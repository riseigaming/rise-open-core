@php
    $offlineDemo = $offlineDemo ?? ['active' => false];
    $sportsbookShellRuntimeConfig = [
        'offline_demo' => $offlineDemo,
        'finance_read_only' => (bool)($financeReadOnly ?? true),
        'adapter_pre_submit_quote_refresh_enabled' => (bool) config('betting.adapter_vps_public_read_cache.pre_submit_quote_refresh_enabled', true),
    ];
    $sportsbookUi = (array) __('cms_public.sportsbook');
    $sportsbookShellI18n = array_merge($sportsbookUi['js'] ?? [], $sportsbookUi['surface_states'] ?? [], [
        'history' => $sportsbookUi['history'] ?? [],
        'markets' => $sportsbookUi['markets'] ?? [],
        'social_market' => $sportsbookUi['social_market'] ?? [],
    ]);
    $sbText = static fn (string $key, string $fallback = ''): string => (string) data_get($sportsbookUi, $key, $fallback);
    $defaultSportFilter = $defaultSportFilter ?? 'soccer';
    $sportsbookTheme = $sportsbookTheme ?? 'default';
    $formatCountLabel = static function (int $count): string {
        return $count > 1000 ? '1000+' : (string) $count;
    };
    $sports = $sports ?? [
        ['key' => 'soccer', 'label' => $sbText('sports.soccer', 'Futbol'), 'count' => 0, 'active' => true],
        ['key' => 'basketball', 'label' => $sbText('sports.basketball', 'Basketbol'), 'count' => 0, 'active' => false],
        ['key' => 'tennis', 'label' => $sbText('sports.tennis', 'Tenis'), 'count' => 0, 'active' => false],
        ['key' => 'baseball', 'label' => $sbText('sports.baseball', 'Beyzbol'), 'count' => 0, 'active' => false],
        ['key' => 'snooker', 'label' => $sbText('sports.snooker', 'Snooker'), 'count' => 0, 'active' => false],
    ];

    $leagues = $leagues ?? [
        [
            'badge' => 'UCL',
            'title' => 'Europe. Women. UEFA Champions League',
            'sport' => $sbText('sports.soccer', 'Futbol'),
            'matches' => [
                [
                    'time' => '20:45',
                    'date' => '24.03.2026',
                    'home' => 'VfL Wolfsburg W',
                    'away' => 'Olympique Lyon',
                    'match_result' => [
                        ['label' => '1', 'odd' => '6.5'],
                        ['label' => 'X', 'odd' => '5.0'],
                        ['label' => '2', 'odd' => '1.35'],
                    ],
                    'totals' => [
                        ['label' => '3.5 Alt', 'odd' => '1.86'],
                        ['label' => '3.5 Üst', 'odd' => '1.94'],
                    ],
                    'more' => '+99',
                ],
                [
                    'time' => '23:00',
                    'date' => '24.03.2026',
                    'home' => 'Arsenal Women',
                    'away' => 'Chelsea W',
                    'match_result' => [
                        ['label' => '1', 'odd' => '2.09'],
                        ['label' => 'X', 'odd' => '3.59'],
                        ['label' => '2', 'odd' => '3.04'],
                    ],
                    'totals' => [
                        ['label' => '2.5 Alt', 'odd' => '2.01'],
                        ['label' => '2.5 Üst', 'odd' => '1.8'],
                    ],
                    'more' => '+99',
                ],
            ],
        ],
        [
            'badge' => 'PL',
            'title' => 'İngiltere. Premier Lig',
            'sport' => $sbText('sports.soccer', 'Futbol'),
            'matches' => [
                [
                    'time' => '22:00',
                    'date' => '10.04.2026',
                    'home' => 'West Ham United',
                    'away' => 'Wolverhampton',
                    'match_result' => [
                        ['label' => '1', 'odd' => '1.75'],
                        ['label' => 'X', 'odd' => '3.85'],
                        ['label' => '2', 'odd' => '4.37'],
                    ],
                    'totals' => [
                        ['label' => '2.5 Alt', 'odd' => '2.08'],
                        ['label' => '2.5 Üst', 'odd' => '1.74'],
                    ],
                    'more' => '+99',
                ],
                [
                    'time' => '14:30',
                    'date' => '11.04.2026',
                    'home' => 'Arsenal',
                    'away' => 'AFC Bournemouth',
                    'match_result' => [
                        ['label' => '1', 'odd' => '1.38'],
                        ['label' => 'X', 'odd' => '4.86'],
                        ['label' => '2', 'odd' => '7.9'],
                    ],
                    'totals' => [
                        ['label' => '3.0 Alt', 'odd' => '1.8'],
                        ['label' => '3.0 Üst', 'odd' => '2.0'],
                    ],
                    'more' => '+99',
                ],
            ],
        ],
    ];

    $offerSummary = (array) ($offerSummary ?? []);
    $adapterPublicPrematch = (array) ($adapterPublicPrematch ?? []);
    $adapterPublicPrematchUnavailable = !empty($adapterPublicPrematch['enabled']) && empty($adapterPublicPrematch['available']);
    $hasOffers = $hasOffers ?? !empty($leagues);
    $summaryDebugState = (string) ($offerSummary['summary_state'] ?? 'healthy');
    $summaryDebugGuardReason = (string) ($offerSummary['summary_guard_reason'] ?? '');
    $summaryDebugLastHealthyGeneratedAt = (string) ($offerSummary['summary_last_healthy_generated_at'] ?? '');
    $summaryDebugRawGeneratedAt = (string) ($offerSummary['summary_raw_generated_at'] ?? '');
    $summaryDebugRawTotal = (int) ($offerSummary['summary_raw_total'] ?? 0);
    $initialPhaseKey = ($defaultTabKey ?? 'pre-match') === 'in-game' ? 'in-game' : 'pre-match';
    $activePhaseCountKey = $initialPhaseKey === 'in-game' ? 'in_game_count' : 'pre_match_count';
    $summaryTabCounts = (array) ($offerSummary['tab_counts'] ?? []);
    $matchCount = (int) ($summaryTabCounts[$initialPhaseKey] ?? collect($leagues)->sum(fn (array $league) => (int) ($league[$activePhaseCountKey] ?? count($league['matches'] ?? []))));
    $leagueCount = count($leagues);
    $leagueFilters = $leagueFilters ?? collect($leagues)
        ->map(function (array $league) use ($activePhaseCountKey) {
            $sportGroupKey = (string) ($league['sport_group_key'] ?? 'all');
            $leagueKey = (string) ($league['league_filter_key']
                ?? ($sportGroupKey . '--' . \Illuminate\Support\Str::slug((string) ($league['title'] ?? 'turnuva'))));
            $groupLabel = trim((string) \Illuminate\Support\Str::of((string) ($league['title'] ?? $sbText('filters.tournaments', 'Turnuvalar')))->before(' - ')) ?: $sbText('filters.tournaments', 'Turnuvalar');

            return [
                'key' => $leagueKey,
                'label' => (string) ($league['title'] ?? $sbText('filters.tournament', 'Turnuva')),
                'sport_group_key' => $sportGroupKey,
                'group_key' => \Illuminate\Support\Str::slug($sportGroupKey . '-' . $groupLabel, '_'),
                'group_label' => $groupLabel,
                'count' => (int) ($league[$activePhaseCountKey] ?? count($league['matches'] ?? [])),
            ];
        })
        ->groupBy(fn (array $item) => $item['sport_group_key'] . '|' . $item['key'])
        ->map(function ($group) {
            $first = $group->first();

            return [
                'key' => $first['key'],
                'label' => $first['label'],
                'sport_group_key' => $first['sport_group_key'],
                'count' => (int) $group->sum('count'),
            ];
        })
        ->values()
        ->all();
    $leagueFilterGroups = $leagueFilterGroups ?? collect($leagueFilters)
        ->groupBy(fn (array $item) => $item['sport_group_key'] . '|' . ($item['group_key'] ?? 'other'))
        ->map(function ($group) {
            $first = $group->first();

            return [
                'key' => $first['group_key'] ?? 'other',
                'label' => $first['group_label'] ?? $sbText('filters.tournaments', 'Turnuvalar'),
                'sport_group_key' => $first['sport_group_key'] ?? 'all',
                'count' => (int) $group->sum('count'),
                'event_count' => (int) $group->sum('count'),
                'item_count' => $group->count(),
                'items' => collect($group)->sortBy([
                    ['count', 'desc'],
                    ['label', 'asc'],
                ])->values()->all(),
            ];
        })
        ->sortBy([
            ['count', 'desc'],
            ['label', 'asc'],
        ])
        ->values()
        ->all();
    $footballPremiumCoverageGaps = collect($footballPremiumCoverageGaps ?? [])->values();
    $footballPremiumCoverageGapCount = $footballPremiumCoverageGaps->count();
    $predictionItems = collect($predictionItems ?? [])->values();
    $predictionStats = (array) ($predictionStats ?? []);
    $socialMarketPayload = (array) ($socialMarketPayload ?? ['categories' => [], 'markets' => [], 'stats' => []]);
    $socialMarketCategories = collect($socialMarketPayload['categories'] ?? [])->values();
    $socialMarketItems = collect($socialMarketPayload['markets'] ?? [])->values();
    $esportsSocialMarketPayload = (array) ($esportsSocialMarketPayload ?? $socialMarketPayload);
    $esportsSocialMarketSourceCategories = collect($esportsSocialMarketPayload['categories'] ?? [])->values();
    $esportsSocialMarketSourceItems = collect($esportsSocialMarketPayload['markets'] ?? [])->values();
    $esportsSocialMarketCategoryKey = 'siber-spor';
    $esportsSocialMarketItems = $esportsSocialMarketSourceItems
        ->filter(fn (array $market): bool => (string) data_get($market, 'category_key') === $esportsSocialMarketCategoryKey)
        ->filter(fn (array $market): bool => (string) data_get($market, 'render.editorial_group') === 'esports_manual_v0')
        ->values();
    $esportsSocialMarketCategories = $esportsSocialMarketSourceCategories
        ->filter(fn (array $category): bool => (string) data_get($category, 'key') === $esportsSocialMarketCategoryKey)
        ->map(function (array $category) use ($esportsSocialMarketItems): array {
            $category['count'] = $esportsSocialMarketItems->count();

            return $category;
        })
        ->values();
    $betFeedUi = $betFeedUi ?? [
        'title' => __('cms_public.sportsbook.bet_feed.title'),
        'match' => __('cms_public.sportsbook.bet_feed.match'),
        'selection' => __('cms_public.sportsbook.bet_feed.selection'),
        'odds' => __('cms_public.sportsbook.bet_feed.odds'),
        'stake' => __('cms_public.sportsbook.bet_feed.stake'),
        'potential_win' => __('cms_public.sportsbook.bet_feed.potential_win'),
        'user' => __('cms_public.sportsbook.bet_feed.user'),
        'add_to_betslip' => __('cms_public.sportsbook.bet_feed.add_to_betslip'),
        'adding' => __('cms_public.sportsbook.bet_feed.adding'),
        'added' => __('cms_public.sportsbook.bet_feed.added'),
        'failed' => __('cms_public.sportsbook.bet_feed.failed'),
        'closed' => __('cms_public.sportsbook.bet_feed.closed'),
        'closed_to_betslip' => __('cms_public.sportsbook.bet_feed.closed_to_betslip'),
        'match_started' => __('cms_public.sportsbook.bet_feed.match_started'),
        'odds_updated' => __('cms_public.sportsbook.bet_feed.odds_updated'),
        'market_closed' => __('cms_public.sportsbook.bet_feed.market_closed'),
        'copyable_empty' => __('cms_public.sportsbook.bet_feed.copyable_empty'),
        'empty' => __('cms_public.sportsbook.bet_feed.empty'),
        'coupon_detail' => __('cms_public.sportsbook.bet_feed.coupon_detail'),
        'shared_at' => __('cms_public.sportsbook.bet_feed.shared_at'),
        'shared_at_prefix' => __('cms_public.sportsbook.bet_feed.shared_at_prefix'),
        'some_time_ago' => __('cms_public.sportsbook.bet_feed.some_time_ago'),
        'just_now' => __('cms_public.sportsbook.bet_feed.just_now'),
        'minutes_ago_suffix' => __('cms_public.sportsbook.bet_feed.minutes_ago_suffix'),
        'hours_ago_suffix' => __('cms_public.sportsbook.bet_feed.hours_ago_suffix'),
        'datetime_locale' => __('cms_public.sportsbook.bet_feed.datetime_locale'),
        'league' => __('cms_public.sportsbook.bet_feed.league'),
        'selection_count_suffix' => __('cms_public.sportsbook.bet_feed.selection_count_suffix'),
    ];
    $betFeedUi = array_merge($betFeedUi, (array) __('cms_public.sportsbook.bet_feed'));
    $tabItems = $tabItems ?? [
        ['key' => 'pre-match', 'label' => $sbText('tabs.pre_match', 'Pre-match'), 'count' => $matchCount, 'active' => true],
        ['key' => 'in-game', 'label' => $sbText('tabs.in_game', 'In Game'), 'count' => 0, 'active' => false],
        ['key' => 'bet-feed', 'label' => $betFeedUi['title'], 'count' => count($betFeedItems ?? []), 'active' => false],
        ['key' => 'predictions', 'label' => $sbText('tabs.predictions', 'Tahminler'), 'count' => $predictionItems->count(), 'active' => false],
        ['key' => 'e-sports', 'label' => $sbText('tabs.e_sports', 'E-Sports'), 'count' => $esportsSocialMarketItems->count(), 'active' => false],
        ['key' => 'social-market', 'label' => $sbText('tabs.social_market', 'Sosyal Market'), 'count' => $socialMarketItems->count(), 'active' => false],
    ];
    $tabItems = collect($tabItems)
        ->map(function (array $item) use ($betFeedUi) {
            if (($item['key'] ?? '') === 'bet-feed') {
                $item['label'] = $betFeedUi['title'];
            }

            return $item;
        })
        ->values()
        ->all();
    $defaultTabKey = $defaultTabKey ?? 'pre-match';
    $initialPayloadDeferred = !empty($initialPayloadDeferred);
    $offersPanelActive = in_array($defaultTabKey, ['pre-match', 'in-game'], true);
    $betFeedPanelActive = $defaultTabKey === 'bet-feed';
    $predictionsPanelActive = $defaultTabKey === 'predictions';
    $esportsPanelActive = $defaultTabKey === 'e-sports';
    $socialMarketPanelActive = $defaultTabKey === 'social-market';
    $renderInitialOfferRows = $offersPanelActive && !$initialPayloadDeferred;
    $renderInitialBetFeedRows = $betFeedPanelActive && !$initialPayloadDeferred;
    $renderInitialPredictionCards = $predictionsPanelActive && !$initialPayloadDeferred;
    $renderInitialEsportsCards = true;
    $renderInitialSocialMarketCards = $socialMarketPanelActive && !$initialPayloadDeferred;
    $livePolling = $livePolling ?? [
        'enabled' => false,
        'offers_index_url' => '',
        'visible_interval_seconds' => 12,
        'background_interval_seconds' => 45,
        'upcoming_window_hours' => 24,
    ];
    $betFeedPolling = $betFeedPolling ?? [
        'enabled' => false,
        'items_url' => '',
        'interval_seconds' => 60,
    ];
    $predictionPolling = $predictionPolling ?? [
        'enabled' => false,
        'items_url' => '',
        'interval_seconds' => 3600,
        'limit' => 4,
    ];
    $socialMarketPolling = $socialMarketPolling ?? [
        'enabled' => false,
        'items_url' => '',
    ];
    $recentAcceptances = $recentAcceptances ?? [];
    $betFeedItems = collect($betFeedItems ?? [])->values();
    $visibleBetFeedRows = $renderInitialBetFeedRows ? $betFeedItems->take(12)->values() : collect();
    $betFeedStats = (array) ($betFeedStats ?? []);
    $livePollLimits = [
        'pre_match' => 150,
        'in_game' => 150,
        'bootstrap' => 150,
        'search' => 150,
        'next_matches' => 24,
        'featured_matches' => 24,
    ];
    $historyAllCount = count($recentAcceptances);
    $historyOpenCount = collect($recentAcceptances)
        ->filter(fn (array $item) => ($item['category'] ?? '') === 'open')
        ->count();
    $sportIconTokens = [
        'soccer' => ['type' => 'fa', 'class' => 'fas fa-futbol'],
        'tennis' => ['type' => 'fa', 'class' => 'fas fa-tennis-ball'],
        'basketball' => ['type' => 'fa', 'class' => 'fas fa-basketball-ball'],
        'baseball' => ['type' => 'fa', 'class' => 'fas fa-baseball-ball'],
        'icehockey' => ['type' => 'fa', 'class' => 'fas fa-hockey-puck'],
        'other' => ['type' => 'fa', 'class' => 'fas fa-shapes'],
        'snooker' => ['type' => 'fa', 'class' => 'fas fa-bullseye'],
        'table_tennis' => ['type' => 'fa', 'class' => 'fas fa-table-tennis'],
        'dota2' => ['type' => 'mono', 'value' => 'D2'],
        'cs2' => ['type' => 'mono', 'value' => 'CS'],
        'volleyball' => ['type' => 'fa', 'class' => 'fas fa-volleyball-ball'],
    ];
    $sportCardArtAssets = [
        'soccer' => ['path' => '/assets/cms/img/sportsbook/sports/soccer.svg', 'color' => '#0f9f6e'],
        'basketball' => ['path' => '/assets/cms/img/sportsbook/sports/basketball.svg', 'color' => '#d97706'],
        'tennis' => ['path' => '/assets/cms/img/sportsbook/sports/tennis.svg', 'color' => '#65a30d'],
        'baseball' => ['path' => '/assets/cms/img/sportsbook/sports/baseball.svg', 'color' => '#dc2626'],
        'icehockey' => ['path' => '/assets/cms/img/sportsbook/sports/icehockey.svg', 'color' => '#0891b2'],
        'volleyball' => ['path' => '/assets/cms/img/sportsbook/sports/volleyball.svg', 'color' => '#7c3aed'],
        'table_tennis' => ['path' => '/assets/cms/img/sportsbook/sports/table_tennis.svg', 'color' => '#16a34a'],
        'snooker' => ['path' => '/assets/cms/img/sportsbook/sports/snooker.svg', 'color' => '#0f766e'],
        'e-sports' => ['path' => '/assets/cms/img/sportsbook/sports/cs2.svg', 'color' => '#7c3aed'],
    ];
    $entityMarkHtml = static function (array $asset, string $label, string $type = 'entity'): \Illuminate\Support\HtmlString {
        $safePath = trim((string) data_get($asset, 'path', ''));
        $allowedRoots = (array) config('sportsbook_visual_assets.allowed_roots', []);
        $pathAllowed = $safePath !== ''
            && str_starts_with($safePath, '/')
            && !str_starts_with($safePath, '//')
            && !str_contains($safePath, '..')
            && !str_contains($safePath, '\\')
            && collect($allowedRoots)->contains(fn (string $root): bool => str_starts_with($safePath, $root));
        if (!$pathAllowed) {
            $safePath = '';
        }

        $placeholder = trim((string) data_get($asset, 'placeholder_label', ''));
        if ($placeholder === '') {
            $letters = preg_replace('/[^A-Za-z0-9]+/', '', $label) ?: 'SB';
            $placeholder = mb_strtoupper(mb_substr($letters, 0, 2));
        }

        $color = trim((string) data_get($asset, 'color', '#425466'));
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            $color = '#425466';
        }

        $class = 'sb2-entity-mark sb2-entity-mark--' . preg_replace('/[^a-z0-9_-]+/', '-', strtolower($type ?: 'entity'));
        $fallback = '<span class="sb2-entity-mark__fallback"' . ($safePath !== '' ? ' hidden' : '') . '>' . e(mb_strtoupper(mb_substr($placeholder, 0, 4))) . '</span>';
        $image = $safePath !== ''
            ? '<img class="sb2-entity-mark__image" src="' . e($safePath) . '" alt="" loading="lazy" data-entity-mark-image onerror="this.hidden=true;this.nextElementSibling.hidden=false;">'
            : '';

        return new \Illuminate\Support\HtmlString('<span class="' . e($class) . '" style="--sb2-entity-color: ' . e($color) . ';" aria-hidden="true">' . $image . $fallback . '</span>');
    };
    $sportCardArtHtml = static function (array $asset, string $label, string $sportKey = '') use ($entityMarkHtml, $sportCardArtAssets): \Illuminate\Support\HtmlString {
        $normalizedSportKey = trim(strtolower($sportKey));
        $fallbackAsset = (array) ($sportCardArtAssets[$normalizedSportKey] ?? []);
        $asset = array_filter($asset, static fn ($value): bool => $value !== null && $value !== '');
        $asset = array_replace($fallbackAsset, $asset);

        return new \Illuminate\Support\HtmlString('<span class="sb2-sport-card-art" aria-hidden="true">' . $entityMarkHtml($asset, $label, 'sport') . '</span>');
    };
    $marketTextMatches = static function ($value, array $needles): bool {
        $haystack = \Illuminate\Support\Str::lower((string) $value);

        foreach ($needles as $needle) {
            if ($needle !== '' && \Illuminate\Support\Str::contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    };
    $extraMarketHasKind = static function (array $match, array $needles) use ($marketTextMatches): bool {
        foreach ((array) ($match['extra_markets'] ?? []) as $market) {
            if ($marketTextMatches($market['title'] ?? '', $needles)) {
                return true;
            }

            foreach ((array) ($market['selections'] ?? []) as $selection) {
                if (
                    $marketTextMatches($selection['market_type'] ?? '', $needles)
                    || $marketTextMatches($selection['market_name'] ?? '', $needles)
                    || $marketTextMatches($selection['label'] ?? '', $needles)
                ) {
                    return true;
                }
            }
        }

        return false;
    };
    $marketFamilyMeta = static function (array $market, string $sportGroupKey): array {
        $explicitKey = (string) ($market['market_family_key'] ?? '');
        if ($explicitKey !== '') {
            $explicitLabel = (string) ($market['market_family_label'] ?? $market['title'] ?? $explicitKey);

            return [
                'key' => $explicitKey,
                'chip' => (string) ($market['market_family_chip'] ?? $explicitLabel),
                'label' => $explicitLabel,
                'priority' => (int) ($market['market_family_order'] ?? 999),
                'shell' => (bool) ($market['is_catalog_shell'] ?? false),
            ];
        }

        $text = \Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii((string) ($market['title'] ?? '')));

        foreach ((array) ($market['selections'] ?? []) as $selection) {
            $text .= ' ' . \Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii(implode(' ', array_filter([
                (string) ($selection['market_type'] ?? ''),
                (string) ($selection['market_name'] ?? ''),
                (string) ($selection['label'] ?? ''),
                (string) ($selection['outcome_code'] ?? ''),
            ]))));
        }

        $hasAny = static function (array $needles) use ($text): bool {
            foreach ($needles as $needle) {
                if ($needle !== '' && str_contains($text, $needle)) {
                    return true;
                }
            }

            return false;
        };

        if ($hasAny(['player', 'oyuncu'])) {
            return ['key' => 'player_markets', 'chip' => $sbText('markets.player', 'Oyuncu'), 'label' => $sbText('markets.player_markets', 'Oyuncu marketleri'), 'priority' => 90, 'shell' => true];
        }

        if ($sportGroupKey === 'basketball') {
            return match (true) {
                $hasAny(['quarter', 'ceyrek']) => ['key' => 'quarters', 'chip' => $sbText('markets.quarters', 'Çeyrekler'), 'label' => $sbText('markets.quarters', 'Çeyrekler'), 'priority' => 50, 'shell' => false],
                $hasAny(['half', 'ilk yari', '1st half', 'first half']) => ['key' => 'first_half', 'chip' => $sbText('markets.first_half', 'İlk Yarı'), 'label' => $sbText('markets.first_half', 'İlk Yarı'), 'priority' => 40, 'shell' => false],
                $hasAny(['margin', 'winning margin', 'kazanma marji']) => ['key' => 'margin', 'chip' => $sbText('markets.margin', 'Marj'), 'label' => $sbText('markets.winning_margin', 'Kazanma Marjı'), 'priority' => 60, 'shell' => false],
                $hasAny(['team total', 'team_total', 'takim toplam']) => ['key' => 'team_total', 'chip' => $sbText('markets.team', 'Takım'), 'label' => $sbText('markets.team_total', 'Takım Toplamı'), 'priority' => 35, 'shell' => false],
                $hasAny(['spread', 'handicap', 'handikap']) => ['key' => 'handicap', 'chip' => $sbText('markets.handicap', 'Handikap'), 'label' => $sbText('markets.handicap', 'Handikap'), 'priority' => 30, 'shell' => false],
                $hasAny(['total', 'toplam', 'points']) => ['key' => 'totals', 'chip' => $sbText('markets.total', 'Toplam'), 'label' => $sbText('markets.total_points', 'Toplam Sayı'), 'priority' => 20, 'shell' => false],
                $hasAny(['match_result', 'h2h', 'winner', 'moneyline']) => ['key' => 'main', 'chip' => $sbText('markets.main_chip', 'Ana'), 'label' => $sbText('markets.match_result', 'Maç Sonucu'), 'priority' => 10, 'shell' => false],
                default => ['key' => 'other', 'chip' => $sbText('markets.other', 'Diğer'), 'label' => $sbText('markets.other_markets', 'Diğer Marketler'), 'priority' => 80, 'shell' => false],
            };
        }

        if ($sportGroupKey === 'tennis') {
            return match (true) {
                $hasAny(['tie break', 'tie-break', 'tiebreak']) => ['key' => 'tiebreak', 'chip' => 'Tie-break', 'label' => 'Tie-break', 'priority' => 70, 'shell' => false],
                $hasAny(['set score', 'set_score']) => ['key' => 'set_score', 'chip' => $sbText('markets.set_score', 'Set Skoru'), 'label' => $sbText('markets.set_score', 'Set Skoru'), 'priority' => 60, 'shell' => false],
                $hasAny(['first set', '1st set', 'ilk set']) => ['key' => 'first_set', 'chip' => $sbText('markets.first_set', 'İlk Set'), 'label' => $sbText('markets.first_set', 'İlk Set'), 'priority' => 50, 'shell' => false],
                $hasAny(['set']) => ['key' => 'sets', 'chip' => $sbText('markets.sets', 'Setler'), 'label' => $sbText('markets.sets', 'Setler'), 'priority' => 45, 'shell' => false],
                $hasAny(['spread', 'handicap', 'handikap']) => ['key' => 'handicap', 'chip' => $sbText('markets.handicap', 'Handikap'), 'label' => $sbText('js.game_handicap', 'Game handikap'), 'priority' => 30, 'shell' => false],
                $hasAny(['total', 'toplam', 'games']) => ['key' => 'totals', 'chip' => $sbText('markets.total', 'Toplam'), 'label' => $sbText('markets.total_games', 'Toplam Oyun'), 'priority' => 20, 'shell' => false],
                $hasAny(['match_winner', 'match winner', 'h2h', 'winner']) => ['key' => 'main', 'chip' => $sbText('markets.main_chip', 'Ana'), 'label' => $sbText('markets.match_winner', 'Maç Kazananı'), 'priority' => 10, 'shell' => false],
                default => ['key' => 'other', 'chip' => $sbText('markets.other', 'Diğer'), 'label' => $sbText('markets.other_markets', 'Diğer Marketler'), 'priority' => 80, 'shell' => false],
            };
        }

        return match (true) {
            $hasAny(['corner', 'korner']) => ['key' => 'corners', 'chip' => $sbText('markets.corners', 'Kornerler'), 'label' => $sbText('markets.corners', 'Kornerler'), 'priority' => 70, 'shell' => false],
            $hasAny(['card', 'kart']) => ['key' => 'cards', 'chip' => $sbText('markets.cards', 'Kartlar'), 'label' => $sbText('markets.cards', 'Kartlar'), 'priority' => 75, 'shell' => false],
            $hasAny(['correct score', 'correct_score', 'dogru skor']) => ['key' => 'correct_score', 'chip' => $sbText('markets.score', 'Skor'), 'label' => $sbText('markets.correct_score', 'Doğru Skor'), 'priority' => 60, 'shell' => false],
            $hasAny(['half', 'ilk yari', '1st half', 'first half']) => ['key' => 'first_half', 'chip' => $sbText('markets.first_half', 'İlk Yarı'), 'label' => $sbText('markets.first_half', 'İlk Yarı'), 'priority' => 50, 'shell' => false],
            $hasAny(['both teams', 'btts', 'karsilikli']) => ['key' => 'btts', 'chip' => $sbText('markets.btts_short', 'KG Var'), 'label' => $sbText('markets.btts', 'Karşılıklı Gol'), 'priority' => 45, 'shell' => false],
            $hasAny(['double chance', 'double_chance', 'cifte sans']) => ['key' => 'double_chance', 'chip' => $sbText('markets.double_chance', 'Çifte Şans'), 'label' => $sbText('markets.double_chance', 'Çifte Şans'), 'priority' => 40, 'shell' => false],
            $hasAny(['spread', 'handicap', 'handikap']) => ['key' => 'handicap', 'chip' => $sbText('markets.handicap', 'Handikap'), 'label' => $sbText('markets.handicap', 'Handikap'), 'priority' => 30, 'shell' => false],
            $hasAny(['total', 'toplam', 'goals']) => ['key' => 'totals', 'chip' => $sbText('markets.total', 'Toplam'), 'label' => $sbText('markets.total', 'Toplam'), 'priority' => 20, 'shell' => false],
            $hasAny(['match_result', 'h2h', 'winner', '1x2']) => ['key' => 'main', 'chip' => $sbText('markets.main_chip', 'Ana'), 'label' => $sbText('markets.match_result', 'Maç Sonucu'), 'priority' => 10, 'shell' => false],
            default => ['key' => 'other', 'chip' => $sbText('markets.other', 'Diğer'), 'label' => $sbText('markets.other_markets', 'Diğer Marketler'), 'priority' => 80, 'shell' => false],
        };
    };
    $groupExtraMarketsByFamily = static function (array $markets, string $sportGroupKey) use ($marketFamilyMeta): array {
        return collect($markets)
            ->map(function (array $market) use ($marketFamilyMeta, $sportGroupKey) {
                $meta = $marketFamilyMeta($market, $sportGroupKey);
                $selections = collect((array) ($market['selections'] ?? []));
                $allCatalogShells = $selections->isNotEmpty()
                    && $selections->every(fn (array $selection) => !empty($selection['is_catalog_shell']) || ($selection['acceptance_mode'] ?? '') === 'catalog_shell');

                return [
                    'family_key' => $meta['key'],
                    'family_chip' => $meta['chip'],
                    'family_label' => $meta['label'],
                    'family_priority' => $meta['priority'],
                    'is_shell_family' => (bool) ($meta['shell'] ?? false) || $allCatalogShells,
                    'market' => $market,
                ];
            })
            ->groupBy('family_key')
            ->map(function ($items) {
                $first = $items->first();

                return [
                    'key' => $first['family_key'],
                    'chip' => $first['family_chip'],
                    'label' => $first['family_label'],
                    'priority' => $first['family_priority'],
                    'is_shell_family' => $items->contains(fn (array $item) => !empty($item['is_shell_family'])),
                    'markets' => $items->pluck('market')->values()->all(),
                ];
            })
            ->sortBy([
                ['priority', 'asc'],
                ['label', 'asc'],
            ])
            ->values()
            ->all();
    };
    $coreMarketHeadings = static function (array $match) use ($extraMarketHasKind, $sbText): array {
        $sportGroupKey = (string) ($match['sport_group_key'] ?? '');
        $matchResultCount = count((array) ($match['match_result'] ?? []));
        $totalsCount = count((array) ($match['totals'] ?? []));
        $hasExtraMarkets = !empty($match['extra_markets']);
        $headings = [];

        if ($matchResultCount > 0) {
            $headings[] = [
                'label' => $sportGroupKey === 'tennis' ? $sbText('markets.tennis_winner', 'Maç kazananı') : $sbText('markets.match_result_lower', 'Maç sonucu'),
                'span' => min(3, max(1, $matchResultCount)),
            ];
        }

        if ($totalsCount > 0) {
            $headings[] = [
                'label' => match ($sportGroupKey) {
                    'basketball' => $sbText('markets.total_points', 'Toplam sayı'),
                    'tennis' => $sbText('markets.total_games', 'Toplam oyun'),
                    default => $sbText('markets.total', 'Toplam'),
                },
                'span' => min(2, max(1, $totalsCount)),
            ];
        }

        if ($hasExtraMarkets) {
            $hasHandicap = $extraMarketHasKind($match, ['spread', 'spreads', 'handicap', 'handikap']);
            $headings[] = [
                'label' => match (true) {
                    $sportGroupKey === 'basketball' && $hasHandicap => $sbText('markets.handicap', 'Handikap'),
                    $sportGroupKey === 'tennis' && $hasHandicap => $sbText('js.game_handicap', 'Game handikap'),
                    default => $sbText('js.extra_markets', 'Ek marketler'),
                },
                'span' => 1,
                'secondary' => true,
            ];
        }

        return $headings;
    };
