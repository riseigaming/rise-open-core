<?php

return [
    'defaults' => ['guard' => 'cms', 'passwords' => 'cms_users'],
    'guards' => [
        'cms' => ['driver' => 'session', 'provider' => 'cms'],
    ],
    'providers' => [
        'cms' => ['driver' => 'eloquent', 'model' => \App\Models\CmsUser::class],
    ],
    'passwords' => [
        'cms_users' => ['provider' => 'cms', 'table' => 'password_resets', 'expire' => 60, 'throttle' => 60],
    ],
    'password_timeout' => 10800,
    'cms_staff_absolute_lifetime' => env('CMS_STAFF_ABSOLUTE_SESSION_LIFETIME_MINUTES', 720),
    'cms_recent_auth_timeout' => env('CMS_RECENT_AUTH_TIMEOUT_SECONDS', 1800),
    'cms_remember_session_days' => 30,
];
