<?php

namespace Grocy\Controllers;

use Grocy\Controllers\Users\User;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class StoreIntegrationsApiController extends BaseApiController
{
	public function GetAll(Request $request, Response $response, array $args)
	{
		return $this->FilteredApiResponse($response, $this->getStoreIntegrationsService()->GetAll(), $request->getQueryParams());
	}

	public function GetActive(Request $request, Response $response, array $args)
	{
		return $this->FilteredApiResponse($response, $this->getStoreIntegrationsService()->GetActive(), $request->getQueryParams());
	}

	public function GetOne(Request $request, Response $response, array $args)
	{
		try
		{
			$integration = $this->getStoreIntegrationsService()->GetById($args['integrationId']);
			return $this->ApiResponse($response, $integration);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage(), 404);
		}
	}

	public function SendShoppingList(Request $request, Response $response, array $args)
	{
		try
		{
			$requestBody = $this->GetParsedAndFilteredRequestBody($request);

			if (!isset($requestBody['shopping_list_id']))
			{
				return $this->GenericErrorResponse($response, 'shopping_list_id is required');
			}

			$shoppingLocationId = $requestBody['shopping_location_id'] ?? null;

			$result = $this->getStoreIntegrationsService()->SendShoppingListToStore(
				$args['integrationId'],
				$requestBody['shopping_list_id'],
				$shoppingLocationId
			);

			return $this->ApiResponse($response, $result);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function GetAuthorizationUrl(Request $request, Response $response, array $args)
	{
		try
		{
			// Get the appropriate plugin based on store type (dynamically loaded)
			$storeService = $this->getStoreIntegrationsService()->GetStoreServiceForIntegration($args['integrationId']);
			$redirectUri = $this->AppContainer->get('UrlManager')->ConstructUrl('/storeintegrations/oauth/callback', false);
			$authUrl = $storeService->GetAuthorizationUrl($args['integrationId'], $redirectUri);

			return $this->ApiResponse($response, ['auth_url' => $authUrl]);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function RefreshToken(Request $request, Response $response, array $args)
	{
		try
		{
			// Get the appropriate plugin based on store type (dynamically loaded)
			$storeService = $this->getStoreIntegrationsService()->GetStoreServiceForIntegration($args['integrationId']);
			$storeService->RefreshToken($args['integrationId']);

			return $this->EmptyApiResponse($response);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function LookupProductMetadata(Request $request, Response $response, array $args)
	{
		try
		{
			$metadata = $this->getStoreIntegrationsService()->LookupProductMetadata(
				$args['integrationId'],
				$args['productId']
			);

			return $this->ApiResponse($response, $metadata);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function GetProductMetadata(Request $request, Response $response, array $args)
	{
		try
		{
			$shoppingLocationId = $request->getQueryParams()['shopping_location_id'] ?? null;
			$metadata = $this->getStoreIntegrationsService()->GetProductStoreMetadata(
				$args['productId'],
				$shoppingLocationId
			);

			if ($metadata)
			{
				return $this->ApiResponse($response, $metadata);
			}
			else
			{
				return $this->GenericErrorResponse($response, 'No metadata found for this product', 404);
			}
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function SearchStoreLocations(Request $request, Response $response, array $args)
	{
		try
		{
			$requestBody = $this->GetParsedAndFilteredRequestBody($request);
			$zipCode = $requestBody['zip_code'] ?? null;
			$lat = $requestBody['lat'] ?? null;
			$lon = $requestBody['lon'] ?? null;

			$locations = $this->getStoreIntegrationsService()->SearchStoreLocations(
				$args['integrationId'],
				$zipCode,
				$lat,
				$lon
			);

			return $this->ApiResponse($response, $locations);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function GetShoppingLocations(Request $request, Response $response, array $args)
	{
		try
		{
			$locations = $this->getStoreIntegrationsService()->GetShoppingLocationsForIntegration($args['integrationId']);
			return $this->ApiResponse($response, $locations);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function SaveStoreLocation(Request $request, Response $response, array $args)
	{
		try
		{
			$requestBody = $this->GetParsedAndFilteredRequestBody($request);
			$isPrimary = $requestBody['is_primary'] ?? false;
			$customName = $requestBody['custom_name'] ?? null;
			$locationData = $requestBody['location_data'] ?? $requestBody;

			// Remove non-location data from location_data
			unset($locationData['is_primary']);
			unset($locationData['custom_name']);

			$shoppingLocationId = $this->getStoreIntegrationsService()->SaveStoreLocation(
				$args['integrationId'],
				$locationData,
				$isPrimary,
				$customName
			);

			return $this->ApiResponse($response, ['shopping_location_id' => $shoppingLocationId]);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function DeleteShoppingLocation(Request $request, Response $response, array $args)
	{
		try
		{
			$this->getStoreIntegrationsService()->DeleteShoppingLocation($args['locationId']);
			return $this->EmptyApiResponse($response);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function SetPrimaryShoppingLocation(Request $request, Response $response, array $args)
	{
		try
		{
			$this->getStoreIntegrationsService()->SetPrimaryShoppingLocation($args['locationId']);
			return $this->EmptyApiResponse($response);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function SearchProducts(Request $request, Response $response, array $args)
	{
		try
		{
			$requestBody = $this->GetParsedAndFilteredRequestBody($request);
			$searchTerm = $requestBody['search_term'] ?? null;
			$shoppingLocationId = $requestBody['shopping_location_id'] ?? null;

			if (empty($searchTerm))
			{
				return $this->GenericErrorResponse($response, 'search_term is required');
			}

			$products = $this->getStoreIntegrationsService()->SearchProducts(
				$args['integrationId'],
				$searchTerm,
				$shoppingLocationId
			);

			return $this->ApiResponse($response, $products);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function CreateOrFindProductFromStore(Request $request, Response $response, array $args)
	{
		try
		{
			$requestBody = $this->GetParsedAndFilteredRequestBody($request);
			$externalProductId = $requestBody['external_product_id'] ?? null;
			$name = $requestBody['name'] ?? null;
			$brand = $requestBody['brand'] ?? null;
			$upc = $requestBody['upc'] ?? null;
			$imageUrl = $requestBody['image_url'] ?? null;
			$description = $requestBody['description'] ?? null;
			$price = $requestBody['price'] ?? null;
			$size = $requestBody['size'] ?? null;
			$aisle = $requestBody['aisle'] ?? null;
			$shelf = $requestBody['shelf'] ?? null;
			$department = $requestBody['department'] ?? null;
			$shoppingLocationId = $requestBody['shopping_location_id'] ?? null;

			if (empty($name))
			{
				return $this->GenericErrorResponse($response, 'Product name is required');
			}

			// Check if product already exists by UPC
			$existingProduct = null;
			if (!empty($upc))
			{
				$barcode = $this->getDatabase()->product_barcodes()
					->where('barcode = :1', $upc)
					->fetch();

				if ($barcode)
				{
					$existingProduct = $this->getDatabase()->products()
						->where('id = :1', $barcode->product_id)
						->fetch();
				}
			}

			if ($existingProduct)
			{
				// Product already exists - update shopping_location_id if not set and provided
				if ($shoppingLocationId && empty($existingProduct->shopping_location_id))
				{
					$existingProduct->update(['shopping_location_id' => $shoppingLocationId]);
				}

				// Save/update metadata to product_store_metadata table if shopping location is provided
				if ($shoppingLocationId && (!empty($aisle) || !empty($shelf) || !empty($department) || !empty($price)))
				{
					// Get the store_integration_id from the shopping location
					$location = $this->getDatabase()->shopping_locations()->where('id = :1', $shoppingLocationId)->fetch();
					$storeIntegrationId = $location ? $location->store_integration_id : null;

					$metadataData = [
						'product_id' => $existingProduct->id,
						'shopping_location_id' => $shoppingLocationId,
						'store_integration_id_old' => $storeIntegrationId
					];

					if (!empty($aisle))
					{
						$metadataData['aisle'] = $aisle;
					}

					if (!empty($shelf))
					{
						$metadataData['shelf'] = $shelf;
					}

					if (!empty($department))
					{
						$metadataData['department'] = $department;
					}

					if (!empty($price))
					{
						$metadataData['price'] = $price;
					}

					// Check if metadata already exists for this product/location
					$existingMetadata = $this->getDatabase()->product_store_metadata()
						->where('product_id = :1', $existingProduct->id)
						->where('shopping_location_id = :2', $shoppingLocationId)
						->fetch();

					if ($existingMetadata)
					{
						$existingMetadata->update($metadataData);
					}
					else
					{
						$this->getDatabase()->product_store_metadata()->insert($metadataData);
					}
				}

				// Product already exists, return its ID
				return $this->ApiResponse($response, ['product_id' => $existingProduct->id]);
			}

			// Create new product
			$productName = $brand ? $brand . ' - ' . $name : $name;

			// Get default location and QU from user settings
			$userSettings = $this->getUsersService()->GetUserSettings(GROCY_USER_ID);
			$locationId = $userSettings['product_presets_location_id'] ?? null;
			$quId = $userSettings['product_presets_qu_id'] ?? null;

			// If no user defaults, get first location and QU
			if (!$locationId)
			{
				$location = $this->getDatabase()->locations()->fetch();
				$locationId = $location ? $location->id : 1;
			}

			if (!$quId)
			{
				$qu = $this->getDatabase()->quantity_units()->fetch();
				$quId = $qu ? $qu->id : 1;
			}

			// Create product
			$productData = [
				'name' => $productName,
				'location_id' => $locationId,
				'qu_id_purchase' => $quId,
				'qu_id_stock' => $quId,
				'shopping_location_id' => $shoppingLocationId
			];

			// Add optional fields if provided
			if (!empty($description))
			{
				$productData['description'] = $description;
			}

			if (!empty($aisle))
			{
				$productData['store_location_aisle'] = $aisle;
			}

			if (!empty($shelf))
			{
				$productData['store_location_shelf'] = $shelf;
			}

			if (!empty($department))
			{
				$productData['store_location_department'] = $department;
			}

			// Set store location updated timestamp if any store location data is provided
			if (!empty($aisle) || !empty($shelf) || !empty($department))
			{
				$productData['store_location_updated'] = date('Y-m-d H:i:s');
			}

			$newProduct = $this->getDatabase()->products()->insert($productData);
			$productId = $this->getDatabase()->lastInsertId();

			// Add barcode if UPC is provided
			if (!empty($upc))
			{
				$this->getDatabase()->product_barcodes()->insert([
					'product_id' => $productId,
					'barcode' => $upc
				]);
			}

			// Save metadata to product_store_metadata table if shopping location is provided
			if ($shoppingLocationId && (!empty($aisle) || !empty($shelf) || !empty($department) || !empty($price)))
			{
				// Get the store_integration_id from the shopping location
				$location = $this->getDatabase()->shopping_locations()->where('id = :1', $shoppingLocationId)->fetch();
				$storeIntegrationId = $location ? $location->store_integration_id : null;

				$metadataData = [
					'product_id' => $productId,
					'shopping_location_id' => $shoppingLocationId,
					'store_integration_id_old' => $storeIntegrationId
				];

				if (!empty($aisle))
				{
					$metadataData['aisle'] = $aisle;
				}

				if (!empty($shelf))
				{
					$metadataData['shelf'] = $shelf;
				}

				if (!empty($department))
				{
					$metadataData['department'] = $department;
				}

				if (!empty($price))
				{
					$metadataData['price'] = $price;
				}

				// Check if metadata already exists for this product/location
				$existingMetadata = $this->getDatabase()->product_store_metadata()
					->where('product_id = :1', $productId)
					->where('shopping_location_id = :2', $shoppingLocationId)
					->fetch();

				if ($existingMetadata)
				{
					$existingMetadata->update($metadataData);
				}
				else
				{
					$this->getDatabase()->product_store_metadata()->insert($metadataData);
				}
			}

			// Download and save product image if URL is provided
			if (!empty($imageUrl))
			{
				try
				{
					// Download image using cURL for better HTTPS support
					$ch = curl_init($imageUrl);
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
					curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
					curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For development
					$imageData = curl_exec($ch);
					$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
					curl_close($ch);

					if ($imageData !== false && $httpCode === 200)
					{
						$imageFilename = $productId . '.jpg';

						// Use FilesService to get the correct path (includes storage subdirectory)
						$imagePath = $this->getFilesService()->GetFilePath('productpictures', $imageFilename);

						// Save image file
						$result = file_put_contents($imagePath, $imageData);
						if ($result !== false)
						{
							// Update product with picture filename
							$this->getDatabase()->products($productId)->update([
								'picture_file_name' => $imageFilename
							]);
						}
						else
						{
							error_log('Failed to write product image file: ' . $imagePath);
						}
					}
					else
					{
						error_log('Failed to download product image from: ' . $imageUrl . ' (HTTP ' . $httpCode . ')');
					}
				}
				catch (\Exception $ex)
				{
					// Image download failed, but continue anyway
					error_log('Failed to download product image: ' . $ex->getMessage());
				}
			}

			return $this->ApiResponse($response, ['product_id' => $productId]);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}
}
