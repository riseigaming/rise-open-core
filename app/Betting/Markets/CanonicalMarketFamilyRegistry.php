<?php

namespace App\Betting\Markets;

use Illuminate\Support\Str;

class CanonicalMarketFamilyRegistry
{
    public function definitions(): array
    {
        return [
            'result' => [
                'label' => 'Result',
                'canonical_market_type' => 'match_result',
                'aliases' => ['match_result', 'h2h', 'moneyline', '1x2', 'result'],
                'acceptance_ready' => true,
                'visible' => true,
                'state' => 'acceptance_ready',
                'disabled_reason' => null,
                'sort_order' => 10,
                'specifier_schema' => ['period', 'side_scope', 'participant_scope', 'line'],
            ],
            'totals' => [
                'label' => 'Totals',
                'canonical_market_type' => 'total_goals',
                'aliases' => ['total_goals', 'total_points', 'totals', 'team_total'],
                'acceptance_ready' => true,
                'visible' => true,
                'state' => 'acceptance_ready',
                'disabled_reason' => null,
                'sort_order' => 20,
                'specifier_schema' => ['period', 'line', 'team_scope', 'side_scope'],
            ],
            'handicaps' => [
                'label' => 'Handicaps',
                'canonical_market_type' => 'spreads',
                'aliases' => ['spreads', 'spread', 'handicap', 'handicaps', 'point_spread'],
                'acceptance_ready' => true,
                'visible' => true,
                'state' => 'acceptance_ready',
                'disabled_reason' => null,
                'sort_order' => 30,
                'specifier_schema' => ['period', 'line', 'side_scope', 'team_scope'],
            ],
            'corners' => [
                'label' => 'Corners',
                'canonical_market_type' => 'corners',
                'aliases' => ['corners', 'corner', 'total_corners', 'first_half_corners', 'corner_handicap'],
                'acceptance_ready' => false,
                'visible' => true,
                'state' => 'visible_disabled',
                'disabled_reason' => 'market_family_rollout_disabled',
                'sort_order' => 40,
                'specifier_schema' => ['period', 'line', 'corner_scope', 'team_scope', 'side_scope'],
            ],
            'cards' => [
                'label' => 'Cards',
                'canonical_market_type' => 'cards',
                'aliases' => ['cards', 'card', 'total_cards', 'first_half_cards', 'booking_points'],
                'acceptance_ready' => false,
                'visible' => true,
                'state' => 'visible_disabled',
                'disabled_reason' => 'market_family_rollout_disabled',
                'sort_order' => 50,
                'specifier_schema' => ['period', 'line', 'card_type', 'team_scope', 'participant_scope'],
            ],
            'player_props' => [
                'label' => 'Player Props',
                'canonical_market_type' => 'player_props',
                'aliases' => ['player_props', 'player_prop', 'player_goals', 'player_assists', 'player_shots', 'player_shots_on_target', 'player_cards', 'player_passes', 'player_tackles', 'player_points'],
                'acceptance_ready' => false,
                'visible' => true,
                'state' => 'visible_disabled',
                'disabled_reason' => 'market_family_rollout_disabled',
                'sort_order' => 60,
                'specifier_schema' => ['period', 'line', 'participant_scope', 'team_scope', 'side_scope'],
            ],
            'team_props' => [
                'label' => 'Team Props',
                'canonical_market_type' => 'team_props',
                'aliases' => ['team_props', 'team_prop', 'team_goals', 'team_assists', 'team_shots', 'team_cards', 'team_total_goals', 'team_to_score_yes_no', 'team_clean_sheet_yes_no'],
                'acceptance_ready' => false,
                'visible' => true,
                'state' => 'visible_disabled',
                'disabled_reason' => 'market_family_rollout_disabled',
                'sort_order' => 70,
                'specifier_schema' => ['period', 'line', 'team_scope', 'side_scope'],
            ],
            'futures' => [
                'label' => 'Futures',
                'canonical_market_type' => 'futures',
                'aliases' => ['futures', 'future', 'outright', 'outrights', 'outrights_winner', 'top_scorer', 'stage_reached'],
                'acceptance_ready' => false,
                'visible' => true,
                'state' => 'visible_disabled',
                'disabled_reason' => 'market_family_rollout_disabled',
                'sort_order' => 80,
                'specifier_schema' => ['future_scope', 'stage_scope', 'participant_scope', 'team_scope'],
            ],
        ];
    }

