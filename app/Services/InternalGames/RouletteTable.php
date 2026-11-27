<?php

namespace App\Services\InternalGames;

use App\Exceptions\InternalGames\RouletteException;

class RouletteTable
{
    public const MAX_BET_LINES = 64;

    private const BET_TYPE_ALIASES = [
        'straight' => 'straight',
        'straight up' => 'straight',
        'straight_up' => 'straight',
        'split' => 'split',
        'street' => 'street',
        'corner' => 'corner',
        'six line' => 'six_line',
        'six_line' => 'six_line',
        'sixline' => 'six_line',
        'dozen' => 'dozen',
        'column' => 'column',
        'red/black' => 'red_black',
        'red_black' => 'red_black',
        'redblack' => 'red_black',
        'odd/even' => 'odd_even',
        'odd_even' => 'odd_even',
        'oddeven' => 'odd_even',
        'low/high' => 'low_high',
        'low_high' => 'low_high',
        'lowhigh' => 'low_high',
    ];

    private const RED_NUMBERS = [
        1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36,
    ];

    private const BLACK_NUMBERS = [
        2, 4, 6, 8, 10, 11, 13, 15, 17, 20, 22, 24, 26, 28, 29, 31, 33, 35,
    ];

    private const PAYOUTS = [
        'straight' => 35,
        'split' => 17,
        'street' => 11,
        'corner' => 8,
        'six_line' => 5,
        'dozen' => 2,
        'column' => 2,
        'red_black' => 1,
        'odd_even' => 1,
        'low_high' => 1,
    ];

    public function normalizeBets(array $bets): array
    {
        if ($bets === [] || array_values($bets) !== $bets) {
            throw new RouletteException(422, 'ROULETTE_INVALID_REQUEST', 'At least one roulette bet is required.');
        }

        if (count($bets) > self::MAX_BET_LINES) {
            throw new RouletteException(422, 'ROULETTE_BET_LIST_TOO_LARGE', 'Too many roulette bet lines were submitted in a single request.');
        }

        $normalized = [];
        $seenLines = [];
        foreach ($bets as $index => $bet) {
            if (!is_array($bet)) {
                throw new RouletteException(422, 'ROULETTE_INVALID_REQUEST', 'Each roulette bet must be an object.');
            }

            $betType = $this->normalizeBetType((string) ($bet['betType'] ?? $bet['bet_type'] ?? ''));
            $betKey = trim((string) ($bet['betKey'] ?? $bet['bet_key'] ?? ''));
            $stake = $bet['stake'] ?? $bet['stakeAmount'] ?? $bet['stake_amount'] ?? null;

            if ($betType === '' || $betKey === '') {
                throw new RouletteException(422, 'ROULETTE_INVALID_REQUEST', 'Roulette bet type and key are required.');
            }

            if (mb_strlen($betKey) > 120) {
                throw new RouletteException(422, 'ROULETTE_INVALID_REQUEST', 'Roulette bet key is too long.');
            }

            $normalizedStake = $this->normalizeStakeAmount($stake);
            $coveredNumbers = $this->canonicalNumbersFor($betType, $betKey);
            $providedCoveredNumbers = $bet['coveredNumbers'] ?? $bet['covered_numbers'] ?? null;
            if ($providedCoveredNumbers !== null) {
                if (!is_array($providedCoveredNumbers)) {
                    throw new RouletteException(422, 'ROULETTE_INVALID_REQUEST', 'coveredNumbers must be an array when provided.');
                }

                $normalizedProvided = $this->normalizeNumberList($providedCoveredNumbers);
                if ($normalizedProvided !== $coveredNumbers) {
                    throw new RouletteException(422, 'ROULETTE_INVALID_REQUEST', 'Client coveredNumbers do not match the canonical roulette shape.');
                }
            }

            $canonicalBetKey = $this->canonicalBetKeyFor($betType, $betKey);
            $lineIdentity = sprintf('%s:%s', $betType, $canonicalBetKey);
            if (isset($seenLines[$lineIdentity])) {
                throw new RouletteException(422, 'ROULETTE_DUPLICATE_BET_LINE', 'Duplicate roulette bet lines are not allowed in a single request.');
            }
            $seenLines[$lineIdentity] = true;

            $normalized[] = [
                'line_no' => $index + 1,
                'bet_type' => $betType,
                'bet_key' => $canonicalBetKey,
                'covered_numbers' => $coveredNumbers,
                'stake_amount' => $normalizedStake,
                'payout_multiplier' => (float) self::PAYOUTS[$betType],
                'payout_amount' => 0.0,
                'status' => 'pending',
                'meta' => [
                    'submitted_bet_key' => $betKey,
                ],
            ];
        }

        return $normalized;
    }

