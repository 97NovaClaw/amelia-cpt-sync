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
        $config_manager = new Amelia_CPT_Sync_Popup_Config_Manager();
        $configurations = $config_manager->get_configurations();
        $tracked_popups = array_values($config_manager->get_tracked_popups());

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
            'nonce' => wp_create_nonce('amelia_popup_nonce'),
            'trackedPopups' => $tracked_popups,
            'debug_enabled' => !empty($configurations['global']['debug_enabled']),
            'log_nonce' => wp_create_nonce('amelia_cpt_sync_nonce'),
            'default_popup' => isset($configurations['global']['default_popup_id']) ? $configurations['global']['default_popup_id'] : ''
        ));
    }
    
    /**
     * AJAX handler to render Amelia booking form
     */
    public function ajax_render_booking_form() {
        check_ajax_referer('amelia_popup_nonce', 'nonce');
        
        $raw_shortcode = isset($_POST['shortcode']) ? wp_unslash($_POST['shortcode']) : '';
        $popup_id = isset($_POST['popup_id']) ? sanitize_text_field(wp_unslash($_POST['popup_id'])) : '';
        
        amelia_cpt_sync_debug_log('========== AJAX RENDER BOOKING FORM ==========');
        amelia_cpt_sync_debug_log('Popup ID: ' . ($popup_id ? $popup_id : 'n/a'));

        $shortcode = trim($raw_shortcode);

        if (empty($shortcode)) {
            amelia_cpt_sync_debug_log('ERROR: Empty shortcode provided');
            wp_send_json_error(array('message' => __('No shortcode provided.', 'amelia-cpt-sync')));
        }

        if (strlen($shortcode) > 2000) {
            amelia_cpt_sync_debug_log('ERROR: Shortcode too long (' . strlen($shortcode) . ' chars)');
            wp_send_json_error(array('message' => __('Shortcode is too long.', 'amelia-cpt-sync')));
        }

        $normalized = ltrim($shortcode);

        if (stripos($normalized, '[amelia') !== 0) {
            amelia_cpt_sync_debug_log('ERROR: Shortcode does not start with [amelia');
            wp_send_json_error(array('message' => __('Invalid shortcode.', 'amelia-cpt-sync')));
        }

        // Basic sanitation – strip control characters
        $shortcode = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $shortcode);

        amelia_cpt_sync_debug_log('Processing shortcode: ' . $shortcode);
        
        // Force Amelia to initialize if not already loaded
        if (class_exists('AmeliaBooking\Plugin')) {
            amelia_cpt_sync_debug_log('Amelia plugin class found, attempting initialization');
            
            // Trigger any deferred hooks Amelia might be waiting for
            do_action('wp');
            do_action('wp_loaded');
        } else {
            amelia_cpt_sync_debug_log('WARNING: Amelia plugin class not found');
        }
        
        // Check if Amelia shortcodes are registered
        global $shortcode_tags;
        $amelia_shortcodes_registered = array();
        foreach ($shortcode_tags as $tag => $handler) {
            if (stripos($tag, 'amelia') !== false) {
                $amelia_shortcodes_registered[] = $tag;
            }
        }
        amelia_cpt_sync_debug_log('Registered Amelia shortcodes: ' . implode(', ', $amelia_shortcodes_registered));
        
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
            'popup_id' => $popup_id
        ));
    }
}

