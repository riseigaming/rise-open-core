<?php

namespace App\Services\Wallet;

use App\Models\CmsUser;
use App\Models\CmsUserTransaction;
use App\Models\WalletAccount;
use App\Models\WalletLedgerEntry;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;

class SpendableWalletService
{
    public function __construct(
        private readonly AssetWalletService $assetWallets,
        private readonly CanonicalWalletService $canonicalWallets
    ) {
    }

    public function resolveForWallet(CmsUser $user, WalletAccount $wallet, bool $lockForUpdate = false, ?int $contextUserId = null): ?SpendableWalletContext
    {
        $owner = $this->assetWallets->resolveOwnerUser($user);
        $resolvedWallet = $this->assetWallets->findWalletForOwner($owner, (int) $wallet->id, $lockForUpdate);
        $contextUser = $this->resolveContextUser($owner, $contextUserId);
        if (!$resolvedWallet instanceof WalletAccount || !$contextUser instanceof CmsUser) {
            return null;
        }

        if ((string) $resolvedWallet->wallet_type === 'primary_balance') {
            $canonical = $this->canonicalWallets->resolveForUser($contextUser, (string) $resolvedWallet->asset, $lockForUpdate);
            if (!$canonical instanceof CanonicalWalletContext
                || (int) $canonical->walletAccount()?->id !== (int) $resolvedWallet->id) {
                return null;
            }

            return new SpendableWalletContext(
                walletAccount: $resolvedWallet,
                rootUser: $canonical->rootUser(),
                ownerUser: $canonical->ownerUser(),
                contextUser: $canonical->contextUser(),
                currency: (string) $resolvedWallet->asset,
                canonicalContext: $canonical
            );
        }

        return new SpendableWalletContext(
            walletAccount: $resolvedWallet,
            rootUser: $owner,
            ownerUser: $owner,
            contextUser: $contextUser ?: $owner,
            currency: (string) $resolvedWallet->asset,
            canonicalContext: null
        );
    }

    public function resolveForStoredContext(
        CmsUser $user,
        ?int $walletAccountId,
        ?string $walletType,
        ?string $currency,
        ?int $contextUserId = null,
        bool $lockForUpdate = false
    ): ?SpendableWalletContext {
        $owner = $this->assetWallets->resolveOwnerUser($user);
        $resolvedCurrency = strtoupper(trim((string) ($currency ?: $user->currency ?: $owner->currency ?: 'TRY')));
        $resolvedType = (string) ($walletType ?: 'primary_balance');

        if ($walletAccountId) {
            $wallet = $this->assetWallets->findWalletForOwner($owner, $walletAccountId, $lockForUpdate);
            // Preserve the legacy stale-ID recovery contract only for an absent
            // row and an already-existing canonical default of this owner/asset.
            // A foreign existing ID must never silently redirect to another wallet.
            if (!$wallet instanceof WalletAccount && $resolvedType === 'primary_balance'
                && !WalletAccount::query()->whereKey($walletAccountId)->exists()) {
                $fallback = WalletAccount::query()->where('cms_user_id', $owner->id)
                    ->where('wallet_type', 'primary_balance')->where('wallet_key', 'default')
                    ->where('asset', $resolvedCurrency)->where('status', 'active');
                if ($lockForUpdate) {
                    $fallback->lockForUpdate();
                }
                $wallet = $fallback->first();
            }
            if (!$wallet instanceof WalletAccount || (string) $wallet->wallet_type !== $resolvedType
                || strtoupper((string) $wallet->asset) !== $resolvedCurrency) {
                return null;
            }

            return $this->resolveForWallet($user, $wallet, $lockForUpdate, $contextUserId);
        }

        if ($resolvedType === 'primary_balance') {
            $wallet = $this->assetWallets->ensurePrimaryBalanceWallet($owner, $resolvedCurrency, $lockForUpdate);

            return $wallet instanceof WalletAccount
                ? $this->resolveForWallet($user, $wallet, $lockForUpdate, $contextUserId)
                : null;
        }

        $wallet = $this->assetWallets->ensureCryptoWallet($owner, $resolvedCurrency, $lockForUpdate);

        return $wallet instanceof WalletAccount
            ? $this->resolveForWallet($user, $wallet, $lockForUpdate, $contextUserId)
            : null;
    }

