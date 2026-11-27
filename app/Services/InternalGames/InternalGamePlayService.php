<?php

namespace App\Services\InternalGames;

use App\Enum\TransactionActionEnum;
use App\Enum\TransactionStatusEnum;
use App\Models\CmsUser;
use App\Models\CmsUserTransaction;
use App\Models\InternalGameRound;
use App\Models\InternalGameSession;
use App\Services\Wallet\SpendableWalletContext;
use App\Services\Wallet\SpendableWalletService;
use Illuminate\Database\QueryException;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InternalGamePlayService
{
    public function __construct(
        private InternalGameCatalog $catalog,
        private InternalGameSessionService $sessions,
        private InternalGameAuditService $audit,
        private MiniSlotEngine $engine,
        private SpendableWalletService $wallets,
        private InternalGameLedgerAuditService $ledgerAudit
    ) {
    }

    public function supportsGameCode(string $code): bool {return in_array($code,['mini-slot-classic','big-win'],true);}
    public function spin(string $token, ?CmsUser $user, float $amount, string $key): array {
        $key=trim($key);abort_unless($key!=='',422);
        $session=$this->sessions->resolvePlayableSession($token,$user);abort_unless($this->supportsGameCode($session->game_code),404);
        $lock=Cache::lock('internal-slot-session:'.$session->id,15);$contended=!$lock->get();
        try {
            if($contended){try{$lock->block(5);}catch(\Illuminate\Contracts\Cache\LockTimeoutException){abort(409,'Another slot action is in progress.');}}
            return DB::transaction(function()use($session,$token,$user,$amount,$key,$contended){
                InternalGameSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
                $old=InternalGameRound::where('internal_game_session_id',$session->id)->where('request_ref',$key)->first();
                if($old){$this->assertSpinReplay($old,$amount);return $this->successResponse($old,$this->currentBalanceForSession($session));}
                abort_if($contended,409,'Another slot action completed while this request was waiting.');
                return $this->spinUnlocked($token,$user,$amount,$key);
            },3);
        }finally{$lock->release();}
    }
    private function assertSpinReplay(InternalGameRound $round,float $amount):void {abort_unless(BigDecimal::of((string)$round->bet_amount)->isEqualTo((string)$amount),409,'This requestKey already belongs to a different slot payload.');}
    private function spinUnlocked(string $sessionToken, ?CmsUser $user, float $betAmount, string $requestKey): array
    {
        $requestKey = trim($requestKey);
        abort_unless($requestKey !== '', 422);

        $session = $this->sessions->resolvePlayableSession($sessionToken, $user);
        $this->sessions->assertProductAccessAllowed($session, $user);
        $definition = $this->catalog->definitionByCode($session->game_code);
        abort_unless(is_array($definition), 404);
        // Dice has its own guarded roll action for both demo and real mode; never use the generic slot path.
        abort_if($session->game_code === 'dice' || ($definition['engine'] ?? '') === 'dice-demo-v1', 403, trans('dice.dedicated_action'));

        $allowedBets = array_map('floatval', (array) ($definition['bet_values'] ?? []));
        abort_unless(in_array($betAmount, $allowedBets, true), 422);

        $duplicate = InternalGameRound::query()
            ->with(['debitTransaction', 'winTransaction', 'refundTransaction', 'session'])
            ->where('internal_game_session_id', $session->id)
            ->where('request_ref', $requestKey)
            ->first();

        if ($duplicate instanceof InternalGameRound) {
            $this->assertSpinReplay($duplicate,$betAmount);
            return $this->successResponse($duplicate, $this->currentBalanceForSession($session));
        }

        return DB::transaction(function () use ($session, $definition, $betAmount, $requestKey) {
            try {
                $round = InternalGameRound::query()->create([
                    'internal_game_session_id' => $session->id,
                    'provider_id' => $session->provider_id,
                    'cms_game_id' => $session->cms_game_id,
                    'cms_user_id' => $session->cms_user_id,
                    'root_user_id' => $session->root_user_id,
                    'round_id' => 'igr_' . Str::upper(Str::random(18)),
                    'game_code' => $session->game_code,
                    'session_public_id' => $session->public_id,
                    'status' => 'processing',
                    'currency' => $session->currency,
                    'is_demo' => $session->is_demo,
                    'bet_amount' => $betAmount,
                    'win_amount' => 0,
                    'refund_amount' => 0,
                    'request_ref' => $requestKey,
                    'correlation_ref' => (string) Str::uuid(),
                    'raw_result' => [],
                    'result_summary' => '',
                    'started_at' => now(),
                ]);
            } catch (QueryException $exception) {
                if ((int)($exception->errorInfo[1]??0)!==1062) throw $exception;
                $duplicateRound = InternalGameRound::query()
                    ->where('internal_game_session_id', $session->id)
                    ->where('request_ref', $requestKey)
                    ->firstOrFail();

                return $this->successResponse($duplicateRound, $this->currentBalanceForSession($session));
            }

            $this->audit->logRound($session, $round, 'internal_game.spin.requested', [
                'request_ref' => $requestKey,
                'correlation_ref' => $round->correlation_ref,
                'bet_amount' => $betAmount,
            ]);

            $this->sessions->assertCasinoCountryAllowedForNewGameplay();
            $currentBalance = $session->is_demo ? (string) BigDecimal::of((string)$this->currentBalanceForSession($session))->plus((string)$betAmount) : $this->currentBalanceForSession($session);
            $walletContext = null;

            if ($session->is_demo) {
                abort_unless(BigDecimal::of((string)$currentBalance)->isGreaterThanOrEqualTo((string)$betAmount), 409);
            } else {
                $walletContext = $this->sessions->resolveWalletContextForSession($session, true);
                $this->sessions->assertNewGameplayAllowed($session);
                abort_unless($walletContext instanceof SpendableWalletContext, 404);
                $this->wallets->assertNoDrift($walletContext, 'internal_slot_before_debit', [
                    'session_id' => $session->id,
                    'game_code' => $session->game_code,
                ]);
                $currentBalance = $this->wallets->currentDecimalBalance($walletContext);
                abort_unless(BigDecimal::of((string)$currentBalance)->isGreaterThanOrEqualTo((string)$betAmount), 409);
            }

            $result = $this->engine->spin($definition, $betAmount);
            $balanceAfterDebit = $currentBalance;
            $balanceAfterWin = $currentBalance;

            if (!$session->is_demo) {
                $debitTransaction = CmsUserTransaction::query()->create([
                    'cms_user_id' => $walletContext->ownerWalletId(),
                    'transactionId' => $this->ledgerTransactionId('bet', $round->round_id),
                    'round_id' => $round->round_id,
                    'provider_transaction_id' => $requestKey,
                    'game_provider_id' => $session->provider_id,
                    'game_id' => $session->cms_game_id,
                    'action' => TransactionActionEnum::Bet,
                    'event_type' => 'internal_slot_bet',
                    'status' => TransactionStatusEnum::Success,
                    'amount' => $betAmount,
                    'currency' => $session->currency,
                    'source_wallet_account_id' => $walletContext->walletAccountId(),
                    'source_wallet_type' => $walletContext->walletType(),
                    'source_currency' => $walletContext->currency(),
                    'before_balance' => $currentBalance,
                    'after_balance' => $balanceAfterDebit = (string) BigDecimal::of((string)$currentBalance)->minus((string)$betAmount),
                    'acceptor' => 'api',
                ]);

                $this->wallets->projectDecimalBalance($walletContext, $balanceAfterDebit);
                $this->ledgerAudit->recordDecimal(
                    $walletContext,
                    $session,
                    $round,
                    $debitTransaction,
                    'internal_game_bet_debited',
                    'debit',
                    $betAmount,
                    $currentBalance,
                    $balanceAfterDebit,
                    self::class,
                    [
                        'request_ref' => $requestKey,
                        'result_summary' => $result['resultSummary'] ?? null,
                    ]
                );

                $round->debit_transaction_id = $debitTransaction->id;
                $this->audit->logRound($session, $round, 'internal_game.spin.debited', [
                    'request_ref' => $requestKey,
                    'transaction_id' => $debitTransaction->transactionId,
                    'before_balance' => $currentBalance,
                    'after_balance' => $balanceAfterDebit,
                ] + $walletContext->auditPayload());

                $balanceAfterWin = $balanceAfterDebit;

                if ($result['winAmount'] > 0) {
                    $winTransaction = CmsUserTransaction::query()->create([
                        'cms_user_id' => $walletContext->ownerWalletId(),
                        'transactionId' => $this->ledgerTransactionId('win', $round->round_id),
                        'ref_transaction_id' => $debitTransaction->transactionId,
                        'round_id' => $round->round_id,
                        'provider_transaction_id' => $requestKey,
                        'game_provider_id' => $session->provider_id,
                        'game_id' => $session->cms_game_id,
                        'action' => TransactionActionEnum::Win,
                        'event_type' => 'internal_slot_win',
                        'status' => TransactionStatusEnum::Success,
                        'amount' => $result['winAmount'],
                        'currency' => $session->currency,
                        'source_wallet_account_id' => $walletContext->walletAccountId(),
                        'source_wallet_type' => $walletContext->walletType(),
                        'source_currency' => $walletContext->currency(),
                        'before_balance' => $balanceAfterDebit,
                        'after_balance' => $balanceAfterWin = (string) BigDecimal::of((string)$balanceAfterDebit)->plus((string)$result['winAmount']),
                        'acceptor' => 'api',
                    ]);

                    $this->wallets->projectDecimalBalance($walletContext, $balanceAfterWin);
                    $this->ledgerAudit->recordDecimal(
                        $walletContext,
                        $session,
                        $round,
                        $winTransaction,
                        'internal_game_win_paid',
                        'credit',
                        (float) $result['winAmount'],
                        $balanceAfterDebit,
                        $balanceAfterWin,
                        self::class,
                        [
                            'request_ref' => $requestKey,
                            'win_amount' => (float) $result['winAmount'],
                            'result_summary' => $result['resultSummary'] ?? null,
                        ]
                    );

                    $round->win_transaction_id = $winTransaction->id;
                    $this->audit->logRound($session, $round, 'internal_game.spin.paid', [
                        'request_ref' => $requestKey,
                        'transaction_id' => $winTransaction->transactionId,
                        'win_amount' => $result['winAmount'],
                    ] + $walletContext->auditPayload());
                }
            }

            $round->fill([
                'status' => $result['winAmount'] > 0 ? 'settled_win' : 'settled_loss',
                'win_amount' => $result['winAmount'],
                'raw_result' => $result,
                'result_summary' => $result['resultSummary'],
                'settled_at' => now(),
            ])->save();

            if($session->is_demo)$balanceAfterWin=$this->currentBalanceForSession($session);
            $session->forceFill(['last_activity_at' => now()])->save();

            $this->audit->logRound($session, $round, 'internal_game.spin.settled', [
                'request_ref' => $requestKey,
                'status' => $round->status,
                'bet_amount' => $betAmount,
                'win_amount' => $result['winAmount'],
                'final_balance' => $balanceAfterWin,
            ]);

            return $this->successResponse($round->fresh(['debitTransaction', 'winTransaction', 'refundTransaction']), $balanceAfterWin);
        });
    }

    public function refund(string $roundId, string $requestKey, ?CmsUser $operator = null): array
    {
        $requestKey = trim($requestKey);
        abort_unless($requestKey !== '', 422);

        return DB::transaction(function () use ($roundId, $requestKey, $operator) {
            $round = InternalGameRound::query()
                ->with(['session.game.provider', 'user', 'rootUser', 'refundTransaction', 'debitTransaction', 'winTransaction'])
                ->where('round_id', $roundId)
                ->lockForUpdate()
                ->firstOrFail();

            $session = $round->session;
            abort_unless($session instanceof InternalGameSession, 404);

            if ($round->refund_transaction_id || $round->status === 'refunded') {
                return $this->refundResponse($round, 'already_refunded');
            }

            abort_unless(in_array($round->status, ['processing', 'settled_loss'], true), 409);
            abort_unless((float) $round->win_amount <= 0 && !$round->win_transaction_id, 409);

            // Dice must never credit a processing round that was not actually debited.
            if (!$round->is_demo) {
                $debit = $round->debitTransaction;
                abort_unless($debit && $round->debit_transaction_id
                    && (int) $debit->action === TransactionActionEnum::Bet
                    && (int) $debit->status === TransactionStatusEnum::Success
                    && $debit->event_type === ($round->game_code==='dice'?'internal_dice_bet':'internal_slot_bet')
                    && $debit->round_id === $round->round_id
                    && (int) $debit->cms_user_id === (int) $round->cms_user_id
                    && $debit->currency === $round->currency
                    && \Brick\Math\BigDecimal::of((string) ($debit->exact_amount ?? $debit->amount))->isEqualTo((string) $round->bet_amount)
                    && (int) $debit->source_wallet_account_id === (int) data_get($session->launch_payload, 'wallet_account_id'), 409);
            }

            if ($round->is_demo) {
                $round->fill([
                    'status' => 'refunded',
                    'refund_amount' => $round->bet_amount,
                    'refunded_at' => now(),
                ])->save();

                $this->audit->logRound($session, $round, 'internal_game.round.refunded', [
                    'request_ref' => $requestKey,
                    'operator_id' => $operator?->id,
                    'refund_amount' => $round->bet_amount,
                ]);

                return $this->refundResponse($round, 'refunded');
            }

            $walletContext = $this->sessions->resolveWalletContextForSession($session, true);
            abort_unless($walletContext instanceof SpendableWalletContext, 404);
            $this->wallets->assertNoDrift($walletContext, 'internal_slot_before_refund', [
                'round_id' => $round->round_id,
            ]);

            $isDice = $round->game_code === 'dice';
            $beforeBalance = $this->wallets->currentDecimalBalance($walletContext);
            $afterBalance = (string) BigDecimal::of($beforeBalance)->plus((string)$round->bet_amount);
            $refundTransaction = CmsUserTransaction::query()->create([
                'cms_user_id' => $walletContext->ownerWalletId(),
                'transactionId' => $this->ledgerTransactionId('refund', $round->round_id),
                'ref_transaction_id' => $round->debitTransaction?->transactionId,
                'round_id' => $round->round_id,
                'provider_transaction_id' => $requestKey,
                'game_provider_id' => $round->provider_id,
                'game_id' => $round->cms_game_id,
                'action' => TransactionActionEnum::Rollback,
                'event_type' => $round->game_code === 'dice' ? 'internal_dice_refund' : 'internal_slot_refund',
                'status' => TransactionStatusEnum::Success,
                'amount' => $round->bet_amount,
                'currency' => $round->currency,
                'source_wallet_account_id' => $walletContext->walletAccountId(),
                'source_wallet_type' => $walletContext->walletType(),
                'source_currency' => $walletContext->currency(),
                'before_balance' => $beforeBalance,
                'after_balance' => $afterBalance,
                'acceptor' => 'operator',
            ]);

            $this->wallets->projectDecimalBalance($walletContext,$afterBalance);
            $ledgerMethod = 'recordDecimal';
            $this->ledgerAudit->$ledgerMethod(
                $walletContext,
                $session,
                $round,
                $refundTransaction,
                'internal_game_refund_paid',
                'credit',
                (string) $round->bet_amount,
                $beforeBalance,
                $afterBalance,
                self::class,
                [
                    'request_ref' => $requestKey,
                    'refund_amount' => (float) $round->bet_amount,
                    'operator_id' => $operator?->id,
                ]
            );

            $round->fill([
                'status' => 'refunded',
                'refund_amount' => $round->bet_amount,
                'refund_transaction_id' => $refundTransaction->id,
                'refunded_at' => now(),
            ])->save();

            $this->audit->logRound($session, $round, 'internal_game.round.refunded', [
                'request_ref' => $requestKey,
                'operator_id' => $operator?->id,
                'refund_transaction_id' => $refundTransaction->transactionId,
                'refund_amount' => $round->bet_amount,
            ] + $walletContext->auditPayload());

            return $this->refundResponse($round->fresh(['refundTransaction']), 'refunded');
        });
    }

    public function playPayload(InternalGameSession $session): array
    {
        $definition = $this->catalog->definitionByCode($session->game_code);
        abort_unless(is_array($definition), 404);

        $preview = !$session->exists;
        return [
            'preview' => $preview,
            'sessionToken' => $session->session_token,
            'sessionId' => $session->public_id,
            'gameCode' => $session->game_code,
            'gameName' => $definition['name'] ?? $session->game?->name,
            'currency' => $session->currency,
            'isDemo' => $session->is_demo,
            'balance' => $preview ? null : (float)$this->currentBalanceForSession($session),
            'balanceExact' => $preview ? null : (string)$this->currentBalanceForSession($session),
            'snapshotRoute' => route('cms.internalGames.slot.snapshot'),
            'history' => $preview ? [] : $this->history($session),
            'latestRound' => $preview ? null : $this->latestRound($session),
            'betValues' => array_map('floatval', (array) ($definition['bet_values'] ?? [])),
            'symbolAssets' => (array) ($definition['symbol_assets'] ?? []),
            'audioAssets' => (array) ($definition['audio_assets'] ?? []),
            'paytable' => (array) ($definition['paytable'] ?? []),
            'spinRoute' => route('cms.internalGames.spin'),
        ];
    }

    public function surfaceViewForSession(InternalGameSession $session): string
    {
        $definition = $this->catalog->definitionByCode((string) $session->game_code);

        return is_array($definition)
            ? (string) ($definition['surface_view'] ?? 'game-provider-front.internal-mini-slot')
            : 'game-provider-front.internal-mini-slot';
    }

    public function snapshot(string $token, ?CmsUser $user):array { $s=$this->sessions->resolvePlayableSession($token,$user);abort_unless($this->supportsGameCode($s->game_code),404);return ['balance'=>(float)$this->currentBalanceForSession($s),'balanceExact'=>(string)$this->currentBalanceForSession($s),'currency'=>$s->currency,'history'=>$this->history($s),'latestRound'=>$this->latestRound($s)]; }
    private function history(InternalGameSession $s):array {return InternalGameRound::with(['debitTransaction','winTransaction','refundTransaction'])->where('internal_game_session_id',$s->id)->latest('id')->limit(30)->get()->map(fn($r)=>$this->successResponse($r,null)+['requestRef'=>$r->request_ref])->all();}
    private function latestRound(InternalGameSession $s):?array {return $this->history($s)[0]??null;}
    private function ledgerTransactionId(string $prefix, string $roundId): string
    {
        return sprintf('%s_%s', $prefix, $roundId);
    }

    private function successResponse(InternalGameRound $round, string|float|null $balance): array
    {
        return [
            'roundId' => $round->round_id,
            'requestRef' => $round->request_ref,
            'status' => $round->status,
            'betAmount' => (float) $round->bet_amount,
            'winAmount' => (float) $round->win_amount,
            'refundAmount' => (float) $round->refund_amount,
            'currency' => $round->currency,
            'balance' => (float)$balance,
            'balanceExact' => (string)$balance,
            'result' => $round->raw_result,
            'summary' => $round->result_summary,
            'transactions' => [
                'bet' => $round->debitTransaction?->transactionId,
                'win' => $round->winTransaction?->transactionId,
                'refund' => $round->refundTransaction?->transactionId,
            ],
        ];
    }

    private function refundResponse(InternalGameRound $round, string $result): array
    {
        return [
            'result' => $result,
            'roundId' => $round->round_id,
            'requestRef' => $round->request_ref,
            'status' => $round->status,
            'refundAmount' => (float) $round->refund_amount,
            'refundTransactionId' => $round->refundTransaction?->transactionId,
        ];
    }

    private function currentBalanceForSession(InternalGameSession $session): string|float|null
    {
        if ($session->is_demo) {
            return $this->demoBalanceForSession($session);
        }

        $walletContext = $this->sessions->resolveWalletContextForSession($session);

        return $walletContext instanceof SpendableWalletContext
            ? $this->wallets->currentDecimalBalance($walletContext)
            : null;
    }

    private function demoBalanceForSession(InternalGameSession $session): string
    {
        $baseBalance = (string) config('internal_games.demo_balance', 1000);
        $roundDelta = (string) InternalGameRound::query()
            ->where('internal_game_session_id', $session->id)
            ->selectRaw('COALESCE(SUM(win_amount + refund_amount - bet_amount), 0) as balance_delta')
            ->value('balance_delta');

        return (string) BigDecimal::of($baseBalance)->plus($roundDelta)->toScale(2);
    }
}
