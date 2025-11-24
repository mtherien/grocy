# Store Integration Consolidation Design Document v2.0

**Version:** 2.0 (Simplified - No Backward Compatibility Required)
**Date:** 2025-11-24
**Status:** Draft for Review
**Branch:** store-integration

---

## Executive Summary

Since the store integration feature has **not yet been released**, we can perform a clean consolidation by **merging the functionality directly into the existing `shopping_locations` table**. This eliminates the complexity of maintaining separate tables and provides a much simpler, more intuitive architecture.

**Key Changes from v1.0:**
- ❌ No `store_integration_locations` table (consolidated into `shopping_locations`)
- ❌ No linking/indirection layer
- ✅ Direct enhancement of `shopping_locations` with integration fields
- ✅ Much simpler mental model: "A store can optionally have an API integration"
- ✅ Cleaner codebase with less complexity

---

## Current vs. Proposed Architecture

### Current Architecture (Parallel Systems)

```
┌──────────────────────┐         ┌──────────────────────┐
│  shopping_locations  │         │ store_integrations   │
│  (Legacy)            │         │  (New, Unreleased)   │
├──────────────────────┤         ├──────────────────────┤
│ id                   │         │ id                   │
│ name                 │         │ name                 │
│ description          │         │ store_type           │
└──────────────────────┘         │ access_token         │
         ▲                        │ ...                  │
         │                        └──────────────────────┘
         │                                 │
    (used by)                          (has many)
         │                                 ▼
┌──────────────────────┐         ┌─────────────────────────────┐
│     products         │         │ store_integration_locations │
├──────────────────────┤         ├─────────────────────────────┤
│ shopping_location_id │         │ store_integration_id        │
│ ...                  │         │ external_location_id        │
└──────────────────────┘         │ address, city, state...     │
                                 └─────────────────────────────┘

❌ PROBLEM: Two separate, disconnected systems
```

### Proposed Architecture (Unified System)

```
┌─────────────────────────────────────────────────────────┐
│              shopping_locations (Enhanced)              │
│         (Single Source of Truth for Stores)             │
├─────────────────────────────────────────────────────────┤
│ -- Core fields (existing)                               │
│ id                                                      │
│ name                                                    │
│ description                                             │
│                                                         │
│ -- Integration fields (NEW)                             │
│ store_integration_id      (FK to store_integrations)   │
│ external_location_id      (Store's API identifier)     │
│                                                         │
│ -- Physical location fields (NEW)                       │
│ address                                                 │
│ city                                                    │
│ state                                                   │
│ zip_code                                                │
│ phone                                                   │
│ latitude                                                │
│ longitude                                               │
│                                                         │
│ -- Metadata (NEW)                                       │
│ metadata_json             (Full API response)           │
└─────────────────────────────────────────────────────────┘
         │                              ▲
         │ (references)                 │ (references)
         ▼                              │
┌──────────────────────┐       ┌──────────────────────┐
│ store_integrations   │       │     products         │
│  (API Config)        │       ├──────────────────────┤
├──────────────────────┤       │ shopping_location_id │
│ id                   │       │ ...                  │
│ store_type           │       └──────────────────────┘
│ access_token         │
│ refresh_token        │
│ ...                  │
└──────────────────────┘

✅ SOLUTION: Single unified table, optional API integration
```

### Key Differences

| Aspect | v1.0 (Backward Compatible) | v2.0 (Clean Slate) |
|--------|----------------------------|---------------------|
| **Tables** | 3 (shopping_locations, store_integrations, store_integration_locations) | 2 (shopping_locations, store_integrations) |
| **Relationships** | shopping_locations ← links to → store_integration_locations ← belongs to → store_integrations | shopping_locations → belongs to → store_integrations |
| **Complexity** | High (linking layer) | Low (direct relationship) |
| **Mental Model** | "Stores can be linked to integration locations" | "Stores can have API integration" |
| **Code Changes** | Moderate (maintain both paths) | Minimal (single path) |
| **Future Maintenance** | Higher (more moving parts) | Lower (simpler structure) |

---

## Detailed Design

### Database Schema Changes

#### Drop Unreleased Tables

```sql
-- Migration 0259.sql - Part 1: Cleanup
-- These tables were never released, so we can drop them

DROP TABLE IF EXISTS store_integration_locations;
DROP TABLE IF EXISTS product_store_metadata;

-- Note: We'll incorporate their functionality directly into shopping_locations
```

#### Enhance shopping_locations Table

```sql
-- Migration 0259.sql - Part 2: Enhance shopping_locations

-- Add integration reference
ALTER TABLE shopping_locations
ADD COLUMN store_integration_id INTEGER REFERENCES store_integrations(id) ON DELETE SET NULL;

-- Add external store identifier (from API)
ALTER TABLE shopping_locations
ADD COLUMN external_location_id TEXT;

-- Add physical location data
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

ALTER TABLE shopping_locations
ADD COLUMN latitude REAL;

ALTER TABLE shopping_locations
ADD COLUMN longitude REAL;

-- Add metadata storage
ALTER TABLE shopping_locations
ADD COLUMN metadata_json TEXT;

-- Add flag to indicate if this is the primary/preferred location for an integration
ALTER TABLE shopping_locations
ADD COLUMN is_primary INTEGER DEFAULT 0;

-- Create indexes
CREATE INDEX idx_shopping_locations_integration ON shopping_locations(store_integration_id);
CREATE INDEX idx_shopping_locations_external_id ON shopping_locations(store_integration_id, external_location_id);
CREATE INDEX idx_shopping_locations_primary ON shopping_locations(store_integration_id, is_primary);
```

