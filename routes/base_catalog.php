<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Cms\Admin\{ProviderController,GameController,GameCurationController,UserController,UserTypeController,UserProductAccessController,RestrictionController};
use App\Http\Controllers\Cms\Front\GameController as PublicGames;
use App\Services\CmsStaff\CmsStaffPermissionService as P;

$catalogWeb=['cmsUserActivity','cmsSessionCheck',\App\Http\Middleware\EnforceSiteCountryRestriction::class,\App\Http\Middleware\PublicFrontendMaintenance::class,'cmsLanguage'];
Route::middleware($catalogWeb)->group(function () {
    Route::get('/casino',[PublicGames::class,'index'])->name('cms.games.index');
    Route::get('/cms/games/{slug}',[PublicGames::class,'detail'])->name('cms.games.detail');
    Route::post('/cms/games/{slug}/launch',[PublicGames::class,'launch'])->name('cms.games.launch');
});
require __DIR__.'/internal_games.php';
Route::prefix('cms/admin')->middleware(array_merge($catalogWeb,['cmsUser']))->group(function () {
    Route::middleware('cmsStaffPermission:'.P::CATALOG_PROVIDERS)->group(function () {
        Route::get('providers',[ProviderController::class,'index'])->name('cms.admin.providers.index');
        Route::get('providers/create',[ProviderController::class,'create'])->name('cms.admin.providers.create');
        Route::get('providers/{provider}/edit',[ProviderController::class,'edit'])->name('cms.admin.providers.edit');
        Route::post('providers',[ProviderController::class,'store'])->middleware('cmsRecentAuth')->name('cms.admin.providers.store');
        Route::post('providers/{provider}',[ProviderController::class,'update'])->middleware('cmsRecentAuth')->name('cms.admin.providers.update');
    });
    Route::middleware('cmsStaffPermission:'.P::CATALOG_CURATION)->group(function () {
        Route::get('games/curation',[GameCurationController::class,'index'])->name('cms.admin.games.curation');
        Route::post('games/curation',[GameCurationController::class,'saveOrder'])->middleware('cmsRecentAuth')->name('cms.admin.games.curation.save');
    });
    Route::middleware('cmsStaffPermission:'.P::CATALOG_GAMES)->group(function () {
        Route::get('games',[GameController::class,'index'])->name('cms.admin.games.index');
        Route::get('games/create',[GameController::class,'create'])->name('cms.admin.games.create');
        Route::get('games/{game}/edit',[GameController::class,'edit'])->name('cms.admin.games.edit');
        Route::post('games',[GameController::class,'store'])->middleware('cmsRecentAuth')->name('cms.admin.games.store');
        Route::post('games/{game}',[GameController::class,'update'])->middleware('cmsRecentAuth')->name('cms.admin.games.update');
    });
    Route::middleware('cmsAdmin')->group(function () {
        Route::get('users',[UserController::class,'index'])->name('cms.admin.users.index');
        Route::get('users/create',[UserController::class,'create'])->name('cms.admin.users.create');
        Route::get('users/{user}/edit',[UserController::class,'edit'])->name('cms.admin.users.edit');
        Route::post('users',[UserController::class,'store'])->middleware('cmsRecentAuth')->name('cms.admin.users.store');
        Route::post('users/{user}',[UserController::class,'update'])->middleware('cmsRecentAuth')->name('cms.admin.users.update');
        Route::post('users/{user}/access',[UserProductAccessController::class,'update'])->middleware('cmsRecentAuth')->name('cms.admin.users.access');
        Route::post('users/{user}/sessions/revoke',[UserController::class,'revokeSession'])->middleware('cmsRecentAuth')->name('cms.admin.users.sessions.revoke');
        Route::get('user-types',[UserTypeController::class,'index'])->name('cms.admin.userTypes.index');
        Route::post('user-types',[UserTypeController::class,'store'])->middleware('cmsRecentAuth')->name('cms.admin.userTypes.store');
        Route::post('user-types/{type}',[UserTypeController::class,'update'])->middleware('cmsRecentAuth')->name('cms.admin.userTypes.update');
        Route::get('restrictions',[RestrictionController::class,'index'])->name('cms.admin.restrictions.index');
        Route::post('countries',[RestrictionController::class,'storeCountry'])->middleware('cmsRecentAuth')->name('cms.admin.countries.store');
        Route::post('restrictions/{country}',[RestrictionController::class,'updateCountry'])->middleware('cmsRecentAuth')->name('cms.admin.restrictions.update');
    });
});

require __DIR__.'/canonical_finance.php';

require __DIR__.'/external_games.php';

// Ops Radar reads existing admin evidence without activity tracking or finance mutations.
Route::get('/cms/admin/ops-radar', [\App\Http\Controllers\Cms\Admin\OpsRadarController::class, 'index'])
    ->middleware(['cmsUser', 'cmsLanguage'])->name('cms.admin.opsRadar.index');
