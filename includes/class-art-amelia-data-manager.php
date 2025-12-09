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

        // Exclude specific appointment (for self-blocking prevention)
        if (!empty($filters['exclude_id'])) {
            $query .= " AND id != %d";
            $params[] = $filters['exclude_id'];
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
     * Get resource with linked entities (Direct DB)
     *
     * Replaces API call to /resources/{id}
     * Fetches from wp_amelia_resources + wp_amelia_resources_to_entities
     *
     * @param int $resource_id Resource ID
     * @return array|null Resource DTO or null
     */
    public function get_resource($resource_id) {
        $cache_key = 'art_resource_' . $resource_id;
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        // Query resource with entity links
        $query = "
            SELECT 
                r.id,
                r.name,
                r.quantity,
                r.shared,
                r.status,
                rte.id AS entity_link_id,
                rte.entityId,
                rte.entityType
            FROM {$this->wpdb->prefix}amelia_resources r
            LEFT JOIN {$this->wpdb->prefix}amelia_resources_to_entities rte ON r.id = rte.resourceId
            WHERE r.id = %d
        ";

        $rows = $this->wpdb->get_results($this->wpdb->prepare($query, $resource_id), ARRAY_A);

        if (empty($rows)) {
            return null;
        }

        // Normalize: group entities under single resource object
        $dto = $this->normalize_resource_rows($rows);

        // Cache for 1 hour
        set_transient($cache_key, $dto, HOUR_IN_SECONDS);

        return $dto;
    }

    /**
     * Get all resources with linked entities (Direct DB)
     *
     * Replaces API call to /resources
     *
     * @return array Array of resource DTOs
     */
    public function get_all_resources() {
        $cache_key = 'art_all_resources';
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        $query = "
            SELECT 
                r.id,
                r.name,
                r.quantity,
                r.shared,
                r.status,
                rte.id AS entity_link_id,
                rte.entityId,
                rte.entityType
            FROM {$this->wpdb->prefix}amelia_resources r
            LEFT JOIN {$this->wpdb->prefix}amelia_resources_to_entities rte ON r.id = rte.resourceId
            ORDER BY r.id, rte.id
        ";

        $rows = $this->wpdb->get_results($query, ARRAY_A);

        if (empty($rows)) {
            return [];
        }

        // Group rows by resource ID
        $resources_by_id = [];
        foreach ($rows as $row) {
            $resource_id = $row['id'];
            if (!isset($resources_by_id[$resource_id])) {
                $resources_by_id[$resource_id] = [];
            }
            $resources_by_id[$resource_id][] = $row;
        }

        // Normalize each group
        $dtos = [];
        foreach ($resources_by_id as $resource_rows) {
            $dtos[] = $this->normalize_resource_rows($resource_rows);
        }

        // Cache for 1 hour
        set_transient($cache_key, $dtos, HOUR_IN_SECONDS);

        return $dtos;
    }

    /**
     * Get resources linked to a specific service (Direct DB)
     *
     * @param int $service_id Service ID
     * @return array Array of resource DTOs
     */
    public function get_resources_for_service($service_id) {
        $query = "
            SELECT 
                r.id,
                r.name,
                r.quantity,
                r.shared,
                r.status
            FROM {$this->wpdb->prefix}amelia_resources r
            JOIN {$this->wpdb->prefix}amelia_resources_to_entities rte ON r.id = rte.resourceId
            WHERE rte.entityType = 'service' AND rte.entityId = %d
        ";

        $rows = $this->wpdb->get_results($this->wpdb->prepare($query, $service_id), ARRAY_A);

        if (empty($rows)) {
            return [];
        }

        // For this query, we don't need to group (1 row = 1 resource)
        return array_map([$this, 'normalize_resource_simple'], $rows);
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

    /**
     * Normalize multiple resource rows into single DTO
     * (Handles JOINed data where 1 resource = multiple rows due to multiple entities)
     *
     * @param array $rows Multiple DB rows for same resource
     * @return array Resource DTO
     */
    private function normalize_resource_rows($rows) {
        // All rows have same resource data, different entity links
        $first = $rows[0];

        $dto = [
            'id' => (int) $first['id'],
            'name' => $first['name'],
            'quantity' => (int) $first['quantity'],
            'shared' => (bool) $first['shared'],
            'status' => $first['status'],
            'entities' => []
        ];

        // Collect all entity links
        foreach ($rows as $row) {
            if (!empty($row['entity_link_id'])) {
                $dto['entities'][] = [
                    'entity_type' => $row['entityType'],
                    'entity_id' => (int) $row['entityId']
                ];
            }
        }

        return $dto;
    }

    /**
     * Normalize simple resource row (no entity grouping needed)
     *
     * @param array $row DB row
     * @return array Resource DTO
     */
    private function normalize_resource_simple($row) {
        return [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'quantity' => (int) $row['quantity'],
            'shared' => (bool) $row['shared'],
            'status' => $row['status']
        ];
    }
}

