<?php

namespace App\Betting\Acceptance;

use App\Betting\Enums\BettingAcceptanceDecision;
use App\Betting\Enums\BettingAcceptanceStatus;
use App\Betting\Enums\BettingDecisionSource;
use App\Betting\Enums\BettingOfferStatus;
use App\Betting\Exceptions\AcceptanceReviewConflictException;
use App\Betting\Exceptions\EventCutoffReachedException;
use App\Betting\Exceptions\FrontBettingDisabledException;
use App\Betting\Exceptions\IdempotencyConflictException;
use App\Betting\Exceptions\InsufficientBalanceException;
use App\Betting\Exceptions\MarketNotBettableException;
use App\Betting\Exceptions\QuoteContextMissingException;
use App\Betting\Exceptions\QuoteNotTradeableException;
use App\Betting\Exceptions\QuoteSelectionNotFoundException;
use App\Betting\Exceptions\SelectionNotActiveException;
use App\Betting\Exceptions\StalePriceException;
use App\Betting\Exceptions\StaleQuoteVersionException;
use App\Betting\Exceptions\UnsupportedWalletForProductException;
use App\Betting\Markets\BettingMarketReadinessService;
use App\Betting\Settlement\PendingSettlementRecorder;
use App\Enum\TransactionActionEnum;
use App\Enum\TransactionStatusEnum;
use App\Betting\Support\BettingActivityLogService;
use App\Betting\Acceptance\BettingAcceptanceSnapshotBuilder;
use App\Models\BettingAcceptance;
use App\Models\BettingQuoteSelection;
use App\Models\CmsUser;
use App\Models\CmsUserTransaction;
use App\Models\WalletAccount;
use App\Models\WalletLedgerEntry;
use App\Services\Wallet\ActiveWalletService;
use App\Services\Wallet\AssetWalletService;
use App\Services\Wallet\ProductWalletCapabilityService;
use App\Services\Wallet\SpendableWalletContext;
use App\Services\Wallet\SpendableWalletService;
use App\Services\ProductAccess\ProductAccessPolicyService;
use App\Services\Certification\ProductionCertificationContext;
use Illuminate\Database\QueryException;
use RuntimeException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Brick\Math\BigDecimal;

class AcceptanceService
{
    private PendingSettlementRecorder $pendingSettlements;
    private $activityLogService;
    private $wallets;
    private $marketReadiness;
    private $assetWallets;
    private $activeWallets;
    private $productWallets;
    private $snapshotBuilder;
    private $stakeLimitPolicy;
    private $productAccess;

    public function __construct(
        ?PendingSettlementRecorder $pendingSettlements = null,
        ?BettingActivityLogService $activityLogService = null,
        ?SpendableWalletService $wallets = null,
        ?BettingMarketReadinessService $marketReadiness = null,
        ?AssetWalletService $assetWallets = null,
        ?ActiveWalletService $activeWallets = null,
        ?ProductWalletCapabilityService $productWallets = null,
        ?BettingAcceptanceSnapshotBuilder $snapshotBuilder = null,
        ?StakeLimitPolicy $stakeLimitPolicy = null,
        ?ProductAccessPolicyService $productAccess = null
    )
    {
        $this->activityLogService = $activityLogService ?: app(BettingActivityLogService::class);
        $this->pendingSettlements = $pendingSettlements ?: new PendingSettlementRecorder($this->activityLogService);
        $this->wallets = $wallets ?: app(SpendableWalletService::class);
        $this->marketReadiness = $marketReadiness ?: app(BettingMarketReadinessService::class);
        $this->assetWallets = $assetWallets ?: app(AssetWalletService::class);
        $this->activeWallets = $activeWallets ?: app(ActiveWalletService::class);
        $this->productWallets = $productWallets ?: app(ProductWalletCapabilityService::class);
        $this->snapshotBuilder = $snapshotBuilder ?: app(BettingAcceptanceSnapshotBuilder::class);
        $this->stakeLimitPolicy = $stakeLimitPolicy ?: app(StakeLimitPolicy::class);
        $this->productAccess = $productAccess ?: app(ProductAccessPolicyService::class);
    }

