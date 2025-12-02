@php require_frontend_packages(['datatables']); @endphp

@extends('layout.default')

@section('title', $__t('Stores'))

@section('content')
<div class="row">
	<div class="col">
		<div class="title-related-links">
			<h2 class="title">@yield('title')</h2>
			<div class="float-right @if($embedded) pr-5 @endif">
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
				<a class="btn btn-primary responsive-button m-1 mt-md-0 mb-md-0 float-right show-as-dialog-link"
					href="{{ $U('/shoppinglocation/new?embedded') }}">
					{{ $__t('Add') }}
				</a>
				@if(defined('GROCY_FEATURE_FLAG_STORE_INTEGRATIONS') && GROCY_FEATURE_FLAG_STORE_INTEGRATIONS && !empty($storeIntegrations) && iterator_to_array($storeIntegrations))
				<button class="btn btn-success responsive-button m-1 mt-md-0 mb-md-0 float-right"
					data-toggle="modal"
					data-target="#add-from-integration-modal">
					<i class="fa-solid fa-link"></i> {{ $__t('Add from Integration') }}
				</button>
				@endif
				<a class="btn btn-outline-secondary m-1 mt-md-0 mb-md-0 float-right"
					href="{{ $U('/userfields?entity=shopping_locations') }}">
					{{ $__t('Configure userfields') }}
				</a>
			</div>
		</div>
	</div>
</div>

<hr class="my-2">

<div class="row collapse d-md-flex"
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
		<div class="form-check custom-control custom-checkbox">
			<input class="form-check-input custom-control-input"
				type="checkbox"
				id="show-disabled">
			<label class="form-check-label custom-control-label"
				for="show-disabled">
				{{ $__t('Show disabled') }}
			</label>
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

<div class="row">
	<div class="col">
		<table id="shoppinglocations-table"
			class="table table-sm table-striped nowrap w-100">
			<thead>
				<tr>
					<th class="border-right"><a class="text-muted change-table-columns-visibility-button"
							data-toggle="tooltip"
							title="{{ $__t('Table options') }}"
							data-table-selector="#shoppinglocations-table"
							href="#"><i class="fa-solid fa-eye"></i></a>
					</th>
					<th>{{ $__t('Name') }}</th>
					<th>{{ $__t('Description') }}</th>

					@include('components.userfields_thead', array(
					'userfields' => $userfields
					))

				</tr>
			</thead>
			<tbody class="d-none">
				@foreach($shoppinglocations as $shoppinglocation)
				<tr class="@if($shoppinglocation->active == 0) text-muted @endif">
					<td class="fit-content border-right">
						<a class="btn btn-info btn-sm show-as-dialog-link"
							href="{{ $U('/shoppinglocation/') }}{{ $shoppinglocation->id }}?embedded"
							data-toggle="tooltip"
							title="{{ $__t('Edit this item') }}">
							<i class="fa-solid fa-edit"></i>
						</a>
						<a class="btn btn-danger btn-sm shoppinglocation-delete-button"
							href="#"
							data-shoppinglocation-id="{{ $shoppinglocation->id }}"
							data-shoppinglocation-name="{{ $shoppinglocation->name }}"
							data-toggle="tooltip"
							title="{{ $__t('Delete this item') }}">
							<i class="fa-solid fa-trash"></i>
						</a>
					</td>
					<td>
						{{ $shoppinglocation->name }}
					</td>
					<td>
						{{ $shoppinglocation->description }}
					</td>

					@include('components.userfields_tbody', array(
					'userfields' => $userfields,
					'userfieldValues' => FindAllObjectsInArrayByPropertyValue($userfieldValues, 'object_id', $shoppinglocation->id)
					))

				</tr>
				@endforeach
			</tbody>
		</table>
	</div>
</div>

@if(defined('GROCY_FEATURE_FLAG_STORE_INTEGRATIONS') && GROCY_FEATURE_FLAG_STORE_INTEGRATIONS && !empty($storeIntegrations) && iterator_to_array($storeIntegrations))
<!-- Add from Integration Modal -->
<div class="modal fade"
	id="add-from-integration-modal"
	tabindex="-1"
	role="dialog">
	<div class="modal-dialog modal-lg"
		role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">{{ $__t('Add Store from Integration') }}</h5>
				<button type="button"
					class="close"
					data-dismiss="modal">
					<span>&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<div class="form-group">
					<label for="integration-select">{{ $__t('Select Integration') }}</label>
					<select class="form-control"
						id="integration-select">
						<option value="">{{ $__t('Select...') }}</option>
						@foreach($storeIntegrations as $integration)
						<option value="{{ $integration->id }}">{{ $integration->name }} ({{ $integration->store_type }})</option>
						@endforeach
					</select>
				</div>

				<div id="location-search-section"
					class="d-none">
					<div class="form-group">
						<label for="zip-code-input">{{ $__t('Zip Code') }}</label>
						<div class="input-group">
							<input type="text"
								class="form-control"
								id="zip-code-input"
								placeholder="{{ $__t('Enter zip code') }}">
							<div class="input-group-append">
								<button class="btn btn-primary"
									type="button"
									id="search-stores-button">
									<i class="fa-solid fa-search"></i> {{ $__t('Search') }}
								</button>
							</div>
						</div>
					</div>

					<div id="store-results-section"
						class="d-none">
						<h6>{{ $__t('Search Results') }}</h6>
						<div id="store-results-list"
							class="list-group">
						</div>
					</div>

					<div id="store-search-loading"
						class="d-none text-center py-3">
						<div class="spinner-border text-primary"
							role="status">
							<span class="sr-only">{{ $__t('Loading...') }}</span>
						</div>
					</div>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button"
					class="btn btn-secondary"
					data-dismiss="modal">{{ $__t('Close') }}</button>
			</div>
		</div>
	</div>
</div>
@endif

@stop
