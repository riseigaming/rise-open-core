<?php
namespace Tests\Support;
use App\Models\{CmsUser,CmsCountry,CmsUserType,CmsGame,WalletAccount,WalletLedgerEntry,CmsUserTransaction,InternalGameSession};
use App\Services\Wallet\AssetWalletService;
use App\Services\Finance\ManualFinanceOverrideService;
use App\Services\InternalGames\{BlackjackEngine,BlackjackService,InternalGameSessionService};
use Illuminate\Support\Facades\{DB,Hash,Http};
use Illuminate\Http\Request;
trait BlackjackFixture {
 protected CmsUser $player;protected CmsUser $operator;protected WalletAccount $wallet;
 protected function setupBlackjack(string $fund='500.00',bool $bind=true):void{
  Http::preventStrayRequests();(new \Database\Seeders\InternalBlackjackSeeder)->run();$this->operator=CmsUser::where('role','admin')->firstOrFail();$this->player=new CmsUser;$this->player->forceFill(['username'=>'bj_owner','email'=>'bj-owner@example.invalid','role'=>'customer','status'=>'normal','currency'=>'TRY','country_id'=>CmsCountry::value('id'),'user_type_id'=>CmsUserType::value('id'),'password'=>Hash::make('Blackjack-Local-927!')])->save();
  $this->wallet=DB::transaction(fn()=>app(AssetWalletService::class)->ensurePrimaryBalanceWallet($this->player,'TRY',true));if($fund!=='0')$this->credit($fund);
  if($bind)$this->browserRequest();
 }
 protected function browserRequest():Request{$store=app('session')->driver();$store->start();$request=Request::create('/cms/games/blackjack-classic/launch');$request->setLaravelSession($store);$this->app->instance('request',$request);return $request;}
 protected function credit(string $amount,string $key='bj-initial-funds',?WalletAccount $wallet=null):void{$wallet??=$this->wallet;app(ManualFinanceOverrideService::class)->execute(['operation_id'=>$key,'customer_id'=>$this->player->id,'wallet_account_id'=>$wallet->id,'currency'=>$wallet->asset,'economic_action'=>'manual_credit','external_resolution'=>'manual_operator_decision','amount'=>$amount,'reason'=>'Synthetic isolated Blackjack acceptance','evidence_reference'=>'local-test'],$this->operator);}
 protected function openBlackjack(string $code='blackjack-classic',bool $demo=false):InternalGameSession{return app(InternalGameSessionService::class)->launch(CmsGame::where('slug',$code)->firstOrFail(),$this->player,$demo,request());}
 protected function vector(array $p=['9H','6S'],array $d=['10C','7D'],array $deck=['4D','2C','3S','5H']):void{$engine=\Mockery::mock(BlackjackEngine::class)->makePartial();$engine->shouldReceive('createRoundState')->andReturnUsing(fn($def,$bet)=>BlackjackVectors::initial($def,$bet,$p,$d,$deck));$this->app->instance(BlackjackEngine::class,$engine);}
 protected function rejectBlackjack(callable $fn,string $code):void{try{$fn();$this->fail('Expected '.$code);}catch(\App\Exceptions\InternalGames\BlackjackException $e){$this->assertSame($code,$e->errorCode());}}
 protected function financeHash():string{$data=[];foreach(['wallet_accounts','wallet_ledger_entries','cms_user_transactions','finance_manual_overrides','cms_promotion_codes']as $table)$data[$table]=DB::table($table)->orderBy('id')->get()->all();$data['projection']=DB::table('cms_users')->orderBy('id')->get(['id','balance'])->all();return hash('sha256',json_encode($data));}
 protected function reconcileBlackjack():void{foreach(WalletAccount::all()as $wallet){$sum=\Brick\Math\BigDecimal::of(0);foreach(WalletLedgerEntry::where('wallet_account_id',$wallet->id)->orderBy('id')->get()as $entry){$this->assertTrue($sum->isEqualTo($entry->balance_before));$sum=$entry->direction==='credit'?$sum->plus($entry->amount):$sum->minus($entry->amount);$this->assertTrue($sum->isEqualTo($entry->balance_after));}$this->assertTrue($sum->isEqualTo($wallet->available_balance));}}
}
