<?php
/**
 * Popup Handler Class
 *
 * Handles AJAX rendering of Amelia booking forms for dynamic popups
 *
 * @package AmeliaCPTSync
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class Amelia_CPT_Sync_Popup_Handler {
    
    /**
     * Initialize the class
     */
    public function init() {
        // AJAX hooks for logged-in and non-logged-in users
        add_action('wp_ajax_amelia_render_booking_form', array($this, 'ajax_render_booking_form'));
        add_action('wp_ajax_nopriv_amelia_render_booking_form', array($this, 'ajax_render_booking_form'));
        
        // Enqueue frontend script
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
    }
    
    /**
     * Enqueue frontend scripts
     */
    public function enqueue_frontend_scripts() {
        wp_enqueue_script(
            'amelia-popup-frontend',
            AMELIA_CPT_SYNC_PLUGIN_URL . 'assets/js/popup-frontend.js',
            array('jquery'),
            AMELIA_CPT_SYNC_VERSION,
            true
        );
        
        wp_enqueue_style(
            'amelia-popup-styles',
            AMELIA_CPT_SYNC_PLUGIN_URL . 'assets/css/popup-styles.css',
            array(),
            AMELIA_CPT_SYNC_VERSION
        );
        
        wp_localize_script('amelia-popup-frontend', 'ameliaPopupConfig', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('amelia_popup_nonce')
        ));
    }
    
    /**
     * AJAX handler to render Amelia booking form
     */
    public function ajax_render_booking_form() {
        check_ajax_referer('amelia_popup_nonce', 'nonce');
        
        $type = isset($_POST['type']) ? sanitize_key($_POST['type']) : '';
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        amelia_cpt_sync_debug_log('========== AJAX RENDER BOOKING FORM ==========');
        amelia_cpt_sync_debug_log("Received: type={$type}, id={$id}");
        
        // Validate type
        $allowed_types = array('service', 'category', 'employee', 'event', 'package', 'location');
        
        // Check if it's a custom type
        $custom_types = $this->get_custom_amelia_types();
        $allowed_types = array_merge($allowed_types, $custom_types);
        
        if (empty($type)) {
            amelia_cpt_sync_debug_log('ERROR: No type provided');
            wp_send_json_error(array('message' => 'No booking type specified'));
        }
        
        if (!in_array($type, $allowed_types)) {
            amelia_cpt_sync_debug_log("ERROR: Invalid type: {$type}");
            wp_send_json_error(array('message' => 'Invalid booking type'));
        }
        
        // Validate ID
        if ($id < 1) {
            amelia_cpt_sync_debug_log("ERROR: Invalid ID: {$id}");
            wp_send_json_error(array('message' => 'Invalid ID provided'));
        }
        
        // Build Amelia shortcode
        $shortcode = "[ameliabooking {$type}='{$id}']";
        amelia_cpt_sync_debug_log("Building shortcode: {$shortcode}");
        
        // Render the shortcode
        $rendered_html = do_shortcode($shortcode);
        
        // Check if shortcode was actually processed
        if (empty($rendered_html) || $rendered_html === $shortcode) {
            amelia_cpt_sync_debug_log('ERROR: Shortcode failed to render or returned unchanged');
            amelia_cpt_sync_debug_log('Rendered output: ' . substr($rendered_html, 0, 200));
            wp_send_json_error(array('message' => 'Unable to load booking form. Please try again.'));
        }
        
        amelia_cpt_sync_debug_log('SUCCESS: Shortcode rendered (' . strlen($rendered_html) . ' bytes)');
        amelia_cpt_sync_debug_log('========== END RENDER BOOKING FORM ==========');
        
        wp_send_json_success(array(
            'html' => $rendered_html,
            'shortcode' => $shortcode,
            'type' => $type,
            'id' => $id
        ));
    }
    
    /**
     * Get custom Amelia types from configurations
     */
    private function get_custom_amelia_types() {
        $configs = get_option('amelia_popup_configurations', array());
        $custom_types = array();
        
        if (isset($configs['configs']) && is_array($configs['configs'])) {
            foreach ($configs['configs'] as $config) {
                if (isset($config['amelia_type']) && 
                    strpos($config['amelia_type'], 'custom:') === 0) {
                    $custom_type = str_replace('custom:', '', $config['amelia_type']);
                    $custom_types[] = $custom_type;
                }
            }
        }
        
        return array_unique($custom_types);
    }
}

