<?php
/**
 * Availability Engine Settings Page Template
 *
 * @package AmeliaCPTSync
 * @subpackage ART
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Get settings
$availability_settings = new Amelia_CPT_Sync_ART_Availability_Settings();
$settings = $availability_settings->get_settings();
?>

<div class="wrap art-availability-settings">
    <h1><?php _e('Availability Engine Settings', 'amelia-cpt-sync'); ?></h1>
    
    <p class="description">
        <?php _e('Configure how the Availability Engine checks provider availability. These settings control the strictness of various availability checks.', 'amelia-cpt-sync'); ?>
    </p>
    
    <form id="availability-settings-form" method="post">
        <?php wp_nonce_field('art_availability_nonce', 'art_availability_nonce'); ?>
        
        <table class="form-table" role="presentation">
            
            <!-- Working Hours Check -->
            <tr>
                <th scope="row"><?php _e('Working Hours Check', 'amelia-cpt-sync'); ?></th>
                <td>
                    <fieldset>
                        <label>
                            <input type="radio" name="working_hours_mode" value="strict" 
                                <?php checked($settings['working_hours_mode'], 'strict'); ?>>
                            <strong><?php _e('Strict', 'amelia-cpt-sync'); ?></strong> - 
                            <?php _e('Provider not available outside working hours', 'amelia-cpt-sync'); ?>
                        </label><br>
                        <label>
                            <input type="radio" name="working_hours_mode" value="soft" 
                                <?php checked($settings['working_hours_mode'], 'soft'); ?>>
                            <strong><?php _e('Soft', 'amelia-cpt-sync'); ?></strong> - 
                            <?php _e('Show "Might Conflict" warning', 'amelia-cpt-sync'); ?>
                        </label><br>
                        <label>
                            <input type="radio" name="working_hours_mode" value="ignore" 
                                <?php checked($settings['working_hours_mode'], 'ignore'); ?>>
                            <strong><?php _e('Ignore', 'amelia-cpt-sync'); ?></strong> - 
                            <?php _e("Don't check working hours", 'amelia-cpt-sync'); ?>
                        </label>
                    </fieldset>
                    <p class="description">
                        <?php _e('Controls whether appointments can be booked outside provider working hours.', 'amelia-cpt-sync'); ?>
                    </p>
                </td>
            </tr>
            
            <!-- Buffer Time Check -->
            <tr>
                <th scope="row"><?php _e('Buffer Time Check', 'amelia-cpt-sync'); ?></th>
                <td>
                    <fieldset>
                        <label>
                            <input type="radio" name="buffer_time_mode" value="strict" 
                                <?php checked($settings['buffer_time_mode'], 'strict'); ?>>
                            <strong><?php _e('Strict', 'amelia-cpt-sync'); ?></strong> - 
                            <?php _e('Provider not available if buffer violated', 'amelia-cpt-sync'); ?>
                        </label><br>
                        <label>
                            <input type="radio" name="buffer_time_mode" value="soft" 
                                <?php checked($settings['buffer_time_mode'], 'soft'); ?>>
                            <strong><?php _e('Soft', 'amelia-cpt-sync'); ?></strong> - 
                            <?php _e('Show "Might Conflict" warning', 'amelia-cpt-sync'); ?>
                        </label><br>
                        <label>
                            <input type="radio" name="buffer_time_mode" value="ignore" 
                                <?php checked($settings['buffer_time_mode'], 'ignore'); ?>>
                            <strong><?php _e('Ignore', 'amelia-cpt-sync'); ?></strong> - 
                            <?php _e("Don't check buffer times", 'amelia-cpt-sync'); ?>
                        </label>
                    </fieldset>
                    <p class="description">
                        <?php _e('Buffer times are set per service (time before/after appointments).', 'amelia-cpt-sync'); ?>
                    </p>
                </td>
            </tr>
            
            <!-- Service Schedule Check -->
            <tr>
                <th scope="row"><?php _e('Service Schedule Check', 'amelia-cpt-sync'); ?></th>
                <td>
                    <fieldset>
                        <label>
                            <input type="radio" name="service_schedule_mode" value="strict" 
                                <?php checked($settings['service_schedule_mode'], 'strict'); ?>>
                            <strong><?php _e('Strict', 'amelia-cpt-sync'); ?></strong> - 
                            <?php _e('Provider not available for wrong service/day', 'amelia-cpt-sync'); ?>
                        </label><br>
                        <label>
                            <input type="radio" name="service_schedule_mode" value="soft" 
                                <?php checked($settings['service_schedule_mode'], 'soft'); ?>>
                            <strong><?php _e('Soft', 'amelia-cpt-sync'); ?></strong> - 
                            <?php _e('Show "Might Conflict" warning', 'amelia-cpt-sync'); ?>
                        </label><br>
                        <label>
                            <input type="radio" name="service_schedule_mode" value="ignore" 
                                <?php checked($settings['service_schedule_mode'], 'ignore'); ?>>
                            <strong><?php _e('Ignore', 'amelia-cpt-sync'); ?></strong> - 
                            <?php _e('Assume any service anytime', 'amelia-cpt-sync'); ?>
                        </label>
                    </fieldset>
                    <p class="description">
                        <?php _e('Checks if provider offers this specific service on the selected day.', 'amelia-cpt-sync'); ?>
                    </p>
                </td>
            </tr>
            
            <!-- Location Check -->
            <tr>
                <th scope="row"><?php _e('Location Check', 'amelia-cpt-sync'); ?></th>
                <td>
                    <fieldset>
                        <label>
                            <input type="radio" name="location_mode" value="strict" 
                                <?php checked($settings['location_mode'], 'strict'); ?>>
                            <strong><?php _e('Strict', 'amelia-cpt-sync'); ?></strong> - 
                            <?php _e('Provider not available if not at this location', 'amelia-cpt-sync'); ?>
                        </label><br>
                        <label>
                            <input type="radio" name="location_mode" value="soft" 
                                <?php checked($settings['location_mode'], 'soft'); ?>>
                            <strong><?php _e('Soft', 'amelia-cpt-sync'); ?></strong> - 
                            <?php _e('Show "Might Conflict" warning', 'amelia-cpt-sync'); ?>
                        </label><br>
                        <label>
                            <input type="radio" name="location_mode" value="ignore" 
                                <?php checked($settings['location_mode'], 'ignore'); ?>>
                            <strong><?php _e('Ignore', 'amelia-cpt-sync'); ?></strong> - 
                            <?php _e("Don't check location assignments (recommended for single-location)", 'amelia-cpt-sync'); ?>
                        </label>
                    </fieldset>
                    <p class="description">
                        <?php _e('Location filtering is handled by Amelia API. This adds extra validation.', 'amelia-cpt-sync'); ?>
                    </p>
                </td>
            </tr>
            
            <!-- Show Location Selector -->
            <tr>
                <th scope="row"><?php _e('Location Selector', 'amelia-cpt-sync'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="show_location_selector" value="1" 
                            <?php checked($settings['show_location_selector']); ?>>
                        <?php _e('Show location selector in availability section', 'amelia-cpt-sync'); ?>
                    </label>
                    <p class="description">
                        <?php _e('If unchecked, location will be taken from the booking pillars field.', 'amelia-cpt-sync'); ?>
                    </p>
                </td>
            </tr>
            
            <!-- Resource Checking -->
            <tr>
                <th scope="row"><?php _e('Resource Checking', 'amelia-cpt-sync'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="check_resources" value="1" 
                            <?php checked($settings['check_resources']); ?>>
                        <?php _e('Check resource availability', 'amelia-cpt-sync'); ?>
                    </label>
                    <p class="description">
                        <?php _e('Enable if your services require specific resources (e.g., rooms, equipment).', 'amelia-cpt-sync'); ?>
                    </p>
                </td>
            </tr>
            
            <!-- Appointment Status Checks -->
            <tr>
                <th scope="row"><?php _e('Appointment Status Checks', 'amelia-cpt-sync'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="check_approved_appointments" value="1" 
                            <?php checked($settings['check_approved_appointments']); ?>>
                        <?php _e('Check approved appointments (blocks time)', 'amelia-cpt-sync'); ?>
                    </label><br>
                    <label>
                        <input type="checkbox" name="check_pending_appointments" value="1" 
                            <?php checked($settings['check_pending_appointments']); ?>>
                        <?php _e('Check pending appointments (shows as "Might Conflict")', 'amelia-cpt-sync'); ?>
                    </label>
                    <p class="description">
                        <?php _e('Choose which appointment statuses to consider when checking availability.', 'amelia-cpt-sync'); ?>
                    </p>
                </td>
            </tr>
            
        </table>
        
        <p class="submit">
            <button type="submit" class="button button-primary" id="save-availability-settings">
                <?php _e('Save Settings', 'amelia-cpt-sync'); ?>
            </button>
            <span id="save-status" style="margin-left: 10px;"></span>
        </p>
    </form>
    
    <!-- Info Box -->
    <div class="art-info-box" style="margin-top: 30px; padding: 15px; background: #f0f6fc; border-left: 4px solid #0073aa; max-width: 800px;">
        <h3 style="margin-top: 0;"><?php _e('How the Availability Engine Works', 'amelia-cpt-sync'); ?></h3>
        <p><?php _e('When checking provider availability, the engine performs these checks in order:', 'amelia-cpt-sync'); ?></p>
        <ol>
            <li><strong><?php _e('Day Off Check', 'amelia-cpt-sync'); ?></strong> - <?php _e('Is the provider on a scheduled day off?', 'amelia-cpt-sync'); ?></li>
            <li><strong><?php _e('Special Day Check', 'amelia-cpt-sync'); ?></strong> - <?php _e('Does the provider have a special schedule for this date?', 'amelia-cpt-sync'); ?></li>
            <li><strong><?php _e('Working Hours', 'amelia-cpt-sync'); ?></strong> - <?php _e('Does the appointment fit within working hours?', 'amelia-cpt-sync'); ?></li>
            <li><strong><?php _e('Appointment Overlaps', 'amelia-cpt-sync'); ?></strong> - <?php _e('Does the time conflict with existing appointments?', 'amelia-cpt-sync'); ?></li>
            <li><strong><?php _e('Buffer Times', 'amelia-cpt-sync'); ?></strong> - <?php _e('Is there enough buffer time between appointments?', 'amelia-cpt-sync'); ?></li>
            <li><strong><?php _e('Service Schedule', 'amelia-cpt-sync'); ?></strong> - <?php _e('Does the provider offer this service on this day?', 'amelia-cpt-sync'); ?></li>
            <li><strong><?php _e('Resources', 'amelia-cpt-sync'); ?></strong> - <?php _e('Are required resources available?', 'amelia-cpt-sync'); ?></li>
        </ol>
        <p><strong><?php _e('Status Meanings:', 'amelia-cpt-sync'); ?></strong></p>
        <ul>
            <li><span style="color: green;">✓ <?php _e('Available', 'amelia-cpt-sync'); ?></span> - <?php _e('No conflicts detected', 'amelia-cpt-sync'); ?></li>
            <li><span style="color: orange;">⚠ <?php _e('Might Conflict', 'amelia-cpt-sync'); ?></span> - <?php _e('Soft conflict detected (can still book)', 'amelia-cpt-sync'); ?></li>
            <li><span style="color: gray;">○ <?php _e('Force Book', 'amelia-cpt-sync'); ?></span> - <?php _e('Override availability checks', 'amelia-cpt-sync'); ?></li>
        </ul>
    </div>
</div>

<style>
.art-availability-settings .form-table th {
    width: 200px;
    padding: 20px 10px 20px 0;
}
.art-availability-settings .form-table td {
    padding: 15px 10px;
}
.art-availability-settings fieldset label {
    display: block;
    margin-bottom: 8px;
}
.art-availability-settings .description {
    margin-top: 8px;
    color: #666;
}
#save-status.success {
    color: #46b450;
}
#save-status.error {
    color: #dc3232;
}
</style>

<script>
jQuery(document).ready(function($) {
    $('#availability-settings-form').on('submit', function(e) {
        e.preventDefault();
        
        var $btn = $('#save-availability-settings');
        var $status = $('#save-status');
        
        $btn.prop('disabled', true).text('<?php _e('Saving...', 'amelia-cpt-sync'); ?>');
        $status.removeClass('success error').text('');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'art_save_availability_settings',
                nonce: $('#art_availability_nonce').val(),
                working_hours_mode: $('input[name="working_hours_mode"]:checked').val(),
                buffer_time_mode: $('input[name="buffer_time_mode"]:checked').val(),
                service_schedule_mode: $('input[name="service_schedule_mode"]:checked').val(),
                location_mode: $('input[name="location_mode"]:checked').val(),
                show_location_selector: $('input[name="show_location_selector"]').is(':checked') ? 1 : 0,
                check_resources: $('input[name="check_resources"]').is(':checked') ? 1 : 0,
                check_approved_appointments: $('input[name="check_approved_appointments"]').is(':checked') ? 1 : 0,
                check_pending_appointments: $('input[name="check_pending_appointments"]').is(':checked') ? 1 : 0
            },
            success: function(response) {
                $btn.prop('disabled', false).text('<?php _e('Save Settings', 'amelia-cpt-sync'); ?>');
                if (response.success) {
                    $status.addClass('success').text('✓ ' + response.data.message);
                } else {
                    $status.addClass('error').text('✗ ' + (response.data.message || '<?php _e('Error saving settings', 'amelia-cpt-sync'); ?>'));
                }
            },
            error: function() {
                $btn.prop('disabled', false).text('<?php _e('Save Settings', 'amelia-cpt-sync'); ?>');
                $status.addClass('error').text('✗ <?php _e('Network error', 'amelia-cpt-sync'); ?>');
            }
        });
    });
});
</script>

