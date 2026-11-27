<?php
namespace Tests\Feature\InternalGames;

use App\Models\{CmsGame,CmsUser,CmsCountry,CmsUserType,CmsUserProductAccessOverride,InternalGameSession,InternalGameRound,InternalGameRoundEvent};
use App\Services\InternalGames\{DiceEngine,InternalGameAuditService};
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\{Cache,DB,Hash,Http,Schema};
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Support\BlackjackFixture;

final class DiceDemoTest extends TestCase
{
    use DatabaseTransactions, BlackjackFixture;
    private string $csrf;
    private CmsGame $game;
    protected function setUp(): void
    {
        parent::setUp();
        $this->withCredentials();
        Http::preventStrayRequests();
        $this->app->bind(\App\Http\Middleware\VerifyCsrfToken::class, StrictDemoCsrf::class);
        (new \Database\Seeders\InternalDiceDemoSeeder())->run();
        $this->game=CmsGame::with('provider')->where('slug','dice')->firstOrFail();
        $this->csrf=Str::random(40);
        $this->withSession(['_token'=>$this->csrf]);
        $this->withCookie(config('session.cookie'),session()->getId());
    }
    private function loginPlayer(): void
    {
        if (isset($this->player)) {
            return;
        }
        $this->setupBlackjack('100.00', false);
        $this->postJson('/cms/login', ['_token' => $this->csrf, 'email' => $this->player->username,
            'password' => 'Blackjack-Local-927!'], ['X-Requested-With' => 'XMLHttpRequest'])->assertJsonPath('status', 200);
        $this->csrf = session()->token();
        $this->withCookie(config('session.cookie'), session()->getId());
    }
    private function launch(): InternalGameSession
    {
        $this->loginPlayer();
        $this->post('/cms/games/dice/launch',['_token'=>$this->csrf,'playType'=>'demo'])->assertRedirect();
        $session=InternalGameSession::latest('id')->firstOrFail();
        $this->withCookie(config('session.cookie'),session()->getId());
        $this->assertSame(data_get($session->launch_payload,'dice_browser_hash'),hash('sha256',session()->getId()),'Browser hash after launch');
        return $session;
    }
    private function action(InternalGameSession $s,array $override=[])
    {
        return $this->postJson('/cms/internal-games/dice/roll',array_replace(['_token'=>$this->csrf,
            'sessionToken'=>$s->session_token,'requestKey'=>'dice-unit-request','betAmount'=>'0.20','target'=>'50.00','mode'=>'over'],$override));
    }
    private function snapshot(InternalGameSession $s)
    {
        return $this->getJson('/cms/internal-games/dice/snapshot?sessionToken='.$s->session_token);
    }
    public function test_global_demo_label_two_canonical_rounds_history_and_reload(): void
    {
        $this->get('/casino?q=Dice')->assertOk()->assertSee('Dice');
        $this->get('/cms/games/dice')->assertOk()->assertSee('name="playType" value="demo"',false);
        $s=$this->launch();$expiry=$s->expires_at->toIso8601String();
        $this->assertFalse($s->is_demo);$this->assertSame($this->player->id,(int)$s->cms_user_id);$this->assertSame('TRY',$s->currency);
        $first=$this->action($s)->assertOk()->assertJsonPath('data.betAmount',0.2)->json('data');
        $second=$this->action($s,['requestKey'=>'dice-unit-request-two','mode'=>'under','betAmount'=>'0.30'])->assertOk()->json('data');
        $this->assertNotSame($first['roundId'],$second['roundId']);
        $this->assertSame((int)round(($first['balance']-0.30+$second['winAmount'])*100),(int)round($second['balance']*100));
        foreach([$first,$second]as $r){$target=(int)round($r['result']['target']*100);$roll=(int)round($r['result']['rolled']*100);$math=(new DiceEngine)->settle((int)round($r['betAmount']*100),$r['result']['mode'],$target,$roll);$this->assertSame($math['won'],$r['result']['won']);$this->assertSame($math['payoutMinor'],(int)round($r['winAmount']*100));}
        $this->snapshot($s)->assertOk()->assertJsonCount(2,'data.history')->assertJsonPath('data.currency','TRY');
        $this->get('/cms/internal-games/play/'.$s->session_token)->assertOk()->assertSee('data-history',false)->assertSee(trans('dice.real_note'),false)->assertHeader('Referrer-Policy','no-referrer');
        $this->assertSame($expiry,$s->fresh()->expires_at->toIso8601String());
        $this->assertSame(2,InternalGameRound::count());$this->assertSame(3,InternalGameRoundEvent::count());
        $this->assertSame(2, \App\Models\WalletLedgerEntry::where('direction','debit')->count());
        $this->reconcileBlackjack();
    }
    public function test_replay_is_identical_and_conflicting_replay_has_no_second_round_or_audit(): void
    {
        $s=$this->launch();$first=$this->action($s)->assertOk()->json();
        $this->action($s)->assertOk()->assertExactJson($first);
        $this->action($s,['betAmount'=>'0.30'])->assertStatus(409);
        $this->assertSame(1,InternalGameRound::count());$this->assertSame(2,InternalGameRoundEvent::count());
    }
    public function test_foreign_browser_and_foreign_account_cannot_read_or_roll_session(): void
    {
        $s=$this->launch();$original=session()->getId();session()->setId(Str::random(40));session()->start();session()->put('_token',$this->csrf);
        $this->withCookie(config('session.cookie'),session()->getId());
        $this->snapshot($s)->assertForbidden();$this->action($s)->assertForbidden();
        $this->assertSame(0,InternalGameRound::count());
        session()->setId($original);session()->start();$this->withCookie(config('session.cookie'),$original);
        $s->update(['launch_payload'=>array_replace($s->launch_payload,['requested_user_id'=>999999])]);
        $this->snapshot($s)->assertForbidden();
    }
    public function test_expired_closed_hidden_and_maintenance_sessions_fail_without_rounds(): void
    {
        $s=$this->launch();$s->update(['expires_at'=>now()->subSecond()]);$this->action($s)->assertStatus(410);$this->snapshot($s)->assertStatus(410);
        $s->update(['expires_at'=>now()->addMinute(),'status'=>'closed']);$this->action($s)->assertStatus(410);
        $s->update(['status'=>'active']);$this->game->update(['is_maintenance'=>true]);$this->action($s)->assertStatus(503);
        $this->game->update(['is_maintenance'=>false,'status'=>'passive']);$this->action($s)->assertNotFound();
        $this->assertSame(0,InternalGameRound::count());$this->assertSame(1,InternalGameRoundEvent::count());
    }
    public function test_provider_maintenance_hidden_and_safe_contract_revocation_are_consumed_immediately(): void
    {
        $s=$this->launch();$p=$this->game->provider;$p->update(['is_maintenance'=>true]);$this->action($s)->assertStatus(503);
        $p->update(['is_maintenance'=>false,'show_left_panel'=>false]);$this->action($s)->assertNotFound();
        $p->update(['show_left_panel'=>true]);config(['internal_games.demo_mode.enabled'=>false]);$this->postJson('/cms/games/dice/launch',['_token'=>$this->csrf,'playType'=>'demo'])->assertServiceUnavailable();
        $this->assertSame(0,InternalGameRound::count());
    }
    public function test_real_mode_forged_result_and_wallet_parameters_are_rejected(): void
    {
        $this->postJson('/cms/games/dice/launch',['_token'=>$this->csrf,'playType'=>'real'])->assertForbidden();
        $s=$this->launch();foreach([['wallet'=>['id'=>1]],['is_demo'=>false],['currency'=>'TRY'],['balance'=>1000],['result'=>['won'=>true]],['betAmount'=>'0.201'],['target'=>'99.99'],['mode'=>'fake']]as $extra)$this->action($s,$extra)->assertUnprocessable();
        $this->assertSame(0,InternalGameRound::count());
        try{$s->update(['is_demo'=>true]);$this->fail('DB must reject an invalid virtual conversion');}catch(\Illuminate\Database\QueryException){$this->assertFalse($s->fresh()->is_demo);}
    }
    public function test_csrf_is_enforced_for_roll_and_launch(): void
    {
        $this->post('/cms/games/dice/launch',['playType'=>'demo'])->assertStatus(419);
        $s=$this->launch();$this->postJson('/cms/internal-games/dice/roll',['sessionToken'=>$s->session_token,'requestKey'=>'dice-no-csrf','betAmount'=>'0.20','mode'=>'over','target'=>'50.00'])->assertStatus(419);
        $this->assertSame(0,InternalGameRound::count());
    }
    public function test_insufficient_wallet_and_busy_lock_create_no_rounds(): void
    {
        $s=$this->launch();$lock=Cache::lock('internal-dice-roll:'.$s->id,10);$this->assertTrue($lock->get());
        try{$this->action($s)->assertStatus(409);}finally{$lock->release();}
        app(\App\Services\Finance\ManualFinanceOverrideService::class)->execute([
            'operation_id'=>'dice-run30-drain','customer_id'=>$this->player->id,'wallet_account_id'=>$this->wallet->id,
            'currency'=>'TRY','economic_action'=>'manual_debit','external_resolution'=>'manual_operator_decision',
            'amount'=>'100.00','reason'=>'Synthetic Dice insufficient wallet case'], $this->operator);
        $this->action($s)->assertUnprocessable();$this->assertSame(0,InternalGameRound::count());
    }
    public function test_round_audit_failure_rolls_back_atomic_writes(): void
    {
        $s=$this->launch();$audit=\Mockery::mock(InternalGameAuditService::class);$audit->shouldReceive('logRound')->once()->andThrow(new \RuntimeException('audit fixture failure'));$this->app->instance(InternalGameAuditService::class,$audit);
        $this->withoutExceptionHandling();try{$this->action($s);$this->fail('Audit must fail');}catch(\RuntimeException $e){$this->assertSame('audit fixture failure',$e->getMessage());}finally{$this->withExceptionHandling();}
        $this->assertSame(0,InternalGameRound::count());$this->assertSame(1,InternalGameRoundEvent::count());
    }
    public function test_authenticated_demo_rechecks_user_product_provider_and_country_access(): void
    {
        $session = $this->launch();
        $u = $this->player;
        $this->assertSame((int) $u->id, (int) data_get($session->launch_payload, 'requested_user_id'));
        $this->action($session)->assertOk();
        $deny=CmsUserProductAccessOverride::create(['cms_user_id'=>$u->id,'product_key'=>'internal_games','provider_alias'=>'','access_mode'=>'deny','reason'=>'Local demo access revocation']);
        $this->snapshot($session)->assertNotFound();$this->action($session)->assertNotFound();$deny->delete();
        $deny=CmsUserProductAccessOverride::create(['cms_user_id'=>$u->id,'product_key'=>'internal_games','provider_alias'=>'internal-games','access_mode'=>'deny','reason'=>'Local provider revocation']);
        $this->action($session)->assertNotFound();$deny->delete();
        $policy = app(\App\Services\Restrictions\CentralRestrictionPolicyService::class)->settings();
        $policy['countries'][(string) $u->country_id] = ['products'=>['casino'], 'reason'=>'Synthetic country case'];
        $row = \App\SiteSettings::updateOrCreate(['key'=>\App\Services\Restrictions\CentralRestrictionPolicyService::SETTINGS_KEY],
            ['value'=>json_encode($policy)]);
        $this->action($session)->assertNotFound();
        $row->delete();
        $this->snapshot($session)->assertOk();$this->assertSame(1,InternalGameRound::count());
    }
    public function test_launch_audit_failure_rolls_back_the_session(): void
    {
        $this->loginPlayer();
        $audit=\Mockery::mock(InternalGameAuditService::class);$audit->shouldReceive('logSession')->once()->andThrow(new \RuntimeException('launch audit fixture failure'));
        $this->app->instance(InternalGameAuditService::class,$audit);$this->withoutExceptionHandling();
        try{$this->post('/cms/games/dice/launch',['_token'=>$this->csrf,'playType'=>'demo']);$this->fail('Launch audit must fail');}
        catch(\RuntimeException $e){$this->assertSame('launch audit fixture failure',$e->getMessage());}
        finally{$this->withExceptionHandling();}
        $this->assertSame(0,InternalGameSession::count());$this->assertSame(0,InternalGameRoundEvent::count());
    }
    public function test_named_roll_throttle_is_separate_from_snapshot_and_blocks_the_twenty_first_action(): void
    {
        $session=$this->launch();for($i=0;$i<25;$i++)$this->snapshot($session)->assertOk();
        for($i=0;$i<20;$i++)$this->action($session)->assertOk();
        $this->action($session)->assertStatus(429);$this->assertSame(1,InternalGameRound::count());
        $this->snapshot($session)->assertOk();
    }
    public function test_seeder_preserves_disabled_state_and_rejects_identity_collision(): void
    {
        $this->game->update(['status'=>'passive']);(new \Database\Seeders\InternalDiceDemoSeeder)->run();$this->assertSame('passive',$this->game->fresh()->status);
        $this->game->update(['reference_id'=>'foreign:identity']);
        $this->expectException(\RuntimeException::class);(new \Database\Seeders\InternalDiceDemoSeeder)->run();
    }
    public function test_session_history_is_isolated_and_bounded_to_twenty_rows(): void
    {
        $one=$this->launch();$this->action($one)->assertOk();$two=$this->launch();$this->snapshot($two)->assertJsonCount(0,'data.history');
        $prototype=InternalGameRound::firstOrFail();for($i=0;$i<22;$i++){$r=$prototype->replicate();$r->round_id='history-'.$i;$r->request_ref='history-'.$i;$r->save();}
        $this->snapshot($one)->assertJsonCount(20,'data.history');
    }
}
class StrictDemoCsrf extends \App\Http\Middleware\VerifyCsrfToken
{
    protected function runningUnitTests(): bool { return false; }
}
