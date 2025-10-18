# Developer Documentation

Technical documentation for developers working with or extending the Amelia to CPT Sync plugin.

## Architecture Overview

The plugin follows an object-oriented, modular architecture with three main classes:

```
┌─────────────────────────────────────────────┐
│         amelia-cpt-sync.php                 │
│         (Main Plugin File)                  │
└────────────────┬────────────────────────────┘
                 │
                 ├──────────────────────────────────┐
                 │                                  │
       ┌─────────▼──────────┐          ┌───────────▼──────────┐
       │  Admin Settings    │          │   Sync Handler       │
       │  (Admin UI)        │          │   (Event Listener)   │
       └─────────┬──────────┘          └───────────┬──────────┘
                 │                                  │
                 │                     ┌────────────▼──────────┐
                 │                     │   CPT Manager         │
                 └────────────────────►│   (CRUD Operations)   │
                                       └───────────────────────┘
```

## Class Reference

### 1. Amelia_CPT_Sync_Admin_Settings

**File**: `includes/class-admin-settings.php`

**Purpose**: Handles all admin interface functionality, settings management, and AJAX operations.

#### Methods

##### `init()`
Initializes the class by hooking into WordPress actions.

```php
public function init()
```

**Hooks Used**:
- `admin_menu` - Adds admin menu page
- `admin_init` - Registers settings
- `admin_enqueue_scripts` - Loads CSS/JS
- `wp_ajax_amelia_cpt_sync_save_settings` - AJAX save handler
- `wp_ajax_amelia_cpt_sync_get_taxonomies` - AJAX taxonomy loader

##### `get_settings()`
Retrieves current settings from database.

```php
public function get_settings()
```

**Returns**: `array` - Settings array with structure:
```php
array(
    'cpt_slug' => string,
    'taxonomy_slug' => string,
    'field_mappings' => array(
        'price' => string,
        'duration' => string,
        'duration_format' => string,
        'gallery' => string,
        'extras' => string
    )
)
```

##### `ajax_save_settings()`
AJAX handler for saving settings.

**POST Parameters**:
- `nonce` - Security nonce
- `cpt_slug` - Target CPT slug
- `taxonomy_slug` - Target taxonomy slug
- `price_field` - Price meta field slug
- `duration_field` - Duration meta field slug
- `duration_format` - Duration format (seconds|minutes|hh_mm|readable)
- `gallery_field` - Gallery meta field slug
- `extras_field` - Extras meta field slug

### 2. Amelia_CPT_Sync_CPT_Manager

**File**: `includes/class-cpt-manager.php`

**Purpose**: Manages all interactions with Custom Post Types, taxonomies, and meta fields.

#### Methods

##### `sync_service($service_data)`
Creates or updates a CPT post from Amelia service data.

```php
public function sync_service($service_data)
```

**Parameters**:
- `$service_data` (array) - Amelia service data

**Returns**: `int|WP_Error` - Post ID on success, WP_Error on failure

**Expected Service Data Structure**:
```php
array(
    'id' => int,                    // Required
    'name' => string,               // Service name
    'description' => string,        // Service description
    'price' => float,              // Service price
    'duration' => int,             // Duration in seconds
    'pictureFullPath' => string,   // Primary image URL
    'categoryId' => int,           // Category ID
    'categoryName' => string,      // Category name
    'gallery' => array(            // Gallery images
        array(
            'pictureFullPath' => string
        )
    ),
    'extras' => array(             // Service extras
        array(
            'name' => string,
            'price' => float,
            'description' => string
        )
    )
)
```

##### `delete_service($amelia_service_id)`
Permanently deletes a CPT post by Amelia service ID.

```php
public function delete_service($amelia_service_id)
```

**Parameters**:
- `$amelia_service_id` (int) - Amelia service ID

**Returns**: `bool|WP_Error` - True on success, WP_Error on failure

##### `format_duration($seconds, $format)`
Formats duration based on selected format.

```php
private function format_duration($seconds, $format)
```

**Parameters**:
- `$seconds` (int) - Duration in seconds
- `$format` (string) - Format type: 'seconds', 'minutes', 'hh_mm', 'readable'

**Returns**: `mixed` - Formatted duration value

**Examples**:
```php
format_duration(5400, 'seconds')  // 5400
format_duration(5400, 'minutes')  // 90
format_duration(5400, 'hh_mm')    // "01:30"
format_duration(5400, 'readable') // "1 hour 30 minutes"
```

### 3. Amelia_CPT_Sync_Handler

**File**: `includes/class-sync-handler.php`

**Purpose**: Hooks into Amelia's action hooks and orchestrates the synchronization process.

#### Methods

##### `init()`
Initializes the sync handler and registers Amelia hooks.

```php
public function init()
```

**Amelia Hooks Used**:
- `amelia_after_service_added` - Service creation
- `amelia_after_service_updated` - Service update
- `amelia_before_service_deleted` - Service deletion

