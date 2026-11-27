<?php

namespace App\Betting\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SportsbookCatalogService
{
    private SportsbookAssetResolver $assetResolver;

    private ?array $regionsCache = null;

    private ?array $countriesCache = null;

    private ?array $competitionsCache = null;

    private ?array $teamsCache = null;

    private ?array $competitionAliasMapCache = null;

    private ?array $teamAliasMapCache = null;

    private ?array $catalogWriteStateCache = null;

    private array $dbAliasesCache = [];

    private const COMPETITION_COLUMNS = [
        'canonical_competition_key',
        'competition_key',
        'sport_group_key',
        'region_key',
        'country_key',
        'label',
        'short_label',
        'tier',
        'identity_tier',
        'public_surface',
        'priority',
        'visibility',
        'shell_enabled',
        'source_status',
        'source_urls',
        'asset_meta',
        'meta',
    ];

    private const TEAM_COLUMNS = [
        'canonical_team_key',
        'team_key',
        'sport_group_key',
        'country_key',
        'label',
        'short_label',
        'tier',
        'visibility',
        'competition_keys',
        'source_status',
        'source_urls',
        'asset_meta',
        'meta',
    ];

    private const COUNTRY_COLUMNS = [
        'country_key',
        'region_key',
        'iso2',
        'label',
        'priority',
        'visibility',
        'asset_meta',
        'source_status',
        'source_urls',
    ];

    public function __construct(?SportsbookAssetResolver $assetResolver = null)
    {
        $this->assetResolver = $assetResolver ?: app(SportsbookAssetResolver::class);
    }

    public function sports(): array
    {
        return collect((array) config('sportsbook_catalog.sports', []))
            ->mapWithKeys(function (array $sport, string $sportGroupKey): array {
                return [
                    $sportGroupKey => $this->withAssetDescriptor(
                        $sport,
                        'sport',
                        $sportGroupKey,
                        (string) ($sport['label'] ?? $sportGroupKey)
                    ),
                ];
            })
            ->all();
    }

    public function regions(): array
    {
        if ($this->regionsCache !== null) {
            return $this->regionsCache;
        }

        $rows = $this->mergeConfigWithRows(
            (array) config('sportsbook_catalog.regions', []),
            'betting_catalog_regions',
            'region_key',
            ['asset_meta', 'source_urls']
        );

        return $this->regionsCache ??= $this->withAssetDescriptors($rows, 'region');
    }

    public function countries(): array
    {
        if ($this->countriesCache !== null) {
            return $this->countriesCache;
        }

        $rows = $this->mergeConfigWithRows(
            (array) config('sportsbook_catalog.countries', []),
            'betting_catalog_countries',
            'country_key',
            ['asset_meta', 'source_urls']
        );

        // A partial DB record must not erase an exact configured country code.
        foreach ($rows as $countryKey => &$country) {
            if (trim((string) ($country['iso2'] ?? '')) !== '') {
                continue;
            }

            $configuredCode = (string) config('sportsbook_catalog.countries.' . $countryKey . '.iso2', '');
            if (preg_match('/^[A-Z]{2}$/', $configuredCode) === 1) {
                $country['iso2'] = $configuredCode;
            }
        }
        unset($country);

        return $this->countriesCache ??= $this->withAssetDescriptors($rows, 'country');
    }

    public function competitions(): array
    {
        if ($this->competitionsCache !== null) {
            return $this->competitionsCache;
        }

        $configRows = collect((array) config('sportsbook_catalog.competitions', []))
            ->mapWithKeys(function (array $row, string $canonicalKey): array {
                return [$canonicalKey => $this->normalizeCompetition($canonicalKey, $row)];
            })
            ->all();

        $rows = $this->mergeConfigWithRows(
            $configRows,
            'betting_catalog_competitions',
            'canonical_competition_key',
            ['source_urls', 'asset_meta', 'meta']
        );

        return $this->competitionsCache = collect($rows)
            ->mapWithKeys(function (array $row, string $canonicalKey): array {
                $row = $this->normalizeCompetition($canonicalKey, $row);

                return [
                    $canonicalKey => $this->withAssetDescriptor(
                        $row,
                        'competition',
                        $canonicalKey,
                        (string) ($row['label'] ?? $canonicalKey)
                    ),
                ];
            })
            ->all();
    }

