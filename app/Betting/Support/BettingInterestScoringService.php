<?php

namespace App\Betting\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class BettingInterestScoringService
{
    public function __construct(private ?BettingCompetitionPriorityService $competitionPriority = null)
    {
        $this->competitionPriority = $this->competitionPriority ?: app(BettingCompetitionPriorityService::class);
    }

    public function enabled(): bool
    {
        return (bool) data_get($this->config(), 'enabled', true);
    }

    public function minScore(): int
    {
        return (int) data_get($this->config(), 'min_score', 120);
    }

    public function featuredLimit(): int
    {
        return max(1, (int) data_get($this->config(), 'featured_limit', 24));
    }

    public function score(array $event): int
    {
        if (!$this->enabled()) {
            return 0;
        }

        if ($this->eventIntelligenceEnabled()) {
            return (int) $this->explain($event)['score'];
        }

        $sportGroupKey = $this->sportGroupKey((string) data_get($event, 'sport_code', data_get($event, 'sport_group_key', '')));
        $competition = $this->competitionPriority->resolveForEvent($event);
        $coverageScore = $this->coverageScore($event);
        $teamScore = $this->teamScore($event);
        $score = $this->sportScore($sportGroupKey)
            + $this->competitionScore($competition)
            + $teamScore
            + $coverageScore
            + $this->startWindowScore($event)
            + $this->manualBoost($event, $competition);

        return max(0, $score - $this->safetyPenalty($event));
    }

    public function explain(array $event): array
    {
        $competition = $this->competitionIntelligence($event);
        $teams = $this->teamIntelligence($event);
        $rivalryScore = $this->rivalryScore($event);
        $stageScore = $this->stageScore($event);
        $coverageScore = $this->eventIntelligenceCoverageScore($event);
        $startScore = $this->eventIntelligenceStartWindowScore($event);
        $manualBoost = $this->manualBoost($event, $this->competitionPriority->resolveForEvent($event), true);
        $safetyPenalty = $this->safetyPenalty($event);
        $score = max(0, $competition['score']
            + $teams['score']
            + $rivalryScore
            + $stageScore
            + $coverageScore
            + $startScore
            + $manualBoost
            - $safetyPenalty);

        return [
            'score' => $score,
            'components' => [
                'competition_tier' => $competition['score'],
                'team_tier' => $teams['score'],
                'rivalry_or_derby' => $rivalryScore,
                'event_stage' => $stageScore,
                'coverage_quality' => $coverageScore,
                'start_window' => $startScore,
                'manual_boost' => $manualBoost,
                'safety_penalty' => $safetyPenalty,
            ],
            'matched' => [
                'competition_key' => $competition['key'],
                'competition_tier' => $competition['tier'],
                'teams' => $teams['matched'],
            ],
        ];
    }

    public function isFeaturedEligible(array $event): bool
    {
        return $this->enabled()
            && $this->isFuturePrematch($event)
            && $this->hasOpenBettableSelection($event);
    }

    public function featuredEnabled(): bool
    {
        return $this->enabled() && (bool) config('betting.interest_scoring.featured.enabled', true);
    }

