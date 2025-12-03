/* Shop Mode JavaScript */

$(document).ready(function() {
	// Initialize shop mode
	Grocy.ShopMode.items = {};
	Grocy.ShopMode.checkedCount = 0;
	Grocy.ShopMode.scanQuantity = 1;
	Grocy.ShopMode.scannedProduct = null;

	// Load items if location is already selected
	if (Grocy.ShopMode.locationId) {
		loadShopItems();
	} else {
		showEmptyState(__t('Please select a store to begin shopping'));
	}

	// Location selector change
	$('#shop-location-selector').on('change', function() {
		var locationId = $(this).val();
		var newLocationId = parseInt(locationId);

		// Only reload if location actually changed
		if (newLocationId && newLocationId !== Grocy.ShopMode.locationId) {
			Grocy.ShopMode.locationId = newLocationId;
			loadShopItems();
		}
	});

	// Refresh metadata button
	$('#refresh-metadata-btn').on('click', function() {
		refreshMetadata();
	});

	// Scanner FAB button
	$('#scan-button-fab').on('click', function() {
		$('.barcodescanner-input').first().trigger('click');
	});

	// Barcode scan event
	$(document).on('Grocy.BarcodeScanned', function(e, barcode, targetElement) {
		handleBarcodeScan(barcode);
	});

	// Scan quantity controls
	$('#scan-qty-minus').on('click', function() {
		var qty = Grocy.ShopMode.scanQuantity;
		if (qty > 1) {
			Grocy.ShopMode.scanQuantity = qty - 1;
			$('#scan-quantity').text(Grocy.ShopMode.scanQuantity);
		}
	});

	$('#scan-qty-plus').on('click', function() {
		Grocy.ShopMode.scanQuantity++;
		$('#scan-quantity').text(Grocy.ShopMode.scanQuantity);
	});

	// Scan confirm button
	$('#scan-confirm-btn').on('click', function() {
		var product = Grocy.ShopMode.scannedProduct;
		var amount = Grocy.ShopMode.scanQuantity;

		if (!product) return;

		// Add scanned item to list
		addScannedToList(product.id, amount);
		$('#scan-result-modal').modal('hide');
	});

	// Send to inventory button
	$('#send-to-inventory-btn').on('click', function() {
		openBulkInventoryModal();
	});

	// Bulk inventory confirm button
	$('#bulk-confirm-btn').on('click', function() {
		bulkAddToInventory();
	});
});

function loadShopItems() {
	if (!Grocy.ShopMode.locationId) {
		showEmptyState(__t('Please select a store'));
		return;
	}

	// Show loading
	$('#shop-loading').removeClass('d-none');
	$('#shop-empty-state').addClass('d-none');
	$('#shop-items-container').children('.aisle-group, .not-in-store-group').remove();

	// Call API
	Grocy.Api.Get('shopping-list/' + Grocy.ShopMode.listId + '/shop-mode/items?location_id=' + Grocy.ShopMode.locationId,
		function(result) {
			Grocy.ShopMode.items = {};

			// Hide loading
			$('#shop-loading').addClass('d-none');

			// Check if empty
			if (result.in_store.length === 0 && result.not_in_store.length === 0) {
				showEmptyState(__t('No items on this shopping list'));
				return;
			}

			// Render in-store items (grouped by aisle)
			result.in_store.forEach(function(group) {
				renderAisleGroup(group);
			});

			// Render not-in-store items
			if (result.not_in_store.length > 0) {
				renderNotInStoreGroup(result.not_in_store);
			}

			// Update checked count
			updateCheckedCount();
		},
		function(xhr) {
			$('#shop-loading').addClass('d-none');
			toastr.error(__t('Error loading items') + ': ' + xhr.responseText);
		}
	);
}

