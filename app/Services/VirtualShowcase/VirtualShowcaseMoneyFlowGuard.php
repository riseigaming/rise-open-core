<?php

namespace App\Services\VirtualShowcase;

use App\Models\BettingAcceptance;
use App\Models\BettingSettlement;
use App\Models\CmsUser;
use App\Models\InternalGameSession;
use App\Models\WalletAccount;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class VirtualShowcaseMoneyFlowGuard
{
    public function assertUserIdAllowed(?int $userId, string $context): void
    {
        $userId = (int) ($userId ?? 0);
        if ($userId <= 0 || !Schema::hasTable('cms_users')) {
            return;
        }

        $user = CmsUser::withTrashed()->find($userId);
        if (!$user instanceof CmsUser || !$user->isWeeklyWinnerVirtual()) {
            return;
        }

        $this->reject($context, ['cms_user_id' => $userId]);
    }

    public function assertUserIdsAllowed(array $userIds, string $context): void
    {
        foreach ($userIds as $userId) {
            $this->assertUserIdAllowed($userId === null ? null : (int) $userId, $context);
        }
    }

    public function assertWalletAccountIdAllowed(?int $walletAccountId, string $context): void
    {
        $walletAccountId = (int) ($walletAccountId ?? 0);
        if ($walletAccountId <= 0 || !Schema::hasTable('wallet_accounts')) {
            return;
        }

        $wallet = WalletAccount::query()->find($walletAccountId);
        if (!$wallet instanceof WalletAccount) {
            return;
        }

        $this->assertUserIdAllowed((int) $wallet->cms_user_id, $context);
    }

    public function assertBettingAcceptanceIdAllowed(?int $acceptanceId, string $context): void
    {
        $acceptanceId = (int) ($acceptanceId ?? 0);
        if ($acceptanceId <= 0 || !Schema::hasTable('betting_acceptances')) {
            return;
        }

        $acceptance = BettingAcceptance::query()->find($acceptanceId);
        if (!$acceptance instanceof BettingAcceptance) {
            return;
        }

        $this->assertUserIdsAllowed([
            $acceptance->cms_user_id,
            $acceptance->root_user_id,
        ], $context);
    }

    public function assertBettingSettlementIdAllowed(?int $settlementId, string $context): void
    {
        $settlementId = (int) ($settlementId ?? 0);
        if ($settlementId <= 0 || !Schema::hasTable('betting_settlements')) {
            return;
        }

        $settlement = BettingSettlement::query()->find($settlementId);
        if (!$settlement instanceof BettingSettlement) {
            return;
        }

        $this->assertBettingAcceptanceIdAllowed(
            (int) $settlement->betting_acceptance_id,
            $context
        );
    }

    public function assertInternalGameSessionIdAllowed(?int $sessionId, string $context): void
    {
        $sessionId = (int) ($sessionId ?? 0);
        if ($sessionId <= 0 || !Schema::hasTable('internal_game_sessions')) {
            return;
        }

        $session = InternalGameSession::query()->find($sessionId);
        if (!$session instanceof InternalGameSession || (bool) $session->is_demo) {
            return;
        }

        $this->assertUserIdsAllowed([
            $session->cms_user_id,
            $session->root_user_id,
        ], $context);
    }

    private function reject(string $context, array $meta): void
    {
        Log::warning('Virtual showcase actor blocked from money flow.', [
            'context' => $context,
        ] + $meta);

        throw new RuntimeException('Financial action is not available for this account.');
    }
}
