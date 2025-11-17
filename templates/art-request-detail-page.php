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
$art_settings = get_option('amelia_cpt_sync_art_settings', array());
$global_settings = $art_settings['global'] ?? array();
$show_location = $global_settings['show_location_field'] ?? true;
$show_persons = $global_settings['show_persons_field'] ?? true;

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
                    
                    <!-- Card 2: Time & Duration -->
                    <div class="art-card">
                        <div class="card-header">
                            <h3><?php _e('Time & Duration', 'amelia-cpt-sync'); ?></h3>
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
                                
                                <!-- End Time -->
                                <div class="form-field">
                                    <label for="pillar-end">
                                        <?php _e('End Time', 'amelia-cpt-sync'); ?>
                                    </label>
                                    <input type="datetime-local" 
                                           id="pillar-end" 
                                           name="end_datetime" 
                                           class="form-input"
                                           value="<?php echo esc_attr($end_datetime_value); ?>">
                                </div>
                                
                                <!-- Duration (Auto-calculated, read-only) -->
                                <div class="form-field">
                                    <label for="pillar-duration-display">
                                        <?php _e('Duration', 'amelia-cpt-sync'); ?>
                                    </label>
                                    <input type="text" 
                                           id="pillar-duration-display" 
                                           class="form-input" 
                                           value="<?php echo esc_attr($duration_display); ?>" 
                                           disabled>
                                    <input type="hidden" 
                                           id="pillar-duration-seconds" 
                                           name="duration_seconds" 
                                           value="<?php echo esc_attr($request->duration_seconds); ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Card 3: Quote -->
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
                
                <!-- Panel 3: Availability Engine (Placeholder) -->
                <div class="art-card card-disabled">
                    <div class="card-header">
                        <h3><?php _e('Availability Engine', 'amelia-cpt-sync'); ?></h3>
                        <span class="badge-coming-soon"><?php _e('Coming in Phase 5', 'amelia-cpt-sync'); ?></span>
                    </div>
                    <div class="card-body">
                        <p class="placeholder-text">
                            <?php _e('Availability checking and booking will be enabled in Phase 5.', 'amelia-cpt-sync'); ?>
                        </p>
                        
                        <button class="btn-secondary" disabled>
                            <span class="dashicons dashicons-calendar-alt"></span>
                            <?php _e('Check Availability', 'amelia-cpt-sync'); ?>
                        </button>
                        
                        <div class="placeholder-controls" style="opacity: 0.5; margin-top: 20px;">
                            <div class="pillar-grid-2">
                                <div class="form-field">
                                    <label><?php _e('Provider', 'amelia-cpt-sync'); ?></label>
                                    <select class="form-select" disabled>
                                        <option><?php _e('(Available after checking availability)', 'amelia-cpt-sync'); ?></option>
                                    </select>
                                </div>
                                <div class="form-field">
                                    <label><?php _e('Time Slot', 'amelia-cpt-sync'); ?></label>
                                    <select class="form-select" disabled>
                                        <option><?php _e('(Available after checking availability)', 'amelia-cpt-sync'); ?></option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="placeholder-actions">
                                <button class="btn-secondary" disabled><?php _e('Tentatively Book', 'amelia-cpt-sync'); ?></button>
                                <button class="btn-primary" disabled><?php _e('Book Now', 'amelia-cpt-sync'); ?></button>
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
                                    <dd><?php echo esc_html($request->customer_phone); ?></dd>
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
                
                <!-- Intake Details Card -->
                <?php if (!empty($request->intake_fields)): ?>
                    <div class="art-card">
                        <div class="card-header">
                            <h3><?php _e('Intake Details', 'amelia-cpt-sync'); ?></h3>
                        </div>
                        <div class="card-body">
                            <dl class="info-list">
                                <?php foreach ($request->intake_fields as $field): ?>
                                    <div class="info-row">
                                        <dt><?php echo esc_html($field->field_label); ?></dt>
                                        <dd><?php echo wp_kses_post(nl2br($field->field_value)); ?></dd>
                                    </div>
                                <?php endforeach; ?>
                            </dl>
                        </div>
                    </div>
                <?php endif; ?>
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
        currentLocation: <?php echo wp_json_encode($request->location_id); ?>
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
    });
    
    // === DURATION AUTO-CALCULATE ===
    function calculateDuration() {
        var start = $('#pillar-start').val();
        var end = $('#pillar-end').val();
        
        if (start && end) {
            var startDate = new Date(start);
            var endDate = new Date(end);
            var diffMs = endDate - startDate;
            
            if (diffMs > 0) {
                var diffSeconds = Math.floor(diffMs / 1000);
                var diffMinutes = Math.floor(diffSeconds / 60);
                var hours = Math.floor(diffMinutes / 60);
                var remainingMins = diffMinutes % 60;
                
                var displayText = '';
                if (hours > 0) {
                    displayText = hours + 'h ' + remainingMins + 'm';
                } else {
                    displayText = diffMinutes + ' minutes';
                }
                
                $('#pillar-duration-display').val(displayText);
                $('#pillar-duration-seconds').val(diffSeconds);
            } else {
                $('#pillar-duration-display').val('Invalid range');
                $('#pillar-duration-seconds').val(0);
            }
        }
    }
    
    $('#pillar-start, #pillar-end').on('change', calculateDuration);
    
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
    
    // === SPINNING ANIMATION FOR DASHICONS ===
    $('<style>.dashicons.spin { animation: spin 1s linear infinite; } @keyframes spin { 100% { transform: rotate(360deg); } }</style>').appendTo('head');
});
</script>



