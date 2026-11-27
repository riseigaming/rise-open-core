<?php

namespace Tests\Feature\Finance;

use App\Enum\TransactionActionEnum;
use App\Models\CmsCountry;
use App\Models\CmsUser;
use App\Models\CmsUserTransaction;
use App\Models\CmsUserType;
use App\Models\WalletAccount;
use App\Models\WalletLedgerEntry;
use App\Services\CmsStaff\CmsStaffPermissionService;
use App\Services\Finance\ManualFinanceOverrideService;
use App\Services\Finance\PayoutRoutingService;
use App\Services\Wallet\AssetWalletService;
use App\Services\Wallet\CanonicalWalletService;
use App\Services\Wallet\SpendableWalletService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Adapts WinX S0 PayoutRoutingFlowTest's provenance, cross-reason replay and
 * atomic rollback cases to Rise's service-only synthetic contract. No schema
 * creation, customer payout endpoint or provider dispatch is needed.
 * Tests\TestCase requires .env.testing + TEST_DB_DATABASE=rise_b1_test,
 * loopback MySQL at TEST_DB_PORT (default 13336), blocked egress and the installed
 * schema/country/user-type fixtures. DatabaseTransactions never creates schema.
 * Run ONLY after independent verification of this physical root's protected
 * enrollment, disposable instance identity and non-default TCP port; no 3306.
 */
class PayoutRoutingRefundTest extends TestCase
{
    use DatabaseTransactions;

    private CmsUser $customer;
    private CmsUser $admin;
    private WalletAccount $wallet;
    private CmsUserTransaction $withdrawal;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        // Fixture-local admin: do not depend on an arbitrary installed operator.
        $this->admin = $this->createUser('refund_admin', 'admin');
        $this->assertTrue(app(CmsStaffPermissionService::class)->has(
            $this->admin, CmsStaffPermissionService::FINANCE_MANUAL_OVERRIDE,
        ));
        $this->customer = $this->createUser('refund_source_fixture');
        $this->wallet = DB::transaction(fn () => app(AssetWalletService::class)
            ->ensurePrimaryBalanceWallet($this->customer, 'TRY', true));
        app(ManualFinanceOverrideService::class)->execute([
            'operation_id' => 'refund-source-funding',
            'customer_id' => $this->customer->id,
            'wallet_account_id' => $this->wallet->id,
            'currency' => 'TRY', 'economic_action' => 'manual_credit',
            'external_resolution' => 'manual_operator_decision',
            'amount' => '100.00', 'reason' => 'Synthetic refund acceptance fixture',
        ], $this->admin);

