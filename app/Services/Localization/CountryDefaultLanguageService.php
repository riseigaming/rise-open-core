<?php

namespace App\Services\Localization;

use App\Models\CmsCountry;
use App\Models\CmsLanguage;
use App\Services\Geo\CmsCountryCodeResolver;
use App\SiteSettings;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class CountryDefaultLanguageService
{
    public const SETTINGS_KEY = 'country_default_languages_v1';
    public const SETTINGS_VERSION = 1;

    public function __construct(
        private readonly CmsCountryCodeResolver $countryCodes
    ) {
    }

    public function storageAvailable(): bool
    {
        return $this->hasTable('site_settings');
    }

    public function settings(): array
    {
        if (!$this->storageAvailable()) {
            return $this->normalize([]);
        }

        try {
            $raw = (string) SiteSettings::query()
                ->where('key', self::SETTINGS_KEY)
                ->value('value');
            $decoded = json_decode($raw, true);

            return $this->normalize(is_array($decoded) ? $decoded : []);
        } catch (Throwable) {
            return $this->normalize([]);
        }
    }

    public function hasMappings(): bool
    {
        return $this->settings()['countries'] !== [];
    }

    public function languageForObservedCountry(?string $countryCode): ?CmsLanguage
    {
        try {
            $countryId = $this->countryCodes->resolveCountryId($countryCode);
            if ($countryId === null || !$this->hasTable('cms_languages')) {
                return null;
            }

            $languageCode = (string) data_get($this->settings(), "countries.{$countryId}.language", '');
            if ($languageCode === '') {
                return null;
            }

            return CmsLanguage::query()
                ->select('id', 'code', 'name')
                ->where('status', 1)
                ->where('code', $languageCode)
                ->first();
        } catch (Throwable) {
            return null;
        }
    }

    public function summary(): array
    {
        $settings = $this->settings();
        $languages = $this->activeLanguages();
        $languageLabels = collect($languages)->pluck('name', 'code');
        $countries = collect($this->availableCountries())->keyBy('id');
        $entries = [];

        foreach ($settings['countries'] as $countryId => $mapping) {
            $country = $countries->get((int) $countryId, [
                'id' => (int) $countryId,
                'label' => 'Country #' . (int) $countryId,
                'code' => null,
            ]);
            $languageCode = (string) ($mapping['language'] ?? '');

            $entries[] = [
                'country_id' => (int) $countryId,
                'country_label' => $country['label'],
                'country_code' => $country['code'],
                'language_code' => $languageCode,
                'language_name' => $languageLabels->get($languageCode, strtoupper($languageCode)),
                'active' => $languageLabels->has($languageCode),
                'updated_at' => $mapping['updated_at'] ?? null,
                'updated_by' => $mapping['updated_by'] ?? null,
            ];
        }

        usort($entries, fn (array $left, array $right): int => strnatcasecmp($left['country_label'], $right['country_label']));

        return [
            'storage_available' => $this->storageAvailable(),
            'settings_key' => self::SETTINGS_KEY,
            'version' => self::SETTINGS_VERSION,
            'countries' => $countries->values()->all(),
            'languages' => $languages,
            'entries' => $entries,
        ];
    }

    public function setCountryDefault(int $countryId, string $languageCode, ?int $actorId = null): array
    {
        $this->assertStorageAvailable();
        $this->assertCountryExists($countryId);
        $language = $this->activeLanguage($languageCode);

        if (!$language instanceof CmsLanguage) {
            throw new InvalidArgumentException('Only an active CMS language can be assigned.');
        }

        $settings = $this->settings();
        $settings['countries'][(string) $countryId] = [
            'language' => strtolower((string) $language->code),
            'updated_at' => now()->toDateTimeString(),
            'updated_by' => $actorId,
        ];

        return $this->persist($settings);
    }

    public function removeCountryDefault(int $countryId): array
    {
        $this->assertStorageAvailable();
        $settings = $this->settings();

        if (!array_key_exists((string) $countryId, $settings['countries'])) {
            throw new InvalidArgumentException('Country default language mapping was not found.');
        }

        unset($settings['countries'][(string) $countryId]);

        return $this->persist($settings);
    }

    private function persist(array $settings): array
    {
        $settings = $this->normalize($settings);

        SiteSettings::query()->updateOrCreate(
            ['key' => self::SETTINGS_KEY],
            ['value' => json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]
        );
        Session::forget('settings');

        return $settings;
    }

    private function normalize(array $settings): array
    {
        $countries = [];

        foreach ((array) ($settings['countries'] ?? []) as $countryId => $mapping) {
            $countryId = (int) $countryId;
            $languageCode = strtolower(trim((string) data_get($mapping, 'language', '')));

            if ($countryId <= 0 || !preg_match('/^[a-z]{2,5}$/', $languageCode)) {
                continue;
            }

            $countries[(string) $countryId] = [
                'language' => $languageCode,
                'updated_at' => data_get($mapping, 'updated_at'),
                'updated_by' => data_get($mapping, 'updated_by') !== null
                    ? (int) data_get($mapping, 'updated_by')
                    : null,
            ];
        }

        ksort($countries, SORT_NUMERIC);

        return [
            'version' => self::SETTINGS_VERSION,
            'countries' => $countries,
        ];
    }

    private function activeLanguages(): array
    {
        if (!$this->hasTable('cms_languages')) {
            return [];
        }

        try {
            return CmsLanguage::query()
                ->where('status', 1)
                ->orderBy('name')
                ->get(['id', 'name', 'code'])
                ->map(fn (CmsLanguage $language): array => [
                    'id' => (int) $language->id,
                    'name' => (string) $language->name,
                    'code' => strtolower((string) $language->code),
                ])
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    private function activeLanguage(string $languageCode): ?CmsLanguage
    {
        if (!$this->hasTable('cms_languages')) {
            return null;
        }

        $languageCode = strtolower(trim($languageCode));
        if ($languageCode === '' || !preg_match('/^[a-z]{2,5}$/', $languageCode)) {
            return null;
        }

        return CmsLanguage::query()
            ->where('status', 1)
            ->where('code', $languageCode)
            ->first();
    }

    private function availableCountries(): array
    {
        if (!$this->hasTable('cms_countries')) {
            return [];
        }

        try {
            return CmsCountry::query()
                ->get()
                ->map(function (CmsCountry $country): array {
                    $code = trim((string) ($country->iso2 ?? $country->iso_code_2 ?? $country->iso3 ?? $country->iso_code_3 ?? $country->code ?? ''));
                    $label = trim((string) ($country->name ?? ''));

                    return [
                        'id' => (int) $country->id,
                        'label' => $label !== '' ? $label : ($code !== '' ? strtoupper($code) : 'Country #' . $country->id),
                        'code' => $code !== '' ? strtoupper($code) : null,
                    ];
                })
                ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
                ->values()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    private function assertCountryExists(int $countryId): void
    {
        if ($countryId <= 0 || !$this->hasTable('cms_countries') || !CmsCountry::query()->whereKey($countryId)->exists()) {
            throw new InvalidArgumentException('Country was not found.');
        }
    }

    private function assertStorageAvailable(): void
    {
        if (!$this->storageAvailable()) {
            throw new RuntimeException('Country default language storage is unavailable.');
        }
    }

    private function hasTable(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (Throwable) {
            return false;
        }
    }
}
