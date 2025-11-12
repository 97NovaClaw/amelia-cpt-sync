# 🔄 Development Handoff Document
**Session Date**: November 11, 2024  
**Plugin**: Amelia Expansion Suite (formerly Amelia to CPT Sync)  
**Version**: 2.0.0  
**Branch**: `request-module` (formerly `dynamic-popups`)  
**Current Phase**: Phase 2 Complete ✅ → Moving to Phase 3

---

## 🎯 SESSION SUMMARY

### What Was Accomplished
1. ✅ **Fixed Validation** - Form now properly fails with JFB exception
2. ✅ **Fixed Duplicates** - Only register custom-filter hook (not action)
3. ✅ **Fixed Form Persistence** - Mappings now display after save/refresh
4. ✅ **Plugin Renamed** - "Amelia Expansion Suite" v2.0.0
5. ✅ **Documentation Cleaned** - Removed 6 redundant MD files, consolidated info

### What's Working
- ✅ Form configuration add/edit/delete (multi-form support)
- ✅ JFB JSON parsing via regex
- ✅ Field mapping with Select2 dropdowns
- ✅ Two validation modes (Pass Through Fails, Require Pass Through)
- ✅ Critical fields properly fail form submission in strict mode
- ✅ Database tables created on activation
- ✅ Debug logging to `debug.txt` (not WP_DEBUG_LOG)
- ✅ API caching toggle with duration control

### What Needs Improvement (Next Session)
- 🔧 **Error messages** - Use field labels instead of destination keys  
  Example: "Missing: Vehicle Selection" vs "Missing: request.service_id_source"
- 🔧 **Logic mode validation** - Some combinations may need circular logic checks
- 🔧 **Phase 3** - Build Triage Requests list view (workbench)

---

## 🔑 CRITICAL LESSONS LEARNED

### 1. JetFormBuilder Integration - THE GOTCHAS

#### ✅ CORRECT Exception Class
```php
// ✅ CORRECT (note: Exceptions, not Actions!)
throw new \Jet_Form_Builder\Exceptions\Action_Exception('error message');

// ❌ WRONG (we had this initially)
throw new \Jet_Form_Builder\Actions\Action_Exception('error message');
```

#### ✅ CORRECT Hook Registration
```php
// ✅ CORRECT - Only register custom-filter
add_filter("jet-form-builder/custom-filter/{$hook_name}", 'callback', 10, 3);

// ❌ WRONG - Causes duplicates
add_filter("jet-form-builder/custom-filter/{$hook_name}", 'callback', 10, 3);
add_action("jet-form-builder/custom-action/{$hook_name}", 'callback', 10, 2); // Don't do this!
```

**Why**: JFB fires both if both are registered → duplicate DB entries. Filter allows error returns.

#### Hook Signature
```php
function callback($result, $request, $action_handler) {
    // $request = array of form field values ['field_id' => 'value']
    // Throw exception to fail form
    // Return $result to pass
}
```

### 2. Validation Modes - How They Work

**Pass Through Fails (Forgiving)**:
- Invalid data → Logged, skipped, form succeeds
- Missing data → Logged, skipped, form succeeds
- Use case: When Amelia might not have all services yet

**Require Pass Through (Strict)**:
- Invalid data → Form fails with exception
- Missing critical field → Form fails with exception
- Use case: Production with complete Amelia setup

### 3. WordPress Patterns

#### Form Nesting for Settings API
**Problem**: Mappings weren't persisting on refresh  
**Solution**: All related data must be in ONE `<form>` element
```php
<form method="post">
    <?php wp_nonce_field('action', 'nonce_name'); ?>
    <!-- ALL fields here: basic info, mappings, logic, intake fields -->
    <button type="submit">Save All</button>
</form>
```

#### Debug Logging
```php
// Check if either main plugin OR ART module debug is enabled
function amelia_cpt_sync_is_debug_enabled() {
    $main_debug = get_option('amelia_cpt_sync_debug_mode', false);
    $art_debug = get_option('amelia_cpt_sync_art_settings', [])['debug_enabled'] ?? false;
    return $main_debug || $art_debug;
}

// Log to plugin's debug.txt (NOT wp-content/debug.log)
amelia_cpt_sync_debug_log('ART: Message here');
```

