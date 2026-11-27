<?php

namespace App\Services\ExternalGames;

use App\Models\CmsGame;
use App\Models\Provider;
use App\SiteSettings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use RuntimeException;

class ExternalGameCatalogImportService
{
    public function __construct(
        private readonly PrimaryExternalProviderClient $client
    ) {
    }

    public function sync(string $providerAlias = 'primary_external_provider', bool $dryRun = false, ?int $actorId = null): array
    {
        $payload = $this->client->gamesAll($providerAlias);
        $games = $this->normalizeGames($payload);

        if ($dryRun) {
            return [
                'provider_alias' => $providerAlias,
                'dry_run' => true,
                'seen_count' => $games->count(),
                'created_count' => 0,
                'updated_count' => 0,
                'marked_passive_count' => 0,
                'last_sync_at' => now()->toDateTimeString(),
            ];
        }

        return DB::transaction(function () use ($providerAlias, $games, $actorId): array {
            $provider = $this->upsertProvider($providerAlias);
            $seenIds = [];
            $created = 0;
            $updated = 0;

            foreach ($games as $game) {
                $providerGameId = (string) $game['provider_game_id'];
                $seenIds[] = $providerGameId;

                $existing = CmsGame::query()
                    ->where('provider_id', $provider->id)
                    ->where('reference_id', $providerGameId)
                    ->first();

                $data = $this->gameData($provider, $providerAlias, $game, $existing);

                if ($existing instanceof CmsGame) {
                    $existing->update($data);
                    $updated++;
                } else {
                    CmsGame::query()->create($data);
                    $created++;
                }
            }

            $passiveQuery = CmsGame::query()
                ->where('provider_id', $provider->id)
                ->where('status', '<>', 'passive');

            if ($seenIds !== []) {
                $passiveQuery->whereNotIn('reference_id', $seenIds);
            }

            $markedPassive = $passiveQuery->update(['status' => 'passive']);

            $summary = [
                'provider_alias' => $providerAlias,
                'dry_run' => false,
                'seen_count' => count($seenIds),
                'created_count' => $created,
                'updated_count' => $updated,
                'marked_passive_count' => $markedPassive,
                'last_sync_at' => now()->toDateTimeString(),
                'last_error' => null,
                'updated_by' => $actorId,
            ];

            $this->persistSyncSummary($summary);

            return $summary;
        });
    }

    public function normalizeGames(array $payload): Collection
    {
        $games = data_get($payload, 'games');
        $games ??= data_get($payload, 'data.games');
        $games ??= data_get($payload, 'data');
        $games ??= array_is_list($payload) ? $payload : [];

        if (!is_array($games)) {
            throw new RuntimeException('External Games catalog payload does not contain a games array.');
        }

        return collect($games)
            ->map(fn ($game): ?array => $this->normalizeGame((array) $game))
            ->filter()
            ->values();
    }

    public function recordFailure(string $message, ?int $actorId = null, string $providerAlias = 'primary_external_provider'): void
    {
        $this->persistSyncSummary([
            'provider_alias' => $providerAlias,
            'dry_run' => false,
            'seen_count' => 0,
            'created_count' => 0,
            'updated_count' => 0,
            'marked_passive_count' => 0,
            'last_sync_at' => null,
            'last_error' => $message,
            'updated_by' => $actorId,
            'failed_at' => now()->toDateTimeString(),
        ]);
    }

    private function normalizeGame(array $game): ?array
    {
        $providerGameId = $this->firstString($game, ['game_id', 'id', 'gameId']);
        $name = $this->firstString($game, ['game_name', 'name', 'title']);

        if ($providerGameId === '' || $name === '') {
            return null;
        }

        $active = $this->activeFlag($game);
        if (!$active) {
            return null;
        }

        $image = $this->firstString($game, ['image', 'img', 'thumbnail', 'thumbnail_image', 'icon', 'cover', 'square']);
        $verticalImage = $this->firstString($game, ['image_vertical', 'img_vertical', 'vertical_image', 'banner']);
        $vendorId = $this->firstString($game, ['provider_id', 'vendor_id', 'providerId', 'vendorId']);
        $vendorName = $this->firstString($game, ['provider_name', 'vendor_name', 'provider', 'vendor', 'provider_game']);
        $category = $this->firstString($game, ['category', 'category_name', 'type', 'game_type']);

        return [
            'provider_game_id' => $providerGameId,
            'name' => $name,
            'vendor_id' => $vendorId,
            'vendor_name' => $vendorName,
            'category' => $category,
            'image' => $image,
            'vertical_image' => $verticalImage,
            'raw' => $game,
        ];
    }

