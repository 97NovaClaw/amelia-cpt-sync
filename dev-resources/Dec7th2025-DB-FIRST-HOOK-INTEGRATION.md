# Dec 7th 2025 - Database-First Architecture with Amelia Hook Integration

**Status:** Planning Document  
**Branch:** `dec25-refactor`  
**Goal:** Move ALL reads and writes to Direct Database queries, leverage Amelia hooks for integrations

---

## Executive Summary

This document outlines the complete refactor to eliminate API dependency while maintaining full Amelia integration (Google Calendar, SMS, Emails, Webhooks). The strategy: **Direct DB for data operations + Amelia hooks for business logic**.

---

## Core Principles

### 1. Amelia is the Master System
- Resources, Services, Providers, Locations are managed in Amelia's admin
- Appointments can be created via Amelia's backend, frontend, OR our Triage system
- Our plugin is an **interpreter and enhancer**, not a replacement
- `wp_amelia_*` tables are the source of truth

### 2. Database-First for Performance
- **Reads:** Always direct SQL (fast, reliable, no HTTP overhead)
- **Writes:** Direct SQL + Hook triggering (control + integration)
- **API:** Deprecated for routine operations (only for edge cases if needed)

### 3. Hook-Driven Integration
- Amelia's hooks are stable and well-documented
- Firing the correct hooks triggers all native Amelia features:
  - Email notifications
  - SMS notifications
  - Google Calendar sync
  - Outlook Calendar sync
  - Zoom meeting creation
  - Webhooks
  - Payment processing callbacks

### 4. Force Override Support
- Direct DB writes bypass Amelia's validation logic
- Enables booking when:
  - Provider not scheduled that day
  - Resource appears blocked (but admin wants to override)
  - Time slot outside normal operating hours
- Hooks ensure integrations still fire even for "forced" bookings

---

## Current State Analysis

### What We've Completed (Dec 7th)
✅ `ART_Time_Helper` - UTC Firewall enforcer  
✅ `ART_Amelia_Data_Manager` - Singleton for reading appointments, services (DB-first)  
✅ `ART_Availability_Engine` - Uses Data Manager for appointment reads  
✅ `ART_Resource_Manager` - Uses Data Manager for appointment reads  
✅ `ART_Booking_Orchestrator` - Mode 0 & 1 logic  
✅ 3-Column UI with Resource + Provider rendering  

### What Still Uses API (Problems)
❌ `ART_Resource_Manager::get_resource()` - calls `ART_Resource_API::get_resources()` (API)  
❌ `ART_Booking_Service::create_booking()` - calls API for writes  
❌ `ART_Booking_Service::update_appointment()` - calls API for writes  
❌ `ART_Booking_Service::delete_appointment()` - calls API for writes  

### What Broke (Dec 7th - Fixed)
🐛 `ART_Amelia_Data_Manager` was selecting non-existent `resources` column from `wp_amelia_appointments`  
✅ Fixed: Removed column, logic now works via `wp_amelia_resources_to_entities` join

---

## Database Schema (Amelia Core Tables)

### Appointments
**Table:** `wp_amelia_appointments`

```sql
CREATE TABLE wp_amelia_appointments (
  id INT(11) AUTO_INCREMENT PRIMARY KEY,
  status ENUM('approved', 'pending', 'canceled', 'rejected', 'no-show'),
  bookingStart DATETIME NOT NULL,
  bookingEnd DATETIME NOT NULL,
  notifyParticipants TINYINT(1) NOT NULL,
  serviceId INT(11) NOT NULL,
  providerId INT(11) NOT NULL,
  locationId INT(11) NULL,
  internalNotes TEXT,
  googleCalendarEventId VARCHAR(255),
  outlookCalendarEventId VARCHAR(255),
  zoomMeeting TEXT,
  parentId INT(11),
  error TEXT
)
```

**Key Insights:**
- NO `resources` column (resource tracking is via join table)
- `bookingStart`/`bookingEnd` stored in **UTC** (critical for our queries)
- `status` enum defines appointment lifecycle

### Customer Bookings (The "Who" of an appointment)
**Table:** `wp_amelia_customer_bookings`

```sql
CREATE TABLE wp_amelia_customer_bookings (
  id INT(11) AUTO_INCREMENT PRIMARY KEY,
  appointmentId INT(11) NOT NULL,
  customerId INT(11) NOT NULL,
  status ENUM('approved', 'pending', 'canceled', 'rejected'),
  price DECIMAL(10,2),
  persons INT(11),
  couponId INT(11),
  info TEXT  -- JSON with custom fields
)
```

**Key Insights:**
- Links customers to appointments (N-to-1 relationship)
- For standard 1-customer appointments, we insert 1 row here
- `info` field stores custom form data (JSON)

