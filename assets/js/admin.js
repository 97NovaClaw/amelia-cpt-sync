/**
 * Admin JavaScript for Amelia CPT Sync
 *
 * @package AmeliaCPTSync
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Tab switching functionality
        $('.nav-tab').on('click', function(e) {
            e.preventDefault();
            
            var tab = $(this).data('tab');
            
            // Update active tab
            $('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');
            
            // Show corresponding content
            $('.tab-content').removeClass('active');
            $('#tab-' + tab).addClass('active');
            
            // Update URL hash
            if (history.pushState) {
                history.pushState(null, null, '#' + tab);
            } else {
                window.location.hash = tab;
            }
        });
        
        // Load tab from hash on page load
        if (window.location.hash) {
            var hash = window.location.hash.substring(1);
            var $tab = $('.nav-tab[data-tab="' + hash + '"]');
            
            if ($tab.length) {
                $tab.trigger('click');
            }
        }
        
        // Load taxonomies when CPT changes
        $('#cpt_slug').on('change', function() {
            var cptSlug = $(this).val();
            var $taxonomySelect = $('#taxonomy_slug');
            
            if (!cptSlug) {
                $taxonomySelect.html('<option value="">-- Select a Taxonomy --</option>');
                return;
            }
            
            // Show loading state
            $taxonomySelect.prop('disabled', true);
            $taxonomySelect.html('<option value="">Loading taxonomies...</option>');
            
            // Fetch taxonomies via AJAX
            $.ajax({
                url: ameliaCptSync.ajax_url,
                type: 'POST',
                data: {
                    action: 'amelia_cpt_sync_get_taxonomies',
                    nonce: ameliaCptSync.nonce,
                    cpt_slug: cptSlug
                },
                success: function(response) {
                    if (response.success && response.data.taxonomies) {
                        var options = '<option value="">-- Select a Taxonomy --</option>';
                        
                        $.each(response.data.taxonomies, function(index, taxonomy) {
                            options += '<option value="' + taxonomy.slug + '">';
                            options += taxonomy.label + ' (' + taxonomy.slug + ')';
                            options += '</option>';
                        });
                        
                        $taxonomySelect.html(options);
                        
                        if (response.data.taxonomies.length === 0) {
                            $taxonomySelect.html('<option value="">No taxonomies available for this post type</option>');
                        }
                    } else {
                        $taxonomySelect.html('<option value="">Error loading taxonomies</option>');
                        console.error('Error:', response);
                    }
                },
                error: function(xhr, status, error) {
                    $taxonomySelect.html('<option value="">Error loading taxonomies</option>');
                    console.error('AJAX Error:', status, error);
                },
                complete: function() {
                    $taxonomySelect.prop('disabled', false);
                }
            });
        });
        
        // Save settings via AJAX
        $('#save-settings').on('click', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $spinner = $('.spinner');
            var $message = $('#save-message');
            
            // Validate required fields
            var cptSlug = $('#cpt_slug').val();
            
            if (!cptSlug) {
                $message.text('Please select a Custom Post Type').addClass('error');
                return;
            }
            
            // Show loading state
            $button.prop('disabled', true);
            $spinner.addClass('is-active');
            $message.text('').removeClass('success error');
            
            // Gather form data - read all fields regardless of tab visibility
            var formData = {
                action: 'amelia_cpt_sync_save_settings',
                nonce: ameliaCptSync.nonce,
                cpt_slug: cptSlug,
                taxonomy_slug: $('#taxonomy_slug').val(),
                debug_enabled: $('#debug_enabled').is(':checked') ? 'true' : 'false',
                taxonomy_category_id_field: $('#taxonomy_category_id_field').val() || '',
                service_id_field: $('#service_id_field').val() || '',
                category_id_field: $('#category_id_field').val() || '',
                primary_photo_field: $('#primary_photo_field').val() || '',
                price_field: $('#price_field').val() || '',
                duration_field: $('#duration_field').val() || '',
                duration_format: $('#duration_format').val() || 'seconds',
                gallery_field: $('#gallery_field').val() || '',
                extras_field: $('#extras_field').val() || ''
            };
            
            // Debug: Log field values being sent
            console.log('[Save] service_id_field:', formData.service_id_field);
            console.log('[Save] category_id_field:', formData.category_id_field);
            console.log('[Save] primary_photo_field:', formData.primary_photo_field);
            console.log('[Save] taxonomy_category_id_field:', formData.taxonomy_category_id_field);
            
            // Save settings via AJAX
            $.ajax({
                url: ameliaCptSync.ajax_url,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        $message.text(response.data.message).addClass('success');
                        
                        // Warn if verification failed
                        if (response.data.debug && response.data.debug.verified === false) {
                            $message.text('Settings saved but verification failed.').removeClass('success').addClass('error');
                        }
                    } else {
                        var errorMsg = response.data && response.data.message 
                            ? response.data.message 
                            : 'Failed to save settings';
                        $message.text(errorMsg).addClass('error');
                    }
                },
                error: function(xhr, status, error) {
                    $message.text('Error saving settings: ' + error).addClass('error');
                    console.error('AJAX Error:', status, error);
                },
                complete: function() {
                    $button.prop('disabled', false);
                    $spinner.removeClass('is-active');
                    
                    // Clear message after 5 seconds
                    setTimeout(function() {
                        $message.fadeOut(400, function() {
                            $(this).text('').removeClass('success error').css('display', '');
                        });
                    }, 5000);
                }
            });
        });
        
        // Handle Enter key in form fields (prevent form submission)
        $('#amelia-cpt-sync-form').on('keypress', function(e) {
            if (e.which === 13 && e.target.tagName !== 'TEXTAREA') {
                e.preventDefault();
                $('#save-settings').trigger('click');
            }
        });
        
        // Full Sync button handler
        $('#run-full-sync').on('click', function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to sync all Amelia services? This may take some time.')) {
                return;
            }
            
            var $button = $(this);
            var $spinner = $('#sync-spinner');
            var $results = $('#sync-results');
            var $resultsContent = $('#sync-results-content');
            
            // Show loading state
            $button.prop('disabled', true);
            $spinner.addClass('is-active');
            $results.hide();
            $resultsContent.html('');
            
            // Run full sync via AJAX
            $.ajax({
                url: ameliaCptSync.ajax_url,
                type: 'POST',
                data: {
                    action: 'amelia_cpt_sync_full_sync',
                    nonce: ameliaCptSync.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var html = '<div class="notice notice-success" style="padding: 10px; margin-bottom: 10px;">';
                        html += '<p><strong>Sync completed successfully!</strong></p>';
                        html += '<p>Services synced: ' + response.data.synced + '</p>';
                        
                        if (response.data.errors && response.data.errors.length > 0) {
                            html += '<p><strong>Errors:</strong></p><ul>';
                            $.each(response.data.errors, function(index, error) {
                                html += '<li>' + error + '</li>';
                            });
                            html += '</ul>';
                        }
                        
                        html += '</div>';
                        $resultsContent.html(html);
                    } else {
                        var errorMsg = response.data && response.data.message ? response.data.message : 'Sync failed';
                        $resultsContent.html('<div class="notice notice-error" style="padding: 10px;"><p>' + errorMsg + '</p></div>');
                    }
                    
                    $results.show();
                },
                error: function(xhr, status, error) {
                    $resultsContent.html('<div class="notice notice-error" style="padding: 10px;"><p>Error: ' + error + '</p></div>');
                    $results.show();
                    console.error('AJAX Error:', status, error);
                },
                complete: function() {
                    $button.prop('disabled', false);
                    $spinner.removeClass('is-active');
                }
            });
        });
        
        // View Log button handler
        $('#view-log').on('click', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $spinner = $('#log-spinner');
            var $message = $('#log-message');
            var $viewer = $('#log-viewer');
            var $contents = $('#log-contents');
            
            // Show loading state
            $button.prop('disabled', true);
            $spinner.addClass('is-active');
            $message.text('').removeClass('success error');
            
            // Fetch log via AJAX
            $.ajax({
                url: ameliaCptSync.ajax_url,
                type: 'POST',
                data: {
                    action: 'amelia_cpt_sync_view_log',
                    nonce: ameliaCptSync.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $contents.text(response.data.contents);
                        $viewer.show();
                        $message.text('Log size: ' + response.data.size).addClass('success');
                    } else {
                        var errorMsg = response.data && response.data.message ? response.data.message : 'Failed to load log';
                        $message.text(errorMsg).addClass('error');
                    }
                },
                error: function(xhr, status, error) {
                    $message.text('Error loading log: ' + error).addClass('error');
                    console.error('AJAX Error:', status, error);
                },
                complete: function() {
                    $button.prop('disabled', false);
                    $spinner.removeClass('is-active');
                }
            });
        });
        
        // Clear Log button handler
        $('#clear-log').on('click', function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to clear the debug log? This cannot be undone.')) {
                return;
            }
            
            var $button = $(this);
            var $spinner = $('#log-spinner');
            var $message = $('#log-message');
            var $viewer = $('#log-viewer');
            var $contents = $('#log-contents');
            
            // Show loading state
            $button.prop('disabled', true);
            $spinner.addClass('is-active');
            $message.text('').removeClass('success error');
            
            // Clear log via AJAX
            $.ajax({
                url: ameliaCptSync.ajax_url,
                type: 'POST',
                data: {
                    action: 'amelia_cpt_sync_clear_log',
                    nonce: ameliaCptSync.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $contents.text('');
                        $viewer.hide();
                        $message.text(response.data.message).addClass('success');
                        
                        // Clear message after 3 seconds
                        setTimeout(function() {
                            $message.fadeOut(400, function() {
                                $(this).text('').removeClass('success error').css('display', '');
                            });
                        }, 3000);
                    } else {
                        var errorMsg = response.data && response.data.message ? response.data.message : 'Failed to clear log';
                        $message.text(errorMsg).addClass('error');
                    }
                },
                error: function(xhr, status, error) {
                    $message.text('Error clearing log: ' + error).addClass('error');
                    console.error('AJAX Error:', status, error);
                },
                complete: function() {
                    $button.prop('disabled', false);
                    $spinner.removeClass('is-active');
                }
            });
        });
        
        // Add tooltips for better UX (optional enhancement)
        if ($.fn.tooltip) {
            $('.description').tooltip({
                position: { my: 'left top+10', at: 'left bottom' }
            });
        }
    });
    
})(jQuery);