    public function keys(): array
    {
        return array_keys($this->definitions());
    }

    public function has(string $familyKey): bool
    {
        return array_key_exists($this->normalizeKey($familyKey), $this->definitions());
    }

    public function definition(string $familyKey): array
    {
        $familyKey = $this->familyKey($familyKey);
        $definition = $this->definitions()[$familyKey] ?? null;

        if (!$definition) {
            return [
                'label' => Str::headline($familyKey),
                'canonical_market_type' => $familyKey,
                'aliases' => [$familyKey],
                'acceptance_ready' => false,
                'visible' => true,
                'state' => 'visible_disabled',
                'disabled_reason' => 'market_family_unmapped',
                'sort_order' => 999,
                'specifier_schema' => ['period', 'line', 'side_scope', 'team_scope', 'participant_scope', 'future_scope'],
            ];
        }

        return array_merge([
            'family_key' => $familyKey,
        ], $definition);
    }

    public function familyKey(string $marketTypeOrKey): string
    {
        $normalized = $this->normalizeKey($marketTypeOrKey);
        if ($normalized === '') {
            return '';
        }

        if ($this->has($normalized)) {
            return $normalized;
        }

        foreach ($this->definitions() as $familyKey => $definition) {
            $aliases = array_map([$this, 'normalizeKey'], (array) data_get($definition, 'aliases', []));
            $canonicalMarketType = $this->normalizeKey((string) data_get($definition, 'canonical_market_type', ''));

            if (in_array($normalized, $aliases, true) || $normalized === $canonicalMarketType) {
                return $familyKey;
            }
        }

        return $normalized;
    }

    public function canonicalMarketTypeForFamily(string $familyKey): string
    {
        return (string) data_get($this->definition($familyKey), 'canonical_market_type', $this->familyKey($familyKey));
    }

    public function stateForFamily(string $familyKey, ?string $subfamilyKey = null): array
    {
        $familyKey = $this->familyKey($familyKey);
        $definition = $this->definition($familyKey);
        $subfamilyKey = $this->canonicalSubfamilyKey($familyKey, $subfamilyKey);

        $state = [
            'family_key' => $this->familyKey($familyKey),
            'label' => (string) data_get($definition, 'label', Str::headline($familyKey)),
            'canonical_market_type' => (string) data_get($definition, 'canonical_market_type', $familyKey),
            'acceptance_ready' => (bool) data_get($definition, 'acceptance_ready', false),
            'visible' => (bool) data_get($definition, 'visible', true),
            'state' => (string) data_get($definition, 'state', 'visible_disabled'),
            'disabled_reason' => data_get($definition, 'disabled_reason'),
            'sort_order' => (int) data_get($definition, 'sort_order', 999),
            'specifier_schema' => array_values((array) data_get($definition, 'specifier_schema', [])),
        ];

        if (!in_array($familyKey, ['team_props', 'player_props'], true)) {
            return $state;
        }

        $subfamilyDefinition = $this->subfamilyDefinitionForFamily($familyKey, $subfamilyKey);

        return array_merge($state, [
            'canonical_market_subfamily_key' => (string) data_get($subfamilyDefinition, 'canonical_market_subfamily_key', $subfamilyKey),
            'canonical_market_subfamily_label' => (string) data_get($subfamilyDefinition, 'label', $state['label']),
            'canonical_market_subfamily_state' => (string) data_get($subfamilyDefinition, 'state', 'visible_disabled'),
            'canonical_market_subfamily_disabled_reason' => data_get($subfamilyDefinition, 'disabled_reason', 'market_subfamily_rollout_disabled'),
            'canonical_market_subfamily_specifier_schema' => array_values((array) data_get($subfamilyDefinition, 'specifier_schema', [])),
            'canonical_market_subfamily_line_unit' => data_get($subfamilyDefinition, 'line_unit'),
            'canonical_market_subfamily_acceptance_ready' => (bool) data_get($subfamilyDefinition, 'acceptance_ready', false),
            'disabled_reason' => data_get($subfamilyDefinition, 'disabled_reason', 'market_subfamily_rollout_disabled'),
            'subfamily_key' => (string) data_get($subfamilyDefinition, 'canonical_market_subfamily_key', $subfamilyKey),
            'subfamily_label' => (string) data_get($subfamilyDefinition, 'label', $state['label']),
        ]);
    }

