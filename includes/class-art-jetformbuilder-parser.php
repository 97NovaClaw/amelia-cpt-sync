<?php
/**
 * ART JetFormBuilder Parser Implementation
 *
 * Parses JetFormBuilder form exports to extract field definitions
 * Uses proven regex pattern from WC plugin reference
 *
 * @package AmeliaCPTSync
 * @subpackage ART
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class Amelia_CPT_Sync_ART_JetFormBuilder_Parser implements Amelia_CPT_Sync_ART_Form_Parser {
    
    /**
     * Extract fields from JetFormBuilder JSON export
     *
     * @param array $json_data Decoded JSON data
     * @return array Array of fields
     */
    public function extract_fields($json_data) {
        $fields = array();
        
        if (!is_array($json_data)) {
            amelia_cpt_sync_debug_log('ART JFB Parser: JSON data is not an array');
            return $fields;
        }
        
        $post_content = isset($json_data['post_content']) ? $json_data['post_content'] : '';
        
        if (!is_string($post_content) || empty($post_content)) {
            amelia_cpt_sync_debug_log('ART JFB Parser: post_content is empty or not a string');
            return $fields;
        }
        
        // Regex pattern to match JetFormBuilder Gutenberg blocks
        // Pattern: <!-- wp:jet-forms/{type} {json_attrs} /-->
        $pattern = '/<!--\s*wp:jet-forms\/([a-zA-Z0-9\-]+)\s+({.*?})\s*\/-->/s';
        
        if (preg_match_all($pattern, $post_content, $matches, PREG_SET_ORDER)) {
            amelia_cpt_sync_debug_log('ART JFB Parser: Found ' . count($matches) . ' form fields');
            
            foreach ($matches as $match) {
                $block_type = $match[1] ?? '';
                $json_attrs = $match[2] ?? '';
                
                if (empty($json_attrs)) {
                    continue;
                }
                
                // Handle potential encoding issues
                $json_attrs_decoded = mb_convert_encoding($json_attrs, 'UTF-8', 'UTF-8');
                $attrs = json_decode($json_attrs_decoded, true);
                
                if (json_last_error() === JSON_ERROR_NONE && 
                    is_array($attrs) && 
                    isset($attrs['name']) && 
                    !empty($attrs['name'])) {
                    
                    $field_id = $attrs['name'];
                    $field_label = isset($attrs['label']) && trim($attrs['label']) !== '' 
                        ? trim($attrs['label']) 
                        : $field_id;
                    
                    // Use field ID as key to prevent duplicates
                    if (!isset($fields[$field_id])) {
                        $fields[$field_id] = array(
                            'id' => $field_id,
                            'name' => $field_label,
                            'type' => $block_type
                        );
                    }
                } else {
                    amelia_cpt_sync_debug_log('ART JFB Parser: Failed to decode attributes for block type: ' . $block_type);
                }
            }
        } else {
            amelia_cpt_sync_debug_log('ART JFB Parser: No JetForm block comments found in post_content');
        }
        
        amelia_cpt_sync_debug_log('ART JFB Parser: Extracted ' . count($fields) . ' unique fields');
        
        // Return indexed array
        return array_values($fields);
    }
    
    /**
     * Get parser name
     *
     * @return string Parser name
     */
    public function get_parser_name() {
        return 'JetFormBuilder';
    }
}

