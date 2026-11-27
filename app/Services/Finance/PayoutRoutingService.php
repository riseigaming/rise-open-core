<?php

namespace App\Services\Finance;

use App\Enum\TransactionActionEnum;
use App\Enum\TransactionStatusEnum;
use App\Models\CmsUser;
use App\Models\CmsUserTransaction;
use App\Models\WalletAccount;
use App\Models\WalletLedgerEntry;
use App\Services\Wallet\AssetWalletService;
use App\Services\Wallet\SpendableWalletContext;
use App\Services\Wallet\SpendableWalletService;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

/**
 * Adapted from WinX app/Services/Finance/PayoutRoutingService.php, frozen S0
 * 1c151f21af58c1cc738f67f9e7a10953e6a6e6a2; retains Rise's local refund fixes.
 * Customer request/dispatch remain BLOCKED: Rise lacks the source customer request,
 * WithdrawalMethodReadiness, partner schema/model, payouts/finance_harness config
 * and FinanceGatewayClient binding. A routing snapshot is not payout authority.
 */
class PayoutRoutingService
{
    public function __construct(
        private readonly AssetWalletService $assetWallets,
        private readonly SpendableWalletService $spendableWallets,
    ) {
    }

    /**
     * S0 lines 251-275: record the caller's already projected original debit.
     * Never starts a standalone financial operation or projects a balance.
     * Caller must roll back its creation/projection transaction on any failure.
     */
    public function markRequestCreated(CmsUserTransaction $transaction): void
    {
        if (!$transaction->exists || !$transaction->getKey() || $transaction->isDirty()
            || DB::transactionLevel() < 1
            || $transaction->getConnection()->getName() !== DB::connection()->getName()) {
            throw new \DomainException('Payout request requires a persisted source in the caller transaction.');
        }
        app(PrelaunchFinanceWriteGate::class)->assertWritable();

        DB::transaction(function () use ($transaction): void {
            $locked = CmsUserTransaction::query()->whereKey($transaction->getKey())->lockForUpdate()->firstOrFail();
            foreach (['transactionId', 'cms_user_id', 'action', 'status', 'source_wallet_account_id',
                'source_wallet_type', 'source_currency', 'transaction_asset', 'amount', 'before_balance',
                'after_balance', 'exact_amount', 'exact_before_balance', 'exact_after_balance'] as $field) {
                if ((string) $locked->getRawOriginal($field) !== (string) $transaction->getRawOriginal($field)) {
                    throw new \DomainException('Payout request source changed.');
                }
            }
            if ((int) $locked->action !== TransactionActionEnum::Withdraw
                || (int) $locked->status !== TransactionStatusEnum::Pending
                || trim((string) $locked->transactionId) === ''
                || !$locked->source_wallet_account_id || !$locked->source_wallet_type
                || !$locked->source_currency || !$locked->user instanceof CmsUser
                || !$this->assetWallets->tablesAvailable()) {
                throw new \DomainException('Pending payout source is unavailable.');
            }
            if (CmsUserTransaction::query()->where('transactionId', $locked->transactionId)
                ->lockForUpdate()->get()->count() !== 1) {
                throw new \DomainException('Payout request reference is ambiguous.');
            }
            $owner = $this->assetWallets->resolveOwnerUser($locked->user);
            $wallet = $this->assetWallets->findWalletForOwner($owner, (int) $locked->source_wallet_account_id, true);
            if (!$wallet instanceof WalletAccount
                || (string) $wallet->wallet_type !== (string) $locked->source_wallet_type
                || (string) $wallet->asset !== (string) $locked->source_currency
                || (string) $locked->transaction_asset !== (string) $wallet->asset) {
                throw new \DomainException('Payout source wallet does not match.');
            }
            // No resolveForStoredContext fallback: missing wallets must not be provisioned.
            $context = $this->spendableWallets->resolveForWallet($locked->user, $wallet, true,
                (int) data_get($locked->payout_context_snapshot, 'wallet_context.wallet_context_user_id', $locked->cms_user_id));
            if (!$context instanceof SpendableWalletContext) {
                throw new \DomainException('Payout source owner is unavailable.');
            }
            $amount = $this->spendableWallets->decimalForContext($context, (string) $locked->amount);
            $before = $this->spendableWallets->decimalForContext($context, (string) $locked->before_balance);
            $after = $this->spendableWallets->decimalForContext($context, (string) $locked->after_balance);
            if (!BigDecimal::of($amount)->isPositive() || BigDecimal::of($after)->isNegative()
                || !BigDecimal::of($before)->minus($amount)->isEqualTo($after)
                || !$locked->hasExactPrecisionSnapshot()
                || !BigDecimal::of($amount)->isEqualTo($locked->exactAmountValue())
                || !BigDecimal::of($before)->isEqualTo($locked->exactBeforeBalanceValue())
                || !BigDecimal::of($after)->isEqualTo($locked->exactAfterBalanceValue())) {
                throw new \DomainException('Payout debit amounts do not match.');
            }
            $key = 'payout_request_created:' . $locked->transactionId;
            // Keys are unique only per wallet. Inspect reference/key collisions globally.
            $entries = WalletLedgerEntry::query()->where(function ($query) use ($locked, $key): void {
                $query->where('idempotency_key', $key)->orWhere(function ($source) use ($locked): void {
                    $source->where('source_type', 'cms_user_transaction')
                        ->where('source_reference', $locked->transactionId);
                });
            })->lockForUpdate()->get();
            if ($entries->isNotEmpty()) {
                $entry = $entries->first();
                if ($entries->count() !== 1 || (int) $entry->wallet_account_id !== $context->walletAccountId()
                    || (int) $entry->cms_user_id !== $context->ownerWalletId()
                    || $entry->asset !== $context->currency() || $entry->idempotency_key !== $key
                    || $entry->source_type !== 'cms_user_transaction' || $entry->source_reference !== $locked->transactionId
                    || $entry->entry_type !== 'payout_request_created' || $entry->direction !== 'debit'
                    || $entry->status !== 'applied'
                    || !BigDecimal::of((string) $entry->amount)->isEqualTo($amount)
                    || !BigDecimal::of((string) $entry->balance_before)->isEqualTo($before)
                    || !BigDecimal::of((string) $entry->balance_after)->isEqualTo($after)) {
                    throw new \DomainException('Payout debit ledger conflicts with the source.');
                }
                return; // Matched replay: no second ledger or balance application.
            }
            // Require funded pre-debit provenance and the caller's actual projection.
            // An unledgered opening balance cannot establish an original debit.
            $funded = WalletLedgerEntry::query()->where('wallet_account_id', $wallet->id)
                ->where('status', 'applied')
                ->selectRaw("COALESCE(SUM(CASE direction WHEN 'credit' THEN amount ELSE -amount END), 0) AS funded")
                ->value('funded');
            if (!BigDecimal::of((string) $funded)->isEqualTo($before)
                || !BigDecimal::of((string) $wallet->available_balance)->isEqualTo($after)
                || !BigDecimal::of($this->spendableWallets->currentDecimalBalance($context))->isEqualTo($after)) {
                throw new \DomainException('Payout original debit projection is not proven.');
            }
            $this->spendableWallets->recordDecimalLedgerEntry($context, 'payout_request_created', 'debit',
                $amount, $before, $after, $key, $locked, [
                    'requested_payout_currency' => $locked->requested_payout_currency ?: $locked->currency,
                    'route_type' => $locked->payout_route_type,
                ]);
        });
    }

