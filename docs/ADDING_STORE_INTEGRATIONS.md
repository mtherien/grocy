# Adding a New Store Integration Plugin

This guide explains how to add support for a new grocery store (e.g., Walmart, Target, Albertsons) to Grocy's store integration system using the plugin architecture.

## Overview

Store integrations use a **plugin-based architecture** similar to barcode lookup plugins. This means you can add new store integrations without modifying Grocy's core code - just drop a plugin file into the appropriate directory!

**Plugin Locations:**
- **Built-in plugins**: `plugins/` directory (bundled with Grocy)
- **User plugins**: `data/plugins/` directory (your custom integrations)

**Priority**: User plugins in `data/plugins/` take precedence over built-in plugins with the same store type.

All store integration plugins must extend `BaseStoreIntegrationPlugin` and implement all required methods.

## Creating a Store Integration Plugin

### Step 1: Create the Plugin File

Create a new file with the naming pattern `*StoreIntegrationPlugin.php`:

**For built-in integration**: `plugins/WalmartStoreIntegrationPlugin.php`

**For custom integration**: `data/plugins/WalmartStoreIntegrationPlugin.php`

### Step 2: Implement the Plugin Class

```php
<?php

use Grocy\Helpers\BaseStoreIntegrationPlugin;

/**
 * Walmart Store Integration Plugin
 *
 * Provides integration with Walmart API for shopping list management
 * and product metadata lookup.
 *
 * To use this plugin, configure API credentials in data/config.php:
 * Setting('WALMART_CLIENT_ID', 'your-client-id');
 * Setting('WALMART_CLIENT_SECRET', 'your-client-secret');
 */
class WalmartStoreIntegrationPlugin extends BaseStoreIntegrationPlugin
{
    // Required: Plugin display name
    public const PLUGIN_NAME = 'Walmart';

    // API endpoints
    private const API_BASE_URL = 'https://api.walmart.com/v1';
    private const AUTH_URL = 'https://auth.walmart.com/oauth2/authorize';
    private const TOKEN_URL = 'https://auth.walmart.com/oauth2/token';

    /**
     * Get the OAuth authorization URL
     */
    public function GetAuthorizationUrl($integrationId, $redirectUri)
    {
        // Build OAuth authorization URL with required parameters
        $params = [
            'client_id' => $this->getClientId(),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'cart.write product.read',
            'state' => base64_encode(json_encode(['integration_id' => $integrationId]))
        ];

        return self::AUTH_URL . '?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for tokens
     */
    public function ExchangeCodeForToken($code, $redirectUri)
    {
        // Make API call to exchange code for access/refresh tokens
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
     * Refresh an expired access token
     */
    public function RefreshToken($integrationId)
    {
        $integration = $this->StoreIntegrationsService->GetById($integrationId);

        $response = $this->makeTokenRequest([
            'grant_type' => 'refresh_token',
            'refresh_token' => $integration->refresh_token
        ]);

        // Update tokens in database
        $this->StoreIntegrationsService->SetTokens(
            $integrationId,
            $response['access_token'],
            $response['refresh_token'],
            date('Y-m-d H:i:s', time() + $response['expires_in'])
        );

        return true;
    }

    /**
     * Search for store locations
     */
    public function SearchStoreLocations($integrationId, $zipCode = null, $lat = null, $lon = null, $radiusMiles = 10)
    {
        // Make API call to search for store locations
        // Return array of locations with standardized structure:
        // [
        //     [
        //         'locationId' => string,
        //         'name' => string,
        //         'address' => [
        //             'addressLine1' => string,
        //             'city' => string,
        //             'state' => string,
        //             'zipCode' => string
        //         ],
        //         'phone' => string,
        //         ... (additional metadata)
        //     ]
        // ]
    }

    /**
     * Save a store location
     */
    public function SaveStoreLocation($integrationId, $locationData, $isPrimary = false)
    {
        // Parse locationData and save to store_integration_locations table
        if ($isPrimary)
        {
            $this->Database->store_integration_locations()
                ->where('store_integration_id = :1', $integrationId)
                ->update(['is_primary' => 0]);
        }

        $data = [
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

        $row = $this->Database->store_integration_locations()->insert($data);
        return $row['id'];
    }

    /**
     * Send shopping list to store
     */
    public function SendShoppingList($integrationId, $shoppingListId)
    {
        $integration = $this->StoreIntegrationsService->GetById($integrationId);

        // Get shopping list items from database
        $listItems = $this->Database->shopping_list()
            ->where('shopping_list_id = :1', $shoppingListId)
            ->where('done = 0');

        // Get primary store location
        $primaryLocation = $this->StoreIntegrationsService->GetPrimaryStoreLocation($integrationId);

        // Match products via barcode or name
        // Add items to store's cart/list via API
        // Return API response
    }

    /**
     * Lookup product metadata
     */
    public function LookupProductMetadata($integrationId, $productId)
    {
        $integration = $this->StoreIntegrationsService->GetById($integrationId);
        $product = $this->Database->products()->where('id = :1', $productId)->fetch();

        // Get primary store location
        $primaryLocation = $this->StoreIntegrationsService->GetPrimaryStoreLocation($integrationId);

        // Search for product in store's catalog via API (by barcode or name)
        // Extract and return metadata:
        return [
            'external_product_id' => '...',
            'aisle' => '5',
            'shelf' => 'A',
            'department' => 'Dairy',
            'category' => 'Milk',
            'price' => 3.99,
            'price_unit' => '1 gallon',
            'availability' => 'In Stock',
            'raw_data' => [] // Full API response
        ];
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
     * Validate configuration
     */
    public function ValidateConfiguration()
    {
        // Check that required API credentials are configured
        $this->getClientId();
        $this->getClientSecret();
        return true;
    }

    // Private helper methods
    private function getClientId()
    {
        if (!defined('GROCY_WALMART_CLIENT_ID') || empty(GROCY_WALMART_CLIENT_ID))
        {
            throw new \Exception('Walmart API client ID not configured');
        }
        return GROCY_WALMART_CLIENT_ID;
    }

    private function getClientSecret()
    {
        if (!defined('GROCY_WALMART_CLIENT_SECRET') || empty(GROCY_WALMART_CLIENT_SECRET))
        {
            throw new \Exception('Walmart API client secret not configured');
        }
        return GROCY_WALMART_CLIENT_SECRET;
    }

    private function makeTokenRequest($data)
    {
        // cURL implementation for token requests
    }
}
```

