<?php
namespace App\Http\Controllers\Cms\Front;
use App\Http\Controllers\Controller;
use App\Models\CmsGame;
use App\Services\Games\CatalogReadService;
use App\Services\Restrictions\ProductCountryRestrictionGuard;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

class GameController extends Controller
{
    public function index(Request $r,CatalogReadService $catalog,ProductCountryRestrictionGuard $countries)
    {
        $decision=$countries->decide($r,'casino'); if (!$decision->allowed) return $countries->blockedResponse($r,$decision);
        $filters=$this->filters($r);
        $all=$catalog->items('casino_all_games',auth('cms')->user());
        $providers=$all->pluck('provider')->unique('id')->sortBy('name');
        $items=$catalog->items('casino_all_games',auth('cms')->user(),$filters);$page=(int)($filters['page']??1);
        $games=new LengthAwarePaginator($items->forPage($page,24)->values(),$items->count(),24,$page,['path'=>$r->url(),'query'=>$r->query()]);
        return response()->view('cms.global.games.index',compact('games','providers','filters'))->header('Cache-Control','private, no-store');
    }
    public function detail(Request $r,string $slug,CatalogReadService $catalog,ProductCountryRestrictionGuard $countries)
    {
        $decision=$countries->decide($r,'casino');if (!$decision->allowed) return $countries->blockedResponse($r,$decision);
        $game=CmsGame::with('provider')->where('slug',$slug)->firstOrFail();abort_unless($catalog->allowed($game,auth('cms')->user()),404);
        $filters=$this->filters($r);
        return response()->view('cms.global.games.detail',compact('game','filters'))->header('Cache-Control','private, no-store');
    }
    private function filters(Request $request): array
    {
        // Only reconstruct local catalog queries; never trust a return URL or Referer.
        return $request->validate(['q'=>'nullable|string|max:100', 'provider'=>'nullable|integer|exists:providers,id',
            'type'=>['nullable',Rule::in(array_keys(CmsGame::TYPES))], 'page'=>'nullable|integer|min:1|max:100000']);
    }
    public function launch(Request $r,string $slug,CatalogReadService $catalog,ProductCountryRestrictionGuard $countries)
    {
        $decision=$countries->decide($r,'casino');if (!$decision->allowed) return $countries->blockedResponse($r,$decision);
        $game=CmsGame::with('provider')->where('slug',$slug)->firstOrFail();abort_unless($catalog->allowed($game,auth('cms')->user()),404);
        $state=$game->launchState();
        if ($state==='chess') return redirect()->route('cms.front.providers.chess');
        if ($state!=='maintenance' && app(\App\Services\ExternalGames\ExternalGameAvailabilityService::class)->supports($game)) return app(ExternalGameController::class)->launch($r,$game,$catalog,$countries);
        if (in_array($state,['demo','available'],true)) {
            $r->validate(['playType'=>'required|in:demo,real','wallet'=>'prohibited','currency'=>'prohibited','balance'=>'prohibited','is_demo'=>'prohibited']);
            $user = auth('cms')->user();
            $demoRequested = $r->input('playType') === 'demo';
            if ($demoRequested && $user === null) {
                return app(DiceController::class)->preview($game);
            }
            $session = app(\App\Services\InternalGames\InternalGameSessionService::class)->launch($game, $user, $demoRequested, $r);
            return redirect()->route('cms.internalGames.play',$session->session_token)->header('Cache-Control','private, no-store')->header('Referrer-Policy','no-referrer');
        }
        $message = __($state === 'maintenance' ? 'catalog.launch_maintenance' : 'catalog.launch_unavailable');
        // Deliberately no session, wallet, provider URL, redirect or request is created.
        return response()->view('cms.global.games.launch-unavailable',compact('game','state','message'),$state==='maintenance'?503:409)->header('Cache-Control','private, no-store');
    }
}
