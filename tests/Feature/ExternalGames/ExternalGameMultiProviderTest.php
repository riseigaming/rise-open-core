<?php

namespace Tests\Feature\ExternalGames;

use App\Models\{CmsGame, CmsUserTransaction, ExternalGameSession, Provider, WalletLedgerEntry};
use App\Services\ExternalGames\{ExternalGameAvailabilityService, ExternalGameCallbackFinanceReconciliationService, ExternalGameCallbackHealthService, ExternalGameCatalogImportService, ExternalGameLaunchBlockedException, ExternalGameLaunchService, ExternalGameProviderConfig, ExternalGameProviderRolloutService, ExternalGamesControlSettingsService};
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\Support\BlackjackFixture;
use Tests\TestCase;

final class ExternalGameMultiProviderTest extends TestCase
{
    use DatabaseTransactions, BlackjackFixture;

    private const A = ExternalGameProviderConfig::PRIMARY;
    private const B = 'secondary-external-provider';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupBlackjack();
        app(\App\Services\ProductAccess\ProductAccessPolicyService::class)
            ->upsertOverride($this->player, 'external_games', '', 'allow', 'Run31 fixture approval');
        app(ExternalGamesControlSettingsService::class)->persist([
            'enabled'=>true, 'demo_enabled'=>true, 'emergency_stop'=>false, 'pilot_policy'=>'default_open',
        ], $this->operator);

