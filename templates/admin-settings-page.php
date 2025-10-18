<?php
/**
 * Admin Settings Page Template
 *
 * @package AmeliaCPTSync
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}
?>

<div class="wrap amelia-cpt-sync-settings">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="notice notice-info">
        <p><strong><?php _e('Important:', 'amelia-cpt-sync'); ?></strong> <?php _e('This plugin provides real-time, one-way synchronization from Amelia to your selected Custom Post Type. Changes made in the CPT will not affect Amelia.', 'amelia-cpt-sync'); ?></p>
    </div>
    
    <!-- Tab Navigation -->
    <h2 class="nav-tab-wrapper">
        <a href="#setup" class="nav-tab nav-tab-active" data-tab="setup"><?php _e('Setup', 'amelia-cpt-sync'); ?></a>
        <a href="#field-mapping" class="nav-tab" data-tab="field-mapping"><?php _e('Field Mapping', 'amelia-cpt-sync'); ?></a>
    </h2>
    
    <!-- Tab Content -->
    <form id="amelia-cpt-sync-form" method="post">
        <?php wp_nonce_field('amelia_cpt_sync_settings', 'amelia_cpt_sync_nonce'); ?>
        
        <!-- Setup Tab -->
        <div id="tab-setup" class="tab-content active">
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="cpt_slug"><?php _e('Post Type Selection', 'amelia-cpt-sync'); ?></label>
                        </th>
                        <td>
                            <select name="cpt_slug" id="cpt_slug" class="regular-text">
                                <option value=""><?php _e('-- Select a Custom Post Type --', 'amelia-cpt-sync'); ?></option>
                                <?php foreach ($cpts as $cpt): ?>
                                    <option value="<?php echo esc_attr($cpt->name); ?>" <?php selected($settings['cpt_slug'], $cpt->name); ?>>
                                        <?php echo esc_html($cpt->label); ?> (<?php echo esc_html($cpt->name); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php _e('Select the destination Custom Post Type where Amelia services will be synced.', 'amelia-cpt-sync'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="taxonomy_slug"><?php _e('Taxonomy Selection', 'amelia-cpt-sync'); ?></label>
                        </th>
                        <td>
                            <select name="taxonomy_slug" id="taxonomy_slug" class="regular-text">
                                <option value=""><?php _e('-- Select a Taxonomy --', 'amelia-cpt-sync'); ?></option>
                                <?php if (!empty($taxonomies)): ?>
                                    <?php foreach ($taxonomies as $taxonomy): ?>
                                        <option value="<?php echo esc_attr($taxonomy->name); ?>" <?php selected($settings['taxonomy_slug'], $taxonomy->name); ?>>
                                            <?php echo esc_html($taxonomy->label); ?> (<?php echo esc_html($taxonomy->name); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <p class="description"><?php _e('Select the taxonomy where Amelia service categories will be synced. This field will populate after you select a post type.', 'amelia-cpt-sync'); ?></p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- Field Mapping Tab -->
        <div id="tab-field-mapping" class="tab-content">
            <p class="description" style="margin-bottom: 20px;">
                <?php _e('Map Amelia service data to your Custom Post Type fields. Enter the meta field slugs from your JetEngine CPT setup.', 'amelia-cpt-sync'); ?>
            </p>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 20%;"><?php _e('Amelia Data Field', 'amelia-cpt-sync'); ?></th>
                        <th style="width: 25%;"><?php _e('Description', 'amelia-cpt-sync'); ?></th>
                        <th style="width: 25%;"><?php _e('Mapped To (Your CPT Field)', 'amelia-cpt-sync'); ?></th>
                        <th style="width: 30%;"><?php _e('Recommended JetEngine Setup', 'amelia-cpt-sync'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong><?php _e('Service Name', 'amelia-cpt-sync'); ?></strong></td>
                        <td><?php _e('The name/title of the service', 'amelia-cpt-sync'); ?></td>
                        <td>
                            <span class="dashicons dashicons-lock"></span>
                            <em><?php _e('Post Title (Locked)', 'amelia-cpt-sync'); ?></em>
                        </td>
                        <td><?php _e('No setup needed - uses WordPress post title', 'amelia-cpt-sync'); ?></td>
                    </tr>
                    
                    <tr>
                        <td><strong><?php _e('Description', 'amelia-cpt-sync'); ?></strong></td>
                        <td><?php _e('The full service description', 'amelia-cpt-sync'); ?></td>
                        <td>
                            <span class="dashicons dashicons-lock"></span>
                            <em><?php _e('Post Content (Locked)', 'amelia-cpt-sync'); ?></em>
                        </td>
                        <td><?php _e('No setup needed - uses WordPress post content', 'amelia-cpt-sync'); ?></td>
                    </tr>
                    
                    <tr>
                        <td><strong><?php _e('Primary Photo', 'amelia-cpt-sync'); ?></strong></td>
                        <td><?php _e('The main service image', 'amelia-cpt-sync'); ?></td>
                        <td>
                            <span class="dashicons dashicons-lock"></span>
                            <em><?php _e('Featured Image (Locked)', 'amelia-cpt-sync'); ?></em>
                        </td>
                        <td><?php _e('No setup needed - uses WordPress featured image', 'amelia-cpt-sync'); ?></td>
                    </tr>
                    
                    <tr>
                        <td><strong><?php _e('Price', 'amelia-cpt-sync'); ?></strong></td>
                        <td><?php _e('The service price', 'amelia-cpt-sync'); ?></td>
                        <td>
                            <input type="text" name="price_field" id="price_field" class="regular-text" 
                                   value="<?php echo esc_attr($settings['field_mappings']['price']); ?>" 
                                   placeholder="e.g., service_price">
                        </td>
                        <td><?php _e('Type: Number', 'amelia-cpt-sync'); ?></td>
                    </tr>
                    
                    <tr>
                        <td><strong><?php _e('Duration', 'amelia-cpt-sync'); ?></strong></td>
                        <td><?php _e('The service duration', 'amelia-cpt-sync'); ?></td>
                        <td>
                            <input type="text" name="duration_field" id="duration_field" class="regular-text" 
                                   value="<?php echo esc_attr($settings['field_mappings']['duration']); ?>" 
                                   placeholder="e.g., service_duration">
                            <br><br>
                            <label><?php _e('Save Duration As:', 'amelia-cpt-sync'); ?></label><br>
                            <select name="duration_format" id="duration_format" class="regular-text">
                                <option value="seconds" <?php selected($settings['field_mappings']['duration_format'], 'seconds'); ?>>
                                    <?php _e('Raw Seconds (e.g., 5400)', 'amelia-cpt-sync'); ?>
                                </option>
                                <option value="minutes" <?php selected($settings['field_mappings']['duration_format'], 'minutes'); ?>>
                                    <?php _e('Total Minutes (e.g., 90)', 'amelia-cpt-sync'); ?>
                                </option>
                                <option value="hh_mm" <?php selected($settings['field_mappings']['duration_format'], 'hh_mm'); ?>>
                                    <?php _e('HH:MM Format (e.g., 01:30)', 'amelia-cpt-sync'); ?>
                                </option>
                                <option value="readable" <?php selected($settings['field_mappings']['duration_format'], 'readable'); ?>>
                                    <?php _e('Readable Text (e.g., "1 hour 30 minutes")', 'amelia-cpt-sync'); ?>
                                </option>
                            </select>
                        </td>
                        <td>
                            <?php _e('Type: Text (for HH:MM or readable) or Number (for seconds/minutes)', 'amelia-cpt-sync'); ?>
                        </td>
                    </tr>
                    
                    <tr>
                        <td><strong><?php _e('Gallery', 'amelia-cpt-sync'); ?></strong></td>
                        <td><?php _e('Additional service images', 'amelia-cpt-sync'); ?></td>
                        <td>
                            <input type="text" name="gallery_field" id="gallery_field" class="regular-text" 
                                   value="<?php echo esc_attr($settings['field_mappings']['gallery']); ?>" 
                                   placeholder="e.g., service_gallery">
                        </td>
                        <td><?php _e('Type: Gallery (stores array of attachment IDs)', 'amelia-cpt-sync'); ?></td>
                    </tr>
                    
                    <tr>
                        <td><strong><?php _e('Extras', 'amelia-cpt-sync'); ?></strong></td>
                        <td><?php _e('Service extras/add-ons', 'amelia-cpt-sync'); ?></td>
                        <td>
                            <input type="text" name="extras_field" id="extras_field" class="regular-text" 
                                   value="<?php echo esc_attr($settings['field_mappings']['extras']); ?>" 
                                   placeholder="e.g., service_extras">
                        </td>
                        <td><?php _e('Type: Repeater (stores array of extra objects)', 'amelia-cpt-sync'); ?></td>
                    </tr>
                    
                    <tr>
                        <td><strong><?php _e('Category', 'amelia-cpt-sync'); ?></strong></td>
                        <td><?php _e('The service category', 'amelia-cpt-sync'); ?></td>
                        <td>
                            <span class="dashicons dashicons-lock"></span>
                            <em><?php _e('Selected Taxonomy (Locked)', 'amelia-cpt-sync'); ?></em>
                        </td>
                        <td><?php _e('Uses the taxonomy selected in Setup tab', 'amelia-cpt-sync'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <p class="submit">
            <button type="button" id="save-settings" class="button button-primary">
                <?php _e('Save Settings', 'amelia-cpt-sync'); ?>
            </button>
            <span class="spinner" style="float: none; margin: 0 10px;"></span>
            <span id="save-message"></span>
        </p>
    </form>
</div>

<style>
.amelia-cpt-sync-settings .nav-tab-wrapper {
    margin-bottom: 0;
}

.amelia-cpt-sync-settings .tab-content {
    display: none;
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    border-top: 0;
}

.amelia-cpt-sync-settings .tab-content.active {
    display: block;
}

.amelia-cpt-sync-settings .dashicons-lock {
    color: #999;
    vertical-align: middle;
}

.amelia-cpt-sync-settings .wp-list-table td {
    vertical-align: middle;
}

#save-message.success {
    color: #46b450;
    font-weight: 600;
}

#save-message.error {
    color: #dc3232;
    font-weight: 600;
}
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Tab switching
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        
        var tab = $(this).data('tab');
        
        // Update active tab
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        // Show corresponding content
        $('.tab-content').removeClass('active');
        $('#tab-' + tab).addClass('active');
        
        // Update URL hash
        window.location.hash = tab;
    });
    
    // Load tab from hash on page load
    if (window.location.hash) {
        var hash = window.location.hash.substring(1);
        $('.nav-tab[data-tab="' + hash + '"]').trigger('click');
    }
    
    // Load taxonomies when CPT changes
    $('#cpt_slug').on('change', function() {
        var cptSlug = $(this).val();
        var $taxonomySelect = $('#taxonomy_slug');
        
        if (!cptSlug) {
            $taxonomySelect.html('<option value="">-- Select a Taxonomy --</option>');
            return;
        }
        
        // Show loading
        $taxonomySelect.prop('disabled', true);
        $taxonomySelect.html('<option value="">Loading...</option>');
        
        // Fetch taxonomies
        $.ajax({
            url: ameliaCptSync.ajax_url,
            type: 'POST',
            data: {
                action: 'amelia_cpt_sync_get_taxonomies',
                nonce: ameliaCptSync.nonce,
                cpt_slug: cptSlug
            },
            success: function(response) {
                if (response.success) {
                    var options = '<option value="">-- Select a Taxonomy --</option>';
                    
                    $.each(response.data.taxonomies, function(index, taxonomy) {
                        options += '<option value="' + taxonomy.slug + '">' + taxonomy.label + ' (' + taxonomy.slug + ')</option>';
                    });
                    
                    $taxonomySelect.html(options);
                } else {
                    $taxonomySelect.html('<option value="">Error loading taxonomies</option>');
                }
            },
            error: function() {
                $taxonomySelect.html('<option value="">Error loading taxonomies</option>');
            },
            complete: function() {
                $taxonomySelect.prop('disabled', false);
            }
        });
    });
    
    // Save settings via AJAX
    $('#save-settings').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var $spinner = $('.spinner');
        var $message = $('#save-message');
        
        // Show loading
        $button.prop('disabled', true);
        $spinner.addClass('is-active');
        $message.text('').removeClass('success error');
        
        // Gather form data
        var formData = {
            action: 'amelia_cpt_sync_save_settings',
            nonce: ameliaCptSync.nonce,
            cpt_slug: $('#cpt_slug').val(),
            taxonomy_slug: $('#taxonomy_slug').val(),
            price_field: $('#price_field').val(),
            duration_field: $('#duration_field').val(),
            duration_format: $('#duration_format').val(),
            gallery_field: $('#gallery_field').val(),
            extras_field: $('#extras_field').val()
        };
        
        // Save settings
        $.ajax({
            url: ameliaCptSync.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    $message.text(response.data.message).addClass('success');
                } else {
                    $message.text(response.data.message || 'Failed to save settings').addClass('error');
                }
            },
            error: function() {
                $message.text('Error saving settings').addClass('error');
            },
            complete: function() {
                $button.prop('disabled', false);
                $spinner.removeClass('is-active');
                
                // Clear message after 5 seconds
                setTimeout(function() {
                    $message.fadeOut(function() {
                        $(this).text('').removeClass('success error').show();
                    });
                }, 5000);
            }
        });
    });
});
</script>

