<?php

namespace App\Betting\Exceptions;

class SelectionNotActiveException extends BettingDomainException
{
    protected $errorCode = 'SELECTION_NOT_ACTIVE';
    protected $httpStatus = 409;

    public function __construct(string $offerStatus)
    {
        $this->details = [
            'offer_status' => $offerStatus,
        ];

        parent::__construct('Selected quote selection is not active. Current offer status: [' . $offerStatus . '].');
    }
}
