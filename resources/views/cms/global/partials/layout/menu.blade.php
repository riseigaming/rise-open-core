@php
    $globalUser = auth('cms')->user();
    $globalAuthenticated = $globalUser !== null;
    $globalThemeSettings = \App\Settings\ThemeSettings::from($siteSetting ?? null);
    $globalRegistrationOpen = \App\Settings\ProductSettings::from($siteSetting ?? null)->registrationOpen();
    $globalCurrentPath = '/' . ltrim(request()->path(), '/');
    $globalAccountUrl = route($globalAuthenticated ? 'cms.front.sessions' : 'cms.login');
    $globalBrandTitle = trim((string) data_get($siteSetting, 'translation.site_title')) ?: 'Rise Open Core';
    $globalBrandLogo = $siteSetting?->getPublicLogo();
    $globalNavigation = app(\App\Services\Cms\CmsNavigationService::class);
    $globalMainNavItems = $globalNavigation->sidebar([], app()->getLocale());
    $globalHeroNavItems = $globalNavigation->hero(app()->getLocale());
    $globalRailLanguages = collect($activeLanguages ?? [])->filter(fn ($language) => filled(data_get($language, 'code')));
    $globalNormalizeLanguageCode = static function ($value): string {
        $code = strtolower(str_replace('_', '-', trim((string) $value)));
        $aliases = ['ing' => 'en', 'esp' => 'es'];

        if (isset($aliases[$code])) {
            return $aliases[$code];
        }

        $primary = strtok($code, '-');

        return $primary !== false && $primary !== '' ? ($aliases[$primary] ?? $primary) : $code;
    };
    $globalRailLocale = $globalNormalizeLanguageCode(session('currentLocale', app()->getLocale()));
    $globalRailCurrentLanguage = $globalRailLanguages->first(function ($language) use ($globalNormalizeLanguageCode, $globalRailLocale) {
        return $globalNormalizeLanguageCode(data_get($language, 'code')) === $globalRailLocale;
    });
    $globalRailCurrentLanguageName = data_get($globalRailCurrentLanguage, 'name') ?: strtoupper($globalRailLocale);
