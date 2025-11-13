<?php
/**
 * ART Workbench (Triage Requests List) Template
 *
 * Displays all triage requests with filtering, search, and pagination
 * Design based on ui-mockup-list-view.html (Tailwind aesthetic)
 *
 * @package AmeliaCPTSync
 * @subpackage ART
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Initialize request manager
$request_manager = new Amelia_CPT_Sync_ART_Request_Manager();

// Get query parameters
$current_status = isset($_GET['status_filter']) ? sanitize_text_field($_GET['status_filter']) : '';
$search_term = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
$current_page = isset($_GET['paged']) ? absint($_GET['paged']) : 1;

// Get user's per-page preference (stored in user meta)
$user_id = get_current_user_id();
$per_page = get_user_meta($user_id, 'art_workbench_per_page', true);
if (empty($per_page) || !in_array($per_page, array(5, 15, 25, 50, 100))) {
    $per_page = 25;  // Default to 25
}

// Get requests with filters
$results = $request_manager->get_requests(array(
    'status' => $current_status,
    'search' => $search_term,
    'paged' => $current_page,
    'per_page' => $per_page,
    'orderby' => 'created_at',
    'order' => 'DESC'
));

// Get status counts for filter chips
$status_counts = $request_manager->get_status_counts();

// Status badge colors (matching mockup)
$status_colors = array(
    'Requested' => 'blue',
    'Responded' => 'yellow',
    'Tentative' => 'purple',
    'Booked' => 'green',
    'Abandoned' => 'red'
);
?>

<div class="wrap art-workbench">
    <!-- Page Heading -->
    <div class="art-page-header">
        <h1 class="art-page-title">
            <?php _e('Triage Requests', 'amelia-cpt-sync'); ?>
        </h1>
        <?php if ($results['total'] > 0): ?>
            <span class="art-subtitle">
                <?php echo number_format($results['total']); ?> total
            </span>
        <?php endif; ?>
    </div>
    
    <!-- Filter Section -->
    <div class="art-filters-wrapper">
        <!-- Status Filter Chips -->
        <div class="art-status-chips">
            <a href="<?php echo esc_url(remove_query_arg(array('status_filter', 'paged'))); ?>" 
               class="art-chip <?php echo empty($current_status) ? 'active' : ''; ?>">
                <span class="chip-count"><?php echo $status_counts['all']; ?></span>
                All
            </a>
            
            <?php foreach (array('Requested', 'Responded', 'Tentative', 'Booked', 'Abandoned') as $status): ?>
                <a href="<?php echo esc_url(add_query_arg(array('status_filter' => $status, 'paged' => 1))); ?>" 
                   class="art-chip <?php echo $current_status === $status ? 'active' : ''; ?> chip-<?php echo strtolower($status); ?>">
                    <span class="chip-count"><?php echo $status_counts[$status]; ?></span>
                    <?php echo esc_html($status); ?>
                </a>
            <?php endforeach; ?>
        </div>
        
        <!-- Toolbar: Search + Per Page -->
        <div class="art-toolbar">
            <!-- Per Page Selector -->
            <div class="art-per-page">
                <label for="art-per-page-select" class="per-page-label">
                    <?php _e('Show:', 'amelia-cpt-sync'); ?>
                </label>
                <select id="art-per-page-select" class="art-per-page-select">
                    <?php foreach (array(5, 15, 25, 50, 100) as $option): ?>
                        <option value="<?php echo $option; ?>" <?php selected($per_page, $option); ?>>
                            <?php echo $option; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="per-page-suffix"><?php _e('per page', 'amelia-cpt-sync'); ?></span>
            </div>
            
            <!-- Search Bar -->
            <div class="art-search-container">
                <form method="get" action="" class="art-search-form">
                    <input type="hidden" name="page" value="art-workbench">
                    <?php if (!empty($current_status)): ?>
                        <input type="hidden" name="status_filter" value="<?php echo esc_attr($current_status); ?>">
                    <?php endif; ?>
                    
                    <div class="art-search-input-wrap">
                        <span class="dashicons dashicons-search art-search-icon"></span>
                        <input type="search" 
                               name="s" 
                               value="<?php echo esc_attr($search_term); ?>" 
                               placeholder="Search requests..." 
                               class="art-search-input">
                        <?php if (!empty($search_term)): ?>
                            <a href="<?php echo esc_url(remove_query_arg(array('s', 'paged'))); ?>" 
                               class="art-clear-search" 
                               title="Clear search">
                                <span class="dashicons dashicons-no-alt"></span>
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Results Table -->
    <?php if (empty($results['items'])): ?>
        <div class="art-empty-state">
            <p class="empty-title">
                <?php if (!empty($search_term) || !empty($current_status)): ?>
                    <?php _e('No requests found matching your filters.', 'amelia-cpt-sync'); ?>
                <?php else: ?>
                    <?php _e('No triage requests yet.', 'amelia-cpt-sync'); ?>
                <?php endif; ?>
            </p>
            <p class="empty-subtitle">
                <?php _e('Requests will appear here as customers submit triage forms.', 'amelia-cpt-sync'); ?>
            </p>
        </div>
    <?php else: ?>
        <div class="art-table-container">
            <table class="art-table">
                <thead>
                    <tr>
                        <th class="col-status"><?php _e('Status', 'amelia-cpt-sync'); ?></th>
                        <th class="col-customer"><?php _e('Customer', 'amelia-cpt-sync'); ?></th>
                        <th class="col-service"><?php _e('Service', 'amelia-cpt-sync'); ?></th>
                        <th class="col-requested-start"><?php _e('Requested Start', 'amelia-cpt-sync'); ?></th>
                        <th class="col-submitted"><?php _e('Submitted', 'amelia-cpt-sync'); ?></th>
                        <th class="col-actions"><?php _e('Actions', 'amelia-cpt-sync'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php 
                $row_index = 0;
                foreach ($results['items'] as $request): 
                    // Map status_key to display name
                    $status_display = ucfirst($request->status_key ?? 'requested');
                    $status_color = $status_colors[$status_display] ?? 'gray';
                    
                    $customer_name = trim($request->customer_first_name . ' ' . $request->customer_last_name);
                    if (empty($customer_name) || $customer_name === ' ') {
                        $customer_name = 'Unknown';
                    }
                    
                    // Alternate row background (like mockup)
                    $row_class = ($row_index % 2 === 0) ? '' : 'alt-row';
                    $row_index++;
                    
                    // Format dates
                    $submitted_date = get_date_from_gmt($request->created_at);
                    $submitted_display = date_i18n('M j, Y', strtotime($submitted_date));
                    
                    $start_display = '—';
                    if (!empty($request->start_datetime)) {
                        $start_date = get_date_from_gmt($request->start_datetime);
                        $start_display = date_i18n('M j, Y, g:i A', strtotime($start_date));
                    }
                    
                    $detail_url = add_query_arg(
                        array('page' => 'art-request-detail', 'request_id' => $request->id),
                        admin_url('admin.php')
                    );
                ?>
                    <tr class="art-row <?php echo esc_attr($row_class); ?>" data-request-id="<?php echo esc_attr($request->id); ?>">
                        <td class="col-status">
                            <span class="status-badge badge-<?php echo esc_attr($status_color); ?>">
                                <?php echo esc_html($status_display); ?>
                            </span>
                        </td>
                        <td class="col-customer">
                            <div class="customer-info">
                                <a href="<?php echo esc_url($detail_url); ?>" class="customer-name">
                                    <?php echo esc_html($customer_name); ?>
                                </a>
                                <?php if (!empty($request->customer_email)): ?>
                                    <p class="customer-email">
                                        <?php echo esc_html($request->customer_email); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="col-service">
                            <?php if (!empty($request->service_name)): ?>
                                <span class="service-name"><?php echo esc_html($request->service_name); ?></span>
                            <?php elseif (!empty($request->service_id)): ?>
                                <span class="service-id">Service #<?php echo esc_html($request->service_id); ?></span>
                            <?php else: ?>
                                <span class="no-data">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="col-requested-start">
                            <?php echo esc_html($start_display); ?>
                        </td>
                        <td class="col-submitted">
                            <?php echo esc_html($submitted_display); ?>
                        </td>
                        <td class="col-actions">
                            <a href="<?php echo esc_url($detail_url); ?>" class="art-btn-view">
                                <?php _e('View', 'amelia-cpt-sync'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($results['total_pages'] > 1): ?>
            <div class="art-pagination">
                <?php
                $page_links = paginate_links(array(
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => '<span class="dashicons dashicons-arrow-left-alt2"></span>',
                    'next_text' => '<span class="dashicons dashicons-arrow-right-alt2"></span>',
                    'total' => $results['total_pages'],
                    'current' => $current_page,
                    'type' => 'list',
                    'mid_size' => 2,
                    'end_size' => 1
                ));
                
                if ($page_links) {
                    echo $page_links;
                }
                ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Styles (Tailwind-inspired, matching ui-mockup-list-view.html) -->
<style>
/* ============================================================================
   ART WORKBENCH STYLES
   Design inspired by ui-mockup-list-view.html (Tailwind aesthetic)
   Modern, clean, rounded corners, better spacing
   ============================================================================ */