    public function teams(): array
    {
        if ($this->teamsCache !== null) {
            return $this->teamsCache;
        }

        $configRows = collect((array) config('sportsbook_catalog.teams', []))
            ->mapWithKeys(function (array $row, string $canonicalKey): array {
                return [$canonicalKey => $this->normalizeTeam($canonicalKey, $row)];
            })
            ->all();

        $rows = $this->mergeConfigWithRows(
            $configRows,
            'betting_catalog_teams',
            'canonical_team_key',
            ['competition_keys', 'source_urls', 'asset_meta', 'meta']
        );

        return $this->teamsCache = collect($rows)
            ->mapWithKeys(function (array $row, string $canonicalKey): array {
                $row = $this->normalizeTeam($canonicalKey, $row);

                return [
                    $canonicalKey => $this->withAssetDescriptor(
                        $row,
                        'team',
                        $canonicalKey,
                        (string) ($row['label'] ?? $canonicalKey)
                    ),
                ];
            })
            ->all();
    }

    public function competition(string $canonicalKey): ?array
    {
        $competitions = $this->competitions();

        return $competitions[$canonicalKey] ?? null;
    }

    public function team(string $canonicalKey): ?array
    {
        $teams = $this->teams();

        return $teams[$canonicalKey] ?? null;
    }

    public function country(string $countryKey): ?array
    {
        $countries = $this->countries();

        return $countries[$countryKey] ?? null;
    }

    public function competitionByAlias(string $sportGroupKey, string $countryKey, string $rawLabel, array $context = []): ?array
    {
        $canonicalKey = $this->lookupAlias(
            $this->competitionAliasMap(),
            $sportGroupKey,
            $countryKey,
            $rawLabel,
            (string) ($context['provider_key'] ?? ''),
            (string) ($context['connector_key'] ?? '')
        );

        return $canonicalKey !== '' ? $this->competition($canonicalKey) : null;
    }

    public function teamByAlias(string $sportGroupKey, string $countryKey, string $rawLabel, array $context = []): ?array
    {
        $canonicalKey = $this->lookupAlias(
            $this->teamAliasMap(),
            $sportGroupKey,
            $countryKey,
            $rawLabel,
            (string) ($context['provider_key'] ?? ''),
            (string) ($context['connector_key'] ?? '')
        );

        return $canonicalKey !== '' ? $this->team($canonicalKey) : null;
    }

    public function competitionAliasMap(): array
    {
        if ($this->competitionAliasMapCache !== null) {
            return $this->competitionAliasMapCache;
        }

        $aliases = [];
        $competitions = $this->competitions();

        foreach ($competitions as $canonicalKey => $definition) {
            $sport = (string) ($definition['sport_group_key'] ?? '');
            $country = (string) ($definition['country_key'] ?? '');
            $competition = (string) ($definition['competition_key'] ?? '');
            $legacyKey = (string) ($definition['legacy_key'] ?? $this->legacyCompetitionKey($country, $competition));

            foreach (array_filter(array_merge([
                $canonicalKey,
                $legacyKey,
                $competition,
                (string) ($definition['label'] ?? ''),
                trim($this->countryLabel($country) . ' ' . (string) ($definition['label'] ?? '')),
            ], (array) ($definition['aliases'] ?? []))) as $alias) {
                $this->addAlias($aliases, 'competition', $canonicalKey, $sport, $country, (string) $alias);
            }
        }

        foreach ((array) config('betting.canonical_identity.competition_aliases', []) as $alias => $canonicalKey) {
            if (isset($competitions[(string) $canonicalKey])) {
                $definition = $competitions[(string) $canonicalKey];
                $this->addAlias(
                    $aliases,
                    'competition',
                    (string) $canonicalKey,
                    (string) ($definition['sport_group_key'] ?? ''),
                    (string) ($definition['country_key'] ?? ''),
                    (string) $alias
                );
            }
        }

        foreach ($this->dbAliases('competition') as $alias) {
            $canonicalKey = (string) ($alias['canonical_key'] ?? '');
            if ($canonicalKey === '' || !isset($competitions[$canonicalKey])) {
                continue;
            }

            $definition = $competitions[$canonicalKey];
            $this->addAlias(
                $aliases,
                'competition',
                $canonicalKey,
                (string) ($definition['sport_group_key'] ?? ''),
                (string) ($definition['country_key'] ?? ''),
                (string) ($alias['alias_value'] ?? ''),
                (string) ($alias['provider_key'] ?? ''),
                (string) ($alias['connector_key'] ?? '')
            );
        }

        return $this->competitionAliasMapCache = $aliases;
    }

