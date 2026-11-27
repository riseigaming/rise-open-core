<?php

namespace App\Betting\Exceptions;

use RuntimeException;

class PublicSelectionTokenException extends RuntimeException
{
    private $reasonCode;
    private $httpStatus;

    public function __construct(string $reasonCode, string $message, int $httpStatus = 422)
    {
        $this->reasonCode = $reasonCode;
        $this->httpStatus = $httpStatus;

        parent::__construct($message);
    }

    public function reasonCode(): string
    {
        return $this->reasonCode;
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }
}
