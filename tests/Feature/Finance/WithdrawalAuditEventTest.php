<?php

namespace Tests\Feature\Finance;

use App\Enum\UserLogEnum;
use App\Helper\CmsUserLogService;
use App\Models\CmsUser;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** DBless: intercept only the audit model's query/create boundary. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class WithdrawalAuditEventTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        parent::setUp();
        $container = new Container;
        $container->instance('config', new Repository([
            'wallets' => ['precision' => ['fiat' => 2, 'assets' => ['BTC' => 8]]],
        ]));
        Container::setInstance($container);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        Container::setInstance(null);
        parent::tearDown();
    }

    public static function amounts(): array
    {
        return [
            'fiat exact large decimal' => ['TRY', '90071992547410.29', '0.71', '90071992547410,29', '0,71'],
            'crypto precision' => ['BTC', '0.00000001', '1.12345678', '0,00000001', '1,12345678'],
        ];
    }

    #[DataProvider('amounts')]
    public function test_withdraw_formats_only_standard_fields_and_preserves_actor(
        string $currency, string $amount, string $balance, string $formattedAmount, string $formattedBalance
    ): void {
        $user = $this->user(101, 'Member');
        $operator = $this->user(202, 'Operator');
        $writer = $this->writer();
        $writer->shouldReceive('create')->once()->with([
            'cms_user_id' => 101,
            'cms_operator_id' => 202,
            'title' => 'Çekim Talebi',
            'description' => "Tutar: {$formattedAmount} {$currency} | Bakiye: {$formattedBalance} | Ref TrxId: withdraw-fixture-1 | Operatör: Operator Fixture (#202)",
            'log_type' => UserLogEnum::WITHDRAW,
        ])->andReturnNull();

        CmsUserLogService::save($user, $operator, UserLogEnum::WITHDRAW, false, [
            'logTitle' => 'Çekim Talebi', 'amount' => $amount, 'balance' => $balance,
            'currency' => $currency, 'refTransactionId' => 'withdraw-fixture-1',
            'secret' => 'must-not-be-logged', 'iban' => 'must-not-be-logged',
            'actorLabel' => 'must-not-replace-authorized-operator',
        ]);
    }

    public function test_withdraw_audit_persistence_failure_is_not_swallowed(): void
    {
        $failure = new \RuntimeException('Injected audit persistence failure');
        $this->writer()->shouldReceive('create')->once()->andThrow($failure);

        try {
            CmsUserLogService::save($this->user(101, 'Member'), null, UserLogEnum::WITHDRAW, false, [
                'logTitle' => 'Çekim Talebi', 'amount' => '10', 'balance' => '90',
                'currency' => 'TRY', 'refTransactionId' => 'withdraw-fixture-2',
            ]);
            self::fail('Audit persistence failure must reach the caller.');
        } catch (\RuntimeException $exception) {
            self::assertSame('CMS user audit could not be persisted.', $exception->getMessage());
            self::assertSame($failure, $exception->getPrevious());
        }
    }

    public function test_other_unsupported_financial_events_remain_rejected(): void
    {
        $model = Mockery::mock('alias:App\\Models\\CmsUserLog');
        $model->shouldNotReceive('query');

        try {
            CmsUserLogService::save($this->user(101, 'Member'), null, UserLogEnum::DEPOSIT);
            self::fail('Unsupported events must remain fail-closed.');
        } catch (\RuntimeException $exception) {
            self::assertInstanceOf(\InvalidArgumentException::class, $exception->getPrevious());
        }
    }

    private function user(int $id, string $firstName): CmsUser
    {
        $user = new CmsUser;
        $user->setRawAttributes(['id' => $id, 'first_name' => $firstName, 'last_name' => 'Fixture']);

        return $user;
    }

    private function writer(): \Mockery\MockInterface
    {
        $model = Mockery::mock('alias:App\\Models\\CmsUserLog');
        $writer = Mockery::mock();
        $model->shouldReceive('query')->once()->andReturn($writer);

        return $writer;
    }
}
