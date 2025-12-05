<?php
/**
 * ART Resource API Class
 *
 * Handles all API calls to Amelia's resource endpoints
 * Provides wrapper methods for CRUD operations on resources
 *
 * @package AmeliaCPTSync
 * @subpackage ART
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class ART_Resource_API {
    
    /**
     * API Manager instance
     *
     * @var Amelia_CPT_Sync_ART_API_Manager
     */
    private $api_manager;
    
    /**
     * Resource cache
     *
     * @var array|null
     */
    private $resource_cache = null;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->api_manager = new Amelia_CPT_Sync_ART_API_Manager();
    }
    
    /**
     * Get all resources from Amelia
     *
     * @param bool $use_cache Whether to use cached data
     * @return array|WP_Error Resources array or error
     */
    public function get_resources($use_cache = true) {
        if ($use_cache && $this->resource_cache !== null) {
            amelia_cpt_sync_debug_log('ART Resource API: Using cached resources');
            return $this->resource_cache;
        }
        
        amelia_cpt_sync_debug_log('ART Resource API: Fetching all resources from Amelia');
        
        $response = $this->api_manager->api_request('/resources', 'GET');
        
        if (is_wp_error($response)) {
            amelia_cpt_sync_debug_log('ART Resource API: Failed to fetch resources - ' . $response->get_error_message());
            return $response;
        }
        
        $resources = $response['data']['resources'] ?? array();
        
        amelia_cpt_sync_debug_log('ART Resource API: Fetched ' . count($resources) . ' resources');
        
        if ($use_cache) {
            $this->resource_cache = $resources;
        }
        
        return $resources;
    }
    
    /**
     * Get a single resource by ID
     *
     * @param int $resource_id Resource ID
     * @return array|WP_Error Resource data or error
     */
    public function get_resource($resource_id) {
        amelia_cpt_sync_debug_log('ART Resource API: Fetching resource #' . $resource_id);
        
        $response = $this->api_manager->api_request('/resources/' . absint($resource_id), 'GET');
        
        if (is_wp_error($response)) {
            amelia_cpt_sync_debug_log('ART Resource API: Failed to fetch resource - ' . $response->get_error_message());
            return $response;
        }
        
        return $response['data']['resource'] ?? new WP_Error('no_data', 'No resource data in response');
    }
    
    /**
     * Create a new resource in Amelia
     *
     * @param array $data Resource data
     * @return array|WP_Error Created resource or error
     */
    public function create_resource($data) {
        amelia_cpt_sync_debug_log('ART Resource API: Creating resource - ' . ($data['name'] ?? 'unnamed'));
        
        $response = $this->api_manager->api_request('/resources', 'POST', $data);
        
        if (is_wp_error($response)) {
            amelia_cpt_sync_debug_log('ART Resource API: Failed to create resource - ' . $response->get_error_message());
            return $response;
        }
        
        // Clear cache
        $this->resource_cache = null;
        
        amelia_cpt_sync_debug_log('ART Resource API: Successfully created resource #' . ($response['data']['resource']['id'] ?? 'unknown'));
        
        return $response['data']['resource'] ?? new WP_Error('no_data', 'No resource data in response');
    }
    
    /**
     * Update a resource in Amelia
     *
     * @param int $resource_id Resource ID
     * @param array $data Update data
     * @return array|WP_Error Updated resource or error
     */
    public function update_resource($resource_id, $data) {
        amelia_cpt_sync_debug_log('ART Resource API: Updating resource #' . $resource_id);
        
        $response = $this->api_manager->api_request('/resources/' . absint($resource_id), 'POST', $data);
        
        if (is_wp_error($response)) {
            amelia_cpt_sync_debug_log('ART Resource API: Failed to update resource - ' . $response->get_error_message());
            return $response;
        }
        
        // Clear cache
        $this->resource_cache = null;
        
        amelia_cpt_sync_debug_log('ART Resource API: Successfully updated resource #' . $resource_id);
        
        return $response['data']['resource'] ?? new WP_Error('no_data', 'No resource data in response');
    }
    
    /**
     * Delete a resource from Amelia
     *
     * @param int $resource_id Resource ID
     * @return bool|WP_Error True on success or error
     */
    public function delete_resource($resource_id) {
        amelia_cpt_sync_debug_log('ART Resource API: Deleting resource #' . $resource_id);
        
        $response = $this->api_manager->api_request('/resources/delete/' . absint($resource_id), 'POST');
        
        if (is_wp_error($response)) {
            amelia_cpt_sync_debug_log('ART Resource API: Failed to delete resource - ' . $response->get_error_message());
            return $response;
        }
        
        // Clear cache
        $this->resource_cache = null;
        
        amelia_cpt_sync_debug_log('ART Resource API: Successfully deleted resource #' . $resource_id);
        
        return true;
    }
    
    /**
     * Get resources linked to a specific service
     *
     * @param int $service_id Service ID
     * @return array|WP_Error Array of resources or error
     */
    public function get_service_resources($service_id) {
        $all_resources = $this->get_resources();
        
        if (is_wp_error($all_resources)) {
            return $all_resources;
        }
        
        $service_resources = array();
        
        foreach ($all_resources as $resource) {
            $entities = $resource['entities'] ?? array();
            
            // Check if this resource is linked to this service
            foreach ($entities as $entity) {
                if (isset($entity['entityType']) && $entity['entityType'] === 'service' &&
                    isset($entity['entityId']) && intval($entity['entityId']) === intval($service_id)) {
                    $service_resources[] = $resource;
                    break;
                }
            }
        }
        
        amelia_cpt_sync_debug_log('ART Resource API: Found ' . count($service_resources) . ' resources for service #' . $service_id);
        
        return $service_resources;
    }
    
    /**
     * Link a resource to a service
     *
     * @param int $resource_id Resource ID
     * @param int $service_id Service ID
     * @return array|WP_Error Updated resource or error
     */
    public function link_resource_to_service($resource_id, $service_id) {
        amelia_cpt_sync_debug_log('ART Resource API: Linking resource #' . $resource_id . ' to service #' . $service_id);
        
        // Get current resource
        $resource = $this->get_resource($resource_id);
        
        if (is_wp_error($resource)) {
            return $resource;
        }
        
        // Get current entities
        $entities = $resource['entities'] ?? array();
        
        // Check if already linked
        foreach ($entities as $entity) {
            if (isset($entity['entityType']) && $entity['entityType'] === 'service' &&
                isset($entity['entityId']) && intval($entity['entityId']) === intval($service_id)) {
                amelia_cpt_sync_debug_log('ART Resource API: Resource already linked to service');
                return $resource;
            }
        }
        
        // Add service entity
        $entities[] = array(
            'entityId' => intval($service_id),
            'entityType' => 'service'
        );
        
        // Update resource
        return $this->update_resource($resource_id, array(
            'entities' => array_values($entities)
        ));
    }
    
    /**
     * Unlink a resource from a service
     *
     * @param int $resource_id Resource ID
     * @param int $service_id Service ID
     * @return array|WP_Error Updated resource or error
     */
    public function unlink_resource_from_service($resource_id, $service_id) {
        amelia_cpt_sync_debug_log('ART Resource API: Unlinking resource #' . $resource_id . ' from service #' . $service_id);
        
        // Get current resource
        $resource = $this->get_resource($resource_id);
        
        if (is_wp_error($resource)) {
            return $resource;
        }
        
        // Get current entities
        $entities = $resource['entities'] ?? array();
        
        // Remove service entity
        $filtered_entities = array();
        foreach ($entities as $entity) {
            if (isset($entity['entityType']) && $entity['entityType'] === 'service' &&
                isset($entity['entityId']) && intval($entity['entityId']) === intval($service_id)) {
                continue; // Skip this entity
            }
            $filtered_entities[] = $entity;
        }
        
        // Update resource
        return $this->update_resource($resource_id, array(
            'entities' => array_values($filtered_entities)
        ));
    }
    
    /**
     * Get resources linked to a provider
     *
     * @param int $provider_id Provider ID
     * @return array|WP_Error Array of resources or error
     */
    public function get_provider_resources($provider_id) {
        $all_resources = $this->get_resources();
        
        if (is_wp_error($all_resources)) {
            return $all_resources;
        }
        
        $provider_resources = array();
        
        foreach ($all_resources as $resource) {
            $entities = $resource['entities'] ?? array();
            
            foreach ($entities as $entity) {
                if (isset($entity['entityType']) && $entity['entityType'] === 'employee' &&
                    isset($entity['entityId']) && intval($entity['entityId']) === intval($provider_id)) {
                    $provider_resources[] = $resource;
                    break;
                }
            }
        }
        
        return $provider_resources;
    }
    
    /**
     * Get resources linked to a location
     *
     * @param int $location_id Location ID
     * @return array|WP_Error Array of resources or error
     */
    public function get_location_resources($location_id) {
        $all_resources = $this->get_resources();
        
        if (is_wp_error($all_resources)) {
            return $all_resources;
        }
        
        $location_resources = array();
        
        foreach ($all_resources as $resource) {
            $entities = $resource['entities'] ?? array();
            
            foreach ($entities as $entity) {
                if (isset($entity['entityType']) && $entity['entityType'] === 'location' &&
                    isset($entity['entityId']) && intval($entity['entityId']) === intval($location_id)) {
                    $location_resources[] = $resource;
                    break;
                }
            }
        }
        
        return $location_resources;
    }
    
    /**
     * Clear resource cache
     *
     * @return void
     */
    public function clear_cache() {
        $this->resource_cache = null;
        amelia_cpt_sync_debug_log('ART Resource API: Cache cleared');
    }
}

