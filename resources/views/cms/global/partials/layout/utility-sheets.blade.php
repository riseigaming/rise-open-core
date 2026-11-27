@php
    $globalUtilityAuthenticated = auth()->guard('cms')->check();
    $globalUtilityThemeSettings = \App\Settings\ThemeSettings::from($siteSetting ?? null);
    $globalUtilityLanguages = collect($activeLanguages ?? [])->filter(fn ($language) => filled(data_get($language, 'code')));
    $globalUtilityNormalizeLanguageCode = static function ($value): string {
        $code = strtolower(str_replace('_', '-', trim((string) $value)));
        $aliases = ['ing' => 'en', 'esp' => 'es'];

        if (isset($aliases[$code])) {
            return $aliases[$code];
        }

        $primary = strtok($code, '-');

        return $primary !== false && $primary !== '' ? ($aliases[$primary] ?? $primary) : $code;
    };
    $globalUtilityLocale = $globalUtilityNormalizeLanguageCode(session('currentLocale', app()->getLocale()));
    $globalUtilityNavigation = app(\App\Services\Cms\CmsNavigationService::class);
@endphp

<div class="globalSheetLayer" data-global-menu-sheet data-global-motion-state="closed" hidden>
    <button class="globalSheetBackdrop" type="button" tabindex="-1" data-global-sheet-close aria-label="{{ trans('cms_public.shell.close_menu') }}"></button>
    <section class="globalSheetPanel is-compact" role="dialog" aria-modal="true" aria-labelledby="global-menu-sheet-title">
        <header class="globalSheetHeader">
            <div>
                <span class="globalEyebrow">{{ trans('cms_public.common.more') }}</span>
                <h2 id="global-menu-sheet-title">{{ trans('global.everything_within_reach') }}</h2>
            </div>
            <button class="globalSheetClose" type="button" data-global-sheet-close aria-label="{{ trans('cms_public.common.close') }}">&times;</button>
        </header>
        @foreach(['sidebar', 'hero'] as $globalSlot)
            <div class="globalMenuSheetSection {{ $loop->first ? 'is-first' : '' }}">
                <span class="globalEyebrow">{{ trans('navigation.'.$globalSlot) }}</span>
                <nav class="globalMenuSheetGrid" aria-label="{{ trans('navigation.'.$globalSlot) }}">
                    @include('cms.global.partials.journey.nav-links',['items'=>$globalSlot === 'sidebar' ? $globalUtilityNavigation->sidebar([], app()->getLocale()) : $globalUtilityNavigation->hero(app()->getLocale())])
                </nav>
            </div>
        @endforeach
        @if($globalUtilityLanguages->isNotEmpty())
            <div class="globalMenuSheetSection is-language">
                <span class="globalEyebrow">{{ trans('cms_public.shell.language') }}</span>
                <div class="globalChipRow">
                    @foreach($globalUtilityLanguages as $language)
                        @php($globalUtilityLanguageRouteCode = strtolower(trim((string) data_get($language, 'code'))))
                        @php($globalUtilityLanguageCode = $globalUtilityNormalizeLanguageCode($globalUtilityLanguageRouteCode))
                        <a class="globalChip {{ $globalUtilityLocale === $globalUtilityLanguageCode ? 'is-active' : '' }}"
                           href="{{ route('cms.setLanguage', ['languageCode' => $globalUtilityLanguageRouteCode, 'redirect' => url()->full()]) }}"
                           data-global-language-code="{{ $globalUtilityLanguageCode }}"
                           @if($globalUtilityLocale === $globalUtilityLanguageCode) aria-current="true" @endif>
                            {{ data_get($language, 'name') ?: strtoupper($globalUtilityLanguageCode) }}
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        @if($globalUtilityThemeSettings->topbarFullscreenVisible() || ($globalUtilityAuthenticated && $globalUtilityThemeSettings->topbarLogoutVisible()))
            <div class="globalSheetActions">
                @if($globalUtilityThemeSettings->topbarFullscreenVisible())
                    <button class="globalGhostAction" type="button" data-global-fullscreen-toggle>{{ trans('global.toggle_fullscreen') }}</button>
                @endif
                @if($globalUtilityAuthenticated && $globalUtilityThemeSettings->topbarLogoutVisible())
                    <a class="globalSecondaryAction" href="{{ route('cms.logout') }}">{{ trans('cms_public.shell.logout') }}</a>
                @endif
            </div>
        @endif
    </section>