    /**
     * Read-only subset of S0 detailForTransaction. Uses only stored evidence;
     * unlike S0, missing source/route/settlement is unknown, never a default
     * primary wallet, same-currency route or inferred successful settlement.
     * Does not resolve/provision wallets, inspect providers or authorize dispatch.
     */
    public function detailForTransaction(CmsUserTransaction $transaction): array
    {
        return [
            'exact_amount' => $transaction->exactAmountValue(),
            'exact_before_balance' => $transaction->exactBeforeBalanceValue(),
            'exact_after_balance' => $transaction->exactAfterBalanceValue(),
            'exact_available' => $transaction->hasExactPrecisionSnapshot(),
            'source_wallet_id' => $transaction->source_wallet_account_id ? (int) $transaction->source_wallet_account_id : null,
            'source_wallet_type' => $transaction->source_wallet_type ?: null,
            'source_currency' => $transaction->source_currency ?: null,
            'payout_currency' => $transaction->requested_payout_currency ?: null,
            'route_type' => $transaction->payout_route_type ?: null,
            'conversion_required' => $transaction->payout_requires_conversion,
            'conversion_policy' => $transaction->payout_conversion_policy ?: null,
            'route_reason' => $transaction->payout_route_reason ?: null,
            'execution_wallet_id' => $transaction->execution_wallet_account_id ? (int) $transaction->execution_wallet_account_id : null,
            'execution_currency' => $transaction->execution_currency ?: null,
            'final_wallet_id' => $transaction->settled_wallet_account_id ? (int) $transaction->settled_wallet_account_id : null,
            'final_currency' => $transaction->settled_currency ?: null,
        ];
    }

