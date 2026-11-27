<?php

return [
    'turnstile' => [
        'enabled' => filter_var(env('REGISTRATION_TURNSTILE_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'site_key' => env('REGISTRATION_TURNSTILE_SITE_KEY', ''),
        'secret_key' => env('REGISTRATION_TURNSTILE_SECRET_KEY', ''),
        'expected_action' => 'register',
        'expected_hostname' => env('REGISTRATION_TURNSTILE_EXPECTED_HOSTNAME', ''),
    ],
];
