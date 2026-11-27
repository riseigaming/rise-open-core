<?php

namespace App\Betting\Enums;

class BettingSettlementStatus
{
    const PENDING = 'pending';
    const REVIEW = 'review';
    const FINAL = 'final';
    const VOID = 'void';
    const CANCELLED = 'cancelled';
    const POSTPONED = 'postponed';

    public static function values(): array
    {
        return [
            self::PENDING,
            self::REVIEW,
            self::FINAL,
            self::VOID,
            self::CANCELLED,
            self::POSTPONED,
        ];
    }
}