### Resources
**Table:** `wp_amelia_resources`

```sql
CREATE TABLE wp_amelia_resources (
  id INT(11) AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255),
  quantity INT(11),  -- How many instances exist
  shared TINYINT(1),
  status ENUM('visible', 'hidden'),
  countAdditionalPeople INT(11)
)
```

**Table:** `wp_amelia_resources_to_entities`

```sql
CREATE TABLE wp_amelia_resources_to_entities (
  id INT(11) AUTO_INCREMENT PRIMARY KEY,
  resourceId INT(11) NOT NULL,
  entityId INT(11) NOT NULL,      -- Service ID or Location ID or Provider ID
  entityType ENUM('service', 'location', 'provider')
)
```

**Example Data (from your DB):**
```
resourceId=1, entityId=12, entityType='service'
```
Meaning: Resource #1 is linked to Service #12.

**Critical Question:** How does Amelia track "which specific resource instance was assigned to an appointment"?
- **Answer:** Amelia likely does NOT track this at the granular level in its core tables.
- **Implication:** For Shared Pool mode, we MUST use our `wp_art_resource_assignments` table to track "Appointment #66 used Car #3 from the fleet."

---

## The Hook Integration Strategy

### Reading Amelia's Hook Documentation

The hooks fire in this sequence for a booking:

```
Frontend/Backend Booking Flow:
1. amelia_before_booking_added_filter (FILTER - can modify data)
2. amelia_before_booking_added (ACTION)
3. amelia_before_appointment_booking_saved_filter (FILTER)
4. amelia_before_appointment_booking_saved (ACTION)
   [DB INSERT HAPPENS HERE in Amelia's code]
5. amelia_after_appointment_booking_saved (ACTION)
6. amelia_after_booking_added (ACTION)
7. amelia_before_post_booking_actions (ACTION)
   [Emails, Calendar, Zoom happen here]
8. AmeliaAppointmentBookingAdded (ACTION - webhooks)
```

**Our Hybrid Flow (Force Override):**

```
Our Triage "Force Book" Flow:
1. Validate data in PHP
2. [DB INSERT - Direct wpdb->insert]
3. Build $appointment array (mimic Amelia's format)
4. do_action('amelia_after_appointment_added', $appointment, $service, $paymentData)
5. do_action('amelia_before_post_booking_actions', $resultData)
6. do_action('AmeliaAppointmentBookingAdded', $reservation, $bookings, $container)
```

We **skip** the "before" hooks because we've already written to the DB.
We **fire** the "after" hooks to trigger integrations.

### What $appointment Array Must Contain

Based on Amelia's expectations (inferred from hook signatures):

```php
$appointment = [
    'id' => 123,  // The ID we just inserted
    'bookingStart' => '2025-12-06 17:30:00',  // UTC
    'bookingEnd' => '2025-12-07 03:30:00',    // UTC
    'status' => 'approved',
    'serviceId' => 12,
    'providerId' => 13,
    'locationId' => 1,
    'notifyParticipants' => 1,
    'bookings' => [
        [
            'id' => 456,  // customer_booking ID
            'customerId' => 47,
            'customer' => [
                'id' => 47,
                'firstName' => 'John',
                'lastName' => 'Wick',
                'email' => 'john@example.com'
            ],
            'status' => 'approved',
            'price' => 1200.00,
            'persons' => 1
        ]
    ]
];

$service = [
    'id' => 12,
    'name' => 'Gabes stinger',
    'duration' => 3600
];

$paymentData = [
    'gateway' => 'onSite',
    'amount' => 1200.00
];
```

**Challenge:** We must fetch/construct this nested structure after our DB insert.

---

## Refactor Plan - Components to Update

### Phase 1: Direct DB Reads for Resources

**File:** `includes/class-art-amelia-data-manager.php`

**New Methods to Add:**

1.  **`get_resource($resource_id)`**
    ```sql
    SELECT r.*, 
           GROUP_CONCAT(
               CONCAT(rte.entityType, ':', rte.entityId) 
               SEPARATOR '|'
           ) AS entities
    FROM wp_amelia_resources r
    LEFT JOIN wp_amelia_resources_to_entities rte ON r.id = rte.resourceId
    WHERE r.id = %d
    GROUP BY r.id
    ```
    
    **Returns DTO:**
    ```php
    [
        'id' => 1,
        'name' => 'Gabes stinger (Resource)',
        'quantity' => 1,
        'shared' => false,
        'status' => 'visible',
        'entities' => [
            ['entity_type' => 'service', 'entity_id' => 12]
        ]
    ]
    ```

2.  **`get_all_resources()`**
    - Similar query without WHERE clause
    - Returns array of all resources with entities
    - Cache for 1 hour

