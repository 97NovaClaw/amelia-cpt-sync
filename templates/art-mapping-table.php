<?php
/**
 * ART Mapping Table Template
 *
 * Displays the field mapping interface after populating from JSON
 * Variables available: $fields, $form_config
 *
 * @package AmeliaCPTSync
 * @subpackage ART
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Get current mappings and critical fields
$current_mappings = $form_config['mappings'] ?? array();
$critical_fields = $form_config['critical_fields'] ?? array();
$intake_field_defs = $form_config['intake_fields'] ?? array();

// Build destination options
$destinations = array();

// Customer fields
$destinations['Customer Fields'] = array(
    'customer.first_name',
    'customer.last_name',
    'customer.email',
    'customer.phone'
);

// Request fields
$destinations['Request Fields'] = array(
    'request.service_id_source',
    'request.location_id',
    'request.persons',
    'request.start_datetime',
    'request.end_datetime',
    'request.final_price'
);

// Dynamic intake fields
if (!empty($intake_field_defs)) {
    $destinations['Intake Fields'] = array();
    foreach ($intake_field_defs as $label) {
        $destinations['Intake Fields'][] = 'intake_field.' . $label;
    }
}
?>

<h3>Map Form Fields to Destinations</h3>
<p>Match each form field to a destination. Fields marked as "Critical" will cause form validation to fail if invalid (in Strict mode only).</p>

<table class="widefat fixed striped">
    <thead>
        <tr>
            <th style="width: 25%;">Form Field Label</th>
            <th style="width: 20%;">Form Field ID</th>
            <th style="width: 40%;">Map To Destination</th>
            <th style="width: 15%;">Critical?</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($fields as $field): 
            $field_id = $field['id'];
            $field_name = $field['name'];
            $field_type = $field['type'] ?? '';
            $current_mapping = $current_mappings[$field_id] ?? '';
        ?>
            <tr>
                <td><?php echo esc_html($field_name); ?></td>
                <td>
                    <code><?php echo esc_html($field_id); ?></code>
                    <?php if ($field_type): ?>
                        <br><small style="color: #666;"><?php echo esc_html($field_type); ?></small>
                    <?php endif; ?>
                </td>
                <td>
                    <select name="mappings[<?php echo esc_attr($field_id); ?>]" 
                            class="art-mapping-select2" 
                            style="width: 100%;">
                        <option value="">-- Not Mapped --</option>
                        <?php foreach ($destinations as $group_label => $group_fields): ?>
                            <optgroup label="<?php echo esc_attr($group_label); ?>">
                                <?php foreach ($group_fields as $dest): ?>
                                    <option value="<?php echo esc_attr($dest); ?>" 
                                            <?php selected($current_mapping, $dest); ?>>
                                        <?php echo esc_html($dest); ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <label>
                        <input type="checkbox" 
                               name="critical_fields[]" 
                               value="<?php echo esc_attr($current_mapping); ?>"
                               data-field-id="<?php echo esc_attr($field_id); ?>"
                               data-row-id="row-<?php echo esc_attr($field_id); ?>"
                               class="critical-field-checkbox"
                               <?php checked(in_array($current_mapping, $critical_fields) && !empty($current_mapping)); ?>
                               <?php echo empty($current_mapping) ? 'disabled' : ''; ?>>
                        <span title="In Strict mode: Form fails if this field is missing or invalid. In Forgiving mode: Field is logged and skipped if invalid.">⚠️ Critical</span>
                    </label>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<p class="submit">
    <button type="submit" class="button button-primary button-large">
        Save Field Mappings & Settings
    </button>
</p>

<!-- Enqueue Select2 -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
jQuery(document).ready(function($) {
    // Initialize Select2
    $('.art-mapping-select2').select2({
        placeholder: '-- Not Mapped --',
        allowClear: true,
        width: 'resolve'
    });
    
    // When mapping changes, update critical checkbox value
    $('.art-mapping-select2').on('change', function() {
        var row = $(this).closest('tr');
        var checkbox = row.find('.critical-field-checkbox');
        var newValue = $(this).val();
        
        console.log('ART Mapping: Dropdown changed to:', newValue);
        
        if (newValue && newValue !== '') {
            // Enable checkbox and update its value to match the destination
            checkbox.prop('disabled', false);
            checkbox.val(newValue);
            console.log('ART Mapping: Checkbox enabled, value set to:', newValue);
        } else {
            // Disable and uncheck if unmapped
            checkbox.prop('disabled', true);
            checkbox.prop('checked', false);
            checkbox.val('');
            console.log('ART Mapping: Checkbox disabled (field unmapped)');
        }
    });
    
    // On page load, ensure all checkbox values match their mapping
    $('.art-mapping-select2').each(function() {
        var row = $(this).closest('tr');
        var checkbox = row.find('.critical-field-checkbox');
        var currentValue = $(this).val();
        
        if (currentValue) {
            checkbox.val(currentValue);
        }
    });
});
</script>

<style>
    .select2-container {
        z-index: 999999 !important;
    }
</style>