### 4. Database Patterns

**No Foreign Keys** (user decision):
- Simpler for development/debugging
- PHP handles cascade deletes
- Easier database portability

**Schema Versioning**:
```php
$current_version = get_option('art_db_version', '0');
if (version_compare($current_version, '1.0.0', '<')) {
    // Run migrations
    update_option('art_db_version', '1.0.0');
}
```

---

## 📁 FILE STRUCTURE & KEY FILES

### Core ART Module Files (all in `includes/`)
```
class-art-database-manager.php          - DB table creation, CRUD helpers
class-art-admin-settings.php            - Global settings, API config, cache
class-art-form-config-manager.php       - Per-form configs (wp_options)
class-art-form-parser.php               - Interface for parsers
class-art-jetformbuilder-parser.php     - JFB-specific parser (regex)
class-art-hook-handler.php              - Form submission handler ⭐ KEY FILE
```

### Templates (in `templates/`)
```
art-triage-forms-page.php               - Main forms management UI
art-mapping-table.php                   - Mapping table component
art-admin-settings-page.php             - Global settings UI (future)
```

### Documentation (organized)
```
ART-MODULE-TRACKER.md                   - Main tracker, all phases
HANDOFF-SESSION-NOV-11-2024.md          - This file

dev-resources/
├── Amelia API documentation.md         - Full API docs (8453 lines)
├── Summary amelia API.md               - API index
├── amelia-api-notes.md                 - Quick ref
├── JFB parser code from other plugin as refrence.md
├── FIELD-MAPPING-EXPLAINED.md          - User guide
├── PHASE-2-PLAN.md                     - Current phase + fixes
├── ui-mockup-*.html                    - Design mockups
└── UI-DESIGN-NOTES.md                  - Design analysis
```

---

## 🔧 MY FILE UPDATE APPROACH

### 1. Read Before Edit
```bash
# Always read files first to understand context
read_file → grep (to find pattern) → search_replace (targeted change)
```

### 2. Use search_replace for Most Edits
```php
// ✅ Preferred: Targeted replacements
search_replace(
    file_path: "includes/class-foo.php",
    old_string: "exact code to replace\n    with proper indentation",
    new_string: "updated code\n    maintaining indentation"
)
```

**Rules**:
- Include 3-5 lines of context before/after the change
- Match EXACT indentation (tabs vs spaces matter!)
- One change per call (unique old_string)

### 3. Use grep to Find Patterns
```bash
# Find all occurrences of a pattern
grep(pattern: "function_name", path: "includes/", -B: 2, -A: 5)
```

### 4. Use codebase_search for Discovery
```bash
# When you don't know the exact text
codebase_search(query: "How does form validation work?", target: ["includes/"])
```

### 5. Use write Only for New Files
```bash
# NEVER use write() to edit existing files - use search_replace
write(file_path: "new-file.php", contents: "...")
```

### 6. Always Check Lints
```php
read_lints(paths: ["includes/class-that-i-edited.php"])
// Fix any errors before moving on
```

### 7. Multiple Independent Changes = Parallel Calls
```bash
# If editing 3 unrelated files, make 3 parallel calls in one batch
# ✅ Faster, more efficient
read_file(file1) + read_file(file2) + read_file(file3) in parallel
```

---

## 🚀 QUICK START FOR NEXT SESSION

### Immediate Context
```
Branch: request-module
Last commit: (check git log)
Phase: 2 complete, moving to 3
Status: Validation working ✅, duplicates fixed ✅
```

### First Thing to Do
1. **Test validation** - Ensure exception still works after any changes
2. **Implement label in errors** - See "Quick Win" section below
3. **Start Phase 3** - Workbench list view (see ART-MODULE-TRACKER.md)

### Quick Win: Better Error Messages (15 min task)

**Current Issue**: Errors show destination field names  
`"Missing required field: request.service_id_source"`

**Goal**: Show user-friendly labels  
`"Missing required field: Vehicle Selection"`

**What to Change**:

**File 1**: `templates/art-triage-forms-page.php` (line ~60-68)
```php
// OLD (simple mapping):
$config_data['mappings'][sanitize_text_field($field_id)] = $dest;

// NEW (enhanced mapping with label):
$config_data['mappings'][sanitize_text_field($field_id)] = [
    'destination' => $dest,
    'label' => sanitize_text_field($_POST['field_labels'][$field_id] ?? $field_id)
];
```

