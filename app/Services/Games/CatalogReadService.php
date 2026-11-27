<?php
namespace App\Services\Games;

use App\Models\{CmsGame,CmsUser,GameCurationItem,GameCurationSurface};
use App\Services\ProductAccess\ProductAccessPolicyService;
use App\Services\Restrictions\CentralRestrictionPolicyService;
use Illuminate\Support\Collection;

class CatalogReadService
{
    public function __construct(private ProductAccessPolicyService $access, private CentralRestrictionPolicyService $countries) {}

    public function allowed(CmsGame $game, ?CmsUser $user): bool
    {
        if (!app(\App\Services\ExternalGames\ExternalGameDiscoveryReadService::class)->visible($game)) return false;
        if (!$game->catalogVisible() || !$this->access->catalogAllowed($user,$game->provider)) return false;
        // Account country is an additional restriction, not a claim about the visitor's IP.
        return !$user?->country_id || $this->countries->decideCountryRestriction((int)$user->country_id,'casino')->allowed;
    }

    public function items(string $surfaceKey, ?CmsUser $user, array $filters = []): Collection
    {
        $surface=GameCurationSurface::where('surface_key',$surfaceKey)->firstOrFail();
        if ($surface->status!=='active') return collect();
        $query=CmsGame::with('provider')->where('status','active')->where('is_restricted',false)
            ->whereHas('provider',fn($q)=>$q->where('status','active')->where('show_left_panel',true));
        if (($filters['q']??'')!=='') $query->where('name','like','%'.$filters['q'].'%');
        if (($filters['provider']??'')!=='') $query->where('provider_id',(int)$filters['provider']);
        if (($filters['type']??'')!=='') $query->where('game_type',$filters['type']);
        $ranked=GameCurationItem::where('surface_key',$surfaceKey)->orderBy('rank')->orderBy('id')->pluck('rank','game_id');
        if (!$surface->auto_fill_enabled) $query->whereIn('id',$ranked->keys());
        $games=$query->orderBy('rank')->orderBy('id')->get();
        if ($games->isEmpty()) return $games;
        $providerAllowed=$this->access->catalogFilter($user);
        $countryAllowed=null;
        return $games->filter(function ($game) use ($user,$providerAllowed,&$countryAllowed) {
            if (!app(\App\Services\ExternalGames\ExternalGameDiscoveryReadService::class)->visible($game)) return false;
            if (!$game->catalogVisible() || !$providerAllowed($game->provider)) return false;
            // The account country and product are constant for this evaluation.
            // Resolve lazily once, retaining the existing short-circuit behavior.
            return $countryAllowed ??= (!$user?->country_id || $this->countries->decideCountryRestriction((int)$user->country_id,'casino')->allowed);
        })
            ->sortBy(fn($g)=>sprintf('%010d-%010d-%010d',$ranked[$g->id]??1000000000,$g->rank,$g->id))->values();
    }
}
