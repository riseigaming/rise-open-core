<?php

use Monolog\Handler\StreamHandler;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that gets used when writing
    | messages to the logs. The name specified in this option should match
    | one of the channels defined in the "channels" configuration array.
    |
    */

    'default' => config('local_safety.enabled') ? 'daily' : env('LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Out of
    | the box, Laravel uses the Monolog PHP logging library. This gives
    | you a variety of powerful log handlers / formatters to utilize.
    |
    | Available Drivers: "single", "daily", "slack", "syslog",
    |                    "errorlog", "monolog",
    |                    "custom", "stack"
    |
    */

    'channels' => [
        'account_mail' => ['driver' => 'single', 'path' => storage_path('logs/account-mail.log'), 'level' => 'debug', 'permission' => 0600],
        'stack' => [
            'driver' => 'stack',
            'channels' => [config('local_safety.enabled') ? 'daily' : 'single'],
            'ignore_exceptions' => false,
        ],
        'local_safety' => [
            'driver' => 'daily',
            'path' => storage_path('logs/local-safety.log'),
            'level' => env('LOG_LOCAL_SAFETY_LEVEL', 'error'),
            'days' => env('LOG_LOCAL_SAFETY_DAYS', 1),
        ],
        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => 'debug',
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => config('local_safety.enabled') ? env('LOG_LEVEL', 'error') : env('LOG_LEVEL', 'debug'),
            'days' => env('LOG_DAILY_DAYS', 1),
        ],

        'stderr' => [
            'driver' => 'monolog',
            'handler' => StreamHandler::class,
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'with' => [
                'stream' => 'php://stderr',
            ],
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => 'debug',
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],
    ],

];
