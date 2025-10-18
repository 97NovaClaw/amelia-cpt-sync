<?php
/**
 * Debug Logger Class
 *
 * Handles plugin-specific debug logging with file output
 *
 * @package AmeliaCPTSync
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class Amelia_CPT_Sync_Debug_Logger {
    
    /**
     * Debug directory path
     */
    private $debug_dir;
    
    /**
     * Log file path
     */
    private $log_file;
    
    /**
     * Maximum log file size (5MB)
     */
    private $max_log_size = 5242880;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->debug_dir = AMELIA_CPT_SYNC_PLUGIN_DIR . 'debug';
        $this->log_file = $this->debug_dir . '/debug.log';
        
        // Create debug directory if it doesn't exist
        $this->create_debug_directory();
    }
    
    /**
     * Create debug directory with security
     */
    private function create_debug_directory() {
        if (!file_exists($this->debug_dir)) {
            wp_mkdir_p($this->debug_dir);
            
            // Add .htaccess to prevent direct access
            $htaccess_content = "Order deny,allow\nDeny from all";
            file_put_contents($this->debug_dir . '/.htaccess', $htaccess_content);
            
            // Add index.php to prevent directory listing
            file_put_contents($this->debug_dir . '/index.php', '<?php // Silence is golden');
        }
    }
    
    /**
     * Check if debug logging is enabled
     */
    public function is_enabled() {
        $settings_json = get_option('amelia_cpt_sync_settings');
        
        if (empty($settings_json)) {
            return false;
        }
        
        $settings = json_decode($settings_json, true);
        
        return isset($settings['debug_enabled']) && $settings['debug_enabled'] === true;
    }
    
    /**
     * Log a message
     *
     * @param string $message The message to log
     * @param string $level Log level (INFO, WARNING, ERROR)
     */
    public function log($message, $level = 'INFO') {
        // Only log if debug is enabled
        if (!$this->is_enabled()) {
            return;
        }
        
        // Rotate log if too large
        $this->rotate_log_if_needed();
        
        // Format log entry
        $timestamp = current_time('Y-m-d H:i:s');
        $log_entry = sprintf(
            "[%s] [%s] %s\n",
            $timestamp,
            strtoupper($level),
            $message
        );
        
        // Write to log file
        error_log($log_entry, 3, $this->log_file);
    }
    
    /**
     * Log info message
     */
    public function info($message) {
        $this->log($message, 'INFO');
    }
    
    /**
     * Log warning message
     */
    public function warning($message) {
        $this->log($message, 'WARNING');
    }
    
    /**
     * Log error message
     */
    public function error($message) {
        $this->log($message, 'ERROR');
    }
    
    /**
     * Log debug message
     */
    public function debug($message) {
        $this->log($message, 'DEBUG');
    }
    
    /**
     * Rotate log file if it exceeds max size
     */
    private function rotate_log_if_needed() {
        if (!file_exists($this->log_file)) {
            return;
        }
        
        $file_size = filesize($this->log_file);
        
        if ($file_size > $this->max_log_size) {
            // Rename current log to backup
            $backup_file = $this->debug_dir . '/debug-' . date('Y-m-d-His') . '.log';
            rename($this->log_file, $backup_file);
            
            // Keep only last 5 backup files
            $this->cleanup_old_logs();
        }
    }
    
    /**
     * Cleanup old log files
     */
    private function cleanup_old_logs() {
        $files = glob($this->debug_dir . '/debug-*.log');
        
        if (count($files) > 5) {
            // Sort by modification time
            usort($files, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            
            // Delete oldest files
            $files_to_delete = array_slice($files, 0, count($files) - 5);
            foreach ($files_to_delete as $file) {
                unlink($file);
            }
        }
    }
    
    /**
     * Get log file contents
     */
    public function get_log_contents($lines = 100) {
        if (!file_exists($this->log_file)) {
            return 'No log file exists yet.';
        }
        
        $file = new SplFileObject($this->log_file, 'r');
        $file->seek(PHP_INT_MAX);
        $total_lines = $file->key();
        
        $start_line = max(0, $total_lines - $lines);
        
        $file->seek($start_line);
        
        $contents = '';
        while (!$file->eof()) {
            $contents .= $file->current();
            $file->next();
        }
        
        return $contents;
    }
    
    /**
     * Get log file path
     */
    public function get_log_file_path() {
        return $this->log_file;
    }
    
    /**
     * Clear log file
     */
    public function clear_log() {
        if (file_exists($this->log_file)) {
            file_put_contents($this->log_file, '');
            $this->log('Log file cleared', 'INFO');
            return true;
        }
        return false;
    }
    
    /**
     * Get log file size
     */
    public function get_log_size() {
        if (!file_exists($this->log_file)) {
            return 0;
        }
        
        return filesize($this->log_file);
    }
    
    /**
     * Format file size for display
     */
    public function format_file_size($bytes) {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } elseif ($bytes > 1) {
            return $bytes . ' bytes';
        } elseif ($bytes == 1) {
            return $bytes . ' byte';
        } else {
            return '0 bytes';
        }
    }
}

