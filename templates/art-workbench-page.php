<?php
/**
 * ART Workbench (Triage Requests List) Template
 *
 * Displays all triage requests with filtering, search, and pagination
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

// Get requests with filters
$results = $request_manager->get_requests(array(
    'status' => $current_status,
    'search' => $search_term,
    'paged' => $current_page,
    'per_page' => 20,
    'orderby' => 'submitted_at',
    'order' => 'DESC'
));

// Get status counts for filter chips
$status_counts = $request_manager->get_status_counts();

// Status badge colors
$status_colors = array(
    'Requested' => 'blue',
    'Responded' => 'purple',
    'Tentative' => 'yellow',
    'Booked' => 'green',
    'Abandoned' => 'gray'
);
?>

<div class="wrap art-workbench">
    <h1 class="wp-heading-inline">
        <?php _e('Triage Requests', 'amelia-cpt-sync'); ?>
    </h1>
    
    <?php if ($results['total'] > 0): ?>
        <span class="subtitle" style="color: #666; margin-left: 10px;">
            (<?php echo number_format($results['total']); ?> total)
        </span>
    <?php endif; ?>
    
    <hr class="wp-header-end">
    
    <!-- Status Filter Chips -->
    <div class="art-status-filters" style="margin: 20px 0; display: flex; gap: 10px; flex-wrap: wrap;">
        <a href="<?php echo esc_url(remove_query_arg(array('status_filter', 'paged'))); ?>" 
           class="art-filter-chip <?php echo empty($current_status) ? 'active' : ''; ?>">
            <span class="count"><?php echo $status_counts['all']; ?></span>
            All Requests
        </a>
        
        <?php foreach (array('Requested', 'Responded', 'Tentative', 'Booked', 'Abandoned') as $status): ?>
            <a href="<?php echo esc_url(add_query_arg(array('status_filter' => $status, 'paged' => 1))); ?>" 
               class="art-filter-chip <?php echo $current_status === $status ? 'active' : ''; ?> status-<?php echo strtolower($status); ?>">
                <span class="count"><?php echo $status_counts[$status]; ?></span>
                <?php echo esc_html($status); ?>
            </a>
        <?php endforeach; ?>
    </div>
    
    <!-- Search Bar -->
    <div class="art-search-bar" style="margin: 20px 0;">
        <form method="get" action="">
            <input type="hidden" name="page" value="art-workbench">
            <?php if (!empty($current_status)): ?>
                <input type="hidden" name="status_filter" value="<?php echo esc_attr($current_status); ?>">
            <?php endif; ?>
            
            <div style="display: flex; gap: 10px; max-width: 600px;">
                <input type="search" 
                       name="s" 
                       value="<?php echo esc_attr($search_term); ?>" 
                       placeholder="Search by customer name or email..." 
                       class="regular-text"
                       style="flex: 1;">
                <button type="submit" class="button">
                    <?php _e('Search', 'amelia-cpt-sync'); ?>
                </button>
                <?php if (!empty($search_term)): ?>
                    <a href="<?php echo esc_url(remove_query_arg(array('s', 'paged'))); ?>" class="button">
                        Clear
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <!-- Results Table -->
    <?php if (empty($results['items'])): ?>
        <div class="art-empty-state" style="text-align: center; padding: 60px 20px; background: #fff; border: 1px solid #ddd; border-radius: 4px;">
            <p style="font-size: 18px; color: #666; margin: 0 0 10px;">
                <?php if (!empty($search_term) || !empty($current_status)): ?>
                    <?php _e('No requests found matching your filters.', 'amelia-cpt-sync'); ?>
                <?php else: ?>
                    <?php _e('No triage requests yet.', 'amelia-cpt-sync'); ?>
                <?php endif; ?>
            </p>
            <p style="color: #999;">
                <?php _e('Requests will appear here as customers submit triage forms.', 'amelia-cpt-sync'); ?>
            </p>
        </div>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped art-requests-table">
            <thead>
                <tr>
                    <th scope="col" style="width: 60px;"><?php _e('ID', 'amelia-cpt-sync'); ?></th>
                    <th scope="col" style="width: 20%;"><?php _e('Customer', 'amelia-cpt-sync'); ?></th>
                    <th scope="col" style="width: 20%;"><?php _e('Contact', 'amelia-cpt-sync'); ?></th>
                    <th scope="col" style="width: 15%;"><?php _e('Submitted', 'amelia-cpt-sync'); ?></th>
                    <th scope="col" style="width: 12%;"><?php _e('Status', 'amelia-cpt-sync'); ?></th>
                    <th scope="col" style="width: 15%;"><?php _e('Service', 'amelia-cpt-sync'); ?></th>
                    <th scope="col" class="art-actions-col"><?php _e('Actions', 'amelia-cpt-sync'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results['items'] as $request): 
                    // Map status_key to display name
                    $status_display = ucfirst($request->status_key ?? 'requested');
                    $status_color = $status_colors[$status_display] ?? 'gray';
                    
                    $customer_name = trim($request->customer_first_name . ' ' . $request->customer_last_name);
                    if (empty($customer_name)) {
                        $customer_name = '—';
                    }
                ?>
                    <tr class="art-request-row" data-request-id="<?php echo esc_attr($request->id); ?>">
                        <td class="art-request-id">
                            <strong>#<?php echo esc_html($request->id); ?></strong>
                        </td>
                        <td class="art-customer-name">
                            <?php echo esc_html($customer_name); ?>
                        </td>
                        <td class="art-customer-contact">
                            <?php if (!empty($request->customer_email)): ?>
                                <div style="margin-bottom: 2px;">
                                    <a href="mailto:<?php echo esc_attr($request->customer_email); ?>">
                                        <?php echo esc_html($request->customer_email); ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($request->customer_phone)): ?>
                                <div style="color: #666; font-size: 12px;">
                                    <?php echo esc_html($request->customer_phone); ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="art-submitted-date">
                            <?php
                            $submitted_date = get_date_from_gmt($request->created_at);
                            $date = date_i18n('M j, Y', strtotime($submitted_date));
                            $time = date_i18n('g:i a', strtotime($submitted_date));
                            ?>
                            <div><?php echo $date; ?></div>
                            <div style="color: #666; font-size: 12px;"><?php echo $time; ?></div>
                        </td>
                        <td class="art-status">
                            <span class="art-status-badge status-<?php echo esc_attr($status_color); ?>">
                                <?php echo esc_html($status_display); ?>
                            </span>
                        </td>
                        <td class="art-service">
                            <?php if (!empty($request->service_id)): ?>
                                <code><?php echo esc_html($request->service_id); ?></code>
                            <?php else: ?>
                                <span style="color: #999;">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="art-actions">
                            <a href="<?php echo esc_url(add_query_arg(array('page' => 'art-request-detail', 'request_id' => $request->id), admin_url('admin.php'))); ?>" 
                               class="button button-small">
                                <?php _e('View Details', 'amelia-cpt-sync'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- Pagination -->
        <?php if ($results['total_pages'] > 1): ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php
                    $page_links = paginate_links(array(
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => __('&laquo; Previous', 'amelia-cpt-sync'),
                        'next_text' => __('Next &raquo;', 'amelia-cpt-sync'),
                        'total' => $results['total_pages'],
                        'current' => $current_page,
                        'type' => 'plain'
                    ));
                    
                    if ($page_links) {
                        echo '<span class="pagination-links">' . $page_links . '</span>';
                    }
                    ?>
                    <span class="displaying-num">
                        <?php
                        printf(
                            _n('%s item', '%s items', $results['total'], 'amelia-cpt-sync'),
                            number_format_i18n($results['total'])
                        );
                        ?>
                    </span>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Inline Styles (will move to CSS file) -->
<style>
.art-workbench {
    background: #f5f5f5;
    padding: 20px;
    margin: -10px -20px 0 -22px;
}

.art-status-filters {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.art-filter-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    background: #fff;
    border: 2px solid #ddd;
    border-radius: 20px;
    text-decoration: none;
    color: #333;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.2s;
}

.art-filter-chip:hover {
    border-color: #2271b1;
    color: #2271b1;
    text-decoration: none;
}

.art-filter-chip.active {
    background: #2271b1;
    border-color: #2271b1;
    color: #fff;
}

.art-filter-chip .count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 24px;
    height: 24px;
    padding: 0 6px;
    background: #f0f0f0;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.art-filter-chip.active .count {
    background: rgba(255, 255, 255, 0.3);
    color: #fff;
}

/* Status color variations */
.art-filter-chip.status-requested { border-color: #0073aa; }
.art-filter-chip.status-requested:hover,
.art-filter-chip.status-requested.active { background: #0073aa; border-color: #0073aa; }

.art-filter-chip.status-responded { border-color: #826eb4; }
.art-filter-chip.status-responded:hover,
.art-filter-chip.status-responded.active { background: #826eb4; border-color: #826eb4; }

.art-filter-chip.status-tentative { border-color: #f0b849; }
.art-filter-chip.status-tentative:hover,
.art-filter-chip.status-tentative.active { background: #f0b849; border-color: #f0b849; }

.art-filter-chip.status-booked { border-color: #46b450; }
.art-filter-chip.status-booked:hover,
.art-filter-chip.status-booked.active { background: #46b450; border-color: #46b450; }

.art-filter-chip.status-abandoned { border-color: #999; }
.art-filter-chip.status-abandoned:hover,
.art-filter-chip.status-abandoned.active { background: #999; border-color: #999; }

.art-search-bar {
    background: #fff;
    padding: 15px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.art-requests-table {
    background: #fff;
    border: 1px solid #ddd;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.art-requests-table th {
    font-weight: 600;
}

.art-request-row {
    cursor: pointer;
}

.art-request-row:hover {
    background: #f9f9f9 !important;
}

.art-status-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.art-status-badge.status-blue {
    background: #e5f2ff;
    color: #0073aa;
}

.art-status-badge.status-purple {
    background: #f0ebf7;
    color: #826eb4;
}

.art-status-badge.status-yellow {
    background: #fef8e5;
    color: #f0b849;
}

.art-status-badge.status-green {
    background: #ecf7ed;
    color: #46b450;
}

.art-status-badge.status-gray {
    background: #f0f0f0;
    color: #666;
}

.art-empty-state {
    text-align: center;
    padding: 60px 20px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.art-actions-col {
    text-align: center;
}
</style>

