<?php
namespace Tests\Support;
use App\Services\InternalGames\BlackjackEngine;
/** Test-only card vectors; all deal/action/score/payout rules run the unmodified original engine. */
class BlackjackVectors {
 public static function card(string $code):array{$suit=['H'=>'hearts','D'=>'diamonds','S'=>'spades','C'=>'clubs'][substr($code,-1)];$rank=substr($code,0,-1);return ['code'=>$code,'rank'=>$rank,'suit'=>$suit,'color'=>in_array($suit,['hearts','diamonds'])?'red':'black','value'=>$rank==='A'?11:(in_array($rank,['K','Q','J'])?10:(int)$rank)];}
 public static function initial(array $def,float $bet,array $player,array $dealer,array $deck):array{
  $engine=new BlackjackEngine;$state=$engine->createRoundState($def,$bet);$player=array_map(self::card(...),$player);$dealer=array_map(self::card(...),$dealer);
  $state=array_merge($state,['playerHand'=>$player,'dealerHand'=>$dealer,'remainingDeck'=>array_map(self::card(...),$deck),'phase'=>'player_turn','outcome'=>null,'version'=>1,'decisionCount'=>0,'doubled'=>false,'baseBet'=>$bet,'additionalBet'=>0.0,'totalStake'=>$bet,'payoutTotal'=>0.0,'net'=>0.0,'resultSummary'=>'','dealerRevealHoleCard'=>false,'requestRegistry'=>[],'actionTimeline'=>[['sequence'=>1,'type'=>'initial_deal','player_cards'=>array_column($player,'code'),'dealer_cards'=>array_column($dealer,'code'),'dealer_visible'=>[$dealer[0]['code']]]],'linkedTransactions'=>['bet'=>null,'additionalDebits'=>[],'settlement'=>null,'refund'=>null]]);
  $state=(new \ReflectionMethod($engine,'refreshState'))->invoke($engine,$state);return (new \ReflectionMethod($engine,'resolveInitialNaturals'))->invoke($engine,$state);
 }
}
