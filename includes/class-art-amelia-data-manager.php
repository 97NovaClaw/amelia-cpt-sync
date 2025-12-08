<?php
/**
 * ART Amelia Data Manager
 *
 * Single Source of Truth for READING data from Amelia.
 * - Uses Direct Database queries for performance.
 * - Returns standardized DTOs (Arrays).
 * - Implements Singleton pattern.
 *
 * @package AmeliaCPTSync
 * @subpackage ART
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class ART_Amelia_Data_Manager {

    /**
     * Singleton instance
     *
     * @var ART_Amelia_Data_Manager|null
     */
    private static $instance = null;

    /**
     * WordPress Database Object
     *
     * @var wpdb
     */
    private $wpdb;

    /**
     * Private constructor to enforce Singleton
     */
    private function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * Get the singleton instance
     *
     * @return ART_Amelia_Data_Manager
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get appointments for a date range (Direct DB)
     *
     * Optimizes the heavy API call (~184KB) into a lightweight SQL query.
     * Returns simplified DTOs.
     *
     * @param string $start_date Start date (Y-m-d)
     * @param string $end_date   End date (Y-m-d)
     * @param array  $filters    Optional filters (provider_id, service_id)
     * @return array List of appointment DTOs
     */
    public function get_appointments($start_date, $end_date, $filters = []) {
        // Ensure dates are strings
        $start_date = substr($start_date, 0, 10);
        $end_date = substr($end_date, 0, 10);

        $query = "
            SELECT 
                id, 
                serviceId AS service_id, 
                providerId AS provider_id, 
                locationId AS location_id, 
                bookingStart AS start_utc, 
                bookingEnd AS end_utc, 
                status
            FROM {$this->wpdb->prefix}amelia_appointments
            WHERE 
                status IN ('approved', 'pending')
                AND (
                    DATE(bookingStart) BETWEEN %s AND %s
                    OR DATE(bookingEnd) BETWEEN %s AND %s
                )
        ";

        $params = [$start_date, $end_date, $start_date, $end_date];

        if (!empty($filters['provider_id'])) {
            $query .= " AND providerId = %d";
            $params[] = $filters['provider_id'];
        }

        if (!empty($filters['service_id'])) {
            $query .= " AND serviceId = %d";
            $params[] = $filters['service_id'];
        }

        $query .= " ORDER BY bookingStart ASC";

        $results = $this->wpdb->get_results($this->wpdb->prepare($query, $params), ARRAY_A);

        return array_map([$this, 'normalize_appointment'], $results);
    }

    /**
     * Get service details (Cached DB Query)
     *
     * @param int $service_id
     * @return array|null Service DTO or null
     */
    public function get_service($service_id) {
        $cache_key = 'art_service_' . $service_id;
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        $row = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT id, name, duration, timeBefore, timeAfter, settings, categoryId 
             FROM {$this->wpdb->prefix}amelia_services 
             WHERE id = %d",
            $service_id
        ), ARRAY_A);

        if (!$row) {
            return null;
        }

        // Normalize
        $dto = [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'duration' => (int) $row['duration'],
            'buffer_before' => (int) $row['timeBefore'],
            'buffer_after' => (int) $row['timeAfter'],
            'category_id' => (int) $row['categoryId'],
            'settings' => json_decode($row['settings'], true)
        ];

        // Cache for 1 hour
        set_transient($cache_key, $dto, HOUR_IN_SECONDS);

        return $dto;
    }

    /**
     * Get provider basic details (Cached DB Query)
     * Does NOT fetch schedule (complex logic remains in API or Phase 2 DB refactor)
     *
     * @param int $provider_id
     * @return array|null Provider DTO or null
     */
    public function get_provider($provider_id) {
        $cache_key = 'art_provider_' . $provider_id;
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        $row = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT id, firstName, lastName, email 
             FROM {$this->wpdb->prefix}amelia_users 
             WHERE id = %d AND type = 'provider'",
            $provider_id
        ), ARRAY_A);

        if (!$row) {
            return null;
        }

        $dto = [
            'id' => (int) $row['id'],
            'name' => trim($row['firstName'] . ' ' . $row['lastName']),
            'email' => $row['email']
        ];

        set_transient($cache_key, $dto, HOUR_IN_SECONDS);

        return $dto;
    }

    /**
     * Normalize appointment DB row to standard DTO
     *
     * @param array $row DB row
     * @return array DTO
     */
    private function normalize_appointment($row) {
        return [
            'id' => (int) $row['id'],
            'service_id' => (int) $row['service_id'],
            'provider_id' => (int) $row['provider_id'],
            'location_id' => $row['location_id'] ? (int) $row['location_id'] : null,
            'resources' => [], // Resources column not available in DB (tracked via art_resource_assignments)
            'start_utc' => $row['start_utc'],
            'end_utc' => $row['end_utc'],
            'status' => $row['status']
        ];
    }
}