.art-workbench {
    background: #F7F8FC;
    padding: 32px 24px;
    margin: -10px -20px 0 -22px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    min-height: calc(100vh - 32px);
}

/* === PAGE HEADER === */
.art-page-header {
    display: flex;
    align-items: baseline;
    gap: 12px;
    margin-bottom: 24px;
}

.art-page-title {
    font-size: 30px;
    font-weight: 900;
    letter-spacing: -0.025em;
    color: #1E293B;
    margin: 0;
}

.art-subtitle {
    font-size: 16px;
    color: #64748b;
    font-weight: 500;
}

/* === FILTER SECTION === */
.art-filters-wrapper {
    margin-bottom: 24px;
}

.art-status-chips {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 16px;
}

.art-chip {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    height: 36px;
    padding: 0 16px;
    background: #fff;
    border: 1px solid #E0E5F1;
    border-radius: 9999px;
    text-decoration: none;
    color: #1E293B;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
}

.art-chip:hover {
    background: #F1F5F9;
    text-decoration: none;
    color: #1E293B;
    border-color: #CBD5E1;
}

.art-chip.active {
    background: #1A84EE;
    border-color: #1A84EE;
    color: #fff;
}

.chip-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 20px;
    font-weight: 600;
    font-size: 13px;
}

/* === SEARCH BAR === */
.art-search-container {
    max-width: 512px;
}

