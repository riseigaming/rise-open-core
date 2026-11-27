<?php
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;
use App\Services\ChessWallet\ChessWalletReservationService;
final class ChessWinPayoutValidationTest extends TestCase {
 public function test_win_amount_contract():void {
  $r=new \ReflectionClass(ChessWalletReservationService::class);$s=$r->newInstanceWithoutConstructor();$m=$r->getMethod('validateSettlementPayload');
  $base=['cmsReservationId'=>'validation-only','placedBetId'=>'validation-only','resultStatus'=>'WIN','settlementSummary'=>[],'settledAt'=>'2026-10-29T12:00:00Z'];
  foreach([[],['payoutAmount'=>null],['payoutAmount'=>'0'],['payoutAmount'=>'0.00'],['payoutAmount'=>'-1'],['payoutAmount'=>'bad'],['payoutAmount'=>'0.001'],['payoutAmount'=>'1e2'],['payoutAmount'=>[]],['payoutAmount'=>true],['payoutAmount'=>'10000000000000000']] as $v)$this->assertSame(422,$m->invoke($s,array_merge($base,$v))['httpStatus']??null);
  foreach(['0.01','22.53','9999999999999999.99',1,1.25] as $v)$this->assertNull($m->invoke($s,$base+['payoutAmount'=>$v]));
  foreach(['LOSS','VOID'] as $result)$this->assertNull($m->invoke($s,array_replace($base,['resultStatus'=>$result])));
 }
}
