<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Cms\Front\ChessEmbedController as Embed;
use App\Http\Controllers\Cms\Admin\ChessLiveCockpitController as Cockpit;
use App\Http\Controllers\Api\ChessWalletController as Wallet;
Route::middleware($catalogWeb)->group(function(){
 Route::get('/cms/providers/chess',[Embed::class,'show'])->name('cms.front.providers.chess');
 Route::middleware(['cmsUser','cmsStaffPermission:finance.manual.override'])->prefix('cms/admin/betting/live-chess-cockpit')->group(function(){
  Route::get('/',[Cockpit::class,'index'])->name('cms.admin.betting.liveChessCockpit');
  Route::post('wallet-support',[Cockpit::class,'updateWalletSupport'])->middleware('cmsRecentAuth')->name('cms.admin.betting.liveChessCockpit.walletSupport.update');
  Route::post('templates/open',[Cockpit::class,'openFromTemplate'])->middleware('cmsRecentAuth')->name('cms.admin.betting.liveChessCockpit.templates.open');
  Route::post('boards/{board}/feature',[Cockpit::class,'featureBoard'])->middleware('cmsRecentAuth')->name('cms.admin.betting.liveChessCockpit.boards.feature');
  Route::post('boards/{board}/unfeature',[Cockpit::class,'unfeatureBoard'])->middleware('cmsRecentAuth')->name('cms.admin.betting.liveChessCockpit.boards.unfeature');
  Route::post('boards/{board}/publish',[Cockpit::class,'publishBoard'])->middleware('cmsRecentAuth')->name('cms.admin.betting.liveChessCockpit.boards.publish');
  Route::post('boards/{board}/unpublish',[Cockpit::class,'unpublishBoard'])->middleware('cmsRecentAuth')->name('cms.admin.betting.liveChessCockpit.boards.unpublish');
  Route::post('boards/{board}/hide',[Cockpit::class,'hideBoardFromBettingSurface'])->middleware('cmsRecentAuth')->name('cms.admin.betting.liveChessCockpit.boards.hide');
  Route::post('boards/{board}/show',[Cockpit::class,'showBoardOnBettingSurface'])->middleware('cmsRecentAuth')->name('cms.admin.betting.liveChessCockpit.boards.show');
  Route::post('markets/{market}/open',[Cockpit::class,'open'])->middleware('cmsRecentAuth')->name('cms.admin.betting.liveChessCockpit.markets.open');
  Route::post('markets/{market}/suspend',[Cockpit::class,'suspend'])->middleware('cmsRecentAuth')->name('cms.admin.betting.liveChessCockpit.markets.suspend');
  Route::post('markets/{market}/reopen',[Cockpit::class,'reopen'])->middleware('cmsRecentAuth')->name('cms.admin.betting.liveChessCockpit.markets.reopen');
  Route::post('markets/{market}/close',[Cockpit::class,'close'])->middleware('cmsRecentAuth')->name('cms.admin.betting.liveChessCockpit.markets.close');
  Route::post('markets/{market}/settle',[Cockpit::class,'settle'])->middleware('cmsRecentAuth')->name('cms.admin.betting.liveChessCockpit.markets.settle');
  Route::post('markets/{market}/void',[Cockpit::class,'void'])->middleware('cmsRecentAuth')->name('cms.admin.betting.liveChessCockpit.markets.void');
  Route::post('markets/{market}/note',[Cockpit::class,'updateNote'])->middleware('cmsRecentAuth')->name('cms.admin.betting.liveChessCockpit.markets.note');
 });
});
Route::prefix('wallet')->name('chessWallet.')->middleware([\App\Http\Middleware\ChessWalletAuthenticate::class,\App\Http\Middleware\SyntheticLocalFinance::class])->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class)->group(function(){
 Route::post('reservations/reserve',[Wallet::class,'reserve'])->name('reservations.reserve');
 Route::post('reservations/confirm',[Wallet::class,'confirm'])->name('reservations.confirm');
 Route::post('reservations/release',[Wallet::class,'release'])->name('reservations.release');
 Route::get('reservations/{cmsReservationId}',[Wallet::class,'show'])->name('reservations.show');
 Route::post('settlements/handoff',[Wallet::class,'settlementHandoff'])->name('settlements.handoff');
 Route::get('balance',[Wallet::class,'balance'])->name('balance');
 Route::get('health',[Wallet::class,'health'])->name('health');
});
