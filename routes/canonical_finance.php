<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Cms\Front\{WalletController,PromotionCodeController,RouletteController};
use App\Http\Controllers\Cms\Admin\{ManualFinanceOverrideController,LocalFinanceController};
use App\Http\Middleware\SyntheticLocalFinance;
Route::middleware(array_merge($catalogWeb,[SyntheticLocalFinance::class]))->group(function(){
 Route::get('/cms/wallet',[WalletController::class,'index'])->middleware('cmsUser')->name('cms.wallet.index');
 Route::post('/cms/wallet/provision',[WalletController::class,'provision'])->middleware('cmsUser')->name('cms.wallet.provision');
 Route::post('/cms/wallet/switch',[WalletController::class,'switch'])->middleware('cmsUser')->name('cms.wallet.switch');
 Route::post('/cms/promotion-code/redeem',[PromotionCodeController::class,'checkPromotionCode'])->middleware(['cmsUser','throttle:10,1'])->name('cms.promotionCode.redeem');
 Route::post('/cms/internal-games/roulette/spin',[RouletteController::class,'spin'])->middleware('throttle:internal-games-spin')->name('cms.internalGames.roulette.spin');
 Route::get('/cms/internal-games/roulette/snapshot',[RouletteController::class,'snapshot'])->middleware('throttle:internal-games-snapshot')->name('cms.internalGames.roulette.snapshot');
 Route::post('/cms/internal-games/blackjack/start',[\App\Http\Controllers\Cms\Front\BlackjackController::class,'start'])->middleware('throttle:internal-games-spin')->name('cms.internalGames.blackjack.start');
 Route::post('/cms/internal-games/blackjack/action',[\App\Http\Controllers\Cms\Front\BlackjackController::class,'action'])->middleware('throttle:internal-games-spin')->name('cms.internalGames.blackjack.action');
 Route::get('/cms/internal-games/blackjack/snapshot',[\App\Http\Controllers\Cms\Front\BlackjackController::class,'snapshot'])->middleware('throttle:internal-games-snapshot')->name('cms.internalGames.blackjack.snapshot');
 Route::get('/cms/internal-games/skyrush/snapshot',[\App\Http\Controllers\Cms\Front\SkyRushController::class,'snapshot'])->middleware('throttle:internal-crash-snapshot')->name('cms.internalGames.skyRush.snapshot');
 Route::post('/cms/internal-games/skyrush/bet',[\App\Http\Controllers\Cms\Front\SkyRushController::class,'bet'])->middleware('throttle:internal-crash-action')->name('cms.internalGames.skyRush.bet');
 Route::post('/cms/internal-games/skyrush/cashout',[\App\Http\Controllers\Cms\Front\SkyRushController::class,'cashout'])->middleware('throttle:internal-crash-action')->name('cms.internalGames.skyRush.cashout');
 Route::post('/cms/internal-games/slot/spin',[\App\Http\Controllers\Cms\Front\SlotController::class,'spin'])->middleware('throttle:internal-games-spin')->name('cms.internalGames.spin');
 Route::get('/cms/internal-games/slot/snapshot',[\App\Http\Controllers\Cms\Front\SlotController::class,'snapshot'])->middleware('throttle:internal-games-snapshot')->name('cms.internalGames.slot.snapshot');
 Route::middleware(['cmsUser','cmsStaffPermission:finance.manual.override'])->group(function(){
  Route::get('/cms/admin/finance',[ManualFinanceOverrideController::class,'create'])->name('cms.admin.transactions.manualOverride.create');
  Route::post('/cms/admin/finance',[ManualFinanceOverrideController::class,'store'])->middleware('cmsRecentAuth')->name('cms.admin.transactions.manualOverride.store');
  Route::post('/cms/admin/finance/provision',[LocalFinanceController::class,'provision'])->middleware('cmsRecentAuth')->name('cms.admin.finance.provision');
 });
 Route::post('/cms/admin/promotion-codes',[LocalFinanceController::class,'createPromo'])->middleware(['cmsUser','cmsAdmin','cmsRecentAuth'])->name('cms.admin.promotionCodes.store');
 Route::post('/cms/admin/internal-games/rounds/{roundId}/refund',[LocalFinanceController::class,'refund'])->middleware(['cmsUser','cmsAdmin','cmsRecentAuth'])->name('cms.admin.internalGames.refund');
});

Route::middleware(array_merge($catalogWeb, ['cmsUser']))->group(function () {
    Route::get('/cms/wallet/nova', [\App\Http\Controllers\Cms\Front\NovaDepositController::class, 'index'])
        ->name('cms.wallet.nova.index');
    Route::post('/cms/wallet/nova', [\App\Http\Controllers\Cms\Front\NovaDepositController::class, 'store'])
        ->middleware('throttle:10,1')->name('cms.wallet.nova.store');
    Route::post('/cms/wallet/nova/{reference}/recheck', [\App\Http\Controllers\Cms\Front\NovaDepositController::class, 'recheck'])
        ->middleware('throttle:30,1')->name('cms.wallet.nova.recheck');
    Route::get('/cms/admin/finance/nova', \App\Http\Controllers\Cms\Admin\NovaDepositReadController::class)
        ->middleware('cmsStaffPermission:finance.manual.override')->name('cms.admin.finance.nova');
});
