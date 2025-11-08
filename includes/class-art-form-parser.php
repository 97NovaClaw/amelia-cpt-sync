<?php
/**
 * ART Form Parser Interface
 *
 * Abstract interface for form parsers
 * Allows support for multiple form builders (JetFormBuilder, Gravity Forms, etc.)
 *
 * @package AmeliaCPTSync
 * @subpackage ART
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

interface Amelia_CPT_Sync_ART_Form_Parser {
    
    /**
     * Extract fields from form export JSON
     *
     * @param array $json_data Decoded JSON data from form export
     * @return array Array of fields: [['id' => 'field_id', 'name' => 'Field Label'], ...]
     */
    public function extract_fields($json_data);
    
    /**
     * Get parser name for display
     *
     * @return string Parser name (e.g., 'JetFormBuilder')
     */
    public function get_parser_name();
}

