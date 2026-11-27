<?php

namespace App\Betting\Enums;

class BettingCanonicalResultStatus
{
    const PENDING = 'pending';
    const MAPPED = 'mapped';
    const PARTIAL = 'partial';
    const FAILED = 'failed';

    public static function values(): array
    {
        return [
            self::PENDING,
            self::MAPPED,
            self::PARTIAL,
            self::FAILED,
        ];
    }
}
