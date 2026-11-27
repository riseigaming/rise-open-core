<?php

namespace App\Services\ExternalGames;

use App\Models\CmsGame;
use App\SiteSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use RuntimeException;

class ExternalGameProviderRolloutService
{
    public const STATE_HIDDEN = 'hidden';
    public const STATE_PREVIEW = 'preview';
    public const STATE_CANARY = 'canary';
    public const STATE_ACTIVE = 'active';
    public const STATE_MAINTENANCE = 'maintenance';
    public const STATE_DISABLED = 'disabled';

    public const STATES = [
        self::STATE_HIDDEN,
        self::STATE_PREVIEW,
        self::STATE_CANARY,
        self::STATE_ACTIVE,
        self::STATE_MAINTENANCE,
        self::STATE_DISABLED,
    ];

    public const GAME_STATE_INHERIT = 'inherit';

    private const SETTINGS_KEY = 'external_games_provider_rollout_v1';
    private const DEFAULT_STATE = self::STATE_ACTIVE;
    public const INTENT_ADMIN = 'admin';
    public const INTENT_PILOT = 'pilot';
    private const INDEX_NAME = 'site_settings_key_id_index';

    /**
     * @var array<string, mixed>|null
     */
    private ?array $settingsCache = null;

    public function decisionForGame(CmsGame $game): array
    {
        $provider = $this->providerIdentity($game);
        $stateDecision = $this->stateDecisionForGame($provider['key'], $game);
        $state = $stateDecision['state'];

        return [
            'provider_key' => $provider['key'],
            'provider_name' => $provider['name'],
            'state' => $state,
            'state_source' => $stateDecision['source'],
            'provider_state' => $stateDecision['provider_state'],
            'game_state' => $stateDecision['game_state'],
            'enabled' => !in_array($state, [self::STATE_DISABLED, self::STATE_MAINTENANCE], true),
            'maintenance' => $state === self::STATE_MAINTENANCE,
            'launchable' => in_array($state, [self::STATE_CANARY, self::STATE_ACTIVE], true),
            'visible_state' => in_array($state, [self::STATE_PREVIEW, self::STATE_CANARY, self::STATE_ACTIVE], true),
        ];
    }

    public function setProviderState(
        string $providerKey,
        string $state,
        ?int $actorId = null,
        string $intent = self::INTENT_ADMIN
    ): array {
        $providerKey = $this->normalizeProviderKey($providerKey);
        $state = in_array($state, self::STATES, true) ? $state : self::STATE_HIDDEN;
        if (!in_array($intent, [self::INTENT_ADMIN, self::INTENT_PILOT], true)) {
            throw new RuntimeException('Unknown rollout writer intent.');
        }

        // Never merge from request-local memoized settings, even if a caller
        // previously read the JSON. A later outer rollback must not poison it.
        $this->settingsCache = null;
        try {
            $settings = $this->persist($providerKey, $state, $actorId, $intent);
            Session::forget('settings');

            return $settings;
        } finally {
            $this->settingsCache = null;
        }
    }

