<?php
/**
 * ART Resource Configuration Modal
 *
 * Per-service resource configuration interface
 * Phase 1: Mode 0/1 active, others show "Coming Soon"
 *
 * @package AmeliaCPTSync
 * @subpackage ART
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}
?>

<!-- Resource Configuration Modal -->
<div id="art-resource-config-modal" class="art-modal" style="display: none;">
    <div class="art-modal-overlay"></div>
    <div class="art-modal-content" style="max-width: 700px;">
        <div class="art-modal-header">
            <h2><?php _e('Resource Configuration', 'amelia-cpt-sync'); ?></h2>
            <p class="modal-subtitle">
                <?php _e('Service:', 'amelia-cpt-sync'); ?> <strong id="config-service-name"></strong>
            </p>
            <button type="button" class="art-modal-close" id="close-resource-config">&times;</button>
        </div>
        
        <div class="art-modal-body">
            <form id="resource-config-form">
                <input type="hidden" id="config-service-id" value="">
                
                <!-- Resource Mode Selection -->
                <div class="form-section">
                    <h3><?php _e('Resource Mode', 'amelia-cpt-sync'); ?></h3>
                    <p class="description"><?php _e('Choose how resources are managed for this service.', 'amelia-cpt-sync'); ?></p>
                    
                    <div class="mode-options">
                        <!-- Mode 0: None -->
                        <label class="mode-option" data-mode="none">
                            <input type="radio" name="resource_mode" value="none" checked>
                            <div class="mode-card">
                                <div class="mode-header">
                                    <strong><?php _e('None', 'amelia-cpt-sync'); ?></strong>
                                    <span class="mode-status available"><?php _e('Active', 'amelia-cpt-sync'); ?></span>
                                </div>
                                <p><?php _e('No resource management for this service', 'amelia-cpt-sync'); ?></p>
                                <p class="mode-use-case"><?php _e('Best for: Services without physical constraints', 'amelia-cpt-sync'); ?></p>
                            </div>
                        </label>
                        
                        <!-- Mode 1: Mirrored -->
                        <label class="mode-option" data-mode="mirrored">
                            <input type="radio" name="resource_mode" value="mirrored">
                            <div class="mode-card">
                                <div class="mode-header">
                                    <strong><?php _e('Mirrored (1:1)', 'amelia-cpt-sync'); ?></strong>
                                    <span class="mode-status available"><?php _e('Active', 'amelia-cpt-sync'); ?></span>
                                </div>
                                <p><?php _e('This service IS the resource', 'amelia-cpt-sync'); ?></p>
                                <p class="mode-use-case"><?php _e('Best for: Vehicles, unique rooms, single equipment items', 'amelia-cpt-sync'); ?></p>
                            </div>
                        </label>
                        
                        <!-- Mode 2: Shared Pool (Phase 2) -->
                        <label class="mode-option disabled" data-mode="shared_pool">
                            <input type="radio" name="resource_mode" value="shared_pool" disabled>
                            <div class="mode-card">
                                <div class="mode-header">
                                    <strong><?php _e('Shared Pool', 'amelia-cpt-sync'); ?></strong>
                                    <span class="mode-status pending"><?php _e('Phase 2', 'amelia-cpt-sync'); ?></span>
                                </div>
                                <p><?php _e('Multiple services share a pool of resources', 'amelia-cpt-sync'); ?></p>
                                <p class="mode-use-case"><?php _e('Best for: Treatment rooms, shared equipment', 'amelia-cpt-sync'); ?></p>
                            </div>
                        </label>
                        
                        <!-- Mode 3: Quantity Pool (Phase 2) -->
                        <label class="mode-option disabled" data-mode="quantity_pool">
                            <input type="radio" name="resource_mode" value="quantity_pool" disabled>
                            <div class="mode-card">
                                <div class="mode-header">
                                    <strong><?php _e('Quantity Pool', 'amelia-cpt-sync'); ?></strong>
                                    <span class="mode-status pending"><?php _e('Phase 2', 'amelia-cpt-sync'); ?></span>
                                </div>
                                <p><?php _e('Resource with multiple units (quantity > 1)', 'amelia-cpt-sync'); ?></p>
                                <p class="mode-use-case"><?php _e('Best for: Bikes, chairs, identical items', 'amelia-cpt-sync'); ?></p>
                            </div>
                        </label>
                        
                        <!-- Mode 4: Provider Bound (Phase 3) -->
                        <label class="mode-option disabled" data-mode="provider_bound">
                            <input type="radio" name="resource_mode" value="provider_bound" disabled>
                            <div class="mode-card">
                                <div class="mode-header">
                                    <strong><?php _e('Provider Bound', 'amelia-cpt-sync'); ?></strong>
                                    <span class="mode-status pending"><?php _e('Phase 3', 'amelia-cpt-sync'); ?></span>
                                </div>
                                <p><?php _e('Each provider has their own resource', 'amelia-cpt-sync'); ?></p>
                                <p class="mode-use-case"><?php _e('Best for: Tattoo stations, styling chairs', 'amelia-cpt-sync'); ?></p>
                            </div>
                        </label>
                        
                        <!-- Mode 5: Location Bound (Phase 3) -->
                        <label class="mode-option disabled" data-mode="location_bound">
                            <input type="radio" name="resource_mode" value="location_bound" disabled>
                            <div class="mode-card">
                                <div class="mode-header">
                                    <strong><?php _e('Location Bound', 'amelia-cpt-sync'); ?></strong>
                                    <span class="mode-status pending"><?php _e('Phase 3', 'amelia-cpt-sync'); ?></span>
                                </div>
                                <p><?php _e('Resources vary by location', 'amelia-cpt-sync'); ?></p>
                                <p class="mode-use-case"><?php _e('Best for: Multi-branch businesses', 'amelia-cpt-sync'); ?></p>
                            </div>
                        </label>
                        
                        <!-- Mode 6: Composite (Phase 4) -->
                        <label class="mode-option disabled" data-mode="composite">
                            <input type="radio" name="resource_mode" value="composite" disabled>
                            <div class="mode-card">
                                <div class="mode-header">
                                    <strong><?php _e('Composite', 'amelia-cpt-sync'); ?></strong>
                                    <span class="mode-status pending"><?php _e('Phase 4', 'amelia-cpt-sync'); ?></span>
                                </div>
                                <p><?php _e('Service requires multiple resources', 'amelia-cpt-sync'); ?></p>
                                <p class="mode-use-case"><?php _e('Best for: Photo studio, event packages', 'amelia-cpt-sync'); ?></p>
                            </div>
                        </label>
                    </div>
                </div>
                
                <!-- Mode-Specific Settings -->
                
                <!-- Mode 1: Mirrored Settings -->
                <div id="mode-mirrored-settings" class="mode-settings" style="display: none;">
                    <div class="form-section">
                        <h3><?php _e('Mirrored Mode Settings', 'amelia-cpt-sync'); ?></h3>
                        
                        <div class="form-group">
                            <label>
                                <input type="checkbox" id="mirrored-auto-create" checked>
                                <?php _e('Auto-create resource for this service', 'amelia-cpt-sync'); ?>
                            </label>
                            <p class="description">
                                <?php _e('Create a new resource automatically when saving this configuration.', 'amelia-cpt-sync'); ?>
                            </p>
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <input type="checkbox" id="mirrored-sync-name" checked>
                                <?php _e('Keep resource name synced with service', 'amelia-cpt-sync'); ?>
                            </label>
                            <p class="description">
                                <?php _e('Automatically update the resource name when the service is renamed.', 'amelia-cpt-sync'); ?>
                            </p>
                        </div>
                        
                        <div class="form-group" id="existing-resource-select-group" style="display: none;">
                            <label for="existing-resource-select"><?php _e('Or use existing resource:', 'amelia-cpt-sync'); ?></label>
                            <select id="existing-resource-select" class="regular-text">
                                <option value=""><?php _e('-- Create new --', 'amelia-cpt-sync'); ?></option>
                                <!-- Populated via AJAX -->
                            </select>
                        </div>
                        
                        <div id="current-resource-status" class="resource-status-box" style="display: none;">
                            <!-- Shows current linked resource if exists -->
                        </div>
                    </div>
                </div>
                
                <!-- Conflict Handling -->
                <div class="form-section">
                    <h3><?php _e('Conflict Handling', 'amelia-cpt-sync'); ?></h3>
                    <p class="description"><?php _e('Override global conflict handling for this service.', 'amelia-cpt-sync'); ?></p>
                    
                    <div class="form-group">
                        <label>
                            <input type="radio" name="conflict_handling" value="strict" checked>
                            <?php _e('Strict - Block booking if resource unavailable', 'amelia-cpt-sync'); ?>
                        </label>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="radio" name="conflict_handling" value="soft">
                            <?php _e('Soft - Show warning, allow booking', 'amelia-cpt-sync'); ?>
                        </label>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="radio" name="conflict_handling" value="ignore">
                            <?php _e('Ignore - Don\'t check resource availability', 'amelia-cpt-sync'); ?>
                        </label>
                    </div>
                </div>
            </form>
        </div>
        
        <div class="art-modal-footer">
            <button type="button" class="button" id="cancel-resource-config"><?php _e('Cancel', 'amelia-cpt-sync'); ?></button>
            <button type="button" class="button button-primary" id="save-resource-config"><?php _e('Save Configuration', 'amelia-cpt-sync'); ?></button>
        </div>
    </div>
</div>

<style>
/* Modal Styling */
.art-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 100000;
}