**File 2**: `templates/art-mapping-table.php` (line ~80)
```php
// Add hidden field to pass label:
<input type="hidden" name="field_labels[<?php echo esc_attr($field_id); ?>]" 
       value="<?php echo esc_attr($field_name); ?>">
```

**File 3**: `includes/class-art-hook-handler.php` (~line 200-250)
```php
// In validate_data(), when checking critical fields:
// OLD:
$error = "Missing required field: {$destination}";

// NEW:
$mapping_info = $config['mappings'][$jfb_field] ?? [];
$label = is_array($mapping_info) ? $mapping_info['label'] : $jfb_field;
$error = "Missing required field: {$label}";
```

**Also Update**: Everywhere that reads `$config['mappings']` to handle both formats:
```php
// Backwards compatible:
$destination = is_array($mapping) ? $mapping['destination'] : $mapping;
$label = is_array($mapping) ? $mapping['label'] : $jfb_field;
```

---

## 🗺️ KEY CODE PATTERNS

### Pattern 1: Multi-Form Config Management
```php
// Get all configs
$all = get_option('amelia_cpt_sync_art_form_configs', ['forms' => []]);

// Save specific form
$all['forms'][$form_id] = $config_data;
update_option('amelia_cpt_sync_art_form_configs', $all);

// Structure:
[
    'forms' => [
        'service_request_form' => [
            'label' => 'Service Request',
            'hook_name' => 'art_service_hook',
            'mappings' => [...],
            'logic' => [...],
            'critical_fields' => [...],
            'intake_fields' => [...]
        ]
    ]
]
```

### Pattern 2: Dynamic Hook Registration
```php
// In class-art-hook-handler.php init():
$configs = $this->config_manager->get_configurations();
foreach ($configs['forms'] as $form_id => $config) {
    $hook_name = $config['hook_name'];
    add_filter(
        "jet-form-builder/custom-filter/{$hook_name}",
        function($result, $request, $handler) use ($form_id) {
            return $this->handle_form_submission($form_id, $result, $request, $handler);
        },
        10,
        3
    );
}
```

### Pattern 3: Validation with Critical Fields
```php
$validation_mode = $config['logic']['validation_mode'];
$critical_destinations = $config['critical_fields']; // ['request.service_id_source', ...]
$errors = [];

// Check each critical field
foreach ($critical_destinations as $destination) {
    [$bucket, $field] = explode('.', $destination, 2);
    
    if ($validation_mode === 'require_pass_through') {
        // STRICT: Missing or invalid = fail
        if (!isset($buckets[$bucket][$field]) || empty($buckets[$bucket][$field])) {
            $errors[] = "Missing required field: {$field}";
        }
    }
}

// Fail form if errors in strict mode
if ($validation_mode === 'require_pass_through' && !empty($errors)) {
    throw new \Jet_Form_Builder\Exceptions\Action_Exception(implode(', ', $errors));
}
```

### Pattern 4: Database Operations
```php
// Using dbDelta for table creation (handles create/update):
require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
dbDelta($sql);

// Using $wpdb for queries:
global $wpdb;
$table = $wpdb->prefix . 'art_requests';
$wpdb->insert($table, $data, $format);
$id = $wpdb->insert_id;

// Find or create pattern:
$existing = $wpdb->get_var($wpdb->prepare(
    "SELECT id FROM {$table} WHERE email = %s",
    $email
));
if ($existing) return $existing;
// else create...
```

---

## 🗄️ DATABASE SCHEMA QUICK REF

### Table: wp_art_customers
```sql
id (PK), first_name, last_name, email (unique), phone,
amelia_customer_id (nullable), created_at, updated_at
```

### Table: wp_art_requests  ⭐ MAIN TABLE
```sql
id (PK), customer_id, form_config_id, status (default: Requested),
service_id_source, location_id, persons, start_datetime, end_datetime,
final_price, submitted_at, last_status_change
```

### Table: wp_art_intake_fields
```sql
id (PK), request_id, field_name, field_value
-- One row per intake field per request
```

