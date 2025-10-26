<?php
/**
 * CPT Manager Class
 *
 * Handles all CPT and Taxonomy interactions based on saved settings
 *
 * @package AmeliaCPTSync
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class Amelia_CPT_Sync_CPT_Manager {
    
    /**
     * The settings option name
     */
    private $option_name = 'amelia_cpt_sync_settings';
    
    /**
     * Get settings from wp_options
     */
    private function get_settings() {
        return get_option('amelia_cpt_sync_settings', false);
    }
    
    /**
     * Create or update a CPT post from Amelia service data
     *
     * @param array $service_data The Amelia service data
     * @return int|WP_Error Post ID on success, WP_Error on failure
     */
    public function sync_service($service_data) {
        $settings = $this->get_settings();
        
        if (!$settings || empty($settings['cpt_slug'])) {
            amelia_cpt_sync_debug_log('ERROR: No CPT sync settings configured');
            return new WP_Error('no_settings', 'No CPT sync settings configured');
        }
        
        // Get Amelia service ID
        $amelia_service_id = isset($service_data['id']) ? intval($service_data['id']) : 0;
        
        if (!$amelia_service_id) {
            amelia_cpt_sync_debug_log('ERROR: No Amelia service ID provided');
            return new WP_Error('no_service_id', 'No Amelia service ID provided');
        }
        
        amelia_cpt_sync_debug_log('Starting service sync for Amelia Service ID: ' . $amelia_service_id);
        
        // Check if post already exists
        $existing_post_id = $this->find_post_by_amelia_id($amelia_service_id, $settings['cpt_slug']);
        
        // Prepare post data
        $post_data = array(
            'post_type' => $settings['cpt_slug'],
            'post_title' => isset($service_data['name']) ? sanitize_text_field($service_data['name']) : '',
            'post_content' => isset($service_data['description']) ? wp_kses_post($service_data['description']) : '',
            'post_status' => 'publish'
        );
        
        // Update or insert post
        if ($existing_post_id) {
            $post_data['ID'] = $existing_post_id;
            $post_id = wp_update_post($post_data);
            amelia_cpt_sync_debug_log('Updating existing CPT post ID: ' . $existing_post_id);
        } else {
            $post_id = wp_insert_post($post_data);
            amelia_cpt_sync_debug_log('Creating new CPT post');
        }
        
        if (is_wp_error($post_id)) {
            amelia_cpt_sync_debug_log('ERROR: Failed to save CPT post: ' . $post_id->get_error_message());
            return $post_id;
        }
        
        amelia_cpt_sync_debug_log('Successfully saved CPT post ID: ' . $post_id);
        
        // Store Amelia service ID as meta for future lookups
        update_post_meta($post_id, '_amelia_service_id', $amelia_service_id);
        
        // Handle primary photo (custom field)
        if (!empty($settings['field_mappings']['primary_photo']) && isset($service_data['pictureFullPath']) && !empty($service_data['pictureFullPath'])) {
            $this->set_primary_photo($post_id, $service_data['pictureFullPath'], $settings['field_mappings']['primary_photo']);
        }
        
        // Handle category/taxonomy
        if (!empty($settings['taxonomy_slug']) && isset($service_data['categoryId'])) {
            $this->sync_category($post_id, $service_data, $settings['taxonomy_slug'], $settings);
        }
        
        // Handle field mappings
        $this->sync_custom_fields($post_id, $service_data, $settings['field_mappings']);
        
        // Handle custom fields from database
        $this->sync_user_defined_custom_fields($post_id, $amelia_service_id);
        
        return $post_id;
    }
    
    /**
     * Sync user-defined custom fields from database
     *
     * @param int $post_id The CPT post ID
     * @param int $amelia_service_id The Amelia service ID
     */
    private function sync_user_defined_custom_fields($post_id, $amelia_service_id) {
        $manager = new Amelia_CPT_Sync_Custom_Fields_Manager();
        $custom_field_values = $manager->get_service_field_values($amelia_service_id);
        
        if (empty($custom_field_values)) {
            amelia_cpt_sync_debug_log("No custom field values for service {$amelia_service_id}");
            return;
        }
        
        amelia_cpt_sync_debug_log("Syncing custom fields for service {$amelia_service_id}: " . print_r($custom_field_values, true));
        
        foreach ($custom_field_values as $meta_key => $meta_value) {
            update_post_meta($post_id, $meta_key, $meta_value);
            amelia_cpt_sync_debug_log("  - Set {$meta_key} = {$meta_value}");
        }
    }
    
    /**
     * Delete a CPT post by Amelia service ID
     *
     * @param int $amelia_service_id The Amelia service ID
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function delete_service($amelia_service_id) {
        amelia_cpt_sync_debug_log('Starting service deletion for Amelia Service ID: ' . $amelia_service_id);
        
        $settings = $this->get_settings();
        
        if (!$settings || empty($settings['cpt_slug'])) {
            amelia_cpt_sync_debug_log('ERROR: No CPT sync settings configured');
            return new WP_Error('no_settings', 'No CPT sync settings configured');
        }
        
        $post_id = $this->find_post_by_amelia_id($amelia_service_id, $settings['cpt_slug']);
        
        if (!$post_id) {
            amelia_cpt_sync_debug_log('WARNING: No post found for Amelia Service ID: ' . $amelia_service_id);
            return new WP_Error('post_not_found', 'No post found for this Amelia service');
        }
        
        amelia_cpt_sync_debug_log('Found CPT post ID to delete: ' . $post_id);
        
        // Permanently delete the post (not trash)
        $result = wp_delete_post($post_id, true);
        
        if (!$result) {
            amelia_cpt_sync_debug_log('ERROR: Failed to delete CPT post ID: ' . $post_id);
            return new WP_Error('delete_failed', 'Failed to delete post');
        }
        
        amelia_cpt_sync_debug_log('Successfully deleted CPT post ID: ' . $post_id);
        return true;
    }
    
    /**
     * Find a CPT post by Amelia service ID
     *
     * @param int $amelia_service_id The Amelia service ID
     * @param string $post_type The CPT slug
     * @return int|false Post ID or false if not found
     */
    private function find_post_by_amelia_id($amelia_service_id, $post_type) {
        $args = array(
            'post_type' => $post_type,
            'post_status' => 'any',
            'posts_per_page' => 1,
            'meta_query' => array(
                array(
                    'key' => '_amelia_service_id',
                    'value' => $amelia_service_id,
                    'compare' => '='
                )
            ),
            'fields' => 'ids'
        );
        
        $posts = get_posts($args);
        
        return !empty($posts) ? $posts[0] : false;
    }
    
    /**
     * Set primary photo to custom field from URL
     *
     * @param int $post_id The post ID
     * @param string $image_url The image URL
     * @param string $field_slug The custom field slug
     */
    private function set_primary_photo($post_id, $image_url, $field_slug) {
        // Get current primary photo attachment ID
        $current_attachment_id = get_post_meta($post_id, $field_slug, true);
        $current_image_url = $current_attachment_id ? wp_get_attachment_url($current_attachment_id) : '';
        
        // If the same image URL, skip
        if ($current_image_url === $image_url) {
            return;
        }
        
        // Require necessary files
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        // Sideload image
        $attachment_id = $this->sideload_image($image_url, $post_id);
        
        if ($attachment_id && !is_wp_error($attachment_id)) {
            // Save attachment ID to custom field
            update_post_meta($post_id, $field_slug, $attachment_id);
        }
    }
    
    /**
     * Sideload an image from URL to media library
     *
     * @param string $image_url The image URL
     * @param int $post_id The post ID to attach to
     * @return int|WP_Error Attachment ID or WP_Error
     */
    private function sideload_image($image_url, $post_id = 0) {
        // Get the file name
        $file_name = basename($image_url);
        
        // Download the file
        $temp_file = download_url($image_url);
        
        if (is_wp_error($temp_file)) {
            return $temp_file;
        }
        
        // Prepare file array
        $file_array = array(
            'name' => $file_name,
            'tmp_name' => $temp_file
        );
        
        // Sideload the file
        $attachment_id = media_handle_sideload($file_array, $post_id);
        
        // Delete temp file if it still exists
        if (file_exists($temp_file)) {
            @unlink($temp_file);
        }
        
        return $attachment_id;
    }
    
    /**
     * Sync category/taxonomy term
     *
     * @param int $post_id The post ID
     * @param array $service_data The Amelia service data
     * @param string $taxonomy_slug The taxonomy slug
     * @param array $settings The plugin settings
     */
    private function sync_category($post_id, $service_data, $taxonomy_slug, $settings) {
        // Get category name and ID from service data
        $category_name = isset($service_data['categoryName']) ? $service_data['categoryName'] : '';
        $category_id = isset($service_data['categoryId']) ? intval($service_data['categoryId']) : 0;
        
        if (empty($category_name)) {
            return;
        }
        
        // Check if term exists
        $term = get_term_by('name', $category_name, $taxonomy_slug);
        
        // If term doesn't exist, create it
        if (!$term) {
            $term_data = wp_insert_term($category_name, $taxonomy_slug);
            
            if (is_wp_error($term_data)) {
                return;
            }
            
            $term_id = $term_data['term_id'];
        } else {
            $term_id = $term->term_id;
        }
        
        // Save Amelia Category ID as term meta if field mapping is configured
        if (!empty($settings['taxonomy_meta']['category_id']) && $category_id > 0) {
            update_term_meta($term_id, $settings['taxonomy_meta']['category_id'], $category_id);
        }
        
        // Sync taxonomy custom fields to term meta if category_id exists
        if ($category_id > 0) {
            $this->sync_taxonomy_custom_fields($term_id, $category_id);
        }
        
        // Assign term to post
        wp_set_post_terms($post_id, array($term_id), $taxonomy_slug, false);
    }
    
    /**
     * Sync taxonomy custom fields to term meta
     *
     * @param int $term_id The WordPress term ID
     * @param int $amelia_category_id The Amelia category ID
     */
    private function sync_taxonomy_custom_fields($term_id, $amelia_category_id) {
        $manager = new Amelia_CPT_Sync_Taxonomy_Custom_Fields_Manager();
        $custom_field_values = $manager->get_category_field_values($amelia_category_id);
        
        if (empty($custom_field_values)) {
            amelia_cpt_sync_debug_log("No taxonomy custom field values for category {$amelia_category_id}");
            return;
        }
        
        amelia_cpt_sync_debug_log("Syncing taxonomy custom fields for category {$amelia_category_id} to term {$term_id}: " . print_r($custom_field_values, true));
        
        foreach ($custom_field_values as $meta_key => $meta_value) {
            update_term_meta($term_id, $meta_key, $meta_value);
            amelia_cpt_sync_debug_log("  - Set term meta {$meta_key} = {$meta_value}");
        }
    }
    
    /**
     * Sync custom fields based on field mappings
     *
     * @param int $post_id The post ID
     * @param array $service_data The Amelia service data
     * @param array $field_mappings The field mappings from settings
     */
    private function sync_custom_fields($post_id, $service_data, $field_mappings) {
        // Service ID
        if (!empty($field_mappings['service_id']) && isset($service_data['id'])) {
            update_post_meta($post_id, $field_mappings['service_id'], intval($service_data['id']));
        }
        
        // Category ID
        if (!empty($field_mappings['category_id']) && isset($service_data['categoryId'])) {
            update_post_meta($post_id, $field_mappings['category_id'], intval($service_data['categoryId']));
        }
        
        // Price
        if (!empty($field_mappings['price']) && isset($service_data['price'])) {
            update_post_meta($post_id, $field_mappings['price'], floatval($service_data['price']));
        }
        
        // Duration
        if (!empty($field_mappings['duration']) && isset($service_data['duration'])) {
            $duration_value = $this->format_duration(
                intval($service_data['duration']),
                $field_mappings['duration_format']
            );
            update_post_meta($post_id, $field_mappings['duration'], $duration_value);
        }
        
        // Gallery
        if (!empty($field_mappings['gallery']) && isset($service_data['gallery']) && is_array($service_data['gallery'])) {
            $gallery_ids = $this->sync_gallery($post_id, $service_data['gallery']);
            if (!empty($gallery_ids)) {
                update_post_meta($post_id, $field_mappings['gallery'], $gallery_ids);
            }
        }
        
        // Extras
        if (!empty($field_mappings['extras']) && isset($service_data['extras']) && is_array($service_data['extras'])) {
            // Store the entire extras array for use with JetEngine Repeater
            update_post_meta($post_id, $field_mappings['extras'], $service_data['extras']);
        }
    }
    
    /**
     * Format duration based on selected format
     *
     * @param int $seconds Duration in seconds
     * @param string $format The format type
     * @return mixed Formatted duration
     */
    private function format_duration($seconds, $format) {
        switch ($format) {
            case 'minutes':
                return floor($seconds / 60);
                
            case 'hh_mm':
                $hours = floor($seconds / 3600);
                $minutes = floor(($seconds % 3600) / 60);
                return sprintf('%02d:%02d', $hours, $minutes);
                
            case 'readable':
                $hours = floor($seconds / 3600);
                $minutes = floor(($seconds % 3600) / 60);
                
                $parts = array();
                if ($hours > 0) {
                    $parts[] = $hours . ' ' . _n('hour', 'hours', $hours, 'amelia-cpt-sync');
                }
                if ($minutes > 0) {
                    $parts[] = $minutes . ' ' . _n('minute', 'minutes', $minutes, 'amelia-cpt-sync');
                }
                
                return implode(' ', $parts);
                
            case 'seconds':
            default:
                return $seconds;
        }
    }
    
    /**
     * Sync gallery images
     *
     * @param int $post_id The post ID
     * @param array $gallery_images Array of gallery image data
     * @return array Array of attachment IDs
     */
    private function sync_gallery($post_id, $gallery_images) {
        // Require necessary files
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        $attachment_ids = array();
        
        foreach ($gallery_images as $image) {
            // Get image URL - adjust based on Amelia's data structure
            $image_url = isset($image['pictureFullPath']) ? $image['pictureFullPath'] : 
                        (isset($image['url']) ? $image['url'] : '');
            
            if (empty($image_url)) {
                continue;
            }
            
            // Sideload image
            $attachment_id = $this->sideload_image($image_url, $post_id);
            
            if ($attachment_id && !is_wp_error($attachment_id)) {
                $attachment_ids[] = $attachment_id;
            }
        }
        
        return $attachment_ids;
    }
}

