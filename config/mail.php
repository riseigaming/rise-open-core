<?php

return [
    // B1 captures generated account mail locally. It makes no delivery claim.
    'default' => 'log',
    'mailers' => ['log' => ['transport' => 'log', 'channel' => 'account_mail']],
    'from' => ['address' => 'noreply@example.invalid', 'name' => 'Rise Open Core'],
    'markdown' => ['theme' => 'default', 'paths' => [resource_path('views/vendor/mail')]],
    'log_channel' => 'account_mail',
    'features' => [
        'account_delivery' => env('MAIL_ACCOUNT_DELIVERY_ENABLED', true),
        'verification' => env('MAIL_VERIFICATION_ENABLED', true),
        'password_reset' => env('MAIL_PASSWORD_RESET_ENABLED', true),
    ],
];
