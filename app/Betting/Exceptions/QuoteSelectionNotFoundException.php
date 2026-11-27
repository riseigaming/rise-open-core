<?php

namespace App\Betting\Exceptions;

class QuoteSelectionNotFoundException extends BettingDomainException
{
    protected $errorCode = 'QUOTE_SELECTION_NOT_FOUND';
    protected $httpStatus = 404;

    public function __construct()
    {
        parent::__construct('Selected quote selection was not found.');
    }
}
