<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Cms\Front\RiseSportsbookController as FrontSports;
use App\Http\Controllers\Cms\Admin\RiseSportsbookController as AdminSports;
Route::middleware($catalogWeb)->group(function(){
 Route::get('/cms/sportsbook',[FrontSports::class,'index'])->name('cms.front.sportsbook');
 Route::get('/cms/api/betting/offers',[FrontSports::class,'offers'])->name('cms.front.sportsbook.liveOffers');
 Route::get('/cms/api/betting/offers/events/{id}',[FrontSports::class,'event']);
 Route::get('/cms/sportsbook/catalog-tree',[FrontSports::class,'catalog'])->name('cms.front.sportsbook.catalogTree');
 Route::get('/cms/sportsbook/history',[FrontSports::class,'history'])->middleware('cmsUser')->name('cms.front.sportsbook.history');
 Route::get('/cms/sportsbook/wallet',[FrontSports::class,'wallet'])->middleware('cmsUser');
 Route::any('/cms/sportsbook/unavailable/{feature}',fn()=>response()->json(['code'=>'UNTRANSFERRED_CAPABILITY'],409));
 Route::middleware(['cmsUser',\App\Http\Middleware\SyntheticLocalFinance::class,\App\Http\Middleware\SportsbookActor::class,'throttle:30,1'])->group(function(){
  Route::post('/cms/api/betting/acceptances',[\App\Http\Controllers\Cms\Api\BettingAcceptanceController::class,'store'])->name('cms.api.betting.acceptances.store');
  Route::post('/cms/api/betting/acceptances/adapter-vps',[\App\Http\Controllers\Cms\Api\BettingAcceptanceController::class,'storeAdapterVpsQuoteRefEndpoint'])->name('cms.api.betting.acceptances.adapter-vps.store');
 });
 Route::middleware(['cmsUser','cmsStaffPermission:finance.manual.override',\App\Http\Middleware\SyntheticLocalFinance::class])->group(function(){
  Route::get('/cms/admin/sportsbook',[AdminSports::class,'index'])->name('cms.admin.sportsbook.index');
  Route::get('/cms/admin/sportsbook/acceptances/{id}',[AdminSports::class,'showAcceptance'])->name('cms.admin.betting.acceptances.show');
  Route::get('/cms/admin/sportsbook/settlements/pending',[AdminSports::class,'index'])->name('cms.admin.betting.settlements.pending');
  Route::get('/cms/admin/sportsbook/settlements/{id}',[AdminSports::class,'showSettlement'])->name('cms.admin.betting.settlements.show');
  Route::post('/cms/admin/sportsbook/acceptances/{id}/review',[AdminSports::class,'review'])->middleware('cmsRecentAuth')->name('cms.admin.sportsbook.review');
  Route::post('/cms/admin/sportsbook/settlements/{id}/review',[AdminSports::class,'settle'])->middleware('cmsRecentAuth')->name('cms.admin.sportsbook.settle');
 });
});
