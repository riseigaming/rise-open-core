<?php

namespace App\Betting\Settlement;

use App\Betting\Enums\BettingSettlementResult;
use App\Betting\Enums\BettingSettlementStatus;
use App\Betting\Support\BettingActivityLogService;
use App\Enum\TransactionActionEnum;
use App\Enum\TransactionStatusEnum;
use App\Models\BettingAcceptance;
use App\Models\BettingSettlement;
use App\Models\BettingSettlementSourceResult;
use App\Models\BettingSettlementWalletEffect;
use App\Models\CmsUser;
use App\Models\CmsUserTransaction;
use App\Services\Wallet\SpendableWalletContext;
use App\Services\Wallet\SpendableWalletService;
use Illuminate\Support\Str;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class SettlementWalletService
{
    private $activityLogService;
    private $wallets;

    public function __construct(?BettingActivityLogService $activityLogService = null, ?SpendableWalletService $wallets = null)
    {
        $this->activityLogService = $activityLogService ?: app(BettingActivityLogService::class);
        $this->wallets = $wallets ?: app(SpendableWalletService::class);
    }

    public function reconcile(BettingSettlement $settlement, ?BettingSettlementSourceResult $sourceResult = null, ?string $reason = null, ?CmsUser $actor = null): array
    {
        app(\App\Services\Finance\PrelaunchFinanceWriteGate::class)->assertWritable();
        $settlement->loadMissing([
            'acceptance.user',
            'acceptance.rootUser',
            'walletEffects.transaction',
        ]);

        $walletUsers = $this->resolveWalletUsers($settlement->acceptance);
        if ($walletUsers === null) {
            return [
                'enabled' => false,
                'state' => 'skipped_no_wallet_user',
                'reversed' => [],
                'applied' => null,
            ];
        }

        $activeEffect = $this->activeEffect($settlement);
        $desiredEffect = $this->desiredEffect($settlement);

        if ($activeEffect && $desiredEffect && $this->effectMatches($activeEffect, $desiredEffect)) {
            return [
                'enabled' => true,
                'state' => 'already_applied',
                'reversed' => [],
                'applied' => null,
            ];
        }

        $reversed = [];
        if ($activeEffect) {
            $reversed[] = $this->reverseEffect($settlement, $activeEffect, $walletUsers, $sourceResult, $reason, $actor);
        }

        $applied = null;
        if ($desiredEffect !== null) {
            $applied = $this->applyEffect($settlement, $desiredEffect, $walletUsers, $sourceResult, $reason, $actor);
        }

        return [
            'enabled' => true,
            'state' => $desiredEffect === null ? 'cleared' : 'applied',
            'reversed' => $reversed,
            'applied' => $applied,
        ];
    }

    private function activeEffect(BettingSettlement $settlement): ?BettingSettlementWalletEffect
    {
        return BettingSettlementWalletEffect::query()
            ->where('betting_settlement_id', $settlement->id)
            ->where('effect_phase', 'apply')
            ->whereNull('reversed_at')
            ->latest('id')
            ->first();
    }

    private function desiredEffect(BettingSettlement $settlement): ?array
    {
        $resultCode = (string) $settlement->result_code;
        $status = (string) $settlement->status;

        if ($resultCode === BettingSettlementResult::WON && $status === BettingSettlementStatus::FINAL) {
            return [
                'effect_family' => 'payout',
                'result_code' => BettingSettlementResult::WON,
                'amount' => (string) BigDecimal::of((string)$settlement->return_amount)->toScale(2,RoundingMode::HALF_UP),
                'direction' => 'credit',
                'action' => TransactionActionEnum::Win,
                'event_type' => 'sportsbook_settlement_win',
            ];
        }

        if (in_array($resultCode, [
            BettingSettlementResult::VOID,
            BettingSettlementResult::REFUNDED,
            BettingSettlementResult::CANCELLED,
        ], true) && in_array($status, [
            BettingSettlementStatus::VOID,
            BettingSettlementStatus::CANCELLED,
        ], true)) {
            return [
                'effect_family' => 'refund',
                'result_code' => $resultCode,
                'amount' => (string) BigDecimal::of((string)$settlement->return_amount)->toScale(2,RoundingMode::HALF_UP),
                'direction' => 'credit',
                'action' => TransactionActionEnum::Rollback,
                'event_type' => 'sportsbook_settlement_' . $resultCode,
            ];
        }

        return null;
    }

    private function effectMatches(BettingSettlementWalletEffect $effect, array $desiredEffect): bool
    {
        return (string) $effect->effect_family === (string) $desiredEffect['effect_family']
            && (string) $effect->result_code === (string) $desiredEffect['result_code']
            && BigDecimal::of((string)$effect->amount)->isEqualTo((string)$desiredEffect['amount'])
            && (string) $effect->direction === (string) $desiredEffect['direction'];
    }

    private function reverseEffect(
        BettingSettlement $settlement,
        BettingSettlementWalletEffect $effect,
        SpendableWalletContext $walletContext,
        ?BettingSettlementSourceResult $sourceResult = null,
        ?string $reason = null,
        ?CmsUser $actor = null
    ): BettingSettlementWalletEffect {
        $account = $walletContext->ownerUser();

        $this->wallets->assertNoDrift($walletContext, 'sportsbook_settlement_before_reverse', [
            'settlement_ref' => $settlement->settlement_ref,
        ]);

        $beforeBalance = $this->wallets->currentDecimalBalance($walletContext);
        $amount = $this->wallets->decimalForContext($walletContext, (string) $effect->amount);
        $afterBalance = (string) BigDecimal::of($beforeBalance)->minus($amount);

        $transaction = CmsUserTransaction::query()->create([
            'cms_user_id' => $walletContext->ownerWalletId(),
            'transactionId' => (string) Str::uuid(),
            'ref_transaction_id' => optional($effect->transaction)->transactionId ?: $settlement->acceptance->acceptance_ref,
            'round_id' => $settlement->settlement_ref,
            'provider_transaction_id' => $sourceResult?->source_result_key ?: $settlement->settlement_ref,
            'action' => TransactionActionEnum::Bet,
            'event_type' => 'sportsbook_settlement_reverse',
            'status' => TransactionStatusEnum::Success,
            'amount' => $amount,
            'currency' => $settlement->acceptance->currency ?: $account->currency ?: 'TRY',
            'source_wallet_account_id' => $walletContext->walletAccountId(),
            'source_wallet_type' => $walletContext->walletType(),
            'source_currency' => $walletContext->currency(),
            'before_balance' => $beforeBalance,
            'after_balance' => $afterBalance,
            'acceptor' => 'sportsbook',
        ]);

        $this->wallets->projectDecimalBalance($walletContext, $afterBalance);

        $reverseEffect = BettingSettlementWalletEffect::query()->create([
            'effect_ref' => (string) Str::uuid(),
            'betting_settlement_id' => $settlement->id,
            'betting_settlement_source_result_id' => optional($sourceResult)->id,
            'cms_user_id' => $walletContext->ownerWalletId(),
            'cms_user_transaction_id' => $transaction->id,
            'reversed_wallet_effect_id' => $effect->id,
            'effect_phase' => 'reverse',
            'effect_family' => $effect->effect_family,
            'result_code' => $effect->result_code,
            'direction' => 'debit',
            'amount' => $effect->amount,
            'currency' => $effect->currency,
            'applied_at' => now(),
            'meta' => [
                'reason' => $reason ?: 'wallet_effect_reversed',
                'source_result_key' => $sourceResult?->source_result_key,
                'reversed_transaction_id' => optional($effect->transaction)->transactionId,
                'wallet_account_id' => data_get($settlement->acceptance->context, 'wallet_account_id'),
                'active_wallet_id' => data_get($settlement->acceptance->context, 'active_wallet_id'),
                'wallet_currency' => data_get($settlement->acceptance->context, 'wallet_currency'),
            ],
        ]);

        $this->recordSettlementLedgerAudit(
            $settlement,
            $walletContext,
            $transaction,
            $reverseEffect,
            'sportsbook_settlement_reversal',
            'debit',
            $amount,
            $beforeBalance,
            $afterBalance,
            'sportsbook_settlement_reverse:' . (string) $reverseEffect->effect_ref
        );

        $effect->update([
            'reversed_at' => now(),
            'reversed_by_wallet_effect_id' => $reverseEffect->id,
        ]);

        $this->activityLogService->logSettlement(
            $settlement,
            'settlement.wallet_reversed',
            'Settlement wallet effect reversed before applying the new internal truth.',
            [
                'wallet_effect_id' => $effect->id,
                'reverse_wallet_effect_id' => $reverseEffect->id,
                'transaction_id' => $transaction->transactionId,
                'source_result_id' => optional($sourceResult)->id,
                'amount' => (float) $effect->amount,
                'effect_family' => $effect->effect_family,
                'result_code' => $effect->result_code,
            ] + $walletContext->auditPayload(),
            $actor,
            'warning'
        );

        return $reverseEffect->fresh(['transaction', 'reversedWalletEffect']);
    }

    private function applyEffect(
        BettingSettlement $settlement,
        array $desiredEffect,
        SpendableWalletContext $walletContext,
        ?BettingSettlementSourceResult $sourceResult = null,
        ?string $reason = null,
        ?CmsUser $actor = null
    ): BettingSettlementWalletEffect {
        $account = $walletContext->ownerUser();

        $this->wallets->assertNoDrift($walletContext, 'sportsbook_settlement_before_apply', [
            'settlement_ref' => $settlement->settlement_ref,
        ]);

        $beforeBalance = $this->wallets->currentDecimalBalance($walletContext);
        $amount = $this->wallets->decimalForContext($walletContext, (string) $desiredEffect['amount']);
        $afterBalance = (string) BigDecimal::of($beforeBalance)->plus($amount);

        $transaction = CmsUserTransaction::query()->create([
            'cms_user_id' => $walletContext->ownerWalletId(),
            'transactionId' => (string) Str::uuid(),
            'ref_transaction_id' => $settlement->acceptance->acceptance_ref,
            'round_id' => $settlement->settlement_ref,
            'provider_transaction_id' => $sourceResult?->source_result_key ?: $settlement->settlement_ref,
            'action' => $desiredEffect['action'],
            'event_type' => $desiredEffect['event_type'],
            'status' => TransactionStatusEnum::Success,
            'amount' => $amount,
            'currency' => $settlement->acceptance->currency ?: $account->currency ?: 'TRY',
            'source_wallet_account_id' => $walletContext->walletAccountId(),
            'source_wallet_type' => $walletContext->walletType(),
            'source_currency' => $walletContext->currency(),
            'before_balance' => $beforeBalance,
            'after_balance' => $afterBalance,
            'acceptor' => 'sportsbook',
        ]);

        $this->wallets->projectDecimalBalance($walletContext, $afterBalance);

        $walletEffect = BettingSettlementWalletEffect::query()->create([
            'effect_ref' => (string) Str::uuid(),
            'betting_settlement_id' => $settlement->id,
            'betting_settlement_source_result_id' => optional($sourceResult)->id,
            'cms_user_id' => $walletContext->ownerWalletId(),
            'cms_user_transaction_id' => $transaction->id,
            'effect_phase' => 'apply',
            'effect_family' => $desiredEffect['effect_family'],
            'result_code' => $desiredEffect['result_code'],
            'direction' => 'credit',
            'amount' => $desiredEffect['amount'],
            'currency' => $settlement->acceptance->currency ?: $account->currency ?: 'TRY',
            'applied_at' => now(),
            'meta' => [
                'reason' => $reason ?: 'wallet_effect_applied',
                'source_result_key' => $sourceResult?->source_result_key,
                'wallet_account_id' => data_get($settlement->acceptance->context, 'wallet_account_id'),
                'active_wallet_id' => data_get($settlement->acceptance->context, 'active_wallet_id'),
                'wallet_currency' => data_get($settlement->acceptance->context, 'wallet_currency'),
            ],
        ]);

        $this->recordSettlementLedgerAudit(
            $settlement,
            $walletContext,
            $transaction,
            $walletEffect,
            $this->settlementLedgerEntryType($desiredEffect),
            'credit',
            $amount,
            $beforeBalance,
            $afterBalance,
            'sportsbook_settlement_apply:' . (string) $walletEffect->effect_ref
        );

        $this->activityLogService->logSettlement(
            $settlement,
            'settlement.wallet_applied',
            'Settlement wallet effect applied to the customer ledger.',
            [
                'wallet_effect_id' => $walletEffect->id,
                'transaction_id' => $transaction->transactionId,
                'source_result_id' => optional($sourceResult)->id,
                'amount' => $amount,
                'effect_family' => $desiredEffect['effect_family'],
                'result_code' => $desiredEffect['result_code'],
            ] + $walletContext->auditPayload(),
            $actor
        );

        return $walletEffect->fresh(['transaction']);
    }

    private function recordSettlementLedgerAudit(
        BettingSettlement $settlement,
        SpendableWalletContext $walletContext,
        CmsUserTransaction $transaction,
        BettingSettlementWalletEffect $walletEffect,
        string $entryType,
        string $direction,
        string $amount,
        string $beforeBalance,
        string $afterBalance,
        string $idempotencyKey
    ): void {
        $acceptance = $settlement->acceptance;
        if (!$acceptance instanceof BettingAcceptance) {
            return;
        }

        $this->wallets->recordDecimalLedgerEntry(
            $walletContext,
            $entryType,
            $direction,
            $amount,
            $beforeBalance,
            $afterBalance,
            $idempotencyKey,
            $transaction,
            [
                'audit_only' => true,
                'settlement_id' => (int) $settlement->id,
                'settlement_ref' => (string) $settlement->settlement_ref,
                'wallet_effect_id' => (int) $walletEffect->id,
                'wallet_effect_ref' => (string) $walletEffect->effect_ref,
                'acceptance_id' => (int) $acceptance->id,
                'acceptance_ref' => (string) $acceptance->acceptance_ref,
                'acceptance_source' => (string) $acceptance->acceptance_source,
                'acceptance_contract_version' => (string) $acceptance->acceptance_contract_version,
                'result_code' => (string) $walletEffect->result_code,
                'effect_family' => (string) $walletEffect->effect_family,
                'cms_user_transaction_id' => (int) $transaction->id,
            ]
        );
    }

    private function settlementLedgerEntryType(array $desiredEffect): string
    {
        return (string) ($desiredEffect['effect_family'] ?? '') === 'refund'
            ? 'sportsbook_settlement_refund'
            : 'sportsbook_settlement_payout';
    }

    private function isModernBettingAcceptance(BettingAcceptance $acceptance): bool
    {
        return in_array([
            (string) $acceptance->acceptance_contract_version,
            (string) $acceptance->acceptance_source,
        ], [
            ['adapter_vps_v2', 'adapter_vps'],
            ['social_market_v1', 'social_market'],
            ['social_feed_v1', 'social_feed'],
        ], true);
    }

    private function resolveWalletUsers(BettingAcceptance $acceptance): ?SpendableWalletContext
    {
        $contextWalletId = (int) data_get($acceptance->context, 'wallet_context_user_id', 0);
        $lookupUserId = $contextWalletId > 0
            ? $contextWalletId
            : (int) ($acceptance->cms_user_id ?: $acceptance->root_user_id ?: 0);

        if ($lookupUserId <= 0) {
            return null;
        }

        $user = CmsUser::query()->find($lookupUserId);
        if (!$user instanceof CmsUser) {
            return null;
        }

        return $this->wallets->resolveForStoredContext(
            $user,
            (int) data_get($acceptance->context, 'wallet_account_id', 0) ?: null,
            (string) data_get($acceptance->context, 'wallet_type', 'primary_balance'),
            (string) data_get($acceptance->context, 'wallet_currency', $acceptance->currency),
            $contextWalletId > 0 ? $contextWalletId : null,
            true
        );
    }
}
