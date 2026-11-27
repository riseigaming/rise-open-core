<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LocalEgress
{
    public static function isEnabled(): bool
    {
        return (bool) config('local_safety.enabled', false);
    }

    public static function shouldBlock(): bool
    {
        return self::isEnabled() && (bool) config('local_safety.block_external_requests', true);
    }

    public static function blocks(string $feature): bool
    {
        return self::isEnabled() && (bool) config("local_safety.{$feature}", false);
    }

    public static function allowedHosts(): array
    {
        return array_values(array_unique(array_filter(array_map([self::class, 'normalizeHost'], (array) config('local_safety.allowed_hosts', [])))));
    }

    public static function allowedUrlAuthorities(): array
    {
        return array_values(array_unique(array_filter(array_map([self::class, 'normalizeAuthority'], (array) config('local_safety.allowed_url_authorities', [])))));
    }

    public static function normalizeHost(?string $host): string
    {
        $host = trim((string) $host);

        if ($host === '') {
            return '';
        }

        return trim(Str::lower($host), '[]');
    }

    public static function normalizeAuthority(?string $authority): string
    {
        $authority = trim(Str::lower((string) $authority));

        if ($authority === '') {
            return '';
        }

        return trim($authority, '[]');
    }

    public static function isUrlAuthorityAllowed(?string $url): bool
    {
        $authority = self::extractUrlAuthority($url);

        if ($authority === null) {
            return !$url;
        }

        return in_array($authority, self::allowedUrlAuthorities(), true);
    }

    public static function extractUrlAuthority(?string $url): ?string
    {
        if (!$url) {
            return null;
        }

        $host = parse_url((string) $url, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return null;
        }

        $port = parse_url((string) $url, PHP_URL_PORT);

        return self::normalizeAuthority($host . ($port ? ':' . $port : ''));
    }

    public static function isHostAllowed(?string $host): bool
    {
        $host = self::normalizeHost($host);

        if ($host === '' || !$host) {
            return true;
        }

        if (!(bool) config('local_safety.only_allow_local_hosts', true)) {
            return true;
        }

        return in_array($host, self::allowedHosts(), true);
    }

    public static function isUrlAllowed(?string $url): bool
    {
        if (!$url) {
            return true;
        }

        if (Str::startsWith($url, ['/','#','?'])) {
            return true;
        }

        if (self::isUrlAuthorityAllowed($url)) {
            return true;
        }

        $host = parse_url($url, PHP_URL_HOST);

        return self::isHostAllowed(is_string($host) ? $host : null);
    }

    public static function browserAssetHosts(?string $type = null): array
    {
        $allowlist = (array) config('local_safety.browser_asset_allowlist', []);

        if ($type !== null) {
            return array_values(array_unique(array_filter(array_map([self::class, 'normalizeHost'], (array) ($allowlist[$type] ?? [])))));
        }

        $hosts = [];
        foreach ($allowlist as $assetHosts) {
            $hosts = array_merge($hosts, (array) $assetHosts);
        }

        return array_values(array_unique(array_filter(array_map([self::class, 'normalizeHost'], $hosts))));
    }

    public static function isBrowserAssetUrlAllowed(?string $url, ?string $type = null): bool
    {
        if (!$url) {
            return false;
        }

        if (self::isUrlAllowed($url)) {
            return true;
        }

        $host = parse_url((string) $url, PHP_URL_HOST);
        $host = is_string($host) ? self::normalizeHost($host) : '';

        if ($host === '') {
            return false;
        }

        $allowedHosts = $type === null ? self::browserAssetHosts() : self::browserAssetHosts($type);

        return in_array($host, $allowedHosts, true);
    }

    public static function browserAssetCspSources(?string $type = null): array
    {
        return array_map(static function (string $host): string {
            return 'https://' . $host;
        }, $type === null ? self::browserAssetHosts() : self::browserAssetHosts($type));
    }

    public static function assertUrlAllowed(?string $url, string $target, array $context = []): bool
    {
        if (!self::shouldBlock()) {
            return true;
        }

        if (self::isUrlAllowed($url)) {
            return true;
        }

        self::skip($target, array_merge($context, [
            'url' => $url,
            'host' => is_string(parse_url((string) $url, PHP_URL_HOST)) ? parse_url((string) $url, PHP_URL_HOST) : null,
            'authority' => self::extractUrlAuthority($url),
        ]));

        return false;
    }

    public static function assertUrlAllowedAgainstAuthorities(?string $url, array $allowedAuthorities, string $target, array $context = []): bool
    {
        if (!self::shouldBlock()) {
            return true;
        }

        $authority = self::extractUrlAuthority($url);
        $normalizedAllowedAuthorities = array_values(array_unique(array_filter(array_map([self::class, 'normalizeAuthority'], $allowedAuthorities))));

        if ($authority !== null && in_array($authority, $normalizedAllowedAuthorities, true)) {
            return true;
        }

        self::skip($target, array_merge($context, [
            'url' => $url,
            'host' => is_string(parse_url((string) $url, PHP_URL_HOST)) ? parse_url((string) $url, PHP_URL_HOST) : null,
            'authority' => $authority,
            'allowed_payment_authorities' => $normalizedAllowedAuthorities,
        ]));

        return false;
    }

    public static function assertHostAllowed(?string $host, string $target, array $context = []): bool
    {
        if (!self::shouldBlock()) {
            return true;
        }

        if (self::isHostAllowed($host)) {
            return true;
        }

        self::skip($target, array_merge($context, [
            'host' => $host,
        ]));

        return false;
    }

    public static function shouldBlockRequest(Request $request): bool
    {
        if (!self::isEnabled() || !(bool) config('local_safety.block_risky_routes', true)) {
            return false;
        }

        $path = trim($request->path(), '/');

        foreach ((array) config('local_safety.blocked_request_paths', []) as $pattern) {
            if ($request->is($pattern) || Str::is(trim($pattern, '/'), $path)) {
                return true;
            }
        }

        return false;
    }

    public static function contentSecurityPolicy(): string
    {
        $imgSources = array_merge(["'self'", 'data:', 'blob:'], self::browserAssetCspSources('img'));
        $fontSources = array_merge(["'self'", 'data:'], self::browserAssetCspSources('font'));
        $styleSources = array_merge(["'self'", "'unsafe-inline'"], self::browserAssetCspSources('style'));
        $scriptSources = array_merge(["'self'", "'unsafe-inline'", "'unsafe-eval'"], self::browserAssetCspSources('script'));
        $connectSources = array_unique(array_merge([
            "'self'",
            'http://127.0.0.1:*',
            'http://localhost:*',
            'ws://127.0.0.1:*',
            'ws://localhost:*',
        ], self::browserAssetCspSources()));

        return implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
            "object-src 'none'",
            'img-src ' . implode(' ', $imgSources),
            'font-src ' . implode(' ', $fontSources),
            'style-src ' . implode(' ', $styleSources),
            'script-src ' . implode(' ', $scriptSources),
            'connect-src ' . implode(' ', $connectSources),
            "frame-src 'self' http://127.0.0.1:* http://localhost:*",
            "media-src 'self' data: blob:",
        ]);
    }

    public static function skip(string $target, array $context = []): void
    {
        Log::channel(config('local_safety.log_channel', 'single'))->warning('Blocked external request in local environment.', array_merge([
            'target' => $target,
            'allowed_hosts' => self::allowedHosts(),
            'allowed_url_authorities' => self::allowedUrlAuthorities(),
        ], $context));
    }
}
