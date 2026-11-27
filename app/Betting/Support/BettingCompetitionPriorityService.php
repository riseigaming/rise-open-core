<?php

namespace App\Betting\Support;

use App\Models\BettingEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class BettingCompetitionPriorityService
{
    public function __construct(private ?SportsbookCanonicalIdentityService $canonicalIdentity = null)
    {
        $this->canonicalIdentity = $this->canonicalIdentity ?: app(SportsbookCanonicalIdentityService::class);
    }

    public function resolveForEvent(BettingEvent|array $event): array
    {
        $eventData = $this->eventData($event);
        $sourceMeta = (array) data_get($eventData, 'meta', []);

        foreach ([
            'canonical_competition_key',
            'competition_canonical_key',
            'competition_key',
            'competition_country_key',
            'competition_country_label',
            'competition_identity_known',
        ] as $key) {
            if (data_get($eventData, $key) !== null) {
                $sourceMeta[$key] = data_get($eventData, $key);
            }
        }

        return $this->resolve(
            (string) data_get($eventData, 'sport_code', data_get($eventData, 'sport_group_key', '')),
            $this->extractCompetitionKeyCandidate((string) data_get($eventData, 'competition_key', data_get($eventData, 'competition_canonical_key', data_get($eventData, 'canonical_competition_key', data_get($eventData, 'league_key', data_get($eventData, 'meta.league_key', data_get($eventData, 'competition_group_key', ''))))))),
            (string) data_get($eventData, 'competition_label', data_get($eventData, 'league_title', data_get($eventData, 'tournament_name', data_get($eventData, 'meta.league_name', '')))),
            (string) data_get($eventData, 'competition_country_label', data_get($eventData, 'country_label', data_get($eventData, 'meta.country_name', data_get($eventData, 'meta.country', '')))),
            $sourceMeta
        );
    }

    public function resolve(string $sportCode, ?string $competitionKey = null, ?string $competitionLabel = null, ?string $countryLabel = null, array $sourceMeta = []): array
    {
        $rawCompetitionLabel = trim((string) ($competitionLabel ?? ''));
        $rawCountryLabel = trim((string) ($countryLabel ?? ''));
        $sportGroupKey = $this->sportGroupKey($sportCode);
        $sportDefinition = $this->sportDefinition($sportGroupKey);
        $sportPriority = $this->priorityFromDefinition($sportDefinition, $this->defaultSportPriority($sportGroupKey));
        $sportLabel = (string) data_get($sportDefinition, 'label', $this->sportLabel($sportGroupKey));

        [$derivedCountryLabel, $derivedCompetitionLabel] = $this->splitCountryAndCompetition($rawCompetitionLabel);
        $countryLabel = trim((string) ($rawCountryLabel ?: $derivedCountryLabel));
        $competitionLabel = trim((string) ($rawCompetitionLabel ?: $derivedCompetitionLabel));

        $identity = $this->canonicalPriorityIdentity(
            $sportGroupKey,
            $competitionKey,
            $competitionLabel,
            $countryLabel,
            $sourceMeta
        );
        $identityCountryKey = '';
        $identityKnown = (bool) data_get($identity, 'known', false);

        if ($identityKnown) {
            $competitionKey = (string) data_get($identity, 'legacy_key', $competitionKey);
            $competitionLabel = (string) data_get($identity, 'label', $competitionLabel);
            $identityCountryKey = (string) data_get($identity, 'country_key', '');
            $countryLabel = $countryLabel ?: (string) data_get($identity, 'country_label', '');
        }

        $competitionKey = $this->canonicalCompetitionKey($competitionKey ?: $competitionLabel);
        if ($competitionKey === '') {
            $competitionKey = $this->canonicalCompetitionKey($competitionLabel);
        }

        $countryKey = $identityCountryKey !== '' ? $identityCountryKey : $this->countryKey($countryLabel, $competitionKey);
        $countryDefinition = $this->countryDefinition($countryKey);
        $countryCode = $this->countryCodeForKey($countryKey);
        $countryPriority = $this->countryWeight($countryCode);
        if ($countryPriority === 0) {
            $countryPriority = $this->priorityFromDefinition($countryDefinition, 0);
        }
        $countryResolvedLabel = (string) data_get($countryDefinition, 'label', $countryLabel ?: $this->countryLabel($countryKey));

        $competitionDefinition = $this->competitionDefinition($competitionKey);
        $manualPriority = $this->manualPriorityScore($sportGroupKey, $competitionKey);
        $knownCompetition = $competitionDefinition !== [];
        $competitionPriority = $manualPriority ?? $this->priorityFromDefinition($competitionDefinition, $knownCompetition ? 300 : -300);
        $competitionResolvedLabel = (string) data_get($competitionDefinition, 'label', $competitionLabel ?: $this->competitionLabel($competitionKey));
        $priorityBucket = (string) data_get($competitionDefinition, 'bucket', $this->priorityBucket($sportDefinition, $countryDefinition, $competitionDefinition, $competitionKey, $manualPriority, $knownCompetition));

        $displayLabel = $this->displayLabel($countryResolvedLabel, $competitionResolvedLabel, $competitionKey, $sportLabel);
        $sourceIndependentCanonicalKey = implode('|', array_filter([
            $sportGroupKey !== '' ? $sportGroupKey : 'other',
            $countryKey !== '' ? $countryKey : 'unknown_country',
            $competitionKey !== '' ? $competitionKey : 'unknown_competition',
        ]));
        $priorityScore = $this->priorityScore(
            $competitionPriority,
            $countryPriority,
            $this->liveBonus($sourceMeta),
            $this->multiSourceBonus($sourceMeta),
            $this->coveragePenalty($sourceMeta),
            $knownCompetition
        );

        return [
            'sport_group_key' => $sportGroupKey !== '' ? $sportGroupKey : 'other',
            'sport_label' => $sportLabel,
            'sport_priority' => $sportPriority,
            'country_key' => $countryKey !== '' ? $countryKey : 'unknown_country',
            'country_code' => $countryCode !== '' ? $countryCode : strtoupper($countryKey),
            'country_label' => $countryResolvedLabel !== '' ? $countryResolvedLabel : 'Unknown Country',
            'country_priority' => $countryPriority,
            'competition_key' => $competitionKey !== '' ? $competitionKey : 'unknown_competition',
            'competition_label' => $competitionResolvedLabel !== '' ? $competitionResolvedLabel : 'Unknown Competition',
            'competition_display_label' => $displayLabel,
            'competition_priority' => $competitionPriority,
            'priority_bucket' => $priorityBucket,
            'priority_score' => $priorityScore,
            'sort_key' => sprintf('%010d|%s', 1000000000 - max(0, $priorityScore), $competitionKey !== '' ? $competitionKey : 'unknown_competition'),
            'source_independent_canonical_key' => $sourceIndependentCanonicalKey,
            'canonical_competition_key' => (string) data_get($identity, 'canonical_key', ''),
            'competition_identity_known' => $identityKnown,
            'competition_identity_tier' => (string) data_get($identity, 'tier', ''),
            'competition_catalog_tier' => (string) data_get($identity, 'catalog_tier', ''),
            'competition_public_surface' => (string) data_get($identity, 'public_surface', ''),
            'raw_competition_label' => $rawCompetitionLabel,
            'raw_country_label' => $rawCountryLabel,
            'manual_pin' => (bool) data_get($competitionDefinition, 'pinned', false),
            'featured' => (string) $priorityBucket === 'featured',
            'source_meta' => $sourceMeta,
        ];
    }

    public function summary(iterable $events): array
    {
        $resolved = collect($events)
            ->map(fn ($event): array => $this->resolveForEvent($event))
            ->values();

        $competitions = $resolved
            ->groupBy(fn (array $row): string => (string) ($row['source_independent_canonical_key'] ?? 'unknown'))
            ->map(function (Collection $rows): array {
                $first = $rows->first();
                $aliases = $rows
                    ->pluck('raw_competition_label')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                return [
                    'source_independent_canonical_key' => (string) ($first['source_independent_canonical_key'] ?? 'unknown'),
                    'sport_group_key' => (string) ($first['sport_group_key'] ?? 'other'),
                    'sport_label' => (string) ($first['sport_label'] ?? 'Diğer'),
                    'country_key' => (string) ($first['country_key'] ?? 'unknown_country'),
                    'country_label' => (string) ($first['country_label'] ?? 'Unknown Country'),
                    'competition_key' => (string) ($first['competition_key'] ?? 'unknown_competition'),
                    'canonical_competition_key' => (string) ($first['canonical_competition_key'] ?? ''),
                    'competition_identity_known' => (bool) ($first['competition_identity_known'] ?? false),
                    'competition_identity_tier' => (string) ($first['competition_identity_tier'] ?? ''),
                    'competition_label' => (string) ($first['competition_display_label'] ?? $first['competition_label'] ?? 'Unknown Competition'),
                    'priority_bucket' => (string) ($first['priority_bucket'] ?? 'normal'),
                    'priority_score' => (int) ($first['priority_score'] ?? 0),
                    'event_count' => $rows->count(),
                    'aliases' => $aliases,
                    'is_unknown' => $this->competitionDefinition((string) ($first['competition_key'] ?? '')) === [],
                ];
            })
            ->sort(function (array $left, array $right): int {
                if (($left['priority_score'] ?? 0) !== ($right['priority_score'] ?? 0)) {
                    return ($right['priority_score'] ?? 0) <=> ($left['priority_score'] ?? 0);
                }

                if (($left['event_count'] ?? 0) !== ($right['event_count'] ?? 0)) {
                    return ($right['event_count'] ?? 0) <=> ($left['event_count'] ?? 0);
                }

                return strcmp((string) ($left['competition_label'] ?? ''), (string) ($right['competition_label'] ?? ''));
            })
            ->values();

        $sportGroups = $resolved
            ->groupBy('sport_group_key')
            ->map(function (Collection $rows): array {
                $first = $rows->first();

                return [
                    'sport_group_key' => (string) ($first['sport_group_key'] ?? 'other'),
                    'sport_label' => (string) ($first['sport_label'] ?? 'Diğer'),
                    'sport_priority' => (int) ($first['sport_priority'] ?? 0),
                    'priority_bucket' => (string) ($first['priority_bucket'] ?? 'normal'),
                    'competition_count' => $rows
                        ->pluck('source_independent_canonical_key')
                        ->unique()
                        ->count(),
                    'event_count' => $rows->count(),
                ];
            })
            ->sort(function (array $left, array $right): int {
                if (($left['sport_priority'] ?? 0) !== ($right['sport_priority'] ?? 0)) {
                    return ($left['sport_priority'] ?? 0) <=> ($right['sport_priority'] ?? 0);
                }

                return strcmp((string) ($left['sport_label'] ?? ''), (string) ($right['sport_label'] ?? ''));
            })
            ->values();

        $countries = $resolved
            ->groupBy('country_key')
            ->map(function (Collection $rows): array {
                $first = $rows->first();

                return [
                    'country_key' => (string) ($first['country_key'] ?? 'unknown_country'),
                    'country_label' => (string) ($first['country_label'] ?? 'Unknown Country'),
                    'country_priority' => (int) ($first['country_priority'] ?? 0),
                    'competition_count' => $rows
                        ->pluck('source_independent_canonical_key')
                        ->unique()
                        ->count(),
                    'event_count' => $rows->count(),
                ];
            })
            ->sort(function (array $left, array $right): int {
                if (($left['country_priority'] ?? 0) !== ($right['country_priority'] ?? 0)) {
                    return ($right['country_priority'] ?? 0) <=> ($left['country_priority'] ?? 0);
                }

                return strcmp((string) ($left['country_label'] ?? ''), (string) ($right['country_label'] ?? ''));
            })
            ->values();

        return [
            'sports' => $sportGroups->all(),
            'countries' => $countries->all(),
            'competitions' => $competitions->all(),
            'prioritized_competitions' => $competitions->take(12)->all(),
            'unknown_competition_count' => $competitions->where('is_unknown', true)->count(),
            'fallback_competition_count' => $competitions->where('priority_bucket', 'fallback')->count(),
            'source_aliases' => $competitions
                ->filter(fn (array $row): bool => count((array) ($row['aliases'] ?? [])) > 1)
                ->map(fn (array $row): array => [
                    'canonical' => (string) ($row['competition_key'] ?? 'unknown_competition'),
                    'aliases' => array_values(array_diff((array) ($row['aliases'] ?? []), [(string) ($row['competition_label'] ?? '')])),
                ])
                ->filter(fn (array $row): bool => $row['aliases'] !== [])
                ->values()
                ->all(),
        ];
    }

    private function eventData(BettingEvent|array $event): array
    {
        if ($event instanceof BettingEvent) {
            return $event->toArray();
        }

        return $event;
    }

    private function sportDefinition(string $sportGroupKey): array
    {
        return (array) data_get($this->competitionTaxonomyConfig(), 'sports.' . $sportGroupKey, []);
    }

    private function countryDefinition(string $countryKey): array
    {
        return (array) data_get($this->competitionTaxonomyConfig(), 'countries.' . $countryKey, []);
    }

    private function competitionDefinition(string $competitionKey): array
    {
        return (array) data_get($this->competitionTaxonomyConfig(), 'competitions.' . $competitionKey, []);
    }

    private function competitionAliases(): array
    {
        $aliases = [];

        foreach ((array) data_get($this->competitionTaxonomyConfig(), 'competition_aliases', []) as $alias => $canonicalKey) {
            $aliases[$this->normalizeKey((string) $alias)] = $this->normalizeKey((string) $canonicalKey);
        }

        return $aliases;
    }

    private function countryAliases(): array
    {
        return array_map(
            fn (string $value): string => $this->normalizeKey($value),
            (array) data_get($this->competitionTaxonomyConfig(), 'country_aliases', [])
        );
    }

    private function canonicalPriorityIdentity(
        string $sportGroupKey,
        ?string $competitionKey,
        string $competitionLabel,
        string $countryLabel,
        array $sourceMeta
    ): array {
        $candidates = collect([
            data_get($sourceMeta, 'canonical_competition_key'),
            data_get($sourceMeta, 'competition_canonical_key'),
            $competitionKey,
            $competitionLabel,
        ])
            ->map(fn ($value): string => trim((string) $value))
            ->filter(fn (string $value): bool => $value !== '')
            ->unique()
            ->values();

        $country = trim((string) (data_get($sourceMeta, 'competition_country_key')
            ?: data_get($sourceMeta, 'competition_country_label')
            ?: $countryLabel));

        foreach ($candidates as $candidate) {
            $identity = $this->canonicalIdentity->competition($sportGroupKey, $candidate, [
                'sport_group_key' => $sportGroupKey,
                'country' => $country,
            ]);

            if ((bool) data_get($identity, 'known', false)) {
                return $identity;
            }
        }

        return $this->canonicalIdentity->competition($sportGroupKey, $competitionLabel ?: $competitionKey, [
            'sport_group_key' => $sportGroupKey,
            'country' => $country,
        ]);
    }

    private function canonicalCompetitionKey(string $value): string
    {
        $normalized = $this->normalizeKey($value);
        if ($normalized === '') {
            return '';
        }

        $aliases = array_merge($this->competitionAliases(), $this->competitionAliasMap());

        return $aliases[$normalized] ?? $normalized;
    }

    private function competitionAliasMap(): array
    {
        $map = [];
        foreach ((array) data_get($this->competitionTaxonomyConfig(), 'competitions', []) as $key => $definition) {
            $canonicalKey = $this->normalizeKey((string) $key);
            $map[$canonicalKey] = $canonicalKey;

            foreach ((array) data_get($definition, 'aliases', []) as $alias) {
                $map[$this->normalizeKey((string) $alias)] = $canonicalKey;
            }
        }

        foreach ((array) data_get($this->competitionTaxonomyConfig(), 'competition_aliases', []) as $alias => $canonicalKey) {
            $map[$this->normalizeKey((string) $alias)] = $this->normalizeKey((string) $canonicalKey);
        }

        return $map;
    }

    private function countryKey(string $countryLabel, string $competitionKey): string
    {
        $normalized = $this->normalizeKey($countryLabel);
        if ($normalized !== '') {
            $aliases = (array) data_get($this->competitionTaxonomyConfig(), 'country_aliases', []);
            $normalizedAliases = [];
            foreach ($aliases as $alias => $target) {
                $normalizedAliases[$this->normalizeKey((string) $alias)] = $this->normalizeKey((string) $target);
            }

            return $normalizedAliases[$normalized] ?? $normalized;
        }

        if ($competitionKey !== '' && str_contains($competitionKey, '_')) {
            return (string) Str::before($competitionKey, '_');
        }

        return '';
    }

    private function splitCountryAndCompetition(string $value): array
    {
        $normalized = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
        if ($normalized === '') {
            return ['', ''];
        }

        $parts = preg_split('/\s(?:-|\||\/|:)\s/u', $normalized, 2);
        if (is_array($parts) && count($parts) === 2) {
            return [trim((string) $parts[0]), trim((string) $parts[1])];
        }

        $normalizedKey = $this->normalizeKey($normalized);
        foreach (array_keys((array) data_get($this->competitionTaxonomyConfig(), 'country_aliases', [])) as $alias) {
            $aliasKey = $this->normalizeKey((string) $alias);
            if ($aliasKey !== '' && Str::startsWith($normalizedKey, $aliasKey . '_')) {
                return [ucwords(str_replace('_', ' ', $aliasKey)), trim(Str::after($normalized, ' '))];
            }
        }

        return ['', $normalized];
    }

    private function displayLabel(string $countryLabel, string $competitionLabel, string $competitionKey, string $sportLabel): string
    {
        $competitionDefinition = $this->competitionDefinition($competitionKey);
        $label = trim((string) data_get($competitionDefinition, 'label', $competitionLabel));

        if ($label === '') {
            $label = $sportLabel;
        }

        if ($countryLabel !== '') {
            return $countryLabel . ' - ' . $label;
        }

        return $label;
    }

    private function priorityScore(
        int $competitionPriority,
        int $countryPriority,
        int $freshLiveBonus = 0,
        int $multiSourceBonus = 0,
        int $lowCoveragePenalty = 0,
        bool $knownCompetition = false
    ): int
    {
        $config = $this->priorityScoringConfig();
        $base = $competitionPriority;

        if ($base === 0) {
            $base = (int) data_get($config, $knownCompetition ? 'fallback.known_competition' : 'fallback.unknown_competition', 0);
        }

        return $base + $countryPriority + $freshLiveBonus + $multiSourceBonus + $lowCoveragePenalty;
    }

    private function priorityFromDefinition(array $definition, int $fallback): int
    {
        return (int) data_get($definition, 'priority', $fallback);
    }

    private function priorityBucket(
        array $sportDefinition,
        array $countryDefinition,
        array $competitionDefinition,
        string $competitionKey,
        ?int $manualPriority = null,
        bool $knownCompetition = false
    ): string
    {
        $bucket = (string) data_get($competitionDefinition, 'bucket', '');
        if ($bucket !== '') {
            return $bucket;
        }

        if ($manualPriority !== null) {
            return 'featured';
        }

        $bucket = (string) data_get($countryDefinition, 'bucket', '');
        if ($bucket !== '') {
            return $bucket;
        }

        if (($competitionDefinition !== [] || $countryDefinition !== []) && $competitionKey !== '' && $competitionKey !== 'unknown_competition' && $knownCompetition) {
            $bucket = (string) data_get($sportDefinition, 'bucket', '');
            if ($bucket !== '') {
                return $bucket;
            }
        }

        return 'fallback';
    }

    private function defaultSportPriority(string $sportGroupKey): int
    {
        return match ($sportGroupKey) {
            'soccer' => 10,
            'basketball' => 20,
            'tennis' => 30,
            'snooker' => 35,
            'volleyball' => 40,
            'baseball' => 50,
            'icehockey' => 60,
            default => 0,
        };
    }

    private function countryCodeForKey(string $countryKey): string
    {
        return match ($countryKey) {
            'turkey' => 'TR',
            'england' => 'GB',
            'spain' => 'ES',
            'italy' => 'IT',
            'germany' => 'DE',
            'france' => 'FR',
            'brazil' => 'BR',
            'japan' => 'JP',
            'sweden' => 'SE',
            'finland' => 'FI',
            'norway' => 'NO',
            'chile' => 'CL',
            'usa' => 'US',
            'uefa' => 'UEFA',
            'fifa' => 'FIFA',
            'conmebol' => 'CONMEBOL',
            default => strtoupper($countryKey),
        };
    }

    private function countryWeight(string $countryCode): int
    {
        if ($countryCode === '') {
            return 0;
        }

        return (int) data_get($this->priorityScoringConfig(), 'country_weights.' . $countryCode, 0);
    }

    private function manualPriorityScore(string $sportGroupKey, string $competitionKey): ?int
    {
        $manual = collect((array) data_get($this->priorityScoringConfig(), 'manual', []))
            ->mapWithKeys(fn ($value, $key): array => [$this->normalizeKey((string) $key) => (int) $value])
            ->all();
        $candidates = array_filter([
            $sportGroupKey !== '' && $competitionKey !== '' ? $sportGroupKey . ':' . $competitionKey : null,
            $competitionKey !== '' ? $competitionKey : null,
        ]);

        foreach ($candidates as $candidate) {
            $normalized = $this->normalizeKey((string) $candidate);
            if (array_key_exists($normalized, $manual)) {
                return (int) $manual[$normalized];
            }
        }

        return null;
    }

    private function liveBonus(array $sourceMeta): int
    {
        if (!$this->isFreshLiveSource($sourceMeta)) {
            return 0;
        }

        return (int) data_get($this->priorityScoringConfig(), 'fallback.fresh_live_bonus', 0);
    }

    private function multiSourceBonus(array $sourceMeta): int
    {
        $sourceCount = (int) data_get($sourceMeta, 'source_count', data_get($sourceMeta, 'public_live_state.source_count', 0));
        if ($sourceCount < 2) {
            return 0;
        }

        return (int) data_get($this->priorityScoringConfig(), 'fallback.multi_source_bonus', 0);
    }

    private function coveragePenalty(array $sourceMeta): int
    {
        $coverageRatio = (float) data_get($sourceMeta, 'coverage_ratio', data_get($sourceMeta, 'public_live_state.coverage_ratio', 0.0));
        if ($coverageRatio <= 0.0 || $coverageRatio >= 0.99) {
            return 0;
        }

        return (int) data_get($this->priorityScoringConfig(), 'fallback.low_coverage_penalty', 0);
    }

    private function isFreshLiveSource(array $sourceMeta): bool
    {
        $isLive = (bool) data_get($sourceMeta, 'is_live', data_get($sourceMeta, 'public_live_state.phase_key', '') === 'in-game');
        if (!$isLive) {
            return false;
        }

        $timestamps = array_filter([
            data_get($sourceMeta, 'last_source_seen_at'),
            data_get($sourceMeta, 'public_live_state.last_source_seen_at'),
            data_get($sourceMeta, 'public_live_state.updated_at'),
        ]);

        if ($timestamps === []) {
            return true;
        }

        $latest = null;
        foreach ($timestamps as $timestamp) {
            try {
                $current = Carbon::parse($timestamp);
            } catch (\Throwable) {
                continue;
            }

            if ($latest === null || $current->greaterThan($latest)) {
                $latest = $current;
            }
        }

        if ($latest === null) {
            return false;
        }

        return $latest->greaterThanOrEqualTo(now()->subMinutes(30));
    }

    private function sportGroupKey(string $sportCode): string
    {
        $sportCode = trim($sportCode);

        return match (true) {
            str_starts_with($sportCode, 'soccer') => 'soccer',
            str_starts_with($sportCode, 'tennis') => 'tennis',
            str_starts_with($sportCode, 'basketball') => 'basketball',
            str_starts_with($sportCode, 'snooker') => 'snooker',
            str_contains($sportCode, 'table_tennis') => 'table_tennis',
            str_contains($sportCode, 'dota') => 'dota2',
            str_contains($sportCode, 'cs2') => 'cs2',
            str_starts_with($sportCode, 'volleyball') => 'volleyball',
            str_starts_with($sportCode, 'baseball') => 'baseball',
            str_starts_with($sportCode, 'icehockey') => 'icehockey',
            default => Str::slug($sportCode, '_'),
        };
    }

    private function sportLabel(string $sportGroupKey): string
    {
        return match ($sportGroupKey) {
            'soccer' => 'Futbol',
            'basketball' => 'Basketbol',
            'tennis' => 'Tenis',
            'snooker' => 'Snooker',
            'volleyball' => 'Voleybol',
            'baseball' => 'Beyzbol',
            'icehockey' => 'Buz Hokeyi',
            'table_tennis' => 'Masa Tenisi',
            'dota2' => 'Dota 2',
            'cs2' => 'CS2',
            default => 'Diğer',
        };
    }

    private function countryLabel(string $countryKey): string
    {
        return match ($countryKey) {
            'england' => 'England',
            'spain' => 'Spain',
            'italy' => 'Italy',
            'germany' => 'Germany',
            'france' => 'France',
            'brazil' => 'Brazil',
            'turkey' => 'Turkey',
            'japan' => 'Japan',
            'sweden' => 'Sweden',
            'finland' => 'Finland',
            'norway' => 'Norway',
            'chile' => 'Chile',
            'uefa' => 'UEFA',
            'fifa' => 'FIFA',
            'conmebol' => 'CONMEBOL',
            'usa' => 'USA',
            default => ucfirst(str_replace('_', ' ', $countryKey)),
        };
    }

    private function competitionLabel(string $competitionKey): string
    {
        return match ($competitionKey) {
            'fifa_world_cup' => 'World Cup',
            'england_premier_league' => 'Premier League',
            'uefa_champions_league' => 'Champions League',
            'uefa_europa_league' => 'Europa League',
            'uefa_conference_league' => 'Conference League',
            'copa_libertadores' => 'Copa Libertadores',
            'copa_sudamericana' => 'Copa Sudamericana',
            'uefa_womens_qualifiers' => 'Women World Cup Qualifiers Europe',
            'spain_la_liga' => 'La Liga',
            'spain_segunda' => 'Segunda',
            'italy_serie_a' => 'Serie A',
            'germany_bundesliga' => 'Bundesliga',
            'france_ligue_one' => 'Ligue 1',
            'brazil_serie_a' => 'Série A',
            'brazil_serie_b' => 'Série B',
            'turkey_super_lig' => 'Süper Lig',
            'usa_mls' => 'MLS',
            'japan_j_league' => 'J League',
            'sweden_allsvenskan' => 'Allsvenskan',
            'sweden_superettan' => 'Superettan',
            'finland_veikkausliiga' => 'Veikkausliiga',
            'norway_eliteserien' => 'Eliteserien',
            'chile_primera' => 'Primera',
            default => ucfirst(str_replace('_', ' ', $competitionKey)),
        };
    }

    private function normalizeKey(string $value): string
    {
        return trim(Str::slug(trim($value), '_'));
    }

    private function priorityScoringConfig(): array
    {
        $config = (array) config('betting.competition_priority', []);

        if ($config !== []) {
            return $config;
        }

        return (array) data_get(config('betting.feeds', []), 'competition_priority', []);
    }

    private function competitionTaxonomyConfig(): array
    {
        $config = (array) data_get(config('betting.feeds.internal_scraper', []), 'competition_priority', []);

        if ($config !== []) {
            return $config;
        }

        return (array) data_get(config('betting.feeds', []), 'competition_priority', []);
    }

    private function extractCompetitionKeyCandidate(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (str_contains($value, '|')) {
            return trim((string) Str::afterLast($value, '|'));
        }

        if (str_contains($value, '--')) {
            return trim((string) Str::afterLast($value, '--'));
        }

        return $value;
    }
}
