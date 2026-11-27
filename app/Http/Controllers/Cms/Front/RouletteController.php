<?php
namespace App\Http\Controllers\Cms\Front;
use App\Http\Controllers\Controller;
use App\Services\InternalGames\{RouletteService,InternalGameSessionService};
use App\Exceptions\InternalGames\RouletteException;
use Illuminate\Http\Request;
class RouletteController extends Controller {
 public function spin(Request $r,RouletteService $service){$d=$r->validate(['sessionToken'=>'required|uuid','requestKey'=>'required|string|min:8|max:100','bets'=>'required|array|max:64','wallet'=>'prohibited','balance'=>'prohibited','result'=>'prohibited']);try{return response()->json(['status'=>200,'data'=>$service->spin($d['sessionToken'],auth('cms')->user(),$d['bets'],$d['requestKey'])])->header('Cache-Control','private, no-store');}catch(RouletteException $e){return response()->json(['status'=>$e->statusCode(),'code'=>$e->errorCode(),'message'=>$e->getMessage()],$e->statusCode());}}
 public function snapshot(Request $r,RouletteService $service,InternalGameSessionService $sessions){$d=$r->validate(['sessionToken'=>'required|uuid']);$s=$sessions->resolvePlayableSession($d['sessionToken'],auth('cms')->user());abort_unless($service->supportsGameCode($s->game_code),404);return response()->json(['status'=>200,'data'=>$service->playPayload($s)])->header('Cache-Control','private, no-store');}
}
