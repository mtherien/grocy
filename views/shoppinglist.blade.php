@php require_frontend_packages(['datatables', 'summernote', 'animatecss', 'bwipjs']); @endphp

@extends('layout.default')

@section('title', $__t('Shopping list'))

@push('pageStyles')
<style>
	#shopping-list-print-shadow-table_wrapper .dataTable>thead>tr>th[class*="sort"]:before,
	#shopping-list-print-shadow-table_wrapper .dataTable>thead>tr>th[class*="sort"]:after {
		content: "" !important;
	}
</style>
@endpush

@push('pageScripts')
<script src="{{ $U('/viewjs/purchase.js?v=', true) }}{{ $version }}"></script>
@endpush

@php
if(boolval($userSettings['shopping_list_round_up']))
{
foreach($listItems as $listItem)
{
$listItem->amount = ceil($listItem->amount);
$listItem->last_price_total = $listItem->price * $listItem->amount;
}
}
@endphp

@section('content')
<div class="row d-print-none hide-on-fullscreen-card">
	<div class="col">
		<div class="title-related-links">
			<h2 class="title mr-2 order-0">
				@yield('title')
			</h2>
			@if(GROCY_FEATURE_FLAG_STOCK_PRICE_TRACKING)
			<h2 class="mb-0 mr-auto order-3 order-md-1 width-xs-sm-100">
				<span class="text-muted small">{!! $__t('%s total value', '<span class="locale-number locale-number-currency">' . SumArrayValue($listItems, 'last_price_total') . '</span>') !!}</span>
			</h2>
			@endif
			<div class="float-right @if($embedded) pr-5 @endif">
				<button class="btn btn-primary responsive-button d-md-none mt-2 order-1 order-md-3 show-as-dialog-link"
					href="{{ $U('/shoppinglistitem/new?embedded&list=' . $selectedShoppingListId) }}">
					{{ $__t('Add item') }}
				</button>
				<button class="btn btn-outline-dark d-md-none mt-2 order-1 order-md-3"
					type="button"
					data-toggle="collapse"
					data-target="#table-filter-row">
					<i class="fa-solid fa-filter"></i>
				</button>
				<button class="btn btn-outline-dark d-md-none mt-2 order-1 order-md-3"
					type="button"
					data-toggle="collapse"
					data-target="#related-links">
					<i class="fa-solid fa-ellipsis-v"></i>
				</button>
			</div>
			<div class="related-links collapse d-md-flex order-2 width-xs-sm-100"
				id="related-links">
				@if(GROCY_FEATURE_FLAG_SHOPPINGLIST_MULTIPLE_LISTS)
				<div class="my-auto float-right">
					<select class="custom-control custom-select bg-light font-weight-bold mt-md-0 mt-1"
						id="selected-shopping-list">
						@foreach($shoppingLists as $shoppingList)
						<option @if($shoppingList->id == $selectedShoppingListId) selected="selected" @endif value="{{ $shoppingList->id }}" data-shoppinglist-name="{{ $shoppingList->name }}">{{ $shoppingList->name }} ({{ $shoppingList->item_count }})</option>
						@endforeach
					</select>
				</div>
				<div class="dropdown">
					<a class="btn btn-outline-dark responsive-button m-1 mt-md-0 mb-md-0 float-right dropdown-toggle"
						href="#"
						data-toggle="dropdown">
						{{ $__t('List actions') }}
					</a>
					<div class="dropdown-menu">
						<a class="dropdown-item show-as-dialog-link"
							href="{{ $U('/shoppinglist/new?embedded') }}">
							{{ $__t('New shopping list') }}
						</a>
						<a class="dropdown-item show-as-dialog-link"
							href="{{ $U('/shoppinglist/' . $selectedShoppingListId . '?embedded') }}">
							{{ $__t('Edit shopping list') }}
						</a>
						<a id="delete-selected-shopping-list"
							class="dropdown-item text-danger @if($selectedShoppingListId == 1) disabled text-muted @endif"
							href="#">
							{{ $__t('Delete shopping list') }}
						</a>
						<div class="dropdown-divider"></div>
						<a id="print-shopping-list-button"
							class="dropdown-item"
							href="#">
							{{ $__t('Print') }}
						</a>
						@if(defined('GROCY_FEATURE_FLAG_STORE_INTEGRATIONS') && GROCY_FEATURE_FLAG_STORE_INTEGRATIONS && !empty($storeIntegrations) && iterator_to_array($storeIntegrations))
						<div class="dropdown-divider"></div>
						<a id="start-shop-mode-button"
							class="dropdown-item @if($listItems->count() == 0) disabled @endif"
							href="#">
							<i class="fa-solid fa-shopping-cart"></i> {{ $__t('Shop List') }}
						</a>
						@endif
						@if(defined('GROCY_FEATURE_FLAG_STORE_INTEGRATIONS') && GROCY_FEATURE_FLAG_STORE_INTEGRATIONS && !empty($storeIntegrations) && iterator_to_array($storeIntegrations))
						<div class="dropdown-divider"></div>
						<h6 class="dropdown-header">{{ $__t('Add To Cart') }}</h6>
						@foreach($storeIntegrations as $integration)
							@php
								$integrationLocations = array_filter($storeLocations, function($loc) use ($integration) {
									return $loc->store_integration_id == $integration->id;
								});
							@endphp
							@if(count($integrationLocations) > 0)
								<h6 class="dropdown-header pl-4"><small>{{ $integration->name }}</small></h6>
								@foreach($integrationLocations as $location)
								<a class="dropdown-item send-to-store-button pl-5 @if($listItems->count() == 0) disabled @endif"
									href="#"
									data-integration-id="{{ $integration->id }}"
									data-integration-name="{{ $integration->name }}"
									data-store-type="{{ $integration->store_type }}"
									data-location-id="{{ $location->id }}"
									data-location-name="{{ $location->name }}">
									{{ $location->name }}@if($location->is_primary) <span class="badge badge-primary ml-1">{{ $__t('Primary') }}</span>@endif
									@if($location->city), {{ $location->city }}@endif
								</a>
								@endforeach
							@else
								<a class="dropdown-item send-to-store-button @if($listItems->count() == 0) disabled @endif"
									href="#"
									data-integration-id="{{ $integration->id }}"
									data-integration-name="{{ $integration->name }}"
									data-store-type="{{ $integration->store_type }}">
									{{ $integration->name }} ({{ ucfirst($integration->store_type) }})
								</a>
							@endif
						@endforeach
						<div class="dropdown-divider"></div>
						<a class="dropdown-item"
							href="{{ $U('/storeintegrations') }}">
							{{ $__t('Manage integrations') }}
						</a>
						@endif
					</div>
				</div>
				@else
				<input type="hidden"
					name="selected-shopping-list"
					id="selected-shopping-list"
					value="1">
				@endif
			</div>
		</div>
		<div id="filter-container"
			class="border-top border-bottom my-2 py-1">
			<div id="table-filter-row"
				data-status-filter="belowminstockamount"
				class="collapse normal-message status-filter-message responsive-button @if(!GROCY_FEATURE_FLAG_STOCK) d-none @else d-md-inline-block @endif"><span class="d-block d-md-none">{{count($missingProducts)}} <i class="fa-solid fa-exclamation-circle"></i></span><span class="d-none d-md-block">{{ $__n(count($missingProducts), '%s product is below defined min. stock amount', '%s products are below defined min. stock amount') }}</span></div>
			<div id="related-links"
				class="float-right mt-1 collapse d-md-block">
				<a class="btn btn-primary responsive-button btn-sm mb-1 show-as-dialog-link d-none d-md-inline-block"
					href="{{ $U('/shoppinglistitem/new?embedded&list=' . $selectedShoppingListId) }}">
					{{ $__t('Add item') }}
				</a>
				@if(defined('GROCY_FEATURE_FLAG_STORE_INTEGRATIONS') && GROCY_FEATURE_FLAG_STORE_INTEGRATIONS && !empty($storeIntegrations) && iterator_to_array($storeIntegrations))
				<button class="btn btn-outline-primary responsive-button btn-sm mb-1 d-none d-md-inline-block"
					id="add-from-store-button"
					data-toggle="modal"
					data-target="#store-product-search-modal">
					{{ $__t('Add from store') }}
				</button>
				@endif
				<div class="btn-group">
					<a id="clear-shopping-list"
						class="btn btn-outline-danger btn-sm mb-1 responsive-button @if($listItems->count() == 0) disabled @endif"
						href="#">
						{{ $__t('Clear list') }}
					</a>
					<a id="clear-done-items"
						class="btn btn-outline-danger btn-sm mb-1 responsive-button @if($listItems->count() == 0) disabled @endif"
						href="#">
						{{ $__t('Clear done items') }}
					</a>
				</div>

				@if(GROCY_FEATURE_FLAG_STOCK)
				<div class="dropdown d-inline">
					<a class="btn btn-sm btn-outline-dark responsive-button mb-1 dropdown-toggle"
						href="#"
						data-toggle="dropdown">
						{{ $__t('Stock actions') }}
					</a>
					<div class="dropdown-menu text-right">
						<a id="add-all-items-to-stock-button"
							class="dropdown-item"
							href="#">{{ $__t('Add all list items to stock') }}</a>
						@if(!boolval($userSettings['shopping_list_auto_add_below_min_stock_amount']))
						<a id="add-products-below-min-stock-amount"
							class="dropdown-item"
							href="#">{{ $__t('Add products that are below defined min. stock amount') }}</a>
						@endif
						<a id="add-overdue-expired-products"
							class="dropdown-item"
							href="#">{{ $__t('Add overdue/expired products') }}</a>
					</div>
				</div>
				@endif
			</div>
		</div>
	</div>
