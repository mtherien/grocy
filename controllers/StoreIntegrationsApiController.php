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

			$result = $this->getStoreIntegrationsService()->SendShoppingListToStore(
				$args['integrationId'],
				$requestBody['shopping_list_id']
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
			$integrationId = $request->getQueryParams()['integration_id'] ?? null;
			$metadata = $this->getStoreIntegrationsService()->GetProductStoreMetadata(
				$args['productId'],
				$integrationId
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

	public function GetStoreLocations(Request $request, Response $response, array $args)
	{
		try
		{
			$locations = $this->getStoreIntegrationsService()->GetStoreLocations($args['integrationId']);
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
			unset($requestBody['is_primary']);

			$locationId = $this->getStoreIntegrationsService()->SaveStoreLocation(
				$args['integrationId'],
				$requestBody,
				$isPrimary
			);

			return $this->ApiResponse($response, ['id' => $locationId]);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function DeleteStoreLocation(Request $request, Response $response, array $args)
	{
		try
		{
			$this->getStoreIntegrationsService()->DeleteStoreLocation($args['locationId']);
			return $this->EmptyApiResponse($response);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function SetPrimaryStoreLocation(Request $request, Response $response, array $args)
	{
		try
		{
			$this->getStoreIntegrationsService()->SetPrimaryStoreLocation($args['locationId']);
			return $this->EmptyApiResponse($response);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}
}
