@extends('layout.default')

@if($mode == 'edit')
@section('title', $__t('Edit store'))
@else
@section('title', $__t('Create store'))
@endif

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
			Grocy.EditObjectId = {{ $shoppingLocation->id }};
		</script>

		@if(!empty($shoppingLocation->store_integration_id))
		<div class="alert alert-info">
			<i class="fas fa-link"></i>
			<strong>{{ $__t('Store Integration') }}</strong>
			<p class="mb-1">
				{{ $__t('This store is integrated with') }} <strong>{{ $shoppingLocation->integration_name }}</strong> ({{ $shoppingLocation->store_type }})
			</p>
			@if(!empty($shoppingLocation->external_location_id))
			<small class="text-muted">{{ $__t('External ID') }}: {{ $shoppingLocation->external_location_id }}</small>
			@endif
			@if($shoppingLocation->is_primary == 1)
			<br><small><span class="badge badge-primary">{{ $__t('Primary Location') }}</span></small>
			@endif
		</div>
		@endif
		@endif

		<form id="shoppinglocation-form"
			novalidate>

			<div class="form-group">
				<label for="name">{{ $__t('Name') }}</label>
				<input type="text"
					class="form-control"
					required
					id="name"
					name="name"
					value="@if($mode == 'edit'){{ $shoppingLocation->name }}@endif">
				<div class="invalid-feedback">{{ $__t('A name is required') }}</div>
			</div>

			<div class="form-group">
				<div class="custom-control custom-checkbox">
					<input @if($mode=='create'
						)
						checked
						@elseif($mode=='edit'
						&&
						$shoppingLocation->active == 1) checked @endif class="form-check-input custom-control-input" type="checkbox" id="active" name="active" value="1">
					<label class="form-check-label custom-control-label"
						for="active">{{ $__t('Active') }}</label>
				</div>
			</div>

			<div class="form-group">
				<label for="description">{{ $__t('Description') }}</label>
				<textarea class="form-control"
					rows="2"
					id="description"
					name="description">@if($mode == 'edit'){{ $shoppingLocation->description }}@endif</textarea>
			</div>

			<h4 class="mt-4 mb-3">{{ $__t('Location Details') }}</h4>

			<div class="form-group">
				<label for="address">{{ $__t('Address') }}</label>
				<input type="text"
					class="form-control"
					id="address"
					name="address"
					value="@if($mode == 'edit'){{ $shoppingLocation->address }}@endif">
			</div>

			<div class="form-row">
				<div class="form-group col-md-6">
					<label for="city">{{ $__t('City') }}</label>
					<input type="text"
						class="form-control"
						id="city"
						name="city"
						value="@if($mode == 'edit'){{ $shoppingLocation->city }}@endif">
				</div>
				<div class="form-group col-md-3">
					<label for="state">{{ $__t('State') }}</label>
					<input type="text"
						class="form-control"
						id="state"
						name="state"
						value="@if($mode == 'edit'){{ $shoppingLocation->state }}@endif">
				</div>
				<div class="form-group col-md-3">
					<label for="zip_code">{{ $__t('Zip code') }}</label>
					<input type="text"
						class="form-control"
						id="zip_code"
						name="zip_code"
						value="@if($mode == 'edit'){{ $shoppingLocation->zip_code }}@endif">
				</div>
			</div>

			<div class="form-group">
				<label for="phone">{{ $__t('Phone') }}</label>
				<input type="tel"
					class="form-control"
					id="phone"
					name="phone"
					value="@if($mode == 'edit'){{ $shoppingLocation->phone }}@endif">
			</div>

			@include('components.userfieldsform', array(
			'userfields' => $userfields,
			'entity' => 'shopping_locations'
			))

			<button id="save-shopping-location-button"
				class="btn btn-success">{{ $__t('Save') }}</button>

		</form>
	</div>
</div>
@stop
