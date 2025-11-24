# Store Integration Consolidation Design Document

**Version:** 1.0
**Date:** 2025-11-24
**Status:** Draft for Review
**Branch:** store-integration

---

## Executive Summary

This document outlines a plan to consolidate two parallel "store" systems in Grocy:

1. **Legacy `shopping_locations`** - Simple store references used throughout Grocy since 2019
2. **New `store_integrations`** - OAuth-enabled API integrations with external stores (Kroger, etc.)

Currently, these systems operate independently, creating confusion and preventing full utilization of both features. This proposal integrates them into a unified system that maintains backward compatibility while enabling powerful new capabilities.

**Key Goals:**
- Unify store concepts under `shopping_locations` as the primary model
- Enable `shopping_locations` to optionally link to API-integrated stores
- Maintain 100% backward compatibility with existing data and workflows
- Leverage existing UI and database fields (`products.shopping_location_id`)
- Provide clear migration path for users to adopt integrations

---

## Current Architecture Analysis

### System 1: Legacy Shopping Locations

**Database Schema:**
```sql
CREATE TABLE shopping_locations (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL UNIQUE,
    description TEXT,
    row_created_timestamp DATETIME
);
```

**Usage:**
- `products.shopping_location_id` - Default store for purchasing product
- `stock_log.shopping_location_id` - Where item was purchased
- `stock.shopping_location_id` - Where item was acquired
- Shopping list UI shows "Default store" column
- Product form has "Default shopping location" picker
- Simple CRUD UI at `/shoppinglocations`

**Characteristics:**
- ✅ Simple, user-friendly
- ✅ Integrated throughout entire codebase
- ✅ No external dependencies
- ❌ No API capabilities
- ❌ No address/location data
- ❌ No product metadata (prices, aisles)

### System 2: New Store Integrations

**Database Schema:**
```sql
-- API Integration Configuration
CREATE TABLE store_integrations (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL,
    store_type TEXT NOT NULL,
    access_token TEXT,
    refresh_token TEXT,
    token_expires_at DATETIME,
    configuration TEXT,
    active INTEGER DEFAULT 1
);

-- Physical Store Locations
CREATE TABLE store_integration_locations (
    id INTEGER PRIMARY KEY,
    store_integration_id INTEGER NOT NULL,
    external_location_id TEXT NOT NULL,
    name TEXT NOT NULL,
    address TEXT,
    city TEXT,
    state TEXT,
    zip_code TEXT,
    phone TEXT,
    is_primary INTEGER DEFAULT 0,
    metadata_json TEXT
);

-- Product Metadata per Store
CREATE TABLE product_store_metadata (
    id INTEGER PRIMARY KEY,
    product_id INTEGER NOT NULL,
    store_integration_id INTEGER NOT NULL,
    external_product_id TEXT,
    aisle TEXT,
    shelf TEXT,
    department TEXT,
    category TEXT,
    price REAL,
    price_unit TEXT,
    availability TEXT,
    metadata_json TEXT,
    last_updated DATETIME
);
```

**Additional Product Fields:**
```sql
ALTER TABLE products ADD COLUMN store_location_aisle TEXT;
ALTER TABLE products ADD COLUMN store_location_shelf TEXT;
ALTER TABLE products ADD COLUMN store_location_department TEXT;
ALTER TABLE products ADD COLUMN store_location_updated DATETIME;
```

**Architecture:**
- Plugin-based system (`BaseStoreIntegrationPlugin`)
- OAuth 2.0 authentication flow
- Store location search by zip/coordinates
- Product search and metadata lookup
- Shopping list synchronization to store carts
- Kroger implementation complete

**Characteristics:**
- ✅ Full API integration capabilities
- ✅ Rich product metadata (prices, locations, availability)
- ✅ Physical store data (addresses, coordinates)
- ✅ Extensible plugin architecture
- ❌ Parallel to existing shopping_locations
- ❌ Not integrated with `products.shopping_location_id`
- ❌ Separate UI and workflows

---

## Problems with Current Approach

### 1. **Conceptual Duplication**

Users must understand two different "store" concepts:
- "Shopping Locations" (old system) = where I buy things
- "Store Integrations" (new system) = also where I buy things, but with an API

This is confusing and creates fragmentation.

### 2. **Data Inconsistency**

When a product is created from store integration search:
- Its `shopping_location_id` is **not set**
- Store location data goes to `product_store_metadata` table
- Product appears to have no "default store" in UI
- Shopping list doesn't group it properly

### 3. **Underutilized Existing Infrastructure**

The `shopping_locations` system is:
- Already referenced by products
- Already in shopping list views
- Already has UI throughout Grocy
- **But can't leverage new API capabilities**

### 4. **Duplicate Store Management UIs**

- `/shoppinglocations` - Manage simple stores
- `/storeintegrations` - Manage API-integrated stores

Users must use both to get full functionality.

### 5. **Missing Integration Opportunities**

Cannot answer questions like:
- "Which of my shopping locations have API integrations?"
- "Show me products from my local Kroger (with address XYZ)"
- "What's the price of this product at my preferred store?"

### 6. **Product Store Metadata Not Surfaced**

Rich data in `product_store_metadata` table:
- Price information
- Aisle/shelf locations
- Availability
- **Not easily accessible or displayed in UI**

---

## Proposed Solution: Unified Store Model

### Design Principle

**`shopping_locations` becomes the single source of truth for all stores**

A `shopping_location` can be:
- **Simple store** - Just a name (e.g., "Local Farmers Market")
- **Integrated store** - Linked to `store_integration_location` for API capabilities

### Architecture Overview

