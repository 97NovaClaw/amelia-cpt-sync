/**
 * Amelia Modal Interceptor
 *
 * Intercepts Amelia service saves and shows custom fields modal
 *
 * @package AmeliaCPTSync
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        console.log('[Amelia CPT Sync] ========== MODAL SCRIPT LOADED ==========');
        console.log('[Amelia CPT Sync] Current page:', window.location.href);
        console.log('[Amelia CPT Sync] Modal config:', ameliaCptSyncModal);
        
        var modalDialog = null;
        var pendingServiceId = null;
        var pendingServiceName = null;
        
        /**
         * Intercept XMLHttpRequest (Amelia uses this based on network tab showing "XHR")
         */
        var originalXHROpen = XMLHttpRequest.prototype.open;
        var originalXHRSend = XMLHttpRequest.prototype.send;
        
        XMLHttpRequest.prototype.open = function(method, url) {
            this._ameliaURL = url;
            this._ameliaMethod = method;
            return originalXHROpen.apply(this, arguments);
        };
        
        XMLHttpRequest.prototype.send = function() {
            var xhr = this;
            
            if (this._ameliaURL && this._ameliaURL.includes('wpamelia_api')) {
                console.log('[Amelia CPT Sync] XHR SEND:', this._ameliaMethod, this._ameliaURL);
                
                this.addEventListener('load', function() {
                    // Check for service saves
                    if (xhr._ameliaURL.includes('call=/services')) {
                        console.log('[Amelia CPT Sync] XHR Service call completed');
                        
                        try {
                            var response = JSON.parse(xhr.responseText);
                            console.log('[Amelia CPT Sync] Parsed response:', response);
                            
                            if (response.message &&
                                (response.message.includes('Successfully added') ||
                                 response.message.includes('Successfully updated'))) {
                                
                                if (response.data && response.data.service) {
                                    var serviceId = response.data.service.id;
                                    var serviceName = response.data.service.name;
                                    var isNewService = response.message.includes('Successfully added');
                                    
                                    console.log('[Amelia CPT Sync] ✅ Service save detected via XHR!');
                                    console.log('[Amelia CPT Sync] Service ID:', serviceId, 'Name:', serviceName);
                                    console.log('[Amelia CPT Sync] Is New Service:', isNewService);
                                    
                                    setTimeout(function() {
                                        showCustomFieldsPrompt(serviceId, serviceName, isNewService);
                                    }, 500);
                                }
                            }
                        } catch (e) {
                            console.log('[Amelia CPT Sync] Error parsing XHR response:', e);
                        }
                    }
                    
                    // Check for category saves
                    if (xhr._ameliaURL.includes('call=/categories')) {
                        console.log('[Amelia CPT Sync] XHR Category call completed');
                        
                        try {
                            var response = JSON.parse(xhr.responseText);
                            console.log('[Amelia CPT Sync] Parsed category response:', response);
                            
                            if (response.message &&
                                (response.message.includes('Successfully added') ||
                                 response.message.includes('Successfully updated'))) {
                                
                                if (response.data && response.data.category) {
                                    var categoryId = response.data.category.id;
                                    var categoryName = response.data.category.name;
                                    
                                    console.log('[Amelia CPT Sync] ✅ Category save detected via XHR!');
                                    console.log('[Amelia CPT Sync] Category ID:', categoryId, 'Name:', categoryName);
                                    
                                    setTimeout(function() {
                                        showTaxonomyCustomFieldsPrompt(categoryId, categoryName);
                                    }, 500);
                                }
                            }
                        } catch (e) {
                            console.log('[Amelia CPT Sync] Error parsing XHR response:', e);
                        }
                    }
                });
            }
            
            return originalXHRSend.apply(this, arguments);
        };
        
        /**
         * Also try jQuery AJAX intercept (fallback)
         */
        $(document).ajaxSend(function(event, jqxhr, settings) {
            if (settings.url && settings.url.includes('wpamelia_api')) {
                console.log('[Amelia CPT Sync] jQuery AJAX SEND:', settings.url);
            }
        });
        
        /**
         * Intercept Amelia AJAX success responses (jQuery)
         */
        $(document).ajaxSuccess(function(event, xhr, settings) {
            // Log all Amelia API calls
            if (settings.url && settings.url.includes('wpamelia_api')) {
                console.log('[Amelia CPT Sync] AJAX SUCCESS:', settings.url);
                console.log('[Amelia CPT Sync] Response status:', xhr.status);
            }
            
            try {
                // Check if this is an Amelia API call for services
                if (!settings.url || !settings.url.includes('wpamelia_api')) {
                    return;
                }
                
                // Log the URL for debugging
                if (settings.url.includes('call=/services')) {
                    console.log('[Amelia CPT Sync] Service API call detected:', settings.url);
                    console.log('[Amelia CPT Sync] Method:', settings.type);
                }
                
                // Check if it's a service save endpoint (not positions or list)
                var urlMatch = settings.url.match(/call=\/services(\/\d+)?(&|$)/);
                if (!urlMatch) {
                    return;
                }
                
                console.log('[Amelia CPT Sync] Attempting to parse response...');
                
                // Parse response
                var response = JSON.parse(xhr.responseText);
                console.log('[Amelia CPT Sync] Response parsed:', response);
                
                // Check if service was successfully saved
                if (response.message) {
                    console.log('[Amelia CPT Sync] Response message:', response.message);
                    
                    if (response.message.includes('Successfully added') || 
                        response.message.includes('Successfully updated') ||
                        response.message.includes('Successfully retrieved')) {
                        
                        if (response.data && response.data.service) {
                            var serviceId = response.data.service.id;
                            var serviceName = response.data.service.name;
                            var isNewService = response.message.includes('Successfully added');
                            
                            console.log('[Amelia CPT Sync] ✅ Service detected:', serviceId, serviceName);
                            console.log('[Amelia CPT Sync] Message type:', response.message);
                            console.log('[Amelia CPT Sync] Is New Service:', isNewService);
                            
                            // Only show modal for add/update, not retrieve
                            if (response.message.includes('Successfully added') || 
                                response.message.includes('Successfully updated')) {
                                
                                console.log('[Amelia CPT Sync] Showing modal in 500ms...');
                                
                                // Small delay to let Amelia finish its UI updates
                                setTimeout(function() {
                                    showCustomFieldsPrompt(serviceId, serviceName, isNewService);
                                }, 500);
                            }
                        }
                    }
                }
            } catch (e) {
                console.log('[Amelia CPT Sync] Error parsing response:', e);
            }
        });
        
        /**
         * Show prompt to add custom fields
         */
        function showCustomFieldsPrompt(serviceId, serviceName, isNewService) {
            pendingServiceId = serviceId;
            pendingServiceName = serviceName;
            
            // Load custom fields and show modal
            loadCustomFieldsModal(serviceId, serviceName, isNewService);
        }
        
        /**
         * Load custom fields modal content
         */
        function loadCustomFieldsModal(serviceId, serviceName, isNewService) {
            console.log('[Amelia CPT Sync] Loading custom fields modal for service:', serviceId);
            console.log('[Amelia CPT Sync] Is New Service:', isNewService);
            
            // Log to server debug.txt as well
            $.post(ameliaCptSyncModal.ajax_url, {
                action: 'amelia_cpt_sync_log_debug',
                nonce: ameliaCptSyncModal.nonce,
                message: 'Modal: Loading custom fields for service ' + serviceId + ' (' + serviceName + ') - Is New: ' + isNewService
            });
            
            $.ajax({
                url: ameliaCptSyncModal.ajax_url,
                type: 'POST',
                data: {
                    action: 'amelia_cpt_sync_get_custom_fields_modal',
                    nonce: ameliaCptSyncModal.nonce,
                    service_id: serviceId,
                    service_name: serviceName,
                    is_new_service: isNewService ? '1' : '0'
                },
                success: function(response) {
                    console.log('[Amelia CPT Sync] Modal AJAX response:', response);
                    
                    if (response.success) {
                        console.log('[Amelia CPT Sync] Opening modal...');
                        // Populate modal content
                        $('#amelia-cpt-sync-modal-content').html(response.data.html);
                        
                        // Open modal
                        openModal(serviceId);
                    } else {
                        // No custom fields defined or error
                        console.log('[Amelia CPT Sync] No custom fields defined or error:', response.data.message);
                        
                        // Log to server
                        $.post(ameliaCptSyncModal.ajax_url, {
                            action: 'amelia_cpt_sync_log_debug',
                            nonce: ameliaCptSyncModal.nonce,
                            message: 'Modal ERROR: ' + response.data.message
                        });
                        // Don't show modal if no fields are configured
                    }
                },
                error: function(xhr, status, error) {
                    console.error('[Amelia CPT Sync] Error loading modal:', error);
                    
                    // Log to server
                    $.post(ameliaCptSyncModal.ajax_url, {
                        action: 'amelia_cpt_sync_log_debug',
                        nonce: ameliaCptSyncModal.nonce,
                        message: 'Modal AJAX ERROR: ' + error
                    });
                }
            });
        }
        
        /**
         * Open the jQuery UI dialog
         */
        function openModal(serviceId) {
            if (modalDialog) {
                modalDialog.dialog('destroy');
            }
            
            modalDialog = $('#amelia-cpt-sync-custom-fields-modal').dialog({
                modal: true,
                width: 600,
                maxHeight: 600,
                buttons: [
                    {
                        text: 'Save Details',
                        class: 'button button-primary',
                        click: function() {
                            saveCustomFieldValues(serviceId);
                        }
                    },
                    {
                        text: 'Skip for Now',
                        class: 'button button-secondary',
                        click: function() {
                            $(this).dialog('close');
                            // Refresh page to ensure Amelia UI is up to date
                            console.log('[Amelia CPT Sync] Skip clicked, refreshing page...');
                            location.reload();
                        }
                    }
                ],
                close: function() {
                    pendingServiceId = null;
                    pendingServiceName = null;
                }
            });
        }
        
        /**
         * Save custom field values
         */
        function saveCustomFieldValues(serviceId) {
            var customFields = {};
            var resourceConfig = {};
            
            // Gather all custom field values
            $('.amelia-custom-fields-form input[name^="custom_fields"]').each(function() {
                var name = $(this).attr('name');
                var match = name.match(/custom_fields\[([^\]]+)\]/);
                if (match) {
                    var key = match[1];
                    var value = $(this).val();
                    customFields[key] = value;
                }
            });
            
            // Gather resource config based on MODE DROPDOWN (v2.34.0 per-service mode)
            var selectedMode = $('#art-resource-mode').val() || 'none';
            resourceConfig.mode = selectedMode;
            resourceConfig.is_new_service = window.isNewServiceFlag ? '1' : (window.resourceModalState && window.resourceModalState.isNewService ? '1' : '0');
            
            console.log('[Amelia CPT Sync] Selected resource mode: ' + selectedMode);
            
            if (selectedMode === 'mirrored' && window.resourceModalState) {
                // DEDICATED RESOURCE: Collect from state machine
                var stateData = window.resourceModalState.getData();
                resourceConfig.action = stateData.action;
                resourceConfig.resource_id = stateData.resource_id;
                resourceConfig.resource_name = stateData.resource_name;
                resourceConfig.resource_quantity = stateData.resource_quantity;
                resourceConfig.original_resource_id = stateData.original_resource_id;
                resourceConfig.cleanup_orphan = stateData.cleanup_orphan;
                resourceConfig.quantity_required = 1;
                
                console.log('[Amelia CPT Sync] Mirrored config: action=' + stateData.action + ', resource=' + stateData.resource_id);
                
            } else if (selectedMode === 'shared_pool') {
                // RESOURCE POOL: Collect checkboxes + repeater
                resourceConfig.pool_resources = [];
                $('input[name="resource_config[pool_resources][]"]:checked').each(function() {
                    resourceConfig.pool_resources.push($(this).val());
                });
                
                resourceConfig.selection_strategy = $('#selection_strategy').val() || 'first_available';
                resourceConfig.quantity_per_booking = $('#quantity_per_booking').val() || 1;
                
                // Collect repeater data (new resources to create)
                var newPoolResources = {};
                var newResourceCount = 0;
                
                $('#pool-resource-repeater-body tr').each(function() {
                    var $row = $(this);
                    var rowId = $row.data('row-id');
                    var nameInput = $row.find('input[name*="[name]"]');
                    var quantityInput = $row.find('input[name*="[quantity]"]');
                    
                    if (nameInput.length && rowId) {
                        var name = nameInput.val();
                        var quantity = quantityInput.val();
                        
                        if (name && name.trim() !== '') {
                            newPoolResources[rowId] = {
                                name: name.trim(),
                                quantity: parseInt(quantity) || 1
                            };
                            newResourceCount++;
                        }
                    }
                });
                
                if (newResourceCount > 0) {
                    resourceConfig.new_pool_resources = newPoolResources;
                }
                
                console.log('[Amelia CPT Sync] Pool config: ' + resourceConfig.pool_resources.length + ' existing + ' + newResourceCount + ' new, strategy: ' + resourceConfig.selection_strategy);
                
            } else {
                // NONE or other mode: minimal config
                console.log('[Amelia CPT Sync] Mode: ' + selectedMode + ' — no resource data to collect');
            }
            
            console.log('[Amelia CPT Sync] Saving custom field values:', customFields);
            console.log('[Amelia CPT Sync] Saving resource config:', resourceConfig);
            
            // Disable buttons
            $('.ui-dialog-buttonpane button').prop('disabled', true);
            
            $.ajax({
                url: ameliaCptSyncModal.ajax_url,
                type: 'POST',
                data: {
                    action: 'amelia_cpt_sync_save_custom_field_values',
                    nonce: ameliaCptSyncModal.nonce,
                    service_id: serviceId,
                    custom_fields: customFields,
                    resource_config: resourceConfig
                },
                success: function(response) {
                    if (response.success) {
                        console.log('[Amelia CPT Sync] Custom fields saved successfully');
                        modalDialog.dialog('close');
                        
                        // Refresh page to show updated data
                        console.log('[Amelia CPT Sync] Refreshing page...');
                        location.reload();
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                },
                error: function() {
                    alert('Error saving custom field values');
                },
                complete: function() {
                    $('.ui-dialog-buttonpane button').prop('disabled', false);
                }
            });
        }
        /**
         * Show prompt to add taxonomy custom fields
         */
        function showTaxonomyCustomFieldsPrompt(categoryId, categoryName) {
            pendingCategoryId = categoryId;
            pendingCategoryName = categoryName;
            
            // Load custom fields and show modal
            loadTaxonomyCustomFieldsModal(categoryId, categoryName);
        }
        
        /**
         * Load taxonomy custom fields modal content
         */
        function loadTaxonomyCustomFieldsModal(categoryId, categoryName) {
            console.log('[Amelia CPT Sync] Loading taxonomy custom fields modal for category:', categoryId);
            
            // Log to server debug.txt as well
            $.post(ameliaCptSyncModal.ajax_url, {
                action: 'amelia_cpt_sync_log_debug',
                nonce: ameliaCptSyncModal.nonce,
                message: 'Taxonomy Modal: Loading custom fields for category ' + categoryId + ' (' + categoryName + ')'
            });
            
            $.ajax({
                url: ameliaCptSyncModal.ajax_url,
                type: 'POST',
                data: {
                    action: 'amelia_cpt_sync_get_taxonomy_custom_fields_modal',
                    nonce: ameliaCptSyncModal.nonce,
                    category_id: categoryId,
                    category_name: categoryName
                },
                success: function(response) {
                    console.log('[Amelia CPT Sync] Taxonomy modal AJAX response:', response);
                    
                    if (response.success) {
                        console.log('[Amelia CPT Sync] Opening taxonomy modal...');
                        // Populate modal content
                        $('#amelia-cpt-sync-taxonomy-modal-content').html(response.data.html);
                        
                        // Open modal
                        openTaxonomyModal(categoryId);
                    } else {
                        console.log('[Amelia CPT Sync] No taxonomy custom fields defined or error:', response.data.message);
                        
                        // Log to server
                        $.post(ameliaCptSyncModal.ajax_url, {
                            action: 'amelia_cpt_sync_log_debug',
                            nonce: ameliaCptSyncModal.nonce,
                            message: 'Taxonomy Modal ERROR: ' + response.data.message
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('[Amelia CPT Sync] Error loading taxonomy modal:', error);
                    
                    // Log to server
                    $.post(ameliaCptSyncModal.ajax_url, {
                        action: 'amelia_cpt_sync_log_debug',
                        nonce: ameliaCptSyncModal.nonce,
                        message: 'Taxonomy Modal AJAX ERROR: ' + error
                    });
                }
            });
        }
        
        /**
         * Open the taxonomy custom fields jQuery UI dialog
         */
        function openTaxonomyModal(categoryId) {
            if (taxonomyModalDialog) {
                taxonomyModalDialog.dialog('destroy');
            }
            
            taxonomyModalDialog = $('#amelia-cpt-sync-taxonomy-custom-fields-modal').dialog({
                modal: true,
                width: 600,
                maxHeight: 600,
                buttons: [
                    {
                        text: 'Save Details',
                        class: 'button button-primary',
                        click: function() {
                            saveTaxonomyCustomFieldValues(categoryId);
                        }
                    },
                    {
                        text: 'Skip for Now',
                        class: 'button button-secondary',
                        click: function() {
                            $(this).dialog('close');
                        }
                    }
                ],
                close: function() {
                    pendingCategoryId = null;
                    pendingCategoryName = null;
                }
            });
        }
        
        /**
         * Save taxonomy custom field values
         */
        function saveTaxonomyCustomFieldValues(categoryId) {
            var taxonomyCustomFields = {};
            
            // Gather all taxonomy custom field values
            $('.amelia-taxonomy-custom-fields-form input[name^="taxonomy_custom_fields"]').each(function() {
                var name = $(this).attr('name');
                var match = name.match(/taxonomy_custom_fields\[([^\]]+)\]/);
                if (match) {
                    var key = match[1];
                    var value = $(this).val();
                    taxonomyCustomFields[key] = value;
                }
            });
            
            console.log('[Amelia CPT Sync] Saving taxonomy custom field values:', taxonomyCustomFields);
            
            // Disable buttons
            $('.ui-dialog-buttonpane button').prop('disabled', true);
            
            $.ajax({
                url: ameliaCptSyncModal.ajax_url,
                type: 'POST',
                data: {
                    action: 'amelia_cpt_sync_save_taxonomy_custom_field_values',
                    nonce: ameliaCptSyncModal.nonce,
                    category_id: categoryId,
                    taxonomy_custom_fields: taxonomyCustomFields
                },
                success: function(response) {
                    if (response.success) {
                        console.log('[Amelia CPT Sync] Taxonomy custom fields saved successfully');
                        taxonomyModalDialog.dialog('close');
                        
                        // Show success notice
                        if ($('.amelia-page-header').length) {
                            var notice = $('<div class="notice notice-success is-dismissible" style="margin: 10px 0;"><p><strong>Amelia to CPT Sync:</strong> Category custom field details saved successfully!</p></div>');
                            $('.amelia-page-header').after(notice);
                            setTimeout(function() {
                                notice.fadeOut(400, function() { $(this).remove(); });
                            }, 3000);
                        }
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                },
                error: function() {
                    alert('Error saving taxonomy custom field values');
                },
                complete: function() {
                    $('.ui-dialog-buttonpane button').prop('disabled', false);
                }
            });
        }
        
        // Additional variables for taxonomy modal
        var taxonomyModalDialog = null;
        var pendingCategoryId = null;
        var pendingCategoryName = null;
    });
    
})(jQuery);

