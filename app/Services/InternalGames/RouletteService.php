<?php

namespace App\Services\InternalGames;

use App\Enum\TransactionActionEnum;
use App\Enum\TransactionStatusEnum;
use App\Exceptions\InternalGames\RouletteException;
use App\Models\CmsUser;
use App\Models\CmsUserTransaction;
use App\Models\InternalGameRound;
use App\Models\InternalGameRoundBet;
use App\Models\InternalGameSession;
use App\Services\Wallet\SpendableWalletContext;
use App\Services\Wallet\SpendableWalletService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Brick\Math\BigDecimal;

class RouletteService
{
    private const WHEEL_ORDER = [0, 32, 15, 19, 4, 21, 2, 25, 17, 34, 6, 27, 13, 36, 11, 30, 8, 23, 10, 5, 24, 16, 33, 1, 20, 14, 31, 9, 22, 18, 29, 7, 28, 12, 35, 3, 26];

    public function __construct(
        private InternalGameCatalog $catalog,
        private InternalGameSessionService $sessions,
        private InternalGameAuditService $audit,
        private RouletteTable $table,
        private RouletteOutcomeResolver $outcomeResolver,
        private SpendableWalletService $wallets,
        private InternalGameLedgerAuditService $ledgerAudit
    ) {
    }

    public function supportsGameCode(string $gameCode): bool
    {
        $definition = $this->catalog->definitionByCode($gameCode);

        return is_array($definition)
            && (string) ($definition['engine'] ?? $definition['code'] ?? '') === 'roulette-european';
    }

    public function surfaceViewForSession(InternalGameSession $session): string
    {
        return 'game-provider-front.internal-roulette';
    }

    public function playPayload(InternalGameSession $session): array
    {
        $definition = $this->rouletteDefinitionForGameCode((string) $session->game_code);
        if (!is_array($definition)) {
            abort(404);
        }

        $limits = $this->tableLimitsForDefinition($definition);
        $preview = !$session->exists;

        return [
            'preview' => $preview,
            'sessionToken' => $session->session_token,
            'sessionId' => $session->public_id,
            'gameCode' => $session->game_code,
            'gameName' => $definition['name'] ?? 'European Roulette',
            'variantLabel' => $definition['variant_label'] ?? null,
            'currency' => $session->currency,
            'isDemo' => $session->is_demo,
            'balance' => $preview ? null : $this->currentBalanceForSession($session),
            'spinRoute' => route('cms.internalGames.roulette.spin'),
            'tableLimits' => $limits,
            'chipValues' => $this->chipValuesForDefinition($definition),
            'betModes' => [
                ['key' => 'straight', 'label' => 'Straight'],
                ['key' => 'split', 'label' => 'Split'],
                ['key' => 'street', 'label' => 'Street'],
                ['key' => 'corner', 'label' => 'Corner'],
                ['key' => 'six_line', 'label' => 'Six Line'],
            ],
            'wheelOrder' => self::WHEEL_ORDER,
            'recentResults' => $preview ? [] : $this->recentResultsPayload($session),
            'tableLayout' => [
                'numbers' => $this->numberLayout(),
                'columns' => $this->columnLayout(),
                'dozens' => $this->dozenLayout(),
                'outside' => $this->outsideLayout(),
                'insideHotspots' => [
                    'split' => $this->splitHotspots(),
                    'street' => $this->streetHotspots(),
                    'corner' => $this->cornerHotspots(),
                    'six_line' => $this->sixLineHotspots(),
                ],
            ],
        ];
    }

