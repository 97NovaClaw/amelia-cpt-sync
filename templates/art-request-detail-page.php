<?php
/**
 * ART Request Detail Page Template
 *
 * Displays full request details with editable booking pillars
 * Design based on ui-mockup-detail-view.html
 *
 * @package AmeliaCPTSync
 * @subpackage ART
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Get request ID from URL
$request_id = isset($_GET['request_id']) ? absint($_GET['request_id']) : 0;

if (!$request_id) {
    wp_die(__('Invalid request ID', 'amelia-cpt-sync'));
}

// Initialize managers
$request_manager = new Amelia_CPT_Sync_ART_Request_Manager();

// Get full request data with all related info
$request = $request_manager->get_request($request_id);

if (!$request) {
    wp_die(__('Request not found', 'amelia-cpt-sync'));
}

// Get main plugin settings for CPT/taxonomy info
$main_settings = get_option('amelia_cpt_sync_settings', array());
$cpt_slug = $main_settings['cpt_slug'] ?? 'vehicles';
$taxonomy_slug = $main_settings['taxonomy_slug'] ?? '';
$service_meta_key = $main_settings['field_mappings']['service_id'] ?? '_amelia_service_id';
$category_meta_key = $main_settings['taxonomy_meta']['category_id'] ?? 'category_id';

// Get ART settings for display options
$art_settings = get_option('art_settings', array());
$global_settings = $art_settings['global'] ?? array();
$show_location = $global_settings['show_location_field'] ?? true;
$show_persons = $global_settings['show_persons_field'] ?? true;
$show_timeslots_grid = $global_settings['show_timeslots_grid'] ?? false; // Off by default

// Get duration settings
$duration_interval_minutes = $global_settings['duration_interval_minutes'] ?? 30;
$duration_max_hours = $global_settings['duration_max_hours'] ?? 12;

// Get user's calendar zoom preference
$user_calendar_zoom = get_user_meta(get_current_user_id(), 'art_calendar_zoom', true);
$user_calendar_zoom = $user_calendar_zoom ? intval($user_calendar_zoom) : 100;

// Generate duration dropdown options
$duration_options = array();
$max_seconds = $duration_max_hours * 3600;
for ($seconds = $duration_interval_minutes * 60; $seconds <= $max_seconds; $seconds += ($duration_interval_minutes * 60)) {
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $duration_options[] = array(
        'seconds' => $seconds,
        'display' => sprintf('%02d:%02d', $hours, $minutes)
    );
}

// Format dates for display
$submitted_date = get_date_from_gmt($request->created_at);
$submitted_display = date_i18n('M j, Y \a\t g:i A', strtotime($submitted_date));

// Format start/end for datetime-local inputs (if they exist)
$start_datetime_value = '';
if (!empty($request->start_datetime)) {
    $start_local = get_date_from_gmt($request->start_datetime);
    $start_datetime_value = date('Y-m-d\TH:i', strtotime($start_local));
}

$end_datetime_value = '';
if (!empty($request->end_datetime)) {
    $end_local = get_date_from_gmt($request->end_datetime);
    $end_datetime_value = date('Y-m-d\TH:i', strtotime($end_local));
}

// Format follow-up date
$follow_up_value = '';
if (!empty($request->follow_up_by)) {
    $follow_up_local = get_date_from_gmt($request->follow_up_by);
    $follow_up_value = date('Y-m-d', strtotime($follow_up_local));
}

// Format duration for display
$duration_display = '';
if ($request->duration_seconds > 0) {
    $minutes = floor($request->duration_seconds / 60);
    $hours = floor($minutes / 60);
    $remaining_mins = $minutes % 60;
    
    if ($hours > 0) {
        $duration_display = $hours . 'h ' . $remaining_mins . 'm';
    } else {
        $duration_display = $minutes . ' minutes';
    }
}

// Get category dropdown options (from taxonomy)
$category_options = array();
if (!empty($taxonomy_slug)) {
    $terms = get_terms(array(
        'taxonomy' => $taxonomy_slug,
        'hide_empty' => false,
        'orderby' => 'name',
        'order' => 'ASC'
    ));
    
    if (!is_wp_error($terms)) {
        foreach ($terms as $term) {
            $amelia_category_id = get_term_meta($term->term_id, $category_meta_key, true);
            if ($amelia_category_id) {
                $category_options[] = array(
                    'id' => $amelia_category_id,
                    'name' => $term->name
                );
            }
        }
    }
}

// Get service dropdown options (from linked CPT)
$service_options = array();
$services = get_posts(array(
    'post_type' => $cpt_slug,
    'posts_per_page' => -1,
    'meta_key' => $service_meta_key,
    'orderby' => 'title',
    'order' => 'ASC'
));

foreach ($services as $service_post) {
    $amelia_service_id = get_post_meta($service_post->ID, $service_meta_key, true);
    $service_category_id = get_post_meta($service_post->ID, 'category_id', true);
    
    if ($amelia_service_id) {
        $service_options[] = array(
            'id' => $amelia_service_id,
            'name' => $service_post->post_title,
            'category_id' => $service_category_id
        );
    }
}

// Customer name
$customer_name = trim($request->customer_first_name . ' ' . $request->customer_last_name);
if (empty($customer_name) || $customer_name === ' ') {
    $customer_name = 'Unknown Customer';
}

// Status display
$status_display = ucfirst($request->status_key ?? 'requested');

// Available statuses
$available_statuses = array('Requested', 'Responded', 'Tentative', 'Booked', 'Abandoned');
?>

<div class="wrap art-detail-view">
    <!-- Sticky Header -->
    <header class="art-detail-header">
        <div class="header-left">
            <a href="<?php echo esc_url(admin_url('admin.php?page=art-workbench')); ?>" class="back-link">
                <span class="dashicons dashicons-arrow-left-alt2"></span>
                <?php _e('Back to List', 'amelia-cpt-sync'); ?>
            </a>
            <h1 class="page-title">
                <?php printf(__('Triage Request #%d', 'amelia-cpt-sync'), $request_id); ?>
            </h1>
        </div>
        
        <div class="header-actions">
            <!-- Status Dropdown -->
            <div class="header-field">
                <label for="status-dropdown" class="header-label">
                    <?php _e('Status:', 'amelia-cpt-sync'); ?>
                </label>
                <select id="status-dropdown" class="header-select" data-request-id="<?php echo $request_id; ?>">
                    <?php foreach ($available_statuses as $status): ?>
                        <option value="<?php echo esc_attr($status); ?>" 
                                <?php selected($status_display, $status); ?>>
                            <?php echo esc_html($status); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Follow-Up Date -->
            <div class="header-field">
                <label for="follow-up-date" class="header-label">
                    <?php _e('Follow-up By:', 'amelia-cpt-sync'); ?>
                </label>
                <input type="date" 
                       id="follow-up-date" 
                       class="header-date" 
                       value="<?php echo esc_attr($follow_up_value); ?>"
                       data-request-id="<?php echo $request_id; ?>">
            </div>
        </div>
    </header>
    
    <!-- Main Content Area -->
    <div class="art-detail-container">
        <div class="art-grid-container">
            <!-- Left/Main Column: Booking Pillars (2/3 width) -->
            <div class="art-main-column">
                
                <?php 
                // Check if there's an active Amelia booking
                $active_booking = null;
                if (!empty($request->bookings) && is_array($request->bookings)) {
                    $potential_booking = $request->bookings[0]; // Most recent booking
                    
                    // Verify appointment still exists in Amelia (prevents orphaned links)
                    if ($potential_booking->amelia_appointment_id) {
                        global $wpdb;
                        $appointment_exists = $wpdb->get_var($wpdb->prepare(
                            "SELECT id FROM {$wpdb->prefix}amelia_appointments WHERE id = %d",
                            $potential_booking->amelia_appointment_id
                        ));
                        
                        if ($appointment_exists) {
                            $active_booking = $potential_booking;
                            
                            // Fetch full appointment details from Amelia
                            $appointment_full = $wpdb->get_row($wpdb->prepare(
                                "SELECT a.*, s.name as service_name, s.categoryId as category_id
                                 FROM {$wpdb->prefix}amelia_appointments a
                                 LEFT JOIN {$wpdb->prefix}amelia_services s ON a.serviceId = s.id
                                 WHERE a.id = %d",
                                $active_booking->amelia_appointment_id
                            ));
                            
                            // Get provider name
                            $provider = $wpdb->get_row($wpdb->prepare(
                                "SELECT firstName, lastName FROM {$wpdb->prefix}amelia_users WHERE id = %d AND type = 'provider'",
                                $appointment_full->providerId
                            ));
                            
                            // Get location name if set
                            $location_name = '';
                            if ($appointment_full->locationId) {
                                $location = $wpdb->get_row($wpdb->prepare(
                                    "SELECT name FROM {$wpdb->prefix}amelia_locations WHERE id = %d",
                                    $appointment_full->locationId
                                ));
                                $location_name = $location ? $location->name : '';
                            }
                            
                            // Get category name if available
                            $category_name = '';
                            if ($appointment_full->category_id) {
                                $category = $wpdb->get_row($wpdb->prepare(
                                    "SELECT name FROM {$wpdb->prefix}amelia_categories WHERE id = %d",
                                    $appointment_full->category_id
                                ));
                                $category_name = $category ? $category->name : '';
                            }
                            
                            // Attach full details to active_booking object
                            $active_booking->service_name = $appointment_full->service_name ?? '';
                            $active_booking->category_name = $category_name;
                            $active_booking->provider_name = $provider ? trim($provider->firstName . ' ' . $provider->lastName) : '';
                            $active_booking->location_name = $location_name;
                            
                            // Convert UTC booking times to local timezone for display
                            $wp_tz = wp_timezone();
                            $utc_tz = new DateTimeZone('UTC');
                            $dt_start = new DateTime($appointment_full->bookingStart, $utc_tz);
                            $dt_start->setTimezone($wp_tz);
                            
                            $active_booking->formatted_date = $dt_start->format('l, F jS, Y');
                            $active_booking->formatted_time = $dt_start->format('h:i A');
                            
                            amelia_cpt_sync_debug_log('ART Detail Page: Active booking loaded with full details');
                        } else {
                            // Orphaned link - clean it up
                            amelia_cpt_sync_debug_log('ART Detail Page: Cleaning up orphaned booking link (appointment #' . $potential_booking->amelia_appointment_id . ' no longer exists)');
                            $wpdb->delete(
                                $wpdb->prefix . 'art_booking_links',
                                array('id' => $potential_booking->id),
                                array('%d')
                            );
                            
                            // Reset request status to tentative
                            $wpdb->update(
                                $wpdb->prefix . 'art_requests',
                                array('status_key' => 'tentative'),
                                array('id' => $request_id),
                                array('%s'),
                                array('%d')
                            );
                        }
                    }
                } else {
                    amelia_cpt_sync_debug_log('ART Detail Page: No booking links found for request #' . $request_id);
                }
                ?>
                
                <!-- Active Booking Info Card (shown when booked) -->
                <div id="active-booking-card" class="art-card art-booking-card" style="<?php echo $active_booking ? '' : 'display: none;'; ?>">
                    <div class="card-header booking-header">
                        <h3>
                            <span class="dashicons dashicons-calendar-alt"></span>
                            <?php _e('Active Amelia Booking', 'amelia-cpt-sync'); ?>
                        </h3>
                        <span class="booking-status-badge"><?php _e('Confirmed', 'amelia-cpt-sync'); ?></span>
                    </div>
                    <div class="card-body">
                        <div id="active-booking-details" class="booking-details-grid">
                            <?php if ($active_booking): ?>
                            <div class="booking-detail-item">
                                <span class="detail-label"><?php _e('Booking ID', 'amelia-cpt-sync'); ?></span>
                                <span class="detail-value">#<?php echo esc_html($active_booking->amelia_booking_id); ?></span>
                            </div>
                            <div class="booking-detail-item">
                                <span class="detail-label"><?php _e('Appointment ID', 'amelia-cpt-sync'); ?></span>
                                <span class="detail-value">#<?php echo esc_html($active_booking->amelia_appointment_id); ?></span>
                            </div>
                            <?php if (!empty($active_booking->service_name)): ?>
                            <div class="booking-detail-item">
                                <span class="detail-label"><?php _e('Service', 'amelia-cpt-sync'); ?></span>
                                <span class="detail-value"><?php echo esc_html($active_booking->service_name); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($active_booking->category_name)): ?>
                            <div class="booking-detail-item">
                                <span class="detail-label"><?php _e('Category', 'amelia-cpt-sync'); ?></span>
                                <span class="detail-value"><?php echo esc_html($active_booking->category_name); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($active_booking->formatted_date)): ?>
                            <div class="booking-detail-item">
                                <span class="detail-label"><?php _e('Date', 'amelia-cpt-sync'); ?></span>
                                <span class="detail-value"><?php echo esc_html($active_booking->formatted_date); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($active_booking->formatted_time)): ?>
                            <div class="booking-detail-item">
                                <span class="detail-label"><?php _e('Time', 'amelia-cpt-sync'); ?></span>
                                <span class="detail-value"><?php echo esc_html($active_booking->formatted_time); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($active_booking->provider_name)): ?>
                            <div class="booking-detail-item">
                                <span class="detail-label"><?php _e('Provider', 'amelia-cpt-sync'); ?></span>
                                <span class="detail-value"><?php echo esc_html($active_booking->provider_name); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($active_booking->location_name)): ?>
                            <div class="booking-detail-item">
                                <span class="detail-label"><?php _e('Location', 'amelia-cpt-sync'); ?></span>
                                <span class="detail-value"><?php echo esc_html($active_booking->location_name); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <form id="booking-pillars-form" data-request-id="<?php echo $request_id; ?>">
                    <!-- Card 1: Core Pillars -->
                    <div class="art-card">
                        <div class="card-header">
                            <h3><?php _e('Core Pillars', 'amelia-cpt-sync'); ?></h3>
                        </div>
                        <div class="card-body">
                            <div class="pillar-grid-3">
                                <!-- Category -->
                                <div class="form-field">
                                    <label for="pillar-category">
                                        <?php _e('Category', 'amelia-cpt-sync'); ?>
                                    </label>
                                    <select id="pillar-category" name="category_id" class="form-select">
                                        <option value=""><?php _e('Select Category', 'amelia-cpt-sync'); ?></option>
                                        <?php foreach ($category_options as $category): ?>
                                            <option value="<?php echo esc_attr($category['id']); ?>"
                                                    <?php selected($request->category_id, $category['id']); ?>>
                                                <?php echo esc_html($category['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <!-- Service -->
                                <div class="form-field">
                                    <label for="pillar-service">
                                        <?php _e('Service', 'amelia-cpt-sync'); ?>
                                    </label>
                                    <select id="pillar-service" name="service_id" class="form-select">
                                        <option value=""><?php _e('Select Service', 'amelia-cpt-sync'); ?></option>
                                        <?php foreach ($service_options as $service): ?>
                                            <option value="<?php echo esc_attr($service['id']); ?>"
                                                    data-category-id="<?php echo esc_attr($service['category_id']); ?>"
                                                    <?php selected($request->service_id, $service['id']); ?>>
                                                <?php echo esc_html($service['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <!-- Location (Conditional display) -->
                                <?php if ($show_location): ?>
                                    <div class="form-field">
                                        <label for="pillar-location">
                                            <?php _e('Location', 'amelia-cpt-sync'); ?>
                                        </label>
                                        <select id="pillar-location" name="location_id" class="form-select">
                                            <option value=""><?php _e('Loading...', 'amelia-cpt-sync'); ?></option>
                                        </select>
                                        <p class="field-note"><?php _e('Optional - leave blank if not needed', 'amelia-cpt-sync'); ?></p>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Persons (Conditional display) -->
                                <?php if ($show_persons): ?>
                                    <div class="form-field">
                                        <label for="pillar-persons">
                                            <?php _e('Persons', 'amelia-cpt-sync'); ?>
                                        </label>
                                        <select id="pillar-persons" name="persons" class="form-select">
                                            <?php for ($i = 1; $i <= 10; $i++): ?>
                                                <option value="<?php echo $i; ?>" 
                                                        <?php selected($request->persons, $i); ?>>
                                                    <?php echo $i; ?> <?php echo $i === 1 ? __('Person', 'amelia-cpt-sync') : __('Persons', 'amelia-cpt-sync'); ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Card 2: Intake Details -->
                    <?php if (!empty($request->intake_fields)): ?>
                        <div class="art-card">
                            <div class="card-header">
                                <h3><?php _e('Intake Details', 'amelia-cpt-sync'); ?></h3>
                                <span class="badge-info"><?php echo count($request->intake_fields); ?> <?php _e('fields', 'amelia-cpt-sync'); ?></span>
                            </div>
                            <div class="card-body">
                                <div class="intake-fields-list">
                                    <?php foreach ($request->intake_fields as $field): 
                                        $field_value = $field->field_value;
                                        $is_long_text = strlen($field_value) > 100;
                                    ?>
                                        <div class="intake-field-item <?php echo $is_long_text ? 'long-text' : ''; ?>">
                                            <dt class="intake-label"><?php echo esc_html($field->field_label); ?></dt>
                                            <dd class="intake-value">
                                                <?php 
                                                // Format based on content
                                                // Preserve paragraphs, line breaks, and basic HTML
                                                echo wp_kses_post(wpautop($field_value)); 
                                                ?>
                                            </dd>
                                            
                                            <?php 
                                            // TODO: Gallery support - check if field_value contains attachment IDs
                                            // If field type is 'media-field' or contains comma-separated IDs
                                            // Display as thumbnail grid
                                            ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Card 3: Time & Duration -->
                    <div class="art-card">
                        <div class="card-header">
                            <h3><?php _e('Time & Duration', 'amelia-cpt-sync'); ?></h3>
                            <span id="service-duration-display" class="service-duration-badge" style="display: none;">
                                <button type="button" class="refresh-icon" title="Refresh service duration">↻</button>
                                <span class="duration-text"></span>
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="pillar-grid-3">
                                <!-- Start Time -->
                                <div class="form-field">
                                    <label for="pillar-start">
                                        <?php _e('Start Time', 'amelia-cpt-sync'); ?>
                                    </label>
                                    <input type="datetime-local" 
                                           id="pillar-start" 
                                           name="start_datetime" 
                                           class="form-input"
                                           value="<?php echo esc_attr($start_datetime_value); ?>">
                                </div>
                                
                                <!-- End Time OR Duration Selector -->
                                <div class="form-field">
                                    <label for="pillar-end">
                                        <?php _e('End Time', 'amelia-cpt-sync'); ?>
                                    </label>
                                    <input type="datetime-local" 
                                           id="pillar-end" 
                                           name="end_datetime" 
                                           class="form-input"
                                           value="<?php echo esc_attr($end_datetime_value); ?>">
                                    <p class="field-note"><?php _e('Or use duration selector below', 'amelia-cpt-sync'); ?></p>
                                </div>
                                
                                <!-- Duration Selector (Dynamic from settings) -->
                                <div class="form-field">
                                    <label for="pillar-duration-selector">
                                        <?php _e('Duration (HH:MM)', 'amelia-cpt-sync'); ?>
                                    </label>
                                    <select id="pillar-duration-selector" class="form-select art-duration-select">
                                        <option value=""><?php _e('Select duration...', 'amelia-cpt-sync'); ?></option>
                                        <?php foreach ($duration_options as $option): ?>
                                            <option value="<?php echo esc_attr($option['seconds']); ?>"
                                                    <?php selected($request->duration_seconds, $option['seconds']); ?>>
                                                <?php echo esc_html($option['display']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="field-note"><?php _e('Selecting duration calculates end time, or enter custom HH:MM', 'amelia-cpt-sync'); ?></p>
                                    <input type="hidden" 
                                           id="pillar-duration-seconds" 
                                           name="duration_seconds" 
                                           value="<?php echo esc_attr($request->duration_seconds); ?>">
                                </div>
                            </div>
                            
                            <!-- Duration Display (calculated) -->
                            <div class="duration-summary">
                                <span class="duration-icon">⏱️</span>
                                <span class="duration-text">
                                    <?php _e('Calculated Duration:', 'amelia-cpt-sync'); ?> 
                                    <strong id="duration-display"><?php echo esc_html($duration_display ?: 'Not set'); ?></strong>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Card 4: Quote -->
                    <div class="art-card">
                        <div class="card-header">
                            <h3><?php _e('Quote', 'amelia-cpt-sync'); ?></h3>
                        </div>
                        <div class="card-body">
                            <div class="pillar-grid-1">
                                <!-- Price -->
                                <div class="form-field price-field">
                                    <label for="pillar-price">
                                        <?php _e('Price', 'amelia-cpt-sync'); ?>
                                    </label>
                                    <div class="price-input-wrap">
                                        <span class="price-symbol">$</span>
                                        <input type="number" 
                                               id="pillar-price" 
                                               name="final_price" 
                                               class="form-input price-input" 
                                               step="0.01" 
                                               min="0"
                                               value="<?php echo esc_attr($request->final_price ?? ''); ?>"
                                               placeholder="0.00">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Save Button -->
                    <div class="form-actions">
                        <button type="submit" class="btn-primary btn-large">
                            <span class="dashicons dashicons-saved"></span>
                            <?php _e('Save Draft', 'amelia-cpt-sync'); ?>
                        </button>
                        <span class="save-indicator" style="display: none;"></span>
                    </div>
                </form>
                
                <!-- Panel 3: Availability & Booking Engine -->
                <div class="art-card">
                    <div class="card-header">
                        <h3><?php _e('Availability & Booking', 'amelia-cpt-sync'); ?></h3>
                        <span class="badge-phase5"><?php _e('Phase 5', 'amelia-cpt-sync'); ?></span>
                    </div>
                    <div class="card-body">
                        
                        <!-- Visual Calendar Iframe (Auto-loaded) -->
                        <div id="calendar-visual-container" class="calendar-container" data-zoom="<?php echo esc_attr($user_calendar_zoom); ?>">
                            <div class="calendar-toolbar">
                                <strong><?php _e('Amelia Calendar Reference', 'amelia-cpt-sync'); ?></strong>
                                <div class="calendar-controls">
                                    <!-- Zoom Controls -->
                                    <div class="zoom-controls">
                                        <button type="button" id="btn-zoom-out" class="btn-icon" title="<?php esc_attr_e('Zoom Out', 'amelia-cpt-sync'); ?>">
                                            <span class="dashicons dashicons-minus"></span>
                                        </button>
                                        <span id="zoom-level"><?php echo esc_html($user_calendar_zoom); ?>%</span>
                                        <button type="button" id="btn-zoom-in" class="btn-icon" title="<?php esc_attr_e('Zoom In', 'amelia-cpt-sync'); ?>">
                                            <span class="dashicons dashicons-plus"></span>
                                        </button>
                                        <button type="button" id="btn-zoom-reset" class="btn-icon" title="<?php esc_attr_e('Reset Zoom', 'amelia-cpt-sync'); ?>">
                                            <span class="dashicons dashicons-image-rotate"></span>
                                        </button>
                                    </div>
                                    
                                    <!-- Locked Expand Button -->
                                    <button type="button" id="btn-expand-locked" class="btn-icon" title="<?php esc_attr_e('Expand (Locked)', 'amelia-cpt-sync'); ?>">
                                        <span class="dashicons dashicons-editor-expand"></span>
                                        <span class="lock-icon dashicons dashicons-lock"></span>
                                    </button>
                                    
                                    <!-- Unlocked/Draggable Mode Button -->
                                    <button type="button" id="btn-expand-unlocked" class="btn-icon" title="<?php esc_attr_e('Floating Window Mode', 'amelia-cpt-sync'); ?>">
                                        <span class="dashicons dashicons-move"></span>
                                    </button>
                                    
                                    <!-- Hide Button -->
                                    <button type="button" id="btn-close-calendar" class="btn-icon btn-close" title="<?php esc_attr_e('Hide Calendar', 'amelia-cpt-sync'); ?>">
                                        <span class="dashicons dashicons-no-alt"></span>
                                    </button>
                                </div>
                            </div>
                            <div class="calendar-iframe-wrapper">
                                <iframe id="amelia-calendar-frame" src="<?php echo admin_url('admin.php?page=wpamelia-calendar'); ?>" style="transform-origin: 0 0; transform: scale(<?php echo $user_calendar_zoom / 100; ?>);"></iframe>
                            </div>
                        </div>

                        <!-- Step 1: Check Availability Button -->
                        <div id="availability-check-section">
                            <p class="help-text">
                                <?php _e('Check available time slots in Amelia based on the service, duration, and location above.', 'amelia-cpt-sync'); ?>
                            </p>
                            
                            <div class="availability-actions" style="display: flex; gap: 10px; align-items: center;">
                                <button type="button" id="btn-check-availability" class="btn-primary">
                                    <span class="dashicons dashicons-calendar-alt"></span>
                                    <?php _e('Check Availability', 'amelia-cpt-sync'); ?>
                                </button>
                                
                                <button type="button" id="btn-toggle-calendar" class="btn-secondary" style="display: none;">
                                    <span class="dashicons dashicons-visibility"></span>
                                    <?php _e('Show Calendar', 'amelia-cpt-sync'); ?>
                                </button>
                            </div>
                            
                            <div id="availability-status" style="margin-top: 12px; display: none;"></div>
                        </div>
                        
                        <!-- Step 2: Available Slots Display (hidden until check completes) -->
                        <div id="availability-results" style="display: none; margin-top: 20px;">
                            
                            <!-- Provider Filter (Phase 5 Enhancement) -->
                            <div class="provider-filter-section" style="margin-bottom: 16px; padding-bottom: 16px; border-bottom: 1px solid #E0E5F1;">
                                <label for="filter-provider" style="font-weight: 600; color: #2C3E50; display: block; margin-bottom: 8px;">
                                    <?php _e('Filter by Employee:', 'amelia-cpt-sync'); ?>
                                </label>
                                <select id="filter-provider" class="form-select" style="max-width: 300px;">
                                    <option value="all"><?php _e('All Available Employees', 'amelia-cpt-sync'); ?></option>
                                </select>
                            </div>

                            <h4 style="margin-bottom: 12px; color: #2C3E50;">
                                <?php _e('Select Date & Time', 'amelia-cpt-sync'); ?>
                                <span id="slot-count-badge" class="badge-info" style="margin-left: 8px;"></span>
                            </h4>
                            
                            <!-- Date & Time Picker Container -->
                            <div class="art-picker-container <?php echo $show_timeslots_grid ? 'with-timegrid' : 'no-timegrid'; ?>">
                                <!-- Column 1: Dates -->
                                <div class="art-picker-dates" id="picker-dates-list">
                                    <!-- Dates will be injected here -->
                                    <div style="padding: 20px; text-align: center; color: #94A3B8;">
                                        Loading dates...
                                    </div>
                                </div>
                                
                                <?php if ($show_timeslots_grid): ?>
                                <!-- Column 2: Times (optional - controlled by setting) -->
                                <div class="art-picker-times">
                                    <div class="art-picker-times-header" id="picker-times-header">
                                        Select a date to see times
                                    </div>
                                    <div class="art-time-grid" id="picker-times-grid">
                                        <!-- Times will be injected here -->
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Column: Time Entry -->
                                <div class="art-picker-time-entry">
                                    <div class="art-picker-column-header">
                                        <?php _e('Select Time', 'amelia-cpt-sync'); ?>
                                    </div>
                                    <div class="art-picker-column-content">
                                        <div class="time-entry-field">
                                            <label for="custom-time-input"><?php _e('Enter Time', 'amelia-cpt-sync'); ?></label>
                                            <input type="time" id="custom-time-input" class="form-input" disabled>
                                            <p class="field-hint"><?php _e('Reference the calendar above', 'amelia-cpt-sync'); ?></p>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Column: Provider Selection -->
                                <div class="art-picker-providers">
                                    <div class="art-picker-column-header">
                                        <?php _e('Select Provider', 'amelia-cpt-sync'); ?>
                                    </div>
                                    <div class="art-provider-list" id="provider-list">
                                        <div class="provider-placeholder">
                                            <?php _e('Select a date and time first', 'amelia-cpt-sync'); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Confirm Selection Button -->
                            <div class="art-picker-confirm" id="picker-confirm-section" style="display: none;">
                                <button type="button" id="btn-use-custom-time" class="btn-primary" disabled>
                                    <span class="dashicons dashicons-yes-alt"></span>
                                    <?php _e('Confirm Selection', 'amelia-cpt-sync'); ?>
                                </button>
                            </div>
                            
                            <!-- Hidden inputs to store selection for booking logic -->
                            <input type="hidden" id="selected-slot-datetime" value="">
                            <input type="hidden" id="selected-provider-id" value="">
                            <input type="hidden" id="selected-location-id" value="">
                            
                            <div id="slot-details" style="margin-top: 16px; padding: 12px; background: #E8F4F8; border-left: 4px solid #1A84EE; border-radius: 4px; display: none;">
                                <strong><?php _e('Booking Summary:', 'amelia-cpt-sync'); ?></strong>
                                <div id="slot-summary" style="margin-top: 8px; line-height: 1.6;"></div>
                            </div>
                            
                            <!-- Step 3: Create Booking Actions -->
                            <div class="placeholder-actions" style="margin-top: 20px;">
                                <button type="button" id="btn-create-booking" class="btn-primary" disabled>
                                    <span class="dashicons dashicons-yes-alt"></span>
                                    <?php _e('Create Amelia Booking', 'amelia-cpt-sync'); ?>
                                </button>
                                <button type="button" id="btn-cancel-availability" class="btn-link">
                                    <?php _e('Clear Results', 'amelia-cpt-sync'); ?>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Booking Success Toast (temporary notification) -->
                        <div id="booking-success-toast" style="display: none; margin-top: 20px; padding: 12px 16px; background: #D4EDDA; border: 1px solid #C3E6CB; border-radius: 6px; color: #155724;">
                            <span class="dashicons dashicons-yes" style="color: #28A745;"></span>
                            <span id="booking-toast-message"><?php _e('Booking created successfully!', 'amelia-cpt-sync'); ?></span>
                        </div>
                        
                        <!-- Reschedule Confirmation (hidden by default) -->
                        <div id="reschedule-confirm-section" style="display: none; margin-top: 16px; padding: 16px; background: #FFF3CD; border: 1px solid #FFECB5; border-radius: 6px;">
                            <h4 style="margin: 0 0 12px 0; color: #856404;">
                                <span class="dashicons dashicons-warning"></span>
                                <?php _e('Modify Existing Booking', 'amelia-cpt-sync'); ?>
                            </h4>
                            <p style="margin: 0 0 16px 0; color: #664d03; font-size: 13px;">
                                <?php _e('An Amelia booking already exists for this request. How would you like to proceed?', 'amelia-cpt-sync'); ?>
                            </p>
                            <div class="reschedule-options">
                                <select id="booking-action-select" class="form-select" style="margin-bottom: 12px;">
                                    <option value=""><?php _e('-- Select Action --', 'amelia-cpt-sync'); ?></option>
                                    <option value="reschedule"><?php _e('Reschedule - Update existing booking with new time/provider', 'amelia-cpt-sync'); ?></option>
                                    <option value="delete_and_create"><?php _e('Delete & Create New - Remove old booking and create fresh', 'amelia-cpt-sync'); ?></option>
                                </select>
                                <div id="action-implications" style="display: none; margin-bottom: 12px; padding: 10px; background: #fff; border-radius: 4px; font-size: 12px;">
                                </div>
                                <div class="reschedule-buttons" style="display: flex; gap: 10px;">
                                    <button type="button" id="btn-confirm-booking-action" class="btn-danger" disabled>
                                        <span class="dashicons dashicons-yes"></span>
                                        <?php _e('Confirm Action', 'amelia-cpt-sync'); ?>
                                    </button>
                                    <button type="button" id="btn-cancel-booking-action" class="btn-link">
                                        <?php _e('Cancel', 'amelia-cpt-sync'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                    </div>
                </div>
            </div>
            
            <!-- Right Column: Context & Info (1/3 width) -->
            <div class="art-sidebar-column">
                <!-- Customer Details Card -->
                <div class="art-card">
                    <div class="card-header">
                        <h3><?php _e('Customer Details', 'amelia-cpt-sync'); ?></h3>
                    </div>
                    <div class="card-body">
                        <dl class="info-list">
                            <div class="info-row">
                                <dt><?php _e('Name', 'amelia-cpt-sync'); ?></dt>
                                <dd><?php echo esc_html($customer_name); ?></dd>
                            </div>
                            
                            <?php if (!empty($request->customer_email)): ?>
                                <div class="info-row">
                                    <dt><?php _e('Email', 'amelia-cpt-sync'); ?></dt>
                                    <dd class="email-value">
                                        <a href="mailto:<?php echo esc_attr($request->customer_email); ?>">
                                            <?php echo esc_html($request->customer_email); ?>
                                        </a>
                                    </dd>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($request->customer_phone)): ?>
                                <div class="info-row">
                                    <dt><?php _e('Phone', 'amelia-cpt-sync'); ?></dt>
                                    <dd>
                                        <a href="tel:<?php echo esc_attr($request->customer_phone); ?>">
                                            <?php echo esc_html($request->customer_phone); ?>
                                        </a>
                                    </dd>
                                </div>
                            <?php endif; ?>
                            
                            <div class="info-row">
                                <dt><?php _e('Submitted', 'amelia-cpt-sync'); ?></dt>
                                <dd><?php echo esc_html($submitted_display); ?></dd>
                            </div>
                        </dl>
                        
                        <!-- Customer Match Check -->
                        <div class="customer-match-section">
                            <button type="button" 
                                    id="check-customer-match" 
                                    class="btn-secondary btn-small btn-block"
                                    data-email="<?php echo esc_attr($request->customer_email); ?>">
                                <span class="dashicons dashicons-search"></span>
                                <?php _e('Check Amelia Match', 'amelia-cpt-sync'); ?>
                            </button>
                            <div id="customer-match-result"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Intake details moved to main column -->
            </div>
        </div>
    </div>
</div>

<!-- Styles (Tailwind-inspired) -->
<style>
/* ============================================================================
   ART DETAIL VIEW STYLES
   Matching ui-mockup-detail-view.html aesthetic
   ============================================================================ */

