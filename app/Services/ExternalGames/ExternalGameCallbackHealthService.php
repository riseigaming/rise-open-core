<?php

namespace App\Services\ExternalGames;

use App\SiteSettings;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;

class ExternalGameCallbackHealthService
{
    public function __construct(private string $providerAlias = ExternalGameProviderConfig::PRIMARY)
    {
    }

    public function forProvider(string $alias): self
    {
        return new self($alias);
    }

    private function settingsKey(): string
    {
        return ExternalGameProviderConfig::storageKey(
            (string) config('external_games.callback_health_settings_key', 'external_games_callback_health_v1'),
            $this->providerAlias
        );
    }

    public function summary(): array
    {
        $stored = $this->stored();

        if ($this->isExpired($stored)) {
            return $this->defaults();
        }

        return array_merge($this->defaults(), $stored);
    }

    public function recordSuccess(): void
    {
        $this->increment(['callbacks_24h']);
    }

    public function recordFailure(?string $message = null, array $failureSummary = []): void
    {
        $this->increment(['callbacks_24h', 'callback_failures_24h'], $message, $failureSummary);
    }

    public function recordIgnored(string $reason, array $summary = []): void
    {
        $this->increment(['callbacks_24h'], null, [], [
            'status' => 'ignored',
            'reason' => $reason,
            'summary' => $summary,
        ]);
    }

    public function recordReplay(): void
    {
        $this->increment(['callbacks_24h', 'idempotent_replays_24h']);
    }

    public function recordConflict(?string $message = null): void
    {
        $this->increment(['callbacks_24h', 'conflicts_24h'], $message);
    }

    public function recordLimitRejected(string $reason, array $summary = []): void
    {
        $this->increment(
            ['callbacks_24h', 'callback_failures_24h', 'risk_limit_rejections_24h'],
            'External Games risk limit rejected callback: ' . $reason,
            array_merge(['reason' => $reason], $summary)
        );
    }

    private function increment(array $keys, ?string $message = null, array $failureSummary = [], array $callbackSummary = []): void
    {
        try {
            if (!Schema::hasTable('site_settings')) {
                return;
            }

            $summary = $this->summary();
            $summary['window_started_at'] = $summary['window_started_at'] ?: now()->toDateTimeString();

            foreach ($keys as $key) {
                $summary[$key] = (int) ($summary[$key] ?? 0) + 1;
            }

            if ($message !== null && $message !== '') {
                $summary['last_critical_error'] = mb_substr($message, 0, 500);
            }

            if ($failureSummary !== []) {
                $summary['last_failure_summary'] = $this->redactedSummary($failureSummary);
            }

            if ($callbackSummary !== []) {
                $summary['last_callback_summary'] = $this->redactedSummary($callbackSummary);
            }

            $summary['updated_at'] = now()->toDateTimeString();

            SiteSettings::query()->updateOrCreate(
                ['key' => $this->settingsKey()],
                ['value' => json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]
            );

            Session::forget('settings');
        } catch (\Throwable $throwable) {
            report($throwable);
        }
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

    private function isExpired(array $summary): bool
    {
        $startedAt = trim((string) ($summary['window_started_at'] ?? ''));

        return $startedAt === '' || Carbon::parse($startedAt)->lt(now()->subDay());
    }

    private function defaults(): array
    {
        return [
            'window_started_at' => now()->toDateTimeString(),
            'callbacks_24h' => 0,
            'callback_failures_24h' => 0,
            'idempotent_replays_24h' => 0,
            'conflicts_24h' => 0,
            'risk_limit_rejections_24h' => 0,
            'last_critical_error' => null,
            'last_failure_summary' => null,
            'last_callback_summary' => null,
            'updated_at' => null,
        ];
    }

    private function redactedSummary(array $summary): array
    {
        return collect($summary)
            ->mapWithKeys(function ($value, string $key): array {
                $normalized = strtolower($key);
                if (str_contains($normalized, 'secret') || str_contains($normalized, 'token') || $normalized === 'authorization' || $normalized === 'x-authorization') {
                    return [$key => '[redacted]'];
                }

                if (is_array($value)) {
                    return [$key => $this->redactedSummary($value)];
                }

                return [$key => $value];
            })
            ->all();
    }
}
