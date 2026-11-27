<?php

namespace App\Services\Wallet;

use App\Models\CmsUser;
use App\Models\WalletAccount;

class CanonicalWalletContext
{
    /**
     * @param  CmsUser[]  $projectionUsers
     */
    public function __construct(
        private CmsUser $rootUser,
        private CmsUser $ownerUser,
        private CmsUser $contextUser,
        private string $currency,
        private array $projectionUsers = [],
        private ?WalletAccount $walletAccount = null,
    ) {
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

    public function walletAccount(): ?WalletAccount
    {
        return $this->walletAccount;
    }

    public function walletAccountId(): ?int
    {
        return $this->walletAccount instanceof WalletAccount
            ? (int) $this->walletAccount->id
            : null;
    }

    public function hasAuthoritativeWalletBalance(): bool
    {
        return $this->walletAccount instanceof WalletAccount
            && (int) data_get($this->walletAccount->meta, 'authoritative_balance_version', 0) >= 2;
    }

    public function syncWalletDecimalBalance(string $balance, array $meta): void
    {
        if (!$this->walletAccount instanceof WalletAccount) {
            return;
        }

        $this->walletAccount->forceFill([
            'available_balance' => $balance,
            'last_synced_at' => now(),
            'meta' => $meta,
        ]);
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

    /**
     * @return int[]
     */
    public function projectionWalletIds(): array
    {
        return collect($this->projectionUsers)
            ->prepend($this->ownerUser)
            ->map(fn (CmsUser $user) => (int) $user->id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * cms_users.balance is a compatibility mirror for the root user only, and
     * only when its configured currency matches this wallet's asset.
     *
     * @return int[]
     */
    public function compatibilityMirrorUserIds(): array
    {
        return strtoupper((string) $this->rootUser->currency) === strtoupper($this->currency)
            ? [(int) $this->rootUser->id]
            : [];
    }

    public function ownerBalance(): float
    {
        if ($this->hasAuthoritativeWalletBalance()) {
            return (float) $this->walletAccount->available_balance;
        }

        return strtoupper((string) $this->ownerUser->currency) === strtoupper($this->currency)
            ? (float) $this->ownerUser->balance
            : 0.0;
    }

    public function syncProjectedDecimalBalance(string $balance): void
    {
        $mirrorIds = $this->compatibilityMirrorUserIds();
        foreach (collect($this->projectionUsers)->push($this->rootUser)->push($this->ownerUser)->push($this->contextUser)->unique('id') as $user) {
            if (in_array((int) $user->id, $mirrorIds, true)) {
                $user->forceFill(['balance' => $balance]);
            }
        }
    }

    public function syncProjectedBalance(float $balance): void
    {
        $normalized = round($balance, 2);

        $this->syncProjectedDecimalBalance((string) $normalized);
    }

    public function auditPayload(): array
    {
        return [
            'wallet_root_user_id' => $this->rootWalletId(),
            'wallet_owner_user_id' => $this->ownerWalletId(),
            'wallet_context_user_id' => $this->contextWalletId(),
            'wallet_currency' => $this->currency(),
            'wallet_projection_user_ids' => $this->projectionWalletIds(),
            'wallet_compatibility_mirror_user_ids' => $this->compatibilityMirrorUserIds(),
            'wallet_account_id' => $this->walletAccountId(),
            'wallet_balance_authority' => $this->hasAuthoritativeWalletBalance()
                ? 'wallet_accounts.available_balance'
                : 'legacy_currency_mirror',
        ];
    }
}
