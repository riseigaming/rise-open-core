<?php

namespace Tests\Feature\Finance;

use App\Models\CmsUserTransaction;
use App\Services\Finance\PayoutRoutingService;
use App\Services\Wallet\AssetWalletService;
use App\Services\Wallet\SpendableWalletService;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase;

/**
 * New DB-less tests for the read-model subset adapted from WinX payout at S0
 * 1c151f21af58c1cc738f67f9e7a10953e6a6e6a2. No Laravel application boot,
 * schema helpers or provider fixture: S0's request/debit/dispatch and Rise's
 * refund/concurrency integration tests remain NOT_RUN without an enrolled DB.
 */
class PayoutRoutingFlowTest extends TestCase
{
    private ?ConnectionResolverInterface $previousResolver;
    private Container $previousContainer;
    private PayoutRoutingService $routing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousContainer = Container::getInstance();
        $container = new Container();
        $container->instance('config', new Repository());
        Container::setInstance($container);
        $this->previousResolver = Model::getConnectionResolver();
        Model::setConnectionResolver(new class implements ConnectionResolverInterface {
            public function connection($name = null) { throw new \LogicException('DB access forbidden in payout read-model test.'); }
            public function getDefaultConnection() { throw new \LogicException('DB access forbidden.'); }
            public function setDefaultConnection($name) { throw new \LogicException('DB access forbidden.'); }
        });
        $assets = $this->createMock(AssetWalletService::class);
        $assets->expects($this->never())->method($this->anything());
        $spendable = $this->createMock(SpendableWalletService::class);
        $spendable->expects($this->never())->method($this->anything());
        $this->routing = new PayoutRoutingService($assets, $spendable);
    }

    protected function tearDown(): void
    {
        if ($this->previousResolver !== null) {
            Model::setConnectionResolver($this->previousResolver);
        } else {
            Model::unsetConnectionResolver();
        }
        Container::setInstance($this->previousContainer);
        parent::tearDown();
    }

    public function test_missing_pins_are_unknown_even_for_a_completed_legacy_transaction(): void
    {
        $transaction = new CmsUserTransaction(['currency' => 'TRY', 'amount' => '25.00', 'status' => 0]);
        $detail = $this->routing->detailForTransaction($transaction);

        foreach (['source_wallet_id', 'source_wallet_type', 'source_currency', 'payout_currency',
            'route_type', 'conversion_required', 'conversion_policy', 'execution_wallet_id',
            'execution_currency', 'final_wallet_id', 'final_currency', 'exact_amount'] as $key) {
            $this->assertNull($detail[$key], $key);
        }
        $this->assertFalse($detail['exact_available']);
    }

    public function test_pinned_source_is_preserved_without_loading_child_or_owner_relations(): void
    {
        $transaction = new CmsUserTransaction([
            'cms_user_id' => 82, 'currency' => 'USD', 'source_wallet_account_id' => 17,
            'source_wallet_type' => 'primary_balance', 'source_currency' => 'EUR',
            'requested_payout_currency' => 'EUR', 'payout_route_type' => 'primary_same_currency',
            'payout_requires_conversion' => false, 'payout_conversion_policy' => 'none',
        ]);
        $before = $transaction->getAttributes();
        $detail = $this->routing->detailForTransaction($transaction);

        $this->assertSame(17, $detail['source_wallet_id']);
        $this->assertSame('primary_balance', $detail['source_wallet_type']);
        $this->assertSame('EUR', $detail['source_currency']);
        $this->assertSame('EUR', $detail['payout_currency']);
        $this->assertFalse($detail['conversion_required']);
        $this->assertSame('primary_same_currency', $detail['route_type']);
        $this->assertNull($detail['final_currency']);
        $this->assertSame($before, $transaction->getAttributes());
        $this->assertSame([], $transaction->getRelations());
    }

    public function test_cross_currency_refund_displays_stored_source_settlement_separately(): void
    {
        $transaction = new CmsUserTransaction([
            'source_wallet_account_id' => 17, 'source_currency' => 'EUR',
            'requested_payout_currency' => 'USD', 'payout_requires_conversion' => true,
            'payout_route_type' => 'manual_conversion_review', 'payout_conversion_policy' => 'manual_review',
            'payout_route_reason' => 'conversion_required_manual_review',
            'settled_wallet_account_id' => 17, 'settled_currency' => 'EUR',
        ]);
        $detail = $this->routing->detailForTransaction($transaction);

        $this->assertSame('USD', $detail['payout_currency']);
        $this->assertTrue($detail['conversion_required']);
        $this->assertSame('manual_review', $detail['conversion_policy']);
        $this->assertSame('conversion_required_manual_review', $detail['route_reason']);
        $this->assertSame(17, $detail['final_wallet_id']);
        $this->assertSame('EUR', $detail['final_currency']);
        $this->assertNull($detail['execution_currency']);
    }

    public function test_crypto_snapshot_retains_eight_decimal_precision_without_a_float_roundtrip(): void
    {
        $transaction = new CmsUserTransaction([
            'transaction_asset' => 'BTC', 'transaction_precision_scale' => 8,
            'source_wallet_type' => 'crypto_asset_wallet', 'source_currency' => 'BTC',
            'requested_payout_currency' => 'BTC', 'exact_amount' => '0.00000001',
            'exact_before_balance' => '9999999999999999.99999999',
            'exact_after_balance' => '9999999999999999.99999998',
        ]);
        $detail = $this->routing->detailForTransaction($transaction);

        $this->assertSame('0.00000001', $detail['exact_amount']);
        $this->assertSame('9999999999999999.99999999', $detail['exact_before_balance']);
        $this->assertSame('9999999999999999.99999998', $detail['exact_after_balance']);
        $this->assertTrue($detail['exact_available']);
        $this->assertNull($detail['route_type']);
    }

    public function test_preserved_refund_gate_rejects_nonlocal_execution_before_database_access(): void
    {
        Container::setInstance(new class extends Container {
            public function environment($environments = null): bool { return false; }
        });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Synthetic isolated local finance profile required.');

        $this->routing->refundToSource(new CmsUserTransaction());
    }
}