    public function settleLines(array $lines, int $winningNumber): array
    {
        $winningColor = $this->winningColor($winningNumber);
        $settledLines = [];
        $totalStake = 0.0;
        $totalReturn = 0.0;

        foreach ($lines as $line) {
            $stake = round((float) ($line['stake_amount'] ?? 0), 2);
            $payoutMultiplier = (float) ($line['payout_multiplier'] ?? 0);
            $won = in_array($winningNumber, (array) ($line['covered_numbers'] ?? []), true);
            $payoutAmount = $won ? round($stake * ($payoutMultiplier + 1), 2) : 0.0;

            $totalStake += $stake;
            $totalReturn += $payoutAmount;

            $settledLines[] = array_merge($line, [
                'status' => $won ? 'won' : 'lost',
                'payout_amount' => $payoutAmount,
            ]);
        }

        $wonLines = count(array_filter($settledLines, fn (array $line) => $line['status'] === 'won'));

        return [
            'winning_number' => $winningNumber,
            'winning_color' => $winningColor,
            'total_stake' => round($totalStake, 2),
            'total_return' => round($totalReturn, 2),
            'net' => round($totalReturn - $totalStake, 2),
            'line_results' => $settledLines,
            'result_summary' => sprintf(
                'Winning number %d (%s) | %d/%d lines won',
                $winningNumber,
                $winningColor,
                $wonLines,
                count($settledLines)
            ),
        ];
    }

    public function winningColor(int $number): string
    {
        if ($number === 0) {
            return 'green';
        }

        return in_array($number, self::RED_NUMBERS, true) ? 'red' : 'black';
    }

    private function canonicalNumbersFor(string $betType, string $betKey): array
    {
        return match ($betType) {
            'straight' => $this->straightNumbers($betKey),
            'split' => $this->splitNumbers($betKey),
            'street' => $this->streetNumbers($betKey),
            'corner' => $this->cornerNumbers($betKey),
            'six_line' => $this->sixLineNumbers($betKey),
            'dozen' => $this->dozenNumbers($betKey),
            'column' => $this->columnNumbers($betKey),
            'red_black' => $this->redBlackNumbers($betKey),
            'odd_even' => $this->oddEvenNumbers($betKey),
            'low_high' => $this->lowHighNumbers($betKey),
            default => throw new RouletteException(422, 'ROULETTE_UNSUPPORTED_BET_TYPE', 'Unsupported roulette bet type: ' . $betType),
        };
    }

    private function canonicalBetKeyFor(string $betType, string $betKey): string
    {
        return match ($betType) {
            'straight', 'split', 'street', 'corner', 'six_line' => implode('-', $this->canonicalNumbersFor($betType, $betKey)),
            'dozen', 'column', 'red_black', 'odd_even', 'low_high' => trim(strtolower($betKey)),
            default => trim(strtolower($betKey)),
        };
    }

    private function straightNumbers(string $betKey): array
    {
        $numbers = $this->parseExactCount($betKey, 1);
        $number = $numbers[0];

        if ($number < 0 || $number > 36) {
            throw new RouletteException(422, 'ROULETTE_INVALID_BET', 'Straight up bet must target a number between 0 and 36.');
        }

        return $numbers;
    }

    private function splitNumbers(string $betKey): array
    {
        $numbers = $this->parseExactCount($betKey, 2);
        [$a, $b] = $numbers;

        if ($a === 0 || $b === 0) {
            throw new RouletteException(422, 'ROULETTE_INVALID_BET', 'European roulette does not allow zero split variants in this game.');
        }

        $sameRow = ($b - $a === 1) && intdiv($a - 1, 3) === intdiv($b - 1, 3);
        $sameColumn = ($b - $a === 3) && (($a - 1) % 3 === ($b - 1) % 3);

        if (!$sameRow && !$sameColumn) {
            throw new RouletteException(422, 'ROULETTE_INVALID_BET', 'Split bet must target two adjacent standard-table numbers.');
        }

        return $numbers;
    }

    private function streetNumbers(string $betKey): array
    {
        $numbers = $this->parseExactCount($betKey, 3);
        [$first, $second, $third] = $numbers;

        if ($first === 0 || ($first - 1) % 3 !== 0 || $second !== $first + 1 || $third !== $first + 2) {
            throw new RouletteException(422, 'ROULETTE_INVALID_BET', 'Street bet must target one horizontal row on the standard 1-36 table.');
        }

        return $numbers;
    }

    private function cornerNumbers(string $betKey): array
    {
        $numbers = $this->parseExactCount($betKey, 4);
        [$topLeft, $topRight, $bottomLeft, $bottomRight] = $numbers;

        if ($topLeft === 0 || $topLeft % 3 === 0 || $topLeft > 32) {
            throw new RouletteException(422, 'ROULETTE_INVALID_BET', 'Corner bet must target a standard 2x2 square on numbers 1-36.');
        }

        if ($topRight !== $topLeft + 1 || $bottomLeft !== $topLeft + 3 || $bottomRight !== $topLeft + 4) {
            throw new RouletteException(422, 'ROULETTE_INVALID_BET', 'Corner bet must target a standard 2x2 square on numbers 1-36.');
        }

        return $numbers;
    }

