@php global $GROCY_REQUIRED_FRONTEND_PACKAGES; @endphp

<!DOCTYPE html>
<html lang="{{ GROCY_LOCALE }}"
	dir="{{ $dir }}">

<head>
	<meta charset="utf-8">
	<meta name="viewport"
		content="width=device-width, initial-scale=1">
	<meta name="robots"
		content="noindex,nofollow">

	<link rel="icon"
		type="image/png"
		sizes="32x32"
		href="{{ $U('/img/icon-32.png?v=', true) }}{{ $version }}">

	@if (GROCY_AUTHENTICATED)
	<link rel="manifest"
		crossorigin="use-credentials"
		href="{{ $U('/manifest') . '?data=' . base64_encode($__env->yieldContent('title') . '#' . $U($_SERVER['REQUEST_URI'])) }}">
	@endif

	<title>@yield('title') | Grocy</title>

	<link href="{{ $U('/packages/@fontsource/roboto/400.css?v=', true) }}{{ $version }}"
		rel="stylesheet">
	<link href="{{ $U('/packages/@fontsource/roboto/500.css?v=', true) }}{{ $version }}"
		rel="stylesheet">
	<link href="{{ $U('/packages/@fontsource/roboto/700.css?v=', true) }}{{ $version }}"
		rel="stylesheet">
	<link href="{{ $U('/packages/bootstrap/dist/css/bootstrap.min.css?v=', true) }}{{ $version }}"
		rel="stylesheet">
	<link href="{{ $U('/packages/@fortawesome/fontawesome-free/css/fontawesome.min.css?v=', true) }}{{ $version }}"
		rel="stylesheet">
	<link href="{{ $U('/packages/@fortawesome/fontawesome-free/css/solid.min.css?v=', true) }}{{ $version }}"
		rel="stylesheet">
	<link href="{{ $U('/packages/toastr/build/toastr.min.css?v=', true) }}{{ $version }}"
		rel="stylesheet">

	@if(in_array('animatecss', $GROCY_REQUIRED_FRONTEND_PACKAGES))
	<link href="{{ $U('/packages/animate.css/animate.min.css?v=', true) }}{{ $version }}"
		rel="stylesheet">
	@endif

	<link href="{{ $U('/css/grocy.css?v=', true) }}{{ $version }}"
		rel="stylesheet">

	@if(boolval($userSettings['night_mode_enabled_internal']))
	<link id="night-mode-stylesheet"
		href="{{ $U('/css/grocy_night_mode.css?v=', true) }}{{ $version }}"
		rel="stylesheet">
	@endif

	@stack('pageStyles')

	@if(file_exists(GROCY_DATAPATH . '/custom_css.html'))
	@php include GROCY_DATAPATH . '/custom_css.html' @endphp
	@endif

	<style>
		/* Fullscreen layout - no scrolling on body */
		html, body {
			margin: 0;
			padding: 0;
			height: 100%;
			overflow: hidden;
			position: fixed;
			width: 100%;
		}

		#fullscreen-content {
			height: 100vh;
			overflow-y: auto;
			overflow-x: hidden;
			-webkit-overflow-scrolling: touch;
		}
	</style>

	<script>
		var Grocy = { };
		Grocy.Components = { };
		Grocy.Mode = '{{ GROCY_MODE }}';
		Grocy.BaseUrl = '{{ $U('/') }}';
		Grocy.Version = '{{ $version }}';
		Grocy.View = '{{ $viewName }}';
		Grocy.Currency = '{{ GROCY_CURRENCY }}';
		Grocy.LocalizationStrings = {!! $LocalizationStrings !!};
		Grocy.CalendarFirstDayOfWeek = '{{ $userSettings['calendar_first_day_of_week'] ?? '' }}';
		Grocy.FeatureFlags = {!! json_encode($featureFlags) !!};
		@if(GROCY_AUTHENTICATED)
		Grocy.UserSettings = {!! json_encode($userSettings) !!};
		Grocy.UserId = {{ GROCY_USER_ID }};
		Grocy.UserPermissions = {!! json_encode($permissions) !!};
		@else
		Grocy.UserSettings = { };
		Grocy.UserId = -1;
		Grocy.UserPermissions = [];
		@endif
	</script>
</head>

<body class="@if(boolval($userSettings['night_mode_enabled_internal'])) night-mode @endif">
	<div id="fullscreen-content">
		@yield('content')
	</div>

	<script src="{{ $U('/packages/jquery/dist/jquery.min.js?v=', true) }}{{ $version }}"></script>
	<script src="{{ $U('/packages/bootstrap/dist/js/bootstrap.bundle.min.js?v=', true) }}{{ $version }}"></script>
	<script src="{{ $U('/packages/bootbox/dist/bootbox.min.js?v=', true) }}{{ $version }}"></script>
	<script src="{{ $U('/packages/jquery-serializejson/jquery.serializejson.min.js?v=', true) }}{{ $version }}"></script>
	<script src="{{ $U('/packages/moment/min/moment.min.js?v=', true) }}{{ $version }}"></script>
	@if(!empty($__t('moment_locale') && $__t('moment_locale') != 'x'))<script src="{{ $U('/packages', true) }}/moment/locale/{{ $__t('moment_locale') }}.js?v={{ $version }}"></script>@endif
	<script src="{{ $U('/packages/toastr/build/toastr.min.js?v=', true) }}{{ $version }}"></script>
	<script src="{{ $U('/packages/sprintf-js/dist/sprintf.min.js?v=', true) }}{{ $version }}"></script>
	<script src="{{ $U('/packages/gettext-translator/dist/translator.js?v=', true) }}{{ $version }}"></script>
	<script src="{{ $U('/packages/nosleep.js/dist/NoSleep.min.js?v=', true) }}{{ $version }}"></script>

	<script>
		// Initialize translator
		var translator = new Translator(Grocy.LocalizationStrings, Grocy.LocalizationStrings, {
			defaultMissingMessage: 'not translated'
		});
		var __t = function(text, ...placeholderValues)
		{
			return translator.get(text, ...placeholderValues);
		};
		var __n = function(number, singularForm, pluralForm)
		{
			return translator.getPlural(number, singularForm, pluralForm);
		};
		var U = function(relativePath, noHash)
		{
			return Grocy.BaseUrl + relativePath.replace(/^\/+/g, '') + (noHash ? '' : '?v=' + Grocy.Version);
		};
	</script>

	<script src="{{ $U('/js/extensions.js?v=', true) }}{{ $version }}"></script>
	<script src="{{ $U('/js/grocy.js?v=', true) }}{{ $version }}"></script>

	@stack('pageScripts')

	@if(file_exists(GROCY_DATAPATH . '/custom_js.html'))
	@php include GROCY_DATAPATH . '/custom_js.html' @endphp
	@endif
</body>
</html>
