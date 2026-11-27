<?php
namespace App\Http\Controllers\Cms\Front;
use App\Http\Controllers\Controller;
use App\Services\InternalGames\InternalGamePlayService;
use Illuminate\Http\Request;
class SlotController extends Controller {
 private function response(callable $work){return response()->json(['status'=>200,'data'=>$work()])->header('Cache-Control','private, no-store')->header('Referrer-Policy','no-referrer');}
 public function spin(Request $r,InternalGamePlayService $s){$d=$r->validate(['sessionToken'=>'required|uuid','requestKey'=>'required|string|regex:/\A[A-Za-z0-9_-]{1,150}\z/','betAmount'=>'required|numeric|decimal:0,2|gt:0']+array_fill_keys(['result','outcome','winAmount','payout','balance','currency','wallet','wallet_account_id','isDemo','is_demo','playType','grid','multiplier'],'prohibited'));return $this->response(fn()=>$s->spin($d['sessionToken'],auth('cms')->user(),(float)$d['betAmount'],$d['requestKey']));}
 public function snapshot(Request $r,InternalGamePlayService $s){$d=$r->validate(['sessionToken'=>'required|uuid']);return $this->response(fn()=>$s->snapshot($d['sessionToken'],auth('cms')->user()));}
}
