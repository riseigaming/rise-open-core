<?php
namespace App\Services\ExternalGames;
use App\Models\CmsGame;
use Illuminate\Support\Str;

/** Source discovery metadata/rollout contract, composed into the existing Rise catalog. */
final class ExternalGameDiscoveryReadService
{
    public function __construct(private ExternalGameAvailabilityService $availability,private ExternalGameProviderRolloutService $rollout) {}
    public function visible(CmsGame $game):bool {
        if (!$this->availability->supports($game)) return true;
        return (bool)$this->rollout->decisionForGame($game)['visible_state'];
    }
    public function realCapability(CmsGame $game, ?\App\Models\CmsUser $user):array {
        $controls=app(ExternalGamesControlSettingsService::class)->settings();
        $alias=$this->availability->providerAlias($game);$config=(array)config('external_games.providers.'.$alias,[]);
        if (!ExternalGameProviderConfig::enabled($alias)||empty($config['api_base_url'])||empty($config['agent_token'])||empty($config['agent_secret'])) return ['available'=>false,'reason'=>'Provider connection is not configured.'];
        if (\App\Support\LocalEgress::blocks('block_provider_apis')&&!ExternalGameLocalContract::allows((string)$config['api_base_url'], $alias)) return ['available'=>false,'reason'=>'Provider calls are disabled in this local profile.'];
        if (!$user) return ['available'=>false,'reason'=>'Sign in for real-wallet play.'];
        try {$this->availability->assertLaunchable($game,$user,false);}catch(ExternalGameLaunchBlockedException $e){return ['available'=>false,'reason'=>$e->getMessage()];}
        return ['available'=>true,'reason'=>null];
    }
    public function metadata(CmsGame $game):array {
        $extra=is_array($game->extra_parameters)?$game->extra_parameters:(json_decode((string)$game->extra_parameters,true)?:[]);
        $external=(array)($extra['external_games']??[]);$raw=(array)($external['raw']??[]);
        return ['id'=>$game->id,'catalog_source'=>'external_games','aggregator_alias'=>config('external_games.providers.'.$this->availability->providerAlias($game).'.public_alias'),
            'provider_game_id'=>$external['provider_game_id']??$game->reference_id,
            'provider'=>['key'=>$external['vendor_id']??'','name'=>$external['vendor_name']??$game->type_name],
            'game_type'=>$this->normalizeGameType($external['category']??$raw['game_type']??$game->category_name),
            'rollout'=>$this->rollout->decisionForGame($game),
            'fun_mode'=>['demo_available'=>$this->availability->providerAlias($game) === ExternalGameProviderConfig::PRIMARY && SpinStructDemoClient::flag($raw['fun_mode']??null)===1,'financial_contribution'=>'zero']];
    }
    public function normalizeGameType(?string $value):array {
        $raw=trim((string)$value);$key=(string)Str::of($raw)->lower()->replace([' ','-'],'_');$compact=str_replace('_','',$key);
        $normalized=match($compact){'slot','slots'=>'slots','blackjack'=>'blackjack','roulette'=>'roulette','baccarat'=>'baccarat',
            'tablegame','tablegames'=>'table_games','instantgame','instantgames','crashgame','crashgames'=>'instant_or_crash',default=>'other'};
        return ['normalized'=>$normalized,'raw'=>$raw];
    }
}
