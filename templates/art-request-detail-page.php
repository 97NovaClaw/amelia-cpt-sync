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

// Get availability engine settings for validation
$avail_settings = get_option('art_availability_settings', array());
$location_mode = $avail_settings['location_mode'] ?? 'ignore';
$location_required_for_availability = ($location_mode === 'strict' || $location_mode === 'soft');

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

// Parse start_datetime into separate date and time for new picker UI
// PRIORITY: Use active booking data (Amelia) over exploration data (wp_art_requests)
// This ensures pillars always match the actual booking on page load
$pillar_date = '';
$pillar_time = '';

if (!empty($active_booking) && !empty($active_booking->formatted_date_time)) {
    // Use ACTUAL BOOKING from Amelia as source of truth
    // formatted_date_time is already timezone-converted to local (format: "2025-12-28T16:05")
    $parts = explode('T', $active_booking->formatted_date_time);
    if (count($parts) === 2) {
        $pillar_date = $parts[0];  // "2025-12-28"
        $pillar_time = $parts[1];  // "16:05" (24-hour format)
    }
    
    error_log(sprintf(
        '[ART] Request #%d: Populating pillars from active booking (Date: %s, Time: %s)',
        $request_id,
        $pillar_date,
        $pillar_time
    ));
} elseif (!empty($request->start_datetime)) {
    // Only use request data if no active booking exists (new requests or exploration)
    $start_local = get_date_from_gmt($request->start_datetime);
    $pillar_date = date('Y-m-d', strtotime($start_local));
    $pillar_time = date('H:i', strtotime($start_local));
    
    error_log(sprintf(
        '[ART] Request #%d: Populating pillars from request data (Date: %s, Time: %s)',
        $request_id,
        $pillar_date,
        $pillar_time
    ));
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

// Get original service/category names (from form submission, never changes)
$original_service_name = '';
$original_category_name = '';

if (!empty($request->original_service_id)) {
    foreach ($service_options as $svc) {
        if (intval($svc['id']) === intval($request->original_service_id)) {
            $original_service_name = $svc['name'];
            break;
        }
    }
}

if (!empty($request->original_category_id)) {
    foreach ($category_options as $cat) {
        if (intval($cat['id']) === intval($request->original_category_id)) {
            $original_category_name = $cat['name'];
            break;
        }
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
                            
                            // Get assigned resource(s) for this appointment (ALL of them for composite mode)
                            $assigned_resources_all = $wpdb->get_results($wpdb->prepare(
                                "SELECT ra.amelia_resource_id as resource_id, r.name as resource_name, ra.quantity_used
                                 FROM {$wpdb->prefix}art_resource_assignments ra
                                 LEFT JOIN {$wpdb->prefix}amelia_resources r ON ra.amelia_resource_id = r.id
                                 WHERE ra.amelia_appointment_id = %d AND ra.status = 'active'
                                 ORDER BY ra.id ASC",
                                $active_booking->amelia_appointment_id
                            ));
                            
                            // First resource for backward compatibility (single activeResource)
                            $assigned_resource = !empty($assigned_resources_all) ? $assigned_resources_all[0] : null;
                            
                            // Debug log
                            amelia_cpt_sync_debug_log('ART Detail Page: Resource query for appointment #' . $active_booking->amelia_appointment_id . ' returned: ' . count($assigned_resources_all) . ' resource(s)');
                            if ($assigned_resource) {
                                amelia_cpt_sync_debug_log('ART Detail Page: Primary resource: ' . json_encode($assigned_resource));
                            }
                            
                            // Attach full details to active_booking object
                            $active_booking->service_name = $appointment_full->service_name ?? '';
                            $active_booking->category_name = $category_name;
                            $active_booking->provider_name = $provider ? trim($provider->firstName . ' ' . $provider->lastName) : '';
                            $active_booking->location_name = $location_name;
                            $active_booking->resource_id = $assigned_resource ? intval($assigned_resource->resource_id) : null;
                            $active_booking->resource_name = $assigned_resource ? $assigned_resource->resource_name : null;
                            $active_booking->service_id = $appointment_full->serviceId;
                            $active_booking->category_id = $appointment_full->category_id;
                            
                            // All assigned resources (for composite mode - includes quantity_used)
                            $active_booking->all_resources = array();
                            foreach ($assigned_resources_all as $ar) {
                                $active_booking->all_resources[] = array(
                                    'id' => intval($ar->resource_id),
                                    'name' => $ar->resource_name,
                                    'quantity_used' => intval($ar->quantity_used)
                                );
                            }
                            
                            amelia_cpt_sync_debug_log('ART Detail Page: activeResource will be: ' . json_encode(array('id' => $active_booking->resource_id, 'name' => $active_booking->resource_name)));
                            if (count($active_booking->all_resources) > 1) {
                                amelia_cpt_sync_debug_log('ART Detail Page: activeResources (composite): ' . json_encode($active_booking->all_resources));
                            }
                            
                            // Convert UTC booking times to local timezone for display
                            $wp_tz = wp_timezone();
                            $utc_tz = new DateTimeZone('UTC');
                            $dt_start = new DateTime($appointment_full->bookingStart, $utc_tz);
                            $dt_start->setTimezone($wp_tz);
                            
                            $active_booking->formatted_date = $dt_start->format('l, F jS, Y');
                            $active_booking->formatted_time = $dt_start->format('h:i A');
                            $active_booking->formatted_date_time = $dt_start->format('Y-m-d\TH:i'); // For datetime-local input
                            $active_booking->provider_id = $appointment_full->providerId;
                            $active_booking->location_id = $appointment_full->locationId ?? null;
                            $active_booking->date_only = $dt_start->format('Y-m-d');
                            
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
                <?php 
                $booking_type = $active_booking ? ($active_booking->booking_type ?? 'confirmed') : 'confirmed';
                $is_tentative = ($booking_type === 'tentative');
                ?>
                <div id="active-booking-card" class="art-card art-booking-card <?php echo $is_tentative ? 'booking-tentative' : 'booking-confirmed'; ?>" style="<?php echo $active_booking ? '' : 'display: none;'; ?>" data-booking-type="<?php echo esc_attr($booking_type); ?>">
                    <div class="card-header booking-header">
                        <h3>
                            <span class="dashicons dashicons-calendar-alt"></span>
                            <?php _e('Active Amelia Booking', 'amelia-cpt-sync'); ?>
                        </h3>
                        <span class="booking-status-badge <?php echo $is_tentative ? 'badge-tentative' : 'badge-confirmed'; ?>">
                            <?php echo $is_tentative ? __('Tentative', 'amelia-cpt-sync') : __('Confirmed', 'amelia-cpt-sync'); ?>
                        </span>
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
                            <h3>
                                <?php 
                                _e('Core Pillars', 'amelia-cpt-sync');
                                
                                // Show original form-submitted service/category (read-only, never changes)
                                $original_pillars = array();
                                
                                if (!empty($original_service_name)) {
                                    $original_pillars[] = $original_service_name;
                                }
                                
                                if (!empty($original_category_name)) {
                                    $original_pillars[] = $original_category_name;
                                }
                                
                                if (!empty($original_pillars)) {
                                    echo ' <span class="original-request-time">(' . __('Requested:', 'amelia-cpt-sync') . ' ' . esc_html(implode(' - ', $original_pillars)) . ')</span>';
                                }
                                ?>
                            </h3>
                        </div>
                        <div class="card-body">
                            <?php
                            // Determine effective pillar values (booking takes priority over request)
                            $effective_category_id = (!empty($active_booking->category_id)) 
                                ? $active_booking->category_id 
                                : $request->category_id;
                                
                            $effective_service_id = (!empty($active_booking->service_id)) 
                                ? $active_booking->service_id 
                                : $request->service_id;

                            $effective_location_id = (!empty($active_booking->location_id)) 
                                ? $active_booking->location_id 
                                : $request->location_id;
                            ?>
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
                                                    <?php selected($effective_category_id, $category['id']); ?>>
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
                                                    <?php selected($effective_service_id, $service['id']); ?>>
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
                            <h3>
                                <?php 
                                _e('Time & Duration', 'amelia-cpt-sync');
                                
                                // Build original requested time display from ORIGINAL form submission data (read-only)
                                // These columns are NEVER updated, they preserve what the customer originally requested
                                $original_display = array();
                                
                                if (!empty($request->original_start_datetime) && $request->original_start_datetime !== '0000-00-00 00:00:00') {
                                    $start_local = get_date_from_gmt($request->original_start_datetime);
                                    $date_str = date_i18n('M j', strtotime($start_local));
                                    
                                    // Check if it's just a date (time is 00:00:00)
                                    $time_parts = explode(' ', $start_local);
                                    $just_time = $time_parts[1] ?? '00:00:00';
                                    
                                    if ($just_time === '00:00:00') {
                                        // Only date provided (Date Only mode)
                                        $original_display[] = $date_str;
                                    } else {
                                        // Date and time provided
                                        $time_str = date_i18n('g:i A', strtotime($start_local));
                                        
                                        if (!empty($request->original_end_datetime) && $request->original_end_datetime !== '0000-00-00 00:00:00') {
                                            $end_local = get_date_from_gmt($request->original_end_datetime);
                                            $end_time_str = date_i18n('g:i A', strtotime($end_local));
                                            $original_display[] = "$date_str, $time_str - $end_time_str";
                                        } else {
                                            $original_display[] = "$date_str, $time_str";
                                        }
                                    }
                                } elseif (!empty($request->original_duration_seconds) && $request->original_duration_seconds > 0) {
                                    // Only duration provided (Duration Only mode)
                                    $hours = floor($request->original_duration_seconds / 3600);
                                    $mins = floor(($request->original_duration_seconds % 3600) / 60);
                                    if ($hours > 0) {
                                        $original_display[] = __('Duration:', 'amelia-cpt-sync') . " {$hours}h" . ($mins > 0 ? " {$mins}m" : "");
                                    } else {
                                        $original_display[] = __('Duration:', 'amelia-cpt-sync') . " {$mins}m";
                                    }
                                }
                                
                                if (!empty($original_display)) {
                                    echo ' <span class="original-request-time">(' . __('Requested:', 'amelia-cpt-sync') . ' ' . esc_html(implode(', ', $original_display)) . ')</span>';
                                }
                                ?>
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="pillar-grid-3">
                                <!-- Date Picker -->
                                <div class="form-field">
                                    <label for="pillar-date">
                                        <?php _e('Date', 'amelia-cpt-sync'); ?>
                                    </label>
                                    <input type="date" 
                                           id="pillar-date" 
                                           name="pillar_date" 
                                           class="form-input"
                                           value="<?php echo esc_attr($pillar_date); ?>">
                                </div>
                                
                                <!-- Time Picker -->
                                <div class="form-field">
                                    <label for="pillar-time">
                                        <?php _e('Time', 'amelia-cpt-sync'); ?>
                                    </label>
                                    <input type="time" 
                                           id="pillar-time" 
                                           name="pillar_time" 
                                           class="form-input"
                                           value="<?php echo esc_attr($pillar_time); ?>">
                                </div>
                                
                                <!-- Duration Selector -->
                                <div class="form-field">
                                    <label for="pillar-duration-selector">
                                        <?php _e('Duration (HH:MM)', 'amelia-cpt-sync'); ?>
                                        <span id="service-duration-badge" class="service-duration-badge" style="display: none; margin-left: 8px;">
                                            <button type="button" class="refresh-icon" title="Refresh service duration">↻</button>
                                            <span class="duration-text"></span>
                                        </span>
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
                                    <input type="hidden" 
                                           id="pillar-duration-seconds" 
                                           name="duration_seconds" 
                                           value="<?php echo esc_attr($request->duration_seconds); ?>">
                                </div>
                            </div>
                            
                            <!-- Calculated Date/Time Summary -->
                            <div class="pillar-datetime-summary" id="pillar-datetime-summary" style="margin-top: 16px; padding: 12px; background: #F8FAFC; border-radius: 6px; display: none;">
                                <div style="display: flex; align-items: center; gap: 12px; font-size: 14px; color: #475569;">
                                    <strong style="color: #0F172A;">Start:</strong> <span id="pillar-start-display">—</span>
                                    <span style="color: #94A3B8;">→</span>
                                    <strong style="color: #0F172A;">End:</strong> <span id="pillar-end-display">—</span>
                                </div>
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
                                    <div id="price-suggestion" style="display: none; margin-top: 8px; font-size: 13px; color: #64748B;">
                                        <span class="dashicons dashicons-lightbulb" style="font-size: 14px; vertical-align: middle;"></span>
                                        <span id="price-suggestion-text"></span>
                                        <button type="button" id="btn-apply-suggested-price" class="button button-small" style="margin-left: 8px; vertical-align: middle;">
                                            <?php _e('Apply', 'amelia-cpt-sync'); ?>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Auto-save indicator (button hidden, auto-saves on field changes) -->
                    <div class="form-actions" style="justify-content: flex-end;">
                        <span class="save-indicator" style="display: none; font-size: 13px; color: #666;">
                            <span class="dashicons dashicons-update spin" style="display: none;"></span>
                            <span class="status-text"></span>
                        </span>
                    </div>
                </form>
                
                <!-- Panel 3: Availability & Booking Engine -->
                <div class="art-card" id="availability-section">
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
                                
                                <button type="button" id="btn-reset-to-current" class="btn-secondary" style="display: none;">
                                    <span class="dashicons dashicons-image-rotate"></span>
                                    <?php _e('Reset to Current Booking', 'amelia-cpt-sync'); ?>
                                </button>
                                
                                <button type="button" id="btn-toggle-calendar" class="btn-secondary" style="display: none;">
                                    <span class="dashicons dashicons-visibility"></span>
                                    <?php _e('Show Calendar', 'amelia-cpt-sync'); ?>
                                </button>
                            </div>
                            
                            <div id="availability-status" style="margin-top: 12px; display: none;"></div>
                        </div>
                        
                        <?php if ($active_booking): ?>
                        <!-- Original Booking Status (Always visible when there's an active booking) -->
                        <div id="original-booking-status" style="margin-top: 20px; padding: 16px; background: #F0F9FF; border: 2px solid #3B82F6; border-radius: 8px;">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                                <h4 style="margin: 0; color: #1E40AF; display: flex; align-items: center; gap: 8px;">
                                    <span class="dashicons dashicons-saved" style="font-size: 20px;"></span>
                                    <?php _e('Current Booking', 'amelia-cpt-sync'); ?>
                                </h4>
                                <span id="booking-status-badge" style="padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600;">
                                    <?php 
                                    if ($active_booking->status === 'approved') {
                                        echo '<span style="background: #DEF7EC; color: #03543F;">✅ ' . __('Confirmed', 'amelia-cpt-sync') . '</span>';
                                    } else {
                                        echo '<span style="background: #FEF3C7; color: #92400E;">⏳ ' . __('Tentative', 'amelia-cpt-sync') . '</span>';
                                    }
                                    ?>
                                </span>
                            </div>
                            <div style="display: grid; grid-template-columns: auto 1fr; gap: 8px 16px; font-size: 14px; line-height: 1.8;">
                                <strong style="color: #64748B;"><?php _e('Service:', 'amelia-cpt-sync'); ?></strong>
                                <span><?php echo esc_html($active_booking->service_name); ?></span>
                                
                                <strong style="color: #64748B;"><?php _e('Date & Time:', 'amelia-cpt-sync'); ?></strong>
                                <span><?php echo esc_html($active_booking->formatted_date . ' at ' . $active_booking->formatted_time); ?></span>
                                
                                <strong style="color: #64748B;"><?php _e('Provider:', 'amelia-cpt-sync'); ?></strong>
                                <span><?php echo esc_html($active_booking->provider_name); ?></span>
                                
                                <?php if (!empty($active_booking->resource_name)): ?>
                                <strong style="color: #64748B;"><?php _e('Resource:', 'amelia-cpt-sync'); ?></strong>
                                <span><?php echo esc_html($active_booking->resource_name); ?></span>
                                <?php endif; ?>
                                
                                <strong style="color: #64748B;"><?php _e('Location:', 'amelia-cpt-sync'); ?></strong>
                                <span><?php echo esc_html($active_booking->location_name); ?></span>
                            </div>
                        </div>
                        <?php endif; ?>
                        
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

                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; flex-wrap: wrap; gap: 12px;">
                                <h4 style="margin: 0; color: #2C3E50;">
                                    <?php _e('Select Date & Time', 'amelia-cpt-sync'); ?>
                                    <span id="slot-count-badge" class="badge-info" style="margin-left: 8px;"></span>
                                </h4>
                                <div id="booking-mode-indicator" style="padding: 6px 14px; border-radius: 20px; font-size: 13px; font-weight: 600; display: none;">
                                    <!-- Will be populated by JavaScript based on bookingViewState.mode -->
                                </div>
                            </div>
                            
                            <!-- Date & Time Picker Container -->
                            <div class="art-picker-container <?php echo $show_timeslots_grid ? 'with-timegrid' : 'no-timegrid'; ?>">
                                <!-- Column 1: Date & Time Combined -->
                                <div class="art-picker-datetime-combined">
                                    <!-- Time Entry Section (Top) -->
                                    <div class="art-picker-column-header">
                                        <?php _e('Select Date & Time', 'amelia-cpt-sync'); ?>
                                    </div>
                                    <div class="datetime-time-section">
                                        <label for="custom-time-input" style="font-size: 12px; color: #64748B; margin-bottom: 6px; display: block;"><?php _e('Time', 'amelia-cpt-sync'); ?></label>
                                        <input type="time" id="custom-time-input" class="form-input" disabled style="width: 100%; height: 40px;">
                                    </div>
                                    
                                    <!-- Dates List (Below Time) -->
                                    <div class="datetime-dates-section">
                                        <label style="font-size: 12px; color: #64748B; margin: 12px 0 6px 0; display: block;"><?php _e('Date', 'amelia-cpt-sync'); ?></label>
                                        <div class="art-picker-dates" id="picker-dates-list">
                                            <!-- Dates will be injected here -->
                                            <div style="padding: 20px; text-align: center; color: #94A3B8;">
                                                Loading dates...
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <?php if ($show_timeslots_grid): ?>
                                    <!-- Optional: Time Grid (can be hidden/shown) -->
                                    <div class="datetime-timegrid-section" style="display: none;">
                                        <div class="art-picker-times-header" id="picker-times-header">
                                            Select a date to see times
                                        </div>
                                        <div class="art-time-grid" id="picker-times-grid">
                                            <!-- Times will be injected here -->
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Column: Resource Availability (NEW - Phase 1) -->
                                <div class="art-picker-resources" id="resource-availability-column">
                                    <div class="art-picker-column-header">
                                        <span class="dashicons dashicons-admin-tools"></span>
                                        <?php _e('Resource Availability', 'amelia-cpt-sync'); ?>
                                    </div>
                                    <div class="art-resource-list" id="resource-status-list">
                                        <div class="resource-placeholder">
                                            <?php _e('Select date & time to check', 'amelia-cpt-sync'); ?>
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
                                            <?php _e('Select resource first', 'amelia-cpt-sync'); ?>
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
                            <input type="hidden" id="selected-resource-id" value="">
                            
                            <div id="slot-details" style="margin-top: 16px; padding: 12px; background: #E8F4F8; border-left: 4px solid #1A84EE; border-radius: 4px; display: none;">
                                <strong><?php _e('Booking Summary:', 'amelia-cpt-sync'); ?></strong>
                                <div id="slot-summary" style="margin-top: 8px; line-height: 1.6;"></div>
                            </div>
                            
                            <!-- Step 3: Create Booking Actions -->
                            <div class="booking-actions-grid" style="margin-top: 20px; display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
                                <button type="button" id="btn-tentative-booking" class="btn-warning" disabled>
                                    <span class="dashicons dashicons-clock"></span>
                                    <?php _e('Tentative Booking', 'amelia-cpt-sync'); ?>
                                </button>
                                <button type="button" id="btn-formal-booking" class="btn-success" disabled>
                                    <span class="dashicons dashicons-yes-alt"></span>
                                    <?php _e('Confirm Booking', 'amelia-cpt-sync'); ?>
                                </button>
                                <button type="button" id="btn-cancel-booking" class="btn-danger" style="display: none;">
                                    <span class="dashicons dashicons-no-alt"></span>
                                    <?php _e('Cancel Booking', 'amelia-cpt-sync'); ?>
                                </button>
                                <button type="button" id="btn-cancel-availability" class="btn-link">
                                    <?php _e('Clear Results', 'amelia-cpt-sync'); ?>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Booking Success Toast (temporary notification) -->
                        <div id="booking-success-toast" style="display: none; margin-top: 20px; padding: 12px 16px; border-radius: 6px;">
                            <span class="toast-icon"></span>
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
                                    <option value="reschedule"><?php _e('Update Time/Provider - Keep same booking, change details', 'amelia-cpt-sync'); ?></option>
                                    <option value="delete_and_create"><?php _e('Replace Booking - Delete old and create fresh', 'amelia-cpt-sync'); ?></option>
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
                        
                        <!-- Customer Match Results (Auto-loads) -->
                        <div class="customer-match-section">
                            <h4><?php _e('Customer Match', 'amelia-cpt-sync'); ?></h4>
                            <div id="customer-match-results">
                                <div class="customer-loading">
                                    <span class="dashicons dashicons-update spin"></span>
                                    <?php _e('Searching for existing customers...', 'amelia-cpt-sync'); ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Notes & Activity -->
                        <?php include AMELIA_CPT_SYNC_PLUGIN_DIR . 'templates/components/art-notes-card.php'; ?>
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

/* Active Booking Card - Confirmed State (Green) */
.art-booking-card.booking-confirmed {
    background: #fff;
    border-color: #28A745;
    border-width: 2px;
    margin-bottom: 20px;
}

.art-booking-card.booking-confirmed .booking-header {
    background: linear-gradient(135deg, #D4EDDA 0%, #C3E6CB 100%);
}

.art-booking-card.booking-confirmed .booking-header h3 {
    color: #155724;
}

/* Active Booking Card - Tentative State (Orange) */
.art-booking-card.booking-tentative {
    background: #fff;
    border-color: #F0AD4E;
    border-width: 2px;
    margin-bottom: 20px;
}

.art-booking-card.booking-tentative .booking-header {
    background: linear-gradient(135deg, #FFF3CD 0%, #FFECB5 100%);
}

.art-booking-card.booking-tentative .booking-header h3 {
    color: #856404;
}

.art-booking-card .booking-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.art-booking-card .booking-header h3 {
    display: flex;
    align-items: center;
    gap: 8px;
}

.art-booking-card .card-body {
    background: #fff;
}

.booking-status-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.booking-status-badge.badge-confirmed {
    background: #28A745;
    color: #fff;
}

.booking-status-badge.badge-tentative {
    background: #F0AD4E;
    color: #fff;
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
    color: #64748B;
    opacity: 0.8;
}

.booking-detail-item .detail-value {
    font-size: 14px;
    font-weight: 600;
    color: #1E293B;
}

.booking-detail-item.full-width {
    grid-column: 1 / -1;
}

/* Success button (green - for confirmed bookings) */
.btn-success {
    background: #28A745;
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

.btn-success:hover {
    background: #218838;
}

.btn-success:disabled {
    background: #E9ECEF;
    color: #6C757D;
    cursor: not-allowed;
}

/* Warning button (orange - for tentative bookings) */
.btn-warning {
    background: #F0AD4E;
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

.btn-warning:hover {
    background: #EC971F;
}

.btn-warning:disabled {
    background: #E9ECEF;
    color: #6C757D;
    cursor: not-allowed;
}

/* Danger button (red - for cancel) */
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

.original-request-time {
    font-size: 13px;
    font-weight: normal;
    color: #64748B;
    margin-left: 8px;
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

/* 3-column layout: Date&Time | Resources | Providers */
.art-picker-container.with-timegrid,
.art-picker-container.no-timegrid {
    grid-template-columns: 30% 35% 35%;
    gap: 0;
    /* Date&Time 30% | Resources 35% | Providers 35% = 100% exactly */
}

/* Combined Date & Time Column */
.art-picker-datetime-combined {
    background: #F8FAFC;
    border-right: 1px solid #E0E5F1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.datetime-time-section {
    padding: 12px 16px;
    border-bottom: 1px solid #E0E5F1;
    background: #fff;
}

.datetime-dates-section {
    flex: 1;
    overflow-y: auto;
    padding: 12px 16px;
}

.datetime-dates-section .art-picker-dates {
    background: transparent;
    border: none;
    max-height: none;
}

/* Mode-specific adjustments: Hide resource column for modes that don't use it */
.art-picker-container.mode-none .art-picker-resources,
.art-picker-container.mode-provider-bound .art-picker-resources {
    display: none;
}

.art-picker-container.mode-none.no-timegrid,
.art-picker-container.mode-none.with-timegrid {
    grid-template-columns: 35% 65%;
    /* Date&Time 35% | Providers 65% (no resources) */
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

/* NEW: Detailed conflict display (matches resource pattern) */
.provider-item .provider-conflicts-detail {
    margin-top: 4px;
}

.provider-item .provider-conflict-line {
    font-size: 10px;
    line-height: 1.4;
    margin-top: 2px;
    font-weight: 500;
}

/* Dark red for booking conflicts (tentative and confirmed) */
.provider-item .provider-conflict-line.tentative,
.provider-item .provider-conflict-line.confirmed {
    color: #991B1B;  /* Dark red */
}

/* Normal gray for non-booking conflicts (shifts, schedules) */
.provider-item .provider-conflict-line.other {
    color: #64748B;
}

.provider-item .provider-conflict-line a {
    color: inherit;
    text-decoration: none;
    border-bottom: 1px dotted currentColor;
}

.provider-item .provider-conflict-line a:hover {
    color: #7F1D1D;  /* Darker red on hover */
    border-bottom-style: solid;
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
    .art-picker-resources {
        border-left: none;
        border-bottom: 1px solid #E0E5F1;
        max-height: 400px;
    }
    .art-picker-providers {
        border-left: none;
        max-height: 300px;
    }
}

/* ========================================
   RESOURCE COLUMN STYLES (Match Provider Styling)
   ======================================== */

.art-picker-resources {
    background: #fff;
    border-right: 1px solid #E0E5F1;
    padding: 0;
    overflow-y: auto;
    max-height: 500px;
}

.art-picker-column-header {
    padding: 12px 16px;
    border-bottom: 1px solid #E0E5F1;
    font-size: 13px;
    font-weight: 600;
    color: #475569;
    background: #F8FAFC;
    display: flex;
    align-items: center;
    gap: 8px;
}

.art-resource-list {
    padding: 12px;
}

.resource-placeholder {
    text-align: center;
    padding: 40px 20px;
    color: #94A3B8;
    font-size: 13px;
    font-style: italic;
}

/* Resource Groups (match provider groups) */
.resource-group {
    margin-bottom: 12px;
}

.resource-group-label {
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

.resource-group-label.available {
    background: #DCFCE7;
    color: #166534;
}

.resource-group-label.warning {
    background: #FEF3C7;
    color: #92400E;
}

.resource-group-label.blocked {
    background: #FEE2E2;
    color: #991B1B;
}

/* Resource Items (unified with provider pattern - text + checkmark only) */
.resource-item {
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

.resource-item:hover {
    background: #F8FAFC;
    border-color: #E0E5F1;
}

.resource-item.selected {
    background: #EFF6FF;
    border-color: #1A84EE;
}

.resource-item .resource-info {
    flex: 1;
    min-width: 0;
}

.resource-item .resource-name {
    font-size: 13px;
    font-weight: 500;
    color: #1E293B;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.resource-item .resource-status {
    font-size: 11px;
    color: #64748B;
}

.resource-item .resource-conflicts {
    font-size: 10px;
    color: #94A3B8;
    margin-top: 2px;
    line-height: 1.3;
}

.resource-group.warning .resource-item .resource-conflicts {
    color: #B45309;
}

/* NEW: Detailed conflict display */
.resource-item .resource-conflicts-detail {
    margin-top: 4px;
}

.resource-item .resource-conflict-line {
    font-size: 10px;
    line-height: 1.4;
    margin-top: 2px;
    color: #991B1B;  /* Dark red for both tentative and confirmed */
    font-weight: 500;
}

.resource-item .resource-conflict-line a {
    color: inherit;
    text-decoration: none;
    border-bottom: 1px dotted currentColor;
}

.resource-item .resource-conflict-line a:hover {
    color: #7F1D1D;  /* Darker red on hover */
    border-bottom-style: solid;
}

/* Resource checkmark (matches provider pattern) */
.resource-item .resource-check {
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

.resource-item.selected .resource-check {
    background: #1A84EE;
    border-color: #1A84EE;
    color: #fff;
}

.resource-item.selected .resource-check .dashicons {
    font-size: 14px;
    width: 14px;
    height: 14px;
}

/* Resource Quantity Display */
.resource-quantity {
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px solid #E5E7EB;
}

.qty-bar {
    height: 8px;
    background: #E5E7EB;
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 4px;
}

.qty-bar-used {
    height: 100%;
    background: linear-gradient(90deg, #10B981 0%, #059669 100%);
    border-radius: 4px;
    transition: width 0.3s ease;
}

.resource-item.soft-block .qty-bar-used {
    background: linear-gradient(90deg, #F59E0B 0%, #D97706 100%);
}

.resource-item.hard-block .qty-bar-used,
.resource-item.unavailable .qty-bar-used {
    background: linear-gradient(90deg, #EF4444 0%, #DC2626 100%);
}

.qty-text {
    font-size: 12px;
    color: #6B7280;
}

/* Multi-resource header */
.multi-resource-header {
    background: #EFF6FF;
    color: #1E40AF;
    padding: 8px 12px;
    border-radius: 6px;
    margin-bottom: 12px;
    font-size: 13px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 6px;
}

.multi-resource-header .dashicons {
    font-size: 16px;
}

/* Old resource card styles (legacy, can be removed) */
.resource-card {
    display: none; /* Using resource-item instead */
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

.customer-match-section h4 {
    font-size: 14px;
    font-weight: 600;
    color: #1E293B;
    margin: 0 0 12px 0;
}

#customer-match-results {
    margin-top: 12px;
}

.customer-loading,
.customer-error {
    padding: 12px;
    border-radius: 6px;
    font-size: 13px;
    color: #64748B;
    text-align: center;
}

.customer-loading .dashicons {
    vertical-align: middle;
}

.customer-group {
    margin-bottom: 12px;
}

.customer-group-label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    padding: 6px 10px;
    border-radius: 4px;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.customer-group-label.exact {
    background: #D4EDDA;
    color: #155724;
    border-left: 3px solid #28A745;
}

.customer-group-label.high {
    background: #FFF3CD;
    color: #856404;
    border-left: 3px solid #F0AD4E;
}

.customer-group-label.possible {
    background: #E0E7FF;
    color: #4338CA;
    border-left: 3px solid #6366F1;
}

.customer-group-label.new {
    background: #F1F5F9;
    color: #64748B;
    border-left: 3px solid #94A3B8;
}

.customer-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: #fff;
    border: 2px solid #E0E5F1;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
    margin-bottom: 8px;
}

.customer-item:hover {
    border-color: #E0E5F1;
    background: #F8FAFC;
}

.customer-item.selected {
    border-color: #1A84EE;
    background: #EFF6FF;
}

.customer-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 14px;
    flex-shrink: 0;
}

.customer-info {
    flex: 1;
    min-width: 0;
}

.customer-name {
    font-weight: 600;
    color: #1E293B;
    font-size: 14px;
}

.customer-details {
    font-size: 12px;
    color: #64748B;
    margin-top: 2px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.customer-match-reasons {
    font-size: 11px;
    color: #16A34A;
    margin-top: 4px;
}

.customer-check {
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

.customer-item.selected .customer-check {
    background: #1A84EE;
    border-color: #1A84EE;
    color: #fff;
}

.customer-item.selected .customer-check .dashicons {
    font-size: 14px;
    width: 14px;
    height: 14px;
}

/* Legacy styles (kept for backwards compat) */
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

/* === AVAILABILITY DISABLED STATE === */
.availability-disabled {
    opacity: 0.5;
    pointer-events: none;
    position: relative;
}

.availability-disabled::before {
    content: "Complete required fields above to check availability";
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: rgba(255,255,255,0.95);
    padding: 12px 24px;
    border-radius: 6px;
    border: 2px dashed #ccc;
    font-weight: 500;
    color: #666;
    z-index: 10;
    text-align: center;
    white-space: nowrap;
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

/* ========================================================================
   NOTES SYSTEM STYLES
   ======================================================================== */

/* Notes Card */
.art-notes-card {
    margin-top: 16px;
}

.art-notes-list {
    max-height: 400px;
    overflow-y: auto;
    padding: 12px;
    background: #F8FAFC;
    border-radius: 6px;
    margin-bottom: 12px;
    scroll-behavior: smooth;
}

.art-note-item {
    display: flex;
    gap: 10px;
    margin-bottom: 12px;
    padding: 10px;
    background: #fff;
    border-radius: 6px;
    border-left: 3px solid transparent;
}

.art-note-item[data-note-type="manual"] {
    border-left-color: #667eea;
}

.art-note-item[data-note-type="system"] {
    border-left-color: #1A84EE;
}

.art-note-item[data-note-type="booking_event"] {
    border-left-color: #F59E0B;
}

.note-icon {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 12px;
    font-weight: 600;
}

.note-icon-manual {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
}

.note-icon-system {
    background: #EFF6FF;
    color: #1A84EE;
}

.note-icon-booking {
    background: #FEF3C7;
    color: #F59E0B;
}

.note-content-wrapper {
    flex: 1;
    min-width: 0;
}

.note-meta {
    display: flex;
    gap: 8px;
    align-items: center;
    margin-bottom: 4px;
    font-size: 12px;
}

.note-author {
    font-weight: 600;
    color: #1E293B;
}

.note-timestamp {
    color: #64748B;
}

.note-edited {
    color: #94A3B8;
    font-style: italic;
}

.note-content {
    font-size: 13px;
    color: #334155;
    line-height: 1.5;
    word-wrap: break-word;
    white-space: pre-wrap;
}

.note-content ul {
    margin: 8px 0;
    padding-left: 20px;
}

.note-content li {
    margin: 4px 0;
}

.note-actions-inline {
    display: flex;
    gap: 8px;
    margin-top: 6px;
}

.note-action-btn {
    background: none;
    border: none;
    color: #1A84EE;
    font-size: 11px;
    cursor: pointer;
    padding: 0;
}

.note-action-btn:hover {
    text-decoration: underline;
}

/* Note Composer (Input at Bottom) */
.art-note-composer {
    border-top: 1px solid #E0E5F1;
    padding-top: 12px;
}

.note-toolbar {
    display: flex;
    gap: 8px;
    align-items: center;
    margin-bottom: 8px;
}

.format-btn {
    background: #F1F5F9;
    border: 1px solid #E0E5F1;
    border-radius: 4px;
    padding: 4px 8px;
    font-size: 12px;
    cursor: pointer;
    transition: all 0.15s;
}

.format-btn:hover {
    background: #E0E7FF;
    border-color: #1A84EE;
}

.format-btn.active {
    background: #1A84EE;
    color: #fff;
    border-color: #1A84EE;
}

.char-counter {
    margin-left: auto;
    font-size: 11px;
    color: #64748B;
}

.char-counter.over-limit {
    color: #DC2626;
    font-weight: 600;
}

.note-input-wrapper {
    position: relative;
}

.note-input {
    min-height: 60px;
    max-height: 150px;
    overflow-y: auto;
    padding: 10px;
    border: 2px solid #E0E5F1;
    border-radius: 6px;
    background: #fff;
    font-size: 13px;
    line-height: 1.5;
    outline: none;
    transition: border-color 0.15s;
}

.note-input:focus {
    border-color: #1A84EE;
}

.note-input[data-placeholder]:empty:before {
    content: attr(data-placeholder);
    color: #94A3B8;
}

.note-actions {
    margin-top: 8px;
    display: flex;
    justify-content: flex-end;
}

#btn-add-note {
    background: #1A84EE;
    color: #fff;
    padding: 8px 16px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 500;
    border: none;
    cursor: pointer;
}

#btn-add-note:hover:not(:disabled) {
    background: #1570CD;
}

#btn-add-note:disabled {
    background: #E0E5F1;
    color: #94A3B8;
    cursor: not-allowed;
}

/* Edit Mode */
.note-edit-wrapper {
    margin-top: 8px;
}

.note-edit-wrapper .note-input {
    margin-bottom: 8px;
}

.note-edit-actions {
    display: flex;
    gap: 8px;
}

.note-save-btn,
.note-cancel-btn {
    padding: 4px 12px;
    border-radius: 4px;
    font-size: 12px;
    cursor: pointer;
    border: none;
}

.note-save-btn {
    background: #1A84EE;
    color: #fff;
}

.note-save-btn:hover {
    background: #1570CD;
}

.note-cancel-btn {
    background: #F1F5F9;
    color: #64748B;
}

.note-cancel-btn:hover {
    background: #E0E5F1;
}

/* Loading States */
.notes-loading-initial,
.notes-loading-more {
    text-align: center;
    padding: 20px;
    color: #64748B;
    font-size: 13px;
}

.notes-loading-initial .dashicons,
.notes-loading-more .dashicons {
    animation: spin 1s linear infinite;
}

.notes-end {
    text-align: center;
    padding: 10px;
    color: #94A3B8;
    font-size: 11px;
    font-style: italic;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

</style>

<!-- JavaScript for Interactivity -->
<script>
jQuery(document).ready(function($) {
    var artDetailData = {
        requestId: <?php echo $request_id; ?>,
        currentUserId: <?php echo get_current_user_id(); ?>,
        customerEmail: <?php echo wp_json_encode($request->customer_email); ?>,
        nonce: <?php echo wp_json_encode(wp_create_nonce('art_nonce')); ?>,
        currentCategory: <?php echo wp_json_encode($effective_category_id); ?>,
        currentService: <?php echo wp_json_encode($effective_service_id); ?>,
        currentLocation: <?php echo wp_json_encode($effective_location_id); ?>,
        currentDuration: <?php echo wp_json_encode($request->duration_seconds); ?>,
        currentPersons: <?php echo wp_json_encode($request->persons); ?>,
        providers: {}, // Will be populated by fetchServiceEmployees()
        showTimeslotsGrid: <?php echo $show_timeslots_grid ? 'true' : 'false'; ?>,
        hasActiveBooking: <?php echo (!empty($active_booking) && !empty($active_booking->amelia_appointment_id)) ? 'true' : 'false'; ?>,
        activeAppointmentId: <?php echo (!empty($active_booking) && !empty($active_booking->amelia_appointment_id)) ? intval($active_booking->amelia_appointment_id) : 'null'; ?>,
        activeBookingId: <?php echo (!empty($active_booking) && !empty($active_booking->amelia_booking_id)) ? intval($active_booking->amelia_booking_id) : 'null'; ?>,
        activeServiceId: <?php echo (!empty($active_booking->service_id)) ? intval($active_booking->service_id) : 'null'; ?>,
        bookingType: <?php echo (!empty($active_booking) && !empty($active_booking->booking_type)) ? wp_json_encode($active_booking->booking_type) : wp_json_encode('confirmed'); ?>,
        existingBookedDateTime: <?php echo !empty($active_booking->formatted_date_time) ? wp_json_encode($active_booking->formatted_date_time) : 'null'; ?>,
        existingBookedDate: <?php echo !empty($active_booking->date_only) ? wp_json_encode($active_booking->date_only) : 'null'; ?>,
        existingBookedTime: <?php echo !empty($active_booking->formatted_time) ? wp_json_encode($active_booking->formatted_time) : 'null'; ?>,
        existingBookedProviderId: <?php echo !empty($active_booking->provider_id) ? intval($active_booking->provider_id) : 'null'; ?>,
        existingBookedProviderName: <?php echo !empty($active_booking->provider_name) ? wp_json_encode($active_booking->provider_name) : 'null'; ?>,
        existingBookedLocationId: <?php echo !empty($active_booking->location_id) ? intval($active_booking->location_id) : 'null'; ?>,
        activeResource: <?php echo (!empty($active_booking->resource_id)) ? wp_json_encode(array('id' => intval($active_booking->resource_id), 'name' => $active_booking->resource_name)) : 'null'; ?>,
        activeResources: <?php echo (!empty($active_booking->all_resources)) ? wp_json_encode($active_booking->all_resources) : '[]'; ?>
    };
    
    // Version and debug logging
    console.log('ART Detail Page v<?php echo AMELIA_CPT_SYNC_VERSION; ?> loaded');
    console.log('ART artDetailData:', {
        hasActiveBooking: artDetailData.hasActiveBooking,
        activeAppointmentId: artDetailData.activeAppointmentId,
        activeResource: artDetailData.activeResource,
        existingBookedProviderId: artDetailData.existingBookedProviderId
    });
    
    // ========================================
    // AUTO-POPULATION GUARD
    // ========================================
    
    /**
     * Prevent auto-population from running more than once per page session
     * This prevents the auto-population from overwriting user actions when
     * they change services or trigger availability checks
     */
    var hasAutoPopulatedOnce = false;
    
    // ========================================
    // BOOKING VIEW STATE MANAGEMENT
    // ========================================
    
    /**
     * Track whether user is viewing current booking or exploring alternatives
     * - 'current': Shows "Currently Selected" labels, allows comparison
     * - 'exploring': Standard availability display (Available/Unavailable)
     */
    var bookingViewState = {
        mode: artDetailData.hasActiveBooking ? 'current' : 'exploring',
        originalDate: artDetailData.existingBookedDate,
        originalTime: artDetailData.existingBookedTime,
        originalServiceId: artDetailData.activeServiceId,
        isAutoPopulating: false  // Track if we're in auto-population to prevent mode switch
    };
    
    /**
     * Check if current values differ from original booking
     */
    function hasBookingChanges() {
        if (!artDetailData.hasActiveBooking) return false;
        
        // Get currently active date from the date picker
        var activeDateBtn = $('#picker-dates-list .art-picker-date-btn.active');
        var currentDate = activeDateBtn.length ? activeDateBtn.data('date') : null;
        var currentTime = $('#custom-time-input').val();
        var currentService = $('#pillar-service').val();
        
        return (currentDate !== bookingViewState.originalDate) ||
               (currentTime !== bookingViewState.originalTime) ||
               (currentService != bookingViewState.originalServiceId);
    }
    
    /**
     * Switch to exploring mode
     */
    /**
     * Update the mode indicator badge based on current mode
     */
    function updateModeIndicator() {
        var indicator = $('#booking-mode-indicator');
        
        if (!artDetailData.hasActiveBooking) {
            indicator.hide();
            return;
        }
        
        if (bookingViewState.mode === 'current') {
            indicator.html('<span class="dashicons dashicons-visibility" style="font-size: 14px; vertical-align: middle;"></span> <?php _e('Viewing Current Booking', 'amelia-cpt-sync'); ?>')
                     .css({
                         'background': '#E0E7FF',
                         'color': '#4338CA',
                         'border': '1px solid #4338CA'
                     })
                     .show();
        } else if (bookingViewState.mode === 'exploring') {
            indicator.html('<span class="dashicons dashicons-search" style="font-size: 14px; vertical-align: middle;"></span> <?php _e('Exploring Alternatives', 'amelia-cpt-sync'); ?>')
                     .css({
                         'background': '#FEF3C7',
                         'color': '#92400E',
                         'border': '1px solid #D97706'
                     })
                     .show();
        }
    }
    
    function enterExploringMode() {
        // Don't switch modes during auto-population
        if (bookingViewState.isAutoPopulating) {
            return;
        }
        
        // Don't switch if already in exploring mode
        if (bookingViewState.mode === 'exploring') return;
        
        bookingViewState.mode = 'exploring';
        console.log('ART: Switched to EXPLORING mode');
        updateModeIndicator();
        $('#btn-reset-to-current').fadeIn();
    }
    
    /**
     * Reset to current booking view
     */
    /**
     * CHANGE #3: Reset to Current Booking (Event-Driven)
     * Waits for slots to actually load before restoring date/time
     */
    function resetToCurrentBooking() {
        if (!artDetailData.hasActiveBooking) return;
        
        console.log('ART: Resetting to current booking');
        
        // Disable button to prevent double-clicks
        var resetBtn = $('#btn-reset-to-current');
        resetBtn.prop('disabled', true);
        
        bookingViewState.isAutoPopulating = true;
        
        // Restore service (triggers slot reload)
        if (bookingViewState.originalServiceId) {
            $('#pillar-service').val(bookingViewState.originalServiceId).trigger('change');
        }
        
        // Trigger fresh slot load
        $('#btn-check-availability').trigger('click');
        
        // Wait for slots to actually load (event-driven!)
        waitForEvent('art-slots-loaded', function(success) {
            if (!success) {
                console.error('ART: Timeout waiting for slots during reset');
                showNotice('Reset timed out. Please try again.', 'error');
                resetBtn.prop('disabled', false);
                bookingViewState.isAutoPopulating = false;
                return;
            }
            
            console.log('ART: Slots loaded after reset, restoring date/time');
            
            // Restore time
            var time24 = convertTo24Hour(bookingViewState.originalTime);
            if (time24) {
                $('#custom-time-input').val(time24);
                $('#pillar-time').val(time24);
            }
            
            // Restore date by clicking button
            if (bookingViewState.originalDate) {
                var targetDateBtn = $('.art-picker-date-btn[data-date="' + 
                    bookingViewState.originalDate + '"]');
                
                if (targetDateBtn.length) {
                    // Click triggers orchestrator
                    targetDateBtn.trigger('click');
                    $('#pillar-date').val(bookingViewState.originalDate);
                } else {
                    console.warn('ART: Original date not available:', 
                        bookingViewState.originalDate);
                    showNotice('Original date no longer available. Please select a new date.', 'warning');
                }
            }
            
            // Update summary
            updateDatetimeSummary();
            
            // Restore mode after orchestrator completes
            setTimeout(function() {
                bookingViewState.mode = 'current';
                bookingViewState.isAutoPopulating = false;
                updateModeIndicator();
                resetBtn.fadeOut().prop('disabled', false);
                
                // Re-render with "Currently Selected" labels
                if (lastOrchestratorResult) {
                    renderResourceColumn(lastOrchestratorResult);
                    renderAvailabilityEngineResults(
                        lastOrchestratorResult.providers || [],
                        lastOrchestratorResult.has_availability_error || false,
                        lastOrchestratorResult.availability_error_message || ''
                    );
                }
                
                // Apply saved booking state over orchestrator greedy picks
                applySavedBookingState();
                
                console.log('ART: Reset complete, mode set to current');
            }, 300);
        }, 10000);  // 10 second timeout
    }
    
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
    
    /**
     * CHANGE #5: Wait for event with timeout fallback
     * Prevents infinite waiting if event never fires due to network issues
     * 
     * @param {string} eventName - Event to wait for
     * @param {function} callback - Success callback (receives true/false)
     * @param {number} timeout - Max wait time in ms (default 10000)
     */
    function waitForEvent(eventName, callback, timeout) {
        timeout = timeout || 10000; // 10 second default
        var fired = false;
        
        var timeoutHandle = setTimeout(function() {
            if (!fired) {
                console.warn('ART: Timeout waiting for', eventName);
                fired = true;
                callback(false); // Call with failure flag
            }
        }, timeout);
        
        $(document).one(eventName, function() {
            if (!fired) {
                clearTimeout(timeoutHandle);
                fired = true;
                callback(true); // Call with success flag
            }
        });
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
        
        // Check if this change moves us away from current booking
        if (artDetailData.hasActiveBooking && hasBookingChanges()) {
            enterExploringMode();
        }
        
        // Phase 5: Fetch and display service duration
        fetchServiceDuration();
    });
    
    // Check if date/time pillar changes move us away from current booking
    $('#pillar-date, #pillar-time').on('change', function() {
        // Don't trigger during auto-population
        if (bookingViewState.isAutoPopulating) {
            return;
        }
        
        // Check if this change moves us away from current booking
        if (artDetailData.hasActiveBooking && hasBookingChanges()) {
            enterExploringMode();
        }
    });
    
    /**
     * Fetch and display service default duration and price from Amelia API (Phase 5)
     */
    var serviceData = {}; // Cache service data for price calculations
    
    function fetchServiceDuration() {
        var serviceId = $('#pillar-service').val();
        var durationDisplay = $('#service-duration-badge');
        
        if (!serviceId) {
            durationDisplay.hide();
            $('#price-suggestion').hide();
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
                
                // Cache service data for price calculations
                serviceData = {
                    duration_seconds: response.data.duration_seconds,
                    default_price: response.data.default_price
                };
                
                // Smart duration handling: Only auto-populate if duration is empty
                // This respects user's manual selections while providing helpful defaults
                var durationSeconds = response.data.duration_seconds;
                var currentDuration = $('#pillar-duration-seconds').val();
                
                // Only auto-populate if duration is currently empty or zero
                if (!currentDuration || currentDuration === '0' || currentDuration === '') {
                    console.log('ART: Auto-populating duration to service default (' + durationSeconds + 's)');
                    $('#pillar-duration-seconds').val(durationSeconds);
                    
                    // Try to select matching duration in dropdown
                    var exactMatch = $('#pillar-duration-selector option[value="' + durationSeconds + '"]');
                    if (exactMatch.length) {
                        $('#pillar-duration-selector').val(durationSeconds).trigger('change.select2');
                    } else {
                        $('#pillar-duration-selector').val('').trigger('change.select2');
                    }
                    
                    // Update the start→end summary display with new duration
                    updateDatetimeSummary();
                } else {
                    console.log('ART: Keeping user\'s manual duration (' + currentDuration + 's), service default is ' + durationSeconds + 's');
                }
                
                // Calculate suggested price
                calculateSuggestedPrice();
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
    
    /**
     * Calculate and display suggested price based on service default and custom duration
     */
    function calculateSuggestedPrice() {
        var priceInput = $('#pillar-price');
        var currentPrice = parseFloat(priceInput.val());
        
        // If price is already set, don't show suggestion
        if (currentPrice && currentPrice > 0) {
            $('#price-suggestion').hide();
            return;
        }
        
        // Need service data
        if (!serviceData.default_price || !serviceData.duration_seconds) {
            $('#price-suggestion').hide();
            return;
        }
        
        var customDuration = parseInt($('#pillar-duration-seconds').val()) || 0;
        var suggestedPrice;
        
        if (customDuration > 0 && customDuration !== serviceData.duration_seconds) {
            // Calculate proportional price based on custom duration
            var ratio = customDuration / serviceData.duration_seconds;
            suggestedPrice = serviceData.default_price * ratio;
            
            var customHours = Math.floor(customDuration / 3600);
            var customMins = Math.floor((customDuration % 3600) / 60);
            var customDisplay = customHours > 0 ? customHours + 'h ' + customMins + 'm' : customMins + 'm';
            
            $('#price-suggestion-text').html(
                'Suggested: <strong>$' + suggestedPrice.toFixed(2) + '</strong> (based on ' + customDisplay + ' custom duration)'
            );
        } else {
            // Use default service price
            suggestedPrice = serviceData.default_price;
            $('#price-suggestion-text').html(
                'Suggested: <strong>$' + suggestedPrice.toFixed(2) + '</strong> (service default)'
            );
        }
        
        // Store suggested price in data attribute
        $('#btn-apply-suggested-price').data('suggested-price', suggestedPrice.toFixed(2));
        $('#price-suggestion').slideDown();
    }
    
    // Apply suggested price button
    $(document).on('click', '#btn-apply-suggested-price', function() {
        var suggestedPrice = $(this).data('suggested-price');
        $('#pillar-price').val(suggestedPrice).trigger('change');
        $('#price-suggestion').slideUp();
    });
    
    // Recalculate price suggestion when duration changes
    $(document).on('change', '#pillar-duration-seconds, #pillar-duration-selector', function() {
        if (serviceData.default_price) {
            calculateSuggestedPrice();
        }
    });
    
    // Hide suggestion when user manually enters a price
    $(document).on('input change', '#pillar-price', function() {
        var val = parseFloat($(this).val());
        if (val && val > 0) {
            $('#price-suggestion').slideUp();
        }
    });
    
    // Refresh button for service duration
    $(document).on('click', '#service-duration-badge .refresh-icon', function(e) {
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
    
    /**
     * Update the calculated date/time summary (Start → End)
     * This replaces the old bidirectional start/end calculation with a simpler one-way flow:
     * Date + Time + Duration → Calculate End (display only)
     */
    function updateDatetimeSummary() {
        var date = $('#pillar-date').val();      // YYYY-MM-DD
        var time = $('#pillar-time').val();      // HH:mm
        
        // Always sync dropdown → hidden field first
        var dropdownDuration = $('#pillar-duration-selector').val();
        if (dropdownDuration) {
            $('#pillar-duration-seconds').val(dropdownDuration);
        }
        
        // Now get duration from hidden field (freshly updated)
        var durationSeconds = parseInt($('#pillar-duration-seconds').val()) || 0;
        
        var summaryDiv = $('#pillar-datetime-summary');
        
        if (!date || !time) {
            summaryDiv.hide();
            return;
        }
        
        if (!durationSeconds || durationSeconds <= 0) {
            summaryDiv.hide();
            return;
        }
        
        // Calculate start datetime
        var startDT = new Date(date + 'T' + time);
        var startDisplay = startDT.toLocaleDateString('en-US', {
            weekday: 'short',
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        }) + ' at ' + startDT.toLocaleTimeString('en-US', {
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        });
        
        // Calculate end datetime
        var endDT = new Date(startDT.getTime() + (durationSeconds * 1000));
        var endDisplay = endDT.toLocaleDateString('en-US', {
            weekday: 'short',
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        }) + ' at ' + endDT.toLocaleTimeString('en-US', {
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        });
        
        // Update displays
        $('#pillar-start-display').text(startDisplay);
        $('#pillar-end-display').text(endDisplay);
        summaryDiv.show();
    }
    
    // Event: Date, Time, or Duration changes → Update summary
    $('#pillar-date, #pillar-time, #pillar-duration-selector').on('change', updateDatetimeSummary);
    
    // Trigger on page load if values exist
    if ($('#pillar-date').val() && $('#pillar-time').val()) {
        updateDatetimeSummary();
    }
    
    /**
     * Sync pillars → availability engine when user changes date/time in pillars
     */
    function syncPillarsToAvailabilityEngine() {
        var pillarDate = $('#pillar-date').val();
        var pillarTime = $('#pillar-time').val();
        
        if (pillarDate) {
            // Find and click matching date button in availability engine
            var matchingBtn = $('.art-picker-date-btn[data-date="' + pillarDate + '"]');
            if (matchingBtn.length && !matchingBtn.hasClass('active')) {
                $('.art-picker-date-btn').removeClass('active');
                matchingBtn.addClass('active');
            }
        }
        
        if (pillarTime) {
            // Sync to availability engine time input
            $('#custom-time-input').val(pillarTime);
        }
        
        // Trigger provider list update
        updateProviderList();
    }
    
    /**
     * Sync availability engine → pillars when user selects date/time in availability engine
     */
    function syncAvailabilityEngineToPillars() {
        var activeDateBtn = $('#picker-dates-list .art-picker-date-btn.active');
        var dateStr = activeDateBtn.length ? activeDateBtn.data('date') : null;
        var timeStr = $('#custom-time-input').val();
        
        if (dateStr && $('#pillar-date').val() !== dateStr) {
            $('#pillar-date').val(dateStr).trigger('change');
        }
        
        if (timeStr && $('#pillar-time').val() !== timeStr) {
            $('#pillar-time').val(timeStr).trigger('change');
        }
    }
    
    // Auto-sync when pillars change (with debounce to prevent infinite loop)
    var pillarSyncTimeout;
    $('#pillar-date, #pillar-time').on('change', function() {
        clearTimeout(pillarSyncTimeout);
        pillarSyncTimeout = setTimeout(syncPillarsToAvailabilityEngine, 100);
    });
    
    /**
     * Clear stale availability results when pillars change
     * This prompts user to re-run availability check with new values
     */
    $('#pillar-service, #pillar-date, #pillar-time, #pillar-duration-selector').on('change', function() {
        // Only clear if results are visible (don't clear during auto-population)
        if (!bookingViewState.isAutoPopulating && $('#resource-status-list').is(':visible')) {
            $('#resource-status-list').html(
                '<div class="resource-placeholder" style="padding: 20px; text-align: center; color: #64748B;">' +
                    '<span class="dashicons dashicons-info"></span> ' +
                    '<?php _e('Click "Check Availability" to update results', 'amelia-cpt-sync'); ?>' +
                '</div>'
            );
            
            // Also clear provider list
            var providerList = $('#provider-list');
            if (providerList.is(':visible') && !providerList.find('.provider-placeholder').length) {
                providerList.html(
                    '<div class="provider-placeholder"><?php _e('Click "Check Availability" to update', 'amelia-cpt-sync'); ?></div>'
                );
            }
        }
    });
    
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
    // === CUSTOMER FUZZY MATCHING ===
    var selectedCustomerId = null; // 0 = create new, >0 = use existing
    
    /**
     * Auto-search for customer matches on page load
     */
    function searchCustomerMatches() {
        $.post(ajaxurl, {
            action: 'art_find_customer_matches',
            nonce: artDetailData.nonce,
            email: artDetailData.customerEmail,
            phone: '<?php echo esc_js($request->customer_phone ?? ''); ?>',
            first_name: '<?php echo esc_js($request->customer_first_name); ?>',
            last_name: '<?php echo esc_js($request->customer_last_name); ?>'
        }, function(response) {
            if (response.success) {
                renderCustomerMatches(response.data);
            } else {
                $('#customer-match-results').html('<div class="customer-error">Error searching customers</div>');
            }
        }).fail(function() {
            $('#customer-match-results').html('<div class="customer-error">Network error</div>');
        });
    }
    
    /**
     * Render customer match results
     */
    function renderCustomerMatches(matches) {
        var html = '';
        
        // Exact match (auto-select)
        if (matches.exact) {
            selectedCustomerId = matches.exact.customer.id;
            html += '<div class="customer-group">';
            html += '<div class="customer-group-label exact"><span class="dashicons dashicons-yes-alt"></span> Exact Match (Auto-Selected)</div>';
            html += buildCustomerItem(matches.exact.customer, matches.exact.reasons, matches.exact.score, true);
            html += '</div>';
        }
        
        // High confidence matches
        if (matches.high && matches.high.length > 0) {
            html += '<div class="customer-group">';
            html += '<div class="customer-group-label high"><span class="dashicons dashicons-info"></span> Possible Matches</div>';
            $.each(matches.high, function(i, match) {
                html += buildCustomerItem(match.customer, match.reasons, match.score, false);
            });
            html += '</div>';
        }
        
        // Possible matches
        if (matches.possible && matches.possible.length > 0) {
            html += '<div class="customer-group">';
            html += '<div class="customer-group-label possible"><span class="dashicons dashicons-search"></span> Low Confidence</div>';
            $.each(matches.possible, function(i, match) {
                html += buildCustomerItem(match.customer, match.reasons, match.score, false);
            });
            html += '</div>';
        }
        
        // Only show "Create New" option if no exact match
        if (!matches.exact) {
            html += '<div class="customer-group">';
            html += '<div class="customer-group-label new"><span class="dashicons dashicons-plus-alt"></span> Create New Customer</div>';
            html += '<div class="customer-item create-new' + (!selectedCustomerId ? ' selected' : '') + '" data-customer-id="0">';
            html += '<div class="customer-avatar">NC</div>';
            html += '<div class="customer-info">';
            html += '<div class="customer-name">New Customer</div>';
            html += '<div class="customer-details">No match found - will create new when booking</div>';
            html += '</div>';
            html += '<div class="customer-check"><span class="dashicons dashicons-yes"></span></div>';
            html += '</div>';
            html += '</div>';
            
            // If no fuzzy matches either, auto-select "Create New"
            if ((!matches.high || matches.high.length === 0) && (!matches.possible || matches.possible.length === 0)) {
                selectedCustomerId = 0;
            }
        }
        
        $('#customer-match-results').html(html);
    }
    
    /**
     * Build customer item HTML
     */
    function buildCustomerItem(customer, reasons, score, selected) {
        var initials = (customer.firstName.charAt(0) + customer.lastName.charAt(0)).toUpperCase();
        var selectedClass = selected ? 'selected' : '';
        
        var html = '<div class="customer-item ' + selectedClass + '" data-customer-id="' + customer.id + '">';
        html += '<div class="customer-avatar">' + initials + '</div>';
        html += '<div class="customer-info">';
        html += '<div class="customer-name">' + customer.firstName + ' ' + customer.lastName + '</div>';
        html += '<div class="customer-details">';
        html += customer.email;
        if (customer.phone) {
            html += ' • ' + customer.phone;
        }
        html += '</div>';
        if (reasons && reasons.length > 0) {
            html += '<div class="customer-match-reasons">' + reasons.join(', ') + '</div>';
        }
        html += '</div>';
        html += '<div class="customer-check"><span class="dashicons dashicons-yes"></span></div>';
        html += '</div>';
        
        return html;
    }
    
    /**
     * Handle customer selection
     */
    $(document).on('click', '.customer-item', function() {
        var customerId = $(this).data('customer-id');
        
        $('.customer-item').removeClass('selected');
        $(this).addClass('selected');
        selectedCustomerId = customerId;
        
        console.log('ART: Selected customer ID:', selectedCustomerId === 0 ? 'Create New' : selectedCustomerId);
    });
    
    // Run customer matching on page load
    searchCustomerMatches();
    
    // === AUTO-SAVE PILLARS ===
    var autoSaveTimer = null;
    var isSaving = false;
    
    // Reusable save function for auto-save
    function savePillarsAuto(showSuccessNotice) {
        if (isSaving) return; // Prevent concurrent saves
        
        // Calculate start_datetime from date + time inputs
        var date = $('#pillar-date').val();
        var time = $('#pillar-time').val();
        var startDatetime = (date && time) ? date + ' ' + time + ':00' : null;
        
        // Note: end_datetime will be calculated server-side from start + duration
        // We don't send it from frontend anymore
        
        var formData = {
            action: 'art_save_pillars',
            nonce: artDetailData.nonce,
            request_id: artDetailData.requestId,
            category_id: $('#pillar-category').val(),
            service_id: $('#pillar-service').val(),
            location_id: $('#pillar-location').length ? $('#pillar-location').val() : null,
            persons: $('#pillar-persons').length ? $('#pillar-persons').val() : 1,
            start_datetime: startDatetime,
            duration_seconds: $('#pillar-duration-seconds').val(),
            final_price: $('#pillar-price').val()
            // end_datetime removed - calculated server-side
        };
        
        isSaving = true;
        
        // Show saving indicator
        var indicator = $('.save-indicator');
        indicator.find('.dashicons').show();
        indicator.find('.status-text').text('Saving...');
        indicator.show();
        
        $.post(ajaxurl, formData, function(response) {
            isSaving = false;
            indicator.find('.dashicons').hide();
            
            if (response.success) {
                indicator.find('.status-text').text('✓ Saved');
                indicator.css('color', '#16A34A');
                
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
                
                // Check if availability section should be enabled
                checkAvailabilityReady();
                
                // Fade out after 2 seconds
                setTimeout(function() {
                    indicator.fadeOut();
                }, 2000);
                
                if (showSuccessNotice) {
                    showNotice('Booking details saved successfully', 'success');
                }
            } else {
                indicator.find('.status-text').text('✗ Save failed');
                indicator.css('color', '#DC2626');
                showNotice('Error: ' + (response.data.message || 'Unknown error'), 'error');
                
                setTimeout(function() {
                    indicator.fadeOut();
                }, 3000);
            }
        }).fail(function() {
            isSaving = false;
            indicator.find('.dashicons').hide();
            indicator.find('.status-text').text('✗ Connection error');
            indicator.css('color', '#DC2626');
            
            setTimeout(function() {
                indicator.fadeOut();
            }, 3000);
        });
    }
    
    // Debounced auto-save on field changes
    function triggerAutoSave() {
        clearTimeout(autoSaveTimer);
        autoSaveTimer = setTimeout(function() {
            savePillarsAuto(false); // Don't show big success notice for auto-save
        }, 500); // 500ms debounce
    }
    
    // Attach auto-save to all pillar fields
    $('#pillar-service, #pillar-category, #pillar-location, #pillar-persons').on('change', triggerAutoSave);
    $('#pillar-duration-selector').on('change', triggerAutoSave);
    $('#pillar-date, #pillar-time, #pillar-price').on('blur change', triggerAutoSave);
    
    // Keep form submit handler as manual trigger (if user presses Enter)
    $('#booking-pillars-form').on('submit', function(e) {
        e.preventDefault();
        clearTimeout(autoSaveTimer);
        savePillarsAuto(true); // Show success notice for manual save
    });
    
    // === AVAILABILITY SECTION VALIDATION ===
    var locationRequiredForAvailability = <?php echo $location_required_for_availability ? 'true' : 'false'; ?>;
    
    function checkAvailabilityReady() {
        var service = $('#pillar-service').val();
        var category = $('#pillar-category').val();
        var duration = $('#pillar-duration-seconds').val();
        var pillarDate = $('#pillar-date').val();
        var pillarTime = $('#pillar-time').val();
        
        // Check location only if availability engine requires it
        var location = $('#pillar-location').val();
        
        var isReady = service && category && duration && pillarDate && pillarTime;
        if (locationRequiredForAvailability) {
            isReady = isReady && location;
        }
        
        var availSection = $('#availability-section');
        
        if (isReady) {
            availSection.removeClass('availability-disabled');
        } else {
            availSection.addClass('availability-disabled');
        }
    }
    
    // Check on page load
    checkAvailabilityReady();
    
    // Check after every relevant field change
    $('#pillar-service, #pillar-category, #pillar-location, #pillar-duration-selector, #pillar-date, #pillar-time').on('change', checkAvailabilityReady);
    
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
                
                // Auto-select date/time if available (from existing booking OR from pillar inputs)
                // GUARD: Only run this once per page session to prevent overwriting user actions
                var hasExistingBookingData = artDetailData.hasActiveBooking && 
                                              artDetailData.existingBookedDate && 
                                              artDetailData.existingBookedTime;
                var hasPillarData = $('#pillar-date').val() && $('#pillar-time').val();
                
                if ((hasExistingBookingData || hasPillarData) && !hasAutoPopulatedOnce) {
                    // Determine data source
                    var targetDate = hasExistingBookingData ? 
                        artDetailData.existingBookedDate : 
                        $('#pillar-date').val();
                    var targetTime = hasExistingBookingData ? 
                        artDetailData.existingBookedTime : 
                        $('#pillar-time').val();
                    
                    console.log('ART DEBUG: Pre-filling date/time inputs from ' + 
                        (hasExistingBookingData ? 'booking' : 'pillars'), {
                        date: targetDate,
                        time: targetTime
                    });
                    
                    hasAutoPopulatedOnce = true; // Mark as done
                    bookingViewState.isAutoPopulating = true;
                    
                    setTimeout(function() {
                        // Find and click matching date button
                        var matchingDateBtn = $('.art-picker-date-btn[data-date="' + targetDate + '"]');
                        
                        if (matchingDateBtn.length) {
                            matchingDateBtn.trigger('click');
                            
                            // Convert time format if needed (12hr to 24hr)
                            var time24 = targetTime;
                            if (targetTime && (targetTime.includes('AM') || targetTime.includes('PM'))) {
                                time24 = convertTo24Hour(targetTime);
                            }
                            
                            if (time24) {
                                $('#custom-time-input').val(time24).trigger('change');
                                
                                // Sync to pillar inputs if not already there
                                if ($('#pillar-date').val() !== targetDate) {
                                    $('#pillar-date').val(targetDate);
                                }
                                if ($('#pillar-time').val() !== time24) {
                                    $('#pillar-time').val(time24);
                                }
                                updateDatetimeSummary();
                            }
                            
                            console.log('ART: Date/time auto-selected successfully');
                        } else {
                            console.warn('ART: Date button not found for', targetDate);
                        }
                        
                        // Reset auto-populating flag
                        bookingViewState.isAutoPopulating = false;
                    }, 300); // Delay to ensure date buttons are rendered
                }
                
                // Filter Change Event
                $('#filter-provider').off('change').on('change', function() {
                    var selected = $(this).val();
                    renderFilteredDates(selected);
                    
                    // Reset selection details
                    $('#slot-details').hide();
                    $('#btn-tentative-booking, #btn-formal-booking').prop('disabled', true);
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
            
            // CHANGE #1: Fire event to signal slots are loaded and rendered
            // This allows event-driven async handling for auto-population and reset
            $(document).trigger('art-slots-loaded');
            console.log('ART: Slots loaded and rendered, event fired');
            
        }).fail(function(xhr, status, error) {
            btn.prop('disabled', false);
            icon.removeClass('spin');
            
            console.error('ART: Slot loading failed', {
                status: status,
                error: error
            });
            
            $('#availability-status').html(
                '<p style="color: #DC3545;">Network error. Please try again.</p>'
            );
            
            // CHANGE #4: Fire event even on error to prevent infinite waiting
            // Listeners need to know the operation completed (even if it failed)
            $(document).trigger('art-slots-loaded');
            console.log('ART: Slots loading failed, but event fired to prevent hangs');
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
        
        // Check if this is different from existing booking
        var hasChanges = false;
        if (artDetailData.hasActiveBooking) {
            hasChanges = (slot.datetime !== artDetailData.existingBookedDateTime) ||
                        (slot.provider_id != artDetailData.existingBookedProviderId);
        }
        
        // Get service name
        var serviceName = $('#pillar-service option:selected').text() || 'Not set';
        
        // Build summary
        var summary = '<div style="color: #2C3E50;">';
        
        if (hasChanges && artDetailData.hasActiveBooking) {
            // Show change comparison
            summary += '<strong style="font-size:14px; color: #F0AD4E;">Change Booking Details:</strong><br>';
            summary += '<div style="margin: 8px 0; padding: 8px; background: #FFF3CD; border-radius: 4px;">';
            summary += '<strong>From:</strong><br>';
            summary += 'Service: ' + serviceName + '<br>';
            summary += 'Date: ' + artDetailData.existingBookedDate + ' at ' + artDetailData.existingBookedTime + '<br>';
            summary += 'Provider: ' + (artDetailData.existingBookedProviderName || 'Unknown');
            summary += '</div>';
            summary += '<div style="margin: 8px 0; padding: 8px; background: #D4EDDA; border-radius: 4px;">';
            summary += '<strong>To:</strong><br>';
            summary += 'Service: ' + serviceName + '<br>';
            summary += 'Date: ' + slot.date + ' at ' + timeDisplay + '<br>';
            summary += 'Provider: ' + providerName + '<br>';
            summary += 'Location: ' + locationName;
            summary += '</div>';
        } else {
            // Regular summary
            summary += '<strong style="font-size:14px;">Booking Summary:</strong><br>';
            summary += '<strong>Service:</strong> ' + serviceName + '<br>';
            
            // Add resource info if exists
            var resourceInfo = getResourceSummary();
            if (resourceInfo) {
                summary += '<strong>Resource:</strong> ' + resourceInfo + '<br>';
            }
            
            summary += '<strong>Date & Time:</strong> ' + slot.date + ' at ' + timeDisplay + '<br>';
            summary += '<strong>Provider:</strong> ' + providerName + '<br>';
            summary += '<strong>Location:</strong> ' + locationName;
        }
        
        summary += '</div>';
        
        $('#slot-summary').html(summary);
        $('#slot-details').slideDown();
        
        // Enable booking buttons
        $('#btn-tentative-booking, #btn-formal-booking').prop('disabled', false);
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
        
        // Resources (if any)
        if (data.resources && data.resources.length > 0) {
            html += '<div class="booking-detail-item full-width">';
            html += '<span class="detail-label"><?php _e('Resources', 'amelia-cpt-sync'); ?></span>';
            html += '<span class="detail-value">' + data.resources.join(', ') + '</span>';
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
        
        // Update card styling based on booking type
        var card = $('#active-booking-card');
        var badge = card.find('.booking-status-badge');
        var bookingType = data.booking_type || 'confirmed';
        
        card.removeClass('booking-tentative booking-confirmed');
        badge.removeClass('badge-tentative badge-confirmed');
        
        if (bookingType === 'tentative') {
            card.addClass('booking-tentative');
            badge.addClass('badge-tentative').text('<?php _e('Tentative', 'amelia-cpt-sync'); ?>');
        } else {
            card.addClass('booking-confirmed');
            badge.addClass('badge-confirmed').text('<?php _e('Confirmed', 'amelia-cpt-sync'); ?>');
        }
        
        card.attr('data-booking-type', bookingType);
        card.slideDown();
        
        // Mark that we now have an active booking
        artDetailData.hasActiveBooking = true;
        artDetailData.activeAppointmentId = data.appointment_id;
        artDetailData.activeBookingId = data.booking_id;
        artDetailData.bookingType = bookingType;
        
        // Update booking buttons
        updateBookingButtons();
    }
    
    /**
     * Show booking toast notification with appropriate styling
     */
    function showBookingToast(message, type) {
        var toast = $('#booking-success-toast');
        var icon = toast.find('.toast-icon');
        
        // Set styling based on type
        if (type === 'warning') {
            toast.css({
                'background': '#FFF3CD',
                'border': '1px solid #FFECB5',
                'color': '#856404'
            });
            icon.attr('class', 'toast-icon dashicons dashicons-clock').css('color', '#F0AD4E');
        } else if (type === 'success') {
            toast.css({
                'background': '#D4EDDA',
                'border': '1px solid #C3E6CB',
                'color': '#155724'
            });
            icon.attr('class', 'toast-icon dashicons dashicons-yes').css('color', '#28A745');
        } else {
            toast.css({
                'background': '#F8D7DA',
                'border': '1px solid #F5C6CB',
                'color': '#721C24'
            });
            icon.attr('class', 'toast-icon dashicons dashicons-no').css('color', '#DC3545');
        }
        
        $('#booking-toast-message').text(message);
        toast.slideDown().delay(4000).slideUp();
    }
    
    /**
     * Update booking buttons based on current state
     */
    function updateBookingButtons() {
        var hasBooking = hasExistingBooking();
        
        // Show/hide cancel button based on booking existence
        if (hasBooking) {
            $('#btn-cancel-booking').show();
        } else {
            $('#btn-cancel-booking').hide();
        }
    }
    
    // Initialize button state on page load
    updateBookingButtons();
    
    /**
     * Helper: Convert 12-hour time to 24-hour format for time input
     * e.g., "10:30 AM" -> "10:30", "02:00 PM" -> "14:00"
     */
    function convertTo24Hour(time12h) {
        if (!time12h) return '';
        
        var parts = time12h.match(/(\d+):(\d+)\s*(AM|PM)/i);
        if (!parts) return '';
        
        var hours = parseInt(parts[1]);
        var minutes = parts[2];
        var meridiem = parts[3].toUpperCase();
        
        if (meridiem === 'PM' && hours !== 12) {
            hours += 12;
        } else if (meridiem === 'AM' && hours === 12) {
            hours = 0;
        }
        
        return String(hours).padStart(2, '0') + ':' + minutes;
    }
    
    /**
     * CHANGE #2: Auto-populate availability engine with existing booking details on page load
     * EVENT-DRIVEN: Waits for slots to actually load before auto-selecting date/time
     * Only runs once per page session to prevent overwriting user actions
     */
    if (artDetailData.hasActiveBooking && artDetailData.existingBookedDateTime && !hasAutoPopulatedOnce) {
        hasAutoPopulatedOnce = true;
        bookingViewState.isAutoPopulating = true;
        
        console.log('ART: Starting auto-population for existing booking', {
            date: artDetailData.existingBookedDate,
            time: artDetailData.existingBookedTime,
            providerId: artDetailData.existingBookedProviderId
        });
        
        // Add provider to map for UI display
        artDetailData.providers[artDetailData.existingBookedProviderId] = 
            artDetailData.existingBookedProviderName;
        
        setTimeout(function() {
            // STEP 1: Trigger slot loading by clicking the "Check Availability" button
            console.log('ART: Triggering availability check to load slots');
            $('#btn-check-availability').trigger('click');
            
            // STEP 2: Wait for slots to actually load (event-driven!)
            waitForEvent('art-slots-loaded', function(success) {
                if (!success) {
                    console.error('ART: Timeout waiting for slots to load');
                    showNotice('Auto-population timed out. Please click "Check Availability" manually.', 'error');
                    bookingViewState.isAutoPopulating = false;
                    return;
                }
                
                console.log('ART: Slots loaded successfully, auto-selecting date/time');
                
                // STEP 3: Find and click the date button
                var targetDateBtn = $('.art-picker-date-btn[data-date="' + 
                    artDetailData.existingBookedDate + '"]');
                
                if (targetDateBtn.length) {
                    // Click triggers updateProviderList() → orchestrator
                    targetDateBtn.trigger('click');
                    
                    // Set time input
                    var time24 = convertTo24Hour(artDetailData.existingBookedTime);
                    if (time24) {
                        $('#custom-time-input').val(time24).trigger('change');
                    }
                    
                    console.log('ART: Date/time auto-selected successfully');
                } else {
                    console.warn('ART: Date button not found for', 
                        artDetailData.existingBookedDate,
                        '- Original booking date may not be available in current slots');
                    showNotice('Original booking date not currently available. Showing current availability.', 'warning');
                }
                
                // STEP 4: Set mode to 'current' after orchestrator runs
                setTimeout(function() {
                    bookingViewState.mode = 'current';
                    bookingViewState.isAutoPopulating = false;
                    updateModeIndicator();
                    
                    // Re-render to show "Currently Selected" labels
                    if (lastOrchestratorResult) {
                        renderResourceColumn(lastOrchestratorResult);
                        renderAvailabilityEngineResults(
                            lastOrchestratorResult.providers || [],
                            lastOrchestratorResult.has_availability_error || false,
                            lastOrchestratorResult.availability_error_message || ''
                        );
                    }
                    
                    // FINAL PASS: Apply saved booking state over orchestrator greedy picks
                    // This runs LAST, after all renders complete, and overrides auto-selections
                    // with the actual saved resource assignments from the database
                    applySavedBookingState();
                    
                    console.log('ART: Auto-population complete, mode set to current');
                }, 500);  // Wait for orchestrator to complete
            }, 10000);  // 10 second timeout
        }, 1000);  // Delay to ensure all functions are defined
        
        // Pre-select slot for visual consistency
        selectSlot({
            datetime: artDetailData.existingBookedDateTime,
            provider_id: artDetailData.existingBookedProviderId,
            location_id: artDetailData.existingBookedLocationId || 0,
            date: artDetailData.existingBookedDate
        }, artDetailData.existingBookedTime);
    }
    
    /**
     * Unified booking management function (Quick Win)
     * Backend handles all decision logic
     */
    function manageBooking(desiredStatus, btn) {
        var slotDatetime = $('#selected-slot-datetime').val();
        var providerId = $('#selected-provider-id').val();
        
        if (!slotDatetime || !providerId) {
            showNotice('<?php _e('Please select a time slot first', 'amelia-cpt-sync'); ?>', 'error');
            return;
        }
        
        btn.prop('disabled', true);
        var originalText = btn.html();
        var loadingText = desiredStatus === 'tentative' 
            ? '<?php _e('Processing tentative...', 'amelia-cpt-sync'); ?>'
            : '<?php _e('Processing confirmation...', 'amelia-cpt-sync'); ?>';
        btn.html('<span class="dashicons dashicons-update spin"></span> ' + loadingText);
        
        $.post(ajaxurl, {
            action: 'art_manage_booking',
            nonce: artDetailData.nonce,
            request_id: artDetailData.requestId,
            service_id: $('#pillar-service').val(),
            duration: $('#pillar-duration-seconds').val(),
            slot_datetime: slotDatetime,
            provider_id: providerId,
            desired_status: desiredStatus,
            location_id: $('#pillar-location').val() || null,
            persons: $('#pillar-persons').val() || 1,
            selected_customer_id: selectedCustomerId, // Include fuzzy match selection
            selected_resources: JSON.stringify(getSelectedResources()) // JSON-encode for composite {resource_id, quantity} objects
        }, function(response) {
            btn.prop('disabled', false);
            btn.html(originalText);
            
            if (response.success) {
                var data = response.data;
                
                // Handle different action results
                if (data.action_taken === 'no_change') {
                    showNotice(data.message, 'info');
                    return;
                }
                
                // Update UI with result
                var isTentative = (data.booking_type === 'tentative');
                
                // Update active booking card
                updateActiveBookingCard({
                    booking_id: data.booking_id,
                    appointment_id: data.appointment_id,
                    booking_type: data.booking_type,
                    service_name: $('#pillar-service option:selected').text(),
                    category_name: $('#pillar-category option:selected').text(),
                    formatted_date: $('#picker-dates-list .art-picker-date-btn.active').text(),
                    formatted_time: $('#custom-time-input').val(),
                    provider_name: artDetailData.providers[providerId] || 'Provider #' + providerId,
                    location_name: $('#pillar-location option:selected').text() || ''
                });
                
                // Update artDetailData
                artDetailData.hasActiveBooking = true;
                artDetailData.activeBookingId = data.booking_id;
                artDetailData.activeAppointmentId = data.appointment_id;
                artDetailData.bookingType = data.booking_type;
                artDetailData.existingBookedDateTime = slotDatetime;
                artDetailData.existingBookedProviderId = providerId;
                
                // Update provider name in cache
                var selectedProviderName = $('.provider-item[data-provider-id="' + providerId + '"]').find('.provider-name').text();
                if (selectedProviderName) {
                    artDetailData.providers[providerId] = selectedProviderName;
                    artDetailData.existingBookedProviderName = selectedProviderName;
                }
                
                // Update resource info in cache (for "Currently Selected Resource" display)
                var selectedResourceIds = getSelectedResources();
                if (selectedResourceIds.length > 0) {
                    // Update single activeResource (backward compat)
                    var selectedResourceId = selectedResourceIds[0];
                    var selectedResourceName = $('.resource-item[data-resource-id="' + selectedResourceId + '"]').find('.resource-name').text();
                    
                    if (selectedResourceName) {
                        artDetailData.activeResource = {
                            id: selectedResourceId,
                            name: selectedResourceName
                        };
                    }
                    
                    // Update plural activeResources (for composite mode — includes quantity)
                    artDetailData.activeResources = [];
                    selectedResourceIds.forEach(function(sel) {
                        var resId = (typeof sel === 'object') ? sel.resource_id : sel;
                        var resQty = (typeof sel === 'object') ? sel.quantity : 1;
                        var resName = $('.resource-item[data-resource-id="' + resId + '"]').find('.resource-name').text();
                        artDetailData.activeResources.push({ 
                            id: parseInt(resId), 
                            name: resName || 'Resource #' + resId,
                            quantity_used: resQty
                        });
                    });
                    
                    console.log('ART DEBUG: Updated activeResources:', artDetailData.activeResources);
                }
                
                // Set mode back to 'current' after successful save
                // This prevents the post-save orchestrator call from using greedy auto-selection
                bookingViewState.mode = 'current';
                bookingViewState.isAutoPopulating = false;
                updateModeIndicator();
                $('#btn-reset-to-current').fadeOut();
                
                // Update bookingViewState originals so hasBookingChanges() returns false
                bookingViewState.originalDate = artDetailData.existingBookedDate;
                bookingViewState.originalTime = artDetailData.existingBookedTime;
                bookingViewState.originalServiceId = artDetailData.activeServiceId;
                
                // Refresh provider list to show new "Currently Selected Provider"
                if (typeof updateProviderList === 'function') {
                    hasAutoSelectedProvider = false; // Reset flag so it can re-auto-select
                    updateProviderList();
                }
                
                // After provider list re-renders, apply saved state to lock in selections
                setTimeout(function() {
                    applySavedBookingState();
                }, 200);
                
                // Refresh notes to show booking event
                if (typeof artRefreshNotes === 'function') {
                    artRefreshNotes(artDetailData.requestId, true);
                }
                
                // Update status dropdown
                var newStatus = isTentative ? 'Tentative' : 'Booked';
                $('#status-dropdown').val(newStatus).trigger('change');
                
                // Show toast
                showBookingToast(data.message, isTentative ? 'warning' : 'success');
                showNotice(data.message, 'success');
                
                // Update buttons
                updateBookingButtons();
                
                // Scroll to top to show Active Booking card
                $('html, body').animate({ scrollTop: 0 }, 500);
                
                // Reload Amelia calendar iframe to show new/updated appointment
                var iframe = $('#amelia-calendar-frame');
                if (iframe.length) {
                    iframe.attr('src', iframe.attr('src'));
                }
                
            } else {
                showNotice(response.data.message, 'error');
            }
        }).fail(function() {
            btn.prop('disabled', false);
            btn.html(originalText);
            showNotice('<?php _e('Network error', 'amelia-cpt-sync'); ?>', 'error');
        });
    }
    
    /**
     * Reset to Current Booking Button
     */
    $('#btn-reset-to-current').on('click', function() {
        resetToCurrentBooking();
    });
    
    /**
     * Tentative Booking Button
     */
    $('#btn-tentative-booking').on('click', function() {
        manageBooking('tentative', $(this));
    });
    
    /**
     * Confirm Booking Button
     */
    $('#btn-formal-booking').on('click', function() {
        manageBooking('confirmed', $(this));
    });
    
    /**
     * Cancel Booking Button - Deletes any existing booking
     */
    $('#btn-cancel-booking').on('click', function() {
        var btn = $(this);
        
        if (!hasExistingBooking()) {
            showNotice('<?php _e('No booking to cancel', 'amelia-cpt-sync'); ?>', 'error');
            return;
        }
        
        if (!confirm('<?php _e('Are you sure you want to cancel this booking? This will delete the Amelia appointment.', 'amelia-cpt-sync'); ?>')) {
            return;
        }
        
        btn.prop('disabled', true);
        var originalText = btn.html();
        btn.html('<span class="dashicons dashicons-update spin"></span> <?php _e('Canceling...', 'amelia-cpt-sync'); ?>');
        
        $.post(ajaxurl, {
            action: 'art_delete_booking',
            nonce: artDetailData.nonce,
            request_id: artDetailData.requestId
        }, function(response) {
            btn.prop('disabled', false);
            btn.html(originalText);
            
            if (response.success) {
                // Hide the active booking card
                $('#active-booking-card').slideUp();
                
                // Reset booking state
                artDetailData.hasActiveBooking = false;
                artDetailData.activeAppointmentId = null;
                artDetailData.activeBookingId = null;
                
                // Update buttons
                updateBookingButtons();
                
                // Update status dropdown to tentative
                $('#status-dropdown').val('Tentative').trigger('change');
                
                // Show toast
                showBookingToast('<?php _e('Booking canceled successfully', 'amelia-cpt-sync'); ?>', 'warning');
                showNotice('<?php _e('Booking canceled successfully', 'amelia-cpt-sync'); ?>', 'success');
                
                // Scroll to top
                $('html, body').animate({ scrollTop: 0 }, 500);
                
                // Reload Amelia calendar iframe
                var iframe = $('#amelia-calendar-frame');
                if (iframe.length) {
                    iframe.attr('src', iframe.attr('src'));
                }
            } else {
                showNotice('<?php _e('Cancel failed:', 'amelia-cpt-sync'); ?> ' + response.data.message, 'error');
            }
        }).fail(function() {
            btn.prop('disabled', false);
            btn.html(originalText);
            showNotice('<?php _e('Network error during cancellation', 'amelia-cpt-sync'); ?>', 'error');
        });
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
                    
                    // Update the active booking card with new data (preserve existing booking_type)
                    updateActiveBookingCard({
                        booking_id: artDetailData.activeBookingId,
                        appointment_id: artDetailData.activeAppointmentId,
                        booking_type: artDetailData.bookingType, // Keep current type (tentative stays tentative, confirmed stays confirmed)
                        service_name: $('#pillar-service option:selected').text(),
                        category_name: $('#pillar-category option:selected').text(),
                        formatted_date: response.data.formatted_date,
                        formatted_time: response.data.formatted_time,
                        provider_name: response.data.provider_name,
                        location_name: $('#pillar-location option:selected').text() || ''
                    });
                    
                    // Update artDetailData with new details
                    artDetailData.existingBookedDateTime = $('#selected-slot-datetime').val();
                    artDetailData.existingBookedProviderId = selectedProviderId;
                    
                    // Show toast
                    var toastType = (artDetailData.bookingType === 'tentative') ? 'warning' : 'success';
                    showBookingToast('<?php _e('Booking details updated!', 'amelia-cpt-sync'); ?>', toastType);
                    showNotice('<?php _e('Booking details updated successfully!', 'amelia-cpt-sync'); ?>', 'success');
                    
                    // Scroll to top
                    $('html, body').animate({ scrollTop: 0 }, 500);
                    
                    // Reload Amelia calendar iframe
                    var iframe = $('#amelia-calendar-frame');
                    if (iframe.length) {
                        iframe.attr('src', iframe.attr('src'));
                    }
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
     * @param btn - The button element
     * @param slotDatetime - Selected date/time
     * @param providerId - Selected provider ID
     * @param bookingType - 'tentative' or 'confirmed' (default: 'confirmed')
     * @param callback - Optional callback function
     */
    function createNewBooking(btn, slotDatetime, providerId, bookingType, callback) {
        // Handle legacy calls where bookingType might be the callback
        if (typeof bookingType === 'function') {
            callback = bookingType;
            bookingType = 'confirmed';
        }
        bookingType = bookingType || 'confirmed';
        
        btn.prop('disabled', true);
        var originalText = btn.html();
        var loadingText = bookingType === 'tentative' 
            ? '<?php _e('Creating tentative...', 'amelia-cpt-sync'); ?>'
            : '<?php _e('Confirming booking...', 'amelia-cpt-sync'); ?>';
        btn.html('<span class="dashicons dashicons-update spin"></span> ' + loadingText);
        
        $.post(ajaxurl, {
            action: 'art_create_booking',
            nonce: artDetailData.nonce,
            request_id: artDetailData.requestId,
            provider_id: providerId,
            slot_datetime: slotDatetime,
            booking_type: bookingType
        }, function(response) {
            btn.prop('disabled', false);
            btn.html(originalText);
            
            if (response.success) {
                var isTentative = (response.data.booking_type === 'tentative');
                
                // Update the active booking card with full details and booking type
                updateActiveBookingCard({
                    booking_id: response.data.booking_id,
                    appointment_id: response.data.appointment_id,
                    booking_type: response.data.booking_type,
                    service_name: response.data.service_name || $('#pillar-service option:selected').text(),
                    category_name: response.data.category_name || $('#pillar-category option:selected').text(),
                    formatted_date: response.data.formatted_date,
                    formatted_time: response.data.formatted_time,
                    provider_name: response.data.provider_name,
                    location_name: response.data.location_name || ''
                });
                
                // Update Time & Duration fields with actual booked times from Amelia
                if (response.data.booked_start_local) {
                    // booked_start_local is in format "2025-12-27T03:51"
                    var parts = response.data.booked_start_local.split('T');
                    if (parts.length === 2) {
                        $('#pillar-date').val(parts[0]);
                        $('#pillar-time').val(parts[1]);
                        updateDatetimeSummary();
                    }
                }
                if (response.data.booked_duration_seconds) {
                    var bookedDuration = response.data.booked_duration_seconds;
                    $('#pillar-duration-seconds').val(bookedDuration);
                    
                    // Update dropdown if exact match exists
                    var matchingOption = $('#pillar-duration-selector option[value="' + bookedDuration + '"]');
                    if (matchingOption.length) {
                        $('#pillar-duration-selector').val(bookedDuration);
                    }
                    
                    // Update duration display
                    $('#duration-display').text(formatDuration(bookedDuration));
                }
                
                // Auto-save the updated times to database
                setTimeout(function() {
                    savePillarsAuto(false);
                }, 100);
                
                // Show toast notification with appropriate styling
                var toastMessage = isTentative 
                    ? '<?php _e('Tentative booking created!', 'amelia-cpt-sync'); ?>'
                    : '<?php _e('Booking confirmed!', 'amelia-cpt-sync'); ?>';
                showBookingToast(toastMessage, isTentative ? 'warning' : 'success');
                
                // Update status dropdown
                var newStatus = isTentative ? 'Tentative' : 'Booked';
                $('#status-dropdown').val(newStatus).trigger('change');
                
                showNotice(response.data.message, 'success');
                
                // Update booking buttons
                updateBookingButtons();
                
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
        $('#btn-tentative-booking, #btn-formal-booking').prop('disabled', true);
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
    var hasAutoSelectedProvider = false; // Flag to only auto-select provider once
    var lastOrchestratorResult = null; // Store last orchestrator result for re-rendering
    
    function updateProviderList() {
        console.log('ART DEBUG: updateProviderList() called', {
            isAutoPopulating: bookingViewState.isAutoPopulating,
            currentSelectedProviderId: selectedProviderId,
            timestamp: new Date().toISOString()
        });
        
        var activeDateBtn = $('#picker-dates-list .art-picker-date-btn.active');
        var providerList = $('#provider-list');
        var confirmSection = $('#picker-confirm-section');
        
        var providerCount = artDetailData.providers ? Object.keys(artDetailData.providers).length : 0;
        
        // Reset selection (unless we're auto-populating)
        if (!bookingViewState.isAutoPopulating) {
            console.log('ART DEBUG: Resetting provider selection (not auto-populating)');
            selectedProviderId = null;
            confirmSection.hide();
            $('#btn-use-custom-time').prop('disabled', true);
        } else {
            console.log('ART DEBUG: SKIPPING reset - auto-population in progress');
        }
        
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
     * Check availability using Booking Orchestrator (includes resources + providers)
     */
    function checkProviderAvailabilityEngine(dateStr, timeStr) {
        var providerList = $('#provider-list');
        var resourceList = $('#resource-status-list');
        var serviceId = $('#pillar-service').val();
        var duration = $('#pillar-duration-seconds').val() || artDetailData.serviceDuration || 3600;
        var locationId = $('#pillar-location').val() || 0;
        var persons = $('#pillar-persons').val() || 1;
        
        if (!serviceId) {
            providerList.html('<div class="provider-placeholder"><?php _e('Select a service first', 'amelia-cpt-sync'); ?></div>');
            return;
        }
        
        // Show loading states
        providerList.html(
            '<div class="provider-loading">' +
                '<span class="dashicons dashicons-update spin"></span> ' +
                '<?php _e('Checking providers...', 'amelia-cpt-sync'); ?>' +
            '</div>'
        );
        
        resourceList.html(
            '<div class="resource-placeholder">' +
                '<span class="dashicons dashicons-update spin"></span> ' +
                '<?php _e('Checking resources...', 'amelia-cpt-sync'); ?>' +
            '</div>'
        );
        
        // Call Booking Orchestrator (unified availability check)
        // If there's an active booking, exclude it from conflict detection (prevent self-blocking)
        var excludeAppointmentId = (artDetailData.hasActiveBooking && artDetailData.activeAppointmentId) 
            ? artDetailData.activeAppointmentId 
            : 0;
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'art_check_booking_availability',  // Orchestrator endpoint
                nonce: artDetailData.nonce,
                service_id: serviceId,
                date: dateStr,
                time: timeStr,
                duration: duration,
                location_id: locationId,
                persons: persons,
                selected_resources: getSelectedResources(),
                exclude_appointment_id: excludeAppointmentId
            },
            success: function(response) {
                if (response.success) {
                    try {
                    handleOrchestratorResult(response.data);
                    } catch (error) {
                        console.error('ART Frontend Error in handleOrchestratorResult:', error);
                        console.error('Response data:', response.data);
                        providerList.html(
                            '<div class="provider-error">' +
                                '<span class="dashicons dashicons-warning"></span> ' +
                                '<?php _e('Error displaying results. Check browser console.', 'amelia-cpt-sync'); ?>' +
                            '</div>'
                        );
                        resourceList.html('<div class="resource-placeholder"><?php _e('JavaScript error occurred', 'amelia-cpt-sync'); ?></div>');
                    }
                } else {
                    providerList.html(
                        '<div class="provider-error">' +
                            '<span class="dashicons dashicons-warning"></span> ' +
                            (response.data.message || '<?php _e('Error checking availability', 'amelia-cpt-sync'); ?>') +
                        '</div>'
                    );
                    resourceList.html('<div class="resource-placeholder"><?php _e('Error checking resources', 'amelia-cpt-sync'); ?></div>');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('ART AJAX Error:', textStatus, errorThrown);
                console.error('Response:', jqXHR.responseText);
                providerList.html(
                    '<div class="provider-error">' +
                        '<span class="dashicons dashicons-warning"></span> ' +
                        '<?php _e('Network error. Please try again.', 'amelia-cpt-sync'); ?>' +
                    '</div>'
                );
                resourceList.html('<div class="resource-placeholder"><?php _e('Network error', 'amelia-cpt-sync'); ?></div>');
            }
        });
    }
    
    /**
     * Apply saved booking state to the DOM (FINAL PASS)
     * 
     * Runs ONCE after all orchestrator renders complete.
     * Overrides orchestrator greedy auto-selections with actual saved assignments.
     * Handles: resource qty inputs, resource highlights, provider highlight, confirm section.
     */
    function applySavedBookingState() {
        if (!artDetailData.hasActiveBooking) return;
        
        var activeResources = artDetailData.activeResources || [];
        var activeProviderId = artDetailData.existingBookedProviderId;
        
        console.log('ART: Applying saved booking state', {
            resources: activeResources,
            providerId: activeProviderId,
            mode: resourceState.mode
        });
        
        // === RESOURCES: Override qty inputs and highlights with saved data ===
        if (activeResources.length > 0 && resourceState.mode === 'composite') {
            // First, clear all current selections (undo greedy picks)
            $('.composite-qty-select').val(0);
            $('.composite-group-display .resource-item').removeClass('selected');
            
            // Apply saved assignments
            activeResources.forEach(function(ar) {
                var rid = ar.id || ar;
                var qty = ar.quantity_used || ar.quantity || 1;
                
                var $input = $('.composite-qty-select[data-resource-id="' + rid + '"]');
                if ($input.length) {
                    var maxQty = parseInt($input.data('max-qty')) || 1;
                    $input.val(Math.min(qty, maxQty));
                    $input.closest('.resource-item').addClass('selected');
                } else {
                    // No qty input (unavailable or qty=1 readonly) — just highlight
                    $('.resource-item[data-resource-id="' + rid + '"]').addClass('selected');
                }
            });
            
            // Update all group totals
            $('.composite-group-display').each(function() {
                var $firstInput = $(this).find('.composite-qty-select').first();
                if ($firstInput.length && typeof updateCompositeGroupTotal === 'function') {
                    updateCompositeGroupTotal($firstInput);
                }
            });
            
            // Update resourceState with saved data
            resourceState.selectedResources = activeResources.map(function(ar) {
                return {
                    resource_id: parseInt(ar.id || ar),
                    quantity: ar.quantity_used || ar.quantity || 1
                };
            });
            
            console.log('ART: Saved resource state applied:', resourceState.selectedResources);
        }
        
        // === PROVIDER: Ensure highlight persists ===
        if (activeProviderId) {
            selectedProviderId = activeProviderId;
            $('.provider-item').removeClass('selected');
            var $providerCard = $('.provider-item[data-provider-id="' + activeProviderId + '"]');
            if ($providerCard.length) {
                $providerCard.addClass('selected');
            }
            
            // Ensure confirm section is visible
            $('#selected-provider-id').val(activeProviderId);
            $('#picker-confirm-section').show();
            $('#btn-use-custom-time').prop('disabled', false);
        }
    }
    
    /**
     * Handle unified result from Booking Orchestrator
     */
    function handleOrchestratorResult(data) {
        console.log('Orchestrator result:', data);
        
        // Store result for later re-rendering
        lastOrchestratorResult = data;
        
        // CRITICAL: Sync resourceState.mode from orchestrator response
        // This ensures click handlers know the correct mode (composite vs pool vs mirrored)
        if (data.resource_mode) {
            resourceState.mode = data.resource_mode;
        }
        
        // Update grid mode class
        $('.art-picker-container')
            .removeClass('mode-none mode-mirrored mode-shared-pool mode-quantity-pool mode-provider-bound mode-location-bound mode-composite mode-hybrid')
            .addClass('mode-' + (data.resource_mode || 'none'));
        
        // Render resource column
        renderResourceColumn(data);
        
        // Render provider column (gated by resource availability)
        if (data.resource_block) {
            showResourceBlockedProviders(data.resource_message);
        } else {
            renderAvailabilityEngineResults(data.providers, data.error, data.message);
        }
        
        // Re-apply provider highlight after render (prevents flicker from multiple orchestrator calls)
        if (selectedProviderId) {
            setTimeout(function() {
                var $providerCard = $('.provider-item[data-provider-id="' + selectedProviderId + '"]');
                if ($providerCard.length && !$providerCard.hasClass('selected')) {
                    $providerCard.addClass('selected');
                    $('#picker-confirm-section').show();
                    $('#btn-use-custom-time').prop('disabled', false);
                }
            }, 60);
        }
    }
    
    /**
     * Render resource availability column (matches provider styling)
     */
    /**
     * Render SHARED POOL resources (Phase 4)
     */
    /**
     * Render SHARED POOL resources (simplified - uses buildResourceItem)
     */
    function renderSharedPoolResources(data, appendMode) {
        var $container = $('#resource-status-list');
        var pool = data.resources.pool || [];
        var selected = data.resources.selected;
        var config = data.resources.config || {};
        var availableCount = data.resources.available_count || 0;
        
        if (pool.length === 0) {
            if (!appendMode) {
                $container.html('<div class="resource-placeholder"><?php _e('No resources in pool', 'amelia-cpt-sync'); ?></div>');
            } else {
                $container.append('<div class="resource-placeholder"><?php _e('No resources in pool', 'amelia-cpt-sync'); ?></div>');
            }
            return;
        }
        
        var html = '';
        
        // Pool header with summary
        var headerClass = availableCount > 0 ? 'available' : 'blocked';
        var headerIcon = availableCount > 0 ? '✓' : '✗';
        var headerText = availableCount > 0 ? 
            availableCount + ' of ' + pool.length + ' <?php _e('POOL RESOURCES AVAILABLE', 'amelia-cpt-sync'); ?>' :
            '<?php _e('NO POOL RESOURCES AVAILABLE', 'amelia-cpt-sync'); ?>';
        
        html += '<div class="resource-group-label ' + headerClass + '">';
        html += '<span class="dashicons dashicons-networking"></span> ';
        html += headerIcon + ' ' + headerText;
        html += '</div>';
        
        // Strategy indicator (simple text, not badge)
        var strategyLabel = {
            'first_available': 'First Available',
            'least_used': 'Load Balanced',
            'manual': 'Manual Selection'
        }[config.selection_strategy] || config.selection_strategy;
        
        html += '<div style="margin: 8px 12px; font-size: 11px; color: #64748B;">';
        html += '<?php _e('Strategy:', 'amelia-cpt-sync'); ?> ' + strategyLabel;
        html += '</div>';
        
        // Check if there's a currently assigned resource from active booking (matches provider pattern)
        var currentlyAssignedResource = null;
        var isInCurrentMode = (typeof bookingViewState !== 'undefined' && bookingViewState.mode === 'current');
        
        if (isInCurrentMode && artDetailData.hasActiveBooking && artDetailData.activeResource) {
            // Find which pool resource is currently assigned
            currentlyAssignedResource = pool.find(function(r) {
                return r.id == artDetailData.activeResource.id;
            });
            
            if (currentlyAssignedResource) {
                // Remove from main pool list (will show separately)
                pool = pool.filter(function(r) {
                    return r.id != artDetailData.activeResource.id;
                });
            }
        }
        
        // Show currently assigned resource first (if exists)
        if (currentlyAssignedResource) {
            html += '<div class="resource-group">';
            html += '<div class="resource-group-label" style="background: #E0E7FF; color: #4338CA; border-left: 3px solid #4338CA;">';
            html += '<span class="dashicons dashicons-saved"></span> <?php _e('Currently Selected Resource', 'amelia-cpt-sync'); ?></div>';
            
            var qtyInfo = {
                total: currentlyAssignedResource.total_quantity || 1,
                available: currentlyAssignedResource.available_quantity || 0,
                booked: currentlyAssignedResource.booked_quantity || 1
            };
            // Use actual conflicts array from backend, not message string
            var conflicts = currentlyAssignedResource.conflicts || [];
            
            html += buildResourceItem(currentlyAssignedResource.id, currentlyAssignedResource.name, currentlyAssignedResource.status, qtyInfo, conflicts, true);
            html += '</div>';
        }
        
        // Show label for remaining pool resources (if we separated out the current one)
        if (currentlyAssignedResource && pool.length > 0) {
            html += '<div class="resource-group-label" style="background: #F8FAFC; color: #64748B; border-left: 3px solid #CBD5E1;">';
            html += '<span class="dashicons dashicons-networking"></span> <?php _e('Other Pool Resources', 'amelia-cpt-sync'); ?></div>';
        }
        
        // Pool resources list (using buildResourceItem)
        pool.forEach(function(resource) {
            var quantityInfo = {
                total: resource.total_quantity || 1,
                available: resource.available_quantity || 0,
                booked: resource.booked_quantity || 0
            };
            
            // Use actual conflicts array from backend (not message string)
            var conflicts = resource.conflicts || [];
            
            html += buildResourceItem(resource.id, resource.name, resource.status, quantityInfo, conflicts, false);
        });
        
        // Block alert (if all pool resources unavailable)
        if (data.resource_block) {
            html += '<div style="margin: 12px; padding: 12px; background: #FEF3C7; border: 2px solid #F59E0B; border-radius: 6px;">';
            html += '<div style="color: #92400E; font-weight: 600; font-size: 12px; margin-bottom: 6px;">⚠️ <?php _e('All Pool Resources Blocked', 'amelia-cpt-sync'); ?></div>';
            html += '<div style="color: #78350F; font-size: 11px;">' + (data.resource_message || '<?php _e('All resources in pool are booked', 'amelia-cpt-sync'); ?>') + '</div>';
            html += '</div>';
        }
        
        // Use append when mode badge was already written to container
        if (appendMode) {
            $container.append(html);
        } else {
            $container.html(html);
        }
        
        // Auto-select the currently assigned resource (after DOM update)
        if (currentlyAssignedResource) {
            resourceState.selectedResourceId = currentlyAssignedResource.id;
            $('#selected-resource-id').val(currentlyAssignedResource.id);
            
            // Apply visual selection
            setTimeout(function() {
                $('.resource-item[data-resource-id="' + currentlyAssignedResource.id + '"]').addClass('selected');
                console.log('ART DEBUG: Auto-selected currently assigned resource:', currentlyAssignedResource.id);
            }, 50);
        }
    }
    
    /**
     * Render COMPOSITE (Multi Resource) mode - grouped requirements
     */
    function renderCompositeResources(data) {
        var $container = $('#resource-status-list');
        var groups = (data.resources && data.resources.groups) ? data.resources.groups : [];
        var allSatisfied = data.resources ? data.resources.all_satisfied : false;
        var anySoftBlock = data.resources ? data.resources.any_soft_block : false;
        
        if (groups.length === 0) {
            $container.append('<div class="resource-placeholder"><?php _e('No requirement groups configured', 'amelia-cpt-sync'); ?></div>');
            return;
        }
        
        var html = '';
        
        // Overall header
        var headerClass = allSatisfied ? 'available' : (anySoftBlock ? 'warning' : 'blocked');
        var headerIcon = allSatisfied ? '✓' : (anySoftBlock ? '⚠️' : '✗');
        var headerText = allSatisfied ? 
            '<?php _e('ALL', 'amelia-cpt-sync'); ?> ' + groups.length + ' <?php _e('REQUIREMENTS MET', 'amelia-cpt-sync'); ?>' :
            (anySoftBlock ? '<?php _e('MIGHT CONFLICT — FORCE BOOK AVAILABLE', 'amelia-cpt-sync'); ?>' : '<?php _e('REQUIREMENTS NOT MET', 'amelia-cpt-sync'); ?>');
        
        html += '<div class="resource-group-label ' + headerClass + '">';
        html += headerIcon + ' ' + headerText;
        html += '</div>';
        
        // Track auto-selected resources for post-render qty/highlight setup
        // Each entry: {resource_id, quantity}
        var autoSelections = [];
        
        // Check if viewing current booking with active resources
        var isInCurrentMode = (typeof bookingViewState !== 'undefined' && bookingViewState.mode === 'current');
        var activeResources = (artDetailData.activeResources || []);
        if (activeResources.length === 0 && artDetailData.activeResource) {
            activeResources = [artDetailData.activeResource];
        }
        
        // Build a lookup of active resource quantities (from saved booking)
        var activeResourceQtyMap = {};
        activeResources.forEach(function(ar) {
            var rid = ar.id || ar;
            var qty = ar.quantity_used || ar.quantity || 1;
            activeResourceQtyMap[rid] = qty;
        });
        
        // Render each requirement group
        groups.forEach(function(group, groupIdx) {
            var groupSatisfied = group.satisfied;
            var groupLabel = group.label || ('Group ' + (groupIdx + 1));
            var qtyNeeded = group.quantity_needed || 1;
            
            // Determine initial selections for this group
            // In current-booking mode: use saved assignment data (with quantities)
            // In exploring mode: use orchestrator auto-selection
            if (isInCurrentMode) {
                // Find active resources that belong to this group
                var groupResIds = (group.resources || []).map(function(r) { return r.id; });
                activeResources.forEach(function(ar) {
                    var rid = ar.id || ar;
                    if (groupResIds.indexOf(rid) !== -1 || groupResIds.indexOf(parseInt(rid)) !== -1) {
                        autoSelections.push({
                            resource_id: parseInt(rid),
                            quantity: ar.quantity_used || ar.quantity || 1
                        });
                    }
                });
            } else if (group.selected) {
                // Orchestrator auto-selection (could be array of {id, name, quantity} or single object)
                if (Array.isArray(group.selected)) {
                    group.selected.forEach(function(sel) {
                        autoSelections.push({
                            resource_id: parseInt(sel.id),
                            quantity: sel.quantity || 1
                        });
                    });
                } else if (group.selected.id) {
                    autoSelections.push({
                        resource_id: parseInt(group.selected.id),
                        quantity: qtyNeeded
                    });
                }
            }
            
            // Group header with integrated counter (replaces separate bottom counter)
            html += '<div class="composite-group-display" data-group-index="' + groupIdx + '">';
            
            html += '<div class="composite-group-header" data-group-index="' + groupIdx + '" data-qty-needed="' + qtyNeeded + '" style="margin: 8px 0 4px 0; padding: 8px 12px; background: #F1F5F9; border-radius: 6px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: #475569; display: flex; justify-content: space-between; align-items: center;">';
            html += '<span>' + groupLabel.toUpperCase() + ' (<?php _e('need', 'amelia-cpt-sync'); ?> ' + qtyNeeded + ')</span>';
            html += '<span class="group-header-counter" style="font-size: 10px; font-weight: 600;"><span class="group-total-count">0</span> of ' + qtyNeeded + ' <?php _e('selected', 'amelia-cpt-sync'); ?></span>';
            html += '</div>';
            
            // Resource cards within group (with qty input for composite mode)
            var resources = group.resources || [];
            resources.forEach(function(resource) {
                var quantityInfo = {
                    total: resource.total_quantity || 1,
                    available: resource.available_quantity || 0,
                    booked: resource.booked_quantity || 0
                };
                var conflicts = resource.conflicts || [];
                // Check if this resource is assigned in the current booking
                var isCurrentBookingResource = isInCurrentMode && activeResourceQtyMap.hasOwnProperty(resource.id);
                
                // 7th argument = true: show composite qty input
                html += buildResourceItem(resource.id, resource.name, resource.status, quantityInfo, conflicts, isCurrentBookingResource, true);
            });
            
            // (Group counter is now in the header row above)
            
            html += '</div>'; // close composite-group-display
        });
        
        // Block alert if any group failed
        if (!allSatisfied) {
            var failedGroups = groups.filter(function(g) { return !g.satisfied; });
            var failedNames = failedGroups.map(function(g) { return g.label || 'Unnamed'; }).join(', ');
            
            html += '<div style="margin: 12px; padding: 12px; background: #FEF3C7; border: 2px solid #F59E0B; border-radius: 6px;">';
            html += '<div style="color: #92400E; font-weight: 600; font-size: 12px; margin-bottom: 4px;">';
            html += (anySoftBlock && !groups.some(function(g) { return g.block_type === 'hard'; })) ? 
                    '⚠️ <?php _e('Tentative conflicts — force book available', 'amelia-cpt-sync'); ?>' : 
                    '✗ <?php _e('Requirements not met', 'amelia-cpt-sync'); ?>';
            html += '</div>';
            html += '<div style="color: #78350F; font-size: 11px;"><?php _e('Blocked groups:', 'amelia-cpt-sync'); ?> ' + failedNames + '</div>';
            html += '</div>';
        }
        
        $container.append(html);
        
        // Post-render: Set qty inputs and apply selections from saved/orchestrator data
        if (autoSelections.length > 0) {
            setTimeout(function() {
                autoSelections.forEach(function(sel) {
                    var $input = $('.composite-qty-select[data-resource-id="' + sel.resource_id + '"]');
                    if ($input.length) {
                        var maxQty = parseInt($input.data('max-qty')) || 1;
                        var qty = Math.min(sel.quantity, maxQty);
                        // Directly set value and visual state
                        $input.val(qty);
                        var $card = $input.closest('.resource-item');
                        if (qty > 0) {
                            $card.addClass('selected');
                        }
                    } else {
                        // No qty input (unavailable resource) — just highlight
                        var $card = $('.resource-item[data-resource-id="' + sel.resource_id + '"]');
                        $card.addClass('selected');
                    }
                });
                
                // Update all group totals
                $('.composite-group-display').each(function() {
                    var $firstInput = $(this).find('.composite-qty-select').first();
                    if ($firstInput.length) {
                        updateCompositeGroupTotal($firstInput);
                    }
                });
                
                console.log('ART DEBUG: Composite auto-selections applied:', autoSelections);
            }, 50);
        }
    }
    
    /**
     * Render resource availability column (with quantity support)
     */
    function renderResourceColumn(data) {
        var $container = $('#resource-status-list');
        
        if (!data.resources || data.resource_mode === 'none') {
            $container.html('<div class="resource-placeholder"><?php _e('No resources required', 'amelia-cpt-sync'); ?></div>');
            return;
        }
        
        var html = '';
        
        // MODE BADGE: Show which resource mode this service uses (v2.34.0)
        var modeLabels = {
            'mirrored': { icon: '🔗', label: '<?php _e('Dedicated Resource', 'amelia-cpt-sync'); ?>' },
            'shared_pool': { icon: '🏊', label: '<?php _e('Resource Pool', 'amelia-cpt-sync'); ?>' },
            'composite': { icon: '📦', label: '<?php _e('Multi Resource', 'amelia-cpt-sync'); ?>' }
        };
        
        if (data.resource_mode && modeLabels[data.resource_mode]) {
            var modeInfo = modeLabels[data.resource_mode];
            html += '<div class="resource-mode-indicator" style="font-size: 10px; color: #64748B; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; padding: 4px 12px; margin-bottom: 4px;">';
            html += modeInfo.icon + ' ' + modeInfo.label;
            html += '</div>';
        }
        
        // SHARED POOL MODE: Different rendering
        if (data.resource_mode === 'shared_pool') {
            // Prepend mode badge, then delegate to pool renderer
            $container.html(html);
            renderSharedPoolResources(data, true); // true = append mode (don't clear container)
            return;
        }
        
        // COMPOSITE (MULTI RESOURCE) MODE: Group-based rendering
        if (data.resource_mode === 'composite') {
            $container.html(html);
            renderCompositeResources(data);
            return;
        }
        
        // MIRRORED MODE: Handle assigned resources
        var resources = data.resources.assigned || [];
        
        if (resources.length === 0) {
            $container.html('<div class="resource-placeholder"><?php _e('No resources configured', 'amelia-cpt-sync'); ?></div>');
            return;
        }
        
        // Multi-resource indicator
        if (resources.length > 1) {
            html += '<div class="multi-resource-header">';
            html += '<span class="dashicons dashicons-database"></span> ';
            html += resources.length + ' <?php _e('resources required', 'amelia-cpt-sync'); ?>';
            html += '</div>';
        }
        
        resources.forEach(function(resource, index) {
            // Group label for first resource
            if (index === 0) {
                var isCurrentBooking = (typeof bookingViewState !== 'undefined' && 
                                       bookingViewState.mode === 'current' && 
                                       artDetailData.hasActiveBooking && 
                                       artDetailData.activeResource &&
                                       artDetailData.activeResource.id === resource.id);
                
                if (isCurrentBooking) {
                    html += '<div class="resource-group-label current-selection">';
                    html += '<span class="dashicons dashicons-saved"></span> <?php _e('Currently Selected Resource', 'amelia-cpt-sync'); ?></div>';
                } else {
                    var isAvailable = resource.status === 'available';
                    var isSoftBlock = resource.status === 'soft_block';
                    var isPartial = resource.status === 'partial';
                    var labelClass = isAvailable ? 'available' : (isSoftBlock || isPartial ? 'warning' : 'blocked');
                    var labelText = isAvailable ? '<?php _e('AVAILABLE', 'amelia-cpt-sync'); ?>' : 
                                   (isSoftBlock ? '<?php _e('MIGHT CONFLICT', 'amelia-cpt-sync'); ?>' : '<?php _e('UNAVAILABLE', 'amelia-cpt-sync'); ?>');
                    html += '<div class="resource-group-label ' + labelClass + '">' + (isAvailable ? '✓ ' : (isSoftBlock ? '⚠️ ' : '✗ ')) + labelText + '</div>';
                }
            }
            
            // Build resource card using unified function
            var quantityInfo = {
                total: resource.total_quantity || 1,
                available: resource.available_quantity || 0,
                booked: resource.booked_quantity || 0
            };
            
            // Use actual conflicts array from backend (not message string)
            var conflicts = resource.conflicts || [];
            
            html += buildResourceItem(resource.id, resource.name, resource.status, quantityInfo, conflicts, false);
        });
        
        // Add alert if blocked
        if (data.resource_block) {
            html += '<div class="resource-block-alert" style="margin: 12px; padding: 12px; background: #FEF3C7; border: 2px solid #F59E0B; border-radius: 6px;">';
            html += '<div style="color: #92400E; font-weight: 600; font-size: 12px; margin-bottom: 6px;">⚠️ <?php _e('All Providers Blocked', 'amelia-cpt-sync'); ?></div>';
            html += '<div style="color: #78350F; font-size: 11px;">' + (data.resource_message || '<?php _e('This resource is booked', 'amelia-cpt-sync'); ?>') + '</div>';
            html += '</div>';
        }
        
        $container.html(html);
    }
    
    /**
     * Show resource blocked message in provider column
     */
    function showResourceBlockedProviders(message) {
        var html = '<div class="provider-blocked-by-resource">';
        html += '<div style="text-align: center; padding: 40px 20px;">';
        html += '<span class="dashicons dashicons-lock" style="font-size: 40px; color: #EF4444; opacity: 0.5;"></span>';
        html += '<h4 style="color: #EF4444; margin: 16px 0 8px 0;"><?php _e('All Providers Blocked', 'amelia-cpt-sync'); ?></h4>';
        html += '<p style="color: #64748B; font-size: 13px; margin: 0;">' + (message || '<?php _e('Resource unavailable', 'amelia-cpt-sync'); ?>') + '</p>';
        html += '<p style="color: #94A3B8; font-size: 12px; margin-top: 12px; font-style: italic;"><?php _e('Providers cannot be assigned without the required resource.', 'amelia-cpt-sync'); ?></p>';
        html += '</div>';
        html += '</div>';
        
        $('#provider-list').html(html);
    }
    
    /**
     * Render results from Availability Engine
     */
    function renderAvailabilityEngineResults(providers, hasError, errorMessage) {
        var providerList = $('#provider-list');
        var html = '';
        
        // Safety check: ensure providers is an array
        if (!providers || !Array.isArray(providers)) {
            console.error('ART: providers is not an array:', providers);
            providerList.html('<div class="provider-error"><?php _e('Invalid provider data received', 'amelia-cpt-sync'); ?></div>');
            return;
        }
        
        // Show warning if there was an error
        if (hasError && errorMessage) {
            html += '<div class="provider-warning" style="padding: 8px; background: #fff3cd; border-radius: 4px; margin-bottom: 10px; font-size: 12px;">' +
                '<span class="dashicons dashicons-warning" style="color: #856404;"></span> ' +
                errorMessage +
            '</div>';
        }
        
        // Check if we should show "Currently Selected Provider"
        // Only show this in 'current' mode (not when exploring alternatives)
        var existingProvider = null;
        var isInCurrentMode = (typeof bookingViewState !== 'undefined' && bookingViewState.mode === 'current');
        
        if (isInCurrentMode && artDetailData.hasActiveBooking && artDetailData.existingBookedProviderId) {
            existingProvider = providers.find(function(p) { 
                return p.id == artDetailData.existingBookedProviderId; 
            });
            
            // Remove from main list to show separately
            providers = providers.filter(function(p) { 
                return p.id != artDetailData.existingBookedProviderId; 
            });
        }
        
        // Show currently selected provider first (if exists and in current mode)
        if (existingProvider) {
            html += '<div class="provider-group">';
            html += '<div class="provider-group-label" style="background: #E0E7FF; color: #4338CA; border-left: 3px solid #4338CA;">' +
                    '<span class="dashicons dashicons-saved"></span> <?php _e('Currently Selected Provider', 'amelia-cpt-sync'); ?></div>';
            var conflictText = (existingProvider.conflicts && existingProvider.conflicts.length > 0) ? existingProvider.conflicts.join(', ') : '';
            html += buildProviderItemWithConflicts(existingProvider.id, existingProvider.name, getInitials(existingProvider.name), existingProvider.status_display, existingProvider.conflicts || []);
            html += '</div>';
        }
        
        // Group remaining providers by status
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
        
        // Set provider selection from existing booking + apply visual highlight
        if (artDetailData.hasActiveBooking && artDetailData.existingBookedProviderId && !hasAutoSelectedProvider) {
            hasAutoSelectedProvider = true;
            selectedProviderId = artDetailData.existingBookedProviderId;
            
            // Apply blue highlight to the currently selected provider card
            setTimeout(function() {
                var $providerCard = $('.provider-item[data-provider-id="' + selectedProviderId + '"]');
                if ($providerCard.length) {
                    $providerCard.addClass('selected');
                    
                    // Also set hidden input and show confirm section
                    $('#selected-provider-id').val(selectedProviderId);
                    $('#picker-confirm-section').show();
                    $('#btn-use-custom-time').prop('disabled', false);
                }
            }, 50);
            
            console.log('ART DEBUG: Set selectedProviderId from existing booking with visual selection:', selectedProviderId);
        }
    }
    
    /**
     * Convert request references to clickable links
     * Parses "(Req #XX)" pattern and converts to link
     */
    function linkifyRequestReferences(text) {
        if (!text) return text;
        
        // Match pattern: (Req #123) or (Req #45)
        return text.replace(/\(Req #(\d+)\)/g, function(match, requestId) {
            var url = '<?php echo admin_url("admin.php?page=art-request-detail&request_id="); ?>' + requestId;
            return '<a href="' + url + '" target="_blank" style="color: inherit; text-decoration: underline;" title="Open Request #' + requestId + ' in new tab">(Req #' + requestId + ')</a>';
        });
    }
    
    /**
     * Build provider item with conflict details
     * Enhanced to match resource pattern: grouped, clickable, dark red
     */
    function buildProviderItemWithConflicts(id, name, initials, status, conflicts) {
        var conflictHtml = '';
        if (conflicts && conflicts.length > 0) {
            var tentative = [];
            var confirmed = [];
            var other = [];  // For non-booking conflicts (shifts, buffers)
            
            conflicts.forEach(function(conflictText) {
                // Parse conflict strings for booking conflicts
                // Format: "Tentative booking - 9:28 PM - 1:28 AM (Req #58)"
                var tentativeMatch = conflictText.match(/Tentative booking - (.+?)(?: \(Req #(\d+)\))?$/);
                var confirmedMatch = conflictText.match(/Confirmed booking - (.+?)(?: \(Req #(\d+)\))?$/);
                
                if (tentativeMatch) {
                    var timeRange = tentativeMatch[1].trim();
                    var requestId = tentativeMatch[2];
                    
                    // Make time range clickable
                    var timeHtml = timeRange;
                    if (requestId) {
                        var url = '<?php echo admin_url('admin.php?page=art-request-detail&request_id='); ?>' + requestId;
                        timeHtml = '<a href="' + url + '" target="_blank" style="color: inherit; text-decoration: none; border-bottom: 1px dotted currentColor;">' + timeRange + '</a>';
                    }
                    tentative.push(timeHtml);
                    
                } else if (confirmedMatch) {
                    var timeRange = confirmedMatch[1].trim();
                    var requestId = confirmedMatch[2];
                    
                    // Make time range clickable
                    var timeHtml = timeRange;
                    if (requestId) {
                        var url = '<?php echo admin_url('admin.php?page=art-request-detail&request_id='); ?>' + requestId;
                        timeHtml = '<a href="' + url + '" target="_blank" style="color: inherit; text-decoration: none; border-bottom: 1px dotted currentColor;">' + timeRange + '</a>';
                    }
                    confirmed.push(timeHtml);
                    
                } else {
                    // Non-booking conflict (shift, buffer, etc.) - keep as-is
                    other.push(conflictText);
                }
            });
            
            conflictHtml = '<div class="provider-conflicts-detail">';
            
            // Show non-booking conflicts first (shifts, schedules)
            if (other.length > 0) {
                other.forEach(function(text) {
                    conflictHtml += '<div class="provider-conflict-line other">' + text + '</div>';
                });
            }
            
            // Show booking conflicts grouped by status
            if (tentative.length > 0) {
                conflictHtml += '<div class="provider-conflict-line tentative">Tentative: ' + tentative.join(', ') + '</div>';
            }
            if (confirmed.length > 0) {
                conflictHtml += '<div class="provider-conflict-line confirmed">Booked: ' + confirmed.join(', ') + '</div>';
            }
            
            conflictHtml += '</div>';
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
     * Build resource item (mirrors provider pattern - text + checkmark only)
     * @param {number} id - Resource ID
     * @param {string} name - Resource name
     * @param {string} status - Resource status (available, soft_block, unavailable)
     * @param {object} quantityInfo - {total: X, available: Y, booked: Z}
     * @param {array} conflicts - Array of conflict objects OR legacy message strings
     * @param {boolean} isCurrentBooking - If true, shows "Using X of Y" instead of "X of Y available"
     */
    function buildResourceItem(id, name, status, quantityInfo, conflicts, isCurrentBooking) {
        var cardClass = 'resource-item';
        if (status === 'available') cardClass += ' available';
        if (status === 'soft_block') cardClass += ' soft-block';
        if (status === 'unavailable') cardClass += ' unavailable';
        
        // Single status line
        var displayStatus = 'Available';
        if (quantityInfo && quantityInfo.total > 1) {
            if (isCurrentBooking) {
                // Format: "Using 1 of 4 (approx 2-3 others available)" when conflicts exist
                var using = quantityInfo.booked || 1;
                var total = quantityInfo.total;
                
                // Count other tentative and confirmed bookings from conflicts
                var otherTentative = 0;
                var otherConfirmed = 0;
                
                if (conflicts && conflicts.length > 0 && typeof conflicts[0] === 'object' && conflicts[0].time_range) {
                    conflicts.forEach(function(c) {
                        var qty = c.quantity_used || 1;
                        if (c.status === 'pending') {
                            otherTentative += qty;
                        } else if (c.status === 'approved') {
                            otherConfirmed += qty;
                        }
                    });
                }
                
                // Calculate availability range
                // Min: assume all tentative bookings stay
                var minAvailable = total - using - otherTentative - otherConfirmed;
                // Max: assume all tentative bookings cancel
                var maxAvailable = total - using - otherConfirmed;
                
                if (otherTentative > 0) {
                    // Show range when tentative bookings exist
                    displayStatus = 'Using ' + using + ' of ' + total + ' (approx ' + minAvailable + '-' + maxAvailable + ' others available)';
                } else {
                    // Exact count when no tentative bookings
                    displayStatus = 'Using ' + using + ' of ' + total + ' (' + maxAvailable + ' others available)';
                }
            } else {
                // Format: "3 of 4 available"
                displayStatus = quantityInfo.available + ' of ' + quantityInfo.total + ' available';
            }
        } else if (status === 'soft_block') {
            displayStatus = 'Might Conflict';
        } else if (status === 'unavailable') {
            displayStatus = 'Unavailable';
        }
        
        // Process conflicts - handle both array of objects and legacy string array
        var conflictHtml = '';
        if (conflicts && conflicts.length > 0) {
            // Check if conflicts is array of objects (new format) or strings (legacy)
            var isObjectArray = typeof conflicts[0] === 'object' && conflicts[0].time_range;
            
            if (isObjectArray) {
                // New format: Group by status and display time ranges
                var tentative = [];
                var confirmed = [];
                
                conflicts.forEach(function(conflict) {
                    var timeRange = conflict.time_range;
                    var requestId = conflict.request_id;
                    
                    // Make clickable link to request detail
                    var timeHtml = timeRange;
                    if (requestId) {
                        var url = '<?php echo admin_url('admin.php?page=art-request-detail&request_id='); ?>' + requestId;
                        timeHtml = '<a href="' + url + '" target="_blank" style="color: inherit; text-decoration: none; border-bottom: 1px dotted currentColor;">' + timeRange + '</a>';
                    }
                    
                    if (conflict.status === 'approved') {
                        confirmed.push(timeHtml);
                    } else {
                        tentative.push(timeHtml);
                    }
                });
                
                conflictHtml = '<div class="resource-conflicts-detail">';
                if (tentative.length > 0) {
                    conflictHtml += '<div class="resource-conflict-line tentative">Tentative: ' + tentative.join(', ') + '</div>';
                }
                if (confirmed.length > 0) {
                    conflictHtml += '<div class="resource-conflict-line confirmed">Booked: ' + confirmed.join(', ') + '</div>';
                }
                conflictHtml += '</div>';
            } else {
                // Legacy format: Just display strings (for backward compatibility)
                conflictHtml = '<div class="resource-conflicts">' + 
                    conflicts.map(linkifyRequestReferences).join('<br>') + '</div>';
            }
        }
        
        // Optional: Quantity input for composite mode (between info and checkmark)
        var qtyInputHtml = '';
        if (arguments.length > 6 && arguments[6] === true && status !== 'unavailable') {
            // showCompositeQty flag passed as 7th argument
            var maxQty = (quantityInfo && quantityInfo.available) ? quantityInfo.available : 1;
            var isReadonly = (maxQty <= 1);
            var readonlyAttr = isReadonly ? 'readonly' : '';
            var readonlyStyle = isReadonly ? 'background: #F1F5F9; color: #64748B; cursor: default;' : '';
            
            qtyInputHtml = '<div class="resource-qty-input" style="display: flex; align-items: center; gap: 4px; margin: 0 8px; flex-shrink: 0;">' +
                '<input type="number" class="composite-qty-select" ' +
                       'data-resource-id="' + id + '" ' +
                       'data-max-qty="' + maxQty + '" ' +
                       'min="0" max="' + maxQty + '" value="0" ' +
                       readonlyAttr + ' ' +
                       'style="width: 48px; padding: 4px 6px; border: 1px solid #E0E5F1; border-radius: 4px; font-size: 12px; text-align: center; ' + readonlyStyle + '">' +
            '</div>';
        }
        
        return '<div class="' + cardClass + '" data-resource-id="' + id + '" data-available-qty="' + ((quantityInfo && quantityInfo.available) || 0) + '">' +
            '<div class="resource-info">' +
                '<div class="resource-name">' + name + '</div>' +
                '<div class="resource-status">' + displayStatus + '</div>' +
                conflictHtml +
            '</div>' +
            qtyInputHtml +
            '<div class="resource-check"><span class="dashicons dashicons-yes"></span></div>' +
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
        
        console.log('ART DEBUG: Provider item clicked', {
            providerId: providerId,
            currentSelected: selectedProviderId,
            isAutoPopulating: bookingViewState.isAutoPopulating
        });
        
        // Toggle selection
        if (selectedProviderId == providerId) {
            // Deselect
            console.log('ART DEBUG: Deselecting provider');
            item.removeClass('selected');
            selectedProviderId = null;
            
            // Clear hidden inputs
            $('#selected-provider-id').val('');
            
            $('#picker-confirm-section').hide();
            $('#btn-use-custom-time').prop('disabled', true);
        } else {
            // Select this one
            console.log('ART DEBUG: Selecting provider', providerId);
            $('.provider-item').removeClass('selected');
            item.addClass('selected');
            selectedProviderId = providerId;
            
            // UPDATE: Set hidden inputs for booking manager
            $('#selected-provider-id').val(providerId);
            
            // Also update datetime from current selections
            var activeDateBtn = $('#picker-dates-list .art-picker-date-btn.active');
            var customTime = $('#custom-time-input').val();
            if (activeDateBtn.length && customTime) {
                var dateStr = activeDateBtn.data('date');
                var datetimeStr = dateStr + ' ' + customTime;
                $('#selected-slot-datetime').val(datetimeStr);
                console.log('ART DEBUG: Updated hidden datetime to:', datetimeStr);
            }
            
            $('#picker-confirm-section').show();
            $('#btn-use-custom-time').prop('disabled', false);
        }
        
        console.log('ART DEBUG: After provider click, selectedProviderId =', selectedProviderId);
    });
    
    // Handle resource selection (mode-aware: pool = single select, composite = per-group select)
    $(document).on('click', '.resource-item', function() {
        var item = $(this);
        var resourceId = item.data('resource-id');
        
        // Skip if unavailable
        if (item.hasClass('unavailable')) {
            console.log('ART DEBUG: Resource unavailable, click ignored');
            return;
        }
        
        var isCompositeMode = (resourceState.mode === 'composite');
        
        console.log('ART DEBUG: Resource item clicked', {
            resourceId: resourceId,
            mode: resourceState.mode,
            currentSelected: resourceState.selectedResourceId
        });
        
        if (isCompositeMode) {
            // COMPOSITE MODE: Click toggles card and qty
            var $qtyInput = item.find('.composite-qty-select');
            
            if ($qtyInput.length) {
                var currentQty = parseInt($qtyInput.val()) || 0;
                if (currentQty > 0) {
                    // Deselect: set to 0
                    $qtyInput.val(0);
                    item.removeClass('selected');
                } else {
                    // Select: set to 1
                    $qtyInput.val(1);
                    item.addClass('selected');
                }
                // Update group total and state
                updateCompositeGroupTotal($qtyInput);
            } else {
                // No qty input (unavailable): just toggle visual
                item.toggleClass('selected');
            }
            
            // Trigger exploring mode if resource selection changed from current booking
            if (artDetailData.hasActiveBooking && typeof bookingViewState !== 'undefined' && bookingViewState.mode === 'current') {
                enterExploringMode();
            }
            
            return;
            
        } else {
            // POOL / MIRRORED MODE: Single selection across all resources
            if (resourceState.selectedResourceId == resourceId) {
                // Deselect
                console.log('ART DEBUG: Deselecting resource');
                item.removeClass('selected');
                resourceState.selectedResourceId = null;
                $('#selected-resource-id').val('');
            } else {
                // Select this one (clear all others)
                console.log('ART DEBUG: Selecting resource', resourceId);
                $('.resource-item').removeClass('selected');
                item.addClass('selected');
                resourceState.selectedResourceId = resourceId;
                $('#selected-resource-id').val(resourceId);
            }
            
            console.log('ART DEBUG: After resource click, selectedResourceId =', resourceState.selectedResourceId);
        }
    });
    
    // Shared function: update composite group totals + visual state + resourceState
    function updateCompositeGroupTotal($input) {
        if (!$input || !$input.length) return;
        
        var resourceId = parseInt($input.data('resource-id'));
        var maxQty = parseInt($input.data('max-qty')) || 1;
        var qty = parseInt($input.val()) || 0;
        
        // Clamp value
        if (qty < 0) qty = 0;
        if (qty > maxQty) qty = maxQty;
        $input.val(qty);
        
        // Auto-toggle card selection based on qty
        var $card = $input.closest('.resource-item');
        if (qty > 0) {
            $card.addClass('selected');
        } else {
            $card.removeClass('selected');
        }
        
        // Update group total display
        var $group = $input.closest('.composite-group-display');
        if ($group.length) {
            var groupIdx = $group.data('group-index');
            var groupTotal = 0;
            $group.find('.composite-qty-select').each(function() {
                groupTotal += parseInt($(this).val()) || 0;
            });
            
            // Update counter in the header row
            var $headerDisplay = $('.composite-group-header[data-group-index="' + groupIdx + '"]');
            var qtyNeeded = parseInt($headerDisplay.data('qty-needed')) || 1;
            var $counter = $headerDisplay.find('.group-total-count');
            $counter.text(groupTotal);
            
            // Color: red = under, green = exact, orange = over-resourced
            var $counterSpan = $headerDisplay.find('.group-header-counter');
            if (groupTotal < qtyNeeded) {
                $counterSpan.css('color', '#DC2626'); // Red: under-resourced
            } else if (groupTotal === qtyNeeded) {
                $counterSpan.css('color', '#059669'); // Green: exact match
            } else {
                $counterSpan.css('color', '#D97706'); // Orange: over-resourced
            }
        }
        
        // Rebuild selectedResources: array of {resource_id, quantity} objects
        resourceState.selectedResources = [];
        $('.composite-group-display .composite-qty-select').each(function() {
            var q = parseInt($(this).val()) || 0;
            if (q > 0) {
                resourceState.selectedResources.push({
                    resource_id: parseInt($(this).data('resource-id')),
                    quantity: q
                });
            }
        });
    }
    
    // Composite mode: Quantity input change/input handler (for manual typing)
    $(document).on('change input', '.composite-qty-select', function(e) {
        e.stopPropagation();
        updateCompositeGroupTotal($(this));
        
        // Trigger exploring mode if qty changed from current booking
        if (artDetailData.hasActiveBooking && typeof bookingViewState !== 'undefined' && bookingViewState.mode === 'current') {
            enterExploringMode();
        }
    });
    
    // Prevent qty input click from triggering card click
    $(document).on('click', '.composite-qty-select', function(e) {
        e.stopPropagation();
    });
    
    // Update provider list when custom time changes
    $('#custom-time-input').on('change input', function() {
        updateProviderList();
        
        // Check if this moves us away from current booking
        if (artDetailData.hasActiveBooking && hasBookingChanges()) {
            enterExploringMode();
        }
        
        // UPDATE: Also update hidden datetime input
        var activeDateBtn = $('#picker-dates-list .art-picker-date-btn.active');
        var customTime = $(this).val();
        if (activeDateBtn.length && customTime && selectedProviderId) {
            var dateStr = activeDateBtn.data('date');
            var datetimeStr = dateStr + ' ' + customTime;
            $('#selected-slot-datetime').val(datetimeStr);
            console.log('ART DEBUG: Time changed, updated hidden datetime to:', datetimeStr);
        }
    });
    
    // Also update when date is selected
    $(document).on('click', '.art-picker-date-btn', function() {
        setTimeout(function() {
            // Check if this moves us away from current booking
            if (artDetailData.hasActiveBooking && hasBookingChanges()) {
                enterExploringMode();
            }
            
            updateProviderList();
            
            // UPDATE: Also update hidden datetime input
            var activeDateBtn = $('#picker-dates-list .art-picker-date-btn.active');
            var customTime = $('#custom-time-input').val();
            if (activeDateBtn.length && customTime && selectedProviderId) {
                var dateStr = activeDateBtn.data('date');
                var datetimeStr = dateStr + ' ' + customTime;
                $('#selected-slot-datetime').val(datetimeStr);
                console.log('ART DEBUG: Date changed, updated hidden datetime to:', datetimeStr);
            }
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
        
        // After confirming selection, enable booking buttons and hide Confirm Selection button
        $('#picker-confirm-section').hide();
        $('#btn-tentative-booking, #btn-formal-booking').prop('disabled', false);
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
                    if (activeDate && typeof renderFilteredDates === 'function') {
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
    
    // ========================================================================
    // NOTES SYSTEM
    // ========================================================================
    
    var artNotesState = {
        offset: 0,
        limit: 20,
        loading: false,
        hasMore: true,
        allNotes: []
    };
    
    /**
     * Initialize rich text note composer
     */
    function initNoteComposer() {
        var input = $('#note-input');
        var addBtn = $('#btn-add-note');
        var charCounter = $('.char-counter');
        
        if (!input.length) return; // Notes card not present
        
        // Format buttons
        $('.format-btn').on('click', function(e) {
            e.preventDefault();
            var command = $(this).data('command');
            
            if (command === 'bold') {
                document.execCommand('bold', false, null);
            } else if (command === 'list') {
                document.execCommand('insertUnorderedList', false, null);
            }
            
            input.focus();
            updateCharCount();
        });
        
        // Character counter
        input.on('input', updateCharCount);
        input.on('keyup', updateCharCount);
        input.on('paste', function() {
            setTimeout(updateCharCount, 10);
        });
        
        function updateCharCount() {
            var text = input.text().trim();
            var length = text.length;
            
            charCounter.text(length + '/1000');
            
            if (length > 1000) {
                charCounter.addClass('over-limit');
                addBtn.prop('disabled', true);
            } else if (length > 0) {
                charCounter.removeClass('over-limit');
                addBtn.prop('disabled', false);
            } else {
                addBtn.prop('disabled', true);
            }
        }
        
        // Add note
        addBtn.on('click', function() {
            var content = input.html().trim();
            if (!content) return;
            
            addBtn.prop('disabled', true);
            var originalText = addBtn.text();
            addBtn.html('<span class="dashicons dashicons-update spin"></span> <?php _e('Adding...', 'amelia-cpt-sync'); ?>');
            
            $.post(ajaxurl, {
                action: 'art_add_note',
                nonce: artDetailData.nonce,
                request_id: artDetailData.requestId,
                note_content: content
            }, function(response) {
                addBtn.prop('disabled', false);
                addBtn.text(originalText);
                
                if (response.success) {
                    // Clear input
                    input.html('');
                    updateCharCount();
                    
                    // Refresh notes (will auto-scroll to bottom)
                    artRefreshNotes(artDetailData.requestId, true);
                } else {
                    alert('<?php _e('Error: ', 'amelia-cpt-sync'); ?>' + (response.data.message || 'Unknown error'));
                }
            });
        });
        
        // Allow Ctrl+Enter to submit
        input.on('keydown', function(e) {
            if (e.ctrlKey && e.key === 'Enter') {
                e.preventDefault();
                addBtn.click();
            }
        });
    }
    
    /**
     * Render notes in the list
     */
    function renderNotes(notes, prepend) {
        var container = $('#art-notes-list');
        
        if (!prepend) {
            container.empty();
            artNotesState.allNotes = notes;
        } else {
            // Prepend older notes to the top
            artNotesState.allNotes = notes.concat(artNotesState.allNotes);
        }
        
        if (artNotesState.allNotes.length === 0) {
            container.html('<div class="notes-loading-initial" style="color: #94A3B8;"><?php _e('No activity yet', 'amelia-cpt-sync'); ?></div>');
            return;
        }
        
        var html = '';
        
        $.each(artNotesState.allNotes, function(i, note) {
            html += buildNoteHTML(note);
        });
        
        container.html(html);
        
        // Auto-scroll to bottom if new notes added (not infinite scroll)
        if (!prepend) {
            scrollNotesToBottom();
        }
    }
    
    /**
     * Build HTML for a single note
     */
    function buildNoteHTML(note) {
        var iconHTML = '';
        var typeClass = 'note-icon-' + note.note_type;
        
        if (note.note_type === 'manual') {
            var initials = note.author_initials || 'U';
            iconHTML = '<span class="user-initials">' + initials + '</span>';
        } else if (note.note_type === 'system') {
            iconHTML = '<span class="dashicons dashicons-info"></span>';
        } else {
            iconHTML = '<span class="dashicons dashicons-calendar-alt"></span>';
        }
        
        var canEdit = note.note_type === 'manual' && note.created_by == artDetailData.currentUserId;
        
        var html = '<div class="art-note-item" data-note-id="' + note.id + '" data-note-type="' + note.note_type + '">';
        html += '<div class="note-icon ' + typeClass + '">' + iconHTML + '</div>';
        html += '<div class="note-content-wrapper">';
        html += '<div class="note-meta">';
        html += '<span class="note-author">' + (note.author_name || '<?php _e('System', 'amelia-cpt-sync'); ?>') + '</span>';
        html += '<span class="note-timestamp">' + note.time_ago + '</span>';
        if (note.updated_at) {
            html += '<span class="note-edited"><?php _e('(edited)', 'amelia-cpt-sync'); ?></span>';
        }
        html += '</div>';
        html += '<div class="note-content">' + note.note_content + '</div>';
        
        if (canEdit) {
            html += '<div class="note-actions-inline">';
            html += '<button class="note-action-btn" data-action="edit"><?php _e('Edit', 'amelia-cpt-sync'); ?></button>';
            html += '<button class="note-action-btn" data-action="delete"><?php _e('Delete', 'amelia-cpt-sync'); ?></button>';
            html += '</div>';
        }
        
        html += '</div></div>';
        
        return html;
    }
    
    /**
     * Scroll notes container to bottom
     */
    function scrollNotesToBottom() {
        var container = $('#art-notes-list');
        if (container.length && container[0].scrollHeight) {
            container.scrollTop(container[0].scrollHeight);
        }
    }
    
    /**
     * Global refresh function - can be called from anywhere
     */
    window.artRefreshNotes = function(requestId, scrollToBottom) {
        $.post(ajaxurl, {
            action: 'art_get_notes',
            nonce: artDetailData.nonce,
            request_id: requestId,
            offset: 0,
            limit: artNotesState.limit
        }, function(response) {
            if (response.success) {
                artNotesState.offset = response.data.notes.length;
                artNotesState.hasMore = response.data.has_more;
                
                renderNotes(response.data.notes, false);
                
                if (scrollToBottom) {
                    setTimeout(scrollNotesToBottom, 100);
                }
            }
        });
    };
    
    /**
     * Initialize infinite scroll (load older notes on scroll up)
     */
    function initInfiniteScroll() {
        var container = $('#art-notes-list');
        
        if (!container.length) return;
        
        container.on('scroll', function() {
            // Detect scroll to TOP (load older notes)
            if (container.scrollTop() <= 50 && !artNotesState.loading && artNotesState.hasMore) {
                loadOlderNotes();
            }
        });
    }
    
    /**
     * Load older notes (prepend to top)
     */
    function loadOlderNotes() {
        artNotesState.loading = true;
        
        // Save current scroll position
        var container = $('#art-notes-list');
        var oldScrollHeight = container[0].scrollHeight;
        
        // Show loading indicator
        container.prepend('<div class="notes-loading-more"><span class="dashicons dashicons-update spin"></span> <?php _e('Loading older notes...', 'amelia-cpt-sync'); ?></div>');
        
        $.post(ajaxurl, {
            action: 'art_get_notes',
            nonce: artDetailData.nonce,
            request_id: artDetailData.requestId,
            offset: artNotesState.offset,
            limit: artNotesState.limit
        }, function(response) {
            $('.notes-loading-more').remove();
            artNotesState.loading = false;
            
            if (response.success && response.data.notes.length > 0) {
                artNotesState.offset += response.data.notes.length;
                artNotesState.hasMore = response.data.has_more;
                
                renderNotes(response.data.notes, true); // Prepend
                
                // Restore scroll position (prevent jump)
                var newScrollHeight = container[0].scrollHeight;
                container.scrollTop(newScrollHeight - oldScrollHeight);
            } else {
                artNotesState.hasMore = false;
                if (artNotesState.allNotes.length > 0) {
                    container.prepend('<div class="notes-end"><?php _e('No more notes', 'amelia-cpt-sync'); ?></div>');
                }
            }
        });
    }
    
    /**
     * Initialize note action handlers (edit/delete)
     */
    function initNoteActions() {
        $(document).on('click', '.note-action-btn', function() {
            var action = $(this).data('action');
            var noteItem = $(this).closest('.art-note-item');
            var noteId = noteItem.data('note-id');
            
            if (action === 'edit') {
                enterEditMode(noteItem);
            } else if (action === 'delete') {
                if (confirm('<?php _e('Delete this note? This cannot be undone.', 'amelia-cpt-sync'); ?>')) {
                    deleteNote(noteId);
                }
            }
        });
    }
    
    /**
     * Enter edit mode for a note
     */
    function enterEditMode(noteItem) {
        var noteId = noteItem.data('note-id');
        var contentDiv = noteItem.find('.note-content');
        var currentContent = contentDiv.html();
        
        // Replace with editable version
        var editHTML = '<div class="note-edit-wrapper">';
        editHTML += '<div class="note-input" contenteditable="true">' + currentContent + '</div>';
        editHTML += '<div class="note-edit-actions">';
        editHTML += '<button class="note-save-btn" data-note-id="' + noteId + '"><?php _e('Save', 'amelia-cpt-sync'); ?></button>';
        editHTML += '<button class="note-cancel-btn"><?php _e('Cancel', 'amelia-cpt-sync'); ?></button>';
        editHTML += '</div>';
        editHTML += '</div>';
        
        contentDiv.replaceWith(editHTML);
        noteItem.find('.note-actions-inline').hide();
        
        // Focus the editable div
        noteItem.find('.note-input').focus();
    }
    
    /**
     * Save edited note
     */
    $(document).on('click', '.note-save-btn', function() {
        var noteId = $(this).data('note-id');
        var newContent = $(this).closest('.note-edit-wrapper').find('.note-input').html();
        
        var btn = $(this);
        btn.prop('disabled', true).text('<?php _e('Saving...', 'amelia-cpt-sync'); ?>');
        
        $.post(ajaxurl, {
            action: 'art_update_note',
            nonce: artDetailData.nonce,
            note_id: noteId,
            note_content: newContent
        }, function(response) {
            if (response.success) {
                artRefreshNotes(artDetailData.requestId, false);
            } else {
                alert('<?php _e('Error: ', 'amelia-cpt-sync'); ?>' + (response.data.message || 'Unknown error'));
                btn.prop('disabled', false).text('<?php _e('Save', 'amelia-cpt-sync'); ?>');
            }
        });
    });
    
    /**
     * Cancel note edit
     */
    $(document).on('click', '.note-cancel-btn', function() {
        artRefreshNotes(artDetailData.requestId, false);
    });
    
    /**
     * Delete note
     */
    function deleteNote(noteId) {
        $.post(ajaxurl, {
            action: 'art_delete_note',
            nonce: artDetailData.nonce,
            note_id: noteId
        }, function(response) {
            if (response.success) {
                artRefreshNotes(artDetailData.requestId, false);
            } else {
                alert('<?php _e('Error: ', 'amelia-cpt-sync'); ?>' + (response.data.message || 'Unknown error'));
            }
        });
    }
    
    /**
     * Initialize notes system on page load
     */
    if ($('#art-notes-list').length) {
        initNoteComposer();
        initInfiniteScroll();
        initNoteActions();
        
        // Load initial notes
        artRefreshNotes(artDetailData.requestId, true);
    }
    
    // ========================================
    // RESOURCE SYSTEM (Phase 1)
    // ========================================
    
    var resourceState = {
        mode: 'none',
        config: null,
        selectedResources: [],
        selectedResourceId: null,  // For click-to-select in shared pool manual mode
        currentStatus: null
    };
    
    /**
     * Load resource config when service changes (v2.34.0 - mode-aware reset)
     */
    $('#pillar-service').on('change', function() {
        var serviceId = $(this).val();
        
        // IMMEDIATELY reset resource state to prevent stale data
        resourceState.mode = 'none';
        resourceState.config = null;
        resourceState.selectedResourceId = null;
        resourceState.selectedResources = [];
        resourceState.currentStatus = null;
        
        // Clear resource UI
        $('#resource-status-list').html(
            '<div class="resource-placeholder">' +
                '<span class="dashicons dashicons-info" style="color: #94A3B8;"></span> ' +
                '<?php _e('Check availability to see resources', 'amelia-cpt-sync'); ?>' +
            '</div>'
        );
        
        // Clear selected resource hidden input
        $('#selected-resource-id').val('');
        
        if (!serviceId) {
            updatePickerModeClass('none');
            return;
        }
        
        $.post(ajaxurl, {
            action: 'art_get_service_resource_config',
            nonce: artDetailData.nonce,
            service_id: serviceId
        }, function(response) {
            if (response.success && response.data.config) {
                resourceState.config = response.data.config;
                resourceState.mode = response.data.config.resource_mode || 'none';
            } else {
                resourceState.mode = 'none';
            }
            
            updatePickerModeClass(resourceState.mode);
        });
    });
    
    /**
     * Update picker container mode class for grid adjustments
     */
    function updatePickerModeClass(mode) {
        $('.art-picker-container')
            .removeClass('mode-none mode-mirrored mode-shared-pool mode-quantity-pool mode-provider-bound mode-location-bound mode-composite mode-hybrid')
            .addClass('mode-' + mode);
    }
    
    /**
     * Jump to a specific time (from next available suggestion)
     */
    function jumpToTime(timeStr) {
        console.log('Jump to time:', timeStr);
        
        // Parse time (format: "HH:MM" or "H:MM AM/PM")
        var time24 = timeStr;
        if (timeStr.includes('AM') || timeStr.includes('PM')) {
            time24 = convertTo24Hour(timeStr);
        }
        
        if (time24) {
            $('#custom-time-input').val(time24).trigger('change');
            // This will trigger updateProviderList which calls checkProviderAvailabilityEngine
        }
    }
    
    /**
     * Render resource section based on mode
     */
    function renderResourceSection() {
        if (resourceState.mode === 'none') {
            $('#resource-section').hide();
            return;
        }
        
        $('#resource-section').show();
        $('#resource-mode-badge').text(getModeBadgeText(resourceState.mode));
        
        switch (resourceState.mode) {
            case 'mirrored':
                renderModeMirrored();
                break;
            default:
                $('#resource-content').html('<p style="color: #94A3B8; font-style: italic;">Mode not implemented yet</p>');
        }
    }
    
    /**
     * Render Mode 1: Mirrored resource (info card)
     */
    function renderModeMirrored() {
        var settings = resourceState.config.mode_settings || {};
        var resourceId = settings.mirrored_resource_id;
        
        if (!resourceId) {
            $('#resource-content').html(
                '<div class="resource-alert warning">' +
                '<span class="dashicons dashicons-warning"></span> ' +
                '<span>Resource not configured for this service</span>' +
                '</div>'
            );
            return;
        }
        
        // Fetch resource details
        $.post(ajaxurl, {
            action: 'art_get_all_resources',
            nonce: artDetailData.nonce
        }, function(response) {
            if (response.success) {
                var resource = response.data.resources.find(function(r) {
                    return parseInt(r.id) === parseInt(resourceId);
                });
                
                if (resource) {
                    var html = '<div class="resource-info-card">' +
                               '<div class="resource-icon">🔧</div>' +
                               '<div class="resource-details">' +
                               '<div class="resource-name">' + resource.name + '</div>' +
                               '<div class="resource-meta">Auto-assigned with this service</div>' +
                               '</div>' +
                               '<div class="resource-status-badge pending">Will check on date/time</div>' +
                               '</div>';
                    
                    $('#resource-content').html(html);
                } else {
                    $('#resource-content').html(
                        '<div class="resource-alert error">' +
                        '<span class="dashicons dashicons-no"></span> ' +
                        '<span>Resource #' + resourceId + ' not found</span>' +
                        '</div>'
                    );
                }
            }
        });
    }
    
    /**
     * Get mode badge text
     */
    function getModeBadgeText(mode) {
        var badges = {
            'none': '',
            'mirrored': 'Dedicated Resource',
            'shared_pool': 'Resource Pool',
            'composite': 'Multi Resource'
        };
        return badges[mode] || mode;
    }
    
    /**
     * Get selected resources (helper for booking)
     */
    function getSelectedResources() {
        if (resourceState.mode === 'none') {
            return [];
        }
        
        if (resourceState.mode === 'mirrored') {
            var settings = resourceState.config?.mode_settings || {};
            return settings.mirrored_resource_id ? [settings.mirrored_resource_id] : [];
        }
        
        if (resourceState.mode === 'shared_pool') {
            // Priority 1: User clicked a resource card
            if (resourceState.selectedResourceId) {
                return [parseInt(resourceState.selectedResourceId)];
            }
            
            // Priority 2: Manual dropdown selection (legacy fallback)
            var manualSelect = $('#manual-pool-resource-select');
            if (manualSelect.length && manualSelect.val()) {
                return [parseInt(manualSelect.val())];
            }
            
            // Otherwise, orchestrator will auto-select based on strategy
            return [];
        }
        
        if (resourceState.mode === 'composite') {
            // Priority 1: User set quantities via qty inputs
            if (resourceState.selectedResources && resourceState.selectedResources.length > 0) {
                // selectedResources is [{resource_id, quantity}, ...] from qty handler
                // Return flat array of IDs for backward compat, but attach quantities
                var result = [];
                resourceState.selectedResources.forEach(function(sel) {
                    if (typeof sel === 'object' && sel.resource_id) {
                        result.push(sel);
                    } else {
                        // Legacy flat ID format
                        result.push({ resource_id: parseInt(sel), quantity: 1 });
                    }
                });
                return result;
            }
            
            // Priority 2: Orchestrator auto-selections (fallback)
            var selected = [];
            if (typeof lastOrchestratorResult !== 'undefined' && lastOrchestratorResult && 
                lastOrchestratorResult.resources && lastOrchestratorResult.resources.groups) {
                lastOrchestratorResult.resources.groups.forEach(function(group) {
                    if (group.selected && group.selected.id) {
                        selected.push({ resource_id: parseInt(group.selected.id), quantity: group.quantity_needed || 1 });
                    }
                });
            }
            return selected;
        }
        
        return resourceState.selectedResources;
    }
    
    /**
     * Get resource summary for booking display
     */
    function getResourceSummary() {
        // Safety check: resourceState might not be initialized yet
        if (typeof resourceState === 'undefined' || !resourceState || resourceState.mode === 'none') {
            return null;
        }
        
        if (resourceState.mode === 'mirrored') {
            var resourceName = $('#resource-content .resource-name').text();
            return resourceName || 'Resource assigned';
        }
        
        return null;
    }
});
</script>

<style>
/* ========================================
   RESOURCE SYSTEM CSS
   ======================================== */

#resource-section {
    margin: 16px 0;
    padding: 16px;
    background: #F8FAFC;
    border: 1px solid #E0E5F1;
    border-radius: 6px;
}

.mode-badge {
    display: inline-block;
    padding: 2px 8px;
    background: #EFF6FF;
    color: #1A84EE;
    font-size: 11px;
    font-weight: 600;
    border-radius: 10px;
    margin-left: 8px;
    text-transform: uppercase;
}

.resource-content {
    margin-top: 12px;
}

.resource-info-card {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: #fff;
    border: 1px solid #E0E5F1;
    border-radius: 6px;
}

.resource-icon {
    font-size: 24px;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #EFF6FF;
    border-radius: 50%;
}

.resource-details {
    flex: 1;
}

.resource-name {
    font-size: 14px;
    font-weight: 600;
    color: #1E293B;
}

.resource-meta {
    font-size: 12px;
    color: #64748B;
    margin-top: 2px;
}

.resource-status-badge {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.resource-status-badge.available {
    background: #D1FAE5;
    color: #065F46;
}

.resource-status-badge.unavailable {
    background: #FEE2E2;
    color: #991B1B;
}

.resource-status-badge.warning {
    background: #FEF3C7;
    color: #92400E;
}

.resource-status-badge.pending {
    background: #E0E7FF;
    color: #3730A3;
}

.resource-alert {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px;
    border-radius: 6px;
    font-size: 13px;
}

.resource-alert .dashicons {
    font-size: 18px;
    width: 18px;
    height: 18px;
}

.resource-alert.error {
    background: #FEE2E2;
    color: #991B1B;
}

.resource-alert.warning {
    background: #FEF3C7;
    color: #92400E;
}

.resource-alert.info {
    background: #EFF6FF;
    color: #1E40AF;
}

.resource-status-message {
    margin-top: 8px;
    font-size: 12px;
    color: #64748B;
}

/* Resource Block Alert */
.resource-block-alert {
    margin: 16px 0;
    padding: 16px;
    background: #FEF3C7;
    border: 2px solid #F59E0B;
    border-radius: 8px;
}

.resource-block-alert h4 {
    margin: 0 0 8px 0;
    color: #92400E;
    font-size: 14px;
}

.resource-block-alert p {
    margin: 0 0 12px 0;
    color: #78350F;
    font-size: 13px;
}

.resource-block-actions {
    display: flex;
    gap: 10px;
}

.resource-block-actions button {
    padding: 6px 14px;
    border-radius: 4px;
    font-size: 12px;
}
</style>




