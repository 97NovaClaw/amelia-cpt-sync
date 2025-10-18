<?php
/**
 * Admin Settings Class
 *
 * Handles the admin settings page UI and saving the JSON configuration
 *
 * @package AmeliaCPTSync
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class Amelia_CPT_Sync_Admin_Settings {
    
    /**
     * The settings option name
     */
    private $option_name = 'amelia_cpt_sync_settings';
    
    /**
     * Initialize the class
     */
    public function init() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_amelia_cpt_sync_save_settings', array($this, 'ajax_save_settings'));
        add_action('wp_ajax_amelia_cpt_sync_get_taxonomies', array($this, 'ajax_get_taxonomies'));
        add_action('wp_ajax_amelia_cpt_sync_full_sync', array($this, 'ajax_full_sync'));
    }
    
    /**
     * Add admin menu page
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Amelia to CPT Sync', 'amelia-cpt-sync'),
            __('Amelia to CPT Sync', 'amelia-cpt-sync'),
            'manage_options',
            'amelia-cpt-sync',
            array($this, 'render_settings_page'),
            'dashicons-update',
            80
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting(
            'amelia_cpt_sync_settings_group',
            $this->option_name,
            array($this, 'sanitize_settings')
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on our settings page
        if ('toplevel_page_amelia-cpt-sync' !== $hook) {
            return;
        }
        
        wp_enqueue_style(
            'amelia-cpt-sync-admin',
            AMELIA_CPT_SYNC_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            AMELIA_CPT_SYNC_VERSION
        );
        
        wp_enqueue_script(
            'amelia-cpt-sync-admin',
            AMELIA_CPT_SYNC_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            AMELIA_CPT_SYNC_VERSION,
            true
        );
        
        wp_localize_script('amelia-cpt-sync-admin', 'ameliaCptSync', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('amelia_cpt_sync_nonce')
        ));
    }
    
    /**
     * Render the settings page
     */
    public function render_settings_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Get saved settings
        $settings = $this->get_settings();
        
        // Get all registered CPTs (excluding built-in types)
        $cpts = $this->get_custom_post_types();
        
        // Get taxonomies for selected CPT
        $taxonomies = array();
        if (!empty($settings['cpt_slug'])) {
            $taxonomies = $this->get_taxonomies_for_cpt($settings['cpt_slug']);
        }
        
        // Include the template
        include AMELIA_CPT_SYNC_PLUGIN_DIR . 'templates/admin-settings-page.php';
    }
    
    /**
     * Get settings from database
     */
    public function get_settings() {
        $settings_json = get_option($this->option_name);
        
        if (empty($settings_json)) {
            return array(
                'cpt_slug' => '',
                'taxonomy_slug' => '',
                'field_mappings' => array(
                    'service_id' => '',
                    'category_id' => '',
                    'primary_photo' => '',
                    'price' => '',
                    'duration' => '',
                    'duration_format' => 'seconds',
                    'gallery' => '',
                    'extras' => ''
                )
            );
        }
        
        return json_decode($settings_json, true);
    }
    
    /**
     * Get all custom post types
     */
    private function get_custom_post_types() {
        $args = array(
            'public' => true,
            '_builtin' => false
        );
        
        $post_types = get_post_types($args, 'objects');
        
        return $post_types;
    }
    
    /**
     * Get taxonomies for a specific CPT
     */
    private function get_taxonomies_for_cpt($cpt_slug) {
        $taxonomies = get_object_taxonomies($cpt_slug, 'objects');
        return $taxonomies;
    }
    
    /**
     * AJAX handler to get taxonomies for selected CPT
     */
    public function ajax_get_taxonomies() {
        check_ajax_referer('amelia_cpt_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $cpt_slug = sanitize_text_field($_POST['cpt_slug']);
        
        if (empty($cpt_slug)) {
            wp_send_json_error(array('message' => 'No CPT slug provided'));
        }
        
        $taxonomies = $this->get_taxonomies_for_cpt($cpt_slug);
        
        $taxonomy_options = array();
        foreach ($taxonomies as $taxonomy) {
            $taxonomy_options[] = array(
                'slug' => $taxonomy->name,
                'label' => $taxonomy->label
            );
        }
        
        wp_send_json_success(array('taxonomies' => $taxonomy_options));
    }
    
    /**
     * AJAX handler to save settings
     */
    public function ajax_save_settings() {
        check_ajax_referer('amelia_cpt_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        // Get POST data
        $cpt_slug = sanitize_text_field($_POST['cpt_slug']);
        $taxonomy_slug = sanitize_text_field($_POST['taxonomy_slug']);
        $service_id_field = sanitize_text_field($_POST['service_id_field']);
        $category_id_field = sanitize_text_field($_POST['category_id_field']);
        $primary_photo_field = sanitize_text_field($_POST['primary_photo_field']);
        $price_field = sanitize_text_field($_POST['price_field']);
        $duration_field = sanitize_text_field($_POST['duration_field']);
        $duration_format = sanitize_text_field($_POST['duration_format']);
        $gallery_field = sanitize_text_field($_POST['gallery_field']);
        $extras_field = sanitize_text_field($_POST['extras_field']);
        
        // Build settings array
        $settings = array(
            'cpt_slug' => $cpt_slug,
            'taxonomy_slug' => $taxonomy_slug,
            'field_mappings' => array(
                'service_id' => $service_id_field,
                'category_id' => $category_id_field,
                'primary_photo' => $primary_photo_field,
                'price' => $price_field,
                'duration' => $duration_field,
                'duration_format' => $duration_format,
                'gallery' => $gallery_field,
                'extras' => $extras_field
            )
        );
        
        // Save as JSON
        update_option($this->option_name, json_encode($settings));
        
        wp_send_json_success(array('message' => 'Settings saved successfully!'));
    }
    
    /**
     * AJAX handler for full sync
     */
    public function ajax_full_sync() {
        check_ajax_referer('amelia_cpt_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        // Get settings
        $settings = $this->get_settings();
        
        if (empty($settings['cpt_slug'])) {
            wp_send_json_error(array('message' => 'Please configure sync settings first'));
        }
        
        global $wpdb;
        
        // Fetch all services from Amelia database
        $services_table = $wpdb->prefix . 'amelia_services';
        $categories_table = $wpdb->prefix . 'amelia_categories';
        
        // Check if Amelia tables exist
        if ($wpdb->get_var("SHOW TABLES LIKE '$services_table'") !== $services_table) {
            wp_send_json_error(array('message' => 'Amelia database tables not found'));
        }
        
        // Get all services with category names
        $services = $wpdb->get_results(
            "SELECT s.*, c.name as categoryName 
             FROM $services_table s 
             LEFT JOIN $categories_table c ON s.categoryId = c.id 
             ORDER BY s.id ASC",
            ARRAY_A
        );
        
        if (empty($services)) {
            wp_send_json_error(array('message' => 'No services found in Amelia'));
        }
        
        // Initialize CPT Manager
        $cpt_manager = new Amelia_CPT_Sync_CPT_Manager();
        
        $results = array(
            'total' => count($services),
            'synced' => 0,
            'created' => 0,
            'updated' => 0,
            'errors' => array()
        );
        
        // Sync each service
        foreach ($services as $service) {
            // Prepare service data
            $service_data = $this->prepare_service_data($service);
            
            // Sync the service
            $result = $cpt_manager->sync_service($service_data);
            
            if (is_wp_error($result)) {
                $results['errors'][] = array(
                    'service' => $service['name'],
                    'error' => $result->get_error_message()
                );
            } else {
                $results['synced']++;
                
                // Check if it was created or updated
                $existing = $wpdb->get_var($wpdb->prepare(
                    "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_amelia_service_id' AND meta_value = %d",
                    $service['id']
                ));
                
                if ($existing) {
                    $results['updated']++;
                } else {
                    $results['created']++;
                }
            }
        }
        
        wp_send_json_success($results);
    }
    
    /**
     * Prepare service data for sync
     */
    private function prepare_service_data($service) {
        global $wpdb;
        
        // Decode JSON fields
        if (!empty($service['gallery']) && is_string($service['gallery'])) {
            $service['gallery'] = json_decode($service['gallery'], true);
        }
        
        if (!empty($service['extras']) && is_string($service['extras'])) {
            $service['extras'] = json_decode($service['extras'], true);
        }
        
        // Get full image paths
        $upload_dir = wp_upload_dir();
        
        if (!empty($service['picture'])) {
            $service['pictureFullPath'] = $upload_dir['baseurl'] . '/amelia/' . ltrim($service['picture'], '/');
        }
        
        // Process gallery images
        if (!empty($service['gallery']) && is_array($service['gallery'])) {
            foreach ($service['gallery'] as $key => $image) {
                if (isset($image['picture'])) {
                    $service['gallery'][$key]['pictureFullPath'] = $upload_dir['baseurl'] . '/amelia/' . ltrim($image['picture'], '/');
                }
            }
        }
        
        return $service;
    }
    
    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        // This is handled by AJAX, but keep for compatibility
        return $input;
    }
}

