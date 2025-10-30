<?php
/**
 * Popup Configuration Manager Class
 *
 * Manages popup trigger configurations
 *
 * @package AmeliaCPTSync
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class Amelia_CPT_Sync_Popup_Config_Manager {
    
    /**
     * Option name for storing configurations
     */
    private $option_name = 'amelia_popup_configurations';
    
    /**
     * Get all configurations
     */
    public function get_configurations() {
        $configs = get_option($this->option_name, array(
            'global' => array(
                'default_popup_id' => '',
                'debug_enabled' => false
            ),
            'configs' => array()
        ));
        
        return $configs;
    }
    
    /**
     * Save configurations
     */
    public function save_configurations($data) {
        amelia_cpt_sync_debug_log('Saving popup configurations: ' . print_r($data, true));
        
        $result = update_option($this->option_name, $data);
        
        if ($result) {
            amelia_cpt_sync_debug_log('SUCCESS: Popup configurations saved');
            return true;
        } else {
            amelia_cpt_sync_debug_log('ERROR: Failed to save popup configurations');
            return false;
        }
    }
    
    /**
     * Generate Elementor attribute string for a configuration
     */
    public function generate_elementor_attributes($config) {
        amelia_cpt_sync_debug_log('Generating Elementor attributes for config: ' . print_r($config, true));
        
        $attributes = array();
        
        // Amelia type attribute
        $amelia_type = $config['amelia_type'];
        
        // Handle custom types
        if (isset($config['custom_type']) && !empty($config['custom_type'])) {
            $amelia_type = $config['custom_type'];
        }
        
        $attributes[] = 'data-amelia-type|' . $amelia_type;
        
        // Dynamic ID attribute (with placeholder for JetEngine)
        $meta_field = isset($config['meta_field']) ? $config['meta_field'] : '';
        if ($meta_field) {
            $attributes[] = 'data-amelia-id|%' . $meta_field . '%';
        }
        
        // JetPopup trigger attribute
        if (!empty($config['popup_id'])) {
            $attributes[] = 'data-jet-popup|' . $config['popup_id'];
        }
        
        $result = implode("\n", $attributes);
        amelia_cpt_sync_debug_log('Generated attributes: ' . $result);
        
        return $result;
    }
    
    /**
     * Get configuration by ID
     */
    public function get_configuration($config_id) {
        $all_configs = $this->get_configurations();
        
        if (isset($all_configs['configs'][$config_id])) {
            return $all_configs['configs'][$config_id];
        }
        
        return false;
    }
    
    /**
     * Add new configuration
     */
    public function add_configuration($config_data) {
        $all_configs = $this->get_configurations();
        
        // Generate unique ID
        $config_id = sanitize_key($config_data['label']) . '_' . time();
        
        $all_configs['configs'][$config_id] = $config_data;
        
        return $this->save_configurations($all_configs);
    }
    
    /**
     * Update configuration
     */
    public function update_configuration($config_id, $config_data) {
        $all_configs = $this->get_configurations();
        
        if (isset($all_configs['configs'][$config_id])) {
            $all_configs['configs'][$config_id] = $config_data;
            return $this->save_configurations($all_configs);
        }
        
        return false;
    }
    
    /**
     * Delete configuration
     */
    public function delete_configuration($config_id) {
        $all_configs = $this->get_configurations();
        
        if (isset($all_configs['configs'][$config_id])) {
            unset($all_configs['configs'][$config_id]);
            return $this->save_configurations($all_configs);
        }
        
        return false;
    }
}