#### Enhance product_store_metadata Table

The `product_store_metadata` table is useful as-is, but we need to update its foreign key:

```sql
-- Migration 0259.sql - Part 3: Update product_store_metadata

-- Change reference from store_integration_id to shopping_location_id
-- This allows product metadata per actual shopping location

-- Rename the existing column first
ALTER TABLE product_store_metadata
RENAME COLUMN store_integration_id TO store_integration_id_old;

-- Add new column
ALTER TABLE product_store_metadata
ADD COLUMN shopping_location_id INTEGER REFERENCES shopping_locations(id);

-- Note: No data migration needed since feature is unreleased

-- Drop old column (after verifying no data)
-- ALTER TABLE product_store_metadata DROP COLUMN store_integration_id_old;

-- Update index
DROP INDEX IF EXISTS idx_product_store_metadata_store_integration_id;
CREATE INDEX idx_product_store_metadata_shopping_location_id ON product_store_metadata(shopping_location_id);
```

#### Updated Schema

**shopping_locations** (complete)
```sql
CREATE TABLE shopping_locations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    description TEXT,

    -- Integration fields
    store_integration_id INTEGER,
    external_location_id TEXT,
    is_primary INTEGER DEFAULT 0,

    -- Physical location
    address TEXT,
    city TEXT,
    state TEXT,
    zip_code TEXT,
    phone TEXT,
    latitude REAL,
    longitude REAL,

    -- Metadata
    metadata_json TEXT,
    row_created_timestamp DATETIME DEFAULT (datetime('now', 'localtime')),

    FOREIGN KEY (store_integration_id)
        REFERENCES store_integrations(id)
        ON DELETE SET NULL
);
```

**store_integrations** (unchanged)
```sql
CREATE TABLE store_integrations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    store_type TEXT NOT NULL,
    access_token TEXT,
    refresh_token TEXT,
    token_expires_at DATETIME,
    configuration TEXT,
    active INTEGER DEFAULT 1,
    row_created_timestamp DATETIME DEFAULT (datetime('now', 'localtime'))
);
```

**product_store_metadata** (updated)
```sql
CREATE TABLE product_store_metadata (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    product_id INTEGER NOT NULL,
    shopping_location_id INTEGER NOT NULL,  -- Changed from store_integration_id
    external_product_id TEXT,
    aisle TEXT,
    shelf TEXT,
    department TEXT,
    category TEXT,
    price REAL,
    price_unit TEXT,
    availability TEXT,
    metadata_json TEXT,
    last_updated DATETIME DEFAULT (datetime('now', 'localtime')),
    row_created_timestamp DATETIME DEFAULT (datetime('now', 'localtime')),

    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (shopping_location_id) REFERENCES shopping_locations(id)
);
```

### Semantic Meaning

A `shopping_location` can be:

1. **Simple Store** (no integration)
   - `store_integration_id` = NULL
   - `external_location_id` = NULL
   - Example: "Local Farmers Market", "Corner Store"

2. **Integrated Store** (with API capabilities)
   - `store_integration_id` = ID of integration (e.g., Kroger API)
   - `external_location_id` = Store's ID in the external system (e.g., "70100123")
   - Address fields populated from API
   - Example: "Kroger Store #123 - Main St"

### Relationship Examples

```
Store Integration: Kroger API (id=1)
  ├─ Shopping Location: "Kroger Downtown" (id=1, store_integration_id=1, external_location_id="70100123")
  ├─ Shopping Location: "Kroger Westside" (id=2, store_integration_id=1, external_location_id="70100456")
  └─ Shopping Location: "Kroger Near Work" (id=3, store_integration_id=1, external_location_id="70100789", is_primary=1)

Store Integration: Walmart API (id=2)
  └─ Shopping Location: "Walmart Supercenter" (id=4, store_integration_id=2, external_location_id="5260")

Simple Stores (no integration):
  ├─ Shopping Location: "Farmers Market" (id=5, store_integration_id=NULL)
  └─ Shopping Location: "Corner Store" (id=6, store_integration_id=NULL)
```

---

## Service Layer Changes

### StoreIntegrationsService Updates

**New/Modified Methods:**