    public function currentBalance(SpendableWalletContext $context): float
    {
        if ($context->isPrimaryBalance() && $context->canonicalContext() instanceof CanonicalWalletContext) {
            return round($this->canonicalWallets->currentBalance($context->canonicalContext()), $context->precision());
        }

        return round((float) $context->walletAccount()->available_balance, $context->precision());
    }

    /** Exact value for mutation paths; currentBalance is the legacy numeric API. */
    public function currentDecimalBalance(SpendableWalletContext $context): string
    {
        if ($context->isPrimaryBalance() && $context->canonicalContext() instanceof CanonicalWalletContext) {
            return $this->canonicalWallets->currentDecimalBalance($context->canonicalContext());
        }

        return $this->decimalForContext($context, (string) $context->walletAccount()->available_balance);
    }

    public function decimalForContext(SpendableWalletContext $context, string $amount): string
    {
        if (!preg_match('/\A[+-]?\d{1,16}(?:\.\d{1,18})?\z/', $amount)) {
            throw new \DomainException('Wallet amount must be a bounded decimal string.');
        }
        return (string) BigDecimal::of($amount)->toScale($context->precision(), RoundingMode::UNNECESSARY);
    }

    public function projectDecimalBalance(SpendableWalletContext $context, string $balance): void
    {
        app(\App\Services\Finance\PrelaunchFinanceWriteGate::class)->assertWritable();
        $normalized = $this->decimalForContext($context, $balance);
        if (BigDecimal::of($normalized)->isNegative()) {
            throw new \DomainException('A spendable balance cannot be negative.');
        }
        if ($context->isPrimaryBalance() && $context->canonicalContext() instanceof CanonicalWalletContext) {
            $this->canonicalWallets->projectDecimalBalance($context->canonicalContext(), $normalized);

            return;
        }

        $wallet = WalletAccount::query()->whereKey($context->walletAccountId())->lockForUpdate()->firstOrFail();
        $wallet->forceFill(['available_balance' => $normalized, 'last_synced_at' => now()])->save();
        $context->walletAccount()->setAttribute('available_balance', $normalized);
    }

    /** Caller holds the wallet lock inside the financial transaction. */
    public function recordDecimalLedgerEntry(
        SpendableWalletContext $context,
        string $entryType,
        string $direction,
        string $amount,
        string $before,
        string $after,
        string $idempotencyKey,
        ?CmsUserTransaction $transaction = null,
        array $meta = [],
        ?string $sourceType = null,
        ?string $sourceReference = null
    ): void {
        $amount = $this->decimalForContext($context, $amount);
        $before = $this->decimalForContext($context, $before);
        $after = $this->decimalForContext($context, $after);
        if (!in_array($direction, ['credit', 'debit'], true) || $idempotencyKey === ''
            || BigDecimal::of($amount)->isNegative() || BigDecimal::of($before)->isNegative()
            || BigDecimal::of($after)->isNegative()) {
            throw new \DomainException('Invalid wallet ledger effect.');
        }
        $expected = $direction === 'credit'
            ? BigDecimal::of($before)->plus($amount) : BigDecimal::of($before)->minus($amount);
        if (!$expected->isEqualTo($after)) {
            throw new \DomainException('Wallet ledger arithmetic mismatch.');
        }

        $values = [
            'cms_user_id' => $context->ownerWalletId(),
            'entry_type' => $entryType,
            'direction' => $direction,
            'status' => 'applied',
            'amount' => $amount,
            'balance_before' => $before,
            'balance_after' => $after,
            'asset' => $context->currency(),
            'source_type' => $sourceType ?: ($transaction ? 'cms_user_transaction' : 'wallet_context'),
            'source_reference' => $sourceReference ?: ($transaction ? (string) $transaction->transactionId : $idempotencyKey),
        ];
        // Missing ledger schema must fail, not silently credit without evidence.
        $entry = WalletLedgerEntry::query()->firstOrCreate(
            ['wallet_account_id' => $context->walletAccountId(), 'idempotency_key' => $idempotencyKey],
            array_merge($values, ['applied_at' => now(), 'meta' => array_merge($context->auditPayload(), $meta)])
        );
        foreach ($values as $field => $value) {
            $matches = in_array($field, ['amount', 'balance_before', 'balance_after'], true)
                ? BigDecimal::of((string) $entry->$field)->isEqualTo($value)
                : (string) $entry->$field === (string) $value;
            if (!$matches) {
                throw new \DomainException('Wallet ledger idempotency conflict.');
            }
        }
    }

