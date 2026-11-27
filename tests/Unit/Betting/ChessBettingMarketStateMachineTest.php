<?php

namespace Tests\Unit\Betting;

use App\Services\ChessLiveCockpit\ChessBettingMarketStateMachine;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ChessBettingMarketStateMachineTest extends TestCase
{
    public function test_close_and_settle_are_distinct_transitions(): void
    {
        $machine = new ChessBettingMarketStateMachine();

        $this->assertSame('closed', $machine->transition('open', 'close'));
        $this->assertSame('settled', $machine->transition('closed', 'settle'));
    }

    public function test_settled_market_cannot_reopen(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $machine = new ChessBettingMarketStateMachine();
        $machine->transition('settled', 'reopen');
    }

    public function test_manual_override_requires_note(): void
    {
        $machine = new ChessBettingMarketStateMachine();

        $this->assertTrue($machine->requiresSettleNote('manual_override'));
        $this->assertFalse($machine->requiresSettleNote('official_board_result'));
    }
}
