@extends('layout.default')

@if($mode == 'edit')
@section('title', $__t('Edit store integration'))
@else
@section('title', $__t('Create store integration'))
@endif

@push('pageScripts')
<script src="{{ $U('/viewjs/storeintegrationform.js?v=', true) }}{{ $version }}"></script>
@endpush

@section('content')
<div class="row">
	<div class="col">
		<h2 class="title">@yield('title')</h2>
	</div>
</div>

<hr class="my-2">

<div class="row">
	<div class="col-lg-6 col-12">
		<script>
			Grocy.EditMode = '{{ $mode }}';
		</script>

		@if($mode == 'edit')
		<script>
			Grocy.EditObjectId = {{ $integration->id }};
			Grocy.StoreType = '{{ $integration->store_type }}';
		</script>
		@endif

		<form id="storeintegration-form"
			novalidate>

			<div class="form-group">
				<label for="name">{{ $__t('Name') }}</label>
				<input type="text"
					class="form-control"
					required
					id="name"
					name="name"
					value="@if($mode == 'edit'){{ $integration->name }}@endif">
				<div class="invalid-feedback">{{ $__t('A name is required') }}</div>
			</div>

			<div class="form-group">
				<label for="store_type">{{ $__t('Store Type') }}</label>
				<select class="custom-control custom-select"
					required
					id="store_type"
					name="store_type"
					@if($mode == 'edit') disabled @endif>
					<option value="">{{ $__t('Please select...') }}</option>
					@foreach($storeTypes as $storeType)
					<option @if($mode=='edit' && $storeType['value'] == $integration->store_type) selected="selected" @endif value="{{ $storeType['value'] }}">{{ $storeType['label'] }}</option>
					@endforeach
				</select>
				<div class="invalid-feedback">{{ $__t('A store type is required') }}</div>
			</div>

			@if($mode == 'edit')
			<div class="form-group">
				<label for="active">
					<input type="checkbox"
						id="active"
						name="active"
						value="1"
						@if($integration->active == 1) checked @endif>
					{{ $__t('Active') }}
				</label>
			</div>
			@endif

			@if($mode == 'edit' && $integration->store_type == 'kroger')
			<div class="form-group">
				<label>{{ $__t('Authentication Status') }}</label>
				<div>
					@if(!empty($integration->access_token))
					<span class="badge badge-success">{{ $__t('Authenticated') }}</span>
					@if(!empty($integration->token_expires_at))
					<small class="text-muted d-block mt-1">{{ $__t('Expires') }}: {{ $integration->token_expires_at }}</small>
					@endif
					@else
					<span class="badge badge-warning">{{ $__t('Not authenticated') }}</span>
					@endif
				</div>
				<button type="button"
					id="authenticate-button"
					class="btn btn-sm btn-primary mt-2">
					{{ $__t('Authenticate with Kroger') }}
				</button>
			</div>

			<hr class="my-3">

			<h5>{{ $__t('Store Locations') }}</h5>
			<p class="text-muted small">{{ $__t('Search and save your Kroger store locations. The primary location will be used for product lookups and pricing.') }}</p>

			<div class="form-group">
				<label for="search_zip">{{ $__t('Search by Zip Code') }}</label>
				<div class="input-group">
					<input type="text"
						class="form-control"
						id="search_zip"
						placeholder="{{ $__t('Enter zip code') }}">
					<div class="input-group-append">
						<button type="button"
							id="search-stores-button"
							class="btn btn-primary">
							{{ $__t('Search') }}
						</button>
					</div>
				</div>
			</div>

			<div id="store-search-results"
				class="mb-3 d-none">
				<h6>{{ $__t('Search Results') }}</h6>
				<div id="store-search-results-list"></div>
			</div>

			<div id="saved-stores-wrapper">
				<h6>{{ $__t('Saved Locations') }}</h6>
				<div id="saved-stores-list"
					class="list-group">
					<div class="text-muted small">{{ $__t('No locations saved yet. Search and add a location above.') }}</div>
				</div>
			</div>
			@endif

			<button id="save-storeintegration-button"
				class="btn btn-success">{{ $__t('Save') }}</button>

		</form>
	</div>

	<div class="col-lg-6 col-12">
		@if($mode == 'edit')
		<div class="card mt-2 mt-lg-0">
			<div class="card-header">
				{{ $__t('Information') }}
			</div>
			<div class="card-body">
				<p>{{ $__t('This integration allows you to send shopping lists directly to your Kroger cart.') }}</p>
				<ul>
					<li>{{ $__t('Authenticate with your Kroger account') }}</li>
					<li>{{ $__t('Products will be matched by barcode (UPC) or name') }}</li>
					<li>{{ $__t('Matched products will be added to your Kroger cart') }}</li>
				</ul>
			</div>
		</div>
		@endif
	</div>
</div>
@stop
