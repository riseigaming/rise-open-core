<?php

namespace App\Services\Wallet;

use App\Models\WalletAccount;
use App\Services\Finance\PaymentMethodReadiness;
use App\Support\Crypto\CryptoRail;

final class CryptoWalletRailReadModel
{
    public static function identity(WalletAccount $wallet): ?array
    {
        if ($wallet->wallet_type !== 'crypto_asset_wallet') {
            return null;
        }
        $meta = (array) $wallet->meta;
        if (isset($meta['network']) && !is_string($meta['network'])) {
            return null;
        }
        $rail = CryptoRail::publicIdentity((string) $wallet->asset, $meta['network'] ?? null);
        if (!$rail || $wallet->wallet_key !== $rail['walletKey']
            || (isset($meta['rail_key']) && $meta['rail_key'] !== $rail['railKey'])) {
            return null;
        }
        return $rail;
    }

    /** Browser-safe projection; provider and tenant evidence stay server-side. */
    public function partners(iterable $partners): array
    {
        $policy = app(CustomerCryptoRailPolicy::class);
        $methods = app(PaymentMethodReadiness::class)->selectable($partners, auth('cms')->user(), request())
            ->filter(fn ($partner) => $partner->partner_type === 'crypto');
        $result = [];
        $user = auth('cms')->user();
        $owner = $user ? app(AssetWalletService::class)->resolveOwnerUser($user) : null;
        // Match FinanceController's active fiat reference without provisioning wallets
        // during this read-only partner-card render.
        $active = $user ? app(ActiveWalletService::class)->existingForUser($user) : null;
        $fiatCurrency = $active && $active->wallet_type === 'primary_balance'
            && in_array((string) $active->asset, ['TRY', 'USD', 'EUR'], true)
            ? (string) $active->asset : (string) ($user?->currency ?? 'TRY');
        $wallets = $owner ? WalletAccount::where('cms_user_id', $owner->id)
            ->where('wallet_type', 'crypto_asset_wallet')->where('status', 'active')->get() : collect();
        $readiness = app(\App\Services\Finance\CryptoRailReadinessService::class);
        foreach ($policy->all() as $identity) {
            if (!$policy->permits($identity['railKey'], 'deposit_enabled')) continue;
            foreach ($methods as $partner) {
                $wallet = $wallets->first(fn ($wallet) => (self::identity($wallet)['railKey'] ?? null) === $identity['railKey']);
                $state = $readiness->evaluate($policy->rail($identity['railKey']), $user, $wallet, $partner, false, $fiatCurrency);
                $result[] = $policy->descriptor($identity) + [
                    'partnerId' => (int) $partner->id, 'partnerType' => 'crypto',
                    'partnerTitle' => (string) optional($partner->translation)->title,
                    // The modal must use the same owned wallet evaluated for readiness;
                    // the layout's active-wallet-only summary is not a wallet portfolio.
                    'walletAccountId' => $state['ready'] && $wallet ? (int) $wallet->id : null,
                ] + $readiness->browserFields($state);
            }
        }
        return $result;
    }

    public static function partnerFor(array $wallet, array $partners): ?array
    {
        if (!is_string($wallet['asset'] ?? null) || !is_string($wallet['network'] ?? null)) {
            return null;
        }
        $rail = CryptoRail::publicIdentity($wallet['asset'], $wallet['network']);
        if (!$rail || ($wallet['railKey'] ?? null) !== $rail['railKey']
            || ($wallet['walletKey'] ?? null) !== $rail['walletKey']) {
            return null;
        }
        foreach ($partners as $partner) {
            if (($partner['ready'] ?? false) === true && ($partner['providerReadiness'] ?? null) !== false && !empty($wallet['railKey'])
                && ($wallet['asset'] ?? null) === ($partner['asset'] ?? null)
                && ($wallet['network'] ?? null) === ($partner['network'] ?? null)
                && $wallet['railKey'] === ($partner['railKey'] ?? null)
                && ($wallet['walletKey'] ?? null) === ($partner['walletKey'] ?? null)) {
                return $partner;
            }
        }
        return null;
    }
}
