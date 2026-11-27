@php
    $globalAccountUser = auth()->guard('cms')->user();
    $globalAccountAuthenticated = $globalAccountUser !== null;
    $globalAccountThemeSettings = \App\Settings\ThemeSettings::from($siteSetting ?? null);
    $globalAccountProductSettings = \App\Settings\ProductSettings::from($siteSetting ?? null);
@endphp

<div class="globalSheetLayer" id="global-account-sheet" data-global-account-sheet data-global-motion-state="closed" hidden>
    <button class="globalSheetBackdrop" type="button" tabindex="-1" data-global-sheet-close aria-label="{{ trans('global.close_account_menu') }}"></button>
    <section class="globalSheetPanel is-compact" role="dialog" aria-modal="true" aria-labelledby="global-account-title" aria-describedby="global-account-description">
        <header class="globalSheetHeader">
            <div>
                <span class="globalEyebrow">{{ trans('global.account_access') }}</span>
                <h2 id="global-account-title">{{ $globalAccountAuthenticated ? trans('global.your_account_tools') : trans('global.continue_with_your_account') }}</h2>
            </div>
            <button class="globalSheetClose" type="button" data-global-sheet-close aria-label="{{ trans('cms_public.common.close') }}">&times;</button>
        </header>

        @if($globalAccountAuthenticated)
            <p class="globalSheetDescription" id="global-account-description">{{ trans('global.b1.account_description') }}</p>

            @php
                $panelPermissions=app(\App\Services\CmsStaff\CmsStaffPermissionService::class);
                $panelRoute=$panelPermissions->isAdmin($globalAccountUser)?'cms.admin.home':null;
                if (!$panelRoute) foreach ([\App\Services\CmsStaff\CmsStaffPermissionService::CATALOG_GAMES=>'cms.admin.games.index',\App\Services\CmsStaff\CmsStaffPermissionService::CATALOG_PROVIDERS=>'cms.admin.providers.index',\App\Services\CmsStaff\CmsStaffPermissionService::CATALOG_CURATION=>'cms.admin.games.curation'] as $key=>$target) if ($panelPermissions->has($globalAccountUser,$key)) { $panelRoute=$target;break; }
            @endphp
            <nav class="globalAccountSheetLinks" aria-label="{{ trans('global.account_tools') }}">
                @if($panelRoute)<a href="{{ route($panelRoute) }}"><span>CMS</span><b aria-hidden="true">&rarr;</b></a>@endif
                <a href="{{ route('cms.front.sessions') }}" data-global-sessions-entry data-global-sheet-initial-focus><span>{{ trans('global.sessions') }}</span><b aria-hidden="true">&rarr;</b></a>
                @if(config('nova_payments.enabled') && app(\App\Services\Payments\NovaDepositService::class)->availableRail($globalAccountUser))
                    <a href="{{ route('cms.wallet.nova.index') }}"><span>Nova</span><b aria-hidden="true">&rarr;</b></a>
                @endif
                <a href="{{ route('cms.front.editPassword') }}"><span>{{ trans('global.b1.change_password') }}</span><b aria-hidden="true">&rarr;</b></a>
                <a href="{{ route('cms.setLanguage', ['languageCode' => app()->getLocale(), 'redirect' => url()->full()]) }}" data-global-popover-open="language" aria-controls="global-language-popover" aria-expanded="false"><span>{{ trans('global.language_and_region') }}</span><b aria-hidden="true">&rarr;</b></a>
                <a href="{{ route('cms.front.learnGuide','account-security') }}">{{ __('journey.guides.account-security.title') }}</a>
                <a href="{{ route('cms.logout') }}" data-global-account-logout><span>{{ trans('cms_public.shell.logout') }}</span><b aria-hidden="true">&rarr;</b></a>
            </nav>
        @else
            <p class="globalSheetDescription" id="global-account-description">{{ trans('global.b1.account_login') }}</p>
            <div class="globalNotice is-auth" role="note">
                <strong>{{ trans('global.no_protected_account_data_is_shown') }}</strong>
                <span>{{ trans('global.your_account_context_becomes_available_only_after_the_existing_cm') }}</span>
            </div>
            <div class="globalSheetActions">
                <a class="globalPrimaryAction" href="{{ url('/cms/login') }}" data-global-auth-open="login" data-global-sheet-initial-focus>{{ trans('global.log_in') }}</a>
                @if($globalAccountProductSettings->registrationOpen())
                    <a class="globalSecondaryAction" href="{{ url('/cms/register') }}" data-global-auth-open="register">{{ trans('cms_public.auth.create_account_cta') }}</a>
                @endif
            </div>
        @endif
    </section>
</div>
