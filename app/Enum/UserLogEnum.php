<?php

namespace App\Enum;

class UserLogEnum
{
   const PROFILE_EDIT = "profile_edit";
   const PASSWORD_EDIT = "password_edit";
   const WRONG_LOGIN = "wrong_login";
   const STATUS_UPDATE = "status_update";
   const LOGGED_IN = "logged_in";
   const LOGGED_OUT = "logged_out";
    const BALANCE_INCREASE = "balance_increase";
    const BALANCE_DECREASE = "balance_decrease";
    const BONUS = "bonus_assigned";
    const SUPPORT_CREDIT = "support_credit_granted";
    const DEPOSIT = "deposit";
    const WITHDRAW = "withdraw";
    const ARCHIVED = "archived";
    const RESTORED = "restored";
    const DISABLED = "disabled";
    const ENABLED = "enabled";
}
