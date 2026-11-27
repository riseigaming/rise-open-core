<?php

namespace App\Betting\Support;

use App\Betting\Exceptions\PublicSelectionTokenException;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;

class PublicSelectionTokenService
{
    private const PURPOSE = 'sportsbook_public_selection';
    private const VERSION = 1;
    private const DEFAULT_TTL_SECONDS = 600;

    public function issueForAdapterSelection(array $event, array $market, array $selection): ?string
    {
        $quoteRef = trim((string) ($selection['quote_ref'] ?? ''));
        if ($quoteRef === '') {
            return null;
        }

        $issuedAt = Carbon::now()->timestamp;
        $ttlSeconds = $this->ttlSeconds();
        $lastTrustedQuote = $this->lastTrustedQuote($event, $market, $selection, $issuedAt);
        $payload = [
            'v' => self::VERSION,
            'purpose' => self::PURPOSE,
            'issued_at' => $issuedAt,
            'expires_at' => $issuedAt + $ttlSeconds,
            'phase_key' => $this->phaseKey($event),
            'event_id' => (int) ($event['event_id'] ?? 0),
            'event_key' => (string) ($event['event_key'] ?? ''),
            'source_event_key' => (string) ($event['source_event_key'] ?? ''),
            'quote_version_id' => data_get($event, 'quote.quote_version_id'),
            'quote_ref' => $quoteRef,
            'market_type' => (string) ($market['market_type'] ?? ''),
            'market_specifier_key' => (string) ($market['specifier_key'] ?? 'default'),
            'market_status' => (string) ($market['status'] ?? 'open'),
            'outcome_code' => (string) ($selection['outcome_code'] ?? ''),
            'offer_status' => (string) ($selection['offer_status'] ?? 'open'),
            'decimal_price' => (string) ($selection['decimal_price'] ?? ''),
            'acceptance_mode' => (string) ($selection['acceptance_mode'] ?? 'adapter_vps_quote_ref'),
            'last_trusted_quote' => $lastTrustedQuote,
        ];

        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            return null;
        }