3.  **`get_resources_for_service($service_id)`**
    ```sql
    SELECT r.*
    FROM wp_amelia_resources r
    JOIN wp_amelia_resources_to_entities rte ON r.id = rte.resourceId
    WHERE rte.entityType = 'service' AND rte.entityId = %d
    ```

**File:** `includes/class-art-resource-manager.php`

**Methods to Refactor:**
- `get_resource()` - Replace `$this->resource_api->get_resources()` with `$this->data_manager->get_resource()`
- `appointment_uses_resource()` - Update to use new DTO format

---

### Phase 2: Direct DB Writes for Bookings

**File:** `includes/class-art-booking-service.php`

**Current Structure (API-based):**
```php
public function create_booking($booking_data) {
    return $this->api_manager->api_request('/appointments', 'POST', $booking_data);
}
```

**New Structure (DB + Hooks):**
```php
public function create_booking($booking_data) {
    // Step 1: Validate & Prepare
    $validated = $this->validate_booking_data($booking_data);
    
    // Step 2: DB Inserts (Transaction)
    global $wpdb;
    $wpdb->query('START TRANSACTION');
    
    try {
        // Insert appointment
        $appointment_id = $this->db_insert_appointment($validated);
        
        // Insert customer booking
        $booking_id = $this->db_insert_customer_booking($appointment_id, $validated);
        
        // Commit
        $wpdb->query('COMMIT');
        
        // Step 3: Build hook payload
        $hook_data = $this->build_hook_payload($appointment_id, $booking_id, $validated);
        
        // Step 4: Fire Amelia hooks
        $this->trigger_amelia_hooks($hook_data);
        
        return [
            'appointment_id' => $appointment_id,
            'booking_id' => $booking_id,
            'success' => true
        ];
        
    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('db_error', $e->getMessage());
    }
}
```

**Helper Methods Needed:**

1.  **`db_insert_appointment($data)`**
    - Converts WP timezone to UTC
    - Inserts into `wp_amelia_appointments`
    - Returns `$wpdb->insert_id`

2.  **`db_insert_customer_booking($appointment_id, $data)`**
    - Links customer to appointment
    - Inserts into `wp_amelia_customer_bookings`
    - Returns `$wpdb->insert_id`

3.  **`build_hook_payload($appointment_id, $booking_id, $data)`**
    - Queries the DB to fetch the complete appointment record
    - Constructs the nested array structure Amelia expects
    - Includes customer data, service data, payment data

4.  **`trigger_amelia_hooks($payload)`**
    - Fires the sequence of hooks in correct order
    - Logs each hook for debugging

---

### Phase 3: Hook Trigger Class

**New File:** `includes/class-art-amelia-hook-trigger.php`

**Purpose:** Centralize all Amelia hook triggering logic.

**Methods:**

1.  **`trigger_appointment_added($appointment, $service, $payment)`**
    ```php
    public function trigger_appointment_added($appointment, $service, $payment) {
        amelia_cpt_sync_debug_log('ART Hook Trigger: Firing amelia_after_appointment_added');
        
        do_action('amelia_after_appointment_added', $appointment, $service, $payment);
        do_action('amelia_after_booking_added', $appointment);
        
        // Build result data for post-booking actions
        $result_data = $this->build_result_data($appointment);
        
        do_action('amelia_before_post_booking_actions', $result_data);
        
        // Trigger webhook-specific action
        $reservation = $this->build_reservation_object($appointment);
        $bookings = $appointment['bookings'];
        $container = null; // May need to investigate what this is
        
        do_action('AmeliaAppointmentBookingAdded', $reservation, $bookings, $container);
        
        amelia_cpt_sync_debug_log('ART Hook Trigger: All hooks fired successfully');
    }
    ```

2.  **`trigger_appointment_updated($appointment, $old_appointment)`**
    - Similar pattern for updates
    - Fires: `amelia_after_appointment_updated`

3.  **`trigger_appointment_deleted($appointment)`**
    - Fires: `amelia_after_appointment_deleted`

4.  **`build_result_data($appointment)`**
    - Constructs the complex nested structure that `amelia_before_post_booking_actions` expects
    - This is where we mimic what the API would have passed

---

## Database Query Specifications

### Resource Reads

**Query 1: Get Single Resource with Entities**
```sql
SELECT 
    r.id,
    r.name,
    r.quantity,
    r.shared,
    r.status,
    rte.id AS entity_link_id,
    rte.entityId,
    rte.entityType
FROM wp_amelia_resources r
LEFT JOIN wp_amelia_resources_to_entities rte ON r.id = rte.resourceId
WHERE r.id = %d
```

