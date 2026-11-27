<?php

namespace App\Services\Finance;

use App\Enum\TransactionActionEnum;
use App\Enum\TransactionStatusEnum;
use App\Enum\UserLogEnum;
use App\Helper\CmsUserLogService;
use App\Models\CmsUser;
use App\Models\CmsUserTransaction;
use App\Models\WalletLedgerEntry;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

/**
 * Internal-only adaptation of WinX S0 FinanceController::cancelWithdrawRequest
 * (1c151f21af58c1cc738f67f9e7a10953e6a6e6a2). No provider dispatch or route.
 */
final class WithdrawalCancellationService
{
    private const REASON = 'finance_withdraw_cancel';

    public function __construct(private readonly PayoutRoutingService $payouts)
    {
    }

    /** The caller supplies the authenticated member, never an admin override. */
    public function cancel(CmsUser $actor, string $transactionId): CmsUserTransaction
    {
        if (!$actor->exists || (int) $actor->getKey() <= 0 || trim($transactionId) === '') {
            throw new \DomainException('Withdrawal cancellation requires a persisted member and reference.');
        }

        return DB::transaction(function () use ($actor, $transactionId): CmsUserTransaction {
            app(PrelaunchFinanceWriteGate::class)->assertWritable();
            $matches = CmsUserTransaction::query()
                ->where('transactionId', $transactionId)
                ->lockForUpdate()->get();
            $transaction = $matches->first();
            if ($matches->count() !== 1 || !$transaction instanceof CmsUserTransaction
                || (string) $transaction->transactionId !== $transactionId
                || (int) $transaction->cms_user_id !== (int) $actor->getKey()
                || (int) $transaction->action !== TransactionActionEnum::Withdraw
                || (int) $transaction->status !== TransactionStatusEnum::Pending) {
                throw new \DomainException('Pending member withdrawal not found.');
            }

            // Empty execution state is required; even partial/unknown execution
            // evidence is not permission to refund an externally executed payout.
            foreach (['provider_transaction_id', 'payment_api_url', 'execution_wallet_account_id',
                'execution_currency', 'settled_wallet_account_id', 'settled_currency'] as $marker) {
                if ($transaction->getAttribute($marker) !== null) {
                    throw new \DomainException('Withdrawal execution or settlement evidence exists.');
                }
            }
            if ($transaction->execution_context_snapshot !== null
                && $transaction->execution_context_snapshot !== []) {
                throw new \DomainException('Withdrawal execution evidence exists.');
            }
            $execution = WalletLedgerEntry::query()
                ->where('source_type', 'cms_user_transaction')
                ->where('source_reference', $transactionId)
                ->whereIn('entry_type', ['payout_execution_approved', 'payout_execution_completed'])
                ->lockForUpdate()->get();
            if ($execution->isNotEmpty()) {
                throw new \DomainException('Withdrawal execution ledger evidence exists.');
            }

            // Do not round a corrupt transaction into a valid refund. The
            // existing routing service additionally locks and proves the unique
            // original debit, funded wallet, owner, asset and exact arithmetic.
            if (!$transaction->hasExactPrecisionSnapshot()
                || !BigDecimal::of($transaction->exactAmountValue())->isPositive()
                || !BigDecimal::of($transaction->exactAmountValue())->isEqualTo((string) $transaction->amount)
                || !BigDecimal::of($transaction->exactBeforeBalanceValue())
                    ->minus($transaction->exactAfterBalanceValue())
                    ->isEqualTo($transaction->exactAmountValue())) {
                throw new \DomainException('Withdrawal exact debit snapshot is invalid.');
            }
            $balance = $this->payouts->refundToSource(
                $transaction, $transaction->exactAmountValue(), self::REASON,
                ['actor_user_id' => (int) $actor->getKey()]
            );
            $transaction->refresh();
            $transaction->status = 4; // Legacy member cancellation, after refund.
            if (!$transaction->save()) {
                throw new \RuntimeException('Withdrawal cancellation could not be persisted.');
            }
            CmsUserLogService::save($actor, $actor, UserLogEnum::WITHDRAW, false, [
                'logTitle' => 'Kullanıcı, withdraw talebini iptal etti | '.self::REASON,
                'amount' => $transaction->exactAmountValue(),
                'balance' => $balance,
                'refTransactionId' => $transaction->transactionId,
                'currency' => $transaction->source_currency,
            ]);

            return $transaction;
        });
    }
}
