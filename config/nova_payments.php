<?php

$authority = trim((string) env('NOVA_PAYMENTS_REMOTE_AUTHORITY', ''));

return [
    'enabled' => env('NOVA_PAYMENTS_ENABLED', false),
    'url' => env('NOVA_PAYMENTS_URL'),
    'username' => env('NOVA_PAYMENTS_USERNAME'),
    'password' => env('NOVA_PAYMENTS_PASSWORD'),
    'site_secret' => env('NOVA_PAYMENTS_SITE_SECRET'),
    'fiat_currency' => env('NOVA_PAYMENTS_FIAT', 'TRY'),
    'rail' => env('NOVA_PAYMENTS_RAIL', 'btc'),
    'remote_authority' => $authority,
    'remote_sessions_enabled' => env('NOVA_PAYMENTS_REMOTE_SESSIONS_ENABLED', false),
    'remote_credit_enabled' => env('NOVA_PAYMENTS_REMOTE_CREDIT_ENABLED', false),
    'provider_mode' => $authority === '' ? 'test' : 'LIVE_HTTP',
    'network_mode' => env('NOVA_PAYMENTS_NETWORK_MODE', $authority === '' ? 'testnet' : 'mainnet'),
];
