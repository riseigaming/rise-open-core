<?php

namespace App\Services\Finance;

use Illuminate\Validation\ValidationException;

final class CryptoSettlementRejected extends ValidationException
{
    public function __construct(public readonly string $reasonCode)
    {
        parent::__construct(validator([], []));
        $this->validator->errors()->add('crypto', FinanceCustomerMessage::forCode('crypto_session_rejected'));
        $this->message = FinanceCustomerMessage::forCode('crypto_session_rejected');
    }
}
