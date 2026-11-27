<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class CmsContentPage extends Model
{
    public const LOCALES = ['tr', 'en', 'es'];

    protected $fillable = ['slug', 'published', 'translations'];

    protected $casts = ['published' => 'boolean', 'translations' => 'array'];

    public static function publishedAtSlug(string $slug): Builder
    {
        return self::query()->where('slug', $slug)->where('published', true);
    }

    public function localizedCopy(string $locale): array
    {
        return $this->translations[$locale] ?? $this->translations['tr'];
    }
}
