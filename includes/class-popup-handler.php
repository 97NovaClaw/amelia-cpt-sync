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
        
        // Parse shortcode to extract type and parameters
        preg_match('/\[([a-zA-Z0-9]+)\s+([^\]]+)\]/', $shortcode, $matches);
        
        if (empty($matches)) {
            amelia_cpt_sync_debug_log('ERROR: Could not parse shortcode format');
            wp_send_json_error(array('message' => __('Invalid shortcode format.', 'amelia-cpt-sync')));
        }
        
        $shortcode_tag = $matches[1]; // e.g., "ameliastepbooking"
        $shortcode_attrs_string = $matches[2]; // e.g., "service=7"
        
        // Parse attributes
        $attributes = shortcode_parse_atts($shortcode_attrs_string);
        amelia_cpt_sync_debug_log('Parsed shortcode: tag=' . $shortcode_tag . ', attrs=' . print_r($attributes, true));
        
        // Call Amelia's shortcode handler directly
        $rendered_html = '';
        
        try {
            // Map shortcode tags to their handler classes
            $shortcode_handlers = array(
                'ameliastepbooking' => 'AmeliaBooking\Infrastructure\WP\ShortcodeService\StepBookingShortcodeService',
                'ameliabooking' => 'AmeliaBooking\Infrastructure\WP\ShortcodeService\BookingShortcodeService',
                'ameliacatalogbooking' => 'AmeliaBooking\Infrastructure\WP\ShortcodeService\CatalogBookingShortcodeService',
                'ameliacatalog' => 'AmeliaBooking\Infrastructure\WP\ShortcodeService\CatalogShortcodeService',
                'ameliaevents' => 'AmeliaBooking\Infrastructure\WP\ShortcodeService\EventsShortcodeService',
                'ameliaeventscalendarbooking' => 'AmeliaBooking\Infrastructure\WP\ShortcodeService\EventsCalendarBookingShortcodeService',
                'ameliaeventslistbooking' => 'AmeliaBooking\Infrastructure\WP\ShortcodeService\EventsListBookingShortcodeService',
                'ameliasearch' => 'AmeliaBooking\Infrastructure\WP\ShortcodeService\SearchShortcodeService',
                'ameliacustomer' => 'AmeliaBooking\Infrastructure\WP\ShortcodeService\CabinetCustomerShortcodeService',
                'ameliaemployee' => 'AmeliaBooking\Infrastructure\WP\ShortcodeService\CabinetEmployeeShortcodeService',
            );
            
            if (!isset($shortcode_handlers[$shortcode_tag])) {
                amelia_cpt_sync_debug_log('ERROR: Unknown Amelia shortcode type: ' . $shortcode_tag);
                wp_send_json_error(array('message' => __('Unsupported shortcode type.', 'amelia-cpt-sync')));
            }
            
            $handler_class = $shortcode_handlers[$shortcode_tag];
            
            if (!class_exists($handler_class)) {
                amelia_cpt_sync_debug_log('ERROR: Handler class not found: ' . $handler_class);
                wp_send_json_error(array('message' => __('Amelia booking plugin not properly loaded.', 'amelia-cpt-sync')));
            }
            
            amelia_cpt_sync_debug_log('Calling Amelia handler: ' . $handler_class);
            $rendered_html = call_user_func(array($handler_class, 'shortcodeHandler'), $attributes);
            
        } catch (\Exception $e) {
            amelia_cpt_sync_debug_log('ERROR rendering shortcode: ' . $e->getMessage());
            wp_send_json_error(array('message' => __('Error rendering booking form.', 'amelia-cpt-sync')));
        }
        
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

