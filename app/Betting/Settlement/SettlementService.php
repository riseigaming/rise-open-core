<?php

namespace App\Betting\Settlement;

use App\Betting\Enums\BettingAcceptanceStatus;
use App\Betting\Enums\BettingDecisionSource;
use App\Betting\Enums\BettingSettlementPrecedenceState;
use App\Betting\Enums\BettingSettlementResult;
use App\Betting\Enums\BettingSettlementStatus;
use App\Betting\Enums\BettingSettlementSourceOutcome;
use App\Betting\Exceptions\SettlementAlreadyFinalizedException;
use App\Betting\Support\BettingActivityLogService;
use App\Models\BettingAcceptance;
use App\Models\BettingSettlement;
use App\Models\BettingSettlementSourceResult;
use App\Models\CmsUser;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class SettlementService
{
    private $activityLogService;
    private $walletService;
    private $manualReadinessService;

    public function __construct(
        ?BettingActivityLogService $activityLogService = null,
        ?SettlementWalletService $walletService = null,
        ?PendingBettingSettlementReadService $manualReadinessService = null
    )
    {
        $this->activityLogService = $activityLogService ?: app(BettingActivityLogService::class);
        $this->walletService = $walletService ?: app(SettlementWalletService::class);
        $this->manualReadinessService = $manualReadinessService ?: app(PendingBettingSettlementReadService::class);
    }

    public function createPendingSettlement(BettingAcceptance $acceptance, array $attributes = []): BettingSettlement
    {
        return (new PendingSettlementRecorder($this->activityLogService))->record($acceptance, $attributes);
    }

    public function reconcileSourceResult(BettingSettlement $settlement, array $attributes, ?CmsUser $actor = null): array
    {
        app(\App\Services\Finance\PrelaunchFinanceWriteGate::class)->assertWritable();
        return DB::transaction(function () use ($settlement, $attributes, $actor) {
            /** @var BettingSettlement|null $lockedSettlement */
            $lockedSettlement = BettingSettlement::query()
                ->whereKey($settlement->id)
                ->lockForUpdate()
                ->first();

            if (!$lockedSettlement) {
                throw new RuntimeException('Settlement was not found.');
            }

            $lockedSettlement->loadMissing([
                'acceptance.event',
                'acceptance.quoteVersion',
                'currentSourceResult',
                'sourceResults',
            ]);

            $payload = $this->normalizeSourceResultPayload($lockedSettlement, $attributes);

            $existingByChecksum = BettingSettlementSourceResult::query()
                ->where('betting_settlement_id', $lockedSettlement->id)
                ->where('source_checksum', $payload['source_checksum'])
                ->first();

            if ($existingByChecksum) {
                $this->activityLogService->logSettlement(
                    $lockedSettlement,
                    'settlement.source_replayed',
                    'Duplicate source settlement replay ignored for settlement [' . $lockedSettlement->settlement_ref . '].',
                    [
                        'source_result_id' => $existingByChecksum->id,
                        'source_result_key' => $existingByChecksum->source_result_key,
                        'source_checksum' => $existingByChecksum->source_checksum,
                        'candidate_result_code' => $existingByChecksum->candidate_result_code,
                    ],
                    $actor
                );

                return [
                    'settlement' => $lockedSettlement->fresh(['acceptance.event', 'reviewedBy', 'currentSourceResult', 'sourceResults']),
                    'source_result' => $existingByChecksum->fresh(),
                    'duplicate' => true,
                    'applied' => false,
                    'review_required' => false,
                ];
            }

            $previousByResultKey = null;
            if (!empty($payload['source_result_key'])) {
                $previousByResultKey = BettingSettlementSourceResult::query()
                    ->where('betting_settlement_id', $lockedSettlement->id)
                    ->where('source_result_key', $payload['source_result_key'])
                    ->latest('id')
                    ->first();
            }

            $governance = $this->governIncomingSourceResult($lockedSettlement, $payload, $previousByResultKey);

            if ($governance['action'] === 'semantic_noop') {
                $this->activityLogService->logSettlement(
                    $lockedSettlement,
                    'settlement.source_semantic_replayed',
                    'Source settlement payload repeated the same semantic outcome and was collapsed without a new history row.',
                    [
                        'source_result_id' => $previousByResultKey->id,
                        'source_result_key' => $previousByResultKey->source_result_key,
                        'semantic_signature' => $payload['semantic_signature'],
                        'reason' => $governance['reason'],
                    ],
                    $actor
                );

                return [
                    'settlement' => $lockedSettlement->fresh(['acceptance.event', 'reviewedBy', 'currentSourceResult', 'sourceResults', 'walletEffects']),
                    'source_result' => $previousByResultKey->fresh(),
                    'duplicate' => true,
                    'applied' => false,
                    'review_required' => false,
                    'reconciliation_outcome' => 'semantic_noop',
                    'reconciliation_reason' => $governance['reason'],
                    'wallet' => [
                        'enabled' => false,
                        'state' => 'semantic_noop',
                        'reversed' => [],
                        'applied' => null,
                    ],
                ];
            }

            if ($governance['action'] === 'stale_ignore') {
                $this->activityLogService->logSettlement(
                    $lockedSettlement,
                    'settlement.source_stale_ignored',
                    'Older settlement correction arrived after a newer source truth and was ignored without mutating internal state.',
                    [
                        'source_result_id' => $previousByResultKey?->id,
                        'source_result_key' => $payload['source_result_key'],
                        'reason' => $governance['reason'],
                        'incoming_ordering' => $payload['ordering'],
                        'current_ordering' => $this->orderingForStoredSourceResult($previousByResultKey),
                    ],
                    $actor,
                    'warning'
                );

                return [
                    'settlement' => $lockedSettlement->fresh(['acceptance.event', 'reviewedBy', 'currentSourceResult', 'sourceResults', 'walletEffects']),
                    'source_result' => $previousByResultKey?->fresh(),
                    'duplicate' => false,
                    'applied' => false,
                    'review_required' => false,
                    'reconciliation_outcome' => 'stale_ignored',
                    'reconciliation_reason' => $governance['reason'],
                    'wallet' => [
                        'enabled' => false,
                        'state' => 'stale_ignored',
                        'reversed' => [],
                        'applied' => null,
                    ],
                ];
            }

            if ($governance['action'] === 'review_required') {
                $sourceResult = BettingSettlementSourceResult::query()->create([
                    'betting_settlement_id' => $lockedSettlement->id,
                    'replaces_source_result_id' => null,
                    'source_adapter_key' => $payload['source_adapter_key'],
                    'ingest_origin' => $payload['ingest_origin'] ?? 'provider_ingest',
                    'source_result_key' => $payload['source_result_key'],
                    'source_event_ref' => $payload['source_event_ref'],
                    'source_market_ref' => $payload['source_market_ref'],
                    'source_outcome_ref' => $payload['source_outcome_ref'],
                    'candidate_result_code' => $payload['candidate_result_code'],
                    'candidate_status' => $payload['candidate_status'],
                    'normalization_status' => $payload['normalization_status'] ?? 'normalized',
                    'normalization_reason' => $payload['normalization_reason'] ?? null,
                    'precedence_outcome' => BettingSettlementSourceOutcome::REVIEW_REQUIRED,
                    'is_correction' => true,
                    'received_at' => $payload['received_at'],
                    'source_checksum' => $payload['source_checksum'],
                    'source_payload' => $payload['source_payload'],
                    'meta' => array_merge($payload['meta'], [
                        'ordering' => $payload['ordering'],
                        'semantic_signature' => $payload['semantic_signature'],
                        'governance_reason' => $governance['reason'],
                    ]),
                    'processed_at' => now(),
                ]);

                $lockedSettlement->update([
                    'status' => BettingSettlementStatus::REVIEW,
                    'result_code' => BettingSettlementResult::PENDING,
                    'return_amount' => 0,
                    'profit_amount' => 0,
                    'settled_at' => null,
                    'current_source_result_id' => $sourceResult->id,
                    'precedence_state' => BettingSettlementPrecedenceState::REVIEW_REQUIRED,
                    'source_reconciled_at' => now(),
                    'source_conflict_reason' => $governance['reason'],
                ]);

                $wallet = $this->walletService->reconcile($lockedSettlement->fresh(['acceptance.user', 'acceptance.rootUser', 'walletEffects.transaction']), $sourceResult, $governance['reason'], $actor);

                $this->activityLogService->logSettlement(
                    $lockedSettlement,
                    'settlement.source_ordering_review_required',
                    'Conflicting same-key settlement correction lacked trustworthy ordering and was routed to review.',
                    [
                        'source_result_id' => $sourceResult->id,
                        'source_result_key' => $sourceResult->source_result_key,
                        'reason' => $governance['reason'],
                        'wallet_state' => $wallet['state'] ?? null,
                    ],
                    $actor,
                    'warning'
                );

                return [
                    'settlement' => $lockedSettlement->fresh(['acceptance.event', 'reviewedBy', 'currentSourceResult', 'sourceResults', 'walletEffects']),
                    'source_result' => $sourceResult->fresh(),
                    'duplicate' => false,
                    'applied' => false,
                    'review_required' => true,
                    'reconciliation_outcome' => 'review_required',
                    'reconciliation_reason' => $governance['reason'],
                    'wallet' => $wallet,
                ];
            }

            if ($previousByResultKey) {
                $previousByResultKey->update([
                    'precedence_outcome' => BettingSettlementSourceOutcome::SUPERSEDED,
                    'superseded_at' => now(),
                ]);
            }

            try {
                $sourceResult = BettingSettlementSourceResult::query()->create([
                    'betting_settlement_id' => $lockedSettlement->id,
                    'replaces_source_result_id' => optional($previousByResultKey)->id,
                    'source_adapter_key' => $payload['source_adapter_key'],
                    'ingest_origin' => $payload['ingest_origin'] ?? 'provider_ingest',
                    'source_result_key' => $payload['source_result_key'],
                    'source_event_ref' => $payload['source_event_ref'],
                    'source_market_ref' => $payload['source_market_ref'],
                    'source_outcome_ref' => $payload['source_outcome_ref'],
                    'candidate_result_code' => $payload['candidate_result_code'],
                    'candidate_status' => $payload['candidate_status'],
                    'normalization_status' => $payload['normalization_status'] ?? 'normalized',
                    'normalization_reason' => $payload['normalization_reason'] ?? null,
                    'precedence_outcome' => BettingSettlementSourceOutcome::RECORDED,
                    'is_correction' => $payload['is_correction'],
                    'received_at' => $payload['received_at'],
                    'source_checksum' => $payload['source_checksum'],
                    'source_payload' => $payload['source_payload'],
                    'meta' => array_merge($payload['meta'], [
                        'ordering' => $payload['ordering'],
                        'semantic_signature' => $payload['semantic_signature'],
                    ]),
                ]);
            } catch (QueryException $exception) {
                $existingByChecksum = BettingSettlementSourceResult::query()
                    ->where('betting_settlement_id', $lockedSettlement->id)
                    ->where('source_checksum', $payload['source_checksum'])
                    ->first();

                if (!$existingByChecksum) {
                    throw $exception;
                }

                $this->activityLogService->logSettlement(
                    $lockedSettlement,
                    'settlement.source_replayed',
                    'Concurrent duplicate source settlement replay ignored for settlement [' . $lockedSettlement->settlement_ref . '].',
                    [
                        'source_result_id' => $existingByChecksum->id,
                        'source_result_key' => $existingByChecksum->source_result_key,
                        'source_checksum' => $existingByChecksum->source_checksum,
                        'candidate_result_code' => $existingByChecksum->candidate_result_code,
                    ],
                    $actor
                );

                return [
                    'settlement' => $lockedSettlement->fresh(['acceptance.event', 'reviewedBy', 'currentSourceResult', 'sourceResults', 'walletEffects']),
                    'source_result' => $existingByChecksum->fresh(),
                    'duplicate' => true,
                    'applied' => false,
                    'review_required' => false,
                    'reconciliation_outcome' => 'duplicate',
                    'reconciliation_reason' => 'concurrent_duplicate_checksum',
                    'wallet' => [
                        'enabled' => false,
                        'state' => 'duplicate',
                        'reversed' => [],
                        'applied' => null,
                    ],
                ];
            }

            $candidateSettlementState = $this->settlementStateForResult(
                $lockedSettlement->acceptance,
                $sourceResult->candidate_result_code,
                $sourceResult->received_at ?: now()
            );

            if ((string) $sourceResult->normalization_status !== 'normalized') {
                $lockedSettlement->update([
                    'status' => BettingSettlementStatus::REVIEW,
                    'result_code' => BettingSettlementResult::PENDING,
                    'settled_at' => null,
                    'current_source_result_id' => $sourceResult->id,
                    'precedence_state' => BettingSettlementPrecedenceState::REVIEW_REQUIRED,
                    'decision_source' => $lockedSettlement->manual_override_active ? BettingDecisionSource::MANUAL : BettingDecisionSource::SOURCE,
                    'source_reconciled_at' => now(),
                    'source_conflict_reason' => $sourceResult->normalization_reason ?: 'unresolved_source_mapping',
                ]);

                $sourceResult->update([
                    'precedence_outcome' => BettingSettlementSourceOutcome::REVIEW_REQUIRED,
                    'processed_at' => now(),
                ]);

                $this->activityLogService->logSettlement(
                    $lockedSettlement,
                    'settlement.source_unresolved',
                    'Source settlement record could not be normalized into a resolved candidate outcome.',
                    [
                        'source_result_id' => $sourceResult->id,
                        'source_result_key' => $sourceResult->source_result_key,
                        'normalization_status' => $sourceResult->normalization_status,
                        'normalization_reason' => $sourceResult->normalization_reason,
                        'ingest_origin' => $sourceResult->ingest_origin,
                    ],
                    $actor,
                    'warning'
                );

                $wallet = $this->walletService->reconcile($lockedSettlement->fresh(['acceptance.user', 'acceptance.rootUser', 'walletEffects.transaction']), $sourceResult, 'source_unresolved', $actor);

                return [
                    'settlement' => $lockedSettlement->fresh(['acceptance.event', 'reviewedBy', 'currentSourceResult', 'sourceResults', 'walletEffects']),
                    'source_result' => $sourceResult->fresh(['replacedSourceResult']),
                    'duplicate' => false,
                    'applied' => false,
                    'review_required' => true,
                    'reconciliation_outcome' => 'review_required',
                    'reconciliation_reason' => 'source_unresolved',
                    'wallet' => $wallet,
                ];
            }

            $isConflict = $this->manualOverrideConflictDetected($lockedSettlement, $sourceResult, $candidateSettlementState);
            $candidateMatchesCurrent = $this->sourceCandidateMatchesCurrentSettlement($lockedSettlement, $sourceResult, $candidateSettlementState);

            if ($isConflict) {
                $lockedSettlement->update([
                    'status' => BettingSettlementStatus::REVIEW,
                    'result_code' => BettingSettlementResult::PENDING,
                    'return_amount' => 0,
                    'profit_amount' => 0,
                    'settled_at' => null,
                    'current_source_result_id' => $sourceResult->id,
                    'precedence_state' => BettingSettlementPrecedenceState::REVIEW_REQUIRED,
                    'source_reconciled_at' => now(),
                    'source_conflict_reason' => 'source_correction_after_manual_override',
                    'override_meta' => array_merge($lockedSettlement->override_meta ?? [], [
                        'source_conflict' => [
                            'triggered_at' => now()->toIso8601String(),
                            'candidate_result_code' => $sourceResult->candidate_result_code,
                            'candidate_status' => $sourceResult->candidate_status,
                            'source_result_id' => $sourceResult->id,
                            'previous_internal_state' => [
                                'status' => $lockedSettlement->getOriginal('status'),
                                'result_code' => $lockedSettlement->getOriginal('result_code'),
                                'decision_source' => $lockedSettlement->getOriginal('decision_source'),
                                'reviewed_at' => $lockedSettlement->getOriginal('reviewed_at'),
                            ],
                        ],
                    ]),
                ]);

                $sourceResult->update([
                    'precedence_outcome' => BettingSettlementSourceOutcome::REVIEW_REQUIRED,
                    'processed_at' => now(),
                ]);

                $this->activityLogService->logSettlement(
                    $lockedSettlement,
                    'settlement.source_review_required',
                    'Source settlement correction recorded, but manual override keeps this settlement in review.',
                    [
                        'source_result_id' => $sourceResult->id,
                        'source_result_key' => $sourceResult->source_result_key,
                        'candidate_result_code' => $sourceResult->candidate_result_code,
                        'precedence_state' => BettingSettlementPrecedenceState::REVIEW_REQUIRED,
                    ],
                    $actor,
                    'warning'
                );

                $wallet = $this->walletService->reconcile($lockedSettlement->fresh(['acceptance.user', 'acceptance.rootUser', 'walletEffects.transaction']), $sourceResult, 'source_correction_after_manual_override', $actor);

                return [
                    'settlement' => $lockedSettlement->fresh(['acceptance.event', 'reviewedBy', 'currentSourceResult', 'sourceResults', 'walletEffects']),
                    'source_result' => $sourceResult->fresh(['replacedSourceResult']),
                    'duplicate' => false,
                    'applied' => false,
                    'review_required' => true,
                    'reconciliation_outcome' => 'review_required',
                    'reconciliation_reason' => 'source_correction_after_manual_override',
                    'wallet' => $wallet,
                ];
            }

            if (!$lockedSettlement->manual_override_active || !$candidateMatchesCurrent) {
                $this->applySettlementState(
                    $lockedSettlement,
                    $candidateSettlementState,
                    [
                        'decision_source' => BettingDecisionSource::SOURCE,
                        'precedence_state' => BettingSettlementPrecedenceState::SOURCE_APPLIED,
                        'manual_override_active' => false,
                        'result_code' => $sourceResult->candidate_result_code,
                        'reviewed_by_cms_user_id' => null,
                        'reviewed_at' => now(),
                        'source_reconciled_at' => now(),
                        'current_source_result_id' => $sourceResult->id,
                        'reason' => 'Source settlement result reconciled into internal settlement state.',
                        'review_notes' => data_get($payload['meta'], 'notes'),
                        'source_conflict_reason' => null,
                        'override_meta' => array_merge($lockedSettlement->override_meta ?? [], [
                            'last_source_reconciliation' => [
                                'source_result_id' => $sourceResult->id,
                                'source_result_key' => $sourceResult->source_result_key,
                                'candidate_result_code' => $sourceResult->candidate_result_code,
                                'received_at' => optional($sourceResult->received_at)->toIso8601String(),
                            ],
                        ]),
                    ]
                );

                $sourceResult->update([
                    'precedence_outcome' => BettingSettlementSourceOutcome::APPLIED,
                    'processed_at' => now(),
                ]);

                $this->activityLogService->logSettlement(
                    $lockedSettlement,
                    'settlement.source_applied',
                    'Source settlement result applied to internal settlement state.',
                    [
                        'source_result_id' => $sourceResult->id,
                        'source_result_key' => $sourceResult->source_result_key,
                        'candidate_result_code' => $sourceResult->candidate_result_code,
                        'precedence_state' => BettingSettlementPrecedenceState::SOURCE_APPLIED,
                    ],
                    $actor
                );

                $wallet = $this->walletService->reconcile($lockedSettlement->fresh(['acceptance.user', 'acceptance.rootUser', 'walletEffects.transaction']), $sourceResult, 'source_applied', $actor);

                return [
                    'settlement' => $lockedSettlement->fresh(['acceptance.event', 'reviewedBy', 'currentSourceResult', 'sourceResults', 'walletEffects']),
                    'source_result' => $sourceResult->fresh(['replacedSourceResult']),
                    'duplicate' => false,
                    'applied' => true,
                    'review_required' => false,
                    'reconciliation_outcome' => 'applied',
                    'reconciliation_reason' => $governance['reason'],
                    'wallet' => $wallet,
                ];
            }

            $lockedSettlement->update([
                'current_source_result_id' => $sourceResult->id,
                'source_reconciled_at' => now(),
                'source_conflict_reason' => null,
            ]);

            $sourceResult->update([
                'precedence_outcome' => BettingSettlementSourceOutcome::RECORDED,
                'processed_at' => now(),
            ]);

            $this->activityLogService->logSettlement(
                $lockedSettlement,
                'settlement.source_recorded',
                'Source settlement result recorded without changing the current manual settlement state.',
                [
                    'source_result_id' => $sourceResult->id,
                    'source_result_key' => $sourceResult->source_result_key,
                    'candidate_result_code' => $sourceResult->candidate_result_code,
                    'precedence_state' => (string) $lockedSettlement->precedence_state,
                ],
                $actor
            );

            return [
                'settlement' => $lockedSettlement->fresh(['acceptance.event', 'reviewedBy', 'currentSourceResult', 'sourceResults', 'walletEffects']),
                'source_result' => $sourceResult->fresh(['replacedSourceResult']),
                'duplicate' => false,
                'applied' => false,
                'review_required' => false,
                'reconciliation_outcome' => 'recorded_only',
                'reconciliation_reason' => 'manual_state_already_matches',
                'wallet' => [
                    'enabled' => false,
                    'state' => 'manual_state_unchanged',
                    'reversed' => [],
                    'applied' => null,
                ],
            ];
        }, 3);
    }

    public function review(BettingSettlement $settlement, string $resultCode, ?string $reason = null, ?CmsUser $actor = null): BettingSettlement
    {
        app(\App\Services\Finance\PrelaunchFinanceWriteGate::class)->assertWritable();
        $resultCode = strtolower($resultCode);

        return DB::transaction(function () use ($settlement, $resultCode, $reason, $actor) {
            /** @var BettingSettlement|null $lockedSettlement */
            $lockedSettlement = BettingSettlement::query()
                ->whereKey($settlement->id)
                ->lockForUpdate()
                ->first();

            if (!$lockedSettlement) {
                throw new RuntimeException('Settlement was not found.');
            }

            $lockedSettlement->loadMissing(['acceptance.event', 'acceptance.quoteVersion', 'currentSourceResult']);
            $this->assertSettlementReviewable($lockedSettlement);
            $this->assertManualReviewReadiness($lockedSettlement, $resultCode, $reason);

            $candidateSettlementState = $this->settlementStateForResult($lockedSettlement->acceptance, $resultCode, now());

            $manualOverrideMeta = [
                'manual_review' => true,
                'quote_version_id' => $lockedSettlement->acceptance->betting_quote_version_id,
                'quote_selection_id' => $lockedSettlement->acceptance->betting_quote_selection_id,
            ];

            if ($lockedSettlement->currentSourceResult) {
                $manualOverrideMeta['source_snapshot'] = [
                    'source_result_id' => $lockedSettlement->currentSourceResult->id,
                    'candidate_result_code' => $lockedSettlement->currentSourceResult->candidate_result_code,
                    'precedence_outcome' => $lockedSettlement->currentSourceResult->precedence_outcome,
                ];
            }

            $this->applySettlementState(
                $lockedSettlement,
                $candidateSettlementState,
                [
                    'decision_source' => BettingDecisionSource::MANUAL,
                    'precedence_state' => BettingSettlementPrecedenceState::MANUAL_OVERRIDE,
                    'manual_override_active' => true,
                    'result_code' => $resultCode,
                    'reviewed_by_cms_user_id' => optional($actor)->id,
                    'reviewed_at' => now(),
                    'reason' => $reason,
                    'review_notes' => $reason,
                    'source_conflict_reason' => null,
                    'override_meta' => array_merge($lockedSettlement->override_meta ?? [], $manualOverrideMeta),
                ]
            );

            $this->activityLogService->logSettlement(
                $lockedSettlement,
                'settlement.' . $resultCode,
                'Settlement reviewed as [' . $resultCode . '] for acceptance [' . $lockedSettlement->acceptance->acceptance_ref . '].',
                [
                    'status' => $candidateSettlementState['status'],
                    'result_code' => $resultCode,
                    'return_amount' => $candidateSettlementState['return_amount'],
                    'profit_amount' => $candidateSettlementState['profit_amount'],
                    'quote_version_id' => $lockedSettlement->acceptance->betting_quote_version_id,
                    'precedence_state' => BettingSettlementPrecedenceState::MANUAL_OVERRIDE,
                ],
                $actor
            );

            $this->walletService->reconcile($lockedSettlement->fresh(['acceptance.user', 'acceptance.rootUser', 'walletEffects.transaction', 'currentSourceResult']), $lockedSettlement->currentSourceResult, 'manual_review', $actor);

            return $lockedSettlement->fresh([
                'acceptance.event',
                'acceptance.quoteVersion',
                'reviewedBy',
                'walletEffects',
            ]);
        }, 3);
    }

    private function assertSettlementReviewable(BettingSettlement $settlement): void
    {
        if (in_array((string) $settlement->status, [
            BettingSettlementStatus::FINAL,
            BettingSettlementStatus::VOID,
            BettingSettlementStatus::CANCELLED,
        ], true) && (string) $settlement->decision_source !== BettingDecisionSource::SOURCE) {
            throw new SettlementAlreadyFinalizedException((string) $settlement->status, (string) $settlement->decision_source);
        }
    }

    private function assertManualReviewReadiness(BettingSettlement $settlement, string $resultCode, ?string $reason): void
    {
        $acceptance = $settlement->acceptance;
        if (!$acceptance instanceof BettingAcceptance) {
            throw new RuntimeException('Settlement acceptance could not be resolved.');
        }

        $acceptance->setRelation('settlement', $settlement);
        $readiness = $this->manualReadinessService->readiness($acceptance);

        // Cancellation is an operator reversal, not a claim about the event
        // outcome. Permit it before the result window only when the operator
        // supplies an auditable reason; WON/LOST/VOID readiness stays strict.
        if ($resultCode === BettingSettlementResult::CANCELLED && trim((string) $reason) !== '') {
            return;
        }

        if (!(bool) ($readiness['can_settle'] ?? false)) {
            throw new RuntimeException('This settlement is not ready for review: ' . (string) ($readiness['reason'] ?? 'Unknown readiness state.'));
        }
    }

    private function normalizeSourceResultPayload(BettingSettlement $settlement, array $attributes): array
    {
        $candidateResultCode = strtolower((string) ($attributes['candidate_result_code'] ?? BettingSettlementResult::PENDING));
        if (!in_array($candidateResultCode, BettingSettlementResult::values(), true)) {
            throw new \InvalidArgumentException('Unsupported source settlement result: ' . $candidateResultCode);
        }

        $candidateState = $this->settlementStateForResult($settlement->acceptance, $candidateResultCode, now());

        $sourcePayload = $attributes['source_payload'] ?? [];
        if (!is_array($sourcePayload)) {
            throw new \InvalidArgumentException('Source settlement payload must be an array.');
        }

        $signature = [
            'source_adapter_key' => $attributes['source_adapter_key'] ?? 'the_odds_api',
            'ingest_origin' => $attributes['ingest_origin'] ?? 'provider_ingest',
            'source_result_key' => $attributes['source_result_key'] ?? null,
            'source_event_ref' => $attributes['source_event_ref'] ?? null,
            'source_market_ref' => $attributes['source_market_ref'] ?? null,
            'source_outcome_ref' => $attributes['source_outcome_ref'] ?? null,
            'candidate_result_code' => $candidateResultCode,
            'source_payload' => $sourcePayload,
        ];

        $previousByResultKey = null;
        if (!empty($signature['source_result_key'])) {
            $previousByResultKey = BettingSettlementSourceResult::query()
                ->where('betting_settlement_id', $settlement->id)
                ->where('source_result_key', $signature['source_result_key'])
                ->latest('id')
                ->first();
        }

        return [
            'source_adapter_key' => (string) $signature['source_adapter_key'],
            'ingest_origin' => (string) $signature['ingest_origin'],
            'source_result_key' => $signature['source_result_key'] ? (string) $signature['source_result_key'] : null,
            'source_event_ref' => !empty($signature['source_event_ref']) ? (string) $signature['source_event_ref'] : null,
            'source_market_ref' => !empty($signature['source_market_ref']) ? (string) $signature['source_market_ref'] : null,
            'source_outcome_ref' => !empty($signature['source_outcome_ref']) ? (string) $signature['source_outcome_ref'] : null,
            'candidate_result_code' => $candidateResultCode,
            'candidate_status' => $candidateState['status'],
            'normalization_status' => (string) ($attributes['normalization_status'] ?? 'normalized'),
            'normalization_reason' => !empty($attributes['normalization_reason']) ? (string) $attributes['normalization_reason'] : null,
            'source_payload' => $sourcePayload,
            'received_at' => !empty($attributes['received_at']) ? $attributes['received_at'] : now(),
            'source_checksum' => hash('sha256', json_encode($signature, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
            'is_correction' => (bool) $previousByResultKey,
            'ordering' => $this->extractOrderingMetadata($sourcePayload, $attributes['received_at'] ?? now()),
            'semantic_signature' => $this->semanticSignatureForPayload([
                'source_adapter_key' => (string) $signature['source_adapter_key'],
                'source_event_ref' => !empty($signature['source_event_ref']) ? (string) $signature['source_event_ref'] : null,
                'source_market_ref' => !empty($signature['source_market_ref']) ? (string) $signature['source_market_ref'] : null,
                'source_outcome_ref' => !empty($signature['source_outcome_ref']) ? (string) $signature['source_outcome_ref'] : null,
                'candidate_result_code' => $candidateResultCode,
                'candidate_status' => $candidateState['status'],
                'normalization_status' => (string) ($attributes['normalization_status'] ?? 'normalized'),
                'normalization_reason' => !empty($attributes['normalization_reason']) ? (string) $attributes['normalization_reason'] : null,
            ]),
            'meta' => array_filter(array_merge($attributes['meta'] ?? [], [
                'notes' => $attributes['notes'] ?? null,
            ]), function ($value) {
                return $value !== null && $value !== '';
            }),
        ];
    }

    private function sourceCandidateMatchesCurrentSettlement(BettingSettlement $settlement, BettingSettlementSourceResult $sourceResult, array $candidateState): bool
    {
        return (string) $settlement->status === (string) $candidateState['status']
            && (string) $settlement->result_code === (string) $sourceResult->candidate_result_code;
    }

    private function manualOverrideConflictDetected(BettingSettlement $settlement, BettingSettlementSourceResult $sourceResult, array $candidateState): bool
    {
        if (!$settlement->manual_override_active && (string) $settlement->decision_source !== BettingDecisionSource::MANUAL) {
            return false;
        }

        return !$this->sourceCandidateMatchesCurrentSettlement($settlement, $sourceResult, $candidateState);
    }

    private function settlementStateForResult(BettingAcceptance $acceptance, string $resultCode, $timestamp): array
    {
        return match ($resultCode) {
            BettingSettlementResult::WON => [
                'status' => BettingSettlementStatus::FINAL,
                'return_amount' => (string) BigDecimal::of((string)$acceptance->stake_amount)->multipliedBy((string)$acceptance->accepted_price)->toScale(6,RoundingMode::HALF_UP),
                'profit_amount' => (string) BigDecimal::of((string)$acceptance->stake_amount)->multipliedBy((string)$acceptance->accepted_price)->minus((string)$acceptance->stake_amount)->toScale(6,RoundingMode::HALF_UP),
                'acceptance_status' => BettingAcceptanceStatus::SETTLED,
                'settled_at' => $timestamp,
            ],
            BettingSettlementResult::LOST => [
                'status' => BettingSettlementStatus::FINAL,
                'return_amount' => 0,
                'profit_amount' => (string) BigDecimal::of((string)$acceptance->stake_amount)->negated()->toScale(6,RoundingMode::HALF_UP),
                'acceptance_status' => BettingAcceptanceStatus::SETTLED,
                'settled_at' => $timestamp,
            ],
            BettingSettlementResult::VOID,
            BettingSettlementResult::REFUNDED => [
                'status' => BettingSettlementStatus::VOID,
                'return_amount' => (string) $acceptance->stake_amount,
                'profit_amount' => 0,
                'acceptance_status' => BettingAcceptanceStatus::SETTLED,
                'settled_at' => $timestamp,
            ],
            BettingSettlementResult::CANCELLED => [
                'status' => BettingSettlementStatus::CANCELLED,
                'return_amount' => (string) $acceptance->stake_amount,
                'profit_amount' => 0,
                'acceptance_status' => BettingAcceptanceStatus::SETTLED,
                'settled_at' => $timestamp,
            ],
            BettingSettlementResult::POSTPONED => [
                'status' => BettingSettlementStatus::POSTPONED,
                'return_amount' => 0,
                'profit_amount' => 0,
                'acceptance_status' => BettingAcceptanceStatus::ACCEPTED,
                'settled_at' => null,
            ],
            BettingSettlementResult::PENDING => [
                'status' => BettingSettlementStatus::REVIEW,
                'return_amount' => 0,
                'profit_amount' => 0,
                'acceptance_status' => $acceptance->status,
                'settled_at' => null,
            ],
            default => throw new \InvalidArgumentException('Unsupported settlement result: ' . $resultCode),
        };
    }

    private function applySettlementState(BettingSettlement $settlement, array $state, array $attributes = []): void
    {
        $settlement->update([
            'status' => $state['status'],
            'decision_source' => $attributes['decision_source'] ?? $settlement->decision_source,
            'precedence_state' => $attributes['precedence_state'] ?? $settlement->precedence_state,
            'manual_override_active' => $attributes['manual_override_active'] ?? $settlement->manual_override_active,
            'result_code' => $attributes['result_code'] ?? $this->resolveResultCodeForState($state),
            'return_amount' => $state['return_amount'],
            'profit_amount' => $state['profit_amount'],
            'settled_at' => $state['settled_at'],
            'reviewed_by_cms_user_id' => $attributes['reviewed_by_cms_user_id'] ?? $settlement->reviewed_by_cms_user_id,
            'reviewed_at' => $attributes['reviewed_at'] ?? $settlement->reviewed_at,
            'source_reconciled_at' => $attributes['source_reconciled_at'] ?? $settlement->source_reconciled_at,
            'reason' => array_key_exists('reason', $attributes) ? $attributes['reason'] : $settlement->reason,
            'review_notes' => array_key_exists('review_notes', $attributes) ? $attributes['review_notes'] : $settlement->review_notes,
            'source_conflict_reason' => array_key_exists('source_conflict_reason', $attributes) ? $attributes['source_conflict_reason'] : $settlement->source_conflict_reason,
            'current_source_result_id' => $attributes['current_source_result_id'] ?? $settlement->current_source_result_id,
            'override_meta' => $attributes['override_meta'] ?? $settlement->override_meta,
        ]);

        $settlement->acceptance->update([
            'status' => $state['acceptance_status'],
            'last_transition_at' => now(),
            'decision_reason' => array_key_exists('reason', $attributes)
                ? ($attributes['reason'] ?: $settlement->acceptance->decision_reason)
                : $settlement->acceptance->decision_reason,
        ]);
    }

    private function resolveResultCodeForState(array $state): string
    {
        return match ($state['status']) {
            BettingSettlementStatus::FINAL => $state['profit_amount'] > 0 ? BettingSettlementResult::WON : BettingSettlementResult::LOST,
            BettingSettlementStatus::VOID => BettingSettlementResult::VOID,
            BettingSettlementStatus::CANCELLED => BettingSettlementResult::CANCELLED,
            BettingSettlementStatus::POSTPONED => BettingSettlementResult::POSTPONED,
            BettingSettlementStatus::REVIEW => BettingSettlementResult::PENDING,
            default => BettingSettlementResult::PENDING,
        };
    }

    private function governIncomingSourceResult(BettingSettlement $settlement, array $payload, ?BettingSettlementSourceResult $previousByResultKey): array
    {
        if (!$previousByResultKey) {
            return ['action' => 'apply', 'reason' => 'first_source_result_for_key'];
        }

        $previousSemanticSignature = $this->semanticSignatureForStoredSourceResult($previousByResultKey);
        $currentSettlementMatchesPreviousSource = (string) $settlement->result_code === (string) $previousByResultKey->candidate_result_code
            && (string) $settlement->status === (string) $previousByResultKey->candidate_status
            && !$settlement->manual_override_active;

        if ($payload['semantic_signature'] === $previousSemanticSignature && $currentSettlementMatchesPreviousSource) {
            return ['action' => 'semantic_noop', 'reason' => 'same_semantic_identity'];
        }

        $incomingOrdering = $payload['ordering'];
        $currentOrdering = $this->orderingForStoredSourceResult($previousByResultKey);

        if ($incomingOrdering['provider_version'] !== null || $currentOrdering['provider_version'] !== null) {
            if ($incomingOrdering['provider_version'] !== null && $currentOrdering['provider_version'] === null) {
                return ['action' => 'apply', 'reason' => 'incoming_has_first_provider_version'];
            }

            if ($incomingOrdering['provider_version'] === null && $currentOrdering['provider_version'] !== null) {
                return ['action' => 'review_required', 'reason' => 'missing_provider_version_for_conflicting_same_key_correction'];
            }

            if ((int) $incomingOrdering['provider_version'] < (int) $currentOrdering['provider_version']) {
                return ['action' => 'stale_ignore', 'reason' => 'older_provider_version'];
            }

            if ((int) $incomingOrdering['provider_version'] === (int) $currentOrdering['provider_version']) {
                return ['action' => 'review_required', 'reason' => 'conflicting_same_provider_version'];
            }

            return ['action' => 'apply', 'reason' => 'newer_provider_version'];
        }

        if ($incomingOrdering['provider_timestamp'] !== null || $currentOrdering['provider_timestamp'] !== null) {
            if ($incomingOrdering['provider_timestamp'] !== null && $currentOrdering['provider_timestamp'] === null) {
                return ['action' => 'apply', 'reason' => 'incoming_has_first_provider_timestamp'];
            }

            if ($incomingOrdering['provider_timestamp'] === null && $currentOrdering['provider_timestamp'] !== null) {
                return ['action' => 'review_required', 'reason' => 'missing_provider_timestamp_for_conflicting_same_key_correction'];
            }

            $incomingTimestamp = strtotime((string) $incomingOrdering['provider_timestamp']);
            $currentTimestamp = strtotime((string) $currentOrdering['provider_timestamp']);

            if ($incomingTimestamp === false || $currentTimestamp === false) {
                return ['action' => 'review_required', 'reason' => 'invalid_provider_timestamp_for_conflicting_same_key_correction'];
            }

            if ($incomingTimestamp < $currentTimestamp) {
                return ['action' => 'stale_ignore', 'reason' => 'older_provider_timestamp'];
            }

            if ($incomingTimestamp === $currentTimestamp) {
                return ['action' => 'review_required', 'reason' => 'conflicting_same_provider_timestamp'];
            }

            return ['action' => 'apply', 'reason' => 'newer_provider_timestamp'];
        }

        return ['action' => 'review_required', 'reason' => 'conflicting_same_key_without_reliable_ordering'];
    }

    private function semanticSignatureForPayload(array $payload): string
    {
        return hash('sha256', json_encode([
            'source_adapter_key' => $payload['source_adapter_key'] ?? null,
            'source_event_ref' => $payload['source_event_ref'] ?? null,
            'source_market_ref' => $payload['source_market_ref'] ?? null,
            'source_outcome_ref' => $payload['source_outcome_ref'] ?? null,
            'candidate_result_code' => $payload['candidate_result_code'] ?? null,
            'candidate_status' => $payload['candidate_status'] ?? null,
            'normalization_status' => $payload['normalization_status'] ?? null,
            'normalization_reason' => $payload['normalization_reason'] ?? null,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function semanticSignatureForStoredSourceResult(BettingSettlementSourceResult $sourceResult): string
    {
        $stored = data_get($sourceResult->meta ?? [], 'semantic_signature');
        if (is_string($stored) && $stored !== '') {
            return $stored;
        }

        return $this->semanticSignatureForPayload([
            'source_adapter_key' => $sourceResult->source_adapter_key,
            'source_event_ref' => $sourceResult->source_event_ref,
            'source_market_ref' => $sourceResult->source_market_ref,
            'source_outcome_ref' => $sourceResult->source_outcome_ref,
            'candidate_result_code' => $sourceResult->candidate_result_code,
            'candidate_status' => $sourceResult->candidate_status,
            'normalization_status' => $sourceResult->normalization_status,
            'normalization_reason' => $sourceResult->normalization_reason,
        ]);
    }

    private function extractOrderingMetadata(array $sourcePayload, $receivedAt): array
    {
        return [
            'provider_version' => $this->extractProviderVersion($sourcePayload),
            'provider_timestamp' => $this->extractProviderTimestamp($sourcePayload),
            'received_at' => $receivedAt instanceof CarbonInterface ? $receivedAt->toIso8601String() : (string) $receivedAt,
        ];
    }

    private function orderingForStoredSourceResult(?BettingSettlementSourceResult $sourceResult): array
    {
        if (!$sourceResult) {
            return [
                'provider_version' => null,
                'provider_timestamp' => null,
                'received_at' => null,
            ];
        }

        $stored = data_get($sourceResult->meta ?? [], 'ordering');
        if (is_array($stored)) {
            return [
                'provider_version' => $stored['provider_version'] ?? null,
                'provider_timestamp' => $stored['provider_timestamp'] ?? null,
                'received_at' => $stored['received_at'] ?? optional($sourceResult->received_at)->toIso8601String(),
            ];
        }

        return $this->extractOrderingMetadata($sourceResult->source_payload ?? [], $sourceResult->received_at);
    }

    private function extractProviderVersion(array $payload): ?int
    {
        foreach ([
            data_get($payload, 'version'),
            data_get($payload, 'revision'),
            data_get($payload, 'sequence'),
            data_get($payload, 'result.version'),
            data_get($payload, 'result.revision'),
            data_get($payload, 'settlement.version'),
            data_get($payload, 'settlement.revision'),
        ] as $candidate) {
            if ($candidate === null || $candidate === '') {
                continue;
            }

            if (is_numeric($candidate)) {
                return (int) $candidate;
            }
        }

        return null;
    }

    private function extractProviderTimestamp(array $payload): ?string
    {
        foreach ([
            data_get($payload, 'published_at'),
            data_get($payload, 'updated_at'),
            data_get($payload, 'settled_at'),
            data_get($payload, 'completed_at'),
            data_get($payload, 'result.published_at'),
            data_get($payload, 'result.updated_at'),
            data_get($payload, 'result.settled_at'),
            data_get($payload, 'settlement.published_at'),
            data_get($payload, 'settlement.updated_at'),
            data_get($payload, 'settlement.settled_at'),
        ] as $candidate) {
            $resolved = trim((string) $candidate);
            if ($resolved !== '') {
                return $resolved;
            }
        }

        return null;
    }
}
