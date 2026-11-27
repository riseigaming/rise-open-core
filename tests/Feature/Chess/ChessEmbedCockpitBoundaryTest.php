<?php
namespace Tests\Feature\Chess;
use Tests\TestCase;
use Tests\Support\BlackjackFixture;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\{Http,DB};
use App\Services\ChessWallet\{ChessEmbedService,ChessWalletReservationService};
use App\Services\ChessLiveCockpit\{ChessRecurringSurfaceOverlayService,ChessBettingMarketActionService};
use App\Models\{Provider,ChessLiveBoard,ChessBettingMarket,WalletLedgerEntry};

final class ChessEmbedCockpitBoundaryTest extends TestCase {
 use DatabaseTransactions,BlackjackFixture;
 protected function setUp():void{parent::setUp();$this->setupBlackjack();(new \Database\Seeders\HostedChessCatalogSeeder)->run();config(['chess_integration.front_enabled'=>true,'chess_integration.wallet_enabled'=>true,'chess_integration.admin_enabled'=>true,'chess_live_cockpit.sources.http.base_url'=>'http://127.0.0.1:18976','chess_live_cockpit.shadow_mode'=>true,'chess_live_cockpit.monitoring_only'=>false]);}
 private function embedConfig():array{return ['embed'=>['public_base_url'=>'http://127.0.0.1:18976','consume_path'=>'/embed/consume','entry_path'=>'/','partner_code'=>'unit-owned','exchange_endpoint'=>'http://127.0.0.1:18976/partner/session/exchange','requested_hostname'=>'127.0.0.1','key_id'=>'unit-key','secret'=>'unit-exchange-secret','bootstrap_secret'=>'unit-bootstrap-secret','parent_origin_override'=>'http://127.0.0.1:18970','post_message_origin'=>'http://127.0.0.1:18976','locale'=>['supported'=>['tr'=>'tr','en'=>'en','es'=>'es'],'fallback'=>'tr']]];}
 private function request():\Illuminate\Http\Request{$r=\Illuminate\Http\Request::create('http://127.0.0.1:18970/cms/providers/chess?redirect=https://evil.invalid');$r->setLaravelSession(request()->session());return $r;}
 public function test_source_authenticated_bootstrap_signature_wallet_identity_origin_safe_redirect_and_locale():void {
  $fake='http://127.0.0.1:18976/partner/session/exchange';Http::fake([$fake=>Http::response(['sessionToken'=>'opaque-owned-token','expiresAt'=>now()->addMinutes(2)->toAtomString()])]);app()->setLocale('en');
  app(\App\Services\Wallet\ActiveWalletService::class)->resolveForUser($this->player);$before=$this->financeHash();$data=app(ChessEmbedService::class)->buildShellFromConfig($this->player,$this->request(),$this->embedConfig());$p=$data['bootstrapPayload'];
  $this->assertSame((string)$this->player->id,$p['externalUserId']);$this->assertSame($this->wallet->id,$p['walletAccountId']);$this->assertSame('TRY',$p['walletCurrency']);$this->assertSame('en',$p['themeHints']['locale']);$this->assertSame(['public_betting','wallet_reserve'],$p['scope']);
  [$encoded,$sig]=explode('.',$data['bootstrapToken']);$json=base64_decode(strtr($encoded,'-_','+/'));$this->assertSame(hash_hmac('sha256',$json,'unit-bootstrap-secret'),$sig);$this->assertEquals($p,json_decode($json,true));$this->assertSame('http://127.0.0.1:18970',$data['parentOrigin']);$this->assertSame('http://127.0.0.1:18976',$data['postMessageOrigin']);$this->assertStringNotContainsString('evil.invalid',$data['iframeSrc']);$this->assertSame($before,$this->financeHash());
  Http::assertSent(fn($r)=>$r->url()===$fake&&$r['requestedSurfaceType']==='PUBLIC'&&$r['requestedRole']==='VIEWER'&&$r['externalUserId']===(string)$this->player->id&&$r['locale']==='en');
 }
 public function test_source_guest_shell_has_no_wallet_bootstrap_bridge_or_exchange():void {
  $config=$this->embedConfig();Provider::where('slug',config('chess_wallet.provider_slug'))->firstOrFail()->update(['config'=>$config]);$hash=$this->financeHash();$data=app(ChessEmbedService::class)->buildGuestShell($this->request());$this->assertNull($data['bootstrapToken']);$this->assertNull($data['bridgeToken']);$this->assertNull($data['walletContextRef']);$this->assertStringContainsString('mode=guest',$data['iframeSrc']);$this->assertArrayNotHasKey('walletAccountId',$data['bootstrapPayload']);Http::assertNothingSent();$this->assertSame($hash,$this->financeHash());
 }
 public function test_unsupported_wallet_rejected_before_hosted_exchange():void {
  app(\App\Services\Wallet\ActiveWalletService::class)->resolveForUser($this->player);config(['product_wallets.products.chess.supported_assets'=>[]]);$before=$this->financeHash();$this->expectException(\App\Services\Wallet\Exceptions\UnsupportedProductWalletException::class);try{app(ChessEmbedService::class)->buildShellFromConfig($this->player,$this->request(),$this->embedConfig());}finally{Http::assertNothingSent();$this->assertSame($before,$this->financeHash());}
 }
 public function test_cockpit_shadow_suspend_close_settle_and_void_never_gain_wallet_authority():void {
  app(ChessRecurringSurfaceOverlayService::class)->ensure();$board=ChessLiveBoard::where('external_board_key','cms-recurring-3-0-thursday')->firstOrFail();$market=$board->bettingMarkets()->firstOrFail();$service=app(ChessBettingMarketActionService::class);$hash=$this->financeHash();
  $service->suspend($market,$this->operator);app(ChessRecurringSurfaceOverlayService::class)->ensure();$this->assertSame('suspended',$market->fresh()->status);$this->assertTrue((bool)data_get($market->fresh()->meta,'shadow_mode'));
  $service->reopen($market->fresh(),$this->operator);$service->close($market->fresh(),$this->operator);$this->assertSame('closed',$market->fresh()->status);
  // Controlled source truth vector only in this deterministic feature test.
  $board->update(['official_result'=>'1-0','official_status'=>'finished']);$code=$market->selections()->value('selection_code');$service->settle($market->fresh(),$code,'official_board_result',null,$this->operator);$this->assertSame('settled',$market->fresh()->status);$this->assertSame($hash,$this->financeHash());$this->assertSame(0,DB::table('chess_wallet_reservations')->count());
  $other=ChessLiveBoard::where('external_board_key','cms-recurring-freestyle-friday')->firstOrFail()->bettingMarkets()->firstOrFail();$service->void($other,$this->operator,'Owned deterministic test');$this->assertSame('void',$other->fresh()->status);$this->assertSame($hash,$this->financeHash());
 }
 public function test_shadow_visibility_writes_and_customer_cockpit_denied():void {
  app(ChessRecurringSurfaceOverlayService::class)->ensure();$board=ChessLiveBoard::where('external_board_key','cms-recurring-3-0-thursday')->firstOrFail();$hash=$this->financeHash();
  $credentials=json_decode(file_get_contents(getenv('TEST_ADMIN_CREDENTIALS_FILE')),true);$this->withCredentials();$this->postJson('/cms/login',['email'=>$credentials['username'],'password'=>$credentials['password']],['X-Requested-With'=>'XMLHttpRequest'])->assertJsonPath('status',200);$this->withCookie(config('session.cookie'),session()->getId());$this->post('/cms/admin/betting/live-chess-cockpit/boards/'.$board->id.'/publish')->assertStatus(409);$this->assertSame($hash,$this->financeHash());
 }
 public function test_disabled_product_and_wallet_route_default_fail_closed():void {
  config(['chess_integration.wallet_enabled'=>false]);$context=['provider'=>Provider::where('slug',config('chess_wallet.provider_slug'))->firstOrFail(),'wallet_config'=>['partner_code'=>'unit-owned']];$hash=$this->financeHash();$this->assertSame(403,app(ChessWalletReservationService::class)->reserve(['externalUserId'=>(string)$this->player->id,'placementIntentId'=>'disabled','amount'=>'1.00'],$context,'r','key')['httpStatus']);$this->assertSame($hash,$this->financeHash());
 }
}
