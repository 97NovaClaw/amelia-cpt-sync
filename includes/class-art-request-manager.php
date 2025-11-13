<?php
/**
 * ART Request Manager
 *
 * Handles CRUD operations for triage requests
 *
 * @package AmeliaCPTSync
 * @subpackage ART
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class Amelia_CPT_Sync_ART_Request_Manager {
    
    /**
     * Get all triage requests with optional filtering
     *
     * @param array $args Query arguments
     * @return array Array of request objects
     */
    public function get_requests($args = array()) {
        global $wpdb;
        
        // Default arguments
        $defaults = array(
            'status' => '',           // Filter by status (status_key in DB)
            'search' => '',           // Search term (customer name, email)
            'service_id' => '',       // Filter by service ID
            'date_from' => '',        // Filter by submitted date (from)
            'date_to' => '',          // Filter by submitted date (to)
            'form_id' => '',          // Filter by form config
            'orderby' => 'created_at',  // Column to order by
            'order' => 'DESC',        // ASC or DESC
            'per_page' => 20,         // Results per page
            'paged' => 1              // Current page
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Build the query
        $requests_table = $wpdb->prefix . 'art_requests';
        $customers_table = $wpdb->prefix . 'art_customers';
        
        $where = array('1=1');
        $join = "LEFT JOIN {$customers_table} ON {$requests_table}.customer_id = {$customers_table}.id";
        
        // Status filter
        if (!empty($args['status'])) {
            $where[] = $wpdb->prepare("{$requests_table}.status_key = %s", strtolower($args['status']));
        }
        
        // Service filter
        if (!empty($args['service_id'])) {
            $where[] = $wpdb->prepare("{$requests_table}.service_id = %d", $args['service_id']);
        }
        
        // Date range filter (submitted date)
        if (!empty($args['date_from'])) {
            $where[] = $wpdb->prepare("{$requests_table}.created_at >= %s", $args['date_from'] . ' 00:00:00');
        }
        if (!empty($args['date_to'])) {
            $where[] = $wpdb->prepare("{$requests_table}.created_at <= %s", $args['date_to'] . ' 23:59:59');
        }
        
        // Form filter (Note: Database doesn't have form_config_id column yet - will add in migration)
        if (!empty($args['form_id'])) {
            // Skip for now - column doesn't exist yet
            // $where[] = $wpdb->prepare("{$requests_table}.form_config_id = %s", $args['form_id']);
        }
        
        // Search filter (customer name, email, or service name)
        if (!empty($args['search'])) {
            $search_like = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[] = $wpdb->prepare(
                "({$customers_table}.first_name LIKE %s OR {$customers_table}.last_name LIKE %s OR {$customers_table}.email LIKE %s OR cpt.post_title LIKE %s)",
                $search_like,
                $search_like,
                $search_like,
                $search_like
            );
        }
        
        // Build WHERE clause
        $where_clause = implode(' AND ', $where);
        
        // Sanitize ORDER BY
        $allowed_orderby = array('created_at', 'status_key', 'last_activity_at', 'id');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        // Calculate offset
        $per_page = absint($args['per_page']);
        $paged = absint($args['paged']);
        $offset = ($paged - 1) * $per_page;
        
        // Get total count (for pagination)
        $count_sql = "SELECT COUNT(*) FROM {$requests_table} {$join} WHERE {$where_clause}";
        $total_items = $wpdb->get_var($count_sql);
        
        // Get CPT slug from main plugin settings (for service name lookup)
        $main_settings = get_option('amelia_cpt_sync_settings', array());
        $cpt_slug = $main_settings['cpt_slug'] ?? 'vehicles';  // Default to vehicles
        
        // Get results with service name from CPT (if available)
        $sql = "SELECT 
                    {$requests_table}.*,
                    {$customers_table}.first_name as customer_first_name,
                    {$customers_table}.last_name as customer_last_name,
                    {$customers_table}.email as customer_email,
                    {$customers_table}.phone as customer_phone,
                    cpt.post_title as service_name,
                    cpt.ID as service_cpt_id
                FROM {$requests_table}
                {$join}
                LEFT JOIN {$wpdb->postmeta} pm ON {$requests_table}.service_id = pm.meta_value AND pm.meta_key = '_amelia_service_id'
                LEFT JOIN {$wpdb->posts} cpt ON pm.post_id = cpt.ID AND cpt.post_type = %s AND cpt.post_status = 'publish'
                WHERE {$where_clause}
                ORDER BY {$requests_table}.{$orderby} {$order}
                LIMIT {$per_page} OFFSET {$offset}";
        
        $results = $wpdb->get_results($wpdb->prepare($sql, $cpt_slug));
        
        return array(
            'items' => $results,
            'total' => $total_items,
            'per_page' => $per_page,
            'current_page' => $paged,
            'total_pages' => ceil($total_items / $per_page)
        );
    }
    
    /**
     * Get a single request by ID with all related data
     *
     * @param int $request_id Request ID
     * @return object|null Request object or null
     */
    public function get_request($request_id) {
        global $wpdb;
        
        $requests_table = $wpdb->prefix . 'art_requests';
        $customers_table = $wpdb->prefix . 'art_customers';
        $intake_table = $wpdb->prefix . 'art_intake_fields';
        $booking_table = $wpdb->prefix . 'art_booking_links';
        $notes_table = $wpdb->prefix . 'art_request_notes';
        
        // Get main request data with customer
        $sql = $wpdb->prepare(
            "SELECT 
                r.*,
                c.first_name as customer_first_name,
                c.last_name as customer_last_name,
                c.email as customer_email,
                c.phone as customer_phone,
                c.amelia_customer_id
            FROM {$requests_table} r
            LEFT JOIN {$customers_table} c ON r.customer_id = c.id
            WHERE r.id = %d",
            $request_id
        );
        
        $request = $wpdb->get_row($sql);
        
        if (!$request) {
            return null;
        }
        
        // Get intake fields
        $intake_sql = $wpdb->prepare(
            "SELECT field_label, field_value FROM {$intake_table} WHERE request_id = %d",
            $request_id
        );
        $request->intake_fields = $wpdb->get_results($intake_sql);
        
        // Get booking links
        $booking_sql = $wpdb->prepare(
            "SELECT * FROM {$booking_table} WHERE request_id = %d ORDER BY linked_at DESC",
            $request_id
        );
        $request->bookings = $wpdb->get_results($booking_sql);
        
        // Get notes
        $notes_sql = $wpdb->prepare(
            "SELECT n.*, u.display_name as author_name
             FROM {$notes_table} n
             LEFT JOIN {$wpdb->users} u ON n.user_id = u.ID
             WHERE n.request_id = %d
             ORDER BY n.created_at DESC",
            $request_id
        );
        $request->notes = $wpdb->get_results($notes_sql);
        
        return $request;
    }
    
    /**
     * Update request status
     *
     * @param int $request_id Request ID
     * @param string $new_status New status
     * @return bool Success
     */
    public function update_status($request_id, $new_status) {
        global $wpdb;
        
        $allowed_statuses = array('Requested', 'Responded', 'Tentative', 'Booked', 'Abandoned');
        
        if (!in_array($new_status, $allowed_statuses)) {
            return false;
        }
        
        $table = $wpdb->prefix . 'art_requests';
        $status_key = strtolower($new_status);
        
        // Update data array based on status
        $update_data = array(
            'status_key' => $status_key,
            'last_activity_at' => current_time('mysql', 1)
        );
        
        // Update timestamp columns based on status
        if ($new_status === 'Responded') {
            $update_data['responded_at'] = current_time('mysql', 1);
        } elseif ($new_status === 'Tentative') {
            $update_data['tentative_at'] = current_time('mysql', 1);
        } elseif ($new_status === 'Booked') {
            $update_data['booked_at'] = current_time('mysql', 1);
        }
        
        $result = $wpdb->update(
            $table,
            $update_data,
            array('id' => $request_id),
            array_fill(0, count($update_data), '%s'),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Add a note to a request
     *
     * @param int $request_id Request ID
     * @param string $note_text Note content
     * @param int $user_id User ID (defaults to current user)
     * @return int|false Note ID or false on failure
     */
    public function add_note($request_id, $note_text, $user_id = null) {
        global $wpdb;
        
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        
        $table = $wpdb->prefix . 'art_request_notes';
        
        $result = $wpdb->insert(
            $table,
            array(
                'request_id' => $request_id,
                'user_id' => $user_id,
                'note_text' => sanitize_textarea_field($note_text),
                'created_at' => current_time('mysql', 1)
            ),
            array('%d', '%d', '%s', '%s')
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Link an Amelia booking to a request
     *
     * @param int $request_id Request ID
     * @param int $amelia_booking_id Amelia booking ID
     * @param int $amelia_appointment_id Amelia appointment ID
     * @param string $booking_status Booking status
     * @return int|false Link ID or false on failure
     */
    public function link_booking($request_id, $amelia_booking_id, $amelia_appointment_id = null, $booking_status = 'approved') {
        global $wpdb;
        
        $table = $wpdb->prefix . 'art_booking_links';
        
        $result = $wpdb->insert(
            $table,
            array(
                'request_id' => $request_id,
                'amelia_booking_id' => $amelia_booking_id,
                'amelia_appointment_id' => $amelia_appointment_id,
                'linked_at' => current_time('mysql', 1),
                'booking_status' => $booking_status
            ),
            array('%d', '%d', '%d', '%s', '%s')
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Get status counts for all requests (for filter chips)
     *
     * @return array Status counts
     */
    public function get_status_counts() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'art_requests';
        
        $sql = "SELECT status_key, COUNT(*) as count FROM {$table} GROUP BY status_key";
        $results = $wpdb->get_results($sql);
        
        $counts = array(
            'all' => 0,
            'Requested' => 0,
            'Responded' => 0,
            'Tentative' => 0,
            'Booked' => 0,
            'Abandoned' => 0
        );
        
        foreach ($results as $row) {
            // Convert status_key (lowercase) to display name (ucfirst)
            $status_display = ucfirst($row->status_key);
            $counts[$status_display] = (int) $row->count;
            $counts['all'] += (int) $row->count;
        }
        
        return $counts;
    }
    
    /**
     * Get unique services with names (for filter dropdown)
     *
     * @return array Array of service objects
     */
    public function get_unique_services() {
        global $wpdb;
        
        $requests_table = $wpdb->prefix . 'art_requests';
        
        // Get CPT slug
        $main_settings = get_option('amelia_cpt_sync_settings', array());
        $cpt_slug = $main_settings['cpt_slug'] ?? 'vehicles';
        
        $sql = "SELECT DISTINCT
                    r.service_id,
                    cpt.post_title as service_name
                FROM {$requests_table} r
                LEFT JOIN {$wpdb->postmeta} pm ON r.service_id = pm.meta_value AND pm.meta_key = '_amelia_service_id'
                LEFT JOIN {$wpdb->posts} cpt ON pm.post_id = cpt.ID AND cpt.post_type = %s AND cpt.post_status = 'publish'
                WHERE r.service_id IS NOT NULL AND r.service_id > 0
                ORDER BY cpt.post_title ASC, r.service_id ASC";
        
        return $wpdb->get_results($wpdb->prepare($sql, $cpt_slug));
    }
    
    /**
     * Delete a request and all related data
     *
     * @param int $request_id Request ID
     * @return bool Success
     */
    public function delete_request($request_id) {
        global $wpdb;
        
        // Delete related data first
        $intake_table = $wpdb->prefix . 'art_intake_fields';
        $booking_table = $wpdb->prefix . 'art_booking_links';
        $notes_table = $wpdb->prefix . 'art_request_notes';
        $requests_table = $wpdb->prefix . 'art_requests';
        
        $wpdb->delete($intake_table, array('request_id' => $request_id), array('%d'));
        $wpdb->delete($booking_table, array('request_id' => $request_id), array('%d'));
        $wpdb->delete($notes_table, array('request_id' => $request_id), array('%d'));
        
        // Delete the request
        $result = $wpdb->delete($requests_table, array('id' => $request_id), array('%d'));
        
        return $result !== false;
    }
}

