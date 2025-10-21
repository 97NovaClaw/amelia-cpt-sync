<?php
/**
 * Custom Fields Manager Class
 *
 * Handles custom field definitions and values
 *
 * @package AmeliaCPTSync
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class Amelia_CPT_Sync_Custom_Fields_Manager {
    
    /**
     * Table name for custom field definitions
     */
    private $definitions_table;
    
    /**
     * Table name for custom field values
     */
    private $values_table;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->definitions_table = $wpdb->prefix . 'amelia_cpt_custom_field_defs';
        $this->values_table = $wpdb->prefix . 'amelia_cpt_custom_field_values';
    }
    
    /**
     * Create database tables
     */
    public function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // Table for field definitions (configured in admin)
        $sql_definitions = "CREATE TABLE IF NOT EXISTS {$this->definitions_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            field_title varchar(255) NOT NULL,
            meta_key varchar(255) NOT NULL,
            description text,
            admin_note text,
            field_order int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY meta_key (meta_key)
        ) $charset_collate;";
        
        // Table for field values (per service)
        $sql_values = "CREATE TABLE IF NOT EXISTS {$this->values_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            amelia_service_id bigint(20) NOT NULL,
            meta_key varchar(255) NOT NULL,
            meta_value longtext,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY service_field (amelia_service_id, meta_key),
            KEY amelia_service_id (amelia_service_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_definitions);
        dbDelta($sql_values);
        
        amelia_cpt_sync_debug_log('Custom fields tables created/verified');
    }
    
    /**
     * Get all field definitions
     */
    public function get_field_definitions() {
        global $wpdb;
        
        $results = $wpdb->get_results(
            "SELECT * FROM {$this->definitions_table} ORDER BY field_order ASC, id ASC",
            ARRAY_A
        );
        
        return $results ? $results : array();
    }
    
    /**
     * Save field definitions
     */
    public function save_field_definitions($definitions) {
        global $wpdb;
        
        amelia_cpt_sync_debug_log('Saving custom field definitions: ' . print_r($definitions, true));
        
        // Clear existing definitions
        $wpdb->query("DELETE FROM {$this->definitions_table}");
        
        // Insert new definitions
        foreach ($definitions as $index => $def) {
            if (empty($def['meta_key'])) {
                continue;
            }
            
            $wpdb->insert(
                $this->definitions_table,
                array(
                    'field_title' => sanitize_text_field($def['field_title']),
                    'meta_key' => sanitize_key($def['meta_key']),
                    'description' => sanitize_textarea_field($def['description']),
                    'admin_note' => sanitize_textarea_field($def['admin_note']),
                    'field_order' => intval($index)
                ),
                array('%s', '%s', '%s', '%s', '%d')
            );
        }
        
        return true;
    }
    
    /**
     * Get custom field values for a service
     */
    public function get_service_field_values($service_id) {
        global $wpdb;
        
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT meta_key, meta_value FROM {$this->values_table} WHERE amelia_service_id = %d",
                $service_id
            ),
            ARRAY_A
        );
        
        $values = array();
        foreach ($results as $row) {
            $values[$row['meta_key']] = $row['meta_value'];
        }
        
        return $values;
    }
    
    /**
     * Save custom field values for a service
     */
    public function save_service_field_values($service_id, $values) {
        global $wpdb;
        
        amelia_cpt_sync_debug_log("Saving custom field values for service {$service_id}: " . print_r($values, true));
        
        foreach ($values as $meta_key => $meta_value) {
            // Check if exists
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$this->values_table} WHERE amelia_service_id = %d AND meta_key = %s",
                $service_id,
                $meta_key
            ));
            
            if ($exists) {
                // Update
                $wpdb->update(
                    $this->values_table,
                    array('meta_value' => $meta_value),
                    array('amelia_service_id' => $service_id, 'meta_key' => $meta_key),
                    array('%s'),
                    array('%d', '%s')
                );
            } else {
                // Insert
                $wpdb->insert(
                    $this->values_table,
                    array(
                        'amelia_service_id' => $service_id,
                        'meta_key' => $meta_key,
                        'meta_value' => $meta_value
                    ),
                    array('%d', '%s', '%s')
                );
            }
        }
        
        return true;
    }
    
    /**
     * Delete all field values for a service
     */
    public function delete_service_fields($service_id) {
        global $wpdb;
        
        $wpdb->delete(
            $this->values_table,
            array('amelia_service_id' => $service_id),
            array('%d')
        );
    }
}

