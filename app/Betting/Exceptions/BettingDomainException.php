<?php

namespace App\Betting\Exceptions;

use RuntimeException;

abstract class BettingDomainException extends RuntimeException
{
    protected $errorCode = 'BETTING_DOMAIN_ERROR';
    protected $httpStatus = 422;
    protected $details = [];

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }

    public function details(): array
    {
        return $this->details;
    }
}
