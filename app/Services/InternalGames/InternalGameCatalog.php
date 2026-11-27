<?php
namespace App\Services\InternalGames;
use App\Models\{CmsGame,CmsUser};
use App\Services\Games\CatalogReadService;
use App\Services\Restrictions\ProductCountryRestrictionGuard;
class InternalGameCatalog {
 public function definitionByCode(string $code): ?array { return config('internal_games.games.'.$code); }
 public function extractGameCode(CmsGame $game): ?string { return str_starts_with((string)$game->reference_id,'internal:')?substr($game->reference_id,9):null; }
 public function definitionForGame(CmsGame $game): ?array { return $this->definitionByCode($this->extractGameCode($game)??''); }
 public function supports(CmsGame $game): bool { $d=$this->definitionForGame($game);return $d && $game->slug===$d['slug'] && $game->provider?->slug==='internal-games' && $game->provider?->kind==='internal'; }
 public function isPlayable(CmsGame $game): bool { return config('internal_games.enabled') && $this->supports($game) && $game->catalogVisible() && !$game->is_maintenance && !$game->provider->is_maintenance; }
 public function allowsNewAdmission(CmsGame $game): bool { return $this->isPlayable($game) && !(bool) data_get($game->provider?->config, 'kill_switch', false); }
 public function currencyAllowed(CmsGame $game,string $currency): bool { return in_array(strtoupper($currency),(array)($this->definitionForGame($game)['supported_currencies']??['TRY','USD','EUR']),true); }
 public function assertAllowed(CmsGame $game,?CmsUser $user): void {
  $guard=app(ProductCountryRestrictionGuard::class);$d=$guard->decide(request(),'casino');if(!$d->allowed)throw new \Illuminate\Http\Exceptions\HttpResponseException($guard->blockedResponse(request(),$d));
  abort_unless(app(CatalogReadService::class)->allowed($game,$user),404);abort_unless($this->supports($game),404);abort_unless($this->isPlayable($game),503);
 }
}
