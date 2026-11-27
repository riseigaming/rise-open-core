<?php

namespace App\Services\InternalGames;

/**
 * Selectively adapted from vFair Games (Apache-2.0), commit ccdb1b2f8bbfbe6249e36579e11523e6d1ed7706.
 * Modified for Rise: integer minor-unit payout, bounded odds and PHP runtime.
 * Upstream paths and full license: docs/licenses/vfair-dice/README.md.
 * This random roll is not a commit/reveal protocol; no provably-fair claim is made.
 */
class DiceEngine
{
    public const RTP_UNITS = 9800;
    public const MIN_CHANCE_UNITS = 100;
    public const MAX_CHANCE_UNITS = 9700;
    public const MIN_BET_MINOR = 10;
    public const MAX_BET_MINOR = 10000;

    public function roll(): int
    {
        return $this->rollFromHash(bin2hex(random_bytes(32)));
    }

    /** vFair's unsigned big-endian 32-bit mapping to 10,000 buckets. */
    public function rollFromHash(string $hash): int
    {
        if (!preg_match('/\A[0-9a-f]{8,}\z/i', $hash)) {
            throw new \InvalidArgumentException('Invalid Dice hash.');
        }

        $integer = unpack('N', hex2bin(substr($hash, 0, 8)))[1];

        return intdiv($integer * 10000, 4294967296);
    }

    public function odds(string $mode, int $target): array
    {
        if (!in_array($mode, ['over', 'under'], true)) {
            throw new \InvalidArgumentException('Invalid Dice mode.');
        }
        $chance = $mode === 'under' ? $target : 10000 - $target;
        if ($chance < self::MIN_CHANCE_UNITS || $chance > self::MAX_CHANCE_UNITS) {
            throw new \InvalidArgumentException('Invalid Dice target.');
        }

        return ['target' => $target / 100, 'winChance' => $chance / 100,
            'multiplier' => round(self::RTP_UNITS / $chance, 4)];
    }

    public function payoutMinor(int $stake, string $mode, int $target): int
    {
        $this->odds($mode, $target);
        if ($stake < self::MIN_BET_MINOR || $stake > self::MAX_BET_MINOR) {
            throw new \InvalidArgumentException('Invalid Dice stake.');
        }

        // Gross return, rounded down once; never use a display-rounded multiplier.
        return intdiv($stake * self::RTP_UNITS, $mode === 'under' ? $target : 10000 - $target);
    }

    public function settle(int $stake, string $mode, int $target, int $rolled): array
    {
        $payout = $this->payoutMinor($stake, $mode, $target);
        if ($rolled < 0 || $rolled > 9999) {
            throw new \InvalidArgumentException('Invalid Dice result.');
        }
        // Preserve vFair's equality rule: under is strict, over includes target.
        $won = $mode === 'under' ? $rolled < $target : $rolled >= $target;

        return $this->odds($mode, $target) + ['mode' => $mode, 'rolled' => $rolled / 100,
            'won' => $won, 'payoutMinor' => $won ? $payout : 0];
    }
}
