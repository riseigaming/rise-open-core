<?php

namespace App\Http\Controllers\Cms\Front;

use App\Http\Controllers\Controller;
use App\Models\CmsSlider;
use App\Support\CmsHomepageDiagnostics;
use App\Support\CmsThemeSliderSelector;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class HomeController extends Controller
{
    private const HOME_CACHE_SECONDS = 300;

    public function index()
    {
        $data = $this->defaultHomeViewData();
        $data['homepageReadFailures'] = [];
        $data['sliderReadState'] = 'ready';
        $data['sliders'] = $this->readHomepageModule('slider_query', function () use (&$data) {
            $selector = app(CmsThemeSliderSelector::class);
            $suffix = $selector->cacheKeySuffix();
            $key = 'cms_front_home:sliders:' . $this->currentHomeLocaleCacheKey() . ':theme:' . $suffix;
            $cached = Cache::get($key);
            if ($cached !== null) {
                $valid = $cached instanceof Collection && $cached->every(fn ($slider) => $slider instanceof CmsSlider
                    && (int) $slider->status === 1 && $slider->placement === CmsSlider::PLACEMENT_HOME
                    && ($suffix === 'legacy' || $slider->theme_key === $suffix));
                // A stale cached active flag must never resurrect a now-inactive slide.
                if (!$valid || $cached->pluck('id')->all() !== $selector->activeIdsForPlacement(CmsSlider::PLACEMENT_HOME)) {
                    $reason = 'slider_cache_invalid';
                    if ($cached instanceof Collection && $cached->contains(fn ($slider) => $slider instanceof CmsSlider
                        && $suffix !== 'legacy' && $slider->theme_key !== $suffix)) {
                        $reason = 'theme_scope_mismatch';
                    }
                    $data['homepageReadFailures'][] = app(CmsHomepageDiagnostics::class)->report('slider_query', $reason);
                    Cache::forget($key);
                    $cached = null;
                }
            }
            $sliders = $cached ?? $selector->forPlacement(CmsSlider::PLACEMENT_HOME);
            if ($cached === null) {
                Cache::put($key, $sliders, self::HOME_CACHE_SECONDS);
            }
            if ($sliders->isEmpty()) {
                $data['sliderReadState'] = 'no_active_home_sliders';
                $data['homepageReadFailures'][] = app(CmsHomepageDiagnostics::class)->report('slider_query', 'no_active_home_sliders');
            }

            return $sliders;
        }, collect(), $data['homepageReadFailures']);
        if (collect($data['homepageReadFailures'])->contains('reason_code', 'slider_query_failed')) {
            $data['sliderReadState'] = 'slider_query_failed';
        }

        $data['topThreePromotions'] = \App\Models\CmsPromotion::query()->with('translation')
            ->publiclyVisible()->orderBy('rank')->limit(3)->get();

        $data['catalogGames']=app(\App\Services\Games\CatalogReadService::class)->items('home_special_games',auth('cms')->user())->take(8);
        return response(frontView('home', $data))->header('Cache-Control','private, no-store');
    }

    private function readHomepageModule(string $stage, callable $read, $default, array &$failures)
    {
        try {
            return $read();
        } catch (\Throwable $error) {
            $failures[] = app(CmsHomepageDiagnostics::class)->report($stage,
                $stage === 'slider_query' ? 'slider_query_failed' : 'homepage_module_read_failed', $error);
            return $default;
        }
    }

    private function defaultHomeViewData(): array
    {
        return ['login' => false, 'sliders' => collect(), 'topThreePromotions' => collect()];
    }

    /**
     * The payment-method rail is a logged-in, zero-balance prompt only. Use the
     * canonical wallet projection so linked accounts resolve to the same balance
     * displayed elsewhere in the frontend; fail closed if it cannot be read.
     */

    private function currentHomeLocaleCacheKey(): string
    {
        $locale = Str::lower((string) (session('currentLocale') ?: app()->getLocale() ?: config('app.locale', 'en')));

        return preg_replace('/[^a-z0-9_-]/', '', $locale) ?: 'en';
    }

}