    public function stateForMarketType(string $marketType): array
    {
        return $this->stateForFamily($this->familyKey($marketType));
    }

    public function canonicalFamilyFromSourceKey(string $sourceMarketKey): string
    {
        return $this->familyKey($sourceMarketKey);
    }

    public function canonicalSubfamilyKey(string $familyKey, ?string $subfamilyKey = null, array $marketMeta = []): string
    {
        $familyKey = $this->familyKey($familyKey);
        if (!in_array($familyKey, ['team_props', 'player_props'], true)) {
            return '';
        }

        $candidates = array_values(array_filter(array_map('strval', [
            $subfamilyKey,
            data_get($marketMeta, 'canonical_market_subfamily'),
            data_get($marketMeta, 'provider_market_key'),
            data_get($marketMeta, 'source_market_key'),
            data_get($marketMeta, 'raw_market_key'),
        ])));

        foreach ($candidates as $candidate) {
            $normalized = $this->normalizeKey($candidate);
            foreach ($this->subfamilyDefinitionsForFamily($familyKey) as $definitionKey => $definition) {
                $aliases = array_map([$this, 'normalizeKey'], (array) data_get($definition, 'aliases', []));
                $canonicalKey = $this->normalizeKey((string) data_get($definition, 'canonical_market_subfamily_key', $definitionKey));

                if ($normalized === $canonicalKey || in_array($normalized, $aliases, true)) {
                    return (string) data_get($definition, 'canonical_market_subfamily_key', $definitionKey);
                }
            }
        }

        $fallback = $this->normalizeKey((string) ($subfamilyKey ?? ''));

        return $fallback !== '' ? $fallback : '';
    }

    public function canonicalSubfamilyLabel(string $familyKey, ?string $subfamilyKey = null, array $marketMeta = []): string
    {
        $familyKey = $this->familyKey($familyKey);
        if (!in_array($familyKey, ['team_props', 'player_props'], true)) {
            return (string) data_get($this->definition($familyKey), 'label', Str::headline($familyKey));
        }

        $subfamilyKey = $this->canonicalSubfamilyKey($familyKey, $subfamilyKey, $marketMeta);
        $definition = $this->subfamilyDefinitionForFamily($familyKey, $subfamilyKey);

        return (string) data_get($definition, 'label', data_get($this->definition($familyKey), 'label', Str::headline($familyKey)));
    }

    public function subfamilyKeys(string $familyKey): array
    {
        return array_keys($this->subfamilyDefinitionsForFamily($familyKey));
    }

    public function subfamilyDefinition(string $familyKey, ?string $subfamilyKey = null): array
    {
        return $this->subfamilyDefinitionForFamily($familyKey, $subfamilyKey);
    }

    private function subfamilyDefinitionsForFamily(string $familyKey): array
    {
        return match ($this->familyKey($familyKey)) {
            'team_props' => $this->teamPropsSubfamilyDefinitions(),
            'player_props' => $this->playerPropsSubfamilyDefinitions(),
            default => [],
        };
    }

