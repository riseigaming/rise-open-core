<?php
namespace App\Http\Controllers\Cms\Front;
use App\Http\Controllers\Controller;
use App\Models\{CmsGame,ExternalGameSession};
use App\Services\ExternalGames\{ExternalGameLaunchService,ExternalGameDemoService,ExternalGameLocalContract,ExternalGameLaunchBlockedException,ExternalGameProviderLaunchException};
use App\Services\Games\CatalogReadService;
use App\Services\Restrictions\ProductCountryRestrictionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class ExternalGameController extends Controller
{
    public function launch(Request $r,CmsGame $game,CatalogReadService $catalog,ProductCountryRestrictionGuard $countries)
    {
        $d=$countries->decide($r,'casino');if(!$d->allowed)return $countries->blockedResponse($r,$d);
        abort_unless($catalog->allowed($game,auth('cms')->user()),404);
        $v=$r->validate(['playType'=>'required|in:demo,real','wallet'=>'prohibited','currency'=>'prohibited','balance'=>'prohibited']);
        try {
            if ($v['playType']==='demo') {
                $url=app(ExternalGameDemoService::class)->launch($game,$r,auth('cms')->user());
                abort_unless(ExternalGameLocalContract::frameAllowed($url),409,'Provider frame origin is not configured.');
                $token=(string)Str::uuid();$r->session()->put('external_fun.'.$token,['url'=>$url,'game_id'=>$game->id]);
            } else {
                abort_unless(auth('cms')->check(),403);
                $s=app(ExternalGameLaunchService::class)->launch($game,auth('cms')->user(),false,$r);
                $token=$s->session_token;
            }
            return redirect()->route('cms.externalGames.play',$token)->header('Cache-Control','private, no-store')->header('Referrer-Policy','no-referrer');
        } catch(ExternalGameLaunchBlockedException $e) {
            return $this->rejection($r,$game,$e->reasonCode,$e->getMessage(),in_array($e->reasonCode,['provider_unavailable','upstream_provider_maintenance'],true)?503:409);
        } catch(\App\Services\Wallet\Exceptions\UnsupportedProductWalletException $e) {
            return $this->rejection($r,$game,'unsupported_wallet','This wallet or currency is not supported by the provider.',409);
        } catch(\Throwable $e) {
            report($e);
            return $this->rejection($r,$game,'provider_launch_failed','Provider launch could not be confirmed. Retry from the game detail.',502);
        }
    }
    private function rejection(Request $r,CmsGame $game,string $state,string $message,int $status)
    {
        return $r->expectsJson() ? response()->json(['error'=>$state,'message'=>$message],$status)
            : response()->view('cms.global.games.launch-unavailable',compact('game','state','message'),$status)->header('Cache-Control','private, no-store');
    }
    public function play(Request $r,string $token,CatalogReadService $catalog,ProductCountryRestrictionGuard $countries)
    {
        $d=$countries->decide($r,'casino');if(!$d->allowed)return $countries->blockedResponse($r,$d);
        $demo=$r->session()->get('external_fun.'.$token);
        if($demo){$game=CmsGame::with('provider')->findOrFail($demo['game_id']);$url=$demo['url'];$mode='demo';$session=null;}
        else {
            abort_unless(auth('cms')->check(),403);
            $session=ExternalGameSession::with('game.provider')->where('session_token',$token)->where('cms_user_id',auth('cms')->id())->firstOrFail();
            abort_unless($session->status==='launched',409);abort_if($session->expires_at?->isPast(),410);
            $game=$session->game;$url=$session->provider_launch_url;$mode='real';
        }
        abort_unless($catalog->allowed($game,auth('cms')->user()),404);
        if ($mode==='demo') {
            abort_unless(app(ExternalGameDemoService::class)->available($game),409);
            app(\App\Services\ProductAccess\ProductAccessPolicyService::class)->assertExternalAllowed(auth('cms')->user(),$game->provider,true);
        } else {
            try { app(\App\Services\ExternalGames\ExternalGameAvailabilityService::class)->assertLaunchable($game,auth('cms')->user(),false); }
            catch(ExternalGameLaunchBlockedException $e) { return $this->rejection($r,$game,$e->reasonCode,$e->getMessage(),in_array($e->reasonCode,['provider_unavailable','upstream_provider_maintenance'],true)?503:409); }
        }
        abort_unless(ExternalGameLocalContract::frameAllowed($url, $session?->provider_alias ?? \App\Services\ExternalGames\ExternalGameProviderConfig::PRIMARY),409);
        return response()->view('cms.global.games.external',compact('game','url','mode','session'))
            ->header('Cache-Control','private, no-store')->header('Referrer-Policy','no-referrer');
    }
}
