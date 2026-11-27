<?php

use Illuminate\Support\Facades\Route;

// B1 HTTP positive registry from the frozen dependency closure.

// B1-HTTP-001
Route::get('/', [\App\Http\Controllers\Cms\Front\HomeController::class, 'index'])->name('cms.front.home')->middleware(['cmsUserActivity', 'cmsSessionCheck', 'cmsLanguage', \App\Http\Middleware\EnforceSiteCountryRestriction::class, \App\Http\Middleware\PublicFrontendMaintenance::class]);

// B1-HTTP-002
Route::get('/login-front', [\App\Http\Controllers\Cms\Front\AuthController::class, 'loginFront'])->name('cms.front.login')->middleware([\App\Http\Middleware\EnforceSiteCountryRestriction::class, 'cmsSessionCheck', 'cmsLanguage', \App\Http\Middleware\PublicFrontendMaintenance::class]);

// B1-HTTP-003
Route::get('/register-front', [\App\Http\Controllers\Cms\Front\AuthController::class, 'registerFront'])->name('cms.front.register')->middleware([\App\Http\Middleware\EnforceSiteCountryRestriction::class, 'cmsSessionCheck', 'cmsLanguage', \App\Http\Middleware\PublicFrontendMaintenance::class]);

// B1-HTTP-004
Route::get('/logout-front', [\App\Http\Controllers\Cms\Front\AuthController::class, 'logoutFront'])->name('cms.front.logout')->middleware([\App\Http\Middleware\EnforceSiteCountryRestriction::class, 'cmsSessionCheck', 'cmsLanguage', \App\Http\Middleware\PublicFrontendMaintenance::class]);

// B1-HTTP-005
Route::get('/verify-email', [\App\Http\Controllers\Cms\Front\AuthController::class, 'verifyEmail'])->name('cms.front.verifyEmail')->middleware([\App\Http\Middleware\EnforceSiteCountryRestriction::class, 'cmsSessionCheck', 'cmsLanguage', \App\Http\Middleware\PublicFrontendMaintenance::class]);

// B1-HTTP-006
Route::redirect('/cms', '/')->middleware([\App\Http\Middleware\EnforceSiteCountryRestriction::class]);

// B1-HTTP-007
Route::get('/cms/login', [\App\Http\Controllers\Cms\Front\AuthController::class, 'login'])->name('cms.login')->middleware(['cmsUserActivity', 'cmsSessionCheck', \App\Http\Middleware\EnforceSiteCountryRestriction::class, \App\Http\Middleware\PublicFrontendMaintenance::class]);

// B1-HTTP-008
Route::get('/cms/information', [\App\Http\Controllers\Cms\Front\StaticPageController::class, 'information'])->name('cms.information')->middleware(['cmsUserActivity', 'cmsSessionCheck', \App\Http\Middleware\EnforceSiteCountryRestriction::class, \App\Http\Middleware\PublicFrontendMaintenance::class, 'cmsLanguage']);
Route::get('/cms/learn', [\App\Http\Controllers\Cms\Front\StaticPageController::class, 'learn'])->name('cms.front.learnCenter')->middleware(['cmsUserActivity', 'cmsSessionCheck', \App\Http\Middleware\EnforceSiteCountryRestriction::class, \App\Http\Middleware\PublicFrontendMaintenance::class, 'cmsLanguage']);
Route::get('/cms/learn/{slug}', [\App\Http\Controllers\Cms\Front\StaticPageController::class, 'learn'])->name('cms.front.learnGuide')->middleware(['cmsUserActivity', 'cmsSessionCheck', \App\Http\Middleware\EnforceSiteCountryRestriction::class, \App\Http\Middleware\PublicFrontendMaintenance::class, 'cmsLanguage']);

// B1-HTTP-009
Route::get('/cms/page/{pageName}', [\App\Http\Controllers\Cms\Front\StaticPageController::class, 'getPage'])->name('cms.staticPage')->middleware(['cmsUserActivity', 'cmsSessionCheck', \App\Http\Middleware\EnforceSiteCountryRestriction::class, \App\Http\Middleware\PublicFrontendMaintenance::class, 'cmsLanguage']);