.art-search-form {
    width: 100%;
}

.art-search-input-wrap {
    position: relative;
    display: flex;
    align-items: center;
}

.art-search-icon {
    position: absolute;
    left: 12px;
    color: #94a3b8;
    font-size: 18px;
    width: 20px;
    height: 20px;
}

.art-search-input {
    width: 100%;
    height: 40px;
    padding: 0 40px 0 40px;
    background: #fff;
    border: 1px solid #E0E5F1;
    border-radius: 8px;
    font-size: 14px;
    color: #1E293B;
    transition: all 0.2s;
}

.art-search-input:focus {
    outline: none;
    border-color: #1A84EE;
    box-shadow: 0 0 0 3px rgba(26, 132, 238, 0.1);
}

.art-search-input::placeholder {
    color: #94a3b8;
}

.art-clear-search {
    position: absolute;
    right: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    text-decoration: none;
    color: #94a3b8;
    transition: color 0.2s;
    border-radius: 50%;
}

.art-clear-search:hover {
    color: #1E293B;
    background: #F1F5F9;
}

.art-clear-search .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
}

/* === TABLE === */
.art-table-container {
    overflow: hidden;
    border-radius: 12px;
    border: 1px solid #E0E5F1;
    background: #fff;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.art-table {
    width: 100%;
    border-collapse: collapse;
    border-spacing: 0;
}

.art-table thead {
    background: #F8FAFC;
    border-bottom: 1px solid #E0E5F1;
}

.art-table thead tr th {
    padding: 12px 16px;
    text-align: left;
    font-size: 13px;
    font-weight: 600;
    color: #1E293B;
}

.art-table tbody tr {
    border-top: 1px solid #E0E5F1;
    transition: background-color 0.15s;
}

.art-table tbody tr:first-child {
    border-top: none;
}

.art-table tbody tr:hover {
    background: #F8FAFC !important;
}

.art-table tbody tr.alt-row {
    background: rgba(248, 250, 252, 0.5);
}

.art-table tbody td {
    padding: 18px 16px;
    font-size: 14px;
    color: #475569;
    vertical-align: middle;
    height: 72px;
}

/* === TABLE COLUMNS === */
.col-status {
    width: 160px;
}

.col-customer {
    min-width: 200px;
}

.col-service {
    width: 180px;
}

.col-requested-start {
    width: 200px;
}

.col-submitted {
    width: 140px;
}

.col-actions {
    width: 100px;
    text-align: center;
}

/* === STATUS BADGES (matching mockup colors) === */
.status-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 28px;
    padding: 0 12px;
    border-radius: 9999px;
    font-size: 12px;
    font-weight: 600;
    text-transform: capitalize;
    letter-spacing: 0.01em;
    white-space: nowrap;
    width: fit-content;
}

.badge-green {
    background: #DCFCE7;
    color: #16A34A;
}

.badge-blue {
    background: #DBEAFE;
    color: #2563EB;
}

.badge-yellow {
    background: #FEF3C7;
    color: #D97706;
}

.badge-purple {
    background: #EDE9FE;
    color: #9333EA;
}

.badge-red {
    background: #FEE2E2;
    color: #DC2626;
}

/* === CUSTOMER INFO === */
.customer-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.customer-name {
    font-weight: 600;
    color: #1E293B;
    text-decoration: underline;
    text-decoration-color: transparent;
    transition: all 0.2s;
}