</div>

<div class="row collapse d-md-flex d-print-none hide-on-fullscreen-card"
	id="table-filter-row">
	<div class="col-12 col-md-6 col-xl-3">
		<div class="input-group">
			<div class="input-group-prepend">
				<span class="input-group-text"><i class="fa-solid fa-search"></i></span>
			</div>
			<input type="text"
				id="search"
				class="form-control"
				placeholder="{{ $__t('Search') }}">
		</div>
	</div>
	<div class="col-12 col-md-6 col-xl-3">
		<div class="input-group">
			<div class="input-group-prepend">
				<span class="input-group-text"><i class="fa-solid fa-filter"></i>&nbsp;{{ $__t('Status') }}</span>
			</div>
			<select class="custom-control custom-select"
				id="status-filter">
				<option value="all">{{ $__t('All') }}</option>
				<option class="@if(!GROCY_FEATURE_FLAG_STOCK) d-none @endif"
					value="belowminstockamount">{{ $__t('Below min. stock amount') }}</option>
				<option value="xxDONExx">{{ $__t('Only done items') }}</option>
				<option value="xxUNDONExx">{{ $__t('Only undone items') }}</option>
			</select>
		</div>
	</div>
	<div class="col">
		<div class="float-right">
			<button id="clear-filter-button"
				class="btn btn-sm btn-outline-info"
				data-toggle="tooltip"
				title="{{ $__t('Clear filter') }}">
				<i class="fa-solid fa-filter-circle-xmark"></i>
			</button>
		</div>
	</div>
