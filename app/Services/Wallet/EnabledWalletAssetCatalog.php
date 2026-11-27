<?php

namespace App\Services\Wallet;

use App\Models\CmsCurrency;
use Illuminate\Support\Facades\Schema;

final class EnabledWalletAssetCatalog
{
    /** @return list<string> */
    public function fiat(): array
    {
        if (!Schema::hasTable('cms_currencies')) {
            return [];
        }

        $configured = array_values(array_unique(array_filter(array_map(
            static fn ($asset) => strtoupper(trim((string) $asset)),
            (array) config('product_wallets.fiat_assets', [])
        ))));

        if ($configured === []) {
            return [];
        }

        $active = CmsCurrency::query()->where('status', 'active')->whereIn('code', $configured)
            ->pluck('code')->map(static fn ($code) => strtoupper((string) $code))->all();

        return array_values(array_intersect($configured, $active));
    }

    }
