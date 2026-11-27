<?php

namespace App\Services\Certification;

use App\Models\CmsUser;
use App\Models\ProductionCertificationParticipant;
use App\Models\ProductionCertificationRun;
use App\Models\WalletAccount;
use Illuminate\Support\Facades\Schema;

class ProductionCertificationContext
{
    private ?bool $tablesAvailableCache = null;

    public function enabled(): bool
    {
        return (bool) config('production_certification.enabled', false);
    }

    public function isActiveFor(?CmsUser $user): bool
    {
        return $this->activeRunFor($user) instanceof ProductionCertificationRun;
    }

    public function activeRunFor(?CmsUser $user): ?ProductionCertificationRun
    {
        if (!$this->enabled() || !$user instanceof CmsUser || !$this->tablesAvailable()) {
            return null;
        }

        $participant = ProductionCertificationParticipant::query()
            ->where('cms_user_id', (int) $user->id)
            ->whereHas('run', function ($query): void {
                $query->where('status', 'active')
                    ->whereNull('revoked_at')
                    ->whereNull('closed_at')
                    ->where('expires_at', '>', now());
            })
            ->with('run')
            ->latest('production_certification_run_id')
            ->first();

        return $participant?->run;
    }

    public function participantFor(?CmsUser $user): ?ProductionCertificationParticipant
    {
        $run = $this->activeRunFor($user);
        if (!$run instanceof ProductionCertificationRun || !$user instanceof CmsUser) {
            return null;
        }

        return ProductionCertificationParticipant::query()
            ->where('production_certification_run_id', $run->id)
            ->where('cms_user_id', $user->id)
            ->first();
    }

    public function walletFor(CmsUser $user, bool $lockForUpdate = false, ?string $asset = null): ?WalletAccount
    {
        $run = $this->activeRunFor($user);
        if (!$run instanceof ProductionCertificationRun || !Schema::hasTable('wallet_accounts')) {
            return null;
        }

        $query = WalletAccount::query()
            ->where('cms_user_id', (int) $user->id)
            ->where('wallet_type', $this->walletType())
            ->where('wallet_key', $this->walletKey($run))
            ->where('status', 'active');

        if (is_string($asset) && trim($asset) !== '') {
            $query->where('asset', strtoupper(trim($asset)));
        } else {
            $query->where('asset', strtoupper((string) ($user->currency ?: 'TRY')));
        }

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    public function ensureWalletFor(CmsUser $user, string $asset = 'TRY'): ?WalletAccount
    {
        $run = $this->activeRunFor($user);
        $asset = strtoupper(trim($asset));

        if (!$run instanceof ProductionCertificationRun
            || !in_array($asset, (array) config('production_certification.allowed_assets', ['TRY']), true)
            || !Schema::hasTable('wallet_accounts')) {
            return null;
        }

        app(\App\Services\Finance\PrelaunchFinanceWriteGate::class)->assertWritable();
        return WalletAccount::query()->firstOrCreate(
            [
                'cms_user_id' => (int) $user->id,
                'wallet_type' => $this->walletType(),
                'wallet_key' => $this->walletKey($run),
                'asset' => $asset,
            ],
            [
                'display_name' => 'Certification ' . $asset,
                'available_balance' => 0,
                'pending_balance' => 0,
                'status' => 'active',
                'last_synced_at' => now(),
                'meta' => [
                    'is_certification' => true,
                    'production_certification_run_id' => (int) $run->id,
                    'run_key' => (string) $run->run_key,
                    'non_redeemable' => true,
                    'non_withdrawable_to_real_provider' => true,
                    'non_transferable_to_real_wallet' => true,
                ],
            ]
        );
    }

    public function isCertificationWallet(?WalletAccount $wallet): bool
    {
        return $wallet instanceof WalletAccount
            && (string) $wallet->wallet_type === $this->walletType()
            && (bool) data_get($wallet->meta, 'is_certification', false);
    }

    public function auditPayload(CmsUser $user): array
    {
        $run = $this->activeRunFor($user);
        $participant = $run instanceof ProductionCertificationRun
            ? ProductionCertificationParticipant::query()
                ->where('production_certification_run_id', $run->id)
                ->where('cms_user_id', $user->id)
                ->first()
            : null;

        return [
            'is_certification' => $run instanceof ProductionCertificationRun,
            'production_certification_run_id' => $run?->id,
            'production_certification_run_key' => $run?->run_key,
            'certification_persona' => $participant?->persona,
        ];
    }

    public function walletType(): string
    {
        return (string) config('production_certification.wallet_type', 'certification_balance');
    }

    private function walletKey(ProductionCertificationRun $run): string
    {
        return (string) config('production_certification.wallet_key_prefix', 'run-') . $run->id;
    }

    private function tablesAvailable(): bool
    {
        return $this->tablesAvailableCache ??= Schema::hasTable('production_certification_runs')
            && Schema::hasTable('production_certification_participants');
    }
}
