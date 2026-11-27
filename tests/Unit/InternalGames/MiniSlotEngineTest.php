<?php
namespace Tests\Unit\InternalGames;
use PHPUnit\Framework\TestCase;
use App\Services\InternalGames\MiniSlotEngine;
class MiniSlotEngineTest extends TestCase {
 private function definitions():array{$all=require dirname(__DIR__,3).'/config/internal_games.php';return [$all['games']['big-win'],$all['games']['mini-slot-classic']];}
 public function test_original_two_definitions_keep_same_reels_paytable_and_three_line_math():void{[$a,$b]=$this->definitions();foreach(['symbols','bet_values','reel_strips','paytable','line_count']as $field)$this->assertSame($a[$field],$b[$field]);$this->assertSame([10,50,100],$a['bet_values']);$this->assertSame([203,152,291],array_column($a['paytable'],'Cherry'));$this->assertSame(3,$a['line_count']);foreach($a['reel_strips']as $strip)$this->assertCount(36,$strip);}
 public function test_all_original_pay_rules_and_loss_vectors_use_unchanged_engine():void{foreach($this->definitions()as $definition)foreach([[['Blank','Blank','Blank'],0],[['BAR','BAR','BAR'],156],[['2xBAR','2xBAR','2xBAR'],450],[['3xBAR','3xBAR','3xBAR'],1140],[['7','7','7'],2700],[['Cherry','Cherry','Cherry'],6460],[['7','Cherry','7'],510],[['BAR','2xBAR','3xBAR'],25.8],[['BAR','7','Cherry'],0]]as [$symbols,$expected]){$definition['reel_strips']=array_map(fn($symbol)=>[$symbol],$symbols);$r=(new MiniSlotEngine)->spin($definition,10);$this->assertSame((float)$expected,$r['winAmount']);$this->assertCount($expected>0?3:0,$r['winningLines']);$this->assertSame(3,$r['lineCount']);}}
 public function test_random_server_stops_are_always_contiguous_original_reel_windows():void{$definition=$this->definitions()[0];$windows=[];foreach($definition['reel_strips']as $index=>$strip){$n=count($strip);for($i=0;$i<$n;$i++)$windows[$index][]=[$strip[$i],$strip[($i+1)%$n],$strip[($i+2)%$n]];}$engine=new MiniSlotEngine;for($i=0;$i<100;$i++)foreach($engine->spin($definition,10)['grid']as $index=>$column)$this->assertContains($column,$windows[$index]);}
}
