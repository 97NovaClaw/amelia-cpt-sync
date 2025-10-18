# Amelia to CPT Sync

A WordPress plugin that provides real-time, one-way synchronization from the AmeliaWP booking plugin to JetEngine Custom Post Types.

## Description

This plugin bridges the gap between AmeliaWP and JetEngine by automatically syncing service data to your custom post types. The synchronization is event-driven and happens in real-time whenever you create, update, or delete services in Amelia.

### Key Features

- **Real-Time Synchronization**: Uses Amelia's built-in action hooks for immediate updates
- **One-Way Sync**: Amelia → JetEngine CPT (changes in CPT won't affect Amelia)
- **User-Friendly Configuration**: No coding required - configure everything through the admin interface
- **Smart Image Handling**: Automatically imports images to WordPress Media Library with custom field support
- **Flexible Duration Formats**: Choose from seconds, minutes, HH:MM, or readable text
- **Category Management**: Automatically creates and assigns taxonomy terms
- **Extras Support**: Full support for service extras/add-ons with JetEngine Repeater fields
- **Full Sync Feature**: Manually sync all existing Amelia services with comparison and progress tracking
- **Custom Field Support**: Map primary photo to any custom field instead of featured image

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher
- AmeliaWP booking plugin (active)
- JetEngine plugin (active)

## Installation

1. Download the plugin files
2. Upload the `amelia-cpt-sync` folder to the `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Navigate to **Amelia to CPT Sync** in the WordPress admin menu
5. Configure your sync settings

## Configuration

### Setup Tab

1. **Post Type Selection**: Choose the JetEngine Custom Post Type where services will be synced
2. **Taxonomy Selection**: Choose the taxonomy for service categories (loads after selecting post type)

### Field Mapping Tab

Map Amelia service data to your JetEngine CPT fields:

#### Automatic Mappings (Locked)
- **Service Name** → Post Title
- **Description** → Post Content  
- **Category** → Selected Taxonomy

#### Custom Field Mappings
Enter the meta field slugs from your JetEngine CPT setup:

- **Primary Photo**: Enter your image field slug (e.g., `service_image`)
  - Recommended JetEngine Type: **Media**
  - Stores WordPress attachment ID (not URL)
  - Image is automatically imported to Media Library

- **Price**: Enter your price field slug (e.g., `service_price`)
  - Recommended JetEngine Type: **Number**

- **Duration**: Enter your duration field slug (e.g., `service_duration`)
  - Choose format: Raw Seconds, Total Minutes, HH:MM, or Readable Text
  - Recommended JetEngine Type: **Text** (for HH:MM/readable) or **Number** (for seconds/minutes)

- **Gallery**: Enter your gallery field slug (e.g., `service_gallery`)
  - Recommended JetEngine Type: **Gallery**
  - Stores array of WordPress attachment IDs

- **Extras**: Enter your extras field slug (e.g., `service_extras`)
  - Recommended JetEngine Type: **Repeater**
  - Stores the complete extras array from Amelia

### Full Sync Tab

Use the Full Sync feature to manually sync all existing Amelia services:

1. Navigate to the **Full Sync** tab
2. Click **Run Full Sync Now**
3. The plugin will:
   - Fetch all services from Amelia database
   - Compare with existing CPT posts
   - Create new posts for services that don't exist
   - Update existing posts with latest data
   - Sync all meta fields, images, and categories
4. View detailed results showing:
   - Total services processed
   - Number created vs updated
   - Any errors encountered

## How It Works

### Event-Driven Synchronization

The plugin hooks into three Amelia action hooks:

1. `amelia_after_service_added` - Creates new CPT post when service is added
2. `amelia_after_service_updated` - Updates existing CPT post when service is modified
3. `amelia_before_service_deleted` - Permanently deletes CPT post when service is removed

### Data Flow

```
Amelia Service Created/Updated → Plugin Triggered → CPT Post Created/Updated
Amelia Service Deleted → Plugin Triggered → CPT Post Permanently Deleted
```

### Image Handling

Images are not stored as URLs. Instead:
- The plugin downloads images from Amelia
- Imports them into WordPress Media Library using `media_handle_sideload()`
- Stores attachment IDs in the appropriate fields
- This ensures images are properly managed and optimized by WordPress

### Category Synchronization

When a service is synced:
1. Plugin checks if a term with the category name exists in your selected taxonomy
2. If not found, creates the term automatically
3. Assigns the post to that term

## File Structure

```
amelia-cpt-sync/
├── amelia-cpt-sync.php           # Main plugin file
├── includes/
│   ├── class-admin-settings.php  # Admin settings page & AJAX handlers
│   ├── class-cpt-manager.php     # CPT/taxonomy operations
│   └── class-sync-handler.php    # Amelia hook integration
├── templates/
│   └── admin-settings-page.php   # Admin UI template
├── assets/
│   ├── css/
│   │   └── admin.css            # Admin styles
│   └── js/
│       └── admin.js             # Admin JavaScript
└── README.md                     # This file
```

## Troubleshooting

### Synchronization Not Working

1. **Check if settings are saved**: Go to Amelia to CPT Sync settings and verify all fields are filled
2. **Verify Amelia hooks**: Ensure you're using a compatible version of AmeliaWP
3. **Enable WordPress debug mode**: Add to `wp-config.php`:
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   ```
4. **Check debug.log**: Look for `[Amelia CPT Sync]` entries in `/wp-content/debug.log`

### Images Not Syncing

1. Check file permissions on `/wp-content/uploads/` directory
2. Verify the image URLs from Amelia are accessible
3. Ensure PHP has `allow_url_fopen` enabled
4. Check WordPress debug log for image-related errors

### Taxonomies Not Loading

1. Verify the selected CPT has registered taxonomies
2. Try selecting a different post type and then re-selecting your desired one
3. Clear browser cache and refresh the settings page

### Custom Fields Not Appearing

1. Double-check the meta field slugs in JetEngine
2. Field slugs are case-sensitive - ensure exact match
3. Make sure the fields are added to the correct post type in JetEngine

## Best Practices

1. **Test First**: Set up a test post type before using your production CPT
2. **Backup**: Always backup your database before making configuration changes
3. **Field Naming**: Use clear, descriptive meta field slugs (e.g., `service_price`, not `sp`)
4. **Duration Format**: Choose the format that matches your front-end display needs
5. **Category Taxonomy**: Use a dedicated taxonomy for Amelia categories (don't mix with other content)

## Frequently Asked Questions

### Does this plugin work with other booking plugins?
No, this plugin is specifically designed for AmeliaWP and uses its unique action hooks.

### Can I sync from CPT back to Amelia?
No, this is a one-way sync from Amelia to CPT only. Changes in the CPT won't affect Amelia.

### Will this work without JetEngine?
The plugin will work with any CPT, but it's optimized for JetEngine field types.

### What happens to old synced posts if I change the CPT?
They remain in the old CPT. Only new/updated services will sync to the new CPT.

### Can I manually trigger a full sync?
Not in the current version. Sync happens automatically when you create/update services in Amelia.

### Does deleting a CPT post delete the Amelia service?
No, this is one-way sync only. Deleting the CPT post won't affect Amelia.

## Performance Considerations

- The plugin is event-driven and only runs when Amelia services are modified
- No cron jobs or background processes
- No performance impact on frontend or admin pages
- Image sideloading happens asynchronously during sync

## Support & Development

### Reporting Issues
If you encounter any issues:
1. Enable WP_DEBUG and check the debug.log
2. Note the exact steps to reproduce the issue
3. Check if the issue occurs with other plugins disabled

### Feature Requests
This plugin is designed to be modular and extensible. Future enhancements could include:
- Manual full sync functionality
- Sync history log
- Custom field type detection
- Support for additional Amelia data fields

## Changelog

### Version 1.0.0
- Initial release
- Real-time sync for service add/update/delete
- Configurable field mappings
- Image sideloading to Media Library
- Multiple duration format options
- Automatic category/taxonomy management
- Support for service extras/add-ons

## License

This plugin is licensed under the GPL v2 or later.

## Credits

Developed for seamless integration between AmeliaWP and JetEngine.

