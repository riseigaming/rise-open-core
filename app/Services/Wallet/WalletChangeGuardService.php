<?php

namespace App\Services\Wallet;

use App\Services\Finance\FinanceCustomerMessage;
use App\Betting\Enums\BettingAcceptanceStatus;
use App\Enum\ChessWalletReservationStatusEnum;
use App\Models\BettingAcceptance;
use App\Models\ChessWalletReservation;
use App\Models\CmsUser;
use App\Models\CmsUserTransaction;
use App\Models\InternalGameRound;
use App\Models\WalletAccount;
use App\Models\WalletConversionRequest;
use App\Models\WalletTransferRequest;
use App\Services\InternalGames\PokerTableStateReader;
use Illuminate\Support\Facades\Schema;

class WalletChangeGuardService
{
    public function __construct(private readonly AssetWalletService $assetWallets)
    {
    }

    public function evaluate(CmsUser $user, ?WalletAccount $targetWallet = null): array
    {
        $owner = $this->assetWallets->resolveOwnerUser($user);
        $familyUserIds = CmsUser::query()
            ->where('id', $owner->id)
            ->orWhere('parent_id', $owner->id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $reasons = [];

        if (Schema::hasTable('cms_user_transactions')) {
            $pendingFinance = CmsUserTransaction::query()
                ->whereIn('cms_user_id', $familyUserIds)
                ->whereIn('status', [2, 5])
                ->count();

            if ($pendingFinance > 0) {
                $reasons[] = [
                    'code' => 'pending_finance',
                    'message' => FinanceCustomerMessage::forCode('pending_finance'),
                ];
            }
        }

        if (Schema::hasTable('internal_game_rounds')) {
            $openRounds = InternalGameRound::query()
                ->where(function ($query) use ($familyUserIds, $owner) {
                    $query->whereIn('cms_user_id', $familyUserIds)
                        ->orWhere('root_user_id', $owner->id);
                })
                ->whereIn('status', ['processing', 'awaiting_player_action', 'bet_accepted'])
                ->where('game_code', '!=', 'texas-holdem-poker')
                ->count();

            if ($openRounds > 0) {
                $reasons[] = [
                    'code' => 'open_internal_game_round',
                    'message' => FinanceCustomerMessage::forCode('open_internal_game_round'),
                ];
            }

            $openPoker = InternalGameRound::where('game_code','texas-holdem-poker')->where('is_demo',false)
                ->whereIn('status',['processing','awaiting_player_action','bet_accepted'])->where(function($q) use($familyUserIds,$owner){$q->whereIn('cms_user_id',$familyUserIds)->orWhere('root_user_id',$owner->id);})->exists();
            if ($openPoker) $reasons[]=['code'=>'open_poker_table','message'=>'An open Poker round blocks wallet changes.'];
        }

        if (Schema::hasTable('betting_acceptances')) {
            $openAcceptances = BettingAcceptance::query()
                ->where(function ($query) use ($familyUserIds, $owner) {
                    $query->whereIn('cms_user_id', $familyUserIds)
                        ->orWhere('root_user_id', $owner->id);
                })
                ->whereIn('status', [
                    BettingAcceptanceStatus::PENDING,
                    BettingAcceptanceStatus::HELD,
                    BettingAcceptanceStatus::SUSPENDED,
                    BettingAcceptanceStatus::ACCEPTED,
                ])
                ->count();

            if ($openAcceptances > 0) {
                $reasons[] = [
                    'code' => 'open_sportsbook_bet',
                    'message' => FinanceCustomerMessage::forCode('open_sportsbook_bet'),
                ];
            }
        }

        if (Schema::hasTable('chess_wallet_reservations')) {
            $openReservations = ChessWalletReservation::query()
                ->whereIn('cms_user_id', $familyUserIds)
                ->whereIn('status', [
                    ChessWalletReservationStatusEnum::CREATED,
                    ChessWalletReservationStatusEnum::RESERVED,
                ])
                ->count();

            if ($openReservations > 0) {
                $reasons[] = [
                    'code' => 'open_chess_reservation',
                    'message' => FinanceCustomerMessage::forCode('open_chess_reservation'),
                ];
            }
        }

        if ($this->assetWallets->tablesAvailable()) {
            $pendingWalletBalance = WalletAccount::query()
                ->where('cms_user_id', $owner->id)
                ->where('pending_balance', '>', 0)
                ->count();

            if ($pendingWalletBalance > 0) {
                $reasons[] = [
                    'code' => 'wallet_pending_balance',
                    'message' => FinanceCustomerMessage::forCode('wallet_pending_balance'),
                ];
            }

            if (Schema::hasTable('wallet_conversion_requests')) {
                $pendingConversions = WalletConversionRequest::query()
                    ->where('cms_user_id', $owner->id)
                    ->whereNotIn('status', ['completed', 'failed', 'cancelled'])
                    ->count();

                if ($pendingConversions > 0) {
                    $reasons[] = [
                        'code' => 'pending_conversion',
                        'message' => FinanceCustomerMessage::forCode('pending_conversion'),
                    ];
                }
            }

            if (Schema::hasTable('wallet_transfer_requests')) {
                $pendingTransfers = WalletTransferRequest::query()
                    ->where('cms_user_id', $owner->id)
                    ->whereNotIn('status', ['completed', 'failed', 'cancelled'])
                    ->count();

                if ($pendingTransfers > 0) {
                    $reasons[] = [
                        'code' => 'pending_transfer',
                        'message' => FinanceCustomerMessage::forCode('pending_transfer'),
                    ];
                }
            }
        }

        return [
            'allowed' => $reasons === [],
            'reasons' => $reasons,
            'targetWallet' => $targetWallet ? [
                'walletAccountId' => (int) $targetWallet->id,
                'walletType' => (string) $targetWallet->wallet_type,
                'asset' => (string) $targetWallet->asset,
            ] : null,
            'message' => $reasons[0]['message'] ?? null,
        ];
    }
}
