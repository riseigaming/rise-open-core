<?php

namespace App\Services\Wallet;

use App\Models\CmsUser;
use App\Models\WalletAccount;

class SpendableWalletContext
{
    public function __construct(
        private readonly WalletAccount $walletAccount,
        private readonly CmsUser $rootUser,
        private readonly CmsUser $ownerUser,
        private readonly CmsUser $contextUser,
        private readonly string $currency,
        private readonly ?CanonicalWalletContext $canonicalContext = null,
    ) {
    }

    public function walletAccount(): WalletAccount
    {
        return $this->walletAccount;
    }

    public function walletAccountId(): int
    {
        return (int) $this->walletAccount->id;
    }

    public function walletType(): string
    {
        return (string) $this->walletAccount->wallet_type;
    }

    public function isPrimaryBalance(): bool
    {
        return $this->walletType() === 'primary_balance';
    }

    public function isAssetWallet(): bool
    {
        return !$this->isPrimaryBalance();
    }

    public function rootUser(): CmsUser
    {
        return $this->rootUser;
    }

    public function ownerUser(): CmsUser
    {
        return $this->ownerUser;
    }

    public function contextUser(): CmsUser
    {
        return $this->contextUser;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function rootWalletId(): int
    {
        return (int) $this->rootUser->id;
    }

    public function ownerWalletId(): int
    {
        return (int) $this->ownerUser->id;
    }

    public function contextWalletId(): int
    {
        return (int) $this->contextUser->id;
    }

    public function canonicalContext(): ?CanonicalWalletContext
    {
        return $this->canonicalContext;
    }

    public function ownerBalance(): float
    {
        return $this->isPrimaryBalance() && $this->canonicalContext instanceof CanonicalWalletContext
            ? $this->canonicalContext->ownerBalance()
            : (float) $this->walletAccount->available_balance;
    }

    public function precision(): int
    {
        return $this->isPrimaryBalance() ? 2 : 8;
    }

    public function projectionWalletIds(): array
    {
        if ($this->canonicalContext instanceof CanonicalWalletContext) {
            return $this->canonicalContext->projectionWalletIds();
        }

        return [(int) $this->ownerUser->id];
    }

    public function auditPayload(): array
    {
        return [
            'wallet_root_user_id' => $this->rootWalletId(),
            'wallet_owner_user_id' => $this->ownerWalletId(),
            'wallet_context_user_id' => $this->contextWalletId(),
            'wallet_currency' => $this->currency(),
            'wallet_projection_user_ids' => $this->projectionWalletIds(),
            'wallet_account_id' => $this->walletAccountId(),
            'wallet_type' => $this->walletType(),
            'active_wallet_id' => $this->walletAccountId(),
        ];
    }
}
