<?php

namespace App\Betting\Exceptions;

class FrontBettingDisabledException extends BettingDomainException
{
    protected $errorCode = 'FRONT_BETTING_DISABLED';
    protected $httpStatus = 409;

    public function __construct(int $eventId, ?string $eventKey = null)
    {
        $this->details = [
            'betting_event_id' => $eventId,
            'event_key' => $eventKey,
            'front_betting_enabled' => false,
        ];

        parent::__construct('Betting is currently closed for this event on the front surface.');
    }
}