function renderAisleGroup(group) {
	// Smart header formatting: ignore department if it contains "Aisle" (it's often wrong)
	var headerText;
	if (group.department) {
		var deptUpper = group.department.toUpperCase();
		if (deptUpper.includes('AISLE')) {
			// Department contains "AISLE" which is often incorrect, just use the actual aisle
			headerText = __t('Aisle') + ' ' + group.aisle;
		} else {
			// Valid department (like "DAIRY"), add aisle info
			headerText = group.department + ' - ' + __t('Aisle') + ' ' + group.aisle;
		}
	} else {
		headerText = __t('Aisle') + ' ' + group.aisle;
	}

	var html = '<div class="aisle-group">';
	html += '<div class="aisle-header">' + escapeHtml(headerText) + '</div>';

	group.items.forEach(function(item) {
		html += renderShopItem(item);
		Grocy.ShopMode.items[item.id] = item;
	});

	html += '</div>';

	$('#shop-items-container').append(html);
}

function renderNotInStoreGroup(items) {
	var html = '<div class="not-in-store-group">';
	html += '<div class="aisle-header not-in-store-header">' + __t('Not In Store') + '</div>';

	items.forEach(function(item) {
		html += renderShopItem(item);
		Grocy.ShopMode.items[item.id] = item;
	});

	html += '</div>';

	$('#shop-items-container').append(html);
}

function renderShopItem(item) {
	var doneClass = item.done ? 'done' : '';
	var checked = item.done ? 'checked' : '';

	var imageHtml = '';
	if (item.picture_file_name) {
		imageHtml = '<img src="' + U('/api/files/productpictures/' + btoa(item.picture_file_name) + '?force_serve_as=picture&best_fit_width=80&best_fit_height=80') + '" class="shop-item-image" alt="' + escapeHtml(item.product_name) + '">';
	} else {
		imageHtml = '<div class="shop-item-image-placeholder"><i class="fa-solid fa-image"></i></div>';
	}

	var locationText = '';
	if (item.aisle && item.shelf) {
		locationText = __t('Aisle') + ' ' + item.aisle + ', ' + __t('Shelf') + ' ' + item.shelf;
	} else if (item.aisle) {
		locationText = __t('Aisle') + ' ' + item.aisle;
	}

	var priceHtml = '';
	if (item.price) {
		priceHtml = '<div class="shop-item-price">$' + parseFloat(item.price).toFixed(2) + '</div>';
	}

	var html = '<div class="shop-item-card ' + doneClass + '" data-item-id="' + item.id + '">';
	html += '<input type="checkbox" class="shop-item-checkbox" ' + checked + '>';
	html += imageHtml;
	html += '<div class="shop-item-details">';
	html += '<div class="shop-item-name">' + escapeHtml(item.product_name) + '</div>';
	if (locationText) {
		html += '<div class="shop-item-location">' + locationText + '</div>';
	}
	html += priceHtml;
	html += '</div>';
	html += '<div class="shop-quantity-controls">';
	html += '<button class="shop-quantity-btn shop-qty-minus" data-item-id="' + item.id + '">-</button>';
	html += '<span class="shop-quantity-display" data-item-id="' + item.id + '">' + item.amount + '</span>';
	html += '<button class="shop-quantity-btn shop-qty-plus" data-item-id="' + item.id + '">+</button>';
	html += '</div>';
	html += '</div>';

	return html;
}

// Event delegation for dynamically created elements
$(document).on('change', '.shop-item-checkbox', function() {
	var $checkbox = $(this);
	var $card = $checkbox.closest('.shop-item-card');
	var itemId = $card.data('item-id');
	var done = $checkbox.is(':checked') ? 1 : 0;

	// Optimistic UI update
	if (done) {
		$card.addClass('done');
	} else {
		$card.removeClass('done');
	}

	// Update backend
	Grocy.Api.Put('objects/shopping_list/' + itemId, {done: done},
		function() {
			// Update local data
			if (Grocy.ShopMode.items[itemId]) {
				Grocy.ShopMode.items[itemId].done = done;
			}
			updateCheckedCount();
		},
		function(xhr) {
			// Rollback UI on error
			$checkbox.prop('checked', !done);
			if (done) {
				$card.removeClass('done');
			} else {
				$card.addClass('done');
			}
			toastr.error(__t('Error updating item'));
		}
	);
});

$(document).on('click', '.shop-qty-minus', function() {
	var itemId = $(this).data('item-id');
	var item = Grocy.ShopMode.items[itemId];
	if (!item) return;

	var newAmount = parseFloat(item.amount) - 1;
	if (newAmount < 0.1) newAmount = 0.1;

	updateItemQuantity(itemId, newAmount);
});

