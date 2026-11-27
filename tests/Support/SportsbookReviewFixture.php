<?php

namespace Tests\Support;

use App\Betting\Acceptance\AcceptanceService;
use App\Models\{BettingAcceptance, BettingEvent, BettingFeed, BettingMarket, BettingOutcome, BettingQuoteSelection, BettingQuoteVersion};
use Illuminate\Support\Str;

trait SportsbookReviewFixture
{
    use BlackjackFixture;

    private BettingQuoteSelection $reviewSelection;

    protected function setupSportsbookReview(bool $bind = true): void
    {
        $this->setupBlackjack(bind: $bind);
        config([
            'betting.sportsbook_local_parity.enabled' => true,
            'betting.sportsbook_local_parity.write_target' => 'local',
            'betting.sportsbook_local_parity.finance_enabled' => true,
        ]);

        $feed = BettingFeed::create([
            'feed_key' => 'run28-review', 'display_name' => 'Run28 synthetic fixture',
            'adapter_key' => 'manual', 'mode' => 'prematch', 'status' => 'active',
        ]);
        $event = BettingEvent::create([
            'event_key' => 'run28:event', 'sport_code' => 'soccer_epl', 'lifecycle_status' => 'scheduled',
            'trading_status' => 'open', 'visibility_status' => 'visible', 'front_betting_enabled' => true,
            'canonical_name' => 'Run28 Home vs Away', 'participant_home' => 'Run28 Home', 'participant_away' => 'Run28 Away',
            'starts_at' => now()->addDay(), 'discovered_at' => now(), 'last_source_seen_at' => now(),
        ]);
        $market = BettingMarket::create([
            'betting_event_id' => $event->id, 'market_type' => 'match_result', 'specifier_key' => 'default',
            'status' => 'open', 'display_name' => 'Match result', 'sort_order' => 10, 'meta' => [],
        ]);
        $outcome = BettingOutcome::create([
            'betting_market_id' => $market->id, 'outcome_code' => 'home',
            'display_name' => 'Home', 'status' => 'open', 'sort_order' => 10,
        ]);
        $version = BettingQuoteVersion::create([
            'betting_event_id' => $event->id, 'betting_feed_id' => $feed->id, 'version_uuid' => (string) Str::uuid(),
            'version_no' => 1, 'quote_status' => 'active', 'generation_mode' => 'automated',
            'trading_state' => 'open', 'selection_count' => 1, 'compiled_at' => now(), 'effective_from' => now(),
            'payload_digest' => hash('sha256', 'run28-review'), 'meta' => [], 'override_meta' => [],
        ]);
        $event->update(['current_quote_version_id' => $version->id]);
        $this->reviewSelection = BettingQuoteSelection::create([
            'betting_quote_version_id' => $version->id, 'betting_market_id' => $market->id, 'betting_outcome_id' => $outcome->id,
            'offer_status' => 'open', 'trading_decision_source' => 'automated',
            'decimal_price' => '2.250000', 'base_decimal_price' => '2.250000', 'meta' => [],
        ]);
    }

    protected function requestReviewCoupon(string $key, string $stake = '10.00'): BettingAcceptance
    {
        return app(AcceptanceService::class)->requestForSelection($this->reviewSelection, [
            'cms_user_id' => $this->player->id, 'stake_amount' => $stake, 'currency' => 'TRY',
            'accepted_price' => '2.250000', 'idempotency_key' => $key,
            'acceptance_source' => 'cms_legacy', 'acceptance_contract_version' => 'legacy_v1',
        ]);
    }
}
