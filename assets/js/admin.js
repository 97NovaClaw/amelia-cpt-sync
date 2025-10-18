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
            
            // Gather form data
            var formData = {
                action: 'amelia_cpt_sync_save_settings',
                nonce: ameliaCptSync.nonce,
                cpt_slug: cptSlug,
                taxonomy_slug: $('#taxonomy_slug').val(),
                primary_photo_field: $('#primary_photo_field').val().trim(),
                price_field: $('#price_field').val().trim(),
                duration_field: $('#duration_field').val().trim(),
                duration_format: $('#duration_format').val(),
                gallery_field: $('#gallery_field').val().trim(),
                extras_field: $('#extras_field').val().trim()
            };
            
            // Save settings via AJAX
            $.ajax({
                url: ameliaCptSync.ajax_url,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        $message.text(response.data.message).addClass('success');
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
        
        // Add tooltips for better UX (optional enhancement)
        if ($.fn.tooltip) {
            $('.description').tooltip({
                position: { my: 'left top+10', at: 'left bottom' }
            });
        }
    });
    
})(jQuery);

