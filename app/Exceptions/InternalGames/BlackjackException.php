<?php

namespace App\Exceptions\InternalGames;

use RuntimeException;

class BlackjackException extends RuntimeException
{
    public function __construct(
        private int $statusCode,
        private string $errorCode,
        string $message
    ) {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }
}