@endphp
<aside class="globalDesktopRail" id="global-desktop-rail" aria-label="{{ trans('global.main_navigation') }}">
    <div class="globalRailHeader">
        <a class="globalRailBrand" href="{{ route('cms.front.home') }}" aria-label="{{ trans('cms_public.shell.home') }}">@if($globalBrandLogo)<img src="{{ $globalBrandLogo }}" alt="" width="32" height="32">@else<span class="globalBrandIcon" aria-hidden="true"><span></span></span>@endif<span class="globalRailBrandText">{{ $globalBrandTitle }}</span></a>
        <button class="globalRailToggle" type="button" data-global-rail-toggle aria-controls="global-desktop-rail" aria-expanded="true" aria-label="{{ trans('global.collapse_navigation') }}"><span aria-hidden="true"></span></button>
    </div>
    <nav class="globalRailNav" aria-label="{{ trans('global.main_navigation') }}">
        <section class="globalRailAccountMini" data-global-rail-account-mini>
            <span class="globalAccountAvatar" aria-hidden="true"></span>
            <span class="globalRailAccountCopy"><strong>{{ $globalAuthenticated ? $globalUser->username : trans('global.guest_mode') }}</strong><small>{{ trans('global.account_controls') }}</small></span>
            <a href="{{ $globalAccountUrl }}" @if($globalAuthenticated) data-global-account-open aria-controls="global-account-sheet" aria-expanded="false" @else data-global-auth-open="login" @endif>{{ trans('global.open_account') }}</a>
        </section>
        <section class="globalRailSection" aria-labelledby="global-rail-main-pages">
            <span class="globalRailSectionTitle" id="global-rail-main-pages">{{ trans('cms_public.shell.main_menu') }}</span>
            @foreach($globalMainNavItems as $item)
                @if(!empty($item['children']))
                    <details class="globalRailDisclosure"><summary class="globalRailLink"><span class="journeyNavIcon" aria-hidden="true">{{ ['casino'=>'♧','learn'=>'≡','info'=>'ⓘ','account'=>'○'][$item['icon']] ?? '›' }}</span><span class="globalRailLinkLabel">{{ $item['text'] }}</span></summary><div class="globalRailSubnav">@include('cms.global.partials.journey.nav-links',['items'=>[$item]])</div></details>
                @else
                    <a class="globalRailLink" href="{{ $item['href'] }}" target="{{ $item['target'] }}" rel="noopener noreferrer" @if($globalNavigation->isCurrent($item['href'])) aria-current="page" @endif><span class="journeyNavIcon" aria-hidden="true">{{ $item['icon']==='home' ? '⌂' : '›' }}</span><span class="globalRailLinkLabel">{{ $item['text'] }}</span></a>
                @endif
            @endforeach
        </section>
        @if($globalRailLanguages->isNotEmpty())
            <section class="globalRailLanguageCard" aria-label="{{ trans('cms_public.shell.language_selection') }}" data-global-active-language="{{ $globalRailLocale }}">
                <span class="globalRailSectionTitle">{{ trans('cms_public.shell.language') }}</span>
                <button class="globalRailLanguageTrigger globalCompactAction" type="button" data-global-popover-open="language" aria-controls="global-language-popover" aria-expanded="false">
                    <span>{{ $globalRailCurrentLanguageName }}</span>
                    <strong>{{ strtoupper($globalRailLocale) }}</strong>
                    <span aria-hidden="true">v</span>
                </button>
                <div>
                    @foreach($globalRailLanguages as $language)
                        @php($globalRailLanguageRouteCode = strtolower(trim((string) data_get($language, 'code'))))
                        @php($globalRailLanguageCode = $globalNormalizeLanguageCode($globalRailLanguageRouteCode))
                        <a class="{{ $globalRailLocale === $globalRailLanguageCode ? 'is-active' : '' }}"
                           href="{{ route('cms.setLanguage', ['languageCode' => $globalRailLanguageRouteCode, 'redirect' => url()->full()]) }}"
                           data-global-language-code="{{ $globalRailLanguageCode }}"
                           @if($globalRailLocale === $globalRailLanguageCode) aria-current="true" @endif>
                            {{ strtoupper($globalRailLanguageCode) }}
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </nav>
    <div class="globalRailFooter"><a class="globalRailLink" href="{{ $globalAccountUrl }}" @if($globalAuthenticated) data-global-account-open @else data-global-auth-open="login" @endif><span class="globalNavGlyph is-account" aria-hidden="true"></span><span class="globalRailLinkLabel">{{ trans('cms_public.shell.profile') }}</span></a></div>
</aside>
<header class="globalTopShell">
    <a class="globalMobileBrand" href="{{ route('cms.front.home') }}">@if($globalBrandLogo)<img src="{{ $globalBrandLogo }}" alt="" width="32" height="32">@else<span class="globalBrandIcon" aria-hidden="true"><span></span></span>@endif<strong>{{ $globalBrandTitle }}</strong></a>
    <nav class="globalTopNavigation" aria-label="{{ trans('cms_public.shell.quick_navigation') }}">
        @foreach($globalHeroNavItems as $item)
            @if(!empty($item['children']))
            <details class="journeyTopGroup"><summary class="globalTextAction">{{ $item['text'] }} <span aria-hidden="true">⌄</span></summary><nav class="journeyTopSubmenu" aria-label="{{ $item['text'] }}">@include('cms.global.partials.journey.nav-links',['items'=>[$item]])</nav></details>
            @else<a class="globalTextAction" href="{{ $item['href'] }}" target="{{ $item['target'] }}" rel="noopener noreferrer" @if($globalNavigation->isCurrent($item['href'])) aria-current="page" @endif>{{ $item['text'] }}</a>@endif
        @endforeach
    </nav>
    <div class="globalTopActions" aria-label="{{ trans('cms_public.shell.account_menu') }}">
        <button class="globalIconAction is-mobile-menu" type="button" data-global-sheet-open="menu" aria-label="{{ trans('cms_public.shell.open_menu') }}"><span class="globalNavGlyph is-more" aria-hidden="true"></span></button>
        @if($globalThemeSettings->topbarFullscreenVisible())<button class="globalIconAction" type="button" data-global-fullscreen-toggle aria-label="{{ trans('global.toggle_fullscreen') }}"><span class="globalFullscreenGlyph" aria-hidden="true"></span></button>@endif
        @if($globalAuthenticated)
            <a class="globalAccountAction" href="{{ $globalAccountUrl }}" data-global-account-open aria-controls="global-account-sheet" aria-expanded="false"><span class="globalAccountAvatar" aria-hidden="true"></span><span>{{ $globalUser->username }}</span></a>
            @if($globalThemeSettings->topbarLogoutVisible())<a class="globalIconAction" href="{{ route('cms.logout') }}" aria-label="{{ trans('cms_public.shell.logout') }}"><span class="globalLogoutGlyph" aria-hidden="true"></span></a>@endif
        @else
            <a class="globalTextAction" href="{{ route('cms.login') }}" data-global-auth-open="login">{{ trans('global.log_in') }}</a>
            @if($globalRegistrationOpen)<a class="globalPrimaryAction" href="{{ route('cms.register') }}" data-global-auth-open="register">{{ trans('cms_public.auth.create_account_cta') }}</a>@endif
        @endif
    </div>
