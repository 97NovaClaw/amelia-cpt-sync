<?php
/**
 * ART Time Helper
 *
 * Enforces the UTC Firewall strategy.
 * - Database interactions MUST use UTC.
 * - Frontend interactions MUST use WordPress Timezone.
 * - This class handles all conversions.
 *
 * @package AmeliaCPTSync
 * @subpackage ART
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class ART_Time_Helper {

    /**
     * Convert Local Time (WP Timezone) to UTC (for DB storage/query)
     *
     * @param string $local_time_string Date string in WP timezone (e.g. "2025-12-05 10:00:00")
     * @param bool   $include_time      Whether to include time component
     * @return string UTC date string
     */
    public static function to_utc($local_time_string, $include_time = true) {
        if (empty($local_time_string)) {
            return '';
        }

        try {
            $wp_tz = wp_timezone();
            $utc_tz = new DateTimeZone('UTC');

            // Create DateTime object with Local Timezone
            $dt = new DateTime($local_time_string, $wp_tz);
            
            // Convert to UTC
            $dt->setTimezone($utc_tz);

            return $dt->format($include_time ? 'Y-m-d H:i:s' : 'Y-m-d');
        } catch (Exception $e) {
            amelia_cpt_sync_debug_log('ART Time Helper Error: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Convert UTC Time (from DB) to Local Time (for Frontend display)
     *
     * @param string $utc_time_string Date string in UTC (e.g. "2025-12-05 14:00:00")
     * @param string $format          Output format (default: Y-m-d H:i:s)
     * @return string Local date string
     */
    public static function to_local($utc_time_string, $format = 'Y-m-d H:i:s') {
        if (empty($utc_time_string)) {
            return '';
        }

        try {
            $wp_tz = wp_timezone();
            $utc_tz = new DateTimeZone('UTC');

            // Create DateTime object with UTC
            $dt = new DateTime($utc_time_string, $utc_tz);
            
            // Convert to Local Timezone
            $dt->setTimezone($wp_tz);

            return $dt->format($format);
        } catch (Exception $e) {
            amelia_cpt_sync_debug_log('ART Time Helper Error: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Get current time in UTC (for DB timestamps like created_at)
     *
     * @return string UTC datetime string
     */
    public static function now_utc() {
        return gmdate('Y-m-d H:i:s');
    }

    /**
     * Get current time in Local WP Timezone (for display defaults)
     *
     * @return string Local datetime string
     */
    public static function now_local() {
        return current_time('mysql'); // Returns local time
    }

    /**
     * Check if two time ranges overlap (in minutes or timestamps)
     *
     * @param int $start1 Start of range 1
     * @param int $end1   End of range 1
     * @param int $start2 Start of range 2
     * @param int $end2   End of range 2
     * @return bool True if overlap
     */
    public static function times_overlap($start1, $end1, $start2, $end2) {
        return ($start1 < $end2) && ($end1 > $start2);
    }
}

