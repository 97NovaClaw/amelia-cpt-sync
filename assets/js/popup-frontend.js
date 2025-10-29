/**
 * Amelia Popup Frontend Script
 *
 * Handles dynamic loading of Amelia booking forms into JetPopups
 *
 * @package AmeliaCPTSync
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        console.log('[Amelia Popup] Frontend script loaded');
        
        /**
         * Listen for popup open events (JetPopup fires these)
         */
        $(document).on('jet-popup/show-event', function(event, popupData) {
            console.log('[Amelia Popup] JetPopup opened:', popupData);
            
            // Check if there's a pending trigger with Amelia data
            var $trigger = $('.amelia-booking-trigger').last();
            
            if ($trigger.length && $trigger.data('amelia-type')) {
                var type = $trigger.data('amelia-type');
                var id = $trigger.data('amelia-id');
                
                console.log('[Amelia Popup] Amelia trigger detected - Type:', type, 'ID:', id);
                
                if (type && id) {
                    loadAmeliaForm(type, id);
                }
            }
        });
        
        /**
         * Also listen for direct clicks on trigger buttons
         */
        $(document).on('click', '.amelia-booking-trigger', function(e) {
            var $button = $(this);
            var type = $button.data('amelia-type');
            var id = $button.data('amelia-id');
            var popupId = $button.data('jet-popup');
            
            console.log('[Amelia Popup] Button clicked - Type:', type, 'ID:', id, 'Popup:', popupId);
            
            // Mark as last clicked for event handler
            $('.amelia-booking-trigger').removeClass('last-clicked');
            $button.addClass('last-clicked');
            
            // If JetPopup doesn't auto-open, open it manually
            if (popupId && typeof JetPopupSettings !== 'undefined') {
                setTimeout(function() {
                    if (!$('.jet-popup').is(':visible')) {
                        console.log('[Amelia Popup] Opening popup manually:', popupId);
                        JetPopupSettings.openPopup(popupId);
                        
                        // Load form after popup opens
                        setTimeout(function() {
                            loadAmeliaForm(type, id);
                        }, 300);
                    }
                }, 100);
            }
        });
        
        /**
         * Load Amelia booking form via AJAX
         */
        function loadAmeliaForm(type, id) {
            console.log('[Amelia Popup] Loading form - Type:', type, 'ID:', id);
            
            var $container = $('#amelia-form-container');
            
            if (!$container.length) {
                console.error('[Amelia Popup] Container #amelia-form-container not found in popup!');
                return;
            }
            
            // Show loading state
            $container.html(
                '<div class="amelia-loading">' +
                '<span class="spinner is-active"></span>' +
                '<p>Loading booking form...</p>' +
                '</div>'
            );
            
            // Make AJAX request
            $.ajax({
                url: ameliaPopupConfig.ajax_url,
                type: 'POST',
                data: {
                    action: 'amelia_render_booking_form',
                    nonce: ameliaPopupConfig.nonce,
                    type: type,
                    id: id
                },
                success: function(response) {
                    console.log('[Amelia Popup] AJAX response:', response);
                    
                    if (response.success && response.data.html) {
                        console.log('[Amelia Popup] Form loaded successfully');
                        
                        // Inject rendered HTML
                        $container.html(response.data.html);
                        
                        // Try to re-initialize Amelia's JavaScript
                        reinitializeAmeliaScripts();
                    } else {
                        console.error('[Amelia Popup] Error:', response.data.message);
                        showError(response.data.message || 'Unable to load booking form');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('[Amelia Popup] AJAX error:', status, error);
                    showError('Connection error. Please try again.');
                }
            });
        }
        
        /**
         * Show error message in popup
         */
        function showError(message) {
            $('#amelia-form-container').html(
                '<div class="amelia-error">' +
                '<p><strong>Error:</strong> ' + message + '</p>' +
                '<p><small>Please contact support if this problem persists.</small></p>' +
                '</div>'
            );
        }
        
        /**
         * Attempt to re-initialize Amelia's JavaScript components
         */
        function reinitializeAmeliaScripts() {
            console.log('[Amelia Popup] Attempting to re-initialize Amelia scripts...');
            
            // Method 1: Global ameliaBooking object
            if (typeof ameliaBooking !== 'undefined' && typeof ameliaBooking.init === 'function') {
                console.log('[Amelia Popup] Re-initializing via ameliaBooking.init()');
                ameliaBooking.init();
            }
            
            // Method 2: Trigger jQuery event
            $(document).trigger('amelia:loaded');
            $(document).trigger('amelia-booking-loaded');
            
            // Method 3: Dispatch native event
            window.dispatchEvent(new Event('ameliaFormLoaded'));
            
            // Method 4: Check for Vue instance and mount
            if (typeof Vue !== 'undefined') {
                console.log('[Amelia Popup] Vue detected - attempting to mount Amelia components');
                // Amelia uses Vue, might need to trigger Vue re-scan
                setTimeout(function() {
                    $('[id^="amelia"]').each(function() {
                        var element = this;
                        if (element.__vue__) {
                            console.log('[Amelia Popup] Found Vue instance, forcing update');
                            element.__vue__.$forceUpdate();
                        }
                    });
                }, 100);
            }
            
            console.log('[Amelia Popup] Re-initialization attempts completed');
        }
    });
    
})(jQuery);

