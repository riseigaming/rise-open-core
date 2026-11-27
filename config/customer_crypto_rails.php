<?php

$selected = env('NOVA_PAYMENTS_RAIL', 'btc');
$enabled = (bool) env('NOVA_PAYMENTS_ENABLED', false);
$rails = [];
foreach (['BTC/BTC' => 'btc', 'LTC/LTC' => 'ltc', 'USDT/TRC20' => 'usdt_trc20'] as $identity => $key) {
    $deposit = $enabled && $key === $selected && $key !== 'usdt_trc20';
    $rails[$identity] = [
        'supported' => true,
        'customer_visible' => $deposit,
        'wallet_discoverable' => $deposit,
        'deposit_enabled' => $deposit,
        'conversion_enabled' => false,
    ];
}
return $rails;
