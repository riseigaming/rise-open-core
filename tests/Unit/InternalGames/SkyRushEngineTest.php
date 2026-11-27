<?php
namespace Tests\Unit\InternalGames;
use PHPUnit\Framework\TestCase;
use App\Services\InternalGames\SkyRushEngine;
class SkyRushEngineTest extends TestCase {
 public function test_original_server_seed_commitment_and_inverse_flight_contract():void{$engine=new SkyRushEngine;$d=['house_edge'=>0.965,'min_crash_multiplier'=>1.08,'max_crash_multiplier'=>25,'growth_rate'=>0.55,'growth_scale'=>0.35];$round='igcr_unit_contract';$math=$engine->createRoundMath($d,$round);$this->assertSame(hash('sha256',$math['server_seed']),$math['server_seed_hash']);$this->assertSame(hash('sha256',$math['server_seed'].'|'.$round),$math['fair_hash']);$this->assertGreaterThanOrEqual(1.08,$math['crash_multiplier']);$this->assertLessThanOrEqual(25,$math['crash_multiplier']);foreach([1.08,1.5,2,5,10,25]as $multiplier)$this->assertEqualsWithDelta($multiplier,$engine->currentMultiplier($d,$engine->secondsForMultiplier($d,$multiplier)),0.005);$this->assertSame(1.0,$engine->currentMultiplier($d,-1));$this->assertSame(0.0,$engine->secondsForMultiplier($d,1));}
}