</div>

<div id="shoppinglist-main"
	class="row d-print-none">
	<div class="@if(boolval($userSettings['shopping_list_show_calendar'])) col-12 col-md-8 @else col-12 @endif pb-3">
		<table id="shoppinglist-table"
			class="table table-sm table-striped nowrap w-100">
			<thead>
				<tr>
					<th class="border-right"><a class="text-muted change-table-columns-visibility-button"
							data-toggle="tooltip"
							title="{{ $__t('Table options') }}"
							data-table-selector="#shoppinglist-table"
							href="#"><i class="fa-solid fa-eye"></i></a>
					</th>
					<th class="allow-grouping">{{ $__t('Product') }} / <em>{{ $__t('Note') }}</em></th>
					<th>{{ $__t('Amount') }}</th>
					<th class="allow-grouping">{{ $__t('Product group') }}</th>
					<th class="d-none">Hidden status</th>
					<th class="@if(!GROCY_FEATURE_FLAG_STOCK_PRICE_TRACKING) d-none @endif">{{ $__t('Last price (Unit)') }}</th>
					<th class="@if(!GROCY_FEATURE_FLAG_STOCK_PRICE_TRACKING) d-none @endif">{{ $__t('Last price (Total)') }}</th>
					<th class="@if(!GROCY_FEATURE_FLAG_STOCK_PRICE_TRACKING) d-none @endif allow-grouping">{{ $__t('Default store') }}</th>
					<th>{{ $__t('Barcodes') }}</th>

					@include('components.userfields_thead', array(
					'userfields' => $userfields
					))
					@include('components.userfields_thead', array(
					'userfields' => $productUserfields
					))

				</tr>
			</thead>
			<tbody class="d-none">
				@foreach($listItems as $listItem)
				<tr id="shoppinglistitem-{{ $listItem->id }}-row"
					class="@if(FindObjectInArrayByPropertyValue($missingProducts, 'id', $listItem->product_id) !== null) table-info @endif @if($listItem->done == 1) text-muted text-strike-through @endif">
					<td class="fit-content border-right">
						<a class="btn btn-success btn-sm order-listitem-button"
							href="#"
							data-item-id="{{ $listItem->id }}"
							data-item-done="{{ $listItem->done }}"
							data-toggle="tooltip"
							data-placement="right"
							title="{{ $__t('Mark this item as done') }}">
							<i class="fa-solid fa-check"></i>
						</a>
						<a class="btn btn-sm btn-info show-as-dialog-link"
							href="{{ $U('/shoppinglistitem/' . $listItem->id . '?embedded&list=' . $selectedShoppingListId ) }}"
							data-toggle="tooltip"
							data-placement="right"
							title="{{ $__t('Edit this item') }}">
							<i class="fa-solid fa-edit"></i>
						</a>
						<a class="btn btn-sm btn-danger shoppinglist-delete-button"
							href="#"
							data-shoppinglist-id="{{ $listItem->id }}"
							data-toggle="tooltip"
							data-placement="right"
							title="{{ $__t('Delete this item') }}">
							<i class="fa-solid fa-trash"></i>
						</a>
						<a class="btn btn-sm btn-primary @if(!GROCY_FEATURE_FLAG_STOCK) d-none @endif @if(empty($listItem->product_id)) disabled @else shopping-list-stock-add-workflow-list-item-button @endif"
							href="{{ $U('/purchase?embedded&flow=shoppinglistitemtostock&product=') }}{{ $listItem->product_id }}&amount={{ $listItem->amount }}&listitemid={{ $listItem->id }}&quId={{ $listItem->qu_id }}"
							@if(!empty($listItem->product_id)) data-toggle="tooltip" title="{{ $__t('Add this item to stock') }}" @endif>
							<i class="fa-solid fa-box"></i>
						</a>
					</td>
					<td class="productcard-trigger cursor-link"
						data-product-id="{{ $listItem->product_id }}">
						@if(!empty($listItem->product_id)) {{ $listItem->product_name }}<br>@endif<em>{!! nl2br($listItem->note ?? '') !!}</em>
					</td>
					@if(!empty($listItem->product_id))
					@php
					$listItem->amount_origin_qu = $listItem->amount;
					$product = FindObjectInArrayByPropertyValue($products, 'id', $listItem->product_id);
					$productQuConversions = FindAllObjectsInArrayByPropertyValue($quantityUnitConversionsResolved, 'product_id', $product->id);
					$productQuConversions = FindAllObjectsInArrayByPropertyValue($productQuConversions, 'from_qu_id', $product->qu_id_stock);
					$productQuConversion = FindObjectInArrayByPropertyValue($productQuConversions, 'to_qu_id', $listItem->qu_id);
					if ($productQuConversion)
					{
					$listItem->amount = $listItem->amount * $productQuConversion->factor;
					}
					@endphp
					@endif
					<td>
						<span class="custom-sort d-none">{{$listItem->amount}}</span>
						<span class="locale-number locale-number-quantity-amount">{{ $listItem->amount }}</span> @if(!empty($listItem->product_id)){{ $__n($listItem->amount, $listItem->qu_name, $listItem->qu_name_plural, true) }}@endif
					</td>
					<td>
						@if(!empty($listItem->product_group_name)) {{ $listItem->product_group_name }} @else <span class="font-italic font-weight-light">{{ $__t('Ungrouped') }}</span> @endif
					</td>
					<td id="shoppinglistitem-{{ $listItem->id }}-status-info"
						class="d-none">
						@if(FindObjectInArrayByPropertyValue($missingProducts, 'id', $listItem->product_id) !== null) belowminstockamount @endif
						@if($listItem->done == 1) xxDONExx @else xxUNDONExx @endif
					</td>
					<td class="@if(!GROCY_FEATURE_FLAG_STOCK_PRICE_TRACKING) d-none @endif">
						<span class="locale-number locale-number-currency">{{ $listItem->last_price_unit }}</span>
					</td>
					<td class="@if(!GROCY_FEATURE_FLAG_STOCK_PRICE_TRACKING) d-none @endif">
						<span class="locale-number locale-number-currency">{{ $listItem->last_price_total }}</span>
					</td>
					<td class="@if(!GROCY_FEATURE_FLAG_STOCK_PRICE_TRACKING) d-none @endif">
						{{ $listItem->default_shopping_location_name }}
					</td>
					<td>
						@if($listItem->product_barcodes != null)
						@foreach(explode(',', $listItem->product_barcodes) as $barcode)
						@if(!empty($barcode))
						<img class="barcode img-fluid pr-2"
							data-barcode="{{ $barcode }}">
						@endif
						@endforeach
						@endif
					</td>

					@include('components.userfields_tbody', array(
					'userfields' => $userfields,
					'userfieldValues' => FindAllObjectsInArrayByPropertyValue($userfieldValues, 'object_id', $listItem->id)
					))
					@include('components.userfields_tbody', array(
					'userfields' => $productUserfields,
					'userfieldValues' => FindAllObjectsInArrayByPropertyValue($productUserfieldValues, 'object_id', $listItem->product_id)
					))

				</tr>
				@endforeach
			</tbody>
		</table>
	</div>

	@if(boolval($userSettings['shopping_list_show_calendar']))
	<div class="col-12 col-md-4 mt-md-2 d-print-none">
		@include('components.calendarcard')
	</div>
	@endif

	<div class="@if(boolval($userSettings['shopping_list_show_calendar'])) col-12 col-md-8 @else col-12 @endif d-print-none pt-2">
		<div class="form-group">
			<label class="text-larger font-weight-bold"
				for="notes">{{ $__t('Notes') }}</label>
			<a id="save-description-button"
				class="btn btn-success btn-sm ml-1 mb-2"
				href="#">{{ $__t('Save') }}</a>
			<a id="clear-description-button"
				class="btn btn-danger btn-sm ml-1 mb-2"
				href="#">{{ $__t('Clear') }}</a>
			<textarea class="form-control wysiwyg-editor"
				id="description"
				name="description">{{ FindObjectInArrayByPropertyValue($shoppingLists, 'id', $selectedShoppingListId)->description }}</textarea>
		</div>
	</div>
