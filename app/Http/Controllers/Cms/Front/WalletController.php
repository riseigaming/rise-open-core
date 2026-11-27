<?php
namespace App\Http\Controllers\Cms\Front;
use App\Http\Controllers\Controller;
use App\Models\{CmsUser,WalletAccount,WalletLedgerEntry,CmsUserTransaction,InternalGameRound};
use App\Services\Wallet\{AssetWalletService,ActiveWalletService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class WalletController extends Controller {
 public function index(Request $r,AssetWalletService $assets,ActiveWalletService $active) {
  $user=auth('cms')->user();$owner=$assets->resolveOwnerUser($user);
  return view('cms.global.wallet',['activeWalletId'=>$active->existingForUser($user)?->id,'wallets'=>WalletAccount::where('cms_user_id',$owner->id)->get(),'transactions'=>CmsUserTransaction::whereIn('cms_user_id',CmsUser::where('id',$owner->id)->orWhere('parent_id',$owner->id)->pluck('id'))->latest('id')->limit(30)->get(),'ledger'=>WalletLedgerEntry::where('cms_user_id',$owner->id)->latest('id')->limit(30)->get()]);
 }
 public function provision(Request $r,AssetWalletService $assets){$r->validate(['currency'=>'required|in:TRY,USD,EUR']);$wallet=DB::transaction(fn()=>$assets->ensurePrimaryBalanceWallet(auth('cms')->user(),$r->input('currency'),true));return $r->expectsJson()?response()->json(['status'=>200,'walletId'=>$wallet->id]):back();}
 public function switch(Request $r,ActiveWalletService $active){$data=$r->validate(['wallet_account_id'=>'required|integer']);try{$value=DB::transaction(fn()=>$active->switch(auth('cms')->user(),(int)$data['wallet_account_id']));}catch(\App\Services\Wallet\Exceptions\ActiveWalletChangeBlockedException $e){$value=['status'=>409,'message'=>'Wallet selection is blocked by an open round or pending financial operation.','reasons'=>$e->reasons()];}if($r->expectsJson())return response()->json($value,$value['status']);return $value['status']===200?back()->with('wallet_status','Active wallet updated.'):back()->withErrors(['wallet'=>'Wallet selection rejected.']);}
}