    public function teamAliasMap(): array
    {
        if ($this->teamAliasMapCache !== null) {
            return $this->teamAliasMapCache;
        }

        $aliases = [];
        $teams = $this->teams();

        foreach ($teams as $canonicalKey => $definition) {
            $sport = (string) ($definition['sport_group_key'] ?? '');
            $country = (string) ($definition['country_key'] ?? '');
            $team = (string) ($definition['team_key'] ?? '');

            foreach (array_filter(array_merge([
                $canonicalKey,
                $team,
                (string) ($definition['label'] ?? ''),
            ], (array) ($definition['aliases'] ?? []))) as $alias) {
                $this->addAlias($aliases, 'team', $canonicalKey, $sport, $country, (string) $alias);
            }
        }

        foreach ($this->dbAliases('team') as $alias) {
            $canonicalKey = (string) ($alias['canonical_key'] ?? '');
            if ($canonicalKey === '' || !isset($teams[$canonicalKey])) {
                continue;
            }

            $definition = $teams[$canonicalKey];
            $this->addAlias(
                $aliases,
                'team',
                $canonicalKey,
                (string) ($definition['sport_group_key'] ?? ''),
                (string) ($definition['country_key'] ?? ''),
                (string) ($alias['alias_value'] ?? ''),
                (string) ($alias['provider_key'] ?? ''),
                (string) ($alias['connector_key'] ?? '')
            );
        }

        return $this->teamAliasMapCache = $aliases;
    }

    public function countryAliases(): array
    {
        $aliases = (array) config('betting.canonical_identity.country_aliases', []);

        foreach ($this->countries() as $countryKey => $country) {
            $aliases[$countryKey] = $countryKey;
            $labelKey = $this->normalizeKey((string) ($country['label'] ?? ''));
            if ($labelKey !== '') {
                $aliases[$labelKey] = $countryKey;
            }
        }

        return array_map(fn ($value): string => $this->normalizeKey((string) $value), $aliases);
    }

    public function countryLabel(string $countryKey): string
    {
        $countries = $this->countries();

        return (string) data_get($countries, $countryKey . '.label', ucfirst(str_replace('_', ' ', $countryKey)));
    }

    public function catalogWriteState(): array
    {
        if ($this->catalogWriteStateCache !== null) {
            return $this->catalogWriteStateCache;
        }

        $required = [
            'betting_catalog_countries' => ['country_key', 'asset_meta'],
            'betting_catalog_competitions' => ['canonical_competition_key', 'public_surface'],
            'betting_catalog_teams' => ['canonical_team_key', 'competition_keys'],
            'betting_catalog_aliases' => ['aliasable_type', 'canonical_key', 'normalized_alias'],
        ];
        $missing = [];

        foreach ($required as $table => $columns) {
            if (!Schema::hasTable($table)) {
                $missing[] = $table;
                continue;
            }

            foreach ($columns as $column) {
                if (!Schema::hasColumn($table, $column)) {
                    $missing[] = $table . '.' . $column;
                }
            }
        }

        return $this->catalogWriteStateCache = [
            'enabled' => $missing === [],
            'missing' => $missing,
        ];
    }

