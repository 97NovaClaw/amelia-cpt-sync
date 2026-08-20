<?php
/**
 * ART Resource Hooks Class
 *
 * Handles WordPress and Amelia hooks related to resources
 * - Auto-sync with Amelia services
 * - Resource assignment on booking
 * - Resource release on delete
 *
 * @package AmeliaCPTSync
 * @subpackage ART
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class ART_Resource_Hooks {
    
    /**
     * Resource Manager instance
     *
     * @var ART_Resource_Manager
     */
    private $resource_manager;
    
    /**
     * Constructor - registers all hooks
     */
    public function __construct() {
        $this->resource_manager = new ART_Resource_Manager();
        
        // Amelia service hooks (for Mode 1 auto-sync)
        add_action('amelia_after_service_added', array($this, 'handle_service_added'), 10, 1);
        add_action('amelia_after_service_updated', array($this, 'handle_service_updated'), 10, 1);
        add_action('amelia_after_service_deleted', array($this, 'handle_service_deleted'), 10, 1);
        
        // ART booking hooks
        add_action('art_booking_created', array($this, 'handle_booking_created'), 10, 3);
        add_action('art_booking_updated', array($this, 'handle_booking_updated'), 10, 3);
        add_action('art_booking_deleted', array($this, 'handle_booking_deleted'), 10, 1);
    }
    
    // ========================================
    // AMELIA SERVICE HOOKS
    // ========================================
    
    /**
     * Handle new service creation
     *
     * Auto-create mirrored resource if global setting enabled
     *
     * @param array $service Service data
     */
    public function handle_service_added($service) {
        $service_id = $service['id'] ?? null;
        
        if (!$service_id) {
            return;
        }
        
        amelia_cpt_sync_debug_log('ART Resource Hooks: Service #' . $service_id . ' created');
        
        // Check global settings for default mode
        $global_settings = get_option('art_resource_settings', array());
        $default_mode = $global_settings['default_mode'] ?? 'none';
        
        if ($default_mode === 'mirrored') {
            amelia_cpt_sync_debug_log('ART Resource Hooks: Auto-creating mirrored resource');
            
            // Create mirrored resource
            $resource_id = $this->resource_manager->auto_create_mirrored_resource($service_id);
            
            if (is_wp_error($resource_id)) {
                amelia_cpt_sync_debug_log('ART Resource Hooks: Failed to auto-create - ' . $resource_id->get_error_message());
                return;
            }
            
            // Save configuration
            $this->resource_manager->save_service_config($service_id, array(
                'resource_mode' => 'mirrored',
                'mode_settings' => array(
                    'auto_create' => true,
                    'sync_name' => $global_settings['mirrored']['sync_name'] ?? true,
                    'mirrored_resource_id' => $resource_id
                ),
                'conflict_handling' => 'strict'
            ));
            
            amelia_cpt_sync_debug_log('ART Resource Hooks: Auto-created resource #' . $resource_id . ' for service #' . $service_id);
        }
    }
    
    /**
     * Handle service update
     *
     * Auto-create resource if global default enabled and no resource exists
     * Sync mirrored resource name if configured
     *
     * @param array $service Service data
     */
    public function handle_service_updated($service) {
        $service_id = $service['id'] ?? null;
        $service_name = $service['name'] ?? null;
        
        if (!$service_id || !$service_name) {
            return;
        }
        
        amelia_cpt_sync_debug_log('ART Resource Hooks: Service #' . $service_id . ' updated');
        
        // Check if service has a resource configuration
        $config = $this->resource_manager->get_service_config($service_id);
        
        // Get global settings
        $global_settings = get_option('art_resource_settings', array());
        $default_mode = $global_settings['default_mode'] ?? 'none';
        
        if (!$config && $default_mode === 'mirrored') {
            // Service updated but no config exists, and global default is mirrored
            // Auto-create resource and config
            amelia_cpt_sync_debug_log('ART Resource Hooks: Auto-creating mirrored resource for existing service (global default)');
            
            $resource_id = $this->resource_manager->auto_create_mirrored_resource($service_id);
            
            if (!is_wp_error($resource_id)) {
                $this->resource_manager->save_service_config($service_id, array(
                    'resource_mode' => 'mirrored',
                    'mode_settings' => array(
                        'auto_create' => true,
                        'sync_name' => $global_settings['mirrored']['sync_name'] ?? true,
                        'mirrored_resource_id' => $resource_id
                    ),
                    'conflict_handling' => 'strict'
                ));
                
                amelia_cpt_sync_debug_log('ART Resource Hooks: Auto-created resource #' . $resource_id . ' for existing service #' . $service_id);
            }
        } elseif ($config && $config->resource_mode === 'mirrored') {
            $mirrored_resource_id = $config->mode_settings['mirrored_resource_id'] ?? null;
            
            // If config exists but no resource linked, auto-create
            if (!$mirrored_resource_id) {
                amelia_cpt_sync_debug_log('ART Resource Hooks: Config exists but no resource linked, auto-creating');
                
                $resource_id = $this->resource_manager->auto_create_mirrored_resource($service_id);
                
                if (!is_wp_error($resource_id)) {
                    // Update config with new resource ID
                    $updated_settings = $config->mode_settings;
                    $updated_settings['mirrored_resource_id'] = $resource_id;
                    
                    $this->resource_manager->save_service_config($service_id, array(
                        'resource_mode' => 'mirrored',
                        'mode_settings' => $updated_settings,
                        'conflict_handling' => $config->conflict_handling
                    ));
                    
                    amelia_cpt_sync_debug_log('ART Resource Hooks: Linked resource #' . $resource_id . ' to service #' . $service_id);
                }
            } else {
                // Resource already linked, just sync name if configured
                $this->resource_manager->sync_mirrored_resource($service_id, $service_name);
            }
        }
    }
    
    /**
     * Handle service deletion
     *
     * Delete mirrored resource if configured
     *
     * @param array $service Service data
     */
    public function handle_service_deleted($service) {
        $service_id = $service['id'] ?? null;
        
        if (!$service_id) {
            return;
        }
        
        amelia_cpt_sync_debug_log('ART Resource Hooks: Service #' . $service_id . ' deleted');
        
        $config = $this->resource_manager->get_service_config($service_id);
        
        if (!$config) {
            return;
        }
        
        // If mirrored mode and auto-delete enabled
        if ($config->resource_mode === 'mirrored') {
            $auto_delete = $config->mode_settings['auto_delete'] ?? false;
            
            if ($auto_delete) {
                $resource_id = $config->mode_settings['mirrored_resource_id'] ?? null;
                
                if ($resource_id) {
                    $resource_api = new ART_Resource_API();
                    $resource_api->delete_resource($resource_id);
                    
                    amelia_cpt_sync_debug_log('ART Resource Hooks: Auto-deleted resource #' . $resource_id);
                }
            }
        }
        
        // Always delete the configuration
        $this->resource_manager->delete_service_config($service_id);
    }
    
    // ========================================
    // ART BOOKING HOOKS
    // ========================================
    
    /**
     * Handle booking creation
     *
     * Assign resources to the new booking with correct quantity
     *
     * @param int $request_id Request ID
     * @param int $appointment_id Appointment ID
     * @param array $booking_data Booking data
     */
    public function handle_booking_created($request_id, $appointment_id, $booking_data) {
        $service_id = $booking_data['service_id'] ?? null;
        
        if (!$service_id) {
            return;
        }
        
        amelia_cpt_sync_debug_log('ART Resource Hooks: Booking created for request #' . $request_id);
        
        $config = $this->resource_manager->get_service_config($service_id);
        
        if (!$config || $config->resource_mode === 'none') {
            return;
        }
        
        // Assign resources based on mode
        $resource_ids = array();
        $quantities = array();
        
        switch ($config->resource_mode) {
            case 'mirrored':
                // Get all resources linked to service (for multi-resource support)
                $all_resources = $this->resource_manager->get_all_service_resources($service_id);
                $configured_resource_id = $config->mode_settings['mirrored_resource_id'] ?? null;
                
                if ($configured_resource_id) {
                    // Use configured resource only
                    $resource_ids = array($configured_resource_id);
                } elseif (!empty($all_resources)) {
                    // Use all auto-detected resources
                    foreach ($all_resources as $res) {
                        $resource_ids[] = $res['id'];
                    }
                }
                
                // Get quantity needed for each resource (default 1)
                $quantity_needed = $config->mode_settings['quantity_required'] ?? 1;
                foreach ($resource_ids as $rid) {
                    $quantities[] = $quantity_needed;
                }
                break;
                
            case 'shared_pool':
                $pool_resource_ids = $config->mode_settings['pool_resource_ids'] ?? array();
                $selection_strategy = $config->mode_settings['selection_strategy'] ?? 'first_available';
                $quantity_per_booking = $config->mode_settings['quantity_per_booking'] ?? 1;
                
                // Check if user manually selected a resource
                // v2.38.2: selected entries may be {resource_id, quantity} objects OR flat IDs
                $selected = $booking_data['selected_resources'] ?? array();
                $selected_id = null;
                if (!empty($selected)) {
                    $first_sel = reset($selected);
                    $selected_id = is_array($first_sel) ? intval($first_sel['resource_id'] ?? 0) : intval($first_sel);
                }
                
                if ($selected_id && in_array($selected_id, array_map('intval', $pool_resource_ids))) {
                    // Use user's manual selection
                    $resource_ids = array($selected_id);
                    amelia_cpt_sync_debug_log('ART Resource Hooks: Using manually selected resource: #' . $selected_id);
                } elseif (!empty($pool_resource_ids)) {
                    // Auto-select based on strategy (first available for now)
                    $resource_ids = array($pool_resource_ids[0]);
                    amelia_cpt_sync_debug_log('ART Resource Hooks: Auto-selected first pool resource: #' . $pool_resource_ids[0]);
                }
                
                $quantities = array($quantity_per_booking);
                break;
                
            case 'composite':
                // MULTI RESOURCE: Assign resources per requirement group (supports qty splitting)
                $groups = $config->mode_settings['requirement_groups'] ?? array();
                $selected = $booking_data['selected_resources'] ?? array();
                
                amelia_cpt_sync_debug_log('ART Resource Hooks: Composite mode - ' . count($groups) . ' requirement groups, ' . count($selected) . ' selected resources');
                amelia_cpt_sync_debug_log('ART Resource Hooks: Selected resources data: ' . wp_json_encode($selected));
                
                // Check if selected_resources contains {resource_id, quantity} objects (new format)
                // or flat IDs (legacy format)
                $has_qty_data = false;
                if (!empty($selected) && is_array($selected)) {
                    $first = reset($selected);
                    $has_qty_data = is_array($first) && isset($first['resource_id']);
                }
                
                if ($has_qty_data) {
                    // NEW FORMAT: [{resource_id: 5, quantity: 1}, {resource_id: 15, quantity: 2}]
                    foreach ($selected as $selection) {
                        $rid = intval($selection['resource_id'] ?? 0);
                        $qty = max(1, intval($selection['quantity'] ?? 1));
                        if ($rid > 0) {
                            $resource_ids[] = $rid;
                            $quantities[] = $qty;
                            amelia_cpt_sync_debug_log("ART Resource Hooks: Composite - Resource #{$rid} qty: {$qty}");
                        }
                    }
                } else {
                    // LEGACY FORMAT: Flat array of IDs, match to groups
                    foreach ($groups as $idx => $group) {
                        $group_resource_id = isset($selected[$idx]) ? intval($selected[$idx]) : null;
                        
                        if (!$group_resource_id && !empty($group['resource_ids'])) {
                            $group_resource_id = $group['resource_ids'][0];
                            amelia_cpt_sync_debug_log("ART Resource Hooks: Composite fallback - Group \"{$group['label']}\" using first resource #{$group_resource_id}");
                        }
                        
                        if ($group_resource_id) {
                            $resource_ids[] = $group_resource_id;
                            $quantities[] = $group['quantity_needed'] ?? 1;
                            amelia_cpt_sync_debug_log("ART Resource Hooks: Composite legacy - Group \"{$group['label']}\" → Resource #{$group_resource_id} (qty: " . ($group['quantity_needed'] ?? 1) . ")");
                        }
                    }
                }
                
                amelia_cpt_sync_debug_log('ART Resource Hooks: Composite mode - assigning ' . count($resource_ids) . ' resources: [' . implode(', ', $resource_ids) . '] with quantities: [' . implode(', ', $quantities) . ']');
                break;
                
            // Other modes will be handled in future phases
        }
        
        if (!empty($resource_ids)) {
            $this->resource_manager->assign_resources(
                $request_id,
                $appointment_id,
                $resource_ids,
                $quantities  // Pass quantity array
            );
            
            amelia_cpt_sync_debug_log('ART Resource Hooks: Assigned ' . count($resource_ids) . ' resource(s) with quantities: ' . implode(', ', $quantities));
        }
    }
    
    /**
     * Handle booking update
     *
     * Update resource assignments if changed
     *
     * @param int $request_id Request ID
     * @param int $appointment_id Appointment ID
     * @param array $booking_data Booking data
     */
    public function handle_booking_updated($request_id, $appointment_id, $booking_data) {
        amelia_cpt_sync_debug_log('ART Resource Hooks: Booking updated for request #' . $request_id);
        
        $service_id = $booking_data['service_id'] ?? null;
        $selected_resources = $booking_data['selected_resources'] ?? array();
        $resources_explicit = !empty($booking_data['resources_explicit']);
        
        // v2.38.2: Explicit empty selection = user deselected everything - release and stop
        if ($service_id && empty($selected_resources) && $resources_explicit) {
            amelia_cpt_sync_debug_log('ART Resource Hooks: Explicit deselect-all - releasing all resources for request #' . $request_id);
            $this->resource_manager->release_resources($request_id);
            return;
        }
        
        if (!$service_id || empty($selected_resources)) {
            amelia_cpt_sync_debug_log('ART Resource Hooks: No resources to update (service_id: ' . $service_id . ', selected: ' . count($selected_resources) . ')');
            return;
        }
        
        // Step 1: Release old assignments
        amelia_cpt_sync_debug_log('ART Resource Hooks: Releasing old resource assignments for request #' . $request_id);
        $this->resource_manager->release_resources($request_id);
        
        // Step 2: Assign new resources (same logic as handle_booking_created)
        $config = $this->resource_manager->get_service_config($service_id);
        $resource_ids = array();
        $quantities = array();
        
        // Check if selected_resources contains {resource_id, quantity} objects
        $has_qty_data = false;
        if (!empty($selected_resources)) {
            $first = reset($selected_resources);
            $has_qty_data = is_array($first) && isset($first['resource_id']);
        }
        
        if ($has_qty_data) {
            // Composite format: [{resource_id: 5, quantity: 2}, ...]
            foreach ($selected_resources as $sel) {
                $rid = absint($sel['resource_id'] ?? 0);
                $qty = max(1, absint($sel['quantity'] ?? 1));
                if ($rid > 0) {
                    $resource_ids[] = $rid;
                    $quantities[] = $qty;
                }
            }
            amelia_cpt_sync_debug_log('ART Resource Hooks: Re-assigning ' . count($resource_ids) . ' resources (composite format)');
        } else {
            // Flat format or mode-specific — fall through to handle_booking_created logic
            amelia_cpt_sync_debug_log('ART Resource Hooks: Falling back to handle_booking_created logic for re-assignment');
            $this->handle_booking_created($request_id, $appointment_id, $booking_data);
            return;
        }
        
        if (!empty($resource_ids)) {
            $this->resource_manager->assign_resources($request_id, $appointment_id, $resource_ids, $quantities);
            amelia_cpt_sync_debug_log('ART Resource Hooks: Re-assigned ' . count($resource_ids) . ' resources: [' . implode(', ', $resource_ids) . '] with quantities: [' . implode(', ', $quantities) . ']');
        }
    }
    
    /**
     * Handle booking deletion
     *
     * Release all assigned resources
     *
     * @param int $request_id Request ID
     */
    public function handle_booking_deleted($request_id) {
        amelia_cpt_sync_debug_log('ART Resource Hooks: Booking deleted for request #' . $request_id);
        
        $this->resource_manager->release_resources($request_id);
    }
}

