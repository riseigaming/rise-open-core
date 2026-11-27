<?php

namespace App\Services\Finance;

use App\Enum\TransactionActionEnum;
use App\Enum\TransactionStatusEnum;
use App\Models\CmsUserTransaction;
use App\Services\Payments\NovaDepositService;
use App\Services\Payments\NovaGatewayClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class PrelaunchFinanceWriteGate
{
    public function available(): bool
    {
        return Schema::hasTable('finance_reset_controls')
            && Schema::hasColumns('finance_reset_controls', [
                'id', 'writes_closed', 'version', 'declaration_reference',
                'applied_plan_sha256', 'isolation_proof_sha256',
                'closed_at', 'opened_at', 'created_at', 'updated_at',
            ]);
    }

    public function closed(): bool
    {
        if (!$this->available()) {
            return false;
        }
        $row = DB::table('finance_reset_controls')->where('id', 1)->first();
        if (!$row) {
            throw new \RuntimeException('finance_reset_control_missing');
        }
        return (bool) $row->writes_closed;
    }

    public function assertWritable(): void
    {
        if (!app()->environment(['local','testing']) || !\App\Support\LocalEgress::shouldBlock()
            || config('database.default')!=='mysql' || config('database.connections.mysql.host')!=='127.0.0.1'
            || !in_array(config('database.connections.mysql.database'),['rise_b1_candidate','rise_b1_test'],true)) {
            throw new \RuntimeException('Synthetic isolated local finance profile required.');
        }
        if ($this->closed()) {
            throw new \RuntimeException('prelaunch_finance_writes_closed');
        }
    }

    public function assertNovaSessionWritable(): void
    {
        $nova = app(NovaGatewayClient::class);
        abort_unless($nova->sessionsAllowed(), 503, 'Nova payment sessions are unavailable.');
        if (!$nova->remote()) {
            $this->assertWritable();
            return;
        }
        $this->assertRemoteNovaResetOpen();
    }

    public function assertCryptoDepositCreditWritable(CmsUserTransaction $transaction, array $state): void
    {
        $nova = app(NovaGatewayClient::class);
        if ($transaction->event_type !== NovaDepositService::EVENT) {
            $this->assertWritable();
            return;
        }

        $context = $transaction->crypto_deposit_context;
        if (!$nova->remote() && ($context['provider_mode'] ?? null) === 'test') {
            $this->assertWritable();
            return;
        }
        abort_unless($nova->remote() && $nova->creditAllowed() && DB::transactionLevel() > 0
            && (int) $transaction->action === TransactionActionEnum::Deposit
            && (int) $transaction->status === TransactionStatusEnum::Pending
            && ($context['tenant_binding'] ?? null) === $nova->tenantBinding()
            && ($context['rail_key'] ?? null) === 'btc'
            && ($context['provider_mode'] ?? null) === 'LIVE_HTTP'
            && ($context['network_mode'] ?? null) === 'mainnet'
            && ($state['status'] ?? null) === 'COMPLETED'
            && ($state['status_reason'] ?? null) === 'POLL_ONLY_RECONCILIATION_READY'
            && ($state['transaction_id'] ?? null) === $transaction->transactionId
            && ($state['session_id'] ?? null) === $transaction->provider_transaction_id
            && $transaction->provider_transaction_id !== null
            && ($state['provider_reference'] ?? null) === ($transaction->execution_context_snapshot['provider_reference'] ?? null)
            && $transaction->source_wallet_account_id, 503, 'Nova wallet credit is unavailable.');
        $this->assertRemoteNovaResetOpen();
    }

    private function assertRemoteNovaResetOpen(): void
    {
        abort_unless($this->available(), 503, 'Nova finance controls are unavailable.');
        $query = DB::table('finance_reset_controls')->where('id', 1);
        if (DB::transactionLevel() > 0) {
            $query->lockForUpdate();
        }
        $control = $query->first(['writes_closed']);
        abort_unless($control && !$control->writes_closed, 503, 'Finance writes are paused.');
    }

    public function close(string $declaration): array
    {
        if ($declaration !== PrelaunchFinanceResetPlan::DECLARATION || !$this->available()) {
            throw new \RuntimeException('finance_reset_gate_preflight_failed');
        }
        return DB::transaction(function () use ($declaration): array {
            $row = DB::table('finance_reset_controls')->where('id', 1)->lockForUpdate()->first();
            if (!$row || $row->applied_plan_sha256 !== null) {
                throw new \RuntimeException('finance_reset_gate_unexpected_state');
            }
            if (!$row->writes_closed) {
                DB::table('finance_reset_controls')->where('id', 1)->update([
                    'writes_closed' => true,
                    'declaration_reference' => $declaration,
                    'version' => (int) $row->version + 1,
                    'closed_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            return ['writes_closed' => true, 'version' => (int) $row->version + ($row->writes_closed ? 0 : 1)];
        });
    }

    /** Must be called from within the reset's transaction. */
    public function lockForReset(): object
    {
        if (!DB::transactionLevel() || !$this->available()) {
            throw new \RuntimeException('finance_reset_gate_transaction_required');
        }
        $row = DB::table('finance_reset_controls')->where('id', 1)->lockForUpdate()->first();
        if (!$row || !$row->writes_closed
            || $row->declaration_reference !== PrelaunchFinanceResetPlan::DECLARATION
            || $row->applied_plan_sha256 !== null) {
            throw new \RuntimeException('finance_reset_gate_not_ready');
        }
        return $row;
    }

    public function markApplied(string $planSha256): void
    {
        if (!DB::transactionLevel() || !preg_match('/\A[a-f0-9]{64}\z/', $planSha256)) {
            throw new \RuntimeException('finance_reset_apply_receipt_invalid');
        }
        if (DB::table('finance_reset_controls')->where('id', 1)
            ->where('writes_closed', true)->whereNull('applied_plan_sha256')
            ->update(['applied_plan_sha256' => $planSha256, 'updated_at' => now()]) !== 1) {
            throw new \RuntimeException('finance_reset_apply_receipt_conflict');
        }
    }

    public function openAfterProof(string $planSha256, string $proofSha256): array
    {
        if (!preg_match('/\A[a-f0-9]{64}\z/', $planSha256)
            || !preg_match('/\A[a-f0-9]{64}\z/', $proofSha256)
            || !$this->available()) {
            throw new \RuntimeException('finance_reset_gate_open_preflight_failed');
        }
        return DB::transaction(function () use ($planSha256, $proofSha256): array {
            $row = DB::table('finance_reset_controls')->where('id', 1)->lockForUpdate()->first();
            if (!$row || !$row->writes_closed || $row->applied_plan_sha256 !== $planSha256) {
                throw new \RuntimeException('finance_reset_gate_open_receipt_mismatch');
            }
            DB::table('finance_reset_controls')->where('id', 1)->update([
                'writes_closed' => false, 'version' => (int) $row->version + 1,
                'isolation_proof_sha256' => $proofSha256,
                'opened_at' => now(), 'updated_at' => now(),
            ]);
            return ['writes_closed' => false, 'version' => (int) $row->version + 1];
        });
    }

    /** Abort a dry-run window only when no reset transaction has committed. */
    public function cancelUnapplied(string $declaration): array
    {
        if ($declaration !== PrelaunchFinanceResetPlan::DECLARATION || !$this->available()) {
            throw new \RuntimeException('finance_reset_gate_cancel_preflight_failed');
        }
        return DB::transaction(function (): array {
            $row = DB::table('finance_reset_controls')->where('id', 1)->lockForUpdate()->first();
            if (!$row || !$row->writes_closed
                || $row->declaration_reference !== PrelaunchFinanceResetPlan::DECLARATION
                || $row->applied_plan_sha256 !== null) {
                throw new \RuntimeException('finance_reset_gate_cancel_receipt_conflict');
            }
            DB::table('finance_reset_controls')->where('id', 1)->update([
                'writes_closed' => false, 'version' => (int) $row->version + 1,
                'opened_at' => now(), 'updated_at' => now(),
            ]);
            return ['writes_closed' => false, 'version' => (int) $row->version + 1];
        });
    }
}
