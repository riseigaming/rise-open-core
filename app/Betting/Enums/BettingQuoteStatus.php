<?php

namespace App\Betting\Enums;

class BettingQuoteStatus
{
    const DRAFT = 'draft';
    const ACTIVE = 'active';
    const SUPERSEDED = 'superseded';
    const LOCKED = 'locked';
    const CLOSED = 'closed';

    public static function values(): array
    {
        return [
            self::DRAFT,
            self::ACTIVE,
            self::SUPERSEDED,
            self::LOCKED,
            self::CLOSED,
        ];
    }
}