@endphp

@if(!empty($sportsbookLocalParity['enabled']) && !app()->environment('local'))
    <div class="sb2-local-sandbox-banner" role="status" aria-live="polite">
        <span class="sb2-local-sandbox-banner__badge">LOCAL SANDBOX</span>
        <span>Live read / local write</span>
    </div>
@endif

<div
    class="sb2-layout"
    data-default-tab="{{ $defaultTabKey }}"
    data-acceptance-api-url="{{ route('cms.api.betting.acceptances.store') }}"
    data-adapter-acceptance-api-url="{{ route('cms.api.betting.acceptances.adapter-vps.store') }}"
    data-bahisbot-research-url="{{ url('/cms/sportsbook/unavailable/bahisbot') }}"
    data-community-share-url="{{ url('/cms/sportsbook/unavailable/community') }}"
    data-offer-event-api-template="{{ url('cms/api/betting/offers/events/__EVENT_ID__') }}"
    data-offers-index-api-url="{{ $livePolling['offers_index_url'] ?? '' }}"
    data-catalog-tree-api-url="{{ route('cms.front.sportsbook.catalogTree',['mode'=>$mode??'B']) }}"
        data-live-poll-enabled="{{ !empty($livePolling['enabled']) ? '1' : '0' }}"
        data-live-poll-visible-seconds="{{ $livePolling['visible_interval_seconds'] ?? 12 }}"
        data-live-poll-background-seconds="{{ $livePolling['background_interval_seconds'] ?? 45 }}"
        data-upcoming-window-hours="{{ $livePolling['upcoming_window_hours'] ?? 24 }}"
        data-display-timezone="{{ $displayTimezone ?? config('app.timezone', 'UTC') }}"
        data-display-timezone-cookie-name="{{ $displayTimezoneCookieName ?? 'cms_display_timezone' }}"
        data-wallet-snapshot-url="{{ $viewer['wallet_snapshot_url'] ?? '' }}"
    data-viewer-id="{{ $viewer['id'] ?? '' }}"
    data-viewer-authenticated="{{ !empty($viewer['authenticated']) ? '1' : '0' }}"
    data-viewer-balance="{{ number_format((float) ($viewer['balance'] ?? 0), 2, '.', '') }}"
    data-viewer-currency="{{ $viewer['currency'] ?? 'TRY' }}"
    data-viewer-max-stake="{{ isset($viewer['max_stake']) ? number_format((float) $viewer['max_stake'], 2, '.', '') : '' }}"
    data-viewer-max-stake-formatted="{{ $viewer['max_stake_formatted'] ?? '' }}"
    data-viewer-deposit-url="{{ $viewer['deposit_url'] ?? '' }}"
    data-viewer-finance-report-url="{{ $viewer['finance_report_url'] ?? '' }}"
    data-initial-payload-deferred="{{ $initialPayloadDeferred ? '1' : '0' }}"
    data-initial-hydration-state="{{ $initialPayloadDeferred ? 'pending' : 'ready' }}"
    data-board-state="{{ $initialPayloadDeferred ? 'not_requested' : 'ready' }}"
    data-board-placeholder="false"
    data-default-sport-filter="{{ $defaultSportFilter ?? 'soccer' }}"
    data-summary-state="{{ $summaryDebugState }}"
    data-summary-guard-reason="{{ $summaryDebugGuardReason }}"
    data-summary-last-healthy-generated-at="{{ $summaryDebugLastHealthyGeneratedAt }}"
    data-summary-raw-generated-at="{{ $summaryDebugRawGeneratedAt }}"
    data-summary-raw-total="{{ $summaryDebugRawTotal }}"
    data-live-poll-prematch-limit="{{ $livePollLimits['pre_match'] }}"
    data-live-poll-ingame-limit="{{ $livePollLimits['in_game'] }}"
    data-live-poll-bootstrap-limit="{{ $livePollLimits['bootstrap'] }}"
    data-live-poll-search-limit="{{ $livePollLimits['search'] }}"
    data-live-poll-next-matches-limit="{{ $livePollLimits['next_matches'] ?? 24 }}"
    data-live-poll-featured-matches-limit="{{ $livePollLimits['featured_matches'] ?? 24 }}"
