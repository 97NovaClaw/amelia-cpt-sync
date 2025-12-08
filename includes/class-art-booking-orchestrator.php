<?php
/**
 * ART Booking Orchestrator Class
 *
 * Coordinates all availability checking into a single unified response
 * - Resource availability (mode-specific logic)
 * - Provider availability (via Availability Engine)
 * - Combined decision making
 *
 * Single source of truth for "can book" decisions
 *
 * @package AmeliaCPTSync
 * @subpackage ART
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class ART_Booking_Orchestrator {
    
    /**
     * Resource Manager instance
     *
     * @var ART_Resource_Manager
     */
    private $resource_manager;
    
    /**
     * Availability Engine instance
     *
     * @var Amelia_CPT_Sync_ART_Availability_Engine
     */
    private $availability_engine;
    
    /**
     * API Manager instance
     *
     * @var Amelia_CPT_Sync_ART_API_Manager
     */
    private $api_manager;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->resource_manager = new ART_Resource_Manager();
        $this->availability_engine = new Amelia_CPT_Sync_ART_Availability_Engine();
        $this->api_manager = new Amelia_CPT_Sync_ART_API_Manager();
    }
    
    /**
     * Check full availability for a booking
     *
     * Main entry point - coordinates all availability checks
     *
     * @param array $params Booking parameters
     * @return array Unified result with resource status, provider list, and decision
     */
    public function check_full_availability($params) {
        amelia_cpt_sync_debug_log('ART Orchestrator: Checking full availability');
        amelia_cpt_sync_debug_log('ART Orchestrator: Params - ' . wp_json_encode($params));
        
        // Initialize result structure
        $result = array(
            'resource_mode' => 'none',
            'resource_block' => false,
            'resource_message' => null,
            'resources' => null,
            'providers' => array(),
            'can_book' => false,
            'requires_resource_selection' => false,
            'requires_force' => false
        );
        
        // ========================================
        // STEP 1: Get Resource Configuration
        // ========================================
        $resource_config = $this->resource_manager->get_service_config($params['service_id']);
        $result['resource_mode'] = $resource_config->resource_mode ?? 'none';
        
        amelia_cpt_sync_debug_log('ART Orchestrator: Resource mode - ' . $result['resource_mode']);
        
        // ========================================
        // STEP 2: Mode-Specific Pre-Check
        // ========================================
        switch ($result['resource_mode']) {
            case 'none':
                $result = $this->check_mode_none($params, $result);
                break;
                
            case 'mirrored':
                $result = $this->check_mode_mirrored($params, $resource_config, $result);
                break;
                
            case 'shared_pool':
                $result = $this->check_mode_shared_pool($params, $resource_config, $result);
                break;
                
            case 'quantity_pool':
                $result = $this->check_mode_quantity_pool($params, $resource_config, $result);
                break;
                
            case 'provider_bound':
                $result = $this->check_mode_provider_bound($params, $resource_config, $result);
                break;
                
            case 'location_bound':
                $result = $this->check_mode_location_bound($params, $resource_config, $result);
                break;
                
            case 'composite':
                $result = $this->check_mode_composite($params, $resource_config, $result);
                break;
                
            case 'hybrid':
                $result = $this->check_mode_hybrid($params, $resource_config, $result);
                break;
        }
        
        // If mode returned early (resource block), return now
        if (isset($result['early_return']) && $result['early_return']) {
            unset($result['early_return']);
            amelia_cpt_sync_debug_log('ART Orchestrator: Early return due to resource block');
            return $result;
        }
        
        // ========================================
        // STEP 3: Final Evaluation
        // ========================================
        $result['can_book'] = $this->evaluate_can_book($result, $params);
        
        amelia_cpt_sync_debug_log('ART Orchestrator: Final decision - can_book: ' . ($result['can_book'] ? 'true' : 'false'));
        
        return $result;
    }
    
    // ========================================
    // MODE HANDLERS
    // ========================================
    
    /**
     * Mode 0: None - No resource checking
     *
     * @param array $params Booking parameters
     * @param array $result Result array
     * @return array Updated result
     */
    private function check_mode_none($params, $result) {
        amelia_cpt_sync_debug_log('ART Orchestrator: Mode NONE - Skipping resource checks');
        
        // No resource checks, just run provider availability
        $result['resources'] = null;
        
        // Get provider availability
        $provider_result = $this->availability_engine->check_availability(
            $params['date'],
            $params['time'],
            $params['service_id'],
            $params['duration'],
            $params['location_id'] ?? null
        );
        
        // Availability Engine returns providers array directly
        $result['providers'] = $provider_result;
        
        return $result;
    }
    
    /**
     * Mode 1: Mirrored - 1:1 Service = Resource
     *
     * @param array $params Booking parameters
     * @param object $config Resource configuration
     * @param array $result Result array
     * @return array Updated result
     */
    private function check_mode_mirrored($params, $config, $result) {
        amelia_cpt_sync_debug_log('ART Orchestrator: Mode MIRRORED - Checking 1:1 resource');
        
        // Get the mirrored resource ID
        $resource_id = $config->mode_settings['mirrored_resource_id'] ?? null;
        
        if (!$resource_id) {
            amelia_cpt_sync_debug_log('ART Orchestrator: No mirrored resource ID configured');
            
            $result['resource_block'] = true;
            $result['resource_message'] = 'Resource not configured for this service';
            $result['early_return'] = true;
            
            return $result;
        }
        
        // Check resource availability
        $is_available = $this->resource_manager->is_resource_available(
            $resource_id,
            $params['date'],
            $params['time'],
            $params['duration']
        );
        
        // Get resource details
        $resource = $this->resource_manager->get_resource($resource_id);
        $resource_name = is_wp_error($resource) ? 'Unknown Resource' : ($resource['name'] ?? 'Unknown Resource');
        
        // Build resource result
        $result['resources'] = array(
            'config' => array('mode' => 'mirrored'),
            'assigned' => array(
                array(
                    'id' => $resource_id,
                    'name' => $resource_name,
                    'status' => $is_available ? 'available' : 'unavailable'
                )
            )
        );
        
        // If resource unavailable, block everything
        if (!$is_available) {
            amelia_cpt_sync_debug_log('ART Orchestrator: Mirrored resource BLOCKED - ' . $resource_name);
            
            $next_available = $this->resource_manager->get_next_available($resource_id, $params['date']);
            
            $result['resource_block'] = true;
            $result['resource_message'] = sprintf('%s is booked for this time', $resource_name);
            $result['resources']['next_available'] = $next_available;
            
            // Get providers but mark all as blocked
            $result['providers'] = $this->get_all_providers_blocked($params['service_id'], 'Resource unavailable');
            
            $result['can_book'] = false;
            $result['early_return'] = true;
            
            return $result;
        }
        
        amelia_cpt_sync_debug_log('ART Orchestrator: Mirrored resource AVAILABLE - ' . $resource_name);
        amelia_cpt_sync_debug_log('ART Orchestrator: Proceeding to provider availability check');
        
        // Resource available, proceed with provider check
        try {
            $provider_result = $this->availability_engine->check_availability(
                $params['date'],
                $params['time'],
                $params['service_id'],
                $params['duration'],
                $params['location_id'] ?? null
            );
            
            // Availability Engine returns providers array directly (not wrapped)
            amelia_cpt_sync_debug_log('ART Orchestrator: Provider check complete - ' . count($provider_result) . ' providers returned');
            amelia_cpt_sync_debug_log('ART Orchestrator: Sample provider - ' . wp_json_encode(array_slice($provider_result, 0, 1)));
            
            $result['providers'] = $provider_result;  // Direct assignment
            
            amelia_cpt_sync_debug_log('ART Orchestrator: Assigned to result, count: ' . count($result['providers']));
            
        } catch (Exception $e) {
            amelia_cpt_sync_debug_log('ART Orchestrator: Provider check FAILED - ' . $e->getMessage());
            $result['providers'] = array();
        }
        
        amelia_cpt_sync_debug_log('ART Orchestrator: Returning mirrored mode result with ' . count($result['providers']) . ' providers');
        
        return $result;
    }
    
    /**
     * Mode 2: Shared Pool - Placeholder for Phase 2
     */
    private function check_mode_shared_pool($params, $config, $result) {
        amelia_cpt_sync_debug_log('ART Orchestrator: Mode SHARED_POOL - Not implemented yet (Phase 2)');
        
        $result['resource_message'] = 'Shared Pool mode coming in Phase 2';
        $result['providers'] = array();
        
        return $result;
    }
    
    /**
     * Mode 3: Quantity Pool - Placeholder for Phase 2
     */
    private function check_mode_quantity_pool($params, $config, $result) {
        amelia_cpt_sync_debug_log('ART Orchestrator: Mode QUANTITY_POOL - Not implemented yet (Phase 2)');
        
        $result['resource_message'] = 'Quantity Pool mode coming in Phase 2';
        $result['providers'] = array();
        
        return $result;
    }
    
    /**
     * Mode 4: Provider Bound - Placeholder for Phase 3
     */
    private function check_mode_provider_bound($params, $config, $result) {
        amelia_cpt_sync_debug_log('ART Orchestrator: Mode PROVIDER_BOUND - Not implemented yet (Phase 3)');
        
        $result['resource_message'] = 'Provider Bound mode coming in Phase 3';
        $result['providers'] = array();
        
        return $result;
    }
    
    /**
     * Mode 5: Location Bound - Placeholder for Phase 3
     */
    private function check_mode_location_bound($params, $config, $result) {
        amelia_cpt_sync_debug_log('ART Orchestrator: Mode LOCATION_BOUND - Not implemented yet (Phase 3)');
        
        $result['resource_message'] = 'Location Bound mode coming in Phase 3';
        $result['providers'] = array();
        
        return $result;
    }
    
    /**
     * Mode 6: Composite - Placeholder for Phase 4
     */
    private function check_mode_composite($params, $config, $result) {
        amelia_cpt_sync_debug_log('ART Orchestrator: Mode COMPOSITE - Not implemented yet (Phase 4)');
        
        $result['resource_message'] = 'Composite mode coming in Phase 4';
        $result['providers'] = array();
        
        return $result;
    }
    
    /**
     * Mode 7: Hybrid - Placeholder for Phase 4
     */
    private function check_mode_hybrid($params, $config, $result) {
        amelia_cpt_sync_debug_log('ART Orchestrator: Mode HYBRID - Not implemented yet (Phase 4)');
        
        $result['resource_message'] = 'Hybrid mode coming in Phase 4';
        $result['providers'] = array();
        
        return $result;
    }
    
    // ========================================
    // HELPER METHODS
    // ========================================
    
    /**
     * Get all providers for a service, marked as blocked
     *
     * @param int $service_id Service ID
     * @param string $reason Block reason
     * @return array Array of blocked providers
     */
    private function get_all_providers_blocked($service_id, $reason) {
        // Get providers for this service
        $providers = $this->api_manager->get_service_employees($service_id);
        
        if (is_wp_error($providers)) {
            return array();
        }
        
        $blocked_list = array();
        
        foreach ($providers as $provider) {
            $blocked_list[] = array(
                'id' => $provider['id'],
                'name' => ($provider['firstName'] ?? '') . ' ' . ($provider['lastName'] ?? ''),
                'status' => 'unavailable',
                'conflicts' => array($reason),
                'resource_blocked' => true
            );
        }
        
        amelia_cpt_sync_debug_log('ART Orchestrator: Marked ' . count($blocked_list) . ' providers as blocked');
        
        return $blocked_list;
    }
    
    /**
     * Determine if booking can proceed
     *
     * @param array $result Orchestrator result
     * @param array $params Original parameters
     * @return bool True if can book
     */
    private function evaluate_can_book($result, $params) {
        // Resource explicitly blocked
        if ($result['resource_block']) {
            return false;
        }
        
        // Requires resource selection but none selected
        if ($result['requires_resource_selection'] && empty($params['selected_resources'])) {
            return false;
        }
        
        // No providers available or might_conflict
        $bookable_providers = array_filter($result['providers'], function($p) {
            return in_array($p['status'], array('available', 'might_conflict'));
        });
        
        if (empty($bookable_providers)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Get service details (helper)
     *
     * @param int $service_id Service ID
     * @return array|WP_Error Service data or error
     */
    private function get_service($service_id) {
        $response = $this->api_manager->get_service($service_id);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return $response['data']['service'] ?? new WP_Error('no_service', 'Service not found');
    }
}

