<?php

namespace App\Services\ChessWallet;

use App\Enum\ChessWalletReasonCodeEnum;
use App\Enum\ChessWalletReservationStatusEnum;
use App\Enum\ChessWalletResultStatusEnum;
use App\Enum\TransactionActionEnum;
use App\Enum\TransactionStatusEnum;
use App\Models\ChessBettingMarket;
use App\Models\ChessLiveBoard;
use App\Models\ChessWalletReservation;
use App\Models\CmsUser;
use App\Models\CmsUserTransaction;
use App\Models\Provider;
use Brick\Math\BigDecimal;
use App\Services\Finance\PrelaunchFinanceWriteGate;
use App\Services\Wallet\ActiveWalletService;
use App\Services\Wallet\AssetWalletService;
use App\Services\Wallet\Exceptions\UnsupportedProductWalletException;
use App\Services\Wallet\ProductWalletCapabilityService;
use App\Services\Wallet\SpendableWalletContext;
use App\Services\Wallet\SpendableWalletService;
use App\Services\RiskLimits\CanonicalRiskLimitProfileService;
use App\Services\OpsRadar\OpsRadarEventEmitter;
use App\Services\ChessLiveCockpit\ChessRecurringBoardResolver;
use App\Services\ChessLiveCockpit\ChessRecurringSurfaceOverlayService;
use App\Models\WalletAccount;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ChessWalletReservationService
{
    public function __construct(
        private SpendableWalletService $wallets,
        private AssetWalletService $assetWallets,
        private ActiveWalletService $activeWallets,
        private ProductWalletCapabilityService $productWallets,
        private ChessRecurringBoardResolver $recurringBoards
    )
    {
    }

    public function reserve(array $payload, array $context, string $requestId, string $idempotencyKey): array
    {
        app(PrelaunchFinanceWriteGate::class)->assertWritable();
        if (!config('chess_integration.wallet_enabled') || !data_get($context,'wallet_config.partner_code')) return $this->failureResponse(403, ChessWalletReasonCodeEnum::INVALID_REQUEST, 'Wallet admission disabled.');
        $partnerCode = data_get($context, 'wallet_config.partner_code');
        $provider = data_get($context, 'provider');

        try {
            return DB::transaction(function () use ($payload, $context, $requestId, $idempotencyKey, $partnerCode, $provider) {
                $validationError = $this->validateReservePayload($payload);
                if ($validationError) {
                    return $validationError;
                }

                $payload = $this->recurringBoards->withCanonicalProvenance($payload);

                $existingByIdempotency = ChessWalletReservation::query()
                    ->where('partner_code', $partnerCode)
                    ->where('external_user_id', (string) $payload['externalUserId'])
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existingByIdempotency instanceof ChessWalletReservation) {
                    return $this->reserveReplay($existingByIdempotency, $payload);
                }

                $duplicateIntent = ChessWalletReservation::query()
                    ->where('partner_code', $partnerCode)
                    ->where('external_user_id', (string) $payload['externalUserId'])
                    ->where('placement_intent_id', (string) $payload['placementIntentId'])
                    ->whereIn('status', [
                        ChessWalletReservationStatusEnum::RESERVED,
                        ChessWalletReservationStatusEnum::RELEASED,
                        ChessWalletReservationStatusEnum::SETTLED_WIN,
                        ChessWalletReservationStatusEnum::SETTLED_LOSS,
                        ChessWalletReservationStatusEnum::SETTLED_VOID,
                    ])
                    ->first();

                if ($duplicateIntent instanceof ChessWalletReservation) {
                    return $this->sameIntent($duplicateIntent,$payload) ? $this->duplicateIntentResponse($duplicateIntent) : $this->conflict();
                }

                $currency = strtoupper((string) ($payload['currency'] ?? ''));
                $walletUser = $this->resolveWalletUser((string) $payload['externalUserId'], $currency !== '' ? $currency : null);

                if ($walletUser['wallet_context'] instanceof SpendableWalletContext) {
                    try { $payload['amount']=$this->wallets->decimalForContext($walletUser['wallet_context'],(string)$payload['amount']); }
                    catch (\Throwable) { return $this->failureResponse(422,ChessWalletReasonCodeEnum::INVALID_REQUEST,'Exact wallet precision required.'); }
                }
                try {
                    $reservation = ChessWalletReservation::query()->create([
                        'cms_reservation_id' => $this->generateReservationId(),
                        'cms_placement_ref' => $this->buildPlacementRef($partnerCode, (string) $payload['placementIntentId']),
                        'provider_id' => $provider instanceof Provider ? $provider->id : null,
                        'cms_user_id' => $walletUser['owner']?->id,
                        'partner_code' => $partnerCode,
                        'external_user_id' => (string) $payload['externalUserId'],
                        'idempotency_key' => $idempotencyKey,
                        'request_id' => $requestId,
                        'placement_intent_id' => (string) $payload['placementIntentId'],
                        'market_instance_id' => $payload['marketInstanceId'] ?? null,
                        'quote_expires_at' => $this->nullableCarbon($payload['quoteExpiresAt'] ?? null),
                        'stake_amount' => $payload['amount'],
                        'currency' => $currency !== '' ? $currency : ($walletUser['account']?->currency ?? 'TRY'),
                        'status' => ChessWalletReservationStatusEnum::CREATED,
                        'request_payload' => $this->reservationEvidence($payload, $walletUser['wallet_meta'] ?? []),
                    ]);
                } catch (QueryException $exception) {
                    $existingReservation = ChessWalletReservation::query()
                        ->where('partner_code', $partnerCode)
                        ->where('external_user_id', (string) $payload['externalUserId'])
                        ->where(function ($query) use ($idempotencyKey, $payload) {
                            $query->where('idempotency_key', $idempotencyKey)
                                ->orWhere('placement_intent_id', (string) $payload['placementIntentId']);
                        })
                        ->latest('id')
                        ->first();

                    if ($existingReservation instanceof ChessWalletReservation) {
                        return $existingReservation->idempotency_key === $idempotencyKey
                            ? $this->reserveReplay($existingReservation, $payload)
                            : ($this->sameIntent($existingReservation,$payload) ? $this->duplicateIntentResponse($existingReservation) : $this->conflict());
                    }

                    throw $exception;
                }

                $rejection = $this->evaluateReserveRequest($reservation, $walletUser, $payload, $context);
                if ($rejection) {
                    return $rejection;
                }

                $exposureRejection = $this->enforceMarketExposure($reservation, $payload);
                if ($exposureRejection) {
                    return $exposureRejection;
                }

                /** @var SpendableWalletContext $walletContext */
                $walletContext = $walletUser['wallet_context'];
                $account = $walletContext->ownerUser();
                $beforeBalance = $this->wallets->currentDecimalBalance($walletContext);
                $amount = $this->wallets->decimalForContext($walletContext, (string) $payload['amount']);
                $afterBalance = (string) BigDecimal::of($beforeBalance)->minus($amount);

                $transaction = CmsUserTransaction::query()->create([
                    'cms_user_id' => $walletContext->ownerWalletId(),
                    'transactionId' => $this->ledgerTransactionId('reserve', $reservation->cms_reservation_id),
                    'ref_transaction_id' => (string) $payload['placementIntentId'],
                    'round_id' => $reservation->cms_reservation_id,
                    'provider_transaction_id' => $requestId,
                    'game_provider_id' => $provider instanceof Provider ? $provider->id : null,
                    'action' => TransactionActionEnum::Bet,
                    'event_type' => 'chess_reserve',
                    'status' => TransactionStatusEnum::Success,
                    'amount' => $amount,
                    'currency' => $walletContext->currency(),
                    'source_wallet_account_id' => $walletContext->walletAccountId(),
                    'source_wallet_type' => $walletContext->walletType(),
                    'source_currency' => $walletContext->currency(),
                    'exact_amount' => $amount,
                    'exact_before_balance' => $beforeBalance,
                    'exact_after_balance' => $afterBalance,
                    'before_balance' => $beforeBalance,
                    'after_balance' => $afterBalance,
                    'acceptor' => 'api',
                ]);

                $this->wallets->assertNoDrift($walletContext, 'chess_reserve_before_debit', [
                    'reservation_id' => $reservation->cms_reservation_id,
                ]);
                $this->wallets->projectDecimalBalance($walletContext, $afterBalance);
                $this->wallets->recordDecimalLedgerEntry($walletContext, 'chess_reserve', 'debit', $amount, $beforeBalance, $afterBalance, $transaction->transactionId, $transaction, [], 'chess_reservation', $reservation->cms_reservation_id);

                $response = [
                    'status' => 'accepted',
                    'decision' => 'RESERVED',
                    'cmsReservationId' => $reservation->cms_reservation_id,
                    'cmsPlacementRef' => $reservation->cms_placement_ref,
                    'reservationStatus' => ChessWalletReservationStatusEnum::RESERVED,
                    'balance' => (float)$afterBalance,
                    'balanceExact' => $afterBalance,
                    'currency' => $walletContext->currency(),
                    'sourceOfTruth' => 'cms',
                    'walletOwnerId' => $walletContext->ownerWalletId(),
                    'walletContextId' => $walletContext->contextWalletId(),
                ];

                $reservation->update([
                    'reserve_transaction_id' => $transaction->id,
                    'status' => ChessWalletReservationStatusEnum::RESERVED,
                    'reserved_at' => now(),
                    'reserve_response_payload' => $response,
                ]);

                $this->logWebhook('chess.reserve.accepted', [
                    'cmsReservationId' => $reservation->cms_reservation_id,
                    'partnerCode' => $partnerCode,
                    'externalUserId' => $payload['externalUserId'],
                    'amount' => $payload['amount'],
                    'requestId' => $requestId,
                ]);

                return [
                    'httpStatus' => 200,
                    'body' => $response,
                ];
            }, 3);
        } catch (UnsupportedProductWalletException $exception) {
            $response = $this->failureResponse(422, ChessWalletReasonCodeEnum::UNSUPPORTED_ACTIVE_WALLET, $exception->getMessage());
            $response['body']['details'] = $exception->details();
            $response['body']['reason_code'] = $exception->details()['reason_code'] ?? 'unsupported_wallet';
            return $response;
        } catch (\Throwable) {
            $this->logWebhook('chess.reserve.exception', [
                'requestId' => $requestId,
                'reasonCode' => ChessWalletReasonCodeEnum::UNKNOWN,
                'httpStatus' => 500,
            ]);

            return $this->failureResponse(500, ChessWalletReasonCodeEnum::UNKNOWN, 'Reservation failed unexpectedly.');
        }
    }

    public function release(array $payload, array $context, string $requestId): array
    {
        app(PrelaunchFinanceWriteGate::class)->assertWritable();
        $reservationId = (string) ($payload['cmsReservationId'] ?? '');
        if ($reservationId === '') {
            return $this->failureResponse(422, ChessWalletReasonCodeEnum::INVALID_REQUEST, 'cmsReservationId is required.');
        }

        try {
            return DB::transaction(function () use ($payload, $context, $requestId, $reservationId) {
                $reservation = ChessWalletReservation::query()
                    ->where('cms_reservation_id', $reservationId)
                    ->lockForUpdate()
                    ->first();

                if (!$reservation instanceof ChessWalletReservation) {
                    return $this->failureResponse(404, ChessWalletReasonCodeEnum::INVALID_REQUEST, 'Reservation not found.');
                }

                if ($partnerRejection = $this->reservationPartnerRejection($reservation, $context)) {
                    return $partnerRejection;
                }

                if ($claimError=$this->claimRejection($reservation,$payload)) return $claimError;
                if ($reservation->status === ChessWalletReservationStatusEnum::RELEASED) {
                    if (!hash_equals((string)data_get($reservation->request_payload,'_release_sha256',''),$this->payloadHash($payload))) return $this->conflict();
                    return [
                        'httpStatus' => 200,
                        'body' => $this->releaseSuccessPayload($reservation, true),
                    ];
                }

                if (in_array($reservation->status, [
                    ChessWalletReservationStatusEnum::SETTLED_WIN,
                    ChessWalletReservationStatusEnum::SETTLED_LOSS,
                    ChessWalletReservationStatusEnum::SETTLED_VOID,
                    ChessWalletReservationStatusEnum::FAILED,
                ], true)) {
                    return $this->failureResponse(409, ChessWalletReasonCodeEnum::INVALID_REQUEST, 'Reservation is not releasable.');
                }

                $walletUser = $this->hydrateReservationUsers($reservation);
                if (!$walletUser) {
                    return $this->failureResponse(404, ChessWalletReasonCodeEnum::USER_NOT_ELIGIBLE, 'Reservation user could not be resolved.');
                }

                /** @var SpendableWalletContext $walletContext */
                $walletContext = $walletUser['wallet_context'];
                $account = $walletContext->ownerUser();
                $beforeBalance = $this->wallets->currentDecimalBalance($walletContext);
                $amount = $this->wallets->decimalForContext($walletContext, (string)$reservation->stake_amount);
                $afterBalance = (string) BigDecimal::of($beforeBalance)->plus($amount);

                $transaction = CmsUserTransaction::query()->create([
                    'cms_user_id' => $walletContext->ownerWalletId(),
                    'transactionId' => $this->ledgerTransactionId('release', $reservation->cms_reservation_id),
                    'ref_transaction_id' => $reservation->reserveTransaction?->transactionId,
                    'round_id' => $reservation->cms_reservation_id,
                    'provider_transaction_id' => $requestId,
                    'game_provider_id' => $reservation->provider_id,
                    'action' => TransactionActionEnum::Rollback,
                    'event_type' => 'chess_release',
                    'status' => TransactionStatusEnum::Success,
                    'amount' => $reservation->stake_amount,
                    'currency' => $walletContext->currency(),
                    'source_wallet_account_id' => $walletContext->walletAccountId(),
                    'source_wallet_type' => $walletContext->walletType(),
                    'source_currency' => $walletContext->currency(),
                    'exact_amount' => $amount,
                    'exact_before_balance' => $beforeBalance,
                    'exact_after_balance' => $afterBalance,
                    'before_balance' => $beforeBalance,
                    'after_balance' => $afterBalance,
                    'acceptor' => 'api',
                ]);

                $this->wallets->assertNoDrift($walletContext, 'chess_release_before_credit', [
                    'reservation_id' => $reservation->cms_reservation_id,
                ]);
                $this->wallets->projectDecimalBalance($walletContext, $afterBalance);
                $this->wallets->recordDecimalLedgerEntry($walletContext, 'chess_release', 'credit', $amount, $beforeBalance, $afterBalance, $transaction->transactionId, $transaction, [], 'chess_reservation', $reservation->cms_reservation_id);

                $reservation->update([
                    'status' => ChessWalletReservationStatusEnum::RELEASED,
                    'release_reason' => $this->releaseReasonCode($payload['reason'] ?? null),
                    'request_payload'=>array_merge((array)$reservation->request_payload,['_release_sha256'=>$this->payloadHash($payload)]),
                    'release_transaction_id' => $transaction->id,
                    'released_at' => now(),
                ]);

                $this->logWebhook('chess.release.accepted', [
                    'cmsReservationId' => $reservation->cms_reservation_id,
                    'requestId' => $requestId,
                ]);

                return [
                    'httpStatus' => 200,
                    'body' => $this->releaseSuccessPayload($reservation),
                ];
            },3);
        } catch (\Throwable) {
            $this->logWebhook('chess.release.exception', [
                'cmsReservationId' => $reservationId,
                'requestId' => $requestId,
                'reasonCode' => ChessWalletReasonCodeEnum::UNKNOWN,
                'httpStatus' => 500,
            ]);

            return $this->failureResponse(500, ChessWalletReasonCodeEnum::UNKNOWN, 'Release failed unexpectedly.');
        }
    }

    public function confirmReservation(array $payload, array $context, string $requestId): array
    {
        return DB::transaction(function() use ($payload,$context,$requestId) {
        $validationError = $this->validateConfirmPayload($payload);
        if ($validationError) {
            $this->logWebhook('chess.confirm.failed', [
                'requestId' => $requestId,
                'reasonCode' => ChessWalletReasonCodeEnum::INVALID_REQUEST,
                'httpStatus' => 422,
            ]);

            return $validationError;
        }

        $partnerCode = data_get($context, 'wallet_config.partner_code');
        if ((string) $payload['partnerCode'] !== (string) $partnerCode) {
            $this->logWebhook('chess.confirm.failed', [
                'requestId' => $requestId,
                'reasonCode' => ChessWalletReasonCodeEnum::INVALID_REQUEST,
                'httpStatus' => 422,
            ]);

            return $this->failureResponse(422, ChessWalletReasonCodeEnum::INVALID_REQUEST, 'partnerCode does not match signed partner context.');
        }

        $externalUserId = (string) $payload['externalUserId'];
        $placementIntentRef = isset($payload['placementIntentRef']) && $payload['placementIntentRef'] !== ''
            ? (string) $payload['placementIntentRef']
            : null;

        $this->logWebhook('chess.confirm.requested', [
            'requestId' => $requestId,
            'partnerCode' => $partnerCode,
            'externalUserId' => $externalUserId,
            'idempotencyKey' => $payload['idempotencyKey'],
            'placementIntentRef' => $placementIntentRef,
        ]);

        $reservation = ChessWalletReservation::query()
            ->where('partner_code', (string) $partnerCode)
            ->where('external_user_id', $externalUserId)
            ->where('idempotency_key', (string) $payload['idempotencyKey'])
            ->lockForUpdate()->latest('id')
            ->first();

        $matchedBy = 'idempotency_key';
        if (!$reservation instanceof ChessWalletReservation && $placementIntentRef !== null) {
            $reservation = ChessWalletReservation::query()
                ->where('partner_code', (string) $partnerCode)
                ->where('external_user_id', $externalUserId)
                ->where('placement_intent_id', $placementIntentRef)
                ->lockForUpdate()->latest('id')
                ->first();
            $matchedBy = 'placement_intent_id';
        }

        if (!$reservation instanceof ChessWalletReservation) {
            $body = [
                'status' => 'ok',
                'found' => false,
                'cmsReservationId' => null,
                'cmsPlacementRef' => null,
                'reservationStatus' => null,
                'accepted' => null,
                'rejectReason' => null,
                'rejectMessage' => null,
                'providerTimestamp' => now()->toAtomString(),
                'sourceOfTruth' => 'cms',
            ];

            $this->logWebhook('chess.confirm.not_found', [
                'requestId' => $requestId,
                'partnerCode' => $partnerCode,
                'externalUserId' => $externalUserId,
                'idempotencyKey' => $payload['idempotencyKey'],
                'placementIntentRef' => $placementIntentRef,
            ]);

            return [
                'httpStatus' => 200,
                'body' => $body,
            ];
        }

        if ($claimError=$this->claimRejection($reservation,$payload)) return $claimError;
        if ($reservation->placed_bet_id && !empty($payload['placedBetId']) && (string)$reservation->placed_bet_id !== (string)$payload['placedBetId']) return $this->conflict();
        if ($reservation->market_instance_id && !empty($payload['marketInstanceId']) && (string)$reservation->market_instance_id !== (string)$payload['marketInstanceId']) return $this->conflict();
        if(!empty($payload['placementIntentRef']) && (string)$reservation->placement_intent_id!==(string)$payload['placementIntentRef']) return $this->conflict();
        $confirmUpdates = [];
        if (!empty($payload['placedBetId'])) {
            $confirmUpdates['placed_bet_id'] = (string) $payload['placedBetId'];
        }

        if (!empty($payload['marketInstanceId']) && empty($reservation->market_instance_id)) {
            $confirmUpdates['market_instance_id'] = (string) $payload['marketInstanceId'];
        }

        if ($confirmUpdates !== []) {
            $reservation->update($confirmUpdates);
            $reservation->refresh();
        }

        $reserveResponsePayload = is_array($reservation->reserve_response_payload) ? $reservation->reserve_response_payload : [];
        $body = [
            'status' => 'ok',
            'found' => true,
            'cmsReservationId' => $reservation->cms_reservation_id,
            'cmsPlacementRef' => $reservation->cms_placement_ref,
            'reservationStatus' => $reservation->status,
            'accepted' => $this->acceptedFromStatus($reservation->status),
            'rejectReason' => $reservation->reject_reason,
            'rejectMessage' => data_get($reserveResponsePayload, 'message'),
            'providerTimestamp' => now()->toAtomString(),
            'placedBetId' => $reservation->placed_bet_id,
            'matchedBy' => $matchedBy,
            'sourceOfTruth' => 'cms',
        ];

        $this->logWebhook('chess.confirm.resolved', [
            'requestId' => $requestId,
            'partnerCode' => $partnerCode,
            'externalUserId' => $externalUserId,
            'idempotencyKey' => $payload['idempotencyKey'],
            'placementIntentRef' => $placementIntentRef,
            'cmsReservationId' => $reservation->cms_reservation_id,
            'reservationStatus' => $reservation->status,
            'matchedBy' => $matchedBy,
        ]);

        return [
            'httpStatus' => 200,
            'body' => $body,
        ];
        },3);
    }

    public function handoffSettlement(array $payload, string $requestId, array $context = []): array
    {
        app(PrelaunchFinanceWriteGate::class)->assertWritable();
        if (!(bool)data_get($context,'wallet_config.allow_settlement_handoff',false)) return $this->failureResponse(403,ChessWalletReasonCodeEnum::INVALID_REQUEST,'Settlement handoff is not authorized.');
        $validationError = $this->validateSettlementPayload($payload);
        if ($validationError) {
            return $validationError;
        }

        try {
            return DB::transaction(function () use ($payload, $requestId, $context) {
                $reservation = ChessWalletReservation::query()
                    ->where('cms_reservation_id', (string) $payload['cmsReservationId'])
                    ->lockForUpdate()
                    ->first();

                if (!$reservation instanceof ChessWalletReservation) {
                    return $this->failureResponse(404, ChessWalletReasonCodeEnum::INVALID_REQUEST, 'Reservation not found.');
                }

                if ($partnerRejection = $this->reservationPartnerRejection($reservation, $context)) {
                    return $partnerRejection;
                }

                if ($claimError=$this->claimRejection($reservation,$payload)) return $claimError;
                if ($reservation->placed_bet_id && (string)$reservation->placed_bet_id !== (string)$payload['placedBetId']) return $this->conflict();
                if ($reservation->market_instance_id && isset($payload['marketInstanceId']) && $reservation->market_instance_id !== (string)$payload['marketInstanceId']) return $this->conflict();
                $normalizedResult = $this->normalizeResultStatus((string) $payload['resultStatus']);
                if ($normalizedResult === null) {
                    return $this->failureResponse(422, ChessWalletReasonCodeEnum::INVALID_REQUEST, 'Unsupported settlement resultStatus.');
                }

                $targetStatus = $this->settledReservationStatus($normalizedResult);
                if ($reservation->status === $targetStatus) {
                    if (!hash_equals((string)data_get($reservation->last_settlement_payload,'payload_sha256',''),$this->payloadHash($payload))) return $this->conflict();
                    return [
                        'httpStatus' => 200,
                        'body' => $this->settlementSuccessPayload($reservation, true),
                    ];
                }

                if (in_array($reservation->status, [
                    ChessWalletReservationStatusEnum::SETTLED_WIN,
                    ChessWalletReservationStatusEnum::SETTLED_LOSS,
                    ChessWalletReservationStatusEnum::SETTLED_VOID,
                    ChessWalletReservationStatusEnum::FAILED,
                ], true)) {
                    return $this->failureResponse(409, ChessWalletReasonCodeEnum::INVALID_REQUEST, 'Reservation is already finalized with another state.');
                }

                if ($reservation->status === ChessWalletReservationStatusEnum::RELEASED
                    && $normalizedResult !== ChessWalletResultStatusEnum::VOID) {
                    return $this->failureResponse(409, ChessWalletReasonCodeEnum::INVALID_REQUEST, 'Released reservations may only be finalized as void.');
                }

                $walletUser = $this->hydrateReservationUsers($reservation);
                if (!$walletUser) {
                    return $this->failureResponse(404, ChessWalletReasonCodeEnum::USER_NOT_ELIGIBLE, 'Reservation user could not be resolved.');
                }

                $settlementTransactionId = null;
                if ($normalizedResult === ChessWalletResultStatusEnum::VOID && $reservation->status !== ChessWalletReservationStatusEnum::RELEASED) {
                    $settlementTransactionId = $this->refundReservation($reservation, $walletUser, $requestId, 'chess_settlement_void');
                }

                if ($normalizedResult === ChessWalletResultStatusEnum::WIN) {
                    $settlementTransactionId = $this->creditPayout($reservation, $walletUser, $requestId, (string) $payload['payoutAmount']);
                }

                $reservation->update([
                    'status' => $targetStatus,
                    'result_status' => $normalizedResult,
                    'placed_bet_id' => (string) $payload['placedBetId'],
                    'market_instance_id' => $payload['marketInstanceId'] ?? $reservation->market_instance_id,
                    'payout_amount' => $payload['payoutAmount'] ?? $reservation->payout_amount,
                    'settlement_summary' => $this->settlementEvidence($payload['settlementSummary']),
                    'last_settlement_payload' => [
                        'request_id_sha256' => hash('sha256', $requestId),
                        'payload_sha256' => $this->payloadHash($payload),
                    ],
                    'last_settlement_handoff_at' => now(),
                    'settled_at' => Carbon::parse($payload['settledAt']),
                    'settlement_transaction_id' => $settlementTransactionId,
                ]);

                $this->logWebhook('chess.settlement.accepted', [
                    'cmsReservationId' => $reservation->cms_reservation_id,
                    'resultStatus' => $normalizedResult,
                    'requestId' => $requestId,
                ]);

                return [
                    'httpStatus' => 200,
                    'body' => $this->settlementSuccessPayload($reservation->fresh()),
                ];
            },3);
        } catch (\Throwable) {
            $this->logWebhook('chess.settlement.exception', [
                'cmsReservationId' => $payload['cmsReservationId'] ?? null,
                'requestId' => $requestId,
                'reasonCode' => ChessWalletReasonCodeEnum::UNKNOWN,
                'httpStatus' => 500,
            ]);

            return $this->failureResponse(500, ChessWalletReasonCodeEnum::UNKNOWN, 'Settlement handoff failed unexpectedly.');
        }
    }

    public function reservationLookup(string $reservationId, array $context = []): array
    {
        $reservation = ChessWalletReservation::query()
            ->where('cms_reservation_id', $reservationId)
            ->first();

        if (!$reservation instanceof ChessWalletReservation) {
            return $this->failureResponse(404, ChessWalletReasonCodeEnum::INVALID_REQUEST, 'Reservation not found.');
        }

        if ($partnerRejection = $this->reservationPartnerRejection($reservation, $context)) {
            return $partnerRejection;
        }

        return [
            'httpStatus' => 200,
            'body' => [
                'status' => 'ok',
                'cmsReservationId' => $reservation->cms_reservation_id,
                'cmsPlacementRef' => $reservation->cms_placement_ref,
                'reservationStatus' => $reservation->status,
                'partnerCode' => $reservation->partner_code,
                'externalUserId' => $reservation->external_user_id,
                'stakeAmount' => (float) $reservation->stake_amount,
                'currency' => $reservation->currency,
                'holdState' => $this->holdState($reservation),
                'idempotencyKey' => $reservation->idempotency_key,
                'placedBetId' => $reservation->placed_bet_id,
                'lastSettlementHandoffAt' => optional($reservation->last_settlement_handoff_at)->toAtomString(),
                'resultStatus' => $reservation->result_status,
                'rejectReason' => $reservation->reject_reason,
                'sourceOfTruth' => 'cms',
            ],
        ];
    }

    public function balance(array $payload, array $context): array
    {
        $externalUserId = trim((string) ($payload['externalUserId'] ?? ''));
        if ($externalUserId === '') {
            return $this->failureResponse(422, ChessWalletReasonCodeEnum::INVALID_REQUEST, 'externalUserId is required.');
        }

        $currency = strtoupper((string) ($payload['currency'] ?? ''));
        $walletUser = $this->resolveWalletUser($externalUserId, $currency !== '' ? $currency : null);

        if (!$walletUser['account'] instanceof CmsUser) {
            return $this->failureResponse(404, ChessWalletReasonCodeEnum::USER_NOT_ELIGIBLE, 'User could not be resolved.');
        }

        /** @var SpendableWalletContext $walletContext */
        $walletContext = $walletUser['wallet_context'];
        $account = $walletContext->ownerUser();

        return [
            'httpStatus' => 200,
            'body' => [
                'status' => 'ok',
                'balance' => $this->wallets->currentBalance($walletContext),
                'balanceExact' => $this->wallets->currentDecimalBalance($walletContext),
                'currency' => $walletContext->currency(),
                'providerTimestamp' => now()->toAtomString(),
                'sourceOfTruth' => 'cms',
                'walletOwnerId' => $walletContext->ownerWalletId(),
                'walletContextId' => $walletContext->contextWalletId(),
            ],
        ];
    }

    public function health(array $context): array
    {
        $provider = data_get($context, 'provider');
        $walletConfig = data_get($context, 'wallet_config', []);

        return [
            'httpStatus' => 200,
            'body' => [
                'status' => 'ok',
                'providerSlug' => config('chess_wallet.provider_slug'),
                'providerRegistered' => $provider instanceof Provider,
                'partnerCode' => $walletConfig['partner_code'] ?? null,
                'signingAlgorithm' => data_get($walletConfig, 'signing.algorithm', 'hmac-sha256'),
                'timestampToleranceSeconds' => (int) data_get($walletConfig, 'timestamp_tolerance_seconds', config('chess_wallet.timestamp_tolerance_seconds')),
                'sourceOfTruth' => 'cms',
            ],
        ];
    }

    private function validateReservePayload(array $payload): ?array
    {
        foreach (['externalUserId', 'placementIntentId', 'amount'] as $field) {
            if (!array_key_exists($field, $payload) || $payload[$field] === null || $payload[$field] === '') {
                return $this->failureResponse(422, ChessWalletReasonCodeEnum::INVALID_REQUEST, $field . ' is required.');
            }
        }

        if (!is_numeric($payload['amount']) || (float) $payload['amount'] <= 0) {
            return $this->failureResponse(422, ChessWalletReasonCodeEnum::INVALID_REQUEST, 'amount must be a positive number.');
        }

        if (isset($payload['quoteExpiresAt']) && $this->nullableCarbon($payload['quoteExpiresAt']) === null) {
            return $this->failureResponse(422, ChessWalletReasonCodeEnum::INVALID_REQUEST, 'quoteExpiresAt must be a valid datetime.');
        }

        if (array_key_exists('marketExposureLimit', $payload)
            && (!is_numeric($payload['marketExposureLimit']) || (float) $payload['marketExposureLimit'] <= 0)) {
            return $this->failureResponse(422, ChessWalletReasonCodeEnum::INVALID_REQUEST, 'marketExposureLimit must be a positive number.');
        }

        return null;
    }

    private function validateConfirmPayload(array $payload): ?array
    {
        foreach (['partnerCode', 'externalUserId', 'idempotencyKey', 'requestTimestamp'] as $field) {
            if (!array_key_exists($field, $payload) || $payload[$field] === null || $payload[$field] === '') {
                return $this->failureResponse(422, ChessWalletReasonCodeEnum::INVALID_REQUEST, $field . ' is required.');
            }
        }

        if ($this->nullableCarbon($payload['requestTimestamp']) === null) {
            return $this->failureResponse(422, ChessWalletReasonCodeEnum::INVALID_REQUEST, 'requestTimestamp must be a valid datetime.');
        }

        if (isset($payload['placedBetId']) && $payload['placedBetId'] !== null && trim((string) $payload['placedBetId']) === '') {
            return $this->failureResponse(422, ChessWalletReasonCodeEnum::INVALID_REQUEST, 'placedBetId must not be blank when provided.');
        }

        if (isset($payload['marketInstanceId']) && $payload['marketInstanceId'] !== null && trim((string) $payload['marketInstanceId']) === '') {
            return $this->failureResponse(422, ChessWalletReasonCodeEnum::INVALID_REQUEST, 'marketInstanceId must not be blank when provided.');
        }

        return null;
    }

    private function validateSettlementPayload(array $payload): ?array
    {
        foreach (['cmsReservationId', 'placedBetId', 'resultStatus', 'settlementSummary', 'settledAt'] as $field) {
            if (!array_key_exists($field, $payload) || $payload[$field] === null || $payload[$field] === '') {
                return $this->failureResponse(422, ChessWalletReasonCodeEnum::INVALID_REQUEST, $field . ' is required.');
            }
        }

        if (!is_array($payload['settlementSummary'])) {
            return $this->failureResponse(422, ChessWalletReasonCodeEnum::INVALID_REQUEST, 'settlementSummary must be an object.');
        }

        if ($this->nullableCarbon($payload['settledAt']) === null) {
            return $this->failureResponse(422, ChessWalletReasonCodeEnum::INVALID_REQUEST, 'settledAt must be a valid datetime.');
        }

        // A WIN must carry a payable exact fiat amount before any terminal state is written.
        if ($this->normalizeResultStatus((string) $payload['resultStatus']) === ChessWalletResultStatusEnum::WIN) {
            $payout = $payload['payoutAmount'] ?? null;
            if ((!is_string($payout) && !is_int($payout) && !is_float($payout))
                || preg_match('/\A\d{1,16}(?:\.\d{1,2})?\z/D', (string) $payout) !== 1
                || !BigDecimal::of((string) $payout)->isPositive()) {
                return $this->failureResponse(422, ChessWalletReasonCodeEnum::INVALID_REQUEST, 'WIN requires a positive exact fiat payoutAmount.');
            }
            return null;
        }

        if (isset($payload['payoutAmount']) && (!is_numeric($payload['payoutAmount']) || (float) $payload['payoutAmount'] < 0)) {
            return $this->failureResponse(422, ChessWalletReasonCodeEnum::INVALID_REQUEST, 'payoutAmount must be a non-negative number.');
        }
        if(isset($payload['payoutAmount']) && preg_match('/\A\d{1,16}(?:\.\d{1,2})?\z/D',(string)$payload['payoutAmount'])!==1) return $this->failureResponse(422,ChessWalletReasonCodeEnum::INVALID_REQUEST,'Exact fiat payout precision required.');

        return null;
    }

    /**
     * Only store values needed to reconstruct the original wallet and to review
     * a Chess market. The signed provider request may contain unrelated data.
     */
    private function reservationEvidence(array $payload, array $walletMeta): array
    {
        $evidence = ['_intent_sha256'=>$this->intentHash($payload)];

        foreach (['matchId', 'selectionId', ChessRecurringBoardResolver::PROVENANCE_KEY] as $key) {
            $code = $this->safeEvidenceCode($payload[$key] ?? null, 128);
            if ($code !== null) {
                $evidence[$key] = $code;
            }
        }

        if (is_numeric($payload['oddsDecimal'] ?? null)) {
            $odds = (float) $payload['oddsDecimal'];
            if (is_finite($odds) && $odds > 0 && $odds <= 10000) {
                $evidence['oddsDecimal'] = $odds;
            }
        }

        foreach (['wallet_context_user_id', 'wallet_account_id'] as $key) {
            if (isset($walletMeta[$key]) && (int) $walletMeta[$key] > 0) {
                $evidence[$key] = (int) $walletMeta[$key];
            }
        }

        $walletType = $this->safeEvidenceCode($walletMeta['wallet_type'] ?? null, 32);
        if ($walletType !== null) {
            $evidence['wallet_type'] = $walletType;
        }

        return $evidence;
    }

    /** Keep correlation and provenance, never the provider's free-form summary. */
    private function settlementEvidence(array $summary): array
    {
        return [
            'source' => ($summary['source'] ?? null) === 'manual_settlement_desk'
                ? 'manual_settlement_desk'
                : 'provider_handoff',
            'summary_sha256' => hash('sha256', json_encode($summary, JSON_THROW_ON_ERROR)),
        ];
    }

    private function releaseReasonCode(mixed $reason): ?string
    {
        $code = $this->safeEvidenceCode($reason, 64);

        return in_array($code, ['bet_cancelled', 'provider_cancelled'], true)
            ? $code
            : ($reason === null ? null : 'provider_release');
    }

    private function safeEvidenceCode(mixed $value, int $maxLength): ?string
    {
        if (!is_string($value) && !is_int($value)) {
            return null;
        }

        $code = trim((string) $value);

        return $code !== '' && strlen($code) <= $maxLength
            && preg_match('/secret|token|authorization|cookie|session|csrf|password|bearer|https?/i', $code) !== 1
            && preg_match('/\A[A-Za-z0-9][A-Za-z0-9_.:-]*\z/D', $code) === 1
                ? $code
                : null;
    }

    private function evaluateReserveRequest(ChessWalletReservation $reservation, array $walletUser, array $payload, array $context): ?array
    {
        $account = $walletUser['owner'];
        $root = $walletUser['root'];

        if (!$account instanceof CmsUser || !$root instanceof CmsUser) {
            return $this->markFailedReservation($reservation, ChessWalletReasonCodeEnum::USER_NOT_ELIGIBLE, 'User could not be resolved.');
        }

        $requestCurrency = strtoupper((string) ($payload['currency'] ?? $walletUser['wallet_context']->currency()));
        if ($requestCurrency !== strtoupper((string) $walletUser['wallet_context']->currency())) {
            return $this->markFailedReservation($reservation, ChessWalletReasonCodeEnum::INVALID_REQUEST, 'Currency mismatch.');
        }

        if ($claimError=$this->claimRejection($reservation,$payload)) return $this->markFailedReservation($reservation,ChessWalletReasonCodeEnum::INVALID_REQUEST,'Wallet context mismatch.');
        $provider=data_get($context,'provider');
        if (!$provider instanceof Provider || $provider->status!=='active' || $provider->is_maintenance) return $this->markFailedReservation($reservation,ChessWalletReasonCodeEnum::USER_NOT_ELIGIBLE,'Product unavailable.');
        $game=\App\Models\CmsGame::with('provider')->where('slug','chess-bet')->first();
        if (!$game || !app(\App\Services\Games\CatalogReadService::class)->allowed($game,$root) || !app(\App\Services\Restrictions\CentralRestrictionPolicyService::class)->decideCountryRestriction((int)$root->country_id,'chessbet')->allowed || $game->is_maintenance) return $this->markFailedReservation($reservation,ChessWalletReasonCodeEnum::USER_NOT_ELIGIBLE,'Product access denied.');
        $blockedStatuses = data_get($context, 'wallet_config.blocked_user_statuses', config('chess_wallet.blocked_user_statuses', []));
        if (in_array($root->status, $blockedStatuses, true) || in_array($account->status, $blockedStatuses, true)) {
            return $this->markFailedReservation($reservation, ChessWalletReasonCodeEnum::USER_NOT_ELIGIBLE, 'User is not eligible.');
        }

        $quoteExpiresAt = $this->nullableCarbon($payload['quoteExpiresAt'] ?? null);
        if ($quoteExpiresAt && $quoteExpiresAt->isPast()) {
            return $this->markFailedReservation($reservation, ChessWalletReasonCodeEnum::STALE_QUOTE, 'Quote has expired.');
        }

        $recurringMarketError = $this->validateRecurringTournamentMarket($reservation, $payload);
        if ($recurringMarketError) {
            return $recurringMarketError;
        }

        $amount = (string) $payload['amount'];
        $limitDecision = $this->limitDecision($account, $amount, $context);
        if ($limitDecision) {
            $response = $this->markFailedReservation($reservation, $limitDecision['code'], 'Stake limit exceeded.');
            // Existing domain evidence remains recorded; optional Ops Radar is outside this transfer.

            return $response;
        }

        if (BigDecimal::of((string)$payload['amount'])->isGreaterThan($this->wallets->currentDecimalBalance($walletUser['wallet_context']))) {
            return $this->markFailedReservation($reservation, ChessWalletReasonCodeEnum::INSUFFICIENT_BALANCE, 'Insufficient balance.');
        }

        return null;
    }

    private function validateRecurringTournamentMarket(ChessWalletReservation $reservation, array $payload): ?array
    {
        $marketInstanceId = trim((string) ($payload['marketInstanceId'] ?? ''));
        if ($marketInstanceId === '') {
            return null;
        }

        if (!Schema::hasTable('chess_live_boards')) {
            return Str::startsWith($marketInstanceId, 'cms-recurring-')
                ? $this->markFailedReservation($reservation, ChessWalletReasonCodeEnum::INVALID_REQUEST, 'Recurring tournament market is not available.')
                : null;
        }

        $boardKey=$this->recurringBoards->resolve($marketInstanceId,$payload);
        if (array_key_exists(ChessRecurringBoardResolver::PROVENANCE_KEY,$payload) && $boardKey===null) return $this->markFailedReservation($reservation,ChessWalletReasonCodeEnum::INVALID_REQUEST,'Recurring market identity conflict.');
        $recurringReference = $boardKey!==null || Str::startsWith($marketInstanceId, 'cms-recurring-');

        $board = ChessLiveBoard::query()
            ->with(['bettingMarkets.selections'])
            ->where('external_board_key', $boardKey ?: $marketInstanceId)
            ->first();

        if ($board instanceof ChessLiveBoard) {
            $known=$board->bettingMarkets->sortByDesc('id')->first();
            if ($known && ($known->status!=='open' || (bool)data_get($known->meta,'shadow_mode',false) || data_get($known->meta,'execution_mode')==='shadow')) return $this->markFailedReservation($reservation,ChessWalletReasonCodeEnum::INVALID_REQUEST,'Known market is closed or shadow-only.');
        }
        if (!$board instanceof ChessLiveBoard || !(bool) data_get($board->meta, 'recurring.enabled', false)) {
            return $recurringReference
                ? $this->markFailedReservation($reservation, ChessWalletReasonCodeEnum::INVALID_REQUEST, 'Recurring tournament market is not available.')
                : null;
        }

        $market = $board->bettingMarkets->sortByDesc('id')
            ->first(fn (ChessBettingMarket $candidate): bool => (string) $candidate->template_variant === 'recurring');

        if (!$market instanceof ChessBettingMarket) {
            return $this->markFailedReservation($reservation, ChessWalletReasonCodeEnum::INVALID_REQUEST, 'Recurring tournament market is not available.');
        }

        if (strtolower((string)$market->status)!=='open' || (bool)data_get($market->meta,'shadow_mode',false) || data_get($market->meta,'execution_mode')==='shadow') return $this->markFailedReservation($reservation,ChessWalletReasonCodeEnum::INVALID_REQUEST,'Market is closed or shadow-only.');
        if ((string) $market->template_type !== 'tournament_winner') {
            return $this->markFailedReservation($reservation, ChessWalletReasonCodeEnum::INVALID_REQUEST, 'Recurring tournament market is not configured for winner betting.');
        }

        $schedule = $this->recurringTournamentSchedule((array) data_get($board->meta, 'recurring', []));
        if (($schedule['status'] ?? null) !== 'open') {
            return $this->markFailedReservation($reservation, ChessWalletReasonCodeEnum::INVALID_REQUEST, 'Recurring tournament betting is not open.');
        }

        $selectionId = trim((string) ($payload['selectionId'] ?? ''));
        if ($selectionId === '') {
            return $this->markFailedReservation($reservation, ChessWalletReasonCodeEnum::INVALID_REQUEST, 'selectionId is required for recurring tournament bets.');
        }

        $selection = $market->selections
            ->first(fn ($candidate): bool => (string) $candidate->selection_code === $selectionId);

        if (!$selection || $this->isLegacyRecurringSelectionCode($selectionId)) {
            return $this->markFailedReservation($reservation, ChessWalletReasonCodeEnum::INVALID_REQUEST, 'Selection is not available for this recurring tournament.');
        }

        return null;
    }

    private function recurringTournamentSchedule(array $recurring): array
    {
        $sourceTimezone = (string) ($recurring['schedule_timezone'] ?? 'Europe/Istanbul');
        $now = Carbon::now($sourceTimezone);
        $weekday = max(1, min(7, (int) ($recurring['weekday'] ?? $now->isoWeekday())));
        $startTime = (string) ($recurring['start_time'] ?? '20:00');
        [$hour, $minute] = array_pad(array_map('intval', explode(':', $startTime, 2)), 2, 0);

        $daysUntil = ($weekday - $now->isoWeekday() + 7) % 7;
        $start = $now->copy()
            ->startOfDay()
            ->addDays($daysUntil)
            ->setTime($hour, $minute);

        if ($now->isoWeekday() === $weekday) {
            $todayStart = $now->copy()->startOfDay()->setTime($hour, $minute);
            $todayEnd = $todayStart->copy()->addMinutes(max(30, (int) ($recurring['duration_minutes'] ?? 120)));

            $start = $now->lessThan($todayEnd)
                ? $todayStart
                : $todayStart->copy()->addWeek();
        }

        $openAt = $start->copy()->subHours(max(0, (int) ($recurring['betting_open_hours_before'] ?? ChessRecurringSurfaceOverlayService::BETTING_OPEN_HOURS_BEFORE)));
        $closeAt = $start->copy()->addMinutes(ChessRecurringSurfaceOverlayService::BETTING_CLOSE_MINUTES_AFTER);

        return [
            'status' => $now->greaterThanOrEqualTo($openAt) && $now->lessThan($closeAt)
                ? 'open'
                : 'closed',
        ];
    }

    private function isLegacyRecurringSelectionCode(string $selectionCode): bool
    {
        return in_array($selectionCode, ['home', 'draw', 'away'], true);
    }

    private function resolveWalletUser(string $externalUserId, ?string $currency = null): array
    {
        $user = $this->findExternalUser($externalUserId);

        if (!$user instanceof CmsUser) {
            return ['root' => null, 'owner' => null, 'account' => null, 'wallet_context' => null];
        }

        $activeWallet = app(\App\Services\Wallet\WalletPlayabilityService::class)->assertForUser('chess', $user, [], true);
        if ($activeWallet instanceof WalletAccount) {
            $capability = $this->productWallets->evaluate('chess', $activeWallet);
            if (!(bool) ($capability['supported'] ?? false)) {
                throw new UnsupportedProductWalletException(
                    trans('wallet_playability.unsupported_wallet'),
                    $this->productWallets->resolveForUser('chess', $user) + [
                        'activeWallet' => $this->assetWallets->serializeWalletAccount($activeWallet, $user),
                    ]
                );
            }

            $currency = (string) $activeWallet->asset;
        }

        $walletContext = $activeWallet instanceof WalletAccount
            ? $this->wallets->resolveForWallet($user, $activeWallet, true)
            : null;
        if (!$walletContext instanceof SpendableWalletContext) {
            return ['root' => null, 'owner' => null, 'account' => null, 'wallet_context' => null];
        }

        return [
            'root' => $walletContext->rootUser(),
            'owner' => $walletContext->ownerUser(),
            'account' => $walletContext->ownerUser(),
            'context' => $walletContext->contextUser(),
            'wallet_context' => $walletContext,
            'wallet_meta' => array_merge($walletContext->auditPayload(), [
                'product_type' => 'chess',
                'wallet_account_id' => $activeWallet?->id,
                'wallet_type' => $activeWallet?->wallet_type,
                'wallet_currency' => $activeWallet?->asset ?: $walletContext->currency(),
                'active_wallet_id' => $activeWallet?->id,
            ]),
        ];
    }

    private function hydrateReservationUsers(ChessWalletReservation $reservation): ?array
    {
        $lookupUserId = (int) data_get($reservation->request_payload, 'wallet_context_user_id', 0);
        if ($lookupUserId <= 0) {
            $lookupUserId = (int) ($reservation->cms_user_id ?: 0);
        }

        if ($lookupUserId <= 0) {
            return null;
        }

        $lookupUser = CmsUser::query()->find($lookupUserId);
        $walletContext = $lookupUser instanceof CmsUser
            ? $this->wallets->resolveForStoredContext(
                $lookupUser,
                (int) data_get($reservation->request_payload, 'wallet_account_id', 0) ?: null,
                (string) data_get($reservation->request_payload, 'wallet_type', 'primary_balance'),
                (string) ($reservation->currency ?: data_get($reservation->request_payload, 'wallet_currency')),
                $lookupUserId,
                true
            )
            : null;
        if (!$walletContext instanceof SpendableWalletContext) {
            return null;
        }

        return [
            'root' => $walletContext->rootUser(),
            'owner' => $walletContext->ownerUser(),
            'account' => $walletContext->ownerUser(),
            'context' => $walletContext->contextUser(),
            'wallet_context' => $walletContext,
        ];
    }

    private function findExternalUser(string $externalUserId): ?CmsUser
    {
        $lookupFields = config('chess_wallet.external_user_lookup_fields', []);

        foreach ($lookupFields as $field) {
            if (!Schema::hasColumn('cms_users',$field)) continue;
            if ($field === 'id' && !ctype_digit($externalUserId)) {
                continue;
            }

            $query = CmsUser::query()->where($field, $field === 'id' ? (int) $externalUserId : $externalUserId);
            $user = $query->first();
            if ($user instanceof CmsUser) {
                return $user;
            }
        }

        return null;
    }

    private function limitDecision(CmsUser $account, string $amount, array $context): ?array
    {
        $currency = strtoupper((string) data_get($context, 'wallet_config.currency', $account->currency ?: 'TRY'));
        $canonical = app(CanonicalRiskLimitProfileService::class)->chessbetLimitsForCurrency($currency);
        $configuredMin = data_get($context, 'wallet_config.limits.min');
        $configuredMax = data_get($context, 'wallet_config.limits.max');

        if (is_array($canonical)) {
            $configuredMin = $this->mostRestrictiveMinimum($configuredMin, $canonical['min'] ?? null);
            $configuredMax = $this->mostRestrictiveMaximum($configuredMax, $canonical['max'] ?? null);
        }

        if ($configuredMin !== null && BigDecimal::of($amount)->isLessThan((string)$configuredMin)) {
            return [
                'code' => ChessWalletReasonCodeEnum::LIMIT_EXCEEDED,
                'limit' => (float) $configuredMin,
                'minimum' => (float) $configuredMin,
                'maximum' => is_numeric($configuredMax) ? (float) $configuredMax : null,
            ];
        }

        if ($configuredMax !== null && BigDecimal::of($amount)->isGreaterThan((string)$configuredMax)) {
            return [
                'code' => ChessWalletReasonCodeEnum::LIMIT_EXCEEDED,
                'limit' => (float) $configuredMax,
                'minimum' => is_numeric($configuredMin) ? (float) $configuredMin : null,
                'maximum' => (float) $configuredMax,
            ];
        }

        return null;
    }

    private function enforceMarketExposure(ChessWalletReservation $reservation, array $payload): ?array
    {
        $currency = strtoupper((string) $reservation->currency);
        $marketInstanceId = trim((string) $reservation->market_instance_id);
        $limit = $this->marketExposureLimit($currency, $payload);

        if ($limit === null) {
            return null;
        }

        // Keep the existing ChessBet-side limit as the rollout fallback until the
        // targeted scope-lock migration is present; never block betting solely
        // because application code activated before its additive table.
        if (!Schema::hasTable('chess_market_exposure_scopes')) {
            return null;
        }

        if ($marketInstanceId === '') {
            return $this->markFailedReservation(
                $reservation,
                ChessWalletReasonCodeEnum::INVALID_REQUEST,
                'marketInstanceId is required when market exposure is enforced.'
            );
        }

        $canonicalMarket=$this->recurringBoards->resolve($marketInstanceId,$payload) ?: $marketInstanceId;
        $scopeKey = hash('sha256', $canonicalMarket . '|' . $currency);

        DB::table('chess_market_exposure_scopes')->insertOrIgnore([
            'scope_key' => $scopeKey,
            'market_instance_id' => $marketInstanceId,
            'currency' => $currency,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('chess_market_exposure_scopes')
            ->where('scope_key', $scopeKey)
            ->lockForUpdate()
            ->first();

        $reservedExposure = ChessWalletReservation::query()
            ->where(fn($q)=>$q->where('market_instance_id',$marketInstanceId)->orWhere('request_payload->'.ChessRecurringBoardResolver::PROVENANCE_KEY,$canonicalMarket))
            ->where('currency', $currency)
            ->where('status', ChessWalletReservationStatusEnum::RESERVED)
            ->lockForUpdate()
            ->get(['stake_amount'])
            ->reduce(fn (BigDecimal $sum, ChessWalletReservation $candidate) => $sum->plus((string)$candidate->stake_amount),BigDecimal::zero());

        if ($reservedExposure->plus((string)$payload['amount'])->isLessThanOrEqualTo((string)$limit)) {
            return null;
        }

        $response = $this->markFailedReservation(
            $reservation,
            ChessWalletReasonCodeEnum::LIMIT_EXCEEDED,
            'Market exposure limit reached.'
        );

        // Existing domain evidence remains recorded; optional Ops Radar is outside this transfer.

        return $response;
    }

    private function marketExposureLimit(string $currency, array $payload): ?string
    {
        $canonical = app(CanonicalRiskLimitProfileService::class)->chessbetLimitsForCurrency($currency);
        $canonicalLimit = is_array($canonical) ? ($canonical['market_exposure'] ?? null) : null;

        if (is_numeric($canonicalLimit) && (float) $canonicalLimit > 0) {
            return (string) $canonicalLimit;
        }

        $signedServiceLimit = $payload['marketExposureLimit'] ?? null;

        return is_numeric($signedServiceLimit) && (float) $signedServiceLimit > 0
            ? (string) $signedServiceLimit
            : null;
    }

    private function mostRestrictiveMinimum(mixed $left, mixed $right): ?string
    {
        $values = array_values(array_filter([$left, $right], fn ($value): bool => is_numeric($value) && (float) $value > 0));

        return $values===[]?null:(string)array_reduce($values,fn($best,$v)=>$best===null||BigDecimal::of((string)$v)->isGreaterThan((string)$best)?$v:$best);
    }

    private function mostRestrictiveMaximum(mixed $left, mixed $right): ?string
    {
        $values = array_values(array_filter([$left, $right], fn ($value): bool => is_numeric($value) && (float) $value > 0));

        return $values===[]?null:(string)array_reduce($values,fn($best,$v)=>$best===null||BigDecimal::of((string)$v)->isLessThan((string)$best)?$v:$best);
    }

    private function markFailedReservation(ChessWalletReservation $reservation, string $reasonCode, string $message): array
    {
        $response = [
            'status' => 'rejected',
            'decision' => 'REJECTED',
            'reasonCode' => $reasonCode,
            'message' => $message,
            'cmsReservationId' => $reservation->cms_reservation_id,
            'cmsPlacementRef' => $reservation->cms_placement_ref,
            'reservationStatus' => ChessWalletReservationStatusEnum::FAILED,
            'sourceOfTruth' => 'cms',
        ];

        $reservation->update([
            'status' => ChessWalletReservationStatusEnum::FAILED,
            'reject_reason' => $reasonCode,
            'reserve_response_payload' => $response,
        ]);

        $this->logWebhook('chess.reserve.rejected', [
            'cmsReservationId' => $reservation->cms_reservation_id,
            'reasonCode' => $reasonCode,
        ]);

        return [
            'httpStatus' => 409,
            'body' => $response,
        ];
    }

    private function reservationPartnerRejection(ChessWalletReservation $reservation, array $context): ?array
    {
        $partnerCode = trim((string) data_get($context, 'wallet_config.partner_code', ''));

        if ($partnerCode === '') {
            return $this->failureResponse(401,ChessWalletReasonCodeEnum::INVALID_REQUEST,'Signed partner context required.');
        }

        if (!hash_equals((string) $reservation->partner_code, $partnerCode)) {
            return $this->failureResponse(404, ChessWalletReasonCodeEnum::INVALID_REQUEST, 'Reservation not found.');
        }

        return null;
    }

    private function responseForStoredReservation(ChessWalletReservation $reservation): array
    {
        $payload = $reservation->reserve_response_payload;
        if (is_array($payload)) {
            return [
                'httpStatus' => $reservation->status === ChessWalletReservationStatusEnum::FAILED ? 409 : 200,
                'body' => $payload,
            ];
        }

        return [
            'httpStatus' => 200,
            'body' => $this->reservationLookup($reservation->cms_reservation_id)['body'],
        ];
    }

    private function duplicateIntentResponse(ChessWalletReservation $reservation): array
    {
        return [
            'httpStatus' => 409,
            'body' => [
                'status' => 'duplicate',
                'decision' => ChessWalletReasonCodeEnum::DUPLICATE_REQUEST,
                'reasonCode' => ChessWalletReasonCodeEnum::DUPLICATE_REQUEST,
                'message' => 'Placement intent has already been processed.',
                'cmsReservationId' => $reservation->cms_reservation_id,
                'cmsPlacementRef' => $reservation->cms_placement_ref,
                'reservationStatus' => $reservation->status,
                'sourceOfTruth' => 'cms',
            ],
        ];
    }

    private function releaseSuccessPayload(ChessWalletReservation $reservation, bool $duplicate = false): array
    {
        return [
            'status' => 'accepted',
            'decision' => 'RELEASED',
            'duplicate' => $duplicate,
            'cmsReservationId' => $reservation->cms_reservation_id,
            'cmsPlacementRef' => $reservation->cms_placement_ref,
            'reservationStatus' => ChessWalletReservationStatusEnum::RELEASED,
            'sourceOfTruth' => 'cms',
        ];
    }

    private function settlementSuccessPayload(ChessWalletReservation $reservation, bool $duplicate = false): array
    {
        return [
            'status' => 'accepted',
            'decision' => 'SETTLEMENT_RECORDED',
            'duplicate' => $duplicate,
            'cmsReservationId' => $reservation->cms_reservation_id,
            'reservationStatus' => $reservation->status,
            'resultStatus' => $reservation->result_status,
            'placedBetId' => $reservation->placed_bet_id,
            'recorded' => true,
            'sourceOfTruth' => 'cms',
        ];
    }

    private function failureResponse(int $httpStatus, string $reasonCode, string $message): array
    {
        return [
            'httpStatus' => $httpStatus,
            'body' => [
                'status' => 'rejected',
                'reasonCode' => $reasonCode,
                'message' => $message,
            ],
        ];
    }

    private function generateReservationId(): string
    {
        return 'chrsv_' . Str::lower(Str::uuid()->toString());
    }

    private function buildPlacementRef(string $partnerCode, string $placementIntentId): string
    {
        return sprintf('chess:%s:%s', $partnerCode, $placementIntentId);
    }

    private function ledgerTransactionId(string $event, string $reservationId): string
    {
        return sprintf('chess_%s_%s', $event, str_replace('-', '_', $reservationId));
    }

    private function nullableCarbon($value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $exception) {
            return null;
        }
    }

    private function normalizeResultStatus(string $resultStatus): ?string
    {
        $normalized = strtoupper($resultStatus);

        return match ($normalized) {
            ChessWalletResultStatusEnum::WIN => ChessWalletResultStatusEnum::WIN,
            ChessWalletResultStatusEnum::LOSS => ChessWalletResultStatusEnum::LOSS,
            ChessWalletResultStatusEnum::VOID,
            ChessWalletResultStatusEnum::CANCELLED => ChessWalletResultStatusEnum::VOID,
            default => null,
        };
    }

    private function settledReservationStatus(string $normalizedResult): string
    {
        return match ($normalizedResult) {
            ChessWalletResultStatusEnum::WIN => ChessWalletReservationStatusEnum::SETTLED_WIN,
            ChessWalletResultStatusEnum::LOSS => ChessWalletReservationStatusEnum::SETTLED_LOSS,
            default => ChessWalletReservationStatusEnum::SETTLED_VOID,
        };
    }

    private function refundReservation(ChessWalletReservation $reservation, array $walletUser, string $requestId, string $eventType): int
    {
        /** @var SpendableWalletContext $walletContext */
        $walletContext = $walletUser['wallet_context'];
        $account = $walletContext->ownerUser();
        $beforeBalance = $this->wallets->currentDecimalBalance($walletContext);
        $amount = $this->wallets->decimalForContext($walletContext, (string)$reservation->stake_amount);
                $afterBalance = (string) BigDecimal::of($beforeBalance)->plus($amount);

        $transaction = CmsUserTransaction::query()->create([
            'cms_user_id' => $walletContext->ownerWalletId(),
            'transactionId' => $this->ledgerTransactionId('void', $reservation->cms_reservation_id),
            'ref_transaction_id' => $reservation->reserveTransaction?->transactionId,
            'round_id' => $reservation->cms_reservation_id,
            'provider_transaction_id' => $requestId,
            'game_provider_id' => $reservation->provider_id,
            'action' => TransactionActionEnum::Rollback,
            'event_type' => $eventType,
            'status' => TransactionStatusEnum::Success,
            'amount' => $reservation->stake_amount,
            'currency' => $walletContext->currency(),
            'source_wallet_account_id' => $walletContext->walletAccountId(),
            'source_wallet_type' => $walletContext->walletType(),
            'source_currency' => $walletContext->currency(),
            'exact_amount' => $amount,
                    'exact_before_balance' => $beforeBalance,
                    'exact_after_balance' => $afterBalance,
                    'before_balance' => $beforeBalance,
            'after_balance' => $afterBalance,
            'acceptor' => 'api',
        ]);

        $this->wallets->assertNoDrift($walletContext, 'chess_settlement_void_before_credit', [
            'reservation_id' => $reservation->cms_reservation_id,
        ]);
        $this->wallets->projectDecimalBalance($walletContext, $afterBalance);
                $this->wallets->recordDecimalLedgerEntry($walletContext, 'chess_settlement_void', 'credit', $amount, $beforeBalance, $afterBalance, $transaction->transactionId, $transaction, [], 'chess_reservation', $reservation->cms_reservation_id);

        return $transaction->id;
    }

    private function creditPayout(ChessWalletReservation $reservation, array $walletUser, string $requestId, string $payoutAmount): int
    {
        /** @var SpendableWalletContext $walletContext */
        $walletContext = $walletUser['wallet_context'];
        $account = $walletContext->ownerUser();
        $beforeBalance = $this->wallets->currentDecimalBalance($walletContext);
        $payoutAmount = $this->wallets->decimalForContext($walletContext, $payoutAmount);
        $afterBalance = (string) BigDecimal::of($beforeBalance)->plus($payoutAmount);

        $transaction = CmsUserTransaction::query()->create([
            'cms_user_id' => $walletContext->ownerWalletId(),
            'transactionId' => $this->ledgerTransactionId('settlement', $reservation->cms_reservation_id),
            'ref_transaction_id' => $reservation->reserveTransaction?->transactionId,
            'round_id' => $reservation->cms_reservation_id,
            'provider_transaction_id' => $requestId,
            'game_provider_id' => $reservation->provider_id,
            'action' => TransactionActionEnum::Win,
            'event_type' => 'chess_settlement_win',
            'status' => TransactionStatusEnum::Success,
            'amount' => $payoutAmount,
            'currency' => $walletContext->currency(),
            'source_wallet_account_id' => $walletContext->walletAccountId(),
            'source_wallet_type' => $walletContext->walletType(),
            'source_currency' => $walletContext->currency(),
            'exact_amount' => $payoutAmount,
                    'exact_before_balance' => $beforeBalance,
                    'exact_after_balance' => $afterBalance,
                    'before_balance' => $beforeBalance,
            'after_balance' => $afterBalance,
            'acceptor' => 'api',
        ]);

        $this->wallets->assertNoDrift($walletContext, 'chess_settlement_win_before_credit', [
            'reservation_id' => $reservation->cms_reservation_id,
        ]);
        $this->wallets->projectDecimalBalance($walletContext, $afterBalance);
                $this->wallets->recordDecimalLedgerEntry($walletContext, 'chess_settlement_win', 'credit', $payoutAmount, $beforeBalance, $afterBalance, $transaction->transactionId, $transaction, [], 'chess_reservation', $reservation->cms_reservation_id);

        return $transaction->id;
    }

    private function holdState(ChessWalletReservation $reservation): string
    {
        return match ($reservation->status) {
            ChessWalletReservationStatusEnum::RESERVED => 'HELD',
            ChessWalletReservationStatusEnum::RELEASED,
            ChessWalletReservationStatusEnum::SETTLED_VOID => 'RELEASED',
            default => 'FINALIZED',
        };
    }

    private function acceptedFromStatus(string $reservationStatus): ?bool
    {
        return match ($reservationStatus) {
            ChessWalletReservationStatusEnum::FAILED => false,
            ChessWalletReservationStatusEnum::RESERVED,
            ChessWalletReservationStatusEnum::RELEASED,
            ChessWalletReservationStatusEnum::SETTLED_WIN,
            ChessWalletReservationStatusEnum::SETTLED_LOSS,
            ChessWalletReservationStatusEnum::SETTLED_VOID => true,
            default => null,
        };
    }

    private function logWebhook(string $message, array $payload): void
    {
        // Webhook evidence is not a request archive. Hash untrusted correlation
        // values and keep only fixed, bounded state; never persist payload text.
        $reason = (string) ($payload['reasonCode'] ?? '');
        $allowedReasons = [
            ChessWalletReasonCodeEnum::INSUFFICIENT_BALANCE,
            ChessWalletReasonCodeEnum::LIMIT_EXCEEDED,
            ChessWalletReasonCodeEnum::STALE_QUOTE,
            ChessWalletReasonCodeEnum::USER_NOT_ELIGIBLE,
            ChessWalletReasonCodeEnum::DUPLICATE_REQUEST,
            ChessWalletReasonCodeEnum::INVALID_REQUEST,
            ChessWalletReasonCodeEnum::UNSUPPORTED_ACTIVE_WALLET,
            ChessWalletReasonCodeEnum::UNKNOWN,
        ];
        $httpStatus = filter_var($payload['httpStatus'] ?? null, FILTER_VALIDATE_INT);
        $evidence = array_filter([
            'operation' => $message,
            'reason_code' => in_array($reason, $allowedReasons, true) ? $reason : null,
            'http_status' => $httpStatus !== false && $httpStatus >= 400 && $httpStatus <= 599 ? $httpStatus : null,
            'request_id_sha256' => $this->webhookReferenceHash($payload['requestId'] ?? null),
            'reservation_id_sha256' => $this->webhookReferenceHash($payload['cmsReservationId'] ?? null),
            'provider_code_sha256' => $this->webhookReferenceHash($payload['partnerCode'] ?? null),
        ], static fn ($value) => $value !== null);

        DB::table('cms_webhook_logs')->insert([
            'request' => json_encode($evidence, JSON_THROW_ON_ERROR),
            'message' => $message,
            'reqdate' => now(),
        ]);
    }

    private function webhookReferenceHash(mixed $reference): ?string
    {
        if (!is_string($reference) && !is_int($reference)) {
            return null;
        }

        $reference = trim((string) $reference);

        return $reference === '' ? null : hash('sha256', $reference);
    }

    private function conflict(): array
    {
        return $this->failureResponse(409,ChessWalletReasonCodeEnum::DUPLICATE_REQUEST,'Request conflicts with the stored financial intent.');
    }
    private function payloadHash(array $payload): string
    {
        $sort=function(array $row) use (&$sort): array { foreach($row as &$v) if(is_array($v)) $v=$sort($v); unset($v); if(!array_is_list($row))ksort($row); return $row; };
        return hash('sha256',json_encode($sort($payload),JSON_THROW_ON_ERROR|JSON_PRESERVE_ZERO_FRACTION));
    }
    private function intentHash(array $payload): string
    {
        $fields=['externalUserId','placementIntentId','amount','currency','marketInstanceId','quoteRef','quoteExpiresAt','selectionId','matchId','oddsDecimal','walletAccountId','walletCurrency','walletContextRef',ChessRecurringBoardResolver::PROVENANCE_KEY];
        $intent=array_intersect_key($payload,array_flip($fields));
        if(isset($intent['amount'])) $intent['amount']=(string)BigDecimal::of((string)$intent['amount'])->stripTrailingZeros();
        if(isset($intent['currency']))$intent['currency']=strtoupper($intent['currency']);
        return $this->payloadHash($intent);
    }
    private function sameIntent(ChessWalletReservation $row,array $payload): bool
    {
        return hash_equals((string)data_get($row->request_payload,'_intent_sha256',''),$this->intentHash($payload));
    }
    private function reserveReplay(ChessWalletReservation $row,array $payload): array
    {
        return $this->sameIntent($row,$payload) ? $this->responseForStoredReservation($row) : $this->conflict();
    }
    private function claimRejection(ChessWalletReservation $row,array $payload): ?array
    {
        $rootId=$row->user?->parent_id ?: $row->cms_user_id;
        if(isset($payload['walletContextRef']) && $payload['walletContextRef']!==sprintf('cms-user:%s:wallet:%s',$rootId,strtolower($row->currency))) return $this->conflict();
        foreach(['externalUserId'=>$row->external_user_id,'currency'=>$row->currency,'walletCurrency'=>$row->currency,'walletAccountId'=>data_get($row->request_payload,'wallet_account_id')] as $key=>$expected) {
            if(isset($payload[$key]) && (string)$payload[$key] !== (string)$expected) return $this->conflict();
        }
        return null;
    }
}
