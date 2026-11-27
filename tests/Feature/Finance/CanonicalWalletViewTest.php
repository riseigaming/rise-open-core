<?php
namespace Tests\Feature\Finance;
use App\Models\{CmsUser,CmsCountry,CmsUserType,WalletAccount,WalletLedgerEntry,CmsUserTransaction};
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\{Hash,Http};
use Tests\TestCase;
class CanonicalWalletViewTest extends TestCase {
 use DatabaseTransactions;
 public function test_unprovisioned_customer_reads_wallet_without_financial_mutation():void {
  Http::preventStrayRequests();$u=new CmsUser;$u->forceFill(['username'=>'wallet_read_customer','email'=>'wallet-read@example.invalid','role'=>'customer','status'=>'normal','currency'=>'TRY','country_id'=>CmsCountry::value('id'),'user_type_id'=>CmsUserType::value('id'),'password'=>Hash::make('Wallet-View-927!')])->save();
  $this->postJson('/cms/login',['email'=>$u->username,'password'=>'Wallet-View-927!'],['X-Requested-With'=>'XMLHttpRequest'])->assertJsonPath('status',200);$this->withCookie(config('session.cookie'),session()->getId());
  $this->get('/cms/wallet')->assertOk()->assertSee('Promo code')->assertSee('Open canonical wallet');$this->assertSame(0,WalletAccount::count());$this->assertSame(0,CmsUserTransaction::count());$this->assertSame(0,WalletLedgerEntry::count());
 }
}
