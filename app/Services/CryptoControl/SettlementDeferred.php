<?php

namespace App\Services\CryptoControl;

use Symfony\Component\HttpKernel\Exception\HttpException;

final class SettlementDeferred extends HttpException
{
    public function __construct(public readonly string $reason = 'settlement_maintenance')
    {
        parent::__construct(503, 'Crypto settlement is temporarily deferred.', null, ['Retry-After' => '5']);
    }
}