    public function setGameState(CmsGame $game, string $state, ?int $actorId = null): CmsGame
    {
        $state = in_array($state, self::STATES, true) ? $state : self::STATE_HIDDEN;
        $extra = $this->extraParameters($game);
        $extra['external_games'] = (array) ($extra['external_games'] ?? []);
        $extra['external_games']['provider_rollout_v1'] = array_merge(
            (array) ($extra['external_games']['provider_rollout_v1'] ?? []),
            [
                'state' => $state,
                'updated_at' => now()->toDateTimeString(),
                'updated_by' => $actorId,
            ]
        );

        $game->forceFill([
            'extra_parameters' => json_encode($extra, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ])->save();

        return $game->refresh();
    }

    public function clearGameState(CmsGame $game, ?int $actorId = null): CmsGame
    {
        $extra = $this->extraParameters($game);
        if (isset($extra['external_games']) && is_array($extra['external_games'])) {
            unset($extra['external_games']['provider_rollout_v1']);
            $extra['external_games']['provider_rollout_v1_cleared_at'] = now()->toDateTimeString();
            $extra['external_games']['provider_rollout_v1_cleared_by'] = $actorId;
        }

        $game->forceFill([
            'extra_parameters' => json_encode($extra, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ])->save();

        return $game->refresh();
    }

    public function gameSummaries(array $filters = [], int $limit = 100): array
    {
        $limit = max(1, min(200, $limit));
        $query = $this->externalGamesQuery()->with('provider');

        $search = trim((string) ($filters['q'] ?? $filters['search'] ?? ''));
        if ($search !== '') {
            $like = '%' . $this->escapeLike($search) . '%';
            $query->where(function (Builder $searchQuery) use ($like): void {
                $searchQuery->where('cms_games.name', 'like', $like)
                    ->orWhere('cms_games.reference_id', 'like', $like)
                    ->orWhere('cms_games.type_name', 'like', $like)
                    ->orWhere('cms_games.extra_parameters', 'like', $like);
            });
        }

        $provider = trim((string) ($filters['provider'] ?? ''));
        if ($provider !== '') {
            $provider = $this->normalizeProviderKey($provider);
            $query->where(function (Builder $providerQuery) use ($provider): void {
                $providerQuery->where('cms_games.type_name', 'like', '%' . $this->escapeLike($provider) . '%')
                    ->orWhere('cms_games.extra_parameters', 'like', '%"vendor_id":"' . $this->escapeLike($provider) . '"%')
                    ->orWhere('cms_games.extra_parameters', 'like', '%"provider_id":"' . $this->escapeLike($provider) . '"%');
            });
        }

        $query->orderByDesc('cms_games.is_featured');
        if (Schema::hasColumn('cms_games', 'show_in_special_games')) {
            $query->orderByDesc('cms_games.show_in_special_games');
        }

        $rows = $query
            ->orderBy('cms_games.name')
            ->limit($limit * 4)
            ->get()
            ->map(fn (CmsGame $game): array => $this->gameSummary($game))
            ->filter(function (array $row) use ($filters): bool {
                $state = trim((string) ($filters['state'] ?? ''));
                if ($state !== '' && $row['state'] !== $state) {
                    return false;
                }

                return true;
            })
            ->take($limit)
            ->values()
            ->all();

        return [
            'rows' => $rows,
            'limit' => $limit,
            'has_more' => count($rows) === $limit,
        ];
    }

    public function isExternalGame(CmsGame $game): bool
    {
        $extra = $this->extraParameters($game);
        if (data_get($extra, 'external_games.product_key') === (string) config('external_games.product_key', 'external_games')) {
            return true;
        }

        $providerAlias = (string) data_get($extra, 'external_games.provider_alias', '');
        if ($providerAlias !== '') {
            return true;
        }

        return in_array((string) $game->provider?->slug, array_keys((array) config('external_games.providers', [])), true);
    }

    public function settings(): array
    {
        $inTransaction = DB::transactionLevel() > 0;
        if (!$inTransaction && $this->settingsCache !== null) {
            return $this->settingsCache;
        }

        if (!Schema::hasTable('site_settings')) {
            $defaults = $this->defaults();

            return $inTransaction ? $defaults : $this->settingsCache = $defaults;
        }

        $value = SiteSettings::query()
            ->where('key', self::SETTINGS_KEY)
            ->value('value');
        $decoded = json_decode((string) $value, true);
        $settings = array_replace_recursive($this->defaults(), is_array($decoded) ? $decoded : []);

        // Do not memoize uncommitted values during a caller's transaction.
        return $inTransaction ? $settings : $this->settingsCache = $settings;
    }

    public function providerSummaries(int $limit = 40): array
    {
        $settings = $this->settings();
        $providers = [];

        $this->externalGamesQuery()
            ->orderBy('id')
            ->chunkById(500, function ($games) use (&$providers, $settings): void {
                foreach ($games as $game) {
                    $identity = $this->providerIdentity($game);
                    $key = $identity['key'];
                    if ($key === '') {
                        continue;
                    }

                    $providers[$key] ??= [
                        'key' => $key,
                        'name' => $identity['name'],
                        'state' => (string) data_get($settings, "providers.{$key}.state", self::DEFAULT_STATE),
                        'game_count' => 0,
                        'visible_game_count' => 0,
                        'playable_game_count' => 0,
                        'last_successful_launch' => null,
                        'last_launch_failure' => null,
                        'last_bet_callback' => null,
                        'last_win_refund_callback' => null,
                        'launches_24h' => 0,
                        'callback_success_24h' => 0,
                        'callback_failure_24h' => 0,
                        'expired_launch_without_callback_24h' => 0,
                        'last_critical_error' => null,
                        'last_critical_error_at' => null,
                    ];

                    $decision = $this->decisionForGame($game);
                    $baseVisible = (bool) $game->is_featured || (bool) $game->show_in_special_games;
                    $visible = $baseVisible && (bool) $decision['visible_state'];
                    $playable = $visible
                        && (string) $game->status === 'active'
                        && (int) $game->is_restricted === 0
                        && (bool) $decision['launchable'];

                    $providers[$key]['game_count']++;
                    $providers[$key]['visible_game_count'] += $visible ? 1 : 0;
                    $providers[$key]['playable_game_count'] += $playable ? 1 : 0;
                }
            });

        $this->appendSessionStats($providers);
        $this->appendTransactionEvidence($providers);
        $this->resolveStaleExpiredLaunchSignals($providers);

        return collect($providers)
            ->sortBy([
                ['playable_game_count', 'desc'],
                ['visible_game_count', 'desc'],
                ['game_count', 'desc'],
                ['name', 'asc'],
            ])
            ->take(max(1, min(200, $limit)))
            ->values()
            ->all();
    }

    public function providerIdentity(CmsGame $game, ?string $storedAlias = null): array
    {
        $extra = $this->extraParameters($game);
        $external = (array) data_get($extra, 'external_games', []);
        $raw = (array) data_get($external, 'raw', []);
        $providerId = $this->firstString([
            $external['vendor_id'] ?? null,
            $raw['provider_id'] ?? null,
            $raw['vendor_id'] ?? null,
        ]);
        $providerName = $this->firstString([
            $external['vendor_name'] ?? null,
            $raw['provider_game'] ?? null,
            $raw['provider_name'] ?? null,
            $raw['vendor_name'] ?? null,
            $game->type_name,
        ]);
        $fallback = $providerName !== '' ? $providerName : $providerId;
        $key = $this->normalizeProviderKey($providerId !== '' ? $providerId : $fallback);
        $alias = $storedAlias ?? (string) ($game->provider?->slug ?: ($external['provider_alias'] ?? ExternalGameProviderConfig::PRIMARY));
        if ($alias !== ExternalGameProviderConfig::PRIMARY && isset(ExternalGameProviderConfig::providers()[$alias])) {
            $key = $alias . ':' . $key;
        }

        return [
            'id' => $providerId,
            'name' => $providerName !== '' ? $providerName : 'External Provider',
            'key' => $key,
        ];
    }

    private function stateDecisionForGame(string $providerKey, CmsGame $game): array
    {
        $providerKey = $this->normalizeProviderKey($providerKey);
        $providerState = (string) data_get($this->settings(), "providers.{$providerKey}.state", '');
        $providerState = in_array($providerState, self::STATES, true) ? $providerState : self::DEFAULT_STATE;

        $gameState = (string) data_get($this->extraParameters($game), 'external_games.provider_rollout_v1.state', '');
        if (in_array($gameState, self::STATES, true)) {
            if (in_array($providerState, [self::STATE_DISABLED, self::STATE_MAINTENANCE], true)) {
                return [
                    'state' => $providerState,
                    'source' => 'provider',
                    'provider_state' => $providerState,
                    'game_state' => $gameState,
                ];
            }

            return [
                'state' => $gameState,
                'source' => 'game',
                'provider_state' => $providerState,
                'game_state' => $gameState,
            ];
        }

        return [
            'state' => $providerState,
            'source' => 'provider',
            'provider_state' => $providerState,
            'game_state' => null,
        ];
    }

    private function gameSummary(CmsGame $game): array
    {
        $identity = $this->providerIdentity($game);
        $decision = $this->decisionForGame($game);
        $baseVisible = (bool) $game->is_featured || (bool) $game->show_in_special_games;
        $visible = $baseVisible && (bool) $decision['visible_state'];
        $playable = $visible
            && (string) $game->status === 'active'
            && (int) $game->is_restricted === 0
            && (bool) $decision['launchable'];

        return [
            'id' => (int) $game->id,
            'name' => (string) $game->name,
            'slug' => (string) $game->slug,
            'reference_id' => (string) $game->reference_id,
            'provider_key' => $identity['key'],
            'provider_name' => $identity['name'],
            'state' => $decision['state'],
            'state_source' => $decision['state_source'],
            'provider_state' => $decision['provider_state'],
            'game_state' => $decision['game_state'],
            'visible' => $visible,
            'playable' => $playable,
            'show_in_special_games' => (bool) $game->show_in_special_games,
            'special_games_rank' => $game->special_games_rank,
            'status' => (string) $game->status,
            'is_restricted' => (bool) $game->is_restricted,
            'public_url' => route('cms.games.detail', ['slug' => $game->slug . '--' . $game->id]),
        ];
    }

    private function persist(string $providerKey, string $state, ?int $actorId, string $intent): array
    {
        if (!Schema::hasTable('site_settings')) {
            throw new RuntimeException('Rollout settings storage is unavailable.');
        }

        $this->assertNarrowSettingsIndex();

        return DB::transaction(function () use ($providerKey, $state, $actorId, $intent): array {
            $controlsKey = (string) config('external_games.controls_settings_key', 'external_games_controls_v1');

            // The indexed current read protects the anchor key range, including
            // concurrent insert/delete, for this short transaction only.
            $anchors = $this->lockedSettingsRows($controlsKey, ['id', 'key']);
            if ($anchors->count() !== 1 || (string) $anchors->first()->key !== $controlsKey) {
                throw new RuntimeException('Canonical External Games controls anchor must be a singleton.');
            }

            $rows = $this->lockedSettingsRows(self::SETTINGS_KEY, ['id', 'value']);
            if ($rows->count() > 1) {
                throw new RuntimeException('Duplicate External Games rollout settings.');
            }

            if ($rows->count() === 1) {
                $settings = json_decode((string) $rows->first()->value, true);
                if (!is_array($settings) || !isset($settings['providers'])
                    || !is_array($settings['providers']) || array_is_list($settings['providers']) && $settings['providers'] !== []) {
                    throw new RuntimeException('Invalid External Games rollout settings document.');
                }
                $settings = array_replace_recursive($this->defaults(), $settings);
            } else {
                $settings = $this->defaults();
            }

            $prior = (string) data_get($settings, 'providers.'.$providerKey.'.state', self::DEFAULT_STATE);
            if ($intent === self::INTENT_PILOT
                && in_array($prior, [self::STATE_DISABLED, self::STATE_MAINTENANCE], true)) {
                // Roll back the entire pilot transaction, including earlier game writes.
                throw new RuntimeException('Pilot cannot override an operator-disabled or maintained provider.');
            }

            $settings['providers'][$providerKey] = array_merge(
                (array) ($settings['providers'][$providerKey] ?? []),
                [
                    'state' => $state,
                    'updated_at' => now()->toDateTimeString(),
                    'updated_by' => $actorId,
                ]
            );
            $settings['updated_at'] = now()->toDateTimeString();
            $serialized = json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

            if ($rows->count() === 1) {
                SiteSettings::query()->whereKey($rows->first()->id)->update(['value' => $serialized]);
            } else {
                SiteSettings::query()->create(['key' => self::SETTINGS_KEY, 'value' => $serialized]);
            }

            return $settings;
        }, 1); // Strictly one attempt. Propagate 1020/1205/1213 to outer caller.
    }

    private function lockedSettingsRows(string $key, array $columns): Collection
    {
        $query = SiteSettings::query();

        if (DB::getDriverName() === 'mysql') {
            // A nonunique index alone does not ensure that the optimizer uses it.
            // Without this hint a small settings table can still be fully locked.
            $query->from(DB::raw('site_settings FORCE INDEX (site_settings_key_id_index)'));
        }

        return $query->where('key', $key)->orderBy('id')
            ->lockForUpdate()->get($columns);
    }

    private function assertNarrowSettingsIndex(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return; // SQLite feature fixtures have no InnoDB row/gap locks.
        }

        $indexes = DB::select(
            "SELECT INDEX_NAME, COLUMN_NAME, SEQ_IN_INDEX, NON_UNIQUE
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'site_settings'
               AND INDEX_NAME = ?
             ORDER BY SEQ_IN_INDEX",
            [self::INDEX_NAME]
        );
        if (count($indexes) !== 2 || (string) $indexes[0]->COLUMN_NAME !== 'key'
            || (int) $indexes[0]->SEQ_IN_INDEX !== 1
            || (string) $indexes[1]->COLUMN_NAME !== 'id'
            || (int) $indexes[1]->SEQ_IN_INDEX !== 2
            || (int) $indexes[0]->NON_UNIQUE !== 1
            || (int) $indexes[1]->NON_UNIQUE !== 1) {
            throw new RuntimeException('Safe rollout write index is not installed.');
        }
    }

    private function appendSessionStats(array &$providers): void
    {
        if (!Schema::hasTable('external_game_sessions') || !Schema::hasTable('cms_games')) {
            return;
        }

        DB::table('external_game_sessions')
            ->join('cms_games', 'cms_games.id', '=', 'external_game_sessions.cms_game_id')
            ->leftJoin('cms_users', 'cms_users.id', '=', 'external_game_sessions.cms_user_id')
            ->select([
                'external_game_sessions.id',
                'external_game_sessions.provider_alias',
                'external_game_sessions.launched_at',
                'external_game_sessions.expires_at',
                'external_game_sessions.failed_at',
                'external_game_sessions.failure_reason',
                'external_game_sessions.status',
                'external_game_sessions.cms_user_id',
                'external_game_sessions.cms_game_id',
                'external_game_sessions.last_callback_command',
                'external_game_sessions.last_callback_status',
                'external_game_sessions.last_callback_at',
                'external_game_sessions.last_callback_payload',
                'external_game_sessions.launch_payload',
                'external_game_sessions.playability_snapshot',
                'external_game_sessions.provider_launch_reference',
                'cms_games.id as game_id',
                'cms_games.name as game_name',
                'cms_games.type_name',
                'cms_games.extra_parameters',
                'cms_users.username as cms_username',
                'cms_users.email as cms_user_email',
                'cms_users.role as cms_user_role',
            ])
            ->whereIn('external_game_sessions.provider_alias', array_keys(ExternalGameProviderConfig::providers()))
            ->where(function ($query): void {
                $query->where('external_game_sessions.launched_at', '>=', now()->subDay())
                    ->orWhere('external_game_sessions.last_callback_at', '>=', now()->subDay())
                    ->orWhereNotNull('external_game_sessions.launched_at');
            })
            ->orderByDesc('external_game_sessions.id')
            ->limit(2000)
            ->get()
            ->each(function ($row) use (&$providers): void {
                $game = new CmsGame([
                    'type_name' => $row->type_name,
                    'extra_parameters' => $row->extra_parameters,
                ]);
                $identity = $this->providerIdentity($game, $row->provider_alias);
                $key = $identity['key'];
                if (!isset($providers[$key])) {
                    return;
                }

                if ($row->launched_at !== null && $row->launched_at >= now()->subDay()->toDateTimeString()) {
                    $providers[$key]['launches_24h']++;
                }

                if (
                    (string) $row->status === 'launched'
                    && $row->launched_at !== null
                    && $row->launched_at >= now()->subDay()->toDateTimeString()
                    && $row->expires_at !== null
                    && $row->expires_at < now()->toDateTimeString()
                    && $row->last_callback_at === null
                    && $row->failed_at === null
                    && !$this->isHistoricalLaunchOnlySmokeSession($row)
                ) {
                    $providers[$key]['expired_launch_without_callback_24h']++;
                    $providers[$key]['last_critical_error'] ??= 'expired_launch_without_callback';
                    $providers[$key]['last_critical_error_at'] ??= (string) ($row->expires_at ?: $row->launched_at);
                }

                if ($row->last_callback_at !== null && $row->last_callback_at >= now()->subDay()->toDateTimeString()) {
                    if ($row->last_callback_status === 'success') {
                        $providers[$key]['callback_success_24h']++;
                    } elseif (!$this->isNonCriticalCallbackStatus((string) $row->last_callback_status)) {
                        $providers[$key]['callback_failure_24h']++;
                        $providers[$key]['last_critical_error'] ??= (string) $row->last_callback_status;
                        $providers[$key]['last_critical_error_at'] ??= (string) $row->last_callback_at;
                    }
                }

                if ($row->launched_at !== null && (
                    $providers[$key]['last_successful_launch'] === null
                    || $row->launched_at > $providers[$key]['last_successful_launch']
                )) {
                    $providers[$key]['last_successful_launch'] = (string) $row->launched_at;
                }

                if ($row->failed_at !== null && (
                    $providers[$key]['last_launch_failure'] === null
                    || $row->failed_at > $providers[$key]['last_launch_failure']
                )) {
                    $providers[$key]['last_launch_failure'] = (string) $row->failed_at;
                    $providers[$key]['last_critical_error'] ??= (string) ($row->failure_reason ?: 'launch_failed');
                    $providers[$key]['last_critical_error_at'] ??= (string) $row->failed_at;
                }
            });
    }

    private function resolveStaleExpiredLaunchSignals(array &$providers): void
    {
        foreach ($providers as &$provider) {
            if (($provider['last_critical_error'] ?? null) !== 'expired_launch_without_callback') {
                continue;
            }

            $lastBetCallback = (string) ($provider['last_bet_callback'] ?? '');
            $lastCriticalErrorAt = (string) ($provider['last_critical_error_at'] ?? '');
            if ($lastBetCallback === '' || $lastCriticalErrorAt === '' || $lastBetCallback < $lastCriticalErrorAt) {
                continue;
            }

            $provider['expired_launch_without_callback_24h'] = 0;
            $provider['last_critical_error'] = null;
            $provider['last_critical_error_at'] = null;
        }
        unset($provider);
    }

    private function isHistoricalLaunchOnlySmokeSession(object $session): bool
    {
        if (!$this->isProviderSmokeSession($session)) {
            return false;
        }

        return !$this->hasSessionFinancialEvidence($session);
    }

    private function isProviderSmokeSession(object $session): bool
    {
        $haystack = strtolower(implode(' ', array_filter([
            (string) ($session->cms_username ?? ''),
            (string) ($session->cms_user_email ?? ''),
            (string) ($session->cms_user_role ?? ''),
            (string) ($session->game_name ?? ''),
            (string) ($session->provider_launch_reference ?? ''),
            (string) ($session->failure_reason ?? ''),
            json_encode(json_decode((string) ($session->launch_payload ?? ''), true) ?: (string) ($session->launch_payload ?? '')),
            json_encode(json_decode((string) ($session->playability_snapshot ?? ''), true) ?: (string) ($session->playability_snapshot ?? '')),
            json_encode(json_decode((string) ($session->last_callback_payload ?? ''), true) ?: (string) ($session->last_callback_payload ?? '')),
        ])));

        foreach (['admin', 'external_live_canary', 'canary', 'smoke', 'test_user', 'test-user', 'test_smoke'] as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function isNonCriticalCallbackStatus(string $status): bool
    {
        return in_array($status, [
            'zero_amount_ignored',
            'zero_amount_success',
            'idempotent_replay',
        ], true);
    }

    private function hasSessionFinancialEvidence(object $session): bool
    {
        if (Schema::hasTable('wallet_ledger_entries')) {
            $sessionId = (int) ($session->id ?? 0);
            if ($sessionId > 0 && DB::table('wallet_ledger_entries')
                ->where('idempotency_key', 'like', 'external_games:%')
                ->where('cms_user_id', (int) ($session->cms_user_id ?? 0))
                ->where('meta', 'like', '%external_game_session_id%')
                ->where('meta', 'like', '%' . $sessionId . '%')
                ->exists()) {
                return true;
            }
        }

        if (Schema::hasTable('cms_user_transactions') && !empty($session->cms_game_id) && !empty($session->cms_user_id)) {
            $query = DB::table('cms_user_transactions')
                ->where('transactionId', 'like', 'external_games:%')
                ->where('cms_user_id', (int) $session->cms_user_id)
                ->where('game_id', (int) $session->cms_game_id);

            if (!empty($session->launched_at)) {
                $query->where('created_at', '>=', $session->launched_at);
            }

            return $query->exists();
        }

        return false;
    }

    private function appendTransactionEvidence(array &$providers): void
    {
        if (!Schema::hasTable('cms_user_transactions') || !Schema::hasTable('cms_games')) {
            return;
        }

        DB::table('cms_user_transactions')
            ->leftJoin('cms_games', 'cms_games.id', '=', 'cms_user_transactions.game_id')
            ->select([
                'cms_user_transactions.event_type',
                'cms_user_transactions.transactionId',
                'cms_user_transactions.created_at',
                'cms_games.type_name',
                'cms_games.extra_parameters',
            ])
            ->where(function ($query): void {
                $query->whereIn('cms_user_transactions.event_type', [
                    'external_games_transaction_bet_debit',
                    'external_games_transaction_win_credit',
                    'external_games_refund_credit',
                ])->orWhere('cms_user_transactions.transactionId', 'like', 'external_games:%');
            })
            ->orderByDesc('cms_user_transactions.id')
            ->limit(2000)
            ->get()
            ->each(function ($row) use (&$providers): void {
                $game = new CmsGame([
                    'type_name' => $row->type_name,
                    'extra_parameters' => $row->extra_parameters,
                ]);
                $alias = explode(':', (string) $row->transactionId, 3)[1] ?? ExternalGameProviderConfig::PRIMARY;
                $identity = $this->providerIdentity($game, $alias);
                $key = $identity['key'];
                if (!isset($providers[$key])) {
                    return;
                }

                $eventType = (string) $row->event_type;
                $transactionId = (string) $row->transactionId;
                $createdAt = (string) $row->created_at;

                if ($eventType === 'external_games_transaction_bet_debit' || str_contains($transactionId, ':transaction_bet:')) {
                    if ($providers[$key]['last_bet_callback'] === null || $createdAt > $providers[$key]['last_bet_callback']) {
                        $providers[$key]['last_bet_callback'] = $createdAt;
                    }
                }

                if (
                    $eventType === 'external_games_transaction_win_credit'
                    || $eventType === 'external_games_refund_credit'
                    || str_contains($transactionId, ':transaction_win:')
                    || str_contains($transactionId, ':refund:')
                ) {
                    if ($providers[$key]['last_win_refund_callback'] === null || $createdAt > $providers[$key]['last_win_refund_callback']) {
                        $providers[$key]['last_win_refund_callback'] = $createdAt;
                    }
                }
            });
    }

    private function externalGamesQuery(): Builder
    {
        return CmsGame::query()
            ->where(function (Builder $query): void {
                $query->where('extra_parameters', 'like', '%"external_games"%')
                    ->orWhereHas('provider', function (Builder $providerQuery): void {
                        $providerQuery->where('slug', (string) config('external_games.default_provider_alias', 'primary_external_provider'));
                    });
            });
    }

    private function defaults(): array
    {
        return [
            'default_state' => self::DEFAULT_STATE,
            'providers' => [],
            'updated_at' => null,
        ];
    }

    private function extraParameters(CmsGame $game): array
    {
        if (is_array($game->extra_parameters)) {
            return $game->extra_parameters;
        }

        $decoded = json_decode((string) $game->extra_parameters, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function normalizeProviderKey(string $value): string
    {
        $value = trim($value);
        if (preg_match('/\A([a-z0-9_-]{1,64}):([a-z0-9-]+)\z/', $value, $parts)
            && $parts[1] !== ExternalGameProviderConfig::PRIMARY
            && isset(ExternalGameProviderConfig::providers()[$parts[1]])) {
            return $value;
        }

        return $value !== '' ? (string) Str::of($value)->lower()->replaceMatches('/[^a-z0-9]+/', '-')->trim('-') : 'unknown';
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '\\%_');
    }

    private function firstString(array $values): string
    {
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}