### Step 3: Add Configuration Settings

Add to `data/config.php`:

```php
// Walmart API credentials - Get these from https://developer.walmart.com/
Setting('WALMART_CLIENT_ID', 'your-client-id');
Setting('WALMART_CLIENT_SECRET', 'your-client-secret');
```

### Step 4: Test Your Plugin

1. Add API credentials to `data/config.php`
2. The plugin will be automatically discovered - no code changes needed!
3. Navigate to Store Integrations in Grocy UI
4. Your new store type should appear in the dropdown
5. Create a new integration, authenticate with the store
6. Search and save store locations
7. Test sending shopping list and product metadata lookup

## Plugin Discovery

Grocy automatically discovers all plugins matching the pattern `*StoreIntegrationPlugin.php` in:

1. `plugins/` - Built-in integrations (shipped with Grocy)
2. `data/plugins/` - User custom integrations (take precedence)

**No registration required!** Just create the file and it's available.

## Required Methods Reference

All methods must be implemented:

### OAuth Methods

- **GetAuthorizationUrl($integrationId, $redirectUri)**: Build OAuth URL
- **ExchangeCodeForToken($code, $redirectUri)**: Exchange code for tokens
- **RefreshToken($integrationId)**: Refresh expired token

### Store Location Methods

- **SearchStoreLocations(...)**:  Search nearby stores
- **SaveStoreLocation(...)**:  Save a store location to database

### Shopping Methods

- **SendShoppingList($integrationId, $shoppingListId)**: Send list to store

### Product Methods

- **LookupProductMetadata($integrationId, $productId)**: Get product location/price

### Metadata Methods

- **GetStoreType()**: Return store identifier (lowercase, e.g., 'walmart')
- **GetStoreDisplayName()**: Return display name (e.g., 'Walmart')
- **ValidateConfiguration()**: Check API credentials are configured

## Accessing Database and Services

Through the constructor, your plugin receives:

```php
// Access database via LessQL ORM
$this->Database->products()->where('id = :1', $productId)->fetch();
$this->Database->product_barcodes()->where('product_id = :1', $productId);
$this->Database->store_integration_locations()->insert([...]);

// Access store integrations service
$integration = $this->StoreIntegrationsService->GetById($integrationId);
$this->StoreIntegrationsService->SetTokens($id, $accessToken, $refreshToken, $expiresAt);
$primaryLocation = $this->StoreIntegrationsService->GetPrimaryStoreLocation($integrationId);
```

## Example: Complete Plugin

See `plugins/KrogerStoreIntegrationPlugin.php` for a complete working example with:
- Full OAuth 2.0 implementation
- Store location search and management
- Shopping list synchronization
- Product metadata lookup with aisle/price/availability

## API Documentation Resources

- **Kroger**: https://developer.kroger.com/
- **Walmart**: https://developer.walmart.com/
- **Target**: https://developer.target.com/
- **Albertsons/Safeway**: Contact for API access

## Distribution

To distribute your plugin:

1. Package the plugin file (`YourStoreIntegrationPlugin.php`)
2. Include documentation with required config settings
3. Users drop it into `data/plugins/` directory
4. Plugin is automatically discovered - no code changes needed!

## Need Help?

- Review `KrogerStoreIntegrationPlugin.php` as a reference
- Check `BaseStoreIntegrationPlugin` for method signatures
- All methods are documented with PHPDoc comments
