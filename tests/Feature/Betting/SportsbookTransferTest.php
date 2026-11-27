<?php
namespace Tests\Feature\Betting;
use Tests\TestCase;
use Tests\Support\BlackjackFixture;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use App\Models\{BettingFeed,BettingEvent,BettingMarket,BettingOutcome,BettingQuoteVersion,BettingQuoteSelection,BettingAcceptance,CmsUserTransaction,WalletLedgerEntry};
use App\Betting\Acceptance\AcceptanceService;
use App\Betting\Settlement\SettlementService;
use Brick\Math\BigDecimal;

final class SportsbookTransferTest extends TestCase {
 use DatabaseTransactions,BlackjackFixture;
 private BettingQuoteSelection $selection;
 private string $adapterConfigPath;
 protected function setUp():void {
  parent::setUp();$this->setupBlackjack();
  config(['betting.sportsbook_local_parity.enabled'=>true,'betting.sportsbook_local_parity.read_source'=>'adapter_live','betting.sportsbook_local_parity.write_target'=>'local','betting.sportsbook_local_parity.finance_enabled'=>true,'betting.sportsbook_local_parity.finance_sandbox'=>true,'betting.offline_demo.mode'=>'off']);
  $this->adapterConfigPath=storage_path('framework/sportsbook-unit-'.Str::uuid().'.json');file_put_contents($this->adapterConfigPath,json_encode(['enabled'=>true,'mode'=>'owned_unit_contract','base_url'=>'http://127.0.0.1:18973','auth_token'=>'unit-owned-token','allow_public_prematch'=>true,'allow_quote_verify'=>true,'allow_acceptance'=>true]));
  $this->app->instance(\App\Betting\AdapterVps\AdapterVpsConfigReader::class,new \App\Betting\AdapterVps\AdapterVpsConfigReader($this->adapterConfigPath));
  $f=BettingFeed::create(['feed_key'=>'local-contract','display_name'=>'Local synthetic fixture','adapter_key'=>'manual','mode'=>'prematch','status'=>'active']);
  $e=BettingEvent::create(['event_key'=>'local:event:1','sport_code'=>'soccer_epl','lifecycle_status'=>'scheduled','trading_status'=>'open','visibility_status'=>'visible','front_betting_enabled'=>true,'canonical_name'=>'Synthetic Home vs Synthetic Away','participant_home'=>'Synthetic Home','participant_away'=>'Synthetic Away','starts_at'=>now()->addDay(),'discovered_at'=>now(),'last_source_seen_at'=>now()]);
  $m=BettingMarket::create(['betting_event_id'=>$e->id,'market_type'=>'match_result','specifier_key'=>'default','status'=>'open','display_name'=>'Match result','sort_order'=>10,'meta'=>[]]);
  $o=BettingOutcome::create(['betting_market_id'=>$m->id,'outcome_code'=>'home','display_name'=>'Home','status'=>'open','sort_order'=>10]);
  $v=BettingQuoteVersion::create(['betting_event_id'=>$e->id,'betting_feed_id'=>$f->id,'version_uuid'=>(string)Str::uuid(),'version_no'=>1,'quote_status'=>'active','generation_mode'=>'automated','trading_state'=>'open','selection_count'=>1,'compiled_at'=>now(),'effective_from'=>now(),'payload_digest'=>hash('sha256','synthetic-fixture'),'meta'=>[],'override_meta'=>[]]);
  $e->update(['current_quote_version_id'=>$v->id]);$this->selection=BettingQuoteSelection::create(['betting_quote_version_id'=>$v->id,'betting_market_id'=>$m->id,'betting_outcome_id'=>$o->id,'offer_status'=>'open','trading_decision_source'=>'automated','decimal_price'=>'2.250000','base_decimal_price'=>'2.250000','meta'=>[]]);
 }
 protected function tearDown():void {Carbon::setTestNow();if(isset($this->adapterConfigPath)&&is_file($this->adapterConfigPath))unlink($this->adapterConfigPath);parent::tearDown();}
 private function accept(string $key='accept-1',string $stake='10.00',?BettingQuoteSelection $selection=null):BettingAcceptance {
  return app(AcceptanceService::class)->requestForSelection($selection??$this->selection,['cms_user_id'=>$this->player->id,'stake_amount'=>$stake,'currency'=>'TRY','accepted_price'=>'2.250000','idempotency_key'=>$key,'acceptance_source'=>'cms_legacy','acceptance_contract_version'=>'legacy_v1']);
 }
 public function test_source_acceptance_real_canonical_debit_snapshot_and_idempotency():void {
  $a=$this->accept();$this->assertSame('pending',$a->status);$this->assertNotEmpty($a->accepted_snapshot_digest);
  $this->assertTrue(BigDecimal::of($this->wallet->fresh()->available_balance)->isEqualTo('490'));
  $this->assertSame(1,CmsUserTransaction::where('event_type','sportsbook_acceptance')->count());$hash=$this->financeHash();
  $this->assertSame($a->id,$this->accept()->id);$this->assertSame($hash,$this->financeHash());$this->reconcileBlackjack();
 }
 public function test_different_payload_replay_conflicts_without_second_debit():void {
  $this->accept();$hash=$this->financeHash();try{$this->accept(stake:'11.00');$this->fail('Conflict expected');}catch(\App\Betting\Exceptions\IdempotencyConflictException $e){$this->assertSame($hash,$this->financeHash());}
 }
 public function test_stale_quote_closed_selection_cutoff_and_insufficient_reject():void {
  foreach(['stale','closed','cutoff','insufficient']as $case){$s=$this->selection->fresh(['quoteVersion.event']);$e=$s->quoteVersion->event;
   if($case==='stale')$e->update(['current_quote_version_id'=>null]);
   if($case==='closed')$s->update(['offer_status'=>'closed']);
   if($case==='cutoff')$e->update(['starts_at'=>now()->subMinute()]);
   $hash=$this->financeHash();try{$this->accept('reject-'.$case,$case==='insufficient'?'999.00':'10.00',$s);$this->fail('Rejection expected');}catch(\RuntimeException $ex){$this->assertSame($hash,$this->financeHash());}
   $e->update(['current_quote_version_id'=>$s->betting_quote_version_id,'starts_at'=>now()->addDay()]);$s->update(['offer_status'=>'open']);
  }
 }
 public function test_won_lost_void_refund_source_settlement_and_wallet_reconcile():void {
  $accepted=[];foreach(['won','lost','void','refunded']as $result){$a=$this->accept('result-'.$result);$accepted[$result]=app(AcceptanceService::class)->review($a,'accept','Synthetic local review',$this->operator);}
  Carbon::setTestNow(now()->addDays(2));
  foreach($accepted as $result=>$a){$s=app(SettlementService::class)->review($a->settlement,$result,'Synthetic local result vector',$this->operator);$this->assertSame($result,$s->result_code);$this->assertSame('settled',$a->fresh()->status);}
  $this->assertTrue(BigDecimal::of($this->wallet->fresh()->available_balance)->isEqualTo('502.50'));$this->reconcileBlackjack();
 }
 public function test_pending_settlement_window_and_terminal_replay_never_double_credit():void {
  $a=app(AcceptanceService::class)->review($this->accept(),'accept','Synthetic accepted coupon',$this->operator);$hash=$this->financeHash();
  try{app(SettlementService::class)->review($a->settlement,'won','Too early',$this->operator);$this->fail('Readiness gate expected');}catch(\RuntimeException $ex){$this->assertSame($hash,$this->financeHash());}
  Carbon::setTestNow(now()->addDays(2));$s=app(SettlementService::class)->review($a->settlement,'won','Controlled local result',$this->operator);$hash=$this->financeHash();
  try{app(SettlementService::class)->review($s,'won','Repeated operator result',$this->operator);$this->fail('Final replay expected');}catch(\App\Betting\Exceptions\SettlementAlreadyFinalizedException $ex){$this->assertSame($hash,$this->financeHash());}$this->reconcileBlackjack();
 }
 public function test_source_token_expiry_invalid_signature_and_local_finance_gate():void {
  $tokens=app(\App\Betting\Support\PublicSelectionTokenService::class);$token=$tokens->issueForAdapterSelection(['event_id'=>1,'event_key'=>'local:event:1','phase_key'=>'pre-match'],['market_type'=>'match_result','status'=>'open'],['quote_ref'=>'local:quote','decimal_price'=>'2.25','outcome_code'=>'home','offer_status'=>'open']);
  $this->assertSame('local:quote',$tokens->resolve($token)['quote_ref']);$hash=$this->financeHash();try{$tokens->resolve($token.'bad');$this->fail('Invalid token expected');}catch(\App\Betting\Exceptions\PublicSelectionTokenException $e){$this->assertSame($hash,$this->financeHash());}
  config(['betting.sportsbook_local_parity.finance_enabled'=>false,'betting.sportsbook_local_parity.finance_sandbox'=>false]);
  try{$this->accept();$this->fail('Finance gate expected');}catch(\RuntimeException $e){$this->assertSame($hash,$this->financeHash());}
 }