.art-modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.6);
}

.art-modal-content {
    position: relative;
    max-width: 700px;
    max-height: 90vh;
    margin: 5vh auto;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    display: flex;
    flex-direction: column;
}

.art-modal-header {
    padding: 20px 24px;
    border-bottom: 1px solid #e0e5f1;
}

.art-modal-header h2 {
    margin: 0;
    font-size: 20px;
}

.modal-subtitle {
    margin: 5px 0 0;
    color: #64748B;
    font-size: 14px;
}

.art-modal-close {
    position: absolute;
    top: 15px;
    right: 15px;
    background: none;
    border: none;
    font-size: 28px;
    cursor: pointer;
    color: #64748B;
    width: 30px;
    height: 30px;
    padding: 0;
    line-height: 1;
}

.art-modal-close:hover {
    color: #1E293B;
}

.art-modal-body {
    padding: 24px;
    overflow-y: auto;
    flex: 1;
}

.art-modal-footer {
    padding: 16px 24px;
    border-top: 1px solid #e0e5f1;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

/* Mode Selection */
.mode-options {
    display: grid;
    grid-template-columns: 1fr;
    gap: 12px;
    margin-top: 16px;
}

.mode-option {
    display: block;
    cursor: pointer;
    border-radius: 6px;
    transition: all 0.2s;
}

.mode-option.disabled {
    cursor: not-allowed;
    opacity: 0.6;
}

.mode-option input[type="radio"] {
    position: absolute;
    opacity: 0;
}

.mode-card {
    border: 2px solid #e0e5f1;
    padding: 16px;
    border-radius: 6px;
    transition: all 0.2s;
}

.mode-option input[type="radio"]:checked + .mode-card {
    border-color: #1A84EE;
    background: #EFF6FF;
}

.mode-option:not(.disabled):hover .mode-card {
    border-color: #1A84EE;
}

.mode-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.mode-header strong {
    font-size: 15px;
    color: #1E293B;
}

.mode-status {
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.mode-status.available {
    background: #D1FAE5;
    color: #065F46;
}

.mode-status.pending {
    background: #FEF3C7;
    color: #92400E;
}

.mode-card p {
    margin: 4px 0;
    font-size: 13px;
    color: #475569;
}

.mode-use-case {
    font-size: 12px;
    color: #94A3B8;
    font-style: italic;
}

/* Form Sections */
.form-section {
    margin: 24px 0;
    padding: 20px;
    background: #F8FAFC;
    border-radius: 6px;
    border: 1px solid #e0e5f1;
}

.form-section h3 {
    margin: 0 0 8px 0;
    font-size: 16px;
}

.form-section .description {
    margin: 0 0 16px 0;
    font-size: 13px;
    color: #64748B;
}

.mode-settings {
    animation: fadeIn 0.3s;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.form-group {
    margin: 16px 0;
}

.form-group label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    cursor: pointer;
}

.form-group .description {
    margin: 4px 0 0 24px;
    font-size: 12px;
    color: #64748B;
}

.resource-status-box {
    margin-top: 16px;
    padding: 12px;
    background: #EFF6FF;
    border: 1px solid #1A84EE;
    border-radius: 6px;
    font-size: 13px;
}

.resource-status-box strong {
    color: #1A84EE;
}
</style>

<script>
jQuery(document).ready(function($) {
    var currentServiceId = null;
    var currentServiceName = null;
    
    /**
     * Open configuration modal
     */
    window.openResourceConfigModal = function(serviceId, serviceName) {
        currentServiceId = serviceId;
        currentServiceName = serviceName;
        
        $('#config-service-id').val(serviceId);
        $('#config-service-name').text(serviceName);
        
        // Load existing configuration
        loadServiceConfig(serviceId);
        
        // Load available resources for dropdown
        loadAllResources();
        
        $('#art-resource-config-modal').fadeIn(200);
    };
    
    /**
     * Load existing configuration for service
     */
    function loadServiceConfig(serviceId) {
        $.post(ajaxurl, {
            action: 'art_get_service_resource_config',
            nonce: '<?php echo wp_create_nonce('art_nonce'); ?>',
            service_id: serviceId
        }, function(response) {
            if (response.success && response.data.config) {
                populateForm(response.data.config);
            } else {
                // No config yet - defaults
                resetForm();
            }
        });
    }
    
    /**
     * Populate form with existing config
     */
    function populateForm(config) {
        $('input[name="resource_mode"][value="' + config.resource_mode + '"]').prop('checked', true).trigger('change');
        $('input[name="conflict_handling"][value="' + config.conflict_handling + '"]').prop('checked', true);
        
        if (config.resource_mode === 'mirrored' && config.mode_settings) {
            $('#mirrored-auto-create').prop('checked', config.mode_settings.auto_create ?? true);
            $('#mirrored-sync-name').prop('checked', config.mode_settings.sync_name ?? true);
            
            if (config.mode_settings.mirrored_resource_id) {
                showCurrentResource(config.mode_settings.mirrored_resource_id);
            }
        }
    }
    
    /**
     * Reset form to defaults
     */
    function resetForm() {
        $('input[name="resource_mode"][value="none"]').prop('checked', true).trigger('change');
        $('input[name="conflict_handling"][value="strict"]').prop('checked', true);
        $('#mirrored-auto-create').prop('checked', true);
        $('#mirrored-sync-name').prop('checked', true);
        $('#current-resource-status').hide();
    }
    
    /**
     * Show current linked resource
     */
    function showCurrentResource(resourceId) {
        // TODO: Fetch resource details and display
        $('#current-resource-status').html(
            '<strong>Current Resource:</strong> ID #' + resourceId
        ).show();
    }
    
    /**
     * Load all resources from Amelia
     */
    function loadAllResources() {
        $.post(ajaxurl, {
            action: 'art_get_all_resources',
            nonce: '<?php echo wp_create_nonce('art_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                var html = '<option value="">-- Create new --</option>';
                response.data.resources.forEach(function(resource) {
                    html += '<option value="' + resource.id + '">' + resource.name + 
                            ' (Qty: ' + resource.quantity + ')</option>';
                });
                $('#existing-resource-select').html(html);
            }
        });
    }
    
    /**
     * Mode change handler
     */
    $('input[name="resource_mode"]').on('change', function() {
        var mode = $(this).val();
        
        // Hide all mode settings
        $('.mode-settings').hide();
        
        // Show relevant settings
        if (mode === 'mirrored') {
            $('#mode-mirrored-settings').show();
        }
        // Other modes will be added in future phases
    });
    
    /**
     * Save configuration
     */
    $('#save-resource-config').on('click', function() {
        var serviceId = $('#config-service-id').val();
        var mode = $('input[name="resource_mode"]:checked').val();
        var conflictHandling = $('input[name="conflict_handling"]:checked').val();
        
        var modeSettings = {};
        
        if (mode === 'mirrored') {
            modeSettings = {
                auto_create: $('#mirrored-auto-create').is(':checked'),
                sync_name: $('#mirrored-sync-name').is(':checked'),
                mirrored_resource_id: $('#existing-resource-select').val() || null
            };
        }
        
        $(this).prop('disabled', true).text('Saving...');
        
        $.post(ajaxurl, {
            action: 'art_save_service_resource_config',
            nonce: '<?php echo wp_create_nonce('art_nonce'); ?>',
            service_id: serviceId,
            resource_mode: mode,
            mode_settings: modeSettings,
            conflict_handling: conflictHandling
        }, function(response) {
            $('#save-resource-config').prop('disabled', false).text('Save Configuration');
            
            if (response.success) {
                alert('Configuration saved successfully!');
                $('#art-resource-config-modal').fadeOut(200);
            } else {
                alert('Error: ' + response.data.message);
            }
        });
    });
    
    /**
     * Close modal
     */
    $('#close-resource-config, #cancel-resource-config, .art-modal-overlay').on('click', function() {
        $('#art-resource-config-modal').fadeOut(200);
    });
});
</script>

