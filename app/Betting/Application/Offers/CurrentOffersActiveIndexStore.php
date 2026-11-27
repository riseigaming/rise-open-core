<?php

namespace App\Betting\Application\Offers;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class CurrentOffersActiveIndexStore
{
    private const PREPARED_SPORT_KEYS = ['soccer', 'basketball', 'tennis', 'baseball', 'icehockey'];
    private const PREPARED_SLICE_LIMIT = 24;

    public function __construct(private CurrentOffersSnapshotStore $snapshots)
    {
    }

    public function path(): string
    {
        return Storage::disk('local')->path($this->snapshots->path() . '.inventory');
    }

    public function publish(iterable $events, ?array $sourceEvidence = null): string
    {
        $generation = (string) Str::uuid();
        $directory = $this->path() . '/' . $generation;
        File::ensureDirectoryExists($directory, 0700);
        $this->write($directory . '/lease.json', []);
        $rows = [];
        $page = [];
        $sportSlices = array_fill_keys(self::PREPARED_SPORT_KEYS, []);
        $sportCounts = array_fill_keys(self::PREPARED_SPORT_KEYS, 0);
        $leagueSlices = [];
        $chunkNumber = 0;
        foreach ($events as $event) {
            $event = $this->withoutContracts($event);
            $sportKey = $this->sportGroupKey($event);
            if (array_key_exists($sportKey, $sportCounts)) {
                $sportCounts[$sportKey]++;
                if (count($sportSlices[$sportKey]) < self::PREPARED_SLICE_LIMIT) {
                    $sportSlices[$sportKey][] = $event;
                }
            }
            $leagueKey = trim((string) ($event['league_filter_key'] ?? ''));
            if ($sportKey !== '' && $leagueKey !== '') {
                $sliceKey = $sportKey . "\0" . $leagueKey;
                if (!isset($leagueSlices[$sliceKey])) {
                    $leagueSlices[$sliceKey] = [
                        'sport_key' => $sportKey,
                        'league_filter_key' => $leagueKey,
                        'row_count' => 0,
                        'event_refs' => [],
                    ];
                }
                $leagueSlices[$sliceKey]['row_count']++;
                if (count($leagueSlices[$sliceKey]['event_refs']) < self::PREPARED_SLICE_LIMIT) {
                    $leagueSlices[$sliceKey]['event_refs'][] = [
                        'event_id' => (int) ($event['event_id'] ?? 0),
                        'inventory_page' => $chunkNumber,
                        'content_hash' => $this->sliceContentHash($event),
                    ];
                }
            }
            $page[] = $event;
            $row = Arr::except($event, ['quote', 'source_provider', 'source_event_key']);
            $row['inventory_page'] = $chunkNumber;
            $rows[] = $row;
            if (count($page) === 24) {
                $this->write($directory . '/' . $chunkNumber . '.json', $page);
                $page = [];
                $chunkNumber++;
            }
        }
        if ($page !== []) {
            $this->write($directory . '/' . $chunkNumber . '.json', $page);
        }
        $pageHashes = [];
        for ($i = 0; $i <= $chunkNumber; $i++) {
            if (is_file($directory . '/' . $i . '.json')) {
                $pageHashes[$i] = hash_file('sha256', $directory . '/' . $i . '.json');
            }
        }
        $this->write($directory . '/index.json', [
            'page_hashes' => $pageHashes,
            'schema_version' => 1,
            'generated_at' => now()->toIso8601String(),
            'event_count' => count($rows),
            'source_inventory_evidence' => $sourceEvidence,
            'events' => $rows,
        ]);
        File::ensureDirectoryExists($directory . '/sport-slices', 0700);
        foreach (self::PREPARED_SPORT_KEYS as $sportKey) {
            $sliceEvents = $sportSlices[$sportKey];
            $this->write($directory . '/sport-slices/' . $sportKey . '.json', [
                'schema_version' => 1,
                'generated_at' => now()->toIso8601String(),
                'sport_key' => $sportKey,
                'row_count' => $sportCounts[$sportKey],
                'events' => $sliceEvents,
                'sport_slice_version' => $this->sportSliceVersion($sportKey, $sliceEvents),
            ]);
        }
        File::ensureDirectoryExists($directory . '/league-slices', 0700);
        foreach ($leagueSlices as $slice) {
            $sportKey = (string) $slice['sport_key'];
            $leagueKey = (string) $slice['league_filter_key'];
            $eventRefs = (array) $slice['event_refs'];
            $this->write($directory . '/league-slices/' . $this->leagueSliceFilename($sportKey, $leagueKey), [
                'schema_version' => 1,
                'generated_at' => now()->toIso8601String(),
                'sport_key' => $sportKey,
                'league_filter_key' => $leagueKey,
                'row_count' => (int) $slice['row_count'],
                'event_refs' => $eventRefs,
                'league_slice_version' => $this->leagueSliceVersion($sportKey, $leagueKey, $eventRefs),
            ]);
        }

        return $generation;
    }

