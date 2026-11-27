<?php

return [
    'local_contract_enabled' => (bool) env('EXTERNAL_GAMES_LOCAL_CONTRACT', false),
    'frame_origins' => array_filter(explode(',', (string)env('EXTERNAL_GAMES_FRAME_ORIGINS',''))),
    'product_label' => 'External Games',
    'product_key' => 'external_games',

    'providers' => [
        'secondary-external-provider' => [
            'enabled' => (bool) env('EXTERNAL_GAMES_SECONDARY_ENABLED', false),
            'label' => 'Secondary External Games',
            'public_alias' => 'secondary',
            'api_base_url' => env('EXTERNAL_GAMES_SECONDARY_API_BASE_URL', ''),
            'agent_token' => env('EXTERNAL_GAMES_SECONDARY_AGENT_TOKEN', ''),
            'agent_code' => env('EXTERNAL_GAMES_SECONDARY_AGENT_CODE', ''),
            'agent_secret' => env('EXTERNAL_GAMES_SECONDARY_AGENT_SECRET', ''),
            'gateway_hmac_secret' => env('EXTERNAL_GAMES_SECONDARY_GATEWAY_HMAC_SECRET', ''),
            'timeout_seconds' => (int) env('EXTERNAL_GAMES_TIMEOUT_SECONDS', 15),
            'supported_currencies' => array_values(array_filter(array_map(
                static fn (string $asset): string => strtoupper(trim($asset)),
                explode(',', (string) env('EXTERNAL_GAMES_SECONDARY_SUPPORTED_CURRENCIES', 'TRY'))
            ))),
            'allowed_countries' => array_values(array_filter(array_map(
                static fn (string $country): string => strtoupper(trim($country)),
                explode(',', (string) env('EXTERNAL_GAMES_SECONDARY_ALLOWED_COUNTRIES', ''))
            ))),
            'frame_origins' => array_filter(array_map('trim', explode(',', (string) env('EXTERNAL_GAMES_SECONDARY_FRAME_ORIGINS', '')))),
            'demo_supported' => false,
        ],
        'primary_external_provider' => [
            'label' => 'External Games',
            'alias' => 'primary_external_provider',
            'public_alias' => 'primary',
            'api_base_url' => env('EXTERNAL_GAMES_API_BASE_URL', ''),
            'agent_token' => env('EXTERNAL_GAMES_AGENT_TOKEN', ''),
            'agent_code' => env('EXTERNAL_GAMES_AGENT_CODE', ''),
            'agent_secret' => env('EXTERNAL_GAMES_AGENT_SECRET', ''),
            'timeout_seconds' => (int) env('EXTERNAL_GAMES_TIMEOUT_SECONDS', 15),
            'catalog_timeout_seconds' => (int) env('EXTERNAL_GAMES_CATALOG_TIMEOUT_SECONDS', env('EXTERNAL_GAMES_TIMEOUT_SECONDS', 60)),
            'demo_supported' => (bool) env('EXTERNAL_GAMES_DEMO_SUPPORTED', true),
            // Only contract/runtime-verified currencies. Product support alone
            // must not opt an upstream account into another currency.
            'supported_currencies' => array_values(array_filter(array_unique(array_map(
                fn ($currency) => strtoupper(trim($currency)),
                explode(',', (string) env('EXTERNAL_GAMES_SUPPORTED_CURRENCIES', 'TRY'))
            )))),
            'gateway_hmac_secret' => env('EXTERNAL_GAMES_GATEWAY_HMAC_SECRET', ''),
            'gateway_hmac_replay_window_seconds' => (int) env('EXTERNAL_GAMES_GATEWAY_HMAC_REPLAY_WINDOW_SECONDS', 120),
            'locale' => [
                'supported' => ['tr' => 'tr', 'en' => 'en', 'es' => 'es'],
                'fallback' => 'tr',
            ],
        ],
    ],

    'legacy_fun_games' => [
        'locale' => [
            'supported' => ['tr' => 'tr', 'en' => 'en', 'es' => 'es'],
            'fallback' => 'tr',
        ],
    ],

    'default_provider_alias' => 'primary_external_provider',
    'iframe' => [
        // Provider frames are capability-scoped. Never add a top-navigation
        // capability here: operator/home misconfiguration must stay contained.
        'default_sandbox_capabilities' => [
            'allow-scripts',
            'allow-same-origin',
            'allow-forms',
            'allow-pointer-lock',
            'allow-presentation',
            'allow-popups',
        ],
        'providers' => [],
        'readiness_timeout_milliseconds' => max(1000, (int) env('EXTERNAL_GAMES_IFRAME_READINESS_TIMEOUT_MS', 8000)),
    ],
    'controls_settings_key' => 'external_games_controls_v1',
    'catalog_sync_settings_key' => 'external_games_catalog_sync_v1',
    'callback_health_settings_key' => 'external_games_callback_health_v1',
    'launch_reuse_window_seconds' => (int) env('EXTERNAL_GAMES_LAUNCH_REUSE_WINDOW_SECONDS', 900),
    'launch_single_flight_wait_milliseconds' => (int) env('EXTERNAL_GAMES_LAUNCH_SINGLE_FLIGHT_WAIT_MS', 5000),

    'fun_mode' => [
        'enabled' => (bool) env('EXTERNAL_GAMES_FUN_MODE_ENABLED', true),
        'launch_path' => 'spinstruct_fun_launch',
        // Local opt-in grants only auth + GET games/all + POST games/fun_launch.
        'allow_local_requests' => (bool) env('EXTERNAL_GAMES_FUN_MODE_ALLOW_LOCAL', false),
        // Explicit observed demo redirects only; never a wildcard/global CSP relaxation.
        'local_frame_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env('EXTERNAL_GAMES_FUN_MODE_LOCAL_FRAME_ORIGINS', ''))))),
        'financial_contribution' => 'zero',
    ],

    'commercial_reporting' => [
        'billing_basis' => env('EXTERNAL_GAMES_BILLING_BASIS', 'unknown'),
        'default_rate' => env('EXTERNAL_GAMES_COMMERCIAL_DEFAULT_RATE') !== null
            ? (float) env('EXTERNAL_GAMES_COMMERCIAL_DEFAULT_RATE')
            : null,
        'legacy_current_formula' => [
            'basis' => 'net_turnover',
            'rate' => env('EXTERNAL_GAMES_LEGACY_CURRENT_RATE') !== null
                ? (float) env('EXTERNAL_GAMES_LEGACY_CURRENT_RATE')
                : null,
        ],
    ],

    'launch_payload_profiles' => [
        'strict_spinstruct_reference' => [
            'providers' => ['3oaks', '144', '3 Oaks Gaming'],
            'allowed_keys' => ['agent_token', 'agent_code', 'game_id', 'user_id', 'user_name', 'currency', 'lang', 'balance', 'status'],
            'auth_mode' => 'agent_basic_bearer',
            'balance_format' => 'string_2dp',
        ],
    ],

    'provider_response_profiles' => [
        'default' => [
            'success_status' => 1,
            'failure_status' => 0,
            'status_type' => 'numeric',
            'balance_type' => 'number',
            'include_currency' => false,
        ],
        'providers' => [
            '3oaks' => [
                'success_status' => 'success',
                'failure_status' => 'error',
                'status_type' => 'string',
                'balance_type' => 'string',
                'include_currency' => false,
                'echo_transaction_id' => false,
                'echo_round_id' => false,
                'failure_message_key' => 'message',
                'include_failure_error' => false,
                'include_failure_balance' => false,
            ],
        ],
    ],

    'provider_branding' => [
        'manual_logos_enabled' => (bool) env('EXTERNAL_GAMES_MANUAL_PROVIDER_LOGOS_ENABLED', true),
        'allowed_cdn_hosts' => array_values(array_filter(array_map(
            static fn (string $host): string => strtolower(trim($host)),
            explode(',', (string) env('EXTERNAL_GAMES_PROVIDER_LOGO_CDN_HOSTS', ''))
        ))),
        'providers' => [
            'pragmatic' => [
                'provider_key' => 'pragmatic',
                'display_name' => 'Pragmatic Play',
                'short_label' => 'Pragmatic',
                'local_logo_path' => 'cms-assets/providers/catalog/pragmatic-play.svg',
                'source' => 'manual',
                'confidence' => 'approved',
                'accent_key' => 'ruby',
                'notes' => 'Existing curated local catalog asset.',
            ],
            'pragmatic-live' => [
                'provider_key' => 'pragmatic-live',
                'display_name' => 'Pragmatic Live',
                'short_label' => 'Pragmatic Live',
                'local_logo_path' => 'cms-assets/providers/catalog/pragmatic-live.svg',
                'source' => 'manual',
                'confidence' => 'approved',
                'accent_key' => 'ruby',
                'notes' => 'Existing curated local catalog asset.',
            ],
            'evolutiongh' => [
                'provider_key' => 'evolutionGH',
                'display_name' => 'Evolution',
                'short_label' => 'Evolution',
                'local_logo_path' => 'cms-assets/providers/catalog/evolution.svg',
                'source' => 'manual',
                'confidence' => 'approved',
                'accent_key' => 'emerald',
                'notes' => 'Existing curated local catalog asset shared by Evolution variants.',
            ],
            'evolutionghls' => [
                'provider_key' => 'evolutionGHLS',
                'display_name' => 'Evolution',
                'short_label' => 'Evolution',
                'local_logo_path' => 'cms-assets/providers/catalog/evolution.svg',
                'source' => 'manual',
                'confidence' => 'approved',
                'accent_key' => 'emerald',
                'notes' => 'Existing curated local catalog asset shared by Evolution variants.',
            ],
            'evolutionghhs' => [
                'provider_key' => 'evolutionGHHS',
                'display_name' => 'Evolution',
                'short_label' => 'Evolution',
                'local_logo_path' => 'cms-assets/providers/catalog/evolution.svg',
                'source' => 'manual',
                'confidence' => 'approved',
                'accent_key' => 'emerald',
                'notes' => 'Existing curated local catalog asset shared by Evolution variants.',
            ],
            'amusnet' => [
                'provider_key' => 'amusnet',
                'display_name' => 'Amusnet',
                'short_label' => 'Amusnet',
                'local_logo_path' => 'cms-assets/providers/catalog/amusnet.png',
                'source' => 'manual',
                'confidence' => 'approved',
                'accent_key' => 'amber',
                'notes' => 'Existing curated local catalog asset.',
            ],
            'spinomenal' => [
                'provider_key' => 'spinomenal',
                'display_name' => 'Spinomenal',
                'short_label' => 'Spinomenal',
                'local_logo_path' => 'cms-assets/providers/catalog/spinomenal.svg',
                'source' => 'manual',
                'confidence' => 'approved',
                'accent_key' => 'violet',
                'notes' => 'Existing curated local catalog asset.',
            ],
            'yggdrasil-a' => [
                'provider_key' => 'yggdrasil-a',
                'display_name' => 'Yggdrasil',
                'short_label' => 'Yggdrasil',
                'local_logo_path' => 'cms-assets/providers/catalog/yggdrasil.svg',
                'source' => 'manual',
                'confidence' => 'approved',
                'accent_key' => 'teal',
                'notes' => 'Existing curated local catalog asset.',
            ],
            'redtiger-gh' => [
                'provider_key' => 'redtiger-gh',
                'display_name' => 'Red Tiger',
                'short_label' => 'Red Tiger',
                'local_logo_path' => 'cms-assets/providers/catalog/red-tiger.png',
                'source' => 'manual',
                'confidence' => 'approved',
                'accent_key' => 'ruby',
                'notes' => 'Existing curated local catalog asset.',
            ],
            'evoplay' => [
                'provider_key' => 'evoplay',
                'display_name' => 'Evoplay',
                'short_label' => 'Evoplay',
                'local_logo_path' => 'cms-assets/providers/catalog/evoplay.svg',
                'source' => 'manual',
                'confidence' => 'approved',
                'accent_key' => 'blue',
                'notes' => 'Existing curated local catalog asset.',
            ],
            'wazdan-a' => [
                'provider_key' => 'wazdan-a',
                'display_name' => 'Wazdan',
                'short_label' => 'Wazdan',
                'local_logo_path' => 'cms-assets/providers/catalog/wazdan.svg',
                'source' => 'manual',
                'confidence' => 'approved',
                'accent_key' => 'teal',
                'notes' => 'Existing curated local catalog asset.',
            ],
            'habanero' => [
                'provider_key' => 'habanero',
                'display_name' => 'Habanero',
                'short_label' => 'Habanero',
                'local_logo_path' => 'cms-assets/providers/catalog/habanero.svg',
                'source' => 'manual',
                'confidence' => 'approved',
                'accent_key' => 'amber',
                'notes' => 'Existing curated local catalog asset.',
            ],
            '100hp' => [
                'provider_key' => '100hp',
                'display_name' => '100HP Gaming',
                'short_label' => '100HP',
                'local_logo_path' => 'cms-assets/providers/catalog/100hp-gaming.png',
                'source' => 'manual',
                'confidence' => 'approved',
                'accent_key' => 'violet',
                'notes' => 'Existing curated local catalog asset.',
            ],
            '3oaks' => [
                'provider_key' => '3oaks',
                'display_name' => '3 Oaks Gaming',
                'short_label' => '3 Oaks',
                'local_logo_path' => 'cms-assets/providers/catalog/3-oaks-gaming.svg',
                'source' => 'manual',
                'confidence' => 'approved',
                'accent_key' => 'amber',
                'notes' => 'Existing curated local catalog asset.',
            ],
        ],
    ],

    'image_overrides' => [
        'manual_images_enabled' => false,
        'allowed_cdn_hosts' => array_values(array_filter(array_map(
            static fn (string $host): string => strtolower(trim($host)),
            explode(',', (string) env('EXTERNAL_GAMES_IMAGE_CDN_HOSTS', ''))
        ))),
        'allowed_local_prefixes' => [
            'cms-assets/external-games/game-images/',
            'storage/external-games/game-images/',
        ],
        'games' => [
            // 'reference-id-or-provider-game-id' => [
            //     'square' => 'https://cdn.example.test/external-games/square.webp',
            //     'cover' => 'https://cdn.example.test/external-games/cover.webp',
            //     'banner' => 'https://cdn.example.test/external-games/banner.webp',
            // ],
        ],
    ],

    'media_cache' => [
        'public_path' => env('EXTERNAL_GAMES_MEDIA_CACHE_PUBLIC_PATH', 'storage/external-games/media-cache'),
        'timeout_seconds' => (int) env('EXTERNAL_GAMES_MEDIA_CACHE_TIMEOUT_SECONDS', 15),
        'max_bytes' => (int) env('EXTERNAL_GAMES_MEDIA_CACHE_MAX_BYTES', 5242880),
        'max_total_bytes' => (int) env('EXTERNAL_GAMES_MEDIA_CACHE_MAX_TOTAL_BYTES', 8589934592),
        'orphan_grace_days' => (int) env('EXTERNAL_GAMES_MEDIA_CACHE_ORPHAN_GRACE_DAYS', 7),
        'schedule_enabled' => (bool) env(
            'EXTERNAL_GAMES_MEDIA_CACHE_SCHEDULE_ENABLED',
            ! in_array(env('APP_ENV', 'production'), ['local', 'testing'], true)
        ),
        'schedule_limit' => (int) env('EXTERNAL_GAMES_MEDIA_CACHE_SCHEDULE_LIMIT', 500),
        'schedule_provider_host' => env('EXTERNAL_GAMES_MEDIA_CACHE_SCHEDULE_PROVIDER_HOST', 'api.spinstruct.com'),
        'schedule_time' => env('EXTERNAL_GAMES_MEDIA_CACHE_SCHEDULE_TIME', '03:30'),
        'allowed_content_types' => [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif',
        ],
    ],

    'risk_limits' => [
        'enabled' => (bool) env('EXTERNAL_GAMES_RISK_LIMITS_ENABLED', true),
        'max_single_bet' => (float) env('EXTERNAL_GAMES_MAX_SINGLE_BET', 50),
        'rolling_24h_stake_limit' => (float) env('EXTERNAL_GAMES_24H_STAKE_LIMIT', 250),
        'rolling_24h_net_loss_limit' => (float) env('EXTERNAL_GAMES_24H_NET_LOSS_LIMIT', 150),
        'provider_rolling_24h_exposure_limit' => (float) env('EXTERNAL_GAMES_PROVIDER_24H_EXPOSURE_LIMIT', 0),
        // Compatibility key: fresh real-launch abuse ceiling per minute, floor 100.
        // Not the number of games/tabs a customer can have open.
        'max_open_external_sessions' => (int) env('EXTERNAL_GAMES_MAX_OPEN_SESSIONS', 100),
    ],
];
