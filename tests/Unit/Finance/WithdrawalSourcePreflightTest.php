<?php

declare(strict_types=1);

namespace Tests\Unit\Finance;

use App\Services\Finance\WithdrawalSourcePreflight;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class WithdrawalSourcePreflightTest extends TestCase
{
    private function values(array $overrides = []): array
    {
        return array_replace([
            'wallet_type' => 'primary_balance', 'asset' => 'TRY', 'network' => null,
            'requested_currency' => 'TRY', 'amount' => '0.01',
            'precision_scale' => 2, 'available_balance' => '100.00',
        ], $overrides);
    }

    #[DataProvider('invalidSources')]
    public function test_source_rejections(array $overrides, string $reason): void
    {
        $result = (new WithdrawalSourcePreflight())->validate(...$this->values($overrides));
        self::assertSame(['valid' => false, 'reason_code' => $reason], $result['source_validation']);
        self::assertFalse($result['customer_payout_allowed']);
    }

    public static function invalidSources(): iterable
    {
        foreach (['', 'bad', '-1', '-0.01', '0', '0.00', '0.001', '1e2', ' 1', '1 ', '+1', '.1', '1.', '1,00', 'NaN', 'INF', 0.01, 1, true, null, []] as $amount) {
            yield [['amount' => $amount], 'invalid_amount'];
        }
        foreach (['', '-1', 'bad', '0.001', '1e2', ' 1', 1.0, 1, null, false, []] as $balance) {
            yield [['available_balance' => $balance], 'invalid_available_balance'];
        }
        foreach (['amount', 'available_balance'] as $field) {
            yield [[$field => '10000000000000000.00'], $field === 'amount' ? 'invalid_amount' : 'invalid_available_balance'];
            yield [[$field => str_repeat('9', 129)], $field === 'amount' ? 'invalid_amount' : 'invalid_available_balance'];
        }
        foreach ([-1, 0, 1, 3, 8, 9, '2', 2.0, null] as $precision) {
            yield [['precision_scale' => $precision], 'invalid_precision'];
        }
        yield [['wallet_type' => ''], 'unsupported_wallet_type'];
        yield [['wallet_type' => 'bonus'], 'unsupported_wallet_type'];
        yield [['asset' => ''], 'unsupported_source_currency'];
        yield [['asset' => 'GBP'], 'unsupported_source_currency'];
        yield [['requested_currency' => ''], 'unsupported_payout_currency'];
        yield [['requested_currency' => 'GBP'], 'unsupported_payout_currency'];
        yield [['requested_currency' => 'USD'], 'conversion_required_manual_review'];
        yield [['available_balance' => '0.00'], 'insufficient_source_balance'];
        yield [['network' => 'UNKNOWN'], 'unsupported_network'];
        yield [['network' => 'BTC'], 'unsupported_network'];
        yield [['network' => ' '], 'unsupported_network'];
        foreach (['BTC' => 'BTC', 'LTC' => 'LTC', 'USDT' => 'TRC20'] as $asset => $network) {
            foreach ([$network, 'UNKNOWN', null] as $rail) {
                yield [['wallet_type' => 'crypto_asset_wallet', 'asset' => $asset, 'network' => $rail, 'requested_currency' => $asset], 'crypto_withdrawals_unavailable'];
            }
            yield [['asset' => $asset], 'crypto_withdrawals_unavailable'];
        }
    }

    public function test_valid_fiat_sources_remain_ineligible_for_customer_payout(): void
    {
        foreach (['TRY', 'USD', 'EUR'] as $asset) {
            $values = $this->values(['asset' => $asset, 'requested_currency' => $asset, 'network' => '', 'amount' => '100.000']);
            $before = $values;
            $result = (new WithdrawalSourcePreflight())->validate(...$values);
            self::assertSame(['valid' => true, 'reason_code' => null], $result['source_validation']);
            self::assertSame('100.00', $result['normalized_amount']);
            self::assertFalse($result['customer_payout_allowed']);
            self::assertSame($before, $values);
        }
    }

    public function test_comparison_and_normalization_are_exact_beyond_float_precision(): void
    {
        $preflight = new WithdrawalSourcePreflight();
        $values = $this->values(['amount' => '9007199254740993.01', 'available_balance' => '9007199254740993.00']);
        self::assertSame('insufficient_source_balance', $preflight->validate(...$values)['source_validation']['reason_code']);
        $values['available_balance'] = '9007199254740993.01';
        $result = $preflight->validate(...$values);
        self::assertTrue($result['source_validation']['valid']);
        self::assertSame($values['amount'], $result['normalized_amount']);
        self::assertFalse($result['customer_payout_allowed']);
        $upper = $preflight->validate(...$this->values([
            'amount' => '9999999999999999.99', 'available_balance' => '9999999999999999.99',
        ]));
        self::assertTrue($upper['source_validation']['valid']);
        self::assertFalse($upper['customer_payout_allowed']);
        $result = $preflight->validate(...$this->values(['precision_scale' => 8, 'amount' => '0.00000001']));
        self::assertSame('invalid_precision', $result['source_validation']['reason_code']);
        self::assertFalse($result['customer_payout_allowed']);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_runs_without_application_database_or_external_services(): void
    {
        // In the DB-less invocation no application is booted. Any attempted
        // model/facade/provider dependency load fails immediately.
        self::assertFalse(class_exists(\Illuminate\Foundation\Application::class, false));
        self::assertFalse(class_exists(\Illuminate\Support\Facades\DB::class, false));
        $guard = static function (string $class): void {
            if (str_starts_with($class, 'Illuminate\\') || str_starts_with($class, 'App\\')) {
                throw new \LogicException('Unexpected application dependency: ' . $class);
            }
        };
        class_exists(WithdrawalSourcePreflight::class);
        spl_autoload_register($guard, true, true);
        try {
            $preflight = new WithdrawalSourcePreflight();
            self::assertTrue($preflight->validate(...$this->values())['source_validation']['valid']);
            foreach (self::invalidSources() as [$overrides, $reason]) {
                self::assertSame($reason, $preflight->validate(...$this->values($overrides))['source_validation']['reason_code']);
            }
            self::assertSame([], (new \ReflectionClass($preflight))->getProperties());
            self::assertNull((new \ReflectionClass($preflight))->getConstructor());
            self::assertFalse(class_exists(\Illuminate\Support\Facades\DB::class, false));
        } finally {
            spl_autoload_unregister($guard);
        }
    }
}
