<?php

namespace App\Betting\Support;

use App\Models\BettingAcceptance;
use App\Models\BettingActivityLog;
use App\Models\BettingEvent;
use App\Models\BettingFeed;
use App\Models\BettingSettlement;
use App\Models\BettingSettlementSourceIngest;
use App\Models\CmsUser;

class BettingActivityLogService
{
    public function logAcceptance(BettingAcceptance $acceptance, string $action, string $message, array $meta = [], ?CmsUser $actor = null, string $level = 'info'): BettingActivityLog
    {
        return BettingActivityLog::query()->create([
            'entity_type' => 'acceptance',
            'entity_id' => $acceptance->id,
            'betting_event_id' => $acceptance->betting_event_id,
            'betting_acceptance_id' => $acceptance->id,
            'betting_settlement_id' => optional($acceptance->settlement)->id,
            'cms_user_id' => optional($actor)->id,
            'action' => $action,
            'level' => $level,
            'message' => $message,
            'meta' => $meta,
        ]);
    }

    public function logSettlement(BettingSettlement $settlement, string $action, string $message, array $meta = [], ?CmsUser $actor = null, string $level = 'info'): BettingActivityLog
    {
        $settlement->loadMissing('acceptance');

        return BettingActivityLog::query()->create([
            'entity_type' => 'settlement',
            'entity_id' => $settlement->id,
            'betting_event_id' => optional($settlement->acceptance)->betting_event_id,
            'betting_acceptance_id' => $settlement->betting_acceptance_id,
            'betting_settlement_id' => $settlement->id,
            'cms_user_id' => optional($actor)->id,
            'action' => $action,
            'level' => $level,
            'message' => $message,
            'meta' => $meta,
        ]);
    }

    public function logEvent(BettingEvent $event, string $action, string $message, array $meta = [], ?CmsUser $actor = null, string $level = 'info'): BettingActivityLog
    {
        return BettingActivityLog::query()->create([
            'entity_type' => 'event',
            'entity_id' => $event->id,
            'betting_event_id' => $event->id,
            'betting_acceptance_id' => null,
            'betting_settlement_id' => null,
            'cms_user_id' => optional($actor)->id,
            'action' => $action,
            'level' => $level,
            'message' => $message,
            'meta' => $meta,
        ]);
    }

    public function logFeed(BettingFeed $feed, string $action, string $message, array $meta = [], ?CmsUser $actor = null, string $level = 'info'): BettingActivityLog
    {
        return BettingActivityLog::query()->create([
            'entity_type' => 'feed',
            'entity_id' => $feed->id,
            'betting_event_id' => null,
            'betting_acceptance_id' => null,
            'betting_settlement_id' => null,
            'cms_user_id' => optional($actor)->id,
            'action' => $action,
            'level' => $level,
            'message' => $message,
            'meta' => $meta,
        ]);
    }

    public function logSettlementIngest(BettingSettlementSourceIngest $ingest, string $action, string $message, array $meta = [], ?CmsUser $actor = null, string $level = 'info'): BettingActivityLog
    {
        $ingest->loadMissing(['settlement.acceptance', 'acceptance']);

        $acceptance = $ingest->acceptance ?: optional($ingest->settlement)->acceptance;

        return BettingActivityLog::query()->create([
            'entity_type' => 'settlement_source_ingest',
            'entity_id' => $ingest->id,
            'betting_event_id' => optional($acceptance)->betting_event_id,
            'betting_acceptance_id' => optional($acceptance)->id,
            'betting_settlement_id' => $ingest->betting_settlement_id,
            'cms_user_id' => optional($actor)->id,
            'action' => $action,
            'level' => $level,
            'message' => $message,
            'meta' => $meta,
        ]);
    }
}
