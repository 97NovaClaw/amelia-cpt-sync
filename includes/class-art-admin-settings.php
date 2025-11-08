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
    }
    
    /**
     * Add admin menu pages
     */
    public function add_admin_menu() {
        // Add submenu: ART Settings
        add_submenu_page(
            'amelia-cpt-sync',
            __('ART Settings', 'amelia-cpt-sync'),
            __('ART Settings', 'amelia-cpt-sync'),
            'manage_options',
            'art-settings',
            array($this, 'render_settings_page')
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
                'cache_duration' => 60
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
                'cache_duration' => absint($input['global']['cache_duration'])
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
        
        $settings = $this->get_settings();
        
        $settings['global']['api_key'] = $api_key;
        $settings['global']['api_base_url'] = $api_base_url;
        $settings['global']['debug_enabled'] = $debug_enabled;
        $settings['global']['enable_caching'] = $enable_caching;
        $settings['global']['cache_duration'] = $cache_duration;
        
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
                        cache_duration: $('#art-cache-duration').val()
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
    public function render_triage_forms_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Include the template
        include AMELIA_CPT_SYNC_PLUGIN_DIR . 'templates/art-triage-forms-page.php';
    }
}

