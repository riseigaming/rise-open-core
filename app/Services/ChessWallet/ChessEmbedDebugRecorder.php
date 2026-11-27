<?php

namespace App\Services\ChessWallet;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

class ChessEmbedDebugRecorder
{
    public function enabled(): bool
    {
        return (bool) config('chess_wallet.embed.debug_enabled', app()->environment('local'));
    }

    public function record(string $eventName, array $payload = []): void
    {
        if (!$this->enabled()) {
            return;
        }

        $record = array_merge([
            'event_name' => preg_match('/^embed\.[a-z0-9_.-]{1,80}$/', $eventName) ? $eventName : 'embed.client_event',
            'timestamp' => now()->toAtomString(),
            'environment' => app()->environment(),
        ], $this->safeEvidence($payload));

        $path = $this->path();
        File::ensureDirectoryExists(dirname($path));
        File::append($path, json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);
    }

    private function safeEvidence(array $payload): array
    {
        $evidence = [];
        $requestId = $payload['request_id'] ?? null;
        if (is_string($requestId)
            && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $requestId)) {
            $evidence['request_id'] = $requestId;
        }

        $userId = filter_var($payload['cms_user_id'] ?? null, FILTER_VALIDATE_INT);
        if ($userId !== false && $userId > 0) {
            $evidence['cms_user_id'] = $userId;
            $evidence['cms_user_label'] = 'user:' . $userId;
        }

        foreach (['cms_language', 'effective_locale', 'provider_locale', 'partner_code',
            'bridge_status', 'requested_surface_type', 'resolved_surface_type', 'resolved_role',
            'source', 'probe_name'] as $key) {
            $value = $payload[$key] ?? null;
            if (is_string($value) && preg_match('/^[a-zA-Z0-9_.-]{1,64}$/', $value)) {
                $evidence[$key] = $value;
            }
        }

        $failureReason = $payload['failure_reason'] ?? null;
        if (in_array($failureReason, [
            'incomplete_config', 'local_egress_blocked', 'http_exception', 'rejected',
            'unexpected_consume_response', 'consume_redirected_to_error',
        ], true)) {
            $evidence['failure_reason'] = $failureReason;
        } elseif (is_string($failureReason) && $failureReason !== '') {
            $evidence['failure_reason'] = 'provider_reported_error';
        }

        foreach (['locale_fallback_used', 'bootstrap_generated',
            'resolved_has_external_session_ref', 'resolved_has_wallet_context_ref'] as $key) {
            if (isset($payload[$key]) && is_bool($payload[$key])) {
                $evidence[$key] = $payload[$key];
            }
        }

        $httpStatus = filter_var($payload['http_status'] ?? null, FILTER_VALIDATE_INT);
        if ($httpStatus !== false && $httpStatus >= 100 && $httpStatus <= 599) {
            $evidence['http_status'] = $httpStatus;
        }

        foreach (['external_session_ref', 'wallet_context_ref',
            'resolved_external_session_ref', 'resolved_wallet_context_ref'] as $key) {
            $value = $payload[$key] ?? null;
            if (is_string($value) && $value !== '') {
                $evidence[$key] = hash('sha256', $value);
            }
        }

        foreach (['iframe_src', 'consume_url', 'request_url', 'exchange_endpoint',
            'parent_origin', 'post_message_origin', 'requested_hostname'] as $key) {
            $value = $payload[$key] ?? null;
            if (!is_string($value) || $value === '') {
                continue;
            }

            $parts = parse_url($value);
            $host = is_array($parts) ? ($parts['host'] ?? null) : null;
            if ($host === null && $key === 'requested_hostname') {
                $host = $value;
            }
            if (is_string($host) && preg_match('/^[a-zA-Z0-9.-]{1,253}$/', $host)) {
                $evidence[$key] = strtolower($host);
            }
        }

