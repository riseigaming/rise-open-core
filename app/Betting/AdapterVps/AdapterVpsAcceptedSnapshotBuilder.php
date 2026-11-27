<?php

namespace App\Betting\AdapterVps;

use App\Betting\Acceptance\BettingAcceptanceSnapshotBuilder;

class AdapterVpsAcceptedSnapshotBuilder
{
    public function __construct(private readonly BettingAcceptanceSnapshotBuilder $digestBuilder)
    {
    }

    public function build(array $verifiedQuote, array $attributes, string $acceptanceRef, string $requestFingerprint, ?string $idempotencyKey): array
    {
        $stakeAmount = $this->decimalString($attributes['stake_amount'] ?? null);
        $acceptedPrice = $this->decimalString($this->decimalPrice($verifiedQuote));
        $possibleReturn = $stakeAmount !== null && $acceptedPrice !== null
            ? $this->decimalString(((float) $stakeAmount) * ((float) $acceptedPrice))
            : null;

        return [
            'snapshot_version' => 2,
            'acceptance' => [
                'acceptance_ref' => $acceptanceRef,
                'idempotency_key' => $idempotencyKey,
                'request_fingerprint' => $requestFingerprint,
                'cms_user_id' => $attributes['cms_user_id'] ?? null,
                'root_user_id' => $attributes['root_user_id'] ?? null,
                'currency' => (string) ($attributes['currency'] ?? 'TRY'),
                'stake_amount' => $stakeAmount,
                'accepted_price' => $acceptedPrice,
                'possible_return_amount' => $possibleReturn,
                'possible_profit_amount' => $stakeAmount !== null && $possibleReturn !== null
                    ? $this->decimalString((float) $possibleReturn - (float) $stakeAmount)
                    : null,
                'requested_at' => $this->toIso8601($attributes['requested_at'] ?? now()),
                'accepted_at' => null,
            ],
            'quote_ref' => (string) data_get($verifiedQuote, 'quote_ref'),
            'quote_version_ref' => $this->quoteVersionRef($verifiedQuote),
            'quote_payload_digest' => $this->quotePayloadDigest($verifiedQuote),
            'verified_price' => [
                'decimal_price' => $acceptedPrice,
            ],
            'event' => $this->safeBlock((array) data_get($verifiedQuote, 'event', [])),
            'market' => $this->safeBlock((array) data_get($verifiedQuote, 'market', [])),
            'selection' => $this->safeBlock(array_merge(
                (array) data_get($verifiedQuote, 'selection', []),
                ['decimal_price' => $acceptedPrice]
            )),
            'source' => $this->safeBlock((array) data_get($verifiedQuote, 'source', [])),
            'settlement_identity' => $this->safeBlock((array) data_get($verifiedQuote, 'settlement_identity', [])),
            'request' => [
                'context' => $attributes['context'] ?? [],
                'idempotency_key' => $idempotencyKey,
                'request_fingerprint' => $requestFingerprint,
            ],
            'adapter_verification' => [
                'verification_id' => $this->verificationId($verifiedQuote),
                'verification_digest' => $this->digest($verifiedQuote),
                'verified_at' => $this->toIso8601(data_get($verifiedQuote, 'verified_at', now())),
                'status' => (string) data_get($verifiedQuote, 'status'),
                'trading_state' => (string) data_get($verifiedQuote, 'trading_state'),
                'offer_status' => (string) data_get($verifiedQuote, 'offer_status'),
            ],
            'quote_policy' => $this->safeBlock((array) ($attributes['quote_policy'] ?? [])),
        ];
    }

    public function digest(array $value): string
    {
        return hash('sha256', json_encode($this->canonicalize($this->safeBlock($value)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function snapshotDigest(array $snapshot): string
    {
        return $this->digestBuilder->digest($snapshot);
    }

    public function decimalPrice(array $verifiedQuote): ?string
    {
        $value = data_get($verifiedQuote, 'price.decimal_price')
            ?? data_get($verifiedQuote, 'selection.decimal_price')
            ?? data_get($verifiedQuote, 'decimal_price');

        return $this->decimalString($value);
    }

    public function quoteVersionRef(array $verifiedQuote): ?string
    {
        $value = data_get($verifiedQuote, 'quote_version_ref')
            ?? data_get($verifiedQuote, 'quote.quote_version_ref')
            ?? data_get($verifiedQuote, 'quote.version_ref');

        return $value !== null && $value !== '' ? (string) $value : null;
    }

    public function quotePayloadDigest(array $verifiedQuote): ?string
    {
        $value = data_get($verifiedQuote, 'quote_payload_digest')
            ?? data_get($verifiedQuote, 'quote.payload_digest')
            ?? data_get($verifiedQuote, 'payload_digest');

        return $value !== null && $value !== '' ? (string) $value : null;
    }

    public function verificationId(array $verifiedQuote): ?string
    {
        $value = data_get($verifiedQuote, 'adapter_verification_id')
            ?? data_get($verifiedQuote, 'verification_id')
            ?? data_get($verifiedQuote, 'verification.id');

        return $value !== null && $value !== '' ? (string) $value : null;
    }

    private function safeBlock(array $value): array
    {
        foreach ($value as $key => $item) {
            $normalizedKey = strtolower((string) $key);
            if (in_array($normalizedKey, ['raw', 'raw_payload', 'provider_raw_payload', 'payload', 'feed_body', 'auth_token'], true)) {
                unset($value[$key]);
                continue;
            }

            if (is_array($item)) {
                $value[$key] = $this->safeBlock($item);
            }
        }

        return $value;
    }

    private function canonicalize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $isList = $value === [] || array_keys($value) === range(0, count($value) - 1);
        if ($isList) {
            return array_map(fn ($item) => $this->canonicalize($item), $value);
        }

        ksort($value);
        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
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