**Normalization Logic:**
```php
// Raw rows from query (might be multiple due to JOIN)
$rows = [
    ['id' => 1, 'name' => 'Car A', 'entityId' => 12, 'entityType' => 'service'],
    ['id' => 1, 'name' => 'Car A', 'entityId' => 15, 'entityType' => 'service']
];

// Normalize to single DTO
$dto = [
    'id' => 1,
    'name' => 'Car A',
    'quantity' => 1,
    'entities' => [
        ['entity_type' => 'service', 'entity_id' => 12],
        ['entity_type' => 'service', 'entity_id' => 15]
    ]
];
```

**Query 2: Get Resources for a Service**
```sql
SELECT r.*
FROM wp_amelia_resources r
JOIN wp_amelia_resources_to_entities rte ON r.id = rte.resourceId
WHERE rte.entityType = 'service' AND rte.entityId = %d
```

**Query 3: Check Resource Availability (Optimized for Shared Pool)**
```sql
-- Get count of concurrent bookings for a resource's linked services
SELECT COUNT(*) AS booked_count
FROM wp_amelia_appointments a
WHERE a.status IN ('approved', 'pending')
  AND a.serviceId IN (
      SELECT entityId 
      FROM wp_amelia_resources_to_entities 
      WHERE resourceId = %d AND entityType = 'service'
  )
  AND (
      (a.bookingStart < %s AND a.bookingEnd > %s)  -- Overlap logic
  )
```

This single query replaces the loop in `ART_Resource_Manager::is_resource_available`.
For a resource with Quantity=3, if `booked_count >= 3`, then blocked.

---

### Appointment Writes

**Insert Appointment:**
```sql
INSERT INTO wp_amelia_appointments 
(
    status,
    bookingStart,
    bookingEnd,
    notifyParticipants,
    serviceId,
    providerId,
    locationId,
    internalNotes
) VALUES (
    %s,  -- 'approved' or 'pending'
    %s,  -- UTC datetime
    %s,  -- UTC datetime
    %d,  -- 1 (yes)
    %d,  -- service ID
    %d,  -- provider ID
    %d,  -- location ID (nullable)
    %s   -- 'Created by ART Module'
)
```

**Insert Customer Booking:**
```sql
INSERT INTO wp_amelia_customer_bookings
(
    appointmentId,
    customerId,
    status,
    price,
    persons,
    info
) VALUES (
    %d,  -- The appointment ID we just inserted
    %d,  -- Customer ID (from wp_amelia_users)
    %s,  -- 'approved' or 'pending'
    %f,  -- Price
    %d,  -- Number of persons
    %s   -- JSON with custom fields
)
```

---

## Implementation Phases

### Phase A: Resource Reads (Direct DB)
**Priority:** HIGH (needed for conflict detection to work)

**Files to Modify:**
1.  `includes/class-art-amelia-data-manager.php`
    - Add `get_resource($id)`
    - Add `get_all_resources()`
    - Add `get_resources_for_service($service_id)`

2.  `includes/class-art-resource-manager.php`
    - Replace `$this->resource_api->get_resources()` calls
    - Update `appointment_uses_resource()` to use new DTO format
    - Remove dependency on `ART_Resource_API` for reads

**Testing:**
- Verify resource conflict detection works for Mode 1 (Mirrored)
- Check debug logs for "ART Resource: Checked X appointments, conflict found"

---

### Phase B: Booking Writes (Direct DB + Hooks)
**Priority:** HIGH (enables Force Override)

**Files to Modify:**
1.  **Create:** `includes/class-art-amelia-hook-trigger.php`
    - Centralize hook firing logic
    - Build correct payload structures
    - Log all hook activity for debugging

2.  **Refactor:** `includes/class-art-booking-service.php`
    - Add `db_insert_appointment()`
    - Add `db_insert_customer_booking()`
    - Add `db_update_appointment()`
    - Add `db_delete_appointment()`
    - Keep API methods as fallback (deprecated)

3.  **Update:** `includes/class-art-admin-settings.php`
    - `ajax_create_booking()` - use new DB method
    - `ajax_reschedule_booking()` - use new DB method
    - Add logging for hook triggers

**Testing:**
- Create a "Force Override" booking
- Verify email is sent (check inbox)
- Verify Google Calendar updates (if enabled)
- Check `wp_amelia_appointments` and `wp_amelia_customer_bookings` tables

---

### Phase C: Resource Assignments (Sync with Amelia)
**Priority:** MEDIUM (needed for Shared Pool mode)

**Problem:** If a booking is made via Amelia's native frontend/backend, our `wp_art_resource_assignments` table won't know about it.

**Solution:** Hook into Amelia's booking creation to populate our table.

**New File:** `includes/class-art-resource-hooks.php` (already exists, needs enhancement)

