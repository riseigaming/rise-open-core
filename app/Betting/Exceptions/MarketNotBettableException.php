<?php

namespace App\Betting\Exceptions;

class MarketNotBettableException extends BettingDomainException
{
    protected $errorCode = 'MARKET_NOT_BETTABLE';
    protected $httpStatus = 409;

    public function __construct(
        string $marketType,
        ?string $providerKey,
        ?string $providerMarketKey,
        string $reasonCode
    ) {
        $this->details = [
            'market_type' => $marketType,
            'provider_key' => $providerKey,
            'provider_market_key' => $providerMarketKey,
            'reason_code' => $reasonCode,
        ];

        parent::__construct('Selected market is currently not bettable. Reason: [' . $reasonCode . '].');
    }
}