// B1-HTTP-010
Route::get('/cms/logout', [\App\Http\Controllers\Cms\Front\AuthController::class, 'logout'])->name('cms.logout')->middleware(['cmsUserActivity', 'cmsSessionCheck', \App\Http\Middleware\EnforceSiteCountryRestriction::class, \App\Http\Middleware\PublicFrontendMaintenance::class]);

// B1-HTTP-011
Route::post('/cms/login', [\App\Http\Controllers\Cms\Front\AuthController::class, 'loginPost'])->name('cms.loginPost')->middleware(['cmsUserActivity', 'cmsSessionCheck', \App\Http\Middleware\EnforceSiteCountryRestriction::class, \App\Http\Middleware\PublicFrontendMaintenance::class, 'cmsLanguage', 'throttle:10,1,cms-login:']);

// B1-HTTP-012
Route::get('/cms/register', [\App\Http\Controllers\Cms\Front\AuthController::class, 'register'])->name('cms.register')->middleware(['cmsUserActivity', 'cmsSessionCheck', \App\Http\Middleware\EnforceSiteCountryRestriction::class, \App\Http\Middleware\PublicFrontendMaintenance::class]);

// B1-HTTP-013
Route::get('/cms/register/availability', [\App\Http\Controllers\Cms\Front\AuthController::class, 'registerAvailability'])->name('cms.registerAvailability')->middleware(['cmsUserActivity', 'cmsSessionCheck', \App\Http\Middleware\EnforceSiteCountryRestriction::class, \App\Http\Middleware\PublicFrontendMaintenance::class, 'cmsLanguage', 'throttle:registration-availability']);

// B1-HTTP-014
Route::post('/cms/register', [\App\Http\Controllers\Cms\Front\AuthController::class, 'registerPost'])->name('cms.registerPost')->middleware(['cmsUserActivity', 'cmsSessionCheck', \App\Http\Middleware\EnforceSiteCountryRestriction::class, \App\Http\Middleware\PublicFrontendMaintenance::class, 'cmsLanguage', 'throttle:registration-submit']);

// B1-HTTP-015
Route::get('/cms/password/forgot', [\App\Http\Controllers\Cms\Front\CmsPasswordResetController::class, 'requestForm'])->name('cms.password.request')->middleware(['cmsUserActivity', 'cmsSessionCheck', \App\Http\Middleware\EnforceSiteCountryRestriction::class, \App\Http\Middleware\PublicFrontendMaintenance::class, 'cmsLanguage']);

// B1-HTTP-016
Route::post('/cms/password/forgot', [\App\Http\Controllers\Cms\Front\CmsPasswordResetController::class, 'sendLink'])->name('cms.password.email')->middleware(['cmsUserActivity', 'cmsSessionCheck', \App\Http\Middleware\EnforceSiteCountryRestriction::class, \App\Http\Middleware\PublicFrontendMaintenance::class, 'cmsLanguage', 'throttle:5,1']);

// B1-HTTP-017
Route::get('/cms/password/reset/{token}', [\App\Http\Controllers\Cms\Front\CmsPasswordResetController::class, 'resetForm'])->name('cms.password.reset.form')->middleware(['cmsUserActivity', 'cmsSessionCheck', \App\Http\Middleware\EnforceSiteCountryRestriction::class, \App\Http\Middleware\PublicFrontendMaintenance::class, 'cmsLanguage']);

// B1-HTTP-018
Route::post('/cms/password/reset', [\App\Http\Controllers\Cms\Front\CmsPasswordResetController::class, 'update'])->name('cms.password.update')->middleware(['cmsUserActivity', 'cmsSessionCheck', \App\Http\Middleware\EnforceSiteCountryRestriction::class, \App\Http\Middleware\PublicFrontendMaintenance::class, 'cmsLanguage', 'throttle:10,1']);

// B1-HTTP-019
Route::get('/cms/promotions', [\App\Http\Controllers\Cms\Front\PromotionController::class, 'index'])->name('cms.promotions')->middleware(['cmsUserActivity', 'cmsSessionCheck', \App\Http\Middleware\EnforceSiteCountryRestriction::class, \App\Http\Middleware\PublicFrontendMaintenance::class, 'cmsLanguage']);

