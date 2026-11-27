<?php

namespace App\Http\Middleware;

use App\Enum\ChessWalletReasonCodeEnum;
use App\Services\ChessWallet\ChessWalletProviderResolver;
use App\Services\ChessWallet\ChessWalletSignature;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;

class ChessWalletAuthenticate
{
    public function __construct(
        private ChessWalletProviderResolver $providerResolver,
        private ChessWalletSignature $signature
    ) {
    }

    public function handle(Request $request, Closure $next)
    {
        $partnerCode = trim((string) $request->header('x-partner-code', ''));
        $requestId = trim((string) $request->header('x-request-id', ''));
        $timestamp = trim((string) $request->header('x-timestamp', ''));
        $signature = trim((string) $request->header('x-signature', ''));
        $idempotencyKey = trim((string) $request->header('idempotency-key', ''));

        if ($partnerCode === '' || $requestId === '' || $timestamp === '' || $signature === '') {
            return $this->reject('Missing wallet authentication headers.');
        }

        if (!in_array(strtoupper($request->method()), ['GET'], true) && $idempotencyKey === '') {
            return $this->reject('Missing idempotency key header.');
        }

        $context = $this->providerResolver->resolvePartnerContext($partnerCode);
        if (!is_array($context)) {
            return $this->reject('Unknown chess wallet partner.');
        }

        $secret = trim((string) data_get($context, 'wallet_config.secret', ''));
        if ($secret === '') {
            return $this->reject('Partner secret is not configured.');
        }

        if (!ctype_digit($timestamp)) {
            return $this->reject('Invalid request timestamp.');
        }

        $requestTime = Carbon::createFromTimestampUTC((int) $timestamp);
        $drift = abs(now('UTC')->diffInSeconds($requestTime, false));
        $tolerance = (int) data_get($context, 'wallet_config.timestamp_tolerance_seconds', config('chess_wallet.timestamp_tolerance_seconds', 300));

        if ($drift > $tolerance) {
            return $this->reject('Timestamp drift exceeded.');
        }

        $canonicalPayload = $this->signature->buildCanonicalPayload(
            $request,
            $partnerCode,
            $requestId,
            $timestamp,
            $idempotencyKey
        );

        $expectedSignature = $this->signature->sign($canonicalPayload, $secret);
        if (!hash_equals($expectedSignature, $signature)) {
            return $this->reject('Signature verification failed.');
        }

        $request->attributes->set('chess_wallet_context', $context);
        $request->attributes->set('chess_wallet_partner_code', $partnerCode);
        $request->attributes->set('chess_wallet_request_id', $requestId);
        $request->attributes->set('chess_wallet_idempotency_key', $idempotencyKey);

        return $next($request);
    }

    private function reject(string $message)
    {
        return response()->json([
            'status' => 'rejected',
            'reasonCode' => ChessWalletReasonCodeEnum::INVALID_REQUEST,
            'message' => $message,
        ], 401);
    }
}
