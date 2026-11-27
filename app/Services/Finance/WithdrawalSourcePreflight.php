<?php

declare(strict_types=1);

namespace App\Services\Finance;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;

/** Source-value checks only: no wallet lookup, reservation or payout authority. */
final class WithdrawalSourcePreflight
{
    /**
     * Decimal inputs must be strings, never floats. The caller supplies the
     * primary fiat precision (integer 2), matching canonical wallet policy.
     * A valid snapshot does not establish ownership, freshness or spendability.
     *
     * @return array{source_validation: array{valid: bool, reason_code: ?string}, customer_payout_allowed: false, normalized_amount: ?string}
     */
    public function validate(
        string $wallet_type,
        string $asset,
        ?string $network,
        string $requested_currency,
        mixed $amount,
        mixed $precision_scale,
        mixed $available_balance,
    ): array {
        $result = [
            'source_validation' => ['valid' => false, 'reason_code' => null],
            'customer_payout_allowed' => false,
            'normalized_amount' => null,
        ];
        $asset = strtoupper(trim($asset));
        $requested_currency = strtoupper(trim($requested_currency));

        if ($wallet_type === 'crypto_asset_wallet' || in_array($asset, ['BTC', 'LTC', 'USDT'], true)) {
            $reason = 'crypto_withdrawals_unavailable';
        } elseif ($wallet_type !== 'primary_balance') {
            $reason = 'unsupported_wallet_type';
        } elseif (!in_array($asset, ['TRY', 'USD', 'EUR'], true)) {
            $reason = 'unsupported_source_currency';
        } elseif ($network !== null && $network !== '') {
            // Fiat has no network; never infer or silently discard one.
            $reason = 'unsupported_network';
        } elseif (!in_array($requested_currency, ['TRY', 'USD', 'EUR'], true)) {
            $reason = 'unsupported_payout_currency';
        } elseif ($asset !== $requested_currency) {
            $reason = 'conversion_required_manual_review';
        } elseif (!is_int($precision_scale) || $precision_scale !== 2) {
            // Canonical primary fiat balance is two decimal places; accepting
            // arbitrary 0..8 here would overstate available source precision.
            $reason = 'invalid_precision';
        } else {
            // Plain decimal strings only: no exponent, locale or float coercion.
            foreach (['invalid_amount' => $amount, 'invalid_available_balance' => $available_balance] as $reason => $value) {
                if (!is_string($value) || strlen($value) > 128
                    || !preg_match('/\A[0-9]+(?:\.[0-9]+)?\z/', $value)
                    || strlen(ltrim(explode('.', $value, 2)[0], '0')) > 16) {
                    // DECIMAL(24,8) permits 16 integer digits; reject overflow
                    // before BigDecimal parsing, even when both values match.
                    $result['source_validation']['reason_code'] = $reason;
                    return $result;
                }
                try {
                    $decimal = BigDecimal::of($value)->toScale($precision_scale, RoundingMode::UNNECESSARY);
                } catch (MathException) {
                    $result['source_validation']['reason_code'] = $reason;
                    return $result;
                }
                if ($reason === 'invalid_amount') {
                    if (!$decimal->isPositive()) {
                        $result['source_validation']['reason_code'] = $reason;
                        return $result;
                    }
                    $requested = $decimal;
                } else {
                    $balance = $decimal;
                }
            }
            $reason = $requested->isGreaterThan($balance) ? 'insufficient_source_balance' : null;
            $result['normalized_amount'] = (string) $requested;
        }

        $result['source_validation'] = ['valid' => $reason === null, 'reason_code' => $reason];
        return $result;
    }
}
