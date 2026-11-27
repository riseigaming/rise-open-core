<?php

declare(strict_types=1);

namespace Tests\Unit\Finance;

use App\Services\Finance\NovaWithdrawalEnvelopeVerifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// Also supports an isolated PHPUnit run without Laravel or an application bootstrap.
require_once dirname(__DIR__, 3) . '/app/Services/Finance/NovaWithdrawalEnvelopeVerifier.php';

final class NovaWithdrawalEnvelopeVerifierTest extends TestCase
{
    private const NOW = 1794560400;
    private const SECRET = 'offline-fixture-only-not-a-real-key-0001';

    private static function body(): array
    {
        return [
            'partner_id' => 7, 'site_id' => 9, 'provider' => 'novagateway', 'type' => 'withdraw',
            'transactionId' => 'business-uuid', 'provider_transaction_id' => 'provider-ref',
            'amount' => '0012.34', 'currency' => 'BTC', 'status' => 'SUCCESS',
        ];
    }

    private static function binding(): array
    {
        return ['partner_id' => 7, 'site_id' => 9, 'secret' => self::SECRET];
    }

    private function signed(string $raw, mixed $binding = null, ?string $timestamp = null, int $age = 300, int $now = self::NOW): array
    {
        $timestamp ??= (string) self::NOW;
        return (new NovaWithdrawalEnvelopeVerifier())->verify(
            $raw, $timestamp, hash_hmac('sha256', $timestamp . '.' . $raw, self::SECRET),
            $binding ?? self::binding(), $age, $now,
        );
    }

    private function assertRejected(array $result): void
    {
        self::assertFalse($result['envelope_verified']);
        self::assertNull($result['observation']);
        self::assertTrue($result['db_proof_required']);
        self::assertSame(['envelope_verified', 'observation', 'reason_code', 'db_proof_required'], array_keys($result));
        self::assertStringNotContainsString(self::SECRET, json_encode($result, JSON_THROW_ON_ERROR));
    }

    public function test_authenticated_statuses_are_only_candidates_with_exact_amounts(): void
    {
        foreach (['SUCCESS', 'FAIL', 'CANCELLED'] as $status) {
            foreach (['0012.34' => '12.34000000', '0.00000001' => '0.00000001',
                '9999999999999999.99999999' => '9999999999999999.99999999', '1.0' => '1.00000000'] as $amount => $normalized) {
                $body = array_replace(self::body(), ['status' => $status, 'amount' => $amount]);
                $raw = json_encode($body, JSON_THROW_ON_ERROR);
                $result = $this->signed($raw);
                self::assertTrue($result['envelope_verified']);
                self::assertTrue($result['db_proof_required']);
                self::assertSame('candidate', $result['reason_code']);
                self::assertSame(['kind' => 'candidate'] + array_replace($body, ['amount' => $normalized])
                    + ['timestamp' => self::NOW], $result['observation']);
                foreach (['payout_allowed', 'paid', 'wallet_credit_authorized', 'secret', 'signature', 'raw'] as $key) {
                    self::assertArrayNotHasKey($key, $result);
                    self::assertArrayNotHasKey($key, $result['observation']);
                }
                self::assertStringNotContainsString(self::SECRET, json_encode($result, JSON_THROW_ON_ERROR));
                // Repetition stays a candidate. No replay store or financial claim exists here.
                self::assertSame($result, $this->signed($raw));
            }
        }
    }

    public static function invalidFields(): iterable
    {
        foreach ([
            'partner_id' => [8, '7', 7.0, null, true, [], 0, -1],
            'site_id' => [10, '9', 9.0, null, true, [], 0, -1],
            'provider' => ['NovaGateway', 'other', null, []],
            'type' => ['Withdraw', 'deposit', null, []],
            'status' => ['success', 'PENDING', 'UNKNOWN', 'FAIL ', '', null],
            'amount' => ['1e2', '-1', '+1', '0', '00.00000000', '10000000000000000',
                '0.000000001', '1.', '.1', ' 1', "1\n", 'NaN', 1, 1.5, null, [], false],
            'transactionId' => ['', ' ', str_repeat('x', 129), "id\x00", null, 42, []],
            'provider_transaction_id' => ['', ' ', str_repeat('x', 129), "id\n", null, 42, []],
            'currency' => ['', ' ', str_repeat('x', 129), "BTC\n", null, 42, []],
        ] as $key => $values) {
            foreach ($values as $i => $value) {
                yield "$key-$i" => [$key, $value];
            }
        }
    }

    #[DataProvider('invalidFields')]
    public function test_signed_malformed_or_mismatched_fields_are_rejected(string $key, mixed $value): void
    {
        $this->assertRejected($this->signed(json_encode(array_replace(self::body(), [$key => $value]), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION)));
    }

