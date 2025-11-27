<?php
/**
 * ART API Manager
 *
 * Handles communication with Amelia API
 * Phase 4: Locations and customer match
 * Phase 5: Slots, service details, booking creation
 *
 * @package AmeliaCPTSync
 * @subpackage ART
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class Amelia_CPT_Sync_ART_API_Manager {
    
    /**
     * API base URL
     */
    private $api_base_url;
    
    /**
     * API key
     */
    private $api_key;
    
    /**
     * Constructor
     */
    public function __construct() {
        $settings = get_option('art_settings', array());
        $global = $settings['global'] ?? array();
        $this->api_base_url = $global['api_base_url'] ?? '';
        $this->api_key = $global['api_key'] ?? '';
    }
    
    /**
     * Base API request method
     *
     * @param string $endpoint API endpoint (e.g., '/entities')
     * @param string $method HTTP method (GET, POST)
     * @param array|null $body Request body for POST requests
     * @return array|WP_Error Response data or error
     */
    private function request($endpoint, $method = 'GET', $body = null) {
        if (empty($this->api_base_url) || empty($this->api_key)) {
            amelia_cpt_sync_debug_log('ART API: Missing API configuration');
            return new WP_Error('api_config_error', 'Amelia API not configured');
        }
        
        $url = trailingslashit($this->api_base_url) . ltrim($endpoint, '/');
        
        $args = array(
            'method' => $method,
            'headers' => array(
                'Amelia' => $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        );
        
        if ($body && $method === 'POST') {
            $args['body'] = wp_json_encode($body);
        }
        
        amelia_cpt_sync_debug_log('ART API: ' . $method . ' ' . $url);
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            amelia_cpt_sync_debug_log('ART API Error: ' . $response->get_error_message());
            return $response;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body_response = wp_remote_retrieve_body($response);
        $data = json_decode($body_response, true);
        
        amelia_cpt_sync_debug_log('ART API Response Code: ' . $code);
        
        // Log response content for debugging (but truncate large responses like slots)
        if (strlen($body_response) > 5000) {
            amelia_cpt_sync_debug_log('ART API Response Body (truncated)', array(
                'message' => $data['message'] ?? 'No message',
                'data_keys' => isset($data['data']) ? array_keys($data['data']) : [],
                'response_size' => strlen($body_response) . ' bytes'
            ));
        } else {
            amelia_cpt_sync_debug_log('ART API Response Body', $data);
        }
        
        if ($code < 200 || $code > 299) {
            $error_message = isset($data['message']) ? $data['message'] : 'HTTP ' . $code;
            amelia_cpt_sync_debug_log('ART API Error Response (' . $code . '): ' . print_r($data, true));
            amelia_cpt_sync_debug_log('ART API Raw Response Body: ' . substr($body_response, 0, 1000));
            return new WP_Error('api_error', $error_message, array('code' => $code));
        }
        
        amelia_cpt_sync_debug_log('ART API Success: ' . strlen($body_response) . ' bytes received');
        
        return $data;
    }
    
    /**
     * Get locations from Amelia (Phase 4)
     *
     * @return array|WP_Error Array of locations or error
     */
    public function get_locations() {
        $cache_key = 'art_amelia_locations';
        
        // Try cache first
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            amelia_cpt_sync_debug_log('ART API: Using cached locations');
            return $cached;
        }
        
        // Call API
        $response = $this->request('/entities?types=locations', 'GET');
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $locations = $response['data']['locations'] ?? array();
        
        amelia_cpt_sync_debug_log('ART API: Fetched ' . count($locations) . ' locations');
        
        // Cache for 1 hour
        set_transient($cache_key, $locations, HOUR_IN_SECONDS);
        
        return $locations;
    }
    
    /**
     * Get all service categories (Phase 5)
     *
     * @return array|WP_Error Array of categories or error
     */
    public function get_categories() {
        $cache_key = 'art_amelia_categories';
        
        // Try cache first
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            amelia_cpt_sync_debug_log('ART API: Using cached categories');
            return $cached;
        }
        
        // Call API
        $response = $this->request('/entities?types=categories', 'GET');
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $categories = $response['data']['categories'] ?? array();
        
        amelia_cpt_sync_debug_log('ART API: Fetched ' . count($categories) . ' categories');
        
        // Cache for 1 hour
        set_transient($cache_key, $categories, HOUR_IN_SECONDS);
        
        return $categories;
    }
    
    /**
     * Get employees (providers) for a specific service (Phase 5)
     * Uses /users/providers endpoint as per Amelia API documentation
     *
     * @param int $service_id Service ID to filter by (optional)
     * @return array|WP_Error Array of employee objects
     */
    public function get_service_employees($service_id = null) {
        $cache_key = 'art_providers_service_' . ($service_id ?: 'all');
        
        // Try cache first
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            amelia_cpt_sync_debug_log('ART API: Using cached providers for service #' . ($service_id ?: 'all'));
            return $cached;
        }
        
        // Call API: /users/providers?services[0]=X
        // Per Amelia docs: services[] is an array filter
        $endpoint = '/users/providers';
        if ($service_id) {
            $endpoint .= '&services[0]=' . absint($service_id);
        }
        
        $response = $this->request($endpoint, 'GET');
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Response structure: data.users (array of provider objects)
        $employees = $response['data']['users'] ?? array();
        
        amelia_cpt_sync_debug_log('ART API: Fetched ' . count($employees) . ' providers for service #' . ($service_id ?: 'all'));
        
        // Cache for 1 hour
        set_transient($cache_key, $employees, HOUR_IN_SECONDS);
        
        return $employees;
    }
    
    /**
     * Find customer in Amelia by email (Phase 4)
     *
     * @param string $email Customer email
     * @return array|null Customer data or null if not found
     */
    public function find_customer($email) {
        if (empty($email) || !is_email($email)) {
            return null;
        }
        
        $response = $this->request('/users/customers?search=' . urlencode($email), 'GET');
        
        if (is_wp_error($response)) {
            amelia_cpt_sync_debug_log('ART API: Error searching for customer: ' . $response->get_error_message());
            return null;
        }
        
        $users = $response['data']['users'] ?? array();
        
        amelia_cpt_sync_debug_log('ART API: Customer search for "' . $email . '" returned ' . count($users) . ' results');
        
        // Find exact email match (API search is fuzzy)
        foreach ($users as $user) {
            if (isset($user['email']) && strcasecmp($user['email'], $email) === 0) {
                amelia_cpt_sync_debug_log('ART API: Found exact customer match - ID ' . $user['id']);
                return $user;
            }
        }
        
        amelia_cpt_sync_debug_log('ART API: No exact customer match found');
        return null;
    }
    
    /**
     * Get service details including duration (Phase 5)
     *
     * @param int $service_id Amelia service ID
     * @return array|WP_Error Service data or error
     */
    public function get_service($service_id) {
        if (!$service_id) {
            return new WP_Error('invalid_service', 'Service ID is required');
        }
        
        $cache_key = 'art_service_' . $service_id;
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            amelia_cpt_sync_debug_log('ART API: Using cached service #' . $service_id);
            return $cached;
        }
        
        $response = $this->request('/services/' . $service_id, 'GET');
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $service = $response['data']['service'] ?? null;
        
        if (!$service) {
            return new WP_Error('service_not_found', 'Service not found');
        }
        
        amelia_cpt_sync_debug_log('ART API: Fetched service #' . $service_id . ' - ' . ($service['name'] ?? 'Unknown'));
        
        // Cache for 1 hour
        set_transient($cache_key, $service, HOUR_IN_SECONDS);
        
        return $service;
    }
    
    /**
     * Get available time slots (Phase 5)
     *
     * @param array $params Slot parameters
     * @return array|WP_Error Slots data or error
     */
    public function get_slots($params) {
        // Required parameters
        $required = array('serviceId', 'serviceDuration', 'persons');
        foreach ($required as $key) {
            if (empty($params[$key])) {
                return new WP_Error('missing_param', 'Missing required parameter: ' . $key);
            }
        }
        
        // Build query string in EXACT format from Amelia API docs
        // Example: /slots?locationId=2&serviceId=3&serviceDuration=1800&providerIds=3&persons=1&startDateTime=2023-09-25&extras=[]&excludeAppointmentId=null
        
        $query_parts = array();
        
        // Location (optional - only add if > 0)
        if (!empty($params['locationId']) && $params['locationId'] > 0) {
            $query_parts[] = 'locationId=' . absint($params['locationId']);
        }
        
        // Service ID (required)
        $query_parts[] = 'serviceId=' . absint($params['serviceId']);
        
        // Service Duration in seconds (required)
        $query_parts[] = 'serviceDuration=' . absint($params['serviceDuration']);
        
        // Provider IDs (optional - single ID, not array despite parameter name)
        if (!empty($params['providerIds']) && $params['providerIds'] > 0) {
            $query_parts[] = 'providerIds=' . absint($params['providerIds']);
        }
        
        // Persons (required)
        $query_parts[] = 'persons=' . absint($params['persons']);
        
        // Start date - DATE ONLY format "YYYY-MM-DD" (not "YYYY-MM-DD HH:mm"!)
        if (!empty($params['startDateTime'])) {
            // Extract just date if full datetime provided
            $date_only = substr($params['startDateTime'], 0, 10);
            $query_parts[] = 'startDateTime=' . $date_only;
        } else {
            // Default to today if not specified
            $query_parts[] = 'startDateTime=' . gmdate('Y-m-d');
        }
        
        // Extras (literal [], not URL-encoded %5B%5D)
        $query_parts[] = 'extras=[]';
        
        // Exclude appointment ID (always include, use null)
        $query_parts[] = 'excludeAppointmentId=null';
        
        $query_string = implode('&', $query_parts);
        $endpoint = '/slots&' . $query_string;  // Use & not ? (already in a query string!)
        
        amelia_cpt_sync_debug_log('ART API: Getting slots', array(
            'service_id' => $params['serviceId'],
            'duration' => $params['serviceDuration'],
            'persons' => $params['persons'],
            'start_date' => $params['startDateTime'] ?? 'today',
            'full_endpoint' => $endpoint
        ));
        
        $response = $this->request($endpoint, 'GET');
        
        if (is_wp_error($response)) {
            amelia_cpt_sync_debug_log('ART API Slots Error: ' . $response->get_error_message());
            return $response;
        }
        
        $slots = $response['data']['slots'] ?? array();
        
        // Just log a summary, not all the data
        amelia_cpt_sync_debug_log('ART API: Retrieved slots for ' . count($slots) . ' dates');
        
        // Log if no slots found (might be scheduling issue, not API error)
        if (empty($slots)) {
            amelia_cpt_sync_debug_log('ART API: No slots available (check employee working hours, service assignment, and date range)');
        }
        
        return array(
            'slots' => $slots,
            'minimum' => $response['data']['minimum'] ?? '',
            'maximum' => $response['data']['maximum'] ?? ''
        );
    }
    
    /**
     * Create an Amelia booking (Phase 5)
     * Format based on Amelia API documentation
     *
     * @param array $booking_data Booking data
     * @return array|WP_Error Booking response or error
     */
    public function create_booking($booking_data) {
        // Validate required fields (locationId is optional)
        $required = array('bookingStart', 'serviceId', 'providerId', 'bookings');
        foreach ($required as $key) {
            if (!isset($booking_data[$key])) {
                return new WP_Error('missing_field', 'Missing required booking field: ' . $key);
            }
        }
        
        // Ensure bookings array structure
        if (empty($booking_data['bookings']) || !is_array($booking_data['bookings'])) {
            return new WP_Error('invalid_bookings', 'Bookings must be a non-empty array');
        }
        
        // Build payload in EXACT format from Amelia API docs
        // Key flags for backend/admin booking:
        // - isBackendOrCabinet: bypasses customer blocking, booking limits, recaptcha
        // - packageBookingFromBackend: treats this as backend booking for slot validation (less strict)
        $booking_payload = array(
            'type' => 'appointment',
            'bookings' => $booking_data['bookings'],  // Already formatted by caller
            'payment' => array(
                'gateway' => 'onSite',
                'currency' => 'USD',
                'data' => (object) array()  // Empty object, not array
            ),
            'bookingStart' => $booking_data['bookingStart'],  // "YYYY-MM-DD HH:mm" format
            'notifyParticipants' => 1,
            'providerId' => absint($booking_data['providerId']),
            'serviceId' => absint($booking_data['serviceId']),
            'isBackendOrCabinet' => true,  // Bypass customer restrictions and recaptcha
            'packageBookingFromBackend' => true  // Use backend slot validation (less strict)
        );
        
        // Only add locationId if provided (Amelia can handle bookings without location)
        if (!empty($booking_data['locationId']) && $booking_data['locationId'] > 0) {
            $booking_payload['locationId'] = absint($booking_data['locationId']);
        }
        
        amelia_cpt_sync_debug_log('ART API: Creating booking for service #' . $booking_data['serviceId'] . ' at ' . $booking_data['bookingStart']);
        amelia_cpt_sync_debug_log('ART API: Booking payload: ' . json_encode($booking_payload, JSON_PRETTY_PRINT));
        
        $response = $this->request('/bookings', 'POST', $booking_payload);
        
        if (is_wp_error($response)) {
            amelia_cpt_sync_debug_log('ART API: Booking creation failed - ' . $response->get_error_message());
            return $response;
        }
        
        // Debug: Log the full response structure
        amelia_cpt_sync_debug_log('ART API: Booking response structure', $response);
        
        // Check for Amelia-specific error conditions that return 200 OK but indicate failure
        if (!empty($response['data']['timeSlotUnavailable'])) {
            amelia_cpt_sync_debug_log('ART API: Booking failed - Time slot is unavailable');
            return new WP_Error(
                'time_slot_unavailable', 
                'The selected time slot is unavailable. This usually means: (1) The time is outside the provider\'s working hours, (2) Another booking was just made for this slot, or (3) The time doesn\'t align with the service\'s time slot intervals. Try selecting a time from the available slots list.'
            );
        }
        
        // Check for other error indicators in the response
        if (isset($response['message']) && stripos($response['message'], 'unavailable') !== false) {
            amelia_cpt_sync_debug_log('ART API: Booking failed - ' . $response['message']);
            return new WP_Error('booking_failed', $response['message']);
        }
        
        // According to Amelia API docs, the response structure is:
        // data.appointment.id = appointment ID
        // data.appointment.bookings[0].id = booking ID
        $appointment_id = $response['data']['appointment']['id'] ?? null;
        $booking_id = null;
        
        // Get booking ID from the bookings array inside appointment
        if (isset($response['data']['appointment']['bookings'][0]['id'])) {
            $booking_id = $response['data']['appointment']['bookings'][0]['id'];
        }
        
        // Fallback: Check alternative structures
        if (!$booking_id) {
            // Try data.booking.id
            $booking_id = $response['data']['booking']['id'] ?? null;
        }
        
        if ($booking_id) {
            amelia_cpt_sync_debug_log('ART API: Successfully created booking #' . $booking_id . ' (appointment #' . $appointment_id . ')');
        } else {
            // If we still don't have a booking ID, the booking likely failed
            amelia_cpt_sync_debug_log('ART API: Warning - Could not extract booking ID from response. Message: ' . ($response['message'] ?? 'none'));
            return new WP_Error('booking_id_missing', 'Booking may have failed - no booking ID returned. Response: ' . ($response['message'] ?? 'Unknown error'));
        }
        
        return $response;
    }
    
    /**
     * Update an existing Amelia appointment (reschedule)
     *
     * @param int $appointment_id Amelia appointment ID
     * @param array $update_data Data to update (bookingStart, providerId, locationId, etc.)
     * @return array|WP_Error Response or error
     */
    public function update_appointment($appointment_id, $update_data) {
        if (!$appointment_id) {
            return new WP_Error('invalid_appointment', 'Appointment ID is required');
        }
        
        amelia_cpt_sync_debug_log('ART API: Updating appointment #' . $appointment_id, $update_data);
        
        $response = $this->request('/appointments/' . absint($appointment_id), 'POST', $update_data);
        
        if (is_wp_error($response)) {
            amelia_cpt_sync_debug_log('ART API: Update appointment failed - ' . $response->get_error_message());
            return $response;
        }
        
        amelia_cpt_sync_debug_log('ART API: Successfully updated appointment #' . $appointment_id);
        
        return $response;
    }
    
    /**
     * Delete an Amelia appointment
     *
     * @param int $appointment_id Amelia appointment ID
     * @return array|WP_Error Response or error
     */
    public function delete_appointment($appointment_id) {
        if (!$appointment_id) {
            return new WP_Error('invalid_appointment', 'Appointment ID is required');
        }
        
        amelia_cpt_sync_debug_log('ART API: Deleting appointment #' . $appointment_id);
        
        $response = $this->request('/appointments/delete/' . absint($appointment_id), 'POST');
        
        if (is_wp_error($response)) {
            amelia_cpt_sync_debug_log('ART API: Delete appointment failed - ' . $response->get_error_message());
            return $response;
        }
        
        amelia_cpt_sync_debug_log('ART API: Successfully deleted appointment #' . $appointment_id);
        
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
        
        amelia_cpt_sync_debug_log('ART API: Updating appointment #' . $appointment_id . ' status to ' . $status);
        
        $response = $this->request('/appointments/status/' . absint($appointment_id), 'POST', array(
            'status' => $status,
            'packageCustomerId' => null
        ));
        
        if (is_wp_error($response)) {
            amelia_cpt_sync_debug_log('ART API: Update status failed - ' . $response->get_error_message());
            return $response;
        }
        
        amelia_cpt_sync_debug_log('ART API: Successfully updated appointment #' . $appointment_id . ' status to ' . $status);
        
        return $response;
    }
}

