<?php

namespace App\Betting\Settlement;

use App\Models\ChessBettingMarket;
use App\Models\ChessLiveBoard;
use App\Models\ChessWalletReservation;
use App\Services\ChessLiveCockpit\ChessRecurringBoardResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class PendingChessSettlementReadService
{
    public function __construct(private readonly ChessRecurringBoardResolver $recurringBoards)
    {
    }

    public function filters(array $input): array
    {
        return [
            'search' => trim((string) ($input['search'] ?? '')),
            'user_id' => $this->integerFilter($input['user_id'] ?? null),
            'currency' => strtoupper(substr(trim((string) ($input['currency'] ?? '')), 0, 8)),
        ];
    }

    public function rows(array $filters): Collection
    {
        if (!$this->tablesAvailable()) {
            return collect();
        }

        return ChessWalletReservation::query()
            ->with(['user.parentAccount', 'provider'])
            ->where('status', 'reserved')
            ->orderByDesc('reserved_at')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (ChessWalletReservation $reservation): bool => $this->manualSettlementBoard($reservation) instanceof ChessLiveBoard)
            ->filter(function (ChessWalletReservation $reservation) use ($filters): bool {
                if ($filters['user_id'] !== null) {
                    $rootUserId = (int) (optional(optional($reservation->user)->parentAccount)->id ?: 0);
                    $reservationUserId = (int) ($reservation->cms_user_id ?: 0);

                    if ($reservationUserId !== $filters['user_id'] && $rootUserId !== $filters['user_id']) {
                        return false;
                    }
                }

                if ($filters['currency'] !== '' && strtoupper((string) $reservation->currency) !== $filters['currency']) {
                    return false;
                }

                if ($filters['search'] === '') {
                    return true;
                }

                $search = mb_strtolower($filters['search']);
                $payload = (array) $reservation->request_payload;
                $haystack = collect([
                    (string) $reservation->cms_reservation_id,
                    (string) $reservation->market_instance_id,
                    (string) data_get($payload, 'selectionId', ''),
                    (string) optional($reservation->user)->username,
                    (string) optional($reservation->user)->email,
                    (string) data_get($this->manualSettlementBoard($reservation)?->meta, 'recurring.title', ''),
                ])->implode(' ');

                return mb_stripos($haystack, $search) !== false;
            })
            ->map(fn (ChessWalletReservation $reservation): array => $this->row($reservation))
            ->values();
    }

    private function integerFilter($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        $normalized = (int) $value;

        return $normalized > 0 ? $normalized : null;
    }

    public function countPending(): int
    {
        return $this->rows($this->filters([]))->count();
    }

    public function findReviewableReservation(string $reservationId): ?ChessWalletReservation
    {
        $candidate = $this->reviewCandidate($reservationId);

        if ($candidate === null || !(bool) data_get($candidate, 'readiness.can_settle', false)) {
            return null;
        }

        return $candidate['reservation'];
    }

    public function reviewCandidate(string $reservationId): ?array
    {
        if (!$this->tablesAvailable()) {
            return null;
        }

        $reservation = ChessWalletReservation::query()
            ->with(['user.parentAccount', 'provider'])
            ->where('cms_reservation_id', $reservationId)
            ->where('status', 'reserved')
            ->first();

        if (!$reservation instanceof ChessWalletReservation) {
            return null;
        }

        $board = $this->manualSettlementBoard($reservation);
        if (!$board instanceof ChessLiveBoard) {
            return null;
        }

        $market = $this->openBoardMarket($board);
        $readiness = $this->readiness($reservation, $board, $market);

        return [
            'reservation' => $reservation,
            'board' => $board,
            'market' => $market,
            'readiness' => $readiness,
        ];
    }

    public function row(ChessWalletReservation $reservation): array
    {
        $board = $this->manualSettlementBoard($reservation);
        $market = $board ? $this->openBoardMarket($board) : null;
        $readiness = $this->readiness($reservation, $board, $market);
        $payload = (array) $reservation->request_payload;
        $selectionCode = trim((string) ($payload['selectionId'] ?? ''));
        $selectionLabel = $market
            ? (string) ($market->selections->firstWhere('selection_code', $selectionCode)?->label ?? '')
            : '';
        $oddsDecimal = is_numeric($payload['oddsDecimal'] ?? null) ? (float) $payload['oddsDecimal'] : null;
        $stake = (float) $reservation->stake_amount;
        $potentialReturn = $oddsDecimal !== null ? round($stake * $oddsDecimal, 6) : null;
        $rootUser = optional($reservation->user)->parentAccount ?: $reservation->user;
        $title = trim((string) data_get($board?->meta, 'recurring.title', 'Chess'));

        return [
            'source_type' => 'chess',
            'reservation' => $reservation,
            'board' => $board,
            'market' => $market,
            'user_label' => $rootUser?->username ?: ($reservation->user?->username ?: __('admin.settlement_read.unknown')),
            'source_label' => 'Chess',
            'queue_status' => [
                'label' => __('admin.settlement_read.chess_pending'),
                'badge_class' => 'bg-warning text-dark',
            ],
            'event_label' => $title,
            'market_label' => $market?->title ?: __('admin.settlement_read.chess_match_result', ['title' => $title]),
            'selection_label' => $selectionLabel !== '' ? $selectionLabel : ($selectionCode !== '' ? $selectionCode : __('admin.settlement_read.selection_pending')),
            'stake_amount' => $stake,
            'accepted_price' => $oddsDecimal,
            'potential_return' => $potentialReturn,
            'currency' => (string) $reservation->currency,
            'created_at' => $reservation->reserved_at ?: $reservation->created_at,
            'readiness' => $readiness,
            'can_settle' => (bool) ($readiness['can_settle'] ?? false),
            'action_hint' => (bool) ($readiness['can_settle'] ?? false)
                ? __('admin.settlement_read.ready')
                : __('admin.settlement_read.not_ready'),
            'operator_guidance' => [
                'title' => __('admin.settlement_read.chess_manual_title'),
                'body' => (bool) ($readiness['can_settle'] ?? false)
                    ? __('admin.settlement_read.chess_manual_ready')
                    : __('admin.settlement_read.chess_manual_waiting'),
                'items' => array_values(array_filter([
                    $oddsDecimal !== null
                        ? __('admin.settlement_read.stored_odds', ['odds' => number_format($oddsDecimal, 2, '.', '')])
                        : __('admin.settlement_read.stored_odds_missing'),
                    __('admin.settlement_read.won_effect'),
                    __('admin.settlement_read.lost_effect'),
                    __('admin.settlement_read.void_effect'),
                    !(bool) ($readiness['can_settle'] ?? false) ? __('admin.settlement_read.chess_row_visible') : null,
                ])),
            ],
        ];
    }

    public function defaultPayoutAmount(ChessWalletReservation $reservation): ?float
    {
        $payload = (array) $reservation->request_payload;

        if (!is_numeric($payload['oddsDecimal'] ?? null)) {
            return null;
        }

        return round((float) $reservation->stake_amount * (float) $payload['oddsDecimal'], 6);
    }

    private function manualSettlementBoard(ChessWalletReservation $reservation): ?ChessLiveBoard
    {
        $payload = (array) $reservation->request_payload;
        $marketInstanceId = trim((string) ($reservation->market_instance_id ?: data_get($payload, 'marketInstanceId', '')));
        $boardKey = $this->recurringBoards->resolve($marketInstanceId, $payload);
        if ($boardKey === null || !Schema::hasTable('chess_live_boards')) {
            return null;
        }

        $board = ChessLiveBoard::query()
            ->where('external_board_key', $boardKey)
            ->first();

        if (!$board instanceof ChessLiveBoard) {
            return null;
        }

        $recurring = (array) data_get($board->meta, 'recurring', []);

        return (bool) ($recurring['enabled'] ?? false)
            && (bool) ($recurring['operator_managed'] ?? false)
            && (bool) ($recurring['manual_settlement_eligible'] ?? false)
                ? $board
                : null;
    }

    private function readiness(
        ChessWalletReservation $reservation,
        ?ChessLiveBoard $board,
        ?ChessBettingMarket $market
    ): array {
        return [
            'code' => 'operator_managed',
            'label' => __('admin.settlement_read.ready'),
            'badge_class' => 'bg-success',
            'can_settle' => true,
            'reason' => __('admin.settlement_read.chess_ready_reason'),
        ];
    }

    private function openBoardMarket(ChessLiveBoard $board): ?ChessBettingMarket
    {
        return ChessBettingMarket::query()
            ->with('selections')
            ->where('chess_live_board_id', $board->id)
            ->where('status', 'open')
            ->orderBy('id')
            ->first();
    }

    private function tablesAvailable(): bool
    {
        return Schema::hasTable('chess_wallet_reservations')
            && Schema::hasTable('chess_live_boards')
            && Schema::hasTable('chess_betting_markets')
            && Schema::hasTable('chess_betting_market_selections');
    }
}
