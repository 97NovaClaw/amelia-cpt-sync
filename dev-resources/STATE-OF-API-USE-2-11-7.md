# State of Amelia API Usage - v2.11.7

**Generated:** November 27, 2025  
**Plugin Version:** 2.11.7  
**Purpose:** Document all locations where the Amelia API is used, for potential migration to Amelia hooks

---

## Executive Summary

The Amelia CPT Sync plugin currently uses the **Amelia REST API** for all Amelia interactions. Since the plugin runs on the **same WordPress installation** as Amelia, there's an opportunity to replace API calls with **direct Amelia hooks** for:
- Better performance (no HTTP overhead)
- Bypassing slot validation restrictions
- Full access to Amelia's internal services

---

## Table of Contents

1. [API Manager Class](#1-api-manager-class)
2. [Admin Settings AJAX Handlers](#2-admin-settings-ajax-handlers)
3. [Availability Engine](#3-availability-engine)
4. [Frontend JavaScript Calls](#4-frontend-javascript-calls)
5. [Sync Handler (Hooks Already Used)](#5-sync-handler-hooks-already-used)
6. [Migration Opportunities](#6-migration-opportunities)

---

## 1. API Manager Class

**File:** `includes/class-art-api-manager.php`

This is the central API communication class. All API calls go through the `request()` method.

### Core Request Method

```php
private function request($endpoint, $method = 'GET', $body = null)
```

**Location:** Lines 48-105  
**Purpose:** Base HTTP request method for all Amelia API calls  
**HTTP Method:** GET or POST  
**Headers:**
- `Amelia: {api_key}` - API authentication
- `Content-Type: application/json`

**Timeout:** 30 seconds

---

### API Methods

#### 1.1 `get_locations()`

**Location:** Lines 112-137  
**Endpoint:** `GET /entities?types=locations`  
**Purpose:** Fetch all Amelia locations  
**Caching:** 1 hour (`art_amelia_locations` transient)  
**Returns:** Array of location objects

**Used By:**
- `ajax_get_locations()` in admin settings
- Frontend location dropdown population

**Potential Hook Replacement:**
- `amelia_get_locations_filter` - Modify locations before retrieval
- Direct database query to `{prefix}amelia_locations`

---

#### 1.2 `get_categories()`

**Location:** Lines 144-169  
**Endpoint:** `GET /entities?types=categories`  
**Purpose:** Fetch all service categories  
**Caching:** 1 hour (`art_amelia_categories` transient)  
**Returns:** Array of category objects

**Used By:**
- `ajax_create_booking()` - Get category name for booking summary
- Service-to-category mapping

**Potential Hook Replacement:**
- `amelia_get_categories_filter` - Modify categories
- Direct database query to `{prefix}amelia_categories`

---

#### 1.3 `get_service_employees($service_id)`

**Location:** Lines 178-210  
**Endpoint:** `GET /users/providers&services[0]={service_id}`  
**Purpose:** Get providers (employees) assigned to a service  
**Caching:** 1 hour (`art_providers_service_{id}` transient)  
**Returns:** Array of provider objects with `id`, `firstName`, `lastName`, etc.

**Used By:**
- `ajax_get_service_employees()` - Provider dropdown
- `ajax_create_booking()` - Get provider name for summary
- `ajax_reschedule_booking()` - Get provider name
- Availability Engine - Get providers to check

**Potential Hook Replacement:**
- `amelia_get_providers_filter` - Modify providers list
- Direct database query joining `{prefix}amelia_users` and `{prefix}amelia_providers_to_services`

---

#### 1.4 `find_customer($email)`

**Location:** Lines 218-244  
**Endpoint:** `GET /users/customers?search={email}`  
**Purpose:** Find Amelia customer by email (fuzzy search, then exact match)  
**Caching:** None (real-time)  
**Returns:** Customer object or null

**Used By:**
- `ajax_check_customer_match()` - Customer matching in detail view

**Potential Hook Replacement:**
- `amelia_get_customers_filter` - Modify customer search results
- Direct database query to `{prefix}amelia_users` WHERE type='customer'

---

#### 1.5 `get_service($service_id)`

**Location:** Lines 252-283  
**Endpoint:** `GET /services/{service_id}`  
**Purpose:** Get full service details including duration, buffers, category  
**Caching:** 1 hour (`art_service_{id}` transient)  
**Returns:** Service object

**Used By:**
- `ajax_get_service_duration()` - Display service duration
- `ajax_create_booking()` - Fallback duration
- Availability Engine - Get buffer times

**Potential Hook Replacement:**
- `amelia_get_service_filter` - Modify service data
- Direct database query to `{prefix}amelia_services`

---

#### 1.6 `get_slots($params)` ⚠️ CRITICAL

**Location:** Lines 291-373  
**Endpoint:** `GET /slots&serviceId={id}&serviceDuration={sec}&persons={n}&startDateTime={date}&extras=[]&excludeAppointmentId=null`  
**Purpose:** Get available time slots for booking  
**Caching:** None (real-time)  
**Returns:** Slots array grouped by date → time → provider

**Parameters:**
- `serviceId` (required)
- `serviceDuration` (required, in seconds)
- `persons` (required)
- `startDateTime` (optional, defaults to today)
- `locationId` (optional)
- `providerIds` (optional)

**Used By:**
- `ajax_check_availability()` - Populate availability picker

**Potential Hook Replacement:**
- `amelia_get_timeslots_filter` - Modify slot calculation
- `amelia_before_get_timeslots_filter` - Modify input parameters
- Direct use of Amelia's `TimeSlotService` via container

**⚠️ Note:** This is the most complex API call. Returns ~54,000 slots for a year!

---

#### 1.7 `create_booking($booking_data)` ⚠️ CRITICAL

**Location:** Lines 382-475  
**Endpoint:** `POST /bookings`  
**Purpose:** Create an Amelia appointment booking  
**Caching:** None

**Payload Structure:**
```php
array(
    'type' => 'appointment',
    'bookings' => array(
        array(
            'extras' => array(),
            'customFields' => (object) array(),
            'deposit' => false,
            'locale' => 'en_US',
            'utcOffset' => null,
            'persons' => 1,
            'customerId' => null,
            'customer' => array(...),
            'duration' => 3600,
            'status' => 'approved'
        )
    ),
    'payment' => array(
        'gateway' => 'onSite',
        'currency' => 'USD',
        'data' => (object) array()
    ),
    'bookingStart' => '2025-11-28 14:00',
    'notifyParticipants' => 1,
    'providerId' => 15,
    'serviceId' => 7,
    'locationId' => 1,  // Optional
    'isBackendOrCabinet' => true,
    'packageBookingFromBackend' => true
)
```

**Returns:** Booking response with `data.appointment.id` and `data.appointment.bookings[0].id`

**Used By:**
- `ajax_create_booking()` - Create Amelia booking from triage request

**Known Issues:**
- Returns `timeSlotUnavailable` error even with backend flags
- Requires `status: 'approved'` when `packageBookingFromBackend` is true

**Potential Hook Replacement:** ⭐ HIGH PRIORITY
- `amelia_before_booking_added_filter` - Modify booking data
- `amelia_before_appointment_added_filter` - Modify appointment data
- Direct use of Amelia's `AppointmentReservationService` with `availabilityValidation = false`

---

#### 1.8 `update_appointment($appointment_id, $update_data)`

**Location:** Lines 484-501  
**Endpoint:** `POST /appointments/{id}`  
**Purpose:** Update/reschedule an existing appointment  
**Caching:** None

**Used By:**
- `ajax_reschedule_booking()` - Reschedule appointment

**Potential Hook Replacement:**
- `amelia_before_appointment_updated_filter` - Modify update data
- Direct database update + `amelia_after_appointment_updated` action

---

#### 1.9 `delete_appointment($appointment_id)`

**Location:** Lines 509-526  
**Endpoint:** `POST /appointments/delete/{id}`  
**Purpose:** Delete an Amelia appointment  
**Caching:** None

**Used By:**
- `ajax_delete_booking()` - Delete booking before creating new one

**Potential Hook Replacement:**
- `amelia_before_appointment_deleted` action
- Direct database delete + `amelia_after_appointment_deleted` action

---

#### 1.10 `update_appointment_status($appointment_id, $status)`

**Location:** Lines 535-560  
**Endpoint:** `POST /appointments/status/{id}`  
**Purpose:** Update appointment status (approved, pending, canceled, rejected, no-show)  
**Caching:** None

**Used By:**
- Currently not actively used, but available

**Potential Hook Replacement:**
- `amelia_before_appointment_status_updated` action
- Direct database update + `amelia_after_appointment_status_updated` action

---

## 2. Admin Settings AJAX Handlers

**File:** `includes/class-art-admin-settings.php`

### AJAX Actions Registered

| Action | Handler | API Methods Used |
|--------|---------|------------------|
| `art_check_customer_match` | `ajax_check_customer_match()` | `find_customer()` |
| `art_get_locations` | `ajax_get_locations()` | `get_locations()` |
| `art_get_service_employees` | `ajax_get_service_employees()` | `get_service_employees()` |
| `art_get_service_duration` | `ajax_get_service_duration()` | `get_service()` |
| `art_check_availability` | `ajax_check_availability()` | `get_slots()` |
| `art_create_booking` | `ajax_create_booking()` | `create_booking()`, `get_service()`, `get_categories()`, `get_service_employees()`, `get_locations()` |
| `art_check_provider_availability` | `ajax_check_provider_availability()` | Uses Availability Engine |
| `art_reschedule_booking` | `ajax_reschedule_booking()` | `update_appointment()`, `get_service_employees()` |
| `art_delete_booking` | `ajax_delete_booking()` | `delete_appointment()` |

---

## 3. Availability Engine

**File:** `includes/class-art-availability-engine.php`

### API Calls Made

#### 3.1 `get_service_providers()` (via API Manager)

**Location:** Lines 313-339  
**Uses:** `$this->api_manager->get_service_employees($service_id)`

---

#### 3.2 `get_service()` (via API Manager)

**Location:** Line 96  
**Uses:** `$this->api_manager->get_service($service_id)`  
**Purpose:** Get buffer times (timeBefore, timeAfter)

---

#### 3.3 `get_provider_details()` - Direct API Call

**Location:** Lines 347-370  
**Endpoint:** `GET /users/providers/{provider_id}`  
**Purpose:** Get full provider details including schedule (weekDayList, specialDayList, dayOffList)

**⚠️ Note:** This makes a separate API call for EACH provider, which can be slow for many providers.

**Potential Hook Replacement:**
- `amelia_get_provider_filter` - Modify provider data
- Direct database query with JOINs for schedule data

---

#### 3.4 `get_appointments_for_date()` - Direct API Call

**Location:** Lines 378-396  
**Endpoint:** `GET /appointments&dates={date},{date}&skipServices=1&skipProviders=1`  
**Purpose:** Get all appointments for a specific date to check conflicts

**Potential Hook Replacement:**
- `amelia_get_appointments_filter` - Modify appointments list
- Direct database query to `{prefix}amelia_appointments`

---

## 4. Frontend JavaScript Calls

**File:** `templates/art-request-detail-page.php`

### AJAX Calls to Backend

| Line | Action | Purpose |
|------|--------|---------|
| 2260 | `art_get_service_duration` | Fetch service duration when service changes |
| 2521 | `art_get_locations` | Populate location dropdown |
| 2583 | `art_check_availability` | Get available time slots |
| 3092 | `art_reschedule_booking` | Reschedule existing appointment |
| 3133 | `art_delete_booking` | Delete existing appointment |
| 3170 | `art_create_booking` | Create new Amelia booking |
| 3467 | `art_check_provider_availability` | Check provider availability via engine |
| 4039 | `art_get_service_employees` | Get providers for service |

---

## 5. Sync Handler (Hooks Already Used)

**File:** `includes/class-sync-handler.php`

This file **already uses Amelia hooks** for service sync (not API):

| Hook | Handler | Purpose |
|------|---------|---------|
| `amelia_after_service_added` | `handle_service_added()` | Sync new service to CPT |
| `amelia_after_service_updated` | `handle_service_updated()` | Sync updated service to CPT |
| `amelia_before_service_deleted` | `handle_service_deleted()` | Delete CPT when service deleted |

**Note:** This is a good example of hook-based integration!

---

## 6. Migration Opportunities

### High Priority (Booking Issues)

#### 6.1 Replace `create_booking()` with Direct Container Access

**Current Problem:** API returns "Time slot is unavailable" even with backend flags.

**Solution:** Access Amelia's container directly:

```php
// Get Amelia's container
$container = require WP_PLUGIN_DIR . '/ameliabooking/src/Infrastructure/ContainerConfig/container.php';

// Get reservation service
$reservationService = $container->get('application.reservation.service')->get('appointment');

// Create reservation with validation DISABLED
$reservation = $reservationService->getNew(
    false,  // couponValidation
    false,  // customFieldsValidation
    false   // availabilityValidation  ⭐ KEY!
);

// Process booking without slot validation
$reservation->process(
    $bookingData,
    false,  // inspectTimeSlot = false  ⭐ KEY!
    false   // processPackage
);
```

**Relevant Amelia Hooks:**
- `amelia_before_booking_added_filter` - Modify booking before save
- `amelia_before_appointment_booking_saved_filter` - Modify before DB save
- `amelia_after_appointment_booking_saved` - Action after save

---

### Medium Priority (Performance)

#### 6.2 Replace `get_slots()` with Direct Calculation

**Current Problem:** Returns 54,000+ slots for a year, slow and verbose.

**Solution:** Use Amelia's TimeSlotService directly:

```php
$container = require WP_PLUGIN_DIR . '/ameliabooking/src/Infrastructure/ContainerConfig/container.php';
$timeSlotService = $container->get('application.timeSlot.service');

// Calculate slots with custom parameters
$slots = $timeSlotService->getSlots(
    $serviceId,
    $providerId,
    $locationId,
    $startDate,
    $endDate,
    false  // isFrontEndBooking = false (less strict)
);
```

---

#### 6.3 Replace Provider Details Fetch with Single Query

**Current Problem:** Availability Engine makes N API calls for N providers.

**Solution:** Fetch all provider schedules in one database query:

```php
global $wpdb;
$providers = $wpdb->get_results("
    SELECT 
        u.*,
        GROUP_CONCAT(DISTINCT wd.dayIndex) as work_days
    FROM {$wpdb->prefix}amelia_users u
    LEFT JOIN {$wpdb->prefix}amelia_providers_to_services pts ON u.id = pts.userId
    LEFT JOIN {$wpdb->prefix}amelia_providers_to_weekdays wd ON u.id = wd.userId
    WHERE pts.serviceId = {$service_id}
    GROUP BY u.id
");
```

---

### Low Priority (Already Cached)

These API calls are already cached and work well:
- `get_locations()` - 1 hour cache
- `get_categories()` - 1 hour cache
- `get_service()` - 1 hour cache
- `get_service_employees()` - 1 hour cache

---

## Appendix: Amelia Database Tables

For direct database access, here are the relevant tables:

| Table | Purpose |
|-------|---------|
| `{prefix}amelia_users` | Customers and providers |
| `{prefix}amelia_services` | Service definitions |
| `{prefix}amelia_categories` | Service categories |
| `{prefix}amelia_locations` | Location definitions |
| `{prefix}amelia_appointments` | Appointment records |
| `{prefix}amelia_customer_bookings` | Individual bookings |
| `{prefix}amelia_providers_to_services` | Provider-service assignments |
| `{prefix}amelia_providers_to_weekdays` | Provider weekly schedules |
| `{prefix}amelia_providers_to_specialdays` | Provider special days |
| `{prefix}amelia_providers_to_daysoff` | Provider days off |

---

## Appendix: Amelia Container Services

Key services available via `$container->get()`:

| Service Key | Purpose |
|-------------|---------|
| `application.reservation.service` | Booking creation |
| `application.timeSlot.service` | Slot calculation |
| `domain.booking.appointment.repository` | Appointment CRUD |
| `domain.booking.customer.repository` | Customer CRUD |
| `domain.service.repository` | Service CRUD |
| `domain.location.repository` | Location CRUD |
| `domain.user.provider.repository` | Provider CRUD |

---

## Next Steps

1. **Immediate:** Create a test file to verify Amelia container access works
2. **Phase 1:** Replace `create_booking()` with direct container access
3. **Phase 2:** Replace Availability Engine API calls with direct queries
4. **Phase 3:** Evaluate replacing all cached API calls with database queries

---

*Document generated for migration planning. Review with Amelia source code in `dev-resources/Amelia Plugin/`.*

