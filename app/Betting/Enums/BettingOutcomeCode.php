<?php

namespace App\Betting\Enums;

class BettingOutcomeCode
{
    const HOME = 'home';
    const DRAW = 'draw';
    const AWAY = 'away';
    const OVER = 'over';
    const UNDER = 'under';
    const YES = 'yes';
    const NO = 'no';

    public static function values(): array
    {
        return [
            self::HOME,
            self::DRAW,
            self::AWAY,
            self::OVER,
            self::UNDER,
            self::YES,
            self::NO,
        ];
    }
}
