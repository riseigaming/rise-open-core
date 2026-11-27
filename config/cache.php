<?php

use Illuminate\Support\Str;

return [
    'default' => env('CACHE_DRIVER', 'file'),
    'stores' => [
        'array' => ['driver' => 'array'],
        'file' => ['driver' => 'file', 'path' => env('CACHE_FILE_PATH', storage_path('framework/cache/data'))],
    ],
    'prefix' => env('CACHE_PREFIX', Str::slug(env('APP_NAME', 'rise_b1'), '_').'_cache'),
];
