var locationsTable = $('#shoppinglocations-table').DataTable({
	'order': [[1, 'asc']],
	'columnDefs': [
		{ 'orderable': false, 'targets': 0 },
		{ 'searchable': false, "targets": 0 }
	].concat($.fn.dataTable.defaults.columnDefs)
});
$('#shoppinglocations-table tbody').removeClass("d-none");
locationsTable.columns.adjust().draw();

$("#search").on("keyup", Delay(function()
{
	var value = $(this).val();
	if (value === "all")
	{
		value = "";
	}

	locationsTable.search(value).draw();
}, Grocy.FormFocusDelay));

$("#clear-filter-button").on("click", function()
{
	$("#search").val("");
	locationsTable.search("").draw();
});

$(document).on('click', '.shoppinglocation-delete-button', function(e)
{
	var objectName = $(e.currentTarget).attr('data-shoppinglocation-name');
	var objectId = $(e.currentTarget).attr('data-shoppinglocation-id');

	bootbox.confirm({
		message: __t('Are you sure you want to delete store "%s"?', objectName),
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
				Grocy.Api.Delete('objects/shopping_locations/' + objectId, {},
					function(result)
					{
						window.location.href = U('/shoppinglocations');
					},
					function(xhr)
					{
						console.error(xhr);
					}
				);
			}
		}
	});
});

$("#show-disabled").change(function()
{
	if (this.checked)
	{
		window.location.href = U('/shoppinglocations?include_disabled');
	}
	else
	{
		window.location.href = U('/shoppinglocations');
	}
});

if (GetUriParam('include_disabled'))
{
	$("#show-disabled").prop('checked', true);
}

// Add from Integration Modal functionality
$('#integration-select').on('change', function() {
	var integrationId = $(this).val();
	if (integrationId) {
		$('#location-search-section').removeClass('d-none');
	} else {
		$('#location-search-section').addClass('d-none');
		$('#store-results-section').addClass('d-none');
	}
});

$('#search-stores-button').on('click', function() {
	var integrationId = $('#integration-select').val();
	var zipCode = $('#zip-code-input').val();

	if (!integrationId) {
		toastr.error(__t('Please select an integration'));
		return;
	}

	if (!zipCode) {
		toastr.error(__t('Please enter a zip code'));
		return;
	}

	// Show loading spinner
	$('#store-search-loading').removeClass('d-none');
	$('#store-results-section').addClass('d-none');

	// Search for store locations
	Grocy.Api.Post('store-integrations/' + integrationId + '/locations/search', {
		zip_code: zipCode
	},
		function(result) {
			$('#store-search-loading').addClass('d-none');
			$('#store-results-section').removeClass('d-none');

			var resultsList = $('#store-results-list');
			resultsList.empty();

			if (result && result.length > 0) {
				result.forEach(function(store) {
					var address = '';
					if (store.address && store.address.addressLine1) {
						address = store.address.addressLine1;
						if (store.address.city) address += ', ' + store.address.city;
						if (store.address.state) address += ', ' + store.address.state;
						if (store.address.zipCode) address += ' ' + store.address.zipCode;
					}

					var distance = store.distance ? ' (' + store.distance.toFixed(1) + ' mi)' : '';

					var storeItem = $('<a href="#" class="list-group-item list-group-item-action add-store-location"></a>')
						.data('store-data', store)
						.data('integration-id', integrationId)
						.html('<strong>' + store.name + '</strong>' + distance + '<br><small class="text-muted">' + address + '</small>');

					resultsList.append(storeItem);
				});
			} else {
				resultsList.html('<div class="alert alert-info">' + __t('No stores found') + '</div>');
			}
		},
		function(xhr) {
			$('#store-search-loading').addClass('d-none');
			console.error(xhr);
			toastr.error(__t('Error searching for stores'));
		}
	);
});

$(document).on('click', '.add-store-location', function(e) {
	e.preventDefault();

	var storeData = $(this).data('store-data');
	var integrationId = $(this).data('integration-id');

	// Save the store location - merge store data with is_primary flag
	Grocy.Api.Post('store-integrations/' + integrationId + '/locations', Object.assign({}, storeData, {
		is_primary: false
	}),
		function(result) {
			toastr.success(__t('Store added successfully'));
			$('#add-from-integration-modal').modal('hide');
			window.location.reload();
		},
		function(xhr) {
			console.error(xhr);
			toastr.error(__t('Error adding store'));
		}
	);
});
