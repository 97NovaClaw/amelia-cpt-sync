<?php
/**
 * Field Detector Class
 *
 * Detects available meta fields for a CPT from various sources
 *
 * @package AmeliaCPTSync
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class Amelia_CPT_Sync_Field_Detector {
    
    /**
     * Get all available meta fields for a CPT
     *
     * @param string $cpt_slug The CPT slug
     * @return array Array of field names
     */
    public function get_cpt_meta_fields($cpt_slug) {
        if (empty($cpt_slug)) {
            return array();
        }
        
        $fields = array();
        
        // Method 1: Query existing postmeta keys (universal - works with ANY setup)
        $fields = array_merge($fields, $this->get_postmeta_keys($cpt_slug));
        
        // Method 2: JetEngine specific (if available)
        if (function_exists('jet_engine')) {
            $fields = array_merge($fields, $this->get_jetengine_fields($cpt_slug));
        }
        
        // Method 3: ACF (Advanced Custom Fields) specific (if available)
        if (function_exists('acf_get_field_groups')) {
            $fields = array_merge($fields, $this->get_acf_fields($cpt_slug));
        }
        
        // Remove duplicates and sort
        $fields = array_unique($fields);
        sort($fields);
        
        amelia_cpt_sync_debug_log("Detected fields for CPT '{$cpt_slug}': " . implode(', ', $fields));
        
        return $fields;
    }
    
    /**
     * Get meta keys from existing posts (universal method)
     *
     * @param string $cpt_slug The CPT slug
     * @return array Array of meta keys
     */
    private function get_postmeta_keys($cpt_slug) {
        global $wpdb;
        
        $keys = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT pm.meta_key
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE p.post_type = %s
            AND pm.meta_key NOT LIKE '\\_%%'
            ORDER BY pm.meta_key
            LIMIT 200
        ", $cpt_slug));
        
        return $keys ? $keys : array();
    }
    
    /**
     * Get JetEngine meta fields
     *
     * @param string $cpt_slug The CPT slug
     * @return array Array of field names
     */
    private function get_jetengine_fields($cpt_slug) {
        $fields = array();
        
        // Get JetEngine meta boxes from options
        $meta_boxes = get_option('jet_engine_meta_boxes', array());
        
        if (empty($meta_boxes)) {
            return $fields;
        }
        
        foreach ($meta_boxes as $box) {
            // Check if this meta box is for our CPT
            if (isset($box['args']['allowed_post_type']) && 
                is_array($box['args']['allowed_post_type']) &&
                in_array($cpt_slug, $box['args']['allowed_post_type'])) {
                
                // Get fields from this meta box
                if (isset($box['meta_fields']) && is_array($box['meta_fields'])) {
                    foreach ($box['meta_fields'] as $field) {
                        if (isset($field['name'])) {
                            $fields[] = $field['name'];
                        }
                    }
                }
            }
        }
        
        return $fields;
    }
    
    /**
     * Get ACF fields
     *
     * @param string $cpt_slug The CPT slug
     * @return array Array of field names
     */
    private function get_acf_fields($cpt_slug) {
        $fields = array();
        
        // Get field groups for this post type
        $field_groups = acf_get_field_groups(array(
            'post_type' => $cpt_slug
        ));
        
        if (empty($field_groups)) {
            return $fields;
        }
        
        foreach ($field_groups as $group) {
            // Get fields in this group
            $group_fields = acf_get_fields($group['key']);
            
            if ($group_fields) {
                foreach ($group_fields as $field) {
                    if (isset($field['name'])) {
                        $fields[] = $field['name'];
                    }
                }
            }
        }
        
        return $fields;
    }
    
    /**
     * Get taxonomy meta fields
     *
     * @param string $taxonomy_slug The taxonomy slug
     * @return array Array of term meta keys
     */
    public function get_taxonomy_meta_fields($taxonomy_slug) {
        if (empty($taxonomy_slug)) {
            return array();
        }
        
        global $wpdb;
        
        // Query existing term meta keys
        $keys = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT tm.meta_key
            FROM {$wpdb->termmeta} tm
            INNER JOIN {$wpdb->term_taxonomy} tt ON tm.term_id = tt.term_id
            WHERE tt.taxonomy = %s
            AND tm.meta_key NOT LIKE '\\_%%'
            ORDER BY tm.meta_key
            LIMIT 100
        ", $taxonomy_slug));
        
        return $keys ? $keys : array();
    }
}

