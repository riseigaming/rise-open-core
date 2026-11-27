<?php

namespace App\Betting\Markets;

use App\Models\BettingEvent;
use App\Models\BettingMarket;

class CanonicalMarketFamilyAcceptanceService
{
    public function __construct(private ?CanonicalMarketFamilyRegistry $registry = null)
    {
        $this->registry = $this->registry ?: app(CanonicalMarketFamilyRegistry::class);
    }

    public function summary(): array
    {
        $currentSlice = $this->currentFamilySlice();
        $currentSubfamilyKey = (string) data_get($currentSlice, 'market_subfamily_keys.0', '');

        return [
            'rollout_mode' => $this->rolloutMode(),
            'family_acceptance_mode' => $this->familyAcceptanceMode(),
            'enabled_family_keys' => $this->enabledFamilyKeys(),
            'enabled_subfamily_keys' => $this->enabledSubfamilyKeys(),
            'allowed_sport_group_keys' => $this->allowedSportGroupKeys(),
            'allowed_adapter_keys' => $this->allowedAdapterKeys(),
            'allowed_market_family_keys' => $this->allowedMarketFamilyKeys(),
            'current_family_slice' => $currentSlice,
            'families' => collect(['result', 'totals', 'handicaps', 'corners', 'cards', 'team_props', 'player_props'])
                ->mapWithKeys(function (string $familyKey) use ($currentSlice, $currentSubfamilyKey): array {
                    return [
                        $familyKey => $this->stateForFamily(
                            $familyKey,
                            (string) data_get($currentSlice, 'sport_group_key', 'soccer'),
                            (string) data_get($currentSlice, 'adapter_key', 'internal_scraper'),
                            $familyKey === 'team_props' ? $currentSubfamilyKey : null
                        ),
                    ];
                })
                ->all(),
            'player_props_subfamilies' => collect($this->registry->subfamilyKeys('player_props'))
                ->mapWithKeys(fn (string $subfamilyKey): array => [
                    $subfamilyKey => $this->stateForFamily(
                        'player_props',
                        (string) data_get($currentSlice, 'sport_group_key', 'soccer'),
                        (string) data_get($currentSlice, 'adapter_key', 'internal_scraper'),
                        $subfamilyKey
                    ),
                ])
                ->all(),
            'team_props_subfamilies' => collect($this->enabledSubfamilyKeys())
                ->mapWithKeys(fn (string $subfamilyKey): array => [
                    $subfamilyKey => $this->stateForFamily(
                        'team_props',
                        (string) data_get($currentSlice, 'sport_group_key', 'soccer'),
                        (string) data_get($currentSlice, 'adapter_key', 'internal_scraper'),
                        $subfamilyKey
                    ),
                ])
                ->all(),
        ];
    }

    public function stateForMarket(BettingMarket $market, ?string $sportCode = null): array
    {
        $providerKey = (string) data_get($market->meta, 'provider_key', 'internal_scraper');
        $familyKey = (string) data_get($market->meta, 'canonical_market_family', $market->market_type);
        $subfamilyKey = (string) data_get($market->meta, 'canonical_market_subfamily', data_get($market->meta, 'provider_market_key', ''));

        return $this->stateForFamily($familyKey, $sportCode, $providerKey, $subfamilyKey);
    }

    public function stateForFamily(string $familyKey, ?string $sportCode = null, ?string $adapterKey = null, ?string $subfamilyKey = null): array
    {
        $familyKey = $this->registry->familyKey($familyKey);
        $subfamilyKey = $this->registry->canonicalSubfamilyKey($familyKey, $subfamilyKey);
        $definition = $this->registry->stateForFamily($familyKey, $subfamilyKey);
        $rolloutMode = $this->rolloutMode();
        $familyAcceptanceMode = $this->familyAcceptanceMode();
        $enabledFamilies = $this->enabledFamilyKeys();

        $canOpen = $rolloutMode !== 'off'
            && $familyAcceptanceMode !== 'off'
            && in_array($familyKey, $enabledFamilies, true)
            && $this->sportAllowed($sportCode)
            && $this->adapterAllowed($adapterKey)
            && $this->marketFamilyAllowed($familyKey)
            && $this->subfamilyAllowed($familyKey, $subfamilyKey);

        if (data_get($definition, 'acceptance_ready', false) || $canOpen) {
            return array_merge($definition, [
                'acceptance_ready' => true,
                'visible' => true,
                'state' => 'acceptance_ready',
                'disabled_reason' => null,
                'canonical_market_subfamily_state' => $subfamilyKey !== '' ? 'acceptance_ready' : data_get($definition, 'canonical_market_subfamily_state'),
                'canonical_market_subfamily_acceptance_ready' => $subfamilyKey !== '' ? true : data_get($definition, 'canonical_market_subfamily_acceptance_ready'),
            ]);
        }

        return $definition;
    }

