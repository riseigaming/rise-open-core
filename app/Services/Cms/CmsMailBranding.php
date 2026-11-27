<?php

namespace App\Services\Cms;

use App\Models\CmsSiteSetting;
use App\Support\CmsThemeResolver;
use App\Support\CmsThemeSettingOverlay;
use Illuminate\Support\Facades\Schema;

class CmsMailBranding
{
    /**
     * @return array{name: string, theme: string, logo_url: string|null, accent: string, button: string}
     */
    public function current(): array
    {
        $setting = null;

        try {
            if (Schema::hasTable('cms_site_settings')) {
                $query = CmsSiteSetting::query();
                if (Schema::hasTable('cms_site_setting_translations')) {
                    $query->with('translation');
                }
                $setting = $query->first();
                $setting = app(CmsThemeSettingOverlay::class)->apply($setting);
            }
        } catch (\Throwable $throwable) {
            report($throwable);
        }

        $theme = CmsThemeResolver::supportedThemePublicKey(
            $setting?->getAttribute('active_theme') ?: CmsThemeResolver::activePhysicalNamespace()
        );
        $translation = $setting && $setting->relationLoaded('translation')
            ? $setting->getRelation('translation')
            : null;
        $configuredName = trim((string) optional($translation)->site_title);

        return [
            'name' => $configuredName !== '' ? $configuredName : 'Rise Open Core',
            'theme' => 'global', 'logo_url' => $setting?->getPublicLogo(),
            'accent' => '#d6ae4a', 'button' => '#f1c84b',
        ];
    }
}