    private function upsertProvider(string $providerAlias): Provider
    {
        $existing = Provider::where('slug', $providerAlias)->lockForUpdate()->first();
        if ($existing !== null && $existing->kind !== 'external') {
            throw new RuntimeException('External Games connector conflicts with an existing provider identity.');
        }
        $config = [
            'product_key' => (string) config('external_games.product_key', 'external_games'),
            'provider_alias' => $providerAlias,
            'catalog_source' => 'games_all',
            'demo_supported' => (bool) config("external_games.providers.{$providerAlias}.demo_supported", false),
        ];

        return Provider::query()->updateOrCreate(
            ['slug' => $providerAlias],
            [
                'name' => (string) config("external_games.providers.{$providerAlias}.label", 'External Games'),
                'kind' => 'external',
                'ref_provider_id' => is_numeric($providerAlias) ? (int) $providerAlias : null,
                'status' => $existing?->status ?: 'active',
                'config' => array_replace_recursive((array) $existing?->configData(), $config),
                'show_left_panel' => (bool) $existing?->show_left_panel,
                'is_maintenance' => (bool) $existing?->is_maintenance,
            ]
        );
    }

    private function gameData(Provider $provider, string $providerAlias, array $game, ?CmsGame $existing): array
    {
        $images = [
            'thumbnail' => $game['image'],
            'vertical' => $game['vertical_image'],
        ];
        $extra = $this->existingExtraParameters($existing);
        $extra['external_games'] = array_replace((array)($extra['external_games']??[]), [
            'product_key' => (string) config('external_games.product_key', 'external_games'),
            'provider_alias' => $providerAlias,
            'provider_game_id' => $game['provider_game_id'],
            'vendor_id' => $game['vendor_id'],
            'vendor_name' => $game['vendor_name'],
            'category' => $game['category'],
            'catalog_imported_at' => now()->toDateTimeString(),
            'raw' => $game['raw'],
        ]);

        return [
            'name' => $game['name'],
            'slug' => $existing?->slug ?: $this->slug($providerAlias, $game['name'], $game['provider_game_id']),
            'thumbnail_image' => $game['image'] ?: ($existing?->thumbnail_image),
            'detail_image' => $game['vertical_image'] ?: $game['image'] ?: ($existing?->detail_image),
            'reference_id' => $game['provider_game_id'],
            'provider_id' => $provider->id,
            'game_type' => in_array(strtolower($game['category']),array_keys(CmsGame::TYPES),true) ? strtolower($game['category']) : 'slot',
            'category_name' => $game['category'] ?: ($existing?->category_name),
            'type_name' => $game['vendor_name'] ?: ($existing?->type_name),
            'status' => $existing?->status ?: 'active',
            'images' => json_encode($images, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'extra_parameters' => json_encode($extra, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'is_restricted' => $existing instanceof CmsGame ? (int) $existing->is_restricted : 1,
        ];
    }

    private function existingExtraParameters(?CmsGame $game): array
    {
        if (!$game instanceof CmsGame) {
            return [];
        }

        if (is_array($game->extra_parameters)) {
            return $game->extra_parameters;
        }

        $decoded = json_decode((string) $game->extra_parameters, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function persistSyncSummary(array $summary): void
    {
        if (!Schema::hasTable('site_settings')) {
            return;
        }

        SiteSettings::query()->updateOrCreate(
            ['key' => ExternalGameProviderConfig::storageKey((string) config('external_games.catalog_sync_settings_key', 'external_games_catalog_sync_v1'), $summary['provider_alias'])],
            ['value' => json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]
        );

        Session::forget('settings');
    }

    private function slug(string $providerAlias, string $name, string $providerGameId): string
    {
        if ($providerAlias === ExternalGameProviderConfig::PRIMARY) {
            return Str::slug('external-games-' . $name . '-' . $providerGameId);
        }

        return Str::limit(Str::slug('external-games-' . $providerAlias . '-' . $name), 90, '')
            . '-' . substr(hash('sha256', $providerAlias . ':' . $providerGameId), 0, 16);
    }

    private function firstString(array $game, array $keys): string
    {
        foreach ($keys as $key) {
            $value = trim((string) data_get($game, $key, ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function activeFlag(array $game): bool
    {
        $status = strtolower(trim((string) data_get($game, 'status', '')));
        if ($status !== '') {
            return in_array($status, ['1', 'active', 'enabled', 'true'], true);
        }

        if (array_key_exists('active', $game)) {
            return $this->truthy($game['active']);
        }

        if (array_key_exists('is_active', $game)) {
            return $this->truthy($game['is_active']);
        }

        return true;
    }

    private function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'active', 'enabled', 'true', 'yes'], true);
    }
}
