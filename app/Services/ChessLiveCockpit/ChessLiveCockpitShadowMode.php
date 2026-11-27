<?php

namespace App\Services\ChessLiveCockpit;

use Carbon\CarbonImmutable;

class ChessLiveCockpitShadowMode
{
    public function enabled(): bool
    {
        return (bool) config('chess_live_cockpit.shadow_mode', true);
    }

    public function sourceType(): string
    {
        return (string) config('chess_live_cockpit.default_source', 'http');
    }

    public function decorateMarketMeta(array $meta, string $actionType): array
    {
        if (!$this->enabled()) {
            return $meta;
        }

        $meta['execution_mode'] = 'shadow';
        $meta['shadow_mode'] = true;
        $meta['shadow_context'] = [
            'source_type' => $this->sourceType(),
            'public_publish_blocked' => true,
            'acceptance_downstream_skipped' => true,
            'payout_downstream_skipped' => true,
            'settlement_downstream_skipped' => true,
            'last_shadow_action' => $actionType,
            'last_shadow_at' => CarbonImmutable::now()->toIso8601String(),
        ];

        return $meta;
    }

    public function decorateLogMeta(array $meta): array
    {
        if (!$this->enabled()) {
            return $meta;
        }

        $meta['shadow_mode'] = true;
        $meta['source_type'] = $this->sourceType();
        $meta['public_publish_blocked'] = true;
        $meta['acceptance_downstream_skipped'] = true;
        $meta['payout_downstream_skipped'] = true;
        $meta['settlement_downstream_skipped'] = true;

        return $meta;
    }

    public function annotateNote(?string $note = null): ?string
    {
        if (!$this->enabled()) {
            return $note;
        }

        $shadowNote = 'Shadow mode active: public publish blocked; acceptance/payout/settlement downstream skipped.';

        if ($note === null || trim($note) === '') {
            return $shadowNote;
        }

        return trim($note . ' | ' . $shadowNote);
    }

    public function blockedMessage(string $operation): string
    {
        return 'Shadow mode blocked [' . $operation . ']. Public visibility changes are disabled.';
    }
}
