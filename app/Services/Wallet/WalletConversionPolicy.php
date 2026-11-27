<?php

namespace App\Services\Wallet;

use App\Models\CmsCurrency;
use App\Models\CmsUser;
use App\Models\WalletAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class WalletConversionPolicy
{
    public function allows(CmsUser $user, WalletAccount $source, WalletAccount $target): bool
    {
        $owner = (int) ($user->parent_id ?: $user->id);
        $rail = CryptoWalletRailReadModel::identity($source);
        if (!$rail || !app(CustomerCryptoRailPolicy::class)->permits($rail['railKey'], 'conversion_enabled')
            || !in_array($target->asset, (array) config('wallets.conversion.fiat_targets', ['TRY']), true)
            || !in_array($rail['railKey'], ['btc', 'ltc'], true)
            || $source->status !== 'active' || $target->status !== 'active'
            || (int) $source->cms_user_id !== $owner || (int) $target->cms_user_id !== $owner
            || $target->wallet_type !== 'primary_balance' || $target->wallet_key !== 'default'
            || !in_array($target->asset, (array) config('product_wallets.fiat_assets', []), true)
            || !CmsCurrency::where('code', $target->asset)->where('status', 'active')->exists()) {
            return false;
        }
        $context = app(CanonicalWalletService::class)->resolveForUserId($owner, $target->asset);
        return $context && $context->rootWalletId() === $owner
            && $context->currency() === $target->asset
            && $context->walletAccountId() === (int) $target->id;
    }

    public function targets(CmsUser $user, WalletAccount $source): array
    {
        if (!$this->canRequestQuote()) {
            return [];
        }
        return WalletAccount::where('cms_user_id', $user->parent_id ?: $user->id)
            ->where('wallet_type', 'primary_balance')->where('status', 'active')->orderBy('id')->get()
            ->filter(fn (WalletAccount $target) => $this->allows($user, $source, $target))
            ->map(fn (WalletAccount $target) => [
                'walletAccountId' => (int) $target->id, 'walletType' => 'primary_balance',
                'asset' => $target->asset, 'name' => trans('cms_wallet_center.target_main_balance', ['asset' => $target->asset]),
            ])->values()->all();
    }

    /** Local admission only, not a claim that the remote provider can quote now. */
    public function canRequestQuote(): bool
    {
        if (!config('wallets.conversion.enabled', false)) {
            return false;
        }
        try {
            return Schema::hasTable('crypto_settlement_control')
                && DB::table('crypto_settlement_control')->where('id', 1)->value('state') === 'RUNNING';
        } catch (\Throwable) {
            return false;
        }
    }
}