**Hook to Listen For:**
```php
add_action('amelia_after_appointment_added', [$this, 'on_appointment_added'], 10, 3);
add_action('amelia_after_appointment_updated', [$this, 'on_appointment_updated'], 10, 5);
add_action('amelia_after_appointment_deleted', [$this, 'on_appointment_deleted'], 10, 1);
```

**Logic:**
```php
public function on_appointment_added($appointment, $service, $payment) {
    // 1. Get service ID
    $service_id = $appointment['serviceId'];
    
    // 2. Check our resource config
    $config = $this->resource_manager->get_service_config($service_id);
    
    if (!$config || $config->resource_mode === 'none') {
        return; // This service doesn't use resources
    }
    
    // 3. Determine which resource to assign based on mode
    if ($config->resource_mode === 'mirrored') {
        $resource_id = $config->mode_settings['mirrored_resource_id'];
        
        // 4. Insert into wp_art_resource_assignments
        $this->resource_manager->assign_resources(
            null, // No ART request_id (native Amelia booking)
            $appointment['id'],
            [$resource_id]
        );
    }
    
    // For Shared Pool, we'd need smarter assignment logic here
}
```

**This ensures:**
- Native Amelia bookings also populate our resource tracking table
- Our availability checks see these assignments
- Two-way sync is maintained

---

## Data Flow Diagrams

### Current Flow (API-based - Slow)
```
User clicks "Book"
  ↓
AJAX to ajax_create_booking()
  ↓
ART_Booking_Service::create_booking()
  ↓
HTTP POST to Amelia API (/appointments)
  [Amelia validates, inserts to DB, fires hooks]
  ↓
API returns success/error
  ↓
Frontend shows result
```

**Bottleneck:** Single HTTP call that does everything. If API rejects, we can't override.

---

### New Flow (DB + Hooks - Fast + Flexible)
```
User clicks "Force Book"
  ↓
AJAX to ajax_create_booking()
  ↓
ART_Booking_Service::create_booking_db()
  ↓
wpdb->insert (wp_amelia_appointments)
wpdb->insert (wp_amelia_customer_bookings)
  [Transaction committed]
  ↓
ART_Amelia_Hook_Trigger::trigger_appointment_added()
  ↓
  do_action('amelia_after_appointment_added')
  do_action('amelia_before_post_booking_actions')
  ↓
  Amelia's listeners wake up:
    - Email module sends notifications
    - Google Calendar module syncs event
    - SMS module sends text
  ↓
Frontend shows result
```

**Advantages:**
- Fast (no HTTP overhead)
- Controllable (we bypass validation if needed)
- Integrated (hooks trigger all Amelia features)

---

## Risk Assessment & Mitigation

### Risk 1: Hook Payload Format Mismatch
**Problem:** If we pass the wrong array structure to hooks, integrations might fail silently.

**Mitigation:**
- Study Amelia's source code to understand exact payload format
- Test with minimal booking first
- Enable debug logging for all hooks
- Compare our payload vs what API would pass

### Risk 2: Missing Relational Data
**Problem:** After inserting appointment, we might forget to insert `wp_amelia_customer_bookings`, causing orphaned data.

**Mitigation:**
- Use database transactions (`START TRANSACTION` / `COMMIT` / `ROLLBACK`)
- If any insert fails, rollback all
- Validate integrity after insert

### Risk 3: Timezone Bugs in UTC Conversion
**Problem:** `bookingStart` must be UTC. If we convert wrong, appointments show at wrong time in Amelia.

**Mitigation:**
- Use `ART_Time_Helper::to_utc()` for all conversions
- Log the conversion (input local time + output UTC time)
- Test with timezone offsets (EST, PST, etc.)

### Risk 4: Amelia Version Incompatibility
**Problem:** Future Amelia updates might change DB schema or hook signatures.

**Mitigation:**
- Version-check our code (detect Amelia version on init)
- Graceful fallback to API if DB schema is unexpected
- Monitor Amelia changelogs for breaking changes

### Risk 5: Notification Double-Sending
**Problem:** If we fire `amelia_after_appointment_added` AND the API fires it (in a fallback scenario), customer gets 2 emails.

**Mitigation:**
- Use a flag: `$appointment['_art_hook_triggered'] = true`
- Our hook listener checks this flag and skips if already processed
- Alternatively, choose ONE method (DB or API) per booking, never both

---

## Compatibility with Existing Features

### Feature: Tentative vs Confirmed Bookings
**Current:** Handled via `status` field in both tables.
**New:** Same. We set `status = 'pending'` (tentative) or `'approved'` (confirmed).

### Feature: Reschedule Booking
**Current:** API call to update `bookingStart`.
**New:** Direct `UPDATE wp_amelia_appointments SET bookingStart = %s WHERE id = %d`.
**Hook:** Fire `amelia_after_appointment_updated` to sync calendars.

