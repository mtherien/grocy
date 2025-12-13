# Store Integrations Guide

**Version:** 1.0
**Last Updated:** 2025-12-12
**Feature Status:** Available on `store-integration` branch

---

## Table of Contents

1. [Overview](#overview)
2. [User Guide](#user-guide)
   - [Setting Up a Store Integration](#setting-up-a-store-integration)
   - [Connecting Shopping Locations](#connecting-shopping-locations)
   - [Using Shop Mode](#using-shop-mode)
   - [Product Metadata](#product-metadata)
3. [Architecture](#architecture)
4. [Developer Guide](#developer-guide)
   - [Creating a Store Integration Plugin](#creating-a-store-integration-plugin)
   - [Plugin Methods Reference](#plugin-methods-reference)
   - [Example Implementation](#example-implementation)
5. [Database Schema](#database-schema)
6. [API Reference](#api-reference)

---

## Overview

Store Integrations bring powerful automation to Grocy by connecting it directly to grocery store APIs (like Kroger, Walmart, Target). This feature enables:

- **Automatic product metadata**: Get aisle, shelf, department, and pricing information for products
- **Shop Mode**: Mobile-optimized shopping interface with barcode scanning
- **Smart product creation**: Scan unknown barcodes to automatically create products with complete metadata
- **Shopping location management**: Link Grocy shopping locations to physical stores with API access

### Key Features

✅ **Unified Shopping Locations** - All stores (manual and API-integrated) in one place
✅ **OAuth 2.0 Integration** - Secure authentication with grocery store APIs
✅ **Plugin Architecture** - Add new store integrations without modifying core code
✅ **Mobile-Optimized Shopping** - Full-screen shop mode with camera barcode scanning
✅ **Automatic Metadata** - Product aisles, prices, and availability from store APIs
✅ **Bulk Operations** - Add entire shopping list to inventory after shopping

---

## User Guide

### Setting Up a Store Integration

#### Step 1: Configure API Credentials

Add your store API credentials to `data/config.php`:

```php
// Kroger API credentials
Setting('KROGER_CLIENT_ID', 'your-client-id-here');
Setting('KROGER_CLIENT_SECRET', 'your-client-secret-here');

// Additional stores can be added similarly
Setting('WALMART_CLIENT_ID', 'your-walmart-client-id');
Setting('WALMART_CLIENT_SECRET', 'your-walmart-client-secret');
```

**Where to get API credentials:**
- **Kroger**: https://developer.kroger.com/ (free developer account)
- **Walmart**: https://developer.walmart.com/
- **Target**: https://developer.target.com/

#### Step 2: Create a Store Integration

1. Navigate to **Store Integrations** in Grocy
2. Click **"Add Store Integration"**
3. Select your store type (e.g., "Kroger")
4. Click **"Authorize with Store"**
5. Log in to your store account and grant permissions
6. You'll be redirected back to Grocy with a successful connection

#### Step 3: Find and Add Store Locations

1. Open your newly created store integration
2. Enter your ZIP code or address
3. Click **"Search for Stores"**
4. Browse available store locations
5. Click **"Add Store"** for locations you shop at
6. Mark one as your **primary store** (used by default)

### Connecting Shopping Locations

Grocy uses **Shopping Locations** to track where you buy products. With store integrations, you can:

#### Option A: Create New Shopping Location with Integration

1. Go to **Shopping Locations** (Master Data → Shopping Locations)
2. Click **"Add"**
3. Enter a name (e.g., "Kroger - Downtown")
4. Select a **Store Integration** from the dropdown
5. Choose the **External Store Location** (physical store)
6. Save

#### Option B: Add Integration to Existing Location

1. Edit an existing shopping location
2. Select a **Store Integration**
3. Choose the **External Store Location**
4. Save

**Benefits:**
- Products assigned to this location can fetch metadata (aisle, price)
- Shop Mode works with this location
- Barcode scanning can auto-create products from the store's catalog

### Using Shop Mode

Shop Mode is a mobile-optimized, full-screen shopping interface that makes grocery shopping efficient and organized.

#### Starting Shop Mode

1. Open a **Shopping List**
2. Click **"Start Shop Mode"**
3. Select which store you're shopping at
4. Grocy automatically fetches aisle/shelf information for all items
5. Items are grouped by aisle for efficient shopping

#### Shop Mode Features

**✓ Aisle Grouping**: Items automatically organized by store aisle
**✓ Camera Scanning**: Tap the camera icon to scan barcodes
**✓ Auto-Check**: Scanning an item on your list marks it as done
**✓ Quick Add**: Scan items not on your list to add them
**✓ Product Images**: See product photos from the store API
**✓ Real-time Updates**: Changes sync immediately

#### Using Camera Barcode Scanner

1. In Shop Mode, tap the **floating camera button**
2. Point camera at barcode
3. **If product is on your list**: Automatically marked as done ✓
4. **If product is NOT in Grocy**: Option to create it with store data
5. **If product is not on list**: Option to add to list

#### Completing Your Shopping Trip

1. After scanning all items, click **"Send to Inventory"**
2. Review items and quantities
3. Optionally adjust dates, prices, or locations
4. Click **"Add to Inventory"**
5. All scanned items are added to stock and removed from shopping list

### Product Metadata

Store integrations automatically fetch rich metadata for products:

- **Aisle Number**: Where to find the product in the store
- **Shelf Location**: Specific shelf within the aisle
- **Department**: Store department (Dairy, Produce, etc.)
- **Price**: Current price at that store
- **Availability**: In stock, out of stock, limited
- **Product Images**: Official product photos

#### Fetching Metadata

**Automatically**:
- When starting Shop Mode, metadata is fetched for all items
- When scanning unknown barcodes, metadata comes with the product

**Manually**:
- Edit a product
- If assigned to a shopping location with integration
- Click **"Fetch Store Metadata"** button
- Aisle, price, and other data populate automatically

---

## Architecture

### Unified Shopping Locations Model

Store integrations use a **unified architecture** where all stores (both manual and API-integrated) exist in the `shopping_locations` table. This provides:

- **Single source of truth** for all store locations
- **Backward compatibility** with existing Grocy workflows
- **Optional enhancement** - shopping locations work with or without integrations

```
┌─────────────────────────────────────────────────────────┐
│              shopping_locations (Enhanced)              │
│         (Single Source of Truth for Stores)             │
├─────────────────────────────────────────────────────────┤
│ -- Core fields (existing)                               │
│ id, name, description                                   │
│                                                         │
│ -- Integration fields (NEW)                             │
│ store_integration_id      (FK to store_integrations)   │
│ external_location_id      (Store's API identifier)     │
│                                                         │
│ -- Physical location fields (NEW)                       │
│ address, city, state, zip_code, phone                   │
│ latitude, longitude                                     │
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
```

### Data Flow

1. **OAuth Setup**: User authorizes Grocy to access store API
2. **Token Storage**: Access/refresh tokens stored in `store_integrations` table
3. **Location Linking**: Physical stores saved as `shopping_locations` with `store_integration_id`
4. **Product Association**: Products reference `shopping_locations` via `shopping_location_id`
5. **Metadata Retrieval**: API calls use tokens to fetch product data
6. **Metadata Storage**: Product-specific data stored in `product_store_metadata` table

---

## Developer Guide

### Creating a Store Integration Plugin

Store integrations use a **plugin-based architecture** - add new stores without modifying Grocy core code!

#### Plugin Locations

- **Built-in plugins**: `plugins/` directory (bundled with Grocy)
- **User plugins**: `data/plugins/` directory (custom integrations)
- **Priority**: User plugins override built-in plugins with the same store type

#### Step 1: Create Plugin File

Create a file matching the pattern `*StoreIntegrationPlugin.php`:

```
data/plugins/WalmartStoreIntegrationPlugin.php
```

#### Step 2: Implement Plugin Class

```php
<?php

use Grocy\Helpers\BaseStoreIntegrationPlugin;

/**
 * Walmart Store Integration Plugin
 */
class WalmartStoreIntegrationPlugin extends BaseStoreIntegrationPlugin
{
    public const PLUGIN_NAME = 'Walmart';

    private const API_BASE_URL = 'https://api.walmart.com/v1';
    private const AUTH_URL = 'https://auth.walmart.com/oauth2/authorize';
    private const TOKEN_URL = 'https://auth.walmart.com/oauth2/token';

    /**
     * Get OAuth authorization URL
     */
    public function GetAuthorizationUrl($integrationId, $redirectUri)
    {
        $params = [
            'client_id' => $this->getClientId(),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'cart.write product.read store.read',
            'state' => base64_encode(json_encode(['integration_id' => $integrationId]))
        ];

        return self::AUTH_URL . '?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for access token
     */
    public function ExchangeCodeForToken($code, $redirectUri)
    {
        $response = $this->makeTokenRequest([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri
        ]);

        return [
            'access_token' => $response['access_token'],
            'refresh_token' => $response['refresh_token'],
            'expires_at' => date('Y-m-d H:i:s', time() + $response['expires_in'])
        ];
    }

    /**
     * Refresh expired access token
     */
    public function RefreshToken($integrationId)
    {
        $integration = $this->StoreIntegrationsService->GetById($integrationId);

        $response = $this->makeTokenRequest([
            'grant_type' => 'refresh_token',
            'refresh_token' => $integration->refresh_token
        ]);

        $this->StoreIntegrationsService->SetTokens(
            $integrationId,
            $response['access_token'],
            $response['refresh_token'],
            date('Y-m-d H:i:s', time() + $response['expires_in'])
        );

        return true;
    }

    /**
     * Search for nearby store locations
     */
    public function SearchStoreLocations($integrationId, $shoppingLocationId = null)
    {
        // Get shopping location for context (ZIP code, lat/lon)
        $location = null;
        if ($shoppingLocationId) {
            $location = $this->Database->shopping_locations()
                ->where('id = :1', $shoppingLocationId)
                ->fetch();
        }

        // Make API call to search stores
        $results = $this->apiCall('GET', '/stores/search', [
            'zip' => $location->zip_code ?? '90210',
            'radius' => 25
        ]);

        // Return standardized location array
        return array_map(function($store) {
            return [
                'locationId' => $store['id'],
                'name' => $store['name'],
                'address' => [
                    'addressLine1' => $store['address'],
                    'city' => $store['city'],
                    'state' => $store['state'],
                    'zipCode' => $store['zip']
                ],
                'phone' => $store['phone'] ?? '',
                'coordinates' => [
                    'latitude' => $store['lat'],
                    'longitude' => $store['lon']
                ],
                'metadata' => $store // Full API response
            ];
        }, $results['data']);
    }

    /**
     * Search for products by barcode or term
     */
    public function SearchProducts($integrationId, $externalLocationId, $barcode = null, $searchTerm = null)
    {
        $params = ['locationId' => $externalLocationId];

        if ($barcode) {
            $params['filter.productId'] = $barcode;
        } elseif ($searchTerm) {
            $params['filter.term'] = $searchTerm;
        }

        $results = $this->apiCall('GET', '/products', $params);

        return array_map(function($product) {
            return [
                'externalProductId' => $product['productId'],
                'name' => $product['description'],
                'description' => $product['brand'] ?? '',
                'barcode' => $product['upc'],
                'price' => $product['price']['regular'] ?? null,
                'image' => $product['images'][0] ?? null,
                'aisle' => $product['aisleLocations'][0]['bayNumber'] ?? null,
                'shelf' => $product['aisleLocations'][0]['shelfNumber'] ?? null,
                'department' => $product['categories'][0] ?? null,
                'availability' => $product['fulfillment']['inStock'] ?? false,
                'rawData' => $product
            ];
        }, $results['data']);
    }

    /**
     * Lookup metadata for a specific product
     */
    public function LookupProductMetadata($integrationId, $shoppingLocationId, $productId)
    {
        // Get product to find barcode
        $product = $this->Database->products()->where('id = :1', $productId)->fetch();
        $barcode = $this->Database->product_barcodes()
            ->where('product_id = :1', $productId)
            ->fetch();

        if (!$barcode) {
            throw new \Exception('Product has no barcode');
        }

        // Get shopping location for external_location_id
        $location = $this->Database->shopping_locations()
            ->where('id = :1', $shoppingLocationId)
            ->fetch();

        if (!$location || !$location->external_location_id) {
            throw new \Exception('Shopping location not configured');
        }

        // Search for product
        $results = $this->SearchProducts(
            $integrationId,
            $location->external_location_id,
            $barcode->barcode
        );

        if (empty($results)) {
            throw new \Exception('Product not found in store catalog');
        }

        return $results[0]; // Return first match
    }

    /**
     * Get store type identifier
     */
    public function GetStoreType()
    {
        return 'walmart';
    }

    /**
     * Get store display name
     */
    public function GetStoreDisplayName()
    {
        return 'Walmart';
    }

    /**
     * Validate plugin configuration
     */
    public function ValidateConfiguration()
    {
        $this->getClientId();
        $this->getClientSecret();
        return true;
    }

    // Private helper methods
    private function getClientId()
    {
        if (!defined('GROCY_WALMART_CLIENT_ID') || empty(GROCY_WALMART_CLIENT_ID)) {
            throw new \Exception('Walmart CLIENT_ID not configured in data/config.php');
        }
        return GROCY_WALMART_CLIENT_ID;
    }

    private function getClientSecret()
    {
        if (!defined('GROCY_WALMART_CLIENT_SECRET') || empty(GROCY_WALMART_CLIENT_SECRET)) {
            throw new \Exception('Walmart CLIENT_SECRET not configured in data/config.php');
        }
        return GROCY_WALMART_CLIENT_SECRET;
    }

    private function makeTokenRequest($data)
    {
        $data['client_id'] = $this->getClientId();
        $data['client_secret'] = $this->getClientSecret();

        $ch = curl_init(self::TOKEN_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \Exception('Token request failed: ' . $response);
        }

        return json_decode($response, true);
    }

    private function apiCall($method, $endpoint, $params = [])
    {
        $integration = $this->StoreIntegrationsService->GetById($this->currentIntegrationId);

        // Refresh token if needed
        if (strtotime($integration->token_expires_at) < time() + 300) {
            $this->RefreshToken($this->currentIntegrationId);
            $integration = $this->StoreIntegrationsService->GetById($this->currentIntegrationId);
        }

        $url = self::API_BASE_URL . $endpoint;
        if ($method === 'GET' && !empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $integration->access_token,
            'Accept: application/json'
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $integration->access_token,
                'Content-Type: application/json'
            ]);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \Exception('API call failed: ' . $response);
        }

        return json_decode($response, true);
    }
}
```

#### Step 3: Add Configuration

Add to `data/config.php`:

```php
Setting('WALMART_CLIENT_ID', 'your-client-id');
Setting('WALMART_CLIENT_SECRET', 'your-client-secret');
```

#### Step 4: Test

1. Plugin is **automatically discovered** - no registration needed!
2. Navigate to Store Integrations in Grocy
3. "Walmart" appears in store type dropdown
4. Create integration and test OAuth flow

### Plugin Methods Reference

All plugins must implement these methods:

#### OAuth Methods

| Method | Parameters | Returns | Description |
|--------|-----------|---------|-------------|
| `GetAuthorizationUrl()` | `$integrationId`, `$redirectUri` | `string` | Build OAuth authorization URL |
| `ExchangeCodeForToken()` | `$code`, `$redirectUri` | `array` | Exchange auth code for tokens |
| `RefreshToken()` | `$integrationId` | `bool` | Refresh expired access token |

#### Store Location Methods

| Method | Parameters | Returns | Description |
|--------|-----------|---------|-------------|
| `SearchStoreLocations()` | `$integrationId`, `$shoppingLocationId` | `array` | Search for nearby stores |

#### Product Methods

| Method | Parameters | Returns | Description |
|--------|-----------|---------|-------------|
| `SearchProducts()` | `$integrationId`, `$externalLocationId`, `$barcode`, `$searchTerm` | `array` | Search store catalog |
| `LookupProductMetadata()` | `$integrationId`, `$shoppingLocationId`, `$productId` | `array` | Get product metadata |

#### Metadata Methods

| Method | Parameters | Returns | Description |
|--------|-----------|---------|-------------|
| `GetStoreType()` | - | `string` | Store identifier (lowercase) |
| `GetStoreDisplayName()` | - | `string` | Human-readable name |
| `ValidateConfiguration()` | - | `bool` | Check API credentials |

### Example Implementation

See `data/plugins/grocy-kroger-plugins/KrogerStoreIntegrationPlugin.php` for a complete, production-ready example implementing:

- ✅ Full OAuth 2.0 flow with automatic token refresh
- ✅ Store location search by ZIP code
- ✅ Product search by barcode and name
- ✅ Metadata extraction (aisle, price, images)
- ✅ Error handling and logging
- ✅ API rate limiting

---

## Database Schema

### Core Tables

#### `store_integrations`
Stores API configuration and OAuth tokens for each integration.

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

#### `shopping_locations` (Enhanced)
Core shopping location table with optional integration fields.

```sql
CREATE TABLE shopping_locations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    description TEXT,

    -- Integration fields (NEW)
    store_integration_id INTEGER,
    external_location_id TEXT,

    -- Physical location (NEW)
    address TEXT,
    city TEXT,
    state TEXT,
    zip_code TEXT,
    phone TEXT,
    latitude REAL,
    longitude REAL,

    -- Metadata (NEW)
    metadata_json TEXT,

    row_created_timestamp DATETIME DEFAULT (datetime('now', 'localtime')),

    FOREIGN KEY (store_integration_id) REFERENCES store_integrations(id)
);
```

#### `product_store_metadata`
Stores product-specific metadata from store APIs.

```sql
CREATE TABLE product_store_metadata (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    product_id INTEGER NOT NULL,
    shopping_location_id INTEGER NOT NULL,
    store_integration_id INTEGER NOT NULL,
    store_integration_id_old INTEGER NOT NULL, -- Legacy field
    external_product_id TEXT,
    aisle TEXT,
    shelf TEXT,
    department TEXT,
    category TEXT,
    price REAL,
    price_unit TEXT,
    availability TEXT,
    last_updated DATETIME DEFAULT (datetime('now', 'localtime')),
    raw_data TEXT,

    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (shopping_location_id) REFERENCES shopping_locations(id),
    FOREIGN KEY (store_integration_id) REFERENCES store_integrations(id),

    UNIQUE(product_id, shopping_location_id)
);
```

### Relationships

```
store_integrations
    ↓ (1:many)
shopping_locations
    ↓ (1:many)
products
    ↓ (1:many)
product_store_metadata
```

---

## API Reference

### Store Integration Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/store-integrations` | GET | List all integrations |
| `/api/store-integrations` | POST | Create new integration |
| `/api/store-integrations/{id}` | GET | Get integration details |
| `/api/store-integrations/{id}` | PUT | Update integration |
| `/api/store-integrations/{id}` | DELETE | Delete integration |
| `/api/store-integrations/{id}/authorize` | GET | Get OAuth URL |
| `/api/store-integrations/{id}/search-locations` | POST | Search store locations |
| `/api/store-integrations/{id}/search-products` | POST | Search products |

### Shop Mode Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/shopping-list/{id}/shop-mode/start` | POST | Start shop mode session |
| `/api/shopping-list/{id}/shop-mode/items` | GET | Get items with metadata |
| `/api/shopping-list/{id}/shop-mode/scan` | POST | Handle barcode scan |
| `/api/shopping-list/{id}/shop-mode/add-scanned` | POST | Add scanned item |
| `/api/shopping-list/{id}/shop-mode/bulk-add-to-inventory` | POST | Complete shopping trip |

### Product Metadata Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/store-integrations/{id}/lookup-metadata` | POST | Fetch product metadata |
| `/api/products/{id}/store-metadata` | GET | Get stored metadata |

---

## Configuration Reference

### Required Settings per Store

#### Kroger
```php
Setting('KROGER_CLIENT_ID', 'your-kroger-client-id');
Setting('KROGER_CLIENT_SECRET', 'your-kroger-client-secret');
```

#### Walmart (Example)
```php
Setting('WALMART_CLIENT_ID', 'your-walmart-client-id');
Setting('WALMART_CLIENT_SECRET', 'your-walmart-client-secret');
```

### Getting API Credentials

1. **Kroger**:
   - Visit https://developer.kroger.com/
   - Create free developer account
   - Register application
   - Copy Client ID and Client Secret

2. **Other Stores**: Check respective developer portals

---

## Troubleshooting

### Common Issues

**OAuth callback fails**
- Ensure `BASE_URL` in `data/config.php` is correct
- Check redirect URI matches exactly in store developer console

**Token expires repeatedly**
- Check system clock is synchronized
- Verify token refresh logic in plugin

**Products not found**
- Ensure product has valid barcode
- Check barcode format matches store's requirements (UPC-A, EAN-13)

**Metadata not displaying**
- Verify shopping location has `store_integration_id` set
- Check product is assigned to integrated shopping location
- Review `product_store_metadata` table for data

### Debug Logging

Enable SQL logging in `data/config.php`:

```php
Setting('MODE', 'dev');
```

Check `data/sql.log` for database queries and errors.

---

## Migration Notes

### From Previous Versions

The unified `shopping_locations` model is **backward compatible**:

- ✅ Existing `shopping_locations` work unchanged
- ✅ Integration fields are optional (NULL by default)
- ✅ Products continue working with non-integrated locations
- ✅ New features only activate when integration is configured

### Data Migration

Migration scripts automatically:
1. Add new columns to `shopping_locations`
2. Create `product_store_metadata` table
3. Preserve all existing data

---

## Security Considerations

- **OAuth Tokens**: Stored encrypted in database
- **API Credentials**: Store in `data/config.php` (excluded from version control)
- **User Data**: Never sent to store APIs except during authentication
- **HTTPS Required**: OAuth callbacks must use HTTPS in production

---

## Contributing

To contribute a new store integration plugin:

1. Implement plugin extending `BaseStoreIntegrationPlugin`
2. Test thoroughly with real API credentials
3. Document required configuration settings
4. Submit pull request with plugin in `plugins/` directory
5. Include developer account setup instructions

---

## Support

- **Documentation**: This file and inline code comments
- **Reference Implementation**: `data/plugins/grocy-kroger-plugins/KrogerStoreIntegrationPlugin.php`
- **Issues**: https://github.com/grocy/grocy/issues
- **Discussions**: https://github.com/grocy/grocy/discussions
