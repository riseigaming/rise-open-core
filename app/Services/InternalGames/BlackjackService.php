<?php

namespace App\Services\InternalGames;

use App\Enum\TransactionActionEnum;
use App\Enum\TransactionStatusEnum;
use App\Exceptions\InternalGames\BlackjackException;
use App\Models\CmsUser;
use App\Models\CmsUserTransaction;
use App\Models\InternalGameRound;
use App\Models\InternalGameRoundBet;
use App\Models\InternalGameSession;
use App\Services\Wallet\CanonicalWalletContext;
use App\Services\Wallet\CanonicalWalletService;
use App\Services\Wallet\SpendableWalletContext;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Brick\Math\BigDecimal;

class BlackjackService
{
    public function __construct(
        private InternalGameCatalog $catalog,
        private InternalGameSessionService $sessions,
        private InternalGameAuditService $audit,
        private BlackjackEngine $engine,
        private CanonicalWalletService $wallets,
        private InternalGameLedgerAuditService $ledgerAudit
    ) {
    }

    public function supportsGameCode(string $gameCode): bool
    {
        $definition = $this->catalog->definitionByCode($gameCode);

        return is_array($definition)
            && (string) ($definition['engine'] ?? $definition['code'] ?? '') === 'blackjack-v1';
    }

    public function surfaceViewForSession(InternalGameSession $session): string
    {
        $definition = $this->blackjackDefinitionForGameCode((string) $session->game_code);

        return (string) ($definition['surface_view'] ?? 'game-provider-front.internal-blackjack');
    }

    public function playPayload(InternalGameSession $session): array
    {
        $definition = $this->blackjackDefinitionForGameCode((string) $session->game_code);
        if (!is_array($definition)) {
            abort(404);
        }

        $preview = !$session->exists;
        $activeRound = $preview ? null : $this->activeRoundForSession($session);

        return [
            'preview' => $preview,
            'sessionToken' => $session->session_token,
            'sessionId' => $session->public_id,
            'gameCode' => $session->game_code,
            'gameName' => $definition['name'] ?? 'Blackjack Classic',
            'currency' => $session->currency,
            'isDemo' => $session->is_demo,
            'balance' => $preview ? null : (float) $this->currentBalanceForSession($session),
            'balanceExact' => $preview ? null : (string) $this->currentBalanceForSession($session),
            'history' => $preview ? [] : $this->historyForSession($session),
            'startRoute' => route('cms.internalGames.blackjack.start'),
            'actionRoute' => route('cms.internalGames.blackjack.action'),
            'betValues' => array_values(array_map('floatval', (array) ($definition['bet_values'] ?? [10, 25, 50, 100]))),
            'rules' => [
                'blackjackPayoutMultiplier' => round((float) ($definition['blackjack_payout_multiplier'] ?? 2.5), 2),
                'dealerHitsSoft17' => (bool) ($definition['dealer_hits_soft_17'] ?? false),
                'doublePolicy' => (string) ($definition['double_policy'] ?? 'any_two_cards'),
            ],
            'currentRound' => $activeRound instanceof InternalGameRound
                ? $this->roundResponse(
                    $activeRound,
                    $this->stateForRound($activeRound),
                    $this->currentBalanceForSession($session),
                    (string) $activeRound->request_ref
                )
                : null,
        ];
    }

