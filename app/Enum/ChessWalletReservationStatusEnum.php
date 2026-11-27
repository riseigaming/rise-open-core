<?php

namespace App\Enum;

class ChessWalletReservationStatusEnum
{
    const CREATED = 'created';
    const RESERVED = 'reserved';
    const RELEASED = 'released';
    const SETTLED_WIN = 'settled_win';
    const SETTLED_LOSS = 'settled_loss';
    const SETTLED_VOID = 'settled_void';
    const FAILED = 'failed';
}
