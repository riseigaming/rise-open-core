<?php

namespace App\Betting\Enums;

class BettingSettlementSourceOutcome
{
    const APPLIED = 'applied';
    const DUPLICATE = 'duplicate';
    const REVIEW_REQUIRED = 'review_required';
    const RECORDED = 'recorded';
    const SUPERSEDED = 'superseded';

    public static function values(): array
    {
        return [
            self::APPLIED,
            self::DUPLICATE,
            self::REVIEW_REQUIRED,
            self::RECORDED,
            self::SUPERSEDED,
        ];
    }
}
