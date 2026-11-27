<?php

namespace App\Services\ExternalGames;

use App\Enum\TransactionStatusEnum;
use App\Models\CmsUser;
use App\Models\CmsUserTransaction;
use App\Models\ExternalGameRealMoneyEligibilityOverride;
use App\Models\WalletLedgerEntry;
use App\Services\Finance\RealMoneyDepositPredicateService;
use App\Services\Wallet\CanonicalWalletContext;
use App\Services\Wallet\CanonicalWalletService;
use Illuminate\Support\Facades\Schema;

class ExternalGameRealDepositEligibilityService
{
    public function __construct(
        private readonly CanonicalWalletService $wallets,
        private readonly RealMoneyDepositPredicateService $realMoneyDeposits,
        private readonly ExternalGameRealMoneyEligibilityOverrideService $overrides
    ) {
    }

    public function resolve(CmsUser $user): array
    {
        $activeOverride = $this->overrides->activeFor($user);
        $latestOverride = $this->overrides->latestFor($user);
        if ($activeOverride instanceof ExternalGameRealMoneyEligibilityOverride) {
            return $this->decision(true, 'admin_override', 'explicit_admin_override', [
                'override' => $activeOverride,
                'latest_override' => $latestOverride,
            ]);
        }

        if ($this->hasApprovedRealMoneyDeposit($user)) {
            return $this->decision(true, 'real_money_deposit', 'successful_real_money_deposit', [
                'latest_override' => $latestOverride,
            ]);
        }

        if ($this->hasAdminManualCashCredit($user)) {
            return $this->decision(true, 'admin_manual_cash_credit', 'admin_manual_cash_credit', [
                'latest_override' => $latestOverride,
            ]);
        }

        return $this->decision(false, 'none', 'real_money_deposit_required', [
            'latest_override' => $latestOverride,
        ]);
    }

    public function isEligible(CmsUser $user): bool
    {
        return (bool) ($this->resolve($user)['eligible'] ?? false);
    }

    public function hasApprovedRealMoneyDeposit(CmsUser $user): bool
    {
        if (!Schema::hasTable('cms_user_transactions')) {
            return false;
        }

        $walletContext = $this->wallets->resolveForUser($user, $user->currency ?: 'TRY', false);
        $userIds = $walletContext instanceof CanonicalWalletContext
            ? $walletContext->projectionWalletIds()
            : [(int) $user->id];

        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if ($userIds === []) {
            return false;
        }

        $query = CmsUserTransaction::query()->whereIn('cms_user_id', $userIds);

        return $this->realMoneyDeposits
            ->applyApprovedRealMoneyDepositFilters($query)
            ->exists();
    }

    public function hasAdminManualCashCredit(CmsUser $user): bool
    {
        if (!Schema::hasTable('cms_user_transactions')) {
            return $this->hasAdminManualCashCreditLedger($user);
        }

        $userIds = $this->projectionUserIds($user);
        if ($userIds === []) {
            return false;
        }

        $query = CmsUserTransaction::query()
            ->whereIn('cms_user_id', $userIds)
            ->where('action', 9)
            ->where('status', TransactionStatusEnum::Success)
            ->where('amount', '>', 0)
            ->where(function ($builder): void {
                $builder->whereNull('promotion_code_id')->orWhere('promotion_code_id', 0);
            })
            ->where(function ($builder): void {
                $builder->whereNull('event_type')
                    ->orWhereIn('event_type', [
                        'admin_manual_balance_increased',
                        'admin_manual_balance_increase',
                        'manual_balance_increased',
                    ]);
            });

        if (Schema::hasColumn('cms_user_transactions', 'created_by_admin')) {
            $query->where('created_by_admin', 1);
        }

        return $query->exists() || $this->hasAdminManualCashCreditLedger($user);
    }