$(document).on('click', '.shop-qty-plus', function() {
	var itemId = $(this).data('item-id');
	var item = Grocy.ShopMode.items[itemId];
	if (!item) return;

	var newAmount = parseFloat(item.amount) + 1;
	updateItemQuantity(itemId, newAmount);
});

function updateItemQuantity(itemId, newAmount) {
	var $display = $('.shop-quantity-display[data-item-id="' + itemId + '"]');
	var oldAmount = $display.text();

	// Optimistic UI update
	$display.text(newAmount);

	// Update backend
	Grocy.Api.Put('objects/shopping_list/' + itemId, {amount: newAmount},
		function() {
			// Update local data
			if (Grocy.ShopMode.items[itemId]) {
				Grocy.ShopMode.items[itemId].amount = newAmount;
			}
		},
		function(xhr) {
			// Rollback UI on error
			$display.text(oldAmount);
			toastr.error(__t('Error updating quantity'));
		}
	);
}

function updateCheckedCount() {
	var count = 0;
	for (var id in Grocy.ShopMode.items) {
		if (Grocy.ShopMode.items[id].done) {
			count++;
		}
	}

	Grocy.ShopMode.checkedCount = count;
	$('#checked-count').text(count);

	// Enable/disable send to inventory button
	if (count > 0) {
		$('#send-to-inventory-btn').prop('disabled', false);
	} else {
		$('#send-to-inventory-btn').prop('disabled', true);
	}
}

function handleBarcodeScan(barcode) {
	// Call API to handle scan
	Grocy.Api.Post('shopping-list/' + Grocy.ShopMode.listId + '/shop-mode/scan', {barcode: barcode},
		function(result) {
			if (!result.found) {
				// Barcode not recognized
				toastr.error(__t('Product not found'));
				return;
			}

			if (result.on_list) {
				// Item already on list - marked as done
				toastr.success(__t('Marked as done') + ': ' + result.item.product_name);

				// Update UI
				var $card = $('.shop-item-card[data-item-id="' + result.item.id + '"]');
				$card.addClass('done');
				$card.find('.shop-item-checkbox').prop('checked', true);

				// Update local data
				if (Grocy.ShopMode.items[result.item.id]) {
					Grocy.ShopMode.items[result.item.id].done = 1;
				}

				updateCheckedCount();
			} else {
				// Item not on list - show confirmation modal
				showScanConfirmationModal(result.product);
			}
		},
		function(xhr) {
			toastr.error(__t('Error scanning barcode'));
		}
	);
}

function showScanConfirmationModal(product) {
	Grocy.ShopMode.scannedProduct = product;
	Grocy.ShopMode.scanQuantity = 1;

	$('#scan-result-name').text(product.name);
	$('#scan-quantity').text(1);

	if (product.picture_file_name) {
		$('#scan-result-image').attr('src', U('/api/files/productpictures/' + btoa(product.picture_file_name) + '?force_serve_as=picture&best_fit_width=200&best_fit_height=200')).show();
	} else {
		$('#scan-result-image').hide();
	}

	$('#scan-result-modal').modal('show');
}

function addScannedToList(productId, amount) {
	Grocy.Api.Post('shopping-list/' + Grocy.ShopMode.listId + '/shop-mode/add-scanned',
		{product_id: productId, amount: amount},
		function(result) {
			toastr.success(__t('Item added to list'));
			// Reload items to show the new item
			loadShopItems();
		},
		function(xhr) {
			toastr.error(__t('Error adding item'));
		}
	);
}

