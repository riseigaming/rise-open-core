<?php

namespace Tests\Unit\InternalGames;

use App\Exceptions\InternalGames\RouletteException;
use App\Services\InternalGames\RouletteTable;
use PHPUnit\Framework\TestCase;

class RouletteTableTest extends TestCase
{
    private RouletteTable $table;

    protected function setUp(): void
    {
        parent::setUp();

        $this->table = new RouletteTable();
    }

    public function test_all_supported_bet_types_normalize_and_settle_correctly(): void
    {
        $cases = [
            ['betType' => 'straight', 'betKey' => '17', 'stake' => 10, 'winning' => 17, 'return' => 360.0],
            ['betType' => 'split', 'betKey' => '17-20', 'stake' => 10, 'winning' => 20, 'return' => 180.0],
            ['betType' => 'street', 'betKey' => '16-17-18', 'stake' => 10, 'winning' => 18, 'return' => 120.0],
            ['betType' => 'corner', 'betKey' => '17-18-20-21', 'stake' => 10, 'winning' => 21, 'return' => 90.0],
            ['betType' => 'six_line', 'betKey' => '16-17-18-19-20-21', 'stake' => 10, 'winning' => 20, 'return' => 60.0],
            ['betType' => 'dozen', 'betKey' => '2', 'stake' => 10, 'winning' => 17, 'return' => 30.0],
            ['betType' => 'column', 'betKey' => '2', 'stake' => 10, 'winning' => 17, 'return' => 30.0],
            ['betType' => 'red_black', 'betKey' => 'black', 'stake' => 10, 'winning' => 17, 'return' => 20.0],
            ['betType' => 'odd_even', 'betKey' => 'odd', 'stake' => 10, 'winning' => 17, 'return' => 20.0],
            ['betType' => 'low_high', 'betKey' => 'low', 'stake' => 10, 'winning' => 17, 'return' => 20.0],
        ];

        foreach ($cases as $case) {
            $lines = $this->table->normalizeBets([[
                'betType' => $case['betType'],
                'betKey' => $case['betKey'],
                'stake' => $case['stake'],
            ]]);

            $settlement = $this->table->settleLines($lines, $case['winning']);

            $this->assertSame($case['winning'], $settlement['winning_number']);
            $this->assertSame($case['return'], $settlement['total_return']);
            $this->assertSame('won', $settlement['line_results'][0]['status']);
            $this->assertSame($case['return'], $settlement['line_results'][0]['payout_amount']);
        }
    }

    public function test_invalid_geometries_are_rejected(): void
    {
        $invalidCases = [
            ['betType' => 'split', 'betKey' => '1-5'],
            ['betType' => 'street', 'betKey' => '0-1-2'],
            ['betType' => 'corner', 'betKey' => '0-1-2-3'],
            ['betType' => 'six_line', 'betKey' => '1-2-3-5-6-7'],
            ['betType' => 'split', 'betKey' => '0-1'],
        ];

        foreach ($invalidCases as $invalidCase) {
            try {
                $this->table->normalizeBets([[
                    'betType' => $invalidCase['betType'],
                    'betKey' => $invalidCase['betKey'],
                    'stake' => 10,
                ]]);

                $this->fail('Expected invalid roulette geometry to be rejected.');
            } catch (RouletteException $exception) {
                $this->assertSame(422, $exception->statusCode());
            }
        }
    }

    public function test_zero_behavior_clears_even_money_bets(): void
    {
        $lines = $this->table->normalizeBets([
            ['betType' => 'red_black', 'betKey' => 'red', 'stake' => 10],
            ['betType' => 'odd_even', 'betKey' => 'odd', 'stake' => 10],
            ['betType' => 'low_high', 'betKey' => 'high', 'stake' => 10],
        ]);

        $settlement = $this->table->settleLines($lines, 0);

        $this->assertSame('green', $settlement['winning_color']);
        $this->assertSame(0.0, $settlement['total_return']);
        $this->assertSame(-30.0, $settlement['net']);
        $this->assertSame(['lost', 'lost', 'lost'], array_column($settlement['line_results'], 'status'));
    }

    public function test_client_supplied_covered_numbers_must_match_canonical_shape(): void
    {
        $this->expectException(RouletteException::class);

        $this->table->normalizeBets([[
            'betType' => 'column',
            'betKey' => '1',
            'stake' => 10,
            'coveredNumbers' => [1, 2, 3],
        ]]);
    }

    public function test_alias_bet_types_are_normalized_to_canonical_internal_types(): void
    {
        $lines = $this->table->normalizeBets([
            ['betType' => 'straight up', 'betKey' => '17', 'stake' => 10],
            ['betType' => 'six line', 'betKey' => '16-17-18-19-20-21', 'stake' => 10],
            ['betType' => 'red/black', 'betKey' => 'black', 'stake' => 10],
            ['betType' => 'odd/even', 'betKey' => 'odd', 'stake' => 10],
            ['betType' => 'low/high', 'betKey' => 'low', 'stake' => 10],
        ]);

        $this->assertSame(
            ['straight', 'six_line', 'red_black', 'odd_even', 'low_high'],
            array_column($lines, 'bet_type')
        );
    }

    public function test_duplicate_lines_and_over_precise_stakes_are_rejected(): void
    {
        try {
            $this->table->normalizeBets([
                ['betType' => 'straight', 'betKey' => '17', 'stake' => '10.123'],
            ]);

            $this->fail('Expected invalid stake precision to be rejected.');
        } catch (RouletteException $exception) {
            $this->assertSame('ROULETTE_INVALID_STAKE_FORMAT', $exception->errorCode());
        }

        try {
            $this->table->normalizeBets([
                ['betType' => 'straight', 'betKey' => '17', 'stake' => 10],
                ['betType' => 'straight', 'betKey' => '17', 'stake' => 5],
            ]);

            $this->fail('Expected duplicate roulette lines to be rejected.');
        } catch (RouletteException $exception) {
            $this->assertSame('ROULETTE_DUPLICATE_BET_LINE', $exception->errorCode());
        }
    }

    public function test_bet_list_size_is_capped(): void
    {
        $bets = [];
        for ($number = 1; $number <= 65; $number++) {
            $bets[] = [
                'betType' => 'straight',
                'betKey' => (string) (($number - 1) % 36 + 1),
                'stake' => 1,
            ];
        }

        try {
            $this->table->normalizeBets($bets);
            $this->fail('Expected oversized bet list to be rejected.');
        } catch (RouletteException $exception) {
            $this->assertSame('ROULETTE_BET_LIST_TOO_LARGE', $exception->errorCode());
        }
    }
}