</header>
<nav class="globalMobileBottomNav" data-global-mobile-dock aria-label="{{ trans('cms_public.shell.mobile_quick_menu') }}">
    @foreach(array_slice($globalMainNavItems, 0, 4) as $item)<a class="globalMobileNavLink" href="{{ $item['href'] }}" target="{{ $item['target'] }}" rel="noopener noreferrer"><span class="globalNavGlyph is-home" aria-hidden="true"></span><span>{{ $item['text'] }}</span></a>@endforeach
    <button class="globalMobileNavLink" type="button" data-global-sheet-open="menu" aria-label="{{ trans('cms_public.shell.open_menu') }}"><span class="globalNavGlyph is-more" aria-hidden="true"></span><span>{{ trans('cms_public.common.more') }}</span></button>
</nav>
@if($globalRailLanguages->isNotEmpty())
    <div class="globalPopoverLayer is-language-origin" id="global-language-popover" data-global-language-popover data-global-popover="language" data-global-motion-state="closed" hidden>
        <button class="globalPopoverBackdrop" type="button" tabindex="-1" data-global-popover-close aria-label="{{ trans('cms_public.common.close') }}"></button>
        <section class="globalPopoverPanel globalSheetPanel is-compact is-language" role="dialog" aria-modal="false" aria-labelledby="global-language-popover-title">
            <header class="globalSheetHeader">
                <div>
                    <span class="globalEyebrow">{{ trans('cms_public.shell.language') }}</span>
                    <h2 id="global-language-popover-title">{{ trans('global.choose_language') }}</h2>
                </div>
                <button class="globalSheetClose" type="button" data-global-popover-close aria-label="{{ trans('cms_public.common.close') }}">&times;</button>
            </header>
            <nav class="globalGrid globalGrid--1" aria-label="{{ trans('cms_public.shell.languages_title') }}">
                @foreach($globalRailLanguages as $language)
                    @php($globalRailPopoverRouteCode = strtolower(trim((string) data_get($language, 'code'))))
                    @php($globalRailPopoverLanguageCode = $globalNormalizeLanguageCode($globalRailPopoverRouteCode))
                    @php($globalRailPopoverLanguageName = data_get($language, 'name') ?: strtoupper($globalRailPopoverLanguageCode))
                    <a class="globalRouteCard {{ $globalRailLocale === $globalRailPopoverLanguageCode ? 'is-active' : '' }}"
                       href="{{ route('cms.setLanguage', ['languageCode' => $globalRailPopoverRouteCode, 'redirect' => url()->full()]) }}"
                       data-global-language-code="{{ $globalRailPopoverLanguageCode }}"
                       @if($globalRailLocale === $globalRailPopoverLanguageCode) aria-current="true" @endif>
                        <span class="globalNavGlyph is-language" aria-hidden="true"></span>
                        <span><strong>{{ $globalRailPopoverLanguageName }}</strong><small>{{ strtoupper($globalRailPopoverLanguageCode) }}</small></span>
                        <b aria-hidden="true">&rarr;</b>
                    </a>
                @endforeach
            </nav>
        </section>
    </div>
@endif
