<?php

namespace App\Support;

use App\Models\CmsSiteSetting;
use App\Models\CmsSiteSettingTranslation;
use App\Models\CmsThemeSetting;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Schema;

class CmsThemeSettingOverlay
{
    public function apply(?CmsSiteSetting $setting): ?CmsSiteSetting
    {
        if (! $setting) {
            return null;
        }

        $themeKey = CmsThemeResolver::supportedThemePublicKey($setting->getAttribute('active_theme'));
        if (! $this->themeSettingsTableAvailable()) {
            return $setting;
        }

        $themeSetting = CmsThemeSetting::query()->where('theme_key', $themeKey)->first();
        if (! $themeSetting) {
            return $setting;
        }

        $setting->setAttribute('accent_preset', CmsThemeSetting::normalizeAccentPreset($themeSetting->accent_preset));
        $this->applyLogo($setting, $themeSetting);
        $this->applyCurrentTranslation($setting, $themeSetting);
        $this->applyLoadedTranslations($setting, $themeSetting);

        return $setting;
    }

    private function applyLogo(CmsSiteSetting $setting, CmsThemeSetting $themeSetting): void
    {
        $logo = trim((string) $themeSetting->getAttribute('logo'));
        if ($logo !== '') {
            $setting->setAttribute('logo', $logo);
            $setting->setAttribute('theme_scoped_logo_applied', true);
        }
    }

    private function applyCurrentTranslation(CmsSiteSetting $setting, CmsThemeSetting $themeSetting): void
    {
        if (! $setting->relationLoaded('translation')) {
            return;
        }

        $translation = $setting->getRelation('translation');
        if (! $translation instanceof CmsSiteSettingTranslation) {
            return;
        }

        $setting->setRelation(
            'translation',
            $this->translatedCopy($translation, $themeSetting->translationForLanguageId((int) $translation->cms_language_id))
        );
    }

    private function applyLoadedTranslations(CmsSiteSetting $setting, CmsThemeSetting $themeSetting): void
    {
        if (! $setting->relationLoaded('translations')) {
            return;
        }

        $translations = $setting->getRelation('translations');
        if (! $translations instanceof EloquentCollection) {
            return;
        }

        $setting->setRelation('translations', $translations->map(function (CmsSiteSettingTranslation $translation) use ($themeSetting): CmsSiteSettingTranslation {
            return $this->translatedCopy($translation, $themeSetting->translationForLanguageId((int) $translation->cms_language_id));
        }));
    }

    private function translatedCopy(CmsSiteSettingTranslation $translation, array $overrides): CmsSiteSettingTranslation
    {
        $copy = $translation->replicate();
        $copy->exists = $translation->exists;
        $copy->setAttribute($translation->getKeyName(), $translation->getKey());

        foreach (['site_title', 'site_description'] as $attribute) {
            $value = trim((string) ($overrides[$attribute] ?? ''));
            if ($value !== '') {
                $copy->setAttribute($attribute, $value);
            }
        }

        return $copy;
    }

    private function themeSettingsTableAvailable(): bool
    {
        try {
            return Schema::hasTable('cms_theme_settings');
        } catch (\Throwable $exception) {
            return false;
        }
    }
}
