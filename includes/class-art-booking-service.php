<?php
/**
 * ART Booking Service
 *
 * Handles booking creation, modification, and deletion.
 * Uses direct database access with API fallback.
 *
 * @package AmeliaCPTSync
 * @subpackage ART
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class Amelia_CPT_Sync_ART_Booking_Service {
    
    /**
     * @var Amelia_CPT_Sync_ART_API_Manager
     */
    private $api_manager;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->api_manager = new Amelia_CPT_Sync_ART_API_Manager();
    }
    
    /**
     * Create an Amelia booking (Main entry point)
     * 
     * Uses hybrid approach:
     * 1. First tries direct database insert (bypasses ALL validation)
     * 2. Falls back to REST API if database method fails
     *
     * @param array $booking_data Booking data
     * @return array|WP_Error Booking response or error
     */
    public function create_booking($booking_data) {
        // Validate required fields first
        $required = array('bookingStart', 'serviceId', 'providerId', 'bookings');
        foreach ($required as $key) {
            if (!isset($booking_data[$key])) {
                return new WP_Error('missing_field', 'Missing required booking field: ' . $key);
            }
        }
        
        if (empty($booking_data['bookings']) || !is_array($booking_data['bookings'])) {
            return new WP_Error('invalid_bookings', 'Bookings must be a non-empty array');
        }
        
        // Try direct database method first (bypasses ALL slot validation)
        amelia_cpt_sync_debug_log('ART Booking: Attempting booking via direct database (hybrid approach)');
        $result = $this->create_booking_via_database($booking_data);
        
        // If database method succeeded, return result
        if (!is_wp_error($result)) {
            return $result;
        }
        
        // Log the database error
        amelia_cpt_sync_debug_log('ART Booking: Database method failed: ' . $result->get_error_message());
        amelia_cpt_sync_debug_log('ART Booking: Falling back to API method');
        
        // Fall back to API method
        return $this->create_booking_via_api($booking_data);
    }
    
    /**
     * Create an Amelia booking using direct database access
     * 
     * This method bypasses the REST API validation by writing directly to Amelia's database.
     * Benefits:
     * - No "time slot unavailable" errors (no validation at all)
     * - Reliable (no container initialization issues)
     * - Full control over the booking
     *
     * @param array $booking_data Booking data
     * @return array|WP_Error Booking response or error
     */
    private function create_booking_via_database($booking_data) {
        global $wpdb;
        
        amelia_cpt_sync_debug_log('ART Booking DB: Creating booking via direct database insert');
        
        // Check if Amelia tables exist
        $appointments_table = $wpdb->prefix . 'amelia_appointments';
        $bookings_table = $wpdb->prefix . 'amelia_customer_bookings';
        $customers_table = $wpdb->prefix . 'amelia_users';
        $payments_table = $wpdb->prefix . 'amelia_payments';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$appointments_table'") !== $appointments_table) {
            amelia_cpt_sync_debug_log('ART Booking DB: Amelia tables not found, falling back to API');
            return new WP_Error('no_tables', 'Amelia tables not found');
        }
        
        try {
            // Start transaction
            $wpdb->query('START TRANSACTION');
            
            // Extract booking info
            $booking_info = $booking_data['bookings'][0] ?? array();
            $customer_data = $booking_info['customer'] ?? array();
            
            // Parse booking start time
            // CRITICAL: Amelia stores ALL appointment times in UTC in the database
            // The datetime from UI is in WordPress timezone, we must convert to UTC
            $booking_start = $booking_data['bookingStart'];
            $duration_seconds = absint($booking_info['duration'] ?? 3600);
            
            amelia_cpt_sync_debug_log('ART Booking DB: Received booking start (WP timezone): ' . $booking_start);
            amelia_cpt_sync_debug_log('ART Booking DB: Duration seconds: ' . $duration_seconds);
            amelia_cpt_sync_debug_log('ART Booking DB: WordPress timezone: ' . wp_timezone_string());
            
            // Create DateTime object in WordPress timezone
            $wp_tz = new DateTimeZone(wp_timezone_string());
            $utc_tz = new DateTimeZone('UTC');
            
            $dt_start = new DateTime($booking_start, $wp_tz);
            $dt_end = clone $dt_start;
            $dt_end->modify('+' . $duration_seconds . ' seconds');
            
            // Convert to UTC for database storage (Amelia requirement)
            $dt_start->setTimezone($utc_tz);
            $dt_end->setTimezone($utc_tz);
            
            $booking_start_formatted = $dt_start->format('Y-m-d H:i:s');
            $booking_end = $dt_end->format('Y-m-d H:i:s');
            
            amelia_cpt_sync_debug_log('ART Booking DB: Formatted start (UTC): ' . $booking_start_formatted);
            amelia_cpt_sync_debug_log('ART Booking DB: Calculated end (UTC): ' . $booking_end);
            
            // Step 1: Find or create customer
            $customer_id = $this->find_or_create_customer($customer_data);
            
            if (is_wp_error($customer_id)) {
                $wpdb->query('ROLLBACK');
                return $customer_id;
            }
            
            // Step 2: Create appointment
            // Using raw SQL to handle NULL values and all columns properly
            $service_id = absint($booking_data['serviceId']);
            $provider_id = absint($booking_data['providerId']);
            $location_id = !empty($booking_data['locationId']) ? absint($booking_data['locationId']) : 'NULL';
            $location_sql = $location_id === 'NULL' ? 'NULL' : '%d';
            
            if ($location_id === 'NULL') {
                $wpdb->query($wpdb->prepare(
                    "INSERT INTO $appointments_table 
                    (status, bookingStart, bookingEnd, notifyParticipants, createPaymentLinks, serviceId, packageId, providerId, locationId, internalNotes, googleCalendarEventId, googleMeetUrl, outlookCalendarEventId, microsoftTeamsUrl, appleCalendarEventId, zoomMeeting, lessonSpace, parentId, error) 
                    VALUES (%s, %s, %s, 1, 1, %d, NULL, %d, NULL, %s, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL)",
                    'approved',
                    $booking_start_formatted,
                    $booking_end,
                    $service_id,
                    $provider_id,
                    'Created by ART Module'
                ));
            } else {
                $wpdb->query($wpdb->prepare(
                    "INSERT INTO $appointments_table 
                    (status, bookingStart, bookingEnd, notifyParticipants, createPaymentLinks, serviceId, packageId, providerId, locationId, internalNotes, googleCalendarEventId, googleMeetUrl, outlookCalendarEventId, microsoftTeamsUrl, appleCalendarEventId, zoomMeeting, lessonSpace, parentId, error) 
                    VALUES (%s, %s, %s, 1, 1, %d, NULL, %d, %d, %s, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL)",
                    'approved',
                    $booking_start_formatted,
                    $booking_end,
                    $service_id,
                    $provider_id,
                    $location_id,
                    'Created by ART Module'
                ));
            }
            
            $appointment_id = $wpdb->insert_id;
            
            if (!$appointment_id) {
                $wpdb->query('ROLLBACK');
                amelia_cpt_sync_debug_log('ART Booking DB: Failed to create appointment - ' . $wpdb->last_error);
                return new WP_Error('appointment_error', 'Failed to create appointment: ' . $wpdb->last_error);
            }
            
            amelia_cpt_sync_debug_log('ART Booking DB: Created appointment #' . $appointment_id);
            
            // Step 3: Create customer booking
            // Note: Using raw SQL to handle NULL values properly
            $token = wp_generate_uuid4();
            $info_json = wp_json_encode(array(
                'firstName' => $customer_data['firstName'] ?? '',
                'lastName' => $customer_data['lastName'] ?? '',
                'phone' => $customer_data['phone'] ?? '',
                'locale' => 'en_US',
            ));
            $created_time = current_time('mysql');
            $price = floatval($booking_info['price'] ?? 0);
            $persons = absint($booking_info['persons'] ?? 1);
            
            $booking_insert_result = $wpdb->query($wpdb->prepare(
                "INSERT INTO $bookings_table 
                (appointmentId, customerId, status, price, tax, persons, couponId, token, customFields, info, utcOffset, aggregatedPrice, packageCustomerServiceId, duration, created, actionsCompleted) 
                VALUES (%d, %d, %s, %f, NULL, %d, NULL, %s, %s, %s, NULL, 1, NULL, %d, %s, 1)",
                $appointment_id,
                $customer_id,
                'approved',
                $price,
                $persons,
                $token,
                '{}',
                $info_json,
                $duration_seconds,
                $created_time
            ));
            
            $booking_id = $wpdb->insert_id;
            
            $booking_id = $wpdb->insert_id;
            
            if (!$booking_id) {
                $wpdb->query('ROLLBACK');
                amelia_cpt_sync_debug_log('ART Booking DB: Failed to create booking - ' . $wpdb->last_error);
                return new WP_Error('booking_error', 'Failed to create booking: ' . $wpdb->last_error);
            }
            
            amelia_cpt_sync_debug_log('ART Booking DB: Created booking #' . $booking_id);
            
            // Step 4: Create payment record
            // Using raw SQL to handle NULL values properly
            // IMPORTANT: Use local time, not UTC
            $payment_datetime = current_time('mysql');
            $payment_amount = floatval($booking_info['price'] ?? 0);
            
            $wpdb->query($wpdb->prepare(
                "INSERT INTO $payments_table 
                (customerBookingId, amount, dateTime, status, gateway, gatewayTitle, data, packageCustomerId, parentId, entity, created, actionsCompleted, triggeredActions, wcOrderId, wcOrderItemId, transactionId, transfers, invoiceNumber) 
                VALUES (%d, %f, %s, %s, %s, %s, %s, NULL, NULL, %s, %s, 1, NULL, NULL, NULL, NULL, NULL, NULL)",
                $booking_id,
                $payment_amount,
                $payment_datetime,
                'pending',
                'onSite',
                'On-site',
                '',
                'appointment',
                $payment_datetime
            ));
            
            $payment_id = $wpdb->insert_id;
            amelia_cpt_sync_debug_log('ART Booking DB: Created payment #' . $payment_id);
            
            // Commit transaction
            $wpdb->query('COMMIT');
            
            amelia_cpt_sync_debug_log('ART Booking DB: Successfully created booking #' . $booking_id . ' (appointment #' . $appointment_id . ')');
            
            // Trigger Amelia hooks for compatibility
            $appointmentData = array(
                'type' => 'appointment',
                'bookingStart' => $booking_start_formatted,
                'serviceId' => absint($booking_data['serviceId']),
                'providerId' => absint($booking_data['providerId']),
                'bookings' => $booking_data['bookings'],
            );
            do_action('amelia_after_booking_added', $appointmentData);
            
            // Return in same format as API response
            return array(
                'message' => 'Successfully added booking (via direct database)',
                'data' => array(
                    'appointment' => array(
                        'id' => $appointment_id,
                        'bookings' => array(
                            array(
                                'id' => $booking_id,
                                'customerId' => $customer_id
                            )
                        )
                    ),
                    'customer' => array(
                        'id' => $customer_id
                    )
                )
            );
            
        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
            amelia_cpt_sync_debug_log('ART Booking DB: Exception - ' . $e->getMessage());
            return new WP_Error('db_exception', $e->getMessage());
        }
    }
    
    /**
     * Find or create a customer in Amelia
     *
     * @param array $customer_data Customer data (email, firstName, lastName, phone)
     * @return int|WP_Error Customer ID or error
     */
    private function find_or_create_customer($customer_data) {
        global $wpdb;
        
        $customers_table = $wpdb->prefix . 'amelia_users';
        
        if (empty($customer_data['email'])) {
            return new WP_Error('no_email', 'Customer email is required');
        }
        
        // Look for existing customer
        $existing_customer = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $customers_table WHERE email = %s AND type = 'customer' LIMIT 1",
            $customer_data['email']
        ));
        
        if ($existing_customer) {
            amelia_cpt_sync_debug_log('ART Booking DB: Found existing customer #' . $existing_customer->id);
            return intval($existing_customer->id);
        }
        
        // Create new customer
        $wpdb->insert(
            $customers_table,
            array(
                'status' => 'visible',
                'type' => 'customer',
                'firstName' => sanitize_text_field($customer_data['firstName'] ?? ''),
                'lastName' => sanitize_text_field($customer_data['lastName'] ?? ''),
                'email' => sanitize_email($customer_data['email']),
                'phone' => sanitize_text_field($customer_data['phone'] ?? ''),
                'countryPhoneIso' => '',
                'gender' => null,
                'birthday' => null,
                'note' => 'Created by ART Module',
                'pictureFullPath' => null,
                'pictureThumbPath' => null,
                'translations' => '{}',
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        $customer_id = $wpdb->insert_id;
        
        if (!$customer_id) {
            return new WP_Error('customer_error', 'Failed to create customer: ' . $wpdb->last_error);
        }
        
        amelia_cpt_sync_debug_log('ART Booking DB: Created new customer #' . $customer_id);
        return $customer_id;
    }
    
    /**
     * Create an Amelia booking via REST API (Fallback method)
     *
     * @param array $booking_data Booking data
     * @return array|WP_Error Booking response or error
     */
    private function create_booking_via_api($booking_data) {
        // Build payload in EXACT format from Amelia API docs
        $booking_payload = array(
            'type' => 'appointment',
            'bookings' => $booking_data['bookings'],
            'payment' => array(
                'gateway' => 'onSite',
                'currency' => 'USD',
                'data' => (object) array()
            ),
            'bookingStart' => $booking_data['bookingStart'],
            'notifyParticipants' => 1,
            'providerId' => absint($booking_data['providerId']),
            'serviceId' => absint($booking_data['serviceId']),
            'isBackendOrCabinet' => true,
            'packageBookingFromBackend' => true
        );
        
        // Only add locationId if provided
        if (!empty($booking_data['locationId']) && $booking_data['locationId'] > 0) {
            $booking_payload['locationId'] = absint($booking_data['locationId']);
        }
        
        amelia_cpt_sync_debug_log('ART Booking API: Creating booking for service #' . $booking_data['serviceId']);
        
        $response = $this->api_manager->api_request('/bookings', 'POST', $booking_payload);
        
        if (is_wp_error($response)) {
            amelia_cpt_sync_debug_log('ART Booking API: Failed - ' . $response->get_error_message());
            return $response;
        }
        
        // Check for Amelia-specific error conditions
        if (!empty($response['data']['timeSlotUnavailable'])) {
            return new WP_Error(
                'time_slot_unavailable', 
                'The selected time slot is unavailable. Try selecting a time from the available slots list.'
            );
        }
        
        // Extract booking ID
        $booking_id = $response['data']['appointment']['bookings'][0]['id'] ?? null;
        $appointment_id = $response['data']['appointment']['id'] ?? null;
        
        if ($booking_id) {
            amelia_cpt_sync_debug_log('ART Booking API: Successfully created booking #' . $booking_id);
        } else {
            return new WP_Error('booking_id_missing', 'Booking may have failed - no booking ID returned');
        }
        
        return $response;
    }
    
    /**
     * Update an existing Amelia appointment (reschedule)
     * 
     * Uses hybrid approach:
     * 1. First tries direct database update (bypasses validation)
     * 2. Falls back to REST API if database method fails
     *
     * @param int $appointment_id Amelia appointment ID
     * @param array $update_data Data to update
     * @return array|WP_Error Response or error
     */
    public function update_appointment($appointment_id, $update_data) {
        if (!$appointment_id) {
            return new WP_Error('invalid_appointment', 'Appointment ID is required');
        }
        
        amelia_cpt_sync_debug_log('ART Booking: Attempting update via direct database (hybrid approach)');
        $result = $this->update_appointment_via_database($appointment_id, $update_data);
        
        if (!is_wp_error($result)) {
            return $result;
        }
        
        amelia_cpt_sync_debug_log('ART Booking: Database update failed: ' . $result->get_error_message());
        amelia_cpt_sync_debug_log('ART Booking: Falling back to API method');
        
        return $this->update_appointment_via_api($appointment_id, $update_data);
    }
    
    /**
     * Update appointment via direct database
     *
     * @param int $appointment_id Amelia appointment ID
     * @param array $update_data Data to update
     * @return array|WP_Error Response or error
     */
    private function update_appointment_via_database($appointment_id, $update_data) {
        global $wpdb;
        
        $appointments_table = $wpdb->prefix . 'amelia_appointments';
        $bookings_table = $wpdb->prefix . 'amelia_customer_bookings';
        
        amelia_cpt_sync_debug_log('ART Booking DB: Updating appointment #' . $appointment_id . ' via database');
        
        // Check if appointment exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $appointments_table WHERE id = %d",
            $appointment_id
        ));
        
        if (!$existing) {
            return new WP_Error('appointment_not_found', 'Appointment not found');
        }
        
        $update_fields = array();
        $format = array();
        
        // Handle bookingStart (convert to UTC if provided)
        if (isset($update_data['bookingStart'])) {
            $wp_tz = new DateTimeZone(wp_timezone_string());
            $utc_tz = new DateTimeZone('UTC');
            $dt_start = new DateTime($update_data['bookingStart'], $wp_tz);
            $dt_start->setTimezone($utc_tz);
            $update_fields['bookingStart'] = $dt_start->format('Y-m-d H:i:s');
            $format[] = '%s';
            
            amelia_cpt_sync_debug_log('ART Booking DB: New start time (UTC): ' . $update_fields['bookingStart']);
            
            // Calculate bookingEnd based on duration
            $booking = $wpdb->get_row($wpdb->prepare(
                "SELECT duration FROM $bookings_table WHERE appointmentId = %d LIMIT 1",
                $appointment_id
            ));
            
            if ($booking && $booking->duration) {
                $dt_end = clone $dt_start;
                $dt_end->modify('+' . intval($booking->duration) . ' seconds');
                $update_fields['bookingEnd'] = $dt_end->format('Y-m-d H:i:s');
                $format[] = '%s';
                
                amelia_cpt_sync_debug_log('ART Booking DB: New end time (UTC): ' . $update_fields['bookingEnd']);
            }
        }
        
        if (isset($update_data['providerId'])) {
            $update_fields['providerId'] = absint($update_data['providerId']);
            $format[] = '%d';
        }
        
        if (isset($update_data['locationId'])) {
            $update_fields['locationId'] = absint($update_data['locationId']);
            $format[] = '%d';
        }
        
        if (empty($update_fields)) {
            return new WP_Error('no_update_data', 'No valid update data provided');
        }
        
        // Update the appointment
        $result = $wpdb->update(
            $appointments_table,
            $update_fields,
            array('id' => $appointment_id),
            $format,
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('update_failed', 'Database update failed: ' . $wpdb->last_error);
        }
        
        amelia_cpt_sync_debug_log('ART Booking DB: Successfully updated appointment #' . $appointment_id);
        
        // Trigger Amelia hook for compatibility
        do_action('amelia_after_appointment_updated', $appointment_id, $update_data);
        
        return array(
            'message' => 'Successfully updated appointment',
            'data' => array('appointment' => array('id' => $appointment_id))
        );
    }
    
    /**
     * Update appointment via REST API (Fallback)
     *
     * @param int $appointment_id Amelia appointment ID
     * @param array $update_data Data to update
     * @return array|WP_Error Response or error
     */
    private function update_appointment_via_api($appointment_id, $update_data) {
        amelia_cpt_sync_debug_log('ART Booking API: Updating appointment #' . $appointment_id);
        
        $response = $this->api_manager->api_request('/appointments/' . absint($appointment_id), 'POST', $update_data);
        
        if (is_wp_error($response)) {
            amelia_cpt_sync_debug_log('ART Booking API: Update failed - ' . $response->get_error_message());
            return $response;
        }
        
        amelia_cpt_sync_debug_log('ART Booking API: Successfully updated appointment #' . $appointment_id);
        return $response;
    }
    
    /**
     * Delete an Amelia appointment
     * 
     * Uses hybrid approach:
     * 1. First tries direct database delete (bypasses validation)
     * 2. Falls back to REST API if database method fails
     *
     * @param int $appointment_id Amelia appointment ID
     * @return array|WP_Error Response or error
     */
    public function delete_appointment($appointment_id) {
        if (!$appointment_id) {
            return new WP_Error('invalid_appointment', 'Appointment ID is required');
        }
        
        amelia_cpt_sync_debug_log('ART Booking: Attempting delete via direct database (hybrid approach)');
        $result = $this->delete_appointment_via_database($appointment_id);
        
        if (!is_wp_error($result)) {
            return $result;
        }
        
        amelia_cpt_sync_debug_log('ART Booking: Database delete failed: ' . $result->get_error_message());
        amelia_cpt_sync_debug_log('ART Booking: Falling back to API method');
        
        return $this->delete_appointment_via_api($appointment_id);
    }
    
    /**
     * Delete appointment via direct database
     *
     * @param int $appointment_id Amelia appointment ID
     * @return array|WP_Error Response or error
     */
    private function delete_appointment_via_database($appointment_id) {
        global $wpdb;
        
        $appointments_table = $wpdb->prefix . 'amelia_appointments';
        $bookings_table = $wpdb->prefix . 'amelia_customer_bookings';
        $payments_table = $wpdb->prefix . 'amelia_payments';
        
        amelia_cpt_sync_debug_log('ART Booking DB: Deleting appointment #' . $appointment_id . ' via database');
        
        // Check if appointment exists and get data for hooks
        $appointment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $appointments_table WHERE id = %d",
            $appointment_id
        ), ARRAY_A);
        
        if (!$appointment) {
            return new WP_Error('appointment_not_found', 'Appointment not found');
        }
        
        // Start transaction
        $wpdb->query('START TRANSACTION');
        
        try {
            // Delete in reverse order to respect foreign key constraints
            
            // 1. Delete payments (via customer bookings)
            $deleted_payments = $wpdb->query($wpdb->prepare(
                "DELETE p FROM $payments_table p
                 INNER JOIN $bookings_table cb ON p.customerBookingId = cb.id
                 WHERE cb.appointmentId = %d",
                $appointment_id
            ));
            
            amelia_cpt_sync_debug_log('ART Booking DB: Deleted ' . ($deleted_payments ?: 0) . ' payment(s)');
            
            // 2. Delete customer bookings
            $deleted_bookings = $wpdb->delete(
                $bookings_table,
                array('appointmentId' => $appointment_id),
                array('%d')
            );
            
            amelia_cpt_sync_debug_log('ART Booking DB: Deleted ' . ($deleted_bookings ?: 0) . ' booking(s)');
            
            // 3. Delete appointment
            $deleted_appointment = $wpdb->delete(
                $appointments_table,
                array('id' => $appointment_id),
                array('%d')
            );
            
            if ($deleted_appointment === false) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('delete_failed', 'Failed to delete appointment: ' . $wpdb->last_error);
            }
            
            $wpdb->query('COMMIT');
            
            amelia_cpt_sync_debug_log('ART Booking DB: Successfully deleted appointment #' . $appointment_id);
            
            // Trigger Amelia hook for compatibility
            do_action('amelia_after_appointment_deleted', $appointment);
            
            return array(
                'message' => 'Successfully deleted appointment',
                'data' => array('appointment' => array('id' => $appointment_id))
            );
            
        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
            amelia_cpt_sync_debug_log('ART Booking DB: Delete exception - ' . $e->getMessage());
            return new WP_Error('delete_exception', $e->getMessage());
        }
    }
    
    /**
     * Delete appointment via REST API (Fallback)
     *
     * @param int $appointment_id Amelia appointment ID
     * @return array|WP_Error Response or error
     */
    private function delete_appointment_via_api($appointment_id) {
        amelia_cpt_sync_debug_log('ART Booking API: Deleting appointment #' . $appointment_id);
        
        $response = $this->api_manager->api_request('/appointments/delete/' . absint($appointment_id), 'POST');
        
        if (is_wp_error($response)) {
            amelia_cpt_sync_debug_log('ART Booking API: Delete failed - ' . $response->get_error_message());
            return $response;
        }
        
        amelia_cpt_sync_debug_log('ART Booking API: Successfully deleted appointment #' . $appointment_id);
        return $response;
    }
    
    /**
     * Update appointment status (cancel, approve, etc.)
     *
     * @param int $appointment_id Amelia appointment ID
     * @param string $status New status (approved, pending, canceled, rejected, no-show)
     * @return array|WP_Error Response or error
     */
    public function update_appointment_status($appointment_id, $status) {
        if (!$appointment_id) {
            return new WP_Error('invalid_appointment', 'Appointment ID is required');
        }
        
        $valid_statuses = array('approved', 'pending', 'canceled', 'rejected', 'no-show');
        if (!in_array($status, $valid_statuses, true)) {
            return new WP_Error('invalid_status', 'Invalid status: ' . $status);
        }
        
        amelia_cpt_sync_debug_log('ART Booking: Updating appointment #' . $appointment_id . ' status to ' . $status);
        
        $response = $this->api_manager->api_request('/appointments/status/' . absint($appointment_id), 'POST', array(
            'status' => $status,
            'packageCustomerId' => null
        ));
        
        if (is_wp_error($response)) {
            amelia_cpt_sync_debug_log('ART Booking: Update status failed - ' . $response->get_error_message());
            return $response;
        }
        
        amelia_cpt_sync_debug_log('ART Booking: Successfully updated status to ' . $status);
        return $response;
    }
}

