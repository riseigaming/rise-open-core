<?php

namespace Tests\Unit\Finance;

use App\Enum\TransactionActionEnum;
use App\Models\CmsUserTransaction;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WithdrawalIdentityIntegrityTest extends TestCase
{
    private function transaction(array $original = [], bool $exists = true): CmsUserTransaction
    {
        $transaction = new CmsUserTransaction;
        $transaction->setRawAttributes(array_replace([
            'action' => TransactionActionEnum::Withdraw,
            'transactionId' => 'withdrawal-original',
            'cms_user_id' => 41,
            'source_wallet_account_id' => 73,
            'source_wallet_type' => 'primary_balance',
            'source_currency' => 'TRY',
            'transaction_asset' => 'TRY',
            'status' => 0,
        ], $original));
        $transaction->syncOriginal();
        $transaction->exists = $exists;

        return $transaction;
    }

    private function assertIdentity(CmsUserTransaction $transaction): void
    {
        (new \ReflectionMethod(CmsUserTransaction::class, 'assertWithdrawalIdentityUnchanged'))
            ->invoke($transaction);
    }

    public static function identityMutations(): iterable
    {
        yield 'reference replacement' => [['transactionId' => 'withdrawal-other']];
        yield 'reference removal' => [['transactionId' => null]];
        yield 'reference blank' => [['transactionId' => '']];
        yield 'reference whitespace' => [['transactionId' => ' ']];
        yield 'source wallet replacement' => [['source_wallet_account_id' => 74]];
        yield 'source wallet removal' => [['source_wallet_account_id' => null]];
        yield 'action replacement' => [['action' => TransactionActionEnum::Deposit]];
        yield 'owner replacement' => [['cms_user_id' => 42]];
        yield 'wallet type replacement' => [['source_wallet_type' => 'crypto_asset_wallet']];
        yield 'source asset replacement' => [['source_currency' => 'USD']];
        yield 'transaction asset replacement' => [['transaction_asset' => 'USD']];
        yield 'same update qualification bypass' => [[
            'transactionId' => 'withdrawal-other',
            'action' => TransactionActionEnum::Deposit,
            'source_wallet_account_id' => null,
        ]];
    }

    #[DataProvider('identityMutations')]
    public function test_pinned_withdrawal_identity_changes_are_rejected(array $changes): void
    {
        $transaction = $this->transaction();
        $transaction->fill($changes);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Pinned withdrawal identity is immutable:');
        $this->assertIdentity($transaction);
    }

    public function test_status_and_execution_settlement_metadata_remain_mutable(): void
    {
        $transaction = $this->transaction();
        $transaction->fill([
            'status' => 1,
            'provider_transaction_id' => 'provider-ack',
            'rejected_reason' => 'reviewed',
            'acceptor' => 12,
            'execution_wallet_account_id' => 73,
            'execution_currency' => 'TRY',
            'execution_context_snapshot' => ['stage' => 'approved'],
            'settled_wallet_account_id' => 73,
            'settled_currency' => 'TRY',
        ]);
        $this->assertIdentity($transaction);
        $this->assertTrue($transaction->isDirty('status'));
        $this->assertTrue($transaction->isDirty('settled_wallet_account_id'));
        $this->assertSame('withdrawal-original', $transaction->transactionId);
    }

    public static function unpinnedTransactions(): iterable
    {
        foreach ([TransactionActionEnum::Failed, TransactionActionEnum::Deposit, TransactionActionEnum::Promotion,
            TransactionActionEnum::Bet, TransactionActionEnum::Win, TransactionActionEnum::Rollback] as $action) {
            yield 'ordinary action '.$action => [['action' => $action], true];
        }
        yield 'new withdrawal' => [[], false];
        yield 'legacy missing wallet' => [['source_wallet_account_id' => null], true];
        yield 'legacy zero wallet' => [['source_wallet_account_id' => 0], true];
        yield 'legacy missing reference' => [['transactionId' => null], true];
        yield 'legacy blank reference' => [['transactionId' => '  '], true];
    }

    #[DataProvider('unpinnedTransactions')]
    public function test_unpinned_or_non_withdrawal_behavior_is_unchanged(array $original, bool $exists): void
    {
        $transaction = $this->transaction($original, $exists);
        $transaction->transactionId = 'new-reference';
        $this->assertIdentity($transaction);
        $this->assertSame('new-reference', $transaction->transactionId);
    }

    public function test_optional_missing_source_snapshots_can_be_populated(): void
    {
        $transaction = $this->transaction(['source_currency' => null, 'transaction_asset' => null]);
        $transaction->fill(['source_currency' => 'TRY', 'transaction_asset' => 'TRY']);
        $this->assertIdentity($transaction);
        $this->assertTrue($transaction->isDirty('source_currency'));
    }

    public function test_equivalent_database_string_ids_are_unchanged(): void
    {
        $transaction = $this->transaction(['action' => '2', 'source_wallet_account_id' => '73']);
        $transaction->fill(['action' => 2, 'source_wallet_account_id' => 73]);
        $this->assertIdentity($transaction);
        $this->assertFalse($transaction->isDirty(['action', 'source_wallet_account_id']));
    }

    public function test_updating_event_enforces_identity_and_preserves_crypto_guards(): void
    {
        // Fire only the ORM updating event: no save, application boot or SQL.
        $previousDispatcher = CmsUserTransaction::getEventDispatcher();
        $previousContainer = Container::getInstance();
        $container = new Container;
        $container->instance('config', new Repository);
        Container::setInstance($container);
        CmsUserTransaction::setEventDispatcher(new Dispatcher($container));

        try {
            $model = new class extends CmsUserTransaction {
                public function updatingWithoutDatabase(): void
                {
                    $this->fireModelEvent('updating');
                }
            };
            foreach ([
                [[], ['transactionId' => 'other'], 'Pinned withdrawal identity'],
                [[], ['crypto_deposit_context' => ['asset' => 'BTC']], 'Crypto deposit context'],
                [[
                    'action' => TransactionActionEnum::Deposit,
                    'crypto_deposit_context' => '{"asset":"BTC"}',
                    'transaction_asset' => 'BTC',
                    'transaction_precision_scale' => 8,
                ], ['transaction_asset' => 'LTC'], 'Pinned crypto deposit asset and precision'],
                [[
                    'action' => TransactionActionEnum::Deposit,
                    'crypto_deposit_context' => '{"asset":"BTC"}',
                    'transaction_precision_scale' => 8,
                ], ['transaction_precision_scale' => 2], 'Pinned crypto deposit asset and precision'],
            ] as [$original, $changes, $message]) {
                $model->setRawAttributes($this->transaction($original)->getAttributes());
                $model->syncOriginal();
                $model->exists = true;
                $model->fill($changes);
                try {
                    $model->updatingWithoutDatabase();
                    $this->fail('Updating event must reject immutable identity mutation.');
                } catch (\LogicException $exception) {
                    $this->assertStringContainsString($message, $exception->getMessage());
                }
            }

            $model->setRawAttributes($this->transaction()->getAttributes());
            $model->syncOriginal();
            $model->status = 1;
            $model->settled_wallet_account_id = 73;
            $model->updatingWithoutDatabase();
            $this->assertSame(1, $model->status);
        } finally {
            if ($previousDispatcher !== null) {
                CmsUserTransaction::setEventDispatcher($previousDispatcher);
            } else {
                CmsUserTransaction::unsetEventDispatcher();
            }
            Container::setInstance($previousContainer);
        }
    }
}
