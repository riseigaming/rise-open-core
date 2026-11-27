{{-- Existing shared notice component: no new delivery or business rules. --}}
@once('global-frontend-notices')
@push('head')
<link rel="stylesheet" href="{{ asset('cms-assets/css/frontend-notice.css') }}?v={{ filemtime(public_path('cms-assets/css/frontend-notice.css')) }}">
@endpush
@push('js')
<script src="{{ asset('cms-assets/js/frontend-notice.js') }}?v={{ filemtime(public_path('cms-assets/js/frontend-notice.js')) }}"></script>
@endpush
@endonce