        return Crypt::encryptString($encoded);
    }

    public function resolve(string $token): array
    {
        $token = trim($token);
        if ($token === '') {
            throw new PublicSelectionTokenException('selection_token_missing', 'Selection token is missing.');
        }

        try {
            $decoded = Crypt::decryptString($token);
        } catch (DecryptException $exception) {
            throw new PublicSelectionTokenException('selection_token_invalid', 'Selection token is invalid.');
        }

        $payload = json_decode($decoded, true);
        if (!is_array($payload)
            || (int) ($payload['v'] ?? 0) !== self::VERSION
            || (string) ($payload['purpose'] ?? '') !== self::PURPOSE
            || trim((string) ($payload['quote_ref'] ?? '')) === ''
        ) {
            throw new PublicSelectionTokenException('selection_token_invalid', 'Selection token payload is invalid.');
        }

        if ((int) ($payload['expires_at'] ?? 0) <= Carbon::now()->timestamp) {
            throw new PublicSelectionTokenException('selection_token_expired', 'Selection token expired.', 409);
        }

        return $payload;
    }

    private function lastTrustedQuote(array $event, array $market, array $selection, int $issuedAt): array
    {
        $startsAt = $event['starts_at'] ?? $event['starts_at_iso'] ?? null;
        if (($startsAt === null || $startsAt === '') && (int) ($event['starts_at_timestamp'] ?? 0) > 0) {
            $startsAt = Carbon::createFromTimestamp((int) $event['starts_at_timestamp'])->toIso8601String();
        }

        $sourceEventKey = trim((string) ($event['source_event_key'] ?? $event['event_key'] ?? ''));
        $marketType = trim((string) ($market['market_type'] ?? ''));
        $specifierKey = trim((string) ($market['specifier_key'] ?? 'default')) ?: 'default';
        $outcomeCode = trim((string) ($selection['outcome_code'] ?? ''));
        $providerKey = trim((string) ($selection['source_provider']
            ?? $event['source_provider']
            ?? data_get($event, 'public_live_state.provider_key', 'adapter_vps')));

        $trusted = [
            'quote_ref' => (string) ($selection['quote_ref'] ?? ''),
            'quote_version_ref' => (string) (data_get($event, 'quote.quote_version_ref')
                ?? data_get($event, 'quote.version_ref')
                ?? data_get($event, 'quote.quote_version_id')
                ?? ''),
            'verified_at' => Carbon::createFromTimestamp($issuedAt)->toIso8601String(),
            'event' => [
                'event_id' => $event['event_id'] ?? null,
                'event_key' => (string) ($event['event_key'] ?? ''),
                'source_event_key' => $sourceEventKey,
                'canonical_name' => (string) ($event['canonical_name'] ?? $event['event_name'] ?? ''),
                'sport_code' => (string) ($event['sport_code'] ?? $event['sport_group_key'] ?? ''),
                'tournament_name' => (string) ($event['tournament_name'] ?? $event['competition_label'] ?? ''),
                'starts_at' => $startsAt,
                'phase_key' => $this->phaseKey($event),
                'is_live' => (bool) ($event['is_live'] ?? false),
                'lifecycle_status' => (string) ($event['lifecycle_status'] ?? 'scheduled'),
            ],
            'market' => [
                'market_type' => $marketType,
                'specifier_key' => $specifierKey,
                'display_name' => (string) ($market['display_name'] ?? $market['title'] ?? $marketType),
                'status' => (string) ($market['status'] ?? ''),
                'line' => $market['line'] ?? null,
            ],
            'selection' => [
                'outcome_code' => $outcomeCode,
                'selection_key' => $outcomeCode,
                'display_name' => (string) ($selection['display_name'] ?? $selection['label'] ?? $outcomeCode),
                'offer_status' => (string) ($selection['offer_status'] ?? ''),
                'decimal_price' => (string) ($selection['decimal_price'] ?? ''),
                'settlement_identity_state' => (string) ($selection['settlement_identity_state'] ?? ''),
            ],
            'source' => [
                'provider_key' => $providerKey !== '' ? $providerKey : 'adapter_vps',
                'connector_key' => 'adapter_vps',
            ],
            'settlement_identity' => [
                'source_event_key' => $sourceEventKey,
                'event_name' => (string) ($event['canonical_name'] ?? $event['event_name'] ?? ''),
                'event_start_at' => $startsAt,
                'sport_key' => (string) ($event['sport_code'] ?? $event['sport_group_key'] ?? ''),
                'competition_name' => (string) ($event['tournament_name'] ?? $event['competition_label'] ?? ''),
                'market_type' => $marketType,
                'market_key' => $marketType . ':' . $specifierKey,
                'selection_key' => $outcomeCode,
            ],
        ];
        $trusted['quote_payload_digest'] = hash('sha256', json_encode($trusted, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '');

        return $trusted;
    }

    private function ttlSeconds(): int
    {
        return max(60, min(3600, (int) config('betting.public_selection_token.ttl_seconds', self::DEFAULT_TTL_SECONDS)));
    }

    private function phaseKey(array $event): string
    {
        $phaseKey = (string) data_get($event, 'public_live_state.phase_key', data_get($event, 'phase_key', ''));
        if ($phaseKey !== '') {
            return $phaseKey;
        }

        $lifecycleStatus = (string) ($event['lifecycle_status'] ?? '');
        if ((bool) ($event['is_live'] ?? false) || in_array($lifecycleStatus, ['started', 'live', 'inplay'], true)) {
            return 'in-game';
        }

        if (in_array($lifecycleStatus, ['settled', 'cancelled', 'finished', 'completed', 'closed'], true)) {
            return 'ended';
        }

        return 'pre-match';
    }
}