```
┌─────────────────────────────────────────────────────────────┐
│                     STORE INTEGRATIONS                      │
│                   (API Configuration)                       │
│                                                             │
│  store_integrations                                         │
│  ├─ id: 1                                                   │
│  ├─ name: "Kroger API"                                      │
│  ├─ store_type: "kroger"                                    │
│  ├─ access_token: "..."                                     │
│  └─ OAuth configuration                                     │
└──────────────────────┬──────────────────────────────────────┘
                       │ has many
                       ▼
┌─────────────────────────────────────────────────────────────┐
│              STORE INTEGRATION LOCATIONS                    │
│              (Physical Store Locations)                     │
│                                                             │
│  store_integration_locations                                │
│  ├─ id: 1                                                   │
│  ├─ store_integration_id: 1 (Kroger API)                    │
│  ├─ external_location_id: "70100123" (Kroger Store ID)     │
│  ├─ name: "Kroger Store #123"                              │
│  ├─ address: "123 Main St"                                 │
│  ├─ city: "Springfield"                                    │
│  └─ is_primary: 1                                           │
└──────────────────────┬──────────────────────────────────────┘
                       │ linked to
                       ▼
┌─────────────────────────────────────────────────────────────┐
│                  SHOPPING LOCATIONS                         │
│            (User's Preferred Stores)                        │
│                                                             │
│  shopping_locations                                         │
│  ├─ id: 1                                                   │
│  ├─ name: "My Local Kroger"                                │
│  ├─ description: "The one near home"                        │
│  └─ store_integration_location_id: 1 ← NEW LINK            │
└──────────────────────┬──────────────────────────────────────┘
                       │ referenced by
                       ▼
┌─────────────────────────────────────────────────────────────┐
│                       PRODUCTS                              │
│                                                             │
│  products                                                   │
│  ├─ id: 1                                                   │
│  ├─ name: "Milk"                                            │
│  ├─ shopping_location_id: 1 (My Local Kroger)              │
│  ├─ store_location_aisle: "Aisle 5"                        │
│  ├─ store_location_shelf: "B"                              │
│  └─ store_location_department: "Dairy"                     │
└─────────────────────────────────────────────────────────────┘
```

### Key Relationships

