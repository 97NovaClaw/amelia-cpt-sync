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
     * Now quantity-aware: checks how many units are booked vs total quantity.
     *
     * @param int $resource_id Resource ID
     * @param string $date Date (Y-m-d)
     * @param string $time Time (H:i)
     * @param int $duration Duration in seconds
     * @param int $exclude_appointment_id Optional appointment ID to exclude (for self-blocking prevention)
     * @param int $quantity_needed How many units of this resource are needed (default 1)
     * @return array {
     *     @type bool   $available   True if sufficient quantity available
     *     @type string $block_type  'none', 'soft' (tentative), 'hard' (confirmed)
     *     @type string|null $message Human-readable status message
     *     @type int|null $conflicting_request_id ART request ID if conflict exists (for hard blocks)
     *     @type int $total_quantity Resource's max quantity
     *     @type int $booked_quantity Currently booked at this time
     *     @type int $available_quantity Remaining available
     *     @type int $hard_booked Count of confirmed (approved) bookings
     *     @type int $soft_booked Count of tentative (pending) bookings
     *     @type array $conflicts List of conflicting appointments with details
     * }
     */
    public function is_resource_available($resource_id, $date, $time, $duration, $exclude_appointment_id = null, $quantity_needed = 1) {
        amelia_cpt_sync_debug_log('ART Resource: Checking availability for resource #' . $resource_id);
        amelia_cpt_sync_debug_log('ART Resource: Check params - date: ' . $date . ', time: ' . $time . ', duration: ' . $duration . ', qty_needed: ' . $quantity_needed);
        
        // Get resource details including quantity
        $resource = $this->get_resource($resource_id);
        
        if (is_wp_error($resource)) {
            amelia_cpt_sync_debug_log('ART Resource: Failed to fetch resource #' . $resource_id);
            return array(
                'available' => false,
                'block_type' => 'hard',
                'message' => 'Resource not found',
                'conflicting_request_id' => null,
                'total_quantity' => 0,
                'booked_quantity' => 0,
                'available_quantity' => 0,
                'hard_booked' => 0,
                'soft_booked' => 0,
                'conflicts' => array()
            );
        }
        
        $total_quantity = intval($resource['quantity'] ?? 1);
        amelia_cpt_sync_debug_log('ART Resource: Total quantity for resource #' . $resource_id . ' = ' . $total_quantity);
        
        // UTC FIREWALL: Convert local datetime to UTC to determine correct date range
        $local_datetime = $date . ' ' . $time . ':00';
        $start_utc = ART_Time_Helper::to_utc($local_datetime);
        
        // Calculate end time in UTC
        $end_timestamp = strtotime($start_utc) + $duration;
        $end_utc = gmdate('Y-m-d H:i:s', $end_timestamp);
        
        // Extract date range (might span multiple days in UTC)
        $start_date = substr($start_utc, 0, 10);
        $end_date = substr($end_utc, 0, 10);
        
        amelia_cpt_sync_debug_log('ART Resource: UTC range - ' . $start_utc . ' to ' . $end_utc);
        amelia_cpt_sync_debug_log('ART Resource: Fetching appointments from ' . $start_date . ' to ' . $end_date);
        
        // Get existing appointments for the UTC date range (Direct DB)
        $filters = [];
        if ($exclude_appointment_id) {
            $filters['exclude_id'] = $exclude_appointment_id;
            amelia_cpt_sync_debug_log('ART Resource: Excluding appointment #' . $exclude_appointment_id . ' from check');
        }
        
        $appointments = $this->data_manager->get_appointments($start_date, $end_date, $filters);
        
        amelia_cpt_sync_debug_log('ART Resource: Found ' . count($appointments) . ' appointments in date range');
        
        // Convert UTC strings to timestamps for accurate comparison
        $request_start_timestamp = strtotime($start_utc);
        $request_end_timestamp = strtotime($end_utc);
        
        amelia_cpt_sync_debug_log('ART Resource: Request UTC timestamps - ' . $request_start_timestamp . ' to ' . $request_end_timestamp);
        
        // Track all overlapping appointments
        $hard_booked = 0;  // Confirmed (approved) bookings
        $soft_booked = 0;  // Tentative (pending) bookings
        $conflicts = array();
        $first_hard_conflict = null;
        $first_soft_conflict = null;
        
        // Check each appointment to see if it uses this resource
        foreach ($appointments as $appt) {
            // Check if this appointment uses this resource
            if (!$this->appointment_uses_resource($appt, $resource_id)) {
                continue;
            }
            
            // Check time overlap using UTC timestamps
            $appt_start_timestamp = strtotime($appt['start_utc']);
            $appt_end_timestamp = strtotime($appt['end_utc']);
            
            // Overlap if: (request_start < appt_end) AND (request_end > appt_start)
            if ($request_start_timestamp < $appt_end_timestamp && $request_end_timestamp > $appt_start_timestamp) {
                $appt_status = $appt['status'] ?? 'approved';
                
                // Get quantity used by this appointment (from our tracking table)
                $qty_used = $this->get_appointment_quantity_used($appt['id'], $resource_id);
                
                // Format time range for messages
                $start_local = get_date_from_gmt($appt['start_utc']);
                $end_local = get_date_from_gmt($appt['end_utc']);
                $time_range = date('g:i A', strtotime($start_local)) . ' - ' . date('g:i A', strtotime($end_local));
                
                $request_ref = !empty($appt['request_id']) ? " (Req #{$appt['request_id']})" : '';
                
                $conflict_entry = array(
                    'appointment_id' => $appt['id'],
                    'status' => $appt_status,
                    'request_id' => $appt['request_id'] ?? null,
                    'quantity_used' => $qty_used,
                    'time_range' => $time_range
                );
                $conflicts[] = $conflict_entry;
                
                if ($appt_status === 'approved') {
                    $hard_booked += $qty_used;
                    if (!$first_hard_conflict) {
                        $first_hard_conflict = array(
                            'message' => "Confirmed booking - {$time_range}{$request_ref}",
                            'request_id' => $appt['request_id'] ?? null
                        );
                    }
                    amelia_cpt_sync_debug_log('ART Resource: Hard conflict - Appt #' . $appt['id'] . ' uses ' . $qty_used . ' units');
                } else {
                    $soft_booked += $qty_used;
                    if (!$first_soft_conflict) {
                        $first_soft_conflict = array(
                            'message' => "Tentative booking - {$time_range}{$request_ref}",
                            'request_id' => $appt['request_id'] ?? null
                        );
                    }
                    amelia_cpt_sync_debug_log('ART Resource: Soft conflict - Appt #' . $appt['id'] . ' uses ' . $qty_used . ' units');
                }
            }
        }
        
        $total_booked = $hard_booked + $soft_booked;
        $available_quantity = max(0, $total_quantity - $total_booked);
        
        amelia_cpt_sync_debug_log('ART Resource: Quantity summary - total: ' . $total_quantity . ', hard_booked: ' . $hard_booked . ', soft_booked: ' . $soft_booked . ', available: ' . $available_quantity . ', needed: ' . $quantity_needed);
        
        // Determine availability based on quantity
        $result = array(
            'total_quantity' => $total_quantity,
            'booked_quantity' => $total_booked,
            'available_quantity' => $available_quantity,
            'hard_booked' => $hard_booked,
            'soft_booked' => $soft_booked,
            'conflicts' => $conflicts
        );
        
        // Case 1: Hard block - confirmed bookings use all units
        if ($hard_booked >= $total_quantity) {
            amelia_cpt_sync_debug_log('ART Resource: HARD BLOCK - All ' . $total_quantity . ' units confirmed booked');
            return array_merge($result, array(
                'available' => false,
                'block_type' => 'hard',
                'message' => $first_hard_conflict['message'] . ' (' . $hard_booked . '/' . $total_quantity . ' in use)',
                'conflicting_request_id' => $first_hard_conflict['request_id']
            ));
        }
        
        // Case 2: Soft block - tentative bookings use remaining units  
        if ($total_booked >= $total_quantity) {
            amelia_cpt_sync_debug_log('ART Resource: SOFT BLOCK - All units booked (some tentative)');
            return array_merge($result, array(
                'available' => false,
                'block_type' => 'soft',
                'message' => $first_soft_conflict['message'] . ' (' . $total_booked . '/' . $total_quantity . ' in use)',
                'conflicting_request_id' => $first_soft_conflict['request_id']
            ));
        }
        
        // Case 3: Partial availability - some units available but conflicts exist
        if (!empty($conflicts) && $available_quantity >= $quantity_needed) {
            amelia_cpt_sync_debug_log('ART Resource: AVAILABLE with conflicts - ' . $available_quantity . ' of ' . $total_quantity . ' available');
            
            $conflict_type = ($hard_booked > 0) ? 'hard' : 'soft';
            $conflict_msg = ($hard_booked > 0) ? $first_hard_conflict : $first_soft_conflict;
            
            return array_merge($result, array(
                'available' => true,  // Still available because quantity_needed is met
                'block_type' => $conflict_type,  // Indicates there ARE conflicts (for UI warning)
                'message' => $available_quantity . ' of ' . $total_quantity . ' available',
                'conflicting_request_id' => $conflict_msg ? $conflict_msg['request_id'] : null
            ));
        }
        
        // Case 4: Not enough units available
        if ($available_quantity < $quantity_needed) {
            amelia_cpt_sync_debug_log('ART Resource: BLOCKED - Need ' . $quantity_needed . ' but only ' . $available_quantity . ' available');
            
            $conflict = $first_hard_conflict ?: $first_soft_conflict;
            return array_merge($result, array(
                'available' => false,
                'block_type' => $first_hard_conflict ? 'hard' : 'soft',
                'message' => 'Need ' . $quantity_needed . ', only ' . $available_quantity . ' available',
                'conflicting_request_id' => $conflict ? $conflict['request_id'] : null
            ));
        }
        
        // Case 5: Fully available - no conflicts at all
        amelia_cpt_sync_debug_log('ART Resource: FULLY AVAILABLE - ' . $total_quantity . ' units, 0 booked');
        return array_merge($result, array(
            'available' => true,
            'block_type' => 'none',
            'message' => ($total_quantity > 1) ? 'All ' . $total_quantity . ' available' : null,
            'conflicting_request_id' => null
        ));
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
        // UTC FIREWALL: Calculate UTC date range
        $local_datetime = $date . ' ' . $time . ':00';
        $start_utc = ART_Time_Helper::to_utc($local_datetime);
        $end_timestamp = strtotime($start_utc) + $duration;
        $end_utc = gmdate('Y-m-d H:i:s', $end_timestamp);
        
        $start_date = substr($start_utc, 0, 10);
        $end_date = substr($end_utc, 0, 10);
        
        $appointments = $this->data_manager->get_appointments($start_date, $end_date);
        
        // Convert UTC strings to timestamps for accurate comparison
        $request_start_timestamp = strtotime($start_utc);
        $request_end_timestamp = strtotime($end_utc);
        
        $booked_count = 0;
        
        foreach ($appointments as $appt) {
            if (!$this->appointment_uses_resource($appt, $resource_id)) {
                continue;
            }
            
            $appt_start_timestamp = strtotime($appt['start_utc']);
            $appt_end_timestamp = strtotime($appt['end_utc']);
            
            // Overlap if: (request_start < appt_end) AND (request_end > appt_start)
            if ($request_start_timestamp < $appt_end_timestamp && $request_end_timestamp > $appt_start_timestamp) {
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
        amelia_cpt_sync_debug_log('ART Resource: Checking if appt #' . $appointment['id'] . ' uses resource #' . $resource_id);
        
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
            amelia_cpt_sync_debug_log('ART Resource: MATCH via art_resource_assignments');
            return true;
        }
        
        // Check Amelia's built-in resource tracking
        // Note: DTO 'resources' field is already an array
        $resources = $appointment['resources'] ?? array();
        
        foreach ($resources as $resource) {
            if (intval($resource['id'] ?? 0) === intval($resource_id)) {
                amelia_cpt_sync_debug_log('ART Resource: MATCH via appointment resources field');
                return true;
            }
        }
        
        // For Mode 1 (Mirrored): Check if appointment is for the service that owns this resource
        $resource = $this->get_resource($resource_id);
        
        amelia_cpt_sync_debug_log('ART Resource: Fetched resource #' . $resource_id . ' - ' . wp_json_encode($resource));
        
        if (is_wp_error($resource)) {
            amelia_cpt_sync_debug_log('ART Resource: ERROR fetching resource - ' . $resource->get_error_message());
            return false;
        }
        
        if (!isset($resource['entities'])) {
            amelia_cpt_sync_debug_log('ART Resource: No entities found for resource #' . $resource_id);
            return false;
        }
        
        amelia_cpt_sync_debug_log('ART Resource: Appointment service_id = ' . ($appointment['service_id'] ?? 'NULL'));
        amelia_cpt_sync_debug_log('ART Resource: Resource entities = ' . wp_json_encode($resource['entities']));
        
            foreach ($resource['entities'] as $entity) {
            // New DTO format uses snake_case keys
            if (isset($entity['entity_type']) && $entity['entity_type'] === 'service') {
                $linked_service_id = $entity['entity_id'];
                $appointment_service_id = $appointment['service_id'] ?? null;
                
                amelia_cpt_sync_debug_log('ART Resource: Comparing service ' . $linked_service_id . ' === ' . $appointment_service_id);
                    
                    if ($linked_service_id && $appointment_service_id && intval($linked_service_id) === intval($appointment_service_id)) {
                    amelia_cpt_sync_debug_log('ART Resource: MATCH - Appointment uses this resource!');
                        return true;
                }
            }
        }
        
        amelia_cpt_sync_debug_log('ART Resource: NO MATCH - Appointment does not use this resource');
        return false;
    }
    
    /**
     * Get the quantity of a resource used by a specific appointment
     *
     * Checks our art_resource_assignments table first, falls back to 1.
     *
     * @param int $appointment_id Amelia appointment ID
     * @param int $resource_id Resource ID
     * @return int Quantity used (minimum 1)
     */
    private function get_appointment_quantity_used($appointment_id, $resource_id) {
        $table = $this->wpdb->prefix . 'art_resource_assignments';
        
        $quantity = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT quantity_used FROM $table 
            WHERE amelia_appointment_id = %d 
            AND amelia_resource_id = %d 
            AND status = 'active'",
            $appointment_id,
            $resource_id
        ));
        
        // If not tracked in our table, assume 1 unit used
        return $quantity ? intval($quantity) : 1;
    }
    
    /**
     * Get all resources linked to a service (from Amelia's resources_to_entities)
     *
     * @param int $service_id Service ID
     * @return array Array of resource DTOs with quantity info
     */
    public function get_all_service_resources($service_id) {
        // Use Data Manager which already has this method
        return $this->data_manager->get_resources_for_service($service_id);
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
        // Fetch appointments for the day (and potentially next day if overnight)
        $next_date = date('Y-m-d', strtotime($date . ' +1 day'));
        $appointments = $this->data_manager->get_appointments($date, $next_date);
        
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
