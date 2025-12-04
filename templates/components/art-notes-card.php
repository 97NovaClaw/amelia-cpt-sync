<?php
/**
 * ART Notes Card Component
 *
 * Reusable notes and activity log component
 * Chat-style interface: oldest at top, newest at bottom, input at bottom
 *
 * @package AmeliaCPTSync
 * @subpackage ART
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}
?>

<div class="art-detail-card art-notes-card">
    <div class="card-header">
        <h3><?php _e('Notes & Activity', 'amelia-cpt-sync'); ?></h3>
    </div>
    
    <div class="card-body">
        <!-- Scrollable notes list (oldest at top, newest at bottom) -->
        <div class="art-notes-list" id="art-notes-list">
            <!-- Notes will be rendered here via JavaScript -->
            <div class="notes-loading-initial">
                <span class="dashicons dashicons-update spin"></span>
                <?php _e('Loading activity...', 'amelia-cpt-sync'); ?>
            </div>
        </div>
        
        <!-- Input area at bottom -->
        <div class="art-note-composer">
            <div class="note-toolbar">
                <button type="button" class="format-btn" data-command="bold" title="<?php esc_attr_e('Bold', 'amelia-cpt-sync'); ?>">
                    <strong>B</strong>
                </button>
                <button type="button" class="format-btn" data-command="list" title="<?php esc_attr_e('Bullet List', 'amelia-cpt-sync'); ?>">
                    • <?php _e('List', 'amelia-cpt-sync'); ?>
                </button>
                <span class="char-counter">0/1000</span>
            </div>
            
            <div class="note-input-wrapper">
                <div 
                    id="note-input" 
                    class="note-input" 
                    contenteditable="true" 
                    data-placeholder="<?php esc_attr_e('Add a note...', 'amelia-cpt-sync'); ?>"
                ></div>
            </div>
            
            <div class="note-actions">
                <button type="button" id="btn-add-note" class="art-button btn-primary" disabled>
                    <?php _e('Add Note', 'amelia-cpt-sync'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

