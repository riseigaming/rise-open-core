<?php

namespace App\Services\ExternalGames;

use App\Models\CmsUser;
use App\Models\ExternalGameUserRiskLimitOverride;
use App\Models\ExternalGameUserRiskLimitOverrideAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ExternalGameUserRiskLimitOverrideService
{
    private const LIMIT_KEYS = [
        'max_single_bet',
        'rolling_24h_stake_limit',
        'rolling_24h_net_loss_limit',
        'max_open_external_sessions',
    ];

    public function available(): bool
    {
        return Schema::hasTable('external_game_user_risk_limit_overrides');
    }

    public function activeFor(CmsUser $user): ?ExternalGameUserRiskLimitOverride
    {
        if (!$this->available()) {
            return null;
        }

        return ExternalGameUserRiskLimitOverride::query()
            ->where('cms_user_id', $user->id)
            ->where('is_active', true)
            ->whereNull('revoked_at')
            ->where(function ($window): void {
                $window->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($window): void {
                $window->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latest('id')
            ->first();
    }

    public function latestFor(CmsUser $user): ?ExternalGameUserRiskLimitOverride
    {
        if (!$this->available()) {
            return null;
        }

        return ExternalGameUserRiskLimitOverride::query()
            ->where('cms_user_id', $user->id)
            ->latest('id')
            ->first();
    }

    public function upsert(CmsUser $user, array $limits, string $reason, ?CmsUser $actor = null, ?string $expiresAt = null): ExternalGameUserRiskLimitOverride
    {
        $this->assertAvailable();

        $reason = trim($reason);
        if ($reason === '') {
            throw new \InvalidArgumentException('Reason is required.');
        }

        $normalized = $this->normalizeLimits($limits);
        $old = $this->activeFor($user);
        $oldSnapshot = $old ? $this->snapshot($old) : null;

        return DB::transaction(function () use ($user, $normalized, $reason, $actor, $expiresAt, $old, $oldSnapshot): ExternalGameUserRiskLimitOverride {
            if ($old) {
                $old->forceFill([
                    'is_active' => false,
                    'revoked_at' => now(),
                    'revoked_by' => $actor?->id,
                    'revoke_reason' => 'replaced_by_new_override',
                    'updated_by' => $actor?->id,
                ])->save();
            }

            $override = ExternalGameUserRiskLimitOverride::query()->create($normalized + [
                'cms_user_id' => $user->id,
                'starts_at' => null,
                'expires_at' => $expiresAt ?: null,
                'reason' => $reason,
                'is_active' => true,
                'created_by' => $actor?->id,
                'updated_by' => $actor?->id,
            ]);

            $this->audit($override, $old ? 'updated' : 'created', $oldSnapshot, $this->snapshot($override), $reason, $actor);

            return $override;
        });
    }

    public function revoke(CmsUser $user, string $reason, ?CmsUser $actor = null): ?ExternalGameUserRiskLimitOverride
    {
        $this->assertAvailable();

        $reason = trim($reason);
        if ($reason === '') {
            throw new \InvalidArgumentException('Reason is required.');
        }

        $override = $this->activeFor($user);
        if (!$override) {
            return null;
        }

        return DB::transaction(function () use ($override, $reason, $actor): ExternalGameUserRiskLimitOverride {
            $oldSnapshot = $this->snapshot($override);
            $override->forceFill([
                'is_active' => false,
                'revoked_at' => now(),
                'revoked_by' => $actor?->id,
                'revoke_reason' => $reason,
                'updated_by' => $actor?->id,
            ])->save();

            $this->audit($override, 'revoked', $oldSnapshot, null, $reason, $actor);

            return $override;
        });
    }

    public function snapshot(ExternalGameUserRiskLimitOverride $override): array
    {
        return [
            'max_single_bet' => $override->max_single_bet,
            'rolling_24h_stake_limit' => $override->rolling_24h_stake_limit,
            'rolling_24h_net_loss_limit' => $override->rolling_24h_net_loss_limit,
            'max_open_external_sessions' => $override->max_open_external_sessions,
            'starts_at' => optional($override->starts_at)->toDateTimeString(),
            'expires_at' => optional($override->expires_at)->toDateTimeString(),
            'reason' => $override->reason,
            'is_active' => (bool) $override->is_active,
            'updated_by' => $override->updated_by,
            'revoked_at' => optional($override->revoked_at)->toDateTimeString(),
            'revoke_reason' => $override->revoke_reason,
        ];
    }

    private function normalizeLimits(array $limits): array
    {
        $normalized = [];
        foreach (self::LIMIT_KEYS as $key) {
            $value = $limits[$key] ?? null;
            if ($value === '' || $value === null) {
                $normalized[$key] = null;
                continue;
            }

            if (!is_numeric($value) || !is_finite((float) $value) || (float) $value <= 0) {
                throw new \InvalidArgumentException("{$key} must be a positive finite restrictive limit.");
            }

            $normalized[$key] = $key === 'max_open_external_sessions'
                ? (int) $value
                : round((float) $value, 8);
        }

        return $normalized;
    }

    private function audit(
        ExternalGameUserRiskLimitOverride $override,
        string $action,
        ?array $oldLimits,
        ?array $newLimits,
        string $reason,
        ?CmsUser $actor
    ): void {
        if (!Schema::hasTable('external_game_user_risk_limit_override_audits')) {
            return;
        }

        ExternalGameUserRiskLimitOverrideAudit::query()->create([
            'override_id' => $override->id,
            'cms_user_id' => $override->cms_user_id,
            'action' => $action,
            'old_limits' => $oldLimits,
            'new_limits' => $newLimits,
            'reason' => $reason,
            'expires_at' => $override->expires_at,
            'actor_id' => $actor?->id,
        ]);
    }

    private function assertAvailable(): void
    {
        if (!$this->available()) {
            throw new \RuntimeException('External Games risk-limit override table is unavailable.');
        }
    }
}
