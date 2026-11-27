<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class CmsSiteSetting extends Model
{
    use HasFactory;

    protected $guarded = ["id"];
    protected $fillable =[
        "chat_status",
        "socket_url",
        "socket_port",
        "default_lang",
        "register_status",
        "homepage_hero_auth_mode",
        "active_theme",
        "first_modal_image_lg",
        "first_modal_image_sm",
        "splash_image",
        "splash_status",
        "splash_display_mode",
        "balance_status",
        "logo",
        "menu_version",
        "mobile_nav_type",
        "homepage_sportsbook_card_image",
        "homepage_sportsbook_desktop_layout",
        "homepage_chess_card_image",
        "sportsbook_theme",
        "homepage_catalog_quick_order",
        "homepage_social_bet_feed_status",
        "homepage_special_games_status",
        "casino_trust_rail_status",
        "support_customer_ai_replies_status",
        "topbar_fullscreen_status",
        "topbar_logout_status",
        "persistent_game_picture_in_picture_status",
        "account_mail_status",
    ];

    protected $casts = [
        'homepage_catalog_quick_order' => 'array',
    ];

    public function translation(){
        return $this->hasOne(CmsSiteSettingTranslation::class, "cms_setting_id")->where("cms_language_id", (session("currentLanguageId") ?? 1));
    }

    public function translations(){
        return $this->hasMany(CmsSiteSettingTranslation::class, "cms_setting_id");
    }

    public function homepageHeroAuthMode(): string
    {
        $mode = (string) ($this->getAttribute('homepage_hero_auth_mode') ?: 'login');

        return in_array($mode, ['login', 'register'], true) ? $mode : 'login';
    }

    public function homepageHeroUsesInlineRegister(): bool
    {
        return $this->homepageHeroAuthMode() === 'register';
    }

    public function getLogo(): ?string
    {
        $logo = trim((string) $this->getAttribute('logo'));
        if ($logo === '' || basename($logo) !== $logo) {
            return null;
        }
        $path = 'storage/settings/logos/' . $logo;
        return is_file(public_path($path)) ? url($path) : null;
    }

    public function getPublicLogo(?string $theme = null): ?string
    {
        return $this->getLogo();
    }

    public function getMobileNavType(): string
    {
        if (! Schema::hasTable($this->getTable()) || ! Schema::hasColumn($this->getTable(), 'mobile_nav_type')) {
            return 'type_1';
        }

        $type = (string) $this->getAttribute('mobile_nav_type');

        return in_array($type, ['type_1', 'type_2'], true)
            ? $type
            : 'type_1';
    }

    public function accountMailEnabled(): bool
    {
        if (! Schema::hasTable($this->getTable()) || ! Schema::hasColumn($this->getTable(), 'account_mail_status')) {
            return true;
        }

        return (string) ($this->getAttribute('account_mail_status') ?? 'active') === 'active';
    }

    
}