    public function canonicalSpecifierKey(string $familyKey, ?float $line = null, array $marketMeta = []): string
    {
        $familyKey = $this->familyKey($familyKey);
        $lineKey = $line !== null ? $this->lineKey($line) : 'default';
        $period = $this->marketMetaValue($marketMeta, ['period', 'market_period', 'scope.period'], 'full_game');
        $teamScope = $this->marketMetaValue($marketMeta, ['team_scope', 'team', 'side'], 'default');
        $participantScope = $this->marketMetaValue($marketMeta, ['participant_scope', 'participant', 'player'], 'default');
        $futureScope = $this->marketMetaValue($marketMeta, ['future_scope', 'future', 'outright'], 'default');
        $stageScope = $this->marketMetaValue($marketMeta, ['stage_scope', 'stage'], 'default');
        $cardType = $this->marketMetaValue($marketMeta, ['card_type'], 'total');
        $cornerScope = $this->marketMetaValue($marketMeta, ['corner_scope'], 'default');
        $sideScope = $this->marketMetaValue($marketMeta, ['side_scope', 'side'], 'default');
        $subfamilyKey = $this->canonicalSubfamilyKey($familyKey, null, $marketMeta);

        return match ($familyKey) {
            'result' => 'default',
            'totals' => 'period:' . $period . ':line:' . $lineKey,
            'handicaps' => 'side:' . $sideScope . ':line:' . $lineKey,
            'corners' => 'corner_scope:' . $cornerScope . ':period:' . $period . ':line:' . $lineKey,
            'cards' => 'card_type:' . $cardType . ':period:' . $period . ':line:' . $lineKey,
            'player_props' => match ($subfamilyKey) {
                'player_goals' => 'subfamily:player_goals:participant:' . $participantScope . ':period:' . $period . ':line:' . $lineKey,
                'player_assists' => 'subfamily:player_assists:participant:' . $participantScope . ':period:' . $period . ':line:' . $lineKey,
                'player_shots' => 'subfamily:player_shots:participant:' . $participantScope . ':period:' . $period . ':line:' . $lineKey,
                'player_shots_on_target' => 'subfamily:player_shots_on_target:participant:' . $participantScope . ':period:' . $period . ':line:' . $lineKey,
                'player_cards' => 'subfamily:player_cards:participant:' . $participantScope . ':period:' . $period . ':line:' . $lineKey,
                'player_passes' => 'subfamily:player_passes:participant:' . $participantScope . ':period:' . $period . ':line:' . $lineKey,
                'player_tackles' => 'subfamily:player_tackles:participant:' . $participantScope . ':period:' . $period . ':line:' . $lineKey,
                default => 'participant:' . $participantScope . ':period:' . $period . ':line:' . $lineKey,
            },
            'team_props' => match ($this->canonicalSubfamilyKey($familyKey, null, $marketMeta)) {
                'team_total_goals' => 'subfamily:team_total_goals:team_scope:' . $teamScope . ':period:' . $period . ':line:' . $lineKey,
                'team_to_score_yes_no' => 'subfamily:team_to_score_yes_no:team_scope:' . $teamScope . ':period:' . $period,
                'team_clean_sheet_yes_no' => 'subfamily:team_clean_sheet_yes_no:team_scope:' . $teamScope . ':period:' . $period,
                default => 'team:' . $teamScope . ':period:' . $period . ':line:' . $lineKey,
            },
            'futures' => 'future:' . $futureScope . ':stage:' . $stageScope,
            default => 'default',
        };
    }

