<?php

namespace App\Betting\Application\Offers;

use Illuminate\Support\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use JsonMachine\RecursiveItems;

class CurrentOffersSnapshotStore
{
    private const METADATA_SCHEMA_VERSION = 1;

    public function demoMode(): string
    {
        // A damaged local enable marker must still fail closed, never enable finance.
        if (app()->environment('local') && Storage::disk('local')->exists($this->path() . '.local-demo.json')) {
            return ($this->readDemoSidecar('.local-demo.json')['mode'] ?? null) === 'auto' ? 'auto' : 'manual';
        }
        return (string) config('betting.offline_demo.mode', 'off');
    }

    public function localAutoDemoEnabled(): bool
    {
        return app()->environment('local')
            && ($this->readDemoSidecar('.local-demo.json')['mode'] ?? null) === 'auto';
    }

    public function enableLocalAutoDemo(): array
    {
        $evidence = $this->adoptLocalDemo(true);
        $this->writeDemoSidecar('.local-demo.json', ['mode' => 'auto', 'enabled_at' => now()->toIso8601String()]);
        return $evidence;
    }

    public function disableLocalDemo(): void
    {
        if (!app()->environment('local')) throw new \RuntimeException('Local environment required.');
        Storage::disk('local')->delete($this->path() . '.local-demo.json');
    }

    public function adoptLocalDemo(bool $enable = false): array
    {
        if (!app()->environment('local')) throw new \RuntimeException('Local environment required.');
        $disk = Storage::disk('local');
        $snapshotHash = $disk->exists($this->path()) ? hash_file('sha256', $disk->path($this->path())) : null;
        $snapshot = $this->read();
        if (!is_array($snapshot) || ($snapshot['inventory_state'] ?? '') !== 'available'
            || empty($snapshot['events']) || count($snapshot['events']) !== ($snapshot['event_count'] ?? null)
            || (int) data_get($snapshot, 'source_summary.provider_counts.adapter_vps', 0) !== $snapshot['event_count']
            || !hash_equals((string) ($snapshot['checksum'] ?? ''), app(CurrentOffersReadService::class)->snapshotChecksum($snapshot['events']))) {
            throw new \RuntimeException('Historical snapshot checksum/source/count validation failed.');
        }
        $verified = app(CurrentOffersActiveIndexStore::class)->verifyHistoricalDemo($snapshot);
        // Retain the shared generation lease until the retention pointer is saved.
        $pin = $verified['pin'];
        unset($verified['pin']);
        if (!hash_equals($snapshotHash, hash_file('sha256', $disk->path($this->path())))) {
            throw new \RuntimeException('Snapshot changed during validation; retry.');
        }
        $reference = array_merge(Arr::only($snapshot, [
            'generated_at', 'expires_at', 'checksum', 'active_inventory_generation', 'inventory_state',
        ]), $verified, [
            'provenance' => 'local_historical_validation', 'snapshot_sha256' => $snapshotHash,
            'adopted_at' => now()->toIso8601String(),
        ]);
        $existing = $this->demoReference();
        if ($disk->exists($this->path() . '.demo.json') && $existing === null) {
            throw new \RuntimeException('Existing demo reference is corrupt; refusing to replace it.');
        }
        if ($existing && $existing['active_inventory_generation'] === $reference['active_inventory_generation']
            && (($existing['index_sha256'] ?? null) !== $reference['index_sha256']
                || (isset($existing['page_hashes']) && $existing['page_hashes'] !== $reference['page_hashes']))) {
            throw new \RuntimeException('Previously retained demo changed; refusing to reseal it.');
        }
        if ($enable) {
            $this->writeDemoSidecar('.demo.json', $existing && $existing['active_inventory_generation'] === $reference['active_inventory_generation'] ? $existing : $reference);
            $this->writeDemoSidecar('.local-demo.json', ['mode' => 'manual', 'enabled_at' => now()->toIso8601String()]);
        }
        return Arr::only($reference, ['generated_at', 'event_count', 'active_inventory_generation', 'page_count', 'historical_hash_checks', 'snapshot_sha256', 'index_sha256', 'provenance']);
    }

    public function demoActive(): bool
    {
        if ($this->demoMode() === 'manual') {
            return true;
        }
        if ($this->demoMode() !== 'auto') {
            return false;
        }
        $failure = $this->demoFailureId();

        return $failure !== null && $failure !== ($this->readMetadata()['recovered_failure_id'] ?? null);
    }

