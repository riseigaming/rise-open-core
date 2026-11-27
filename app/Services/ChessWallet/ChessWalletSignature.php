<?php

namespace App\Services\ChessWallet;

use Illuminate\Http\Request;

class ChessWalletSignature
{
    public function buildCanonicalPayload(Request $request, string $partnerCode, string $requestId, string $timestamp, string $idempotencyKey): string
    {
        return implode("\n", [
            strtoupper($request->method()),
            '/' . ltrim($request->path(), '/'),
            $partnerCode,
            $requestId,
            $idempotencyKey,
            $timestamp,
            hash('sha256', (string) $request->getContent()),
        ]);
    }

    public function sign(string $canonicalPayload, string $secret): string
    {
        return hash_hmac('sha256', $canonicalPayload, $secret);
    }
}
