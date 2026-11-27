<?php

namespace App\Betting\Enums;

class BettingSettlementPrecedenceState
{
    const SYSTEM_PENDING = 'system_pending';
    const SOURCE_APPLIED = 'source_applied';
    const MANUAL_OVERRIDE = 'manual_override';
    const REVIEW_REQUIRED = 'review_required';

    public static function values(): array
    {
        return [
            self::SYSTEM_PENDING,
            self::SOURCE_APPLIED,
            self::MANUAL_OVERRIDE,
            self::REVIEW_REQUIRED,
        ];
    }
}