    public function sportSlice(string $generation, string $sportKey): ?array
    {
        if (!Str::isUuid($generation) || !in_array($sportKey, self::PREPARED_SPORT_KEYS, true)) {
            return null;
        }

        $slice = $this->readFile($this->path() . '/' . $generation . '/sport-slices/' . $sportKey . '.json');
        if (($slice['schema_version'] ?? null) !== 1
            || ($slice['sport_key'] ?? null) !== $sportKey
            || !is_array($slice['events'] ?? null)
            || count($slice['events']) > self::PREPARED_SLICE_LIMIT
            || (int) ($slice['row_count'] ?? -1) < count($slice['events'])
            || !is_string($slice['sport_slice_version'] ?? null)
            || strlen($slice['sport_slice_version']) !== 64) {
            return null;
        }

        return $slice;
    }

    public function leagueSlice(string $generation, string $sportKey, string $leagueKey): ?array
    {
        if (!Str::isUuid($generation) || trim($sportKey) === '' || trim($leagueKey) === '') {
            return null;
        }

        $slice = $this->readFile(
            $this->path() . '/' . $generation . '/league-slices/' . $this->leagueSliceFilename($sportKey, $leagueKey)
        );
        $eventRefs = $slice['event_refs'] ?? null;
        if (($slice['schema_version'] ?? null) !== 1
            || ($slice['sport_key'] ?? null) !== $sportKey
            || ($slice['league_filter_key'] ?? null) !== $leagueKey
            || !is_array($eventRefs)
            || count($eventRefs) > self::PREPARED_SLICE_LIMIT
            || (int) ($slice['row_count'] ?? -1) < count($eventRefs)
            || !is_string($slice['league_slice_version'] ?? null)
            || strlen($slice['league_slice_version']) !== 64
            || collect($eventRefs)->contains(fn ($ref): bool => !is_array($ref)
                || (int) ($ref['event_id'] ?? 0) <= 0
                || (int) ($ref['inventory_page'] ?? -1) < 0
                || !is_string($ref['content_hash'] ?? null)
                || strlen($ref['content_hash']) !== 64)) {
            return null;
        }

        return $slice;
    }

    public function read(string $generation): ?array
    {
        if (!Str::isUuid($generation)) {
            return null;
        }

        $index = $this->readFile($this->path() . '/' . $generation . '/index.json');

        return ($index['schema_version'] ?? null) === 1 && is_array($index['events'] ?? null)
            && ($index['event_count'] ?? null) === count($index['events']) ? $index : null;
    }

    public function pin(string $generation): ?PinnedCurrentOffersGeneration
    {
        if (!Str::isUuid($generation)) {
            return null;
        }
        $lease = @fopen($this->path() . '/' . $generation . '/lease.json', 'rb');
        if ($lease === false) {
            return null;
        }
        if (!flock($lease, LOCK_SH)) {
            fclose($lease);
            return null;
        }
        $index = $this->read($generation);
        if ($index === null) {
            fclose($lease);
            return null;
        }
        return new PinnedCurrentOffersGeneration($generation, $index, $lease);
    }

    public function events(string $generation, Collection $rows, ?array $pageHashes = null): Collection
    {
        if (!Str::isUuid($generation)) {
            throw new RuntimeException('Invalid active inventory generation.');
        }
        $events = collect();
        foreach ($rows->pluck('inventory_page')->unique() as $page) {
            if ($pageHashes !== null && (!isset($pageHashes[$page])
                || !is_file($this->path() . '/' . $generation . '/' . (int) $page . '.json')
                || !hash_equals($pageHashes[$page], hash_file('sha256', $this->path() . '/' . $generation . '/' . (int) $page . '.json')))) {
                throw new RuntimeException('Demo inventory integrity check failed.');
            }
            $payload = $this->readFile($this->path() . '/' . $generation . '/' . (int) $page . '.json');
            if ($payload === null) {
                throw new RuntimeException('Active inventory page unavailable.');
            }
            $events = $events->merge($payload);
        }
        $byId = $events->keyBy('event_id');
        if ($rows->contains(fn (array $row): bool => !$byId->has($row['event_id']))) {
            throw new RuntimeException('Active inventory page incomplete.');
        }

        return $rows->map(fn (array $row): ?array => $byId->get($row['event_id']))->filter()->values();
    }

