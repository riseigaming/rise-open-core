<?php

namespace App\Betting\Markets;

use App\Betting\Feed\Adapters\TheOddsApiMarketCapability;
use App\Models\BettingMarket;

class BettingMarketReadinessService
{
    public function __construct(
        private ?TheOddsApiMarketCapability $theOddsApiCapability = null,
        private ?CanonicalMarketFamilyRegistry $familyRegistry = null,
        private ?CanonicalMarketFamilyAcceptanceService $familyAcceptance = null
    )
    {
        $this->theOddsApiCapability = $this->theOddsApiCapability ?: app(TheOddsApiMarketCapability::class);
        $this->familyRegistry = $this->familyRegistry ?: app(CanonicalMarketFamilyRegistry::class);
        $this->familyAcceptance = $this->familyAcceptance ?: app(CanonicalMarketFamilyAcceptanceService::class);
    }

    public function statusForMarket(?BettingMarket $market, ?string $sportCode = null): array
    {
        if (!$market) {
            return $this->defaultStatus();
        }

        $meta = (array) $market->meta;
        $providerKey = trim((string) data_get($meta, 'provider_key', ''));
        $providerMarketKey = trim((string) data_get($meta, 'provider_market_key', ''));

        if ($providerKey === 'the_odds_api' && $providerMarketKey !== '') {
            return $this->theOddsApiStatus($providerMarketKey, $sportCode);
        }

        if ($providerKey === 'internal_scraper') {
            return $this->internalScraperStatus($market, $providerMarketKey, $sportCode);
        }

        return $this->defaultStatus();
    }

    public function theOddsApiStatus(string $providerMarketKey, ?string $sportCode = null): array
    {
        $providerMarketKey = strtolower(trim($providerMarketKey));
        $definition = $this->theOddsApiCapability->definition($providerMarketKey);
        $quoteable = $definition['quote_generation_support'] === TheOddsApiMarketCapability::SUPPORT_SUPPORTED;
        $acceptanceSupported = $definition['acceptance_support'] === TheOddsApiMarketCapability::SUPPORT_SUPPORTED;
        $settlementSupported = $definition['settlement_support'] === TheOddsApiMarketCapability::SUPPORT_SUPPORTED;
        $observeOnlyEnabled = $this->theOddsApiObserveOnlyEnabled($providerMarketKey);
        $acceptanceRolloutEnabled = $this->theOddsApiAcceptanceRolloutEnabled($providerMarketKey);
        $settlementRolloutEnabled = $this->theOddsApiSettlementRolloutEnabled($providerMarketKey);
        $inCanaryScope = $this->theOddsApiInCanaryScope($providerMarketKey, $sportCode);
        $visible = $quoteable && $this->theOddsApiVisibilityEnabled($providerMarketKey) && $inCanaryScope;
        $settlementReady = $settlementSupported && $settlementRolloutEnabled && $inCanaryScope;
        $bettable = $quoteable && $acceptanceSupported && $acceptanceRolloutEnabled && $inCanaryScope;

        return [
            'provider_key' => 'the_odds_api',
            'provider_market_key' => $providerMarketKey,
            'quoteable' => $quoteable,
            'visible' => $visible,
            'acceptance_supported' => $acceptanceSupported,
            'acceptance_rollout_enabled' => $acceptanceRolloutEnabled,
            'settlement_supported' => $settlementSupported,
            'settlement_rollout_enabled' => $settlementRolloutEnabled,
            'settlement_ready' => $settlementReady,
            'observe_only_enabled' => $observeOnlyEnabled,
            'in_canary_scope' => $inCanaryScope,
            'bettable' => $bettable,
            'operational_stage' => $this->operationalStage($providerMarketKey, $observeOnlyEnabled, $acceptanceRolloutEnabled),
            'reason_code' => $this->reasonCode(
                $providerMarketKey,
                $quoteable,
                $acceptanceSupported,
                $observeOnlyEnabled,
                $acceptanceRolloutEnabled,
                $inCanaryScope
            ),
            'reason' => (string) ($definition['reason'] ?? ''),
            'display_name' => (string) ($definition['display_name'] ?? strtoupper($providerMarketKey)),
            'spreads_canary_sport_keys' => $providerMarketKey === 'spreads'
                ? array_values((array) config('betting.feeds.the_odds_api.spreads_canary_sport_keys', []))
                : [],
            'spreads_canary_max_events_per_sport' => $providerMarketKey === 'spreads'
                ? (int) config('betting.feeds.the_odds_api.spreads_canary_max_events_per_sport', 4)
                : 0,
        ];
    }

