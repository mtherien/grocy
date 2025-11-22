<?php

namespace Grocy\Services;

use LessQL\Result;

class StoreIntegrationsService extends BaseService
{
	public function GetAll(): Result
	{
		return $this->getDatabase()->store_integrations()->orderBy('name');
	}

	public function GetActive(): Result
	{
		return $this->getDatabase()->store_integrations()->where('active = 1')->orderBy('name');
	}

	public function GetById($id)
	{
		$integration = $this->getDatabase()->store_integrations()->where('id = :1', $id)->fetch();
		if ($integration === null)
		{
			throw new \Exception('Store integration does not exist');
		}

		return $integration;
	}

	public function Create($name, $storeType, $configuration = null)
	{
		$integrationRow = $this->getDatabase()->store_integrations()->createRow([
			'name' => $name,
			'store_type' => $storeType,
			'configuration' => $configuration ? json_encode($configuration) : null,
			'active' => 1
		]);

		$integrationRow->save();

		return $integrationRow->id;
	}

	public function Update($id, $name, $active, $configuration = null)
	{
		if (!$this->IntegrationExists($id))
		{
			throw new \Exception('Store integration does not exist');
		}

		$integrationRow = $this->getDatabase()->store_integrations()->where('id = :1', $id)->fetch();
		$integrationRow->update([
			'name' => $name,
			'active' => $active,
			'configuration' => $configuration ? json_encode($configuration) : $integrationRow->configuration
		]);

		return true;
	}

	public function Delete($id)
	{
		if (!$this->IntegrationExists($id))
		{
			throw new \Exception('Store integration does not exist');
		}

		$integrationRow = $this->getDatabase()->store_integrations()->where('id = :1', $id)->fetch();
		$integrationRow->delete();

		return true;
	}

	public function SetTokens($id, $accessToken, $refreshToken, $expiresAt)
	{
		if (!$this->IntegrationExists($id))
		{
			throw new \Exception('Store integration does not exist');
		}

		$integrationRow = $this->getDatabase()->store_integrations()->where('id = :1', $id)->fetch();
		$integrationRow->update([
			'access_token' => $accessToken,
			'refresh_token' => $refreshToken,
			'token_expires_at' => $expiresAt
		]);

		return true;
	}

	public function IsTokenExpired($id)
	{
		$integration = $this->GetById($id);

		if (empty($integration->token_expires_at))
		{
			return true;
		}

		$expiresAt = new \DateTime($integration->token_expires_at);
		$now = new \DateTime();

		return $now >= $expiresAt;
	}

	public function SendShoppingListToStore($integrationId, $shoppingListId, $locationId = null)
	{
		$integration = $this->GetById($integrationId);

		if (!$integration->active)
		{
			throw new \Exception('Store integration is not active');
		}

		// Get the appropriate store service based on store_type
		$storeService = $this->getStoreService($integration->store_type);

		// Refresh token if expired
		if ($this->IsTokenExpired($integrationId))
		{
			$storeService->RefreshToken($integrationId);
		}

		// Send the shopping list to the store
		return $storeService->SendShoppingList($integrationId, $shoppingListId, $locationId);
	}

	public function LookupProductMetadata($integrationId, $productId)
	{
		$integration = $this->GetById($integrationId);

		if (!$integration->active)
		{
			throw new \Exception('Store integration is not active');
		}

		// Get the appropriate store service based on store_type
		$storeService = $this->getStoreService($integration->store_type);

		// Refresh token if expired
		if ($this->IsTokenExpired($integrationId))
		{
			$storeService->RefreshToken($integrationId);
		}

		// Lookup product metadata
		return $storeService->LookupProductMetadata($integrationId, $productId);
	}

	public function GetProductStoreMetadata($productId, $integrationId = null)
	{
		if ($integrationId)
		{
			return $this->getDatabase()->product_store_metadata()
				->where('product_id = :1', $productId)
				->where('store_integration_id = :2', $integrationId)
				->fetch();
		}
		else
		{
			return $this->getDatabase()->product_store_metadata()
				->where('product_id = :1', $productId)
				->orderBy('last_updated', 'DESC')
				->fetch();
		}
	}

