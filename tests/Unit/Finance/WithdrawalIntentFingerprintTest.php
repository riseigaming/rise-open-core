<?php

declare(strict_types=1);

namespace Tests\Unit\Finance;

use App\Services\Finance\WithdrawalIntentFingerprint;
use DomainException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// Direct source load: no application bootstrap, Laravel, DB or network.
require_once dirname(__DIR__, 3) . '/app/Services/Finance/WithdrawalIntentFingerprint.php';

final class WithdrawalIntentFingerprintTest extends TestCase
{
    private static function intent(): array
    {
        return [
            'tenant_namespace' => 'brand:test',
            'member_id' => 17,
            'source_wallet_id' => 29,
            'asset' => 'TRY',
            'requested_currency' => 'TRY',
            'amount' => '1.00',
            'beneficiary_name' => 'Synthetic Example',
            'beneficiary_target' => 'Synthetic:Target|A',
            'method_id' => 'provider:method',
            'request_key' => 'client-origin-key',
            'hmac_secret' => str_repeat('s', 32),
        ];
    }

    public function test_canonical_equivalence_determinism_and_input_order(): void
    {
        $helper = new WithdrawalIntentFingerprint();
        foreach (['1', '1.0', '1.00'] as $amount) {
            $input = array_replace(self::intent(), ['amount' => $amount]);
            $this->assertSame($helper->generate(self::intent()), $helper->generate($input));
            $this->assertSame($helper->generate($input), $helper->generate(array_reverse($input, true)));
        }
        $this->assertSame(
            $helper->generate(array_replace(self::intent(), ['amount' => '0.1'])),
            $helper->generate(array_replace(self::intent(), ['amount' => '0.10']))
        );
    }

    #[DataProvider('changedFields')]
    public function test_every_scope_and_intent_field_is_bound(array $changes): void
    {
        $helper = new WithdrawalIntentFingerprint();
        $this->assertNotSame(
            $helper->generate(self::intent())['fingerprint'],
            $helper->generate(array_replace(self::intent(), $changes))['fingerprint']
        );
    }

    public static function changedFields(): iterable
    {
        foreach ([
            'tenant_namespace' => 'brand:other', 'member_id' => 18,
            'source_wallet_id' => 30, 'amount' => '1.01',
            'beneficiary_name' => 'Synthetic Other', 'beneficiary_target' => 'Synthetic:Target|B',
            'method_id' => 'provider:other', 'request_key' => 'other-client-key',
            'hmac_secret' => str_repeat('t', 32),
        ] as $key => $value) {
            yield $key => [[$key => $value]];
        }
        yield 'USD asset and bound currency' => [['asset' => 'USD', 'requested_currency' => 'USD']];
        yield 'EUR asset and bound currency' => [['asset' => 'EUR', 'requested_currency' => 'EUR']];
        foreach (['beneficiary_name', 'beneficiary_target'] as $key) {
            yield $key . ' case' => [[$key => strtolower(self::intent()[$key])]];
            yield $key . ' whitespace' => [[$key => ' ' . self::intent()[$key] . ' ']];
        }
    }

    public function test_ordered_json_prevents_delimiter_and_escaping_collisions(): void
    {
        $helper = new WithdrawalIntentFingerprint();
        $a = array_replace(self::intent(), ['beneficiary_name' => 'A|B', 'beneficiary_target' => 'C']);
        $b = array_replace(self::intent(), ['beneficiary_name' => 'A', 'beneficiary_target' => 'B|C']);
        $this->assertNotSame($helper->generate($a), $helper->generate($b));
        $a['beneficiary_name'] = 'A","beneficiary_target":"C';
        $this->assertNotSame($helper->generate($a), $helper->generate($b));
    }

    public function test_fixed_private_output_and_versioned_hmac_contract(): void
    {
        $input = self::intent();
        $result = (new WithdrawalIntentFingerprint())->generate($input);
        $this->assertSame(['fingerprint', 'persistence_proof_required', 'customer_payout_allowed'], array_keys($result));
        $this->assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/', $result['fingerprint']);
        $this->assertTrue($result['persistence_proof_required']);
        $this->assertFalse($result['customer_payout_allowed']);
        $expected = '{"version":"rise.withdrawal-intent.v1","tenant_namespace":"brand:test","member_id":17,"source_wallet_id":29,"asset":"TRY","requested_currency":"TRY","amount":"1.00","beneficiary_name":"Synthetic Example","beneficiary_target":"Synthetic:Target|A","method_id":"provider:method","request_key":"client-origin-key"}';
        $this->assertSame(hash_hmac('sha256', $expected, $input['hmac_secret']), $result['fingerprint']);
        foreach (['beneficiary_name', 'beneficiary_target', 'request_key', 'hmac_secret'] as $key) {
            $this->assertStringNotContainsString($input[$key], json_encode($result, JSON_THROW_ON_ERROR));
        }
    }

