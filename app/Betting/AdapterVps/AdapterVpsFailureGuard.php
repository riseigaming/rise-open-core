<?php

namespace App\Betting\AdapterVps;

use App\Betting\Support\QuoteRefRedactor;
use Illuminate\Support\Facades\RateLimiter;

class AdapterVpsFailureGuard
{
    private const DECAY_SECONDS = 120;
    private const USER_MAX_ATTEMPTS = 6;
    private const IP_MAX_ATTEMPTS = 10;
    private const QUOTE_MAX_ATTEMPTS = 3;

    private QuoteRefRedactor $quoteRefRedactor;

    public function __construct(?QuoteRefRedactor $quoteRefRedactor = null)
    {
        $this->quoteRefRedactor = $quoteRefRedactor ?: app(QuoteRefRedactor::class);
    }

    public function blockContext(?int $rootUserId, ?string $ipAddress, ?string $quoteRef): ?array
    {
        foreach ($this->signatures($rootUserId, $ipAddress, $quoteRef) as $signature) {
            if (RateLimiter::tooManyAttempts($signature['key'], $signature['max_attempts'])) {
                return [
                    'scope' => $signature['scope'],
                    'retry_after_seconds' => max(1, RateLimiter::availableIn($signature['key'])),
                    'max_attempts' => $signature['max_attempts'],
                ];
            }
        }

        return null;
    }

    public function recordFailure(?int $rootUserId, ?string $ipAddress, ?string $quoteRef): void
    {
        foreach ($this->signatures($rootUserId, $ipAddress, $quoteRef) as $signature) {
            RateLimiter::hit($signature['key'], self::DECAY_SECONDS);
        }
    }

    private function signatures(?int $rootUserId, ?string $ipAddress, ?string $quoteRef): array
    {
        $signatures = [];

        if ($rootUserId !== null && $rootUserId > 0) {
            $signatures[] = [
                'scope' => 'root_user',
                'key' => 'adapter-vps-failure:root_user:' . $rootUserId,
                'max_attempts' => self::USER_MAX_ATTEMPTS,
            ];
        }

        $ipAddress = trim((string) $ipAddress);
        if ($ipAddress !== '') {
            $signatures[] = [
                'scope' => 'ip',
                'key' => 'adapter-vps-failure:ip:' . $ipAddress,
                'max_attempts' => self::IP_MAX_ATTEMPTS,
            ];
        }

        $quoteHash = $this->quoteRefRedactor->hash((string) $quoteRef);
        if ($quoteHash !== '') {
            $signatures[] = [
                'scope' => 'quote_ref_hash',
                'key' => 'adapter-vps-failure:quote:' . $quoteHash,
                'max_attempts' => self::QUOTE_MAX_ATTEMPTS,
            ];
        }

        return $signatures;
    }
}
