<?php

namespace App\Services\Wallet;

use App\Models\CmsUser;
use App\Models\WalletAccount;
use App\Support\Money\CurrencyPrecision;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class CanonicalWalletService
{
    public function resolveForUserId(?int $userId, ?string $currency = null, bool $lockForUpdate = false): ?CanonicalWalletContext
    {
        $resolvedUserId = (int) ($userId ?? 0);
        if ($resolvedUserId <= 0) {
            return null;
        }

        $user = $this->findUser($resolvedUserId, $lockForUpdate);

        return $user instanceof CmsUser
            ? $this->resolveForUser($user, $currency, $lockForUpdate)
            : null;
    }

    public function resolveForUser(?CmsUser $user, ?string $currency = null, bool $lockForUpdate = false): ?CanonicalWalletContext
    {
        if (!$user instanceof CmsUser) {
            return null;
        }

        $requestedUser = $this->findUser((int) $user->id, $lockForUpdate);
        if (!$requestedUser instanceof CmsUser) {
            return null;
        }
        $rootUser = $requestedUser->parent_id
            ? $this->findUser((int) $requestedUser->parent_id, $lockForUpdate)
            : $requestedUser;
        if (!$rootUser instanceof CmsUser) {
            return null;
        }

        $resolvedCurrency = strtoupper(trim((string) ($currency ?: $requestedUser->currency ?: $rootUser->currency ?: 'TRY')));
        $sameCurrencyChildren = $this->sameCurrencyChildren($rootUser, $resolvedCurrency, $lockForUpdate);
        $requestedUserMatchesCurrency = strtoupper((string) $requestedUser->currency) === $resolvedCurrency;
        $rootMatchesCurrency = strtoupper((string) $rootUser->currency) === $resolvedCurrency;

        if ($rootMatchesCurrency) {
            $ownerUser = $rootUser;
            $contextUser = $requestedUser->parent_id && $requestedUserMatchesCurrency
                ? $requestedUser
                : ($sameCurrencyChildren->firstWhere('id', $requestedUser->id) ?: $sameCurrencyChildren->first() ?: $rootUser);
            $projectionUsers = $sameCurrencyChildren->prepend($rootUser)->unique('id')->values()->all();
        } else {
            $ownerUser = ($requestedUser->parent_id && $requestedUserMatchesCurrency)
                ? $requestedUser
                : $sameCurrencyChildren->first();

            if (!$ownerUser instanceof CmsUser) {
                $ownerUser = $rootUser;
            }

            $contextUser = ($requestedUser->parent_id && $requestedUserMatchesCurrency)
                ? $requestedUser
                : ($sameCurrencyChildren->firstWhere('id', $requestedUser->id) ?: $ownerUser);
            $projectionUsers = $sameCurrencyChildren->isNotEmpty()
                ? $sameCurrencyChildren->unique('id')->values()->all()
                : [$ownerUser];
        }

        $walletAccount = $this->primaryWalletFor($rootUser, $resolvedCurrency, $lockForUpdate);

        return new CanonicalWalletContext(
            rootUser: $rootUser,
            ownerUser: $ownerUser,
            contextUser: $contextUser instanceof CmsUser ? $contextUser : $ownerUser,
            currency: $resolvedCurrency,
            projectionUsers: $projectionUsers,
            walletAccount: $walletAccount,
        );
    }

    public function currentBalance(CanonicalWalletContext $context): float
    {
        return (float) $this->currentDecimalBalance($context, false);
    }

    public function currentDecimalBalance(CanonicalWalletContext $context, bool $strict = true): string
    {
        $wallet = $context->walletAccount()
            ?: $this->primaryWalletFor($context->rootUser(), $context->currency(), false);
        $value = $wallet instanceof WalletAccount
            && (int) data_get($wallet->meta, 'authoritative_balance_version', 0) >= 2
                ? (string) $wallet->available_balance
                : $this->legacyBalanceFor($context);

        // Validate before callers create a financial record, not just at the
        // later balance write. Unreviewed independent value cannot become zero.
        if ($wallet instanceof WalletAccount
            && (int) data_get($wallet->meta, 'authoritative_balance_version', 0) < 2) {
            $plan = app(PrimaryWalletAuthorityPlan::class)->forWallet($wallet);
            if (!$plan['safe'] || !BigDecimal::of($value)->isEqualTo((string) $plan['after_balance'])) {
                throw new \App\Services\Wallet\Exceptions\WalletAuthorityUnavailableException();
            }
        }

        return (string) BigDecimal::of($value)
            ->toScale(
                CurrencyPrecision::scaleFor($context->currency(), 'primary_balance'),
                $strict ? RoundingMode::UNNECESSARY : RoundingMode::HALF_UP
            );
    }

    public function projectDecimalBalance(CanonicalWalletContext $context, string $balance): void
    {
        app(\App\Services\Finance\PrelaunchFinanceWriteGate::class)->assertWritable();
        $value = (string) BigDecimal::of($balance)
            ->toScale(CurrencyPrecision::scaleFor($context->currency(), 'primary_balance'), RoundingMode::UNNECESSARY);

        $wallet = $context->walletAccount()
            ?: $this->primaryWalletFor($context->rootUser(), $context->currency(), true);
        if ($wallet instanceof WalletAccount) {
            if ((int) data_get($wallet->meta, 'authoritative_balance_version', 0) < 2) {
                $plan = app(PrimaryWalletAuthorityPlan::class)->forWallet($wallet);
                if (!$plan['safe'] || $plan['category'] === 'B' || !BigDecimal::of($this->currentDecimalBalance($context))
                    ->isEqualTo((string) $plan['after_balance'])) {
                    throw new \DomainException('Primary wallet requires reviewed authority reconciliation.');
                }
            }
            $meta = array_merge((array) $wallet->meta, [
                'authoritative_balance_version' => 2,
                'authoritative_balance_source' => 'wallet_accounts.available_balance',
            ]);
            WalletAccount::query()->whereKey($wallet->id)->update([
                'available_balance' => $value,
                'last_synced_at' => now(),
                'meta' => json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
            $context->syncWalletDecimalBalance($value, $meta);
        }

        $mirrorIds = $context->compatibilityMirrorUserIds();
        if ($mirrorIds !== []) {
            CmsUser::query()->whereIn('id', $mirrorIds)
                ->where('currency', $context->currency())->update(['balance' => $value]);
        }
        $context->syncProjectedDecimalBalance($value);
    }

    public function projectBalance(CanonicalWalletContext $context, float $balance, array $meta = []): void
    {
        $normalizedBalance = CurrencyPrecision::normalize(
            (string) $balance,
            CurrencyPrecision::scaleFor($context->currency(), 'primary_balance')
        );
        $this->projectDecimalBalance($context, (string) $normalizedBalance);
        $this->assertNoDrift($context, 'wallet_projection_after_write', array_merge($meta, [
            'projected_balance' => $normalizedBalance,
        ]));
    }

    public function assertNoDrift(CanonicalWalletContext $context, string $source, array $meta = []): void
    {
        $mirrorIds = $context->compatibilityMirrorUserIds();
        if ($mirrorIds === []) {
            return;
        }

        $walletRows = CmsUser::query()
            ->whereIn('id', $mirrorIds)
            ->orderBy('id')
            ->get(['id', 'balance']);

        $expected = $this->currentDecimalBalance($context, false);
        $distinctBalances = $walletRows
            ->map(fn (CmsUser $user) => CurrencyPrecision::normalize(
                (string) $user->balance,
                CurrencyPrecision::scaleFor($context->currency(), 'primary_balance')
            ))
            ->push($expected)
            ->unique()
            ->values();

        if ($distinctBalances->count() <= 1) {
            return;
        }

        $payload = array_merge($context->auditPayload(), $meta, [
            'source' => $source,
            'wallet_balances' => $walletRows
                ->map(fn (CmsUser $user) => [
                    'id' => (int) $user->id,
                    'balance' => CurrencyPrecision::normalize(
                        (string) $user->balance,
                        CurrencyPrecision::scaleFor($context->currency(), 'primary_balance')
                    ),
                ])
                ->all(),
            'authoritative_balance' => $expected,
        ]);

        Log::warning('Canonical wallet drift detected.', $payload);

        DB::table('cms_webhook_logs')->insert([
            'request' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'message' => 'canonical_wallet_drift_detected',
            'reqdate' => now(),
        ]);
    }

    private function findUser(int $userId, bool $lockForUpdate = false): ?CmsUser
    {
        $query = CmsUser::query()->whereKey($userId);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    private function legacyBalanceFor(CanonicalWalletContext $context): string
    {
        if (strtoupper((string) $context->ownerUser()->currency) !== strtoupper($context->currency())) {
            return '0';
        }

        if ($context->walletAccount() instanceof WalletAccount
            || (int) $context->ownerUser()->id !== (int) $context->rootUser()->id
            || !Schema::hasTable('wallet_accounts')) {
            return (string) $context->ownerUser()->balance;
        }

        $hasAnotherPrimaryWallet = WalletAccount::query()
            ->where('cms_user_id', $context->rootUser()->id)
            ->where('wallet_type', 'primary_balance')
            ->exists();

        return $hasAnotherPrimaryWallet ? '0' : (string) $context->ownerUser()->balance;
    }

    private function primaryWalletFor(CmsUser $rootUser, string $currency, bool $lockForUpdate): ?WalletAccount
    {
        if (!Schema::hasTable('wallet_accounts')) {
            return null;
        }

        $query = WalletAccount::query()
            ->where('cms_user_id', $rootUser->id)
            ->where('wallet_type', 'primary_balance')
            ->where('wallet_key', 'default')
            ->where('asset', $currency);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /**
     * @return Collection<int, CmsUser>
     */
    private function sameCurrencyChildren(CmsUser $rootUser, string $currency, bool $lockForUpdate = false): Collection
    {
        $query = CmsUser::query()
            ->where('parent_id', $rootUser->id)
            ->where('currency', $currency)
            ->orderBy('id');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->get();
    }
}