### Table: wp_art_booking_links
```sql
id (PK), request_id, amelia_booking_id, amelia_appointment_id,
linked_at, booking_status
-- Created when admin books via Amelia API
```

### Table: wp_art_request_notes
```sql
id (PK), request_id, user_id, note_text, created_at
-- Admin notes on triage requests
```

---

## 🎨 UI/UX PATTERNS

### Select2 Initialization
```javascript
jQuery(document).ready(function($) {
    $('.my-select2').select2({
        placeholder: '-- Select --',
        allowClear: true,
        width: 'resolve'
    });
});
```

### WordPress Admin Notices
```php
add_settings_error(
    'art_forms',          // Settings slug
    'unique_code',        // Error code
    'Message here',       // Message
    'success'             // Type: success, error, warning, info
);

// Display:
settings_errors('art_forms');
```

### AJAX Pattern (for future)
```javascript
jQuery.post(ajaxurl, {
    action: 'art_my_action',
    nonce: artData.nonce,
    data: payload
}, function(response) {
    if (response.success) {
        // Handle success
    }
});
```

```php
add_action('wp_ajax_art_my_action', 'callback');
function callback() {
    check_ajax_referer('art_nonce', 'nonce');
    wp_send_json_success($data);
}
```

---

## ⚠️ GOTCHAS TO REMEMBER

### 1. Windows Paths
```php
// User's workspace has spaces and special chars:
// C:\Users\Luca's PC\OneDrive\Documents\00 - Code Projects\Amelia CPT Sync\

// Always use proper escaping in PowerShell commands
// Use forward slashes when possible
```

### 2. Amelia API Auth
```php
// Uses custom header, NOT standard Bearer token:
$headers = [
    'Amelia' => $api_key,  // NOT 'Authorization: Bearer'
    'Content-Type' => 'application/json'
];
```

### 3. JetFormBuilder Field Parsing
```php
// Regex pattern for Gutenberg block comments:
$pattern = '/<!--\s*wp:jet-forms\/([a-zA-Z0-9\-]+)\s+({.*?})\s*\/-->/s';

// Extract 'name' and 'label' from JSON attributes
// name = field ID, label = display label
```

### 4. Critical Fields are Destination-Based
```php
// ❌ WRONG: Don't store JFB field IDs in critical_fields
$config['critical_fields'] = ['vehicle', 'email'];

// ✅ CORRECT: Store destination field paths
$config['critical_fields'] = ['request.service_id_source', 'customer.email'];

// Why: One JFB field can map to multiple destinations
```

### 5. DateTime Format
```php
// Store in MySQL format (UTC):
$datetime = gmdate('Y-m-d H:i:s', strtotime($user_input));

// Validate format:
if (preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}(:\d{2})?)?$/', $value)) {
    // Valid
}
```

### 6. Transients vs Options
```php
// ✅ Use Transients for API Cache (auto-expire):
set_transient('art_amelia_services', $data, $duration);
$cached = get_transient('art_amelia_services');

// ✅ Use Options for Persistent Config:
update_option('amelia_cpt_sync_art_form_configs', $data);
```

---

## 🧪 TESTING CHECKLIST

### Validation Testing
- [ ] Submit form with all fields → Success, 1 DB entry
- [ ] Submit without critical field (strict mode) → Error, 0 DB entries
- [ ] Submit without critical field (forgiving mode) → Success, 1 DB entry (field NULL)
- [ ] Submit with invalid email → Error message shows
- [ ] Check debug.txt for detailed logs

### Form Config Testing
- [ ] Create new form config → Saves correctly
- [ ] Edit existing config → Changes persist after refresh
- [ ] Upload JSON → Fields populate in mapping table
- [ ] Delete config → Removes from list
- [ ] Multiple form configs → Each has unique hook

### Database Testing
```sql
-- Check tables exist:
SHOW TABLES LIKE 'wp_art_%';

-- Check customer created:
SELECT * FROM wp_art_customers ORDER BY id DESC LIMIT 5;

-- Check request created:
SELECT * FROM wp_art_requests ORDER BY id DESC LIMIT 5;

-- Check intake fields saved:
SELECT * FROM wp_art_intake_fields WHERE request_id = ?;
```

---

## 📋 PHASE 3 PREVIEW

