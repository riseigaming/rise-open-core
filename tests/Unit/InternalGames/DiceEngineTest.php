<?php

namespace Tests\Unit\InternalGames;

use App\Services\InternalGames\DiceEngine;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class DiceEngineTest extends TestCase
{
    public function test_vfair_unsigned_hash_mapping_vectors_are_deterministic(): void
    {
        $engine = new DiceEngine();
        foreach (['00000000' => 0, '80000000' => 5000, '7fffffff' => 4999, 'ffffffff' => 9999] as $hash => $expected) {
            $this->assertSame($expected, $engine->rollFromHash((string) $hash));
            $this->assertSame($expected, $engine->rollFromHash($hash . str_repeat('a', 56)));
        }
    }

    public function test_under_is_strict_and_over_includes_equality(): void
    {
        $engine = new DiceEngine();
        $this->assertTrue($engine->settle(100, 'over', 5000, 5000)['won']);
        $this->assertFalse($engine->settle(100, 'under', 5000, 5000)['won']);
        $this->assertTrue($engine->settle(100, 'under', 5000, 4999)['won']);
        $this->assertFalse($engine->settle(100, 'over', 5000, 4999)['won']);
    }

    public function test_all_ten_thousand_buckets_match_probability_and_never_exceed_rtp(): void
    {
        $engine = new DiceEngine();
        foreach (['under', 'over'] as $mode) {
            foreach ([100, 5000, 9700] as $chance) {
                $target = $mode === 'under' ? $chance : 10000 - $chance;
                $wins = 0;
                $returns = 0;
                for ($roll = 0; $roll < 10000; $roll++) {
                    $result = $engine->settle(123, $mode, $target, $roll);
                    $wins += (int) $result['won'];
                    $returns += $result['payoutMinor'];
                }
                $this->assertSame($chance, $wins);
                $this->assertLessThanOrEqual(123 * 9800, $returns);
                $this->assertSame($engine->odds($mode, $target)['multiplier'], $engine->odds($mode === 'over' ? 'under' : 'over', 10000 - $target)['multiplier']);
            }
        }
    }

    public function test_payout_is_gross_return_floored_once_in_minor_units(): void
    {
        $engine = new DiceEngine();
        $this->assertSame(1.96, $engine->odds('over', 5000)['multiplier']);
        $this->assertSame(39, $engine->payoutMinor(20, 'over', 5000));
        $this->assertSame(0, $engine->settle(20, 'over', 5000, 100)['payoutMinor']);
        $this->assertSame(980000, $engine->payoutMinor(10000, 'under', 100));
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_stakes_modes_targets_and_results_are_rejected(int $stake, string $mode, int $target, int $roll): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new DiceEngine())->settle($stake, $mode, $target, $roll);
    }

    public static function invalidInputs(): array
    {
        return [[0, 'over', 5000, 1], [10001, 'over', 5000, 1], [100, 'fake', 5000, 1],
            [100, 'under', 99, 1], [100, 'under', 9701, 1], [100, 'over', 299, 1],
            [100, 'over', 9901, 1], [100, 'over', 5000, -1], [100, 'over', 5000, 10000]];
    }
}
