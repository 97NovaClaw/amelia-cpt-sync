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
                                <?php _e('No Resources — Manual configuration per service', 'amelia-cpt-sync'); ?>
                            </option>
                            <option value="mirrored" <?php selected($settings['default_mode'], 'mirrored'); ?>>
                                <?php _e('🔗 Dedicated Resource — One specific item per service', 'amelia-cpt-sync'); ?>
                            </option>
                            <option value="shared_pool" <?php selected($settings['default_mode'], 'shared_pool'); ?>>
                                <?php _e('🏊 Resource Pool — Pick one available item from a group', 'amelia-cpt-sync'); ?>
                            </option>
                            <option value="composite" <?php selected($settings['default_mode'], 'composite'); ?>>
                                <?php _e('📦 Multi Resource — Needs several different items at once', 'amelia-cpt-sync'); ?>
                            </option>
                        </select>
                        <p class="description">
                            <?php _e('New services will use this mode by default. Each service can override its mode in the service edit modal.', 'amelia-cpt-sync'); ?>
                        </p>
                        <ul style="margin-top: 8px; margin-left: 20px; font-size: 13px; color: #64748B;">
                            <li><strong>🔗 Dedicated Resource:</strong> <?php _e('Each service always uses one specific item (e.g., a named vehicle, a specific room)', 'amelia-cpt-sync'); ?></li>
                            <li><strong>🏊 Resource Pool:</strong> <?php _e('Pick one available item from a group (e.g., any van from the fleet, any open room)', 'amelia-cpt-sync'); ?></li>
                            <li><strong>📦 Multi Resource:</strong> <?php _e('Booking needs several different items at once, organized in requirement groups (e.g., a vehicle AND a driver kit)', 'amelia-cpt-sync'); ?></li>
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
            
            <h3><?php _e('📦 Multi Resource Mode', 'amelia-cpt-sync'); ?> <span style="color: #10B981; font-weight: 600; font-size: 14px;">✓ Available (v2.37.0)</span></h3>
            <p class="description">
                <?php _e('For services requiring ALL of multiple items at once (e.g., Vehicle + Camera + Lighting). Requirement groups are configured per service — use the Resource Configuration table below.', 'amelia-cpt-sync'); ?>
            </p>
            
            <h3><?php _e('Provider Bound Mode', 'amelia-cpt-sync'); ?> <span style="color: #666; font-weight: normal; font-size: 14px;">(Future Enhancement)</span></h3>
            <p style="color: #666; font-style: italic;"><?php _e('Settings for provider-specific resources are not yet available.', 'amelia-cpt-sync'); ?></p>
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
    
    <!-- Resource Configuration Hub (v2.41.0) -->
    <div class="art-settings-card" style="background: #fff; padding: 20px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <h2><?php _e('Resource Configuration', 'amelia-cpt-sync'); ?></h2>
        <p class="description">
            <?php _e('Every Amelia service and its current resource mode. Click Configure to change a service\'s mode, resources, and quantities — the same editor that appears when saving a service in Amelia.', 'amelia-cpt-sync'); ?>
        </p>
        
        <?php
        // List all visible services with their resource mode (NOTE: config column is amelia_service_id)
        $hub_services = $wpdb->get_results(
            "SELECT s.id, s.name, s.categoryId, cat.name AS category_name, c.resource_mode
             FROM {$wpdb->prefix}amelia_services s
             LEFT JOIN {$wpdb->prefix}art_resource_configs c ON c.amelia_service_id = s.id
             LEFT JOIN {$wpdb->prefix}amelia_categories cat ON cat.id = s.categoryId
             WHERE s.status = 'visible'
             ORDER BY s.name ASC"
        );
        
        $mode_badges = array(
            'none'        => array('label' => __('No Resources', 'amelia-cpt-sync'),        'bg' => '#F1F5F9', 'color' => '#64748B'),
            'mirrored'    => array('label' => __('🔗 Dedicated Resource', 'amelia-cpt-sync'), 'bg' => '#EDE9FE', 'color' => '#6D28D9'),
            'shared_pool' => array('label' => __('🏊 Resource Pool', 'amelia-cpt-sync'),      'bg' => '#DBEAFE', 'color' => '#1D4ED8'),
            'composite'   => array('label' => __('📦 Multi Resource', 'amelia-cpt-sync'),     'bg' => '#DCFCE7', 'color' => '#15803D'),
        );
        ?>
        
        <table class="widefat striped" id="art-resource-config-hub" style="margin-top: 12px;">
            <thead>
                <tr>
                    <th style="width: 35%;"><?php _e('Service', 'amelia-cpt-sync'); ?></th>
                    <th style="width: 25%;"><?php _e('Category', 'amelia-cpt-sync'); ?></th>
                    <th style="width: 25%;"><?php _e('Resource Mode', 'amelia-cpt-sync'); ?></th>
                    <th style="width: 15%;"></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($hub_services)): ?>
                    <tr><td colspan="4"><?php _e('No visible services found in Amelia.', 'amelia-cpt-sync'); ?></td></tr>
                <?php else: ?>
                    <?php foreach ($hub_services as $svc):
                        $badge = $mode_badges[$svc->resource_mode] ?? null;
                    ?>
                        <tr>
                            <td><strong><?php echo esc_html($svc->name); ?></strong> <span style="color: #94A3B8; font-size: 11px;">#<?php echo esc_html($svc->id); ?></span></td>
                            <td><?php echo esc_html($svc->category_name ?: '—'); ?></td>
                            <td>
                                <?php if ($badge): ?>
                                    <span style="display: inline-block; padding: 2px 10px; border-radius: 10px; font-size: 12px; font-weight: 600; background: <?php echo esc_attr($badge['bg']); ?>; color: <?php echo esc_attr($badge['color']); ?>;">
                                        <?php echo esc_html($badge['label']); ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: #94A3B8; font-style: italic; font-size: 12px;"><?php _e('Not configured', 'amelia-cpt-sync'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <button type="button"
                                        class="button button-secondary art-hub-configure"
                                        data-service-id="<?php echo esc_attr($svc->id); ?>"
                                        data-service-name="<?php echo esc_attr($svc->name); ?>">
                                    <?php _e('Configure', 'amelia-cpt-sync'); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Resource Configuration hub: open the same service modal the Amelia save flow uses
    $(document).on('click', '.art-hub-configure', function() {
        var serviceId = $(this).data('service-id');
        var serviceName = $(this).data('service-name');
        
        if (typeof window.ameliaCptSyncOpenServiceModal === 'function') {
            console.log('[Resource Hub] Opening config modal for service #' + serviceId + ' (' + serviceName + ')');
            window.ameliaCptSyncOpenServiceModal(serviceId, serviceName, false);
        } else {
            alert('<?php echo esc_js(__('The configuration modal script did not load. Please refresh the page.', 'amelia-cpt-sync')); ?>');
        }
    });
});
</script>

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

