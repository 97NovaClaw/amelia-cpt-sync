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
                        $popup_slug = isset($config['popup_slug']) ? $config['popup_slug'] : (isset($config['popup_id']) ? $config['popup_id'] : '');
                        $popup_numeric_id = isset($config['popup_numeric_id']) ? intval($config['popup_numeric_id']) : 0;
                        $notes = isset($config['notes']) ? $config['notes'] : '';
                        $prefixed_id = $popup_numeric_id ? 'jet-popup-' . $popup_numeric_id : '';
                        $subtitle_parts = array();

                        if ($popup_slug) {
                            $subtitle_parts[] = sprintf(__('Slug: %s', 'amelia-cpt-sync'), esc_html($popup_slug));
                        }

                        if ($popup_numeric_id) {
                            $subtitle_parts[] = sprintf(__('ID: %d', 'amelia-cpt-sync'), $popup_numeric_id);
                        }

                        $subtitle_text = !empty($subtitle_parts)
                            ? implode(' • ', $subtitle_parts)
                            : __('Popup not resolved yet', 'amelia-cpt-sync');
                    ?>
                        <div class="popup-config-item" data-config-id="<?php echo esc_attr($config_id); ?>">
                            <div class="popup-config-header">
                                <h3 class="config-title"><?php echo esc_html($label ? $label : __('Untitled Popup', 'amelia-cpt-sync')); ?></h3>
                                <span class="config-subtitle" data-popup-slug="<?php echo esc_attr($popup_slug); ?>" data-popup-numeric="<?php echo esc_attr($popup_numeric_id); ?>">
                                    <?php echo wp_kses_post($subtitle_text); ?>
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
                                    <th><label><?php _e('JetPopup Slug', 'amelia-cpt-sync'); ?></label></th>
                                    <td>
                                        <input type="text" name="configs[<?php echo esc_attr($config_id); ?>][popup_slug]"
                                               class="regular-text config-popup-slug-input"
                                               value="<?php echo esc_attr($popup_slug); ?>"
                                               placeholder="e.g., v2-book-by-vehicle-popup">
                                        <p class="description"><?php _e('Enter the JetPopup slug (found under JetPopup → All Popups). The system will resolve the numeric ID automatically.', 'amelia-cpt-sync'); ?></p>

                                        <div class="popup-resolve-feedback <?php echo $popup_numeric_id ? 'resolved' : 'pending'; ?>">
                                            <span class="status-dot"></span>
                                            <span class="status-text">
                                                <?php if ($popup_numeric_id): ?>
                                                    <?php echo sprintf(__('Resolved as ID %1$d (%2$s)', 'amelia-cpt-sync'), $popup_numeric_id, esc_html($prefixed_id)); ?>
                                                <?php else: ?>
                                                    <?php _e('Slug not resolved yet. Enter a valid slug and click outside the field to look it up.', 'amelia-cpt-sync'); ?>
                                                <?php endif; ?>
                                            </span>
                                        </div>

                                        <div class="popup-resolved-wrapper">
                                            <label><?php _e('Resolved Popup ID', 'amelia-cpt-sync'); ?></label>
                                            <input type="text" class="regular-text config-popup-prefixed-input" value="<?php echo esc_attr($prefixed_id); ?>" readonly>
                                        </div>

                                        <input type="hidden" name="configs[<?php echo esc_attr($config_id); ?>][popup_numeric_id]" value="<?php echo esc_attr($popup_numeric_id); ?>" class="popup-numeric-id-input">
                                    </td>
                                </tr>

                                <tr>
                                    <th><label><?php _e('Amelia Shortcode Template', 'amelia-cpt-sync'); ?></label></th>
                                    <td>
                                        <?php
                                        $shortcode_template = isset($config['shortcode_template']) ? $config['shortcode_template'] : '';
                                        ?>
                                        <input type="text" name="configs[<?php echo esc_attr($config_id); ?>][shortcode_template]"
                                               class="large-text code config-shortcode-template"
                                               value="<?php echo esc_attr($shortcode_template); ?>"
                                               placeholder="e.g., [ameliastepbooking service=*]">
                                        <p class="description"><?php _e('Enter your Amelia shortcode with <code>*</code> as the placeholder for the dynamic ID.', 'amelia-cpt-sync'); ?></p>
                                        
                                        <div class="shortcode-split-output" style="margin-top: 15px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd;">
                                            <h4 style="margin-top: 0;"><?php _e('Elementor Custom Attribute Setup', 'amelia-cpt-sync'); ?></h4>
                                            <p><?php _e('Use these 3 fields in Elementor → Advanced → Custom Attributes:', 'amelia-cpt-sync'); ?></p>
                                            
                                            <table class="widefat" style="max-width: 600px; background: #fff;">
                                                <tbody>
                                                    <tr>
                                                        <td style="width: 30%; font-weight: 600;"><?php _e('Attribute Key', 'amelia-cpt-sync'); ?></td>
                                                        <td>
                                                            <input type="text" class="code shortcode-attr-key" value="data-amelia-shortcode" readonly style="width: 100%;" onclick="this.select();">
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="font-weight: 600;"><?php _e('Before', 'amelia-cpt-sync'); ?></td>
                                                        <td>
                                                            <input type="text" class="code shortcode-before" value="" readonly style="width: 100%;" onclick="this.select();">
                                                            <button type="button" class="button button-small copy-before-btn" style="margin-top: 5px;">
                                                                <span class="dashicons dashicons-clipboard"></span> <?php _e('Copy', 'amelia-cpt-sync'); ?>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="font-weight: 600;"><?php _e('After', 'amelia-cpt-sync'); ?></td>
                                                        <td>
                                                            <input type="text" class="code shortcode-after" value="" readonly style="width: 100%;" onclick="this.select();">
                                                            <button type="button" class="button button-small copy-after-btn" style="margin-top: 5px;">
                                                                <span class="dashicons dashicons-clipboard"></span> <?php _e('Copy', 'amelia-cpt-sync'); ?>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                            <p class="description" style="margin-top: 10px;">
                                                <strong><?php _e('Instructions:', 'amelia-cpt-sync'); ?></strong><br>
                                                <?php _e('1. Copy "Before" text → paste in Elementor "Before" field', 'amelia-cpt-sync'); ?><br>
                                                <?php _e('2. Click dynamic tag icon → select JetEngine meta field for the ID', 'amelia-cpt-sync'); ?><br>
                                                <?php _e('3. Copy "After" text → paste in Elementor "After" field', 'amelia-cpt-sync'); ?>
                                            </p>
                                        </div>
                                    </td>
                                </tr>

                                <tr>
                                    <th><label><?php _e('Team Notes (Optional)', 'amelia-cpt-sync'); ?></label></th>
                                    <td>
                                        <textarea name="configs[<?php echo esc_attr($config_id); ?>][notes]" rows="3" class="large-text" placeholder="<?php esc_attr_e('Store additional setup reminders.', 'amelia-cpt-sync'); ?>"><?php echo esc_textarea($notes); ?></textarea>
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