```php
/**
 * Save a store location from API search
 * This now creates a shopping_location directly
 *
 * @param int $integrationId
 * @param array $locationData API response data
 * @param bool $isPrimary Set as primary location for this integration
 * @param string|null $customName Optional custom name
 * @return int The shopping_location ID
 */
public function SaveStoreLocation($integrationId, $locationData, $isPrimary = false, $customName = null)
{
    // Unset other primary locations if this is primary
    if ($isPrimary) {
        $this->getDatabase()->shopping_locations()
            ->where('store_integration_id = :1', $integrationId)
            ->update(['is_primary' => 0]);
    }

    // Check if location already exists
    $existing = $this->getDatabase()->shopping_locations()
        ->where('store_integration_id = :1', $integrationId)
        ->where('external_location_id = :2', $locationData['locationId'])
        ->fetch();

    $data = [
        'name' => $customName ?? $locationData['name'],
        'description' => sprintf('Integrated %s store', $this->GetIntegrationStoreName($integrationId)),
        'store_integration_id' => $integrationId,
        'external_location_id' => $locationData['locationId'],
        'address' => $locationData['address']['addressLine1'] ?? null,
        'city' => $locationData['address']['city'] ?? null,
        'state' => $locationData['address']['state'] ?? null,
        'zip_code' => $locationData['address']['zipCode'] ?? null,
        'phone' => $locationData['phone'] ?? null,
        'latitude' => $locationData['geolocation']['latitude'] ?? null,
        'longitude' => $locationData['geolocation']['longitude'] ?? null,
        'is_primary' => $isPrimary ? 1 : 0,
        'metadata_json' => json_encode($locationData)
    ];

    if ($existing) {
        $existing->update($data);
        return $existing->id;
    } else {
        $this->getDatabase()->shopping_locations()->insert($data);
        return $this->getDatabase()->lastInsertId();
    }
}

/**
 * Get all shopping locations for a specific integration
 *
 * @param int $integrationId
 * @return array
 */
public function GetShoppingLocationsForIntegration($integrationId)
{
    return $this->getDatabase()->shopping_locations()
        ->where('store_integration_id = :1', $integrationId);
}

/**
 * Get the primary shopping location for an integration
 *
 * @param int $integrationId
 * @return object|null
 */
public function GetPrimaryShoppingLocation($integrationId)
{
    return $this->getDatabase()->shopping_locations()
        ->where('store_integration_id = :1', $integrationId)
        ->where('is_primary = 1')
        ->fetch();
}

/**
 * Search products - returns shopping_location_id in results
 *
 * @param int $integrationId
 * @param string $searchTerm
 * @param int|null $shoppingLocationId Optional: search at specific location
 * @return array
 */
public function SearchProducts($integrationId, $searchTerm, $shoppingLocationId = null)
{
    $integration = $this->GetById($integrationId);

    // Get external location ID if shopping location provided
    $externalLocationId = null;
    if ($shoppingLocationId) {
        $location = $this->getDatabase()->shopping_locations()
            ->where('id = :1', $shoppingLocationId)
            ->fetch();

        if ($location && $location->store_integration_id == $integrationId) {
            $externalLocationId = $location->external_location_id;
        }
    } else {
        // Use primary location if no location specified
        $primaryLocation = $this->GetPrimaryShoppingLocation($integrationId);
        if ($primaryLocation) {
            $externalLocationId = $primaryLocation->external_location_id;
            $shoppingLocationId = $primaryLocation->id;
        }
    }

    // Get the appropriate plugin and search
    $storeService = $this->GetStoreServiceForIntegration($integrationId);
    $results = $storeService->SearchProducts($integrationId, $searchTerm, $externalLocationId);

    // Add shopping_location_id to each result
    foreach ($results as &$result) {
        $result['shopping_location_id'] = $shoppingLocationId;
    }

    return $results;
}

/**
 * Get product metadata for a specific shopping location
 *
 * @param int $productId
 * @param int $shoppingLocationId
 * @return object|null
 */
public function GetProductMetadataForLocation($productId, $shoppingLocationId)
{
    return $this->getDatabase()->product_store_metadata()
        ->where('product_id = :1', $productId)
        ->where('shopping_location_id = :2', $shoppingLocationId)
        ->fetch();
}

/**
 * Save product metadata for a shopping location
 *
 * @param int $productId
 * @param int $shoppingLocationId
 * @param array $metadata
 */
public function SaveProductMetadata($productId, $shoppingLocationId, $metadata)
{
    $existing = $this->GetProductMetadataForLocation($productId, $shoppingLocationId);

    $data = [
        'product_id' => $productId,
        'shopping_location_id' => $shoppingLocationId,
        'external_product_id' => $metadata['external_product_id'] ?? null,
        'aisle' => $metadata['aisle'] ?? null,
        'shelf' => $metadata['shelf'] ?? null,
        'department' => $metadata['department'] ?? null,
        'category' => $metadata['category'] ?? null,
        'price' => $metadata['price'] ?? null,
        'price_unit' => $metadata['price_unit'] ?? null,
        'availability' => $metadata['availability'] ?? null,
        'metadata_json' => json_encode($metadata['raw_data'] ?? []),
        'last_updated' => date('Y-m-d H:i:s')
    ];

    if ($existing) {
        $existing->update($data);
    } else {
        $this->getDatabase()->product_store_metadata()->insert($data);
    }
}

/**
 * Check if a shopping location is integrated
 *
 * @param int $shoppingLocationId
 * @return bool
 */
public function IsShoppingLocationIntegrated($shoppingLocationId)
{
    $location = $this->getDatabase()->shopping_locations()
        ->where('id = :1', $shoppingLocationId)
        ->fetch();

    return $location && !empty($location->store_integration_id);
}

/**
 * Get integration for a shopping location
 *
 * @param int $shoppingLocationId
 * @return object|null
 */
public function GetIntegrationForShoppingLocation($shoppingLocationId)
{
    $location = $this->getDatabase()->shopping_locations()
        ->where('id = :1', $shoppingLocationId)
        ->fetch();

    if (!$location || !$location->store_integration_id) {
        return null;
    }

    return $this->GetById($location->store_integration_id);
}
```