// B1-HTTP-020
Route::get('/cms/promotions/detail/{id}', [\App\Http\Controllers\Cms\Front\PromotionController::class, 'detail'])->name('cms.promotions.detail')->middleware(['cmsUserActivity', 'cmsSessionCheck', \App\Http\Middleware\EnforceSiteCountryRestriction::class, \App\Http\Middleware\PublicFrontendMaintenance::class, 'cmsLanguage']);

// B1-HTTP-021
Route::post('/cms/promotion-detail', [\App\Http\Controllers\Cms\Front\PromotionController::class, 'getDetail'])->name('cms.promotions.getDetail')->middleware(['cmsUserActivity', 'cmsSessionCheck', \App\Http\Middleware\EnforceSiteCountryRestriction::class, \App\Http\Middleware\PublicFrontendMaintenance::class, 'cmsLanguage']);

// B1-HTTP-024
Route::get('/cms/edit-password', [\App\Http\Controllers\Cms\Front\ProfileController::class, 'editPassword'])->name('cms.front.editPassword')->middleware(['cmsUserActivity', 'cmsSessionCheck', \App\Http\Middleware\EnforceSiteCountryRestriction::class, \App\Http\Middleware\PublicFrontendMaintenance::class, 'cmsUser', 'cmsLanguage']);

// B1-HTTP-025
Route::post('/cms/resendVerifyEmail', [\App\Http\Controllers\Cms\Front\ProfileController::class, 'resendVerifyEmail'])->name('cms.front.resendVerifyEmail')->middleware(['cmsUserActivity', 'cmsSessionCheck', \App\Http\Middleware\EnforceSiteCountryRestriction::class, \App\Http\Middleware\PublicFrontendMaintenance::class, 'cmsUser', 'cmsLanguage']);

// B1-HTTP-026
Route::post('/cms/update-profile', [\App\Http\Controllers\Cms\Front\ProfileController::class, 'update'])->name('cms.front.updateProfile')->middleware(['cmsUserActivity', 'cmsSessionCheck', \App\Http\Middleware\EnforceSiteCountryRestriction::class, \App\Http\Middleware\PublicFrontendMaintenance::class, 'cmsUser', 'cmsLanguage']);

// B1-HTTP-027
Route::get('/cms/sessions', [\App\Http\Controllers\Cms\Front\ProfileController::class, 'sessions'])->name('cms.front.sessions')->middleware(['cmsUserActivity', 'cmsSessionCheck', \App\Http\Middleware\EnforceSiteCountryRestriction::class, \App\Http\Middleware\PublicFrontendMaintenance::class, 'cmsUser', 'cmsLanguage']);

// B1-HTTP-028
Route::post('/cms/make-logout-session', [\App\Http\Controllers\Cms\Front\ProfileController::class, 'makeLogoutSession'])->name('cms.front.makeLogoutSession')->middleware(['cmsUserActivity', 'cmsSessionCheck', \App\Http\Middleware\EnforceSiteCountryRestriction::class, \App\Http\Middleware\PublicFrontendMaintenance::class, 'cmsUser', 'cmsLanguage']);

// B1-HTTP-029
Route::post('/cms/logout-all-sessions', [\App\Http\Controllers\Cms\Front\ProfileController::class, 'logoutAllSessions'])->name('cms.front.logoutAllSessions')->middleware(['cmsUserActivity', 'cmsSessionCheck', \App\Http\Middleware\EnforceSiteCountryRestriction::class, \App\Http\Middleware\PublicFrontendMaintenance::class, 'cmsUser', 'cmsLanguage']);

// B1-HTTP-030
Route::post('/cms/logout-other-sessions', [\App\Http\Controllers\Cms\Front\ProfileController::class, 'logoutOtherSessions'])->name('cms.front.logoutOtherSessions')->middleware(['cmsUserActivity', 'cmsSessionCheck', \App\Http\Middleware\EnforceSiteCountryRestriction::class, \App\Http\Middleware\PublicFrontendMaintenance::class, 'cmsUser', 'cmsLanguage', 'throttle:10,1']);

// B1-HTTP-031
Route::get('/cms/admin', [\App\Http\Controllers\Cms\Admin\HomeController::class, 'index'])->name('cms.admin.home')->middleware(['cmsUserActivity', 'cmsSessionCheck', \App\Http\Middleware\EnforceSiteCountryRestriction::class, \App\Http\Middleware\PublicFrontendMaintenance::class, 'cmsUser', 'cmsAdmin']);

