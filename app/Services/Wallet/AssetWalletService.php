<?php

namespace App\Services\Wallet;

use App\Services\Finance\CryptoDepositStateGuard;
use App\Support\Crypto\CryptoAmount;
use App\Support\Crypto\CryptoRail;
use Brick\Math\BigDecimal;

use App\Models\CmsCurrency;
use App\Models\CmsUser;
use App\Models\CmsUserTransaction;
use App\Models\WalletUserPreference;
use App\Models\WalletAccount;
use App\Models\WalletLedgerEntry;
use App\Services\Certification\ProductionCertificationContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AssetWalletService
{
    public function __construct(
        private readonly CanonicalWalletService $wallets,
        private readonly ProductionCertificationContext $certification
    )
    {
    }

    public function tablesAvailable(): bool
    {
        return Schema::hasTable('wallet_accounts') && Schema::hasTable('wallet_ledger_entries');
    }

    public function resolveOwnerUser(CmsUser $user): CmsUser
    {
        return $user->parentAccount ?: $user;
    }

    public function ensurePrimaryBalanceWallet(CmsUser $user, ?string $currency = null, bool $lockForUpdate = false): ?WalletAccount
    {
        if (!$this->tablesAvailable()) {
            return null;
        }

        $owner = $this->resolveOwnerUser($user);
        $requestedCurrency = strtoupper((string) ($currency ?: $user->currency));
        $rootCurrency = strtoupper((string) $owner->currency);
        // Capture the root-currency opening once before an unfunded foreign
        // wallet exists; otherwise creation order can hide the legacy root value.
        if ($requestedCurrency !== $rootCurrency && in_array($rootCurrency, ['TRY', 'USD', 'EUR'], true)
            && !WalletAccount::query()->where('cms_user_id', $owner->id)
                ->where('wallet_type', 'primary_balance')->exists()) {
            $this->ensurePrimaryBalanceWallet($owner, $rootCurrency, $lockForUpdate);
        }
        $walletContext = $this->wallets->resolveForUser($owner, $currency ?: $user->currency, $lockForUpdate);
        if (!$walletContext instanceof CanonicalWalletContext) {
            return null;
        }

        if ($walletContext->walletAccount() instanceof WalletAccount) {
            return $walletContext->walletAccount();
        }

        $initialBalance = $this->wallets->currentDecimalBalance($walletContext, false);
        $hasLegacyCurrencyOwner = strtoupper((string) $walletContext->ownerUser()->currency)
            === strtoupper((string) $walletContext->currency());

        $account = $this->resolveOrCreateWalletAccount([
            'cms_user_id' => (int) $owner->id,
            'wallet_type' => 'primary_balance',
            'wallet_key' => 'default',
            'asset' => (string) $walletContext->currency(),
        ], [
            'display_name' => 'Ana Bakiye',
            'available_balance' => $initialBalance,
            'pending_balance' => 0,
            'status' => 'active',
            'last_synced_at' => now(),
            'meta' => [
                'reference' => 'canonical_wallet_projection',
                'owner_wallet_user_id' => $walletContext->ownerWalletId(),
                'projection_wallet_ids' => $walletContext->projectionWalletIds(),
                'authoritative_balance_version' => 2,
                'authoritative_balance_source' => $hasLegacyCurrencyOwner
                    ? 'legacy_currency_owner'
                    : 'zero_unfunded',
            ],
        ], $lockForUpdate);

        return $account->fresh();
    }

    public function ensureSelectableWallets(CmsUser $user): array
    {
        $owner = $this->resolveOwnerUser($user);
        $wallets = [];

        $defaultPrimary = $this->ensurePrimaryBalanceWallet($owner, $user->currency);
        if ($defaultPrimary instanceof WalletAccount) {
            $wallets[] = $defaultPrimary;
        }

        CmsUser::query()
            ->where('id', $owner->id)
            ->orWhere('parent_id', $owner->id)
            ->orderBy('id')
            ->get()
            ->map(fn (CmsUser $familyUser) => strtoupper((string) ($familyUser->currency ?: 'TRY')))
            ->unique()
            ->each(function (string $currency) use ($owner, &$wallets) {
                $wallet = $this->ensurePrimaryBalanceWallet($owner, $currency);
                if ($wallet instanceof WalletAccount) {
                    $wallets[] = $wallet;
                }
            });

        if (Schema::hasTable('wallet_accounts')) {
            $wallets = array_merge(
                $wallets,
                WalletAccount::query()
                    ->where('cms_user_id', $owner->id)
                    ->where('wallet_type', 'primary_balance')
                    ->where('status', 'active')
                    ->orderBy('asset')
                    ->orderBy('id')
                    ->get()
                    ->all()
            );
        }

        if (Schema::hasTable('wallet_accounts')) {
            $wallets = array_merge(
                $wallets,
                WalletAccount::query()
                    ->where('cms_user_id', $owner->id)
                    ->where('wallet_type', 'crypto_asset_wallet')
                    ->where('status', 'active')
                    ->orderByRaw("case asset when 'BTC' then 0 when 'ETH' then 1 when 'LTC' then 2 else 99 end")
                    ->orderBy('id')
                    ->get()
                    ->all()
            );
        }

        if ($this->certification->isActiveFor($user)) {
            $certificationWallet = $this->certification->walletFor($user)
                ?: $this->certification->ensureWalletFor($user, (string) ($user->currency ?: 'TRY'));
            if ($certificationWallet instanceof WalletAccount) {
                $wallets[] = $certificationWallet;
            }
        }

        return collect($wallets)
            ->filter(fn ($wallet) => $wallet instanceof WalletAccount && $wallet->status === 'active')
            ->unique(fn (WalletAccount $wallet) => implode(':', [$wallet->wallet_type, $wallet->wallet_key, $wallet->asset]))
            ->values()
            ->all();
    }

    public function selectableWalletsForUser(CmsUser $user): array
    {
        return collect($this->ensureSelectableWallets($user))
            ->map(fn (WalletAccount $wallet) => $this->serializeWallet(
                $wallet,
                $user,
                (string) $wallet->wallet_type === 'primary_balance'
            ))
            ->filter()
            ->values()
            ->all();
    }

    public function ensureCryptoWallet(CmsUser $user, string $asset = 'BTC', bool $lockForUpdate = false): ?WalletAccount
    {
        if (!$this->tablesAvailable()) {
            return null;
        }

        $owner = $this->resolveOwnerUser($user);
        $normalizedAsset = strtoupper(trim($asset));
        // Token networks must never silently enter the native/default wallet namespace.
        if ($normalizedAsset === 'USDT') {
            return null;
        }

        return $this->resolveOrCreateWalletAccount([
            'cms_user_id' => (int) $owner->id,
            'wallet_type' => 'crypto_asset_wallet',
            'wallet_key' => 'default',
            'asset' => $normalizedAsset,
        ], [
            'display_name' => $normalizedAsset . ' Cüzdanı',
            'available_balance' => 0,
            'pending_balance' => 0,
            'status' => 'active',
            'last_synced_at' => now(),
            'meta' => [
                'wallet_origin' => 'crypto_asset_domain',
                ...(in_array($normalizedAsset, ['BTC', 'LTC'], true) ? [
                    'network' => $normalizedAsset,
                    'rail_key' => CryptoRail::resolve($normalizedAsset)->key,
                ] : []),
            ],
        ], $lockForUpdate);
    }

    public function ensureCryptoRailWallet(CmsUser $user, CryptoRail $rail, bool $lockForUpdate = false): ?WalletAccount
    {
        return $this->resolveCryptoRailWallet($user, $rail, $lockForUpdate);
    }

    public function ensureNovaDepositWallet(CmsUser $user, CryptoRail $rail): ?WalletAccount
    {
        app(\App\Services\CryptoControl\CryptoSettlementMaintenanceGate::class)->assertHeld();
        $writes = app(\App\Services\Finance\PrelaunchFinanceWriteGate::class);
        return $this->resolveCryptoRailWallet($user, $rail, true, $writes->assertNovaSessionWritable(...));
    }

    private function resolveCryptoRailWallet(
        CmsUser $user, CryptoRail $rail, bool $lockForUpdate, ?\Closure $authorizeCreation = null
    ): ?WalletAccount {
        if (!$this->tablesAvailable()) {
            return null;
        }
        $owner = $this->resolveOwnerUser($user);
        return $this->resolveOrCreateWalletAccount([
            'cms_user_id' => (int) $owner->id,
            ...$rail->walletIdentity(),
        ], [
            'display_name' => $rail->asset . ' Cüzdanı',
            'available_balance' => 0, 'pending_balance' => 0,
            'status' => 'active', 'last_synced_at' => now(),
            'meta' => ['wallet_origin' => 'crypto_asset_domain', 'network' => $rail->network,
                'rail_key' => $rail->key, 'precision' => $rail->precision],
        ], $lockForUpdate, $authorizeCreation);
    }

    public function creditCryptoDeposit(CmsUserTransaction $transaction, array $gatewayState): ?WalletAccount
    {
        return app(\App\Services\CryptoControl\CryptoSettlementMaintenanceGate::class)->run(
            'cms.wallet_credit', (string) $transaction->transactionId,
            fn () => $this->creditCryptoDepositUnderLease($transaction, $gatewayState)
        );
    }

    private function creditCryptoDepositUnderLease(CmsUserTransaction $transaction, array $gatewayState): ?WalletAccount
    {
        app(CryptoDepositStateGuard::class)->assertCreditable($transaction, $gatewayState);
        if (!$this->tablesAvailable()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['crypto' => 'Crypto wallet schema is unavailable.']);
        }

        $amount = $this->cryptoDepositCreditAmount($gatewayState);
        if (!BigDecimal::of($amount)->isPositive()) {
            return null;
        }

        return DB::transaction(function () use ($transaction, $gatewayState, $amount) {
            $transaction = CmsUserTransaction::whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            $rail = app(CryptoDepositStateGuard::class)->assertCreditable($transaction, $gatewayState);
            app(\App\Services\Finance\PrelaunchFinanceWriteGate::class)
                ->assertCryptoDepositCreditWritable($transaction, $gatewayState);
            $transaction->loadMissing('user.parentAccount');
            $walletOwner = $transaction->user;
            if (!$walletOwner instanceof CmsUser) {
                throw \Illuminate\Validation\ValidationException::withMessages(['crypto' => 'Crypto wallet owner is unavailable.']);
            }

            $wallet = $this->ensureCryptoRailWallet($walletOwner, $rail, true);
            if (!$wallet instanceof WalletAccount) {
                throw \Illuminate\Validation\ValidationException::withMessages(['crypto' => 'Crypto wallet is unavailable.']);
            }

            $idempotencyKey = 'crypto_deposit_credited:' . (string) $transaction->transactionId;
            $existingEntry = WalletLedgerEntry::query()
                ->where('wallet_account_id', $wallet->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existingEntry instanceof WalletLedgerEntry) {
                // Historical credited rows are a no-op, never an inferred/backfilled payment claim.
                if (!empty($existingEntry->meta['provenance'])) {
                    app(\App\Services\Finance\CryptoSettlementClaimService::class)->acquire($transaction, $gatewayState);
                }
                return $wallet->fresh();
            }

            app(\App\Services\Finance\CryptoSettlementClaimService::class)->acquire($transaction, $gatewayState);
            $wallet = WalletAccount::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            $before = CryptoAmount::normalize($wallet->available_balance, $rail);
            $after = CryptoAmount::normalize((string) BigDecimal::of($before)->plus($amount), $rail);

            $wallet->forceFill([
                'available_balance' => $after,
                'last_synced_at' => now(),
            ])->save();

            WalletLedgerEntry::query()->create([
                'wallet_account_id' => $wallet->id,
                'cms_user_id' => $wallet->cms_user_id,
                'entry_type' => 'crypto_deposit_credited',
                'direction' => 'credit',
                'status' => 'applied',
                'amount' => $amount,
                'balance_before' => $before,
                'balance_after' => $after,
                'asset' => $wallet->asset,
                'source_type' => 'cms_user_transaction',
                'source_reference' => (string) $transaction->transactionId,
                'idempotency_key' => $idempotencyKey,
                'applied_at' => now(),
                'meta' => [
                    'session_id' => $gatewayState['session_id'] ?? null,
                    'network' => $rail->network,
                    'rail_key' => $rail->key,
                    'precision' => $rail->precision,
                    'provider' => $gatewayState['provider'] ?? null,
                    'tx_hash' => $gatewayState['tx_hash'] ?? null,
                    'provider_reference' => $gatewayState['provider_reference'] ?? null,
                    'status' => $gatewayState['status'] ?? null,
                    'requested_amount_crypto' => $gatewayState['amount_crypto'] ?? null,
                    'received_amount_crypto' => $gatewayState['received_amount_crypto'] ?? null,
                    'credited_amount_crypto' => $amount,
                    'operator_deposit_fee_crypto' => CryptoAmount::normalize('0', $rail),
                    'credit_amount_source' => $this->cryptoDepositCreditAmountSource($gatewayState),
                    'provenance' => $gatewayState['provenance'],
                    'payment_outputs' => $gatewayState['payment_outputs'],
                ],
            ]);

            return $wallet->fresh();
        });
    }

    private function cryptoDepositCreditAmount(array $gatewayState): string
    {
        return CryptoAmount::creditAmount($gatewayState);
    }

    private function cryptoDepositCreditAmountSource(array $gatewayState): string
    {
        return 'received_amount_crypto';
    }

    public function markCryptoDepositCompleted(CmsUserTransaction $transaction, array $gatewayState): ?WalletLedgerEntry
    {
        return app(\App\Services\CryptoControl\CryptoSettlementMaintenanceGate::class)->run(
            'cms.wallet_complete', (string) $transaction->transactionId,
            fn () => $this->markCryptoDepositCompletedUnderLease($transaction, $gatewayState)
        );
    }

    private function markCryptoDepositCompletedUnderLease(CmsUserTransaction $transaction, array $gatewayState): ?WalletLedgerEntry
    {
        return DB::transaction(function () use ($transaction, $gatewayState) {
            $transaction = CmsUserTransaction::whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            $rail = app(CryptoDepositStateGuard::class)->assertCreditable($transaction, $gatewayState);
            app(\App\Services\Finance\PrelaunchFinanceWriteGate::class)
                ->assertCryptoDepositCreditWritable($transaction, $gatewayState);
            if (strtoupper((string) $gatewayState['status']) !== 'COMPLETED'
                || !WalletLedgerEntry::where('idempotency_key', 'crypto_deposit_credited:' . $transaction->transactionId)->exists()) {
                throw new \App\Services\Finance\CryptoSettlementRejected('provider_not_settled');
            }
            if (!$this->tablesAvailable()) {
                return null;
            }

            $transaction->loadMissing('user.parentAccount');
            $walletOwner = $transaction->user;
            if (!$walletOwner instanceof CmsUser) {
                return null;
            }

            $wallet = $this->ensureCryptoRailWallet($walletOwner, $rail, true);
            if (!$wallet instanceof WalletAccount) {
                return null;
            }

            $idempotencyKey = 'crypto_deposit_completed:' . (string) $transaction->transactionId;

            return WalletLedgerEntry::query()->firstOrCreate(
                [
                    'wallet_account_id' => $wallet->id,
                    'idempotency_key' => $idempotencyKey,
                ],
                [
                    'cms_user_id' => $wallet->cms_user_id,
                    'entry_type' => 'crypto_deposit_completed',
                    'direction' => 'credit',
                    'status' => 'applied',
                    'amount' => 0,
                    'balance_before' => CryptoAmount::normalize($wallet->available_balance, $rail),
                    'balance_after' => CryptoAmount::normalize($wallet->available_balance, $rail),
                    'asset' => $wallet->asset,
                    'source_type' => 'cms_user_transaction',
                    'source_reference' => (string) $transaction->transactionId,
                    'applied_at' => now(),
                    'meta' => [
                        'session_id' => $gatewayState['session_id'] ?? null,
                        'provider_reference' => $gatewayState['provider_reference'] ?? null,
                        'status' => $gatewayState['status'] ?? null,
                        'network' => $rail->network,
                        'rail_key' => $rail->key,
                        'precision' => $rail->precision,
                        'provider' => $gatewayState['provider'] ?? null,
                        'tx_hash' => $gatewayState['tx_hash'] ?? null,
                    ],
                ]
            );
        });
    }

    public function portfolioForUser(CmsUser $user): array
    {
        $walletsAvailable = $this->tablesAvailable();
        $primaryBalanceWallet = $this->ensurePrimaryBalanceWallet($user);
        $selectableWallets = $this->ensureSelectableWallets($user);
        $fiatWallets = collect($selectableWallets)
            ->filter(fn (WalletAccount $wallet) => (string) $wallet->wallet_type === 'primary_balance')
            ->sortBy(fn (WalletAccount $wallet) => (string) $wallet->asset === (string) $user->currency ? 0 : 1)
            ->values();
        $cryptoWallets = $walletsAvailable
            ? collect($selectableWallets)
                ->filter(fn (WalletAccount $wallet) => (string) $wallet->wallet_type === 'crypto_asset_wallet')
                ->values()
            : collect();

        $activeWalletId = null;
        if ($this->certification->isActiveFor($user)) {
            $activeWalletId = (int) optional($this->certification->walletFor($user))->id;
        } elseif (Schema::hasTable('wallet_user_preferences')) {
            $activeWalletId = (int) WalletUserPreference::query()
                ->where('cms_user_id', $this->resolveOwnerUser($user)->id)
                ->value('active_wallet_account_id');
        }

        // A missing/stale preference must agree with the canonical registration-currency fallback.
        $preferredWalletId = $activeWalletId;
        $selectedWallet = collect($selectableWallets)->firstWhere('id', $activeWalletId)
            ?: ($primaryBalanceWallet?->status === 'active' ? $primaryBalanceWallet : null);
        $fallback = $preferredWalletId > 0 && $preferredWalletId !== (int) $selectedWallet?->id;
        $activeWalletId = $selectedWallet ? (int) $selectedWallet->id : null;
        $selectableWallets = collect($selectableWallets)
            ->sortBy(fn (WalletAccount $wallet) => (int) $wallet->id === $activeWalletId ? 0 : 1)
            ->values()->all();

        return [
            'activeWalletResolution' => [
                'preferred_wallet_account_id' => $preferredWalletId ?: null,
                'fallback' => $fallback,
                'reason_code' => $fallback ? 'preferred_wallet_unavailable' : ($selectedWallet ? null : 'wallet_unavailable'),
                'message' => $fallback ? trans('wallet_playability.preferred_wallet_unavailable') : null,
            ],
            'primaryBalance' => $this->serializeWallet($primaryBalanceWallet, $user, true),
            'fiatWallets' => $fiatWallets
                ->map(fn (WalletAccount $wallet) => $this->serializeWallet($wallet, $user, true, $activeWalletId))
                ->values()
                ->all(),
            'cryptoWallets' => $cryptoWallets
                ->map(fn (WalletAccount $wallet) => $this->serializeWallet($wallet, $user, false, $activeWalletId))
                ->values()
                ->all(),
            'activeWallet' => $this->serializeWallet(
                $selectedWallet,
                $user,
                $selectedWallet instanceof WalletAccount && (string) $selectedWallet->wallet_type === 'primary_balance',
                $activeWalletId
            ),
            'addableWallets' => $this->addableWalletOptionsForUser($user),
            'selectableWallets' => collect($selectableWallets)
                ->map(fn (WalletAccount $wallet) => $this->serializeWallet(
                    $wallet,
                    $user,
                    (string) $wallet->wallet_type === 'primary_balance',
                    $activeWalletId
                ))
                ->values()
                ->all(),
        ];
    }

    public function snapshotForUser(CmsUser $user): array
    {
        $portfolio = $this->portfolioForUser($user);

        return [
            'assetWallets' => collect($portfolio['cryptoWallets'] ?? [])
                ->map(function (array $wallet) {
                    return [
                        'walletAccountId' => $wallet['walletAccountId'] ?? null,
                        'walletType' => $wallet['walletType'] ?? null,
                        'walletKey' => $wallet['walletKey'] ?? null,
                        'asset' => $wallet['asset'] ?? null,
                        'name' => $wallet['name'] ?? null,
                        'nativeBalance' => $wallet['nativeBalance'] ?? null,
                        'formattedNativeBalance' => $wallet['formattedNativeBalance'] ?? null,
                        'approxTryValue' => $wallet['approxTryValue'] ?? null,
                        'formattedApproxTry' => $wallet['formattedApproxTry'] ?? null,
                        'convertTargets' => $wallet['convertTargets'] ?? [],
                        'transferTargets' => $wallet['transferTargets'] ?? [],
                    ];
                })
                ->all(),
            'fiatWallets' => $portfolio['fiatWallets'] ?? [],
            'activeWallet' => $portfolio['activeWallet'] ?? null,
            'selectableWallets' => $portfolio['selectableWallets'] ?? [],
        ];
    }

    public function findWalletForOwner(CmsUser $user, int $walletAccountId, bool $lockForUpdate = false): ?WalletAccount
    {
        if (!$this->tablesAvailable()) {
            return null;
        }

        $query = WalletAccount::query()
            ->where('cms_user_id', $this->resolveOwnerUser($user)->id)
            ->whereKey($walletAccountId);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    public function serializeWalletAccount(?WalletAccount $wallet, CmsUser $user): ?array
    {
        return $this->serializeWallet($wallet, $user, (string) optional($wallet)->wallet_type === 'primary_balance');
    }

    public function addableWalletOptionsForUser(CmsUser $user): array
    {
        $existingKeys = collect($this->ensureSelectableWallets($user))
            ->filter(fn ($wallet) => $wallet instanceof WalletAccount)
            ->mapWithKeys(function (WalletAccount $wallet) {
                return [strtoupper((string) $wallet->wallet_type . ':' . $wallet->asset) => true];
            });

        $fiatOptions = $this->resolveFiatAssetCodes($user)
            ->filter(fn (string $asset) => !$existingKeys->has(strtoupper('primary_balance:' . $asset)))
            ->map(fn (string $asset) => [
                'walletType' => 'primary_balance',
                'asset' => $asset,
                'name' => trans('cms_wallet_center.fiat_balance_name', ['asset' => $asset]),
                'description' => trans('cms_wallet_center.open_fiat_wallet'),
            ]);

        $policy = app(CustomerCryptoRailPolicy::class);
        $owned = WalletAccount::query()->where('cms_user_id', $this->resolveOwnerUser($user)->id)
            ->where('wallet_type', 'crypto_asset_wallet')->get();
        $cryptoOptions = collect($policy->all())
            ->filter(fn (array $rail) => $policy->permits($rail['railKey'], 'wallet_discoverable'))
            ->reject(fn (array $rail) => $owned->contains(fn (WalletAccount $wallet) =>
                $wallet->asset === $rail['asset'] && $wallet->wallet_key === $rail['walletKey']))
            ->map(fn (array $rail) => $policy->descriptor($rail));

        return $fiatOptions
            ->concat($cryptoOptions)
            ->values()
            ->all();
    }

    public function openWalletOptionForUser(CmsUser $user, string $walletType, string $asset = '', ?string $railKey = null, array $hints = []): ?WalletAccount
    {
        $normalizedWalletType = trim(strtolower($walletType));
        $normalizedAsset = strtoupper(trim($asset));

        if ($normalizedWalletType === 'primary_balance') {
            return $normalizedAsset !== '' ? $this->ensurePrimaryBalanceWallet($user, $normalizedAsset) : null;
        }

        if ($normalizedWalletType === 'crypto_asset_wallet') {
            $policy = app(CustomerCryptoRailPolicy::class);
            if ($railKey === null || !$policy->permits($railKey, 'wallet_discoverable')) return null;
            $descriptor = $policy->find($railKey);
            if ($asset !== '') $hints['asset'] = $asset;
            foreach (['asset', 'network', 'walletKey'] as $field) {
                if (array_key_exists($field, $hints) && $hints[$field] !== $descriptor[$field]) return null;
            }
            $owner = $this->resolveOwnerUser($user);
            $rail = $policy->rail($railKey);
            return DB::transaction(function () use ($owner, $rail) {
                CmsUser::query()->whereKey($owner->id)->lockForUpdate()->firstOrFail();
                $existing = WalletAccount::query()->where('cms_user_id', $owner->id)
                    ->where($rail->walletIdentity())->lockForUpdate()->first();
                if ($existing) {
                    return $existing->status === 'active' && CryptoWalletRailReadModel::identity($existing)
                        === CryptoRail::publicIdentity($rail->asset, $rail->network) ? $existing : null;
                }
                return $this->ensureCryptoRailWallet($owner, $rail, true);
            }, 3);
        }

        return null;
    }

    private function serializeWallet(?WalletAccount $wallet, CmsUser $user, bool $isPrimary = false, ?int $activeWalletId = null): ?array
    {
        if (!$wallet instanceof WalletAccount) {
            return null;
        }

        $nativeBalance = round((float) $wallet->available_balance, 8);
        $rail = CryptoWalletRailReadModel::identity($wallet);
        $network = $rail['network'] ?? ($wallet->meta['network'] ?? null);
        $network = is_string($network) && preg_match('/^[A-Z0-9]{1,16}$/', $network) ? $network : null;
        $assetLabel = $wallet->asset === 'USDT' ? 'USDT · ' . ($network ?: '?') : (string) $wallet->asset;
        $isCertification = $this->certification->isCertificationWallet($wallet);
        $formattedNative = ($isPrimary || $isCertification)
            ? number_format($nativeBalance, 2) . ' ' . $wallet->asset
            : number_format($nativeBalance, 8, '.', '') . ' ' . $wallet->asset;

        $approxTryValue = $this->approximateTryValue($wallet->asset, $nativeBalance);
        $convertTargets = $this->convertTargetsForWallet($wallet, $user);
        $transferTargets = $this->transferTargetsForWallet($wallet, $user);

        return [
            'walletAccountId' => (int) $wallet->id,
            'walletType' => (string) $wallet->wallet_type,
            'walletKey' => (string) $wallet->wallet_key,
            'status' => (string) $wallet->status,
            'asset' => (string) $wallet->asset,
            'network' => $rail['network'] ?? null,
            'railKey' => $rail['railKey'] ?? null,
            'assetLabel' => $assetLabel,
            'name' => $this->localizedWalletDisplayName($wallet, $assetLabel),
            'nativeBalance' => $nativeBalance,
            'exactNativeBalance' => (string) $wallet->available_balance,
            'formattedNativeBalance' => $formattedNative,
            'availableLabel' => ($isPrimary || $isCertification)
                ? number_format($nativeBalance, 2) . ' ' . $wallet->asset
                : number_format($nativeBalance, 8, '.', '') . ' ' . $wallet->asset,
            'approxTryValue' => $approxTryValue,
            'formattedApproxTry' => $approxTryValue === null ? null : trans('cms_wallet_center.formatted_approx_try', ['amount' => number_format($approxTryValue, 2)]),
            'convertTargets' => $convertTargets,
            'transferTargets' => $transferTargets,
            'isActive' => $wallet->status === 'active' && ($activeWalletId !== null
                ? (int) $wallet->id === (int) $activeWalletId
                : ((string) $wallet->wallet_type === 'primary_balance' && (string) $wallet->asset === (string) $user->currency)),
            'isCertification' => $isCertification,
            'nonRedeemable' => $isCertification,
        ];
    }

    private function localizedWalletDisplayName(WalletAccount $wallet, string $assetLabel): string
    {
        if ($wallet->asset === 'USDT') {
            return $assetLabel;
        }

        $name = (string) ($wallet->display_name ?: ($wallet->asset . ' Cüzdanı'));
        if ($wallet->wallet_type === 'primary_balance' && $name === 'Ana Bakiye') {
            return trans('cms_wallet_center.main_balance');
        }
        if ($wallet->wallet_type === 'crypto_asset_wallet' && $name === $wallet->asset . ' Cüzdanı') {
            return trans('cms_wallet_center.asset_wallet', ['asset' => $wallet->asset]);
        }

        return $name;
    }

    private function convertTargetsForWallet(WalletAccount $wallet, CmsUser $user): array
    {
        if ($this->certification->isCertificationWallet($wallet)) {
            return collect((array) config('production_certification.allowed_assets', ['TRY']))
                ->map(fn ($asset) => strtoupper(trim((string) $asset)))
                ->filter(fn (string $asset) => $asset !== '' && $asset !== strtoupper((string) $wallet->asset))
                ->map(fn (string $asset) => $this->certification->ensureWalletFor($user, $asset))
                ->filter(fn ($target) => $target instanceof WalletAccount && $this->certification->isCertificationWallet($target))
                ->map(fn (WalletAccount $target) => [
                    'walletAccountId' => (int) $target->id,
                    'walletType' => (string) $target->wallet_type,
                    'asset' => (string) $target->asset,
                    'name' => (string) ($target->display_name ?: ('Certification ' . $target->asset)),
                ])
                ->values()
                ->all();
        }

        return app(WalletConversionPolicy::class)->targets($user, $wallet);
    }

    private function transferTargetsForWallet(WalletAccount $wallet, CmsUser $user): array
    {
        if (!$this->tablesAvailable()) {
            return [];
        }

        $sourceRail = CryptoWalletRailReadModel::identity($wallet);
        if ($wallet->wallet_type === 'crypto_asset_wallet' && !$sourceRail) {
            return [];
        }
        return WalletAccount::query()
            ->where('cms_user_id', $this->resolveOwnerUser($user)->id)
            ->where('asset', $wallet->asset)
            ->where('status', 'active')
            ->where('id', '!=', $wallet->id)
            ->orderBy('id')
            ->get()
            ->filter(function (WalletAccount $target) use ($wallet, $sourceRail) {
                if ($wallet->wallet_type !== 'crypto_asset_wallet') {
                    return $target->wallet_type !== 'crypto_asset_wallet';
                }
                return $sourceRail === CryptoWalletRailReadModel::identity($target);
            })
            ->map(function (WalletAccount $target) {
                return [
                    'walletAccountId' => (int) $target->id,
                    'walletType' => (string) $target->wallet_type,
                    'walletKey' => (string) $target->wallet_key,
                    'network' => CryptoWalletRailReadModel::identity($target)['network'] ?? null,
                    'railKey' => CryptoWalletRailReadModel::identity($target)['railKey'] ?? null,
                    'asset' => (string) $target->asset,
                    'name' => $this->localizedWalletDisplayName($target, (string) $target->asset),
                ];
            })
            ->values()
            ->all();
    }

    private function approximateTryValue(string $asset, float $amount): ?float
    {
        // LTC legacy CmsCurrency rows are not a time-bound rate authority.
        // A live LTC estimate is hydrated from the verified conversion quote.
        if (strtoupper($asset) === 'LTC') return null;
        $source = CmsCurrency::query()->where('code', strtoupper($asset))->first();
        $try = CmsCurrency::query()->where('code', 'TRY')->first();

        if (!$source || !$try) {
            return null;
        }

        return round($amount * ((float) $source->rate / max((float) $try->rate, 0.000001)), 2);
    }

    private function resolveFiatAssetCodes(CmsUser $user): Collection
    {
        $configuredFiatAssets = collect((array) config('product_wallets.fiat_assets', ['TRY', 'USD', 'EUR']))
            ->map(fn ($asset) => strtoupper(trim((string) $asset)))
            ->filter();

        $activeCurrencyCodes = CmsCurrency::query()
            ->where('status', 'active')
            ->pluck('code')
            ->map(fn ($asset) => strtoupper(trim((string) $asset)))
            ->filter();

        return $configuredFiatAssets
            ->concat($activeCurrencyCodes->intersect($configuredFiatAssets))
            ->push(strtoupper((string) ($user->currency ?: 'TRY')))
            ->unique()
            ->values();
    }

    private function resolveOrCreateWalletAccount(
        array $identity, array $defaults, bool $lockForUpdate = false, ?\Closure $authorizeCreation = null
    ): WalletAccount {
        $query = WalletAccount::query()->where($identity);
        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $existing = $query->first();
        if ($existing instanceof WalletAccount) {
            return $existing;
        }

        $authorizeCreation ??= app(\App\Services\Finance\PrelaunchFinanceWriteGate::class)->assertWritable(...);
        $authorizeCreation();
        return WalletAccount::query()->create(array_merge($identity, $defaults));
    }
}