</div>

<div class="modal fade"
	id="shopping-list-stock-add-workflow-modal"
	tabindex="-1">
	<div class="modal-dialog">
		<div class="modal-content text-center">
			<div class="modal-body">
				<iframe id="shopping-list-stock-add-workflow-purchase-form-frame"
					class="embed-responsive">
				</iframe>
			</div>
			<div class="modal-footer d-none">
				<span id="shopping-list-stock-add-workflow-purchase-item-count"
					class="d-none mr-auto"></span>
				<button id="shopping-list-stock-add-workflow-skip-button"
					type="button"
					class="btn btn-primary">{{ $__t('Skip') }}</button>
			</div>
		</div>
	</div>
</div>

<div class="d-none d-print-block">
	<div id="print-header">
		<h1 class="text-center">
			<img src="{{ $U('/img/logo.svg?v=', true) }}{{ $version }}"
				width="114"
				height="30"
				class="d-print-flex mx-auto">
			{{ $__t("Shopping list") }}
		</h1>
		@if (FindObjectInArrayByPropertyValue($shoppingLists, 'id', $selectedShoppingListId)->name != $__t("Shopping list"))
		<h3 class="text-center">
			{{ FindObjectInArrayByPropertyValue($shoppingLists, 'id', $selectedShoppingListId)->name }}
		</h3>
		@endif
		<h6 class="text-center mb-4">
			{{ $__t('Time of printing') }}:
			<span class="d-inline print-timestamp"></span>
		</h6>
	</div>
	<div class="w-75 print-layout-container print-layout-type-table d-none">
		<div>
			<table id="shopping-list-print-shadow-table"
				class="table table-sm table-striped nowrap">
				<thead>
					<tr>
						<th>{{ $__t('Product') }} / <em>{{ $__t('Note') }}</em></th>
						<th>{{ $__t('Amount') }}</th>
						<th>{{ $__t('Product group') }}</th>

						@include('components.userfields_thead', array(
						'userfields' => $userfields
						))
						@include('components.userfields_thead', array(
						'userfields' => $productUserfields
						))
						@include('components.userfields_thead', array(
						'userfields' => $productGroupUserfields
						))
					</tr>
				</thead>
				<tbody>
					@foreach($listItems as $listItem)
					<tr>
						<td>
							@if(!empty($listItem->product_id)) {{ $listItem->product_name }}<br>@endif<em>{!! nl2br($listItem->note ?? '') !!}</em>
						</td>
						<td>
							<span class="locale-number locale-number-quantity-amount">{{ $listItem->amount }}</span> @if(!empty($listItem->product_id)){{ $__n($listItem->amount, $listItem->qu_name, $listItem->qu_name_plural, true) }}@endif
						</td>
						<td>
							@if(!empty($listItem->product_group_name)) {{ $listItem->product_group_name }} @else <span class="font-italic font-weight-light">{{ $__t('Ungrouped') }}</span> @endif
						</td>

						@include('components.userfields_tbody', array(
						'userfields' => $userfields,
						'userfieldValues' => FindAllObjectsInArrayByPropertyValue($userfieldValues, 'object_id', $listItem->id)
						))
						@include('components.userfields_tbody', array(
						'userfields' => $productUserfields,
						'userfieldValues' => FindAllObjectsInArrayByPropertyValue($productUserfieldValues, 'object_id', $listItem->product_id)
						))
						@include('components.userfields_tbody', array(
						'userfields' => $productGroupUserfields,
						'userfieldValues' => FindAllObjectsInArrayByPropertyValue($productGroupUserfieldValues, 'object_id', $listItem->product_group_id)
						))

					</tr>
					@endforeach
				</tbody>
			</table>
		</div>
	</div>
	<div class="w-75 print-layout-container print-layout-type-list d-none">
		@foreach($listItems as $listItem)
		<div class="py-0">
			<span class="locale-number locale-number-quantity-amount">{{ $listItem->amount }}</span> @if(!empty($listItem->product_id)){{ $__n($listItem->amount, $listItem->qu_name, $listItem->qu_name_plural, true) }}@endif
			@if(!empty($listItem->product_id)) {{ $listItem->product_name }}<br>@endif<em>{!! nl2br($listItem->note ?? '') !!}</em>
		</div><br>
		@endforeach
	</div>
	<div class="w-75 pt-3">
		<div>
			<h5>{{ $__t('Notes') }}</h5>
			<p id="description-for-print"></p>
		</div>
	</div>