    public function test_schema_boundaries_unicode_and_binary_secret(): void
    {
        foreach (['0.01', '9999999999999999.99'] as $amount) {
            $input = array_replace(self::intent(), [
                'amount' => $amount, 'member_id' => PHP_INT_MAX,
                'beneficiary_name' => 'Örnek Üye', 'hmac_secret' => str_repeat("\xFF\x00", 16),
            ]);
            $this->assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/', (new WithdrawalIntentFingerprint())->generate($input)['fingerprint']);
        }
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_inputs_throw_domain_exception_without_echo(mixed $input): void
    {
        try {
            (new WithdrawalIntentFingerprint())->generate($input);
            $this->fail('Invalid intent accepted.');
        } catch (DomainException $exception) {
            foreach (['beneficiary_name', 'beneficiary_target', 'request_key', 'hmac_secret'] as $key) {
                $this->assertStringNotContainsString(self::intent()[$key], $exception->getMessage());
            }
        }
    }

    public static function invalidInputs(): iterable
    {
        foreach ([null, false, 1, 'intent', new \stdClass(), []] as $index => $value) {
            yield 'not intent ' . $index => [$value];
        }
        yield 'unknown key' => [self::intent() + ['extra' => 'value']];
        yield 'list' => [array_values(self::intent())];
        foreach (array_keys(self::intent()) as $key) {
            $missing = self::intent();
            unset($missing[$key]);
            yield 'missing ' . $key => [$missing];
            foreach ([null, [], new \stdClass(), true, 1.0] as $index => $value) {
                yield $key . ' wrong type ' . $index => [array_replace(self::intent(), [$key => $value])];
            }
        }
        foreach (['member_id', 'source_wallet_id'] as $key) {
            foreach ([0, -1, '17', '1e2'] as $index => $value) {
                yield $key . ' invalid ' . $index => [array_replace(self::intent(), [$key => $value])];
            }
        }
        foreach (['tenant_namespace', 'beneficiary_name', 'beneficiary_target', 'method_id', 'request_key'] as $key) {
            foreach (['', '   ', "\xFF", 17] as $index => $value) {
                yield $key . ' invalid string ' . $index => [array_replace(self::intent(), [$key => $value])];
            }
        }
        foreach (['', '0', '0.0', '0.00', '-1', '+1', '01', '01.00', '.1', '1.', '1.000', '0.001',
            '1e2', '1E2', 'NaN', 'INF', '1,00', '1,000.00', ' 1', "1\n", '1 0',
            '10000000000000000', '10000000000000000.00', 1, 1.01] as $index => $amount) {
            yield 'amount ' . $index => [array_replace(self::intent(), ['amount' => $amount])];
        }
        foreach (['BTC', 'try', ' TRY', '', 17] as $index => $asset) {
            yield 'asset ' . $index => [array_replace(self::intent(), ['asset' => $asset, 'requested_currency' => $asset])];
        }
        foreach (['USD', 'EUR', 'try', 'TRY ', '', 17] as $index => $currency) {
            yield 'currency ' . $index => [array_replace(self::intent(), ['requested_currency' => $currency])];
        }
        foreach (['tenant_namespace' => 128, 'beneficiary_name' => 512,
            'beneficiary_target' => 128, 'method_id' => 128, 'request_key' => 128] as $field => $limit) {
            yield $field . ' oversized' => [array_replace(self::intent(), [$field => str_repeat('x', $limit + 1)])];
            yield $field . ' multibyte oversized' => [array_replace(self::intent(), [$field => str_repeat('Ö', $limit)])];
            foreach (["\x00", "\n", "\r", "\t", "\x7F", "\u{0085}", "\u{200B}", "\u{2028}"] as $index => $control) {
                yield $field . ' control ' . $index => [array_replace(self::intent(), [$field => 'A' . $control . 'B'])];
            }
        }
        yield 'amount oversized' => [array_replace(self::intent(), ['amount' => str_repeat('9', 129)])];
        yield 'valid UTF8 name within byte bound' => [array_replace(self::intent(), ['beneficiary_name' => str_repeat('Ö', 257)])];
        foreach (['', str_repeat('s', 31), str_repeat('s', 257), 32] as $index => $secret) {
            yield 'secret ' . $index => [array_replace(self::intent(), ['hmac_secret' => $secret])];
        }
    }
}
