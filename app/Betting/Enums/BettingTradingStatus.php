<?php

namespace App\Betting\Enums;

class BettingTradingStatus
{
    const OPEN = 'open';
    const SUSPENDED = 'suspended';
    const CLOSED = 'closed';

    public static function values(): array
    {
        return [
            self::OPEN,
            self::SUSPENDED,
            self::CLOSED,
        ];
    }
}
