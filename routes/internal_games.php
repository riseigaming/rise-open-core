<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Cms\Front\DiceController;

// Same web/session/site/maintenance/locale chain as the existing Global catalog.
Route::middleware($catalogWeb)->group(function () {
    Route::get('/cms/internal-games/play/{sessionToken}',[DiceController::class,'play'])->middleware('throttle:internal-games-play')->name('cms.internalGames.play');
    Route::post('/cms/internal-games/dice/roll',[DiceController::class,'roll'])->middleware('throttle:internal-games-spin')->name('cms.internalGames.dice.roll');
    Route::get('/cms/internal-games/dice/snapshot',[DiceController::class,'snapshot'])->middleware('throttle:internal-games-snapshot')->name('cms.internalGames.dice.snapshot');
});
