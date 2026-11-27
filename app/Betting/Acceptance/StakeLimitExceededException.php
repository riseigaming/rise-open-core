<?php

namespace App\Betting\Acceptance;

use App\Betting\Exceptions\BettingDomainException;

class StakeLimitExceededException extends BettingDomainException
{
    protected $errorCode = 'STAKE_LIMIT_EXCEEDED';
    protected $httpStatus = 422;

    public function __construct(string $currency, float $stakeAmount, float $maxStake)
    {
        $currency = strtoupper(trim($currency));

        $this->details = [
            'currency' => $currency,
            'stake_amount' => round($stakeAmount, 2),
            'max_stake' => round($maxStake, 2),
            'max_stake_formatted' => StakeLimitPolicy::formatMoney($maxStake, $currency),
        ];

        parent::__construct(sprintf(
            'Bu cüzdan için tek kupon tutarı en fazla %s. Tutarı düşürüp tekrar deneyin.',
            $this->details['max_stake_formatted']
        ));
    }
}
