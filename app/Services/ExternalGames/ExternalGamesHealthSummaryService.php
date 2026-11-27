<?php

namespace App\Services\ExternalGames;

use App\Models\CmsGame;
use App\Models\ExternalGameSession;
use App\Models\Provider;
use App\SiteSettings;
use Illuminate\Support\Facades\Schema;

class ExternalGamesHealthSummaryService
{
    public function __construct(private readonly ExternalGameCallbackHealthService $callbackHealth)
    {
    }

    public function summary(string $providerAlias = 'primary_external_provider'): array
    {
        $provider = $this->provider($providerAlias);
        $sync = $this->syncSummary($providerAlias);
        $callbacks = $this->callbackHealth->forProvider($providerAlias)->summary();

        $importedCount = 0;
        $activeCount = 0;
        $launches24h = 0;

        if ($provider instanceof Provider && Schema::hasTable('cms_games')) {
            $base = CmsGame::query()->where('provider_id', $provider->id);
            $importedCount = (clone $base)->count();
            $activeCount = (clone $base)
                ->where('status', 'active')
                ->where('is_restricted', 0)
                ->count();
        }

        if (Schema::hasTable('external_game_sessions')) {
            $launches24h = ExternalGameSession::query()
                ->where('provider_alias', $providerAlias)
                ->whereNotNull('launched_at')
                ->where('launched_at', '>=', now()->subDay())
                ->count();
        }

        return [
            'last_sync' => $sync['last_sync_at'] ?? null,
            'imported_game_count' => $importedCount,
            'active_playable_game_count' => $activeCount,
            'launches_24h' => $launches24h,
            'callbacks_24h' => (int) ($callbacks['callbacks_24h'] ?? 0),
            'callback_failures_24h' => (int) ($callbacks['callback_failures_24h'] ?? 0),
            'idempotent_replays_24h' => (int) ($callbacks['idempotent_replays_24h'] ?? 0),
            'conflicts_24h' => (int) ($callbacks['conflicts_24h'] ?? 0),
            'risk_limit_rejections_24h' => (int) ($callbacks['risk_limit_rejections_24h'] ?? 0),
            'last_critical_error' => $callbacks['last_critical_error'] ?? ($sync['last_error'] ?? null),
        ];
    }

    private function provider(string $providerAlias): ?Provider
    {
        if (!Schema::hasTable('providers')) {
            return null;
        }

        $query = Provider::query()->where('slug', $providerAlias);

        if (is_numeric($providerAlias)) {
            $query->orWhere('ref_provider_id', (int) $providerAlias);
        }

        return $query->first();
    }

    private function syncSummary(string $providerAlias): array
    {
        if (!Schema::hasTable('site_settings')) {
            return [];
        }

        $raw = (string) SiteSettings::query()
            ->where('key', ExternalGameProviderConfig::storageKey((string) config('external_games.catalog_sync_settings_key', 'external_games_catalog_sync_v1'), $providerAlias))
            ->value('value');

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
