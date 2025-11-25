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
        $settings = get_option('amelia_cpt_sync_art_settings', array());
        $this->api_base_url = $settings['api_base_url'] ?? '';
        $this->api_key = $settings['api_key'] ?? '';
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
            amelia_cpt_sync_debug_log('ART API Error Response: ' . print_r($data, true));
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
        
        // Build query string
        $query_params = array(
            'serviceId' => absint($params['serviceId']),
            'serviceDuration' => absint($params['serviceDuration']),
            'persons' => absint($params['persons']),
            'locationId' => absint($params['locationId'] ?? 0),
            'startDateTime' => sanitize_text_field($params['startDateTime'] ?? ''),
            'extras' => '[]'  // No extras for now
        );
        
        // Optional: provider IDs
        if (!empty($params['providerIds'])) {
            $query_params['providerIds'] = absint($params['providerIds']);
        }
        
        $query_string = http_build_query($query_params);
        $endpoint = '/slots?' . $query_string;
        
        amelia_cpt_sync_debug_log('ART API: Getting slots for service #' . $params['serviceId']);
        
        $response = $this->request($endpoint, 'GET');
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $slots = $response['data']['slots'] ?? array();
        
        amelia_cpt_sync_debug_log('ART API: Retrieved slots for ' . count($slots) . ' dates');
        
        return array(
            'slots' => $slots,
            'minimum' => $response['data']['minimum'] ?? '',
            'maximum' => $response['data']['maximum'] ?? ''
        );
    }
    
    /**
     * Create an Amelia booking (Phase 5)
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
        
        // Default values
        $booking_payload = array_merge(array(
            'type' => 'appointment',
            'notifyParticipants' => 1,
            'internalNotes' => '',
            'recurring' => array(),
            'payment' => array(
                'gateway' => 'onSite',
                'currency' => 'USD',
                'data' => array()
            )
        ), $booking_data);
        
        amelia_cpt_sync_debug_log('ART API: Creating booking for service #' . $booking_data['serviceId'] . ' at ' . $booking_data['bookingStart']);
        
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

