<?php
namespace App\Http\Middleware;
use Closure;
class SportsbookActor {
 public function handle($request,Closure $next){
  $u=auth('cms')->user();abort_unless($u,401);
  if(in_array($request->query('mode'),['A','B'],true))return response()->json(['success'=>false,'error'=>['code'=>'SPORTSBOOK_READ_ONLY']],409);
  try { app(\App\Services\ProductAccess\ProductAccessPolicyService::class)->assertAllowed($u,'sportsbook'); } catch(\App\Betting\Exceptions\BettingDomainException $e) { return response()->json(['success'=>false,'error'=>['code'=>$e->errorCode(),'message'=>$e->getMessage()]],$e->httpStatus()); }
  $decision=app(\App\Services\Restrictions\CentralRestrictionPolicyService::class)->decideCountryRestriction($u->country_id,'sportsbook');
  abort_unless($decision->allowed,403,'Sportsbook country restriction.');
  $request->attributes->set('cms_api_actor',$u);$request->attributes->set('cms_api_auth_mode','session');
  $request->attributes->set('cms_api_actor_type','cms_user');$request->attributes->set('cms_api_actor_role',$u->role);
  return $next($request);
 }
}
