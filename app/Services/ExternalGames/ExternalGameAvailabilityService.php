<?php

namespace App\Services\ExternalGames;

use App\Exceptions\ProductAccess\ProductAccessDeniedException;
use App\Models\CmsGame;
use App\Models\CmsUser;
use App\Models\Provider;
use App\Models\WalletAccount;
use App\Services\ProductAccess\ProductAccessPolicyService;

class ExternalGameAvailabilityService
{
    public function __construct(
        private readonly ExternalGamesControlSettingsService $controls,
        private readonly ProductAccessPolicyService $productAccess,
        private readonly ExternalGameProviderRolloutService $providerRollout
    ) {
    }

    public function supports(CmsGame $game): bool
    {
        $providerAlias = $this->providerAlias($game);

        return isset(ExternalGameProviderConfig::providers()[$providerAlias])
            || data_get($this->gameExtraParameters($game), 'external_games.product_key') === (string) config('external_games.product_key', 'external_games');
    }

    /**
     * @throws ExternalGameLaunchBlockedException
     * @throws ProductAccessDeniedException
     */
    public function assertLaunchable(CmsGame $game, CmsUser $user, bool $isDemo, ?WalletAccount $wallet = null, bool $legacy = false): array
    {
        $providerAlias = $this->providerAlias($game);
        $provider = $game->provider;
        $controls = $this->controls->settings();
        $demoSupported = $this->controls->demoSupported($providerAlias);
        $providerConfig = $provider instanceof Provider ? $provider->configData() : [];
        $gameMeta = (array) data_get($this->gameExtraParameters($game), 'external_games', []);

        if (!$legacy && !$this->supports($game)) {
            $this->block('not_external_games', 'Bu oyun External Games kapsaminda degil.');
        }

        if (!ExternalGameProviderConfig::enabled($providerAlias)) {
            $this->block('provider_disabled', 'Provider connection is disabled.');
        }

        $allowedCountries = (array) config("external_games.providers.{$providerAlias}.allowed_countries", []);
        if ($allowedCountries !== []) {
            $country = $user->country_id ? \App\Models\CmsCountry::find($user->country_id) : null;
            if (!in_array(strtoupper((string) $country?->iso2), $allowedCountries, true)) {
                $this->block('provider_country_unsupported', 'Provider is unavailable for this account country.');
            }
        }

        if (!(bool) ($controls['enabled'] ?? false)) {
            $this->block('external_games_disabled', 'External Games su anda kullanilamiyor.');
        }

        if ((bool) ($controls['emergency_stop'] ?? false)) {
            $this->block('emergency_stop', 'External Games su anda gecici olarak durduruldu.');
        }

        if (!$provider instanceof Provider || (string) $provider->status !== 'active') {
            $this->block('provider_disabled', 'Oyun saglayicisi su anda kullanilamiyor.');
        }

        if ((bool) $provider->is_maintenance || $this->providerKillSwitchEnabled($providerConfig)) {
            $this->block('provider_unavailable', 'Oyun saglayicisi su anda bakimda.');
        }

        $upstreamProvider = $this->providerRollout->decisionForGame($game);
        if ($upstreamProvider['state'] === ExternalGameProviderRolloutService::STATE_DISABLED) {
            $this->block('upstream_provider_disabled', 'Bu oyun saglayicisi su anda kullanilamiyor.');
        }

        if ($upstreamProvider['state'] === ExternalGameProviderRolloutService::STATE_MAINTENANCE) {
            $this->block('upstream_provider_maintenance', 'Bu oyun saglayicisi su anda bakimda.');
        }

        if (!$upstreamProvider['launchable']) {
            $this->block('upstream_provider_not_playable', 'Bu oyun saglayicisi pilot oynanisa acik degil.');
        }

        if ((string) $game->status !== 'active') {
            $this->block('game_inactive', 'Bu oyun su anda kullanilamiyor.');
        }

        if ((bool) $game->is_maintenance) $this->block('provider_unavailable', 'Game maintenance');
        if ((bool) $game->is_restricted || $this->gameKillSwitchEnabled($gameMeta)) {
            $this->block('game_not_playable', 'Bu oyun su anda oynanabilir degil.');
        }

        if ($isDemo && (!$demoSupported || !(bool) ($controls['demo_enabled'] ?? false))) {
            $this->block('demo_unavailable', 'Demo modu bu oyun icin kullanilamiyor.');
        }

        $this->productAccess->assertExternalAllowed($user, $provider);

        return $this->snapshot($game, $provider, $providerAlias, $controls, $demoSupported, $wallet);
    }

