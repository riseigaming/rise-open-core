<?php

namespace Tests\Feature\Finance;

use App\Enum\TransactionActionEnum;
use App\Enum\TransactionStatusEnum;
use App\Models\{CmsCountry, CmsUser, CmsUserTransaction, CmsUserType, WalletAccount, WalletLedgerEntry};
use App\Services\Finance\PayoutRoutingService;
use App\Services\Wallet\{AssetWalletService, CanonicalWalletService, SpendableWalletService};
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\{DB, Hash, Http};
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Disposable enrolled MySQL only; no migrations or provider requests.
 * Backlog: ORM identity hooks do not protect direct SQL/query-builder writes.
 * These tests establish no SQL-level immutability or production acceptance.
 */
class PayoutRequestLedgerTest extends TestCase
{
    use DatabaseTransactions;

    private CmsUser $user;
    private WalletAccount $wallet;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->user = new CmsUser;
        // Password is guarded against mass assignment on the canonical model.
        $this->user->forceFill([
            'username' => 'payout_request_fixture', 'email' => 'payout-request@example.invalid',
            'role' => 'customer', 'status' => 'normal', 'currency' => 'TRY',
            'country_id' => CmsCountry::query()->orderBy('id')->firstOrFail()->id,
            'user_type_id' => CmsUserType::where('name', CmsUserType::TYPE_NORMAL)->firstOrFail()->id,
            'password' => Hash::make('Synthetic-request-fixture-927!'),
        ])->save();
        $this->wallet = app(AssetWalletService::class)->ensurePrimaryBalanceWallet($this->user, 'TRY', true);
        $spendable = app(SpendableWalletService::class);
        $context = $spendable->resolveForWallet($this->user, $this->wallet, true);
        $spendable->projectDecimalBalance($context, '100.00');
        $spendable->recordDecimalLedgerEntry($context, 'synthetic_fixture_funding', 'credit',
            '100.00', '0.00', '100.00', 'payout-fixture-funding');
    }

    /** Caller owns creation, projection and rollback, just as S0 FinanceController:2940-2962. */
    private function createRequest(array $overrides = [], ?PayoutRoutingService $service = null, bool $project = true): CmsUserTransaction
    {
        return DB::transaction(function () use ($overrides, $service, $project): CmsUserTransaction {
            $request = CmsUserTransaction::create(array_merge([
                'cms_user_id' => $this->user->id, 'transactionId' => 'synthetic-withdrawal-one',
                'action' => TransactionActionEnum::Withdraw, 'status' => TransactionStatusEnum::Pending,
                'amount' => '20.00', 'before_balance' => '100.00', 'after_balance' => '80.00',
                'currency' => 'TRY', 'source_currency' => 'TRY', 'source_wallet_type' => 'primary_balance',
                'source_wallet_account_id' => $this->wallet->id,
            ], $overrides));
            if ($project) {
                $spendable = app(SpendableWalletService::class);
                $spendable->projectDecimalBalance(
                    $spendable->resolveForWallet($this->user, $this->wallet, true), '80.00');
            }
            ($service ?? app(PayoutRoutingService::class))->markRequestCreated($request->fresh());
            return $request;
        });
    }

    private function rejectWithoutEffect(callable $call, ?string $expectedMessage = null): void
    {
        $tables = ['cms_users', 'wallet_accounts', 'cms_user_transactions', 'wallet_ledger_entries',
            'cms_user_logs', 'cms_sensitive_mutation_events'];
        $snapshot = fn () => collect($tables)->mapWithKeys(
            fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()]
        )->all();
        $before = $snapshot();
        $level = DB::transactionLevel();
        try {
            $call();
            $this->fail('Expected payout evidence rejection.');
        } catch (\DomainException $exception) {
            $this->assertNotSame('', $exception->getMessage());
            if ($expectedMessage !== null) {
                $this->assertSame($expectedMessage, $exception->getMessage());
            }
        }
        $this->assertSame($level, DB::transactionLevel());
        $this->assertSame($before, $snapshot());
    }

    public function test_original_debit_is_linked_without_second_balance_application_and_replays_once(): void
    {
        $request = $this->createRequest();
        app(PayoutRoutingService::class)->markRequestCreated($request->fresh());
        $ledger = WalletLedgerEntry::where('idempotency_key', 'payout_request_created:'.$request->transactionId)->sole();
        $this->assertSame($this->wallet->id, (int) $ledger->wallet_account_id);
        $this->assertSame($this->user->id, (int) $ledger->cms_user_id);
        $this->assertSame('cms_user_transaction', $ledger->source_type);
        $this->assertSame($request->transactionId, $ledger->source_reference);
        $this->assertSame('payout_request_created', $ledger->entry_type);
        $this->assertSame('debit', $ledger->direction);
        $this->assertSame('applied', $ledger->status);
        $this->assertSame('TRY', $ledger->asset);
        $this->assertSame('20.00000000', $ledger->amount);
        $this->assertSame('100.00000000', $ledger->balance_before);
        $this->assertSame('80.00000000', $ledger->balance_after);
        $this->assertSame('80.00000000', $this->wallet->fresh()->available_balance);
        $this->assertSame(2, WalletLedgerEntry::where('wallet_account_id', $this->wallet->id)->count());
    }

    public function test_missing_projection_cannot_mint_a_phantom_debit(): void
    {
        $this->rejectWithoutEffect(fn () => $this->createRequest([], null, false));
    }

    public static function unprovenFundingCases(): array
    {
        return [['missing'], ['pending'], ['other_wallet']];
    }

    #[DataProvider('unprovenFundingCases')]
    public function test_only_applied_funding_on_the_source_wallet_proves_the_debit(string $case): void
    {
        $funding = WalletLedgerEntry::where('wallet_account_id', $this->wallet->id)->sole();
        if ($case === 'missing') {
            $funding->delete();
        } elseif ($case === 'pending') {
            $funding->update(['status' => 'pending']);
        } else {
            $otherWallet = app(AssetWalletService::class)->ensurePrimaryBalanceWallet($this->user, 'USD', true);
            // Corrupt provenance fixture: funding on a different wallet is not proof.
            $funding->update(['wallet_account_id' => $otherWallet->id]);
        }
        $this->rejectWithoutEffect(fn () => $this->createRequest(),
            'Payout original debit projection is not proven.');
    }

    public function test_unpersisted_and_dirty_caller_sources_are_rejected_without_writes(): void
    {
        $message = 'Payout request requires a persisted source in the caller transaction.';
        $this->rejectWithoutEffect(fn () => app(PayoutRoutingService::class)
            ->markRequestCreated(new CmsUserTransaction), $message);
        $request = $this->createRequest()->fresh();
        $request->amount = '10.00';
        $this->rejectWithoutEffect(fn () => app(PayoutRoutingService::class)
            ->markRequestCreated($request), $message);
    }

    public function test_funded_prebalance_must_match_and_invalid_amounts_fail(): void
    {
        foreach ([
            ['amount' => '0'], ['amount' => '-20'], ['amount' => '21'],
            ['before_balance' => '90', 'after_balance' => '70'],
            ['exact_amount' => '19'], ['after_balance' => '-1'],
        ] as $override) {
            $this->rejectWithoutEffect(fn () => $this->createRequest($override));
        }
    }

    public function test_source_asset_missing_wallet_owner_action_and_status_fail_closed(): void
    {
        foreach ([
            ['source_currency' => 'USD'], ['source_currency' => null],
            ['source_wallet_account_id' => null], ['cms_user_id' => 2147483647],
            ['source_wallet_type' => 'crypto_asset_wallet'],
            ['action' => TransactionActionEnum::Deposit], ['status' => TransactionStatusEnum::Success],
            ['transactionId' => ''],
        ] as $override) {
            $this->rejectWithoutEffect(fn () => $this->createRequest($override));
        }
    }

    public function test_source_changed_since_read_is_rejected(): void
    {
        $request = $this->createRequest();
        $stale = $request->fresh();
        // Deliberate fixture bypass demonstrates the documented SQL guard gap.
        DB::table('cms_user_transactions')->where('id', $request->id)->update(['source_currency' => 'USD']);
        $this->rejectWithoutEffect(fn () => app(PayoutRoutingService::class)->markRequestCreated($stale),
            'Payout request source changed.');
    }

    public function test_replay_rejects_a_changed_amount_even_with_valid_arithmetic(): void
    {
        $request = $this->createRequest();
        $request->forceFill(['amount' => '10', 'after_balance' => '90',
            'exact_amount' => '10', 'exact_after_balance' => '90'])->save();
        $this->rejectWithoutEffect(fn () => app(PayoutRoutingService::class)->markRequestCreated($request->fresh()));
    }

    public function test_synthetic_finance_gate_rejects_production_before_writing(): void
    {
        $request = $this->createRequest();
        $source = $request->fresh();
        $environment = $this->app['env'];
        $this->app['env'] = 'production';
        try {
            app(PayoutRoutingService::class)->markRequestCreated($source);
            $this->fail('Expected synthetic finance gate rejection.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Synthetic isolated local finance profile required.', $exception->getMessage());
        } finally {
            $this->app['env'] = $environment;
        }
        $this->assertSame(2, WalletLedgerEntry::where('wallet_account_id', $this->wallet->id)->count());
        $this->assertSame('80.00000000', $this->wallet->fresh()->available_balance);
    }

    public function test_cross_owner_wallet_and_cross_wallet_ledger_replay_are_rejected(): void
    {
        $other = $this->user->replicate();
        $other->username = 'other_payout_owner';
        $other->email = 'other-payout@example.invalid';
        $other->save();
        $wallet = app(AssetWalletService::class)->ensurePrimaryBalanceWallet($other, 'TRY', true);
        $this->rejectWithoutEffect(fn () => $this->createRequest(['source_wallet_account_id' => $wallet->id]));
        $request = $this->createRequest();
        $entry = WalletLedgerEntry::where('source_reference', $request->transactionId)
            ->where('entry_type', 'payout_request_created')->sole()->replicate();
        $entry->wallet_account_id = $wallet->id;
        $entry->cms_user_id = $other->id;
        $entry->save();
        $this->rejectWithoutEffect(fn () => app(PayoutRoutingService::class)->markRequestCreated($request->fresh()));
    }

    public function test_ledger_failure_rolls_back_caller_request_and_projection(): void
    {
        $spendable = \Mockery::mock(SpendableWalletService::class, [
            app(AssetWalletService::class), app(CanonicalWalletService::class),
        ])->makePartial();
        $spendable->shouldReceive('recordDecimalLedgerEntry')->once()->andThrow(new \RuntimeException('ledger fixture failure'));
        $service = new PayoutRoutingService(app(AssetWalletService::class), $spendable);
        try {
            $this->createRequest([], $service);
            $this->fail('Expected ledger failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('ledger fixture failure', $exception->getMessage());
        }
        $this->assertSame(0, CmsUserTransaction::where('cms_user_id', $this->user->id)->count());
        $this->assertSame(1, WalletLedgerEntry::where('wallet_account_id', $this->wallet->id)->count());
        $this->assertSame('100.00000000', $this->wallet->fresh()->available_balance);
    }

    public function test_caller_rollback_removes_successfully_recorded_debit(): void
    {
        try {
            DB::transaction(function (): void {
                $this->createRequest();
                throw new \RuntimeException('caller rollback');
            });
        } catch (\RuntimeException $exception) {
            $this->assertSame('caller rollback', $exception->getMessage());
        }
        $this->assertSame(0, CmsUserTransaction::where('cms_user_id', $this->user->id)->count());
        $this->assertSame(1, WalletLedgerEntry::where('wallet_account_id', $this->wallet->id)->count());
        $this->assertSame('100.00000000', $this->wallet->fresh()->available_balance);
    }

    public function test_pinned_withdrawal_orm_identity_cannot_be_rewritten(): void
    {
        $request = $this->createRequest();
        foreach (['transactionId' => 'rewritten', 'action' => TransactionActionEnum::Deposit,
            'source_wallet_account_id' => 2147483647, 'cms_user_id' => 2147483647,
            'source_currency' => 'USD', 'source_wallet_type' => 'crypto_asset_wallet', 'transaction_asset' => 'USD'] as $field => $value) {
            try {
                $request->fresh()->forceFill([$field => $value])->save();
                $this->fail('Expected pinned identity rejection: '.$field);
            } catch (\LogicException $exception) {
                $this->assertStringContainsString('Pinned withdrawal identity is immutable', $exception->getMessage());
            }
        }
    }
}