### Feature: Delete Booking
**Current:** API call to delete appointment.
**New:** Direct `UPDATE wp_amelia_appointments SET status = 'canceled'` (soft delete).
**Hook:** Fire `amelia_after_appointment_deleted`.

### Feature: Resource Assignment Tracking
**Current:** `wp_art_resource_assignments` table (our custom table).
**New:** Same, but enhanced with Amelia hook listeners to populate it even for native Amelia bookings.

---

## Testing Strategy

### Test Case 1: Normal Booking (No Conflicts)
- Service with Mode 1 (Mirrored)
- Available time slot
- **Expected:** DB insert succeeds, email sent, calendar synced

### Test Case 2: Force Override Booking
- Provider NOT scheduled on that day
- User clicks "Force Book"
- **Expected:** DB insert succeeds (bypasses API validation), email still sent

### Test Case 3: Resource Conflict Detection
- Resource already booked
- **Expected:** UI shows "Resource Unavailable", providers blocked

### Test Case 4: Native Amelia Booking → ART Sync
- Create appointment in Amelia's backend
- **Expected:** Our hook listener populates `wp_art_resource_assignments`, future availability checks see it

### Test Case 5: Tentative → Confirmed Upgrade
- Change status from 'pending' to 'approved'
- **Expected:** DB updated, email re-sent (if configured), calendar updated

---

## Code Architecture Changes

### New Class: ART_Amelia_Hook_Trigger

**Responsibilities:**
- Build correct payload structures for Amelia hooks
- Fire hooks in correct sequence
- Log all activity for debugging
- Handle errors gracefully (if hook listener throws exception)

**Dependencies:**
- `ART_Amelia_Data_Manager` (to fetch full appointment data after insert)
- `global $wpdb` (to query related tables for building payloads)

**Example Method:**
```php
class ART_Amelia_Hook_Trigger {
    
    public function trigger_appointment_added($appointment_id) {
        // Step 1: Fetch complete appointment data from DB
        $appointment = $this->fetch_appointment_with_relations($appointment_id);
        
        // Step 2: Fetch service data
        $service = $this->data_manager->get_service($appointment['serviceId']);
        
        // Step 3: Build payment data
        $payment = $this->build_payment_data($appointment);
        
        // Step 4: Fire the hook
        do_action('amelia_after_appointment_added', $appointment, $service, $payment);
        
        // Step 5: Fire post-booking actions
        $result_data = [
            'type' => 'appointment',
            'appointment' => $appointment,
            'booking' => $appointment['bookings'][0] ?? null,
            'customer' => $appointment['bookings'][0]['customer'] ?? null
        ];
        
        do_action('amelia_before_post_booking_actions', $result_data);
    }
    
    private function fetch_appointment_with_relations($appointment_id) {
        global $wpdb;
        
        // Query appointment
        $appt = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}amelia_appointments WHERE id = %d",
            $appointment_id
        ), ARRAY_A);
        
        // Query customer bookings
        $bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT cb.*, u.firstName, u.lastName, u.email, u.phone
             FROM {$wpdb->prefix}amelia_customer_bookings cb
             JOIN {$wpdb->prefix}amelia_users u ON cb.customerId = u.id
             WHERE cb.appointmentId = %d",
            $appointment_id
        ), ARRAY_A);
        
        // Normalize to Amelia's expected format
        $appt['bookings'] = array_map(function($booking) {
            return [
                'id' => $booking['id'],
                'customerId' => $booking['customerId'],
                'customer' => [
                    'id' => $booking['customerId'],
                    'firstName' => $booking['firstName'],
                    'lastName' => $booking['lastName'],
                    'email' => $booking['email'],
                    'phone' => $booking['phone']
                ],
                'status' => $booking['status'],
                'price' => $booking['price'],
                'persons' => $booking['persons']
            ];
        }, $bookings);
        
        return $appt;
    }
}
```

---

### Updated Class: ART_Booking_Service

**New Public Methods:**

1.  **`create_booking_db($booking_data, $force_override = false)`**
    - Validates data
    - Converts times to UTC
    - Inserts to DB (transaction-safe)
    - Triggers hooks
    - Returns appointment ID

2.  **`update_appointment_db($appointment_id, $update_data)`**
    - Updates appointment fields
    - Triggers `amelia_after_appointment_updated`

3.  **`delete_appointment_db($appointment_id)`**
    - Soft-deletes (sets status = 'canceled')
    - Triggers `amelia_after_appointment_deleted`

**Deprecated Methods (keep for backward compat, mark as deprecated):**
- `create_booking()` - API-based (slow)
- `update_appointment()` - API-based
- `delete_appointment()` - API-based

---

