<?php

namespace App\Services\ChessLiveCockpit;

use App\Models\ChessBettingMarket;
use App\Models\ChessLiveBoard;
use App\Models\ChessLiveEvent;
use App\Models\ChessWalletReservation;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class ChessLiveCockpitViewService
{
    /** @var ChessLiveSnapshotSyncService */
    private $snapshotSyncService;

    /** @var ChessBettingMarketActionService */
    private $marketActionService;

    /** @var ChessBettingMarketTemplateCatalog */
    private $templateCatalog;

    /** @var ChessLiveAttentionQueueService */
    private $attentionQueueService;

    /** @var ChessBettingMarketStateMachine */
    private $stateMachine;

    /** @var ChessLiveCockpitShadowMode */
    private $shadowMode;

    /** @var ChessRecurringSurfaceOverlayService */
    private $recurringOverlay;

    public function __construct(
        ChessLiveSnapshotSyncService $snapshotSyncService,
        ChessBettingMarketActionService $marketActionService,
        ChessBettingMarketTemplateCatalog $templateCatalog,
        ChessLiveAttentionQueueService $attentionQueueService,
        ChessBettingMarketStateMachine $stateMachine,
        ChessLiveCockpitShadowMode $shadowMode,
        ChessRecurringSurfaceOverlayService $recurringOverlay
    ) {
        $this->snapshotSyncService = $snapshotSyncService;
        $this->marketActionService = $marketActionService;
        $this->templateCatalog = $templateCatalog;
        $this->attentionQueueService = $attentionQueueService;
        $this->stateMachine = $stateMachine;
        $this->shadowMode = $shadowMode;
        $this->recurringOverlay = $recurringOverlay;
    }

    public function build(?int $selectedBoardId = null): array
    {
        $dailyBetMonitor = $this->dailyBetMonitor();
        $this->recurringOverlay->ensure();
        $event = $this->snapshotSyncService->syncActiveEvent();

        if (!$event instanceof ChessLiveEvent) {
            return [
                'event' => null,
                'eventMeta' => null,
                'boards' => collect(),
                'selectedBoard' => null,
                'templates' => collect(),
                'queueItems' => collect(),
                'logs' => collect(),
                'dailyBetSummary' => $dailyBetMonitor['summary'],
                'dailyBetReservations' => $dailyBetMonitor['reservations'],
                'dailyBetWindowLabel' => $dailyBetMonitor['windowLabel'],
                'stateLabels' => $this->stateMachine->labels(),
                'syncState' => [
                    'status' => 'unavailable',
                    'message' => 'Live source is unavailable and no cached snapshot exists yet.',
                    'freshness_status' => 'UNKNOWN',
                ],
                'shadowMode' => $this->shadowState(),
                'pollIntervalSeconds' => max(5, (int) config('chess_live_cockpit.poll_interval_seconds', 15)),
            ];
        }

        $boards = ChessLiveBoard::query()
            ->with([
                'event',
                'bettingMarkets.selections',
                'bettingMarkets.actionLogs.actor',
                'marketActionLogs.actor',
            ])
            ->where(function ($query) use ($event) {
                $query->where('chess_live_event_id', $event->id)
                    ->orWhereIn('external_board_key', $this->recurringOverlay->boardKeys());
            })
            ->get();

        $boards->each(function (ChessLiveBoard $board) {
            $this->marketActionService->syncAwaitingResultStatesForBoard($board);
        });

        $boards = ChessLiveBoard::query()
            ->with([
                'event',
                'bettingMarkets.selections',
                'bettingMarkets.actionLogs.actor',
                'marketActionLogs.actor',
            ])
            ->where(function ($query) use ($event) {
                $query->where('chess_live_event_id', $event->id)
                    ->orWhereIn('external_board_key', $this->recurringOverlay->boardKeys());
            })
            ->get();

        $sortedBoards = $this->sortBoards($boards);
        $selectedBoard = $sortedBoards->firstWhere('id', $selectedBoardId) ?: $sortedBoards->first();
        $selectedBoardGuard = $selectedBoard ? $this->boardGuardState($selectedBoard) : null;
        $templates = $selectedBoard ? $this->templateViewModels($selectedBoard, $selectedBoardGuard) : collect();
        $queue = $this->attentionQueueService->build($event, $sortedBoards);
        $logs = $event->marketActionLogs()
            ->with(['actor', 'market'])
            ->latest('created_at')
            ->limit(40)
            ->get();

        return [
            'event' => $event->fresh(),
            'eventMeta' => $this->eventMetaViewModel($event),
            'boards' => $this->boardViewModels($sortedBoards),
            'selectedBoard' => $selectedBoard ? $this->selectedBoardViewModel($selectedBoard) : null,
            'templates' => $templates,
            'queueItems' => $queue,
            'logs' => $logs,
            'dailyBetSummary' => $dailyBetMonitor['summary'],
            'dailyBetReservations' => $dailyBetMonitor['reservations'],
            'dailyBetWindowLabel' => $dailyBetMonitor['windowLabel'],
            'stateLabels' => $this->stateMachine->labels(),
            'syncState' => [
                'status' => data_get($event->meta, 'sync.status', 'ok'),
                'message' => data_get($event->meta, 'sync.message'),
                'freshness_status' => data_get($event->meta, 'source_freshness.status', 'UNKNOWN'),
                'freshness_reason' => data_get($event->meta, 'source_freshness.reason'),
                'observed_at' => data_get($event->meta, 'observed_at'),
                'failed_at' => data_get($event->meta, 'sync.failed_at'),
            ],
            'shadowMode' => $this->shadowState(),
            'pollIntervalSeconds' => max(5, (int) config('chess_live_cockpit.poll_interval_seconds', 15)),
        ];
    }

    private function dailyBetMonitor(): array
    {
        if (!Schema::hasTable('chess_wallet_reservations')) {
            return [
                'summary' => [
                    'total' => 0,
                    'pending' => 0,
                    'settled' => 0,
                    'won' => 0,
                    'lost' => 0,
                    'failed' => 0,
                ],
                'windowLabel' => Carbon::now()->startOfDay()->translatedFormat('d M Y'),
                'reservations' => collect(),
            ];
        }

        $windowStart = Carbon::now()->startOfDay();
        $windowEnd = Carbon::now()->endOfDay();

        $reservations = ChessWalletReservation::query()
            ->with([
                'user.parentAccount',
                'provider:id,name,slug',
            ])
            ->whereBetween('created_at', [$windowStart, $windowEnd])
            ->orderByDesc('reserved_at')
            ->orderByDesc('id')
            ->limit(60)
            ->get();

        $summary = [
            'total' => $reservations->count(),
            'pending' => $reservations->where('status', 'reserved')->count(),
            'settled' => $reservations->filter(fn (ChessWalletReservation $reservation) => str_starts_with((string) $reservation->status, 'settled_'))->count(),
            'won' => $reservations->where('status', 'settled_win')->count(),
            'lost' => $reservations->where('status', 'settled_loss')->count(),
            'failed' => $reservations->where('status', 'failed')->count(),
        ];

        return [
            'summary' => $summary,
            'windowLabel' => $windowStart->translatedFormat('d M Y'),
            'reservations' => $reservations->map(function (ChessWalletReservation $reservation) {
                $walletUser = $reservation->user;
                $primaryUser = $walletUser && $walletUser->parentAccount ? $walletUser->parentAccount : $walletUser;
                $requestPayload = (array) $reservation->request_payload;
                $settlementSummary = (array) $reservation->settlement_summary;
                $status = (string) $reservation->status;

                return [
                    'id' => $reservation->id,
                    'support_id' => $reservation->cms_reservation_id,
                    'technical_ref' => $reservation->market_instance_id ?: $reservation->cms_placement_ref,
                    'status' => $status,
                    'status_label' => $this->reservationStatusLabel($status, (string) $reservation->result_status),
                    'status_tone' => $this->reservationStatusTone($status),
                    'result_status' => $reservation->result_status,
                    'customer_label' => $primaryUser ? ($primaryUser->full_name ?: $primaryUser->username) : ('External ' . ((string) ($reservation->external_user_id ?: '-'))),
                    'wallet_label' => $walletUser ? $walletUser->username : null,
                    'currency' => $reservation->currency,
                    'stake_amount' => (float) $reservation->stake_amount,
                    'payout_amount' => (float) $reservation->payout_amount,
                    'partner_code' => $reservation->partner_code,
                    'provider_name' => optional($reservation->provider)->name ?: 'Chess',
                    'reserved_at' => optional($reservation->reserved_at ?: $reservation->created_at)->format('d.m.Y H:i:s'),
                    'settled_at' => optional($reservation->settled_at)->format('d.m.Y H:i:s'),
                    'created_at' => optional($reservation->created_at)->format('d.m.Y H:i:s'),
                    'match_id' => $requestPayload['matchId'] ?? null,
                    'market_instance_id' => $requestPayload['marketInstanceId'] ?? $reservation->market_instance_id,
                    'selection_id' => $requestPayload['selectionId'] ?? null,
                    'odds_decimal' => isset($requestPayload['oddsDecimal']) ? number_format((float) $requestPayload['oddsDecimal'], 2) : null,
                    'summary' => $settlementSummary['summary'] ?? null,
                    'wallet_context' => [
                        'transaction_wallet' => (string) ($requestPayload['wallet_currency'] ?? $reservation->currency ?: '-'),
                        'active_wallet' => (string) ($requestPayload['wallet_currency'] ?? $reservation->currency ?: '-'),
                        'product_type' => ucfirst((string) ($requestPayload['product_type'] ?? 'chess')),
                        'unsupported_reason' => $status === 'failed' ? ($reservation->reject_reason ?: null) : null,
                    ],
                ];
            })->values(),
        ];
    }

    public function suggestedSelectionCode(ChessBettingMarket $market): ?string
    {
        $meta = (array) optional($market->board)->meta;
        $resultHints = (array) ($meta['result_hints'] ?? []);
        $hintKey = (string) ($market->meta['result_hint_key'] ?? $market->template_type);

        return $resultHints[$hintKey] ?? null;
    }

    private function sortBoards(Collection $boards): Collection
    {
        return $boards->sort(function (ChessLiveBoard $a, ChessLiveBoard $b) {
            return $this->boardSortKey($b) <=> $this->boardSortKey($a)
                ?: (($a->board_no <=> $b->board_no));
        })->values();
    }

    private function boardSortKey(ChessLiveBoard $board): int
    {
        $markets = $board->bettingMarkets;
        $openCount = $markets->where('status', ChessBettingMarketStateMachine::STATUS_OPEN)->count();
        $reviewCount = $markets->whereIn('status', [
            ChessBettingMarketStateMachine::STATUS_OPEN,
            ChessBettingMarketStateMachine::STATUS_SUSPENDED,
            ChessBettingMarketStateMachine::STATUS_CLOSED,
            ChessBettingMarketStateMachine::STATUS_AWAITING_RESULT,
        ])->count();

        if ($board->featured_flag && $openCount > 0) {
            return 3000 + $openCount;
        }

        if ($board->official_result && $reviewCount > 0) {
            return 2000 + $reviewCount;
        }

        return ($board->featured_flag ? 1000 : 0) + ($openCount * 10);
    }

    private function boardViewModels(Collection $boards): Collection
    {
        return $boards->map(function (ChessLiveBoard $board) {
            $latestLog = $board->marketActionLogs->sortByDesc('created_at')->first();
            $reviewCount = $board->bettingMarkets->whereIn('status', [
                ChessBettingMarketStateMachine::STATUS_OPEN,
                ChessBettingMarketStateMachine::STATUS_SUSPENDED,
                ChessBettingMarketStateMachine::STATUS_CLOSED,
                ChessBettingMarketStateMachine::STATUS_AWAITING_RESULT,
            ])->count();
            $guard = $this->boardGuardState($board);
            $editorial = $this->editorialState($board);

            return [
                'id' => $board->id,
                'board_no' => $board->board_no,
                'white_player_name' => $board->white_player_name,
                'black_player_name' => $board->black_player_name,
                'white_clock' => $this->formatClock($board->white_clock_seconds),
                'black_clock' => $this->formatClock($board->black_clock_seconds),
                'official_status' => $board->official_status,
                'official_result' => $board->official_result,
                'featured_flag' => $board->featured_flag,
                'open_market_count' => $board->bettingMarkets->where('status', ChessBettingMarketStateMachine::STATUS_OPEN)->count(),
                'last_action' => $latestLog ? $latestLog->action_type : null,
                'awaiting_settle' => (bool) ($board->official_result && $reviewCount > 0),
                'source_updated_at' => optional($board->source_updated_at)->format('d.m H:i:s'),
                'source_freshness_status' => data_get($board->meta, 'source_freshness.status', 'UNKNOWN'),
                'source_freshness_reason' => data_get($board->meta, 'source_freshness.reason'),
                'source_type' => $guard['source_type'],
                'source_type_label' => $guard['source_type_label'],
                'source_integrity' => $guard['source_integrity'],
                'source_integrity_label' => $guard['source_integrity_label'],
                'critical_missing_fields' => $guard['critical_missing_fields'],
                'is_replaced' => $guard['is_replaced'],
                'is_cancelled' => $guard['is_cancelled'],
                'open_block_reason' => $guard['open_block_reason'],
                'settle_block_reason' => $guard['settle_block_reason'],
                'guard_warnings' => $guard['warnings'],
                'editorial' => $editorial,
                'shadow_mode' => $this->shadowMode->enabled(),
            ];
        });
    }

    private function selectedBoardViewModel(ChessLiveBoard $board): array
    {
        $guard = $this->boardGuardState($board);
        $editorial = $this->editorialState($board);

        $markets = $board->bettingMarkets->sortByDesc(function (ChessBettingMarket $market) {
            return optional($market->updated_at)->timestamp ?: 0;
        })->values()->map(function (ChessBettingMarket $market) use ($guard) {
            $actions = $this->marketActionAvailability($market, $guard);

            return array_merge($market->toArray(), [
                'selections' => $market->selections->map(function ($selection) {
                    return $selection->only(['selection_code', 'label']);
                })->values()->all(),
                'status_label' => $this->stateMachine->labels()[$market->status] ?? $market->status,
                'suggested_selection_code' => $this->suggestedSelectionCode($market),
                'latest_action' => optional($market->actionLogs->first())->action_type,
                'actions' => $actions,
            ]);
        });

        return [
            'id' => $board->id,
            'board_no' => $board->board_no,
            'event_name' => optional($board->event)->title,
            'stage_name' => optional($board->event)->stage_name,
            'round_name' => optional($board->event)->round_name,
            'white_player_name' => $board->white_player_name,
            'black_player_name' => $board->black_player_name,
            'white_rating' => $board->white_rating,
            'black_rating' => $board->black_rating,
            'white_points' => $board->white_points,
            'black_points' => $board->black_points,
            'white_clock' => $this->formatClock($board->white_clock_seconds),
            'black_clock' => $this->formatClock($board->black_clock_seconds),
            'official_status' => $board->official_status,
            'official_result' => $board->official_result,
            'official_source_url' => $board->official_source_url ?: optional($board->event)->official_source_url,
            'featured_flag' => $board->featured_flag,
            'source_updated_at' => optional($board->source_updated_at)->format('d.m.Y H:i:s'),
            'source_freshness_status' => data_get($board->meta, 'source_freshness.status', 'UNKNOWN'),
            'source_freshness_reason' => data_get($board->meta, 'source_freshness.reason'),
            'source_type' => $guard['source_type'],
            'source_type_label' => $guard['source_type_label'],
            'source_integrity' => $guard['source_integrity'],
            'source_integrity_label' => $guard['source_integrity_label'],
            'critical_missing_fields' => $guard['critical_missing_fields'],
            'is_replaced' => $guard['is_replaced'],
            'is_cancelled' => $guard['is_cancelled'],
            'replacement_target' => data_get($board->meta, 'replaced_by_source_board_id'),
            'replacement_source' => data_get($board->meta, 'replacement_of_source_board_id'),
            'warnings' => $guard['warnings'],
            'open_block_reason' => $guard['open_block_reason'],
            'settle_block_reason' => $guard['settle_block_reason'],
            'editorial' => $editorial,
            'shadow_mode' => $this->shadowMode->enabled(),
            'incomplete_warnings' => array_values(array_filter([
                $board->white_clock_seconds === null || $board->black_clock_seconds === null ? __('admin.chess_cockpit.live_clocks_incomplete') : null,
                $board->white_rating === null || $board->black_rating === null ? __('admin.chess_cockpit.rating_incomplete') : null,
                empty(data_get($board->meta, 'standing_context')) ? __('admin.chess_cockpit.standing_partial') : null,
            ])),
            'source_truth' => [
                'board_no' => $board->board_no,
                'white_player_name' => $board->white_player_name ?: '-',
                'black_player_name' => $board->black_player_name ?: '-',
                'stage_name' => optional($board->event)->stage_name ?: '-',
                'round_name' => optional($board->event)->round_name ?: '-',
                'official_status' => $board->official_status ?: '-',
                'official_result' => $board->official_result ?: '-',
                'replacement_target' => data_get($board->meta, 'replaced_by_source_board_id') ?: '-',
                'replacement_source' => data_get($board->meta, 'replacement_of_source_board_id') ?: '-',
                'source_ref' => $board->source_ref ?: '-',
            ],
            'markets' => $markets,
            'status_summary' => [
                'open' => $board->bettingMarkets->where('status', ChessBettingMarketStateMachine::STATUS_OPEN)->count(),
                'suspended' => $board->bettingMarkets->where('status', ChessBettingMarketStateMachine::STATUS_SUSPENDED)->count(),
                'review' => $board->bettingMarkets->whereIn('status', [
                    ChessBettingMarketStateMachine::STATUS_CLOSED,
                    ChessBettingMarketStateMachine::STATUS_AWAITING_RESULT,
                ])->count(),
                'settled' => $board->bettingMarkets->where('status', ChessBettingMarketStateMachine::STATUS_SETTLED)->count(),
            ],
        ];
    }

    private function templateViewModels(ChessLiveBoard $board, array $guard): Collection
    {
        return $this->templateCatalog->availableForBoard($board)->map(function (array $template) use ($guard) {
            return array_merge($template, [
                'open_block_reason' => $guard['open_block_reason'],
                'can_open' => $guard['open_block_reason'] === null,
            ]);
        })->values();
    }

    private function marketActionAvailability(ChessBettingMarket $market, array $boardGuard): array
    {
        $settleBlockedReason = null;

        if (!$boardGuard['has_official_result']) {
            $settleBlockedReason = 'Official result missing. Settlement stays locked until source truth confirms a result.';
        } elseif ($boardGuard['is_cancelled']) {
            $settleBlockedReason = 'Cancelled board cannot be settled from CMS. Review and void instead.';
        } elseif ($boardGuard['is_replaced']) {
            $settleBlockedReason = 'Replaced board should not be settled on the obsolete pairing.';
        } elseif ($boardGuard['source_integrity'] === 'incomplete') {
            $settleBlockedReason = 'Incomplete source truth must be reviewed before settlement.';
        }

        return [
            'settle_block_reason' => $settleBlockedReason,
        ];
    }

    private function boardGuardState(ChessLiveBoard $board): array
    {
        $meta = (array) $board->meta;
        $sourceType = $this->normalizeSourceType(Arr::get($meta, 'source_type'));
        $sourceIntegrity = $this->normalizeSourceIntegrity(Arr::get($meta, 'source_integrity'));
        $criticalMissingFields = array_values(array_filter((array) Arr::get($meta, 'critical_missing_fields', [])));
        $isReplaced = in_array($board->official_status, ['board_replaced'], true) || (bool) Arr::get($meta, 'is_replaced', false);
        $isCancelled = in_array($board->official_status, ['board_cancelled'], true) || (bool) Arr::get($meta, 'is_cancelled', false);
        $freshnessStatus = strtoupper((string) Arr::get($meta, 'source_freshness.status', 'UNKNOWN'));
        $warnings = [];

        if ($sourceType === 'manual' && $sourceIntegrity === 'source_pending') {
            $warnings[] = __('admin.chess_cockpit.manual_source_pending');
        }
        if ($sourceIntegrity === 'incomplete') {
            $warnings[] = __('admin.chess_cockpit.source_incomplete', ['fields' => implode(', ', $criticalMissingFields ?: ['unknown'])]);
        }
        if ($isReplaced) {
            $warnings[] = __('admin.chess_cockpit.board_replaced');
        }
        if ($isCancelled) {
            $warnings[] = __('admin.chess_cockpit.board_cancelled');
        }
        if ($freshnessStatus === 'STALE' || $freshnessStatus === 'UNKNOWN') {
            $warnings[] = __('admin.chess_cockpit.source_freshness', [
                'status' => __('admin.chess_cockpit.codes.'.strtolower($freshnessStatus)),
            ]);
        }

        $openBlockReason = null;
        if ($isCancelled) {
            $openBlockReason = __('admin.chess_cockpit.cancelled_open_block');
        } elseif ($isReplaced) {
            $openBlockReason = __('admin.chess_cockpit.replaced_open_block');
        } elseif ($sourceIntegrity === 'incomplete' && !empty($criticalMissingFields)) {
            $openBlockReason = __('admin.chess_cockpit.critical_fields_missing', ['fields' => implode(', ', $criticalMissingFields)]);
        }

        return [
            'source_type' => $sourceType,
            'source_type_label' => strtoupper($sourceType),
            'source_integrity' => $sourceIntegrity,
            'source_integrity_label' => strtoupper(str_replace('_', ' ', $sourceIntegrity)),
            'critical_missing_fields' => $criticalMissingFields,
            'is_replaced' => $isReplaced,
            'is_cancelled' => $isCancelled,
            'has_official_result' => (bool) $board->official_result,
            'warnings' => $warnings,
            'open_block_reason' => $openBlockReason,
            'settle_block_reason' => !$board->official_result
                ? __('admin.chess_cockpit.result_missing')
                : null,
        ];
    }

    private function editorialState(ChessLiveBoard $board): array
    {
        $meta = (array) $board->meta;

        return [
            'is_published' => (bool) Arr::get($meta, 'editorial.is_published', true),
            'is_hidden_from_betting_surface' => (bool) Arr::get($meta, 'editorial.is_hidden_from_betting_surface', false),
        ];
    }

    private function eventMetaViewModel(ChessLiveEvent $event): array
    {
        $meta = (array) $event->meta;

        return [
            'source_type' => $this->normalizeSourceType(Arr::get($meta, 'source_type')),
            'source_type_label' => strtoupper($this->normalizeSourceType(Arr::get($meta, 'source_type'))),
            'source_integrity' => $this->normalizeSourceIntegrity(Arr::get($meta, 'source_integrity')),
            'source_integrity_label' => strtoupper(str_replace('_', ' ', $this->normalizeSourceIntegrity(Arr::get($meta, 'source_integrity')))),
        ];
    }

    private function normalizeSourceType($value): string
    {
        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['manual', 'upstream'], true) ? $normalized : 'upstream';
    }

    private function normalizeSourceIntegrity($value): string
    {
        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['source_confirmed', 'source_pending', 'incomplete'], true)
            ? $normalized
            : 'source_confirmed';
    }

    private function formatClock(?int $seconds): string
    {
        if ($seconds === null) {
            return '-';
        }

        $minutes = intdiv(max(0, $seconds), 60);
        $remainder = max(0, $seconds) % 60;

        return sprintf('%02d:%02d', $minutes, $remainder);
    }

    private function shadowState(): array
    {
        return [
            'enabled' => $this->shadowMode->enabled(),
            'source_type' => $this->shadowMode->sourceType(),
            'guard_summary' => __('admin.chess_cockpit.shadow_guard'),
        ];
    }

    private function reservationStatusLabel(string $status, string $resultStatus = ''): string
    {
        return match ($status) {
            'reserved' => __('admin.ui.pending'),
            'settled_win' => __('admin.chess_cockpit.won'),
            'settled_loss' => __('admin.chess_cockpit.lost'),
            'failed' => __('admin.chess_cockpit.failed'),
            default => $resultStatus !== '' ? $resultStatus : ucfirst(str_replace('_', ' ', $status)),
        };
    }

    private function reservationStatusTone(string $status): string
    {
        return match ($status) {
            'reserved' => 'warning',
            'settled_win' => 'success',
            'settled_loss' => 'secondary',
            'failed' => 'danger',
            default => 'info',
        };
    }
}
