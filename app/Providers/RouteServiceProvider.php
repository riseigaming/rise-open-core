<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Cache\RateLimiting\Limit;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * This namespace is applied to your controller routes.
     *
     * In addition, it is set as the URL generator's root namespace.
     *
     * @var string
     */
    protected $namespace = 'App\Http\Controllers';

    /**
     * The path to the "home" route for your application.
     *
     * @var string
     */
    public const HOME = '/cms/admin';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        RateLimiter::for('internal-games-spin', fn (Request $request) => Limit::perMinute(20)->by('dice-roll:'.$request->ip()));
        RateLimiter::for('internal-games-snapshot', fn (Request $request) => Limit::perMinute(120)->by('dice-snapshot:'.$request->ip()));
        RateLimiter::for('internal-crash-snapshot', fn (Request $request) => Limit::perMinute(1200)->by('crash-snapshot:'.hash('sha256',$request->session()->getId())));
        RateLimiter::for('internal-crash-action', fn (Request $request) => Limit::perMinute(60)->by('crash-action:'.hash('sha256',$request->session()->getId())));
        RateLimiter::for('internal-games-play', fn (Request $request) => Limit::perMinute(60)->by('dice-play:'.$request->ip()));
        RateLimiter::for('registration-availability', fn (Request $request) => [
            Limit::perMinute(30)->by('minute:'.$request->ip()),
            Limit::perHour(180)->by('hour:'.$request->ip()),
        ]);
        RateLimiter::for('registration-submit', fn (Request $request) => [
            Limit::perMinute(10)->by('registration-submit:minute:'.$request->ip()),
            Limit::perHour(300)->by('registration-submit:hour:'.$request->ip()),
        ]);
    }

    /**
     * Define the routes for the application.
     *
     * @return void
     */
    public function map()
    {

        $this->mapWebRoutes();

        //
    }

    /**
     * Define the "web" routes for the application.
     *
     * These routes all receive session state, CSRF protection, etc.
     *
     * @return void
     */
    protected function mapWebRoutes()
    {
        Route::middleware('web')
             ->namespace($this->namespace)
             ->group(base_path('routes/web.php'));
    }

}
