<?php
/**
 * Simple Debug Functions
 *
 * Traditional debug logging system
 *
 * @package AmeliaCPTSync
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Check if debug is enabled by reading from wp_options
 */
function amelia_cpt_sync_is_debug_enabled() {
    $settings = get_option('amelia_cpt_sync_settings', array('debug_enabled' => false));
    
    return isset($settings['debug_enabled']) && $settings['debug_enabled'] === true;
}

/**
 * Write to debug log
 *
 * @param string $message The message to log
 */
function amelia_cpt_sync_debug_log($message) {
    // Only log if debug is enabled
    if (!amelia_cpt_sync_is_debug_enabled()) {
        return;
    }
    
    $log_file = AMELIA_CPT_SYNC_PLUGIN_DIR . 'debug.txt';
    $timestamp = current_time('Y-m-d H:i:s');
    $log_entry = "[{$timestamp}] {$message}\n";
    
    // Append to log file
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

