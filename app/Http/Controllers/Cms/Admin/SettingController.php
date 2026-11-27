<?php

namespace App\Http\Controllers\Cms\Admin;

use App\Helper\LogoUploadHelper;
use App\Http\Controllers\Controller;
use App\Models\CmsLanguage;
use App\Models\CmsSiteSetting;
use App\Models\CmsSiteSettingTranslation;
use App\Models\CmsThemeSetting;
use App\Support\CmsThemeSettingOverlay;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SettingController extends Controller
{

    public function siteSettings(Request $request)
    {
        $request->validate(['content_theme' => 'sometimes|in:global']);
        $languages = CmsLanguage::query()->where('status', 1)->get()->toArray();
        $setting = CmsSiteSetting::with('translations')->firstOrFail();
        $contentSetting = app(CmsThemeSettingOverlay::class)->apply(clone $setting);
        return view('cms.admin.settings.site-settings', [
            'languages' => $languages, 'setting' => $contentSetting,
            'transData' => $contentSetting->translations->keyBy('cms_language_id')->toArray(),
            'contentTheme' => 'global',
        ]);
    }

    public function updateSiteSettings(Request $request)
    {
        $allowed = ['_token', 'active_theme', 'active_theme_change_intent', 'content_theme', 'logo',
            'register_status', 'account_mail_status', 'homepage_hero_auth_mode', 'mobile_nav_type',
            'topbar_fullscreen_status', 'topbar_logout_status', 'langContent', 'accent_preset'];
        if (array_diff(array_keys($request->all()), $allowed) !== []) {
            return response()->json(['status' => 422, 'message' => 'Unsupported B1 settings field.'], 422);
        }
        $input = $request->validate([
            'active_theme' => 'sometimes|in:global', 'content_theme' => 'sometimes|in:global',
            'active_theme_change_intent' => 'sometimes|boolean', 'register_status' => 'sometimes|boolean',
            'account_mail_status' => 'sometimes|in:active,passive',
            'homepage_hero_auth_mode' => 'sometimes|in:login,register', 'mobile_nav_type' => 'sometimes|in:type_1,type_2',
            'topbar_fullscreen_status' => 'sometimes|in:active,passive', 'topbar_logout_status' => 'sometimes|in:active,passive',
            'accent_preset' => ['sometimes', 'required', Rule::in(CmsThemeSetting::ACCENT_PRESETS)],
            'logo' => 'sometimes|file|max:5120', 'langContent' => 'sometimes|array',
            'langContent.*' => 'array:cms_language_id,site_title,site_description',
            'langContent.*.cms_language_id' => 'required|integer|distinct|exists:cms_languages,id',
            'langContent.*.site_title' => 'sometimes|nullable|string|max:255',
            'langContent.*.site_description' => 'sometimes|nullable|string|max:65535',
        ]);
        $logo = null;
        try {
            if ($request->hasFile('logo')) {
                $logo = LogoUploadHelper::upload($request->file('logo'), 'global-' . Str::uuid(), 'settings/logos', 256, 131);
            }
            \Illuminate\Support\Facades\DB::transaction(function () use ($input, $logo): void {
                $setting = CmsSiteSetting::query()->lockForUpdate()->firstOrFail();
                $data = array_intersect_key($input, array_flip(['active_theme', 'register_status', 'account_mail_status',
                    'homepage_hero_auth_mode', 'mobile_nav_type', 'topbar_fullscreen_status', 'topbar_logout_status']));
                if ($logo !== null) $data['logo'] = $logo;
                $setting->fill($data)->save();
                $theme = CmsThemeSetting::query()->firstOrNew(['theme_key' => 'global']);
                if ($logo !== null) $theme->logo = $logo;
                if (isset($input['accent_preset'])) {
                    $theme->accent_preset = $input['accent_preset'];
                }
                $translations = $theme->translations ?? [];
                foreach ($input['langContent'] ?? [] as $content) {
                    $languageId = (int) $content['cms_language_id'];
                    $values = array_intersect_key($content, array_flip(['site_title', 'site_description']));
                    CmsSiteSettingTranslation::query()->updateOrCreate([
                        'cms_setting_id' => $setting->id, 'cms_language_id' => $languageId,
                    ], $values);
                    $translations[(string) $languageId] = array_merge($translations[(string) $languageId] ?? [], $values);
                }
                $theme->translations = $translations;
                $theme->save();
            });
            foreach (['siteSetting', 'cms_front_home:site_setting', 'cms_theme_setting:global'] as $key) cache()->forget($key);
            $fresh = app(CmsThemeSettingOverlay::class)->apply(CmsSiteSetting::with('translation')->firstOrFail());
            app()->instance('cms.siteSetting', $fresh);
            \Illuminate\Support\Facades\View::share('siteSetting', $fresh);
            return response()->json(['status' => 200, 'message' => __('admin.b1.settings_saved')]);
        } catch (\InvalidArgumentException $error) {
            return response()->json(['status' => 422, 'message' => $error->getMessage()], 422);
        } catch (\Throwable $error) {
            \Illuminate\Support\Facades\Log::error('B1 settings persistence failed.', ['exception_class' => get_class($error)]);
            return response()->json(['status' => 500, 'message' => __('admin.copy.unexpected_error')], 500);
        }
    }

}
