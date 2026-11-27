<?php

namespace App\Enum;

class TransactionActionEnum
{
    const Failed = 0;
    const Deposit = 1;
    const Withdraw = 2;
    const Promotion = 3;
    const Bet = 4;
    const Win = 5;
    const Rollback = 6;
}