<?php
/**
 * Plugin Name: Amelia to CPT Sync
 * Plugin URI: https://github.com/97NovaClaw/amelia-cpt-sync
 * Description: Real-time, one-way synchronization from AmeliaWP booking plugin to JetEngine Custom Post Types
 * Version: 1.2.9
 * Author: 97NovaClaw
 * Author URI: https://github.com/97NovaClaw
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: amelia-cpt-sync
 * Domain Path: /languages
 *
 * @package AmeliaCPTSync
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Current plugin version.
 */
define('AMELIA_CPT_SYNC_VERSION', '1.2.9');
define('AMELIA_CPT_SYNC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AMELIA_CPT_SYNC_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * The code that runs during plugin activation.
 */
function activate_amelia_cpt_sync() {
    $default_settings = array(
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
    
    // Migrate from settings.json to wp_options if JSON file exists
    $settings_file = AMELIA_CPT_SYNC_PLUGIN_DIR . 'settings.json';
    
    if (file_exists($settings_file)) {
        $json_content = file_get_contents($settings_file);
        $json_settings = json_decode($json_content, true);
        
        if ($json_settings && json_last_error() === JSON_ERROR_NONE) {
            // Merge with defaults to ensure all keys exist
            $migrated_settings = array_replace_recursive($default_settings, $json_settings);
            
            // Save to wp_options
            update_option('amelia_cpt_sync_settings', $migrated_settings);
            
            // Rename old file as backup
            rename($settings_file, $settings_file . '.backup');
            
            amelia_cpt_sync_debug_log('Successfully migrated settings.json to wp_options database');
            amelia_cpt_sync_debug_log('Old file backed up as settings.json.backup');
        }
    }
    
    // Create default settings in wp_options if they don't exist
    if (false === get_option('amelia_cpt_sync_settings')) {
        add_option('amelia_cpt_sync_settings', $default_settings);
    }
    
    // Create custom fields database tables
    require_once AMELIA_CPT_SYNC_PLUGIN_DIR . 'includes/class-custom-fields-manager.php';
    $custom_fields_manager = new Amelia_CPT_Sync_Custom_Fields_Manager();
    $custom_fields_manager->create_tables();
    
    // Create taxonomy custom fields database tables
    require_once AMELIA_CPT_SYNC_PLUGIN_DIR . 'includes/class-taxonomy-custom-fields-manager.php';
    $taxonomy_custom_fields_manager = new Amelia_CPT_Sync_Taxonomy_Custom_Fields_Manager();
    $taxonomy_custom_fields_manager->create_tables();

    // Flush rewrite rules
    flush_rewrite_rules();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_amelia_cpt_sync() {
    // Flush rewrite rules
    flush_rewrite_rules();
}

register_activation_hook(__FILE__, 'activate_amelia_cpt_sync');
register_deactivation_hook(__FILE__, 'deactivate_amelia_cpt_sync');

/**
 * Load plugin classes and functions
 */
require_once AMELIA_CPT_SYNC_PLUGIN_DIR . 'includes/debug-functions.php';
require_once AMELIA_CPT_SYNC_PLUGIN_DIR . 'includes/class-field-detector.php';
require_once AMELIA_CPT_SYNC_PLUGIN_DIR . 'includes/class-custom-fields-manager.php';
require_once AMELIA_CPT_SYNC_PLUGIN_DIR . 'includes/class-taxonomy-custom-fields-manager.php';
require_once AMELIA_CPT_SYNC_PLUGIN_DIR . 'includes/class-popup-handler.php';
require_once AMELIA_CPT_SYNC_PLUGIN_DIR . 'includes/class-popup-config-manager.php';
require_once AMELIA_CPT_SYNC_PLUGIN_DIR . 'includes/class-admin-settings.php';
require_once AMELIA_CPT_SYNC_PLUGIN_DIR . 'includes/class-cpt-manager.php';
require_once AMELIA_CPT_SYNC_PLUGIN_DIR . 'includes/class-sync-handler.php';

/**
 * Initialize the plugin
 */
function run_amelia_cpt_sync() {
    // Initialize admin settings
    if (is_admin()) {
        $admin_settings = new Amelia_CPT_Sync_Admin_Settings();
        $admin_settings->init();
    }
    
    // Initialize sync handler (runs on both frontend and backend)
    $sync_handler = new Amelia_CPT_Sync_Handler();
    $sync_handler->init();
    
    // Initialize popup handler (AJAX + frontend scripts)
    $popup_handler = new Amelia_CPT_Sync_Popup_Handler();
    $popup_handler->init();
}

add_action('plugins_loaded', 'run_amelia_cpt_sync');

