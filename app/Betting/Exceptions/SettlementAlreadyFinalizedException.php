<?php

namespace App\Betting\Exceptions;

class SettlementAlreadyFinalizedException extends BettingDomainException
{
    protected $errorCode = 'SETTLEMENT_ALREADY_FINALIZED';
    protected $httpStatus = 409;

    public function __construct(string $status, string $decisionSource)
    {
        $this->details = [
            'status' => $status,
            'decision_source' => $decisionSource,
        ];

        parent::__construct(
            'Settlement review already finalized. Current status: [' . $status . '], decision source: [' . $decisionSource . '].'
        );
    }
}
