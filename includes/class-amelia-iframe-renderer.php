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

