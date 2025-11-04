/**
 * Amelia Popup Frontend Script
 *
 * Handles dynamic loading of Amelia booking forms into JetPopups
 *
 * @package AmeliaCPTSync
 */

(function($) {
    'use strict';

    var trackedPopups = (ameliaPopupConfig.trackedPopups || []).filter(Boolean);
    var defaultPopup = ameliaPopupConfig.default_popup || '';
    var debugEnabled = !!ameliaPopupConfig.debug_enabled;
    var logNonce = ameliaPopupConfig.log_nonce || '';
    var popupConfigs = ameliaPopupConfig.configs || {};
    var lastTrigger = null;

    function reportDebug(message, context) {
        var payload = context || {};
        console.log('[Amelia Popup] ' + message, payload);

        if (!debugEnabled || !logNonce) {
            return;
        }

        var note = message;
        if (payload && Object.keys(payload).length) {
            try {
                note += ' | ' + JSON.stringify(payload);
            } catch (e) {
                note += ' | [unserializable context]';
            }
        }

        $.post(ameliaPopupConfig.ajax_url, {
            action: 'amelia_cpt_sync_log_debug',
            nonce: logNonce,
            message: '[Popup] ' + note
        });
    }

    function isTrackedPopup(popupId) {
        if (!popupId) {
            return false;
        }

        if (!trackedPopups.length) {
            return true; // No specific popups configured, allow all.
        }

        return trackedPopups.indexOf(popupId) !== -1;
    }

    function resolvePopupId(popupData) {
        if (popupData) {
            if (popupData.popupId) {
                return popupData.popupId;
            }

            if (popupData.popup) {
                var $popup = $(popupData.popup);
                return $popup.data('popup-id') || $popup.attr('data-popup-id') || '';
            }

            if (popupData.data && popupData.data.popupId) {
                return popupData.data.popupId;
            }
        }

        if (lastTrigger && lastTrigger.popupId) {
            var rawPopupId = lastTrigger.popupId;

            // Check if it's a JSON string (from data-jet-popup attribute)
            if (typeof rawPopupId === 'string' && rawPopupId.indexOf('{') === 0) {
                try {
                    var parsed = JSON.parse(rawPopupId);
                    if (parsed['attached-popup']) {
                        return parsed['attached-popup'];
                    }
                } catch (e) {
                    console.warn('[Amelia Popup] Failed to parse popup ID JSON:', e);
                }
            }

            return rawPopupId;
        }

        return defaultPopup || '';
    }

    function getPopupConfig(popupId) {
        if (!popupId || !popupConfigs) {
            return null;
        }

        // Try exact match first
        for (var configId in popupConfigs) {
            if (!popupConfigs.hasOwnProperty(configId)) continue;
            
            var config = popupConfigs[configId];
            
            // Match by slug
            if (config.popup_slug && config.popup_slug === popupId) {
                return config;
            }
            
            // Match by numeric ID
            if (config.popup_numeric_id && 
                (config.popup_numeric_id == popupId || 
                 ('jet-popup-' + config.popup_numeric_id) === popupId)) {
                return config;
            }
        }

        return null;
    }

    function buildCustomizationParams(config) {
        if (!config) {
            return '';
        }

        var params = [];
        
        // Explicitly check for truthy values (handles 1, "1", true, etc.)
        if (config.hide_employees === true || config.hide_employees === 1 || config.hide_employees === '1') {
            params.push('hide_employees=1');
        }
        
        if (config.hide_pricing === true || config.hide_pricing === 1 || config.hide_pricing === '1') {
            params.push('hide_pricing=1');
        }
        
        if (config.hide_extras === true || config.hide_extras === 1 || config.hide_extras === '1') {
            params.push('hide_extras=1');
        }

        return params.length ? '&' + params.join('&') : '';
    }

    function resolveTriggerElement(popupData) {
        if (popupData && popupData.trigger) {
            var $trigger = $(popupData.trigger);
            if ($trigger.length) {
                return $trigger;
            }
        }

        if (lastTrigger && lastTrigger.$el && lastTrigger.$el.length) {
            return lastTrigger.$el;
        }

        return null;
    }

    function decodeHtmlEntities(value) {
        if (!value || (value.indexOf('&') === -1 && value.indexOf('<') === -1)) {
            return value;
        }

        var textarea = document.createElement('textarea');
        textarea.innerHTML = value;
        return textarea.value;
    }

    function normalizePayload(value) {
        if (!value) {
            return '';
        }

        var decoded = decodeHtmlEntities(value).trim();

        if (!decoded) {
            return '';
        }

        // Support [[shortcode]] syntax to escape server-side processing.
        if (decoded.indexOf('[[') !== -1 || decoded.indexOf(']]') !== -1) {
            decoded = decoded.replace(/\[\[/g, '[').replace(/\]\]/g, ']');
        }

        return decoded;
    }

    function resolveShortcode($trigger) {
        if (!$trigger) {
            return '';
        }

        var shortcode = $trigger.attr('data-amelia-shortcode');

        if (shortcode) {
            return normalizePayload(shortcode);
        }

        if (lastTrigger && lastTrigger.shortcode) {
            return normalizePayload(lastTrigger.shortcode);
        }

        return '';
    }

    function setLoadingState($container) {
        $container.html(
            '<div class="amelia-loading">' +
                '<span class="spinner is-active"></span>' +
                '<p>Loading booking form...</p>' +
            '</div>'
        );
    }

    function showError(message) {
        $('#amelia-form-container').html(
            '<div class="amelia-error">' +
                '<p><strong>Error:</strong> ' + message + '</p>' +
                '<p><small>Please contact support if this problem persists.</small></p>' +
            '</div>'
        );
    }

    function reinitializeAmeliaScripts() {
        reportDebug('Re-initializing Amelia scripts - waiting for Vue to detect elements');
        
        // Just trigger events and let Amelia's already-loaded scripts handle it
        $(document).trigger('amelia:loaded');
        $(document).trigger('amelia-booking-loaded');
        window.dispatchEvent(new Event('ameliaFormLoaded'));
    }

    function injectRenderedMarkup(html, popupId) {
        var $container = $('#amelia-form-container');

        if (!$container.length) {
            reportDebug('Container #amelia-form-container not found', {
                available: $('[id*="amelia"]').map(function() { return this.id; }).get()
            });
            return;
        }

        setLoadingState($container);

        reportDebug('Injecting pre-rendered markup', {
            popup: popupId,
            length: html.length
        });

        var $wrapper = $('<div />').html(html);

        $container.empty().append($wrapper.contents());

        // Extract and execute inline scripts (Amelia initialization)
        var scriptsExecuted = 0;
        $container.find('script').each(function() {
            var $script = $(this);
            var scriptContent = $script.html() || $script.text();

            if (scriptContent) {
                reportDebug('Executing inline script', { length: scriptContent.length });
                try {
                    $.globalEval(scriptContent);
                    scriptsExecuted++;
                } catch (e) {
                    console.error('[Amelia Popup] Script execution error:', e);
                }
            }

            if ($script.attr('src')) {
                $.getScript($script.attr('src'));
            }
        });

        reportDebug('Scripts executed', { count: scriptsExecuted });

        // Give Amelia's script time to set up data, then reinitialize
        setTimeout(function() {
            reinitializeAmeliaScripts();
        }, 100);
    }

    function loadAmeliaForm(shortcode, popupId) {
        var $container = $('#amelia-form-container');

        if (!$container.length) {
            reportDebug('Container #amelia-form-container not found', {
                available: $('[id*="amelia"]').map(function() { return this.id; }).get()
            });
            return;
        }

        reportDebug('Loading Amelia form via iframe', {
            popup: popupId,
            shortcode: shortcode
        });

        // Get popup configuration for customizations
        var config = getPopupConfig(popupId);
        var customizationParams = buildCustomizationParams(config);

        reportDebug('Popup customizations', {
            popupId: popupId,
            config: config,
            hide_employees: config ? config.hide_employees : 'no config',
            hide_pricing: config ? config.hide_pricing : 'no config',
            hide_extras: config ? config.hide_extras : 'no config',
            params: customizationParams
        });

        // Build iframe URL with customization parameters
        var iframeUrl = window.location.origin + '/amelia-render/?sc=' + encodeURIComponent(shortcode) + '&iframe_id=amelia-form-container' + customizationParams;

        // Create iframe
        var $iframe = $('<iframe>')
            .attr('id', 'amelia-booking-iframe')
            .attr('src', iframeUrl)
            .css({
                width: '100%',
                border: 'none',
                background: 'transparent',
                display: 'block',
                minHeight: '400px'
            });

        // Replace container content with iframe
        $container.empty().append($iframe);

        reportDebug('Iframe created', { src: iframeUrl });
    }

    function handlePopupPayload(payload, popupId) {
        var trimmed = (payload || '').trim();

        if (!trimmed) {
            reportDebug('Empty shortcode payload after normalization');
            showError('No shortcode configured for this popup trigger.');
            return;
        }

        if (trimmed.charAt(0) === '<' || trimmed.indexOf('<div') !== -1) {
            injectRenderedMarkup(trimmed, popupId);
            return;
        }

        if (trimmed.indexOf('[amelia') !== 0) {
            reportDebug('Payload not recognized as Amelia shortcode', {
                payload: trimmed.substring(0, 80)
            });
            showError('Invalid Amelia shortcode payload.');
            return;
        }

        loadAmeliaForm(trimmed, popupId);
    }

    function handlePopupOpen(event, popupData) {
        var detail = popupData || (event && event.detail ? event.detail : {});

        // JetPopup AJAX response shape
        if (detail && detail.data && detail.data.popupId) {
            detail.popupId = detail.data.popupId;
        }

        reportDebug('JetPopup open event fired', {
            event: event.type,
            detail: detail
        });

        var popupId = resolvePopupId(detail);

        if (!isTrackedPopup(popupId)) {
            reportDebug('Popup not tracked, skipping', { popup: popupId, tracked: trackedPopups });
            return;
        }

        var $trigger = resolveTriggerElement(detail);

        if (!$trigger) {
            reportDebug('No trigger element could be resolved');
            return;
        }

        var shortcode = resolveShortcode($trigger);

        if (!shortcode) {
            reportDebug('Trigger missing data-amelia-shortcode attribute');
            showError('No shortcode configured for this popup trigger.');
            return;
        }

        handlePopupPayload(shortcode, popupId);
    }

    function bindJetPopupEvents() {
        var events = [
            'jet-popup/show-popup',
            'jet-popup/show-event',
            'jet-popup/show-event/after-show',
            'jet-popup/show-event/before-show',
            'jet-popup/after-open',
            'jet-popup/ajax/frontend-init',
            'jet-popup/ajax/frontend-init/after'
        ];

        console.log('[Amelia Popup] Binding to JetPopup events:', events);

        events.forEach(function(evt) {
            document.addEventListener(evt, function(event) {
                console.log('[Amelia Popup] Document event caught:', evt);
                handlePopupOpen(event, event.detail);
            });

            window.addEventListener(evt, function(event) {
                console.log('[Amelia Popup] Window event caught:', evt);
                handlePopupOpen(event, event.detail);
            });

            $(document).on(evt, function(event, data) {
                console.log('[Amelia Popup] jQuery event caught:', evt, data);
                handlePopupOpen(event, data);
            });

            $(window).on(evt, function(event, data) {
                console.log('[Amelia Popup] jQuery window event caught:', evt, data);
                handlePopupOpen(event, data);
            });
        });

        if (window.JetPopupEvents && typeof window.JetPopupEvents.subscribe === 'function') {
            events.forEach(function(evt) {
                window.JetPopupEvents.subscribe(evt, function(data) {
                    console.log('[Amelia Popup] JetPopupEvents bus caught:', evt);
                    handlePopupOpen({ type: evt }, data);
                });
            });
        }
    }

    $(function() {
        reportDebug('Frontend script initialized', {
            tracked: trackedPopups,
            configs: popupConfigs
        });
        
        console.log('[Amelia Popup] Full config object:', popupConfigs);

        // ========== COMPREHENSIVE EVENT LOGGING ==========
        console.log('[Amelia Popup] ========== INSTALLING COMPREHENSIVE EVENT MONITORS ==========');

        // Log ALL events that mention 'popup' or 'jet'
        const logAllEvents = function(e) {
            const type = e.type || '';
            if (type.toLowerCase().indexOf('popup') !== -1 || type.toLowerCase().indexOf('jet') !== -1) {
                console.log('[EVENT MONITOR] ' + type, e);
            }
        };

        // Capture phase for all events
        document.addEventListener('click', logAllEvents, true);
        document.addEventListener('mousedown', logAllEvents, true);
        document.addEventListener('change', logAllEvents, true);

        // Override dispatchEvent to catch custom events
        const originalDispatch = EventTarget.prototype.dispatchEvent;
        EventTarget.prototype.dispatchEvent = function(event) {
            if (event.type && (event.type.indexOf('popup') !== -1 || event.type.indexOf('jet') !== -1 || event.type.indexOf('Jet') !== -1)) {
                console.log('[CUSTOM EVENT DISPATCH] ' + event.type, event);
            }
            return originalDispatch.apply(this, arguments);
        };

        // Monitor jQuery trigger
        const originalTrigger = $.fn.trigger;
        $.fn.trigger = function(type) {
            if (typeof type === 'string' && (type.indexOf('popup') !== -1 || type.indexOf('jet') !== -1)) {
                console.log('[JQUERY TRIGGER] ' + type, this, arguments);
            }
            return originalTrigger.apply(this, arguments);
        };

        // Watch for popup DOM elements
        const domObserver = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1 && node.classList) {
                        const classes = typeof node.className === 'string' ? node.className : (node.className.baseVal || '');
                        if (classes && (classes.indexOf('jet-popup') !== -1 || classes.indexOf('popup') !== -1)) {
                            console.log('[DOM MONITOR] Popup-related element added:', {
                                tag: node.tagName,
                                id: node.id,
                                classes: classes,
                                node: node
                            });
                        }
                    }
                });
            });
        });

        if (document.body) {
            domObserver.observe(document.body, { childList: true, subtree: true });
        }

        // Check for JetPopup globals
        if (window.JetPopupSettings) {
            console.log('[JetPopup] window.JetPopupSettings found:', window.JetPopupSettings);
        } else {
            console.log('[JetPopup] window.JetPopupSettings NOT found');
        }

        if (window.JetPopupEvents) {
            console.log('[JetPopup] window.JetPopupEvents found:', window.JetPopupEvents);
        } else {
            console.log('[JetPopup] window.JetPopupEvents NOT found');
        }

        // Listen to common popup event names
        ['jet-popup-init', 'jet-popup-open', 'jet-popup-close', 'jet-popup-before-open', 'jet-popup-after-open',
         'jetpopup-init', 'jetpopup-open', 'jetpopup-close',
         'popup-open', 'popup-close', 'popup-show', 'popup-hide'].forEach(function(eventName) {
            window.addEventListener(eventName, function(e) {
                console.log('[WINDOW EVENT] ' + eventName, e);
            });
            document.addEventListener(eventName, function(e) {
                console.log('[DOCUMENT EVENT] ' + eventName, e);
            });
        });

        console.log('[Amelia Popup] ========== EVENT MONITORS INSTALLED ==========');
        console.log('[Amelia Popup] Waiting for popup to open...');

        // ========== END COMPREHENSIVE LOGGING ==========

        document.addEventListener('click', function(event) {
            if (!event.target) {
                return;
            }

            var triggerElement = event.target.closest ? event.target.closest('.amelia-booking-trigger') : null;

            if (!triggerElement) {
                return;
            }

            var $button = $(triggerElement);
            var popupIdCapture = $button.attr('data-jet-popup') || '';
            var shortcodeCapture = $button.attr('data-amelia-shortcode') || '';

            lastTrigger = {
                $el: $button,
                popupId: popupIdCapture,
                shortcode: shortcodeCapture.trim()
            };

            $('.amelia-booking-trigger').removeClass('last-clicked');
            $button.addClass('last-clicked');

            reportDebug('Trigger captured (capture phase)', {
                popup: popupIdCapture,
                hasShortcode: !!shortcodeCapture.trim(),
                classes: triggerElement.className
            });
        }, true);

        $(document).on('click', '.amelia-booking-trigger', function() {
            var $button = $(this);
            var popupId = $button.attr('data-jet-popup') || '';
            var shortcode = $button.attr('data-amelia-shortcode') || '';

            lastTrigger = {
                $el: $button,
                popupId: popupId,
                shortcode: shortcode.trim()
            };

            $('.amelia-booking-trigger').removeClass('last-clicked');
            $button.addClass('last-clicked');

            reportDebug('Trigger clicked', {
                popup: popupId,
                hasShortcode: !!shortcode.trim(),
                classes: $button.attr('class')
            });
        });

        bindJetPopupEvents();
        
        // Listen for iframe messages
        window.addEventListener('message', function(event) {
            // Height updates
            if (event.data && event.data.ameliaIframeHeight) {
                var iframeId = event.data.ameliaIframeId || 'amelia-form-container';
                var $iframe = $('#' + iframeId).find('iframe');
                
                if ($iframe.length) {
                    var newHeight = parseInt(event.data.ameliaIframeHeight, 10);
                    $iframe.css('height', newHeight + 'px');
                    reportDebug('Iframe height updated', { height: newHeight });
                }
            }
            
            // Booking completion - close popup
            if (event.data && event.data.ameliaBookingComplete) {
                reportDebug('Finish button clicked, closing popup');
                
                // Find the currently open JetPopup
                var $popup = $('.jet-popup.jet-popup--show-state');
                
                if ($popup.length) {
                    // Trigger JetPopup close
                    $popup.find('.jet-popup__close-button').trigger('click');
                    
                    // Fallback: use JetPopup API if available
                    if (window.jetPopup && typeof window.jetPopup.hidePopup === 'function') {
                        var popupId = $popup.attr('id');
                        window.jetPopup.hidePopup({ popupId: popupId });
                    }
                }
            }
        });
    });
})(jQuery);

