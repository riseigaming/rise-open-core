<?php

namespace App\Betting\Acceptance;

use App\Models\BettingAcceptance;
use App\Models\BettingEvent;
use App\Models\BettingQuoteSelection;
use App\Models\BettingQuoteVersion;
use Illuminate\Support\Str;
use RuntimeException;

class BettingAcceptanceSnapshotBuilder
{
    public function fromAcceptanceRequest(
        BettingQuoteSelection $selection,
        BettingQuoteVersion $quoteVersion,
        BettingEvent $event,
        array $attributes,
        string $requestFingerprint,
        ?string $idempotencyKey
    ): array {
        $selection->loadMissing(['market.outcomes', 'outcome', 'quoteVersion.feed']);

        return $this->buildSnapshot(
            acceptanceRef: (string) ($attributes['acceptance_ref'] ?? Str::uuid()),
            acceptanceIdempotencyKey: $idempotencyKey,
            requestFingerprint: $requestFingerprint,
            cmsUserId: $attributes['cms_user_id'] ?? null,
            rootUserId: $attributes['root_user_id'] ?? null,
            currency: (string) ($attributes['currency'] ?? 'TRY'),
            stakeAmount: $attributes['stake_amount'] ?? null,
            acceptedPrice: $attributes['accepted_price'] ?? $selection->decimal_price,
            requestedAt: $attributes['requested_at'] ?? now(),
            acceptedAt: $attributes['accepted_at'] ?? null,
            quoteVersion: $quoteVersion,
            event: $event,
            selection: $selection,
            requestContext: $attributes['context'] ?? [],
            overrideMeta: $attributes['override_meta'] ?? null,
        );
    }

    public function fromAcceptance(BettingAcceptance $acceptance): array
    {
        $acceptance->loadMissing([
            'quoteVersion.feed',
            'quoteSelection.market.outcomes',
            'quoteSelection.outcome',
            'event',
        ]);

        $quoteVersion = $acceptance->quoteVersion;
        $selection = $acceptance->quoteSelection;
        $event = $acceptance->event;

        if (!$quoteVersion || !$selection || !$event) {
            throw new RuntimeException('Acceptance is missing quote version, selection, or event context.');
        }

        return $this->buildSnapshot(
            acceptanceRef: (string) $acceptance->acceptance_ref,
            acceptanceIdempotencyKey: $acceptance->idempotency_key,
            requestFingerprint: (string) $acceptance->request_fingerprint,
            cmsUserId: $acceptance->cms_user_id,
            rootUserId: $acceptance->root_user_id,
            currency: (string) ($acceptance->currency ?: 'TRY'),
            stakeAmount: $acceptance->stake_amount,
            acceptedPrice: $acceptance->accepted_price,
            requestedAt: $acceptance->requested_at ?: $acceptance->created_at ?: now(),
            acceptedAt: $acceptance->accepted_at,
            quoteVersion: $quoteVersion,
            event: $event,
            selection: $selection,
            requestContext: $acceptance->context ?? [],
            overrideMeta: $acceptance->override_meta ?? null,
        );
    }

