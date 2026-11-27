<?php
namespace App\Http\Controllers\Cms\Front;
use App\Http\Controllers\Controller;
use App\Services\InternalGames\{BlackjackService,InternalGameSessionService};
use App\Exceptions\InternalGames\BlackjackException;
use Illuminate\Http\Request;
/** Existing CMS start/action envelope; Rise browser/session and strict input boundaries. */
class BlackjackController extends Controller {
 private function response(callable $work){try{return response()->json(['status'=>200,'data'=>$work()])->header('Cache-Control','private, no-store')->header('Referrer-Policy','no-referrer');}catch(BlackjackException $e){return response()->json(['status'=>$e->statusCode(),'code'=>$e->errorCode(),'message'=>$e->getMessage()],$e->statusCode())->header('Cache-Control','private, no-store');}}
 private function prohibited():array{return array_fill_keys(['result','outcome','payoutTotal','balance','currency','wallet','wallet_account_id','isDemo','is_demo','playType','playerHand','dealerHand','remainingDeck','cards'],'prohibited');}
 public function start(Request $r,BlackjackService $service){$d=$r->validate(['sessionToken'=>'required|uuid','requestKey'=>'required|string|regex:/\A[A-Za-z0-9_-]{1,150}\z/','betAmount'=>'required|numeric|decimal:0,2|gt:0']+$this->prohibited());return $this->response(fn()=>$service->startRound($d['sessionToken'],auth('cms')->user(),(float)$d['betAmount'],$d['requestKey']));}
 public function action(Request $r,BlackjackService $service){$d=$r->validate(['sessionToken'=>'required|uuid','requestKey'=>'required|string|regex:/\A[A-Za-z0-9_-]{1,150}\z/','roundId'=>'required|string|max:64','action'=>'required|in:hit,stand,double','roundVersion'=>'required|integer|min:1']+$this->prohibited());return $this->response(fn()=>$service->action($d['sessionToken'],auth('cms')->user(),$d['roundId'],$d['action'],$d['requestKey'],(int)$d['roundVersion']));}
 public function snapshot(Request $r,BlackjackService $service,InternalGameSessionService $sessions){$d=$r->validate(['sessionToken'=>'required|uuid']);$s=$sessions->resolvePlayableSession($d['sessionToken'],auth('cms')->user());abort_unless($service->supportsGameCode($s->game_code),404);return $this->response(fn()=>$service->playPayload($s));}
}