 private function verifiedQuote(array $changes=[]):array {
  return array_replace_recursive(['status'=>'open','trading_state'=>'open','offer_status'=>'open','quote_ref'=>'owned:quote:unit:home','quote_version_ref'=>'owned:version:1','quote_payload_digest'=>hash('sha256','fixture'),'verification_id'=>'unit-verification-1','verified_at'=>now()->toIso8601String(),'price'=>['decimal_price'=>'2.250000'],'event'=>['canonical_name'=>'Owned synthetic unit event','sport_code'=>'soccer_epl','starts_at'=>now()->addDay()->toIso8601String()],'market'=>['market_type'=>'match_result','display_name'=>'Match result'],'selection'=>['selection_key'=>'home','display_name'=>'Home','offer_status'=>'open'],'source'=>['provider_key'=>'owned_synthetic_fixture','connector_key'=>'adapter_vps'],'settlement_identity'=>['source_event_key'=>'owned-unit','source_market_key'=>'match_result','source_selection_key'=>'home','market_type'=>'match_result','selection_key'=>'home']],$changes);
 }
 private function adapterAccept(string $key='unit-adapter',string $stake='10.00',?int $user=null):BettingAcceptance {
  return app(\App\Betting\AdapterVps\AdapterVpsQuoteAcceptanceService::class)->requestForQuoteRef('owned:quote:unit:home',['cms_user_id'=>$user??$this->player->id,'stake_amount'=>$stake,'currency'=>'TRY','accepted_price'=>'2.250000','idempotency_key'=>$key]);
 }
 public function test_actual_adapter_verify_snapshot_wallet_and_replay_contract():void {
  Http::fake(['http://127.0.0.1:18973/quotes/verify'=>Http::response($this->verifiedQuote())]);
  $a=$this->adapterAccept();$this->assertSame('adapter_vps_v2',$a->acceptance_contract_version);$this->assertNotEmpty($a->accepted_snapshot_digest);$this->assertNull($a->betting_event_id);
  $this->assertTrue(BigDecimal::of($this->wallet->fresh()->available_balance)->isEqualTo('490'));
  $before=$this->financeHash();$this->assertSame($a->id,$this->adapterAccept()->id);$this->assertSame($before,$this->financeHash());Http::assertSentCount(1);
  try{$this->adapterAccept(stake:'11.00');$this->fail('Conflict expected');}catch(\App\Betting\Exceptions\IdempotencyConflictException $e){$this->assertSame($before,$this->financeHash());}
  $this->reconcileBlackjack();
 }
 public function test_adapter_stale_closed_insufficient_and_foreign_replay_never_debit_twice():void {
  foreach(['stale','closed','insufficient']as $case){$before=$this->financeHash();$q=$this->verifiedQuote();if($case==='stale')$q['price']['decimal_price']='3.000000';if($case==='closed')$q['offer_status']='closed';Http::fake(['http://127.0.0.1:18973/quotes/verify'=>Http::response($q)]);
   try{$this->adapterAccept($case,$case==='insufficient'?'99999.00':'10.00');$this->fail('Rejection expected '.$case);}catch(\App\Betting\Exceptions\BettingDomainException $e){$this->assertSame($before,$this->financeHash());}
  }
 }
 public function test_source_settlement_applies_payout_then_refund_correction_and_duplicate_once():void {
  $a=$this->accept();app(AcceptanceService::class)->review($a,'accept','Synthetic verified decision',$this->operator);$s=$a->fresh()->settlement;
  $svc=app(SettlementService::class);$attrs=['source_adapter_key'=>'owned_synthetic_results','ingest_origin'=>'local_contract','source_result_key'=>'unit-owned-result','candidate_result_code'=>'won','source_payload'=>['revision'=>1]];
  $r=$svc->reconcileSourceResult($s,$attrs,$this->operator);$this->assertSame('won',$s->fresh()->result_code);$this->assertTrue(BigDecimal::of($this->wallet->fresh()->available_balance)->isEqualTo('512.50'));$before=$this->financeHash();$svc->reconcileSourceResult($s,$attrs,$this->operator);$this->assertSame($before,$this->financeHash());
  $attrs['candidate_result_code']='void';$attrs['source_payload']['revision']=2;$svc->reconcileSourceResult($s,$attrs,$this->operator);$this->assertSame('void',$s->fresh()->result_code);$this->assertTrue(BigDecimal::of($this->wallet->fresh()->available_balance)->isEqualTo('500'));$before=$this->financeHash();$svc->reconcileSourceResult($s,$attrs,$this->operator);$this->assertSame($before,$this->financeHash());$this->reconcileBlackjack();
 }

