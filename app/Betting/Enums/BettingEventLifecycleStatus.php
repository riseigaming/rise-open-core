<?php

namespace App\Betting\Enums;

class BettingEventLifecycleStatus
{
    const DRAFT = 'draft';
    const SCHEDULED = 'scheduled';
    const STARTED = 'started';
    const SETTLED = 'settled';
    const CANCELLED = 'cancelled';

    public static function values(): array
    {
        return [
            self::DRAFT,
            self::SCHEDULED,
            self::STARTED,
            self::SETTLED,
            self::CANCELLED,
        ];
    }
}
