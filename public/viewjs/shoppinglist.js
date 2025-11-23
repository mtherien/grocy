var shoppingListTable = $('#shoppinglist-table').DataTable({
	'order': [[1, 'asc']],
	"orderFixed": [[3, 'asc']],
	'columnDefs': [
		{ 'orderable': false, 'targets': 0 },
		{ 'searchable': false, "targets": 0 },
		{ 'visible': false, 'targets': 3 },
		{ 'visible': false, 'targets': 5 },
		{ 'visible': false, 'targets': 6 },
		{ 'visible': false, 'targets': 7 },
		{ 'visible': false, 'targets': 8 },
		{ "type": "custom-sort", "targets": 2 },
		{ "type": "html-num-fmt", "targets": 5 },
		{ "type": "html-num-fmt", "targets": 6 }
	].concat($.fn.dataTable.defaults.columnDefs),
	'rowGroup': {
		enable: true,
		dataSrc: 3
	}
});
$('#shoppinglist-table tbody').removeClass("d-none");
shoppingListTable.columns.adjust().draw();

// Track if any products were added during store search modal session
var storeProductsAdded = false;

var shoppingListPrintShadowTable = $('#shopping-list-print-shadow-table').DataTable({
	"orderFixed": [[0, 'asc'], [2, 'asc']],
	'columnDefs': [
		{ 'visible': false, 'targets': 2 },
		{ 'orderable': false, 'targets': '_all' }
	].concat($.fn.dataTable.defaults.columnDefs),
	'rowGroup': {
		enable: true,
		dataSrc: 2
	}
});
shoppingListPrintShadowTable.columns.adjust().draw();

$("#search").on("keyup", Delay(function()
{
	var value = $(this).val();
	if (value === "all")
	{
		value = "";
	}

	shoppingListTable.search(value).draw();
}, Grocy.FormFocusDelay));

$("#clear-filter-button").on("click", function()
{
	$("#search").val("");
	$("#status-filter").val("all");
	$("#search").trigger("keyup");
	$("#status-filter").trigger("change");
});

$("#status-filter").on("change", function()
{
	var value = $(this).val();
	if (value === "all")
	{
		value = "";
	}

	// Transfer CSS classes of selected element to dropdown element (for background)
	$(this).attr("class", $("#" + $(this).attr("id") + " option[value='" + value + "']").attr("class") + " form-control");

	shoppingListTable.column(shoppingListTable.colReorder.transpose(4)).search(value).draw();
});

$("#selected-shopping-list").on("change", function()
{
	var value = $(this).val();
	window.location.href = U('/shoppinglist?list=' + value);
});

$(".status-filter-message").on("click", function()
{
	var value = $(this).data("status-filter");
	$("#status-filter").val(value);
	$("#status-filter").trigger("change");
});