    /** Validate local historical artifacts without publishing or changing their dates. */
    public function verifyHistoricalDemo(array $snapshot): array
    {
        $pin = $this->pin((string) ($snapshot['active_inventory_generation'] ?? ''));
        $index = $pin?->index;
        $evidence = $index['source_inventory_evidence'] ?? [];
        $success = collect(data_get($snapshot, 'context.attempts', []))->contains(fn ($attempt): bool =>
            ($attempt['source'] ?? '') === 'adapter_vps'
            && data_get($attempt, 'source_meta.status') === 'ok'
            && data_get($attempt, 'source_meta.code') === 'ok'
            && data_get($attempt, 'source_meta.publication_mode') === 'streamed_compact_inventory');
        if (!$pin || !$success || empty($index['events']) || count($index['events']) > 98304
            || ($evidence['contract'] ?? '') !== 'active_pre_match_v1'
            || ($index['generated_at'] ?? null) !== ($snapshot['generated_at'] ?? null)) {
            throw new RuntimeException('Historical generation/source evidence validation failed.');
        }
        $directory = $this->path() . '/' . $pin->id;
        $indexHash = hash_file('sha256', $directory . '/index.json');
        $rows = collect($index['events']);
        $ids = $rows->pluck('event_id')->all();
        $expected = array_values(array_diff($evidence['eligible_event_ids'] ?? [], array_keys($evidence['publication_excluded'] ?? [])));
        sort($ids); sort($expected);
        if ($ids !== $expected || count(array_unique($ids)) !== count($ids)) {
            throw new RuntimeException('Historical generation identity/count mismatch.');
        }
        $snapshotEvents = collect($snapshot['events'])->keyBy('event_id');
        $hashes = []; $checked = 0; $snapshotMatches = 0; $slices = [];
        foreach ($rows->groupBy('inventory_page') as $page => $pageRows) {
            if (!ctype_digit((string) $page) || (int) $page >= 4096) throw new RuntimeException('Invalid inventory page.');
            $file = $directory . '/' . $page . '.json';
            if (!is_file($file) || is_link($file) || filesize($file) > 32 * 1024 * 1024) throw new RuntimeException('Historical page missing or invalid.');
            $bytes = File::get($file);
            $events = json_decode($bytes, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($events) || count($events) !== $pageRows->count()) throw new RuntimeException('Historical page count mismatch.');
            $byId = $pageRows->keyBy('event_id');
            foreach ($events as $event) {
                $id = $event['event_id'] ?? null;
                $row = $byId->pull($id);
                if (!$row || Arr::except($row, ['inventory_page']) != Arr::except($event, ['quote', 'source_provider', 'source_event_key'])
                    || !str_starts_with($event['event_key'] ?? '', 'adapter_vps:') || empty($event['quote']['markets'])) {
                    throw new RuntimeException('Historical page/index identity or content mismatch.');
                }
                if ($snapshotEvents->has($id)) {
                    if ($this->withoutContracts($snapshotEvents->get($id)) != $event) throw new RuntimeException('Snapshot/page content mismatch.');
                    $snapshotMatches++;
                }
                foreach ($event['quote']['markets'] as $market) foreach ($market['selections'] ?? [] as $selection) {
                    if (!is_numeric($selection['decimal_price'] ?? null) || !is_finite((float) $selection['decimal_price'])
                        || (float) $selection['decimal_price'] <= 1) throw new RuntimeException('Invalid historical odds.');
                }
                $sport = $this->sportGroupKey($row);
                $league = (string) $row['league_filter_key'];
                $sliceKey = $sport . ':' . $league;
                $slice = $slices[$sliceKey] ??= $this->leagueSlice($pin->id, $sport, $league);
                if (!$slice) throw new RuntimeException('Historical league evidence missing.');
                foreach ($slice['event_refs'] as $ref) if ($ref['event_id'] === $id) {
                    if ($ref['inventory_page'] !== (int) $page || !hash_equals($ref['content_hash'], $this->sliceContentHash($event))) {
                        throw new RuntimeException('Historical league content hash mismatch.');
                    }
                    $checked++;
                }
            }
            $hashes[$page] = hash('sha256', $bytes);
        }
        if ($snapshotMatches !== count($snapshot['events']) || $checked === 0
            || !hash_equals($indexHash, hash_file('sha256', $directory . '/index.json'))) {
            throw new RuntimeException('Historical coverage or stable generation validation failed.');
        }
        foreach ($hashes as $page => $hash) {
            if (!hash_equals($hash, hash_file('sha256', $directory . '/' . $page . '.json'))) {
                throw new RuntimeException('Historical page changed during validation.');
            }
        }
        return ['pin' => $pin, 'event_count' => count($ids), 'index_sha256' => $indexHash,
            'page_hashes' => $hashes, 'page_count' => count($hashes), 'historical_hash_checks' => $checked];
    }

