<?php

// Registration currency metadata only; no wallet or product execution registry.
return [
    'fiat_assets' => ['TRY', 'USD', 'EUR'],
    'crypto_assets'=>[], 'gameplay_assets'=>['TRY','USD','EUR'],
    'products'=>['chess'=>['supported_assets'=>['TRY','USD','EUR'],'supported_wallet_types'=>['primary_balance']],'sportsbook'=>['supported_assets'=>['TRY','USD','EUR'],'supported_wallet_types'=>['primary_balance']],'external_games'=>['supported_assets'=>['TRY','USD','EUR'],'supported_wallet_types'=>['primary_balance']],'internal_games'=>['supported_assets'=>['TRY','USD','EUR'],'supported_wallet_types'=>['primary_balance']]],
    'default_switch_block_message'=>'Close open rounds before changing wallet.',
];
