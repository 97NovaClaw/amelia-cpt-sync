<?php
/**
 * Amelia Iframe Renderer
 *
 * Handles rendering Amelia shortcodes in a clean iframe context
 *
 * @package AmeliaCPTSync
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class Amelia_CPT_Sync_Iframe_Renderer {
    
    /**
     * Initialize the renderer
     */
    public function init() {
        add_action('init', array($this, 'add_rewrite_endpoint'));
        add_action('template_redirect', array($this, 'handle_render_request'));
    }
    
    /**
     * Add custom rewrite endpoint
     */
    public function add_rewrite_endpoint() {
        add_rewrite_endpoint('amelia-render', EP_ROOT);
    }
    
    /**
     * Handle render request
     */
    public function handle_render_request() {
        global $wp_query;
        
        if (!isset($wp_query->query_vars['amelia-render'])) {
            return;
        }
        
        // Validate shortcode parameter
        $shortcode = isset($_GET['sc']) ? wp_unslash($_GET['sc']) : '';
        
        if (empty($shortcode)) {
            wp_die('No shortcode provided', 'Invalid Request', array('response' => 400));
        }
        
        // Security: verify it's an Amelia shortcode
        if (stripos($shortcode, '[amelia') !== 0) {
            wp_die('Invalid shortcode', 'Security Error', array('response' => 403));
        }
        
        amelia_cpt_sync_debug_log('[Iframe Renderer] Rendering shortcode: ' . $shortcode);
        
        // Render the bare template
        $this->render_iframe_template($shortcode);
        exit;
    }
    
    /**
     * Render minimal template for iframe
     */
    private function render_iframe_template($shortcode) {
        // Get customization parameters
        $hide_employees = isset($_GET['hide_employees']) && $_GET['hide_employees'] === '1';
        $hide_pricing = isset($_GET['hide_pricing']) && $_GET['hide_pricing'] === '1';
        $hide_extras = isset($_GET['hide_extras']) && $_GET['hide_extras'] === '1';
        
        amelia_cpt_sync_debug_log('[Iframe Renderer] Customizations: hide_employees=' . ($hide_employees ? 'yes' : 'no') . ', hide_pricing=' . ($hide_pricing ? 'yes' : 'no') . ', hide_extras=' . ($hide_extras ? 'yes' : 'no'));
        
        ?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5, user-scalable=yes">
    <meta name="robots" content="noindex,nofollow">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        html, body {
            background: transparent !important;
            overflow-x: hidden;
            min-height: auto !important;
        }
        body {
            padding: 20px;
        }
        /* Hide any theme elements that might leak through */
        #wpadminbar,
        .site-header,
        .site-footer,
        nav,
        aside {
            display: none !important;
        }
        
        <?php if ($hide_employees): ?>
        /* Hide Employee Selection */
        .am-select-employee,
        .am-select-employee-option,
        .am-employee-select,
        .am-employee-option,
        .amelia-employee-selection,
        .el-form-item__label:has-text("Employee"),
        [class*="employee"] .am-select,
        .am-bringing-anyone-employee,
        div[class*="employee"],
        .am-employee,
        .am-step-employee,
        [data-step="employee"],
        .am-bringing-anyone-with-employee {
            display: none !important;
        }
        <?php endif; ?>
        
        <?php if ($hide_pricing): ?>
        /* Hide Pricing Information */
        .am-price,
        .am-service-price,
        .am-total-price,
        .am-payment-total,
        .am-addon-price,
        .am-package-price,
        .am-event-price,
        [class*="price"],
        .am-service-info-price,
        .am-confirmation-booking-price,
        .am-price-per-person,
        .am-total-amount,
        .am-payment-amount {
            display: none !important;
        }
        <?php endif; ?>
        
        <?php if ($hide_extras): ?>
        /* Hide Extras/Add-ons */
        .am-extras,
        .am-extra,
        .am-addon,
        .am-addons,
        .am-service-extras,
        .am-extras-container,
        [data-step="extras"],
        .am-step-extras,
        div[class*="extras"],
        div[class*="addon"] {
            display: none !important;
        }
        <?php endif; ?>
    </style>
    <?php wp_head(); ?>
    <script>
        // Auto-resize iframe to content height (mobile-friendly)
        function notifyParentOfHeight() {
            if (window.parent && window.parent !== window) {
                var height = Math.max(
                    document.body.scrollHeight,
                    document.body.offsetHeight,
                    document.documentElement.clientHeight,
                    document.documentElement.scrollHeight,
                    document.documentElement.offsetHeight
                );
                window.parent.postMessage({
                    ameliaIframeHeight: height,
                    ameliaIframeId: '<?php echo esc_js($_GET['iframe_id'] ?? 'amelia-form-container'); ?>'
                }, '*');
            }
        }

        // Notify on load and when content changes
        window.addEventListener('load', function() {
            notifyParentOfHeight();
            
            // Re-measure after short delay (Amelia might lazy-load content)
            setTimeout(notifyParentOfHeight, 500);
            setTimeout(notifyParentOfHeight, 1000);
            setTimeout(notifyParentOfHeight, 2000);
        });

        // Watch for DOM changes
        if (window.MutationObserver) {
            var resizeTimer;
            var observer = new MutationObserver(function() {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(notifyParentOfHeight, 250);
            });
            
            document.addEventListener('DOMContentLoaded', function() {
                observer.observe(document.body, {
                    attributes: true,
                    childList: true,
                    subtree: true
                });
            });
        }

        // Just log Amelia's hooks for debugging, don't auto-close
        window.ameliaActions = window.ameliaActions || {};
        
        window.ameliaActions.Schedule = function(success, error, data) {
            console.log('[Amelia Iframe] Schedule hook fired', data);
            if (success) success();
        };
        
        window.ameliaActions.Purchased = function(success, error, data) {
            console.log('[Amelia Iframe] Purchased hook fired', data);
            if (success) success();
        };
        
        <?php if ($hide_employees || $hide_pricing || $hide_extras): ?>
        // Apply customizations to dynamically loaded elements
        function applyCustomizations() {
            <?php if ($hide_employees): ?>
            // Hide employee-related elements
            var employeeSelectors = [
                '.am-select-employee',
                '.am-employee-select',
                '.am-employee-option',
                '.am-step-employee',
                '[data-step="employee"]',
                '.am-employee',
                '.am-bringing-anyone-employee'
            ];
            employeeSelectors.forEach(function(selector) {
                var elements = document.querySelectorAll(selector);
                elements.forEach(function(el) {
                    el.style.display = 'none';
                });
            });
            <?php endif; ?>
            
            <?php if ($hide_pricing): ?>
            // Hide pricing elements
            var priceSelectors = [
                '.am-price',
                '.am-service-price',
                '.am-total-price',
                '.am-payment-total',
                '[class*="price"]'
            ];
            priceSelectors.forEach(function(selector) {
                var elements = document.querySelectorAll(selector);
                elements.forEach(function(el) {
                    el.style.display = 'none';
                });
            });
            <?php endif; ?>
            
            <?php if ($hide_extras): ?>
            // Hide extras/addon elements
            var extraSelectors = [
                '.am-extras',
                '.am-extra',
                '.am-addon',
                '.am-addons',
                '[data-step="extras"]',
                '.am-step-extras'
            ];
            extraSelectors.forEach(function(selector) {
                var elements = document.querySelectorAll(selector);
                elements.forEach(function(el) {
                    el.style.display = 'none';
                });
            });
            <?php endif; ?>
            
            console.log('[Amelia Iframe] Customizations applied');
        }
        
        // Apply on load and periodically for dynamically added content
        window.addEventListener('load', function() {
            applyCustomizations();
            setTimeout(applyCustomizations, 500);
            setTimeout(applyCustomizations, 1000);
            setTimeout(applyCustomizations, 2000);
        });
        
        // Watch for DOM changes and reapply
        if (window.MutationObserver) {
            var customizationObserver = new MutationObserver(function() {
                applyCustomizations();
            });
            
            document.addEventListener('DOMContentLoaded', function() {
                customizationObserver.observe(document.body, {
                    childList: true,
                    subtree: true
                });
            });
        }
        <?php endif; ?>
        
        // Intercept Finish button click to close popup
        document.addEventListener('DOMContentLoaded', function() {
            var finishHandled = false;
            
            var checkInterval = setInterval(function() {
                if (finishHandled) return;
                
                // Look for Finish button
                var finishButtons = document.querySelectorAll('.am-button');
                
                finishButtons.forEach(function(button) {
                    var text = button.textContent || button.innerText || '';
                    if (text.toLowerCase().indexOf('finish') !== -1 && !button.dataset.ameliaIntercepted) {
                        button.dataset.ameliaIntercepted = 'true';
                        
                        button.addEventListener('click', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            
                            console.log('[Amelia Iframe] Finish clicked, closing popup');
                            finishHandled = true;
                            
                            if (window.parent && window.parent !== window) {
                                window.parent.postMessage({
                                    ameliaBookingComplete: true
                                }, '*');
                            }
                        }, true); // Capture phase
                        
                        console.log('[Amelia Iframe] Finish button intercepted');
                    }
                });
            }, 500);
            
            // Stop checking after 60 seconds
            setTimeout(function() { clearInterval(checkInterval); }, 60000);
        });
    </script>
</head>
<body class="amelia-iframe-body">
    <?php 
    amelia_cpt_sync_debug_log('[Iframe Renderer] Executing shortcode');
    echo do_shortcode($shortcode); 
    ?>
    <?php wp_footer(); ?>
</body>
</html><?php
    }
}

