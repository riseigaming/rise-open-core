<?php

namespace App\Services\ExternalGames;

use App\Models\ExternalGameSession;
use Illuminate\Http\Request;

/**
 * Classifies the value stored in cms_user_transactions.round_id.
 *
 * That legacy column is an operational grouping key. It is provider round truth
 * only when the callback explicitly supplies round_id or roundId.
 */
final class ExternalGameCallbackGroupingIdentity
{
    public const PROVIDER_ROUND_ID = 'provider_round_id';
    public const PROVIDER_SESSION_REFERENCE = 'provider_session_reference';
    public const CALLBACK_GROUPING_KEY = 'callback_grouping_key';

    private function __construct(
        public readonly string $value,
        public readonly string $classification
    ) {
    }

    public static function resolve(Request $request, ExternalGameSession $session): self
    {
        $explicitRound = self::firstString($request, ['round_id', 'roundId']);
        if ($explicitRound !== '') {
            return new self($explicitRound, self::PROVIDER_ROUND_ID);
        }

        $sessionReference = self::firstString($request, ['session_id']);
        if ($sessionReference !== '') {
            return new self($sessionReference, self::PROVIDER_SESSION_REFERENCE);
        }

        return new self((string) $session->session_token, self::CALLBACK_GROUPING_KEY);
    }

    public function isExplicitProviderRound(): bool
    {
        return $this->classification === self::PROVIDER_ROUND_ID;
    }

    private static function firstString(Request $request, array $keys): string
    {
        foreach ($keys as $key) {
            $value = trim((string) $request->input($key, ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}
