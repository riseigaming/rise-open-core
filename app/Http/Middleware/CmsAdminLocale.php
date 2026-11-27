<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CmsAdminLocale
{
    private const SESSION_KEY = 'cms_admin_locale';

    private const SUPPORTED_LOCALES = ['en', 'tr'];

    public function handle(Request $request, Closure $next)
    {
        if (! $request->is('cms/admin')
            && ! $request->is('cms/admin/*')
            && ! $request->is('cms/static-translations')
            && ! $request->is('cms/static-translations/*')) {
            return $next($request);
        }

        $requestedLocale = strtolower(trim((string) $request->query('admin_locale', '')));

        if (in_array($requestedLocale, self::SUPPORTED_LOCALES, true)) {
            $request->session()->put(self::SESSION_KEY, $requestedLocale);
        }

        $locale = strtolower(trim((string) $request->session()->get(self::SESSION_KEY, 'en')));

        if (! in_array($locale, self::SUPPORTED_LOCALES, true)) {
            $locale = 'en';
        }

        $request->session()->put(self::SESSION_KEY, $locale);

        app()->setLocale($locale);

        return $next($request);
    }
}