    /** Rank only the requested surface; never change the full inventory's score or coverage. */
    public function featuredSelection(Collection $index): array
    {
        $meta = ['featured_window' => '48h', 'candidate_count_48h' => 0,
            'candidate_count_7d' => 0, 'returned_count' => 0, 'featured_hidden' => !$this->featuredEnabled()];
        if (!$this->featuredEnabled()) {
            return ['rows' => collect(), 'meta' => $meta];
        }
        $now = now('UTC')->timestamp;
        $eligible = app(\App\Betting\Application\Offers\ActivePrematchEligibility::class);
        $candidates = $index->filter(function (array $event) use ($now, $eligible): bool {
            $start = (int) ($event['starts_at_timestamp'] ?? 0);
            return $start > $now && $start <= $now + 7 * 86400
                && ($event['front_betting_enabled'] ?? true) !== false
                && !in_array(data_get($event, 'public_live_state.phase_key'), ['in-game', 'live'], true)
                && $eligible->classify($event, $now)['eligible'];
        });
        $near = $candidates->filter(fn (array $event): bool => $event['starts_at_timestamp'] <= $now + 48 * 3600);
        $useful = max(1, (int) config('betting.interest_scoring.featured.useful_count', 6));
        $expanded = $near->count() < $useful;
        $ranked = ($expanded ? $candidates : $near)->map(function (array $event) use ($now): array {
            $hours = ($event['starts_at_timestamp'] - $now) / 3600;
            $competition = $this->competitionIntelligence($event, true);
            $teams = $this->teamIntelligence($event);
            // A valid unknown competition has a baseline, not a zero-quality penalty.
            $components = [
                'proximity' => $hours <= 6 ? 400 : ($hours <= 24 ? 340 : ($hours <= 48 ? 260 : 100)),
                'prestige' => 80 + (int) round(min(900, $competition['score']) / 900 * 140)
                    + (int) round(min(500, $teams['score']) / 500 * 60),
                'market_depth' => min(100, (int) data_get($event, 'inventory_eligibility.eligible_market_count', 0) * 10),
                'identity' => data_get($event, 'inventory_eligibility.settlement_identity') === 'verified' ? 60 : 30,
                'stage_rivalry' => min(80, (int) round(($this->stageScore($event) + $this->rivalryScore($event)) / 8)),
                'configured_boost' => $this->manualBoost($event, ['competition_key' => $event['canonical_competition_key'] ?? $event['league_filter_key'] ?? ''], true),
            ];
            $event['featured_score_components'] = $components;
            $event['interest_score'] = array_sum($components);
            return $event;
        })->sortBy([['interest_score', 'desc'], ['starts_at_timestamp', 'asc'], ['event_id', 'asc']])->values();
        $hidden = (bool) config('betting.interest_scoring.featured.hide_below_useful_count', false) && $ranked->count() < $useful;
        $rows = $hidden ? collect() : $this->diversifyFeaturedCandidates($ranked)->take(min(24, $this->featuredLimit()))->values();
        return ['rows' => $rows, 'meta' => array_replace($meta, [
            'featured_window' => $expanded ? '7d' : '48h',
            'candidate_count_48h' => $near->count(), 'candidate_count_7d' => $candidates->count(),
            'returned_count' => $rows->count(), 'featured_hidden' => $hidden,
        ])];
    }

    public function diversifyFeaturedCandidates(Collection $scored): Collection
    {
        $scored = $scored->values();
        $diversity = (array) data_get($this->config(), 'diversity', []);

        if (!(bool) ($diversity['enabled'] ?? true) || $scored->count() <= 1) {
            return $scored;
        }

        $featuredLimit = min($this->featuredLimit(), $scored->count());
        $topScore = max(1, (int) $scored->max(fn (array $event): int => (int) data_get($event, 'interest_score', 0)));
        $minQualityScore = (int) floor($topScore * max(0.0, min(1.0, (float) ($diversity['min_quality_ratio_for_diversity'] ?? 0.55))));
        $maxPerSport = max(1, (int) ($diversity['max_per_sport'] ?? 8));
        $maxPerLeague = max(1, (int) ($diversity['max_per_league'] ?? 4));
        $remaining = $scored;
        $selected = collect();
        $sportCounts = [];
        $leagueCounts = [];

        while ($selected->count() < $featuredLimit && $remaining->isNotEmpty()) {
            $candidate = $this->bestDiversityCandidate(
                $remaining,
                $minQualityScore,
                $maxPerSport,
                $maxPerLeague,
                $sportCounts,
                $leagueCounts
            ) ?: $remaining->first();

            $selected->push($candidate);
            $sportKey = $this->eventSportGroupKey($candidate);
            $leagueKey = $this->eventLeagueKey($candidate);
            $sportCounts[$sportKey] = ($sportCounts[$sportKey] ?? 0) + 1;
            $leagueCounts[$leagueKey] = ($leagueCounts[$leagueKey] ?? 0) + 1;
            $identity = $this->eventIdentity($candidate);
            $remaining = $remaining
                ->reject(fn (array $event): bool => $this->eventIdentity($event) === $identity)
                ->values();
        }

        return $selected
            ->concat($remaining)
            ->values();
    }