function refreshMetadata() {
	if (!Grocy.ShopMode.locationId) {
		toastr.warning(__t('Please select a store first'));
		return;
	}

	var locationId = Grocy.ShopMode.locationId;
	var $location = $('#shop-location-selector option:selected');
	var integrationId = $location.data('integration-id');

	if (!integrationId) {
		toastr.error(__t('Selected location does not have integration'));
		return;
	}

	var $btn = $('#refresh-metadata-btn');
	$btn.prop('disabled', true);
	$btn.find('i').addClass('fa-spin');

	Grocy.Api.Post('shopping-list/' + Grocy.ShopMode.listId + '/shop-mode/start',
		{shopping_location_id: locationId},
		function(result) {
			$btn.prop('disabled', false);
			$btn.find('i').removeClass('fa-spin');

			var summary = result.metadata_results;
			var message = '';

			if (summary.succeeded > 0) {
				message = summary.succeeded + ' ' + __t('items fetched');
			}

			if (summary.skipped > 0) {
				if (message) message += ', ';
				message += summary.skipped + ' ' + __t('already had data');
			}

			if (summary.failed > 0) {
				if (message) message += ', ';
				message += summary.failed + ' ' + __t('not found');
				toastr.warning(__t('Metadata refreshed') + ': ' + message);
			} else if (summary.succeeded > 0 || summary.skipped > 0) {
				toastr.success(__t('Metadata refreshed') + ': ' + message);
			} else {
				toastr.info(__t('All items already have metadata'));
			}

			// Reload items
			loadShopItems();
		},
		function(xhr) {
			$btn.prop('disabled', false);
			$btn.find('i').removeClass('fa-spin');
			toastr.error(__t('Error refreshing metadata'));
		}
	);
}

function openBulkInventoryModal() {
	// Get all checked items
	var checkedItems = [];
	for (var id in Grocy.ShopMode.items) {
		if (Grocy.ShopMode.items[id].done) {
			checkedItems.push(Grocy.ShopMode.items[id]);
		}
	}

	if (checkedItems.length === 0) {
		toastr.warning(__t('No items checked'));
		return;
	}

	// Update count
	$('#bulk-items-count').text(checkedItems.length);

	// Render items list
	var html = '<div class="list-group">';
	checkedItems.forEach(function(item) {
		html += '<div class="list-group-item">';
		html += '<div class="d-flex align-items-center">';

		if (item.picture_file_name) {
			html += '<img src="' + U('/api/files/productpictures/' + btoa(item.picture_file_name) + '?force_serve_as=picture&best_fit_width=80&best_fit_height=80') + '" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px; margin-right: 10px;">';
		}

		html += '<div>';
		html += '<div>' + escapeHtml(item.product_name) + '</div>';
		html += '<small class="text-muted">' + item.amount + ' ' + (item.qu_name || '') + '</small>';
		html += '</div>';
		html += '</div>';
		html += '</div>';
	});
	html += '</div>';

	$('#bulk-items-list').html(html);

	$('#bulk-inventory-modal').modal('show');
}

function bulkAddToInventory() {
	var locationId = $('#bulk-location-id').val();
	var purchasedDate = $('#bulk-purchased-date').val();
	var bestBeforeDate = $('#bulk-best-before-date').val() || null;

	if (!locationId) {
		toastr.error(__t('Please select a location'));
		return;
	}

	// Show loading
	$('#bulk-confirm-btn').prop('disabled', true);
	$('#bulk-confirm-text').addClass('d-none');
	$('#bulk-confirm-spinner').removeClass('d-none');

	Grocy.Api.Post('shopping-list/' + Grocy.ShopMode.listId + '/shop-mode/bulk-add-to-inventory',
		{
			location_id: parseInt(locationId),
			purchased_date: purchasedDate,
			best_before_date: bestBeforeDate
		},
		function(result) {
			$('#bulk-confirm-btn').prop('disabled', false);
			$('#bulk-confirm-text').removeClass('d-none');
			$('#bulk-confirm-spinner').addClass('d-none');

			var summary = result.summary;
			toastr.success(__t('%s of %s items added to inventory', summary.succeeded, summary.total));

			$('#bulk-inventory-modal').modal('hide');

			// Reload items
			loadShopItems();
		},
		function(xhr) {
			$('#bulk-confirm-btn').prop('disabled', false);
			$('#bulk-confirm-text').removeClass('d-none');
			$('#bulk-confirm-spinner').addClass('d-none');

			toastr.error(__t('Error adding to inventory'));
		}
	);
}

function showEmptyState(message) {
	$('#shop-loading').addClass('d-none');
	$('#shop-empty-state').removeClass('d-none');
	$('#shop-empty-state h3').text(message || __t('No items'));
}

function escapeHtml(text) {
	var map = {
		'&': '&amp;',
		'<': '&lt;',
		'>': '&gt;',
		'"': '&quot;',
		"'": '&#039;'
	};
	return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}
