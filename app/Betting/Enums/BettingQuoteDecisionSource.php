<?php

namespace App\Betting\Enums;

class BettingQuoteDecisionSource
{
    const AUTOMATED = 'automated';
    const MANUAL = 'manual';

    public static function values(): array
    {
        return [
            self::AUTOMATED,
            self::MANUAL,
        ];
    }
}
