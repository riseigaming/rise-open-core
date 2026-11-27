<?php

namespace App\Services\ChessLiveCockpit;

use App\Models\ChessBettingMarket;
use App\Models\ChessBettingMarketSelection;
use App\Models\ChessLiveBoard;
use App\Models\ChessLiveEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ChessRecurringSurfaceOverlayService
{
    public const BETTING_OPEN_HOURS_BEFORE = 168;
    public const BETTING_CLOSE_MINUTES_AFTER = 30;

    private const EVENT_KEY = 'cms-recurring-chessbet-public';

    private const DEFINITIONS = [
        [
            'board_key' => 'cms-recurring-tuesday',
            'slug' => 'titled-tuesday',
            'title' => 'Titled Tuesday',
            'board_no' => 901,
            'white_player_name' => 'Titled Tuesday',
            'black_player_name' => 'Open Field',
            'weekday' => 2,
            'start_time' => '17:00',
            'schedule_timezone' => 'Europe/Paris',
            'duration_minutes' => 120,
            'betting_open_hours_before' => self::BETTING_OPEN_HOURS_BEFORE,
            'candidate_labels' => ['Hikaru Nakamura', 'Magnus Carlsen'],
        ],
        [
            'board_key' => 'cms-recurring-freestyle-friday',
            'slug' => 'freestyle-friday',
            'title' => 'Freestyle Friday',
            'board_no' => 902,
            'white_player_name' => 'Freestyle Friday',
            'black_player_name' => 'Open Field',
            'weekday' => 5,
            'start_time' => '20:00',
            'schedule_timezone' => 'Europe/Istanbul',
            'duration_minutes' => 120,
            'betting_open_hours_before' => self::BETTING_OPEN_HOURS_BEFORE,
            'candidate_labels' => ['Magnus Carlsen', 'Hikaru Nakamura'],
        ],
        [
            'board_key' => 'cms-recurring-3-0-thursday',
            'slug' => '3-0-thursday',
            'title' => '3+0 Thursday',
            'board_no' => 903,
            'white_player_name' => '3+0 Thursday',
            'black_player_name' => 'Open Field',
            'weekday' => 4,
            'start_time' => '20:00',
            'schedule_timezone' => 'Europe/Istanbul',
            'duration_minutes' => 120,
            'betting_open_hours_before' => self::BETTING_OPEN_HOURS_BEFORE,
            'candidate_labels' => ['Hikaru Nakamura', 'Magnus Carlsen'],
        ],
    ];

    public function boardKeys(): array
    {
        return collect(self::DEFINITIONS)
            ->pluck('board_key')
            ->map(fn (string $key): string => $key)
            ->all();
    }

    public function ensure(): ChessLiveEvent
    {
        $now = CarbonImmutable::now();

        $event = ChessLiveEvent::query()->firstOrNew([
            'external_event_key' => self::EVENT_KEY,
        ]);

        $event->fill([
            'title' => 'ChessBet Recurring Surfaces',
            'stage_name' => 'Recurring',
            'round_name' => 'Public Rotation',
            'official_status' => 'scheduled',
            'official_result' => null,
            'official_source_url' => null,
            'source_updated_at' => $now,
            'meta' => array_merge((array) $event->meta, [
                'source_type' => 'manual',
                'source_integrity' => 'source_confirmed',
                'recurring_overlay' => true,
                'operator_managed' => true,
                'public_eligible' => true,
            ]),
        ]);
        $event->save();

        foreach (self::DEFINITIONS as $definition) {
            $board = ChessLiveBoard::query()->firstOrNew([
                'external_board_key' => $definition['board_key'],
            ]);

            $board->fill([
                'chess_live_event_id' => $event->id,
                'board_no' => $definition['board_no'],
                'white_player_name' => $definition['white_player_name'],
                'black_player_name' => $definition['black_player_name'],
                'white_rating' => null,
                'black_rating' => null,
                'white_points' => null,
                'black_points' => null,
                'white_clock_seconds' => null,
                'black_clock_seconds' => null,
                'official_status' => 'scheduled',
                'official_result' => null,
                'official_source_url' => null,
                'featured_flag' => true,
                'source_updated_at' => $now,
                'source_ref' => 'cms://chess/recurring/' . $definition['slug'],
                'meta' => array_merge((array) $board->meta, [
                    'source_type' => 'manual',
                    'source_integrity' => 'source_confirmed',
                    'critical_missing_fields' => [],
                    'supports_player_qualifies' => false,
                    'supports_top3' => false,
                    'supports_mini_match' => false,
                    'result_hints' => [],
                    'editorial' => [
                        'is_published' => true,
                        'is_hidden_from_betting_surface' => false,
                    ],
                    'recurring' => [
                        'enabled' => true,
                        'slug' => $definition['slug'],
                        'title' => $definition['title'],
                        'operator_managed' => true,
                        'public_eligible' => true,
                        'manual_settlement_eligible' => true,
                        'bettable' => true,
                        'weekday' => $definition['weekday'],
                        'start_time' => $definition['start_time'],
                        'schedule_timezone' => $definition['schedule_timezone'],
                        'duration_minutes' => $definition['duration_minutes'],
                        'betting_open_hours_before' => $definition['betting_open_hours_before'],
                    ],
                ]),
            ]);
            $board->save();

            $this->ensureOpenMarket($event, $board, $definition, $now);
        }

        return $event->fresh();
    }

    public function publicCards(): Collection
    {
        $this->ensure();

        return ChessLiveBoard::query()
            ->with([
                'bettingMarkets' => fn ($query) => $query->where('status', ChessBettingMarketStateMachine::STATUS_OPEN),
                'bettingMarkets.selections',
            ])
            ->whereIn('external_board_key', collect(self::DEFINITIONS)->pluck('board_key'))
            ->orderBy('board_no')
            ->get()
            ->map(function (ChessLiveBoard $board): ?array {
                $recurring = (array) data_get($board->meta, 'recurring', []);
                $title = trim((string) ($recurring['title'] ?? $board->white_player_name));
                $slug = trim((string) ($recurring['slug'] ?? ''));
                $market = $board->bettingMarkets->first();
                $definition = array_replace_recursive(
                    $this->definitionForBoardKey((string) $board->external_board_key),
                    $recurring
                );

                if ($title === '' || $slug === '' || !$market instanceof ChessBettingMarket) {
                    return null;
                }

                $schedule = $this->scheduleForDefinition($definition);
                $boardKey = (string) $board->external_board_key;
                $eventHref = route('cms.front.providers.chess', [
                    'redirect' => $this->providerMarketRedirect($boardKey),
                ]);
                $candidateSelections = $this->publicTournamentSelections($market, $eventHref, $boardKey);

                return [
                    'contextType' => 'TOURNAMENT',
                    'isRecurringTournament' => true,
                    'name' => $title,
                    'tournamentName' => 'Chess Bet',
                    'roundName' => trans('cms_public.chess.winner_market'),
                    'marketLabel' => trans('cms_public.chess.winner_market'),
                    'statusLabel' => $schedule['status_label'],
                    'whitePlayerName' => $board->white_player_name,
                    'blackPlayerName' => $board->black_player_name,
                    'participantCount' => $candidateSelections['total'],
                    'scheduledStartAt' => $schedule['display'],
                    'startsAt' => $schedule['starts_at'],
                    'endsAt' => $schedule['ends_at'],
                    'bettingStatus' => $schedule['status'],
                    'href' => $eventHref,
                    'selections' => $schedule['status'] === 'open' ? $candidateSelections['visible']->all() : [],
                    'source' => 'controlled_recurring',
                    'sortPriority' => $schedule['sort_priority'],
                ];
            })
            ->filter()
            ->sortBy(fn (array $card): string => sprintf(
                '%02d|%s',
                (int) ($card['sortPriority'] ?? 99),
                (string) ($card['startsAt'] ?? '')
            ))
            ->values();
    }

    private function ensureOpenMarket(
        ChessLiveEvent $event,
        ChessLiveBoard $board,
        array $definition,
        CarbonImmutable $now
    ): void {
        $market = ChessBettingMarket::query()
            ->where('chess_live_board_id', $board->id)
            ->where('template_variant', 'recurring')
            ->whereNotIn('status', [
                ChessBettingMarketStateMachine::STATUS_SETTLED,
                ChessBettingMarketStateMachine::STATUS_VOID,
            ])
            ->first();

        if (!$market instanceof ChessBettingMarket) {
            $market = ChessBettingMarket::query()->create([
                'chess_live_event_id' => $event->id,
                'chess_live_board_id' => $board->id,
                'scope_type' => 'board',
                'template_type' => 'tournament_winner',
                'template_variant' => 'recurring',
                'title' => $definition['title'] . ' Winner',
                'status' => ChessBettingMarketStateMachine::STATUS_OPEN,
                'opened_at' => $now,
                'source_ref' => $board->source_ref,
                'meta' => [
                    'source_type' => 'manual',
                    'source_integrity' => 'source_confirmed',
                    'recurring_overlay' => true,
                    'operator_managed' => true,
                    'public_eligible' => true,
                    'manual_settlement_eligible' => true,
                    'acceptance_downstream_enabled' => true,
                    'settlement_downstream_enabled' => true,
                ],
            ]);
        } else {
            $market->update([
                'template_type' => 'tournament_winner',
                'title' => $definition['title'] . ' Winner',
                'opened_at' => $market->opened_at ?: $now,
                'meta' => array_merge((array) $market->meta, [
                    'recurring_overlay' => true,
                    'operator_managed' => true,
                    'public_eligible' => true,
                    'manual_settlement_eligible' => true,
                    'acceptance_downstream_enabled' => true,
                    'settlement_downstream_enabled' => true,
                ]),
            ]);
        }

        collect($definition['candidate_labels'] ?? [])
            ->values()
            ->each(function (string $label, int $index) use ($market) {
                $this->ensureSelection($market, $this->candidateSelectionCode($label), $label, $index);
            });
        $this->ensureSelection($market, 'field', 'Other', 99);
    }

    private function ensureSelection(ChessBettingMarket $market, string $code, string $label, int $sortOrder): void
    {
        $selection = ChessBettingMarketSelection::query()->firstOrNew([
            'chess_betting_market_id' => $market->id,
            'selection_code' => $code,
        ]);

        $selection->fill([
            'label' => $label,
            'sort_order' => $sortOrder,
            'meta' => array_merge((array) $selection->meta, [
                'recurring_overlay' => true,
            ]),
        ]);
        $selection->save();
    }

    private function definitionForBoardKey(string $boardKey): array
    {
        return collect(self::DEFINITIONS)
            ->firstWhere('board_key', $boardKey) ?? self::DEFINITIONS[0];
    }

    private function scheduleForDefinition(array $definition): array
    {
        $appTimezone = config('app.timezone', 'Europe/Istanbul');
        $sourceTimezone = (string) ($definition['schedule_timezone'] ?? $appTimezone);
        $now = CarbonImmutable::now($appTimezone);
        $weekday = max(1, min(7, (int) ($definition['weekday'] ?? 1)));
        $startTime = (string) ($definition['start_time'] ?? '20:00');
        [$hour, $minute] = array_pad(array_map('intval', explode(':', $startTime, 2)), 2, 0);

        $nowInSource = $now->setTimezone($sourceTimezone);
        $daysUntil = ($weekday - $nowInSource->isoWeekday() + 7) % 7;
        $start = $nowInSource
            ->startOfDay()
            ->addDays($daysUntil)
            ->setTime($hour, $minute);

        if ($nowInSource->isoWeekday() === $weekday) {
            $todayStart = $nowInSource
                ->startOfDay()
                ->setTime($hour, $minute);
            $todayEnd = $todayStart->addMinutes((int) ($definition['duration_minutes'] ?? 120));

            if ($nowInSource->lessThan($todayEnd)) {
                $start = $todayStart;
            } else {
                $start = $todayStart->addWeek();
            }
        }

        $durationMinutes = max(30, (int) ($definition['duration_minutes'] ?? 120));
        $openAt = $start->subHours(max(0, (int) ($definition['betting_open_hours_before'] ?? self::BETTING_OPEN_HOURS_BEFORE)));
        $closeAt = $start->addMinutes(self::BETTING_CLOSE_MINUTES_AFTER);
        $endAt = $start->addMinutes($durationMinutes);
        $status = 'upcoming';

        if ($nowInSource->greaterThanOrEqualTo($openAt) && $nowInSource->lessThan($closeAt)) {
            $status = 'open';
        } elseif ($nowInSource->greaterThanOrEqualTo($closeAt) && $nowInSource->lessThan($endAt)) {
            $status = 'closed';
        }

        return [
            'starts_at' => $start->setTimezone($appTimezone)->toIso8601String(),
            'ends_at' => $endAt->setTimezone($appTimezone)->toIso8601String(),
            'display' => $start->setTimezone($appTimezone)->translatedFormat('d M, D H:i'),
            'status' => $status,
            'status_label' => trans('cms_public.chess.status.' . $status),
            'sort_priority' => ['open' => 0, 'closed' => 1, 'upcoming' => 2][$status] ?? 9,
        ];
    }

    private function publicTournamentSelections(ChessBettingMarket $market, string $eventHref, string $boardKey): array
    {
        $all = $market->selections
            ->reject(fn (ChessBettingMarketSelection $selection): bool => $this->isLegacyRecurringSelection($selection))
            ->values();

        $visible = $all->take(3)->map(function (ChessBettingMarketSelection $selection) use ($boardKey): array {
            return [
                'id' => (string) $selection->selection_code,
                'name' => $this->publicSelectionLabel($selection),
                'odds' => null,
                'ctaLabel' => trans('cms_public.chess.bet_cta'),
                'href' => route('cms.front.providers.chess', [
                    'redirect' => $this->providerMarketRedirect($boardKey, (string) $selection->selection_code),
                ]),
            ];
        });

        if ($all->count() > 3) {
            $visible = $all->take(2)->map(function (ChessBettingMarketSelection $selection) use ($boardKey): array {
                return [
                    'id' => (string) $selection->selection_code,
                    'name' => $this->publicSelectionLabel($selection),
                    'odds' => null,
                    'ctaLabel' => trans('cms_public.chess.bet_cta'),
                    'href' => route('cms.front.providers.chess', [
                        'redirect' => $this->providerMarketRedirect($boardKey, (string) $selection->selection_code),
                    ]),
                ];
            })->push([
                'id' => 'more',
                'name' => trans('cms_public.chess.more_candidates', ['count' => $all->count() - 2]),
                'odds' => null,
                'ctaLabel' => trans('cms_public.chess.view_all_cta'),
                'href' => $eventHref,
            ]);
        }

        return [
            'total' => $all->count(),
            'visible' => $visible->values(),
        ];
    }

    private function providerMarketRedirect(string $boardKey, ?string $selectionId = null): string
    {
        $query = ['marketInstanceId' => $boardKey];

        if ($selectionId !== null && $selectionId !== '') {
            $query['selectionId'] = $selectionId;
        }

        return '/market-instances/' . rawurlencode($boardKey) . '?' . http_build_query($query);
    }

    private function isLegacyRecurringSelection(ChessBettingMarketSelection $selection): bool
    {
        $code = (string) $selection->selection_code;
        $label = trim((string) $selection->label);

        return in_array($code, ['home', 'draw', 'away'], true)
            && in_array($label, ['Draw', 'Open Field', 'Titled Tuesday', 'Freestyle Friday', '3 0 Thursday', '3+0 Thursday'], true)
            && (bool) data_get($selection->meta, 'recurring_overlay', false);
    }

    private function publicSelectionLabel(ChessBettingMarketSelection $selection): string
    {
        return (string) $selection->selection_code === 'field'
            ? trans('cms_public.chess.other_candidate')
            : (string) $selection->label;
    }

    private function candidateSelectionCode(string $label): string
    {
        $slug = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '_', $label), '_'));

        return 'player_' . ($slug !== '' ? $slug : 'candidate');
    }
}
