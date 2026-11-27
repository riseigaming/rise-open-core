<?php

namespace App\Services\ChessLiveCockpit;

use App\Models\ChessBettingMarket;
use App\Models\ChessBettingMarketActionLog;
use App\Models\ChessBettingMarketSelection;
use App\Models\ChessLiveBoard;
use App\Models\CmsUser;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChessBettingMarketActionService
{
    /** @var ChessBettingMarketTemplateCatalog */
    private $templateCatalog;

    /** @var ChessBettingMarketStateMachine */
    private $stateMachine;

    /** @var ChessLiveCockpitShadowMode */
    private $shadowMode;

    public function __construct(
        ChessBettingMarketTemplateCatalog $templateCatalog,
        ChessBettingMarketStateMachine $stateMachine,
        ChessLiveCockpitShadowMode $shadowMode
    ) {
        $this->templateCatalog = $templateCatalog;
        $this->stateMachine = $stateMachine;
        $this->shadowMode = $shadowMode;
    }

    public function createFromTemplate(ChessLiveBoard $board, string $templateKey, string $targetStatus, ?CmsUser $actor): ChessBettingMarket
    {
        $this->guardBoardOpenEligibility($board);

        $template = $this->templateCatalog->findOrFail($board, $templateKey);
        $targetStatus = $targetStatus === ChessBettingMarketStateMachine::STATUS_DRAFT
            ? ChessBettingMarketStateMachine::STATUS_DRAFT
            : ChessBettingMarketStateMachine::STATUS_OPEN;

        return DB::transaction(function () use ($board, $template, $targetStatus, $actor) {
            $existing = ChessBettingMarket::query()
                ->where('chess_live_board_id', $board->id)
                ->where('template_type', $template['template_type'])
                ->where('template_variant', $template['template_variant'])
                ->whereNotIn('status', [
                    ChessBettingMarketStateMachine::STATUS_SETTLED,
                    ChessBettingMarketStateMachine::STATUS_VOID,
                ])
                ->first();

            if ($existing) {
                throw ValidationException::withMessages([
                    'template_key' => 'An active or reviewable market already exists for this template.',
                ]);
            }

            /** @var ChessBettingMarket $market */
            $market = ChessBettingMarket::query()->create([
                'chess_live_event_id' => $board->chess_live_event_id,
                'chess_live_board_id' => $board->id,
                'scope_type' => $template['scope_type'],
                'template_type' => $template['template_type'],
                'template_variant' => $template['template_variant'],
                'title' => $template['title'],
                'status' => $targetStatus,
                'opened_at' => $targetStatus === ChessBettingMarketStateMachine::STATUS_OPEN ? CarbonImmutable::now() : null,
                'created_by' => optional($actor)->id,
                'updated_by' => optional($actor)->id,
                'source_ref' => $board->source_ref,
                'meta' => [
                    'template_key' => $template['key'],
                    'template_group_label' => $template['group_label'],
                    'player_side' => $template['player_side'] ?? null,
                    'player_name' => $template['player_name'] ?? null,
                    'result_hint_key' => $template['result_hint_key'] ?? null,
                ],
            ]);

            $market->update([
                'meta' => $this->shadowMode->decorateMarketMeta((array) $market->meta, 'template_create'),
            ]);

            foreach ($template['selections'] as $index => $selection) {
                ChessBettingMarketSelection::query()->create([
                    'chess_betting_market_id' => $market->id,
                    'selection_code' => $selection['code'],
                    'label' => $selection['label'],
                    'sort_order' => $index,
                ]);
            }

            $this->log(
                $market->fresh('board'),
                $actor,
                'template_create',
                null,
                $targetStatus,
                null,
                null
            );

            return $market->fresh(['selections', 'board', 'event', 'actionLogs.actor']);
        });
    }

    public function openDraft(ChessBettingMarket $market, ?CmsUser $actor): ChessBettingMarket
    {
        return $this->transition($market, ChessBettingMarketStateMachine::ACTION_OPEN, $actor);
    }

    public function suspend(ChessBettingMarket $market, ?CmsUser $actor): ChessBettingMarket
    {
        return $this->transition($market, ChessBettingMarketStateMachine::ACTION_SUSPEND, $actor);
    }

    public function reopen(ChessBettingMarket $market, ?CmsUser $actor): ChessBettingMarket
    {
        return $this->transition($market, ChessBettingMarketStateMachine::ACTION_REOPEN, $actor);
    }

    public function close(ChessBettingMarket $market, ?CmsUser $actor): ChessBettingMarket
    {
        return $this->transition($market, ChessBettingMarketStateMachine::ACTION_CLOSE, $actor);
    }

    public function void(ChessBettingMarket $market, ?CmsUser $actor, ?string $note = null): ChessBettingMarket
    {
        return $this->transition($market, ChessBettingMarketStateMachine::ACTION_VOID, $actor, [
            'note' => $note,
        ]);
    }

    public function settle(
        ChessBettingMarket $market,
        string $selectionCode,
        string $reason,
        ?string $note,
        ?CmsUser $actor
    ): ChessBettingMarket {
        $this->stateMachine->validateSettleReason($reason);

        $board = $market->board()->first();
        if (!$board instanceof ChessLiveBoard || !$board->official_result) {
            throw ValidationException::withMessages([
                'official_result' => 'Official result is required before settlement.',
            ]);
        }

        if (in_array($board->official_status, ['board_cancelled', 'board_replaced'], true)
            || Arr::get($board->meta, 'is_cancelled')
            || Arr::get($board->meta, 'is_replaced')) {
            throw ValidationException::withMessages([
                'official_result' => 'Cancelled or replaced boards cannot be settled from CMS.',
            ]);
        }

        if (strtolower((string) Arr::get($board->meta, 'source_integrity', 'source_confirmed')) === 'incomplete') {
            throw ValidationException::withMessages([
                'official_result' => 'Incomplete source truth must be reviewed before settlement.',
            ]);
        }

        if ($this->stateMachine->requiresSettleNote($reason) && trim((string) $note) === '') {
            throw ValidationException::withMessages([
                'settle_note' => 'A note is required when using manual_override.',
            ]);
        }

        $selection = $market->selections()->where('selection_code', $selectionCode)->first();
        if (!$selection instanceof ChessBettingMarketSelection) {
            throw ValidationException::withMessages([
                'result_selection_code' => 'The selected settlement outcome is invalid for this market.',
            ]);
        }

        return $this->transition($market, ChessBettingMarketStateMachine::ACTION_SETTLE, $actor, [
            'note' => $note,
            'reason' => $reason,
            'selection' => [
                'code' => $selection->selection_code,
                'label' => $selection->label,
            ],
        ]);
    }

    public function updateNote(ChessBettingMarket $market, string $note, ?CmsUser $actor): ChessBettingMarket
    {
        $market->update([
            'operator_note' => $note,
            'updated_by' => optional($actor)->id,
            'meta' => $this->shadowMode->decorateMarketMeta((array) $market->meta, ChessBettingMarketStateMachine::ACTION_NOTE),
        ]);

        $this->log($market->fresh(['board', 'event']), $actor, ChessBettingMarketStateMachine::ACTION_NOTE, $market->status, $market->status, null, $note);

        return $market->fresh(['selections', 'board', 'event', 'actionLogs.actor']);
    }

    public function syncAwaitingResultStatesForBoard(ChessLiveBoard $board): void
    {
        if (!$board->official_result) {
            return;
        }

        ChessBettingMarket::query()
            ->where('chess_live_board_id', $board->id)
            ->where('status', ChessBettingMarketStateMachine::STATUS_CLOSED)
            ->get()
            ->each(function (ChessBettingMarket $market) use ($board) {
                $nextState = $this->stateMachine->transition(
                    $market->status,
                    ChessBettingMarketStateMachine::ACTION_AUTO_AWAITING_RESULT
                );

                $market->update([
                    'status' => $nextState,
                    'updated_by' => null,
                    'meta' => $this->shadowMode->decorateMarketMeta((array) $market->meta, ChessBettingMarketStateMachine::ACTION_AUTO_AWAITING_RESULT),
                ]);

                $this->log(
                    $market->fresh(['board', 'event']),
                    null,
                    ChessBettingMarketStateMachine::ACTION_AUTO_AWAITING_RESULT,
                    ChessBettingMarketStateMachine::STATUS_CLOSED,
                    $nextState,
                    'official_board_result',
                    'Official board result became visible in the read model.'
                );
            });
    }

    private function transition(ChessBettingMarket $market, string $action, ?CmsUser $actor, array $context = []): ChessBettingMarket
    {
        return DB::transaction(function () use ($market, $action, $actor, $context) {
            $currentState = (string) $market->status;
            $nextState = $this->stateMachine->transition($currentState, $action);
            $now = CarbonImmutable::now();

            $attributes = [
                'status' => $nextState,
                'updated_by' => optional($actor)->id,
            ];

            if ($action === ChessBettingMarketStateMachine::ACTION_OPEN || $action === ChessBettingMarketStateMachine::ACTION_REOPEN) {
                $attributes['opened_at'] = $market->opened_at ?: $now;
            }

            if ($action === ChessBettingMarketStateMachine::ACTION_CLOSE) {
                $attributes['closed_at'] = $now;
            }

            if ($action === ChessBettingMarketStateMachine::ACTION_SETTLE) {
                $attributes['settled_at'] = $now;
                $attributes['settle_reason'] = $context['reason'] ?? null;
                $attributes['settle_note'] = $context['note'] ?? null;
                $attributes['result_selection_code'] = Arr::get($context, 'selection.code');
                $attributes['result_payload'] = $context['selection'] ?? null;
            }

            if ($action === ChessBettingMarketStateMachine::ACTION_VOID) {
                $attributes['voided_at'] = $now;
            }

            $attributes['meta'] = $this->shadowMode->decorateMarketMeta((array) $market->meta, $action);

            $market->update($attributes);

            $this->log(
                $market->fresh(['board', 'event']),
                $actor,
                $action,
                $currentState,
                $nextState,
                $context['reason'] ?? null,
                $context['note'] ?? null
            );

            return $market->fresh(['selections', 'board', 'event', 'actionLogs.actor']);
        });
    }

    private function guardBoardOpenEligibility(ChessLiveBoard $board): void
    {
        if (in_array($board->official_status, ['board_cancelled', 'board_replaced'], true)
            || Arr::get($board->meta, 'is_cancelled')
            || Arr::get($board->meta, 'is_replaced')) {
            throw ValidationException::withMessages([
                'board_id' => 'Cancelled or replaced boards cannot open new markets.',
            ]);
        }

        $criticalMissingFields = (array) Arr::get($board->meta, 'critical_missing_fields', []);
        if (strtolower((string) Arr::get($board->meta, 'source_integrity', 'source_confirmed')) === 'incomplete'
            && !empty($criticalMissingFields)) {
            throw ValidationException::withMessages([
                'board_id' => 'Critical source fields are missing: ' . implode(', ', $criticalMissingFields) . '.',
            ]);
        }
    }

    private function log(
        ChessBettingMarket $market,
        ?CmsUser $actor,
        string $actionType,
        ?string $fromState,
        ?string $toState,
        ?string $reasonType,
        ?string $note
    ): void {
        $note = $this->shadowMode->annotateNote($note);

        ChessBettingMarketActionLog::query()->create([
            'chess_betting_market_id' => $market->id,
            'chess_live_event_id' => $market->chess_live_event_id,
            'chess_live_board_id' => $market->chess_live_board_id,
            'actor_id' => optional($actor)->id,
            'action_type' => $actionType,
            'from_state' => $fromState,
            'to_state' => $toState,
            'reason_type' => $reasonType,
            'note' => $note,
            'source_ref' => $market->source_ref ?: optional($market->board)->source_ref,
            'source_snapshot' => [
                'board_status' => optional($market->board)->official_status,
                'board_result' => optional($market->board)->official_result,
                'event_status' => optional($market->event)->official_status,
                'shadow_mode' => $this->shadowMode->enabled(),
            ],
            'meta' => $this->shadowMode->decorateLogMeta([
                'market_title' => $market->title,
                'template_type' => $market->template_type,
                'template_variant' => $market->template_variant,
            ]),
            'created_at' => CarbonImmutable::now(),
        ]);
    }
}
