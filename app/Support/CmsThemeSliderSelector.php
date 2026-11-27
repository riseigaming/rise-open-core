<?php

namespace App\Support;

use App\Models\CmsSlider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

final class CmsThemeSliderSelector
{
    public function forPlacement(string $placement, ?string $theme = null): Collection
    {
        $this->assertPlacement($placement);
        $this->publicThemeKey($theme);
        if (!Schema::hasTable('cms_sliders') || !Schema::hasTable('cms_slider_translations')) {
            return collect();
        }

        if (!$this->themeKeyColumnExists()) {
            return $this->baseQuery($placement)
                ->orderBy('rank', 'ASC')
                ->orderBy('id', 'ASC')
                ->get();
        }

        $themeKey = $this->publicThemeKey($theme);
        return $this->baseQuery($placement)
            ->where('theme_key', $themeKey)
            ->orderBy('rank', 'ASC')
            ->orderBy('id', 'ASC')
            ->get();
    }

    public function cacheKeySuffix(?string $theme = null): string
    {
        return $this->themeKeyColumnExists()
            ? $this->publicThemeKey($theme)
            : 'legacy';
    }

    public function activeIdsForPlacement(string $placement, ?string $theme = null): array
    {
        $this->assertPlacement($placement);
        $this->publicThemeKey($theme);
        if (!Schema::hasTable('cms_sliders') || !Schema::hasTable('cms_slider_translations')) {
            return [];
        }

        $query = $this->baseQuery($placement)->without(['translation', 'translations']);
        if ($this->themeKeyColumnExists()) {
            $query->where('theme_key', $this->publicThemeKey($theme));
        }

        return $query->orderBy('rank', 'ASC')->orderBy('id', 'ASC')->pluck('id')->all();
    }

    public function themeKeyColumnExists(): bool
    {
        return Schema::hasTable('cms_sliders') && Schema::hasColumn('cms_sliders', 'theme_key');
    }

    private function assertPlacement(string $placement): void
    {
        if ($placement !== CmsSlider::PLACEMENT_HOME) throw new \InvalidArgumentException('Only the Global home placement is available.');
    }

    private function baseQuery(string $placement): Builder
    {
        return CmsSlider::with(['translation', 'translations'])
            ->whereHas('translations')
            ->where('placement', $placement)
            ->where('status', 1);
    }

    private function publicThemeKey(?string $theme): string
    {
        return CmsThemeResolver::supportedThemePublicKey($theme ?? CmsThemeResolver::activePhysicalNamespace());
    }
}
