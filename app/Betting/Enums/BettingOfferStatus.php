<?php

namespace App\Betting\Enums;

class BettingOfferStatus
{
    const OPEN = 'open';
    const SUSPENDED = 'suspended';
    const HELD = 'held';
    const CLOSED = 'closed';

    public static function values(): array
    {
        return [
            self::OPEN,
            self::SUSPENDED,
            self::HELD,
            self::CLOSED,
        ];
    }
}