    public function refundToSource(
        CmsUserTransaction $transaction,
        string|int|float|null $amount = null,
        string $reason = 'payout_refund',
        array $auditMeta = []
    ): string
    {
        // Retain Rise's synthetic-only write boundary, including direct replays.
        app(PrelaunchFinanceWriteGate::class)->assertWritable();

        return DB::transaction(function () use ($transaction, $amount, $reason, $auditMeta) {
            $locked = CmsUserTransaction::query()->whereKey($transaction->id)->lockForUpdate()->firstOrFail();

            return $this->refundLockedSource($locked, $amount, $reason, $auditMeta);
        });
    }

    private function refundLockedSource(CmsUserTransaction $transaction, string|int|float|null $amount, string $reason, array $auditMeta = []): string
    {
        if ((int) $transaction->action !== TransactionActionEnum::Withdraw
            || !$this->assetWallets->tablesAvailable()
            || !$transaction->source_wallet_account_id
            || !$transaction->user instanceof CmsUser
            || trim((string) $transaction->transactionId) === '') {
            throw new \DomainException('Payout debit evidence is unavailable.');
        }

        // Never use the stored-context fallback here: a refund must return to
        // the existing wallet that actually funded this exact withdrawal.
        $owner = $this->assetWallets->resolveOwnerUser($transaction->user);
        $wallet = $this->assetWallets->findWalletForOwner($owner, (int) $transaction->source_wallet_account_id, true);
        $debitKey = 'payout_request_created:' . (string) $transaction->transactionId;
        // Catch cross-wallet key collisions and malformed source records too.
        $debits = WalletLedgerEntry::query()
            ->where(function ($query) use ($transaction, $debitKey): void {
                $query->where('idempotency_key', $debitKey)
                    ->orWhere(function ($source) use ($transaction): void {
                        $source->where('source_type', 'cms_user_transaction')
                            ->where('source_reference', (string) $transaction->transactionId)
                            ->where('entry_type', 'payout_request_created');
                    });
            })->lockForUpdate()->get();
        // The schema only makes keys unique within a wallet. A second wallet
        // carrying the same debit reference is ambiguous funding evidence.
        if ($debits->count() !== 1) {
            throw new \DomainException('Payout debit evidence is unavailable or ambiguous.');
        }
        $debit = $debits->first();
        $refundAmount = BigDecimal::of((string) ($amount ?? $transaction->amount));
        if (!$wallet instanceof WalletAccount || !$debit instanceof WalletLedgerEntry
            || (int) $debit->wallet_account_id !== (int) $wallet->id
            || $debit->idempotency_key !== $debitKey
            || $debit->source_type !== 'cms_user_transaction'
            || $debit->source_reference !== (string) $transaction->transactionId
            || $debit->entry_type !== 'payout_request_created'
            || $debit->direction !== 'debit' || $debit->status !== 'applied'
            || (string) $wallet->wallet_type !== (string) $transaction->source_wallet_type
            || strtoupper((string) $wallet->asset) !== strtoupper((string) $transaction->source_currency)
            || strtoupper((string) $debit->asset) !== strtoupper((string) $wallet->asset)
            || !$refundAmount->isPositive()
            || !$refundAmount->isEqualTo((string) $transaction->amount)
            || !$refundAmount->isEqualTo((string) $debit->amount)
            || !BigDecimal::of((string) $debit->balance_before)->minus((string) $debit->balance_after)->isEqualTo($refundAmount)
            || !BigDecimal::of((string) $transaction->before_balance)->isEqualTo((string) $debit->balance_before)
            || !BigDecimal::of((string) $transaction->after_balance)->isEqualTo((string) $debit->balance_after)
            || ($transaction->transaction_asset !== null
                && strtoupper((string) $transaction->transaction_asset) !== strtoupper((string) $wallet->asset))
            || ($transaction->hasExactPrecisionSnapshot()
                && (!BigDecimal::of($transaction->exactAmountValue())->isEqualTo($refundAmount)
                    || !BigDecimal::of($transaction->exactBeforeBalanceValue())->isEqualTo((string) $debit->balance_before)
                    || !BigDecimal::of($transaction->exactAfterBalanceValue())->isEqualTo((string) $debit->balance_after)))) {
            throw new \DomainException('Payout debit evidence does not match the source.');
        }
        $context = $this->spendableWallets->resolveForWallet(
            $transaction->user, $wallet, true,
            (int) data_get($debit->meta, 'wallet_context_user_id', $transaction->cms_user_id)
        );
        if (!$context instanceof SpendableWalletContext
            || (int) $debit->cms_user_id !== $context->ownerWalletId()
        ) {
            throw new \DomainException('Payout source owner does not match the debit.');
        }

        $idempotencyKey = 'payout_request_released:' . (string) $transaction->transactionId;
        // Include historical, reason-specific releases without rewriting them.
        $existingRefunds = WalletLedgerEntry::query()
            ->where(function ($query) use ($transaction, $idempotencyKey): void {
                $query->where('idempotency_key', $idempotencyKey)
                    ->orWhere(function ($source) use ($transaction): void {
                        $source->where('source_type', 'cms_user_transaction')
                            ->where('source_reference', (string) $transaction->transactionId)
                            ->where('entry_type', 'payout_request_released');
                    });
            })->lockForUpdate()->get();
        if ($existingRefunds->count() > 1) {
            throw new \DomainException('Existing payout refund evidence is ambiguous.');
        }
        $existingRefund = $existingRefunds->first();

        if ($existingRefund instanceof WalletLedgerEntry) {
            if ($existingRefund->source_type !== 'cms_user_transaction'
                || $existingRefund->source_reference !== (string) $transaction->transactionId
                || $existingRefund->entry_type !== 'payout_request_released'
                || $existingRefund->direction !== 'credit' || $existingRefund->status !== 'applied'
                || (int) $existingRefund->wallet_account_id !== $context->walletAccountId()
                || (int) $existingRefund->cms_user_id !== $context->ownerWalletId()
                || (string) $existingRefund->asset !== $context->currency()
                || !$refundAmount->isEqualTo((string) $existingRefund->amount)
                || !BigDecimal::of((string) $existingRefund->balance_before)
                    ->plus((string) $existingRefund->amount)->isEqualTo((string) $existingRefund->balance_after)) {
                throw new \DomainException('Existing payout refund evidence does not match the source.');
            }
            return $this->spendableWallets->currentDecimalBalance($context);
        }

        $refundAmount = $this->spendableWallets->decimalForContext($context, (string) ($amount ?? $transaction->amount));
        $currentBalance = $this->spendableWallets->currentDecimalBalance($context);
        $refundBalance = (string) BigDecimal::of($currentBalance)->plus($refundAmount);

        $this->spendableWallets->projectDecimalBalance($context, $refundBalance);
        $this->spendableWallets->recordDecimalLedgerEntry(
            $context,
            'payout_request_released',
            'credit',
            $refundAmount,
            $currentBalance,
            $refundBalance,
            $idempotencyKey,
            $transaction,
            array_merge([
                'reason' => $reason,
                'requested_payout_currency' => $transaction->requested_payout_currency ?: $transaction->currency,
            ], $auditMeta)
        );

        $this->markSettled($transaction, [
            'stage' => $reason,
            'status' => 'refunded',
        ]);

        return $refundBalance;
    }

