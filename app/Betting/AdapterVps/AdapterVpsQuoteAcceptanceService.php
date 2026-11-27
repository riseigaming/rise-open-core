<?php

namespace App\Betting\AdapterVps;

use App\Betting\Acceptance\AcceptanceService;
use App\Betting\Enums\BettingAcceptanceDecision;
use App\Betting\Enums\BettingAcceptanceStatus;
use App\Betting\Enums\BettingDecisionSource;
use App\Betting\Exceptions\AdapterVpsAcceptanceException;
use App\Betting\Exceptions\IdempotencyConflictException;
use App\Betting\Support\BettingActivityLogService;
use App\Betting\Support\QuoteRefRedactor;
use App\Betting\Support\SportsbookLocalParityService;
use App\Models\BettingAcceptance;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdapterVpsQuoteAcceptanceService
{
    public function __construct(
        private readonly AdapterVpsClient $client,
        private readonly AdapterVpsConfigReader $configReader,
        private readonly AcceptanceService $acceptanceService,
        private readonly AdapterVpsAcceptedSnapshotBuilder $snapshotBuilder,
        private readonly PreMatchLastGoodAcceptancePolicy $lastGoodPolicy,
        private readonly BettingActivityLogService $activityLogService,
        private readonly QuoteRefRedactor $quoteRefRedactor,
    ) {
    }

    public function requestForQuoteRef(string $quoteRef, array $attributes): BettingAcceptance
    {
        app(\App\Services\Finance\PrelaunchFinanceWriteGate::class)->assertWritable();
        if (!app(SportsbookLocalParityService::class)->financeSandboxEnabled()) throw new \RuntimeException('Local sportsbook finance gate is closed.');
        $this->acceptanceService->assertProductAccessForAttributes($attributes);

        $quoteRef = trim($quoteRef);
        if ($quoteRef === '') {
            throw new AdapterVpsAcceptanceException('ADAPTER_VPS_VERIFY_INVALID', 'Adapter VPS quote_ref is required.', 422);
        }

        $localParity = app(SportsbookLocalParityService::class);
        if (app()->environment(['local', 'testing']) && $localParity->enabled() && !$localParity->financeSandboxEnabled()) {
            throw new AdapterVpsAcceptanceException('ADAPTER_VPS_VERIFY_FAILED', 'Local sportsbook parity finance sandbox is disabled.', 503, [
                'reason_code' => 'local_parity_finance_sandbox_required',
            ]);
        }

        $config = $this->configReader->read();
        if (!$config->isUsable()) {
            throw new AdapterVpsAcceptanceException('ADAPTER_VPS_VERIFY_FAILED', 'Adapter VPS config is not usable.', 503, [
                'reason_code' => $config->code(),
            ]);
        }

        if (!$config->allowQuoteVerify()) {
            throw new AdapterVpsAcceptanceException('ADAPTER_VPS_VERIFY_FAILED', 'Adapter VPS quote verification is disabled.', 409, [
                'reason_code' => 'quote_verify_disabled',
            ]);
        }

        if (!$config->allowAcceptance()) {
            throw new AdapterVpsAcceptanceException('ADAPTER_VPS_ACCEPTANCE_DISABLED', 'Adapter VPS quote_ref acceptance is disabled.', 409, [
                'reason_code' => 'acceptance_disabled',
                'quote_ref' => $quoteRef,
            ]);
        }

        $idempotencyKey = $this->resolveIdempotencyKey($attributes);
        $existingReplay = $this->existingIdempotentReplay($idempotencyKey, $this->preVerifyFingerprint($quoteRef, $attributes));
        if ($existingReplay instanceof BettingAcceptance) {
            return $existingReplay;
        }

        // Cheap local admission before provider I/O; authoritative check remains inside acceptance transaction.
        $this->acceptanceService->normalizeWalletAttributesForAcceptance($attributes);

        $verify = $this->client->verifyQuote($quoteRef, [
            'stake_amount' => $attributes['stake_amount'] ?? null,
            'currency' => $attributes['currency'] ?? 'TRY',
            'accepted_price' => $attributes['accepted_price'] ?? null,
            'request_id' => data_get($attributes, 'context.request_id'),
            'idempotency_key' => $idempotencyKey,
        ]);

        $quotePolicy = null;
        if (($verify['status'] ?? '') !== 'ok') {
            $decision = $this->lastGoodPolicy->evaluate($quoteRef, $verify, $attributes);
            if (!($decision['allowed'] ?? false)) {
                throw new AdapterVpsAcceptanceException('ADAPTER_VPS_VERIFY_FAILED', 'Adapter VPS quote verification failed.', 409, [
                    'reason_code' => (string) ($verify['code'] ?? 'unavailable'),
                    'grace_reason' => (string) ($decision['reason'] ?? 'prematch_last_good_grace_denied'),
                    'provider_failure_reason' => (string) ($verify['code'] ?? 'unavailable'),
                    'quote_ref' => $quoteRef,
                ]);
            }

            $verifiedQuote = (array) ($decision['verified_quote'] ?? []);
            $quotePolicy = (array) ($decision['metadata'] ?? []);
        } else {
            $verifiedQuote = (array) ($verify['body'] ?? []);
            $quotePolicy = $this->lastGoodPolicy->freshMetadata($verifiedQuote);
        }

        $this->assertVerifiedQuoteIsAcceptable($quoteRef, $verifiedQuote, $attributes);
        $attributes['quote_policy'] = $quotePolicy;
        $attributes['context'] = array_merge((array) ($attributes['context'] ?? []), [
            'quote_policy' => $quotePolicy,
        ]);

        try {
            return DB::transaction(function () use ($quoteRef, $attributes, $idempotencyKey, $verifiedQuote, $quotePolicy) {
                $requestFingerprint = $this->requestFingerprint($quoteRef, $attributes);
                [$attributes, $walletContext] = $this->acceptanceService->normalizeWalletAttributesForAcceptance($attributes);

                if ($idempotencyKey !== null) {
                    $existing = BettingAcceptance::query()
                        ->where('idempotency_key', $idempotencyKey)
                        ->lockForUpdate()
                        ->first();

                    if ($existing instanceof BettingAcceptance) {
                        return $this->resolveIdempotentReplay($existing, $idempotencyKey, $requestFingerprint);
                    }
                }

                $this->acceptanceService->assertStakeWithinLimit($attributes);

                $acceptanceRef = (string) Str::uuid();
                $snapshot = $this->snapshotBuilder->build($verifiedQuote, array_merge($attributes, [
                    'requested_at' => now(),
                ]), $acceptanceRef, $requestFingerprint, $idempotencyKey);
                $acceptedPrice = $this->snapshotBuilder->decimalPrice($verifiedQuote);

                $acceptance = BettingAcceptance::query()->create([
                    'acceptance_ref' => $acceptanceRef,
                    'acceptance_contract_version' => 'adapter_vps_v2',
                    'acceptance_source' => 'adapter_vps',
                    'idempotency_key' => $idempotencyKey,
                    'request_fingerprint' => $requestFingerprint,
                    'quote_ref' => $quoteRef,
                    'quote_version_ref' => $this->snapshotBuilder->quoteVersionRef($verifiedQuote),
                    'adapter_verification_id' => $this->snapshotBuilder->verificationId($verifiedQuote),
                    'adapter_verification_digest' => $this->snapshotBuilder->digest($verifiedQuote),
                    'adapter_payload_digest' => $this->snapshotBuilder->quotePayloadDigest($verifiedQuote),
                    'source_provider_key' => data_get($verifiedQuote, 'source.provider_key'),
                    'connector_key' => data_get($verifiedQuote, 'source.connector_key'),
                    'betting_quote_version_id' => null,
                    'betting_quote_selection_id' => null,
                    'betting_event_id' => null,
                    'betting_market_id' => null,
                    'betting_outcome_id' => null,
                    'cms_user_id' => $attributes['cms_user_id'] ?? null,
                    'root_user_id' => $attributes['root_user_id'] ?? null,
                    'currency' => $attributes['currency'] ?? 'TRY',
                    'stake_amount' => $attributes['stake_amount'],
                    'accepted_price' => $acceptedPrice,
                    'status' => BettingAcceptanceStatus::PENDING,
                    'decision' => BettingAcceptanceDecision::PENDING,
                    'decision_source' => BettingDecisionSource::SYSTEM,
                    'requested_at' => now(),
                    'last_transition_at' => now(),
                    'context' => array_merge($attributes['context'] ?? [], [
                        'request_source' => data_get($attributes, 'context.request_source', 'cms_betting_api_adapter_vps'),
                        'adapter_vps_quote_ref' => $quoteRef,
                        'adapter_vps_acceptance_contract_version' => 'adapter_vps_v2',
                    ]),
                    'accepted_snapshot_json' => $snapshot,
                    'accepted_snapshot_digest' => $this->snapshotBuilder->snapshotDigest($snapshot),
                ]);

                $this->acceptanceService->debitStakeForAcceptance($acceptance, $attributes, $walletContext);

                $this->activityLogService->logAcceptance(
                    $acceptance,
                    'acceptance.adapter_vps_v2_requested',
                    'Adapter VPS quote_ref acceptance request recorded.',
                    [
                        'quote_ref_hash' => $this->quoteRefRedactor->hash($quoteRef),
                        'quote_version_ref' => $acceptance->quote_version_ref,
                        'source_provider_key' => $acceptance->source_provider_key,
                        'connector_key' => $acceptance->connector_key,
                        'idempotency_key' => $idempotencyKey,
                        'quote_source' => (string) ($quotePolicy['quote_source'] ?? 'fresh'),
                        'quote_age_seconds' => $quotePolicy['quote_age_seconds'] ?? null,
                        'grace_used' => (bool) ($quotePolicy['grace_used'] ?? false),
                        'grace_reason' => $quotePolicy['grace_reason'] ?? null,
                        'provider_failure_reason' => $quotePolicy['provider_failure_reason'] ?? null,
                        'policy_version' => $quotePolicy['policy_version'] ?? PreMatchLastGoodAcceptancePolicy::POLICY_VERSION,
                    ]
                );

                return $acceptance->fresh(['settlement']);
            }, 3);
        } catch (QueryException $exception) {
            if ($idempotencyKey === null || !$this->isIdempotencyCollision($exception)) {
                throw $exception;
            }

            $existing = BettingAcceptance::query()->where('idempotency_key', $idempotencyKey)->first();
            if (!$existing instanceof BettingAcceptance) {
                throw $exception;
            }

            return $this->resolveIdempotentReplay($existing, $idempotencyKey, $this->requestFingerprint($quoteRef, $attributes));
        }
    }

    private function assertVerifiedQuoteIsAcceptable(string $quoteRef, array $verifiedQuote, array $attributes): void
    {
        $status = strtolower((string) data_get($verifiedQuote, 'status'));
        $tradingState = strtolower((string) data_get($verifiedQuote, 'trading_state', data_get($verifiedQuote, 'quote.trading_state')));
        $offerStatus = strtolower((string) data_get($verifiedQuote, 'offer_status', data_get($verifiedQuote, 'selection.offer_status')));
        $terminal = ['expired', 'stale', 'suspended', 'closed', 'not_found', 'price_changed'];

        foreach ([$status, $tradingState, $offerStatus] as $state) {
            if (in_array($state, $terminal, true)) {
                throw new AdapterVpsAcceptanceException('ADAPTER_VPS_QUOTE_REJECTED', 'Adapter VPS quote is not open.', 409, [
                    'reason_code' => $state,
                    'quote_ref' => $quoteRef,
                ]);
            }
        }

        if ($status !== 'open' || $tradingState !== 'open' || $offerStatus !== 'open') {
            throw new AdapterVpsAcceptanceException('ADAPTER_VPS_QUOTE_REJECTED', 'Adapter VPS quote is not open.', 409, [
                'reason_code' => 'non_open',
                'quote_ref' => $quoteRef,
            ]);
        }

        if ((string) data_get($verifiedQuote, 'quote_ref') !== $quoteRef) {
            throw new AdapterVpsAcceptanceException('ADAPTER_VPS_VERIFY_INVALID', 'Adapter VPS quote_ref verification mismatch.', 409, [
                'reason_code' => 'quote_ref_mismatch',
                'quote_ref' => $quoteRef,
            ]);
        }

        $acceptedPrice = $this->snapshotBuilder->decimalPrice($verifiedQuote);
        if ($acceptedPrice === null) {
            throw new AdapterVpsAcceptanceException('ADAPTER_VPS_VERIFY_INVALID', 'Adapter VPS verified price is missing.', 409, [
                'reason_code' => 'missing_price',
                'quote_ref' => $quoteRef,
            ]);
        }

        if (isset($attributes['accepted_price']) && round((float) $attributes['accepted_price'], 6) !== round((float) $acceptedPrice, 6)) {
            throw new AdapterVpsAcceptanceException('ADAPTER_VPS_QUOTE_REJECTED', 'Adapter VPS verified price changed.', 409, [
                'reason_code' => 'price_changed',
                'quote_ref' => $quoteRef,
            ]);
        }

        foreach ([
            'quote_payload_digest' => $this->snapshotBuilder->quotePayloadDigest($verifiedQuote),
            'source' => data_get($verifiedQuote, 'source'),
            'settlement_identity' => data_get($verifiedQuote, 'settlement_identity'),
        ] as $field => $value) {
            if ($value === null || $value === '' || (is_array($value) && $value === [])) {
                throw new AdapterVpsAcceptanceException('ADAPTER_VPS_VERIFY_INVALID', 'Adapter VPS verified quote is missing required data.', 409, [
                    'reason_code' => 'missing_' . $field,
                    'quote_ref' => $quoteRef,
                ]);
            }
        }
    }

    private function existingIdempotentReplay(?string $idempotencyKey, string $requestFingerprint): ?BettingAcceptance
    {
        if ($idempotencyKey === null) {
            return null;
        }

        $existing = BettingAcceptance::query()->where('idempotency_key', $idempotencyKey)->first();
        if (!$existing instanceof BettingAcceptance) {
            return null;
        }

        return $this->resolveIdempotentReplay($existing, $idempotencyKey, $requestFingerprint);
    }

    private function resolveIdempotentReplay(BettingAcceptance $acceptance, string $idempotencyKey, string $requestFingerprint): BettingAcceptance
    {
        if ((string) $acceptance->request_fingerprint !== $requestFingerprint) {
            throw new IdempotencyConflictException($idempotencyKey);
        }

        $this->activityLogService->logAcceptance(
            $acceptance,
            'acceptance.idempotent_replay',
            'Acceptance request replayed with existing idempotency key.',
            [
                'idempotency_key' => $idempotencyKey,
                'quote_ref_hash' => $this->quoteRefRedactor->hash($acceptance->quote_ref),
            ]
        );

        return $acceptance->fresh(['settlement']);
    }

    private function requestFingerprint(string $quoteRef, array $attributes): string
    {
        return $this->preVerifyFingerprint($quoteRef, $attributes);
    }

    private function preVerifyFingerprint(string $quoteRef, array $attributes): string
    {
        return hash('sha256', json_encode([
            'acceptance_contract_version' => 'adapter_vps_v2',
            'quote_ref' => $quoteRef,
            'cms_user_id' => $attributes['cms_user_id'] ?? null,
            'root_user_id' => $attributes['root_user_id'] ?? null,
            'currency' => $attributes['currency'] ?? 'TRY',
            'stake_amount' => (string) ($attributes['stake_amount'] ?? ''),
            'accepted_price' => (string) ($attributes['accepted_price'] ?? ''),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function resolveIdempotencyKey(array $attributes): ?string
    {
        $key = trim((string) ($attributes['idempotency_key'] ?? $attributes['client_request_id'] ?? ''));

        return $key !== '' ? $key : null;
    }

    private function isIdempotencyCollision(QueryException $exception): bool
    {
        $message = Str::lower($exception->getMessage());

        return Str::contains($message, [
            'betting_acceptances_idempotency_unique',
            'betting_acceptances.idempotency_key',
            'idempotency_key',
        ]);
    }
}
