<?php

namespace App\Services\ChessLiveCockpit;

use InvalidArgumentException;

class ChessBettingMarketStateMachine
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_OPEN = 'open';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_AWAITING_RESULT = 'awaiting_result';
    public const STATUS_SETTLED = 'settled';
    public const STATUS_VOID = 'void';

    public const ACTION_OPEN = 'open';
    public const ACTION_SUSPEND = 'suspend';
    public const ACTION_REOPEN = 'reopen';
    public const ACTION_CLOSE = 'close';
    public const ACTION_SETTLE = 'settle';
    public const ACTION_VOID = 'void';
    public const ACTION_NOTE = 'note_update';
    public const ACTION_AUTO_AWAITING_RESULT = 'awaiting_result_sync';

    public const SETTLE_REASONS = [
        'official_board_result',
        'standings_confirmed',
        'bracket_confirmed',
        'arbiter_confirmation',
        'manual_override',
    ];

    public function statuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_OPEN,
            self::STATUS_SUSPENDED,
            self::STATUS_CLOSED,
            self::STATUS_AWAITING_RESULT,
            self::STATUS_SETTLED,
            self::STATUS_VOID,
        ];
    }

    public function labels(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_OPEN => 'Open',
            self::STATUS_SUSPENDED => 'Suspended',
            self::STATUS_CLOSED => 'Closed',
            self::STATUS_AWAITING_RESULT => 'Awaiting Result',
            self::STATUS_SETTLED => 'Settled',
            self::STATUS_VOID => 'Void',
        ];
    }

    public function transition(string $currentState, string $action): string
    {
        $map = [
            self::ACTION_OPEN => [
                self::STATUS_DRAFT => self::STATUS_OPEN,
            ],
            self::ACTION_SUSPEND => [
                self::STATUS_OPEN => self::STATUS_SUSPENDED,
            ],
            self::ACTION_REOPEN => [
                self::STATUS_SUSPENDED => self::STATUS_OPEN,
            ],
            self::ACTION_CLOSE => [
                self::STATUS_OPEN => self::STATUS_CLOSED,
                self::STATUS_SUSPENDED => self::STATUS_CLOSED,
            ],
            self::ACTION_SETTLE => [
                self::STATUS_CLOSED => self::STATUS_SETTLED,
                self::STATUS_AWAITING_RESULT => self::STATUS_SETTLED,
            ],
            self::ACTION_VOID => [
                self::STATUS_OPEN => self::STATUS_VOID,
                self::STATUS_SUSPENDED => self::STATUS_VOID,
                self::STATUS_CLOSED => self::STATUS_VOID,
                self::STATUS_AWAITING_RESULT => self::STATUS_VOID,
            ],
            self::ACTION_AUTO_AWAITING_RESULT => [
                self::STATUS_CLOSED => self::STATUS_AWAITING_RESULT,
            ],
        ];

        $nextState = $map[$action][$currentState] ?? null;

        if ($nextState === null) {
            throw new InvalidArgumentException('Invalid market transition [' . $currentState . ' -> ' . $action . '].');
        }

        return $nextState;
    }

    public function canTransition(string $currentState, string $action): bool
    {
        try {
            $this->transition($currentState, $action);

            return true;
        } catch (InvalidArgumentException $exception) {
            return false;
        }
    }

    public function requiresSettleNote(string $reason): bool
    {
        return $reason === 'manual_override';
    }

    public function validateSettleReason(string $reason): void
    {
        if (!in_array($reason, self::SETTLE_REASONS, true)) {
            throw new InvalidArgumentException('Invalid settle reason [' . $reason . '].');
        }
    }
}
