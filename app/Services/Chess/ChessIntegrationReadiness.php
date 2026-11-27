<?php

namespace App\Services\Chess;

use App\Models\ChessLiveBoard;
use App\Models\Provider;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;

class ChessIntegrationReadiness
{
    private array $memo = [];

    public function viewData(): array
    {
        $front = $this->frontState();
        $homepage = $this->homepageState();
        $guestEmbed = $this->guestEmbedState();
        $embedShell = $this->embedShellState();
        $admin = $this->adminState();
        $wallet = $this->walletState();

        return [
            'chessReadiness' => [
                'front' => $front,
                'homepage' => $homepage,
                'guest_embed' => $guestEmbed,
                'embed_shell' => $embedShell,
                'admin' => $admin,
                'wallet' => $wallet,
            ],
            'chessFrontReady' => (bool) $front['ready'],
            'chessHomepageReady' => (bool) $homepage['ready'],
            'chessGuestEmbedReady' => (bool) $guestEmbed['ready'],
            'chessEmbedShellReady' => (bool) $embedShell['ready'],
            'chessAdminReady' => (bool) $admin['ready'],
            'chessWalletReady' => (bool) $wallet['ready'],
        ];
    }

    public function frontState(): array
    {
        return $this->remember(__FUNCTION__, function () {
            $state = $this->baseState('front_enabled');

            if (!$state['enabled']) {
                return $this->fail($state, 'feature_disabled', 'Chess front visibility is disabled.');
            }

            if (!$this->requiredTablesReady(['providers'])) {
                return $this->fail($state, 'missing_tables', 'Required chess public front tables are missing.');
            }

            if (!$this->providerExists()) {
                return $this->fail($state, 'missing_provider', 'Chess provider row is missing.');
            }

            if (!$this->guestEmbedConfigured()) {
                return $this->fail($state, 'embed_not_ready', 'Chess public embed runtime configuration is incomplete.');
            }

            return $this->ready($state);
        });
    }

    public function homepageState(): array
    {
        return $this->remember(__FUNCTION__, function () {
            $state = $this->baseState('front_enabled');

            if (!$state['enabled']) {
                return $this->fail($state, 'feature_disabled', 'Chess homepage visibility is disabled.');
            }

            $baseUrl = trim((string) config('services.core_api.base_url', ''));
            if ($baseUrl === '') {
                return $this->fail($state, 'core_api_not_ready', 'Chess homepage feed base URL is missing.');
            }

            return $this->ready($state);
        });
    }

    public function embedShellState(): array
    {
        return $this->authenticatedEmbedState();
    }

    public function guestEmbedState(): array
    {
        return $this->remember(__FUNCTION__, function () {
            $state = $this->baseState('front_enabled');

            if (!$state['enabled']) {
                return $this->fail($state, 'feature_disabled', 'Chess guest embed visibility is disabled.');
            }

            if (!$this->requiredTablesReady(['providers'])) {
                return $this->fail($state, 'missing_tables', 'Required chess guest embed tables are missing.');
            }

            if (!$this->providerExists()) {
                return $this->fail($state, 'missing_provider', 'Chess provider row is missing.');
            }

            if (!$this->guestEmbedConfigured()) {
                return $this->fail($state, 'embed_not_ready', 'Chess guest embed runtime configuration is incomplete.');
            }

            return $this->ready($state);
        });
    }

    public function authenticatedEmbedState(): array
    {
        return $this->remember(__FUNCTION__, function () {
            $state = $this->baseState('front_enabled');

            if (!$state['enabled']) {
                return $this->fail($state, 'feature_disabled', 'Chess embed visibility is disabled.');
            }

            if (!$this->requiredTablesReady(['providers', 'chess_wallet_reservations'])) {
                return $this->fail($state, 'missing_tables', 'Required chess embed tables are missing.');
            }

            if (!$this->providerExists()) {
                return $this->fail($state, 'missing_provider', 'Chess provider row is missing.');
            }

            if (!$this->authenticatedEmbedConfigured()) {
                return $this->fail($state, 'embed_not_ready', 'Chess embed runtime configuration is incomplete.');
            }

            return $this->ready($state);
        });
    }

    public function adminState(): array
    {
        return $this->remember(__FUNCTION__, function () {
            $state = $this->baseState('admin_enabled');

            if (!$state['enabled']) {
                return $this->fail($state, 'feature_disabled', 'Chess admin cockpit is disabled.');
            }

            if (!$this->requiredTablesReady([
                'chess_live_events',
                'chess_live_boards',
                'chess_betting_markets',
                'chess_betting_market_action_logs',
                'chess_betting_market_selections',
            ])) {
                return $this->fail($state, 'missing_tables', 'Required chess cockpit tables are missing.');
            }

            if (!$this->sourceConfigured()) {
                return $this->fail($state, 'source_not_ready', 'Chess cockpit source mode is not configured.');
            }

            return $this->ready($state);
        });
    }

