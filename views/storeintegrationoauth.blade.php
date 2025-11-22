@extends('layout.default')

@section('title', $__t('Store Integration Authentication'))

@section('content')
<div class="row">
	<div class="col">
		<h2 class="title">@yield('title')</h2>
	</div>
</div>

<hr class="my-2">

<div class="row">
	<div class="col-lg-6 col-12">
		@if($success)
		<div class="alert alert-success">
			<h4>{{ $__t('Success!') }}</h4>
			<p>{{ $__t('Your store integration "%s" has been successfully authenticated.', $integrationName) }}</p>
			<p id="close-message">{{ $__t('This window will close automatically...') }}</p>
		</div>
		@else
		<div class="alert alert-danger">
			<h4>{{ $__t('Authentication Failed') }}</h4>
			<p>{{ $__t('There was an error authenticating your store integration:') }}</p>
			<p><strong>{{ $error }}</strong></p>
			<p>{{ $__t('Please try again or contact support if the problem persists.') }}</p>
		</div>
		<a href="{{ $U('/storeintegrations') }}" class="btn btn-primary">{{ $__t('Go to Store Integrations') }}</a>
		@endif
	</div>
</div>
@stop

@push('pageScripts')
<script>
@if($success)
// If this page is opened in a popup window, close it automatically
if (window.opener) {
	setTimeout(function() {
		window.close();
	}, 1500); // Close after 1.5 seconds to allow user to see success message
} else {
	// Not in a popup, show manual close instructions
	document.getElementById('close-message').innerHTML = '{{ $__t('You can now close this window and return to the settings page.') }}';
}
@endif
</script>
@endpush
