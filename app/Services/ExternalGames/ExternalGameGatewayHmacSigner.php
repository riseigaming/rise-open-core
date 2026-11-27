<?php

namespace App\Services\ExternalGames;

use Illuminate\Support\Str;

class ExternalGameGatewayHmacSigner
{
    public function headers(string $method, string $url, string $rawBody, array $config): array
    {
        $secret = trim((string) ($config['gateway_hmac_secret'] ?? ''));
        if ($secret === '') {
            return [];
        }

        $timestamp = (string) time();
        $nonce = (string) Str::uuid();
        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        $bodyHash = hash('sha256', $rawBody);
        $canonical = strtoupper($method) . "\n" . $path . "\n" . $timestamp . "\n" . $nonce . "\n" . $bodyHash;

        return [
            'X-Gateway-Timestamp' => $timestamp,
            'X-Gateway-Nonce' => $nonce,
            'X-Gateway-Signature' => hash_hmac('sha256', $canonical, $secret),
        ];
    }
}
