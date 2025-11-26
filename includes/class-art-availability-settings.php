<?php
/**
 * ART Availability Engine Settings
 *
 * Manages settings for the Availability Engine
 *
 * @package AmeliaCPTSync
 * @subpackage ART
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class Amelia_CPT_Sync_ART_Availability_Settings {
    
    /**
     * Option name for settings
     */
    const OPTION_NAME = 'art_availability_settings';
    
    /**
     * Default settings
     */
    private $defaults = array(
        'working_hours_mode' => 'soft',
        'buffer_time_mode' => 'soft',
        'service_schedule_mode' => 'soft',
        'location_mode' => 'ignore',
        'show_location_selector' => false,
        'check_resources' => false,
        'check_approved_appointments' => true,
        'check_pending_appointments' => true,
    );
    
    /**
     * Initialize - register menu and AJAX handlers
     */
    public function init() {
        add_action('admin_menu', array($this, 'add_admin_menu'), 25);
        add_action('wp_ajax_art_save_availability_settings', array($this, 'ajax_save_settings'));
    }
    
    /**
     * Add menu item under Amelia CPT Sync
     */
    public function add_admin_menu() {
        add_submenu_page(
            'amelia-cpt-sync',
            __('Availability Engine', 'amelia-cpt-sync'),
            __('Availability Engine', 'amelia-cpt-sync'),
            'manage_options',
            'art-availability-settings',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Get settings with defaults
     *
     * @return array Settings array
     */
    public function get_settings() {
        $saved = get_option(self::OPTION_NAME, array());
        return array_merge($this->defaults, $saved);
    }
    
    /**
     * Save settings
     *
     * @param array $settings Settings to save
     * @return bool Success
     */
    public function save_settings($settings) {
        return update_option(self::OPTION_NAME, $settings);
    }
    
    /**
     * AJAX handler for saving settings
     */
    public function ajax_save_settings() {
        check_ajax_referer('art_availability_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'amelia-cpt-sync')));
        }
        
        // Validate mode values
        $valid_modes = array('strict', 'soft', 'ignore');
        
        $settings = array(
            'working_hours_mode' => in_array($_POST['working_hours_mode'] ?? '', $valid_modes, true) 
                ? sanitize_text_field($_POST['working_hours_mode']) : 'soft',
            'buffer_time_mode' => in_array($_POST['buffer_time_mode'] ?? '', $valid_modes, true) 
                ? sanitize_text_field($_POST['buffer_time_mode']) : 'soft',
            'service_schedule_mode' => in_array($_POST['service_schedule_mode'] ?? '', $valid_modes, true) 
                ? sanitize_text_field($_POST['service_schedule_mode']) : 'soft',
            'location_mode' => in_array($_POST['location_mode'] ?? '', $valid_modes, true) 
                ? sanitize_text_field($_POST['location_mode']) : 'ignore',
            'show_location_selector' => !empty($_POST['show_location_selector']),
            'check_resources' => !empty($_POST['check_resources']),
            'check_approved_appointments' => !empty($_POST['check_approved_appointments']),
            'check_pending_appointments' => !empty($_POST['check_pending_appointments']),
        );
        
        $this->save_settings($settings);
        
        amelia_cpt_sync_debug_log('Availability Engine: Settings saved', $settings);
        
        wp_send_json_success(array('message' => __('Settings saved successfully', 'amelia-cpt-sync')));
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        include AMELIA_CPT_SYNC_PLUGIN_DIR . 'templates/art-availability-settings-page.php';
    }
}

