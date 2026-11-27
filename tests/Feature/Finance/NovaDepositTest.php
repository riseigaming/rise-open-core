<?php

namespace Tests\Feature\Finance;

use App\Enum\TransactionStatusEnum;
use App\Models\CmsCountry;
use App\Models\CmsUser;
use App\Models\CmsUserType;
use App\Models\WalletAccount;
use App\Models\WalletLedgerEntry;
use App\Services\Finance\PrelaunchFinanceWriteGate;
use App\Services\Payments\NovaDepositService;
use App\Services\Security\CmsAuthenticatedSessionService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

final class NovaDepositTest extends TestCase
{
    private const BASE = 'http://127.0.0.1:18982';
    private const REMOTE = 'https://nova.example.test';
    private const MAINNET_ADDRESS = '1BoatSLRHtKNngkdXEeobR76b53LETtpyT'; // Wire fixture only.
    private const ADDRESS = 'mipcBbFg9gMiCh81Kj8tqqdgoZub1ZJRfn';
    private CmsUser $customer;
    private CmsUser $stranger;
    private array $state;
    private array $changes = [];
    private int $httpStatus = 200;
    private int $creates = 0;
    private int $polls = 0;
    private string $key;
    private bool $trigger = false;
    private bool $timeoutCreate = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->key = (string) Str::uuid();
        foreach (['customer', 'stranger'] as $name) {
            $username = 'nova_' . $name . '_' . Str::random(8);
            $this->$name = CmsUser::create([
                'username' => $username, 'email' => $username . '@example.test', 'role' => 'customer',
                'status' => 'normal', 'country_id' => CmsCountry::value('id'),
                'user_type_id' => CmsUserType::value('id'), 'currency' => 'TRY', 'password' => Hash::make(Str::random(24)),
            ]);
        }
        config([
            'nova_payments.enabled' => true, 'nova_payments.url' => self::BASE,
            'nova_payments.username' => 'fixture', 'nova_payments.password' => 'local-fixture',
            'nova_payments.site_secret' => 'site-fixture',
            'customer_crypto_rails.BTC/BTC.customer_visible' => true,
            'customer_crypto_rails.BTC/BTC.deposit_enabled' => true,
        ]);
        $this->state = [
            'session_id' => 'nova-session-1', 'provider_reference' => 'invoice-1', 'provider' => 'bitcart',
            'asset' => 'BTC', 'network' => 'BTC', 'rail_key' => 'btc', 'precision' => 8, 'payment_rail' => 'CRYPTO',
            'contract_version' => 2, 'polling_supported' => true, 'customer_id' => $this->customer->id,
            'network_mode' => 'testnet', 'provider_mode' => 'test', 'pay_address' => self::ADDRESS,
            'amount_crypto' => '0.00100000', 'requested_amount_crypto' => '0.00100000',
            'received_amount_crypto' => '0.00000000', 'fiat_reference_amount' => '100.00',
            'fiat_currency' => 'TRY', 'fiat_rate' => '100000.00', 'status' => 'AWAITING_PAYMENT',
            'quote_expired' => false, 'quote_expires_at' => now()->addMinutes(15)->toIso8601String(),
        ];
        Http::preventStrayRequests();
        $fake = function (Request $request) {
            $base = config('nova_payments.url');
            $this->assertTrue($request->hasHeader('Authorization', 'Basic ' . base64_encode('fixture:local-fixture')));
            if ($request->url() === self::REMOTE . '/api/external/crypto/rails/btc/readiness') {
                $this->assertSame('GET', $request->method());
                $this->assertTrue($request->hasHeader('X-Site-Secret', 'site-fixture'));
                return Http::response(['data' => [
                    'contract_version' => 1, 'rail' => ['asset' => 'BTC', 'network' => 'BTC', 'railKey' => 'btc', 'precision' => 8],
                    'tenantFingerprint' => $this->changes['tenantFingerprint'] ?? hash_hmac('sha256', 'crypto-runtime-readiness:tenant:v1', 'site-fixture'),
                ]], $this->httpStatus);
            }
            if ($request->method() === 'POST') {
                ++$this->creates;
                $this->assertSame($base . '/api/external/crypto/session', $request->url());
                $this->assertSame('site-fixture', $request['siteSecret']);
                $this->assertSame('100.00', $request['amount']);
                $this->assertSame($this->customer->id, $request['customerId']);
                $this->assertSame($this->state['network_mode'], $request['networkMode']);
                $this->assertSame($this->state['provider_mode'], $request['providerMode']);
                $this->state['transaction_id'] = $request['transactionId'];
                if ($this->timeoutCreate) {
                    return (Http::failedConnection('fixture timeout'))($request);
                }
                return Http::response(['status' => 'Success', 'data' => $this->state], 201);
            }

            ++$this->polls;
            $this->assertSame($base . '/api/external/crypto/transaction/' . $this->state['transaction_id'], $request->url());
            $this->assertTrue($request->hasHeader('X-Site-Secret', 'site-fixture'));
            return Http::response(['status' => 'Success', 'data' => $this->changes + $this->state], $this->httpStatus);
        };
        Http::fake([self::BASE . '/*' => $fake, self::REMOTE . '/*' => $fake]);
        $this->establish($this->customer);
    }

    protected function tearDown(): void
    {
        if (isset($this->customer)) {
            if ($this->trigger) {
                DB::unprepared('DROP TRIGGER IF EXISTS run32_fail_nova_ledger');
            }
            $ids = [$this->customer->id, $this->stranger->id];
            DB::table('crypto_settlement_claims')->whereIn('cms_transaction_id',
                DB::table('cms_user_transactions')->whereIn('cms_user_id', $ids)->select('id'))->delete();
            DB::table('cms_sessions')->whereIn('cms_user_id', $ids)->delete();
            WalletLedgerEntry::whereIn('cms_user_id', $ids)->delete();
            app(NovaDepositService::class)->deposits()->whereIn('cms_user_id', $ids)->delete();
            WalletAccount::whereIn('cms_user_id', $ids)->delete();
            CmsUser::whereIn('id', $ids)->delete();
        }
        parent::tearDown();
    }

    public function test_pending_then_verified_credit_replay_and_customer_and_admin_readback(): void
    {
        $reference = $this->start()->assertOk()->assertJsonPath('status', TransactionStatusEnum::Pending)
            ->assertJsonPath('payment.pay_address', self::ADDRESS)->json('reference');
        $this->start()->assertOk()->assertJsonPath('reference', $reference);
        $this->assertSame(1, $this->creates);
        $this->postJson('/cms/wallet/nova', ['request_key' => $this->key, 'amount' => '101.00'])->assertConflict();
        $this->get('/cms/wallet/nova')->assertOk()->assertSee(self::ADDRESS)->assertSee($reference);
        $this->recheck($reference)->assertOk()->assertJsonPath('status', TransactionStatusEnum::Pending);
        $this->assertUncredited();

        $this->complete();
        $this->recheck($reference)->assertOk()->assertJsonPath('status', TransactionStatusEnum::Success)
            ->assertJsonPath('amount', '0.00100000');
        $this->recheck($reference)->assertOk()->assertJsonPath('status', TransactionStatusEnum::Success);
        $this->assertSame(2, $this->polls);
        $deposit = app(NovaDepositService::class)->deposits()->where('transactionId', $reference)->firstOrFail();
        $wallet = WalletAccount::findOrFail($deposit->settled_wallet_account_id);
        $this->assertSame($wallet->id, $deposit->source_wallet_account_id);
        $this->assertSame('0.00100000', $wallet->available_balance);
        $this->assertSame('0.00000000', $this->customer->fresh()->balance);
        $ledger = WalletLedgerEntry::where('source_reference', $reference)->orderBy('id')->get();
        $this->assertSame(['crypto_deposit_credited', 'crypto_deposit_completed'], $ledger->pluck('entry_type')->all());
        $this->assertSame(['0.00100000', '0.00000000'], $ledger->pluck('amount')->all());
        $this->assertSame([$wallet->id, $wallet->id], $ledger->pluck('wallet_account_id')->all());
        $this->assertDatabaseHas('crypto_settlement_claims', ['cms_transaction_id' => $deposit->id, 'provider_reference' => 'invoice-1']);
        $this->get('/cms/wallet/nova')->assertOk()->assertSee('Credited');
        $this->get('/cms/admin/finance/nova')->assertForbidden();
        $this->establish(CmsUser::where('role', 'admin')->firstOrFail());
        $this->get('/cms/admin/finance/nova')->assertOk()->assertSee($reference)->assertSee('0.00100000 BTC');
    }

    public function test_timeout_reuses_the_persisted_reference_and_cannot_credit_creation_response(): void
    {
        $this->timeoutCreate = true;
        $this->start()->assertStatus(503);
        $reference = $this->state['transaction_id'];
        $this->get('/cms/wallet/nova')->assertOk()->assertSee('Retry original request')->assertSee($reference);
        $this->timeoutCreate = false;
        $this->complete();
        $this->state = $this->changes + $this->state;
        $this->start()->assertOk()->assertJsonPath('reference', $reference)->assertJsonPath('status', TransactionStatusEnum::Pending);
        $this->assertSame(2, $this->creates);
        $this->assertUncredited();
        $this->recheck($reference)->assertOk()->assertJsonPath('status', TransactionStatusEnum::Success);
    }

    public function test_mismatched_identity_amount_finality_and_auth_cannot_credit(): void
    {
        $reference = $this->start()->assertOk()->json('reference');
        $this->complete();
        $completed = $this->changes;
        foreach ([
            'asset' => 'LTC', 'network' => 'LTC', 'network_mode' => 'mainnet', 'fiat_currency' => 'USD',
            'fiat_reference_amount' => '101.00', 'amount_crypto' => '0.00110000',
            'received_amount_crypto' => '0.00090000', 'customer_id' => $this->stranger->id,
            'session_id' => 'different-session', 'provider_reference' => 'different-invoice',
            'required_confirmations' => 100, 'credit_finalized' => false, 'manual_approved' => true,
            'payment_outputs' => [],
        ] as $field => $value) {
            $this->changes = [$field => $value] + $completed;
            $this->recheck($reference)->assertStatus(422);
            $this->assertUncredited();
        }
        $this->changes = ['status' => 'UNRECOGNIZED'] + $completed;
        $this->recheck($reference)->assertOk()->assertJsonPath('status', TransactionStatusEnum::Pending);
        $this->httpStatus = 401;
        $this->recheck($reference)->assertStatus(503);
        $polls = $this->polls;
        config(['nova_payments.site_secret' => 'another-tenant']);
        $this->recheck($reference)->assertStatus(503);
        $this->assertSame($polls, $this->polls);
        $this->establish($this->stranger);
        $this->recheck($reference)->assertNotFound();
        $this->assertUncredited();
    }

    public function test_duplicate_indexed_output_cannot_fund_another_intent(): void
    {
        $first = $this->start()->assertOk()->json('reference');
        $this->complete();
        $this->recheck($first)->assertOk()->assertJsonPath('status', TransactionStatusEnum::Success);

        $this->key = (string) Str::uuid();
        $this->state['session_id'] = 'nova-session-2';
        $this->state['provider_reference'] = 'invoice-2';
        $second = $this->start()->assertOk()->json('reference');
        $this->changes['settlement_key'] = 'crypto-credit:nova-session-2';
        $this->changes['provenance']['provider_reference'] = 'invoice-2';

        $this->recheck($second)->assertUnprocessable();
        $this->assertSame(1, DB::table('crypto_settlement_claims')->whereIn('provider_reference', ['invoice-1', 'invoice-2'])->count());
        $this->assertSame(0, WalletLedgerEntry::where('source_reference', $second)->count());
        $this->assertDatabaseHas('cms_user_transactions', ['transactionId' => $second, 'status' => TransactionStatusEnum::Pending]);
    }

    public function test_default_off_and_currency_configuration_mismatch_do_not_call_nova(): void
    {
        config(['nova_payments.enabled' => false]);
        $this->start()->assertStatus(503);
        config(['nova_payments.enabled' => true, 'nova_payments.fiat_currency' => 'USD']);
        $this->start()->assertStatus(503);
        Http::assertNothingSent();
        $this->assertFalse(app(NovaDepositService::class)->deposits()->where('cms_user_id', $this->customer->id)->exists());
    }

    public function test_ledger_insert_failure_rolls_back_transaction_claim_and_balance(): void
    {
        $reference = $this->start()->assertOk()->json('reference');
        $this->complete();
        DB::unprepared("CREATE TRIGGER run32_fail_nova_ledger BEFORE INSERT ON wallet_ledger_entries
            FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'run32 synthetic ledger failure'");
        $this->trigger = true;
        $this->recheck($reference)->assertStatus(500);
        $this->assertUncredited();
        $this->assertDatabaseMissing('crypto_settlement_claims', ['provider_reference' => 'invoice-1']);
        $this->assertDatabaseHas('cms_user_transactions', ['transactionId' => $reference, 'status' => TransactionStatusEnum::Pending]);
        DB::unprepared('DROP TRIGGER run32_fail_nova_ledger');
        $this->trigger = false;
        $this->recheck($reference)->assertOk()->assertJsonPath('status', TransactionStatusEnum::Success);
    }

    public function test_remote_connection_checks_tenant_without_session_or_credit_permission(): void
    {
        $this->configureRemote();
        config(['nova_payments.remote_sessions_enabled' => false, 'nova_payments.remote_credit_enabled' => true]);

        $this->artisan('nova:connection-check')->expectsOutput('Nova HTTPS, tenant and BTC rail verified.')->assertSuccessful();
        $this->start()->assertStatus(503);
        $this->assertSame(0, $this->creates);
        $this->assertDatabaseMissing('cms_user_transactions', ['cms_user_id' => $this->customer->id]);
        Http::assertSentCount(1);
        config(['nova_payments.url' => self::REMOTE . ':443', 'nova_payments.remote_authority' => 'nova.example.test:443']);
        $this->artisan('nova:connection-check')->assertSuccessful();
        config(['nova_payments.url' => self::REMOTE, 'nova_payments.remote_authority' => 'nova.example.test']);

        try {
            Http::get(self::REMOTE . '/api/external/crypto/rails/btc/readiness');
            $this->fail('Generic HTTP must retain the B1 egress restriction.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('External HTTP request blocked by local network safety policy.', $exception->getMessage());
        }

        $this->changes = ['tenantFingerprint' => str_repeat('a', 64)];
        $this->artisan('nova:connection-check')->assertFailed();
        $this->changes = [];
        $this->httpStatus = 302;
        $this->artisan('nova:connection-check')->assertFailed();
        foreach (['http://nova.example.test', 'https://other.example.test', 'https://nova.example.test/prefix',
            'https://name:password@nova.example.test', 'https://nova.example.test/?query=1'] as $url) {
            config(['nova_payments.url' => $url]);
            $this->artisan('nova:connection-check')->assertFailed();
        }
        Http::assertSentCount(4);
    }

    public function test_remote_credit_requires_separate_permission_and_retains_the_existing_reset_gate(): void
    {
        $this->configureRemote();
        $this->app->instance('env', 'production');
        try {
            app(PrelaunchFinanceWriteGate::class)->assertWritable();
            $this->fail('General finance write gate must remain closed for this profile.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Synthetic isolated local finance profile required.', $exception->getMessage());
        }

        $this->get('/')->assertOk()->assertSee(route('cms.wallet.nova.index'));
        $reference = $this->start()->assertOk()->assertJsonPath('payment.pay_address', self::MAINNET_ADDRESS)->json('reference');
        $this->get('/cms/wallet/nova')->assertOk()->assertSee('Do not send funds.')->assertSee(self::MAINNET_ADDRESS);
        $this->complete();
        $this->recheck($reference)->assertOk()->assertJsonPath('status', TransactionStatusEnum::Pending)
            ->assertJsonPath('payment.status', 'COMPLETED');
        $this->assertUncredited();
        $this->assertDatabaseMissing('crypto_settlement_claims', ['provider_reference' => 'invoice-1']);

        config(['nova_payments.remote_credit_enabled' => true]);
        DB::table('finance_reset_controls')->where('id', 1)->update(['writes_closed' => true]);
        try {
            $this->recheck($reference)->assertStatus(503);
            $this->start()->assertStatus(503);
            $this->assertUncredited();
        } finally {
            DB::table('finance_reset_controls')->where('id', 1)->update(['writes_closed' => false]);
        }

        $this->recheck($reference)->assertOk()->assertJsonPath('status', TransactionStatusEnum::Success)
            ->assertJsonPath('amount', '0.00100000');
        $this->recheck($reference)->assertOk()->assertJsonPath('amount', '0.00100000');
        $this->assertSame(1, WalletLedgerEntry::where('source_reference', $reference)->where('amount', '>', 0)->count());
        $this->assertSame(1, DB::table('crypto_settlement_claims')->where('provider_reference', 'invoice-1')->count());
        $this->establish(CmsUser::where('role', 'admin')->firstOrFail());
        $this->get('/cms/admin')->assertOk()->assertSee(route('cms.admin.finance.nova'));
        $this->get('/cms/admin/finance/nova')->assertOk()->assertSee($reference)->assertSee('0.00100000 BTC');
    }

    private function configureRemote(): void
    {
        config([
            'nova_payments.url' => self::REMOTE, 'nova_payments.remote_authority' => 'nova.example.test',
            'nova_payments.remote_sessions_enabled' => true, 'nova_payments.remote_credit_enabled' => false,
            'nova_payments.provider_mode' => 'LIVE_HTTP', 'nova_payments.network_mode' => 'mainnet',
        ]);
        $this->state['provider_mode'] = 'LIVE_HTTP';
        $this->state['network_mode'] = 'mainnet';
        $this->state['pay_address'] = self::MAINNET_ADDRESS;
    }

    private function establish(CmsUser $user): void
    {
        $this->actingAs($user, 'cms');
        $session = app('session')->driver();
        $session->start();
        $request = \Illuminate\Http\Request::create('/');
        $request->setLaravelSession($session);
        app(CmsAuthenticatedSessionService::class)->establish($request, $user, 'run32_test');
        $session->save();
        $this->withCookie(config('session.cookie'), $session->getId());
        $this->withHeader('X-CSRF-TOKEN', $session->token());
    }

    private function start()
    {
        return $this->postJson('/cms/wallet/nova', ['request_key' => $this->key, 'amount' => '100.00']);
    }

    private function recheck(string $reference)
    {
        return $this->postJson('/cms/wallet/nova/' . $reference . '/recheck');
    }

    private function assertUncredited(): void
    {
        $this->assertSame('0.00000000', WalletAccount::where('cms_user_id', $this->customer->id)->firstOrFail()->available_balance);
        $this->assertSame(0, WalletLedgerEntry::where('cms_user_id', $this->customer->id)->count());
    }

    private function complete(): void
    {
        $hash = str_repeat('b', 64);
        $this->changes = [
            'status' => 'COMPLETED', 'status_reason' => 'POLL_ONLY_RECONCILIATION_READY',
            'terminal' => true, 'terminal_state' => 'SUCCESS', 'credit_state' => 'SETTLED_POLL_RECHECK',
            'credit_finalized' => true, 'needs_manual_review' => false, 'manual_approved' => false,
            'provider_attribution_valid' => true, 'settlement_key' => 'crypto-credit:nova-session-1',
            'settlement_amount_source' => 'received_amount_crypto', 'received_amount_crypto' => '0.00100000',
            'confirmations' => 3, 'required_confirmations' => 2, 'tx_hash' => $hash,
            'provenance' => ['address_source' => 'provider_recheck', 'network_source' => 'rail_plus_provider',
                'rail_key' => 'btc', 'network_mode' => $this->state['network_mode'], 'provider_reference' => 'invoice-1'],
            'payment_outputs' => [['kind' => 'utxo_vout', 'output_index' => 0, 'tx_hash' => $hash,
                'address' => $this->state['pay_address'], 'amount' => '0.00100000']],
        ];
    }
}