	public function SearchStoreLocations($integrationId, $zipCode = null, $lat = null, $lon = null)
	{
		$integration = $this->GetById($integrationId);

		if (!$integration->active)
		{
			throw new \Exception('Store integration is not active');
		}

		// Get the appropriate store service based on store_type
		$storeService = $this->getStoreService($integration->store_type);

		// Refresh token if expired
		if ($this->IsTokenExpired($integrationId))
		{
			$storeService->RefreshToken($integrationId);
		}

		return $storeService->SearchStoreLocations($integrationId, $zipCode, $lat, $lon);
	}

	public function GetStoreLocations($integrationId)
	{
		return $this->getDatabase()->store_integration_locations()
			->where('store_integration_id = :1', $integrationId)
			->orderBy('is_primary', 'DESC')
			->orderBy('name');
	}

	public function GetPrimaryStoreLocation($integrationId)
	{
		return $this->getDatabase()->store_integration_locations()
			->where('store_integration_id = :1', $integrationId)
			->where('is_primary = 1')
			->fetch();
	}

	public function SaveStoreLocation($integrationId, $locationData, $isPrimary = false)
	{
		$integration = $this->GetById($integrationId);

		// Get the appropriate store service based on store_type
		$storeService = $this->getStoreService($integration->store_type);

		return $storeService->SaveStoreLocation($integrationId, $locationData, $isPrimary);
	}

	public function DeleteStoreLocation($locationId)
	{
		$location = $this->getDatabase()->store_integration_locations()
			->where('id = :1', $locationId)
			->fetch();

		if (!$location)
		{
			throw new \Exception('Store location not found');
		}

		$location->delete();
		return true;
	}

	public function SetPrimaryStoreLocation($locationId)
	{
		$location = $this->getDatabase()->store_integration_locations()
			->where('id = :1', $locationId)
			->fetch();

		if (!$location)
		{
			throw new \Exception('Store location not found');
		}

		// Unset other primary locations for this integration
		$this->getDatabase()->store_integration_locations()
			->where('store_integration_id = :1', $location->store_integration_id)
			->update(['is_primary' => 0]);

		// Set this as primary
		$location->update(['is_primary' => 1]);

		return true;
	}

	public function SearchProducts($integrationId, $searchTerm, $locationId = null)
	{
		$integration = $this->GetById($integrationId);

		if (!$integration->active)
		{
			throw new \Exception('Store integration is not active');
		}

		// Get the appropriate store service based on store_type
		$storeService = $this->getStoreService($integration->store_type);

		// Refresh token if expired
		if ($this->IsTokenExpired($integrationId))
		{
			$storeService->RefreshToken($integrationId);
		}

		// Search for products
		return $storeService->SearchProducts($integrationId, $searchTerm, $locationId);
	}

	private $loadedPlugins = [];

	/**
	 * Get the store service plugin for a specific integration
	 *
	 * @param int $integrationId The integration ID
	 * @return \Grocy\Helpers\BaseStoreIntegrationPlugin
	 * @throws \Exception If integration not found or plugin not available
	 */
	public function GetStoreServiceForIntegration($integrationId)
	{
		$integration = $this->GetById($integrationId);
		return $this->getStoreService($integration->store_type);
	}

	/**
	 * Get the appropriate store plugin instance based on store type
	 *
	 * @param string $storeType The store type identifier (e.g., 'kroger')
	 * @return \Grocy\Helpers\BaseStoreIntegrationPlugin
	 * @throws \Exception If store type is not supported
	 */
	private function getStoreService($storeType)
	{
		// Return cached plugin if already loaded
		if (isset($this->loadedPlugins[$storeType]))
		{
			return $this->loadedPlugins[$storeType];
		}

		// Find and load the plugin
		$plugin = $this->loadStoreIntegrationPlugin($storeType);
		$this->loadedPlugins[$storeType] = $plugin;

		return $plugin;
	}

