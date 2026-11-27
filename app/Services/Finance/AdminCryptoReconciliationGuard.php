<?php

namespace App\Services\Finance;

use App\Models\CmsUserTransaction;

final class AdminCryptoReconciliationGuard
{
    public function assertReconciled(CmsUserTransaction $transaction, array $intent): void
    {
        // Stored economics and request-supplied observations cannot authenticate
        // a new on-chain receipt. Until an independent authority exists, neither
        // generic status acknowledgement nor economic adjustment is admitted.
        throw new \DomainException('Crypto deposits cannot use generic finance overrides. Independent authenticated recovery proof is unavailable.');
    }

    /** Parse untrusted legacy intent data for fingerprinting; this never authenticates proof. */
    public function normalizeEvidence(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }
        if (!is_array($value)) {
            throw new \DomainException('Settlement evidence must be a structured provider observation.');
        }
        $strings = ['transaction_id', 'session_id', 'provider', 'provider_reference', 'asset', 'network',
            'status', 'provider_mode', 'network_mode', 'received_amount_crypto', 'pay_address', 'tx_hash'];
        $allowed = [...$strings, 'precision', 'required_confirmations', 'confirmations', 'provenance', 'payment_outputs',
            'fiat_rate', 'minimum_fiat', 'under_minimum', 'manual_approved'];
        if (array_diff(array_keys($value), $allowed) !== []) {
            throw new \DomainException('Unsupported settlement evidence field.');
        }
        foreach ($strings as $key) {
            if (!is_string($value[$key] ?? null) || trim($value[$key]) !== $value[$key] || $value[$key] === ''
                || strlen($value[$key]) > 120 || preg_match('/[\x00-\x1f\x7f]/', $value[$key])) {
                throw new \DomainException('Settlement evidence requires explicit string fields: ' . $key);
            }
        }
        foreach (['precision', 'required_confirmations', 'confirmations'] as $key) {
            if (!is_int($value[$key] ?? null) || $value[$key] < 0) {
                throw new \DomainException('Settlement evidence requires integer fields: ' . $key);
            }
        }
        foreach (['received_amount_crypto', 'fiat_rate', 'minimum_fiat'] as $key) {
            if (array_key_exists($key, $value) && (!is_string($value[$key])
                || preg_match('/\A\d{1,32}(?:\.\d{1,32})?\z/', $value[$key]) !== 1)) {
                throw new \DomainException('Settlement evidence requires exact plain decimal strings.');
            }
        }
        foreach (['under_minimum', 'manual_approved'] as $key) {
            if (array_key_exists($key, $value) && !is_bool($value[$key])) {
                throw new \DomainException('Settlement evidence flags must be booleans.');
            }
        }
        $provenance = $value['provenance'] ?? null;
        $provenanceKeys = ['address_source', 'network_source', 'provider_reference', 'rail_key', 'network_mode'];
        if (!is_array($provenance) || count($provenance) !== count($provenanceKeys)
            || array_diff(array_keys($provenance), $provenanceKeys) !== []) {
            throw new \DomainException('Settlement evidence requires complete provider provenance.');
        }
        foreach ($provenance as $item) {
            if (!is_string($item) || $item === '' || strlen($item) > 120) {
                throw new \DomainException('Invalid settlement provenance field.');
            }
        }
        $outputs = $value['payment_outputs'] ?? null;
        if (!is_array($outputs) || array_keys($outputs) !== [0] || !is_array($outputs[0])) {
            throw new \DomainException('Settlement evidence requires one fully identified payment output.');
        }
        $keys = match ($outputs[0]['kind'] ?? null) {
            'tx_hash_address_amount' => ['kind', 'tx_hash', 'address', 'amount'],
            'utxo_vout' => ['kind', 'tx_hash', 'address', 'amount', 'output_index'],
            'trc20_transfer' => ['kind', 'tx_hash', 'address', 'amount', 'event_index', 'contract'],
            default => [],
        };
        if ($keys === [] || count($outputs[0]) !== count($keys)
            || array_diff(array_keys($outputs[0]), $keys) !== []) {
            throw new \DomainException('Settlement evidence requires one fully identified payment output.');
        }
        foreach ($outputs[0] as $key => $item) {
            if (in_array($key, ['output_index', 'event_index'], true)) {
                if (!is_int($item) || $item < 0 || $item > 4294967295) {
                    throw new \DomainException('Invalid settlement output index.');
                }
            } elseif (!is_string($item) || $item === '' || strlen($item) > 120) {
                throw new \DomainException('Invalid settlement output field.');
            }
        }
        if (preg_match('/\A\d{1,16}(?:\.\d{1,8})?\z/', $outputs[0]['amount']) !== 1) {
            throw new \DomainException('Settlement output requires an exact plain decimal amount.');
        }
        // Object key order is not part of operation identity.
        ksort($value['provenance']);
        ksort($value['payment_outputs'][0]);
        ksort($value);
        return $value;
    }
}