##### `handle_service_added($service_data)`
Handles service creation events.

```php
public function handle_service_added($service_data)
```

##### `handle_service_updated($service_data)`
Handles service update events.

```php
public function handle_service_updated($service_data)
```

##### `handle_service_deleted($service_data)`
Handles service deletion events.

```php
public function handle_service_deleted($service_data)
```

##### `enrich_service_data($service_data)`
Enriches service data with additional information.

```php
private function enrich_service_data($service_data)
```

**Purpose**: Fetches additional data not included in hook payload, such as:
- Full image URLs
- Category names
- Complete gallery information

## Hooks & Filters

### Actions

#### Plugin Actions

Currently, the plugin doesn't expose custom action hooks, but this can be added:

```php
// Example of future action hooks:

// Before service sync
do_action('amelia_cpt_sync_before_sync', $service_data, $post_id);

// After service sync
do_action('amelia_cpt_sync_after_sync', $service_data, $post_id);

// Before service delete
do_action('amelia_cpt_sync_before_delete', $amelia_service_id, $post_id);

// After service delete
do_action('amelia_cpt_sync_after_delete', $amelia_service_id);
```

### Filters

Future filter hooks for customization:

```php
// Example of future filter hooks:

// Modify service data before sync
$service_data = apply_filters('amelia_cpt_sync_service_data', $service_data);

// Modify post data before insert/update
$post_data = apply_filters('amelia_cpt_sync_post_data', $post_data, $service_data);

// Modify duration format
$duration = apply_filters('amelia_cpt_sync_duration_format', $duration, $seconds, $format);

// Modify image sideload behavior
$should_sideload = apply_filters('amelia_cpt_sync_should_sideload_image', true, $image_url);
```

## Database Schema

### Options Table

The plugin stores settings in `wp_options`:

**Option Name**: `amelia_cpt_sync_settings`

**Value Format**: JSON string
```json
{
    "cpt_slug": "services",
    "taxonomy_slug": "service-category",
    "field_mappings": {
        "price": "service_price",
        "duration": "service_duration",
        "duration_format": "hh_mm",
        "gallery": "service_gallery",
        "extras": "service_extras"
    }
}
```

### Post Meta

For each synced post, the following meta is stored:

| Meta Key | Type | Description |
|----------|------|-------------|
| `_amelia_service_id` | integer | Amelia service ID (for lookups) |
| `{price_field}` | float | Service price |
| `{duration_field}` | mixed | Duration (format varies) |
| `{gallery_field}` | array | Gallery attachment IDs |
| `{extras_field}` | array | Extras/add-ons data |

## Extending the Plugin

### Adding Custom Field Mappings

To add support for additional Amelia fields:

1. **Update Settings Structure** (`class-admin-settings.php`):

```php
// In get_settings() method, add new field
'field_mappings' => array(
    'price' => '',
    'duration' => '',
    'duration_format' => 'seconds',
    'gallery' => '',
    'extras' => '',
    'capacity' => '', // New field
)
```

2. **Update Admin Template** (`templates/admin-settings-page.php`):

```php
<tr>
    <td><strong><?php _e('Capacity', 'amelia-cpt-sync'); ?></strong></td>
    <td><?php _e('Maximum number of bookings', 'amelia-cpt-sync'); ?></td>
    <td>
        <input type="text" name="capacity_field" id="capacity_field" 
               value="<?php echo esc_attr($settings['field_mappings']['capacity']); ?>">
    </td>
    <td><?php _e('Type: Number', 'amelia-cpt-sync'); ?></td>
</tr>
```

3. **Update AJAX Save Handler** (`class-admin-settings.php`):

```php
// In ajax_save_settings() method
$capacity_field = sanitize_text_field($_POST['capacity_field']);

$settings = array(
    // ... existing fields
    'field_mappings' => array(
        // ... existing mappings
        'capacity' => $capacity_field
    )
);
```

4. **Update Sync Logic** (`class-cpt-manager.php`):

```php
// In sync_custom_fields() method
if (!empty($field_mappings['capacity']) && isset($service_data['maxCapacity'])) {
    update_post_meta($post_id, $field_mappings['capacity'], intval($service_data['maxCapacity']));
}
```

### Adding Custom Field Transformations

Create a custom transformer:

```php
// In class-cpt-manager.php

private function transform_field_value($value, $field_type, $settings = array()) {
    switch ($field_type) {
        case 'price':
            // Add currency symbol
            return '$' . number_format($value, 2);
            
        case 'duration':
            // Custom duration format
            return $this->format_duration($value, $settings['format']);
            
        default:
            return $value;
    }
}
```

### Creating Custom Sync Handlers

For specialized sync requirements:

```php
// Create a new class extending the base handler

class Custom_Amelia_Sync_Handler extends Amelia_CPT_Sync_Handler {
    
    public function init() {
        parent::init();
        
        // Add custom hooks
        add_action('amelia_after_employee_added', array($this, 'sync_employee'));
    }
    
    public function sync_employee($employee_data) {
        // Custom employee sync logic
    }
}
```

