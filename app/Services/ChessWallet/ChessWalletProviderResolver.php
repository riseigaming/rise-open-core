<?php

namespace App\Services\ChessWallet;

use App\Models\Provider;
use Illuminate\Support\Facades\Schema;

class ChessWalletProviderResolver
{
    public function findProvider(): ?Provider
    {
        if (!Schema::hasTable('providers')) {
            return null;
        }

        try {
            return Provider::query()
                ->where('slug', config('chess_wallet.provider_slug'))
                ->first();
        } catch (\Throwable $exception) {
            return null;
        }
    }

    public function resolveProvider(): ?Provider
    {
        $provider = $this->findProvider();

        if ($provider instanceof Provider) {
            return $provider;
        }

        if (!config('chess_wallet.auto_create_provider') || !Schema::hasTable('providers')) {
            return null;
        }

        return Provider::query()->create([
            'name' => config('chess_wallet.provider_name'),
            'slug' => config('chess_wallet.provider_slug'),
            'kind'=>'external','show_left_panel'=>true,
            'status' => 'active',
            'config' => config('chess_wallet.default_provider_config'),
        ]);
    }

    public function resolveProviderConfig(?Provider $provider): array
    {
        $providerConfig = $provider?->config ?? [];
        $defaultConfig = config('chess_wallet.default_provider_config', []);
        $partnerOverrides = [
            'wallet' => [
                'partners' => config('chess_wallet.partner_overrides', []),
            ],
        ];

        return array_replace_recursive($defaultConfig, $partnerOverrides, is_array($providerConfig) ? $providerConfig : []);
    }

    public function resolvePartnerContext(string $partnerCode): ?array
    {
        $provider = $this->findProvider();
        $providerConfig = $this->resolveProviderConfig($provider);
        $walletConfig = $providerConfig['wallet'] ?? [];
        $partners = $walletConfig['partners'] ?? [];

        if (!is_array($partners) || !array_key_exists($partnerCode, $partners)) {
            return null;
        }

        $partnerConfig = $partners[$partnerCode] ?? [];
        $partnerWalletConfig = array_replace_recursive($walletConfig, is_array($partnerConfig) ? $partnerConfig : []);
        $partnerWalletConfig['partner_code'] = $partnerCode;

        return [
            'provider' => $provider,
            'provider_config' => $providerConfig,
            'wallet_config' => $partnerWalletConfig,
        ];
    }
}
