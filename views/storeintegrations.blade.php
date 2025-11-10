@php require_frontend_packages(['datatables']); @endphp

@extends('layout.default')

@section('title', $__t('Store Integrations'))

@push('pageScripts')
<script src="{{ $U('/viewjs/storeintegrations.js?v=', true) }}{{ $version }}"></script>
@endpush

@section('content')
<div class="row">
	<div class="col">
		<div class="title-related-links">
			<h2 class="title">@yield('title')</h2>
			<div class="float-right">
				<button class="btn btn-outline-dark d-md-none mt-2 order-1 order-md-3"
					type="button"
					data-toggle="collapse"
					data-target="#table-filter-row">
					<i class="fa-solid fa-filter"></i>
				</button>
			</div>
			<div class="related-links">
				<a class="btn btn-primary responsive-button show-as-dialog-link"
					href="{{ $U('/storeintegration/new?embedded') }}">
					{{ $__t('Add integration') }}
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
		<table id="storeintegrations-table"
			class="table table-sm table-striped nowrap w-100">
			<thead>
				<tr>
					<th class="border-right"><a class="text-muted change-table-columns-visibility-button"
							data-toggle="tooltip"
							title="{{ $__t('Table options') }}"
							data-table-selector="#storeintegrations-table"
							href="#"><i class="fa-solid fa-eye"></i></a>
					</th>
					<th>{{ $__t('Name') }}</th>
					<th>{{ $__t('Store Type') }}</th>
					<th>{{ $__t('Status') }}</th>
					<th>{{ $__t('Authenticated') }}</th>
				</tr>
			</thead>
			<tbody>
				@foreach($integrations as $integration)
				<tr>
					<td class="fit-content border-right">
						<a class="btn btn-info btn-sm show-as-dialog-link"
							href="{{ $U('/storeintegration/' . $integration->id . '?embedded') }}"
							data-toggle="tooltip"
							title="{{ $__t('Edit this item') }}">
							<i class="fa-solid fa-edit"></i>
						</a>
						<a class="btn btn-danger btn-sm integration-delete-button"
							href="#"
							data-integration-id="{{ $integration->id }}"
							data-integration-name="{{ $integration->name }}"
							data-toggle="tooltip"
							title="{{ $__t('Delete this item') }}">
							<i class="fa-solid fa-trash"></i>
						</a>
					</td>
					<td>{{ $integration->name }}</td>
					<td>{{ ucfirst($integration->store_type) }}</td>
					<td>
						@if($integration->active == 1)
						<span class="badge badge-success">{{ $__t('Active') }}</span>
						@else
						<span class="badge badge-secondary">{{ $__t('Inactive') }}</span>
						@endif
					</td>
					<td>
						@if(!empty($integration->access_token))
						<span class="badge badge-success">{{ $__t('Yes') }}</span>
						@else
						<span class="badge badge-warning">{{ $__t('No') }}</span>
						@endif
					</td>
				</tr>
				@endforeach
			</tbody>
		</table>
	</div>
</div>
@stop
