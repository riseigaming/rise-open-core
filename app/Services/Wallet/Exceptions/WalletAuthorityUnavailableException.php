<?php

namespace App\Services\Wallet\Exceptions;

use DomainException;
use Illuminate\Http\Request;

/** Keep unreviewed money fail-closed without turning an expected hold into a raw 500. */
final class WalletAuthorityUnavailableException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Primary wallet requires reviewed authority reconciliation.');
    }

    public function render(Request $request)
    {
        $headers = ['Cache-Control' => 'no-store, private', 'Retry-After' => '60'];

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 503,
                'code' => 'WALLET_AUTHORITY_UNAVAILABLE',
                'message' => trans('wallet_availability.message'),
            ], 503, $headers);
        }

        return response()->view('errors.wallet-unavailable', [], 503, $headers);
    }
}