    public function test_strict_json_shape_size_and_duplicate_keys(): void
    {
        $valid = json_encode(self::body(), JSON_THROW_ON_ERROR);
        $missing = self::body();
        unset($missing['currency']);
        foreach (['', '{', 'null', '[]', 'true', '42', '[{}]', '{"x":{}}', "\xff", $valid . ' false',
            str_repeat(' ', 4097), json_encode($missing),
            json_encode(self::body() + ['secret' => 'untrusted-extra']),
            '{"partner_id":7,' . substr($valid, 1),
            '{"partner_id":8,' . substr($valid, 1),
            '{"partner_\u0069d":7,' . substr($valid, 1),
        ] as $raw) {
            $this->assertRejected($this->signed($raw));
        }
        // Byte cap is inclusive and applies to the original signed representation.
        $this->assertRejected($this->signed($valid . str_repeat(' ', 4097 - strlen($valid))));
        self::assertTrue($this->signed($valid . str_repeat(' ', 4096 - strlen($valid)))['envelope_verified']);
        $escaped = array_replace(self::body(), ['transactionId' => 'reference ": with \\ escapes']);
        self::assertTrue($this->signed(json_encode($escaped, JSON_THROW_ON_ERROR))['envelope_verified']);
    }

    public function test_malformed_or_untrusted_binding_has_no_fallback_or_coercion(): void
    {
        $raw = json_encode(self::body(), JSON_THROW_ON_ERROR);
        foreach ([false, 'binding', 7, new \stdClass(), [], self::body(),
            ['partner_id' => 7, 'site_id' => 9],
        ] as $binding) {
            $this->assertRejected($this->signed($raw, $binding));
        }
        foreach (['partner_id' => ['7', 7.0, 0, -1, null, true, []],
            'site_id' => ['9', 9.0, 0, -1, null, true, []],
            'secret' => ['', str_repeat('x', 31), null, 123, [], str_repeat('x', 32)],
            'provider_transaction_id' => [null, '', ' ', [], 12, str_repeat('x', 129)],
            'transactionId' => [null, '', ' ', [], 12, str_repeat('x', 129)],
        ] as $key => $values) {
            foreach ($values as $value) {
                $this->assertRejected($this->signed($raw, array_replace(self::binding(), [$key => $value])));
            }
        }
        $this->assertRejected($this->signed($raw, self::binding() + ['unexpected' => true]));
        $this->assertRejected((new NovaWithdrawalEnvelopeVerifier())->verify($raw, (string) self::NOW,
            hash_hmac('sha256', self::NOW . '.' . $raw, ''), [], 300, self::NOW));
    }

    public function test_optional_trusted_references_are_compared_exactly(): void
    {
        $raw = json_encode(self::body(), JSON_THROW_ON_ERROR);
        $pinned = self::binding() + ['transactionId' => 'business-uuid', 'provider_transaction_id' => 'provider-ref'];
        self::assertTrue($this->signed($raw, $pinned)['envelope_verified']);
        foreach (['provider_transaction_id' => ['other-ref', 'PROVIDER-REF'], 'transactionId' => ['other-business', 'BUSINESS-UUID']] as $key => $values) {
            foreach ($values as $value) {
                $this->assertRejected($this->signed($raw, array_replace($pinned, [$key => $value])));
            }
        }
    }

    public function test_signature_requires_exact_raw_bytes_header_case_and_secret(): void
    {
        $raw = json_encode(self::body(), JSON_THROW_ON_ERROR);
        $timestamp = (string) self::NOW;
        $good = hash_hmac('sha256', $timestamp . '.' . $raw, self::SECRET);
        foreach (['', str_repeat('a', 63), str_repeat('g', 64), strtoupper($good), $good . "\n", str_repeat('0', 64),
            hash_hmac('sha256', $raw, self::SECRET), hash_hmac('sha256', $timestamp . '.' . $raw, 'wrong-secret'),
        ] as $signature) {
            $this->assertRejected((new NovaWithdrawalEnvelopeVerifier())->verify($raw, $timestamp, $signature, self::binding(), 300, self::NOW));
        }
        $this->assertRejected((new NovaWithdrawalEnvelopeVerifier())->verify($raw . ' ', $timestamp, $good, self::binding(), 300, self::NOW));
        $this->assertRejected((new NovaWithdrawalEnvelopeVerifier())->verify($raw, (string) (self::NOW + 1), $good, self::binding(), 300, self::NOW));
        // Minimum secret size is accepted only with that exact caller-provided secret.
        $secret = str_repeat('z', 32);
        self::assertTrue((new NovaWithdrawalEnvelopeVerifier())->verify($raw, $timestamp,
            hash_hmac('sha256', $timestamp . '.' . $raw, $secret), array_replace(self::binding(), ['secret' => $secret]), 300, self::NOW)['envelope_verified']);
    }

    public function test_timestamp_format_and_symmetric_clamped_replay_window(): void
    {
        $raw = json_encode(self::body(), JSON_THROW_ON_ERROR);
        foreach (['', '179456040', '17945604000', '+794560400', '1794560400.0', '1e00000009',
            ' 1794560400', "1794560400\n", '１７９４５６０４００', '0000000000', '9999999999',
        ] as $timestamp) {
            $this->assertRejected($this->signed($raw, timestamp: $timestamp));
        }
        foreach ([PHP_INT_MIN, -1, 0, 1, 30, 300, 301, PHP_INT_MAX] as $age) {
            $window = min(300, max(1, $age));
            foreach ([-1, 1] as $direction) {
                self::assertTrue($this->signed($raw, timestamp: (string) (self::NOW + $direction * $window), age: $age)['envelope_verified']);
                $this->assertRejected($this->signed($raw, timestamp: (string) (self::NOW + $direction * ($window + 1)), age: $age));
            }
        }
        foreach ([PHP_INT_MIN, -1, PHP_INT_MAX] as $now) {
            $this->assertRejected($this->signed($raw, now: $now));
        }
    }
}