    private function markSettled(CmsUserTransaction $transaction, array $meta = []): void
    {
        DB::transaction(function () use ($transaction, $meta): void {
            $transaction = CmsUserTransaction::query()->whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            // A reversed withdrawal settles in the original funded wallet asset,
            // not the currency that the customer requested for the payout.
            $finalCurrency = $transaction->source_currency ?: $transaction->currency;
            $context = $this->spendableContextForTransaction($transaction, true);
            if (!$context instanceof SpendableWalletContext) {
                throw new \DomainException('Payout settlement source wallet is unavailable.');
            }

            $transaction->forceFill([
                'settled_wallet_account_id' => $transaction->source_wallet_account_id,
                'settled_currency' => $finalCurrency,
                'execution_context_snapshot' => $transaction->execution_context_snapshot ?: null,
            ])->save();

            if ($context instanceof SpendableWalletContext) {
                $this->spendableWallets->recordDecimalLedgerEntry(
                    $context,
                    'payout_execution_completed',
                    'debit',
                    0,
                    (string) $transaction->after_balance,
                    (string) $transaction->after_balance,
                    'payout_execution_completed:' . (string) $transaction->transactionId . ':' . (string) ($meta['status'] ?? 'completed'),
                    $transaction,
                    array_merge([
                        'stage' => 'payout_execution_completed',
                        'final_currency' => $finalCurrency,
                    ], [
                        'wallet_context' => $context->auditPayload(),
                    ], $meta)
                );
            }
        });
    }

    private function spendableContextForTransaction(CmsUserTransaction $transaction, bool $lockForUpdate = false): ?SpendableWalletContext
    {
        $transaction->loadMissing('user.parentAccount');
        $user = $transaction->user;
        if (!$user instanceof CmsUser) {
            return null;
        }

        return $this->spendableWallets->resolveForStoredContext(
            $user,
            $transaction->source_wallet_account_id ? (int) $transaction->source_wallet_account_id : null,
            $transaction->source_wallet_type ?: 'primary_balance',
            $transaction->source_currency ?: $transaction->currency ?: $user->currency,
            (int) data_get($transaction->payout_context_snapshot, 'wallet_context.wallet_context_user_id', $user->id),
            $lockForUpdate
        );
    }
}
