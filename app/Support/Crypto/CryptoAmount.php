<?php

namespace App\Support\Crypto;

use Brick\Math\BigDecimal;
use InvalidArgumentException;

final class CryptoAmount
{
    public static function normalize(mixed $value, CryptoRail $rail): string
    {
        if ((!is_string($value) && !is_int($value))
            || strlen((string) $value) > 64
            || !preg_match('/\A[+]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE]([+-]?\d{1,3}))?\z/', (string) $value, $match)
            || abs((int) ($match[1] ?? 0)) > 24) {
            throw new InvalidArgumentException('Invalid crypto amount.');
        }

        try {
            // Float observations have already lost provenance of their exact decimal value.
            $amount = BigDecimal::of((string) $value)->toScale($rail->precision);
            if ($amount->isNegative() || $amount->isGreaterThan('9999999999999999.99999999')) {
                throw new InvalidArgumentException();
            }

            return (string) $amount;
        } catch (\Throwable $exception) {
            throw new InvalidArgumentException('Invalid crypto amount or precision.');
        }
    }

    public static function creditAmount(array $state): string
    {
        $rail = CryptoRail::resolve((string) ($state['asset'] ?? ''), $state['network'] ?? null, $state['precision'] ?? null);
        // The invoice is an estimate, never evidence of money received.
        return self::normalize($state['received_amount_crypto'] ?? '0', $rail);
    }
}