    public function demoFailureId(): ?string
    {
        return $this->readDemoSidecar('.access-failure.json')['id'] ?? null;
    }

    public function recordDemoAccessFailure(string $code, ?int $status = null): void
    {
        if ($this->demoMode() !== 'auto'
            || !(in_array($code, ['timeout', 'connection_failed'], true)
                || in_array($status, [401, 403, 502, 503, 504], true))) {
            return;
        }
        $this->writeDemoSidecar('.access-failure.json', [
            'id' => bin2hex(random_bytes(16)), 'code' => $code,
            'status' => $status, 'at' => now()->toIso8601String(),
        ]);
    }

    public function demoReference(): ?array
    {
        $reference = $this->readDemoSidecar('.demo.json');
        return is_array($reference) && (int) ($reference['event_count'] ?? 0) > 0
            && \Illuminate\Support\Str::isUuid($reference['active_inventory_generation'] ?? '')
            && is_string($reference['index_sha256'] ?? null) ? $reference : null;
    }

    /** The inventory is immutable already. Retain only a small, hashed pointer. */
    public function retainDemoReference(array $snapshot): void
    {
        if (($snapshot['inventory_state'] ?? '') !== 'available'
            || (int) ($snapshot['event_count'] ?? 0) <= 0 || !$this->isFresh($snapshot)) {
            return;
        }
        $generation = (string) ($snapshot['active_inventory_generation'] ?? '');
        $index = app(CurrentOffersActiveIndexStore::class)->pin($generation);
        if (!$index || empty($index->index['page_hashes'])) {
            return;
        }
        $this->writeDemoSidecar('.demo.json', array_merge(Arr::only($snapshot, [
            'generated_at', 'expires_at', 'event_count', 'checksum', 'active_inventory_generation', 'inventory_state',
        ]), ['index_sha256' => hash_file('sha256', app(CurrentOffersActiveIndexStore::class)->path() . '/' . $generation . '/index.json')]));
    }

