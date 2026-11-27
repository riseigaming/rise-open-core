<?php
namespace App\Http\Controllers\Cms\Front;
use App\Http\Controllers\Controller;
use App\Services\InternalGames\SkyRushService;
use Illuminate\Http\Request;
class SkyRushController extends Controller {
 private function response(callable $work){return response()->json(['status'=>200,'data'=>$work()])->header('Cache-Control','private, no-store')->header('Referrer-Policy','no-referrer');}
 private function inputs():array{return ['sessionToken'=>'required|uuid']+array_fill_keys(['result','outcome','multiplier','crashMultiplier','cashoutMultiplier','cashoutAmount','balance','currency','wallet','wallet_account_id','isDemo','is_demo','playType','serverTime','crashedAt'],'prohibited');}
 public function snapshot(Request $r,SkyRushService $s){$d=$r->validate($this->inputs());return $this->response(fn()=>$s->snapshot($d['sessionToken'],auth('cms')->user()));}
 public function bet(Request $r,SkyRushService $s){$d=$r->validate($this->inputs()+['requestKey'=>'required|string|regex:/\A[A-Za-z0-9_-]{1,150}\z/','betAmount'=>'required|numeric|decimal:0,2|gt:0','lane'=>'nullable|integer|in:1,2','crashRoundId'=>'nullable|string|max:64']);return $this->response(fn()=>$s->placeBet($d['sessionToken'],auth('cms')->user(),(float)$d['betAmount'],$d['requestKey'],(int)($d['lane']??1),$d['crashRoundId']??null));}
 public function cashout(Request $r,SkyRushService $s){$d=$r->validate($this->inputs()+['requestKey'=>'required|string|regex:/\A[A-Za-z0-9_-]{1,150}\z/','betRoundId'=>'required|string|max:64']);return $this->response(fn()=>$s->cashout($d['sessionToken'],auth('cms')->user(),$d['betRoundId'],$d['requestKey']));}
}
