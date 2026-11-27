<?php

declare(strict_types=1);

namespace App\Services\Finance;

use DomainException;

/**
 * Pure intent hashing only: no ownership, method readiness or payout authorization.
 * The caller supplies trusted server scope, externally approved/canonical beneficiary
 * strings, a stable original client request key and a private random HMAC secret.
 * Keep the secret stable for comparisons; rotation changes fingerprints.
 * Atomic persisted idempotency still requires a scoped DB unique key, transaction
 * and race/conflicting-replay checks. This helper provides none of that proof.
 */
final class WithdrawalIntentFingerprint
{
    private const KEYS = [
        'tenant_namespace', 'member_id', 'source_wallet_id', 'asset',
        'requested_currency', 'amount', 'beneficiary_name', 'beneficiary_target',
        'method_id', 'request_key', 'hmac_secret',
    ];

    /**
     * Exact keys: tenant_namespace, member_id, source_wallet_id, asset,
     * requested_currency, amount, beneficiary_name, beneficiary_target,
     * method_id, request_key, hmac_secret. IDs are positive PHP integers;
     * all other values are strings. method_id is an opaque provider/method
     * identifier, never readiness evidence. Amount accepts plain integer or
     * 1-2 fractional digits, no leading zeros, at most 16 integer digits.
     * Only amount is normalized; all other strings retain their exact bytes.
     *
     * @return array{fingerprint: string, persistence_proof_required: true, customer_payout_allowed: false}
     * @throws DomainException Generic messages deliberately exclude input/PII.
     */
    public function generate(mixed $input): array
    {
        if (!is_array($input)
            || count($input) !== count(self::KEYS)
            || array_diff(self::KEYS, array_keys($input)) !== []
            || array_diff(array_keys($input), self::KEYS) !== []) {
            throw new DomainException('Invalid withdrawal intent fields.');
        }

        foreach (['member_id', 'source_wallet_id'] as $key) {
            if (!is_int($input[$key]) || $input[$key] <= 0) {
                throw new DomainException('Invalid withdrawal intent identity.');
            }
        }
        foreach (self::KEYS as $key) {
            if (in_array($key, ['member_id', 'source_wallet_id', 'hmac_secret'], true)) {
                continue;
            }
            $limit = match ($key) {
                'beneficiary_name' => 512,
                'beneficiary_target', 'tenant_namespace', 'method_id', 'request_key' => 128,
                'amount' => 32,
                default => 8,
            };
            if (!is_string($input[$key]) || strlen($input[$key]) > $limit
                || trim($input[$key]) === '' || preg_match('//u', $input[$key]) !== 1
                || preg_match('/[\p{Cc}\p{Cf}\p{Zl}\p{Zp}]/u', $input[$key]) === 1) {
                throw new DomainException('Invalid withdrawal intent string.');
            }
        }
        if (!is_string($input['hmac_secret']) || strlen($input['hmac_secret']) < 32
            || strlen($input['hmac_secret']) > 256) {
            throw new DomainException('Invalid withdrawal intent secret.');
        }
        if (!in_array($input['asset'], ['TRY', 'USD', 'EUR'], true)
            || $input['requested_currency'] !== $input['asset']) {
            throw new DomainException('Invalid withdrawal intent currency.');
        }
        if (preg_match('/\A(0|[1-9][0-9]{0,15})(?:\.([0-9]{1,2}))?\z/', $input['amount'], $parts) !== 1) {
            throw new DomainException('Invalid withdrawal intent amount.');
        }
        $amount = $parts[1] . '.' . str_pad($parts[2] ?? '', 2, '0');
        if ($amount === '0.00') {
            throw new DomainException('Invalid withdrawal intent amount.');
        }

        // Ordered JSON prevents delimiter ambiguity; never serialize the secret.
        $payload = [
            'version' => 'rise.withdrawal-intent.v1',
            'tenant_namespace' => $input['tenant_namespace'],
            'member_id' => $input['member_id'],
            'source_wallet_id' => $input['source_wallet_id'],
            'asset' => $input['asset'],
            'requested_currency' => $input['requested_currency'],
            'amount' => $amount,
            'beneficiary_name' => $input['beneficiary_name'],
            'beneficiary_target' => $input['beneficiary_target'],
            'method_id' => $input['method_id'],
            'request_key' => $input['request_key'],
        ];

        return [
            'fingerprint' => hash_hmac('sha256', json_encode($payload, JSON_THROW_ON_ERROR), $input['hmac_secret']),
            'persistence_proof_required' => true,
            'customer_payout_allowed' => false,
        ];
    }
}
