<?php

namespace App\Services\Payments;

use App\Enum\TransactionActionEnum;
use App\Enum\TransactionStatusEnum;
use App\Models\CmsUser;
use App\Models\CmsUserTransaction;
use App\Models\WalletLedgerEntry;
use App\Services\CryptoControl\CryptoSettlementMaintenanceGate;
use App\Services\Finance\CryptoSettlementRailPolicy;
use App\Services\Finance\PrelaunchFinanceWriteGate;
use App\Services\Wallet\AssetWalletService;
use App\Services\Wallet\CustomerCryptoRailPolicy;
use App\Support\Crypto\CryptoAddressValidator;
use App\Support\Crypto\CryptoRail;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class NovaDepositService
{
    public const EVENT = 'nova_crypto_deposit';

    public function __construct(
        private readonly NovaGatewayClient $gateway,
        private readonly AssetWalletService $wallets,
        private readonly CustomerCryptoRailPolicy $rails,
        private readonly CryptoSettlementRailPolicy $settlement,
        private readonly CryptoSettlementMaintenanceGate $maintenance,
        private readonly PrelaunchFinanceWriteGate $writes,
        private readonly CryptoAddressValidator $addresses,
    ) {
    }

    public function availableRail(CmsUser $customer): ?CryptoRail
    {
        $rail = (string) config('nova_payments.rail');

        return $this->gateway->sessionsAllowed() && $customer->role === 'customer' && !$customer->parent_id
            && in_array($customer->currency, ['TRY', 'USD', 'EUR'], true)
            && $customer->currency === config('nova_payments.fiat_currency')
            && $this->rails->permits($rail, 'deposit_enabled')
            ? $this->rails->rail($rail) : null;
    }

    public function deposits(): Builder
    {
        return CmsUserTransaction::query()
            ->where('event_type', self::EVENT)
            ->where('action', TransactionActionEnum::Deposit);
    }

    public function start(CmsUser $customer, string $key, string $fiat): CmsUserTransaction
    {
        $rail = $this->availableRail($customer);
        abort_unless($rail, 503, 'Nova deposits are unavailable for this account.');
        $this->writes->assertNovaSessionWritable();
        $fiat = (string) BigDecimal::of($fiat)->toScale(2);
        $reference = 'nova_' . hash_hmac('sha256', $customer->id . ':' . strtolower($key), (string) config('app.key'));

        $transaction = $this->maintenance->run('cms.nova.intent', $reference, function () use ($customer, $rail, $reference, $fiat, $key) {
            $this->writes->assertNovaSessionWritable();
            $customer = CmsUser::whereKey($customer->id)->lockForUpdate()->firstOrFail();
            abort_unless($this->availableRail($customer)?->key === $rail->key, 409);
            $existing = $this->deposits()->where('transactionId', $reference)->first();
            if ($existing) {
                abort_unless($existing->cms_user_id === $customer->id
                    && $existing->crypto_deposit_context['fiat_amount'] === $fiat, 409, 'Deposit request changed.');

                return $existing;
            }

            $wallet = $this->wallets->ensureNovaDepositWallet($customer, $rail);
            abort_unless($wallet?->status === 'active', 409, 'Deposit wallet is unavailable.');
            return CmsUserTransaction::create([
                'cms_user_id' => $customer->id, 'transactionId' => $reference,
                'event_type' => self::EVENT, 'action' => TransactionActionEnum::Deposit,
                'status' => TransactionStatusEnum::Pending, 'amount' => '0', 'currency' => $rail->asset,
                'transaction_asset' => $rail->asset, 'source_currency' => $rail->asset,
                'source_wallet_type' => $wallet->wallet_type, 'source_wallet_account_id' => $wallet->id,
                'crypto_deposit_context' => $this->settlement->creationContext($rail) + [
                    'fiat_amount' => $fiat, 'fiat_currency' => $customer->currency, 'request_key' => strtolower($key),
                    'tenant_binding' => $this->gateway->tenantBinding(),
                ],
            ]);
        });

        $context = $this->boundContext($transaction);
        if ($transaction->provider_transaction_id) {
            return $transaction;
        }
        $this->writes->assertNovaSessionWritable();
        // Repeat the same Nova transaction ID after an uncertain create. Nova
        // recovers its invoice without switching to a new local intent.
        abort_unless($this->maintenance->status()['new_sessions_allowed'], 503, 'Crypto settlement is paused.');
        $state = $this->gateway->createSession([
            'transactionId' => $reference, 'customerId' => $customer->id,
            'amount' => $context['fiat_amount'], 'fiatCurrency' => $context['fiat_currency'],
            'asset' => $context['asset'], 'network' => $context['network'],
            'providerMode' => $context['provider_mode'], 'networkMode' => $context['network_mode'],
        ]);

        return $this->record($transaction, $state, false);
    }

    public function recheck(CmsUserTransaction $transaction): CmsUserTransaction
    {
        if ($transaction->status === TransactionStatusEnum::Success) {
            return $transaction;
        }

        $this->boundContext($transaction);
        $state = $this->gateway->transactionStatus($transaction->transactionId);

        return $this->record($transaction, $state, true);
    }

    private function boundContext(CmsUserTransaction $transaction): array
    {
        $context = $transaction->crypto_deposit_context;
        abort_unless($this->gateway->configured()
            && hash_equals($context['tenant_binding'], $this->gateway->tenantBinding())
            && $context['provider_mode'] === config('nova_payments.provider_mode')
            && $context['network_mode'] === config('nova_payments.network_mode'), 503, 'Nova tenant is unavailable.');

        return $context;
    }

    private function record(CmsUserTransaction $transaction, array $state, bool $polled): CmsUserTransaction
    {
        return $this->maintenance->run('cms.nova.observe', $transaction->transactionId, function () use ($transaction, $state, $polled) {
            $locked = $this->deposits()->whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === TransactionStatusEnum::Success) {
                return $locked;
            }
            abort_unless($locked->status === TransactionStatusEnum::Pending, 409);
            $this->writes->assertNovaSessionWritable();
            $context = $this->boundContext($locked);
            $rail = $this->settlement->expectedRail($locked);
            $this->settlement->assertEnabled($rail);
            abort_unless($locked->user?->role === 'customer', 409);
            $wallet = $this->wallets->findWalletForOwner($locked->user, (int) $locked->source_wallet_account_id, true);
            abort_unless($wallet?->status === 'active' && $wallet->cms_user_id === $locked->cms_user_id
                && $wallet->asset === $rail->asset && $wallet->wallet_key === $rail->walletKey
                && $wallet->wallet_type === 'crypto_asset_wallet' && !$locked->user->parent_id, 409);
            $snapshot = $this->verifiedSnapshot($locked, $state, $context, $rail);
            if ($locked->provider_transaction_id && !$polled) {
                return $locked;
            }

            $locked->fill([
                'provider_transaction_id' => $state['session_id'],
                'execution_context_snapshot' => $snapshot,
            ])->save();

            if ($polled && $state['status'] === 'COMPLETED' && $this->gateway->creditAllowed()) {
                $this->settle($locked, $wallet->id, $state, $snapshot);
            }

            return $locked;
        });
    }

    private function verifiedSnapshot(CmsUserTransaction $transaction, array $state, array $context, CryptoRail $rail): array
    {
        $expected = array_intersect_key($context, array_flip(['asset', 'network', 'rail_key', 'precision',
            'provider', 'provider_mode', 'network_mode', 'fiat_currency']));
        $expected += [
            'transaction_id' => $transaction->transactionId, 'customer_id' => $transaction->cms_user_id,
            'contract_version' => 2, 'payment_rail' => 'CRYPTO', 'polling_supported' => true,
        ];
        foreach ($expected as $key => $value) {
            abort_unless(($state[$key] ?? null) === $value, 422, 'Nova payment identity mismatch.');
        }

        foreach (['session_id', 'provider_reference'] as $field) {
            abort_unless(is_string($state[$field] ?? null) && preg_match('/\A[A-Za-z0-9_-]{1,120}\z/', $state[$field]),
                422, 'Nova payment reference is invalid.');
        }
        $address = $state['pay_address'] ?? null;
        abort_unless(is_string($address) && $this->addresses->isValid($address, $rail, $context['network_mode']),
            422, 'Nova payment address is invalid.');

        $requested = $this->decimal($state['amount_crypto'] ?? null, $rail->precision);
        $rate = $this->decimal($state['fiat_rate'] ?? null, 32);
        $fiat = $this->decimal($state['fiat_reference_amount'] ?? null, 2);
        abort_unless($requested->isPositive() && $rate->isPositive() && $fiat->isEqualTo($context['fiat_amount'])
            && $requested->isEqualTo($fiat->dividedBy($rate, $rail->precision,
                $rail->asset === 'LTC' ? RoundingMode::CEILING : RoundingMode::HALF_UP))
            && $requested->isEqualTo($this->decimal($state['requested_amount_crypto'] ?? null, $rail->precision)),
            422, 'Nova quote amount mismatch.');

        $expiry = $state['quote_expires_at'] ?? null;
        abort_unless(is_string($expiry) && preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})\z/', $expiry)
            && strtotime($expiry) !== false && is_bool($state['quote_expired'] ?? null), 422);

        $binding = [
            'session_id' => $state['session_id'], 'provider_reference' => $state['provider_reference'],
            'pay_address' => $address, 'requested_amount_crypto' => (string) $requested, 'fiat_rate' => (string) $rate, 'expires_at' => $expiry,
        ];
        foreach ($binding as $key => $value) {
            abort_unless(($transaction->execution_context_snapshot[$key] ?? $value) === $value,
                422, 'Nova invoice binding changed.');
        }

        $status = $state['status'] ?? null;
        abort_unless(is_string($status) && preg_match('/\A[A-Z_]{1,40}\z/', $status), 422);
        return $binding + ['status' => $status, 'quote_expired' => $state['quote_expired'],
            'observed_at' => now()->toIso8601String()];
    }

    private function settle(CmsUserTransaction $transaction, int $walletId, array $state, array $snapshot): void
    {
        abort_unless(($state['terminal'] ?? null) === true && ($state['terminal_state'] ?? null) === 'SUCCESS'
            && ($state['credit_state'] ?? null) === 'SETTLED_POLL_RECHECK'
            && ($state['status_reason'] ?? null) === 'POLL_ONLY_RECONCILIATION_READY'
            && ($state['credit_finalized'] ?? null) === true && ($state['needs_manual_review'] ?? null) === false
            && ($state['manual_approved'] ?? null) === false && ($state['provider_attribution_valid'] ?? null) === true
            && ($state['settlement_key'] ?? null) === 'crypto-credit:' . $state['session_id']
            && ($state['settlement_amount_source'] ?? null) === 'received_amount_crypto'
            && $this->decimal($state['received_amount_crypto'] ?? null, (int) $state['precision'])
                ->isEqualTo($snapshot['requested_amount_crypto']), 422, 'Nova payment requires reconciliation.');

        $wallet = $this->wallets->creditCryptoDeposit($transaction, $state);
        abort_unless($wallet?->id === $walletId, 409);
        $ledger = WalletLedgerEntry::where('wallet_account_id', $walletId)
            ->where('idempotency_key', 'crypto_deposit_credited:' . $transaction->transactionId)->firstOrFail();
        $this->wallets->markCryptoDepositCompleted($transaction, $state);
        $transaction->fill([
            'status' => TransactionStatusEnum::Success, 'settled_wallet_account_id' => $walletId,
            'settled_currency' => $wallet->asset, 'amount' => $ledger->amount, 'exact_amount' => $ledger->amount,
            'before_balance' => $ledger->balance_before, 'exact_before_balance' => $ledger->balance_before,
            'after_balance' => $ledger->balance_after, 'exact_after_balance' => $ledger->balance_after,
        ])->save();
    }

    private function decimal(mixed $value, int $scale): BigDecimal
    {
        abort_unless(is_string($value) && preg_match('/\A\d{1,16}(?:\.\d{1,32})?\z/', $value), 422);
        $number = BigDecimal::of($value)->stripTrailingZeros();
        abort_unless($number->getScale() <= $scale, 422, 'Nova amount precision mismatch.');

        return $number;
    }
}
