<?php

namespace App\Services\Wallet\Exceptions;

use RuntimeException;

class UnsupportedProductWalletException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly array $details = []
    ) {
        parent::__construct($message);
    }

    public function details(): array
    {
        return $this->details;
    }

    public function render($request)
    {
        return response()->json([
            'status' => 422, 'code' => 'UNSUPPORTED_ACTIVE_WALLET',
            'message' => $this->getMessage(), 'details' => $this->details,
        ], 422);
    }
}
