<?php

namespace App\Betting\Acceptance;

class StakeLimitPolicy
{
    public function assertStakeAllowed(float $stakeAmount, string $currency): void
    {
        $maxStake = $this->maxStakeForCurrency($currency);
        if ($maxStake === null) {
            return;
        }

        if (round($stakeAmount, 2) > round($maxStake, 2)) {
            throw new StakeLimitExceededException($currency, $stakeAmount, $maxStake);
        }
    }

    public function maxStakeForCurrency(?string $currency): ?float
    {
        $currency = strtoupper(trim((string) $currency));
        if ($currency === '') {
            return null;
        }

        return $this->configuredLimits()[$currency] ?? null;
    }

    public function configuredLimits(): array
    {
        try {
            $stored = app(\App\Services\RiskLimits\CanonicalRiskLimitProfileService::class)->sportsbookLimits();
            if ($stored !== []) {
                return $stored;
            }
        } catch (\Throwable $throwable) {
            report($throwable);
        }

        return $this->configuredConfigLimits();
    }

    /** @return array<string, float> */
    public function configuredConfigLimits(): array
    {
        $raw = config('betting.acceptance.max_stake_by_currency', []);
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($raw)) {
            return [];
        }

        $limits = [];
        foreach ($raw as $currency => $limit) {
            $currency = strtoupper(trim((string) $currency));
            if ($currency === '' || !is_numeric($limit)) {
                continue;
            }

            $limit = (float) $limit;
            if ($limit <= 0) {
                continue;
            }

            $limits[$currency] = round($limit, 2);
        }

        return $limits;
    }

    public static function formatMoney(float $amount, string $currency): string
    {
        $formatted = number_format($amount, 2, ',', '.');

        return match (strtoupper(trim($currency))) {
            'TRY' => '₺' . $formatted,
            'USD' => '$' . $formatted,
            'EUR' => '€' . $formatted,
            default => $formatted . ' ' . strtoupper(trim($currency)),
        };
    }
}
