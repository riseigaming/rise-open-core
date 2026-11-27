<?php

namespace App\Services\Localization;

use Illuminate\Translation\Translator;
use InvalidArgumentException;

class LocalizedCopyResolver
{
    private const DOMAINS = ['legal'];

    public function __construct(private readonly Translator $translator)
    {
    }

    /**
     * Resolve copy from the locale already selected by CmsLanguageMiddleware.
     *
     * @param array<string, mixed> $context
     * @return array{copy:mixed,copy_domain:string,copy_key:string,effective_locale:string,resolved_locale:?string,fallback_used:bool,source:string,country:?string,provider:?string,found:bool}
     */
    public function resolve(
        string $domain,
        string $key,
        ?string $effectiveLocale = null,
        array $context = []
    ): array {
        $domain = strtolower(trim($domain));
        $key = strtolower(trim($key));

        if (!in_array($domain, self::DOMAINS, true)) {
            throw new InvalidArgumentException('Unsupported localized copy domain.');
        }

        if (!preg_match('/^[a-z0-9][a-z0-9._-]{0,119}$/', $key)) {
            throw new InvalidArgumentException('Invalid localized copy key.');
        }

        $effectiveLocale = $this->normalizeLocale($effectiveLocale ?: app()->getLocale())
            ?: $this->normalizeLocale((string) config('app.locale', 'en'))
            ?: 'en';
        $fallbackLocale = $this->normalizeLocale((string) config(
            'localized_copy.fallback_locale',
            config('app.fallback_locale', 'en')
        )) ?: 'en';
        $country = $this->normalizeCountry((string) ($context['country'] ?? ''));
        $provider = $this->normalizeProvider((string) ($context['provider'] ?? ''));
        $replace = is_array($context['replace'] ?? null) ? $context['replace'] : [];

        foreach ($this->candidates(
            $domain,
            $key,
            $effectiveLocale,
            $fallbackLocale,
            $country,
            $provider,
            $replace,
            $context
        ) as $candidate) {
            if (!$this->validCopy($candidate['copy'] ?? null)) {
                continue;
            }

            return $this->result(
                $candidate['copy'],
                $domain,
                $key,
                $effectiveLocale,
                (string) $candidate['locale'],
                (string) $candidate['source'],
                $country,
                $provider,
                true
            );
        }

        $default = $context['default'] ?? null;

        return $this->result(
            $this->validCopy($default) ? $default : null,
            $domain,
            $key,
            $effectiveLocale,
            null,
            $this->validCopy($default) ? 'existing_default' : 'missing',
            $country,
            $provider,
            false
        );
    }

    /** @return array<int, array{copy:mixed,locale:string,source:string}> */
    private function candidates(
        string $domain,
        string $key,
        string $effectiveLocale,
        string $fallbackLocale,
        ?string $country,
        ?string $provider,
        array $replace,
        array $context
    ): array {
        return $this->legalCandidates($key, $effectiveLocale, $fallbackLocale, $country, $replace, $context);
    }

    private function legalCandidates(
        string $key,
        string $effectiveLocale,
        string $fallbackLocale,
        ?string $country,
        array $replace,
        array $context
    ): array {
        $candidates = [];

        if ($country !== null) {
            $candidates = array_merge(
                $candidates,
                $this->legalCountryCandidatesForLocale($key, $country, $effectiveLocale, $replace, $context)
            );

            if ($effectiveLocale !== $fallbackLocale) {
                $candidates = array_merge(
                    $candidates,
                    $this->legalCountryCandidatesForLocale($key, $country, $fallbackLocale, $replace, $context)
                );
            }
        }

        $candidates = array_merge(
            $candidates,
            $this->legalGlobalCandidatesForLocale($key, $effectiveLocale, $replace, $context)
        );

        if ($effectiveLocale !== $fallbackLocale) {
            $candidates = array_merge(
                $candidates,
                $this->legalGlobalCandidatesForLocale($key, $fallbackLocale, $replace, $context)
            );
        }

        return $candidates;
    }

    private function legalCountryCandidatesForLocale(
        string $key,
        string $country,
        string $locale,
        array $replace,
        array $context
    ): array {
        return array_values(array_filter([
            $this->variantCandidate(
                data_get($context, 'country_variants.' . $country, []),
                $locale,
                'country_variant'
            ),
            $this->translationCandidate(
                'localized_copy.legal.countries.' . $country . '.' . $key,
                $locale,
                'country_translation',
                $replace
            ),
        ]));
    }

    private function legalGlobalCandidatesForLocale(
        string $key,
        string $locale,
        array $replace,
        array $context
    ): array {
        return array_values(array_filter([
            $this->variantCandidate((array) ($context['variants'] ?? []), $locale, 'global_variant'),
            $this->translationCandidate(
                'localized_copy.legal.global.' . $key,
                $locale,
                'global_translation',
                $replace
            ),
            $this->aliasCandidate('legal', $key, $locale, $replace),
        ]));
    }





    private function aliasCandidate(string $domain, string $key, string $locale, array $replace): ?array
    {
        $translationKey = config('localized_copy.aliases.' . $domain . '.' . $key);

        return is_string($translationKey) && $translationKey !== ''
            ? $this->translationCandidate($translationKey, $locale, 'existing_translation', $replace)
            : null;
    }

    private function translationCandidate(string $key, string $locale, string $source, array $replace): ?array
    {
        if (!$this->translator->has($key, $locale, false)) {
            return null;
        }

        return [
            'copy' => $this->translator->get($key, $replace, $locale, false),
            'locale' => $locale,
            'source' => $source,
        ];
    }

    private function variantCandidate(array $variants, string $locale, string $source): ?array
    {
        if (!array_key_exists($locale, $variants)) {
            return null;
        }

        return [
            'copy' => $variants[$locale],
            'locale' => $locale,
            'source' => $source,
        ];
    }

    private function result(
        mixed $copy,
        string $domain,
        string $key,
        string $effectiveLocale,
        ?string $resolvedLocale,
        string $source,
        ?string $country,
        ?string $provider,
        bool $found
    ): array {
        return [
            'copy' => $copy,
            'copy_domain' => $domain,
            'copy_key' => $key,
            'effective_locale' => $effectiveLocale,
            'resolved_locale' => $resolvedLocale,
            'fallback_used' => !$found || $resolvedLocale !== $effectiveLocale,
            'source' => $source,
            'country' => $country,
            'provider' => $provider,
            'found' => $found,
        ];
    }

    private function validCopy(mixed $copy): bool
    {
        return (is_string($copy) && trim($copy) !== '') || (is_array($copy) && $copy !== []);
    }

    private function normalizeLocale(string $locale): string
    {
        $locale = strtolower(str_replace('_', '-', trim($locale)));
        $language = strtok($locale, '-');

        return is_string($language) && preg_match('/^[a-z]{2,5}$/', $language) ? $language : '';
    }

    private function normalizeCountry(string $country): ?string
    {
        $country = strtoupper(trim($country));

        return preg_match('/^[A-Z]{2}$/', $country) ? $country : null;
    }

    private function normalizeProvider(string $provider): ?string
    {
        $provider = strtolower(trim($provider));
        $provider = preg_replace('/[^a-z0-9_-]+/', '_', $provider) ?: '';

        return $provider !== '' ? substr($provider, 0, 80) : null;
    }
}