### BaseStoreIntegrationPlugin Updates

**Updated Method Signatures:**

```php
abstract class BaseStoreIntegrationPlugin
{
    // ... OAuth methods unchanged ...

    /**
     * Search for store locations
     * Returns data in format compatible with shopping_locations table
     */
    abstract public function SearchStoreLocations($integrationId, $zipCode = null, $lat = null, $lon = null, $radiusMiles = 10);

    /**
     * Save store location - REMOVED
     * This is now handled by StoreIntegrationsService::SaveStoreLocation()
     */
    // REMOVED: public function SaveStoreLocation(...)

    /**
     * Send shopping list to store
     * Uses shopping_location_id instead of locationId
     */
    abstract public function SendShoppingList($integrationId, $shoppingListId, $shoppingLocationId = null);

    /**
     * Search products
     * Uses external_location_id from shopping_location
     */
    abstract public function SearchProducts($integrationId, $searchTerm, $externalLocationId = null);

    /**
     * Lookup product metadata
     * Returns metadata to be saved via StoreIntegrationsService
     */
    abstract public function LookupProductMetadata($integrationId, $productId, $shoppingLocationId = null);

    // ... metadata methods unchanged ...
}
```

**Key Changes:**
- ❌ Removed `SaveStoreLocation()` from plugin (now in service)
- ✅ Changed `SendShoppingList()` to use `$shoppingLocationId` instead of `$locationId`
- ✅ Changed `LookupProductMetadata()` to accept `$shoppingLocationId`
- ✅ Simplified plugin responsibilities

---

## Controller Changes

### StoreIntegrationsApiController Updates

**Modified Endpoints:**