// B1-HTTP-032
Route::get('/cms/admin/languages', [\App\Http\Controllers\Cms\Admin\LanguageController::class, 'index'])->name('cms.admin.languages.index')->middleware(['cmsUserActivity', 'cmsSessionCheck', \App\Http\Middleware\EnforceSiteCountryRestriction::class, \App\Http\Middleware\PublicFrontendMaintenance::class, 'cmsUser', 'cmsAdmin']);

// B1-HTTP-033
Route::get('/cms/admin/languages/get-ajax-list', [\App\Http\Controllers\Cms\Admin\LanguageController::class, 'getAjaxList'])->name('cms.admin.languages.getAjaxList')->middleware(['cmsUserActivity', 'cmsSessionCheck', \App\Http\Middleware\EnforceSiteCountryRestriction::class, \App\Http\Middleware\PublicFrontendMaintenance::class, 'cmsUser', 'cmsAdmin']);

// B1-HTTP-034
Route::get('/cms/admin/languages/create', [\App\Http\Controllers\Cms\Admin\LanguageController::class, 'create'])->name('cms.admin.languages.create')->middleware(['cmsUserActivity', 'cmsSessionCheck', \App\Http\Middleware\EnforceSiteCountryRestriction::class, \App\Http\Middleware\PublicFrontendMaintenance::class, 'cmsUser', 'cmsAdmin']);

// B1-HTTP-035
Route::post('/cms/admin/languages/store', [\App\Http\Controllers\Cms\Admin\LanguageController::class, 'store'])->name('cms.admin.languages.store')->middleware(['cmsUserActivity', 'cmsSessionCheck', \App\Http\Middleware\EnforceSiteCountryRestriction::class, \App\Http\Middleware\PublicFrontendMaintenance::class, 'cmsUser', 'cmsAdmin']);

// B1-HTTP-036
Route::get('/cms/admin/languages/edit/{id}', [\App\Http\Controllers\Cms\Admin\LanguageController::class, 'edit'])->name('cms.admin.languages.edit')->middleware(['cmsUserActivity', 'cmsSessionCheck', \App\Http\Middleware\EnforceSiteCountryRestriction::class, \App\Http\Middleware\PublicFrontendMaintenance::class, 'cmsUser', 'cmsAdmin']);

// B1-HTTP-037
Route::put('/cms/admin/languages/update/{id}', [\App\Http\Controllers\Cms\Admin\LanguageController::class, 'update'])->name('cms.admin.languages.update')->middleware(['cmsUserActivity', 'cmsSessionCheck', \App\Http\Middleware\EnforceSiteCountryRestriction::class, \App\Http\Middleware\PublicFrontendMaintenance::class, 'cmsUser', 'cmsAdmin']);

// B1-HTTP-038
Route::delete('/cms/admin/languages/delete/{id}', [\App\Http\Controllers\Cms\Admin\LanguageController::class, 'delete'])->name('cms.admin.languages.delete')->middleware(['cmsUserActivity', 'cmsSessionCheck', \App\Http\Middleware\EnforceSiteCountryRestriction::class, \App\Http\Middleware\PublicFrontendMaintenance::class, 'cmsUser', 'cmsAdmin']);

// B1-HTTP-039
Route::post('/cms/admin/languages/bulkAction', [\App\Http\Controllers\Cms\Admin\LanguageController::class, 'bulkAction'])->name('cms.admin.languages.bulkAction')->middleware(['cmsUserActivity', 'cmsSessionCheck', \App\Http\Middleware\EnforceSiteCountryRestriction::class, \App\Http\Middleware\PublicFrontendMaintenance::class, 'cmsUser', 'cmsAdmin']);

// B1-HTTP-040
Route::post('/cms/admin/languages/country-defaults', [\App\Http\Controllers\Cms\Admin\LanguageController::class, 'setCountryDefault'])->name('cms.admin.languages.countryDefaults.set')->middleware(['cmsUserActivity', 'cmsSessionCheck', \App\Http\Middleware\EnforceSiteCountryRestriction::class, \App\Http\Middleware\PublicFrontendMaintenance::class, 'cmsUser', 'cmsAdmin']);