    public function prune(string $currentGeneration): void
    {
        $demoGeneration = $this->snapshots->demoReference()['active_inventory_generation'] ?? null;
        // Keep a generous reader window across atomic snapshot publication.
        foreach (File::directories($this->path()) as $directory) {
            if (Str::isUuid(basename($directory)) && basename($directory) !== $currentGeneration && basename($directory) !== $demoGeneration
                && File::lastModified($directory) < time() - max(600, $this->snapshots->ttlSeconds() * 2)) {
                $lease = @fopen($directory . '/lease.json', 'rb');
                if ($lease === false) {
                    continue;
                }
                try {
                    // An in-flight response may still be reading this generation.
                    if (flock($lease, LOCK_EX | LOCK_NB)) {
                        File::deleteDirectory($directory);
                    }
                } finally {
                    fclose($lease);
                }
            }
        }
    }

    private function withoutContracts(array $event): array
    {
        foreach ($event['quote']['markets'] as &$market) {
            foreach ($market['selections'] as &$selection) {
                unset($selection['quote_ref'], $selection['selection_token'], $selection['source_provider']);
                if (in_array($selection['acceptance_mode'] ?? '', ['adapter_vps_quote_ref', 'adapter_vps_selection_token'], true)) {
                    $selection['acceptance_mode'] = 'adapter_vps_event_scope';
                    $selection['acceptance_ready'] = false;
                }
            }
            unset($selection);
        }
        unset($market);

        return $this->redact($event);
    }

    private function redact(array $value): array
    {
        $value = Arr::except($value, ['quote_ref', 'selection_token', 'source_provider', 'provider_key', 'provider', 'raw_payload', 'provider_payload', 'auth_token', 'api_key']);
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->redact($item);
            } elseif ($key === 'bettable_reason' && $item === 'adapter_vps_missing_quote_ref') {
                $value[$key] = 'contract_refresh_required';
            }
        }

        return $value;
    }

    private function sportGroupKey(array $event): string
    {
        $existing = trim((string) ($event['sport_group_key'] ?? ''));
        if ($existing !== '') {
            return $existing;
        }

        $sportCode = (string) ($event['sport_code'] ?? '');

        return match (true) {
            str_starts_with($sportCode, 'soccer') => 'soccer',
            str_starts_with($sportCode, 'basketball') => 'basketball',
            str_starts_with($sportCode, 'tennis') => 'tennis',
            str_starts_with($sportCode, 'baseball') => 'baseball',
            str_starts_with($sportCode, 'icehockey') => 'icehockey',
            default => $sportCode,
        };
    }

    private function sportSliceVersion(string $sportKey, array $events): string
    {
        return hash('sha256', json_encode([
            'sport_key' => $sportKey,
            'events' => $this->stableSliceValue($events),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private function leagueSliceVersion(string $sportKey, string $leagueKey, array $eventRefs): string
    {
        return hash('sha256', json_encode([
            'sport_key' => $sportKey,
            'league_filter_key' => $leagueKey,
            'events' => collect($eventRefs)
                ->map(fn (array $ref): array => Arr::only($ref, ['event_id', 'content_hash']))
                ->all(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private function sliceContentHash(array $event): string
    {
        return hash('sha256', json_encode(
            $this->stableSliceValue($event),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        ));
    }

    private function leagueSliceFilename(string $sportKey, string $leagueKey): string
    {
        return hash('sha256', $sportKey . "\0" . $leagueKey) . '.json';
    }

    private function stableSliceValue(array $value): array
    {
        $volatileKeys = [
            'published_at',
            'generated_at',
            'expires_at',
            'stored_at',
            'quote_version_id',
            'quote_selection_id',
            'version_no',
            'source_event_key',
            'inventory_page',
        ];
        $result = [];
        foreach ($value as $key => $item) {
            if (is_string($key) && in_array($key, $volatileKeys, true)) {
                continue;
            }
            $result[$key] = is_array($item) ? $this->stableSliceValue($item) : $item;
        }

        return $result;
    }

    private function write(string $path, array $payload): void
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (file_put_contents($path, $json, LOCK_EX) !== strlen($json) || !chmod($path, 0600)) {
            throw new RuntimeException('Active inventory publication failed.');
        }
    }

    private function readFile(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }
        try {
            $payload = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);

            return is_array($payload) ? $payload : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
