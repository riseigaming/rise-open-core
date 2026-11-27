<?php

// Settlement authorization only. This does not grant customer/provider readiness.
return [
    // LTC receipt-credit floor by the *actual invoice fiat currency*.
    // No implied TRY/USD/EUR conversion: an unconfigured currency fails closed.
    // New deposit request minimums remain in crypto_deposits.minimums.
    'ltc_topup_operational_floors' => ['TRY' => '50.00', 'USD' => null, 'EUR' => null],
    'settlement' => [
        'btc' => ['supported' => true, 'settlement_enabled' => true, 'providers' => ['bitcart', 'bitcart_like']],
        'ltc' => ['supported' => true, 'settlement_enabled' => true, 'providers' => ['bitcart', 'bitcart_like']],
        'usdt_trc20' => ['supported' => true, 'settlement_enabled' => false, 'providers' => ['bitcart', 'bitcart_like']],
    ],
];
