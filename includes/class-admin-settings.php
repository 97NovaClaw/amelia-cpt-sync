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
        add_action('admin_footer', array($this, 'add_custom_fields_modal_html'));
        add_action('wp_ajax_amelia_cpt_sync_save_all', array($this, 'ajax_save_all'));
        add_action('wp_ajax_amelia_cpt_sync_get_taxonomies', array($this, 'ajax_get_taxonomies'));
        add_action('wp_ajax_amelia_cpt_sync_get_cpt_fields', array($this, 'ajax_get_cpt_fields'));
        add_action('wp_ajax_amelia_cpt_sync_get_taxonomy_fields', array($this, 'ajax_get_taxonomy_fields'));
        add_action('wp_ajax_amelia_cpt_sync_full_sync', array($this, 'ajax_full_sync'));
        add_action('wp_ajax_amelia_cpt_sync_view_log', array($this, 'ajax_view_log'));
        add_action('wp_ajax_amelia_cpt_sync_clear_log', array($this, 'ajax_clear_log'));
        add_action('wp_ajax_amelia_cpt_sync_get_custom_fields_modal', array($this, 'ajax_get_custom_fields_modal'));
        add_action('wp_ajax_amelia_cpt_sync_save_custom_field_values', array($this, 'ajax_save_custom_field_values'));
        add_action('wp_ajax_amelia_cpt_sync_get_taxonomy_custom_fields_modal', array($this, 'ajax_get_taxonomy_custom_fields_modal'));
        add_action('wp_ajax_amelia_cpt_sync_save_taxonomy_custom_field_values', array($this, 'ajax_save_taxonomy_custom_field_values'));
        add_action('wp_ajax_amelia_save_popup_configs', array($this, 'ajax_save_popup_configs'));
        add_action('wp_ajax_amelia_get_popup_config', array($this, 'ajax_get_popup_config'));
        add_action('wp_ajax_nopriv_amelia_get_popup_config', array($this, 'ajax_get_popup_config'));
        add_action('wp_ajax_amelia_resolve_popup_slug', array($this, 'ajax_resolve_popup_slug'));
        add_action('wp_ajax_amelia_cpt_sync_log_debug', array($this, 'ajax_log_debug'));
    }
    
    /**
     * Add admin menu page
     */
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            __('Amelia to CPT Sync', 'amelia-cpt-sync'),
            __('Amelia to CPT Sync', 'amelia-cpt-sync'),
            'manage_options',
            'amelia-cpt-sync',
            array($this, 'render_settings_page'),
            'dashicons-update',
            80
        );
        
        // Submenu: Popup Triggers
        add_submenu_page(
            'amelia-cpt-sync',
            __('Popup Triggers', 'amelia-cpt-sync'),
            __('Popup Triggers', 'amelia-cpt-sync'),
            'manage_options',
            'amelia-popup-triggers',
            array($this, 'render_popup_manager_page')
        );
    }
    
    /**
     * Render the popup manager page
     */
    public function render_popup_manager_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Include the template
        include AMELIA_CPT_SYNC_PLUGIN_DIR . 'templates/popup-manager-page.php';
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
        $js_file = AMELIA_CPT_SYNC_PLUGIN_DIR . 'assets/js/admin.js';
        $css_file = AMELIA_CPT_SYNC_PLUGIN_DIR . 'assets/css/admin.css';
        $modal_js_file = AMELIA_CPT_SYNC_PLUGIN_DIR . 'assets/js/amelia-modal.js';
        
        $js_version = AMELIA_CPT_SYNC_VERSION . '.' . (file_exists($js_file) ? filemtime($js_file) : time());
        $css_version = AMELIA_CPT_SYNC_VERSION . '.' . (file_exists($css_file) ? filemtime($css_file) : time());
        $modal_js_version = AMELIA_CPT_SYNC_VERSION . '.' . (file_exists($modal_js_file) ? filemtime($modal_js_file) : time());
        
        // Load on our settings page
        if ('toplevel_page_amelia-cpt-sync' === $hook) {
            wp_enqueue_style(
                'amelia-cpt-sync-admin',
                AMELIA_CPT_SYNC_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                $css_version
            );
            
            wp_enqueue_script(
                'amelia-cpt-sync-admin',
                AMELIA_CPT_SYNC_PLUGIN_URL . 'assets/js/admin.js',
                array('jquery', 'jquery-ui-sortable'),
                $js_version,
                true
            );
            
            wp_localize_script('amelia-cpt-sync-admin', 'ameliaCptSync', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('amelia_cpt_sync_nonce')
            ));
        }
        
        // Load modal script on ALL admin pages (Amelia might use different page slugs)
        // Check more broadly to ensure we catch Amelia's service pages
        $current_screen = get_current_screen();
        $is_amelia_page = false;
        
        // Check multiple conditions
        if ((isset($_GET['page']) && strpos($_GET['page'], 'wpamelia') !== false) ||
            (isset($_GET['page']) && strpos($_GET['page'], 'amelia') !== false) ||
            ($current_screen && strpos($current_screen->id, 'amelia') !== false)) {
            $is_amelia_page = true;
        }
        
        if ($is_amelia_page) {
            wp_enqueue_style('wp-jquery-ui-dialog');
            wp_enqueue_script('jquery-ui-dialog');
            
            wp_enqueue_script(
                'amelia-cpt-sync-modal',
                AMELIA_CPT_SYNC_PLUGIN_URL . 'assets/js/amelia-modal.js',
                array('jquery', 'jquery-ui-dialog'),
                $modal_js_version,
                true
            );
            
            wp_localize_script('amelia-cpt-sync-modal', 'ameliaCptSyncModal', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('amelia_cpt_sync_nonce'),
                'debug' => true
            ));
            
            amelia_cpt_sync_debug_log('Modal script enqueued on page: ' . (isset($_GET['page']) ? $_GET['page'] : 'unknown'));
        }
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
     * Get settings from wp_options
     */
    public function get_settings() {
        // Default settings structure
        $defaults = array(
            'cpt_slug' => '',
            'taxonomy_slug' => '',
            'debug_enabled' => false,
            'taxonomy_meta' => array(
                'category_id' => ''
            ),
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
        
        // Get settings from database
        $saved_settings = get_option($this->option_name, array());
        
        // Merge with defaults to ensure all keys exist
        return array_replace_recursive($defaults, $saved_settings);
    }
    
    /**
     * Save settings to wp_options
     *
     * @param array $settings The settings array to save
     * @return bool True on success, false on failure
     */
    private function save_settings($settings) {
        amelia_cpt_sync_debug_log('Saving settings to wp_options');
        amelia_cpt_sync_debug_log('Settings: ' . print_r($settings, true));
        
        $result = update_option($this->option_name, $settings);
        
        if ($result) {
            amelia_cpt_sync_debug_log('SUCCESS: Settings saved to wp_options');
            return true;
        } else {
            amelia_cpt_sync_debug_log('ERROR: Failed to save settings to wp_options');
            return false;
        }
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
     * AJAX handler to save all settings (unified)
     * Saves both plugin settings and custom field definitions in one transaction
     */
    public function ajax_save_all() {
        check_ajax_referer('amelia_cpt_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        // Log raw POST data
        amelia_cpt_sync_debug_log('========== AJAX SAVE SETTINGS REQUEST ==========');
        amelia_cpt_sync_debug_log('Raw $_POST data: ' . print_r($_POST, true));
        
        // Get POST data with isset checks
        $cpt_slug = isset($_POST['cpt_slug']) ? sanitize_text_field($_POST['cpt_slug']) : '';
        $taxonomy_slug = isset($_POST['taxonomy_slug']) ? sanitize_text_field($_POST['taxonomy_slug']) : '';
        
        // Debug checkbox value processing
        amelia_cpt_sync_debug_log('Checkbox processing:');
        amelia_cpt_sync_debug_log('  - isset($_POST[debug_enabled]): ' . (isset($_POST['debug_enabled']) ? 'YES' : 'NO'));
        if (isset($_POST['debug_enabled'])) {
            amelia_cpt_sync_debug_log('  - $_POST[debug_enabled] raw value: "' . $_POST['debug_enabled'] . '"');
            amelia_cpt_sync_debug_log('  - $_POST[debug_enabled] === "true": ' . ($_POST['debug_enabled'] === 'true' ? 'YES' : 'NO'));
        }
        
        $debug_enabled = isset($_POST['debug_enabled']) && $_POST['debug_enabled'] === 'true';
        amelia_cpt_sync_debug_log('  - Final $debug_enabled value: ' . ($debug_enabled ? 'TRUE' : 'FALSE'));
        
        $taxonomy_category_id_field = isset($_POST['taxonomy_category_id_field']) ? sanitize_text_field($_POST['taxonomy_category_id_field']) : '';
        $service_id_field = isset($_POST['service_id_field']) ? sanitize_text_field($_POST['service_id_field']) : '';
        $category_id_field = isset($_POST['category_id_field']) ? sanitize_text_field($_POST['category_id_field']) : '';
        $primary_photo_field = isset($_POST['primary_photo_field']) ? sanitize_text_field($_POST['primary_photo_field']) : '';
        $price_field = isset($_POST['price_field']) ? sanitize_text_field($_POST['price_field']) : '';
        $duration_field = isset($_POST['duration_field']) ? sanitize_text_field($_POST['duration_field']) : '';
        $duration_format = isset($_POST['duration_format']) ? sanitize_text_field($_POST['duration_format']) : 'seconds';
        $gallery_field = isset($_POST['gallery_field']) ? sanitize_text_field($_POST['gallery_field']) : '';
        $extras_field = isset($_POST['extras_field']) ? sanitize_text_field($_POST['extras_field']) : '';
        
        amelia_cpt_sync_debug_log('Sanitized field values:');
        amelia_cpt_sync_debug_log('  - cpt_slug: "' . $cpt_slug . '"');
        amelia_cpt_sync_debug_log('  - taxonomy_slug: "' . $taxonomy_slug . '"');
        amelia_cpt_sync_debug_log('  - debug_enabled: ' . ($debug_enabled ? 'TRUE' : 'FALSE'));
        amelia_cpt_sync_debug_log('  - price_field: "' . $price_field . '"');
        amelia_cpt_sync_debug_log('  - duration_field: "' . $duration_field . '"');
        
        // Build settings array
        $settings = array(
            'cpt_slug' => $cpt_slug,
            'taxonomy_slug' => $taxonomy_slug,
            'debug_enabled' => $debug_enabled,
            'taxonomy_meta' => array(
                'category_id' => $taxonomy_category_id_field
            ),
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
        
        // Save to JSON file
        amelia_cpt_sync_debug_log('---------- Building Settings Array ----------');
        amelia_cpt_sync_debug_log('Built settings array: ' . print_r($settings, true));
        amelia_cpt_sync_debug_log('Settings array [debug_enabled] type: ' . gettype($settings['debug_enabled']));
        amelia_cpt_sync_debug_log('Settings array [debug_enabled] value: ' . var_export($settings['debug_enabled'], true));
        
        amelia_cpt_sync_debug_log('---------- Calling save_settings() ----------');
        $save_result = $this->save_settings($settings);
        amelia_cpt_sync_debug_log('save_settings() returned: ' . ($save_result ? 'TRUE' : 'FALSE'));
        
        // Verify by reading back
        amelia_cpt_sync_debug_log('---------- Verifying Saved Settings ----------');
        $verified_settings = $this->get_settings();
        amelia_cpt_sync_debug_log('Read back from file: ' . print_r($verified_settings, true));
        
        $verify_success = ($verified_settings === $settings);
        amelia_cpt_sync_debug_log('Verification result: ' . ($verify_success ? 'MATCH' : 'MISMATCH'));
        
        if (!$verify_success) {
            amelia_cpt_sync_debug_log('ERROR: Settings verification failed!');
            amelia_cpt_sync_debug_log('Expected debug_enabled: ' . var_export($settings['debug_enabled'], true));
            amelia_cpt_sync_debug_log('Got debug_enabled: ' . var_export($verified_settings['debug_enabled'], true));
            
            // Detailed comparison
            foreach ($settings as $key => $value) {
                if ($verified_settings[$key] !== $value) {
                    amelia_cpt_sync_debug_log("  MISMATCH on key '$key':");
                    amelia_cpt_sync_debug_log('    Expected: ' . print_r($value, true));
                    amelia_cpt_sync_debug_log('    Got: ' . print_r($verified_settings[$key], true));
                }
            }
        }
        
        amelia_cpt_sync_debug_log('---------- Saving Service Custom Field Definitions ----------');
        
        // Save service custom field definitions if provided
        if (isset($_POST['custom_fields']) && is_array($_POST['custom_fields'])) {
            $custom_fields_defs = $_POST['custom_fields'];
            amelia_cpt_sync_debug_log('Service custom field definitions: ' . print_r($custom_fields_defs, true));
            
            $cf_manager = new Amelia_CPT_Sync_Custom_Fields_Manager();
            $cf_result = $cf_manager->save_field_definitions($custom_fields_defs);
            
            amelia_cpt_sync_debug_log('Service custom fields save result: ' . ($cf_result ? 'SUCCESS' : 'FAILED'));
        } else {
            amelia_cpt_sync_debug_log('No service custom field definitions in request');
        }
        
        amelia_cpt_sync_debug_log('---------- Saving Taxonomy Custom Field Definitions ----------');
        
        // Save taxonomy custom field definitions if provided
        if (isset($_POST['taxonomy_custom_fields']) && is_array($_POST['taxonomy_custom_fields'])) {
            $taxonomy_custom_fields_defs = $_POST['taxonomy_custom_fields'];
            amelia_cpt_sync_debug_log('Taxonomy custom field definitions: ' . print_r($taxonomy_custom_fields_defs, true));
            
            $tax_cf_manager = new Amelia_CPT_Sync_Taxonomy_Custom_Fields_Manager();
            $tax_cf_result = $tax_cf_manager->save_field_definitions($taxonomy_custom_fields_defs);
            
            amelia_cpt_sync_debug_log('Taxonomy custom fields save result: ' . ($tax_cf_result ? 'SUCCESS' : 'FAILED'));
        } else {
            amelia_cpt_sync_debug_log('No taxonomy custom field definitions in request');
        }
        
        amelia_cpt_sync_debug_log('========== END AJAX SAVE ALL ==========');
        
        wp_send_json_success(array(
            'message' => 'All settings saved successfully!',
            'debug' => array(
                'saved' => $save_result,
                'verified' => $verify_success,
                'storage' => 'wp_options',
                'settings' => $settings
            )
        ));
    }
    
    /**
     * AJAX handler to get available CPT fields for dropdowns
     */
    public function ajax_get_cpt_fields() {
        check_ajax_referer('amelia_cpt_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $cpt_slug = isset($_POST['cpt_slug']) ? sanitize_text_field($_POST['cpt_slug']) : '';
        
        if (empty($cpt_slug)) {
            wp_send_json_error(array('message' => 'No CPT slug provided'));
        }
        
        $detector = new Amelia_CPT_Sync_Field_Detector();
        $fields = $detector->get_cpt_meta_fields($cpt_slug);
        
        wp_send_json_success(array(
            'fields' => $fields,
            'count' => count($fields)
        ));
    }
    
    /**
     * AJAX handler to get available taxonomy term meta fields for dropdowns
     */
    public function ajax_get_taxonomy_fields() {
        check_ajax_referer('amelia_cpt_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $taxonomy_slug = isset($_POST['taxonomy_slug']) ? sanitize_text_field($_POST['taxonomy_slug']) : '';
        
        if (empty($taxonomy_slug)) {
            wp_send_json_error(array('message' => 'No taxonomy slug provided'));
        }
        
        $detector = new Amelia_CPT_Sync_Field_Detector();
        $fields = $detector->get_taxonomy_meta_fields($taxonomy_slug);
        
        wp_send_json_success(array(
            'fields' => $fields,
            'count' => count($fields)
        ));
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
     * AJAX handler to view debug log
     */
    public function ajax_view_log() {
        check_ajax_referer('amelia_cpt_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $log_file = AMELIA_CPT_SYNC_PLUGIN_DIR . 'debug.txt';
        
        if (!file_exists($log_file)) {
            wp_send_json_success(array(
                'contents' => 'No log file exists yet. Enable debug logging and trigger a sync to start logging.',
                'size' => '0 bytes'
            ));
            return;
        }
        
        $contents = file_get_contents($log_file);
        $size = filesize($log_file);
        $formatted_size = $this->format_file_size($size);
        
        wp_send_json_success(array(
            'contents' => $contents,
            'size' => $formatted_size
        ));
    }
    
    /**
     * AJAX handler to clear debug log
     */
    public function ajax_clear_log() {
        check_ajax_referer('amelia_cpt_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $log_file = AMELIA_CPT_SYNC_PLUGIN_DIR . 'debug.txt';
        
        if (file_exists($log_file)) {
            file_put_contents($log_file, '');
            wp_send_json_success(array('message' => 'Debug log cleared successfully!'));
        } else {
            wp_send_json_error(array('message' => 'No log file to clear'));
        }
    }
    
    /**
     * Format file size for display
     */
    private function format_file_size($bytes) {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }
    
    /**
     * AJAX handler to save custom field definitions
     */
    public function ajax_save_custom_fields_defs() {
        check_ajax_referer('amelia_cpt_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $definitions = isset($_POST['definitions']) ? $_POST['definitions'] : array();
        
        amelia_cpt_sync_debug_log('Saving custom field definitions: ' . print_r($definitions, true));
        
        $manager = new Amelia_CPT_Sync_Custom_Fields_Manager();
        $result = $manager->save_field_definitions($definitions);
        
        if ($result) {
            wp_send_json_success(array('message' => 'Custom field definitions saved!'));
        } else {
            wp_send_json_error(array('message' => 'Failed to save custom field definitions'));
        }
    }
    
    /**
     * AJAX handler to get custom fields modal HTML
     */
    public function ajax_get_custom_fields_modal() {
        check_ajax_referer('amelia_cpt_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $service_id = isset($_POST['service_id']) ? intval($_POST['service_id']) : 0;
        $service_name = isset($_POST['service_name']) ? sanitize_text_field($_POST['service_name']) : 'Unknown Service';
        
        if (!$service_id) {
            wp_send_json_error(array('message' => 'No service ID provided'));
        }
        
        $manager = new Amelia_CPT_Sync_Custom_Fields_Manager();
        $definitions = $manager->get_field_definitions();
        $values = $manager->get_service_field_values($service_id);
        
        if (empty($definitions)) {
            wp_send_json_error(array(
                'message' => 'No custom fields defined. Please configure custom fields in Amelia to CPT Sync settings first.'
            ));
            return;
        }
        
        // Build modal HTML
        ob_start();
        ?>
        <div class="amelia-custom-fields-form">
            <p><strong>Service:</strong> <?php echo esc_html($service_name); ?> (ID: <?php echo esc_html($service_id); ?>)</p>
            <p class="description">Fill in the custom details for this service. These will be synced to your CPT.</p>
            
            <table class="form-table">
                <?php foreach ($definitions as $def): ?>
                    <tr>
                        <th scope="row">
                            <label for="custom_field_<?php echo esc_attr($def['meta_key']); ?>">
                                <?php echo esc_html($def['field_title']); ?>
                            </label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="custom_field_<?php echo esc_attr($def['meta_key']); ?>" 
                                   name="custom_fields[<?php echo esc_attr($def['meta_key']); ?>]"
                                   class="regular-text"
                                   value="<?php echo esc_attr(isset($values[$def['meta_key']]) ? $values[$def['meta_key']] : ''); ?>"
                                   placeholder="<?php echo esc_attr($def['description']); ?>">
                            <?php if (!empty($def['description'])): ?>
                                <p class="description"><?php echo esc_html($def['description']); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php
        $html = ob_get_clean();
        
        wp_send_json_success(array(
            'html' => $html,
            'service_id' => $service_id,
            'service_name' => $service_name
        ));
    }
    
    /**
     * AJAX handler to save custom field values
     */
    public function ajax_save_custom_field_values() {
        check_ajax_referer('amelia_cpt_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $service_id = isset($_POST['service_id']) ? intval($_POST['service_id']) : 0;
        $values = isset($_POST['custom_fields']) ? $_POST['custom_fields'] : array();
        
        if (!$service_id) {
            wp_send_json_error(array('message' => 'No service ID provided'));
        }
        
        amelia_cpt_sync_debug_log("Saving custom field values for service {$service_id}");
        
        $manager = new Amelia_CPT_Sync_Custom_Fields_Manager();
        $result = $manager->save_service_field_values($service_id, $values);
        
        if ($result) {
            // Trigger a re-sync to update the CPT with custom fields
            amelia_cpt_sync_debug_log("Custom fields saved, triggering re-sync for service {$service_id}");
            
            // Get the full service data from Amelia
            $service_data = $this->get_amelia_service_by_id($service_id);
            
            if ($service_data) {
                $cpt_manager = new Amelia_CPT_Sync_CPT_Manager();
                $cpt_result = $cpt_manager->sync_service($service_data);
                
                if (!is_wp_error($cpt_result)) {
                    amelia_cpt_sync_debug_log("Re-sync successful for service {$service_id}, CPT post: {$cpt_result}");
                }
            }
            
            wp_send_json_success(array('message' => 'Custom field values saved successfully!'));
        } else {
            wp_send_json_error(array('message' => 'Failed to save custom field values'));
        }
    }
    
    /**
     * Get Amelia service data by ID
     */
    private function get_amelia_service_by_id($service_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'amelia_services';
        
        $service = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $service_id
        ), ARRAY_A);
        
        if (!$service) {
            return false;
        }
        
        // Use the same preparation logic as full sync
        return $this->prepare_service_data($service);
    }
    
    /**
     * AJAX handler to get taxonomy custom fields modal HTML
     */
    public function ajax_get_taxonomy_custom_fields_modal() {
        check_ajax_referer('amelia_cpt_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
        $category_name = isset($_POST['category_name']) ? sanitize_text_field($_POST['category_name']) : 'Unknown Category';
        
        if (!$category_id) {
            wp_send_json_error(array('message' => 'No category ID provided'));
        }
        
        $manager = new Amelia_CPT_Sync_Taxonomy_Custom_Fields_Manager();
        $definitions = $manager->get_field_definitions();
        $values = $manager->get_category_field_values($category_id);
        
        if (empty($definitions)) {
            wp_send_json_error(array(
                'message' => 'No custom taxonomy fields defined. Please configure custom taxonomy fields in Amelia to CPT Sync settings first.'
            ));
            return;
        }
        
        // Build modal HTML
        ob_start();
        ?>
        <div class="amelia-taxonomy-custom-fields-form">
            <p><strong>Category:</strong> <?php echo esc_html($category_name); ?> (ID: <?php echo esc_html($category_id); ?>)</p>
            <p class="description">Fill in the custom details for this category. These will be synced to your taxonomy term meta.</p>
            
            <table class="form-table">
                <?php foreach ($definitions as $def): ?>
                    <tr>
                        <th scope="row">
                            <label for="taxonomy_custom_field_<?php echo esc_attr($def['meta_key']); ?>">
                                <?php echo esc_html($def['field_title']); ?>
                            </label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="taxonomy_custom_field_<?php echo esc_attr($def['meta_key']); ?>" 
                                   name="taxonomy_custom_fields[<?php echo esc_attr($def['meta_key']); ?>]"
                                   class="regular-text"
                                   value="<?php echo esc_attr(isset($values[$def['meta_key']]) ? $values[$def['meta_key']] : ''); ?>"
                                   placeholder="<?php echo esc_attr($def['description']); ?>">
                            <?php if (!empty($def['description'])): ?>
                                <p class="description"><?php echo esc_html($def['description']); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php
        $html = ob_get_clean();
        
        wp_send_json_success(array(
            'html' => $html,
            'category_id' => $category_id,
            'category_name' => $category_name
        ));
    }
    
    /**
     * AJAX handler to save taxonomy custom field values
     */
    public function ajax_save_taxonomy_custom_field_values() {
        check_ajax_referer('amelia_cpt_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
        $values = isset($_POST['taxonomy_custom_fields']) ? $_POST['taxonomy_custom_fields'] : array();
        
        if (!$category_id) {
            wp_send_json_error(array('message' => 'No category ID provided'));
        }
        
        amelia_cpt_sync_debug_log("Saving taxonomy custom field values for category {$category_id}");
        
        $manager = new Amelia_CPT_Sync_Taxonomy_Custom_Fields_Manager();
        $result = $manager->save_category_field_values($category_id, $values);
        
        if ($result) {
            amelia_cpt_sync_debug_log("Taxonomy custom fields saved successfully for category {$category_id}");
            wp_send_json_success(array('message' => 'Taxonomy custom field values saved successfully!'));
        } else {
            wp_send_json_error(array('message' => 'Failed to save taxonomy custom field values'));
        }
    }
    
    /**
     * Add custom fields modal HTML to admin footer
     */
    public function add_custom_fields_modal_html() {
        // Check if we're on an Amelia page (broadly)
        $is_amelia_page = false;
        
        if ((isset($_GET['page']) && strpos($_GET['page'], 'wpamelia') !== false) ||
            (isset($_GET['page']) && strpos($_GET['page'], 'amelia') !== false)) {
            $is_amelia_page = true;
        }
        
        if (!$is_amelia_page) {
            return;
        }
        
        amelia_cpt_sync_debug_log('Adding modal HTML to footer on page: ' . (isset($_GET['page']) ? $_GET['page'] : 'unknown'));
        ?>
        <!-- Amelia CPT Sync Custom Fields Modal (Services) -->
        <div id="amelia-cpt-sync-custom-fields-modal" style="display: none;" title="Additional Service Details">
            <div id="amelia-cpt-sync-modal-content">
                <p>Loading...</p>
            </div>
        </div>
        
        <!-- Amelia CPT Sync Custom Fields Modal (Taxonomy/Categories) -->
        <div id="amelia-cpt-sync-taxonomy-custom-fields-modal" style="display: none;" title="Additional Category Details">
            <div id="amelia-cpt-sync-taxonomy-modal-content">
                <p>Loading...</p>
            </div>
        </div>
        
        <script>
            console.log('[Amelia CPT Sync] Modal HTML added to page');
            console.log('[Amelia CPT Sync] Service modal exists:', jQuery('#amelia-cpt-sync-custom-fields-modal').length > 0);
            console.log('[Amelia CPT Sync] Taxonomy modal exists:', jQuery('#amelia-cpt-sync-taxonomy-custom-fields-modal').length > 0);
        </script>
        <?php
    }
    
    /**
     * AJAX handler to save popup configurations
     */
    /**
     * AJAX handler to get fresh popup config for a specific popup ID
     */
    public function ajax_get_popup_config() {
        check_ajax_referer('amelia_popup_nonce', 'nonce');
        
        $popup_id = isset($_POST['popup_id']) ? sanitize_text_field($_POST['popup_id']) : '';
        
        if (empty($popup_id)) {
            wp_send_json_error(array('message' => 'No popup ID provided'));
        }
        
        $config_manager = new Amelia_CPT_Sync_Popup_Config_Manager();
        $configurations = $config_manager->get_configurations();
        
        // Find matching config
        $matched_config = null;
        if (!empty($configurations['configs'])) {
            foreach ($configurations['configs'] as $config_id => $config) {
                if (isset($config['popup_slug']) && $config['popup_slug'] === $popup_id) {
                    $matched_config = $config;
                    break;
                }
                if (isset($config['popup_numeric_id']) && 
                    ($config['popup_numeric_id'] == $popup_id || 
                     ('jet-popup-' . $config['popup_numeric_id']) === $popup_id)) {
                    $matched_config = $config;
                    break;
                }
            }
        }
        
        if ($matched_config) {
            wp_send_json_success(array('config' => $matched_config));
        } else {
            wp_send_json_success(array('config' => null));
        }
    }
    
    public function ajax_save_popup_configs() {
        amelia_cpt_sync_debug_log('========== SAVE POPUP CONFIGS REQUEST ==========');
        amelia_cpt_sync_debug_log('Raw $_POST: ' . print_r($_POST, true));
        
        check_ajax_referer('amelia_popup_save', 'nonce');
        
        if (!current_user_can('manage_options')) {
            amelia_cpt_sync_debug_log('ERROR: Unauthorized user');
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $global = isset($_POST['global']) ? $_POST['global'] : array();
        $configs = isset($_POST['configs']) ? $_POST['configs'] : array();
        
        amelia_cpt_sync_debug_log('Parsed global: ' . print_r($global, true));
        amelia_cpt_sync_debug_log('Parsed configs: ' . print_r($configs, true));
        
        $data = array(
            'global' => array(
                'default_popup_id' => isset($global['default_popup_id']) ? sanitize_title($global['default_popup_id']) : '',
                'debug_enabled' => !empty($global['debug_enabled'])
            ),
            'configs' => array()
        );
        
        // Sanitize configurations
        foreach ($configs as $config_id => $config) {
            $key = sanitize_key($config_id);

            $label = isset($config['label']) ? sanitize_text_field($config['label']) : '';
            $popup_slug = isset($config['popup_slug']) ? sanitize_title($config['popup_slug']) : '';
            $popup_numeric_id = isset($config['popup_numeric_id']) ? absint($config['popup_numeric_id']) : 0;
            $shortcode_template = isset($config['shortcode_template']) ? sanitize_text_field($config['shortcode_template']) : '';
            $notes = isset($config['notes']) ? sanitize_textarea_field($config['notes']) : '';
            
            // Form customization checkboxes - explicitly cast to boolean
            $hide_employees = isset($config['hide_employees']) && $config['hide_employees'] == '1';
            $hide_pricing = isset($config['hide_pricing']) && $config['hide_pricing'] == '1';
            $hide_extras = isset($config['hide_extras']) && $config['hide_extras'] == '1';

            if (!$label && !$popup_slug && !$popup_numeric_id) {
                continue;
            }

            // Attempt to resolve numeric ID if missing but slug provided
            if ($popup_slug && !$popup_numeric_id) {
                $resolved = $this->resolve_popup_identifier($popup_slug);

                if ($resolved && !empty($resolved['numeric_id'])) {
                    $popup_numeric_id = (int) $resolved['numeric_id'];
                }
            }

            $data['configs'][$key] = array(
                'label' => $label,
                'popup_slug' => $popup_slug,
                'popup_numeric_id' => $popup_numeric_id,
                'hide_employees' => $hide_employees,
                'hide_pricing' => $hide_pricing,
                'hide_extras' => $hide_extras,
            );
            
            amelia_cpt_sync_debug_log("Config {$key} customizations: employees=" . ($hide_employees ? 'true' : 'false') . ", pricing=" . ($hide_pricing ? 'true' : 'false') . ", extras=" . ($hide_extras ? 'true' : 'false'));

            if ($shortcode_template) {
                $data['configs'][$key]['shortcode_template'] = $shortcode_template;
            }

            if ($notes) {
                $data['configs'][$key]['notes'] = $notes;
            }

            // Back-compat field for existing code paths
            if ($popup_slug) {
                $data['configs'][$key]['popup_id'] = $popup_slug;
            }
        }
        
        amelia_cpt_sync_debug_log('Sanitized data to save: ' . print_r($data, true));
        
        $manager = new Amelia_CPT_Sync_Popup_Config_Manager();
        $result = $manager->save_configurations($data);
        
        if ($result) {
            amelia_cpt_sync_debug_log('SUCCESS: Popup configurations saved');
            wp_send_json_success(array('message' => 'Popup configurations saved successfully!'));
        } else {
            amelia_cpt_sync_debug_log('ERROR: Failed to save popup configurations');
            wp_send_json_error(array('message' => 'Failed to save configurations'));
        }
    }
    
    /**
     * AJAX handler to log debug message from JavaScript
     */
    public function ajax_log_debug() {
        check_ajax_referer('amelia_cpt_sync_nonce', 'nonce');
        
        $message = isset($_POST['message']) ? sanitize_text_field($_POST['message']) : '';
        
        if ($message) {
            amelia_cpt_sync_debug_log('[JS] ' . $message);
        }
        
        wp_send_json_success();
    }
    
    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        // This is handled by AJAX, but keep for compatibility
        return $input;
    }

    /**
     * AJAX handler to resolve JetPopup slug to numeric ID
     */
    public function ajax_resolve_popup_slug() {
        check_ajax_referer('amelia_popup_resolve', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'amelia-cpt-sync')));
        }

        $slug = isset($_POST['slug']) ? sanitize_text_field(wp_unslash($_POST['slug'])) : '';

        if (empty($slug)) {
            wp_send_json_error(array('message' => __('No slug provided.', 'amelia-cpt-sync')));
        }

        $result = $this->resolve_popup_identifier($slug);

        if (!$result) {
            wp_send_json_error(array('message' => __('Popup not found.', 'amelia-cpt-sync')));
        }

        wp_send_json_success($result);
    }

    /**
     * Resolve slug or identifier to popup data
     */
    private function resolve_popup_identifier($identifier) {
        $identifier = trim($identifier);

        if (empty($identifier)) {
            return false;
        }

        $post = null;

        // If prefixed (jet-popup-123), strip prefix
        if (stripos($identifier, 'jet-popup-') === 0) {
            $potential_id = intval(substr($identifier, strlen('jet-popup-')));
            if ($potential_id) {
                $post = get_post($potential_id);
            }
        }

        // Numeric identifier
        if (!$post && is_numeric($identifier)) {
            $post = get_post(absint($identifier));
        }

        // Slug lookup
        if (!$post) {
            $post = get_page_by_path($identifier, OBJECT, 'jet-popup');
        }

        if (!$post || 'jet-popup' !== $post->post_type) {
            return false;
        }

        return array(
            'numeric_id' => (int) $post->ID,
            'prefixed_id' => 'jet-popup-' . $post->ID,
            'slug' => $post->post_name,
            'title' => get_the_title($post)
        );
    }
}


