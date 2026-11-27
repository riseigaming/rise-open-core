<?php

namespace App\Services\Finance;

use App\Models\CmsUserTransaction;
use App\Support\Crypto\CryptoRail;

final class CryptoSettlementRailPolicy
{
    public function resolve(string $asset, ?string $network, mixed $precision = null): CryptoRail
    {
        if (strtoupper(trim($asset)) === 'USDT' && trim((string) $network) === '') {
            throw new CryptoSettlementRejected('rail_ambiguous');
        }
        if (CryptoRail::publicIdentity($asset, $network) === null) {
            throw new CryptoSettlementRejected('rail_unsupported');
        }
        if ($precision !== null && (!is_int($precision) || $precision < 0 || $precision > 8)) {
            throw new CryptoSettlementRejected('precision_mismatch');
        }
        try {
            return CryptoRail::resolve($asset, $network, $precision);
        } catch (\InvalidArgumentException $exception) {
            throw new CryptoSettlementRejected('precision_mismatch');
        }
    }

    public function assertEnabled(CryptoRail $rail): void
    {
        if (config('crypto_rails.settlement.' . $rail->key . '.supported') !== true) {
            throw new CryptoSettlementRejected('rail_unsupported');
        }
        if (config('crypto_rails.settlement.' . $rail->key . '.settlement_enabled') !== true) {
            throw new CryptoSettlementRejected('rail_disabled');
        }
    }

    public function assertProvider(CryptoRail $rail, ?string $provider): void
    {
        if (!in_array(strtolower((string) $provider), (array) config('crypto_rails.settlement.' . $rail->key . '.providers', []), true)) {
            throw new CryptoSettlementRejected('provider_mismatch');
        }
    }

    public function expectedRail(CmsUserTransaction $transaction): CryptoRail
    {
        if ((int) $transaction->action !== 1 || ($transaction->event_type !== \App\Services\Payments\NovaDepositService::EVENT
            && $transaction->partner?->partner_type !== 'crypto')) {
            throw new CryptoSettlementRejected('transaction_binding_mismatch');
        }
        $context = $transaction->crypto_deposit_context;
        if ($context !== null) {
            if (!is_array($context) || ($context['version'] ?? null) !== 1) {
                throw new CryptoSettlementRejected('rail_ambiguous');
            }
            $rail = $this->resolve((string) ($context['asset'] ?? ''), $context['network'] ?? '', $context['precision'] ?? null);
            if (($context['rail_key'] ?? null) !== $rail->key
                || $transaction->transaction_asset !== $rail->asset
                || $transaction->transaction_precision_scale !== $rail->precision) {
                throw new CryptoSettlementRejected('transaction_binding_mismatch');
            }
            return $rail;
        }

        // Before deposit contexts existed the sole crypto creation contract was BTC.
        // Old fiat-reference snapshots are not evidence for a token/native-coin migration.
        $asset = strtoupper((string) $transaction->transaction_asset);
        $source = strtoupper((string) $transaction->source_currency);
        $nativeBtc = $asset === 'BTC' && $transaction->transaction_precision_scale === 8;
        $fiatReference = in_array(strtoupper((string) $transaction->currency), ['TRY', 'USD', 'EUR', 'GBP'], true)
            && in_array($asset, ['', strtoupper((string) $transaction->currency)], true)
            && in_array($transaction->exact_precision_source, [null, 'transaction_snapshot'], true)
            && empty($transaction->source_wallet_account_id);
        if ((!$nativeBtc && !$fiatReference) || !in_array($source, ['', 'BTC'], true)) {
            throw new CryptoSettlementRejected('rail_ambiguous');
        }
        if ($transaction->source_wallet_account_id) {
            $wallet = \App\Models\WalletAccount::find($transaction->source_wallet_account_id);
            if (!$wallet || $wallet->asset !== 'BTC' || $wallet->wallet_key !== 'default'
                || $wallet->wallet_type !== 'crypto_asset_wallet'
                || ($wallet->meta['network'] ?? 'BTC') !== 'BTC') {
                throw new CryptoSettlementRejected('transaction_binding_mismatch');
            }
        }
        return CryptoRail::resolve('BTC', 'BTC', 8);
    }

    public function creationContext(CryptoRail $rail): array
    {
        $this->assertEnabled($rail);
        return [
            'version' => 1, 'asset' => $rail->asset, 'network' => $rail->network,
            'rail_key' => $rail->key, 'precision' => $rail->precision,
            'provider' => 'bitcart',
            'provider_mode' => config('nova_payments.provider_mode'),
            'network_mode' => config('nova_payments.network_mode'),
        ];
    }
}
