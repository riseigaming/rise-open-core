<?php

namespace App\Services\Finance;

use App\Models\CmsUserTransaction;
use App\Support\Crypto\CryptoAddressValidator;
use App\Support\Crypto\CryptoAmount;
use App\Support\Crypto\CryptoRail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CryptoSettlementClaimService
{
    public function acquire(CmsUserTransaction $transaction, array $state): void
    {
        app(\App\Services\CryptoControl\CryptoSettlementMaintenanceGate::class)->assertHeld();
        if (DB::transactionLevel() < 1) {
            throw new \LogicException('Settlement claims require the wallet credit transaction.');
        }
        app(CryptoDepositStateGuard::class)->assertCreditable($transaction, $state);
        // A new claim cannot establish global ownership while older credits lack claims.
        // Do not silently backfill unverifiable historical financial provenance.
        $unclaimedCredit = DB::table('wallet_ledger_entries as ledger')
            ->where('ledger.entry_type', 'crypto_deposit_credited')
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')->from('crypto_settlement_claims as claim')
                    ->join('cms_user_transactions as transaction', 'transaction.id', '=', 'claim.cms_transaction_id')
                    ->whereColumn('transaction.transactionId', 'ledger.source_reference');
            })->exists();
        if ($unclaimedCredit) {
            throw ValidationException::withMessages(['crypto' => 'Historical crypto output ownership requires reconciliation before new credits.']);
        }
        $candidate = $this->candidate($transaction, $state);
        // A legacy hash+address claim lacks vout identity. Do not silently
        // grant any indexed output from that transaction until reconciled.
        if ($candidate['output_kind'] !== 'tx_hash_address_amount'
            && DB::table('crypto_settlement_claims')->where('rail_key', $candidate['rail_key'])
                ->where('network_mode', $candidate['network_mode'])->where('tx_hash', $candidate['tx_hash'])
                ->where('output_kind', 'tx_hash_address_amount')->exists()) {
            $this->reject();
        }
        DB::table('crypto_settlement_claims')->insertOrIgnore($candidate + ['created_at' => now(), 'updated_at' => now()]);
        $claim = DB::table('crypto_settlement_claims')->where('output_fingerprint', $candidate['output_fingerprint'])->lockForUpdate()->first();
        if (!$claim || !$this->matches($claim, $candidate)) {
            $this->reject();
        }
    }

    /** Pure validation shared with historical audit; never acquires or credits. */
    public function candidate(CmsUserTransaction $transaction, array $state): array
    {
        $rail = CryptoRail::resolve((string) ($state['asset'] ?? ''), $state['network'] ?? null, $state['precision'] ?? null);
        $provenance = $state['provenance'] ?? [];
        $outputs = $state['payment_outputs'] ?? [];
        $mode = strtolower((string) ($state['network_mode'] ?? ''));
        if (($provenance['address_source'] ?? null) !== 'provider_recheck'
            || ($provenance['network_source'] ?? null) !== 'rail_plus_provider'
            || ($provenance['rail_key'] ?? null) !== $rail->key
            || ($provenance['network_mode'] ?? null) !== $mode
            || ($provenance['provider_reference'] ?? null) !== ($state['provider_reference'] ?? null)
            || !in_array($mode, ['mainnet', 'testnet', 'regtest'], true)
            || !is_array($outputs) || count($outputs) !== 1 || !isset($outputs[0])) {
            $this->reject();
        }
        $output = $outputs[0];
        $hash = strtolower((string) ($output['tx_hash'] ?? ''));
        $address = $output['address'] ?? '';
        $amount = CryptoAmount::creditAmount($state);
        if (!preg_match('/\A[0-9a-f]{64}\z/', $hash)
            || !is_string($address) || !app(CryptoAddressValidator::class)->isValid($address, $rail, $mode)
            || $address !== ($state['pay_address'] ?? null)
            || $hash !== strtolower((string) ($state['tx_hash'] ?? ''))
            || !is_string($output['amount'] ?? null)
            || CryptoAmount::normalize($output['amount'], $rail) !== $amount) {
            $this->reject();
        }
        foreach (['provider_reference', 'session_id'] as $key) {
            if (!is_string($state[$key] ?? null) || !preg_match('/\A[a-zA-Z0-9_-]{1,120}\z/', $state[$key])) {
                $this->reject();
            }
        }
        $provider = match ($state['provider'] ?? null) {
            'bitcart', 'bitcart_like' => 'bitcart',
            default => null,
        };
        if ($provider === null) {
            $this->reject();
        }
        // Invoice IDs and amounts cannot evade ownership of the same hash/address.
        // Bech32 is case insensitive; Base58 is not.
        $identityAddress = preg_match('/\A(?:bc1|tb1|bcrt1|ltc1|tltc1)/i', $address) ? strtolower($address) : $address;
        $kind = $output['kind'] ?? null;
        $index = null;
        $tokenContract = null;
        if (in_array($rail->asset, ['BTC', 'LTC'], true)) {
            if ($kind === 'utxo_vout') {
                $index = $output['output_index'] ?? null;
                if (array_key_exists('event_index', $output) || array_key_exists('contract', $output)) $this->reject();
            } elseif ($kind !== 'tx_hash_address_amount' || array_key_exists('output_index', $output)) {
                $this->reject();
            }
        } elseif ($rail->asset === 'USDT' && $rail->network === 'TRC20') {
            $index = $output['event_index'] ?? null;
            $tokenContract = CryptoRail::contractIdentity('USDT', 'TRC20')['token_contract'];
            if ($kind !== 'trc20_transfer' || ($output['contract'] ?? null) !== $tokenContract
                || array_key_exists('output_index', $output)) $this->reject();
        } else {
            $this->reject();
        }
        if ($kind !== 'tx_hash_address_amount' && (!is_int($index) || $index < 0 || $index > 4294967295)) {
            $this->reject();
        }
        // Provider and address are not part of the indexed on-chain receipt identity.
        // Preserve legacy fingerprint only for historical credited no-op replay.
        $fingerprint = $kind === 'tx_hash_address_amount'
            ? hash('sha256', json_encode([$provider, $rail->key, $mode, $hash, $identityAddress]))
            : hash('sha256', json_encode(['receipt-v2', $rail->addressFamily, $mode, $hash, $index, $tokenContract]));
        return [
            'output_fingerprint' => $fingerprint, 'cms_transaction_id' => $transaction->id,
            'provider' => $provider, 'provider_reference' => $state['provider_reference'], 'session_id' => $state['session_id'],
            'rail_key' => $rail->key, 'asset' => $rail->asset, 'network' => $rail->network, 'network_mode' => $mode,
            'tx_hash' => $hash, 'output_kind' => $output['kind'], 'address' => $identityAddress,
            'exact_amount' => $amount,
        ];
    }

    public function matches(object $claim, array $candidate): bool
    {
        foreach ($candidate as $key => $value) {
            if ($key === 'exact_amount') {
                if (!\Brick\Math\BigDecimal::of((string) $claim->$key)->isEqualTo($value)) {
                    return false;
                }
            } elseif ((string) ($claim->$key ?? '') !== (string) $value) {
                return false;
            }
        }
        return true;
    }

    /** Reconciliation verifies existing ownership; it never infers or inserts it. */
    public function assertOwned(CmsUserTransaction $transaction, array $state): void
    {
        app(\App\Services\CryptoControl\CryptoSettlementMaintenanceGate::class)->assertHeld();
        $candidate = $this->candidate($transaction, $state);
        $claims = DB::table('crypto_settlement_claims')
            ->where('output_fingerprint', $candidate['output_fingerprint'])
            ->orWhere('cms_transaction_id', $transaction->id)
            ->lockForUpdate()->get();
        if ($claims->count() !== 1 || !$this->matches($claims->first(), $candidate)) {
            $this->reject();
        }
    }

    private function reject(): never
    {
        throw ValidationException::withMessages(['crypto' => 'Missing authenticated payment provenance or duplicate external settlement ownership.']);
    }
}