    public function roundForContext(SpendableWalletContext $context, float $amount): float
    {
        return round($amount, $context->precision());
    }

    public function assertNoDrift(SpendableWalletContext $context, string $source, array $meta = []): void
    {
        if ($context->isPrimaryBalance() && $context->canonicalContext() instanceof CanonicalWalletContext) {
            $this->canonicalWallets->assertNoDrift($context->canonicalContext(), $source, $meta);
        }
    }

    public function projectBalance(SpendableWalletContext $context, float $balance, array $meta = []): void
    {
        $normalized = $this->roundForContext($context, $balance);

        if ($context->isPrimaryBalance() && $context->canonicalContext() instanceof CanonicalWalletContext) {
            $this->canonicalWallets->projectBalance($context->canonicalContext(), $normalized, $meta);

            return;
        }

        $wallet = WalletAccount::query()->whereKey($context->walletAccountId())->lockForUpdate()->first();
        if ($wallet instanceof WalletAccount) {
            $wallet->forceFill([
                'available_balance' => round($normalized, 8),
                'last_synced_at' => now(),
            ])->save();
        }
    }

    public function recordLedgerEntry(
        SpendableWalletContext $context,
        string $entryType,
        string $direction,
        float $amount,
        float $before,
        float $after,
        string $idempotencyKey,
        ?CmsUserTransaction $transaction = null,
        array $meta = [],
        ?string $sourceType = null,
        ?string $sourceReference = null
    ): void {
        if (!$this->assetWallets->tablesAvailable()) {
            return;
        }

        WalletLedgerEntry::query()->firstOrCreate(
            [
                'wallet_account_id' => $context->walletAccountId(),
                'idempotency_key' => $idempotencyKey,
            ],
            [
                'cms_user_id' => $context->ownerWalletId(),
                'entry_type' => $entryType,
                'direction' => $direction,
                'status' => 'applied',
                'amount' => round($amount, 8),
                'balance_before' => round($before, 8),
                'balance_after' => round($after, 8),
                'asset' => $context->currency(),
                'source_type' => $sourceType ?: ($transaction instanceof CmsUserTransaction ? 'cms_user_transaction' : 'wallet_context'),
                'source_reference' => $sourceReference ?: ($transaction instanceof CmsUserTransaction ? (string) $transaction->transactionId : $idempotencyKey),
                'applied_at' => now(),
                'meta' => array_merge($context->auditPayload(), $meta),
            ]
        );
    }

    private function resolveContextUser(CmsUser $owner, ?int $contextUserId): ?CmsUser
    {
        $resolvedId = (int) ($contextUserId ?? 0);
        if ($resolvedId <= 0) {
            return $owner;
        }

        return CmsUser::query()
            ->where(function ($query) use ($owner, $resolvedId) {
                $query->where('id', $resolvedId)
                    ->where(function ($family) use ($owner) {
                        $family->where('id', $owner->id)
                            ->orWhere('parent_id', $owner->id);
                    });
            })
            ->first();
    }
}
