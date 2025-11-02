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
        
        // Check if data changed
        $old_data = get_option($this->option_name, array());
        
        if ($old_data === $data) {
            amelia_cpt_sync_debug_log('NOTE: Data unchanged, but treating as success');
            return true; // Data identical, no need to update but not an error
        }
        
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

    /**
     * Get an array of popup IDs keyed by configuration ID
     */
    public function get_tracked_popups() {
        $configs = $this->get_configurations();
        $tracked = array();

        if (!empty($configs['configs']) && is_array($configs['configs'])) {
            foreach ($configs['configs'] as $config_id => $config) {
                if (!empty($config['popup_id'])) {
                    $tracked[$config_id] = sanitize_text_field($config['popup_id']);
                }
            }
        }

        return $tracked;
    }
}