        config(['external_games.local_contract_enabled'=>true]);
        foreach (['a'=>[self::A, 18971, 'primary'], 'b'=>[self::B, 18972, 'secondary']] as $tag=>[$alias,$port,$public]) {
            $prefix = "external_games.providers.{$alias}";
            config([
                "{$prefix}.enabled"=>true, "{$prefix}.public_alias"=>$public,
                "{$prefix}.api_base_url"=>"http://127.0.0.1:{$port}", "{$prefix}.agent_code"=>"code-{$tag}",
                "{$prefix}.agent_token"=>"token-{$tag}", "{$prefix}.agent_secret"=>"secret-{$tag}",
                "{$prefix}.supported_currencies"=>['TRY'], "{$prefix}.allowed_countries"=>['TR'],
            ]);
        }
    }

    private function upstream(bool $failA = false): void
    {
        Http::fake(function ($request) use ($failA) {
            $port = parse_url($request->url(), PHP_URL_PORT);
            $tag = match ($port) { 18971 => 'a', 18972 => 'b' };
            $path = parse_url($request->url(), PHP_URL_PATH);
            if ($path === '/api/v1/auth/authentication') {
                self::assertTrue($request->hasHeader('X-Authorization', 'Bearer '.base64_encode("token-{$tag}:secret-{$tag}")));
                return Http::response($tag === 'a' && $failA ? [] : ['access_token'=>"access-{$tag}"], $tag === 'a' && $failA ? 503 : 200);
            }
            self::assertTrue($request->hasHeader('X-Authorization', 'Bearer access-'.$tag));
            if ($path === '/api/v1/games/all') {
                return Http::response(['status'=>1,'games'=>[[
                    'game_id'=>'identical-game', 'game_name'=>'Identical title',
                    'provider_id'=>'identical-vendor','status'=>1,'category'=>'slot',
                ]]]);
            }
            self::assertSame('/api/v1/games/game_launch', $path);
            self::assertSame('code-'.$tag, $request['agent_code']);
            self::assertSame('token-'.$tag, $request['agent_token']);
            return Http::response(['status'=>1,'launch_url'=>"http://127.0.0.1:{$port}/frame/{$tag}"]);
        });
    }

    private function catalog(string $alias): CmsGame
    {
        app(ExternalGameCatalogImportService::class)->sync($alias);
        $provider = Provider::where('slug',$alias)->sole();
        $provider->update(['show_left_panel'=>true]);
        $game = CmsGame::where('provider_id',$provider->id)->sole();
        $game->update(['is_restricted'=>false]);
        return $game->fresh('provider');
    }

    private function deliverCallback(string $route, string $tag, ExternalGameSession $session, string $method, array $extra = [])
    {
        $amountKey = match ($method) { 'transaction_bet' => 'bet', 'transaction_win' => 'win', 'refund' => 'amount' };
        return $this->postJson("/integrations/games/{$route}/wallet", array_replace([
            'method'=>$method,'agent_code'=>"code-{$tag}",'agent_token'=>"token-{$tag}",'agent_secret_key'=>"secret-{$tag}",
            'session_token'=>$session->session_token,'user_id'=>$this->player->id,'currency'=>'TRY',
            'game'=>'identical-game','round_id'=>'shared-round','transaction_id'=>'shared-'.$method,
            $amountKey=>'1.00',
        ],$extra))->assertOk();
    }

    public function test_two_connectors_isolate_catalog_session_callbacks_and_reconciliation(): void
    {
        $this->upstream();
        $gameA = $this->catalog(self::A);
        $gameB = $this->catalog(self::B);
        self::assertNotSame($gameA->slug,$gameB->slug);
        self::assertSame($gameA->reference_id,$gameB->reference_id);
        self::assertNotSame($gameA->provider_id,$gameB->provider_id);
        $rollout = app(ExternalGameProviderRolloutService::class);
        $rollout->setProviderState('identical-vendor','maintenance');
        self::assertFalse($rollout->decisionForGame($gameA)['launchable']);
        self::assertTrue($rollout->decisionForGame($gameB)['launchable']);
        $rollout->setProviderState('identical-vendor','active');

        $launch = app(ExternalGameLaunchService::class);
        $a = $launch->launch($gameA,$this->player);
        $b = $launch->launch($gameB,$this->player);
        self::assertSame(self::A,$a->provider_alias);
        self::assertSame(self::B,$b->provider_alias);
        self::assertNotSame($a->session_token,$b->session_token);
        self::assertSame($a->wallet_account_id,$b->wallet_account_id);
        $before = $this->financeHash();
        $this->deliverCallback('secondary','a',$b,'transaction_bet')->assertJsonPath('status',0);
        $this->deliverCallback('primary','a',$b,'transaction_bet',['provider_alias'=>self::B])->assertJsonPath('status',0);
        $this->deliverCallback('secondary','b',$a,'transaction_bet')->assertJsonPath('status',0);
        $this->deliverCallback('secondary','b',$b,'transaction_bet',['currency'=>'USD'])->assertJsonPath('status',0);
        self::assertSame($before,$this->financeHash());

        $this->deliverCallback('primary','a',$a,'transaction_bet')->assertJsonPath('status',1);
        $this->deliverCallback('secondary','b',$b,'transaction_win')->assertJsonPath('status',0);
        $this->deliverCallback('secondary','b',$b,'refund')->assertJsonPath('status',0);
        $this->deliverCallback('secondary','b',$b,'transaction_bet')->assertJsonPath('status',1);
        foreach (['transaction_win','refund'] as $method) {
            $this->deliverCallback('primary','a',$a,$method)->assertJsonPath('status',1);
            $this->deliverCallback('secondary','b',$b,$method)->assertJsonPath('status',1);
        }
        $applied = $this->financeHash();
        foreach (['transaction_bet','transaction_win','refund'] as $method) {
            $this->deliverCallback('primary','a',$a,$method)->assertJsonPath('status',1);
            $this->deliverCallback('secondary','b',$b,$method)->assertJsonPath('status',1);
        }
        self::assertSame($applied,$this->financeHash());
        self::assertSame('502.00000000',$this->wallet->fresh()->available_balance);
        $this->reconcileBlackjack();

        $finance = app(ExternalGameCallbackFinanceReconciliationService::class);
        self::assertSame('ok',$finance->report()['finance_integrity_status']);
        foreach ([self::A,self::B] as $alias) {
            $report = $finance->forProvider($alias)->report();
            self::assertSame('ok',$report['finance_integrity_status']);
            self::assertSame(3,$report['external_transaction_count']);
            self::assertSame(3,$report['external_ledger_count']);
            foreach (['transaction_bet','transaction_win','refund'] as $method) {
                $key = "external_games:{$alias}:{$method}:shared-{$method}";
                self::assertSame($this->wallet->id,(int) CmsUserTransaction::where('transactionId',$key)->sole()->source_wallet_account_id);
                self::assertSame($alias,WalletLedgerEntry::where('idempotency_key',$key)->sole()->meta['financial_identity']['provider_alias']);
            }
        }
        $health = app(ExternalGameCallbackHealthService::class);
        self::assertGreaterThan($health->summary()['callback_failures_24h'], $health->forProvider(self::B)->summary()['callback_failures_24h']);
    }

    public function test_primary_outage_country_and_asset_policy_leave_secondary_independent(): void
    {
        $this->upstream(true);
        try {
            $this->catalog(self::A);
            self::fail('A outage must fail');
        } catch (\App\Services\ExternalGames\ExternalGameProviderLaunchException) {
            self::assertFalse(Provider::where('slug',self::A)->exists());
        }
        $game = $this->catalog(self::B);
        $availability = app(ExternalGameAvailabilityService::class);
        config(['external_games.providers.'.self::B.'.allowed_countries'=>['ES']]);
        try {
            $availability->assertLaunchable($game,$this->player,false);
            self::fail('Provider country rule must apply');
        } catch (ExternalGameLaunchBlockedException $exception) {
            self::assertSame('provider_country_unsupported',$exception->reasonCode);
        }
        config(['external_games.providers.'.self::B.'.allowed_countries'=>['TR']]);
        $b = app(ExternalGameLaunchService::class)->launch($game,$this->player);
        self::assertSame(self::B,$b->provider_alias);
        config(['external_games.providers.'.self::B.'.supported_currencies'=>['USD']]);
        $before = $this->financeHash();
        $this->deliverCallback('secondary','b',$b,'transaction_bet')->assertJsonPath('status',0);
        self::assertSame($before,$this->financeHash());
        config(['external_games.providers.'.self::B.'.supported_currencies'=>['TRY']]);
        $this->deliverCallback('secondary','b',$b,'transaction_bet')->assertJsonPath('status',1);
    }
}
