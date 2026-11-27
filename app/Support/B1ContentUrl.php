<?php

namespace App\Support;

use App\Models\CmsContentPage;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/** Destination policy shared by selected menus, sliders and promotion CTAs. */
final class B1ContentUrl
{
    private const ROUTES = ['cms.front.providers.chess','cms.front.sportsbook','cms.front.sportsbook.history','cms.front.learnCenter', 'cms.front.learnGuide', 'cms.games.index', 'cms.games.detail', 'cms.front.home', 'cms.promotions', 'cms.promotions.detail',
        'cms.information', 'cms.staticPage', 'cms.login', 'cms.register', 'cms.front.login',
        'cms.front.register', 'cms.front.sessions', 'cms.front.editPassword', 'cms.password.request'];

    public static function allowed(?string $value): bool
    {
        $value = trim((string) $value);
        if ($value === '' || str_starts_with($value, '//') || preg_match('/[\x00-\x20\\\\]/', $value)) {
            return false;
        }
        $parts = parse_url($value);
        if ($parts === false || isset($parts['user']) || isset($parts['pass'])) return false;
        if (isset($parts['scheme']) && !in_array(strtolower($parts['scheme']), ['http', 'https'], true)) return false;
        if (isset($parts['host']) && strtolower($parts['host']) !== strtolower(request()->getHost())) {
            return ($parts['scheme'] ?? '') === 'https' && !isset($parts['port'])
                && in_array(strtolower($parts['host']), config('cms_public.content_link_hosts', []), true);
        }
        if (isset($parts['host']) && ($parts['port'] ?? (($parts['scheme'] ?? '') === 'https' ? 443 : 80)) !== request()->getPort()) return false;
        $path = $parts['path'] ?? '/';
        if (!str_starts_with($path, '/')) return false;
        try {
            $route = app('router')->getRoutes()->match(Request::create(url($path), 'GET'));
            if (!in_array($route->getName(), self::ROUTES, true)) return false;
            if ($route->getName() === 'cms.front.learnGuide') return in_array($route->parameter('slug'), GlobalGuides::SLUGS, true);
            return $route->getName() !== 'cms.staticPage'
                || self::pageIsVisible((string) $route->parameter('pageName'));
        } catch (HttpExceptionInterface) {
            return false;
        }
    }
    public static function isHiddenPageLink(string $href): bool
    {
        $parts = parse_url($href);
        return $parts !== false
            && (!isset($parts['host']) || strcasecmp($parts['host'], request()->getHost()) === 0)
            && preg_match('~^/cms/page/([^/]+)$~D', rtrim(rawurldecode($parts['path'] ?? ''), '/'), $matches)
            && !self::pageIsVisible($matches[1]);
    }

    private static function pageIsVisible(string $slug): bool
    {
        return isset(WinxInformationPages::PAGES[$slug]) || CmsContentPage::publishedAtSlug($slug)->exists();
    }
}