## Performance Considerations

### Image Sideloading

Image imports can be resource-intensive. Consider:

1. **Queue-Based Processing** (Future Enhancement):

```php
// Pseudo-code for queued image processing
function queue_image_sideload($image_url, $post_id) {
    wp_schedule_single_event(time(), 'process_image_sideload', array($image_url, $post_id));
}

add_action('process_image_sideload', 'process_image_sideload_callback', 10, 2);
```

2. **Check for Existing Images**:

```php
// Already implemented in set_featured_image()
$current_thumbnail_url = wp_get_attachment_url($current_thumbnail_id);
if ($current_thumbnail_url === $image_url) {
    return; // Skip if same image
}
```

### Database Queries

The plugin uses efficient queries:

```php
// Single query to find post by Amelia ID
$args = array(
    'post_type' => $post_type,
    'meta_query' => array(
        array(
            'key' => '_amelia_service_id',
            'value' => $amelia_service_id
        )
    ),
    'fields' => 'ids' // Only return IDs
);
```

### Caching Considerations

Settings are retrieved from database on each sync. For high-traffic sites:

```php
// Example caching implementation
private function get_settings() {
    $cache_key = 'amelia_cpt_sync_settings';
    $settings = wp_cache_get($cache_key);
    
    if (false === $settings) {
        $settings_json = get_option($this->option_name);
        $settings = json_decode($settings_json, true);
        wp_cache_set($cache_key, $settings, '', 3600);
    }
    
    return $settings;
}
```

## Testing

### Unit Testing Setup

```php
// Example PHPUnit test

class Test_CPT_Manager extends WP_UnitTestCase {
    
    private $cpt_manager;
    
    public function setUp() {
        parent::setUp();
        $this->cpt_manager = new Amelia_CPT_Sync_CPT_Manager();
    }
    
    public function test_format_duration_seconds() {
        $result = $this->cpt_manager->format_duration(5400, 'seconds');
        $this->assertEquals(5400, $result);
    }
    
    public function test_format_duration_hh_mm() {
        $result = $this->cpt_manager->format_duration(5400, 'hh_mm');
        $this->assertEquals('01:30', $result);
    }
}
```

### Manual Testing Checklist

- [ ] Service creation triggers CPT post creation
- [ ] Service update triggers CPT post update
- [ ] Service deletion triggers CPT post deletion
- [ ] Images are imported to Media Library
- [ ] Categories are created and assigned
- [ ] All meta fields are populated correctly
- [ ] Duration formats work correctly
- [ ] Gallery images sync properly
- [ ] Extras array syncs correctly
- [ ] Settings save via AJAX
- [ ] Taxonomy dropdown loads dynamically

## Debugging

### Enable Debug Mode

Add to `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### Debug Logging

The plugin includes debug logging:

```php
// In class-sync-handler.php
private function log_debug($message, $data = null) {
    if (defined('WP_DEBUG') && WP_DEBUG === true) {
        error_log('[Amelia CPT Sync] ' . $message);
        if ($data !== null) {
            error_log('[Amelia CPT Sync] Data: ' . print_r($data, true));
        }
    }
}
```

### Check Debug Log

```bash
tail -f /wp-content/debug.log | grep "Amelia CPT Sync"
```

## Security

### Nonce Verification

All AJAX requests use nonce verification:

```php
check_ajax_referer('amelia_cpt_sync_nonce', 'nonce');
```

### Capability Checks

All admin operations check capabilities:

```php
if (!current_user_can('manage_options')) {
    wp_send_json_error(array('message' => 'Unauthorized'));
}
```

### Input Sanitization

All inputs are sanitized:

```php
$cpt_slug = sanitize_text_field($_POST['cpt_slug']);
$price = floatval($service_data['price']);
```

### Output Escaping

All outputs are escaped:

```php
echo esc_html($service_name);
echo esc_attr($field_value);
echo esc_url($image_url);
```

## Best Practices

1. **Always check settings exist** before syncing
2. **Log errors** for debugging
3. **Validate data** from Amelia before processing
4. **Handle WP_Error** objects from WordPress functions
5. **Use WordPress functions** instead of direct database queries
6. **Follow WordPress coding standards**
7. **Document all functions** with PHPDoc
8. **Test thoroughly** before deploying

## Contributing

To contribute to this plugin:

1. Fork the repository
2. Create a feature branch
3. Follow WordPress coding standards
4. Add PHPDoc comments
5. Test thoroughly
6. Submit a pull request

## Resources

- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- [JetEngine Documentation](https://crocoblock.com/knowledge-base/jetengine/)
- [AmeliaWP Hooks Documentation](https://wpamelia.com/hooks/)

---

For questions or support, please open an issue on GitHub.

