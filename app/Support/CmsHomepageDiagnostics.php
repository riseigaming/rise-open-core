<?php

namespace App\Support;

use App\Models\CmsUser;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final class CmsHomepageDiagnostics
{
    public function report(string $stage, string $reason, ?\Throwable $error = null): array
    {
        $diagnostic = ['stage' => $stage, 'reason_code' => $reason];
        try {
            $user = auth('cms')->user();
            $diagnostic += [
                'exception_class' => $error ? get_class($error) : null,
                'theme' => CmsThemeResolver::activePhysicalNamespace(),
                'locale' => preg_replace('/[^a-z0-9_-]/', '', strtolower((string) (session('currentLocale') ?: app()->getLocale()))),
                'authenticated' => $user instanceof CmsUser,
                'user_category' => $user instanceof CmsUser ? ($user->isOperatorSmoke() ? 'smoke' : 'customer') : 'guest',
            ];
            $key = 'cms_front_home:read_warning:' . hash('sha256', json_encode($diagnostic));
            if (Cache::add($key, true, 300)) {
                Log::warning('CMS homepage read unavailable.', $diagnostic);
            }
        } catch (\Throwable) {
            // Diagnostics cannot interrupt the public read path.
        }

        return $diagnostic;
    }

    public function resolveSliderImages(Collection $sliders, callable $resolve): Collection
    {
        return $sliders->filter(function ($slider) use ($resolve) {
            try {
                $slider->resolvedTranslation = $resolve($slider);
                if (filled(data_get($slider, 'resolvedTranslation.image_url'))) {
                    return true;
                }
                $this->report('slider_render', 'active_slider_missing_image');
            } catch (\Throwable $error) {
                $this->report('slider_render', 'slider_render_resolution_failed', $error);
            }

            return false;
        })->values();
    }
}
