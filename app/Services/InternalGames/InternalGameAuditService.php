<?php

namespace App\Services\InternalGames;

use App\Models\InternalGameRound;
use App\Models\InternalGameRoundEvent;
use App\Models\InternalGameSession;

class InternalGameAuditService
{
    public function logSession(InternalGameSession $session, string $message, array $payload = [], string $status = 'info'): void
    {
        $this->logEvent($session, null, $message, $status, $payload);
    }

    public function logRound(InternalGameSession $session, ?InternalGameRound $round, string $message, array $payload = [], string $status = 'info'): void
    {
        $this->logEvent($session, $round, $message, $status, $payload);
    }

    private function logEvent(InternalGameSession $session, ?InternalGameRound $round, string $message, string $status, array $payload): void
    {
        InternalGameRoundEvent::query()->create([
            'internal_game_round_id' => $round?->id,
            'internal_game_session_id' => $session->id,
            'event_type' => $message,
            'event_status' => $status,
            'request_ref' => $payload['request_ref'] ?? null,
            'correlation_ref' => $payload['correlation_ref'] ?? null,
            'message' => $payload['message'] ?? null,
            'payload' => $payload,
            'created_at' => now(),
        ]);
    }
}