    public function digest(array $snapshot): string
    {
        return hash('sha256', json_encode($this->canonicalizeSnapshotValue($snapshot), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function buildSnapshot(
        string $acceptanceRef,
        ?string $acceptanceIdempotencyKey,
        string $requestFingerprint,
        mixed $cmsUserId,
        mixed $rootUserId,
        string $currency,
        mixed $stakeAmount,
        mixed $acceptedPrice,
        mixed $requestedAt,
        mixed $acceptedAt,
        BettingQuoteVersion $quoteVersion,
        BettingEvent $event,
        BettingQuoteSelection $selection,
        array $requestContext,
        mixed $overrideMeta
    ): array {
        $stakeAmount = $this->decimalString($stakeAmount);
        $acceptedPrice = $this->decimalString($acceptedPrice);
        $possibleReturnAmount = $stakeAmount !== null && $acceptedPrice !== null
            ? $this->decimalString(((float) $stakeAmount) * ((float) $acceptedPrice))
            : null;

        return [
            'snapshot_version' => 1,
            'acceptance' => [
                'acceptance_ref' => $acceptanceRef,
                'idempotency_key' => $acceptanceIdempotencyKey,
                'request_fingerprint' => $requestFingerprint,
                'cms_user_id' => $cmsUserId,
                'root_user_id' => $rootUserId,
                'currency' => $currency,
                'stake_amount' => $stakeAmount,
                'accepted_price' => $acceptedPrice,
                'possible_return_amount' => $possibleReturnAmount,
                'possible_profit_amount' => $stakeAmount !== null && $possibleReturnAmount !== null
                    ? $this->decimalString((float) $possibleReturnAmount - (float) $stakeAmount)
                    : null,
                'requested_at' => $this->toIso8601($requestedAt),
                'accepted_at' => $this->toIso8601($acceptedAt),
            ],
            'quote' => [
                'quote_version_id' => (int) $quoteVersion->id,
                'version_no' => (int) $quoteVersion->version_no,
                'version_uuid' => (string) $quoteVersion->version_uuid,
                'quote_status' => (string) $quoteVersion->quote_status,
                'trading_state' => (string) $quoteVersion->trading_state,
                'selection_count' => (int) $quoteVersion->selection_count,
                'compiled_at' => $this->toIso8601($quoteVersion->compiled_at),
                'effective_from' => $this->toIso8601($quoteVersion->effective_from),
                'effective_until' => $this->toIso8601($quoteVersion->effective_until),
                'payload_digest' => $quoteVersion->payload_digest,
                'feed' => [
                    'betting_feed_id' => $quoteVersion->betting_feed_id,
                    'feed_key' => optional($quoteVersion->feed)->feed_key,
                    'display_name' => optional($quoteVersion->feed)->display_name,
                    'adapter_key' => optional($quoteVersion->feed)->adapter_key,
                ],
            ],
            'event' => [
                'event_id' => (int) $event->id,
                'event_key' => (string) $event->event_key,
                'sport_code' => (string) $event->sport_code,
                'canonical_name' => (string) $event->canonical_name,
                'tournament_name' => $event->tournament_name,
                'participant_home' => (string) $event->participant_home,
                'participant_away' => (string) $event->participant_away,
                'starts_at' => $this->toIso8601($event->starts_at),
                'lifecycle_status' => (string) $event->lifecycle_status,
                'trading_status' => (string) $event->trading_status,
                'visibility_status' => (string) $event->visibility_status,
                'front_betting_enabled' => (bool) $event->front_betting_enabled,
                'meta' => $event->meta ?? [],
            ],
            'market' => [
                'market_id' => (int) $selection->betting_market_id,
                'market_type' => (string) optional($selection->market)->market_type,
                'specifier_key' => (string) optional($selection->market)->specifier_key,
                'line' => $this->decimalString(optional($selection->market)->line, 3),
                'line_unit' => optional($selection->market)->line_unit,
                'status' => (string) optional($selection->market)->status,
                'display_name' => optional($selection->market)->display_name,
                'sort_order' => (int) optional($selection->market)->sort_order,
                'meta' => optional($selection->market)->meta ?? [],
            ],
            'selection' => [
                'quote_selection_id' => (int) $selection->id,
                'offer_status' => (string) $selection->offer_status,
                'trading_decision_source' => (string) $selection->trading_decision_source,
                'decimal_price' => $this->decimalString($selection->decimal_price),
                'base_decimal_price' => $this->decimalString($selection->base_decimal_price),
                'margin_factor' => $this->decimalString($selection->margin_factor),
                'implied_probability' => $this->decimalString($selection->implied_probability),
                'meta' => $selection->meta ?? [],
            ],
            'outcome' => [
                'outcome_id' => (int) $selection->betting_outcome_id,
                'outcome_code' => (string) optional($selection->outcome)->outcome_code,
                'display_name' => optional($selection->outcome)->display_name,
                'status' => optional($selection->outcome)->status,
                'sort_order' => (int) optional($selection->outcome)->sort_order,
                'meta' => optional($selection->outcome)->meta ?? [],
            ],
            'source' => [
                'provider_key' => data_get($selection->meta, 'source_reference.provider_key')
                    ?? data_get($selection->market->meta ?? [], 'provider_key')
                    ?? data_get($event->meta ?? [], 'provider_key')
                    ?? optional($quoteVersion->feed)->adapter_key,
                'connector_key' => data_get($selection->meta, 'source_reference.connector_key')
                    ?? data_get($selection->market->meta ?? [], 'connector_key')
                    ?? data_get($event->meta ?? [], 'connector_key'),
                'source_name' => data_get($selection->meta, 'source_reference.source_name')
                    ?? data_get($selection->market->meta ?? [], 'bookmaker_name')
                    ?? data_get($selection->market->meta ?? [], 'source_name')
                    ?? data_get($event->meta ?? [], 'source_name')
                    ?? data_get($selection->meta, 'source_reference.connector_key')
                    ?? optional($quoteVersion->feed)->display_name,
                'source_event_key' => data_get($selection->meta, 'source_reference.source_event_key')
                    ?? data_get($event->meta ?? [], 'source_event_key')
                    ?? $event->event_key,
                'source_market_key' => data_get($selection->meta, 'source_reference.source_market_key')
                    ?? data_get($selection->market->meta ?? [], 'provider_market_key')
                    ?? $selection->market->market_type,
                'source_outcome_key' => data_get($selection->meta, 'source_reference.source_outcome_key')
                    ?? data_get($selection->outcome->meta ?? [], 'source_outcome_key')
                    ?? $selection->outcome->outcome_code,
                'source_observed_at' => $this->toIso8601(
                    data_get($selection->meta, 'source_reference.source_observed_at')
                    ?? data_get($event->meta ?? [], 'source_observed_at')
                    ?? data_get($quoteVersion->meta ?? [], 'source_observed_at')
                ),
            ],
            'request' => [
                'context' => $requestContext,
                'override_meta' => $overrideMeta,
            ],
        ];
    }

    private function canonicalizeSnapshotValue(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $isList = $value === [] || array_keys($value) === range(0, count($value) - 1);
        if ($isList) {
            return array_map(fn ($item) => $this->canonicalizeSnapshotValue($item), $value);
        }

        ksort($value);

        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalizeSnapshotValue($item);
        }

        return $value;
    }

    private function decimalString(mixed $value, int $precision = 6): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return number_format((float) $value, $precision, '.', '');
    }

    private function toIso8601(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if (is_string($value)) {
            return $value;
        }

        return null;
    }
}
