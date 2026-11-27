<?php

namespace App\Betting\Exceptions;

class QuoteNotTradeableException extends BettingDomainException
{
    protected $errorCode = 'QUOTE_NOT_TRADEABLE';
    protected $httpStatus = 409;

    public function __construct(string $tradingState)
    {
        $this->details = [
            'trading_state' => $tradingState,
        ];

        parent::__construct('Current quote version is not tradeable. Trading state: [' . $tradingState . '].');
    }
}
