<?php

namespace App\Betting\Exceptions;

class QuoteSelectionMismatchException extends BettingDomainException
{
    protected $errorCode = 'QUOTE_SELECTION_MISMATCH';
    protected $httpStatus = 409;

    public function __construct(int $quoteVersionId, int $quoteSelectionId)
    {
        $this->details = [
            'quote_version_id' => $quoteVersionId,
            'quote_selection_id' => $quoteSelectionId,
        ];

        parent::__construct('Selected quote selection does not belong to the requested quote version.');
    }
}