.art-detail-view {
    background: #F7F8FC;
    padding: 0;
    margin: -10px -20px 0 -22px;
    min-height: 100vh;
}

/* === STICKY HEADER === */
.art-detail-header {
    position: sticky;
    top: 32px;  /* WordPress admin bar height */
    z-index: 100;
    background: rgba(247, 248, 252, 0.95);
    backdrop-filter: blur(8px);
    border-bottom: 1px solid #E0E5F1;
    padding: 16px 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
}

.header-left {
    display: flex;
    align-items: center;
    gap: 16px;
}

.back-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    background: #fff;
    border: 1px solid #E0E5F1;
    border-radius: 8px;
    color: #475569;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.2s;
}

.back-link:hover {
    background: #F1F5F9;
    border-color: #1A84EE;
    color: #1A84EE;
    text-decoration: none;
}

.back-link .dashicons {
    font-size: 18px;
    width: 18px;
    height: 18px;
}

.page-title {
    font-size: 20px;
    font-weight: 700;
    color: #1E293B;
    margin: 0;
}

.header-actions {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
}

.header-field {
    display: flex;
    align-items: center;
    gap: 8px;
}

.header-label {
    font-size: 14px;
    font-weight: 600;
    color: #64748b;
    white-space: nowrap;
    margin: 0;
}

