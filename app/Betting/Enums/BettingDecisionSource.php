<?php

namespace App\Betting\Enums;

class BettingDecisionSource
{
    const AUTOMATED = 'automated';
    const MANUAL = 'manual';
    const SOURCE = 'source';
    const SYSTEM = 'system';

    public static function values(): array
    {
        return [
            self::AUTOMATED,
            self::MANUAL,
            self::SOURCE,
            self::SYSTEM,
        ];
    }
}
