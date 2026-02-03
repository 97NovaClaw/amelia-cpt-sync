<?php
/**
 * ART Resource Settings Page Template
 *
 * Global settings for resource management system
 * Phase 1: Mode 0/1 settings, placeholders for future modes
 *
 * @package AmeliaCPTSync
 * @subpackage ART
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Get current settings
$settings = get_option('art_resource_settings', array(
    'enabled' => false,
    'default_mode' => 'none',
    'overbooking_policy' => 'admin_only',
    'mirrored' => array(
        'auto_create' => true,
        'sync_name' => true,
        'auto_delete' => false
    )
));
?>

<div class="wrap art-settings-page">
    <h1><?php _e('Resource Management Settings', 'amelia-cpt-sync'); ?></h1>
    <p class="description">
        <?php _e('Configure how resources work across your booking system. Resources can represent vehicles, rooms, equipment, or any limited physical items required for services.', 'amelia-cpt-sync'); ?>
    </p>
    
    <form method="post" action="options.php" id="art-resource-settings-form">
        <?php settings_fields('art_resource_settings_group'); ?>
        
        <!-- General Settings -->
        <div class="art-settings-card" style="background: #fff; padding: 20px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h2><?php _e('General Settings', 'amelia-cpt-sync'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="resource-enabled"><?php _e('Enable Resource Management', 'amelia-cpt-sync'); ?></label>
                    </th>
                    <td>
                        <label class="switch">
                            <input type="checkbox" id="resource-enabled" name="art_resource_settings[enabled]" value="1" <?php checked($settings['enabled'], true); ?>>
                            <span class="slider"></span>
                        </label>
                        <p class="description">
                            <?php _e('Enable resource checking across the plugin. When enabled, services can be configured to require specific resources.', 'amelia-cpt-sync'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="default-mode"><?php _e('Default Mode for New Services', 'amelia-cpt-sync'); ?></label>
                    </th>
                    <td>
                        <select id="default-mode" name="art_resource_settings[default_mode]" class="regular-text">
                            <option value="none" <?php selected($settings['default_mode'], 'none'); ?>>
                                <?php _e('None - Manual configuration required', 'amelia-cpt-sync'); ?>
                            </option>
                            <option value="mirrored" <?php selected($settings['default_mode'], 'mirrored'); ?>>
                                <?php _e('🔗 Mirrored - Auto-create 1:1 resource per service', 'amelia-cpt-sync'); ?>
                            </option>
                            <option value="shared_pool" <?php selected($settings['default_mode'], 'shared_pool'); ?>>
                                <?php _e('🏊 Shared Pool - Services share resource pool', 'amelia-cpt-sync'); ?>
                            </option>
                            <option value="ask" <?php selected($settings['default_mode'], 'ask'); ?>>
                                <?php _e('Ask - Prompt when creating services', 'amelia-cpt-sync'); ?>
                            </option>
                        </select>
                        <p class="description">
                            <?php _e('Choose the default resource mode for ALL services:', 'amelia-cpt-sync'); ?>
                        </p>
                        <ul style="margin-top: 8px; margin-left: 20px; font-size: 13px; color: #64748B;">
                            <li><strong>🔗 Mirrored:</strong> Each service has its own dedicated resource (1:1 relationship)</li>
                            <li><strong>🏊 Shared Pool:</strong> Multiple services share a pool of resources (e.g., 3 massage services share 5 rooms)</li>
                        </ul>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="overbooking-policy"><?php _e('Overbooking Policy', 'amelia-cpt-sync'); ?></label>
                    </th>
                    <td>
                        <select id="overbooking-policy" name="art_resource_settings[overbooking_policy]" class="regular-text">
                            <option value="never" <?php selected($settings['overbooking_policy'], 'never'); ?>>
                                <?php _e('Never allow overbooking', 'amelia-cpt-sync'); ?>
                            </option>
                            <option value="admin_only" <?php selected($settings['overbooking_policy'], 'admin_only'); ?>>
                                <?php _e('Admin can force-book (with reason)', 'amelia-cpt-sync'); ?>
                            </option>
                            <option value="warning" <?php selected($settings['overbooking_policy'], 'warning'); ?>>
                                <?php _e('Allow with warning', 'amelia-cpt-sync'); ?>
                            </option>
                        </select>
                        <p class="description">
                            <?php _e('How to handle resource conflicts and overbooking.', 'amelia-cpt-sync'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Mode-Specific Defaults -->
        <div class="art-settings-card" style="background: #fff; padding: 20px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h2><?php _e('Mode-Specific Defaults', 'amelia-cpt-sync'); ?></h2>
            <p class="description">
                <?php _e('Configure default behavior for each resource mode.', 'amelia-cpt-sync'); ?>
            </p>
            
            <!-- Mirrored Mode Defaults -->
            <h3><?php _e('Mirrored Mode (1:1 Service = Resource)', 'amelia-cpt-sync'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Auto-create resource', 'amelia-cpt-sync'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="art_resource_settings[mirrored][auto_create]" value="1" <?php checked($settings['mirrored']['auto_create'] ?? true, true); ?>>
                            <?php _e('Automatically create a new resource when enabling mirrored mode', 'amelia-cpt-sync'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Sync names', 'amelia-cpt-sync'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="art_resource_settings[mirrored][sync_name]" value="1" <?php checked($settings['mirrored']['sync_name'] ?? true, true); ?>>
                            <?php _e('Keep resource name synced with service name', 'amelia-cpt-sync'); ?>
                        </label>
                        <p class="description">
                            <?php _e('When a service is renamed, automatically rename its mirrored resource.', 'amelia-cpt-sync'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Auto-delete on service delete', 'amelia-cpt-sync'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="art_resource_settings[mirrored][auto_delete]" value="1" <?php checked($settings['mirrored']['auto_delete'] ?? false, true); ?>>
                            <?php _e('Delete the mirrored resource when the service is deleted', 'amelia-cpt-sync'); ?>
                        </label>
                        <p class="description">
                            <?php _e('If unchecked, orphaned resources will remain in Amelia.', 'amelia-cpt-sync'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            
            <!-- Shared Pool Mode Settings -->
            <h3><?php _e('🏊 Shared Pool Mode', 'amelia-cpt-sync'); ?> <span style="color: #10B981; font-weight: 600; font-size: 14px;">✓ Available (v2.32.0)</span></h3>
            <p class="description" style="margin-bottom: 16px;">
                <?php _e('Multiple services share a pool of resources. The system automatically assigns an available resource based on your selection strategy.', 'amelia-cpt-sync'); ?>
            </p>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label><?php _e('Default Selection Strategy', 'amelia-cpt-sync'); ?></label>
                    </th>
                    <td>
                        <?php
                        $pool_settings = $settings['shared_pool'] ?? array();
                        $default_strategy = $pool_settings['default_strategy'] ?? 'first_available';
                        ?>
                        <select name="art_resource_settings[shared_pool][default_strategy]" class="regular-text">
                            <option value="first_available" <?php selected($default_strategy, 'first_available'); ?>>
                                <?php _e('⚡ First Available - Use first resource with capacity', 'amelia-cpt-sync'); ?>
                            </option>
                            <option value="least_used" <?php selected($default_strategy, 'least_used'); ?>>
                                <?php _e('⚖️ Least Used - Balance load across pool', 'amelia-cpt-sync'); ?>
                            </option>
                            <option value="manual" <?php selected($default_strategy, 'manual'); ?>>
                                <?php _e('👤 Manual - Admin chooses at booking time', 'amelia-cpt-sync'); ?>
                            </option>
                        </select>
                        <p class="description">
                            <?php _e('How the system selects which resource to use when multiple are available. Can be overridden per service.', 'amelia-cpt-sync'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            
            <!-- Future mode placeholder -->
            <h3><?php _e('📦 Composite Mode', 'amelia-cpt-sync'); ?> <span style="color: #94A3B8; font-weight: normal; font-size: 14px;">(Future Enhancement)</span></h3>
            <p style="color: #64748B; font-style: italic;">
                <?php _e('For services requiring ALL of multiple specific resources (e.g., Camera + Lighting + Backdrop). Contact developer if needed.', 'amelia-cpt-sync'); ?>
            </p>
            
            <h3><?php _e('Provider Bound Mode', 'amelia-cpt-sync'); ?> <span style="color: #666; font-weight: normal; font-size: 14px;">(Coming in Phase 3)</span></h3>
            <p style="color: #666; font-style: italic;"><?php _e('Settings for provider-specific resources will be available in v2.25.0', 'amelia-cpt-sync'); ?></p>
        </div>
        
        <?php submit_button(__('Save Settings', 'amelia-cpt-sync')); ?>
    </form>
    
    <!-- Quick Stats -->
    <div class="art-settings-card" style="background: #fff; padding: 20px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <h2><?php _e('System Status', 'amelia-cpt-sync'); ?></h2>
        <?php
        $resource_api = new ART_Resource_API();
        $resources = $resource_api->get_resources(false);
        $resource_count = is_wp_error($resources) ? 0 : count($resources);
        
        global $wpdb;
        $configs_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}art_resource_configs");
        $assignments_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}art_resource_assignments WHERE status = 'active'");
        ?>
        <table class="widefat">
            <tr>
                <th><?php _e('Total Resources in Amelia', 'amelia-cpt-sync'); ?></th>
                <td><strong><?php echo esc_html($resource_count); ?></strong></td>
            </tr>
            <tr>
                <th><?php _e('Services with Resource Config', 'amelia-cpt-sync'); ?></th>
                <td><strong><?php echo esc_html($configs_count ?? 0); ?></strong></td>
            </tr>
            <tr>
                <th><?php _e('Active Resource Assignments', 'amelia-cpt-sync'); ?></th>
                <td><strong><?php echo esc_html($assignments_count ?? 0); ?></strong></td>
            </tr>
        </table>
    </div>
</div>

<style>
.art-settings-page {
    max-width: 1200px;
}

.art-settings-card {
    background: #fff;
    border: 1px solid #e0e5f1;
    border-radius: 8px;
}

.art-settings-card h2 {
    margin-top: 0;
    font-size: 18px;
    font-weight: 600;
}

.art-settings-card h3 {
    font-size: 16px;
    font-weight: 600;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #e0e5f1;
}

.art-settings-card h3:first-of-type {
    margin-top: 0;
    padding-top: 0;
    border-top: none;
}

/* Toggle switch */
.switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 24px;
}

.switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: .4s;
    border-radius: 24px;
}

.slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
}

input:checked + .slider {
    background-color: #1A84EE;
}

input:checked + .slider:before {
    transform: translateX(26px);
}
</style>

<script>
jQuery(document).ready(function($) {
    $('#art-resource-settings-form').on('submit', function(e) {
        console.log('Resource settings form submitted');
    });
});
</script>

