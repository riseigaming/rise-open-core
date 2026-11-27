<?php

namespace App\Services\ExternalGames;

use App\Enum\TransactionActionEnum;
use App\Enum\TransactionStatusEnum;
use App\Models\CmsUser;
use App\Models\CmsUserTransaction;
use App\Models\ExternalGameSession;
use App\Models\WalletAccount;
use App\Models\WalletLedgerEntry;

use App\Services\Wallet\SpendableWalletContext;
use App\Services\Wallet\SpendableWalletService;
use Illuminate\Http\Request;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ExternalGameCallbackService
{
    private const COMMANDS = ['authenticate', 'balance', 'changebalance'];
    private const PROVIDER_METHODS = ['account_details', 'user_balance', 'transaction_bet', 'transaction_win', 'refund'];

    public function __construct(
        private readonly SpendableWalletService $wallets,
        private readonly ExternalGamesControlSettingsService $controls,
        private readonly ExternalGameCallbackHealthService $health,
        private readonly ExternalGameRiskLimitService $riskLimits,
        private readonly ExternalGameAudit $opsRadar
    ) {
    }

    public function handle(Request $request, ?string $routeCommand = null): array
    {
        $alias = $this->providerAlias($request);
        if (!ExternalGameProviderConfig::enabled($alias)) {
            return $this->providerFailure('Unknown provider.');
        }
        $providerMethod = $this->resolveProviderMethod($request);
        if ($providerMethod !== null) {
            return $this->providerWalletCallback($request, $providerMethod);
        }

        if ($alias !== ExternalGameProviderConfig::PRIMARY || $this->stringField($request, ['method']) !== '') {
            $this->health($request)->recordFailure('External Games provider callback method could not be resolved.');

            return $this->providerFailure('Invalid callback method.');
        }

        $command = $this->resolveCommand($request, $routeCommand);
        if ($command === null) {
            $this->health($request)->recordFailure('External Games callback command could not be resolved.');

            return $this->failure('INVALID_COMMAND', 'Command could not be resolved.', 400);
        }

        if (!$this->validSignature($request, $command)) {
            $this->health($request)->recordFailure('External Games callback signature validation failed.');

            return $this->failure('INVALID_SIGNATURE', 'Signature validation failed.', 403);
        }

        return match ($command) {
            'authenticate' => $this->authenticate($request, $command),
            'balance' => $this->balance($request, $command),
            'changebalance' => $this->changeBalance($request, $command),
        };
    }

    private function authenticate(Request $request, string $command): array
    {
        $session = $this->resolveSession($request);
        if (!$session instanceof ExternalGameSession) {
            $this->health($request)->recordFailure('External Games authenticate callback could not resolve a safe session.');

            return $this->failure('SESSION_NOT_FOUND', 'Session could not be resolved.');
        }

        $balance = $this->balanceForSession($session);
        if ($balance === null) {
            $this->health($request)->recordFailure('External Games authenticate callback could not resolve wallet balance.');
            $this->observe($session, $command, 'failed', $request);

            return $this->failure('WALLET_NOT_FOUND', 'Wallet could not be resolved.');
        }

        $this->observe($session, $command, 'success', $request);
        $this->health($request)->recordSuccess();

        return $this->success($balance, [
            'currency' => $session->wallet_currency,
            'user_id' => (string) $session->cms_user_id,
            'session_token' => $session->session_token,
        ]);
    }

    private function balance(Request $request, string $command): array
    {
        $session = $this->resolveSession($request);
        if (!$session instanceof ExternalGameSession) {
            $this->health($request)->recordFailure('External Games balance callback could not resolve a safe session.');

            return $this->failure('SESSION_NOT_FOUND', 'Session could not be resolved.');
        }

        $balance = $this->balanceForSession($session);
        if ($balance === null) {
            $this->health($request)->recordFailure('External Games balance callback could not resolve wallet balance.');
            $this->observe($session, $command, 'failed', $request);

            return $this->failure('WALLET_NOT_FOUND', 'Wallet could not be resolved.');
        }

        $this->observe($session, $command, 'success', $request);
        $this->health($request)->recordSuccess();

        return $this->success($balance, ['currency' => $session->wallet_currency]);
    }

    private function changeBalance(Request $request, string $command): array
    {
        $providerTransactionId = $this->stringField($request, ['transaction_id', 'transactionId', 'txn_id']);
        $amount = $this->amount($request);

        if ($providerTransactionId === '' || $amount === null || (string) BigDecimal::of($amount)->abs() <= 0) {
            $this->health($request)->recordFailure('External Games changebalance callback missing transaction_id or non-zero amount.');

            return $this->failure('INVALID_PAYLOAD', 'transaction_id and non-zero amount are required.');
        }

        $session = $this->resolveSession($request);
        if (!$session instanceof ExternalGameSession) {
            $this->health($request)->recordFailure('External Games changebalance callback could not resolve a safe session.');

            return $this->failure('SESSION_NOT_FOUND', 'Session could not be resolved.');
        }

        $idempotencyKey = $this->idempotencyKey($session->provider_alias, $command, $providerTransactionId);

        return $this->mutateWallet($request, $session, $command, $providerTransactionId, $amount, $idempotencyKey);
    }

    private function providerWalletCallback(Request $request, string $method): array
    {
        if (!$this->validProviderCredentials($request)) {
            $this->health($request)->recordFailure(
                'External Games provider payload credential validation failed.',
                $this->providerCallbackSummary(
                    $request,
                    $method,
                    'failed',
                    'invalid_provider_credentials',
                    $this->stringField($request, ['transaction_id', 'transactionId', 'txn_id']) ?: null
                )
            );

            return $this->providerFailure('Invalid provider credentials.');
        }

        if ($method === 'account_details') {
            return $this->providerAccountDetails($request, $method);
        }

        if ($method === 'user_balance') {
            return $this->providerUserBalance($request, $method);
        }

        $providerTransactionId = $this->stringField($request, ['transaction_id', 'transactionId', 'txn_id']);
        $amount = $this->providerAmount($request, $method);

        if ($providerTransactionId === '') {
            $session = $this->resolveProviderSession($request);
            if ($session instanceof ExternalGameSession) {
                $this->observe($session, $method, 'missing_transaction_id', $request);
            }

            $this->health($request)->recordFailure(
                'External Games provider callback missing transaction_id.',
                $this->providerCallbackSummary($request, $method, 'failed', 'missing_transaction_id')
            );

            return $this->providerFailure('Missing transaction id.');
        }

        if ($amount === null) {
            $session = $this->resolveProviderSession($request);
            if ($session instanceof ExternalGameSession) {
                $this->observe($session, $method, 'missing_amount', $request, $providerTransactionId);
            }

            $this->health($request)->recordFailure(
                'External Games provider callback missing method amount.',
                $this->providerCallbackSummary($request, $method, 'failed', 'missing_amount', $providerTransactionId)
            );

            return $this->providerFailure('Invalid callback payload.');
        }

        if ((string) BigDecimal::of($amount)->abs() <= 0) {
            $session = $this->resolveProviderSession($request);
            if ($method === 'transaction_win' && $session instanceof ExternalGameSession) {
                $balance = $this->balanceForSession($session);

                if ($balance !== null) {
                    $this->observe($session, $method, 'zero_amount_success', $request, $providerTransactionId);
                    return $this->providerSuccess($balance, $session, $request, $providerTransactionId);
                }
            }

            if ($session instanceof ExternalGameSession) {
                $this->observe($session, $method, 'zero_amount_ignored', $request, $providerTransactionId);
            }

            $this->health($request)->recordIgnored(
                'zero_amount_ignored',
                $this->providerCallbackSummary($request, $method, 'ignored', 'zero_amount_ignored', $providerTransactionId)
            );

            return $this->providerFailure('Zero amount ignored.', [], $session instanceof ExternalGameSession ? $session : null);
        }

        $session = $this->resolveProviderSession($request);
        if (!$session instanceof ExternalGameSession) {
            $this->health($request)->recordFailure(
                'External Games provider callback could not resolve a safe session.',
                $this->providerCallbackSummary($request, $method, 'failed', 'session_not_resolved', $providerTransactionId)
            );

            return $this->providerFailure('Session could not be resolved.');
        }

        $idempotencyKey = $this->idempotencyKey($session->provider_alias, $method, $providerTransactionId);

        return $this->mutateWallet($request, $session, $method, $providerTransactionId, $amount, $idempotencyKey, true);
    }

    private function providerAccountDetails(Request $request, string $method): array
    {
        $session = $this->resolveProviderSession($request);
        if (!$session && ($this->providerAlias($request) !== ExternalGameProviderConfig::PRIMARY || $this->hasExplicitSessionIdentity($request) || $request->attributes->get('external_game_financial_identity_ambiguous', false))) return $this->providerFailure('Session identity could not be resolved.');
        $userId = (int) $this->stringField($request, ['user_id', 'userId', 'player_id']);

        if ($userId <= 0) {
            $this->health($request)->recordFailure(
                'External Games provider account details callback missing user_id.',
                $this->providerCallbackSummary($request, $method, 'failed', 'missing_user_id')
            );

            return $this->providerFailure('User ID is required', [], $session);
        }

        $user = CmsUser::query()->whereKey($userId)->first();
        if (!$user instanceof CmsUser) {
            $this->health($request)->recordFailure(
                'External Games provider account details callback could not resolve user.',
                $this->providerCallbackSummary($request, $method, 'failed', 'user_not_found')
            );

            return $this->providerFailure('User not found', [], $session);
        }

        if ($session instanceof ExternalGameSession) {
            $this->observe($session, $method, 'success', $request);
        }

        $this->afterResponse(fn () => $this->health($request)->recordSuccess());

        return $this->providerAccountSuccess($session, [
            'user_id' => (int) $user->id,
            'email' => (string) $user->email,
            'name_jogador' => (string) ($user->username ?: $user->name ?: ('user-' . $user->id)),
        ]);
    }

    private function providerUserBalance(Request $request, string $method): array
    {
        $session = $this->resolveProviderSession($request);
        if (!$session && ($this->providerAlias($request) !== ExternalGameProviderConfig::PRIMARY || $this->hasExplicitSessionIdentity($request) || $request->attributes->get('external_game_financial_identity_ambiguous', false))) return $this->providerFailure('Session identity could not be resolved.');
        $userId = (int) $this->stringField($request, ['user_id', 'userId', 'player_id']);

        if ($userId <= 0) {
            $this->health($request)->recordFailure(
                'External Games provider balance callback missing user_id.',
                $this->providerCallbackSummary($request, $method, 'failed', 'missing_user_id')
            );

            return $this->providerFailure('User ID is required', [], $session);
        }

        $user = CmsUser::query()->whereKey($userId)->first();
        if (!$user instanceof CmsUser) {
            $this->health($request)->recordFailure(
                'External Games provider balance callback could not resolve user.',
                $this->providerCallbackSummary($request, $method, 'failed', 'user_not_found')
            );

            return $this->providerFailure('User not found', [], $session);
        }

        $balance = $session instanceof ExternalGameSession
            ? $this->balanceForSession($session)
            : (float) $user->balance;

        if ($balance === null) {
            if ($session instanceof ExternalGameSession) {
                $this->observe($session, $method, 'failed', $request);
            }

            $this->health($request)->recordFailure(
                'External Games provider balance callback could not resolve wallet balance.',
                $this->providerCallbackSummary($request, $method, 'failed', 'wallet_not_found')
            );

            return $this->providerFailure('Wallet could not be resolved', [], $session);
        }

        if ($session instanceof ExternalGameSession) {
            $this->observe($session, $method, 'success', $request);
        }

        $this->afterResponse(fn () => $this->health($request)->recordSuccess());

        return $this->providerSuccess($balance, $session, $request);
    }

    private function mutateWallet(
        Request $request,
        ExternalGameSession $session,
        string $operation,
        string $providerTransactionId,
        string $amount,
        string $idempotencyKey,
        bool $providerResponse = false
    ): array {
        $existing = CmsUserTransaction::query()->where('transactionId', $idempotencyKey)->first();
        if ($existing instanceof CmsUserTransaction) {
            if ($this->isExactReplay($existing, $session, $providerTransactionId, $amount, $operation, $request)) {
                $this->observe($session, $operation, 'idempotent_replay', $request, $providerTransactionId);
                $this->health($request)->recordReplay();

                return $providerResponse ? $this->providerSuccess((string) $existing->after_balance, $session, $request, $providerTransactionId) : $this->success((string) $existing->after_balance, [
                    'currency' => $session->wallet_currency,
                    'idempotent_replay' => true,
                    'transaction_id' => $providerTransactionId,
                ]);
            }

            $this->observe($session, $operation, 'conflict', $request, $providerTransactionId);
            $this->health($request)->recordConflict('External Games changebalance idempotency conflict.');

            return $providerResponse ? $this->providerFailure('Transaction idempotency conflict.', [], $session) : $this->failure('IDEMPOTENCY_CONFLICT', 'Transaction idempotency conflict.', 200, [
                'transaction_id' => $providerTransactionId,
            ]);
        }

        if ((bool) ($this->controls->settings()['emergency_stop'] ?? false) && $amount < 0) {
            $this->observe($session, $operation, 'blocked_emergency_stop', $request, $providerTransactionId);
            $this->health($request)->recordFailure('External Games emergency stop blocked new debit callback.');

            return $providerResponse ? $this->providerFailure('New debit callbacks are blocked.', [], $session) : $this->failure('EMERGENCY_STOP', 'New debit callbacks are blocked.');
        }

        $afterCommitTelemetry = [];

        try {
            $result = DB::transaction(function () use ($session, $request, $operation, $providerTransactionId, $amount, $idempotencyKey, $providerResponse, &$afterCommitTelemetry): array {
                // One lock order across currency projections and independent sessions.
                CmsUser::query()->whereKey($session->root_user_id)->lockForUpdate()->firstOrFail();
                // A parallel delivery may have committed while this request waited.
                $replay = CmsUserTransaction::query()->where('transactionId', $idempotencyKey)->lockForUpdate()->first();
                if ($replay) {
                    if (!$this->isExactReplay($replay, $session, $providerTransactionId, $amount, $operation, $request)) {
                        return $providerResponse ? $this->providerFailure('Transaction idempotency conflict.', [], $session)
                            : $this->failure('IDEMPOTENCY_CONFLICT', 'Transaction idempotency conflict.');
                    }
                    return $providerResponse ? $this->providerSuccess((string) $replay->after_balance, $session, $request, $providerTransactionId)
                        : $this->success((string) $replay->after_balance, ['currency' => $session->wallet_currency, 'idempotent_replay' => true]);
                }
                // A competing launch can add a different wallet identity while
                // this callback waits for the owner lock. Recheck before money
                // moves, under the same lock used by launch preparation.
                if ($this->stringField($request, ['session_token', 'callback_session_token', 'token']) === '') {
                    $resolved = $this->resolveProviderSession($request, false);
                    if (!$resolved || !ExternalGameFinancialIdentity::matches(
                        ExternalGameFinancialIdentity::fromSession($session),
                        ExternalGameFinancialIdentity::fromSession($resolved)
                    )) {
                        return $providerResponse ? $this->providerFailure('Session could not be resolved.', [], $session)
                            : $this->failure('SESSION_NOT_FOUND', 'Session could not be resolved.');
                    }
                }
                $lockedSession = ExternalGameSession::query()
                    ->whereKey($session->id)
                    ->lockForUpdate()
                    ->first();

                if (!$lockedSession instanceof ExternalGameSession || $lockedSession->provider_alias !== $this->providerAlias($request)) {
                    return $providerResponse ? $this->providerFailure('Session could not be resolved.', [], $session) : $this->failure('SESSION_NOT_FOUND', 'Session could not be resolved.');
                }

                $user = CmsUser::query()->whereKey($lockedSession->cms_user_id)->lockForUpdate()->first();
                if (!$user instanceof CmsUser) {
                    return $providerResponse ? $this->providerFailure('User could not be resolved.', [], $lockedSession) : $this->failure('USER_NOT_FOUND', 'User could not be resolved.');
                }

                $walletContext = $this->walletContextForSession($lockedSession, true);
                if (!$walletContext instanceof SpendableWalletContext) {
                    $this->observe($lockedSession, $operation, 'failed', $request, $providerTransactionId);

                    return $providerResponse ? $this->providerFailure('Wallet could not be resolved.', [], $lockedSession) : $this->failure('WALLET_NOT_FOUND', 'Wallet could not be resolved.');
                }

                // Only a NEW debit is admission-controlled. Replays returned above; linked credits/refunds remain payable.
                if ($amount < 0) {
                    try {
                        if ($lockedSession->provider_alias !== ExternalGameProviderConfig::PRIMARY) {
                            $game = $lockedSession->game;
                            if (!$game || $game->provider?->slug !== $lockedSession->provider_alias
                                || ($user->country_id && !app(\App\Services\Restrictions\CentralRestrictionPolicyService::class)
                                    ->decideCountryRestriction((int) $user->country_id, 'casino')->allowed)) {
                                return $this->providerFailure('Provider session is unavailable.', [], $lockedSession);
                            }
                            app(ExternalGameAvailabilityService::class)->assertLaunchable($game, $user, false);
                        }
                        $providerAssets = (array) config("external_games.providers.{$lockedSession->provider_alias}.supported_currencies", []);
                        if (!in_array(strtoupper($walletContext->currency()), $providerAssets, true)) {
                            return $providerResponse
                                ? $this->providerFailure('Unsupported provider wallet currency.', [], $lockedSession)
                                : $this->failure('UNSUPPORTED_ACTIVE_WALLET', 'Unsupported provider wallet currency.');
                        }
                        app(\App\Services\Wallet\WalletPlayabilityService::class)->assertForWallet(
                            'external_games', $walletContext->contextUser(), $walletContext->walletAccount());
                    } catch (ExternalGameLaunchBlockedException | \App\Exceptions\ProductAccess\ProductAccessDeniedException $exception) {
                        return $this->providerFailure($exception->getMessage(), [], $lockedSession);
                    } catch (\App\Services\Wallet\Exceptions\UnsupportedProductWalletException $exception) {
                        return $providerResponse
                            ? $this->providerFailure($exception->getMessage(), [], $lockedSession)
                            : $this->failure('UNSUPPORTED_ACTIVE_WALLET', $exception->getMessage());
                    }
                }

                if (in_array($operation, ['transaction_win', 'refund'], true)) {
                    $creditGuard = $this->validateProviderCreditLinkage($lockedSession, $request, $operation, $providerTransactionId, (string) BigDecimal::of($amount)->abs());
                    if ($creditGuard !== null) {
                        [$reason, $message] = $creditGuard;

                        $this->observe($lockedSession, $operation, $reason, $request, $providerTransactionId);
                        $this->safeTelemetry(fn () => $this->health($request)->recordFailure(
                            'External Games provider credit callback rejected: ' . $reason,
                            $this->providerCallbackSummary($request, $operation, 'failed', $reason, $providerTransactionId)
                        ));

                        return $providerResponse ? $this->providerFailure($message, ['error' => $reason], $lockedSession) : $this->failure(strtoupper($reason), $message);
                    }
                }

                $before = $this->wallets->currentDecimalBalance($walletContext);
                $absoluteAmount = $this->wallets->decimalForContext($walletContext, (string) BigDecimal::of($amount)->abs());
                $direction = $amount < 0 ? 'debit' : 'credit';

                if ($direction === 'debit' && BigDecimal::of($before)->isLessThan($absoluteAmount)) {
                    $this->observe($lockedSession, $operation, 'insufficient_funds', $request, $providerTransactionId);

                    return $providerResponse ? $this->providerFailure('Insufficient funds.', ['balance' => $before], $lockedSession) : $this->failure('INSUFFICIENT_FUNDS', 'Insufficient funds.', 200, [
                        'balance' => $before,
                    ]);
                }

                if ($direction === 'debit') {
                    $riskGuard = $this->riskLimits->debitGuard($lockedSession, $walletContext, (float) $absoluteAmount);
                    if ($riskGuard !== null) {
                        $reason = (string) $riskGuard['reason'];
                        $summary = (array) $riskGuard['summary'];

                        $this->observe($lockedSession, $operation, $reason, $request, $providerTransactionId);
                        $this->safeTelemetry(fn () => $this->health($request)->recordLimitRejected(
                            $reason,
                            $this->providerCallbackSummary($request, $operation, 'failed', $reason, $providerTransactionId) + ['limit' => $summary]
                        ));
                        $this->safeTelemetry(fn () => $this->opsRadar->externalGameRiskLimitBlocked($request, $lockedSession, [
                            'reason' => $reason,
                            'amount' => $absoluteAmount,
                            'limit' => $summary['limit'] ?? null,
                            'limit_currency' => 'TRY',
                            'wallet_direction' => 'debit',
                        ]));

                        return $providerResponse ? $this->providerFailure((string) $riskGuard['message'], ['error' => $reason, 'balance' => $before], $lockedSession) : $this->failure(strtoupper($reason), (string) $riskGuard['message'], 200, [
                            'balance' => $before,
                        ]);
                    }
                }

                $after = (string) ($direction === 'debit' ? BigDecimal::of($before)->minus($absoluteAmount) : BigDecimal::of($before)->plus($absoluteAmount));
                $eventType = $direction === 'debit'
                    ? $this->debitEventType($operation)
                    : $this->creditEventType($operation);

                $transaction = CmsUserTransaction::query()->create([
                    'cms_user_id' => $walletContext->ownerWalletId(),
                    'transactionId' => $idempotencyKey,
                    'ref_transaction_id' => $providerTransactionId,
                    // Historical column name: this is a callback grouping key and
                    // is provider round truth only for explicit round_id/roundId.
                    'round_id' => $this->callbackGroupingKey($request, $lockedSession),
                    'provider_transaction_id' => $providerTransactionId,
                    'game_provider_id' => $lockedSession->provider_id,
                    'game_id' => $lockedSession->cms_game_id,
                    'action' => $this->transactionAction($operation, $direction),
                    'event_type' => $eventType,
                    'status' => TransactionStatusEnum::Success,
                    'amount' => $absoluteAmount,
                    'currency' => $walletContext->currency(),
                    'source_wallet_account_id' => $walletContext->walletAccountId(),
                    'source_wallet_type' => $walletContext->walletType(),
                    'source_currency' => $walletContext->currency(),
                    'before_balance' => $before,
                    'after_balance' => $after,
                    'acceptor' => 'external_games',
                ]);

                $this->wallets->projectDecimalBalance($walletContext, $after);
                /* source projection audit accompanies the existing ledger below: [
                    'source' => $eventType,
                    'external_game_session_id' => $lockedSession->id,
                    'provider_transaction_id' => $providerTransactionId,
                ]; */

                $this->wallets->recordDecimalLedgerEntry(
                    $walletContext,
                    $eventType,
                    $direction,
                    $absoluteAmount,
                    $before,
                    $after,
                    $idempotencyKey,
                    $transaction,
                    [
                        'external_game_session_id' => $lockedSession->id,
                        'provider_alias' => $lockedSession->provider_alias,
                        'financial_identity' => ExternalGameFinancialIdentity::fromSession($lockedSession),
                        'provider_transaction_id' => $providerTransactionId,
                        'command' => $operation,
                        'canonical_risk' => $this->riskLimits->eventRiskSnapshot((float) $absoluteAmount, $walletContext->currency()),
                    ]
                );

                $afterCommitTelemetry[] = function () use ($lockedSession, $request, $operation, $providerTransactionId, $transaction, $direction): void {
                    $this->observe($lockedSession, $operation, 'success', $request, $providerTransactionId);
                    $this->opsRadar->externalGameCallback($request, $lockedSession->fresh(['game.provider']) ?: $lockedSession, $transaction, $operation, $direction);

                    $warning = $this->providerLifecycleWarning($lockedSession, $transaction);
                    if ($warning !== null) {
                        $this->opsRadar->externalGameLifecycleWarning($request, $lockedSession->fresh(['game.provider']) ?: $lockedSession, $warning);
                    }
                };

                return $providerResponse ? $this->providerSuccess($after, $lockedSession, $request, $providerTransactionId) : $this->success($after, [
                    'currency' => $lockedSession->wallet_currency,
                    'transaction_id' => $providerTransactionId,
                ]);
            });
        } catch (\Throwable $throwable) {
            $this->safeTelemetry(fn () => $this->health($request)->recordFailure($throwable->getMessage()));

            return $providerResponse ? $this->providerFailure('Wallet mutation failed.', [], $session) : $this->failure('MUTATION_FAILED', 'Wallet mutation failed.');
        }

        foreach ($afterCommitTelemetry as $telemetry) {
            $this->afterResponse($telemetry);
        }

        if ($this->isSuccessfulResult($result)) {
            $this->afterResponse(fn () => $this->health($request)->recordSuccess());
        } else {
            $this->afterResponse(fn () => $this->health($request)->recordFailure((string) ($result['error'] ?? 'External Games changebalance callback failed.')));
        }

        return $result;
    }

    private function resolveProviderMethod(Request $request): ?string
    {
        $method = strtolower(trim((string) $request->input('method', '')));

        return in_array($method, self::PROVIDER_METHODS, true) ? $method : null;
    }

    private function resolveCommand(Request $request, ?string $routeCommand): ?string
    {
        $routeCommand = strtolower(trim((string) $routeCommand));
        $requestCommand = strtolower(trim((string) $request->input('command', $request->input('cmd', ''))));

        if ($routeCommand !== '' && $requestCommand !== '' && $routeCommand !== $requestCommand) {
            return null;
        }

        $command = $routeCommand ?: $requestCommand;

        return in_array($command, self::COMMANDS, true) ? $command : null;
    }

    private function providerAlias(Request $request): string
    {
        return $request->attributes->get(ExternalGameProviderConfig::REQUEST_ALIAS, ExternalGameProviderConfig::PRIMARY);
    }

    private function health(Request $request): ExternalGameCallbackHealthService
    {
        return $this->health->forProvider($this->providerAlias($request));
    }

    private function validSignature(Request $request, string $command): bool
    {
        $secret = trim((string) config('external_games.providers.primary_external_provider.agent_secret', ''));
        $signature = trim((string) $request->header('X-Authorization', ''));

        return $secret !== ''
            && $signature !== ''
            && hash_equals(sha1($command . $secret), $signature);
    }

    private function validProviderCredentials(Request $request): bool
    {
        $alias = $this->providerAlias($request);
        $config = ExternalGameProviderConfig::providers()[$alias] ?? [];

        return ExternalGameProviderConfig::distinctWalletCredentials($alias)
            && $this->hashEquals((string) ($config['agent_code'] ?? ''), $this->stringField($request, ['agent_code']))
            && $this->hashEquals((string) ($config['agent_token'] ?? ''), $this->stringField($request, ['agent_token']))
            && $this->hashEquals((string) ($config['agent_secret'] ?? ''), $this->stringField($request, ['agent_secret_key']));
    }

    private function hashEquals(string $expected, string $actual): bool
    {
        $expected = trim($expected);
        $actual = trim($actual);

        return $expected !== '' && $actual !== '' && hash_equals($expected, $actual);
    }

    private function resolveSession(Request $request): ?ExternalGameSession
    {
        return $this->resolveProviderSession($request);
    }

    private function resolveProviderSession(Request $request, bool $auditResolution = true): ?ExternalGameSession
    {
        $request->attributes->set('external_game_financial_identity_ambiguous', false);
        if (!Schema::hasTable('external_game_sessions')) return null;
        $query = ExternalGameSession::query()
            ->where('provider_alias', $this->providerAlias($request))
            ->where('status', 'launched')
            ->where(fn ($mode) => $mode->whereNull('mode')->orWhereNotIn('mode', ['demo', 'fun']))
            ->where(fn ($expiry) => $expiry->whereNull('expires_at')->orWhere('expires_at', '>=', now()));
        $token = $this->stringField($request, ['session_token', 'callback_session_token', 'token']);
        if ($token !== '') return $this->identifiedSession($request, $token, $query);

        $userId = (int) $this->stringField($request, ['user_id', 'userId', 'player_id']);
        if ($userId <= 0) return null;
        // Include historical identities: expiry cannot make a different wallet
        // disappear from ambiguity analysis or discard legitimate late settlement.
        $candidates = ExternalGameSession::query()->with('game')
            ->where('provider_alias', $this->providerAlias($request))->where('cms_user_id', $userId)
            ->whereIn('status', ['launched', 'superseded'])
            ->where(fn ($mode) => $mode->whereNull('mode')->orWhereNotIn('mode', ['demo', 'fun']))
            ->orderByDesc('id')->get();
        $candidates = $candidates->filter(function ($candidate) use ($request): bool {
            if (!$this->callbackIdentityMatches($request, $candidate)) return false;
            foreach (['game', 'game_id', 'provider_game_id'] as $field) {
                if ($request->filled($field) && !in_array((string) $request->input($field),
                    [(string) $candidate->provider_game_id, (string) $candidate->game?->reference_id], true)) return false;
            }
            return true;
        });

        // A prior transaction/round can narrow wallets, but only from our ledger,
        // never from the current wallet preference or an arbitrary newest row.
        $operation = $this->resolveProviderMethod($request) ?? $this->resolveCommand($request, $request->route('command'));
        $transactionId = $this->stringField($request, ['transaction_id', 'transactionId', 'txn_id']);
        if ($operation && $transactionId !== '') {
            $existing = CmsUserTransaction::where('transactionId', $this->idempotencyKey($this->providerAlias($request), $operation, $transactionId))->first();
            if ($existing) {
                $candidates = $candidates->filter(fn ($candidate) => $this->transactionMatchesFinancialIdentity($existing, $candidate));
            } elseif (in_array($operation, ['transaction_win', 'refund'], true) && $this->explicitRoundId($request) !== '') {
                $roundMatches = $candidates->filter(function ($candidate) use ($request): bool {
                    return $this->successfulRoundTransactions($candidate, $this->explicitRoundId($request))
                        ->where('event_type', 'external_games_transaction_bet_debit')->get()
                        ->contains(fn ($bet) => $this->transactionMatchesFinancialIdentity($bet, $candidate));
                });
                if ($roundMatches->isNotEmpty()) $candidates = $roundMatches;
            }
        }
        $count = $candidates->count();
        $identity = $count ? ExternalGameFinancialIdentity::fromSession($candidates->first()) : [];
        // Account/balance reads need an exact wallet, not a game, unless the
        // provider supplied one. Economic callbacks always retain game identity.
        $requireGame = !in_array($operation, ['authenticate', 'balance', 'account_details', 'user_balance'], true)
            || $request->filled('game') || $request->filled('game_id') || $request->filled('provider_game_id');
        $equivalent = $count && ExternalGameFinancialIdentity::valid($identity)
            && $candidates->every(fn ($candidate) => ExternalGameFinancialIdentity::matches(
                ExternalGameFinancialIdentity::fromSession($candidate), $identity, $requireGame));
        if (!$equivalent) {
            // Preserve pre-launch account lookup, but never let its legacy user
            // balance fallback hide multiple plausible launched wallets.
            $request->attributes->set('external_game_financial_identity_ambiguous', $count > 0);
            $this->financialIdentityTelemetry($request, null, $count, 'tokenless_financial_identity_ambiguous');
            return null;
        }
        // Prefer launched, newest compatible; historic rows remain valid financial
        // evidence. No browser lease is required for financial correlation.
        $chosen = $candidates->firstWhere('status', 'launched') ?: $candidates->first();
        if ($count > 1 && $auditResolution) $this->financialIdentityTelemetry($request, $chosen, $count, 'tokenless_financial_identity_resolution', $requireGame);
        return $chosen;
    }

    private function financialIdentityTelemetry(Request $request, ?ExternalGameSession $session, int $count, string $reason, bool $requireGame = true): void
    {
        $this->safeTelemetry(fn () => $this->opsRadar->emit('external_game.callback.' . $reason, $request, [
            'domain' => 'external_game', 'severity' => $session ? 'info' : 'warning',
            'source_table' => 'external_game_sessions', 'source_id' => $session?->id,
            'subject_user_id' => $session?->root_user_id,
            'title' => 'Tokenless callback financial identity',
            'summary' => $session ? 'Equivalent financial identity resolved.' : 'No unique financial identity.',
            'metadata' => ['reason' => $reason, 'candidate_count' => $count, 'chosen_session_id' => $session?->id,
                'wallet_account_id' => $session?->wallet_account_id, 'wallet_currency' => $session?->wallet_currency,
                'provider_game_id' => $requireGame ? $session?->provider_game_id : null,
                'confidence' => $session ? ($requireGame ? 'exact_financial_identity' : 'exact_wallet_identity') : 'unresolved'],
            'dedupe_seconds' => 0,
        ]));
    }

    private function identifiedSession(Request $request, string $token, $activeQuery): ?ExternalGameSession
    {
        $credit = in_array($request->input('method'), ['transaction_win', 'refund'], true)
            || ($this->resolveCommand($request, $request->route('command')) === 'changebalance' && ($this->amount($request) ?? 0) > 0);
        $query = $credit ? ExternalGameSession::query()->where('provider_alias', $this->providerAlias($request))
            ->whereIn('status', ['launched', 'superseded'])
            ->where(fn ($mode) => $mode->whereNull('mode')->orWhereNotIn('mode', ['demo', 'fun'])) : clone $activeQuery;
        $session = $query->where('session_token', $token)->first();
        return $session && $this->callbackIdentityMatches($request, $session) ? $session : null;
    }

    private function callbackIdentityMatches(Request $request, ExternalGameSession $session): bool
    {
        foreach (['session_token', 'callback_session_token', 'token'] as $field) {
            if ($request->filled($field) && !hash_equals($session->session_token, (string) $request->input($field))) return false;
        }
        foreach (['currency', 'wallet_currency', 'source_currency'] as $field) {
            if ($request->filled($field) && strtoupper((string) $request->input($field)) !== strtoupper($session->wallet_currency)) return false;
        }
        foreach (['wallet_account_id' => $session->wallet_account_id, 'user_id' => $session->cms_user_id,
            'userId' => $session->cms_user_id, 'player_id' => $session->cms_user_id] as $field => $expected) {
            if ($request->filled($field) && (string) $request->input($field) !== (string) $expected) return false;
        }
        // Live-table runtime aliases can differ from catalogue IDs. The trusted
        // session token, account and wallet are identity; a game label is not.
        return true;
    }

    private function hasExplicitSessionIdentity(Request $request): bool
    {
        foreach (['session_token', 'callback_session_token', 'token', 'wallet_account_id', 'currency', 'wallet_currency', 'game', 'game_id', 'provider_game_id'] as $field) {
            if ($request->filled($field)) return true;
        }
        return false;
    }


    private function balanceForSession(ExternalGameSession $session): ?string
    {
        $walletContext = $this->walletContextForSession($session);

        return $walletContext instanceof SpendableWalletContext
            ? $this->wallets->currentDecimalBalance($walletContext)
            : null;
    }

    private function walletContextForSession(ExternalGameSession $session, bool $lockForUpdate = false): ?SpendableWalletContext
    {
        $user = CmsUser::query()->whereKey($session->cms_user_id)->first();
        if (!$user instanceof CmsUser) {
            return null;
        }

        // Never fall back to creating/resolving a replacement wallet for a
        // financial session whose pinned wallet is missing or contradictory.
        $wallet = WalletAccount::query()->whereKey($session->wallet_account_id)->first();
        if (!$wallet || (int) $wallet->cms_user_id !== (int) $session->root_user_id
            || (string) $wallet->wallet_type !== (string) $session->wallet_account_type
            || strtoupper((string) $wallet->asset) !== strtoupper((string) $session->wallet_currency)) {
            return null;
        }

        $context = $this->wallets->resolveForStoredContext(
            $user,
            $session->wallet_account_id,
            $session->wallet_account_type,
            $session->wallet_currency,
            $session->cms_user_id,
            $lockForUpdate
        );
        if (!$context || $context->walletAccountId() !== (int) $session->wallet_account_id
            || $context->rootWalletId() !== (int) $session->root_user_id
            || strtoupper($context->currency()) !== strtoupper($session->wallet_currency)
            || ($context->isPrimaryBalance() && strtoupper((string) $context->ownerUser()->currency) !== strtoupper($session->wallet_currency))) {
            return null;
        }
        return $context;
    }

    private function isExactReplay(
        CmsUserTransaction $transaction,
        ExternalGameSession $session,
        string $providerTransactionId,
        string $amount,
        string $operation,
        Request $request
    ): bool {
        $expectedEvent = $amount < 0 ? $this->debitEventType($operation) : $this->creditEventType($operation);

        return (string) $transaction->provider_transaction_id === $providerTransactionId
            && $this->transactionMatchesFinancialIdentity($transaction, $session)
            && $this->replayGroupingMatches($transaction, $request, $session)
            && (int) $transaction->source_wallet_account_id === (int) $session->wallet_account_id
            && (string) $transaction->event_type === $expectedEvent
            && BigDecimal::of((string) $transaction->amount)->isEqualTo(BigDecimal::of($amount)->abs());
    }

    /**
     * @return array{0:string,1:string}|null
     */
    private function validateProviderCreditLinkage(
        ExternalGameSession $session,
        Request $request,
        string $operation,
        string $providerTransactionId,
        string $absoluteAmount
    ): ?array {
        $roundId = $this->explicitRoundId($request);
        if ($roundId === '') {
            return [$operation === 'refund' ? 'orphan_refund' : 'orphan_win', 'Round could not be resolved.'];
        }

        if (!$this->callbackGameMatchesSession($request, $session)) {
            return [$operation === 'refund' ? 'orphan_refund' : 'orphan_win', 'Game could not be resolved.'];
        }

        $betQuery = $this->successfulRoundTransactions($session, $roundId)
            ->where('event_type', 'external_games_transaction_bet_debit');

        $betTotal = (clone $betQuery)->get()->filter(fn ($transaction) =>
            $this->transactionMatchesFinancialIdentity($transaction, $session))->reduce(fn ($sum,$row)=>$sum->plus((string)$row->amount),BigDecimal::zero());
        if (!$betTotal->isPositive()) {
            return [$operation === 'refund' ? 'orphan_refund' : 'orphan_win', 'Prior round bet could not be resolved.'];
        }

        if ($operation === 'refund') {
            $priorRefundTotal = $this->successfulRoundTransactions($session, $roundId)
                ->where('event_type', 'external_games_refund_credit')
                ->get()->filter(fn ($transaction) => $this->transactionMatchesFinancialIdentity($transaction, $session))->reduce(fn ($sum,$row)=>$sum->plus((string)$row->amount),BigDecimal::zero());

            if ($priorRefundTotal->plus($absoluteAmount)->isGreaterThan($betTotal)) {
                return ['refund_exceeds_debit', 'Refund exceeds prior round debit.'];
            }
        }

        return null;
    }

    private function successfulRoundTransactions(ExternalGameSession $session, string $roundId)
    {
        return CmsUserTransaction::query()
            ->where('transactionId', 'like', addcslashes(ExternalGameProviderConfig::transactionPrefix($session->provider_alias), '\\%_') . '%')
            ->where('status', TransactionStatusEnum::Success)
            ->where('source_wallet_account_id', $session->wallet_account_id)
            ->where('round_id', $roundId);
    }

    private function ledgerSessionId(CmsUserTransaction $transaction): ?int
    {
        $id = data_get(WalletLedgerEntry::where('idempotency_key', $transaction->transactionId)
            ->where('wallet_account_id', $transaction->source_wallet_account_id)->first()?->meta, 'external_game_session_id');
        return $id ? (int) $id : null;
    }

    private function transactionMatchesFinancialIdentity(CmsUserTransaction $transaction, ExternalGameSession $session): bool
    {
        $entry = WalletLedgerEntry::where('idempotency_key', $transaction->transactionId)
            ->where('wallet_account_id', $transaction->source_wallet_account_id)->first();
        $stored = data_get($entry?->meta, 'financial_identity');
        if (!is_array($stored)) {
            // Read-only compatibility for earlier ledger entries; never infer
            // ownership from whichever wallet is currently active.
            $original = ExternalGameSession::find((int) data_get($entry?->meta, 'external_game_session_id'));
            $stored = $original ? ExternalGameFinancialIdentity::fromSession($original) : [];
        }
        return ExternalGameFinancialIdentity::matches($stored, ExternalGameFinancialIdentity::fromSession($session))
            && (int) $transaction->source_wallet_account_id === (int) $session->wallet_account_id
            && strtoupper((string) ($transaction->source_currency ?: $transaction->currency)) === strtoupper($session->wallet_currency);
    }

    private function replayGroupingMatches(CmsUserTransaction $transaction, Request $request, ExternalGameSession $session): bool
    {
        $group = ExternalGameCallbackGroupingIdentity::resolve($request, $session);
        if ($group->classification !== ExternalGameCallbackGroupingIdentity::CALLBACK_GROUPING_KEY) {
            return (string) $transaction->round_id === $group->value;
        }
        // When no provider round/reference exists, the old launch token was only
        // a synthetic grouping key. Reusing the transaction ID on an equivalent
        // launch must not invent a different economic event.
        $original = ExternalGameSession::find($this->ledgerSessionId($transaction));
        return $original && (string) $transaction->round_id === (string) $original->session_token;
    }

    private function providerLifecycleWarning(ExternalGameSession $session, CmsUserTransaction $transaction): ?array
    {
        if (!in_array((string) $transaction->event_type, [
            'external_games_transaction_bet_debit',
            'external_games_refund_credit',
        ], true) || !Schema::hasTable('cms_user_transactions')) {
            return null;
        }

        $summary = CmsUserTransaction::query()
            ->where('transactionId', 'like', addcslashes(ExternalGameProviderConfig::transactionPrefix($session->provider_alias), '\\%_') . '%')
            ->where('status', TransactionStatusEnum::Success)
            ->where('cms_user_id', $session->cms_user_id)
            ->where('game_provider_id', $session->provider_id)
            ->where('game_id', $session->cms_game_id)
            ->where('created_at', '>=', now()->subMinutes(15))
            ->selectRaw("SUM(CASE WHEN event_type = 'external_games_transaction_bet_debit' THEN 1 ELSE 0 END) as bet_count")
            ->selectRaw("SUM(CASE WHEN event_type = 'external_games_refund_credit' THEN 1 ELSE 0 END) as refund_count")
            ->selectRaw("COUNT(DISTINCT round_id) as round_count")
            ->selectRaw("SUM(CASE WHEN event_type = 'external_games_transaction_bet_debit' THEN amount ELSE 0 END) as bet_total")
            ->selectRaw("SUM(CASE WHEN event_type = 'external_games_refund_credit' THEN amount ELSE 0 END) as refund_total")
            ->first();

        $betCount = (int) ($summary?->bet_count ?? 0);
        $refundCount = (int) ($summary?->refund_count ?? 0);
        $roundCount = (int) ($summary?->round_count ?? 0);

        if ($betCount < 5 || $refundCount < 3 || $roundCount < 5) {
            return null;
        }

        return [
            'reason' => 'rapid_debit_refund_lifecycle',
            'recent_bet_count' => $betCount,
            'recent_refund_count' => $refundCount,
            'recent_round_count' => $roundCount,
            'recent_bet_total' => round((float) ($summary?->bet_total ?? 0), 8),
            'recent_refund_total' => round((float) ($summary?->refund_total ?? 0), 8),
        ];
    }

    /**
     * @return list<string>
     */
    private function sessionProviderLifecycleKeys(ExternalGameSession $session): array
    {
        $extra = $this->gameExternalParameters($session);
        $providerConfig = $session->game?->provider?->config ?? [];
        if (is_string($providerConfig)) {
            $decoded = json_decode($providerConfig, true);
            $providerConfig = is_array($decoded) ? $decoded : [];
        }

        return collect([
            $extra['vendor_id'] ?? null,
            $extra['vendor_name'] ?? null,
            $extra['provider_key'] ?? null,
            $extra['provider_id'] ?? null,
            $session->game?->type_name ?? null,
            $session->game?->category_name ?? null,
            $session->game?->provider?->slug ?? null,
            is_array($providerConfig) ? ($providerConfig['provider_key'] ?? null) : null,
        ])
            ->map(fn ($provider): string => $this->providerLifecycleKey((string) $provider))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function gameExternalParameters(ExternalGameSession $session): array
    {
        $raw = $session->game?->extra_parameters;
        if (is_array($raw)) {
            return (array) ($raw['external_games'] ?? []);
        }

        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? (array) ($decoded['external_games'] ?? []) : [];
    }

    private function providerLifecycleKey(string $provider): string
    {
        return strtolower(trim(str_replace([' ', '_'], '-', $provider)));
    }

    private function roundHash(string $roundId): string
    {
        return hash('sha256', $roundId);
    }

    private function explicitRoundId(Request $request): string
    {
        return $this->stringField($request, ['round_id', 'roundId']);
    }

    private function callbackGameMatchesSession(Request $request, ExternalGameSession $session): bool
    {
        $game = $this->stringField($request, ['game', 'game_id', 'provider_game_id']);
        if ($game === '') {
            return true;
        }

        if ((string) $session->provider_game_id === $game) {
            return true;
        }

        $referenceId = (string) ($session->game?->reference_id ?? '');

        if ($referenceId !== '' && $referenceId === $game) {
            return true;
        }

        $lastObservedGame = $this->payloadStringField((array) $session->last_callback_payload, ['game', 'game_id', 'provider_game_id']);

        return $lastObservedGame !== '' && hash_equals($lastObservedGame, $game);
    }

    private function idempotencyKey(string $alias, string $operation, string $providerTransactionId): string
    {
        return ExternalGameProviderConfig::transactionPrefix($alias) . $operation . ':' . $providerTransactionId;
    }

    private function observe(
        ExternalGameSession $session,
        string $command,
        string $status,
        Request $request,
        ?string $transactionId = null
    ): void {
        if (!Schema::hasTable('external_game_sessions')) {
            return;
        }

        $payload = [
            'last_callback_command' => $command,
            'last_callback_transaction_id' => $transactionId,
            'last_callback_status' => $status,
            'last_callback_payload' => $this->safePayload($request),
            'last_callback_at' => now(),
        ];

        if (
            ($command === 'transaction_win' && in_array($status, ['success', 'zero_amount_ignored', 'zero_amount_success'], true))
            || ($command === 'refund' && $status === 'success')
        ) {
            $roundId = $this->explicitRoundId($request);
            if ($roundId !== '') {
                $snapshot = (array) ($session->playability_snapshot ?? []);
                $snapshot['terminal_round_hashes'] = collect((array) ($snapshot['terminal_round_hashes'] ?? []))
                    ->push($this->roundHash($roundId))
                    ->filter()
                    ->unique()
                    ->values()
                    ->take(-20)
                    ->all();

                $payload['playability_snapshot'] = $snapshot;
            }
        }

        $payload = collect($payload)
            ->filter(fn ($value, string $column): bool => Schema::hasColumn('external_game_sessions', $column))
            ->all();

        if ($payload === []) {
            return;
        }

        $session->forceFill($payload)->save();
    }

    private function safePayload(Request $request): array
    {
        return collect($request->all())
            ->mapWithKeys(function ($value, string $key): array {
                $normalized = strtolower($key);
                if (str_contains($normalized, 'secret') || str_contains($normalized, 'token')) {
                    return [$key => '[redacted]'];
                }

                return [$key => $value];
            })
            ->all();
    }

    private function providerCallbackSummary(
        Request $request,
        string $method,
        string $status,
        string $reason,
        ?string $providerTransactionId = null
    ): array
    {
        $payload = $request->all();

        return [
            'provider_alias' => $this->providerAlias($request),
            'method' => $method,
            'transaction_id' => $providerTransactionId,
            'status' => $status,
            'reason' => $reason,
            'observed_at' => now()->toDateTimeString(),
            'top_level_keys' => array_values(array_map(
                fn (string $key): string => $this->redactedPayloadKey($key),
                array_keys($payload)
            )),
            'has_user_id' => $this->hasAnyPayloadKey($payload, ['user_id', 'userId', 'player_id']),
            'has_transaction_id' => $this->hasAnyPayloadKey($payload, ['transaction_id', 'transactionId', 'txn_id']),
            'has_round_id' => $this->hasAnyPayloadKey($payload, ['round_id', 'roundId']),
            'has_session_id' => $this->hasAnyPayloadKey($payload, ['session_id']),
            'has_session_token' => $this->hasAnyPayloadKey($payload, ['session_token', 'callback_session_token', 'token']),
            'has_game' => $this->hasAnyPayloadKey($payload, ['game', 'game_id', 'provider_game_id']),
            'has_bet' => $this->hasAnyPayloadKey($payload, ['bet']),
            'has_win' => $this->hasAnyPayloadKey($payload, ['win']),
            'has_amount' => $this->hasAnyPayloadKey($payload, ['amount']),
            'has_agent_code' => $this->hasAnyPayloadKey($payload, ['agent_code']),
            'has_agent_token' => $this->hasAnyPayloadKey($payload, ['agent_token']),
            'has_agent_secret_key' => $this->hasAnyPayloadKey($payload, ['agent_secret_key']),
        ];
    }

    private function hasAnyPayloadKey(array $payload, array $keys): bool
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $payload)) {
                return true;
            }
        }

        return false;
    }

    private function redactedPayloadKey(string $key): string
    {
        $normalized = strtolower($key);

        return str_contains($normalized, 'secret') || str_contains($normalized, 'token')
            ? $key . ':[redacted]'
            : $key;
    }

    private function stringField(Request $request, array $keys): string
    {
        foreach ($keys as $key) {
            $value = trim((string) $request->input($key, ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function payloadStringField(array $payload, array $keys): string
    {
        foreach ($keys as $key) {
            $value = trim((string) ($payload[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function amount(Request $request): ?string
    {
        $raw = $this->stringField($request, ['amount', 'sum', 'value']);

        return preg_match('/\A[+-]?\d{1,16}(?:\.\d{1,2})?\z/', $raw) ? (string) BigDecimal::of($raw) : null;
    }

    private function providerAmount(Request $request, string $method): ?string
    {
        $field = match ($method) {
            'transaction_bet' => 'bet',
            'transaction_win' => 'win',
            'refund' => 'amount',
            default => '',
        };

        $raw = $field !== '' ? $this->stringField($request, [$field]) : '';
        if (!preg_match('/\A[+-]?\d{1,16}(?:\.\d{1,2})?\z/', $raw)) {
            return null;
        }

        $amount = BigDecimal::of($raw)->abs();

        return (string) ($method === 'transaction_bet' ? $amount->negated() : $amount);
    }

    private function callbackGroupingKey(Request $request, ExternalGameSession $session): string
    {
        return ExternalGameCallbackGroupingIdentity::resolve($request, $session)->value;
    }

    private function debitEventType(string $operation): string
    {
        return match ($operation) {
            'transaction_bet' => 'external_games_transaction_bet_debit',
            default => 'external_games_changebalance_debit',
        };
    }

    private function creditEventType(string $operation): string
    {
        return match ($operation) {
            'transaction_win' => 'external_games_transaction_win_credit',
            'refund' => 'external_games_refund_credit',
            default => 'external_games_changebalance_credit',
        };
    }

    private function transactionAction(string $operation, string $direction): int
    {
        if ($direction === 'debit') {
            return TransactionActionEnum::Bet;
        }

        return $operation === 'refund' ? TransactionActionEnum::Rollback : TransactionActionEnum::Win;
    }

    private function success(float|string $balance, array $extra = []): array
    {
        return array_merge([
            'status' => 1,
            'balance' => round($balance, 8),
            'currency' => 'TRY',
            'http_status' => 200,
        ], $extra);
    }

    private function failure(string $code, string $message, int $httpStatus = 200, array $extra = []): array
    {
        return array_merge([
            'status' => 0,
            'error' => $code,
            'message' => $message,
            'http_status' => $httpStatus,
        ], $extra);
    }

    private function providerSuccess(
        float|string $balance,
        ?ExternalGameSession $session = null,
        ?Request $request = null,
        ?string $providerTransactionId = null
    ): array
    {
        $profile = $this->providerResponseProfile($session);
        $response = [
            'status' => $this->providerResponseStatus($profile, 'success_status', 1),
            'balance' => $this->providerResponseBalance($profile, $balance),
            'http_status' => 200,
        ];

        if ((bool) ($profile['include_currency'] ?? false)) {
            $response['currency'] = (string) ($session?->wallet_currency ?: 'TRY');
        }

        if ((bool) ($profile['echo_transaction_id'] ?? false) && $providerTransactionId !== null && $providerTransactionId !== '') {
            $response['transaction_id'] = $providerTransactionId;
        }

        if ((bool) ($profile['echo_round_id'] ?? false) && $request instanceof Request) {
            $roundId = $this->explicitRoundId($request);
            if ($roundId !== '') {
                $response['round_id'] = $roundId;
            }
        }

        return $response;
    }

    private function providerAccountSuccess(?ExternalGameSession $session, array $extra): array
    {
        $profile = $this->providerResponseProfile($session);

        return array_merge([
            'status' => $this->providerResponseStatus($profile, 'success_status', 1),
            'http_status' => 200,
        ], $extra);
    }

    private function providerFailure(string $message, array $extra = [], ?ExternalGameSession $session = null): array
    {
        $profile = $this->providerResponseProfile($session);
        if (array_key_exists('balance', $extra)) {
            if ((bool) ($profile['include_failure_balance'] ?? true)) {
                $extra['balance'] = $this->providerResponseBalance($profile, (float) $extra['balance']);
            } else {
                unset($extra['balance']);
            }
        }

        $includeFailureError = (bool) ($profile['include_failure_error'] ?? true);
        $failureMessageKey = trim((string) ($profile['failure_message_key'] ?? 'message')) ?: 'message';
        $error = (string) ($extra['error'] ?? 'PROVIDER_CALLBACK_FAILED');
        unset($extra['error']);

        $response = [
            'status' => $this->providerResponseStatus($profile, 'failure_status', 0),
            'http_status' => 200,
        ];

        if ($includeFailureError) {
            $response['error'] = $error;
        }

        $response[$failureMessageKey] = $message;

        if ((bool) ($profile['include_currency'] ?? false) && !array_key_exists('currency', $extra)) {
            $response['currency'] = (string) ($session?->wallet_currency ?: 'TRY');
        }

        return array_merge($response, $extra);
    }

    private function providerResponseProfile(?ExternalGameSession $session): array
    {
        $default = (array) config('external_games.provider_response_profiles.default', []);
        $providers = (array) config('external_games.provider_response_profiles.providers', []);

        if (!$session instanceof ExternalGameSession) {
            return $default;
        }

        foreach ($this->sessionProviderLifecycleKeys($session) as $providerKey) {
            if (isset($providers[$providerKey]) && is_array($providers[$providerKey])) {
                return array_merge($default, $providers[$providerKey]);
            }
        }

        return $default;
    }

    private function providerResponseStatus(array $profile, string $key, int|string $fallback): int|string
    {
        $value = $profile[$key] ?? $fallback;
        $type = strtolower((string) ($profile['status_type'] ?? 'numeric'));

        if ($type === 'string') {
            return (string) $value;
        }

        return is_numeric($value) ? (int) $value : (string) $value;
    }

    private function providerResponseBalance(array $profile, float|string $balance): float|string
    {
        $rounded = round($balance, 8);
        $type = strtolower((string) ($profile['balance_type'] ?? 'number'));

        if ($type === 'string') {
            return (string) BigDecimal::of((string)$balance)->toScale(2, \Brick\Math\RoundingMode::HALF_UP);
        }

        return $rounded;
    }

    private function isSuccessfulResult(array $result): bool
    {
        return in_array($result['status'] ?? null, [1, 200, '1', '200', 'success'], true);
    }

    private function afterResponse(callable $callback): void
    {
        app()->terminating(fn () => $this->safeTelemetry($callback));
    }

    private function safeTelemetry(callable $callback): void
    {
        try {
            $callback();
        } catch (\Throwable $throwable) {
            report($throwable);
        }
    }
}