1. **One `store_integration`** (e.g., "Kroger API")
   - Has many `store_integration_locations` (Kroger stores #123, #456, etc.)

2. **One `store_integration_location`** (e.g., "Kroger Store #123 at 123 Main St")
   - Can be linked to by many `shopping_locations`

3. **One `shopping_location`** (e.g., "My Local Kroger")
   - Optionally links to one `store_integration_location`
   - Referenced by many `products`

### Benefits of This Design

✅ **Single Store Concept** - Users only work with "shopping locations"
✅ **Backward Compatible** - Existing shopping_locations work unchanged
✅ **Progressive Enhancement** - Users opt into integrations per store
✅ **Leverages Existing Fields** - `products.shopping_location_id` gains API powers
✅ **Unified UI** - Single store management interface
✅ **Flexible** - Supports both simple and integrated stores
✅ **Data Consistency** - Products always have a shopping_location
✅ **Future-Proof** - Easy to add more integration types

---

## Detailed Design

### Database Changes

#### Migration 0259.sql

```sql
-- Link shopping_locations to store integrations
ALTER TABLE shopping_locations
ADD COLUMN store_integration_location_id INTEGER REFERENCES store_integration_locations(id);

CREATE INDEX idx_shopping_locations_integration_location
ON shopping_locations(store_integration_location_id);

-- Add helper columns for richer store data
ALTER TABLE shopping_locations
ADD COLUMN address TEXT;

ALTER TABLE shopping_locations
ADD COLUMN city TEXT;

ALTER TABLE shopping_locations
ADD COLUMN state TEXT;

ALTER TABLE shopping_locations
ADD COLUMN zip_code TEXT;

ALTER TABLE shopping_locations
ADD COLUMN phone TEXT;

-- When a shopping_location is linked, these can be populated from integration data
-- When not linked, users can manually enter this data
```

**Migration Strategy:**
- Existing `shopping_locations` records remain unchanged
- `store_integration_location_id` defaults to NULL (not linked)
- No data loss, fully backward compatible

#### Updated Table Relationships

**shopping_locations** (enhanced)
```sql
CREATE TABLE shopping_locations (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL UNIQUE,
    description TEXT,
    store_integration_location_id INTEGER,  -- NEW: Optional link
    address TEXT,                            -- NEW: Can be auto-populated
    city TEXT,                               -- NEW: Can be auto-populated
    state TEXT,                              -- NEW: Can be auto-populated
    zip_code TEXT,                           -- NEW: Can be auto-populated
    phone TEXT,                              -- NEW: Can be auto-populated
    row_created_timestamp DATETIME,
    FOREIGN KEY (store_integration_location_id)
        REFERENCES store_integration_locations(id)
        ON DELETE SET NULL
);
```

**Semantic Meaning:**
- **NULL `store_integration_location_id`** = Simple store, no API integration
- **Non-NULL `store_integration_location_id`** = Integrated store with API capabilities

### Service Layer Changes

#### StoreIntegrationsService Updates

**New Methods:**

```php
/**
 * Find or create a shopping_location for a store integration location
 *
 * @param int $integrationLocationId
 * @param string|null $customName Optional custom name for shopping location
 * @return object The shopping_location record
 */
public function GetOrCreateShoppingLocation($integrationLocationId, $customName = null)
{
    // Check if shopping_location already exists for this integration location
    $existing = $this->getDatabase()->shopping_locations()
        ->where('store_integration_location_id = :1', $integrationLocationId)
        ->fetch();

    if ($existing) {
        return $existing;
    }

    // Get integration location data
    $integrationLocation = $this->getDatabase()->store_integration_locations()
        ->where('id = :1', $integrationLocationId)
        ->fetch();

    if (!$integrationLocation) {
        throw new \Exception('Store integration location not found');
    }

    // Create new shopping_location
    $name = $customName ?? $integrationLocation->name;

    $data = [
        'name' => $name,
        'description' => 'Linked to ' . $integrationLocation->name,
        'store_integration_location_id' => $integrationLocationId,
        'address' => $integrationLocation->address,
        'city' => $integrationLocation->city,
        'state' => $integrationLocation->state,
        'zip_code' => $integrationLocation->zip_code,
        'phone' => $integrationLocation->phone
    ];

    $this->getDatabase()->shopping_locations()->insert($data);
    return $this->getDatabase()->shopping_locations()
        ->where('id = :1', $this->getDatabase()->lastInsertId())
        ->fetch();
}

/**
 * Link an existing shopping_location to a store integration location
 *
 * @param int $shoppingLocationId
 * @param int $integrationLocationId
 */
public function LinkShoppingLocationToIntegration($shoppingLocationId, $integrationLocationId)
{
    $shoppingLocation = $this->getDatabase()->shopping_locations()
        ->where('id = :1', $shoppingLocationId)
        ->fetch();

    if (!$shoppingLocation) {
        throw new \Exception('Shopping location not found');
    }

    $integrationLocation = $this->getDatabase()->store_integration_locations()
        ->where('id = :1', $integrationLocationId)
        ->fetch();

    if (!$integrationLocation) {
        throw new \Exception('Store integration location not found');
    }

    // Update the shopping location
    $shoppingLocation->update([
        'store_integration_location_id' => $integrationLocationId,
        'address' => $integrationLocation->address,
        'city' => $integrationLocation->city,
        'state' => $integrationLocation->state,
        'zip_code' => $integrationLocation->zip_code,
        'phone' => $integrationLocation->phone
    ]);
}

/**
 * Get the store integration for a shopping location (if linked)
 *
 * @param int $shoppingLocationId
 * @return object|null The store_integration record, or null if not linked
 */
public function GetIntegrationForShoppingLocation($shoppingLocationId)
{
    $shoppingLocation = $this->getDatabase()->shopping_locations()
        ->where('id = :1', $shoppingLocationId)
        ->fetch();

    if (!$shoppingLocation || !$shoppingLocation->store_integration_location_id) {
        return null;
    }

    $integrationLocation = $this->getDatabase()->store_integration_locations()
        ->where('id = :1', $shoppingLocation->store_integration_location_id)
        ->fetch();

    if (!$integrationLocation) {
        return null;
    }

    return $this->getDatabase()->store_integrations()
        ->where('id = :1', $integrationLocation->store_integration_id)
        ->fetch();
}

/**
 * Get all shopping locations with their integration status
 *
 * @return array Enhanced shopping_location objects with integration data
 */
public function GetShoppingLocationsWithIntegrationStatus()
{
    $locations = $this->getDatabase()->shopping_locations();
    $result = [];

    foreach ($locations as $location) {
        $locationData = (array)$location;
        $locationData['is_integrated'] = !empty($location->store_integration_location_id);
        $locationData['integration'] = null;
        $locationData['integration_location'] = null;

        if ($locationData['is_integrated']) {
            $integrationLocation = $this->getDatabase()->store_integration_locations()
                ->where('id = :1', $location->store_integration_location_id)
                ->fetch();

            if ($integrationLocation) {
                $locationData['integration_location'] = $integrationLocation;
                $integration = $this->getDatabase()->store_integrations()
                    ->where('id = :1', $integrationLocation->store_integration_id)
                    ->fetch();
                $locationData['integration'] = $integration;
            }
        }

        $result[] = (object)$locationData;
    }

    return $result;
}

/**
 * Unlink a shopping location from its integration
 *
 * @param int $shoppingLocationId
 */
public function UnlinkShoppingLocation($shoppingLocationId)
{
    $shoppingLocation = $this->getDatabase()->shopping_locations()
        ->where('id = :1', $shoppingLocationId)
        ->fetch();

    if ($shoppingLocation) {
        $shoppingLocation->update([
            'store_integration_location_id' => null
        ]);
    }
}
```

**Modified Methods:**

```php
/**
 * Search products - now returns shopping_location_id
 */
public function SearchProducts($integrationId, $searchTerm, $locationId = null)
{
    // ... existing code ...

    // For each product result, find associated shopping_location
    foreach ($products as &$product) {
        $product['shopping_location_id'] = null;

        if ($locationId) {
            // Find shopping_location linked to this integration location
            $shoppingLocation = $this->getDatabase()->shopping_locations()
                ->where('store_integration_location_id = :1', $locationId)
                ->fetch();

            if ($shoppingLocation) {
                $product['shopping_location_id'] = $shoppingLocation->id;
            }
        }
    }

    return $products;
}
```

#### BaseStoreIntegrationPlugin Updates

**Modified signature for `SaveStoreLocation`:**

```php
/**
 * Save a store location - now creates/links shopping_location
 *
 * @param int $integrationId
 * @param array $locationData
 * @param bool $isPrimary
 * @param string|null $customShoppingLocationName Custom name for shopping_location
 * @return array ['integration_location_id' => int, 'shopping_location_id' => int]
 */
public function SaveStoreLocation($integrationId, $locationData, $isPrimary = false, $customShoppingLocationName = null)
{
    // Set primary flag
    if ($isPrimary) {
        $this->Database->store_integration_locations()
            ->where('store_integration_id = :1', $integrationId)
            ->update(['is_primary' => 0]);
    }

    // Check if integration location already exists
    $existing = $this->Database->store_integration_locations()
        ->where('store_integration_id = :1', $integrationId)
        ->where('external_location_id = :2', $locationData['locationId'])
        ->fetch();

    $integrationLocationData = [
        'store_integration_id' => $integrationId,
        'external_location_id' => $locationData['locationId'],
        'name' => $locationData['name'] ?? '',
        'address' => $locationData['address']['addressLine1'] ?? '',
        'city' => $locationData['address']['city'] ?? '',
        'state' => $locationData['address']['state'] ?? '',
        'zip_code' => $locationData['address']['zipCode'] ?? '',
        'phone' => $locationData['phone'] ?? '',
        'is_primary' => $isPrimary ? 1 : 0,
        'metadata_json' => json_encode($locationData)
    ];

    if ($existing) {
        $existing->update($integrationLocationData);
        $integrationLocationId = $existing->id;
    } else {
        $this->Database->store_integration_locations()->insert($integrationLocationData);
        $integrationLocationId = $this->Database->lastInsertId();
    }

    // Create or get shopping_location linked to this integration location
    $shoppingLocation = $this->StoreIntegrationsService->GetOrCreateShoppingLocation(
        $integrationLocationId,
        $customShoppingLocationName
    );

    return [
        'integration_location_id' => $integrationLocationId,
        'shopping_location_id' => $shoppingLocation->id
    ];
}
```

### Controller Changes

#### StoreIntegrationsApiController

**New Endpoints:**

```php
/**
 * GET /api/shopping-locations/{locationId}/integration-status
 * Get integration status for a shopping location
 */
public function GetShoppingLocationIntegrationStatus(Request $request, Response $response, array $args)
{
    $shoppingLocationId = $args['locationId'];
    $integration = $this->getStoreIntegrationsService()
        ->GetIntegrationForShoppingLocation($shoppingLocationId);

    if (!$integration) {
        return $this->ApiResponse($response, [
            'is_integrated' => false,
            'integration' => null
        ]);
    }

    return $this->ApiResponse($response, [
        'is_integrated' => true,
        'integration' => $integration
    ]);
}

/**
 * POST /api/shopping-locations/{locationId}/link-integration
 * Link a shopping location to a store integration location
 */
public function LinkShoppingLocationToIntegration(Request $request, Response $response, array $args)
{
    try {
        $requestBody = $this->GetParsedAndFilteredRequestBody($request);

        if (!isset($requestBody['integration_location_id'])) {
            return $this->GenericErrorResponse($response, 'integration_location_id is required');
        }

        $this->getStoreIntegrationsService()->LinkShoppingLocationToIntegration(
            $args['locationId'],
            $requestBody['integration_location_id']
        );

        return $this->EmptyApiResponse($response);
    } catch (\Exception $ex) {
        return $this->GenericErrorResponse($response, $ex->getMessage());
    }
}

/**
 * POST /api/shopping-locations/{locationId}/unlink-integration
 * Unlink a shopping location from its integration
 */
public function UnlinkShoppingLocationFromIntegration(Request $request, Response $response, array $args)
{
    try {
        $this->getStoreIntegrationsService()->UnlinkShoppingLocation($args['locationId']);
        return $this->EmptyApiResponse($response);
    } catch (\Exception $ex) {
        return $this->GenericErrorResponse($response, $ex->getMessage());
    }
}

/**
 * GET /api/shopping-locations/with-integration-status
 * Get all shopping locations with their integration status
 */
public function GetShoppingLocationsWithIntegrationStatus(Request $request, Response $response, array $args)
{
    $locations = $this->getStoreIntegrationsService()
        ->GetShoppingLocationsWithIntegrationStatus();

    return $this->FilteredApiResponse($response, $locations, $request->getQueryParams());
}
```

**Modified Endpoint:**

```php
/**
 * POST /api/store-integrations/products/create-from-store
 * Now sets shopping_location_id on created product
 */
public function CreateProductFromStoreData(Request $request, Response $response, array $args)
{
    // ... existing validation code ...

    // NEW: Determine shopping_location_id
    $shoppingLocationId = null;
    if (isset($requestBody['integration_location_id'])) {
        $shoppingLocation = $this->getStoreIntegrationsService()
            ->GetOrCreateShoppingLocation($requestBody['integration_location_id']);
        $shoppingLocationId = $shoppingLocation->id;
    }

    // Check for existing product by UPC
    if (!empty($requestBody['upc'])) {
        $existingBarcode = $this->getDatabase()->product_barcodes()
            ->where('barcode = :1', $requestBody['upc'])
            ->fetch();

        if ($existingBarcode) {
            $product = $this->getDatabase()->products()
                ->where('id = :1', $existingBarcode->product_id)
                ->fetch();

            // Update shopping_location_id if not set
            if ($shoppingLocationId && empty($product->shopping_location_id)) {
                $product->update(['shopping_location_id' => $shoppingLocationId]);
            }

            return $this->ApiResponse($response, [
                'product_id' => $product->id,
                'created' => false
            ]);
        }
    }

    // Create new product with shopping_location_id
    $productData = [
        'name' => $requestBody['name'],
        'description' => $requestBody['description'] ?? null,
        'location_id' => $this->getUsersService()->GetUserSettings(GROCY_USER_ID)['stock_default_location_id'],
        'qu_id_stock' => $this->getUsersService()->GetUserSettings(GROCY_USER_ID)['stock_default_quantity_unit_id'],
        'qu_id_purchase' => $this->getUsersService()->GetUserSettings(GROCY_USER_ID)['stock_default_quantity_unit_id'],
        'shopping_location_id' => $shoppingLocationId,  // NEW
        'store_location_aisle' => $requestBody['aisle'] ?? null,
        'store_location_shelf' => $requestBody['shelf'] ?? null,
        'store_location_department' => $requestBody['department'] ?? null,
        'store_location_updated' => date('Y-m-d H:i:s')
    ];

    // ... rest of existing code ...
}
```

### UI Changes

#### Shopping Locations Management (`/shoppinglocations`)

**Enhanced Table Columns:**
- Name
- Description
- **Integration Status** (NEW) - Badge showing "Integrated" or "Simple"
- **Store Type** (NEW) - "Kroger", "Walmart", etc. (if integrated)
- **Address** (NEW) - Physical address (if available)
- Actions (Edit, Delete, **Manage Integration**)

**New "Link Integration" Dialog:**
```
┌──────────────────────────────────────────────┐
│  Link "My Local Kroger" to Integration       │
├──────────────────────────────────────────────┤
│                                              │
│  Select Integration:                         │
│  ┌──────────────────────────────────────┐   │
│  │ Kroger API                        ▼  │   │
│  └──────────────────────────────────────┘   │
│                                              │
│  Select Location:                            │
│  ┌──────────────────────────────────────┐   │
│  │ Kroger Store #123                    │   │
│  │ 123 Main St, Springfield, OH         │   │
│  │                                   ▼  │   │
│  └──────────────────────────────────────┘   │
│                                              │
│  [Search Locations]  [Cancel]  [Link]       │
└──────────────────────────────────────────────┘
```

**Integration Status Badge:**
```html
<!-- Simple store -->
<span class="badge badge-secondary">Simple</span>

<!-- Integrated store -->
<span class="badge badge-success">
    <i class="fa-solid fa-link"></i> Integrated (Kroger)
</span>
```

#### Product Form Enhancements

**Default Shopping Location Picker:**
- Existing dropdown unchanged
- NEW: Show integration badge next to integrated stores
- NEW: Link to view product at store (if integrated)

```html
<select id="shopping-location-id" name="shopping_location_id">
    <option value="">None</option>
    <option value="1">
        My Local Kroger
        🔗 (Kroger)  <!-- NEW: Integration indicator -->
    </option>
    <option value="2">
        Farmers Market  <!-- No integration -->
    </option>
</select>
```

**Product Aisle/Location Display:**
- Show `store_location_aisle`, `store_location_shelf`, `store_location_department`
- NEW: Link to refresh from integration if available
- Show last updated timestamp

#### Shopping List Modal (`store-product-search-modal`)

**Store Dropdown Enhancement:**
```html
<select id="store-search-integration">
    <option value="">Select a store</option>

    <!-- Group by shopping locations -->
    <optgroup label="Integrated Stores">
        <option value="1"
                data-shopping-location-id="1"
                data-integration-id="1"
                data-location-id="1">
            My Local Kroger - 123 Main St
        </option>
    </optgroup>

    <optgroup label="Simple Stores (No Integration)">
        <option disabled>
            Farmers Market (link integration to search)
        </option>
    </optgroup>
</select>
```

**Product Creation Flow Update:**
- When product is added from search, its `shopping_location_id` is automatically set
- Product immediately appears with correct "Default store" in shopping list
- Shopping list grouping works correctly

#### Store Integrations Management (`/storeintegrations`)

**Consolidation with Shopping Locations:**

Option 1: Merge into `/shoppinglocations` (Recommended)
- Rename page title to "Stores & Integrations"
- Show all shopping locations
- Add "Setup Integration" button for each store
- Inline integration management

Option 2: Keep separate but add cross-links
- `/shoppinglocations` → Link to setup integration
- `/storeintegrations` → Show linked shopping_locations
- Breadcrumb navigation between pages

### Data Views

#### New Database View: `shopping_locations_with_integrations`

```sql
CREATE VIEW shopping_locations_with_integrations AS
SELECT
    sl.id,
    sl.name,
    sl.description,
    sl.store_integration_location_id,
    sl.address,
    sl.city,
    sl.state,
    sl.zip_code,
    sl.phone,
    sl.row_created_timestamp,

    -- Integration data
    sil.external_location_id,
    sil.is_primary AS is_primary_location,
    si.id AS store_integration_id,
    si.name AS integration_name,
    si.store_type,
    si.active AS integration_active,

    -- Computed columns
    CASE
        WHEN sl.store_integration_location_id IS NOT NULL THEN 1
        ELSE 0
    END AS is_integrated,

    CASE
        WHEN sl.store_integration_location_id IS NOT NULL
        THEN si.name || ' - ' || sil.name
        ELSE sl.name
    END AS display_name

FROM shopping_locations sl
LEFT JOIN store_integration_locations sil
    ON sl.store_integration_location_id = sil.id
LEFT JOIN store_integrations si
    ON sil.store_integration_id = si.id;
```

---

## Migration Strategy

### Phase 1: Database Changes (Non-Breaking)

**Goals:**
- Add linking capability
- No changes to existing data
- Zero downtime

**Steps:**
1. Create migration 0259.sql
2. Add `store_integration_location_id` to `shopping_locations` (NULL default)
3. Add address fields to `shopping_locations` (NULL default)
4. Create indexes
5. Create `shopping_locations_with_integrations` view

**Risk:** None - All changes are additive

### Phase 2: Service Layer Updates

**Goals:**
- Enable new functionality
- Maintain backward compatibility

**Steps:**
1. Add new methods to `StoreIntegrationsService`
2. Update `BaseStoreIntegrationPlugin` signatures
3. Update Kroger plugin to create shopping_locations
4. Modify product creation to set `shopping_location_id`

**Testing:**
- Unit tests for new service methods
- Integration tests for shopping location linking
- Verify existing functionality unchanged

### Phase 3: API Endpoints

**Goals:**
- Expose new functionality via API

**Steps:**
1. Add new endpoints to `StoreIntegrationsApiController`
2. Update OpenAPI spec (`grocy.openapi.json`)
3. Add route definitions to `routes.php`

**Testing:**
- API endpoint tests
- Verify response formats
- Test error conditions

### Phase 4: UI Updates

**Goals:**
- Present unified interface
- Enable users to link stores

**Steps:**
1. Update `/shoppinglocations` view to show integration status
2. Add "Link Integration" modal
3. Update product form to show integration badges
4. Enhance shopping list modal
5. Update documentation

**Testing:**
- Manual UI testing
- Verify all workflows
- Test on mobile devices

### Phase 5: Data Migration (Optional)

**For users with existing `store_integration_locations`:**

Create a data migration script:
```php
// Migration script: link_existing_store_integrations.php

// Find all store_integration_locations without linked shopping_locations
$integrationLocations = $db->store_integration_locations();

foreach ($integrationLocations as $intLoc) {
    // Check if shopping_location already exists
    $existingSL = $db->shopping_locations()
        ->where('store_integration_location_id = :1', $intLoc->id)
        ->fetch();

    if (!$existingSL) {
        // Create new shopping_location
        $db->shopping_locations()->insert([
            'name' => $intLoc->name,
            'description' => 'Auto-migrated from store integration',
            'store_integration_location_id' => $intLoc->id,
            'address' => $intLoc->address,
            'city' => $intLoc->city,
            'state' => $intLoc->state,
            'zip_code' => $intLoc->zip_code,
            'phone' => $intLoc->phone
        ]);

        echo "Created shopping_location for: {$intLoc->name}\n";
    }
}
```

**This is optional and non-destructive** - Users can manually link or use auto-migration.

---

## Implementation Plan

### Sprint 1: Foundation (Week 1)

**Goals:** Database changes and service layer

**Tasks:**
- [ ] Create migration 0259.sql
- [ ] Run migration and verify schema
- [ ] Add new methods to `StoreIntegrationsService`
- [ ] Write unit tests for service methods
- [ ] Update `BaseStoreIntegrationPlugin` interface
- [ ] Update Kroger plugin implementation
- [ ] Test plugin changes with Kroger API

**Deliverables:**
- Migration file
- Service layer code
- Plugin updates
- Unit tests passing

### Sprint 2: API Layer (Week 2)

**Goals:** Expose new functionality via API

**Tasks:**
- [ ] Add new endpoints to `StoreIntegrationsApiController`
- [ ] Update routes.php
- [ ] Update OpenAPI spec
- [ ] Write API integration tests
- [ ] Test all endpoints with Postman/curl
- [ ] Update API documentation

**Deliverables:**
- New API endpoints
- Updated OpenAPI spec
- API tests passing
- API documentation

### Sprint 3: UI - Shopping Locations (Week 3)

**Goals:** Update shopping locations management UI

**Tasks:**
- [ ] Update `shoppinglocations.blade.php` view
- [ ] Add integration status badges
- [ ] Create "Link Integration" modal
- [ ] Update `shoppinglocations.js`
- [ ] Add address display
- [ ] Test all CRUD operations
- [ ] Mobile responsive testing

**Deliverables:**
- Updated shopping locations UI
- Link integration functionality
- Responsive design

### Sprint 4: UI - Product & Shopping List (Week 4)

**Goals:** Update product and shopping list UIs

**Tasks:**
- [ ] Update product form to show integration badges
- [ ] Enhance shopping list store search modal
- [ ] Update product creation flow
- [ ] Verify shopping_location_id is set correctly
- [ ] Test shopping list grouping
- [ ] Update aisle/location display in product views

**Deliverables:**
- Updated product form
- Enhanced shopping list modal
- Correct shopping_location_id assignment

### Sprint 5: Testing & Documentation (Week 5)

**Goals:** Comprehensive testing and documentation

**Tasks:**
- [ ] End-to-end testing of all workflows
- [ ] Test with multiple store integrations
- [ ] Test simple (non-integrated) stores
- [ ] Performance testing
- [ ] Write user documentation
- [ ] Update CLAUDE.md
- [ ] Create migration guide for existing users

**Deliverables:**
- Test report
- User documentation
- Migration guide
- Updated CLAUDE.md

---

## Testing Strategy

### Unit Tests

**StoreIntegrationsService:**
```php
// Test: GetOrCreateShoppingLocation creates new location
testGetOrCreateShoppingLocation_CreatesNew()

// Test: GetOrCreateShoppingLocation returns existing
testGetOrCreateShoppingLocation_ReturnsExisting()

// Test: LinkShoppingLocationToIntegration links correctly
testLinkShoppingLocationToIntegration()

// Test: GetIntegrationForShoppingLocation returns integration
testGetIntegrationForShoppingLocation_Integrated()

// Test: GetIntegrationForShoppingLocation returns null for simple store
testGetIntegrationForShoppingLocation_Simple()

// Test: UnlinkShoppingLocation removes link
testUnlinkShoppingLocation()
```

### Integration Tests

**Store Integration Workflow:**
1. Authenticate with Kroger
2. Search for store locations
3. Save location (should create shopping_location)
4. Verify shopping_location exists and is linked
5. Search for product
6. Add product to shopping list
7. Verify product has shopping_location_id set

**Simple Store Workflow:**
1. Create simple shopping_location (no integration)
2. Assign to product
3. Add product to shopping list
4. Verify workflow works as before

### UI Testing

**Manual Test Cases:**

1. **Link Integration to Existing Store:**
   - Go to Shopping Locations
   - Click "Link Integration" on existing store
   - Select integration and location
   - Verify link is created
   - Verify address fields populated

2. **Create Store from Integration:**
   - Go to Store Integrations
   - Search for location
   - Save location
   - Verify shopping_location is created

3. **Product Creation from Store Search:**
   - Open shopping list
   - Click "Add from store"
   - Search for product
   - Add to list
   - Verify product has shopping_location_id
   - Verify product shows "Default store" in UI

4. **Unlink Integration:**
   - Go to Shopping Locations
   - Click "Unlink" on integrated store
   - Verify link is removed
   - Verify store still exists as simple location

### Performance Tests

**Metrics to Monitor:**
- Shopping location listing time (with/without integrations)
- Product search modal load time
- API response times for new endpoints

**Acceptance Criteria:**
- No performance degradation for users without integrations
- API endpoints respond in < 500ms
- UI remains responsive

---

## Backward Compatibility

### Existing Data

**Zero Impact:**
- All existing `shopping_locations` records work unchanged
- All existing products with `shopping_location_id` work unchanged
- All existing shopping lists work unchanged

**New Field Defaults:**
- `store_integration_location_id` defaults to NULL
- Address fields default to NULL
- No existing data is modified

### Existing Code

**Service Methods:**
- All existing service methods continue to work
- New methods are additions only
- No breaking changes to method signatures

**API Endpoints:**
- All existing endpoints unchanged
- New endpoints are additions
- No changes to existing response formats

**UI:**
- Existing views work with enhanced data
- New UI elements are opt-in
- No breaking changes to existing workflows

### Upgrade Path

**For Users:**
1. Upgrade Grocy (migration runs automatically)
2. Continue using existing shopping_locations as before
3. Optionally: Set up store integrations
4. Optionally: Link existing locations to integrations

**No Action Required** - Everything continues working

---

## Future Considerations

### Multi-Store Products

Currently: Product has one `shopping_location_id`

**Future Enhancement:**
- Product available at multiple stores
- Price comparison across stores
- Automatic selection of cheapest store
- "Where can I buy this?" view

**Implementation:**
- New table: `product_shopping_locations` (many-to-many)
- Keep `products.shopping_location_id` as "preferred store"
- UI shows all available stores

### Store Availability Tracking

**Feature:**
- Track which products are available at which stores
- "Out of stock" notifications
- Automatic store switching

**Implementation:**
- Use `product_store_metadata.availability` field
- Add background job to refresh availability
- Show availability in product card

### Price History & Tracking

**Feature:**
- Track price changes over time
- Show price trends
- Alert on price drops

**Implementation:**
- New table: `product_price_history`
- Chart in product view
- Price alert system

### Recipe-to-Store Mapping

**Feature:**
- "Add recipe to shopping list" with store optimization
- Group ingredients by store
- "Best store for this recipe" recommendation

**Implementation:**
- Analyze recipe ingredients
- Find stores with most ingredients
- Optimize shopping list

### Store Rewards Integration

**Feature:**
- Import store rewards/coupons
- Apply discounts automatically
- Track savings

**Implementation:**
- Extend plugin API for rewards
- New `store_coupons` table
- Integration in shopping list UI

---

## Risk Assessment

### High Risk: Plugin Breaking Changes

**Risk:** Updated plugin interface breaks Kroger implementation

**Mitigation:**
- Add new methods as optional (with defaults)
- Keep existing method signatures
- Provide migration path for plugins
- Test Kroger plugin thoroughly

**Likelihood:** Low
**Impact:** High
**Priority:** High

### Medium Risk: Performance Degradation

**Risk:** Additional joins slow down shopping location queries

**Mitigation:**
- Add appropriate indexes
- Use view for complex queries
- Cache integration status
- Monitor query performance

**Likelihood:** Low
**Impact:** Medium
**Priority:** Medium

### Low Risk: User Confusion

**Risk:** Users confused by two ways to create stores

**Mitigation:**
- Clear UI labels and help text
- Tutorial/wizard for first-time setup
- Documentation with screenshots
- Consistent terminology

**Likelihood:** Medium
**Impact:** Low
**Priority:** Low

---

## Open Questions

### Q1: Should we deprecate direct store_integration_locations management?

**Options:**
1. Always create shopping_location when saving integration location
2. Allow both direct and linked approaches
3. Gradually phase out direct approach

**Recommendation:** Option 1 - Always create shopping_location

**Rationale:** Simplifies mental model, ensures consistency

---

### Q2: How to handle duplicate shopping_location names?

**Scenario:** User has "Kroger" (simple) and creates integration to "Kroger Store #123"

**Options:**
1. Auto-suffix with number: "Kroger (2)"
2. Force unique names during link
3. Allow duplicates, show full address in pickers

**Recommendation:** Option 3 with enhanced display

**Rationale:** Different Kroger locations should have different names anyway

---

### Q3: Should address fields be required for integrated stores?

**Current:** Optional fields, populated from integration

**Options:**
1. Required for integrated stores
2. Always optional
3. Required if manually entered, optional if from integration

**Recommendation:** Option 2 - Always optional

**Rationale:** Some APIs might not provide full address data

---

### Q4: What happens if integration location is deleted?

**Scenario:** User deletes `store_integration_location` but `shopping_location` references it

**Options:**
1. CASCADE DELETE (delete shopping_location too)
2. SET NULL (unlink, keep shopping_location)
3. RESTRICT (prevent deletion if linked)

**Recommendation:** Option 2 - SET NULL (defined in migration)

**Rationale:** Preserve shopping_location as simple store

---

### Q5: Should we auto-migrate existing integrations?

**Question:** Run migration script to create shopping_locations for existing integrations?

**Options:**
1. Auto-migrate on upgrade
2. Provide migration script, user runs manually
3. Users manually link via UI

**Recommendation:** Option 3 with Option 2 available

**Rationale:** User has control, can choose custom names

---

## Success Criteria

### Functional Requirements

✅ Users can link shopping_locations to store integrations
✅ Users can search store locations via integrations
✅ Users can search products and add to shopping list
✅ Products created from store search have shopping_location_id set
✅ Shopping list displays correct "Default store"
✅ Shopping list groups by store correctly
✅ Users can manage both simple and integrated stores
✅ Store integration authentication works
✅ Product metadata (price, aisle) is stored and displayed

### Non-Functional Requirements

✅ Zero breaking changes to existing data
✅ All existing shopping_locations work unchanged
✅ No performance degradation for non-integrated users
✅ API response times < 500ms
✅ Mobile-responsive UI
✅ Comprehensive documentation
✅ Test coverage > 80%

### User Experience

✅ Single, clear "Stores" concept
✅ Optional integration clearly communicated
✅ Smooth workflow from store search to shopping list
✅ Minimal clicks to link integration
✅ Clear visual indicators of integration status

---

## Conclusion

This design consolidates two parallel store systems into a unified model while maintaining full backward compatibility. By making `shopping_locations` the single source of truth and allowing optional linking to API integrations, we provide a clear mental model for users and leverage existing infrastructure.

The phased implementation approach minimizes risk and allows for iterative testing and refinement. Users who don't use integrations see no changes, while users who adopt integrations benefit from seamless integration between simple stores and API-powered stores.

---

## Appendix A: Database Schema Diagrams

### Before Integration

```
┌──────────────────────┐         ┌──────────────────────┐
│  shopping_locations  │         │ store_integrations   │
├──────────────────────┤         ├──────────────────────┤
│ id                   │         │ id                   │
│ name                 │         │ name                 │
│ description          │         │ store_type           │
└──────────────────────┘         │ access_token         │
         ▲                        │ ...                  │
         │                        └──────────────────────┘
         │                                 │
    (referenced by)                    (has many)
         │                                 ▼
┌──────────────────────┐         ┌─────────────────────────────┐
│     products         │         │ store_integration_locations │
├──────────────────────┤         ├─────────────────────────────┤
│ id                   │         │ id                          │
│ shopping_location_id │         │ store_integration_id        │
│ ...                  │         │ external_location_id        │
└──────────────────────┘         │ name                        │
                                 │ address                     │
                                 │ ...                         │
                                 └─────────────────────────────┘

⚠️ PROBLEM: Two separate systems, no connection
```

### After Integration

```
┌──────────────────────────────────────────┐
│        store_integrations                │
│  (API Configuration)                     │
├──────────────────────────────────────────┤
│ id                                       │
│ name                                     │
│ store_type                               │
│ access_token                             │
│ ...                                      │
└──────────────────────────────────────────┘
                    │
                    │ (has many)
                    ▼
┌──────────────────────────────────────────┐
│     store_integration_locations          │
│  (Physical Store Locations)              │
├──────────────────────────────────────────┤
│ id                                       │
│ store_integration_id                     │
│ external_location_id                     │
│ name                                     │
│ address                                  │
│ ...                                      │
└──────────────────────────────────────────┘
                    │
                    │ (optionally linked by)
                    ▼
┌──────────────────────────────────────────┐
│        shopping_locations                │
│  (User's Preferred Stores)               │
├──────────────────────────────────────────┤
│ id                                       │
│ name                                     │
│ description                              │
│ store_integration_location_id  ← NEW    │
│ address                        ← NEW    │
│ city                           ← NEW    │
│ ...                                      │
└──────────────────────────────────────────┘
                    ▲
                    │ (referenced by)
                    │
┌──────────────────────────────────────────┐
│            products                      │
├──────────────────────────────────────────┤
│ id                                       │
│ shopping_location_id                     │
│ store_location_aisle                     │
│ store_location_shelf                     │
│ ...                                      │
└──────────────────────────────────────────┘

✅ SOLUTION: Unified system, shopping_locations as single source of truth
```

---

## Appendix B: API Endpoint Reference

### New Endpoints

```
GET    /api/shopping-locations/with-integration-status
       → Returns all shopping locations with integration data

GET    /api/shopping-locations/{id}/integration-status
       → Returns integration status for specific location

POST   /api/shopping-locations/{id}/link-integration
       Body: { "integration_location_id": 123 }
       → Links shopping location to integration location

POST   /api/shopping-locations/{id}/unlink-integration
       → Unlinks shopping location from integration

GET    /api/store-integrations/{id}/shopping-locations
       → Returns all shopping_locations linked to this integration
```

### Modified Endpoints

```
POST   /api/store-integrations/products/create-from-store
       Body: {
           ...,
           "integration_location_id": 123  ← NEW: Optional
       }
       Response: {
           "product_id": 456,
           "shopping_location_id": 789,    ← NEW
           "created": true
       }

POST   /api/store-integrations/{id}/locations
       Body: { ... }
       Response: {
           "integration_location_id": 123,
           "shopping_location_id": 456     ← NEW
       }
```

---

**Document End**
