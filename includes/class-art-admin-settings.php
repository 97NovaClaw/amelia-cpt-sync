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
        
        // Phase 4: Detail view AJAX handlers
        add_action('wp_ajax_art_update_status', array($this, 'ajax_update_status'));
        add_action('wp_ajax_art_update_follow_up', array($this, 'ajax_update_follow_up'));
        add_action('wp_ajax_art_save_pillars', array($this, 'ajax_save_pillars'));
        add_action('wp_ajax_art_check_customer_match', array($this, 'ajax_check_customer_match'));
        add_action('wp_ajax_art_get_locations', array($this, 'ajax_get_locations'));
        add_action('wp_ajax_art_get_service_employees', array($this, 'ajax_get_service_employees'));
        
        // Phase 5: API integration
        add_action('wp_ajax_art_get_service_duration', array($this, 'ajax_get_service_duration'));
        add_action('wp_ajax_art_check_availability', array($this, 'ajax_check_availability'));
        add_action('wp_ajax_art_create_booking', array($this, 'ajax_create_booking'));
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
            $follow_up_utc = gmdate('Y-m-d H:i:s', strtotime($follow_up_date . ' 00:00:00'));
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
            $data['start_datetime'] = gmdate('Y-m-d H:i:s', strtotime($_POST['start_datetime']));
        }
        
        if (isset($_POST['end_datetime']) && !empty($_POST['end_datetime'])) {
            $data['end_datetime'] = gmdate('Y-m-d H:i:s', strtotime($_POST['end_datetime']));
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
     * AJAX handler for customer match check
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
        
        wp_send_json_success(array(
            'duration_seconds' => $duration_seconds,
            'duration_display' => $display,
            'service_name' => $service['name'] ?? ''
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
            'startDateTime' => !empty($request->start_datetime) ? gmdate('Y-m-d', strtotime($request->start_datetime)) : gmdate('Y-m-d')
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
        
        // Debug: Log raw slots structure
        $raw_slots = $result['slots'] ?? array();
        amelia_cpt_sync_debug_log('ART Slots: Raw structure', array(
            'total_dates' => count($raw_slots),
            'dates_available' => array_keys($raw_slots)
        ));
        
        // Log specific date for debugging (today and tomorrow)
        $today = gmdate('Y-m-d');
        $tomorrow = gmdate('Y-m-d', strtotime('+1 day'));
        if (isset($raw_slots[$today])) {
            amelia_cpt_sync_debug_log('ART Slots: Today (' . $today . ') times', array_keys($raw_slots[$today]));
        }
        if (isset($raw_slots[$tomorrow])) {
            amelia_cpt_sync_debug_log('ART Slots: Tomorrow (' . $tomorrow . ') times', array_keys($raw_slots[$tomorrow]));
            
            // Log provider details for early morning slots (to debug the 6am issue)
            foreach (array('5:00', '6:00', '7:00', '05:00', '06:00', '07:00') as $check_time) {
                if (isset($raw_slots[$tomorrow][$check_time])) {
                    amelia_cpt_sync_debug_log('ART Slots: ' . $tomorrow . ' @ ' . $check_time . ' providers', $raw_slots[$tomorrow][$check_time]);
                }
            }
        }
        
        foreach ($result['slots'] as $date => $times) {
            foreach ($times as $time => $providers) {
                // providers is an array of [providerId, locationId] pairs
                foreach ($providers as $provider_info) {
                    // Skip slots without valid provider
                    if (empty($provider_info[0])) {
                        amelia_cpt_sync_debug_log('ART Slots: Skipping slot without provider', array(
                            'date' => $date,
                            'time' => $time,
                            'raw_provider_info' => $provider_info
                        ));
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
        $missing = array();
        if (!$request->service_id) $missing[] = 'Service';
        if (!$request->start_datetime) $missing[] = 'Start Time';
        if (!$request->end_datetime) $missing[] = 'End Time';
        if (!$request->location_id) $missing[] = 'Location';
        if (empty($customer->email)) $missing[] = 'Customer Email';
        
        if (!empty($missing)) {
            wp_send_json_error(array('message' => 'Missing required fields: ' . implode(', ', $missing)));
        }
        
        // Get selected provider and slot from POST (user selected from availability results)
        $selected_provider_id = absint($_POST['provider_id'] ?? 0);
        $selected_slot_datetime = sanitize_text_field($_POST['slot_datetime'] ?? '');
        
        if (!$selected_provider_id) {
            wp_send_json_error(array('message' => 'Please select a provider from availability results'));
        }
        
        if (!$selected_slot_datetime) {
            wp_send_json_error(array('message' => 'Please select a time slot'));
        }
        
        // Build booking data in EXACT format from Amelia API docs
        $booking_object = array(
            'extras' => array(),
            'customFields' => (object) array(),  // Empty object, not array
            'deposit' => false,
            'locale' => 'en_US',
            'utcOffset' => null,
            'persons' => absint($request->persons ?? 1),
            'customerId' => $customer->amelia_customer_id ?? null,
            'customer' => array(
                'id' => $customer->amelia_customer_id ?? null,
                'firstName' => $customer->first_name,
                'lastName' => $customer->last_name,
                'email' => $customer->email,
                'phone' => $customer->phone ?? '',
                'countryPhoneIso' => '',
                'externalId' => null
            ),
            'duration' => absint($request->duration_seconds)
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
            'bookingStart' => gmdate('Y-m-d H:i', strtotime($selected_slot_datetime)),  // "YYYY-MM-DD HH:mm"
            'notifyParticipants' => 1,
            'locationId' => absint($request->location_id),
            'providerId' => $selected_provider_id,
            'serviceId' => absint($request->service_id)
        );
        
        $api_manager = new Amelia_CPT_Sync_ART_API_Manager();
        $result = $api_manager->create_booking($booking_data);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => 'Booking failed: ' . $result->get_error_message()));
        }
        
        // Extract booking IDs from response
        $booking_id = $result['data']['booking']['id'] ?? null;
        $appointment_id = $result['data']['appointment']['id'] ?? null;
        $amelia_customer_id = $result['data']['customer']['id'] ?? null;
        
        // Update request with Amelia IDs and change status to 'booked'
        $wpdb->update(
            $requests_table,
            array(
                'status_key' => 'booked',
                'amelia_booking_id' => $booking_id,
                'amelia_appointment_id' => $appointment_id
            ),
            array('id' => $request_id),
            array('%s', '%d', '%d'),
            array('%d')
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
        
        wp_send_json_success(array(
            'message' => 'Booking created successfully!',
            'booking_id' => $booking_id,
            'appointment_id' => $appointment_id,
            'amelia_customer_id' => $amelia_customer_id
        ));
    }
}

