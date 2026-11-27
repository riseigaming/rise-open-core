<?php

namespace App\Betting\Enums;

class BettingSettlementResult
{
    const PENDING = 'pending';
    const WON = 'won';
    const LOST = 'lost';
    const VOID = 'void';
    const CANCELLED = 'cancelled';
    const POSTPONED = 'postponed';
    const REFUNDED = 'refunded';

    public static function values(): array
    {
        return [
            self::PENDING,
            self::WON,
            self::LOST,
            self::VOID,
            self::CANCELLED,
            self::POSTPONED,
            self::REFUNDED,
        ];
    }
}
