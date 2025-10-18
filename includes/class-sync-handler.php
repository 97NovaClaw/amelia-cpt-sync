<?php
/**
 * Sync Handler Class
 *
 * Contains the core sync logic that hooks into Amelia's action hooks
 *
 * @package AmeliaCPTSync
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class Amelia_CPT_Sync_Handler {
    
    /**
     * The CPT Manager instance
     */
    private $cpt_manager;
    
    /**
     * Initialize the class
     */
    public function init() {
        // Initialize CPT Manager
        $this->cpt_manager = new Amelia_CPT_Sync_CPT_Manager();
        
        // Hook into Amelia's action hooks for event-driven sync
        add_action('amelia_after_service_added', array($this, 'handle_service_added'), 10, 1);
        add_action('amelia_after_service_updated', array($this, 'handle_service_updated'), 10, 1);
        add_action('amelia_before_service_deleted', array($this, 'handle_service_deleted'), 10, 1);
        
        // Add admin notices for sync results
        add_action('admin_notices', array($this, 'display_admin_notices'));
    }
    
    /**
     * Handle service added event
     *
     * @param array|object $service_data The service data from Amelia
     */
    public function handle_service_added($service_data) {
        $this->log_debug('Service Added Event Triggered', $service_data);
        
        // Convert to array if object
        if (is_object($service_data)) {
            $service_data = $this->object_to_array($service_data);
        }
        
        // Enrich service data with additional information
        $service_data = $this->enrich_service_data($service_data);
        
        // Sync the service
        $result = $this->cpt_manager->sync_service($service_data);
        
        if (is_wp_error($result)) {
            $this->log_error('Failed to sync new service', $result->get_error_message());
            $this->set_admin_notice('error', 'Failed to sync new service: ' . $result->get_error_message());
        } else {
            $this->log_debug('Successfully synced new service', array('post_id' => $result));
            $this->set_admin_notice('success', 'Successfully synced new service to CPT.');
        }
    }
    
    /**
     * Handle service updated event
     *
     * @param array|object $service_data The service data from Amelia
     */
    public function handle_service_updated($service_data) {
        $this->log_debug('Service Updated Event Triggered', $service_data);
        
        // Convert to array if object
        if (is_object($service_data)) {
            $service_data = $this->object_to_array($service_data);
        }
        
        // Enrich service data with additional information
        $service_data = $this->enrich_service_data($service_data);
        
        // Sync the service (same method handles both create and update)
        $result = $this->cpt_manager->sync_service($service_data);
        
        if (is_wp_error($result)) {
            $this->log_error('Failed to sync updated service', $result->get_error_message());
            $this->set_admin_notice('error', 'Failed to sync updated service: ' . $result->get_error_message());
        } else {
            $this->log_debug('Successfully synced updated service', array('post_id' => $result));
            $this->set_admin_notice('success', 'Successfully synced updated service to CPT.');
        }
    }
    
    /**
     * Handle service deleted event
     *
     * @param array|object $service_data The service data from Amelia
     */
    public function handle_service_deleted($service_data) {
        $this->log_debug('Service Deleted Event Triggered', $service_data);
        
        // Convert to array if object
        if (is_object($service_data)) {
            $service_data = $this->object_to_array($service_data);
        }
        
        // Get service ID
        $service_id = isset($service_data['id']) ? intval($service_data['id']) : 0;
        
        if (!$service_id) {
            $this->log_error('No service ID provided for deletion', $service_data);
            return;
        }
        
        // Delete the corresponding CPT post
        $result = $this->cpt_manager->delete_service($service_id);
        
        if (is_wp_error($result)) {
            $this->log_error('Failed to delete synced service', $result->get_error_message());
            $this->set_admin_notice('error', 'Failed to delete synced service: ' . $result->get_error_message());
        } else {
            $this->log_debug('Successfully deleted synced service', array('service_id' => $service_id));
            $this->set_admin_notice('success', 'Successfully deleted synced service from CPT.');
        }
    }
    
    /**
     * Enrich service data with additional information from Amelia
     *
     * This method fetches additional data that might not be included in the hook payload
     *
     * @param array $service_data The base service data
     * @return array Enriched service data
     */
    private function enrich_service_data($service_data) {
        // Get service ID
        $service_id = isset($service_data['id']) ? intval($service_data['id']) : 0;
        
        if (!$service_id) {
            return $service_data;
        }
        
        // Try to fetch full service data from Amelia's API or database
        // Note: This depends on Amelia's API structure. Adjust as needed.
        
        // Check if we need to fetch category name
        if (isset($service_data['categoryId']) && !isset($service_data['categoryName'])) {
            $service_data['categoryName'] = $this->get_amelia_category_name($service_data['categoryId']);
        }
        
        // Ensure full image paths are available
        if (isset($service_data['pictureFullPath']) && empty($service_data['pictureFullPath'])) {
            if (isset($service_data['picture'])) {
                $service_data['pictureFullPath'] = $this->get_amelia_image_url($service_data['picture']);
            }
        }
        
        // Ensure gallery images have full paths
        if (isset($service_data['gallery']) && is_array($service_data['gallery'])) {
            foreach ($service_data['gallery'] as $key => $image) {
                if (is_array($image) && isset($image['picture']) && !isset($image['pictureFullPath'])) {
                    $service_data['gallery'][$key]['pictureFullPath'] = $this->get_amelia_image_url($image['picture']);
                }
            }
        }
        
        return $service_data;
    }
    
    /**
     * Get Amelia category name by ID
     *
     * @param int $category_id The category ID
     * @return string The category name
     */
    private function get_amelia_category_name($category_id) {
        global $wpdb;
        
        // Get Amelia's categories table name
        $table_name = $wpdb->prefix . 'amelia_categories';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            return '';
        }
        
        // Fetch category name
        $category_name = $wpdb->get_var($wpdb->prepare(
            "SELECT name FROM $table_name WHERE id = %d",
            $category_id
        ));
        
        return $category_name ? $category_name : '';
    }
    
    /**
     * Get full Amelia image URL
     *
     * @param string $image_path The relative image path
     * @return string The full image URL
     */
    private function get_amelia_image_url($image_path) {
        if (empty($image_path)) {
            return '';
        }
        
        // If already a full URL, return as is
        if (strpos($image_path, 'http') === 0) {
            return $image_path;
        }
        
        // Get WordPress uploads directory
        $upload_dir = wp_upload_dir();
        
        // Construct full URL (adjust path as needed based on Amelia's structure)
        $full_url = $upload_dir['baseurl'] . '/amelia/' . ltrim($image_path, '/');
        
        return $full_url;
    }
    
    /**
     * Convert object to array recursively
     *
     * @param mixed $data The data to convert
     * @return array The converted array
     */
    private function object_to_array($data) {
        if (is_object($data)) {
            $data = get_object_vars($data);
        }
        
        if (is_array($data)) {
            return array_map(array($this, 'object_to_array'), $data);
        }
        
        return $data;
    }
    
    /**
     * Log debug message
     *
     * @param string $message The message
     * @param mixed $data Additional data
     */
    private function log_debug($message, $data = null) {
        if (defined('WP_DEBUG') && WP_DEBUG === true) {
            error_log('[Amelia CPT Sync] ' . $message);
            if ($data !== null) {
                error_log('[Amelia CPT Sync] Data: ' . print_r($data, true));
            }
        }
    }
    
    /**
     * Log error message
     *
     * @param string $message The message
     * @param mixed $data Additional data
     */
    private function log_error($message, $data = null) {
        error_log('[Amelia CPT Sync Error] ' . $message);
        if ($data !== null) {
            error_log('[Amelia CPT Sync Error] Data: ' . print_r($data, true));
        }
    }
    
    /**
     * Set admin notice
     *
     * @param string $type The notice type (success, error, warning, info)
     * @param string $message The notice message
     */
    private function set_admin_notice($type, $message) {
        $notices = get_transient('amelia_cpt_sync_admin_notices');
        
        if (!is_array($notices)) {
            $notices = array();
        }
        
        $notices[] = array(
            'type' => $type,
            'message' => $message
        );
        
        set_transient('amelia_cpt_sync_admin_notices', $notices, 60);
    }
    
    /**
     * Display admin notices
     */
    public function display_admin_notices() {
        $notices = get_transient('amelia_cpt_sync_admin_notices');
        
        if (!is_array($notices) || empty($notices)) {
            return;
        }
        
        foreach ($notices as $notice) {
            printf(
                '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                esc_attr($notice['type']),
                esc_html($notice['message'])
            );
        }
        
        delete_transient('amelia_cpt_sync_admin_notices');
    }
}

