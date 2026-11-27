<?php

namespace App\Services\ExternalGames;

use App\Enum\TransactionStatusEnum;
use App\Models\CmsUser;
use App\Models\CmsUserTransaction;
use App\Models\ExternalGameSession;
use App\Models\WalletLedgerEntry;
use App\Models\ExternalGameUserRiskLimitOverride;
use App\Services\Wallet\SpendableWalletContext;

use App\Services\RiskLimits\CanonicalRiskValueService;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class ExternalGameRiskLimitService
{
    public function __construct(
        private readonly ExternalGamesControlSettingsService $controls,
        private readonly ExternalGameUserRiskLimitOverrideService $userOverrides,
        private readonly CanonicalRiskValueService $fx,
        private readonly ExternalGameRiskProfile $profile
    )
    {
    }

    /**
     * @return array{reason:string,message:string,summary:array}|null
     */
    public function launchGuard(CmsUser $user, string $providerAlias, ?string $providerGameId = null, ?int $rootUserId = null): ?array
    {
        $owner = $rootUserId ? (CmsUser::find($rootUserId) ?: $user) : $user;
        $limits = $this->resolvedLimits($owner)['limits'];
        $maximum = (int) ($limits['max_open_external_sessions'] ?? 100);
        if ($maximum <= 0) return null;
        // Legacy low values are NOT customer concurrency limits. Count only a
        // very high burst of fresh real launches, under the canonical owner lock.
        // Failed attempts count; stale sessions, expiry and browser heartbeats do not.
        $maximum = max(100, $maximum);
        $open = ExternalGameSession::query()
            ->where('root_user_id', $rootUserId ?? $user->id)
            ->where('mode', 'real')
            ->where('created_at', '>', now()->subMinute())->count();

        return $open >= $maximum
            ? $this->blocked('launch_security_burst', trans('game_access_controls.session_limit'), [
                'fresh_launches' => $open, 'limit' => $maximum, 'window_seconds' => 60,
            ]) : null;
    }

    /**
     * @return array{reason:string,message:string,summary:array}|null
     */
    public function debitGuard(ExternalGameSession $session, SpendableWalletContext $walletContext, float $amount): ?array
    {
        if ($amount > 0 && ($walletContext->walletType() !== 'primary_balance'
            || !in_array(strtoupper($walletContext->currency()), (array) config('product_wallets.gameplay_assets', ['TRY', 'USD', 'EUR']), true))) {
            return $this->blocked('unsupported_gameplay_wallet', 'This wallet is funding-only.', []);
        }
        $limits = $this->resolvedLimits($walletContext->rootUser())['limits'];
        if (!$this->enabled($limits) || $amount <= 0 || !Schema::hasTable('cms_user_transactions')) {
            return null;
        }

        $amount = round($amount, 8);
        try {
            $canonicalAmount = $this->fx->toCanonical($amount, $walletContext->currency());
        } catch (RuntimeException $exception) {
            return $this->blocked('canonical_fx_unavailable', 'External Games risk value cannot be calculated safely.', [
                'currency' => strtoupper($walletContext->currency()),
            ]);
        }
        $maxSingleBet = (float) ($limits['max_single_bet'] ?? 0);
        if ($maxSingleBet > 0 && $canonicalAmount > round($maxSingleBet, 8)) {
            return $this->blocked('max_single_bet', 'External Games bet limit reached.', [
                'amount_try_equivalent' => $canonicalAmount,
                'limit' => $maxSingleBet,
            ]);
        }

        $userId = $walletContext->ownerWalletId();
        $since = now()->subDay();
        try {
            $betTotal = $this->sumTransactions($userId, ['external_games_transaction_bet_debit'], $since);
            $winRefundTotal = $this->sumTransactions($userId, [
                'external_games_transaction_win_credit',
                'external_games_refund_credit',
            ], $since);
        } catch (RuntimeException $exception) {
            return $this->blocked('canonical_fx_unavailable', 'External Games rolling risk cannot be calculated safely.', [
                'currency' => strtoupper($walletContext->currency()),
            ]);
        }

        $stakeLimit = (float) ($limits['rolling_24h_stake_limit'] ?? 0);
        if ($stakeLimit > 0 && round($betTotal + $canonicalAmount, 8) > round($stakeLimit, 8)) {
            return $this->blocked('rolling_24h_stake_limit', 'External Games daily stake limit reached.', [
                'current_24h_stake' => round($betTotal, 8),
                'attempted_amount_try_equivalent' => $canonicalAmount,
                'limit' => $stakeLimit,
            ]);
        }

        $netLossLimit = (float) ($limits['rolling_24h_net_loss_limit'] ?? 0);
        $currentNetLoss = max(0.0, round($betTotal - $winRefundTotal, 8));
        // Apply the next debit before clamping: existing net wins must offset it.
        // This is cash outflow, not evidence that open rounds have settled as losses.
        $projectedOutflow = max(0.0, round($betTotal + $canonicalAmount - $winRefundTotal, 8));
        if ($netLossLimit > 0 && $projectedOutflow > round($netLossLimit, 8)) {
            return $this->blocked('rolling_24h_net_loss_limit', 'External Games net game outflow limit reached.', [
                'current_24h_net_loss' => $currentNetLoss,
                'projected_24h_net_outflow' => $projectedOutflow,
                'attempted_amount_try_equivalent' => $canonicalAmount,
                'limit' => $netLossLimit,
            ]);
        }

        $providerExposureLimit = (float) ($limits['provider_rolling_24h_exposure_limit'] ?? 0);
        if ($providerExposureLimit > 0) {
            $providerExposure = $this->providerExposure((int) $session->provider_id, $since);
            if (round($providerExposure + $canonicalAmount, 8) > round($providerExposureLimit, 8)) {
                return $this->blocked('provider_rolling_24h_exposure_limit', 'External Games provider exposure limit reached.', [
                    'current_24h_provider_exposure' => round($providerExposure, 8),
                    'attempted_amount_try_equivalent' => $canonicalAmount,
                    'limit' => $providerExposureLimit,
                ]);
            }
        }

        return null;
    }

    public function resolvedLimits(?CmsUser $user = null): array
    {
        $base = $this->baseLimits();
        $source = $this->siteSettingsHaveRiskLimits() ? 'site_setting' : 'default';
        $override = $user ? $this->userOverrides->activeFor($user) : null;
        $latestOverride = $user ? $this->userOverrides->latestFor($user) : null;

        if ($override instanceof ExternalGameUserRiskLimitOverride) {
            foreach ([
                'max_single_bet',
                'rolling_24h_stake_limit',
                'rolling_24h_net_loss_limit',
            ] as $key) {
                if ($override->{$key} !== null) {
                    $overrideValue = max(0.0, (float) $override->{$key});
                    $baseValue = max(0.0, (float) ($base[$key] ?? 0));
                    $base[$key] = $baseValue > 0 ? min($baseValue, $overrideValue) : $overrideValue;
                }
            }

            $source = 'user_override';
        }

        return [
            'limits' => $base,
            'source' => $source,
            'override' => $override,
            'latest_override' => $latestOverride,
            'expiry' => $override?->expires_at,
            'explanation' => $this->explanation($source, $override),
            'override_table_available' => $this->userOverrides->available(),
        ];
    }

    /** @return array{stake_try:float,credits_try:float,net_loss_try:float} */
    public function rollingTotalsForUser(int $cmsUserId, ?\DateTimeInterface $since = null): array
    {
        $since ??= now()->subDay();
        $stake = $this->sumTransactions($cmsUserId, ['external_games_transaction_bet_debit'], $since);
        $credits = $this->sumTransactions($cmsUserId, [
            'external_games_transaction_win_credit',
            'external_games_refund_credit',
        ], $since);

        return [
            'stake_try' => $stake,
            'credits_try' => $credits,
            'net_loss_try' => max(0.0, round($stake - $credits, 8)),
        ];
    }

    private function baseLimits(): array
    {
        $compatibility = (array) ($this->controls->settings()['risk_limits'] ?? config('external_games.risk_limits', []));
        $canonical = $this->profile->externalLimits();

        return $canonical === [] ? $compatibility : array_merge($compatibility, $canonical);
    }

    private function siteSettingsHaveRiskLimits(): bool
    {
        if (!Schema::hasTable('site_settings')) {
            return false;
        }

        $raw = (string) \App\SiteSettings::query()
            ->where('key', (string) config('external_games.controls_settings_key', 'external_games_controls_v1'))
            ->value('value');
        $decoded = json_decode($raw, true);

        return is_array($decoded) && is_array($decoded['risk_limits'] ?? null);
    }

    private function explanation(string $source, ?ExternalGameUserRiskLimitOverride $override): string
    {
        if ($source === 'user_override' && $override) {
            $expiry = $override->expires_at ? ' until ' . $override->expires_at->toDateTimeString() : '';

            return 'User-specific External Games risk override is active' . $expiry . '.';
        }

        if ($source === 'site_setting') {
            return 'External Games risk limits are using site control settings.';
        }

        return 'External Games risk limits are using config defaults.';
    }

    private function enabled(array $limits): bool
    {
        return (bool) ($limits['enabled'] ?? true);
    }

    private function sumTransactions(int $cmsUserId, array $eventTypes, \DateTimeInterface $since): float
    {
        $total = 0.0;
        $rows = CmsUserTransaction::query()
            ->where('transactionId', 'like', 'external_games:%')
            ->where('status', TransactionStatusEnum::Success)
            ->whereIn('cms_user_id', $this->accountFamilyIds($cmsUserId))
            ->whereIn('event_type', $eventTypes)
            ->where('created_at', '>=', $since)
            ->select(['id', 'transactionId', 'amount', 'currency', 'source_currency'])
            ->lazyById(250);

        foreach ($rows as $row) {
            $currency = strtoupper((string) ($row->source_currency ?: $row->currency));
            $total += $this->historicalRiskAmount($row, $currency);
        }

        return round($total, 8);
    }

    private function accountFamilyIds(int $cmsUserId): array
    {
        $user = CmsUser::find($cmsUserId);
        $rootId = (int) ($user?->parent_id ?: $cmsUserId);

        return CmsUser::query()->whereKey($rootId)->orWhere('parent_id', $rootId)
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    private function providerExposure(int $providerId, \DateTimeInterface $since): float
    {
        $bets = $this->sumProviderTransactions($providerId, ['external_games_transaction_bet_debit'], $since);
        $credits = $this->sumProviderTransactions($providerId, ['external_games_transaction_win_credit', 'external_games_refund_credit'], $since);

        return max(0.0, round($bets - $credits, 8));
    }

    private function sumProviderTransactions(int $providerId, array $eventTypes, \DateTimeInterface $since): float
    {
        $total = 0.0;
        $rows = CmsUserTransaction::query()
            ->where('transactionId', 'like', 'external_games:%')
            ->where('status', TransactionStatusEnum::Success)
            ->where('game_provider_id', $providerId)
            ->whereIn('event_type', $eventTypes)
            ->where('created_at', '>=', $since)
            ->select(['id', 'transactionId', 'amount', 'currency', 'source_currency'])
            ->lazyById(250);

        foreach ($rows as $row) {
            $currency = strtoupper((string) ($row->source_currency ?: $row->currency));
            $total += $this->historicalRiskAmount($row, $currency);
        }

        return round($total, 8);
    }

    public function eventRiskSnapshot(float $amount, string $currency): ?array
    {
        try {
            return ['currency' => 'TRY', 'amount' => $this->fx->toCanonical($amount, $currency),
                'source_currency' => strtoupper($currency), 'source_amount' => $amount,
                'recorded_at' => now()->toIso8601String()];
        } catch (RuntimeException $exception) {
            // Missing FX must never prevent a legitimate provider credit.
            return null;
        }
    }

    private function historicalRiskAmount(CmsUserTransaction $row, string $currency): float
    {
        if ($currency === 'TRY') return (float) $row->amount;
        $entry = WalletLedgerEntry::where('source_type', 'cms_user_transaction')
            ->where('source_reference', $row->transactionId)->first();
        $snapshot = (array) data_get($entry?->meta, 'canonical_risk', []);
        if (($snapshot['currency'] ?? '') !== 'TRY' || ($snapshot['source_currency'] ?? '') !== $currency
            || !is_numeric($snapshot['amount'] ?? null) || (float) $snapshot['amount'] < 0
            || (float) ($snapshot['source_amount'] ?? -1) !== (float) $row->amount) {
            throw new RuntimeException('Event-time canonical risk evidence is unavailable.');
        }
        return (float) $snapshot['amount'];
    }

    private function blocked(string $reason, string $message, array $summary): array
    {
        return [
            'reason' => $reason,
            'message' => $message,
            'summary' => ['reason' => $reason] + $summary,
        ];
    }
}
