<?php
namespace Database\Seeders;
use Illuminate\Support\Facades\{DB,Schema};
class HostedChessCatalogSeeder extends \Illuminate\Database\Seeder {
 private const SOURCE_REFERENCE='provider:chess-bet';
 public function run(){ $this->seedProvider();$this->seedGame(); }
    private function seedProvider()
    {
        $slug = config('chess_wallet.provider_slug', 'chess-betting-provider');

        $exists = DB::table('providers')->where('slug', $slug)->exists();
        if ($exists) {
            return;
        }

        DB::table('providers')->insert([
            'name' => config('chess_wallet.provider_name', 'Chess Betting Provider'),
            'slug' => $slug,
            'kind' => 'external',
            'show_left_panel' => true,
            'status' => 'active',
            'config' => json_encode(config('chess_wallet.default_provider_config', []), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedGame()
    {
        if (!Schema::hasTable('providers') || !Schema::hasTable('cms_games')) {
            return;
        }

        $providerSlug = (string) config('chess_wallet.provider_slug', 'chess-betting-provider');
        $provider = DB::table('providers')
            ->where('slug', $providerSlug)
            ->first();

        if (!$provider) {
            return;
        }

        $existing = DB::table('cms_games')
            ->where('provider_id', $provider->id)
            ->where(function ($query) {
                $query->where('slug', 'chess-bet')
                    ->orWhere('extra_parameters', 'like', '%"source_reference":"' . self::SOURCE_REFERENCE . '"%');
            })
            ->orderByDesc('id')
            ->first();

        $extraParameters = [];
        if ($existing && !empty($existing->extra_parameters)) {
            $extraParameters = json_decode((string) $existing->extra_parameters, true) ?: [];
        }

        $extraParameters = json_encode(array_merge($extraParameters, [
            'source_reference' => self::SOURCE_REFERENCE,
            'front_route' => 'cms.front.providers.chess',
        ]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $payload = [
            'name' => 'Chess Bet',
            'slug' => 'chess-bet',
            'reference_id' => $this->preferExisting($existing->reference_id ?? null, 'chess-bet'),
            'game_type_id' => $existing->game_type_id ?? null,
            'bonus_support' => (int) $this->preferExisting($existing->bonus_support ?? null, 0),
            'demo' => (int) $this->preferExisting($existing->demo ?? null, 0),
            'category_name' => $this->preferExisting($existing->category_name ?? null, 'Chess'),
            'type_name' => $this->preferExisting($existing->type_name ?? null, 'Skill'),
            'provider_rtp' => $this->preferExisting($existing->provider_rtp ?? null, '0.00'),
            'status' => $this->preferExisting($existing->status ?? null, 'active'),
            'extra_parameters' => $extraParameters,
            'provider_id' => $provider->id,
            'rank' => (int) $this->preferExisting($existing->rank ?? null, 10),
            'images' => $existing->images ?? null,
            'is_restricted' => 0,
            'is_featured' => (int) $this->preferExisting($existing->is_featured ?? null, 1),
            'updated_at' => now(),
        ];

        $payload['game_type']='table';
        $payload['description']='ChessBet hosted partner surface · canonical Rise wallet';
        $payload=array_intersect_key($payload,array_flip(Schema::getColumnListing('cms_games')));
        if ($existing) {
            if ($this->shouldSyncImage($existing->thumbnail_image ?? null)) {
                $payload['thumbnail_image'] = null;
            }


            if (!empty($existing->deleted_at)) {
                $payload['deleted_at'] = null;
            }

            DB::table('cms_games')->where('id', $existing->id)->update($payload);

            return;
        }

        $payload['thumbnail_image'] = null;
        $payload['detail_image'] = null;
        $payload['created_at'] = now();

        DB::table('cms_games')->insert($payload);
    }

    private function shouldSyncImage($current): bool
    {
        $value = trim((string) $current);

        return $value === '' || str_starts_with($value, 'internal-games/placeholders/');
    }


    private function preferExisting($current, $fallback)
    {
        if (is_string($current)) {
            return trim($current) !== '' ? $current : $fallback;
        }

        return $current !== null ? $current : $fallback;
    }

}
