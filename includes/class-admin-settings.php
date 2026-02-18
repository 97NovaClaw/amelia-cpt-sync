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
        $is_new_service = isset($_POST['is_new_service']) && $_POST['is_new_service'] === '1';
        
        if (!$service_id) {
            wp_send_json_error(array('message' => 'No service ID provided'));
        }
        
        $manager = new Amelia_CPT_Sync_Custom_Fields_Manager();
        $definitions = $manager->get_field_definitions();
        $values = $manager->get_service_field_values($service_id);
        
        // Get GLOBAL resource settings (used as default for new services)
        $global_settings = get_option('art_resource_settings', array());
        $global_mode = $global_settings['default_mode'] ?? 'none';
        $resource_enabled = !empty($global_settings['enabled']);
        
        // Get per-service configuration for quantity and linked resource
        $resource_manager = new ART_Resource_Manager();
        $resource_config = $resource_manager->get_service_config($service_id);
        $mode_settings = $resource_config && $resource_config->mode_settings ? $resource_config->mode_settings : array();
        $quantity_required = $mode_settings['quantity_required'] ?? 1;
        $mirrored_resource_id = $mode_settings['mirrored_resource_id'] ?? null;
        
        // PER-SERVICE MODE: Read from saved config, fall back to global default
        $effective_mode = ($resource_config && $resource_config->resource_mode) 
            ? $resource_config->resource_mode 
            : $global_mode;
        
        // Get linked resource details (for mirrored mode)
        $linked_resource = null;
        if ($mirrored_resource_id) {
            $linked_resource = $resource_manager->get_resource($mirrored_resource_id);
            if (is_wp_error($linked_resource)) {
                $linked_resource = null;
            }
        }
        
        // Get all resources linked to this service from Amelia
        $all_service_resources = $resource_manager->get_all_service_resources($service_id);
        
        // Fetch ALL resources (needed for both mirrored dropdown and pool checkboxes)
        $data_manager = ART_Amelia_Data_Manager::get_instance();
        $all_resources_raw = $data_manager->get_all_resources();
        
        // Mirrored mode needs filtered list (1:1 enforcement)
        $mirrored_resources = array();
        foreach ($all_resources_raw as $resource) {
            $resource_entities = $resource['entities'] ?? [];
            $has_other_service = false;
            
            foreach ($resource_entities as $entity) {
                $entity_type = $entity['entity_type'] ?? $entity['entityType'] ?? null;
                $entity_id = $entity['entity_id'] ?? $entity['entityId'] ?? null;
                
                if ($entity_type === 'service' && $entity_id != $service_id) {
                    $has_other_service = true;
                    break;
                }
            }
            
            if (!$has_other_service) {
                $mirrored_resources[] = $resource;
            }
        }
        
        // Pool mode uses all resources (sharing allowed)
        $all_resources = $all_resources_raw;
        
        // Show modal if custom fields exist OR resource management is enabled
        $has_content = !empty($definitions) || $resource_enabled;
        
        if (!$has_content) {
            wp_send_json_error(array(
                'message' => 'No configuration needed for this service.'
            ));
            return;
        }
        
        // Build modal HTML
        ob_start();
        ?>
        <div class="amelia-custom-fields-form">
            <p><strong>Service:</strong> <?php echo esc_html($service_name); ?> (ID: <?php echo esc_html($service_id); ?>)</p>
            <p class="description">Fill in the custom details for this service. These will be synced to your CPT.</p>
            
            <!-- RESOURCE CONFIGURATION SECTION (Per-Service Mode) -->
            <?php if ($resource_enabled): ?>
            
            <!-- Mode Selector Dropdown -->
            <div class="resource-mode-selector" style="margin: 20px 0 16px 0;">
                <label for="art-resource-mode" style="display: block; font-weight: 600; margin-bottom: 6px;">
                    <?php _e('Resource Mode:', 'amelia-cpt-sync'); ?>
                </label>
                <select id="art-resource-mode" name="resource_config[mode]" class="regular-text" style="width: 100%; max-width: 400px;">
                    <option value="none" <?php selected($effective_mode, 'none'); ?>>
                        <?php _e('No Resources — This service doesn\'t need any tracked items', 'amelia-cpt-sync'); ?>
                    </option>
                    <option value="mirrored" <?php selected($effective_mode, 'mirrored'); ?>>
                        <?php _e('Dedicated Resource — This service always uses one specific item', 'amelia-cpt-sync'); ?>
                    </option>
                    <option value="shared_pool" <?php selected($effective_mode, 'shared_pool'); ?>>
                        <?php _e('Resource Pool — Pick one available item from a group', 'amelia-cpt-sync'); ?>
                    </option>
                    <option value="composite" <?php selected($effective_mode, 'composite'); ?>>
                        <?php _e('Multi Resource — Needs several different items at once', 'amelia-cpt-sync'); ?>
                    </option>
                </select>
            </div>
            
            <!-- Transition Banner (populated by JavaScript on mode change) -->
            <div id="mode-transition-banner" style="display: none;"></div>
            
            <!-- MODE: None -->
            <div class="mode-section" data-mode="none" style="display: none;">
                <div style="padding: 20px; background: #F8FAFC; border: 1px dashed #CBD5E1; border-radius: 8px; text-align: center; color: #64748B;">
                    <span class="dashicons dashicons-info" style="font-size: 24px; margin-bottom: 8px; display: block;"></span>
                    <?php _e('No resource tracking for this service. Availability will be checked by provider schedule only.', 'amelia-cpt-sync'); ?>
                </div>
            </div>
            
            <!-- MODE: Dedicated Resource (Mirrored) -->
            <div class="mode-section" data-mode="mirrored" style="display: none;">
            <h4 style="margin: 10px 0 10px 0;">🔗 <?php _e('Dedicated Resource', 'amelia-cpt-sync'); ?></h4>
            
            <div id="resource-modal-container" 
                 data-service-id="<?php echo esc_attr($service_id); ?>"
                 data-service-name="<?php echo esc_attr($service_name); ?>"
                 data-linked-resource-id="<?php echo esc_attr($mirrored_resource_id ?? ''); ?>"
                 data-is-auto-created="<?php echo esc_attr($mode_settings['auto_created'] ?? 'false'); ?>">
                
                <!-- DEFAULT STATE: Shows current linked resource -->
                <div id="resource-default-state">
                    <?php if ($linked_resource): ?>
                    <!-- Current Resource - Repeater-Style Table -->
                    <div class="resource-edit-section">
                        <h4><?php _e('Linked Resource', 'amelia-cpt-sync'); ?></h4>
                        <table class="pool-resource-repeater-table" style="width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #E0E5F1; border-radius: 6px; overflow: hidden;">
                            <thead>
                                <tr style="background: #F8FAFC; border-bottom: 2px solid #E0E5F1;">
                                    <th style="padding: 10px 12px; text-align: left; font-size: 11px; font-weight: 600; color: #64748B; text-transform: uppercase; width: 65%;">
                                        <?php _e('Name', 'amelia-cpt-sync'); ?>
                                    </th>
                                    <th style="padding: 10px 12px; text-align: left; font-size: 11px; font-weight: 600; color: #64748B; text-transform: uppercase; width: 35%;">
                                        <?php _e('Quantity', 'amelia-cpt-sync'); ?>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td style="padding: 10px 12px;">
                                        <input type="text" 
                                               id="resource_name" 
                                               name="resource_config[resource_name]"
                                               value="<?php echo esc_attr($linked_resource['name']); ?>"
                                               style="width: 100%; padding: 6px 10px; border: 1px solid #E0E5F1; border-radius: 4px; font-size: 13px;">
                                    </td>
                                    <td style="padding: 10px 12px;">
                                        <input type="number" 
                                               id="resource_quantity" 
                                               name="resource_config[resource_quantity]"
                                               min="1"
                                               value="<?php echo esc_attr($linked_resource['quantity']); ?>"
                                               style="width: 60px; padding: 6px 10px; border: 1px solid #E0E5F1; border-radius: 4px; font-size: 13px; text-align: center;">
                                        <span style="margin-left: 4px; color: #64748B; font-size: 12px;"><?php _e('units', 'amelia-cpt-sync'); ?></span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php else: ?>
                    <!-- No Resource State - Repeater-Style New Row -->
                    <div class="resource-edit-section">
                        <h4><?php _e('New Resource', 'amelia-cpt-sync'); ?></h4>
                        <p class="description" style="margin-bottom: 8px;"><?php _e('A new resource will be created when you save.', 'amelia-cpt-sync'); ?></p>
                        <table class="pool-resource-repeater-table" style="width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #E0E5F1; border-radius: 6px; overflow: hidden;">
                            <thead>
                                <tr style="background: #F8FAFC; border-bottom: 2px solid #E0E5F1;">
                                    <th style="padding: 10px 12px; text-align: left; font-size: 11px; font-weight: 600; color: #64748B; text-transform: uppercase; width: 65%;">
                                        <?php _e('Name', 'amelia-cpt-sync'); ?>
                                    </th>
                                    <th style="padding: 10px 12px; text-align: left; font-size: 11px; font-weight: 600; color: #64748B; text-transform: uppercase; width: 35%;">
                                        <?php _e('Quantity', 'amelia-cpt-sync'); ?>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td style="padding: 10px 12px;">
                                        <input type="text" 
                                               id="resource_name" 
                                               name="resource_config[resource_name]"
                                               value="<?php echo esc_attr($service_name . ' (Resource)'); ?>"
                                               style="width: 100%; padding: 6px 10px; border: 1px solid #E0E5F1; border-radius: 4px; font-size: 13px;">
                                    </td>
                                    <td style="padding: 10px 12px;">
                                        <input type="number" 
                                               id="resource_quantity" 
                                               name="resource_config[resource_quantity]"
                                               min="1"
                                               value="1"
                                               style="width: 60px; padding: 6px 10px; border: 1px solid #E0E5F1; border-radius: 4px; font-size: 13px; text-align: center;">
                                        <span style="margin-left: 4px; color: #64748B; font-size: 12px;"><?php _e('units', 'amelia-cpt-sync'); ?></span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Switch Resource Button -->
                    <button type="button" 
                            id="resource-switch-trigger" 
                            class="resource-switch-trigger">
                        <span class="dashicons dashicons-update"></span>
                        <?php _e('Switch to different resource', 'amelia-cpt-sync'); ?>
                    </button>
                </div>
                
                <!-- Transition: Select from all resources (OUTSIDE resource-default-state so it's visible when parent is hidden) -->
                <div id="dedicated-resource-picker" style="display: none; margin-top: 16px;">
                    <div class="resource-edit-section">
                        <h4><?php _e('Select Dedicated Resource', 'amelia-cpt-sync'); ?></h4>
                        <p class="description" style="margin-bottom: 8px;"><?php _e('Choose which resource to keep as the dedicated item for this service.', 'amelia-cpt-sync'); ?></p>
                        <div class="resource-pool-list" style="max-height: 250px; overflow-y: auto; border: 1px solid #E0E5F1; border-radius: 6px; background: #fff;">
                            <?php foreach ($all_resources_raw as $res): ?>
                            <div class="resource-pool-item">
                                <label>
                                    <input type="radio" 
                                           name="resource_config[transition_resource_id]" 
                                           value="<?php echo esc_attr($res['id']); ?>"
                                           data-name="<?php echo esc_attr($res['name']); ?>"
                                           data-quantity="<?php echo esc_attr($res['quantity']); ?>"
                                           style="margin: 0;">
                                    <span class="pool-item-name"><?php echo esc_html($res['name']); ?></span>
                                    <span class="units-badge"><?php echo esc_html($res['quantity']); ?> <?php echo $res['quantity'] > 1 ? 'units' : 'unit'; ?></span>
                                    <?php 
                                    // Show linked services
                                    $radio_linked = array();
                                    if (!empty($res['entities'])) {
                                        foreach ($res['entities'] as $entity) {
                                            $etype = $entity['entity_type'] ?? $entity['entityType'];
                                            $eid = $entity['entity_id'] ?? $entity['entityId'];
                                            if ($etype === 'service' && $eid != $service_id) {
                                                $sdata = $this->get_amelia_service_by_id($eid);
                                                if ($sdata) $radio_linked[] = $sdata['name'];
                                            }
                                        }
                                    }
                                    if (!empty($radio_linked)): ?>
                                        <span class="linked-badge" title="Also used by: <?php echo esc_attr(implode(', ', $radio_linked)); ?>">
                                            🔗 <?php echo count($radio_linked); ?> service<?php echo count($radio_linked) > 1 ? 's' : ''; ?>
                                        </span>
                                    <?php endif; ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- SELECT STATE: Shows resource picker (Appendix K.4.2) -->
                <div id="resource-select-state" style="display: none;">
                    <!-- Warning Banner -->
                    <div class="resource-switch-warning">
                        ⚠️ <?php _e('SWITCHING RESOURCE', 'amelia-cpt-sync'); ?>
                        <br>
                        <strong><?php _e('Current:', 'amelia-cpt-sync'); ?></strong>
                        <span id="current-resource-name"><?php echo esc_html($linked_resource['name'] ?? __('None', 'amelia-cpt-sync')); ?></span>
                        → <?php _e('will be UNLINKED from this service', 'amelia-cpt-sync'); ?>
                    </div>
                    
                    <!-- Resource Selection Dropdown -->
                    <div style="margin-bottom: 16px;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 500;"><?php _e('Select new resource:', 'amelia-cpt-sync'); ?></label>
                        <select id="resource_select" 
                                name="resource_config[resource_id]" 
                                class="regular-text"
                                style="width: 100%;">
                            <option value=""><?php _e('— Choose Resource —', 'amelia-cpt-sync'); ?></option>
                            <optgroup label="<?php _e('EXISTING RESOURCES', 'amelia-cpt-sync'); ?>">
                                <?php foreach ($mirrored_resources as $res): ?>
                                    <?php if ($res['id'] != $mirrored_resource_id): // Don't show current ?>
                                        <option value="<?php echo esc_attr($res['id']); ?>" 
                                                data-name="<?php echo esc_attr($res['name']); ?>"
                                                data-quantity="<?php echo esc_attr($res['quantity']); ?>"
                                                data-linked-to="<?php echo esc_attr(implode(', ', array_column($res['entities'] ?? [], 'entity_id'))); ?>">
                                            <?php echo esc_html($res['name']); ?> (<?php echo esc_html($res['quantity']); ?> <?php echo $res['quantity'] > 1 ? __('units', 'amelia-cpt-sync') : __('unit', 'amelia-cpt-sync'); ?>)
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </optgroup>
                            <optgroup label="<?php _e('CREATE NEW', 'amelia-cpt-sync'); ?>">
                                <option value="new">✨ <?php _e('Create new resource for this service', 'amelia-cpt-sync'); ?></option>
                            </optgroup>
                        </select>
                    </div>
                    
                    <!-- Cancel Button -->
                    <button type="button" 
                            id="resource-switch-cancel" 
                            class="resource-switch-cancel">
                        <?php _e('Cancel - Keep Current', 'amelia-cpt-sync'); ?>
                    </button>
                </div>
                
                <!-- Hidden fields for state tracking -->
                <input type="hidden" id="resource_action" name="resource_config[action]" value="update">
                <input type="hidden" id="original_resource_id" name="resource_config[original_resource_id]" value="<?php echo esc_attr($mirrored_resource_id ?? ''); ?>">
                <input type="hidden" id="selected_resource_id" name="resource_config[resource_id]" value="<?php echo esc_attr($mirrored_resource_id ?? ''); ?>">
            </div>
            
            
            <!-- Resource Modal CSS (Appendix K.7) -->
            <style>
            /* Resource Modal Styles - Matching Plugin Design System */
            
            /* Resource Hero Card (current linked resource) */
            .resource-current-card {
                background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%);
                border: 2px solid #1A84EE;
                border-radius: 8px;
                padding: 16px;
                margin-bottom: 16px;
                display: flex;
                align-items: flex-start;
                gap: 12px;
            }
            
            .resource-current-card .resource-icon {
                font-size: 24px;
                line-height: 1;
            }
            
            .resource-current-card .resource-info {
                flex: 1;
            }
            
            .resource-current-card .resource-name {
                font-size: 16px;
                font-weight: 600;
                color: #1E293B;
                margin-bottom: 4px;
            }
            
            .resource-current-card .resource-meta {
                font-size: 13px;
                color: #64748B;
            }
            
            /* Empty state card */
            .resource-empty-card {
                background: #F8FAFC;
                border: 2px dashed #CBD5E1;
                border-radius: 8px;
                padding: 20px;
                text-align: center;
                margin-bottom: 16px;
            }
            
            .resource-empty-card .empty-icon {
                font-size: 32px;
                line-height: 1;
                margin-bottom: 8px;
            }
            
            .resource-empty-card .resource-name {
                font-size: 16px;
                font-weight: 600;
                color: #1E293B;
                margin-bottom: 4px;
            }
            
            .resource-empty-card .resource-meta {
                font-size: 13px;
                color: #64748B;
            }
            
            /* Edit form section */
            .resource-edit-section {
                background: #F8FAFC;
                border: 1px solid #E0E5F1;
                border-radius: 6px;
                padding: 16px;
                margin-bottom: 16px;
            }
            
            .resource-edit-section h4 {
                margin: 0 0 12px 0;
                font-size: 14px;
                color: #64748B;
                font-weight: 500;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            
            /* Switch trigger button */
            .resource-switch-trigger {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                width: 100%;
                padding: 12px;
                background: #fff;
                border: 1px solid #E0E5F1;
                border-radius: 6px;
                color: #64748B;
                font-size: 14px;
                cursor: pointer;
                transition: all 0.2s;
            }
            
            .resource-switch-trigger:hover {
                border-color: #1A84EE;
                color: #1A84EE;
                background: #F8FAFC;
            }
            
            .resource-switch-trigger .dashicons {
                width: 18px;
                height: 18px;
                font-size: 18px;
            }
            
            /* Switch mode warning banner */
            .resource-switch-warning {
                background: #FEF3C7;
                border: 1px solid #F59E0B;
                border-radius: 6px;
                padding: 12px;
                margin-bottom: 16px;
                font-size: 13px;
                color: #92400E;
                line-height: 1.6;
            }
            
            .resource-switch-warning strong {
                color: #92400E;
            }
            
            /* Cancel button in switch mode */
            .resource-switch-cancel {
                display: block;
                width: 100%;
                padding: 10px;
                background: none;
                border: 1px solid #E0E5F1;
                border-radius: 6px;
                color: #64748B;
                cursor: pointer;
                transition: all 0.2s;
                font-size: 14px;
            }
            
            .resource-switch-cancel:hover {
                background: #F8FAFC;
                border-color: #94A3B8;
            }
            
            /* Select2 custom styling */
            #resource_select {
                width: 100% !important;
            }
            
            .select2-container--default .select2-results__group {
                font-weight: 600;
                color: #64748B;
                font-size: 11px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                padding: 8px 12px 4px;
                background: #F8FAFC;
                border-bottom: 1px solid #E0E5F1;
            }
            </style>
            
            <!-- Resource Modal JavaScript State Machine (Appendix K.9) -->
            <script>
            jQuery(document).ready(function($) {
                // Resource Modal State Machine
                var resourceModalState = {
                    currentState: 'default',  // 'default' or 'selecting'
                    isNewService: <?php echo $is_new_service ? 'true' : 'false'; ?>,
                    linkedResource: {
                        id: <?php echo $linked_resource ? intval($linked_resource['id']) : 'null'; ?>,
                        name: '<?php echo esc_js($linked_resource['name'] ?? ''); ?>',
                        quantity: <?php echo $linked_resource ? intval($linked_resource['quantity']) : 1; ?>,
                        isAutoCreated: <?php echo ($mode_settings['auto_created'] ?? false) ? 'true' : 'false'; ?>
                    },
                    originalResource: null,
                    pendingAction: 'update',  // 'update', 'switch', 'create'
                    
                    init: function() {
                        this.originalResource = {...this.linkedResource};
                        this.bindEvents();
                        this.initSelect2();
                    },
                    
                    bindEvents: function() {
                        var self = this;
                        
                        // Switch to select mode
                        $('#resource-switch-trigger').on('click', function() {
                            self.enterSelectMode();
                        });
                        
                        // Cancel select mode
                        $('#resource-switch-cancel').on('click', function() {
                            self.cancelSelect();
                        });
                        
                        // Resource selected
                        $('#resource_select').on('change', function() {
                            var selectedId = $(this).val();
                            var selected = $(this).find(':selected');
                            
                            if (selectedId === 'new') {
                                self.createNew();
                            } else if (selectedId) {
                                self.selectResource(selectedId, selected);
                            }
                        });
                        
                        // Track name/quantity changes
                        $('#resource_name, #resource_quantity').on('change', function() {
                            if (self.pendingAction === 'update') {
                                // Still updating, just track the change
                            }
                        });
                    },
                    
                    initSelect2: function() {
                        if ($.fn.select2) {
                            $('#resource_select').select2({
                                width: '100%',
                                dropdownParent: $('#amelia-cpt-sync-custom-fields-modal'),
                                placeholder: '<?php _e('Choose a resource...', 'amelia-cpt-sync'); ?>'
                            });
                        }
                    },
                    
                    enterSelectMode: function() {
                        console.log('[Resource Modal] Entering select mode');
                        this.currentState = 'selecting';
                        this.render();
                    },
                    
                    cancelSelect: function() {
                        console.log('[Resource Modal] Cancelling select - returning to default');
                        this.currentState = 'default';
                        this.pendingAction = 'update';
                        $('#resource_select').val('').trigger('change');
                        this.render();
                    },
                    
                    selectResource: function(resourceId, optionEl) {
                        console.log('[Resource Modal] Resource selected:', resourceId);
                        
                        this.pendingAction = 'switch';
                        this.linkedResource = {
                            id: parseInt(resourceId),
                            name: optionEl.data('name') || optionEl.text().split(' (')[0],
                            quantity: parseInt(optionEl.data('quantity')) || 1,
                            isAutoCreated: false
                        };
                        
                        // Update form fields
                        $('#resource_name').val(this.linkedResource.name);
                        $('#resource_quantity').val(this.linkedResource.quantity);
                        $('#selected_resource_id').val(resourceId);
                        $('#resource_action').val('switch');
                        
                        // Return to default state
                        this.currentState = 'default';
                        this.render();
                    },
                    
                    createNew: function() {
                        console.log('[Resource Modal] Creating new resource');
                        
                        this.pendingAction = 'create';
                        var newName = '<?php echo esc_js($service_name); ?> (Resource)';
                        
                        this.linkedResource = {
                            id: null,
                            name: newName,
                            quantity: 1,
                            isAutoCreated: false
                        };
                        
                        // Update form fields
                        $('#resource_name').val(newName);
                        $('#resource_quantity').val(1);
                        $('#selected_resource_id').val('new');
                        $('#resource_action').val('create');
                        
                        // Return to default state
                        this.currentState = 'default';
                        this.render();
                    },
                    
                    render: function() {
                        if (this.currentState === 'selecting') {
                            $('#resource-default-state').hide();
                            $('#resource-select-state').show();
                            $('#current-resource-name').text(this.originalResource.name || '<?php _e('None', 'amelia-cpt-sync'); ?>');
                        } else {
                            $('#resource-default-state').show();
                            $('#resource-select-state').hide();
                        }
                    },
                    
                    getData: function() {
                        // Returns data for AJAX save
                        return {
                            action: this.pendingAction,
                            resource_id: $('#selected_resource_id').val(),
                            resource_name: $('#resource_name').val(),
                            resource_quantity: parseInt($('#resource_quantity').val()) || 1,
                            original_resource_id: this.originalResource.id,
                            cleanup_orphan: this.originalResource.isAutoCreated && this.pendingAction === 'switch'
                        };
                    }
                };
                
                // Initialize state machine
                resourceModalState.init();
                
                // Expose to global scope for save handler
                window.resourceModalState = resourceModalState;
            });
            </script>
            
            </div><!-- /.mode-section[data-mode="mirrored"] -->
            
            <!-- MODE: Resource Pool (Shared Pool) -->
            <div class="mode-section" data-mode="shared_pool" style="display: none;">
            <h4 style="margin: 10px 0 10px 0;">🏊 <?php _e('Resource Pool', 'amelia-cpt-sync'); ?></h4>
            
            <?php
            // Get current pool configuration
            $pool_resource_ids = $mode_settings['pool_resource_ids'] ?? array();
            $selection_strategy = $mode_settings['selection_strategy'] ?? 'first_available';
            $quantity_per_booking = $mode_settings['quantity_per_booking'] ?? 1;
            ?>
            
            <div class="resource-pool-config">
                <p class="description">
                    <?php _e('Select resources that this service can use. The system will automatically assign an available resource based on your strategy.', 'amelia-cpt-sync'); ?>
                </p>
                
                <!-- Pool Resource Selection -->
                <div class="resource-pool-selection">
                    <label style="font-weight: 600; margin-bottom: 8px; display: block;">
                        <?php _e('Pool Resources:', 'amelia-cpt-sync'); ?>
                    </label>
                    
                    <div class="resource-pool-list">
                        <?php if (!empty($all_resources)): ?>
                            <?php foreach ($all_resources as $res): ?>
                                <?php 
                                $is_in_pool = in_array($res['id'], $pool_resource_ids);
                                
                                // Get services linked to this resource for display
                                $linked_services = array();
                                if (!empty($res['entities'])) {
                                    foreach ($res['entities'] as $entity) {
                                        $entity_type = $entity['entity_type'] ?? $entity['entityType'];
                                        $entity_id = $entity['entity_id'] ?? $entity['entityId'];
                                        
                                        if ($entity_type === 'service' && $entity_id !== $service_id) {
                                            // Get service name
                                            $linked_service_data = $this->get_amelia_service_by_id($entity_id);
                                            if ($linked_service_data) {
                                                $linked_services[] = $linked_service_data['name'];
                                            }
                                        }
                                    }
                                }
                                ?>
                                <div class="resource-pool-item">
                                    <label>
                                        <input type="checkbox" 
                                               name="resource_config[pool_resources][]" 
                                               value="<?php echo esc_attr($res['id']); ?>"
                                               <?php checked($is_in_pool); ?>>
                                        <span class="pool-item-name"><?php echo esc_html($res['name']); ?></span>
                                        <span class="units-badge"><?php echo esc_html($res['quantity']); ?> units</span>
                                        <?php if (!empty($linked_services)): ?>
                                            <span class="linked-badge" title="Also used by: <?php echo esc_attr(implode(', ', $linked_services)); ?>">
                                                🔗 <?php echo count($linked_services); ?> service<?php echo count($linked_services) > 1 ? 's' : ''; ?>
                                            </span>
                                        <?php endif; ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="description"><?php _e('No resources available. Create resources in Amelia first.', 'amelia-cpt-sync'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- CREATE NEW RESOURCES Section -->
                <div class="resource-pool-create-section" style="margin-top: 24px;">
                    <div class="section-divider" style="display: flex; align-items: center; margin: 20px 0; color: #64748B; font-size: 12px; font-weight: 600; text-transform: uppercase;">
                        <span style="flex: 1; height: 1px; background: #E0E5F1;"></span>
                        <span style="padding: 0 12px;"><?php _e('Create New Resources', 'amelia-cpt-sync'); ?></span>
                        <span style="flex: 1; height: 1px; background: #E0E5F1;"></span>
                    </div>
                    
                    <button type="button" 
                            id="add-new-pool-resource-trigger" 
                            class="button button-secondary"
                            style="margin-bottom: 12px;">
                        <span class="dashicons dashicons-plus-alt" style="margin-top: 3px;"></span>
                        <?php _e('Add New Resource to Pool', 'amelia-cpt-sync'); ?>
                    </button>
                    
                    <!-- Repeater Table (hidden initially) -->
                    <div id="pool-resource-repeater" style="display: none; margin-top: 12px;">
                        <table class="pool-resource-repeater-table" style="width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #E0E5F1; border-radius: 6px; overflow: hidden;">
                            <thead>
                                <tr style="background: #F8FAFC; border-bottom: 2px solid #E0E5F1;">
                                    <th style="padding: 10px 12px; text-align: left; font-size: 11px; font-weight: 600; color: #64748B; text-transform: uppercase; width: 60%;">
                                        <?php _e('Name', 'amelia-cpt-sync'); ?>
                                    </th>
                                    <th style="padding: 10px 12px; text-align: left; font-size: 11px; font-weight: 600; color: #64748B; text-transform: uppercase; width: 25%;">
                                        <?php _e('Quantity', 'amelia-cpt-sync'); ?>
                                    </th>
                                    <th style="padding: 10px 12px; text-align: center; font-size: 11px; font-weight: 600; color: #64748B; text-transform: uppercase; width: 15%;">
                                        <?php _e('Action', 'amelia-cpt-sync'); ?>
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="pool-resource-repeater-body">
                                <!-- Rows will be added here via JavaScript -->
                            </tbody>
                        </table>
                        
                        <button type="button" 
                                id="add-another-pool-resource" 
                                class="button button-link"
                                style="margin-top: 8px; color: #1A84EE;">
                            <span class="dashicons dashicons-plus-alt" style="margin-top: 3px;"></span>
                            <?php _e('Add Another Resource', 'amelia-cpt-sync'); ?>
                        </button>
                    </div>
                </div>
                
                <!-- Selection Strategy -->
                <div class="section-divider" style="display: flex; align-items: center; margin: 20px 0; color: #64748B; font-size: 12px; font-weight: 600; text-transform: uppercase;">
                    <span style="flex: 1; height: 1px; background: #E0E5F1;"></span>
                    <span style="padding: 0 12px;"><?php _e('Pool Settings', 'amelia-cpt-sync'); ?></span>
                    <span style="flex: 1; height: 1px; background: #E0E5F1;"></span>
                </div>
                
                <div class="form-row" style="margin-top: 16px;">
                    <label for="selection_strategy" style="font-weight: 600;">
                        <?php _e('Selection Strategy:', 'amelia-cpt-sync'); ?>
                    </label>
                    <select id="selection_strategy" name="resource_config[selection_strategy]" class="regular-text" style="margin-top: 4px;">
                        <option value="first_available" <?php selected($selection_strategy, 'first_available'); ?>>
                            <?php _e('First Available - Use first resource with capacity', 'amelia-cpt-sync'); ?>
                        </option>
                        <option value="least_used" <?php selected($selection_strategy, 'least_used'); ?>>
                            <?php _e('Least Used - Balance load across resources', 'amelia-cpt-sync'); ?>
                        </option>
                        <option value="manual" <?php selected($selection_strategy, 'manual'); ?>>
                            <?php _e('Manual - Admin chooses at booking time', 'amelia-cpt-sync'); ?>
                        </option>
                    </select>
                    <p class="description">
                        <?php _e('How the system selects which resource to use when multiple are available.', 'amelia-cpt-sync'); ?>
                    </p>
                </div>
                
                <!-- Units Per Booking -->
                <div class="form-row" style="margin-top: 16px;">
                    <label for="quantity_per_booking" style="font-weight: 600;">
                        <?php _e('Units Per Booking:', 'amelia-cpt-sync'); ?>
                    </label>
                    <input type="number" 
                           id="quantity_per_booking"
                           name="resource_config[quantity_per_booking]" 
                           value="<?php echo esc_attr($quantity_per_booking); ?>" 
                           min="1" 
                           class="small-text" 
                           style="margin-top: 4px;">
                    <p class="description">
                        <?php _e('How many units does one booking consume? (Usually 1)', 'amelia-cpt-sync'); ?>
                    </p>
                </div>
                
                <!-- Pool Summary -->
                <?php if (!empty($pool_resource_ids)): ?>
                <div class="resource-pool-summary" style="margin-top: 16px; background: #EFF6FF; border: 1px solid #1A84EE; border-radius: 6px; padding: 10px 12px; font-size: 13px; color: #1E40AF;">
                    <strong><?php _e('Pool Summary:', 'amelia-cpt-sync'); ?></strong> 
                    <?php echo count($pool_resource_ids); ?> resource<?php echo count($pool_resource_ids) > 1 ? 's' : ''; ?> selected
                    <?php
                    $total_units = 0;
                    foreach ($all_resources as $res) {
                        if (in_array($res['id'], $pool_resource_ids)) {
                            $total_units += $res['quantity'];
                        }
                    }
                    ?>
                    • <?php echo $total_units; ?> total units available
                </div>
                <?php endif; ?>
            </div>
            
            <style>
            /* Pool-specific styles */
            .resource-pool-list {
                max-height: 300px;
                overflow-y: auto;
                border: 1px solid #E0E5F1;
                border-radius: 6px;
                background: #fff;
            }
            
            .resource-pool-item {
                display: flex;
                align-items: center;
                padding: 10px 12px;
                border-bottom: 1px solid #F1F5F9;
                transition: background 0.2s;
            }
            
            .resource-pool-item:hover {
                background: #F8FAFC;
            }
            
            .resource-pool-item:last-child {
                border-bottom: none;
            }
            
            .resource-pool-item label {
                display: flex;
                align-items: center;
                gap: 8px;
                cursor: pointer;
                width: 100%;
            }
            
            .resource-pool-item input[type="checkbox"] {
                margin: 0;
            }
            
            .pool-item-name {
                flex: 1;
                font-weight: 500;
                color: #1E293B;
            }
            
            .units-badge {
                font-size: 12px;
                color: #64748B;
                background: #F1F5F9;
                padding: 2px 8px;
                border-radius: 10px;
            }
            
            .linked-badge {
                font-size: 11px;
                color: #1E40AF;
                background: #DBEAFE;
                padding: 2px 8px;
                border-radius: 10px;
                cursor: help;
            }
            
            /* Repeater Table Styles (matches pool-list styling) */
            .pool-resource-repeater-table {
                box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            }
            
            .pool-resource-repeater-table tbody tr {
                border-bottom: 1px solid #F1F5F9;
                transition: background 0.2s;
            }
            
            .pool-resource-repeater-table tbody tr:hover {
                background: #F8FAFC;
            }
            
            .pool-resource-repeater-table tbody tr:last-child {
                border-bottom: none;
            }
            
            .pool-resource-repeater-table td {
                padding: 10px 12px;
            }
            
            .pool-resource-repeater-table input[type="text"] {
                width: 100%;
                padding: 6px 10px;
                border: 1px solid #E0E5F1;
                border-radius: 4px;
                font-size: 13px;
            }
            
            .pool-resource-repeater-table input[type="number"] {
                width: 60px;
                padding: 6px 10px;
                border: 1px solid #E0E5F1;
                border-radius: 4px;
                font-size: 13px;
                text-align: center;
            }
            
            .pool-repeater-remove {
                background: none;
                border: none;
                color: #DC2626;
                cursor: pointer;
                padding: 4px 8px;
                border-radius: 4px;
                transition: all 0.2s;
                font-size: 16px;
            }
            
            .pool-repeater-remove:hover {
                background: #FEE2E2;
                color: #991B1B;
            }
            
            .section-divider {
                margin: 24px 0 16px 0;
            }
            </style>
            
            <!-- JavaScript for Pool Resource Repeater -->
            <script>
            jQuery(document).ready(function($) {
                var poolRepeaterState = {
                    rows: [],
                    nextId: 1,
                    serviceName: '<?php echo esc_js($service_name); ?>',
                    
                    init: function() {
                        this.bindEvents();
                    },
                    
                    bindEvents: function() {
                        var self = this;
                        
                        // Show repeater on first click
                        $('#add-new-pool-resource-trigger').on('click', function() {
                            $('#pool-resource-repeater').show();
                            self.addRow();
                            $(this).hide(); // Hide trigger, show "Add Another" instead
                        });
                        
                        // Add another row
                        $('#add-another-pool-resource').on('click', function() {
                            self.addRow();
                        });
                        
                        // Remove row (delegated event)
                        $(document).on('click', '.pool-repeater-remove', function() {
                            var rowId = $(this).data('row-id');
                            self.removeRow(rowId);
                        });
                    },
                    
                    addRow: function() {
                        var rowId = this.nextId++;
                        var defaultName = this.serviceName + ' Resource #' + rowId;
                        
                        var rowHtml = '<tr data-row-id="' + rowId + '">' +
                            '<td>' +
                                '<input type="text" ' +
                                       'name="resource_config[new_pool_resources][' + rowId + '][name]" ' +
                                       'value="' + defaultName + '" ' +
                                       'placeholder="Resource name" ' +
                                       'required>' +
                            '</td>' +
                            '<td>' +
                                '<input type="number" ' +
                                       'name="resource_config[new_pool_resources][' + rowId + '][quantity]" ' +
                                       'value="1" ' +
                                       'min="1" ' +
                                       'required>' +
                            '</td>' +
                            '<td style="text-align: center;">' +
                                '<button type="button" ' +
                                        'class="pool-repeater-remove" ' +
                                        'data-row-id="' + rowId + '" ' +
                                        'title="<?php _e('Remove', 'amelia-cpt-sync'); ?>">' +
                                    '<span class="dashicons dashicons-trash"></span>' +
                                '</button>' +
                            '</td>' +
                        '</tr>';
                        
                        $('#pool-resource-repeater-body').append(rowHtml);
                        this.rows.push(rowId);
                        
                        console.log('[Pool Repeater] Added row #' + rowId);
                    },
                    
                    removeRow: function(rowId) {
                        $('tr[data-row-id="' + rowId + '"]').fadeOut(200, function() {
                            $(this).remove();
                            
                            // Hide repeater if no rows left
                            if ($('#pool-resource-repeater-body tr').length === 0) {
                                $('#pool-resource-repeater').hide();
                                $('#add-new-pool-resource-trigger').show();
                            }
                        });
                        
                        this.rows = this.rows.filter(function(id) { return id !== rowId; });
                        
                        console.log('[Pool Repeater] Removed row #' + rowId);
                    }
                };
                
                // Initialize repeater
                poolRepeaterState.init();
                
                // Expose to global scope
                window.poolRepeaterState = poolRepeaterState;
            });
            </script>
            
            </div><!-- /.mode-section[data-mode="shared_pool"] -->
            
            <!-- MODE: Multi Resource (Composite) -->
            <div class="mode-section" data-mode="composite" style="display: none;">
            <h4 style="margin: 10px 0 10px 0;">📦 <?php _e('Multi Resource Requirements', 'amelia-cpt-sync'); ?></h4>
            
            <?php
            // Get current composite configuration
            $composite_groups = $mode_settings['requirement_groups'] ?? array();
            ?>
            
            <div class="resource-pool-config">
                <p class="description">
                    <?php _e('Define requirement groups. Each group specifies resources that can fill a requirement. A booking requires ALL groups to have availability.', 'amelia-cpt-sync'); ?>
                </p>
                
                <div id="composite-groups-container">
                    <?php if (!empty($composite_groups)): ?>
                        <?php foreach ($composite_groups as $g_idx => $group): ?>
                        <div class="composite-group" data-group-index="<?php echo $g_idx; ?>" style="border: 1px solid #E0E5F1; border-radius: 6px; padding: 16px; margin-bottom: 12px; background: #fff;">
                            <div style="display: flex; gap: 12px; align-items: center; margin-bottom: 12px;">
                                <div style="flex: 1;">
                                    <label style="font-size: 11px; font-weight: 600; color: #64748B; text-transform: uppercase; display: block; margin-bottom: 4px;"><?php _e('Group Name', 'amelia-cpt-sync'); ?></label>
                                    <input type="text" class="composite-group-label regular-text" 
                                           name="resource_config[requirement_groups][<?php echo $g_idx; ?>][label]" 
                                           value="<?php echo esc_attr($group['label'] ?? ''); ?>"
                                           placeholder="<?php _e('e.g. Room, Vehicle, Equipment', 'amelia-cpt-sync'); ?>"
                                           style="width: 100%; padding: 6px 10px; border: 1px solid #E0E5F1; border-radius: 4px; font-size: 13px;">
                                </div>
                                <div>
                                    <label style="font-size: 11px; font-weight: 600; color: #64748B; text-transform: uppercase; display: block; margin-bottom: 4px;"><?php _e('Qty Needed', 'amelia-cpt-sync'); ?></label>
                                    <input type="number" class="composite-group-qty" 
                                           name="resource_config[requirement_groups][<?php echo $g_idx; ?>][quantity_needed]" 
                                           value="<?php echo esc_attr($group['quantity_needed'] ?? 1); ?>"
                                           min="1"
                                           style="width: 60px; padding: 6px 10px; border: 1px solid #E0E5F1; border-radius: 4px; font-size: 13px; text-align: center;">
                                </div>
                                <div style="padding-top: 18px;">
                                    <button type="button" class="composite-group-remove pool-repeater-remove" data-group-index="<?php echo $g_idx; ?>" title="<?php _e('Remove Group', 'amelia-cpt-sync'); ?>">
                                        <span class="dashicons dashicons-trash"></span>
                                    </button>
                                </div>
                            </div>
                            
                            <label style="font-size: 11px; font-weight: 600; color: #64748B; text-transform: uppercase; display: block; margin-bottom: 6px;"><?php _e('Resources that can fill this requirement:', 'amelia-cpt-sync'); ?></label>
                            <div class="resource-pool-list" style="max-height: 200px; overflow-y: auto; border: 1px solid #E0E5F1; border-radius: 6px; background: #fff;">
                                <?php foreach ($all_resources_raw as $res): 
                                    $is_in_group = in_array($res['id'], $group['resource_ids'] ?? array());
                                ?>
                                <div class="resource-pool-item">
                                    <label>
                                        <input type="checkbox" 
                                               name="resource_config[requirement_groups][<?php echo $g_idx; ?>][resource_ids][]" 
                                               value="<?php echo esc_attr($res['id']); ?>"
                                               <?php checked($is_in_group); ?>
                                               style="margin: 0;">
                                        <span class="pool-item-name"><?php echo esc_html($res['name']); ?></span>
                                        <span class="units-badge"><?php echo esc_html($res['quantity']); ?> <?php echo $res['quantity'] > 1 ? 'units' : 'unit'; ?></span>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Per-group: Create New Resource -->
                            <button type="button" class="composite-create-resource-trigger button button-link" data-group-index="<?php echo $g_idx; ?>" style="margin-top: 8px; color: #1A84EE; font-size: 12px;">
                                <span class="dashicons dashicons-plus-alt" style="font-size: 14px; margin-top: 2px;"></span>
                                <?php _e('Create New Resource', 'amelia-cpt-sync'); ?>
                            </button>
                            <div class="composite-new-resource-repeater" data-group-index="<?php echo $g_idx; ?>" style="display: none; margin-top: 8px;">
                                <table class="pool-resource-repeater-table" style="width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #E0E5F1; border-radius: 6px; overflow: hidden;">
                                    <thead>
                                        <tr style="background: #F8FAFC; border-bottom: 2px solid #E0E5F1;">
                                            <th style="padding: 8px 10px; text-align: left; font-size: 10px; font-weight: 600; color: #64748B; text-transform: uppercase; width: 55%;"><?php _e('Name', 'amelia-cpt-sync'); ?></th>
                                            <th style="padding: 8px 10px; text-align: left; font-size: 10px; font-weight: 600; color: #64748B; text-transform: uppercase; width: 25%;"><?php _e('Qty', 'amelia-cpt-sync'); ?></th>
                                            <th style="padding: 8px 10px; text-align: center; font-size: 10px; font-weight: 600; color: #64748B; text-transform: uppercase; width: 20%;"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="composite-new-resource-body" data-group-index="<?php echo $g_idx; ?>">
                                    </tbody>
                                </table>
                                <button type="button" class="composite-add-another-resource button button-link" data-group-index="<?php echo $g_idx; ?>" style="margin-top: 4px; color: #1A84EE; font-size: 11px;">
                                    <span class="dashicons dashicons-plus-alt" style="font-size: 12px; margin-top: 3px;"></span>
                                    <?php _e('Add Another', 'amelia-cpt-sync'); ?>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div id="composite-empty-state" style="padding: 20px; background: #F8FAFC; border: 1px dashed #CBD5E1; border-radius: 8px; text-align: center; color: #64748B; margin-bottom: 12px;">
                            <?php _e('No requirement groups configured yet. Click the button below to add one.', 'amelia-cpt-sync'); ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <button type="button" id="add-composite-group" class="button button-secondary" style="margin-top: 8px;">
                    <span class="dashicons dashicons-plus-alt" style="margin-top: 3px;"></span>
                    <?php _e('Add Requirement Group', 'amelia-cpt-sync'); ?>
                </button>
            </div>
            
            <!-- Composite Group Repeater JavaScript -->
            <script>
            jQuery(document).ready(function($) {
                var compositeGroupManager = {
                    nextGroupIndex: <?php echo count($composite_groups); ?>,
                    
                    // All resources as JSON for cloning checkbox lists into new groups
                    allResources: <?php echo wp_json_encode(array_map(function($res) {
                        return array(
                            'id' => $res['id'],
                            'name' => $res['name'],
                            'quantity' => $res['quantity']
                        );
                    }, $all_resources_raw)); ?>,
                    
                    nextRowId: 1,  // For new resource rows within groups
                    
                    init: function() {
                        var self = this;
                        
                        $('#add-composite-group').on('click', function() {
                            self.addGroup();
                        });
                        
                        $(document).on('click', '.composite-group-remove', function() {
                            var idx = $(this).data('group-index');
                            self.removeGroup(idx);
                        });
                        
                        // Per-group: Create New Resource trigger
                        $(document).on('click', '.composite-create-resource-trigger', function() {
                            var groupIdx = $(this).data('group-index');
                            $(this).hide();
                            var $repeater = $('.composite-new-resource-repeater[data-group-index="' + groupIdx + '"]');
                            $repeater.show();
                            self.addNewResourceRow(groupIdx);
                        });
                        
                        // Per-group: Add Another
                        $(document).on('click', '.composite-add-another-resource', function() {
                            var groupIdx = $(this).data('group-index');
                            self.addNewResourceRow(groupIdx);
                        });
                        
                        // Per-group: Remove new resource row
                        $(document).on('click', '.composite-new-res-remove', function() {
                            var groupIdx = $(this).data('group-index');
                            var rowId = $(this).data('row-id');
                            self.removeNewResourceRow(groupIdx, rowId);
                        });
                    },
                    
                    addGroup: function() {
                        var idx = this.nextGroupIndex++;
                        
                        // Hide empty state
                        $('#composite-empty-state').hide();
                        
                        // Build checkbox list HTML
                        var checkboxHtml = '';
                        this.allResources.forEach(function(res) {
                            checkboxHtml += '<div class="resource-pool-item">' +
                                '<label>' +
                                    '<input type="checkbox" ' +
                                           'name="resource_config[requirement_groups][' + idx + '][resource_ids][]" ' +
                                           'value="' + res.id + '" ' +
                                           'style="margin: 0;"> ' +
                                    '<span class="pool-item-name">' + res.name + '</span> ' +
                                    '<span class="units-badge">' + res.quantity + ' ' + (res.quantity > 1 ? 'units' : 'unit') + '</span>' +
                                '</label>' +
                            '</div>';
                        });
                        
                        var groupHtml = '<div class="composite-group" data-group-index="' + idx + '" style="border: 1px solid #E0E5F1; border-radius: 6px; padding: 16px; margin-bottom: 12px; background: #fff;">' +
                            '<div style="display: flex; gap: 12px; align-items: center; margin-bottom: 12px;">' +
                                '<div style="flex: 1;">' +
                                    '<label style="font-size: 11px; font-weight: 600; color: #64748B; text-transform: uppercase; display: block; margin-bottom: 4px;"><?php _e('Group Name', 'amelia-cpt-sync'); ?></label>' +
                                    '<input type="text" class="composite-group-label regular-text" ' +
                                           'name="resource_config[requirement_groups][' + idx + '][label]" ' +
                                           'value="" ' +
                                           'placeholder="<?php _e('e.g. Room, Vehicle, Equipment', 'amelia-cpt-sync'); ?>" ' +
                                           'style="width: 100%; padding: 6px 10px; border: 1px solid #E0E5F1; border-radius: 4px; font-size: 13px;">' +
                                '</div>' +
                                '<div>' +
                                    '<label style="font-size: 11px; font-weight: 600; color: #64748B; text-transform: uppercase; display: block; margin-bottom: 4px;"><?php _e('Qty Needed', 'amelia-cpt-sync'); ?></label>' +
                                    '<input type="number" class="composite-group-qty" ' +
                                           'name="resource_config[requirement_groups][' + idx + '][quantity_needed]" ' +
                                           'value="1" min="1" ' +
                                           'style="width: 60px; padding: 6px 10px; border: 1px solid #E0E5F1; border-radius: 4px; font-size: 13px; text-align: center;">' +
                                '</div>' +
                                '<div style="padding-top: 18px;">' +
                                    '<button type="button" class="composite-group-remove pool-repeater-remove" data-group-index="' + idx + '" title="<?php _e('Remove Group', 'amelia-cpt-sync'); ?>">' +
                                        '<span class="dashicons dashicons-trash"></span>' +
                                    '</button>' +
                                '</div>' +
                            '</div>' +
                            '<label style="font-size: 11px; font-weight: 600; color: #64748B; text-transform: uppercase; display: block; margin-bottom: 6px;"><?php _e('Resources that can fill this requirement:', 'amelia-cpt-sync'); ?></label>' +
                            '<div class="resource-pool-list" style="max-height: 200px; overflow-y: auto; border: 1px solid #E0E5F1; border-radius: 6px; background: #fff;">' +
                                checkboxHtml +
                            '</div>' +
                            // Per-group: Create New Resource button + repeater
                            '<button type="button" class="composite-create-resource-trigger button button-link" data-group-index="' + idx + '" style="margin-top: 8px; color: #1A84EE; font-size: 12px;">' +
                                '<span class="dashicons dashicons-plus-alt" style="font-size: 14px; margin-top: 2px;"></span> ' +
                                '<?php _e('Create New Resource', 'amelia-cpt-sync'); ?>' +
                            '</button>' +
                            '<div class="composite-new-resource-repeater" data-group-index="' + idx + '" style="display: none; margin-top: 8px;">' +
                                '<table class="pool-resource-repeater-table" style="width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #E0E5F1; border-radius: 6px; overflow: hidden;">' +
                                    '<thead><tr style="background: #F8FAFC; border-bottom: 2px solid #E0E5F1;">' +
                                        '<th style="padding: 8px 10px; text-align: left; font-size: 10px; font-weight: 600; color: #64748B; text-transform: uppercase; width: 55%;"><?php _e('Name', 'amelia-cpt-sync'); ?></th>' +
                                        '<th style="padding: 8px 10px; text-align: left; font-size: 10px; font-weight: 600; color: #64748B; text-transform: uppercase; width: 25%;"><?php _e('Qty', 'amelia-cpt-sync'); ?></th>' +
                                        '<th style="padding: 8px 10px; text-align: center; font-size: 10px; font-weight: 600; color: #64748B; text-transform: uppercase; width: 20%;"></th>' +
                                    '</tr></thead>' +
                                    '<tbody class="composite-new-resource-body" data-group-index="' + idx + '"></tbody>' +
                                '</table>' +
                                '<button type="button" class="composite-add-another-resource button button-link" data-group-index="' + idx + '" style="margin-top: 4px; color: #1A84EE; font-size: 11px;">' +
                                    '<span class="dashicons dashicons-plus-alt" style="font-size: 12px; margin-top: 3px;"></span> ' +
                                    '<?php _e('Add Another', 'amelia-cpt-sync'); ?>' +
                                '</button>' +
                            '</div>' +
                        '</div>';
                        
                        $('#composite-groups-container').append(groupHtml);
                        
                        console.log('[Composite Repeater] Added group #' + idx);
                    },
                    
                    removeGroup: function(idx) {
                        $('.composite-group[data-group-index="' + idx + '"]').fadeOut(200, function() {
                            $(this).remove();
                            
                            // Show empty state if no groups left
                            if ($('#composite-groups-container .composite-group').length === 0) {
                                if ($('#composite-empty-state').length === 0) {
                                    $('#composite-groups-container').html(
                                        '<div id="composite-empty-state" style="padding: 20px; background: #F8FAFC; border: 1px dashed #CBD5E1; border-radius: 8px; text-align: center; color: #64748B; margin-bottom: 12px;">' +
                                        '<?php _e('No requirement groups configured yet. Click the button below to add one.', 'amelia-cpt-sync'); ?>' +
                                        '</div>'
                                    );
                                } else {
                                    $('#composite-empty-state').show();
                                }
                            }
                        });
                        
                        console.log('[Composite Repeater] Removed group #' + idx);
                    },
                    
                    addNewResourceRow: function(groupIdx) {
                        var rowId = this.nextRowId++;
                        var defaultName = this.serviceName + ' Resource #' + rowId;
                        
                        var rowHtml = '<tr data-group-index="' + groupIdx + '" data-row-id="' + rowId + '">' +
                            '<td style="padding: 8px 10px;">' +
                                '<input type="text" ' +
                                       'name="resource_config[new_composite_resources][' + groupIdx + '][' + rowId + '][name]" ' +
                                       'value="' + defaultName + '" ' +
                                       'placeholder="Resource name" ' +
                                       'style="width: 100%; padding: 4px 8px; border: 1px solid #E0E5F1; border-radius: 4px; font-size: 12px;">' +
                            '</td>' +
                            '<td style="padding: 8px 10px;">' +
                                '<input type="number" ' +
                                       'name="resource_config[new_composite_resources][' + groupIdx + '][' + rowId + '][quantity]" ' +
                                       'value="1" min="1" ' +
                                       'style="width: 50px; padding: 4px 8px; border: 1px solid #E0E5F1; border-radius: 4px; font-size: 12px; text-align: center;">' +
                            '</td>' +
                            '<td style="padding: 8px 10px; text-align: center;">' +
                                '<button type="button" class="composite-new-res-remove pool-repeater-remove" ' +
                                        'data-group-index="' + groupIdx + '" data-row-id="' + rowId + '" ' +
                                        'title="<?php _e('Remove', 'amelia-cpt-sync'); ?>">' +
                                    '<span class="dashicons dashicons-trash"></span>' +
                                '</button>' +
                            '</td>' +
                        '</tr>';
                        
                        $('.composite-new-resource-body[data-group-index="' + groupIdx + '"]').append(rowHtml);
                        console.log('[Composite Repeater] Added new resource row #' + rowId + ' to group #' + groupIdx);
                    },
                    
                    removeNewResourceRow: function(groupIdx, rowId) {
                        $('tr[data-group-index="' + groupIdx + '"][data-row-id="' + rowId + '"]').fadeOut(200, function() {
                            $(this).remove();
                            
                            // Hide repeater if no rows left in this group
                            if ($('.composite-new-resource-body[data-group-index="' + groupIdx + '"] tr').length === 0) {
                                $('.composite-new-resource-repeater[data-group-index="' + groupIdx + '"]').hide();
                                $('.composite-create-resource-trigger[data-group-index="' + groupIdx + '"]').show();
                            }
                        });
                        console.log('[Composite Repeater] Removed new resource row #' + rowId + ' from group #' + groupIdx);
                    }
                };
                
                compositeGroupManager.init();
                window.compositeGroupManager = compositeGroupManager;
            });
            </script>
            
            </div><!-- /.mode-section[data-mode="composite"] -->
            
            <!-- Mode Section Show/Hide + Transition Logic JavaScript -->
            <script>
            jQuery(document).ready(function($) {
                // Track the mode that was active when modal opened
                var originalMode = '<?php echo esc_js($effective_mode); ?>';
                
                // Show/hide mode sections and handle transitions
                function updateModeSections() {
                    var selectedMode = $('#art-resource-mode').val();
                    var isTransition = (selectedMode !== originalMode && originalMode !== 'none');
                    
                    // Show the correct mode section
                    $('.mode-section').hide();
                    $('.mode-section[data-mode="' + selectedMode + '"]').show();
                    
                    // Reset transition UI
                    $('#mode-transition-banner').hide().empty();
                    $('#dedicated-resource-picker').hide();
                    
                    // Handle transitions between modes
                    if (isTransition) {
                        handleModeTransition(originalMode, selectedMode);
                    }
                }
                
                function handleModeTransition(fromMode, toMode) {
                    var $banner = $('#mode-transition-banner');
                    
                    if (fromMode === 'shared_pool' && toMode === 'mirrored') {
                        // POOL → DEDICATED: Show radio picker, hide normal mirrored UI
                        $banner.html(
                            '<div style="padding: 12px; background: #FEF3C7; border: 1px solid #F59E0B; border-radius: 6px; margin-bottom: 12px; font-size: 13px; color: #92400E;">' +
                                '<strong>⚠️ <?php _e('Switching from Resource Pool to Dedicated Resource', 'amelia-cpt-sync'); ?></strong><br>' +
                                '<?php _e('Select which resource to keep. The others will be unlinked from this service on save. (They will still exist in Amelia.)', 'amelia-cpt-sync'); ?>' +
                            '</div>'
                        ).show();
                        
                        // Hide the normal mirrored edit/new state
                        $('#resource-default-state').hide();
                        $('#resource-select-state').hide();
                        
                        // Show the resource picker radio list
                        $('#dedicated-resource-picker').show();
                        
                        // Pre-select pool resources if they exist (from checkboxes)
                        var firstPoolResource = $('input[name="resource_config[pool_resources][]"]:checked').first().val();
                        if (firstPoolResource) {
                            $('input[name="resource_config[transition_resource_id]"][value="' + firstPoolResource + '"]').prop('checked', true);
                        }
                        
                    } else if (fromMode === 'mirrored' && toMode === 'shared_pool') {
                        // DEDICATED → POOL: Auto-check the dedicated resource in pool list
                        var dedicatedId = '<?php echo esc_js($mirrored_resource_id ?? ''); ?>';
                        
                        $banner.html(
                            '<div style="padding: 12px; background: #DBEAFE; border: 1px solid #3B82F6; border-radius: 6px; margin-bottom: 12px; font-size: 13px; color: #1E40AF;">' +
                                '<strong>ℹ️ <?php _e('Switching from Dedicated Resource to Resource Pool', 'amelia-cpt-sync'); ?></strong><br>' +
                                '<?php _e('Your current dedicated resource will be added to the pool. You can select additional resources below.', 'amelia-cpt-sync'); ?>' +
                            '</div>'
                        ).show();
                        
                        // Auto-check the dedicated resource in pool checkboxes
                        if (dedicatedId) {
                            $('input[name="resource_config[pool_resources][]"][value="' + dedicatedId + '"]').prop('checked', true);
                        }
                        
                    } else if (fromMode === 'mirrored' && toMode === 'composite') {
                        // DEDICATED → MULTI RESOURCE: Seed first group with dedicated resource
                        var dedicatedId = '<?php echo esc_js($mirrored_resource_id ?? ''); ?>';
                        
                        $banner.html(
                            '<div style="padding: 12px; background: #DBEAFE; border: 1px solid #3B82F6; border-radius: 6px; margin-bottom: 12px; font-size: 13px; color: #1E40AF;">' +
                                '<strong>ℹ️ <?php _e('Switching to Multi Resource', 'amelia-cpt-sync'); ?></strong><br>' +
                                '<?php _e('Add requirement groups below. Your current dedicated resource can be added to a group.', 'amelia-cpt-sync'); ?>' +
                            '</div>'
                        ).show();
                        
                    } else if (fromMode === 'shared_pool' && toMode === 'composite') {
                        // POOL → MULTI RESOURCE: Info banner
                        $banner.html(
                            '<div style="padding: 12px; background: #DBEAFE; border: 1px solid #3B82F6; border-radius: 6px; margin-bottom: 12px; font-size: 13px; color: #1E40AF;">' +
                                '<strong>ℹ️ <?php _e('Switching to Multi Resource', 'amelia-cpt-sync'); ?></strong><br>' +
                                '<?php _e('Add requirement groups below. Your pool resources can be organized into groups.', 'amelia-cpt-sync'); ?>' +
                            '</div>'
                        ).show();
                        
                    } else if (fromMode === 'composite' && toMode === 'mirrored') {
                        // MULTI RESOURCE → DEDICATED: Show radio picker
                        $banner.html(
                            '<div style="padding: 12px; background: #FEF3C7; border: 1px solid #F59E0B; border-radius: 6px; margin-bottom: 12px; font-size: 13px; color: #92400E;">' +
                                '<strong>⚠️ <?php _e('Switching to Dedicated Resource', 'amelia-cpt-sync'); ?></strong><br>' +
                                '<?php _e('Select which resource to keep. Other resources will be unlinked on save.', 'amelia-cpt-sync'); ?>' +
                            '</div>'
                        ).show();
                        
                        // Show the resource picker (same as pool→dedicated transition)
                        $('#resource-default-state').hide();
                        $('#resource-select-state').hide();
                        $('#dedicated-resource-picker').show();
                        
                    } else if (fromMode === 'composite' && toMode === 'shared_pool') {
                        // MULTI RESOURCE → POOL: Seed pool with all composite resources
                        $banner.html(
                            '<div style="padding: 12px; background: #DBEAFE; border: 1px solid #3B82F6; border-radius: 6px; margin-bottom: 12px; font-size: 13px; color: #1E40AF;">' +
                                '<strong>ℹ️ <?php _e('Switching to Resource Pool', 'amelia-cpt-sync'); ?></strong><br>' +
                                '<?php _e('Resources from your requirement groups have been added to the pool. Adjust as needed.', 'amelia-cpt-sync'); ?>' +
                            '</div>'
                        ).show();
                        
                        // Auto-check all resources that were in composite groups
                        $('.composite-group input[type="checkbox"]:checked').each(function() {
                            var resourceId = $(this).val();
                            $('input[name="resource_config[pool_resources][]"][value="' + resourceId + '"]').prop('checked', true);
                        });
                        
                    } else if (toMode === 'none') {
                        // ANY → NONE: Info banner
                        $banner.html(
                            '<div style="padding: 12px; background: #F1F5F9; border: 1px solid #CBD5E1; border-radius: 6px; margin-bottom: 12px; font-size: 13px; color: #475569;">' +
                                '<strong>ℹ️ <?php _e('Removing Resource Tracking', 'amelia-cpt-sync'); ?></strong><br>' +
                                '<?php _e('Resources will be unlinked from this service on save. Existing resources will not be deleted from Amelia.', 'amelia-cpt-sync'); ?>' +
                            '</div>'
                        ).show();
                    }
                }
                
                // When radio is selected in transition picker, update the mirrored form fields
                $(document).on('change', 'input[name="resource_config[transition_resource_id]"]', function() {
                    var $selected = $(this);
                    var resName = $selected.data('name');
                    var resQty = $selected.data('quantity');
                    var resId = $selected.val();
                    
                    // Update the mirrored form fields so save handler picks them up
                    $('#resource_name').val(resName);
                    $('#resource_quantity').val(resQty);
                    $('#selected_resource_id').val(resId);
                    $('#resource_action').val('switch');
                    
                    console.log('[Mode Transition] Selected resource #' + resId + ': ' + resName + ' (' + resQty + ' units)');
                });
                
                // When user switches back to original mode, restore normal UI
                $('#art-resource-mode').on('change', function() {
                    var selectedMode = $(this).val();
                    
                    // Restore mirrored default state visibility when not transitioning
                    if (selectedMode === 'mirrored' && selectedMode === originalMode) {
                        $('#resource-default-state').show();
                        $('#dedicated-resource-picker').hide();
                    }
                    
                    updateModeSections();
                });
                
                // Initialize on load (no transition on first render)
                updateModeSections();
            });
            </script>
            
            <?php endif; ?><!-- /if resource_enabled -->
            
            <?php if (!empty($definitions)): ?>
            <!-- CUSTOM FIELDS SECTION -->
            <h4 style="margin-top: 20px;"><?php _e('CPT Custom Fields', 'amelia-cpt-sync'); ?></h4>
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
            <?php endif; ?>
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
        $resource_config = isset($_POST['resource_config']) ? $_POST['resource_config'] : array();
        
        if (!$service_id) {
            wp_send_json_error(array('message' => 'No service ID provided'));
        }
        
        amelia_cpt_sync_debug_log("Saving custom field values for service {$service_id}");
        
        // Save custom fields
        $manager = new Amelia_CPT_Sync_Custom_Fields_Manager();
        $result = $manager->save_service_field_values($service_id, $values);
        
        // Save resource configuration using PER-SERVICE mode (v2.34.0)
        $selected_mode = sanitize_text_field($resource_config['mode'] ?? '');
        if (empty($selected_mode)) {
            // Fall back to global default if mode not sent from modal
            $global_settings = get_option('art_resource_settings', array());
            $selected_mode = $global_settings['default_mode'] ?? 'none';
        }
        
        if (!empty($resource_config) && isset($resource_config['mode'])) {
            amelia_cpt_sync_debug_log("Saving resource configuration for service {$service_id} (Per-Service Mode: {$selected_mode})", $resource_config);
            
            // If mode is 'none', just save the mode and skip resource logic
            if ($selected_mode === 'none') {
                $resource_manager = new ART_Resource_Manager();
                $resource_manager->save_service_config($service_id, array(
                    'resource_mode' => 'none',
                    'mode_settings' => array(),
                    'conflict_handling' => 'strict'
                ));
                amelia_cpt_sync_debug_log("  → Mode set to 'none' — no resource configuration needed");
            } else {
            
            $resource_manager = new ART_Resource_Manager();
            $mode_settings = array();
            
            // Detect mode transition for cleanup
            $existing_config = $resource_manager->get_service_config($service_id);
            $old_mode = ($existing_config && $existing_config->resource_mode) ? $existing_config->resource_mode : 'none';
            $old_mode_settings = ($existing_config && $existing_config->mode_settings) ? $existing_config->mode_settings : array();
            
            if ($old_mode !== $selected_mode && $old_mode !== 'none') {
                amelia_cpt_sync_debug_log("Mode transition: {$old_mode} → {$selected_mode} for service #{$service_id}");
                
                // Handle mode transitions
                if ($old_mode === 'mirrored' && $selected_mode === 'shared_pool') {
                    // Mirrored → Pool: Seed pool with dedicated resource
                    $old_resource_id = $old_mode_settings['mirrored_resource_id'] ?? null;
                    if ($old_resource_id) {
                        // Pre-fill pool with the existing dedicated resource
                        $pool_resources_from_post = isset($resource_config['pool_resources']) && is_array($resource_config['pool_resources']) 
                            ? array_map('intval', $resource_config['pool_resources']) 
                            : array();
                        if (!in_array($old_resource_id, $pool_resources_from_post)) {
                            $pool_resources_from_post[] = intval($old_resource_id);
                            $resource_config['pool_resources'] = $pool_resources_from_post;
                        }
                        amelia_cpt_sync_debug_log("  → Seeded pool with mirrored resource #{$old_resource_id}");
                    }
                } elseif ($old_mode === 'shared_pool' && $selected_mode === 'mirrored') {
                    // Pool → Mirrored: Keep first pool resource as dedicated
                    $old_pool_ids = $old_mode_settings['pool_resource_ids'] ?? array();
                    if (!empty($old_pool_ids) && empty($resource_config['resource_id'])) {
                        $resource_config['resource_id'] = $old_pool_ids[0];
                        $resource_config['action'] = 'switch';
                        amelia_cpt_sync_debug_log("  → Set mirrored resource to first pool resource #{$old_pool_ids[0]}");
                        
                        // Unlink other pool resources from this service
                        if (count($old_pool_ids) > 1) {
                            global $wpdb;
                            $entities_table = $wpdb->prefix . 'amelia_resources_to_entities';
                            foreach (array_slice($old_pool_ids, 1) as $unlink_rid) {
                                $wpdb->delete($entities_table, [
                                    'resourceId' => intval($unlink_rid),
                                    'entityId' => intval($service_id),
                                    'entityType' => 'service'
                                ], ['%d', '%d', '%s']);
                                amelia_cpt_sync_debug_log("  → Unlinked pool resource #{$unlink_rid} from service");
                                delete_transient('art_resource_' . $unlink_rid);
                            }
                            delete_transient('art_all_resources');
                        }
                    }
                }
            }
            
            if ($selected_mode === 'mirrored') {
                $mode_settings['quantity_required'] = isset($resource_config['quantity_required']) ? absint($resource_config['quantity_required']) : 1;
                $mode_settings['sync_name'] = $global_settings['mirrored']['sync_name'] ?? true;
                
                $resource_api = new ART_Resource_API();
                $action = $resource_config['action'] ?? 'update';
                $resource_id = $resource_config['resource_id'] ?? null;
                $resource_name = sanitize_text_field($resource_config['resource_name'] ?? '');
                $resource_quantity = absint($resource_config['resource_quantity'] ?? 1);
                $original_resource_id = $resource_config['original_resource_id'] ?? null;
                
                // Get service name for default resource name
                $service_data = $this->get_amelia_service_by_id($service_id);
                $service_name = $service_data['name'] ?? 'Service';
                
                amelia_cpt_sync_debug_log("Resource action: {$action} for service #{$service_id}");
                
                // Handle based on action (from state machine)
                if ($action === 'create' || $resource_id === 'new' || empty($resource_id)) {
                    // CREATE NEW RESOURCE
                    amelia_cpt_sync_debug_log("Creating new resource for service #{$service_id}");
                    
                    $new_resource = $resource_api->create_resource(array(
                        'name' => $resource_name ?: ($service_name . ' (Resource)'),
                        'quantity' => max(1, $resource_quantity),
                        'status' => 'visible',
                        'entities' => array(
                            array('entityId' => $service_id, 'entityType' => 'service')
                        )
                    ));
                    
                    if (!is_wp_error($new_resource)) {
                        $mode_settings['mirrored_resource_id'] = $new_resource['id'];
                        $mode_settings['auto_created'] = true;  // Track for orphan cleanup
                        amelia_cpt_sync_debug_log("Created resource #{$new_resource['id']} for service #{$service_id}");
                        
                        // CRITICAL: Remove service from OLD resource's entities (enables orphan cleanup)
                        if ($original_resource_id && $original_resource_id != $new_resource['id']) {
                            global $wpdb;
                            $entities_table = $wpdb->prefix . 'amelia_resources_to_entities';
                            
                            amelia_cpt_sync_debug_log("Removing Service #{$service_id} from old Resource #{$original_resource_id} entities");
                            
                            $delete_result = $wpdb->delete(
                                $entities_table,
                                [
                                    'resourceId' => intval($original_resource_id),
                                    'entityId' => intval($service_id),
                                    'entityType' => 'service'
                                ],
                                ['%d', '%d', '%s']
                            );
                            
                            if ($delete_result !== false) {
                                amelia_cpt_sync_debug_log("✓ Removed Service #{$service_id} from old Resource #{$original_resource_id} (rows affected: {$delete_result})");
                                // Clear cache immediately
                                delete_transient('art_resource_' . $original_resource_id);
                                delete_transient('art_all_resources');
                            } else {
                                amelia_cpt_sync_debug_log("✗ Failed to remove entity link: " . $wpdb->last_error);
                            }
                        }
                    } else {
                        amelia_cpt_sync_debug_log("ERROR creating resource: " . $new_resource->get_error_message());
                    }
                    
                } elseif ($action === 'switch') {
                    // SWITCH TO DIFFERENT EXISTING RESOURCE
                    // Must update Amelia entities to maintain data integrity
                    amelia_cpt_sync_debug_log("Switching service #{$service_id} from resource #{$original_resource_id} to #{$resource_id}");
                    
                    $switch_success = true;
                    
                    // Use database transaction for atomicity
                    global $wpdb;
                    $wpdb->query('START TRANSACTION');
                    
                    try {
                        $entities_table = $wpdb->prefix . 'amelia_resources_to_entities';
                        
                        // STEP 1: Add this service to the NEW resource's entities (DIRECT DB)
                        // Check if already linked
                        $existing = $wpdb->get_var($wpdb->prepare(
                            "SELECT id FROM {$entities_table} 
                            WHERE resourceId = %d AND entityId = %d AND entityType = 'service'",
                            $resource_id,
                            $service_id
                        ));
                        
                        if (!$existing) {
                            // Insert new entity link directly
                            $insert_result = $wpdb->insert(
                                $entities_table,
                                [
                                    'resourceId' => intval($resource_id),
                                    'entityId' => intval($service_id),
                                    'entityType' => 'service'
                                ],
                                ['%d', '%d', '%s']
                            );
                            
                            if ($insert_result === false) {
                                throw new Exception("DB insert failed: " . $wpdb->last_error);
                            }
                            
                            amelia_cpt_sync_debug_log("✓ Added Service #{$service_id} to Resource #{$resource_id} entities (direct DB)");
                            
                            // Clear cache immediately for fresh reads
                            delete_transient('art_resource_' . $resource_id);
                            delete_transient('art_all_resources');
                        } else {
                            amelia_cpt_sync_debug_log("Service #{$service_id} already linked to Resource #{$resource_id}");
                        }
                        
                        // STEP 2: Remove this service from the OLD resource's entities (DIRECT DB)
                        if ($original_resource_id && $original_resource_id != $resource_id) {
                            // Delete old entity link directly
                            $delete_result = $wpdb->delete(
                                $entities_table,
                                [
                                    'resourceId' => intval($original_resource_id),
                                    'entityId' => intval($service_id),
                                    'entityType' => 'service'
                                ],
                                ['%d', '%d', '%s']
                            );
                            
                            if ($delete_result === false) {
                                throw new Exception("DB delete failed: " . $wpdb->last_error);
                            }
                            
                            amelia_cpt_sync_debug_log("✓ Removed Service #{$service_id} from Resource #{$original_resource_id} entities (direct DB)");
                            
                            // Clear cache immediately for fresh reads
                            delete_transient('art_resource_' . $original_resource_id);
                            delete_transient('art_all_resources');
                        }
                        
                        // Commit transaction
                        $wpdb->query('COMMIT');
                        amelia_cpt_sync_debug_log("✓ Entity management transaction committed");
                        
                    } catch (Exception $e) {
                        // Rollback on any error
                        $wpdb->query('ROLLBACK');
                        amelia_cpt_sync_debug_log("✗ Transaction rolled back: " . $e->getMessage());
                        $switch_success = false;
                    }
                    
                    // STEP 3: Update ART config (only if switch was successful)
                    if ($switch_success) {
                        $mode_settings['mirrored_resource_id'] = intval($resource_id);
                        $mode_settings['auto_created'] = false;  // Switched to existing
                        amelia_cpt_sync_debug_log("✓ Switched to resource #{$resource_id}");
                    } else {
                        // Rollback - keep original resource
                        amelia_cpt_sync_debug_log("✗ Switch failed, keeping original resource #{$original_resource_id}");
                        wp_send_json_error([
                            'message' => 'Failed to switch resources. Please try again or contact support.'
                        ]);
                        return;
                    }
                    
                } else {
                    // UPDATE EXISTING RESOURCE (default action)
                    amelia_cpt_sync_debug_log("Updating resource #{$resource_id} for service #{$service_id}");
                    
                    $update_data = array();
                    
                    if (!empty($resource_name)) {
                        $update_data['name'] = $resource_name;
                    }
                    if ($resource_quantity > 0) {
                        $update_data['quantity'] = $resource_quantity;
                    }
                    
                    if (!empty($update_data)) {
                        $update_result = $resource_api->update_resource(intval($resource_id), $update_data);
                        if (!is_wp_error($update_result)) {
                            amelia_cpt_sync_debug_log("Updated resource #{$resource_id}: " . wp_json_encode($update_data));
                        } else {
                            amelia_cpt_sync_debug_log("ERROR updating resource: " . $update_result->get_error_message());
                        }
                    }
                    
                    $mode_settings['mirrored_resource_id'] = intval($resource_id);
                }
                
                // ENHANCED ORPHAN CLEANUP LOGIC (Appendix K.2 + Entity Check)
                // Delete original resource ONLY if this is a NEW service (first-time setup)
                // Not for existing services being reconfigured!
                // Note: $action and $original_resource_id already extracted above
                $cleanup_orphan = $resource_config['cleanup_orphan'] ?? false;
                $is_new_service = isset($resource_config['is_new_service']) && $resource_config['is_new_service'] === '1';
                
                // Get the actual new resource ID (either switched-to or newly created)
                $new_resource_id = $mode_settings['mirrored_resource_id'] ?? null;
                
                // CRITICAL: Orphan cleanup applies to BOTH 'switch' (select existing) AND 'create' (create new)
                // When user creates a NEW resource, the original auto-created one becomes an orphan
                if (($action === 'switch' || $action === 'create') && 
                    $original_resource_id && 
                    $new_resource_id && 
                    $original_resource_id != $new_resource_id) {
                    
                    // CRITICAL CHECK: Only cleanup orphans for NEW services, not existing ones
                    if (!$is_new_service) {
                        amelia_cpt_sync_debug_log("⏭️ Skipping orphan cleanup: This is an UPDATE to an existing service (Amelia: 'Successfully updated')");
                        amelia_cpt_sync_debug_log("  → User is reconfiguring an existing service - preserving original resource #{$original_resource_id}");
                    } else {
                        amelia_cpt_sync_debug_log("⚙️ Checking for orphan cleanup: This is a NEW service with action '{$action}' (Amelia: 'Successfully added')");
                        amelia_cpt_sync_debug_log("  → Resource #{$original_resource_id} was auto-created, now switching to #{$new_resource_id}");
                    
                    // Check 1: Any active bookings/assignments?
                    global $wpdb;
                    $assignments_table = $wpdb->prefix . 'art_resource_assignments';
                    $assignment_count = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$assignments_table} 
                        WHERE amelia_resource_id = %d 
                        AND status = 'active'",
                        $original_resource_id
                    ));
                    
                    amelia_cpt_sync_debug_log("  → Assignments check: {$assignment_count} active bookings");
                    
                    // Check 2: Any entity links remaining in Amelia?
                    // Direct DB query (fresh data since we just did direct DB writes)
                    global $wpdb;
                    $entities_table = $wpdb->prefix . 'amelia_resources_to_entities';
                    $entity_count = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$entities_table} WHERE resourceId = %d",
                        $original_resource_id
                    ));
                    
                    amelia_cpt_sync_debug_log("  → Entity links check: {$entity_count} services/locations/employees linked");
                    
                    // Delete only if BOTH checks pass (0 bookings AND 0 entities)
                    if ($assignment_count == 0 && $entity_count == 0) {
                        amelia_cpt_sync_debug_log("✓ Orphan confirmed: Resource #{$original_resource_id} has 0 bookings and 0 entity links");
                        amelia_cpt_sync_debug_log("  → Deleting orphan resource...");
                        
                        $delete_result = $resource_api->delete_resource($original_resource_id);
                        if (!is_wp_error($delete_result)) {
                            amelia_cpt_sync_debug_log("✓ Successfully deleted orphan resource #{$original_resource_id}");
                        } else {
                            amelia_cpt_sync_debug_log("✗ ERROR deleting orphan: " . $delete_result->get_error_message());
                        }
                    } else {
                        $reasons = [];
                        if ($assignment_count > 0) $reasons[] = "{$assignment_count} active bookings";
                        if ($entity_count > 0) $reasons[] = "{$entity_count} entity links";
                        
                        amelia_cpt_sync_debug_log("✗ Orphan cleanup skipped: Resource #{$original_resource_id} has " . implode(' and ', $reasons));
                    }
                    } // End if ($is_new_service)
                }
                
                // Track if this is a newly created resource for future orphan detection
                if ($action === 'create' && isset($mode_settings['mirrored_resource_id'])) {
                    $mode_settings['auto_created'] = true;
                } elseif ($action === 'switch') {
                    $mode_settings['auto_created'] = false;  // Switched to existing, not auto-created
                }
                
                // Clear resource caches
                delete_transient('art_all_resources');
                delete_transient('art_resource_' . ($mode_settings['mirrored_resource_id'] ?? 0));
                if ($original_resource_id) {
                    delete_transient('art_resource_' . $original_resource_id);
                }
                
            } elseif ($selected_mode === 'shared_pool') {
                // SHARED POOL MODE HANDLER
                // Following lessons from Appendix L.1-L.7
                
                $pool_resource_ids = isset($resource_config['pool_resources']) && is_array($resource_config['pool_resources']) 
                    ? array_map('intval', $resource_config['pool_resources']) 
                    : array();
                $selection_strategy = sanitize_text_field($resource_config['selection_strategy'] ?? 'first_available');
                $quantity_per_booking = absint($resource_config['quantity_per_booking'] ?? 1);
                
                amelia_cpt_sync_debug_log("Shared Pool config for service #{$service_id}: " . count($pool_resource_ids) . " resources, strategy: {$selection_strategy}");
                
                // NEW: Handle created resources from repeater (Lesson #2 - Use API for creation)
                $new_pool_resources = isset($resource_config['new_pool_resources']) && is_array($resource_config['new_pool_resources']) 
                    ? $resource_config['new_pool_resources'] 
                    : array();
                
                if (!empty($new_pool_resources)) {
                    amelia_cpt_sync_debug_log("  → Creating " . count($new_pool_resources) . " new pool resources");
                    
                    $resource_api = new ART_Resource_API();
                    
                    foreach ($new_pool_resources as $row_id => $new_res) {
                        // Skip empty rows
                        if (empty($new_res['name'])) {
                            amelia_cpt_sync_debug_log("    ⏭️ Skipping empty row #{$row_id}");
                            continue;
                        }
                        
                        $new_name = sanitize_text_field($new_res['name']);
                        $new_quantity = max(1, absint($new_res['quantity'] ?? 1));
                        
                        amelia_cpt_sync_debug_log("    → Creating: '{$new_name}' (qty: {$new_quantity})");
                        
                        // STEP 1: Create via API (same pattern as Mirrored mode!)
                        // API automatically creates entity link when 'entities' is provided
                        $created = $resource_api->create_resource(array(
                            'name' => $new_name,
                            'quantity' => $new_quantity,
                            'status' => 'visible',
                            'entities' => array(
                                array('entityId' => $service_id, 'entityType' => 'service')
                            )
                        ));
                        
                        if (!is_wp_error($created)) {
                            // Add newly created resource to pool list
                            $pool_resource_ids[] = $created['id'];
                            amelia_cpt_sync_debug_log("    ✓ Created Resource #{$created['id']}: {$new_name}");
                        } else {
                            amelia_cpt_sync_debug_log("    ✗ ERROR creating resource: " . $created->get_error_message());
                        }
                    }
                    
                    amelia_cpt_sync_debug_log("  ✓ Pool now has " . count($pool_resource_ids) . " total resources (including newly created)");
                }
                
                // Get existing pool configuration for comparison
                $existing_config = $resource_manager->get_service_config($service_id);
                $original_pool_ids = ($existing_config && isset($existing_config->mode_settings['pool_resource_ids'])) 
                    ? $existing_config->mode_settings['pool_resource_ids'] 
                    : array();
                
                amelia_cpt_sync_debug_log("  → Original pool: [" . implode(', ', $original_pool_ids) . "]");
                amelia_cpt_sync_debug_log("  → New pool: [" . implode(', ', $pool_resource_ids) . "]");
                
                // REUSE: Direct DB entity management pattern (Lesson #2)
                global $wpdb;
                $entities_table = $wpdb->prefix . 'amelia_resources_to_entities';
                $assignments_table = $wpdb->prefix . 'art_resource_assignments';
                
                // REUSE: Transaction pattern (Lesson #2, #6)
                $wpdb->query('START TRANSACTION');
                
                try {
                    // Resources to ADD (in new pool but not in original)
                    $to_add = array_diff($pool_resource_ids, $original_pool_ids);
                    
                    if (!empty($to_add)) {
                        amelia_cpt_sync_debug_log("  → Adding " . count($to_add) . " resources to pool: [" . implode(', ', $to_add) . "]");
                    }
                    
                    foreach ($to_add as $rid) {
                        // Check if already linked (safety check)
                        $existing = $wpdb->get_var($wpdb->prepare(
                            "SELECT id FROM {$entities_table} 
                            WHERE resourceId = %d AND entityId = %d AND entityType = 'service'",
                            $rid,
                            $service_id
                        ));
                        
                        if (!$existing) {
                            $insert_result = $wpdb->insert(
                                $entities_table,
                                [
                                    'resourceId' => intval($rid),
                                    'entityId' => intval($service_id),
                                    'entityType' => 'service'
                                ],
                                ['%d', '%d', '%s']
                            );
                            
                            if ($insert_result === false) {
                                throw new Exception("Failed to add Resource #{$rid} to pool: " . $wpdb->last_error);
                            }
                            
                            amelia_cpt_sync_debug_log("    ✓ Added Resource #{$rid} to pool for Service #{$service_id}");
                        } else {
                            amelia_cpt_sync_debug_log("    • Resource #{$rid} already linked to Service #{$service_id}");
                        }
                    }
                    
                    // Resources to REMOVE (in original but not in new pool)
                    $to_remove = array_diff($original_pool_ids, $pool_resource_ids);
                    
                    if (!empty($to_remove)) {
                        amelia_cpt_sync_debug_log("  → Removing " . count($to_remove) . " resources from pool: [" . implode(', ', $to_remove) . "]");
                    }
                    
                    $resource_api = new ART_Resource_API();
                    
                    foreach ($to_remove as $rid) {
                        $delete_result = $wpdb->delete(
                            $entities_table,
                            [
                                'resourceId' => intval($rid),
                                'entityId' => intval($service_id),
                                'entityType' => 'service'
                            ],
                            ['%d', '%d', '%s']
                        );
                        
                        if ($delete_result === false) {
                            throw new Exception("Failed to remove Resource #{$rid} from pool: " . $wpdb->last_error);
                        }
                        
                        amelia_cpt_sync_debug_log("    ✓ Removed Resource #{$rid} from pool (rows affected: {$delete_result})");
                        
                        // REUSE: Orphan cleanup pattern (Lesson #5, #4)
                        // Only cleanup for NEW services, not existing ones being reconfigured
                        $is_new_service = isset($resource_config['is_new_service']) && $resource_config['is_new_service'] === '1';
                        
                        if ($is_new_service) {
                            amelia_cpt_sync_debug_log("    🔍 Orphan check for Resource #{$rid} (new service):");
                            
                            // Check 1: Any active bookings/assignments?
                            $assignment_count = $wpdb->get_var($wpdb->prepare(
                                "SELECT COUNT(*) FROM {$assignments_table} 
                                WHERE amelia_resource_id = %d AND status = 'active'",
                                $rid
                            ));
                            
                            amelia_cpt_sync_debug_log("      → Assignments: {$assignment_count}");
                            
                            // Check 2: Any entity links remaining?
                            $entity_count = $wpdb->get_var($wpdb->prepare(
                                "SELECT COUNT(*) FROM {$entities_table} WHERE resourceId = %d",
                                $rid
                            ));
                            
                            amelia_cpt_sync_debug_log("      → Entity links: {$entity_count}");
                            
                            // Delete only if BOTH are zero (Lesson #5)
                            if ($assignment_count == 0 && $entity_count == 0) {
                                amelia_cpt_sync_debug_log("      ✓ Orphan confirmed - deleting Resource #{$rid}");
                                
                                $delete_resource_result = $resource_api->delete_resource($rid);
                                if (!is_wp_error($delete_resource_result)) {
                                    amelia_cpt_sync_debug_log("      ✓ Successfully deleted orphan Resource #{$rid}");
                                } else {
                                    amelia_cpt_sync_debug_log("      ✗ ERROR deleting orphan: " . $delete_resource_result->get_error_message());
                                }
                            } else {
                                $reasons = [];
                                if ($assignment_count > 0) $reasons[] = "{$assignment_count} bookings";
                                if ($entity_count > 0) $reasons[] = "{$entity_count} entity links";
                                amelia_cpt_sync_debug_log("      ⏭️ Skipped - has " . implode(' and ', $reasons));
                            }
                        } else {
                            amelia_cpt_sync_debug_log("    ⏭️ Orphan check skipped: Existing service reconfiguration (Lesson #4)");
                        }
                    }
                    
                    // Commit transaction (Lesson #2)
                    $wpdb->query('COMMIT');
                    amelia_cpt_sync_debug_log("  ✓ Pool entity management transaction committed");
                    
                    // REUSE: Cache clearing (Lesson #6) - Clear ALL affected resources
                    foreach (array_merge($pool_resource_ids, $to_remove) as $rid) {
                        delete_transient('art_resource_' . $rid);
                    }
                    delete_transient('art_all_resources');
                    
                } catch (Exception $e) {
                    // REUSE: Rollback pattern (Lesson #2)
                    $wpdb->query('ROLLBACK');
                    amelia_cpt_sync_debug_log("  ✗ Pool update transaction rolled back: " . $e->getMessage());
                    
                    wp_send_json_error([
                        'message' => 'Failed to update resource pool. Please try again.'
                    ]);
                    return;
                }
                
                // Save pool configuration
                $mode_settings = [
                    'pool_resource_ids' => $pool_resource_ids,
                    'selection_strategy' => $selection_strategy,
                    'quantity_per_booking' => $quantity_per_booking
                ];
                
                amelia_cpt_sync_debug_log("  ✓ Pool configuration saved: " . count($pool_resource_ids) . " resources");
                
            } elseif ($selected_mode === 'composite') {
                // COMPOSITE (MULTI RESOURCE) MODE HANDLER
                amelia_cpt_sync_debug_log("Composite config for service #{$service_id}");
                
                $raw_groups = isset($resource_config['requirement_groups']) && is_array($resource_config['requirement_groups']) 
                    ? $resource_config['requirement_groups'] 
                    : array();
                
                // NEW: Create resources from per-group repeaters (same API pattern as pool mode)
                $new_composite_resources = isset($resource_config['new_composite_resources']) && is_array($resource_config['new_composite_resources'])
                    ? $resource_config['new_composite_resources']
                    : array();
                
                if (!empty($new_composite_resources)) {
                    amelia_cpt_sync_debug_log("  → Creating new composite resources from repeaters");
                    $resource_api_for_composite = new ART_Resource_API();
                    
                    foreach ($new_composite_resources as $group_idx => $rows) {
                        if (!is_array($rows)) continue;
                        
                        foreach ($rows as $row_id => $new_res) {
                            if (empty($new_res['name'])) continue;
                            
                            $new_name = sanitize_text_field($new_res['name']);
                            $new_quantity = max(1, absint($new_res['quantity'] ?? 1));
                            
                            amelia_cpt_sync_debug_log("    → Creating: '{$new_name}' (qty: {$new_quantity}) for group #{$group_idx}");
                            
                            // Create via API (auto-links entity via entities payload)
                            $created = $resource_api_for_composite->create_resource(array(
                                'name' => $new_name,
                                'quantity' => $new_quantity,
                                'status' => 'visible',
                                'entities' => array(
                                    array('entityId' => $service_id, 'entityType' => 'service')
                                )
                            ));
                            
                            if (!is_wp_error($created)) {
                                // Inject into the matching group's resource_ids
                                if (isset($raw_groups[$group_idx])) {
                                    if (!isset($raw_groups[$group_idx]['resource_ids']) || !is_array($raw_groups[$group_idx]['resource_ids'])) {
                                        $raw_groups[$group_idx]['resource_ids'] = array();
                                    }
                                    $raw_groups[$group_idx]['resource_ids'][] = strval($created['id']);
                                    amelia_cpt_sync_debug_log("    ✓ Created Resource #{$created['id']}: {$new_name} → injected into group #{$group_idx}");
                                } else {
                                    amelia_cpt_sync_debug_log("    ⚠️ Created Resource #{$created['id']} but group #{$group_idx} doesn't exist in submitted data");
                                }
                            } else {
                                amelia_cpt_sync_debug_log("    ✗ ERROR creating resource: " . $created->get_error_message());
                            }
                        }
                    }
                }
                
                // Clean and validate groups
                $cleaned_groups = array();
                $all_composite_resource_ids = array();
                
                foreach ($raw_groups as $group) {
                    $resource_ids = isset($group['resource_ids']) && is_array($group['resource_ids']) 
                        ? array_map('intval', $group['resource_ids']) 
                        : array();
                    
                    if (empty($resource_ids)) {
                        amelia_cpt_sync_debug_log("  ⏭️ Skipping group with no resources");
                        continue;
                    }
                    
                    $cleaned_group = array(
                        'label' => sanitize_text_field($group['label'] ?? ''),
                        'resource_ids' => $resource_ids,
                        'quantity_needed' => max(1, absint($group['quantity_needed'] ?? 1)),
                        'strategy' => 'first_available'
                    );
                    
                    $cleaned_groups[] = $cleaned_group;
                    $all_composite_resource_ids = array_merge($all_composite_resource_ids, $resource_ids);
                    
                    amelia_cpt_sync_debug_log("  → Group \"{$cleaned_group['label']}\": resources [" . implode(', ', $resource_ids) . "], qty_needed: {$cleaned_group['quantity_needed']}");
                }
                
                $all_composite_resource_ids = array_unique($all_composite_resource_ids);
                amelia_cpt_sync_debug_log("  → All unique resource IDs: [" . implode(', ', $all_composite_resource_ids) . "]");
                
                // Entity management: link ALL resources from ALL groups to service (direct DB, transaction pattern)
                $existing_config_for_entity = $resource_manager->get_service_config($service_id);
                $original_composite_ids = array();
                if ($existing_config_for_entity && isset($existing_config_for_entity->mode_settings['requirement_groups'])) {
                    foreach ($existing_config_for_entity->mode_settings['requirement_groups'] as $old_group) {
                        $original_composite_ids = array_merge($original_composite_ids, ($old_group['resource_ids'] ?? array()));
                    }
                }
                // Also include pool/mirrored resources that may have been linked before mode switch
                if ($existing_config_for_entity) {
                    $old_pool = $existing_config_for_entity->mode_settings['pool_resource_ids'] ?? array();
                    $old_mirrored = $existing_config_for_entity->mode_settings['mirrored_resource_id'] ?? null;
                    if (!empty($old_pool)) $original_composite_ids = array_merge($original_composite_ids, $old_pool);
                    if ($old_mirrored) $original_composite_ids[] = $old_mirrored;
                }
                $original_composite_ids = array_unique(array_map('intval', $original_composite_ids));
                
                $to_add = array_diff($all_composite_resource_ids, $original_composite_ids);
                $to_remove = array_diff($original_composite_ids, $all_composite_resource_ids);
                
                if (!empty($to_add) || !empty($to_remove)) {
                    global $wpdb;
                    $entities_table = $wpdb->prefix . 'amelia_resources_to_entities';
                    
                    $wpdb->query('START TRANSACTION');
                    
                    try {
                        // Add new entity links
                        foreach ($to_add as $rid) {
                            $existing = $wpdb->get_var($wpdb->prepare(
                                "SELECT id FROM {$entities_table} WHERE resourceId = %d AND entityId = %d AND entityType = 'service'",
                                $rid, $service_id
                            ));
                            
                            if (!$existing) {
                                $wpdb->insert($entities_table, [
                                    'resourceId' => intval($rid),
                                    'entityId' => intval($service_id),
                                    'entityType' => 'service'
                                ], ['%d', '%d', '%s']);
                                amelia_cpt_sync_debug_log("    ✓ Entity link added: Resource #{$rid} → Service #{$service_id}");
                            }
                        }
                        
                        // Remove stale entity links
                        foreach ($to_remove as $rid) {
                            $wpdb->delete($entities_table, [
                                'resourceId' => intval($rid),
                                'entityId' => intval($service_id),
                                'entityType' => 'service'
                            ], ['%d', '%d', '%s']);
                            amelia_cpt_sync_debug_log("    ✓ Entity link removed: Resource #{$rid} → Service #{$service_id}");
                        }
                        
                        $wpdb->query('COMMIT');
                        amelia_cpt_sync_debug_log("  ✓ Composite entity management transaction committed");
                        
                    } catch (Exception $e) {
                        $wpdb->query('ROLLBACK');
                        amelia_cpt_sync_debug_log("  ✗ Composite entity transaction rolled back: " . $e->getMessage());
                    }
                    
                    // Clear caches
                    foreach (array_merge($to_add, $to_remove) as $rid) {
                        delete_transient('art_resource_' . $rid);
                    }
                    delete_transient('art_all_resources');
                }
                
                $mode_settings = array(
                    'requirement_groups' => $cleaned_groups
                );
                
                amelia_cpt_sync_debug_log("  ✓ Composite configuration saved: " . count($cleaned_groups) . " groups, " . count($all_composite_resource_ids) . " unique resources");
            }
            
            $resource_manager->save_service_config($service_id, array(
                'resource_mode' => $selected_mode,  // Per-service mode from modal dropdown
                'mode_settings' => $mode_settings,
                'conflict_handling' => 'strict'
            ));
            } // end else (mode !== 'none')
        }
        
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
            
            $success_msg = 'Custom field values saved successfully!';
            if (!empty($resource_config)) {
                $success_msg .= ' Resource configuration updated.';
            }
            wp_send_json_success(array('message' => $success_msg));
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
        
        $global_config_loading = isset($global['config_loading']) ? sanitize_text_field($global['config_loading']) : 'fresh';
        if (!in_array($global_config_loading, array('fresh', 'cached'))) {
            $global_config_loading = 'fresh';
        }
        
        $data = array(
            'global' => array(
                'default_popup_id' => isset($global['default_popup_id']) ? sanitize_title($global['default_popup_id']) : '',
                'debug_enabled' => !empty($global['debug_enabled']),
                'config_loading' => $global_config_loading
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
            
            // Caching settings
            $use_global_caching = isset($config['use_global_caching']) && $config['use_global_caching'] == '1';
            $caching_method = 'fresh'; // Default
            if (!$use_global_caching && isset($config['caching_method'])) {
                $caching_method = sanitize_text_field($config['caching_method']);
                if (!in_array($caching_method, array('fresh', 'cached'))) {
                    $caching_method = 'fresh';
                }
            }

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
                'use_global_caching' => $use_global_caching,
                'caching_method' => $caching_method,
            );
            
            amelia_cpt_sync_debug_log("Config {$key} customizations: employees=" . ($hide_employees ? 'true' : 'false') . ", pricing=" . ($hide_pricing ? 'true' : 'false') . ", extras=" . ($hide_extras ? 'true' : 'false') . ", caching=" . ($use_global_caching ? 'global' : $caching_method));

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


