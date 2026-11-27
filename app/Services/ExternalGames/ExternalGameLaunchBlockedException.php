<?php

namespace App\Services\ExternalGames;

use RuntimeException;

class ExternalGameLaunchBlockedException extends RuntimeException
{
    public function __construct(
        public readonly string $reasonCode,
        string $message,
        public readonly array $details = []
    ) {
        parent::__construct($message);
    }
}
