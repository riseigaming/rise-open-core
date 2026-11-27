<?php

namespace App\Betting\Acceptance;

use App\Betting\Support\QuoteRefRedactor;
use App\Models\BettingAcceptance;

class BettingAcceptanceSnapshotReadModel
{
    public function __construct(private readonly ?QuoteRefRedactor $quoteRefRedactor = null)
    {
    }

    public function eventLabel(BettingAcceptance $acceptance): string
    {
        return $this->firstNonEmpty([
            optional($acceptance->event)->canonical_name,
            data_get($acceptance->accepted_snapshot_json, 'event.canonical_name'),
            data_get($acceptance->accepted_snapshot_json, 'event.name'),
            data_get($acceptance->accepted_snapshot_json, 'event.label'),
            data_get($acceptance->accepted_snapshot_json, 'settlement_identity.event_name'),
        ], 'Sportsbook bahsi');
    }

    public function marketLabel(BettingAcceptance $acceptance): string
    {
        return $this->firstNonEmpty([
            optional($acceptance->market)->display_name,
            data_get($acceptance->accepted_snapshot_json, 'market.display_name'),
            data_get($acceptance->accepted_snapshot_json, 'market.market_type'),
            data_get($acceptance->accepted_snapshot_json, 'settlement_identity.market_type'),
        ], 'Market');
    }

    public function selectionLabel(BettingAcceptance $acceptance): string
    {
        return $this->firstNonEmpty([
            optional($acceptance->outcome)->display_name,
            optional(optional($acceptance->quoteSelection)->outcome)->display_name,
            data_get($acceptance->accepted_snapshot_json, 'selection.display_name'),
            data_get($acceptance->accepted_snapshot_json, 'selection.label'),
            data_get($acceptance->accepted_snapshot_json, 'selection.name'),
            data_get($acceptance->accepted_snapshot_json, 'outcome.display_name'),
            data_get($acceptance->accepted_snapshot_json, 'settlement_identity.selection_key'),
        ], 'Seçim');
    }

    public function quoteLabel(BettingAcceptance $acceptance): string
    {
        if ($acceptance->quoteVersion) {
            return 'v' . $acceptance->quoteVersion->version_no;
        }

        return $this->firstNonEmpty([
            $acceptance->quote_version_ref,
            data_get($acceptance->accepted_snapshot_json, 'quote.quote_version_ref'),
            data_get($acceptance->accepted_snapshot_json, 'quote_version_ref'),
            $this->redactedQuoteRef($acceptance),
        ], '-');
    }

    public function sourceLabel(BettingAcceptance $acceptance): string
    {
        return $this->firstNonEmpty([
            $acceptance->source_provider_key,
            data_get($acceptance->accepted_snapshot_json, 'source.provider_key'),
            data_get($acceptance->accepted_snapshot_json, 'source.provider'),
            data_get($acceptance->accepted_snapshot_json, 'source.name'),
        ], 'sportsbook');
    }

    public function settlementIdentity(BettingAcceptance $acceptance): array
    {
        $identity = data_get($acceptance->accepted_snapshot_json, 'settlement_identity', []);

        return is_array($identity) ? $this->withoutRawPayload($identity) : [];
    }

    public function safeSnapshotSummary(BettingAcceptance $acceptance): array
    {
        return [
            'snapshot_version' => data_get($acceptance->accepted_snapshot_json, 'snapshot_version'),
            'acceptance_contract_version' => $acceptance->acceptance_contract_version,
            'acceptance_source' => $acceptance->acceptance_source,
            'quote_ref_redacted' => $this->redactedQuoteRef($acceptance),
            'quote_ref_hash' => $this->quoteRefHash($acceptance),
            'quote_version_ref' => $acceptance->quote_version_ref ?: data_get($acceptance->accepted_snapshot_json, 'quote_version_ref'),
            'adapter_verification_digest' => $acceptance->adapter_verification_digest,
            'adapter_payload_digest' => $acceptance->adapter_payload_digest,
            'source_provider_key' => $acceptance->source_provider_key ?: data_get($acceptance->accepted_snapshot_json, 'source.provider_key'),
            'connector_key' => $acceptance->connector_key ?: data_get($acceptance->accepted_snapshot_json, 'source.connector_key'),
            'event_label' => $this->eventLabel($acceptance),
            'market_label' => $this->marketLabel($acceptance),
            'selection_label' => $this->selectionLabel($acceptance),
            'settlement_identity_present' => $this->settlementIdentity($acceptance) !== [],
            'settlement_identity_keys' => array_values(array_keys($this->settlementIdentity($acceptance))),
        ];
    }

    private function firstNonEmpty(array $values, string $fallback): string
    {
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                return $value;
            }
        }

        return $fallback;
    }

    public function redactedQuoteRef(BettingAcceptance $acceptance): string
    {
        return $this->redactor()->label($this->rawQuoteRef($acceptance));
    }

    public function quoteRefHash(BettingAcceptance $acceptance): ?string
    {
        return $this->redactor()->hash($this->rawQuoteRef($acceptance));
    }

    private function withoutRawPayload(array $value): array
    {
        foreach ($value as $key => $item) {
            $normalizedKey = strtolower((string) $key);
            if (in_array($normalizedKey, ['raw', 'raw_payload', 'provider_raw_payload', 'payload', 'feed_body', 'auth_token'], true)) {
                unset($value[$key]);
                continue;
            }

            if (is_array($item)) {
                $value[$key] = $this->withoutRawPayload($item);
            }
        }

        return $value;
    }

    private function rawQuoteRef(BettingAcceptance $acceptance): ?string
    {
        return $acceptance->quote_ref ?: data_get($acceptance->accepted_snapshot_json, 'quote_ref');
    }

    private function redactor(): QuoteRefRedactor
    {
        return $this->quoteRefRedactor ?: app(QuoteRefRedactor::class);
    }
}
