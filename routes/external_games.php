<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Cms\Front\ExternalGameController as Play;
use App\Http\Controllers\Cms\Admin\ExternalGamesControlController as Admin;
use App\Http\Controllers\Api\ExternalGameCallbackController as Callback;
use App\Http\Middleware\SyntheticLocalFinance;
Route::middleware(array_merge($catalogWeb,[SyntheticLocalFinance::class]))->group(function(){
    Route::post('/cms/external-games/{game}/launch',[Play::class,'launch'])->name('cms.externalGames.launch');
    Route::get('/cms/external-games/play/{token}',[Play::class,'play'])->name('cms.externalGames.play');
});
Route::middleware(array_merge($catalogWeb,[SyntheticLocalFinance::class,'cmsUser','cmsStaffPermission:catalog.providers.manage']))->group(function(){
    Route::get('/cms/admin/external-games',[Admin::class,'edit'])->name('cms.admin.externalGames.edit');
    Route::middleware('cmsRecentAuth')->group(function(){
        Route::post('/cms/admin/external-games/risk',[Admin::class,'risk'])->middleware('cmsAdmin')->name('cms.admin.externalGames.risk');
        Route::post('/cms/admin/external-games/controls',[Admin::class,'update'])->name('cms.admin.externalGames.update');
        Route::post('/cms/admin/external-games/catalog',[Admin::class,'syncCatalog'])->name('cms.admin.externalGames.sync');
        Route::post('/cms/admin/external-games/rollout',[Admin::class,'updateRollout'])->name('cms.admin.externalGames.rollout');
    });
});
// Provider protocol endpoints use source credential/signature authentication, never browser CSRF.
Route::withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class])->middleware(SyntheticLocalFinance::class)->group(function(){
    Route::post('/api/v1/external-games/callback/{command?}',[Callback::class,'handle'])->name('externalGames.callback');
    Route::post('/integrations/games/{provider_alias}/wallet',[Callback::class,'handleProviderWallet'])->name('externalGames.providerWallet');
});
