<?php

namespace App\Betting\AdapterVps;

use App\Betting\Support\SportsbookLocalParityService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class AdapterVpsClient
{
    private $configReader;

    public function __construct(AdapterVpsConfigReader $configReader)
    {
        $this->configReader = $configReader;
    }

    public function smoke(): array
    {
        $config = $this->configReader->read();
        $configSummary = $config->safeSummary();

        if (!$config->isUsable()) {
            return [
                'status' => 'disabled',
                'code' => $config->code(),
                'reason' => $config->reason(),
                'config' => $configSummary,
                'ready' => false,
                'provider_summary' => [],
                'connector_runs' => [],
                'read_source' => null,
                'requests_remaining' => null,
                'last_successful_run_at' => null,
                'event_count' => null,
                'offer_count' => null,
                'coverage' => [],
                'checks' => [],
            ];
        }

        $checks = [
            'health' => $this->requestJson($config, '/health', false),
            'ready' => $this->requestJson($config, '/ready', false),
            'connectors' => $this->requestJson($config, '/connectors/health', true),
            'offers' => $this->requestJson($config, '/offers/current', true),
        ];

        foreach ($checks as $check) {
            if (!($check['ok'] ?? false)) {
                return [
                    'status' => 'error',
                    'code' => $check['code'] ?? 'unavailable',
                    'reason' => $check['reason'] ?? 'Adapter VPS smoke check failed.',
                    'config' => $configSummary,
                    'ready' => false,
                    'provider_summary' => [],
                    'connector_runs' => [],
                    'read_source' => null,
                    'requests_remaining' => null,
                    'last_successful_run_at' => null,
                    'event_count' => null,
                    'offer_count' => null,
                    'coverage' => [],
                    'checks' => $this->safeChecks($checks),
                ];
            }
        }

        $healthBody = $checks['health']['body'];
        $readyBody = $checks['ready']['body'];
        $connectorsBody = $checks['connectors']['body'];
        $offersBody = $checks['offers']['body'];
        $connectors = $this->connectorList($connectorsBody, $healthBody, $readyBody);

        return [
            'status' => 'ok',
            'code' => 'ok',
            'reason' => null,
            'config' => $configSummary,
            'ready' => (bool) data_get($readyBody, 'ready', data_get($readyBody, 'status') === 'ready'),
            'provider_summary' => $this->providerSummary($connectors),
            'connector_runs' => $this->connectorRuns($connectors),
            'connectors_summary' => (array) data_get($connectorsBody, 'summary', []),
            'read_source' => $this->readSource($offersBody),
            'requests_remaining' => $this->requestsRemaining($connectorsBody, $offersBody),
            'last_successful_run_at' => $this->lastSuccessfulRunAt($connectorsBody, $offersBody),
            'heartbeat_freshness_seconds' => $this->heartbeatFreshnessSeconds($connectorsBody),
            'event_count' => $this->eventCount($offersBody),
            'offer_count' => $this->offerCount($offersBody),
            'coverage' => $this->coverageSummary($connectorsBody),
            'checks' => $this->safeChecks($checks),
        ];
    }

    public function currentOffers(?int $readTimeoutMsOverride = null): array
    {
        $config = $this->configReader->read();
        $configSummary = $config->safeSummary();

        if (!$config->isUsable()) {
            return [
                'status' => 'disabled',
                'code' => $config->code(),
                'reason' => $config->reason(),
                'config' => $configSummary,
                'body' => null,
                'check' => null,
            ];
        }

        if (!$config->allowPublicPrematch()) {
            return [
                'status' => 'disabled',
                'code' => 'disabled',
                'reason' => 'Adapter VPS public pre-match read path is disabled.',
                'config' => $configSummary,
                'body' => null,
                'check' => null,
            ];
        }

        $check = $this->requestJson($config, '/offers/current', true, 'get', [], $readTimeoutMsOverride);

        if (!($check['ok'] ?? false)) {
            return [
                'status' => 'error',
                'code' => $check['code'] ?? 'unavailable',
                'reason' => $check['reason'] ?? 'Adapter VPS offers request failed.',
                'http_status' => $check['status'] ?? null,
                'config' => $configSummary,
                'body' => null,
                'check' => $this->safeChecks(['offers' => $check])['offers'],
            ];
        }

        return [
            'status' => 'ok',
            'code' => 'ok',
            'reason' => null,
            'config' => $configSummary,
            'body' => $check['body'],
            'check' => $this->safeChecks(['offers' => $check])['offers'],
        ];
    }

    public function currentCompactOffers(?int $readTimeoutMsOverride = null): array
    {
        return $this->currentOffersFromPath(
            '/offers/current/compact',
            $readTimeoutMsOverride,
            'Adapter VPS compact offers request failed.'
        );
    }

    /** Download once to a spillable stream; never decode a complete book here. */
    public function consumeCompactOffers(callable $consumer): array
    {
        $demoStore = app(\App\Betting\Application\Offers\CurrentOffersSnapshotStore::class);
        if ($demoStore->demoMode() === 'manual') {
            return ['status' => 'disabled', 'code' => 'offline_demo', 'body' => null];
        }
        $config = $this->configReader->read();
        if (!$config->isUsable() || !$config->allowPublicPrematch()) {
            return ['status' => 'error', 'code' => $config->code(), 'body' => null];
        }
        $stream = fopen('php://temp/maxmemory:1048576', 'w+b');
        try {
            if (!is_resource($stream)) {
                throw new \RuntimeException('Compact stream unavailable.');
            }
            $response = Http::acceptJson()->withToken($config->authToken())->withOptions([
                'connect_timeout' => $demoStore->localAutoDemoEnabled() ? min(2, $config->connectTimeoutSeconds()) : $config->connectTimeoutSeconds(),
                'timeout' => $demoStore->localAutoDemoEnabled() ? min(8, $config->readTimeoutSeconds()) : $config->readTimeoutSeconds(),
                'sink' => $stream,
                'progress' => static function ($total, $downloaded): void {
                    if ($total > 64 * 1024 * 1024 || $downloaded > 64 * 1024 * 1024) {
                        throw new \RuntimeException('Compact transfer budget exceeded.');
                    }
                },
            ])->get($this->url($config, '/offers/current/compact'));
            if (!$response->successful()) {
                $demoStore->recordDemoAccessFailure('http_error', $response->status());
                return ['status' => 'error', 'code' => $response->status() === 429 ? 'rate_limited' : 'unavailable', 'http_status' => $response->status(), 'body' => null];
            }
            // Laravel HTTP fakes do not implement Guzzle's sink option.
            if ((fstat($stream)['size'] ?? 0) === 0) {
                $body = $response->toPsrResponse()->getBody();
                while (!$body->eof()) {
                    $chunk = $body->read(8192);
                    if (ftell($stream) + strlen($chunk) > 64 * 1024 * 1024 || fwrite($stream, $chunk) !== strlen($chunk)) {
                        throw new \RuntimeException('Compact transfer budget exceeded.');
                    }
                }
            }
            rewind($stream);
            return ['status' => 'ok', 'code' => 'ok', 'body' => $consumer($stream)];
        } catch (ConnectionException $error) {
            $demoStore->recordDemoAccessFailure('connection_failed');
            return ['status' => 'error', 'code' => $this->connectionCode($error), 'body' => null];
        } catch (Throwable $error) {
            // Never log a transport exception containing credentials/provider data.
            return ['status' => 'error', 'code' => 'invalid_compact_inventory', 'body' => null];
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    private function currentOffersFromPath(string $path, ?int $readTimeoutMsOverride, string $failureReason): array
    {
        $config = $this->configReader->read();
        $configSummary = $config->safeSummary();

        if (!$config->isUsable()) {
            return [
                'status' => 'disabled',
                'code' => $config->code(),
                'reason' => $config->reason(),
                'config' => $configSummary,
                'body' => null,
                'check' => null,
            ];
        }

        if (!$config->allowPublicPrematch()) {
            return [
                'status' => 'disabled',
                'code' => 'disabled',
                'reason' => 'Adapter VPS public pre-match read path is disabled.',
                'config' => $configSummary,
                'body' => null,
                'check' => null,
            ];
        }

        $check = $this->requestJson($config, $path, true, 'get', [], $readTimeoutMsOverride);
        if (!($check['ok'] ?? false)) {
            return [
                'status' => 'error',
                'code' => $check['code'] ?? 'unavailable',
                'reason' => $check['reason'] ?? $failureReason,
                'http_status' => $check['status'] ?? null,
                'config' => $configSummary,
                'body' => null,
                'check' => $this->safeChecks(['offers' => $check])['offers'],
            ];
        }

        return [
            'status' => 'ok',
            'code' => 'ok',
            'reason' => null,
            'config' => $configSummary,
            'body' => $check['body'],
            'check' => $this->safeChecks(['offers' => $check])['offers'],
        ];
    }

    public function currentEventOffers(array $eventIds, ?int $readTimeoutMsOverride = null): array
    {
        $config = $this->configReader->read();
        $configSummary = $config->safeSummary();
        $eventIds = collect($eventIds)
            ->map(fn ($id): string => trim((string) $id))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($eventIds === []) {
            return [
                'status' => 'error',
                'code' => 'missing_event_ids',
                'reason' => 'Adapter VPS exact event refresh requires event ids.',
                'config' => $configSummary,
                'body' => null,
                'check' => null,
            ];
        }

        if (!$config->isUsable()) {
            return [
                'status' => 'disabled',
                'code' => $config->code(),
                'reason' => $config->reason(),
                'config' => $configSummary,
                'body' => null,
                'check' => null,
            ];
        }

        if (!$config->allowPublicPrematch()) {
            return [
                'status' => 'disabled',
                'code' => 'disabled',
                'reason' => 'Adapter VPS public pre-match read path is disabled.',
                'config' => $configSummary,
                'body' => null,
                'check' => null,
            ];
        }

        $query = http_build_query(['event_ids' => implode(',', $eventIds)], '', '&', PHP_QUERY_RFC3986);
        $check = $this->requestJson($config, '/offers/current/events?' . $query, true, 'get', [], $readTimeoutMsOverride);

        if (!($check['ok'] ?? false)) {
            return [
                'status' => 'error',
                'code' => $check['code'] ?? 'unavailable',
                'reason' => $check['reason'] ?? 'Adapter VPS exact event offers request failed.',
                'config' => $configSummary,
                'body' => null,
                'check' => $this->safeChecks(['offers' => $check])['offers'],
            ];
        }

        return [
            'status' => 'ok',
            'code' => 'ok',
            'reason' => null,
            'config' => $configSummary,
            'body' => $check['body'],
            'check' => $this->safeChecks(['offers' => $check])['offers'],
        ];
    }

    public function telemetry(): array
    {
        $config = $this->configReader->read();
        $configSummary = $config->safeSummary();

        if (!$config->isUsable()) {
            return [
                'status' => 'disabled',
                'code' => $config->code(),
                'reason' => $config->reason(),
                'config' => $configSummary,
                'ready' => false,
                'provider_summary' => [],
                'connector_runs' => [],
                'connectors_summary' => [],
                'requests_remaining' => null,
                'last_successful_run_at' => null,
                'heartbeat_freshness_seconds' => null,
                'coverage' => [],
                'checks' => [],
            ];
        }

        $check = $this->requestJson($config, '/connectors/health', true);
        if (!($check['ok'] ?? false)) {
            return [
                'status' => 'error',
                'code' => $check['code'] ?? 'unavailable',
                'reason' => $check['reason'] ?? 'Adapter VPS telemetry request failed.',
                'config' => $configSummary,
                'ready' => false,
                'provider_summary' => [],
                'connector_runs' => [],
                'connectors_summary' => [],
                'requests_remaining' => null,
                'last_successful_run_at' => null,
                'heartbeat_freshness_seconds' => null,
                'coverage' => [],
                'checks' => $this->safeChecks(['connectors' => $check]),
            ];
        }

        $connectorsBody = $check['body'];
        $connectors = $this->connectorList($connectorsBody, [], []);

        return [
            'status' => 'ok',
            'code' => 'ok',
            'reason' => null,
            'config' => $configSummary,
            'ready' => true,
            'provider_summary' => $this->providerSummary($connectors),
            'connector_runs' => $this->connectorRuns($connectors),
            'connectors_summary' => (array) data_get($connectorsBody, 'summary', []),
            'requests_remaining' => $this->requestsRemaining($connectorsBody, []),
            'last_successful_run_at' => $this->lastSuccessfulRunAt($connectorsBody, []),
            'heartbeat_freshness_seconds' => $this->heartbeatFreshnessSeconds($connectorsBody),
            'coverage' => $this->coverageSummary($connectorsBody),
            'checks' => $this->safeChecks(['connectors' => $check]),
        ];
    }

    /** Recovery evidence only: HTTP success and a recent CMS write are not source freshness. */
    public function offlineDemoRecoveryEvidence(?string $generatedAt, array $providerKeys): array
    {
        $artifactAge = $this->recoveryTimestampAge($generatedAt);
        if ($artifactAge === null || $artifactAge > (int) config('production_health.adapter.artifact_max_age_seconds', 180)) {
            return ['verified' => false, 'reason' => 'artifact_freshness_unverified'];
        }
        $providerKeys = array_values(array_unique($providerKeys));
        if ($providerKeys === [] || in_array('', $providerKeys, true) || in_array('adapter_vps', $providerKeys, true)) {
            return ['verified' => false, 'reason' => 'provider_identity_unverified'];
        }
        $config = $this->configReader->read();
        if (!$config->isUsable()) {
            return ['verified' => false, 'reason' => 'provider_health_unavailable'];
        }
        $check = $this->requestJson($config, '/connectors/health', true, 'get', [], 8000);
        if (!($check['ok'] ?? false)) {
            return ['verified' => false, 'reason' => 'provider_health_unavailable'];
        }
        $connectors = $this->connectorList($check['body'], [], []);
        $syncTimes = [];
        foreach ($providerKeys as $providerKey) {
            $matches = array_values(array_filter($connectors, static fn (array $connector): bool =>
                ($connector['provider_key'] ?? $connector['key'] ?? '') === $providerKey));
            if (count($matches) !== 1) {
                return ['verified' => false, 'reason' => 'provider_identity_unverified'];
            }
            $connector = $matches[0];
            $syncAt = data_get($connector, 'provider_state.last_successful_sync_at');
            $syncAge = $this->recoveryTimestampAge($syncAt);
            if (($connector['implementation_status'] ?? '') !== 'live'
                || ($connector['connector_status'] ?? '') !== 'ok'
                || ($connector['live_status'] ?? '') !== 'ready'
                || data_get($connector, 'provider_state.credential_state') !== 'active'
                || $syncAge === null
                || $syncAge > (int) config('production_health.adapter.provider_max_age_seconds', 180)) {
                return ['verified' => false, 'reason' => 'provider_sync_unverified'];
            }
            $syncTimes[$providerKey] = $syncAt;
        }
        // Recheck after the health request; a slow response must not extend artifact age.
        $artifactAge = $this->recoveryTimestampAge($generatedAt);
        if ($artifactAge === null || $artifactAge > (int) config('production_health.adapter.artifact_max_age_seconds', 180)) {
            return ['verified' => false, 'reason' => 'artifact_freshness_unverified'];
        }
        return ['verified' => true, 'reason' => 'source_verified', 'source_generated_at' => $generatedAt,
            'provider_sync_at' => $syncTimes, 'checked_at' => now()->toIso8601String()];
    }

    private function recoveryTimestampAge($value): ?int
    {
        // Do not let Carbon interpret missing/relative dates as "now".
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/D', $value)) return null;
        try {
            $timestamp = new \DateTimeImmutable($value);
            $errors = \DateTimeImmutable::getLastErrors();
            if (is_array($errors) && ($errors['warning_count'] || $errors['error_count'])) return null;
            $age = now()->timestamp - $timestamp->getTimestamp();
            return $age >= 0 ? $age : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function verifyQuote(string $quoteRef, array $payload = []): array
    {
        if (app(\App\Betting\Application\Offers\CurrentOffersSnapshotStore::class)->demoActive()) {
            return ['status' => 'disabled', 'code' => 'offline_demo', 'body' => null];
        }
        $config = $this->configReader->read();
        $configSummary = $config->safeSummary();

        if ($this->shouldUseLocalSandboxVerify($quoteRef)) {
            return $this->verifyLocalSandboxQuote($quoteRef, $configSummary);
        }

        if (!$config->isUsable()) {
            return [
                'status' => 'disabled',
                'code' => $config->code(),
                'reason' => $config->reason(),
                'config' => $configSummary,
                'body' => null,
                'check' => null,
            ];
        }

        if (!$config->allowQuoteVerify()) {
            return [
                'status' => 'disabled',
                'code' => 'disabled',
                'reason' => 'Adapter VPS quote verification is disabled.',
                'config' => $configSummary,
                'body' => null,
                'check' => null,
            ];
        }

        $check = $this->requestJson($config, '/quotes/verify', true, 'post', array_merge($payload, [
            'quote_ref' => $quoteRef,
        ]));

        if (!($check['ok'] ?? false)) {
            return [
                'status' => 'error',
                'code' => $check['code'] ?? 'unavailable',
                'reason' => $check['reason'] ?? 'Adapter VPS quote verification failed.',
                'config' => $configSummary,
                'body' => null,
                'check' => $this->safeChecks(['verify' => $check])['verify'],
            ];
        }

        return [
            'status' => 'ok',
            'code' => 'ok',
            'reason' => null,
            'config' => $configSummary,
            'body' => $this->withoutRawPayload($check['body']),
            'check' => $this->safeChecks(['verify' => $check])['verify'],
        ];
    }

    private function shouldUseLocalSandboxVerify(string $quoteRef): bool
    {
        return app()->environment(['local', 'testing'])
            && str_starts_with($quoteRef, 'sandbox:')
            && app(SportsbookLocalParityService::class)->financeSandboxEnabled();
    }

    private function verifyLocalSandboxQuote(string $quoteRef, array $configSummary): array
    {
        $quote = app(SportsbookLocalParityService::class)->verifiedQuoteFor($quoteRef);

        if (!is_array($quote)) {
            return [
                'status' => 'error',
                'code' => 'expired',
                'reason' => 'Local sportsbook sandbox quote is unavailable.',
                'config' => $configSummary,
                'body' => null,
                'check' => [
                    'ok' => false,
                    'code' => 'expired',
                    'reason' => 'Local sportsbook sandbox quote is unavailable.',
                    'status' => 409,
                ],
            ];
        }

        return [
            'status' => 'ok',
            'code' => 'ok',
            'reason' => null,
            'config' => $configSummary,
            'body' => $this->withoutRawPayload($quote),
            'check' => [
                'ok' => true,
                'code' => 'ok',
                'reason' => null,
                'status' => 200,
            ],
        ];
    }

    private function requestJson(
        AdapterVpsRuntimeConfig $config,
        string $path,
        bool $authenticated,
        string $method = 'get',
        array $payload = [],
        ?int $readTimeoutMsOverride = null
    ): array
    {
        $demoStore = app(\App\Betting\Application\Offers\CurrentOffersSnapshotStore::class);
        if ($demoStore->demoMode() === 'manual') {
            return ['ok' => false, 'code' => 'offline_demo', 'reason' => 'Offline display only.', 'status' => null];
        }
        $startedAt = microtime(true);
        $requestStartedAt = null;
        $responseReceivedAt = null;
        $transferTiming = [];
        try {
            $readTimeoutSeconds = $readTimeoutMsOverride !== null
                ? max(0.001, $readTimeoutMsOverride / 1000)
                : $config->readTimeoutSeconds();
            $pending = Http::acceptJson()
                ->asJson()
                ->withOptions([
                    'connect_timeout' => $config->connectTimeoutSeconds(),
                    'timeout' => $readTimeoutSeconds,
                ]);

            if ($authenticated) {
                $pending = $pending->withToken($config->authToken());
            }

            $requestStartedAt = microtime(true);
            $response = strtolower($method) === 'post'
                ? $pending->post($this->url($config, $path), $payload)
                : $pending->get($this->url($config, $path));
            $responseReceivedAt = microtime(true);
            $transferTiming = $this->transferTiming(
                (array) $response->handlerStats(),
                $responseReceivedAt - $requestStartedAt
            );
        } catch (ConnectionException $exception) {
            if (str_starts_with($path, '/offers/')) $demoStore->recordDemoAccessFailure('connection_failed');
            $diagnostic = $this->connectionDiagnostic($config, $path, $exception, $startedAt);
            $diagnostic['timing'] = $this->requestTiming(
                $startedAt,
                $requestStartedAt,
                microtime(true),
                null,
                $transferTiming,
                []
            );
            Log::warning('adapter_vps.connection_failed', $diagnostic);

            return [
                'ok' => false,
                'code' => $this->connectionCode($exception),
                'reason' => 'Adapter VPS connection failed.',
                'status' => null,
                'diagnostic' => $diagnostic,
                'timing' => $diagnostic['timing'],
            ];
        } catch (Throwable $exception) {
            $diagnostic = $this->connectionDiagnostic($config, $path, $exception, $startedAt);
            $diagnostic['timing'] = $this->requestTiming(
                $startedAt,
                $requestStartedAt,
                microtime(true),
                null,
                $transferTiming,
                []
            );
            Log::warning('adapter_vps.request_failed', $diagnostic);

            return [
                'ok' => false,
                'code' => 'unavailable',
                'reason' => 'Adapter VPS request failed.',
                'status' => null,
                'diagnostic' => $diagnostic,
                'timing' => $diagnostic['timing'],
            ];
        }

        $serverTiming = $this->parseServerTiming((string) $response->header('Server-Timing'));
        if (str_starts_with($path, '/offers/')) $demoStore->recordDemoAccessFailure('http_error', $response->status());

        if (in_array($response->status(), [401, 403], true)) {
            return [
                'ok' => false,
                'code' => 'auth_failed',
                'reason' => 'Adapter VPS rejected CMS authentication.',
                'status' => $response->status(),
                'timing' => $this->requestTiming($startedAt, $requestStartedAt, $responseReceivedAt, null, $transferTiming, $serverTiming),
            ];
        }

        $decodeStartedAt = microtime(true);
        $body = $response->json();
        $decodeFinishedAt = microtime(true);
        if (!is_array($body)) {
            $body = null;
        }

        $timing = $this->requestTiming(
            $startedAt,
            $requestStartedAt,
            $responseReceivedAt,
            ($decodeFinishedAt - $decodeStartedAt) * 1000,
            $transferTiming,
            $serverTiming
        );

        if (!$response->successful()) {
            return [
                'ok' => false,
                'code' => $this->responseErrorCode($body, $response->status()),
                'reason' => 'Adapter VPS returned an unsuccessful response.',
                'status' => $response->status(),
                'timing' => $timing,
            ];
        }

        if ($body === null) {
            return [
                'ok' => false,
                'code' => 'invalid_response',
                'reason' => 'Adapter VPS returned a non-JSON response.',
                'status' => $response->status(),
                'timing' => $timing,
            ];
        }

        return [
            'ok' => true,
            'code' => 'ok',
            'reason' => null,
            'status' => $response->status(),
            'body' => $body,
            'timing' => $timing,
        ];
    }

    private function transferTiming(array $stats, float $transferSeconds): array
    {
        $dns = $this->transferStatSeconds($stats, 'namelookup_time');
        $connect = $this->transferStatSeconds($stats, 'connect_time');
        $tls = $this->transferStatSeconds($stats, 'appconnect_time');
        $pretransfer = $this->transferStatSeconds($stats, 'pretransfer_time');
        $ttfb = $this->transferStatSeconds($stats, 'starttransfer_time');
        $total = $this->transferStatSeconds($stats, 'total_time');
        $timing = [
            'curl_transfer_timing_measured' => $stats !== [] && $total !== null,
        ];

        if ($pretransfer !== null) {
            $timing['connection_setup_ms'] = round($pretransfer * 1000, 3);
        }
        if ($dns !== null) {
            $timing['dns_ms'] = round($dns * 1000, 3);
        }
        if ($connect !== null && $dns !== null) {
            $timing['tcp_connect_ms'] = round(max(0.0, $connect - $dns) * 1000, 3);
        }
        if ($tls !== null && $connect !== null) {
            $timing['tls_handshake_ms'] = round(max(0.0, $tls - $connect) * 1000, 3);
        }
        if ($ttfb !== null) {
            $timing['adapter_ttfb_ms'] = round($ttfb * 1000, 3);
        }
        if ($ttfb !== null && $pretransfer !== null) {
            $timing['request_after_setup_to_ttfb_ms'] = round(max(0.0, $ttfb - $pretransfer) * 1000, 3);
        }
        if ($total !== null && $ttfb !== null) {
            $timing['response_download_ms'] = round(max(0.0, $total - $ttfb) * 1000, 3);
        }
        if ($total !== null) {
            $timing['http_total_ms'] = round($total * 1000, 3);
        }
        if ($connect !== null && $ttfb !== null) {
            // This is the PHP/cURL connection to the configured endpoint. Once
            // pooling is active that endpoint is loopback Nginx, not Adapter.
            $timing['cms_loopback_connection_reused'] = $connect <= 0.000001 && $ttfb > 0.0;
        }

        return $timing;
    }

    private function transferStatSeconds(array $stats, string $key): ?float
    {
        if (!array_key_exists($key, $stats) || !is_numeric($stats[$key])) {
            return null;
        }

        return max(0.0, (float) $stats[$key]);
    }

    private function requestTiming(
        float $startedAt,
        ?float $requestStartedAt,
        ?float $responseReceivedAt,
        ?float $jsonDecodeMs,
        array $transferTiming,
        array $serverTiming
    ): array
    {
        $finishedAt = microtime(true);
        $requestStartedAt = $requestStartedAt ?? $startedAt;
        $responseReceivedAt = $responseReceivedAt ?? $finishedAt;
        $httpTotalMs = is_numeric($transferTiming['http_total_ms'] ?? null)
            ? (float) $transferTiming['http_total_ms']
            : (($responseReceivedAt - $requestStartedAt) * 1000);
        $timing = array_merge([
            'curl_transfer_timing_measured' => false,
        ], $transferTiming, [
            'cms_http_prepare_ms' => round(max(0.0, $requestStartedAt - $startedAt) * 1000, 3),
            'http_total_ms' => round(max(0.0, $httpTotalMs), 3),
            'adapter_server_timing' => $serverTiming,
        ]);

        if ($jsonDecodeMs !== null) {
            $timing['cms_json_decode_ms'] = round(max(0.0, $jsonDecodeMs), 3);
        }

        return $timing;
    }

    private function parseServerTiming(string $header): array
    {
        $allowed = [
            'adapter_route',
            'adapter_exact_lookup',
            'adapter_exact_projection',
            'adapter_serialize',
        ];
        $timing = [];

        foreach (explode(',', $header) as $metric) {
            if (!preg_match('/^\s*([a-z0-9_-]+)\s*;\s*dur=([0-9]+(?:\.[0-9]+)?)\s*$/i', $metric, $matches)) {
                continue;
            }
            $name = strtolower($matches[1]);
            if (in_array($name, $allowed, true)) {
                $timing[$name . '_ms'] = round((float) $matches[2], 3);
            }
        }

        return $timing;
    }

    private function url(AdapterVpsRuntimeConfig $config, string $path): string
    {
        return rtrim($config->baseUrl(), '/') . '/' . ltrim($path, '/');
    }

    private function connectionCode(ConnectionException $exception): string
    {
        $message = strtolower($exception->getMessage());

        return strpos($message, 'timed out') !== false || strpos($message, 'timeout') !== false
            ? 'timeout'
            : 'unavailable';
    }

    private function connectionDiagnostic(AdapterVpsRuntimeConfig $config, string $path, Throwable $exception, float $startedAt): array
    {
        $message = strtolower($exception->getMessage());
        $classification = str_contains($message, 'could not resolve host') || str_contains($message, 'name or service not known')
            ? 'dns_resolution_failed'
            : (str_contains($message, 'failed to connect') || str_contains($message, 'connection refused') || str_contains($message, 'no route to host')
                ? 'tcp_connect_failed'
                : (str_contains($message, 'timed out') || str_contains($message, 'timeout')
                    ? 'request_timed_out'
                    : (str_contains($message, 'ssl') || str_contains($message, 'certificate') ? 'tls_failed' : 'transport_unknown')));
        $url = parse_url($config->baseUrl());

        return [
            'classification' => $classification,
            'exception_class' => class_basename($exception),
            'target_scheme' => (string) ($url['scheme'] ?? ''),
            'target_host' => (string) ($url['host'] ?? ''),
            'target_port' => isset($url['port']) ? (int) $url['port'] : null,
            'request_path' => '/' . ltrim(strtok($path, '?'), '/'),
            'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'connect_timeout_ms' => (int) round($config->connectTimeoutSeconds() * 1000),
            'read_timeout_ms' => (int) round($config->readTimeoutSeconds() * 1000),
        ];
    }

    private function responseErrorCode(?array $body, int $status): string
    {
        $code = strtolower((string) data_get($body, 'code', ''));
        $statusValue = strtolower((string) data_get($body, 'status', ''));
        $reason = strtolower((string) data_get($body, 'reason', ''));

        foreach ([$code, $statusValue, $reason] as $value) {
            if ($value === '') {
                continue;
            }

            if (str_contains($value, 'expired')) {
                return 'expired';
            }

            if (str_contains($value, 'stale')) {
                return 'stale';
            }

            if (str_contains($value, 'suspended')) {
                return 'suspended';
            }

            if (str_contains($value, 'closed')) {
                return 'closed';
            }

            if (str_contains($value, 'not_found') || str_contains($value, 'not found')) {
                return 'not_found';
            }

            if (str_contains($value, 'price_changed') || str_contains($value, 'price changed')) {
                return 'price_changed';
            }

            if (str_contains($value, 'artifact') && str_contains($value, 'unavailable')) {
                return 'artifact_unavailable';
            }

            if (str_contains($value, 'heavy_route') || str_contains($value, 'heavy route')) {
                return 'heavy_routes_busy';
            }

            if (str_contains($value, 'warming')) {
                return 'warming';
            }

            if (str_contains($value, 'corrupt')) {
                return 'corrupt_artifact';
            }

            if (str_contains($value, 'permission')) {
                return 'permission_error';
            }

            if (str_contains($value, 'out_of_memory') || str_contains($value, 'out of memory') || str_contains($value, 'oom')) {
                return 'resource_exhausted';
            }
        }

        return in_array($status, [408, 429, 500, 502, 503, 504], true)
            ? 'unavailable'
            : 'invalid_response';
    }

    private function connectorList(array $connectorsBody, array $healthBody, array $readyBody): array
    {
        foreach ([
            data_get($connectorsBody, 'connectors'),
            data_get($connectorsBody, 'providers'),
            data_get($connectorsBody, 'provider_summary'),
            data_get($healthBody, 'connectors'),
            data_get($healthBody, 'providers'),
            data_get($healthBody, 'registry.connectors'),
            data_get($healthBody, 'registry.providers'),
            data_get($readyBody, 'connectors'),
            data_get($readyBody, 'providers'),
            data_get($readyBody, 'registry.connectors'),
            data_get($readyBody, 'registry.providers'),
        ] as $candidate) {
            $normalized = $this->normalizeList($candidate);
            if ($normalized !== []) {
                return $normalized;
            }
        }

        return [];
    }

    private function normalizeList($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $items = array_is_list($value)
            ? $value
            : array_map(static function ($item, $key): array {
                $item = is_array($item) ? $item : [];
                $item['provider_key'] = $item['provider_key'] ?? $key;

                return $item;
            }, $value, array_keys($value));

        return array_values(array_filter(array_map(static function ($item): array {
            return is_array($item) ? $item : [];
        }, $items)));
    }

    private function providerSummary(array $connectors): array
    {
        return array_values(array_map(static function ($connector): array {
            $connector = (array) $connector;

            return [
                'provider_key' => (string) ($connector['provider_key'] ?? $connector['key'] ?? ''),
                'implementation_status' => (string) ($connector['implementation_status'] ?? $connector['status'] ?? ''),
                'live_status' => (string) ($connector['live_status'] ?? $connector['ready_status'] ?? ''),
                'connector_status' => (string) ($connector['connector_status'] ?? $connector['health_status'] ?? ''),
                'never_run' => (bool) ($connector['never_run'] ?? false),
            ];
        }, $connectors));
    }

    private function connectorRuns(array $connectors): array
    {
        return array_values(array_map(function ($connector): array {
            $connector = (array) $connector;

            return [
                'provider_key' => (string) ($connector['provider_key'] ?? ''),
                'connector_key' => $this->firstNonEmpty([
                    $connector['connector_key'] ?? null,
                    data_get($connector, 'summary.connector_key'),
                ]),
                'implementation_status' => (string) ($connector['implementation_status'] ?? ''),
                'live_status' => (string) ($connector['live_status'] ?? ''),
                'connector_status' => (string) ($connector['connector_status'] ?? ''),
                'requests_remaining' => $this->firstNumeric([
                    $connector['requests_remaining'] ?? null,
                    $connector['request_credits_remaining'] ?? null,
                    data_get($connector, 'quota.requests_remaining'),
                    data_get($connector, 'summary.requests_remaining'),
                ]),
                'last_successful_run_at' => $this->firstNonEmpty([
                    $connector['last_successful_run_at'] ?? null,
                    data_get($connector, 'summary.last_successful_run_at'),
                    $connector['finished_at'] ?? null,
                    $connector['last_finished_at'] ?? null,
                    $connector['last_run_at'] ?? null,
                ]),
                'heartbeat_freshness_seconds' => $this->firstNumeric([
                    data_get($connector, 'heartbeat.freshness_seconds'),
                    $connector['heartbeat_freshness_seconds'] ?? null,
                    data_get($connector, 'summary.heartbeat_freshness_seconds'),
                ]),
            ];
        }, $connectors));
    }

    private function readSource(array $offersBody): ?string
    {
        return $this->firstNonEmpty([
            data_get($offersBody, 'meta.read_source'),
            data_get($offersBody, 'metadata.read_source'),
            data_get($offersBody, 'summary.read_source'),
            data_get($offersBody, 'read_source'),
            data_get($offersBody, 'source.read_source'),
            data_get($offersBody, 'source'),
        ]);
    }

    private function eventCount(array $offersBody): int
    {
        return $this->firstNumeric([
            data_get($offersBody, 'event_count'),
            data_get($offersBody, 'summary.event_count'),
            data_get($offersBody, 'meta.event_count'),
            data_get($offersBody, 'counts.events'),
            data_get($offersBody, 'counts.event_count'),
        ]) ?? count((array) data_get($offersBody, 'events', []));
    }

    private function offerCount(array $offersBody): int
    {
        return $this->firstNumeric([
            data_get($offersBody, 'offer_count'),
            data_get($offersBody, 'summary.offer_count'),
            data_get($offersBody, 'meta.offer_count'),
            data_get($offersBody, 'counts.offers'),
            data_get($offersBody, 'counts.offer_count'),
        ]) ?? count((array) data_get($offersBody, 'offers', []));
    }

    private function requestsRemaining(array $connectorsBody, array $offersBody): ?int
    {
        return $this->firstNumeric([
            data_get($connectorsBody, 'requests_remaining'),
            data_get($connectorsBody, 'quota.requests_remaining'),
            data_get($connectorsBody, 'summary.requests_remaining'),
            data_get($offersBody, 'requests_remaining'),
            data_get($offersBody, 'quota.requests_remaining'),
            data_get($offersBody, 'meta.requests_remaining'),
        ]);
    }

    private function lastSuccessfulRunAt(array $connectorsBody, array $offersBody): ?string
    {
        $candidates = array_filter([
            $this->firstNonEmpty([
                data_get($connectorsBody, 'last_successful_run_at'),
                data_get($connectorsBody, 'summary.last_successful_run_at'),
            ]),
            $this->firstNonEmpty([
                data_get($offersBody, 'last_successful_run_at'),
                data_get($offersBody, 'meta.last_successful_run_at'),
            ]),
        ]);

        foreach ((array) data_get($connectorsBody, 'connectors', []) as $connector) {
            $connector = (array) $connector;
            $value = $this->firstNonEmpty([
                $connector['last_successful_run_at'] ?? null,
                data_get($connector, 'summary.last_successful_run_at'),
                $connector['finished_at'] ?? null,
                $connector['last_finished_at'] ?? null,
                $connector['last_run_at'] ?? null,
            ]);

            if ($value !== null) {
                $candidates[] = $value;
            }
        }

        $latest = null;
        $latestTs = null;

        foreach ($candidates as $candidate) {
            $timestamp = strtotime((string) $candidate);
            if ($timestamp === false) {
                continue;
            }

            if ($latestTs === null || $timestamp > $latestTs) {
                $latestTs = $timestamp;
                $latest = (string) $candidate;
            }
        }

        return $latest;
    }

    private function heartbeatFreshnessSeconds(array $connectorsBody): ?int
    {
        $candidates = [
            $this->firstNumeric([
                data_get($connectorsBody, 'heartbeat.freshness_seconds'),
                data_get($connectorsBody, 'summary.heartbeat_freshness_seconds'),
            ]),
        ];

        foreach ((array) data_get($connectorsBody, 'connectors', []) as $connector) {
            $candidates[] = $this->firstNumeric([
                data_get($connector, 'heartbeat.freshness_seconds'),
                $connector['heartbeat_freshness_seconds'] ?? null,
                data_get($connector, 'summary.heartbeat_freshness_seconds'),
            ]);
        }

        $numeric = array_values(array_filter($candidates, static fn ($value): bool => is_int($value)));

        return $numeric === [] ? null : min($numeric);
    }

    private function coverageSummary(array $connectorsBody): array
    {
        $coverage = data_get($connectorsBody, 'coverage');
        if (!is_array($coverage)) {
            return [];
        }

        return [
            'source' => $this->firstNonEmpty([data_get($coverage, 'source')]),
            'discovery_state_available' => (bool) data_get($coverage, 'discovery_state_available', false),
            'coverage_profile' => $this->firstNonEmpty([data_get($coverage, 'coverage_profile')]),
            'effective_profile' => $this->firstNonEmpty([data_get($coverage, 'effective_profile')]),
            'state_age_seconds' => $this->firstNumeric([data_get($coverage, 'state_age_seconds')]),
            'active_sport_key_count' => $this->firstNumeric([data_get($coverage, 'active_sport_key_count')]),
            'active_sport_keys' => $this->stringList(data_get($coverage, 'active_sport_keys')),
            'future_event_counts_by_sport_key' => $this->intMap(data_get($coverage, 'future_event_counts_by_sport_key')),
            'zero_future_sport_keys' => $this->stringList(data_get($coverage, 'zero_future_sport_keys')),
            'backoff_sport_keys' => $this->stringList(data_get($coverage, 'backoff_sport_keys')),
            'last_checked_at_by_sport_key' => $this->stringMap(data_get($coverage, 'last_checked_at_by_sport_key')),
            'next_due_at_by_sport_key' => $this->stringMap(data_get($coverage, 'next_due_at_by_sport_key')),
            'sport_key_reason_by_sport_key' => $this->stringMap(data_get($coverage, 'sport_key_reason_by_sport_key')),
        ];
    }

    private function stringList($value, int $limit = 100): array
    {
        if (!is_array($value)) {
            return [];
        }

        return collect($value)
            ->filter(fn ($item): bool => !is_array($item) && !is_object($item) && trim((string) $item) !== '')
            ->map(fn ($item): string => trim((string) $item))
            ->unique()
            ->take($limit)
            ->values()
            ->all();
    }

    private function intMap($value, int $limit = 100): array
    {
        if (!is_array($value)) {
            return [];
        }

        return collect($value)
            ->filter(fn ($item, $key): bool => is_numeric($item) && !is_array($key) && !is_object($key))
            ->mapWithKeys(fn ($item, $key): array => [(string) $key => (int) $item])
            ->take($limit)
            ->all();
    }

    private function stringMap($value, int $limit = 100): array
    {
        if (!is_array($value)) {
            return [];
        }

        return collect($value)
            ->filter(fn ($item, $key): bool => !is_array($item) && !is_object($item) && !is_array($key) && !is_object($key) && trim((string) $item) !== '')
            ->mapWithKeys(fn ($item, $key): array => [(string) $key => trim((string) $item)])
            ->take($limit)
            ->all();
    }

    private function firstNonEmpty(array $values): ?string
    {
        foreach ($values as $value) {
            if (is_array($value) || is_object($value)) {
                continue;
            }

            $value = trim((string) $value);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function firstNumeric(array $values): ?int
    {
        foreach ($values as $value) {
            if (is_numeric($value)) {
                return (int) $value;
            }
        }

        return null;
    }

    private function safeChecks(array $checks): array
    {
        return array_map(static function (array $check): array {
            return [
                'ok' => (bool) ($check['ok'] ?? false),
                'code' => (string) ($check['code'] ?? 'unknown'),
                'status' => $check['status'] ?? null,
                'reason' => $check['reason'] ?? null,
                'diagnostic' => $check['diagnostic'] ?? null,
                'timing' => $check['timing'] ?? null,
            ];
        }, $checks);
    }

    private function withoutRawPayload(array $body): array
    {
        foreach ($body as $key => $value) {
            $normalizedKey = strtolower((string) $key);
            if (in_array($normalizedKey, ['raw', 'raw_payload', 'provider_raw_payload', 'payload', 'feed_body', 'auth_token'], true)) {
                unset($body[$key]);
                continue;
            }

            if (is_array($value)) {
                $body[$key] = $this->withoutRawPayload($value);
            }
        }

        return $body;
    }
}
