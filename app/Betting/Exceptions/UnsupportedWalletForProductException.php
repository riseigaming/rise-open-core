<?php

namespace App\Betting\Exceptions;

class UnsupportedWalletForProductException extends BettingDomainException
{
    protected $errorCode = 'UNSUPPORTED_ACTIVE_WALLET';
    protected $httpStatus = 422;

    public function __construct(string $message, array $details = [])
    {
        parent::__construct($message);
        $this->details = $details;
    }
}