    public function canonicalOutcomeKey(string $familyKey, string $label, string $designation = ''): string
    {
        $familyKey = $this->familyKey($familyKey);
        $normalizedLabel = $this->normalizeKey($label);
        $normalizedDesignation = $this->normalizeKey($designation);

        return match ($familyKey) {
            'result' => match (true) {
                str_contains($normalizedDesignation, 'away'),
                str_contains($normalizedLabel, 'away') => 'away',
                str_contains($normalizedDesignation, 'draw'),
                str_contains($normalizedLabel, 'draw') => 'draw',
                default => 'home',
            },
            'totals', 'corners', 'cards', 'player_props', 'team_props' => match (true) {
                str_contains($normalizedDesignation, 'under'),
                str_contains($normalizedLabel, 'under') => 'under',
                str_contains($normalizedDesignation, 'over'),
                str_contains($normalizedLabel, 'over') => 'over',
                str_contains($normalizedDesignation, 'yes'),
                str_contains($normalizedLabel, 'yes') => 'yes',
                str_contains($normalizedDesignation, 'no'),
                str_contains($normalizedLabel, 'no') => 'no',
                default => $this->normalizeKey($designation !== '' ? $designation : $label) ?: 'selection',
            },
            'handicaps' => match (true) {
                str_contains($normalizedDesignation, 'away'),
                str_contains($normalizedLabel, 'away') => 'away',
                default => 'home',
            },
            'futures' => $this->normalizeKey($designation !== '' ? $designation : $label) ?: 'future_selection',
            default => $this->normalizeKey($designation !== '' ? $designation : $label) ?: 'selection',
        };
    }

    private function teamPropsSubfamilyDefinitions(): array
    {
        return [
            'team_total_goals' => [
                'canonical_market_subfamily_key' => 'team_total_goals',
                'label' => 'Team Total Goals',
                'aliases' => ['team_total_goals', 'team_goals', 'team_total_goals_over_under'],
                'acceptance_ready' => false,
                'visible' => true,
                'state' => 'visible_disabled',
                'disabled_reason' => 'market_subfamily_rollout_disabled',
                'specifier_schema' => ['period', 'line', 'team_scope'],
                'line_unit' => 'goals',
            ],
            'team_to_score_yes_no' => [
                'canonical_market_subfamily_key' => 'team_to_score_yes_no',
                'label' => 'Team To Score',
                'aliases' => ['team_to_score_yes_no', 'team_to_score', 'team_score_yes_no'],
                'acceptance_ready' => false,
                'visible' => true,
                'state' => 'visible_disabled',
                'disabled_reason' => 'market_subfamily_rollout_disabled',
                'specifier_schema' => ['period', 'team_scope'],
                'line_unit' => null,
            ],
            'team_clean_sheet_yes_no' => [
                'canonical_market_subfamily_key' => 'team_clean_sheet_yes_no',
                'label' => 'Team Clean Sheet',
                'aliases' => ['team_clean_sheet_yes_no', 'team_clean_sheet'],
                'acceptance_ready' => false,
                'visible' => true,
                'state' => 'visible_disabled',
                'disabled_reason' => 'market_subfamily_rollout_disabled',
                'specifier_schema' => ['period', 'team_scope'],
                'line_unit' => null,
            ],
        ];
    }

