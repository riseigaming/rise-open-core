<?php

namespace App\Services\ChessLiveCockpit;

use Illuminate\Support\Str;

class ChessRecurringBoardResolver
{
    public const PROVENANCE_KEY = 'cms_recurring_board_key';

    public function resolve(?string $marketInstanceId, array $payload = []): ?string
    {
        $marketReference = $this->normalizeReference($marketInstanceId);
        $directBoardKey = $this->normalizeBoardKey($marketReference);
        $mappedBoardKey = $directBoardKey === null
            ? $this->mappedBoardKey($marketReference)
            : null;
        $persistedBoardKeys = $this->persistedBoardKeys($payload);
        if (count($persistedBoardKeys) > 1) {
            return null;
        }

        $persistedBoardKey = $persistedBoardKeys[0] ?? null;
        $resolvedMarketBoardKey = $directBoardKey ?? $mappedBoardKey;

        if ($resolvedMarketBoardKey !== null
            && $persistedBoardKey !== null
            && $resolvedMarketBoardKey !== $persistedBoardKey) {
            return null;
        }

        return $directBoardKey ?? $persistedBoardKey ?? $mappedBoardKey;
    }

    public function withCanonicalProvenance(array $payload): array
    {
        $boardKey = $this->resolve(
            isset($payload['marketInstanceId']) ? (string) $payload['marketInstanceId'] : null,
            $payload
        );

        if ($boardKey !== null) {
            $payload[self::PROVENANCE_KEY] = $boardKey;
        }

        return $payload;
    }

    private function persistedBoardKeys(array $payload): array
    {
        $keys = [
            self::PROVENANCE_KEY,
            'cmsRecurringBoardKey',
        ];
        $resolved = [];

        foreach ($keys as $key) {
            if (!array_key_exists($key, $payload)) {
                continue;
            }

            $boardKey = $this->normalizeBoardKey($payload[$key]);
            if ($boardKey !== null) {
                $resolved[$boardKey] = true;
            }
        }

        return array_keys($resolved);
    }

    private function mappedBoardKey(?string $marketInstanceId): ?string
    {
        if ($marketInstanceId === null) {
            return null;
        }

        $mapped = data_get(
            config('chess_live_cockpit.recurring_market_instance_board_map', []),
            $marketInstanceId
        );

        return $this->normalizeBoardKey($mapped);
    }

    private function normalizeBoardKey(mixed $value): ?string
    {
        $normalized = $this->normalizeReference($value);

        return $normalized !== null && Str::startsWith($normalized, 'cms-recurring-')
            ? $normalized
            : null;
    }

    private function normalizeReference(mixed $value): ?string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }
}