    public function saveCompetition(string $canonicalKey, array $payload, array $aliases, ?int $cmsUserId = null): void
    {
        if (!$this->catalogWriteState()['enabled']) {
            return;
        }

        $definition = $this->normalizeCompetition($canonicalKey, array_merge($this->competition($canonicalKey) ?? [], $payload));
        $this->upsertRow('betting_catalog_competitions', 'canonical_competition_key', $canonicalKey, $definition, self::COMPETITION_COLUMNS, ['source_urls', 'asset_meta', 'meta']);
        $this->replaceAliases('competition', $canonicalKey, $aliases, $cmsUserId);
        $this->flushCaches();
    }

    public function saveTeam(string $canonicalKey, array $payload, array $aliases, ?int $cmsUserId = null): void
    {
        if (!$this->catalogWriteState()['enabled']) {
            return;
        }

        $definition = $this->normalizeTeam($canonicalKey, array_merge($this->team($canonicalKey) ?? [], $payload));
        $this->upsertRow('betting_catalog_teams', 'canonical_team_key', $canonicalKey, $definition, self::TEAM_COLUMNS, ['competition_keys', 'source_urls', 'asset_meta', 'meta']);
        $this->replaceAliases('team', $canonicalKey, $aliases, $cmsUserId);
        $this->flushCaches();
    }

    public function saveCountry(string $countryKey, array $payload, ?int $cmsUserId = null): void
    {
        if (!$this->catalogWriteState()['enabled']) {
            return;
        }

        $definition = $this->normalizeCountry($countryKey, array_merge($this->country($countryKey) ?? [], $payload));
        $this->upsertRow('betting_catalog_countries', 'country_key', $countryKey, $definition, self::COUNTRY_COLUMNS, ['source_urls', 'asset_meta']);
        $this->flushCaches();
    }

    public function saveAssetMeta(string $type, string $canonicalKey, array $assetMeta, ?int $cmsUserId = null): void
    {
        if (!$this->catalogWriteState()['enabled']) {
            return;
        }

        if ($type === 'team') {
            $definition = $this->normalizeTeam($canonicalKey, array_merge($this->team($canonicalKey) ?? [], ['asset_meta' => $assetMeta]));
            $this->upsertAssetMeta('betting_catalog_teams', 'canonical_team_key', $canonicalKey, $definition, self::TEAM_COLUMNS);
        } elseif ($type === 'federation') {
            $definition = $this->normalizeCountry($canonicalKey, array_merge($this->country($canonicalKey) ?? [], ['asset_meta' => $assetMeta]));
            $this->upsertAssetMeta('betting_catalog_countries', 'country_key', $canonicalKey, $definition, self::COUNTRY_COLUMNS);
        } else {
            $definition = $this->normalizeCompetition($canonicalKey, array_merge($this->competition($canonicalKey) ?? [], ['asset_meta' => $assetMeta]));
            $this->upsertAssetMeta('betting_catalog_competitions', 'canonical_competition_key', $canonicalKey, $definition, self::COMPETITION_COLUMNS);
        }

        $this->flushCaches();
    }

    public function aliasesFor(string $type, string $canonicalKey): array
    {
        $definitions = $type === 'team' ? $this->teams() : $this->competitions();
        $definition = $definitions[$canonicalKey] ?? [];
        $configAliases = (array) ($definition['aliases'] ?? []);
        $dbAliases = collect($this->dbAliases($type))
            ->where('canonical_key', $canonicalKey)
            ->pluck('alias_value')
            ->filter()
            ->values()
            ->all();

        return collect(array_merge($configAliases, $dbAliases))->unique()->values()->all();
    }

    public function normalizeKey(string $value): string
    {
        return trim(Str::slug(Str::ascii(trim($value)), '_'));
    }

