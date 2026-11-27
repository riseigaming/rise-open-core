<?php

namespace App\Betting\Exceptions;

class StalePriceException extends BettingDomainException
{
    protected $errorCode = 'STALE_PRICE';
    protected $httpStatus = 409;

    public function __construct(float $requestedPrice, float $currentPrice)
    {
        $this->details = [
            'requested_price' => $requestedPrice,
            'current_price' => $currentPrice,
        ];

        parent::__construct(
            'Stale price. Requested price [' . $requestedPrice . '] no longer matches current internal quote [' . $currentPrice . '].'
        );
    }
}
