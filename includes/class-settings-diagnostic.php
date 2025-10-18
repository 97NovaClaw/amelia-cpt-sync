<?php
/**
 * Settings Diagnostic Tool
 *
 * Helper class to debug settings storage issues
 *
 * @package AmeliaCPTSync
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class Amelia_CPT_Sync_Settings_Diagnostic {
    
    /**
     * Get raw settings from database
     */
    public static function get_raw_settings() {
        return get_option('amelia_cpt_sync_settings', false);
    }
    
    /**
     * Display settings diagnostic
     */
    public static function display_diagnostic() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        echo '<div class="notice notice-info" style="margin: 20px 0; padding: 15px;">';
        echo '<h3>🔍 Settings Diagnostic</h3>';
        echo '<p><strong>📌 Important:</strong> Plugin settings are stored in your WordPress <strong>database</strong> (wp_options table), NOT as a physical JSON file. The diagnostic below shows what\'s currently stored.</p>';
        echo '<p><strong>Database Key:</strong> <code>amelia_cpt_sync_settings</code></p>';
        
        $raw_settings = self::get_raw_settings();
        
        if ($raw_settings === false) {
            echo '<p style="color: red;"><strong>❌ No settings found in database!</strong></p>';
            echo '<p>The option "amelia_cpt_sync_settings" does not exist in wp_options table.</p>';
        } else {
            echo '<p style="color: green;"><strong>✅ Settings found in database</strong></p>';
            echo '<p><strong>Storage Type:</strong> ' . gettype($raw_settings) . '</p>';
            echo '<p><strong>Raw Value (JSON string):</strong></p>';
            echo '<pre style="background: #f5f5f5; padding: 10px; overflow: auto; max-height: 300px;">';
            echo esc_html($raw_settings);
            echo '</pre>';
            
            $decoded = json_decode($raw_settings, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                echo '<p><strong>✅ JSON is valid</strong></p>';
                echo '<p><strong>Decoded Array:</strong></p>';
                echo '<pre style="background: #f5f5f5; padding: 10px; overflow: auto; max-height: 300px;">';
                print_r($decoded);
                echo '</pre>';
                
                // Check specific fields
                echo '<h4>Field Check:</h4>';
                echo '<ul>';
                echo '<li>CPT Slug: <strong>' . (isset($decoded['cpt_slug']) ? $decoded['cpt_slug'] : 'NOT SET') . '</strong></li>';
                echo '<li>Taxonomy Slug: <strong>' . (isset($decoded['taxonomy_slug']) ? $decoded['taxonomy_slug'] : 'NOT SET') . '</strong></li>';
                echo '<li>Debug Enabled: <strong>' . (isset($decoded['debug_enabled']) ? ($decoded['debug_enabled'] ? 'TRUE' : 'FALSE') : 'NOT SET') . '</strong></li>';
                echo '<li>Service ID Field: <strong>' . (isset($decoded['field_mappings']['service_id']) ? $decoded['field_mappings']['service_id'] : 'NOT SET') . '</strong></li>';
                echo '<li>Category ID Field: <strong>' . (isset($decoded['field_mappings']['category_id']) ? $decoded['field_mappings']['category_id'] : 'NOT SET') . '</strong></li>';
                echo '<li>Primary Photo Field: <strong>' . (isset($decoded['field_mappings']['primary_photo']) ? $decoded['field_mappings']['primary_photo'] : 'NOT SET') . '</strong></li>';
                echo '<li>Price Field: <strong>' . (isset($decoded['field_mappings']['price']) ? $decoded['field_mappings']['price'] : 'NOT SET') . '</strong></li>';
                echo '</ul>';
            } else {
                echo '<p style="color: red;"><strong>❌ JSON is INVALID</strong></p>';
                echo '<p>Error: ' . json_last_error_msg() . '</p>';
            }
        }
        
        echo '</div>';
    }
    
    /**
     * Show where debug log should be
     */
    public static function show_debug_location() {
        $debug_dir = AMELIA_CPT_SYNC_PLUGIN_DIR . 'debug';
        $debug_file = $debug_dir . '/debug.log';
        
        echo '<div class="notice notice-info" style="margin: 20px 0; padding: 15px;">';
        echo '<h3>📁 Debug Log Location</h3>';
        echo '<p><strong>Expected Directory:</strong> <code>' . esc_html($debug_dir) . '</code></p>';
        echo '<p><strong>Expected File:</strong> <code>' . esc_html($debug_file) . '</code></p>';
        
        if (file_exists($debug_dir)) {
            echo '<p style="color: green;">✅ Debug directory EXISTS</p>';
            
            if (file_exists($debug_file)) {
                echo '<p style="color: green;">✅ Debug log file EXISTS</p>';
                $size = filesize($debug_file);
                echo '<p><strong>File Size:</strong> ' . $size . ' bytes</p>';
                
                if ($size > 0) {
                    echo '<p><strong>Last 10 lines:</strong></p>';
                    echo '<pre style="background: #1e1e1e; color: #d4d4d4; padding: 10px; overflow: auto; max-height: 200px;">';
                    $lines = file($debug_file);
                    $last_lines = array_slice($lines, -10);
                    echo esc_html(implode('', $last_lines));
                    echo '</pre>';
                } else {
                    echo '<p style="color: orange;">⚠️ Debug log file is EMPTY (0 bytes)</p>';
                    echo '<p>This means debug logging is either disabled or no events have been logged yet.</p>';
                }
            } else {
                echo '<p style="color: orange;">⚠️ Debug log file does NOT exist yet</p>';
                echo '<p>It will be created automatically when the first log entry is written.</p>';
            }
        } else {
            echo '<p style="color: orange;">⚠️ Debug directory does NOT exist yet</p>';
            echo '<p>It will be created automatically when the first log entry is written.</p>';
        }
        
        echo '</div>';
    }
}

