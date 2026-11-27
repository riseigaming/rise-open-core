<?php

namespace App\Services\Wallet;

use App\Models\CmsGame;
use App\Models\CmsUser;
use App\Models\WalletAccount;
use App\Services\Certification\ProductionCertificationContext;
use Illuminate\Support\Arr;

class ProductWalletCapabilityService
{
    public function __construct(
        private readonly AssetWalletService $assetWallets,
        private readonly ProductWalletOverrideSettingsService $overrideSettings,
        private readonly ProductionCertificationContext $certification
    )
    {
    }

    public function policySnapshot(string $productType, array $context = []): array
    {
        $policy = $this->normalizePolicy($this->policyFor($productType, $context));

        return [
            'productType' => strtolower(trim($productType)),
            'supportedWalletTypes' => collect($policy['supported_wallet_types'] ?? [])
                ->map(fn ($value) => (string) $value)
                ->values()
                ->all(),
            'supportedAssets' => collect($policy['supported_assets'] ?? [])
                ->map(fn ($value) => strtoupper((string) $value))
                ->values()
                ->all(),
        ];
    }

    public function configurableAssetOptions(): array
    {
        return collect((array) config('product_wallets.gameplay_assets', ['TRY', 'USD', 'EUR']))
            ->map(fn ($value) => strtoupper((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->map(fn (string $asset) => [
                'value' => $asset,
                'label' => $asset,
            ])
            ->all();
    }

    public function configurableAssetOptionsForProduct(string $productType, array $context = []): array
    {
        $productType = strtolower(trim($productType));
        $policy = $this->normalizePolicy((array) config("product_wallets.products.{$productType}", []));
        $assets = collect((array) ($policy['supported_assets'] ?? []))
            ->merge((array) config('product_wallets.gameplay_assets', ['TRY', 'USD', 'EUR']))
            ->map(fn ($value) => strtoupper((string) $value))
            ->filter(fn ($value) => in_array($value, (array) config('product_wallets.gameplay_assets', ['TRY', 'USD', 'EUR']), true))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return collect($assets)
            ->map(fn (string $asset) => [
                'value' => $asset,
                'label' => $asset,
            ])
            ->values()
            ->all();
    }

    public function evaluate(string $productType, WalletAccount $wallet, array $context = []): array
    {
        $policy = $this->policySnapshot($productType, $context);
        $supportedTypes = $policy['supportedWalletTypes'];
        $supportedAssets = $policy['supportedAssets'];

        $certificationSupported = $this->certification->isCertificationWallet($wallet)
            && $this->certification->isActiveFor($wallet->owner)
            && in_array(strtoupper((string) $wallet->asset), (array) config('production_certification.allowed_assets', ['TRY']), true);
        $supported = $certificationSupported || (
            in_array((string) $wallet->wallet_type, $supportedTypes, true)
            && in_array(strtoupper((string) $wallet->asset), $supportedAssets, true)
        );
        if ($certificationSupported) {
            $supportedTypes[] = $this->certification->walletType();
            $supportedAssets[] = strtoupper((string) $wallet->asset);
        }

        return [
            'supported' => $supported,
            'productType' => $productType,
            'supportedWalletTypes' => $supportedTypes,
            'supportedAssets' => $supportedAssets,
            'supportedWallets' => [],
            'message' => $supported
                ? null
                : trans('wallet_playability.unsupported_wallet'),
        ];
    }

    public function resolveForUser(string $productType, CmsUser $user, array $context = []): array
    {
        $wallets = $this->assetWallets->selectableWalletsForUser($user);
        $policy = $this->policySnapshot($productType, $context);
        $supportedTypes = $policy['supportedWalletTypes'];
        $supportedAssets = $policy['supportedAssets'];

        if ($this->certification->isActiveFor($user)) {
            $supportedTypes[] = $this->certification->walletType();
            $supportedAssets = array_merge(
                $supportedAssets,
                (array) config('production_certification.allowed_assets', ['TRY'])
            );
            $supportedTypes = array_values(array_unique($supportedTypes));
            $supportedAssets = array_values(array_unique(array_map('strtoupper', $supportedAssets)));
        }

        $supportedWallets = collect($wallets)
            ->filter(function (array $wallet) use ($supportedTypes, $supportedAssets) {
                return in_array((string) ($wallet['walletType'] ?? ''), $supportedTypes, true)
                    && in_array(strtoupper((string) ($wallet['asset'] ?? '')), $supportedAssets, true);
            })
            ->values()
            ->all();

        return [
            'productType' => $productType,
            'supportedWalletTypes' => $supportedTypes,
            'supportedAssets' => $supportedAssets,
            'supportedWallets' => $supportedWallets,
            'supportedLabels' => array_values(array_unique(array_map(
                fn (array $wallet) => (string) ($wallet['name'] ?? $wallet['asset'] ?? ''),
                $supportedWallets
            ))),
        ];
    }

    private function policyFor(string $productType, array $context = []): array
    {
        $productType = strtolower(trim($productType));
        $base = (array) config("product_wallets.products.{$productType}", []);

        if ($productType === 'internal_games') {
            $game = $context['game'] ?? null;
            $gameCode = $context['game_code'] ?? null;

            if ($game instanceof CmsGame) {
                $gameCode = $gameCode ?: data_get($this->gameExtraParameters($game), 'internal_game_code');
            }

            if ($gameCode) {
                $override = Arr::get($base, 'game_overrides.' . $gameCode);
                if (is_array($override)) {
                    $base = $this->mergePolicyOverride($base, $override);
                }
            }

            $explicitGameOverride = $this->gameWalletOverride($game);
            if (is_array($explicitGameOverride)) {
                $base = $this->mergePolicyOverride($base, $explicitGameOverride);
            }
        }

        $explicitProductOverride = $this->overrideSettings->overrideFor($productType);
        if (is_array($explicitProductOverride)) {
            $base = $this->mergePolicyOverride($base, $explicitProductOverride);
        }

        return $base;
    }

    private function normalizePolicy(array $policy): array
    {
        $supportedAssets = collect((array) ($policy['supported_assets'] ?? []))
            ->map(fn ($value) => strtoupper((string) $value))
            ->filter(fn ($value) => in_array($value, (array) config('product_wallets.gameplay_assets', ['TRY', 'USD', 'EUR']), true))
            ->filter()
            ->values()
            ->all();
        $supportedTypes = collect((array) ($policy['supported_wallet_types'] ?? []))
            ->map(fn ($value) => (string) $value)
            ->filter(fn ($value) => $value === 'primary_balance')
            ->filter()
            ->values()
            ->all();

        $fiatAssets = collect((array) config('product_wallets.fiat_assets', ['TRY', 'USD', 'EUR']))
            ->map(fn ($value) => strtoupper((string) $value))
            ->all();
        $cryptoAssets = collect((array) config('product_wallets.crypto_assets', ['BTC']))
            ->map(fn ($value) => strtoupper((string) $value))
            ->all();

        if (array_intersect($supportedAssets, $fiatAssets) !== []) {
            $supportedTypes[] = 'primary_balance';
        }

        if (array_intersect($supportedAssets, $cryptoAssets) !== []) {
            $supportedTypes[] = 'crypto_asset_wallet';
        }

        $policy['supported_assets'] = array_values(array_unique($supportedAssets));
        $policy['supported_wallet_types'] = array_values(array_unique($supportedTypes));

        return $policy;
    }

    private function mergePolicyOverride(array $base, array $override): array
    {
        $merged = array_replace_recursive($base, $override);

        if (array_key_exists('supported_assets', $override)) {
            $merged['supported_assets'] = array_values((array) $override['supported_assets']);
        }

        if (array_key_exists('supported_wallet_types', $override)) {
            $merged['supported_wallet_types'] = array_values((array) $override['supported_wallet_types']);
        }

        return $merged;
    }

    private function gameWalletOverride(mixed $game): ?array
    {
        if (!$game instanceof CmsGame) {
            return null;
        }

        $parameters = $this->gameExtraParameters($game);
        $walletSupport = data_get($parameters, 'wallet_support');

        if (!is_array($walletSupport)) {
            return null;
        }

        $override = [];
        $supportedAssets = collect((array) ($walletSupport['supported_assets'] ?? []))
            ->map(fn ($value) => strtoupper((string) $value))
            ->filter()
            ->values()
            ->all();
        $supportedTypes = collect((array) ($walletSupport['supported_wallet_types'] ?? []))
            ->map(fn ($value) => (string) $value)
            ->filter()
            ->values()
            ->all();

        if ($supportedAssets !== []) {
            $override['supported_assets'] = $supportedAssets;
        }

        if ($supportedTypes !== []) {
            $override['supported_wallet_types'] = $supportedTypes;
        }

        return $override === [] ? null : $override;
    }

    private function gameExtraParameters(CmsGame $game): array
    {
        if (is_array($game->extra_parameters)) {
            return $game->extra_parameters;
        }

        $decoded = json_decode((string) $game->extra_parameters, true);

        return is_array($decoded) ? $decoded : [];
    }
}