	/**
	 * Load a store integration plugin by store type
	 *
	 * @param string $storeType The store type identifier
	 * @return \Grocy\Helpers\BaseStoreIntegrationPlugin
	 * @throws \Exception If plugin not found
	 */
	private function loadStoreIntegrationPlugin($storeType)
	{
		// Discover all available plugins
		$availablePlugins = $this->discoverStoreIntegrationPlugins();

		// Find the plugin with matching store type
		foreach ($availablePlugins as $pluginInfo)
		{
			if ($pluginInfo['type'] === $storeType)
			{
				// Load the plugin file if not already loaded
				if (!class_exists($pluginInfo['class']))
				{
					require_once $pluginInfo['path'];
				}

				// Instantiate the plugin
				$className = $pluginInfo['class'];
				return new $className($this->getDatabase(), $this);
			}
		}

		throw new \Exception("Store integration plugin for type '$storeType' not found");
	}

	/**
	 * Discover all available store integration plugins
	 *
	 * @return array Array of plugin info with keys: class, path, type, name
	 */
	private function discoverStoreIntegrationPlugins()
	{
		$plugins = [];

		// Scan built-in plugins directory
		$builtInPath = __DIR__ . '/../plugins';
		$plugins = array_merge($plugins, $this->scanPluginDirectory($builtInPath, false));

		// Scan user plugins directory (these take precedence)
		$userPath = GROCY_DATAPATH . '/plugins';
		if (file_exists($userPath))
		{
			$userPlugins = $this->scanPluginDirectory($userPath, true);
			// User plugins override built-in plugins with same type
			foreach ($userPlugins as $userPlugin)
			{
				// Remove any built-in plugin with same type
				$plugins = array_filter($plugins, function ($p) use ($userPlugin) {
					return $p['type'] !== $userPlugin['type'];
				});
				$plugins[] = $userPlugin;
			}
		}

		return $plugins;
	}

	/**
	 * Scan a directory for store integration plugins
	 *
	 * @param string $directory The directory to scan
	 * @param bool $isUserPlugin Whether this is a user plugin directory
	 * @return array Array of plugin info
	 */
	private function scanPluginDirectory($directory, $isUserPlugin)
	{
		$plugins = [];

		if (!is_dir($directory))
		{
			return $plugins;
		}

		$files = glob($directory . '/*StoreIntegrationPlugin.php');

		foreach ($files as $file)
		{
			$className = basename($file, '.php');

			// Temporarily include the file to get plugin info
			require_once $file;

			if (!class_exists($className))
			{
				continue;
			}

			// Create a temporary instance to get store type and name
			try
			{
				$tempPlugin = new $className($this->getDatabase(), $this);
				$plugins[] = [
					'class' => $className,
					'path' => $file,
					'type' => $tempPlugin->GetStoreType(),
					'name' => $tempPlugin->GetStoreDisplayName(),
					'is_user_plugin' => $isUserPlugin
				];
			}
			catch (\Exception $ex)
			{
				// Skip plugins that fail to instantiate
				continue;
			}
		}

		return $plugins;
	}

	/**
	 * Get all available store types
	 *
	 * @return array Array of store types with keys: type, display_name, configured
	 */
	public function GetAvailableStoreTypes()
	{
		$storeTypes = [];

		// Discover all available plugins
		$availablePlugins = $this->discoverStoreIntegrationPlugins();

		foreach ($availablePlugins as $pluginInfo)
		{
			try
			{
				// Load plugin if not already loaded
				if (!class_exists($pluginInfo['class']))
				{
					require_once $pluginInfo['path'];
				}

				// Instantiate plugin to check configuration
				$className = $pluginInfo['class'];
				$plugin = new $className($this->getDatabase(), $this);

				// Validate configuration
				$plugin->ValidateConfiguration();

				$storeTypes[] = [
					'type' => $plugin->GetStoreType(),
					'display_name' => $plugin->GetStoreDisplayName(),
					'configured' => true,
					'is_user_plugin' => $pluginInfo['is_user_plugin']
				];
			}
			catch (\Exception $ex)
			{
				$storeTypes[] = [
					'type' => $pluginInfo['type'],
					'display_name' => $pluginInfo['name'],
					'configured' => false,
					'error' => $ex->getMessage(),
					'is_user_plugin' => $pluginInfo['is_user_plugin']
				];
			}
		}

		return $storeTypes;
	}

	private function IntegrationExists($id)
	{
		$integrationRow = $this->getDatabase()->store_integrations()->where('id = :1', $id)->fetch();
		return $integrationRow !== null;
	}
}