 public function test_large_canonical_balance_keeps_cents_through_actual_source_acceptance_payout_and_refund():void {
  $this->credit('90071992546910.29','sportsbook-large-credit');$a=$this->accept('large-source','10.00');$a=app(AcceptanceService::class)->review($a,'accept','Synthetic large balance review',$this->operator);$this->assertSame('90071992547400.29000000',$this->wallet->fresh()->available_balance);
  Carbon::setTestNow(now()->addDays(2));app(SettlementService::class)->review($a->settlement,'won','Synthetic large balance payout',$this->operator);$this->assertSame('90071992547422.79000000',$this->wallet->fresh()->available_balance);$this->reconcileBlackjack();
 }
 public function test_public_selection_token_expiry_and_disabled_acceptance_are_fail_closed():void {
  $tokens=app(\App\Betting\Support\PublicSelectionTokenService::class);$token=$tokens->issueForAdapterSelection(['event_id'=>1,'event_key'=>'adapter_vps:unit','starts_at'=>now()->addDay()->toIso8601String()],['market_type'=>'match_result','status'=>'open'],['quote_ref'=>'owned:quote:unit:home','decimal_price'=>'2.25','outcome_code'=>'home','offer_status'=>'open']);$before=$this->financeHash();Carbon::setTestNow(now()->addDay());try{$tokens->resolve($token);$this->fail('Expiry expected');}catch(\App\Betting\Exceptions\PublicSelectionTokenException $e){$this->assertSame($before,$this->financeHash());}Carbon::setTestNow();
  config(['betting.sportsbook_local_parity.finance_enabled'=>false,'betting.sportsbook_local_parity.finance_sandbox'=>false]);try{$this->adapterAccept();$this->fail('Closed local finance gate expected');}catch(\RuntimeException $e){$this->assertSame($before,$this->financeHash());}Http::assertNothingSent();
 }

