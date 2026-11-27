<?php

namespace App\Services\InternalGames;

use App\Models\CmsUserTransaction;
use App\Models\InternalGameRound;
use App\Models\InternalGameSession;
use App\Models\WalletLedgerEntry;
use App\Services\Wallet\SpendableWalletContext;
use App\Services\Wallet\SpendableWalletService;
use Illuminate\Support\Facades\Schema;
use LogicException;

class InternalGameLedgerAuditService
{
    public function __construct(private readonly SpendableWalletService $wallets)
    {
    }

    public function record(
        SpendableWalletContext $walletContext,
        InternalGameSession $session,
        InternalGameRound $round,
        CmsUserTransaction $transaction,
        string $entryType,
        string $direction,
        float $amount,
        float $beforeBalance,
        float $afterBalance,
        string $sourceService,
        array $meta = []
    ): void {
        if (!Schema::hasTable('wallet_ledger_entries')) {
            return;
        }

        $idempotencyKey = sprintf(
            'internal_game:%s:%s:%s',
            (string) $session->game_code,
            (string) $transaction->event_type,
            (string) $transaction->transactionId
        );

        $existing = WalletLedgerEntry::query()
            ->where('wallet_account_id', $walletContext->walletAccountId())
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing instanceof WalletLedgerEntry) {
            $this->assertCompatibleDuplicate($existing, $entryType, $direction, $amount, $beforeBalance, $afterBalance);

            return;
        }

        $this->wallets->recordLedgerEntry(
            $walletContext,
            $entryType,
            $direction,
            $amount,
            $beforeBalance,
            $afterBalance,
            $idempotencyKey,
            $transaction,
            array_merge([
                'audit_only' => true,
                'game_key' => (string) $session->game_code,
                'round_id' => (string) $round->round_id,
                'internal_game_round_id' => (int) $round->id,
                'internal_game_session_id' => (int) $session->id,
                'cms_game_id' => (int) $round->cms_game_id,
                'provider_id' => (int) $round->provider_id,
                'cms_user_transaction_id' => (int) $transaction->id,
                'transaction_id' => (string) $transaction->transactionId,
                'event_type' => (string) $transaction->event_type,
                'amount' => round($amount, 8),
                'currency' => (string) $transaction->currency,
                'source_service' => $sourceService,
            ], $meta)
        );
    }

    /** Exact, fail-closed variant for Dice; preserves canonical ledger identity and provenance. */
    public function recordDecimal(
        SpendableWalletContext $walletContext,
        InternalGameSession $session,
        InternalGameRound $round,
        CmsUserTransaction $transaction,
        string $entryType,
        string $direction,
        string $amount,
        string $beforeBalance,
        string $afterBalance,
        string $sourceService,
        array $meta = []
    ): void {
        $this->wallets->recordDecimalLedgerEntry(
            $walletContext, $entryType, $direction, $amount, $beforeBalance, $afterBalance,
            sprintf('internal_game:%s:%s:%s', $session->game_code, $transaction->event_type, $transaction->transactionId),
            $transaction,
            array_merge([
                'audit_only' => true,
                'game_key' => (string) $session->game_code,
                'round_id' => (string) $round->round_id,
                'internal_game_round_id' => (int) $round->id,
                'internal_game_session_id' => (int) $session->id,
                'cms_game_id' => (int) $round->cms_game_id,
                'provider_id' => (int) $round->provider_id,
                'cms_user_transaction_id' => (int) $transaction->id,
                'transaction_id' => (string) $transaction->transactionId,
                'event_type' => (string) $transaction->event_type,
                'amount' => $amount,
                'currency' => (string) $transaction->currency,
                'source_service' => $sourceService,
            ], $meta)
        );
    }

    private function assertCompatibleDuplicate(
        WalletLedgerEntry $entry,
        string $entryType,
        string $direction,
        float $amount,
        float $beforeBalance,
        float $afterBalance
    ): void {
        $matches = (string) $entry->entry_type === $entryType
            && (string) $entry->direction === $direction
            && round((float) $entry->amount, 8) === round($amount, 8)
            && round((float) $entry->balance_before, 8) === round($beforeBalance, 8)
            && round((float) $entry->balance_after, 8) === round($afterBalance, 8);

        if (!$matches) {
            throw new LogicException('Incompatible internal game wallet ledger duplicate detected.');
        }
    }
}
