<?php

namespace App\Support\Money;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final class CurrencyPrecision
{
    public static function fiatScale(): int
    {
        return (int) config('wallets.precision.fiat', 2);
    }

    public static function scaleFor(?string $currencyOrAsset, ?string $walletType = null): int
    {
        $normalized = strtoupper(trim((string) $currencyOrAsset));
        $configured = collect((array) config('wallets.precision.assets', ['BTC' => 8]))
            ->mapWithKeys(fn ($scale, $asset) => [strtoupper((string) $asset) => (int) $scale])
            ->all();

        if ($normalized !== '' && array_key_exists($normalized, $configured)) {
            return (int) $configured[$normalized];
        }

        if ((string) $walletType === 'crypto_asset_wallet') {
            return (int) config('wallets.precision.asset_default', 8);
        }

        return self::fiatScale();
    }

    public static function isHighPrecision(?string $currencyOrAsset, ?string $walletType = null): bool
    {
        return self::scaleFor($currencyOrAsset, $walletType) > self::fiatScale();
    }

    public static function normalize(int|float|string|null $value, int $scale): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($scale < 0 || $scale > 18 || strlen((string) $value) > 128
            || !preg_match('/\A[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE]([+-]?\d{1,3}))?\z/', (string) $value, $match)
            || abs((int) ($match[1] ?? 0)) > 64) {
            throw new \InvalidArgumentException('Invalid decimal value or scale.');
        }

        return (string) BigDecimal::of((string) $value)->toScale($scale, RoundingMode::HALF_UP);
    }

    public static function format(int|float|string|null $value, int $scale): string
    {
        return self::normalize($value, $scale) ?? number_format(0, $scale, '.', '');
    }

    public static function formatLegacy(int|float|string|null $value, bool $highPrecision = false): string
    {
        return self::format($value, $highPrecision ? 6 : self::fiatScale());
    }
}