    public function createPendingAcceptance(array $attributes): BettingAcceptance
    {
        $acceptance = BettingAcceptance::query()->create([
            'acceptance_ref' => $attributes['acceptance_ref'] ?? (string) Str::uuid(),
            'acceptance_contract_version' => $attributes['acceptance_contract_version'] ?? 'legacy_v1',
            'acceptance_source' => $attributes['acceptance_source'] ?? 'cms_legacy',
            'idempotency_key' => $attributes['idempotency_key'] ?? null,
            'request_fingerprint' => $attributes['request_fingerprint'] ?? null,
            'betting_quote_version_id' => $attributes['betting_quote_version_id'],
            'betting_quote_selection_id' => $attributes['betting_quote_selection_id'],
            'betting_event_id' => $attributes['betting_event_id'],
            'betting_market_id' => $attributes['betting_market_id'],
            'betting_outcome_id' => $attributes['betting_outcome_id'],
            'cms_user_id' => $attributes['cms_user_id'] ?? null,
            'root_user_id' => $attributes['root_user_id'] ?? null,
            'reviewed_by_cms_user_id' => $attributes['reviewed_by_cms_user_id'] ?? null,
            'currency' => $attributes['currency'] ?? 'TRY',
            'stake_amount' => $attributes['stake_amount'],
            'accepted_price' => $attributes['accepted_price'],
            'status' => $attributes['status'] ?? BettingAcceptanceStatus::PENDING,
            'decision' => $attributes['decision'] ?? BettingAcceptanceDecision::PENDING,
            'decision_source' => $attributes['decision_source'] ?? BettingDecisionSource::SYSTEM,
            'requested_at' => $attributes['requested_at'] ?? now(),
            'reviewed_at' => $attributes['reviewed_at'] ?? null,
            'last_transition_at' => $attributes['last_transition_at'] ?? now(),
            'rejected_reason' => $attributes['rejected_reason'] ?? null,
            'decision_reason' => $attributes['decision_reason'] ?? null,
            'context' => $attributes['context'] ?? [],
            'accepted_snapshot_json' => $attributes['accepted_snapshot_json'] ?? null,
            'accepted_snapshot_digest' => $attributes['accepted_snapshot_digest'] ?? null,
            'override_meta' => $attributes['override_meta'] ?? null,
        ]);

        $this->activityLogService->logAcceptance(
            $acceptance,
            'acceptance.requested',
            'Acceptance request recorded against quote version v' . data_get($attributes, 'quote_version_no', '?') . '.',
            [
                'status' => $acceptance->status,
                'decision' => $acceptance->decision,
                'idempotency_key' => $acceptance->idempotency_key,
                'cms_user_id' => $acceptance->cms_user_id,
                'request_source' => data_get($attributes, 'context.request_source'),
                'quote_version_id' => $acceptance->betting_quote_version_id,
                'quote_selection_id' => $acceptance->betting_quote_selection_id,
            ]
        );

        return $acceptance;
    }