    public function spin(string $sessionToken, ?CmsUser $user, array $bets, string $requestKey): array
    {
        $requestKey = trim($requestKey);
        if ($requestKey === '') {
            throw new RouletteException(422, 'ROULETTE_INVALID_REQUEST', 'requestKey is required.');
        }

        $normalizedBets = $this->table->normalizeBets($bets);
        $requestFingerprint = $this->requestFingerprint($normalizedBets);
        $totalStake = round((float) collect($normalizedBets)->sum('stake_amount'), 2);
        if ($totalStake <= 0) {
            throw new RouletteException(422, 'ROULETTE_INVALID_REQUEST', 'Total roulette stake must be greater than zero.');
        }

        $session = $this->sessions->resolvePlayableSession($sessionToken, $user);
        $this->sessions->assertProductAccessAllowed($session, $user);
        if (!$this->supportsGameCode((string) $session->game_code)) {
            throw new RouletteException(404, 'ROULETTE_SESSION_NOT_FOUND', 'Roulette session not found.');
        }

        $definition = $this->rouletteDefinitionForGameCode((string) $session->game_code);
        if (!is_array($definition)) {
            throw new RouletteException(404, 'ROULETTE_SESSION_NOT_FOUND', 'Roulette definition not found.');
        }
        $limits = $this->tableLimitsForDefinition($definition);
        if ($totalStake < (float) $limits['minBet']) {
            throw new RouletteException(422, 'ROULETTE_STAKE_BELOW_MIN_LIMIT', 'Roulette stake is below the minimum limit for this variant.');
        }
        if ($totalStake > (float) $limits['maxBet']) {
            throw new RouletteException(422, 'ROULETTE_STAKE_ABOVE_MAX_LIMIT', 'Roulette stake is above the maximum limit for this variant.');
        }

        $lock = Cache::lock('internal-roulette-spin:' . $session->id, 5);
        $contended = !$lock->get();

        if ($contended) {
            try {
                $lock->block(5);
            } catch (LockTimeoutException) {
                throw new RouletteException(409, 'ROULETTE_SPIN_IN_PROGRESS', 'Another roulette spin is already being processed for this session.');
            }
        }

        try {
            return DB::transaction(function () use ($session, $normalizedBets, $requestFingerprint, $totalStake, $requestKey, $contended) {
                $session = InternalGameSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();

                $duplicate = InternalGameRound::query()
                    ->with(['debitTransaction', 'winTransaction', 'refundTransaction', 'betLines'])
                    ->where('internal_game_session_id', $session->id)
                    ->where('request_ref', $requestKey)
                    ->first();

                if ($duplicate instanceof InternalGameRound) {
                    $this->assertReplayMatches($duplicate, $requestFingerprint);

                    return $this->successResponse($duplicate, $this->currentBalanceForSession($session), $requestKey);
                }

                if ($contended) {
                    throw new RouletteException(409, 'ROULETTE_SPIN_IN_PROGRESS', 'Another roulette spin is already being processed for this session.');
                }

                $openRound = InternalGameRound::query()
                    ->where('internal_game_session_id', $session->id)
                    ->where('game_code', $session->game_code)
                    ->where('status', 'processing')
                    ->lockForUpdate()
                    ->first();

                if ($openRound instanceof InternalGameRound) {
                    throw new RouletteException(409, 'ROULETTE_ACTIVE_ROUND_EXISTS', 'A roulette round is already active for this session.');
                }

                $this->sessions->assertCasinoCountryAllowedForNewGameplay();

                $currentBalance = (float) $this->currentBalanceForSession($session);
                $walletContext = null;

                if ($session->is_demo) {
                    if ($currentBalance < $totalStake) {
                        throw new RouletteException(409, 'ROULETTE_INSUFFICIENT_FUNDS', 'Insufficient funds for this roulette spin.');
                    }
                } else {
                    $walletContext = $this->sessions->resolveWalletContextForSession($session, true);
                    $this->sessions->assertNewGameplayAllowed($session);
                    if (!$walletContext instanceof SpendableWalletContext) {
                        throw new RouletteException(404, 'ROULETTE_WALLET_NOT_FOUND', 'Roulette wallet context could not be resolved.');
                    }

                    $this->wallets->assertNoDrift($walletContext, 'internal_roulette_before_debit', [
                        'session_id' => $session->id,
                        'game_code' => $session->game_code,
                    ]);

                    $currentBalance = $this->wallets->currentDecimalBalance($walletContext);
                    if (BigDecimal::of($currentBalance)->isLessThan((string) $totalStake)) {
                        throw new RouletteException(409, 'ROULETTE_INSUFFICIENT_FUNDS', 'Insufficient funds for this roulette spin.');
                    }
                }

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
                        'bet_amount' => $totalStake,
                        'win_amount' => 0,
                        'refund_amount' => 0,
                        'request_ref' => $requestKey,
                        'correlation_ref' => (string) Str::uuid(),
                        'raw_result' => [
                            'requestKey' => $requestKey,
                            'requestFingerprint' => $requestFingerprint,
                            'requestedLines' => $this->lineResultsPayload($normalizedBets),
                        ],
                        'started_at' => now(),
                    ]);
                } catch (QueryException) {
                    $duplicateRound = InternalGameRound::query()
                        ->with(['debitTransaction', 'winTransaction', 'refundTransaction', 'betLines'])
                        ->where('internal_game_session_id', $session->id)
                        ->where('request_ref', $requestKey)
                        ->first();

                    if ($duplicateRound instanceof InternalGameRound) {
                        $this->assertReplayMatches($duplicateRound, $requestFingerprint);

                        return $this->successResponse($duplicateRound, $this->currentBalanceForSession($session), $requestKey);
                    }

                    throw new RouletteException(409, 'ROULETTE_ACTIVE_ROUND_EXISTS', 'A roulette round is already active for this session.');
                }

                foreach ($normalizedBets as $line) {
                    InternalGameRoundBet::query()->create([
                        'internal_game_round_id' => $round->id,
                        'line_no' => $line['line_no'],
                        'bet_type' => $line['bet_type'],
                        'bet_key' => $line['bet_key'],
                        'covered_numbers_json' => $line['covered_numbers'],
                        'stake_amount' => $line['stake_amount'],
                        'payout_multiplier' => $line['payout_multiplier'],
                        'payout_amount' => 0,
                        'status' => 'pending',
                        'meta_json' => $line['meta'],
                    ]);
                }

                $this->audit->logRound($session, $round, 'internal_game.roulette.spin.requested', [
                    'request_ref' => $requestKey,
                    'correlation_ref' => $round->correlation_ref,
                    'request_fingerprint' => $requestFingerprint,
                    'bet_amount' => $totalStake,
                    'line_count' => count($normalizedBets),
                    'line_snapshot' => $this->lineResultsPayload($normalizedBets),
                ]);

                $balanceAfterDebit = $currentBalance;
                $debitTransaction = null;

                if (!$session->is_demo) {
                    $debitTransaction = CmsUserTransaction::query()->create([
                        'cms_user_id' => $walletContext->ownerWalletId(),
                        'transactionId' => $this->ledgerTransactionId('bet', $round->round_id),
                        'round_id' => $round->round_id,
                        'provider_transaction_id' => $requestKey,
                        'game_provider_id' => $session->provider_id,
                        'game_id' => $session->cms_game_id,
                        'action' => TransactionActionEnum::Bet,
                        'event_type' => 'internal_roulette_bet',
                        'status' => TransactionStatusEnum::Success,
                        'amount' => $totalStake,
                        'currency' => $session->currency,
                        'source_wallet_account_id' => $walletContext->walletAccountId(),
                        'source_wallet_type' => $walletContext->walletType(),
                        'source_currency' => $walletContext->currency(),
                        'before_balance' => $currentBalance,
                        'after_balance' => $balanceAfterDebit = (string) BigDecimal::of($currentBalance)->minus((string) $totalStake),
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
                        (string) $totalStake,
                        $currentBalance,
                        $balanceAfterDebit,
                        self::class,
                        [
                            'request_ref' => $requestKey,
                            'line_count' => count($normalizedBets),
                        ]
                    );

                    $round->debit_transaction_id = $debitTransaction->id;

                    $this->audit->logRound($session, $round, 'internal_game.roulette.spin.debited', [
                        'request_ref' => $requestKey,
                        'transaction_id' => $debitTransaction->transactionId,
                        'before_balance' => $currentBalance,
                        'after_balance' => $balanceAfterDebit,
                    ] + $walletContext->auditPayload());
                }

                $winningNumber = $this->outcomeResolver->resolveWinningNumber();
                $this->assertRoundCanBeSettled($round);
                $settlement = $this->table->settleLines($normalizedBets, $winningNumber);
                $this->assertSettlementIntegrity($normalizedBets, $settlement);

                foreach ($settlement['line_results'] as $line) {
                    InternalGameRoundBet::query()
                        ->where('internal_game_round_id', $round->id)
                        ->where('line_no', $line['line_no'])
                        ->update([
                            'payout_amount' => $line['payout_amount'],
                            'status' => $line['status'],
                            'meta_json' => array_merge((array) ($line['meta'] ?? []), [
                                'winning_number' => $settlement['winning_number'],
                                'winning_color' => $settlement['winning_color'],
                            ]),
                            'updated_at' => now(),
                        ]);
                }

                $balanceAfterSettle = $balanceAfterDebit;
                $winTransaction = null;
                if (!$session->is_demo && (float) $settlement['total_return'] > 0) {
                    $winTransaction = CmsUserTransaction::query()->create([
                        'cms_user_id' => $walletContext->ownerWalletId(),
                        'transactionId' => $this->ledgerTransactionId('win', $round->round_id),
                        'ref_transaction_id' => $debitTransaction?->transactionId,
                        'round_id' => $round->round_id,
                        'provider_transaction_id' => $requestKey,
                        'game_provider_id' => $session->provider_id,
                        'game_id' => $session->cms_game_id,
                        'action' => TransactionActionEnum::Win,
                        'event_type' => 'internal_roulette_win',
                        'status' => TransactionStatusEnum::Success,
                        'amount' => $settlement['total_return'],
                        'currency' => $session->currency,
                        'source_wallet_account_id' => $walletContext->walletAccountId(),
                        'source_wallet_type' => $walletContext->walletType(),
                        'source_currency' => $walletContext->currency(),
                        'before_balance' => $balanceAfterDebit,
                        'after_balance' => $balanceAfterSettle = (string) BigDecimal::of($balanceAfterDebit)->plus((string) $settlement['total_return']),
                        'acceptor' => 'api',
                    ]);

                    $this->wallets->projectDecimalBalance($walletContext, $balanceAfterSettle);
                    $this->ledgerAudit->recordDecimal(
                        $walletContext,
                        $session,
                        $round,
                        $winTransaction,
                        'internal_game_win_paid',
                        'credit',
                        (string) $settlement['total_return'],
                        $balanceAfterDebit,
                        $balanceAfterSettle,
                        self::class,
                        [
                            'request_ref' => $requestKey,
                            'winning_number' => $settlement['winning_number'] ?? null,
                            'winning_color' => $settlement['winning_color'] ?? null,
                            'line_count' => count($normalizedBets),
                        ]
                    );

                    $round->win_transaction_id = $winTransaction->id;

                    $this->audit->logRound($session, $round, 'internal_game.roulette.spin.paid', [
                        'request_ref' => $requestKey,
                        'transaction_id' => $winTransaction->transactionId,
                        'win_amount' => $settlement['total_return'],
                    ] + $walletContext->auditPayload());
                } elseif ($session->is_demo) {
                    $balanceAfterSettle = round($currentBalance - $settlement['total_stake'] + $settlement['total_return'], 2);
                }

                $round->fill([
                    'status' => (float) $settlement['total_return'] > 0 ? 'settled_win' : 'settled_loss',
                    'win_amount' => $settlement['total_return'],
                    'raw_result' => array_merge((array) $round->raw_result, [
                        'winningNumber' => $settlement['winning_number'],
                        'winningColor' => $settlement['winning_color'],
                        'totalStake' => $settlement['total_stake'],
                        'totalReturn' => $settlement['total_return'],
                        'net' => $settlement['net'],
                        'lineResults' => $this->lineResultsPayload($settlement['line_results']),
                        'linkedTransactions' => $this->linkedTransactionsPayload($round, $debitTransaction, $winTransaction, null),
                    ]),
                    'result_summary' => $settlement['result_summary'],
                    'settled_at' => now(),
                ])->save();

                $session->forceFill(['last_activity_at' => now()])->save();

                $this->audit->logRound($session, $round, 'internal_game.roulette.spin.settled', [
                    'request_ref' => $requestKey,
                    'status' => $round->status,
                    'bet_amount' => $settlement['total_stake'],
                    'win_amount' => $settlement['total_return'],
                    'winning_number' => $settlement['winning_number'],
                    'winning_color' => $settlement['winning_color'],
                    'final_balance' => $balanceAfterSettle,
                ]);

                return $this->successResponse(
                    $round->fresh(['debitTransaction', 'winTransaction', 'refundTransaction', 'betLines']),
                    $balanceAfterSettle,
                    $requestKey
                );
            });
        } finally {
            optional($lock)->release();
        }
    }

    public function refund(string $roundId, string $requestKey, ?CmsUser $operator = null): array
    {
        $requestKey = trim($requestKey);
        if ($requestKey === '') {
            throw new RouletteException(422, 'ROULETTE_INVALID_REQUEST', 'requestKey is required.');
        }

        return DB::transaction(function () use ($roundId, $requestKey, $operator) {
            $round = InternalGameRound::query()
                ->with(['session', 'refundTransaction', 'debitTransaction', 'winTransaction'])
                ->where('round_id', $roundId)
                ->lockForUpdate()
                ->firstOrFail();

            if (!$this->supportsGameCode((string) $round->game_code)) {
                throw new RouletteException(404, 'ROULETTE_ROUND_NOT_FOUND', 'Roulette round not found.');
            }

            $session = $round->session;
            if (!$session instanceof InternalGameSession) {
                throw new RouletteException(404, 'ROULETTE_SESSION_NOT_FOUND', 'Roulette session not found.');
            }

            if ($round->refund_transaction_id) {
                return [
                    'result' => 'already_refunded',
                    'roundId' => $round->round_id,
                    'status' => $round->status,
                    'refundAmount' => (float) $round->refund_amount,
                    'refundTransactionId' => $round->refundTransaction?->transactionId,
                ];
            }

            if (!$round->is_demo && !$round->debit_transaction_id) {
                throw new RouletteException(409, 'ROULETTE_REFUND_REJECTED', 'A real-money roulette round cannot be refunded before the debit transaction exists.');
            }

            if ($round->win_transaction_id || (float) $round->win_amount > 0) {
                throw new RouletteException(409, 'ROULETTE_REFUND_REJECTED', 'A winning roulette round cannot be refunded.');
            }

            if (!in_array($round->status, ['processing', 'settled_loss'], true) || (float) $round->win_amount > 0) {
                throw new RouletteException(409, 'ROULETTE_REFUND_REJECTED', 'Only losing or open roulette rounds can be refunded.');
            }

            if ($round->is_demo) {
                $round->fill([
                    'status' => 'refunded',
                    'refund_amount' => $round->bet_amount,
                    'raw_result' => array_merge((array) $round->raw_result, [
                        'linkedTransactions' => $this->linkedTransactionsPayload(
                            $round,
                            $round->debitTransaction,
                            $round->winTransaction,
                            null
                        ),
                    ]),
                    'refunded_at' => now(),
                ])->save();

                $this->audit->logRound($session, $round, 'internal_game.round.refunded', [
                    'request_ref' => $requestKey,
                    'operator_id' => $operator?->id,
                    'refund_amount' => $round->bet_amount,
                ]);

                return [
                    'result' => 'refunded',
                    'roundId' => $round->round_id,
                    'status' => $round->status,
                    'refundAmount' => (float) $round->refund_amount,
                    'refundTransactionId' => null,
                ];
            }

            $walletContext = $this->sessions->resolveWalletContextForSession($session, true);
            if (!$walletContext instanceof SpendableWalletContext) {
                throw new RouletteException(404, 'ROULETTE_WALLET_NOT_FOUND', 'Roulette wallet context could not be resolved.');
            }

            // A refund must match the actually funded round and its pinned wallet.
            $debit = $round->debitTransaction;
            if (!$debit || (int) $debit->action !== TransactionActionEnum::Bet
                || (int) $debit->status !== TransactionStatusEnum::Success
                || $debit->event_type !== 'internal_roulette_bet'
                || $debit->round_id !== $round->round_id
                || (int) $debit->cms_user_id !== (int) $round->cms_user_id
                || $debit->currency !== $round->currency
                || !BigDecimal::of((string) $debit->amount)->isEqualTo((string) $round->bet_amount)
                || (int) $debit->source_wallet_account_id !== (int) $walletContext->walletAccountId()) {
                throw new RouletteException(409, 'ROULETTE_REFUND_REJECTED', 'The funded debit does not match this round.');
            }

            $this->wallets->assertNoDrift($walletContext, 'internal_roulette_before_refund', [
                'round_id' => $round->round_id,
            ]);

            $beforeBalance = $this->wallets->currentDecimalBalance($walletContext);
            $afterBalance = (string) BigDecimal::of($beforeBalance)->plus((string) $round->bet_amount);

            $refundTransaction = CmsUserTransaction::query()->create([
                'cms_user_id' => $walletContext->ownerWalletId(),
                'transactionId' => $this->ledgerTransactionId('refund', $round->round_id),
                'ref_transaction_id' => $round->debitTransaction?->transactionId,
                'round_id' => $round->round_id,
                'provider_transaction_id' => $requestKey,
                'game_provider_id' => $round->provider_id,
                'game_id' => $round->cms_game_id,
                'action' => TransactionActionEnum::Rollback,
                'event_type' => 'internal_roulette_refund',
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

            $this->wallets->projectDecimalBalance($walletContext, $afterBalance);
            $this->ledgerAudit->recordDecimal(
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
                'raw_result' => array_merge((array) $round->raw_result, [
                    'linkedTransactions' => $this->linkedTransactionsPayload(
                        $round,
                        $round->debitTransaction,
                        $round->winTransaction,
                        $refundTransaction
                    ),
                ]),
                'refunded_at' => now(),
            ])->save();

            $this->audit->logRound($session, $round, 'internal_game.round.refunded', [
                'request_ref' => $requestKey,
                'operator_id' => $operator?->id,
                'refund_transaction_id' => $refundTransaction->transactionId,
                'refund_amount' => $round->bet_amount,
            ] + $walletContext->auditPayload());

            return [
                'result' => 'refunded',
                'roundId' => $round->round_id,
                'status' => $round->status,
                'refundAmount' => (float) $round->refund_amount,
                'refundTransactionId' => $refundTransaction->transactionId,
            ];
        });
    }

    public function assertRoundCanBeSettled(InternalGameRound $round): void
    {
        if (!$this->supportsGameCode((string) $round->game_code)) {
            throw new RouletteException(404, 'ROULETTE_ROUND_NOT_FOUND', 'Roulette round not found.');
        }

        if ($round->settled_at !== null || in_array((string) $round->status, ['settled_win', 'settled_loss', 'refunded'], true)) {
            throw new RouletteException(409, 'ROULETTE_ROUND_ALREADY_SETTLED', 'This roulette round has already been settled.');
        }

        if ((float) $round->win_amount > 0 || (float) $round->refund_amount > 0 || $round->win_transaction_id || $round->refund_transaction_id) {
            throw new RouletteException(409, 'ROULETTE_ROUND_LEDGER_CONFLICT', 'Roulette round already has settlement ledger effects attached.');
        }
    }

    private function ledgerTransactionId(string $prefix, string $roundId): string
    {
        return sprintf('%s_%s', $prefix, $roundId);
    }

    private function rouletteDefinitionForGameCode(string $gameCode): ?array
    {
        $definition = $this->catalog->definitionByCode($gameCode);

        if (!is_array($definition)) {
            return null;
        }

        return (string) ($definition['engine'] ?? $definition['code'] ?? '') === 'roulette-european'
            ? $definition
            : null;
    }

    private function tableLimitsForDefinition(array $definition): array
    {
        return [
            'minBet' => round((float) ($definition['table_min_bet'] ?? 1), 2),
            'maxBet' => round((float) ($definition['table_max_bet'] ?? 1000), 2),
        ];
    }

    private function chipValuesForDefinition(array $definition): array
    {
        $chipValues = array_values(array_filter(array_map(
            static fn ($value) => is_numeric($value) ? round((float) $value, 2) : null,
            (array) ($definition['chip_values'] ?? [1, 5, 10, 25, 50, 100])
        ), static fn ($value) => $value !== null && $value > 0));

        return $chipValues !== [] ? $chipValues : [1, 5, 10, 25, 50, 100];
    }

    private function successResponse(InternalGameRound $round, ?float $balance, string $requestKey): array
    {
        $winningNumber = (int) data_get($round->raw_result, 'winningNumber', -1);

        return [
            'roundId' => $round->round_id,
            'winningNumber' => $winningNumber,
            'winningColor' => data_get($round->raw_result, 'winningColor', $winningNumber >= 0 ? $this->table->winningColor($winningNumber) : null),
            'totalStake' => round((float) $round->bet_amount, 2),
            'totalReturn' => round((float) $round->win_amount, 2),
            'net' => round(((float) $round->win_amount) - ((float) $round->bet_amount), 2),
            'balance' => $balance,
            'lineResults' => $this->lineResultsPayload(
                $round->relationLoaded('betLines') ? $round->betLines->all() : $round->betLines()->get()->all()
            ),
            'resultSummary' => (string) ($round->result_summary ?? ''),
            'requestKey' => $requestKey,
        ];
    }

    private function lineResultsPayload(array $lines): array
    {
        return array_map(function ($line) {
            $payload = $line instanceof InternalGameRoundBet ? $line->toArray() : $line;

            return [
                'lineNo' => (int) data_get($payload, 'line_no'),
                'betType' => (string) data_get($payload, 'bet_type'),
                'betKey' => (string) data_get($payload, 'bet_key'),
                'coveredNumbers' => array_values((array) data_get($payload, 'covered_numbers_json', data_get($payload, 'covered_numbers', []))),
                'stakeAmount' => round((float) data_get($payload, 'stake_amount'), 2),
                'payoutMultiplier' => (float) data_get($payload, 'payout_multiplier'),
                'payoutAmount' => round((float) data_get($payload, 'payout_amount'), 2),
                'status' => (string) data_get($payload, 'status', 'pending'),
            ];
        }, $lines);
    }

    private function currentBalanceForSession(InternalGameSession $session): float
    {
        if ($session->is_demo) {
            $baseBalance = (float) config('internal_games.demo_balance', 1000);
            $roundDelta = (float) InternalGameRound::query()
                ->where('internal_game_session_id', $session->id)
                ->selectRaw('COALESCE(SUM(win_amount + refund_amount - bet_amount), 0) as balance_delta')
                ->value('balance_delta');

            return round($baseBalance + $roundDelta, 2);
        }

        $walletContext = $this->sessions->resolveWalletContextForSession($session);

        return $walletContext instanceof SpendableWalletContext
            ? $this->wallets->currentBalance($walletContext)
            : 0.0;
    }

    private function recentResultsPayload(InternalGameSession $session): array
    {
        return InternalGameRound::query()
            ->where('cms_game_id', $session->cms_game_id)
            ->whereIn('status', ['settled_win', 'settled_loss'])
            ->whereNotNull('settled_at')
            ->latest('id')
            ->limit(12)
            ->get()
            ->map(function (InternalGameRound $round) {
                $number = (int) data_get($round->raw_result, 'winningNumber', -1);

                return [
                    'number' => $number,
                    'color' => data_get($round->raw_result, 'winningColor', $number >= 0 ? $this->table->winningColor($number) : null),
                    'roundId' => $round->round_id,
                ];
            })
            ->filter(fn (array $entry) => $entry['number'] >= 0)
            ->values()
            ->all();
    }

    private function numberLayout(): array
    {
        $layout = [];

        for ($column = 0; $column < 12; $column++) {
            $base = ($column * 3) + 1;
            foreach ([3, 2, 1] as $rowIndex => $offset) {
                $number = $base + ($offset - 1);
                $layout[] = [
                    'number' => $number,
                    'row' => $rowIndex,
                    'column' => $column,
                    'color' => $this->table->winningColor($number),
                ];
            }
        }

        return $layout;
    }

    private function columnLayout(): array
    {
        return [
            ['betType' => 'column', 'betKey' => '3', 'label' => '2 to 1', 'row' => 0],
            ['betType' => 'column', 'betKey' => '2', 'label' => '2 to 1', 'row' => 1],
            ['betType' => 'column', 'betKey' => '1', 'label' => '2 to 1', 'row' => 2],
        ];
    }

    private function dozenLayout(): array
    {
        return [
            ['betType' => 'dozen', 'betKey' => '1', 'label' => '1st 12'],
            ['betType' => 'dozen', 'betKey' => '2', 'label' => '2nd 12'],
            ['betType' => 'dozen', 'betKey' => '3', 'label' => '3rd 12'],
        ];
    }

    private function outsideLayout(): array
    {
        return [
            ['betType' => 'low_high', 'betKey' => 'low', 'label' => '1 to 18', 'tone' => 'neutral'],
            ['betType' => 'odd_even', 'betKey' => 'even', 'label' => 'Even', 'tone' => 'neutral'],
            ['betType' => 'red_black', 'betKey' => 'red', 'label' => 'Red', 'tone' => 'red'],
            ['betType' => 'red_black', 'betKey' => 'black', 'label' => 'Black', 'tone' => 'black'],
            ['betType' => 'odd_even', 'betKey' => 'odd', 'label' => 'Odd', 'tone' => 'neutral'],
            ['betType' => 'low_high', 'betKey' => 'high', 'label' => '19 to 36', 'tone' => 'neutral'],
        ];
    }

    private function splitHotspots(): array
    {
        $hotspots = [];

        for ($column = 0; $column < 12; $column++) {
            $base = ($column * 3) + 1;
            $hotspots[] = $this->insideHotspot(
                'split',
                [$base + 1, $base + 2],
                $column / 12,
                (1 / 3) - 0.06,
                1 / 12,
                0.12,
                'split-v'
            );
            $hotspots[] = $this->insideHotspot(
                'split',
                [$base, $base + 1],
                $column / 12,
                (2 / 3) - 0.06,
                1 / 12,
                0.12,
                'split-v'
            );
        }

        for ($column = 0; $column < 11; $column++) {
            $base = ($column * 3) + 1;
            foreach ([[0, 1], [1, 2], [2, 3]] as [$row, $numberOffset]) {
                $screenRow = 2 - $row;
                $hotspots[] = $this->insideHotspot(
                    'split',
                    [$base + $row, $base + $row + 3],
                    (($column + 1) / 12) - 0.03,
                    ($screenRow / 3) + 0.06,
                    0.06,
                    0.21,
                    'split-h'
                );
            }
        }

        return $hotspots;
    }

    private function streetHotspots(): array
    {
        $hotspots = [];

        for ($column = 0; $column < 12; $column++) {
            $base = ($column * 3) + 1;
            $hotspots[] = $this->insideHotspot(
                'street',
                [$base, $base + 1, $base + 2],
                ($column / 12) + 0.01,
                0.04,
                (1 / 12) - 0.02,
                0.92,
                'street'
            );
        }

        return $hotspots;
    }

    private function cornerHotspots(): array
    {
        $hotspots = [];

        for ($column = 0; $column < 11; $column++) {
            $base = ($column * 3) + 1;
            $hotspots[] = $this->insideHotspot(
                'corner',
                [$base + 1, $base + 2, $base + 4, $base + 5],
                (($column + 1) / 12) - 0.06,
                (1 / 3) - 0.06,
                0.12,
                0.12,
                'corner'
            );
            $hotspots[] = $this->insideHotspot(
                'corner',
                [$base, $base + 1, $base + 3, $base + 4],
                (($column + 1) / 12) - 0.06,
                (2 / 3) - 0.06,
                0.12,
                0.12,
                'corner'
            );
        }

        return $hotspots;
    }

    private function sixLineHotspots(): array
    {
        $hotspots = [];

        for ($column = 0; $column < 11; $column++) {
            $base = ($column * 3) + 1;
            $hotspots[] = $this->insideHotspot(
                'six_line',
                [$base, $base + 1, $base + 2, $base + 3, $base + 4, $base + 5],
                ($column / 12) + 0.01,
                0.08,
                (2 / 12) - 0.02,
                0.84,
                'six-line'
            );
        }

        return $hotspots;
    }

    private function insideHotspot(string $betType, array $numbers, float $left, float $top, float $width, float $height, string $shape): array
    {
        sort($numbers);

        return [
            'betType' => $betType,
            'betKey' => implode('-', $numbers),
            'numbers' => $numbers,
            'shape' => $shape,
            'style' => [
                'left' => round($left * 100, 4) . '%',
                'top' => round($top * 100, 4) . '%',
                'width' => round($width * 100, 4) . '%',
                'height' => round($height * 100, 4) . '%',
            ],
        ];
    }

    private function assertReplayMatches(InternalGameRound $round, string $requestFingerprint): void
    {
        $storedFingerprint = $this->storedRequestFingerprint($round);

        if ($storedFingerprint !== null && hash_equals($storedFingerprint, $requestFingerprint)) {
            return;
        }

        throw new RouletteException(409, 'ROULETTE_REPLAY_PAYLOAD_MISMATCH', 'This requestKey was already used with a different roulette payload.');
    }

    private function storedRequestFingerprint(InternalGameRound $round): ?string
    {
        $stored = data_get($round->raw_result, 'requestFingerprint');
        if (is_string($stored) && $stored !== '') {
            return $stored;
        }

        $betLines = $round->relationLoaded('betLines') ? $round->betLines->all() : $round->betLines()->get()->all();
        if ($betLines === []) {
            return null;
        }

        $normalized = array_map(function ($line) {
            $payload = $line instanceof InternalGameRoundBet ? $line->toArray() : (array) $line;

            return [
                'bet_type' => (string) data_get($payload, 'bet_type'),
                'bet_key' => (string) data_get($payload, 'bet_key'),
                'covered_numbers' => array_values((array) data_get($payload, 'covered_numbers_json', data_get($payload, 'covered_numbers', []))),
                'stake_amount' => round((float) data_get($payload, 'stake_amount'), 2),
                'payout_multiplier' => (float) data_get($payload, 'payout_multiplier'),
            ];
        }, $betLines);

        return $this->requestFingerprint($normalized);
    }

    private function requestFingerprint(array $normalizedBets): string
    {
        $fingerprintLines = array_map(function (array $line) {
            return [
                'bet_type' => (string) ($line['bet_type'] ?? ''),
                'bet_key' => (string) ($line['bet_key'] ?? ''),
                'covered_numbers' => array_values((array) ($line['covered_numbers'] ?? [])),
                'stake_amount' => number_format((float) ($line['stake_amount'] ?? 0), 2, '.', ''),
                'payout_multiplier' => number_format((float) ($line['payout_multiplier'] ?? 0), 2, '.', ''),
            ];
        }, $normalizedBets);

        usort($fingerprintLines, function (array $left, array $right) {
            return [$left['bet_type'], $left['bet_key'], $left['stake_amount']]
                <=> [$right['bet_type'], $right['bet_key'], $right['stake_amount']];
        });

        return hash('sha256', json_encode($fingerprintLines, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function assertSettlementIntegrity(array $normalizedBets, array $settlement): void
    {
        $lineResults = (array) ($settlement['line_results'] ?? []);
        $expectedStake = round((float) collect($normalizedBets)->sum('stake_amount'), 2);
        $expectedReturn = round((float) collect($lineResults)->sum('payout_amount'), 2);

        if (count($lineResults) !== count($normalizedBets)) {
            throw new RouletteException(500, 'ROULETTE_SETTLEMENT_INTEGRITY_ERROR', 'Roulette settlement line count drifted.');
        }

        if (round((float) ($settlement['total_stake'] ?? 0), 2) !== $expectedStake) {
            throw new RouletteException(500, 'ROULETTE_SETTLEMENT_INTEGRITY_ERROR', 'Roulette settlement stake total drifted.');
        }

        if (round((float) ($settlement['total_return'] ?? 0), 2) !== $expectedReturn) {
            throw new RouletteException(500, 'ROULETTE_SETTLEMENT_INTEGRITY_ERROR', 'Roulette settlement return total drifted.');
        }

        if (round((float) ($settlement['net'] ?? 0), 2) !== round($expectedReturn - $expectedStake, 2)) {
            throw new RouletteException(500, 'ROULETTE_SETTLEMENT_INTEGRITY_ERROR', 'Roulette settlement net total drifted.');
        }
    }

    private function linkedTransactionsPayload(
        InternalGameRound $round,
        ?CmsUserTransaction $debitTransaction,
        ?CmsUserTransaction $winTransaction,
        ?CmsUserTransaction $refundTransaction
    ): array {
        return [
            'bet' => $debitTransaction?->transactionId ?? $round->debitTransaction?->transactionId,
            'win' => $winTransaction?->transactionId ?? $round->winTransaction?->transactionId,
            'refund' => $refundTransaction?->transactionId ?? $round->refundTransaction?->transactionId,
        ];
    }
}
