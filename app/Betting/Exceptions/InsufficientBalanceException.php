<?php

namespace App\Betting\Exceptions;

class InsufficientBalanceException extends BettingDomainException
{
    protected $errorCode = 'INSUFFICIENT_BALANCE';
    protected $httpStatus = 409;

    public function __construct(float $currentBalance, float $requestedStake, string $currency = 'TRY')
    {
        parent::__construct('Insufficient balance. Increase your balance or lower the stake amount.');

        $this->details = [
            'current_balance' => round($currentBalance, 2),
            'requested_stake' => round($requestedStake, 2),
            'currency' => $currency,
        ];
    }
}
