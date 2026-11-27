<?php

namespace App\Services\ExternalGames;

final class ExternalGameProviderConfig
{
    public const PRIMARY = 'primary_external_provider';
    public const REQUEST_ALIAS = 'external_games_provider_alias';

    public static function providers(): array
    {
        return array_filter(
            (array) config('external_games.providers', []),
            static fn (mixed $entry, string $alias): bool => is_array($entry)
                && preg_match('/\A[a-z0-9_-]{1,64}\z/', $alias) === 1,
            ARRAY_FILTER_USE_BOTH
        );
    }

    public static function enabled(string $alias): bool
    {
        $entry = self::providers()[$alias] ?? null;

        return $entry !== null && ($entry['enabled'] ?? true) === true;
    }

    public static function fromPublicAlias(string $publicAlias): ?string
    {
        $matches = array_keys(array_filter(
            self::providers(),
            static fn (array $entry): bool => ($entry['public_alias'] ?? null) === $publicAlias
        ));

        return count($matches) === 1 && self::enabled($matches[0]) ? $matches[0] : null;
    }

    public static function distinctWalletCredentials(string $alias): bool
    {
        $providers = self::providers();
        $entry = $providers[$alias] ?? [];
        foreach ($providers as $otherAlias => $other) {
            if ($otherAlias === $alias || !self::enabled($otherAlias)) {
                continue;
            }
            if (trim((string) ($entry['agent_token'] ?? '')) === trim((string) ($other['agent_token'] ?? ''))
                && trim((string) ($entry['agent_code'] ?? '')) === trim((string) ($other['agent_code'] ?? ''))
                && trim((string) ($entry['agent_secret'] ?? '')) === trim((string) ($other['agent_secret'] ?? ''))) {
                return false;
            }
        }

        return true;
    }

    public static function storageKey(string $base, string $alias): string
    {
        return $alias === self::PRIMARY ? $base : $base . ':' . $alias;
    }

    public static function transactionPrefix(string $alias): string
    {
        return 'external_games:' . $alias . ':';
    }
}
