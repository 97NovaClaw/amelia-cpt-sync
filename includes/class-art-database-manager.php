<?php
/**
 * ART Database Manager Class
 *
 * Handles all database operations for the ART (Amelia Request Triage) module
 * Creates and manages custom tables, provides CRUD operations
 *
 * @package AmeliaCPTSync
 * @subpackage ART
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class Amelia_CPT_Sync_ART_Database_Manager {
    
    /**
     * Database version for schema tracking
     */
    const DB_VERSION = '1.0.0';
    
    /**
     * Option name for storing database version
     */
    const DB_VERSION_OPTION = 'art_db_version';
    
    /**
     * Table names (without prefix)
     */
    private $table_customers = 'art_customers';
    private $table_requests = 'art_requests';
    private $table_intake_fields = 'art_intake_fields';
    private $table_booking_links = 'art_booking_links';
    private $table_request_notes = 'art_request_notes';
    
    /**
     * WordPress database object
     */
    private $wpdb;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }
    
    /**
     * Create all ART module tables
     * Called on plugin activation
     *
     * @return bool True on success, false on failure
     */
    public function create_tables() {
        $current_version = get_option(self::DB_VERSION_OPTION, '0.0.0');
        
        // Only run if not already at current version
        if (version_compare($current_version, self::DB_VERSION, '>=')) {
            amelia_cpt_sync_debug_log('ART Database: Already at version ' . self::DB_VERSION);
            return true;
        }
        
        amelia_cpt_sync_debug_log('ART Database: Creating/updating tables to version ' . self::DB_VERSION);
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $charset_collate = $this->wpdb->get_charset_collate();
        
        // Create all tables
        $this->create_customers_table($charset_collate);
        $this->create_requests_table($charset_collate);
        $this->create_intake_fields_table($charset_collate);
        $this->create_booking_links_table($charset_collate);
        $this->create_request_notes_table($charset_collate);
        
        // Update version
        update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
        
        amelia_cpt_sync_debug_log('ART Database: Tables created/updated successfully');
        
        return true;
    }
    
    /**
     * Create art_customers table
     *
     * @param string $charset_collate Database charset
     */
    private function create_customers_table($charset_collate) {
        $table_name = $this->wpdb->prefix . $this->table_customers;
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            first_name varchar(255) NOT NULL,
            last_name varchar(255) NOT NULL,
            email varchar(255) NOT NULL,
            phone varchar(50) DEFAULT NULL,
            amelia_customer_id bigint(20) DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY email (email),
            KEY amelia_customer_id (amelia_customer_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        amelia_cpt_sync_debug_log('ART Database: Created/updated table ' . $table_name);
    }
    
    /**
     * Create art_requests table
     *
     * @param string $charset_collate Database charset
     */
    private function create_requests_table($charset_collate) {
        $table_name = $this->wpdb->prefix . $this->table_requests;
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            customer_id bigint(20) UNSIGNED NOT NULL,
            status_key varchar(50) NOT NULL DEFAULT 'requested',
            service_id bigint(20) DEFAULT NULL,
            location_id bigint(20) DEFAULT NULL,
            persons int(11) DEFAULT 1,
            start_datetime datetime DEFAULT NULL,
            end_datetime datetime DEFAULT NULL,
            duration_seconds int(11) DEFAULT 0,
            final_price decimal(10,2) DEFAULT NULL,
            final_provider_id bigint(20) DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            follow_up_by datetime DEFAULT NULL,
            last_activity_at datetime DEFAULT NULL,
            responded_at datetime DEFAULT NULL,
            tentative_at datetime DEFAULT NULL,
            booked_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY customer_id (customer_id),
            KEY status_key (status_key),
            KEY start_datetime (start_datetime),
            KEY follow_up_by (follow_up_by),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        amelia_cpt_sync_debug_log('ART Database: Created/updated table ' . $table_name);
    }
    
    /**
     * Create art_intake_fields table
     *
     * @param string $charset_collate Database charset
     */
    private function create_intake_fields_table($charset_collate) {
        $table_name = $this->wpdb->prefix . $this->table_intake_fields;
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            request_id bigint(20) UNSIGNED NOT NULL,
            field_label varchar(255) NOT NULL,
            field_value text DEFAULT NULL,
            PRIMARY KEY (id),
            KEY request_id (request_id),
            KEY field_label (field_label)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        amelia_cpt_sync_debug_log('ART Database: Created/updated table ' . $table_name);
    }
    
    /**
     * Create art_booking_links table
     *
     * @param string $charset_collate Database charset
     */
    private function create_booking_links_table($charset_collate) {
        $table_name = $this->wpdb->prefix . $this->table_booking_links;
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            request_id bigint(20) UNSIGNED NOT NULL,
            amelia_appointment_id bigint(20) NOT NULL,
            amelia_booking_id bigint(20) DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY request_id (request_id),
            KEY amelia_appointment_id (amelia_appointment_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        amelia_cpt_sync_debug_log('ART Database: Created/updated table ' . $table_name);
    }
    
    /**
     * Create art_request_notes table
     *
     * @param string $charset_collate Database charset
     */
    private function create_request_notes_table($charset_collate) {
        $table_name = $this->wpdb->prefix . $this->table_request_notes;
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            request_id bigint(20) UNSIGNED NOT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            note_type varchar(50) NOT NULL DEFAULT 'user',
            note_text text NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY request_id (request_id),
            KEY user_id (user_id),
            KEY note_type (note_type),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        amelia_cpt_sync_debug_log('ART Database: Created/updated table ' . $table_name);
    }
    
    /**
     * Drop all ART module tables
     * Used for testing/development or uninstall
     *
     * @return bool True on success
     */
    public function drop_tables() {
        $tables = array(
            $this->wpdb->prefix . $this->table_booking_links,
            $this->wpdb->prefix . $this->table_request_notes,
            $this->wpdb->prefix . $this->table_intake_fields,
            $this->wpdb->prefix . $this->table_requests,
            $this->wpdb->prefix . $this->table_customers
        );
        
        foreach ($tables as $table) {
            $this->wpdb->query("DROP TABLE IF EXISTS $table");
            amelia_cpt_sync_debug_log('ART Database: Dropped table ' . $table);
        }
        
        delete_option(self::DB_VERSION_OPTION);
        
        return true;
    }
    
    /**
     * Get table name with prefix
     *
     * @param string $table_key Table key (customers, requests, etc.)
     * @return string Full table name with prefix
     */
    public function get_table_name($table_key) {
        $property = 'table_' . $table_key;
        if (property_exists($this, $property)) {
            return $this->wpdb->prefix . $this->$property;
        }
        return false;
    }
    
    /**
     * Find or create customer by email
     *
     * @param array $customer_data Customer data (first_name, last_name, email, phone)
     * @return int|false Customer ID on success, false on failure
     */
    public function find_or_create_customer($customer_data) {
        $table = $this->get_table_name('customers');
        
        // Check if customer exists by email
        $existing = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT id FROM $table WHERE email = %s",
            $customer_data['email']
        ));
        
        if ($existing) {
            amelia_cpt_sync_debug_log('ART Database: Found existing customer ID ' . $existing->id);
            return $existing->id;
        }
        
        // Create new customer
        $inserted = $this->wpdb->insert(
            $table,
            array(
                'first_name' => sanitize_text_field($customer_data['first_name']),
                'last_name' => sanitize_text_field($customer_data['last_name']),
                'email' => sanitize_email($customer_data['email']),
                'phone' => sanitize_text_field($customer_data['phone'] ?? ''),
                'amelia_customer_id' => isset($customer_data['amelia_customer_id']) ? intval($customer_data['amelia_customer_id']) : null
            ),
            array('%s', '%s', '%s', '%s', '%d')
        );
        
        if ($inserted) {
            $customer_id = $this->wpdb->insert_id;
            amelia_cpt_sync_debug_log('ART Database: Created new customer ID ' . $customer_id);
            return $customer_id;
        }
        
        return false;
    }
    
    /**
     * Create a new request
     *
     * @param array $request_data Request data
     * @return int|false Request ID on success, false on failure
     */
    public function create_request($request_data) {
        $table = $this->get_table_name('requests');
        
        $data = array(
            'customer_id' => intval($request_data['customer_id']),
            'status_key' => sanitize_key($request_data['status_key'] ?? 'requested'),
            'service_id' => isset($request_data['service_id']) ? intval($request_data['service_id']) : null,
            'location_id' => isset($request_data['location_id']) ? intval($request_data['location_id']) : null,
            'persons' => isset($request_data['persons']) ? intval($request_data['persons']) : 1,
            'start_datetime' => $request_data['start_datetime'] ?? null,
            'end_datetime' => $request_data['end_datetime'] ?? null,
            'duration_seconds' => isset($request_data['duration_seconds']) ? intval($request_data['duration_seconds']) : 0,
            'final_price' => isset($request_data['final_price']) ? floatval($request_data['final_price']) : null,
            'final_provider_id' => isset($request_data['final_provider_id']) ? intval($request_data['final_provider_id']) : null
        );
        
        $format = array('%d', '%s', '%d', '%d', '%d', '%s', '%s', '%d', '%f', '%d');
        
        $inserted = $this->wpdb->insert($table, $data, $format);
        
        if ($inserted) {
            $request_id = $this->wpdb->insert_id;
            amelia_cpt_sync_debug_log('ART Database: Created new request ID ' . $request_id);
            return $request_id;
        }
        
        return false;
    }
    
    /**
     * Add intake fields for a request
     *
     * @param int $request_id Request ID
     * @param array $fields Array of field_label => field_value
     * @return bool True on success
     */
    public function add_intake_fields($request_id, $fields) {
        $table = $this->get_table_name('intake_fields');
        
        foreach ($fields as $label => $value) {
            $this->wpdb->insert(
                $table,
                array(
                    'request_id' => intval($request_id),
                    'field_label' => sanitize_text_field($label),
                    'field_value' => sanitize_textarea_field($value)
                ),
                array('%d', '%s', '%s')
            );
        }
        
        amelia_cpt_sync_debug_log('ART Database: Added ' . count($fields) . ' intake fields for request ' . $request_id);
        
        return true;
    }
    
    /**
     * Delete customer and all related data
     * Manually handles cascade since we don't use foreign keys
     *
     * @param int $customer_id Customer ID
     * @return bool True on success
     */
    public function delete_customer($customer_id) {
        $customer_id = intval($customer_id);
        
        // Get all request IDs for this customer
        $requests_table = $this->get_table_name('requests');
        $request_ids = $this->wpdb->get_col($this->wpdb->prepare(
            "SELECT id FROM $requests_table WHERE customer_id = %d",
            $customer_id
        ));
        
        // Delete all requests (which will cascade to related tables)
        foreach ($request_ids as $request_id) {
            $this->delete_request($request_id);
        }
        
        // Delete customer
        $customers_table = $this->get_table_name('customers');
        $this->wpdb->delete($customers_table, array('id' => $customer_id), array('%d'));
        
        amelia_cpt_sync_debug_log('ART Database: Deleted customer ' . $customer_id . ' and all related data');
        
        return true;
    }
    
    /**
     * Delete request and all related data
     * Manually handles cascade since we don't use foreign keys
     *
     * @param int $request_id Request ID
     * @return bool True on success
     */
    public function delete_request($request_id) {
        $request_id = intval($request_id);
        
        // Delete intake fields
        $intake_table = $this->get_table_name('intake_fields');
        $this->wpdb->delete($intake_table, array('request_id' => $request_id), array('%d'));
        
        // Delete booking links
        $booking_table = $this->get_table_name('booking_links');
        $this->wpdb->delete($booking_table, array('request_id' => $request_id), array('%d'));
        
        // Delete request notes
        $notes_table = $this->get_table_name('request_notes');
        $this->wpdb->delete($notes_table, array('request_id' => $request_id), array('%d'));
        
        // Delete request
        $requests_table = $this->get_table_name('requests');
        $this->wpdb->delete($requests_table, array('id' => $request_id), array('%d'));
        
        amelia_cpt_sync_debug_log('ART Database: Deleted request ' . $request_id . ' and all related data');
        
        return true;
    }
    
    /**
     * Get current database version
     *
     * @return string Version number
     */
    public function get_version() {
        return get_option(self::DB_VERSION_OPTION, '0.0.0');
    }
    
    /**
     * Check if tables exist
     *
     * @return bool True if all tables exist
     */
    public function tables_exist() {
        $tables = array(
            $this->wpdb->prefix . $this->table_customers,
            $this->wpdb->prefix . $this->table_requests,
            $this->wpdb->prefix . $this->table_intake_fields,
            $this->wpdb->prefix . $this->table_booking_links,
            $this->wpdb->prefix . $this->table_request_notes
        );
        
        foreach ($tables as $table) {
            if ($this->wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
                return false;
            }
        }
        
        return true;
    }
}