.customer-name:hover {
    color: #1A84EE;
    text-decoration-color: #1A84EE;
}

.customer-email {
    font-size: 13px;
    color: #64748b;
    margin: 0;
}

/* === SERVICE & DATA === */
.service-name {
    color: #1E293B;
    font-size: 14px;
    font-weight: 500;
}

.service-id {
    color: #64748b;
    font-size: 13px;
    font-family: 'Courier New', monospace;
}

.no-data {
    color: #94a3b8;
}

/* === VIEW BUTTON === */
.art-btn-view {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 32px;
    padding: 0 16px;
    background: #fff;
    border: 1px solid #E0E5F1;
    border-radius: 6px;
    color: #1E293B;
    font-size: 13px;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.2s;
    cursor: pointer;
}

.art-btn-view:hover {
    background: #F1F5F9;
    border-color: #1A84EE;
    color: #1A84EE;
    text-decoration: none;
}

/* === EMPTY STATE === */
.art-empty-state {
    text-align: center;
    padding: 80px 20px;
    background: #fff;
    border: 1px solid #E0E5F1;
    border-radius: 12px;
}

.empty-title {
    font-size: 18px;
    font-weight: 600;
    color: #64748b;
    margin: 0 0 8px;
}

.empty-subtitle {
    font-size: 14px;
    color: #94a3b8;
    margin: 0;
}

/* === PAGINATION === */
.art-pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    margin-top: 24px;
    gap: 8px;
}

.art-pagination .page-numbers {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 36px;
    height: 36px;
    padding: 0 8px;
    background: #fff;
    border: 1px solid #E0E5F1;
    border-radius: 9999px;
    color: #475569;
    font-size: 13px;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.2s;
}

.art-pagination .page-numbers:hover {
    background: #F1F5F9;
    color: #1E293B;
    text-decoration: none;
    border-color: #CBD5E1;
}

.art-pagination .page-numbers.current {
    background: #1A84EE;
    border-color: #1A84EE;
    color: #fff;
    font-weight: 700;
}

.art-pagination .page-numbers.dots {
    border: none;
    background: transparent;
}

.art-pagination .page-numbers .dashicons {
    font-size: 18px;
    width: 18px;
    height: 18px;
}

/* === TOOLBAR === */
.art-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
}

.art-per-page {
    display: flex;
    align-items: center;
    gap: 8px;
}

.per-page-label {
    font-size: 14px;
    color: #475569;
    font-weight: 500;
    margin: 0;
}

.art-per-page-select {
    height: 36px;
    padding: 0 32px 0 12px;
    background: #fff;
    border: 1px solid #E0E5F1;
    border-radius: 6px;
    font-size: 14px;
    color: #1E293B;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3E%3C/svg%3E");
    background-position: right 8px center;
    background-repeat: no-repeat;
    background-size: 16px;
}

.art-per-page-select:hover {
    border-color: #CBD5E1;
    background-color: #F8FAFC;
}

.art-per-page-select:focus {
    outline: none;
    border-color: #1A84EE;
    box-shadow: 0 0 0 3px rgba(26, 132, 238, 0.1);
}

.per-page-suffix {
    font-size: 14px;
    color: #64748b;
}

/* === RESPONSIVE (matching mockup's container queries) === */
@media (max-width: 900px) {
    .col-service {
        display: none;
    }
}

@media (max-width: 768px) {
    .col-requested-start {
        display: none;
    }
    
    .art-toolbar {
        flex-direction: column;
        align-items: stretch;
    }
}

@media (max-width: 600px) {
    .col-submitted {
        display: none;
    }
    
    .art-status-chips {
        gap: 6px;
    }
    
    .art-chip {
        font-size: 13px;
        padding: 0 12px;
        height: 32px;
    }
    
    .art-page-title {
        font-size: 24px;
    }
}
</style>

<!-- JavaScript for AJAX per-page update -->
<script>
jQuery(document).ready(function($) {
    // Handle per-page change
    $('#art-per-page-select').on('change', function() {
        var perPage = $(this).val();
        
        // Save preference via AJAX
        $.post(ajaxurl, {
            action: 'art_save_per_page',
            nonce: '<?php echo wp_create_nonce('art_nonce'); ?>',
            per_page: perPage
        }, function(response) {
            if (response.success) {
                // Reload page with paged=1 to show first page of new per_page
                var url = new URL(window.location.href);
                url.searchParams.set('paged', '1');
                window.location.href = url.toString();
            } else {
                console.error('ART: Failed to save per_page preference', response);
            }
        });
    });
});
</script>

