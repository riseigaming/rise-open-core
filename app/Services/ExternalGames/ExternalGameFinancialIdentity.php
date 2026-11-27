<?php

namespace App\Services\ExternalGames;

use App\Models\ExternalGameSession;

/** Launch rows are correlation evidence; this tuple owns the money. */
final class ExternalGameFinancialIdentity
{
    public static function fromSession(ExternalGameSession $session): array
    {
        return [
            'root_user_id' => (int) $session->root_user_id,
            'cms_user_id' => (int) $session->cms_user_id,
            'provider_alias' => (string) $session->provider_alias,
            'provider_game_id' => (string) $session->provider_game_id,
            'wallet_account_id' => (int) $session->wallet_account_id,
            'wallet_account_type' => (string) $session->wallet_account_type,
            'wallet_currency' => strtoupper((string) $session->wallet_currency),
        ];
    }

    public static function valid(array $identity): bool
    {
        if (count($identity) !== 7) return false;
        foreach (['root_user_id', 'cms_user_id', 'wallet_account_id'] as $key) {
            if (!is_int($identity[$key] ?? null) || $identity[$key] <= 0) return false;
        }
        foreach (['provider_alias', 'provider_game_id', 'wallet_account_type', 'wallet_currency'] as $key) {
            if (!is_string($identity[$key] ?? null) || trim($identity[$key]) === '') return false;
        }
        return true;
    }

    public static function matches(array $left, array $right, bool $requireGame = true): bool
    {
        if (!self::valid($left) || !self::valid($right)) return false;
        // MySQL binary JSON can reorder object keys. Values stay strictly typed.
        foreach ($left as $key => $value) {
            if ($key === 'provider_game_id' && !$requireGame) continue;
            if ($value !== $right[$key]) return false;
        }
        return true;
    }
}
