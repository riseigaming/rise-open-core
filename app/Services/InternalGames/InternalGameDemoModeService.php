<?php
namespace App\Services\InternalGames;

use App\Models\CmsGame;
use Illuminate\Support\Facades\Schema;

/** Existing safe demo gate; unsupported games remain unavailable. */
class InternalGameDemoModeService
{
    public function isInternalDemoSupported(CmsGame $game): bool
    {
        return app(InternalGameCatalog::class)->allowsNewAdmission($game)
            && config('internal_games.demo_mode.enabled') && config('internal_games.demo_mode.safe_contracts.'.app(InternalGameCatalog::class)->extractGameCode($game))
            && Schema::hasTable('internal_game_sessions') && Schema::hasTable('internal_game_rounds')
            && Schema::hasTable('internal_game_round_events');
    }
}
