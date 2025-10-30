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
        console.log('[Amelia Popup] ========== FRONTEND SCRIPT LOADED ==========');
        console.log('[Amelia Popup] Config:', ameliaPopupConfig);
        console.log('[Amelia Popup] jQuery version:', $.fn.jquery);
        console.log('[Amelia Popup] Looking for buttons with class: amelia-booking-trigger');
        console.log('[Amelia Popup] Found', $('.amelia-booking-trigger').length, 'trigger buttons');
        
        // Debug: Show all trigger buttons on page
        $('.amelia-booking-trigger').each(function(index) {
            console.log('[Amelia Popup] Button', index, ':', {
                ameliaType: $(this).data('amelia-type'),
                ameliaId: $(this).data('amelia-id'),
                jetPopup: $(this).data('jet-popup'),
                element: this
            });
        });
        
        /**
         * Listen for popup open events (JetPopup fires these)
         */
        $(document).on('jet-popup/show-event', function(event, popupData) {
            console.log('[Amelia Popup] ========== JETPOPUP OPENED ==========');
            console.log('[Amelia Popup] Event data:', popupData);
            console.log('[Amelia Popup] Event:', event);
            
            // Check if there's a pending trigger with Amelia data
            var $trigger = $('.amelia-booking-trigger.last-clicked, .amelia-booking-trigger').last();
            
            console.log('[Amelia Popup] Found trigger element:', $trigger.length > 0);
            
            if ($trigger.length && $trigger.data('amelia-type')) {
                var type = $trigger.data('amelia-type');
                var id = $trigger.data('amelia-id');
                
                console.log('[Amelia Popup] Amelia trigger detected - Type:', type, 'ID:', id);
                
                if (type && id) {
                    loadAmeliaForm(type, id);
                } else {
                    console.warn('[Amelia Popup] Missing type or ID');
                }
            } else {
                console.warn('[Amelia Popup] No Amelia trigger found or missing data-amelia-type');
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
            console.log('[Amelia Popup] ========== LOADING FORM ==========');
            console.log('[Amelia Popup] Type:', type);
            console.log('[Amelia Popup] ID:', id);
            console.log('[Amelia Popup] AJAX URL:', ameliaPopupConfig.ajax_url);
            
            var $container = $('#amelia-form-container');
            
            console.log('[Amelia Popup] Container found:', $container.length > 0);
            
            if (!$container.length) {
                console.error('[Amelia Popup] ERROR: Container #amelia-form-container not found in popup!');
                console.error('[Amelia Popup] Available containers:', $('[id*="amelia"]').map(function() { return this.id; }).get());
                return;
            }
            
            // Show loading state
            $container.html(
                '<div class="amelia-loading">' +
                '<span class="spinner is-active"></span>' +
                '<p>Loading booking form...</p>' +
                '</div>'
            );
            
            console.log('[Amelia Popup] Sending AJAX request...');
            
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