.header-select,
.header-date {
    height: 36px;
    padding: 0 12px;
    background: #fff;
    border: 1px solid #E0E5F1;
    border-radius: 8px;
    font-size: 14px;
    color: #1E293B;
    font-weight: 500;
    min-width: 150px;
    transition: all 0.2s;
}

.header-select:focus,
.header-date:focus {
    outline: none;
    border-color: #1A84EE;
    box-shadow: 0 0 0 3px rgba(26, 132, 238, 0.1);
}

/* === MAIN CONTAINER === */
.art-detail-container {
    padding: 24px;
    max-width: 1600px;
    margin: 0 auto;
}

.art-grid-container {
    display: grid;
    grid-template-columns: 1fr 400px;
    gap: 24px;
}

.art-main-column {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.art-sidebar-column {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

/* === CARDS === */
.art-card {
    background: #fff;
    border: 1px solid #E0E5F1;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.card-disabled {
    opacity: 0.7;
    background: #F8FAFC;
}

/* Active Booking Card */
.art-booking-card {
    background: linear-gradient(135deg, #D4EDDA 0%, #C3E6CB 100%);
    border-color: #28A745;
    margin-bottom: 20px;
}

.art-booking-card .booking-header {
    background: rgba(40, 167, 69, 0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.art-booking-card .booking-header h3 {
    color: #155724;
    display: flex;
    align-items: center;
    gap: 8px;
}

.booking-status-badge {
    background: #28A745;
    color: #fff;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.booking-details-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
}

@media (max-width: 768px) {
    .booking-details-grid {
        grid-template-columns: 1fr;
    }
}

.booking-detail-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.booking-detail-item .detail-label {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #155724;
    opacity: 0.8;
}

.booking-detail-item .detail-value {
    font-size: 14px;
    font-weight: 600;
    color: #155724;
}

.booking-detail-item.full-width {
    grid-column: 1 / -1;
}

/* Danger button */
.btn-danger {
    background: #DC3545;
    color: #fff;
    border: none;
    padding: 10px 20px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: background 0.2s ease;
}

.btn-danger:hover {
    background: #C82333;
}

.btn-danger:disabled {
    background: #E9ECEF;
    color: #6C757D;
    cursor: not-allowed;
}

.card-header {
    padding: 16px 20px;
    border-bottom: 1px solid #E0E5F1;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-header h3 {
    font-size: 18px;
    font-weight: 600;
    color: #1E293B;
    margin: 0;
}

.badge-coming-soon {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    background: #FEF3C7;
    color: #D97706;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.badge-phase5 {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    background: #D1FAE5;
    color: #065F46;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.service-duration-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    background: #E0F2FE;
    border: 1px solid #BAE6FD;
    border-radius: 6px;
    font-size: 13px;
    color: #0C4A6E;
}

.service-duration-badge .refresh-icon {
    background: none;
    border: none;
    cursor: pointer;
    font-size: 16px;
    color: #0284C7;
    padding: 0;
    margin: 0;
    line-height: 1;
    transition: transform 0.2s;
}

.service-duration-badge .refresh-icon:hover {
    transform: rotate(90deg);
    color: #0369A1;
}

.service-duration-badge .duration-text {
    font-weight: 600;
}

.badge-info {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    background: #DBEAFE;
    color: #2563EB;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

/* === DATE & TIME PICKER (Phase 5) === */
.art-picker-container {
    display: grid;
    gap: 0;
    border: 1px solid #E0E5F1;
    border-radius: 8px;
    overflow: hidden;
    background: #fff;
    margin-top: 16px;
}

/* 4-column layout (with time grid): Dates | Time Grid | Time Entry | Providers */
.art-picker-container.with-timegrid {
    grid-template-columns: 15% 1fr 20% 35%;
}

/* 3-column layout (no time grid - default): Dates 25% | Time Entry 25% | Providers 50% */
.art-picker-container.no-timegrid {
    grid-template-columns: 25% 25% 50%;
}

.art-picker-container.no-timegrid .art-picker-dates {
    max-height: 400px;
}

.art-picker-dates {
    background: #F8FAFC;
    border-right: 1px solid #E0E5F1;
    max-height: 350px;
    overflow-y: auto;
}

.art-picker-date-btn {
    display: block;
    width: 100%;
    text-align: left;
    padding: 12px 16px;
    border: none;
    border-bottom: 1px solid #F1F5F9;
    background: transparent;
    color: #64748B;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.art-picker-date-btn:hover {
    background: #fff;
    color: #1A84EE;
}

.art-picker-date-btn.active {
    background: #fff;
    color: #1A84EE;
    border-left: 3px solid #1A84EE;
    font-weight: 600;
    padding-left: 13px; /* Compensate for border */
}

.art-picker-times {
    padding: 20px;
    background: #fff;
    max-height: 350px;
    overflow-y: auto;
}

/* Time Entry Column */
.art-picker-time-entry {
    padding: 16px;
    background: #F8FAFC;
    border-left: 1px solid #E0E5F1;
    display: flex;
    flex-direction: column;
}

.art-picker-time-entry .time-entry-field {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.art-picker-time-entry label {
    font-size: 12px;
    font-weight: 500;
    color: #64748B;
}

.art-picker-time-entry input[type="time"] {
    padding: 10px 12px;
    font-size: 16px;
    border: 1px solid #E0E5F1;
    border-radius: 6px;
    background: #fff;
}

.art-picker-time-entry input[type="time"]:focus {
    border-color: #1A84EE;
    outline: none;
    box-shadow: 0 0 0 3px rgba(26, 132, 238, 0.1);
}

.art-picker-time-entry .field-hint {
    font-size: 11px;
    color: #94A3B8;
    margin: 0;
}

/* Provider Selection Column */
.art-picker-providers {
    background: #fff;
    border-left: 1px solid #E0E5F1;
    display: flex;
    flex-direction: column;
    max-height: 400px;
}

.art-picker-column-header {
    font-size: 13px;
    font-weight: 600;
    color: #1E293B;
    padding: 12px 16px;
    background: #F8FAFC;
    border-bottom: 1px solid #E0E5F1;
    flex-shrink: 0;
}

.art-picker-column-content {
    padding: 16px;
    flex: 1;
}

.art-provider-list {
    flex: 1;
    overflow-y: auto;
    padding: 8px;
}

.provider-placeholder {
    padding: 20px;
    text-align: center;
    color: #94A3B8;
    font-size: 13px;
}

/* Provider Item Styles */
.provider-group {
    margin-bottom: 12px;
}

.provider-group-label {
    font-size: 11px;
    font-weight: 600;
    color: #64748B;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 6px 12px;
    background: #F1F5F9;
    border-radius: 4px;
    margin-bottom: 6px;
}

.provider-group-label.available {
    background: #DCFCE7;
    color: #166534;
}

.provider-group-label.nearby {
    background: #FEF3C7;
    color: #92400E;
}

.provider-group-label.force {
    background: #FEE2E2;
    color: #991B1B;
}

.provider-item {
    display: flex;
    align-items: center;
    padding: 10px 12px;
    margin: 4px 0;
    border: 2px solid transparent;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.15s ease;
    background: #fff;
}

.provider-item:hover {
    background: #F8FAFC;
    border-color: #E0E5F1;
}

.provider-item.selected {
    background: #EFF6FF;
    border-color: #1A84EE;
}

.provider-item .provider-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 600;
    margin-right: 10px;
    flex-shrink: 0;
}

.provider-item .provider-info {
    flex: 1;
    min-width: 0;
}

.provider-item .provider-name {
    font-size: 13px;
    font-weight: 500;
    color: #1E293B;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.provider-item .provider-status {
    font-size: 11px;
    color: #64748B;
}

.provider-item .provider-conflicts {
    font-size: 10px;
    color: #94A3B8;
    margin-top: 2px;
    line-height: 1.3;
}

.provider-group.warning .provider-item .provider-conflicts {
    color: #B45309;
}

.provider-loading {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 30px 15px;
    color: #64748B;
    font-size: 13px;
}

.provider-loading .dashicons {
    margin-right: 8px;
}

.provider-loading .dashicons.spin {
    animation: spin 1s linear infinite;
}

.provider-error {
    padding: 15px;
    color: #DC3545;
    font-size: 13px;
    text-align: center;
}

.provider-warning {
    padding: 8px 12px;
    background: #FFF3CD;
    border-radius: 6px;
    margin-bottom: 10px;
    font-size: 12px;
    color: #856404;
}

.provider-item .provider-check {
    width: 20px;
    height: 20px;
    border: 2px solid #E0E5F1;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: all 0.15s ease;
}

.provider-item.selected .provider-check {
    background: #1A84EE;
    border-color: #1A84EE;
    color: #fff;
}

.provider-item.selected .provider-check .dashicons {
    font-size: 14px;
    width: 14px;
    height: 14px;
}

/* Confirm Button Section */
.art-picker-confirm {
    margin-top: 16px;
    padding: 16px;
    background: #F8FAFC;
    border: 1px solid #E0E5F1;
    border-radius: 8px;
    text-align: center;
}

.art-picker-confirm .btn-primary {
    padding: 12px 24px;
    font-size: 14px;
}

.art-picker-times-header {
    font-size: 14px;
    font-weight: 600;
    color: #1E293B;
    margin-bottom: 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid #E2E8F0;
}

.art-time-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
    gap: 10px;
    overflow: visible; /* Allow tooltips to show outside */
    padding-top: 60px; /* Space for tooltips above first row */
}

.art-picker-times {
    overflow: visible !important; /* Ensure tooltips aren't clipped */
}

.art-time-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 8px 12px;
    border: 1px solid #E2E8F0;
    border-radius: 6px;
    background: #fff;
    color: #1A84EE;
    font-weight: 500;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.2s;
}

.art-time-btn:hover {
    border-color: #1A84EE;
    background: #EFF6FF;
}

.art-time-btn.active {
    background: #1A84EE;
    color: #fff;
    border-color: #1A84EE;
    box-shadow: 0 2px 4px rgba(26, 132, 238, 0.2);
}

/* Hide scrollbar for clean look */
.art-picker-dates::-webkit-scrollbar,
.art-picker-times::-webkit-scrollbar {
    width: 6px;
}
.art-picker-dates::-webkit-scrollbar-track,
.art-picker-times::-webkit-scrollbar-track {
    background: #F8FAFC;
}
.art-picker-dates::-webkit-scrollbar-thumb,
.art-picker-times::-webkit-scrollbar-thumb {
    background: #CBD5E1;
    border-radius: 3px;
}

@media (max-width: 1200px) {
    .art-picker-container.with-timegrid {
        grid-template-columns: 180px 1fr 200px; /* Hide time grid on medium screens */
    }
    .art-picker-container.with-timegrid .art-picker-times {
        display: none;
    }
}

@media (max-width: 900px) {
    .art-picker-container.with-timegrid,
    .art-picker-container.no-timegrid {
        grid-template-columns: 1fr; /* Stack on smaller screens */
    }
    .art-picker-dates {
        border-right: none;
        border-bottom: 1px solid #E0E5F1;
        max-height: 200px;
    }
    .art-picker-times {
        max-height: 250px;
        border-left: none;
        border-bottom: 1px solid #E0E5F1;
    }
    .art-picker-time-entry {
        border-left: none;
        border-bottom: 1px solid #E0E5F1;
    }
    .art-picker-providers {
        border-left: none;
        max-height: 300px;
    }
}

.card-body {
    padding: 20px;
}

/* === FORM FIELDS === */
.pillar-grid-1 {
    display: grid;
    grid-template-columns: 1fr;
    gap: 16px;
    max-width: 400px;
}

.pillar-grid-2 {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
}

.pillar-grid-3 {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
}

.form-field {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.form-field label {
    font-size: 14px;
    font-weight: 600;
    color: #475569;
    margin: 0;
}

.form-select,
.form-input {
    height: 44px;
    padding: 0 14px;
    background: #F7F8FC;
    border: 1px solid #CBD5E1;
    border-radius: 8px;
    font-size: 14px;
    color: #1E293B;
    transition: all 0.2s;
}

.form-select:hover,
.form-input:hover {
    background: #fff;
    border-color: #94A3B8;
}

.form-select:focus,
.form-input:focus {
    outline: none;
    background: #fff;
    border-color: #1A84EE;
    box-shadow: 0 0 0 3px rgba(26, 132, 238, 0.1);
}

.form-input:disabled {
    background: #F1F5F9;
    color: #94A3B8;
    cursor: not-allowed;
}

.field-note {
    font-size: 12px;
    color: #64748b;
    margin: 0;
    font-style: italic;
}

/* === TOOLTIP (Phase 5) === */
.art-slot-tooltip {
    position: absolute;
    bottom: 100%; /* Position above */
    left: 50%;
    transform: translateX(-50%);
    margin-bottom: 8px; /* Spacing */
    background: #1E293B;
    color: #fff;
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 12px;
    z-index: 1000;
    white-space: nowrap;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    pointer-events: auto;
}

/* Arrow pointing down */
.art-slot-tooltip::after {
    content: '';
    position: absolute;
    top: 100%;
    left: 50%;
    margin-left: -5px;
    border-width: 5px;
    border-style: solid;
    border-color: #1E293B transparent transparent transparent;
}

/* Relative parent for positioning */
.art-time-btn {
    position: relative;
    /* ... existing styles ... */
}

.art-provider-link {
    display: block;
    color: #fff;
    text-decoration: none;
    padding: 2px 0;
    cursor: pointer;
}

.art-provider-link:hover {
    color: #60A5FA;
    text-decoration: underline;
}

/* === PRICE INPUT === */
.price-input-wrap {
    position: relative;
}

.price-symbol {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #64748b;
    font-weight: 600;
    font-size: 16px;
}

.price-input {
    padding-left: 32px !important;
}

/* === INFO LIST (Customer/Intake Cards) === */
.info-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin: 0;
}

.info-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    font-size: 14px;
}

.info-row dt {
    color: #64748b;
    font-weight: 500;
    white-space: nowrap;
    margin: 0;
}

.info-row dd {
    color: #1E293B;
    font-weight: 600;
    text-align: right;
    margin: 0;
    word-break: break-word;
}

.email-value a {
    color: #1A84EE;
    text-decoration: none;
}

.email-value a:hover {
    text-decoration: underline;
}

/* === INTAKE FIELDS === */
.intake-fields-list {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.intake-field-item {
    padding-bottom: 20px;
    border-bottom: 1px solid #F1F5F9;
}

.intake-field-item:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.intake-field-item.long-text {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.intake-label {
    font-size: 14px;
    font-weight: 600;
    color: #475569;
    margin: 0;
}

.intake-field-item.long-text .intake-label {
    margin-bottom: 4px;
}

.intake-value {
    font-size: 14px;
    color: #1E293B;
    line-height: 1.6;
    margin: 0;
}

.intake-value p {
    margin: 0 0 12px;
}

.intake-value p:last-child {
    margin-bottom: 0;
}

/* === DURATION SUMMARY === */
.duration-summary {
    margin-top: 16px;
    padding: 12px 16px;
    background: #F8FAFC;
    border: 1px solid #E0E5F1;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.duration-icon {
    font-size: 18px;
}

.duration-text {
    font-size: 14px;
    color: #64748b;
}

.duration-text strong {
    color: #1A84EE;
    font-weight: 700;
}

/* === CUSTOMER MATCH === */
.customer-match-section {
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid #E0E5F1;
}

#customer-match-result {
    margin-top: 12px;
    padding: 12px;
    border-radius: 8px;
    font-size: 13px;
}

.match-found {
    background: #DCFCE7;
    color: #16A34A;
    display: flex;
    align-items: center;
    gap: 8px;
}

.match-found .dashicons {
    color: #16A34A;
}

.match-not-found {
    background: #FEF3C7;
    color: #D97706;
    display: flex;
    align-items: center;
    gap: 8px;
}

.match-not-found .dashicons {
    color: #D97706;
}

/* === BUTTONS === */
.btn-primary {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 0 24px;
    height: 44px;
    background: #1A84EE;
    border: none;
    border-radius: 8px;
    color: #fff;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
}

.btn-primary:hover {
    background: #1569C7;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(26, 132, 238, 0.3);
}

.btn-primary:disabled {
    background: #CBD5E1;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.btn-secondary {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 0 20px;
    height: 40px;
    background: #fff;
    border: 1px solid #E0E5F1;
    border-radius: 8px;
    color: #475569;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-secondary:hover {
    background: #F1F5F9;
    border-color: #1A84EE;
    color: #1A84EE;
}

.btn-secondary:disabled {
    background: #F8FAFC;
    color: #CBD5E1;
    cursor: not-allowed;
}

.btn-large {
    height: 48px;
    padding: 0 32px;
    font-size: 15px;
}

.btn-small {
    height: 36px;
    padding: 0 16px;
    font-size: 13px;
}

.btn-block {
    width: 100%;
}

.form-actions {
    display: flex;
    align-items: center;
    gap: 16px;
}

.save-indicator {
    font-size: 14px;
    color: #16A34A;
    font-weight: 500;
}

/* === PLACEHOLDER CONTENT === */
.placeholder-text {
    color: #64748b;
    text-align: center;
    padding: 20px;
    font-size: 14px;
    margin: 0 0 16px;
}

.placeholder-controls {
    pointer-events: none;
}

.placeholder-actions {
    display: flex;
    gap: 12px;
    margin-top: 16px;
    justify-content: flex-end;
}

/* === CALENDAR IFRAME === */
.calendar-container {
    margin-bottom: 20px;
    border: 1px solid #E0E5F1;
    border-radius: 8px;
    overflow: hidden;
    background: #fff;
    transition: all 0.3s ease;
}

/* Locked Expand Mode - fills both columns */
.calendar-container.expanded-locked {
    position: fixed;
    top: 80px;
    left: 200px;
    right: 20px;
    bottom: 40px;
    z-index: 9999;
    margin: 0;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.3);
    display: flex;
    flex-direction: column;
}

.calendar-container.expanded-locked .calendar-toolbar {
    flex-shrink: 0;
}

.calendar-container.expanded-locked .calendar-iframe-wrapper {
    flex: 1;
    overflow: hidden;
}

/* Unlocked/Floating Mode - draggable & resizable */
.calendar-container.expanded-unlocked {
    position: fixed;
    z-index: 9999;
    margin: 0;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.3);
    resize: both;
    overflow: hidden;
    min-width: 500px;
    min-height: 400px;
    max-width: 95vw;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
}

/* Draggable header styling */
.calendar-container.expanded-unlocked .calendar-toolbar {
    cursor: move;
    user-select: none;
    background: linear-gradient(to bottom, #f8f9fa, #e9ecef);
    flex-shrink: 0;
}

/* While dragging */
.calendar-container.is-dragging {
    opacity: 0.9;
    cursor: move !important;
}

.calendar-container.is-dragging * {
    pointer-events: none;
}

.calendar-container.is-dragging .calendar-toolbar {
    background: linear-gradient(to bottom, #e3e6ea, #d5d9de);
}

/* Iframe wrapper in unlocked mode */
.calendar-container.expanded-unlocked .calendar-iframe-wrapper {
    flex: 1;
    overflow: hidden;
}

/* Resize handle indicator */
.calendar-container.expanded-unlocked::after {
    content: '';
    position: absolute;
    bottom: 0;
    right: 0;
    width: 20px;
    height: 20px;
    cursor: nwse-resize;
    background: linear-gradient(135deg, transparent 50%, #94A3B8 50%, #94A3B8 60%, transparent 60%, transparent 70%, #94A3B8 70%, #94A3B8 80%, transparent 80%);
    z-index: 10;
}

/* Active state for buttons */
#btn-expand-locked.active,
#btn-expand-unlocked.active {
    background: #1A84EE;
    color: #fff;
    border-color: #1A84EE;
}

/* Lock icon styling */
#btn-expand-locked {
    position: relative;
}

#btn-expand-locked .lock-icon {
    position: absolute;
    font-size: 10px;
    width: 10px;
    height: 10px;
    bottom: 2px;
    right: 2px;
    color: inherit;
}

.calendar-toolbar {
    padding: 12px 16px;
    background: #f8f9fa;
    border-bottom: 1px solid #E0E5F1;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.calendar-toolbar strong {
    font-size: 14px;
    color: #1E293B;
}

.calendar-controls {
    display: flex;
    align-items: center;
    gap: 8px;
}

.zoom-controls {
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 4px 8px;
    background: #fff;
    border: 1px solid #E0E5F1;
    border-radius: 6px;
}

.zoom-controls #zoom-level {
    min-width: 45px;
    text-align: center;
    font-size: 12px;
    font-weight: 600;
    color: #64748B;
}

.btn-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    padding: 0;
    border: 1px solid #E0E5F1;
    border-radius: 6px;
    background: #fff;
    color: #64748B;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-icon:hover {
    background: #f1f5f9;
    color: #1A84EE;
    border-color: #1A84EE;
}

