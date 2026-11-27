<?php

$enabled = in_array(env('APP_ENV', 'local'), ['local', 'testing', 'candidate', 'preview'], true);

return [
    'enabled' => $enabled,
    'allow_betting_scheduler' => false,
    'block_external_requests' => true,
    'only_allow_local_hosts' => true,
    'allowed_hosts' => ['localhost', '127.0.0.1', '::1'],
    'allowed_url_authorities' => [],
    'block_graylog' => true,
    'block_partner_payments' => true,
    'block_provider_apis' => true,
    'block_telegram' => true,
    'block_callbacks' => true,
    'block_remote_storage' => true,
    'block_mail_delivery' => true,
    'block_broadcast' => true,
    'block_websockets' => true,
    'prefer_local_assets' => true,
    'browser_asset_allowlist' => ['script' => [], 'style' => [], 'font' => [], 'img' => []],
    'block_risky_routes' => true,
    'blocked_request_paths' => [
        'artisan', 'api/artisan', 'payment', 'external/*', 'cms/socket-test',
        'api/automatic/*', 'api/external/*', 'api/integration/*', 'api/message',
        'api/telegramMessage', 'getLobby', 'cms/financial-check',
        'cms/get-payment-url', 'cms/financial/payment-result',
        'api/v1/wallet/*', 'api/v1/funGames/*', 'api/v1/funGamesBridge/*',
    ],
    'log_channel' => 'local_safety',
    'local_redirect_status' => 409,
    'blocked_request_status' => 503,
];