## Configuration & Settings

### New Setting: Write Method Preference
**Option Name:** `art_write_method`

**Values:**
- `db_with_hooks` (Default, recommended) - Direct DB + Hook triggering
- `api` (Legacy fallback) - Use Amelia API
- `hybrid` (Experimental) - Try DB first, fallback to API if hooks fail

**Where to Add:**
- `includes/class-art-admin-settings.php` settings page
- Radio buttons under "Advanced Settings"

---

## Migration Considerations

### Existing Bookings
- Already in `wp_amelia_appointments` ✅
- No migration needed ✅
- Our code reads them via Direct DB ✅

### Existing Resources
- Already in `wp_amelia_resources` ✅
- Links already in `wp_amelia_resources_to_entities` ✅
- Our code will read them via Direct DB ✅

### Our Custom Tables
- `wp_art_resource_configs` - KEEP (stores our mode metadata)
- `wp_art_resource_assignments` - KEEP (tracks specific resource instance assignments)
- These complement Amelia's data, not replace it

---

## Performance Benchmarks (Expected)

### Availability Check (Before - API)
- Fetch appointments: ~500ms (HTTP GET /appointments?dates=2025-12-06)
- Fetch resource config: ~200ms (HTTP GET /resources)
- **Total:** ~700ms per check

### Availability Check (After - DB)
- Fetch appointments: ~5ms (SELECT from wp_amelia_appointments)
- Fetch resource config: ~3ms (SELECT from wp_amelia_resources + join, cached)
- **Total:** ~8ms per check

**Improvement:** ~87x faster

### Booking Creation (Before - API)
- Single HTTP POST: ~800ms
- Validation + Hooks handled by Amelia

### Booking Creation (After - DB + Hooks)
- DB Insert: ~10ms
- Hook Triggering: ~50ms (depends on listeners)
- **Total:** ~60ms

**Improvement:** ~13x faster

---

## Debug & Logging Strategy

### Log Points to Add

1.  **Resource Query Logs:**
    ```php
    amelia_cpt_sync_debug_log('ART Data Manager: Fetching resource #' . $id . ' from DB');
    amelia_cpt_sync_debug_log('ART Data Manager: Resource entities - ' . wp_json_encode($entities));
    ```

2.  **Booking Write Logs:**
    ```php
    amelia_cpt_sync_debug_log('ART Booking: Inserting appointment to DB (UTC times)', [
        'start_utc' => $start_utc,
        'end_utc' => $end_utc
    ]);
    amelia_cpt_sync_debug_log('ART Booking: Appointment inserted with ID #' . $appointment_id);
    ```

3.  **Hook Trigger Logs:**
    ```php
    amelia_cpt_sync_debug_log('ART Hooks: Firing amelia_after_appointment_added with payload:', $payload);
    amelia_cpt_sync_debug_log('ART Hooks: Hook completed, listeners executed');
    ```

4.  **Error Logs:**
    ```php
    amelia_cpt_sync_debug_log('ART Booking ERROR: Transaction rollback - ' . $wpdb->last_error);
    ```

---

## Architecture Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                    ART Triage Interface                      │
│                  (WordPress Admin Panel)                     │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│           ART_Booking_Orchestrator (Controller)              │
│  • Check resource availability (Mode 0-7)                    │
│  • Check provider availability                               │
│  • Return unified decision                                   │
└─────────────────────────────────────────────────────────────┘
          │                                      │
          ▼                                      ▼
┌─────────────────────┐              ┌─────────────────────────┐
│ ART_Resource_Manager│              │ ART_Availability_Engine │
│ (Resource Logic)    │              │ (Provider Logic)        │
└─────────────────────┘              └─────────────────────────┘
          │                                      │
          │              ┌───────────────────────┘
          │              │
          ▼              ▼
┌─────────────────────────────────────────────────────────────┐
│          ART_Amelia_Data_Manager (Data Layer)                │
│  • get_appointments() - SELECT from wp_amelia_appointments   │
│  • get_resource() - SELECT from wp_amelia_resources + JOIN   │
│  • get_service() - SELECT from wp_amelia_services            │
│  ───────────────────────────────────────────────────────     │
│  ALL READS ARE DIRECT SQL (No API)                           │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
                    ┌──────────────────┐
                    │  Amelia Database │
                    │  wp_amelia_*     │
                    └──────────────────┘

When User Confirms Booking:
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│         ART_Booking_Service (Write Operations)               │
│  • create_booking_db()                                       │
│    → INSERT wp_amelia_appointments                           │
│    → INSERT wp_amelia_customer_bookings                      │
│    → INSERT wp_art_resource_assignments (if applicable)      │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│       ART_Amelia_Hook_Trigger (Integration Bridge)           │
│  • Fetch complete appointment data from DB                   │
│  • Build payload (appointment + service + payment)           │
│  • do_action('amelia_after_appointment_added', ...)          │
│  • do_action('amelia_before_post_booking_actions', ...)      │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
          ┌───────────────────┴──────────────────┐
          │                                      │
          ▼                                      ▼
