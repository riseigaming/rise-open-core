<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Chess\ChessIntegrationReadiness;
use App\Services\ChessWallet\ChessWalletReservationService;
use Illuminate\Http\Request;

class ChessWalletController extends Controller
{
    public function __construct(
        private ChessWalletReservationService $reservationService,
        private ChessIntegrationReadiness $readiness
    )
    {
    }

    public function reserve(Request $request)
    {
        if ($response = $this->unavailableResponse($request)) {
            return $response;
        }

        $result = $this->reservationService->reserve(
            $request->all(),
            (array) $request->attributes->get('chess_wallet_context', []),
            (string) $request->attributes->get('chess_wallet_request_id', ''),
            (string) $request->attributes->get('chess_wallet_idempotency_key', '')
        );

        return response()->json($result['body'], $result['httpStatus']);
    }

    public function release(Request $request)
    {
        if ($response = $this->unavailableResponse($request)) {
            return $response;
        }

        $result = $this->reservationService->release(
            $request->all(),
            (array) $request->attributes->get('chess_wallet_context', []),
            (string) $request->attributes->get('chess_wallet_request_id', '')
        );

        return response()->json($result['body'], $result['httpStatus']);
    }

    public function confirm(Request $request)
    {
        if ($response = $this->unavailableResponse($request)) {
            return $response;
        }

        $result = $this->reservationService->confirmReservation(
            $request->all(),
            (array) $request->attributes->get('chess_wallet_context', []),
            (string) $request->attributes->get('chess_wallet_request_id', '')
        );

        return response()->json($result['body'], $result['httpStatus']);
    }

    public function settlementHandoff(Request $request)
    {
        if ($response = $this->unavailableResponse($request)) {
            return $response;
        }

        $result = $this->reservationService->handoffSettlement(
            $request->all(),
            (string) $request->attributes->get('chess_wallet_request_id', ''),
            (array) $request->attributes->get('chess_wallet_context', [])
        );

        return response()->json($result['body'], $result['httpStatus']);
    }

    public function balance(Request $request)
    {
        if ($response = $this->unavailableResponse($request)) {
            return $response;
        }

        $result = $this->reservationService->balance(
            $request->query(),
            (array) $request->attributes->get('chess_wallet_context', [])
        );

        return response()->json($result['body'], $result['httpStatus']);
    }

    public function health(Request $request)
    {
        if ($response = $this->unavailableResponse($request)) {
            return $response;
        }

        $result = $this->reservationService->health(
            (array) $request->attributes->get('chess_wallet_context', [])
        );

        return response()->json($result['body'], $result['httpStatus']);
    }

    public function show(Request $request, string $cmsReservationId)
    {
        if ($response = $this->unavailableResponse($request)) {
            return $response;
        }

        $result = $this->reservationService->reservationLookup(
            $cmsReservationId,
            (array) $request->attributes->get('chess_wallet_context', [])
        );

        return response()->json($result['body'], $result['httpStatus']);
    }

    private function unavailableResponse(Request $request)
    {
        $partnerCode = (string) $request->attributes->get('chess_wallet_partner_code', '');
        $walletState = $this->readiness->walletState($partnerCode !== '' ? $partnerCode : null);

        if ($walletState['ready']) {
            return null;
        }

        return response()->json([
            'status' => 'unavailable',
            'reasonCode' => 'CHESS_WALLET_NOT_READY',
            'message' => $walletState['message'] ?: 'Chess wallet is not ready.',
        ], 503);
    }
}