>
    <script>
        (function () {
            if (window.__sportsbookInitialOffers || typeof window.fetch !== 'function') {
                return;
            }

            var script = document.currentScript;
            var layout = script ? script.closest('.sb2-layout[data-initial-payload-deferred="1"]') : null;
            if (!layout || layout.dataset.livePollEnabled !== '1' || !layout.dataset.offersIndexApiUrl) {
                return;
            }

            try {
                var endpoint = new URL(layout.dataset.offersIndexApiUrl, window.location.origin);
                var pageParams = new URLSearchParams(window.location.search);
                var initialSport = pageParams.get('sport') || layout.dataset.defaultSportFilter || 'soccer';
                endpoint.searchParams.set('limit', '24');
                endpoint.searchParams.set('phase_key', 'pre-match');
                endpoint.searchParams.set('sport_group_key', initialSport);
                endpoint.searchParams.set('surface', 'initial');
                endpoint.searchParams.set('defer_contracts', '1');
                var requestUrl = endpoint.toString();
                if (window.performance && typeof window.performance.mark === 'function') {
                    window.performance.mark('sportsbook-early-initial-request-started');
                }
                window.__sportsbookInitialOffers = {
                    url: requestUrl,
                    startedAt: Date.now(),
                    promise: window.fetch(requestUrl, {
                        method: 'GET',
                        credentials: 'same-origin',
                        cache: 'no-store',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-Display-Timezone': layout.dataset.displayTimezone || ''
                        }
                    }).then(function (response) {
                        return response.json().then(function (payload) {
                            return {
                                ok: response.ok,
                                status: response.status,
                                payload: payload
                            };
                        });
                    })
                };
            } catch (error) {
                window.__sportsbookInitialOffers = null;
            }
        })();
    </script>
    <div class="sb2-main">
        <div class="sb2-offline-demo-notice" data-offline-demo-notice role="status" @if(empty($offlineDemo['active'])) hidden @endif>
            <strong>{{ $sbText('js.offline_demo_title') }}</strong>
            <span data-offline-demo-copy>{{ $sbText('js.offline_demo_no_submit') }}</span>
            <time data-offline-demo-time>{{ $offlineDemo['generated_at'] ?? '' }}</time>
        </div>
        <div class="sb2-topbar">
            <div class="sb2-primary-nav" aria-label="{{ $sbText('tabs.navigation', 'Sportsbook sekmeleri') }}">
                @foreach ($tabItems as $tabItem)
                    @php($isDefaultTab = ($tabItem['active'] ?? false) || $tabItem['key'] === $defaultTabKey)
                    @php($tabKey = (string) ($tabItem['key'] ?? 'pre-match'))
                    @php($countDeferred = $initialPayloadDeferred && in_array($tabKey, ['pre-match', 'in-game', 'bet-feed', 'predictions', 'social-market'], true))
                    @php($tabCount = (int) ($tabItem['count'] ?? 0))
                    <button
                        type="button"
                        class="sb2-primary-tab {{ $isDefaultTab ? 'is-active' : '' }}"
                        data-tab="{{ $tabKey }}"
                        data-tab-trigger="{{ $tabKey }}"
                        @if($countDeferred) data-count-deferred="1" @endif
                        aria-selected="{{ $isDefaultTab ? 'true' : 'false' }}"
                    >
                        <span>{{ $tabItem['label'] }}</span>
                        <strong
                            class="sb2-primary-tab-count"
                            data-tab-count
                            data-count-raw="{{ $tabCount }}"
                        >{{ $countDeferred ? '—' : $formatCountLabel($tabCount) }}</strong>
                    </button>
                @endforeach
            </div>

            <div class="sb2-topbar-actions">
                <button
                    type="button"
                    class="sb2-search-btn sb2-catalog-btn"
                    data-catalog-modal-open
                    aria-label="{{ $sbText('catalog.button', 'Katalog') }}"
                    aria-expanded="false"
                    aria-controls="sb2CatalogModal"
                    title="{{ $sbText('catalog.button', 'Katalog') }}"
                >
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M4 6.5h6.5M4 12h10M4 17.5h7M16.5 5.5l3 3-3 3M13.5 8.5h6" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"/>
                    </svg>
                    <span class="sb2-catalog-btn-label">{{ $sbText('catalog.button', 'Katalog') }}</span>
                </button>
                <button
                    type="button"
                    class="sb2-search-btn sb2-my-bets-btn"
                    data-my-bets-open
                    aria-label="{{ $sbText('history.panel_title', 'Bahislerim') }}"
                    aria-expanded="false"
                    aria-controls="sb2MyBetsPanel"
                    title="{{ $sbText('history.panel_title', 'Bahislerim') }}"
                >
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M6.5 4.5h11a2 2 0 0 1 2 2v2a2.5 2.5 0 0 0 0 5v4a2 2 0 0 1-2 2h-11a2 2 0 0 1-2-2v-4a2.5 2.5 0 0 0 0-5v-2a2 2 0 0 1 2-2Z" fill="none" stroke="currentColor" stroke-linejoin="round" stroke-width="1.8"/>
                        <path d="M9 9h6M9 12h4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="1.8"/>
                    </svg>
                    <span class="sb2-my-bets-btn-label">{{ $sbText('history.panel_title', 'Bahislerim') }}</span>
                    <span class="sb2-my-bets-btn-count" data-history-count="all" @if($historyAllCount === 0) hidden @endif>{{ $historyAllCount }}</span>
                </button>
                <button type="button" class="sb2-search-btn" data-search-toggle aria-label="{{ $sbText('filters.search_open', 'Maç aramasını aç') }}" aria-expanded="false" aria-controls="sb2SearchPanel">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M15.5 15.5L20 20M10.5 17a6.5 6.5 0 1 1 0-13 6.5 6.5 0 0 1 0 13Z" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"/>
                    </svg>
                </button>
                <button
                    type="button"
                    class="sb2-search-btn sb2-theme-btn"
                    data-theme-toggle
                    aria-label="{{ $sportsbookTheme === 'dark_blend' ? $sbText('common.switch_to_light_theme', 'Açık temaya geç') : $sbText('common.switch_to_dark_theme', 'Koyu temaya geç') }}"
                    aria-pressed="{{ $sportsbookTheme === 'dark_blend' ? 'true' : 'false' }}"
                    title="{{ $sportsbookTheme === 'dark_blend' ? $sbText('common.switch_to_light_theme', 'Açık temaya geç') : $sbText('common.switch_to_dark_theme', 'Koyu temaya geç') }}"
                >
                    @if ($sportsbookTheme === 'dark_blend')
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 3.5v2.25M12 18.25v2.25M4.75 12H2.5M21.5 12h-2.25M6.1 6.1 4.5 4.5M19.5 19.5l-1.6-1.6M17.9 6.1l1.6-1.6M4.5 19.5l1.6-1.6M12 7.25A4.75 4.75 0 1 1 7.25 12 4.76 4.76 0 0 1 12 7.25Z" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"/>
                        </svg>
                    @else
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M20 15.5A8.5 8.5 0 1 1 8.5 4a6.7 6.7 0 0 0 11.5 11.5Z" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"/>
                        </svg>
                    @endif
                </button>
            </div>
        </div>

        <div class="sb2-toolbar-panel sb2-search-modal" data-toolbar-panel hidden aria-hidden="true">
            <button type="button" class="sb2-search-modal-backdrop" data-search-panel-close aria-label="{{ $sbText('filters.search_close', 'Aramayı kapat') }}"></button>
            <section class="sb2-toolbar-search-panel sb2-search-modal-panel" id="sb2SearchPanel" data-search-panel hidden role="dialog" aria-modal="true" aria-labelledby="sb2SearchPanelTitle">
                <div class="sb2-search-modal-head">
                    <div>
                        <span class="sb2-search-modal-kicker">Sportsbook</span>
                        <h3 id="sb2SearchPanelTitle">{{ $sbText('filters.search_title', 'Maç ara') }}</h3>
                    </div>
                    <button type="button" class="sb2-search-modal-close" data-search-panel-close aria-label="{{ $sbText('filters.search_close', 'Aramayı kapat') }}">
                        <i class="fas fa-times" aria-hidden="true"></i>
                    </button>
                </div>
                <label class="sb2-toolbar-panel-label" for="sb2EventSearchInput">{{ $sbText('filters.search_title', 'Maç ara') }}</label>
                <div class="sb2-toolbar-search-shell">
                    <span class="sb2-search-modal-icon" aria-hidden="true">
                        <i class="fas fa-search"></i>
                    </span>
                    <input type="search" id="sb2EventSearchInput" class="sb2-toolbar-search-input" data-event-search-input placeholder="{{ $sbText('filters.search_placeholder', 'Örn. Barcelona, NBA, World Cup') }}">
                    <button type="button" class="sb2-toolbar-search-clear" data-event-search-clear hidden>{{ $sbText('common.close', 'Kapat') }}</button>
                </div>
                <div class="sb2-search-modal-meta">
                    <span data-event-search-count></span>
                    <strong>{{ $sbText('filters.search_scope', 'Sportsbook ve Sosyal Market') }}</strong>
                </div>
            </section>
        </div>

        <div class="sb2-catalog-modal" id="sb2CatalogModal" data-catalog-modal hidden>
            <div class="sb2-catalog-modal__backdrop" data-catalog-modal-close></div>
            <section class="sb2-catalog-modal__panel" role="dialog" aria-modal="true" aria-labelledby="sb2CatalogModalTitle">
                <header class="sb2-catalog-modal__header">
                    <button type="button" class="sb2-catalog-modal__icon-button" data-catalog-modal-back aria-label="{{ $sbText('common.back', 'Geri') }}">
                        <i class="fas fa-arrow-left" aria-hidden="true"></i>
                    </button>
                    <h2 id="sb2CatalogModalTitle" class="sb2-catalog-modal__title">
                        <span class="sb2-catalog-modal__title-icon" data-catalog-modal-title-icon aria-hidden="true"><i class="fas fa-globe"></i></span>
                        <span data-catalog-modal-title>{{ $sbText('catalog.fallback', 'Katalog') }}</span>
                    </h2>
                    <button type="button" class="sb2-catalog-modal__icon-button sb2-catalog-modal__close" data-catalog-modal-close aria-label="{{ $sbText('common.close', 'Kapat') }}">
                        <i class="fas fa-xmark" aria-hidden="true"></i>
                    </button>
                </header>
                <div class="sb2-catalog-modal__body">
                    <p class="sb2-catalog-modal__status" data-catalog-modal-status>{{ $sbText('catalog.loading', 'Katalog yükleniyor...') }}</p>
                    <nav class="sb2-catalog-modal__crumbs" data-catalog-crumbs aria-label="{{ $sbText('catalog.location', 'Katalog konumu') }}" hidden></nav>
                    <div class="sb2-catalog-modal__list" data-catalog-list></div>
                    <div class="sb2-catalog-modal__empty" data-catalog-empty hidden>{{ $sbText('common.no_active_match', 'Aktif maç yok') }}</div>
                </div>
            </section>
        </div>

        <div class="sb2-my-bets-modal" id="sb2MyBetsPanel" data-my-bets-panel hidden aria-hidden="true">
            <button type="button" class="sb2-my-bets-modal__backdrop" data-my-bets-close aria-label="{{ $sbText('history.close_panel', 'Bahislerim panelini kapat') }}"></button>
            <section class="sb2-my-bets-modal__panel" role="dialog" aria-modal="true" aria-labelledby="sb2MyBetsTitle">
                <header class="sb2-my-bets-modal__header">
                    <div class="sb2-my-bets-modal__heading">
                        <span class="sb2-my-bets-modal__mark" aria-hidden="true">
                            <svg viewBox="0 0 24 24">
                                <path d="M6.5 4.5h11a2 2 0 0 1 2 2v2a2.5 2.5 0 0 0 0 5v4a2 2 0 0 1-2 2h-11a2 2 0 0 1-2-2v-4a2.5 2.5 0 0 0 0-5v-2a2 2 0 0 1 2-2Z" fill="none" stroke="currentColor" stroke-linejoin="round" stroke-width="1.8"/>
                                <path d="M9 9h6M9 12h4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="1.8"/>
                            </svg>
                        </span>
                        <div>
                            <h2 id="sb2MyBetsTitle">{{ $sbText('history.panel_title', 'Bahislerim') }}</h2>
                            <p>{{ $sbText('history.caption', 'Açık ve son sonuçlanan seçimlerini burada takipleyebilirsin.') }}</p>
                        </div>
                    </div>
                    <button type="button" class="sb2-my-bets-modal__close" data-my-bets-close aria-label="{{ $sbText('history.close_panel', 'Bahislerim panelini kapat') }}">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M7 7l10 10M17 7 7 17" fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="1.9"/>
                        </svg>
                    </button>
                </header>

                <div class="sb2-history-card" data-history-card>
                    <div class="sb2-history-tabs" role="tablist" aria-label="{{ $sbText('history.filter_aria', 'Kupon durum filtreleri') }}">
                        <button type="button" class="sb2-history-tab is-active" role="tab" data-history-filter="all" data-history-supported="1" aria-selected="true">
                            <span>{{ $sbText('history.all', 'Hepsi') }}</span>
                            <strong data-history-count="all">{{ $historyAllCount }}</strong>
                        </button>
                        <button type="button" class="sb2-history-tab" role="tab" data-history-filter="open" data-history-supported="1" aria-selected="false">
                            <span>{{ $sbText('history.open_bets', 'Açık bahisler') }}</span>
                            <strong data-history-count="open">{{ $historyOpenCount }}</strong>
                        </button>
                        @foreach ([
                            'won' => 'won_bets',
                            'lost' => 'lost_bets',
                            'cashed_out' => 'cashed_out_bets',
                            'cancelled' => 'cancelled_bets',
                            'refunded' => 'refunded_bets',
                        ] as $unsupportedHistoryKey => $unsupportedHistoryLabel)
                            <button
                                type="button"
                                class="sb2-history-tab is-disabled"
                                role="tab"
                                data-history-filter="{{ $unsupportedHistoryKey }}"
                                data-history-supported="0"
                                aria-disabled="true"
                                title="{{ $sbText('history.coming_soon_hint', 'Bu görünüm yakında aktif olacak.') }}"
                                disabled
                            >
                                <span>{{ $sbText('history.' . $unsupportedHistoryLabel, $unsupportedHistoryLabel) }}</span>
                                <em>{{ $sbText('surface_states.surface_soon', 'Yakında') }}</em>
                            </button>
                        @endforeach
                    </div>

                    <div class="sb2-history-loading" data-history-loading hidden aria-hidden="true">
                        <span></span><span></span><span></span>
                    </div>

                    <div class="sb2-history-empty" data-history-empty @if(!empty($recentAcceptances)) hidden @endif>
                        <span class="sb2-history-empty__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24">
                                <path d="M6.5 4.5h11a2 2 0 0 1 2 2v2a2.5 2.5 0 0 0 0 5v4a2 2 0 0 1-2 2h-11a2 2 0 0 1-2-2v-4a2.5 2.5 0 0 0 0-5v-2a2 2 0 0 1 2-2Z" fill="none" stroke="currentColor" stroke-linejoin="round" stroke-width="1.8"/>
                            </svg>
                        </span>
                        <strong>{{ !empty($viewer['authenticated']) ? $sbText('history.empty_member_title', 'Henüz gösterilecek bahis yok.') : $sbText('history.empty_guest_title', 'Bahislerini görmek için giriş yap.') }}</strong>
                        <p>{{ !empty($viewer['authenticated']) ? $sbText('history.empty_member_body', 'Açık bahislerin ve geçmiş kuponların burada görünecek.') : $sbText('history.empty_guest_body', 'Giriş yaptıktan sonra açık bahislerin ve kupon geçmişin burada görünecek.') }}</p>
                    </div>

                    <div class="sb2-history-items" data-history-items @if(empty($recentAcceptances)) hidden @endif>
                        @foreach ($recentAcceptances as $acceptance)
                            <article class="sb2-history-item" data-history-item data-history-category="{{ $acceptance['category'] }}">
                                <div class="sb2-history-item-top">
                                    <div>
                                        <span class="sb2-history-item-kicker">{{ $sbText('history.bet_coupon', 'Bahis kuponu') }}</span>
                                        <strong>{{ $acceptance['event_name'] }}</strong>
                                    </div>
                                    <time>{{ $acceptance['requested_at_label'] }}</time>
                                </div>
                                <div class="sb2-history-item-mid">
                                    <span>{{ $acceptance['market_name'] }}</span>
                                    <strong>{{ $acceptance['outcome_name'] }}</strong>
                                </div>
                                <div class="sb2-history-item-metrics">
                                    <span><small>{{ $sbText('history.stake_amount', 'Bahis tutarı') }}</small><strong>{{ $acceptance['stake_amount'] }} {{ $acceptance['currency'] }}</strong></span>
                                    <span><small>{{ $sbText('history.odds', 'Oran') }}</small><strong>{{ $acceptance['accepted_price'] }}</strong></span>
                                </div>
                                <footer class="sb2-history-item-footer">
                                    <span class="sb2-history-status">{{ $acceptance['status_label'] }}</span>
                                    <button type="button" class="sb2-history-detail-toggle" data-history-detail-toggle aria-expanded="false" aria-label="{{ $sbText('history.show_detail', 'Kupon ayrıntılarını göster') }}">
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.9"/></svg>
                                    </button>
                                </footer>
                                <div class="sb2-history-item-detail" data-history-item-detail hidden>
                                    <span>{{ $sbText('history.short_reference', 'Kısa referans') }}</span>
                                    <div class="sb2-history-ref">
                                        <code>{{ $acceptance['short_acceptance_ref'] ?? '-' }}</code>
                                        <button type="button" class="sb2-history-copy" data-copy-text="{{ $acceptance['acceptance_ref'] }}" aria-label="{{ $sbText('history.copy_ref', 'Referansı kopyala') }}" title="{{ $sbText('history.copy_ref', 'Referansı kopyala') }}">
                                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                                <path d="M9 9.5A2.5 2.5 0 0 1 11.5 7h5A2.5 2.5 0 0 1 19 9.5v7a2.5 2.5 0 0 1-2.5 2.5h-5A2.5 2.5 0 0 1 9 16.5v-7Zm-4 2A2.5 2.5 0 0 1 7.5 9" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"/>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>

                    <div class="sb2-history-pagination" data-history-pagination hidden>
                        <button type="button" class="sb2-history-pagination-btn" data-history-page-prev>{{ $sbText('common.previous', 'Önceki') }}</button>
                        <div class="sb2-history-pagination-label" data-history-page-label>1 / 1</div>
                        <button type="button" class="sb2-history-pagination-btn" data-history-page-next>{{ $sbText('common.next', 'Sonraki') }}</button>
                    </div>
                </div>
            </section>
        </div>

        <div class="sb2-tab-panels">
            <div class="sb2-tab-panel {{ $offersPanelActive ? 'is-active' : '' }}" data-tab-panel="pre-match" aria-hidden="{{ $offersPanelActive ? 'false' : 'true' }}" @if(!$offersPanelActive) hidden @endif>
                <div class="sb2-sports-strip-shell">
                    <button type="button" class="sb2-sport-arrow sb2-sport-arrow--prev" data-sport-scroll-prev aria-label="{{ $sbText('filters.slide_left', 'Kategorileri sola kaydır') }}">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M15 6L9 12L15 18" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"/>
                        </svg>
                    </button>

                    <div class="sb2-sports-strip" data-sport-strip role="tablist" aria-label="{{ $sbText('filters.sports_categories', 'Spor kategorileri') }}">
                    @php($providerSportRailPending = $initialPayloadDeferred && empty($sports))
                    @if($initialPayloadDeferred && empty($sports))
                        @for($sportSkeletonIndex = 0; $sportSkeletonIndex < 5; $sportSkeletonIndex++)
                            <div class="sb2-sport-card sb2-sport-card--skeleton is-loading" data-sport-strip-loading aria-hidden="true">
                                <span class="sb2-sport-label"></span>
                                <span class="sb2-sport-count"></span>
                                <span class="sb2-sport-icon-shell" aria-hidden="true">
                                    <span class="sb2-sport-monogram"></span>
                                </span>
                            </div>
                        @endfor
                    @endif
                    @foreach ($sports as $sport)
                        <button
                            type="button"
                            class="sb2-sport-card {{ $sport['active'] ? 'is-active' : '' }}"
                            data-sport-filter="{{ $sport['key'] }}"
                            role="tab"
                            aria-selected="{{ $sport['active'] ? 'true' : 'false' }}"
                        >
                            {!! $sportCardArtHtml((array) ($sport['asset_meta'] ?? []), (string) ($sport['label'] ?? ''), (string) ($sport['key'] ?? '')) !!}
                            <span class="sb2-sport-label">{{ $sport['label'] }}</span>
                            @if(isset($sport['count']))
                                @php($sportCount = (int) ($sport[$activePhaseCountKey] ?? $sport['count']))
                                <span class="sb2-sport-count" data-count-raw="{{ $sportCount }}">{{ $formatCountLabel($sportCount) }}</span>
                            @endif
                            <span class="sb2-sport-icon-shell" aria-hidden="true">
                                {!! $entityMarkHtml((array) ($sport['asset_meta'] ?? []), (string) ($sport['label'] ?? ''), 'sport') !!}
                            </span>
                        </button>
                    @endforeach
                    @if($esportsSocialMarketItems->count() > 0)
                        <button
                            type="button"
                            class="sb2-sport-card sb2-sport-card--surface {{ $defaultTabKey === 'e-sports' ? 'is-active' : '' }}"
                            data-tab-switch="e-sports"
                            data-surface-sport-switch
                            role="tab"
                            aria-selected="{{ $defaultTabKey === 'e-sports' ? 'true' : 'false' }}"
                        >
                            {!! $sportCardArtHtml(['color' => '#7c3aed', 'placeholder_label' => 'ES'], $sbText('tabs.esports', 'E-Sports'), 'e-sports') !!}
                            <span class="sb2-sport-label">{{ $sbText('tabs.esports', 'E-Sports') }}</span>
                            <span class="sb2-sport-count" data-count-raw="{{ $esportsSocialMarketItems->count() }}">{{ $formatCountLabel($esportsSocialMarketItems->count()) }}</span>
                            <span class="sb2-sport-icon-shell" aria-hidden="true">
                                <span class="sb2-sport-monogram">ES</span>
                            </span>
                        </button>
                    @endif
                    </div>

                    <div class="sb2-sport-fade"></div>
                    <button type="button" class="sb2-sport-arrow sb2-sport-arrow--next" data-sport-scroll-next aria-label="{{ $sbText('filters.slide_right', 'Kategorileri sağa kaydır') }}">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M9 6L15 12L9 18" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"/>
                        </svg>
                    </button>
                </div>

                <div class="sb2-time-utility-row" data-time-utility-row>
                    @if (!empty($featuredAvailable) && app(\App\Betting\Support\BettingInterestScoringService::class)->featuredEnabled())
                    <button type="button" class="sb2-time-utility-chip" data-featured-matches-toggle aria-pressed="false">
                        <span class="sb2-time-utility-dot" aria-hidden="true"></span>
                        <span>{{ $sbText('common.featured', 'Öne çıkanlar') }}</span>
                    </button>
                    @endif
                    <button type="button" class="sb2-time-utility-chip" data-next-matches-toggle aria-pressed="false">
                        <span class="sb2-time-utility-dot" aria-hidden="true"></span>
                        <span>{{ $sbText('common.upcoming', 'Sıradaki maçlar') }}</span>
                    </button>
                    <button type="button" class="sb2-time-utility-chip" data-all-matches-toggle aria-pressed="false">
                        <span class="sb2-time-utility-dot" aria-hidden="true"></span>
                        <span>{{ $sbText('common.all', 'Tüm maçlar') }}</span>
                    </button>
                    <button
                        type="button"
                        class="sb2-time-utility-chip sb2-time-utility-chip--active-filter is-active"
                        data-active-filter-chip
                        data-active-filter-clear
                        aria-label="{{ $sbText('filters.clear_active_filter', 'Aktif turnuva filtresini temizle') }}"
                        hidden
                    >
                        <span class="sb2-time-utility-dot" aria-hidden="true"></span>
                        <span data-active-filter-label>{{ $sbText('filters.active_filter', 'Filtre') }}</span>
                        <strong aria-hidden="true">×</strong>
                    </button>
                </div>

                <div class="sb2-market-grid">
                    <aside class="sb2-competition-rail" data-league-filter-panel>
                        <div class="sb2-competition-rail-card">
                            <button type="button" class="sb2-competition-rail-header sb2-competition-rail-header--compact" data-competition-rail-toggle aria-expanded="true" aria-label="{{ $sbText('filters.toggle_tournaments', 'Turnuva filtrelerini daralt veya genişlet') }}">
                                <div class="sb2-competition-rail-meta">
                                    <span class="sb2-competition-rail-title">{{ $sbText('filters.tournaments', 'Turnuvalar') }}</span>
                                    <span class="sb2-competition-rail-meta-counts">
                                        <span class="sb2-competition-rail-count" data-competition-rail-count data-count-raw="{{ count($leagueFilters) }}">{{ $formatCountLabel(count($leagueFilters)) }} {{ $sbText('common.tournaments', 'turnuva') }}</span>
                                        <span class="sb2-competition-rail-meta-text" data-competition-rail-active-count>{{ $formatCountLabel($matchCount) }} {{ $sbText('common.matches', 'maç') }}</span>
                                    </span>
                                </div>
                                <span class="sb2-competition-rail-chevron" aria-hidden="true">
                                    <svg viewBox="0 0 24 24">
                                        <path d="M6 9L12 15L18 9" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"/>
                                    </svg>
                                </span>
                            </button>

                            <div class="sb2-competition-rail-body" data-competition-rail-body>
                                <button type="button" class="sb2-competition-rail-close" data-competition-rail-close aria-label="{{ $sbText('filters.close_filters', 'Filtreleri kapat') }}">
                                    <span>{{ $sbText('filters.close_filters', 'Filtreleri kapat') }}</span>
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M6 6L18 18M18 6L6 18" fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="1.9"/>
                                    </svg>
                                </button>
                                <div class="sb2-tournament-filter-list sb2-tournament-filter-list--rail" data-league-filter-list>
                                    @foreach ($leagueFilterGroups as $groupIndex => $leagueFilterGroup)
                                        @php($groupExpanded = $groupIndex === 0)
                                        @php($groupDisplayLabel = in_array(\Illuminate\Support\Str::lower((string) $leagueFilterGroup['label']), ['diger', 'diğer', 'other'], true) ? $sbText('filters.other_tournaments', 'Diğer turnuvalar') : $leagueFilterGroup['label'])
                                        @php($groupEventCount = (int) ($leagueFilterGroup['event_count'] ?? $leagueFilterGroup['count'] ?? 0))
                                        @php($groupItemCount = (int) ($leagueFilterGroup['item_count'] ?? count($leagueFilterGroup['items'] ?? [])))
                                        <section
                                            class="sb2-tournament-group {{ $groupExpanded ? 'is-open' : '' }}"
                                            data-league-filter-group="{{ $leagueFilterGroup['key'] }}"
                                            data-sport-group-key="{{ $leagueFilterGroup['sport_group_key'] }}"
                                        >
                                            <button
                                                type="button"
                                                class="sb2-tournament-group-toggle"
                                                data-league-filter-group-toggle
                                                aria-expanded="{{ $groupExpanded ? 'true' : 'false' }}"
                                            >
                                                <span class="sb2-tournament-group-copy">
                                                    <strong>{{ $groupDisplayLabel }}</strong>
                                                    <span data-count-raw="{{ $groupItemCount }}">{{ $formatCountLabel($groupItemCount) }} {{ $sbText('common.tournaments', 'turnuva') }}</span>
                                                </span>
                                                <span class="sb2-tournament-group-meta">
                                                    <strong data-league-filter-group-item-count data-count-raw="{{ $groupEventCount }}">{{ $formatCountLabel($groupEventCount) }}</strong>
                                                    <em data-league-filter-group-event-count data-count-raw="{{ $groupEventCount }}">{{ $sbText('common.events', 'etkinlik') }}</em>
                                                    <span class="sb2-tournament-group-chevron" aria-hidden="true">
                                                        <svg viewBox="0 0 24 24">
                                                            <path d="M6 9L12 15L18 9" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"/>
                                                        </svg>
                                                    </span>
                                                </span>
                                            </button>

                                            <div class="sb2-tournament-group-body" data-league-filter-group-body @if (!$groupExpanded) hidden @endif>
                                                @foreach ($leagueFilterGroup['items'] as $leagueFilter)
                                                    <button
                                                        type="button"
                                                        class="sb2-tournament-chip"
                                                        data-league-filter="{{ $leagueFilter['key'] }}"
                                                        data-league-filter-label="{{ $leagueFilter['label'] }}"
                                                        data-sport-group-key="{{ $leagueFilter['sport_group_key'] }}"
                                                        data-league-filter-group-key="{{ $leagueFilterGroup['key'] }}"
                                                        aria-pressed="false"
                                                    >
                                                        <span class="sb2-tournament-chip-title">
                                                            {!! $entityMarkHtml((array) ($leagueFilter['competition_asset'] ?? []), (string) ($leagueFilter['label'] ?? ''), 'competition') !!}
                                                            <span>{{ $leagueFilter['label'] }}</span>
                                                        </span>
                                                        <strong data-count-raw="{{ (int) $leagueFilter['count'] }}">{{ $formatCountLabel((int) $leagueFilter['count']) }}</strong>
                                                    </button>
                                                @endforeach
                                            </div>
                                        </section>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </aside>

                    <div class="sb2-market-board">
                        <div class="sb2-toolbar"></div>

                        <div class="sb2-board-status" data-board-status hidden>
                            <div class="sb2-board-status-copy">
                                <strong data-board-status-title></strong>
                                <span data-board-status-text></span>
                                <div class="sb2-hydration-progress" data-hydration-progress hidden>
                                    <span class="sb2-hydration-progress-bar" data-hydration-progress-bar></span>
                                </div>
                                <span class="sb2-board-status-debug" data-board-status-debug hidden></span>
                            </div>
                            <span class="sb2-board-status-meter" data-board-status-meter>{{ $sbText('common.loading', 'Yükleniyor') }}</span>
                        </div>

                        <div class="sb2-board-skeleton" data-board-skeleton hidden aria-hidden="true">
                            <div class="sb2-board-skeleton-card">
                                <div class="sb2-board-skeleton-head">
                                    <span class="sb2-board-skeleton-badge"></span>
                                    <span class="sb2-board-skeleton-chip"></span>
                                </div>
                                <div class="sb2-board-skeleton-line sb2-board-skeleton-line--league"></div>
                                <div class="sb2-board-skeleton-market-head">
                                    <span class="sb2-board-skeleton-market-pill is-wide"></span>
                                    <span class="sb2-board-skeleton-market-pill"></span>
                                    <span class="sb2-board-skeleton-market-pill is-tight"></span>
                                </div>
                                <div class="sb2-board-skeleton-fixture">
                                    <div class="sb2-board-skeleton-time"></div>
                                    <div class="sb2-board-skeleton-team">
                                        <span class="sb2-board-skeleton-team-dot"></span>
                                        <span class="sb2-board-skeleton-line sb2-board-skeleton-line--team sb2-board-skeleton-line--team-home"></span>
                                    </div>
                                    <div class="sb2-board-skeleton-team">
                                        <span class="sb2-board-skeleton-team-dot is-away"></span>
                                        <span class="sb2-board-skeleton-line sb2-board-skeleton-line--team sb2-board-skeleton-line--team-away"></span>
                                    </div>
                                </div>
                                <div class="sb2-board-skeleton-grid sb2-board-skeleton-grid--three">
                                    <span class="sb2-board-skeleton-odd"></span>
                                    <span class="sb2-board-skeleton-odd"></span>
                                    <span class="sb2-board-skeleton-odd"></span>
                                </div>
                            </div>
                            <div class="sb2-board-skeleton-card">
                                <div class="sb2-board-skeleton-head">
                                    <span class="sb2-board-skeleton-badge is-small"></span>
                                    <span class="sb2-board-skeleton-chip"></span>
                                </div>
                                <div class="sb2-board-skeleton-line sb2-board-skeleton-line--league medium"></div>
                                <div class="sb2-board-skeleton-market-head">
                                    <span class="sb2-board-skeleton-market-pill is-wide"></span>
                                    <span class="sb2-board-skeleton-market-pill"></span>
                                    <span class="sb2-board-skeleton-market-pill is-tight"></span>
                                </div>
                                <div class="sb2-board-skeleton-fixture">
                                    <div class="sb2-board-skeleton-time"></div>
                                    <div class="sb2-board-skeleton-team">
                                        <span class="sb2-board-skeleton-team-dot"></span>
                                        <span class="sb2-board-skeleton-line sb2-board-skeleton-line--team sb2-board-skeleton-line--team-home"></span>
                                    </div>
                                    <div class="sb2-board-skeleton-team">
                                        <span class="sb2-board-skeleton-team-dot is-away"></span>
                                        <span class="sb2-board-skeleton-line sb2-board-skeleton-line--team sb2-board-skeleton-line--team-away"></span>
                                    </div>
                                </div>
                                <div class="sb2-board-skeleton-grid sb2-board-skeleton-grid--three">
                                    <span class="sb2-board-skeleton-odd"></span>
                                    <span class="sb2-board-skeleton-odd"></span>
                                    <span class="sb2-board-skeleton-odd"></span>
                                </div>
                            </div>
                            <div class="sb2-board-skeleton-card">
                                <div class="sb2-board-skeleton-head">
                                    <span class="sb2-board-skeleton-badge is-wide"></span>
                                    <span class="sb2-board-skeleton-chip"></span>
                                </div>
                                <div class="sb2-board-skeleton-line sb2-board-skeleton-line--league short"></div>
                                <div class="sb2-board-skeleton-market-head">
                                    <span class="sb2-board-skeleton-market-pill is-wide"></span>
                                    <span class="sb2-board-skeleton-market-pill"></span>
                                    <span class="sb2-board-skeleton-market-pill is-tight"></span>
                                </div>
                                <div class="sb2-board-skeleton-fixture">
                                    <div class="sb2-board-skeleton-time"></div>
                                    <div class="sb2-board-skeleton-team">
                                        <span class="sb2-board-skeleton-team-dot"></span>
                                        <span class="sb2-board-skeleton-line sb2-board-skeleton-line--team sb2-board-skeleton-line--team-home"></span>
                                    </div>
                                    <div class="sb2-board-skeleton-team">
                                        <span class="sb2-board-skeleton-team-dot is-away"></span>
                                        <span class="sb2-board-skeleton-line sb2-board-skeleton-line--team sb2-board-skeleton-line--team-away"></span>
                                    </div>
                                </div>
                                <div class="sb2-board-skeleton-grid sb2-board-skeleton-grid--three">
                                    <span class="sb2-board-skeleton-odd"></span>
                                    <span class="sb2-board-skeleton-odd"></span>
                                    <span class="sb2-board-skeleton-odd"></span>
                                </div>
                            </div>
                        </div>

                        <div class="sb2-leagues">
                    @if ($renderInitialOfferRows && $hasOffers)
                        @foreach ($leagues as $league)
                            @php($leagueFilterKey = (string) ($league['league_filter_key'] ?? (($league['sport_group_key'] ?? 'all') . '--' . \Illuminate\Support\Str::slug((string) ($league['title'] ?? 'turnuva')))))
                            @php($leagueInitialMatchCount = (int) ($league[$activePhaseCountKey] ?? collect($league['matches'] ?? [])->where('phase_key', $initialPhaseKey)->count()))
                            <div
                                class="sb2-league is-open"
                                data-sport-group-key="{{ $league['sport_group_key'] }}"
                                data-league-filter-key="{{ $leagueFilterKey }}"
                                data-league-title="{{ $league['title'] }}"
                                @if ($leagueInitialMatchCount === 0) hidden @endif
                            >
                                <button type="button" class="sb2-league-header" data-accordion-trigger aria-expanded="true">
                                    <div class="sb2-league-head-left">
                                        <div class="sb2-league-badge">
                                            {!! $entityMarkHtml((array) ($league['competition_asset'] ?? []), (string) ($league['title'] ?? $league['badge'] ?? 'Lig'), 'competition') !!}
                                        </div>
                                        <div class="sb2-league-meta">
                                            <div class="sb2-league-title">{{ $league['title'] }}</div>
                                            <div class="sb2-league-sport">{{ $league['sport'] }}</div>
                                        </div>
                                    </div>

                                    <span class="sb2-league-chevron" aria-hidden="true">
                                        <svg viewBox="0 0 24 24">
                                            <path d="M6 14L12 8L18 14" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"/>
                                        </svg>
                                    </span>
                                </button>

                                <div class="sb2-league-body">
                                    @foreach ($league['matches'] as $match)
                                        <div
                                            class="sb2-match-row"
                                            data-event-row
                                            data-event-id="{{ $match['event_id'] ?? '' }}"
                                            data-event-name="{{ $match['event_name'] ?? '' }}"
                                            data-event-phase="{{ $match['phase_key'] ?? 'pre-match' }}"
                                            data-event-lifecycle-status="{{ $match['lifecycle_status'] ?? '' }}"
                                            data-event-trading-status="{{ $match['trading_status'] ?? '' }}"
                                            data-starts-at="{{ $match['starts_at_timestamp'] ?? '' }}"
                                            data-sport-key="{{ $match['sport_group_key'] ?? '' }}"
                                            data-league-filter-key="{{ $leagueFilterKey }}"
                                            data-search-text="{{ trim(implode(' ', array_filter([
                                                $league['title'] ?? '',
                                                $league['sport'] ?? '',
                                                $match['event_name'] ?? '',
                                                $match['home'] ?? '',
                                                $match['away'] ?? '',
                                            ]))) }}"
                                            @if (($match['phase_key'] ?? 'pre-match') !== $initialPhaseKey) hidden @endif
                                        >
                                            <div class="sb2-match-meta">
                                                <div class="sb2-match-meta-top">
                                                    <div class="sb2-match-tags">
                                                        <span class="sb2-match-tag">{{ $league['title'] }}</span>
                                                        <span
                                                            class="sb2-match-tag sb2-match-tag--phase {{ ($match['phase_key'] ?? 'pre-match') === 'in-game' ? 'is-live' : '' }}"
                                                            data-event-phase-badge
                                                        >
                                                            {{ $match['phase_label'] ?? 'Pre-match' }}
                                                        </span>
                                                    </div>

                                                    <div class="sb2-time-pill">
                                                        <span class="sb2-time-fire" aria-hidden="true"></span>
                                                        <span data-time-pill-label>{{ ($match['phase_key'] ?? 'pre-match') === 'in-game' ? $sbText('common.live', 'Canlı') : ($match['time'] . ' • ' . $match['date']) }}</span>
                                                    </div>
                                                </div>

                                                <div class="sb2-team-lines">
                                                    <div class="sb2-team-line">
                                                        {!! $entityMarkHtml((array) ($match['home_team_asset'] ?? []), (string) ($match['home'] ?? ''), 'team') !!}
                                                        <span>{{ $match['home'] }}</span>
                                                    </div>
                                                    <div class="sb2-team-line">
                                                        {!! $entityMarkHtml((array) ($match['away_team_asset'] ?? []), (string) ($match['away'] ?? ''), 'team') !!}
                                                        <span>{{ $match['away'] }}</span>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="sb2-odds-area">
                                                @php($marketHeadings = $coreMarketHeadings($match))
                                                <div class="sb2-market-headings" data-market-headings>
                                                    @foreach ($marketHeadings as $heading)
                                                        <div
                                                            class="sb2-market-heading {{ !empty($heading['secondary']) ? 'is-secondary' : '' }}"
                                                            data-heading-span="{{ (int) ($heading['span'] ?? 1) }}"
                                                        >
                                                            {{ $heading['label'] }}
                                                        </div>
                                                    @endforeach
                                                </div>

                                                <div
                                                    class="sb2-betting-closed-note"
                                                    data-betting-closed-note
                                                    role="status"
                                                    aria-live="polite"
                                                    @if (($match['row_notice_state'] ?? 'none') === 'none') hidden @endif
                                                >
                                                    @if (($match['row_notice_state'] ?? '') === 'ended')
                                                        {{ $sbText('js.event_ended', 'Karşılaşma sonuçlandı.') }}
                                                    @elseif (($match['row_notice_state'] ?? '') === 'closed')
                                                        {{ $sbText('markets.betting_closed', 'Şu an bahis kapalı') }}
                                                    @elseif (($match['row_notice_state'] ?? '') === 'suspended')
                                                        {{ $sbText('js.event_suspended', 'Bahis geçici olarak askıda.') }}
                                                    @elseif (($match['row_notice_state'] ?? '') === 'resolving')
                                                        {{ $sbText('js.odds_resolving', 'Oranlar doğrulanıyor…') }}
                                                    @elseif (($match['row_notice_state'] ?? '') === 'unavailable')
                                                        {{ $sbText('js.event_unavailable', 'Oranlar şu anda kullanılamıyor.') }}
                                                    @endif
                                                </div>

                                                <div class="sb2-odds-grid">
                                                    @foreach ($match['match_result'] as $item)
                                                        @php($selectionPayload = array_merge($item, ['context_time' => $match['time'], 'context_date' => $match['date'], 'context_sport' => $league['sport'] ?? '']))
                                                        @php($isCatalogShell = !empty($item['is_catalog_shell']) || ($item['acceptance_mode'] ?? '') === 'catalog_shell')
                                                        @php($demoSelectable = !empty($match['offline_demo']) && !empty($item['demo_selectable']))
                                                        @php($winxEdgePct = is_numeric($item['winx_edge_pct'] ?? null) ? round((float) $item['winx_edge_pct'], 1) : null)
                                                        <button
                                                            type="button"
                                                            class="sb2-odd-box {{ $isCatalogShell ? 'sb2-odd-box--catalog-shell' : '' }} {{ empty($item['selectable']) && !$demoSelectable ? 'is-disabled' : '' }}"
                                                            data-selection-trigger
                                                            data-selection='@json($selectionPayload)'
                                                            data-event-id="{{ $match['event_id'] ?? '' }}"
                                                            data-market-type="{{ $item['market_type'] ?? '' }}"
                                                            data-market-specifier="{{ $item['market_specifier_key'] ?? 'default' }}"
                                                            data-outcome-code="{{ $item['outcome_code'] ?? '' }}"
                                                            @if (empty($item['selectable']) && !$demoSelectable) title="{{ $item['selection_state_message'] ?? $sbText('markets.betting_closed', 'Şu an bahis kapalı') }}" disabled aria-disabled="true" @endif
                                                        >
                                                            <span class="sb2-odd-box-label">{{ $item['label'] }}</span>
                                                            <span class="sb2-odd-box-value-wrap">
                                                                @if ($winxEdgePct !== null && $winxEdgePct >= 2.0)
                                                                    <span
                                                                        class="sb2-rise-edge"
                                                                        title="{{ __('cms_public.sportsbook.markets.winx_edge_tooltip') }}"
                                                                        aria-label="{{ __('cms_public.sportsbook.markets.winx_edge') }} +{{ number_format($winxEdgePct, 1, '.', '') }}%"
                                                                    >EDGE +{{ number_format($winxEdgePct, 1, '.', '') }}%</span>
                                                                @endif
                                                                <span class="sb2-odd-box-value">{{ $item['odd'] }}</span>
                                                            </span>
                                                        </button>
                                                    @endforeach

                                                    @foreach ($match['totals'] as $item)
                                                        @php($selectionPayload = array_merge($item, ['context_time' => $match['time'], 'context_date' => $match['date'], 'context_sport' => $league['sport'] ?? '']))
                                                        @php($isCatalogShell = !empty($item['is_catalog_shell']) || ($item['acceptance_mode'] ?? '') === 'catalog_shell')
                                                        <?php
                                                            $totalPrefix = ($item['outcome_code'] ?? '') === 'over'
                                                                ? $sbText('markets.over', 'Üst')
                                                                : (($item['outcome_code'] ?? '') === 'under' ? $sbText('markets.under', 'Alt') : '');
                                                            $totalLine = $item['market_line'] ?? null;
                                                            $totalIdentityReady = !empty($item['event_id'])
                                                                && (!empty($item['selection_key']) || !empty($item['quote_selection_id']) || !empty($item['selection_token']));
                                                            $totalPresentationReady = $totalPrefix !== '' && $totalLine !== null && $totalLine !== ''
                                                                && is_numeric($totalLine) && $totalIdentityReady
                                                                && is_numeric($item['raw_odd'] ?? null) && (float) $item['raw_odd'] > 1;
                                                            $totalLabel = $totalPresentationReady ? $totalPrefix . ' ' . $totalLine : (string) ($item['label'] ?? '');
                                                            $totalButtonDisabled = empty($item['selectable']) || !$totalPresentationReady;
                                                            $selectionPayload['selectable'] = !$totalButtonDisabled;
                                                            if ($totalPresentationReady) {
                                                                $selectionPayload['label'] = $totalLabel;
                                                                $selectionPayload['outcome_name'] = $totalLabel;
                                                            }
                                                        ?>
                                                        <button
                                                            type="button"
                                                            class="sb2-odd-box sb2-odd-box--total {{ $isCatalogShell ? 'sb2-odd-box--catalog-shell' : '' }} {{ $totalButtonDisabled ? 'is-disabled' : '' }}"
                                                            data-selection-trigger
                                                            data-selection='@json($selectionPayload)'
                                                            data-event-id="{{ $match['event_id'] ?? '' }}"
                                                            data-market-type="{{ $item['market_type'] ?? '' }}"
                                                            data-market-specifier="{{ $item['market_specifier_key'] ?? 'default' }}"
                                                            data-outcome-code="{{ $item['outcome_code'] ?? '' }}"
                                                            @if ($totalButtonDisabled) title="{{ !$totalPresentationReady ? $sbText('js.odds_resolving', 'Oranlar doğrulanıyor…') : ($item['selection_state_message'] ?? $sbText('js.event_unavailable', 'Oranlar şu anda kullanılamıyor.')) }}" disabled aria-disabled="true" @endif
                                                        >
                                                            <span class="sb2-odd-box-label">{{ $totalLabel }}</span>
                                                            <span class="sb2-odd-box-value">{{ $item['odd'] }}</span>
                                                        </button>
                                                    @endforeach

                                                    @if (!empty($match['extra_markets']))
                                                        <button
                                                            type="button"
                                                            class="sb2-more-box"
                                                            data-more-toggle
                                                            data-more-label="{{ $match['more'] }}"
                                                            aria-expanded="false"
                                                            aria-label="{{ str_replace(':count', (string) $match['more'], $sbText('markets.more_markets_aria', 'Ek marketleri aç: :count')) }}"
                                                        >
                                                            {{ $match['more'] }}
                                                        </button>
                                                    @endif
                                                </div>

                                                {{-- Expanded market detail is lazy-rendered on first +N open to keep SSR HTML light. --}}
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="sb2-league-placeholder" data-league-placeholder hidden aria-hidden="true"></div>
                            </div>
                        @endforeach

                        <div class="sb2-filter-empty-state" data-filter-empty-state hidden>
                            <h2>{{ $sbText('empty.no_active_match', 'Aktif maç yok') }}</h2>
                            <p>{{ $sbText('empty.try_clear_filters', 'Filtreleri temizleyerek maç listesine dönebilirsin.') }}</p>
                            <p data-provider-degraded-code hidden></p>
                        </div>

                        <div class="sb2-list-pagination" data-match-pagination hidden>
                            <div class="sb2-list-pagination-meta">
                                <div class="sb2-list-pagination-label" data-match-page-label>{{ $sbText('filters.list', 'Liste') }}</div>
                                <div class="sb2-list-pagination-summary" data-match-page-summary>0 {{ $sbText('common.events', 'etkinlik') }}</div>
                            </div>
                            <button type="button" class="sb2-list-pagination-btn sb2-list-pagination-load-more" data-match-page-next>
                                {{ $sbText('common.more', 'Daha fazla göster') }}
                            </button>
                        </div>
                    @else
                        <div class="sb2-empty-state" data-initial-offers-empty-state>
                            @if($adapterPublicPrematchUnavailable)
                                <h2>{{ $sbText('empty.prematch_refresh_unavailable_title', 'Maç öncesi veriler şu anda yenilenemiyor.') }}</h2>
                                <p>{{ $sbText('empty.prematch_refresh_unavailable_body', 'Birkaç dakika sonra tekrar deneyin.') }}</p>
                                <p data-provider-degraded-code>{{ $sbText('js.error_code_prefix', 'Kod') }}: SPORTSBOOK_ADAPTER_UNAVAILABLE</p>
                            @else
                                @if($initialPayloadDeferred)
                                    <h2>{{ $sbText('empty.odds_loading_title', 'Oranlar yükleniyor.') }}</h2>
                                    <p>{{ $sbText('empty.odds_loading_body', 'Güncel maç listesi birkaç saniye içinde burada görünecek.') }}</p>
                                @else
                                    <h2>{{ $sbText('empty.no_prematch_title', 'Şu anda gösterilecek maç öncesi bahis seçeneği yok.') }}</h2>
                                    <p>{{ $sbText('empty.no_prematch_body', 'Güncel oran verisi geldiğinde lig blokları burada aynı düzenle listelenecek.') }}</p>
                                @endif
                            @endif
                        </div>
                        <div class="sb2-filter-empty-state" data-filter-empty-state hidden>
                            <h2>{{ $sbText('empty.no_active_match', 'Aktif maç yok') }}</h2>
                            <p>{{ $sbText('empty.try_clear_filters', 'Filtreleri temizleyerek maç listesine dönebilirsin.') }}</p>
                            <p data-provider-degraded-code hidden></p>
                        </div>
                    @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="sb2-tab-panel {{ $betFeedPanelActive ? 'is-active' : '' }}" data-tab-panel="bet-feed" aria-hidden="{{ $betFeedPanelActive ? 'false' : 'true' }}" @if(!$betFeedPanelActive) hidden @endif>
                <div class="sb2-feed-shell" data-bet-feed-root>
                    <div class="sb2-feed-board">
                        <div class="sb2-feed-board-head">
                            <div class="sb2-feed-board-title">
                                <h3>{{ $betFeedUi['title'] }}</h3>
                            </div>
                        </div>

                        @php($hasCopyableBetFeedRows = collect($visibleBetFeedRows ?? [])->contains(fn ($item) => (bool) data_get($item, 'can_add_to_betslip')))
                        <div class="sb2-feed-stream" data-bet-feed-stream>
                            @if(!$hasCopyableBetFeedRows && count($visibleBetFeedRows ?? []) > 0)
                                <div class="sb2-feed-empty sb2-feed-copyable-empty" data-bet-feed-copyable-empty>
                                    <strong>{{ $sbText('empty.copyable_empty_title', 'Şu an kupona eklenebilir seçim yok.') }}</strong>
                                    <span>{{ $sbText('empty.copyable_empty_body', 'Aşağıdaki akış geçmiş/güncel durum bilgisi olarak gösteriliyor.') }}</span>
                                </div>
                            @endif
                            @forelse($visibleBetFeedRows as $item)
                                @include('cms.shared.sportsbook.partials.bet-feed-row', ['item' => $item, 'expanded' => false, 'betFeedUi' => $betFeedUi])
                            @empty
                                <div class="sb2-feed-empty" data-bet-feed-empty data-deferred-empty="{{ $renderInitialBetFeedRows ? '0' : '1' }}">{{ $renderInitialBetFeedRows ? $betFeedUi['empty'] : $sbText('empty.bet_feed_loading', 'Bahis akışı yükleniyor. Bu birkaç saniye sürebilir.') }}</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <div class="sb2-tab-panel {{ $predictionsPanelActive ? 'is-active' : '' }}" data-tab-panel="predictions" aria-hidden="{{ $predictionsPanelActive ? 'false' : 'true' }}" @if(!$predictionsPanelActive) hidden @endif>
                <div class="sb2-predictions-shell" data-predictions-shell>
                    <div class="sb2-predictions-board">
                        <div class="sb2-predictions-head">
                            <div>
                                <h3>{{ $sbText('predictions.title', 'Tahminler') }}</h3>
                            </div>
                        </div>

                        <div class="sb2-predictions-content" data-predictions-content>
                            <div class="sb2-predictions-grid" data-predictions-grid>
                                @forelse(($renderInitialPredictionCards ? $predictionItems : collect()) as $item)
                                    @include('cms.shared.sportsbook.partials.prediction-card', ['item' => $item])
                                @empty
                                    <div class="sb2-feed-empty">{{ $renderInitialPredictionCards ? $sbText('empty.prediction_empty', 'Gösterilecek tahmin kaydı bulunamadı.') : $sbText('empty.predictions_loading', 'Tahminler yükleniyor.') }}</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @include('cms.shared.sportsbook.partials.social-market-panel', [
                'panelKey' => 'e-sports',
                'panelTitle' => 'E-Sports',
                'panelActive' => $esportsPanelActive,
                'renderInitialCards' => $renderInitialEsportsCards,
                'categories' => $esportsSocialMarketCategories,
                'items' => $esportsSocialMarketItems,
                'categoryAriaLabel' => $sbText('social_market.esports_category_aria', 'E-Sports kategorileri'),
                'loadedEmptyText' => $sbText('empty.esports_empty', 'Gösterilecek E-Sports marketi bulunamadı.'),
                'lazyEmptyText' => $sbText('empty.esports_lazy', 'E-Sports marketleri bu sekmeye geçtiğinizde yüklenecek.'),
                'modalTitleId' => 'esportsMarketDetailTitle',
                'staticPayload' => true,
                'categoryScope' => $esportsSocialMarketCategoryKey,
            ])

            <div class="sb2-tab-panel {{ $socialMarketPanelActive ? 'is-active' : '' }}" data-tab-panel="social-market" aria-hidden="{{ $socialMarketPanelActive ? 'false' : 'true' }}" @if(!$socialMarketPanelActive) hidden @endif>
                <div class="sb2-social-market-shell" data-social-market-root>
                    <div class="sb2-social-market-board">
                        <div class="sb2-social-market-head">
                            <div>
                                <h3>{{ $sbText('tabs.social_market', 'Sosyal Market') }}</h3>
                            </div>
                        </div>

                        <div class="sb2-social-market-chip-rail" role="tablist" aria-label="{{ $sbText('social_market.category_aria', 'Sosyal market kategorileri') }}" data-social-market-chip-rail>
                            @foreach(($renderInitialSocialMarketCards ? $socialMarketCategories : collect()) as $index => $category)
                                @php($categoryKey = (string) data_get($category, 'key'))
                                @php($categoryTitle = (string) data_get($category, 'title'))
                                @php($categoryCount = (int) data_get($category, 'count', 0))
                                @php($categoryIcon = trim((string) data_get($category, 'icon', '')))
                                <button
                                    type="button"
                                    class="sb2-social-market-chip {{ $index === 0 ? 'is-active' : '' }}"
                                    data-social-market-category-filter="{{ $categoryKey }}"
                                    aria-selected="{{ $index === 0 ? 'true' : 'false' }}"
                                >
                                    <span class="sb2-social-market-chip-icon">
                                        @if(str_starts_with($categoryIcon, 'fa'))
                                            <i class="{{ $categoryIcon }}" aria-hidden="true"></i>
                                        @else
                                            {{ strtoupper(substr($categoryIcon !== '' ? $categoryIcon : $categoryTitle, 0, 2)) }}
                                        @endif
                                    </span>
                                    <span>{{ $categoryTitle }}</span>
                                    <strong data-count-raw="{{ $categoryCount }}">{{ $formatCountLabel($categoryCount) }}</strong>
                                </button>
                            @endforeach
                        </div>

                        <div class="sb2-social-market-grid" data-social-market-grid>
                            @forelse(($renderInitialSocialMarketCards ? $socialMarketItems : collect()) as $market)
                                @php($marketCategoryKey = (string) data_get($market, 'category_key'))
                                @php($marketHidden = $socialMarketCategories->isNotEmpty() && $marketCategoryKey !== (string) data_get($socialMarketCategories->first(), 'key'))
                                @php($marketRender = (array) data_get($market, 'render', []))
                                @php($marketBadge = trim((string) data_get($marketRender, 'badge', 'SM')))
                                @php($marketIcon = trim((string) data_get($marketRender, 'thumbnail_icon', 'fas fa-chart-line')))
                                @php($marketTopic = trim((string) data_get($marketRender, 'topic', data_get($market, 'subtitle', ''))))
                                @php($marketVariant = trim((string) data_get($marketRender, 'card_variant', 'standard')))
                                @php($marketSearchText = \Illuminate\Support\Str::lower(collect([
                                    data_get($market, 'title'),
                                    data_get($market, 'subtitle'),
                                    data_get($market, 'category_title'),
                                    $marketTopic,
                                    $marketBadge,
                                    collect(data_get($market, 'outcomes', []))->pluck('label')->implode(' '),
                                ])->filter()->implode(' ')))
                                <article class="sb2-social-market-card" data-social-market-card data-social-market-category="{{ $marketCategoryKey }}" data-social-market-search="{{ $marketSearchText }}" @if($marketHidden) hidden @endif>
                                    <button type="button" class="sb2-social-market-card-head" data-social-market-detail-trigger aria-label="{{ str_replace(':title', (string) data_get($market, 'title'), $sbText('social_market.detail_open', ':title detayını aç')) }}">
                                        <span class="sb2-social-market-thumb" data-variant="{{ $marketVariant }}">
                                            @if(str_starts_with($marketIcon, 'fa'))
                                                <i class="{{ $marketIcon }}" aria-hidden="true"></i>
                                            @endif
                                            <strong>{{ $marketBadge !== '' ? $marketBadge : strtoupper(substr((string) data_get($market, 'category_title'), 0, 2)) }}</strong>
                                        </span>
                                        <div class="sb2-social-market-title-block">
                                            <h3>{{ data_get($market, 'title') }}</h3>
                                            <span>{{ $marketTopic !== '' ? $marketTopic : data_get($market, 'category_title') }}</span>
                                        </div>
                                        <span class="sb2-social-market-chevron" aria-hidden="true">
                                            <i class="fas fa-chevron-right"></i>
                                        </span>
                                    </button>

                                    @if(data_get($market, 'subtitle'))
                                        <p class="sb2-social-market-subtitle">{{ data_get($market, 'subtitle') }}</p>
                                    @endif

                                    <div class="sb2-social-market-outcomes">
                                        @foreach(collect(data_get($market, 'outcomes', []))->values() as $outcome)
                                            @php($selectionPayload = (array) $outcome)
                                            @php($outcomeTone = (string) data_get($outcome, 'render.tone', 'neutral'))
                                            @php($priceLabel = 'x' . rtrim(rtrim(number_format((float) data_get($outcome, 'price', 0), 2, '.', ''), '0'), '.'))
                                            <button
                                                type="button"
                                                class="sb2-social-market-outcome sb2-odd-box is-{{ in_array($outcomeTone, ['yes', 'no', 'neutral'], true) ? $outcomeTone : 'neutral' }}"
                                                data-selection-trigger
                                                data-selection='@json($selectionPayload)'
                                            >
                                                <span class="sb2-odd-box-label">{{ data_get($outcome, 'label') }}</span>
                                                <span class="sb2-odd-box-value">{{ $priceLabel }}</span>
                                            </button>
                                        @endforeach
                                    </div>

                                    <div class="sb2-social-market-metrics" aria-label="{{ $sbText('social_market.stats_aria', 'Sosyal market istatistikleri') }}">
                                        <span><i class="fas fa-chart-bar" aria-hidden="true"></i> {{ $sbText('social_market.interest_score', 'İlgi skoru') }}: {{ data_get($marketRender, 'volume_label', '-') }}</span>
                                        <span><i class="fas fa-users" aria-hidden="true"></i> {{ $sbText('social_market.community_interest', 'Topluluk ilgisi') }}: {{ data_get($marketRender, 'bettors_label', '-') }}</span>
                                    </div>
                                </article>
                            @empty
                                <div class="sb2-feed-empty">{{ $renderInitialSocialMarketCards ? $sbText('empty.social_empty', 'Gösterilecek sosyal market bulunamadı.') : $sbText('empty.social_loading', 'Sosyal Market yükleniyor.') }}</div>
                            @endforelse
                        </div>

                        <div class="sb2-social-market-modal" data-social-market-detail-modal hidden aria-hidden="true">
                            <button type="button" class="sb2-social-market-modal-backdrop" data-social-market-detail-close aria-label="{{ $sbText('social_market.detail_close', 'Detayı kapat') }}"></button>
                            <section class="sb2-social-market-modal-panel" role="dialog" aria-modal="true" aria-labelledby="socialMarketDetailTitle">
                                <div class="sb2-social-market-modal-head">
                                    <span class="sb2-social-market-modal-thumb" data-social-market-modal-thumb></span>
                                    <div class="sb2-social-market-modal-kicker">
                                        <span data-social-market-modal-topic></span>
                                        <strong>{{ $sbText('social_market.single_bet', 'Tekli bahis') }}</strong>
                                    </div>
                                    <button type="button" class="sb2-social-market-modal-close" data-social-market-detail-close aria-label="{{ $sbText('social_market.detail_close', 'Detayı kapat') }}">
                                        <i class="fas fa-times" aria-hidden="true"></i>
                                    </button>
                                </div>
                                <h3 id="socialMarketDetailTitle" data-social-market-modal-title></h3>
                                <p data-social-market-modal-subtitle></p>
                                <div class="sb2-social-market-modal-outcomes" data-social-market-modal-outcomes></div>
                                <div class="sb2-social-market-modal-metrics" data-social-market-modal-metrics></div>
                            </section>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <div class="sb2-sidebar">
        <div class="sb2-sidebar-card" data-slip-shell>
            <div class="sb2-sidebar-top">
                <div class="sb2-sidebar-title-wrap">
                    <button type="button" class="sb2-sidebar-chevron" data-slip-toggle aria-label="{{ $sbText('slip.collapse', 'Kuponu küçült') }}">
                        <svg viewBox="0 0 24 24">
                            <path d="m7 14 5-5 5 5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/>
                        </svg>
                    </button>
                    <div class="sb2-sidebar-title">{{ $sbText('slip.title', 'Kupon') }}</div>
                </div>
                <div class="sb2-sidebar-actions">
                    <div class="sb2-balance-wrap">
                        <span class="sb2-wallet-icon" aria-hidden="true"></span>
                        <span class="sb2-balance">{{ $viewer['formatted_balance'] ?? '₺ 0,00' }}</span>
                    </div>
                </div>
            </div>

            <div class="sb2-slip-mode-row" aria-label="{{ $sbText('slip.type_aria', 'Kupon türü') }}">
                <div class="sb2-slip-mode-tabs" role="tablist" aria-label="{{ $sbText('slip.tabs_aria', 'Kupon sekmeleri') }}">
                    <button type="button" class="sb2-slip-mode-tab is-active" role="tab" aria-selected="true" data-slip-mode-tab="single">{{ $sbText('slip.single', 'Tekli') }}</button>
                    <button type="button" class="sb2-slip-mode-tab" role="tab" aria-selected="false" data-slip-mode-tab="combo">{{ $sbText('slip.combo', 'Kombine') }}</button>
                    @if (empty($viewer['authenticated']))
                        <button type="button" class="sb2-slip-mode-tab" role="tab" aria-selected="false" data-slip-mode-tab="system">{{ $sbText('slip.system', 'Seri') }}</button>
                    @endif
                </div>
                <button type="button" class="sb2-slip-trash-btn" data-remove-selection aria-label="{{ $sbText('slip.clear', 'Kuponu temizle') }}" title="{{ $sbText('slip.clear', 'Kuponu temizle') }}">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M8 7.5h8m-6.2 0V6.2c0-.9.7-1.6 1.6-1.6h1.2c.9 0 1.6.7 1.6 1.6v1.3m-7 0 .8 11c.1.8.7 1.4 1.5 1.4h5c.8 0 1.4-.6 1.5-1.4l.8-11M10.5 11v5m3-5v5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.9"/>
                        <path d="M17.5 5.5 20 3m0 2.5L17.5 3" fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="1.8"/>
                    </svg>
                </button>
            </div>

            <button type="button" class="sb2-sidebar-collapsed-summary" data-slip-collapsed-summary data-slip-toggle hidden>
                <span class="sb2-sidebar-collapsed-pill-copy">
                    <span class="sb2-sidebar-collapsed-pill-label">{{ $sbText('slip.title', 'Kupon') }}</span>
                    <span class="sb2-sidebar-collapsed-count" data-slip-collapsed-count hidden>0</span>
                </span>
                <div class="sb2-sidebar-collapsed-title" data-slip-collapsed-title>{{ $sbText('slip.empty_title', 'Kupon boş') }}</div>
                <div class="sb2-sidebar-collapsed-meta" data-slip-collapsed-meta></div>
                <div class="sb2-sidebar-collapsed-stake" data-slip-collapsed-stake>₺0,00</div>
                <div class="sb2-sidebar-collapsed-odd" data-slip-collapsed-odd>-</div>
            </button>

            <div class="sb2-slip-panel" data-slip-panel>
                <div class="sb2-slip-idle-card" data-slip-idle-card hidden>
                    <div class="sb2-slip-idle-title">{{ $sbText('slip.select_odds', 'Bir oran seç') }}</div>
                </div>

                <div class="sb2-slip-mode-surface" data-slip-mode-surface hidden>
                    <div class="sb2-slip-mode-surface-title" data-slip-mode-surface-title></div>
                    <div class="sb2-slip-mode-surface-text" data-slip-mode-surface-text></div>
                    <button type="button" class="sb2-slip-mode-surface-action" data-slip-mode-reset>{{ $sbText('slip.single', 'Tekli') }}</button>
                </div>

                <div class="sb2-slip-filled" data-slip-filled hidden>
                    <div class="sb2-result-card" data-result-card hidden>
                        <div class="sb2-result-card-top">
                            <div>
                                <div class="sb2-result-card-title" data-result-title>-</div>
                                <div class="sb2-result-card-text" data-result-text>-</div>
                            </div>
                            <div class="sb2-result-card-actions">
                                <button type="button" class="sb2-result-card-action" data-result-action hidden>{{ $sbText('slip.change_selection', 'Seçimi değiştir') }}</button>
                                <button type="button" class="sb2-result-card-close" data-result-close hidden aria-label="{{ $sbText('slip.close_message', 'Mesajı kapat') }}">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M7 7l10 10M17 7 7 17" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="sb2-slip-selection-card" data-slip-selection-card hidden>
                        <button type="button" class="sb2-slip-remove" data-remove-selection aria-label="{{ $sbText('slip.remove_selection', 'Seçimi kaldır') }}">×</button>
                        <div class="sb2-slip-event" data-slip-event></div>
                        <div class="sb2-slip-context" data-slip-context></div>
                        <div class="sb2-slip-market" data-slip-market></div>
                        <div class="sb2-slip-pick-row">
                            <span data-slip-outcome></span>
                            <strong data-slip-odd></strong>
                        </div>
                        <div class="sb2-exact-refresh-warning" data-exact-refresh-warning hidden>
                            <p role="status" aria-live="polite">{{ $sbText('js.exact_refresh_preserved', 'Oran şu anda güncellenemedi. Kuponun korunuyor; birazdan tekrar deneyebilirsin.') }}</p>
                            <button type="button" class="sb2-result-card-action" data-exact-refresh-retry>
                                <i class="fas fa-redo" aria-hidden="true"></i> {{ __('cms_public.common.retry') }}
                            </button>
                        </div>
                        @if (!empty($viewer['authenticated']))
                            <div class="sb2-result-card-actions">
                                <button type="button" class="sb2-result-card-action" data-community-share-selection disabled>
                                    {{ $sbText('slip.share_in_sports', 'Sporda Paylaş') }}
                                </button>
                                <small data-community-share-feedback aria-live="polite"></small>
                            </div>
                        @endif
                    </div>

                    <div class="sb2-stake-card" data-slip-stake-card>
                        <div class="sb2-stake-amount-card">
                            <div class="sb2-stake-top">
                                <span>{{ $sbText('slip.stake_amount', 'Bahis tutarı') }}</span>
                            </div>

                            <div class="sb2-stake-input-wrap">
                                <input type="text" class="sb2-stake-input" data-stake-input inputmode="numeric" pattern="[1-9][0-9]*" value="" placeholder="0">
                                <button type="button" class="sb2-stake-fill-btn" data-fill-balance>{{ $sbText('slip.fill_balance', 'Tümünü yatır') }}</button>
                            </div>

                            <div class="sb2-stake-quick-row" aria-label="{{ $sbText('slip.quick_stakes_aria', 'Hızlı tutar seçimleri') }}">
                                <button type="button" class="sb2-stake-chip" data-stake-chip="50">₺50</button>
                                <button type="button" class="sb2-stake-chip" data-stake-chip="100">₺100</button>
                                <button type="button" class="sb2-stake-chip" data-stake-chip="250">₺250</button>
                                <button type="button" class="sb2-stake-chip" data-stake-chip="500">₺500</button>
                            </div>
                        </div>

                        <div class="sb2-slip-metrics-card">
                            <div class="sb2-slip-summary">
                                <div class="sb2-slip-summary-row sb2-slip-summary-row--math">
                                    <span>{{ $sbText('slip.amount_odds', 'Miktar / oran') }}</span>
                                    <strong>
                                        <span data-slip-stake>₺ 0,00</span>
                                        <em data-slip-multiplier>{{ $sbText('slip.odds_empty', 'Oran -') }}</em>
                                    </strong>
                                </div>
                                <div class="sb2-slip-summary-row">
                                    <span>{{ $sbText('slip.potential_return', 'Olası getiri') }}</span>
                                    <strong data-slip-return>₺ 0,00</strong>
                                </div>
                            </div>
                        </div>

                        <label class="sb2-slip-summary-row sb2-slip-auto-accept-row">
                            <span>
                                {{ $sbText('slip.auto_accept_label', 'Oran değişirse otomatik kabul et') }}
                                <small data-auto-accept-odds-helper>{{ $sbText('slip.auto_accept_off', 'Kapalı: Oran değişirse senden onay istenir.') }}</small>
                            </span>
                            <input type="checkbox" data-auto-accept-odds-change>
                        </label>

                        <div class="sb2-slip-action-row">
                            <button type="button" class="sb2-submit-btn" data-submit-slip disabled>{{ $sbText('slip.submit', 'Bahis yap') }}</button>
                            <div class="sb2-submit-helper" data-submit-helper hidden></div>
                        </div>
                    </div>

                    <div class="sb2-last-action-card" data-last-action-card hidden>
                        <div class="sb2-last-action-title">{{ $sbText('slip.last_action', 'Son işlem') }}</div>
                        <div class="sb2-last-action-summary" data-last-action-summary>{{ $sbText('slip.accepted', 'Bahis alındı') }}</div>
                        <div class="sb2-last-action-row">
                            <span>{{ $sbText('slip.status', 'Durum') }}</span>
                            <strong data-last-action-status>{{ $sbText('slip.accepted', 'Bahis alındı') }}</strong>
                        </div>
                        <div class="sb2-last-action-row">
                            <span>{{ $sbText('slip.stake', 'Tutar') }}</span>
                            <strong data-last-stake>-</strong>
                        </div>
                        <div class="sb2-last-action-row">
                            <span>{{ $sbText('slip.odds', 'Oran') }}</span>
                            <strong data-last-accepted-price>-</strong>
                        </div>
                        <div class="sb2-last-action-row">
                            <span>{{ $sbText('slip.potential_return', 'Olası getiri') }}</span>
                            <strong data-last-return>-</strong>
                        </div>
                        <div class="sb2-last-action-row">
                            <span>{{ $sbText('slip.short_ref', 'Kısa ref') }}</span>
                            <strong data-last-acceptance-ref>-</strong>
                        </div>
                        @if (!empty($viewer['finance_report_url']))
                            <a href="{{ $viewer['finance_report_url'] }}" class="sb2-last-action-link">{{ $sbText('slip.view_transactions', 'İşlemleri gör') }}</a>
                        @endif
                    </div>
                </div>

            </div>
        </div>

        @if(!empty($bahisbotEnabled))
        <div class="sb2-bahisbot-panel" data-bahisbot-panel>
            <div class="sb2-bahisbot-top">
                <div class="sb2-bahisbot-brand">
                    <div class="sb2-bahisbot-mark" aria-hidden="true">
                        <span></span>
                    </div>
                    <div class="sb2-bahisbot-copy">
                        <div class="sb2-bahisbot-title">Bahisbot</div>
                    </div>
                </div>
                <button type="button" class="sb2-bahisbot-control" data-bahisbot-toggle aria-expanded="true" aria-controls="sb2BahisbotBody" aria-label="{{ $sbText('js.bahisbot_close', "Bahisbot'u kapat") }}" title="{{ $sbText('js.bahisbot_close', "Bahisbot'u kapat") }}"><i class="fas fa-chevron-up" aria-hidden="true"></i></button>
            </div>

            <div class="sb2-bahisbot-selection" id="sb2BahisbotBody" data-bahisbot-selection hidden>
                <div class="sb2-bahisbot-identity" data-bahisbot-identity hidden></div>
                <div class="sb2-bahisbot-match" data-bahisbot-match></div>
                <time class="sb2-bahisbot-start" data-bahisbot-start hidden></time>
                <div class="sb2-bahisbot-pick" data-bahisbot-pick></div>

                <div class="sb2-bahisbot-progress" data-bahisbot-progress hidden aria-hidden="true">
                    <span data-bahisbot-progress-step="match">{{ $sbText('js.bahisbot_match_checking', 'Maç bilgisi kontrol ediliyor…') }}</span>
                    <span data-bahisbot-progress-step="sources">{{ $sbText('js.bahisbot_sources_scanning', 'Kaynaklar taranıyor…') }}</span>
                    <span data-bahisbot-progress-step="result">{{ $sbText('js.bahisbot_result_preparing', 'Sonuç hazırlanıyor…') }}</span>
                </div>

                <div class="sb2-bahisbot-movement" data-bahisbot-movement hidden>
                    <span data-bahisbot-movement-label>{{ $sbText('common.odds_movement', 'Oran hareketi') }}</span>
                    <p data-bahisbot-movement-text></p>
                    <small data-bahisbot-movement-caveat>{{ $sbText('common.odds_movement_unknown', 'Hareketin nedeni doğrulanmadı.') }}</small>
                </div>

                <div class="sb2-bahisbot-status" data-bahisbot-status role="status" aria-live="polite" aria-atomic="true" hidden></div>
                <small class="sb2-bahisbot-cache-window" data-bahisbot-cache-window hidden></small>
                <a class="sb2-bahisbot-card" data-bahisbot-card href="#" target="_blank" rel="noopener noreferrer" hidden>
                    <span data-bahisbot-card-title></span>
                    <strong data-bahisbot-card-summary></strong>
                    <small data-bahisbot-card-source></small>
                    <small data-bahisbot-news-age></small>
                    <small data-bahisbot-check-age></small>
                </a>
                <div class="sb2-bahisbot-retry-row" data-bahisbot-retry-row hidden>
                    <button type="button" class="sb2-bahisbot-control" data-bahisbot-retry aria-label="{{ $sbText('js.bahisbot_retry', 'Yeniden dene') }}" title="{{ $sbText('js.bahisbot_retry', 'Yeniden dene') }}"><i class="fas fa-redo" aria-hidden="true"></i></button>
                    <small data-bahisbot-retry-label>{{ $sbText('js.bahisbot_retry', 'Yeniden dene') }}</small>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>

