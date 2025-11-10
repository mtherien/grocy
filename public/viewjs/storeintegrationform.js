$('#save-storeintegration-button').on('click', function(e)
{
	e.preventDefault();

	if (!Grocy.FrontendHelpers.ValidateForm("storeintegration-form", true))
	{
		return;
	}

	if ($(".combobox-menu-visible").length)
	{
		return;
	}

	var jsonData = $('#storeintegration-form').serializeJSON();

	// Convert active checkbox to 1/0
	if (Grocy.EditMode === 'edit')
	{
		jsonData.active = $('#active').is(':checked') ? 1 : 0;
	}

	// Handle configuration
	if (Grocy.EditMode === 'edit' && Grocy.StoreType === 'kroger')
	{
		var config = {};
		if (jsonData.location_id && jsonData.location_id.trim() !== '')
		{
			config.location_id = jsonData.location_id.trim();
		}
		jsonData.configuration = JSON.stringify(config);
		delete jsonData.location_id;
	}

	Grocy.FrontendHelpers.BeginUiBusy("storeintegration-form");

	if (Grocy.EditMode === 'create')
	{
		Grocy.Api.Post('objects/store_integrations', jsonData,
			function(result)
			{
				if (GetUriParam("embedded") !== undefined)
				{
					window.parent.postMessage(WindowMessageBag("Reload"), Grocy.BaseUrl);
				}
				else
				{
					window.location.href = U('/storeintegrations');
				}
			},
			function(xhr)
			{
				Grocy.FrontendHelpers.EndUiBusy("storeintegration-form");
				Grocy.FrontendHelpers.ShowGenericError('Error while saving, probably this item already exists', xhr.response);
			}
		);
	}
	else
	{
		Grocy.Api.Put('objects/store_integrations/' + Grocy.EditObjectId, jsonData,
			function(result)
			{
				if (GetUriParam("embedded") !== undefined)
				{
					window.parent.postMessage(WindowMessageBag("Reload"), Grocy.BaseUrl);
				}
				else
				{
					window.location.href = U('/storeintegrations');
				}
			},
			function(xhr)
			{
				Grocy.FrontendHelpers.EndUiBusy("storeintegration-form");
				Grocy.FrontendHelpers.ShowGenericError('Error while saving, probably this item already exists', xhr.response);
			}
		);
	}
});

$('#authenticate-button').on('click', function(e)
{
	e.preventDefault();

	if (Grocy.EditMode !== 'edit')
	{
		return;
	}

	Grocy.FrontendHelpers.BeginUiBusy();

	Grocy.Api.Get('store-integrations/' + Grocy.EditObjectId + '/auth-url',
		function(result)
		{
			Grocy.FrontendHelpers.EndUiBusy();

			if (result.auth_url)
			{
				// Open OAuth URL in new window
				var authWindow = window.open(result.auth_url, 'KrogerAuth', 'width=600,height=700');

				// Optional: Monitor the window for close to refresh the page
				var pollTimer = window.setInterval(function()
				{
					if (authWindow.closed !== false)
					{
						window.clearInterval(pollTimer);
						// Refresh the page to show updated auth status
						window.location.reload();
					}
				}, 200);
			}
		},
		function(xhr)
		{
			Grocy.FrontendHelpers.EndUiBusy();
			Grocy.FrontendHelpers.ShowGenericError('Error while getting authorization URL', xhr.response);
		}
	);
});

$('#storeintegration-form input').keyup(function(event)
{
	Grocy.FrontendHelpers.ValidateForm('storeintegration-form');
});

$('#storeintegration-form input').keydown(function(event)
{
	if (event.keyCode === 13) // Enter
	{
		event.preventDefault();

		if (!Grocy.FrontendHelpers.ValidateForm('storeintegration-form'))
		{
			return false;
		}
		else
		{
			$('#save-storeintegration-button').click();
		}
	}
});

setTimeout(function()
{
	$('#name').focus();
}, Grocy.FormFocusDelay);

Grocy.FrontendHelpers.ValidateForm('storeintegration-form');

// Load saved store locations if in edit mode
if (Grocy.EditMode === 'edit')
{
	loadSavedStoreLocations();
}

// Search for store locations
$('#search-stores-button').on('click', function(e)
{
	e.preventDefault();

	var zipCode = $('#search_zip').val().trim();

	if (!zipCode)
	{
		toastr.error(__t('Please enter a zip code'));
		return;
	}

	Grocy.FrontendHelpers.BeginUiBusy();

	Grocy.Api.Post('store-integrations/' + Grocy.EditObjectId + '/locations/search', {
		zip_code: zipCode
	},
		function(results)
		{
			Grocy.FrontendHelpers.EndUiBusy();
			displayStoreSearchResults(results);
		},
		function(xhr)
		{
			Grocy.FrontendHelpers.EndUiBusy();
			console.error(xhr);
			Grocy.FrontendHelpers.ShowGenericError('Error while searching for stores', xhr.response);
		}
	);
});

