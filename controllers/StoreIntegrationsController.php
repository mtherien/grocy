<?php

namespace Grocy\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class StoreIntegrationsController extends BaseController
{
	public function Overview(Request $request, Response $response, array $args)
	{
		return $this->renderPage($response, 'storeintegrations', [
			'integrations' => $this->getStoreIntegrationsService()->GetAll()
		]);
	}

	public function IntegrationEditForm(Request $request, Response $response, array $args)
	{
		// Get available store types dynamically
		$availableStoreTypes = $this->getStoreIntegrationsService()->GetAvailableStoreTypes();
		$storeTypes = [];

		foreach ($availableStoreTypes as $storeType)
		{
			$label = $storeType['display_name'];
			if (!$storeType['configured'])
			{
				$label .= ' (Not Configured)';
			}

			$storeTypes[] = [
				'value' => $storeType['type'],
				'label' => $label,
				'configured' => $storeType['configured']
			];
		}

		if ($args['integrationId'] == 'new')
		{
			return $this->renderPage($response, 'storeintegrationform', [
				'mode' => 'create',
				'storeTypes' => $storeTypes
			]);
		}
		else
		{
			$integration = $this->getStoreIntegrationsService()->GetById($args['integrationId']);
			$config = json_decode($integration->configuration ?? '{}', true);

			return $this->renderPage($response, 'storeintegrationform', [
				'integration' => $integration,
				'config' => $config,
				'mode' => 'edit',
				'storeTypes' => $storeTypes
			]);
		}
	}

	public function OAuthCallback(Request $request, Response $response, array $args)
	{
		$queryParams = $request->getQueryParams();

		if (!isset($queryParams['code']) || !isset($queryParams['state']))
		{
			return $this->renderPage($response, 'storeintegrationoauth', [
				'success' => false,
				'error' => 'Missing authorization code or state'
			]);
		}

		try
		{
			// Decode state to get integration ID
			$state = json_decode(base64_decode($queryParams['state']), true);
			$integrationId = $state['integration_id'];

			$integration = $this->getStoreIntegrationsService()->GetById($integrationId);

			// Get the appropriate plugin based on store type (dynamically loaded)
			$storeService = $this->getStoreIntegrationsService()->GetStoreServiceForIntegration($integrationId);
			$redirectUri = $this->AppContainer->get('UrlManager')->ConstructUrl('/storeintegrations/oauth/callback', false);

			$tokens = $storeService->ExchangeCodeForToken($queryParams['code'], $redirectUri);

			// Save tokens
			$this->getStoreIntegrationsService()->SetTokens(
				$integrationId,
				$tokens['access_token'],
				$tokens['refresh_token'],
				$tokens['expires_at']
			);

			return $this->renderPage($response, 'storeintegrationoauth', [
				'success' => true,
				'integrationName' => $integration->name
			]);
		}
		catch (\Exception $ex)
		{
			return $this->renderPage($response, 'storeintegrationoauth', [
				'success' => false,
				'error' => $ex->getMessage()
			]);
		}
	}
}