<template id="sb2BetFeedRowTemplate">
    @include('cms.shared.sportsbook.partials.bet-feed-row', ['item' => []])
</template>

<template id="sb2PredictionCardTemplate">
    <article class="sb2-prediction-card" data-prediction-card>
        <div class="sb2-prediction-top">
            <div>
                <div class="sb2-prediction-league" data-role="prediction-league"></div>
                <h3 class="sb2-prediction-fixture" data-role="prediction-fixture"></h3>
            </div>
        </div>

        <button type="button" class="sb2-prediction-detail-toggle" data-role="prediction-detail-toggle" aria-expanded="false">
            <span data-role="prediction-detail-toggle-label">{{ $sbText('predictions.detail_open', 'Detayı aç') }}</span>
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="m7 10 5 5 5-5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/>
            </svg>
        </button>

        <div class="sb2-prediction-detail" data-role="prediction-detail" hidden>
            <span class="sb2-prediction-provider" data-role="prediction-provider"></span>
            <div class="sb2-prediction-meta">
                <span class="sb2-prediction-pill" data-role="prediction-kickoff"></span>
                <span class="sb2-prediction-pill" data-role="prediction-predictable"></span>
                <span class="sb2-prediction-pill is-muted" data-role="prediction-observed"></span>
                <span class="sb2-prediction-pill is-muted" data-role="prediction-updated"></span>
            </div>

            <div class="sb2-prediction-stats">
                <div class="sb2-prediction-stat">
                    <span>{{ $sbText('predictions.match_result', 'Maç sonucu tahmini') }}</span>
                    <strong data-role="prediction-winner-pick"></strong>
                    <small data-role="prediction-winner-breakdown"></small>
                </div>
                <div class="sb2-prediction-stat">
                    <span>{{ $sbText('predictions.draw_probability', 'Beraberlik olasılığı') }}</span>
                    <strong data-role="prediction-draw-probability"></strong>
                    <small data-role="prediction-provider-small"></small>
                </div>
                <div class="sb2-prediction-stat">
                    <span>{{ $sbText('predictions.total_goals', 'Toplam gol tahmini') }}</span>
                    <strong data-role="prediction-totals-pick"></strong>
                    <small data-role="prediction-totals-breakdown"></small>
                </div>
                <div class="sb2-prediction-stat">
                    <span>{{ $sbText('predictions.confidence', 'Confidence') }}</span>
                    <strong data-role="prediction-confidence"></strong>
                    <small data-role="prediction-predictable-small"></small>
                </div>
            </div>

            <div class="sb2-prediction-copy" data-role="prediction-copy">
                <p data-role="prediction-short-summary"></p>
                <p data-role="prediction-editor-note"></p>
            </div>
        </div>
    </article>