    private function projectionUserIds(CmsUser $user): array
    {
        $walletContext = $this->wallets->resolveForUser($user, $user->currency ?: 'TRY', false);
        $userIds = $walletContext instanceof CanonicalWalletContext
            ? $walletContext->projectionWalletIds()
            : [(int) $user->id];

        return array_values(array_unique(array_filter(array_map('intval', $userIds))));
    }

    private function hasAdminManualCashCreditLedger(CmsUser $user): bool
    {
        if (!Schema::hasTable('wallet_ledger_entries')) {
            return false;
        }

        $userIds = $this->projectionUserIds($user);
        if ($userIds === []) {
            return false;
        }

        return WalletLedgerEntry::query()
            ->whereIn('cms_user_id', $userIds)
            ->where(function ($q): void {
                $q->where('entry_type', 'admin_manual_balance_increased');
                // Run19 canonical manual credit is an audited ledger effect, not a deposit transaction.
                // Require its actual operation audit, pinned owner/wallet/asset and exact amount.
                if (Schema::hasTable('finance_manual_overrides')) {
                    $q->orWhere(function ($canonical): void {
                        $canonical->where('entry_type','finance_manual_credit')->where('source_type','finance_manual_override')
                            ->whereExists(function ($audit): void {
                                $audit->selectRaw('1')->from('finance_manual_overrides as f')
                                    ->whereColumn('f.operation_id','wallet_ledger_entries.source_reference')
                                    ->whereColumn('f.cms_user_id','wallet_ledger_entries.cms_user_id')
                                    ->whereColumn('f.wallet_account_id','wallet_ledger_entries.wallet_account_id')
                                    ->whereColumn('f.asset','wallet_ledger_entries.asset')
                                    ->whereColumn('f.amount','wallet_ledger_entries.amount')
                                    ->where('f.economic_action','manual_credit')
                                    ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(f.meta, '$.state')) = 'applied'");
                            });
                    });
                }
            })
            ->where('direction', 'credit')
            ->where('status', 'applied')
            ->where('amount', '>', 0)
            ->exists();
    }

    private function decision(bool $eligible, string $source, string $reasonCode, array $extra = []): array
    {
        $latestOverride = $extra['latest_override'] ?? null;
        $activeOverride = $extra['override'] ?? null;

        return [
            'eligible' => $eligible,
            'source' => $source,
            'source_label' => $this->sourceLabel($source),
            'reason_code' => $reasonCode,
            'operator_explanation' => $this->operatorExplanation($eligible, $source, $reasonCode),
            'override_table_available' => $this->overrides->available(),
            'override' => $activeOverride,
            'latest_override' => $latestOverride,
            'override_reason' => $activeOverride?->reason,
            'override_expires_at' => optional($activeOverride?->expires_at)->toDateTimeString(),
            'latest_override_reason' => $latestOverride?->reason,
            'latest_override_revoked_at' => optional($latestOverride?->revoked_at)->toDateTimeString(),
            'latest_override_expires_at' => optional($latestOverride?->expires_at)->toDateTimeString(),
            'latest_override_revoke_reason' => $latestOverride?->revoke_reason,
        ];
    }

    private function sourceLabel(string $source): string
    {
        return match ($source) {
            'admin_override' => 'Admin override',
            'real_money_deposit' => 'Real-money deposit',
            'admin_manual_cash_credit' => 'Admin manual cash credit',
            default => 'No qualifying real-money source',
        };
    }

    private function operatorExplanation(bool $eligible, string $source, string $reasonCode): string
    {
        if ($eligible) {
            return match ($source) {
                'admin_override' => 'A human/admin real-money eligibility override is active.',
                'real_money_deposit' => 'A successful non-promo real-money deposit is recorded.',
                'admin_manual_cash_credit' => 'A positive admin manual cash credit is recorded.',
                default => 'External Games real-money eligibility is satisfied.',
            };
        }

        return match ($reasonCode) {
            'real_money_deposit_required' => 'No successful real-money deposit, admin manual cash credit, or active eligibility override was found.',
            default => 'External Games real-money eligibility is not satisfied.',
        };
    }
}
