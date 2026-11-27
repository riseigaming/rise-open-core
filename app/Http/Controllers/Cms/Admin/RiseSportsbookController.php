<?php
namespace App\Http\Controllers\Cms\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{BettingAcceptance,BettingSettlement};
use App\Betting\Acceptance\AcceptanceService;
use App\Betting\Settlement\{SettlementService,PendingBettingSettlementReadService};
class RiseSportsbookController extends Controller {
 public function index(Request $r){
  $pending=app(PendingBettingSettlementReadService::class);$exceptions=app(\App\Betting\Admin\SettlementExceptionsReadService::class);$filters=$exceptions->filtersFromRequest($r);
  $ledger=app(\App\Betting\Admin\BetControlLedgerReadService::class);
  return view('cms.admin.sportsbook.index',['feed'=>app(\App\Betting\AdapterVps\AdapterVpsClient::class)->smoke(),'rows'=>BettingAcceptance::with('settlement')->when($r->filled('acceptance'),fn($q)=>$q->whereKey($r->integer('acceptance')))->latest('id')->paginate(30)->through(fn($a)=>$pending->row($a)),'pendingCount'=>$pending->countPending(),'exceptions'=>$exceptions->summary($filters),'ledger'=>$ledger->summary(),'catalog'=>(array)data_get(app(\App\Betting\AdapterVps\AdapterVpsClient::class)->currentOffers(),'body.events',[]),'ledgerRows'=>$ledger->paginate($ledger->filters($r->all()),30),'exceptionRows'=>$exceptions->items($filters)]);
 }
 public function showAcceptance(Request $r,int $id){$r->query->set('acceptance',$id);return $this->index($r);}
 public function showSettlement(Request $r,int $id){$r->query->set('acceptance',BettingSettlement::findOrFail($id)->betting_acceptance_id);return $this->index($r);}
 public function review(Request $r, int $id)
 {
     $v = $r->validate(['decision' => 'required|in:accept,reject,cancel', 'reason' => 'required|string|min:5|max:1000']);
     $a = BettingAcceptance::findOrFail($id);
     try {
         app(AcceptanceService::class)->review($a, $v['decision'], $v['reason'], auth('cms')->user());
     } catch (\App\Betting\Exceptions\AcceptanceReviewConflictException $e) {
         abort(409, $e->getMessage());
     }
     return $r->expectsJson() ? response()->json(['success' => true, 'id' => $id]) : back();
 }
 public function settle(Request $r,int $id){abort_unless(config('betting.sportsbook_result_settlement_enabled'), 403);$v=$r->validate(['result'=>'required|in:won,lost,void,cancelled,refunded,postponed','reason'=>'required|string|min:5|max:1000']);$s=BettingSettlement::findOrFail($id);try{$s=app(SettlementService::class)->review($s,$v['result'],$v['reason'],auth('cms')->user());}catch(\App\Betting\Exceptions\SettlementAlreadyFinalizedException $e){return response()->json(['success'=>false,'code'=>'SETTLEMENT_ALREADY_FINALIZED'],409);}catch(\RuntimeException $e){return response()->json(['success'=>false,'code'=>'SETTLEMENT_NOT_READY','message'=>$e->getMessage()],409);}return $r->expectsJson()?response()->json(['success'=>true,'id'=>$s->id,'result'=>$s->result_code,'return_amount'=>$s->return_amount]):back();}
}
