<?php

namespace App\Betting\Exceptions;

class QuoteContextMissingException extends BettingDomainException
{
    protected $errorCode = 'QUOTE_CONTEXT_MISSING';
    protected $httpStatus = 422;
}