$("#delete-selected-shopping-list").on("click", function()
{
	var objectName = $("#selected-shopping-list option:selected").attr("data-shoppinglist-name");
	var objectId = $("#selected-shopping-list").val();

	bootbox.confirm({
		message: __t('Are you sure you want to delete shopping list "%s"?', objectName),
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
				Grocy.Api.Delete('objects/shopping_lists/' + objectId, {},
					function(result)
					{
						window.location.href = U('/shoppinglist');
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

$(document).on('click', '.shoppinglist-delete-button', function(e)
{
	e.preventDefault();

	var shoppingListItemId = $(e.currentTarget).attr('data-shoppinglist-id');
	Grocy.FrontendHelpers.BeginUiBusy();

	Grocy.Api.Delete('objects/shopping_list/' + shoppingListItemId, {},
		function(result)
		{
			animateCSS("#shoppinglistitem-" + shoppingListItemId + "-row", "fadeOut", function()
			{
				Grocy.FrontendHelpers.EndUiBusy();
				$("#shoppinglistitem-" + shoppingListItemId + "-row").addClass("d-none").remove();
				OnListItemRemoved();
			});
		},
		function(xhr)
		{
			Grocy.FrontendHelpers.EndUiBusy();
			console.error(xhr);
		}
	);
});

$(document).on('click', '#add-products-below-min-stock-amount', function(e)
{
	Grocy.Api.Post('stock/shoppinglist/add-missing-products', { "list_id": $("#selected-shopping-list").val() },
		function(result)
		{
			window.location.href = U('/shoppinglist?list=' + $("#selected-shopping-list").val());
		},
		function(xhr)
		{
			console.error(xhr);
		}
	);
});

$(document).on('click', '#add-overdue-expired-products', function(e)
{
	Grocy.Api.Post('stock/shoppinglist/add-overdue-products', { "list_id": $("#selected-shopping-list").val() },
		function(result)
		{
			Grocy.Api.Post('stock/shoppinglist/add-expired-products', { "list_id": $("#selected-shopping-list").val() },
				function(result)
				{
					window.location.href = U('/shoppinglist?list=' + $("#selected-shopping-list").val());
				},
				function(xhr)
				{
					console.error(xhr);
				}
			);
		},
		function(xhr)
		{
			console.error(xhr);
		}
	);
});

$(document).on('click', '#clear-shopping-list', function(e)
{
	var confirmMessage = __t('Are you sure you want to empty shopping list "%s"?', $("#selected-shopping-list option:selected").text());
	if (!BoolVal(Grocy.FeatureFlags.GROCY_FEATURE_FLAG_SHOPPINGLIST_MULTIPLE_LISTS))
	{
		confirmMessage = __t('Are you sure you want to empty the shopping list?');
	}

	bootbox.confirm({
		message: confirmMessage,
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

				Grocy.Api.Post('stock/shoppinglist/clear', { "list_id": $("#selected-shopping-list").val() },
					function(result)
					{
						window.location.reload();
					},
					function(xhr)
					{
						Grocy.FrontendHelpers.EndUiBusy();
						console.error(xhr);
					}
				);
			}
		}
	});
});

$(document).on("click", "#clear-done-items", function(e)
{
	Grocy.Api.Post('stock/shoppinglist/clear', { "list_id": $("#selected-shopping-list").val(), "done_only": true },
		function(result)
		{
			window.location.reload();
		},
		function(xhr)
		{
			console.error(xhr);
		}
	);
});

$(document).on('click', '.shopping-list-stock-add-workflow-list-item-button', function(e)
{
	e.preventDefault();

	var href = $(e.currentTarget).attr('href');

	$("#shopping-list-stock-add-workflow-purchase-form-frame").attr("src", href);
	$("#shopping-list-stock-add-workflow-modal").modal("show");

	if (Grocy.ShoppingListToStockWorkflowAll)
	{
		$("#shopping-list-stock-add-workflow-purchase-item-count").removeClass("d-none");
		$("#shopping-list-stock-add-workflow-purchase-item-count").text(__t("Adding shopping list item %1$s of %2$s", Grocy.ShoppingListToStockWorkflowCurrent, Grocy.ShoppingListToStockWorkflowCount));
		$("#shopping-list-stock-add-workflow-skip-button").removeClass("d-none");
	}
	else
	{
		$("#shopping-list-stock-add-workflow-purchase-item-count").addClass("d-none");
		$("#shopping-list-stock-add-workflow-skip-button").addClass("d-none");
	}
});

Grocy.ShoppingListToStockWorkflowAll = false;
Grocy.ShoppingListToStockWorkflowCount = 0;
Grocy.ShoppingListToStockWorkflowCurrent = 0;
Grocy.ShoppingListAddToStockButtonList = [];
$(document).on('click', '#add-all-items-to-stock-button', function(e)
{
	Grocy.ShoppingListToStockWorkflowAll = true;
	Grocy.ShoppingListAddToStockButtonList = $(".shopping-list-stock-add-workflow-list-item-button");
	Grocy.ShoppingListToStockWorkflowCount = Grocy.ShoppingListAddToStockButtonList.length;
	Grocy.ShoppingListToStockWorkflowCurrent++;
	$("#shopping-list-stock-add-workflow-modal .modal-footer").removeClass("d-none");
	$(".shopping-list-stock-add-workflow-list-item-button").first().click();
});

$("#shopping-list-stock-add-workflow-modal").on("hidden.bs.modal", function(e)
{
	Grocy.ShoppingListToStockWorkflowAll = false;
	Grocy.ShoppingListToStockWorkflowCount = 0;
	Grocy.ShoppingListToStockWorkflowCurrent = 0;
	Grocy.ShoppingListAddToStockButtonList = [];
	$("#shopping-list-stock-add-workflow-modal .modal-footer").addClass("d-none");
})

$(window).on("message", function(e)
{
	var data = e.originalEvent.data;

	if (data.Message === "AfterItemAdded")
	{
		$(".shoppinglist-delete-button[data-shoppinglist-id='" + data.Payload + "']").click();
	}
	else if (data.Message === "Ready")
	{
		if (!Grocy.ShoppingListToStockWorkflowAll)
		{
			$("#shopping-list-stock-add-workflow-modal").modal("hide");
		}
		else
		{
			Grocy.ShoppingListToStockWorkflowCurrent++;
			if (Grocy.ShoppingListToStockWorkflowCurrent <= Grocy.ShoppingListToStockWorkflowCount)
			{
				Grocy.ShoppingListAddToStockButtonList[Grocy.ShoppingListToStockWorkflowCurrent - 1].click();
			}
			else
			{
				$("#shopping-list-stock-add-workflow-modal").modal("hide");
			}
		}
	}
});

$(document).on('click', '#shopping-list-stock-add-workflow-skip-button', function(e)
{
	e.preventDefault();

	window.postMessage(WindowMessageBag("Ready"), Grocy.BaseUrl);
});

$(document).on('click', '.order-listitem-button', function(e)
{
	e.preventDefault();

	Grocy.FrontendHelpers.BeginUiBusy();

	var listItemId = $(e.currentTarget).attr('data-item-id');

	var done = 1;
	if ($(e.currentTarget).attr('data-item-done') == 1)
	{
		done = 0;
	}

	$(e.currentTarget).attr('data-item-done', done);

	Grocy.Api.Put('objects/shopping_list/' + listItemId, { 'done': done },
		function()
		{
			var statusInfoCell = $("#shoppinglistitem-" + listItemId + "-status-info");

			if (done == 1)
			{
				$('#shoppinglistitem-' + listItemId + '-row').addClass("text-muted");
				$('#shoppinglistitem-' + listItemId + '-row').addClass("text-strike-through");
				statusInfoCell.text(statusInfoCell.text().replace("xxUNDONExx", "xxDONExx"));
			}
			else
			{
				$('#shoppinglistitem-' + listItemId + '-row').removeClass("text-muted");
				$('#shoppinglistitem-' + listItemId + '-row').removeClass("text-strike-through");
				statusInfoCell.text(statusInfoCell.text().replace("xxDONExx", "xxUNDONExx"));
			}

			shoppingListTable.rows().invalidate().draw(false);
			$("#status-filter").trigger("change");

			Grocy.FrontendHelpers.EndUiBusy();
		},
		function(xhr)
		{
			Grocy.FrontendHelpers.EndUiBusy();
			console.error(xhr);
		}
	);
});

function OnListItemRemoved()
{
	if ($(".shopping-list-stock-add-workflow-list-item-button").length === 0)
	{
		$("#add-all-items-to-stock-button").addClass("disabled");
	}
}
OnListItemRemoved();

$(document).on("click", "#print-shopping-list-button", function(e)
{
	var checkedPrintShowHeader = "";
	if (BoolVal(Grocy.UserSettings.shopping_list_print_show_header))
	{
		checkedPrintShowHeader = "checked";
	}

	var checkedGroupByProductGroup = "";
	if (BoolVal(Grocy.UserSettings.shopping_list_print_group_by_product_group))
	{
		checkedGroupByProductGroup = "checked";
	}

	var checkedLayoutTypeTable = "";
	var checkedLayoutTypeList = "";
	if (Grocy.UserSettings.shopping_list_print_layout_type == "table")
	{
		checkedLayoutTypeTable = "checked";
		checkedLayoutTypeList = "";
	}
	else
	{
		checkedLayoutTypeTable = "";
		checkedLayoutTypeList = "checked";
	}

	var dialogHtml = ' \
	<div class="text-center"><h5>' + __t('Print options') + '</h5><hr></div> \
	<div class="custom-control custom-checkbox"> \
		<input id="print-show-header" \
			 ' + checkedPrintShowHeader + ' \
			class="form-check-input custom-control-input user-setting-control" \
			data-setting-key="shopping_list_print_show_header" \
			type="checkbox" \
			value="1"> \
		<label class="form-check-label custom-control-label" \
			for="print-show-header">' + __t('Show header') + ' \
		</label> \
	</div> \
	<div class="custom-control custom-checkbox"> \
		<input id="print-group-by-product-group" \
			 ' + checkedGroupByProductGroup + ' \
			class="form-check-input custom-control-input user-setting-control" \
			data-setting-key="shopping_list_print_group_by_product_group" \
			type="checkbox" \
			value="1"> \
		<label class="form-check-label custom-control-label" \
			for="print-group-by-product-group">' + __t('Group by product group') + ' \
		</label> \
	</div> \
	<h5 class="pt-3 pb-0">' + __t('Layout type') + '</h5> \
	<div class="custom-control custom-radio"> \
		<input id="print-layout-type-table" \
			' + checkedLayoutTypeTable + ' \
			class="custom-control-input user-setting-control" \
			data-setting-key="shopping_list_print_layout_type" \
			type="radio" \
			name="print-layout-type" \
			value="table"> \
		<label class="custom-control-label" \
			for="print-layout-type-table">' + __t('Table') + ' \
		</label> \
	</div> \
	<div class="custom-control custom-radio"> \
		<input id="print-layout-type-list" \
		' + checkedLayoutTypeList + ' \
			class="custom-control-input user-setting-control" \
			data-setting-key="shopping_list_print_layout_type" \
			type="radio" \
			name="print-layout-type" \
			value="list"> \
		<label class="custom-control-label" \
			for="print-layout-type-list">' + __t('List') + ' \
		</label> \
	</div>';

	var sizePrintDialog = 'medium';
	var printButtons = {
		cancel: {
			label: __t('Cancel'),
			className: 'btn-secondary',
			callback: function()
			{
				$(".modal").last().modal("hide");
			}
		},
		printtp: {
			label: __t('Thermal printer'),
			className: 'btn-secondary',
			callback: function()
			{
				$(".modal").last().modal("hide");
				var printHeader = $("#print-show-header").prop("checked");
				var thermalPrintDialog = bootbox.dialog({
					title: __t('Printing'),
					message: '<p><i class="fa fa-spin fa-spinner"></i> ' + __t('Connecting to printer...') + '</p>'
				});

				// Delaying for one second so that the alert can be closed
				setTimeout(function()
				{
					Grocy.Api.Get('print/shoppinglist/thermal?list=' + $("#selected-shopping-list").val() + '&printHeader=' + printHeader,
						function(result)
						{
							$(".modal").last().modal("hide");
						},
						function(xhr)
						{
							console.error(xhr);
							var validResponse = true;

							try
							{
								var jsonError = JSON.parse(xhr.responseText);
							}
							catch (e)
							{
								validResponse = false;
							}

							if (validResponse)
							{
								thermalPrintDialog.find('.bootbox-body').html(__t('Unable to print') + '<br><pre><code>' + jsonError.error_message + '</pre></code>');
							}
							else
							{
								thermalPrintDialog.find('.bootbox-body').html(__t('Unable to print') + '<br><pre><code>' + xhr.responseText + '</pre></code>');
							}
						}
					);
				}, 1000);
			}
		},
		ok: {
			label: __t('Print'),
			className: 'btn-primary responsive-button',
			callback: function()
			{
				$(".modal").last().modal("hide");
				$('.modal-backdrop').remove();
				$(".print-timestamp").text(moment().format("l LT"));

				$("#description-for-print").html($("#description").val());
				if (!$("#description").text())
				{
					$("#description-for-print").parent().addClass("d-print-none");
				}

				if (!$("#print-show-header").prop("checked"))
				{
					$("#print-header").addClass("d-none");
				}

				if (!$("#print-group-by-product-group").prop("checked"))
				{
					shoppingListPrintShadowTable.rowGroup().enable(false);
					shoppingListPrintShadowTable.draw();
				}

				$(".print-layout-container").addClass("d-none");
				$(".print-layout-type-" + $("input[name='print-layout-type']:checked").val()).removeClass("d-none");

				window.print();
			}
		}
	}

	if (!Grocy.FeatureFlags["GROCY_FEATURE_FLAG_THERMAL_PRINTER"])
	{
		delete printButtons['printtp'];
		sizePrintDialog = 'small';
	}

	bootbox.dialog({
		message: dialogHtml,
		size: sizePrintDialog,
		backdrop: true,
		closeButton: false,
		className: "d-print-none",
		buttons: printButtons
	});
});

$("#description").on("summernote.change", function()
{
	$("#save-description-button").removeClass("disabled");

	if ($("#description").summernote("isEmpty"))
	{
		$("#clear-description-button").addClass("disabled");
	}
	else
	{
		$("#clear-description-button").removeClass("disabled");
	}
});

$(document).on("click", "#save-description-button", function(e)
{
	e.preventDefault();

	Grocy.Api.Put('objects/shopping_lists/' + $("#selected-shopping-list").val(), { description: $("#description").val() },
		function(result)
		{
			$("#save-description-button").addClass("disabled");
		},
		function(xhr)
		{
			console.error(xhr);
		}
	);
});

$(document).on("click", "#clear-description-button", function(e)
{
	e.preventDefault();

	$("#description").summernote("reset");
	$("#save-description-button").click();
});

$("#description").trigger("summernote.change");
$("#save-description-button").addClass("disabled");

$(window).on("message", function(e)
{
	var data = e.originalEvent.data;

	if (data.Message === "ShoppingListChanged")
	{
		window.location.href = U('/shoppinglist?list=' + data.Payload);
	}
});

var dummyCanvas = document.createElement("canvas");
$("img.barcode").each(function()
{
	var img = $(this);
	var barcode = img.attr("data-barcode").replace(/\D/g, "");

	var barcodeType = "code128";
	if (barcode.length == 8)
	{
		barcodeType = "ean8";
	}
	else if (barcode.length == 13)
	{
		barcodeType = "ean13";
	}

	bwipjs.toCanvas(dummyCanvas, {
		bcid: barcodeType,
		text: barcode,
		height: 5,
		includetext: false
	});

	img.attr("src", dummyCanvas.toDataURL("image/png"));
});

if ($(window).width() < 768 || !Grocy.FeatureFlags.GROCY_FEATURE_FLAG_STOCK)
{
	$("#filter-container").removeClass("border-bottom");
}

// Send shopping list to store integration
$(document).on('click', '.send-to-store-button', function(e)
{
	e.preventDefault();

	var integrationId = $(e.currentTarget).attr('data-integration-id');
	var integrationName = $(e.currentTarget).attr('data-integration-name');
	var storeType = $(e.currentTarget).attr('data-store-type');
	var locationId = $(e.currentTarget).attr('data-location-id');
	var locationName = $(e.currentTarget).attr('data-location-name');
	var shoppingListId = $('#selected-shopping-list').val();

	var displayName = locationName ? locationName + ' (' + integrationName + ')' : integrationName;

	bootbox.confirm({
		message: __t('Send this shopping list to %s?', displayName),
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

				var requestData = {
					shopping_list_id: shoppingListId
				};

				if (locationId)
				{
					requestData.location_id = locationId;
				}

				Grocy.Api.Post('store-integrations/' + integrationId + '/send-shopping-list', requestData,
					function(result)
					{
						Grocy.FrontendHelpers.EndUiBusy();
						toastr.success(__t('Shopping list sent successfully to %s', displayName));
					},
					function(xhr)
					{
						Grocy.FrontendHelpers.EndUiBusy();
						console.error(xhr);
						Grocy.FrontendHelpers.ShowGenericError('Error while sending shopping list to store', xhr.response);
					}
				);
			}
		}
	});
});

// Store product search functionality
$('#store-search-button').on('click', function()
{
	var integrationId = $('#store-search-integration').val();
	var searchTerm = $('#store-search-term').val();
	var locationId = $('#store-search-integration option:selected').attr('data-location-id');

	if (!integrationId)
	{
		toastr.error(__t('Please select a store'));
		return;
	}

	if (!searchTerm || searchTerm.trim() === '')
	{
		toastr.error(__t('Please enter a search term'));
		return;
	}

	$('#store-search-results').addClass('d-none');
	$('#store-search-error').addClass('d-none');
	$('#store-search-loading').removeClass('d-none');

	var requestData = {
		search_term: searchTerm
	};

	if (locationId)
	{
		requestData.location_id = locationId;
	}

	Grocy.Api.Post('store-integrations/' + integrationId + '/products/search', requestData,
		function(result)
		{
			$('#store-search-loading').addClass('d-none');

			if (!result || result.length === 0)
			{
				$('#store-search-error').removeClass('d-none').text(__t('No products found'));
				return;
			}

			var resultsHtml = '';
			result.forEach(function(product)
			{
				var imageHtml = product.imageUrl ? '<img src="' + product.imageUrl + '" class="mr-2" style="width: 50px; height: 50px; object-fit: contain;">' : '<div class="mr-2" style="width: 50px; height: 50px;"></div>';
				var priceHtml = product.price ? '<span class="badge badge-success">' + product.price + '</span>' : '';
				var sizeHtml = product.size ? '<small class="text-muted">' + product.size + '</small>' : '';

				resultsHtml += '<a href="#" class="list-group-item list-group-item-action store-product-item" ' +
					'data-product-id="' + product.productId + '" ' +
					'data-product-name="' + product.name + '" ' +
					'data-product-brand="' + (product.brand || '') + '" ' +
					'data-product-upc="' + (product.upc || '') + '" ' +
					'data-image-url="' + (product.imageUrl || '') + '" ' +
					'data-description="' + (product.description || '') + '" ' +
					'data-price="' + (product.price || '') + '" ' +
					'data-size="' + (product.size || '') + '" ' +
					'data-aisle="' + (product.aisle || '') + '" ' +
					'data-shelf="' + (product.shelf || '') + '" ' +
					'data-department="' + (product.department || '') + '">' +
					'<div class="d-flex align-items-center">' +
					imageHtml +
					'<div class="flex-grow-1">' +
					'<div><strong>' + product.name + '</strong></div>' +
					'<div>' + (product.brand ? '<span class="text-muted">' + product.brand + '</span> ' : '') + sizeHtml + '</div>' +
					'</div>' +
					'<div class="ml-2">' + priceHtml + '</div>' +
					'</div>' +
					'</a>';
			});

			$('#store-search-results-list').html(resultsHtml);
			$('#store-search-results').removeClass('d-none');
		},
		function(xhr)
		{
			$('#store-search-loading').addClass('d-none');
			$('#store-search-error').removeClass('d-none').text(__t('Error searching products: ') + (xhr.responseJSON?.error_message || xhr.statusText));
		}
	);
});

// Handle search on Enter key
$('#store-search-term').on('keypress', function(e)
{
	if (e.which === 13)
	{
		e.preventDefault();
		$('#store-search-button').click();
	}
});

// Handle adding product from search results
$(document).on('click', '.store-product-item', function(e)
{
	e.preventDefault();

	// Mark that a product was added in this session
	storeProductsAdded = true;

	var productId = $(this).attr('data-product-id');
	var productName = $(this).attr('data-product-name');
	var productBrand = $(this).attr('data-product-brand');
	var productUpc = $(this).attr('data-product-upc');
	var imageUrl = $(this).attr('data-image-url');
	var description = $(this).attr('data-description');
	var price = $(this).attr('data-price');
	var size = $(this).attr('data-size');
	var aisle = $(this).attr('data-aisle');
	var shelf = $(this).attr('data-shelf');
	var department = $(this).attr('data-department');
	var shoppingListId = $('#selected-shopping-list').val();

	// First, create or find the Grocy product from the store data
	Grocy.Api.Post('store-integrations/products/create-from-store', {
		external_product_id: productId,
		name: productName,
		brand: productBrand,
		upc: productUpc,
		image_url: imageUrl,
		description: description,
		price: price,
		size: size,
		aisle: aisle,
		shelf: shelf,
		department: department
	},
		function(createResult)
		{
			// Now add the product to the shopping list
			Grocy.Api.Post('stock/shoppinglist/add-product', {
				product_id: createResult.product_id,
				list_id: shoppingListId,
				product_amount: 1
			},
				function(addResult)
				{
					// Function to reset the search UI
					var resetSearchUI = function()
					{
						toastr.success(__t('Product added to shopping list'));
						$('#store-search-term').val('');
						$('#store-search-results').addClass('d-none');
						$('#store-search-error').addClass('d-none');
						$('#store-search-term').focus();
					};

					// Function to add the item to the table
					var addItemToTable = function()
					{
						// Fetch the shopping list to get the newly added item
						Grocy.Api.Get('objects/shopping_list?query[]=shopping_list_id=' + shoppingListId + '&order=row_created_timestamp:desc&limit=1',
							function(items)
							{
								if (items && items.length > 0)
								{
									var item = items[0];
									// Add a simplified row to the DataTable
									var displayName = productBrand ? productBrand + ' - ' + productName : productName;

									// Determine the group name - use aisle if available, otherwise use product group or "Ungrouped"
									var groupName = '';
									if (aisle)
									{
										// Check if aisle name already contains "Aisle" (case-insensitive)
										var aisleDisplay = aisle.toLowerCase().includes('aisle') ? aisle : 'Aisle ' + aisle;

										if (department)
										{
											groupName = department + ' - ' + aisleDisplay;
										}
										else
										{
											groupName = aisleDisplay;
										}
									}
									else
									{
										groupName = '<span class="font-italic font-weight-light">' + __t('Ungrouped') + '</span>';
									}

									var newRowHtml = '<tr id="shoppinglistitem-' + item.id + '-row">' +
										'<td class="fit-content border-right">' +
										'<a class="btn btn-success btn-sm order-listitem-button" href="#" data-item-id="' + item.id + '" data-item-done="0"><i class="fa-solid fa-check"></i></a> ' +
										'<a class="btn btn-sm btn-info show-as-dialog-link" href="' + U('/shoppinglistitem/' + item.id + '?embedded&list=' + shoppingListId) + '"><i class="fa-solid fa-edit"></i></a> ' +
										'<a class="btn btn-sm btn-danger shoppinglist-delete-button" href="#" data-shoppinglist-id="' + item.id + '"><i class="fa-solid fa-trash"></i></a> ' +
										'<a class="btn btn-sm btn-primary shopping-list-stock-add-workflow-list-item-button" href="' + U('/purchase?embedded&flow=shoppinglistitemtostock&product=' + createResult.product_id + '&amount=1&listitemid=' + item.id + '&quId=' + item.qu_id) + '"><i class="fa-solid fa-box"></i></a>' +
										'</td>' +
										'<td class="productcard-trigger cursor-link" data-product-id="' + createResult.product_id + '">' + displayName + '</td>' +
										'<td><span class="locale-number locale-number-quantity-amount">1</span></td>' +
										'<td>' + groupName + '</td>' +
										'<td id="shoppinglistitem-' + item.id + '-status-info" class="d-none">xxUNDONExx</td>' +
										'<td class="d-none"></td>' +
										'<td class="d-none"></td>' +
										'<td class="d-none"></td>' +
										'<td></td>' +
										'</tr>';

									// Add to DataTable and redraw with grouping
									var $row = $(newRowHtml);
									var addedRow = shoppingListTable.row.add($row);

									// Invalidate and redraw to ensure grouping updates
									shoppingListTable.rows().invalidate().draw();

									// Update the shopping list count in the dropdown
									var currentCount = parseInt($('#selected-shopping-list option:selected').text().match(/\((\d+)\)/)[1] || 0);
									var newCount = currentCount + 1;
									var listName = $('#selected-shopping-list option:selected').text().replace(/\(\d+\)/, '(' + newCount + ')');
									$('#selected-shopping-list option:selected').text(listName);
								}
								resetSearchUI();
							},
							function(xhr)
							{
								// Even if we can't fetch the item, still reset the UI
								console.error('Could not fetch newly added item:', xhr);
								resetSearchUI();
							}
						);
					};

					// Save the selected store integration ID to the shopping list
					var selectedIntegrationId = $('#store-search-integration').val();
					if (selectedIntegrationId)
					{
						Grocy.Api.Put('objects/shopping_lists/' + shoppingListId, {
							store_integration_id: selectedIntegrationId
						},
							function()
							{
								// Successfully saved, now add item to table
								addItemToTable();
							},
							function()
							{
								// Even if saving store preference fails, still add item to table
								addItemToTable();
							}
						);
					}
					else
					{
						// No store selected, just add item to table
						addItemToTable();
					}
				},
				function(xhr)
				{
					console.error(xhr);
					Grocy.FrontendHelpers.ShowGenericError('Error while adding product to shopping list', xhr.response);
				}
			);
		},
		function(xhr)
		{
			console.error(xhr);
			Grocy.FrontendHelpers.ShowGenericError('Error while creating product', xhr.response);
		}
	);
});

// Pre-select the remembered store when the modal opens
$('#store-product-search-modal').on('shown.bs.modal', function()
{
	var preferredStoreIntegrationId = $(this).attr('data-preferred-store-integration-id');
	if (preferredStoreIntegrationId && preferredStoreIntegrationId !== 'null' && preferredStoreIntegrationId !== '')
	{
		$('#store-search-integration').val(preferredStoreIntegrationId);
	}
});

// Reset the search fields when modal opens
$('#store-product-search-modal').on('show.bs.modal', function()
{
	storeProductsAdded = false;
	$('#store-search-term').val('');
	$('#store-search-results').addClass('d-none');
	$('#store-search-error').addClass('d-none');
});