</div>

<!-- Store Product Search Modal -->
<div class="modal fade"
	id="store-product-search-modal"
	tabindex="-1"
	data-preferred-store-integration-id="{{ $preferredStoreIntegrationId }}">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">{{ $__t('Add from store') }}</h5>
				<button type="button"
					class="close"
					data-dismiss="modal">
					<span>&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<div class="form-group">
					<label for="store-search-integration">{{ $__t('Store') }}</label>
					<select class="form-control"
						id="store-search-integration">
						<option value="">{{ $__t('Select a store') }}</option>
						@foreach($storeIntegrations as $integration)
							@php
								$integrationLocations = array_filter($storeLocations, function($loc) use ($integration) {
									return $loc->store_integration_id == $integration->id;
								});
							@endphp
							@if(count($integrationLocations) > 0)
								@foreach($integrationLocations as $location)
								<option value="{{ $integration->id }}"
									data-location-id="{{ $location->id }}"
									data-integration-name="{{ $integration->name }}"
									data-location-name="{{ $location->name }}">
									{{ $integration->name }} - {{ $location->name }}@if($location->city), {{ $location->city }}@endif
								</option>
								@endforeach
							@else
								<option value="{{ $integration->id }}"
									data-integration-name="{{ $integration->name }}">
									{{ $integration->name }} ({{ ucfirst($integration->store_type) }})
								</option>
							@endif
						@endforeach
					</select>
				</div>
				<div class="form-group">
					<label for="store-search-term">{{ $__t('Search') }}</label>
					<div class="input-group">
						<input type="text"
							class="form-control"
							id="store-search-term"
							placeholder="{{ $__t('Enter product name or barcode') }}">
						<div class="input-group-append">
							<button class="btn btn-primary"
								id="store-search-button"
								type="button">
								<i class="fa-solid fa-search"></i> {{ $__t('Search') }}
							</button>
						</div>
					</div>
				</div>
				<div id="store-search-results"
					class="d-none">
					<h6>{{ $__t('Results') }}</h6>
					<div id="store-search-results-list"
						class="list-group"
						style="max-height: 400px; overflow-y: auto;">
					</div>
				</div>
				<div id="store-search-loading"
					class="text-center d-none">
					<i class="fa-solid fa-spinner fa-spin fa-2x"></i>
					<p>{{ $__t('Searching...') }}</p>
				</div>
				<div id="store-search-error"
					class="alert alert-danger d-none"></div>
			</div>
			<div class="modal-footer">
				<button type="button"
					class="btn btn-secondary"
					data-dismiss="modal">{{ $__t('Close') }}</button>
			</div>
		</div>
	</div>
</div>

@include('components.productcard', [
'asModal' => true
])
@stop
