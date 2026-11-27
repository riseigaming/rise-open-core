<?php

namespace App\Services\Finance;

use App\Enum\TransactionActionEnum;
use App\Models\CmsUser;
use App\Models\CmsUserTransaction;
use App\Models\FinanceManualOverride;
use App\Models\WalletAccount;
use App\Models\WalletLedgerEntry;
use App\Services\CmsStaff\CmsStaffPermissionService;
use App\Services\Wallet\AssetWalletService;
use App\Services\Wallet\SpendableWalletContext;
use App\Services\Wallet\SpendableWalletService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\Schema;

class ManualFinanceOverrideService
{
    public const ECONOMIC_ACTIONS = [
        'none',
        'refund_original_debit',
        'manual_credit',
        'manual_debit',
        'external_only',
    ];

    public const EXTERNAL_RESOLUTIONS = [
        'provider_confirmed_failed',
        'provider_confirmed_cancelled',
        'provider_confirmed_completed',
        'handled_externally',
        'manual_operator_decision',
        'unknown_unresolved',
    ];

    public const OPERATIONAL_STATUSES = [0, 1, 2, 3, 4, 5];

    public function __construct(
        private readonly CmsStaffPermissionService $permissions,
        private readonly AssetWalletService $assetWallets,
        private readonly SpendableWalletService $spendableWallets,
    ) {
    }

