<?php
/**
 * ART Admin Settings Class
 *
 * Handles the admin settings page for the ART (Amelia Request Triage) module
 * Minimal implementation for Phase 1 - Global API settings only
 *
 * @package AmeliaCPTSync
 * @subpackage ART
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class Amelia_CPT_Sync_ART_Admin_Settings {
    
    /**
     * Settings option name
     */
    private $option_name = 'art_settings';
    
    /**
     * Initialize the class
     */
    public function init() {
        add_action('admin_menu', array($this, 'add_admin_menu'), 20);
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_art_save_settings', array($this, 'ajax_save_settings'));
        add_action('wp_ajax_art_clear_cache', array($this, 'ajax_clear_cache'));
        add_action('wp_ajax_art_save_per_page', array($this, 'ajax_save_per_page'));
        add_action('wp_ajax_art_save_calendar_zoom', array($this, 'ajax_save_calendar_zoom'));
        
        // Phase 4: Detail view AJAX handlers
        add_action('wp_ajax_art_update_status', array($this, 'ajax_update_status'));
        add_action('wp_ajax_art_update_follow_up', array($this, 'ajax_update_follow_up'));
        add_action('wp_ajax_art_save_pillars', array($this, 'ajax_save_pillars'));
        add_action('wp_ajax_art_check_customer_match', array($this, 'ajax_check_customer_match'));
        add_action('wp_ajax_art_find_customer_matches', array($this, 'ajax_find_customer_matches')); // NEW: Fuzzy matching
        add_action('wp_ajax_art_get_locations', array($this, 'ajax_get_locations'));
        add_action('wp_ajax_art_get_service_employees', array($this, 'ajax_get_service_employees'));
        
        // Phase 5: API integration
        add_action('wp_ajax_art_get_service_duration', array($this, 'ajax_get_service_duration'));
        add_action('wp_ajax_art_check_availability', array($this, 'ajax_check_availability'));
        add_action('wp_ajax_art_create_booking', array($this, 'ajax_create_booking'));
        
        // Phase 5B: Availability Engine
        add_action('wp_ajax_art_check_provider_availability', array($this, 'ajax_check_provider_availability'));
        
        // Booking management
        add_action('wp_ajax_art_manage_booking', array($this, 'ajax_manage_booking')); // NEW: Unified endpoint
        add_action('wp_ajax_art_reschedule_booking', array($this, 'ajax_reschedule_booking'));
        add_action('wp_ajax_art_delete_booking', array($this, 'ajax_delete_booking'));
        
        // Notes System
        add_action('wp_ajax_art_add_note', array($this, 'ajax_add_note'));
        add_action('wp_ajax_art_update_note', array($this, 'ajax_update_note'));
        add_action('wp_ajax_art_delete_note', array($this, 'ajax_delete_note'));
        add_action('wp_ajax_art_get_notes', array($this, 'ajax_get_notes'));
        add_action('wp_ajax_art_log_customer_match', array($this, 'ajax_log_customer_match'));
    }
    
    /**
     * Add admin menu pages
     */
    public function add_admin_menu() {
        // Add submenu: Triage Requests (Workbench) - PRIMARY
        add_submenu_page(
            'amelia-cpt-sync',
            __('Triage Requests', 'amelia-cpt-sync'),
            __('Triage Requests', 'amelia-cpt-sync'),
            'manage_options',
            'art-workbench',
            array($this, 'render_workbench_page')
        );
        
        // Add submenu: Triage Forms
        add_submenu_page(
            'amelia-cpt-sync',
            __('Triage Forms', 'amelia-cpt-sync'),
            __('Triage Forms', 'amelia-cpt-sync'),
            'manage_options',
            'art-triage-forms',
            array($this, 'render_triage_forms_page')
        );
        
        // Add submenu: ART Settings
        add_submenu_page(
            'amelia-cpt-sync',
            __('ART Settings', 'amelia-cpt-sync'),
            __('ART Settings', 'amelia-cpt-sync'),
            'manage_options',
            'art-settings',
            array($this, 'render_settings_page')
        );
        
        // Add hidden submenu: Request Detail (accessed via link, not menu)
        add_submenu_page(
            null,  // No parent = hidden from menu
            __('Request Detail', 'amelia-cpt-sync'),
            __('Request Detail', 'amelia-cpt-sync'),
            'manage_options',
            'art-request-detail',
            array($this, 'render_request_detail_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting(
            'art_settings_group',
            $this->option_name,
            array($this, 'sanitize_settings')
        );
    }
    
    /**
     * Get settings with defaults
     *
     * @return array Settings array
     */
    public function get_settings() {
        $defaults = array(
            'global' => array(
                'api_key' => '',
                'api_base_url' => site_url() . '/wp-admin/admin-ajax.php?action=wpamelia_api&call=/api/v1',
                'debug_enabled' => false,
                'enable_caching' => true,
                'cache_duration' => 60,
                'show_location_field' => true,
                'show_persons_field' => true,
                'show_timeslots_grid' => false, // Off by default - use Custom Time instead
                'duration_interval_minutes' => 30,
                'duration_max_hours' => 12
            ),
            'forms' => array()
        );
        
        $saved = get_option($this->option_name, array());
        
        return array_replace_recursive($defaults, $saved);
    }
    
    /**
     * Sanitize settings before saving
     *
     * @param array $input Raw input data
     * @return array Sanitized data
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        // Sanitize global settings
        if (isset($input['global'])) {
            $sanitized['global'] = array(
                'api_key' => sanitize_text_field($input['global']['api_key']),
                'api_base_url' => esc_url_raw($input['global']['api_base_url']),
                'debug_enabled' => !empty($input['global']['debug_enabled']),
                'enable_caching' => !empty($input['global']['enable_caching']),
                'cache_duration' => absint($input['global']['cache_duration']),
                'show_location_field' => !empty($input['global']['show_location_field']),
                'show_persons_field' => !empty($input['global']['show_persons_field']),
                'show_timeslots_grid' => !empty($input['global']['show_timeslots_grid']),
                'duration_interval_minutes' => absint($input['global']['duration_interval_minutes']),
                'duration_max_hours' => absint($input['global']['duration_max_hours'])
            );
        }
        
        // Preserve form configurations
        if (isset($input['forms'])) {
            $sanitized['forms'] = $input['forms'];
        }
        
        return $sanitized;
    }
    
    /**
     * AJAX handler for saving settings
     */
    public function ajax_save_settings() {
        check_ajax_referer('art_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $api_key = sanitize_text_field($_POST['api_key'] ?? '');
        $api_base_url = esc_url_raw($_POST['api_base_url'] ?? '');
        $debug_enabled = !empty($_POST['debug_enabled']);
        $enable_caching = !empty($_POST['enable_caching']);
        $cache_duration = absint($_POST['cache_duration'] ?? 60);
        $show_location_field = !empty($_POST['show_location_field']);
        $show_persons_field = !empty($_POST['show_persons_field']);
        $show_timeslots_grid = !empty($_POST['show_timeslots_grid']);
        $duration_interval = absint($_POST['duration_interval_minutes'] ?? 30);
        $duration_max = absint($_POST['duration_max_hours'] ?? 12);
        
        $settings = $this->get_settings();
        
        $settings['global']['api_key'] = $api_key;
        $settings['global']['api_base_url'] = $api_base_url;
        $settings['global']['debug_enabled'] = $debug_enabled;
        $settings['global']['enable_caching'] = $enable_caching;
        $settings['global']['cache_duration'] = $cache_duration;
        $settings['global']['show_location_field'] = $show_location_field;
        $settings['global']['show_persons_field'] = $show_persons_field;
        $settings['global']['show_timeslots_grid'] = $show_timeslots_grid;
        $settings['global']['duration_interval_minutes'] = $duration_interval;
        $settings['global']['duration_max_hours'] = $duration_max;
        
        $result = update_option($this->option_name, $settings);
        
        if ($result || get_option($this->option_name) === $settings) {
            amelia_cpt_sync_debug_log('ART Settings: Global settings saved successfully');
            wp_send_json_success(array(
                'message' => 'Settings saved successfully'
            ));
        } else {
            amelia_cpt_sync_debug_log('ART Settings: Failed to save global settings');
            wp_send_json_error(array(
                'message' => 'Failed to save settings'
            ));
        }
    }
    
    /**
     * AJAX handler for clearing cache
     */
    public function ajax_clear_cache() {
        check_ajax_referer('art_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        global $wpdb;
        
        // Delete all ART transients
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_art_%' 
             OR option_name LIKE '_transient_timeout_art_%'"
        );
        
        amelia_cpt_sync_debug_log('ART Settings: Cleared all API caches');
        
        wp_send_json_success(array('message' => 'Cache cleared successfully'));
    }
    
    /**
     * AJAX handler for saving per-page preference
     */
    public function ajax_save_per_page() {
        check_ajax_referer('art_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $per_page = isset($_POST['per_page']) ? absint($_POST['per_page']) : 25;
        
        // Validate value
        if (!in_array($per_page, array(5, 15, 25, 50, 100))) {
            wp_send_json_error(array('message' => 'Invalid per_page value'));
        }
        
        // Save to user meta (per-user preference)
        $user_id = get_current_user_id();
        update_user_meta($user_id, 'art_workbench_per_page', $per_page);
        
        amelia_cpt_sync_debug_log('ART Workbench: User ' . $user_id . ' set per_page to ' . $per_page);
        
        wp_send_json_success(array(
            'message' => 'Preference saved',
            'per_page' => $per_page
        ));
    }
    
    /**
     * AJAX handler for saving calendar zoom preference
     */
    public function ajax_save_calendar_zoom() {
        check_ajax_referer('art_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $zoom = isset($_POST['zoom']) ? intval($_POST['zoom']) : 100;
        
        // Validate zoom value (50% to 150%)
        if ($zoom < 50 || $zoom > 150) {
            $zoom = 100;
        }
        
        // Save to user meta (per-user preference)
        $user_id = get_current_user_id();
        update_user_meta($user_id, 'art_calendar_zoom', $zoom);
        
        wp_send_json_success(array(
            'message' => 'Zoom preference saved',
            'zoom' => $zoom
        ));
    }
    
    /**
     * Render the settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $settings = $this->get_settings();
        $global = $settings['global'];
        
        // Check if database tables exist
        $db_manager = new Amelia_CPT_Sync_ART_Database_Manager();
        $tables_exist = $db_manager->tables_exist();
        $db_version = $db_manager->get_version();
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php if (!$tables_exist): ?>
                <div class="notice notice-error">
                    <p><strong>Database tables are missing!</strong> Please deactivate and reactivate the plugin to create tables.</p>
                </div>
            <?php else: ?>
                <div class="notice notice-success is-dismissible" style="display:none;" id="art-settings-saved">
                    <p>Settings saved successfully!</p>
                </div>
                
                <div class="notice notice-error is-dismissible" style="display:none;" id="art-settings-error">
                    <p>Error saving settings. Please try again.</p>
                </div>
            <?php endif; ?>
            
            <div class="card" style="max-width: 800px; margin-top: 20px;">
                <h2>Global API Settings</h2>
                <p>Configure the Amelia API credentials used by all triage forms.</p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="art-api-base-url">API Base URL</label>
                        </th>
                        <td>
                            <input 
                                type="url" 
                                id="art-api-base-url" 
                                name="api_base_url" 
                                value="<?php echo esc_attr($global['api_base_url']); ?>" 
                                class="regular-text"
                            />
                            <p class="description">
                                Base URL for Amelia API calls. Usually your site URL: <code><?php echo esc_html(site_url()); ?></code>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="art-api-key">API Key</label>
                        </th>
                        <td>
                            <div style="position: relative; display: inline-block; width: 100%; max-width: 400px;">
                                <input 
                                    type="password" 
                                    id="art-api-key" 
                                    name="api_key" 
                                    value="<?php echo esc_attr($global['api_key']); ?>" 
                                    class="regular-text"
                                    autocomplete="off"
                                    style="padding-right: 80px;"
                                />
                                <button 
                                    type="button" 
                                    class="button button-secondary" 
                                    id="art-toggle-api-key"
                                    style="position: absolute; right: 0; top: 0; height: 30px;"
                                >
                                    Show
                                </button>
                            </div>
                            <p class="description">
                                Your Amelia API key. Found in Amelia → Settings → Integrations → API.<br>
                                Header name: <code>Amelia</code>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="art-debug-enabled">Debug Mode</label>
                        </th>
                        <td>
                            <label>
                                <input 
                                    type="checkbox" 
                                    id="art-debug-enabled" 
                                    name="debug_enabled" 
                                    value="1"
                                    <?php checked($global['debug_enabled'], true); ?>
                                />
                                Enable ART debug logging
                            </label>
                            <p class="description">
                                Log ART module activity to the plugin's debug file.<br>
                                Debug log location: <code><?php echo esc_html(AMELIA_CPT_SYNC_PLUGIN_DIR); ?>debug.txt</code>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="art-enable-caching">API Caching</label>
                        </th>
                        <td>
                            <label>
                                <input 
                                    type="checkbox" 
                                    id="art-enable-caching" 
                                    name="enable_caching" 
                                    value="1"
                                    <?php checked($global['enable_caching'], true); ?>
                                />
                                Enable API response caching
                            </label>
                            <p class="description">
                                Cache Amelia API responses (services, locations, providers) for better performance.<br>
                                Disable during development/debugging to always get fresh data.
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="art-cache-duration">Cache Duration</label>
                        </th>
                        <td>
                            <input 
                                type="number" 
                                id="art-cache-duration" 
                                name="cache_duration" 
                                value="<?php echo esc_attr($global['cache_duration']); ?>" 
                                min="1" 
                                max="1440"
                                style="width: 80px;"
                            />
                            minutes
                            <p class="description">
                                How long to cache API responses. Default: 60 minutes (1 hour).
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Detail View Display</th>
                        <td>
                            <label>
                                <input 
                                    type="checkbox" 
                                    name="show_location_field" 
                                    value="1" 
                                    <?php checked($global['show_location_field'] ?? true); ?>
                                />
                                Show Location Field in detail view
                            </label>
                            <br>
                            <label>
                                <input 
                                    type="checkbox" 
                                    name="show_persons_field" 
                                    value="1" 
                                    <?php checked($global['show_persons_field'] ?? true); ?>
                                />
                                Show Persons Field in detail view
                            </label>
                            <br><br>
                            <label>
                                <input 
                                    type="checkbox" 
                                    name="show_timeslots_grid" 
                                    value="1" 
                                    <?php checked($global['show_timeslots_grid'] ?? false); ?>
                                />
                                Show Time Slots Grid (middle column in availability picker)
                                <p class="description" style="margin-left: 24px; margin-top: 4px;">
                                    When disabled, only Date selection and Custom Time entry are shown. 
                                    Use the embedded Amelia calendar for visual reference.
                                </p>
                            </label>
                            <p class="description">
                                Control which fields appear when editing triage requests.<br>
                                Data is still captured and saved if forms submit these fields.<br>
                                Hiding fields improves UI clarity for businesses that don't need them.
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Duration Settings</th>
                        <td>
                            <label>
                                Dropdown Interval:
                                <input 
                                    type="number" 
                                    name="duration_interval_minutes" 
                                    id="art-duration-interval"
                                    value="<?php echo esc_attr($global['duration_interval_minutes'] ?? 30); ?>" 
                                    min="5" 
                                    max="240"
                                    style="width: 80px;"
                                />
                                minutes
                            </label>
                            <p class="description">
                                Duration dropdown increments (e.g., 30 = shows 0:30, 1:00, 1:30, 2:00...)
                            </p>
                            
                            <label>
                                Maximum Duration:
                                <input 
                                    type="number" 
                                    name="duration_max_hours" 
                                    id="art-duration-max"
                                    value="<?php echo esc_attr($global['duration_max_hours'] ?? 12); ?>" 
                                    min="1" 
                                    max="48"
                                    style="width: 80px;"
                                />
                                hours
                            </label>
                            <p class="description">
                                Last option in duration dropdown (e.g., 12 = stops at 12:00)
                            </p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="button" class="button button-primary" id="art-save-settings">
                        Save Global Settings
                    </button>
                    <span class="spinner" id="art-save-spinner" style="float: none; margin: 0 0 0 10px;"></span>
                </p>
                
                <hr>
                
                <h3>Cache Management</h3>
                <p>
                    <button type="button" class="button" id="art-clear-cache">
                        Clear All API Caches
                    </button>
                    <span class="spinner" id="art-cache-spinner" style="float: none; margin: 0 0 0 10px;"></span>
                </p>
                <p class="description">
                    Clears all cached Amelia API data (services, locations, providers). 
                    Use this if data appears outdated or after making changes in Amelia.
                </p>
                <div class="notice notice-success is-dismissible" style="display:none;" id="art-cache-cleared">
                    <p>Cache cleared successfully!</p>
                </div>
            </div>
            
            <div class="card" style="max-width: 800px; margin-top: 20px;">
                <h2>Database Information</h2>
                <table class="widefat">
                    <tr>
                        <td><strong>Database Version:</strong></td>
                        <td><code><?php echo esc_html($db_version); ?></code></td>
                    </tr>
                    <tr>
                        <td><strong>Tables Status:</strong></td>
                        <td>
                            <?php if ($tables_exist): ?>
                                <span style="color: #46b450;">✓ All tables exist</span>
                            <?php else: ?>
                                <span style="color: #dc3232;">✗ Tables missing</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Tables:</strong></td>
                        <td>
                            <code><?php echo esc_html($GLOBALS['wpdb']->prefix); ?>art_customers</code><br>
                            <code><?php echo esc_html($GLOBALS['wpdb']->prefix); ?>art_requests</code><br>
                            <code><?php echo esc_html($GLOBALS['wpdb']->prefix); ?>art_intake_fields</code><br>
                            <code><?php echo esc_html($GLOBALS['wpdb']->prefix); ?>art_booking_links</code><br>
                            <code><?php echo esc_html($GLOBALS['wpdb']->prefix); ?>art_request_notes</code>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="card" style="max-width: 800px; margin-top: 20px; background: #f0f6fc; border-left: 4px solid #0073aa;">
                <h2 style="margin-top: 0;">📋 Coming in Phase 2</h2>
                <p>Form configuration management will be added in Phase 2, allowing you to:</p>
                <ul style="margin-left: 20px;">
                    <li>Configure multiple triage forms (like the popup system)</li>
                    <li>Set up field mappings per form</li>
                    <li>Define intake field definitions</li>
                    <li>Configure form-specific logic (duration mode, price mode, etc.)</li>
                </ul>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Toggle API key visibility
            $('#art-toggle-api-key').on('click', function() {
                var input = $('#art-api-key');
                var button = $(this);
                
                if (input.attr('type') === 'password') {
                    input.attr('type', 'text');
                    button.text('Hide');
                } else {
                    input.attr('type', 'password');
                    button.text('Show');
                }
            });
            
            // Save settings via AJAX
            $('#art-save-settings').on('click', function() {
                var button = $(this);
                var spinner = $('#art-save-spinner');
                
                button.prop('disabled', true);
                spinner.addClass('is-active');
                $('#art-settings-saved, #art-settings-error').hide();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'art_save_settings',
                        nonce: '<?php echo wp_create_nonce('art_nonce'); ?>',
                        api_key: $('#art-api-key').val(),
                        api_base_url: $('#art-api-base-url').val(),
                        debug_enabled: $('#art-debug-enabled').is(':checked') ? 1 : 0,
                        enable_caching: $('#art-enable-caching').is(':checked') ? 1 : 0,
                        cache_duration: $('#art-cache-duration').val(),
                        show_location_field: $('input[name="show_location_field"]').is(':checked') ? 1 : 0,
                        show_persons_field: $('input[name="show_persons_field"]').is(':checked') ? 1 : 0,
                        show_timeslots_grid: $('input[name="show_timeslots_grid"]').is(':checked') ? 1 : 0,
                        duration_interval_minutes: $('#art-duration-interval').val(),
                        duration_max_hours: $('#art-duration-max').val()
                    },
                    success: function(response) {
                        spinner.removeClass('is-active');
                        button.prop('disabled', false);
                        
                        if (response.success) {
                            $('#art-settings-saved').fadeIn();
                            setTimeout(function() {
                                $('#art-settings-saved').fadeOut();
                            }, 3000);
                        } else {
                            $('#art-settings-error').text(response.data.message).fadeIn();
                        }
                    },
                    error: function() {
                        spinner.removeClass('is-active');
                        button.prop('disabled', false);
                        $('#art-settings-error').fadeIn();
                    }
                });
            });
            
            // Clear cache button
            $('#art-clear-cache').on('click', function() {
                var button = $(this);
                var spinner = $('#art-cache-spinner');
                
                button.prop('disabled', true);
                spinner.addClass('is-active');
                $('#art-cache-cleared').hide();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'art_clear_cache',
                        nonce: '<?php echo wp_create_nonce('art_nonce'); ?>'
                    },
                    success: function(response) {
                        spinner.removeClass('is-active');
                        button.prop('disabled', false);
                        
                        if (response.success) {
                            $('#art-cache-cleared').fadeIn();
                            setTimeout(function() {
                                $('#art-cache-cleared').fadeOut();
                            }, 3000);
                        }
                    },
                    error: function() {
                        spinner.removeClass('is-active');
                        button.prop('disabled', false);
                    }
                });
            });
        });
        </script>
        
        <style>
            .card {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                padding: 20px;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
            }
            .card h2 {
                margin-top: 0;
                padding-bottom: 10px;
                border-bottom: 1px solid #eee;
            }
        </style>
        <?php
    }
    
    /**
     * Render the Triage Forms management page
     */
    public function render_workbench_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Include the template
        include AMELIA_CPT_SYNC_PLUGIN_DIR . 'templates/art-workbench-page.php';
    }
    
    public function render_triage_forms_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Include the template
        include AMELIA_CPT_SYNC_PLUGIN_DIR . 'templates/art-triage-forms-page.php';
    }
    
    public function render_request_detail_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Include the template
        include AMELIA_CPT_SYNC_PLUGIN_DIR . 'templates/art-request-detail-page.php';
    }
    
    /**
     * AJAX handler for updating request status
     */
    public function ajax_update_status() {
        check_ajax_referer('art_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $request_id = isset($_POST['request_id']) ? absint($_POST['request_id']) : 0;
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        
        if (!$request_id || !$status) {
            wp_send_json_error(array('message' => 'Missing required parameters'));
        }
        
        $request_manager = new Amelia_CPT_Sync_ART_Request_Manager();
        $result = $request_manager->update_status($request_id, $status);
        
        if ($result) {
            amelia_cpt_sync_debug_log('ART Detail: Updated request ' . $request_id . ' status to ' . $status);
            wp_send_json_success(array('message' => 'Status updated'));
        } else {
            wp_send_json_error(array('message' => 'Failed to update status'));
        }
    }
    
    /**
     * AJAX handler for updating follow-up date
     */
    public function ajax_update_follow_up() {
        check_ajax_referer('art_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $request_id = isset($_POST['request_id']) ? absint($_POST['request_id']) : 0;
        $follow_up_date = isset($_POST['follow_up_date']) ? sanitize_text_field($_POST['follow_up_date']) : '';
        
        if (!$request_id) {
            wp_send_json_error(array('message' => 'Missing request ID'));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'art_requests';
        
        // Convert to UTC for storage
        $follow_up_utc = null;
        if (!empty($follow_up_date)) {
            // Store as-is in local timezone (just for display purposes in ART table)
            $follow_up_utc = date('Y-m-d H:i:s', strtotime($follow_up_date . ' 00:00:00'));
        }
        
        $result = $wpdb->update(
            $table,
            array('follow_up_by' => $follow_up_utc),
            array('id' => $request_id),
            array('%s'),
            array('%d')
        );
        
        if ($result !== false) {
            amelia_cpt_sync_debug_log('ART Detail: Updated request ' . $request_id . ' follow_up_by to ' . $follow_up_date);
            wp_send_json_success(array('message' => 'Follow-up date saved'));
        } else {
            wp_send_json_error(array('message' => 'Failed to save follow-up date'));
        }
    }
    
    /**
     * AJAX handler for saving booking pillars
     */
    public function ajax_save_pillars() {
        check_ajax_referer('art_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $request_id = isset($_POST['request_id']) ? absint($_POST['request_id']) : 0;
        
        if (!$request_id) {
            wp_send_json_error(array('message' => 'Missing request ID'));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'art_requests';
        
        // Prepare data for update
        $data = array();
        
        if (isset($_POST['category_id'])) {
            $data['category_id'] = !empty($_POST['category_id']) ? absint($_POST['category_id']) : null;
        }
        
        if (isset($_POST['service_id'])) {
            $data['service_id'] = !empty($_POST['service_id']) ? absint($_POST['service_id']) : null;
        }
        
        if (isset($_POST['location_id'])) {
            $data['location_id'] = !empty($_POST['location_id']) ? absint($_POST['location_id']) : null;
        }
        
        if (isset($_POST['persons'])) {
            $data['persons'] = absint($_POST['persons']) ?: 1;
        }
        
        if (isset($_POST['start_datetime']) && !empty($_POST['start_datetime'])) {
            // Store in local timezone (for display in ART UI, not sent to Amelia)
            $data['start_datetime'] = date('Y-m-d H:i:s', strtotime($_POST['start_datetime']));
        }
        
        if (isset($_POST['end_datetime']) && !empty($_POST['end_datetime'])) {
            // Store in local timezone (for display in ART UI, not sent to Amelia)
            $data['end_datetime'] = date('Y-m-d H:i:s', strtotime($_POST['end_datetime']));
        }
        
        if (isset($_POST['duration_seconds'])) {
            $data['duration_seconds'] = absint($_POST['duration_seconds']);
        }
        
        if (isset($_POST['final_price'])) {
            $data['final_price'] = !empty($_POST['final_price']) ? floatval($_POST['final_price']) : null;
        }
        
        // Always update last_activity_at
        $data['last_activity_at'] = current_time('mysql', 1);
        
        $result = $wpdb->update(
            $table,
            $data,
            array('id' => $request_id),
            array_fill(0, count($data), '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            amelia_cpt_sync_debug_log('ART Detail: Saved pillars for request ' . $request_id);
            wp_send_json_success(array('message' => 'Booking details saved'));
        } else {
            wp_send_json_error(array('message' => 'Failed to save booking details'));
        }
    }
    
    /**
     * AJAX handler for customer match check (Legacy - kept for backwards compat)
     */
    public function ajax_check_customer_match() {
        check_ajax_referer('art_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        
        if (empty($email) || !is_email($email)) {
            wp_send_json_error(array('message' => 'Invalid email'));
        }
        
        $api_manager = new Amelia_CPT_Sync_ART_API_Manager();
        $customer = $api_manager->find_customer($email);
        
        if ($customer) {
            wp_send_json_success(array('customer' => $customer));
        } else {
            wp_send_json_success(array('customer' => null));
        }
    }
    
    /**
     * AJAX: Find customer matches using fuzzy matching
     */
    public function ajax_find_customer_matches() {
        check_ajax_referer('art_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $matcher = new Amelia_Customer_Matcher();
        
        $results = $matcher->find_matches(array(
            'email' => sanitize_email($_POST['email'] ?? ''),
            'phone' => sanitize_text_field($_POST['phone'] ?? ''),
            'first_name' => sanitize_text_field($_POST['first_name'] ?? ''),
            'last_name' => sanitize_text_field($_POST['last_name'] ?? '')
        ));
        
        wp_send_json_success($results);
    }
    
    /**
     * AJAX handler for getting locations
     */
    public function ajax_get_locations() {
        check_ajax_referer('art_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $api_manager = new Amelia_CPT_Sync_ART_API_Manager();
        $locations = $api_manager->get_locations();
        
        if (is_wp_error($locations)) {
            wp_send_json_error(array(
                'message' => $locations->get_error_message(),
                'locations' => array()
            ));
        } else {
            wp_send_json_success(array('locations' => $locations));
        }
    }
    
    /**
     * AJAX handler for getting employees for a service
     * Phase 5 Enhancement
     */
    public function ajax_get_service_employees() {
        check_ajax_referer('art_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $service_id = absint($_POST['service_id'] ?? 0);
        if (!$service_id) {
            wp_send_json_error(array('message' => 'Service ID required'));
        }
        
        $api_manager = new Amelia_CPT_Sync_ART_API_Manager();
        $employees = $api_manager->get_service_employees($service_id);
        
        if (is_wp_error($employees)) {
            wp_send_json_error(array(
                'message' => $employees->get_error_message(),
                'employees' => array()
            ));
        } else {
            // Map for easier frontend usage: ID -> Name
            $provider_map = array();
            foreach ($employees as $emp) {
                $name = trim(($emp['firstName'] ?? '') . ' ' . ($emp['lastName'] ?? ''));
                if (empty($name)) {
                    $name = 'Provider #' . $emp['id'];
                }
                $provider_map[$emp['id']] = $name;
            }
            
            wp_send_json_success(array(
                'employees' => $employees,
                'provider_map' => $provider_map
            ));
        }
    }
    
    /**
     * AJAX: Get service duration from Amelia API (Phase 5)
     */
    public function ajax_get_service_duration() {
        check_ajax_referer('art_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $service_id = absint($_POST['service_id'] ?? 0);
        
        if (!$service_id) {
            wp_send_json_error(array('message' => 'Service ID is required'));
        }
        
        $api_manager = new Amelia_CPT_Sync_ART_API_Manager();
        $service = $api_manager->get_service($service_id);
        
        if (is_wp_error($service)) {
            wp_send_json_error(array('message' => $service->get_error_message()));
        }
        
        $duration_seconds = $service['duration'] ?? 0;
        
        // Format duration for display
        $hours = floor($duration_seconds / 3600);
        $minutes = floor(($duration_seconds % 3600) / 60);
        
        if ($hours > 0 && $minutes > 0) {
            $display = sprintf('%d hour%s %d min%s', $hours, ($hours > 1 ? 's' : ''), $minutes, ($minutes > 1 ? 's' : ''));
        } elseif ($hours > 0) {
            $display = sprintf('%d hour%s', $hours, ($hours > 1 ? 's' : ''));
        } else {
            $display = sprintf('%d min%s', $minutes, ($minutes > 1 ? 's' : ''));
        }
        
        // Get price information
        $default_price = floatval($service['price'] ?? 0);
        
        wp_send_json_success(array(
            'duration_seconds' => $duration_seconds,
            'duration_display' => $display,
            'service_name' => $service['name'] ?? '',
            'default_price' => $default_price
        ));
    }
    
    /**
     * AJAX: Check availability (Phase 5)
     */
    public function ajax_check_availability() {
        check_ajax_referer('art_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $request_id = absint($_POST['request_id'] ?? 0);
        
        if (!$request_id) {
            wp_send_json_error(array('message' => 'Request ID is required'));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'art_requests';
        
        $request = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $request_id
        ));
        
        if (!$request) {
            wp_send_json_error(array('message' => 'Request not found'));
        }
        
        // Validate required fields for availability check
        if (!$request->service_id) {
            wp_send_json_error(array('message' => 'Service is required to check availability'));
        }
        
        if (!$request->duration_seconds || $request->duration_seconds <= 0) {
            wp_send_json_error(array('message' => 'Duration is required to check availability'));
        }
        
        // Build params for slots API (matching Amelia API docs format)
        // serviceDuration determines both:
        // 1. The minimum time block needed (filters out slots that can't fit)
        // 2. The interval between displayed slots
        // 
        // For long durations (e.g., 5 hours), slots will be spaced 5 hours apart.
        // Users can use "Custom Time" to check specific times not in the grid.
        $params = array(
            'serviceId' => $request->service_id,
            'serviceDuration' => $request->duration_seconds,
            'persons' => $request->persons ?? 1,
            'startDateTime' => !empty($request->start_datetime) ? date('Y-m-d', strtotime($request->start_datetime)) : date('Y-m-d')
        );
        
        // Only add locationId if it's set and > 0 (omit if null/0)
        if (!empty($request->location_id) && $request->location_id > 0) {
            $params['locationId'] = $request->location_id;
        }
        
        $api_manager = new Amelia_CPT_Sync_ART_API_Manager();
        $result = $api_manager->get_slots($params);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        // Process slots for frontend display
        $formatted_slots = array();
        
        // Brief debug log - just summary, not all dates
        $raw_slots = $result['slots'] ?? array();
        amelia_cpt_sync_debug_log('ART Slots: ' . count($raw_slots) . ' dates available');
        
        foreach ($result['slots'] as $date => $times) {
            foreach ($times as $time => $providers) {
                // providers is an array of [providerId, locationId] pairs
                foreach ($providers as $provider_info) {
                    // Skip slots without valid provider
                    if (empty($provider_info[0])) {
                        continue;
                    }
                    
                    $formatted_slots[] = array(
                        'date' => $date,
                        'time' => $time,
                        'datetime' => $date . ' ' . $time,
                        'provider_id' => intval($provider_info[0]),
                        'location_id' => isset($provider_info[1]) ? intval($provider_info[1]) : 0
                    );
                }
            }
        }
        
        amelia_cpt_sync_debug_log('ART Slots: Formatted ' . count($formatted_slots) . ' slots');
        
        wp_send_json_success(array(
            'slots' => $formatted_slots,
            'total' => count($formatted_slots),
            'minimum' => $result['minimum'] ?? '',
            'maximum' => $result['maximum'] ?? ''
        ));
    }
    
    /**
     * AJAX: Create Amelia booking (Phase 5)
     */
    public function ajax_create_booking() {
        check_ajax_referer('art_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $request_id = absint($_POST['request_id'] ?? 0);
        
        if (!$request_id) {
            wp_send_json_error(array('message' => 'Request ID is required'));
        }
        
        global $wpdb;
        $requests_table = $wpdb->prefix . 'art_requests';
        $customers_table = $wpdb->prefix . 'art_customers';
        
        // Get request
        $request = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $requests_table WHERE id = %d",
            $request_id
        ));
        
        if (!$request) {
            wp_send_json_error(array('message' => 'Request not found'));
        }
        
        // Get customer
        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $customers_table WHERE id = %d",
            $request->customer_id
        ));
        
        if (!$customer) {
            wp_send_json_error(array('message' => 'Customer not found'));
        }
        
        // Validate required booking fields
        // Note: location_id is optional - Amelia can handle bookings without a specific location
        $missing = array();
        if (!$request->service_id) $missing[] = 'Service';
        if (empty($customer->email)) $missing[] = 'Customer Email';
        
        if (!empty($missing)) {
            wp_send_json_error(array('message' => 'Missing required fields: ' . implode(', ', $missing)));
        }
        
        // Duration is required but can be calculated if not set
        if (!$request->duration_seconds || $request->duration_seconds <= 0) {
            // Try to get duration from service
            $api_manager = new Amelia_CPT_Sync_ART_API_Manager();
            $service = $api_manager->get_service($request->service_id);
            if (!is_wp_error($service) && !empty($service['duration'])) {
                $request->duration_seconds = intval($service['duration']);
            } else {
                wp_send_json_error(array('message' => 'Duration is required. Please set a duration in the Time & Duration section.'));
            }
        }
        
        // Get selected provider and slot from POST (user selected from availability results)
        $selected_provider_id = absint($_POST['provider_id'] ?? 0);
        $selected_slot_datetime = sanitize_text_field($_POST['slot_datetime'] ?? '');
        $booking_type = sanitize_key($_POST['booking_type'] ?? 'confirmed'); // 'tentative' or 'confirmed'
        
        if (!$selected_provider_id) {
            wp_send_json_error(array('message' => 'Please select a provider from availability results'));
        }
        
        if (!$selected_slot_datetime) {
            wp_send_json_error(array('message' => 'Please select a time slot'));
        }
        
        // Determine Amelia status based on booking type
        // Tentative = 'pending', Confirmed = 'approved'
        $amelia_status = ($booking_type === 'tentative') ? 'pending' : 'approved';
        
        // Build booking data in EXACT format from Amelia API docs
        $booking_object = array(
            'extras' => array(),
            'customFields' => (object) array(),  // Empty object, not array
            'deposit' => false,
            'locale' => 'en_US',
            'utcOffset' => null,
            'persons' => absint($request->persons ?? 1),
            'customerId' => !empty($customer->amelia_customer_id) ? absint($customer->amelia_customer_id) : null,
            'customer' => array(
                'id' => !empty($customer->amelia_customer_id) ? absint($customer->amelia_customer_id) : null,
                'firstName' => $customer->first_name,
                'lastName' => $customer->last_name,
                'email' => $customer->email,
                'phone' => $customer->phone ?? '',
                'countryPhoneIso' => '',
                'externalId' => null
            ),
            'duration' => absint($request->duration_seconds),
            'status' => $amelia_status
        );
        
        // Add price if available
        if ($request->final_price !== null) {
            $booking_object['price'] = floatval($request->final_price);
        }
        
        // Build complete booking payload
        $booking_data = array(
            'type' => 'appointment',
            'bookings' => array($booking_object),
            'payment' => array(
                'gateway' => 'onSite',
                'currency' => 'USD',
                'data' => (object) array()
            ),
            'bookingStart' => $selected_slot_datetime,  // Pass as-is, booking service will convert to UTC
            'notifyParticipants' => 1,
            'providerId' => $selected_provider_id,
            'serviceId' => absint($request->service_id)
        );
        
        // Only add locationId if it's set (Amelia can handle bookings without location)
        if (!empty($request->location_id) && $request->location_id > 0) {
            $booking_data['locationId'] = absint($request->location_id);
        }
        
        $booking_service = new Amelia_CPT_Sync_ART_Booking_Service();
        $result = $booking_service->create_booking($booking_data);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => 'Booking failed: ' . $result->get_error_message()));
        }
        
        // Extract booking IDs from response
        // According to Amelia API docs:
        // - data.appointment.id = appointment ID
        // - data.appointment.bookings[0].id = booking ID
        $appointment_id = $result['data']['appointment']['id'] ?? null;
        $booking_id = null;
        if (isset($result['data']['appointment']['bookings'][0]['id'])) {
            $booking_id = $result['data']['appointment']['bookings'][0]['id'];
        }
        $amelia_customer_id = $result['data']['appointment']['bookings'][0]['customer']['id'] ?? 
                              $result['data']['customer']['id'] ?? null;
        
        // Update request status to 'booked'
        $wpdb->update(
            $requests_table,
            array(
                'status_key' => 'booked',
                'booked_at' => current_time('mysql', 1)
            ),
            array('id' => $request_id),
            array('%s', '%s'),
            array('%d')
        );
        
        // Store the booking link in art_booking_links table
        $booking_links_table = $wpdb->prefix . 'art_booking_links';
        
        // First, delete any existing booking links for this request
        $wpdb->delete($booking_links_table, array('request_id' => $request_id), array('%d'));
        
        // Insert new booking link with booking type
        $wpdb->insert(
            $booking_links_table,
            array(
                'request_id' => $request_id,
                'amelia_booking_id' => $booking_id,
                'amelia_appointment_id' => $appointment_id,
                'booking_type' => $booking_type
            ),
            array('%d', '%d', '%d', '%s')
        );
        
        // Update customer with Amelia customer ID if available
        if ($amelia_customer_id && !$customer->amelia_customer_id) {
            $wpdb->update(
                $customers_table,
                array('amelia_customer_id' => $amelia_customer_id),
                array('id' => $customer->id),
                array('%d'),
                array('%d')
            );
        }
        
        amelia_cpt_sync_debug_log('ART: Successfully created Amelia booking #' . $booking_id . ' for request #' . $request_id);
        
        // Get service and category names from Amelia API
        $api_manager_for_names = new Amelia_CPT_Sync_ART_API_Manager();
        
        $service_name = '';
        $category_name = '';
        
        // Get service details (includes category)
        $service_details = $api_manager_for_names->get_service($request->service_id);
        if (!is_wp_error($service_details) && !empty($service_details['name'])) {
            $service_name = $service_details['name'];
            
            // Try to get category from service
            if (!empty($service_details['categoryId'])) {
                $categories = $api_manager_for_names->get_categories();
                if (!is_wp_error($categories)) {
                    foreach ($categories as $cat) {
                        if (intval($cat['id']) === intval($service_details['categoryId'])) {
                            $category_name = $cat['name'];
                            break;
                        }
                    }
                }
            }
        }
        
        // Get provider name from the selected provider
        $provider_name = '';
        $providers = $api_manager_for_names->get_service_employees($request->service_id);
        if (!is_wp_error($providers)) {
            foreach ($providers as $p) {
                if (intval($p['id']) === intval($selected_provider_id)) {
                    $provider_name = trim(($p['firstName'] ?? '') . ' ' . ($p['lastName'] ?? ''));
                    break;
                }
            }
        }
        
        // Get location name
        $location_name = '';
        if ($request->location_id) {
            $locations = $api_manager_for_names->get_locations();
            if (!is_wp_error($locations)) {
                foreach ($locations as $loc) {
                    if (intval($loc['id']) === intval($request->location_id)) {
                        $location_name = $loc['name'];
                        break;
                    }
                }
            }
        }
        
        // Format datetime for human-readable display
        $booking_timestamp = strtotime($selected_slot_datetime);
        $formatted_date = date('l, F jS, Y', $booking_timestamp); // e.g., "Friday, November 17th, 2025"
        $formatted_time = date('h:i A', $booking_timestamp); // e.g., "02:00 AM"
        
        // Fetch actual booked times from Amelia (in UTC) for pillar field updates
        $booked_start_local = '';
        $booked_end_local = '';
        $booked_duration_seconds = 0;
        
        if ($appointment_id) {
            $appointment_data = $wpdb->get_row($wpdb->prepare(
                "SELECT bookingStart, bookingEnd FROM {$wpdb->prefix}amelia_appointments WHERE id = %d",
                $appointment_id
            ));
            
            if ($appointment_data) {
                // Convert UTC to WordPress timezone for display
                $wp_tz = wp_timezone();
                $utc_tz = new DateTimeZone('UTC');
                
                $dt_start = new DateTime($appointment_data->bookingStart, $utc_tz);
                $dt_start->setTimezone($wp_tz);
                
                $dt_end = new DateTime($appointment_data->bookingEnd, $utc_tz);
                $dt_end->setTimezone($wp_tz);
                
                $booked_start_local = $dt_start->format('Y-m-d\TH:i');
                $booked_end_local = $dt_end->format('Y-m-d\TH:i');
                $booked_duration_seconds = strtotime($appointment_data->bookingEnd) - strtotime($appointment_data->bookingStart);
            }
        }
        
        $message = ($booking_type === 'tentative') 
            ? 'Tentative booking created successfully!' 
            : 'Booking confirmed successfully!';
        
        wp_send_json_success(array(
            'message' => $message,
            'booking_id' => $booking_id,
            'appointment_id' => $appointment_id,
            'amelia_customer_id' => $amelia_customer_id,
            'booking_type' => $booking_type,
            'service_name' => $service_name,
            'category_name' => $category_name,
            'provider_name' => $provider_name,
            'location_name' => $location_name,
            'formatted_date' => $formatted_date,
            'formatted_time' => $formatted_time,
            'raw_datetime' => $selected_slot_datetime,
            'booked_start_local' => $booked_start_local,
            'booked_end_local' => $booked_end_local,
            'booked_duration_seconds' => $booked_duration_seconds
        ));
    }
    
    /**
     * AJAX: Check provider availability using Availability Engine (Phase 5B)
     * 
     * This uses the custom Availability Engine for detailed provider-level checks
     * including working hours, appointments, buffers, etc.
     */
    public function ajax_check_provider_availability() {
        check_ajax_referer('art_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        // Get parameters
        $date = sanitize_text_field($_POST['date'] ?? '');
        $time = sanitize_text_field($_POST['time'] ?? '');
        $service_id = absint($_POST['service_id'] ?? 0);
        $duration = absint($_POST['duration'] ?? 0);
        $location_id = !empty($_POST['location_id']) ? absint($_POST['location_id']) : null;
        
        // Validate required fields
        if (empty($date)) {
            wp_send_json_error(array('message' => __('Date is required', 'amelia-cpt-sync')));
        }
        
        if (empty($time)) {
            wp_send_json_error(array('message' => __('Time is required', 'amelia-cpt-sync')));
        }
        
        if (!$service_id) {
            wp_send_json_error(array('message' => __('Service is required', 'amelia-cpt-sync')));
        }
        
        if (!$duration) {
            wp_send_json_error(array('message' => __('Duration is required', 'amelia-cpt-sync')));
        }
        
        amelia_cpt_sync_debug_log('Availability Engine AJAX: Starting check', array(
            'date' => $date,
            'time' => $time,
            'service_id' => $service_id,
            'duration' => $duration,
            'location_id' => $location_id
        ));
        
        // Run availability engine
        $engine = new Amelia_CPT_Sync_ART_Availability_Engine();
        $result = $engine->check_availability($date, $time, $service_id, $duration, $location_id);
        
        if (is_wp_error($result)) {
            amelia_cpt_sync_debug_log('Availability Engine AJAX: Error - ' . $result->get_error_message());
            
            // Fallback: return all providers as force-book with warning
            $api_manager = new Amelia_CPT_Sync_ART_API_Manager();
            $providers = $api_manager->get_service_employees($service_id);
            
            $fallback_providers = array();
            if (!is_wp_error($providers)) {
                foreach ($providers as $p) {
                    $fallback_providers[] = array(
                        'id' => $p['id'],
                        'name' => trim(($p['firstName'] ?? '') . ' ' . ($p['lastName'] ?? '')),
                        'status' => 'force_book',
                        'conflicts' => array()
                    );
                }
            }
            
            wp_send_json_success(array(
                'error' => true,
                'message' => __('Availability check failed. Please verify manually in Amelia calendar.', 'amelia-cpt-sync'),
                'providers' => $fallback_providers
            ));
        }
        
        amelia_cpt_sync_debug_log('Availability Engine AJAX: Success', array(
            'total_providers' => count($result)
        ));
        
        wp_send_json_success(array(
            'providers' => $result,
            'error' => false
        ));
    }
    
    /**
     * AJAX: Reschedule an existing Amelia booking
     */
    public function ajax_reschedule_booking() {
        check_ajax_referer('art_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $request_id = absint($_POST['request_id'] ?? 0);
        $new_datetime = sanitize_text_field($_POST['new_datetime'] ?? '');
        $new_provider_id = absint($_POST['new_provider_id'] ?? 0);
        $upgrade_to_confirmed = isset($_POST['upgrade_to_confirmed']) && $_POST['upgrade_to_confirmed'];
        $downgrade_to_tentative = isset($_POST['downgrade_to_tentative']) && $_POST['downgrade_to_tentative'];
        
        amelia_cpt_sync_debug_log('ART Reschedule: Received request', array(
            'request_id' => $request_id,
            'new_datetime' => $new_datetime,
            'new_provider_id' => $new_provider_id,
            'upgrade_to_confirmed' => $upgrade_to_confirmed,
            'downgrade_to_tentative' => $downgrade_to_tentative
        ));
        
        if (!$request_id || !$new_datetime || !$new_provider_id) {
            amelia_cpt_sync_debug_log('ART Reschedule: Missing required fields');
            wp_send_json_error(array('message' => 'Missing required fields'));
        }
        
        global $wpdb;
        $requests_table = $wpdb->prefix . 'art_requests';
        $booking_links_table = $wpdb->prefix . 'art_booking_links';
        
        // Get request data
        $request = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $requests_table WHERE id = %d",
            $request_id
        ));
        
        if (!$request) {
            wp_send_json_error(array('message' => 'Request not found'));
        }
        
        // Get the active booking link
        $booking_link = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $booking_links_table WHERE request_id = %d ORDER BY created_at DESC LIMIT 1",
            $request_id
        ));
        
        if (!$booking_link || !$booking_link->amelia_appointment_id) {
            wp_send_json_error(array('message' => 'No existing Amelia appointment to reschedule'));
        }
        
        $booking_service = new Amelia_CPT_Sync_ART_Booking_Service();
        $api_manager = new Amelia_CPT_Sync_ART_API_Manager();
        
        // Update the appointment with new datetime and provider
        // CRITICAL: Send datetime as-is (in WordPress timezone)
        // Amelia API will handle conversion to UTC internally
        $update_data = array(
            'bookingStart' => $new_datetime,  // Do NOT convert to UTC here
            'providerId' => $new_provider_id,
            'notifyParticipants' => 1
        );
        
        // Add location if set
        if (!empty($request->location_id) && $request->location_id > 0) {
            $update_data['locationId'] = absint($request->location_id);
        }
        
        $result = $booking_service->update_appointment($booking_link->amelia_appointment_id, $update_data);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => 'Reschedule failed: ' . $result->get_error_message()));
        }
        
        // If upgrading tentative to confirmed, update appointment status and booking_type
        if ($upgrade_to_confirmed) {
            $status_result = $booking_service->update_appointment_status($booking_link->amelia_appointment_id, 'approved');
            
            if (is_wp_error($status_result)) {
                amelia_cpt_sync_debug_log('ART: Warning - Could not upgrade status to approved: ' . $status_result->get_error_message());
            }
            
            // Update booking_type in art_booking_links
            $wpdb->update(
                $booking_links_table,
                array('booking_type' => 'confirmed'),
                array('request_id' => $request_id),
                array('%s'),
                array('%d')
            );
            
            amelia_cpt_sync_debug_log('ART: Upgraded tentative booking to confirmed for request #' . $request_id);
        }
        
        // If downgrading confirmed to tentative, update appointment status and booking_type
        if ($downgrade_to_tentative) {
            $status_result = $booking_service->update_appointment_status($booking_link->amelia_appointment_id, 'pending');
            
            if (is_wp_error($status_result)) {
                amelia_cpt_sync_debug_log('ART: Warning - Could not downgrade status to pending: ' . $status_result->get_error_message());
            }
            
            // Update booking_type in art_booking_links
            $wpdb->update(
                $booking_links_table,
                array('booking_type' => 'tentative'),
                array('request_id' => $request_id),
                array('%s'),
                array('%d')
            );
            
            amelia_cpt_sync_debug_log('ART: Downgraded confirmed booking to tentative for request #' . $request_id);
        }
        
        // Format datetime for response
        $booking_timestamp = strtotime($new_datetime);
        $formatted_date = date('l, F jS, Y', $booking_timestamp);
        $formatted_time = date('h:i A', $booking_timestamp);
        
        // Get provider name
        $provider_name = '';
        $providers = $api_manager->get_service_employees($request->service_id);
        if (!is_wp_error($providers)) {
            foreach ($providers as $p) {
                if (intval($p['id']) === intval($new_provider_id)) {
                    $provider_name = trim(($p['firstName'] ?? '') . ' ' . ($p['lastName'] ?? ''));
                    break;
                }
            }
        }
        
        amelia_cpt_sync_debug_log('ART: Successfully rescheduled appointment #' . $booking_link->amelia_appointment_id);
        
        wp_send_json_success(array(
            'message' => 'Booking rescheduled successfully!',
            'appointment_id' => $booking_link->amelia_appointment_id,
            'booking_id' => $booking_link->amelia_booking_id,
            'formatted_date' => $formatted_date,
            'formatted_time' => $formatted_time,
            'provider_name' => $provider_name,
            'raw_datetime' => $new_datetime
        ));
    }
    
    /**
     * AJAX: Unified booking management endpoint (Quick Win)
     * 
     * Handles all booking operations through centralized decision engine
     */
    public function ajax_manage_booking() {
        check_ajax_referer('art_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $manager = new Amelia_CPT_Sync_ART_Booking_Manager();
        
        $result = $manager->process_booking(array(
            'request_id' => absint($_POST['request_id'] ?? 0),
            'service_id' => absint($_POST['service_id'] ?? 0),
            'duration' => absint($_POST['duration'] ?? 0),
            'slot_datetime' => sanitize_text_field($_POST['slot_datetime'] ?? ''),
            'provider_id' => absint($_POST['provider_id'] ?? 0),
            'desired_status' => sanitize_key($_POST['desired_status'] ?? 'confirmed'),
            'location_id' => !empty($_POST['location_id']) ? absint($_POST['location_id']) : null,
            'persons' => absint($_POST['persons'] ?? 1)
        ));
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * AJAX: Add a manual note
     */
    public function ajax_add_note() {
        check_ajax_referer('art_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $notes_manager = new ART_Notes_Manager();
        $note_id = $notes_manager->add_manual_note(
            absint($_POST['request_id'] ?? 0),
            $_POST['note_content'] ?? '', // Will be sanitized in manager
            get_current_user_id()
        );
        
        if (is_wp_error($note_id)) {
            wp_send_json_error(array('message' => $note_id->get_error_message()));
        }
        
        // Return the newly created note with user data
        $note = $notes_manager->get_note_by_id($note_id);
        wp_send_json_success(array('note' => $note));
    }
    
    /**
     * AJAX: Update a manual note
     */
    public function ajax_update_note() {
        check_ajax_referer('art_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $notes_manager = new ART_Notes_Manager();
        $result = $notes_manager->update_note(
            absint($_POST['note_id'] ?? 0),
            $_POST['note_content'] ?? '', // Will be sanitized in manager
            get_current_user_id()
        );
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success(array('message' => 'Note updated successfully'));
    }
    
    /**
     * AJAX: Delete a note
     */
    public function ajax_delete_note() {
        check_ajax_referer('art_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $notes_manager = new ART_Notes_Manager();
        $result = $notes_manager->delete_note(
            absint($_POST['note_id'] ?? 0),
            get_current_user_id()
        );
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success(array('message' => 'Note deleted successfully'));
    }
    
    /**
     * AJAX: Get notes for a request (paginated)
     */
    public function ajax_get_notes() {
        check_ajax_referer('art_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $notes_manager = new ART_Notes_Manager();
        $request_id = absint($_POST['request_id'] ?? 0);
        $offset = absint($_POST['offset'] ?? 0);
        $limit = absint($_POST['limit'] ?? 20);
        
        $notes = $notes_manager->get_notes($request_id, $offset, $limit);
        $total = $notes_manager->get_note_count($request_id);
        
        wp_send_json_success(array(
            'notes' => $notes,
            'total' => $total,
            'has_more' => ($offset + count($notes)) < $total
        ));
    }
    
    /**
     * AJAX: Log customer match selection
     */
    public function ajax_log_customer_match() {
        check_ajax_referer('art_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $notes_manager = new ART_Notes_Manager();
        $notes_manager->add_system_note(
            absint($_POST['request_id'] ?? 0),
            sprintf(__('Customer matched to existing Amelia customer #%d', 'amelia-cpt-sync'), absint($_POST['customer_id'] ?? 0)),
            array('customer_id' => absint($_POST['customer_id'] ?? 0))
        );
        
        wp_send_json_success();
    }
    
    /**
     * AJAX: Delete an existing Amelia booking and create a new one
     */
    public function ajax_delete_booking() {
        check_ajax_referer('art_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $request_id = absint($_POST['request_id'] ?? 0);
        
        if (!$request_id) {
            wp_send_json_error(array('message' => 'Request ID is required'));
        }
        
        global $wpdb;
        $requests_table = $wpdb->prefix . 'art_requests';
        $booking_links_table = $wpdb->prefix . 'art_booking_links';
        
        // Get the active booking link
        $booking_link = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $booking_links_table WHERE request_id = %d ORDER BY created_at DESC LIMIT 1",
            $request_id
        ));
        
        if (!$booking_link || !$booking_link->amelia_appointment_id) {
            wp_send_json_error(array('message' => 'No existing Amelia appointment to delete'));
        }
        
        $booking_service = new Amelia_CPT_Sync_ART_Booking_Service();
        
        // Delete the appointment from Amelia
        $result = $booking_service->delete_appointment($booking_link->amelia_appointment_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => 'Delete failed: ' . $result->get_error_message()));
        }
        
        // Remove the booking link
        $wpdb->delete($booking_links_table, array('request_id' => $request_id), array('%d'));
        
        // Update request status back to tentative
        $wpdb->update(
            $requests_table,
            array('status_key' => 'tentative'),
            array('id' => $request_id),
            array('%s'),
            array('%d')
        );
        
        amelia_cpt_sync_debug_log('ART: Successfully deleted appointment #' . $booking_link->amelia_appointment_id . ' for request #' . $request_id);
        
        wp_send_json_success(array(
            'message' => 'Previous booking deleted. You can now create a new booking.',
            'deleted_appointment_id' => $booking_link->amelia_appointment_id
        ));
    }
}

