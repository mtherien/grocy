// Destroy existing DataTable instance if it exists
if ($.fn.DataTable.isDataTable('#storeintegrations-table'))
{
	$('#storeintegrations-table').DataTable().destroy();
}

var storeIntegrationsTable = $('#storeintegrations-table').DataTable({
	'order': [[1, 'asc']],
	'columnDefs': [
		{ 'orderable': false, 'targets': 0 }
	]
});

$('#storeintegrations-table tbody').removeClass('d-none');
storeIntegrationsTable.columns.adjust().draw();

$(document).on('click', '.integration-delete-button', function(e)
{
	var objectName = $(e.currentTarget).attr('data-integration-name');
	var objectId = $(e.currentTarget).attr('data-integration-id');

	bootbox.confirm({
		message: __t('Are you sure to delete store integration "%s"?', objectName),
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
				Grocy.Api.Delete('objects/store_integrations/' + objectId, {},
					function(result)
					{
						window.location.href = U('/storeintegrations');
					},
					function(xhr)
					{
						Grocy.FrontendHelpers.ShowGenericError('Error while deleting', xhr.response);
					}
				);
			}
		}
	});
});

$('#search').on('keyup', function()
{
	var value = $(this).val();
	if (value === 'all')
	{
		value = '';
	}
	storeIntegrationsTable.search(value).draw();
});

$('#clear-filter-button').on('click', function()
{
	$('#search').val('');
	storeIntegrationsTable.search('').draw();
});