    private function playerPropsSubfamilyDefinitions(): array
    {
        return [
            'player_goals' => [
                'canonical_market_subfamily_key' => 'player_goals',
                'label' => 'Player Goals',
                'aliases' => ['player_goals', 'goalscorer', 'anytime_scorer'],
                'acceptance_ready' => false,
                'visible' => true,
                'state' => 'visible_disabled',
                'disabled_reason' => 'market_subfamily_rollout_disabled',
                'specifier_schema' => ['period', 'line', 'participant_scope', 'team_scope'],
                'line_unit' => 'goals',
            ],
            'player_assists' => [
                'canonical_market_subfamily_key' => 'player_assists',
                'label' => 'Player Assists',
                'aliases' => ['player_assists', 'assist', 'assists'],
                'acceptance_ready' => false,
                'visible' => true,
                'state' => 'visible_disabled',
                'disabled_reason' => 'market_subfamily_rollout_disabled',
                'specifier_schema' => ['period', 'line', 'participant_scope', 'team_scope'],
                'line_unit' => 'assists',
            ],
            'player_shots' => [
                'canonical_market_subfamily_key' => 'player_shots',
                'label' => 'Player Shots',
                'aliases' => ['player_shots', 'shots', 'shot'],
                'acceptance_ready' => false,
                'visible' => true,
                'state' => 'visible_disabled',
                'disabled_reason' => 'market_subfamily_rollout_disabled',
                'specifier_schema' => ['period', 'line', 'participant_scope', 'team_scope'],
                'line_unit' => 'shots',
            ],
            'player_shots_on_target' => [
                'canonical_market_subfamily_key' => 'player_shots_on_target',
                'label' => 'Player Shots On Target',
                'aliases' => ['player_shots_on_target', 'shots_on_target', 'sot'],
                'acceptance_ready' => false,
                'visible' => true,
                'state' => 'visible_disabled',
                'disabled_reason' => 'market_subfamily_rollout_disabled',
                'specifier_schema' => ['period', 'line', 'participant_scope', 'team_scope'],
                'line_unit' => 'shots',
            ],
            'player_cards' => [
                'canonical_market_subfamily_key' => 'player_cards',
                'label' => 'Player Cards',
                'aliases' => ['player_cards', 'booking', 'bookings'],
                'acceptance_ready' => false,
                'visible' => true,
                'state' => 'visible_disabled',
                'disabled_reason' => 'market_subfamily_rollout_disabled',
                'specifier_schema' => ['period', 'line', 'participant_scope', 'team_scope'],
                'line_unit' => 'cards',
            ],
            'player_passes' => [
                'canonical_market_subfamily_key' => 'player_passes',
                'label' => 'Player Passes',
                'aliases' => ['player_passes', 'passes', 'pass'],
                'acceptance_ready' => false,
                'visible' => true,
                'state' => 'visible_disabled',
                'disabled_reason' => 'market_subfamily_rollout_disabled',
                'specifier_schema' => ['period', 'line', 'participant_scope', 'team_scope'],
                'line_unit' => 'passes',
            ],
            'player_tackles' => [
                'canonical_market_subfamily_key' => 'player_tackles',
                'label' => 'Player Tackles',
                'aliases' => ['player_tackles', 'tackles', 'tackle'],
                'acceptance_ready' => false,
                'visible' => true,
                'state' => 'visible_disabled',
                'disabled_reason' => 'market_subfamily_rollout_disabled',
                'specifier_schema' => ['period', 'line', 'participant_scope', 'team_scope'],
                'line_unit' => 'tackles',
            ],
        ];
    }

    private function subfamilyDefinitionForFamily(string $familyKey, ?string $subfamilyKey): array
    {
        $familyKey = $this->familyKey($familyKey);
        $subfamilyKey = $this->normalizeKey((string) $subfamilyKey);
        $definitions = $this->subfamilyDefinitionsForFamily($familyKey);

        foreach ($definitions as $definitionKey => $definition) {
            $aliases = array_map([$this, 'normalizeKey'], (array) data_get($definition, 'aliases', []));
            $canonicalKey = $this->normalizeKey((string) data_get($definition, 'canonical_market_subfamily_key', $definitionKey));

            if ($subfamilyKey !== '' && ($subfamilyKey === $canonicalKey || in_array($subfamilyKey, $aliases, true))) {
                return $definition;
            }
        }

        return [
            'canonical_market_subfamily_key' => $subfamilyKey !== '' ? $subfamilyKey : null,
            'label' => $subfamilyKey !== '' ? Str::headline($subfamilyKey) : 'Team Props',
            'aliases' => $subfamilyKey !== '' ? [$subfamilyKey] : [],
            'acceptance_ready' => false,
            'visible' => true,
            'state' => 'visible_disabled',
            'disabled_reason' => 'market_subfamily_rollout_disabled',
            'specifier_schema' => ['period', 'team_scope'],
            'line_unit' => null,
        ];
    }

    private function marketMetaValue(array $marketMeta, array $paths, string $default): string
    {
        foreach ($paths as $path) {
            $value = data_get($marketMeta, $path);
            if (is_string($value) && trim($value) !== '') {
                return $this->normalizeKey($value);
            }
        }

        return $this->normalizeKey($default);
    }

    private function normalizeKey(string $value): string
    {
        $value = Str::ascii(trim($value));
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?: '';

        return trim($value, '_');
    }

    private function lineKey(float $line): string
    {
        $line = round($line, 3);

        return rtrim(rtrim(number_format($line, 3, '.', ''), '0'), '.');
    }
}
