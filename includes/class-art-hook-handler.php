<?php
/**
 * ART Hook Handler Class
 *
 * Handles form submissions from JetFormBuilder
 * Registers dynamic hooks, validates data, and saves to database
 *
 * @package AmeliaCPTSync
 * @subpackage ART
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class Amelia_CPT_Sync_ART_Hook_Handler {
    
    /**
     * Form config manager instance
     */
    private $config_manager;
    
    /**
     * Database manager instance
     */
    private $db_manager;
    
    /**
     * Initialize the class
     */
    public function init() {
        $this->config_manager = new Amelia_CPT_Sync_ART_Form_Config_Manager();
        $this->db_manager = new Amelia_CPT_Sync_ART_Database_Manager();
        
        // Register hooks for all configured forms
        add_action('init', array($this, 'register_form_hooks'), 20);
    }
    
    /**
     * Register form hooks dynamically based on configurations
     */
    public function register_form_hooks() {
        $all_configs = $this->config_manager->get_configurations();
        
        if (empty($all_configs['forms'])) {
            return;
        }
        
        foreach ($all_configs['forms'] as $form_id => $form_config) {
            if (empty($form_config['hook_name'])) {
                continue;
            }
            
            $hook_tag = sanitize_key($form_config['hook_name']);
            
            // ONLY register custom-filter (not action)
            // Filter allows us to return validation errors to JFB
            add_filter("jet-form-builder/custom-filter/{$hook_tag}", 
                array($this, 'handle_form_submission'), 10, 3);
            
            amelia_cpt_sync_debug_log('ART Hook Handler: Registered FILTER for hook: ' . $hook_tag);
        }
    }
    
    /**
     * Handle form submission
     *
     * @param mixed $result JFB result object
     * @param array $request Form data
     * @param object $action_handler JFB action handler
     * @return mixed Result or WP_Error on validation failure
     */
    public function handle_form_submission($result, $request, $action_handler) {
        amelia_cpt_sync_debug_log('========== ART FORM SUBMISSION STARTED ==========');
        amelia_cpt_sync_debug_log('ART: Form data received: ' . print_r($request, true));
        
        // Get current hook name
        $current_hook = current_filter();
        $hook_parts = explode('/', $current_hook);
        $hook_name = end($hook_parts);
        
        amelia_cpt_sync_debug_log('ART: Hook name: ' . $hook_name);
        
        // Find form configuration by hook name
        $form_config = $this->config_manager->get_form_by_hook($hook_name);
        
        if (!$form_config) {
            amelia_cpt_sync_debug_log('ART ERROR: No form configuration found for hook: ' . $hook_name);
            return $result;  // Pass through, don't break form
        }
        
        amelia_cpt_sync_debug_log('ART: Using form config: ' . $form_config['label']);
        
        // Parse form data into buckets
        $buckets = $this->parse_form_data($request, $form_config);
        
        if (is_wp_error($buckets)) {
            amelia_cpt_sync_debug_log('ART ERROR: ' . $buckets->get_error_message());
            return $buckets;  // Return error to JFB
        }
        
        amelia_cpt_sync_debug_log('ART: Parsed buckets: ' . print_r($buckets, true));
        
        // Validate data
        $validated = $this->validate_data($buckets, $form_config);
        
        if (is_wp_error($validated)) {
            amelia_cpt_sync_debug_log('ART ERROR: Validation failed: ' . $validated->get_error_message());
            return $validated;  // Return error to JFB (stops submission)
        }
        
        $buckets = $validated;
        
        // Apply logic transformations
        $buckets = $this->apply_logic($buckets, $form_config);
        
        // Save to database
        $request_id = $this->save_to_database($buckets);
        
        if ($request_id) {
            amelia_cpt_sync_debug_log('ART SUCCESS: Created request #' . $request_id);
            amelia_cpt_sync_debug_log('========== ART FORM SUBMISSION COMPLETE ==========');
        } else {
            amelia_cpt_sync_debug_log('ART ERROR: Failed to create request in database');
        }
        
        // Return original result to allow JFB to continue
        return $result;
    }
    
    /**
     * Parse form data into buckets (customer, request, intake)
     *
     * @param array $request Form data from JFB
     * @param array $form_config Form configuration
     * @return array|WP_Error Buckets array or error
     */
    private function parse_form_data($request, $form_config) {
        $mappings = $form_config['mappings'] ?? array();
        
        if (empty($mappings)) {
            return new WP_Error('no_mappings', 'No field mappings configured for this form');
        }
        
        $buckets = array(
            'customer' => array(),
            'request' => array(),
            'intake' => array()
        );
        
        foreach ($mappings as $form_field_id => $mapping_data) {
            // Support both old format (string) and new format (array with 'destination' and 'label')
            $destination = is_array($mapping_data) ? ($mapping_data['destination'] ?? '') : $mapping_data;
            
            if (empty($destination) || $destination === '') {
                continue;  // Skip unmapped fields
            }
            
            // Get value from form submission
            if (!isset($request[$form_field_id])) {
                continue;  // Field not in submission
            }
            
            $value = $request[$form_field_id];
            
            // Sanitize based on destination type (basic sanitization)
            $value = is_array($value) 
                ? array_map('sanitize_text_field', $value) 
                : sanitize_text_field($value);
            
            // Route to correct bucket
            if (strpos($destination, 'customer.') === 0) {
                $field_key = substr($destination, strlen('customer.'));
                $buckets['customer'][$field_key] = $value;
                
            } elseif (strpos($destination, 'request.') === 0) {
                $field_key = substr($destination, strlen('request.'));
                $buckets['request'][$field_key] = $value;
                
            } elseif (strpos($destination, 'intake_field.') === 0) {
                $field_label = substr($destination, strlen('intake_field.'));
                $buckets['intake'][$field_label] = $value;
            }
        }
        
        amelia_cpt_sync_debug_log('ART: Parsed ' . count($buckets['customer']) . ' customer fields, ' . 
                                   count($buckets['request']) . ' request fields, ' . 
                                   count($buckets['intake']) . ' intake fields');
        
        return $buckets;
    }
    
    /**
     * Validate data based on validation mode
     *
     * @param array $buckets Data buckets
     * @param array $form_config Form configuration
     * @return array|WP_Error Cleaned buckets or error
     */
    private function validate_data($buckets, $form_config) {
        $validation_mode = $form_config['logic']['validation_mode'] ?? 'pass_through_fails';
        $critical_fields = $form_config['critical_fields'] ?? array();
        $mappings = $form_config['mappings'] ?? array();
        $errors = array();
        
        amelia_cpt_sync_debug_log('ART Validation: Mode = ' . $validation_mode);
        amelia_cpt_sync_debug_log('ART Validation: Critical fields = ' . print_r($critical_fields, true));
        
        // Build reverse lookup: destination => label
        $destination_to_label = array();
        foreach ($mappings as $field_id => $mapping_data) {
            // Support both old format (string) and new format (array with 'destination' and 'label')
            if (is_array($mapping_data)) {
                $destination = $mapping_data['destination'] ?? '';
                $label = $mapping_data['label'] ?? $field_id;
            } else {
                $destination = $mapping_data;
                $label = $field_id; // Fallback for old format
            }
            
            if (!empty($destination)) {
                $destination_to_label[$destination] = $label;
            }
        }
        
        // Check for MISSING critical fields (only in strict mode)
        if ($validation_mode === 'require_pass_through') {
            foreach ($critical_fields as $critical_field) {
                list($bucket, $field) = explode('.', $critical_field, 2);
                
                if (!isset($buckets[$bucket][$field]) || empty($buckets[$bucket][$field])) {
                    // Use friendly label if available
                    $field_label = $destination_to_label[$critical_field] ?? $critical_field;
                    $errors[] = "Missing required field: {$field_label}";
                    amelia_cpt_sync_debug_log('ART Validation: CRITICAL MISSING: ' . $critical_field . ' (label: ' . $field_label . ')');
                }
            }
        }
        
        // Validate customer.email
        if (isset($buckets['customer']['email'])) {
            $email = sanitize_email($buckets['customer']['email']);
            
            if (!is_email($email)) {
                $is_critical = in_array('customer.email', $critical_fields);
                
                if ($validation_mode === 'require_pass_through' && $is_critical) {
                    $errors[] = 'Invalid email address';
                } else {
                    amelia_cpt_sync_debug_log('ART Validation: Invalid email, skipping field');
                    unset($buckets['customer']['email']);
                }
            } else {
                $buckets['customer']['email'] = $email;
            }
        }
        
        // Validate customer.phone
        if (isset($buckets['customer']['phone'])) {
            $buckets['customer']['phone'] = sanitize_text_field($buckets['customer']['phone']);
        }
        
        // Validate request.start_datetime
        if (isset($buckets['request']['start_datetime'])) {
            $validated_dt = $this->validate_datetime($buckets['request']['start_datetime']);
            
            if (!$validated_dt) {
                $is_critical = in_array('request.start_datetime', $critical_fields);
                
                if ($validation_mode === 'require_pass_through' && $is_critical) {
                    $errors[] = 'Invalid start datetime format';
                } else {
                    amelia_cpt_sync_debug_log('ART Validation: Invalid start_datetime, setting to null');
                    $buckets['request']['start_datetime'] = null;
                }
            } else {
                $buckets['request']['start_datetime'] = $validated_dt;
            }
        }
        
        // Validate request.end_datetime
        if (isset($buckets['request']['end_datetime'])) {
            $validated_dt = $this->validate_datetime($buckets['request']['end_datetime']);
            
            if (!$validated_dt) {
                amelia_cpt_sync_debug_log('ART Validation: Invalid end_datetime, setting to null');
                $buckets['request']['end_datetime'] = null;
            } else {
                $buckets['request']['end_datetime'] = $validated_dt;
            }
        }
        
        // Validate numeric fields
        if (isset($buckets['request']['service_id_source'])) {
            $buckets['request']['service_id_source'] = absint($buckets['request']['service_id_source']);
        }
        
        if (isset($buckets['request']['category_id'])) {
            $buckets['request']['category_id'] = absint($buckets['request']['category_id']);
        }
        
        if (isset($buckets['request']['location_id'])) {
            $buckets['request']['location_id'] = absint($buckets['request']['location_id']);
        }
        
        if (isset($buckets['request']['persons'])) {
            $buckets['request']['persons'] = absint($buckets['request']['persons']);
        }
        
        if (isset($buckets['request']['final_price'])) {
            $buckets['request']['final_price'] = floatval($buckets['request']['final_price']);
        }
        
        // If critical errors in strict mode, fail the submission
        if ($validation_mode === 'require_pass_through' && !empty($errors)) {
            $error_message = implode(', ', $errors);
            amelia_cpt_sync_debug_log('ART Validation: FAILED - ' . $error_message);
            
            // Throw JFB's exception to properly fail the form
            // This is the correct way per JFB documentation
            throw new \Jet_Form_Builder\Exceptions\Action_Exception($error_message);
        }
        
        amelia_cpt_sync_debug_log('ART Validation: Passed - All critical fields present and valid');
        return $buckets;
    }
    
    /**
     * Validate and convert datetime to UTC
     *
     * @param string $input DateTime input
     * @return string|false UTC datetime string or false if invalid
     */
    private function validate_datetime($input) {
        if (empty($input)) {
            return false;
        }
        
        try {
            $wp_timezone = new DateTimeZone(wp_timezone_string());
            $utc_timezone = new DateTimeZone('UTC');
            
            // Try multiple datetime formats
            $formats = array(
                'Y-m-d H:i:s',
                'Y-m-d H:i',
                'Y-m-d\TH:i',
                'Y-m-d\TH:i:s'
            );
            
            foreach ($formats as $format) {
                $dt = DateTime::createFromFormat($format, $input, $wp_timezone);
                
                if ($dt !== false) {
                    // Convert to UTC and return in MySQL format
                    return $dt->setTimezone($utc_timezone)->format('Y-m-d H:i:s');
                }
            }
            
            // Try strtotime as fallback
            $timestamp = strtotime($input);
            if ($timestamp !== false) {
                $dt = new DateTime('@' . $timestamp);
                return $dt->setTimezone($utc_timezone)->format('Y-m-d H:i:s');
            }
            
        } catch (Exception $e) {
            amelia_cpt_sync_debug_log('ART Datetime Validation Error: ' . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * Apply logic transformations based on form configuration
     *
     * @param array $buckets Data buckets
     * @param array $form_config Form configuration
     * @return array Transformed buckets
     */
    private function apply_logic($buckets, $form_config) {
        $logic = $form_config['logic'];
        
        // Service ID transformation
        if ($logic['service_id_source'] === 'cpt' && isset($buckets['request']['service_id_source'])) {
            $cpt_id = absint($buckets['request']['service_id_source']);
            
            if ($cpt_id > 0) {
                // Get configured meta field name for service_id
                $settings = get_option('amelia_cpt_sync_settings', array());
                $service_meta_key = $settings['field_mappings']['service_id'] ?? '_amelia_service_id';
                
                $service_id = get_post_meta($cpt_id, $service_meta_key, true);
                
                if ($service_id) {
                    $buckets['request']['service_id'] = intval($service_id);
                    amelia_cpt_sync_debug_log('ART Logic: Converted CPT ID ' . $cpt_id . ' to service ID ' . $service_id . ' (using meta key: ' . $service_meta_key . ')');
                } else {
                    amelia_cpt_sync_debug_log('ART Logic: No service ID found in CPT ' . $cpt_id . ' meta key ' . $service_meta_key);
                    $buckets['request']['service_id'] = null;
                }
            }
        } elseif ($logic['service_id_source'] === 'direct' && isset($buckets['request']['service_id_source'])) {
            // Direct service ID
            $buckets['request']['service_id'] = absint($buckets['request']['service_id_source']);
        }
        
        // Remove temporary field
        unset($buckets['request']['service_id_source']);
        
        // Category ID transformation
        $category_source = $logic['category_id_source'] ?? 'convert';
        
        if ($category_source === 'convert' && isset($buckets['request']['category_id'])) {
            // Mode: Convert - Form value is a taxonomy term ID, convert to Amelia category ID
            $term_id = absint($buckets['request']['category_id']);
            
            if ($term_id > 0) {
                // Get configured taxonomy meta field name
                $settings = get_option('amelia_cpt_sync_settings', array());
                $taxonomy_meta_key = $settings['taxonomy_meta']['category_id'] ?? 'category_id';
                
                // Read Amelia category ID from term meta
                $amelia_category_id = get_term_meta($term_id, $taxonomy_meta_key, true);
                
                if ($amelia_category_id) {
                    $buckets['request']['category_id'] = intval($amelia_category_id);
                    amelia_cpt_sync_debug_log('ART Logic: Converted taxonomy term ' . $term_id . ' to category_id ' . $amelia_category_id . ' (using term meta key: ' . $taxonomy_meta_key . ')');
                } else {
                    amelia_cpt_sync_debug_log('ART Logic: No category_id found in term meta for term ' . $term_id . ' (meta key: ' . $taxonomy_meta_key . ')');
                    $buckets['request']['category_id'] = null;
                }
            }
        } elseif ($category_source === 'direct' && isset($buckets['request']['category_id'])) {
            // Mode: Direct - Form value is already Amelia category ID
            $buckets['request']['category_id'] = absint($buckets['request']['category_id']);
            amelia_cpt_sync_debug_log('ART Logic: Direct mode - Using category_id ' . $buckets['request']['category_id'] . ' as-is');
        }
        
        // Duration calculation
        if ($logic['duration_mode'] === 'start_end' && 
            !empty($buckets['request']['start_datetime']) && 
            !empty($buckets['request']['end_datetime'])) {
            
            $start = strtotime($buckets['request']['start_datetime']);
            $end = strtotime($buckets['request']['end_datetime']);
            
            if ($start && $end && $end > $start) {
                $buckets['request']['duration_seconds'] = $end - $start;
                amelia_cpt_sync_debug_log('ART Logic: Calculated duration: ' . $buckets['request']['duration_seconds'] . ' seconds');
            } else {
                $buckets['request']['duration_seconds'] = 0;
            }
        } else {
            // Manual or other modes - admin fills in workbench
            $buckets['request']['duration_seconds'] = 0;
        }
        
        // Location handling
        if ($logic['location_mode'] === 'disabled') {
            $buckets['request']['location_id'] = null;
        } elseif ($logic['location_mode'] === 'default' && isset($logic['default_location_id'])) {
            $buckets['request']['location_id'] = absint($logic['default_location_id']);
        }
        // If 'form' mode, location_id already set from mapping
        
        // Persons handling
        if ($logic['persons_mode'] === 'disabled') {
            $buckets['request']['persons'] = 1;  // Default
        }
        // If 'form' mode, persons already set from mapping
        
        // Price handling
        if ($logic['price_mode'] === 'manual') {
            $buckets['request']['final_price'] = null;  // Admin enters in workbench
        }
        // If 'form' or 'hook' mode, price already set
        
        return $buckets;
    }
    
    /**
     * Save data to database
     *
     * @param array $buckets Data buckets
     * @return int|false Request ID on success, false on failure
     */
    private function save_to_database($buckets) {
        // Create or find customer in our local tracking table
        // NOTE: This is NOT Amelia's customer table!
        // amelia_customer_id will be NULL until booking (Phase 5)
        
        $customer_data = $buckets['customer'];
        
        // Ensure required customer fields
        if (empty($customer_data['email'])) {
            amelia_cpt_sync_debug_log('ART Save: No email provided, cannot create customer');
            return false;
        }
        
        $customer_id = $this->db_manager->find_or_create_customer($customer_data);
        
        if (!$customer_id) {
            amelia_cpt_sync_debug_log('ART Save: Failed to create customer');
            return false;
        }
        
        // Create request
        $request_data = $buckets['request'];
        $request_data['customer_id'] = $customer_id;
        $request_data['status_key'] = 'requested';  // Initial status
        
        $request_id = $this->db_manager->create_request($request_data);
        
        if (!$request_id) {
            amelia_cpt_sync_debug_log('ART Save: Failed to create request');
            return false;
        }
        
        // Add intake fields
        if (!empty($buckets['intake'])) {
            $this->db_manager->add_intake_fields($request_id, $buckets['intake']);
        }
        
        amelia_cpt_sync_debug_log('ART Save: Successfully created request #' . $request_id . ' for customer #' . $customer_id);
        
        return $request_id;
    }
}

