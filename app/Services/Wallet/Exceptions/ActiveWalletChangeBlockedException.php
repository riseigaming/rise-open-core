<?php

namespace App\Services\Wallet\Exceptions;

use RuntimeException;

class ActiveWalletChangeBlockedException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly array $reasons = []
    ) {
        parent::__construct($message);
    }

    public function reasons(): array
    {
        return $this->reasons;
    }
}
