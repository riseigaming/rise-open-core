<?php
namespace App\Services\InternalGames;
use App\Models\{CmsGame,CmsUser,InternalGameSession};
use App\Services\Wallet\{SpendableWalletService,SpendableWalletContext,WalletPlayabilityService};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
/** Existing CMS launch/resolve/wallet pinning, adapted to the same Rise catalog and owner guards. */
class InternalGameSessionService {
 public function __construct(private InternalGameCatalog $catalog,private InternalGameDemoModeService $demos,private InternalGameAuditService $audit){}
 public function launch(CmsGame $game,?CmsUser $user,bool $demoRequested,Request $request): InternalGameSession {
  abort_unless($request->hasSession(),403);$this->catalog->assertAllowed($game,$user);
  abort_unless($this->catalog->allowsNewAdmission($game),503);
  abort_unless($user && $user->role==='customer',403);
  if($demoRequested)abort_unless($this->demos->isInternalDemoSupported($game),503);
  app(\App\Services\Finance\PrelaunchFinanceWriteGate::class)->assertWritable();
  return DB::transaction(function()use($game,$user,$request){
   $active=app(WalletPlayabilityService::class)->assertForUser('internal_games',$user,['game'=>$game,'game_code'=>$this->catalog->extractGameCode($game)],true);
   $wallet=app(SpendableWalletService::class)->resolveForWallet($user,$active,true);
   abort_unless($wallet instanceof SpendableWalletContext && $this->catalog->currencyAllowed($game,$wallet->currency()),403);
   if(data_get($this->catalog->definitionForGame($game),'engine')==='blackjack-v1'){
    $resumed=$this->resumeBlackjack($game,$user,$wallet,$request);if($resumed)return $resumed;
   }
   $session=InternalGameSession::create(['public_id'=>'igs_'.Str::upper(Str::random(14)),'session_token'=>(string)Str::uuid(),'provider_id'=>$game->provider_id,'cms_game_id'=>$game->id,'cms_user_id'=>$wallet->ownerWalletId(),'root_user_id'=>$wallet->rootWalletId(),'game_code'=>$this->catalog->extractGameCode($game),'currency'=>$wallet->currency(),'status'=>'active','is_demo'=>false,'launch_payload'=>[
    'finance_mode'=>'real_wallet','requested_user_id'=>$user->id,'dice_browser_hash'=>hash('sha256',$request->session()->getId()),
   ]+$wallet->auditPayload(),'launched_at'=>now(),'last_activity_at'=>now(),'expires_at'=>now()->addMinutes((int)config('internal_games.session_ttl_minutes',30))]);
   $this->audit->logSession($session,'internal_game.launch.created',['game_code'=>$session->game_code,'is_demo'=>false,'finance_mode'=>'real_wallet']);return $session;
  });
 }
 private function resumeBlackjack(CmsGame $game,CmsUser $user,SpendableWalletContext $wallet,Request $request): ?InternalGameSession {
  $existing=InternalGameSession::where('cms_game_id',$game->id)->where('is_demo',false)->where('status','active')
   ->where('root_user_id',$wallet->rootWalletId())->where('cms_user_id',$wallet->ownerWalletId())->where('currency',$wallet->currency())
   ->where('launch_payload->wallet_account_id',$wallet->walletAccountId())->where('launch_payload->requested_user_id',$user->id)
   ->where(fn($q)=>$q->where('expires_at','>',now())->orWhereHas('rounds',fn($r)=>$r->where('is_demo',false)->where('status','awaiting_player_action')))
   ->oldest('id')->lockForUpdate()->get()->first(fn($s)=>data_get($s->launch_payload,'wallet_type')===$wallet->walletType()
    && data_get($s->launch_payload,'wallet_currency')===$wallet->currency() && (int)data_get($s->launch_payload,'wallet_context_user_id')===$wallet->contextWalletId());
  if(!$existing)return null;
  $hash=hash('sha256',$request->session()->getId());$expired=!$existing->expires_at?->isFuture();$rebound=!hash_equals((string)data_get($existing->launch_payload,'dice_browser_hash',''),$hash);
  if($expired||$rebound){$changes=['session_token'=>(string)Str::uuid(),'last_activity_at'=>now(),'launch_payload'=>array_merge($existing->launch_payload,['dice_browser_hash'=>$hash])];if($expired)$changes['expires_at']=now()->addMinutes(max(1,(int)config('internal_games.session_ttl_minutes',30)));$existing->forceFill($changes)->save();$this->audit->logSession($existing,'internal_game.launch.resumed',['reason'=>$expired?'expired_session_with_open_blackjack_hand':'authorized_browser_rebind','wallet_account_id'=>$wallet->walletAccountId()]);}
  return $existing;
 }
 public function resolvePlayableSession(string $token,?CmsUser $user): InternalGameSession { $s=InternalGameSession::with('game.provider')->where('session_token',$token)->firstOrFail();$this->assertPlayableSession($s,$user);return $s; }
 public function assertPlayableSession(InternalGameSession $s,?CmsUser $user): void {
  // Historical virtual sessions are retained as data and cannot resume play.
  abort_if($s->is_demo,403);
  abort_unless($s->status==='active'&&$s->expires_at?->isFuture(),410);
  abort_unless(request()->hasSession()&&hash_equals((string)data_get($s->launch_payload,'dice_browser_hash',''),hash('sha256',request()->session()->getId())),403);
  abort_unless((int)data_get($s->launch_payload,'requested_user_id',0)===(int)($user?->id??0),403);
  $game=$s->game()->with('provider')->firstOrFail();$this->catalog->assertAllowed($game,$user);
  abort_unless($user&&$user->role==='customer'&&data_get($s->launch_payload,'finance_mode')==='real_wallet'&&$s->cms_user_id&&$s->root_user_id&&$this->catalog->currencyAllowed($game,$s->currency),403);
  $wallet=$this->resolveWalletContextForSession($s);
  abort_unless($wallet&&(int)$wallet->ownerWalletId()===(int)$s->cms_user_id&&(int)$wallet->rootWalletId()===(int)$s->root_user_id,403);
 }
 public function resolveWalletContextForSession(InternalGameSession $s,bool $lockForUpdate=false): ?SpendableWalletContext {
  if($s->is_demo)return null;$id=(int)data_get($s->launch_payload,'wallet_context_user_id',$s->cms_user_id);$user=CmsUser::find($id);if(!$user)return null;
  return app(SpendableWalletService::class)->resolveForStoredContext($user,(int)data_get($s->launch_payload,'wallet_account_id'),(string)data_get($s->launch_payload,'wallet_type','primary_balance'),$s->currency,$id,$lockForUpdate);
 }
 public function assertNewGameplayAllowed(InternalGameSession $s): void
 {
  abort_unless($this->catalog->allowsNewAdmission($s->game),503);
  $wallet = $this->resolveWalletContextForSession($s, true);
  abort_unless($wallet, 403);
  app(WalletPlayabilityService::class)->assertForWallet('internal_games', $wallet->contextUser(), $wallet->walletAccount(),
      ['game' => $s->game, 'game_code' => $s->game_code]);
 }
 public function assertProductAccessAllowed(InternalGameSession $s,?CmsUser $u): void {$this->catalog->assertAllowed($s->game,$u);}
 public function assertCasinoCountryAllowedForNewGameplay(): void { $g=app(\App\Services\Restrictions\ProductCountryRestrictionGuard::class);$d=$g->decide(request(),'casino');if(!$d->allowed)throw new \Illuminate\Http\Exceptions\HttpResponseException($g->blockedResponse(request(),$d)); }
}
