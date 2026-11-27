<?php

namespace Tests\Feature\Finance;

use App\Models\CmsUserTransaction;
use App\Models\WalletAccount;
use App\Services\Finance\TransactionReconciliationSummaryService;
use Carbon\Carbon;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use PHPUnit\Framework\TestCase;

/**
 * DB-less fixtures, not the WinX destructive schema bootstrap or DB acceptance.
 * A real Eloquent query is compiled; only chunk delivery is replaced. No Laravel
 * application/env/provider boot, schema operations, DB reads/writes or HTTP calls.
 */
class TransactionReconciliationSummaryServiceTest extends TestCase
{
    private ?Container $previousContainer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousContainer = Container::getInstance();
        $container = new Container;
        $container->instance('config', new Repository(['wallets' => ['precision' => [
            'fiat' => 2, 'asset_default' => 8, 'assets' => ['BTC' => 8, 'LTC' => 8],
        ]]]));
        Container::setInstance($container);
    }

    protected function tearDown(): void
    {
        Container::setInstance($this->previousContainer);
        parent::tearDown();
    }

    public function test_exact_btc_totals_do_not_include_missing_snapshots_or_invent_repair_results(): void
    {
        $exact = $this->transaction('BTC', '0.12500001', 1, [
            'exact_precision_backfilled_at' => '2026-04-05 10:00:00',
        ]);
        $legacy = $this->transaction('BTC', '0.05000000', 1, ['exact_amount' => null]);
        $partial = $this->transaction('BTC', '0.03000000', 1, ['exact_after_balance' => null]);
        $before = array_map(fn ($row) => $row->getAttributes(), [$exact, $legacy, $partial]);
        $summary = $this->summarize([$exact, $legacy, $partial]);
        $overview = $summary['overview'];
        $row = $summary['transaction_blocks'][0]['rows'][0];

        self::assertSame('partial', $summary['status']);
        self::assertSame(3, $overview['scanned_count']);
        self::assertSame(1, $overview['exact_available_count']);
        self::assertSame(2, $overview['unclassified_count']);
        self::assertSame(1, $overview['repaired_count']);
        self::assertSame(33.33, $overview['exact_coverage_rate']);
        foreach (['recoverable_count', 'legacy_only_count', 'ambiguous_count', 'failed_count'] as $key) {
            self::assertNull($overview[$key]);
        }
        self::assertSame('0.12500001', $row['exact_total']);
        self::assertNull($row['summary_total']);
        self::assertNull($row['display_total']);
        self::assertSame($before, array_map(fn ($row) => $row->getAttributes(), [$exact, $legacy, $partial]));
    }

    public function test_assets_scales_categories_and_large_minor_unit_totals_stay_separate(): void
    {
        $summary = $this->summarize([
            $this->transaction('BTC', '99999999999.99999999'),
            $this->transaction('BTC', '0.00000001'),
            $this->transaction('LTC', '0.00000001'),
            $this->transaction('TRY', '250.00'),
            $this->transaction('TRY', '75.00', 2),
            $this->transaction('BTC', '1.00', 1, ['transaction_precision_scale' => 2]),
        ]);
        $blocks = $summary['transaction_blocks'];
        self::assertCount(4, $blocks);
        self::assertSame('100000000000.00000000', $blocks[0]['rows'][0]['exact_total']);
        self::assertSame(8, $blocks[0]['precision_scale']);
        self::assertSame('1.00', $blocks[1]['rows'][0]['exact_total']);
        self::assertSame(2, $blocks[1]['precision_scale']);
        self::assertSame('LTC', $blocks[2]['asset_or_currency']);
        self::assertSame('0.00000001', $blocks[2]['rows'][0]['exact_total']);
        self::assertSame('TRY', $blocks[3]['asset_or_currency']);
        self::assertSame('250.00', $blocks[3]['rows'][0]['display_total']);
        self::assertSame('payouts', $blocks[3]['rows'][1]['key']);
        self::assertSame('75.00', $blocks[3]['rows'][1]['exact_total']);
    }

    public function test_signed_string_arithmetic_handles_borrow_and_zero_without_float(): void
    {
        $summary = $this->summarize([
            $this->transaction('BTC', '10000000000.00000000'),
            $this->transaction('BTC', '-9999999999.99999999'),
            $this->transaction('TRY', '-1.00'),
            $this->transaction('TRY', '1.00'),
            $this->transaction('LTC', '-0.00000002'),
            $this->transaction('LTC', '0.00000001'),
        ]);
        self::assertSame('0.00000001', $summary['transaction_blocks'][0]['rows'][0]['exact_total']);
        self::assertSame('-0.00000001', $summary['transaction_blocks'][1]['rows'][0]['exact_total']);
        self::assertSame('0.00', $summary['transaction_blocks'][2]['rows'][0]['exact_total']);
    }

    public function test_pending_rejected_and_refund_records_are_not_claimed_as_applied_cashflow(): void
    {
        $summary = $this->summarize([
            $this->transaction('TRY', '5.00', 1, ['status' => 2]),
            $this->transaction('TRY', '3.00', 2, ['status' => 3]),
            $this->transaction('TRY', '3.00', 6, ['ref_transaction_id' => 'original-debit']),
        ]);
        $block = $summary['transaction_blocks'][0];
        self::assertSame(['deposits', 'payouts', 'gameplay_refunds'], array_column($block['rows'], 'key'));
        self::assertSame('transaction_snapshots_not_ledger_reconciliation', $summary['basis']);
        self::assertNull($block['exact_inflow_total']);
        self::assertNull($block['exact_outflow_total']);
        self::assertNull($block['exact_net_total']);
        self::assertContains('ledger_cashflow', $summary['unsupported']);
    }

    public function test_caller_owner_provider_filters_survive_on_clone_and_dates_only_narrow_query(): void
    {
        $query = $this->query([]);
        $query->where('cms_user_id', 41)->where('transactionId', 'like', 'external_games:second:%')
            ->where('status', 1)->orderByDesc('created_at');
        $before = [$query->toSql(), $query->getBindings()];
        $start = Carbon::parse('2026-04-04 00:00:00');
        $end = Carbon::parse('2026-04-04 23:59:59');
        $summary = (new TransactionReconciliationSummaryService)->summarize($query, [
            'period_start' => $start, 'period_end' => $end, 'transaction_scope' => 'descriptive_only',
        ]);
        self::assertSame($before, [$query->toSql(), $query->getBindings()]);
        $scan = $query->capture->query;
        self::assertSame([41, 'external_games:second:%', 1, $start, $end], $scan->getBindings());
        self::assertSame('cms_user_transactions.*', $scan->getQuery()->columns[0]);
        self::assertSame([['column' => 'cms_user_transactions.id', 'direction' => 'asc']], $scan->getQuery()->orders);
        self::assertSame('2026-04-04 00:00:00', $summary['period_start']);
        self::assertSame('2026-04-04 23:59:59', $summary['period_end']);
        self::assertNull($summary['conversion_blocks']);
        self::assertContains('conversions', $summary['unsupported']);
    }

    public function test_empty_and_legacy_only_sets_do_not_fake_zero_or_complete_reconciliation(): void
    {
        $empty = $this->summarize([]);
        self::assertSame([], $empty['transaction_blocks']);
        self::assertNull($empty['overview']['exact_coverage_rate']);
        self::assertNull($empty['conversion_blocks']);
        $legacy = $this->summarize([$this->transaction('BTC', '0.10', 1, ['exact_amount' => null])]);
        self::assertNull($legacy['transaction_blocks'][0]['rows'][0]['exact_total']);
        self::assertNull($legacy['transaction_blocks'][0]['rows'][0]['display_total']);
        self::assertSame(0.0, $legacy['overview']['exact_coverage_rate']);
    }

    public function test_totals_continue_across_multiple_source_chunks(): void
    {
        $records = [];
        for ($id = 1; $id <= 401; $id++) {
            $records[] = $this->transaction('BTC', '0.00000001', 1, ['id' => $id]);
        }
        $summary = $this->summarize($records);
        self::assertSame(401, $summary['overview']['scanned_count']);
        self::assertSame('0.00000401', $summary['transaction_blocks'][0]['rows'][0]['exact_total']);
    }

    public function test_invalid_precision_fails_instead_of_returning_a_zero_total(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->summarize([$this->transaction('BTC', '0.10', 1, ['transaction_precision_scale' => 19])]);
    }

    public function test_joined_query_is_rejected_before_any_scan(): void
    {
        $query = $this->query([])->join('wallet_ledger_entries', 'cms_user_transactions.id', '=', 'wallet_ledger_entries.id');
        $this->expectException(\InvalidArgumentException::class);
        (new TransactionReconciliationSummaryService)->summarize($query);
    }

    public function test_wrong_model_is_rejected_before_any_scan(): void
    {
        $query = $this->query([])->setModel(new WalletAccount);
        $this->expectException(\InvalidArgumentException::class);
        (new TransactionReconciliationSummaryService)->summarize($query);
    }

    public function test_invalid_date_filter_is_rejected_instead_of_widening_scope(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new TransactionReconciliationSummaryService)->summarize($this->query([]), ['period_start' => '2026-04-04']);
    }

    public function test_reversed_date_filter_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new TransactionReconciliationSummaryService)->summarize($this->query([]), [
            'period_start' => Carbon::parse('2026-04-05'), 'period_end' => Carbon::parse('2026-04-04'),
        ]);
    }

    private function transaction(string $asset, string $amount, int $action = 1, array $overrides = []): CmsUserTransaction
    {
        $transaction = new CmsUserTransaction;
        $transaction->setDateFormat('Y-m-d H:i:s');
        $transaction->setRawAttributes(array_replace([
            'id' => 1, 'cms_user_id' => 41, 'transactionId' => 'fixture-only',
            'action' => $action, 'status' => 1, 'currency' => $asset, 'transaction_asset' => $asset,
            'transaction_precision_scale' => in_array($asset, ['BTC', 'LTC'], true) ? 8 : 2,
            'amount' => $amount, 'exact_amount' => $amount,
            'exact_before_balance' => '0.00000000', 'exact_after_balance' => $amount,
            'exact_precision_backfilled_at' => null,
        ], $overrides), true);
        return $transaction;
    }

    private function query(array $records): ReconciliationFixtureQuery
    {
        // The PDO resolver is a tripwire, never a real connection.
        $connection = new Connection(static function () {
            throw new \LogicException('DB access is forbidden in this fixture test.');
        });
        $query = new ReconciliationFixtureQuery($connection->query());
        $query->setModel(new CmsUserTransaction);
        $query->records = $records;
        $query->capture = new \stdClass;
        return $query;
    }

    private function summarize(array $records): array
    {
        return (new TransactionReconciliationSummaryService)->summarize($this->query($records));
    }
}

/** Only supplies pre-scoped fixture rows; SQL execution/date selection is NOT_RUN. */
class ReconciliationFixtureQuery extends Builder
{
    public array $records = [];
    public \stdClass $capture;

    public function chunkById($count, callable $callback, $column = null, $alias = null)
    {
        TestCase::assertSame(200, $count);
        TestCase::assertSame('cms_user_transactions.id', $column);
        TestCase::assertSame('id', $alias);
        $this->capture->query = $this;
        foreach (array_chunk($this->records, $count) as $chunk) {
            $callback(collect($chunk));
        }
        return true;
    }
}
