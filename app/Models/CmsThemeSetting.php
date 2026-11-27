<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CmsThemeSetting extends Model
{
    use HasFactory;

    public const ACCENT_PRESETS = ['default', 'blue', 'mint'];

    public static function normalizeAccentPreset(?string $preset): string
    {
        return in_array($preset, self::ACCENT_PRESETS, true) ? $preset : 'default';
    }

    protected $fillable = [
        'theme_key',
        'logo',
        'accent_preset',
        'translations',
    ];

    protected $casts = [
        'translations' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $setting): void {
            $key = $setting->getAttribute('theme_key') ?: 'global';
            if ($key !== 'global') throw new \InvalidArgumentException('Only the Global theme is available in B1.');
            $setting->setAttribute('theme_key', $key);
        });
    }

    public function translationForLanguageId(?int $languageId): array
    {
        $translations = $this->translations ?? [];
        if (!$languageId) return [];
        $key = (string) $languageId;

        return is_array($translations[$key] ?? null) ? $translations[$key] : [];
    }

}
