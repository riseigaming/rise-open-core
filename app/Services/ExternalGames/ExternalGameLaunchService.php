<?php

namespace App\Services\ExternalGames;

use App\Models\CmsGame;
use App\Models\CmsUser;
use App\Models\ExternalGameSession;
use App\Models\WalletAccount;
use App\Services\Wallet\ActiveWalletService;
use App\Services\Wallet\CanonicalWalletService;
use App\Services\Wallet\Exceptions\UnsupportedProductWalletException;
use App\Services\Wallet\ProductWalletCapabilityService;

use App\Services\Localization\ProductLocaleResolver;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ExternalGameLaunchService
{
    public function __construct(
        private readonly ExternalGameAvailabilityService $availability,
        private readonly ExternalGameSessionService $sessions,
        private readonly PrimaryExternalProviderClient $providerClient,
        private readonly ActiveWalletService $activeWallets,
        private readonly ProductWalletCapabilityService $productWallets,
        private readonly CanonicalWalletService $wallets,
        private readonly ExternalGameRiskLimitService $riskLimits,
        private readonly ExternalGameAudit $opsRadar,
        private readonly ProductLocaleResolver $productLocales
    ) {
    }

    public function launch(
        CmsGame $game,
        CmsUser $user,
        bool $isDemo = false,
        ?Request $request = null,
        bool $forceFreshLaunch = false
    ): ExternalGameSession
    {
        $providerAlias = $this->availability->providerAlias($game);
        $providerGameId = $this->availability->providerGameId($game);
        $localeResolution = $this->productLocales->resolve(
            'casino',
            $providerAlias,
            (array) config("external_games.providers.{$providerAlias}.locale", [])
        );
        $this->opsRadar->externalGameLaunchRequested($request, $user, [
            'game_id' => (int) $game->id,
            'game_name' => (string) $game->name,
            'provider_alias' => $providerAlias,
            'provider_key' => (string) ($game->type_name ?: $game->provider_id ?: ''),
            'provider_name' => (string) ($game->type_name ?: optional($game->provider)->name ?: ''),
            'provider_game_id' => $providerGameId,
            'currency' => strtoupper((string) ($user->currency ?: 'TRY')),
            'effective_locale' => $localeResolution['effective_locale'],
            'provider_locale' => $localeResolution['provider_locale'],
            'locale_fallback_used' => $localeResolution['locale_fallback_used'],
        ]);
        $this->availability->assertLaunchable($game, $user, $isDemo);

        // First-time wallet bootstrap also writes rows. Serialize it before
        // session preparation so simultaneous legitimate launches cannot race
        // on wallet uniqueness or overwrite a callback's balance projection.
        $owner = $this->wallets->resolveForUser($user)?->rootUser();
        if (!$owner instanceof CmsUser) {
            throw new ExternalGameLaunchBlockedException('wallet_identity_mismatch', trans('game_access_controls.wallet_unavailable'), ['reason' => 'wallet_identity_mismatch']);
        }
        $wallet = DB::transaction(function () use ($owner, $user, $game) {
            CmsUser::whereKey($owner->id)->lockForUpdate()->firstOrFail();
            return app(\App\Services\Wallet\WalletPlayabilityService::class)->assertForUser(
                'external_games', CmsUser::findOrFail($user->id), [
                    'provider_assets' => (array) config('external_games.providers.'.$this->availability->providerAlias($game).'.supported_currencies', []),
                ], true
            );
        }, 3);
        if (!$wallet instanceof WalletAccount) {
            throw new UnsupportedProductWalletException(
                trans('wallet_playability.unsupported_wallet'),
                $this->productWallets->resolveForUser('external_games', $user)
            );
        }

        $walletCapability = $this->productWallets->evaluate('external_games', $wallet);
        if (!(bool) ($walletCapability['supported'] ?? false)) {
            throw new UnsupportedProductWalletException(
                (string) ($walletCapability['message'] ?? trans('wallet_playability.unsupported_wallet')),
                $this->productWallets->resolveForUser('external_games', $user)
            );
        }

        $playabilitySnapshot = $this->availability->assertLaunchable($game, $user, $isDemo, $wallet);
        if (!$isDemo && !in_array(strtoupper((string) $wallet->asset),
            (array) config("external_games.providers.{$providerAlias}.supported_currencies", []), true)) {
            throw new ExternalGameLaunchBlockedException('unsupported_provider_currency',
                trans('wallet_playability.provider_currency_unsupported'),
                app(\App\Services\Wallet\WalletPlayabilityService::class)->decision('external_games', $user, $wallet,
                    'provider_currency_unsupported', ['provider_assets' => (array) config("external_games.providers.{$providerAlias}.supported_currencies", [])])
            );
        }
        $canonicalWallet = $this->wallets->resolveForUser($user, (string) $wallet->asset);
        if (!$canonicalWallet || strtoupper((string) $canonicalWallet->ownerUser()->currency) !== strtoupper((string) $wallet->asset)) {
            throw new ExternalGameLaunchBlockedException('wallet_identity_mismatch', trans('game_access_controls.wallet_unavailable'), ['reason' => 'wallet_identity_mismatch']);
        }
        $rootUser = $canonicalWallet->rootUser();
        if ((float) $wallet->available_balance <= 0.0) {
            throw new ExternalGameLaunchBlockedException(
                'zero_balance',
                'External oyunları açmak için önce bakiye yüklemeniz gerekiyor.',
                ['reason' => 'zero_balance']
            );
        }

        $playabilitySnapshot['locale_resolution'] = $localeResolution;
        $launchPayload = $this->launchPayload(
            $game,
            $user,
            $wallet,
            $providerAlias,
            $providerGameId,
            $isDemo,
            $request,
            $localeResolution
        );
        $preparation = $this->sessions->prepareOrReuse(
            $game,
            $user,
            $rootUser,
            $wallet,
            $providerAlias,
            $providerGameId,
            $isDemo,
            $playabilitySnapshot,
            $launchPayload,
            $forceFreshLaunch ? 0 : $this->launchReuseWindowSeconds(),
            function () use ($request, $game, $user, $rootUser, $wallet, $providerAlias, $providerGameId, $isDemo): void {
                if ($isDemo) return;
                $launchGuard = $this->riskLimits->launchGuard($user, $providerAlias, $providerGameId, (int) $rootUser->id);
                if ($launchGuard === null) {
                    return;
                }

                $this->opsRadar->externalGameRiskLimitBlocked($request, null, [
                    'game_id' => (int) $game->id,
                    'game_name' => (string) $game->name,
                    'provider_alias' => $providerAlias,
                    'provider_key' => (string) ($game->type_name ?: $game->provider_id ?: ''),
                    'provider_name' => (string) ($game->type_name ?: optional($game->provider)->name ?: ''),
                    'provider_game_id' => $providerGameId,
                    'currency' => strtoupper((string) ($wallet->asset ?: 'TRY')),
                    'reason' => (string) $launchGuard['reason'],
                ]);

                throw new ExternalGameLaunchBlockedException(
                    (string) $launchGuard['reason'],
                    (string) $launchGuard['message'],
                    (array) $launchGuard['summary']
                );
            }
        );
        $session = $preparation['session'];

        if ((bool) $preparation['reused']) {
            $reused = (string) $preparation['reuse_status'] === 'pending'
                ? $this->sessions->waitForLaunchedSession($session, $this->singleFlightWaitMilliseconds())
                : $session;

            if ($reused instanceof ExternalGameSession && $reused->status === 'launched' && filled($reused->provider_launch_url)) {
                $this->opsRadar->externalGameLaunchReused($request, $reused, (string) $preparation['reuse_status']);

                return $reused->fresh(['game.provider']) ?: $reused;
            }

            if ($reused instanceof ExternalGameSession && $reused->status === 'failed') {
                $reason = (string) ($reused->failure_reason ?: 'External Games launch failed.');
                $this->opsRadar->externalGameLaunchFailed($request, $reused, $reason);

                throw new RuntimeException($reason);
            }

            throw new ExternalGameLaunchBlockedException(
                'launch_in_progress',
                'Oyun oturumu hâlâ hazırlanıyor. Lütfen birkaç saniye sonra tekrar deneyin.',
                ['reason' => 'launch_in_progress']
            );
        }

        try {
            $providerLaunch = $this->providerClient->gameLaunch((array) $session->launch_payload, $providerAlias);
            if (!ExternalGameLocalContract::frameAllowed((string)$providerLaunch['launch_url'], $providerAlias)) throw new RuntimeException('Provider frame origin is not configured.');
        } catch (RuntimeException $exception) {
            $safeProviderResponse = null;
            if ($exception instanceof ExternalGameProviderLaunchException) {
                $observedCountry = $this->trustedObservedCountry($request);
                if ($observedCountry !== null) {
                    $exception = $exception->withContext(['observed_country' => $observedCountry]);
                }
                $safeProviderResponse = ['failure' => $exception->safeDetails()];
            }

            $this->sessions->markFailed($session, $exception->getMessage(), $safeProviderResponse);
            $this->opsRadar->externalGameLaunchFailed($request, $session, $exception->getMessage());

            throw $exception;
        }

        $launched = $this->sessions->markLaunched($session, $providerLaunch);
        $this->opsRadar->externalGameLaunchSucceeded($request, $launched);

        return $launched;
    }

    private function launchReuseWindowSeconds(): int
    {
        return max(0, (int) config('external_games.launch_reuse_window_seconds', 900));
    }

    private function trustedObservedCountry(?Request $request): ?string
    {
        if (!$request instanceof Request) {
            return null;
        }

        $fields = $request->attributes->get('product_restriction_event_fields');
        $country = strtoupper(trim((string) data_get(is_array($fields) ? $fields : [], 'observed_country', '')));

        return preg_match('/^[A-Z]{2}$/', $country) === 1 ? $country : null;
    }

    private function singleFlightWaitMilliseconds(): int
    {
        return max(0, (int) config('external_games.launch_single_flight_wait_milliseconds', 5000));
    }

    private function launchPayload(
        CmsGame $game,
        CmsUser $user,
        WalletAccount $wallet,
        string $providerAlias,
        string $providerGameId,
        bool $isDemo,
        ?Request $request,
        array $localeResolution
    ): array {
        $payload = [
            'product_key' => (string) config('external_games.product_key', 'external_games'),
            'provider_alias' => $providerAlias,
            'game_id' => $providerGameId,
            'cms_game_id' => (int) $game->id,
            'user_id' => (string) $user->id,
            'user_name' => (string) ($user->username ?: $user->email ?: ('user_' . $user->id)),
            'currency' => strtoupper((string) $wallet->asset),
            'balance' => (string) $wallet->available_balance,
            'status' => (string) $user->status,
            'wallet_account_id' => (int) $wallet->id,
            'wallet_type' => (string) $wallet->wallet_type,
            'demo' => $isDemo,
            'effective_locale' => (string) $localeResolution['effective_locale'],
            'provider_locale' => (string) $localeResolution['provider_locale'],
            'locale_fallback_used' => (bool) $localeResolution['locale_fallback_used'],
            'return_url' => $request?->fullUrl(),
            'ip_address' => $request?->ip(),
        ];

        if ((string) $localeResolution['provider_locale'] !== '') {
            $payload['lang'] = (string) $localeResolution['provider_locale'];
            $payload['language'] = (string) $localeResolution['provider_locale'];
        }

        $launchPayloadProfile = $this->launchPayloadProfileForGame($game);
        if ($launchPayloadProfile !== '') {
            $payload['_launch_payload_profile'] = $launchPayloadProfile;
        }

        return $payload;
    }

    private function launchPayloadProfileForGame(CmsGame $game): string
    {
        $extra = json_decode((string) $game->extra_parameters, true);
        $extra = is_array($extra) ? $extra : [];

        $providerCandidates = array_filter(array_map(
            static fn ($value): string => strtolower(trim((string) $value)),
            [
                data_get($extra, 'external_games.vendor_id'),
                data_get($extra, 'external_games.vendor_name'),
                data_get($extra, 'external_games.raw.provider_id'),
                data_get($extra, 'external_games.raw.provider_game'),
                $game->type_name,
                $game->provider_id,
            ]
        ));

        foreach ((array) config('external_games.launch_payload_profiles', []) as $profile => $settings) {
            $configuredProviders = array_filter(array_map(
                static fn ($value): string => strtolower(trim((string) $value)),
                (array) ($settings['providers'] ?? [])
            ));

            if (count(array_intersect($providerCandidates, $configuredProviders)) > 0) {
                return (string) $profile;
            }
        }

        return '';
    }
}
