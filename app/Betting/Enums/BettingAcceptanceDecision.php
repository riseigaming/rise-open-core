<?php

namespace App\Betting\Enums;

class BettingAcceptanceDecision
{
    const PENDING = 'pending';
    const ACCEPT = 'accept';
    const REJECT = 'reject';
    const HOLD = 'hold';
    const SUSPEND = 'suspend';
    const CANCEL = 'cancel';

    public static function values(): array
    {
        return [
            self::PENDING,
            self::ACCEPT,
            self::REJECT,
            self::HOLD,
            self::SUSPEND,
            self::CANCEL,
        ];
    }
}