```php
/**
 * POST /api/store-integrations/{integrationId}/locations
 * Save a store location (creates shopping_location)
 */
public function SaveStoreLocation(Request $request, Response $response, array $args)
{
    try {
        $requestBody = $this->GetParsedAndFilteredRequestBody($request);

        if (!isset($requestBody['location_data'])) {
            return $this->GenericErrorResponse($response, 'location_data is required');
        }

        $isPrimary = $requestBody['is_primary'] ?? false;
        $customName = $requestBody['custom_name'] ?? null;

        $shoppingLocationId = $this->getStoreIntegrationsService()->SaveStoreLocation(
            $args['integrationId'],
            $requestBody['location_data'],
            $isPrimary,
            $customName
        );

        return $this->ApiResponse($response, [
            'shopping_location_id' => $shoppingLocationId
        ]);
    } catch (\Exception $ex) {
        return $this->GenericErrorResponse($response, $ex->getMessage());
    }
}

/**
 * GET /api/store-integrations/{integrationId}/locations
 * Get all shopping locations for this integration
 */
public function GetLocationsForIntegration(Request $request, Response $response, array $args)
{
    $locations = $this->getStoreIntegrationsService()
        ->GetShoppingLocationsForIntegration($args['integrationId']);

    return $this->FilteredApiResponse($response, $locations, $request->getQueryParams());
}

/**
 * POST /api/store-integrations/{integrationId}/products/search
 * Search products at a specific shopping location
 */
public function SearchProducts(Request $request, Response $response, array $args)
{
    try {
        $requestBody = $this->GetParsedAndFilteredRequestBody($request);

        if (!isset($requestBody['search_term'])) {
            return $this->GenericErrorResponse($response, 'search_term is required');
        }

        $shoppingLocationId = $requestBody['shopping_location_id'] ?? null;

        $results = $this->getStoreIntegrationsService()->SearchProducts(
            $args['integrationId'],
            $requestBody['search_term'],
            $shoppingLocationId
        );

        return $this->ApiResponse($response, $results);
    } catch (\Exception $ex) {
        return $this->GenericErrorResponse($response, $ex->getMessage());
    }
}

/**
 * POST /api/store-integrations/{integrationId}/send-shopping-list
 * Send shopping list to store
 */
public function SendShoppingList(Request $request, Response $response, array $args)
{
    try {
        $requestBody = $this->GetParsedAndFilteredRequestBody($request);

        if (!isset($requestBody['shopping_list_id'])) {
            return $this->GenericErrorResponse($response, 'shopping_list_id is required');
        }

        $shoppingLocationId = $requestBody['shopping_location_id'] ?? null;

        $result = $this->getStoreIntegrationsService()->SendShoppingListToStore(
            $args['integrationId'],
            $requestBody['shopping_list_id'],
            $shoppingLocationId
        );

        return $this->ApiResponse($response, $result);
    } catch (\Exception $ex) {
        return $this->GenericErrorResponse($response, $ex->getMessage());
    }
}

/**
 * POST /api/store-integrations/products/create-from-store
 * Create product from store data
 */
public function CreateProductFromStoreData(Request $request, Response $response, array $args)
{
    try {
        $requestBody = $this->GetParsedAndFilteredRequestBody($request);

        // ... existing validation ...

        $shoppingLocationId = $requestBody['shopping_location_id'] ?? null;

        // Check for existing product by UPC
        if (!empty($requestBody['upc'])) {
            $existingBarcode = $this->getDatabase()->product_barcodes()
                ->where('barcode = :1', $requestBody['upc'])
                ->fetch();

            if ($existingBarcode) {
                $product = $this->getDatabase()->products()
                    ->where('id = :1', $existingBarcode->product_id)
                    ->fetch();

                // Update shopping_location_id if provided and not already set
                if ($shoppingLocationId && empty($product->shopping_location_id)) {
                    $product->update(['shopping_location_id' => $shoppingLocationId]);
                }

                return $this->ApiResponse($response, [
                    'product_id' => $product->id,
                    'created' => false
                ]);
            }
        }

        // Create new product
        $productData = [
            'name' => $requestBody['name'],
            'description' => $requestBody['description'] ?? null,
            'location_id' => $this->getUsersService()->GetUserSettings(GROCY_USER_ID)['stock_default_location_id'],
            'qu_id_stock' => $this->getUsersService()->GetUserSettings(GROCY_USER_ID)['stock_default_quantity_unit_id'],
            'qu_id_purchase' => $this->getUsersService()->GetUserSettings(GROCY_USER_ID)['stock_default_quantity_unit_id'],
            'shopping_location_id' => $shoppingLocationId,
            'store_location_aisle' => $requestBody['aisle'] ?? null,
            'store_location_shelf' => $requestBody['shelf'] ?? null,
            'store_location_department' => $requestBody['department'] ?? null,
            'store_location_updated' => date('Y-m-d H:i:s')
        ];

        $this->getDatabase()->products()->insert($productData);
        $productId = $this->getDatabase()->lastInsertId();

        // Add barcode if provided
        if (!empty($requestBody['upc'])) {
            $this->getDatabase()->product_barcodes()->insert([
                'product_id' => $productId,
                'barcode' => $requestBody['upc']
            ]);
        }

        // Download and save product image
        if (!empty($requestBody['image_url'])) {
            // ... existing image download code ...
        }

        return $this->ApiResponse($response, [
            'product_id' => $productId,
            'created' => true
        ]);
    } catch (\Exception $ex) {
        return $this->GenericErrorResponse($response, $ex->getMessage());
    }
}

/**
 * GET /api/products/{productId}/store-metadata
 * Get product metadata for all shopping locations
 */
public function GetProductStoreMetadata(Request $request, Response $response, array $args)
{
    $metadata = $this->getDatabase()->product_store_metadata()
        ->where('product_id = :1', $args['productId']);

    return $this->FilteredApiResponse($response, $metadata, $request->getQueryParams());
}

/**
 * GET /api/products/{productId}/store-metadata/{locationId}
 * Get product metadata for specific shopping location
 */
public function GetProductStoreMetadataForLocation(Request $request, Response $response, array $args)
{
    $metadata = $this->getStoreIntegrationsService()->GetProductMetadataForLocation(
        $args['productId'],
        $args['locationId']
    );

    if (!$metadata) {
        return $this->GenericErrorResponse($response, 'Metadata not found', 404);
    }

    return $this->ApiResponse($response, $metadata);
}
```

**New Endpoints:**

```php
/**
 * DELETE /api/shopping-locations/{locationId}
 * Delete a shopping location
 * (Standard CRUD via objects API, but worth noting)
 */

/**
 * PUT /api/shopping-locations/{locationId}/set-primary
 * Set a shopping location as primary for its integration
 */
public function SetPrimaryLocation(Request $request, Response $response, array $args)
{
    try {
        $location = $this->getDatabase()->shopping_locations()
            ->where('id = :1', $args['locationId'])
            ->fetch();

        if (!$location) {
            return $this->GenericErrorResponse($response, 'Shopping location not found', 404);
        }

        if (!$location->store_integration_id) {
            return $this->GenericErrorResponse($response, 'Location is not integrated');
        }

        // Unset other primary locations
        $this->getDatabase()->shopping_locations()
            ->where('store_integration_id = :1', $location->store_integration_id)
            ->update(['is_primary' => 0]);

        // Set this as primary
        $location->update(['is_primary' => 1]);

        return $this->EmptyApiResponse($response);
    } catch (\Exception $ex) {
        return $this->GenericErrorResponse($response, $ex->getMessage());
    }
}
```

---

## UI Changes

### Shopping Locations Page (`/shoppinglocations`)