┌────────────────────┐              ┌──────────────────────┐
│ Amelia Email Module│              │ Amelia Calendar Sync │
│ (Listens to hooks) │              │ (Listens to hooks)   │
│ Sends notifications│              │ Updates Google/Outlook│
└────────────────────┘              └──────────────────────┘
```

---

## Success Criteria

### Must Have:
✅ Resource conflict detection works (Mode 1)  
✅ Provider conflict detection works  
✅ Force Override bookings succeed  
✅ Email notifications send for all bookings  
✅ Bookings created via Amelia backend are detected by our availability checks  

### Should Have:
✅ Google Calendar sync works (if enabled in Amelia)  
✅ SMS notifications work (if configured)  
✅ Webhooks fire correctly  
✅ Performance improvement: <100ms for availability checks  

### Nice to Have:
✅ Outlook Calendar sync works  
✅ Zoom meeting auto-creation works  
✅ Payment gateway callbacks work  

---

## Open Questions

1.  **What is the `$container` argument in hooks?**
    - Many hooks expect a 3rd argument `$container`.
    - Need to investigate if this is a Dependency Injection container or can be null.

2.  **Does Amelia support custom resource assignment tracking?**
    - We assume Amelia doesn't track "which car from the fleet was used."
    - Need to verify if there's a hidden table or JSON field we missed.

3.  **What happens if a hook listener throws an exception?**
    - Will it break our flow?
    - Should we wrap `do_action` in try-catch?

4.  **Can we query `wp_amelia_providers_to_weekdays` for working hours?**
    - Current code uses API to get provider schedule.
    - Moving this to DB would eliminate 7+ API calls per availability check.

5.  **How does Amelia handle recurring appointments?**
    - `parentId` column suggests parent-child relationships.
    - Need to ensure our DB writes don't break recurring logic.

---

## Implementation Timeline (Estimated)

| Phase | Task | Effort | Priority |
|-------|------|--------|----------|
| A1 | Add `get_resource()` to Data Manager | 2h | HIGH |
| A2 | Refactor Resource Manager to use it | 1h | HIGH |
| A3 | Test resource conflict detection | 1h | HIGH |
| B1 | Create `ART_Amelia_Hook_Trigger` class | 3h | HIGH |
| B2 | Add DB write methods to Booking Service | 4h | HIGH |
| B3 | Update AJAX handlers to use DB writes | 2h | HIGH |
| B4 | Test Force Override + Email sending | 2h | HIGH |
| C1 | Add Amelia hook listeners to Resource Hooks | 2h | MEDIUM |
| C2 | Test native Amelia booking → ART sync | 1h | MEDIUM |
| D1 | Refactor provider schedule to DB reads | 4h | LOW |
| D2 | Performance benchmarking | 2h | LOW |

**Total Estimated Effort:** ~24 hours of development

---

## Rollback Plan

If the DB + Hooks approach causes unexpected issues:

1.  **Immediate Rollback:** Git revert to commit before DB writes were implemented.
2.  **Partial Rollback:** Keep DB reads (proven stable), revert DB writes to API.
3.  **Debug Mode:** Add setting to toggle between DB and API writes for A/B testing.

---

## Documentation Requirements

After implementation, update these docs:
- `ARCHITECTURE-REFACTOR-2025.md` - Add Hook Integration section
- `DEVELOPER.md` - Add "How to Debug Hook Triggers" section
- `CHANGELOG.md` - Document the API → DB migration
- Inline code comments for all hook trigger points

---

## Future Enhancements (Post-Refactor)

1.  **Batch Operations:** Insert multiple appointments in a single transaction (for recurring bookings).
2.  **Async Hook Processing:** Queue heavy hooks (like calendar sync) to run in background.
3.  **Hook Monitoring Dashboard:** Admin page showing which hooks fired for each booking.
4.  **Amelia Version Detection:** Auto-adjust queries if Amelia's schema changes between versions.

---

## End State Vision

**When this refactor is complete:**
- All availability checks run in <50ms (pure SQL).
- Force Override works seamlessly.
- All Amelia features (email, calendar, SMS, webhooks) work exactly as if the booking was made via Amelia's UI.
- Our plugin is invisible to the end-user experience but provides the power-user Triage workflow.
- The codebase is clean, testable, and maintainable (OOP, DTOs, clear separation of concerns).

---

**Next Step:** Get user approval on this plan, then implement Phase A (Resource DB Reads) first.