    private function config(): array
    {
        return (array) config('betting.interest_scoring', []);
    }

    private function eventIntelligenceConfig(): array
    {
        return (array) data_get($this->config(), 'event_intelligence', []);
    }

    private function eventIntelligenceEnabled(): bool
    {
        return (bool) data_get($this->eventIntelligenceConfig(), 'enabled', true);
    }

    private function sportScore(string $sportGroupKey): int
    {
        return (int) data_get($this->config(), 'sport_scores.' . $sportGroupKey, data_get($this->config(), 'sport_scores.other', 0));
    }

    private function competitionScore(array $competition): int
    {
        $priorityScore = (int) ($competition['priority_score'] ?? 0);

        return (int) round($priorityScore / 20);
    }

    private function coverageScore(array $event, ?array $weights = null): int
    {
        $markets = collect((array) data_get($event, 'quote.markets', []));
        $quoteRefCount = $markets
            ->flatMap(fn (array $market): array => (array) data_get($market, 'selections', []))
            ->pluck('quote_ref')
            ->filter()
            ->unique()
            ->count();
        $bookmakerCount = collect([
            data_get($event, 'source_provider'),
            data_get($event, 'quote.source_provider'),
        ])->filter()->unique()->count();

        $weights = $weights ?: (array) data_get($this->config(), 'coverage', []);
        $score = ($markets->count() * (int) ($weights['market_count_weight'] ?? 4))
            + ($quoteRefCount * (int) ($weights['quote_ref_count_weight'] ?? 1))
            + ($bookmakerCount * (int) ($weights['bookmaker_count_weight'] ?? 3));

        return min(max(0, $score), (int) ($weights['max_score'] ?? 120));
    }

    private function teamScore(array $event): int
    {
        $teamScores = (array) data_get($this->config(), 'team_scores', []);
        $homeKey = $this->scoreKey((string) data_get($event, 'participant_home', ''));
        $awayKey = $this->scoreKey((string) data_get($event, 'participant_away', ''));

        return min(
            (int) data_get($this->config(), 'caps.team_score', 160),
            (int) ($teamScores[$homeKey] ?? 0) + (int) ($teamScores[$awayKey] ?? 0)
        );
    }

    private function startWindowScore(array $event, ?array $windows = null): int
    {
        $startsAt = (int) data_get($event, 'starts_at_timestamp', 0);
        if ($startsAt <= 0) {
            return 0;
        }

        $minutesUntilStart = (int) floor(($startsAt - Carbon::now('UTC')->timestamp) / 60);
        if ($minutesUntilStart < 0) {
            return 0;
        }

        foreach ((array) ($windows ?: data_get($this->config(), 'start_windows', [])) as $window) {
            if ($minutesUntilStart <= (int) ($window['max_minutes'] ?? 0)) {
                return (int) ($window['score'] ?? 0);
            }
        }

        return 0;
    }

    private function manualBoost(array $event, array $competition, bool $eventIntelligence = false): int
    {
        $eventKey = (string) data_get($event, 'source_event_key', data_get($event, 'event_key', ''));
        $leagueKey = (string) ($competition['competition_key'] ?? '');
        $eventBoost = (int) data_get($this->config(), 'manual_event_boosts.' . $eventKey, 0);
        $leagueBoost = (int) data_get($this->config(), 'manual_league_boosts.' . $leagueKey, 0);
        $boost = $eventBoost + $leagueBoost;
        $cap = $eventIntelligence
            ? (int) data_get($this->eventIntelligenceConfig(), 'caps.manual_boost', 300)
            : (int) data_get($this->config(), 'caps.manual_boost', 200);

        return min(max($boost, -100), $cap);
    }

