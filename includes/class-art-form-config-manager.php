<?php
/**
 * ART Form Configuration Manager Class
 *
 * Manages triage form configurations (similar to popup config manager)
 * Handles CRUD operations for multiple form configurations
 *
 * @package AmeliaCPTSync
 * @subpackage ART
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class Amelia_CPT_Sync_ART_Form_Config_Manager {
    
    /**
     * Option name for storing configurations
     */
    private $option_name = 'art_form_configurations';
    
    /**
     * Get all configurations
     *
     * @return array Configurations array
     */
    public function get_configurations() {
        $configs = get_option($this->option_name, array(
            'forms' => array()
        ));
        
        return $configs;
    }
    
    /**
     * Get single form configuration by ID
     *
     * @param string $form_id Form configuration ID
     * @return array|false Configuration array or false if not found
     */
    public function get_configuration($form_id) {
        $all_configs = $this->get_configurations();
        
        if (isset($all_configs['forms'][$form_id])) {
            return $all_configs['forms'][$form_id];
        }
        
        return false;
    }
    
    /**
     * Get form configuration by hook name
     *
     * @param string $hook_name Hook name to search for
     * @return array|false Configuration array with 'id' key added, or false
     */
    public function get_form_by_hook($hook_name) {
        $all_configs = $this->get_configurations();
        
        foreach ($all_configs['forms'] as $form_id => $config) {
            if (isset($config['hook_name']) && $config['hook_name'] === $hook_name) {
                $config['id'] = $form_id;  // Add ID to return
                return $config;
            }
        }
        
        return false;
    }
    
    /**
     * Check if form configuration exists
     *
     * @param string $form_id Form configuration ID
     * @return bool True if exists
     */
    public function form_exists($form_id) {
        $all_configs = $this->get_configurations();
        return isset($all_configs['forms'][$form_id]);
    }
    
    /**
     * Check if hook name is already in use
     *
     * @param string $hook_name Hook name to check
     * @param string $exclude_form_id Form ID to exclude from check (for updates)
     * @return bool True if hook is in use
     */
    public function hook_name_exists($hook_name, $exclude_form_id = '') {
        $all_configs = $this->get_configurations();
        
        foreach ($all_configs['forms'] as $form_id => $config) {
            if ($form_id === $exclude_form_id) {
                continue;  // Skip the form being edited
            }
            
            if (isset($config['hook_name']) && $config['hook_name'] === $hook_name) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Save form configuration
     *
     * @param string $form_id Form configuration ID
     * @param array $config_data Configuration data
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function save_configuration($form_id, $config_data) {
        // Validate hook name uniqueness
        if (isset($config_data['hook_name'])) {
            if ($this->hook_name_exists($config_data['hook_name'], $form_id)) {
                amelia_cpt_sync_debug_log('ART Form Config: Hook name already in use: ' . $config_data['hook_name']);
                return new WP_Error('duplicate_hook', 'Hook name is already in use by another form');
            }
        }
        
        $all_configs = $this->get_configurations();
        $all_configs['forms'][$form_id] = $config_data;
        
        $result = update_option($this->option_name, $all_configs);
        
        if ($result || get_option($this->option_name) === $all_configs) {
            amelia_cpt_sync_debug_log('ART Form Config: Saved configuration for form ' . $form_id);
            return true;
        }
        
        amelia_cpt_sync_debug_log('ART Form Config: Failed to save configuration for form ' . $form_id);
        return new WP_Error('save_failed', 'Failed to save form configuration');
    }
    
    /**
     * Delete form configuration
     *
     * @param string $form_id Form configuration ID
     * @return bool True on success
     */
    public function delete_configuration($form_id) {
        $all_configs = $this->get_configurations();
        
        if (!isset($all_configs['forms'][$form_id])) {
            return false;
        }
        
        unset($all_configs['forms'][$form_id]);
        
        $result = update_option($this->option_name, $all_configs);
        
        if ($result) {
            amelia_cpt_sync_debug_log('ART Form Config: Deleted configuration for form ' . $form_id);
        }
        
        return $result;
    }
    
    /**
     * Generate unique form ID
     *
     * @param string $label Form label
     * @return string Unique form ID
     */
    public function generate_form_id($label) {
        $base = sanitize_key($label);
        $form_id = $base . '_' . time();
        
        // Ensure uniqueness
        $counter = 1;
        while ($this->form_exists($form_id)) {
            $form_id = $base . '_' . time() . '_' . $counter;
            $counter++;
        }
        
        return $form_id;
    }
    
    /**
     * Get default form configuration structure
     *
     * @return array Default configuration
     */
    public function get_default_config() {
        return array(
            'label' => '',
            'hook_name' => '',
            'uploaded_json' => null,
            'mappings' => array(),
            'logic' => array(
                'service_id_source' => 'cpt',
                'duration_mode' => 'manual',
                'price_mode' => 'manual',
                'location_mode' => 'disabled',
                'persons_mode' => 'disabled',
                'validation_mode' => 'pass_through_fails'
            ),
            'critical_fields' => array(),
            'intake_fields' => array()
        );
    }
    
    /**
     * Get all form IDs and labels (for dropdowns)
     *
     * @return array Array of form_id => label
     */
    public function get_forms_list() {
        $all_configs = $this->get_configurations();
        $list = array();
        
        foreach ($all_configs['forms'] as $form_id => $config) {
            $list[$form_id] = $config['label'] ?? $form_id;
        }
        
        return $list;
    }
}

