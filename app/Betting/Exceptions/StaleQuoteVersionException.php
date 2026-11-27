<?php

namespace App\Betting\Exceptions;

class StaleQuoteVersionException extends BettingDomainException
{
    protected $errorCode = 'STALE_QUOTE_VERSION';
    protected $httpStatus = 409;

    public function __construct(int $requestedQuoteVersionId)
    {
        $this->details = [
            'quote_version_id' => $requestedQuoteVersionId,
        ];

        parent::__construct(
            'Stale quote version. Requested quote version #' . $requestedQuoteVersionId . ' is no longer current for this event.'
        );
    }
}
