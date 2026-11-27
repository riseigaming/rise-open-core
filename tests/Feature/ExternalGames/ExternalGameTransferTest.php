<?php
namespace Tests\Feature\ExternalGames;
use Tests\TestCase;
use Tests\Support\BlackjackFixture;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Http};
use App\Models\{CmsGame,Provider,ExternalGameSession,CmsUserTransaction,WalletLedgerEntry};
use App\Services\ExternalGames\{ExternalGameCallbackService,ExternalGameSessionService,ExternalGameCallbackFinanceReconciliationService,ExternalGameProviderRolloutService,ExternalGamesControlSettingsService,ExternalGameUserRiskLimitOverrideService};
use Brick\Math\BigDecimal;

final class ExternalGameTransferTest extends TestCase
{
    use DatabaseTransactions,BlackjackFixture;
    private CmsGame $game;private ExternalGameSession $external;
    protected function setUp():void {
        parent::setUp();$this->setupBlackjack();app(\App\Services\ProductAccess\ProductAccessPolicyService::class)->upsertOverride($this->player,'external_games','','allow','Local explicit operator approval');
        config(['external_games.providers.primary_external_provider.gateway_hmac_secret'=>'local-unit-gateway-secret','external_games.local_contract_enabled'=>true,'external_games.providers.primary_external_provider.api_base_url'=>'http://127.0.0.1:18971','external_games.providers.primary_external_provider.agent_code'=>'local-code','external_games.providers.primary_external_provider.agent_token'=>'local-token','external_games.providers.primary_external_provider.agent_secret'=>'local-secret']);
        app(ExternalGamesControlSettingsService::class)->persist(['enabled'=>true,'demo_enabled'=>true,'emergency_stop'=>false,'pilot_policy'=>'default_open'],$this->operator);
        $p=Provider::create(['name'=>'Local source provider','slug'=>'primary_external_provider','kind'=>'external','status'=>'active','show_left_panel'=>true,'config'=>['provider_alias'=>'primary_external_provider']]);
        $this->game=CmsGame::create(['provider_id'=>$p->id,'slug'=>'external-test','name'=>'External test','reference_id'=>'provider-game-1','status'=>'active','extra_parameters'=>json_encode(['external_games'=>['provider_alias'=>'primary_external_provider','provider_game_id'=>'provider-game-1','vendor_id'=>'local-contract','raw'=>['fun_mode'=>1]]])]);
        $this->external=$this->newExternalSession();
    }
    private function newExternalSession():ExternalGameSession {
        $s=app(ExternalGameSessionService::class)->prepare($this->game,$this->player,$this->player,$this->wallet,'primary_external_provider','provider-game-1',false,[],['lang'=>'tr']);
        return app(ExternalGameSessionService::class)->markLaunched($s,['launch_url'=>'http://127.0.0.1:18971/frame/test']);
    }
    private function deliverCallback(string $method='transaction_bet',string $id='bet-1',string $amount='10.00',array $extra=[]):array {
        $p=['method'=>$method,'agent_code'=>'local-code','agent_token'=>'local-token','agent_secret_key'=>'local-secret','session_token'=>$this->external->session_token,
            'user_id'=>$this->player->id,'currency'=>'TRY','game'=>'provider-game-1','round_id'=>'round-1','transaction_id'=>$id,
            match($method){'transaction_bet'=>'bet','transaction_win'=>'win',default=>'amount'}=>$amount];
        $r=Request::create('/integrations/games/primary/wallet','POST',array_replace($p,$extra));
        $result=app(ExternalGameCallbackService::class)->handle($r);$this->app->terminate();return $result;
    }
    public function test_bet_win_refund_replay_conflict_ledger_and_report():void {
        $a=$this->deliverCallback();$this->assertSame(1,$a['status']);$this->assertTrue(BigDecimal::of($this->wallet->fresh()->available_balance)->isEqualTo('490'));
        $hash=$this->financeHash();$this->assertSame($a,$this->deliverCallback());$this->assertSame($hash,$this->financeHash());
        $this->assertSame(0,$this->deliverCallback(amount:'11.00')['status']);$this->assertSame($hash,$this->financeHash());
        $this->assertSame(1,$this->deliverCallback('transaction_win','win-1','25.00')['status']);
        $refund=$this->deliverCallback('refund','refund-1','10.00');$this->assertSame(1,$refund['status']);
        $hash=$this->financeHash();$this->assertSame($refund,$this->deliverCallback('refund','refund-1','10.00'));
        $this->assertSame(0,$this->deliverCallback('refund','refund-2','0.01')['status']);$this->assertSame($hash,$this->financeHash());
        $this->assertCount(3,CmsUserTransaction::where('transactionId','like','external_games:%')->get());$this->reconcileBlackjack();
        $r=app(ExternalGameCallbackFinanceReconciliationService::class)->report();$this->assertSame('ok',$r['finance_integrity_status']);
    }
    public function test_credentials_identity_currency_demo_expiry_and_insufficient_have_no_effect():void {
        $hash=$this->financeHash();
        foreach([['agent_secret_key'=>'wrong'],['user_id'=>$this->operator->id],['currency'=>'USD'],['session_token'=>'unknown-token']]as $p)
            $this->assertSame(0,$this->deliverCallback(extra:$p)['status']);
        $this->assertSame(0,$this->deliverCallback(amount:'501.00')['status']);$this->assertSame(0,$this->deliverCallback(amount:'1.001')['status']);
        $this->external->update(['mode'=>'demo']);$this->assertSame(0,$this->deliverCallback()['status']);
        $this->external->update(['mode'=>'real','expires_at'=>now()->subMinute()]);$this->assertSame(0,$this->deliverCallback()['status']);
        $this->assertSame($hash,$this->financeHash());
    }
    public function test_orphan_win_refund_and_reversed_delivery_reject_until_bet_exists():void {
        $hash=$this->financeHash();$this->assertSame(0,$this->deliverCallback('transaction_win','out-of-order-win')['status']);
        $this->assertSame(0,$this->deliverCallback('refund','orphan-refund')['status']);$this->assertSame($hash,$this->financeHash());
        $this->assertSame(1,$this->deliverCallback()['status']);$this->assertSame(1,$this->deliverCallback('transaction_win','out-of-order-win')['status']);$this->reconcileBlackjack();
    }
    public function test_relaunch_same_identity_replay_and_cross_wallet_tampering():void {
        $a=$this->deliverCallback();$this->external=$this->newExternalSession();$hash=$this->financeHash();$this->assertSame($a,$this->deliverCallback());$this->assertSame($hash,$this->financeHash());
        $foreign=\App\Models\WalletAccount::create(['cms_user_id'=>$this->operator->id,'wallet_type'=>'primary_balance','wallet_key'=>'default','asset'=>'TRY','available_balance'=>'0','pending_balance'=>'0','status'=>'active']);
        $this->external->update(['wallet_account_id'=>$foreign->id]);$hash=$this->financeHash();$this->assertSame(0,$this->deliverCallback(id:'foreign-wallet')['status']);$this->assertSame($hash,$this->financeHash());
    }
    public function test_emergency_stop_blocks_new_debit_preserves_replay_linked_credit_refund():void {
        $first=$this->deliverCallback();app(ExternalGamesControlSettingsService::class)->persist(['enabled'=>true,'emergency_stop'=>true,'demo_enabled'=>true,'pilot_policy'=>'default_open'],$this->operator);
        $hash=$this->financeHash();$this->assertSame($first,$this->deliverCallback());$this->assertSame(0,$this->deliverCallback(id:'new-bet')['status']);$this->assertSame($hash,$this->financeHash());
        $this->assertSame(1,$this->deliverCallback('transaction_win','win-stop')['status']);$this->assertSame(1,$this->deliverCallback('refund','refund-stop')['status']);$this->reconcileBlackjack();
    }
    public function test_risk_override_applies_before_debit_and_keeps_audit():void {
        app(ExternalGameUserRiskLimitOverrideService::class)->upsert($this->player,['max_single_bet'=>5],'Local synthetic risk acceptance',$this->operator);
        $hash=$this->financeHash();$this->assertSame(0,$this->deliverCallback()['status']);$this->assertSame($hash,$this->financeHash());
        $this->assertSame(1,DB::table('external_game_user_risk_limit_override_audits')->count());
    }
    public function test_large_balance_cent_integrity_uses_existing_exact_finance_path():void {
        $this->credit('90071992547400.01','external-large-balance');$before=$this->wallet->fresh()->available_balance;
        $this->assertSame(1,$this->deliverCallback(amount:'0.01')['status']);$after=$this->wallet->fresh()->available_balance;
        $this->assertTrue(BigDecimal::of($before)->minus('0.01')->isEqualTo($after));
        $tx=CmsUserTransaction::where('transactionId','like','external_games:%')->firstOrFail();$this->assertTrue(BigDecimal::of($tx->exact_after_balance)->isEqualTo($after));
        $this->assertSame(1,$this->deliverCallback('refund','large-refund','0.01')['status']);$this->assertTrue(BigDecimal::of($before)->isEqualTo($this->wallet->fresh()->available_balance));$this->reconcileBlackjack();
    }
    public function test_source_generic_signature_and_invalid_signature():void {
        $p=['session_token'=>$this->external->session_token,'user_id'=>$this->player->id,'currency'=>'TRY','transaction_id'=>'generic-signed-bet','amount'=>'-1.00'];
        $r=Request::create('/api/v1/external-games/callback/changebalance','POST',$p);$r->headers->set('X-Authorization',sha1('changebalance'.'local-secret'));
        $result=app(ExternalGameCallbackService::class)->handle($r,'changebalance');$this->assertSame(1,$result['status']);$this->app->terminate();$hash=$this->financeHash();
        $r->headers->set('X-Authorization','wrong');$this->assertSame(403,app(ExternalGameCallbackService::class)->handle($r,'changebalance')['http_status']);$this->assertSame($hash,$this->financeHash());$this->reconcileBlackjack();
    }
    public function test_rollout_maintenance_disabled_and_access_deny():void {
        app(\App\Services\ExternalGames\ExternalGameAvailabilityService::class)->assertLaunchable($this->game,$this->player,false);
        app(ExternalGameProviderRolloutService::class)->setGameState($this->game,'maintenance',$this->operator->id);
        $this->expectException(\App\Services\ExternalGames\ExternalGameLaunchBlockedException::class);
        app(\App\Services\ExternalGames\ExternalGameAvailabilityService::class)->assertLaunchable($this->game->fresh('provider'),$this->player,false);
    }
    public function test_reimport_preserves_operator_visibility_maintenance_and_game_rollout():void {
        $this->game->provider->update(['show_left_panel'=>false,'is_maintenance'=>true,'status'=>'passive','config'=>['external_games'=>['kill_switch'=>true]]]);
        app(ExternalGameProviderRolloutService::class)->setGameState($this->game,'disabled',$this->operator->id);
        $this->game->update(['is_maintenance'=>true,'status'=>'passive']);
        Http::fake(['http://127.0.0.1:18971/api/v1/auth/authentication'=>Http::response(['access_token'=>'local-unit-token']),
            'http://127.0.0.1:18971/api/v1/games/all'=>Http::response(['status'=>1,'games'=>[['game_id'=>'provider-game-1','game_name'=>'Updated source title','provider_id'=>'local-contract','status'=>1,'category'=>'table']]])]);
        $hash=$this->financeHash();$r=app(\App\Services\ExternalGames\ExternalGameCatalogImportService::class)->sync();$this->assertSame(1,$r['updated_count']);
        $g=$this->game->fresh('provider');$this->assertFalse($g->provider->show_left_panel);$this->assertTrue($g->provider->is_maintenance);$this->assertSame('passive',$g->provider->status);
        $this->assertTrue(data_get($g->provider->config,'external_games.kill_switch'));$this->assertTrue($g->is_maintenance);$this->assertSame('passive',$g->status);
        $this->assertSame('disabled',app(ExternalGameProviderRolloutService::class)->decisionForGame($g)['state']);$this->assertSame($hash,$this->financeHash());
    }
    public function test_expiry_blocks_new_bet_but_linked_late_settlement_refund_remain_payable():void {
        $this->assertSame(1,$this->deliverCallback()['status']);$this->external->update(['expires_at'=>now()->subHour()]);
        $hash=$this->financeHash();$this->assertSame(0,$this->deliverCallback(id:'expired-new-bet')['status']);$this->assertSame($hash,$this->financeHash());
        $this->assertSame(1,$this->deliverCallback('transaction_win','late-win','5.00')['status']);
        $this->assertSame(1,$this->deliverCallback('refund','late-refund','10.00')['status']);$this->reconcileBlackjack();
    }
    public function test_live_table_runtime_alias_keeps_pinned_game_identity():void {
        $this->assertSame(1,$this->deliverCallback(extra:['game'=>'live-runtime-table-alias'])['status']);
        $tx=CmsUserTransaction::where('transactionId','like','external_games:%')->firstOrFail();$this->assertSame($this->game->id,(int)$tx->game_id);
        $this->assertSame(1,$this->deliverCallback('transaction_win','alias-win','5.00', ['game'=>'live-runtime-table-alias'])['status']);$this->reconcileBlackjack();
    }
    public function test_strict_provider_launch_string_balance_is_exact_and_field_profile_is_preserved():void {
        Http::fake(['http://127.0.0.1:18971/api/v1/games/game_launch'=>Http::response(['status'=>1,'launch_url'=>'http://127.0.0.1:18971/frame/unit'])]);
        app(\App\Services\ExternalGames\PrimaryExternalProviderClient::class)->gameLaunch([
            '_launch_payload_profile'=>'strict_spinstruct_reference','game_id'=>'provider-game-1',
            'user_id'=>(string)$this->player->id,'user_name'=>'local-player','currency'=>'TRY',
            'balance'=>'90071992547400.01','status'=>'normal','lang'=>'tr','wallet_account_id'=>$this->wallet->id,'session_token'=>'must-not-leak-into-strict-profile']);
        Http::assertSent(function($r):bool {
            $body=json_decode($r->body(),true);
            return $body['balance']==='90071992547400.01'&&!isset($body['wallet_account_id'])&&!isset($body['session_token'])
                &&$r->hasHeader('X-Gateway-Signature')&&$r->hasHeader('Authorization','Bearer '.base64_encode('local-token:local-secret'));
        });
    }
    public function test_existing_manual_credit_financing_is_recognized_by_source_eligibility_predicate():void {
        $before=$this->financeHash();
        $d=app(\App\Services\ExternalGames\ExternalGameRealDepositEligibilityService::class)->resolve($this->player);
        $this->assertTrue($d['eligible']);$this->assertSame('admin_manual_cash_credit',$d['source']);
        $this->assertSame($before,$this->financeHash());
    }
    public function test_real_capability_defaults_to_operator_approval_and_narrow_allow_never_overrides_deny():void {
        $policy=app(\App\Services\ProductAccess\ProductAccessPolicyService::class);$read=app(\App\Services\ExternalGames\ExternalGameDiscoveryReadService::class);
        $hash=$this->financeHash();$policy->upsertOverride($this->player,'external_games','','default','Remove explicit approval');
        $this->assertFalse($read->realCapability($this->game,$this->player)['available']);
        $policy->upsertOverride($this->player,'external_games','primary_external_provider','allow','Narrow provider approval');
        $this->assertFalse($read->realCapability($this->game,$this->player)['available']);
        $policy->upsertOverride($this->player,'external_games','','deny','Global explicit denial');
        $this->assertFalse($read->realCapability($this->game,$this->player)['available']);
        $policy->upsertOverride($this->player,'external_games','','allow','Restore operator approval');
        $this->assertTrue($read->realCapability($this->game,$this->player)['available']);
        $this->assertTrue(app(\App\Services\ExternalGames\ExternalGameDemoService::class)->available($this->game));
        config(['external_games.providers.primary_external_provider.agent_secret'=>'']);
        $this->assertFalse($read->realCapability($this->game,$this->player)['available']);
        $this->assertFalse(app(\App\Services\ExternalGames\ExternalGameDemoService::class)->available($this->game));
        $this->assertSame($hash,$this->financeHash());
    }
    public function test_eligibility_and_risk_override_expiry_restore_source_fallback_without_mutation():void {
        $eligibility=app(\App\Services\ExternalGames\ExternalGameRealMoneyEligibilityOverrideService::class);
        $grant=$eligibility->upsert($this->player,'Synthetic eligibility window',$this->operator,now()->addMinute()->toDateTimeString());
        $this->assertNotNull($eligibility->activeFor($this->player));$grant->update(['expires_at'=>now()->subSecond()]);$this->assertNull($eligibility->activeFor($this->player));
        $d=app(\App\Services\ExternalGames\ExternalGameRealDepositEligibilityService::class)->resolve($this->player);$this->assertSame('admin_manual_cash_credit',$d['source']);
        $risk=app(ExternalGameUserRiskLimitOverrideService::class);$limit=$risk->upsert($this->player,['max_single_bet'=>1],'Synthetic risk window',$this->operator,now()->addMinute()->toDateTimeString());
        $this->assertNotNull($risk->activeFor($this->player));$limit->update(['expires_at'=>now()->subSecond()]);$this->assertNull($risk->activeFor($this->player));
        $hash=$this->financeHash();$this->assertSame('default',app(\App\Services\ExternalGames\ExternalGameRiskLimitService::class)->resolvedLimits($this->player)['source']);$this->assertSame($hash,$this->financeHash());
    }
    public function test_source_long_provider_transaction_and_round_ids_remain_exact_and_replayable():void {
        $id=str_repeat('t',191);$round=str_repeat('r',191);
        $r=$this->deliverCallback(id:$id,extra:['round_id'=>$round]);$this->assertSame(1,$r['status']);
        $tx=CmsUserTransaction::where('provider_transaction_id',$id)->firstOrFail();$this->assertSame($round,$tx->round_id);
        $this->assertGreaterThan(120,strlen($tx->transactionId));$hash=$this->financeHash();
        $this->assertSame($r,$this->deliverCallback(id:$id,extra:['round_id'=>$round]));$this->assertSame($hash,$this->financeHash());
        $this->assertSame(1,$this->deliverCallback('refund',str_repeat('f',191),'10.00',['round_id'=>$round])['status']);$this->reconcileBlackjack();
    }
    public function test_game_maintenance_retains_503_before_launch_payload_validation():void {
        auth('cms')->login($this->player);$this->game->update(['is_maintenance'=>true]);$hash=$this->financeHash();
        $r=\Illuminate\Http\Request::create('/cms/games/external-test/launch','POST');
        $response=app(\App\Http\Controllers\Cms\Front\GameController::class)->launch($r,'external-test',app(\App\Services\Games\CatalogReadService::class),app(\App\Services\Restrictions\ProductCountryRestrictionGuard::class));
        $this->assertSame(503,$response->getStatusCode());$this->assertSame($hash,$this->financeHash());
    }
    public function test_unsupported_wallet_launch_returns_409_and_never_reaches_provider_finance():void {
        auth('cms')->login($this->player);config(['product_wallets.products.external_games.supported_assets'=>['USD']]);$hash=$this->financeHash();
        $r=\Illuminate\Http\Request::create('/cms/games/external-test/launch','POST',['playType'=>'real']);
        $response=app(\App\Http\Controllers\Cms\Front\ExternalGameController::class)->launch($r,$this->game,app(\App\Services\Games\CatalogReadService::class),app(\App\Services\Restrictions\ProductCountryRestrictionGuard::class));
        $this->assertSame(409,$response->getStatusCode());$this->assertSame($hash,$this->financeHash());
    }
}
