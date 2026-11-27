<?php

namespace Tests\Feature\Finance;

use App\Enum\TransactionActionEnum;
use App\Enum\TransactionStatusEnum;
use App\Enum\UserLogEnum;
use App\Models\CmsCountry;
use App\Models\CmsUser;
use App\Models\CmsUserLog;
use App\Models\CmsUserTransaction;
use App\Models\CmsUserType;
use App\Models\WalletAccount;
use App\Models\WalletLedgerEntry;
use App\Services\Finance\WithdrawalCancellationService;
use App\Services\Wallet\AssetWalletService;
use App\Services\Wallet\SpendableWalletService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Requires the existing enrolled, migrated synthetic MySQL acceptance root. */
class WithdrawalCancellationTest extends TestCase
{
    use DatabaseTransactions;

    private CmsUser $member;
    private WalletAccount $wallet;
    private CmsUserTransaction $withdrawal;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->member = $this->member();
        DB::transaction(function (): void {
            $this->wallet = app(AssetWalletService::class)
                ->ensurePrimaryBalanceWallet($this->member, 'TRY', true);
            $context = app(SpendableWalletService::class)->resolveForWallet($this->member, $this->wallet, true);
            $spendable = app(SpendableWalletService::class);
            $spendable->projectDecimalBalance($context, '100.00');
            $spendable->recordDecimalLedgerEntry(
                $context, 'synthetic_test_funding', 'credit', '100.00', '0.00', '100.00',
                'cancel-funding:'.Str::uuid()
            );
            $this->withdrawal = CmsUserTransaction::create([
                'cms_user_id' => $this->member->id,
                'transactionId' => 'cancel-'.Str::uuid(),
                'action' => TransactionActionEnum::Withdraw,
                'status' => TransactionStatusEnum::Pending,
                'amount' => '12.34', 'before_balance' => '100.00', 'after_balance' => '87.66',
                'currency' => 'TRY', 'source_currency' => 'TRY',
                'source_wallet_type' => 'primary_balance',
                'source_wallet_account_id' => $this->wallet->id,
                'requested_payout_currency' => 'TRY',
            ]);
            $spendable->projectDecimalBalance($context, '87.66');
            $spendable->recordDecimalLedgerEntry(
                $context, 'payout_request_created', 'debit', '12.34', '100.00', '87.66',
                'payout_request_created:'.$this->withdrawal->transactionId, $this->withdrawal
            );
        });
    }

    private function member(): CmsUser
    {
        $suffix = (string) Str::uuid();
        $user = new CmsUser;
        $user->forceFill([
            'username' => 'cancel-'.$suffix, 'email' => $suffix.'@example.invalid',
            'password' => Hash::make('Synthetic-cancel-fixture-927!'), 'role' => 'customer', 'status' => 'normal',
            'country_id' => CmsCountry::query()->orderBy('id')->firstOrFail()->id,
            'user_type_id' => CmsUserType::where('name', CmsUserType::TYPE_NORMAL)->firstOrFail()->id, 'currency' => 'TRY',
        ])->save();

        return $user;
    }

    private function cancel(?CmsUser $actor = null, ?string $reference = null): CmsUserTransaction
    {
        return app(WithdrawalCancellationService::class)->cancel(
            $actor ?? $this->member, $reference ?? $this->withdrawal->transactionId
        );
    }

    private function assertRejected(callable $attempt): void
    {
        $tables = ['cms_users', 'wallet_accounts', 'cms_user_transactions', 'wallet_ledger_entries',
            'cms_user_logs', 'cms_sensitive_mutation_events'];
        $snapshot = fn () => collect($tables)->mapWithKeys(
            fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()]
        )->all();
        $before = $snapshot();
        $level = DB::transactionLevel();
        try {
            $attempt();
            $this->fail('Expected cancellation rejection.');
        } catch (\DomainException $exception) {
            $this->assertNotEmpty($exception->getMessage());
        }
        $this->assertSame($level, DB::transactionLevel());
        $this->assertSame($before, $snapshot());
    }

    public function test_one_exact_credit_self_audit_and_terminal_retry(): void
    {
        $result = $this->cancel();
        $this->assertSame(4, (int) $result->status);
        $this->assertSame('100.00000000', $this->wallet->fresh()->available_balance);
        $this->assertSame('100.00000000', (string) $this->member->fresh()->balance);
        $refund = WalletLedgerEntry::where('source_reference', $result->transactionId)
            ->where('entry_type', 'payout_request_released')->sole();
        $this->assertSame('12.34000000', $refund->amount);
        $this->assertSame($this->wallet->id, (int) $refund->wallet_account_id);
        $log = CmsUserLog::where('cms_user_id', $this->member->id)
            ->where('log_type', UserLogEnum::WITHDRAW)->sole();
        $this->assertSame($this->member->id, (int) $log->cms_operator_id);
        $this->assertStringContainsString('finance_withdraw_cancel', $log->title);
        $this->assertStringContainsString($result->transactionId, $log->description);
        $this->assertStringContainsString('TRY', $log->description);
        $this->assertRejected(fn () => $this->cancel());
        Http::assertNothingSent();
    }

    public function test_unpersisted_actor_is_unauthorized(): void
    {
        $this->assertRejected(fn () => $this->cancel(new CmsUser));
    }

    public function test_other_member_and_admin_have_no_owner_override(): void
    {
        $other = $this->member();
        $this->assertRejected(fn () => $this->cancel($other));
        $other->update(['role' => 'admin']);
        $this->assertRejected(fn () => $this->cancel($other));
    }

    public function test_stale_reference_is_rejected(): void
    {
        $this->assertRejected(fn () => $this->cancel(reference: 'unknown-reference'));
    }

    public function test_nonpending_and_nonwithdrawal_are_rejected(): void
    {
        foreach ([0, 1, 3, 4] as $status) {
            $this->withdrawal->update(['status' => $status]);
            $this->assertRejected(fn () => $this->cancel());
        }
        // Raw corruption fixture deliberately bypasses the immutable ORM guard.
        DB::table('cms_user_transactions')->where('id', $this->withdrawal->id)
            ->update(['status' => 2, 'action' => TransactionActionEnum::Deposit]);
        $this->assertRejected(fn () => $this->cancel());
    }

    public function test_dispatched_provider_execution_and_settlement_markers_reject(): void
    {
        foreach ([
            'provider_transaction_id' => 'synthetic-provider-ref',
            'payment_api_url' => 'https://example.invalid/never-called',
            'execution_wallet_account_id' => $this->wallet->id,
            'execution_currency' => 'TRY',
            'execution_context_snapshot' => ['stage' => 'execution_dispatched'],
            'settled_wallet_account_id' => $this->wallet->id,
            'settled_currency' => 'TRY',
        ] as $column => $value) {
            $this->withdrawal->refresh()->update([$column => $value]);
            $this->assertRejected(fn () => $this->cancel());
            $this->withdrawal->refresh()->update([$column => null]);
        }
    }

    public function test_execution_ledger_marker_alone_rejects(): void
    {
        foreach (['payout_execution_approved', 'payout_execution_completed'] as $type) {
            $marker = WalletLedgerEntry::create([
                'wallet_account_id' => $this->wallet->id, 'cms_user_id' => $this->member->id,
                'entry_type' => $type, 'direction' => 'debit', 'status' => 'applied',
                'asset' => 'TRY', 'amount' => '0', 'balance_before' => '87.66', 'balance_after' => '87.66',
                'source_type' => 'cms_user_transaction', 'source_reference' => $this->withdrawal->transactionId,
                'idempotency_key' => $type.':'.$this->withdrawal->transactionId,
            ]);
            $this->assertRejected(fn () => $this->cancel());
            $marker->delete();
        }
    }

    public function test_wrong_source_wallet_owner_is_rejected(): void
    {
        $other = $this->member();
        DB::table('wallet_accounts')->where('id', $this->wallet->id)->update(['cms_user_id' => $other->id]);
        $this->assertRejected(fn () => $this->cancel());
    }

    public function test_corrupted_original_debit_is_rejected(): void
    {
        $debit = WalletLedgerEntry::where('source_reference', $this->withdrawal->transactionId)->sole();
        foreach (['amount' => '12.35', 'balance_before' => '101.00', 'asset' => 'USD',
            'cms_user_id' => $this->member()->id] as $column => $value) {
            $original = $debit->getRawOriginal($column);
            $debit->update([$column => $value]);
            $this->assertRejected(fn () => $this->cancel());
            $debit->update([$column => $original]);
        }
    }

    public function test_missing_debit_and_corrupt_exact_snapshot_are_rejected(): void
    {
        DB::table('cms_user_transactions')->where('id', $this->withdrawal->id)->update(['exact_amount' => '12.35']);
        $this->assertRejected(fn () => $this->cancel());
        DB::table('cms_user_transactions')->where('id', $this->withdrawal->id)->update(['exact_amount' => '12.34']);
        WalletLedgerEntry::where('source_reference', $this->withdrawal->transactionId)->delete();
        $this->assertRejected(fn () => $this->cancel());
    }

    public function test_shifted_transaction_balances_do_not_authorize_cancellation(): void
    {
        DB::table('cms_user_transactions')->where('id', $this->withdrawal->id)->update([
            'before_balance' => '200.00', 'after_balance' => '187.66',
            'exact_before_balance' => '200.00', 'exact_after_balance' => '187.66',
        ]);
        $this->assertRejected(fn () => $this->cancel());
    }

    public function test_audit_failure_rolls_back_refund_status_and_settlement(): void
    {
        $count = WalletLedgerEntry::count();
        $logs = CmsUserLog::count();
        $beforeTransaction = $this->withdrawal->fresh()->getRawOriginal();
        $level = DB::transactionLevel();
        $dispatcher = CmsUserLog::getEventDispatcher();
        CmsUserLog::setEventDispatcher(clone $dispatcher);
        CmsUserLog::creating(static function (): void {
            throw new \RuntimeException('Synthetic audit persistence failure.');
        });
        try {
            try {
                $this->cancel();
                $this->fail('Audit failure must abort cancellation.');
            } catch (\RuntimeException $exception) {
                $this->assertSame('CMS user audit could not be persisted.', $exception->getMessage());
                $this->assertSame('Synthetic audit persistence failure.', $exception->getPrevious()?->getMessage());
            }
        } finally {
            CmsUserLog::setEventDispatcher($dispatcher);
        }
        $this->assertSame('87.66000000', $this->wallet->fresh()->available_balance);
        $this->assertSame('87.66000000', (string) $this->member->fresh()->balance);
        $this->assertSame(2, (int) $this->withdrawal->fresh()->status);
        $this->assertNull($this->withdrawal->fresh()->settled_wallet_account_id);
        $this->assertSame($count, WalletLedgerEntry::count());
        $this->assertSame($logs, CmsUserLog::count());
        $this->assertSame($beforeTransaction, $this->withdrawal->fresh()->getRawOriginal());
        $this->assertSame($level, DB::transactionLevel());
    }

    public function test_pinned_withdrawal_identity_cannot_be_rewritten_by_orm(): void
    {
        foreach (['cms_user_id' => $this->member()->id, 'transactionId' => 'forged-ref',
            'source_wallet_account_id' => $this->wallet->id + 1,
            'action' => TransactionActionEnum::Deposit, 'source_currency' => 'USD',
            'source_wallet_type' => 'crypto_asset_wallet', 'transaction_asset' => 'USD'] as $field => $value) {
            $transaction = $this->withdrawal->fresh();
            $original = $transaction->getRawOriginal($field);
            try {
                $transaction->update([$field => $value]);
                $this->fail('Pinned withdrawal identity must remain immutable.');
            } catch (\LogicException $exception) {
                $this->assertStringContainsString('Pinned withdrawal identity is immutable', $exception->getMessage());
            }
            $this->assertSame($original, $this->withdrawal->fresh()->getRawOriginal($field));
        }
        $this->assertSame('87.66000000', $this->wallet->fresh()->available_balance);
    }

    public function test_closed_finance_gate_prevents_refund(): void
    {
        DB::table('finance_reset_controls')->where('id', 1)->update(['writes_closed' => true]);
        $this->expectExceptionMessage('prelaunch_finance_writes_closed');
        try {
            $this->cancel();
        } finally {
            $this->assertSame('87.66000000', $this->wallet->fresh()->available_balance);
            $this->assertSame(2, (int) $this->withdrawal->fresh()->status);
        }
    }
}
