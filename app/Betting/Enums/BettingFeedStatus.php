<?php

namespace App\Betting\Enums;

class BettingFeedStatus
{
    const ACTIVE = 'active';
    const PAUSED = 'paused';
    const ARCHIVED = 'archived';

    public static function values(): array
    {
        return [
            self::ACTIVE,
            self::PAUSED,
            self::ARCHIVED,
        ];
    }
}