    public function walletState(?string $partnerCode = null): array
    {
        $cacheKey = __FUNCTION__ . ':' . ($partnerCode ?: '_default');

        return $this->remember($cacheKey, function () use ($partnerCode) {
            $state = $this->baseState('wallet_enabled');
            $partnerCode = $partnerCode ?: trim((string) data_get(config('chess_wallet.default_provider_config', []), 'embed.partner_code', ''));

            if (!$state['enabled']) {
                return $this->fail($state, 'feature_disabled', 'Chess wallet API is disabled.');
            }

            if (!$this->requiredTablesReady([
                'providers',
                'cms_users',
                'cms_user_transactions',
                'chess_wallet_reservations',
            ])) {
                return $this->fail($state, 'missing_tables', 'Required chess wallet tables are missing.');
            }

            if (!$this->providerExists()) {
                return $this->fail($state, 'missing_provider', 'Chess wallet provider row is missing.');
            }

            if (!$this->walletPartnerConfigured($partnerCode)) {
                return $this->fail($state, 'partner_not_ready', 'Chess wallet partner configuration is incomplete.');
            }

            return $this->ready($state);
        });
    }

    private function baseState(string $flagKey): array
    {
        return [
            'enabled' => (bool) config("chess_integration.{$flagKey}", false),
            'ready' => false,
            'code' => null,
            'message' => null,
        ];
    }

    private function ready(array $state): array
    {
        $state['ready'] = true;
        $state['code'] = 'ready';
        $state['message'] = null;

        return $state;
    }

    private function fail(array $state, string $code, string $message): array
    {
        $state['ready'] = false;
        $state['code'] = $code;
        $state['message'] = $message;

        return $state;
    }

    private function requiredTablesReady(array $tables): bool
    {
        foreach ($tables as $table) {
            if (!$this->hasTable($table)) {
                return false;
            }
        }

        return true;
    }

    private function hasTable(string $table): bool
    {
        return $this->remember('table:' . $table, function () use ($table) {
            try {
                return Schema::hasTable($table);
            } catch (\Throwable $exception) {
                return false;
            }
        });
    }

    private function providerExists(): bool
    {
        return $this->remember(__FUNCTION__, function () {
            if (!$this->hasTable('providers')) {
                return false;
            }

            try {
                return Provider::query()
                    ->where('slug', config('chess_wallet.provider_slug'))
                    ->exists();
            } catch (\Throwable $exception) {
                return false;
            }
        });
    }

    private function sourceConfigured(): bool
    {
        return $this->remember(__FUNCTION__, function () {
            $source = trim((string) config('chess_live_cockpit.default_source', ''));
            if ($source === '') {
                return false;
            }

            $sourceConfig = (array) config("chess_live_cockpit.sources.{$source}", []);
            if ($source === 'fake') {
                return filled($sourceConfig['adapter'] ?? null);
            }

            if ($source === 'http') {
                return trim((string) Arr::get($sourceConfig, 'base_url', '')) !== '';
            }

            return !empty($sourceConfig);
        });
    }

    private function guestEmbedConfigured(): bool
    {
        return $this->remember(__FUNCTION__, function () {
            $embedConfig = (array) data_get(config('chess_wallet.default_provider_config', []), 'embed', []);

            return trim((string) Arr::get($embedConfig, 'public_base_url', '')) !== '';
        });
    }

    private function authenticatedEmbedConfigured(): bool
    {
        return $this->remember(__FUNCTION__, function () {
            $embedConfig = (array) data_get(config('chess_wallet.default_provider_config', []), 'embed', []);

            return trim((string) Arr::get($embedConfig, 'public_base_url', '')) !== ''
                && trim((string) Arr::get($embedConfig, 'partner_code', '')) !== ''
                && trim((string) Arr::get($embedConfig, 'exchange_endpoint', '')) !== ''
                && trim((string) Arr::get($embedConfig, 'key_id', '')) !== ''
                && trim((string) Arr::get($embedConfig, 'secret', '')) !== '';
        });
    }

    private function walletPartnerConfigured(?string $partnerCode): bool
    {
        if (!filled($partnerCode)) {
            return false;
        }

        $partners = (array) config('chess_wallet.partner_overrides', []);
        $partnerConfig = (array) Arr::get($partners, $partnerCode, []);

        return trim((string) Arr::get($partnerConfig, 'secret', '')) !== '';
    }

    private function hasBoards(): bool
    {
        return $this->remember(__FUNCTION__, function () {
            if (!$this->hasTable('chess_live_boards')) {
                return false;
            }

            try {
                return ChessLiveBoard::query()->exists();
            } catch (\Throwable $exception) {
                return false;
            }
        });
    }

    private function remember(string $key, callable $resolver)
    {
        if (!array_key_exists($key, $this->memo)) {
            $this->memo[$key] = $resolver();
        }

        return $this->memo[$key];
    }
}
