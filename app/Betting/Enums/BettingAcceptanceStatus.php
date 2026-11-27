<?php

namespace App\Betting\Enums;

class BettingAcceptanceStatus
{
    const PENDING = 'pending';
    const HELD = 'held';
    const SUSPENDED = 'suspended';
    const ACCEPTED = 'accepted';
    const REJECTED = 'rejected';
    const CANCELLED = 'cancelled';
    const SETTLED = 'settled';

    public static function values(): array
    {
        return [
            self::PENDING,
            self::HELD,
            self::SUSPENDED,
            self::ACCEPTED,
            self::REJECTED,
            self::CANCELLED,
            self::SETTLED,
        ];
    }
}
