<?php
/**
 * Popup Manager Admin Page Template
 *
 * @package AmeliaCPTSync
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

$manager = new Amelia_CPT_Sync_Popup_Config_Manager();
$configurations = $manager->get_configurations();
$detector = new Amelia_CPT_Sync_Field_Detector();
?>

<div class="wrap amelia-popup-manager">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="notice notice-info">
        <p><strong><?php _e('Dynamic Popups:', 'amelia-cpt-sync'); ?></strong> <?php _e('Configure popup trigger settings for dynamically loading Amelia booking forms into JetPopups from your listing grids.', 'amelia-cpt-sync'); ?></p>
    </div>
    
    <form id="popup-config-form" method="post">
        <?php wp_nonce_field('amelia_popup_config', 'popup_config_nonce'); ?>
        
        <!-- Global Settings -->
        <div class="config-section">
            <h2><?php _e('Global Settings', 'amelia-cpt-sync'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="default_popup_id"><?php _e('Default Popup Template ID', 'amelia-cpt-sync'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="global[default_popup_id]" id="default_popup_id" 
                               class="regular-text" 
                               value="<?php echo esc_attr($configurations['global']['default_popup_id']); ?>"
                               placeholder="e.g., amelia-booking-popup">
                        <p class="description"><?php _e('Default JetPopup template ID to use for all configurations (can be overridden per config).', 'amelia-cpt-sync'); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        
        <hr>
        
        <!-- Popup Configurations -->
        <div class="config-section">
            <h2><?php _e('Popup Trigger Configurations', 'amelia-cpt-sync'); ?></h2>
            
            <div id="configurations-container">
                <?php if (empty($configurations['configs'])): ?>
                    <p class="no-configs-message"><?php _e('No configurations yet. Click "Add New Configuration" to get started.', 'amelia-cpt-sync'); ?></p>
                <?php else: ?>
                    <?php foreach ($configurations['configs'] as $config_id => $config): ?>
                        <div class="popup-config-item" data-config-id="<?php echo esc_attr($config_id); ?>">
                            <h3><?php echo esc_html($config['label']); ?></h3>
                            
                            <table class="form-table">
                                <tr>
                                    <th><label><?php _e('Label/Name', 'amelia-cpt-sync'); ?></label></th>
                                    <td>
                                        <input type="text" name="configs[<?php echo esc_attr($config_id); ?>][label]" 
                                               class="regular-text" 
                                               value="<?php echo esc_attr($config['label']); ?>"
                                               placeholder="e.g., Service Booking Form">
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th><label><?php _e('Amelia Type', 'amelia-cpt-sync'); ?></label></th>
                                    <td>
                                        <select name="configs[<?php echo esc_attr($config_id); ?>][amelia_type]" class="regular-text amelia-type-selector">
                                            <option value="service" <?php selected($config['amelia_type'], 'service'); ?>>Service</option>
                                            <option value="category" <?php selected($config['amelia_type'], 'category'); ?>>Category</option>
                                            <option value="employee" <?php selected($config['amelia_type'], 'employee'); ?>>Employee</option>
                                            <option value="event" <?php selected($config['amelia_type'], 'event'); ?>>Event</option>
                                            <option value="package" <?php selected($config['amelia_type'], 'package'); ?>>Package</option>
                                            <option value="location" <?php selected($config['amelia_type'], 'location'); ?>>Location</option>
                                            <option value="custom" <?php selected($config['amelia_type'], 'custom'); ?>>→ Custom Type (specify below)</option>
                                        </select>
                                        
                                        <div class="custom-type-field" style="margin-top: 10px; <?php echo ($config['amelia_type'] !== 'custom' ? 'display:none;' : ''); ?>">
                                            <input type="text" name="configs[<?php echo esc_attr($config_id); ?>][custom_type]" 
                                                   class="regular-text" 
                                                   value="<?php echo esc_attr(isset($config['custom_type']) ? $config['custom_type'] : ''); ?>"
                                                   placeholder="e.g., resource, appointment">
                                            <p class="description"><?php _e('Enter the custom parameter name for Amelia shortcode.', 'amelia-cpt-sync'); ?></p>
                                        </div>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th><label><?php _e('JetPopup Template ID', 'amelia-cpt-sync'); ?></label></th>
                                    <td>
                                        <input type="text" name="configs[<?php echo esc_attr($config_id); ?>][popup_id]" 
                                               class="regular-text" 
                                               value="<?php echo esc_attr($config['popup_id']); ?>"
                                               placeholder="e.g., amelia-booking-popup">
                                        <p class="description"><?php _e('The ID/slug of your JetPopup template (find this in JetPopup settings).', 'amelia-cpt-sync'); ?></p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th><label><?php _e('Source', 'amelia-cpt-sync'); ?></label></th>
                                    <td>
                                        <label>
                                            <input type="radio" name="configs[<?php echo esc_attr($config_id); ?>][source_type]" value="cpt_meta" <?php checked(isset($config['source_type']) ? $config['source_type'] : 'cpt_meta', 'cpt_meta'); ?>>
                                            <?php _e('CPT Post Meta', 'amelia-cpt-sync'); ?>
                                        </label>
                                        &nbsp;&nbsp;
                                        <label>
                                            <input type="radio" name="configs[<?php echo esc_attr($config_id); ?>][source_type]" value="term_meta" <?php checked(isset($config['source_type']) ? $config['source_type'] : 'cpt_meta', 'term_meta'); ?>>
                                            <?php _e('Taxonomy Term Meta', 'amelia-cpt-sync'); ?>
                                        </label>
                                        <p class="description"><?php _e('Where the Amelia ID is stored (usually CPT Post Meta for services, Term Meta for categories).', 'amelia-cpt-sync'); ?></p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th><label><?php _e('Meta Field Name', 'amelia-cpt-sync'); ?></label></th>
                                    <td>
                                        <input type="text" name="configs[<?php echo esc_attr($config_id); ?>][meta_field]" 
                                               class="regular-text config-meta-field" 
                                               value="<?php echo esc_attr($config['meta_field']); ?>"
                                               placeholder="e.g., service_id">
                                        <p class="description"><?php _e('The meta field name that contains the Amelia ID (must exist on your CPT or taxonomy).', 'amelia-cpt-sync'); ?></p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th><label><?php _e('Elementor Attributes', 'amelia-cpt-sync'); ?></label></th>
                                    <td>
                                        <p><strong><?php _e('Add these attributes to your Elementor button:', 'amelia-cpt-sync'); ?></strong></p>
                                        <?php 
                                        $amelia_type = $config['amelia_type'];
                                        if (!empty($config['custom_type'])) {
                                            $amelia_type = $config['custom_type'];
                                        }
                                        ?>
                                        
                                        <table class="widefat" style="max-width: 600px; margin-bottom: 10px;">
                                            <thead>
                                                <tr>
                                                    <th style="width: 50%;">Attribute Key</th>
                                                    <th style="width: 50%;">Attribute Value</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td><code>data-amelia-type</code></td>
                                                    <td><code><?php echo esc_html($amelia_type); ?></code></td>
                                                </tr>
                                                <tr>
                                                    <td><code>data-amelia-id</code></td>
                                                    <td><code>%<?php echo esc_html($config['meta_field']); ?>%</code> <small>(replace with JetEngine tag)</small></td>
                                                </tr>
                                                <tr>
                                                    <td><code>data-jet-popup</code></td>
                                                    <td><code><?php echo esc_html($config['popup_id']); ?></code></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                        
                                        <p><strong><?php _e('CSS Class to Add:', 'amelia-cpt-sync'); ?></strong> <code>amelia-booking-trigger</code></p>
                                        
                                        <details style="margin-top: 15px;">
                                            <summary style="cursor: pointer; color: #2271b1;"><?php _e('📋 Copy/Paste Format (for manual editing)', 'amelia-cpt-sync'); ?></summary>
                                            <textarea class="large-text code generated-attributes" rows="4" readonly style="margin-top: 10px;"><?php 
                                                echo esc_textarea($manager->generate_elementor_attributes($config)); 
                                            ?></textarea>
                                            <button type="button" class="button button-secondary copy-attributes" data-config-id="<?php echo esc_attr($config_id); ?>">
                                                <span class="dashicons dashicons-clipboard"></span>
                                                <?php _e('Copy to Clipboard', 'amelia-cpt-sync'); ?>
                                            </button>
                                            <span class="copy-success" style="color: #46b450; margin-left: 10px; display: none;">✓ Copied!</span>
                                        </details>
                                    </td>
                                </tr>
                            </table>
                            
                            <p>
                                <button type="button" class="button button-link-delete delete-config" data-config-id="<?php echo esc_attr($config_id); ?>">
                                    <?php _e('Delete This Configuration', 'amelia-cpt-sync'); ?>
                                </button>
                            </p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <p>
                <button type="button" id="add-new-config" class="button button-secondary">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php _e('Add New Configuration', 'amelia-cpt-sync'); ?>
                </button>
            </p>
        </div>
        
        <hr>
        
        <!-- Setup Instructions -->
        <div class="config-section">
            <h2 class="instructions-toggle" style="cursor: pointer;">
                <span class="dashicons dashicons-book"></span>
                <?php _e('Setup Instructions', 'amelia-cpt-sync'); ?>
                <span class="dashicons dashicons-arrow-down-alt2"></span>
            </h2>
            
            <div class="instructions-content" style="display: none;">
                <div class="notice notice-info inline">
                    <h3><?php _e('📖 How to Use with Elementor & JetPopup', 'amelia-cpt-sync'); ?></h3>
                    
                    <h4><?php _e('Step 1: Create JetPopup Template', 'amelia-cpt-sync'); ?></h4>
                    <ol>
                        <li><?php _e('Go to: JetPopup → Add New', 'amelia-cpt-sync'); ?></li>
                        <li><?php _e('Name your popup (e.g., "Amelia Booking Form")', 'amelia-cpt-sync'); ?></li>
                        <li><?php _e('Add an HTML widget with this code:', 'amelia-cpt-sync'); ?>
                            <pre class="code-snippet"><code>&lt;div id="amelia-form-container" class="amelia-dynamic-popup"&gt;
  &lt;div class="amelia-loading"&gt;
    &lt;span class="spinner is-active"&gt;&lt;/span&gt;
    &lt;p&gt;Loading booking form...&lt;/p&gt;
  &lt;/div&gt;
&lt;/div&gt;</code></pre>
                            <button type="button" class="button button-small copy-html-snippet"><?php _e('Copy HTML', 'amelia-cpt-sync'); ?></button>
                        </li>
                        <li><?php _e('Publish the popup', 'amelia-cpt-sync'); ?></li>
                        <li><?php _e('Note the Popup ID from JetPopup settings (e.g., "amelia-booking-popup")', 'amelia-cpt-sync'); ?></li>
                    </ol>
                    
                    <h4><?php _e('Step 2: Configure Popup Trigger', 'amelia-cpt-sync'); ?></h4>
                    <ol>
                        <li><?php _e('Click "Add New Configuration" above', 'amelia-cpt-sync'); ?></li>
                        <li><?php _e('Fill in:', 'amelia-cpt-sync'); ?>
                            <ul>
                                <li><?php _e('Label: Descriptive name for your reference', 'amelia-cpt-sync'); ?></li>
                                <li><?php _e('Amelia Type: Service, Category, Employee, etc.', 'amelia-cpt-sync'); ?></li>
                                <li><?php _e('JetPopup Template ID: The ID you noted in Step 1', 'amelia-cpt-sync'); ?></li>
                                <li><?php _e('Meta Field: The field containing Amelia ID (e.g., service_id)', 'amelia-cpt-sync'); ?></li>
                            </ul>
                        </li>
                        <li><?php _e('Generated attributes will appear automatically', 'amelia-cpt-sync'); ?></li>
                        <li><?php _e('Click "Copy to Clipboard"', 'amelia-cpt-sync'); ?></li>
                        <li><?php _e('Click "Save Configurations"', 'amelia-cpt-sync'); ?></li>
                    </ol>
                    
                    <h4><?php _e('Step 3: Setup Elementor Button', 'amelia-cpt-sync'); ?></h4>
                    <ol>
                        <li><?php _e('Edit your JetEngine Listing Template in Elementor', 'amelia-cpt-sync'); ?></li>
                        <li><?php _e('Add a Button widget', 'amelia-cpt-sync'); ?></li>
                        <li><?php _e('Link: Can leave empty or use #', 'amelia-cpt-sync'); ?></li>
                        <li><?php _e('Advanced Tab → Custom Attributes', 'amelia-cpt-sync'); ?>
                            <br><strong><?php _e('⚠️ Add each attribute separately:', 'amelia-cpt-sync'); ?></strong>
                            <ul>
                                <li><?php _e('Click "Custom Field" (+ icon)', 'amelia-cpt-sync'); ?></li>
                                <li><?php _e('Before: data-amelia-type | After: service', 'amelia-cpt-sync'); ?></li>
                                <li><?php _e('Click "Custom Field" again', 'amelia-cpt-sync'); ?></li>
                                <li><?php _e('Before: data-amelia-id | After: Click dynamic tag icon → JetEngine → service_id', 'amelia-cpt-sync'); ?></li>
                                <li><?php _e('Click "Custom Field" again', 'amelia-cpt-sync'); ?></li>
                                <li><?php _e('Before: data-jet-popup | After: book-by-vehicle-popup', 'amelia-cpt-sync'); ?></li>
                            </ul>
                        </li>
                        <li><?php _e('Advanced Tab → CSS Classes → Add: amelia-booking-trigger', 'amelia-cpt-sync'); ?></li>
                        <li><?php _e('⚠️ IMPORTANT: Add class amelia-booking-trigger to CSS Classes field!', 'amelia-cpt-sync'); ?></li>
                        <li><?php _e('Publish your template', 'amelia-cpt-sync'); ?></li>
                    </ol>
                    
                    <h4><?php _e('Step 4: Test', 'amelia-cpt-sync'); ?></h4>
                    <ol>
                        <li><?php _e('Visit your listing grid on the frontend', 'amelia-cpt-sync'); ?></li>
                        <li><?php _e('Click the button', 'amelia-cpt-sync'); ?></li>
                        <li><?php _e('✅ JetPopup should open', 'amelia-cpt-sync'); ?></li>
                        <li><?php _e('✅ Loading spinner should appear', 'amelia-cpt-sync'); ?></li>
                        <li><?php _e('✅ Amelia booking form should load (filtered correctly)', 'amelia-cpt-sync'); ?></li>
                    </ol>
                    
                    <h4><?php _e('🔧 Troubleshooting', 'amelia-cpt-sync'); ?></h4>
                    <ul>
                        <li><strong><?php _e('Popup shows literal shortcode text:', 'amelia-cpt-sync'); ?></strong> <?php _e('Check browser console for errors. Enable debug logging.', 'amelia-cpt-sync'); ?></li>
                        <li><strong><?php _e('Popup doesn\'t open:', 'amelia-cpt-sync'); ?></strong> <?php _e('Verify JetPopup ID matches configuration. Check button has amelia-booking-trigger class.', 'amelia-cpt-sync'); ?></li>
                        <li><strong><?php _e('Form loads but calendar/features don\'t work:', 'amelia-cpt-sync'); ?></strong> <?php _e('Amelia JS may need re-initialization. Check debug log and contact support.', 'amelia-cpt-sync'); ?></li>
                        <li><strong><?php _e('Wrong service/category shows:', 'amelia-cpt-sync'); ?></strong> <?php _e('Check dynamic tag is reading correct meta field. Verify value exists in CPT post.', 'amelia-cpt-sync'); ?></li>
                    </ul>
                </div>
            </div>
        </div>
        
        <p class="submit">
            <button type="button" id="save-popup-configs" class="button button-primary button-hero">
                <span class="dashicons dashicons-saved" style="vertical-align: middle; margin-right: 8px;"></span>
                <?php _e('Save Configurations', 'amelia-cpt-sync'); ?>
            </button>
            <span class="spinner" style="float: none; margin: 0 10px;"></span>
            <span id="save-popup-message"></span>
        </p>
    </form>
</div>

<style>
.amelia-popup-manager .config-section {
    background: #fff;
    padding: 20px;
    margin: 20px 0;
    border: 1px solid #ccd0d4;
}

.popup-config-item {
    background: #f9f9f9;
    padding: 20px;
    margin: 15px 0;
    border: 1px solid #ddd;
    border-left: 4px solid #2271b1;
}

.popup-config-item h3 {
    margin-top: 0;
}

.generated-attributes {
    font-family: Consolas, Monaco, monospace;
    background: #f5f5f5;
    border: 1px solid #ddd;
}

.copy-success {
    animation: fadeIn 0.3s;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.instructions-toggle {
    padding: 10px;
    background: #f0f0f0;
    border-radius: 4px;
}

.instructions-toggle:hover {
    background: #e5e5e5;
}

.instructions-content {
    background: #fff;
    padding: 20px;
    margin-top: 10px;
}

.instructions-content h3 {
    color: #2271b1;
}

.instructions-content h4 {
    margin-top: 25px;
    color: #555;
}

.code-snippet {
    background: #1e1e1e;
    color: #d4d4d4;
    padding: 15px;
    border-radius: 4px;
    overflow: auto;
}

.code-snippet code {
    color: #d4d4d4;
}

#save-popup-message.success {
    color: #46b450;
    font-weight: 600;
}

#save-popup-message.error {
    color: #dc3232;
    font-weight: 600;
}

.no-configs-message {
    padding: 40px;
    text-align: center;
    color: #999;
    font-size: 16px;
}

.custom-type-field {
    margin-top: 10px;
}

.delete-config {
    color: #dc3232;
}

.delete-config:hover {
    color: #a00;
}
</style>

<script>
jQuery(document).ready(function($) {
    console.log('[Popup Manager] Page loaded');
    
    // Toggle instructions
    $('.instructions-toggle').on('click', function() {
        $('.instructions-content').slideToggle();
        $(this).find('.dashicons-arrow-down-alt2, .dashicons-arrow-up-alt2').toggleClass('dashicons-arrow-down-alt2 dashicons-arrow-up-alt2');
    });
    
    // Live update generated attributes
    function updateGeneratedAttributes($configItem) {
        var ameliaType = $configItem.find('.amelia-type-selector').val();
        var customType = $configItem.find('[name*="[custom_type]"]').val();
        var popupId = $configItem.find('[name*="[popup_id]"]').val();
        var metaField = $configItem.find('[name*="[meta_field]"]').val();
        
        // Use custom type if selected
        var finalType = ameliaType === 'custom' && customType ? customType : ameliaType;
        
        // Build attributes
        var attributes = [];
        if (finalType) {
            attributes.push('data-amelia-type|' + finalType);
        }
        if (metaField) {
            attributes.push('data-amelia-id|%' + metaField + '%');
        }
        if (popupId) {
            attributes.push('data-jet-popup|' + popupId);
        }
        
        // Update textarea
        $configItem.find('.generated-attributes').val(attributes.join('\n'));
        
        console.log('[Popup Manager] Updated attributes for config');
    }
    
    // Update attributes on field change
    $(document).on('change keyup', '.amelia-type-selector, [name*="[custom_type]"], [name*="[popup_id]"], .config-meta-field', function() {
        var $configItem = $(this).closest('.popup-config-item');
        updateGeneratedAttributes($configItem);
    });
    
    // Show/hide custom type field
    $(document).on('change', '.amelia-type-selector', function() {
        var $select = $(this);
        var $customField = $select.closest('td').find('.custom-type-field');
        
        if ($select.val() === 'custom') {
            $customField.show();
        } else {
            $customField.hide();
        }
    });
    
    // Copy attributes to clipboard
    $('.copy-attributes').on('click', function() {
        var $button = $(this);
        var $textarea = $button.closest('td').find('.generated-attributes');
        var $success = $button.siblings('.copy-success');
        
        $textarea.select();
        document.execCommand('copy');
        
        $success.fadeIn().delay(2000).fadeOut();
    });
    
    // Copy HTML snippet
    $('.copy-html-snippet').on('click', function() {
        var $code = $(this).prev('.code-snippet').find('code');
        var text = $code.text();
        
        var $temp = $('<textarea>');
        $('body').append($temp);
        $temp.val(text).select();
        document.execCommand('copy');
        $temp.remove();
        
        $(this).text('✓ Copied!');
        setTimeout(function() {
            $('.copy-html-snippet').text('Copy HTML');
        }, 2000);
    });
    
    // Add new configuration
    $('#add-new-config').on('click', function() {
        var timestamp = Date.now();
        var configId = 'new_config_' + timestamp;
        
        var newConfig = `
            <div class="popup-config-item" data-config-id="${configId}">
                <h3>New Configuration</h3>
                
                <table class="form-table">
                    <tr>
                        <th><label>Label/Name</label></th>
                        <td>
                            <input type="text" name="configs[${configId}][label]" class="regular-text" placeholder="e.g., Service Booking Form">
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label>Amelia Type</label></th>
                        <td>
                            <select name="configs[${configId}][amelia_type]" class="regular-text amelia-type-selector">
                                <option value="service">Service</option>
                                <option value="category">Category</option>
                                <option value="employee">Employee</option>
                                <option value="event">Event</option>
                                <option value="package">Package</option>
                                <option value="location">Location</option>
                                <option value="custom">→ Custom Type (specify below)</option>
                            </select>
                            
                            <div class="custom-type-field" style="margin-top: 10px; display: none;">
                                <input type="text" name="configs[${configId}][custom_type]" class="regular-text" placeholder="e.g., resource, appointment">
                                <p class="description">Enter the custom parameter name for Amelia shortcode.</p>
                            </div>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label>JetPopup Template ID</label></th>
                        <td>
                            <input type="text" name="configs[${configId}][popup_id]" class="regular-text" placeholder="e.g., amelia-booking-popup">
                            <p class="description">The ID/slug of your JetPopup template.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label>Source</label></th>
                        <td>
                            <label>
                                <input type="radio" name="configs[${configId}][source_type]" value="cpt_meta" checked>
                                CPT Post Meta
                            </label>
                            &nbsp;&nbsp;
                            <label>
                                <input type="radio" name="configs[${configId}][source_type]" value="term_meta">
                                Taxonomy Term Meta
                            </label>
                            <p class="description">Where the Amelia ID is stored.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label>Meta Field Name</label></th>
                        <td>
                            <input type="text" name="configs[${configId}][meta_field]" class="regular-text config-meta-field" placeholder="e.g., service_id">
                            <p class="description">The meta field name that contains the Amelia ID.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label>Generated Elementor Attributes</label></th>
                        <td>
                            <textarea class="large-text code generated-attributes" rows="4" readonly></textarea>
                            <p>
                                <button type="button" class="button button-secondary copy-attributes">
                                    <span class="dashicons dashicons-clipboard"></span>
                                    Copy to Clipboard
                                </button>
                                <span class="copy-success" style="color: #46b450; margin-left: 10px; display: none;">✓ Copied!</span>
                            </p>
                            <p class="description">Paste these into Elementor Button → Advanced → Custom Attributes. Replace %field_name% with JetEngine dynamic tags.</p>
                        </td>
                    </tr>
                </table>
                
                <p>
                    <button type="button" class="button button-link-delete delete-config" data-config-id="${configId}">
                        Delete This Configuration
                    </button>
                </p>
            </div>
        `;
        
        $('.no-configs-message').remove();
        $('#configurations-container').append(newConfig);
    });
    
    // Delete configuration
    $(document).on('click', '.delete-config', function() {
        if (!confirm('Are you sure you want to delete this configuration?')) {
            return;
        }
        
        $(this).closest('.popup-config-item').fadeOut(300, function() {
            $(this).remove();
            
            if ($('.popup-config-item').length === 0) {
                $('#configurations-container').html('<p class="no-configs-message">No configurations yet. Click "Add New Configuration" to get started.</p>');
            }
        });
    });
    
    // Save configurations
    $('#save-popup-configs').on('click', function() {
        console.log('[Popup Manager] Save button clicked');
        
        var $button = $(this);
        var $spinner = $('.spinner');
        var $message = $('#save-popup-message');
        
        $button.prop('disabled', true);
        $spinner.addClass('is-active');
        $message.text('').removeClass('success error');
        
        // Serialize form data
        var formData = $('#popup-config-form').serialize();
        formData += '&action=amelia_save_popup_configs';
        formData += '&nonce=' + '<?php echo wp_create_nonce("amelia_popup_save"); ?>';
        
        console.log('[Popup Manager] Sending AJAX request');
        console.log('[Popup Manager] Form data length:', formData.length);
        
        $.post(ajaxurl, formData, function(response) {
            console.log('[Popup Manager] AJAX response:', response);
            
            if (response.success) {
                $message.text(response.data.message).addClass('success');
                
                console.log('[Popup Manager] Save successful, reloading page...');
                
                // Reload page to refresh generated attributes
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                console.error('[Popup Manager] Save failed:', response.data.message);
                $message.text(response.data.message).addClass('error');
            }
        }).fail(function(xhr, status, error) {
            console.error('[Popup Manager] AJAX error:', status, error);
            console.error('[Popup Manager] Response:', xhr.responseText);
            $message.text('Error saving configurations: ' + error).addClass('error');
        }).always(function() {
            $button.prop('disabled', false);
            $spinner.removeClass('is-active');
        });
    });
});
</script>

