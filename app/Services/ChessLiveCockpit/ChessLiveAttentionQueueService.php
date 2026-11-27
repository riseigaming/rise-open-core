<?php

namespace App\Services\ChessLiveCockpit;

use App\Models\ChessLiveBoard;
use App\Models\ChessLiveEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class ChessLiveAttentionQueueService
{
    public function build(ChessLiveEvent $event, Collection $boards): Collection
    {
        $now = CarbonImmutable::now();
        $staleAfterMinutes = max(1, (int) config('chess_live_cockpit.stale_after_minutes', 5));

        return $boards->flatMap(function (ChessLiveBoard $board) use ($event, $now, $staleAfterMinutes) {
            $items = collect();
            $markets = $board->bettingMarkets;
            $openMarkets = $markets->where('status', ChessBettingMarketStateMachine::STATUS_OPEN);
            $reviewMarkets = $markets->whereIn('status', [
                ChessBettingMarketStateMachine::STATUS_OPEN,
                ChessBettingMarketStateMachine::STATUS_SUSPENDED,
                ChessBettingMarketStateMachine::STATUS_CLOSED,
                ChessBettingMarketStateMachine::STATUS_AWAITING_RESULT,
            ]);
            $qualificationMarkets = $markets->whereIn('template_type', [
                'player_qualifies_yes_no',
                'top_3_yes_no',
                'mini_match_qualifies_yes_no',
            ])->whereNotIn('status', [
                ChessBettingMarketStateMachine::STATUS_SETTLED,
                ChessBettingMarketStateMachine::STATUS_VOID,
            ]);
            $sourceType = strtolower((string) Arr::get($board->meta, 'source_type', 'upstream'));
            $sourceIntegrity = strtolower((string) Arr::get($board->meta, 'source_integrity', 'source_confirmed'));
            $criticalMissingFields = (array) Arr::get($board->meta, 'critical_missing_fields', []);

            if ($sourceType === 'manual' && $sourceIntegrity === 'source_pending') {
                $items->push([
                    'type' => 'manual_source_pending_review',
                    'severity' => 'warning',
                    'label' => 'Manual source pending review',
                    'board_id' => $board->id,
                    'board_no' => $board->board_no,
                    'summary' => 'Board ' . $board->board_no . ' comes from manual source truth and is still pending confirmation.',
                ]);
            }

            if ($sourceType === 'manual' && $sourceIntegrity === 'incomplete') {
                $items->push([
                    'type' => 'incomplete_manual_board_warning',
                    'severity' => 'danger',
                    'label' => 'Incomplete manual board warning',
                    'board_id' => $board->id,
                    'board_no' => $board->board_no,
                    'summary' => 'Board ' . $board->board_no . ' manual source truth is incomplete. Missing: ' . implode(', ', $criticalMissingFields ?: ['unknown']) . '.',
                ]);
            }

            if (in_array($board->official_status, ['new_pairing', 'pairing_created'], true) && $openMarkets->isEmpty()) {
                $items->push([
                    'type' => 'new_pairing_no_market',
                    'severity' => 'warning',
                    'label' => 'New pairing but no market open',
                    'board_id' => $board->id,
                    'board_no' => $board->board_no,
                    'summary' => 'Board ' . $board->board_no . ' has a fresh pairing and still has no open market.',
                ]);
            }

            if ($board->featured_flag && in_array($board->official_status, ['in_progress', 'new_pairing', 'result_posted', 'pairing_created', 'board_started', 'board_updated', 'result_visible'], true)) {
                $items->push([
                    'type' => 'featured_board_active',
                    'severity' => 'info',
                    'label' => 'Featured board active',
                    'board_id' => $board->id,
                    'board_no' => $board->board_no,
                    'summary' => 'Featured board ' . $board->board_no . ' should stay under direct operator attention.',
                ]);
            }

            if ($board->official_result && $reviewMarkets->isNotEmpty()) {
                $items->push([
                    'type' => 'result_visible_not_settled',
                    'severity' => 'danger',
                    'label' => 'Official result visible but not settled',
                    'board_id' => $board->id,
                    'board_no' => $board->board_no,
                    'summary' => 'Board ' . $board->board_no . ' has an official result and ' . $reviewMarkets->count() . ' unsettled market(s).',
                ]);
            }

            if (in_array($board->official_status, ['board_replaced', 'board_cancelled'], true) || Arr::get($board->meta, 'is_replaced') || Arr::get($board->meta, 'is_cancelled')) {
                $items->push([
                    'type' => 'replacement_or_cancel_warning',
                    'severity' => 'warning',
                    'label' => 'Replaced/cancelled board warning',
                    'board_id' => $board->id,
                    'board_no' => $board->board_no,
                    'summary' => 'Board ' . $board->board_no . ' is marked as replaced or cancelled. Open markets should be reviewed immediately.',
                ]);
            }

            if (($event->meta['standings_changed'] ?? false) && $qualificationMarkets->isNotEmpty()) {
                $items->push([
                    'type' => 'standings_review_needed',
                    'severity' => 'warning',
                    'label' => 'Standings changed, qualification review needed',
                    'board_id' => $board->id,
                    'board_no' => $board->board_no,
                    'summary' => 'Qualification-related markets on board ' . $board->board_no . ' need review against the latest standings.',
                ]);
            }

            $freshnessStatus = (string) Arr::get($board->meta, 'source_freshness.status', '');
            if (
                $freshnessStatus === 'STALE' ||
                $freshnessStatus === 'UNKNOWN' ||
                ($board->source_updated_at && $board->source_updated_at->lt($now->subMinutes($staleAfterMinutes)))
            ) {
                $items->push([
                    'type' => 'stale_source',
                    'severity' => 'secondary',
                    'label' => 'Stale source warning',
                    'board_id' => $board->id,
                    'board_no' => $board->board_no,
                    'summary' => 'Board ' . $board->board_no . ' source snapshot is stale or missing freshness confirmation.',
                ]);
            }

            return $items;
        })->sortByDesc(function (array $item) {
            if ($item['severity'] === 'danger') {
                return 400;
            }

            if ($item['severity'] === 'warning') {
                return 300;
            }

            if ($item['severity'] === 'info') {
                return 200;
            }

            return 100;
        })->values();
    }
}
