<?php

namespace App\Services\Wallet;

use App\Support\Crypto\CryptoRail;

/** Customer capabilities only; never provider evidence or settlement authorization. */
final class CustomerCryptoRailPolicy
{
    private const FIELDS = ['supported', 'customer_visible', 'wallet_discoverable', 'deposit_enabled', 'conversion_enabled'];

    public function all(): array
    {
        $result = [];
        $config = config('customer_crypto_rails');
        if (!is_array($config)) return [];
        foreach ($config as $key => $flags) {
            if (!is_string($key) || !preg_match('~^[A-Z0-9]+/[A-Z0-9]+$~D', $key) || !is_array($flags)) continue;
            foreach (self::FIELDS as $field) {
                if (!array_key_exists($field, $flags) || !is_bool($flags[$field])) continue 2;
            }
            [$asset, $network] = explode('/', $key);
            $identity = CryptoRail::publicIdentity($asset, $network);
            if (!$identity) continue;
            $contract = CryptoRail::contractIdentity($asset, $network);
            $result[$identity['railKey']] = $identity + ['precision' => $contract['precision']]
                + array_intersect_key($flags, array_flip(self::FIELDS));
        }
        return $result;
    }

    public function find(string $railKey): ?array
    {
        return $this->all()[$railKey] ?? null;
    }

    public function permits(string $railKey, string $capability): bool
    {
        $rail = $this->find($railKey);
        return in_array($capability, self::FIELDS, true) && ($rail['supported'] ?? false)
            && ($rail['customer_visible'] ?? false) && ($rail[$capability] ?? false);
    }

    public function rail(string $railKey): ?CryptoRail
    {
        $item = $this->find($railKey);
        return $item ? CryptoRail::resolve($item['asset'], $item['network'], $item['precision']) : null;
    }

    public function descriptor(array $rail): array
    {
        return array_intersect_key($rail, array_flip(['asset', 'network', 'railKey', 'walletKey', 'precision'])) + [
            'walletType' => 'crypto_asset_wallet',
            'name' => trans('cms_public.customer_crypto_rails.' . $rail['railKey'] . '.name'),
            'description' => trans('cms_public.customer_crypto_rails.' . $rail['railKey'] . '.description'),
        ];
    }

    public function descriptors(): array
    {
        return array_values(array_map(fn ($rail) => $this->descriptor($rail), $this->all()));
    }
}
