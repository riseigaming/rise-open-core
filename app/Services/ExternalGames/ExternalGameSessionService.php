<?php

namespace App\Services\ExternalGames;

use App\Models\CmsGame;
use App\Models\CmsUser;
use App\Models\ExternalGameSession;
use App\Models\WalletAccount;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ExternalGameSessionService
{
    public function __construct(
        private readonly ExternalGameAudit $opsRadar
    ) {
    }

    public function prepare(
        CmsGame $game,
        CmsUser $user,
        CmsUser $rootUser,
        WalletAccount $wallet,
        string $providerAlias,
        string $providerGameId,
        bool $isDemo,
        array $playabilitySnapshot,
        array $launchPayload
    ): ExternalGameSession {
        $sessionToken = (string) Str::uuid();
        $launchPayload = array_merge($launchPayload, [
            'session_token' => $sessionToken,
            'callback_session_token' => $sessionToken,
        ]);
        $payloadDigest = hash('sha256', json_encode($launchPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return DB::transaction(function () use ($game, $user, $rootUser, $wallet, $providerAlias, $providerGameId, $isDemo, $playabilitySnapshot, $launchPayload, $payloadDigest, $sessionToken): ExternalGameSession {
            $now = now();

            // Another tab/launch must not invalidate this provider session.

            $session = ExternalGameSession::query()->create([
                'provider_alias' => $providerAlias,
                'provider_id' => $game->provider_id,
                'cms_game_id' => $game->id,
                'provider_game_id' => $providerGameId,
                'cms_user_id' => $user->id,
                'root_user_id' => $rootUser->id,
                'wallet_account_id' => $wallet->id,
                'wallet_account_type' => (string) $wallet->wallet_type,
                'wallet_account_key' => (string) $wallet->wallet_key,
                'wallet_currency' => strtoupper((string) $wallet->asset),
                'mode' => $isDemo ? 'demo' : 'real',
                'session_token' => $sessionToken,
                'playability_snapshot' => $playabilitySnapshot,
                'launch_payload_digest' => $payloadDigest,
                'launch_payload' => $launchPayload,
                'status' => 'pending',
                'expires_at' => $now->copy()->addHours(6),
            ]);


            return $session;
        });
    }

    /**
     * @return array{session:ExternalGameSession,reused:bool,reuse_status:string}
     */
    public function prepareOrReuse(
        CmsGame $game,
        CmsUser $user,
        CmsUser $rootUser,
        WalletAccount $wallet,
        string $providerAlias,
        string $providerGameId,
        bool $isDemo,
        array $playabilitySnapshot,
        array $launchPayload,
        int $reuseWindowSeconds,
        ?Closure $beforeCreate = null
    ): array {
        $reuseWindowSeconds = max(0, $reuseWindowSeconds);

        return DB::transaction(function () use ($game, $user, $rootUser, $wallet, $providerAlias, $providerGameId, $isDemo, $playabilitySnapshot, $launchPayload, $reuseWindowSeconds, $beforeCreate): array {
            $now = now();
            // Serialize all currency projections of the same owner.
            CmsUser::query()->whereKey($rootUser->id)->lockForUpdate()->first();

            if ($reuseWindowSeconds > 0) {
                $reusable = $this->sameGameSessionQuery($user, $rootUser, $providerAlias, $providerGameId, $now, $reuseWindowSeconds)
                    ->where('wallet_account_id', $wallet->id)
                    ->where('wallet_currency', strtoupper((string) $wallet->asset))
                    ->where('mode', $isDemo ? 'demo' : 'real')
                    ->where('status', 'launched')
                    ->whereNotNull('provider_launch_url')
                    ->where('provider_launch_url', '!=', '')
                    ->lockForUpdate()
                    ->first();

                if ($reusable instanceof ExternalGameSession) {
                    if ($this->launchLocaleMatches($reusable, $launchPayload)) {
                        return [
                            'session' => $reusable,
                            'reused' => true,
                            'reuse_status' => 'launched',
                        ];
                    }
                }

                // Pending/abandoned launches are never a prerequisite for a new
                // launch. Each fresh attempt gets its own immutable identity.
            }

            if ($beforeCreate instanceof Closure) {
                $beforeCreate();
            }

            $sessionToken = (string) Str::uuid();
            $launchPayload = array_merge($launchPayload, [
                'session_token' => $sessionToken,
                'callback_session_token' => $sessionToken,
            ]);
            $payloadDigest = hash('sha256', json_encode($launchPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            // Keep prior provider identities available for callbacks.

            $session = ExternalGameSession::query()->create([
                'provider_alias' => $providerAlias,
                'provider_id' => $game->provider_id,
                'cms_game_id' => $game->id,
                'provider_game_id' => $providerGameId,
                'cms_user_id' => $user->id,
                'root_user_id' => $rootUser->id,
                'wallet_account_id' => $wallet->id,
                'wallet_account_type' => (string) $wallet->wallet_type,
                'wallet_account_key' => (string) $wallet->wallet_key,
                'wallet_currency' => strtoupper((string) $wallet->asset),
                'mode' => $isDemo ? 'demo' : 'real',
                'session_token' => $sessionToken,
                'playability_snapshot' => $playabilitySnapshot,
                'launch_payload_digest' => $payloadDigest,
                'launch_payload' => $launchPayload,
                'status' => 'pending',
                'expires_at' => $now->copy()->addHours(6),
            ]);


            return [
                'session' => $session,
                'reused' => false,
                'reuse_status' => 'created',
            ];
        });
    }

    public function waitForLaunchedSession(ExternalGameSession $session, int $timeoutMilliseconds): ?ExternalGameSession
    {
        $deadline = microtime(true) + (max(0, $timeoutMilliseconds) / 1000);

        do {
            $fresh = $session->fresh();

            if (!$fresh instanceof ExternalGameSession) {
                return null;
            }

            if ($fresh->status === 'launched' && filled($fresh->provider_launch_url)) {
                return $fresh;
            }

            if (in_array($fresh->status, ['failed', 'superseded'], true)) {
                return $fresh;
            }

            usleep(100_000);
        } while (microtime(true) < $deadline);

        return $session->fresh();
    }

    private function launchLocaleMatches(ExternalGameSession $session, array $launchPayload): bool
    {
        return (string) data_get($session->launch_payload, 'provider_locale', data_get($session->launch_payload, 'lang', ''))
            === (string) ($launchPayload['provider_locale'] ?? $launchPayload['lang'] ?? '');
    }

    public function markLaunched(ExternalGameSession $session, array $providerLaunch): ExternalGameSession
    {
        $session->forceFill([
            'provider_launch_url' => (string) $providerLaunch['launch_url'],
            'provider_launch_reference' => $providerLaunch['provider_reference'] ?? null,
            'provider_response' => $providerLaunch['raw'] ?? $providerLaunch,
            'status' => 'launched',
            'launched_at' => now(),
            'failure_reason' => null,
        ])->save();

        return $session->fresh();
    }

    public function markFailed(
        ExternalGameSession $session,
        string $reason,
        ?array $safeProviderResponse = null
    ): ExternalGameSession
    {
        $attributes = [
            'status' => 'failed',
            'failed_at' => now(),
            'failure_reason' => mb_substr($reason, 0, 500),
        ];

        if ($safeProviderResponse !== null) {
            $attributes['provider_response'] = $safeProviderResponse;
        }

        $session->forceFill($attributes)->save();

        return $session->fresh();
    }

    private function sameGameSessionQuery(
        CmsUser $user,
        CmsUser $rootUser,
        string $providerAlias,
        string $providerGameId,
        mixed $now,
        int $reuseWindowSeconds
    ) {
        $cutoff = $now->copy()->subSeconds($reuseWindowSeconds);

        return ExternalGameSession::query()
            ->where('provider_alias', $providerAlias)
            ->where('provider_game_id', $providerGameId)
            ->where('cms_user_id', $user->id)
            ->where('root_user_id', $rootUser->id)
            ->where(function ($expires) use ($now): void {
                $expires->whereNull('expires_at')->orWhere('expires_at', '>=', $now);
            })
            ->where(function ($fresh) use ($cutoff): void {
                $fresh->where('updated_at', '>=', $cutoff)
                    ->orWhere('created_at', '>=', $cutoff)
                    ->orWhere('launched_at', '>=', $cutoff);
            })
            ->latest('id');
    }
}
