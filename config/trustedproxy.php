<?php

use Illuminate\Http\Request;

$trustedProxies = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('TRUSTED_PROXIES', ''))
), static function (string $proxy): bool {
    if ($proxy === '' || in_array($proxy, ['*', '**'], true)) {
        return false;
    }

    [$address, $prefix] = array_pad(explode('/', $proxy, 2), 2, null);
    if (filter_var($address, FILTER_VALIDATE_IP) === false) {
        return false;
    }

    if ($prefix === null) {
        return true;
    }

    if (!ctype_digit($prefix)) {
        return false;
    }

    $maximum = str_contains($address, ':') ? 128 : 32;

    return (int) $prefix <= $maximum;
}));

return [
    'proxies' => $trustedProxies,
    'headers' => Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO,
];