    public function requestForSelection(BettingQuoteSelection $selection, array $attributes): BettingAcceptance
    {
        app(\App\Services\Finance\PrelaunchFinanceWriteGate::class)->assertWritable();
        if (!app(\App\Betting\Support\SportsbookLocalParityService::class)->financeSandboxEnabled()) throw new RuntimeException('Local sportsbook finance gate is closed.');
        $this->assertProductAccessForAttributes($attributes);
        $idempotencyKey = $this->resolveIdempotencyKey($attributes);

        try {
            return DB::transaction(function () use ($selection, $attributes, $idempotencyKey) {
                // A replay returns an existing effect, not new admission. Resolve its stored
                // wallet with ownership checks so a policy/preference change cannot strand it.
                if ($idempotencyKey !== null) {
                    $existing = BettingAcceptance::where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
                    if ($existing && ($walletId = data_get($existing->context, 'wallet_account_id'))) {
                        $actor = CmsUser::find($attributes['cms_user_id'] ?? 0);
                        $storedWallet = $actor ? $this->assetWallets->findWalletForOwner(
                            $this->assetWallets->resolveOwnerUser($actor), (int) $walletId, true
                        ) : null;
                        if (!$storedWallet) {
                            throw new IdempotencyConflictException($idempotencyKey);
                        }
                        $storedContext = $this->wallets->resolveForWallet($actor, $storedWallet, true);
                        if (!$storedContext) {
                            throw new IdempotencyConflictException($idempotencyKey);
                        }
                        $selection->loadMissing('quoteVersion');
                        $replayAttributes = array_merge($attributes, [
                            'cms_user_id' => $storedContext->ownerWalletId(),
                            'root_user_id' => $storedContext->rootWalletId(),
                            'currency' => $storedContext->currency(),
                        ]);
                        return $this->resolveIdempotentAcceptanceReplay($existing, $idempotencyKey,
                            $this->requestFingerprint($selection, $replayAttributes));
                    }
                }
                $walletContext = $this->resolveWalletContext($attributes);
                $attributes = $this->normalizeWalletAttributes($attributes, $walletContext);

                /** @var BettingQuoteSelection|null $lockedSelection */
            $lockedSelection = BettingQuoteSelection::query()
                ->whereKey($selection->id)
                ->lockForUpdate()
                ->first();

            if (!$lockedSelection) {
                throw new QuoteSelectionNotFoundException();
            }

                $lockedSelection->loadMissing(['quoteVersion.event', 'market', 'outcome']);
                $quoteVersion = $lockedSelection->quoteVersion;
                $event = optional($quoteVersion)->event;

            if (!$quoteVersion || !$event) {
                throw new QuoteContextMissingException('Quote selection is missing its event or quote version context.');
            }

                $this->assertAcceptanceRequestIsValid($lockedSelection, $quoteVersion->id, $attributes);
                $requestFingerprint = $this->requestFingerprint($lockedSelection, $attributes);

                if ($idempotencyKey !== null) {
                    $existingAcceptance = BettingAcceptance::query()
                        ->where('idempotency_key', $idempotencyKey)
                        ->lockForUpdate()
                        ->first();

                    if ($existingAcceptance) {
                        return $this->resolveIdempotentAcceptanceReplay($existingAcceptance, $idempotencyKey, $requestFingerprint);
                    }
                }

                $this->assertStakeWithinLimit($attributes);

                $acceptanceRef = (string) Str::uuid();
                $acceptedSnapshot = $this->snapshotBuilder->fromAcceptanceRequest(
                    $lockedSelection,
                    $quoteVersion,
                    $event,
                    array_merge($attributes, ['acceptance_ref' => $acceptanceRef]),
                    $requestFingerprint,
                    $idempotencyKey
                );

                $acceptance = $this->createPendingAcceptance([
                    'acceptance_ref' => $acceptanceRef,
                    'acceptance_contract_version' => $attributes['acceptance_contract_version'] ?? 'legacy_v1',
                    'acceptance_source' => $attributes['acceptance_source'] ?? 'cms_legacy',
                    'idempotency_key' => $idempotencyKey,
                    'request_fingerprint' => $requestFingerprint,
                    'betting_quote_version_id' => $lockedSelection->betting_quote_version_id,
                    'betting_quote_selection_id' => $lockedSelection->id,
                    'betting_event_id' => $quoteVersion->betting_event_id,
                    'betting_market_id' => $lockedSelection->betting_market_id,
                    'betting_outcome_id' => $lockedSelection->betting_outcome_id,
                    'cms_user_id' => $attributes['cms_user_id'] ?? null,
                    'root_user_id' => $attributes['root_user_id'] ?? null,
                    'currency' => $attributes['currency'] ?? 'TRY',
                    'stake_amount' => $attributes['stake_amount'],
                    'accepted_price' => $attributes['accepted_price'] ?? $lockedSelection->decimal_price,
                    'accepted_snapshot_json' => $acceptedSnapshot,
                    'accepted_snapshot_digest' => $this->snapshotBuilder->digest($acceptedSnapshot),
                    'context' => array_merge([
                        'quote_version_id' => $lockedSelection->betting_quote_version_id,
                        'quote_version_no' => $quoteVersion->version_no,
                        'quote_trading_state' => $quoteVersion->trading_state,
                        'selection_offer_status' => $lockedSelection->offer_status,
                        'event_starts_at' => optional($event->starts_at)?->toDateTimeString(),
                    ], $attributes['context'] ?? []),
                    'quote_version_no' => $quoteVersion->version_no,
                ]);

                $this->debitAcceptanceStake($acceptance, $attributes, $walletContext);

                return $acceptance->fresh([
                    'quoteVersion',
                    'quoteSelection.outcome',
                    'event',
                    'settlement',
                ]);
            }, 3);
        } catch (QueryException $exception) {
            if ($idempotencyKey === null || !$this->isIdempotencyCollision($exception)) {
                throw $exception;
            }

            $selection->loadMissing('quoteVersion');

            $existingAcceptance = BettingAcceptance::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if (!$existingAcceptance) {
                throw $exception;
            }

            return $this->resolveIdempotentAcceptanceReplay(
                $existingAcceptance,
                $idempotencyKey,
                $this->requestFingerprint($selection, $attributes)
            );
        }
    }

    public function normalizeWalletAttributesForAcceptance(array $attributes): array
    {
        $walletContext = $this->resolveWalletContext($attributes);

        return [
            $this->normalizeWalletAttributes($attributes, $walletContext),
            $walletContext,
        ];
    }

    public function assertStakeWithinLimit(array $attributes): void
    {
        $this->stakeLimitPolicy->assertStakeAllowed(
            (float) ($attributes['stake_amount'] ?? 0),
            (string) ($attributes['currency'] ?? '')
        );
    }