    private function theOddsApiObserveOnlyEnabled(string $providerMarketKey): bool
    {
        return match ($providerMarketKey) {
            'spreads' => (bool) config('betting.feeds.the_odds_api.spreads_rollout.observe_only_enabled', true),
            default => true,
        };
    }

    private function theOddsApiAcceptanceRolloutEnabled(string $providerMarketKey): bool
    {
        return match ($providerMarketKey) {
            'spreads' => (bool) config('betting.acceptance.market_rollout.the_odds_api.spreads_enabled', false),
            default => true,
        };
    }

    private function theOddsApiSettlementRolloutEnabled(string $providerMarketKey): bool
    {
        return match ($providerMarketKey) {
            'h2h' => (bool) config('betting.sync.results.external_poller.h2h_enabled', true),
            'totals' => (bool) config('betting.sync.results.external_poller.totals_enabled', true),
            'spreads' => (bool) config('betting.sync.results.external_poller.spreads_enabled', false),
            default => false,
        };
    }

    private function theOddsApiVisibilityEnabled(string $providerMarketKey): bool
    {
        return match ($providerMarketKey) {
            'spreads' => $this->theOddsApiObserveOnlyEnabled($providerMarketKey) || $this->theOddsApiAcceptanceRolloutEnabled($providerMarketKey),
            default => true,
        };
    }

    private function theOddsApiInCanaryScope(string $providerMarketKey, ?string $sportCode): bool
    {
        if ($providerMarketKey !== 'spreads') {
            return true;
        }

        $allowlist = array_values((array) config('betting.feeds.the_odds_api.spreads_canary_sport_keys', []));
        if ($allowlist === [] || $sportCode === null || trim($sportCode) === '') {
            return $allowlist === [];
        }

        return in_array(trim($sportCode), $allowlist, true);
    }

    private function operationalStage(string $providerMarketKey, bool $observeOnlyEnabled, bool $acceptanceRolloutEnabled): string
    {
        if ($providerMarketKey !== 'spreads') {
            return 'bettable_enabled';
        }

        if ($acceptanceRolloutEnabled) {
            return 'bettable_enabled';
        }

        if ($observeOnlyEnabled) {
            return 'observe_only';
        }

        return 'disabled';
    }

    private function reasonCode(
        string $providerMarketKey,
        bool $quoteable,
        bool $acceptanceSupported,
        bool $observeOnlyEnabled,
        bool $acceptanceRolloutEnabled,
        bool $inCanaryScope
    ): ?string {
        if (!$quoteable) {
            return 'market_quote_not_supported';
        }

        if (!$inCanaryScope) {
            return 'market_outside_canary_scope';
        }

        if ($providerMarketKey === 'spreads' && !$observeOnlyEnabled && !$acceptanceRolloutEnabled) {
            return 'market_rollout_disabled';
        }

        if (!$acceptanceSupported) {
            return 'market_acceptance_not_supported';
        }

        if (!$acceptanceRolloutEnabled) {
            return 'market_acceptance_rollout_disabled';
        }

        return null;
    }

    private function defaultStatus(): array
    {
        return [
            'provider_key' => null,
            'provider_market_key' => null,
            'quoteable' => true,
            'visible' => true,
            'acceptance_supported' => true,
            'acceptance_rollout_enabled' => true,
            'settlement_supported' => true,
            'settlement_rollout_enabled' => true,
            'settlement_ready' => true,
            'observe_only_enabled' => false,
            'in_canary_scope' => true,
            'bettable' => true,
            'operational_stage' => 'bettable_enabled',
            'reason_code' => null,
            'reason' => null,
            'display_name' => null,
            'spreads_canary_sport_keys' => [],
            'spreads_canary_max_events_per_sport' => 0,
        ];
    }