        // Model an already-funded withdrawal with the real canonical writer.
        // Rise does not expose WinX's customer withdrawal creation endpoint.
        DB::transaction(function (): void {
            $wallets = app(SpendableWalletService::class);
            $context = $wallets->resolveForWallet($this->customer, $this->wallet, true);
            $this->withdrawal = CmsUserTransaction::create([
                'cms_user_id' => $this->customer->id,
                'transactionId' => 'refund-source-withdrawal',
                'action' => TransactionActionEnum::Withdraw, 'status' => 1,
                'amount' => '20.01', 'currency' => 'TRY',
                'before_balance' => '100.00', 'after_balance' => '79.99',
                'source_wallet_account_id' => $this->wallet->id,
                'source_wallet_type' => 'primary_balance', 'source_currency' => 'TRY',
                'requested_payout_currency' => 'TRY',
                'payout_context_snapshot' => ['wallet_context' => $context->auditPayload()],
            ]);
            $wallets->projectDecimalBalance($context, '79.99');
            $wallets->recordDecimalLedgerEntry(
                $context, 'payout_request_created', 'debit', '20.01', '100.00', '79.99',
                'payout_request_created:'.$this->withdrawal->transactionId, $this->withdrawal,
            );
        });
    }

    private function createUser(string $name, string $role = 'customer'): CmsUser
    {
        $user = new CmsUser;
        // password is deliberately absent from CmsUser::$fillable.
        $user->forceFill([
            'username' => $name, 'email' => $name.'@example.invalid',
            'role' => $role, 'status' => 'normal', 'currency' => 'TRY',
            'country_id' => CmsCountry::query()->orderBy('id')->firstOrFail()->id,
            'user_type_id' => CmsUserType::where('name', CmsUserType::TYPE_NORMAL)->firstOrFail()->id,
            'password' => Hash::make('Synthetic-refund-fixture-927!'),
        ])->save();

        return $user;
    }

    private function intent(): array
    {
        return [
            'operation_id' => 'manual-original-debit-refund',
            'customer_id' => $this->customer->id,
            'transaction_id' => $this->withdrawal->id, 'new_status' => 3,
            'economic_action' => 'refund_original_debit',
            'external_resolution' => 'manual_operator_decision',
            'wallet_account_id' => $this->wallet->id, 'currency' => 'TRY',
            'amount' => '20.01', 'reason' => 'Synthetic local refund',
            'evidence_reference' => 'synthetic-original-debit',
        ];
    }

    private function snapshot(): array
    {
        // Include every owner/wallet projection so cross-owner mutations cannot hide.
        return collect([
            'cms_users', 'wallet_accounts', 'wallet_user_preferences',
            'cms_user_transactions', 'wallet_ledger_entries', 'finance_manual_overrides',
            'cms_user_logs', 'cms_sensitive_mutation_events',
        ])->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()])->all();
    }

    public function test_manual_refund_records_exact_source_credit_and_replays_without_mutation(): void
    {
        $service = app(ManualFinanceOverrideService::class);
        $result = $service->execute($this->intent(), $this->admin);
        $this->assertFalse($result['idempotent_replay']);
        $this->assertSame('100.00000000', $this->wallet->fresh()->available_balance);
        $this->assertSame('100.00000000', (string) $this->customer->fresh()->balance);
        $this->assertSame(3, (int) $this->withdrawal->fresh()->status);
        $this->assertSame($this->wallet->id, (int) $this->withdrawal->fresh()->settled_wallet_account_id);
        $entry = WalletLedgerEntry::where('source_reference', $this->withdrawal->transactionId)
            ->where('entry_type', 'payout_request_released')->sole();
        $this->assertSame($this->wallet->id, (int) $entry->wallet_account_id);
        $this->assertSame($this->customer->id, (int) $entry->cms_user_id);
        $this->assertSame('TRY', $entry->asset);
        $this->assertSame('credit', $entry->direction);
        $this->assertSame('applied', $entry->status);
        $this->assertSame('cms_user_transaction', $entry->source_type);
        $this->assertSame($this->withdrawal->transactionId, $entry->source_reference);
        $this->assertSame('payout_request_released:'.$this->withdrawal->transactionId, $entry->idempotency_key);
        $this->assertSame('20.01000000', $entry->amount);
        $this->assertSame('79.99000000', $entry->balance_before);
        $this->assertSame('100.00000000', $entry->balance_after);
        $this->assertSame((int) $result['audit']->id, (int) $entry->meta['finance_manual_override_id']);
        $completed = WalletLedgerEntry::where('source_reference', $this->withdrawal->transactionId)
            ->where('entry_type', 'payout_execution_completed')->sole();
        $this->assertSame('0.00000000', $completed->amount);
        $this->assertSame('79.99000000', $completed->balance_before);
        $this->assertSame($completed->balance_before, $completed->balance_after);
        $this->assertSame('refunded', $completed->meta['status']);
        $this->assertSame('TRY', $this->withdrawal->fresh()->settled_currency);
        // Funding credit - original debit + exactly one release = final wallet.
        $net = DB::table('wallet_ledger_entries')->where('wallet_account_id', $this->wallet->id)
            ->selectRaw("SUM(CASE direction WHEN 'credit' THEN amount ELSE -amount END) AS total")->value('total');
        $this->assertTrue(\Brick\Math\BigDecimal::of((string) $net)->isEqualTo('100.00'));
        $after = $this->snapshot();
        $this->assertTrue($service->execute($this->intent(), $this->admin)['idempotent_replay']);
        $this->assertEquals($after, $this->snapshot());
        $this->assertSame('100.00', app(PayoutRoutingService::class)->refundToSource(
            $this->withdrawal, '20.01', 'different_reason',
        ));
        $this->assertEquals($after, $this->snapshot());
    }

    #[DataProvider('invalidDebitCases')]
    public function test_invalid_original_debit_rolls_back_audit_status_and_credit(string $case): void
    {
        $debit = WalletLedgerEntry::where('source_reference', $this->withdrawal->transactionId)
            ->where('entry_type', 'payout_request_created')->sole();
        match ($case) {
            'missing' => $debit->delete(),
            'owner' => $debit->update(['cms_user_id' => $this->admin->id]),
            'wallet' => $debit->update(['wallet_account_id' => 999999999]),
            'currency' => $debit->update(['asset' => 'USD']),
            'amount' => $debit->update(['amount' => '20.02']),
            'arithmetic' => $debit->update(['balance_after' => '80.00']),
            'source' => $debit->update(['source_reference' => 'unrelated-withdrawal']),
            'wallet_type' => DB::table('cms_user_transactions')->where('id', $this->withdrawal->id)->update(['source_wallet_type' => 'crypto_asset_wallet']),
            'source_currency' => DB::table('cms_user_transactions')->where('id', $this->withdrawal->id)->update(['source_currency' => 'USD']),
            'direction' => $debit->update(['direction' => 'credit']),
            'status' => $debit->update(['status' => 'pending']),
            'source_type' => $debit->update(['source_type' => 'unrelated_source']),
            'key' => $debit->update(['idempotency_key' => 'unrelated_debit']),
            'context_owner' => $debit->update(['meta' => ['wallet_context_user_id' => $this->admin->id]]),
        };
        $before = $this->snapshot();
        try {
            app(ManualFinanceOverrideService::class)->execute($this->intent(), $this->admin);
            $this->fail('Invalid original debit must not authorize a refund.');
        } catch (\DomainException $exception) {
            $this->assertStringContainsString('Payout', $exception->getMessage());
        }
        $this->assertEquals($before, $this->snapshot());
    }

    public function test_original_debit_with_nonzero_extra_precision_is_denied_without_effect(): void
    {
        // MariaDB DECIMAL(24,8) pads valid fiat with zeros, but nonzero
        // third-to-eighth decimal digits cannot become a two-decimal refund.
        DB::table('cms_user_transactions')->where('id', $this->withdrawal->id)
            ->update(['amount' => '20.01000001']);
        $before = $this->snapshot();
        try {
            app(ManualFinanceOverrideService::class)->execute($this->intent(), $this->admin);
            $this->fail('Nonzero overprecision must not be rounded into a refund.');
        } catch (\DomainException $exception) {
            $this->assertStringContainsString('Original debit amount does not match', $exception->getMessage());
        }
        $this->assertEquals($before, $this->snapshot());
    }

    public static function invalidDebitCases(): array
    {
        return array_map(fn ($case) => [$case], [
            'missing', 'owner', 'wallet', 'currency', 'amount', 'arithmetic',
            'source', 'wallet_type', 'source_currency',
            'direction', 'status', 'source_type', 'key', 'context_owner',
        ]);
    }

    public function test_historical_reason_specific_release_is_not_credited_again(): void
    {
        $service = app(PayoutRoutingService::class);
        $service->refundToSource($this->withdrawal, '20.01');
        WalletLedgerEntry::where('source_reference', $this->withdrawal->transactionId)
            ->where('entry_type', 'payout_request_released')->update([
            'idempotency_key' => 'payout_request_released:old_reason:'.$this->withdrawal->transactionId,
        ]);
        $before = $this->snapshot();
        $service->refundToSource($this->withdrawal, '20.01', 'new_reason');
        $this->assertEquals($before, $this->snapshot());
    }

    public function test_changed_wallet_preference_cannot_retarget_the_source_refund(): void
    {
        $other = DB::transaction(fn () => app(AssetWalletService::class)
            ->ensurePrimaryBalanceWallet($this->customer, 'USD', true));
        DB::table('wallet_user_preferences')->updateOrInsert(
            ['cms_user_id' => $this->customer->id],
            ['active_wallet_account_id' => $other->id],
        );
        $otherBefore = $other->fresh()->available_balance;
        app(ManualFinanceOverrideService::class)->execute($this->intent(), $this->admin);
        $this->assertSame('100.00000000', $this->wallet->fresh()->available_balance);
        $this->assertSame($otherBefore, $other->fresh()->available_balance);
        $this->assertSame($this->wallet->id, (int) WalletLedgerEntry::where('source_reference', $this->withdrawal->transactionId)
            ->where('entry_type', 'payout_request_released')->sole()->wallet_account_id);
    }

    #[DataProvider('conflictingReleases')]
    public function test_conflicting_existing_release_cannot_authorize_another_credit(string $field, string $value): void
    {
        $service = app(PayoutRoutingService::class);
        $service->refundToSource($this->withdrawal, '20.01');
        WalletLedgerEntry::where('source_reference', $this->withdrawal->transactionId)
            ->where('entry_type', 'payout_request_released')->update([$field => $value]);
        $before = $this->snapshot();
        try {
            $service->refundToSource($this->withdrawal, '20.01');
            $this->fail('Conflicting release evidence requires reconciliation.');
        } catch (\DomainException $exception) {
            $this->assertSame('Existing payout refund evidence does not match the source.', $exception->getMessage());
        }
        $this->assertEquals($before, $this->snapshot());
    }

    public static function conflictingReleases(): array
    {
        return [
            ['amount', '20.02'], ['asset', 'USD'], ['cms_user_id', '999999999'],
            ['wallet_account_id', '999999999'], ['balance_after', '100.01'],
        ];
    }

    #[DataProvider('ledgerFailureStages')]
    public function test_ledger_failure_rolls_back_manual_audit_status_and_balance(string $stage): void
    {
        $real = app(SpendableWalletService::class);
        $wallets = \Mockery::mock(SpendableWalletService::class, [
            app(AssetWalletService::class), app(CanonicalWalletService::class),
        ])->makePartial();
        $wallets->shouldReceive('recordDecimalLedgerEntry')
            ->times($stage === 'payout_request_released' ? 1 : 2)
            ->andReturnUsing(function (...$args) use ($real, $stage): void {
                if ($args[1] === $stage) {
                    throw new \RuntimeException('Synthetic ledger failure');
                }
                $real->recordDecimalLedgerEntry(...$args);
            });
        $this->app->instance(SpendableWalletService::class, $wallets);
        $before = $this->snapshot();
        try {
            app(ManualFinanceOverrideService::class)->execute($this->intent(), $this->admin);
            $this->fail('Ledger failure must abort the complete override.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Synthetic ledger failure', $exception->getMessage());
        }
        $this->assertEquals($before, $this->snapshot());
    }

    public static function ledgerFailureStages(): array
    {
        return [['payout_request_released'], ['payout_execution_completed']];
    }

    public function test_unauthorized_actor_cannot_refund_or_reserve_an_audit(): void
    {
        $this->rejectWithoutMutation(fn () => app(ManualFinanceOverrideService::class)
            ->execute($this->intent(), $this->customer));
    }

    public function test_another_customer_cannot_claim_the_withdrawal(): void
    {
        $other = $this->createUser('refund_other_owner');
        $intent = array_replace($this->intent(), ['customer_id' => $other->id]);
        $this->rejectWithoutMutation(fn () => app(ManualFinanceOverrideService::class)
            ->execute($intent, $this->admin));
    }

    public function test_source_wallet_of_another_owner_cannot_be_used(): void
    {
        $other = $this->createUser('refund_foreign_wallet');
        $foreign = DB::transaction(fn () => app(AssetWalletService::class)
            ->ensurePrimaryBalanceWallet($other, 'TRY', true));
        // Deliberate corruption fixture: ORM identity enforcement has its own tests.
        DB::table('cms_user_transactions')->where('id', $this->withdrawal->id)
            ->update(['source_wallet_account_id' => $foreign->id]);
        $this->rejectWithoutMutation(fn () => app(PayoutRoutingService::class)
            ->refundToSource($this->withdrawal));
    }

    public function test_duplicate_original_debit_on_another_wallet_is_ambiguous(): void
    {
        $other = DB::transaction(fn () => app(AssetWalletService::class)
            ->ensurePrimaryBalanceWallet($this->customer, 'USD', true));
        $duplicate = WalletLedgerEntry::where('source_reference', $this->withdrawal->transactionId)
            ->where('entry_type', 'payout_request_created')->sole()->replicate();
        $duplicate->wallet_account_id = $other->id;
        $duplicate->save();
        $this->rejectWithoutMutation(fn () => app(PayoutRoutingService::class)
            ->refundToSource($this->withdrawal));
    }

    public function test_shifted_transaction_balances_cannot_replace_original_debit(): void
    {
        DB::table('cms_user_transactions')->where('id', $this->withdrawal->id)->update([
            'before_balance' => '200.00', 'after_balance' => '179.99',
            'exact_before_balance' => '200.00', 'exact_after_balance' => '179.99',
        ]);
        $this->rejectWithoutMutation(fn () => app(PayoutRoutingService::class)
            ->refundToSource($this->withdrawal->fresh()));
    }

    public function test_cross_wallet_debit_key_collision_with_wrong_source_is_rejected(): void
    {
        $other = DB::transaction(fn () => app(AssetWalletService::class)
            ->ensurePrimaryBalanceWallet($this->customer, 'USD', true));
        $duplicate = WalletLedgerEntry::where('source_reference', $this->withdrawal->transactionId)
            ->where('entry_type', 'payout_request_created')->sole()->replicate();
        $duplicate->wallet_account_id = $other->id;
        $duplicate->source_type = 'wallet_context';
        $duplicate->source_reference = 'unrelated';
        $duplicate->entry_type = 'other';
        $duplicate->save();
        $this->rejectWithoutMutation(fn () => app(PayoutRoutingService::class)
            ->refundToSource($this->withdrawal));
    }

    public function test_cross_wallet_refund_key_collision_with_wrong_source_is_rejected(): void
    {
        $other = DB::transaction(fn () => app(AssetWalletService::class)
            ->ensurePrimaryBalanceWallet($this->customer, 'USD', true));
        $collision = WalletLedgerEntry::where('source_reference', $this->withdrawal->transactionId)
            ->where('entry_type', 'payout_request_created')->sole()->replicate();
        $collision->wallet_account_id = $other->id;
        $collision->idempotency_key = 'payout_request_released:'.$this->withdrawal->transactionId;
        $collision->source_type = 'wallet_context';
        $collision->source_reference = 'unrelated';
        $collision->entry_type = 'other';
        $collision->save();
        $this->rejectWithoutMutation(fn () => app(PayoutRoutingService::class)
            ->refundToSource($this->withdrawal));
    }

    public function test_multiple_historical_releases_require_reconciliation(): void
    {
        $service = app(PayoutRoutingService::class);
        $service->refundToSource($this->withdrawal);
        $duplicate = WalletLedgerEntry::where('source_reference', $this->withdrawal->transactionId)
            ->where('entry_type', 'payout_request_released')->sole()->replicate();
        $duplicate->idempotency_key = 'payout_request_released:historical:'.$this->withdrawal->transactionId;
        $duplicate->save();
        $this->rejectWithoutMutation(fn () => $service->refundToSource($this->withdrawal));
    }

    #[DataProvider('invalidRefundAmounts')]
    public function test_direct_refund_requires_exact_positive_original_amount(string $amount): void
    {
        $this->rejectWithoutMutation(fn () => app(PayoutRoutingService::class)
            ->refundToSource($this->withdrawal, $amount));
    }

    public static function invalidRefundAmounts(): array
    {
        return [['0'], ['-20.01'], ['20.00'], ['20.02'], ['20.011']];
    }

    public function test_stale_caller_model_cannot_replace_locked_database_evidence(): void
    {
        DB::table('cms_user_transactions')->where('id', $this->withdrawal->id)->update(['source_currency' => 'USD']);
        // Retain the original caller snapshot; only the locked DB row is corrupt.
        $this->assertSame('TRY', $this->withdrawal->source_currency);
        $this->rejectWithoutMutation(fn () => app(PayoutRoutingService::class)
            ->refundToSource($this->withdrawal));
    }

    public function test_new_manual_operation_cannot_refund_an_already_released_debit(): void
    {
        $service = app(ManualFinanceOverrideService::class);
        $service->execute($this->intent(), $this->admin);
        $intent = array_replace($this->intent(), ['operation_id' => 'another-refund-operation']);
        $this->rejectWithoutMutation(fn () => $service->execute($intent, $this->admin));
        $changed = array_replace($this->intent(), ['reason' => 'Changed replay intent']);
        $this->rejectWithoutMutation(fn () => $service->execute($changed, $this->admin));
    }

    public function test_outer_transaction_rollback_reverts_successful_nested_refund(): void
    {
        $before = $this->snapshot();
        $level = DB::transactionLevel();
        try {
            DB::transaction(function (): void {
                app(ManualFinanceOverrideService::class)->execute($this->intent(), $this->admin);
                $this->assertSame('100.00000000', $this->wallet->fresh()->available_balance);
                throw new \RuntimeException('Synthetic outer rollback');
            });
            $this->fail('Outer rollback expected.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Synthetic outer rollback', $exception->getMessage());
        }
        $this->assertSame($level, DB::transactionLevel());
        $this->assertEquals($before, $this->snapshot());
    }

    public function test_refund_settlement_uses_source_asset_not_requested_payout_asset(): void
    {
        $this->withdrawal->update(['requested_payout_currency' => 'USD']);
        $result = app(ManualFinanceOverrideService::class)->execute($this->intent(), $this->admin);
        $this->assertFalse($result['idempotent_replay']);
        $this->assertSame('TRY', $this->withdrawal->fresh()->settled_currency);
        $this->assertSame('TRY', WalletLedgerEntry::where('source_reference', $this->withdrawal->transactionId)
            ->where('entry_type', 'payout_request_released')->sole()->asset);
    }

    public function test_currency_child_refund_replays_with_canonical_ledger_owner(): void
    {
        $child = $this->createUser('refund_usd_child');
        $child->forceFill(['parent_id' => $this->customer->id, 'currency' => 'USD'])->save();
        $usdWallet = DB::transaction(fn () => app(AssetWalletService::class)
            ->ensurePrimaryBalanceWallet($child, 'USD', true));
        $this->assertSame($this->customer->id, (int) $usdWallet->cms_user_id);

        $fundingIntent = [
            'operation_id' => 'fund-refund-usd-child',
            'customer_id' => $child->id,
            'wallet_account_id' => $usdWallet->id,
            'currency' => 'USD', 'economic_action' => 'manual_credit',
            'external_resolution' => 'manual_operator_decision',
            'amount' => '50.00', 'reason' => 'Synthetic USD child funding',
        ];
        $manual = app(ManualFinanceOverrideService::class);
        $this->assertFalse($manual->execute($fundingIntent, $this->admin)['idempotent_replay']);
        $this->assertTrue($manual->execute($fundingIntent, $this->admin)['idempotent_replay']);

        $usdWithdrawal = DB::transaction(function () use ($child, $usdWallet): CmsUserTransaction {
            $wallets = app(SpendableWalletService::class);
            $context = $wallets->resolveForWallet($child, $usdWallet, true, $child->id);
            $this->assertSame($child->id, $context->ownerWalletId());
            $withdrawal = CmsUserTransaction::create([
                'cms_user_id' => $child->id, 'transactionId' => 'refund-usd-child-withdrawal',
                'action' => TransactionActionEnum::Withdraw, 'status' => 1,
                'amount' => '10.00', 'currency' => 'USD',
                'before_balance' => '50.00', 'after_balance' => '40.00',
                'source_wallet_account_id' => $usdWallet->id,
                'source_wallet_type' => 'primary_balance', 'source_currency' => 'USD',
                'requested_payout_currency' => 'EUR',
                'payout_context_snapshot' => ['wallet_context' => $context->auditPayload()],
            ]);
            $wallets->projectDecimalBalance($context, '40.00');
            $wallets->recordDecimalLedgerEntry(
                $context, 'payout_request_created', 'debit', '10.00',
                '50.00', '40.00', 'payout_request_created:'.$withdrawal->transactionId, $withdrawal,
            );
            return $withdrawal;
        });

        $intent = array_replace($this->intent(), [
            'operation_id' => 'refund-usd-child',
            'customer_id' => $child->id, 'transaction_id' => $usdWithdrawal->id,
            'wallet_account_id' => $usdWallet->id, 'currency' => 'USD', 'amount' => '10.00',
        ]);
        $service = app(ManualFinanceOverrideService::class);
        $this->assertFalse($service->execute($intent, $this->admin)['idempotent_replay']);
        $replay = $service->execute($intent, $this->admin);
        $this->assertTrue($replay['idempotent_replay']);
        $this->assertSame('50.00000000', $usdWallet->fresh()->available_balance);
        $this->assertSame('USD', $usdWithdrawal->fresh()->settled_currency);
        $releases = WalletLedgerEntry::where('entry_type', 'payout_request_released')
            ->where('source_reference', $usdWithdrawal->transactionId)->get();
        $this->assertCount(1, $releases);
        $this->assertSame($child->id, (int) $releases->first()->cms_user_id);
        $this->assertSame('USD', $releases->first()->asset);
    }

    private function rejectWithoutMutation(callable $call): void
    {
        $before = $this->snapshot();
        $level = DB::transactionLevel();
        try {
            $call();
            $this->fail('Untrusted refund evidence must be rejected.');
        } catch (\DomainException $exception) {
            $this->assertNotEmpty($exception->getMessage());
        }
        $this->assertSame($level, DB::transactionLevel());
        $this->assertEquals($before, $this->snapshot());
    }

    public function test_closed_gate_prevents_refund_without_mutation(): void
    {
        DB::table('finance_reset_controls')->where('id', 1)->update(['writes_closed' => true]);
        $before = $this->snapshot();
        try {
            app(ManualFinanceOverrideService::class)->execute($this->intent(), $this->admin);
            $this->fail('Closed gate must reject refund.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('prelaunch_finance_writes_closed', $exception->getMessage());
        }
        $this->assertEquals($before, $this->snapshot());
    }

    public function test_direct_refund_cannot_enter_a_production_profile(): void
    {
        $before = $this->snapshot();
        $previousEnvironment = $this->app['env'];
        $this->app['env'] = 'production';
        try {
            app(PayoutRoutingService::class)->refundToSource($this->withdrawal, '20.01');
            $this->fail('Refund must retain the synthetic finance boundary.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Synthetic isolated local finance profile required.', $exception->getMessage());
        } finally {
            $this->app['env'] = $previousEnvironment;
        }
        $this->assertEquals($before, $this->snapshot());
    }
}
