<?php
/**
 * ART Notes Manager Class
 *
 * Handles all note operations for the ART (Amelia Request Triage) module
 * Manages manual staff notes, auto-generated system events, and booking events
 *
 * @package AmeliaCPTSync
 * @subpackage ART
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class ART_Notes_Manager {
    
    /**
     * Add a manual note (staff-written)
     *
     * @param int $request_id Request ID
     * @param string $note_content Note content (HTML allowed: strong, b, ul, li)
     * @param int $user_id WordPress user ID
     * @return int|WP_Error Note ID or error
     */
    public function add_manual_note($request_id, $note_content, $user_id) {
        global $wpdb;
        
        // Validate length (max 1000 chars plain text)
        $text_only = wp_strip_all_tags($note_content);
        if (strlen($text_only) > 1000) {
            return new WP_Error('note_too_long', __('Note exceeds 1000 characters', 'amelia-cpt-sync'));
        }
        
        if (empty(trim($text_only))) {
            return new WP_Error('note_empty', __('Note cannot be empty', 'amelia-cpt-sync'));
        }
        
        // Sanitize HTML (only allow: <strong>, <b>, <ul>, <li>)
        $allowed_tags = array(
            'strong' => array(),
            'b' => array(),
            'ul' => array(),
            'li' => array(),
            'br' => array()
        );
        $sanitized_content = wp_kses($note_content, $allowed_tags);
        
        // Insert into database
        $result = $wpdb->insert(
            $wpdb->prefix . 'art_notes',
            array(
                'request_id' => absint($request_id),
                'note_type' => 'manual',
                'note_content' => $sanitized_content,
                'created_by' => absint($user_id),
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%d', '%s')
        );
        
        if ($result === false) {
            return new WP_Error('insert_failed', __('Failed to create note', 'amelia-cpt-sync'));
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update a manual note (edit)
     *
     * @param int $note_id Note ID
     * @param string $note_content Updated content
     * @param int $user_id WordPress user ID (for permission check)
     * @return bool|WP_Error True on success or error
     */
    public function update_note($note_id, $note_content, $user_id) {
        global $wpdb;
        
        // Verify user owns this note
        $note = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}art_notes WHERE id = %d",
            $note_id
        ));
        
        if (!$note) {
            return new WP_Error('note_not_found', __('Note not found', 'amelia-cpt-sync'));
        }
        
        if ($note->note_type !== 'manual') {
            return new WP_Error('cannot_edit_system_note', __('Cannot edit system notes', 'amelia-cpt-sync'));
        }
        
        if (intval($note->created_by) !== intval($user_id) && !current_user_can('manage_options')) {
            return new WP_Error('permission_denied', __('You can only edit your own notes', 'amelia-cpt-sync'));
        }
        
        // Validate + sanitize
        $text_only = wp_strip_all_tags($note_content);
        if (strlen($text_only) > 1000) {
            return new WP_Error('note_too_long', __('Note exceeds 1000 characters', 'amelia-cpt-sync'));
        }
        
        $allowed_tags = array(
            'strong' => array(),
            'b' => array(),
            'ul' => array(),
            'li' => array(),
            'br' => array()
        );
        $sanitized_content = wp_kses($note_content, $allowed_tags);
        
        // Update note_content and updated_at
        $result = $wpdb->update(
            $wpdb->prefix . 'art_notes',
            array(
                'note_content' => $sanitized_content,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $note_id),
            array('%s', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('update_failed', __('Failed to update note', 'amelia-cpt-sync'));
        }
        
        return true;
    }
    
    /**
     * Delete a note (hard delete)
     *
     * @param int $note_id Note ID
     * @param int $user_id WordPress user ID (for permission check)
     * @return bool|WP_Error True on success or error
     */
    public function delete_note($note_id, $user_id) {
        global $wpdb;
        
        // Verify user owns this note (or is admin)
        $note = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}art_notes WHERE id = %d",
            $note_id
        ));
        
        if (!$note) {
            return new WP_Error('note_not_found', __('Note not found', 'amelia-cpt-sync'));
        }
        
        if ($note->note_type !== 'manual') {
            return new WP_Error('cannot_delete_system_note', __('Cannot delete system notes', 'amelia-cpt-sync'));
        }
        
        if (intval($note->created_by) !== intval($user_id) && !current_user_can('manage_options')) {
            return new WP_Error('permission_denied', __('You can only delete your own notes', 'amelia-cpt-sync'));
        }
        
        // Hard delete from database
        $result = $wpdb->delete(
            $wpdb->prefix . 'art_notes',
            array('id' => $note_id),
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('delete_failed', __('Failed to delete note', 'amelia-cpt-sync'));
        }
        
        return true;
    }
    
    /**
     * Add a system note (auto-generated)
     *
     * @param int $request_id Request ID
     * @param string $note_content Note content
     * @param array|null $metadata Optional metadata (stored as JSON)
     * @return int|WP_Error Note ID or error
     */
    public function add_system_note($request_id, $note_content, $metadata = null) {
        global $wpdb;
        
        $metadata_json = null;
        if ($metadata && is_array($metadata)) {
            $metadata_json = json_encode($metadata);
        }
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'art_notes',
            array(
                'request_id' => absint($request_id),
                'note_type' => 'system',
                'note_content' => sanitize_text_field($note_content),
                'created_by' => null,
                'created_at' => current_time('mysql'),
                'metadata' => $metadata_json
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            return new WP_Error('insert_failed', __('Failed to create system note', 'amelia-cpt-sync'));
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Add a booking event note (auto-generated from changes)
     *
     * @param int $request_id Request ID
     * @param string $event_type Event type (booking_created, booking_rescheduled, etc.)
     * @param array $changes Changes array from Booking Manager
     * @param int|null $user_id WordPress user ID who made the change
     * @return int|WP_Error Note ID or error
     */
    public function add_booking_event($request_id, $event_type, $changes, $user_id = null) {
        global $wpdb;
        
        // Generate human-readable message from changes
        $message = $this->format_booking_event($event_type, $changes);
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'art_notes',
            array(
                'request_id' => absint($request_id),
                'note_type' => 'booking_event',
                'note_content' => $message,
                'created_by' => $user_id ? absint($user_id) : null,
                'created_at' => current_time('mysql'),
                'metadata' => json_encode(array(
                    'event_type' => $event_type,
                    'changes' => $changes
                ))
            ),
            array('%d', '%s', '%s', '%d', '%s', '%s')
        );
        
        if ($result === false) {
            return new WP_Error('insert_failed', __('Failed to create booking event note', 'amelia-cpt-sync'));
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Format booking event into human-readable message
     *
     * @param string $event_type Event type
     * @param array $changes Changes array
     * @return string Formatted message
     */
    private function format_booking_event($event_type, $changes) {
        switch ($event_type) {
            case 'booking_created':
                $status = ucfirst($changes['status'] ?? 'tentative');
                $provider_name = $changes['provider_name'] ?? 'Provider #' . ($changes['provider_id'] ?? '');
                $datetime = $this->format_datetime($changes['datetime'] ?? '');
                $service_name = $changes['service_name'] ?? '';
                $location_name = $changes['location_name'] ?? '';
                
                $msg = sprintf(__('Booking created (%s)', 'amelia-cpt-sync'), $status) . "\n";
                if ($provider_name) $msg .= sprintf(__('Provider: %s', 'amelia-cpt-sync'), $provider_name) . "\n";
                if ($service_name) $msg .= sprintf(__('Service: %s', 'amelia-cpt-sync'), $service_name) . "\n";
                if ($datetime) $msg .= sprintf(__('Date: %s', 'amelia-cpt-sync'), $datetime) . "\n";
                if ($location_name) $msg .= sprintf(__('Location: %s', 'amelia-cpt-sync'), $location_name);
                
                return $msg;
            
            case 'booking_rescheduled':
                $from_datetime = $this->format_datetime($changes['from_datetime'] ?? '');
                $to_datetime = $this->format_datetime($changes['to_datetime'] ?? '');
                $from_provider = $changes['from_provider'] ?? '';
                $to_provider = $changes['to_provider'] ?? '';
                
                $msg = __('Booking rescheduled', 'amelia-cpt-sync') . "\n";
                if ($from_datetime && $to_datetime) {
                    $msg .= sprintf(__('From: %s', 'amelia-cpt-sync'), $from_datetime) . "\n";
                    $msg .= sprintf(__('To: %s', 'amelia-cpt-sync'), $to_datetime);
                }
                if ($from_provider != $to_provider && $to_provider) {
                    $msg .= "\n" . sprintf(__('Provider changed to: #%s', 'amelia-cpt-sync'), $to_provider);
                }
                
                return $msg;
            
            case 'status_upgraded':
                return __('Status upgraded to Confirmed', 'amelia-cpt-sync');
            
            case 'status_downgraded':
                return __('Status downgraded to Tentative', 'amelia-cpt-sync');
            
            case 'booking_canceled':
                return __('Booking canceled', 'amelia-cpt-sync');
            
            case 'provider_changed':
                $from = $changes['from_provider_name'] ?? 'Provider #' . ($changes['from'] ?? '');
                $to = $changes['to_provider_name'] ?? 'Provider #' . ($changes['to'] ?? '');
                return sprintf(__('Provider changed from %s to %s', 'amelia-cpt-sync'), $from, $to);
            
            case 'datetime_changed':
                $from = $this->format_datetime($changes['from'] ?? '');
                $to = $this->format_datetime($changes['to'] ?? '');
                return sprintf(__('Date/time changed from %s to %s', 'amelia-cpt-sync'), $from, $to);
            
            default:
                return sprintf(__('Booking event: %s', 'amelia-cpt-sync'), $event_type);
        }
    }
    
    /**
     * Format datetime for display
     *
     * @param string $datetime Datetime string
     * @return string Formatted datetime
     */
    private function format_datetime($datetime) {
        if (empty($datetime)) {
            return '';
        }
        
        $timestamp = strtotime($datetime);
        if (!$timestamp) {
            return $datetime;
        }
        
        return date_i18n('l, F jS, Y \a\t g:i A', $timestamp);
    }
    
    /**
     * Get notes for a request (chronological ASC - oldest first)
     *
     * @param int $request_id Request ID
     * @param int $offset Starting offset for pagination
     * @param int $limit Number of notes to retrieve
     * @return array Array of notes with user data
     */
    public function get_notes($request_id, $offset = 0, $limit = 20) {
        global $wpdb;
        
        $notes = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                n.*,
                u.display_name as author_name,
                CONCAT(SUBSTRING(u.display_name, 1, 1), SUBSTRING(SUBSTRING_INDEX(u.display_name, ' ', -1), 1, 1)) as author_initials
            FROM {$wpdb->prefix}art_notes n
            LEFT JOIN {$wpdb->users} u ON n.created_by = u.ID
            WHERE n.request_id = %d
            ORDER BY n.created_at ASC
            LIMIT %d OFFSET %d",
            $request_id,
            $limit,
            $offset
        ), ARRAY_A);
        
        // Add time_ago for each note
        foreach ($notes as &$note) {
            $note['time_ago'] = $this->time_ago($note['created_at']);
        }
        
        return $notes;
    }
    
    /**
     * Get a single note by ID (for returning after creation)
     *
     * @param int $note_id Note ID
     * @return array|null Note data or null
     */
    public function get_note_by_id($note_id) {
        global $wpdb;
        
        $note = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                n.*,
                u.display_name as author_name,
                CONCAT(SUBSTRING(u.display_name, 1, 1), SUBSTRING(SUBSTRING_INDEX(u.display_name, ' ', -1), 1, 1)) as author_initials
            FROM {$wpdb->prefix}art_notes n
            LEFT JOIN {$wpdb->users} u ON n.created_by = u.ID
            WHERE n.id = %d",
            $note_id
        ), ARRAY_A);
        
        if ($note) {
            $note['time_ago'] = $this->time_ago($note['created_at']);
        }
        
        return $note;
    }
    
    /**
     * Get total note count (for infinite scroll)
     *
     * @param int $request_id Request ID
     * @return int Total count
     */
    public function get_note_count($request_id) {
        global $wpdb;
        
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}art_notes WHERE request_id = %d",
            $request_id
        ));
    }
    
    /**
     * Calculate "time ago" string
     *
     * @param string $datetime Datetime string
     * @return string Time ago string (e.g., "5 minutes ago")
     */
    private function time_ago($datetime) {
        $timestamp = strtotime($datetime);
        $diff = time() - $timestamp;
        
        if ($diff < 60) {
            return __('Just now', 'amelia-cpt-sync');
        } elseif ($diff < 3600) {
            $minutes = round($diff / 60);
            return sprintf(_n('%s minute ago', '%s minutes ago', $minutes, 'amelia-cpt-sync'), $minutes);
        } elseif ($diff < 86400) {
            $hours = round($diff / 3600);
            return sprintf(_n('%s hour ago', '%s hours ago', $hours, 'amelia-cpt-sync'), $hours);
        } elseif ($diff < 604800) {
            $days = round($diff / 86400);
            return sprintf(_n('%s day ago', '%s days ago', $days, 'amelia-cpt-sync'), $days);
        } else {
            return date_i18n('M j, Y \a\t g:i A', $timestamp);
        }
    }
}