### Next: Workbench List View
**Files to Create**:
- `templates/art-workbench-page.php` - Main list view
- `includes/class-art-request-manager.php` - CRUD for requests
- `assets/css/art-workbench.css` - Styling

**Features to Build**:
1. Table listing all triage requests
2. Status filter chips (Requested, Responded, Tentative, Booked, Abandoned)
3. Search/filter by customer, date, form
4. Pagination
5. Click row → Detail view (Phase 4)

**Design Reference**: `dev-resources/ui-mockup-list-view.html`

---

## 🔗 USEFUL COMMANDS

### Git
```bash
# Check status
git status

# Commit changes
git add .
git commit -m "Phase 2 complete: Validation fixes + label enhancement"

# Push to branch
git push -u origin request-module

# Create new branch for Phase 3
git checkout -b phase-3-workbench
```

### WordPress
```php
// Enable WP_DEBUG (wp-config.php):
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);  // Logs to wp-content/debug.log
define('WP_DEBUG_DISPLAY', false);

// Plugin debug (uses plugin's debug.txt):
// Go to Amelia Expansion Suite → ART Settings → Debug Mode
```

### Database
```bash
# Via WP-CLI:
wp db query "SELECT * FROM wp_art_requests LIMIT 5"

# Via phpMyAdmin or Adminer:
# Look for wp_art_* tables
```

---

## 📞 HANDOFF NOTES

### What User Confirmed
- ✅ Validation is working (throws exception correctly)
- ✅ No more duplicates (single entry per submission)
- ✅ Ready to improve error messages with labels
- ✅ Documentation cleanup is good (removed redundant files)

### User's Next Request
> "Make error messages use the form field label instead of destination field"

**Status**: Documented as "Quick Win" above, ready to implement

### User Feedback on Process
- ✅ Likes consolidated documentation (one tracker, organized dev-resources)
- ✅ Wants periodic version number updates (done: now v2.0.0)
- ✅ Appreciates detailed explanations (FIELD-MAPPING-EXPLAINED.md)

---

## 🎓 SESSION INSIGHTS

### What Worked Well
1. **Iterative debugging** - Test → Fix → Test → Fix worked great
2. **Reference code** - User's WC plugin JFB integration was invaluable
3. **Comprehensive planning** - Tracker + phase plans kept us organized
4. **Parallel tool calls** - Reading multiple files at once saved time

### What Took Longer Than Expected
1. **JFB exception discovery** - Wrong namespace cost us time
2. **Form persistence debugging** - Form nesting issue was subtle
3. **Critical field logic** - Ensuring checkbox values update correctly

### Architectural Wins
1. **Multi-form design** - Like popup manager, scales well
2. **Parser abstraction** - Interface allows multiple form builders later
3. **Validation modes** - Flexibility for different deployment stages
4. **Flat config structure** - Easier to serialize/deserialize

---

## 📚 KEY FILES TO READ FIRST (Next Session)

### Must Read (in order):
1. `ART-MODULE-TRACKER.md` - Overall context
2. `includes/class-art-hook-handler.php` - Core submission logic
3. `templates/art-triage-forms-page.php` - UI patterns
4. This file (`HANDOFF-SESSION-NOV-11-2024.md`)

### Reference as Needed:
- `dev-resources/FIELD-MAPPING-EXPLAINED.md` - For user questions
- `dev-resources/Amelia API documentation.md` - For Phase 4 (API booking)
- `dev-resources/ui-mockup-*.html` - For Phase 3/4 UI

---

## ✅ FINAL STATUS

```
✅ Phase 1: Database & Foundation - COMPLETE
✅ Phase 2: Form Capture & Mapping - COMPLETE
🔜 Phase 3: Workbench List View - READY TO START
⏸️ Phase 4: Detail View & Booking - Blocked by Phase 3
⏸️ Phase 5: API Integration - Blocked by Phase 4
⏸️ Phase 6: Polish & Launch - Blocked by Phase 5
```

**Ready for**: Label enhancement (15 min) → Phase 3 kickoff  
**Branch**: `request-module`  
**No blockers**: All dependencies resolved  
**User**: Happy with progress, ready to continue 🚀

---

**End of Handoff Document**  
*Next AI: You've got this! Everything you need is above. Start with the "Quick Win" for immediate value, then dive into Phase 3.* 🎯
