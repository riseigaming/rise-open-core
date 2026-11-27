<?php

namespace App\Betting\Admin;

use App\Betting\Enums\BettingAcceptanceStatus;
use App\Betting\Enums\BettingDecisionSource;
use App\Betting\Enums\BettingSettlementPrecedenceState;
use App\Betting\Enums\BettingSettlementStatus;
use App\Models\BettingActivityLog;
use App\Models\BettingAcceptance;
use App\Models\BettingEvent;
use App\Models\BettingSettlement;
use App\Models\BettingSettlementSourceIngest;
use App\Models\BettingSettlementWalletEffect;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SettlementExceptionsReadService
{
    private const HIGH_SEVERITY_GRACE_MINUTES = 30;

    public function filtersFromRequest(Request $request): array
    {
        $window = (int) $request->query('window', 7);
        if (!in_array($window, [1, 3, 7, 14, 30, 90], true)) {
            $window = 7;
        }

        $severity = strtolower(trim((string) $request->query('severity', 'all')));
        if (!in_array($severity, ['all', 'high', 'medium', 'low'], true)) {
            $severity = 'all';
        }

        $type = strtolower(trim((string) $request->query('type', 'all')));
        if (!in_array($type, [
            'all',
            'missing_settlement',
            'settlement_mismatch',
            'suspicious_payout',
            'mixed_precedence',
            'missing_finance_link',
            'mixed_states',
            'review_required',
        ], true)) {
            $type = 'all';
        }

        $settlement = strtolower(trim((string) $request->query('settlement', 'all')));
        if (!in_array($settlement, ['all', 'settled', 'unsettled'], true)) {
            $settlement = 'all';
        }

        return [
            'q' => trim((string) $request->query('q', '')),
            'window' => $window,
            'severity' => $severity,
            'type' => $type,
            'settlement' => $settlement,
        ];
    }

    public function items(array $filters): LengthAwarePaginator
    {
        $paginator = $this->baseQuery($filters)
            ->paginate(40)
            ->withQueryString();

        $eventIds = $paginator->getCollection()->pluck('id')->all();
        $recentLogs = $this->recentLogs($eventIds);

        $paginator->setCollection(
            $paginator->getCollection()->map(function (BettingEvent $event) use ($recentLogs) {
                $exceptions = $this->exceptionDefinitions($event);
                $severity = $this->severityDefinition((int) ($event->exception_severity_rank ?? 0));

                $lastActivity = collect([
                    $event->exception_last_activity_at,
                    $event->starts_at,
                ])->first(function ($value) {
                    return $value instanceof Carbon || (is_string($value) && trim($value) !== '');
                });

                if (is_string($lastActivity) && trim($lastActivity) !== '') {
                    $lastActivity = Carbon::parse($lastActivity);
                }

                $event->setAttribute('exception_items', collect($exceptions));
                $event->setAttribute('exception_primary', $exceptions[0] ?? null);
                $event->setAttribute('exception_severity', $severity);
                $event->setAttribute('exception_recent_logs', $recentLogs->get($event->id, collect()));
                $event->setAttribute('exception_last_activity_at', $lastActivity);
                $event->setAttribute('exception_finance_summary', [
                    'wallet_effect_count' => (int) ($event->wallet_effect_count ?? 0),
                    'linked_finance_count' => (int) ($event->linked_finance_count ?? 0),
                    'missing_wallet_link_count' => (int) ($event->missing_wallet_link_count ?? 0),
                ]);
                $event->setAttribute('exception_reconciliation_summary', [
                    'source_applied_count' => (int) ($event->source_applied_count ?? 0),
                    'manual_override_count' => (int) ($event->manual_override_count ?? 0),
                    'review_precedence_count' => (int) ($event->review_precedence_count ?? 0),
                    'review_ingest_count' => (int) ($event->review_ingest_count ?? 0),
                    'unresolved_ingest_count' => (int) ($event->unresolved_ingest_count ?? 0),
                    'ambiguous_ingest_count' => (int) ($event->ambiguous_ingest_count ?? 0),
                ]);

                return $event;
            })
        );

        return $paginator;
    }

    public function summary(array $filters): array
    {
        $rows = $this->baseQuery($filters)->get();

        return [
            'total' => $rows->count(),
            'high' => $rows->where('exception_severity_rank', 3)->count(),
            'medium' => $rows->where('exception_severity_rank', 2)->count(),
            'low' => $rows->where('exception_severity_rank', 1)->count(),
        ];
    }

    private function baseQuery(array $filters): Builder
    {
        $acceptanceStats = $this->acceptanceStatsQuery();
        $settlementStats = $this->settlementStatsQuery();
        $walletEffectStats = $this->walletEffectStatsQuery();
        $sourceIngestStats = $this->sourceIngestStatsQuery();
        $activityStats = $this->activityStatsQuery();

        $acceptedExpr = 'COALESCE(acceptance_stats.accepted_bet_count, 0)';
        $settlementTotalExpr = 'COALESCE(settlement_stats.settlement_total_count, 0)';
        $closedExpr = 'COALESCE(settlement_stats.closed_settlement_count, 0)';
        $reviewExpr = 'COALESCE(settlement_stats.review_settlement_count, 0)';
        $pendingExpr = 'COALESCE(settlement_stats.pending_settlement_count, 0)';
        $winnerExpr = 'COALESCE(settlement_stats.winner_count, 0)';
        $loserExpr = 'COALESCE(settlement_stats.loser_count, 0)';
        $voidExpr = 'COALESCE(settlement_stats.void_count, 0)';
        $refundedExpr = 'COALESCE(settlement_stats.refunded_count, 0)';
        $cancelledExpr = 'COALESCE(settlement_stats.cancelled_count, 0)';
        $postponedExpr = 'COALESCE(settlement_stats.postponed_count, 0)';
        $payoutExpr = 'COALESCE(settlement_stats.payout_total, 0)';
        $manualExpr = 'COALESCE(settlement_stats.manual_override_count, 0)';
        $sourceAppliedExpr = 'COALESCE(settlement_stats.source_applied_count, 0)';
        $reviewPrecedenceExpr = 'COALESCE(settlement_stats.review_precedence_count, 0)';
        $sourceConflictExpr = 'COALESCE(settlement_stats.source_conflict_count, 0)';
        $missingWalletLinkExpr = 'COALESCE(wallet_effect_stats.missing_wallet_link_count, 0)';
        $reviewIngestExpr = 'COALESCE(source_ingest_stats.review_ingest_count, 0)';
        $pastBoundary = now()->toDateTimeString();
        $graceBoundary = now()->subMinutes(self::HIGH_SEVERITY_GRACE_MINUTES)->toDateTimeString();
        $coveredResultsExpr = "({$winnerExpr} + {$loserExpr} + {$voidExpr} + {$refundedExpr} + {$cancelledExpr} + {$postponedExpr})";
        $exceptionGraceEligibleExpr = "betting_events.starts_at < '{$graceBoundary}'";

        $missingSettlementCondition = "{$acceptedExpr} > 0 AND betting_events.starts_at < '{$pastBoundary}' AND {$exceptionGraceEligibleExpr} AND {$settlementTotalExpr} = 0";
        $settlementMismatchCondition = "{$acceptedExpr} > 0 AND betting_events.starts_at < '{$pastBoundary}' AND {$exceptionGraceEligibleExpr} AND {$settlementTotalExpr} > 0 AND {$closedExpr} < {$acceptedExpr}";
        $suspiciousPayoutCondition = "{$closedExpr} > 0 AND (({$winnerExpr} > 0 AND ABS({$payoutExpr}) < 0.000001) OR ({$payoutExpr} > 0 AND {$winnerExpr} = 0) OR ({$coveredResultsExpr} < {$closedExpr}))";
        $mixedPrecedenceCondition = "{$manualExpr} > 0 AND {$sourceAppliedExpr} > 0";
        $missingFinanceCondition = "{$missingWalletLinkExpr} > 0";
        $mixedStatesCondition = "{$closedExpr} > 0 AND ({$pendingExpr} > 0 OR {$reviewExpr} > 0)";
        $reviewRequiredCondition = "{$reviewExpr} > 0 OR {$reviewPrecedenceExpr} > 0 OR {$sourceConflictExpr} > 0 OR {$reviewIngestExpr} > 0";
        $severityRankExpr = "CASE
            WHEN {$missingSettlementCondition} OR {$settlementMismatchCondition} OR {$suspiciousPayoutCondition} THEN 3
            WHEN {$mixedPrecedenceCondition} OR {$missingFinanceCondition} OR {$mixedStatesCondition} THEN 2
            WHEN {$reviewRequiredCondition} THEN 1
            ELSE 0
        END";
        $lastActivityExpr = "COALESCE(activity_stats.last_activity_at, settlement_stats.last_settlement_update_at, source_ingest_stats.last_source_received_at, acceptance_stats.last_acceptance_transition_at, betting_events.starts_at)";

        $query = BettingEvent::query()
            ->with(['currentQuoteVersion:id,betting_event_id,version_no,trading_state,quote_status'])
            ->leftJoinSub($acceptanceStats, 'acceptance_stats', function ($join) {
                $join->on('acceptance_stats.betting_event_id', '=', 'betting_events.id');
            })
            ->leftJoinSub($settlementStats, 'settlement_stats', function ($join) {
                $join->on('settlement_stats.betting_event_id', '=', 'betting_events.id');
            })
            ->leftJoinSub($walletEffectStats, 'wallet_effect_stats', function ($join) {
                $join->on('wallet_effect_stats.betting_event_id', '=', 'betting_events.id');
            })
            ->leftJoinSub($sourceIngestStats, 'source_ingest_stats', function ($join) {
                $join->on('source_ingest_stats.betting_event_id', '=', 'betting_events.id');
            })
            ->leftJoinSub($activityStats, 'activity_stats', function ($join) {
                $join->on('activity_stats.betting_event_id', '=', 'betting_events.id');
            })
            ->select([
                'betting_events.id',
                'betting_events.event_key',
                'betting_events.sport_code',
                'betting_events.lifecycle_status',
                'betting_events.trading_status',
                'betting_events.visibility_status',
                'betting_events.front_betting_enabled',
                'betting_events.canonical_name',
                'betting_events.tournament_name',
                'betting_events.starts_at',
                'betting_events.current_quote_version_id',
            ])
            ->addSelect([
                DB::raw('COALESCE(acceptance_stats.acceptance_total_count, 0) as acceptance_total_count'),
                DB::raw('COALESCE(acceptance_stats.accepted_bet_count, 0) as accepted_bet_count'),
                DB::raw('COALESCE(acceptance_stats.unique_bettor_count, 0) as unique_bettor_count'),
                DB::raw('COALESCE(acceptance_stats.total_stake_amount, 0) as total_stake_amount'),
                DB::raw('COALESCE(acceptance_stats.rejected_bet_count, 0) as rejected_bet_count'),
                DB::raw('COALESCE(acceptance_stats.held_bet_count, 0) as held_bet_count'),
                DB::raw('COALESCE(acceptance_stats.latest_accepted_acceptance_id, NULL) as latest_accepted_acceptance_id'),
                DB::raw('COALESCE(acceptance_stats.last_acceptance_transition_at, NULL) as last_acceptance_transition_at'),
                DB::raw('COALESCE(settlement_stats.settlement_total_count, 0) as settlement_total_count'),
                DB::raw('COALESCE(settlement_stats.closed_settlement_count, 0) as closed_settlement_count'),
                DB::raw('COALESCE(settlement_stats.pending_settlement_count, 0) as pending_settlement_count'),
                DB::raw('COALESCE(settlement_stats.review_settlement_count, 0) as review_settlement_count'),
                DB::raw('COALESCE(settlement_stats.winner_count, 0) as winner_count'),
                DB::raw('COALESCE(settlement_stats.loser_count, 0) as loser_count'),
                DB::raw('COALESCE(settlement_stats.void_count, 0) as void_count'),
                DB::raw('COALESCE(settlement_stats.refunded_count, 0) as refunded_count'),
                DB::raw('COALESCE(settlement_stats.cancelled_count, 0) as cancelled_count'),
                DB::raw('COALESCE(settlement_stats.postponed_count, 0) as postponed_count'),
                DB::raw('COALESCE(settlement_stats.payout_total, 0) as payout_total'),
                DB::raw('COALESCE(settlement_stats.manual_override_count, 0) as manual_override_count'),
                DB::raw('COALESCE(settlement_stats.source_applied_count, 0) as source_applied_count'),
                DB::raw('COALESCE(settlement_stats.review_precedence_count, 0) as review_precedence_count'),
                DB::raw('COALESCE(settlement_stats.source_conflict_count, 0) as source_conflict_count'),
                DB::raw('COALESCE(settlement_stats.latest_settlement_id, NULL) as latest_settlement_id'),
                DB::raw('COALESCE(settlement_stats.last_settlement_update_at, NULL) as last_settlement_update_at'),
                DB::raw('COALESCE(wallet_effect_stats.wallet_effect_count, 0) as wallet_effect_count'),
                DB::raw('COALESCE(wallet_effect_stats.linked_finance_count, 0) as linked_finance_count'),
                DB::raw('COALESCE(wallet_effect_stats.missing_wallet_link_count, 0) as missing_wallet_link_count'),
                DB::raw('COALESCE(source_ingest_stats.review_ingest_count, 0) as review_ingest_count'),
                DB::raw('COALESCE(source_ingest_stats.unresolved_ingest_count, 0) as unresolved_ingest_count'),
                DB::raw('COALESCE(source_ingest_stats.ambiguous_ingest_count, 0) as ambiguous_ingest_count'),
                DB::raw('COALESCE(source_ingest_stats.last_source_received_at, NULL) as last_source_received_at'),
                DB::raw("CASE WHEN {$missingSettlementCondition} THEN 1 ELSE 0 END as exception_missing_settlement"),
                DB::raw("CASE WHEN {$settlementMismatchCondition} THEN 1 ELSE 0 END as exception_settlement_mismatch"),
                DB::raw("CASE WHEN {$suspiciousPayoutCondition} THEN 1 ELSE 0 END as exception_suspicious_payout"),
                DB::raw("CASE WHEN {$mixedPrecedenceCondition} THEN 1 ELSE 0 END as exception_mixed_precedence"),
                DB::raw("CASE WHEN {$missingFinanceCondition} THEN 1 ELSE 0 END as exception_missing_finance_link"),
                DB::raw("CASE WHEN {$mixedStatesCondition} THEN 1 ELSE 0 END as exception_mixed_states"),
                DB::raw("CASE WHEN {$reviewRequiredCondition} THEN 1 ELSE 0 END as exception_review_required"),
                DB::raw("{$severityRankExpr} as exception_severity_rank"),
                DB::raw("{$lastActivityExpr} as exception_last_activity_at"),
            ]);

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $needle = '%' . $search . '%';
            $query->where(function (Builder $builder) use ($needle) {
                $builder
                    ->where('betting_events.canonical_name', 'like', $needle)
                    ->orWhere('betting_events.event_key', 'like', $needle)
                    ->orWhere('betting_events.tournament_name', 'like', $needle)
                    ->orWhere('betting_events.sport_code', 'like', $needle);
            });
        }

        $windowDays = (int) ($filters['window'] ?? 7);
        $query->where('betting_events.starts_at', '>=', now()->subDays($windowDays)->startOfDay());
        $query->whereRaw("{$acceptedExpr} > 0");

        if (($filters['settlement'] ?? 'all') === 'settled') {
            $query->whereRaw("{$closedExpr} >= {$acceptedExpr}");
        }

        if (($filters['settlement'] ?? 'all') === 'unsettled') {
            $query->whereRaw("{$closedExpr} < {$acceptedExpr}");
        }

        $typeConditions = [
            'missing_settlement' => $missingSettlementCondition,
            'settlement_mismatch' => $settlementMismatchCondition,
            'suspicious_payout' => $suspiciousPayoutCondition,
            'mixed_precedence' => $mixedPrecedenceCondition,
            'missing_finance_link' => $missingFinanceCondition,
            'mixed_states' => $mixedStatesCondition,
            'review_required' => $reviewRequiredCondition,
        ];

        $selectedType = $filters['type'] ?? 'all';
        if ($selectedType !== 'all' && isset($typeConditions[$selectedType])) {
            $query->whereRaw($typeConditions[$selectedType]);
        } else {
            $query->where(function (Builder $builder) use ($typeConditions) {
                foreach ($typeConditions as $condition) {
                    $builder->orWhereRaw($condition);
                }
            });
        }

        $selectedSeverity = $filters['severity'] ?? 'all';
        if ($selectedSeverity !== 'all') {
            $severityMap = ['low' => 1, 'medium' => 2, 'high' => 3];
            $query->whereRaw("({$severityRankExpr}) = ?", [$severityMap[$selectedSeverity] ?? 0]);
        }

        return $query
            ->orderByRaw("({$severityRankExpr}) DESC")
            ->orderByRaw("{$lastActivityExpr} DESC")
            ->orderByDesc('betting_events.starts_at')
            ->orderByDesc('betting_events.id');
    }

    private function acceptanceStatsQuery()
    {
        if (!$this->tableExists('betting_acceptances')) {
            return $this->emptyEventStatsQuery([
                'acceptance_total_count' => '0',
                'accepted_bet_count' => '0',
                'unique_bettor_count' => '0',
                'total_stake_amount' => '0',
                'rejected_bet_count' => '0',
                'held_bet_count' => '0',
                'latest_accepted_acceptance_id' => 'NULL',
                'last_acceptance_transition_at' => 'NULL',
            ]);
        }

        $acceptedStatuses = sprintf(
            "'%s','%s','%s'",
            BettingAcceptanceStatus::ACCEPTED,
            BettingAcceptanceStatus::SETTLED,
            BettingAcceptanceStatus::CANCELLED
        );

        return BettingAcceptance::query()
            ->selectRaw('betting_event_id')
            ->selectRaw('COUNT(*) as acceptance_total_count')
            ->selectRaw("SUM(CASE WHEN status IN ({$acceptedStatuses}) THEN 1 ELSE 0 END) as accepted_bet_count")
            ->selectRaw("COUNT(DISTINCT CASE WHEN status IN ({$acceptedStatuses}) THEN COALESCE(root_user_id, cms_user_id) END) as unique_bettor_count")
            ->selectRaw("SUM(CASE WHEN status IN ({$acceptedStatuses}) THEN stake_amount ELSE 0 END) as total_stake_amount")
            ->selectRaw("SUM(CASE WHEN status = '" . BettingAcceptanceStatus::REJECTED . "' THEN 1 ELSE 0 END) as rejected_bet_count")
            ->selectRaw("SUM(CASE WHEN status IN ('" . BettingAcceptanceStatus::HELD . "','" . BettingAcceptanceStatus::SUSPENDED . "') THEN 1 ELSE 0 END) as held_bet_count")
            ->selectRaw("MAX(CASE WHEN status IN ({$acceptedStatuses}) THEN id ELSE NULL END) as latest_accepted_acceptance_id")
            ->selectRaw('MAX(COALESCE(last_transition_at, accepted_at, requested_at)) as last_acceptance_transition_at')
            ->groupBy('betting_event_id');
    }

    private function settlementStatsQuery()
    {
        if (!$this->tableExists('betting_settlements') || !$this->tableExists('betting_acceptances')) {
            return $this->emptyEventStatsQuery([
                'settlement_total_count' => '0',
                'pending_settlement_count' => '0',
                'review_settlement_count' => '0',
                'closed_settlement_count' => '0',
                'winner_count' => '0',
                'loser_count' => '0',
                'void_count' => '0',
                'refunded_count' => '0',
                'cancelled_count' => '0',
                'postponed_count' => '0',
                'payout_total' => '0',
                'manual_override_count' => '0',
                'source_applied_count' => '0',
                'review_precedence_count' => '0',
                'source_conflict_count' => '0',
                'latest_settlement_id' => 'NULL',
                'last_settlement_update_at' => 'NULL',
            ]);
        }

        $closedStatuses = sprintf(
            "'%s','%s','%s','%s'",
            BettingSettlementStatus::FINAL,
            BettingSettlementStatus::VOID,
            BettingSettlementStatus::CANCELLED,
            BettingSettlementStatus::POSTPONED
        );

        return BettingSettlement::query()
            ->join('betting_acceptances', 'betting_acceptances.id', '=', 'betting_settlements.betting_acceptance_id')
            ->selectRaw('betting_acceptances.betting_event_id as betting_event_id')
            ->selectRaw('COUNT(*) as settlement_total_count')
            ->selectRaw("SUM(CASE WHEN betting_settlements.status = '" . BettingSettlementStatus::PENDING . "' THEN 1 ELSE 0 END) as pending_settlement_count")
            ->selectRaw("SUM(CASE WHEN betting_settlements.status = '" . BettingSettlementStatus::REVIEW . "' THEN 1 ELSE 0 END) as review_settlement_count")
            ->selectRaw("SUM(CASE WHEN betting_settlements.settled_at IS NOT NULL AND betting_settlements.status IN ({$closedStatuses}) THEN 1 ELSE 0 END) as closed_settlement_count")
            ->selectRaw("SUM(CASE WHEN betting_settlements.settled_at IS NOT NULL AND betting_settlements.result_code = 'won' THEN 1 ELSE 0 END) as winner_count")
            ->selectRaw("SUM(CASE WHEN betting_settlements.settled_at IS NOT NULL AND betting_settlements.result_code = 'lost' THEN 1 ELSE 0 END) as loser_count")
            ->selectRaw("SUM(CASE WHEN betting_settlements.settled_at IS NOT NULL AND betting_settlements.result_code = 'void' THEN 1 ELSE 0 END) as void_count")
            ->selectRaw("SUM(CASE WHEN betting_settlements.settled_at IS NOT NULL AND betting_settlements.result_code = 'refunded' THEN 1 ELSE 0 END) as refunded_count")
            ->selectRaw("SUM(CASE WHEN betting_settlements.settled_at IS NOT NULL AND betting_settlements.result_code = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count")
            ->selectRaw("SUM(CASE WHEN betting_settlements.settled_at IS NOT NULL AND betting_settlements.result_code = 'postponed' THEN 1 ELSE 0 END) as postponed_count")
            ->selectRaw("SUM(CASE WHEN betting_settlements.settled_at IS NOT NULL AND betting_settlements.result_code <> 'pending' THEN betting_settlements.return_amount ELSE 0 END) as payout_total")
            ->selectRaw("SUM(CASE WHEN betting_settlements.manual_override_active = 1 THEN 1 ELSE 0 END) as manual_override_count")
            ->selectRaw("SUM(CASE WHEN betting_settlements.decision_source = '" . BettingDecisionSource::SOURCE . "' AND betting_settlements.precedence_state = '" . BettingSettlementPrecedenceState::SOURCE_APPLIED . "' THEN 1 ELSE 0 END) as source_applied_count")
            ->selectRaw("SUM(CASE WHEN betting_settlements.precedence_state = '" . BettingSettlementPrecedenceState::REVIEW_REQUIRED . "' THEN 1 ELSE 0 END) as review_precedence_count")
            ->selectRaw("SUM(CASE WHEN betting_settlements.source_conflict_reason IS NOT NULL AND betting_settlements.source_conflict_reason <> '' THEN 1 ELSE 0 END) as source_conflict_count")
            ->selectRaw('MAX(betting_settlements.id) as latest_settlement_id')
            ->selectRaw('MAX(COALESCE(betting_settlements.source_reconciled_at, betting_settlements.settled_at, betting_settlements.reviewed_at, betting_settlements.updated_at)) as last_settlement_update_at')
            ->groupBy('betting_acceptances.betting_event_id');
    }

    private function walletEffectStatsQuery()
    {
        if (
            !$this->tableExists('betting_settlement_wallet_effects')
            || !$this->tableExists('betting_settlements')
            || !$this->tableExists('betting_acceptances')
        ) {
            return $this->emptyEventStatsQuery([
                'wallet_effect_count' => '0',
                'linked_finance_count' => '0',
                'missing_wallet_link_count' => '0',
            ]);
        }

        return BettingSettlementWalletEffect::query()
            ->join('betting_settlements', 'betting_settlements.id', '=', 'betting_settlement_wallet_effects.betting_settlement_id')
            ->join('betting_acceptances', 'betting_acceptances.id', '=', 'betting_settlements.betting_acceptance_id')
            ->selectRaw('betting_acceptances.betting_event_id as betting_event_id')
            ->selectRaw('COUNT(*) as wallet_effect_count')
            ->selectRaw('SUM(CASE WHEN betting_settlement_wallet_effects.cms_user_transaction_id IS NOT NULL THEN 1 ELSE 0 END) as linked_finance_count')
            ->selectRaw('SUM(CASE WHEN betting_settlement_wallet_effects.cms_user_transaction_id IS NULL THEN 1 ELSE 0 END) as missing_wallet_link_count')
            ->groupBy('betting_acceptances.betting_event_id');
    }

    private function sourceIngestStatsQuery()
    {
        if (!$this->tableExists('betting_settlement_source_ingests') || !$this->tableExists('betting_acceptances')) {
            return $this->emptyEventStatsQuery([
                'review_ingest_count' => '0',
                'unresolved_ingest_count' => '0',
                'ambiguous_ingest_count' => '0',
                'last_source_received_at' => 'NULL',
            ]);
        }

        return BettingSettlementSourceIngest::query()
            ->leftJoin('betting_settlements', 'betting_settlements.id', '=', 'betting_settlement_source_ingests.betting_settlement_id')
            ->leftJoin('betting_acceptances as direct_acceptances', 'direct_acceptances.id', '=', 'betting_settlement_source_ingests.betting_acceptance_id')
            ->leftJoin('betting_acceptances as settlement_acceptances', 'settlement_acceptances.id', '=', 'betting_settlements.betting_acceptance_id')
            ->selectRaw('COALESCE(direct_acceptances.betting_event_id, settlement_acceptances.betting_event_id) as betting_event_id')
            ->selectRaw("SUM(CASE WHEN betting_settlement_source_ingests.ingest_status IN ('unresolved', 'ambiguous') THEN 1 ELSE 0 END) as review_ingest_count")
            ->selectRaw("SUM(CASE WHEN betting_settlement_source_ingests.ingest_status = 'unresolved' THEN 1 ELSE 0 END) as unresolved_ingest_count")
            ->selectRaw("SUM(CASE WHEN betting_settlement_source_ingests.ingest_status = 'ambiguous' THEN 1 ELSE 0 END) as ambiguous_ingest_count")
            ->selectRaw('MAX(COALESCE(betting_settlement_source_ingests.last_received_at, betting_settlement_source_ingests.resolved_at, betting_settlement_source_ingests.created_at)) as last_source_received_at')
            ->whereNotNull(DB::raw('COALESCE(direct_acceptances.betting_event_id, settlement_acceptances.betting_event_id)'))
            ->groupBy(DB::raw('COALESCE(direct_acceptances.betting_event_id, settlement_acceptances.betting_event_id)'));
    }

    private function activityStatsQuery()
    {
        if (!$this->tableExists('betting_activity_logs')) {
            return $this->emptyEventStatsQuery([
                'last_activity_at' => 'NULL',
            ]);
        }

        return BettingActivityLog::query()
            ->selectRaw('betting_event_id')
            ->selectRaw('MAX(created_at) as last_activity_at')
            ->whereNotNull('betting_event_id')
            ->groupBy('betting_event_id');
    }

    private function recentLogs(array $eventIds): Collection
    {
        if (empty($eventIds) || !$this->tableExists('betting_activity_logs')) {
            return collect();
        }

        return BettingActivityLog::query()
            ->select([
                'id',
                'betting_event_id',
                'action',
                'message',
                'created_at',
            ])
            ->whereIn('betting_event_id', $eventIds)
            ->latest('id')
            ->limit(max(count($eventIds) * 4, 24))
            ->get()
            ->groupBy('betting_event_id')
            ->map(fn (Collection $logs) => $logs->take(4)->values());
    }

    private function exceptionDefinitions(BettingEvent $event): array
    {
        $definitions = [
            'exception_missing_settlement' => [
                'key' => 'missing_settlement',
                'label' => __('admin.settlement_exceptions.missing_settlement'),
                'severity' => 'high',
                'tone' => 'danger',
                'reason' => __('admin.settlement_exceptions.missing_settlement_reason'),
            ],
            'exception_settlement_mismatch' => [
                'key' => 'settlement_mismatch',
                'label' => __('admin.settlement_exceptions.settlement_mismatch'),
                'severity' => 'high',
                'tone' => 'danger',
                'reason' => __('admin.settlement_exceptions.settlement_mismatch_reason', [
                    'closed' => (int) ($event->closed_settlement_count ?? 0),
                    'accepted' => (int) ($event->accepted_bet_count ?? 0),
                ]),
            ],
            'exception_suspicious_payout' => [
                'key' => 'suspicious_payout',
                'label' => __('admin.settlement_exceptions.suspicious_payout'),
                'severity' => 'high',
                'tone' => 'danger',
                'reason' => $this->suspiciousPayoutReason($event),
            ],
            'exception_mixed_precedence' => [
                'key' => 'mixed_precedence',
                'label' => __('admin.settlement_exceptions.mixed_precedence'),
                'severity' => 'medium',
                'tone' => 'warning',
                'reason' => __('admin.settlement_exceptions.mixed_precedence_reason'),
            ],
            'exception_missing_finance_link' => [
                'key' => 'missing_finance_link',
                'label' => __('admin.settlement_exceptions.missing_finance_link'),
                'severity' => 'medium',
                'tone' => 'warning',
                'reason' => __('admin.settlement_exceptions.missing_finance_link_reason'),
            ],
            'exception_mixed_states' => [
                'key' => 'mixed_states',
                'label' => __('admin.settlement_exceptions.mixed_states'),
                'severity' => 'medium',
                'tone' => 'warning',
                'reason' => __('admin.settlement_exceptions.mixed_states_reason'),
            ],
            'exception_review_required' => [
                'key' => 'review_required',
                'label' => __('admin.settlement_exceptions.review_required'),
                'severity' => 'low',
                'tone' => 'info',
                'reason' => $this->reviewRequiredReason($event),
            ],
        ];

        $items = [];
        foreach ($definitions as $attribute => $meta) {
            if ((int) ($event->{$attribute} ?? 0) === 1) {
                $items[] = $meta;
            }
        }

        return $items;
    }

    private function suspiciousPayoutReason(BettingEvent $event): string
    {
        $winnerCount = (int) ($event->winner_count ?? 0);
        $payout = (float) ($event->payout_total ?? 0);
        $closedCount = (int) ($event->closed_settlement_count ?? 0);
        $coveredCount = (int) ($event->winner_count ?? 0)
            + (int) ($event->loser_count ?? 0)
            + (int) ($event->void_count ?? 0)
            + (int) ($event->refunded_count ?? 0)
            + (int) ($event->cancelled_count ?? 0)
            + (int) ($event->postponed_count ?? 0);

        if ($winnerCount > 0 && abs($payout) < 0.000001) {
            return __('admin.settlement_exceptions.winner_without_payout');
        }

        if ($payout > 0 && $winnerCount === 0) {
            return __('admin.settlement_exceptions.payout_without_winner');
        }

        if ($coveredCount < $closedCount) {
            return __('admin.settlement_exceptions.incomplete_closed_distribution');
        }

        return __('admin.settlement_exceptions.aggregate_review');
    }

    private function reviewRequiredReason(BettingEvent $event): string
    {
        if ((int) ($event->review_ingest_count ?? 0) > 0) {
            return __('admin.settlement_exceptions.unresolved_ingest');
        }

        if ((int) ($event->review_precedence_count ?? 0) > 0) {
            return __('admin.settlement_exceptions.precedence_review');
        }

        if ((int) ($event->review_settlement_count ?? 0) > 0) {
            return __('admin.settlement_exceptions.rows_in_review');
        }

        if ((int) ($event->source_conflict_count ?? 0) > 0) {
            return __('admin.settlement_exceptions.source_conflict');
        }

        return __('admin.settlement_exceptions.neutral_review');
    }

    private function severityDefinition(int $rank): array
    {
        return match ($rank) {
            3 => ['key' => 'high', 'label' => __('admin.settlement_exceptions.high'), 'tone' => 'danger'],
            2 => ['key' => 'medium', 'label' => __('admin.settlement_exceptions.medium'), 'tone' => 'warning'],
            default => ['key' => 'low', 'label' => __('admin.settlement_exceptions.low'), 'tone' => 'info'],
        };
    }

    private function emptyEventStatsQuery(array $columns)
    {
        $query = DB::query()->selectRaw('NULL as betting_event_id');

        foreach ($columns as $name => $defaultExpression) {
            $query->selectRaw($defaultExpression . ' as ' . $name);
        }

        return $query->whereRaw('1 = 0');
    }

    private function tableExists(string $table): bool
    {
        return Schema::hasTable($table);
    }
}
