<?php

namespace App\Services\Wallet;

use App\Models\CmsUser;
use App\SiteSettings;
use Illuminate\Support\Facades\Schema;

class ProductWalletOverrideSettingsService
{
    public const SETTINGS_KEY = 'product_wallet_support_overrides_v1';

    public function overrideFor(string $productType): ?array
    {
        if (!Schema::hasTable('site_settings')) {
            return null;
        }

        $overrides = $this->allOverrides();
        $override = data_get($overrides, strtolower(trim($productType)));

        if (!is_array($override)) {
            return null;
        }

        $supportedAssets = collect((array) ($override['supported_assets'] ?? []))
            ->map(fn ($value) => strtoupper((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $supportedAssets === []
            ? null
            : ['supported_assets' => $supportedAssets];
    }

    public function selectedAssets(string $productType): array
    {
        return (array) data_get($this->overrideFor($productType), 'supported_assets', []);
    }

    public function sourceLabel(string $productType): string
    {
        return $this->overrideFor($productType)
            ? __('admin.wallet_source.product_override')
            : __('admin.wallet_source.product_default');
    }

    public function persist(string $productType, array $supportedAssets, ?CmsUser $actor = null): array
    {
        if (!Schema::hasTable('site_settings')) {
            return [];
        }

        $productType = strtolower(trim($productType));
        $overrides = $this->allOverrides();
        $normalizedAssets = collect($supportedAssets)
            ->map(fn ($value) => strtoupper((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (array_diff($normalizedAssets, (array) config('product_wallets.gameplay_assets', ['TRY', 'USD', 'EUR'])) !== []) {
            throw new \InvalidArgumentException('Funding-only assets cannot be enabled for gameplay.');
        }

        if ($normalizedAssets === []) {
            unset($overrides[$productType]);
        } else {
            $overrides[$productType] = [
                'supported_assets' => $normalizedAssets,
                'updated_at' => now()->toDateTimeString(),
                'updated_by' => optional($actor)->id,
            ];
        }

        SiteSettings::query()->updateOrCreate(
            ['key' => self::SETTINGS_KEY],
            ['value' => json_encode($overrides, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]
        );

        return $overrides[$productType] ?? [];
    }

    private function allOverrides(): array
    {
        $raw = (string) SiteSettings::query()->where('key', self::SETTINGS_KEY)->value('value');

        if (trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
