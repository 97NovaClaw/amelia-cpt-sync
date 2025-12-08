<?php
/**
 * ART Amelia Hook Trigger Class
 *
 * Centralized hook triggering for Amelia integration.
 * - Fires Amelia's native hooks after Direct DB writes
 * - Ensures integrations work (Email, SMS, Calendar, Webhooks)
 * - Builds correct payload structures
 *
 * @package AmeliaCPTSync
 * @subpackage ART
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class ART_Amelia_Hook_Trigger {
    
    /**
     * WordPress database object
     *
     * @var wpdb
     */
    private $wpdb;
    
    /**
     * Data Manager instance
     *
     * @var ART_Amelia_Data_Manager
     */
    private $data_manager;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->data_manager = ART_Amelia_Data_Manager::get_instance();
    }
    
    /**
     * Trigger hooks after appointment creation
     *
     * Fires the complete sequence of Amelia hooks to enable:
     * - Email notifications
     * - SMS notifications
     * - Google Calendar sync
     * - Outlook Calendar sync
     * - Webhooks
     *
     * @param int $appointment_id The appointment ID that was just created
     * @return bool True on success
     */
    public function trigger_appointment_added($appointment_id) {
        amelia_cpt_sync_debug_log('ART Hook Trigger: Starting hook sequence for appointment #' . $appointment_id);
        
        // Step 1: Fetch complete appointment data from DB
        $appointment = $this->fetch_appointment_with_relations($appointment_id);
        
        if (!$appointment) {
            amelia_cpt_sync_debug_log('ART Hook Trigger: ERROR - Could not fetch appointment #' . $appointment_id);
            return false;
        }
        
        // Step 2: Fetch service data
        $service = $this->data_manager->get_service($appointment['serviceId']);
        
        if (!$service) {
            amelia_cpt_sync_debug_log('ART Hook Trigger: WARNING - Could not fetch service, using minimal data');
            $service = ['id' => $appointment['serviceId'], 'name' => 'Unknown Service'];
        }
        
        // Step 3: Build payment data
        $payment_data = $this->build_payment_data($appointment);
        
        // Step 4: Fire the main hook (triggers most integrations)
        amelia_cpt_sync_debug_log('ART Hook Trigger: Firing amelia_after_appointment_added');
        do_action('amelia_after_appointment_added', $appointment, $service, $payment_data);
        
        // Step 5: Fire booking-specific hook
        amelia_cpt_sync_debug_log('ART Hook Trigger: Firing amelia_after_booking_added');
        do_action('amelia_after_booking_added', $appointment);
        
        // Step 6: Fire post-booking actions (email, calendar, etc.)
        $result_data = $this->build_result_data($appointment, $service);
        amelia_cpt_sync_debug_log('ART Hook Trigger: Firing amelia_before_post_booking_actions');
        do_action('amelia_before_post_booking_actions', $result_data);
        
        // Step 7: Fire webhook-specific action
        $reservation = $appointment; // Reservation is essentially the appointment
        $bookings = $appointment['bookings'] ?? [];
        $container = null; // Container is Amelia's DI container - may not be needed for hooks
        
        amelia_cpt_sync_debug_log('ART Hook Trigger: Firing AmeliaAppointmentBookingAdded');
        do_action('AmeliaAppointmentBookingAdded', $reservation, $bookings, $container);
        
        amelia_cpt_sync_debug_log('ART Hook Trigger: All hooks fired successfully for appointment #' . $appointment_id);
        
        return true;
    }
    
    /**
     * Trigger hooks after appointment update
     *
     * @param int $appointment_id Appointment ID
     * @param array $old_appointment_data Optional old data for comparison
     * @return bool True on success
     */
    public function trigger_appointment_updated($appointment_id, $old_appointment_data = null) {
        amelia_cpt_sync_debug_log('ART Hook Trigger: Firing update hooks for appointment #' . $appointment_id);
        
        $appointment = $this->fetch_appointment_with_relations($appointment_id);
        
        if (!$appointment) {
            return false;
        }
        
        $service = $this->data_manager->get_service($appointment['serviceId']);
        
        // Fire hooks
        do_action('amelia_after_appointment_updated', $appointment, $old_appointment_data, [], $service, []);
        
        amelia_cpt_sync_debug_log('ART Hook Trigger: Update hooks fired for appointment #' . $appointment_id);
        
        return true;
    }
    
    /**
     * Trigger hooks after appointment deletion
     *
     * @param int $appointment_id Appointment ID
     * @return bool True on success
     */
    public function trigger_appointment_deleted($appointment_id) {
        amelia_cpt_sync_debug_log('ART Hook Trigger: Firing delete hooks for appointment #' . $appointment_id);
        
        // We might not be able to fetch full data if already deleted
        // Pass minimal data
        $appointment = ['id' => $appointment_id];
        
        do_action('amelia_after_appointment_deleted', $appointment);
        
        amelia_cpt_sync_debug_log('ART Hook Trigger: Delete hooks fired for appointment #' . $appointment_id);
        
        return true;
    }
    
    // ========================================
    // HELPER METHODS - Build Hook Payloads
    // ========================================
    
    /**
     * Fetch complete appointment data with customer bookings
     *
     * Builds the nested structure that Amelia hooks expect
     *
     * @param int $appointment_id Appointment ID
     * @return array|null Complete appointment array or null
     */
    private function fetch_appointment_with_relations($appointment_id) {
        // Query appointment
        $appointment = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->wpdb->prefix}amelia_appointments WHERE id = %d",
            $appointment_id
        ), ARRAY_A);
        
        if (!$appointment) {
            return null;
        }
        
        // Query customer bookings with customer data
        $bookings = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT 
                cb.id,
                cb.appointmentId,
                cb.customerId,
                cb.status,
                cb.price,
                cb.persons,
                cb.info,
                u.firstName,
                u.lastName,
                u.email,
                u.phone
             FROM {$this->wpdb->prefix}amelia_customer_bookings cb
             JOIN {$this->wpdb->prefix}amelia_users u ON cb.customerId = u.id
             WHERE cb.appointmentId = %d",
            $appointment_id
        ), ARRAY_A);
        
        // Normalize bookings to Amelia's expected format
        $appointment['bookings'] = array_map(function($booking) {
            return [
                'id' => (int) $booking['id'],
                'appointmentId' => (int) $booking['appointmentId'],
                'customerId' => (int) $booking['customerId'],
                'customer' => [
                    'id' => (int) $booking['customerId'],
                    'firstName' => $booking['firstName'],
                    'lastName' => $booking['lastName'],
                    'email' => $booking['email'],
                    'phone' => $booking['phone'] ?? ''
                ],
                'status' => $booking['status'],
                'price' => (float) $booking['price'],
                'persons' => (int) $booking['persons'],
                'info' => $booking['info'] ? json_decode($booking['info'], true) : []
            ];
        }, $bookings);
        
        // Convert numeric string keys to proper types
        $appointment['id'] = (int) $appointment['id'];
        $appointment['serviceId'] = (int) $appointment['serviceId'];
        $appointment['providerId'] = (int) $appointment['providerId'];
        $appointment['locationId'] = $appointment['locationId'] ? (int) $appointment['locationId'] : null;
        $appointment['notifyParticipants'] = (int) $appointment['notifyParticipants'];
        
        return $appointment;
    }
    
    /**
     * Build payment data structure
     *
     * @param array $appointment Appointment data
     * @return array Payment data for hooks
     */
    private function build_payment_data($appointment) {
        $first_booking = $appointment['bookings'][0] ?? null;
        
        return [
            'gateway' => 'onSite', // Default to on-site payment
            'amount' => $first_booking ? (float) $first_booking['price'] : 0,
            'currency' => 'USD', // Could be made configurable
            'data' => (object) []
        ];
    }
    
    /**
     * Build result data for post-booking actions
     *
     * This is the structure passed to amelia_before_post_booking_actions
     *
     * @param array $appointment Appointment data
     * @param array $service Service data
     * @return array Result data
     */
    private function build_result_data($appointment, $service) {
        $first_booking = $appointment['bookings'][0] ?? null;
        
        return [
            'type' => 'appointment',
            'appointment' => $appointment,
            'booking' => $first_booking,
            'customer' => $first_booking ? $first_booking['customer'] : null,
            'service' => $service,
            'paymentId' => null,
            'packageId' => null,
            'recurring' => []
        ];
    }
}

