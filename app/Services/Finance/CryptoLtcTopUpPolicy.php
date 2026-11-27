<?php

namespace App\Services\Finance;

use App\Models\CmsUserTransaction;
use Brick\Math\BigDecimal;
use App\Support\Crypto\CryptoAmount;

/**
 * Fiat minimum controls new requests; native LTC actually received is credited.
 * Receipt-only, fiat-specific; never authorizes an invoice or converts fiat.
 */
final class CryptoLtcTopUpPolicy
{
    public function eligible(CmsUserTransaction $transaction, array $state): bool
    {
        $context = (array) ($transaction->crypto_deposit_context ?? []);
        if (($context['rail_key'] ?? null) !== 'ltc'
            || ($context['network_mode'] ?? null) !== 'mainnet'
            || ($state['asset'] ?? null) !== 'LTC'
            || ($state['network'] ?? null) !== 'LTC'
            || ($state['network_mode'] ?? null) !== 'mainnet'
            || !in_array($transaction->currency, ['TRY', 'USD', 'EUR'], true)
            || ($state['fiat_currency'] ?? null) !== $transaction->currency
            || ($state['under_minimum'] ?? null) !== true
            || !is_string($state['receipt_quote_fingerprint'] ?? null)
            || !preg_match('/\A[0-9a-f]{64}\z/', $state['receipt_quote_fingerprint'])
            || !is_string($state['permanent_customer_key'] ?? null)
            || !preg_match('/\A[0-9a-f]{64}\z/', $state['permanent_customer_key'])) return false;

        $rate = $state['fiat_rate'] ?? null;
        $floor = $this->floor($transaction->currency);
        if (!is_string($rate) || !preg_match('/\A\d{1,32}(?:\.\d{1,32})?\z/', $rate)
            || !is_string($floor) || !preg_match('/\A\d{1,12}(?:\.\d{1,2})?\z/', $floor)) return false;

        try {
            $native = BigDecimal::of(CryptoAmount::creditAmount($state));
            return $native->isPositive() && BigDecimal::of($floor)->isPositive()
                && $native->multipliedBy(BigDecimal::of($rate))->isGreaterThanOrEqualTo($floor);
        } catch (\Throwable) {
            return false;
        }
    }

    public function floor(string $currency): ?string
    {
        $floor = config('crypto_rails.ltc_topup_operational_floors.' . $currency);
        return is_string($floor) && preg_match('/\A\d{1,12}(?:\.\d{1,2})?\z/', $floor)
            && BigDecimal::of($floor)->isPositive() ? $floor : null;
    }
}