    public function execute(array $input, CmsUser $actor): array
    {
        if (!$this->permissions->has($actor, CmsStaffPermissionService::FINANCE_MANUAL_OVERRIDE)) {
            throw new \DomainException('Manual finance override permission is required.');
        }

        if (!Schema::hasTable('finance_manual_overrides') || !$this->assetWallets->tablesAvailable()) {
            throw new \DomainException('Manual finance override storage is unavailable.');
        }

        $intent = $this->normalizeIntent($input);
        $fingerprint = hash('sha256', json_encode($intent, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $intent['settlement_evidence'] ??= null;
        $existing = FinanceManualOverride::query()->where('operation_id', $intent['operation_id'])->first();

        if ($existing instanceof FinanceManualOverride) {
            return $this->replayResult($existing, $fingerprint, $intent);
        }

        try {
            $transaction = $intent['transaction_id'] !== null ? CmsUserTransaction::find($intent['transaction_id']) : null;
            if ($transaction && $this->isCryptoDeposit($transaction)) {
                return app(\App\Services\CryptoControl\CryptoSettlementMaintenanceGate::class)->run(
                    'cms.admin_reconciliation', $intent['operation_id'],
                    fn () => DB::transaction(fn () => $this->executeNew($intent, $fingerprint, $actor), 3)
                );
            }
            return DB::transaction(fn () => $this->executeNew($intent, $fingerprint, $actor), 3);
        } catch (QueryException $exception) {
            if (!$this->isUniqueOperationCollision($exception)) {
                throw $exception;
            }

            $existing = FinanceManualOverride::query()->where('operation_id', $intent['operation_id'])->first();
            if (!$existing instanceof FinanceManualOverride) {
                throw $exception;
            }

            return $this->replayResult($existing, $fingerprint, $intent);
        }
    }

    private function executeNew(array $intent, string $fingerprint, CmsUser $actor): array
    {
        $audit = FinanceManualOverride::query()->create([
            'operation_id' => $intent['operation_id'],
            'actor_admin_id' => $actor->id,
            'cms_user_id' => $intent['customer_id'],
            'cms_user_transaction_id' => $intent['transaction_id'],
            'external_resolution' => $intent['external_resolution'],
            'economic_action' => $intent['economic_action'],
            'reason' => $intent['reason'],
            'evidence_reference' => $intent['evidence_reference'],
            'manual_override' => true,
            'request_fingerprint' => $fingerprint,
            'meta' => ['state' => 'reserved', 'manual_override' => true],
        ]);

        $customer = CmsUser::query()->with('parentAccount')->whereKey($intent['customer_id'])->lockForUpdate()->first();
        if (!$customer instanceof CmsUser || (string) $customer->role !== 'customer') {
            throw new \DomainException('Target customer is unavailable.');
        }

        $transaction = null;
        $beforeStatus = null;
        if ($intent['transaction_id'] !== null) {
            $transaction = CmsUserTransaction::query()->with('user.parentAccount')
                ->whereKey($intent['transaction_id'])->lockForUpdate()->first();
            if (!$transaction instanceof CmsUserTransaction) {
                throw new \DomainException('Finance transaction is unavailable.');
            }
            if (!$transaction->user instanceof CmsUser
                || (int) $this->assetWallets->resolveOwnerUser($transaction->user)->id
                    !== (int) $this->assetWallets->resolveOwnerUser($customer)->id) {
                throw new \DomainException('Transaction and customer do not match.');
            }
            $beforeStatus = (int) $transaction->status;
            if ($this->isCryptoDeposit($transaction)) {
                app(AdminCryptoReconciliationGuard::class)->assertReconciled($transaction, $intent);
            } elseif ($intent['settlement_evidence'] !== null) {
                throw new \DomainException('Settlement evidence is only valid for crypto deposit reconciliation.');
            }
        }

        [$wallet, $context] = $this->resolveWalletContext($intent, $customer, $transaction);
        $amount = $this->resolveAmount($intent, $transaction, $context);
        $balanceBefore = $context instanceof SpendableWalletContext
            ? $this->spendableWallets->currentDecimalBalance($context)
            : null;
        $balanceAfter = $balanceBefore;

        if (in_array($intent['economic_action'], ['manual_credit', 'manual_debit'], true)) {
            $idempotencyKey = 'finance_manual_override:' . $intent['operation_id'];
            if (WalletLedgerEntry::query()
                ->where('wallet_account_id', $context->walletAccountId())
                ->where('idempotency_key', $idempotencyKey)
                ->exists()) {
                throw new \DomainException('A ledger effect already exists for this operation ID.');
            }

            $this->spendableWallets->assertNoDrift($context, 'finance_manual_override', [
                'operation_id' => $intent['operation_id'],
            ]);
            $balanceAfter = $intent['economic_action'] === 'manual_credit'
                ? (string) BigDecimal::of($balanceBefore)->plus($amount)
                : (string) BigDecimal::of($balanceBefore)->minus($amount);

            if (BigDecimal::of($balanceAfter)->isNegative()) {
                throw new \DomainException('Manual debit cannot create a negative available balance.');
            }

            $this->spendableWallets->projectDecimalBalance($context, $balanceAfter);
            $this->spendableWallets->recordDecimalLedgerEntry(
                $context,
                $intent['economic_action'] === 'manual_credit' ? 'finance_manual_credit' : 'finance_manual_debit',
                $intent['economic_action'] === 'manual_credit' ? 'credit' : 'debit',
                $amount,
                $balanceBefore,
                $balanceAfter,
                $idempotencyKey,
                null,
                $this->ledgerMeta($intent, $actor, $transaction, $audit),
                'finance_manual_override',
                $intent['operation_id'],
            );
        } elseif ($intent['economic_action'] === 'refund_original_debit') {
            $existingRefund = WalletLedgerEntry::query()
                ->where('source_type', 'cms_user_transaction')
                ->where('source_reference', (string) $transaction->transactionId)
                ->where('entry_type', 'payout_request_released')
                ->where('direction', 'credit')
                ->where('status', 'applied')
                ->lockForUpdate()
                ->first();
            if ($existingRefund instanceof WalletLedgerEntry) {
                throw new \DomainException('The original debit has already been refunded.');
            }

            $balanceAfter = app(PayoutRoutingService::class)->refundToSource(
                $transaction,
                $amount,
                'manual_admin_override',
                $this->ledgerMeta($intent, $actor, $transaction, $audit),
            );
        }

        if ($transaction instanceof CmsUserTransaction) {
            $transaction->forceFill(['status' => $intent['new_status']])->save();
        }

        $finalMeta = [
            'manual_override' => true,
            'state' => 'applied',
            'request_fingerprint' => $fingerprint,
            'provider_boundary_crossed' => $transaction instanceof CmsUserTransaction && (int) $beforeStatus === 5,
            'transaction_action' => $transaction?->action,
            'provider_reference_present' => $transaction instanceof CmsUserTransaction
                && trim((string) $transaction->provider_transaction_id) !== '',
        ];

        DB::table('finance_manual_overrides')->where('id', $audit->id)->update([
            'transaction_reference' => $transaction?->transactionId,
            'before_status' => $beforeStatus,
            'after_status' => $transaction instanceof CmsUserTransaction ? $intent['new_status'] : null,
            'wallet_account_id' => $wallet?->id,
            'wallet_type' => $wallet?->wallet_type,
            'asset' => $wallet?->asset,
            'amount' => BigDecimal::of($amount)->isPositive() ? $amount : null,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'meta' => json_encode($finalMeta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);

        return [
            'audit' => FinanceManualOverride::query()->findOrFail($audit->id),
            'idempotent_replay' => false,
        ];
    }

    private function resolveWalletContext(
        array $intent,
        CmsUser $customer,
        ?CmsUserTransaction $transaction
    ): array {
        if (in_array($intent['economic_action'], ['none', 'external_only'], true)) {
            return [null, null];
        }

        if (!$intent['wallet_account_id']) {
            throw new \DomainException('An explicit wallet is required for an economic action.');
        }

        $wallet = $this->assetWallets->findWalletForOwner($customer, $intent['wallet_account_id'], true);
        if (!$wallet instanceof WalletAccount || (string) $wallet->status !== 'active') {
            throw new \DomainException('The selected wallet is unavailable for this customer.');
        }
        if (strtoupper((string) $wallet->asset) !== $intent['currency']) {
            throw new \DomainException('The selected wallet currency does not match the requested currency.');
        }

        if ($intent['economic_action'] === 'refund_original_debit') {
            if (!$transaction instanceof CmsUserTransaction
                || (int) $transaction->action !== TransactionActionEnum::Withdraw
                || (int) $transaction->source_wallet_account_id !== (int) $wallet->id) {
                throw new \DomainException('Refund original debit requires the transaction source wallet.');
            }
        }

        $context = $this->spendableWallets->resolveForWallet(
            $customer,
            $wallet,
            true,
            (int) ($transaction?->cms_user_id ?: $customer->id),
        );
        if (!$context instanceof SpendableWalletContext) {
            throw new \DomainException('Canonical wallet context could not be resolved.');
        }

        return [$wallet, $context];
    }

    private function resolveAmount(
        array $intent,
        ?CmsUserTransaction $transaction,
        ?SpendableWalletContext $context
    ): string {
        if (in_array($intent['economic_action'], ['none', 'external_only'], true)) {
            return '0';
        }

        if (!$context instanceof SpendableWalletContext) {
            throw new \DomainException('A canonical wallet is required for an economic action.');
        }

        $canonicalAmount = $this->validatedDecimalForContext($intent['amount'], $context, 'Economic action amount');
        $amount = $canonicalAmount;
        if (!BigDecimal::of($amount)->isPositive()) {
            throw new \DomainException('Economic action amount must be greater than zero.');
        }

        if ($intent['economic_action'] === 'refund_original_debit') {
            $expected = $this->validatedDecimalForContext((string) $transaction->amount, $context, 'Original debit amount');
            if (!hash_equals($expected, $canonicalAmount)) {
                throw new \DomainException('Refund amount must exactly match the original debit.');
            }
        }

        return $amount;
    }

    private function normalizeIntent(array $input): array
    {
        $economicAction = $this->textInput($input['economic_action'] ?? null, 'Economic action');
        $externalResolution = $this->textInput($input['external_resolution'] ?? null, 'External resolution');
        $operationId = $this->textInput($input['operation_id'] ?? null, 'Operation ID');
        $reason = $this->textInput($input['reason'] ?? null, 'Reason');
        $evidence = $this->textInput($input['evidence_reference'] ?? null, 'Evidence reference');
        $transactionId = $this->integerInput($input['transaction_id'] ?? null, 'Transaction ID', true);
        $newStatus = $this->integerInput($input['new_status'] ?? null, 'Operational status', true, 0);
        $customerId = $this->integerInput($input['customer_id'] ?? null, 'Customer ID');
        $walletId = $this->integerInput($input['wallet_account_id'] ?? null, 'Wallet ID', true);
        $settlementEvidence = app(AdminCryptoReconciliationGuard::class)->normalizeEvidence($input['settlement_evidence'] ?? null);

        if ($operationId === '' || mb_strlen($operationId) > 120) {
            throw new \DomainException('A valid unique operation ID is required.');
        }
        if (preg_match('/[\x00-\x1f\x7f]/', $operationId)
            || (in_array($economicAction, ['manual_credit', 'manual_debit'], true)
                && strlen('finance_manual_override:' . $operationId) > 120)) {
            throw new \DomainException('Operation ID cannot contain control characters or exceed ledger key storage.');
        }
        if (!in_array($economicAction, self::ECONOMIC_ACTIONS, true)) {
            throw new \DomainException('Unsupported economic action.');
        }
        if (!in_array($externalResolution, self::EXTERNAL_RESOLUTIONS, true)) {
            throw new \DomainException('Unsupported external resolution classification.');
        }
        if ($transactionId !== null && !in_array($newStatus, self::OPERATIONAL_STATUSES, true)) {
            throw new \DomainException('A legitimate operational status is required for a transaction override.');
        }
        if ($transactionId === null && $newStatus !== null) {
            throw new \DomainException('Operational status requires a transaction.');
        }
        if (in_array($economicAction, ['refund_original_debit', 'external_only'], true) && $transactionId === null) {
            throw new \DomainException('This economic action requires a transaction.');
        }
        if ($transactionId === null && !in_array($economicAction, ['manual_credit', 'manual_debit'], true)) {
            throw new \DomainException('Standalone overrides must be an explicit manual credit or debit.');
        }
        if ($reason === '' || mb_strlen($reason) > 500) {
            throw new \DomainException('Reason is required and must be 500 characters or fewer.');
        }
        if (mb_strlen($evidence) > 500) {
            throw new \DomainException('Evidence reference must be 500 characters or fewer.');
        }
        if ((str_starts_with($externalResolution, 'provider_confirmed_') || $economicAction === 'refund_original_debit')
            && $evidence === '') {
            throw new \DomainException('An operator-provided evidence reference is required for provider reconciliation or refund.');
        }
        if ($settlementEvidence !== null && $transactionId === null) {
            throw new \DomainException('Settlement evidence requires a transaction.');
        }
        if ($economicAction === 'refund_original_debit'
            && ($externalResolution === 'provider_confirmed_completed' || $externalResolution === 'unknown_unresolved')) {
            throw new \DomainException('Completed or unresolved provider payments cannot authorize a refund.');
        }

        $rawAmount = $input['amount'] ?? null;
        $amount = in_array($economicAction, ['none', 'external_only'], true)
            && ($rawAmount === null || (is_string($rawAmount) && trim($rawAmount) === ''))
            ? '0'
            : $this->normalizeDecimalInput($rawAmount);
        if (in_array($economicAction, ['none', 'external_only'], true) && !$this->isZeroDecimal($amount)) {
            throw new \DomainException('No-wallet actions cannot include an amount.');
        }

        return [
            'operation_id' => $operationId,
            'customer_id' => $customerId,
            'transaction_id' => $transactionId,
            'new_status' => $newStatus,
            'external_resolution' => $externalResolution,
            'economic_action' => $economicAction,
            'wallet_account_id' => $walletId,
            'currency' => strtoupper($this->textInput($input['currency'] ?? null, 'Currency')),
            'amount' => $amount,
            'reason' => $reason,
            'evidence_reference' => $evidence !== '' ? $evidence : null,
            // Omit absent evidence to retain fingerprints of existing operations.
            ...($settlementEvidence !== null ? ['settlement_evidence' => $settlementEvidence] : []),
        ];
    }

    private function isCryptoDeposit(CmsUserTransaction $transaction): bool
    {
        return (int) $transaction->action === 1 && ($transaction->partner?->partner_type === 'crypto'
            || $transaction->crypto_deposit_context !== null
            || (Schema::hasTable('crypto_settlement_claims')
                && DB::table('crypto_settlement_claims')->where('cms_transaction_id', $transaction->id)->exists())
            || WalletLedgerEntry::where('source_reference', $transaction->transactionId)->where('entry_type', 'crypto_deposit_credited')->exists());
    }

    private function textInput(mixed $value, string $label): string
    {
        if ($value === null) return '';
        if (!is_string($value) || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $value)) {
            throw new \DomainException($label . ' must be text without control characters.');
        }
        return trim($value);
    }

    private function integerInput(mixed $value, string $label, bool $optional = false, int $minimum = 1): ?int
    {
        if ($optional && ($value === null || $value === '')) return null;
        if ((!is_int($value) && (!is_string($value) || preg_match('/\A(?:0|[1-9]\d*)\z/', $value) !== 1))
            || filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < $minimum) {
            throw new \DomainException($label . ' must be a valid integer.');
        }
        return (int) $value;
    }

    private function ledgerMeta(
        array $intent,
        CmsUser $actor,
        ?CmsUserTransaction $transaction,
        FinanceManualOverride $audit
    ): array {
        return [
            'manual_override' => true,
            'label' => match ($intent['economic_action']) {
                'manual_credit' => 'Manual credit',
                'manual_debit' => 'Manual debit',
                'refund_original_debit' => 'Manual refund',
                default => 'Manual admin override',
            },
            'finance_manual_override_id' => (int) $audit->id,
            'operation_id' => $intent['operation_id'],
            'actor_admin_id' => (int) $actor->id,
            'target_customer_id' => $intent['customer_id'],
            'cms_user_transaction_id' => $transaction?->id,
            'external_resolution' => $intent['external_resolution'],
            'economic_action' => $intent['economic_action'],
            'reason' => $intent['reason'],
            'evidence_reference' => $intent['evidence_reference'],
        ];
    }

    /**
     * Request amounts must remain plain decimal strings until the target wallet
     * establishes their scale. This prevents silent monetary coercion.
     */
    private function normalizeDecimalInput(mixed $value): string
    {
        if (is_int($value)) {
            $value = (string) $value;
        }
        if (!is_string($value)) {
            throw new \DomainException('Amount must be a decimal string.');
        }

        $value = trim($value);
        if ($value === '' || strlen($value) > 64 || !preg_match('/\A\d+(?:\.\d+)?\z/', $value)) {
            throw new \DomainException('Amount must use plain decimal notation.');
        }

        return $value;
    }

    private function validatedDecimalForContext(string $amount, SpendableWalletContext $context, string $label): string
    {
        [$integer, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        $integer = ltrim($integer, '0');
        $integer = $integer === '' ? '0' : $integer;

        // DECIMAL(24, 8) pads stored values with zeros. Ignore only trailing
        // zeros before checking the wallet's precision; nonzero extra digits
        // still reject rather than silently round the original debit.
        $fraction = rtrim($fraction, '0');
        if (strlen($integer) > 16 || strlen($fraction) > $context->precision()) {
            throw new \DomainException($label . ' does not match the selected wallet precision.');
        }
        $canonical = $fraction === '' ? $integer : $integer . '.' . $fraction;
        if ($this->isZeroDecimal($canonical)) {
            throw new \DomainException('Economic action amount must be greater than zero.');
        }

        return $canonical;
    }

    private function isZeroDecimal(string $amount): bool
    {
        return preg_match('/^0+(?:\.0+)?$/', $amount) === 1;
    }

    private function replayResult(FinanceManualOverride $existing, string $fingerprint, array $intent): array
    {
        if (!hash_equals((string) $existing->request_fingerprint, $fingerprint)) {
            throw new \DomainException('Operation ID was already used for a different manual finance request.');
        }

        if (data_get($existing->meta, 'state') !== 'applied') {
            throw new \DomainException('The recorded operation is incomplete; reconciliation is required.');
        }
        $transaction = $existing->cms_user_transaction_id ? CmsUserTransaction::find($existing->cms_user_transaction_id) : null;
        if (($transaction && $this->isCryptoDeposit($transaction)) || $intent['settlement_evidence'] !== null) {
            if (!$transaction || !$this->isCryptoDeposit($transaction)) {
                throw new \DomainException('The recorded crypto transaction is unavailable; reconciliation is required.');
            }
            app(\App\Services\CryptoControl\CryptoSettlementMaintenanceGate::class)->run(
                'cms.admin_reconciliation', $existing->operation_id,
                function () use ($transaction, $intent): void {
                    $locked = CmsUserTransaction::whereKey($transaction->id)->lockForUpdate()->firstOrFail();
                    app(AdminCryptoReconciliationGuard::class)->assertReconciled($locked, $intent);
                }
            );
        }
        if (in_array($existing->economic_action, ['manual_credit', 'manual_debit', 'refund_original_debit'], true)) {
            $refund = $existing->economic_action === 'refund_original_debit';
            $entries = WalletLedgerEntry::query()->where('wallet_account_id', $existing->wallet_account_id)
                ->where('source_type', $refund ? 'cms_user_transaction' : 'finance_manual_override')
                ->where('source_reference', $refund ? $existing->transaction_reference : $existing->operation_id)->get();
            $entries = $entries->filter(fn ($entry) => $refund ? $entry->entry_type === 'payout_request_released'
                : $entry->idempotency_key === 'finance_manual_override:' . $existing->operation_id);
            $entry = $entries->count() === 1 ? $entries->first() : null;
            $direction = $existing->economic_action === 'manual_debit' ? 'debit' : 'credit';
            $wallet = WalletAccount::find($existing->wallet_account_id);
            $customer = CmsUser::with('parentAccount')->find($existing->cms_user_id);
            // A foreign-currency primary wallet belongs to the root account,
            // while its authoritative ledger owner can be the currency child.
            // Reconstruct the pinned context from the original transaction/audit,
            // never from the current selected wallet or mutable ledger metadata.
            $expectedContext = $wallet instanceof WalletAccount && $customer instanceof CmsUser
                ? $this->spendableWallets->resolveForWallet(
                    $customer, $wallet, false, (int) ($transaction?->cms_user_id ?: $customer->id)
                )
                : null;
            if (!$entry || !($expectedContext instanceof SpendableWalletContext)
                || $entry->status !== 'applied' || $entry->direction !== $direction
                || $entry->entry_type !== ($refund ? 'payout_request_released' : 'finance_' . $existing->economic_action)
                || $entry->asset !== $existing->asset
                || !$wallet || !$customer || (int) $wallet->cms_user_id !== (int) $this->assetWallets->resolveOwnerUser($customer)->id
                || (int) $entry->cms_user_id !== $expectedContext->ownerWalletId() || $wallet->asset !== $existing->asset
                || $wallet->wallet_type !== $existing->wallet_type
                || $entry->idempotency_key !== ($refund ? 'payout_request_released:' . $existing->transaction_reference : 'finance_manual_override:' . $existing->operation_id)
                || (int) data_get($entry->meta, 'finance_manual_override_id') !== (int) $existing->id
                || !BigDecimal::of($entry->amount)->isEqualTo($existing->amount)
                || !BigDecimal::of($entry->balance_before)->isEqualTo($existing->balance_before)
                || !BigDecimal::of($entry->balance_after)->isEqualTo($existing->balance_after)) {
                throw new \DomainException('The recorded ledger effect is missing or conflicting; reconciliation is required.');
            }
        }

        return ['audit' => $existing, 'idempotent_replay' => true];
    }

    private function isUniqueOperationCollision(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());

        return in_array($sqlState, ['23000', '23505'], true)
            && str_contains(strtolower($exception->getMessage()), 'operation');
    }
}
