<?php

namespace App\Services\ExternalGames;

use App\Models\CmsUser;
use App\SiteSettings;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;

class ExternalGamesControlSettingsService
{
    public const POLICY_DEFAULT_OPEN = 'default_open';
    public const POLICY_MANUAL_NEW_USERS = 'manual_approval_for_new_users';
    public const POLICY_PILOT_COHORT_ONLY = 'pilot_cohort_only';

    public const POLICIES = [
        self::POLICY_DEFAULT_OPEN,
        self::POLICY_MANUAL_NEW_USERS,
        self::POLICY_PILOT_COHORT_ONLY,
    ];

    public function settings(): array
    {
        return $this->normalize($this->stored());
    }

    public function persist(array $payload, ?CmsUser $actor = null): array
    {
        if (!Schema::hasTable('site_settings')) {
            return $this->settings();
        }

        $stored = $this->stored();
        $previous = $this->settings();
        $policy = $this->normalizePolicy((string) ($payload['pilot_policy'] ?? $previous['pilot_policy']));
        $policyEnabledAt = (string) ($previous['pilot_policy_enabled_at'] ?? '');

        if ($policy !== (string) ($previous['pilot_policy'] ?? self::POLICY_DEFAULT_OPEN) || $policyEnabledAt === '') {
            $policyEnabledAt = now()->toDateTimeString();
        }

        $settings = [
            'enabled' => (bool) ($payload['enabled'] ?? false),
            'emergency_stop' => (bool) ($payload['emergency_stop'] ?? false),
            'pilot_policy' => $policy,
            'pilot_policy_enabled_at' => $policyEnabledAt,
            'demo_enabled' => (bool) ($payload['demo_enabled'] ?? false),
            'updated_at' => now()->toDateTimeString(),
            'updated_by' => $actor?->id,
        ];

        if (is_array($stored['risk_limits'] ?? null)) {
            $settings['risk_limits'] = $this->normalizeRiskLimits((array) $stored['risk_limits']);
        }

        SiteSettings::query()->updateOrCreate(
            ['key' => $this->settingsKey()],
            ['value' => json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]
        );

        Session::forget('settings');

        return $settings;
    }

    public function demoSupported(string $providerAlias = 'primary_external_provider'): bool
    {
        return (bool) config("external_games.providers.{$providerAlias}.demo_supported", false);
    }

    public function policies(): array
    {
        return [
            self::POLICY_DEFAULT_OPEN => __('admin.external_games.default_open'),
            self::POLICY_MANUAL_NEW_USERS => __('admin.external_games.manual_new_users'),
            self::POLICY_PILOT_COHORT_ONLY => __('admin.external_games.pilot_cohort_only'),
        ];
    }

    private function settingsKey(): string
    {
        return (string) config('external_games.controls_settings_key', 'external_games_controls_v1');
    }

    private function stored(): array
    {
        if (!Schema::hasTable('site_settings')) {
            return [];
        }

        $raw = (string) SiteSettings::query()
            ->where('key', $this->settingsKey())
            ->value('value');

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function normalize(array $settings): array
    {
        $policy = $this->normalizePolicy((string) ($settings['pilot_policy'] ?? self::POLICY_DEFAULT_OPEN));

        return [
            'enabled' => (bool) ($settings['enabled'] ?? false),
            'emergency_stop' => (bool) ($settings['emergency_stop'] ?? false),
            'pilot_policy' => $policy,
            'pilot_policy_enabled_at' => (string) ($settings['pilot_policy_enabled_at'] ?? ''),
            'demo_enabled' => (bool) ($settings['demo_enabled'] ?? false),
            'updated_at' => $settings['updated_at'] ?? null,
            'updated_by' => $settings['updated_by'] ?? null,
            'risk_limits' => $this->normalizeRiskLimits((array) ($settings['risk_limits'] ?? [])),
        ];
    }

    private function normalizePolicy(string $policy): string
    {
        return in_array($policy, self::POLICIES, true)
            ? $policy
            : self::POLICY_DEFAULT_OPEN;
    }

    private function normalizeRiskLimits(array $settings): array
    {
        $defaults = (array) config('external_games.risk_limits', []);
        $securityCeiling = max(0, (int) ($settings['max_open_external_sessions'] ?? $defaults['max_open_external_sessions'] ?? 100));

        return [
            'enabled' => (bool) ($settings['enabled'] ?? $defaults['enabled'] ?? true),
            'max_single_bet' => max(0.0, (float) ($settings['max_single_bet'] ?? $defaults['max_single_bet'] ?? 0)),
            'rolling_24h_stake_limit' => max(0.0, (float) ($settings['rolling_24h_stake_limit'] ?? $defaults['rolling_24h_stake_limit'] ?? 0)),
            'rolling_24h_net_loss_limit' => max(0.0, (float) ($settings['rolling_24h_net_loss_limit'] ?? $defaults['rolling_24h_net_loss_limit'] ?? 0)),
            'provider_rolling_24h_exposure_limit' => max(0.0, (float) ($settings['provider_rolling_24h_exposure_limit'] ?? $defaults['provider_rolling_24h_exposure_limit'] ?? 0)),
            'max_open_external_sessions' => $securityCeiling > 0 ? max(100, $securityCeiling) : 0,
            'debit_burst_window_seconds' => max(0, (int) ($settings['debit_burst_window_seconds'] ?? $defaults['debit_burst_window_seconds'] ?? 0)),
            'max_debits_per_burst_window' => max(0, (int) ($settings['max_debits_per_burst_window'] ?? $defaults['max_debits_per_burst_window'] ?? 0)),
            'max_debit_amount_per_burst_window' => max(0.0, (float) ($settings['max_debit_amount_per_burst_window'] ?? $defaults['max_debit_amount_per_burst_window'] ?? 0)),
        ];
    }
}
