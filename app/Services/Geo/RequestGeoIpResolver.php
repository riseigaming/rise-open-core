<?php

namespace App\Services\Geo;

use App\Support\LocalEgress;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class RequestGeoIpResolver
{
    public function __construct(
        private readonly VpnSignalResolver $vpnSignals
    ) {
    }

    public function resolve(Request $request): GeoIpResolution
    {
        $ip = $this->normalizeIp($request->ip());

        if ($this->mayUseLocalHeaders() && $this->hasLocalOverride($request)) {
            return $this->resolveFromLocalHeaders($request, $ip);
        }

        if ($ip === null) {
            return GeoIpResolution::unavailable(null, 'request_ip_unavailable');
        }

        $localVpn = $this->vpnSignals->resolve($ip);
        if ($localVpn->confirmedVpn()) {
            return new GeoIpResolution(
                $ip,
                null,
                true,
                true,
                false,
                'local_vpn_registry',
                'v1',
                $localVpn->state,
                $localVpn->source,
                $localVpn->matchedType,
                $localVpn->label
            );
        }

        return $this->resolveFromConfiguredProvider($request, $ip);
    }

    private function resolveFromConfiguredProvider(Request $request, string $ip): GeoIpResolution
    {
        if (!(bool) config('restrictions.geo.enabled', false)) {
            return GeoIpResolution::unavailable($ip, 'geo_provider_disabled');
        }

        $provider = strtolower(trim((string) config('restrictions.geo.provider', 'http_json')));
        if ($provider === 'cloudflare_header') {
            $resolution = $this->resolveFromCloudflareHeader($request, $ip);
            if (!$resolution->countryLookupFailed
                || config('restrictions.geo.cloudflare_header.fallback_provider', 'none') !== 'http_json') {
                return $resolution;
            }

            return $this->resolveFromHttpJson($ip);
        }

        if ($provider !== 'http_json') {
            return GeoIpResolution::unavailable($ip, 'geo_provider_unsupported');
        }

        return $this->resolveFromHttpJson($ip);
    }

    private function resolveFromHttpJson(string $ip): GeoIpResolution
    {
        $provider = 'http_json';

        $template = trim((string) config('restrictions.geo.lookup_url', ''));
        if ($template === '' || !str_contains($template, '{ip}')) {
            return GeoIpResolution::unavailable($ip, 'geo_provider_unconfigured');
        }

        $url = str_replace('{ip}', rawurlencode($ip), $template);
        if (!$this->providerUrlAllowed($url)) {
            return GeoIpResolution::unavailable($ip, 'geo_provider_not_allowed');
        }

        $cacheKey = 'restrictions:geo:v2:' . hash('sha256', implode('|', [
            $provider,
            $template,
            (string) config('restrictions.geo.provider_name', 'configured_http'),
            (string) config('restrictions.geo.provider_version', 'v1'),
            $ip,
        ]));
        $cached = Cache::get($cacheKey);
        if ($cached instanceof GeoIpResolution) {
            return $cached;
        }

        $resolution = $this->requestProvider($url, $ip);
        $ttl = $resolution->countryLookupFailed || $resolution->vpnProxyLookupFailed
            ? max(0, (int) config('restrictions.geo.failure_cache_ttl_seconds', 30))
            : max(0, (int) config('restrictions.geo.cache_ttl_seconds', 300));

        if ($ttl > 0) {
            Cache::put($cacheKey, $resolution, now()->addSeconds($ttl));
        }

        return $resolution;
    }

    private function resolveFromCloudflareHeader(Request $request, string $ip): GeoIpResolution
    {
        $headerName = (string) config('restrictions.geo.cloudflare_header.name', 'CF-IPCountry');
        $country = strtoupper(trim((string) $request->headers->get($headerName, '')));
        if (preg_match('/^[A-Z]{2}$/', $country) !== 1 || in_array($country, ['XX'], true)) {
            return GeoIpResolution::unavailable($ip, 'cloudflare_header_unavailable');
        }

        return new GeoIpResolution(
            $ip,
            $country,
            false,
            false,
            true,
            'cloudflare_header',
            'v1',
            VpnSignalResolution::UNKNOWN,
            'cloudflare_header'
        );
    }

    private function requestProvider(string $url, string $ip): GeoIpResolution
    {
        try {
            $request = Http::acceptJson()
                ->timeout(max(1, (int) config('restrictions.geo.timeout_seconds', 3)))
                ->withOptions([
                    'connect_timeout' => max(1, (int) config('restrictions.geo.connect_timeout_seconds', 1)),
                ]);
            $request = $this->withBearerToken($request);
            $response = $request->get($url);

            if (!$response->successful()) {
                return GeoIpResolution::unavailable($ip, 'geo_provider_http_failure');
            }

            $payload = $response->json();
            if (!is_array($payload)) {
                return GeoIpResolution::unavailable($ip, 'geo_provider_invalid_payload');
            }

            $country = $this->normalizeCountryCode(data_get(
                $payload,
                (string) config('restrictions.geo.country_path', 'country_code')
            ));
            $vpnSignal = $this->vpnSignalFromPayload($payload);

            return new GeoIpResolution(
                $ip,
                $country,
                $vpnSignal->confirmedVpn(),
                $country === null,
                false,
                (string) config('restrictions.geo.provider_name', 'configured_http'),
                (string) config('restrictions.geo.provider_version', 'v1'),
                $vpnSignal->state,
                $vpnSignal->source
            );
        } catch (Throwable) {
            return GeoIpResolution::unavailable($ip, 'geo_provider_exception');
        }
    }

    private function resolveFromLocalHeaders(Request $request, ?string $ip): GeoIpResolution
    {
        $headers = (array) config('restrictions.geo.local_headers', []);
        $failure = strtolower(trim((string) $request->headers->get((string) ($headers['failure'] ?? ''), '')));
        $countryFailed = in_array($failure, ['country', 'all'], true);
        $vpnFailed = in_array($failure, ['vpn', 'proxy', 'vpn_proxy', 'all'], true);
        $country = $countryFailed
            ? null
            : $this->normalizeCountryCode($request->headers->get((string) ($headers['country'] ?? '')));
        $vpnValue = strtolower(trim((string) $request->headers->get((string) ($headers['vpn_proxy'] ?? ''), '')));

        if (in_array($vpnValue, ['failure', 'failed', 'unknown'], true)) {
            $vpnFailed = true;
        }

        $vpnSignal = $vpnFailed || $vpnValue === ''
            ? VpnSignalResolution::unknown('local_header_override')
            : (in_array($vpnValue, ['1', 'true', 'yes', 'on'], true)
                ? VpnSignalResolution::confirmed('local_header_override')
                : VpnSignalResolution::notDetected('local_header_override'));

        return new GeoIpResolution(
            $ip,
            $country,
            $vpnSignal->confirmedVpn(),
            $countryFailed || $country === null,
            false,
            'local_header_override',
            'test-v1',
            $vpnSignal->state,
            $vpnSignal->source
        );
    }

    private function vpnSignalFromPayload(array $payload): VpnSignalResolution
    {
        $found = false;
        $detected = false;
        $malformed = false;

        foreach ((array) config('restrictions.geo.vpn_proxy_paths', []) as $path) {
            $value = data_get($payload, (string) $path);
            if ($value === null) {
                continue;
            }

            $found = true;
            $normalized = $this->normalizeBoolean($value);
            if ($normalized === null) {
                $malformed = true;
                continue;
            }

            $detected = $detected || $normalized;
        }

        if (!$found || $malformed) {
            return VpnSignalResolution::unknown('external_http_json');
        }

        return $detected
            ? VpnSignalResolution::confirmed('external_http_json')
            : VpnSignalResolution::notDetected('external_http_json');
    }

    private function normalizeBoolean(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) && in_array($value, [0, 1], true)) {
            return $value === 1;
        }

        if (is_string($value)) {
            return match (strtolower(trim($value))) {
                '1', 'true', 'yes', 'on' => true,
                '0', 'false', 'no', 'off' => false,
                default => null,
            };
        }

        return null;
    }

    private function providerUrlAllowed(string $url): bool
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['https', 'http'], true)) {
            return false;
        }

        if ($scheme === 'http' && !app()->environment(['local', 'testing'])) {
            return false;
        }

        $authority = LocalEgress::extractUrlAuthority($url);
        $allowed = array_map(
            [LocalEgress::class, 'normalizeAuthority'],
            (array) config('restrictions.geo.allowed_authorities', [])
        );

        if ($authority === null || !in_array($authority, $allowed, true)) {
            return false;
        }

        return LocalEgress::assertUrlAllowedAgainstAuthorities(
            $url,
            $allowed,
            'restrictions.geo.lookup'
        );
    }

    private function withBearerToken(PendingRequest $request): PendingRequest
    {
        $token = trim((string) config('restrictions.geo.bearer_token', ''));

        return $token !== '' ? $request->withToken($token) : $request;
    }

    private function hasLocalOverride(Request $request): bool
    {
        foreach ((array) config('restrictions.geo.local_headers', []) as $header) {
            if ($header !== '' && $request->headers->has((string) $header)) {
                return true;
            }
        }

        return false;
    }

    private function mayUseLocalHeaders(): bool
    {
        return app()->environment(['local', 'testing'])
            && (bool) config('restrictions.geo.allow_local_header_override', false);
    }

    private function normalizeIp(mixed $ip): ?string
    {
        $ip = trim((string) $ip);

        return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : null;
    }

    private function normalizeCountryCode(mixed $country): ?string
    {
        $country = strtoupper(trim((string) $country));

        return preg_match('/^[A-Z]{2,3}$/', $country) === 1 ? $country : null;
    }
}
