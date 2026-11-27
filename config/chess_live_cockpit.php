<?php

$recurringMarketInstanceBoardMap = json_decode(
    (string) env('CHESS_RECURRING_MARKET_INSTANCE_BOARD_MAP_JSON', '{}'),
    true
);

return [
    'monitoring_only' => filter_var(env('CHESS_LIVE_COCKPIT_MONITORING_ONLY',true),FILTER_VALIDATE_BOOLEAN),
    'default_source' => env('CHESS_LIVE_COCKPIT_SOURCE', 'http'),
    'shadow_mode' => filter_var(env('CHESS_LIVE_COCKPIT_SHADOW_MODE', true), FILTER_VALIDATE_BOOLEAN),

    'sources' => [
        'http' => [
            'adapter' => \App\Services\ChessLiveCockpit\Sources\HttpChessLiveReadModelAdapter::class,
            'base_url' => env('CHESS_LIVE_COCKPIT_HTTP_BASE_URL', ''),
            'event_id' => env('CHESS_LIVE_COCKPIT_HTTP_EVENT_ID'),
            'shared_secret' => env('CHESS_LIVE_COCKPIT_HTTP_SHARED_SECRET', ''),
            'timeout_seconds' => (int) env('CHESS_LIVE_COCKPIT_HTTP_TIMEOUT_SECONDS', 2),
        ],
    ],

    'stale_after_minutes' => (int) env('CHESS_LIVE_COCKPIT_STALE_AFTER_MINUTES', 5),
    'poll_interval_seconds' => (int) env('CHESS_LIVE_COCKPIT_POLL_INTERVAL_SECONDS', 15),

    'recurring_market_instance_board_map' => array_replace([
        'b190e98a-454c-4625-b02c-ada4db850f8c' => 'cms-recurring-tuesday',
        '5329540f-cd11-475c-ae0d-27700dfc9f72' => 'cms-recurring-freestyle-friday',
        '0e56856e-afe0-47ee-be69-f2e517c7ae84' => 'cms-recurring-3-0-thursday',
    ], is_array($recurringMarketInstanceBoardMap) ? $recurringMarketInstanceBoardMap : []),
];