    public function startRound(string $sessionToken, ?CmsUser $user, float $betAmount, string $requestKey): array
    {
        $requestKey = trim($requestKey);
        if ($requestKey === '') {
            throw new BlackjackException(422, 'BLACKJACK_INVALID_REQUEST', 'requestKey is required.');
        }
        $requestFingerprint = $this->requestFingerprint([
            'betAmount' => round($betAmount, 2),
        ]);

        $session = $this->sessions->resolvePlayableSession($sessionToken, $user);
        $this->sessions->assertProductAccessAllowed($session, $user);
        if (!$this->supportsGameCode((string) $session->game_code)) {
            throw new BlackjackException(404, 'BLACKJACK_SESSION_NOT_FOUND', 'Blackjack session not found.');
        }

        $definition = $this->blackjackDefinitionForGameCode((string) $session->game_code);
        if (!is_array($definition)) {
            throw new BlackjackException(404, 'BLACKJACK_SESSION_NOT_FOUND', 'Blackjack definition not found.');
        }

        $allowedBets = array_map('floatval', (array) ($definition['bet_values'] ?? []));
        if (!in_array(round($betAmount, 2), array_map(fn ($value) => round($value, 2), $allowedBets), true)) {
            throw new BlackjackException(422, 'BLACKJACK_INVALID_BET', 'Bet amount is not allowed for this blackjack table.');
        }

        $lock = null;

        try {
            $lock = Cache::lock('internal-blackjack-session:' . $session->id, 5);
            $contended = !$lock->get();

            if ($contended) {
                try {
                    $lock->block(5);
                } catch (LockTimeoutException) {
                    throw new BlackjackException(409, 'BLACKJACK_ROUND_IN_PROGRESS', 'Another blackjack action is already being processed for this session.');
                }
            }

            return DB::transaction(function () use ($session, $definition, $betAmount, $requestKey, $requestFingerprint, $contended) {
                $session = InternalGameSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();

                $duplicate = InternalGameRound::query()
                    ->with(['debitTransaction', 'winTransaction', 'refundTransaction', 'betLines'])
                    ->where('internal_game_session_id', $session->id)
                    ->where('request_ref', $requestKey)
                    ->first();

                if ($duplicate instanceof InternalGameRound) {
                    $state = $this->stateForRound($duplicate);
                    $cached = $this->cachedResponse($state, $requestKey, $requestFingerprint);

                    if ($cached !== null) {
                        return $cached;
                    }

                    if (round((float) data_get($state, 'baseBet', $duplicate->bet_amount), 2) !== round($betAmount, 2)) {
                        throw new BlackjackException(409, 'BLACKJACK_REPLAY_PAYLOAD_MISMATCH', 'This requestKey was already used with a different blackjack payload.');
                    }

                    return $this->roundResponse(
                        $duplicate,
                        $state,
                        $this->currentBalanceForSession($session),
                        $requestKey
                    );
                }

                if ($contended) {
                    throw new BlackjackException(409, 'BLACKJACK_ROUND_IN_PROGRESS', 'Another blackjack action is already being processed for this session.');
                }

                $openRound = $this->activeRoundForSession($session, true);
                $this->sessions->assertCasinoCountryAllowedForNewGameplay();
                $this->sessions->assertNewGameplayAllowed($session);
                if ($openRound instanceof InternalGameRound) {
                    throw new BlackjackException(409, 'BLACKJACK_ACTIVE_ROUND_EXISTS', 'A blackjack round is already waiting for action.');
                }

                $currentBalance = (float) $this->currentBalanceForSession($session);
                $walletContext = null;

                if ($session->is_demo) {
                    if (BigDecimal::of((string) $currentBalance)->isLessThan((string) $betAmount)) {
                        throw new BlackjackException(409, 'BLACKJACK_INSUFFICIENT_FUNDS', 'Insufficient funds for this blackjack round.');
                    }
                } else {
                    $walletContext = $this->resolveCanonicalWalletContextForSession($session, true);
                    if (!$walletContext instanceof CanonicalWalletContext) {
                        throw new BlackjackException(404, 'BLACKJACK_WALLET_NOT_FOUND', 'Blackjack wallet context could not be resolved.');
                    }

                    $this->wallets->assertNoDrift($walletContext, 'internal_blackjack_before_bet', [
                        'session_id' => $session->id,
                        'game_code' => $session->game_code,
                    ]);

                    $currentBalance = $this->wallets->currentDecimalBalance($walletContext);
                    if (BigDecimal::of((string) $currentBalance)->isLessThan((string) $betAmount)) {
                        throw new BlackjackException(409, 'BLACKJACK_INSUFFICIENT_FUNDS', 'Insufficient funds for this blackjack round.');
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
                        'bet_amount' => round($betAmount, 2),
                        'win_amount' => 0,
                        'refund_amount' => 0,
                        'request_ref' => $requestKey,
                        'correlation_ref' => (string) Str::uuid(),
                        'raw_result' => [],
                        'result_summary' => '',
                        'started_at' => now(),
                    ]);
                } catch (QueryException $exception) {
                    if ((int) ($exception->errorInfo[1] ?? 0) !== 1062) { throw $exception; }
                    $duplicateRound = InternalGameRound::query()
                        ->with(['debitTransaction', 'winTransaction', 'refundTransaction', 'betLines'])
                        ->where('internal_game_session_id', $session->id)
                        ->where('request_ref', $requestKey)
                        ->first();

                    if ($duplicateRound instanceof InternalGameRound) {
                        $state = $this->stateForRound($duplicateRound);
                        $cached = $this->cachedResponse($state, $requestKey, $requestFingerprint);

                        if ($cached !== null) {
                            return $cached;
                        }

                        if (round((float) data_get($state, 'baseBet', $duplicateRound->bet_amount), 2) !== round($betAmount, 2)) {
                            throw new BlackjackException(409, 'BLACKJACK_REPLAY_PAYLOAD_MISMATCH', 'This requestKey was already used with a different blackjack payload.');
                        }

                        return $this->roundResponse(
                            $duplicateRound,
                            $state,
                            $this->currentBalanceForSession($session),
                            $requestKey
                        );
                    }

                    throw new BlackjackException(409, 'BLACKJACK_ACTIVE_ROUND_EXISTS', 'A blackjack round is already being processed.');
                }

                $this->audit->logRound($session, $round, 'internal_game.blackjack.round.requested', [
                    'request_ref' => $requestKey,
                    'correlation_ref' => $round->correlation_ref,
                    'bet_amount' => $betAmount,
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
                        'event_type' => 'internal_blackjack_bet',
                        'status' => TransactionStatusEnum::Success,
                        'amount' => $betAmount,
                        'currency' => $session->currency,
                        'source_wallet_account_id' => $walletContext->walletAccountId(),
                        'source_wallet_type' => 'primary_balance',
                        'source_currency' => $walletContext->currency(),
                        'before_balance' => $currentBalance,
                        'after_balance' => $balanceAfterDebit = (string) BigDecimal::of((string) $currentBalance)->minus((string) $betAmount),
                        'acceptor' => 'api',
                    ]);

                    $this->wallets->projectDecimalBalance($walletContext, (string) $balanceAfterDebit);
                    $this->ledgerAudit->recordDecimal(
                        $this->spendableWalletContextForLedger($session),
                        $session,
                        $round,
                        $debitTransaction,
                        'internal_game_bet_debited',
                        'debit',
                        (string) $betAmount,
                        $currentBalance,
                        $balanceAfterDebit,
                        self::class,
                        [
                            'request_ref' => $requestKey,
                        ]
                    );

                    $round->debit_transaction_id = $debitTransaction->id;

                    $this->audit->logRound($session, $round, 'internal_game.blackjack.round.debited', [
                        'request_ref' => $requestKey,
                        'transaction_id' => $debitTransaction->transactionId,
                        'before_balance' => $currentBalance,
                        'after_balance' => $balanceAfterDebit,
                    ] + $walletContext->auditPayload());
                }

                $state = $this->engine->createRoundState($definition, $betAmount);
                $state = $this->withLinkedTransactions($state, $debitTransaction?->transactionId, [], null, null);

                InternalGameRoundBet::query()->create([
                    'internal_game_round_id' => $round->id,
                    'line_no' => 1,
                    'bet_type' => 'blackjack_main',
                    'bet_key' => 'main',
                    'covered_numbers_json' => [],
                    'stake_amount' => $betAmount,
                    'payout_multiplier' => 1,
                    'payout_amount' => 0,
                    'status' => 'accepted',
                    'meta_json' => [
                        'phase' => 'initial_deal',
                    ],
                ]);

                $this->audit->logRound($session, $round, 'internal_game.blackjack.initial_deal', [
                    'request_ref' => $requestKey,
                    'round_version' => (int) ($state['version'] ?? 1),
                    'player_total' => (int) data_get($state, 'playerSummary.bestTotal', 0),
                    'dealer_total' => (int) data_get($state, 'dealerSummary.bestTotal', 0),
                ]);

                $balanceAfterSettlement = $balanceAfterDebit;
                $settlementTransaction = null;
                if (($state['phase'] ?? null) === 'resolved') {
                    [$round, $state, $settlementTransaction, $balanceAfterSettlement] = $this->settleResolvedRound(
                        $session,
                        $round,
                        $state,
                        $requestKey,
                        $walletContext,
                        $balanceAfterDebit,
                        $debitTransaction,
                    );
                } elseif ($session->is_demo) {
                    $balanceAfterSettlement = (string) BigDecimal::of((string) $currentBalance)->minus((string) $betAmount);
                }

                $round->fill([
                    'status' => $this->roundStatusFromState($state),
                    'bet_amount' => round((float) ($state['totalStake'] ?? $betAmount), 2),
                    'win_amount' => round((float) ($state['payoutTotal'] ?? 0), 2),
                    'raw_result' => $state,
                    'result_summary' => (string) ($state['resultSummary'] ?? ''),
                    'settled_at' => ($state['phase'] ?? null) === 'resolved' ? now() : null,
                ])->save();

                $session->forceFill(['last_activity_at' => now()])->save();

                $response = $this->roundResponse(
                    $round->fresh(['debitTransaction', 'winTransaction', 'refundTransaction', 'betLines']),
                    $state,
                    ($state['phase'] ?? null) === 'resolved' ? $balanceAfterSettlement : $balanceAfterDebit,
                    $requestKey
                );

                $round->forceFill([
                    'raw_result' => $this->storeRequestResponse(
                        (array) $round->raw_result,
                        $requestKey,
                        $requestFingerprint,
                        $response
                    ),
                ])->save();

                return $response;
            });
        } catch (BlackjackException $exception) {
            $this->audit->logSession($session, 'internal_game.blackjack.round.rejected', [
                'request_ref' => $requestKey,
                'bet_amount' => round($betAmount, 2),
                'code' => $exception->errorCode(),
                'message' => $exception->getMessage(),
            ], 'warning');

            throw $exception;
        } finally {
            optional($lock)->release();
        }
    }

    public function action(string $sessionToken, ?CmsUser $user, string $roundId, string $action, string $requestKey, int $roundVersion): array
    {
        $requestKey = trim($requestKey);
        if ($requestKey === '') {
            throw new BlackjackException(422, 'BLACKJACK_INVALID_REQUEST', 'requestKey is required.');
        }

        $session = $this->sessions->resolvePlayableSession($sessionToken, $user);
        if ($action === 'double') {
            $this->sessions->assertProductAccessAllowed($session, $user);
        }
        if (!$this->supportsGameCode((string) $session->game_code)) {
            throw new BlackjackException(404, 'BLACKJACK_SESSION_NOT_FOUND', 'Blackjack session not found.');
        }

        $lock = null;

        try {
            $lock = Cache::lock('internal-blackjack-session:' . $session->id, 5);
            $contended = !$lock->get();

            if ($contended) {
                try {
                    $lock->block(5);
                } catch (LockTimeoutException) {
                    throw new BlackjackException(409, 'BLACKJACK_ROUND_IN_PROGRESS', 'Another blackjack action is already being processed for this session.');
                }
            }

            return DB::transaction(function () use ($session, $roundId, $action, $requestKey, $roundVersion, $contended) {
                $round = InternalGameRound::query()
                    ->with(['session', 'debitTransaction', 'winTransaction', 'refundTransaction', 'betLines'])
                    ->where('internal_game_session_id', $session->id)
                    ->where('round_id', $roundId)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (!$this->supportsGameCode((string) $round->game_code)) {
                    throw new BlackjackException(404, 'BLACKJACK_ROUND_NOT_FOUND', 'Blackjack round not found.');
                }

                if ($contended) {
                    throw new BlackjackException(409, 'BLACKJACK_ROUND_IN_PROGRESS', 'Another blackjack action is already being processed for this session.');
                }

                $state = $this->stateForRound($round);
                $fingerprint = $this->requestFingerprint([
                    'action' => strtolower(trim($action)),
                    'roundVersion' => $roundVersion,
                ]);

                $cached = $this->cachedResponse($state, $requestKey, $fingerprint);
                if ($cached !== null) {
                    return $cached;
                }

                $currentVersion = (int) ($state['version'] ?? 1);
                if ($roundVersion !== $currentVersion) {
                    throw new BlackjackException(409, 'BLACKJACK_STALE_ACTION', 'Blackjack action was sent with a stale round version.');
                }

                if (($state['phase'] ?? null) !== 'player_turn') {
                    throw new BlackjackException(409, 'BLACKJACK_ROUND_ALREADY_RESOLVED', 'Blackjack round is already resolved.');
                }

                $normalizedAction = strtolower(trim($action));
                if (!in_array($normalizedAction, ['hit', 'stand', 'double'], true)) {
                    throw new BlackjackException(422, 'BLACKJACK_INVALID_ACTION', 'Unsupported blackjack action.');
                }

                $allowedActions = array_values((array) $this->engine->buildResponse($state)['allowedActions']);
                if (!in_array($normalizedAction, $allowedActions, true)) {
                    throw new BlackjackException(409, 'BLACKJACK_ACTION_REJECTED', 'Blackjack action is not valid in the current round state.');
                }

                $walletContext = null;
                $balanceBeforeAction = $this->currentBalanceForSession($session);
                $additionalDebitTransaction = null;

                if ($normalizedAction === 'double') {
                    // Extra stake is a new debit; stand/hit/settlement and cached replay remain available.
                    $this->sessions->assertCasinoCountryAllowedForNewGameplay();
                    $this->sessions->assertNewGameplayAllowed($session);
                    $additionalStake = round((float) ($state['baseBet'] ?? 0), 2);
                    if ($round->is_demo && $balanceBeforeAction < $additionalStake) { throw new BlackjackException(409, 'BLACKJACK_INSUFFICIENT_FUNDS', 'Insufficient demo credits to double.'); }

                    if (!$round->is_demo) {
                        $walletContext = $this->resolveCanonicalWalletContextForSession($session, true);
                        if (!$walletContext instanceof CanonicalWalletContext) {
                            throw new BlackjackException(404, 'BLACKJACK_WALLET_NOT_FOUND', 'Blackjack wallet context could not be resolved.');
                        }

                        $this->wallets->assertNoDrift($walletContext, 'internal_blackjack_before_double', [
                            'round_id' => $round->round_id,
                            'request_ref' => $requestKey,
                        ]);

                        $balanceBeforeAction = $this->wallets->currentDecimalBalance($walletContext);
                        if (BigDecimal::of((string) $balanceBeforeAction)->isLessThan((string) $additionalStake)) {
                            throw new BlackjackException(409, 'BLACKJACK_INSUFFICIENT_FUNDS', 'Insufficient funds to double this blackjack hand.');
                        }

                        $additionalDebitTransaction = CmsUserTransaction::query()->create([
                            'cms_user_id' => $walletContext->ownerWalletId(),
                            'transactionId' => $this->ledgerTransactionId('double', $round->round_id),
                            'ref_transaction_id' => $round->debitTransaction?->transactionId,
                            'round_id' => $round->round_id,
                            'provider_transaction_id' => $requestKey,
                            'game_provider_id' => $round->provider_id,
                            'game_id' => $round->cms_game_id,
                            'action' => TransactionActionEnum::Bet,
                            'event_type' => 'internal_blackjack_double_bet',
                            'status' => TransactionStatusEnum::Success,
                            'amount' => $additionalStake,
                            'currency' => $round->currency,
                            'source_wallet_account_id' => $walletContext->walletAccountId(),
                            'source_wallet_type' => 'primary_balance',
                            'source_currency' => $walletContext->currency(),
                            'before_balance' => $balanceBeforeAction,
                            'after_balance' => $balanceBeforeAction = (string) BigDecimal::of((string) $balanceBeforeAction)->minus((string) $additionalStake),
                            'acceptor' => 'api',
                        ]);

                        $this->wallets->projectDecimalBalance($walletContext, (string) $balanceBeforeAction);
                        $this->ledgerAudit->recordDecimal(
                            $this->spendableWalletContextForLedger($session),
                            $session,
                            $round,
                            $additionalDebitTransaction,
                            'internal_game_bet_debited',
                            'debit',
                            (string) $additionalStake,
                            (string) $additionalDebitTransaction->exact_before_balance,
                            $balanceBeforeAction,
                            self::class,
                            [
                                'request_ref' => $requestKey,
                                'blackjack_action' => 'double',
                            ]
                        );
                    } else {
                        $balanceBeforeAction = (string) BigDecimal::of((string) $balanceBeforeAction)->minus((string) $additionalStake);
                    }

                    InternalGameRoundBet::query()->updateOrCreate([
                        'internal_game_round_id' => $round->id,
                        'line_no' => 2,
                    ], [
                        'bet_type' => 'blackjack_double',
                        'bet_key' => 'double',
                        'covered_numbers_json' => [],
                        'stake_amount' => $additionalStake,
                        'payout_multiplier' => 1,
                        'payout_amount' => 0,
                        'status' => 'accepted',
                        'meta_json' => [
                            'request_ref' => $requestKey,
                        ],
                    ]);

                    $this->audit->logRound($session, $round, 'internal_game.blackjack.action.double_debited', [
                        'request_ref' => $requestKey,
                        'round_version' => $roundVersion,
                        'transaction_id' => $additionalDebitTransaction?->transactionId,
                        'additional_bet' => $additionalStake,
                        'after_balance' => $balanceBeforeAction,
                    ] + ($walletContext?->auditPayload() ?? []));
                }

                $state = $this->withLinkedTransactions(
                    $state,
                    data_get($state, 'linkedTransactions.bet'),
                    array_values(array_filter(array_merge(
                        (array) data_get($state, 'linkedTransactions.additionalDebits', []),
                        [$additionalDebitTransaction?->transactionId]
                    ))),
                    data_get($state, 'linkedTransactions.settlement'),
                    data_get($state, 'linkedTransactions.refund')
                );

                $state = $this->engine->applyAction($state, $normalizedAction);
                $state['version'] = $currentVersion + 1;

                $this->audit->logRound($session, $round, 'internal_game.blackjack.action.accepted', [
                    'request_ref' => $requestKey,
                    'action_type' => $normalizedAction,
                    'round_version' => $state['version'],
                    'player_total' => (int) data_get($state, 'playerSummary.bestTotal', 0),
                    'dealer_total' => (int) data_get($state, 'dealerSummary.bestTotal', 0),
                ]);

                $balanceAfterRound = $balanceBeforeAction;
                if (($state['phase'] ?? null) === 'resolved') {
                    [$round, $state, $settlementTransaction, $balanceAfterRound] = $this->settleResolvedRound(
                        $session,
                        $round,
                        $state,
                        $requestKey,
                        $walletContext,
                        $balanceBeforeAction,
                        $round->debitTransaction,
                    );

                    if ($settlementTransaction) {
                        $state = $this->withLinkedTransactions(
                            $state,
                            data_get($state, 'linkedTransactions.bet'),
                            (array) data_get($state, 'linkedTransactions.additionalDebits', []),
                            $settlementTransaction->transactionId,
                            data_get($state, 'linkedTransactions.refund')
                        );
                    }
                }

                $round->fill([
                    'status' => $this->roundStatusFromState($state),
                    'bet_amount' => round((float) ($state['totalStake'] ?? $round->bet_amount), 2),
                    'win_amount' => round((float) ($state['payoutTotal'] ?? $round->win_amount), 2),
                    'raw_result' => $state,
                    'result_summary' => (string) ($state['resultSummary'] ?? ''),
                    'settled_at' => ($state['phase'] ?? null) === 'resolved' ? ($round->settled_at ?: now()) : null,
                ])->save();

                $response = $this->roundResponse(
                    $round->fresh(['debitTransaction', 'winTransaction', 'refundTransaction', 'betLines']),
                    $state,
                    $balanceAfterRound,
                    $requestKey
                );

                $round->forceFill([
                    'raw_result' => $this->storeRequestResponse(
                        (array) $round->raw_result,
                        $requestKey,
                        $fingerprint,
                        $response
                    ),
                ])->save();

                $session->forceFill(['last_activity_at' => now()])->save();

                return $response;
            });
        } catch (BlackjackException $exception) {
            $this->logRejectedAction($session, $roundId, $action, $requestKey, $roundVersion, $exception);

            throw $exception;
        } finally {
            optional($lock)->release();
        }
    }

    public function refund(string $roundId, string $requestKey, ?CmsUser $operator = null): array
    {
        $requestKey = trim($requestKey);
        if ($requestKey === '') {
            throw new BlackjackException(422, 'BLACKJACK_INVALID_REQUEST', 'requestKey is required.');
        }

        try {
            return DB::transaction(function () use ($roundId, $requestKey, $operator) {
                $round = InternalGameRound::query()
                    ->with(['session', 'refundTransaction', 'debitTransaction', 'winTransaction'])
                    ->where('round_id', $roundId)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (!$this->supportsGameCode((string) $round->game_code)) {
                    throw new BlackjackException(404, 'BLACKJACK_ROUND_NOT_FOUND', 'Blackjack round not found.');
                }

                $session = $round->session;
                if (!$session instanceof InternalGameSession) {
                    throw new BlackjackException(404, 'BLACKJACK_SESSION_NOT_FOUND', 'Blackjack session not found.');
                }

                if ($round->refund_transaction_id || ($round->is_demo && $round->status === 'refunded')) {
                    return [
                        'result' => 'already_refunded',
                        'roundId' => $round->round_id,
                        'status' => $round->status,
                        'refundAmount' => (float) $round->refund_amount,
                        'refundTransactionId' => $round->refundTransaction?->transactionId,
                    ];
                }

                if ((float) $round->win_amount > 0 || !in_array($round->status, ['processing','awaiting_player_action','settled_loss','settled_win','settled_push'], true) || $round->win_transaction_id || in_array((string) $round->status, ['settled_win', 'settled_push'], true)) {
                    throw new BlackjackException(409, 'BLACKJACK_REFUND_REJECTED', 'Only losing or open blackjack rounds can be refunded.');
                }

                $refundAmount = round((float) $round->bet_amount, 2);

                if ($round->is_demo) {
                    $state = $this->withLinkedTransactions($this->stateForRound($round), data_get($round->raw_result, 'linkedTransactions.bet'), (array) data_get($round->raw_result, 'linkedTransactions.additionalDebits', []), data_get($round->raw_result, 'linkedTransactions.settlement'), null);
                    $state['phase'] = 'refunded';
                    $state['resultSummary'] = 'Round refunded by operator.';

                    $round->fill([
                        'status' => 'refunded',
                        'refund_amount' => $refundAmount,
                        'raw_result' => $state,
                        'refunded_at' => now(),
                    ])->save();

                    $this->audit->logRound($session, $round, 'internal_game.round.refunded', [
                        'request_ref' => $requestKey,
                        'operator_id' => $operator?->id,
                        'refund_amount' => $refundAmount,
                    ]);

                    return [
                        'result' => 'refunded',
                        'roundId' => $round->round_id,
                        'status' => $round->status,
                        'refundAmount' => (float) $round->refund_amount,
                        'refundTransactionId' => null,
                    ];
                }

                $walletContext = $this->resolveCanonicalWalletContextForSession($session, true);
                if (!$walletContext instanceof CanonicalWalletContext) {
                    throw new BlackjackException(404, 'BLACKJACK_WALLET_NOT_FOUND', 'Blackjack wallet context could not be resolved.');
                }

                $this->assertFundedRefund($round, $walletContext);

                $this->wallets->assertNoDrift($walletContext, 'internal_blackjack_before_refund', [
                    'round_id' => $round->round_id,
                ]);

                $beforeBalance = $this->wallets->currentDecimalBalance($walletContext);
                $afterBalance = (string) BigDecimal::of((string) $beforeBalance)->plus((string) $refundAmount);

                $refundTransaction = CmsUserTransaction::query()->create([
                    'cms_user_id' => $walletContext->ownerWalletId(),
                    'transactionId' => $this->ledgerTransactionId('refund', $round->round_id),
                    'ref_transaction_id' => $round->debitTransaction?->transactionId,
                    'round_id' => $round->round_id,
                    'provider_transaction_id' => $requestKey,
                    'game_provider_id' => $round->provider_id,
                    'game_id' => $round->cms_game_id,
                    'action' => TransactionActionEnum::Rollback,
                    'event_type' => 'internal_blackjack_refund',
                    'status' => TransactionStatusEnum::Success,
                    'amount' => $refundAmount,
                    'currency' => $round->currency,
                    'source_wallet_account_id' => $walletContext->walletAccountId(),
                    'source_wallet_type' => 'primary_balance',
                    'source_currency' => $walletContext->currency(),
                    'before_balance' => $beforeBalance,
                    'after_balance' => $afterBalance,
                    'acceptor' => 'operator',
                ]);

                $this->wallets->projectDecimalBalance($walletContext, (string) $afterBalance);
                $this->ledgerAudit->recordDecimal(
                    $this->spendableWalletContextForLedger($session),
                    $session,
                    $round,
                    $refundTransaction,
                    'internal_game_refund_paid',
                    'credit',
                    (string) $refundAmount,
                    $beforeBalance,
                    $afterBalance,
                    self::class,
                    [
                        'request_ref' => $requestKey,
                        'refund_amount' => $refundAmount,
                    ]
                );

                $state = $this->withLinkedTransactions(
                    $this->stateForRound($round),
                    data_get($round->raw_result, 'linkedTransactions.bet'),
                    (array) data_get($round->raw_result, 'linkedTransactions.additionalDebits', []),
                    data_get($round->raw_result, 'linkedTransactions.settlement'),
                    $refundTransaction->transactionId
                );
                $state['phase'] = 'refunded';
                $state['resultSummary'] = 'Round refunded by operator.';

                $round->fill([
                    'status' => 'refunded',
                    'refund_amount' => $refundAmount,
                    'refund_transaction_id' => $refundTransaction->id,
                    'raw_result' => $state,
                    'refunded_at' => now(),
                ])->save();

                $this->audit->logRound($session, $round, 'internal_game.round.refunded', [
                    'request_ref' => $requestKey,
                    'operator_id' => $operator?->id,
                    'refund_transaction_id' => $refundTransaction->transactionId,
                    'refund_amount' => $refundAmount,
                ] + $walletContext->auditPayload());

                return [
                    'result' => 'refunded',
                    'roundId' => $round->round_id,
                    'status' => $round->status,
                    'refundAmount' => (float) $round->refund_amount,
                    'refundTransactionId' => $refundTransaction->transactionId,
                ];
            });
        } catch (BlackjackException $exception) {
            $this->logRejectedRefund($roundId, $requestKey, $operator, $exception);

            throw $exception;
        }
    }

    private function settleResolvedRound(
        InternalGameSession $session,
        InternalGameRound $round,
        array $state,
        string $requestKey,
        ?CanonicalWalletContext $walletContext,
        float|string $balanceBeforeSettlement,
        ?CmsUserTransaction $referenceDebit
    ): array {
        $payoutTotal = round((float) ($state['payoutTotal'] ?? 0), 2);
        $balanceAfterSettlement = $balanceBeforeSettlement;
        $settlementTransaction = null;

        if (!$round->is_demo && $payoutTotal > 0) {
            if (!$walletContext instanceof CanonicalWalletContext) {
                $walletContext = $this->resolveCanonicalWalletContextForSession($session, true);
            }

            if (!$walletContext instanceof CanonicalWalletContext) {
                throw new BlackjackException(404, 'BLACKJACK_WALLET_NOT_FOUND', 'Blackjack wallet context could not be resolved.');
            }

            $eventType = ($state['outcome'] ?? null) === 'push'
                ? 'internal_blackjack_push'
                : 'internal_blackjack_win';

            $settlementTransaction = CmsUserTransaction::query()->create([
                'cms_user_id' => $walletContext->ownerWalletId(),
                'transactionId' => $this->ledgerTransactionId('settle', $round->round_id),
                'ref_transaction_id' => $referenceDebit?->transactionId,
                'round_id' => $round->round_id,
                'provider_transaction_id' => $requestKey,
                'game_provider_id' => $round->provider_id,
                'game_id' => $round->cms_game_id,
                'action' => TransactionActionEnum::Win,
                'event_type' => $eventType,
                'status' => TransactionStatusEnum::Success,
                'amount' => $payoutTotal,
                'currency' => $round->currency,
                'source_wallet_account_id' => $walletContext->walletAccountId(),
                'source_wallet_type' => 'primary_balance',
                'source_currency' => $walletContext->currency(),
                'before_balance' => $balanceBeforeSettlement,
                'after_balance' => $balanceAfterSettlement = (string) BigDecimal::of((string) $balanceBeforeSettlement)->plus((string) $payoutTotal),
                'acceptor' => 'api',
            ]);

            $this->wallets->projectDecimalBalance($walletContext, (string) $balanceAfterSettlement);
            $this->ledgerAudit->recordDecimal(
                $this->spendableWalletContextForLedger($session),
                $session,
                $round,
                $settlementTransaction,
                'internal_game_win_paid',
                'credit',
                (string) $payoutTotal,
                $balanceBeforeSettlement,
                $balanceAfterSettlement,
                self::class,
                [
                    'request_ref' => $requestKey,
                    'blackjack_outcome' => $state['outcome'] ?? null,
                    'payout_total' => $payoutTotal,
                ]
            );

            $round->win_transaction_id = $settlementTransaction->id;

            $this->audit->logRound($session, $round, 'internal_game.blackjack.round.settled', [
                'request_ref' => $requestKey,
                'transaction_id' => $settlementTransaction->transactionId,
                'status' => $this->roundStatusFromState($state),
                'win_amount' => $payoutTotal,
                'final_balance' => $balanceAfterSettlement,
                'player_total' => (int) data_get($state, 'playerSummary.bestTotal', 0),
                'dealer_total' => (int) data_get($state, 'dealerSummary.bestTotal', 0),
                'action_type' => (string) ($state['outcome'] ?? ''),
            ] + $walletContext->auditPayload());
        } elseif ($round->is_demo) {
            $oldEffect = (float) $round->win_amount + (float) $round->refund_amount - (float) $round->bet_amount;
            $newEffect = $payoutTotal - (float) $state['totalStake'];
            $balanceAfterSettlement = round((float) $this->currentBalanceForSession($session) - $oldEffect + $newEffect, 2);
        }

        $state = $this->withLinkedTransactions(
            $state,
            data_get($state, 'linkedTransactions.bet'),
            (array) data_get($state, 'linkedTransactions.additionalDebits', []),
            $settlementTransaction?->transactionId,
            data_get($state, 'linkedTransactions.refund')
        );

        return [$round, $state, $settlementTransaction, $balanceAfterSettlement];
    }

    private function roundResponse(InternalGameRound $round, array $state, float|string|null $balance, string $requestKey): array
    {
        $response = $this->engine->buildResponse($state);
        if (empty($state['dealerRevealHoleCard']) && ($state['phase'] ?? '') !== 'resolved') {
            $response['actionTimeline'] = array_map(function(array $entry) { unset($entry['dealer_cards']); return $entry; }, $response['actionTimeline']);
        }

        return [
            'roundId' => $round->round_id,
            'status' => $this->roundStatusFromState($state),
            'balance' => $balance === null ? null : (float) $balance,
            'balanceExact' => $balance === null ? null : (string) $balance,
            'currency' => $round->currency,
            'requestKey' => $requestKey,
        ] + $response;
    }

    private function blackjackDefinitionForGameCode(string $gameCode): ?array
    {
        $definition = $this->catalog->definitionByCode($gameCode);

        if (!is_array($definition)) {
            return null;
        }

        return (string) ($definition['engine'] ?? $definition['code'] ?? '') === 'blackjack-v1'
            ? $definition
            : null;
    }

    private function activeRoundForSession(InternalGameSession $session, bool $lockForUpdate = false): ?InternalGameRound
    {
        $query = InternalGameRound::query()
            ->where('internal_game_session_id', $session->id)
            ->where('game_code', $session->game_code)
            ->whereIn('status', ['processing', 'awaiting_player_action'])
            ->latest('id');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    private function currentBalanceForSession(InternalGameSession $session): float|string
    {
        if ($session->is_demo) {
            $baseBalance = (float) config('internal_games.demo_balance', 1000);
            $roundDelta = (float) InternalGameRound::query()
                ->where('internal_game_session_id', $session->id)
                ->selectRaw('COALESCE(SUM(win_amount + refund_amount - bet_amount), 0) as balance_delta')
                ->value('balance_delta');

            return round($baseBalance + $roundDelta, 2);
        }

        $walletContext = $this->resolveCanonicalWalletContextForSession($session);

        return $walletContext instanceof CanonicalWalletContext
            ? $this->wallets->currentDecimalBalance($walletContext)
            : 0.0;
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
        if (!$walletContext instanceof SpendableWalletContext) {
            throw new BlackjackException(404, 'BLACKJACK_WALLET_NOT_FOUND', 'Blackjack wallet context could not be resolved.');
        }

        return $walletContext;
    }

    private function historyForSession(InternalGameSession $session): array
    {
        return InternalGameRound::where('internal_game_session_id', $session->id)->whereIn('status',['settled_win','settled_loss','settled_push','refunded'])->latest('id')->limit(20)->get()->map(fn($r)=>[
            'roundId'=>$r->round_id,'status'=>$r->status,'totalStake'=>(float)$r->bet_amount,'payoutTotal'=>(float)$r->win_amount,'refundAmount'=>(float)$r->refund_amount,'outcome'=>data_get($r->raw_result,'outcome'),'currency'=>$r->currency,
        ])->all();
    }

    private function assertFundedRefund(InternalGameRound $round, CanonicalWalletContext $wallet): void
    {
        $debit=$round->debitTransaction;$keys=(array)data_get($round->raw_result,'linkedTransactions.additionalDebits',[]);
        $transactions=$debit ? collect([$debit])->merge(CmsUserTransaction::whereIn('transactionId',$keys)->get()) : collect();
        $valid=$debit && count($keys)===count(array_unique($keys)) && $transactions->count()===count($keys)+1;
        $sum=BigDecimal::of('0');
        foreach($transactions as $tx){
            $valid=$valid && (int)$tx->action===TransactionActionEnum::Bet && (int)$tx->status===TransactionStatusEnum::Success
                && $tx->round_id===$round->round_id && (int)$tx->cms_user_id===(int)$round->cms_user_id
                && $tx->currency===$round->currency && (int)$tx->source_wallet_account_id===(int)$wallet->walletAccountId()
                && ($tx->id===$debit->id ? $tx->event_type==='internal_blackjack_bet' : ($tx->event_type==='internal_blackjack_double_bet' && $tx->ref_transaction_id===$debit->transactionId));
            $sum=$sum->plus((string)$tx->exact_amount);
        }
        if(!$valid || !$sum->isEqualTo((string)$round->bet_amount))throw new BlackjackException(409,'BLACKJACK_REFUND_REJECTED','Funded debit chain must match this round, owner, amount and pinned wallet.');
    }

    private function ledgerTransactionId(string $prefix, string $roundId): string
    {
        return sprintf('%s_%s', $prefix, $roundId);
    }

    private function roundStatusFromState(array $state): string
    {
        return match ((string) ($state['phase'] ?? 'player_turn')) {
            'resolved' => match ((string) ($state['outcome'] ?? 'loss')) {
                'push' => 'settled_push',
                'win', 'blackjack' => 'settled_win',
                default => 'settled_loss',
            },
            'refunded' => 'refunded',
            default => 'awaiting_player_action',
        };
    }

    private function requestFingerprint(array $payload): string
    {
        ksort($payload);

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function cachedResponse(array $state, string $requestKey, string $fingerprint): ?array
    {
        $cached = data_get($state, "requestRegistry.{$requestKey}");

        if (!is_array($cached)) {
            return null;
        }

        $storedFingerprint = (string) ($cached['fingerprint'] ?? '');
        if ($storedFingerprint !== '' && hash_equals($storedFingerprint, $fingerprint)) {
            return (array) ($cached['response'] ?? []);
        }

        throw new BlackjackException(409, 'BLACKJACK_REPLAY_PAYLOAD_MISMATCH', 'This requestKey was already used with a different blackjack payload.');
    }

    private function storeRequestResponse(array $state, string $requestKey, string $fingerprint, array $response): array
    {
        $registry = (array) ($state['requestRegistry'] ?? []);
        $registry[$requestKey] = [
            'fingerprint' => $fingerprint,
            'response' => $response,
            'recorded_at' => now()->toIso8601String(),
        ];
        $state['requestRegistry'] = $registry;

        return $state;
    }

    private function stateForRound(InternalGameRound $round): array
    {
        return is_array($round->raw_result) ? $round->raw_result : [];
    }

    private function logRejectedAction(
        InternalGameSession $session,
        string $roundId,
        string $action,
        string $requestKey,
        int $roundVersion,
        BlackjackException $exception
    ): void {
        $round = InternalGameRound::query()
            ->where('internal_game_session_id', $session->id)
            ->where('round_id', $roundId)
            ->first();

        $payload = [
            'request_ref' => $requestKey,
            'round_version' => $roundVersion,
            'action_type' => strtolower(trim($action)),
            'code' => $exception->errorCode(),
            'message' => $exception->getMessage(),
        ];

        if ($round instanceof InternalGameRound) {
            $this->audit->logRound($session, $round, 'internal_game.blackjack.action.rejected', $payload, 'warning');

            return;
        }

        $this->audit->logSession($session, 'internal_game.blackjack.action.rejected', $payload, 'warning');
    }

    private function logRejectedRefund(string $roundId, string $requestKey, ?CmsUser $operator, BlackjackException $exception): void
    {
        $round = InternalGameRound::query()
            ->with('session')
            ->where('round_id', $roundId)
            ->first();

        $session = $round?->session;
        if (!$round instanceof InternalGameRound || !$session instanceof InternalGameSession) {
            return;
        }

        $this->audit->logRound($session, $round, 'internal_game.blackjack.refund.rejected', [
            'request_ref' => $requestKey,
            'operator_id' => $operator?->id,
            'code' => $exception->errorCode(),
            'message' => $exception->getMessage(),
        ], 'warning');
    }

    private function withLinkedTransactions(array $state, ?string $betTransactionId, array $additionalDebits, ?string $settlementTransactionId, ?string $refundTransactionId): array
    {
        $state['linkedTransactions'] = [
            'bet' => $betTransactionId,
            'additionalDebits' => array_values(array_filter($additionalDebits)),
            'settlement' => $settlementTransactionId,
            'refund' => $refundTransactionId,
        ];

        return $state;
    }
}