 public function test_sportsbook_country_policy_blocks_the_authenticated_actor_without_finance_mutation():void {
  $this->actingAs($this->player,'cms');\App\SiteSettings::updateOrCreate(['key'=>\App\Services\Restrictions\CentralRestrictionPolicyService::SETTINGS_KEY],['value'=>json_encode(['countries'=>[(string)$this->player->country_id=>['products'=>['sportsbook'],'reason_code'=>'geo_restricted']]])]);$before=$this->financeHash();try{app(\App\Http\Middleware\SportsbookActor::class)->handle(request(),fn()=>response('unexpected'));$this->fail('Country deny expected');}catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame(403,$e->getStatusCode());$this->assertSame($before,$this->financeHash());}
 }

 public function test_member_wallet_snapshot_preserves_exact_large_balance_and_does_not_write():void {
  $this->credit('90071992546910.29','sportsbook-exact-HUD');$this->actingAs($this->player,'cms');$before=$this->financeHash();$response=app(\App\Http\Controllers\Cms\Front\RiseSportsbookController::class)->wallet();$this->assertSame('90071992547410.29',$response->getData()->balanceExact);$this->assertSame('90071992547410.29',$response->getData()->formattedBalance);$this->assertSame($before,$this->financeHash());
 }

 public function test_explicit_A_and_B_financial_requests_are_read_only_before_any_adapter_or_wallet_effect():void {
  $this->actingAs($this->player,'cms');$before=$this->financeHash();foreach(['A','B']as $mode){$request=\Illuminate\Http\Request::create('/cms/api/betting/acceptances/adapter-vps?mode='.$mode,'POST');$response=app(\App\Http\Middleware\SportsbookActor::class)->handle($request,fn()=>response('unexpected'));$this->assertSame(409,$response->getStatusCode());$this->assertSame($before,$this->financeHash());}Http::assertNothingSent();
 }
 public function test_A_snapshot_remains_selectable_in_league_rows_with_no_financial_authority():void {
  $before=$this->financeHash();$event=['event_id'=>42,'event_key'=>'owned:42','sport_code'=>'soccer_epl','canonical_name'=>'Owned Home vs Owned Away','participant_home'=>'Owned Home','participant_away'=>'Owned Away','starts_at'=>now()->addDay()->toAtomString(),'offline_demo'=>true,'public_live_state'=>['phase_key'=>'pre-match'],'quote'=>['is_tradeable'=>true,'quote_version_id'=>'owned-42','markets'=>[['market_type'=>'match_result','specifier_key'=>'default','display_name'=>'Match result','status'=>'open','selections'=>[['outcome_code'=>'home','display_name'=>'Home','decimal_price'=>'2.250000','offer_status'=>'open','quote_ref'=>'owned:42:home','quote_selection_id'=>null,'acceptance_mode'=>'adapter_vps_quote_ref','bettable'=>true]]]]]];
  $board=app(\App\Betting\Support\SportsbookShellPresentation::class)->board([$event]);$match=$board['leagues'][0]['matches'][0];$this->assertTrue($match['offline_demo']);$this->assertTrue($match['match_result'][0]['demo_selectable']);$this->assertFalse($match['match_result'][0]['acceptance_ready']);$this->assertNull($match['match_result'][0]['selection_token']);$this->assertSame($before,$this->financeHash());
 }
}