.btn-icon.btn-close:hover {
    background: #FEE2E2;
    color: #DC2626;
    border-color: #DC2626;
}

.btn-icon .dashicons {
    font-size: 18px;
    width: 18px;
    height: 18px;
}

.calendar-iframe-wrapper {
    position: relative;
    height: 500px;
    overflow: auto;
    background: #fff;
}

/* Expanded modes - iframe fills available space */
.calendar-container.expanded-locked .calendar-iframe-wrapper,
.calendar-container.expanded-unlocked .calendar-iframe-wrapper {
    height: calc(100% - 52px); /* Subtract toolbar height */
}

#amelia-calendar-frame {
    border: none;
    background: #fff;
    width: 100%;
    height: 100%;
    display: block; /* Remove inline spacing */
}

/* === RESPONSIVE === */
@media (max-width: 1200px) {
    .art-grid-container {
        grid-template-columns: 1fr 350px;
    }
}

@media (max-width: 900px) {
    .art-grid-container {
        grid-template-columns: 1fr;
    }
    
    .art-sidebar-column {
        order: -1;  /* Move sidebar to top on mobile */
    }
    
    .pillar-grid-3 {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 600px) {
    .art-detail-header {
        flex-direction: column;
        align-items: stretch;
    }
    
    .header-actions {
        flex-direction: column;
        align-items: stretch;
    }
    
    .header-field {
        flex-direction: column;
        align-items: stretch;
        gap: 4px;
    }
    
    .header-select,
    .header-date {
        width: 100%;
    }
    
    .pillar-grid-2 {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- JavaScript for Interactivity -->
<script>
jQuery(document).ready(function($) {
    var artDetailData = {
        requestId: <?php echo $request_id; ?>,
        customerEmail: <?php echo wp_json_encode($request->customer_email); ?>,
        nonce: <?php echo wp_json_encode(wp_create_nonce('art_nonce')); ?>,
        currentCategory: <?php echo wp_json_encode($request->category_id); ?>,
        currentService: <?php echo wp_json_encode($request->service_id); ?>,
        currentLocation: <?php echo wp_json_encode($request->location_id); ?>,
        providers: {}, // Will be populated by fetchServiceEmployees()
        showTimeslotsGrid: <?php echo $show_timeslots_grid ? 'true' : 'false'; ?>,
        hasActiveBooking: <?php echo (!empty($active_booking) && !empty($active_booking->amelia_appointment_id)) ? 'true' : 'false'; ?>,
        activeAppointmentId: <?php echo (!empty($active_booking) && !empty($active_booking->amelia_appointment_id)) ? intval($active_booking->amelia_appointment_id) : 'null'; ?>,
        activeBookingId: <?php echo (!empty($active_booking) && !empty($active_booking->amelia_booking_id)) ? intval($active_booking->amelia_booking_id) : 'null'; ?>
    };
    
    // === HELPER: Show Notice ===
    function showNotice(message, type) {
        var noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
        var notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
        
        $('.art-detail-view').prepend(notice);
        
        // Auto-dismiss after 3 seconds
        setTimeout(function() {
            notice.fadeOut(function() {
                $(this).remove();
            });
        }, 3000);
    }
    
    // === STATUS DROPDOWN: Auto-save on change ===
    $('#status-dropdown').on('change', function() {
        var newStatus = $(this).val();
        var requestId = $(this).data('request-id');
        
        $.post(ajaxurl, {
            action: 'art_update_status',
            nonce: artDetailData.nonce,
            request_id: requestId,
            status: newStatus
        }, function(response) {
            if (response.success) {
                showNotice('Status updated to ' + newStatus, 'success');
            } else {
                showNotice('Error updating status: ' + response.data.message, 'error');
            }
        });
    });
    
    // === FOLLOW-UP DATE: Auto-save on change ===
    $('#follow-up-date').on('change', function() {
        var date = $(this).val();
        var requestId = $(this).data('request-id');
        
        $.post(ajaxurl, {
            action: 'art_update_follow_up',
            nonce: artDetailData.nonce,
            request_id: requestId,
            follow_up_date: date
        }, function(response) {
            if (response.success) {
                showNotice('Follow-up date saved', 'success');
            } else {
                showNotice('Error saving follow-up date', 'error');
            }
        });
    });
    
    // === CATEGORY → SERVICE CASCADING ===
    $('#pillar-category').on('change', function() {
        var selectedCategoryId = $(this).val();
        
        // Filter service dropdown by category
        $('#pillar-service option').each(function() {
            if ($(this).val() === '') {
                $(this).show();  // Always show "Select Service"
                return;
            }
            
            var serviceCategoryId = $(this).data('category-id');
            
            if (!selectedCategoryId || serviceCategoryId == selectedCategoryId) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
        
        // Reset service selection if current service doesn't match category
        var currentServiceCat = $('#pillar-service option:selected').data('category-id');
        if (selectedCategoryId && currentServiceCat != selectedCategoryId) {
            $('#pillar-service').val('');
        }
    });
    
    // === SERVICE → CATEGORY AUTO-UPDATE ===
    $('#pillar-service').on('change', function() {
        var serviceCategoryId = $(this).find(':selected').data('category-id');
        
        if (serviceCategoryId) {
            // Auto-update category to match service
            $('#pillar-category').val(serviceCategoryId);
        }
        
        // Phase 5: Fetch and display service duration
        fetchServiceDuration();
    });
    
    /**
     * Fetch and display service default duration from Amelia API (Phase 5)
     */
    function fetchServiceDuration() {
        var serviceId = $('#pillar-service').val();
        var durationDisplay = $('#service-duration-display');
        
        if (!serviceId) {
            durationDisplay.hide();
            return;
        }
        
        // Show loading state
        durationDisplay.find('.duration-text').html('<span style="opacity: 0.6;">Loading...</span>');
        durationDisplay.show();
        
        $.post(ajaxurl, {
            action: 'art_get_service_duration',
            nonce: artDetailData.nonce,
            service_id: serviceId
        }, function(response) {
            if (response.success) {
                durationDisplay.find('.duration-text').html(
                    'Service default: <strong>' + response.data.duration_display + '</strong>'
                );
            } else {
                durationDisplay.find('.duration-text').html(
                    '<span style="color: #DC3545;">API Error</span>'
                );
            }
        }).fail(function() {
            durationDisplay.find('.duration-text').html(
                '<span style="color: #DC3545;">Failed</span>'
            );
        });
    }
    
    // Refresh button for service duration
    $(document).on('click', '#service-duration-display .refresh-icon', function(e) {
        e.preventDefault();
        fetchServiceDuration();
    });
    
    // Load service duration on page load if service is already selected
    if (artDetailData.currentService) {
        fetchServiceDuration();
    }
    
    // === DURATION CALCULATION (Bidirectional) ===
    
    // Helper: Format seconds to HH:MM display
    function formatDuration(seconds) {
        if (!seconds || seconds <= 0) return 'Not set';
        
        var hours = Math.floor(seconds / 3600);
        var minutes = Math.floor((seconds % 3600) / 60);
        
        if (hours > 0 && minutes > 0) {
            return hours + 'h ' + minutes + 'm';
        } else if (hours > 0) {
            return hours + 'h';
        } else {
            return minutes + ' minutes';
        }
    }
    
    // Calculate duration from start + end times
    function calculateDurationFromTimes() {
        var start = $('#pillar-start').val();
        var end = $('#pillar-end').val();
        
        if (start && end) {
            var startDate = new Date(start);
            var endDate = new Date(end);
            var diffMs = endDate - startDate;
            
            if (diffMs > 0) {
                var diffSeconds = Math.floor(diffMs / 1000);
                
                $('#pillar-duration-seconds').val(diffSeconds);
                $('#duration-display').text(formatDuration(diffSeconds));
                
                // Try to select matching duration in dropdown
                var exactMatch = $('#pillar-duration-selector option[value="' + diffSeconds + '"]');
                if (exactMatch.length) {
                    $('#pillar-duration-selector').val(diffSeconds);
                } else {
                    $('#pillar-duration-selector').val('');  // Custom duration
                }
            } else {
                $('#duration-display').text('Invalid range');
                $('#pillar-duration-seconds').val(0);
            }
        } else if (!end && $('#pillar-duration-seconds').val()) {
            // Start set but no end - show duration from hidden field
            $('#duration-display').text(formatDuration($('#pillar-duration-seconds').val()));
        }
    }
    
    // Calculate end time from start + duration
    function calculateEndFromDuration() {
        var start = $('#pillar-start').val();
        var durationSeconds = parseInt($('#pillar-duration-selector').val());
        
        if (start && durationSeconds > 0) {
            var startDate = new Date(start);
            var endDate = new Date(startDate.getTime() + (durationSeconds * 1000));
            
            // Format for datetime-local input
            var endFormatted = endDate.getFullYear() + '-' +
                String(endDate.getMonth() + 1).padStart(2, '0') + '-' +
                String(endDate.getDate()).padStart(2, '0') + 'T' +
                String(endDate.getHours()).padStart(2, '0') + ':' +
                String(endDate.getMinutes()).padStart(2, '0');
            
            $('#pillar-end').val(endFormatted);
            $('#pillar-duration-seconds').val(durationSeconds);
            $('#duration-display').text(formatDuration(durationSeconds));
        }
    }
    
    // Event: Start or End time changes → Calculate duration
    $('#pillar-start, #pillar-end').on('change', calculateDurationFromTimes);
    
    // Event: Duration dropdown changes → Calculate end time
    $('#pillar-duration-selector').on('change', calculateEndFromDuration);
    
    // === ENQUEUE SELECT2 FOR CUSTOM DURATION ENTRY ===
    if (!$('link[href*="select2"]').length) {
        $('<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">').appendTo('head');
        $.getScript('https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', function() {
            initDurationSelect2();
        });
    } else {
        initDurationSelect2();
    }
    
    function initDurationSelect2() {
        $('#pillar-duration-selector').select2({
            tags: true,  // Allow custom entry
            placeholder: 'Select or enter duration (HH:MM)',
            allowClear: true,
            createTag: function(params) {
                var term = $.trim(params.term);
                
                // Validate HH:MM format
                if (/^\d{1,2}:\d{2}$/.test(term)) {
                    var parts = term.split(':');
                    var hours = parseInt(parts[0]);
                    var mins = parseInt(parts[1]);
                    
                    if (mins >= 60) {
                        return null;  // Invalid minutes
                    }
                    
                    var seconds = (hours * 3600) + (mins * 60);
                    
                    return {
                        id: seconds,
                        text: term,
                        newTag: true
                    };
                }
                
                return null;  // Invalid format
            }
        });
        
        // When custom tag selected, trigger calculation
        $('#pillar-duration-selector').on('select2:select', function(e) {
            if (e.params.data.newTag) {
                calculateEndFromDuration();
            }
        });
    }
    
    // === CUSTOMER MATCH CHECK ===
    $('#check-customer-match').on('click', function() {
        var email = $(this).data('email');
        var btn = $(this);
        var originalText = btn.html();
        
        btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Checking...');
        
        $.post(ajaxurl, {
            action: 'art_check_customer_match',
            nonce: artDetailData.nonce,
            email: email
        }, function(response) {
            if (response.success && response.data.customer) {
                var customer = response.data.customer;
                $('#customer-match-result').html(
                    '<div class="match-found">' +
                    '<span class="dashicons dashicons-yes-alt"></span> ' +
                    '<span>Found in Amelia: <strong>' + customer.firstName + ' ' + customer.lastName + 
                    '</strong> (ID: ' + customer.id + ')</span>' +
                    '</div>'
                );
            } else {
                $('#customer-match-result').html(
                    '<div class="match-not-found">' +
                    '<span class="dashicons dashicons-info"></span> ' +
                    '<span>Not found in Amelia - new customer will be created when booking</span>' +
                    '</div>'
                );
            }
            
            btn.prop('disabled', false).html(originalText);
        }).fail(function() {
            $('#customer-match-result').html(
                '<div class="match-not-found">' +
                '<span class="dashicons dashicons-warning"></span> ' +
                '<span>Error checking customer - check API settings</span>' +
                '</div>'
            );
            btn.prop('disabled', false).html(originalText);
        });
    });
    
    // === SAVE PILLARS FORM ===
    $('#booking-pillars-form').on('submit', function(e) {
        e.preventDefault();
        
        var formData = {
            action: 'art_save_pillars',
            nonce: artDetailData.nonce,
            request_id: artDetailData.requestId,
            category_id: $('#pillar-category').val(),
            service_id: $('#pillar-service').val(),
            location_id: $('#pillar-location').length ? $('#pillar-location').val() : null,
            persons: $('#pillar-persons').length ? $('#pillar-persons').val() : 1,
            start_datetime: $('#pillar-start').val(),
            end_datetime: $('#pillar-end').val(),
            duration_seconds: $('#pillar-duration-seconds').val(),
            final_price: $('#pillar-price').val()
        };
        
        var btn = $(this).find('button[type="submit"]');
        var originalHtml = btn.html();
        
        btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Saving...');
        
        $.post(ajaxurl, formData, function(response) {
            if (response.success) {
                showNotice('Booking details saved successfully', 'success');
                $('.save-indicator').text('✓ Saved').show().fadeOut(3000);
                
                // Update hidden field and dropdown to reflect saved value
                var savedDuration = formData.duration_seconds;
                $('#pillar-duration-seconds').val(savedDuration);
                
                // Update dropdown selection if exact match exists
                var matchingOption = $('#pillar-duration-selector option[value="' + savedDuration + '"]');
                if (matchingOption.length) {
                    $('#pillar-duration-selector').val(savedDuration);
                }
                
                // Update duration display
                $('#duration-display').text(formatDuration(savedDuration));
            } else {
                showNotice('Error: ' + (response.data.message || 'Unknown error'), 'error');
            }
            
            btn.prop('disabled', false).html(originalHtml);
        }).fail(function() {
            showNotice('Error: Failed to save (check connection)', 'error');
            btn.prop('disabled', false).html(originalHtml);
        });
    });
    
    // === LOAD LOCATIONS FROM API ===
    function loadLocations() {
        // Only load if location field is visible
        if ($('#pillar-location').length === 0) {
            return;  // Field not in DOM (hidden by settings)
        }
        
        $.post(ajaxurl, {
            action: 'art_get_locations',
            nonce: artDetailData.nonce
        }, function(response) {
            if (response.success && response.data.locations) {
                var locations = response.data.locations;
                var select = $('#pillar-location');
                var currentLocation = artDetailData.currentLocation;
                
                select.html('<option value="">Select Location (Optional)</option>');
                
                $.each(locations, function(i, location) {
                    var selected = currentLocation == location.id ? ' selected' : '';
                    select.append('<option value="' + location.id + '"' + selected + '>' + location.name + '</option>');
                });
            } else {
                $('#pillar-location').html('<option value="">Error loading locations</option>');
            }
        }).fail(function() {
            $('#pillar-location').html('<option value="">API Error</option>');
        });
    }
    
    // Load locations on page load (only if field exists)
    loadLocations();
    
    // ========================================================================
    // PHASE 5: AVAILABILITY & BOOKING
    // ========================================================================
    
    var availabilityData = null;  // Store slots from API
    
    /**
     * Check Availability Button (Updated for Picker UI)
     */
    $('#btn-check-availability').on('click', function() {
        var btn = $(this);
        var icon = btn.find('.dashicons');
        
        // Validate required fields
        var serviceId = $('#pillar-service').val();
        var durationSeconds = $('#pillar-duration-selector').val() || $('#pillar-duration-hidden').val();
        var persons = $('#pillar-persons').val() || 1;
        var locationId = $('#pillar-location').val() || 0;
        
        if (!serviceId) {
            showNotice('Please select a service first', 'error');
            return;
        }
        
        if (!durationSeconds || durationSeconds <= 0) {
            showNotice('Please set a duration first', 'error');
            return;
        }
        
        // Show loading state
        btn.prop('disabled', true);
        icon.addClass('spin');
        $('#availability-status').html('<p style="color: #1A84EE;">Checking availability...</p>').show();
        $('#availability-results').hide();
        
        // Call API
        $.post(ajaxurl, {
            action: 'art_check_availability',
            nonce: artDetailData.nonce,
            request_id: artDetailData.requestId
        }, function(response) {
            btn.prop('disabled', false);
            icon.removeClass('spin');
            
            if (response.success) {
                var slots = response.data.slots || [];
                
                if (slots.length === 0) {
                    $('#availability-status').html(
                        '<p style="color: #DC3545;">No available slots found. Try adjusting the service, duration, or location.</p>'
                    );
                    return;
                }
                
                availabilityData = slots;
                
                // Group slots by date AND time
                var slotsByDate = {};
                var uniqueProviders = {}; // Track unique providers
                
                console.log('ART: Processing ' + slots.length + ' slots from API');
                
                $.each(slots, function(i, slot) {
                    if (!slotsByDate[slot.date]) {
                        slotsByDate[slot.date] = {}; // Use object to group by time
                    }
                    
                    if (!slotsByDate[slot.date][slot.time]) {
                        slotsByDate[slot.date][slot.time] = [];
                    }
                    
                    slotsByDate[slot.date][slot.time].push(slot);
                    
                    // Track provider ID (using ID as name for now until we have name lookup)
                    if (slot.provider_id) {
                        // Try to find name from preloaded provider map
                        var providerName = 'Provider #' + slot.provider_id;
                        if (artDetailData.providers && artDetailData.providers[slot.provider_id]) {
                            providerName = artDetailData.providers[slot.provider_id];
                        }
                        uniqueProviders[slot.provider_id] = providerName;
                    }
                });
                
                // Populate Provider Filter dropdown (for filtering the time grid)
                var providerSelect = $('#filter-provider');
                
                providerSelect.html('<option value="all">' + '<?php _e('All Available Employees', 'amelia-cpt-sync'); ?>' + '</option>');
                
                $.each(uniqueProviders, function(id, name) {
                    var option = '<option value="' + id + '">' + name + '</option>';
                    providerSelect.append(option);
                });
                
                // Update provider list column (will show placeholder)
                updateProviderList();
                
                // Helper to render dates (filtered)
                function renderFilteredDates(providerFilter) {
                    var datesList = $('#picker-dates-list');
                    datesList.empty();
                    
                    var sortedDates = Object.keys(slotsByDate).sort();
                    var visibleCount = 0;
                    var firstDate = null;
                    
                    $.each(sortedDates, function(i, dateStr) {
                        // Calculate total visible slots for this date based on filter
                        var dateTimes = slotsByDate[dateStr];
                        var filteredTimes = {};
                        var totalFilteredSlots = 0;
                        
                        $.each(dateTimes, function(time, providerSlots) {
                            if (providerFilter === 'all') {
                                filteredTimes[time] = providerSlots;
                                totalFilteredSlots++; // Count unique times, not providers
                            } else {
                                // Check if this time has the specific provider
                                var matchingSlots = providerSlots.filter(function(s) {
                                    return s.provider_id == providerFilter;
                                });
                                if (matchingSlots.length > 0) {
                                    filteredTimes[time] = matchingSlots;
                                    totalFilteredSlots++;
                                }
                            }
                        });
                        
                        if (totalFilteredSlots > 0) {
                            visibleCount++;
                            if (!firstDate) firstDate = dateStr;
                            
                            // Parse date parts to avoid timezone issues
                            // dateStr is "YYYY-MM-DD" format
                            var dateParts = dateStr.split('-');
                            var dateObj = new Date(dateParts[0], dateParts[1] - 1, dateParts[2]); // Month is 0-indexed
                            var formattedDate = dateObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric', weekday: 'short' });
                            
                            var btnHtml = '<button type="button" class="art-picker-date-btn" data-date="' + dateStr + '">' + 
                                          formattedDate + 
                                          ' <span style="float:right; color:#94A3B8; font-size:11px;">' + totalFilteredSlots + '</span>' +
                                          '</button>';
                            
                            // Store filtered times on the button
                            var btn = $(btnHtml);
                            btn.data('times', filteredTimes);
                            datesList.append(btn);
                        }
                    });
                    
                    if (visibleCount === 0) {
                        datesList.html('<div style="padding: 20px; text-align: center; color: #94A3B8;">No slots for this employee.</div>');
                        $('#picker-times-grid').empty();
                        $('#picker-times-header').text('Select a date');
                    } else {
                        // Auto-select first available date
                        datesList.find('.art-picker-date-btn').first().trigger('click');
                    }
                }
                
                // Initial Render
                renderFilteredDates('all');
                
                // Filter Change Event
                $('#filter-provider').off('change').on('change', function() {
                    var selected = $(this).val();
                    renderFilteredDates(selected);
                    
                    // Reset selection details
                    $('#slot-details').hide();
                    $('#btn-create-booking').prop('disabled', true);
                });
                
                // Handle Date Click (Delegated)
                $('#picker-dates-list').off('click').on('click', '.art-picker-date-btn', function() {
                    var btn = $(this);
                    var selectedDate = btn.data('date');
                    var dateTimes = btn.data('times'); // Use the filtered times map
                    
                    $('.art-picker-date-btn').removeClass('active');
                    btn.addClass('active');
                    
                    // Only render times if the time grid is enabled
                    if (artDetailData.showTimeslotsGrid) {
                        renderTimes(selectedDate, dateTimes);
                    }
                    
                    // Enable Time Input
                    $('#custom-time-input').prop('disabled', false);
                    
                    // Update provider list (will show placeholder until time is entered)
                    updateProviderList();
                });
                
                // Show results
                $('#slot-count-badge').text(slots.length + ' available'); // Total raw slots
                $('#availability-status').html(
                    '<p style="color: #28A745;"><strong>✓</strong> Found availability</p>'
                );
                $('#availability-results').slideDown();
                
            } else {
                $('#availability-status').html(
                    '<p style="color: #DC3545;">Error: ' + response.data.message + '</p>'
                );
            }
        }).fail(function() {
            btn.prop('disabled', false);
            icon.removeClass('spin');
            $('#availability-status').html(
                '<p style="color: #DC3545;">Network error. Please try again.</p>'
            );
        });
    });
    
    /**
     * Render Time Slots for a specific date (Consolidated)
     */
    function renderTimes(dateStr, timesMap) {
        var timesGrid = $('#picker-times-grid');
        var header = $('#picker-times-header');
        
        timesGrid.empty();
        
        // Format header date - parse parts to avoid timezone issues
        // dateStr is "YYYY-MM-DD" format
        var dateParts = dateStr.split('-');
        var dateObj = new Date(dateParts[0], dateParts[1] - 1, dateParts[2]); // Month is 0-indexed
        var formattedDate = dateObj.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' });
        header.text(formattedDate);
        
        // Sort times
        var sortedTimes = Object.keys(timesMap).sort();
        
        console.log('ART: renderTimes for ' + dateStr, {
            timeCount: sortedTimes.length,
            times: sortedTimes.slice(0, 10) // First 10 times
        });
        
        $.each(sortedTimes, function(i, time) {
            var providerSlots = timesMap[time]; // Array of slots for this time
            
            // Format time (e.g., "09:00" -> "9:00 AM")
            var timeParts = time.split(':');
            var hour = parseInt(timeParts[0]);
            var min = timeParts[1];
            var ampm = hour >= 12 ? 'PM' : 'AM';
            var hour12 = hour % 12;
            hour12 = hour12 ? hour12 : 12;
            var timeDisplay = hour12 + ':' + min + ' ' + ampm;
            
            // Create button
            var btn = $('<button type="button" class="art-time-btn">' + timeDisplay + '</button>');
            
            // Add tooltip logic if multiple providers
            if (providerSlots.length > 1) {
                var providerListHtml = '<div class="art-slot-tooltip" style="display:none;"><strong>Select Provider:</strong><br>';
                $.each(providerSlots, function(j, slot) {
                    var pName = (artDetailData.providers && artDetailData.providers[slot.provider_id]) ? 
                                artDetailData.providers[slot.provider_id] : 'Provider #' + slot.provider_id;
                    
                    // Make the name clickable to select that specific provider
                    providerListHtml += '<div class="art-provider-link" data-pid="' + slot.provider_id + '" data-idx="' + j + '">' + pName + '</div>';
                });
                providerListHtml += '</div>';
                
                var tooltip = $(providerListHtml);
                btn.append(tooltip);
                
                // Handle Main Button Click -> Toggle Tooltip
                btn.on('click', function(e) {
                    e.stopPropagation();
                    
                    // If this button is already active, just toggle tooltip
                    if ($(this).hasClass('active')) {
                        $(this).find('.art-slot-tooltip').toggle();
                        return;
                    }
                    
                    // Otherwise, select first provider AND show tooltip to offer others
                    $('.art-time-btn').removeClass('active');
                    $('.art-slot-tooltip').hide(); // Close others
                    
                    $(this).addClass('active');
                    $(this).find('.art-slot-tooltip').show();
                    
                    // Select first by default
                    var selectedSlot = providerSlots[0]; 
                    selectSlot(selectedSlot, timeDisplay + ' (Multiple Available)');
                });
                
                // Handle Provider Selection inside Tooltip
                tooltip.find('.art-provider-link').on('click', function(e) {
                    e.stopPropagation(); // Prevent bubbling to button
                    
                    var slotIdx = $(this).data('idx');
                    var selectedSlot = providerSlots[slotIdx];
                    var pName = $(this).text();
                    
                    // Select this specific slot/provider
                    selectSlot(selectedSlot, timeDisplay + ' (' + pName + ')');
                    
                    // Hide tooltip after selection
                    tooltip.hide();
                });
                
                // Close tooltip when clicking outside
                $(document).on('click', function() {
                    $('.art-slot-tooltip').hide();
                });
                
            } else {
                // Single provider - simple click
                btn.on('click', function() {
                    $('.art-time-btn').removeClass('active');
                    $('.art-slot-tooltip').hide();
                    $(this).addClass('active');
                    
                    var selectedSlot = providerSlots[0];
                    var pName = (artDetailData.providers && artDetailData.providers[selectedSlot.provider_id]) ? 
                                artDetailData.providers[selectedSlot.provider_id] : 'Provider #' + selectedSlot.provider_id;
                                
                    selectSlot(selectedSlot, timeDisplay + ' (' + pName + ')');
                });
            }
            
            timesGrid.append(btn);
        });
    }
    
    /**
     * Handle Final Slot Selection
     */
    function selectSlot(slot, timeDisplay) {
        // Store data
        $('#selected-slot-datetime').val(slot.datetime);
        $('#selected-provider-id').val(slot.provider_id);
        $('#selected-location-id').val(slot.location_id);
        
        // Get provider name
        var providerName = (artDetailData.providers && artDetailData.providers[slot.provider_id]) 
            ? artDetailData.providers[slot.provider_id] 
            : 'Provider #' + slot.provider_id;
        
        // Get location name
        var locationName = 'Default';
        if (slot.location_id && $('#pillar-location option[value="' + slot.location_id + '"]').length) {
            locationName = $('#pillar-location option[value="' + slot.location_id + '"]').text();
        }
        
        // Build summary
        var summary = 
            '<div style="color: #2C3E50;">' +
            '<strong style="font-size:14px;">Booking Summary:</strong><br>' +
            '<strong>Date & Time:</strong> ' + slot.date + ' at ' + timeDisplay + '<br>' +
            '<strong>Provider:</strong> ' + providerName + '<br>' +
            '<strong>Location:</strong> ' + locationName +
            '</div>';
        
        $('#slot-summary').html(summary);
        $('#slot-details').slideDown();
        
        // Enable booking button
        $('#btn-create-booking').prop('disabled', false);
    }
    
    /**
     * Check if there's an existing Amelia booking
     */
    function hasExistingBooking() {
        return artDetailData.hasActiveBooking === true;
    }
    
    /**
     * Update the Active Booking Card with booking details
     */
    function updateActiveBookingCard(data) {
        var html = '';
        
        // Booking ID
        html += '<div class="booking-detail-item">';
        html += '<span class="detail-label"><?php _e('Booking ID', 'amelia-cpt-sync'); ?></span>';
        html += '<span class="detail-value">#' + data.booking_id + '</span>';
        html += '</div>';
        
        // Appointment ID
        html += '<div class="booking-detail-item">';
        html += '<span class="detail-label"><?php _e('Appointment ID', 'amelia-cpt-sync'); ?></span>';
        html += '<span class="detail-value">#' + data.appointment_id + '</span>';
        html += '</div>';
        
        // Service
        if (data.service_name) {
            html += '<div class="booking-detail-item">';
            html += '<span class="detail-label"><?php _e('Service', 'amelia-cpt-sync'); ?></span>';
            html += '<span class="detail-value">' + data.service_name + '</span>';
            html += '</div>';
        }
        
        // Category
        if (data.category_name) {
            html += '<div class="booking-detail-item">';
            html += '<span class="detail-label"><?php _e('Category', 'amelia-cpt-sync'); ?></span>';
            html += '<span class="detail-value">' + data.category_name + '</span>';
            html += '</div>';
        }
        
        // Date (full width)
        html += '<div class="booking-detail-item full-width">';
        html += '<span class="detail-label"><?php _e('Date', 'amelia-cpt-sync'); ?></span>';
        html += '<span class="detail-value">' + data.formatted_date + '</span>';
        html += '</div>';
        
        // Time
        html += '<div class="booking-detail-item">';
        html += '<span class="detail-label"><?php _e('Time', 'amelia-cpt-sync'); ?></span>';
        html += '<span class="detail-value">' + data.formatted_time + '</span>';
        html += '</div>';
        
        // Provider
        if (data.provider_name) {
            html += '<div class="booking-detail-item">';
            html += '<span class="detail-label"><?php _e('Provider', 'amelia-cpt-sync'); ?></span>';
            html += '<span class="detail-value">' + data.provider_name + '</span>';
            html += '</div>';
        }
        
        // Location
        if (data.location_name) {
            html += '<div class="booking-detail-item">';
            html += '<span class="detail-label"><?php _e('Location', 'amelia-cpt-sync'); ?></span>';
            html += '<span class="detail-value">' + data.location_name + '</span>';
            html += '</div>';
        }
        
        $('#active-booking-details').html(html);
        $('#active-booking-card').slideDown();
        
        // Mark that we now have an active booking
        artDetailData.hasActiveBooking = true;
        artDetailData.activeAppointmentId = data.appointment_id;
        artDetailData.activeBookingId = data.booking_id;
        
        // Update the Create Booking button to show "Override" text
        updateCreateBookingButton();
    }
    
    /**
     * Update Create Booking button based on whether booking exists
     */
    function updateCreateBookingButton() {
        var btn = $('#btn-create-booking');
        if (hasExistingBooking()) {
            btn.removeClass('btn-primary').addClass('btn-danger');
            btn.html('<span class="dashicons dashicons-update"></span> <?php _e('Override Previous Booking', 'amelia-cpt-sync'); ?>');
        } else {
            btn.removeClass('btn-danger').addClass('btn-primary');
            btn.html('<span class="dashicons dashicons-calendar-alt"></span> <?php _e('Create Amelia Booking', 'amelia-cpt-sync'); ?>');
        }
    }
    
    // Initialize button state on page load
    updateCreateBookingButton();
    
    /**
     * Create Booking Button (Updated with reschedule/delete flow)
     */
    $('#btn-create-booking').on('click', function() {
        var btn = $(this);
        
        var slotDatetime = $('#selected-slot-datetime').val();
        var providerId = $('#selected-provider-id').val();
        
        if (!slotDatetime || !providerId) {
            showNotice('<?php _e('Please select a time slot first', 'amelia-cpt-sync'); ?>', 'error');
            return;
        }
        
        // Check if there's an existing booking
        if (hasExistingBooking()) {
            // Show the reschedule confirmation section
            $('#reschedule-confirm-section').slideDown();
            $('#booking-action-select').val('').trigger('change');
            $('#slot-details').hide();
            return;
        }
        
        // No existing booking - create new
        createNewBooking(btn, slotDatetime, providerId);
    });
    
    /**
     * Booking action dropdown change handler
     */
    $('#booking-action-select').on('change', function() {
        var action = $(this).val();
        var implications = $('#action-implications');
        var confirmBtn = $('#btn-confirm-booking-action');
        
        if (!action) {
            implications.hide();
            confirmBtn.prop('disabled', true);
            return;
        }
        
        confirmBtn.prop('disabled', false);
        
        if (action === 'reschedule') {
            implications.html(
                '<strong style="color: #0C5460;"><?php _e('Reschedule:', 'amelia-cpt-sync'); ?></strong> ' +
                '<?php _e('The existing Amelia appointment will be updated with the new date, time, and provider. Customer will be notified of the change.', 'amelia-cpt-sync'); ?>'
            ).show();
        } else if (action === 'delete_and_create') {
            implications.html(
                '<strong style="color: #721C24;"><?php _e('Delete & Create New:', 'amelia-cpt-sync'); ?></strong> ' +
                '<?php _e('The existing Amelia appointment will be permanently deleted. A completely new booking will be created. This cannot be undone.', 'amelia-cpt-sync'); ?>'
            ).show();
        }
    });
    
    /**
     * Cancel booking action
     */
    $('#btn-cancel-booking-action').on('click', function() {
        $('#reschedule-confirm-section').slideUp();
        $('#slot-details').slideDown();
    });
    
    /**
     * Confirm booking action (reschedule or delete+create)
     */
    $('#btn-confirm-booking-action').on('click', function() {
        var btn = $(this);
        var action = $('#booking-action-select').val();
        var slotDatetime = $('#selected-slot-datetime').val();
        var providerId = $('#selected-provider-id').val();
        
        if (!action) return;
        
        btn.prop('disabled', true);
        var originalText = btn.html();
        btn.html('<span class="dashicons dashicons-update spin"></span> <?php _e('Processing...', 'amelia-cpt-sync'); ?>');
        
        if (action === 'reschedule') {
            // Reschedule existing appointment
            $.post(ajaxurl, {
                action: 'art_reschedule_booking',
                nonce: artDetailData.nonce,
                request_id: artDetailData.requestId,
                new_datetime: slotDatetime,
                new_provider_id: providerId
            }, function(response) {
                btn.prop('disabled', false);
                btn.html(originalText);
                
                if (response.success) {
                    $('#reschedule-confirm-section').slideUp();
                    
                    // Update the active booking card with new data
                    updateActiveBookingCard({
                        booking_id: artDetailData.activeBookingId,
                        appointment_id: artDetailData.activeAppointmentId,
                        service_name: $('#pillar-service option:selected').text(),
                        category_name: $('#pillar-category option:selected').text(),
                        formatted_date: response.data.formatted_date,
                        formatted_time: response.data.formatted_time,
                        provider_name: response.data.provider_name,
                        location_name: $('#pillar-location option:selected').text() || ''
                    });
                    
                    // Show toast
                    $('#booking-toast-message').text('<?php _e('Booking rescheduled successfully!', 'amelia-cpt-sync'); ?>');
                    $('#booking-success-toast').slideDown().delay(4000).slideUp();
                    
                    showNotice('<?php _e('Amelia booking rescheduled successfully!', 'amelia-cpt-sync'); ?>', 'success');
                } else {
                    showNotice('<?php _e('Reschedule failed:', 'amelia-cpt-sync'); ?> ' + response.data.message, 'error');
                }
            }).fail(function() {
                btn.prop('disabled', false);
                btn.html(originalText);
                showNotice('<?php _e('Network error during reschedule', 'amelia-cpt-sync'); ?>', 'error');
            });
            
        } else if (action === 'delete_and_create') {
            // First delete the old booking
            $.post(ajaxurl, {
                action: 'art_delete_booking',
                nonce: artDetailData.nonce,
                request_id: artDetailData.requestId
            }, function(response) {
                if (response.success) {
                    // Now create a new booking
                    artDetailData.hasActiveBooking = false;
                    artDetailData.activeAppointmentId = null;
                    artDetailData.activeBookingId = null;
                    
                    createNewBooking(btn, slotDatetime, providerId, function() {
                        btn.prop('disabled', false);
                        btn.html(originalText);
                        $('#reschedule-confirm-section').slideUp();
                    });
                } else {
                    btn.prop('disabled', false);
                    btn.html(originalText);
                    showNotice('<?php _e('Delete failed:', 'amelia-cpt-sync'); ?> ' + response.data.message, 'error');
                }
            }).fail(function() {
                btn.prop('disabled', false);
                btn.html(originalText);
                showNotice('<?php _e('Network error during delete', 'amelia-cpt-sync'); ?>', 'error');
            });
        }
    });
    
    /**
     * Create a new Amelia booking
     */
    function createNewBooking(btn, slotDatetime, providerId, callback) {
        btn.prop('disabled', true);
        var originalText = btn.html();
        btn.html('<span class="dashicons dashicons-update spin"></span> <?php _e('Creating booking...', 'amelia-cpt-sync'); ?>');
        
        $.post(ajaxurl, {
            action: 'art_create_booking',
            nonce: artDetailData.nonce,
            request_id: artDetailData.requestId,
            provider_id: providerId,
            slot_datetime: slotDatetime
        }, function(response) {
            btn.prop('disabled', false);
            btn.html(originalText);
            
            if (response.success) {
                // Update the active booking card with full details
                updateActiveBookingCard({
                    booking_id: response.data.booking_id,
                    appointment_id: response.data.appointment_id,
                    service_name: response.data.service_name || $('#pillar-service option:selected').text(),
                    category_name: response.data.category_name || $('#pillar-category option:selected').text(),
                    formatted_date: response.data.formatted_date,
                    formatted_time: response.data.formatted_time,
                    provider_name: response.data.provider_name,
                    location_name: response.data.location_name || ''
                });
                
                // Show toast notification
                $('#booking-toast-message').text('<?php _e('Amelia booking created successfully!', 'amelia-cpt-sync'); ?>');
                $('#booking-success-toast').slideDown().delay(4000).slideUp();
                
                // Update status dropdown
                $('#status-dropdown').val('booked').trigger('change');
                
                showNotice('<?php _e('Amelia booking created successfully!', 'amelia-cpt-sync'); ?>', 'success');
                
                // Keep availability engine visible but update button
                updateCreateBookingButton();
                
            } else {
                showNotice('<?php _e('Booking failed:', 'amelia-cpt-sync'); ?> ' + response.data.message, 'error');
            }
            
            if (callback) callback();
        }).fail(function() {
            btn.prop('disabled', false);
            btn.html(originalText);
            showNotice('<?php _e('Network error creating booking', 'amelia-cpt-sync'); ?>', 'error');
            if (callback) callback();
        });
    }
    
    /**
     * Cancel/Clear Availability Results (Updated)
     */
    $('#btn-cancel-availability').on('click', function() {
        $('#availability-results').slideUp();
        $('#availability-status').empty().hide();
        
        // Reset picker
        $('#picker-dates-list').empty();
        $('#picker-times-grid').empty();
        $('#selected-slot-datetime').val('');
        $('#selected-provider-id').val('');
        
        // Reset custom input
        $('#custom-time-input').val('').prop('disabled', true);
        $('#btn-use-custom-time').prop('disabled', true);
        
        $('#slot-details').hide();
        $('#btn-create-booking').prop('disabled', true);
        availabilityData = null;
    });
    
    // ========================================================================
    // CUSTOM TIME OVERRIDE
    // ========================================================================
    
    /**
     * Find providers available at a specific date/time from availability data
     */
    function findProvidersAtTime(dateStr, timeStr) {
        if (!availabilityData || !dateStr || !timeStr) {
            console.log('ART: findProvidersAtTime - missing data', {dateStr, timeStr, hasAvailability: !!availabilityData});
            return [];
        }
        
        var found = [];
        
        // Normalize input time to match Amelia format (no leading zero for hours < 10)
        // Input: "08:30" -> Compare with "8:30"
        // Input: "14:00" -> Compare with "14:00"
        var inputParts = timeStr.split(':');
        var inputHour = parseInt(inputParts[0], 10);
        var inputMin = inputParts[1];
        var normalizedInput = inputHour + ':' + inputMin; // "8:30" or "14:00"
        
        $.each(availabilityData, function(i, slot) {
            if (slot.date === dateStr) {
                // Normalize slot time the same way
                var slotParts = slot.time.split(':');
                var slotHour = parseInt(slotParts[0], 10);
                var slotMin = slotParts[1];
                var normalizedSlot = slotHour + ':' + slotMin;
                
                if (normalizedSlot === normalizedInput) {
                    found.push({
                        provider_id: slot.provider_id,
                        provider_name: (artDetailData.providers && artDetailData.providers[slot.provider_id]) 
                            ? artDetailData.providers[slot.provider_id] 
                            : 'Provider #' + slot.provider_id,
                        location_id: slot.location_id
                    });
                }
            }
        });
        
        console.log('ART: findProvidersAtTime result', {dateStr, timeStr, normalizedInput, foundCount: found.length});
        return found;
    }
    
    /**
     * Find nearest available slots within a time window (±2 hours)
     * Returns providers sorted by proximity to requested time
     */
    function findNearestAvailableSlots(dateStr, requestedTime) {
        if (!availabilityData || !dateStr || !requestedTime) {
            return [];
        }
        
        // Parse requested time to minutes since midnight
        var reqParts = requestedTime.split(':');
        var reqMinutes = parseInt(reqParts[0], 10) * 60 + parseInt(reqParts[1], 10);
        
        var nearbySlots = [];
        var maxDiffMinutes = 120; // Search within ±2 hours
        
        // Iterate through all slots to find nearby ones
        $.each(availabilityData, function(i, slot) {
            if (slot.date !== dateStr) return;
            
            // Parse slot time
            var slotParts = slot.time.split(':');
            var slotMinutes = parseInt(slotParts[0], 10) * 60 + parseInt(slotParts[1], 10);
            
            // Calculate difference
            var diff = slotMinutes - reqMinutes;
            var absDiff = Math.abs(diff);
            
            // Skip if exact match (handled separately) or too far
            if (absDiff === 0 || absDiff > maxDiffMinutes) return;
            
            // Get provider name from our providers map
            var providerName = artDetailData.providers[slot.provider_id] || slot.provider_name || 'Provider #' + slot.provider_id;
            
            nearbySlots.push({
                provider_id: slot.provider_id,
                provider_name: providerName,
                time: slot.time,
                diff: absDiff,
                direction: diff < 0 ? 'before' : 'after',
                rawDiff: diff
            });
        });
        
        // Sort by absolute difference (closest first)
        nearbySlots.sort(function(a, b) {
            return a.diff - b.diff;
        });
        
        // Limit to closest 6 slots to avoid overwhelming the UI
        // But ensure we show at least one "before" and one "after" if available
        var result = [];
        var hasBefore = false;
        var hasAfter = false;
        var seenProviders = {};
        
        $.each(nearbySlots, function(i, slot) {
            // Prioritize showing variety (before/after, different providers)
            var providerKey = slot.provider_id + '_' + slot.time;
            if (seenProviders[providerKey]) return;
            
            if (result.length < 6) {
                result.push(slot);
                seenProviders[providerKey] = true;
                if (slot.direction === 'before') hasBefore = true;
                if (slot.direction === 'after') hasAfter = true;
            } else if (!hasBefore && slot.direction === 'before') {
                // Make room for a "before" slot
                result.push(slot);
                hasBefore = true;
            } else if (!hasAfter && slot.direction === 'after') {
                // Make room for an "after" slot
                result.push(slot);
                hasAfter = true;
            }
        });
        
        // Re-sort result: show "before" slots first, then "after"
        result.sort(function(a, b) {
            if (a.direction === b.direction) {
                return a.diff - b.diff;
            }
            return a.direction === 'before' ? -1 : 1;
        });
        
        // Debug: Show all available times for this date
        var allTimesForDate = [];
        $.each(availabilityData, function(i, slot) {
            if (slot.date === dateStr && allTimesForDate.indexOf(slot.time) === -1) {
                allTimesForDate.push(slot.time);
            }
        });
        allTimesForDate.sort();
        
        console.log('ART: findNearestAvailableSlots', {
            dateStr: dateStr,
            requestedTime: requestedTime,
            reqMinutes: reqMinutes,
            allTimesForDate: allTimesForDate,
            totalNearby: nearbySlots.length,
            returning: result.length
        });
        
        return result;
    }
    
    /**
     * Update provider list column based on entered time
     * Uses the Availability Engine for detailed availability checking
     */
    var selectedProviderId = null;
    var availabilityEngineEnabled = true; // Set to false to use old slots-based logic
    
    function updateProviderList() {
        var activeDateBtn = $('#picker-dates-list .art-picker-date-btn.active');
        var providerList = $('#provider-list');
        var confirmSection = $('#picker-confirm-section');
        
        var providerCount = artDetailData.providers ? Object.keys(artDetailData.providers).length : 0;
        
        // Reset selection
        selectedProviderId = null;
        confirmSection.hide();
        $('#btn-use-custom-time').prop('disabled', true);
        
        // If no providers loaded yet, show loading
        if (providerCount === 0) {
            providerList.html('<div class="provider-placeholder"><?php _e('Loading providers...', 'amelia-cpt-sync'); ?></div>');
            return;
        }
        
        var dateStr = activeDateBtn.length ? activeDateBtn.data('date') : null;
        var timeStr = $('#custom-time-input').val();
        
        if (!dateStr) {
            providerList.html('<div class="provider-placeholder"><?php _e('Select a date first', 'amelia-cpt-sync'); ?></div>');
            return;
        }
        
        if (!timeStr) {
            providerList.html('<div class="provider-placeholder"><?php _e('Enter a time to see availability', 'amelia-cpt-sync'); ?></div>');
            return;
        }
        
        // Use Availability Engine if enabled
        if (availabilityEngineEnabled) {
            checkProviderAvailabilityEngine(dateStr, timeStr);
            return;
        }
        
        // Fallback to old slots-based logic
        updateProviderListFromSlots(dateStr, timeStr);
    }
    
    /**
     * Check provider availability using the Availability Engine
     */
    function checkProviderAvailabilityEngine(dateStr, timeStr) {
        var providerList = $('#provider-list');
        var serviceId = $('#pillar-service').val();
        var duration = $('#pillar-duration-hidden').val() || artDetailData.serviceDuration || 3600;
        var locationId = $('#pillar-location').val() || 0;
        
        if (!serviceId) {
            providerList.html('<div class="provider-placeholder"><?php _e('Select a service first', 'amelia-cpt-sync'); ?></div>');
            return;
        }
        
        // Show loading state
        providerList.html(
            '<div class="provider-loading">' +
                '<span class="dashicons dashicons-update spin"></span> ' +
                '<?php _e('Checking availability...', 'amelia-cpt-sync'); ?>' +
            '</div>'
        );
        
        // Call Availability Engine AJAX
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'art_check_provider_availability',
                nonce: artDetailData.nonce,
                date: dateStr,
                time: timeStr,
                service_id: serviceId,
                duration: duration,
                location_id: locationId
            },
            success: function(response) {
                if (response.success) {
                    renderAvailabilityEngineResults(response.data.providers, response.data.error, response.data.message);
                } else {
                    providerList.html(
                        '<div class="provider-error">' +
                            '<span class="dashicons dashicons-warning"></span> ' +
                            (response.data.message || '<?php _e('Error checking availability', 'amelia-cpt-sync'); ?>') +
                        '</div>'
                    );
                }
            },
            error: function() {
                providerList.html(
                    '<div class="provider-error">' +
                        '<span class="dashicons dashicons-warning"></span> ' +
                        '<?php _e('Network error. Please try again.', 'amelia-cpt-sync'); ?>' +
                    '</div>'
                );
            }
        });
    }
    
    /**
     * Render results from Availability Engine
     */
    function renderAvailabilityEngineResults(providers, hasError, errorMessage) {
        var providerList = $('#provider-list');
        var html = '';
        
        // Show warning if there was an error
        if (hasError && errorMessage) {
            html += '<div class="provider-warning" style="padding: 8px; background: #fff3cd; border-radius: 4px; margin-bottom: 10px; font-size: 12px;">' +
                '<span class="dashicons dashicons-warning" style="color: #856404;"></span> ' +
                errorMessage +
            '</div>';
        }
        
        // Group providers by status
        var available = providers.filter(function(p) { return p.status === 'available'; });
        var mightConflict = providers.filter(function(p) { return p.status === 'might_conflict'; });
        var forceBook = providers.filter(function(p) { return p.status === 'force_book' || p.status === 'not_available'; });
        
        // Render Available providers
        if (available.length > 0) {
            html += '<div class="provider-group">';
            html += '<div class="provider-group-label available"><?php _e('✓ Available', 'amelia-cpt-sync'); ?></div>';
            $.each(available, function(i, p) {
                html += buildProviderItemWithConflicts(p.id, p.name, getInitials(p.name), '<?php _e('Available', 'amelia-cpt-sync'); ?>', []);
            });
            html += '</div>';
        }
        
        // Render Might Conflict providers
        if (mightConflict.length > 0) {
            html += '<div class="provider-group">';
            html += '<div class="provider-group-label warning"><?php _e('⚠️ Might Conflict', 'amelia-cpt-sync'); ?></div>';
            $.each(mightConflict, function(i, p) {
                html += buildProviderItemWithConflicts(p.id, p.name, getInitials(p.name), '<?php _e('Might Conflict', 'amelia-cpt-sync'); ?>', p.conflicts || []);
            });
            html += '</div>';
        }
        
        // Render Force Book providers
        if (forceBook.length > 0) {
            html += '<div class="provider-group">';
            html += '<div class="provider-group-label force"><?php _e('Force Book', 'amelia-cpt-sync'); ?></div>';
            $.each(forceBook, function(i, p) {
                var conflictText = (p.conflicts && p.conflicts.length > 0) ? p.conflicts.join(', ') : '';
                html += buildProviderItemWithConflicts(p.id, p.name, getInitials(p.name), '<?php _e('Override', 'amelia-cpt-sync'); ?>', p.conflicts || []);
            });
            html += '</div>';
        }
        
        if (html === '' || providers.length === 0) {
            html = '<div class="provider-placeholder"><?php _e('No providers available for this service', 'amelia-cpt-sync'); ?></div>';
        }
        
        providerList.html(html);
    }
    
    /**
     * Build provider item with conflict details
     */
    function buildProviderItemWithConflicts(id, name, initials, status, conflicts) {
        var conflictHtml = '';
        if (conflicts && conflicts.length > 0) {
            conflictHtml = '<div class="provider-conflicts">' + conflicts.join('<br>') + '</div>';
        }
        
        return '<div class="provider-item" data-provider-id="' + id + '">' +
            '<div class="provider-avatar">' + initials + '</div>' +
            '<div class="provider-info">' +
                '<div class="provider-name">' + name + '</div>' +
                '<div class="provider-status">' + status + '</div>' +
                conflictHtml +
            '</div>' +
            '<div class="provider-check"><span class="dashicons dashicons-yes"></span></div>' +
        '</div>';
    }
    
    /**
     * Fallback: Update provider list from slots data (old logic)
     */
    function updateProviderListFromSlots(dateStr, timeStr) {
        var providerList = $('#provider-list');
        var html = '';
        var shownIds = [];
        
        // Find which providers are available at this EXACT custom time
        var availableAtTime = findProvidersAtTime(dateStr, timeStr);
        
        // Show exact match providers first (if any)
        if (availableAtTime.length > 0) {
            html += '<div class="provider-group">';
            html += '<div class="provider-group-label available"><?php _e('✓ Available at this time', 'amelia-cpt-sync'); ?></div>';
            $.each(availableAtTime, function(i, p) {
                html += buildProviderItem(p.provider_id, p.provider_name, getInitials(p.provider_name), '<?php _e('Available', 'amelia-cpt-sync'); ?>');
                shownIds.push(String(p.provider_id));
            });
            html += '</div>';
        } else {
            // No exact match - find nearest available slots
            var nearbySlots = findNearestAvailableSlots(dateStr, timeStr);
            
            if (nearbySlots.length > 0) {
                // Group by time slot
                var slotsByTime = {};
                $.each(nearbySlots, function(i, slot) {
                    if (!slotsByTime[slot.time]) {
                        slotsByTime[slot.time] = {
                            time: slot.time,
                            diff: slot.diff,
                            direction: slot.direction,
                            providers: []
                        };
                    }
                    slotsByTime[slot.time].providers.push(slot);
                });
                
                // Render each nearby time group
                $.each(slotsByTime, function(time, group) {
                    var dirLabel = group.direction === 'before' ? '<?php _e('Earlier', 'amelia-cpt-sync'); ?>' : '<?php _e('Later', 'amelia-cpt-sync'); ?>';
                    var diffMins = group.diff;
                    var diffLabel = diffMins < 60 ? diffMins + ' <?php _e('min', 'amelia-cpt-sync'); ?>' : Math.round(diffMins/60) + ' <?php _e('hr', 'amelia-cpt-sync'); ?>';
                    
                    html += '<div class="provider-group">';
                    html += '<div class="provider-group-label nearby">' + dirLabel + ': ' + formatTime12(time) + ' <span style="opacity:0.7">(' + diffLabel + ')</span></div>';
                    $.each(group.providers, function(i, p) {
                        html += buildProviderItem(p.provider_id, p.provider_name, getInitials(p.provider_name), formatTime12(time));
                        shownIds.push(String(p.provider_id));
                    });
                    html += '</div>';
                });
            }
        }
        
        // Add remaining providers for force booking
        var forceProviders = [];
        $.each(artDetailData.providers, function(id, name) {
            if (shownIds.indexOf(String(id)) === -1) {
                forceProviders.push({ id: id, name: name });
            }
        });
        
        if (forceProviders.length > 0) {
            html += '<div class="provider-group">';
            html += '<div class="provider-group-label force"><?php _e('Force Book', 'amelia-cpt-sync'); ?></div>';
            $.each(forceProviders, function(i, prov) {
                html += buildProviderItem(prov.id, prov.name, getInitials(prov.name), '<?php _e('Override schedule', 'amelia-cpt-sync'); ?>');
            });
            html += '</div>';
        }
        
        if (html === '') {
            html = '<div class="provider-placeholder"><?php _e('No providers available', 'amelia-cpt-sync'); ?></div>';
        }
        
        providerList.html(html);
    }
    
    function buildProviderItem(id, name, initials, status) {
        return '<div class="provider-item" data-provider-id="' + id + '">' +
            '<div class="provider-avatar">' + initials + '</div>' +
            '<div class="provider-info">' +
                '<div class="provider-name">' + name + '</div>' +
                '<div class="provider-status">' + status + '</div>' +
            '</div>' +
            '<div class="provider-check"><span class="dashicons dashicons-yes"></span></div>' +
        '</div>';
    }
    
    function getInitials(name) {
        if (!name) return '??';
        var parts = name.split(' ');
        if (parts.length >= 2) {
            return (parts[0][0] + parts[1][0]).toUpperCase();
        }
        return name.substring(0, 2).toUpperCase();
    }
    
    function formatTime12(timeStr) {
        var parts = timeStr.split(':');
        var hour = parseInt(parts[0]);
        var min = parts[1] || '00';
        var ampm = hour >= 12 ? 'PM' : 'AM';
        var hour12 = hour % 12;
        hour12 = hour12 ? hour12 : 12;
        return hour12 + ':' + min + ' ' + ampm;
    }
    
    // Handle provider selection
    $(document).on('click', '.provider-item', function() {
        var item = $(this);
        var providerId = item.data('provider-id');
        
        // Toggle selection
        if (selectedProviderId == providerId) {
            // Deselect
            item.removeClass('selected');
            selectedProviderId = null;
            $('#picker-confirm-section').hide();
            $('#btn-use-custom-time').prop('disabled', true);
        } else {
            // Select this one
            $('.provider-item').removeClass('selected');
            item.addClass('selected');
            selectedProviderId = providerId;
            $('#picker-confirm-section').show();
            $('#btn-use-custom-time').prop('disabled', false);
        }
    });
    
    // Update provider list when custom time changes
    $('#custom-time-input').on('change input', function() {
        updateProviderList();
    });
    
    // Also update when date is selected
    $(document).on('click', '.art-picker-date-btn', function() {
        setTimeout(function() {
            updateProviderList();
        }, 100);
    });
    
    // When "Confirm Selection" is clicked
    $('#btn-use-custom-time').on('click', function() {
        // Get date
        var activeDateBtn = $('#picker-dates-list .art-picker-date-btn.active');
        if (!activeDateBtn.length) { 
            showNotice('Please select a date first', 'error');
            return;
        }
        var dateStr = activeDateBtn.data('date'); // "YYYY-MM-DD"
        
        // Get time
        var timeStr = $('#custom-time-input').val(); // "HH:mm"
        if (!timeStr) { 
            showNotice('Please enter a time', 'error');
            return;
        }
        
        // Get Provider from selection
        if (!selectedProviderId) {
            showNotice('Please select a provider', 'error');
            return;
        }
        var providerId = selectedProviderId;
        
        // Format time for display
        var timeParts = timeStr.split(':');
        var hour = parseInt(timeParts[0]);
        var min = timeParts[1];
        var ampm = hour >= 12 ? 'PM' : 'AM';
        var hour12 = hour % 12;
        hour12 = hour12 ? hour12 : 12;
        var timeDisplay = hour12 + ':' + min + ' ' + ampm;
        
        // Check if this is a force booking (not in available slots)
        var availableAtTime = findProvidersAtTime(dateStr, timeStr);
        var isForced = !availableAtTime.some(function(p) { return p.provider_id == providerId; });
        
        // Get Provider Name
        var providerName = $('#custom-provider-select option:selected').text().replace(' ✓', '');
        
        // Construct custom slot object
        var slot = {
            date: dateStr,
            time: timeStr,
            datetime: dateStr + ' ' + timeStr,
            provider_id: providerId,
            location_id: $('#pillar-location').val() || 0
        };
        
        // Update UI
        $('.art-time-btn').removeClass('active'); // Deselect grid
        var displayLabel = timeDisplay + ' with ' + providerName;
        if (isForced) {
            displayLabel += ' (FORCED)';
        }
        selectSlot(slot, displayLabel);
    });
    
    // Enable custom time inputs when date is selected (Updated renderFilteredDates)
    // We'll attach this logic inside the date click handler below
    
    // ========================================================================
    // VISUAL CALENDAR (IFRAME) - With Zoom & Expand
    // ========================================================================
    
    var calendarZoom = <?php echo $user_calendar_zoom; ?>;
    var calendarExpanded = false;
    
    function updateIframeZoom() {
        var scale = calendarZoom / 100;
        var iframe = $('#amelia-calendar-frame');
        
        // Scale the iframe and adjust its dimensions to compensate
        iframe.css({
            'transform': 'scale(' + scale + ')',
            'width': (100 / scale) + '%',
            'height': (100 / scale) + '%'
        });
        
        $('#zoom-level').text(calendarZoom + '%');
    }
    
    function saveZoomPreference() {
        $.post(ajaxurl, {
            action: 'art_save_calendar_zoom',
            nonce: artDetailData.nonce,
            zoom: calendarZoom
        });
    }
    
    // Zoom In
    $('#btn-zoom-in').on('click', function() {
        if (calendarZoom < 150) {
            calendarZoom += 10;
            updateIframeZoom();
            saveZoomPreference();
        }
    });
    
    // Zoom Out
    $('#btn-zoom-out').on('click', function() {
        if (calendarZoom > 50) {
            calendarZoom -= 10;
            updateIframeZoom();
            saveZoomPreference();
        }
    });
    
    // Reset Zoom
    $('#btn-zoom-reset').on('click', function() {
        calendarZoom = 100;
        updateIframeZoom();
        saveZoomPreference();
    });
    
    // Calendar expansion modes
    var calendarMode = 'normal'; // 'normal', 'locked', 'unlocked'
    var dragOffset = { x: 0, y: 0 };
    var isDragging = false;
    
    function resetCalendarMode() {
        var container = $('#calendar-visual-container');
        container.removeClass('expanded-locked expanded-unlocked');
        container.css({ top: '', left: '', right: '', bottom: '', width: '', height: '' });
        $('#btn-expand-locked').removeClass('active');
        $('#btn-expand-unlocked').removeClass('active');
        calendarMode = 'normal';
        calendarExpanded = false;
    }
    
    // Locked Expand Mode
    $('#btn-expand-locked').on('click', function() {
        var container = $('#calendar-visual-container');
        var btn = $(this);
        
        if (calendarMode === 'locked') {
            // Collapse back to normal
            resetCalendarMode();
        } else {
            // Switch to locked mode
            resetCalendarMode();
            container.addClass('expanded-locked');
            btn.addClass('active');
            calendarMode = 'locked';
            calendarExpanded = true;
        }
    });
    
    // Unlocked/Floating Mode
    $('#btn-expand-unlocked').on('click', function() {
        var container = $('#calendar-visual-container');
        var btn = $(this);
        
        if (calendarMode === 'unlocked') {
            // Collapse back to normal
            resetCalendarMode();
        } else {
            // Switch to unlocked mode
            resetCalendarMode();
            
            // Set initial position and size (centered, reasonable size)
            var winWidth = $(window).width();
            var winHeight = $(window).height();
            var width = Math.min(900, winWidth - 100);
            var height = Math.min(600, winHeight - 100);
            var left = (winWidth - width) / 2;
            var top = Math.max(50, (winHeight - height) / 2);
            
            container.addClass('expanded-unlocked');
            container.css({
                top: top + 'px',
                left: left + 'px',
                width: width + 'px',
                height: height + 'px'
            });
            
            btn.addClass('active');
            calendarMode = 'unlocked';
            calendarExpanded = true;
        }
    });
    
    // Dragging functionality for unlocked mode
    var $calendarContainer = $('#calendar-visual-container');
    
    $calendarContainer.on('mousedown', '.calendar-toolbar', function(e) {
        if (calendarMode !== 'unlocked') return;
        if ($(e.target).closest('.calendar-controls').length) return; // Don't drag when clicking controls
        
        isDragging = true;
        
        var containerPos = $calendarContainer.position();
        dragOffset.x = e.pageX - containerPos.left;
        dragOffset.y = e.pageY - containerPos.top;
        
        // Add dragging class for visual feedback
        $calendarContainer.addClass('is-dragging');
        
        // Prevent text selection while dragging
        e.preventDefault();
    });
    
    $(document).on('mousemove.calendarDrag', function(e) {
        if (!isDragging || calendarMode !== 'unlocked') return;
        
        var newLeft = e.pageX - dragOffset.x;
        var newTop = e.pageY - dragOffset.y;
        
        // Keep within viewport bounds (with some padding)
        var maxLeft = $(window).width() - $calendarContainer.outerWidth();
        var maxTop = $(window).height() - 50; // Allow some to go off bottom
        
        newLeft = Math.max(0, Math.min(newLeft, maxLeft));
        newTop = Math.max(0, Math.min(newTop, maxTop));
        
        $calendarContainer.css({
            left: newLeft + 'px',
            top: newTop + 'px'
        });
    });
    
    $(document).on('mouseup.calendarDrag', function() {
        if (isDragging) {
            isDragging = false;
            $calendarContainer.removeClass('is-dragging');
        }
    });
    
    // ESC key to collapse any expanded mode
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && calendarExpanded) {
            resetCalendarMode();
        }
    });
    
    function toggleCalendar(show) {
        if (show) {
            $('#calendar-visual-container').slideDown();
            $('#btn-toggle-calendar').hide();
        } else {
            // If expanded, collapse first
            if (calendarExpanded) {
                resetCalendarMode();
            }
            $('#calendar-visual-container').slideUp();
            $('#btn-toggle-calendar').show();
        }
    }
    
    $('#btn-toggle-calendar').on('click', function(e) {
        e.preventDefault();
        toggleCalendar(true);
    });
    
    $('#btn-close-calendar').on('click', function(e) {
        e.preventDefault();
        toggleCalendar(false);
    });
    
    // Auto-show calendar on load (it's already visible in HTML, just ensuring state)
    $('#btn-toggle-calendar').hide();
    
    // Initialize zoom on load
    updateIframeZoom();
    
    // Inject CSS into iframe when loaded to hide WP admin UI and Amelia headers
    $('#amelia-calendar-frame').on('load', function() {
        var frame = $(this);
        frame.css('opacity', '1');
        
        try {
            var frameDoc = frame[0].contentWindow.document;
            
            // CSS to hide WP Admin UI + Amelia Header/Logo/Add Button
            var css = `
                /* Hide WP Admin UI */
                #adminmenumain, #wpadminbar, #wpfooter { display: none !important; }
                html.wp-toolbar { padding-top: 0 !important; }
                #wpcontent { margin-left: 0 !important; padding: 0 !important; }
                
                /* Hide Amelia Header & Logo */
                .am-header, .am-logo, .am-page-title { display: none !important; }
                .el-col.el-col-6 { visibility: hidden !important; height: 0 !important; } /* Hides logo column structure */
                
                /* Hide "New Appointment" Button */
                #am-button-new, .am-button-new, #am-plus-symbol { display: none !important; }
                
                /* Clean up layout */
                .am-wrap { margin: 0 !important; padding: 10px !important; }
                .am-body { padding-top: 0 !important; }
            `;
            
            var style = frameDoc.createElement('style');
            style.type = 'text/css';
            if (style.styleSheet) {
                style.styleSheet.cssText = css;
            } else {
                style.appendChild(frameDoc.createTextNode(css));
            }
            frameDoc.head.appendChild(style);
            
        } catch (e) {
            console.log('ART: Could not inject CSS into calendar iframe (likely cross-origin restriction if domains differ)');
        }
    });
    
    // ========================================================================
    // INITIALIZATION
    // ========================================================================
    
    // Fetch employees for the current service
    function fetchServiceEmployees(callback) {
        var serviceId = $('#pillar-service').val();
        if (!serviceId) {
            if (callback) callback();
            return;
        }
        
        $.post(ajaxurl, {
            action: 'art_get_service_employees',
            nonce: artDetailData.nonce,
            service_id: serviceId
        }, function(response) {
            if (response.success) {
                // Store the map: ID -> Name
                artDetailData.providers = response.data.provider_map;
                console.log('ART: Loaded providers', artDetailData.providers);
                
                // Update Custom Time Dropdown
                var customSelect = $('#custom-provider-select');
                customSelect.empty().append('<option value="">' + '<?php _e('-- Select Provider --', 'amelia-cpt-sync'); ?>' + '</option>');
                
                $.each(response.data.provider_map, function(id, name) {
                    customSelect.append('<option value="' + id + '">' + name + '</option>');
                });
                
                // Update Main Filter if it exists
                var filterSelect = $('#filter-provider');
                var currentVal = filterSelect.val();
                
                // Only update filter options if not "all" or preserve selection
                filterSelect.empty().append('<option value="all">' + '<?php _e('All Available Employees', 'amelia-cpt-sync'); ?>' + '</option>');
                $.each(response.data.provider_map, function(id, name) {
                    filterSelect.append('<option value="' + id + '">' + name + '</option>');
                });
                
                if (currentVal && currentVal !== 'all') {
                    filterSelect.val(currentVal);
                }
                
                // If availability data exists, re-render with correct names
                if (availabilityData && availabilityData.length > 0) {
                    var activeDate = $('#picker-dates-list .art-picker-date-btn.active').data('date');
                    if (activeDate) {
                        // Re-render dates and times with new provider names
                        var selectedFilter = $('#filter-provider').val() || 'all';
                        renderFilteredDates(selectedFilter);
                    }
                }
            }
            
            if (callback) callback();
        });
    }
    
    // Call on load
    if (artDetailData.currentService) {
        fetchServiceEmployees();
    }
    
    // Call when service changes
    $('#pillar-service').on('change', function() {
        fetchServiceEmployees();
    });
    
    // === SPINNING ANIMATION FOR DASHICONS ===
    $('<style>.dashicons.spin { animation: spin 1s linear infinite; } @keyframes spin { 100% { transform: rotate(360deg); } }</style>').appendTo('head');
});
</script>