    private function mergeConfigWithRows(array $configRows, string $table, string $keyColumn, array $jsonColumns = []): array
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $keyColumn)) {
            return $configRows;
        }

        $rows = DB::table($table)->get()
            ->mapWithKeys(function ($row) use ($keyColumn, $jsonColumns): array {
                $array = (array) $row;
                foreach ($jsonColumns as $column) {
                    $array[$column] = $this->decodeJsonValue($array[$column] ?? null);
                }

                unset($array['id'], $array['created_at'], $array['updated_at']);

                return [(string) ($array[$keyColumn] ?? '') => $array];
            })
            ->filter(fn (array $row, string $key): bool => $key !== '')
            ->all();

        return collect($configRows)
            ->mergeRecursive([])
            ->mapWithKeys(fn (array $row, string $key): array => [$key => array_merge($row, $rows[$key] ?? [])])
            ->union($rows)
            ->all();
    }

    private function withAssetDescriptors(array $rows, string $entityType): array
    {
        return collect($rows)
            ->mapWithKeys(function (array $row, string $key) use ($entityType): array {
                return [
                    $key => $this->withAssetDescriptor(
                        $row,
                        $entityType,
                        $key,
                        (string) ($row['label'] ?? $key)
                    ),
                ];
            })
            ->all();
    }

    private function withAssetDescriptor(array $row, string $entityType, string $canonicalKey, string $label): array
    {
        $row['asset_meta'] = $this->assetResolver->resolve(
            $entityType,
            $canonicalKey,
            $label,
            (array) ($row['asset_meta'] ?? [])
        );
        $row['asset_status'] = (string) ($row['asset_meta']['status'] ?? 'placeholder');

        return $row;
    }

    private function dbAliases(string $type): array
    {
        if (array_key_exists($type, $this->dbAliasesCache)) {
            return $this->dbAliasesCache[$type];
        }

        if (!Schema::hasTable('betting_catalog_aliases')) {
            return $this->dbAliasesCache[$type] = [];
        }

        return $this->dbAliasesCache[$type] = DB::table('betting_catalog_aliases')
            ->where('aliasable_type', $type)
            ->where('is_active', true)
            ->get()
            ->map(fn ($row): array => (array) $row)
            ->all();
    }

    private function flushCaches(): void
    {
        $this->regionsCache = null;
        $this->countriesCache = null;
        $this->competitionsCache = null;
        $this->teamsCache = null;
        $this->competitionAliasMapCache = null;
        $this->teamAliasMapCache = null;
        $this->catalogWriteStateCache = null;
        $this->dbAliasesCache = [];
    }

    private function addAlias(array &$aliases, string $type, string $canonicalKey, string $sport, string $country, string $alias, string $providerKey = '', string $connectorKey = ''): void
    {
        $normalized = $this->normalizeKey($alias);
        if ($normalized === '') {
            return;
        }

        $providerPrefix = $providerKey !== '' || $connectorKey !== ''
            ? $providerKey . '|' . $connectorKey . '|'
            : '';

        foreach (array_filter([
            $sport !== '' && $country !== '' ? $sport . ':' . $country . ':' . $normalized : '',
            $sport !== '' ? $sport . ':' . $normalized : '',
            $normalized,
        ]) as $candidate) {
            $aliases[$providerPrefix . $candidate] = $canonicalKey;
            if ($providerPrefix !== '') {
                $aliases[$candidate] = $aliases[$candidate] ?? $canonicalKey;
            }
        }
    }

    private function lookupAlias(array $aliases, string $sportGroupKey, string $countryKey, string $rawLabel, string $providerKey = '', string $connectorKey = ''): string
    {
        $normalized = $this->normalizeKey($rawLabel);
        if ($normalized === '') {
            return '';
        }

        $candidates = array_values(array_filter([
            $sportGroupKey !== '' && $countryKey !== '' ? $sportGroupKey . ':' . $countryKey . ':' . $normalized : '',
            $sportGroupKey !== '' ? $sportGroupKey . ':' . $normalized : '',
            $normalized,
        ]));
        $providerPrefix = $providerKey !== '' || $connectorKey !== '' ? $providerKey . '|' . $connectorKey . '|' : '';

        foreach ($candidates as $candidate) {
            if ($providerPrefix !== '' && isset($aliases[$providerPrefix . $candidate])) {
                return $aliases[$providerPrefix . $candidate];
            }

            if (isset($aliases[$candidate])) {
                return $aliases[$candidate];
            }
        }

        return '';
    }

    private function normalizeCompetition(string $canonicalKey, array $row): array
    {
        [$sport, $country, $competition] = $this->splitScopedKey($canonicalKey);
        $tier = (string) ($row['tier'] ?? 'standard');
        $identityTier = (string) ($row['identity_tier'] ?? $this->identityTierFromCatalogTier($tier));

        return array_merge([
            'canonical_competition_key' => $canonicalKey,
            'competition_key' => (string) ($row['competition_key'] ?? $competition),
            'legacy_key' => (string) ($row['legacy_key'] ?? $this->legacyCompetitionKey($country, (string) ($row['competition_key'] ?? $competition))),
            'sport_group_key' => (string) ($row['sport_group_key'] ?? $sport),
            'region_key' => (string) ($row['region_key'] ?? 'other'),
            'country_key' => (string) ($row['country_key'] ?? $country),
            'label' => (string) ($row['label'] ?? $competition),
            'short_label' => (string) ($row['short_label'] ?? $row['label'] ?? $competition),
            'tier' => $tier,
            'identity_tier' => $identityTier,
            'public_surface' => (string) ($row['public_surface'] ?? $this->surfaceFromTier($tier, $identityTier)),
            'priority' => (int) ($row['priority'] ?? 500),
            'visibility' => (string) ($row['visibility'] ?? 'visible'),
            'shell_enabled' => (bool) ($row['shell_enabled'] ?? true),
            'source_status' => (string) ($row['source_status'] ?? 'needs_review'),
            'source_urls' => (array) ($row['source_urls'] ?? []),
            'asset_meta' => array_merge($this->placeholderAsset('competition', $canonicalKey, (string) ($row['label'] ?? $competition)), (array) ($row['asset_meta'] ?? [])),
            'meta' => (array) ($row['meta'] ?? []),
            'aliases' => (array) ($row['aliases'] ?? []),
        ], $row);
    }

    private function normalizeTeam(string $canonicalKey, array $row): array
    {
        [$sport, $country, $team] = $this->splitScopedKey($canonicalKey);

        return array_merge([
            'canonical_team_key' => $canonicalKey,
            'team_key' => (string) ($row['team_key'] ?? $team),
            'sport_group_key' => (string) ($row['sport_group_key'] ?? $sport),
            'country_key' => (string) ($row['country_key'] ?? $country),
            'label' => (string) ($row['label'] ?? $team),
            'short_label' => (string) ($row['short_label'] ?? $row['label'] ?? $team),
            'tier' => (string) ($row['tier'] ?? 'standard'),
            'visibility' => (string) ($row['visibility'] ?? 'visible'),
            'competition_keys' => (array) ($row['competition_keys'] ?? []),
            'source_status' => (string) ($row['source_status'] ?? 'needs_review'),
            'source_urls' => (array) ($row['source_urls'] ?? []),
            'asset_meta' => array_merge($this->placeholderAsset('team', $canonicalKey, (string) ($row['label'] ?? $team)), (array) ($row['asset_meta'] ?? [])),
            'meta' => (array) ($row['meta'] ?? []),
            'aliases' => (array) ($row['aliases'] ?? []),
        ], $row);
    }

    private function normalizeCountry(string $countryKey, array $row): array
    {
        return array_merge([
            'country_key' => $countryKey,
            'region_key' => (string) ($row['region_key'] ?? 'international'),
            'iso2' => (string) ($row['iso2'] ?? ''),
            'label' => (string) ($row['label'] ?? $countryKey),
            'priority' => (int) ($row['priority'] ?? 500),
            'visibility' => (string) ($row['visibility'] ?? 'visible'),
            'asset_meta' => array_merge($this->placeholderAsset('country', $countryKey, (string) ($row['label'] ?? $countryKey)), (array) ($row['asset_meta'] ?? [])),
            'source_status' => (string) ($row['source_status'] ?? 'needs_review'),
            'source_urls' => (array) ($row['source_urls'] ?? []),
        ], $row);
    }

    private function upsertRow(string $table, string $keyColumn, string $key, array $payload, array $allowedColumns, array $jsonColumns = []): void
    {
        $columns = collect($allowedColumns)
            ->filter(fn (string $column): bool => Schema::hasColumn($table, $column))
            ->values()
            ->all();
        $row = [];

        foreach ($columns as $column) {
            $value = $payload[$column] ?? null;
            if (in_array($column, $jsonColumns, true)) {
                $value = $value === null ? null : json_encode(array_values($value) === $value ? array_values($value) : $value);
            }

            if (is_bool($value)) {
                $value = $value ? 1 : 0;
            }

            $row[$column] = $value;
        }

        $row[$keyColumn] = $key;
        if (Schema::hasColumn($table, 'updated_at')) {
            $row['updated_at'] = now();
        }

        $exists = DB::table($table)->where($keyColumn, $key)->exists();
        if ($exists) {
            DB::table($table)->where($keyColumn, $key)->update($row);
            return;
        }

        if (Schema::hasColumn($table, 'created_at')) {
            $row['created_at'] = now();
        }

        DB::table($table)->insert($row);
    }

    private function upsertAssetMeta(string $table, string $keyColumn, string $key, array $definition, array $allowedColumns): void
    {
        $encoded = json_encode((array) ($definition['asset_meta'] ?? []));

        if (DB::table($table)->where($keyColumn, $key)->exists()) {
            $row = ['asset_meta' => $encoded];
            if (Schema::hasColumn($table, 'updated_at')) {
                $row['updated_at'] = now();
            }

            DB::table($table)->where($keyColumn, $key)->update($row);
            return;
        }

        $this->upsertRow($table, $keyColumn, $key, $definition, $allowedColumns, ['source_urls', 'asset_meta', 'meta', 'competition_keys']);
    }

    private function replaceAliases(string $type, string $canonicalKey, array $aliases, ?int $cmsUserId = null): void
    {
        if (!Schema::hasTable('betting_catalog_aliases')) {
            return;
        }

        DB::table('betting_catalog_aliases')
            ->where('aliasable_type', $type)
            ->where('canonical_key', $canonicalKey)
            ->delete();

        foreach (collect($aliases)->map(fn ($alias): string => trim((string) $alias))->filter()->unique() as $alias) {
            DB::table('betting_catalog_aliases')->insert([
                'aliasable_type' => $type,
                'canonical_key' => $canonicalKey,
                'provider_key' => null,
                'connector_key' => null,
                'context_key' => null,
                'alias_value' => $alias,
                'normalized_alias' => $this->normalizeKey($alias),
                'is_active' => 1,
                'source_status' => 'needs_review',
                'notes' => null,
                'cms_user_id' => $cmsUserId,
                'meta' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function splitScopedKey(string $key): array
    {
        $parts = explode(':', $key);

        return [
            (string) ($parts[0] ?? ''),
            (string) ($parts[1] ?? ''),
            (string) ($parts[2] ?? ''),
        ];
    }

    private function legacyCompetitionKey(string $countryKey, string $competitionKey): string
    {
        if ($competitionKey === '') {
            return $countryKey;
        }

        if ($countryKey === '' || in_array($countryKey, ['fifa', 'uefa', 'conmebol', 'concacaf', 'caf'], true)) {
            return $competitionKey;
        }

        return $countryKey . '_' . $competitionKey;
    }

    private function surfaceFromTier(string $tier, string $identityTier): string
    {
        if ($tier === 'premium' || in_array($identityTier, ['S', 'A', 'B'], true)) {
            return 'premium_surface';
        }

        return match ($tier) {
            'secondary' => 'secondary_surface',
            'long_tail' => 'long_tail_surface',
            default => 'standard_surface',
        };
    }

    private function identityTierFromCatalogTier(string $tier): string
    {
        return match ($tier) {
            'premium' => 'B',
            'secondary' => 'D',
            'long_tail' => 'D',
            default => 'C',
        };
    }

    private function placeholderAsset(string $type, string $canonicalKey, string $label): array
    {
        $slug = $this->normalizeKey($canonicalKey);

        return [
            'placeholder' => true,
            'placeholder_label' => mb_strtoupper(mb_substr(trim($label) !== '' ? trim($label) : $canonicalKey, 0, 2)),
            'path' => '/assets/cms/img/sportsbook-catalog/placeholders/' . $type . '/' . $slug . '.svg',
        ];
    }

    private function decodeJsonValue($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if ($value === null || $value === '') {
            return [];
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