        return $evidence;
    }

    public function sessions(int $limit = 50): array
    {
        $sessions = [];

        foreach (array_reverse($this->readAll()) as $record) {
            $requestId = (string) ($record['request_id'] ?? '');
            if ($requestId === '' || isset($sessions[$requestId])) {
                continue;
            }

            $sessionRecords = $this->recordsFor($requestId);
            if ($sessionRecords === []) {
                continue;
            }

            $first = $sessionRecords[0];
            $last = $sessionRecords[count($sessionRecords) - 1];
            $sessions[$requestId] = [
                'request_id' => $requestId,
                'cms_user_id' => $first['cms_user_id'] ?? null,
                'cms_user_label' => $first['cms_user_label'] ?? null,
                'cms_language' => $first['cms_language'] ?? null,
                'effective_locale' => $first['effective_locale'] ?? null,
                'provider_locale' => $first['provider_locale'] ?? null,
                'locale_fallback_used' => $first['locale_fallback_used'] ?? null,
                'partner_code' => $first['partner_code'] ?? null,
                'external_user_id' => $first['external_user_id'] ?? null,
                'external_session_ref' => $first['external_session_ref'] ?? null,
                'wallet_context_ref' => $first['wallet_context_ref'] ?? null,
                'source' => $this->firstNonEmpty($sessionRecords, 'source'),
                'status' => $last['event_name'] ?? 'unknown',
                'created_at' => $first['timestamp'] ?? null,
                'updated_at' => $last['timestamp'] ?? null,
                'failure_reason' => $last['failure_reason'] ?? $last['detail'] ?? null,
            ];

            if (count($sessions) >= $limit) {
                break;
            }
        }

        return array_values($sessions);
    }

    public function session(string $requestId): ?array
    {
        $records = $this->recordsFor($requestId);
        if ($records === []) {
            return null;
        }

        $first = $records[0];
        $last = $records[count($records) - 1];

        return [
            'request_id' => $requestId,
            'summary' => [
                'cms_user_id' => $first['cms_user_id'] ?? null,
                'cms_user_label' => $first['cms_user_label'] ?? null,
                'cms_language' => $first['cms_language'] ?? null,
                'effective_locale' => $first['effective_locale'] ?? null,
                'provider_locale' => $first['provider_locale'] ?? null,
                'locale_fallback_used' => $first['locale_fallback_used'] ?? null,
                'partner_code' => $first['partner_code'] ?? null,
                'external_user_id' => $first['external_user_id'] ?? null,
                'external_session_ref' => $first['external_session_ref'] ?? null,
                'wallet_context_ref' => $first['wallet_context_ref'] ?? null,
                'source' => $this->firstNonEmpty($records, 'source'),
                'status' => $last['event_name'] ?? null,
                'created_at' => $first['timestamp'] ?? null,
                'updated_at' => $last['timestamp'] ?? null,
                'iframe_src' => $this->firstNonEmpty($records, 'iframe_src'),
                'consume_url' => $this->firstNonEmpty($records, 'consume_url'),
                'parent_origin' => $this->firstNonEmpty($records, 'parent_origin'),
                'post_message_origin' => $this->firstNonEmpty($records, 'post_message_origin'),
                'requested_hostname' => $this->firstNonEmpty($records, 'requested_hostname'),
                'failure_reason' => $last['failure_reason'] ?? $last['detail'] ?? null,
            ],
            'records' => $records,
        ];
    }

    public function clear(): void
    {
        if (File::exists($this->path())) {
            File::delete($this->path());
        }
    }

    private function path(): string
    {
        return (string) config('chess_wallet.embed.debug_log_path', storage_path('app/chess-embed-debug.ndjson'));
    }

    private function readAll(): array
    {
        if (!File::exists($this->path())) {
            return [];
        }

        return collect(explode(PHP_EOL, (string) File::get($this->path())))
            ->filter()
            ->map(function (string $line) {
                $decoded = json_decode($line, true);

                if (!is_array($decoded)) {
                    return null;
                }

                if (isset($decoded['timestamp'])) {
                    try {
                        $decoded['_sort'] = Carbon::parse((string) $decoded['timestamp'])->getTimestampMs();
                    } catch (\Throwable $exception) {
                        $decoded['_sort'] = null;
                    }
                }

                return $decoded;
            })
            ->filter()
            ->sortBy('_sort')
            ->values()
            ->all();
    }

    private function recordsFor(string $requestId): array
    {
        return array_values(array_filter($this->readAll(), static fn (array $record) => ($record['request_id'] ?? null) === $requestId));
    }

    private function firstNonEmpty(array $records, string $key): mixed
    {
        foreach ($records as $record) {
            $value = $record[$key] ?? null;
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
