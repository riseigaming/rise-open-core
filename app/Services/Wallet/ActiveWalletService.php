<?php

namespace App\Services\Wallet;

use App\Services\Finance\FinanceCustomerMessage;
use App\Models\CmsUser;
use App\Models\WalletAccount;
use App\Models\WalletUserPreference;
use App\Services\Certification\ProductionCertificationContext;
use App\Services\Wallet\Exceptions\ActiveWalletChangeBlockedException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ActiveWalletService
{
    public function __construct(
        private readonly AssetWalletService $assetWallets,
        private readonly WalletChangeGuardService $guard,
        private readonly ProductionCertificationContext $certification
    ) {
    }

    public function tablesAvailable(): bool
    {
        return $this->assetWallets->tablesAvailable() && Schema::hasTable('wallet_user_preferences');
    }

    /** Resolve only an existing active wallet; never provision from a page render. */
    public function existingForUser(CmsUser $user): ?WalletAccount
    {
        if ($this->certification->isActiveFor($user)) {
            return $this->certification->walletFor($user);
        }

        if (!Schema::hasTable('wallet_accounts')) {
            return null;
        }

        $owner = $this->assetWallets->resolveOwnerUser($user);
        $defaultWallet = WalletAccount::query()
            ->where('cms_user_id', $owner->id)
            ->where('wallet_type', 'primary_balance')
            ->where('wallet_key', 'default')
            ->where('asset', strtoupper((string) $user->currency))
            ->where('status', 'active')
            ->first();

        if (Schema::hasTable('wallet_user_preferences')) {
            $preferredId = (int) WalletUserPreference::query()
                ->where('cms_user_id', $owner->id)
                ->value('active_wallet_account_id');
            if ($preferredId > 0) {
                $preferredWallet = WalletAccount::query()
                    ->where('cms_user_id', $owner->id)
                    ->whereKey($preferredId)
                    ->where('status', 'active')
                    ->first();
                if ($preferredWallet instanceof WalletAccount) {
                    return $preferredWallet;
                }
            }
        }

        return $defaultWallet;
    }

    public function resolveForUser(CmsUser $user, bool $lockForUpdate = false): ?WalletAccount
    {
        if ($this->certification->isActiveFor($user)) {
            return $this->certification->walletFor($user, $lockForUpdate)
                ?: $this->certification->ensureWalletFor($user, (string) ($user->currency ?: 'TRY'));
        }

        if (!$this->tablesAvailable()) {
            $wallet = $this->assetWallets->ensurePrimaryBalanceWallet($user, $user->currency, $lockForUpdate);
            return $wallet?->status === 'active' ? $wallet : null;
        }

        $owner = $this->assetWallets->resolveOwnerUser($user);
        $defaultWallet = $this->assetWallets->ensurePrimaryBalanceWallet($owner, $user->currency, $lockForUpdate);
        $this->assetWallets->ensureSelectableWallets($owner);

        $preferenceQuery = WalletUserPreference::query()->where('cms_user_id', $owner->id);
        if ($lockForUpdate) {
            $preferenceQuery->lockForUpdate();
        }

        $preference = $preferenceQuery->first();
        if ($preference instanceof WalletUserPreference && (int) $preference->active_wallet_account_id > 0) {
            $preferredWallet = $this->assetWallets->findWalletForOwner($owner, (int) $preference->active_wallet_account_id, $lockForUpdate);
            if ($preferredWallet instanceof WalletAccount && (string) $preferredWallet->status === 'active') {
                return $preferredWallet;
            }
        }

        return $defaultWallet?->status === 'active' ? $defaultWallet : null;
    }

    /** Preference history is retained; new gameplay must not silently spend the fallback. */
    public function resolutionForUser(CmsUser $user, bool $lockForUpdate = false): array
    {
        $wallet = $this->resolveForUser($user, $lockForUpdate);
        $preferredId = $this->tablesAvailable() && !$this->certification->isActiveFor($user)
            ? (int) WalletUserPreference::where('cms_user_id', $user->parent_id ?: $user->id)->value('active_wallet_account_id')
            : null;
        $fallback = $preferredId > 0 && $preferredId !== (int) $wallet?->id;

        return [
            'wallet' => $wallet,
            'preferred_wallet_account_id' => $preferredId ?: null,
            'fallback' => $fallback,
            'reason_code' => $fallback ? 'preferred_wallet_unavailable' : ($wallet ? null : 'wallet_unavailable'),
        ];
    }

    public function serializeForUser(CmsUser $user): ?array
    {
        $wallet = $this->resolveForUser($user);

        return $wallet ? $this->assetWallets->serializeWalletAccount($wallet, $user) : null;
    }

    public function switch(CmsUser $user, int $walletAccountId): array
    {
        if (!$this->tablesAvailable()) {
            return [
                'status' => 503,
                'message' => FinanceCustomerMessage::forCode('wallet_unavailable'),
            ];
        }

        if ($this->certification->isActiveFor($user)) {
            $certificationWallet = $this->certification->walletFor($user)
                ?: $this->certification->ensureWalletFor($user, (string) ($user->currency ?: 'TRY'));

            if (!$certificationWallet instanceof WalletAccount || (int) $certificationWallet->id !== $walletAccountId) {
                return [
                    'status' => 403,
                    'message' => FinanceCustomerMessage::forCode('request_unavailable'),
                ];
            }
        }

        $targetWallet = $this->assetWallets->findWalletForOwner($user, $walletAccountId, true);
        if (!$targetWallet instanceof WalletAccount || (string) $targetWallet->status !== 'active') {
            return [
                'status' => 404,
                'message' => FinanceCustomerMessage::forCode('wallet_not_found'),
            ];
        }

        if ($this->isCurrentWalletSelection($user, $targetWallet)) {
            return [
                'status' => 200,
                'message' => FinanceCustomerMessage::forCode('wallet_current'),
                'activeWallet' => $this->assetWallets->serializeWalletAccount($targetWallet, $user),
                'guard' => $this->guard->evaluate($user, $targetWallet),
            ];
        }

        $guardState = $this->guard->evaluate($user, $targetWallet);
        if (!(bool) ($guardState['allowed'] ?? false)) {
            throw new ActiveWalletChangeBlockedException(
                (string) ($guardState['message'] ?? config('product_wallets.default_switch_block_message')),
                (array) ($guardState['reasons'] ?? [])
            );
        }

        $currentWallet = $this->resolveForUser($user, true);
        if ($currentWallet instanceof WalletAccount && (int) $currentWallet->id === (int) $targetWallet->id
            && !$this->resolutionForUser($user)['fallback']) {
            return [
                'status' => 200,
                'message' => FinanceCustomerMessage::forCode('wallet_current'),
                'activeWallet' => $this->assetWallets->serializeWalletAccount($targetWallet, $user),
                'guard' => [
                    'allowed' => true,
                    'reasons' => [],
                    'message' => null,
                ],
            ];
        }

        $owner = $this->assetWallets->resolveOwnerUser($user);

        return DB::transaction(function () use ($owner, $targetWallet, $user) {
            WalletUserPreference::query()->updateOrCreate(
                ['cms_user_id' => $owner->id],
                [
                    'active_wallet_account_id' => $targetWallet->id,
                    'changed_at' => now(),
                    'meta' => [
                        'wallet_type' => $targetWallet->wallet_type,
                        'asset' => $targetWallet->asset,
                    ],
                ]
            );

            return [
                'status' => 200,
                'message' => FinanceCustomerMessage::forCode('wallet_updated'),
                'activeWallet' => $this->assetWallets->serializeWalletAccount($targetWallet, $user),
                'guard' => [
                    'allowed' => true,
                    'reasons' => [],
                    'message' => null,
                ],
            ];
        });
    }

    private function isCurrentWalletSelection(CmsUser $user, WalletAccount $targetWallet): bool
    {
        $owner = $this->assetWallets->resolveOwnerUser($user);

        $preferredWalletId = WalletUserPreference::query()
            ->where('cms_user_id', $owner->id)
            ->value('active_wallet_account_id');

        if ((int) ($preferredWalletId ?? 0) > 0) {
            $preferredWallet = $this->assetWallets->findWalletForOwner($owner, (int) $preferredWalletId);

            if ($preferredWallet instanceof WalletAccount && (string) $preferredWallet->status === 'active') {
                return (int) $preferredWallet->id === (int) $targetWallet->id;
            }
            return false;
        }

        return (string) $targetWallet->wallet_type === 'primary_balance'
            && (string) $targetWallet->asset === strtoupper((string) ($user->currency ?: $owner->currency ?: 'TRY'));
    }
}
