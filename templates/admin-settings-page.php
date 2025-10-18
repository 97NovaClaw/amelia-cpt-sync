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
        <a href="#full-sync" class="nav-tab" data-tab="full-sync"><?php _e('Full Sync', 'amelia-cpt-sync'); ?></a>
        <a href="#debug" class="nav-tab" data-tab="debug"><?php _e('Debug', 'amelia-cpt-sync'); ?></a>
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
                    
                    <tr>
                        <th scope="row">
                            <label for="debug_enabled"><?php _e('Debug Logging', 'amelia-cpt-sync'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="debug_enabled" id="debug_enabled" value="1" <?php checked($settings['debug_enabled'], true); ?>>
                                <?php _e('Enable debug logging', 'amelia-cpt-sync'); ?>
                            </label>
                            <p class="description"><?php _e('When enabled, all sync operations and errors will be logged to a plugin-specific debug file. View logs in the Debug tab.', 'amelia-cpt-sync'); ?></p>
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
            
            <h3><?php _e('Service Mappings (Post Meta Fields)', 'amelia-cpt-sync'); ?></h3>
            <p class="description" style="margin-bottom: 15px;">
                <?php _e('These fields will be stored as meta data on each service post.', 'amelia-cpt-sync'); ?>
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
                        <td><strong><?php _e('Service ID', 'amelia-cpt-sync'); ?></strong></td>
                        <td><?php _e('The unique Amelia service ID', 'amelia-cpt-sync'); ?></td>
                        <td>
                            <input type="text" name="service_id_field" id="service_id_field" class="regular-text" 
                                   value="<?php echo esc_attr($settings['field_mappings']['service_id']); ?>" 
                                   placeholder="e.g., amelia_service_id">
                        </td>
                        <td><?php _e('Type: Number (useful for shortcodes and queries)', 'amelia-cpt-sync'); ?></td>
                    </tr>
                    
                    <tr>
                        <td><strong><?php _e('Category ID', 'amelia-cpt-sync'); ?></strong></td>
                        <td><?php _e('The Amelia category ID', 'amelia-cpt-sync'); ?></td>
                        <td>
                            <input type="text" name="category_id_field" id="category_id_field" class="regular-text" 
                                   value="<?php echo esc_attr($settings['field_mappings']['category_id']); ?>" 
                                   placeholder="e.g., amelia_category_id">
                        </td>
                        <td><?php _e('Type: Number (useful for filtering and queries)', 'amelia-cpt-sync'); ?></td>
                    </tr>
                    
                    <tr>
                        <td><strong><?php _e('Primary Photo', 'amelia-cpt-sync'); ?></strong></td>
                        <td><?php _e('The main service image', 'amelia-cpt-sync'); ?></td>
                        <td>
                            <input type="text" name="primary_photo_field" id="primary_photo_field" class="regular-text" 
                                   value="<?php echo esc_attr($settings['field_mappings']['primary_photo']); ?>" 
                                   placeholder="e.g., service_image">
                        </td>
                        <td><?php _e('Type: Media (stores attachment ID)', 'amelia-cpt-sync'); ?></td>
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
            
            <hr style="margin: 40px 0;">
            
            <h3><?php _e('Taxonomy Mappings (Term Meta Fields)', 'amelia-cpt-sync'); ?></h3>
            <p class="description" style="margin-bottom: 15px;">
                <?php _e('These fields will be stored as meta data on taxonomy terms themselves. This allows you to query taxonomy terms by Amelia data.', 'amelia-cpt-sync'); ?>
            </p>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 20%;"><?php _e('Amelia Data Field', 'amelia-cpt-sync'); ?></th>
                        <th style="width: 25%;"><?php _e('Description', 'amelia-cpt-sync'); ?></th>
                        <th style="width: 25%;"><?php _e('Mapped To (Your Term Meta Field)', 'amelia-cpt-sync'); ?></th>
                        <th style="width: 30%;"><?php _e('Recommended JetEngine Setup', 'amelia-cpt-sync'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong><?php _e('Category ID', 'amelia-cpt-sync'); ?></strong></td>
                        <td><?php _e('The unique Amelia category ID', 'amelia-cpt-sync'); ?></td>
                        <td>
                            <input type="text" name="taxonomy_category_id_field" id="taxonomy_category_id_field" class="regular-text" 
                                   value="<?php echo esc_attr($settings['taxonomy_meta']['category_id']); ?>" 
                                   placeholder="e.g., amelia_category_id">
                        </td>
                        <td><?php _e('Type: Number (stored on taxonomy term, allows term queries by Amelia category)', 'amelia-cpt-sync'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- Full Sync Tab -->
        <div id="tab-full-sync" class="tab-content">
            <h3><?php _e('Manual Full Sync', 'amelia-cpt-sync'); ?></h3>
            
            <div class="notice notice-warning">
                <p><strong><?php _e('Warning:', 'amelia-cpt-sync'); ?></strong> <?php _e('This will sync ALL services from Amelia to your CPT. This may take some time depending on how many services you have.', 'amelia-cpt-sync'); ?></p>
            </div>
            
            <p><?php _e('Use this feature to perform a one-time sync of all existing Amelia services. The sync will:', 'amelia-cpt-sync'); ?></p>
            
            <ul style="list-style: disc; margin-left: 30px; margin-bottom: 20px;">
                <li><?php _e('Fetch all services from Amelia database', 'amelia-cpt-sync'); ?></li>
                <li><?php _e('Compare with existing CPT posts', 'amelia-cpt-sync'); ?></li>
                <li><?php _e('Create new posts for services that don\'t exist', 'amelia-cpt-sync'); ?></li>
                <li><?php _e('Update existing posts with latest Amelia data', 'amelia-cpt-sync'); ?></li>
                <li><?php _e('Sync all meta fields, images, and categories', 'amelia-cpt-sync'); ?></li>
            </ul>
            
            <p><strong><?php _e('Note:', 'amelia-cpt-sync'); ?></strong> <?php _e('Make sure you have saved your settings in the Setup and Field Mapping tabs before running a full sync.', 'amelia-cpt-sync'); ?></p>
            
            <div style="margin: 30px 0;">
                <button type="button" id="run-full-sync" class="button button-primary button-hero">
                    <span class="dashicons dashicons-update-alt" style="vertical-align: middle; margin-right: 5px;"></span>
                    <?php _e('Run Full Sync Now', 'amelia-cpt-sync'); ?>
                </button>
                <span class="spinner" id="sync-spinner" style="float: none; margin: 0 15px;"></span>
            </div>
            
            <div id="sync-results" style="display: none; margin-top: 30px;">
                <h3><?php _e('Sync Results', 'amelia-cpt-sync'); ?></h3>
                <div id="sync-results-content" class="sync-results-box"></div>
            </div>
        </div>
        
        <!-- Debug Tab -->
        <div id="tab-debug" class="tab-content">
            <?php 
            $logger = new Amelia_CPT_Sync_Debug_Logger();
            $is_enabled = $logger->is_enabled();
            $log_size = $logger->format_file_size($logger->get_log_size());
            ?>
            
            <h3><?php _e('Debug Logging', 'amelia-cpt-sync'); ?></h3>
            
            <div class="notice notice-info">
                <p><strong><?php _e('Status:', 'amelia-cpt-sync'); ?></strong> 
                    <?php if ($is_enabled): ?>
                        <span style="color: #46b450;"><?php _e('Debug logging is ENABLED', 'amelia-cpt-sync'); ?></span>
                    <?php else: ?>
                        <span style="color: #999;"><?php _e('Debug logging is DISABLED', 'amelia-cpt-sync'); ?></span>
                    <?php endif; ?>
                </p>
                <p><?php _e('Enable debug logging in the Setup tab to track all sync operations, errors, and system events.', 'amelia-cpt-sync'); ?></p>
            </div>
            
            <?php if ($is_enabled): ?>
                <div style="margin: 20px 0;">
                    <p><strong><?php _e('Log File Size:', 'amelia-cpt-sync'); ?></strong> <?php echo esc_html($log_size); ?></p>
                    <p class="description"><?php _e('Log files are automatically rotated when they exceed 5MB. The last 5 backup files are kept.', 'amelia-cpt-sync'); ?></p>
                </div>
                
                <div style="margin: 20px 0;">
                    <button type="button" id="view-log" class="button button-secondary">
                        <span class="dashicons dashicons-visibility" style="vertical-align: middle; margin-right: 5px;"></span>
                        <?php _e('View Log (Last 200 Lines)', 'amelia-cpt-sync'); ?>
                    </button>
                    
                    <button type="button" id="clear-log" class="button button-secondary">
                        <span class="dashicons dashicons-trash" style="vertical-align: middle; margin-right: 5px;"></span>
                        <?php _e('Clear Log', 'amelia-cpt-sync'); ?>
                    </button>
                    
                    <span class="spinner" id="log-spinner" style="float: none; margin: 0 15px;"></span>
                    <span id="log-message"></span>
                </div>
                
                <div id="log-viewer" style="display: none; margin-top: 20px;">
                    <h4><?php _e('Debug Log Contents', 'amelia-cpt-sync'); ?></h4>
                    <pre id="log-contents" style="background: #1e1e1e; color: #d4d4d4; padding: 20px; border-radius: 4px; max-height: 600px; overflow: auto; font-family: 'Courier New', monospace; font-size: 13px; line-height: 1.5;"></pre>
                </div>
            <?php else: ?>
                <div class="notice notice-warning" style="margin-top: 20px;">
                    <p><?php _e('Debug logging is currently disabled. Enable it in the Setup tab to start logging.', 'amelia-cpt-sync'); ?></p>
                </div>
            <?php endif; ?>
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

#save-message.success,
#log-message.success {
    color: #46b450;
    font-weight: 600;
}

#save-message.error,
#log-message.error {
    color: #dc3232;
    font-weight: 600;
}

.sync-results-box {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-left: 4px solid #46b450;
    padding: 20px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.sync-results-box.has-errors {
    border-left-color: #dc3232;
}

.sync-stat {
    display: inline-block;
    margin-right: 30px;
    margin-bottom: 10px;
}

.sync-stat-number {
    font-size: 32px;
    font-weight: 600;
    color: #2271b1;
    display: block;
}

.sync-stat-label {
    font-size: 14px;
    color: #646970;
}

.sync-errors-list {
    margin-top: 20px;
    padding: 15px;
    background: #fcf0f0;
    border-left: 4px solid #dc3232;
}

.sync-errors-list h4 {
    margin-top: 0;
    color: #dc3232;
}

.sync-errors-list ul {
    list-style: disc;
    margin-left: 20px;
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
    
    // Run full sync
    $('#run-full-sync').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var $spinner = $('#sync-spinner');
        var $results = $('#sync-results');
        var $resultsContent = $('#sync-results-content');
        
        // Confirm before running
        if (!confirm('<?php _e('Are you sure you want to run a full sync? This will process all Amelia services.', 'amelia-cpt-sync'); ?>')) {
            return;
        }
        
        // Show loading
        $button.prop('disabled', true);
        $spinner.addClass('is-active');
        $results.hide();
        $resultsContent.html('').removeClass('has-errors');
        
        // Run full sync
        $.ajax({
            url: ameliaCptSync.ajax_url,
            type: 'POST',
            data: {
                action: 'amelia_cpt_sync_full_sync',
                nonce: ameliaCptSync.nonce
            },
            success: function(response) {
                if (response.success) {
                    var data = response.data;
                    
                    // Build results HTML
                    var html = '<div class="sync-stats">';
                    html += '<div class="sync-stat"><span class="sync-stat-number">' + data.total + '</span><span class="sync-stat-label"><?php _e('Total Services', 'amelia-cpt-sync'); ?></span></div>';
                    html += '<div class="sync-stat"><span class="sync-stat-number">' + data.synced + '</span><span class="sync-stat-label"><?php _e('Successfully Synced', 'amelia-cpt-sync'); ?></span></div>';
                    html += '<div class="sync-stat"><span class="sync-stat-number">' + data.created + '</span><span class="sync-stat-label"><?php _e('Created', 'amelia-cpt-sync'); ?></span></div>';
                    html += '<div class="sync-stat"><span class="sync-stat-number">' + data.updated + '</span><span class="sync-stat-label"><?php _e('Updated', 'amelia-cpt-sync'); ?></span></div>';
                    html += '</div>';
                    
                    // Show errors if any
                    if (data.errors && data.errors.length > 0) {
                        html += '<div class="sync-errors-list">';
                        html += '<h4><?php _e('Errors:', 'amelia-cpt-sync'); ?></h4>';
                        html += '<ul>';
                        $.each(data.errors, function(index, error) {
                            html += '<li><strong>' + error.service + ':</strong> ' + error.error + '</li>';
                        });
                        html += '</ul></div>';
                        $resultsContent.addClass('has-errors');
                    }
                    
                    $resultsContent.html(html);
                    $results.slideDown();
                } else {
                    alert('<?php _e('Error:', 'amelia-cpt-sync'); ?> ' + (response.data.message || '<?php _e('Unknown error occurred', 'amelia-cpt-sync'); ?>'));
                }
            },
            error: function(xhr, status, error) {
                alert('<?php _e('AJAX Error:', 'amelia-cpt-sync'); ?> ' + error);
            },
            complete: function() {
                $button.prop('disabled', false);
                $spinner.removeClass('is-active');
            }
        });
    });
});
</script>

