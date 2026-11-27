<?php

namespace App\Providers;

use App\Models\CmsSiteSetting;
use App\Models\CmsSlider;
use App\Support\CmsThemeSettingOverlay;
use App\Support\CmsThemeResolver;
use App\Support\CmsThemeSliderSelector;
use App\Support\DatabaseSafety;
use App\Support\LocalEgress;
use Carbon\Carbon;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        \Illuminate\Database\Connection::resolverFor('mysql', static function ($pdo, $database, $prefix, $config) {
            return new \App\Database\MySqlConnection($pdo, $database, $prefix, $config);
        });
        $this->app->singleton(DatabaseSafety::class, fn () => new DatabaseSafety());
        $this->app->singleton(\App\Services\CryptoControl\CryptoSettlementMaintenanceGate::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $locale = $this->translationLocale(config('app.locale', 'en'));
        app()->setLocale($locale);

        if (LocalEgress::shouldBlock()) {
            Http::globalMiddleware(static function (callable $next): callable {
                return static function (\Psr\Http\Message\RequestInterface $request, array $options) use ($next) {
                    $nova = $options[\App\Services\Payments\NovaGatewayClient::EGRESS_OPTION] ?? null;
                    if (!LocalEgress::isUrlAllowed((string) $request->getUri())
                        && !($nova instanceof \App\Services\Payments\NovaGatewayClient && $nova->permitsRemoteRequest($request))) {
                        // Never include a potentially signed URL or token in the error/log.
                        throw new \RuntimeException('External HTTP request blocked by local network safety policy.');
                    }
                    return $next($request, $options);
                };
            });
        }

        if ($this->app->runningInConsole()) {
            Event::listen(CommandStarting::class, function (CommandStarting $event): void {
                $this->app->make(\App\Support\ViewCachePathSafety::class)->assertSafe(
                    (string) $event->command,
                    $this->app->environment(),
                    config('view.compiled'),
                    storage_path()
                );
                $databaseSafety = $this->app->make(DatabaseSafety::class);
                $databaseSafety->announceForCommand($event->command, $event->output);
                if ($event->command !== null) {
                    $databaseSafety->guardDestructiveCommand($event->command, $event->input);
                }
            });
        }

        $setting = null;
        $cmsSiteSettingsTableAvailable = false;
        // Do not connect before CommandStarting can reject an invalid CLI target.
        if (!$this->app->runningInConsole() || $this->app->make(DatabaseSafety::class)->canProbeRuntimeConnection()) {
            try {
                $cmsSiteSettingsTableAvailable = Schema::hasTable('cms_site_settings');
            } catch (\Throwable $exception) {
                $cmsSiteSettingsTableAvailable = false;
            }
        }

        if ($cmsSiteSettingsTableAvailable) {
            $setting = CmsSiteSetting::with('translation')->first();
            $setting = app(CmsThemeSettingOverlay::class)->apply($setting);
            $this->app->instance('cms.siteSetting', $setting);
        }

        if (!defined('activeTheme')) {
            $requestedTheme = $setting->active_theme ?? env('ACTIVE_THEME', CmsThemeResolver::NEUTRAL_DEFAULT_THEME);
            $resolvedTheme = CmsThemeResolver::bootPhysicalNamespace($requestedTheme);

            define('activeTheme', $resolvedTheme);
        }

        //

        View::share([
           "siteSetting" => $setting,
           "localSafety" => config('local_safety'),
           "localSafetyEnabled" => LocalEgress::isEnabled(),
        ]);

        $indexComposer = function ($view): void {
            $data = $view->getData();
            if (!array_key_exists('sliders', $data)) {
                $view->with('sliders', app(CmsThemeSliderSelector::class)->forPlacement(CmsSlider::PLACEMENT_HOME));
            }

        };

        foreach (CmsThemeResolver::frontendComposerTargets('index') as $viewName) {
            View::composer($viewName, $indexComposer);
        }
        Carbon::setLocale($locale);
        Paginator::useBootstrap();
    }

    private function translationLocale(?string $locale): string
    {
        $locale = trim((string) ($locale ?: 'en'));

        if ($locale === '') {
            return 'en';
        }

        $canonicalLocale = str_replace('-', '_', $locale);
        $languageCode = strtolower(strtok($canonicalLocale, '_.') ?: $canonicalLocale);

        if ($languageCode !== '' && is_dir(resource_path("lang/$languageCode"))) {
            return $languageCode;
        }

        return $locale;
    }


}