    public function rolloutMode(): string
    {
        return (string) data_get($this->config(), 'rollout_mode', 'off');
    }

    public function familyAcceptanceMode(): string
    {
        return (string) data_get($this->config(), 'family_acceptance_mode', 'off');
    }

    public function enabledFamilyKeys(): array
    {
        return array_values(array_map('strval', (array) data_get($this->config(), 'enabled_family_keys', [])));
    }

    public function allowedSportGroupKeys(): array
    {
        return array_values(array_map('strval', (array) data_get($this->config(), 'allowed_sport_group_keys', [])));
    }

    public function allowedAdapterKeys(): array
    {
        return array_values(array_map('strval', (array) data_get($this->config(), 'allowed_adapter_keys', [])));
    }

    public function enabledSubfamilyKeys(): array
    {
        return array_values(array_map('strval', (array) data_get($this->config(), 'enabled_subfamily_keys', [])));
    }

    public function allowedMarketFamilyKeys(): array
    {
        return array_values(array_map('strval', (array) data_get($this->config(), 'allowed_market_family_keys', [])));
    }

    public function currentFamilySlice(): array
    {
        return (array) data_get($this->config(), 'current_family_slice', [
            'sport_group_key' => 'soccer',
            'adapter_key' => 'internal_scraper',
            'market_family_keys' => ['team_props'],
            'market_subfamily_keys' => ['team_total_goals', 'team_to_score_yes_no', 'team_clean_sheet_yes_no'],
        ]);
    }

    private function config(): array
    {
        return (array) config('betting.internal_adapter.family_acceptance', []);
    }

    private function sportAllowed(?string $sportCode): bool
    {
        $allowedSports = $this->allowedSportGroupKeys();
        if ($allowedSports === []) {
            return true;
        }

        $sportGroupKey = $this->sportGroupKey((string) $sportCode);

        return in_array($sportGroupKey, $allowedSports, true);
    }

    private function adapterAllowed(?string $adapterKey): bool
    {
        $allowedAdapters = $this->allowedAdapterKeys();
        if ($allowedAdapters === []) {
            return true;
        }

        $adapterKey = trim((string) $adapterKey);
        if ($adapterKey === '') {
            $adapterKey = 'internal_scraper';
        }

        return in_array($adapterKey, $allowedAdapters, true);
    }

    private function marketFamilyAllowed(string $familyKey): bool
    {
        $allowedFamilies = $this->allowedMarketFamilyKeys();
        if ($allowedFamilies === []) {
            return true;
        }

        return in_array($this->registry->familyKey($familyKey), $allowedFamilies, true);
    }

    private function subfamilyAllowed(string $familyKey, ?string $subfamilyKey): bool
    {
        $familyKey = $this->registry->familyKey($familyKey);
        if ($familyKey !== 'team_props') {
            return true;
        }

        $allowedSubfamilies = $this->enabledSubfamilyKeys();
        if ($allowedSubfamilies === []) {
            return false;
        }

        $subfamilyKey = $this->registry->canonicalSubfamilyKey($familyKey, $subfamilyKey);
        if ($subfamilyKey === '') {
            return false;
        }

        return in_array($subfamilyKey, $allowedSubfamilies, true);
    }

    private function sportGroupKey(string $sportCode): string
    {
        return match (true) {
            str_starts_with($sportCode, 'soccer') => 'soccer',
            str_starts_with($sportCode, 'tennis') => 'tennis',
            str_starts_with($sportCode, 'basketball') => 'basketball',
            str_contains($sportCode, 'table_tennis') => 'table_tennis',
            str_contains($sportCode, 'dota') => 'dota2',
            str_contains($sportCode, 'cs2') => 'cs2',
            str_starts_with($sportCode, 'volleyball') => 'volleyball',
            str_starts_with($sportCode, 'baseball') => 'baseball',
            str_starts_with($sportCode, 'icehockey') => 'icehockey',
            default => $sportCode,
        };
    }
}
