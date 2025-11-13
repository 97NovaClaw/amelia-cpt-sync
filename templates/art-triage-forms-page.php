<?php
/**
 * ART Triage Forms Management Page Template
 *
 * Handles listing, adding, editing, and deleting form configurations
 *
 * @package AmeliaCPTSync
 * @subpackage ART
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

$config_manager = new Amelia_CPT_Sync_ART_Form_Config_Manager();
$parser = new Amelia_CPT_Sync_ART_JetFormBuilder_Parser();

// Determine current view
$action = isset($_GET['action']) ? sanitize_key($_GET['action']) : 'list';
$form_id = isset($_GET['form_id']) ? sanitize_key($_GET['form_id']) : '';

// Handle form submission (edit/add)
if (isset($_POST['art_save_form_config']) && check_admin_referer('art_form_config_nonce', 'art_nonce')) {
    
    $form_id_post = sanitize_key($_POST['form_id']);
    $label = sanitize_text_field($_POST['form_label']);
    $hook_name = sanitize_key($_POST['hook_name']);
    
    // Get existing config or start with defaults
    if ($form_id_post !== 'new' && $config_manager->form_exists($form_id_post)) {
        $config_data = $config_manager->get_configuration($form_id_post);
    } else {
        $config_data = $config_manager->get_default_config();
        $form_id_post = $config_manager->generate_form_id($label);
    }
    
    // Update basic info
    $config_data['label'] = $label;
    $config_data['hook_name'] = $hook_name;
    
    // Handle JSON upload
    if (isset($_FILES['form_json']) && $_FILES['form_json']['error'] === UPLOAD_ERR_OK) {
        if ($_FILES['form_json']['type'] === 'application/json') {
            $json_content = file_get_contents($_FILES['form_json']['tmp_name']);
            $decoded = json_decode($json_content, true);
            
            if (json_last_error() === JSON_ERROR_NONE) {
                $config_data['uploaded_json'] = $decoded;
                add_settings_error('art_forms', 'json_uploaded', 'Form JSON uploaded successfully', 'success');
            } else {
                add_settings_error('art_forms', 'json_error', 'Invalid JSON file: ' . json_last_error_msg(), 'error');
            }
        } else {
            add_settings_error('art_forms', 'file_type', 'File must be JSON format', 'error');
        }
    }
    
    // Save mappings if provided (now includes labels for better error messages)
    if (isset($_POST['mappings']) && is_array($_POST['mappings'])) {
        $config_data['mappings'] = array();
        $field_labels = $_POST['field_labels'] ?? array();
        
        foreach ($_POST['mappings'] as $field_id => $destination) {
            $dest = sanitize_text_field($destination);
            if (!empty($dest)) {
                $sanitized_field_id = sanitize_text_field($field_id);
                $label = isset($field_labels[$field_id]) ? sanitize_text_field($field_labels[$field_id]) : $sanitized_field_id;
                
                $config_data['mappings'][$sanitized_field_id] = array(
                    'destination' => $dest,
                    'label' => $label
                );
            }
        }
    }
    
    // Save logic settings
    if (isset($_POST['logic'])) {
        $config_data['logic']['service_id_source'] = sanitize_key($_POST['logic']['service_id_source'] ?? 'cpt');
        $config_data['logic']['category_id_source'] = sanitize_key($_POST['logic']['category_id_source'] ?? 'cpt');
        $config_data['logic']['duration_mode'] = sanitize_key($_POST['logic']['duration_mode'] ?? 'manual');
        $config_data['logic']['price_mode'] = sanitize_key($_POST['logic']['price_mode'] ?? 'manual');
        $config_data['logic']['location_mode'] = sanitize_key($_POST['logic']['location_mode'] ?? 'disabled');
        $config_data['logic']['persons_mode'] = sanitize_key($_POST['logic']['persons_mode'] ?? 'disabled');
        $config_data['logic']['validation_mode'] = sanitize_key($_POST['logic']['validation_mode'] ?? 'pass_through_fails');
        
        if (isset($_POST['logic']['default_location_id'])) {
            $config_data['logic']['default_location_id'] = absint($_POST['logic']['default_location_id']);
        }
    }
    
    // Save critical fields
    if (isset($_POST['critical_fields']) && is_array($_POST['critical_fields'])) {
        $config_data['critical_fields'] = array_filter(array_map('sanitize_text_field', $_POST['critical_fields']));
    } else {
        $config_data['critical_fields'] = array();
    }
    
    // Save intake fields
    if (isset($_POST['intake_fields']) && is_array($_POST['intake_fields'])) {
        $config_data['intake_fields'] = array_filter(array_map('sanitize_text_field', $_POST['intake_fields']));
    } else {
        $config_data['intake_fields'] = array();
    }
    
    // Save configuration
    $result = $config_manager->save_configuration($form_id_post, $config_data);
    
    if (is_wp_error($result)) {
        add_settings_error('art_forms', 'save_error', $result->get_error_message(), 'error');
    } else {
        add_settings_error('art_forms', 'save_success', 'Form configuration saved successfully', 'success');
        
        // Redirect to avoid form resubmission
        wp_redirect(add_query_arg(array(
            'page' => 'art-triage-forms',
            'action' => 'edit',
            'form_id' => $form_id_post,
            'saved' => '1'
        ), admin_url('admin.php')));
        exit;
    }
}

// Handle delete action
if ($action === 'delete' && !empty($form_id) && check_admin_referer('art_delete_form_' . $form_id)) {
    $config_manager->delete_configuration($form_id);
    add_settings_error('art_forms', 'deleted', 'Form configuration deleted', 'success');
    
    wp_redirect(add_query_arg(array('page' => 'art-triage-forms'), admin_url('admin.php')));
    exit;
}

?>
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php settings_errors('art_forms'); ?>
    
    <?php if ($action === 'list'): ?>
        <!-- LIST VIEW -->
        <?php
        $all_configs = $config_manager->get_configurations();
        $forms = $all_configs['forms'] ?? array();
        ?>
        
        <p>
            <a href="<?php echo esc_url(add_query_arg(array('action' => 'add'), admin_url('admin.php?page=art-triage-forms'))); ?>" class="button button-primary">
                + Add New Form
            </a>
        </p>
        
        <?php if (empty($forms)): ?>
            <div class="card" style="max-width: 600px; margin-top: 20px; text-align: center; padding: 40px;">
                <p style="font-size: 16px; color: #666;">No triage forms configured yet.</p>
                <p>
                    <a href="<?php echo esc_url(add_query_arg(array('action' => 'add'), admin_url('admin.php?page=art-triage-forms'))); ?>" class="button button-primary button-large">
                        Create Your First Form
                    </a>
                </p>
            </div>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 30%;">Form Label</th>
                        <th style="width: 25%;">Hook Name</th>
                        <th style="width: 15%;">Fields Mapped</th>
                        <th style="width: 15%;">Validation Mode</th>
                        <th style="width: 15%;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($forms as $id => $config): ?>
                        <tr>
                            <td><strong><?php echo esc_html($config['label']); ?></strong></td>
                            <td><code><?php echo esc_html($config['hook_name']); ?></code></td>
                            <td><?php echo count($config['mappings'] ?? array()); ?> fields</td>
                            <td>
                                <?php 
                                $mode = $config['logic']['validation_mode'] ?? 'pass_through_fails';
                                echo $mode === 'pass_through_fails' ? 'Forgiving' : 'Strict';
                                ?>
                            </td>
                            <td>
                                <a href="<?php echo esc_url(add_query_arg(array('action' => 'edit', 'form_id' => $id), admin_url('admin.php?page=art-triage-forms'))); ?>" class="button button-small">Edit</a>
                                <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(array('action' => 'delete', 'form_id' => $id), admin_url('admin.php?page=art-triage-forms')), 'art_delete_form_' . $id)); ?>" class="button button-small button-link-delete" onclick="return confirm('Are you sure you want to delete this form configuration?');">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
    <?php elseif ($action === 'add' || $action === 'edit'): ?>
        <!-- ADD/EDIT VIEW -->
        <?php
        if ($action === 'edit' && !empty($form_id)) {
            $form_config = $config_manager->get_configuration($form_id);
            if (!$form_config) {
                echo '<div class="notice notice-error"><p>Form configuration not found</p></div>';
                return;
            }
        } else {
            $form_id = 'new';
            $form_config = $config_manager->get_default_config();
        }
        
        $label = $form_config['label'] ?? '';
        $hook_name = $form_config['hook_name'] ?? '';
        $logic = $form_config['logic'] ?? array();
        $critical_fields = $form_config['critical_fields'] ?? array();
        $intake_fields = $form_config['intake_fields'] ?? array();
        $has_json = !empty($form_config['uploaded_json']);
        
        // Extract fields if JSON exists (for mapping table)
        $fields = array();
        if ($has_json) {
            $fields = $parser->extract_fields($form_config['uploaded_json']);
        }
        ?>
        
        <p>
            <a href="<?php echo esc_url(admin_url('admin.php?page=art-triage-forms')); ?>" class="button">
                ← Back to Forms List
            </a>
        </p>
        
        <!-- ONE COMPREHENSIVE FORM -->
        <form method="post" enctype="multipart/form-data" action="">
            <?php wp_nonce_field('art_form_config_nonce', 'art_nonce'); ?>
            <input type="hidden" name="form_id" value="<?php echo esc_attr($form_id); ?>">
            <input type="hidden" name="art_save_form_config" value="1">
            
            <!-- BASIC INFO -->
            <div class="card" style="max-width: 1200px; margin-top: 20px;">
                <h2><?php echo $action === 'edit' ? 'Edit Form Configuration' : 'Add New Form Configuration'; ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="form-label">Form Label</label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="form-label" 
                                   name="form_label" 
                                   value="<?php echo esc_attr($label); ?>" 
                                   class="regular-text" 
                                   required>
                            <p class="description">Display name for this form configuration (e.g., "Airport Transfer Intake")</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="hook-name">Hook Name</label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="hook-name" 
                                   name="hook_name" 
                                   value="<?php echo esc_attr($hook_name); ?>" 
                                   class="regular-text" 
                                   required>
                            <p class="description">
                                Unique hook name for JetFormBuilder "Call Hook" action.<br>
                                Example: <code>airport_transfer_intake</code><br>
                                <strong>Must be unique across all forms!</strong>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="form-json">Upload Form JSON</label>
                        </th>
                        <td>
                            <input type="file" 
                                   id="form-json" 
                                   name="form_json" 
                                   accept=".json">
                            <p class="description">
                                Export your JetFormBuilder form (Form → Export), upload the JSON file here.<br>
                                <?php if ($has_json): ?>
                                    <span style="color: #46b450;">✓ JSON file uploaded</span>
                                <?php else: ?>
                                    <span style="color: #dc3232;">⚠ No JSON file uploaded yet - Upload to enable field mapping</span>
                                <?php endif; ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <?php if ($has_json && !empty($fields)): ?>
                <!-- FIELD MAPPING -->
                <div class="card" style="max-width: 1200px; margin-top: 20px;">
                    <?php
                    // Include mapping table
                    include AMELIA_CPT_SYNC_PLUGIN_DIR . 'templates/art-mapping-table.php';
                    ?>
                </div>
                
                <!-- LOGIC SETTINGS -->
                <div class="card" style="max-width: 1200px; margin-top: 20px;">
                    <h2>Logic Settings</h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">Service ID Source</th>
                            <td>
                                <label>
                                    <input type="radio" 
                                           name="logic[service_id_source]" 
                                           value="cpt" 
                                           <?php checked($logic['service_id_source'] ?? 'cpt', 'cpt'); ?>>
                                    CPT Post ID (converts to amelia_service_id)
                                </label><br>
                                <label>
                                    <input type="radio" 
                                           name="logic[service_id_source]" 
                                           value="direct" 
                                           <?php checked($logic['service_id_source'] ?? 'cpt', 'direct'); ?>>
                                    Direct Amelia Service ID
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Category ID Source</th>
                            <td>
                                <label>
                                    <input type="radio" 
                                           name="logic[category_id_source]" 
                                           value="cpt" 
                                           <?php checked($logic['category_id_source'] ?? 'cpt', 'cpt'); ?>>
                                    From CPT Post Meta (if using CPT for service)
                                </label><br>
                                <label>
                                    <input type="radio" 
                                           name="logic[category_id_source]" 
                                           value="taxonomy_term" 
                                           <?php checked($logic['category_id_source'] ?? 'cpt', 'taxonomy_term'); ?>>
                                    From WordPress Taxonomy Term (reads Amelia category ID from term meta)
                                </label><br>
                                <label>
                                    <input type="radio" 
                                           name="logic[category_id_source]" 
                                           value="direct" 
                                           <?php checked($logic['category_id_source'] ?? 'cpt', 'direct'); ?>>
                                    Direct Amelia Category ID (from form field)
                                </label><br>
                                <label>
                                    <input type="radio" 
                                           name="logic[category_id_source]" 
                                           value="disabled" 
                                           <?php checked($logic['category_id_source'] ?? 'cpt', 'disabled'); ?>>
                                    Disabled (don't capture category)
                                </label>
                                <p class="description">
                                    How to interpret the category field from your form.<br>
                                    <strong>CPT:</strong> If form has a CPT post dropdown, category auto-reads from post meta.<br>
                                    <strong>Taxonomy Term:</strong> If form has a category dropdown (WordPress term), reads Amelia category ID from term meta.<br>
                                    <strong>Direct:</strong> Form field contains the Amelia category ID directly.<br>
                                    <strong>Disabled:</strong> Category will be set in the detail view by admin.
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Duration Mode</th>
                            <td>
                                <label>
                                    <input type="radio" 
                                           name="logic[duration_mode]" 
                                           value="start_end" 
                                           <?php checked($logic['duration_mode'] ?? 'manual', 'start_end'); ?>>
                                    Calculate from Start + End Times
                                </label><br>
                                <label>
                                    <input type="radio" 
                                           name="logic[duration_mode]" 
                                           value="manual" 
                                           <?php checked($logic['duration_mode'] ?? 'manual', 'manual'); ?>>
                                    Manual Entry (admin fills in workbench)
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Price Mode</th>
                            <td>
                                <label>
                                    <input type="radio" 
                                           name="logic[price_mode]" 
                                           value="manual" 
                                           <?php checked($logic['price_mode'] ?? 'manual', 'manual'); ?>>
                                    Manual Entry (admin fills in workbench)
                                </label><br>
                                <label>
                                    <input type="radio" 
                                           name="logic[price_mode]" 
                                           value="form" 
                                           <?php checked($logic['price_mode'] ?? 'manual', 'form'); ?>>
                                    From Form Field
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Location Mode</th>
                            <td>
                                <label>
                                    <input type="radio" 
                                           name="logic[location_mode]" 
                                           value="disabled" 
                                           <?php checked($logic['location_mode'] ?? 'disabled', 'disabled'); ?>>
                                    Disabled (null)
                                </label><br>
                                <label>
                                    <input type="radio" 
                                           name="logic[location_mode]" 
                                           value="form" 
                                           <?php checked($logic['location_mode'] ?? 'disabled', 'form'); ?>>
                                    From Form Field
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Persons Mode</th>
                            <td>
                                <label>
                                    <input type="radio" 
                                           name="logic[persons_mode]" 
                                           value="disabled" 
                                           <?php checked($logic['persons_mode'] ?? 'disabled', 'disabled'); ?>>
                                    Disabled (default to 1)
                                </label><br>
                                <label>
                                    <input type="radio" 
                                           name="logic[persons_mode]" 
                                           value="form" 
                                           <?php checked($logic['persons_mode'] ?? 'disabled', 'form'); ?>>
                                    From Form Field
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Validation Mode</th>
                            <td>
                                <label>
                                    <input type="radio" 
                                           name="logic[validation_mode]" 
                                           value="pass_through_fails" 
                                           <?php checked($logic['validation_mode'] ?? 'pass_through_fails', 'pass_through_fails'); ?>>
                                    <strong>Pass Through Fails</strong> (Forgiving) - Log and skip invalid fields
                                </label>
                                <p class="description" style="margin-left: 20px;">
                                    Invalid fields are logged and skipped. Request created with partial data. Admin fixes in workbench.
                                </p>
                                <label>
                                    <input type="radio" 
                                           name="logic[validation_mode]" 
                                           value="require_pass_through" 
                                           <?php checked($logic['validation_mode'] ?? 'pass_through_fails', 'require_pass_through'); ?>>
                                    <strong>Require Pass Through</strong> (Strict) - Fail form if critical fields invalid
                                </label>
                                <p class="description" style="margin-left: 20px;">
                                    Critical fields must be valid or form submission fails. User sees error message.
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- INTAKE FIELD DEFINITIONS -->
                <div class="card" style="max-width: 1200px; margin-top: 20px;">
                    <h2>Intake Field Definitions</h2>
                    <p>Define custom field labels that will be stored as intake data (not mapped to standard fields).</p>
                    
                    <div id="intake-fields-list">
                        <?php 
                        if (!empty($intake_fields)):
                            foreach ($intake_fields as $index => $field_label): 
                        ?>
                            <div class="intake-field-row" style="margin-bottom: 10px;">
                                <input type="text" 
                                       name="intake_fields[]" 
                                       value="<?php echo esc_attr($field_label); ?>" 
                                       class="regular-text" 
                                       placeholder="Field Label (e.g., Flight Number)">
                                <button type="button" class="button remove-intake-field">Remove</button>
                            </div>
                        <?php 
                            endforeach;
                        endif;
                        ?>
                    </div>
                    
                    <p>
                        <button type="button" class="button" id="add-intake-field">+ Add Intake Field</button>
                    </p>
                </div>
            <?php endif; ?>
            
            <!-- SAVE BUTTON -->
            <p class="submit">
                <button type="submit" class="button button-primary button-large">
                    <?php if ($has_json && !empty($fields)): ?>
                        Save All Configuration
                    <?php else: ?>
                        Save Basic Info (Upload JSON to enable mapping)
                    <?php endif; ?>
                </button>
            </p>
        </form>
        
        <script>
        jQuery(document).ready(function($) {
            // Add intake field
            $('#add-intake-field').on('click', function() {
                var html = '<div class="intake-field-row" style="margin-bottom: 10px;">' +
                    '<input type="text" name="intake_fields[]" class="regular-text" placeholder="Field Label (e.g., Flight Number)">' +
                    ' <button type="button" class="button remove-intake-field">Remove</button>' +
                    '</div>';
                $('#intake-fields-list').append(html);
            });
            
            // Remove intake field
            $(document).on('click', '.remove-intake-field', function() {
                $(this).closest('.intake-field-row').remove();
            });
        });
        </script>
    <?php endif; ?>
</div>

<style>
    .card {
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        padding: 20px;
        box-shadow: 0 1px 1px rgba(0,0,0,.04);
    }
    .card h2 {
        margin-top: 0;
        padding-bottom: 10px;
        border-bottom: 1px solid #eee;
    }
</style>
