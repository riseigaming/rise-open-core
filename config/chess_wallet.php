<?php

$partnerOverrides = json_decode((string) env('CHESS_WALLET_PARTNERS_JSON', '{}'), true);
$isLocalLike = in_array((string) env('APP_ENV', 'production'), ['local', 'testing'], true);

return [
    'embed'=>['debug_enabled'=>false],
    'provider_slug' => env('CHESS_WALLET_PROVIDER_SLUG', 'chess-betting-provider'),
    'provider_name' => env('CHESS_WALLET_PROVIDER_NAME', 'Chess Betting Provider'),
    'provider_logo' => env('CHESS_WALLET_PROVIDER_LOGO', ''),
    'timestamp_tolerance_seconds' => (int) env('CHESS_WALLET_TIMESTAMP_TOLERANCE_SECONDS', 300),
    'auto_create_provider' => (bool) env('CHESS_WALLET_AUTO_CREATE_PROVIDER', false),
    'external_user_lookup_fields' => ['player_id', 'external_id', 'id', 'username'],
    'blocked_user_statuses' => ['rejected'],
    'partner_overrides' => is_array($partnerOverrides) ? $partnerOverrides : [],
    'default_provider_config' => [
        'wallet' => [
            'channel' => 'chess',
            'signing' => [
                'algorithm' => 'hmac-sha256',
            ],
            'timestamp_tolerance_seconds' => (int) env('CHESS_WALLET_TIMESTAMP_TOLERANCE_SECONDS', 300),
            'limits' => [
                'min' => null,
                'max' => null,
            ],
            'partners' => [],
        ],
        'embed' => [
            'debug_enabled' => (bool) env('CHESS_EMBED_DEBUG_ENABLED', false),
            'debug_log_path' => env('CHESS_EMBED_DEBUG_LOG_PATH', storage_path('app/chess-embed-debug.ndjson')),
            'public_base_url' => env('CHESS_EMBED_PUBLIC_BASE_URL', null),
            'consume_path' => env('CHESS_EMBED_CONSUME_PATH', '/embed/consume'),
            'entry_path' => env('CHESS_EMBED_ENTRY_PATH', '/'),
            'partner_code' => env('CHESS_EMBED_PARTNER_CODE', env('CMS_WALLET_PARTNER_CODE', null)),
            'parent_origin_override' => env('CHESS_EMBED_PARENT_ORIGIN', env('CHESS_EMBED_POST_MESSAGE_ORIGIN')),
            'post_message_origin' => env('CHESS_EMBED_CHILD_POST_MESSAGE_ORIGIN'),
            'requested_hostname' => env('CHESS_EMBED_REQUESTED_HOSTNAME', null),
            'exchange_endpoint' => env('CHESS_EMBED_EXCHANGE_ENDPOINT', null),
            'resolve_endpoint' => env('CHESS_EMBED_RESOLVE_ENDPOINT'),
            'exchange_timeout_ms' => (int) env('CHESS_EMBED_EXCHANGE_TIMEOUT_MS', 2000),
            'key_id' => env('CHESS_EMBED_KEY_ID', null),
            'secret' => env('CHESS_EMBED_SECRET', null),
            'allowed_surface_type' => env('CHESS_EMBED_ALLOWED_SURFACE_TYPE', 'PUBLIC'),
            'default_role' => env('CHESS_EMBED_DEFAULT_ROLE', 'VIEWER'),
            'embed_config_code' => env('CHESS_EMBED_CONFIG_CODE'),
            'bridge_query_key' => env('CHESS_EMBED_BRIDGE_QUERY_KEY', 'token'),
            'bootstrap_query_key' => env('CHESS_EMBED_BOOTSTRAP_QUERY_KEY', 'bootstrap'),
            'bootstrap_ttl_seconds' => (int) env('CHESS_EMBED_BOOTSTRAP_TTL_SECONDS', 120),
            'bootstrap_secret' => env('CHESS_EMBED_BOOTSTRAP_SECRET'),
            'layout_hints' => [
                'mode' => env('CHESS_EMBED_LAYOUT_MODE', 'iframe-shell'),
                'theme' => env('CHESS_EMBED_THEME_HINT', null),
                'compact_header' => (bool) env('CHESS_EMBED_COMPACT_HEADER', true),
            ],
            'locale' => [
                'supported' => ['tr' => 'tr', 'en' => 'en', 'es' => 'es'],
                'fallback' => 'tr',
            ],
        ],
    ],
];
