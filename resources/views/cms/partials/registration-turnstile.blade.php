@if(config('registration_security.turnstile.enabled', false))
    @if(config('registration_security.turnstile.site_key'))
        <div class="cmsRegistrationTurnstile" data-registration-turnstile
             data-sitekey="{{ config('registration_security.turnstile.site_key') }}"></div>
        @once
            <script src="{{ asset('cms-assets/js/registration-turnstile.js') }}?v={{ filemtime(public_path('cms-assets/js/registration-turnstile.js')) }}" defer></script>
        @endonce
    @else
        <p role="alert">{{ trans('cms_public.auth.turnstile_failed') }}</p>
    @endif
@endif
