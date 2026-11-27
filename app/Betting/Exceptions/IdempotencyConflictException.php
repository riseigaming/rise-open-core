<?php

namespace App\Betting\Exceptions;

class IdempotencyConflictException extends BettingDomainException
{
    protected $errorCode = 'IDEMPOTENCY_CONFLICT';
    protected $httpStatus = 409;

    public function __construct(?string $idempotencyKey = null)
    {
        $this->details = [
            'idempotency_key' => $idempotencyKey,
        ];

        parent::__construct('Idempotency key conflict. The same key was already used with a different acceptance payload.');
    }
}