// B1-HTTP-041
Route::delete('/cms/admin/languages/country-defaults/{countryId}', [\App\Http\Controllers\Cms\Admin\LanguageController::class, 'removeCountryDefault'])->name('cms.admin.languages.countryDefaults.remove')->middleware(['cmsUserActivity', 'cmsSessionCheck', \App\Http\Middleware\EnforceSiteCountryRestriction::class, \App\Http\Middleware\PublicFrontendMaintenance::class, 'cmsUser', 'cmsAdmin']);

// B1-HTTP-042
Route::get('/cms/admin/sliders', [\App\Http\Controllers\Cms\Admin\SliderController::class, 'index'])->name('cms.admin.sliders.index')->middleware(['cmsUserActivity', 'cmsSessionCheck', \App\Http\Middleware\EnforceSiteCountryRestriction::class, \App\Http\Middleware\PublicFrontendMaintenance::class, 'cmsUser', 'cmsAdmin']);

// B1-HTTP-043
Route::get('/cms/admin/sliders/get-ajax-list', [\App\Http\Controllers\Cms\Admin\SliderController::class, 'getAjaxList'])->name('cms.admin.sliders.getAjaxList')->middleware(['cmsUserActivity', 'cmsSessionCheck', \App\Http\Middleware\EnforceSiteCountryRestriction::class, \App\Http\Middleware\PublicFrontendMaintenance::class, 'cmsUser', 'cmsAdmin']);

// B1-HTTP-044
Route::get('/cms/admin/sliders/create', [\App\Http\Controllers\Cms\Admin\SliderController::class, 'create'])->name('cms.admin.sliders.create')->middleware(['cmsUserActivity', 'cmsSessionCheck', \App\Http\Middleware\EnforceSiteCountryRestriction::class, \App\Http\Middleware\PublicFrontendMaintenance::class, 'cmsUser', 'cmsAdmin']);

// B1-HTTP-045
Route::post('/cms/admin/sliders/store', [\App\Http\Controllers\Cms\Admin\SliderController::class, 'store'])->name('cms.admin.sliders.store')->middleware(['cmsUserActivity', 'cmsSessionCheck', \App\Http\Middleware\EnforceSiteCountryRestriction::class, \App\Http\Middleware\PublicFrontendMaintenance::class, 'cmsUser', 'cmsAdmin']);

// B1-HTTP-046
Route::get('/cms/admin/sliders/archive', [\App\Http\Controllers\Cms\Admin\SliderController::class, 'archive'])->name('cms.admin.sliders.archive')->middleware(['cmsUserActivity', 'cmsSessionCheck', \App\Http\Middleware\EnforceSiteCountryRestriction::class, \App\Http\Middleware\PublicFrontendMaintenance::class, 'cmsUser', 'cmsAdmin']);

// B1-HTTP-047
Route::post('/cms/admin/sliders/restore/{id}', [\App\Http\Controllers\Cms\Admin\SliderController::class, 'restore'])->name('cms.admin.sliders.restore')->middleware(['cmsUserActivity', 'cmsSessionCheck', \App\Http\Middleware\EnforceSiteCountryRestriction::class, \App\Http\Middleware\PublicFrontendMaintenance::class, 'cmsUser', 'cmsAdmin']);

// B1-HTTP-048
Route::get('/cms/admin/sliders/edit/{id}', [\App\Http\Controllers\Cms\Admin\SliderController::class, 'edit'])->name('cms.admin.sliders.edit')->middleware(['cmsUserActivity', 'cmsSessionCheck', \App\Http\Middleware\EnforceSiteCountryRestriction::class, \App\Http\Middleware\PublicFrontendMaintenance::class, 'cmsUser', 'cmsAdmin']);

// B1-HTTP-049
Route::post('/cms/admin/sliders/update', [\App\Http\Controllers\Cms\Admin\SliderController::class, 'update'])->name('cms.admin.sliders.update')->middleware(['cmsUserActivity', 'cmsSessionCheck', \App\Http\Middleware\EnforceSiteCountryRestriction::class, \App\Http\Middleware\PublicFrontendMaintenance::class, 'cmsUser', 'cmsAdmin']);

