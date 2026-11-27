<?php
namespace App\Http\Controllers\Cms\Front;
use App\Http\Controllers\Controller;
use App\Betting\AdapterVps\AdapterVpsClient;
use App\Betting\Support\{SportsbookShellPresentation,PublicSelectionTokenService};
use App\Models\BettingAcceptance;
use Illuminate\Http\Request;
class RiseSportsbookController extends Controller {
 private function mode(Request $r):string {$r->validate(['mode'=>'nullable|string|in:A,B,C']);$m=strtoupper((string)$r->query('mode','B'));abort_unless(in_array($m,['A','B','C'],true),422);return $m;}
 private function payload(string $mode):array {
  if($mode==='A') {abort_unless(app()->environment(['local','testing']),404);$p=storage_path('app/private/sportsbook-snapshot.json');abort_unless(is_file($p),503);$data=json_decode(file_get_contents($p),true,512,JSON_THROW_ON_ERROR);foreach($data['events'] as &$event)$event['offline_demo']=true;return $data;}
  $read=app(AdapterVpsClient::class)->currentOffers(2500);abort_unless(($read['status']??'')==='ok',503,'Sportsbook feed unavailable.');return (array)($read['body']??[]);
 }
 private function events(Request $r):array {
  $events=(array)data_get($this->payload($this->mode($r)),'events',[]);
  if($r->filled('event_ids')) {$ids=explode(',',(string)$r->query('event_ids'));$events=array_values(array_filter($events,fn($e)=>in_array((string)$e['event_id'],$ids,true)));}
  foreach($events as &$e)foreach($e['quote']['markets'] as &$m)foreach($m['selections'] as &$s){$s['selection_token']=!empty($e['offline_demo'])?null:app(PublicSelectionTokenService::class)->issueForAdapterSelection($e,$m,$s);if(!empty($e['offline_demo']))$s['acceptance_mode']='offline_demo';}
  return $events;
 }
 public function index(Request $r){
  $mode=$this->mode($r);if($mode==='C'){abort_unless(app(\App\Betting\Support\SportsbookLocalParityService::class)->financeSandboxEnabled(),503);app(\App\Services\Finance\PrelaunchFinanceWriteGate::class)->assertWritable();}$raw=(array)data_get($this->payload($mode),'events',[]);$board=app(SportsbookShellPresentation::class)->board($raw);
  $u=auth('cms')->user();$wallet=$u?app(\App\Services\Wallet\CanonicalWalletService::class)->resolveForUser($u,$u->currency?:'TRY'):null;
  $viewer=['id'=>$u?->id,'authenticated'=>(bool)$u,'balance'=>$wallet?->walletAccount()?(string)\Brick\Math\BigDecimal::of($wallet->walletAccount()->available_balance)->toScale(2):'0.00','currency'=>$u?->currency?:'TRY','formatted_balance'=>$wallet?->walletAccount()?(string)\Brick\Math\BigDecimal::of($wallet->walletAccount()->available_balance)->toScale(2):'0.00','wallet_snapshot_url'=>url('/cms/sportsbook/wallet'),'finance_report_url'=>url('/cms/wallet'),'deposit_url'=>url('/cms/wallet')];
  return view('cms.global.sportsbook',array_merge($board,compact('mode','viewer'),['offlineDemo'=>['active'=>$mode==='A','available'=>true],'recentAcceptances'=>app(SportsbookShellPresentation::class)->recentAcceptances($u),'hasOffers'=>count($raw)>0,'tabItems'=>[['key'=>'pre-match','label'=>'Pre-match','count'=>count($raw),'active'=>true],['key'=>'in-game','label'=>'Live','count'=>0,'active'=>false]],'financeReadOnly'=>$mode!=='C','livePolling'=>['enabled'=>false,'offers_index_url'=>url('/cms/api/betting/offers?mode='.$mode)],'initialPayloadDeferred'=>false]));
 }
 public function offers(Request $r){
  $r->validate(['sport_group_key'=>'nullable|string|max:40','phase_key'=>'nullable|string|max:40','league_filter_key'=>'nullable|string|max:191','event_ids'=>'nullable|string|max:1000','search'=>'nullable|string|max:120']);
  $events=$this->events($r);$presentation=app(SportsbookShellPresentation::class);$board=$presentation->board($events);$rows=collect($board['rows']);
  if($r->filled('sport_group_key')&&!in_array($r->query('sport_group_key'),['all','upcoming'],true))$rows=$rows->where('sport_group_key',$r->query('sport_group_key'));
  if($r->filled('phase_key'))$rows=$rows->where('phase_key',$r->query('phase_key'));
  if($r->filled('league_filter_key'))$rows=$rows->where('league_filter_key',$r->query('league_filter_key'));
  if($r->filled('search'))$rows=$rows->filter(fn($row)=>str_contains(mb_strtolower($row['event_name'].' '.$row['league_title']),mb_strtolower($r->query('search'))));
  if($r->query('time_priority')==='next')$rows=$rows->sortBy('starts_at_timestamp');
  $ids=$rows->pluck('event_id')->all();$events=array_values(array_filter($events,fn($e)=>in_array($e['event_id'],$ids,true)));
  foreach($events as &$e)foreach($e['quote']['markets'] as &$m)foreach($m['selections'] as &$selection)unset($selection['quote_ref']);
  $count=$rows->count();return response()->json(['success'=>true,'data'=>['events'=>$events,'shell_events'=>$rows->values()->all()],'error'=>null,'meta'=>['offline_demo'=>['active'=>$this->mode($r)==='A','available'=>true],'read_source'=>$this->mode($r)==='A'?'snapshot':'adapter_vps', 'generated_at'=>now()->toIso8601String(),'returned_count'=>$count,'visible_total'=>$count,'visible_total_authoritative'=>true,'has_more'=>false,'contract_state'=>'hydrated','provider_state'=>'healthy','surface'=>'full','summary'=>$board['offerSummary'],'counts'=>$presentation->liveOfferCountsPayload($board['offerSummary'],$rows),'pagination'=>['mode'=>'cursor','returned_count'=>$count,'visible_total'=>$count,'has_more'=>false,'next_cursor'=>null]]]);
 }
 public function event(Request $r,string $id){$r->query->set('event_ids',$id);return $this->offers($r);}
 public function catalog(Request $r){$r->validate(['sport_group_key'=>'nullable|string|max:40']);$board=app(SportsbookShellPresentation::class)->board((array)data_get($this->payload($this->mode($r)),'events',[]));$data=app(SportsbookShellPresentation::class)->catalogTreePayload($board['offerSummary'],(string)$r->query('sport_group_key',''));return response()->json(['success'=>true,'data'=>$data,'error'=>null]);}
 public function wallet(){ $u=auth('cms')->user();$service=app(\App\Services\Wallet\CanonicalWalletService::class);$context=$service->resolveForUser($u,$u->currency?:'TRY');$balance=$context?$service->currentDecimalBalance($context,false):'0.00';$exact=(string)\Brick\Math\BigDecimal::of($balance)->toScale(2);return response()->json(['balance'=>(float)$exact,'balanceExact'=>$exact,'formattedBalance'=>$exact,'currency'=>$context?->currency()?:$u->currency?:'TRY']);}
 public function history(){ $user=auth('cms')->id();return view('cms.global.sportsbook-history',['coupons'=>BettingAcceptance::where('cms_user_id',$user)->with('settlement')->latest('id')->paginate(30),'readModel'=>app(\App\Betting\Acceptance\BettingAcceptanceSnapshotReadModel::class)]);}
}
