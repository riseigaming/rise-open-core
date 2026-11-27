<?php

namespace App\Services\Finance;

use App\Enum\TransactionActionEnum;
use App\Enum\TransactionStatusEnum;
use App\Models\CmsUser;
use App\Models\CmsUserTransaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class RealMoneyDepositPredicateService
{
    /**
     * Select FTD before applying campaign amount, currency or date requirements.
     * The caller supplies only its attribution's customer/family scope. Keep the
     * established Affiliate transaction-id ordering; a later eligible deposit
     * must never replace an earlier approved real-money deposit.
     */
    public function firstApprovedRealMoneyDeposit(Builder $customerScope): ?CmsUserTransaction
    {
        return $this->applyApprovedRealMoneyDepositFilters($customerScope)
            ->reorder('id')->first();
    }

    public function isApprovedRealMoneyDepositTransaction(CmsUserTransaction $transaction): bool
    {
        if (!((int) $transaction->cms_user_id > 0
            && (int) $transaction->action === TransactionActionEnum::Deposit
            && (int) $transaction->status === TransactionStatusEnum::Success
            && (float) $transaction->amount > 0
            && $transaction->promotion_code_id === null
            && (string) $transaction->source_wallet_type !== (string) config('production_certification.wallet_type', 'certification_balance')
            && $this->eventTypeIsRealMoneyDeposit($transaction->event_type))) {
            return false;
        }

        $user = $transaction->user()->withTrashed()->first();
        if (!$user || $user->isWeeklyWinnerVirtual()
            || ($user->parent_id && Schema::hasColumn('cms_users', 'registration_source')
                && CmsUser::withTrashed()->whereKey($user->parent_id)->weeklyWinnerVirtual()->exists())) {
            return false;
        }

        return true;
    }

    public function applyApprovedRealMoneyDepositFilters(Builder $query): Builder
    {
        if (Schema::hasColumn('cms_users', 'registration_source')) {
            $query->whereHas('user', fn (Builder $users) => $users->withTrashed()->withoutWeeklyWinnerVirtual()
                ->whereDoesntHave('parentAccount', fn (Builder $parents) => $parents->withTrashed()->weeklyWinnerVirtual()));
        }

        return $query
            ->where('action', TransactionActionEnum::Deposit)
            ->where('status', TransactionStatusEnum::Success)
            ->where('amount', '>', 0)
            ->whereNull('promotion_code_id')
            ->excludeCertification()
            ->where(function (Builder $query): void {
                $query->whereNull('event_type')
                    ->orWhere(function (Builder $eventQuery): void {
                        foreach ($this->excludedEventFragments() as $fragment) {
                            $eventQuery->where('event_type', 'not like', '%' . $fragment . '%');
                        }
                    });
            });
    }

    private function eventTypeIsRealMoneyDeposit(mixed $eventType): bool
    {
        $eventType = strtolower(trim((string) $eventType));

        if ($eventType === '') {
            return true;
        }

        foreach ($this->excludedEventFragments() as $fragment) {
            if (str_contains($eventType, $fragment)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return string[]
     */
    private function excludedEventFragments(): array
    {
        return [
            'promo',
            'promotion',
            'support_credit',
            'customer_referral_reward',
            'admin_manual',
            'manual_balance',
            'refund',
            'rollback',
            'reversal',
            'settlement',
            'win',
            'cashout',
            'internal_',
            'external_games_',
            'external_games_transaction_win',
            'external_games_refund',
            'test_credit',
            'crypto_under_minimum',
            'demo',
            'certification_',
        ];
    }
}
