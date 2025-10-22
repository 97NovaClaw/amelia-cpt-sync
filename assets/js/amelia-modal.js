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
         * Intercept ALL AJAX requests for debugging
         */
        $(document).ajaxSend(function(event, jqxhr, settings) {
            if (settings.url && settings.url.includes('wpamelia_api')) {
                console.log('[Amelia CPT Sync] AJAX SEND:', settings.url);
            }
        });
        
        /**
         * Intercept Amelia AJAX success responses
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
                            
                            console.log('[Amelia CPT Sync] ✅ Service detected:', serviceId, serviceName);
                            console.log('[Amelia CPT Sync] Message type:', response.message);
                            
                            // Only show modal for add/update, not retrieve
                            if (response.message.includes('Successfully added') || 
                                response.message.includes('Successfully updated')) {
                                
                                console.log('[Amelia CPT Sync] Showing modal in 500ms...');
                                
                                // Small delay to let Amelia finish its UI updates
                                setTimeout(function() {
                                    showCustomFieldsPrompt(serviceId, serviceName);
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
        function showCustomFieldsPrompt(serviceId, serviceName) {
            pendingServiceId = serviceId;
            pendingServiceName = serviceName;
            
            // Load custom fields and show modal
            loadCustomFieldsModal(serviceId, serviceName);
        }
        
        /**
         * Load custom fields modal content
         */
        function loadCustomFieldsModal(serviceId, serviceName) {
            console.log('[Amelia CPT Sync] Loading custom fields modal for service:', serviceId);
            
            // Log to server debug.txt as well
            $.post(ameliaCptSyncModal.ajax_url, {
                action: 'amelia_cpt_sync_log_debug',
                nonce: ameliaCptSyncModal.nonce,
                message: 'Modal: Loading custom fields for service ' + serviceId + ' (' + serviceName + ')'
            });
            
            $.ajax({
                url: ameliaCptSyncModal.ajax_url,
                type: 'POST',
                data: {
                    action: 'amelia_cpt_sync_get_custom_fields_modal',
                    nonce: ameliaCptSyncModal.nonce,
                    service_id: serviceId,
                    service_name: serviceName
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
            
            console.log('[Amelia CPT Sync] Saving custom field values:', customFields);
            
            // Disable buttons
            $('.ui-dialog-buttonpane button').prop('disabled', true);
            
            $.ajax({
                url: ameliaCptSyncModal.ajax_url,
                type: 'POST',
                data: {
                    action: 'amelia_cpt_sync_save_custom_field_values',
                    nonce: ameliaCptSyncModal.nonce,
                    service_id: serviceId,
                    custom_fields: customFields
                },
                success: function(response) {
                    if (response.success) {
                        console.log('[Amelia CPT Sync] Custom fields saved successfully');
                        modalDialog.dialog('close');
                        
                        // Show success notice
                        if ($('.amelia-page-header').length) {
                            var notice = $('<div class="notice notice-success is-dismissible" style="margin: 10px 0;"><p><strong>Amelia to CPT Sync:</strong> Custom field details saved successfully!</p></div>');
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
                    alert('Error saving custom field values');
                },
                complete: function() {
                    $('.ui-dialog-buttonpane button').prop('disabled', false);
                }
            });
        }
    });
    
})(jQuery);