**Enhanced Table:**

| Name | Type | Address | Primary | Actions |
|------|------|---------|---------|---------|
| My Local Kroger | 🔗 Kroger | 123 Main St, Springfield, OH | ⭐ | Edit \| Delete |
| Kroger Westside | 🔗 Kroger | 456 West Ave, Springfield, OH | | Edit \| Delete \| Set Primary |
| Walmart | 🔗 Walmart | 789 Plaza Dr, Springfield, OH | ⭐ | Edit \| Delete |
| Farmers Market | Simple | - | - | Edit \| Delete |

**Features:**
- Shows integration type with icon
- Displays full address for integrated stores
- Shows primary indicator (⭐) per integration
- "Set Primary" action for non-primary integrated stores

**Add/Edit Form Enhancement:**

```html
<form>
    <input type="text" name="name" placeholder="Store Name" required>
    <textarea name="description" placeholder="Description"></textarea>

    <!-- NEW: Integration Section -->
    <fieldset>
        <legend>Store Integration (Optional)</legend>

        <select name="store_integration_id" id="integration-select">
            <option value="">No integration</option>
            <option value="1">Kroger API</option>
            <option value="2">Walmart API</option>
        </select>

        <div id="integration-location-search" class="hidden">
            <h4>Search for Store Location</h4>
            <input type="text" placeholder="Zip Code" id="zip-search">
            <button type="button" id="search-locations-btn">Search</button>

            <div id="location-results">
                <!-- Populated via JavaScript -->
            </div>
        </div>

        <div id="manual-location-entry">
            <h4>Or Enter Manually</h4>
            <input type="text" name="address" placeholder="Address">
            <input type="text" name="city" placeholder="City">
            <input type="text" name="state" placeholder="State">
            <input type="text" name="zip_code" placeholder="Zip Code">
            <input type="text" name="phone" placeholder="Phone">
        </div>
    </fieldset>

    <button type="submit">Save</button>
</form>
```

**Workflow:**
1. User creates new shopping location
2. Optionally selects integration
3. If integration selected:
   - Can search for locations via API
   - Or manually enter location data
4. Location is saved with integration reference

### Shopping List Modal (`store-product-search-modal`)

**Simplified Store Picker:**

```html
<select id="store-search-location">
    <option value="">Select a store</option>

    <!-- Integrated stores -->
    <optgroup label="Kroger">
        <option value="1">My Local Kroger - 123 Main St</option>
        <option value="2">Kroger Westside - 456 West Ave</option>
    </optgroup>

    <optgroup label="Walmart">
        <option value="4">Walmart Supercenter - 789 Plaza Dr</option>
    </optgroup>

    <!-- Non-integrated stores (disabled for search) -->
    <optgroup label="Other Stores">
        <option value="5" disabled>Farmers Market (no online search)</option>
    </optgroup>
</select>
```

**Search Flow:**
1. User selects integrated shopping location
2. Enters search term
3. Results are displayed with prices/images
4. On add:
   - Product is created with `shopping_location_id` set
   - Product metadata is saved to `product_store_metadata`
   - Product appears in shopping list with correct store

### Product Form Enhancement

**Shopping Location Picker:**

```html
<label>Default Shopping Location</label>
<select name="shopping_location_id">
    <option value="">None</option>
    <option value="1">🔗 My Local Kroger</option>
    <option value="2">🔗 Kroger Westside</option>
    <option value="4">🔗 Walmart Supercenter</option>
    <option value="5">Farmers Market</option>
</select>

<!-- Show store metadata if available -->
<div id="store-metadata-display">
    <h4>Store Information</h4>
    <p><strong>Aisle:</strong> <span id="aisle-display">5</span></p>
    <p><strong>Department:</strong> <span id="department-display">Dairy</span></p>
    <p><strong>Price:</strong> <span id="price-display">$3.99</span></p>
    <p><small>Last updated: <span id="updated-display">2025-11-20</span></small></p>
    <button type="button" id="refresh-metadata-btn">Refresh from Store</button>
</div>
```

**Features:**
- Shows integration icon for integrated stores
- Displays product metadata if available
- "Refresh from Store" button to update metadata via API

### Store Integrations Management (`/storeintegrations`)

**Simplified View:**

Since locations are now managed via `/shoppinglocations`, this page focuses on:
- Listing integrations (Kroger API, Walmart API, etc.)
- Authentication status
- OAuth flow
- Configuration
- Link to manage locations: "Manage Stores →"

**Integration Card:**

```
┌──────────────────────────────────────────────┐
│  Kroger API                                  │
│  ✅ Authenticated                            │
│  Token expires: 2025-12-01                   │
│                                              │
│  📍 3 Stores Configured                      │
│  [Manage Stores] [Re-authenticate] [Delete]  │
└──────────────────────────────────────────────┘
```

---

## Database Views

### shopping_locations_enhanced View