    public function debitStakeForAcceptance(BettingAcceptance $acceptance, array $attributes, ?SpendableWalletContext $walletContext = null): void
    {
        $this->debitAcceptanceStake($acceptance, $attributes, $walletContext);
    }

    public function assertProductAccessForAttributes(array $attributes): void
    {
        $userId = (int) ($attributes['cms_user_id'] ?? 0);
        $user = $userId > 0 ? CmsUser::query()->find($userId) : null;

        $this->productAccess->assertAllowed($user, ProductAccessPolicyService::PRODUCT_SPORTSBOOK);
    }

    public function review(BettingAcceptance $acceptance, string $decision, ?string $reason = null, ?CmsUser $actor = null): BettingAcceptance
    {
        app(\App\Services\Finance\PrelaunchFinanceWriteGate::class)->assertWritable();
        $decision = strtolower($decision);

        return DB::transaction(function () use ($acceptance, $decision, $reason, $actor) {
            /** @var BettingAcceptance|null $lockedAcceptance */
            $lockedAcceptance = BettingAcceptance::query()
                ->whereKey($acceptance->id)
                ->lockForUpdate()
                ->first();

            if (!$lockedAcceptance) {
                throw new RuntimeException('Acceptance was not found.');
            }

            $lockedAcceptance->loadMissing(['quoteVersion.event', 'quoteSelection', 'settlement']);
            $this->assertAcceptanceReviewable($lockedAcceptance);

            [$status, $decisionValue, $timestamps] = match ($decision) {
                BettingAcceptanceDecision::ACCEPT => [
                    BettingAcceptanceStatus::ACCEPTED,
                    BettingAcceptanceDecision::ACCEPT,
                    ['accepted_at' => now(), 'rejected_at' => null],
                ],
                BettingAcceptanceDecision::REJECT => [
                    BettingAcceptanceStatus::REJECTED,
                    BettingAcceptanceDecision::REJECT,
                    ['rejected_at' => now(), 'accepted_at' => null],
                ],
                BettingAcceptanceDecision::HOLD => [
                    BettingAcceptanceStatus::HELD,
                    BettingAcceptanceDecision::HOLD,
                    ['accepted_at' => null, 'rejected_at' => null],
                ],
                BettingAcceptanceDecision::SUSPEND => [
                    BettingAcceptanceStatus::SUSPENDED,
                    BettingAcceptanceDecision::SUSPEND,
                    ['accepted_at' => null, 'rejected_at' => null],
                ],
                BettingAcceptanceDecision::CANCEL => [
                    BettingAcceptanceStatus::CANCELLED,
                    BettingAcceptanceDecision::CANCEL,
                    ['accepted_at' => null, 'rejected_at' => null],
                ],
                default => throw new \InvalidArgumentException('Unsupported acceptance decision: ' . $decision),
            };

            if (in_array($decisionValue, [BettingAcceptanceDecision::REJECT, BettingAcceptanceDecision::CANCEL], true)) {
                $this->refundAcceptanceStake($lockedAcceptance);
            }

            $lockedAcceptance->update(array_merge($timestamps, [
                'status' => $status,
                'decision' => $decisionValue,
                'decision_source' => BettingDecisionSource::MANUAL,
                'reviewed_by_cms_user_id' => optional($actor)->id,
                'reviewed_at' => now(),
                'last_transition_at' => now(),
                'rejected_reason' => $decisionValue === BettingAcceptanceDecision::REJECT ? $reason : null,
                'decision_reason' => $reason,
                'override_meta' => [
                    'manual_review' => true,
                    'quote_version_id' => $lockedAcceptance->betting_quote_version_id,
                    'quote_selection_id' => $lockedAcceptance->betting_quote_selection_id,
                    'quote_offer_status' => optional($lockedAcceptance->quoteSelection)->offer_status,
                    'quote_trading_state' => optional($lockedAcceptance->quoteVersion)->trading_state,
                ],
            ]));

            if ($decisionValue === BettingAcceptanceDecision::ACCEPT) {
                $this->pendingSettlements->record($lockedAcceptance, [
                    'status' => 'pending',
                    'result_code' => 'pending',
                    'reason' => 'Awaiting prematch settlement review.',
                ]);
            }

            $this->activityLogService->logAcceptance(
                $lockedAcceptance->fresh(['settlement']),
                'acceptance.' . $decisionValue,
                'Acceptance moved to [' . $status . '] from quote version #' . $lockedAcceptance->betting_quote_version_id . '.',
                [
                    'decision' => $decisionValue,
                    'reason' => $reason,
                    'quote_version_id' => $lockedAcceptance->betting_quote_version_id,
                    'quote_selection_id' => $lockedAcceptance->betting_quote_selection_id,
                ],
                $actor
            );

            return $lockedAcceptance->fresh([
                'quoteVersion',
                'quoteSelection.outcome',
                'event',
                'settlement',
                'reviewedBy',
            ]);
        }, 3);
    }

