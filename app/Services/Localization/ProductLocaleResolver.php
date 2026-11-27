<?php

namespace App\Services\Localization;

use App\Models\CmsLanguage;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ProductLocaleResolver
{
    /**
     * Map the locale already selected by the CMS to one integration capability.
     *
     * @return array{product:string,provider:string,effective_locale:string,provider_locale:string,locale_fallback_used:bool,locale_source:string}
     */
    public function resolve(
        string $product,
        string $provider,
        array $capability,
        ?string $effectiveLocale = null
    ): array {
        $effectiveLocale = $this->normalizeCmsLocale($effectiveLocale ?: app()->getLocale());
        $localeMap = $this->localeMap((array) ($capability['supported'] ?? []));
        $fallbackLocale = $this->fallbackLocale($localeMap, (string) ($capability['fallback'] ?? ''));
        $canUseEffectiveLocale = isset($localeMap[$effectiveLocale])
            && $this->isActiveCmsLocale($effectiveLocale);

        return [
            'product' => $this->safeIdentity($product, 'unknown'),
            'provider' => $this->safeIdentity($provider, 'unknown'),
            'effective_locale' => $effectiveLocale,
            'provider_locale' => $canUseEffectiveLocale ? $localeMap[$effectiveLocale] : $fallbackLocale,
            'locale_fallback_used' => !$canUseEffectiveLocale,
            'locale_source' => 'cms_effective_locale',
        ];
    }

    private function localeMap(array $supported): array
    {
        $map = [];

        foreach ($supported as $cmsLocale => $providerLocale) {
            if (is_int($cmsLocale)) {
                $cmsLocale = (string) $providerLocale;
            }

            $cmsLocale = $this->normalizeCmsLocale((string) $cmsLocale);
            $providerLocale = $this->normalizeProviderLocale((string) $providerLocale);

            if ($cmsLocale !== '' && $providerLocale !== '') {
                $map[$cmsLocale] = $providerLocale;
            }
        }

        return $map;
    }

    private function fallbackLocale(array $localeMap, string $configuredFallback): string
    {
        $configuredFallback = $this->normalizeCmsLocale($configuredFallback);

        if ($configuredFallback !== '' && isset($localeMap[$configuredFallback])) {
            return $localeMap[$configuredFallback];
        }

        return (string) (reset($localeMap) ?: '');
    }

    private function isActiveCmsLocale(string $locale): bool
    {
        try {
            if (!Schema::hasTable('cms_languages')) {
                return true;
            }

            return CmsLanguage::query()
                ->where('status', 1)
                ->whereRaw('LOWER(code) = ?', [$locale])
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }

    private function normalizeCmsLocale(?string $locale): string
    {
        $locale = strtolower(trim((string) $locale));
        $locale = str_replace('_', '-', $locale);
        $language = strtok($locale, '-');

        return is_string($language) && preg_match('/^[a-z]{2,5}$/', $language)
            ? $language
            : '';
    }

    private function normalizeProviderLocale(string $locale): string
    {
        $locale = strtolower(trim($locale));

        return preg_match('/^[a-z0-9_-]{2,16}$/', $locale) ? $locale : '';
    }

    private function safeIdentity(string $value, string $fallback): string
    {
        $value = strtolower(trim($value));

        return preg_match('/^[a-z0-9_-]{1,80}$/', $value) ? $value : $fallback;
    }
}
