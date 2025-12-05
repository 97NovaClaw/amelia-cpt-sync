<?php
/**
 * ART Booking Manager
 *
 * Centralized booking decision engine that handles all booking logic:
 * - Detects what changed (datetime, provider, status, service)
 * - Determines appropriate action (create, update, upgrade, downgrade)
 * - Executes the action via Booking Service
 * - Logs changes for audit trail
 *
 * @package AmeliaCPTSync
 * @subpackage ART
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class Amelia_CPT_Sync_ART_Booking_Manager {
    
    /**
     * Process a booking request
     * 
     * Main entry point - receives desired booking state and figures out what to do
     *
     * @param array $params Booking parameters
     * @return array|WP_Error Result with action taken and booking details
     */
    public function process_booking($params) {
        // Validate required params
        $required = array('request_id', 'service_id', 'duration', 'slot_datetime', 'provider_id', 'desired_status');
        foreach ($required as $field) {
            if (empty($params[$field])) {
                return new WP_Error('missing_field', sprintf('Missing required field: %s', $field));
            }
        }
        
        // Get existing booking (if any)
        $existing = $this->get_existing_booking($params['request_id']);
        
        // Detect what changed
        $changes = $this->detect_changes($existing, $params);
        
        // Determine appropriate action
        $action = $this->determine_action($existing, $changes, $params['desired_status']);
        
        amelia_cpt_sync_debug_log('ART Booking Manager: Action determined', array(
            'action_type' => $action['type'],
            'changes' => $changes
        ));
        
        // Execute the action
        $result = $this->execute_action($action, $existing, $params);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Log changes for audit trail
        $this->log_change($params['request_id'], $action, $changes, $result);
        
        return $result;
    }
    
    /**
     * Get existing booking details for a request
     *
     * @param int $request_id Request ID
     * @return array|null Existing booking data or null
     */
    private function get_existing_booking($request_id) {
        global $wpdb;
        
        // Get booking link
        $booking_link = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}art_booking_links WHERE request_id = %d ORDER BY created_at DESC LIMIT 1",
            $request_id
        ));
        
        if (!$booking_link || !$booking_link->amelia_appointment_id) {
            return null;
        }
        
        // Get appointment details from Amelia
        $appointment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}amelia_appointments WHERE id = %d",
            $booking_link->amelia_appointment_id
        ));
        
        if (!$appointment) {
            return null;
        }
        
        // Get request details for service comparison
        $request = $wpdb->get_row($wpdb->prepare(
            "SELECT service_id, duration_seconds FROM {$wpdb->prefix}art_requests WHERE id = %d",
            $request_id
        ));
        
        // Convert UTC to WordPress timezone for comparison
        $wp_tz = wp_timezone();
        $utc_tz = new DateTimeZone('UTC');
        $dt_start = new DateTime($appointment->bookingStart, $utc_tz);
        $dt_start->setTimezone($wp_tz);
        
        return array(
            'appointment_id' => $booking_link->amelia_appointment_id,
            'booking_id' => $booking_link->amelia_booking_id,
            'booking_type' => $booking_link->booking_type ?? 'confirmed',
            'datetime' => $dt_start->format('Y-m-d H:i'),
            'provider_id' => $appointment->providerId,
            'location_id' => $appointment->locationId ?? null,
            'service_id' => $appointment->serviceId,
            'duration' => $request->duration_seconds ?? 0
        );
    }
    
    /**
     * Detect what changed between existing and new booking
     *
     * @param array|null $existing Existing booking data
     * @param array $new New booking parameters
     * @return array List of changes
     */
    private function detect_changes($existing, $new) {
        $changes = array();
        
        if (!$existing) {
            $changes[] = array('field' => 'booking', 'type' => 'new');
            return $changes;
        }
        
        // Normalize datetime for comparison (remove seconds)
        $existing_dt = date('Y-m-d H:i', strtotime($existing['datetime']));
        $new_dt = date('Y-m-d H:i', strtotime($new['slot_datetime']));
        
        // Check datetime
        if ($existing_dt !== $new_dt) {
            $changes[] = array(
                'field' => 'datetime',
                'from' => $existing['datetime'],
                'to' => $new['slot_datetime']
            );
        }
        
        // Check provider
        if (intval($existing['provider_id']) !== intval($new['provider_id'])) {
            $changes[] = array(
                'field' => 'provider',
                'from' => $existing['provider_id'],
                'to' => $new['provider_id']
            );
        }
        
        // Check service
        if (intval($existing['service_id']) !== intval($new['service_id'])) {
            $changes[] = array(
                'field' => 'service',
                'from' => $existing['service_id'],
                'to' => $new['service_id']
            );
        }
        
        // Check duration
        if (intval($existing['duration']) !== intval($new['duration'])) {
            $changes[] = array(
                'field' => 'duration',
                'from' => $existing['duration'],
                'to' => $new['duration']
            );
        }
        
        // Check status
        if ($existing['booking_type'] !== $new['desired_status']) {
            $changes[] = array(
                'field' => 'status',
                'from' => $existing['booking_type'],
                'to' => $new['desired_status']
            );
        }
        
        return $changes;
    }
    
    /**
     * Determine what action to take based on changes and desired status
     *
     * @param array|null $existing Existing booking
     * @param array $changes Detected changes
     * @param string $desired_status Desired booking status
     * @return array Action descriptor
     */
    private function determine_action($existing, $changes, $desired_status) {
        // No existing booking
        if (!$existing) {
            return array(
                'type' => 'create',
                'status' => $desired_status,
                'message' => $desired_status === 'tentative' 
                    ? 'Create tentative booking' 
                    : 'Create confirmed booking'
            );
        }
        
        // Parse changes
        $has_datetime_change = false;
        $has_provider_change = false;
        $has_service_change = false;
        $has_duration_change = false;
        $has_status_change = false;
        
        foreach ($changes as $change) {
            switch ($change['field']) {
                case 'datetime':
                    $has_datetime_change = true;
                    break;
                case 'provider':
                    $has_provider_change = true;
                    break;
                case 'service':
                    $has_service_change = true;
                    break;
                case 'duration':
                    $has_duration_change = true;
                    break;
                case 'status':
                    $has_status_change = true;
                    break;
            }
        }
        
        $has_details_change = $has_datetime_change || $has_provider_change || $has_service_change || $has_duration_change;
        
        // Service/duration change = must create new booking (different appointment type)
        if ($has_service_change || $has_duration_change) {
            return array(
                'type' => 'replace',
                'status' => $desired_status,
                'message' => 'Service or duration changed - will delete old booking and create new',
                'requires_confirmation' => true,
                'confirmation_message' => 'Changing service/duration requires a new booking. Delete existing and create new?'
            );
        }
        
        // Determine action based on change combinations
        if ($has_details_change && $has_status_change) {
            // Reschedule AND change status
            return array(
                'type' => 'reschedule_and_change_status',
                'status' => $desired_status,
                'message' => sprintf('Reschedule and %s', $desired_status === 'confirmed' ? 'upgrade to confirmed' : 'downgrade to tentative'),
                'requires_confirmation' => true,
                'confirmation_message' => sprintf('Update booking details and %s?', $desired_status === 'confirmed' ? 'confirm' : 'mark as tentative')
            );
        } elseif ($has_details_change) {
            // Just reschedule (keep current status)
            return array(
                'type' => 'reschedule',
                'status' => $existing['booking_type'], // Preserve current status
                'message' => 'Update booking details (keep current status)',
                'requires_confirmation' => true,
                'confirmation_message' => 'Update booking with new date/time/provider?'
            );
        } elseif ($has_status_change) {
            // Just status change
            if ($desired_status === 'confirmed') {
                return array(
                    'type' => 'upgrade',
                    'status' => 'confirmed',
                    'message' => 'Upgrade tentative to confirmed',
                    'requires_confirmation' => true,
                    'confirmation_message' => 'Confirm this tentative booking?'
                );
            } else {
                return array(
                    'type' => 'downgrade',
                    'status' => 'tentative',
                    'message' => 'Downgrade confirmed to tentative',
                    'requires_confirmation' => true,
                    'confirmation_message' => 'Change this confirmed booking to tentative status?'
                );
            }
        } else {
            // No changes at all
            $current_status = $existing['booking_type'];
            return array(
                'type' => 'no_change',
                'status' => $current_status,
                'message' => sprintf('Booking already set as %s with these details', $current_status),
                'requires_confirmation' => false
            );
        }
    }
    
    /**
     * Execute the determined action
     *
     * @param array $action Action descriptor
     * @param array|null $existing Existing booking
     * @param array $params New booking parameters
     * @return array|WP_Error Result
     */
    private function execute_action($action, $existing, $params) {
        $booking_service = new Amelia_CPT_Sync_ART_Booking_Service();
        
        switch ($action['type']) {
            case 'create':
                return $this->create_booking($params, $action['status']);
            
            case 'reschedule':
                return $this->reschedule_booking($existing, $params, $existing['booking_type']);
            
            case 'upgrade':
                return $this->change_status($existing, 'confirmed', $params['request_id']);
            
            case 'downgrade':
                return $this->change_status($existing, 'tentative', $params['request_id']);
            
            case 'reschedule_and_change_status':
                // First reschedule
                $reschedule_result = $this->reschedule_booking($existing, $params, $existing['booking_type']);
                if (is_wp_error($reschedule_result)) {
                    return $reschedule_result;
                }
                
                // Then change status
                return $this->change_status($existing, $action['status'], $params['request_id']);
            
            case 'replace':
                // Delete old booking
                $delete_result = $booking_service->delete_appointment($existing['appointment_id']);
                if (is_wp_error($delete_result)) {
                    return $delete_result;
                }
                
                // Create new booking
                return $this->create_booking($params, $action['status']);
            
            case 'no_change':
                return array(
                    'action_taken' => 'no_change',
                    'message' => $action['message'],
                    'booking_id' => $existing['booking_id'],
                    'appointment_id' => $existing['appointment_id']
                );
            
            default:
                return new WP_Error('unknown_action', 'Unknown action type: ' . $action['type']);
        }
    }
    
    /**
     * Create new booking
     */
    private function create_booking($params, $status) {
        global $wpdb;
        
        $requests_table = $wpdb->prefix . 'art_requests';
        $customers_table = $wpdb->prefix . 'art_customers';
        
        // Get request and customer
        $request = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $requests_table WHERE id = %d",
            $params['request_id']
        ));
        
        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $customers_table WHERE id = %d",
            $request->customer_id
        ));
        
        if (!$request || !$customer) {
            return new WP_Error('not_found', 'Request or customer not found');
        }
        
        // Build booking data
        $amelia_status = ($status === 'tentative') ? 'pending' : 'approved';
        
        // Check if user selected an existing Amelia customer (from fuzzy matching)
        $use_existing_customer_id = !empty($params['selected_customer_id']) && $params['selected_customer_id'] > 0 
            ? absint($params['selected_customer_id']) 
            : null;
        
        if ($use_existing_customer_id) {
            // Use selected existing customer from fuzzy matching
            amelia_cpt_sync_debug_log('ART Booking Manager: Using selected Amelia customer #' . $use_existing_customer_id);
            
            $booking_object = array(
                'extras' => array(),
                'customFields' => (object) array(),
                'deposit' => false,
                'locale' => 'en_US',
                'utcOffset' => null,
                'persons' => absint($params['persons']),
                'customerId' => $use_existing_customer_id,
                'customer' => array(
                    'id' => $use_existing_customer_id,
                    'firstName' => $customer->first_name,
                    'lastName' => $customer->last_name,
                    'email' => $customer->email,
                    'phone' => $customer->phone ?? '',
                    'countryPhoneIso' => '',
                    'externalId' => null
                ),
                'duration' => absint($params['duration']),
                'status' => $amelia_status
            );
            
            // Update ART customer record with Amelia ID
            $wpdb->update(
                $customers_table,
                array('amelia_customer_id' => $use_existing_customer_id),
                array('id' => $customer->id),
                array('%d'),
                array('%d')
            );
            
        } else {
            // Default behavior - use cached amelia_customer_id or let booking service create new
            $booking_object = array(
                'extras' => array(),
                'customFields' => (object) array(),
                'deposit' => false,
                'locale' => 'en_US',
                'utcOffset' => null,
                'persons' => absint($params['persons']),
                'customerId' => !empty($customer->amelia_customer_id) ? absint($customer->amelia_customer_id) : null,
                'customer' => array(
                    'id' => !empty($customer->amelia_customer_id) ? absint($customer->amelia_customer_id) : null,
                    'firstName' => $customer->first_name,
                    'lastName' => $customer->last_name,
                    'email' => $customer->email,
                    'phone' => $customer->phone ?? '',
                    'countryPhoneIso' => '',
                    'externalId' => null
                ),
                'duration' => absint($params['duration']),
                'status' => $amelia_status
            );
        }
        
        $booking_data = array(
            'type' => 'appointment',
            'bookings' => array($booking_object),
            'payment' => array(
                'gateway' => 'onSite',
                'currency' => 'USD',
                'data' => (object) array()
            ),
            'bookingStart' => $params['slot_datetime'],
            'notifyParticipants' => 1,
            'providerId' => absint($params['provider_id']),
            'serviceId' => absint($params['service_id'])
        );
        
        if (!empty($params['location_id'])) {
            $booking_data['locationId'] = absint($params['location_id']);
        }
        
        // Create via booking service
        $booking_service = new Amelia_CPT_Sync_ART_Booking_Service();
        $result = $booking_service->create_booking($booking_data);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Extract IDs
        $appointment_id = $result['data']['appointment']['id'] ?? null;
        $booking_id = $result['data']['appointment']['bookings'][0]['id'] ?? null;
        
        // Update request status
        $wpdb->update(
            $requests_table,
            array(
                'status_key' => $status === 'tentative' ? 'tentative' : 'booked',
                'booked_at' => current_time('mysql', 1)
            ),
            array('id' => $params['request_id']),
            array('%s', '%s'),
            array('%d')
        );
        
        // Store booking link
        $booking_links_table = $wpdb->prefix . 'art_booking_links';
        $wpdb->delete($booking_links_table, array('request_id' => $params['request_id']), array('%d'));
        $wpdb->insert(
            $booking_links_table,
            array(
                'request_id' => $params['request_id'],
                'amelia_booking_id' => $booking_id,
                'amelia_appointment_id' => $appointment_id,
                'booking_type' => $status
            ),
            array('%d', '%d', '%d', '%s')
        );
        
        // Log booking created event
        $notes_manager = new ART_Notes_Manager();
        $notes_manager->add_booking_event(
            $params['request_id'],
            'booking_created',
            array(
                'status' => ucfirst($status),
                'provider_id' => $params['provider_id'],
                'service_id' => $params['service_id'],
                'datetime' => $params['slot_datetime'],
                'location_id' => $params['location_id'] ?? null,
                'duration' => $params['duration']
            ),
            get_current_user_id()
        );
        
        // Trigger hook for resource assignment
        do_action('art_booking_created', $params['request_id'], $appointment_id, $params);
        
        return array(
            'action_taken' => 'created',
            'booking_type' => $status,
            'booking_id' => $booking_id,
            'appointment_id' => $appointment_id,
            'message' => $status === 'tentative' 
                ? 'Tentative booking created successfully' 
                : 'Booking confirmed successfully'
        );
    }
    
    /**
     * Reschedule existing booking
     */
    private function reschedule_booking($existing, $params, $keep_status) {
        $booking_service = new Amelia_CPT_Sync_ART_Booking_Service();
        
        $update_data = array(
            'bookingStart' => $params['slot_datetime'],
            'providerId' => $params['provider_id'],
            'notifyParticipants' => 1
        );
        
        if (!empty($params['location_id'])) {
            $update_data['locationId'] = absint($params['location_id']);
        }
        
        $result = $booking_service->update_appointment($existing['appointment_id'], $update_data);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Log reschedule event
        $notes_manager = new ART_Notes_Manager();
        $notes_manager->add_booking_event(
            $params['request_id'],
            'booking_rescheduled',
            array(
                'from_datetime' => $existing['datetime'],
                'to_datetime' => $params['slot_datetime'],
                'from_provider' => $existing['provider_id'],
                'to_provider' => $params['provider_id']
            ),
            get_current_user_id()
        );
        
        // Trigger hook for resource update
        do_action('art_booking_updated', $params['request_id'], $existing['appointment_id'], $params);
        
        return array(
            'action_taken' => 'rescheduled',
            'booking_type' => $keep_status,
            'booking_id' => $existing['booking_id'],
            'appointment_id' => $existing['appointment_id'],
            'message' => 'Booking details updated successfully'
        );
    }
    
    /**
     * Change booking status only
     */
    private function change_status($existing, $new_status, $request_id) {
        global $wpdb;
        
        $booking_service = new Amelia_CPT_Sync_ART_Booking_Service();
        $amelia_status = ($new_status === 'tentative') ? 'pending' : 'approved';
        
        // Update Amelia appointment status
        $result = $booking_service->update_appointment_status($existing['appointment_id'], $amelia_status);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Update booking_type in art_booking_links
        $wpdb->update(
            $wpdb->prefix . 'art_booking_links',
            array('booking_type' => $new_status),
            array('request_id' => $request_id),
            array('%s'),
            array('%d')
        );
        
        // Update request status
        $wpdb->update(
            $wpdb->prefix . 'art_requests',
            array('status_key' => $new_status === 'tentative' ? 'tentative' : 'booked'),
            array('id' => $request_id),
            array('%s'),
            array('%d')
        );
        
        $action_word = ($new_status === 'confirmed') ? 'upgraded' : 'downgraded';
        
        // Log status change event
        $notes_manager = new ART_Notes_Manager();
        $event_type = ($new_status === 'confirmed') ? 'status_upgraded' : 'status_downgraded';
        $notes_manager->add_booking_event(
            $request_id,
            $event_type,
            array(
                'from_status' => $existing['booking_type'],
                'to_status' => $new_status
            ),
            get_current_user_id()
        );
        
        return array(
            'action_taken' => $action_word,
            'booking_type' => $new_status,
            'booking_id' => $existing['booking_id'],
            'appointment_id' => $existing['appointment_id'],
            'message' => sprintf('Booking %s to %s status', $action_word, $new_status)
        );
    }
    
    /**
     * Log change for audit trail
     */
    private function log_change($request_id, $action, $changes, $result) {
        $log_entry = array(
            'request_id' => $request_id,
            'action_type' => $action['type'],
            'changes' => $changes,
            'result' => $result,
            'timestamp' => current_time('mysql'),
            'user_id' => get_current_user_id()
        );
        
        amelia_cpt_sync_debug_log('ART Booking Manager: Action completed', $log_entry);
        
        // TODO: Store in dedicated audit table for notes system
        // For now, just debug log
    }
}