    private function competitionIntelligence(array $event, bool $publishedIndex = false): array
    {
        $sportGroupKey = $this->sportGroupKey((string) data_get($event, 'sport_code', data_get($event, 'sport_group_key', '')));
        $resolved = $publishedIndex ? [] : $this->competitionPriority->resolveForEvent($event);
        $candidates = $this->competitionCandidates($event, $resolved, $sportGroupKey);

        foreach ((array) data_get($this->eventIntelligenceConfig(), 'competition_tiers', []) as $configuredKey => $definition) {
            [$configuredSport, $configuredCompetition] = $this->splitScopedKey((string) $configuredKey);
            if ($configuredSport !== '' && $configuredSport !== $sportGroupKey) {
                continue;
            }

            $configuredAliases = collect((array) data_get($definition, 'aliases', []))
                ->push($configuredCompetition)
                ->map(fn (string $value): string => $this->scoreKey($value))
                ->filter()
                ->unique()
                ->values();

            if ($configuredAliases->intersect($candidates)->isEmpty()) {
                continue;
            }

            $tier = (string) data_get($definition, 'tier', 'D');

            return [
                'key' => ($configuredSport !== '' ? $configuredSport . ':' : '') . $configuredCompetition,
                'tier' => $tier,
                'score' => $this->tierScore($tier),
            ];
        }

        return [
            'key' => $sportGroupKey . ':' . ($candidates->first() ?: 'unknown'),
            'tier' => 'D',
            'score' => $this->tierScore('D'),
        ];
    }

    private function competitionCandidates(array $event, array $resolved, string $sportGroupKey): Collection
    {
        return collect([
            data_get($resolved, 'canonical_competition_key'),
            data_get($event, 'canonical_competition_key'),
            data_get($resolved, 'competition_key'),
            data_get($event, 'competition_canonical_key'),
            data_get($event, 'competition_key'),
            data_get($event, 'league_key'),
            data_get($event, 'competition_group_key'),
            data_get($event, 'league_filter_key'),
            data_get($event, 'competition_label'),
            data_get($event, 'league_title'),
            data_get($event, 'tournament_name'),
            data_get($event, 'sport_code'),
        ])
            ->flatMap(function ($value) use ($sportGroupKey): array {
                $rawValue = (string) $value;
                $key = $this->scoreKey($rawValue);
                if ($key === '') {
                    return [];
                }

                $keys = [$key];
                if (substr_count($rawValue, ':') >= 2) {
                    $parts = array_values(array_filter(explode(':', $rawValue), static fn (string $part): bool => trim($part) !== ''));
                    $country = $this->scoreKey((string) ($parts[1] ?? ''));
                    $competition = $this->scoreKey((string) ($parts[2] ?? ''));

                    if ($country !== '' && $competition !== '') {
                        $keys[] = $country . '_' . $competition;
                    }

                    if ($competition !== '') {
                        $keys[] = $competition;
                    }
                }

                if (str_starts_with($key, $sportGroupKey . '_')) {
                    $keys[] = Str::after($key, $sportGroupKey . '_');
                }

                return $keys;
            })
            ->filter()
            ->unique()
            ->values();
    }

    private function tierScore(string $tier): int
    {
        return (int) data_get($this->eventIntelligenceConfig(), 'tier_scores.' . $tier, 0);
    }

    private function teamIntelligence(array $event): array
    {
        $teamScores = (array) data_get($this->eventIntelligenceConfig(), 'team_scores', []);
        $homeKeys = $this->teamKeys((string) data_get($event, 'participant_home', ''));
        $awayKeys = $this->teamKeys((string) data_get($event, 'participant_away', ''));
        $matched = [];
        $score = 0;

        foreach ([$homeKeys, $awayKeys] as $keys) {
            foreach ($keys as $key) {
                if (array_key_exists($key, $teamScores)) {
                    $score += (int) $teamScores[$key];
                    $matched[] = $key;
                    break;
                }
            }
        }

        return [
            'score' => min($score, (int) data_get($this->eventIntelligenceConfig(), 'caps.team_score', 500)),
            'matched' => array_values(array_unique($matched)),
        ];
    }