function displayStoreSearchResults(results)
{
	if (!results || results.length === 0)
	{
		$('#store-search-results').removeClass('d-none');
		$('#store-search-results-list').html('<p class="text-muted small">' + __t('No stores found') + '</p>');
		return;
	}

	var html = '<div class="list-group">';
	results.forEach(function(store)
	{
		var address = (store.address.addressLine1 || '') + ', ' + (store.address.city || '') + ', ' + (store.address.state || '') + ' ' + (store.address.zipCode || '');

		html += '<div class="list-group-item">';
		html += '<div class="d-flex justify-content-between align-items-start">';
		html += '<div>';
		html += '<h6 class="mb-1">' + (store.name || 'Unknown Store') + '</h6>';
		html += '<p class="mb-1 small">' + address + '</p>';
		if (store.phone)
		{
			html += '<p class="mb-0 small text-muted">' + store.phone + '</p>';
		}
		html += '</div>';
		html += '<button class="btn btn-sm btn-primary add-store-location-button" data-store-data=\'' + JSON.stringify(store) + '\'>' + __t('Add') + '</button>';
		html += '</div>';
		html += '</div>';
	});
	html += '</div>';

	$('#store-search-results-list').html(html);
	$('#store-search-results').removeClass('d-none');
}

$(document).on('click', '.add-store-location-button', function(e)
{
	e.preventDefault();

	var storeData = JSON.parse($(e.currentTarget).attr('data-store-data'));
	var isPrimary = $('#saved-stores-list').children('.list-group-item').length === 0; // First store is primary

	Grocy.FrontendHelpers.BeginUiBusy();

	Grocy.Api.Post('store-integrations/' + Grocy.EditObjectId + '/locations', Object.assign({}, storeData, {
		is_primary: isPrimary
	}),
		function(result)
		{
			Grocy.FrontendHelpers.EndUiBusy();
			toastr.success(__t('Store location added'));
			loadSavedStoreLocations();
			$('#store-search-results').addClass('d-none');
			$('#search_zip').val('');
		},
		function(xhr)
		{
			Grocy.FrontendHelpers.EndUiBusy();
			console.error(xhr);
			Grocy.FrontendHelpers.ShowGenericError('Error while adding store location', xhr.response);
		}
	);
});

function loadSavedStoreLocations()
{
	Grocy.Api.Get('store-integrations/' + Grocy.EditObjectId + '/locations',
		function(locations)
		{
			if (!locations || locations.length === 0)
			{
				$('#saved-stores-list').html('<div class="text-muted small">' + __t('No locations saved yet. Search and add a location above.') + '</div>');
				return;
			}

			var html = '';
			locations.forEach(function(location)
			{
				var address = (location.address || '') + ', ' + (location.city || '') + ', ' + (location.state || '') + ' ' + (location.zip_code || '');

				html += '<div class="list-group-item">';
				html += '<div class="d-flex justify-content-between align-items-start">';
				html += '<div>';
				html += '<h6 class="mb-1">' + location.name;
				if (location.is_primary == 1)
				{
					html += ' <span class="badge badge-primary">Primary</span>';
				}
				html += '</h6>';
				html += '<p class="mb-0 small">' + address + '</p>';
				html += '</div>';
				html += '<div class="btn-group-vertical btn-group-sm">';
				if (location.is_primary != 1)
				{
					html += '<button class="btn btn-sm btn-outline-secondary set-primary-button" data-location-id="' + location.id + '">' + __t('Set Primary') + '</button>';
				}
				html += '<button class="btn btn-sm btn-outline-danger delete-location-button" data-location-id="' + location.id + '">' + __t('Delete') + '</button>';
				html += '</div>';
				html += '</div>';
				html += '</div>';
			});

			$('#saved-stores-list').html(html);
		},
		function(xhr)
		{
			console.error(xhr);
		}
	);
}

$(document).on('click', '.set-primary-button', function(e)
{
	e.preventDefault();

	var locationId = $(e.currentTarget).attr('data-location-id');

	Grocy.FrontendHelpers.BeginUiBusy();

	Grocy.Api.Post('store-locations/' + locationId + '/set-primary', {},
		function()
		{
			Grocy.FrontendHelpers.EndUiBusy();
			toastr.success(__t('Primary location updated'));
			loadSavedStoreLocations();
		},
		function(xhr)
		{
			Grocy.FrontendHelpers.EndUiBusy();
			console.error(xhr);
			Grocy.FrontendHelpers.ShowGenericError('Error while setting primary location', xhr.response);
		}
	);
});

$(document).on('click', '.delete-location-button', function(e)
{
	e.preventDefault();

	var locationId = $(e.currentTarget).attr('data-location-id');

	bootbox.confirm({
		message: __t('Are you sure you want to delete this store location?'),
		closeButton: false,
		buttons: {
			confirm: {
				label: __t('Yes'),
				className: 'btn-success'
			},
			cancel: {
				label: __t('No'),
				className: 'btn-danger'
			}
		},
		callback: function(result)
		{
			if (result === true)
			{
				Grocy.FrontendHelpers.BeginUiBusy();

				Grocy.Api.Delete('store-locations/' + locationId, {},
					function()
					{
						Grocy.FrontendHelpers.EndUiBusy();
						toastr.success(__t('Store location deleted'));
						loadSavedStoreLocations();
					},
					function(xhr)
					{
						Grocy.FrontendHelpers.EndUiBusy();
						console.error(xhr);
						Grocy.FrontendHelpers.ShowGenericError('Error while deleting store location', xhr.response);
					}
				);
			}
		}
	});
});
