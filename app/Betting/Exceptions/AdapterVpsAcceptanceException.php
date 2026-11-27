<?php

namespace App\Betting\Exceptions;

class AdapterVpsAcceptanceException extends BettingDomainException
{
    public function __construct(string $errorCode, string $message, int $httpStatus = 409, array $details = [])
    {
        $this->errorCode = $errorCode;
        $this->httpStatus = $httpStatus;
        $this->details = $details;

        parent::__construct($message);
    }
}
