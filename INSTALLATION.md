# Installation & Setup Guide

Complete step-by-step guide for installing and configuring the Amelia to CPT Sync plugin.

## Prerequisites

Before installing this plugin, ensure you have:

1. **WordPress** (version 5.0 or higher)
2. **PHP** (version 7.2 or higher)
3. **AmeliaWP Booking Plugin** - Active and configured
4. **JetEngine Plugin** - Active with at least one Custom Post Type created
5. **Administrator access** to your WordPress site

## Step 1: Install the Plugin

### Method A: Upload via WordPress Admin

1. Download the plugin as a ZIP file
2. Log in to your WordPress admin dashboard
3. Navigate to **Plugins → Add New**
4. Click **Upload Plugin** at the top
5. Click **Choose File** and select the ZIP file
6. Click **Install Now**
7. Click **Activate Plugin**

### Method B: Manual Installation via FTP

1. Download and extract the plugin files
2. Connect to your server via FTP
3. Upload the `amelia-cpt-sync` folder to `/wp-content/plugins/`
4. Log in to WordPress admin
5. Navigate to **Plugins**
6. Find "Amelia to CPT Sync" and click **Activate**

### Method C: Using WP-CLI

```bash
cd /path/to/wordpress
wp plugin install amelia-cpt-sync.zip
wp plugin activate amelia-cpt-sync
```

## Step 2: Create Your JetEngine CPT

If you haven't already created a Custom Post Type for your services:

1. Go to **JetEngine → Post Types**
2. Click **Add New**
3. Configure your post type:
   - **General Settings**:
     - Slug: `services` (or your preference)
     - Name: `Services`
   - **Advanced Settings**:
     - Enable "Show in Admin Bar"
     - Enable "Has Archive"
     - Enable "Public"
4. Click **Add Post Type**

## Step 3: Create Meta Fields in JetEngine

Create the following meta fields for your services CPT:

### 3.1: Create Price Field

1. Go to **JetEngine → Meta Boxes**
2. Click **Add New**
3. Name: `Service Fields`
4. Post Type: Select your services CPT
5. Click **Add Meta Field**:
   - **Label**: `Price`
   - **Name/ID**: `service_price`
   - **Type**: `Number`
   - **Step Value**: `0.01`
6. Save the meta box

### 3.2: Create Duration Field

Add another field:
- **Label**: `Duration`
- **Name/ID**: `service_duration`
- **Type**: `Text` (or `Number` if using seconds/minutes format)

### 3.3: Create Gallery Field

Add another field:
- **Label**: `Gallery`
- **Name/ID**: `service_gallery`
- **Type**: `Gallery`

### 3.4: Create Extras Field

Add another field:
- **Label**: `Extras`
- **Name/ID**: `service_extras`
- **Type**: `Repeater`
- Add repeater fields:
  - `name` (Text)
  - `price` (Number)
  - `description` (Textarea)

## Step 4: Create a Taxonomy (Optional but Recommended)

1. Go to **JetEngine → Taxonomies**
2. Click **Add New**
3. Configure:
   - **Slug**: `service-category`
   - **Name**: `Service Categories`
   - **Post Types**: Select your services CPT
4. Click **Add Taxonomy**

## Step 5: Configure the Sync Plugin

### 5.1: Access Settings

1. In WordPress admin, go to **Amelia to CPT Sync** (in the main menu)
2. You'll see a settings page with two tabs: **Setup** and **Field Mapping**

### 5.2: Setup Tab Configuration

1. **Post Type Selection**:
   - Select your services CPT (e.g., "Services")
   
2. **Taxonomy Selection**:
   - After selecting the post type, the taxonomy dropdown will populate
   - Select your service categories taxonomy (e.g., "Service Categories")

### 5.3: Field Mapping Tab Configuration

Enter the meta field slugs you created in JetEngine:

1. **Price**: Enter `service_price`
2. **Duration**: 
   - Enter `service_duration`
   - Select format: Choose based on your needs
     - **Raw Seconds**: Best for calculations (e.g., 5400)
     - **Total Minutes**: Best for simple display (e.g., 90)
     - **HH:MM Format**: Best for time display (e.g., 01:30)
     - **Readable Text**: Best for natural language (e.g., "1 hour 30 minutes")
3. **Gallery**: Enter `service_gallery`
4. **Extras**: Enter `service_extras`

### 5.4: Save Settings

Click **Save Settings** and wait for the success message.

## Step 6: Test the Synchronization

### 6.1: Create a Test Service in Amelia