    private function teamKeys(string $value): array
    {
        $key = $this->scoreKey($value);
        if ($key === '') {
            return [];
        }

        $parts = explode('_', $key);

        return collect([$key, end($parts) ?: $key])
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function rivalryScore(array $event): int
    {
        $homeKeys = $this->teamKeys((string) data_get($event, 'participant_home', ''));
        $awayKeys = $this->teamKeys((string) data_get($event, 'participant_away', ''));
        $best = 0;

        foreach ((array) data_get($this->eventIntelligenceConfig(), 'rivalries', []) as $rivalry) {
            $teams = collect((array) data_get($rivalry, 'teams', []))
                ->map(fn (string $team): string => $this->scoreKey($team))
                ->filter()
                ->values();

            if ($teams->count() < 2) {
                continue;
            }

            $first = (string) $teams[0];
            $second = (string) $teams[1];
            $matches = (in_array($first, $homeKeys, true) && in_array($second, $awayKeys, true))
                || (in_array($second, $homeKeys, true) && in_array($first, $awayKeys, true));

            if ($matches) {
                $best = max($best, (int) data_get($rivalry, 'score', 0));
            }
        }

        return min($best, (int) data_get($this->eventIntelligenceConfig(), 'caps.rivalry_score', 300));
    }

    private function stageScore(array $event): int
    {
        $haystack = $this->scoreKey(implode(' ', array_filter([
            data_get($event, 'canonical_name'),
            data_get($event, 'tournament_name'),
            data_get($event, 'competition_label'),
            data_get($event, 'league_title'),
            data_get($event, 'stage'),
            data_get($event, 'round'),
            data_get($event, 'meta.stage'),
            data_get($event, 'meta.round'),
        ])));
        $best = 0;

        foreach ((array) data_get($this->eventIntelligenceConfig(), 'stage_boosts', []) as $key => $score) {
            $stageKey = $this->scoreKey((string) $key);
            if ($stageKey !== '' && preg_match('/(^|_)' . preg_quote($stageKey, '/') . '($|_)/', $haystack) === 1) {
                $best = max($best, (int) $score);
            }
        }

        return min($best, (int) data_get($this->eventIntelligenceConfig(), 'caps.stage_score', 400));
    }

    private function eventIntelligenceCoverageScore(array $event): int
    {
        return $this->coverageScore($event, (array) data_get($this->eventIntelligenceConfig(), 'coverage', []));
    }

    private function eventIntelligenceStartWindowScore(array $event): int
    {
        return $this->startWindowScore($event, (array) data_get($this->eventIntelligenceConfig(), 'start_windows', []));
    }

    private function splitScopedKey(string $key): array
    {
        if (!str_contains($key, ':')) {
            return ['', $this->scoreKey($key)];
        }

        return [
            $this->scoreKey((string) Str::before($key, ':')),
            $this->scoreKey((string) Str::after($key, ':')),
        ];
    }

    private function safetyPenalty(array $event): int
    {
        if (!$this->hasOpenBettableSelection($event)) {
            return 100000;
        }

        return 0;
    }

    private function isFuturePrematch(array $event): bool
    {
        return (int) data_get($event, 'starts_at_timestamp', 0) >= Carbon::now('UTC')->timestamp
            && (string) data_get($event, 'public_live_state.phase_key', 'pre-match') === 'pre-match';
    }

    private function hasOpenBettableSelection(array $event): bool
    {
        return collect((array) data_get($event, 'quote.markets', []))
            ->filter(fn (array $market): bool => ($market['status'] ?? 'open') === 'open')
            ->flatMap(fn (array $market): array => (array) data_get($market, 'selections', []))
            ->contains(function (array $selection): bool {
                // Ranking uses inventory eligibility; visible rows hydrate
                // their opaque contracts separately before becoming selectable.
                $hydratable = ($selection['inventory_eligible'] ?? false) === true
                    && ($selection['acceptance_mode'] ?? '') === 'adapter_vps_event_scope'
                    && ($selection['settlement_identity_state'] ?? '') !== 'missing';
                $ready = (bool) data_get($selection, 'bettable', false)
                    && (string) data_get($selection, 'quote_ref', '') !== '';
                return ($hydratable || $ready)
                    && (float) ($selection['decimal_price'] ?? 0) > 1
                    && empty($selection['is_catalog_shell'])
                    && (string) data_get($selection, 'acceptance_mode', '') !== 'catalog_shell'
                    && (string) data_get($selection, 'offer_status', 'open') === 'open';
            });
    }

    private function sportGroupKey(string $sportCode): string
    {
        $sportCode = Str::lower($sportCode);

        return match (true) {
            str_starts_with($sportCode, 'soccer') => 'soccer',
            str_starts_with($sportCode, 'basketball') => 'basketball',
            str_starts_with($sportCode, 'tennis') => 'tennis',
            str_starts_with($sportCode, 'baseball') => 'baseball',
            default => 'other',
        };
    }

    private function bestDiversityCandidate(
        Collection $remaining,
        int $minQualityScore,
        int $maxPerSport,
        int $maxPerLeague,
        array $sportCounts,
        array $leagueCounts
    ): ?array {
        return $remaining
            ->filter(function (array $event) use ($minQualityScore, $maxPerSport, $maxPerLeague, $sportCounts, $leagueCounts): bool {
                $sportKey = $this->eventSportGroupKey($event);
                $leagueKey = $this->eventLeagueKey($event);

                return (int) data_get($event, 'interest_score', 0) >= $minQualityScore
                    && ($sportCounts[$sportKey] ?? 0) < $maxPerSport
                    && ($leagueCounts[$leagueKey] ?? 0) < $maxPerLeague;
            })
            ->sort(fn (array $left, array $right): int => $this->compareFeaturedCandidates($left, $right))
            ->first();
    }

    private function compareFeaturedCandidates(array $left, array $right): int
    {
        $scoreComparison = ((int) data_get($right, 'interest_score', 0)) <=> ((int) data_get($left, 'interest_score', 0));
        if ($scoreComparison !== 0) {
            return $scoreComparison;
        }

        $startsAtComparison = ((int) data_get($left, 'starts_at_timestamp', 0)) <=> ((int) data_get($right, 'starts_at_timestamp', 0));
        if ($startsAtComparison !== 0) {
            return $startsAtComparison;
        }

        $sportOrderComparison = $this->preferredSportIndex($this->eventSportGroupKey($left)) <=> $this->preferredSportIndex($this->eventSportGroupKey($right));
        if ($sportOrderComparison !== 0) {
            return $sportOrderComparison;
        }

        return strcmp($this->eventIdentity($left), $this->eventIdentity($right));
    }

    private function eventSportGroupKey(array $event): string
    {
        return $this->sportGroupKey((string) data_get($event, 'sport_group_key', data_get($event, 'sport_code', '')));
    }

    private function eventLeagueKey(array $event): string
    {
        // Published inventory already has the canonical Catalog filter key.
        // Do not resolve the same identity for every diversity candidate pass.
        if (($event['inventory_projection'] ?? '') === 'compact_display'
            && trim((string) ($event['league_filter_key'] ?? '')) !== '') {
            return (string) $event['league_filter_key'];
        }

        $resolved = $this->competitionPriority->resolveForEvent($event);
        $sourceIndependent = (string) data_get($resolved, 'source_independent_canonical_key', '');
        if ($sourceIndependent !== '') {
            return $sourceIndependent;
        }

        foreach ([
            data_get($resolved, 'canonical_competition_key'),
            data_get($event, 'canonical_competition_key'),
            data_get($resolved, 'competition_key'),
            data_get($event, 'competition_canonical_key'),
            data_get($event, 'competition_key'),
            data_get($event, 'league_filter_key'),
        ] as $candidate) {
            $key = $this->scoreKey((string) $candidate);
            if ($key !== '') {
                return $key;
            }
        }

        return $this->scoreKey((string) data_get($event, 'tournament_name', data_get($event, 'league_name', 'unknown')));
    }

    private function eventIdentity(array $event): string
    {
        return (string) data_get($event, 'event_key', data_get($event, 'source_event_key', data_get($event, 'event_id', '')));
    }

    private function preferredSportIndex(string $sportGroupKey): int
    {
        $order = array_values((array) data_get($this->config(), 'diversity.preferred_sport_order', ['soccer', 'basketball', 'tennis', 'baseball', 'other']));
        $index = array_search($sportGroupKey, $order, true);

        return $index === false ? PHP_INT_MAX : (int) $index;
    }

    private function scoreKey(string $value): string
    {
        return Str::slug(Str::ascii(Str::lower($value)), '_');
    }
}
