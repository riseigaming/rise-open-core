<?php

namespace App\Services\ExternalGames;

use App\Models\CmsGame;
use App\Models\CmsUser;
use App\Models\Provider;
use App\Services\ProductAccess\ProductAccessPolicyService;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

/** Stateless, guest-compatible fun mode. No wallet/session/ledger dependency. */
class ExternalGameDemoService
{
    private ?array $settings = null;

    public function __construct(
        private ExternalGamesControlSettingsService $controls,
        private ExternalGameProviderRolloutService $rollout,
        private SpinStructDemoClient $client,
        private ProductAccessPolicyService $access,
        private ?ExternalGameAudit $opsRadar = null,
    ) {}

    public function available(CmsGame $game): bool
    {
        if (!config('external_games.fun_mode.enabled', false)) return false;
        $connection=(array)config('external_games.providers.primary_external_provider',[]);
        if (empty($connection['api_base_url'])||empty($connection['agent_token'])||empty($connection['agent_secret'])||empty($connection['agent_code'])) return false;
        if (\App\Support\LocalEgress::blocks('block_provider_apis')&&!ExternalGameLocalContract::allows((string)$connection['api_base_url'])) return false;
        if (config('external_games.providers.primary_external_provider.enabled', true) === false) return false;
        $provider = $game->relationLoaded('provider') ? $game->getRelation('provider') : null;
        if (!$provider instanceof Provider || $provider->slug !== 'primary_external_provider'
            || $provider->status !== 'active' || $provider->is_maintenance
            || $game->status !== 'active' || (int) $game->is_restricted !== 0) return false;
        $providerConfig = $provider->configData();
        if (data_get($providerConfig, 'external_games.kill_switch', false)
            || data_get($providerConfig, 'external_games.launch_disabled', false)
            || data_get($providerConfig, 'launch_disabled', false)
            || data_get($providerConfig, 'kill_switch', false)
            || data_get($providerConfig, 'enabled') === false) return false;
        $extra = is_array($game->extra_parameters) ? $game->extra_parameters : (json_decode((string) $game->extra_parameters, true) ?: []);
        if (data_get($extra, 'external_games.kill_switch', false)
            || data_get($extra, 'external_games.launch_disabled', false)
            || data_get($extra, 'external_games.playable') === false
            || SpinStructDemoClient::flag(data_get($extra, 'external_games.raw.fun_mode')) !== 1) return false;
        $controls = $this->settings ??= $this->controls->settings();
        return !empty($controls['enabled']) && !empty($controls['demo_enabled'])
            && empty($controls['emergency_stop']) && $this->rollout->decisionForGame($game)['launchable'];
    }

    public function launch(CmsGame $game, Request $request, ?CmsUser $user = null): string
    {
        if (!$this->available($game)) {
            throw new ExternalGameLaunchBlockedException('demo_unavailable', 'Demo is unavailable.');
        }
        // The default approval requirement is for account-backed real launches.
        // Guest fun mode has its own explicit demo switch. Explicit operator
        // denials still apply; no real-play policy or user override is changed.
        $this->access->assertExternalAllowed($user, $game->provider, true);
        $key = 'external-demo:' . hash('sha256', (string) $request->ip());
        if (RateLimiter::tooManyAttempts($key, 12)) {
            throw new ExternalGameLaunchBlockedException('demo_rate_limited', 'Too many demo launches.');
        }
        RateLimiter::hit($key, 60);
        $extra = is_array($game->extra_parameters) ? $game->extra_parameters : (json_decode((string) $game->extra_parameters, true) ?: []);
        $id = (string) (data_get($extra, 'external_games.provider_game_id') ?: $game->reference_id);
        if ($id === '') throw new ExternalGameLaunchBlockedException('demo_unavailable', 'Missing provider game ID.');
        $device = preg_match('/Mobile|Android|iPhone|iPad/i', (string) $request->userAgent()) ? 'mobile' : 'desktop';
        $requestId = (string) Str::uuid();
        $request->attributes->set('external_game_demo_request_id', $requestId);
        $context = $this->demoEventContext($game, $request, $id, $requestId, $device);
        $emitter = $this->opsRadar;
        $emitter?->externalGameDemoLaunchRequested($request, $context);

        try {
            $url = $this->client->launch($id, (string) app()->getLocale(), $device, $requestId);
            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            $emitter?->externalGameDemoLaunchSucceeded($request, $context + [
                'launch_host' => $host !== '' ? $host : null,
            ]);

            return $url;
        } catch (Throwable $exception) {
            $reason = $exception instanceof ExternalGameLaunchBlockedException
                ? $exception->reasonCode
                : ($exception instanceof ExternalGameProviderLaunchException
                    ? (string) ($exception->safeDetails()['classification'] ?? 'provider_launch_failed')
                    : 'demo_launch_failed');
            $emitter?->externalGameDemoLaunchFailed($request, $context + ['reason' => $reason]);
            throw $exception;
        }
    }

    private function demoEventContext(CmsGame $game, Request $request, string $providerGameId, string $requestId, string $device): array
    {
        $provider = $game->relationLoaded('provider') ? $game->getRelation('provider') : null;
        $extra = is_array($game->extra_parameters)
            ? $game->extra_parameters
            : (json_decode((string) $game->extra_parameters, true) ?: []);

        return [
            'game_id' => (int) $game->id,
            'game_name' => (string) $game->name,
            'provider_id' => $provider instanceof Provider ? (int) $provider->id : null,
            'provider_name' => $provider instanceof Provider ? (string) $provider->name : null,
            'provider_alias' => (string) (data_get($extra, 'external_games.provider_alias') ?: ($provider?->slug ?: 'primary_external_provider')),
            'provider_game_id' => $providerGameId,
            'demo' => true,
            'observed_country' => data_get($request->attributes->get('site_restriction_event_fields', []), 'observed_country'),
            'request_id' => $requestId,
            'device_class' => $device,
        ];
    }
}