</template>

<script type="application/json" id="sportsbookShellInitialOfferSummary">@json($offerSummary ?? null)</script>
<script type="application/json" id="sportsbookShellRuntimeConfig">@json($sportsbookShellRuntimeConfig)</script>
<script type="application/json" id="sportsbookShellI18n">@json($sportsbookShellI18n)</script>
<script type="application/json" id="sportsbookShellBetFeedI18n">@json($betFeedUi)</script>
<script type="application/json" id="sportsbookShellBetFeedPolling">@json($betFeedPolling)</script>
<script type="application/json" id="sportsbookShellPredictionPolling">@json($predictionPolling)</script>
<script type="application/json" id="sportsbookShellSocialMarketPolling">@json($socialMarketPolling)</script>
<script type="application/json" id="sportsbookShellInitialPredictionItems">@json($renderInitialPredictionCards ? $predictionItems->values() : [])</script>
<script type="application/json" id="sportsbookShellInitialSocialMarkets">@json($renderInitialSocialMarketCards ? $socialMarketPayload : ['categories' => [], 'markets' => [], 'stats' => []])</script>
<script src="{{ asset('assets/cms/js/sportsbook-shell-v2.js') }}?v={{ filemtime(public_path('assets/cms/js/sportsbook-shell-v2.js')) }}"></script>