    private function assertAcceptanceRequestIsValid(BettingQuoteSelection $selection, int $requestedQuoteVersionId, array $attributes): void
    {
        $quoteVersion = $selection->quoteVersion;
        $event = optional($quoteVersion)->event;

        if (!$quoteVersion || !$event) {
            throw new QuoteContextMissingException('Acceptance request is missing quote version or event context.');
        }

        if ((int) $event->current_quote_version_id !== $requestedQuoteVersionId) {
            throw new StaleQuoteVersionException($requestedQuoteVersionId);
        }

        $frontBettingEnabled = array_key_exists('front_betting_enabled', $event->getAttributes())
            ? (bool) $event->front_betting_enabled
            : true;

        if (!$frontBettingEnabled) {
            throw new FrontBettingDisabledException((int) $event->id, (string) $event->event_key);
        }

        if ($selection->offer_status !== BettingOfferStatus::OPEN) {
            throw new SelectionNotActiveException((string) $selection->offer_status);
        }

        if (in_array((string) $quoteVersion->trading_state, ['held', 'suspended'], true)) {
            throw new QuoteNotTradeableException((string) $quoteVersion->trading_state);
        }

        $marketReadiness = $this->marketReadiness->statusForMarket($selection->market, (string) optional($event)->sport_code);
        if (!(bool) ($marketReadiness['bettable'] ?? true)) {
            throw new MarketNotBettableException(
                (string) optional($selection->market)->market_type,
                $marketReadiness['provider_key'] ?? null,
                $marketReadiness['provider_market_key'] ?? null,
                (string) ($marketReadiness['reason_code'] ?? 'market_not_bettable')
            );
        }

        $requestedPrice = isset($attributes['accepted_price']) ? (float) $attributes['accepted_price'] : (float) $selection->decimal_price;
        if (round($requestedPrice, 6) !== round((float) $selection->decimal_price, 6)) {
            throw new StalePriceException($requestedPrice, (float) $selection->decimal_price);
        }

        $cutoffSeconds = max(0, (int) config('betting.acceptance.cutoff_seconds_before_start', 900));
        if ($event->starts_at && now()->greaterThanOrEqualTo($event->starts_at->copy()->subSeconds($cutoffSeconds))) {
            throw new EventCutoffReachedException($event->starts_at, $cutoffSeconds);
        }
    }

    private function assertAcceptanceReviewable(BettingAcceptance $acceptance): void
    {
        if (in_array((string) $acceptance->status, [
            BettingAcceptanceStatus::ACCEPTED,
            BettingAcceptanceStatus::REJECTED,
            BettingAcceptanceStatus::CANCELLED,
            BettingAcceptanceStatus::SETTLED,
        ], true)) {
            throw new AcceptanceReviewConflictException('Acceptance review already finalized. Current status: [' . $acceptance->status . '].');
        }
    }

    private function resolveIdempotencyKey(array $attributes): ?string
    {
        $key = trim((string) ($attributes['idempotency_key'] ?? $attributes['client_request_id'] ?? ''));

        return $key !== '' ? $key : null;
    }

