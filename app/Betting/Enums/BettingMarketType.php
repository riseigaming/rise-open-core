<?php

namespace App\Betting\Enums;

class BettingMarketType
{
    const MATCH_RESULT = 'match_result';
    const TOTAL_GOALS = 'total_goals';
    const SPREADS = 'spreads';
    const BOTH_TEAMS_TO_SCORE = 'both_teams_to_score';

    public static function values(): array
    {
        return [
            self::MATCH_RESULT,
            self::TOTAL_GOALS,
            self::SPREADS,
            self::BOTH_TEAMS_TO_SCORE,
        ];
    }

    public static function labels(): array
    {
        return [
            self::MATCH_RESULT => 'Match Result',
            self::TOTAL_GOALS => 'Total Goals',
            self::SPREADS => 'Spreads',
            self::BOTH_TEAMS_TO_SCORE => 'Both Teams To Score',
        ];
    }
}
