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
        
        if ($code < 200 || $code > 299) {
            $error_message = isset($data['message']) ? $data['message'] : 'HTTP ' . $code;
            amelia_cpt_sync_debug_log('ART API Error Response (' . $code . '): ' . print_r($data, true));
            amelia_cpt_sync_debug_log('ART API Raw Response Body: ' . substr($body_response, 0, 500));
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
     * Get providers (employees) from Amelia (Phase 5)
     *
     * @return array|WP_Error Array of providers or error
     */
    public function get_providers() {
        $cache_key = 'art_amelia_providers';
        
        // Try cache first
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            amelia_cpt_sync_debug_log('ART API: Using cached providers');
            return $cached;
        }
        
        // Call API
        $response = $this->request('/users/providers', 'GET');
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Note: Amelia API returns 'users' for this endpoint, containing provider objects
        $providers = $response['data']['users'] ?? array();
        
        amelia_cpt_sync_debug_log('ART API: Fetched ' . count($providers) . ' providers');
        
        // Cache for 1 hour
        set_transient($cache_key, $providers, HOUR_IN_SECONDS);
        
        return $providers;
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
        
        amelia_cpt_sync_debug_log('ART API: Getting slots for service #' . $params['serviceId']);
        
        $response = $this->request($endpoint, 'GET');
        
        if (is_wp_error($response)) {
            amelia_cpt_sync_debug_log('ART API Slots Error: ' . $response->get_error_message());
            return $response;
        }
        
        $slots = $response['data']['slots'] ?? array();
        
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
        // Validate required fields
        $required = array('bookingStart', 'serviceId', 'providerId', 'locationId', 'bookings');
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
            'locationId' => absint($booking_data['locationId']),
            'providerId' => absint($booking_data['providerId']),
            'serviceId' => absint($booking_data['serviceId'])
        );
        
        amelia_cpt_sync_debug_log('ART API: Creating booking for service #' . $booking_data['serviceId'] . ' at ' . $booking_data['bookingStart']);
        amelia_cpt_sync_debug_log('ART API: Booking payload: ' . json_encode($booking_payload, JSON_PRETTY_PRINT));
        
        $response = $this->request('/bookings', 'POST', $booking_payload);
        
        if (is_wp_error($response)) {
            amelia_cpt_sync_debug_log('ART API: Booking creation failed - ' . $response->get_error_message());
            return $response;
        }
        
        $booking_id = $response['data']['booking']['id'] ?? null;
        $appointment_id = $response['data']['appointment']['id'] ?? null;
        
        if ($booking_id) {
            amelia_cpt_sync_debug_log('ART API: Successfully created booking #' . $booking_id . ' (appointment #' . $appointment_id . ')');
        }
        
        return $response;
    }
}

