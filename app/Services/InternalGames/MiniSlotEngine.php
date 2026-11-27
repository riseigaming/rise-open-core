<?php

namespace App\Services\InternalGames;

class MiniSlotEngine
{
    public function spin(array $definition, float $betAmount): array
    {
        $grid = $this->spinGrid($definition);

        $lines = [
            'top' => [$grid[0][0], $grid[1][0], $grid[2][0]],
            'middle' => [$grid[0][1], $grid[1][1], $grid[2][1]],
            'bottom' => [$grid[0][2], $grid[1][2], $grid[2][2]],
        ];

        $winningLines = [];
        $totalMultiplier = 0.0;

        foreach ($lines as $lineKey => $lineSymbols) {
            $evaluation = $this->evaluateLine(
                $lineSymbols,
                $lineKey,
                (array) (($definition['paytable'] ?? [])[$lineKey] ?? [])
            );

            if ($evaluation['multiplier'] > 0) {
                $winningLines[] = [
                    'line' => $lineKey,
                    'lineLabel' => ucfirst($lineKey),
                    'symbols' => $lineSymbols,
                    'symbol' => $evaluation['symbol'],
                    'rule' => $evaluation['rule'],
                    'multiplier' => $evaluation['multiplier'],
                ];
                $totalMultiplier += $evaluation['multiplier'];
            }
        }

        $winAmount = round($betAmount * $totalMultiplier, 2);

        return [
            'grid' => $grid,
            'winningLines' => $winningLines,
            'lineCount' => count($lines),
            'multiplier' => $totalMultiplier,
            'winAmount' => $winAmount,
            'resultSummary' => $winAmount > 0
                ? sprintf('Win %.2f with %d winning line(s)', $winAmount, count($winningLines))
                : 'No win',
        ];
    }

    private function spinGrid(array $definition): array
    {
        $reelStrips = array_values(array_filter((array) ($definition['reel_strips'] ?? []), 'is_array'));
        if (count($reelStrips) >= 3) {
            return array_map(fn (array $strip) => $this->windowFromStrip($strip), array_slice($reelStrips, 0, 3));
        }

        $symbols = array_values((array) ($definition['symbols'] ?? []));
        $grid = [];

        for ($reelIndex = 0; $reelIndex < 3; $reelIndex++) {
            $available = $symbols;
            $grid[$reelIndex] = [];

            for ($rowIndex = 0; $rowIndex < 3; $rowIndex++) {
                $choiceIndex = random_int(0, max(0, count($available) - 1));
                $grid[$reelIndex][] = $available[$choiceIndex];
                array_splice($available, $choiceIndex, 1);
            }
        }

        return $grid;
    }

    private function windowFromStrip(array $strip): array
    {
        $strip = array_values(array_filter($strip, fn ($symbol) => is_string($symbol) && trim($symbol) !== ''));
        if ($strip === []) {
            return ['BAR', 'BAR', 'BAR'];
        }

        $count = count($strip);
        $stopIndex = random_int(0, $count - 1);

        return [
            $strip[$stopIndex % $count],
            $strip[($stopIndex + 1) % $count],
            $strip[($stopIndex + 2) % $count],
        ];
    }

    private function evaluateLine(array $symbols, string $lineKey, array $paytable): array
    {
        if (count(array_unique($symbols)) === 1) {
            $symbol = $symbols[0];

            return [
                'symbol' => $symbol,
                'rule' => $symbol,
                'multiplier' => (float) ($paytable[$symbol] ?? 0),
            ];
        }

        if ($this->allInSet($symbols, ['7', 'Cherry'])) {
            return [
                'symbol' => '7/Cherry Mix',
                'rule' => '7_or_cherry_mix',
                'multiplier' => (float) ($paytable['7_or_cherry_mix'] ?? 0),
            ];
        }

        if ($this->allInSet($symbols, ['BAR', '2xBAR', '3xBAR'])) {
            return [
                'symbol' => 'BAR Mix',
                'rule' => 'bar_mix',
                'multiplier' => (float) ($paytable['bar_mix'] ?? 0),
            ];
        }

        return [
            'symbol' => $lineKey,
            'rule' => null,
            'multiplier' => 0.0,
        ];
    }

    private function allInSet(array $symbols, array $allowedSymbols): bool
    {
        foreach ($symbols as $symbol) {
            if (!in_array($symbol, $allowedSymbols, true)) {
                return false;
            }
        }

        return true;
    }
}