// B1-HTTP-050
Route::delete('/cms/admin/sliders/delete/{id}', [\App\Http\Controllers\Cms\Admin\SliderController::class, 'delete'])->name('cms.admin.sliders.delete')->middleware(['cmsUserActivity', 'cmsSessionCheck', \App\Http\Middleware\EnforceSiteCountryRestriction::class, \App\Http\Middleware\PublicFrontendMaintenance::class, 'cmsUser', 'cmsAdmin']);

// B1-HTTP-051
Route::post('/cms/admin/sliders/bulkAction', [\App\Http\Controllers\Cms\Admin\SliderController::class, 'bulkAction'])->name('cms.admin.sliders.bulkAction')->middleware(['cmsUserActivity', 'cmsSessionCheck', \App\Http\Middleware\EnforceSiteCountryRestriction::class, \App\Http\Middleware\PublicFrontendMaintenance::class, 'cmsUser', 'cmsAdmin']);

// B1-HTTP-052
Route::get('/cms/admin/promotions', [\App\Http\Controllers\Cms\Admin\PromotionController::class, 'index'])->name('cms.admin.promotions.index')->middleware(['cmsUserActivity', 'cmsSessionCheck', \App\Http\Middleware\EnforceSiteCountryRestriction::class, \App\Http\Middleware\PublicFrontendMaintenance::class, 'cmsUser', 'cmsAdmin']);

// B1-HTTP-053
Route::get('/cms/admin/promotions/get-ajax-list', [\App\Http\Controllers\Cms\Admin\PromotionController::class, 'getAjaxList'])->name('cms.admin.promotions.getAjaxList')->middleware(['cmsUserActivity', 'cmsSessionCheck', \App\Http\Middleware\EnforceSiteCountryRestriction::class, \App\Http\Middleware\PublicFrontendMaintenance::class, 'cmsUser', 'cmsAdmin']);

// B1-HTTP-054
Route::get('/cms/admin/promotions/order', [\App\Http\Controllers\Cms\Admin\PromotionController::class, 'order'])->name('cms.admin.promotions.order')->middleware(['cmsUserActivity', 'cmsSessionCheck', \App\Http\Middleware\EnforceSiteCountryRestriction::class, \App\Http\Middleware\PublicFrontendMaintenance::class, 'cmsUser', 'cmsAdmin']);

// B1-HTTP-055
Route::post('/cms/admin/promotions/order', [\App\Http\Controllers\Cms\Admin\PromotionController::class, 'updateOrder'])->name('cms.admin.promotions.updateOrder')->middleware(['cmsUserActivity', 'cmsSessionCheck', \App\Http\Middleware\EnforceSiteCountryRestriction::class, \App\Http\Middleware\PublicFrontendMaintenance::class, 'cmsUser', 'cmsAdmin']);

// B1-HTTP-056
Route::get('/cms/admin/promotions/create', [\App\Http\Controllers\Cms\Admin\PromotionController::class, 'create'])->name('cms.admin.promotions.create')->middleware(['cmsUserActivity', 'cmsSessionCheck', \App\Http\Middleware\EnforceSiteCountryRestriction::class, \App\Http\Middleware\PublicFrontendMaintenance::class, 'cmsUser', 'cmsAdmin']);

// B1-HTTP-057
Route::post('/cms/admin/promotions/store', [\App\Http\Controllers\Cms\Admin\PromotionController::class, 'store'])->name('cms.admin.promotions.store')->middleware(['cmsUserActivity', 'cmsSessionCheck', \App\Http\Middleware\EnforceSiteCountryRestriction::class, \App\Http\Middleware\PublicFrontendMaintenance::class, 'cmsUser', 'cmsAdmin']);

// B1-HTTP-058
Route::get('/cms/admin/promotions/edit/{id}', [\App\Http\Controllers\Cms\Admin\PromotionController::class, 'edit'])->name('cms.admin.promotions.edit')->middleware(['cmsUserActivity', 'cmsSessionCheck', \App\Http\Middleware\EnforceSiteCountryRestriction::class, \App\Http\Middleware\PublicFrontendMaintenance::class, 'cmsUser', 'cmsAdmin']);

// B1-HTTP-059
Route::post('/cms/admin/promotions/update', [\App\Http\Controllers\Cms\Admin\PromotionController::class, 'update'])->name('cms.admin.promotions.update')->middleware(['cmsUserActivity', 'cmsSessionCheck', \App\Http\Middleware\EnforceSiteCountryRestriction::class, \App\Http\Middleware\PublicFrontendMaintenance::class, 'cmsUser', 'cmsAdmin']);