    private function internalScraperStatus(BettingMarket $market, string $providerMarketKey, ?string $sportCode = null): array
    {
        $providerMarketKey = trim($providerMarketKey) !== '' ? trim($providerMarketKey) : (string) $market->market_type;
        $marketStatus = strtolower(trim((string) $market->status));
        $marketSuspended = in_array($marketStatus, ['suspended', 'held', 'closed'], true);
        $familyState = $this->familyAcceptance->stateForMarket($market, $sportCode);
        $acceptanceReady = (bool) data_get($familyState, 'acceptance_ready', false);
        $visible = (bool) data_get($familyState, 'visible', true);

        if (!$acceptanceReady) {
            return [
                'provider_key' => 'internal_scraper',
                'provider_market_key' => $providerMarketKey,
                'quoteable' => true,
                'visible' => $visible,
                'acceptance_supported' => true,
                'acceptance_rollout_enabled' => false,
                'settlement_supported' => false,
                'settlement_rollout_enabled' => false,
                'settlement_ready' => false,
                'observe_only_enabled' => false,
                'in_canary_scope' => true,
                'bettable' => false,
                'operational_stage' => 'visible_disabled',
                'reason_code' => (string) data_get($familyState, 'disabled_reason', 'market_family_rollout_disabled'),
                'reason' => 'canonical_market_family_visible_but_not_acceptance_ready',
                'display_name' => (string) data_get($familyState, 'label', strtoupper($providerMarketKey)),
                'spreads_canary_sport_keys' => [],
                'spreads_canary_max_events_per_sport' => 0,
                'canonical_market_family' => (string) data_get($familyState, 'family_key', $providerMarketKey),
                'canonical_market_family_label' => (string) data_get($familyState, 'label', strtoupper($providerMarketKey)),
                'canonical_market_family_state' => (string) data_get($familyState, 'state', 'visible_disabled'),
                'canonical_market_subfamily_key' => data_get($familyState, 'canonical_market_subfamily_key'),
                'canonical_market_subfamily_label' => data_get($familyState, 'canonical_market_subfamily_label'),
                'canonical_market_subfamily_state' => data_get($familyState, 'canonical_market_subfamily_state', 'visible_disabled'),
                'acceptance_ready' => false,
            ];
        }

        return [
            'provider_key' => 'internal_scraper',
            'provider_market_key' => $providerMarketKey,
            'quoteable' => true,
            'visible' => $visible,
            'acceptance_supported' => true,
            'acceptance_rollout_enabled' => true,
            'settlement_supported' => false,
            'settlement_rollout_enabled' => false,
            'settlement_ready' => false,
            'observe_only_enabled' => false,
            'in_canary_scope' => true,
            'bettable' => !$marketSuspended,
            'operational_stage' => $marketSuspended ? 'observe_only' : 'bettable_enabled',
            'reason_code' => $marketSuspended ? 'market_not_bettable' : null,
            'reason' => 'internal_scraper_market_ready_before_settlement_rollout',
            'display_name' => strtoupper($providerMarketKey),
            'spreads_canary_sport_keys' => [],
            'spreads_canary_max_events_per_sport' => 0,
            'canonical_market_family' => (string) data_get($familyState, 'family_key', $providerMarketKey),
            'canonical_market_family_label' => (string) data_get($familyState, 'label', strtoupper($providerMarketKey)),
            'canonical_market_family_state' => (string) data_get($familyState, 'state', 'acceptance_ready'),
            'canonical_market_subfamily_key' => data_get($familyState, 'canonical_market_subfamily_key'),
            'canonical_market_subfamily_label' => data_get($familyState, 'canonical_market_subfamily_label'),
            'canonical_market_subfamily_state' => data_get($familyState, 'canonical_market_subfamily_state', 'acceptance_ready'),
            'acceptance_ready' => true,
        ];
    }
}