```sql
CREATE VIEW shopping_locations_enhanced AS
SELECT
    sl.id,
    sl.name,
    sl.description,
    sl.store_integration_id,
    sl.external_location_id,
    sl.address,
    sl.city,
    sl.state,
    sl.zip_code,
    sl.phone,
    sl.latitude,
    sl.longitude,
    sl.is_primary,
    sl.metadata_json,
    sl.row_created_timestamp,

    -- Integration data
    si.name AS integration_name,
    si.store_type,
    si.active AS integration_active,

    -- Computed fields
    CASE
        WHEN sl.store_integration_id IS NOT NULL THEN 1
        ELSE 0
    END AS is_integrated,

    CASE
        WHEN sl.store_integration_id IS NOT NULL
        THEN sl.name || ' (' || si.store_type || ')'
        ELSE sl.name
    END AS display_name_with_type,

    -- Product count
    (SELECT COUNT(*)
     FROM products p
     WHERE p.shopping_location_id = sl.id) AS product_count

FROM shopping_locations sl
LEFT JOIN store_integrations si
    ON sl.store_integration_id = si.id;
```

---

## Migration Strategy

### Step 1: Database Migration

**Migration File: 0259.sql**

```sql
-- Store Integration Consolidation
-- This migration consolidates store_integration_locations into shopping_locations

-- Step 1: Drop unreleased tables
DROP TABLE IF EXISTS store_integration_locations;

-- Step 2: Enhance shopping_locations with integration fields
ALTER TABLE shopping_locations ADD COLUMN store_integration_id INTEGER REFERENCES store_integrations(id) ON DELETE SET NULL;
ALTER TABLE shopping_locations ADD COLUMN external_location_id TEXT;
ALTER TABLE shopping_locations ADD COLUMN is_primary INTEGER DEFAULT 0;
ALTER TABLE shopping_locations ADD COLUMN address TEXT;
ALTER TABLE shopping_locations ADD COLUMN city TEXT;
ALTER TABLE shopping_locations ADD COLUMN state TEXT;
ALTER TABLE shopping_locations ADD COLUMN zip_code TEXT;
ALTER TABLE shopping_locations ADD COLUMN phone TEXT;
ALTER TABLE shopping_locations ADD COLUMN latitude REAL;
ALTER TABLE shopping_locations ADD COLUMN longitude REAL;
ALTER TABLE shopping_locations ADD COLUMN metadata_json TEXT;

-- Step 3: Create indexes
CREATE INDEX idx_shopping_locations_integration ON shopping_locations(store_integration_id);
CREATE INDEX idx_shopping_locations_external_id ON shopping_locations(store_integration_id, external_location_id);
CREATE INDEX idx_shopping_locations_primary ON shopping_locations(store_integration_id, is_primary);

-- Step 4: Update product_store_metadata to reference shopping_locations
-- Backup old column first
ALTER TABLE product_store_metadata RENAME COLUMN store_integration_id TO store_integration_id_old;

-- Add new column
ALTER TABLE product_store_metadata ADD COLUMN shopping_location_id INTEGER REFERENCES shopping_locations(id);

-- Update index
DROP INDEX IF EXISTS idx_product_store_metadata_store_integration_id;
CREATE INDEX idx_product_store_metadata_shopping_location_id ON product_store_metadata(shopping_location_id);

-- Step 5: Create view
CREATE VIEW shopping_locations_enhanced AS
SELECT
    sl.id,
    sl.name,
    sl.description,
    sl.store_integration_id,
    sl.external_location_id,
    sl.address,
    sl.city,
    sl.state,
    sl.zip_code,
    sl.phone,
    sl.latitude,
    sl.longitude,
    sl.is_primary,
    sl.metadata_json,
    sl.row_created_timestamp,
    si.name AS integration_name,
    si.store_type,
    si.active AS integration_active,
    CASE WHEN sl.store_integration_id IS NOT NULL THEN 1 ELSE 0 END AS is_integrated,
    CASE WHEN sl.store_integration_id IS NOT NULL THEN sl.name || ' (' || si.store_type || ')' ELSE sl.name END AS display_name_with_type,
    (SELECT COUNT(*) FROM products p WHERE p.shopping_location_id = sl.id) AS product_count
FROM shopping_locations sl
LEFT JOIN store_integrations si ON sl.store_integration_id = si.id;
```

### Step 2: Code Updates

**Files to Update:**

1. **services/StoreIntegrationsService.php**
   - Add methods listed in "Service Layer Changes"
   - Update existing methods to use shopping_locations

2. **helpers/BaseStoreIntegrationPlugin.php**
   - Remove `SaveStoreLocation()` method
   - Update method signatures

3. **data/plugins/grocy-kroger-plugins/KrogerStoreIntegrationPlugin.php**
   - Remove `SaveStoreLocation()` implementation
   - Update `SendShoppingList()` to use shopping_location_id
   - Update `LookupProductMetadata()` to use shopping_location_id

4. **controllers/StoreIntegrationsApiController.php**
   - Update all endpoints as specified

5. **routes.php**
   - Update route definitions

6. **views/shoppinglocationform.blade.php**
   - Add integration search/select UI

7. **views/shoppinglocations.blade.php**
   - Add integration status column
   - Add primary indicator

