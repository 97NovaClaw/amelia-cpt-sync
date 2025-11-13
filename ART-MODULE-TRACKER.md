# 🎯 ART Module Development Tracker
## Amelia Request Triage (ART) Module

**Plugin Name Change**: Amelia Expansion Suite  
**Development Start**: November 2025 
**Current Phase**: Phase 3 Complete ✅ - Ready for Phase 4  
**Target Completion**: 4-6 weeks (phased approach)

---

## 📋 Table of Contents
1. [Module Overview](#module-overview)
2. [Key Decisions & Requirements](#key-decisions--requirements)
3. [Architecture & Structure](#architecture--structure)
4. [Phase-Based Implementation Plan](#phase-based-implementation-plan)
5. [Security Checklist](#security-checklist)
6. [API Integration Specifications](#api-integration-specifications)
7. [Database Schema](#database-schema)
8. [Future Enhancements (Backlog)](#future-enhancements-backlog)
9. [Testing Checklist](#testing-checklist)

---

## 🎯 Module Overview

### Purpose
Add an intelligent lead-triage workbench to Amelia that:
- Captures form submissions from WordPress form builders (JetFormBuilder primary)
- Creates "Triage Requests" in WordPress admin
- Allows admin to review requests, check Amelia availability, and book appointments
- Provides real-time API integration with Amelia for slots and booking

### Core Value Proposition
Bridge the gap between generic form builders and Amelia's booking system, enabling custom intake workflows with manual approval before booking.

---

## 🔑 Key Decisions & Requirements

### ✅ Plugin Architecture Decisions

| Decision | Rationale | Status |
|----------|-----------|--------|
| **Keep as integrated module** | Shared codebase, same admin context | ✅ Decided |
| **Rename to "Amelia Expansion Suite"** | Reflects broader functionality | ⏳ Pending rename |
| **Use `ART_` prefix for new code** | Namespace isolation, clear module boundary | ✅ Decided |
| **Match existing OOP structure** | Consistency with current plugin architecture | ✅ Decided |
| **Use established debug logging** | Reuse `amelia_cpt_sync_debug_log()` | ✅ Decided |

### ✅ User & Deployment Context

| Factor | Decision | Impact |
|--------|----------|--------|
| **Primary User** | Developer (you) only for now | Simplifies UX/UI decisions |
| **Amelia Version** | Ultimate/Pro (lifetime subscription) | Full API access available |
| **WordPress Requirements** | Match existing (WP 5.0+, PHP 7.2+) | No new dependencies |
| **Rollback Strategy** | Plugin versioning + ManageWP backups | Sufficient for single-user |
| **Activation** | Auto-enabled for all installations | No opt-in/opt-out needed |

### ✅ API Integration Decisions

| Aspect | Implementation | Notes |
|--------|----------------|-------|
| **API Key Storage** | `wp_options` table (unencrypted) | Standard WordPress practice |
| **Authentication Method** | Custom header `Amelia: {api_key}` | Not Bearer token |
| **API Transport** | `wp_remote_get()` / `wp_remote_post()` | WordPress HTTP API |
| **Error Handling** | Existing debug log system | Log all API failures |
| **Caching Strategy** | Optional via admin settings | Similar to existing approach |
| **API Base URL** | Admin configurable setting | Default to site URL |

**API Key Location**: Settings page under new "ART Settings" submenu

### ✅ Form Builder Strategy

| Form Builder | Priority | Status |
|--------------|----------|--------|
| **JetFormBuilder** | Primary (v1.0) | ⏳ To Implement |
| **Gravity Forms** | Future consideration | 📋 Backlog |
| **Other builders** | As needed | 📋 Backlog |

**Strategy**: Use abstraction layer for form parsing to support future builders without refactoring core logic.

### ✅ Timezone Handling

| Aspect | Implementation |
|--------|----------------|
| **Storage** | UTC in database |
| **Display** | WordPress timezone setting |
| **Form Input** | Conversion settings per field |
| **Assumption** | WP timezone === Amelia timezone (user responsibility) |
| **Validation** | DateTime format validation on all datetime fields |

### ✅ Security Approach

| Layer | Implementation |
|-------|----------------|
| **AJAX Nonces** | All AJAX handlers require nonce verification |
| **Capabilities** | `manage_options` check on all admin operations |
| **Input Sanitization** | Type-specific per field (not generic `sanitize_text_field()`) |
| **Form Data** | Use JetFormBuilder's built-in sanitization + additional layer |
| **SQL Queries** | Prepared statements via `$wpdb->prepare()` |
| **API Keys** | HTTPS required, stored in wp_options |

---

## 🏗️ Architecture & Structure

### Class Structure (OOP - Match Existing Plugin)

```
includes/
├── class-art-handler.php                    // Main module init & hooks
├── class-art-admin-settings.php             // Settings page & UI
├── class-art-database-manager.php           // Database abstraction
├── class-art-form-parser.php                // Abstract form parser
├── class-art-jetformbuilder-parser.php      // JetFormBuilder implementation
├── class-art-hook-handler.php               // Form submission handler
├── class-art-api-manager.php                // Amelia API client
├── class-art-workbench.php                  // Workbench UI & AJAX
└── class-art-list-table.php                 // WP_List_Table for requests

templates/
└── art-workbench-page.php                   // Workbench UI template
└── art-settings-page.php                    // Settings UI template
```

### Integration Points

```php
// In main plugin file: amelia-cpt-sync.php

// Load ART module classes
require_once AMELIA_CPT_SYNC_PLUGIN_DIR . 'includes/class-art-handler.php';
// ... other ART classes

// Initialize ART module
function init_art_module() {
    $art_handler = new Amelia_CPT_Sync_ART_Handler();
    $art_handler->init();
}
add_action('plugins_loaded', 'init_art_module', 20); // After main plugin
```

---

## 📅 Phase-Based Implementation Plan

### **Phase 1: Foundation & Database** ✅ COMPLETE
**Goal**: Segit  

#### Tasks
- [x] Create database abstraction class (`Amelia_CPT_Sync_ART_Database_Manager`)
  - [x] Define table creation methods with proper indexes
  - [x] Skip foreign keys (handled in PHP with manual cascade methods)
  - [x] Implement `dbDelta()` for upgrades
  - [x] Add version tracking for schema migrations
- [x] Create **five** database tables:
  - [x] `art_customers` (with indexes on email, amelia_customer_id, created_at)
  - [x] `art_requests` (with indexes on customer_id, status_key, start_datetime, follow_up_by, created_at)
  - [x] `art_intake_fields` (with indexes on request_id, field_label)
  - [x] `art_booking_links` (with indexes on request_id, amelia_appointment_id, created_at)
  - [x] `art_request_notes` (with indexes on request_id, user_id, note_type, created_at) **NEW**
- [x] Create activation hook to run table creation
- [x] Build basic settings page structure
  - [x] Add "ART Settings" submenu under main plugin menu
  - [x] Create settings option group
  - [x] Add API credentials section (URL + API Key with show/hide toggle)
  - [x] Settings structured for multi-form support (global + forms array)
  - [x] Database status display showing version and table health
  - [x] Phase 2 placeholder note for form management
- [x] Integration with main plugin file (activation + initialization)
- [x] Helper methods for CRUD operations (find_or_create_customer, create_request, etc.)

**Success Criteria**:
✅ Tables created correctly on activation  
✅ Settings page accessible and saves data  
✅ No SQL errors in debug log  
✅ Proper WordPress table prefix handling

---

### **Phase 2: Form Capture & Mapping** ✅ COMPLETE
**Goal**: Implement form-to-database pipeline

#### Tasks
- [x] Create form parser abstraction
  - [x] Define `ART_Form_Parser` interface
  - [x] Implement JetFormBuilder parser (using proven regex from WC plugin)
  - [x] JSON extraction logic for field detection with encoding handling
- [x] Build mapping UI on settings page
  - [x] "Triage Forms" submenu page (separate from ART Settings)
  - [x] Forms list view with add/edit/delete
  - [x] File upload for form JSON export
  - [x] "Populate Mapping Table" button (separate form)
  - [x] Dynamic dropdown generation with Select2
  - [x] Save mapping to wp_options (per form config)
- [x] Implement intake field definitions repeater
  - [x] Add/remove buttons for dynamic field list
  - [x] JavaScript for repeater functionality
- [x] Create hook handler class (`Amelia_CPT_Sync_ART_Hook_Handler`)
  - [x] Register custom-filter hooks (one per form config)
  - [x] Read mapping configuration by hook name
  - [x] Parse form data into buckets (customer/request/intake)
  - [x] Implement post-processing logic:
    - [x] Service ID resolution (CPT vs direct)
    - [x] Duration calculation (start/end vs manual)
    - [x] Price handling (manual vs form)
    - [x] Location and persons handling
  - [x] Save to database tables (art_customers, art_requests, art_intake_fields)
- [x] Add comprehensive validation
  - [x] Two validation modes (pass_through_fails, require_pass_through)
  - [x] Per-config critical fields definition
  - [x] Email validation with is_email()
  - [x] Datetime format validation with multiple format support
  - [x] DateTime conversion to UTC for storage
  - [x] Numeric field validation
- [x] Form Config Manager with CRUD operations
- [x] Hook name uniqueness validation
- [x] API caching controls in global settings
- [x] Clear cache functionality

**Success Criteria**:
✅ Form JSON uploads and parses correctly  
✅ Mapping table populates with all fields  
✅ Form submission creates database records  
✅ All data properly sanitized and validated  
✅ Debug log shows successful flow

---

### **Phase 3: Workbench List View** (Week 3) ✅ COMPLETE
**Goal**: Build request management interface

**UI Reference**: See `dev-resources/ui-mockup-list-view.html` for design mockup

#### Tasks
- [ ] Create `WP_List_Table` subclass for requests
  - [ ] Define columns: Status, Customer, Service, Date, Follow-up
  - [ ] Implement pagination (25 per page)
  - [ ] Add sortable columns
  - [ ] Add search functionality (customer name/email)
  - [ ] Add status filter dropdown
  - [ ] Add bulk actions (future: bulk status change)
- [ ] Create "Triage Requests" submenu page
- [ ] Implement row actions
  - [ ] "View Details" link → workbench
  - [ ] "Mark Booked" quick action
  - [ ] "Delete" with confirmation
- [ ] Add request count badges in menu
- [ ] Style to match WordPress admin standards
- [ ] Test with 50+ sample records

**Success Criteria**:
✅ List table loads and paginates correctly  
✅ Search works across customer fields  
✅ Sorting works on all sortable columns  
✅ Performance acceptable with 100+ records  
✅ Mobile-responsive table

---

### **Phase 4: Workbench Detail View** (Week 4)
**Goal**: Build the 3-panel workbench interface

**UI Reference**: See `dev-resources/ui-mockup-detail-view.html` for design mockup

#### Tasks
- [ ] Create workbench page template
- [ ] **Panel 1: Customer & Intake Info**
  - [ ] Display customer data (read-only)
  - [ ] AJAX handler: Check Amelia customer match
  - [ ] Display intake fields as styled list
  - [ ] Add "Customer Notes" section
- [ ] **Panel 2: Booking Pillars Form**
  - [ ] Editable fields: service, location, persons, dates, price
  - [ ] Service dropdown (from Amelia services)
  - [ ] Location dropdown (from Amelia locations)
  - [ ] DateTime pickers with timezone display
  - [ ] Auto-calculate duration on date change (JS)
  - [ ] Display read-only duration_seconds
  - [ ] Save changes via AJAX
- [ ] **Panel 3: Availability Engine (Static UI)**
  - [ ] [Check Availability] button (disabled for now)
  - [ ] Checkbox: Force booking override
  - [ ] Empty results div
  - [ ] Disabled dropdowns: Provider, Slot
  - [ ] Disabled buttons: Tentatively Book, Book Now
- [ ] Add status change dropdown
- [ ] Add activity log section (empty for now)
- [ ] Style as 3-column responsive layout
- [ ] Test workbench load with real request data

**Success Criteria**:
✅ All panels render correctly  
✅ Form saves pillar changes via AJAX  
✅ Customer match check works  
✅ Duration auto-calculates correctly  
✅ Layout responsive on tablet/desktop  

---

### **Phase 5: API Integration** (Week 5)
**Goal**: Connect to Amelia API for availability and booking

#### Tasks
- [ ] Create API Manager class (`Amelia_CPT_Sync_ART_API_Manager`)
  - [ ] Base request method with header injection
  - [ ] Error handling wrapper
  - [ ] Response validation
  - [ ] Debug logging for all requests/responses
- [ ] Implement API endpoints:
  - [ ] `GET /users/customers` (customer search)
  - [ ] `GET /entities` (get providers for service)
  - [ ] `GET /slots` (check availability)
  - [ ] `POST /bookings` (create booking)
  - [ ] `POST /appointments/status/{id}` (set tentative)
- [ ] Add caching for provider lists (1 hour transient)
- [ ] Implement availability checker AJAX handler
  - [ ] Fetch current pillars from form
  - [ ] Call GET /entities
  - [ ] Call GET /slots for each provider
  - [ ] Parse response into structured data
  - [ ] Return available providers + suggestions
- [ ] Enable Panel 3 UI:
  - [ ] Populate provider dropdown on availability check
  - [ ] Populate slot dropdown with available times
  - [ ] Enable booking buttons
- [ ] Implement booking AJAX handler
  - [ ] Build booking payload
  - [ ] Include inline customer data
  - [ ] Override price and duration
  - [ ] Bundle intake notes into customFields
  - [ ] Handle success: save to art_booking_links
  - [ ] Handle tentative: call status API
  - [ ] Update request status
- [ ] Add error handling for:
  - [ ] Network timeouts
  - [ ] API errors (500, 400, etc.)
  - [ ] Invalid service/location IDs
  - [ ] Slot becomes unavailable during booking
  - [ ] Rate limiting (future consideration)
- [ ] Test end-to-end booking flow

**Success Criteria**:
✅ Availability check returns accurate results  
✅ All API errors logged to debug log  
✅ Booking creates appointment in Amelia  
✅ art_booking_links table populated correctly  
✅ Request status updates to "booked"  
✅ Can view booked appointment in Amelia admin

---

### **Phase 6: Polish & Testing** (Week 6)
**Goal**: Refinement, edge cases, and documentation

#### Tasks
- [ ] Add loading spinners for all AJAX operations
- [ ] Add success/error notifications (WordPress admin notices)
- [ ] Implement customer history view (future feature scaffold)
- [ ] Add audit trail for request status changes
- [ ] Build admin notice for long-running availability checks
  - [ ] "This may take 5-10 seconds with multiple providers"
- [ ] Add form field validation in workbench
- [ ] Implement "force booking" override logic
- [ ] Add settings field: Enable/disable caching
- [ ] Add settings field: Cache duration (minutes)
- [ ] Create comprehensive debug logging
- [ ] Test all edge cases:
  - [ ] Service deleted in Amelia (orphaned request)
  - [ ] Invalid datetime formats
  - [ ] Missing required fields
  - [ ] API key invalid/expired
  - [ ] Network failure during booking
- [ ] Update main plugin documentation
- [ ] Create ART module documentation
- [ ] Record video walkthrough of workflow

**Success Criteria**:
✅ All error states handled gracefully  
✅ UI provides clear feedback for all actions  
✅ Documentation complete and accurate  
✅ Zero PHP warnings/errors in debug log  
✅ Performance acceptable with real-world data

---

## 🔒 Security Checklist

### AJAX Handlers
- [ ] All handlers verify nonce: `check_ajax_referer('art_nonce', 'nonce')`
- [ ] All handlers check capability: `current_user_can('manage_options')`
- [ ] All handlers validate/sanitize input before use
- [ ] All handlers use `wp_send_json_success()` / `wp_send_json_error()`

### Input Sanitization Rules
| Field Type | Sanitization Function | Notes |
|-----------|----------------------|-------|
| Email | `sanitize_email()` | Then validate with `is_email()` |
| Phone | `sanitize_text_field()` | Then custom regex validation |
| Names | `sanitize_text_field()` | Strip tags |
| DateTime | Custom validation | Regex: `^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$` |
| Service ID | `absint()` | Integer only |
| Price | `floatval()` | Then check > 0 |
| Text areas | `sanitize_textarea_field()` | Preserve line breaks |
| HTML content | `wp_kses_post()` | If rich text allowed |

### SQL Query Safety
- [ ] All custom queries use `$wpdb->prepare()` for variables
- [ ] No direct variable interpolation in SQL
- [ ] Foreign key constraints in place
- [ ] ON DELETE CASCADE for cleanup

### API Security
- [ ] API key never sent in GET parameters (only headers)
- [ ] HTTPS enforced for API calls
- [ ] API responses validated before use
- [ ] No API keys in debug logs (mask with `substr($key, 0, 8) . '...'`)

---

## 🔌 API Integration Specifications

### API Authentication
```php
// All API calls must include this header:
'Amelia' => get_option('art_api_key')
```

### Base URL
```php
$api_base = get_option('art_api_base_url', site_url());
// Typically: https://yoursite.com/wp-admin/admin-ajax.php
```

### Endpoints to Implement

#### 1. Customer Search
```
GET /users/customers?search={email}
Headers: Amelia: {api_key}
Response: { customers: [{id, firstName, lastName, email}] }
```

#### 2. Get Providers for Service
```
GET /entities?types[]=employee&serviceId={id}
Headers: Amelia: {api_key}
Response: { employees: [{id, firstName, lastName}] }
```

#### 3. Check Availability
```
GET /slots?serviceId={id}&providerIds[]={id}&locationId={id}&persons={num}&serviceDuration={seconds}
Headers: Amelia: {api_key}
Response: { data: { slots: [{time, providers: []}] } }
```

#### 4. Create Booking
```
POST /bookings
Headers: Amelia: {api_key}, Content-Type: application/json
Body: {
  "bookings": [{
    "customer": {
      "firstName": "...",
      "lastName": "...",
      "email": "...",
      "phone": "..."
    },
    "serviceId": 123,
    "providerId": 45,
    "locationId": 67,
    "bookingStart": "2024-11-15 10:00:00",
    "persons": 2,
    "customFields": {...},
    "payment": {
      "amount": 150.00
    }
  }]
}
```

#### 5. Set Appointment to Tentative
```
POST /appointments/status/{appointment_id}
Headers: Amelia: {api_key}, Content-Type: application/json
Body: { "status": "pending" }
```

### Error Handling Strategy

| HTTP Code | Meaning | Action |
|-----------|---------|--------|
| 200 | Success | Process response |
| 400 | Bad Request | Log payload, show error to admin |
| 401 | Unauthorized | Show "Invalid API key" error |
| 404 | Not Found | Show "Service/Location not found" |
| 429 | Rate Limited | Show "Too many requests, try again" |
| 500 | Server Error | Log error, show "Amelia error, check logs" |
| Timeout | Network issue | Show "Connection failed, retry" |

### Caching Strategy

```php
// Provider list cache (1 hour default)
$cache_key = 'art_providers_' . $service_id;
$providers = get_transient($cache_key);

if (false === $providers && get_option('art_enable_caching', true)) {
    $providers = // ... API call
    $duration = get_option('art_cache_duration', 60) * MINUTE_IN_SECONDS;
    set_transient($cache_key, $providers, $duration);
}
```

**Cache Invalidation**: Provide "Clear Cache" button on settings page.

---

## 🗄️ Database Schema

### Table 1: `art_customers`
```sql
CREATE TABLE {prefix}art_customers (
  id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  first_name varchar(255) NOT NULL,
  last_name varchar(255) NOT NULL,
  email varchar(255) NOT NULL,
  phone varchar(50) DEFAULT NULL,
  amelia_customer_id bigint(20) DEFAULT NULL,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY email (email),
  KEY amelia_customer_id (amelia_customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table 2: `art_requests`
```sql
CREATE TABLE {prefix}art_requests (
  id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id bigint(20) UNSIGNED NOT NULL,
  status_key varchar(50) NOT NULL DEFAULT 'requested',
  
  -- Pillar fields for API
  service_id bigint(20) DEFAULT NULL,
  location_id bigint(20) DEFAULT NULL,
  persons int(11) DEFAULT 1,
  start_datetime datetime DEFAULT NULL,
  end_datetime datetime DEFAULT NULL,
  duration_seconds int(11) DEFAULT 0,
  final_price decimal(10,2) DEFAULT NULL,
  final_provider_id bigint(20) DEFAULT NULL,
  
  -- Timestamps
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  follow_up_by datetime DEFAULT NULL,
  last_activity_at datetime DEFAULT NULL,
  responded_at datetime DEFAULT NULL,
  tentative_at datetime DEFAULT NULL,
  booked_at datetime DEFAULT NULL,
  
  PRIMARY KEY (id),
  KEY customer_id (customer_id),
  KEY status_key (status_key),
  KEY start_datetime (start_datetime),
  KEY follow_up_by (follow_up_by),
  FOREIGN KEY (customer_id) REFERENCES {prefix}art_customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table 3: `art_intake_fields`
```sql
CREATE TABLE {prefix}art_intake_fields (
  id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  request_id bigint(20) UNSIGNED NOT NULL,
  field_label varchar(255) NOT NULL,
  field_value text DEFAULT NULL,
  PRIMARY KEY (id),
  KEY request_id (request_id),
  FOREIGN KEY (request_id) REFERENCES {prefix}art_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table 4: `art_booking_links`
```sql
CREATE TABLE {prefix}art_booking_links (
  id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  request_id bigint(20) UNSIGNED NOT NULL,
  amelia_appointment_id bigint(20) NOT NULL,
  amelia_booking_id bigint(20) DEFAULT NULL,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY request_id (request_id),
  KEY amelia_appointment_id (amelia_appointment_id),
  FOREIGN KEY (request_id) REFERENCES {prefix}art_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Schema Version Tracking
```php
// In activation hook:
add_option('art_db_version', '1.0.0');

// In upgrade check:
$current_version = get_option('art_db_version', '0.0.0');
if (version_compare($current_version, '1.1.0', '<')) {
    // Run migration for 1.1.0
    update_option('art_db_version', '1.1.0');
}
```

---

## 📋 Future Enhancements (Backlog)

### Priority: Low (Post v1.0)
- [ ] **Service deletion handling**: Detect orphaned requests when service deleted in Amelia
- [ ] **Booking cancellation sync**: Webhook listener to sync cancellations from Amelia → ART
- [ ] **Undo functionality**: Allow admin to "undo" a booking (delete appointment + reset status)
- [ ] **Audit log**: Track all admin actions with timestamps and user info
- [ ] **Customer cancellation workflow**: Add "Cancelled by Customer" status with email template
- [ ] **Customer history view**: See all requests/bookings for a single customer
- [ ] **Multiple form builder support**: Add Gravity Forms and WPForms parsers
- [ ] **Email notifications**: Notify customer when request is booked/tentative
- [ ] **SMS integration**: Optional Twilio integration for SMS updates
- [ ] **Payment integration**: Track payment status linked to bookings
- [ ] **Recurring requests**: Handle multi-day or recurring booking requests
- [ ] **Team permissions**: Allow non-admin users to access workbench with limited permissions
- [ ] **Custom status workflow**: Define custom statuses beyond requested/tentative/booked
- [ ] **Request templates**: Save common request patterns as templates
- [ ] **Bulk operations**: Bulk status changes, bulk delete, bulk export

### Priority: Medium (v1.5)
- [ ] **Advanced caching**: Cache slots API results with smart invalidation
- [ ] **Rate limiting protection**: Throttle API calls to prevent overload
- [ ] **API version compatibility**: Support multiple Amelia API versions
- [ ] **Export functionality**: Export requests to CSV/Excel
- [ ] **Import functionality**: Bulk import requests from spreadsheet
- [ ] **Dashboard widget**: Show pending requests count on WP dashboard
- [ ] **Mobile app**: PWA for checking/managing requests on mobile

---

## ✅ Testing Checklist

### Unit Testing (Per Component)
- [ ] Database Manager: Table creation, CRUD operations
- [ ] Form Parser: Field extraction from JSON
- [ ] Hook Handler: Data bucketing and sanitization
- [ ] API Manager: All endpoint calls with mocked responses
- [ ] Workbench: AJAX handlers return expected data

### Integration Testing
- [ ] End-to-end: Form submit → Database → Workbench → API → Booking
- [ ] Error scenarios: Invalid API key, missing service, network timeout
- [ ] Edge cases: Extremely long names, special characters, timezone edge cases
- [ ] Performance: 100+ requests in list, 10+ providers in availability check
- [ ] Multisite compatibility: Test on WordPress multisite

### User Acceptance Testing (Manual)
- [ ] Submit test form, verify all data captured
- [ ] Open workbench, verify all panels display correctly
- [ ] Check availability, verify results accurate
- [ ] Book appointment, verify it appears in Amelia
- [ ] Mark as tentative, verify status in Amelia
- [ ] Test on mobile/tablet screen sizes
- [ ] Test with slow network (throttle to 3G)

### Security Testing
- [ ] Verify nonces on all AJAX endpoints
- [ ] Test with non-admin user (should fail gracefully)
- [ ] SQL injection attempts in all input fields
- [ ] XSS attempts in text fields
- [ ] CSRF protection on all forms

---

## 📚 Documentation Requirements

### User Documentation
- [ ] Installation instructions (part of main README)
- [ ] ART module setup guide (new doc: `ART-SETUP.md`)
- [ ] Form builder integration guide
- [ ] Workbench user guide with screenshots
- [ ] Troubleshooting section for common issues
- [ ] Video walkthrough of complete workflow

### Developer Documentation
- [ ] API integration guide (for extending)
- [ ] Database schema reference
- [ ] Class architecture diagram
- [ ] Hooks and filters reference (for future extensibility)
- [ ] Code examples for custom form parsers

---

## 🎬 Next Steps

**Immediate Actions**:
1. ✅ Review and approve this tracker
2. ⏳ Decide on plugin rename timing (can be post-implementation)
3. ⏳ Begin Phase 1: Database structure implementation
4. ⏳ Set up test site with sample Amelia data
5. ⏳ Gather sample JetFormBuilder export JSON for testing

**Before Starting Implementation**:
- [ ] Obtain Amelia API documentation (save to `dev-resources/amelia-api/`)
- [ ] Create test Amelia services and providers
- [ ] Generate API key from Amelia settings
- [ ] Export sample JetFormBuilder form JSON
- [ ] Review existing codebase patterns for consistency
- [x] UI mockups created (`ui-mockup-list-view.html` & `ui-mockup-detail-view.html`)

---

## 📝 Notes & Decisions Log

### 2024-11-11: Phase 3 Complete - Workbench List View Built!
- ✅ **Request Manager Class** - Full CRUD operations for triage requests
- ✅ **Workbench Page** - Beautiful list view matching Tailwind mockup aesthetic
- ✅ **Status Filter Chips** - All 5 statuses + "All" with counts (rounded-full pills)
- ✅ **Search Functionality** - Search by customer name or email with icon
- ✅ **Pagination** - Modern circular pagination buttons
- ✅ **Status Badges** - Exact color-coded badges from mockup (green=Booked, blue=Requested, etc.)
- ✅ **Responsive Layout** - Container query-style responsive (hides columns on mobile)
- ✅ **Menu Integration** - "Triage Requests" now first in ART submenu
- ✅ **Empty States** - Friendly messages when no results
- ✅ **Modern Design** - Tailwind-inspired: rounded corners, better spacing, clean typography
- ✅ **Clickable Customer Names** - Underlined links to detail view
- ✅ **Alternating Rows** - Subtle background for better readability

**Error Message Enhancement**:
- ✅ Validation now uses field labels instead of destination keys
- ✅ "Missing required field: Vehicle Selection" vs "request.service_id_source"
- ✅ Backwards compatible with old mapping format

**Database Schema Fixes**:
- ✅ Fixed column name mismatches (`status_key` not `status`, `service_id` not `service_id_source`)
- ✅ Updated Request Manager to query correct columns
- ✅ Fixed intake fields query (`field_label` not `field_name`)
- ✅ Enhanced `update_status()` to update timestamp columns (`responded_at`, `tentative_at`, `booked_at`)

**Key Decisions**:
- Workbench is primary page (moved to top of menu)
- **25 items per page default** (user can change: 5, 15, 25, 50, 100)
- Service names fetched from vehicles CPT (fallback to ID if not found)
- Per-page preference stored in **user meta** (persists per logged-in user)
- AJAX updates preference and reloads page smoothly
- "View Details" links to Phase 4 page (not built yet)
- Colors match Tailwind mockup exactly (#1A84EE primary, #F7F8FC background)
- Font weights: 900 for title, 600 for names, 500 for chips

**UX Enhancements (v2.0.3)**:
- ✅ Per-page dropdown with 5 options (5, 15, 25, 50, 100) - Default: 25
- ✅ Preference saves via AJAX to user meta (persists per user)
- ✅ Page reloads to page 1 when per-page changes
- ✅ Service name JOIN with vehicles CPT via _amelia_service_id meta
- ✅ Smart fallback: service_name → service_id → "—"
- ✅ Service filter dropdown (shows all unique services with names)
- ✅ **TWO date filters**: Submitted (created_at) AND Start Date (start_datetime)
- ✅ Phone number displays under customer (if available)
- ✅ Search includes service names
- ✅ Clear Filters button (red, shows when filters active)
- ✅ Fixed search icon overlap (!important padding)
- ✅ Fixed pagination height (proper ul/li styling)

### 2024-11-11: Phase 2 Complete + Validation Fixed
- ✅ **Form Config Manager** - Multi-form CRUD system like popup manager
- ✅ **JFB Parser** - Using proven regex from WC plugin reference
- ✅ **Hook Handler** - Dynamic registration, two validation modes
- ✅ **Triage Forms Page** - Complete add/edit/list UI
- ✅ **Mapping Table** - Select2 dropdowns with critical fields system
- ✅ **API Caching** - Enable/disable toggle with clear cache function
- ✅ **Plugin Renamed** - Now "Amelia Expansion Suite" v2.0.0

**Critical Fixes**:
- ✅ Validation now works correctly using `\Jet_Form_Builder\Exceptions\Action_Exception`
- ✅ Form data persistence fixed (single comprehensive form)
- ✅ Duplicate entries fixed (only register custom-filter, not action)

**Key Architectural Decisions**:
- One unique hook per form configuration (simple, reliable)
- Form configs stored in wp_options (not files)
- Use ONLY `custom-filter` hook type (can throw exceptions to fail form)
- Two validation modes: Pass Through Fails (forgiving) vs Require Pass Through (strict)
- Critical fields configurable per form
- Customer created in art_customers immediately (amelia_customer_id = NULL until booking)
- DateTime stored as UTC in MySQL format
- API Base URL: Full endpoint including `/api/v1` path
- Select2 for better UX on mapping dropdowns

**JFB Integration Notes**:
- Exception class: `\Jet_Form_Builder\Exceptions\Action_Exception` (NOT Actions!)
- Hook type: `jet-form-builder/custom-filter/{hook_name}` only
- Arguments: `$result, $request, $action_handler`

### 2024-11-08: Phase 1 Complete
- ✅ **Database Manager** created with 5 tables (added art_request_notes)
- ✅ **Settings Architecture** designed for multi-form support (global + forms array)
- ✅ **Minimal Settings Page** implemented with API credentials
- ✅ **Status Values** locked in: requested, responded, tentative, booked, abandoned
- ✅ **Foreign Keys** skipped - using PHP cascade methods instead
- ✅ **API Key Input** uses password field with show/hide toggle
- ✅ **Integration** complete with main plugin activation and initialization

**Key Architectural Decisions**:
- Settings structured like popup system to support multiple forms in future
- Each form configuration will have its own hook name, mappings, logic toggles
- Global settings (API key, base URL) shared across all forms
- Database tables include comprehensive indexes for performance

### 2024-11-07: Initial Planning
- Decided to keep as integrated module with ART_ prefix
- Confirmed API key storage in wp_options (standard WP practice)
- Timezone handling: Use WP timezone, assume sync with Amelia
- Security: JetFormBuilder sanitization + additional validation layer
- Form builders: Start with JFB, abstract for future expansion
- Single-user deployment simplifies many UX decisions

### Questions for Future Consideration
- Should we add a "Delete request when service deleted" auto-cleanup?
- How to handle when Amelia plugin is deactivated?
- Should API key be required, or gracefully degrade without it?

---

**Last Updated**: 2024-11-08  
**Current Phase**: Phase 2 Complete ✅ - Ready for Phase 3  
**Next Review**: Before starting Phase 3

