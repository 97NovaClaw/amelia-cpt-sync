<?php
/**
 * ART Resource Manager Class
 *
 * Core resource management logic:
 * - Configuration management (per-service settings)
 * - Availability checking (Uses ART_Amelia_Data_Manager for DB access)
 * - Resource assignment tracking
 *
 * @package AmeliaCPTSync
 * @subpackage ART
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class ART_Resource_Manager {
    
    /**
     * WordPress database object
     *
     * @var wpdb
     */
    private $wpdb;
    
    /**
     * Resource API instance
     *
     * @var ART_Resource_API
     */
    private $resource_api;
    
    /**
     * API Manager instance
     *
     * @var Amelia_CPT_Sync_ART_API_Manager
     */
    private $api_manager;

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
        $this->resource_api = new ART_Resource_API();
        $this->api_manager = new Amelia_CPT_Sync_ART_API_Manager();
        $this->data_manager = ART_Amelia_Data_Manager::get_instance();
    }
    
    // ========================================
    // CONFIGURATION METHODS
    // ========================================
    
    /**
     * Get resource configuration for a service
     *
     * @param int $service_id Service ID
     * @return object|null Configuration object or null if not found
     */
    public function get_service_config($service_id) {
        $table = $this->wpdb->prefix . 'art_resource_configs';
        
        $config = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM $table WHERE amelia_service_id = %d",
            $service_id
        ));
        
        if ($config && $config->mode_settings) {
            $config->mode_settings = json_decode($config->mode_settings, true);
        }
        
        return $config;
    }
    
    /**
     * Save resource configuration for a service
     *
     * @param int $service_id Service ID
     * @param array $config_data Configuration data
     * @return int|WP_Error Config ID or error
     */
    public function save_service_config($service_id, $config_data) {
        $table = $this->wpdb->prefix . 'art_resource_configs';
        
        $data = array(
            'amelia_service_id' => absint($service_id),
            'resource_mode' => sanitize_text_field($config_data['resource_mode'] ?? 'none'),
            'mode_settings' => isset($config_data['mode_settings']) ? wp_json_encode($config_data['mode_settings']) : null,
            'conflict_handling' => sanitize_text_field($config_data['conflict_handling'] ?? 'strict'),
            'updated_at' => current_time('mysql')
        );
        
        // Check if config exists
        $existing = $this->get_service_config($service_id);
        
        if ($existing) {
            // Update
            $result = $this->wpdb->update(
                $table,
                $data,
                array('amelia_service_id' => $service_id),
                array('%d', '%s', '%s', '%s', '%s'),
                array('%d')
            );
            
            if ($result === false) {
                return new WP_Error('update_failed', 'Failed to update resource config: ' . $this->wpdb->last_error);
            }
            
            amelia_cpt_sync_debug_log('ART Resource: Updated config for service #' . $service_id);
            return $existing->id;
        } else {
            // Insert
            $data['created_at'] = current_time('mysql');
            
            $result = $this->wpdb->insert(
                $table,
                $data,
                array('%d', '%s', '%s', '%s', '%s', '%s')
            );
            
            if (!$result) {
                return new WP_Error('insert_failed', 'Failed to insert resource config: ' . $this->wpdb->last_error);
            }
            
            amelia_cpt_sync_debug_log('ART Resource: Created config for service #' . $service_id);
            return $this->wpdb->insert_id;
        }
    }
    
    /**
     * Delete resource configuration
     *
     * @param int $service_id Service ID
     * @return bool True on success
     */
    public function delete_service_config($service_id) {
        $table = $this->wpdb->prefix . 'art_resource_configs';
        
        $this->wpdb->delete(
            $table,
            array('amelia_service_id' => $service_id),
            array('%d')
        );
        
        amelia_cpt_sync_debug_log('ART Resource: Deleted config for service #' . $service_id);
        
        return true;
    }
    
    // ========================================
    // AVAILABILITY CHECKING
    // ========================================
    
    /**
     * Check if a resource is available for a time slot
     *
     * @param int $resource_id Resource ID
     * @param string $date Date (Y-m-d)
     * @param string $time Time (H:i)
     * @param int $duration Duration in seconds
     * @return bool True if available
     */
    public function is_resource_available($resource_id, $date, $time, $duration) {
        amelia_cpt_sync_debug_log('ART Resource: Checking availability for resource #' . $resource_id);
        amelia_cpt_sync_debug_log('ART Resource: Check params - date: ' . $date . ', time: ' . $time . ', duration: ' . $duration);
        
        // Get existing appointments for this date (Direct DB)
        $appointments = $this->data_manager->get_appointments($date, $date);
        
        amelia_cpt_sync_debug_log('ART Resource: Found ' . count($appointments) . ' appointments on ' . $date);
        
        // Convert time to minutes for comparison
        $request_start = $this->time_to_minutes($time);
        $request_end = $request_start + ($duration / 60);
        
        amelia_cpt_sync_debug_log('ART Resource: Request window - ' . $request_start . ' to ' . $request_end . ' minutes');
        
        // Check each appointment to see if it uses this resource
        $checked_count = 0;
        foreach ($appointments as $index => $appt) {
            $checked_count++;
            
            // Check if this appointment uses this resource
            if (!$this->appointment_uses_resource($appt, $resource_id)) {
                continue;
            }
            
            // Check time overlap
            $appt_start = $this->datetime_to_minutes($appt['start_utc']);
            $appt_end = $this->datetime_to_minutes($appt['end_utc']);
            
            if ($this->times_overlap($request_start, $request_end, $appt_start, $appt_end)) {
                amelia_cpt_sync_debug_log('ART Resource: OVERLAP DETECTED - Resource #' . $resource_id . ' is booked (appointment #' . $appt['id'] . ')');
                return false;
            }
        }
        
        amelia_cpt_sync_debug_log('ART Resource: Checked ' . $checked_count . ' appointments, no conflicts found');
        amelia_cpt_sync_debug_log('ART Resource: Resource #' . $resource_id . ' is AVAILABLE');
        return true;
    }
    
    /**
     * Get booked quantity for a resource at a time
     *
     * @param int $resource_id Resource ID
     * @param string $date Date (Y-m-d)
     * @param string $time Time (H:i)
     * @param int $duration Duration in seconds
     * @return int Booked quantity
     */
    public function get_booked_quantity($resource_id, $date, $time, $duration) {
        $appointments = $this->data_manager->get_appointments($date, $date);
        
        $request_start = $this->time_to_minutes($time);
        $request_end = $request_start + ($duration / 60);
        
        $booked_count = 0;
        
        foreach ($appointments as $appt) {
            if (!$this->appointment_uses_resource($appt, $resource_id)) {
                continue;
            }
            
            $appt_start = $this->datetime_to_minutes($appt['start_utc']);
            $appt_end = $this->datetime_to_minutes($appt['end_utc']);
            
            if ($this->times_overlap($request_start, $request_end, $appt_start, $appt_end)) {
                $booked_count++;
            }
        }
        
        return $booked_count;
    }
    
    /**
     * Check if appointment uses a specific resource
     *
     * @param array $appointment Appointment DTO
     * @param int $resource_id Resource ID
     * @return bool True if appointment uses this resource
     */
    private function appointment_uses_resource($appointment, $resource_id) {
        // Check our assignments table first
        $assignments_table = $this->wpdb->prefix . 'art_resource_assignments';
        
        $assignment = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM $assignments_table 
            WHERE amelia_appointment_id = %d 
            AND amelia_resource_id = %d 
            AND status = 'active'",
            $appointment['id'],
            $resource_id
        ));
        
        if ($assignment) {
            return true;
        }
        
        // Check Amelia's built-in resource tracking
        // Note: DTO 'resources' field is already an array
        $resources = $appointment['resources'] ?? array();
        
        foreach ($resources as $resource) {
            if (intval($resource['id'] ?? 0) === intval($resource_id)) {
                return true;
            }
        }
        
        // For Mode 1 (Mirrored): Check if appointment is for the service that owns this resource
        $resource = $this->get_resource($resource_id);
        if (!is_wp_error($resource) && isset($resource['entities'])) {
            foreach ($resource['entities'] as $entity) {
                // New DTO format uses snake_case keys
                if (isset($entity['entity_type']) && $entity['entity_type'] === 'service') {
                    $linked_service_id = $entity['entity_id'];
                    $appointment_service_id = $appointment['service_id'] ?? null;
                    
                    if ($linked_service_id && $appointment_service_id && intval($linked_service_id) === intval($appointment_service_id)) {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }
    
    // ========================================
    // ASSIGNMENT METHODS
    // ========================================
    
    /**
     * Assign resources to a booking
     *
     * @param int $request_id Request ID
     * @param int $appointment_id Amelia appointment ID
     * @param array $resource_ids Resource IDs to assign
     * @param array $quantities Quantities (optional, defaults to 1 each)
     * @return bool True on success
     */
    public function assign_resources($request_id, $appointment_id, $resource_ids, $quantities = array()) {
        if (empty($resource_ids)) {
            return true;
        }
        
        $table = $this->wpdb->prefix . 'art_resource_assignments';
        
        foreach ($resource_ids as $index => $resource_id) {
            $quantity = $quantities[$index] ?? 1;
            
            $this->wpdb->insert(
                $table,
                array(
                    'request_id' => absint($request_id),
                    'amelia_appointment_id' => absint($appointment_id),
                    'amelia_resource_id' => absint($resource_id),
                    'quantity_used' => absint($quantity),
                    'assignment_type' => 'automatic',
                    'status' => 'active',
                    'assigned_at' => current_time('mysql')
                ),
                array('%d', '%d', '%d', '%d', '%s', '%s', '%s')
            );
            
            amelia_cpt_sync_debug_log('ART Resource: Assigned resource #' . $resource_id . ' to request #' . $request_id);
        }
        
        return true;
    }
    
    /**
     * Release resources from a booking
     *
     * @param int $request_id Request ID
     * @return bool True on success
     */
    public function release_resources($request_id) {
        $table = $this->wpdb->prefix . 'art_resource_assignments';
        
        $result = $this->wpdb->update(
            $table,
            array(
                'status' => 'released',
                'released_at' => current_time('mysql')
            ),
            array(
                'request_id' => $request_id,
                'status' => 'active'
            ),
            array('%s', '%s'),
            array('%d', '%s')
        );
        
        amelia_cpt_sync_debug_log('ART Resource: Released resources for request #' . $request_id);
        
        return true;
    }
    
    /**
     * Get active assignments for a request
     *
     * @param int $request_id Request ID
     * @return array Array of assignments with resource details
     */
    public function get_assignments($request_id) {
        $table = $this->wpdb->prefix . 'art_resource_assignments';
        
        $assignments = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM $table WHERE request_id = %d AND status = 'active' ORDER BY assigned_at ASC",
            $request_id
        ), ARRAY_A);
        
        // Enrich with resource names
        foreach ($assignments as &$assignment) {
            $resource = $this->resource_api->get_resource($assignment['amelia_resource_id']);
            if (!is_wp_error($resource)) {
                $assignment['resource_name'] = $resource['name'] ?? 'Unknown';
                $assignment['resource_quantity'] = $resource['quantity'] ?? 1;
            }
        }
        
        return $assignments;
    }
    
    // ========================================
    // MODE 1: MIRRORED RESOURCE HELPERS
    // ========================================
    
    /**
     * Auto-create a mirrored resource for a service
     *
     * @param int $service_id Service ID
     * @return int|WP_Error Resource ID or error
     */
    public function auto_create_mirrored_resource($service_id) {
        amelia_cpt_sync_debug_log('ART Resource: Auto-creating mirrored resource for service #' . $service_id);
        
        // Get service details
        $service = $this->api_manager->get_service($service_id);
        
        if (is_wp_error($service)) {
            amelia_cpt_sync_debug_log('ART Resource: Failed to fetch service - ' . $service->get_error_message());
            return $service;
        }
        
        // get_service() returns the service object directly (not wrapped in data.service)
        if (!isset($service['name'])) {
            amelia_cpt_sync_debug_log('ART Resource: Service data invalid - ' . wp_json_encode($service));
            return new WP_Error('no_service', 'Service not found');
        }
        
        // Create resource
        $resource_data = array(
            'name' => $service['name'] . ' (Resource)',
            'quantity' => 1,
            'shared' => null,
            'status' => 'visible',
            'entities' => array(
                array(
                    'entityId' => intval($service_id),
                    'entityType' => 'service'
                )
            ),
            'countAdditionalPeople' => false
        );
        
        amelia_cpt_sync_debug_log('ART Resource: Creating resource - ' . wp_json_encode($resource_data));
        
        $result = $this->resource_api->create_resource($resource_data);
        
        if (is_wp_error($result)) {
            amelia_cpt_sync_debug_log('ART Resource: Failed to create resource - ' . $result->get_error_message());
            return $result;
        }
        
        amelia_cpt_sync_debug_log('ART Resource: Successfully created mirrored resource #' . $result['id']);
        
        return $result['id'];
    }
    
    /**
     * Sync mirrored resource name with service
     *
     * @param int $service_id Service ID
     * @param string $new_service_name New service name
     * @return bool|WP_Error True on success or error
     */
    public function sync_mirrored_resource($service_id, $new_service_name) {
        $config = $this->get_service_config($service_id);
        
        if (!$config || $config->resource_mode !== 'mirrored') {
            return true;
        }
        
        $sync_name = $config->mode_settings['sync_name'] ?? false;
        
        if (!$sync_name) {
            return true;
        }
        
        $resource_id = $config->mode_settings['mirrored_resource_id'] ?? null;
        
        if (!$resource_id) {
            return true;
        }
        
        amelia_cpt_sync_debug_log('ART Resource: Syncing resource #' . $resource_id . ' name with service');
        
        return $this->resource_api->update_resource($resource_id, array(
            'name' => $new_service_name . ' (Resource)'
        ));
    }
    
    // ========================================
    // UTILITY METHODS
    // ========================================
    
    /**
     * Convert time string to minutes
     *
     * @param string $time Time (H:i)
     * @return int Minutes since midnight
     */
    private function time_to_minutes($time) {
        $parts = explode(':', $time);
        return (intval($parts[0]) * 60) + intval($parts[1] ?? 0);
    }
    
    /**
     * Convert datetime string to minutes
     *
     * @param string $datetime Datetime (Y-m-d H:i:s)
     * @return int Minutes since midnight
     */
    private function datetime_to_minutes($datetime) {
        $time_part = substr($datetime, 11, 5); // Extract H:i
        return $this->time_to_minutes($time_part);
    }
    
    /**
     * Check if two time ranges overlap
     *
     * @param int $start1 Start minutes
     * @param int $end1 End minutes
     * @param int $start2 Start minutes
     * @param int $end2 End minutes
     * @return bool True if overlap
     */
    private function times_overlap($start1, $end1, $start2, $end2) {
        // Handle overnight appointments (end < start)
        if ($end2 < $start2) {
            $end2 += 1440; // Add 24 hours
        }
        
        return ($start1 < $end2) && ($end1 > $start2);
    }
    
    /**
     * Get a single resource (Direct DB via Data Manager)
     *
     * @param int $resource_id Resource ID
     * @return array|WP_Error Resource data or error
     */
    public function get_resource($resource_id) {
        $resource = $this->data_manager->get_resource($resource_id);
        
        if ($resource === null) {
            return new WP_Error('not_found', 'Resource not found');
        }
        
        return $resource;
    }
    
    /**
     * Get resource name by ID
     *
     * @param int $resource_id Resource ID
     * @return string Resource name
     */
    public function get_resource_name($resource_id) {
        $resource = $this->get_resource($resource_id);
        
        if (is_wp_error($resource)) {
            return 'Unknown Resource';
        }
        
        return $resource['name'] ?? 'Unknown Resource';
    }
    
    /**
     * Get next available time for a resource
     *
     * @param int $resource_id Resource ID
     * @param string $date Starting date (Y-m-d)
     * @return string|null Next available time (H:i) or null
     */
    public function get_next_available($resource_id, $date) {
        $appointments = $this->data_manager->get_appointments($date, $date);
        
        $resource_appointments = array();
        
        foreach ($appointments as $appt) {
            if ($this->appointment_uses_resource($appt, $resource_id)) {
                $resource_appointments[] = $appt;
            }
        }
        
        if (empty($resource_appointments)) {
            return null;
        }
        
        // Sort by end_utc (previously bookingEnd)
        usort($resource_appointments, function($a, $b) {
            return strcmp($a['end_utc'], $b['end_utc']);
        });
        
        // Return the end time of the first appointment
        $next_free_utc = $resource_appointments[0]['end_utc'];
        // Convert UTC datetime to just Time part
        // Note: This is simplistic, doesn't account for timezone shift on the day
        // But fits current logic level.
        return substr($next_free_utc, 11, 5); // Extract H:i
    }
}