.popup-resolve-feedback {
    margin-top: 8px;
    padding: 6px 10px;
    border-radius: 3px;
    background: #f6f7f7;
    border: 1px solid #dcdcde;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
}

.popup-resolve-feedback .status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
    background: #d63638;
}

.popup-resolve-feedback .status-text {
    line-height: 1.4;
}

.popup-resolve-feedback.resolved {
    border-color: #46b450;
    background: #f2fbf4;
}

.popup-resolve-feedback.resolved .status-dot {
    background: #46b450;
}

.popup-resolve-feedback.loading {
    border-color: #f0ad4e;
    background: #fff6e5;
}

.popup-resolve-feedback.loading .status-dot {
    background: #f0ad4e;
}

.popup-resolve-feedback.error {
    border-color: #d63638;
    background: #fef1f2;
    color: #8a1f1f;
}

.popup-resolve-feedback.error .status-dot {
    background: #d63638;
}

.popup-resolved-wrapper {
    margin-top: 10px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.popup-resolved-wrapper label {
    font-weight: 600;
    min-width: 150px;
}

.popup-resolved-wrapper input {
    max-width: 260px;
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
    const popupNotSet = '<?php echo esc_js(__('Popup not resolved yet', 'amelia-cpt-sync')); ?>';
    const popupResolveNonce = '<?php echo wp_create_nonce('amelia_popup_resolve'); ?>';
    const subtitleSlugLabel = '<?php echo esc_js(__('Slug', 'amelia-cpt-sync')); ?>';
    const subtitleIdLabel = '<?php echo esc_js(__('ID', 'amelia-cpt-sync')); ?>';

    console.log('[Popup Manager] Page loaded');

    function updateConfigSummary($configItem) {
        const label = $.trim($configItem.find('.config-label-input').val());
        const popupSlug = $.trim($configItem.find('.config-popup-slug-input').val());
        const popupNumeric = $.trim($configItem.find('.popup-numeric-id-input').val());

        $configItem.find('.config-title').text(label || untitledLabel);

        const parts = [];
        if (popupSlug) parts.push(subtitleSlugLabel + ': ' + popupSlug);
        if (popupNumeric) parts.push(subtitleIdLabel + ': ' + popupNumeric);

        $configItem.find('.config-subtitle').text(parts.length ? parts.join(' • ') : popupNotSet);
    }

    function setResolveState($configItem, state, payload) {
        const $feedback = $configItem.find('.popup-resolve-feedback');
        const $statusText = $feedback.find('.status-text');
        const $numericInput = $configItem.find('.popup-numeric-id-input');
        const $prefixedInput = $configItem.find('.config-popup-prefixed-input');

        $feedback.removeClass('resolved error loading');

        if (state === 'loading') {
            $feedback.addClass('loading');
            $statusText.text('<?php echo esc_js(__('Resolving slug…', 'amelia-cpt-sync')); ?>');
        } else if (state === 'resolved' && payload && payload.numeric_id) {
            const numeric = parseInt(payload.numeric_id, 10);
            const prefixed = payload.prefixed_id || ('jet-popup-' + numeric);
            $feedback.addClass('resolved');
            $statusText.text('<?php echo esc_js(__('Resolved as ID ', 'amelia-cpt-sync')); ?>' + numeric + ' (' + prefixed + ')');
            $numericInput.val(numeric);
            $prefixedInput.val(prefixed);
        } else if (state === 'error') {
            $feedback.addClass('error');
            $statusText.text('<?php echo esc_js(__('Popup not found. Double-check the slug.', 'amelia-cpt-sync')); ?>');
            $numericInput.val('');
            $prefixedInput.val('');
        } else {
            $statusText.text('<?php echo esc_js(__('Enter a valid slug and click outside the field.', 'amelia-cpt-sync')); ?>');
        }

        updateConfigSummary($configItem);
    }

    function resolvePopupSlug($configItem) {
        const slug = $.trim($configItem.find('.config-popup-slug-input').val());
        if (!slug) {
            setResolveState($configItem, 'pending');
            return;
        }

        setResolveState($configItem, 'loading');

        $.post(ajaxurl, {
            action: 'amelia_resolve_popup_slug',
            nonce: popupResolveNonce,
            slug: slug
        }).done(function(response) {
            setResolveState($configItem, response.success && response.data ? 'resolved' : 'error', response.data);
        }).fail(function() {
            setResolveState($configItem, 'error');
        });
    }

    function bindNewConfig($configItem) {
        updateConfigSummary($configItem);
        updateShortcodeSplit($configItem);
        
        const numeric = $.trim($configItem.find('.popup-numeric-id-input').val());
        const slug = $.trim($configItem.find('.config-popup-slug-input').val());

        if (numeric) {
            setResolveState($configItem, 'resolved', { numeric_id: numeric, prefixed_id: 'jet-popup-' + numeric });
        } else if (slug) {
            resolvePopupSlug($configItem);
        } else {
            setResolveState($configItem, 'pending');
        }
    }

    $('.instructions-toggle').on('click', function() {
        $('.instructions-content').slideToggle();
        $(this).find('.dashicons-arrow-down-alt2, .dashicons-arrow-up-alt2').toggleClass('dashicons-arrow-down-alt2 dashicons-arrow-up-alt2');
    });

    $(document).on('input', '.config-label-input', function() {
        updateConfigSummary($(this).closest('.popup-config-item'));
    });

    $(document).on('input', '.config-popup-slug-input', function() {
        setResolveState($(this).closest('.popup-config-item'), 'pending');
    });

    $(document).on('blur', '.config-popup-slug-input', function() {
        resolvePopupSlug($(this).closest('.popup-config-item'));
    });

    function splitShortcodeTemplate(template) {
        if (!template || template.indexOf('*') === -1) {
            return { before: '', after: '' };
        }

        const parts = template.split('*');
        return {
            before: '[[' + parts[0],
            after: parts.slice(1).join('*') + ']]'
        };
    }

    function updateShortcodeSplit($configItem) {
        const template = $.trim($configItem.find('.config-shortcode-template').val());
        const split = splitShortcodeTemplate(template);

        $configItem.find('.shortcode-before').val(split.before);
        $configItem.find('.shortcode-after').val(split.after);
    }

    $(document).on('input', '.config-shortcode-template', function() {
        updateShortcodeSplit($(this).closest('.popup-config-item'));
    });

    $(document).on('click', '.copy-before-btn, .copy-after-btn', function() {
        const $button = $(this);
        const $input = $button.hasClass('copy-before-btn') 
            ? $button.closest('td').find('.shortcode-before')
            : $button.closest('td').find('.shortcode-after');

        $input.trigger('select');
        try {
            document.execCommand('copy');
            $button.html('<span class="dashicons dashicons-yes"></span> <?php echo esc_js(__('Copied!', 'amelia-cpt-sync')); ?>');
        } catch (e) {}
        setTimeout(function() {
            $button.html('<span class="dashicons dashicons-clipboard"></span> <?php echo esc_js(__('Copy', 'amelia-cpt-sync')); ?>');
        }, 1500);
    });

    $(document).on('click', '.copy-attribute-template', function() {
        const $button = $(this);
        const $input = $button.closest('.attribute-template__field').find('.attribute-template-input');
        $input.trigger('select');
        try {
            document.execCommand('copy');
            $button.html('<span class="dashicons dashicons-yes"></span> <?php echo esc_js(__('Copied', 'amelia-cpt-sync')); ?>');
        } catch (e) {}
        setTimeout(function() {
            $button.html('<span class="dashicons dashicons-clipboard"></span> <?php echo esc_js(__('Copy', 'amelia-cpt-sync')); ?>');
        }, 1500);
    });

    $('.copy-html-snippet').on('click', function() {
        const $code = $(this).prev('.code-snippet').find('code');
        const $temp = $('<textarea>');
        $('body').append($temp);
        $temp.val($code.text()).select();
        document.execCommand('copy');
        $temp.remove();
        $(this).text('✓ <?php echo esc_js(__('Copied!', 'amelia-cpt-sync')); ?>');
        setTimeout(() => $('.copy-html-snippet').text('<?php echo esc_js(__('Copy HTML', 'amelia-cpt-sync')); ?>'), 2000);
    });

    $('#add-new-config').on('click', function() {
        const configId = 'new_config_' + Date.now();
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
                            <p class="description"><?php echo esc_js(__('Internal reference.', 'amelia-cpt-sync')); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php echo esc_js(__('JetPopup Slug', 'amelia-cpt-sync')); ?></label></th>
                        <td>
                            <input type="text" name="configs[${configId}][popup_slug]" class="regular-text config-popup-slug-input" placeholder="<?php echo esc_js(__('e.g., v2-book-by-vehicle-popup', 'amelia-cpt-sync')); ?>">
                            <p class="description"><?php echo esc_js(__('Enter JetPopup slug. Numeric ID resolves automatically.', 'amelia-cpt-sync')); ?></p>
                            <div class="popup-resolve-feedback pending">
                                <span class="status-dot"></span>
                                <span class="status-text"><?php echo esc_js(__('Enter slug and click outside.', 'amelia-cpt-sync')); ?></span>
                            </div>
                            <div class="popup-resolved-wrapper">
                                <label><?php echo esc_js(__('Resolved ID', 'amelia-cpt-sync')); ?></label>
                                <input type="text" class="regular-text config-popup-prefixed-input" readonly>
                            </div>
                            <input type="hidden" name="configs[${configId}][popup_numeric_id]" class="popup-numeric-id-input">
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php echo esc_js(__('Shortcode Template', 'amelia-cpt-sync')); ?></label></th>
                        <td>
                            <input type="text" name="configs[${configId}][shortcode_template]" class="large-text code config-shortcode-template" placeholder="<?php echo esc_js(__('[ameliastepbooking service=*]', 'amelia-cpt-sync')); ?>">
                            <p class="description"><?php echo esc_js(__('Use * as placeholder for dynamic ID.', 'amelia-cpt-sync')); ?></p>
                            <div class="shortcode-split-output" style="margin-top: 15px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd;">
                                <h4 style="margin-top: 0;"><?php echo esc_js(__('Generated Fields', 'amelia-cpt-sync')); ?></h4>
                                <table class="widefat" style="max-width: 600px; background: #fff;">
                                    <tbody>
                                        <tr>
                                            <td style="width: 30%; font-weight: 600;"><?php echo esc_js(__('Key', 'amelia-cpt-sync')); ?></td>
                                            <td><input type="text" class="code shortcode-attr-key" value="data-amelia-shortcode" readonly style="width: 100%;" onclick="this.select();"></td>
                                        </tr>
                                        <tr>
                                            <td style="font-weight: 600;"><?php echo esc_js(__('Before', 'amelia-cpt-sync')); ?></td>
                                            <td>
                                                <input type="text" class="code shortcode-before" readonly style="width: 100%;" onclick="this.select();">
                                                <button type="button" class="button button-small copy-before-btn" style="margin-top: 5px;">
                                                    <span class="dashicons dashicons-clipboard"></span> <?php echo esc_js(__('Copy', 'amelia-cpt-sync')); ?>
                                                </button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="font-weight: 600;"><?php echo esc_js(__('After', 'amelia-cpt-sync')); ?></td>
                                            <td>
                                                <input type="text" class="code shortcode-after" readonly style="width: 100%;" onclick="this.select();">
                                                <button type="button" class="button button-small copy-after-btn" style="margin-top: 5px;">
                                                    <span class="dashicons dashicons-clipboard"></span> <?php echo esc_js(__('Copy', 'amelia-cpt-sync')); ?>
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php echo esc_js(__('Team Notes', 'amelia-cpt-sync')); ?></label></th>
                        <td><textarea name="configs[${configId}][notes]" rows="3" class="large-text"></textarea></td>
                    </tr>
                </table>
                <div class="elementor-instructions-box">
                    <h4><?php echo esc_js(__('Elementor Setup', 'amelia-cpt-sync')); ?></h4>
                    <ol>
                        <li><?php echo esc_js(__('Set JetPopup action', 'amelia-cpt-sync')); ?></li>
                        <li><?php echo esc_js(__('Add class', 'amelia-cpt-sync')); ?> <code>amelia-booking-trigger</code></li>
                        <li><?php echo esc_js(__('Add custom attribute with Key, Before (+ dynamic tag), After from above', 'amelia-cpt-sync')); ?></li>
                    </ol>
                </div>
                <p><button type="button" class="button button-link-delete delete-config"><?php echo esc_js(__('Delete', 'amelia-cpt-sync')); ?></button></p>
            </div>
        `;
        $('.no-configs-message').remove();
        const $config = $(template);
        $('#configurations-container').append($config);
        bindNewConfig($config);
    });

    $(document).on('click', '.delete-config', function() {
        if (!confirm('<?php echo esc_js(__('Delete this configuration?', 'amelia-cpt-sync')); ?>')) return;
        $(this).closest('.popup-config-item').fadeOut(200, function() {
            $(this).remove();
            if (!$('.popup-config-item').length) {
                $('#configurations-container').html('<p class="no-configs-message"><?php echo esc_js(__('No configurations. Click "Add New".', 'amelia-cpt-sync')); ?></p>');
            }
        });
    });

    $('#save-popup-configs').on('click', function() {
        const $button = $(this), $spinner = $('.spinner'), $message = $('#save-popup-message');
        $button.prop('disabled', true);
        $spinner.addClass('is-active');
        $message.text('').removeClass('success error');

        $.post(ajaxurl, $('#popup-config-form').serialize() + '&action=amelia_save_popup_configs&nonce=<?php echo wp_create_nonce('amelia_popup_save'); ?>', function(response) {
            if (response.success) {
                $message.text(response.data.message).addClass('success');
                setTimeout(() => location.reload(), 800);
            } else {
                $message.text(response.data?.message || '<?php echo esc_js(__('Save failed.', 'amelia-cpt-sync')); ?>').addClass('error');
            }
        }).fail((xhr, status, error) => {
            $message.text('<?php echo esc_js(__('Error:', 'amelia-cpt-sync')); ?> ' + error).addClass('error');
        }).always(() => {
            $button.prop('disabled', false);
            $spinner.removeClass('is-active');
        });
    });

    $('.popup-config-item').each(function() { bindNewConfig($(this)); });
});
</script>

