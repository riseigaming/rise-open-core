<?php

namespace App\Services\Wallet;

use App\Models\CmsUser;
use App\Models\WalletAccount;
use App\Services\Wallet\Exceptions\UnsupportedProductWalletException;

/** New gameplay admission only. Never use this gate for settlement/refund/replay. */
final class WalletPlayabilityService
{
    public function __construct(
        private readonly ActiveWalletService $active,
        private readonly ProductWalletCapabilityService $products,
        private readonly AssetWalletService $assets,
        private readonly WalletChangeGuardService $switchGuard,
    ) {}

    public function assertForUser(string $product, CmsUser $user, array $context = [], bool $lock = false): WalletAccount
    {
        $resolution = $this->active->resolutionForUser($user, $lock);
        $wallet = $resolution['wallet'];
        $reason = $resolution['reason_code'];
        if (!$reason && !$this->products->evaluate($product, $wallet, $context)['supported']) {
            $reason = 'unsupported_wallet';
        }
        if ($reason) {
            throw new UnsupportedProductWalletException(trans('wallet_playability.'.$reason),
                $this->decision($product, $user, $wallet, $reason, $context) + ['wallet_resolution' => array_diff_key($resolution, ['wallet' => true])]);
        }
        return $wallet;
    }

    /** Check the pinned wallet for a NEW action without redirecting it to today's preference. */
    public function assertForWallet(string $product, CmsUser $user, WalletAccount $wallet, array $context = []): void
    {
        $reason = $wallet->status !== 'active' ? 'wallet_unavailable'
            : (!$this->products->evaluate($product, $wallet, $context)['supported'] ? 'unsupported_wallet' : null);
        if ($reason) {
            throw new UnsupportedProductWalletException(trans('wallet_playability.'.$reason),
                $this->decision($product, $user, $wallet, $reason, $context));
        }
    }

    public function decision(string $product, CmsUser $user, ?WalletAccount $wallet, string $reason, array $context = []): array
    {
        $support = $this->products->resolveForUser($product, $user, $context);
        $targets = $support['supportedWallets'];
        if (isset($context['provider_assets'])) {
            $targets = array_values(array_filter($targets, fn ($target) => in_array($target['asset'], $context['provider_assets'], true)));
        }
        $support['supportedWallets'] = $targets;
        $support['supportedLabels'] = array_values(array_unique(array_column($targets, 'name')));
        $guard = $this->switchGuard->evaluate($user);
        $switchAllowed = $targets !== [] && $guard['allowed'];
        $active = $wallet ? $this->assets->serializeWalletAccount($wallet, $user) : null;
        if ($active !== null) {
            $active['isActive'] = $wallet->status === 'active';
        }

        return $support + [
            'reason_code' => $reason,
            'product' => $product,
            'active_wallet' => $active,
            'activeWallet' => $active,
            'supported_wallets' => $targets,
            'switch_allowed' => $switchAllowed,
            'switch_reasons' => $guard['reasons'],
            'suggested_action' => $switchAllowed ? 'switch_wallet' : 'none',
            // No provider probe in a gameplay read. Targets alone do not prove a fresh quote or maintenance permission.
            'convert_targets' => [],
            'conversion_available' => false,
            'conversion_readiness' => 'unverified',
            'retryable' => false,
            'maintenance' => false,
            'message' => trans('wallet_playability.'.$reason),
        ];
    }
}