8. **views/shoppinglist.blade.php**
   - Update store picker to use shopping_locations

9. **public/viewjs/shoppinglist.js**
   - Update product search to use shopping_location_id

10. **public/viewjs/shoppinglocationform.js** (new file)
    - Handle integration location search
    - Populate external_location_id

### Step 3: Testing

**Test Cases:**

1. ✅ Create simple shopping location (no integration)
2. ✅ Create integrated shopping location via location search
3. ✅ Set primary location for integration
4. ✅ Search products at specific shopping location
5. ✅ Add product to shopping list from store search
6. ✅ Verify product has shopping_location_id set
7. ✅ Send shopping list to store
8. ✅ Lookup product metadata for location
9. ✅ Delete shopping location (verify cascades)
10. ✅ Delete integration (verify locations become simple)

---

## Implementation Plan

### Week 1: Database & Service Layer

**Tasks:**
- [ ] Create migration 0259.sql
- [ ] Test migration on dev database
- [ ] Update StoreIntegrationsService
- [ ] Write unit tests for service methods
- [ ] Update BaseStoreIntegrationPlugin interface
- [ ] Update Kroger plugin implementation

**Deliverables:**
- Migration file
- Service code
- Unit tests passing
- Plugin updated

### Week 2: API & Controllers

**Tasks:**
- [ ] Update StoreIntegrationsApiController
- [ ] Update routes.php
- [ ] Update OpenAPI spec
- [ ] Write API integration tests
- [ ] Test all endpoints

**Deliverables:**
- API endpoints updated
- Tests passing
- OpenAPI spec updated

### Week 3: UI Implementation

**Tasks:**
- [ ] Update shopping locations form
- [ ] Add integration search UI
- [ ] Update shopping locations list view
- [ ] Update shopping list modal
- [ ] Update product form
- [ ] Create/update JavaScript files

**Deliverables:**
- All UI views updated
- JavaScript functionality working
- Mobile responsive

### Week 4: Testing & Documentation

**Tasks:**
- [ ] End-to-end testing
- [ ] Integration testing with Kroger API
- [ ] Performance testing
- [ ] Update CLAUDE.md
- [ ] Update user documentation
- [ ] Create demo/screenshots

**Deliverables:**
- Test report
- Documentation updated
- Ready for release

---

## Benefits of This Approach

### For Users

✅ **Single Store Concept** - "Shopping locations" is the only term users need to know
✅ **Optional Integration** - Users can have simple stores and integrated stores side-by-side
✅ **Familiar UI** - Shopping location picker works exactly as before, now with superpowers
✅ **Clear Visual Indicators** - Easy to see which stores have online integration
✅ **No Learning Curve** - Existing Grocy users see no changes unless they opt in

### For Developers

✅ **Simpler Architecture** - One less table to maintain
✅ **Fewer Joins** - Direct relationship between products and shopping locations
✅ **Cleaner Code** - No linking/indirection layer
✅ **Easier to Understand** - Mental model matches real world
✅ **Less Maintenance** - Fewer moving parts

### For the Codebase

✅ **Reduced Complexity** - 33% fewer tables
✅ **Better Performance** - Fewer joins required
✅ **More Consistent** - Single source of truth
✅ **Future-Proof** - Easy to add more integration types

---

## Risk Assessment

### Low Risk: Data Loss

**Risk:** Migration drops unreleased tables

**Mitigation:**
- Tables were never released
- No production data exists
- Migration is reversible

**Likelihood:** N/A
**Impact:** None

### Low Risk: Plugin Breaking Changes

**Risk:** Plugin interface changes break Kroger implementation

**Mitigation:**
- We control both the interface and Kroger implementation
- Can update simultaneously
- Simplified interface is easier to implement

**Likelihood:** Low
**Impact:** Low

### Low Risk: User Confusion

**Risk:** Users don't understand integration option

**Mitigation:**
- Integration is optional
- Clear labels and help text
- Progressive disclosure in UI

**Likelihood:** Low
**Impact:** Low

---

## Success Criteria

✅ Migration runs successfully on test database
✅ All existing shopping_locations work unchanged
✅ Can create integrated shopping locations via location search
✅ Can create simple shopping locations manually
✅ Product search returns results with shopping_location_id
✅ Products created from store search have shopping_location_id set
✅ Shopping list displays correct store
✅ Can send shopping list to integrated stores
✅ Product metadata is stored and displayed correctly
✅ All tests pass (unit, integration, E2E)
✅ Performance is equal or better than before
✅ UI is intuitive and responsive

---

## Conclusion

By consolidating the store integration functionality directly into the existing `shopping_locations` table, we achieve a much simpler, more maintainable architecture. This approach eliminates unnecessary complexity while providing all the same capabilities.

The key insight is that **a shopping location can optionally have API integration capabilities** - there's no need for a separate table to represent integrated locations. This results in:

- **Simpler mental model** for users
- **Cleaner code** for developers
- **Better performance** through fewer joins
- **Easier maintenance** going forward

Since the store integration feature hasn't been released yet, we have the opportunity to get the architecture right from the start.

---

**Document End**