</div>


<div class="globalSheetLayer is-corner-origin is-campaign-origin" id="global-campaign-sheet" data-global-campaign-sheet data-global-motion-state="closed" hidden>
    <button class="globalSheetBackdrop" type="button" tabindex="-1" data-global-sheet-close aria-label="{{ trans('cms_public.common.close') }}"></button>
    <section class="globalSheetPanel is-compact" role="dialog" aria-modal="true" aria-labelledby="global-campaign-sheet-title">
        <header class="globalSheetHeader">
            <h2 id="global-campaign-sheet-title">{{ trans('cms_public.shell.campaigns') }}</h2>
            <button class="globalSheetClose" type="button" data-global-sheet-close aria-label="{{ trans('cms_public.common.close') }}">&times;</button>
        </header>
        @forelse(collect($timedPromotions ?? [])->filter(fn ($item) => filled(data_get($item, 'display_listing_title'))) as $globalCampaign)
            <article class="globalNotice" data-global-timed-promotion="{{ $globalCampaign->id }}">
                <strong>{{ $globalCampaign->display_listing_title }}</strong>
                <span>{{ $globalCampaign->display_short_description }}</span>
                @if(filled($globalCampaign->end_date))
                    <time datetime="{{ $globalCampaign->end_date }}">{{ \Illuminate\Support\Carbon::parse($globalCampaign->end_date)->locale(app()->getLocale())->diffForHumans() }}</time>
                @endif
                <button type="button" class="globalGhostAction" data-navbar-timed-promotion-trigger data-bs-target="#globalTimedPromotion-{{ $globalCampaign->id }}" aria-haspopup="dialog" aria-expanded="false">{{ trans('cms_public.promotions.details') }}</button>
            </article>
        @empty
            <p class="globalSheetDescription">{{ trans('cms_public.promotions.empty') }}</p>
        @endforelse
        <a class="globalPrimaryAction" href="{{ route('cms.promotions') }}" data-global-sheet-initial-focus>{{ trans('cms_public.promotions.title') }}</a>
    </section>
</div>
@foreach(collect($timedPromotions ?? [])->filter(fn ($item) => filled(data_get($item, 'display_listing_title'))) as $globalCampaign)
    @include('cms.global.partials.navbar-timed-promotion-modal', ['promotion' => $globalCampaign, 'modalId' => 'globalTimedPromotion-' . $globalCampaign->id])
@endforeach

@if(app()->environment(['local', 'testing']) && request()->query('ui_kit') === '1')
    <div class="globalSheetLayer" data-global-confirmation-sheet data-global-motion-state="closed" hidden>
        <button class="globalSheetBackdrop" type="button" tabindex="-1" data-global-sheet-close aria-label="Close confirmation preview"></button>
        <section class="globalSheetPanel is-compact" role="dialog" aria-modal="true" aria-labelledby="global-confirmation-title" aria-describedby="global-confirmation-description">
            <header class="globalSheetHeader">
                <div>
                    <span class="globalEyebrow">Confirmation pattern</span>
                    <h2 id="global-confirmation-title">Review before a real action.</h2>
                </div>
                <button class="globalSheetClose" type="button" data-global-sheet-close aria-label="{{ trans('cms_public.common.close') }}">&times;</button>
            </header>
            <p class="globalSheetDescription" id="global-confirmation-description">This is a visual contract only. It does not submit, approve or change product data.</p>
            <div class="globalNotice is-warning" role="note">
                <strong>Preview only</strong>
                <span>Real confirmations will show backend-owned amounts, limits and consequences.</span>
            </div>
            <div class="globalSheetActions">
                <button class="globalPrimaryAction" type="button" data-global-sheet-close data-global-sheet-initial-focus>Understood</button>
                <button class="globalGhostAction" type="button" data-global-sheet-close>{{ trans('cms_public.shell.cancel') }}</button>
            </div>
        </section>
    </div>
@endif
