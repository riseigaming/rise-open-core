<?php

namespace App\Support\Crypto;

use InvalidArgumentException;

/** Validation definitions only: this registry never grants payment availability. */
final class CryptoRail
{
    public const CONTRACT_VERSION = 1;

    /** Onboarding identity is not provider-observed precision or runtime permission. */
    public static function contractIdentity(string $asset, ?string $network = null): array
    {
        $identity = self::publicIdentity($asset, $network);
        if ($identity === null) throw new InvalidArgumentException('INVALID_RAIL');
        return ['rail_key' => $identity['railKey'], 'asset' => $identity['asset'],
            'network' => $identity['network'], 'precision' => $identity['asset'] === 'USDT' ? 6 : 8,
            'token_contract' => $identity['asset'] === 'USDT' ? 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t' : null];
    }

    private const DEFINITIONS = [
        'BTC/BTC' => ['btc', 'default', 8, 'bitcoin'],
        'LTC/LTC' => ['ltc', 'default', 8, 'litecoin'],
        'USDT/TRC20' => ['usdt_trc20', 'trc20', null, 'tron'],
    ];

    private function __construct(
        public readonly string $key,
        public readonly string $asset,
        public readonly string $network,
        public readonly string $walletKey,
        public readonly int $precision,
        public readonly string $addressFamily,
    ) {
    }

    public static function resolve(string $asset, ?string $network = null, ?int $providerPrecision = null): self
    {
        $asset = strtoupper(trim($asset));
        // Only native coins have an unambiguous legacy default.
        $network = $network === null && in_array($asset, ['BTC', 'LTC'], true)
            ? $asset : strtoupper(trim((string) $network));
        $definition = self::DEFINITIONS[$asset . '/' . $network] ?? null;
        if ($definition === null) {
            throw new InvalidArgumentException('Unsupported or ambiguous crypto rail.');
        }
        $precision = $definition[2] ?? $providerPrecision;
        if ($precision === null || $precision < 0 || $precision > 8
            || ($providerPrecision !== null && $providerPrecision !== $precision)) {
            throw new InvalidArgumentException('Missing or mismatched crypto rail precision.');
        }

        return new self($definition[0], $asset, $network, $definition[1], $precision, $definition[3]);
    }

    public function walletIdentity(): array
    {
        return ['wallet_type' => 'crypto_asset_wallet', 'wallet_key' => $this->walletKey, 'asset' => $this->asset];
    }

    /** Identity only: no precision assumption or payment capability is granted. */
    public static function publicIdentity(string $asset, ?string $network = null): ?array
    {
        $asset = strtoupper(trim($asset));
        $network = $network === null && in_array($asset, ['BTC', 'LTC'], true)
            ? $asset : strtoupper(trim((string) $network));
        $definition = self::DEFINITIONS[$asset . '/' . $network] ?? null;
        return $definition ? ['asset' => $asset, 'network' => $network,
            'railKey' => $definition[0], 'walletKey' => $definition[1]] : null;
    }
}
