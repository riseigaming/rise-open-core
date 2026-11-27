<?php

namespace App\Betting\Enums;

class BettingQuoteGenerationMode
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