    public function providerAlias(CmsGame $game): string
    {
        // The immutable provider row owns routing. Catalog metadata cannot move a game to another connector.
        return (string) $game->provider?->slug;
    }

    public function providerGameId(CmsGame $game): string
    {
        $providerGameId = (string) data_get($this->gameExtraParameters($game), 'external_games.provider_game_id', '');

        return $providerGameId !== '' ? $providerGameId : (string) $game->reference_id;
    }

    private function snapshot(
        CmsGame $game,
        ?Provider $provider,
        string $providerAlias,
        array $controls,
        bool $demoSupported,
        ?WalletAccount $wallet
    ): array {
        $providerConfig = $provider instanceof Provider ? $provider->configData() : [];
        $gameMeta = (array) data_get($this->gameExtraParameters($game), 'external_games', []);
        $upstreamProvider = $this->providerRollout->decisionForGame($game);

        return [
            'product_key' => (string) config('external_games.product_key', 'external_games'),
            'provider_alias' => $providerAlias,
            'provider_id' => $provider?->id,
            'upstream_provider_key' => $upstreamProvider['provider_key'],
            'upstream_provider_name' => $upstreamProvider['provider_name'],
            'upstream_provider_state' => $upstreamProvider['state'],
            'provider_status' => $provider?->status,
            'provider_maintenance' => (bool) ($provider?->is_maintenance ?? false),
            'provider_kill_switch' => $this->providerKillSwitchEnabled($providerConfig),
            'game_status' => (string) $game->status,
            'game_restricted' => (bool) $game->is_restricted,
            'game_kill_switch' => $this->gameKillSwitchEnabled($gameMeta),
            'demo_supported' => $demoSupported,
            'demo_enabled' => (bool) ($controls['demo_enabled'] ?? false),
            'controls_enabled' => (bool) ($controls['enabled'] ?? false),
            'emergency_stop' => (bool) ($controls['emergency_stop'] ?? false),
            'wallet_account_id' => $wallet?->id,
            'wallet_type' => $wallet?->wallet_type,
            'wallet_currency' => $wallet?->asset,
            'captured_at' => now()->toDateTimeString(),
        ];
    }

    private function providerKillSwitchEnabled(array $providerConfig): bool
    {
        return (bool) data_get($providerConfig, 'external_games.kill_switch', false)
            || (bool) data_get($providerConfig, 'external_games.launch_disabled', false)
            || (bool) data_get($providerConfig, 'kill_switch', false)
            || (data_get($providerConfig, 'enabled') === false);
    }

    private function gameKillSwitchEnabled(array $gameMeta): bool
    {
        return (bool) data_get($gameMeta, 'kill_switch', false)
            || (bool) data_get($gameMeta, 'launch_disabled', false)
            || (data_get($gameMeta, 'playable') === false);
    }

    private function gameExtraParameters(CmsGame $game): array
    {
        if (is_array($game->extra_parameters)) {
            return $game->extra_parameters;
        }

        $decoded = json_decode((string) $game->extra_parameters, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function block(string $reasonCode, string $message): never
    {
        $maintenance = in_array($reasonCode, ['provider_unavailable', 'upstream_provider_maintenance'], true);
        $translated=trans('wallet_playability.'.$reasonCode);
        if ($translated==='wallet_playability.'.$reasonCode) $translated=$message;
        throw new ExternalGameLaunchBlockedException($reasonCode, $translated, [
            'reason_code' => $reasonCode, 'product' => 'external_games',
            'suggested_action' => 'none', 'switch_allowed' => false,
            'convert_targets' => [], 'conversion_available' => false,
            'maintenance' => $maintenance, 'retryable' => $maintenance,
        ]);
    }
}
