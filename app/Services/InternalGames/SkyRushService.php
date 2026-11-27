<?php

namespace App\Services\InternalGames;

use App\Enum\TransactionActionEnum;
use App\Enum\TransactionStatusEnum;
use App\Models\CmsUser;
use App\Models\CmsUserTransaction;
use App\Models\InternalGameCrashRound;
use App\Models\InternalGameRound;
use App\Models\InternalGameSession;
use App\Services\Wallet\CanonicalWalletContext;
use App\Services\Wallet\CanonicalWalletService;
use App\Services\Wallet\SpendableWalletContext;
use Carbon\CarbonInterface;
use Brick\Math\BigDecimal;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SkyRushService
{
    public function __construct(
        private InternalGameCatalog $catalog,
        private InternalGameSessionService $sessions,
        private InternalGameAuditService $audit,
        private SkyRushEngine $engine,
        private CanonicalWalletService $wallets,
        private InternalGameLedgerAuditService $ledgerAudit
    ) {
    }


    private function inGameTransaction(int $gameId, callable $work): array {
        return DB::transaction(function () use ($gameId,$work) {
            \App\Models\CmsGame::query()->whereKey($gameId)->lockForUpdate()->firstOrFail();
            return $work();
        },3);
    }
    public function playPayload(InternalGameSession $session): array
    {
        if (!$session->exists) {
            $definition = $this->definitionForSession($session);
            return [
                'preview' => true, 'gameName' => $definition['name'], 'gameCode' => $session->game_code,
                'display' => $definition['display'] ?? [], 'betValues' => [], 'balance' => null,
                'currency' => null, 'sessionId' => null, 'isDemo' => false,
            ];
        }

        return $this->inGameTransaction($session->cms_game_id, fn () => $this->playPayloadUnlocked($session));
    }
    public function snapshot(string $token, ?CmsUser $user): array {
        $s=$this->sessions->resolvePlayableSession($token,$user);
        return $this->inGameTransaction($s->cms_game_id,fn()=>$this->snapshotUnlocked($token,$user));
    }
    public function placeBet(string $token, ?CmsUser $user, float $amount, string $key, int $lane=1, ?string $expectedRoundId=null): array {
        $s=$this->sessions->resolvePlayableSession($token,$user);
        return $this->inGameTransaction($s->cms_game_id,fn()=>$this->placeBetUnlocked($token,$user,$amount,$key,$lane,$expectedRoundId));
    }
    public function cashout(string $token, ?CmsUser $user, string $roundId, string $key): array {
        $s=$this->sessions->resolvePlayableSession($token,$user);
        // Commit elapsed lifecycle before evaluating a possibly rejected cashout. Never advance this bet to a successor.
        $this->inGameTransaction($s->cms_game_id,function() use($s,$roundId){
            $r=InternalGameRound::where('internal_game_session_id',$s->id)->where('round_id',$roundId)->firstOrFail();
            $this->syncSpecificRound($r->crashRound,$this->definitionForSession($s),false);return [];
        });
        return $this->inGameTransaction($s->cms_game_id,fn()=>$this->cashoutUnlocked($token,$user,$roundId,$key));
    }
    public function refund(string $roundId, string $key, ?CmsUser $operator=null): array {
        $r=InternalGameRound::where('round_id',$roundId)->firstOrFail();
        abort_unless($this->supportsGameCode($r->game_code),404);
        return $this->inGameTransaction($r->cms_game_id,fn()=>$this->refundUnlocked($roundId,$key,$operator));
    }

    private function playPayloadUnlocked(InternalGameSession $session): array
    {
        $definition = $this->definitionForSession($session);
        $round = $this->syncCurrentRound($session, $definition);

        $myBets = $this->playerRoundsPayload($session, $round);

        return [
            'sessionToken' => $session->session_token,
            'sessionId' => $session->public_id,
            'gameCode' => $session->game_code,
            'gameName' => $definition['name'] ?? 'SkyRush',
            'serverTime' => now()->toISOString(),
            'currency' => $session->currency,
            'isDemo' => $session->is_demo,
            'balance' => (float)$this->currentBalanceForSession($session),
            'balanceExact' => (string)$this->currentBalanceForSession($session),
            'betValues' => array_map('floatval', (array) ($definition['bet_values'] ?? [])),
            'snapshotRoute' => route('cms.internalGames.skyRush.snapshot'),
            'betRoute' => route('cms.internalGames.skyRush.bet'),
            'cashoutRoute' => route('cms.internalGames.skyRush.cashout'),
            'snapshotIntervalMs' => (int) ($definition['snapshot_interval_ms'] ?? 750),
            'timing' => [
                'bettingWindowSeconds' => max(1, (int) ($definition['betting_window_seconds'] ?? 4)),
                'postCrashCooldownSeconds' => max(1, (int) ($definition['post_crash_cooldown_seconds'] ?? 2)),
            ],
            'flightMath' => [
                'growthRate' => (float) ($definition['growth_rate'] ?? 0.55),
                'growthScale' => (float) ($definition['growth_scale'] ?? 0.35),
            ],
            'display' => (array) ($definition['display'] ?? []),
            'currentRound' => $this->crashRoundPayload($round, $definition, now()),
            'history' => $this->historyPayload($session, $definition),
            'myBet' => $this->primaryPlayerRoundPayload($myBets),
            'myBets' => $myBets,
            'publicFeed' => $this->publicFeedPayload($round),
            'myBetHistory' => $this->myBetHistoryPayload($session, $definition),
        ];
    }

    public function supportsGameCode(?string $gameCode): bool
    {
        if (!is_string($gameCode) || trim($gameCode) === '') {
            return false;
        }

        $definition = $this->catalog->definitionByCode($gameCode);

        return is_array($definition) && (string) ($definition['engine'] ?? '') === 'crash-v1';
    }

    public function surfaceViewForSession(InternalGameSession $session): string
    {
        $definition = $this->definitionForSession($session);

        return (string) ($definition['surface_view'] ?? 'game-provider-front.internal-skyrush');
    }

    private function snapshotUnlocked(string $sessionToken, ?CmsUser $user): array
    {
        $session = $this->sessions->resolvePlayableSession($sessionToken, $user);
        $definition = $this->definitionForSession($session);
        $round = $this->syncCurrentRound($session, $definition);

        $myBets = $this->playerRoundsPayload($session, $round);

        return [
            'serverTime' => now()->toISOString(),
            'balance' => (float)$this->currentBalanceForSession($session),
            'balanceExact' => (string)$this->currentBalanceForSession($session),
            'currency' => $session->currency,
            'currentRound' => $this->crashRoundPayload($round, $definition, now()),
            'history' => $this->historyPayload($session, $definition),
            'myBet' => $this->primaryPlayerRoundPayload($myBets),
            'myBets' => $myBets,
            'publicFeed' => $this->publicFeedPayload($round),
            'myBetHistory' => $this->myBetHistoryPayload($session, $definition),
        ];
    }

    private function placeBetUnlocked(string $sessionToken, ?CmsUser $user, float $betAmount, string $requestKey, int $lane = 1, ?string $expectedRoundId = null): array
    {
        $requestKey = trim($requestKey);
        abort_unless($requestKey !== '', 422);
        $lane = $this->normalizeBetLane($lane);

        $session = $this->sessions->resolvePlayableSession($sessionToken, $user);
        $this->sessions->assertProductAccessAllowed($session, $user);
        $definition = $this->definitionForSession($session);
        $allowedBets = array_map('floatval', (array) ($definition['bet_values'] ?? []));
        abort_unless(in_array($betAmount, $allowedBets, true), 422);
        $requestFingerprint = $this->betRequestFingerprint($betAmount, $lane);

        $duplicate = InternalGameRound::query()
            ->with(['debitTransaction', 'winTransaction', 'refundTransaction', 'crashRound'])
            ->where('internal_game_session_id', $session->id)
            ->where('request_ref', $requestKey)
            ->first();

        if ($duplicate instanceof InternalGameRound) {
            $this->assertBetReplayMatches($duplicate, $requestFingerprint);
            abort_if($expectedRoundId !== null && $duplicate->crashRound?->round_public_id !== $expectedRoundId,409,'Request round conflict.');
            return $this->roundResponse($duplicate, $this->currentBalanceForSession($session));
        }

        return DB::transaction(function () use ($session, $definition, $betAmount, $requestKey, $lane, $requestFingerprint, $expectedRoundId) {
            $this->sessions->assertCasinoCountryAllowedForNewGameplay();
            $crashRound = $this->syncCurrentRound($session, $definition, true);
            abort_if($expectedRoundId !== null && $crashRound->round_public_id !== $expectedRoundId,409,'Betting round changed. Refresh authoritative state.');
            abort_if($crashRound->status !== 'betting', 409, 'Betting window already closed for this round.');
            abort_if(!$crashRound->betting_closes_at?->isFuture(), 409, 'Betting window already closed for this round.');

            $existingLaneBet = InternalGameRound::query()
                ->where('internal_game_session_id', $session->id)
                ->where('internal_game_crash_round_id', $crashRound->id)
                ->where('bet_lane', $lane)
                ->first();

            if ($existingLaneBet instanceof InternalGameRound) {
                abort(409, sprintf('Active bet already exists for lane %d in this round.', $lane));
            }

            try {
                $round = InternalGameRound::query()->create([
                    'internal_game_session_id' => $session->id,
                    'internal_game_crash_round_id' => $crashRound->id,
                    'provider_id' => $session->provider_id,
                    'cms_game_id' => $session->cms_game_id,
                    'cms_user_id' => $session->cms_user_id,
                    'root_user_id' => $session->root_user_id,
                    'round_id' => 'igr_' . Str::upper(Str::random(18)),
                    'game_code' => $session->game_code,
                    'session_public_id' => $session->public_id,
                    'status' => 'bet_accepted',
                    'currency' => $session->currency,
                    'is_demo' => $session->is_demo,
                    'bet_lane' => $lane,
                    'bet_amount' => $betAmount,
                    'win_amount' => 0,
                    'refund_amount' => 0,
                    'cashout_multiplier' => 0,
                    'cashout_amount' => 0,
                    'request_ref' => $requestKey,
                    'correlation_ref' => (string) Str::uuid(),
                    'raw_result' => [
                        'betLane' => $lane,
                        'crashRoundId' => $crashRound->round_public_id,
                        'crashRoundStatus' => $crashRound->status,
                        'requestFingerprint' => $requestFingerprint,
                    ],
                    'result_summary' => sprintf('Lane %d bet accepted for crash round %s', $lane, $crashRound->round_public_id),
                    'started_at' => now(),
                ]);
            } catch (QueryException $exception) {
                $duplicateRound = InternalGameRound::query()
                    ->where('internal_game_session_id', $session->id)
                    ->where('request_ref', $requestKey)
                    ->first();

                if ($duplicateRound instanceof InternalGameRound) {
                    $this->assertBetReplayMatches($duplicateRound, $requestFingerprint);
                    return $this->roundResponse($duplicateRound, $this->currentBalanceForSession($session));
                }

                throw $exception;
            }

            $this->audit->logRound($session, $round, 'internal_game.crash.bet.requested', [
                'request_ref' => $requestKey,
                'correlation_ref' => $round->correlation_ref,
                'bet_amount' => $betAmount,
                'bet_lane' => $lane,
                'crash_round_id' => $crashRound->round_public_id,
            ]);

            $this->sessions->assertNewGameplayAllowed($session);
            $currentBalance = $session->is_demo ? (string) BigDecimal::of((string)$this->currentBalanceForSession($session))->plus((string)$betAmount) : $this->currentBalanceForSession($session);
            $walletContext = null;

            if ($session->is_demo) {
                abort_unless(BigDecimal::of((string)$currentBalance)->isGreaterThanOrEqualTo((string)$betAmount), 409);
            } else {
                $walletContext = $this->resolveCanonicalWalletContextForSession($session, true);
                abort_unless($walletContext instanceof CanonicalWalletContext, 404);
                $this->wallets->assertNoDrift($walletContext, 'internal_crash_before_bet', [
                    'session_id' => $session->id,
                    'game_code' => $session->game_code,
                ]);
                $currentBalance = $this->wallets->currentDecimalBalance($walletContext);
                abort_unless(BigDecimal::of((string)$currentBalance)->isGreaterThanOrEqualTo((string)$betAmount), 409);
            }

            $balanceAfterDebit = (string) BigDecimal::of((string)$currentBalance)->minus((string)$betAmount);
            if (!$session->is_demo) {
                $debitTransaction = CmsUserTransaction::query()->create([
                    'cms_user_id' => $walletContext->ownerWalletId(),
                    'transactionId' => $this->ledgerTransactionId('bet', $round->round_id),
                    'round_id' => $round->round_id,
                    'provider_transaction_id' => $requestKey,
                    'game_provider_id' => $session->provider_id,
                    'game_id' => $session->cms_game_id,
                    'action' => TransactionActionEnum::Bet,
                    'event_type' => 'internal_crash_bet',
                    'status' => TransactionStatusEnum::Success,
                    'amount' => $betAmount,
                    'currency' => $session->currency,
                    'before_balance' => $currentBalance,
                    'after_balance' => $balanceAfterDebit,
                    'source_wallet_account_id' => $walletContext->walletAccountId(),
                    'source_wallet_type' => 'primary_balance',
                    'source_currency' => $session->currency,
                    'acceptor' => 'api',
                ]);

                $this->wallets->projectDecimalBalance($walletContext, $balanceAfterDebit);
                $this->ledgerAudit->recordDecimal(
                    $this->spendableWalletContextForLedger($session),
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
                        'bet_lane' => $lane,
                        'crash_round_id' => $crashRound->round_public_id,
                    ]
                );

                $round->debit_transaction_id = $debitTransaction->id;
                $this->audit->logRound($session, $round, 'internal_game.crash.bet.debited', [
                    'request_ref' => $requestKey,
                    'transaction_id' => $debitTransaction->transactionId,
                    'before_balance' => $currentBalance,
                    'after_balance' => $balanceAfterDebit,
                    'bet_lane' => $lane,
                    'crash_round_id' => $crashRound->round_public_id,
                ] + $walletContext->auditPayload());
            }

            $round->save();
            $this->audit->logRound($session, $round, 'internal_game.crash.bet.accepted', [
                'request_ref' => $requestKey,
                'crash_round_id' => $crashRound->round_public_id,
                'status' => $round->status,
                'bet_amount' => $betAmount,
                'bet_lane' => $lane,
            ]);

            return $this->roundResponse($round->fresh(['debitTransaction', 'winTransaction', 'refundTransaction', 'crashRound']), $this->currentBalanceForSession($session));
        });
    }

    private function cashoutUnlocked(string $sessionToken, ?CmsUser $user, string $betRoundId, string $requestKey): array
    {
        $requestKey = trim($requestKey);
        abort_unless($requestKey !== '', 422);

        $session = $this->sessions->resolvePlayableSession($sessionToken, $user);
        $definition = $this->definitionForSession($session);
        try {
            return DB::transaction(function () use ($session, $definition, $betRoundId, $requestKey) {
                /** @var InternalGameRound $round */
                $round = InternalGameRound::query()
                    ->with(['debitTransaction', 'winTransaction', 'refundTransaction', 'crashRound'])
                    ->where('internal_game_session_id', $session->id)
                    ->where('round_id', $betRoundId)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($round->cashout_request_ref === $requestKey || $round->status === 'settled_win') {
                    return $this->roundResponse($round, $this->currentBalanceForSession($session));
                }

                $crashRound = $round->crashRound ?: InternalGameCrashRound::query()->lockForUpdate()->findOrFail($round->internal_game_crash_round_id);
                $crashRound = $this->syncSpecificRound($crashRound, $definition, false);
                $this->assertRoundCanCashout($round, $session, $requestKey, $crashRound);
                if ($crashRound->status !== 'flying') {
                    $this->rejectCashoutAttempt(
                        $session,
                        $round,
                        $requestKey,
                        'round_not_flying',
                        'Cash out istegi round uctuktan sonra sunucuya ulasti.',
                        $crashRound
                    );
                }

                $now = now();
                if (!$crashRound->crashed_at instanceof CarbonInterface || !$now->lt($crashRound->crashed_at)) {
                    $this->rejectCashoutAttempt(
                        $session,
                        $round,
                        $requestKey,
                        'cashout_window_closed',
                        'Cash out penceresi kapandi; istek sunucuya crash sonrasinda ulasti.',
                        $crashRound,
                        [
                            'server_now' => $now->toISOString(),
                            'crashed_at' => optional($crashRound->crashed_at)->toISOString(),
                        ]
                    );
                }

            $currentMultiplier = $this->currentMultiplierForMoment($crashRound, $definition, $now);
            $cashoutAmount = round((float) $round->bet_amount * $currentMultiplier, 2);
            $balanceAfterWin = $this->currentBalanceForSession($session);

            $this->audit->logRound($session, $round, 'internal_game.crash.cashout.requested', [
                'request_ref' => $requestKey,
                'crash_round_id' => $crashRound->round_public_id,
                'bet_lane' => $this->betLaneForRound($round),
                'cashout_multiplier' => $currentMultiplier,
            ]);

            if (!$session->is_demo) {
                $walletContext = $this->resolveCanonicalWalletContextForSession($session, true);
                abort_unless($walletContext instanceof CanonicalWalletContext, 404);
                $this->wallets->assertNoDrift($walletContext, 'internal_crash_before_cashout', [
                    'round_id' => $round->round_id,
                ]);

                $this->assertDebitProvenance($round,$walletContext);
                $beforeBalance = $this->wallets->currentDecimalBalance($walletContext);
                $afterBalance = (string) BigDecimal::of((string)$beforeBalance)->plus((string)$cashoutAmount);
                $winTransaction = CmsUserTransaction::query()->create([
                    'cms_user_id' => $walletContext->ownerWalletId(),
                    'transactionId' => $this->ledgerTransactionId('cashout', $round->round_id),
                    'ref_transaction_id' => $round->debitTransaction?->transactionId,
                    'round_id' => $round->round_id,
                    'provider_transaction_id' => $requestKey,
                    'game_provider_id' => $session->provider_id,
                    'game_id' => $session->cms_game_id,
                    'action' => TransactionActionEnum::Win,
                    'event_type' => 'internal_crash_cashout',
                    'status' => TransactionStatusEnum::Success,
                    'amount' => $cashoutAmount,
                    'currency' => $session->currency,
                    'before_balance' => $beforeBalance,
                    'after_balance' => $afterBalance,
                    'source_wallet_account_id' => $walletContext->walletAccountId(),
                    'source_wallet_type' => 'primary_balance',
                    'source_currency' => $session->currency,
                    'acceptor' => 'api',
                ]);

                $this->wallets->projectDecimalBalance($walletContext, $afterBalance);
                $this->ledgerAudit->recordDecimal(
                    $this->spendableWalletContextForLedger($session),
                    $session,
                    $round,
                    $winTransaction,
                    'internal_game_win_paid',
                    'credit',
                    $cashoutAmount,
                    $beforeBalance,
                    $afterBalance,
                    self::class,
                    [
                        'request_ref' => $requestKey,
                        'bet_lane' => $this->betLaneForRound($round),
                        'cashout_multiplier' => $currentMultiplier,
                        'crash_round_id' => $crashRound->round_public_id,
                    ]
                );

                $round->win_transaction_id = $winTransaction->id;
                $balanceAfterWin = $afterBalance;

                $this->audit->logRound($session, $round, 'internal_game.crash.cashout.paid', [
                    'request_ref' => $requestKey,
                    'transaction_id' => $winTransaction->transactionId,
                    'cashout_multiplier' => $currentMultiplier,
                    'cashout_amount' => $cashoutAmount,
                    'before_balance' => $beforeBalance,
                    'after_balance' => $afterBalance,
                    'bet_lane' => $this->betLaneForRound($round),
                    'crash_round_id' => $crashRound->round_public_id,
                ] + $walletContext->auditPayload());
            }

            $betLane = $this->betLaneForRound($round);
            $round->fill([
                'status' => 'settled_win',
                'cashout_request_ref' => $requestKey,
                'cashout_multiplier' => $currentMultiplier,
                'cashout_amount' => $cashoutAmount,
                'win_amount' => $cashoutAmount,
                'result_summary' => sprintf('Lane %d cashed out at %.2fx', $betLane, $currentMultiplier),
                'raw_result' => array_merge((array) $round->raw_result, [
                    'betLane' => $betLane,
                    'crashRoundId' => $crashRound->round_public_id,
                    'cashoutMultiplier' => $currentMultiplier,
                    'cashoutAmount' => $cashoutAmount,
                ]),
                'cashed_out_at' => $now,
                'settled_at' => $now,
            ])->save();

                return $this->roundResponse($round->fresh(['debitTransaction', 'winTransaction', 'refundTransaction', 'crashRound']), $session->is_demo ? $this->currentBalanceForSession($session) : $balanceAfterWin);
            });
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $round = InternalGameRound::query()
                ->with(['crashRound'])
                ->where('internal_game_session_id', $session->id)
                ->where('round_id', $betRoundId)
                ->first();

            if ($round instanceof InternalGameRound) {
                $crashRound = $round->crashRound;
                $message = $exception->getMessage() !== '' ? $exception->getMessage() : 'Cash out rejected.';

                $this->audit->logRound($session, $round, 'internal_game.crash.cashout.rejected', [
                    'request_ref' => $requestKey,
                    'reason_code' => $this->cashoutRejectReasonCode($message),
                    'message' => $message,
                    'round_status' => $round->status,
                    'bet_lane' => $this->betLaneForRound($round),
                    'bet_amount' => (float) $round->bet_amount,
                    'cashout_request_ref' => $round->cashout_request_ref,
                    'crash_round_id' => $crashRound?->round_public_id,
                    'crash_round_status' => $crashRound?->status,
                    'crash_multiplier' => $crashRound ? (float) $crashRound->crash_multiplier : null,
                    'crashed_at' => optional($crashRound?->crashed_at)->toISOString(),
                    'server_now' => now()->toISOString(),
                ]);
            }

            throw $exception;
        }
    }

    private function refundUnlocked(string $roundId, string $requestKey, ?CmsUser $operator = null): array
    {
        $requestKey = trim($requestKey);
        abort_unless($requestKey !== '', 422);

        return DB::transaction(function () use ($roundId, $requestKey, $operator) {
            /** @var InternalGameRound $round */
            $round = InternalGameRound::query()
                ->with(['session', 'user', 'rootUser', 'refundTransaction', 'debitTransaction', 'winTransaction'])
                ->where('round_id', $roundId)
                ->lockForUpdate()
                ->firstOrFail();

            $session = $round->session;
            abort_unless($session instanceof InternalGameSession, 404);

            if ($round->refund_transaction_id || $round->status === 'refunded') {
                return $this->refundResponse($round, 'already_refunded');
            }

            if (!$round->is_demo && !$round->debit_transaction_id) {
                abort(409, 'A real-money Sky Rush round cannot be refunded before the debit transaction exists.');
            }

            abort_if($round->win_transaction_id || (float) $round->win_amount > 0, 409);
            abort_unless(in_array($round->status, ['bet_accepted', 'settled_loss', 'processing'], true), 409);
            abort_unless((float) $round->win_amount <= 0, 409);

            if ($round->is_demo) {
                $round->fill([
                    'status' => 'refunded',
                    'refund_amount' => $round->bet_amount,
                    'refunded_at' => now(),
                    'result_summary' => 'Demo refund applied',
                ])->save();

                $this->audit->logRound($session, $round, 'internal_game.round.refunded', [
                    'request_ref' => $requestKey,
                    'operator_id' => $operator?->id,
                    'refund_amount' => $round->bet_amount,
                ]);

                return $this->refundResponse($round, 'refunded');
            }

            $walletContext = $this->resolveCanonicalWalletContextForSession($session, true);
            abort_unless($walletContext instanceof CanonicalWalletContext, 404);
            $this->wallets->assertNoDrift($walletContext, 'internal_crash_before_refund', [
                'round_id' => $round->round_id,
            ]);

            $this->assertDebitProvenance($round,$walletContext);
                $beforeBalance = $this->wallets->currentDecimalBalance($walletContext);
            $afterBalance = (string) BigDecimal::of((string)$beforeBalance)->plus((string)$round->bet_amount);
            $refundTransaction = CmsUserTransaction::query()->create([
                'cms_user_id' => $walletContext->ownerWalletId(),
                'transactionId' => $this->ledgerTransactionId('refund', $round->round_id),
                'ref_transaction_id' => $round->debitTransaction?->transactionId,
                'round_id' => $round->round_id,
                'provider_transaction_id' => $requestKey,
                'game_provider_id' => $round->provider_id,
                'game_id' => $round->cms_game_id,
                'action' => TransactionActionEnum::Rollback,
                'event_type' => 'internal_crash_refund',
                'status' => TransactionStatusEnum::Success,
                'amount' => $round->bet_amount,
                'currency' => $round->currency,
                'before_balance' => $beforeBalance,
                'after_balance' => $afterBalance,
                'source_wallet_account_id' => $walletContext->walletAccountId(),
                    'source_wallet_type' => 'primary_balance',
                    'source_currency' => $session->currency,
                    'acceptor' => 'operator',
            ]);

            $this->wallets->projectDecimalBalance($walletContext, $afterBalance);
            $this->ledgerAudit->recordDecimal(
                $this->spendableWalletContextForLedger($session),
                $session,
                $round,
                $refundTransaction,
                'internal_game_refund_paid',
                'credit',
                (float) $round->bet_amount,
                $beforeBalance,
                $afterBalance,
                self::class,
                [
                    'request_ref' => $requestKey,
                    'bet_lane' => $this->betLaneForRound($round),
                    'refund_amount' => (float) $round->bet_amount,
                    'operator_id' => $operator?->id,
                ]
            );

            $round->fill([
                'status' => 'refunded',
                'refund_amount' => $round->bet_amount,
                'refund_transaction_id' => $refundTransaction->id,
                'refunded_at' => now(),
                'result_summary' => 'Operator refund applied',
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

    private function definitionForSession(InternalGameSession $session): array
    {
        $definition = $this->catalog->definitionByCode($session->game_code);
        abort_unless(is_array($definition) && (string) ($definition['engine'] ?? '') === 'crash-v1', 404);

        return $definition;
    }

    private function syncCurrentRound(InternalGameSession $session, array $definition, bool $forBetPlacement = false): InternalGameCrashRound
    {
        $round = InternalGameCrashRound::query()
            ->where('cms_game_id', $session->cms_game_id)
            ->where('game_code', $session->game_code)
            ->where('currency', $session->currency)
            ->where('is_demo', $session->is_demo)
            ->latest('id')
            ->first();

        if (!$round instanceof InternalGameCrashRound) {
            return $this->createNextCrashRound($session, $definition);
        }

        $round = $this->syncSpecificRound($round, $definition);

        if (!$forBetPlacement && $round->status === 'crashed') {
            return $this->syncSpecificRound($round, $definition);
        }

        return $round;
    }

    private function syncSpecificRound(InternalGameCrashRound $round, array $definition, bool $advance = true): InternalGameCrashRound
    {
        $now = now();
        $dirty = false;

        if ($round->status === 'betting' && $round->starts_at instanceof CarbonInterface && $now->gte($round->starts_at)) {
            $round->status = $round->crashed_at instanceof CarbonInterface && $now->lt($round->crashed_at) ? 'flying' : 'crashed';
            $dirty = true;
        }

        if (in_array($round->status, ['betting', 'flying'], true) && $round->crashed_at instanceof CarbonInterface && $now->gte($round->crashed_at)) {
            $round->status = 'crashed';
            $round->settled_at = $round->settled_at ?: $round->crashed_at;
            $dirty = true;
        }

        if ($dirty) {
            $round->save();
        }

        if ($round->status === 'crashed') {
            if (!$round->settled_at) {$round->settled_at=$round->crashed_at;$round->save();}
            $this->settleOutstandingLosses($round);
            $cooldownSeconds = max(1, (int) ($definition['post_crash_cooldown_seconds'] ?? 2));
            if ($advance && $round->crashed_at instanceof CarbonInterface && $now->gte($round->crashed_at->copy()->addSeconds($cooldownSeconds))) {
                return $this->createNextCrashRoundFromRound($round, $definition);
            }
        }

        return $round->fresh();
    }

    private function createNextCrashRound(InternalGameSession $session, array $definition): InternalGameCrashRound
    {
        return DB::transaction(function () use ($session, $definition) {
            $existing = InternalGameCrashRound::query()
                ->where('cms_game_id', $session->cms_game_id)
                ->where('game_code', $session->game_code)
                ->where('currency', $session->currency)
                ->where('is_demo', $session->is_demo)
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($existing instanceof InternalGameCrashRound) {
                return $existing;
            }

            $baseTime = now();
            $roundPublicId = 'igcr_' . Str::upper(Str::random(14));
            $math = $this->engine->createRoundMath($definition, $roundPublicId);
            $bettingWindowSeconds = max(1, (int) ($definition['betting_window_seconds'] ?? 4));

            return InternalGameCrashRound::query()->create([
                'provider_id' => $session->provider_id,
                'cms_game_id' => $session->cms_game_id,
                'round_public_id' => $roundPublicId,
                'game_code' => $session->game_code,
                'status' => 'betting',
                'currency' => $session->currency,
                'is_demo' => $session->is_demo,
                'crash_multiplier' => $math['crash_multiplier'],
                'server_seed' => $math['server_seed'],
                'server_seed_hash' => $math['server_seed_hash'],
                'fair_hash' => $math['fair_hash'],
                'betting_opens_at' => $baseTime,
                'betting_closes_at' => $baseTime->copy()->addSeconds($bettingWindowSeconds),
                'starts_at' => $baseTime->copy()->addSeconds($bettingWindowSeconds),
                'crashed_at' => $baseTime->copy()->addSeconds($bettingWindowSeconds)->addMilliseconds((int) round($math['flight_seconds'] * 1000)),
                'raw_result' => [
                    'engine' => (string) ($definition['engine'] ?? 'crash-v1'),
                    'server_seed_hash' => $math['server_seed_hash'],
                    'fair_hash' => $math['fair_hash'],
                    'source_reference' => $definition['source_reference'] ?? null,
                ],
            ]);
        });
    }

    private function createNextCrashRoundFromRound(InternalGameCrashRound $previousRound, array $definition): InternalGameCrashRound
    {
        return DB::transaction(function () use ($previousRound, $definition) {
            $latestRound = InternalGameCrashRound::query()
                ->where('cms_game_id', $previousRound->cms_game_id)
                ->where('game_code', $previousRound->game_code)
                ->where('currency', $previousRound->currency)
                ->where('is_demo', $previousRound->is_demo)
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($latestRound instanceof InternalGameCrashRound && (int) $latestRound->id !== (int) $previousRound->id) {
                return $latestRound;
            }

            $cooldownSeconds = max(1, (int) ($definition['post_crash_cooldown_seconds'] ?? 2));
            $baseMoment = ($previousRound->crashed_at ?: now())->copy()->addSeconds($cooldownSeconds);
            if ($baseMoment->isPast()) {
                $baseMoment = now();
            }

            $roundPublicId = 'igcr_' . Str::upper(Str::random(14));
            $math = $this->engine->createRoundMath($definition, $roundPublicId);
            $bettingWindowSeconds = max(1, (int) ($definition['betting_window_seconds'] ?? 4));

            return InternalGameCrashRound::query()->create([
                'provider_id' => $previousRound->provider_id,
                'cms_game_id' => $previousRound->cms_game_id,
                'round_public_id' => $roundPublicId,
                'game_code' => $previousRound->game_code,
                'status' => 'betting',
                'currency' => $previousRound->currency,
                'is_demo' => $previousRound->is_demo,
                'crash_multiplier' => $math['crash_multiplier'],
                'server_seed' => $math['server_seed'],
                'server_seed_hash' => $math['server_seed_hash'],
                'fair_hash' => $math['fair_hash'],
                'betting_opens_at' => $baseMoment,
                'betting_closes_at' => $baseMoment->copy()->addSeconds($bettingWindowSeconds),
                'starts_at' => $baseMoment->copy()->addSeconds($bettingWindowSeconds),
                'crashed_at' => $baseMoment->copy()->addSeconds($bettingWindowSeconds)->addMilliseconds((int) round($math['flight_seconds'] * 1000)),
                'raw_result' => [
                    'engine' => (string) ($definition['engine'] ?? 'crash-v1'),
                    'server_seed_hash' => $math['server_seed_hash'],
                    'fair_hash' => $math['fair_hash'],
                    'source_reference' => $definition['source_reference'] ?? null,
                ],
            ]);
        });
    }

    private function settleOutstandingLosses(InternalGameCrashRound $crashRound): void
    {
        InternalGameRound::query()
            ->with(['session'])
            ->where('internal_game_crash_round_id', $crashRound->id)
            ->whereIn('status', ['bet_accepted', 'processing'])
            ->chunkById(100, function ($rounds) use ($crashRound) {
                foreach ($rounds as $round) {
                    /** @var InternalGameRound $round */
                    if ($round->status !== 'bet_accepted' && $round->status !== 'processing') {
                        continue;
                    }

                    $round->fill([
                        'status' => 'settled_loss',
                        'result_summary' => sprintf('Lane %d crashed at %.2fx before cashout', $this->betLaneForRound($round), (float) $crashRound->crash_multiplier),
                        'raw_result' => array_merge((array) $round->raw_result, [
                            'betLane' => $this->betLaneForRound($round),
                            'crashRoundId' => $crashRound->round_public_id,
                            'crashMultiplier' => (float) $crashRound->crash_multiplier,
                            'outcome' => 'loss',
                        ]),
                        'settled_at' => $crashRound->crashed_at ?: now(),
                    ])->save();

                    $session = $round->session;
                    if ($session instanceof InternalGameSession) {
                        $this->audit->logRound($session, $round, 'internal_game.crash.round.lost', [
                            'crash_round_id' => $crashRound->round_public_id,
                            'crash_multiplier' => (float) $crashRound->crash_multiplier,
                            'bet_amount' => (float) $round->bet_amount,
                            'bet_lane' => $this->betLaneForRound($round),
                            'status' => $round->status,
                        ]);
                    }
                }
            });
    }

    private function currentPlayerRounds(InternalGameSession $session, InternalGameCrashRound $crashRound): array
    {
        $rounds = InternalGameRound::query()
            ->with(['debitTransaction', 'winTransaction', 'refundTransaction'])
            ->where('internal_game_session_id', $session->id)
            ->where('internal_game_crash_round_id', $crashRound->id)
            ->latest('id')
            ->get();

        $byLane = ['1' => null, '2' => null];
        foreach ($rounds as $round) {
            $lane = (string) $this->betLaneForRound($round);
            if (!array_key_exists($lane, $byLane) || $byLane[$lane] instanceof InternalGameRound) {
                continue;
            }

            $byLane[$lane] = $round;
        }

        return $byLane;
    }

    private function playerRoundsPayload(InternalGameSession $session, InternalGameCrashRound $crashRound): array
    {
        return collect($this->currentPlayerRounds($session, $crashRound))
            ->map(fn (?InternalGameRound $round) => $this->playerRoundPayload($round))
            ->all();
    }

    private function primaryPlayerRoundPayload(array $rounds): ?array
    {
        foreach (['1', '2'] as $lane) {
            $round = $rounds[$lane] ?? null;
            if (is_array($round) && in_array((string) ($round['status'] ?? ''), ['bet_accepted', 'processing'], true)) {
                return $round;
            }
        }

        return $rounds['1'] ?? $rounds['2'] ?? null;
    }

    private function crashRoundPayload(InternalGameCrashRound $round, array $definition, CarbonInterface $moment): array
    {
        $currentMultiplier = $this->currentMultiplierForMoment($round, $definition, $moment);

        return [
            'id' => $round->round_public_id,
            'status' => $round->status,
            'bettingClosesAt' => optional($round->betting_closes_at)->toISOString(),
            'startsAt' => optional($round->starts_at)->toISOString(),
            'crashedAt' => $round->status === 'crashed' ? optional($round->crashed_at)->toISOString() : null,
            'crashMultiplier' => $round->status === 'crashed' ? (float) $round->crash_multiplier : null,
            'currentMultiplier' => $currentMultiplier,
            'serverSeedHash' => $round->server_seed_hash,
            'fairHash' => $round->status === 'crashed' ? $round->fair_hash : null,
            'revealedServerSeed' => $round->status === 'crashed' ? $round->server_seed : null,
            'verification' => [
                'algorithm' => 'sha256',
                'roundPublicId' => $round->round_public_id,
                'houseEdge' => (float) ($definition['house_edge'] ?? 0.965),
                'minCrashMultiplier' => (float) ($definition['min_crash_multiplier'] ?? 1.08),
                'maxCrashMultiplier' => (float) ($definition['max_crash_multiplier'] ?? 25.0),
            ],
        ];
    }

    private function historyPayload(InternalGameSession $session, array $definition): array
    {
        $limit = max(3, (int) ($definition['history_limit'] ?? 12));

        return InternalGameCrashRound::query()
            ->where('cms_game_id', $session->cms_game_id)
            ->where('game_code', $session->game_code)
            ->where('currency', $session->currency)
            ->where('is_demo', $session->is_demo)
            ->where('status', 'crashed')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (InternalGameCrashRound $round) => [
                'id' => $round->round_public_id,
                'crashMultiplier' => (float) $round->crash_multiplier,
                'crashedAt' => optional($round->crashed_at)->toISOString(),
            ])
            ->values()
            ->all();
    }

    private function playerRoundPayload(?InternalGameRound $round): ?array
    {
        if (!$round instanceof InternalGameRound) {
            return null;
        }

        $publicCrashMultiplier = $this->publicCrashMultiplierForRound($round);

        return [
            'roundId' => $round->round_id,
            'crashRoundId' => $round->crashRound?->round_public_id,
            'lane' => $this->betLaneForRound($round),
            'status' => $round->status,
            'betAmount' => (float) $round->bet_amount,
            'cashoutMultiplier' => (float) $round->cashout_multiplier,
            'cashoutAmount' => (float) ($round->cashout_amount ?: $round->win_amount),
            'winAmount' => (float) $round->win_amount,
            'refundAmount' => (float) $round->refund_amount,
            'resultSummary' => $round->result_summary,
            'requestRef' => $round->request_ref,
            'cashoutRequestRef' => $round->cashout_request_ref,
            'correlationRef' => $round->correlation_ref,
            'startedAt' => optional($round->started_at)->toISOString(),
            'settledAt' => optional($round->settled_at)->toISOString(),
            'cashedOutAt' => optional($round->cashed_out_at)->toISOString(),
            'crashMultiplier' => $publicCrashMultiplier,
            'transactions' => [
                'bet' => $round->debitTransaction?->transactionId,
                'win' => $round->winTransaction?->transactionId,
                'refund' => $round->refundTransaction?->transactionId,
            ],
        ];
    }

    private function publicFeedPayload(InternalGameCrashRound $round): array
    {
        return InternalGameRound::query()
            ->with(['user.parentAccount'])
            ->where('internal_game_crash_round_id', $round->id)
            ->whereIn('status', ['bet_accepted', 'settled_win'])
            ->orderByDesc('id')
            ->limit(18)
            ->get()
            ->map(function (InternalGameRound $playerRound) {
                $isCashout = $playerRound->status === 'settled_win';

                return [
                    'roundId' => $playerRound->round_id,
                    'lane' => $this->betLaneForRound($playerRound),
                    'type' => $isCashout ? 'cashout' : 'bet',
                    'player' => $this->publicPlayerLabel($playerRound),
                    'betAmount' => (float) $playerRound->bet_amount,
                    'cashoutAmount' => (float) ($playerRound->cashout_amount ?: $playerRound->win_amount),
                    'cashoutMultiplier' => (float) $playerRound->cashout_multiplier,
                    'createdAt' => optional($isCashout ? ($playerRound->cashed_out_at ?: $playerRound->settled_at) : $playerRound->started_at)->toISOString(),
                ];
            })
            ->values()
            ->all();
    }

    private function myBetHistoryPayload(InternalGameSession $session, array $definition): array
    {
        $limit = max(4, min(12, (int) ($definition['history_limit'] ?? 8)));

        return InternalGameRound::query()
            ->with(['debitTransaction', 'winTransaction', 'refundTransaction', 'crashRound'])
            ->where('internal_game_session_id', $session->id)
            ->where('game_code', $session->game_code)
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (InternalGameRound $round) => $this->playerRoundPayload($round))
            ->filter()
            ->values()
            ->all();
    }

    private function currentMultiplierForMoment(InternalGameCrashRound $round, array $definition, CarbonInterface $moment): float
    {
        if ($round->status === 'betting' || !$round->starts_at instanceof CarbonInterface) {
            return 1.00;
        }

        if ($round->status === 'crashed' || ($round->crashed_at instanceof CarbonInterface && $moment->gte($round->crashed_at))) {
            return (float) $round->crash_multiplier;
        }

        $elapsedSeconds = max(0, ($moment->getTimestampMs() - $round->starts_at->getTimestampMs()) / 1000);

        return max(1.01, $this->engine->currentMultiplier($definition, $elapsedSeconds));
    }

    private function roundResponse(InternalGameRound $round, string|float|null $balance): array
    {
        return [
            'roundId' => $round->round_id,
            'crashRoundId' => $round->crashRound?->round_public_id,
            'lane' => $this->betLaneForRound($round),
            'status' => $round->status,
            'betAmount' => (float) $round->bet_amount,
            'winAmount' => (float) $round->win_amount,
            'refundAmount' => (float) $round->refund_amount,
            'cashoutMultiplier' => (float) $round->cashout_multiplier,
            'cashoutAmount' => (float) ($round->cashout_amount ?: $round->win_amount),
            'currency' => $round->currency,
            'balance' => (float)$balance,
            'balanceExact' => (string)$balance,
            'result' => $this->publicRoundResultPayload($round),
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
            'status' => $round->status,
            'refundAmount' => (float) $round->refund_amount,
            'refundTransactionId' => $round->refundTransaction?->transactionId,
        ];
    }

    private function ledgerTransactionId(string $prefix, string $roundId): string
    {
        return sprintf('%s_%s', $prefix, $roundId);
    }

    private function assertRoundCanCashout(InternalGameRound $round, InternalGameSession $session, string $requestKey, ?InternalGameCrashRound $crashRound = null): void
    {
        if ($round->status !== 'bet_accepted') {
            $this->rejectCashoutAttempt(
                $session,
                $round,
                $requestKey,
                'round_not_cashout_eligible',
                'Bu bahis artik cash out icin uygun degil.',
                $crashRound
            );
        }

        if ($round->refund_transaction_id || (float) $round->refund_amount > 0) {
            $this->rejectCashoutAttempt(
                $session,
                $round,
                $requestKey,
                'round_already_refunded',
                'Bu bahis daha once iade edilmis.',
                $crashRound
            );
        }

        if ($round->win_transaction_id || (float) $round->win_amount > 0) {
            $this->rejectCashoutAttempt(
                $session,
                $round,
                $requestKey,
                'round_already_cashed_out',
                'Bu bahis icin cash out daha once tamamlanmis.',
                $crashRound
            );
        }

        if (!$session->is_demo) {
            if (!(bool) $round->debit_transaction_id) {
                $this->rejectCashoutAttempt(
                    $session,
                    $round,
                    $requestKey,
                    'debit_not_settled',
                    'Bahis debit kaydi tamamlanmadan cash out yapilamaz.',
                    $crashRound
                );
            }
        }
    }

    private function rejectCashoutAttempt(
        InternalGameSession $session,
        InternalGameRound $round,
        string $requestKey,
        string $reasonCode,
        string $message,
        ?InternalGameCrashRound $crashRound = null,
        array $extra = []
    ): never {
        abort(409, $message);
    }

    private function cashoutRejectReasonCode(string $message): string
    {
        $normalized = Str::lower(trim($message));

        return match (true) {
            Str::contains($normalized, 'window'),
            Str::contains($normalized, 'crash sonrasinda'),
            Str::contains($normalized, 'closed') => 'cashout_window_closed',
            Str::contains($normalized, 'uygun degil') => 'round_not_cashout_eligible',
            Str::contains($normalized, 'iade') => 'round_already_refunded',
            Str::contains($normalized, 'tamamlanmis') => 'round_already_cashed_out',
            Str::contains($normalized, 'debit') => 'debit_not_settled',
            default => 'cashout_rejected',
        };
    }

    private function normalizeBetLane(int $lane): int
    {
        abort_unless(in_array($lane, [1, 2], true), 422);

        return $lane;
    }

    private function publicPlayerLabel(InternalGameRound $round): string
    {
        $user = $round->user;
        if (!$user instanceof CmsUser) {
            return 'Player';
        }

        $base = trim((string) ($user->username ?: $user->full_name ?: 'Player'));
        if ($base === '') {
            $base = 'Player';
        }

        return Str::limit($base, 12, '');
    }

    private function betLaneForRound(InternalGameRound $round): int
    {
        $lane = (int) ($round->bet_lane ?: data_get($round->raw_result, 'betLane', 1));

        return in_array($lane, [1, 2], true) ? $lane : 1;
    }

    private function currentBalanceForSession(InternalGameSession $session): string|float|null
    {
        if ($session->is_demo) {
            return $this->demoBalanceForSession($session);
        }

        $walletContext = $this->resolveCanonicalWalletContextForSession($session);

        return $walletContext instanceof CanonicalWalletContext
            ? $this->wallets->currentDecimalBalance($walletContext)
            : null;
    }

    private function resolveCanonicalWalletContextForSession(InternalGameSession $session, bool $lockForUpdate = false): ?CanonicalWalletContext
    {
        $walletContext = $this->sessions->resolveWalletContextForSession($session, $lockForUpdate);

        if ($walletContext instanceof CanonicalWalletContext) {
            return $walletContext;
        }

        if ($walletContext instanceof SpendableWalletContext) {
            $canonicalContext = $walletContext->canonicalContext();

            return $canonicalContext instanceof CanonicalWalletContext
                ? $canonicalContext
                : null;
        }

        return null;
    }

    private function spendableWalletContextForLedger(InternalGameSession $session): SpendableWalletContext
    {
        $walletContext = $this->sessions->resolveWalletContextForSession($session);
        abort_unless($walletContext instanceof SpendableWalletContext, 404);

        return $walletContext;
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

    private function assertDebitProvenance(InternalGameRound $round, CanonicalWalletContext $wallet): void {
        $tx=$round->debitTransaction;
        abort_unless($tx && (int)$tx->action===TransactionActionEnum::Bet && (int)$tx->status===TransactionStatusEnum::Success
            && $tx->event_type==='internal_crash_bet' && $tx->round_id===$round->round_id
            && (int)$tx->cms_user_id===(int)$wallet->ownerWalletId() && $tx->currency===$round->currency
            && (int)$tx->source_wallet_account_id===(int)$wallet->walletAccountId()
            && BigDecimal::of((string)($tx->exact_amount ?? $tx->amount))->isEqualTo($round->bet_amount),409,'Crash debit provenance mismatch.');
    }
    private function betRequestFingerprint(float $betAmount, int $lane): string
    {
        return hash('sha256', json_encode([
            'betAmount' => number_format($betAmount, 2, '.', ''),
            'lane' => $lane,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function assertBetReplayMatches(InternalGameRound $round, string $requestFingerprint): void
    {
        $storedFingerprint = (string) data_get($round->raw_result, 'requestFingerprint', '');
        if ($storedFingerprint === '') {
            $storedFingerprint = $this->betRequestFingerprint((float) $round->bet_amount, $this->betLaneForRound($round));
        }

        abort_unless($storedFingerprint !== '' && hash_equals($storedFingerprint, $requestFingerprint), 409, 'This requestKey was already used with a different Sky Rush payload.');
    }

    private function publicRoundResultPayload(InternalGameRound $round): array
    {
        $result = (array) $round->raw_result;

        if (!$this->isCrashOutcomePublic($round)) {
            unset($result['crashMultiplier']);
        }

        return $result;
    }

    private function publicCrashMultiplierForRound(InternalGameRound $round): ?float
    {
        if (!$this->isCrashOutcomePublic($round)) {
            return null;
        }

        $crashMultiplier = $round->crashRound?->crash_multiplier;
        if ($crashMultiplier === null) {
            $crashMultiplier = data_get($round->raw_result, 'crashMultiplier');
        }

        return $crashMultiplier !== null ? (float) $crashMultiplier : null;
    }

    private function isCrashOutcomePublic(InternalGameRound $round): bool
    {
        return (string) ($round->crashRound?->status ?? '') === 'crashed'
            || (string) data_get($round->raw_result, 'outcome') === 'loss';
    }
}
