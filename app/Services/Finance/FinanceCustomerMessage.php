<?php

namespace App\Services\Finance;

/**
 * Presentation only: no provider prose, exceptions, persistence or financial decisions.
 * Callers retain their existing status/reason codes and response contracts.
 */
final class FinanceCustomerMessage
{
    private const KEYS = [
        'ambiguous_source' => 'finance.customer.ambiguous_source',
        'unsupported_crypto_network' => 'finance.customer.unsupported_crypto_network',
        'invalid_amount' => 'finance.customer.invalid_amount',
        'unsupported_wallet_type' => 'finance.customer.unsupported_wallet_type',
        'unsupported_payout_currency' => 'finance.customer.unsupported_payout_currency',
        'conversion_required_manual_review' => 'finance.customer.conversion_required_manual_review',
        'wallet_not_found' => 'finance.customer.wallet_not_found',
        'wallet_selection_invalid' => 'finance.customer.wallet_selection_invalid',
        'wallet_unavailable' => 'finance.customer.wallet_unavailable',
        'balance_opened' => 'finance.customer.balance_opened',
        'wallet_opened' => 'finance.customer.wallet_opened',
        'wallet_current' => 'finance.customer.wallet_current',
        'wallet_updated' => 'finance.customer.wallet_updated',
        'wallet_change_blocked' => 'finance.customer.wallet_change_blocked',
        'pending_finance' => 'finance.customer.pending_finance',
        'open_internal_game_round' => 'finance.customer.open_internal_game_round',
        'open_sportsbook_bet' => 'finance.customer.open_sportsbook_bet',
        'open_chess_reservation' => 'finance.customer.open_chess_reservation',
        'wallet_pending_balance' => 'finance.customer.wallet_pending_balance',
        'pending_conversion' => 'finance.customer.pending_conversion',
        'pending_transfer' => 'finance.customer.pending_transfer',
        'payment_unavailable' => 'finance.customer.payment_unavailable',
        'CRYPTO_SETTLEMENT_DEFERRED' => 'finance.customer.CRYPTO_SETTLEMENT_DEFERRED',
        'deposit_below_minimum' => 'finance.customer.deposit_below_minimum',
        'deposit_above_maximum' => 'finance.customer.deposit_above_maximum',
        'certification_deposit_created' => 'finance.customer.certification_deposit_created',
        'certification_withdrawal_created' => 'finance.customer.certification_withdrawal_created',
        'request_unavailable' => 'finance.customer.request_unavailable',
        'fiat_wallet_required' => 'finance.customer.fiat_wallet_required',
        'unsupported_deposit_wallet' => 'finance.customer.unsupported_deposit_wallet',
        'rate_unavailable' => 'finance.customer.rate_unavailable',
        'realtime_suppressed' => 'finance.customer.realtime_suppressed',
        'crypto_pending_amount_conflict' => 'finance.customer.crypto_pending_amount_conflict',
        'crypto_session_rejected' => 'finance.customer.crypto_session_rejected',
        'finance_canary_limit_exceeded' => 'finance.customer.finance_canary_limit_exceeded',
        'conversion_invalid' => 'finance.customer.conversion_invalid',
        'conversion_conflict' => 'finance.customer.conversion_conflict',
        'conversion_unavailable' => 'finance.customer.conversion_unavailable',
        'conversion_completed' => 'finance.customer.conversion_completed',
        'transfer_unavailable' => 'finance.customer.transfer_unavailable',
        'crypto_manual_review_title' => 'finance.customer.crypto_manual_review_title',
        'crypto_manual_review_message' => 'finance.customer.crypto_manual_review_message',
        'crypto_awaiting_payment_title' => 'finance.customer.crypto_awaiting_payment_title',
        'crypto_awaiting_payment_message' => 'finance.customer.crypto_awaiting_payment_message',
        'crypto_confirming_title' => 'finance.customer.crypto_confirming_title',
        'crypto_confirming_message' => 'finance.customer.crypto_confirming_message',
        'crypto_credited_title' => 'finance.customer.crypto_credited_title',
        'crypto_credited_message' => 'finance.customer.crypto_credited_message',
        'crypto_completed_title' => 'finance.customer.crypto_completed_title',
        'crypto_completed_message' => 'finance.customer.crypto_completed_message',
        'crypto_expired_title' => 'finance.customer.crypto_expired_title',
        'crypto_expired_message' => 'finance.customer.crypto_expired_message',
        'crypto_failed_title' => 'finance.customer.crypto_failed_title',
        'crypto_failed_message' => 'finance.customer.crypto_failed_message',
        'crypto_pending_title' => 'finance.customer.crypto_pending_title',
        'crypto_pending_message' => 'finance.customer.crypto_pending_message',
        'crypto_expired_renewable_message' => 'finance.customer.crypto_expired_renewable_message',
        'insufficient_source_balance' => 'cms_alerts.low_balance',
        'open_poker_table' => 'poker_exit.required',
        'crypto_deposit_below_minimum' => 'finance.customer.deposit_below_minimum',
    ];

    public static function forCode(?string $code, array $replace = []): string
    {
        return trans(self::KEYS[$code ?? ''] ?? 'cms_alerts.general_error', $replace);
    }

    public static function gatewayResponse(array $response): array
    {
        // A provider message is never an input to translation selection.
        $response['message'] = ($response['status'] ?? 500) === 200
            ? trans('cms_alerts.payment_link_created')
            : self::forCode('payment_unavailable');

        return $response;
    }

    public static function walletReasons(array $reasons): array
    {
        return array_map(static function (array $reason): array {
            $reason['message'] = self::forCode($reason['code'] ?? null);
            return $reason;
        }, $reasons);
    }

    public static function session(array $state): array
    {
        // Project diagnostic codes only, AFTER synchronization. Never rewrite financial input.
        $knownReasons = [
            'UNDER_MINIMUM_MANUAL_REVIEW', 'MANUAL_APPROVAL', 'UNDERPAID', 'OVERPAID',
            'WRONG_ASSET', 'WRONG_NETWORK', 'LATE_PAYMENT',
            'crypto_late_payment_review', 'crypto_local_cancelled_payment_review',
        ];
        foreach (['status_reason', 'manual_review_reason'] as $key) {
            if (array_key_exists($key, $state) && !in_array($state[$key], $knownReasons, true)) {
                $state[$key] = null;
            }
        }

        return $state;
    }
}
