<?php

declare(strict_types=1);

namespace App\Services\Finance;

/**
 * Offline envelope subset of private WinX S0 NovaWithdrawalCallback.php.
 * Source SHA256: 641cc13040cd3f53d22fae044e95c3825270f5a417a9af624a260dbfa19ea8ce.
 * No hosted engine, framework bootstrap, financial transition or authorization.
 */
final class NovaWithdrawalEnvelopeVerifier
{
    private const FIELDS = [
        'partner_id', 'site_id', 'provider', 'type', 'transactionId',
        'provider_transaction_id', 'amount', 'currency', 'status',
    ];

    /**
     * Binding MUST come from the trusted caller, never from the callback body.
     * Required: positive integer partner_id/site_id and string secret (>=32 bytes).
     * Optional transactionId/provider_transaction_id pin already trusted references.
     * Unknown/malformed binding keys fail closed; there is no secret fallback.
     *
     * Freshness follows S0's symmetric window, clamped to 1..300 seconds.
     * This stateless check cannot detect repeats within that window. Further DB
     * proof is required for unique provider references, immutable request/owner/
     * wallet/asset/debit, dispatch, replay, locking and atomic economic transitions.
     * Even a signed SUCCESS is only a candidate observation, with no finality.
     *
     * @return array{envelope_verified: bool, observation: ?array, reason_code: string, db_proof_required: bool}
     */
    public function verify(
        string $raw,
        string $timestamp,
        string $signature,
        mixed $trustedBinding,
        int $maxAgeSeconds,
        int $now,
    ): array {
        if (!is_array($trustedBinding)
            || array_diff(array_keys($trustedBinding), ['partner_id', 'site_id', 'secret', 'transactionId', 'provider_transaction_id']) !== []
            || !is_int($trustedBinding['partner_id'] ?? null) || $trustedBinding['partner_id'] <= 0
            || !is_int($trustedBinding['site_id'] ?? null) || $trustedBinding['site_id'] <= 0
            || !is_string($trustedBinding['secret'] ?? null) || strlen($trustedBinding['secret']) < 32) {
            return $this->result(false, null, 'invalid_trusted_binding');
        }
        foreach (['transactionId', 'provider_transaction_id'] as $key) {
            if (array_key_exists($key, $trustedBinding) && !self::boundedString($trustedBinding[$key])) {
                return $this->result(false, null, 'invalid_trusted_binding');
            }
        }
        if (strlen($raw) > 4096 || !preg_match('/^[0-9]{10}$/D', $timestamp)
            || !preg_match('/^[a-f0-9]{64}$/D', $signature)) {
            return $this->result(false, null, 'authentication_required');
        }
        if (!hash_equals(hash_hmac('sha256', $timestamp . '.' . $raw, $trustedBinding['secret']), $signature)) {
            return $this->result(false, null, 'authentication_failed');
        }
        // Bound the injected clock before subtraction; never read the system clock.
        if ($now < 0 || $now > 9999999999
            || abs($now - (int) $timestamp) > min(300, max(1, $maxAgeSeconds))) {
            return $this->result(false, null, 'timestamp_expired');
        }
        try {
            $body = json_decode($raw, false, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->result(false, null, 'invalid_envelope');
        }
        if (!$body instanceof \stdClass) {
            return $this->result(false, null, 'invalid_envelope');
        }
        $body = get_object_vars($body);
        if (count($body) !== count(self::FIELDS) || array_diff(self::FIELDS, array_keys($body)) !== []) {
            return $this->result(false, null, 'invalid_envelope');
        }
        foreach (['transactionId', 'provider_transaction_id', 'amount', 'currency', 'status'] as $key) {
            if (!self::boundedString($body[$key])) {
                return $this->result(false, null, 'invalid_envelope');
            }
        }
        if (!is_int($body['partner_id']) || $body['partner_id'] !== $trustedBinding['partner_id']
            || !is_int($body['site_id']) || $body['site_id'] !== $trustedBinding['site_id']
            || $body['provider'] !== 'novagateway' || $body['type'] !== 'withdraw'
            || !in_array($body['status'], ['SUCCESS', 'FAIL', 'CANCELLED'], true)) {
            return $this->result(false, null, 'identity_or_status_mismatch');
        }
        // All values are now scalars. Count JSON key tokens too: json_decode alone
        // would silently accept duplicate keys, including Unicode-escaped aliases.
        if (preg_match_all('/"(?:[^"\\\\]|\\\\.)*"\s*:/s', $raw) !== count(self::FIELDS)) {
            return $this->result(false, null, 'invalid_envelope');
        }
        $amount = self::decimal($body['amount']);
        if ($amount === null) {
            return $this->result(false, null, 'invalid_amount');
        }
        foreach (['transactionId', 'provider_transaction_id'] as $key) {
            if (array_key_exists($key, $trustedBinding) && !hash_equals($trustedBinding[$key], $body[$key])) {
                return $this->result(false, null, 'reference_mismatch');
            }
        }
        return $this->result(true, [
            'kind' => 'candidate',
            'partner_id' => $body['partner_id'],
            'site_id' => $body['site_id'],
            'provider' => 'novagateway',
            'type' => 'withdraw',
            'transactionId' => $body['transactionId'],
            'provider_transaction_id' => $body['provider_transaction_id'],
            'amount' => $amount,
            'currency' => $body['currency'],
            'status' => $body['status'],
            'timestamp' => (int) $timestamp,
        ], 'candidate');
    }

    private static function boundedString(mixed $value): bool
    {
        return is_string($value) && $value !== '' && trim($value) !== '' && strlen($value) <= 128
            && !preg_match('/[\x00-\x1f\x7f]/', $value);
    }

    private static function decimal(string $value): ?string
    {
        if (!preg_match('/^[0-9]{1,16}(?:\.[0-9]{1,8})?$/D', $value)) {
            return null;
        }
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $normalized = (ltrim($whole, '0') ?: '0') . '.' . str_pad($fraction, 8, '0');
        return $normalized === '0.00000000' ? null : $normalized;
    }

    private function result(bool $verified, ?array $observation, string $reason): array
    {
        // Fixed reasons only: no raw body, signature, binding or secret in output.
        return [
            'envelope_verified' => $verified,
            'observation' => $observation,
            'reason_code' => $reason,
            'db_proof_required' => true,
        ];
    }
}
