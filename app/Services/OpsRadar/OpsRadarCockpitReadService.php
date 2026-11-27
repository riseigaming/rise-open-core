<?php

namespace App\Services\OpsRadar;

use App\Enum\TransactionStatusEnum;
use App\Services\ExternalGames\ExternalGameProviderConfig;
use App\Services\ExternalGames\ExternalGamesHealthSummaryService;
use App\Services\Finance\PrelaunchFinanceWriteGate;
use App\Services\Payments\NovaDepositService;
use Brick\Math\BigDecimal;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class OpsRadarCockpitReadService
{
    public const STATES = ['observed', 'applied', 'pending', 'protected', 'failed', 'unknown'];
    public const SOURCES = ['txn', 'sportsbook', 'round', 'chess', 'session', 'audit'];
    private const LIMIT = 30;

    public function __construct(
        private readonly ExternalGamesHealthSummaryService $health,
        private readonly PrelaunchFinanceWriteGate $writes,
    ) {
    }

    public function cockpit(array $filters, bool $providers, bool $finance, bool $admin): array
    {
        $aliases = $providers ? array_keys(ExternalGameProviderConfig::providers()) : [];
        $provider = $filters['provider'] ?? null;
        [$source, $id] = isset($filters['reference']) ? explode(':', $filters['reference']) : [null, null];
        $localActions = $this->localActionsAvailable();
        $rows = collect();
        if ($providers && ($source === null || $source === 'session')) {
            $rows = $rows->merge($this->sessions($provider ? array_values(array_intersect([$provider], $aliases)) : $aliases, $id, $localActions && $finance));
        }
        if ($finance) {
            $rows = $rows->merge($this->financeRows($source, $id, $provider, $localActions));
        }
        if ($admin && $provider === null && ($source === null || $source === 'audit')) {
            $audit = $this->sample(DB::table('cms_sensitive_mutation_events')
                ->whereIn('event_type', ['catalog.provider.saved', 'catalog.game.saved', 'catalog.country_policy.saved',
                    'external_games.controls.changed', 'external_games.rollout.changed', 'external_games.catalog.imported']), $id);
            $rows = $rows->merge($audit->map(fn ($r) => $this->row('audit', $r, 'observed', $r->event_type)));
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'sample_limit' => self::LIMIT,
            'providers' => array_map(fn ($alias) => $this->provider($alias, $localActions && $finance), $provider ? array_values(array_intersect([$provider], $aliases)) : $aliases),
            'rows' => $rows->when(isset($filters['state']), fn ($list) => $list->where('state', $filters['state']))
                ->sortByDesc('time')->values()->all(),
            'system' => [
                'database' => 'reachable',
                'public_maintenance' => (bool) config('cms_public.maintenance.enabled'),
                'finance_control' => $finance ? $this->financeControl() : 'unknown',
                'global' => $admin ? [
                    'published' => DB::table('cms_content_pages')->where('published', true)->count(),
                    'draft' => DB::table('cms_content_pages')->where('published', false)->count(),
                    'accent' => \App\Models\CmsThemeSetting::normalizeAccentPreset(DB::table('cms_theme_settings')
                        ->where('theme_key', 'global')->value('accent_preset')),
                    'url' => route('cms.admin.content-pages.index'),
                ] : null,
            ],
        ];
    }

    private function provider(string $alias, bool $localActions): array
    {
        $summary = $this->health->summary($alias);
        $lastSync = $summary['last_sync'];
        if (!is_string($lastSync) || !preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}\z/', $lastSync)) {
            $lastSync = null;
        }
        $state = match (true) {
            !ExternalGameProviderConfig::enabled($alias) => 'disabled',
            filled($summary['last_critical_error']) || $summary['callback_failures_24h'] > 0 => 'failure_observed',
            $lastSync !== null || $summary['launches_24h'] > 0 || $summary['callbacks_24h'] > 0 => 'activity_observed',
            default => 'unknown',
        };
        return [
            'alias' => $alias, 'state' => $state, 'games' => $summary['imported_game_count'],
            'launches_24h' => $summary['launches_24h'], 'callback_failures_24h' => $summary['callback_failures_24h'],
            'replays_24h' => $summary['idempotent_replays_24h'], 'last_sync' => $lastSync,
            'url' => $localActions ? route('cms.admin.externalGames.edit', ['provider_alias' => $alias]) : null,
        ];
    }

    private function sessions(array $aliases, ?string $id, bool $localActions): Collection
    {
        return $this->sample(DB::table('external_game_sessions')->whereIn('provider_alias', $aliases)
            ->select('id', 'provider_alias', 'status', 'last_callback_status', 'updated_at'), $id)
            ->map(function ($r) use ($localActions) {
                [$state, $reason] = match (true) {
                    $r->status === 'failed' => ['failed', 'launch_failed'],
                    $r->last_callback_status === 'failed' => ['failed', 'callback_failure_recorded'],
                    $r->last_callback_status === 'idempotent_replay' => ['protected', 'callback_replay_recorded'],
                    in_array($r->last_callback_status, ['success', 'zero_amount_ignored', 'zero_amount_success'], true)
                        => ['observed', 'callback_observed'],
                    filled($r->last_callback_status) => ['observed', 'callback_other_outcome'],
                    $r->status === 'launched' => ['observed', 'session_launched'],
                    default => ['unknown', 'session_state_unconfirmed'],
                };
                return $this->row('session', $r, $state, $reason, $r->provider_alias,
                    $localActions ? route('cms.admin.externalGames.edit', ['provider_alias' => $r->provider_alias]) : null);
            });
    }

    private function financeRows(?string $source, ?string $id, ?string $provider, bool $localActions): Collection
    {
        $txQuery = DB::table('cms_user_transactions');
        if ($provider === 'nova') {
            $txQuery->where('event_type', NovaDepositService::EVENT);
        } elseif ($provider !== null) {
            $txQuery->where('transactionId', 'like', addcslashes(ExternalGameProviderConfig::transactionPrefix($provider), '\\%_') . '%');
        }
        $transactions = $source === null || $source === 'txn' ? $this->sample($txQuery, $id) : collect();

        $sports = collect();
        $rounds = collect();
        $chess = collect();
        if ($provider === null) {
            if ($source === null || $source === 'sportsbook') {
                $sports = $this->sample(DB::table('betting_acceptances')->select('id', 'acceptance_ref', 'status', 'stake_amount', 'currency', 'cms_user_id', 'context', 'updated_at'), $id);
            }
            if ($source === null || $source === 'round') {
                $rounds = $this->sample(DB::table('internal_game_rounds')
                    ->select('id', 'status', 'debit_transaction_id', 'win_transaction_id', 'refund_transaction_id', 'updated_at'), $id);
            }
            if ($source === null || $source === 'chess') {
                $chess = $this->sample(DB::table('chess_wallet_reservations')
                    ->select('id', 'status', 'reserve_transaction_id', 'release_transaction_id', 'settlement_transaction_id', 'updated_at'), $id);
            }
        }

        $roundEffects = ['debit_transaction_id', 'win_transaction_id', 'refund_transaction_id'];
        $chessEffects = ['reserve_transaction_id', 'release_transaction_id', 'settlement_transaction_id'];
        $ids = $rounds->flatMap(fn ($r) => $this->effectIds($r, $roundEffects))
            ->merge($chess->flatMap(fn ($r) => $this->effectIds($r, $chessEffects)))->unique()->values();
        $effects = DB::table('cms_user_transactions')->whereIn('id', $ids)->get();
        $settlements = DB::table('betting_settlements')->whereIn('betting_acceptance_id', $sports->pluck('id'))
            ->get(['betting_acceptance_id', 'status'])->keyBy('betting_acceptance_id');
        $sportsEffects = DB::table('cms_user_transactions')->whereIn('round_id', $sports->pluck('acceptance_ref'))
            ->whereIn('event_type', ['sportsbook_acceptance', 'sportsbook_acceptance_refund'])->get();
        $allTransactions = $transactions->concat($effects)->concat($sportsEffects)->keyBy('id');
        $evidence = $this->ledgerEvidence($allTransactions);
        $nova = $transactions->where('event_type', NovaDepositService::EVENT);
        $claims = DB::table('crypto_settlement_claims')->where(function ($q) use ($nova) {
            $q->whereIn('cms_transaction_id', $nova->pluck('id'))
                ->orWhereIn('session_id', $nova->pluck('provider_transaction_id')->filter()->unique()->values());
        })->where('asset', 'BTC')->where('network', 'BTC')->get();
        $claimOwners = DB::table('cms_user_transactions')->whereIn('id', $claims->pluck('cms_transaction_id'))
            ->where('event_type', NovaDepositService::EVENT)->pluck('crypto_deposit_context', 'id');

        $rows = $transactions->map(fn ($t) => $this->transaction($t, $evidence[$t->id], $claims, $claimOwners, $localActions));
        $rows = $rows->merge($sports->map(fn ($r) => $this->sportsbook($r,
            $sportsEffects->where('round_id', $r->acceptance_ref), $evidence, $settlements->get($r->id)?->status, $localActions)));
        $rows = $rows->merge($rounds->map(fn ($r) => $this->game('round', $r, $roundEffects, $evidence,
            $localActions && $r->debit_transaction_id ? route('cms.admin.transactions.manualOverride.create', ['transaction_id' => $r->debit_transaction_id]) : null)));
        return $rows->merge($chess->map(fn ($r) => $this->game('chess', $r, $chessEffects, $evidence,
            route('cms.admin.betting.liveChessCockpit'))));
    }

    private function ledgerEvidence(Collection $transactions): array
    {
        $ledgers = DB::table('wallet_ledger_entries')->where('source_type', 'cms_user_transaction')
            ->whereIn('source_reference', $transactions->pluck('transactionId'))->get()->groupBy('source_reference');
        $overrides = DB::table('finance_manual_overrides')->whereIn('cms_user_transaction_id', $transactions->keys())
            ->pluck('operation_id', 'cms_user_transaction_id');
        $overrideLedgers = DB::table('wallet_ledger_entries')->where('source_type', 'finance_manual_override')
            ->whereIn('source_reference', $overrides->values())->get()->groupBy('source_reference');
        $chessLedgers = $this->chessLedgers($transactions);

        $wallets = DB::table('wallet_accounts')->whereIn('id', $transactions->map(
            fn ($t) => $t->settled_wallet_account_id ?: $t->source_wallet_account_id
        )->filter()->unique()->values())->get(['id', 'cms_user_id', 'asset'])->keyBy('id');

        return $transactions->mapWithKeys(function ($t) use ($ledgers, $overrides, $overrideLedgers, $wallets, $chessLedgers) {
            $entries = str_starts_with((string) $t->event_type, 'chess_')
                ? $chessLedgers->get($t->id, collect())
                : $ledgers->get($t->transactionId, collect())
                    ->concat($overrideLedgers->get($overrides->get($t->id, ''), collect()));
            $economic = $entries->filter(fn ($l) => BigDecimal::of($l->amount)->isPositive());
            $match = $economic->count() === 1 && (int) $t->status === TransactionStatusEnum::Success
                && $this->matchesLedger($t, $economic->first(), $wallets->get($t->settled_wallet_account_id ?: $t->source_wallet_account_id));
            return [$t->id => ['matched' => $match, 'economic_count' => $economic->count(),
                'ledger_id' => $match ? $economic->first()->id : null,
                'type' => $match ? $economic->first()->entry_type : null,
                'direction' => $match ? $economic->first()->direction : null,
                'amount' => $match ? (string) BigDecimal::of($economic->first()->amount)->stripTrailingZeros() : null,
                'asset' => $match && preg_match('/\A[A-Z0-9]{2,16}\z/', $economic->first()->asset) ? $economic->first()->asset : null]];
        })->all();
    }

    private function chessLedgers(Collection $transactions): Collection
    {
        $transactions = $transactions->filter(fn ($t) => str_starts_with((string) $t->event_type, 'chess_'));
        if ($transactions->isEmpty()) {
            return collect();
        }
        $columns = ['reserve_transaction_id', 'release_transaction_id', 'settlement_transaction_id'];
        $reservations = DB::table('chess_wallet_reservations')->where(function ($q) use ($transactions, $columns) {
            foreach ($columns as $column) {
                $q->orWhereIn($column, $transactions->keys());
            }
        })->get();
        $bindings = collect();
        foreach ($reservations as $r) {
            foreach ($columns as $column) {
                if ($r->$column) {
                    $bindings->push(['id' => $r->$column, 'reservation' => $r, 'column' => $column]);
                }
            }
        }
        $bindings = $bindings->groupBy('id');
        $reserves = DB::table('cms_user_transactions')->whereIn('id', $reservations->pluck('reserve_transaction_id'))
            ->get()->keyBy('id');
        $ledgers = DB::table('wallet_ledger_entries')->where('source_type', 'chess_reservation')
            ->whereIn('source_reference', $reservations->pluck('cms_reservation_id'))
            ->whereIn('idempotency_key', $transactions->pluck('transactionId'))->get();

        return $transactions->mapWithKeys(function ($t) use ($bindings, $ledgers, $reserves) {
            $bound = $bindings->get($t->id, collect());
            if ($bound->count() !== 1) {
                return [$t->id => collect()];
            }
            $r = $bound->first()['reservation'];
            [$type, $direction, $event] = match ($bound->first()['column']) {
                'reserve_transaction_id' => ['chess_reserve', 'debit', 'reserve'],
                'release_transaction_id' => ['chess_release', 'credit', 'release'],
                default => match ($t->event_type) {
                    'chess_settlement_win' => ['chess_settlement_win', 'credit', 'settlement'],
                    'chess_settlement_void' => ['chess_settlement_void', 'credit', 'void'],
                    default => [null, null, null],
                },
            };
            $wallet = data_get(json_decode((string) $r->request_payload, true), 'wallet_account_id');
            $reserve = $reserves->get($r->reserve_transaction_id);
            $amount = $type === 'chess_settlement_win' ? $r->payout_amount : $r->stake_amount;
            if ($type === null || $t->event_type !== $type || !$wallet || !$reserve || $amount === null
                || $t->transactionId !== 'chess_' . $event . '_' . str_replace('-', '_', $r->cms_reservation_id)
                || $t->round_id !== $r->cms_reservation_id || (int) $t->cms_user_id !== (int) $r->cms_user_id
                || (int) $t->source_wallet_account_id !== (int) $wallet
                || ($t->settled_wallet_account_id && (int) $t->settled_wallet_account_id !== (int) $wallet)
                || $t->currency !== $r->currency || ($t->transaction_asset && $t->transaction_asset !== $r->currency)
                || $reserve->event_type !== 'chess_reserve' || (int) $reserve->status !== TransactionStatusEnum::Success
                || $reserve->transactionId !== 'chess_reserve_' . str_replace('-', '_', $r->cms_reservation_id)
                || $reserve->ref_transaction_id !== $r->placement_intent_id
                || !BigDecimal::of($reserve->exact_amount ?? $reserve->amount)->isEqualTo($r->stake_amount)
                || $reserve->round_id !== $r->cms_reservation_id
                || (int) $reserve->cms_user_id !== (int) $r->cms_user_id
                || (int) $reserve->source_wallet_account_id !== (int) $wallet || $reserve->currency !== $r->currency
                || $t->ref_transaction_id !== ($event === 'reserve' ? $r->placement_intent_id : $reserve->transactionId)
                || !BigDecimal::of($t->exact_amount ?? $t->amount)->isEqualTo($amount)) {
                return [$t->id => collect()];
            }
            return [$t->id => $ledgers->filter(fn ($l) => $l->source_reference === $r->cms_reservation_id
                && $l->idempotency_key === $t->transactionId && $l->entry_type === $type && $l->direction === $direction)];
        });
    }

    private function matchesLedger(object $t, object $l, ?object $account): bool
    {
        $wallet = $t->settled_wallet_account_id ?: $t->source_wallet_account_id;
        $asset = $t->transaction_asset ?: $t->currency;
        $before = $t->exact_before_balance ?? $t->before_balance;
        $after = $t->exact_after_balance ?? $t->after_balance;
        if (!$account || $asset !== $account->asset || (int) $account->cms_user_id !== (int) $l->cms_user_id
            || $before === null || $after === null || $l->status !== 'applied'
            || (int) $wallet !== (int) $l->wallet_account_id || (int) $t->cms_user_id !== (int) $l->cms_user_id
            || $asset !== $l->asset || !in_array($l->direction, ['debit', 'credit'], true)) {
            return false;
        }
        $amount = BigDecimal::of($t->exact_amount ?? $t->amount);
        $delta = $l->direction === 'credit' ? $amount : $amount->negated();
        return $amount->isEqualTo($l->amount) && BigDecimal::of($before)->isEqualTo($l->balance_before)
            && BigDecimal::of($after)->isEqualTo($l->balance_after)
            && BigDecimal::of($before)->plus($delta)->isEqualTo($after);
    }

    private function transaction(object $t, array $evidence, Collection $claims, Collection $claimOwners, bool $localActions): array
    {
        [$state, $reason] = match (true) {
            $evidence['matched'] => ['applied', 'transaction_ledger_match'],
            $evidence['economic_count'] > 0 => ['unknown', 'transaction_ledger_mismatch'],
            (int) $t->status === TransactionStatusEnum::Pending => ['pending', 'transaction_pending'],
            in_array((int) $t->status, [TransactionStatusEnum::Failed, TransactionStatusEnum::Rejected], true)
                => ['failed', 'transaction_failed_or_rejected'],
            default => ['unknown', 'economic_ledger_unverified'],
        };
        $provider = null;
        $url = $localActions ? route('cms.admin.transactions.manualOverride.create', ['transaction_id' => $t->id]) : null;
        $duplicateObservation = null;
        if ($t->event_type === NovaDepositService::EVENT) {
            $provider = 'nova';
            $url = route('cms.admin.finance.nova', ['transaction_id' => $t->id]);
            $snapshot = json_decode((string) $t->execution_context_snapshot, true) ?: [];
            $context = json_decode((string) $t->crypto_deposit_context, true) ?: [];
            $tenant = $context['tenant_binding'] ?? null;
            $matchingClaims = $claims->filter(fn ($c) => is_string($tenant) && $tenant !== ''
                && $c->session_id === $t->provider_transaction_id
                && $c->provider_reference === ($snapshot['provider_reference'] ?? null)
                && $c->network_mode === ($context['network_mode'] ?? null)
                && data_get(json_decode((string) $claimOwners->get($c->cms_transaction_id), true), 'tenant_binding') === $tenant);
            $claim = $matchingClaims->count() === 1 ? $matchingClaims->first() : null;
            $duplicateObservation = 'attempt_not_recorded';
            if ($claim && (int) $claim->cms_transaction_id !== (int) $t->id && $evidence['economic_count'] === 0) {
                [$state, $reason] = ['protected', 'nova_receipt_bound_to_other_intent'];
                $duplicateObservation = 'ownership_conflict';
            } elseif ($evidence['matched'] && $evidence['type'] === 'crypto_deposit_credited'
                && $evidence['direction'] === 'credit' && $claim && (int) $claim->cms_transaction_id === (int) $t->id
                && BigDecimal::of($claim->exact_amount)->isEqualTo($t->exact_amount ?? $t->amount)
                && $claim->asset === $t->transaction_asset) {
                $reason = 'nova_credit_and_receipt_claim_matched';
            } elseif ($evidence['matched'] || (int) $t->status === TransactionStatusEnum::Success) {
                [$state, $reason] = ['unknown', 'nova_credit_evidence_incomplete'];
            } elseif ($state === 'pending') {
                [$state, $reason] = match ($snapshot['status'] ?? null) {
                    'COMPLETED' => ['pending', 'nova_confirmed_credit_pending'],
                    'FAILED', 'REJECTED', 'EXPIRED' => ['failed', 'nova_gateway_failure_observed'],
                    null => ['pending', 'nova_intent_unconfirmed'],
                    default => ['pending', 'nova_intent_pending'],
                };
            }
        } elseif ($t->acceptor === 'external_games') {
            foreach (array_keys(ExternalGameProviderConfig::providers()) as $alias) {
                if (str_starts_with($t->transactionId, ExternalGameProviderConfig::transactionPrefix($alias))) {
                    $provider = $alias;
                    break;
                }
            }
        }

        return $this->row('txn', $t, $state, $reason, $provider, $url, [$t->id => $evidence])
            + ['duplicate_observation' => $duplicateObservation];
    }

    private function sportsbook(object $r, Collection $effects, array $evidence, ?string $settlement, bool $localActions): array
    {
        $debits = $effects->where('event_type', 'sportsbook_acceptance');
        $refunds = $effects->where('event_type', 'sportsbook_acceptance_refund');
        $debit = $debits->count() === 1 ? $debits->first() : null;
        $refund = $refunds->count() === 1 ? $refunds->first() : null;
        $context = json_decode((string) $r->context, true) ?: [];
        $funded = $debit && ($evidence[$debit->id]['type'] ?? null) === 'sportsbook_acceptance_debited'
            && $evidence[$debit->id]['direction'] === 'debit' && $debit->ref_transaction_id === $r->acceptance_ref
            && (int) $debit->cms_user_id === (int) $r->cms_user_id && $debit->currency === $r->currency
            && (int) $debit->source_wallet_account_id === (int) ($context['wallet_account_id'] ?? 0)
            && BigDecimal::of($debit->exact_amount ?? $debit->amount)->isEqualTo($r->stake_amount);
        $refunded = $funded && $refund && ($evidence[$refund->id]['type'] ?? null) === 'sportsbook_acceptance_refunded'
            && $evidence[$refund->id]['direction'] === 'credit' && $refund->ref_transaction_id === $debit->transactionId
            && $refund->cms_user_id === $debit->cms_user_id && $refund->currency === $debit->currency
            && $refund->source_wallet_account_id === $debit->source_wallet_account_id
            && BigDecimal::of($refund->exact_amount ?? $refund->amount)->isEqualTo($r->stake_amount);
        $decision = in_array($r->status, ['rejected', 'cancelled'], true) ? $r->status : null;
        [$state, $reason] = match (true) {
            $decision !== null && $refunded => ['applied', 'sportsbook_' . $decision . '_refund_matched'],
            $decision !== null && $effects->isNotEmpty() => ['unknown', 'sportsbook_' . $decision . '_refund_unverified'],
            $decision !== null => ['failed', 'sportsbook_' . $decision . '_no_debit_record'],
            $r->status === 'accepted' && !$funded => ['unknown', 'sportsbook_accepted_debit_unverified'],
            $r->status === 'accepted' && in_array($settlement, [null, 'pending', 'review'], true) => ['pending', 'sportsbook_funded_pending'],
            in_array($r->status, ['pending', 'held', 'suspended'], true) => ['pending', 'sportsbook_awaiting_decision'],
            $r->status === 'settled' || $settlement === 'final' => ['observed', 'sportsbook_result_recorded'],
            default => ['unknown', 'sportsbook_state_unconfirmed'],
        };
        return $this->row('sportsbook', $r, $state, $reason, null,
            $localActions ? route('cms.admin.betting.acceptances.show', ['id' => $r->id]) : null,
            array_intersect_key($evidence, array_flip($effects->pluck('id')->all())));
    }

    private function game(string $source, object $r, array $columns, array $evidence, ?string $url): array
    {
        $bound = array_intersect_key($evidence, array_flip($this->effectIds($r, $columns)));
        [$state, $reason] = match ($r->status) {
            'failed' => ['failed', 'game_failure_recorded'],
            'pending', 'created', 'reserved', 'bet_accepted', 'active', 'awaiting_player_action' => ['pending', 'game_open'],
            'refunded', 'released', 'settled_win', 'settled_loss', 'settled_void', 'settled_push', 'settled' => ['observed', 'game_' . $r->status],
            default => ['unknown', 'game_state_unconfirmed'],
        };
        return $this->row($source, $r, $state, $reason, null, $url, $bound);
    }

    private function effectIds(object $row, array $columns): array
    {
        return array_values(array_filter(array_map(fn ($column) => $row->$column, $columns)));
    }

    private function row(string $source, object $r, string $state, string $reason, ?string $provider = null,
        ?string $url = null, array $effects = []): array
    {
        return [
            'reference' => $source . ':' . $r->id, 'state' => $state, 'reason' => $reason, 'provider' => $provider,
            'time' => $r->updated_at ?? $r->created_at ?? null, 'url' => $url,
            'effects' => collect($effects)->map(fn ($e, $id) => [
                'reference' => 'txn:' . $id, 'matched' => $e['matched'],
                'ledger_reference' => $e['ledger_id'] ? 'ledger:' . $e['ledger_id'] : null,
                'amount' => $e['amount'], 'asset' => $e['asset'], 'direction' => $e['direction'],
            ])->values()->all(),
        ];
    }

    private function sample(Builder $query, ?string $id): Collection
    {
        return $query->when($id !== null, fn ($q) => $q->where('id', $id))
            ->orderByDesc('id')->limit(self::LIMIT)->get();
    }

    private function financeControl(): string
    {
        if (!$this->writes->available()) {
            return 'unknown';
        }
        $closed = DB::table('finance_reset_controls')->where('id', 1)->value('writes_closed');
        return $closed === null ? 'unknown' : ((bool) $closed ? 'paused' : 'open');
    }

    private function localActionsAvailable(): bool
    {
        try {
            $this->writes->assertWritable();
            return true;
        } catch (\RuntimeException) {
            return false;
        }
    }
}
