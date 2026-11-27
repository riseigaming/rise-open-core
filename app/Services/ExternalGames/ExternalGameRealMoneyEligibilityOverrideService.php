<?php

namespace App\Services\ExternalGames;

use App\Models\CmsUser;
use App\Models\ExternalGameRealMoneyEligibilityOverride;
use App\Models\ExternalGameRealMoneyEligibilityOverrideAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ExternalGameRealMoneyEligibilityOverrideService
{
    public function available(): bool
    {
        return Schema::hasTable('external_game_real_money_eligibility_overrides');
    }

    public function activeFor(CmsUser $user): ?ExternalGameRealMoneyEligibilityOverride
    {
        if (!$this->available()) {
            return null;
        }

        return ExternalGameRealMoneyEligibilityOverride::query()
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

    public function latestFor(CmsUser $user): ?ExternalGameRealMoneyEligibilityOverride
    {
        if (!$this->available()) {
            return null;
        }

        return ExternalGameRealMoneyEligibilityOverride::query()
            ->where('cms_user_id', $user->id)
            ->latest('id')
            ->first();
    }

    public function upsert(CmsUser $user, string $reason, ?CmsUser $actor = null, ?string $expiresAt = null): ExternalGameRealMoneyEligibilityOverride
    {
        $this->assertAvailable();

        $reason = trim($reason);
        if ($reason === '') {
            throw new \InvalidArgumentException('Reason is required.');
        }

        $old = $this->activeFor($user);
        $oldSnapshot = $old ? $this->snapshot($old) : null;

        return DB::transaction(function () use ($user, $reason, $actor, $expiresAt, $old, $oldSnapshot): ExternalGameRealMoneyEligibilityOverride {
            if ($old) {
                $old->forceFill([
                    'is_active' => false,
                    'revoked_at' => now(),
                    'revoked_by' => $actor?->id,
                    'revoke_reason' => 'replaced_by_new_override',
                    'updated_by' => $actor?->id,
                ])->save();
            }

            $override = ExternalGameRealMoneyEligibilityOverride::query()->create([
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

    public function revoke(CmsUser $user, string $reason, ?CmsUser $actor = null): ?ExternalGameRealMoneyEligibilityOverride
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

        return DB::transaction(function () use ($override, $reason, $actor): ExternalGameRealMoneyEligibilityOverride {
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

    public function snapshot(ExternalGameRealMoneyEligibilityOverride $override): array
    {
        return [
            'starts_at' => optional($override->starts_at)->toDateTimeString(),
            'expires_at' => optional($override->expires_at)->toDateTimeString(),
            'reason' => $override->reason,
            'is_active' => (bool) $override->is_active,
            'updated_by' => $override->updated_by,
            'revoked_at' => optional($override->revoked_at)->toDateTimeString(),
            'revoke_reason' => $override->revoke_reason,
        ];
    }

    private function audit(
        ExternalGameRealMoneyEligibilityOverride $override,
        string $action,
        ?array $oldValues,
        ?array $newValues,
        string $reason,
        ?CmsUser $actor
    ): void {
        if (!Schema::hasTable('external_game_real_money_eligibility_override_audits')) {
            return;
        }

        ExternalGameRealMoneyEligibilityOverrideAudit::query()->create([
            'override_id' => $override->id,
            'cms_user_id' => $override->cms_user_id,
            'action' => $action,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'reason' => $reason,
            'expires_at' => $override->expires_at,
            'actor_id' => $actor?->id,
        ]);
    }

    private function assertAvailable(): void
    {
        if (!$this->available()) {
            throw new \RuntimeException('External Games real-money eligibility override table is unavailable.');
        }
    }
}
