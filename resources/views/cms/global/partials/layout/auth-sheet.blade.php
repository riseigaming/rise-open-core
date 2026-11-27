@php
    $globalAuthRegistrationOpen = \App\Settings\ProductSettings::from($siteSetting ?? null)->registrationOpen();
    $globalAuthInitialMode = session('open_register_modal') && $globalAuthRegistrationOpen ? 'register' : 'login';
    $globalAuthShouldOpen = (bool) (session('open_login_modal') || session('open_register_modal') || session('auth_error'));
    $globalLoginRedirect = session('login_redirect_after', url()->current());
    $globalRegisterCountries = collect($countries ?? [])->filter(fn ($country) => filled(data_get($country, 'id')));
    $globalRegisterCurrencies = collect($registrationCurrencies ?? [])->filter(fn ($currency) => filled(data_get($currency, 'code')));
    $globalDefaultCountry = $globalRegisterCountries->first(fn ($country) => (int) data_get($country, 'id') === (int) \App\Models\CmsUser::query()->where('registration_source', 'app:admin-bootstrap')->where('role', 'admin')->orderBy('id')->value('country_id'))
        ?: $globalRegisterCountries->first();
    $globalDefaultCurrency = $globalRegisterCurrencies->first(fn ($currency) => (bool) data_get($currency, 'is_default'))
        ?: $globalRegisterCurrencies->first();
@endphp

<div class="globalSheetLayer"
     data-global-auth-sheet
     data-initial-mode="{{ $globalAuthInitialMode }}"
     data-global-motion-state="closed"
     @if(!$globalAuthShouldOpen) hidden @endif>
    <button class="globalSheetBackdrop" type="button" tabindex="-1" data-global-sheet-close aria-label="{{ trans('cms_public.common.close') }}"></button>
    <section class="globalSheetPanel" role="dialog" aria-modal="true" aria-labelledby="global-auth-title" aria-describedby="global-auth-description" tabindex="-1">
        <header class="globalSheetHeader">
            <div>
                <span class="globalEyebrow">{{ trans('global.account_access') }}</span>
                <h2 id="global-auth-title">{{ trans('global.continue_with_global') }}</h2>
            </div>
            <button class="globalSheetClose" type="button" data-global-sheet-close aria-label="{{ trans('cms_public.common.close') }}">&times;</button>
        </header>

        <p class="globalSheetDescription" id="global-auth-description">{{ trans('global.use_the_verified_account_flow_without_leaving_the_current_product') }}</p>

        <div class="globalSheetTabs" role="tablist" aria-label="{{ trans('cms_public.shell.membership') }}">
            <button type="button" role="tab" data-global-auth-mode="login" aria-controls="global-auth-login-panel">{{ trans('global.log_in') }}</button>
            @if($globalAuthRegistrationOpen)
                <button type="button" role="tab" data-global-auth-mode="register" aria-controls="global-auth-register-panel">{{ trans('cms_public.auth.create_account_cta') }}</button>
            @endif
        </div>

        <div id="global-auth-login-panel" data-global-auth-panel="login" role="tabpanel">
            @if(session('auth_error'))
                <p class="globalFormNotice" role="alert">{{ session('auth_error') }}</p>
            @endif
            <form class="globalAuthForm" method="post" action="{{ route('cms.loginPost') }}">
                @csrf
                <input type="hidden" name="auth_surface" value="global">
                <input type="hidden" name="redirectAfterLogin" value="{{ $globalLoginRedirect }}">
                <label>
                    <span>{{ trans('cms_public.auth.email_username') }}</span>
                    <input type="text" name="email" value="{{ old('email') }}" autocomplete="username" data-global-sheet-initial-focus required>
                </label>
                <label>
                    <span>{{ trans('cms_public.auth.password') }}</span>
                    <input type="password" name="password" autocomplete="current-password" required>
                </label>
                <label class="globalCheckRow">
                    <input type="checkbox" name="remember" value="1">
                    <span>{{ trans('cms_public.auth.remember_me') }}</span>
                </label>
                <button class="globalPrimaryAction" type="submit">{{ trans('global.log_in') }}</button>
            </form>
            <a class="globalTextAction" href="{{ route('cms.password.request') }}" data-global-account-recovery>{{ trans('cms_password.forgot_link') }}</a>
        </div>

        @if($globalAuthRegistrationOpen)
            <div id="global-auth-register-panel" data-global-auth-panel="register" role="tabpanel" hidden>
                @if($globalRegisterCountries->isEmpty() || $globalRegisterCurrencies->isEmpty())
                    <div class="globalNotice is-warning" role="alert">
                        <strong>{{ trans('global.registration_is_temporarily_unavailable') }}</strong>
                        <span>{{ trans('global.b1.registration_unavailable') }}</span>
                    </div>
                @else
                    <form class="globalAuthForm"
                          method="post"
                          action="{{ route('cms.registerPost') }}"
                          data-global-register-form>
                        @csrf
                        <input type="hidden" name="auth_surface" value="global">
                        <input type="hidden" name="redirectAfterLogin" value="{{ $globalLoginRedirect }}">
                        <label>
                            <span>{{ trans('cms_public.auth.email_username') }}</span>
                            <input type="text" name="username" autocomplete="username" maxlength="100" required>
                        </label>
                        <div class="globalAuthFieldPair">
                            <label>
                                <span>{{ trans('cms_public.auth.password') }}</span>
                                <input type="password" name="password" autocomplete="new-password" minlength="8" required>
                            </label>
                            <label>
                                <span>{{ trans('cms_public.auth.password_confirm') }}</span>
                                <input type="password" name="passwordConfirm" autocomplete="new-password" minlength="8" required>
                            </label>
                        </div>
                        <div class="globalAuthFieldPair">
                            <label>
                                <span>{{ trans('global.country') }}</span>
                                <select name="countryId" required>
                                    @foreach($globalRegisterCountries as $country)
                                        <option value="{{ data_get($country, 'id') }}" @if((string) data_get($country, 'id') === (string) data_get($globalDefaultCountry, 'id')) selected @endif>
                                            {{ data_get($country, 'name') ?: data_get($country, 'code') }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                            <label>
                                <span>{{ trans('cms_public.auth.currency') }}</span>
                                <select name="currencyId" required>
                                    @foreach($globalRegisterCurrencies as $currency)
                                        @php($globalCurrencyCode = strtoupper((string) data_get($currency, 'code')))
                                        <option value="{{ $globalCurrencyCode }}" @if($globalCurrencyCode === strtoupper((string) data_get($globalDefaultCurrency, 'code'))) selected @endif>
                                            {{ $globalCurrencyCode }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                        </div>
                        <label class="globalCheckRow">
                            <input type="checkbox" name="accept_terms" value="1" required>
                            <span>{{ trans('global.i_am_at_least_18_and_accept_the') }}<a href="{{ route('cms.staticPage', ['pageName' => 'privacy-policy']) }}">{{ trans('cms_public.legal_pages.privacy_policy.title') }}</a> and <a href="{{ route('cms.staticPage', ['pageName' => 'terms-conditions']) }}">{{ trans('global.terms_and_conditions') }}</a>.</span>
                        </label>
                        <p class="globalFormNotice" role="alert" data-global-register-feedback hidden></p>
                        @include('cms.partials.registration-turnstile')
                        <button class="globalPrimaryAction" type="submit">{{ trans('cms_public.auth.create_account_cta') }}</button>
                    </form>
                @endif
            </div>
        @endif
    </section>
</div>
