<?php
/**
 * ART Availability Engine
 *
 * Calculates real provider availability by checking multiple data sources.
 * Uses Direct Database queries for appointments via ART_Amelia_Data_Manager.
 *
 * @package AmeliaCPTSync
 * @subpackage ART
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class Amelia_CPT_Sync_ART_Availability_Engine {
    
    /**
     * @var Amelia_CPT_Sync_ART_API_Manager
     */
    private $api_manager;

    /**
     * @var ART_Amelia_Data_Manager
     */
    private $data_manager;
    
    /**
     * @var array Settings from database
     */
    private $settings;
    
    /**
     * @var array Cached provider details
     */
    private $provider_cache = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->api_manager = new Amelia_CPT_Sync_ART_API_Manager();
        $this->data_manager = ART_Amelia_Data_Manager::get_instance();
        $this->settings = $this->load_settings();
    }
    
    /**
     * Load settings from database
     *
     * @return array Settings with defaults
     */
    private function load_settings() {
        $defaults = array(
            'working_hours_mode' => 'soft',
            'buffer_time_mode' => 'soft',
            'service_schedule_mode' => 'soft',
            'location_mode' => 'ignore',
            'show_location_selector' => false,
            'check_resources' => false,
            'check_approved_appointments' => true,
            'check_pending_appointments' => true,
        );
        
        $saved = get_option('art_availability_settings', array());
        return array_merge($defaults, $saved);
    }
    
    /**
     * Main entry point - check availability for all providers
     *
     * @param string $date Date in Y-m-d format
     * @param string $time Time in H:i format
     * @param int $service_id Amelia service ID
     * @param int $duration_seconds Service duration in seconds
     * @param int|null $location_id Optional location ID to filter by
     * @param int|null $exclude_appointment_id Optional appointment ID to exclude (for self-blocking prevention)
     * @return array|WP_Error Array of provider availability or error
     */
    public function check_availability($date, $time, $service_id, $duration_seconds, $location_id = null, $exclude_appointment_id = null) {
        amelia_cpt_sync_debug_log('Availability Engine: Starting check', array(
            'date' => $date,
            'time' => $time,
            'service_id' => $service_id,
            'duration' => $duration_seconds,
            'location_id' => $location_id
        ));
        
        // Step 1: Get providers for service (with optional location filter)
        $providers_list = $this->get_service_providers($service_id, $location_id);
        
        if (is_wp_error($providers_list)) {
            return $providers_list;
        }
        
        if (empty($providers_list)) {
            amelia_cpt_sync_debug_log('Availability Engine: No providers found for service');
            return array();
        }
        
        amelia_cpt_sync_debug_log('Availability Engine: Found ' . count($providers_list) . ' providers');
        
        // Step 2: Get service details (for buffer times)
        // Use Data Manager for consistent DTO
        $service = $this->data_manager->get_service($service_id);
        $buffer_before = 0;
        $buffer_after = 0;
        
        if ($service) {
            $buffer_before = intval($service['buffer_before'] ?? 0);
            $buffer_after = intval($service['buffer_after'] ?? 0);
            amelia_cpt_sync_debug_log('Availability Engine: Service buffers - before: ' . $buffer_before . 's, after: ' . $buffer_after . 's');
        }
        
        // Step 3: Get appointments for the date range (DIRECT DB)
        // UTC FIREWALL: Calculate UTC date range to catch overnight appointments
        $local_datetime = $date . ' ' . $time . ':00';
        $start_utc = ART_Time_Helper::to_utc($local_datetime);
        $end_timestamp = strtotime($start_utc) + $duration_seconds;
        $end_utc = gmdate('Y-m-d H:i:s', $end_timestamp);
        
        $start_date = substr($start_utc, 0, 10);
        $end_date = substr($end_utc, 0, 10);
        
        amelia_cpt_sync_debug_log('Availability Engine: UTC range - ' . $start_utc . ' to ' . $end_utc);
        
        $filters = [];
        if ($exclude_appointment_id) {
            $filters['exclude_id'] = $exclude_appointment_id;
            amelia_cpt_sync_debug_log('Availability Engine: Excluding appointment #' . $exclude_appointment_id . ' from check');
        }
        
        $appointments = $this->data_manager->get_appointments($start_date, $end_date, $filters);
        
        amelia_cpt_sync_debug_log('Availability Engine: Found ' . count($appointments) . ' appointments for date range (DB)');
        
        // Step 4: Check each provider
        $results = array();
        $provider_count = count($providers_list);
        $checked = 0;
        
        foreach ($providers_list as $provider_basic) {
            $checked++;
            $provider_id = $provider_basic['id'];
            $provider_name = trim(($provider_basic['firstName'] ?? '') . ' ' . ($provider_basic['lastName'] ?? ''));
            
            amelia_cpt_sync_debug_log("Availability Engine: Checking provider $checked/$provider_count - $provider_name (#$provider_id)");
            
            // Get full provider details (includes schedule) - Still API for now
            $provider = $this->get_provider_details($provider_id);
            
            if (is_wp_error($provider)) {
                amelia_cpt_sync_debug_log("Availability Engine: Error fetching provider #$provider_id details");
                // Add as force book if we can't get details
                $results[] = array(
                    'id' => $provider_id,
                    'name' => $provider_name,
                    'status' => 'force_book',
                    'conflicts' => array(__('Could not verify schedule', 'amelia-cpt-sync'))
                );
                continue;
            }
            
            // Run availability checks
            $check_result = $this->check_provider_availability(
                $provider,
                $date,
                $time,
                $duration_seconds,
                $appointments,
                $buffer_before,
                $buffer_after,
                $service_id
            );
            
            $results[] = array(
                'id' => $provider_id,
                'name' => $provider_name,
                'status' => $check_result['status'],
                'conflicts' => $check_result['conflicts']
            );
        }
        
        // Sort results: available first, then might_conflict, then force_book
        usort($results, function($a, $b) {
            $order = array('available' => 0, 'might_conflict' => 1, 'force_book' => 2, 'not_available' => 3);
            return ($order[$a['status']] ?? 99) - ($order[$b['status']] ?? 99);
        });
        
        amelia_cpt_sync_debug_log('Availability Engine: Check complete', array(
            'total_providers' => count($results),
            'available' => count(array_filter($results, function($r) { return $r['status'] === 'available'; })),
            'might_conflict' => count(array_filter($results, function($r) { return $r['status'] === 'might_conflict'; })),
            'force_book' => count(array_filter($results, function($r) { return $r['status'] === 'force_book'; }))
        ));
        
        return $results;
    }
    
    /**
     * Check availability for a single provider
     *
     * @param array $provider Full provider data
     * @param string $date Date in Y-m-d format
     * @param string $time Time in H:i format
     * @param int $duration Duration in seconds
     * @param array $appointments All appointments for the date (DTOs)
     * @param int $buffer_before Buffer time before in seconds
     * @param int $buffer_after Buffer time after in seconds
     * @param int $service_id Service ID
     * @return array Status and conflicts
     */
    private function check_provider_availability($provider, $date, $time, $duration, $appointments, $buffer_before, $buffer_after, $service_id) {
        $conflicts = array();
        $is_available = true;
        $has_pending_conflict = false;  // Track soft blocks for tentative bookings
        $provider_id = $provider['id'];
        
        // Strategy: Hybrid approach for different types of checks
        // 1. Minutes for working hours/schedule checks (local timezone context)
        $request_start_minutes = $this->time_to_minutes($time);
        $request_end_minutes = $request_start_minutes + ($duration / 60);
        
        // 2. Unix timestamps for appointment overlaps (UTC-based, timezone-safe)
        // This matches the Resource Manager's approach and handles midnight wraparound
        $request_datetime_utc = ART_Time_Helper::to_utc($date . ' ' . $time . ':00');
        $request_start_unix = strtotime($request_datetime_utc);
        $request_end_unix = $request_start_unix + $duration;
        
        // a) CHECK DAY OFF
        if ($this->is_day_off($provider, $date)) {
            return array(
                'status' => 'not_available',
                'conflicts' => array(__('Day off', 'amelia-cpt-sync'))
            );
        }
        
        // b) CHECK SPECIAL DAY & c) CHECK WORKING HOURS
        $schedule = $this->get_schedule_for_date($provider, $date);
        
        if ($this->settings['working_hours_mode'] !== 'ignore') {
            $hours_check = $this->check_working_hours($schedule, $request_start_minutes, $request_end_minutes, $date);
            
            if (!$hours_check['passed']) {
                if ($this->settings['working_hours_mode'] === 'strict') {
                    return array(
                        'status' => 'not_available',
                        'conflicts' => array($hours_check['conflict'])
                    );
                } else {
                    $conflicts[] = $hours_check['conflict'];
                }
            }
        }
        
        // d) CHECK APPOINTMENT OVERLAPS
        // Using new DTO keys: provider_id, start_utc, end_utc
        $provider_appointments = array_filter($appointments, function($appt) use ($provider_id) {
            return intval($appt['provider_id']) === intval($provider_id);
        });
        
        foreach ($provider_appointments as $appt) {
            // REFACTOR: Use Unix timestamps (matches Resource Manager pattern)
            // This handles midnight wraparound, DST transitions, and multi-day appointments correctly
            $appt_start_unix = strtotime($appt['start_utc']);
            $appt_end_unix = strtotime($appt['end_utc']);
            $appt_status = $appt['status'] ?? 'approved';
            
            // Debug overlap check
            amelia_cpt_sync_debug_log("Availability Engine: Overlap check for provider #$provider_id", array(
                'appointment_id' => $appt['id'] ?? 'unknown',
                'request_unix' => "$request_start_unix-$request_end_unix",
                'appointment_unix' => "$appt_start_unix-$appt_end_unix",
                'request_utc' => $request_datetime_utc,
                'appointment_utc' => $appt['start_utc'] . ' to ' . $appt['end_utc']
            ));
            
            // Check for overlap using Unix timestamps (timezone-safe!)
            if ($request_start_unix < $appt_end_unix && $request_end_unix > $appt_start_unix) {
                amelia_cpt_sync_debug_log("Availability Engine: OVERLAP DETECTED for provider #$provider_id with appointment #" . ($appt['id'] ?? 'unknown'));
                $appt_time = $this->format_time_range($appt['start_utc'], $appt['end_utc']);
                $request_ref = !empty($appt['request_id']) ? " (Req #{$appt['request_id']})" : '';
                
                if ($appt_status === 'approved' && $this->settings['check_approved_appointments']) {
                    $conflicts[] = sprintf(__('Confirmed booking - %s%s', 'amelia-cpt-sync'), $appt_time, $request_ref);
                    $is_available = false;
                } elseif ($appt_status === 'pending' && $this->settings['check_pending_appointments']) {
                    $conflicts[] = sprintf(__('Tentative booking - %s%s', 'amelia-cpt-sync'), $appt_time, $request_ref);
                    $has_pending_conflict = true;  // Soft block - mark as might_conflict
                }
            } else {
                amelia_cpt_sync_debug_log("Availability Engine: NO overlap for provider #$provider_id with appointment #" . ($appt['id'] ?? 'unknown'));
            }
            
            // e) CHECK BUFFER TIMES (Now using Unix timestamps)
            if ($this->settings['buffer_time_mode'] !== 'ignore') {
                $buffer_check = $this->check_buffer_times_unix(
                    $request_start_unix, $request_end_unix,
                    $appt_start_unix, $appt_end_unix,
                    $buffer_before, $buffer_after,
                    $appt['end_utc']
                );
                
                if (!$buffer_check['passed']) {
                    if ($this->settings['buffer_time_mode'] === 'strict') {
                        $is_available = false;
                    }
                    if ($buffer_check['conflict']) {
                        $conflicts[] = $buffer_check['conflict'];
                    }
                }
            }
        }
        
        // f) CHECK SERVICE SCHEDULE (if provider has service-specific periods)
        if ($this->settings['service_schedule_mode'] !== 'ignore') {
            $service_check = $this->check_service_schedule($provider, $service_id, $date);
            
            if (!$service_check['passed']) {
                if ($this->settings['service_schedule_mode'] === 'strict') {
                    return array(
                        'status' => 'not_available',
                        'conflicts' => array($service_check['conflict'])
                    );
                } else {
                    $conflicts[] = $service_check['conflict'];
                }
            }
        }
        
        // Determine final status
        if (!$is_available) {
            return array(
                'status' => 'not_available',
                'conflicts' => $conflicts
            );
        }
        
        // NEW: Check for soft blocks (tentative booking conflicts)
        if ($has_pending_conflict) {
            return array(
                'status' => 'might_conflict',
                'conflicts' => $conflicts
            );
        }
        
        if (!empty($conflicts)) {
            return array(
                'status' => 'might_conflict',
                'conflicts' => $conflicts
            );
        }
        
        return array(
            'status' => 'available',
            'conflicts' => array()
        );
    }
    
    /**
     * Get providers for a service (list only, no schedule details)
     *
     * @param int $service_id Service ID
     * @param int|null $location_id Optional location filter
     * @return array|WP_Error List of providers
     */
    private function get_service_providers($service_id, $location_id = null) {
        // Still use API Manager for this as it filters users by service logic
        $providers = $this->api_manager->get_service_employees($service_id);
        
        if (is_wp_error($providers)) {
            return $providers;
        }
        
        // If location filter is set, we need to filter client-side
        if ($location_id && $location_id > 0) {
            $providers = array_filter($providers, function($p) use ($location_id) {
                return intval($p['locationId'] ?? 0) === intval($location_id);
            });
            $providers = array_values($providers); // Re-index
        }
        
        return $providers;
    }
    
    /**
     * Get full provider details including schedule
     *
     * @param int $provider_id Provider ID
     * @return array|WP_Error Provider data with schedule
     */
    private function get_provider_details($provider_id) {
        // Check cache first
        if (isset($this->provider_cache[$provider_id])) {
            return $this->provider_cache[$provider_id];
        }
        
        // Use API Manager directly as we haven't built the complex DB schedule builder yet
        $response = $this->api_manager->api_request('/users/providers/' . absint($provider_id));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $provider = $response['data']['user'] ?? null;
        
        if (!$provider) {
            return new WP_Error('provider_not_found', 'Provider not found');
        }
        
        // Cache for this request
        $this->provider_cache[$provider_id] = $provider;
        
        return $provider;
    }
    
    /**
     * Check if date is a day off for provider
     *
     * @param array $provider Provider data
     * @param string $date Date in Y-m-d format
     * @return bool True if day off
     */
    private function is_day_off($provider, $date) {
        $day_off_list = $provider['dayOffList'] ?? array();
        $check_date = strtotime($date);
        $check_month_day = date('m-d', $check_date);
        
        foreach ($day_off_list as $day_off) {
            $start = strtotime($day_off['startDate']);
            $end = strtotime($day_off['endDate']);
            $repeat = intval($day_off['repeat'] ?? 0);
            
            if ($repeat === 1) {
                // Yearly repeat - check month and day only
                $off_start_md = date('m-d', $start);
                $off_end_md = date('m-d', $end);
                
                if ($check_month_day >= $off_start_md && $check_month_day <= $off_end_md) {
                    return true;
                }
            } else {
                // Exact date range
                if ($check_date >= $start && $check_date <= $end) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Get schedule for a specific date (handles special days)
     *
     * @param array $provider Provider data
     * @param string $date Date in Y-m-d format
     * @return array|null Schedule data or null if not working
     */
    private function get_schedule_for_date($provider, $date) {
        // Check for special day first
        $special_days = $provider['specialDayList'] ?? array();
        
        foreach ($special_days as $special) {
            $start = $special['startDate'];
            $end = $special['endDate'];
            
            if ($date >= $start && $date <= $end) {
                return array(
                    'type' => 'special',
                    'periods' => $special['periodList'] ?? array()
                );
            }
        }
        
        // Fall back to regular weekday schedule
        $week_days = $provider['weekDayList'] ?? array();
        $day_index = intval(date('N', strtotime($date))); // 1 = Monday, 7 = Sunday
        
        foreach ($week_days as $day) {
            if (intval($day['dayIndex']) === $day_index) {
                return array(
                    'type' => 'regular',
                    'startTime' => $day['startTime'],
                    'endTime' => $day['endTime'],
                    'timeOutList' => $day['timeOutList'] ?? array(),
                    'periods' => $day['periodList'] ?? array()
                );
            }
        }
        
        return null; // Provider doesn't work this day
    }
    
    /**
     * Check if request fits within working hours
     *
     * @param array|null $schedule Schedule data
     * @param int $request_start Request start in minutes
     * @param int $request_end Request end in minutes
     * @param string $date Date for formatting
     * @return array Check result
     */
    private function check_working_hours($schedule, $request_start, $request_end, $date) {
        if (!$schedule) {
            return array(
                'passed' => false,
                'conflict' => sprintf(__('Not scheduled on %s', 'amelia-cpt-sync'), date('l', strtotime($date)))
            );
        }
        
        if ($schedule['type'] === 'special') {
            // Check against special day periods
            foreach ($schedule['periods'] as $period) {
                $period_start = $this->time_to_minutes($period['startTime']);
                $period_end = $this->time_to_minutes($period['endTime']);
                
                if ($request_start >= $period_start && $request_end <= $period_end) {
                    return array('passed' => true, 'conflict' => null);
                }
            }
            
            return array(
                'passed' => false,
                'conflict' => __('Outside special day hours', 'amelia-cpt-sync')
            );
        }
        
        // Regular schedule
        $work_start = $this->time_to_minutes($schedule['startTime']);
        $work_end = $this->time_to_minutes($schedule['endTime']);
        
        // Check if request is within working hours
        if ($request_start < $work_start) {
            return array(
                'passed' => false,
                'conflict' => sprintf(__('Shift starts at %s', 'amelia-cpt-sync'), $this->minutes_to_time($work_start))
            );
        }
        
        if ($request_end > $work_end) {
            return array(
                'passed' => false,
                'conflict' => sprintf(__('Shift ends at %s', 'amelia-cpt-sync'), $this->minutes_to_time($work_end))
            );
        }
        
        // Check timeOutList (breaks)
        foreach ($schedule['timeOutList'] as $break) {
            $break_start = $this->time_to_minutes($break['startTime'] ?? '');
            $break_end = $this->time_to_minutes($break['endTime'] ?? '');
            
            if ($break_start && $break_end && $this->times_overlap($request_start, $request_end, $break_start, $break_end)) {
                return array(
                    'passed' => false,
                    'conflict' => sprintf(__('Break time %s - %s', 'amelia-cpt-sync'), 
                        $this->minutes_to_time($break_start), 
                        $this->minutes_to_time($break_end))
                );
            }
        }
        
        return array('passed' => true, 'conflict' => null);
    }
    
    /**
     * Check buffer time violations
     *
     * @param int $req_start Request start minutes
     * @param int $req_end Request end minutes
     * @param int $appt_start Appointment start minutes
     * @param int $appt_end Appointment end minutes
     * @param int $buffer_before Buffer before in seconds
     * @param int $buffer_after Buffer after in seconds
     * @param string $appt_end_time Appointment end time for message
     * @return array Check result
     */
    private function check_buffer_times($req_start, $req_end, $appt_start, $appt_end, $buffer_before, $buffer_after, $appt_end_time) {
        $buffer_before_min = $buffer_before / 60;
        $buffer_after_min = $buffer_after / 60;
        
        // Check if request starts within buffer_after of appointment end
        $appt_end_with_buffer = $appt_end + $buffer_after_min;
        if ($req_start < $appt_end_with_buffer && $req_start >= $appt_end) {
            // Use timezone helper if needed, for now simple format
            $end_time = date('g:i A', strtotime($appt_end_time));
            return array(
                'passed' => false,
                'conflict' => sprintf(__('Buffer time - appointment ends at %s', 'amelia-cpt-sync'), $end_time)
            );
        }
        
        // Check if request ends within buffer_before of appointment start
        $appt_start_with_buffer = $appt_start - $buffer_before_min;
        if ($req_end > $appt_start_with_buffer && $req_end <= $appt_start) {
            return array(
                'passed' => false,
                'conflict' => __('Buffer time - too close to next appointment', 'amelia-cpt-sync')
            );
        }
        
        return array('passed' => true, 'conflict' => null);
    }
    
    /**
     * Check buffer time violations using Unix timestamps
     * (Matches Resource Manager's timestamp-based approach)
     *
     * @param int $req_start_unix Request start Unix timestamp
     * @param int $req_end_unix Request end Unix timestamp
     * @param int $appt_start_unix Appointment start Unix timestamp
     * @param int $appt_end_unix Appointment end Unix timestamp
     * @param int $buffer_before Buffer before in seconds
     * @param int $buffer_after Buffer after in seconds
     * @param string $appt_end_utc Appointment end time UTC for display
     * @return array Check result
     */
    private function check_buffer_times_unix($req_start_unix, $req_end_unix, $appt_start_unix, $appt_end_unix, $buffer_before, $buffer_after, $appt_end_utc) {
        // Buffers are in seconds, timestamps are in seconds - units match!
        
        // Check if request starts within buffer_after of appointment end
        $appt_end_with_buffer = $appt_end_unix + $buffer_after;
        if ($req_start_unix < $appt_end_with_buffer && $req_start_unix >= $appt_end_unix) {
            // Convert UTC to local for display
            $end_time = ART_Time_Helper::to_local($appt_end_utc, 'g:i A');
            return array(
                'passed' => false,
                'conflict' => sprintf(__('Buffer time - appointment ends at %s', 'amelia-cpt-sync'), $end_time)
            );
        }
        
        // Check if request ends within buffer_before of appointment start
        $appt_start_with_buffer = $appt_start_unix - $buffer_before;
        if ($req_end_unix > $appt_start_with_buffer && $req_end_unix <= $appt_start_unix) {
            return array(
                'passed' => false,
                'conflict' => __('Buffer time - too close to next appointment', 'amelia-cpt-sync')
            );
        }
        
        return array('passed' => true, 'conflict' => null);
    }
    
    /**
     * Check if provider offers service on this day
     *
     * @param array $provider Provider data
     * @param int $service_id Service ID
     * @param string $date Date
     * @return array Check result
     */
    private function check_service_schedule($provider, $service_id, $date) {
        // Check if provider has the service at all
        $service_list = $provider['serviceList'] ?? array();
        $has_service = false;
        
        foreach ($service_list as $service) {
            if (intval($service['id']) === intval($service_id)) {
                $has_service = true;
                break;
            }
        }
        
        if (!$has_service) {
            return array(
                'passed' => false,
                'conflict' => __('Service not assigned to provider', 'amelia-cpt-sync')
            );
        }
        
        // Check periodServiceList for day-specific restrictions
        $schedule = $this->get_schedule_for_date($provider, $date);
        
        if ($schedule && !empty($schedule['periods'])) {
            foreach ($schedule['periods'] as $period) {
                $period_services = $period['periodServiceList'] ?? array();
                
                // If period has service restrictions, check if our service is included
                if (!empty($period_services)) {
                    $service_allowed = false;
                    foreach ($period_services as $ps) {
                        if (intval($ps['serviceId'] ?? 0) === intval($service_id)) {
                            $service_allowed = true;
                            break;
                        }
                    }
                    
                    if (!$service_allowed) {
                        $day_name = date('l', strtotime($date));
                        return array(
                            'passed' => false,
                            'conflict' => sprintf(__('Service not offered on %s', 'amelia-cpt-sync'), $day_name)
                        );
                    }
                }
            }
        }
        
        return array('passed' => true, 'conflict' => null);
    }
    
    /**
     * Check if two time ranges overlap
     *
     * @param int $start1 First range start (minutes)
     * @param int $end1 First range end (minutes)
     * @param int $start2 Second range start (minutes)
     * @param int $end2 Second range end (minutes)
     * @return bool True if overlap
     */
    private function times_overlap($start1, $end1, $start2, $end2) {
        return $start1 < $end2 && $end1 > $start2;
    }
    
    /**
     * Convert time string to minutes since midnight
     *
     * @param string $time Time in H:i or H:i:s format
     * @return int Minutes
     */
    private function time_to_minutes($time) {
        if (empty($time)) {
            return 0;
        }
        
        $parts = explode(':', $time);
        $hours = intval($parts[0] ?? 0);
        $minutes = intval($parts[1] ?? 0);
        
        return ($hours * 60) + $minutes;
    }
    
    /**
     * Convert datetime string to minutes since midnight
     *
     * @deprecated No longer used - switched to Unix timestamps for timezone safety
     * @param string $datetime Datetime in Y-m-d H:i:s format
     * @return int Minutes
     */
    private function datetime_to_minutes($datetime) {
        // DEPRECATED: This function had a critical bug - it treated UTC hours as local hours
        // Example: UTC 16:00 would become 960 minutes (4pm) instead of converting to local first
        // Kept for reference only - appointment overlaps now use Unix timestamps
        trigger_error('datetime_to_minutes() is deprecated - use Unix timestamps for appointment overlaps', E_USER_DEPRECATED);
        
        $time = date('H:i', strtotime($datetime));
        return $this->time_to_minutes($time);
    }
    
    /**
     * Convert minutes to time string
     *
     * @param int $minutes Minutes since midnight
     * @return string Time in g:i A format
     */
    private function minutes_to_time($minutes) {
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;
        return date('g:i A', strtotime(sprintf('%02d:%02d', $hours, $mins)));
    }
    
    /**
     * Format time range for display (UTC to Local conversion)
     *
     * @param string $start_utc Start datetime (UTC)
     * @param string $end_utc End datetime (UTC)
     * @return string Formatted range in WordPress local timezone
     */
    private function format_time_range($start_utc, $end_utc) {
        // UTC FIREWALL: Convert UTC to WordPress timezone for display
        $start_local = ART_Time_Helper::to_local($start_utc, 'g:i A');
        $end_local = ART_Time_Helper::to_local($end_utc, 'g:i A');
        
        return $start_local . ' - ' . $end_local;
    }
}
