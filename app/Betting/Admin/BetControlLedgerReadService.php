<?php

namespace App\Betting\Admin;

use App\Betting\Acceptance\BettingAcceptanceSnapshotReadModel;
use App\Betting\Enums\BettingAcceptanceStatus;
use App\Betting\Enums\BettingSettlementStatus;
use App\Models\BettingAcceptance;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BetControlLedgerReadService
{
    private const SUPPORTED_CONTRACTS = [
        'adapter_vps_v2' => 'adapter_vps',
        'social_market_v1' => 'social_market',
    ];

    private const TERMINAL_SETTLEMENT_STATUSES = [
        BettingSettlementStatus::FINAL,
        BettingSettlementStatus::VOID,
        BettingSettlementStatus::CANCELLED,
    ];

    public function __construct(private readonly BettingAcceptanceSnapshotReadModel $snapshotReadModel)
    {
    }

    public function filters(array $query): array
    {
        return [
            'source' => trim((string) ($query['source'] ?? '')),
            'contract' => trim((string) ($query['contract'] ?? '')),
            'status' => trim((string) ($query['status'] ?? '')),
            'settlement_state' => trim((string) ($query['settlement_state'] ?? '')),
            'actionability' => trim((string) ($query['actionability'] ?? '')),
            'search' => trim((string) ($query['search'] ?? '')),
        ];
    }

    public function paginate(array $filters, int $perPage = 50): LengthAwarePaginator
    {
        if (!$this->tablesReady()) {
            return new Paginator([], 0, $perPage);
        }

        $items = $this->applyFilters($this->baseQuery(), $filters)
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        $acceptances = $items->getCollection();
        $ledgerAudit = $this->ledgerAuditFor($acceptances);

        $items->setCollection($acceptances->map(fn (BettingAcceptance $acceptance): array => $this->row($acceptance, $ledgerAudit)));

        return $items;
    }

    public function summary(): array
    {
        if (!$this->tablesReady()) {
            return [
                'total_acceptances' => 0,
                'accepted_unsettled' => 0,
                'settlements_existing' => 0,
                'manual_settlement_actionable' => 0,
                'legacy_read_only' => 0,
                'by_source' => [],
            ];
        }

        return [
            'total_acceptances' => BettingAcceptance::query()->count(),
            'accepted_unsettled' => BettingAcceptance::query()
                ->where('status', BettingAcceptanceStatus::ACCEPTED)
                ->whereDoesntHave('settlement')
                ->count(),
            'settlements_existing' => DB::table('betting_settlements')->count(),
            'manual_settlement_actionable' => $this->actionableQuery($this->baseQuery())->count(),
            'legacy_read_only' => BettingAcceptance::query()
                ->where(function (Builder $query): void {
                    $query->where('acceptance_contract_version', 'legacy_v1')
                        ->orWhere('acceptance_source', 'cms_legacy');
                })
                ->count(),
            'by_source' => DB::table('betting_acceptances')
                ->selectRaw('COALESCE(acceptance_source, ?) as source, COUNT(*) as aggregate', ['unknown'])
                ->groupBy('source')
                ->orderBy('source')
                ->pluck('aggregate', 'source')
                ->map(fn ($count): int => (int) $count)
                ->all(),
        ];
    }

    public function options(): array
    {
        if (!$this->tablesReady()) {
            return [
                'sources' => [],
                'contracts' => [],
                'statuses' => [],
                'settlement_states' => ['no_settlement', 'pending', 'settled'],
                'actionabilities' => ['actionable', 'read_only', 'settled'],
            ];
        }

        return [
            'sources' => $this->distinctValues('acceptance_source'),
            'contracts' => $this->distinctValues('acceptance_contract_version'),
            'statuses' => $this->distinctValues('status'),
            'settlement_states' => ['no_settlement', 'pending', 'settled'],
            'actionabilities' => ['actionable', 'read_only', 'settled'],
        ];
    }

    private function baseQuery(): Builder
    {
        return BettingAcceptance::query()
            ->with([
                'event',
                'market',
                'outcome',
                'quoteSelection.outcome',
                'settlement',
            ]);
    }

    private function applyFilters(Builder $query, array $filters): Builder
    {
        if ($filters['source'] !== '') {
            $query->where('acceptance_source', $filters['source']);
        }

        if ($filters['contract'] !== '') {
            $query->where('acceptance_contract_version', $filters['contract']);
        }

        if ($filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        if ($filters['search'] !== '') {
            $search = $filters['search'];
            $query->where(function (Builder $nested) use ($search): void {
                if (ctype_digit($search)) {
                    $nested->orWhere('id', (int) $search);
                }

                $nested->orWhere('acceptance_ref', 'like', '%' . $search . '%');
            });
        }

        if ($filters['settlement_state'] === 'no_settlement') {
            $query->whereDoesntHave('settlement');
        } elseif ($filters['settlement_state'] === 'pending') {
            $query->whereHas('settlement', function (Builder $settlement): void {
                $settlement->whereNotIn('status', self::TERMINAL_SETTLEMENT_STATUSES);
            });
        } elseif ($filters['settlement_state'] === 'settled') {
            $query->whereHas('settlement', function (Builder $settlement): void {
                $settlement->whereIn('status', self::TERMINAL_SETTLEMENT_STATUSES);
            });
        }

        if ($filters['actionability'] === 'actionable') {
            $this->actionableQuery($query);
        } elseif ($filters['actionability'] === 'settled') {
            $query->whereHas('settlement', function (Builder $settlement): void {
                $settlement->whereIn('status', self::TERMINAL_SETTLEMENT_STATUSES);
            });
        } elseif ($filters['actionability'] === 'read_only') {
            $query->where(function (Builder $nested): void {
                $nested->where(function (Builder $unsupported): void {
                    $unsupported->whereNotIn('acceptance_contract_version', array_keys(self::SUPPORTED_CONTRACTS))
                        ->orWhereNotIn('acceptance_source', array_values(self::SUPPORTED_CONTRACTS));
                })->orWhereHas('settlement', function (Builder $settlement): void {
                    $settlement->whereIn('status', self::TERMINAL_SETTLEMENT_STATUSES);
                });
            });
        }

        return $query;
    }

    private function actionableQuery(Builder $query): Builder
    {
        $query
            ->where(function (Builder $nested): void {
                foreach (self::SUPPORTED_CONTRACTS as $contract => $source) {
                    $nested->orWhere(function (Builder $supported) use ($contract, $source): void {
                        $supported
                            ->where('acceptance_contract_version', $contract)
                            ->where('acceptance_source', $source);
                    });
                }
            })
            ->whereIn('status', [
                BettingAcceptanceStatus::PENDING,
                BettingAcceptanceStatus::ACCEPTED,
            ]);

        if (Schema::hasColumn('betting_acceptances', 'accepted_snapshot_json')) {
            $query->whereNotNull('accepted_snapshot_json');
        }

        if (Schema::hasColumn('betting_acceptances', 'accepted_snapshot_digest')) {
            $query->whereNotNull('accepted_snapshot_digest');
        }

        return $query->where(function (Builder $nested): void {
            $nested->whereDoesntHave('settlement')
                ->orWhereHas('settlement', function (Builder $settlement): void {
                    $settlement->whereNotIn('status', self::TERMINAL_SETTLEMENT_STATUSES);
                });
        });
    }

    /**
     * @param  array<string, mixed>  $ledgerAudit
     */
    private function row(BettingAcceptance $acceptance, array $ledgerAudit = []): array
    {
        $settlement = $acceptance->settlement;
        $isSupported = $this->isSupported($acceptance);
        $hasTerminalSettlement = $settlement !== null
            && in_array((string) $settlement->status, self::TERMINAL_SETTLEMENT_STATUSES, true);
        $hasOpenSettlement = $settlement !== null && !$hasTerminalSettlement;
        $hasSnapshot = !empty($acceptance->accepted_snapshot_json)
            && (
                !Schema::hasColumn('betting_acceptances', 'accepted_snapshot_digest')
                || !empty($acceptance->accepted_snapshot_digest)
            );
        $isOpenable = $isSupported
            && !$settlement
            && $hasSnapshot
            && in_array((string) $acceptance->status, [
                BettingAcceptanceStatus::PENDING,
                BettingAcceptanceStatus::ACCEPTED,
            ], true);

        $actionability = $this->actionability($isSupported, $isOpenable, $hasOpenSettlement, $hasTerminalSettlement);

        return [
            'acceptance' => $acceptance,
            'settlement' => $settlement,
            'context_label' => $this->contextLabel($acceptance),
            'source_label' => $this->sourceLabel($acceptance),
            'contract_label' => $this->contractLabel($acceptance),
            'settlement_state' => $settlement ? (string) $settlement->status : 'no_settlement',
            'settlement_outcome' => $settlement ? (string) $settlement->result_code : '-',
            'actionability' => $actionability,
            'action_label' => $this->actionLabel($actionability),
            'action_badge' => $this->actionBadge($actionability),
            'is_openable' => $isOpenable,
            'has_open_settlement' => $hasOpenSettlement,
            'has_terminal_settlement' => $hasTerminalSettlement,
            'ledger_audit' => [
                'acceptance' => $this->acceptanceLedgerStatus($acceptance, $isSupported, $ledgerAudit),
                'settlement' => $this->settlementLedgerStatus($settlement, $ledgerAudit),
            ],
            'links' => [
                'acceptance' => route('cms.admin.betting.acceptances.show', ['id' => $acceptance->id]),
                'manual_desk' => route('cms.admin.betting.settlements.pending', ['search' => (string) $acceptance->id]),
                'settlement' => $settlement ? route('cms.admin.betting.settlements.show', ['id' => $settlement->id]) : null,
            ],
        ];
    }

    private function actionability(bool $isSupported, bool $isOpenable, bool $hasOpenSettlement, bool $hasTerminalSettlement): string
    {
        if ($hasTerminalSettlement) {
            return 'settled';
        }

        if ($isOpenable) {
            return 'actionable';
        }

        if ($isSupported && $hasOpenSettlement) {
            return 'manual_review_open';
        }

        return 'read_only';
    }

    private function actionLabel(string $actionability): string
    {
        return match ($actionability) {
            'actionable' => 'Manual desk eligible',
            'manual_review_open' => 'Review open',
            'settled' => 'Settled',
            default => 'Read-only',
        };
    }

    private function actionBadge(string $actionability): string
    {
        return match ($actionability) {
            'actionable' => 'bg-success',
            'manual_review_open' => 'bg-warning text-dark',
            'settled' => 'bg-secondary',
            default => 'bg-light text-dark border',
        };
    }

    private function isSupported(BettingAcceptance $acceptance): bool
    {
        return (self::SUPPORTED_CONTRACTS[(string) $acceptance->acceptance_contract_version] ?? null) === (string) $acceptance->acceptance_source;
    }

    /**
     * @param  Collection<int, BettingAcceptance>  $acceptances
     * @return array<string, mixed>
     */
    private function ledgerAuditFor(Collection $acceptances): array
    {
        if ($acceptances->isEmpty() || !Schema::hasTable('wallet_ledger_entries')) {
            return [
                'ledger_table_ready' => false,
                'acceptance_keys' => [],
                'settlement_keys' => [],
                'effects_by_settlement' => [],
            ];
        }

        $modernAcceptanceKeys = $acceptances
            ->filter(fn (BettingAcceptance $acceptance): bool => $this->isSupported($acceptance))
            ->mapWithKeys(fn (BettingAcceptance $acceptance): array => [
                (int) $acceptance->id => 'sportsbook_acceptance:' . (string) $acceptance->acceptance_ref,
            ]);

        $settlementIds = $acceptances
            ->map(fn (BettingAcceptance $acceptance): ?int => $acceptance->settlement ? (int) $acceptance->settlement->id : null)
            ->filter()
            ->unique()
            ->values();

        $effectsBySettlement = collect();
        if ($settlementIds->isNotEmpty() && Schema::hasTable('betting_settlement_wallet_effects')) {
            $effectsBySettlement = DB::table('betting_settlement_wallet_effects')
                ->whereIn('betting_settlement_id', $settlementIds->all())
                ->where('effect_phase', 'apply')
                ->orderBy('id')
                ->get()
                ->groupBy(fn ($effect): int => (int) $effect->betting_settlement_id);
        }

        $settlementKeys = $effectsBySettlement
            ->flatten(1)
            ->map(fn ($effect): string => 'sportsbook_settlement_apply:' . (string) $effect->effect_ref)
            ->filter(fn (string $key): bool => trim($key) !== 'sportsbook_settlement_apply:')
            ->values();

        $ledgerKeys = $modernAcceptanceKeys
            ->values()
            ->merge($settlementKeys)
            ->filter()
            ->unique()
            ->values();

        if ($ledgerKeys->isEmpty()) {
            return [
                'ledger_table_ready' => true,
                'acceptance_keys' => [],
                'settlement_keys' => [],
                'effects_by_settlement' => $effectsBySettlement,
            ];
        }

        $ledgerEntries = DB::table('wallet_ledger_entries')
            ->whereIn('idempotency_key', $ledgerKeys->all())
            ->get()
            ->groupBy(fn ($entry): string => (string) $entry->idempotency_key);

        return [
            'ledger_table_ready' => true,
            'acceptance_keys' => $modernAcceptanceKeys->all(),
            'settlement_keys' => $ledgerEntries,
            'effects_by_settlement' => $effectsBySettlement,
        ];
    }

    /**
     * @param  array<string, mixed>  $ledgerAudit
     * @return array{state: string, label: string, badge: string}
     */
    private function acceptanceLedgerStatus(BettingAcceptance $acceptance, bool $isSupported, array $ledgerAudit): array
    {
        if ($this->isLegacy($acceptance)) {
            return $this->ledgerBadge('legacy_na', 'Ledger: legacy N/A', 'bg-light text-dark border');
        }

        if (!$isSupported || !($ledgerAudit['ledger_table_ready'] ?? false)) {
            return $this->ledgerBadge('not_applicable', 'Ledger: not applicable', 'bg-light text-dark border');
        }

        $key = $ledgerAudit['acceptance_keys'][(int) $acceptance->id] ?? null;
        $entries = $key ? ($ledgerAudit['settlement_keys']->get($key) ?? collect()) : collect();
        $hasDebit = $entries->contains(fn ($entry): bool => (string) $entry->entry_type === 'sportsbook_acceptance_debited');

        if ($hasDebit) {
            return $this->ledgerBadge('debit_ok', 'Ledger: debit OK', 'bg-success');
        }

        return $this->ledgerBadge('debit_missing', 'Ledger: debit missing', 'bg-warning text-dark');
    }

    /**
     * @param  array<string, mixed>  $ledgerAudit
     * @return array{state: string, label: string, badge: string}
     */
    private function settlementLedgerStatus($settlement, array $ledgerAudit): array
    {
        if (!$settlement) {
            return $this->ledgerBadge('none_yet', 'Settlement ledger: none yet', 'bg-light text-dark border');
        }

        $resultCode = strtolower((string) $settlement->result_code);
        if (in_array($resultCode, ['lost', 'loss'], true)) {
            return $this->ledgerBadge('lost_no_wallet_effect', 'Settlement ledger: lost/no wallet effect', 'bg-light text-dark border');
        }

        $expectedType = match ($resultCode) {
            'won', 'win' => 'sportsbook_settlement_payout',
            'void', 'refunded', 'refund' => 'sportsbook_settlement_refund',
            default => null,
        };

        if (!$expectedType) {
            return $this->ledgerBadge('not_applicable', 'Settlement ledger: none yet', 'bg-light text-dark border');
        }

        if (!($ledgerAudit['ledger_table_ready'] ?? false)) {
            return $this->ledgerBadge('not_applicable', 'Settlement ledger: not applicable', 'bg-light text-dark border');
        }

        $effects = ($ledgerAudit['effects_by_settlement'] ?? collect())->get((int) $settlement->id, collect());
        foreach ($effects as $effect) {
            $key = 'sportsbook_settlement_apply:' . (string) $effect->effect_ref;
            $entries = $ledgerAudit['settlement_keys']->get($key) ?? collect();
            $hasExpectedEntry = $entries->contains(fn ($entry): bool => (string) $entry->entry_type === $expectedType);

            if ($hasExpectedEntry) {
                return $expectedType === 'sportsbook_settlement_refund'
                    ? $this->ledgerBadge('refund_ok', 'Settlement ledger: refund OK', 'bg-success')
                    : $this->ledgerBadge('payout_ok', 'Settlement ledger: payout OK', 'bg-success');
            }
        }

        return $this->ledgerBadge('missing', 'Settlement ledger: missing', 'bg-warning text-dark');
    }

    /**
     * @return array{state: string, label: string, badge: string}
     */
    private function ledgerBadge(string $state, string $label, string $badge): array
    {
        return [
            'state' => $state,
            'label' => $label,
            'badge' => $badge,
        ];
    }

    private function isLegacy(BettingAcceptance $acceptance): bool
    {
        return (string) $acceptance->acceptance_contract_version === 'legacy_v1'
            || (string) $acceptance->acceptance_source === 'cms_legacy';
    }

    private function contextLabel(BettingAcceptance $acceptance): string
    {
        $event = $this->snapshotReadModel->eventLabel($acceptance);
        $market = $this->snapshotReadModel->marketLabel($acceptance);
        $selection = $this->snapshotReadModel->selectionLabel($acceptance);

        return trim($event . ' / ' . $market . ' / ' . $selection, ' /');
    }

    private function sourceLabel(BettingAcceptance $acceptance): string
    {
        return trim((string) $acceptance->acceptance_source) ?: 'unknown';
    }

    private function contractLabel(BettingAcceptance $acceptance): string
    {
        return trim((string) $acceptance->acceptance_contract_version) ?: 'unknown';
    }

    /**
     * @return array<int, string>
     */
    private function distinctValues(string $column): array
    {
        return DB::table('betting_acceptances')
            ->whereNotNull($column)
            ->select($column)
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->map(fn ($value): string => (string) $value)
            ->filter(fn (string $value): bool => trim($value) !== '')
            ->values()
            ->all();
    }

    private function tablesReady(): bool
    {
        return Schema::hasTable('betting_acceptances') && Schema::hasTable('betting_settlements');
    }
}
