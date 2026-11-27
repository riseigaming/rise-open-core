<?php

namespace App\Betting\Enums;

class BettingVisibilityStatus
{
    const VISIBLE = 'visible';
    const HIDDEN = 'hidden';

    public static function values(): array
    {
        return [
            self::VISIBLE,
            self::HIDDEN,
        ];
    }
}
