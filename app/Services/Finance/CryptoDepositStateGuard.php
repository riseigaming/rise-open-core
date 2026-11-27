<?php

namespace App\Services\Finance;

use App\Models\CmsUserTransaction;
use App\Models\WalletLedgerEntry;
use App\Support\Crypto\CryptoAmount;
use App\Support\Crypto\CryptoRail;
use Brick\Math\BigDecimal;

final class CryptoDepositStateGuard
{
    public function assertCreditable(CmsUserTransaction $transaction, array $state): CryptoRail
    {
        if (!in_array(strtoupper((string) ($state['status'] ?? '')), ['CREDITED', 'COMPLETED'], true)) {
            $this->reject('provider_not_settled');
        }
        return $this->assertMatches($transaction, $state);
    }

    public function assertMatches(CmsUserTransaction $transaction, array $state): CryptoRail
    {
        $policy = app(CryptoSettlementRailPolicy::class);
        $expected = $policy->expectedRail($transaction);
        $observed = $policy->resolve((string) ($state['asset'] ?? ''), $state['network'] ?? '', $state['precision'] ?? null);
        if ($expected->key !== $observed->key) {
            $this->reject('rail_mismatch');
        }
        if ($expected->precision !== $observed->precision) {
            $this->reject('precision_mismatch');
        }
        $policy->assertEnabled($expected);
        $policy->assertProvider($expected, $state['provider'] ?? null);
        $boundProvider = $transaction->crypto_deposit_context['provider'] ?? null;
        if ($boundProvider !== null && $boundProvider !== str_replace('_like', '', strtolower((string) ($state['provider'] ?? '')))) {
            $this->reject('provider_mismatch');
        }
        if (!empty($state['transaction_id']) && !hash_equals((string) $transaction->transactionId, (string) $state['transaction_id'])) {
            $this->reject('transaction_binding_mismatch');
        }
        if (!empty($transaction->provider_transaction_id)
            && !empty($state['session_id'])
            && !hash_equals((string) $transaction->provider_transaction_id, (string) $state['session_id'])) {
            $this->reject('session_binding_mismatch');
        }
        foreach (['provider_mode', 'network_mode'] as $key) {
            $configured = trim((string) ($transaction->crypto_deposit_context[$key] ?? config('services.crypto_gateway.' . $key, '')));
            if ($configured !== '' && ($this->mode($configured, $key) === null
                || $this->mode($configured, $key) !== $this->mode((string) ($state[$key] ?? ''), $key))) {
                $this->reject($key . '_mismatch');
            }
            if (!empty($state[$key]) && $this->mode((string) $state[$key], $key) === null) {
                $this->reject($key . '_mismatch');
            }
        }
        if (in_array(strtoupper((string) ($state['status'] ?? '')), ['CREDITED', 'COMPLETED'], true)) {
            if ($transaction->crypto_deposit_context !== null && $this->mode((string) ($state['provider_mode'] ?? ''), 'provider_mode') === null) {
                $this->reject('provider_mode_mismatch');
            }
            foreach (['asset', 'network', 'transaction_id', 'session_id'] as $required) {
                if (!is_string($state[$required] ?? null) || trim($state[$required]) === '') {
                    $this->reject('transaction_binding_mismatch');
                }
            }
            try {
                if (!isset($state['received_amount_crypto'])) {
                    $this->reject('invalid_amount');
                }
                $received = BigDecimal::of(CryptoAmount::creditAmount($state));
            } catch (\InvalidArgumentException $exception) {
                $this->reject('precision_mismatch');
            }

            $minimum = $transaction->crypto_deposit_context['minimum_fiat'] ?? null;
            if ($minimum !== null) {
                $rate = $state['fiat_rate'] ?? null;
                if (!is_string($rate) || !preg_match('/\A\d{1,32}(?:\.\d{1,32})?\z/', $rate)
                    || !BigDecimal::of($rate)->isPositive()
                    || ($state['minimum_fiat'] ?? null) !== $minimum) {
                    $this->reject('locked_quote_mismatch');
                }
                $underMinimum = $received->multipliedBy($rate)->isLessThan($minimum);
                if (($state['under_minimum'] ?? null) !== $underMinimum) {
                    $this->reject('minimum_classification_mismatch');
                }
                if ($underMinimum && ($state['manual_approved'] ?? false) !== true
                    && !(($state['ltc_auto_partial_allowed'] ?? null) === true
                        && app(CryptoLtcTopUpPolicy::class)->eligible($transaction, $state))) {
                    $this->reject('under_minimum_manual_review');
                }
            }

            $credited = WalletLedgerEntry::query()
                ->where('idempotency_key', 'crypto_deposit_credited:' . (string) $transaction->transactionId)
                ->first();
            if (!$credited && !$received->isPositive()) $this->reject('invalid_amount');
            foreach (['session_id', 'provider_reference', 'provider'] as $key) {
                $bound = data_get($credited?->meta, $key);
                if (is_string($bound) && $bound !== '' && $bound !== ($state[$key] ?? null)) {
                    $this->reject('provider_reference_mismatch');
                }
            }
            if ($credited) {
                $comparison = $received->compareTo($credited->amount);
                if ($comparison < 0) $this->reject('received_total_regressed');
                if ($comparison > 0) $this->reject('cumulative_receipt_evidence_required');
            }
            // Existing legacy credits may replay as no-ops, but a NEW credit
            // requires the exact output/event index, never invoice tx_hashes alone.
            if (!$credited && !in_array($state['payment_outputs'][0]['kind'] ?? null, ['utxo_vout', 'trc20_transfer'], true)) {
                $this->reject('receipt_index_required');
            }
            // Preserve a pre-claims BTC ledger replay as a no-op, never as new ownership.
            if (!$credited || !empty($credited->meta['provenance'])) {
                $required = filter_var($state['required_confirmations'] ?? null, FILTER_VALIDATE_INT);
                $observedConfirmations = filter_var($state['confirmations'] ?? null, FILTER_VALIDATE_INT);
                if ($required === false || $required < 1 || $observedConfirmations === false || $observedConfirmations < $required) {
                    $this->reject('confirmations_required');
                }
                app(CryptoSettlementClaimService::class)->candidate($transaction, $state);
            }
        }
        return $expected;
    }

    private function reject(string $reason): never
    {
        throw new CryptoSettlementRejected($reason);
    }

    private function mode(string $mode, string $key): ?string
    {
        $mode = strtolower(trim($mode));
        $aliases = $key === 'provider_mode'
            ? ['live' => 'live', 'live_http' => 'live', 'production' => 'live', 'prod' => 'live', 'test' => 'test', 'sandbox' => 'test', 'staging' => 'test', 'fake' => 'test']
            : ['mainnet' => 'mainnet', 'production' => 'mainnet', 'prod' => 'mainnet', 'live' => 'mainnet', 'testnet' => 'testnet', 'test' => 'testnet', 'regtest' => 'regtest'];

        return $aliases[$mode] ?? null;
    }
}
