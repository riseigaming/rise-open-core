<?php

namespace App\Betting\Settlement;

use App\Betting\Acceptance\BettingAcceptanceSnapshotReadModel;
use App\Betting\Enums\BettingAcceptanceStatus;
use App\Betting\Enums\BettingSettlementPrecedenceState;
use App\Betting\Enums\BettingSettlementStatus;
use App\Models\BettingAcceptance;
use App\Models\BettingSettlement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PendingBettingSettlementReadService
{
    private const CONTRACTS = ['adapter_vps_v2', 'social_market_v1', 'social_feed_v1'];
    private const SOURCES = ['adapter_vps', 'social_market', 'social_feed'];
    private const ACTIVE_ACCEPTANCE_STATUSES = [
        BettingAcceptanceStatus::PENDING,
        BettingAcceptanceStatus::ACCEPTED,
    ];
    private const TERMINAL_SETTLEMENT_STATUSES = [
        BettingSettlementStatus::FINAL,
        BettingSettlementStatus::VOID,
        BettingSettlementStatus::CANCELLED,
    ];

    public function __construct(private readonly BettingAcceptanceSnapshotReadModel $readModel)
    {
    }

    public function filters(array $input): array
    {
        $source = trim((string) ($input['source'] ?? ''));
        $status = trim((string) ($input['status'] ?? ''));

        return [
            'search' => trim((string) ($input['search'] ?? '')),
            'user_id' => $this->integerFilter($input['user_id'] ?? null),
            'source' => in_array($source, ['adapter_vps', 'social_market', 'social_feed'], true) ? $source : '',
            'currency' => strtoupper(substr(trim((string) ($input['currency'] ?? '')), 0, 8)),
            'sport' => trim((string) ($input['sport'] ?? '')),
            'market_type' => trim((string) ($input['market_type'] ?? '')),
            'status' => in_array($status, ['needs_review_open', 'pending', 'review', 'postponed', 'review_required'], true) ? $status : '',
            'date_from' => $this->dateFilter($input['date_from'] ?? null),
            'date_to' => $this->dateFilter($input['date_to'] ?? null),
            'amount_min' => $this->decimalFilter($input['amount_min'] ?? null),
            'amount_max' => $this->decimalFilter($input['amount_max'] ?? null),
        ];
    }

    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        if (!$this->tablesAvailable()) {
            return new Paginator(collect(), 0, $perPage);
        }

        $paginator = $this->baseQuery()
            ->tap(fn (Builder $query) => $this->applyFilters($query, $filters))
            ->orderByRaw('CASE WHEN EXISTS (SELECT 1 FROM betting_settlements WHERE betting_settlements.betting_acceptance_id = betting_acceptances.id) THEN 1 ELSE 0 END ASC')
            ->orderByRaw('COALESCE(betting_acceptances.requested_at, betting_acceptances.created_at) ASC')
            ->oldest('betting_acceptances.id')
            ->paginate($perPage)
            ->withQueryString();

        $paginator->getCollection()->transform(fn (BettingAcceptance $acceptance): array => $this->row($acceptance));

        return $paginator;
    }

    public function countPending(): int
    {
        if (!$this->tablesAvailable()) {
            return 0;
        }

        return (int) $this->baseQuery()->count();
    }

    public function findOpenableAcceptance(int $acceptanceId): ?BettingAcceptance
    {
        if (!$this->tablesAvailable()) {
            return null;
        }

        return $this->baseQuery()
            ->whereKey($acceptanceId)
            ->whereDoesntHave('settlement')
            ->first();
    }

    public function findReviewableSettlement(int $settlementId): ?BettingSettlement
    {
        if (!$this->tablesAvailable()) {
            return null;
        }

        $acceptance = $this->baseQuery()
            ->whereHas('settlement', fn (Builder $query) => $query->whereKey($settlementId))
            ->first();

        return $acceptance?->settlement;
    }

    public function row(BettingAcceptance $acceptance): array
    {
        $acceptance->loadMissing([
            'user',
            'event',
            'market',
            'outcome',
            'quoteVersion',
            'quoteSelection.outcome',
            'settlement.walletEffects.transaction',
            'settlement.activityLogs.actor',
        ]);

        $settlement = $acceptance->settlement;
        $readiness = $this->readiness($acceptance);
        $stake = (float) $acceptance->stake_amount;
        $price = (float) $acceptance->accepted_price;
        $walletState = $this->walletState($settlement);
        $canOpenReview = $settlement === null;
        $canSettle = $settlement !== null && $readiness['can_settle'] && $walletState['blocking'] === false;

        return [
            'acceptance' => $acceptance,
            'settlement' => $settlement,
            'user_label' => $this->userLabel($acceptance),
            'source_label' => $this->sourceLabel($acceptance),
            'queue_status' => $this->queueStatus($acceptance),
            'event_label' => $this->readModel->eventLabel($acceptance),
            'market_label' => $this->readModel->marketLabel($acceptance),
            'selection_label' => $this->readModel->selectionLabel($acceptance),
            'sport_label' => $this->sportLabel($acceptance),
            'league_label' => $this->leagueLabel($acceptance),
            'stake_amount' => $stake,
            'accepted_price' => $price,
            'potential_return' => round($stake * $price, 6),
            'currency' => (string) $acceptance->currency,
            'created_at' => $acceptance->requested_at ?: $acceptance->created_at,
            'event_start_at' => $readiness['event_start_at'],
            'readiness' => $readiness,
            'wallet_state' => $walletState,
            'can_open_review' => $canOpenReview,
            'can_settle' => $canSettle,
            'action_hint' => $this->actionHint($canOpenReview, $canSettle, $readiness, $walletState),
            'operator_guidance' => $this->operatorGuidance($acceptance),
            'safe_snapshot' => $this->readModel->safeSnapshotSummary($acceptance),
            'latest_log' => $this->latestLog($acceptance),
        ];
    }

    public function readiness(BettingAcceptance $acceptance): array
    {
        $settlement = $acceptance->settlement;
        if ($settlement && (string) $settlement->precedence_state === BettingSettlementPrecedenceState::REVIEW_REQUIRED) {
            return [
                'code' => 'review_required',
                'label' => __('admin.settlement_read.review_required'),
                'badge_class' => 'bg-danger',
                'can_settle' => true,
                'reason' => __('admin.settlement_read.source_conflict_reason'),
                'event_start_at' => $this->decisionTime($acceptance),
            ];
        }

        $decisionTime = $this->decisionTime($acceptance);
        if (!$decisionTime instanceof Carbon) {
            return [
                'code' => 'unknown_time',
                'label' => __('admin.settlement_read.unknown_time'),
                'badge_class' => 'bg-secondary',
                'can_settle' => false,
                'reason' => __('admin.settlement_read.unknown_time_reason'),
                'event_start_at' => null,
            ];
        }

        $isSocial = (string) $acceptance->acceptance_source === 'social_market';
        $readyAt = $isSocial ? $decisionTime : $decisionTime->copy()->addMinutes(120);

        if (now()->lt($readyAt)) {
            return [
                'code' => 'future',
                'label' => __('admin.settlement_read.not_started'),
                'badge_class' => 'bg-warning text-dark',
                'can_settle' => false,
                'reason' => $isSocial
                    ? __('admin.settlement_read.social_market_not_closed')
                    : __('admin.settlement_read.safety_buffer'),
                'event_start_at' => $decisionTime,
            ];
        }

        return [
            'code' => 'ready',
            'label' => __('admin.settlement_read.ready'),
            'badge_class' => 'bg-success',
            'can_settle' => true,
            'reason' => __('admin.settlement_read.ready_reason'),
            'event_start_at' => $decisionTime,
        ];
    }

    private function baseQuery(): Builder
    {
        return BettingAcceptance::query()
            ->with([
                'user',
                'event',
                'market',
                'outcome',
                'quoteVersion',
                'quoteSelection.outcome',
                'settlement.walletEffects.transaction',
                'settlement.activityLogs.actor',
            ])
            ->whereIn('acceptance_contract_version', self::CONTRACTS)
            ->whereIn('acceptance_source', self::SOURCES)
            ->where(function (Builder $query): void {
                $query->whereNull('context->is_certification')
                    ->orWhere('context->is_certification', false);
            })
            ->whereNotNull('accepted_snapshot_json')
            ->when(
                Schema::hasColumn('betting_acceptances', 'accepted_snapshot_digest'),
                fn (Builder $query) => $query->whereNotNull('accepted_snapshot_digest')
            )
            ->whereIn('status', self::ACTIVE_ACCEPTANCE_STATUSES)
            ->where(function (Builder $query): void {
                $query->whereDoesntHave('settlement')
                    ->orWhereHas('settlement', function (Builder $settlement): void {
                        $settlement
                            ->whereNotIn('status', self::TERMINAL_SETTLEMENT_STATUSES)
                            ->where(function (Builder $inner): void {
                                $inner->whereIn('status', [
                                    BettingSettlementStatus::PENDING,
                                    BettingSettlementStatus::REVIEW,
                                    BettingSettlementStatus::POSTPONED,
                                ])->orWhere('precedence_state', BettingSettlementPrecedenceState::REVIEW_REQUIRED);
                            });
                    });
            });
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        if ($filters['search'] !== '') {
            $search = $filters['search'];
            $query->where(function (Builder $inner) use ($search): void {
                $inner->where('acceptance_ref', 'like', '%' . $search . '%')
                    ->orWhere('id', (int) $search)
                    ->orWhereHas('settlement', fn (Builder $settlement) => $settlement->where('settlement_ref', 'like', '%' . $search . '%'))
                    ->orWhereHas('user', function (Builder $user) use ($search): void {
                        $user->where('username', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%');
                    });
            });
        }

        if ($filters['user_id'] !== null) {
            $query->where(function (Builder $inner) use ($filters): void {
                $inner->where('betting_acceptances.cms_user_id', $filters['user_id'])
                    ->orWhere('betting_acceptances.root_user_id', $filters['user_id']);
            });
        }

        if ($filters['source'] !== '') {
            $query->where('acceptance_source', $filters['source']);
        }

        if ($filters['currency'] !== '') {
            $query->where('currency', $filters['currency']);
        }

        if ($filters['sport'] !== '') {
            $sport = $filters['sport'];
            $query->where(function (Builder $inner) use ($sport): void {
                $inner->whereHas('event', fn (Builder $event) => $event->where('sport_code', 'like', '%' . $sport . '%'))
                    ->orWhere('accepted_snapshot_json', 'like', '%' . $sport . '%');
            });
        }

        if ($filters['market_type'] !== '') {
            $market = $filters['market_type'];
            $query->where(function (Builder $inner) use ($market): void {
                $inner->whereHas('market', fn (Builder $relation) => $relation->where('market_type', 'like', '%' . $market . '%'))
                    ->orWhere('accepted_snapshot_json', 'like', '%' . $market . '%');
            });
        }

        if ($filters['status'] === 'needs_review_open') {
            $query->whereDoesntHave('settlement');
        } elseif (in_array($filters['status'], ['pending', 'review', 'postponed'], true)) {
            $query->whereHas('settlement', fn (Builder $settlement) => $settlement->where('status', $filters['status']));
        } elseif ($filters['status'] === 'review_required') {
            $query->whereHas('settlement', fn (Builder $settlement) => $settlement->where('precedence_state', BettingSettlementPrecedenceState::REVIEW_REQUIRED));
        }

        if ($filters['date_from']) {
            $query->where('requested_at', '>=', Carbon::parse($filters['date_from'])->startOfDay());
        }

        if ($filters['date_to']) {
            $query->where('requested_at', '<=', Carbon::parse($filters['date_to'])->endOfDay());
        }

        if ($filters['amount_min'] !== null) {
            $query->where('stake_amount', '>=', $filters['amount_min']);
        }

        if ($filters['amount_max'] !== null) {
            $query->where('stake_amount', '<=', $filters['amount_max']);
        }
    }

    private function queueStatus(BettingAcceptance $acceptance): array
    {
        $settlement = $acceptance->settlement;
        if (!$settlement) {
            return [
                'code' => 'needs_review_open',
                'label' => __('admin.settlement_read.open_review_required'),
                'badge_class' => 'bg-primary',
            ];
        }

        if ((string) $settlement->precedence_state === BettingSettlementPrecedenceState::REVIEW_REQUIRED) {
            return [
                'code' => 'review_required',
                'label' => __('admin.settlement_read.source_conflict'),
                'badge_class' => 'bg-danger',
            ];
        }

        return match ((string) $settlement->status) {
            BettingSettlementStatus::PENDING, BettingSettlementStatus::REVIEW => [
                'code' => (string) $settlement->status,
                'label' => __('admin.settlement_read.review_open'),
                'badge_class' => 'bg-info text-dark',
            ],
            BettingSettlementStatus::POSTPONED => [
                'code' => (string) $settlement->status,
                'label' => __('admin.settlement_read.postponed'),
                'badge_class' => 'bg-secondary',
            ],
            default => [
                'code' => (string) $settlement->status,
                'label' => Str::headline((string) $settlement->status),
                'badge_class' => 'bg-light text-dark border',
            ],
        };
    }

    private function actionHint(bool $canOpenReview, bool $canSettle, array $readiness, array $walletState): string
    {
        if ($canOpenReview) {
            return __('admin.settlement_read.open_review');
        }

        if ($canSettle) {
            return __('admin.settlement_read.ready');
        }

        if ((bool) ($walletState['blocking'] ?? false)) {
            return __('admin.settlement_read.check_wallet_effect');
        }

        return __('admin.settlement_read.not_ready');
    }

    private function operatorGuidance(BettingAcceptance $acceptance): ?array
    {
        if (!$this->isTennisH2h($acceptance)) {
            return null;
        }

        return [
            'title' => __('admin.settlement_read.tennis_policy_title'),
            'body' => __('admin.settlement_read.tennis_policy_body'),
            'items' => [
                __('admin.settlement_read.tennis_policy_retirement'),
                __('admin.settlement_read.tennis_policy_postponed'),
                __('admin.settlement_read.tennis_policy_reason'),
                __('admin.settlement_read.tennis_policy_automatic'),
            ],
        ];
    }

    private function isTennisH2h(BettingAcceptance $acceptance): bool
    {
        $sportKey = Str::lower((string) (optional($acceptance->event)->sport_code
            ?: data_get($acceptance->accepted_snapshot_json, 'event.sport_code')
            ?: data_get($acceptance->accepted_snapshot_json, 'settlement_identity.sport_key')
            ?: data_get($acceptance->accepted_snapshot_json, 'sport_key')));

        if ($sportKey === '' || !Str::startsWith($sportKey, 'tennis')) {
            return false;
        }

        $marketKey = Str::lower((string) (data_get($acceptance->accepted_snapshot_json, 'market.market_type')
            ?: data_get($acceptance->accepted_snapshot_json, 'market.key')
            ?: data_get($acceptance->accepted_snapshot_json, 'market.slug')
            ?: data_get($acceptance->accepted_snapshot_json, 'settlement_identity.market_type')
            ?: data_get($acceptance->accepted_snapshot_json, 'settlement_identity.market_key')));

        return in_array($marketKey, ['h2h', 'match_winner', 'match_result'], true);
    }

    private function walletState(?BettingSettlement $settlement): array
    {
        if (!$settlement) {
            return ['label' => __('admin.settlement_read.no_wallet_movement'), 'blocking' => false];
        }

        $activeEffects = $settlement->walletEffects
            ->where('effect_phase', 'apply')
            ->whereNull('reversed_at')
            ->values();

        if ($activeEffects->isEmpty()) {
            return ['label' => __('admin.settlement_read.not_applied'), 'blocking' => false];
        }

        $effect = $activeEffects->first();
        $blocking = !in_array((string) $settlement->status, self::TERMINAL_SETTLEMENT_STATUSES, true);

        return [
            'label' => __('admin.settlement_read.effect_applied', [
                'effect' => ucfirst((string) $effect->effect_family),
                'amount' => number_format((float) $effect->amount, 2),
                'currency' => $effect->currency,
            ]),
            'blocking' => $blocking,
        ];
    }

    private function decisionTime(BettingAcceptance $acceptance): ?Carbon
    {
        $candidates = [
            optional($acceptance->event)->starts_at,
            data_get($acceptance->accepted_snapshot_json, 'event.starts_at'),
            data_get($acceptance->accepted_snapshot_json, 'event.start_time'),
            data_get($acceptance->accepted_snapshot_json, 'event.commence_time'),
            data_get($acceptance->accepted_snapshot_json, 'settlement_identity.event_start_at'),
            data_get($acceptance->accepted_snapshot_json, 'settlement_identity.commence_time'),
            data_get($acceptance->context, 'event_starts_at'),
        ];

        if ((string) $acceptance->acceptance_source === 'social_market') {
            array_unshift($candidates, data_get($acceptance->accepted_snapshot_json, 'market.closes_at'));
        }

        foreach ($candidates as $candidate) {
            if ($candidate instanceof Carbon) {
                return $candidate;
            }

            if ($candidate) {
                try {
                    return Carbon::parse($candidate);
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        return null;
    }

    private function userLabel(BettingAcceptance $acceptance): string
    {
        $user = $acceptance->user;
        if (!$user) {
            return __('admin.settlement_read.user_number', ['id' => $acceptance->cms_user_id ?: '-']);
        }

        $name = trim(implode(' ', array_filter([(string) $user->first_name, (string) $user->last_name])));

        return ($user->username ?: $name ?: $user->email ?: __('admin.settlement_read.user_number', ['id' => $user->id]));
    }

    private function sourceLabel(BettingAcceptance $acceptance): string
    {
        return match ((string) $acceptance->acceptance_source) {
            'adapter_vps' => 'Adapter Prematch',
            'social_market' => 'Social Market',
            default => 'Sportsbook',
        };
    }

    private function sportLabel(BettingAcceptance $acceptance): string
    {
        return (string) (optional($acceptance->event)->sport_code
            ?: data_get($acceptance->accepted_snapshot_json, 'event.sport_code')
            ?: data_get($acceptance->accepted_snapshot_json, 'settlement_identity.sport_key')
            ?: '-');
    }

    private function leagueLabel(BettingAcceptance $acceptance): string
    {
        $label = $this->firstSafeLabel([
            optional($acceptance->event)->tournament_name,
            data_get($acceptance->accepted_snapshot_json, 'competition.name'),
            data_get($acceptance->accepted_snapshot_json, 'competition.label'),
            data_get($acceptance->accepted_snapshot_json, 'competition_label'),
            data_get($acceptance->accepted_snapshot_json, 'event.competition_label'),
            data_get($acceptance->accepted_snapshot_json, 'event.tournament_name'),
            data_get($acceptance->accepted_snapshot_json, 'event.league_title'),
            data_get($acceptance->accepted_snapshot_json, 'league.name'),
            data_get($acceptance->accepted_snapshot_json, 'league.label'),
            data_get($acceptance->accepted_snapshot_json, 'league_name'),
            data_get($acceptance->accepted_snapshot_json, 'settlement_identity.competition_name'),
            data_get($acceptance->accepted_snapshot_json, 'category.title'),
        ]);

        if ($label !== '') {
            return $label;
        }

        $fallback = $this->firstSafeLabel([
            data_get($acceptance->accepted_snapshot_json, 'competition.key'),
            data_get($acceptance->accepted_snapshot_json, 'competition_key'),
            data_get($acceptance->accepted_snapshot_json, 'event.competition_key'),
            data_get($acceptance->accepted_snapshot_json, 'event.canonical_competition_key'),
            data_get($acceptance->accepted_snapshot_json, 'settlement_identity.competition_key'),
            data_get($acceptance->accepted_snapshot_json, 'settlement_identity.sport_key'),
            data_get($acceptance->accepted_snapshot_json, 'event.sport_code'),
            optional($acceptance->event)->sport_code,
        ]);

        return $fallback !== '' ? $this->humanizedLeagueKey($fallback) : '-';
    }

    private function firstSafeLabel(array $values): string
    {
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value !== '' && !$this->isUnsafeLabel($value)) {
                return $value;
            }
        }

        return '';
    }

    private function humanizedLeagueKey(string $value): string
    {
        $value = trim($value);
        if ($value === '' || $this->isUnsafeLabel($value)) {
            return '-';
        }

        $key = Str::of($value)
            ->replace(':', '_')
            ->replace('-', '_')
            ->lower();
        $key = (string) $key;
        $parts = array_values(array_filter(explode('_', $key), fn (string $part): bool => $part !== ''));

        if (count($parts) <= 1) {
            return '-';
        }

        if (in_array($parts[0], ['soccer', 'basketball', 'tennis', 'baseball', 'volleyball', 'snooker'], true)) {
            array_shift($parts);
        }

        if ($parts === []) {
            return '-';
        }

        return Str::headline(implode('_', $parts));
    }

    private function isUnsafeLabel(string $value): bool
    {
        $lower = (string) Str::of($value)->lower();

        foreach ([
            'quote_ref',
            'adapter_vps_quote_ref',
            'source_provider',
            'source_payload',
            'raw_payload',
            'provider_payload',
            'selection_token',
            'auth_token',
            'api_key',
            'api_token',
            'bearer_token',
        ] as $needle) {
            if (str_contains($lower, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function latestLog(BettingAcceptance $acceptance): ?array
    {
        $logs = $acceptance->settlement?->activityLogs ?: $acceptance->activityLogs;
        $log = $logs->sortByDesc('id')->first();

        if (!$log) {
            return null;
        }

        return [
            'action' => (string) $log->action,
            'created_at' => optional($log->created_at)->format('d.m.Y H:i:s'),
            'actor' => optional($log->actor)->username ?: optional($log->actor)->email ?: '-',
        ];
    }

    private function dateFilter($value): ?string
    {
        $value = trim((string) $value);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }

    private function decimalFilter($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
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

    private function tablesAvailable(): bool
    {
        return Schema::hasTable('betting_acceptances') && Schema::hasTable('betting_settlements');
    }
}