    private function sixLineNumbers(string $betKey): array
    {
        $numbers = $this->parseExactCount($betKey, 6);
        $first = $numbers[0];

        if ($first <= 0 || ($first - 1) % 3 !== 0 || $first > 31) {
            throw new RouletteException(422, 'ROULETTE_INVALID_BET', 'Six line bet must target two adjacent streets on the 1-36 table.');
        }

        for ($index = 1; $index < 6; $index++) {
            if ($numbers[$index] !== $first + $index) {
                throw new RouletteException(422, 'ROULETTE_INVALID_BET', 'Six line bet must target two adjacent streets on the 1-36 table.');
            }
        }

        return $numbers;
    }

    private function dozenNumbers(string $betKey): array
    {
        $dozen = (int) trim($betKey);
        if (!in_array($dozen, [1, 2, 3], true)) {
            throw new RouletteException(422, 'ROULETTE_INVALID_BET', 'Dozen bet key must be 1, 2 or 3.');
        }

        $start = (($dozen - 1) * 12) + 1;

        return range($start, $start + 11);
    }

    private function columnNumbers(string $betKey): array
    {
        $column = (int) trim($betKey);
        if (!in_array($column, [1, 2, 3], true)) {
            throw new RouletteException(422, 'ROULETTE_INVALID_BET', 'Column bet key must be 1, 2 or 3.');
        }

        $numbers = [];
        for ($number = $column; $number <= 36; $number += 3) {
            $numbers[] = $number;
        }

        return $numbers;
    }

    private function redBlackNumbers(string $betKey): array
    {
        $key = trim(strtolower($betKey));

        return match ($key) {
            'red' => self::RED_NUMBERS,
            'black' => self::BLACK_NUMBERS,
            default => throw new RouletteException(422, 'ROULETTE_INVALID_BET', 'red_black bet key must be red or black.'),
        };
    }

    private function oddEvenNumbers(string $betKey): array
    {
        $key = trim(strtolower($betKey));
        if (!in_array($key, ['odd', 'even'], true)) {
            throw new RouletteException(422, 'ROULETTE_INVALID_BET', 'odd_even bet key must be odd or even.');
        }

        return array_values(array_filter(range(1, 36), fn (int $number) => $key === 'odd' ? $number % 2 === 1 : $number % 2 === 0));
    }

    private function lowHighNumbers(string $betKey): array
    {
        $key = trim(strtolower($betKey));

        return match ($key) {
            'low' => range(1, 18),
            'high' => range(19, 36),
            default => throw new RouletteException(422, 'ROULETTE_INVALID_BET', 'low_high bet key must be low or high.'),
        };
    }

    private function parseExactCount(string $betKey, int $count): array
    {
        $numbers = $this->normalizeNumberList(preg_split('/[^0-9]+/', trim($betKey), -1, PREG_SPLIT_NO_EMPTY) ?: []);

        if (count($numbers) !== $count) {
            throw new RouletteException(422, 'ROULETTE_INVALID_BET', 'Roulette bet key does not match the required shape.');
        }

        return $numbers;
    }

    private function normalizeNumberList(array $numbers): array
    {
        $normalized = [];
        foreach ($numbers as $number) {
            if (!is_numeric($number) || (string) (int) $number !== trim((string) $number)) {
                throw new RouletteException(422, 'ROULETTE_INVALID_REQUEST', 'Roulette numbers must be integers.');
            }

            $intNumber = (int) $number;
            if ($intNumber < 0 || $intNumber > 36) {
                throw new RouletteException(422, 'ROULETTE_INVALID_BET', 'Roulette numbers must be between 0 and 36.');
            }

            $normalized[] = $intNumber;
        }

        sort($normalized);

        if (count(array_unique($normalized)) !== count($normalized)) {
            throw new RouletteException(422, 'ROULETTE_INVALID_BET', 'Roulette bet numbers cannot contain duplicates.');
        }

        return array_values($normalized);
    }

    private function normalizeBetType(string $betType): string
    {
        $normalized = trim(strtolower(str_replace(['-', '_'], ' ', $betType)));
        $canonical = self::BET_TYPE_ALIASES[$normalized]
            ?? self::BET_TYPE_ALIASES[str_replace(' ', '_', $normalized)]
            ?? null;

        if ($canonical === null) {
            throw new RouletteException(422, 'ROULETTE_UNSUPPORTED_BET_TYPE', 'Unsupported roulette bet type: ' . trim($betType));
        }

        return $canonical;
    }

    private function normalizeStakeAmount(mixed $stake): float
    {
        if (!is_scalar($stake)) {
            throw new RouletteException(422, 'ROULETTE_INVALID_REQUEST', 'Roulette stake must be a positive number.');
        }

        $stakeString = trim((string) $stake);
        if ($stakeString === '' || !preg_match('/^\d+(?:\.\d{1,2})?$/', $stakeString)) {
            throw new RouletteException(422, 'ROULETTE_INVALID_STAKE_FORMAT', 'Roulette stake must be a positive number with up to two decimal places.');
        }

        $normalizedStake = round((float) $stakeString, 2);
        if (!is_finite($normalizedStake) || $normalizedStake <= 0) {
            throw new RouletteException(422, 'ROULETTE_INVALID_REQUEST', 'Roulette stake must be a positive number.');
        }

        return $normalizedStake;
    }
}
