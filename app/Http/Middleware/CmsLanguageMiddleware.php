<?php

namespace App\Http\Middleware;

use App\Models\CmsCountry;
use App\Models\CmsCurrency;
use App\Models\CmsLanguage;
use App\Models\CmsMenu;
use App\Models\CmsPromotion;
use App\Models\CmsSiteSetting;
use App\Models\CmsUser;
use App\Services\Geo\RequestGeoIpResolver;
use App\Services\Geo\GeoIpResolution;
use App\Services\Localization\CountryDefaultLanguageService;
use App\Support\CmsThemeSettingOverlay;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class CmsLanguageMiddleware
{
    public const MANUAL_LOCALE_COOKIE = 'cms_locale';

    public function __construct(
        private readonly CountryDefaultLanguageService $countryDefaults,
        private readonly RequestGeoIpResolver $geoIp
    ) {
    }

    private function safeHasTable(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (Throwable $throwable) {
            return false;
        }
    }

    private function safeHasColumn(string $table, string $column): bool
    {
        try {
            return Schema::hasColumn($table, $column);
        } catch (Throwable $throwable) {
            return false;
        }
    }

    // Early responses need the same locale policy without loading frontend data.
    public function resolveLocale(Request $request, ?GeoIpResolution $geo = null): void
    {
        $user = $request->user("cms");
        $currentLocale = session()->get("currentLocale");
        $languageTableAvailable = $this->safeHasTable('cms_languages');

        try {
            if ($languageTableAvailable && $currentLocale) {
                $sessionLanguage = $this->languageFromUserPreference($currentLocale);
                if (!$sessionLanguage) {
                    session()->forget(['currentLocale', 'currentLanguageId', 'currentLanguageName', 'currentLocaleManuallySelected']);
                    $currentLocale = null;
                } else {
                    session()->put('currentLocale', $sessionLanguage->code);
                    session()->put('currentLanguageId', $sessionLanguage->id);
                    session()->put('currentLanguageName', $sessionLanguage->name);
                    $currentLocale = $sessionLanguage->code;
                }
            }

            // The picker preference must survive file-session races and a fresh browser
            // request. Only an active CMS language can become authoritative.
            $manualCookieLocale = strtolower(trim((string) $request->cookie(self::MANUAL_LOCALE_COOKIE, '')));
            if ($languageTableAvailable && $manualCookieLocale !== '') {
                $cookieLanguage = $this->languageFromUserPreference($manualCookieLocale);
                if ($cookieLanguage instanceof CmsLanguage) {
                    session()->put('currentLocale', $cookieLanguage->code);
                    session()->put('currentLanguageId', $cookieLanguage->id);
                    session()->put('currentLanguageName', $cookieLanguage->name);
                    session()->put('currentLocaleManuallySelected', true);
                    $currentLocale = $cookieLanguage->code;
                }
            }

            // A slow homepage section can finish after the picker route and write
            // an older file-session snapshot. The saved customer preference is
            // authoritative once an authenticated picker choice exists.
            if ($languageTableAvailable && $user instanceof CmsUser && session()->get('currentLocaleManuallySelected')) {
                $preferredLanguage = $this->languageFromUserPreference($user->language);
                if ($preferredLanguage instanceof CmsLanguage && $currentLocale !== $preferredLanguage->code) {
                    session()->put('currentLocale', $preferredLanguage->code);
                    session()->put('currentLanguageId', $preferredLanguage->id);
                    session()->put('currentLanguageName', $preferredLanguage->name);
                    $currentLocale = $preferredLanguage->code;
                }
            }
            $requestedLocaleInput = $request->query("lang") ?: $this->hostRequestedLocale($request);
            $requestedLocaleInput = is_string($requestedLocaleInput) ? $requestedLocaleInput : null;

            // A language chosen in the picker is authoritative for this session.
            // Old pages in browser history may still carry a stale ?lang value.
            if ($requestedLocaleInput && !session()->get('currentLocaleManuallySelected') && $currentLocale != $requestedLocaleInput) {
                $requestedLocale = $this->translationLocale($requestedLocaleInput);
                $lang = $languageTableAvailable
                    ? $this->languageFromUserPreference($requestedLocale)
                    : null;

                if ($lang) {
                    session()->put("currentLocale", $lang->code);
                    session()->put("currentLanguageId", $lang->id);
                    session()->put("currentLanguageName", $lang->name);
                    $currentLocale = $lang->code;
                } elseif (!$languageTableAvailable && $this->hasStaticTranslationLocale($requestedLocale)) {
                    session()->put("currentLocale", $requestedLocale);
                    session()->put("currentLanguageName", strtoupper($requestedLocale));
                    $currentLocale = $requestedLocale;
                }
            }

            if (!session()->has("currentLocale")) {
                $userLang = $this->languageFromUserPreference($user?->language);

                if ($userLang instanceof CmsLanguage) {
                    session()->put("currentLocale", $userLang->code);
                    session()->put("currentLanguageId", $userLang->id);
                    session()->put("currentLanguageName", $userLang->name);
                    $currentLocale = $userLang->code;
                } else {
                    $browserLang = $this->browserRequestedLanguage($request);

                    if ($browserLang instanceof CmsLanguage) {
                        session()->put("currentLocale", $browserLang->code);
                        session()->put("currentLanguageId", $browserLang->id);
                        session()->put("currentLanguageName", $browserLang->name);
                        $currentLocale = $browserLang->code;
                    }

                    if (!session()->has("currentLocale") && $this->countryDefaults->hasMappings()) {
                        $geo ??= $this->geoIp->resolve($request);
                        $countryLang = $this->countryDefaults->languageForObservedCountry($geo->observedCountryCode);

                        if ($countryLang instanceof CmsLanguage) {
                            session()->put("currentLocale", $countryLang->code);
                            session()->put("currentLanguageId", $countryLang->id);
                            session()->put("currentLanguageName", $countryLang->name);
                            $currentLocale = $countryLang->code;
                        }
                    }
                }
            }

            if (!session()->has("currentLocale")) {
                if ($languageTableAvailable) {
                    $defaultLang = CmsLanguage::query()->select("id", "code", "name")
                        ->where('status', 1)->orderByDesc('is_default')->orderBy('id')->first();
                    if ($defaultLang) {
                        session()->put("currentLocale", $defaultLang->code);
                        session()->put("currentLanguageId", $defaultLang->id);
                        session()->put("currentLanguageName", $defaultLang->name);
                        $currentLocale = $defaultLang->code;
                    } else {
                        session()->forget(['currentLocale', 'currentLanguageId', 'currentLanguageName', 'currentLocaleManuallySelected']);
                        $currentLocale = $this->transientFallbackLocale();
                    }
                }

                if (!session()->has("currentLocale")) {
                    $currentLocale ??= $this->transientFallbackLocale();
                    if (!$languageTableAvailable) {
                        session()->put("currentLocale", $currentLocale);
                        session()->put("currentLanguageName", $currentLocale);
                    }
                }
            }

            app()->setLocale($this->translationLocale($currentLocale ?: session()->get("currentLocale")));
        } catch (Throwable $throwable) {
            session()->forget(['currentLocale', 'currentLanguageId', 'currentLanguageName', 'currentLocaleManuallySelected']);
            $currentLocale = $this->transientFallbackLocale();
            app()->setLocale($this->translationLocale($currentLocale));
        }

        $request->attributes->set('cms_locale_resolved', true);
    }

    public function handle(Request $request, Closure $next)
    {
        $this->resolveLocale($request);

        $user = $request->user("cms");

        $languages = $this->safeHasTable('cms_languages')
            ? CmsLanguage::query()->where("status", 1)->get()->toArray()
            : [];

        $menus = [];
        if ($this->safeHasTable('cms_menus')) {
            try {
                $menus = json_decode(CmsMenu::query()->where('name', 'header')->first()?->json ?? "[]", true);
            } catch (Throwable $throwable) {
                $menus = [];
            }
        }

        $currencyCode = "USD";
        $siteSetting = app()->bound('cms.siteSetting') ? app('cms.siteSetting') : null;
        if (!$siteSetting && $this->safeHasTable('cms_site_settings')) {
            try {
                $siteSetting = CmsSiteSetting::with("translation")->first();
                $siteSetting = app(CmsThemeSettingOverlay::class)->apply($siteSetting);
                app()->instance('cms.siteSetting', $siteSetting);
            } catch (Throwable $throwable) {
                $siteSetting = null;
            }
        }

        $languageId = (int) session('currentLanguageId');
        $loadedTranslation = $siteSetting?->getRelations()['translation'] ?? null;
        if ($siteSetting && $languageId > 0 && (int) ($loadedTranslation?->cms_language_id ?? 0) !== $languageId) {
            // The boot-time translation may have been selected before the session language was resolved.
            $siteSetting = clone $siteSetting;
            try {
                $siteSetting->load('translation');
                $siteSetting = app(CmsThemeSettingOverlay::class)->apply($siteSetting);
                app()->instance('cms.siteSetting', $siteSetting);
            } catch (Throwable) {
                $siteSetting->setRelation('translation', null);
            }
        }

        $currencies = collect();
        if ($this->safeHasTable('cms_currencies')) {
            $currencies = CmsCurrency::query()
                ->where("status", "active")
                ->orderBy("name", "ASC")
                ->get();
        }
        $registrationFiat = app(\App\Services\Wallet\EnabledWalletAssetCatalog::class)->fiat();
        $registrationCurrencies = $currencies->filter(
            static fn (CmsCurrency $currency) => in_array(strtoupper((string) $currency->code), $registrationFiat, true)
        )->values();

        $countries = $this->safeHasTable('cms_countries')
            ? CmsCountry::query()->orderBy("name", "ASC")->get()
            : collect();

        if($user && $user->currency){
            $currencyCode = $user->currency;
        }

        $timedPromotions = collect();
        $timedPromotion = null;
        if (
            $this->safeHasTable('cms_promotions')
            && $this->safeHasTable('cms_promotion_translations')
            && $this->safeHasColumn('cms_promotions', 'show_on_homepage_top')
        ) {
            $currentLanguageId = (int) (session('currentLanguageId') ?: 0);

            $timedPromotions = CmsPromotion::query()
                ->with('translations')
                ->publiclyVisible()
                ->where('show_on_homepage_top', 1)
                ->orderBy('rank', 'ASC')
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->get()
                ->map(fn (CmsPromotion $promotion) => $this->hydratePromotionDisplayCopy($promotion, $currentLanguageId))
                ->filter(fn (CmsPromotion $promotion) => filled($promotion->display_listing_title))
                ->values();
            $timedPromotion = $timedPromotions->first();
        }

        $userCurrency = $this->safeHasTable('cms_currencies')
            ? CmsCurrency::query()->where("code", $currencyCode)->first()
            : null;

        $topThreePromotions = collect();
        if ($this->safeHasTable('cms_promotions')) {
            $topThreePromotions = CmsPromotion::with('translation')
                ->whereHas('translation')
                ->where('is_permanent', 1)
                ->where('show_in_top_line', 1)
                ->where('status', 'active')
                ->orderBy('rank', 'ASC')
                ->orderByDesc('updated_at')
                ->limit(3)
                ->get();
        }

        $viewData = [
            "topThreePromotions" => $topThreePromotions,
            "activeLanguages" => $languages,
            "menus" => is_array($menus) ? $menus : [],
            "currentLocale" => session()->get("currentLocale"),
            "siteSetting" => $siteSetting,
            "userCurrency" => $userCurrency,
            "timedPromotions" => $timedPromotions,
            "timedPromotion" => $timedPromotion,
            "currencies" => $currencies,
            "registrationCurrencies" => $registrationCurrencies,
            "countries" => $countries,
        ];

        View::share($viewData);

        return $next($request);
    }

    private function transientFallbackLocale(): string
    {
        $locale = $this->translationLocale((string) config('app.fallback_locale', config('app.locale', 'en')));
        if (Cache::add('cms_language_configuration_unavailable_warning', true, now()->addMinutes(15))) {
            Log::warning('CMS language configuration unavailable; using transient application fallback.', ['locale' => $locale]);
        }
        return $locale;
    }

    private function hydratePromotionDisplayCopy(CmsPromotion $promotion, int $currentLanguageId): CmsPromotion
    {
        $translations = $promotion->relationLoaded('translations')
            ? $promotion->translations
            : collect();
        $promotion->display_listing_title = $this->localizedPromotionValue($translations, $currentLanguageId, ['listing_title', 'detail_title']);
        $promotion->display_detail_title = $this->localizedPromotionValue($translations, $currentLanguageId, ['detail_title', 'listing_title']);
        $promotion->display_short_description = $this->localizedPromotionValue($translations, $currentLanguageId, ['short_description']);
        $promotion->display_description = $this->localizedPromotionValue($translations, $currentLanguageId, ['description']);
        $promotion->display_redirect_button_text = $this->localizedPromotionValue($translations, $currentLanguageId, ['redirect_button_text']);

        return $promotion;
    }

    private function localizedPromotionValue($translations, int $languageId, array $fields): ?string
    {
        return $languageId > 0
            ? $this->firstFilledPromotionField($translations->firstWhere('cms_language_id', $languageId), $fields)
            : null;
    }

    private function firstFilledPromotionField($translation, array $fields): ?string
    {
        if (! $translation) {
            return null;
        }

        foreach ($fields as $field) {
            $value = trim((string) ($translation->{$field} ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function translationLocale(?string $locale): string
    {
        $locale = trim((string) ($locale ?: 'en'));

        if ($locale === '') {
            return 'en';
        }

        $canonicalLocale = str_replace('-', '_', $locale);
        $languageCode = strtolower(strtok($canonicalLocale, '_.') ?: $canonicalLocale);

        if ($languageCode !== '' && is_dir(resource_path("lang/$languageCode"))) {
            return $languageCode;
        }

        return $locale;
    }

    private function hasStaticTranslationLocale(?string $locale): bool
    {
        $locale = trim((string) ($locale ?: ''));

        return $locale !== '' && is_dir(resource_path("lang/$locale"));
    }

    private function browserRequestedLanguage(Request $request): ?CmsLanguage
    {
        if (! $this->safeHasTable('cms_languages')) {
            return null;
        }

        $candidates = $this->browserLanguageCandidates((string) $request->headers->get('Accept-Language', ''));

        if ($candidates === []) {
            return null;
        }

        $supportedBrowserFallbackCodes = $this->browserFallbackLocaleCodes();
        $candidates = array_values(array_filter($candidates, function (string $candidate) use ($supportedBrowserFallbackCodes): bool {
            return in_array($candidate, $supportedBrowserFallbackCodes, true);
        }));

        if ($candidates === []) {
            return null;
        }

        $languages = CmsLanguage::query()
            ->select('id', 'code', 'name')
            ->where('status', 1)
            ->whereIn('code', $candidates)
            ->get()
            ->keyBy('code');

        foreach ($candidates as $candidate) {
            $language = $languages->get($candidate);

            if ($language instanceof CmsLanguage) {
                return $language;
            }
        }

        return null;
    }

    private function languageFromUserPreference(?string $languageCode): ?CmsLanguage
    {
        if (! $this->safeHasTable('cms_languages')) {
            return null;
        }

        if (trim((string) $languageCode) === '') {
            return null;
        }
        $languageCode = $this->translationLocale(strtolower(trim((string) $languageCode)));
        $languageCode = match ($languageCode) {
            'ing' => 'en',
            'esp' => 'es',
            default => $languageCode,
        };

        if ($languageCode === '') {
            return null;
        }

        return CmsLanguage::query()
            ->select('id', 'code', 'name')
            ->where('status', 1)
            ->where('code', $languageCode)
            ->first();
    }

    private function browserLanguageCandidates(string $header): array
    {
        $weightedTags = [];

        foreach (explode(',', $header) as $index => $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            $segments = array_map('trim', explode(';', $part));
            $tag = strtolower(str_replace('_', '-', (string) ($segments[0] ?? '')));

            if ($tag === '' || $tag === '*') {
                continue;
            }

            if (! preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/', $tag)) {
                continue;
            }

            $quality = 1.0;

            foreach (array_slice($segments, 1) as $segment) {
                if (preg_match('/^q=(0(?:\.\d{0,3})?|1(?:\.0{0,3})?)$/', $segment, $matches)) {
                    $quality = max(0.0, min(1.0, (float) $matches[1]));
                    break;
                }
            }

            if ($quality <= 0.0) {
                continue;
            }

            $primary = strtok($tag, '-') ?: $tag;

            if (! preg_match('/^[a-z]{2,3}$/', $primary)) {
                continue;
            }

            $weightedTags[] = [
                'code' => $primary,
                'quality' => $quality,
                'index' => $index,
            ];
        }

        usort($weightedTags, function (array $left, array $right): int {
            $qualityCompare = $right['quality'] <=> $left['quality'];

            return $qualityCompare !== 0 ? $qualityCompare : ($left['index'] <=> $right['index']);
        });

        $candidates = [];

        foreach ($weightedTags as $tag) {
            if (! in_array($tag['code'], $candidates, true)) {
                $candidates[] = $tag['code'];
            }
        }

        return $candidates;
    }

    private function browserFallbackLocaleCodes(): array
    {
        $configuredLocales = config('cms_public.browser_fallback_locales', ['tr', 'en']);

        if (! is_array($configuredLocales)) {
            return ['tr', 'en'];
        }

        $locales = [];

        foreach ($configuredLocales as $locale) {
            $code = strtolower(trim((string) $locale));

            if ($code !== '' && preg_match('/^[a-z]{2,3}$/', $code) && ! in_array($code, $locales, true)) {
                $locales[] = $code;
            }
        }

        return $locales;
    }

    private function hostRequestedLocale(Request $request): ?string
    {
        $host = strtolower(trim((string) $request->getHost()));

        if ($host === '' || in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return null;
        }

        $label = strtok($host, '.') ?: '';

        if (!preg_match('/^[a-z]{2}(?:[-_][a-z]{2})?$/', $label)) {
            return null;
        }

        $locale = $this->translationLocale($label);

        if ($this->hasStaticTranslationLocale($locale)) {
            return $locale;
        }

        return $label;
    }
}
