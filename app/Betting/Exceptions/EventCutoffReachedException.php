<?php

namespace App\Betting\Exceptions;

use Carbon\CarbonInterface;

class EventCutoffReachedException extends BettingDomainException
{
    protected $errorCode = 'EVENT_CUTOFF_REACHED';
    protected $httpStatus = 409;

    public function __construct(CarbonInterface $startsAt, int $cutoffSeconds)
    {
        $this->details = [
            'starts_at' => $startsAt->toIso8601String(),
            'cutoff_seconds_before_start' => $cutoffSeconds,
        ];

        parent::__construct(
            'Acceptance cutoff reached. Event starts at ' . $startsAt->toDateTimeString() . ' and cutoff is ' . $cutoffSeconds . ' seconds before start.'
        );
    }
}
