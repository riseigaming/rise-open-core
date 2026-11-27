<?php

namespace App\Services\ChessLiveCockpit\Sources;

use App\Services\ChessLiveCockpit\Contracts\ChessLiveReadModelAdapter;
use App\Services\ChessLiveCockpit\Exceptions\ChessLiveSnapshotFetchException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class HttpChessLiveReadModelAdapter implements ChessLiveReadModelAdapter
{
    public function fetchActiveEventSnapshot(): array
    {
        $source = (array) config('chess_live_cockpit.sources.http', []);
        $baseUrl = rtrim((string) ($source['base_url'] ?? ''), '/');
        $eventId = trim((string) ($source['event_id'] ?? ''));
        $sharedSecret = (string) ($source['shared_secret'] ?? '');
        $timeoutSeconds = max(1, (int) ($source['timeout_seconds'] ?? 2));

        if ($baseUrl === '' || $eventId === '') {
            throw new ChessLiveSnapshotFetchException('Chess core live snapshot endpoint is not configured.');
        }

        $endpoint = $baseUrl . '/internal/live-chess-cockpit/events/' . rawurlencode($eventId) . '/snapshot';

        $response = Http::acceptJson()
            ->timeout($timeoutSeconds)
            ->withHeaders([
                'x-live-chess-cockpit-secret' => $sharedSecret,
            ])
            ->get($endpoint);

        if (!$response->successful()) {
            throw new ChessLiveSnapshotFetchException('Chess core snapshot fetch failed with HTTP ' . $response->status() . '.');
        }

        $snapshot = $response->json();

        if (!is_array($snapshot) || !is_array($snapshot['boards'] ?? null)) {
            throw new ChessLiveSnapshotFetchException('Chess core snapshot payload is invalid.');
        }

        return [
            'event' => $this->mapEventPayload($snapshot, $endpoint),
            'boards' => $this->mapBoardPayloads($snapshot),
        ];
    }

    private function mapEventPayload(array $snapshot, string $endpoint): array
    {
        $sourceType = $this->normalizeSourceType($snapshot['source_type'] ?? null);
        $sourceIntegrity = $this->normalizeSourceIntegrity($snapshot['source_integrity'] ?? null);

        return [
            'external_event_key' => (string) ($snapshot['event_id'] ?? ''),
            'title' => (string) ($snapshot['event_name'] ?? 'Live chess event'),
            'stage_name' => $this->nullableString($snapshot['stage'] ?? null),
            'round_name' => $this->nullableString($snapshot['round'] ?? null),
            'official_status' => $this->normalizeEventStatus($snapshot['status'] ?? null),
            'official_result' => null,
            'official_source_url' => null,
            'standings_snapshot' => array_values(array_map(function (array $standing) {
                return [
                    'player_id' => $standing['player_id'] ?? null,
                    'player' => $standing['player_name'] ?? null,
                    'points' => $standing['points'] ?? null,
                    'rank' => $standing['rank'] ?? null,
                    'tiebreak' => $standing['tiebreak'] ?? null,
                    'source_updated_at' => $standing['source_updated_at'] ?? null,
                ];
            }, array_filter((array) ($snapshot['standings'] ?? []), 'is_array'))),
            'source_updated_at' => $snapshot['source_updated_at'] ?? data_get($snapshot, 'source_freshness.source_updated_at'),
            'meta' => [
                'source_name' => 'core_api_http',
                'source_provider' => $snapshot['source_provider'] ?? null,
                'source_type' => $sourceType,
                'source_integrity' => $sourceIntegrity,
                'snapshot_version' => $snapshot['snapshot_version'] ?? null,
                'observed_at' => $snapshot['observed_at'] ?? null,
                'source_freshness' => $snapshot['source_freshness'] ?? null,
                'standings_changed' => false,
                'sync' => [
                    'status' => 'ok',
                    'adapter' => 'http',
                    'endpoint' => $endpoint,
                    'last_success_at' => now()->toIso8601String(),
                ],
            ],
        ];
    }

    private function mapBoardPayloads(array $snapshot): array
    {
        return array_values(array_map(function (array $board, int $index) use ($snapshot) {
            $officialResult = $this->normalizeOfficialResult($board['official_result'] ?? null);
            $standingContext = (array) ($board['standing_context'] ?? []);
            $supportsStandingDrivenTemplates = !empty($standingContext);
            $sourceBoardId = (string) ($board['source_board_id'] ?? ('core-board-' . ($index + 1)));
            $replacementTarget = $this->nullableString($board['replaced_by_source_board_id'] ?? null);
            $replacementSource = $this->nullableString($board['replacement_of_source_board_id'] ?? null);
            $normalizedStatus = $this->normalizeBoardStatus($board['official_status'] ?? null);
            $isCancelled = $normalizedStatus === 'board_cancelled';
            $isReplaced = $normalizedStatus === 'board_replaced' || $replacementTarget !== null || $replacementSource !== null;
            $sourceType = $this->normalizeSourceType($board['source_type'] ?? data_get($board, 'meta.source_type'));
            $criticalMissingFields = $this->criticalMissingFields($board);
            $sourceIntegrity = $this->normalizeSourceIntegrity(
                $board['source_integrity'] ?? data_get($board, 'meta.source_integrity'),
                $criticalMissingFields
            );

            return [
                'external_board_key' => $sourceBoardId,
                'board_no' => $board['board_no'] ?? ($index + 1),
                'white_player_name' => (string) ($board['white_player_name'] ?? 'White TBD'),
                'black_player_name' => (string) ($board['black_player_name'] ?? 'Black TBD'),
                'white_rating' => null,
                'black_rating' => null,
                'white_points' => $standingContext['white_points'] ?? null,
                'black_points' => $standingContext['black_points'] ?? null,
                'white_clock_seconds' => $this->clockToSeconds($board['white_clock'] ?? null),
                'black_clock_seconds' => $this->clockToSeconds($board['black_clock'] ?? null),
                'official_status' => $normalizedStatus,
                'official_result' => $officialResult,
                'official_source_url' => null,
                'featured_flag' => false,
                'source_updated_at' => $board['source_updated_at'] ?? data_get($board, 'source_freshness.source_updated_at') ?? ($snapshot['source_updated_at'] ?? null),
                'source_ref' => 'core-api:' . $sourceBoardId,
                'meta' => [
                    'source_provider' => $snapshot['source_provider'] ?? null,
                    'source_type' => $sourceType,
                    'source_integrity' => $sourceIntegrity,
                    'critical_missing_fields' => $criticalMissingFields,
                    'source_identity_kind' => $board['source_identity_kind'] ?? null,
                    'source_board_id' => $sourceBoardId,
                    'source_event_id' => $snapshot['event_id'] ?? null,
                    'source_freshness' => $board['source_freshness'] ?? null,
                    'source_featured' => $board['featured'] ?? null,
                    'source_status' => $board['official_status'] ?? null,
                    'source_result' => $board['official_result'] ?? null,
                    'standing_context' => $board['standing_context'] ?? null,
                    'supports_player_qualifies' => $supportsStandingDrivenTemplates,
                    'supports_top3' => $supportsStandingDrivenTemplates,
                    'supports_mini_match' => false,
                    'qualification_targets' => [
                        'white' => $supportsStandingDrivenTemplates && $this->nullableString($board['white_player_id'] ?? null) !== null,
                        'black' => $supportsStandingDrivenTemplates && $this->nullableString($board['black_player_id'] ?? null) !== null,
                    ],
                    'top3_targets' => [
                        'white' => $supportsStandingDrivenTemplates && $this->nullableString($board['white_player_id'] ?? null) !== null,
                        'black' => $supportsStandingDrivenTemplates && $this->nullableString($board['black_player_id'] ?? null) !== null,
                    ],
                    'result_hints' => $this->resultHints($officialResult),
                    'started_at' => $board['started_at'] ?? null,
                    'ended_at' => $board['ended_at'] ?? null,
                    'replaced_by_source_board_id' => $replacementTarget,
                    'replacement_of_source_board_id' => $replacementSource,
                    'is_replaced' => $isReplaced,
                    'is_cancelled' => $isCancelled,
                ],
            ];
        }, array_filter((array) ($snapshot['boards'] ?? []), 'is_array'), array_keys((array) ($snapshot['boards'] ?? []))));
    }

    private function normalizeEventStatus($status): string
    {
        $normalized = strtoupper((string) $status);

        return [
            'PAIRING_CREATED' => 'pairing_created',
            'LIVE' => 'live',
            'RESULT_VISIBLE' => 'result_visible',
            'ROUND_FINISHED' => 'round_finished',
            'CANCELLED' => 'cancelled',
            'SCHEDULED' => 'scheduled',
        ][$normalized] ?? 'scheduled';
    }

    private function normalizeBoardStatus($status): string
    {
        $normalized = strtoupper((string) $status);

        return [
            'PAIRING_CREATED' => 'pairing_created',
            'BOARD_STARTED' => 'board_started',
            'BOARD_UPDATED' => 'board_updated',
            'RESULT_VISIBLE' => 'result_visible',
            'BOARD_FINISHED' => 'board_finished',
            'BOARD_CANCELLED' => 'board_cancelled',
            'BOARD_REPLACED' => 'board_replaced',
            'NEW_PAIRING' => 'pairing_created',
            'IN_PROGRESS' => 'board_updated',
            'RESULT_POSTED' => 'result_visible',
        ][$normalized] ?? 'scheduled';
    }

    private function normalizeOfficialResult($result): ?string
    {
        $normalized = strtoupper((string) $result);

        return [
            'WHITE_WIN' => '1-0',
            'DRAW' => '1/2-1/2',
            'BLACK_WIN' => '0-1',
            'VOID' => 'void',
        ][$normalized] ?? $this->nullableString($result);
    }

    private function normalizeSourceType($value): string
    {
        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['manual', 'upstream'], true) ? $normalized : 'upstream';
    }

    private function normalizeSourceIntegrity($value, array $criticalMissingFields = []): string
    {
        if (!empty($criticalMissingFields)) {
            return 'incomplete';
        }

        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['source_confirmed', 'source_pending', 'incomplete'], true)
            ? $normalized
            : 'source_confirmed';
    }

    private function criticalMissingFields(array $board): array
    {
        $missing = [];

        if (!$this->nullableString($board['board_no'] ?? null)) {
            $missing[] = 'board_no';
        }
        if ($this->nullableString($board['white_player_name'] ?? null) === null) {
            $missing[] = 'white_player_name';
        }
        if ($this->nullableString($board['black_player_name'] ?? null) === null) {
            $missing[] = 'black_player_name';
        }

        return $missing;
    }

    private function resultHints(?string $officialResult): array
    {
        if ($officialResult === '1-0') {
            return [
                'match_result_1x2' => 'home',
                'decisive_yes_no' => 'yes',
            ];
        }

        if ($officialResult === '0-1') {
            return [
                'match_result_1x2' => 'away',
                'decisive_yes_no' => 'yes',
            ];
        }

        if ($officialResult === '1/2-1/2') {
            return [
                'match_result_1x2' => 'draw',
                'decisive_yes_no' => 'no',
            ];
        }

        return [];
    }

    private function clockToSeconds($clock): ?int
    {
        if (!is_numeric($clock)) {
            return null;
        }

        return max(0, (int) floor(((float) $clock) / 1000));
    }

    private function nullableString($value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }
}
