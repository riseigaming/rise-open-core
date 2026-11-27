<?php
namespace Tests\Feature\Chess;
use Tests\TestCase;
use Tests\Support\BlackjackFixture;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\{DB,Http};
use App\Models\{ChessWalletReservation,WalletLedgerEntry,CmsUserTransaction,ChessLiveBoard,ChessBettingMarket};
use App\Services\ChessWallet\ChessWalletReservationService;
use Brick\Math\BigDecimal;

final class ChessCanonicalFinanceTest extends TestCase {
 use DatabaseTransactions,BlackjackFixture;
 private array $partner;
 protected function setUp():void {
  parent::setUp();$this->setupBlackjack();(new \Database\Seeders\HostedChessCatalogSeeder)->run();
  config(['chess_integration.wallet_enabled'=>true,'chess_integration.front_enabled'=>true,'chess_wallet.partner_overrides'=>['local-owned'=>['secret'=>'test-only-local-chess-secret','allow_settlement_handoff'=>true]]]);
  $this->partner=app(\App\Services\ChessWallet\ChessWalletProviderResolver::class)->resolvePartnerContext('local-owned');
 }
 private function payload(string $intent='intent-1',string $amount='10.01'):array{return ['externalUserId'=>(string)$this->player->id,'placementIntentId'=>$intent,'amount'=>$amount,'currency'=>'TRY','marketInstanceId'=>'hosted-contract-market','selectionId'=>'source-selection','quoteExpiresAt'=>now()->addMinutes(2)->toAtomString(),'walletAccountId'=>$this->wallet->id];}
 private function reserve(array $payload=[],string $key='key-1'):array {return app(ChessWalletReservationService::class)->reserve($payload?:$this->payload(),$this->partner,'request-1',$key);}
 private function handoff(string $id,string $result,string $amount='0.00'):array{return ['cmsReservationId'=>$id,'placedBetId'=>'placed-'.$id,'resultStatus'=>$result,'payoutAmount'=>$amount,'settlementSummary'=>['source'=>'controlled_contract_vector'],'settledAt'=>now()->toAtomString()];}
 private function assertBalance(string $amount):void{$this->assertTrue(BigDecimal::of($this->wallet->fresh()->available_balance)->isEqualTo($amount));}
 public function test_reserve_confirm_lookup_and_release_exactly_once():void {
  $p=$this->payload();$r=$this->reserve($p);$this->assertSame(200,$r['httpStatus'],json_encode($r));$this->assertSame('489.99',$r['body']['balanceExact']);$id=$r['body']['cmsReservationId'];
  $hash=$this->financeHash();$this->assertSame($r,$this->reserve($p));$this->assertSame($hash,$this->financeHash());
  $svc=app(ChessWalletReservationService::class);$confirm=['partnerCode'=>'local-owned','externalUserId'=>$p['externalUserId'],'idempotencyKey'=>'key-1','requestTimestamp'=>now()->toAtomString(),'placedBetId'=>'placed-1'];
  $this->assertSame('placed-1',$svc->confirmReservation($confirm,$this->partner,'confirm-1')['body']['placedBetId']);
  $this->assertSame(409,$svc->confirmReservation(array_merge($confirm,['placedBetId'=>'other']),$this->partner,'confirm-2')['httpStatus']);
  $this->assertSame('HELD',$svc->reservationLookup($id,$this->partner)['body']['holdState']);
  $release=['cmsReservationId'=>$id,'reason'=>'provider_cancelled'];$this->assertSame(200,$svc->release($release,$this->partner,'release-1')['httpStatus']);$this->assertBalance('500.00');
  $hash=$this->financeHash();$this->assertSame(200,$svc->release($release,$this->partner,'release-2')['httpStatus']);$this->assertSame($hash,$this->financeHash());
  $this->assertSame(409,$svc->release(array_merge($release,['reason'=>'changed']),$this->partner,'release-3')['httpStatus']);
  $this->assertSame(2,WalletLedgerEntry::where('source_type','chess_reservation')->count());$this->reconcileBlackjack();
 }
 public function test_win_loss_void_release_then_void_settlement_and_terminal_conflicts():void {
  $svc=app(ChessWalletReservationService::class);
  foreach(['WIN','LOSS','VOID','RELEASE_VOID']as $i=>$kind){$r=$this->reserve($this->payload('intent-'.$kind,'10.00'),'key-'.$kind);$this->assertSame(200,$r['httpStatus'],json_encode($r));$id=$r['body']['cmsReservationId'];
   if($kind==='RELEASE_VOID')$this->assertSame(200,$svc->release(['cmsReservationId'=>$id],$this->partner,'rel')['httpStatus']);
   $p=$this->handoff($id,$kind==='RELEASE_VOID'?'VOID':$kind,$kind==='WIN'?'22.53':'0.00');$s=$svc->handoffSettlement($p,'settle',$this->partner);$this->assertSame(200,$s['httpStatus'],json_encode($s));$hash=$this->financeHash();
   $this->assertSame(200,$svc->handoffSettlement($p,'retry',$this->partner)['httpStatus']);$this->assertSame($hash,$this->financeHash());
   $this->assertSame(409,$svc->handoffSettlement(array_merge($p,['payoutAmount'=>'99.99']),'bad',$this->partner)['httpStatus']);$this->assertSame($hash,$this->financeHash());
  }
  $this->assertBalance('502.53');$this->reconcileBlackjack();
 }
 public function test_different_payload_user_wallet_partner_and_unauthorized_handoff_reject():void {
  $p=$this->payload();$r=$this->reserve($p);$id=$r['body']['cmsReservationId'];$hash=$this->financeHash();
  $this->assertSame(409,$this->reserve(array_merge($p,['amount'=>'11.00']))['httpStatus']);
  $svc=app(ChessWalletReservationService::class);$this->assertSame(404,$svc->release(['cmsReservationId'=>$id],['wallet_config'=>['partner_code'=>'other']],'x')['httpStatus']);
  $this->assertSame(401,$svc->release(['cmsReservationId'=>$id],[],'x')['httpStatus']);
  foreach(['externalUserId'=>'other','walletAccountId'=>9999,'currency'=>'USD']as $k=>$v)$this->assertSame(409,$svc->release(['cmsReservationId'=>$id,$k=>$v],$this->partner,'x')['httpStatus']);
  $denied=$this->partner;$denied['wallet_config']['allow_settlement_handoff']=false;$this->assertSame(403,$svc->handoffSettlement($this->handoff($id,'WIN','100.00'),'x',$denied)['httpStatus']);$this->assertSame($hash,$this->financeHash());
 }
 public function test_failed_ledger_insert_rolls_back_debit_transaction_and_reservation():void {
  $hash=$this->financeHash();$count=ChessWalletReservation::count();
  WalletLedgerEntry::creating(function($entry){if($entry->source_type==='chess_reservation')throw new \RuntimeException('controlled ledger insertion failure');});
  try{$r=$this->reserve();$this->assertSame(500,$r['httpStatus']);$this->assertSame($hash,$this->financeHash());$this->assertSame($count,ChessWalletReservation::count());}finally{WalletLedgerEntry::flushEventListeners();}
 }
 public function test_failed_payout_ledger_insert_rolls_back_terminal_and_credit():void {
  $r=$this->reserve();$id=$r['body']['cmsReservationId'];$hash=$this->financeHash();WalletLedgerEntry::creating(function($e){if($e->entry_type==='chess_settlement_win')throw new \RuntimeException('controlled payout ledger insert failure');});
  try{$this->assertSame(500,app(ChessWalletReservationService::class)->handoffSettlement($this->handoff($id,'WIN','20.02'),'x',$this->partner)['httpStatus']);$this->assertSame('reserved',ChessWalletReservation::where('cms_reservation_id',$id)->value('status'));$this->assertSame($hash,$this->financeHash());}finally{WalletLedgerEntry::flushEventListeners();}
 }
 public function test_large_balance_cent_precision_and_invalid_fraction():void {
  $this->credit('9999999999999.97','large-chess');$p=$this->payload();$r=$this->reserve($p);$this->assertSame(200,$r['httpStatus'],json_encode($r));$this->assertSame('10000000000489.96',$r['body']['balanceExact']);
  $tx=CmsUserTransaction::where('event_type','chess_reserve')->firstOrFail();$this->assertTrue(BigDecimal::of((string)$tx->exact_after_balance)->isEqualTo('10000000000489.96'));$this->reconcileBlackjack();
  $hash=$this->financeHash();$this->assertSame(422,$this->reserve($this->payload('bad-precision','1.001'),'bad')['httpStatus']);$this->assertSame($hash,$this->financeHash());
 }
 public function test_risk_exposure_stale_quote_insufficient_and_provider_maintenance():void {
  $this->partner['wallet_config']['limits']=['min'=>1,'max'=>100];
  foreach(['min','max','stale','insufficient','maintenance']as $case){$p=$this->payload('reject-'.$case,$case==='min'?'0.50':($case==='max'?'101.00':($case==='insufficient'?'999.00':'10.00')));if($case==='stale')$p['quoteExpiresAt']=now()->subSecond()->toAtomString();if($case==='insufficient')$this->partner['wallet_config']['limits']['max']=1000;if($case==='maintenance')$this->partner['provider']->forceFill(['is_maintenance'=>true])->save();$hash=$this->financeHash();$r=$this->reserve($p,'key-'.$case);$this->assertSame(409,$r['httpStatus'],json_encode($r));$this->assertSame($hash,$this->financeHash());}
  $this->partner['provider']->forceFill(['is_maintenance'=>false])->save();$p=$this->payload('exposure-1','10.00');$p['marketExposureLimit']='15.00';$this->assertSame(200,$this->reserve($p,'e1')['httpStatus']);$hash=$this->financeHash();$p['placementIntentId']='exposure-2';$this->assertSame(409,$this->reserve($p,'e2')['httpStatus']);$this->assertSame($hash,$this->financeHash());
 }
 public function test_recurring_source_status_shadow_cutoff_and_selection_rules():void {
  app(\App\Services\ChessLiveCockpit\ChessRecurringSurfaceOverlayService::class)->ensure();$board=ChessLiveBoard::where('external_board_key','cms-recurring-3-0-thursday')->firstOrFail();$market=$board->bettingMarkets()->with('selections')->firstOrFail();$p=$this->payload();$p['marketInstanceId']=$board->external_board_key;$p['selectionId']=$market->selections->first()->selection_code;
  $base=$p;$this->assertSame(200,$this->reserve($p)['httpStatus']);
  foreach(['closed','shadow','cutoff','selection']as $case){$p=$base;$p['placementIntentId']='reject-'.$case;$market->update(['status'=>$case==='closed'?'closed':'open','meta'=>$case==='shadow'?['shadow_mode'=>true]:[]]);
   if($case==='cutoff'){\Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-10-29 20:31:00','Europe/Istanbul'));$p['quoteExpiresAt']=now()->addMinutes(2)->toAtomString();}if($case==='selection')$p['selectionId']='home';$hash=$this->financeHash();$rejection=$this->reserve($p,$case);$this->assertSame(409,$rejection['httpStatus']);if($case==='cutoff')$this->assertStringContainsString('not open',$rejection['body']['message']);$this->assertSame($hash,$this->financeHash());\Carbon\Carbon::setTestNow();}
 }
 public function test_signed_http_timestamp_signature_and_guest_denials():void {
  $this->postJson('/wallet/reservations/reserve',$this->payload())->assertStatus(401);
  $path='/wallet/reservations/reserve';$body=json_encode($this->payload());$ts=(string)time();$request=\Illuminate\Http\Request::create($path,'POST',[],[],[],[], $body);$signed=app(\App\Services\ChessWallet\ChessWalletSignature::class);$canonical=$signed->buildCanonicalPayload($request,'local-owned','req',$ts,'http-key');$sig=$signed->sign($canonical,'test-only-local-chess-secret');
  $headers=['CONTENT_TYPE'=>'application/json','HTTP_X_PARTNER_CODE'=>'local-owned','HTTP_X_REQUEST_ID'=>'req','HTTP_X_TIMESTAMP'=>$ts,'HTTP_X_SIGNATURE'=>$sig,'HTTP_IDEMPOTENCY_KEY'=>'http-key'];
  $this->call('POST',$path,[],[],[],$headers,$body)->assertOk();$hash=$this->financeHash();$headers['HTTP_X_SIGNATURE']='invalid';$this->call('POST',$path,[],[],[],$headers,$body)->assertStatus(401);$this->assertSame($hash,$this->financeHash());
  $headers['HTTP_X_TIMESTAMP']=(string)(time()-301);$headers['HTTP_X_SIGNATURE']=$signed->sign($signed->buildCanonicalPayload($request,'local-owned','req',$headers['HTTP_X_TIMESTAMP'],'http-key'),'test-only-local-chess-secret');$this->call('POST',$path,[],[],[],$headers,$body)->assertStatus(401);
 }
 public function test_release_and_void_ledger_failures_are_fully_atomic():void {
  foreach(['release','VOID']as $case){$r=$this->reserve($this->payload('rollback-'.$case),'rollback-'.$case);$this->assertSame(200,$r['httpStatus']);$id=$r['body']['cmsReservationId'];$hash=$this->financeHash();WalletLedgerEntry::creating(function($entry){if($entry->source_type==='chess_reservation')throw new \RuntimeException('controlled refund ledger failure');});
   try{$svc=app(ChessWalletReservationService::class);$failure=$case==='release'?$svc->release(['cmsReservationId'=>$id],$this->partner,'r'):$svc->handoffSettlement($this->handoff($id,'VOID'),'s',$this->partner);$this->assertSame(500,$failure['httpStatus']);$this->assertSame($hash,$this->financeHash());$this->assertSame('reserved',ChessWalletReservation::where('cms_reservation_id',$id)->value('status'));}finally{WalletLedgerEntry::flushEventListeners();}
  }
 }
 public function test_recurring_alias_identity_and_exposure_share_the_source_scope():void {
  app(\App\Services\ChessLiveCockpit\ChessRecurringSurfaceOverlayService::class)->ensure();$b=ChessLiveBoard::where('external_board_key','cms-recurring-3-0-thursday')->firstOrFail();$m=$b->bettingMarkets()->firstOrFail();$selection=$m->selections()->value('selection_code');$p=$this->payload('scope-direct','10.00');$p['marketInstanceId']=$b->external_board_key;$p['selectionId']=$selection;$p['marketExposureLimit']='15.00';$this->assertSame(200,$this->reserve($p,'direct')['httpStatus']);
  $p['placementIntentId']='scope-alias';$p['marketInstanceId']='0e56856e-afe0-47ee-be69-f2e517c7ae84';$hash=$this->financeHash();$this->assertSame(409,$this->reserve($p,'alias')['httpStatus']);$this->assertSame(1,DB::table('chess_market_exposure_scopes')->count());$this->assertSame($hash,$this->financeHash());$m->update(['status'=>'closed']);$p['placementIntentId']='closed-alias';$p['marketExposureLimit']='100.00';$response=$this->reserve($p,'closed-alias');$this->assertSame(409,$response['httpStatus']);$this->assertStringContainsString('closed',$response['body']['message']);
 }
 public function test_pinned_wallet_recovery_during_product_maintenance():void {
  $p=$this->payload();$r=$this->reserve($p);$this->assertSame(200,$r['httpStatus']);$id=$r['body']['cmsReservationId'];$this->partner['provider']->update(['is_maintenance'=>true]);
  $this->assertSame(409,$this->reserve($this->payload('maintenance'),'m')['httpStatus']);$this->assertSame(200,app(ChessWalletReservationService::class)->release(['cmsReservationId'=>$id],$this->partner,'recover')['httpStatus']);$this->assertBalance('500.00');$this->reconcileBlackjack();
 }
 public function test_source_recurring_rollover_checks_latest_market_instead_of_old_terminal_row():void {
  $overlay=app(\App\Services\ChessLiveCockpit\ChessRecurringSurfaceOverlayService::class);$overlay->ensure();$b=ChessLiveBoard::where('external_board_key','cms-recurring-3-0-thursday')->firstOrFail();$old=$b->bettingMarkets()->firstOrFail();$old->update(['status'=>'settled']);$overlay->ensure();$current=$b->bettingMarkets()->latest('id')->firstOrFail();$this->assertNotSame($old->id,$current->id);$p=$this->payload('source-rollover');$p['marketInstanceId']=$b->external_board_key;$p['selectionId']=$current->selections()->value('selection_code');$r=$this->reserve($p);$this->assertSame(200,$r['httpStatus'],json_encode($r));$this->reconcileBlackjack();
 }
 public function test_invalid_win_amounts_leave_reservation_and_finance_unchanged_then_valid_win_pays_once():void {
  $svc=app(ChessWalletReservationService::class);
  foreach(['missing',null,'0','0.00','-1.00','broken','1.001','1e2','10000000000000000.00',[],true] as $i=>$amount){
   $r=$this->reserve($this->payload('win-validation-'.$i),'win-validation-'.$i);$this->assertSame(200,$r['httpStatus']);$id=$r['body']['cmsReservationId'];
   $p=$this->handoff($id,'WIN','2.53');if($amount==='missing')unset($p['payoutAmount']);else $p['payoutAmount']=$amount;
   $before=$this->financeHash();$reservation=ChessWalletReservation::where('cms_reservation_id',$id)->firstOrFail()->getRawOriginal();
   $this->assertSame(422,$svc->handoffSettlement($p,'invalid',$this->partner)['httpStatus'],'invalid vector '.$i);
   $this->assertSame($before,$this->financeHash());$this->assertSame($reservation,ChessWalletReservation::where('cms_reservation_id',$id)->firstOrFail()->getRawOriginal());
   $valid=$this->handoff($id,'WIN','2.53');$this->assertSame(200,$svc->handoffSettlement($valid,'valid',$this->partner)['httpStatus']);$after=$this->financeHash();
   $this->assertSame(200,$svc->handoffSettlement($valid,'replay',$this->partner)['httpStatus']);$this->assertSame($after,$this->financeHash());$this->reconcileBlackjack();
  }
 }
}
