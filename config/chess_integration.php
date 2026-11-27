<?php

return [
    'enabled' => filter_var(env('CHESS_INTEGRATION_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'front_enabled' => filter_var(env('CHESS_FRONT_ENABLED', env('CHESS_INTEGRATION_ENABLED', false)), FILTER_VALIDATE_BOOLEAN),
    'admin_enabled' => filter_var(env('CHESS_ADMIN_ENABLED', env('CHESS_INTEGRATION_ENABLED', false)), FILTER_VALIDATE_BOOLEAN),
    'wallet_enabled' => filter_var(env('CHESS_WALLET_ENABLED', env('CHESS_INTEGRATION_ENABLED', false)), FILTER_VALIDATE_BOOLEAN),
];
