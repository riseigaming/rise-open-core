<?php

namespace App\Services\RiskLimits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class CanonicalRiskValueService
{
    public const CANONICAL_CURRENCY = 'TRY';

    /** @var array<string, float>|null */
    private ?array $cachedRates = null;

    public function toCanonical(float $amount, string $currency): float
    {
        $currency = strtoupper(trim($currency));
        $rates = $this->rates();

        if (!isset($rates[$currency]) || $rates[$currency] <= 0 || !isset($rates[self::CANONICAL_CURRENCY]) || $rates[self::CANONICAL_CURRENCY] <= 0) {
            throw new RuntimeException("Canonical FX rate is unavailable for {$currency}.");
        }

        return round($amount * ($rates[$currency] / $rates[self::CANONICAL_CURRENCY]), 8);
    }

    public function fromCanonical(float $canonicalAmount, string $currency): float
    {
        $currency = strtoupper(trim($currency));
        $rates = $this->rates();

        if (!isset($rates[$currency]) || $rates[$currency] <= 0 || !isset($rates[self::CANONICAL_CURRENCY]) || $rates[self::CANONICAL_CURRENCY] <= 0) {
            throw new RuntimeException("Canonical FX rate is unavailable for {$currency}.");
        }

        return round($canonicalAmount * ($rates[self::CANONICAL_CURRENCY] / $rates[$currency]), 2);
    }

    /** @return array<string, float> */
    public function rates(): array
    {
        if ($this->cachedRates !== null) {
            return $this->cachedRates;
        }

        if (!Schema::hasTable('cms_currencies')) {
            return $this->cachedRates = [self::CANONICAL_CURRENCY => 1.0];
        }

        $query = DB::table('cms_currencies');
        if (Schema::hasColumn('cms_currencies', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }
        if (Schema::hasColumn('cms_currencies', 'status')) {
            $query->whereIn('status', ['active', 1, '1']);
        }

        $rates = $query->pluck('rate', 'code')
            ->mapWithKeys(fn ($rate, $code): array => [strtoupper((string) $code) => (float) $rate])
            ->filter(fn (float $rate): bool => $rate > 0)
            ->all();

        if (!isset($rates[self::CANONICAL_CURRENCY])) {
            $rates[self::CANONICAL_CURRENCY] = 1.0;
        }

        return $this->cachedRates = $rates;
    }
}
