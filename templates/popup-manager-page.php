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
                               value="<?php echo esc_attr(isset($configurations['global']['default_popup_id']) ? $configurations['global']['default_popup_id'] : ''); ?>"
                               placeholder="e.g., amelia-booking-popup">
                        <p class="description"><?php _e('Optional fallback JetPopup ID if a trigger button does not specify one.', 'amelia-cpt-sync'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="popup_debug_enabled"><?php _e('Enable Popup Debug Logging', 'amelia-cpt-sync'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="global[debug_enabled]" id="popup_debug_enabled" value="1" <?php checked(!empty($configurations['global']['debug_enabled'])); ?>>
                            <?php _e('When enabled, frontend popup events are written to the plugin debug log.', 'amelia-cpt-sync'); ?>
                        </label>
                    </td>
                </tr>
            </table>
        </div>

        <hr>

        <!-- Popup Configurations -->
        <div class="config-section">
            <h2><?php _e('Popup Trigger Configurations', 'amelia-cpt-sync'); ?></h2>
            <p class="section-intro"><?php _e('Create one entry for each JetPopup template that should load an Amelia booking form dynamically. Elementor and JetEngine handle the dynamic shortcode—this plugin just renders it when the popup opens.', 'amelia-cpt-sync'); ?></p>

            <div id="configurations-container">
                <?php if (empty($configurations['configs'])): ?>
                    <p class="no-configs-message"><?php _e('No configurations yet. Click "Add New Configuration" to get started.', 'amelia-cpt-sync'); ?></p>
                <?php else: ?>
                    <?php foreach ($configurations['configs'] as $config_id => $config):
                        $label = isset($config['label']) ? $config['label'] : '';
                        $popup_id = isset($config['popup_id']) ? $config['popup_id'] : '';
                        $notes = isset($config['notes']) ? $config['notes'] : '';
                    ?>
                        <div class="popup-config-item" data-config-id="<?php echo esc_attr($config_id); ?>">
                            <div class="popup-config-header">
                                <h3 class="config-title"><?php echo esc_html($label ? $label : __('Untitled Popup', 'amelia-cpt-sync')); ?></h3>
                                <span class="config-subtitle">
                                    <?php echo $popup_id ? sprintf(__('Popup ID: %s', 'amelia-cpt-sync'), esc_html($popup_id)) : __('Popup ID not set yet', 'amelia-cpt-sync'); ?>
                                </span>
                            </div>

                            <table class="form-table">
                                <tr>
                                    <th><label><?php _e('Label/Name', 'amelia-cpt-sync'); ?></label></th>
                                    <td>
                                        <input type="text" name="configs[<?php echo esc_attr($config_id); ?>][label]"
                                               class="regular-text config-label-input"
                                               value="<?php echo esc_attr($label); ?>"
                                               placeholder="e.g., Vehicle Booking Popup">
                                        <p class="description"><?php _e('Internal name so your team knows which popup this entry covers.', 'amelia-cpt-sync'); ?></p>
                                    </td>
                                </tr>

                                <tr>
                                    <th><label><?php _e('JetPopup Template ID', 'amelia-cpt-sync'); ?></label></th>
                                    <td>
                                        <input type="text" name="configs[<?php echo esc_attr($config_id); ?>][popup_id]"
                                               class="regular-text config-popup-id-input"
                                               value="<?php echo esc_attr($popup_id); ?>"
                                               placeholder="e.g., book-by-vehicle-popup">
                                        <p class="description"><?php _e('Find the slug/ID in JetPopup → All Popups. This must match the value you set under Elementor → JetPopup action.', 'amelia-cpt-sync'); ?></p>
                                    </td>
                                </tr>

                                <tr>
                                    <th><label><?php _e('Team Notes (Optional)', 'amelia-cpt-sync'); ?></label></th>
                                    <td>
                                        <textarea name="configs[<?php echo esc_attr($config_id); ?>][notes]" rows="3" class="large-text" placeholder="<?php esc_attr_e('Store the Amelia shortcode your team uses, special instructions, etc.', 'amelia-cpt-sync'); ?>"><?php echo esc_textarea($notes); ?></textarea>
                                    </td>
                                </tr>
                            </table>

                            <div class="elementor-instructions-box">
                                <h4><?php _e('Elementor Setup Checklist', 'amelia-cpt-sync'); ?></h4>
                                <ol>
                                    <li><?php _e('Edit the JetEngine listing item that contains your trigger button.', 'amelia-cpt-sync'); ?></li>
                                    <li><?php _e('Under Advanced → JetPopup, choose this popup ID so JetPopup handles the opening.', 'amelia-cpt-sync'); ?></li>
                                    <li><?php _e('Add CSS class', 'amelia-cpt-sync'); ?> <code>amelia-booking-trigger</code>.</li>
                                    <li><?php _e('Add a Custom Attribute with key', 'amelia-cpt-sync'); ?> <code>data-amelia-shortcode</code> <?php _e('and set the value to your fully built Amelia shortcode (JetEngine dynamic tags welcome).', 'amelia-cpt-sync'); ?></li>
                                </ol>

                                <div class="attribute-template">
                                    <label><?php _e('Copy-ready Attribute Template', 'amelia-cpt-sync'); ?></label>
                                    <div class="attribute-template__field">
                                        <input type="text" class="regular-text code attribute-template-input" value="data-amelia-shortcode|[paste your Amelia shortcode here]" readonly>
                                        <button type="button" class="button button-secondary copy-attribute-template">
                                            <span class="dashicons dashicons-clipboard"></span> <?php _e('Copy', 'amelia-cpt-sync'); ?>
                                        </button>
                                    </div>
                                    <p class="description"><?php _e('Replace the placeholder with the exact Amelia shortcode from your listing. JetEngine can output the dynamic ID inside this value.', 'amelia-cpt-sync'); ?></p>
                                </div>
                            </div>

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
                    <h3><?php _e('📖 How the Dynamic Popup Loader Works', 'amelia-cpt-sync'); ?></h3>

                    <h4><?php _e('Step 1: Build the JetPopup Template', 'amelia-cpt-sync'); ?></h4>
                    <ol>
                        <li><?php _e('Go to JetPopup → Add New and design your popup layout.', 'amelia-cpt-sync'); ?></li>
                        <li><?php _e('Insert an HTML widget that contains the loader container:', 'amelia-cpt-sync'); ?>
                            <pre class="code-snippet"><code>&lt;div id="amelia-form-container" class="amelia-dynamic-popup"&gt;
  &lt;div class="amelia-loading"&gt;
    &lt;span class="spinner is-active"&gt;&lt;/span&gt;
    &lt;p&gt;Loading booking form...&lt;/p&gt;
  &lt;/div&gt;
&lt;/div&gt;</code></pre>
                            <button type="button" class="button button-small copy-html-snippet"><?php _e('Copy HTML', 'amelia-cpt-sync'); ?></button>
                        </li>
                        <li><?php _e('Publish the popup and make note of its slug/ID.', 'amelia-cpt-sync'); ?></li>
                    </ol>

                    <h4><?php _e('Step 2: Configure Elementor / JetEngine Button', 'amelia-cpt-sync'); ?></h4>
                    <ol>
                        <li><?php _e('Set the JetPopup action on your button to the popup ID from Step 1 so JetPopup opens it.', 'amelia-cpt-sync'); ?></li>
                        <li><?php _e('Add CSS class', 'amelia-cpt-sync'); ?> <code>amelia-booking-trigger</code>.</li>
                        <li><?php _e('Add custom attribute', 'amelia-cpt-sync'); ?> <code>data-amelia-shortcode</code> <?php _e('and paste your full Amelia shortcode. JetEngine dynamic tags can populate IDs inside that string.', 'amelia-cpt-sync'); ?></li>
                        <li><?php _e('Repeat for each listing card or trigger button that should open the popup.', 'amelia-cpt-sync'); ?></li>
                    </ol>

                    <h4><?php _e('Step 3: Test & Troubleshoot', 'amelia-cpt-sync'); ?></h4>
                    <ul>
                        <li><strong><?php _e('Popup shows raw shortcode:', 'amelia-cpt-sync'); ?></strong> <?php _e('Check that the custom attribute value is the full Amelia shortcode. Enable popup debug logging above to capture details.', 'amelia-cpt-sync'); ?></li>
                        <li><strong><?php _e('Popup doesn\'t open:', 'amelia-cpt-sync'); ?></strong> <?php _e('Confirm JetPopup action is assigned and the button uses the correct popup ID.', 'amelia-cpt-sync'); ?></li>
                        <li><strong><?php _e('Wrong booking data:', 'amelia-cpt-sync'); ?></strong> <?php _e('Review the shortcode value Elementor outputs. JetEngine must supply the correct ID before the popup opens.', 'amelia-cpt-sync'); ?></li>
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

.section-intro {
    margin-top: 0;
    margin-bottom: 15px;
    color: #555;
}

.popup-config-item {
    background: #f9f9f9;
    padding: 20px;
    margin: 15px 0;
    border: 1px solid #ddd;
    border-left: 4px solid #2271b1;
}

.popup-config-header {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    gap: 10px;
    margin-bottom: 10px;
}

.popup-config-header .config-title {
    margin: 0;
    font-size: 1.2em;
}

.popup-config-header .config-subtitle {
    font-size: 0.9em;
    color: #666;
}

.elementor-instructions-box {
    background: #fff;
    border: 1px solid #dcdcdc;
    padding: 15px 20px;
    margin: 20px 0 10px;
}

.elementor-instructions-box h4 {
    margin-top: 0;
}

.elementor-instructions-box ol {
    margin-left: 20px;
}

.attribute-template {
    margin-top: 15px;
}

.attribute-template__field {
    display: flex;
    gap: 10px;
    margin-bottom: 5px;
}

.attribute-template-input {
    flex: 1 1 auto;
    font-family: Consolas, Monaco, monospace;
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

.delete-config {
    color: #dc3232;
}

.delete-config:hover {
    color: #a00;
}
</style>

<script>
jQuery(function($) {
    const untitledLabel = '<?php echo esc_js(__('Untitled Popup', 'amelia-cpt-sync')); ?>';
    const popupNotSet = '<?php echo esc_js(__('Popup ID not set yet', 'amelia-cpt-sync')); ?>';

    console.log('[Popup Manager] Page loaded');

    function updateConfigSummary($config) {
        const label = $.trim($config.find('.config-label-input').val());
        const popupId = $.trim($config.find('.config-popup-id-input').val());

        $config.find('.config-title').text(label || untitledLabel);
        $config.find('.config-subtitle').text(popupId ? '<?php echo esc_js(__('Popup ID: ', 'amelia-cpt-sync')); ?>' + popupId : popupNotSet);
    }

    function bindNewConfig($config) {
        updateConfigSummary($config);
    }

    // Toggle instructions panel
    $('.instructions-toggle').on('click', function() {
        $('.instructions-content').slideToggle();
        $(this).find('.dashicons-arrow-down-alt2, .dashicons-arrow-up-alt2').toggleClass('dashicons-arrow-down-alt2 dashicons-arrow-up-alt2');
    });

    // Update headers when inputs change
    $(document).on('input', '.config-label-input, .config-popup-id-input', function() {
        const $config = $(this).closest('.popup-config-item');
        updateConfigSummary($config);
    });

    // Copy attribute template helper
    $(document).on('click', '.copy-attribute-template', function() {
        const $button = $(this);
        const $input = $button.closest('.attribute-template__field').find('.attribute-template-input');

        $input.trigger('select');

        try {
            document.execCommand('copy');
            $button.html('<span class="dashicons dashicons-yes"></span> <?php echo esc_js(__('Copied', 'amelia-cpt-sync')); ?>');
        } catch (error) {
            console.warn('[Popup Manager] Copy failed', error);
        }

        setTimeout(function() {
            $button.html('<span class="dashicons dashicons-clipboard"></span> <?php echo esc_js(__('Copy', 'amelia-cpt-sync')); ?>');
        }, 1500);
    });

    // Copy HTML snippet from instructions
    $('.copy-html-snippet').on('click', function() {
        const $code = $(this).prev('.code-snippet').find('code');
        const text = $code.text();
        const $temp = $('<textarea>');

        $('body').append($temp);
        $temp.val(text).select();
        document.execCommand('copy');
        $temp.remove();

        $(this).text('✓ <?php echo esc_js(__('Copied!', 'amelia-cpt-sync')); ?>');
        setTimeout(function() {
            $('.copy-html-snippet').text('<?php echo esc_js(__('Copy HTML', 'amelia-cpt-sync')); ?>');
        }, 2000);
    });

    // Add configuration block
    $('#add-new-config').on('click', function() {
        const timestamp = Date.now();
        const configId = 'new_config_' + timestamp;

        const template = `
            <div class="popup-config-item" data-config-id="${configId}">
                <div class="popup-config-header">
                    <h3 class="config-title">${untitledLabel}</h3>
                    <span class="config-subtitle">${popupNotSet}</span>
                </div>

                <table class="form-table">
                    <tr>
                        <th><label><?php echo esc_js(__('Label/Name', 'amelia-cpt-sync')); ?></label></th>
                        <td>
                            <input type="text" name="configs[${configId}][label]" class="regular-text config-label-input" placeholder="<?php echo esc_js(__('e.g., Vehicle Booking Popup', 'amelia-cpt-sync')); ?>">
                            <p class="description"><?php echo esc_js(__('Internal reference for your team.', 'amelia-cpt-sync')); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php echo esc_js(__('JetPopup Template ID', 'amelia-cpt-sync')); ?></label></th>
                        <td>
                            <input type="text" name="configs[${configId}][popup_id]" class="regular-text config-popup-id-input" placeholder="<?php echo esc_js(__('e.g., book-by-vehicle-popup', 'amelia-cpt-sync')); ?>">
                            <p class="description"><?php echo esc_js(__('Must match the JetPopup slug used in Elementor.', 'amelia-cpt-sync')); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php echo esc_js(__('Team Notes (Optional)', 'amelia-cpt-sync')); ?></label></th>
                        <td>
                            <textarea name="configs[${configId}][notes]" rows="3" class="large-text" placeholder="<?php echo esc_js(__('Store the Amelia shortcode or setup reminders.', 'amelia-cpt-sync')); ?>"></textarea>
                        </td>
                    </tr>
                </table>

                <div class="elementor-instructions-box">
                    <h4><?php echo esc_js(__('Elementor Setup Checklist', 'amelia-cpt-sync')); ?></h4>
                    <ol>
                        <li><?php echo esc_js(__('Set JetPopup action to this popup ID.', 'amelia-cpt-sync')); ?></li>
                        <li><?php echo esc_js(__('Add CSS class', 'amelia-cpt-sync')); ?> <code>amelia-booking-trigger</code>.</li>
                        <li><?php echo esc_js(__('Add custom attribute', 'amelia-cpt-sync')); ?> <code>data-amelia-shortcode</code> <?php echo esc_js(__('with your Amelia shortcode.', 'amelia-cpt-sync')); ?></li>
                    </ol>
                    <div class="attribute-template">
                        <label><?php echo esc_js(__('Copy-ready Attribute Template', 'amelia-cpt-sync')); ?></label>
                        <div class="attribute-template__field">
                            <input type="text" class="regular-text code attribute-template-input" value="data-amelia-shortcode|[paste your Amelia shortcode here]" readonly>
                            <button type="button" class="button button-secondary copy-attribute-template">
                                <span class="dashicons dashicons-clipboard"></span> <?php echo esc_js(__('Copy', 'amelia-cpt-sync')); ?>
                            </button>
                        </div>
                        <p class="description"><?php echo esc_js(__('Replace the placeholder with the exact shortcode Elementor outputs.', 'amelia-cpt-sync')); ?></p>
                    </div>
                </div>

                <p>
                    <button type="button" class="button button-link-delete delete-config" data-config-id="${configId}">
                        <?php echo esc_js(__('Delete This Configuration', 'amelia-cpt-sync')); ?>
                    </button>
                </p>
            </div>
        `;

        $('.no-configs-message').remove();
        const $config = $(template);
        $('#configurations-container').append($config);
        bindNewConfig($config);
    });

    // Delete configuration block
    $(document).on('click', '.delete-config', function() {
        if (!confirm('<?php echo esc_js(__('Are you sure you want to delete this configuration?', 'amelia-cpt-sync')); ?>')) {
            return;
        }

        $(this).closest('.popup-config-item').fadeOut(200, function() {
            $(this).remove();

            if ($('.popup-config-item').length === 0) {
                $('#configurations-container').html('<p class="no-configs-message"><?php echo esc_js(__('No configurations yet. Click "Add New Configuration" to get started.', 'amelia-cpt-sync')); ?></p>');
            }
        });
    });

    // Save configurations
    $('#save-popup-configs').on('click', function() {
        console.log('[Popup Manager] Save button clicked');

        const $button = $(this);
        const $spinner = $('.spinner');
        const $message = $('#save-popup-message');

        $button.prop('disabled', true);
        $spinner.addClass('is-active');
        $message.text('').removeClass('success error');

        const formData = $('#popup-config-form').serialize() + '&action=amelia_save_popup_configs&nonce=' + '<?php echo wp_create_nonce('amelia_popup_save'); ?>';

        $.post(ajaxurl, formData, function(response) {
            console.log('[Popup Manager] AJAX response:', response);

            if (response.success) {
                $message.text(response.data.message).addClass('success');

                setTimeout(function() {
                    location.reload();
                }, 800);
            } else {
                const errorMsg = response.data && response.data.message ? response.data.message : '<?php echo esc_js(__('Failed to save configurations.', 'amelia-cpt-sync')); ?>';
                console.error('[Popup Manager] Save failed:', errorMsg);
                $message.text(errorMsg).addClass('error');
            }
        }).fail(function(xhr, status, error) {
            console.error('[Popup Manager] AJAX error:', status, error);
            $message.text('<?php echo esc_js(__('Error saving configurations:', 'amelia-cpt-sync')); ?> ' + error).addClass('error');
        }).always(function() {
            $button.prop('disabled', false);
            $spinner.removeClass('is-active');
        });
    });

    // Initialize summaries for existing configs
    $('.popup-config-item').each(function() {
        bindNewConfig($(this));
    });
});
</script>

