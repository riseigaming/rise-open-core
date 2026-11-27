<?php

namespace App\Betting\Settlement;

use App\Betting\Enums\BettingDecisionSource;
use App\Betting\Enums\BettingSettlementPrecedenceState;
use App\Betting\Enums\BettingSettlementResult;
use App\Betting\Enums\BettingSettlementStatus;
use App\Betting\Support\BettingActivityLogService;
use App\Models\BettingAcceptance;
use App\Models\BettingSettlement;
use Illuminate\Support\Str;

final class PendingSettlementRecorder
{
    public function __construct(private readonly BettingActivityLogService $activityLogService)
    {
    }

    public function record(BettingAcceptance $acceptance, array $attributes = []): BettingSettlement
    {
        $acceptance->loadMissing(['event', 'quoteVersion', 'quoteSelection']);

        $settlement = BettingSettlement::query()->firstOrCreate([
            'betting_acceptance_id' => $acceptance->id,
        ], [
            'settlement_ref' => $attributes['settlement_ref'] ?? (string) Str::uuid(),
            'reviewed_by_cms_user_id' => $attributes['reviewed_by_cms_user_id'] ?? null,
            'status' => $attributes['status'] ?? BettingSettlementStatus::PENDING,
            'decision_source' => $attributes['decision_source'] ?? BettingDecisionSource::SYSTEM,
            'precedence_state' => $attributes['precedence_state'] ?? BettingSettlementPrecedenceState::SYSTEM_PENDING,
            'manual_override_active' => $attributes['manual_override_active'] ?? false,
            'result_code' => $attributes['result_code'] ?? BettingSettlementResult::PENDING,
            'return_amount' => $attributes['return_amount'] ?? 0,
            'profit_amount' => $attributes['profit_amount'] ?? 0,
            'reason' => $attributes['reason'] ?? null,
            'review_notes' => $attributes['review_notes'] ?? null,
            'context' => array_merge([
                'quote_version_id' => $acceptance->betting_quote_version_id,
                'quote_selection_id' => $acceptance->betting_quote_selection_id,
            ], $attributes['context'] ?? []),
            'override_meta' => $attributes['override_meta'] ?? null,
        ]);

        if ($settlement->wasRecentlyCreated) {
            $this->activityLogService->logSettlement(
                $settlement,
                'settlement.pending',
                'Pending settlement opened for acceptance [' . $acceptance->acceptance_ref . '].',
                [
                    'status' => $settlement->status,
                    'result_code' => $settlement->result_code,
                    'quote_version_id' => $acceptance->betting_quote_version_id,
                ]
            );
        }

        return $settlement;
    }
}
