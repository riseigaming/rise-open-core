<?php

namespace App\Betting\AdapterVps;

use Illuminate\Support\Carbon;

class PreMatchLastGoodAcceptancePolicy
{
    public const POLICY_VERSION = 'prematch_last_good_grace_v1';

    public function evaluate(string $quoteRef, array $verify, array $attributes): array
    {
        $providerFailure = strtolower(trim((string) ($verify['code'] ?? 'unavailable')));
        $baseMetadata = [
            'quote_source' => 'fresh',
            'quote_age_seconds' => null,
            'grace_used' => false,
            'grace_reason' => null,
            'provider_failure_reason' => $providerFailure,
            'policy_version' => self::POLICY_VERSION,
        ];

        if (!(bool) config('betting.acceptance.prematch_last_good_grace.enabled', false)) {
            return $this->denied('prematch_last_good_grace_disabled', $baseMetadata);
        }

        if (!$this->eligibleProviderFailure($providerFailure)) {
            return $this->denied('provider_failure_not_eligible', $baseMetadata);
        }

        $trusted = (array) ($attributes['last_trusted_quote'] ?? []);
        if ($trusted === [] || trim((string) ($trusted['quote_ref'] ?? '')) !== $quoteRef) {
            return $this->denied('last_trusted_quote_missing', $baseMetadata);
        }

        $phaseKey = strtolower(trim((string) data_get($trusted, 'event.phase_key', '')));
        $isLive = (bool) data_get($trusted, 'event.is_live', false);
        $lifecycle = strtolower(trim((string) data_get($trusted, 'event.lifecycle_status', '')));
        if ($phaseKey !== 'pre-match' || $isLive || in_array($lifecycle, ['started', 'live', 'inplay'], true)) {
            return $this->denied('live_or_in_play_not_allowed', $baseMetadata);
        }

        $startsAt = $this->parseTimestamp(data_get($trusted, 'event.starts_at'));
        if (!$startsAt instanceof Carbon || $startsAt->lessThanOrEqualTo(now())) {
            return $this->denied('event_started_or_start_missing', $baseMetadata);
        }

        $startBuffer = max(600, (int) config(
            'betting.acceptance.prematch_last_good_grace.min_event_start_buffer_seconds',
            900
        ));
        if ($startsAt->timestamp - now()->timestamp <= $startBuffer) {
            return $this->denied('event_starts_too_soon', $baseMetadata);
        }

        $issuedAt = (int) ($attributes['last_trusted_quote_issued_at'] ?? 0);
        $quoteAge = $issuedAt > 0 && $issuedAt <= now()->timestamp
            ? now()->timestamp - $issuedAt
            : null;
        $maxQuoteAge = max(1, min(300, (int) config(
            'betting.acceptance.prematch_last_good_grace.max_quote_age_seconds',
            120
        )));
        if ($quoteAge === null || $quoteAge > $maxQuoteAge) {
            return $this->denied('last_trusted_quote_too_old', array_merge($baseMetadata, [
                'quote_age_seconds' => $quoteAge,
            ]));
        }

        $marketState = strtolower(trim((string) data_get($trusted, 'market.status', '')));
        if ($marketState !== 'open') {
            return $this->denied('last_trusted_market_not_open', $baseMetadata);
        }

        $selectionState = strtolower(trim((string) data_get($trusted, 'selection.offer_status', '')));
        if ($selectionState !== 'open') {
            return $this->denied('last_trusted_selection_not_open', $baseMetadata);
        }

        if ((string) data_get($trusted, 'selection.settlement_identity_state', '') !== 'verified') {
            return $this->denied('last_trusted_settlement_identity_unverified', $baseMetadata);
        }

        if ((int) ($attributes['selection_count'] ?? 1) !== 1
            || (bool) ($attributes['is_accumulator'] ?? false)) {
            return $this->denied('accumulator_not_allowed', $baseMetadata);
        }

        $stake = (float) ($attributes['stake_amount'] ?? 0);
        $maxStake = max(0.0, (float) config('betting.acceptance.prematch_last_good_grace.max_stake', 100));
        if ($stake <= 0 || $maxStake <= 0 || $stake - 0.000001 > $maxStake) {
            return $this->denied('prematch_last_good_grace_stake_cap_exceeded', $baseMetadata);
        }

        $currency = strtoupper(trim((string) ($attributes['currency'] ?? 'TRY')));
        $allowedCurrencies = array_values(array_filter(array_map(
            fn ($value): string => strtoupper(trim((string) $value)),
            (array) config('betting.acceptance.prematch_last_good_grace.allowed_currencies', ['TRY'])
        )));
        if (!in_array($currency, $allowedCurrencies, true)) {
            return $this->denied('prematch_last_good_grace_currency_not_allowed', $baseMetadata);
        }

        $trustedPrice = (float) data_get($trusted, 'selection.decimal_price', 0);
        $acceptedPrice = (float) ($attributes['accepted_price'] ?? $trustedPrice);
        if ($trustedPrice <= 1 || $acceptedPrice <= 1 || abs($trustedPrice - $acceptedPrice) > 0.000001) {
            return $this->denied('last_trusted_price_mismatch', $baseMetadata);
        }

        foreach (['quote_payload_digest', 'source', 'settlement_identity'] as $required) {
            $value = data_get($trusted, $required);
            if ($value === null || $value === '' || (is_array($value) && $value === [])) {
                return $this->denied('last_trusted_quote_incomplete', $baseMetadata);
            }
        }
        foreach (['source_event_key', 'market_type', 'selection_key'] as $identityKey) {
            if (trim((string) data_get($trusted, 'settlement_identity.' . $identityKey, '')) === '') {
                return $this->denied('last_trusted_quote_incomplete', $baseMetadata);
            }
        }

        $metadata = [
            'quote_source' => 'prematch_last_good_grace',
            'quote_age_seconds' => $quoteAge,
            'grace_used' => true,
            'grace_reason' => 'short_provider_artifact_gap',
            'provider_failure_reason' => $providerFailure,
            'policy_version' => self::POLICY_VERSION,
            'event_starts_at' => $startsAt->toIso8601String(),
            'market_state' => $marketState,
            'selection_state' => $selectionState,
            'stake_cap_applied' => $maxStake,
            'live_in_play' => false,
        ];

        return [
            'allowed' => true,
            'reason' => 'prematch_last_good_grace_allowed',
            'verified_quote' => $this->verifiedQuoteFromTrustedContract($trusted, $issuedAt),
            'metadata' => $metadata,
        ];
    }

