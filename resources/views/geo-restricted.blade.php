<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta name="csrfToken" content="{{ csrf_token() }}">
    @php
        $restrictionProduct = $decision->productKey ?? 'site';
        $restrictionCopyKey = $restrictionProduct === 'site' ? 'site' : 'product';
        $restrictionObservedCountry = strtoupper(trim((string) data_get($decision->context ?? [], 'observed_country', '')));
        $restrictionLanguageItems = [
            ['code' => 'tr', 'label' => 'Türkçe'],
            ['code' => 'en', 'label' => 'English'],
            ['code' => 'es', 'label' => 'Español'],
        ];
    @endphp
    <title>{{ trans("geo_restriction.$restrictionCopyKey.page_title") }}</title>
    <style>
        :root { color-scheme: dark; font-family: Manrope, Inter, ui-sans-serif, system-ui, sans-serif; --gold: #efbe4b; }
        * { box-sizing: border-box; }
        html, body { min-height: 100%; }
        body { margin: 0; background: #0a102a; color: #f6f8ff; }
        button, a { font: inherit; }
        .site-dim { position: fixed; inset: 0; z-index: 1; background: rgba(3, 8, 24, .76); }
        .restriction-dialog-wrap { position: fixed; inset: 0; z-index: 2; display: grid; place-items: center; padding: 20px; overflow-y: auto; pointer-events: none; }
        .restriction-dialog { width: min(100%, 520px); padding: clamp(28px, 5vw, 46px); border: 1px solid #405588; border-radius: 26px; background: linear-gradient(150deg, #152248, #0d1733); box-shadow: 0 30px 90px #0009; text-align: center; pointer-events: auto; }
        .restriction-emblem { display: block; width: 94px; height: 94px; margin: 0 auto 21px; object-fit: contain; }
        h1 { margin: 0; font-size: clamp(27px, 5vw, 37px); line-height: 1.18; letter-spacing: -.035em; }
        .title-accent { color: #8db8ff; }
        .lead { margin: 16px auto 0; max-width: 420px; color: #bbc8e7; line-height: 1.6; }
        .connection-hint { margin: 16px auto 0; color: #cbd8ef; font-size: 14px; line-height: 1.6; }
        .connection-hint strong { color: #f4f7ff; }
        .support-action { display: block; width: 100%; margin-top: 26px; padding: 14px 20px; border: 0; border-radius: 15px; background: var(--gold); color: #152248; font-weight: 800; cursor: pointer; }
        .support-action:focus-visible,
        .geoUtilityToggle:focus-visible,
        .geoUtilityClose:focus-visible,
        .geoUtilityAction:focus-visible,
        .geoLanguageLink:focus-visible { outline: 3px solid #a6d7ff; outline-offset: 3px; }
        .meta { margin-top: 20px; color: #9bacce; font-size: 12px; line-height: 1.55; }
        .meta small { display: block; }
        .meta small + small { margin-top: 4px; }

        .geoUtilityToggle {
            position: fixed; left: 12px; top: 50%; z-index: 5; width: 48px; height: 58px;
            transform: translateY(-50%); border: 1px solid rgba(255,255,255,.10); border-radius: 0 16px 16px 0;
            background: rgba(24,29,47,.96); color: #f5f7ff; cursor: pointer; font-size: 22px;
            box-shadow: 0 18px 42px rgba(0,0,0,.28);
        }
        .geoUtilityDrawer {
            position: fixed; left: 12px; top: 50%; z-index: 6; width: min(290px, calc(100vw - 24px));
            transform: translate(-115%, -50%); opacity: 0; visibility: hidden; pointer-events: none;
            padding: 18px; border: 1px solid rgba(145,166,211,.18); border-radius: 20px;
            background: rgba(18,25,45,.98); box-shadow: 0 26px 70px rgba(0,0,0,.42);
            transition: transform .2s cubic-bezier(.16,1,.3,1), opacity .14s ease, visibility 0s linear .2s;
        }
        .geoUtilityDrawer.is-open { transform: translate(0, -50%); opacity: 1; visibility: visible; pointer-events: auto; transition-delay: 0s; }
        .geoUtilityHead { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
        .geoUtilityHead strong { display: block; font-size: 16px; }
        .geoUtilityHead p { margin: 5px 0 0; color: #9eacc9; font-size: 12px; line-height: 1.45; }
        .geoUtilityClose { flex: 0 0 36px; width: 36px; height: 36px; border: 0; border-radius: 10px; background: rgba(255,255,255,.07); color: #fff; cursor: pointer; }
        .geoUtilitySection { margin-top: 18px; }
        .geoUtilityLabel { display: block; margin-bottom: 8px; color: #9eacc9; font-size: 11px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
        .geoLanguageList { display: grid; grid-template-columns: repeat(3, 1fr); gap: 7px; }
        .geoLanguageLink, .geoUtilityAction {
            display: flex; align-items: center; justify-content: center; min-height: 42px; border: 1px solid rgba(145,166,211,.16);
            border-radius: 12px; background: rgba(255,255,255,.055); color: #eaf0ff; text-decoration: none; font-weight: 800; cursor: pointer;
        }
        .geoLanguageLink.is-active { border-color: rgba(239,190,75,.55); background: rgba(239,190,75,.13); color: #ffd66f; }
        .geoUtilityAction { width: 100%; background: #223b67; border-color: #36558d; }
        .geoUtilityBackdrop { position: fixed; inset: 0; z-index: 4; background: rgba(2,6,18,.28); opacity: 0; visibility: hidden; pointer-events: none; transition: opacity .14s ease, visibility 0s linear .14s; }
        .geoUtilityBackdrop.is-open { opacity: 1; visibility: visible; pointer-events: auto; transition-delay: 0s; }

        body.winx-theme .supportWidgetShell { z-index: 1210; }
        body.winx-theme .supportWidgetBackdrop { z-index: 1209; }

        @media (max-width: 640px) {
            .restriction-dialog { border-radius: 23px; padding: 30px 22px; }
            .restriction-emblem { width: 76px; height: 76px; }
            .geoUtilityToggle { top: auto; bottom: 86px; left: 10px; width: 50px; height: 50px; transform: none; border-radius: 14px; }
            .geoUtilityDrawer { top: auto; bottom: 82px; left: 10px; transform: translateY(18px) scale(.96); transform-origin: left bottom; }
            .geoUtilityDrawer.is-open { transform: translateY(0) scale(1); }
        }
    </style>
</head>
<body>
<div class="site-dim" aria-hidden="true"></div>

<button type="button" class="geoUtilityToggle" data-geo-utility-toggle aria-expanded="false" aria-controls="geoUtilityDrawer" aria-label="{{ trans('cms_public.shell.open_menu') }}">☰</button>
<div class="geoUtilityBackdrop" data-geo-utility-dismiss aria-hidden="true"></div>
<aside class="geoUtilityDrawer" id="geoUtilityDrawer" aria-hidden="true">
    <div class="geoUtilityHead">
        <div>
            <strong>{{ trans('geo_restriction.restricted_menu_title') }}</strong>
            <p>{{ trans('geo_restriction.restricted_menu_body') }}</p>
        </div>
        <button type="button" class="geoUtilityClose" data-geo-utility-dismiss aria-label="{{ trans('cms_public.common.close') }}">×</button>
    </div>
    <div class="geoUtilitySection">
        <span class="geoUtilityLabel">{{ trans('cms_public.shell.language') }}</span>
        <div class="geoLanguageList">
            @foreach($restrictionLanguageItems as $languageItem)
                <a class="geoLanguageLink {{ app()->getLocale() === $languageItem['code'] ? 'is-active' : '' }}"
                   href="{{ route('cms.front.home', ['lang' => $languageItem['code']]) }}"
                   lang="{{ $languageItem['code'] }}"
                   @if(app()->getLocale() === $languageItem['code']) aria-current="page" @endif>
                    {{ $languageItem['label'] }}
                </a>
            @endforeach
        </div>
    </div>

</aside>

<div class="restriction-dialog-wrap">
    <main class="restriction-dialog" role="alertdialog" aria-modal="true" aria-labelledby="restriction-title" data-geo-restriction-page data-restriction-product="{{ $restrictionProduct }}" data-restriction-reason="{{ $decision->reasonCode }}">
        <h1 id="restriction-title">{{ trans("geo_restriction.$restrictionCopyKey.title_start") }}<span class="title-accent">{{ trans("geo_restriction.$restrictionCopyKey.title_accent") }}</span>{{ trans("geo_restriction.$restrictionCopyKey.title_end") }}</h1>
        <p class="lead">{{ trans("geo_restriction.$restrictionCopyKey.body", ['product' => ucfirst($restrictionProduct)]) }}</p>
        <p class="connection-hint">{!! trans('geo_restriction.connection_hint') !!}</p>
        <div class="meta"><small>{{ trans('geo_restriction.reference') }}: {{ $decision->reasonCode }}</small></div>
    </main>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var toggle = document.querySelector('[data-geo-utility-toggle]');
        var drawer = document.getElementById('geoUtilityDrawer');
        var backdrop = document.querySelector('[data-geo-utility-dismiss].geoUtilityBackdrop');
        var dismissers = Array.prototype.slice.call(document.querySelectorAll('[data-geo-utility-dismiss]'));

        var setDrawer = function (open) {
            if (!toggle || !drawer || !backdrop) return;
            drawer.classList.toggle('is-open', open);
            backdrop.classList.toggle('is-open', open);
            drawer.setAttribute('aria-hidden', open ? 'false' : 'true');
            backdrop.setAttribute('aria-hidden', open ? 'false' : 'true');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        };

        if (toggle) toggle.addEventListener('click', function () {
            setDrawer(!drawer.classList.contains('is-open'));
        });
        dismissers.forEach(function (button) {
            button.addEventListener('click', function () { setDrawer(false); });
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && drawer && drawer.classList.contains('is-open')) setDrawer(false);
        });

    }, { once: true });
</script>
</body>
</html>
