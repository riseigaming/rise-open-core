<?php

return [
    'geo' => [
        'enabled' => false,
        'provider' => 'none',
        'lookup_url' => '',
        'allowed_authorities' => [],
        'bearer_token' => null,
        'country_path' => env('RESTRICTIONS_GEO_COUNTRY_PATH', 'country_code'),
        'vpn_proxy_paths' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('RESTRICTIONS_GEO_VPN_PROXY_PATHS', 'security.vpn,security.proxy,vpn,proxy'))
        ))),
        'provider_name' => env('RESTRICTIONS_GEO_PROVIDER_NAME', 'configured_http'),
        'provider_version' => env('RESTRICTIONS_GEO_PROVIDER_VERSION', 'v1'),
        'cloudflare_header' => [
            'name' => 'CF-IPCountry',
            'fallback_provider' => strtolower(trim((string) env('RESTRICTIONS_GEO_CLOUDFLARE_FALLBACK_PROVIDER', 'none'))),
        ],
        'timeout_seconds' => max(1, (int) env('RESTRICTIONS_GEO_TIMEOUT_SECONDS', 3)),
        'connect_timeout_seconds' => max(1, (int) env('RESTRICTIONS_GEO_CONNECT_TIMEOUT_SECONDS', 1)),
        'cache_ttl_seconds' => max(0, (int) env('RESTRICTIONS_GEO_CACHE_TTL_SECONDS', 300)),
        'failure_cache_ttl_seconds' => max(0, (int) env('RESTRICTIONS_GEO_FAILURE_CACHE_TTL_SECONDS', 30)),
        'allow_local_header_override' => filter_var(
            env('RESTRICTIONS_ALLOW_LOCAL_HEADER_OVERRIDE', env('APP_ENV', 'production') === 'local'),
            FILTER_VALIDATE_BOOLEAN
        ),
        'local_headers' => [
            'country' => 'X-CMS-Geo-Country',
            'vpn_proxy' => 'X-CMS-VPN-Proxy',
            'failure' => 'X-CMS-Geo-Lookup-Failure',
        ],
    ],
    'vpn_registry' => [
        'cache_ttl_seconds' => max(30, (int) env('RESTRICTIONS_VPN_REGISTRY_CACHE_TTL_SECONDS', 300)),
        'max_entries' => max(1, min(500, (int) env('RESTRICTIONS_VPN_REGISTRY_MAX_ENTRIES', 250))),
    ],
];
