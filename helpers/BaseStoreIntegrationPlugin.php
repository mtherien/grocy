<?php

namespace Grocy\Helpers;

/**
 * Abstract base class for all store integration plugins
 *
 * All store integrations (Kroger, Walmart, Target, etc.) must extend this class
 * and implement all abstract methods to ensure consistent functionality.
 *
 * Plugins can be placed in:
 * - plugins/ for built-in integrations
 * - data/plugins/ for user-created integrations (takes precedence)
 */
abstract class BaseStoreIntegrationPlugin
{
	// That's a "self-referencing constant" and forces the child class to define it
	public const PLUGIN_NAME = self::PLUGIN_NAME;

	final public function __construct($database, $storeIntegrationsService)
	{
		$this->Database = $database;
		$this->StoreIntegrationsService = $storeIntegrationsService;
	}

	protected $Database;
	protected $StoreIntegrationsService;

	/**
	 * Get the OAuth authorization URL for user authentication
	 *
	 * @param int $integrationId The integration ID
	 * @param string $redirectUri The OAuth callback URL
	 * @return string The authorization URL to redirect the user to
	 */
	abstract public function GetAuthorizationUrl($integrationId, $redirectUri);

	/**
	 * Exchange authorization code for access and refresh tokens
	 *
	 * @param string $code The authorization code from OAuth callback
	 * @param string $redirectUri The OAuth callback URL (must match the one used in GetAuthorizationUrl)
	 * @return array Array with keys: access_token, refresh_token, expires_at
	 * @throws \Exception If token exchange fails
	 */
	abstract public function ExchangeCodeForToken($code, $redirectUri);

	/**
	 * Refresh an expired access token
	 *
	 * @param int $integrationId The integration ID
	 * @return bool True on success
	 * @throws \Exception If token refresh fails
	 */
	abstract public function RefreshToken($integrationId);

	/**
	 * Search for store locations by zip code or coordinates
	 *
	 * @param int $integrationId The integration ID
	 * @param string|null $zipCode Zip code to search near
	 * @param float|null $lat Latitude to search near
	 * @param float|null $lon Longitude to search near
	 * @param int $radiusMiles Search radius in miles
	 * @return array Array of store locations with structure:
	 *   [
	 *     'locationId' => string,
	 *     'name' => string,
	 *     'address' => ['addressLine1' => string, 'city' => string, 'state' => string, 'zipCode' => string],
	 *     'phone' => string,
	 *     ... (additional metadata)
	 *   ]
	 * @throws \Exception If search fails or integration is not authenticated
	 */
	abstract public function SearchStoreLocations($integrationId, $zipCode = null, $lat = null, $lon = null, $radiusMiles = 10);

	/**
	 * Send a shopping list to the store's cart/list system
	 *
	 * @param int $integrationId The integration ID
	 * @param int $shoppingListId The Grocy shopping list ID
	 * @param int|null $shoppingLocationId The Grocy shopping location ID (optional, uses primary if not specified)
	 * @return array Response data from the store API
	 * @throws \Exception If send fails, integration not authenticated, or no store location configured
	 */
	abstract public function SendShoppingList($integrationId, $shoppingListId, $shoppingLocationId = null);

	/**
	 * Search for products in the store's catalog
	 *
	 * @param int $integrationId The integration ID
	 * @param string $searchTerm The search term (product name, barcode, etc.)
	 * @param string|null $externalLocationId The external store location ID from the store's API (optional)
	 * @return array Array of products with structure:
	 *   [
	 *     'productId' => string,
	 *     'name' => string,
	 *     'description' => string,
	 *     'brand' => string,
	 *     'price' => float,
	 *     'imageUrl' => string,
	 *     'upc' => string,
	 *     ... (additional metadata)
	 *   ]
	 * @throws \Exception If search fails, integration not authenticated, or no store location configured
	 */
	abstract public function SearchProducts($integrationId, $searchTerm, $externalLocationId = null);

	/**
	 * Lookup product metadata (aisle, price, availability) from the store
	 *
	 * @param int $integrationId The integration ID
	 * @param int $productId The Grocy product ID
	 * @param int|null $shoppingLocationId The Grocy shopping location ID (optional)
	 * @return array Metadata with keys: external_product_id, aisle, shelf, department, category, price, price_unit, availability, raw_data
	 * @throws \Exception If lookup fails, product not found, integration not authenticated, or no store location configured
	 */
	abstract public function LookupProductMetadata($integrationId, $productId, $shoppingLocationId = null);

	/**
	 * Get the store type identifier (e.g., 'kroger', 'walmart', 'target')
	 *
	 * @return string The store type identifier
	 */
	abstract public function GetStoreType();

	/**
	 * Get the display name of the store (e.g., 'Kroger', 'Walmart', 'Target')
	 *
	 * @return string The store display name
	 */
	abstract public function GetStoreDisplayName();

	/**
	 * Validate that required configuration is present (API keys, secrets, etc.)
	 *
	 * @return bool True if configuration is valid
	 * @throws \Exception If required configuration is missing
	 */
	abstract public function ValidateConfiguration();
}
