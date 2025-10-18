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
     * The settings file path
     */
    private $settings_file;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->settings_file = AMELIA_CPT_SYNC_PLUGIN_DIR . 'settings.json';
    }
    
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
        add_action('wp_ajax_amelia_cpt_sync_view_log', array($this, 'ajax_view_log'));
        add_action('wp_ajax_amelia_cpt_sync_clear_log', array($this, 'ajax_clear_log'));
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
        
        // Aggressive cache busting: version + file modification time
        $js_file = AMELIA_CPT_SYNC_PLUGIN_DIR . 'assets/js/admin.js';
        $css_file = AMELIA_CPT_SYNC_PLUGIN_DIR . 'assets/css/admin.css';
        
        $js_version = AMELIA_CPT_SYNC_VERSION . '.' . (file_exists($js_file) ? filemtime($js_file) : time());
        $css_version = AMELIA_CPT_SYNC_VERSION . '.' . (file_exists($css_file) ? filemtime($css_file) : time());
        
        wp_enqueue_style(
            'amelia-cpt-sync-admin',
            AMELIA_CPT_SYNC_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            $css_version
        );
        
        wp_enqueue_script(
            'amelia-cpt-sync-admin',
            AMELIA_CPT_SYNC_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            $js_version,
            true
        );
        
        // Pass AJAX URL and nonce to JavaScript
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
     * Get settings from JSON file with cache busting
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
        
        // Clear file status cache to prevent stale file info
        clearstatcache(true, $this->settings_file);
        
        // Check if settings file exists
        if (!file_exists($this->settings_file)) {
            // Create default settings file
            $this->save_settings($defaults);
            return $defaults;
        }
        
        // Clear opcache if available (prevents cached file contents)
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($this->settings_file, true);
        }
        
        // Read settings from JSON file
        $json_content = file_get_contents($this->settings_file);
        $saved_settings = json_decode($json_content, true);
        
        // If JSON is invalid, return defaults
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $defaults;
        }
        
        // Merge with defaults to ensure all keys exist
        return array_replace_recursive($defaults, $saved_settings);
    }
    
    /**
     * Save settings to JSON file with cache busting
     *
     * @param array $settings The settings array to save
     * @return bool True on success, false on failure
     */
    private function save_settings($settings) {
        amelia_cpt_sync_debug_log('>>> Inside save_settings()');
        amelia_cpt_sync_debug_log('Received settings param: ' . print_r($settings, true));
        amelia_cpt_sync_debug_log('Settings [debug_enabled] type: ' . gettype($settings['debug_enabled']));
        amelia_cpt_sync_debug_log('Settings [debug_enabled] value: ' . var_export($settings['debug_enabled'], true));
        
        // Convert to pretty JSON for readability
        $json_content = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        amelia_cpt_sync_debug_log('JSON to write:');
        amelia_cpt_sync_debug_log($json_content);
        
        // Check JSON encoding errors
        if (json_last_error() !== JSON_ERROR_NONE) {
            amelia_cpt_sync_debug_log('ERROR: JSON encoding failed: ' . json_last_error_msg());
            return false;
        }
        
        // Check file permissions before writing
        $file_exists = file_exists($this->settings_file);
        $can_write = $file_exists ? is_writable($this->settings_file) : is_writable(dirname($this->settings_file));
        
        amelia_cpt_sync_debug_log('File exists: ' . ($file_exists ? 'YES' : 'NO'));
        amelia_cpt_sync_debug_log('Can write: ' . ($can_write ? 'YES' : 'NO'));
        
        if (!$can_write) {
            amelia_cpt_sync_debug_log('ERROR: File/directory not writable!');
            return false;
        }
        
        // Write to file with LOCK_EX to prevent concurrent writes
        $result = file_put_contents($this->settings_file, $json_content, LOCK_EX);
        amelia_cpt_sync_debug_log('file_put_contents() result: ' . ($result !== false ? $result . ' bytes written' : 'FAILED'));
        
        if ($result !== false) {
            // Clear file status cache after write
            clearstatcache(true, $this->settings_file);
            
            // Clear opcache if available to ensure fresh reads
            if (function_exists('opcache_invalidate')) {
                opcache_invalidate($this->settings_file, true);
            }
            
            // Force filesystem sync (if on Linux/Unix)
            if (function_exists('sync')) {
                sync();
            }
            
            amelia_cpt_sync_debug_log('SUCCESS: Settings saved to ' . $this->settings_file);
            return true;
        } else {
            amelia_cpt_sync_debug_log('ERROR: Failed to write to ' . $this->settings_file);
            $error = error_get_last();
            if ($error) {
                amelia_cpt_sync_debug_log('Last PHP error: ' . print_r($error, true));
            }
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
     * AJAX handler to save settings
     */
    public function ajax_save_settings() {
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
        
        amelia_cpt_sync_debug_log('========== END AJAX SAVE SETTINGS ==========');
        
        wp_send_json_success(array(
            'message' => 'Settings saved successfully!',
            'debug' => array(
                'saved' => $save_result,
                'verified' => $verify_success,
                'file_path' => $this->settings_file,
                'settings' => $settings
            )
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
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        // This is handled by AJAX, but keep for compatibility
        return $input;
    }
}

