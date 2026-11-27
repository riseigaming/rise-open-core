<?php

namespace App\Settings;

use App\Models\CmsSiteSetting;

class ThemeSettings
{
    public function __construct(private readonly CmsSiteSetting $setting)
    {
    }

    public static function from(?CmsSiteSetting $setting): self
    {
        return new self($setting ?? new CmsSiteSetting());
    }

    public function activeTheme(): string
    {
        $theme = trim((string) $this->setting->getAttribute('active_theme'));

        return $theme !== '' ? $theme : 'global';
    }

    public function menuVersion(): string
    {
        $version = (string) ($this->setting->getAttribute('menu_version') ?: '1');

        return in_array($version, ['1', '2'], true) ? $version : '1';
    }

    public function mobileNavigationType(): string
    {
        $type = (string) ($this->setting->getAttribute('mobile_nav_type') ?: 'type_1');

        return in_array($type, ['type_1', 'type_2'], true) ? $type : 'type_1';
    }

    public function homepageHeroAuthMode(): string
    {
        return $this->setting->homepageHeroAuthMode();
    }

    public function homepageHeroUsesInlineRegister(): bool
    {
        return $this->setting->homepageHeroUsesInlineRegister();
    }

    

    

    public function topbarFullscreenVisible(): bool
    {
        return $this->statusIsActive('topbar_fullscreen_status', true);
    }

    public function topbarLogoutVisible(): bool
    {
        return $this->statusIsActive('topbar_logout_status', true);
    }

    

    

    

    

    

    

    

    private function statusIsActive(string $attribute, bool $default): bool
    {
        $value = $this->setting->getAttribute($attribute);

        if ($value === null || $value === '') {
            return $default;
        }

        return (string) $value === 'active' || (string) $value === '1';
    }
}
