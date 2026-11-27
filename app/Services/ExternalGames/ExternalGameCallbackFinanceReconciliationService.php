<?php

namespace App\Services\ExternalGames;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ExternalGameCallbackFinanceReconciliationService
{
    public function __construct(
        private readonly ExternalGameProviderRolloutService $providerRollout,
        private readonly ?string $providerAlias = null
    ) {
    }

    public function forProvider(string $alias): self
    {
        return new self($this->providerRollout, $alias);
    }

    private function financialRows(string $table, string $key, ?string $alias = null)
    {
        $alias ??= $this->providerAlias;
        $prefix = $alias === null ? 'external_games:' : ExternalGameProviderConfig::transactionPrefix($alias);

        return DB::table($table)->where($key, 'like', addcslashes($prefix, '\\%_') . '%');
    }

    private function sessions()
    {
        return DB::table('external_game_sessions')->when($this->providerAlias !== null,
            fn ($query) => $query->where('provider_alias', $this->providerAlias));
    }

    public function report(int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));

        if (!$this->requiredTablesExist()) {
            return [
                'status' => 'blocked',
                'checked_at' => now()->toDateTimeString(),
                'missing_tables' => $this->missingTables(),
                'anomaly_count' => 0,
                'checks' => [],
            ];
        }

        $transactions = $this->externalTransactions();
        $ledgers = $this->externalLedgerEntries();

        $providerTransactionsWithoutLedger = $this->transactionsWithoutLedger($transactions, $ledgers, $limit);
        $ledgerEntriesWithoutProviderTransaction = $this->ledgersWithoutTransaction($transactions, $ledgers, $limit);
        $duplicateProviderTransactionIds = $this->duplicateProviderTransactionIds($limit);
        $balanceMismatches = $this->balanceMismatches($transactions, $ledgers, $limit);
        $callbackSuccessWithoutFinancialRecord = $this->callbackSuccessWithoutFinancialRecord($transactions, $limit);
        $failedCallbacksByReason = $this->failedCallbacksByReason();
        $creditGuardClassifications = $this->creditGuardClassifications();
        $activeSessionAnomalies = $this->activeSessionAnomalies($limit);
        $providerActivity = $this->providerActivity($transactions, $limit);

        $financeIntegrityAnomalyCount = collect([
            $providerTransactionsWithoutLedger['count'],
            $ledgerEntriesWithoutProviderTransaction['count'],
            $duplicateProviderTransactionIds['count'],
            $balanceMismatches['count'],
            $callbackSuccessWithoutFinancialRecord['count'],
        ])->sum();
        $sessionAnomalyCount = (int) $activeSessionAnomalies['count'];
        $anomalyCount = collect([
            $financeIntegrityAnomalyCount,
            $activeSessionAnomalies['count'],
        ])->sum();
        $callbackTrafficWarningCount = (int) $failedCallbacksByReason['current_runtime_failure_count'];
        $testSmokeNoiseCount = (int) ($failedCallbacksByReason['historical_test_smoke_count'] ?? 0)
            + (int) ($activeSessionAnomalies['historical_test_smoke_count'] ?? 0);
        $expiredLaunchOnlyCount = (int) ($activeSessionAnomalies['expired_launch_only_count'] ?? 0);
        $expiredNonBlockingCount = (int) ($activeSessionAnomalies['expired_non_blocking_count'] ?? 0);

        return [
            'status' => $anomalyCount > 0 ? 'warning' : 'ok',
            'checked_at' => now()->toDateTimeString(),
            'anomaly_count' => $anomalyCount,
            'finance_integrity_status' => $financeIntegrityAnomalyCount > 0 ? 'warning' : 'ok',
            'finance_integrity_anomaly_count' => $financeIntegrityAnomalyCount,
            'current_callback_traffic_status' => $callbackTrafficWarningCount > 0 ? 'warning' : 'ok',
            'callback_traffic_status' => $callbackTrafficWarningCount > 0 ? 'warning' : 'ok',
            'callback_traffic_warning_count' => $callbackTrafficWarningCount,
            'historical_test_smoke_count' => $testSmokeNoiseCount,
            'test_smoke_noise_count' => $testSmokeNoiseCount,
            'expired_launch_only_count' => $expiredLaunchOnlyCount,
            'expired_non_blocking_session_count' => $expiredNonBlockingCount,
            'session_anomaly_count' => $sessionAnomalyCount,
            'active_session_anomaly_count' => $sessionAnomalyCount,
            'external_transaction_count' => $transactions->count(),
            'external_ledger_count' => $ledgers->count(),
            'checks' => [
                'provider_transactions_without_ledger' => $providerTransactionsWithoutLedger,
                'ledger_entries_without_provider_transaction' => $ledgerEntriesWithoutProviderTransaction,
                'duplicate_provider_transaction_ids' => $duplicateProviderTransactionIds,
                'balance_before_after_mismatches' => $balanceMismatches,
                'callback_success_with_no_financial_record' => $callbackSuccessWithoutFinancialRecord,
                'failed_callbacks_by_reason' => $failedCallbacksByReason,
                'credit_guard_classifications' => $creditGuardClassifications,
                'superseded_expired_active_session_anomalies' => $activeSessionAnomalies,
                'provider_activity' => $providerActivity,
            ],
        ];
    }

    private function requiredTablesExist(): bool
    {
        return $this->missingTables() === [];
    }

    private function missingTables(): array
    {
        return collect([
            'cms_user_transactions',
            'wallet_ledger_entries',
            'external_game_sessions',
            'site_settings',
        ])->reject(fn (string $table): bool => Schema::hasTable($table))->values()->all();
    }

    private function externalTransactions(): Collection
    {
        return $this->financialRows('cms_user_transactions', 'transactionId')
            ->get();
    }

    private function externalLedgerEntries(): Collection
    {
        return $this->financialRows('wallet_ledger_entries', 'idempotency_key')
            ->get();
    }

    private function transactionsWithoutLedger(Collection $transactions, Collection $ledgers, int $limit): array
    {
        $ledgerKeys = $ledgers->pluck('idempotency_key')->map(fn ($key): string => (string) $key)->all();
        $rows = $transactions
            ->reject(fn ($transaction): bool => in_array((string) $transaction->transactionId, $ledgerKeys, true))
            ->values();

        return $this->sampled($rows, $limit, fn ($transaction): array => [
            'transaction_id' => (string) $transaction->transactionId,
            'provider_transaction_id_present' => !empty($transaction->provider_transaction_id),
            'cms_user_id' => (int) $transaction->cms_user_id,
            'game_id' => $transaction->game_id,
            'event_type' => $transaction->event_type,
            'amount' => (float) $transaction->amount,
        ]);
    }

    private function ledgersWithoutTransaction(Collection $transactions, Collection $ledgers, int $limit): array
    {
        $transactionKeys = $transactions->pluck('transactionId')->map(fn ($key): string => (string) $key)->all();
        $rows = $ledgers
            ->reject(fn ($ledger): bool => in_array((string) $ledger->idempotency_key, $transactionKeys, true))
            ->values();

        return $this->sampled($rows, $limit, fn ($ledger): array => [
            'idempotency_key' => (string) $ledger->idempotency_key,
            'wallet_account_id' => (int) $ledger->wallet_account_id,
            'entry_type' => $ledger->entry_type,
            'direction' => $ledger->direction,
            'amount' => (float) $ledger->amount,
        ]);
    }

    private function duplicateProviderTransactionIds(int $limit): array
    {
        $rows = $this->financialRows('cms_user_transactions', 'transactionId')
            ->select('provider_transaction_id')
            ->selectRaw("SUBSTRING_INDEX(transactionId, ':', 2) as connector, COUNT(*) as duplicate_count")
            ->whereNotNull('provider_transaction_id')
            ->where('provider_transaction_id', '!=', '')
            ->groupBy('connector', 'provider_transaction_id')
            ->havingRaw('count(*) > 1')
            ->limit($limit)
            ->get();

        return $this->sampled($rows, $limit, fn ($row): array => [
            'provider_transaction_id_present' => !empty($row->provider_transaction_id),
            'duplicate_count' => (int) $row->duplicate_count,
        ]);
    }

    private function balanceMismatches(Collection $transactions, Collection $ledgers, int $limit): array
    {
        $ledgerByKey = $ledgers->keyBy(fn ($ledger): string => (string) $ledger->idempotency_key);
        $rows = $transactions
            ->filter(function ($transaction) use ($ledgerByKey): bool {
                $ledger = $ledgerByKey->get((string) $transaction->transactionId);
                if (!$ledger) {
                    return false;
                }

                return round((float) $transaction->amount, 8) !== round((float) $ledger->amount, 8)
                    || round((float) $transaction->before_balance, 8) !== round((float) $ledger->balance_before, 8)
                    || round((float) $transaction->after_balance, 8) !== round((float) $ledger->balance_after, 8);
            })
            ->values();

        return $this->sampled($rows, $limit, fn ($transaction): array => [
            'transaction_id' => (string) $transaction->transactionId,
            'provider_transaction_id_present' => !empty($transaction->provider_transaction_id),
            'amount' => (float) $transaction->amount,
            'before_balance' => (float) $transaction->before_balance,
            'after_balance' => (float) $transaction->after_balance,
        ]);
    }

    private function callbackSuccessWithoutFinancialRecord(Collection $transactions, int $limit): array
    {
        $providerTransactionIds = $transactions->groupBy(
            fn ($row) => explode(':', (string) $row->transactionId, 3)[1] ?? ''
        )->map(fn (Collection $rows) => $rows->pluck('provider_transaction_id')->map(fn ($id) => (string) $id)->all());

        $rows = $this->sessions()
            ->where('last_callback_status', 'success')
            ->whereNotNull('last_callback_transaction_id')
            ->get()
            ->reject(fn ($session): bool => in_array((string) $session->last_callback_transaction_id, $providerTransactionIds->get($session->provider_alias, []), true))
            ->values();

        return $this->sampled($rows, $limit, fn ($session): array => [
            'session_id' => (int) $session->id,
            'provider_alias' => $session->provider_alias,
            'cms_user_id' => (int) $session->cms_user_id,
            'provider_game_id' => (string) $session->provider_game_id,
            'last_callback_command' => $session->last_callback_command,
            'last_callback_transaction_id_present' => !empty($session->last_callback_transaction_id),
        ]);
    }

    private function failedCallbacksByReason(): array
    {
        $nonCriticalStatuses = ['idempotent_replay', 'zero_amount_ignored', 'zero_amount_success'];
        $sessionRows = $this->sessions()
            ->select('last_callback_status', DB::raw('count(*) as count'))
            ->whereNotNull('last_callback_status')
            ->where('last_callback_status', '!=', 'success')
            ->groupBy('last_callback_status')
            ->get();
        $sessionRuntimeFailureCount = (int) $sessionRows
            ->reject(fn ($row): bool => in_array((string) $row->last_callback_status, $nonCriticalStatuses, true))
            ->sum('count');
        $nonCriticalCount = (int) $sessionRows
            ->filter(fn ($row): bool => in_array((string) $row->last_callback_status, $nonCriticalStatuses, true))
            ->sum('count');

        $health = json_decode((string) DB::table('site_settings')
            ->where('key', ExternalGameProviderConfig::storageKey(
                (string) config('external_games.callback_health_settings_key', 'external_games_callback_health_v1'),
                $this->providerAlias ?? ExternalGameProviderConfig::PRIMARY
            ))
            ->value('value'), true) ?: [];
        $lastFailureSummary = is_array($health['last_failure_summary'] ?? null) ? $health['last_failure_summary'] : null;
        $lastFailureReason = $lastFailureSummary['reason'] ?? $this->reasonFromCriticalError($health['last_critical_error'] ?? null);
        $healthFailureCount = (int) ($health['callback_failures_24h'] ?? 0);
        $resolvedLegacyCallbackWarningCount = $this->isResolvedLegacyCallbackWarning($lastFailureSummary, $lastFailureReason)
            ? $healthFailureCount
            : 0;
        $nonFinancialCallbackNoiseCount = $this->isNonFinancialCallbackHealthNoise($lastFailureSummary, $lastFailureReason)
            ? min(1, $healthFailureCount)
            : 0;
        $testSmokeNoiseCount = ($this->isSmokeSummary($lastFailureSummary) ? $healthFailureCount : 0)
            + $resolvedLegacyCallbackWarningCount;
        $nonBlockingHealthFailureCount = $testSmokeNoiseCount + $nonFinancialCallbackNoiseCount;
        $currentRuntimeFailureCount = max(0, $sessionRuntimeFailureCount + $healthFailureCount - $nonBlockingHealthFailureCount);

        return [
            'count' => $currentRuntimeFailureCount,
            'current_runtime_failure_count' => $currentRuntimeFailureCount,
            'historical_test_smoke_count' => $testSmokeNoiseCount,
            'sample' => [
                'session_status_counts' => $sessionRows->map(fn ($row): array => [
                    'reason' => $row->last_callback_status,
                    'count' => (int) $row->count,
                ])->values()->all(),
                'non_critical_session_status_count' => $nonCriticalCount,
                'health_failures_24h' => $healthFailureCount,
                'health_last_failure_reason' => $lastFailureReason,
                'test_smoke_noise_24h' => $testSmokeNoiseCount,
                'resolved_legacy_callback_warning_count' => $resolvedLegacyCallbackWarningCount,
                'non_financial_callback_noise_count' => $nonFinancialCallbackNoiseCount,
                'last_critical_error' => $health['last_critical_error'] ?? null,
                'last_failure_summary' => $lastFailureSummary,
                'last_callback_summary' => $health['last_callback_summary'] ?? null,
            ],
        ];
    }

    private function reasonFromCriticalError(?string $message): ?string
    {
        $message = strtolower(trim((string) $message));
        if ($message === '') {
            return null;
        }

        if (str_contains($message, 'credential validation failed')) {
            return 'invalid_provider_credentials';
        }

        if (str_contains($message, 'missing transaction_id')) {
            return 'missing_transaction_id';
        }

        if (str_contains($message, 'zero amount')) {
            return 'zero_amount_ignored';
        }

        if (str_contains($message, 'session')) {
            return 'session_resolution_failure';
        }

        return 'unknown';
    }

    private function isSmokeSummary(?array $summary): bool
    {
        if ($summary === null) {
            return false;
        }

        $transactionId = strtolower((string) ($summary['transaction_id'] ?? ''));
        $userId = strtolower((string) ($summary['user_id'] ?? ''));

        return str_contains($transactionId, 'smoke')
            || str_contains($transactionId, 'invalid')
            || str_contains($userId, 'smoke');
    }

    private function isResolvedLegacyCallbackWarning(?array $summary, ?string $reason): bool
    {
        if ($summary === null || $reason !== 'session_not_resolved') {
            return false;
        }

        $observedAt = trim((string) ($summary['observed_at'] ?? ''));
        if ($observedAt === '') {
            return false;
        }

        return $this->financialRows('cms_user_transactions', 'transactionId')
            ->where('created_at', '>', $observedAt)
            ->exists();
    }

    private function isNonFinancialCallbackHealthNoise(?array $summary, ?string $reason): bool
    {
        if ($summary === null || $reason !== 'session_not_resolved') {
            return false;
        }

        $transactionId = trim((string) ($summary['transaction_id'] ?? ''));
        if ($transactionId === '') {
            return false;
        }

        if ($this->providerTransactionHasFinancialRecord($transactionId)) {
            return false;
        }

        if ($this->hasActiveSessionForCallbackSummary($summary)) {
            return false;
        }

        return true;
    }

    private function providerTransactionHasFinancialRecord(string $providerTransactionId): bool
    {
        if (Schema::hasTable('cms_user_transactions')) {
            $hasTransaction = $this->financialRows('cms_user_transactions', 'transactionId')
                ->where(function ($query) use ($providerTransactionId): void {
                    $query->where('provider_transaction_id', $providerTransactionId)
                        ->orWhere('transactionId', 'like', '%:' . $providerTransactionId);
                })
                ->exists();

            if ($hasTransaction) {
                return true;
            }
        }

        if (!Schema::hasTable('wallet_ledger_entries')) {
            return false;
        }

        return $this->financialRows('wallet_ledger_entries', 'idempotency_key')
            ->where(function ($query) use ($providerTransactionId): void {
                $query->where('idempotency_key', 'like', '%:' . $providerTransactionId)
                    ->orWhere('source_reference', $providerTransactionId)
                    ->orWhere('meta', 'like', '%"provider_transaction_id":"' . $providerTransactionId . '"%');
            })
            ->exists();
    }

    private function hasActiveSessionForCallbackSummary(array $summary): bool
    {
        if (!Schema::hasTable('external_game_sessions')) {
            return false;
        }

        $userId = (int) ($summary['user_id'] ?? $summary['cms_user_id'] ?? 0);
        if ($userId <= 0) {
            return false;
        }

        $query = $this->sessions()
            ->where('cms_user_id', $userId)
            ->where('status', 'launched')
            ->where(function ($expires): void {
                $expires->whereNull('expires_at')->orWhere('expires_at', '>=', now());
            });

        $cmsGameId = (int) ($summary['cms_game_id'] ?? $summary['game_id'] ?? 0);
        $providerGameId = trim((string) ($summary['provider_game_id'] ?? ''));
        if ($cmsGameId > 0 || $providerGameId !== '') {
            $query->where(function ($game) use ($cmsGameId, $providerGameId): void {
                if ($cmsGameId > 0) {
                    $game->orWhere('cms_game_id', $cmsGameId);
                }

                if ($providerGameId !== '') {
                    $game->orWhere('provider_game_id', $providerGameId);
                }
            });
        }

        return $query->exists();
    }

    private function creditGuardClassifications(): array
    {
        $statuses = ['orphan_win', 'orphan_refund', 'refund_exceeds_debit'];
        $rows = $this->sessions()
            ->select('last_callback_status', DB::raw('count(*) as count'))
            ->whereIn('last_callback_status', $statuses)
            ->groupBy('last_callback_status')
            ->get()
            ->keyBy('last_callback_status');

        return [
            'count' => (int) $rows->sum('count'),
            'sample' => collect($statuses)
                ->map(fn (string $status): array => [
                    'reason' => $status,
                    'count' => (int) ($rows->get($status)->count ?? 0),
                ])
                ->values()
                ->all(),
        ];
    }

    private function activeSessionAnomalies(int $limit): array
    {
        $duplicateRows = $this->sessions()
            ->select('provider_alias', 'cms_user_id', 'root_user_id', 'provider_game_id', DB::raw('count(*) as active_count'))
            ->where('status', 'launched')
            ->where(function ($expires): void {
                $expires->whereNull('expires_at')->orWhere('expires_at', '>=', now());
            })
            ->groupBy('provider_alias', 'cms_user_id', 'root_user_id', 'provider_game_id')
            ->havingRaw('count(*) > 1')
            ->limit($limit)
            ->get();

        $expiredRows = $this->sessions()
            ->leftJoin('cms_users', 'cms_users.id', '=', 'external_game_sessions.cms_user_id')
            ->leftJoin('cms_games', 'cms_games.id', '=', 'external_game_sessions.cms_game_id')
            ->select(
                'external_game_sessions.*',
                'cms_users.username as cms_username',
                'cms_users.email as cms_user_email',
                'cms_users.role as cms_user_role',
                'cms_games.name as cms_game_name'
            )
            ->where('external_game_sessions.status', 'launched')
            ->whereNotNull('external_game_sessions.expires_at')
            ->where('external_game_sessions.expires_at', '<', now())
            ->get();
        [$testSmokeExpiredRows, $nonTestExpiredRows] = $expiredRows
            ->partition(fn ($row): bool => $this->isTestSmokeSession($row));
        [$expiredLaunchOnlyRows, $remainingExpiredRows] = $nonTestExpiredRows
            ->partition(fn ($row): bool => $this->isExpiredLaunchOnlySession($row));
        [$expiredFinanceObservedRows, $unresolvedExpiredRows] = $remainingExpiredRows
            ->partition(fn ($row): bool => $this->isExpiredFinanceObservedSession($row));
        $expiredNonBlockingRows = $expiredLaunchOnlyRows->merge($expiredFinanceObservedRows)->values();

        return [
            'count' => $duplicateRows->count() + $unresolvedExpiredRows->count(),
            'historical_test_smoke_count' => $testSmokeExpiredRows->count(),
            'expired_launch_only_count' => $expiredLaunchOnlyRows->count(),
            'expired_non_blocking_count' => $expiredNonBlockingRows->count(),
            'sample' => [
                'duplicate_active_sessions' => $duplicateRows->map(fn ($row): array => [
                    'provider_alias' => $row->provider_alias,
                    'cms_user_id' => (int) $row->cms_user_id,
                    'root_user_id' => (int) $row->root_user_id,
                    'provider_game_id' => (string) $row->provider_game_id,
                    'active_count' => (int) $row->active_count,
                ])->values()->all(),
                'expired_launched_sessions' => $unresolvedExpiredRows->take($limit)->map(fn ($row): array => [
                    'session_id' => (int) $row->id,
                    'provider_alias' => $row->provider_alias,
                    'cms_user_id' => (int) $row->cms_user_id,
                    'provider_game_id' => (string) $row->provider_game_id,
                    'expires_at' => $row->expires_at,
                ])->values()->all(),
                'expired_launch_only_sessions' => $expiredLaunchOnlyRows->take($limit)->map(fn ($row): array => [
                    'session_id' => (int) $row->id,
                    'provider_alias' => $row->provider_alias,
                    'cms_user_id' => (int) $row->cms_user_id,
                    'provider_game_id' => (string) $row->provider_game_id,
                    'expires_at' => $row->expires_at,
                    'classification' => 'expired_no_finance_activity',
                ])->values()->all(),
                'expired_non_blocking_sessions' => $expiredNonBlockingRows->take($limit)->map(fn ($row): array => [
                    'session_id' => (int) $row->id,
                    'provider_alias' => $row->provider_alias,
                    'cms_user_id' => (int) $row->cms_user_id,
                    'provider_game_id' => (string) $row->provider_game_id,
                    'expires_at' => $row->expires_at,
                    'classification' => $this->isExpiredLaunchOnlySession($row)
                        ? 'expired_no_finance_activity'
                        : 'expired_finance_observed',
                ])->values()->all(),
                'historical_test_smoke_expired_sessions' => $testSmokeExpiredRows->take($limit)->map(fn ($row): array => [
                    'session_id' => (int) $row->id,
                    'provider_alias' => $row->provider_alias,
                    'cms_user_id' => (int) $row->cms_user_id,
                    'username' => (string) $row->cms_username,
                    'provider_game_id' => (string) $row->provider_game_id,
                    'expires_at' => $row->expires_at,
                    'classification' => $this->historicalTestSmokeClassification($row),
                ])->values()->all(),
            ],
        ];
    }

    private function isTestSmokeSession(object $session): bool
    {
        $haystack = strtolower(implode(' ', array_filter([
            (string) ($session->cms_username ?? ''),
            (string) ($session->cms_user_email ?? ''),
            (string) ($session->cms_user_role ?? ''),
            (string) ($session->cms_game_name ?? ''),
            (string) ($session->provider_launch_reference ?? ''),
            (string) ($session->failure_reason ?? ''),
            json_encode(json_decode((string) ($session->launch_payload ?? ''), true) ?: (string) ($session->launch_payload ?? '')),
            json_encode(json_decode((string) ($session->playability_snapshot ?? ''), true) ?: (string) ($session->playability_snapshot ?? '')),
        ])));

        foreach (['admin', 'external_live_canary', 'canary', 'smoke', 'test_user', 'test-user', 'test_smoke'] as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function historicalTestSmokeClassification(object $session): string
    {
        $role = strtolower((string) ($session->cms_user_role ?? ''));

        if (str_contains($role, 'admin') && $this->isExpiredLaunchOnlySession($session)) {
            return 'expired_admin_test_no_finance';
        }

        return 'historical_test_smoke';
    }

    private function isExpiredLaunchOnlySession(object $session): bool
    {
        if (!empty($session->last_callback_at) || !empty($session->last_callback_transaction_id)) {
            return false;
        }

        if (Schema::hasTable('wallet_ledger_entries')) {
            $sessionId = (int) $session->id;
            $hasSessionLedger = $this->financialRows('wallet_ledger_entries', 'idempotency_key', $session->provider_alias)
                ->where('cms_user_id', (int) $session->cms_user_id)
                ->where('meta', 'like', '%external_game_session_id%')
                ->where('meta', 'like', '%' . $sessionId . '%')
                ->exists();

            if ($hasSessionLedger) {
                return false;
            }
        }

        if (Schema::hasTable('cms_user_transactions') && !empty($session->cms_game_id) && !empty($session->cms_user_id)) {
            $query = $this->financialRows('cms_user_transactions', 'transactionId', $session->provider_alias)
                ->where('cms_user_id', (int) $session->cms_user_id)
                ->where('game_id', (int) $session->cms_game_id);

            if (!empty($session->launched_at)) {
                $query->where('created_at', '>=', $session->launched_at);
            }

            if ($query->exists()) {
                return false;
            }
        }

        return true;
    }

    private function isExpiredFinanceObservedSession(object $session): bool
    {
        if (!in_array((string) ($session->last_callback_status ?? ''), ['success', 'zero_amount_success'], true)) {
            return false;
        }

        if (!Schema::hasTable('cms_user_transactions') || !Schema::hasTable('wallet_ledger_entries')) {
            return false;
        }

        if (!empty($session->last_callback_transaction_id)) {
            $providerTransactionId = (string) $session->last_callback_transaction_id;
            $hasTransaction = $this->financialRows('cms_user_transactions', 'transactionId', $session->provider_alias)
                ->where(function ($query) use ($providerTransactionId): void {
                    $query->where('provider_transaction_id', $providerTransactionId)
                        ->orWhere('transactionId', 'like', '%:' . $providerTransactionId);
                })
                ->exists();

            if ($hasTransaction) {
                return true;
            }
        }

        $sessionId = (int) $session->id;
        if ($sessionId > 0) {
            return $this->financialRows('wallet_ledger_entries', 'idempotency_key', $session->provider_alias)
                ->where('meta', 'like', '%external_game_session_id%')
                ->where('meta', 'like', '%' . $sessionId . '%')
                ->exists();
        }

        return false;
    }

    private function providerActivity(Collection $transactions, int $limit): array
    {
        if (!Schema::hasTable('cms_games')) {
            return [
                'count' => 0,
                'sample' => [],
            ];
        }

        $games = DB::table('cms_games')
            ->whereIn('id', $transactions->pluck('game_id')->filter()->unique()->values()->all())
            ->get()
            ->keyBy('id');
        $providers = [];

        foreach ($transactions as $transaction) {
            $gameRow = $games->get($transaction->game_id);
            $game = new \App\Models\CmsGame([
                'type_name' => $gameRow->type_name ?? null,
                'extra_parameters' => $gameRow->extra_parameters ?? null,
            ]);
            $alias = explode(':', (string) $transaction->transactionId, 3)[1] ?? ExternalGameProviderConfig::PRIMARY;
            $identity = $this->providerRollout->providerIdentity($game, $alias);
            $key = $identity['key'];
            $providers[$key] ??= [
                'provider_key' => $key,
                'provider_name' => $identity['name'],
                'bet_total' => 0.0,
                'win_total' => 0.0,
                'refund_total' => 0.0,
                'transaction_count' => 0,
                'last_successful_activity' => null,
            ];

            $amount = (float) $transaction->amount;
            $eventType = (string) $transaction->event_type;
            if ($eventType === 'external_games_transaction_bet_debit' || str_contains((string) $transaction->transactionId, ':transaction_bet:')) {
                $providers[$key]['bet_total'] += abs($amount);
            } elseif ($eventType === 'external_games_transaction_win_credit' || str_contains((string) $transaction->transactionId, ':transaction_win:')) {
                $providers[$key]['win_total'] += abs($amount);
            } elseif ($eventType === 'external_games_refund_credit' || str_contains((string) $transaction->transactionId, ':refund:')) {
                $providers[$key]['refund_total'] += abs($amount);
            }

            $providers[$key]['transaction_count']++;
            $providers[$key]['last_successful_activity'] = max(
                (string) ($providers[$key]['last_successful_activity'] ?? ''),
                (string) ($transaction->created_at ?? '')
            ) ?: null;
        }

        return [
            'count' => count($providers),
            'sample' => collect($providers)
                ->sortByDesc('transaction_count')
                ->take($limit)
                ->values()
                ->all(),
        ];
    }

    private function sampled(Collection $rows, int $limit, callable $map): array
    {
        return [
            'count' => $rows->count(),
            'sample' => $rows->take($limit)->map($map)->values()->all(),
        ];
    }
}
