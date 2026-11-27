<?php

namespace App\Services\ChessLiveCockpit;

use App\Models\ChessLiveBoard;
use App\Models\ChessLiveEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ChessLiveSnapshotSyncService
{
    /** @var ChessLiveSourceManager */
    private $sourceManager;

    /** @var ChessRecurringSurfaceOverlayService */
    private $recurringOverlay;

    public function __construct(
        ChessLiveSourceManager $sourceManager,
        ChessRecurringSurfaceOverlayService $recurringOverlay
    )
    {
        $this->sourceManager = $sourceManager;
        $this->recurringOverlay = $recurringOverlay;
    }

    public function syncActiveEvent(): ?ChessLiveEvent
    {
        try {
            $snapshot = $this->sourceManager->adapter()->fetchActiveEventSnapshot();
        } catch (Throwable $exception) {
            return $this->fallbackToLatestSnapshot($exception);
        }

        return DB::transaction(function () use ($snapshot) {
            $eventPayload = $snapshot['event'];

            /** @var ChessLiveEvent $event */
            $event = ChessLiveEvent::query()->firstOrNew([
                'external_event_key' => $eventPayload['external_event_key'],
            ]);

            $event->fill([
                'title' => $eventPayload['title'],
                'stage_name' => $eventPayload['stage_name'] ?? null,
                'round_name' => $eventPayload['round_name'] ?? null,
                'official_status' => $eventPayload['official_status'] ?? 'scheduled',
                'official_result' => $eventPayload['official_result'] ?? null,
                'official_source_url' => $eventPayload['official_source_url'] ?? null,
                'standings_snapshot' => $eventPayload['standings_snapshot'] ?? null,
                'source_updated_at' => $eventPayload['source_updated_at'] ?? null,
                'meta' => array_merge((array) $event->meta, (array) ($eventPayload['meta'] ?? [])),
            ]);
            $event->save();

            foreach ((array) ($snapshot['boards'] ?? []) as $boardPayload) {
                /** @var ChessLiveBoard $board */
                $board = ChessLiveBoard::query()->firstOrNew([
                    'external_board_key' => $boardPayload['external_board_key'],
                ]);

                $board->fill([
                    'chess_live_event_id' => $event->id,
                    'board_no' => $boardPayload['board_no'],
                    'white_player_name' => $boardPayload['white_player_name'],
                    'black_player_name' => $boardPayload['black_player_name'],
                    'white_rating' => $boardPayload['white_rating'] ?? null,
                    'black_rating' => $boardPayload['black_rating'] ?? null,
                    'white_points' => $boardPayload['white_points'] ?? null,
                    'black_points' => $boardPayload['black_points'] ?? null,
                    'white_clock_seconds' => $boardPayload['white_clock_seconds'] ?? null,
                    'black_clock_seconds' => $boardPayload['black_clock_seconds'] ?? null,
                    'official_status' => $boardPayload['official_status'] ?? 'scheduled',
                    'official_result' => $boardPayload['official_result'] ?? null,
                    'official_source_url' => $boardPayload['official_source_url'] ?? Arr::get($eventPayload, 'official_source_url'),
                    'featured_flag' => $board->exists ? (bool) $board->featured_flag : false,
                    'source_updated_at' => $boardPayload['source_updated_at'] ?? Arr::get($eventPayload, 'source_updated_at'),
                    'source_ref' => $boardPayload['source_ref'] ?? null,
                    'meta' => array_merge((array) $board->meta, (array) ($boardPayload['meta'] ?? [])),
                ]);
                $board->save();
            }

            $this->recurringOverlay->ensure();

            return $event->fresh();
        });
    }

    private function fallbackToLatestSnapshot(Throwable $exception): ?ChessLiveEvent
    {
        Log::warning('Chess live cockpit snapshot fetch failed.', [
            'message' => $exception->getMessage(),
            'source' => config('chess_live_cockpit.default_source', 'http'),
        ]);

        /** @var ChessLiveEvent|null $event */
        $event = ChessLiveEvent::query()->latest('updated_at')->first();

        if (!$event) {
            return $this->recurringOverlay->ensure();
        }

        $meta = array_merge((array) $event->meta, [
            'sync' => [
                'status' => 'fallback_stale',
                'adapter' => config('chess_live_cockpit.default_source', 'http'),
                'message' => $exception->getMessage(),
                'failed_at' => CarbonImmutable::now()->toIso8601String(),
            ],
        ]);

        $event->update([
            'meta' => $meta,
        ]);

        return $event->fresh();
    }
}