    private function readDemoSidecar(string $suffix): ?array
    {
        try {
            $disk = Storage::disk('local');
            $path = $this->path() . $suffix;
            if (!$disk->exists($path) || $disk->size($path) > ($suffix === '.demo.json' ? 512 * 1024 : 8192)) {
                return null;
            }
            $value = json_decode($disk->get($path), true, 32, JSON_THROW_ON_ERROR);
            return is_array($value) ? $value : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function writeDemoSidecar(string $suffix, array $payload): void
    {
        $disk = Storage::disk('local');
        $disk->makeDirectory(dirname($this->path()));
        $target = $disk->path($this->path() . $suffix);
        $temporary = $target . '.' . bin2hex(random_bytes(8)) . '.tmp';
        try {
            if (file_put_contents($temporary, json_encode($payload, JSON_THROW_ON_ERROR), LOCK_EX) === false
                || !chmod($temporary, 0600) || !rename($temporary, $target)) {
                throw new \RuntimeException('Demo reference publication failed.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    public function path(): string
    {
        return (string) data_get(config('betting.current_offers_snapshot', []), 'path', 'betting/current_offers/current_offers_snapshot.json');
    }

    public function enabled(): bool
    {
        return (bool) data_get(config('betting.current_offers_snapshot', []), 'enabled', false);
    }

    public function ttlSeconds(): int
    {
        return max(1, (int) data_get(config('betting.current_offers_snapshot', []), 'ttl_seconds', 60));
    }

    public function maxEvents(): int
    {
        return max(1, (int) data_get(config('betting.current_offers_snapshot', []), 'max_events', 300));
    }

    public function maxPayloadBytes(): int
    {
        $configured = max(1, (int) data_get(config('betting.current_offers_snapshot', []), 'max_payload_bytes', 16 * 1024 * 1024));

        return min($configured, $this->maxPayloadHardCapBytes());
    }

    public function maxDecodePayloadBytes(): int
    {
        $configured = max(1, (int) data_get(config('betting.current_offers_snapshot', []), 'max_decode_payload_bytes', 8 * 1024 * 1024));

        return min($configured, $this->maxPayloadBytes());
    }

    public function maxPayloadHardCapBytes(): int
    {
        $configured = max(1, (int) data_get(config('betting.current_offers_snapshot', []), 'max_payload_hard_cap_bytes', 32 * 1024 * 1024));

        return min($configured, 32 * 1024 * 1024);
    }

    public function payloadSizeBytes(): ?int
    {
        $disk = Storage::disk('local');
        $path = $this->path();

        if (!$disk->exists($path)) {
            return null;
        }

        try {
            return (int) $disk->size($path);
        } catch (\Throwable) {
            return null;
        }
    }

    public function staleFallbackBehavior(): string
    {
        return (string) data_get(config('betting.current_offers_snapshot', []), 'stale_fallback_behavior', 'db_fallback');
    }

    public function read(): ?array
    {
        if (!$this->enabled()) {
            return null;
        }
        $disk = Storage::disk('local');
        $path = $this->path();

        if (!$disk->exists($path)) {
            return null;
        }

        $size = $this->payloadSizeBytes();
        if (!is_null($size) && $size > $this->maxDecodePayloadBytes()) {
            return null;
        }

        $decoded = json_decode((string) $disk->get($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    /** Read public routing metadata without decoding the multi-megabyte events array. */
    public function readMetadata(): ?array
    {
        if (!$this->enabled()) {
            return null;
        }

        $disk = Storage::disk('local');
        if (!$disk->exists($this->path())) {
            return null;
        }

        $sidecar = $this->readMetadataSidecar($disk);
        if ($sidecar !== null) {
            return $sidecar;
        }

        $stream = $disk->readStream($this->path());
        if (!is_resource($stream)) {
            return null;
        }

        $metadata = [];
        $fields = [
            'recovered_failure_id',
            'schema_version',
            'generated_at',
            'expires_at',
            'event_count',
            'inventory_state',
            'checksum',
            'source_summary',
            'active_inventory_generation',
            'stored_at',
            'ttl_seconds',
        ];

        try {
            foreach (RecursiveItems::fromStream($stream) as $field => $value) {
                if (!in_array($field, $fields, true)) {
                    continue;
                }

                $metadata[$field] = $value instanceof RecursiveItems ? $value->toArray() : $value;
            }
        } catch (\Throwable) {
            return null;
        } finally {
            fclose($stream);
        }

        return isset($metadata['generated_at'], $metadata['event_count'])
            ? $metadata
            : null;
    }

    public function write(array $snapshot): void
    {
        if (!$this->enabled()) {
            return;
        }

        $disk = Storage::disk('local');
        $path = $this->path();
        $directory = dirname($path);

        if (!$disk->exists($directory)) {
            $disk->makeDirectory($directory);
        }

        $snapshot['stored_at'] = now()->toIso8601String();
        $snapshot['ttl_seconds'] = $this->ttlSeconds();

        // Validate before writing, without allocating a second whole JSON book.
        $budget = min($this->maxPayloadBytes(), $this->maxDecodePayloadBytes());
        $bytes = 0;
        foreach ($this->jsonChunks($snapshot, $budget) as $chunk) {
            $bytes += strlen($chunk);
            if ($bytes > $budget) {
                throw new \RuntimeException('Current offers snapshot payload budget exceeded.');
            }
        }

        $temporaryPath = $disk->path($path . '.' . bin2hex(random_bytes(8)) . '.tmp');
        $stream = fopen($temporaryPath, 'xb');
        if ($stream === false) {
            throw new \RuntimeException('Current offers snapshot publication failed.');
        }
        try {
            if (!chmod($temporaryPath, 0600)) {
                throw new \RuntimeException('Current offers snapshot permissions failed.');
            }
            foreach ($this->jsonChunks($snapshot, $budget) as $chunk) {
                $offset = 0;
                while ($offset < strlen($chunk)) {
                    $written = fwrite($stream, substr($chunk, $offset));
                    if ($written === false || $written === 0) {
                        throw new \RuntimeException('Current offers snapshot write failed.');
                    }
                    $offset += $written;
                }
            }
            if (!fflush($stream)) {
                throw new \RuntimeException('Current offers snapshot flush failed.');
            }
            fclose($stream);
            $stream = null;
            // Flysystem v1 move refuses an existing target. Same-filesystem
            // rename atomically replaces it without deleting last-good first.
            if (!rename($temporaryPath, $disk->path($path))) {
                throw new \RuntimeException('Current offers snapshot activation failed.');
            }
            try {
                $this->writeMetadataSidecar($disk, $snapshot);
            } catch (\Throwable $exception) {
                $disk->delete($this->metadataPath());
                logger()->warning('sportsbook.snapshot_metadata_sidecar_write_failed', [
                    'exception' => get_class($exception),
                ]);
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }

    private function metadataPath(): string
    {
        return $this->path() . '.meta.json';
    }

    private function readMetadataSidecar($disk): ?array
    {
        $path = $this->metadataPath();
        if (!$disk->exists($path)) {
            return null;
        }

        try {
            $metadata = json_decode((string) $disk->get($path), true, 64, JSON_THROW_ON_ERROR);
            if (!is_array($metadata)
                || (int) ($metadata['metadata_schema_version'] ?? 0) !== self::METADATA_SCHEMA_VERSION
                || (int) ($metadata['snapshot_mtime'] ?? 0) !== (int) $disk->lastModified($this->path())
                || (int) ($metadata['snapshot_size_bytes'] ?? -1) !== (int) $disk->size($this->path())
                || !isset($metadata['generated_at'], $metadata['event_count'])) {
                return null;
            }

            unset($metadata['metadata_schema_version'], $metadata['snapshot_mtime'], $metadata['snapshot_size_bytes']);

            return $metadata;
        } catch (\Throwable) {
            return null;
        }
    }

    private function writeMetadataSidecar($disk, array $snapshot): void
    {
        $fields = [
            'recovered_failure_id',
            'schema_version',
            'generated_at',
            'expires_at',
            'event_count',
            'inventory_state',
            'checksum',
            'source_summary',
            'active_inventory_generation',
            'stored_at',
            'ttl_seconds',
        ];
        $metadata = array_merge(Arr::only($snapshot, $fields), [
            'metadata_schema_version' => self::METADATA_SCHEMA_VERSION,
            'snapshot_mtime' => (int) $disk->lastModified($this->path()),
            'snapshot_size_bytes' => (int) $disk->size($this->path()),
        ]);
        $json = json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $temporaryPath = $disk->path($this->metadataPath() . '.' . bin2hex(random_bytes(8)) . '.tmp');
        $stream = fopen($temporaryPath, 'xb');
        if ($stream === false) {
            throw new \RuntimeException('Current offers snapshot metadata publication failed.');
        }

        try {
            if (!chmod($temporaryPath, 0600)
                || fwrite($stream, $json) !== strlen($json)
                || !fflush($stream)) {
                throw new \RuntimeException('Current offers snapshot metadata write failed.');
            }
            fclose($stream);
            $stream = null;
            if (!rename($temporaryPath, $disk->path($this->metadataPath()))) {
                throw new \RuntimeException('Current offers snapshot metadata activation failed.');
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }

    private function jsonChunks($value, int $budget, int $depth = 0): \Generator
    {
        if ($depth > 64 || (is_string($value) && strlen($value) > $budget)) {
            throw new \RuntimeException('Current offers snapshot payload budget exceeded.');
        }
        if (!is_array($value)) {
            yield json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            return;
        }
        $list = $value === [] || array_keys($value) === range(0, count($value) - 1);
        yield $list ? '[' : '{';
        $first = true;
        foreach ($value as $key => $item) {
            if (!$first) {
                yield ',';
            }
            $first = false;
            if (!$list) {
                yield json_encode((string) $key, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . ':';
            }
            yield from $this->jsonChunks($item, $budget, $depth + 1);
        }
        yield $list ? ']' : '}';
    }

    public function lightweightHealth(): array
    {
        if (!$this->enabled()) {
            return [
                'enabled' => false,
                'healthy' => false,
                'status' => 'disabled',
                'path' => $this->path(),
                'size_bytes' => null,
                'max_payload_bytes' => $this->maxPayloadBytes(),
                'max_decode_payload_bytes' => $this->maxDecodePayloadBytes(),
                'stale_fallback_behavior' => $this->staleFallbackBehavior(),
            ];
        }

        $size = $this->payloadSizeBytes();
        if (is_null($size)) {
            return [
                'enabled' => true,
                'healthy' => false,
                'status' => 'missing',
                'path' => $this->path(),
                'size_bytes' => null,
                'max_payload_bytes' => $this->maxPayloadBytes(),
                'max_decode_payload_bytes' => $this->maxDecodePayloadBytes(),
                'stale_fallback_behavior' => $this->staleFallbackBehavior(),
            ];
        }

        if ($size > $this->maxPayloadBytes()) {
            return [
                'enabled' => true,
                'healthy' => false,
                'status' => 'oversized',
                'path' => $this->path(),
                'size_bytes' => $size,
                'max_payload_bytes' => $this->maxPayloadBytes(),
                'max_decode_payload_bytes' => $this->maxDecodePayloadBytes(),
                'stale_fallback_behavior' => $this->staleFallbackBehavior(),
            ];
        }

        if ($size > $this->maxDecodePayloadBytes()) {
            return [
                'enabled' => true,
                'healthy' => false,
                'status' => 'oversized_decode_guard',
                'path' => $this->path(),
                'size_bytes' => $size,
                'max_payload_bytes' => $this->maxPayloadBytes(),
                'max_decode_payload_bytes' => $this->maxDecodePayloadBytes(),
                'stale_fallback_behavior' => $this->staleFallbackBehavior(),
            ];
        }

        return [
            'enabled' => true,
            'healthy' => null,
            'status' => 'unchecked',
            'path' => $this->path(),
            'size_bytes' => $size,
            'max_payload_bytes' => $this->maxPayloadBytes(),
            'max_decode_payload_bytes' => $this->maxDecodePayloadBytes(),
            'stale_fallback_behavior' => $this->staleFallbackBehavior(),
        ];
    }

    public function health(?array $snapshot = null): array
    {
        if (!$this->enabled()) {
            return [
                'enabled' => false,
                'healthy' => false,
                'status' => 'disabled',
                'path' => $this->path(),
                'size_bytes' => null,
                'max_payload_bytes' => $this->maxPayloadBytes(),
                'max_decode_payload_bytes' => $this->maxDecodePayloadBytes(),
                'stale_fallback_behavior' => $this->staleFallbackBehavior(),
            ];
        }

        $size = $this->payloadSizeBytes();
        if (!is_null($size) && $size > $this->maxPayloadBytes()) {
            return [
                'enabled' => true,
                'healthy' => false,
                'status' => 'oversized',
                'path' => $this->path(),
                'size_bytes' => $size,
                'max_payload_bytes' => $this->maxPayloadBytes(),
                'max_decode_payload_bytes' => $this->maxDecodePayloadBytes(),
                'stale_fallback_behavior' => $this->staleFallbackBehavior(),
            ];
        }

        if (!is_null($size) && $size > $this->maxDecodePayloadBytes()) {
            return [
                'enabled' => true,
                'healthy' => false,
                'status' => 'oversized_decode_guard',
                'path' => $this->path(),
                'size_bytes' => $size,
                'max_payload_bytes' => $this->maxPayloadBytes(),
                'max_decode_payload_bytes' => $this->maxDecodePayloadBytes(),
                'stale_fallback_behavior' => $this->staleFallbackBehavior(),
            ];
        }

        $snapshot = $snapshot ?? $this->readMetadata();
        if (!is_array($snapshot)) {
            return [
                'enabled' => true,
                'healthy' => false,
                'status' => 'missing',
                'path' => $this->path(),
                'size_bytes' => $size,
                'max_payload_bytes' => $this->maxPayloadBytes(),
                'max_decode_payload_bytes' => $this->maxDecodePayloadBytes(),
                'stale_fallback_behavior' => $this->staleFallbackBehavior(),
            ];
        }

        $generatedAt = $this->parseTimestamp(data_get($snapshot, 'generated_at'));
        $expiresAt = $this->parseTimestamp(data_get($snapshot, 'expires_at'));
        $isFresh = $expiresAt > 0 && $expiresAt >= now()->timestamp;
        $eventCount = (int) data_get($snapshot, 'event_count', 0);
        $inventoryState = (string) data_get($snapshot, 'inventory_state', 'unverified_empty');
        $isVerifiedEmpty = $eventCount === 0 && $inventoryState === 'true_empty';
        $isUnverifiedEmpty = $eventCount === 0 && !$isVerifiedEmpty;
        $status = $isUnverifiedEmpty ? 'empty_unverified' : ($isFresh ? 'healthy' : 'stale');

        return [
            'enabled' => true,
            'healthy' => $isFresh && !$isUnverifiedEmpty,
            'status' => $status,
            'path' => $this->path(),
            'size_bytes' => $size,
            'max_payload_bytes' => $this->maxPayloadBytes(),
            'max_decode_payload_bytes' => $this->maxDecodePayloadBytes(),
            'generated_at' => data_get($snapshot, 'generated_at'),
            'expires_at' => data_get($snapshot, 'expires_at'),
            'stored_at' => data_get($snapshot, 'stored_at'),
            'checksum' => data_get($snapshot, 'checksum'),
            'event_count' => $eventCount,
            'inventory_state' => $inventoryState,
            'source_summary' => (array) data_get($snapshot, 'source_summary', []),
            'stale_fallback_behavior' => $this->staleFallbackBehavior(),
            'age_seconds' => $generatedAt > 0 ? max(0, now()->timestamp - $generatedAt) : null,
            'remaining_ttl_seconds' => $expiresAt > 0 ? max(0, $expiresAt - now()->timestamp) : null,
        ];
    }

    public function protectionSummary(): array
    {
        if (!$this->enabled()) {
            return [
                'enabled' => false,
                'healthy' => false,
                'status' => 'disabled',
                'event_ids' => [],
                'quote_version_ids' => [],
                'quote_selection_ids' => [],
                'market_ids' => [],
                'outcome_ids' => [],
                'event_count' => 0,
                'stale_fallback_behavior' => $this->staleFallbackBehavior(),
            ];
        }

        $snapshot = $this->read();
        if (!is_array($snapshot) || !$this->isFresh($snapshot)) {
            return [
                'enabled' => true,
                'healthy' => false,
                'status' => 'stale',
                'event_ids' => [],
                'quote_version_ids' => [],
                'quote_selection_ids' => [],
                'market_ids' => [],
                'outcome_ids' => [],
                'event_count' => 0,
                'stale_fallback_behavior' => $this->staleFallbackBehavior(),
            ];
        }

        $events = collect((array) data_get($snapshot, 'events', []))
            ->filter(fn ($event): bool => is_array($event))
            ->values();

        $eventIds = $events
            ->map(fn (array $event): int => (int) data_get($event, 'event_id', 0))
            ->filter(fn (int $value): bool => $value > 0)
            ->values();

        $quoteVersionIds = $events
            ->map(fn (array $event): int => (int) data_get($event, 'quote.quote_version_id', 0))
            ->filter(fn (int $value): bool => $value > 0)
            ->values();

        $marketIds = $events
            ->flatMap(function (array $event): array {
                return collect((array) data_get($event, 'quote.markets', []))
                    ->map(fn ($market): int => (int) data_get($market, 'market_id', 0))
                    ->filter(fn (int $value): bool => $value > 0)
                    ->all();
            })
            ->values();

        $selectionIds = $events
            ->flatMap(function (array $event): array {
                return collect((array) data_get($event, 'quote.markets', []))
                    ->flatMap(function (array $market): array {
                        return collect((array) data_get($market, 'selections', []))
                            ->map(fn ($selection): int => (int) data_get($selection, 'quote_selection_id', 0))
                            ->filter(fn (int $value): bool => $value > 0)
                            ->all();
                    })
                    ->all();
            })
            ->values();

        $outcomeIds = $events
            ->flatMap(function (array $event): array {
                return collect((array) data_get($event, 'quote.markets', []))
                    ->flatMap(function (array $market): array {
                        return collect((array) data_get($market, 'selections', []))
                            ->map(fn ($selection): int => (int) data_get($selection, 'outcome_id', 0))
                            ->filter(fn (int $value): bool => $value > 0)
                            ->all();
                    })
                    ->all();
            })
            ->values();

        return [
            'enabled' => true,
            'healthy' => true,
            'status' => 'healthy',
            'event_ids' => $eventIds->all(),
            'quote_version_ids' => $quoteVersionIds->all(),
            'quote_selection_ids' => $selectionIds->all(),
            'market_ids' => $marketIds->all(),
            'outcome_ids' => $outcomeIds->all(),
            'event_count' => $events->count(),
            'stale_fallback_behavior' => $this->staleFallbackBehavior(),
        ];
    }

    public function isFresh(?array $snapshot = null): bool
    {
        if (!$this->enabled()) {
            return false;
        }

        $snapshot = $snapshot ?? $this->readMetadata();
        if (!is_array($snapshot)) {
            return false;
        }

        $expiresAt = $this->parseTimestamp(data_get($snapshot, 'expires_at'));

        return $expiresAt > 0 && $expiresAt >= now()->timestamp;
    }

    private function parseTimestamp(mixed $value): int
    {
        if ($value instanceof Carbon) {
            return $value->timestamp;
        }

        if (is_int($value) || is_float($value)) {
            return (int) $value;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return 0;
        }

        try {
            return Carbon::parse($value)->timestamp;
        } catch (\Throwable) {
            return 0;
        }
    }
}
