<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class AdminAjaxBrowserNavigationGuard
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): mixed  $next
     */
    public function handle(Request $request, Closure $next, ?string $parentRoute = null): mixed
    {
        if ($this->isBrowserDocumentRequest($request)) {
            return redirect()->route($this->redirectRoute($request, $parentRoute));
        }

        return $next($request);
    }

    private function isBrowserDocumentRequest(Request $request): bool
    {
        if ($request->ajax() || $request->expectsJson() || $request->wantsJson()) {
            return false;
        }

        $accept = strtolower(trim((string) $request->headers->get('Accept', '')));

        if ($accept === '' || $accept === '*/*' || str_contains($accept, 'application/json')) {
            return false;
        }

        return str_contains($accept, 'text/html');
    }

    private function redirectRoute(Request $request, ?string $parentRoute): string
    {
        if ($parentRoute !== null && Route::has($parentRoute)) {
            return $parentRoute;
        }

        $routeName = (string) optional($request->route())->getName();
        $explicit = [
            'cms.admin.users.search' => 'cms.admin.users.index',
            'cms.admin.users.logs.getAjaxList' => 'cms.admin.users.logs',
            'cms.admin.messages.logs.getAjaxList' => 'cms.admin.messages.logs',
            'cms.admin.externalGames.curation.search' => 'cms.admin.externalGames.curation',
            'cms.admin.betting.internalAdapter.snapshot' => 'cms.admin.betting.adapterVps',
        ];

        if (isset($explicit[$routeName]) && Route::has($explicit[$routeName])) {
            return $explicit[$routeName];
        }

        if (str_ends_with($routeName, '.getAjaxList')) {
            $candidate = substr($routeName, 0, -strlen('.getAjaxList')) . '.index';
            if (Route::has($candidate)) {
                return $candidate;
            }
        }

        return Route::has('cms.admin.home') ? 'cms.admin.home' : 'cms.front.home';
    }
}
