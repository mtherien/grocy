@if (!GROCY_FEATURE_FLAG_DISABLE_BROWSER_BARCODE_CAMERA_SCANNING)

@php require_frontend_packages(['zxing']); @endphp

@once
@push('componentScripts')
<script src="{{ $U('/viewjs/components/camerabarcodescanner.js', true) }}?v={{ $version }}"></script>
@endpush
@endonce

@push('pageStyles')
<style>
	#camerabarcodescanner-start-button {
		position: absolute;
		right: 0;
		margin-top: 4px;
		margin-right: 5px;
		cursor: pointer;
	}

	.combobox-container #camerabarcodescanner-start-button {
		margin-right: 38px !important;
	}

	/* Camera scanner video display */
	#camerabarcodescanner-container {
		width: 100%;
		max-width: 100%;
		overflow: hidden;
	}

	#camerabarcodescanner-livestream {
		width: 100%;
		max-width: 100%;
		height: auto;
		display: block;
		background: #000;
	}

	.camerabarcodescanner-modal .modal-dialog {
		max-width: 90%;
	}

	@media (min-width: 768px) {
		.camerabarcodescanner-modal .modal-dialog {
			max-width: 600px;
		}
	}
</style>
@endpush

@endif
