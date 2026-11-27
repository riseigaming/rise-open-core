@once('global-vendor-runtime')
@prepend('js')
<script src="{{ asset('vendor/jquery/jquery.min.js') }}"></script>
<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('vendor/toastr/toastr.min.js') }}"></script>
<script src="{{ asset('cms-assets/js/common.js') }}"></script>
<script>window.showToastMessage = window.toastMessage;</script>
@endprepend
@endonce
