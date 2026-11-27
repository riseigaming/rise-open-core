<?php

namespace App\Betting\Support;

use Illuminate\Support\Str;

final class SportsbookLeagueFilterKey
{
    public static function fromLabel(string $sportGroupKey, string $leagueLabel): string
    {
        return $sportGroupKey . '--' . Str::slug($leagueLabel);
    }
}