    private function requestFingerprint(BettingQuoteSelection $selection, array $attributes): string
    {
        return hash('sha256', json_encode([
            'betting_quote_version_id' => $selection->betting_quote_version_id,
            'betting_quote_selection_id' => $selection->id,
            'betting_event_id' => $selection->quoteVersion->betting_event_id,
            'cms_user_id' => $attributes['cms_user_id'] ?? null,
            'root_user_id' => $attributes['root_user_id'] ?? null,
            'currency' => $attributes['currency'] ?? 'TRY',
            'stake_amount' => (string) ($attributes['stake_amount'] ?? ''),
            'accepted_price' => (string) ($attributes['accepted_price'] ?? $selection->decimal_price),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function resolveIdempotentAcceptanceReplay(BettingAcceptance $acceptance, string $idempotencyKey, string $requestFingerprint): BettingAcceptance
    {
        if ((string) $acceptance->request_fingerprint !== $requestFingerprint) {
            throw new IdempotencyConflictException($idempotencyKey);
        }

        $this->activityLogService->logAcceptance(
            $acceptance,
            'acceptance.idempotent_replay',
            'Acceptance request replayed with existing idempotency key.',
            [
                'idempotency_key' => $idempotencyKey,
                'quote_version_id' => $acceptance->betting_quote_version_id,
            ]
        );

        return $acceptance->fresh([
            'quoteVersion',
            'quoteSelection.outcome',
            'event',
            'settlement',
        ]);
    }

    private function isIdempotencyCollision(QueryException $exception): bool
    {
        $message = Str::lower($exception->getMessage());

        return Str::contains($message, [
            'betting_acceptances_idempotency_unique',
            'betting_acceptances.idempotency_key',
            'idempotency_key',
        ]);
    }

    private function refundAcceptanceStake(BettingAcceptance $acceptance): void
    {
        $reference = (string) $acceptance->acceptance_ref;
        $walletId = (int) data_get($acceptance->context, 'wallet_account_id');
        $currency = (string) $acceptance->currency;
        $owner = CmsUser::query()->find($acceptance->cms_user_id);
        $wallet = $owner && $walletId > 0
            ? $this->assetWallets->findWalletForOwner($owner, $walletId, true)
            : null;
        $context = $wallet
            ? $this->wallets->resolveForWallet($owner, $wallet, true, (int) data_get($acceptance->context, 'wallet_context_user_id'))
            : null;

        if ($acceptance->settlement !== null || !$context
            || $context->ownerWalletId() !== (int) $acceptance->cms_user_id
            || $context->rootWalletId() !== (int) $acceptance->root_user_id
            || $context->currency() !== $currency
            || data_get($acceptance->context, 'wallet_currency') !== $currency
            || data_get($acceptance->context, 'wallet_type') !== $context->walletType()) {
            throw new AcceptanceReviewConflictException('Original sportsbook wallet could not be verified.');
        }

        $debits = CmsUserTransaction::query()->where('event_type', 'sportsbook_acceptance')
            ->where('ref_transaction_id', $reference)->limit(2)->lockForUpdate()->get();
        $entries = WalletLedgerEntry::query()->where('idempotency_key', 'sportsbook_acceptance:' . $reference)
            ->limit(2)->lockForUpdate()->get();
        if ($debits->count() !== 1 || $entries->count() !== 1) {
            throw new AcceptanceReviewConflictException('Exactly one original sportsbook debit and ledger entry are required.');
        }
        $debit = $debits->first();
        $entry = $entries->first();
        if ((int) $debit->cms_user_id !== $context->ownerWalletId()
            || (int) $debit->source_wallet_account_id !== $walletId
            || $debit->source_wallet_type !== $context->walletType()
            || $debit->currency !== $currency || $debit->source_currency !== $currency
            || $debit->transactionAssetCode() !== $currency
            || $debit->provider_transaction_id !== $reference || $debit->acceptor !== 'sportsbook'
            || (int) $debit->action !== TransactionActionEnum::Bet || (int) $debit->status !== TransactionStatusEnum::Success
            || (int) $entry->wallet_account_id !== $walletId || (int) $entry->cms_user_id !== $context->ownerWalletId()
            || $entry->asset !== $currency || $entry->entry_type !== 'sportsbook_acceptance_debited'
            || $entry->status !== 'applied' || $entry->direction !== 'debit'
            || $entry->source_type !== 'cms_user_transaction' || $entry->source_reference !== $debit->transactionId
            || data_get($entry->meta, 'acceptance_ref') !== $reference
            || (int) data_get($entry->meta, 'acceptance_id') !== (int) $acceptance->id
            || (int) data_get($entry->meta, 'cms_user_transaction_id') !== (int) $debit->id) {
            throw new AcceptanceReviewConflictException('Original sportsbook debit provenance does not match.');
        }

        $amount = $this->wallets->decimalForContext($context, (string) $debit->getRawOriginal('amount'));
        $stake = BigDecimal::of($amount);
        $debitBefore = $debit->getRawOriginal('before_balance');
        $debitAfter = $debit->getRawOriginal('after_balance');
        foreach (['exact_amount' => $amount, 'exact_before_balance' => $debitBefore, 'exact_after_balance' => $debitAfter] as $field => $value) {
            $snapshot = $debit->getRawOriginal($field);
            if ($snapshot !== null && ($value === null || !BigDecimal::of((string) $snapshot)->isEqualTo((string) $value))) {
                throw new AcceptanceReviewConflictException('Original sportsbook debit precision snapshot does not match.');
            }
        }
        if (!$stake->isPositive() || !$stake->isEqualTo((string) $acceptance->getRawOriginal('stake_amount'))
            || !$stake->isEqualTo($entry->amount)
            || $debitBefore === null || $debitAfter === null
            || !BigDecimal::of((string) $debitBefore)->isEqualTo($entry->balance_before)
            || !BigDecimal::of((string) $debitAfter)->isEqualTo($entry->balance_after)
            || !BigDecimal::of((string) $debitBefore)->minus($stake)->isEqualTo((string) $debitAfter)) {
            throw new AcceptanceReviewConflictException('Original sportsbook debit amount does not match.');
        }

        $key = 'sportsbook_acceptance_refund:' . $reference;
        $this->wallets->assertNoDrift($context, 'sportsbook_acceptance_before_refund', ['acceptance_ref' => $reference]);
        $before = $this->wallets->currentDecimalBalance($context);
        $after = (string) BigDecimal::of($before)->plus($stake);
        $refund = CmsUserTransaction::query()->create([
            'cms_user_id' => $context->ownerWalletId(),
            'transactionId' => $key,
            'ref_transaction_id' => $debit->transactionId,
            'round_id' => $reference,
            'provider_transaction_id' => $reference,
            'event_type' => 'sportsbook_acceptance_refund',
            'action' => TransactionActionEnum::Rollback,
            'status' => TransactionStatusEnum::Success,
            'amount' => $amount,
            'currency' => $currency,
            'source_wallet_account_id' => $walletId,
            'source_wallet_type' => $context->walletType(),
            'source_currency' => $currency,
            'before_balance' => $before,
            'after_balance' => $after,
            'acceptor' => 'sportsbook',
        ]);
        $this->wallets->projectDecimalBalance($context, $after);
        $this->wallets->recordDecimalLedgerEntry(
            $context, 'sportsbook_acceptance_refunded', 'credit', $amount, $before, $after, $key, $refund,
            [
                'acceptance_ref' => $reference,
                'acceptance_id' => (int) $acceptance->id,
                'original_debit_transaction_id' => (int) $debit->id,
                'original_debit_ledger_id' => (int) $entry->id,
            ]
        );
    }

    private function debitAcceptanceStake(BettingAcceptance $acceptance, array $attributes, ?SpendableWalletContext $walletContext = null): void
    {
        $walletContext = $walletContext ?: $this->resolveWalletContext([
            'cms_user_id' => $acceptance->cms_user_id,
            'currency' => $acceptance->currency,
        ], true);

        if (!$walletContext instanceof SpendableWalletContext) {
            throw new RuntimeException('Sportsbook financial wallet context is required.');
        }

        $this->wallets->assertNoDrift($walletContext, 'sportsbook_acceptance_before_debit', [
            'acceptance_ref' => $acceptance->acceptance_ref,
        ]);

        $walletUser = $walletContext->ownerUser();
        $stakeAmount = $this->wallets->decimalForContext($walletContext, (string) $acceptance->stake_amount);
        $beforeBalance = $this->wallets->currentDecimalBalance($walletContext);

        if (BigDecimal::of($beforeBalance)->isLessThan($stakeAmount)) {
            throw new InsufficientBalanceException(
                $beforeBalance,
                $stakeAmount,
                (string) ($acceptance->currency ?: $walletUser->currency ?: 'TRY')
            );
        }

        $afterBalance = (string) BigDecimal::of($beforeBalance)->minus($stakeAmount);
        $transactionId = (string) Str::uuid();

        $transaction = CmsUserTransaction::query()->create([
            'cms_user_id' => $walletContext->ownerWalletId(),
            'transactionId' => $transactionId,
            'ref_transaction_id' => $acceptance->acceptance_ref,
            'round_id' => $acceptance->acceptance_ref,
            'provider_transaction_id' => $acceptance->acceptance_ref,
            'event_type' => 'sportsbook_acceptance',
            'action' => 4,
            'status' => 1,
            'amount' => $stakeAmount,
            'currency' => $acceptance->currency ?: $walletUser->currency ?: 'TRY',
            'source_wallet_account_id' => $walletContext->walletAccountId(),
            'source_wallet_type' => $walletContext->walletType(),
            'source_currency' => $walletContext->currency(),
            'before_balance' => $beforeBalance,
            'after_balance' => $afterBalance,
            'acceptor' => 'sportsbook',
        ]);

        $this->wallets->projectDecimalBalance($walletContext, $afterBalance);
        /* audit metadata preserved in source ledger/activity below: [
            'acceptance_ref' => $acceptance->acceptance_ref,
            'transaction_id' => $transactionId,
            'source' => 'sportsbook_acceptance',
        ]; */

        $this->recordAcceptanceLedgerAudit($acceptance, $walletContext, $transaction, $stakeAmount, $beforeBalance, $afterBalance);

        $this->activityLogService->logAcceptance(
            $acceptance,
            'acceptance.wallet_debited',
            'Acceptance stake reserved against the customer wallet.',
            array_merge([
                'cms_user_id' => $walletUser->id,
                'transaction_id' => $transactionId,
                'before_balance' => $beforeBalance,
                'after_balance' => $afterBalance,
                'stake_amount' => $stakeAmount,
                'currency' => $acceptance->currency,
            ], $walletContext->auditPayload())
        );
    }

    private function recordAcceptanceLedgerAudit(
        BettingAcceptance $acceptance,
        SpendableWalletContext $walletContext,
        CmsUserTransaction $transaction,
        string $stakeAmount,
        string $beforeBalance,
        string $afterBalance
    ): void {

        $this->wallets->recordDecimalLedgerEntry(
            $walletContext,
            'sportsbook_acceptance_debited',
            'debit',
            $stakeAmount,
            $beforeBalance,
            $afterBalance,
            'sportsbook_acceptance:' . (string) $acceptance->acceptance_ref,
            $transaction,
            [
                'audit_only' => true,
                'acceptance_id' => (int) $acceptance->id,
                'acceptance_ref' => (string) $acceptance->acceptance_ref,
                'acceptance_source' => (string) $acceptance->acceptance_source,
                'acceptance_contract_version' => (string) $acceptance->acceptance_contract_version,
                'cms_user_transaction_id' => (int) $transaction->id,
                'stake_amount' => $stakeAmount,
                'accepted_price' => (float) $acceptance->accepted_price,
            ]
        );
    }

    private function isModernBettingAcceptance(BettingAcceptance $acceptance): bool
    {
        return in_array([
            (string) $acceptance->acceptance_contract_version,
            (string) $acceptance->acceptance_source,
        ], [
            ['adapter_vps_v2', 'adapter_vps'],
            ['social_market_v1', 'social_market'],
            ['social_feed_v1', 'social_feed'],
        ], true);
    }

    private function resolveWalletContext(array $attributes, bool $lockForUpdate = true): ?SpendableWalletContext
    {
        $cmsUserId = isset($attributes['cms_user_id']) ? (int) $attributes['cms_user_id'] : 0;
        if ($cmsUserId <= 0) {
            return null;
        }

        $user = CmsUser::query()->find($cmsUserId);
        if ($user instanceof CmsUser) {
            try {
                $activeWallet = app(\App\Services\Wallet\WalletPlayabilityService::class)
                    ->assertForUser('sportsbook', $user, [], $lockForUpdate);
            } catch (\App\Services\Wallet\Exceptions\UnsupportedProductWalletException $exception) {
                throw new UnsupportedWalletForProductException($exception->getMessage(), $exception->details());
            }
            if ($activeWallet instanceof WalletAccount) {
                $capability = $this->productWallets->evaluate('sportsbook', $activeWallet);
                if (!(bool) ($capability['supported'] ?? false)) {
                    throw new UnsupportedWalletForProductException(
                        trans('wallet_playability.unsupported_wallet'),
                        $this->productWallets->resolveForUser('sportsbook', $user) + [
                            'active_wallet' => $this->assetWallets->serializeWalletAccount($activeWallet, $user),
                        ]
                    );
                }

                $attributes['currency'] = (string) $activeWallet->asset;
            }

            if ($activeWallet instanceof WalletAccount) {
                return $this->wallets->resolveForWallet($user, $activeWallet, $lockForUpdate);
            }
        }
        
        return null;
    }

    private function normalizeWalletAttributes(array $attributes, ?SpendableWalletContext $walletContext): array
    {
        if (!$walletContext instanceof SpendableWalletContext) {
            return $attributes;
        }

        $actingUser = isset($attributes['cms_user_id']) ? CmsUser::query()->find((int) $attributes['cms_user_id']) : null;
        $activeWallet = $actingUser instanceof CmsUser
            ? $this->activeWallets->resolveForUser($actingUser)
            : null;

        $attributes['cms_user_id'] = $walletContext->ownerWalletId();
        $attributes['root_user_id'] = $walletContext->rootWalletId();
        $attributes['currency'] = (string) $walletContext->currency();
        $attributes['context'] = array_merge($attributes['context'] ?? [], $walletContext->auditPayload(), [
            'product_type' => 'sportsbook',
            'wallet_account_id' => $walletContext->walletAccountId(),
            'wallet_type' => $walletContext->walletType(),
            'wallet_currency' => $walletContext->currency(),
            'active_wallet_id' => $activeWallet?->id,
            'is_certification' => $activeWallet instanceof WalletAccount
                && app(ProductionCertificationContext::class)->isCertificationWallet($activeWallet),
            'non_redeemable' => $activeWallet instanceof WalletAccount
                && app(ProductionCertificationContext::class)->isCertificationWallet($activeWallet),
        ]);

        return $attributes;
    }
}