    public function freshMetadata(array $verifiedQuote): array
    {
        $phaseKey = strtolower((string) data_get($verifiedQuote, 'event.phase_key', ''));
        $liveInPlay = (bool) data_get($verifiedQuote, 'event.is_live', false)
            || in_array($phaseKey, ['in-game', 'in_play', 'in-play', 'live'], true);

        return [
            'quote_source' => 'fresh',
            'quote_age_seconds' => 0,
            'grace_used' => false,
            'grace_reason' => null,
            'provider_failure_reason' => null,
            'policy_version' => self::POLICY_VERSION,
            'event_starts_at' => data_get($verifiedQuote, 'event.starts_at'),
            'market_state' => (string) data_get($verifiedQuote, 'market.status', data_get($verifiedQuote, 'trading_state', '')),
            'selection_state' => (string) data_get($verifiedQuote, 'selection.offer_status', data_get($verifiedQuote, 'offer_status', '')),
            'live_in_play' => $liveInPlay,
        ];
    }

    private function verifiedQuoteFromTrustedContract(array $trusted, int $issuedAt): array
    {
        return [
            'status' => 'open',
            'trading_state' => 'open',
            'offer_status' => 'open',
            'quote_ref' => (string) $trusted['quote_ref'],
            'quote_version_ref' => $trusted['quote_version_ref'] ?? null,
            'quote_payload_digest' => (string) $trusted['quote_payload_digest'],
            'verification_id' => null,
            'verified_at' => Carbon::createFromTimestamp($issuedAt)->toIso8601String(),
            'price' => ['decimal_price' => (string) data_get($trusted, 'selection.decimal_price')],
            'event' => (array) data_get($trusted, 'event', []),
            'market' => (array) data_get($trusted, 'market', []),
            'selection' => (array) data_get($trusted, 'selection', []),
            'source' => (array) data_get($trusted, 'source', []),
            'settlement_identity' => (array) data_get($trusted, 'settlement_identity', []),
        ];
    }

    private function eligibleProviderFailure(string $failure): bool
    {
        $allowed = array_values(array_filter(array_map(
            fn ($value): string => strtolower(trim((string) $value)),
            (array) config('betting.acceptance.prematch_last_good_grace.allowed_failure_codes', [
                'artifact_unavailable',
                'heavy_routes_busy',
                'warming',
            ])
        )));

        return in_array($failure, $allowed, true);
    }

    private function denied(string $reason, array $metadata): array
    {
        return [
            'allowed' => false,
            'reason' => $reason,
            'verified_quote' => null,
            'metadata' => array_merge($metadata, ['grace_reason' => $reason]),
        ];
    }

    private function parseTimestamp(mixed $value): ?Carbon
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
