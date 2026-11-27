<?php

namespace App\Services\InternalGames;

class RouletteOutcomeResolver
{
    public function resolveWinningNumber(): int
    {
        return random_int(0, 36);
    }
}
