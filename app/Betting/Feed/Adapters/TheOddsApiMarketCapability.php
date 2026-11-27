<?php

namespace App\Betting\Feed\Adapters;

class TheOddsApiMarketCapability
{
    const SUPPORT_SUPPORTED = 'supported';
    const SUPPORT_VISIBLE_ONLY = 'visible_only';
    const SUPPORT_UNSUPPORTED = 'unsupported';

    const TIER_FULLY_SUPPORTED = 'fully_supported';
    const TIER_PARTIALLY_SUPPORTED = 'partially_supported';
    const TIER_UNSUPPORTED = 'unsupported';

    private const PREFIX_DEFINITIONS = [
        'player_' => [
            'canonical_mapping_support' => self::SUPPORT_VISIBLE_ONLY,
            'quote_generation_support' => self::SUPPORT_UNSUPPORTED,
            'acceptance_support' => self::SUPPORT_UNSUPPORTED,
            'settlement_support' => self::SUPPORT_UNSUPPORTED,
            'reason' => 'player_prop_market_not_supported_in_internal_quote_or_settlement_pipeline',
        ],
        'batter_' => [
            'canonical_mapping_support' => self::SUPPORT_VISIBLE_ONLY,
            'quote_generation_support' => self::SUPPORT_UNSUPPORTED,
            'acceptance_support' => self::SUPPORT_UNSUPPORTED,
            'settlement_support' => self::SUPPORT_UNSUPPORTED,
            'reason' => 'player_prop_market_not_supported_in_internal_quote_or_settlement_pipeline',
        ],
        'pitcher_' => [
            'canonical_mapping_support' => self::SUPPORT_VISIBLE_ONLY,
            'quote_generation_support' => self::SUPPORT_UNSUPPORTED,
            'acceptance_support' => self::SUPPORT_UNSUPPORTED,
            'settlement_support' => self::SUPPORT_UNSUPPORTED,
            'reason' => 'player_prop_market_not_supported_in_internal_quote_or_settlement_pipeline',
        ],
        'home_' => [
            'canonical_mapping_support' => self::SUPPORT_VISIBLE_ONLY,
            'quote_generation_support' => self::SUPPORT_UNSUPPORTED,
            'acceptance_support' => self::SUPPORT_UNSUPPORTED,
            'settlement_support' => self::SUPPORT_UNSUPPORTED,
            'reason' => 'team_prop_market_not_supported_in_internal_quote_or_settlement_pipeline',
        ],
        'away_' => [
            'canonical_mapping_support' => self::SUPPORT_VISIBLE_ONLY,
            'quote_generation_support' => self::SUPPORT_UNSUPPORTED,
            'acceptance_support' => self::SUPPORT_UNSUPPORTED,
            'settlement_support' => self::SUPPORT_UNSUPPORTED,
            'reason' => 'team_prop_market_not_supported_in_internal_quote_or_settlement_pipeline',
        ],
    ];

    public function definition(string $marketKey): array
    {
        $normalizedKey = strtolower(trim($marketKey));
        $configDefinitions = (array) config('betting.feeds.the_odds_api.market_capabilities', []);
        $definition = (array) ($configDefinitions[$normalizedKey] ?? $this->prefixDefinition($normalizedKey) ?? []);

        $canonicalSupport = (string) ($definition['canonical_mapping_support'] ?? self::SUPPORT_UNSUPPORTED);
        $quoteSupport = (string) ($definition['quote_generation_support'] ?? self::SUPPORT_UNSUPPORTED);
        $acceptanceSupport = (string) ($definition['acceptance_support'] ?? ($quoteSupport === self::SUPPORT_SUPPORTED ? self::SUPPORT_SUPPORTED : self::SUPPORT_UNSUPPORTED));
        $settlementSupport = (string) ($definition['settlement_support'] ?? self::SUPPORT_UNSUPPORTED);

        return [
            'provider_market_key' => $normalizedKey,
            'display_name' => (string) ($definition['display_name'] ?? strtoupper($normalizedKey)),
            'canonical_mapping_support' => $canonicalSupport,
            'quote_generation_support' => $quoteSupport,
            'acceptance_support' => $acceptanceSupport,
            'settlement_support' => $settlementSupport,
            'support_tier' => $this->supportTier($canonicalSupport, $quoteSupport, $acceptanceSupport, $settlementSupport),
            'reason' => (string) ($definition['reason'] ?? 'market_not_configured_for_internal_support'),
        ];
    }

    public function definitions(array $marketKeys): array
    {
        $definitions = [];

        foreach ($marketKeys as $marketKey) {
            $definition = $this->definition((string) $marketKey);
            $definitions[$definition['provider_market_key']] = $definition;
        }

        return $definitions;
    }

    public function isCanonicalSupported(string $marketKey): bool
    {
        return $this->definition($marketKey)['canonical_mapping_support'] === self::SUPPORT_SUPPORTED;
    }

    public function isQuoteSupported(string $marketKey): bool
    {
        return $this->definition($marketKey)['quote_generation_support'] === self::SUPPORT_SUPPORTED;
    }

    public function isSettlementSupported(string $marketKey): bool
    {
        return $this->definition($marketKey)['settlement_support'] === self::SUPPORT_SUPPORTED;
    }

    public function isAcceptanceSupported(string $marketKey): bool
    {
        return $this->definition($marketKey)['acceptance_support'] === self::SUPPORT_SUPPORTED;
    }

    private function prefixDefinition(string $marketKey): ?array
    {
        foreach (self::PREFIX_DEFINITIONS as $prefix => $definition) {
            if (strpos($marketKey, $prefix) === 0) {
                return $definition;
            }
        }

        return null;
    }

    private function supportTier(string $canonicalSupport, string $quoteSupport, string $acceptanceSupport, string $settlementSupport): string
    {
        if (
            $canonicalSupport === self::SUPPORT_SUPPORTED
            && $quoteSupport === self::SUPPORT_SUPPORTED
            && $acceptanceSupport === self::SUPPORT_SUPPORTED
            && $settlementSupport === self::SUPPORT_SUPPORTED
        ) {
            return self::TIER_FULLY_SUPPORTED;
        }

        if (
            $canonicalSupport !== self::SUPPORT_UNSUPPORTED
            || $quoteSupport !== self::SUPPORT_UNSUPPORTED
            || $acceptanceSupport !== self::SUPPORT_UNSUPPORTED
            || $settlementSupport !== self::SUPPORT_UNSUPPORTED
        ) {
            return self::TIER_PARTIALLY_SUPPORTED;
        }

        return self::TIER_UNSUPPORTED;
    }
}
