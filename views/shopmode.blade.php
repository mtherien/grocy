@php require_frontend_packages(['animatecss']); @endphp

@extends('layout.default')

@section('title', $__t('Shop List') . ' - ' . $shoppingList->name)

@push('pageStyles')
<style>
/* Shop Mode Mobile-Optimized Styles */
body {
	padding-bottom: 80px; /* Space for fixed action bar */
}

/* Store Selector */
.shop-store-selector {
	position: sticky;
	top: 0;
	z-index: 100;
	background: white;
	padding: 15px;
	border-bottom: 2px solid #dee2e6;
	box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.shop-store-selector select {
	font-size: 18px;
	height: 50px;
}

.shop-store-selector button {
	height: 50px;
	min-width: 50px;
	font-size: 18px;
}

/* Main Content Area */
.shop-items-container {
	padding: 10px;
	max-width: 600px;
	margin: 0 auto;
}

/* Aisle Headers - Sticky */
.aisle-header {
	position: sticky;
	top: 81px; /* Below store selector */
	z-index: 50;
	background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
	color: white;
	padding: 12px 15px;
	margin: 15px -10px 10px -10px;
	font-size: 20px;
	font-weight: bold;
	box-shadow: 0 2px 4px rgba(0,0,0,0.2);
	border-radius: 8px;
}

.not-in-store-header {
	background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
}

/* Item Cards */
.shop-item-card {
	background: white;
	border: 2px solid #dee2e6;
	border-radius: 12px;
	padding: 15px;
	margin-bottom: 12px;
	display: flex;
	align-items: center;
	gap: 12px;
	box-shadow: 0 2px 4px rgba(0,0,0,0.1);
	transition: all 0.2s ease;
}

.shop-item-card.done {
	opacity: 0.6;
	background: #f8f9fa;
}

.shop-item-card.done .shop-item-name {
	text-decoration: line-through;
}

/* Checkbox - 44x44px touch target */
.shop-item-checkbox {
	width: 44px;
	height: 44px;
	min-width: 44px;
	cursor: pointer;
	flex-shrink: 0;
}

/* Product Image */
.shop-item-image {
	width: 60px;
	height: 60px;
	object-fit: cover;
	border-radius: 8px;
	flex-shrink: 0;
}

.shop-item-image-placeholder {
	width: 60px;
	height: 60px;
	background: #e9ecef;
	border-radius: 8px;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 30px;
	color: #6c757d;
	flex-shrink: 0;
}

/* Item Details */
.shop-item-details {
	flex: 1;
	min-width: 0;
}

.shop-item-name {
	font-size: 18px;
	font-weight: 600;
	margin-bottom: 4px;
	word-wrap: break-word;
}

.shop-item-location {
	font-size: 14px;
	color: #6c757d;
}

.shop-item-price {
	font-size: 16px;
	color: #28a745;
	font-weight: 600;
}

/* Quantity Controls - 44x44px buttons */
.shop-quantity-controls {
	display: flex;
	align-items: center;
	gap: 8px;
	flex-shrink: 0;
}

.shop-quantity-btn {
	width: 44px;
	height: 44px;
	min-width: 44px;
	border: 2px solid #dee2e6;
	background: white;
	border-radius: 8px;
	font-size: 24px;
	font-weight: bold;
	display: flex;
	align-items: center;
	justify-content: center;
	cursor: pointer;
	transition: all 0.2s ease;
}

.shop-quantity-btn:hover {
	background: #f8f9fa;
	border-color: #6c757d;
}

.shop-quantity-btn:active {
	transform: scale(0.95);
}

.shop-quantity-display {
	font-size: 20px;
	font-weight: bold;
	min-width: 40px;
	text-align: center;
}

/* Floating Action Button (Scanner) - 64x64px */
.scan-button-fab {
	position: fixed;
	bottom: 90px;
	right: 20px;
	width: 64px;
	height: 64px;
	border-radius: 50%;
	background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
	color: white;
	border: none;
	box-shadow: 0 4px 12px rgba(0,0,0,0.3);
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 28px;
	cursor: pointer;
	z-index: 1000;
	transition: all 0.3s ease;
}

.scan-button-fab:hover {
	transform: scale(1.1);
	box-shadow: 0 6px 16px rgba(0,0,0,0.4);
}

.scan-button-fab:active {
	transform: scale(0.95);
}

/* Fixed Action Bar at Bottom */
.shop-action-bar {
	position: fixed;
	bottom: 0;
	left: 0;
	right: 0;
	background: white;
	border-top: 2px solid #dee2e6;
	padding: 10px 15px;
	box-shadow: 0 -2px 8px rgba(0,0,0,0.1);
	z-index: 100;
	display: flex;
	align-items: center;
	gap: 10px;
}

.shop-checked-count {
	flex: 1;
	font-size: 18px;
	font-weight: 600;
}

.shop-action-btn {
	height: 50px;
	font-size: 18px;
	font-weight: 600;
	border-radius: 8px;
	min-width: 120px;
}

/* Empty State */
.shop-empty-state {
	text-align: center;
	padding: 40px 20px;
}

.shop-empty-state i {
	font-size: 64px;
	color: #dee2e6;
	margin-bottom: 20px;
}

.shop-empty-state h3 {
	color: #6c757d;
	margin-bottom: 10px;
}

/* Loading State */
.shop-loading {
	text-align: center;
	padding: 40px 20px;
}

.shop-loading .spinner-border {
	width: 3rem;
	height: 3rem;
}

/* Responsive adjustments */
@media (min-width: 768px) {
	.shop-items-container {
		max-width: 800px;
	}
}
</style>
@endpush

@push('pageScripts')
<script src="{{ $U('/viewjs/components/camerabarcodescanner.js?v=', true) }}{{ $version }}"></script>
<script src="{{ $U('/viewjs/shopmode.js?v=', true) }}{{ $version }}"></script>
@endpush

@section('content')
<div class="row">
	<div class="col">
		<!-- Store Selector (Sticky Top) -->
		<div class="shop-store-selector">
			<div class="row align-items-center">
				<div class="col-9">
					<select id="shop-location-selector" class="form-control">
						<option value="">{{ $__t('Select Store') }}</option>
						@foreach($integratedLocations as $location)
							<option value="{{ $location->id }}"
								data-integration-id="{{ $location->store_integration_id }}"
								@if($selectedLocationId == $location->id) selected @endif>
								{{ $location->name }}
							</option>
						@endforeach
					</select>
				</div>
				<div class="col-3">
					<button id="refresh-metadata-btn" class="btn btn-outline-primary btn-block" title="{{ $__t('Refresh store data') }}">
						<i class="fa-solid fa-sync"></i>
					</button>
				</div>
			</div>
		</div>

		<!-- Items Container -->
		<div class="shop-items-container" id="shop-items-container">
			<!-- Loading State -->
			<div class="shop-loading" id="shop-loading">
				<div class="spinner-border text-primary" role="status">
					<span class="sr-only">{{ $__t('Loading') }}...</span>
				</div>
				<p class="mt-3">{{ $__t('Loading items') }}...</p>
			</div>

			<!-- Empty State -->
			<div class="shop-empty-state d-none" id="shop-empty-state">
				<i class="fa-solid fa-shopping-cart"></i>
				<h3>{{ $__t('No items on list') }}</h3>
				<p>{{ $__t('Add items to your shopping list to start shopping') }}</p>
				<a href="{{ $U('/shoppinglist?list=' . $listId) }}" class="btn btn-primary mt-3">
					{{ $__t('Go to Shopping List') }}
				</a>
			</div>

			<!-- Items will be dynamically rendered here -->
		</div>

		<!-- Floating Scanner Button -->
		<button id="scan-button-fab" class="scan-button-fab">
			<i class="fa-solid fa-camera"></i>
		</button>

		<!-- Fixed Action Bar -->
		<div class="shop-action-bar">
			<div class="shop-checked-count">
				<i class="fa-solid fa-check-circle text-success"></i>
				<span id="checked-count">0</span> {{ $__t('items') }}
			</div>
			<button id="send-to-inventory-btn" class="btn btn-success shop-action-btn" disabled>
				{{ $__t('Send to Inventory') }}
			</button>
		</div>
	</div>
</div>

<!-- Barcode Scanner Modal (Included Component) -->
@include('components.camerabarcodescanner')

<!-- Scan Result Confirmation Modal -->
<div class="modal fade" id="scan-result-modal" tabindex="-1">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">{{ $__t('Add to List?') }}</h5>
				<button type="button" class="close" data-dismiss="modal">
					<span>&times;</span>
				</button>
			</div>
			<div class="modal-body text-center">
				<img id="scan-result-image" class="mb-3" style="max-width: 150px; max-height: 150px; border-radius: 8px;">
				<h4 id="scan-result-name" class="mb-3"></h4>
				<div class="shop-quantity-controls justify-content-center mb-3">
					<button class="shop-quantity-btn" id="scan-qty-minus">-</button>
					<span class="shop-quantity-display" id="scan-quantity">1</span>
					<button class="shop-quantity-btn" id="scan-qty-plus">+</button>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-dismiss="modal">{{ $__t('Cancel') }}</button>
				<button type="button" class="btn btn-success" id="scan-confirm-btn">{{ $__t('Add to List') }}</button>
			</div>
		</div>
	</div>
</div>

<!-- Bulk Inventory Modal -->
<div class="modal fade" id="bulk-inventory-modal" tabindex="-1">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">{{ $__t('Send to Inventory') }}</h5>
				<button type="button" class="close" data-dismiss="modal">
					<span>&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<form id="bulk-inventory-form">
					<div class="form-row">
						<div class="form-group col-md-6">
							<label for="bulk-location-id">{{ $__t('Location') }}</label>
							<select id="bulk-location-id" class="form-control" required>
								@foreach($stockLocations as $location)
									<option value="{{ $location->id }}" @if($location->id == $defaultStockLocationId) selected @endif>
										{{ $location->name }}
									</option>
								@endforeach
							</select>
						</div>
						<div class="form-group col-md-6">
							<label for="bulk-purchased-date">{{ $__t('Purchased date') }}</label>
							<input type="date" id="bulk-purchased-date" class="form-control" value="{{ date('Y-m-d') }}" required>
						</div>
					</div>
					<div class="form-row">
						<div class="form-group col-md-6">
							<label for="bulk-best-before-date">{{ $__t('Best before date') }}</label>
							<input type="date" id="bulk-best-before-date" class="form-control">
						</div>
					</div>
					<hr>
					<h6>{{ $__t('Items to add') }} (<span id="bulk-items-count">0</span>)</h6>
					<div id="bulk-items-list" style="max-height: 300px; overflow-y: auto;">
						<!-- Items list will be populated dynamically -->
					</div>
				</form>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-dismiss="modal">{{ $__t('Cancel') }}</button>
				<button type="button" class="btn btn-success" id="bulk-confirm-btn">
					<span id="bulk-confirm-text">{{ $__t('Add to Inventory') }}</span>
					<span id="bulk-confirm-spinner" class="spinner-border spinner-border-sm ml-2 d-none"></span>
				</button>
			</div>
		</div>
	</div>
</div>

<script>
// Pass data from PHP to JavaScript
Grocy.ShopMode = {
	listId: {{ $listId }},
	locationId: {{ $selectedLocationId ?? 'null' }},
	integratedLocations: {!! json_encode($integratedLocations->fetchAll()) !!}
};
</script>
@endsection
