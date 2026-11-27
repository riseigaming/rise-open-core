<?php

namespace App\Services\InternalGames;

use Illuminate\Support\Str;

class SkyRushEngine
{
    public function createRoundMath(array $definition, string $roundPublicId): array
    {
        $serverSeed = Str::random(48);
        $serverSeedHash = hash('sha256', $serverSeed);
        $fairHash = hash('sha256', $serverSeed . '|' . $roundPublicId);
        $crashMultiplier = $this->deriveCrashMultiplier($definition, $fairHash);

        return [
            'server_seed' => $serverSeed,
            'server_seed_hash' => $serverSeedHash,
            'fair_hash' => $fairHash,
            'crash_multiplier' => $crashMultiplier,
            'flight_seconds' => $this->secondsForMultiplier($definition, $crashMultiplier),
        ];
    }

    public function currentMultiplier(array $definition, float $elapsedSeconds): float
    {
        $elapsedSeconds = max(0, $elapsedSeconds);
        $rate = max(0.01, (float) ($definition['growth_rate'] ?? 0.55));
        $scale = max(0.01, (float) ($definition['growth_scale'] ?? 0.35));

        $multiplier = 1 + ((exp($rate * $elapsedSeconds) - 1) * $scale);

        return round(max(1.00, $multiplier), 2);
    }

    public function secondsForMultiplier(array $definition, float $multiplier): float
    {
        $target = max(1.00, $multiplier);
        $rate = max(0.01, (float) ($definition['growth_rate'] ?? 0.55));
        $scale = max(0.01, (float) ($definition['growth_scale'] ?? 0.35));

        if ($target <= 1.00) {
            return 0.0;
        }

        return max(0.0, log((($target - 1.0) / $scale) + 1) / $rate);
    }

    private function deriveCrashMultiplier(array $definition, string $fairHash): float
    {
        $slice = substr($fairHash, 0, 13);
        $max = hexdec(str_repeat('f', 13));
        $ratio = $max > 0 ? (hexdec($slice) / $max) : 0.0;
        $houseEdge = (float) ($definition['house_edge'] ?? 0.965);
        $minMultiplier = (float) ($definition['min_crash_multiplier'] ?? 1.08);
        $maxMultiplier = (float) ($definition['max_crash_multiplier'] ?? 25.0);

        $rawMultiplier = $houseEdge * (1 / max(0.04, 1 - $ratio));

        return round(min($maxMultiplier, max($minMultiplier, $rawMultiplier)), 2);
    }
}