// B1-HTTP-060
Route::delete('/cms/admin/promotions/delete/{id}', [\App\Http\Controllers\Cms\Admin\PromotionController::class, 'delete'])->name('cms.admin.promotions.delete')->middleware(['cmsUserActivity', 'cmsSessionCheck', \App\Http\Middleware\EnforceSiteCountryRestriction::class, \App\Http\Middleware\PublicFrontendMaintenance::class, 'cmsUser', 'cmsAdmin']);

// B1-HTTP-061
Route::post('/cms/admin/promotions/bulkAction', [\App\Http\Controllers\Cms\Admin\PromotionController::class, 'bulkAction'])->name('cms.admin.promotions.bulkAction')->middleware(['cmsUserActivity', 'cmsSessionCheck', \App\Http\Middleware\EnforceSiteCountryRestriction::class, \App\Http\Middleware\PublicFrontendMaintenance::class, 'cmsUser', 'cmsAdmin']);

// B1-HTTP-062
Route::get('/cms/admin/settings/site-settings', [\App\Http\Controllers\Cms\Admin\SettingController::class, 'siteSettings'])->name('cms.admin.settings.getSiteSettings')->middleware(['cmsUserActivity', 'cmsSessionCheck', \App\Http\Middleware\EnforceSiteCountryRestriction::class, \App\Http\Middleware\PublicFrontendMaintenance::class, 'cmsUser', 'cmsAdmin']);

// B1-HTTP-063
Route::post('/cms/admin/settings/updateSiteSettings', [\App\Http\Controllers\Cms\Admin\SettingController::class, 'updateSiteSettings'])->name('cms.admin.settings.updateSiteSettings')->middleware(['cmsUserActivity', 'cmsSessionCheck', \App\Http\Middleware\EnforceSiteCountryRestriction::class, \App\Http\Middleware\PublicFrontendMaintenance::class, 'cmsUser', 'cmsAdmin', 'cmsRecentAuth']);

// B1-HTTP-064
Route::get('/cms/admin/menus/edit', [\App\Http\Controllers\Cms\Admin\MenuController::class, 'edit'])->name('cms.admin.menus.edit')->middleware(['cmsUserActivity', 'cmsSessionCheck', \App\Http\Middleware\EnforceSiteCountryRestriction::class, \App\Http\Middleware\PublicFrontendMaintenance::class, 'cmsUser', 'cmsAdmin']);

// B1-HTTP-065
Route::post('/cms/admin/menus/update', [\App\Http\Controllers\Cms\Admin\MenuController::class, 'update'])->name('cms.admin.menus.update')->middleware(['cmsUserActivity', 'cmsSessionCheck', \App\Http\Middleware\EnforceSiteCountryRestriction::class, \App\Http\Middleware\PublicFrontendMaintenance::class, 'cmsUser', 'cmsAdmin']);

Route::prefix('/cms/admin/content-pages')
    ->name('cms.admin.content-pages.')
    ->middleware(['cmsUserActivity', 'cmsSessionCheck', \App\Http\Middleware\EnforceSiteCountryRestriction::class, \App\Http\Middleware\PublicFrontendMaintenance::class, 'cmsUser', 'cmsAdmin'])
    ->controller(\App\Http\Controllers\Cms\Admin\ContentPageController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/{page}/edit', 'edit')->name('edit');
        Route::post('/', 'store')->middleware('cmsRecentAuth')->name('store');
        Route::put('/{page}', 'update')->middleware('cmsRecentAuth')->name('update');
    });

// B1-HTTP-066
Route::get('/cms/setLanguage/{languageCode}', [\App\Http\Controllers\Cms\Front\ProfileController::class, 'setLanguage'])->name('cms.setLanguage')->middleware(['cmsUserActivity', 'cmsSessionCheck', \App\Http\Middleware\EnforceSiteCountryRestriction::class, \App\Http\Middleware\PublicFrontendMaintenance::class]);

// First connected Base catalog/admin transfer; existing B1 routes stay unchanged.
require __DIR__.'/base_catalog.php';

require __DIR__.'/sportsbook.php';

require __DIR__.'/chess.php';