1. Go to **Amelia → Services**
2. Click **Add Service**
3. Fill in all fields:
   - Name
   - Category
   - Price
   - Duration
   - Description
   - Upload a featured image
   - Add gallery images (optional)
   - Add extras (optional)
4. Click **Save**

### 6.2: Verify the Sync

1. Go to your services CPT (e.g., **Services** in the admin menu)
2. You should see a new post with:
   - The service name as the title
   - Description as the content
   - Featured image set
   - All meta fields populated
   - Category assigned

### 6.3: Test Updates

1. Edit the service in Amelia
2. Change some details (name, price, etc.)
3. Save
4. Verify the CPT post is updated immediately

### 6.4: Test Deletion

1. Delete a test service in Amelia
2. Verify the corresponding CPT post is permanently deleted

## Troubleshooting Installation

### Plugin Won't Activate

**Error**: "The plugin requires WordPress version 5.0 or higher"
- **Solution**: Update WordPress to the latest version

**Error**: "Cannot redeclare class..."
- **Solution**: Deactivate any conflicting plugins, then activate this one

### Settings Page Not Showing

**Issue**: Can't find "Amelia to CPT Sync" in the admin menu
- **Solution**: 
  1. Verify the plugin is activated
  2. Check that your user role is Administrator
  3. Clear browser cache
  4. Try a different browser

### Taxonomies Not Loading

**Issue**: Taxonomy dropdown stays empty after selecting post type
- **Solution**:
  1. Verify the post type has registered taxonomies
  2. Check browser console for JavaScript errors
  3. Disable caching plugins temporarily
  4. Clear browser cache

### Sync Not Working After Setup

**Issue**: Creating services in Amelia doesn't create CPT posts
- **Solution**:
  1. Verify AmeliaWP is active and updated
  2. Check that all settings are saved (look for success message)
  3. Enable WordPress debug mode:
     ```php
     define('WP_DEBUG', true);
     define('WP_DEBUG_LOG', true);
     ```
  4. Create a test service and check `/wp-content/debug.log`
  5. Look for `[Amelia CPT Sync]` entries

## Post-Installation Recommendations

### 1. Test Thoroughly

Before using in production:
- Create test services with all field types
- Test updates on all fields
- Test image uploads
- Test service deletion
- Verify extras/add-ons sync correctly

### 2. Configure Permissions

Set appropriate file permissions:
```bash
chmod 755 /wp-content/plugins/amelia-cpt-sync
chmod 644 /wp-content/plugins/amelia-cpt-sync/*.php
```

### 3. Enable Debug Mode (Development Only)

For debugging during setup, add to `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
@ini_set('display_errors', 0);
```

**Important**: Disable debug mode on production sites!

### 4. Backup Your Database

Before enabling the plugin:
```bash
wp db export backup.sql
```

Or use a backup plugin like UpdraftPlus.

### 5. Monitor First Syncs

Watch the first few syncs carefully:
- Check that images are being uploaded correctly
- Verify all meta fields are populated
- Ensure categories are being created properly

## Next Steps

After installation:

1. **Display on Frontend**: Use JetEngine's Listing Grid to display your synced services
2. **Create Templates**: Design single service page templates using Elementor/Gutenberg
3. **Style Your Services**: Customize the appearance of your service listings
4. **Add Filtering**: Use JetSmartFilters for service filtering by category, price, etc.

## Getting Help

If you encounter issues:

1. Check the main README.md for troubleshooting tips
2. Enable debug mode and check logs
3. Verify all prerequisites are met
4. Test with default WordPress theme and only required plugins active

## Useful Commands

### Check if plugin is active:
```bash
wp plugin list --status=active
```

### View debug log:
```bash
tail -f /path/to/wordpress/wp-content/debug.log
```

### Deactivate plugin:
```bash
wp plugin deactivate amelia-cpt-sync
```

### Reactivate plugin:
```bash
wp plugin activate amelia-cpt-sync
```

## Summary Checklist

- [ ] WordPress, PHP, AmeliaWP, and JetEngine meet minimum requirements
- [ ] Plugin installed and activated successfully
- [ ] JetEngine CPT created with proper settings
- [ ] Meta fields created in JetEngine
- [ ] Taxonomy created (if using categories)
- [ ] Plugin settings configured (Setup tab)
- [ ] Field mappings entered (Field Mapping tab)
- [ ] Settings saved successfully
- [ ] Test service created and synced properly
- [ ] Updates tested and working
- [ ] Deletion tested and working
- [ ] Ready for production use

Congratulations! Your Amelia to CPT sync is now set up and ready to use.

