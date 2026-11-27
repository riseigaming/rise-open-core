<?php

namespace App\Services\ChessWallet;

use App\Models\CmsUser;
use App\Services\Localization\ProductLocaleResolver;
use App\Support\LocalEgress;
use App\Services\Wallet\ActiveWalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ChessEmbedService
{
    public function __construct(
        private ChessWalletProviderResolver $providerResolver,
        private ChessEmbedDebugRecorder $debugRecorder,
        private ActiveWalletService $activeWallets,
        private ProductLocaleResolver $productLocales,
    )
    {
    }

    public function buildShell(CmsUser $user, Request $request): array
    {
        $provider = $this->providerResolver->findProvider();
        $providerConfig = $this->providerResolver->resolveProviderConfig($provider);
        
        return $this->buildShellFromConfig($user, $request, $providerConfig, $provider);
    }

    public function buildGuestShell(Request $request): array
    {
        $provider = $this->providerResolver->findProvider();
        $providerConfig = $this->providerResolver->resolveProviderConfig($provider);
        $embedConfig = $this->normalizeLocalEmbedConfig($request, (array) data_get($providerConfig, 'embed', []));
        $localeResolution = $this->resolveProductLocale($embedConfig);
        $partnerCode = $this->resolvePartnerCode($providerConfig, $embedConfig);
        $parentOrigin = $this->resolveParentOrigin($request, $embedConfig);
        $iframeSrc = $this->buildGuestEntryUrl(
            $embedConfig,
            $localeResolution['provider_locale'],
            $request->query('redirect')
        );
        $postMessageOrigin = $this->resolvePostMessageOrigin($embedConfig, $iframeSrc);

        return [
            'pageTitle' => 'Chess',
            'provider' => $provider,
            'providerConfig' => $providerConfig,
            'embedConfig' => $embedConfig,
            'partnerCode' => $partnerCode,
            'bootstrapPayload' => [
                'allowedSurfaceType' => strtoupper((string) data_get($embedConfig, 'allowed_surface_type', 'PUBLIC')),
                'sourceOfTruth' => 'cms_guest',
                'themeHints' => [
                    'locale' => $localeResolution['provider_locale'],
                ],
            ],
            'bootstrapToken' => null,
            'bootstrapError' => $iframeSrc ? null : 'Chess public embed base URL is not configured or blocked by local safety policy.',
            'bridgeToken' => null,
            'bridgeExchangeStatus' => 'guest',
            'bridgeExchangeMessage' => 'Guest shell opened without wallet bootstrap.',
            'bridgeExpiresAt' => null,
            'iframeSrc' => $iframeSrc,
            'openInNewTabUrl' => $iframeSrc,
            'postMessageOrigin' => $postMessageOrigin,
            'parentOrigin' => $parentOrigin,
            'embedRequestId' => null,
            'externalSessionRef' => $request->session()->getId() ?: null,
            'walletContextRef' => null,
            'iframeTitle' => 'Chess Betting Surface',
            'guestMode' => true,
            'localeResolution' => $localeResolution,
        ];
    }

    public function buildShellFromConfig(CmsUser $user, Request $request, array $providerConfig, $provider = null): array
    {
        app(\App\Services\Wallet\WalletPlayabilityService::class)->assertForUser('chess', $user);
        $embedConfig = $this->normalizeLocalEmbedConfig($request, (array) data_get($providerConfig, 'embed', []));
        $localeResolution = $this->resolveProductLocale($embedConfig);
        $partnerCode = $this->resolvePartnerCode($providerConfig, $embedConfig);
        $bootstrapPayload = $this->buildBootstrapPayload($user, $request, $partnerCode, $embedConfig, $localeResolution);
        $bootstrapSecret = $this->resolveBootstrapSecret($providerConfig, $embedConfig, $partnerCode);
        $bootstrapToken = $bootstrapSecret !== '' ? $this->encodeBootstrapToken($bootstrapPayload, $bootstrapSecret) : null;
        $debugContext = $this->debugContext(
            $user,
            $request,
            $embedConfig,
            $partnerCode,
            $bootstrapPayload,
            $localeResolution
        );
        $this->debugRecorder->record($bootstrapToken ? 'embed.bootstrap.generated' : 'embed.bootstrap.failed', $debugContext + [
            'bootstrap_generated' => $bootstrapToken !== null,
        ]);

        $exchange = $this->exchangePartnerSession($embedConfig, $bootstrapPayload, $debugContext);
        $parentOrigin = $this->resolveParentOrigin($request, $embedConfig);
        $iframeSrc = $this->buildConsumeUrl($embedConfig, $exchange['sessionToken'] ?? null, $parentOrigin, $request->query('redirect'));
        $openInNewTabUrl = $iframeSrc;
        $postMessageOrigin = $this->resolvePostMessageOrigin($embedConfig, $iframeSrc);

        $this->debugRecorder->record('embed.iframe.src_built', $debugContext + [
            'parent_origin' => $parentOrigin,
            'post_message_origin' => $postMessageOrigin,
            'iframe_src' => $iframeSrc,
            'consume_url' => $iframeSrc,
            'bridge_status' => $exchange['status'] ?? null,
            'bridge_token' => $exchange['sessionToken'] ?? null,
            'requested_hostname' => data_get($embedConfig, 'requested_hostname'),
        ]);

        Log::info('chess.embed.shell.prepared', [
            'partner_code' => $partnerCode,
            'request_id' => $bootstrapPayload['requestId'] ?? null,
            'bridge_status' => $exchange['status'] ?? null,
            'iframe_src_ready' => $iframeSrc !== null,
        ]);

        return [
            'pageTitle' => 'Chess',
            'provider' => $provider,
            'providerConfig' => $providerConfig,
            'embedConfig' => $embedConfig,
            'partnerCode' => $partnerCode,
            'bootstrapPayload' => $bootstrapPayload,
            'bootstrapToken' => $bootstrapToken,
            'bootstrapError' => $this->resolveBootstrapError($embedConfig, $exchange, $iframeSrc),
            'bridgeToken' => $exchange['sessionToken'] ?? null,
            'bridgeExchangeStatus' => $exchange['status'],
            'bridgeExchangeMessage' => $exchange['message'],
            'bridgeExpiresAt' => $exchange['expiresAt'] ?? null,
            'iframeSrc' => $iframeSrc,
            'openInNewTabUrl' => $openInNewTabUrl,
            'postMessageOrigin' => $postMessageOrigin,
            'parentOrigin' => $parentOrigin,
            'embedRequestId' => $bootstrapPayload['requestId'] ?? null,
            'externalSessionRef' => $bootstrapPayload['externalSessionRef'] ?? null,
            'walletContextRef' => $bootstrapPayload['walletContextRef'] ?? null,
            'iframeTitle' => 'Chess Betting Surface',
            'localeResolution' => $localeResolution,
        ];
    }

    private function normalizeLocalEmbedConfig(Request $request, array $embedConfig): array
    {
        if (!app()->environment('local')) {
            return $embedConfig;
        }

        $requestHost = trim((string) $request->getHost());
        if (!in_array($requestHost, ['localhost', '127.0.0.1'], true)) {
            return $embedConfig;
        }

        $publicBaseUrl = trim((string) data_get($embedConfig, 'public_base_url', ''));
        if ($publicBaseUrl === '') {
            return $embedConfig;
        }

        $embedHost = (string) parse_url($publicBaseUrl, PHP_URL_HOST);
        if (!in_array($embedHost, ['localhost', '127.0.0.1'], true) || $embedHost === $requestHost) {
            return $embedConfig;
        }

        $embedConfig['public_base_url'] = preg_replace(
            '#^(https?://)(localhost|127\.0\.0\.1)(:\d+)?#',
            '$1' . $requestHost . '$3',
            $publicBaseUrl
        ) ?: $publicBaseUrl;

        return $embedConfig;
    }

    private function buildBootstrapPayload(
        CmsUser $user,
        Request $request,
        string $partnerCode,
        array $embedConfig,
        array $localeResolution
    ): array
    {
        $now = now();
        $expiresAt = $now->copy()->addSeconds(max(30, (int) data_get($embedConfig, 'bootstrap_ttl_seconds', 120)));
        $externalUserId = $this->resolveExternalUserId($user);
        $displayName = trim((string) ($user->full_name ?? $user->username ?? $user->email ?? ('User ' . $user->id)));
        $email = $user->email ? (string) $user->email : null;
        $requestedSurfaceType = strtoupper((string) data_get($embedConfig, 'allowed_surface_type', 'PUBLIC'));
        $defaultRole = strtoupper((string) data_get($embedConfig, 'default_role', 'VIEWER'));
        $activeWallet = $this->activeWallets->resolveForUser($user);
        $walletContextRef = sprintf(
            'cms-user:%s:wallet:%s',
            $user->parent_id ?: $user->id,
            Str::lower((string) ($activeWallet?->asset ?: $user->currency ?: 'TRY'))
        );

        return [
            'partnerCode' => $partnerCode,
            'externalUserId' => $externalUserId,
            'externalSessionRef' => $request->session()->getId() ?: null,
            'displayName' => $displayName !== '' ? $displayName : null,
            'email' => $email,
            'allowedSurfaceType' => $requestedSurfaceType,
            'role' => $defaultRole,
            'scope' => ['public_betting', 'wallet_reserve'],
            'walletContextRef' => $walletContextRef,
            'walletAccountId' => $activeWallet?->id,
            'walletCurrency' => $activeWallet?->asset ?: $user->currency,
            'themeHints' => array_filter([
                'cmsTheme' => defined('activeTheme') ? activeTheme : null,
                'layoutMode' => data_get($embedConfig, 'layout_hints.mode'),
                'compactHeader' => data_get($embedConfig, 'layout_hints.compact_header'),
                'theme' => data_get($embedConfig, 'layout_hints.theme'),
                'locale' => $localeResolution['provider_locale'],
            ], static fn ($value) => $value !== null && $value !== ''),
            'requestTimestamp' => $now->toAtomString(),
            'expiresAt' => $expiresAt->toAtomString(),
            'requestId' => (string) Str::uuid(),
            'sourceOfTruth' => 'cms',
        ];
    }

    private function exchangePartnerSession(array $embedConfig, array $bootstrapPayload, array $debugContext = []): array
    {
        $exchangeEndpoint = trim((string) data_get($embedConfig, 'exchange_endpoint', ''));
        $keyId = trim((string) data_get($embedConfig, 'key_id', ''));
        $secret = trim((string) data_get($embedConfig, 'secret', ''));

        $this->debugRecorder->record('embed.exchange.requested', $debugContext + [
            'exchange_endpoint' => $exchangeEndpoint,
            'requested_hostname' => data_get($embedConfig, 'requested_hostname'),
            'requested_surface_type' => $bootstrapPayload['allowedSurfaceType'] ?? null,
        ]);

        if ($exchangeEndpoint === '' || $keyId === '' || $secret === '') {
            $this->debugRecorder->record('embed.exchange.failed', $debugContext + [
                'failure_reason' => 'incomplete_config',
                'exchange_endpoint' => $exchangeEndpoint,
            ]);

            return [
                'status' => 'skipped',
                'message' => 'Bridge exchange config is incomplete.',
            ];
        }

        if (!LocalEgress::assertUrlAllowed($exchangeEndpoint, 'chess_embed.exchange', [
            'feature' => 'chess_embed_exchange',
        ])) {
            $this->debugRecorder->record('embed.exchange.failed', $debugContext + [
                'failure_reason' => 'local_egress_blocked',
                'exchange_endpoint' => $exchangeEndpoint,
            ]);

            return [
                'status' => 'blocked',
                'message' => 'Bridge exchange endpoint is blocked by local safety policy.',
            ];
        }

        $payload = [
            'keyId' => $keyId,
            'secret' => $secret,
            'externalUserId' => (string) $bootstrapPayload['externalUserId'],
            'email' => $bootstrapPayload['email'] ?? null,
            'displayName' => $bootstrapPayload['displayName'] ?? null,
            'requestedSurfaceType' => (string) $bootstrapPayload['allowedSurfaceType'],
            'requestedRole' => (string) $bootstrapPayload['role'],
            'embedConfigCode' => data_get($embedConfig, 'embed_config_code') ?: null,
            'requestedHostname' => data_get($embedConfig, 'requested_hostname') ?: null,
            'locale' => (string) $bootstrapPayload['themeHints']['locale'],
        ];

        try {
            $response = Http::acceptJson()
                ->timeout(max(1, (int) ceil(((int) data_get($embedConfig, 'exchange_timeout_ms', 2000)) / 1000)))
                ->post($exchangeEndpoint, Arr::where($payload, static fn ($value) => $value !== null && $value !== ''));
        } catch (\Throwable $exception) {
            $this->debugRecorder->record('embed.exchange.failed', $debugContext + [
                'failure_reason' => 'http_exception',
                'error_class' => $exception::class,
                'detail' => $exception->getMessage(),
                'exchange_endpoint' => $exchangeEndpoint,
            ]);

            Log::warning('chess.embed.exchange.exception', [
                'reason_code' => 'http_exception',
                'exception_class' => $exception::class,
                'endpoint_host' => parse_url($exchangeEndpoint, PHP_URL_HOST),
            ]);

            return [
                'status' => 'failed',
                'message' => 'Bridge exchange request failed.',
            ];
        }

        if (!$response->successful()) {
            $this->debugRecorder->record('embed.exchange.failed', $debugContext + [
                'failure_reason' => 'rejected',
                'http_status' => $response->status(),
                'detail' => data_get($response->json(), 'message', 'Bridge exchange was rejected.'),
                'exchange_endpoint' => $exchangeEndpoint,
            ]);

            Log::warning('chess.embed.exchange.rejected', [
                'endpoint_host' => parse_url($exchangeEndpoint, PHP_URL_HOST),
                'request_id' => $bootstrapPayload['requestId'] ?? null,
                'http_status' => $response->status(),
                'reason_code' => 'provider_rejected',
            ]);

            return [
                'status' => 'failed',
                'message' => data_get($response->json(), 'message', 'Bridge exchange was rejected.'),
            ];
        }

        $this->debugRecorder->record('embed.exchange.succeeded', $debugContext + [
            'exchange_endpoint' => $exchangeEndpoint,
            'bridge_token' => data_get($response->json(), 'sessionToken'),
            'bridge_expires_at' => data_get($response->json(), 'expiresAt'),
            'requested_surface_type' => $bootstrapPayload['allowedSurfaceType'] ?? null,
        ]);

        Log::info('chess.embed.exchange.completed', [
            'endpoint_host' => parse_url($exchangeEndpoint, PHP_URL_HOST),
            'request_id' => $bootstrapPayload['requestId'] ?? null,
        ]);

        return [
            'status' => 'ok',
            'message' => data_get($response->json(), 'message', 'Bridge exchange completed.'),
            'sessionToken' => data_get($response->json(), 'sessionToken'),
            'expiresAt' => data_get($response->json(), 'expiresAt'),
        ];
    }

    private function resolveBootstrapError(array $embedConfig, array $exchange, ?string $iframeSrc): ?string
    {
        if (trim((string) data_get($embedConfig, 'public_base_url', '')) === '') {
            return 'Chess public embed base URL is not configured.';
        }

        if ($iframeSrc === null) {
            if (($exchange['status'] ?? null) === 'ok' && empty($exchange['sessionToken'])) {
                return 'Chess bridge token could not be issued.';
            }

            return 'Chess iframe URL could not be built.';
        }

        if (($exchange['status'] ?? null) === 'failed' || ($exchange['status'] ?? null) === 'blocked') {
            return (string) ($exchange['message'] ?? 'Bridge exchange failed.');
        }

        return null;
    }

    private function buildGuestEntryUrl(
        array $embedConfig,
        string $providerLocale,
        ?string $requestedRedirect = null
    ): ?string
    {
        $baseUrl = trim((string) data_get($embedConfig, 'public_base_url', ''));

        if (
            $baseUrl === ''
            || !LocalEgress::assertUrlAllowed($baseUrl, 'chess_embed.iframe', [
                'feature' => 'chess_embed_iframe',
            ])
        ) {
            return null;
        }

        $entryPath = '/' . ltrim((string) data_get($embedConfig, 'entry_path', '/'), '/');
        $url = rtrim($baseUrl, '/') . ($entryPath === '/' ? '/' : $entryPath);
        $query = Arr::where([
            'origin' => null,
            'redirect' => $this->resolveSafeRedirectPath($requestedRedirect),
            'mode' => 'guest',
            'locale' => $providerLocale,
        ], static fn ($value) => $value !== null && $value !== '');

        if ($query === []) {
            return $url;
        }

        return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
    }

    private function resolveBootstrapSecret(array $providerConfig, array $embedConfig, string $partnerCode): string
    {
        $secret = trim((string) data_get($embedConfig, 'bootstrap_secret', ''));
        if ($secret !== '') {
            return $secret;
        }

        $secret = trim((string) data_get($providerConfig, "wallet.partners.{$partnerCode}.secret", ''));
        if ($secret !== '') {
            return $secret;
        }

        return trim((string) data_get($embedConfig, 'secret', ''));
    }

    private function resolvePartnerCode(array $providerConfig, array $embedConfig): string
    {
        $configured = trim((string) data_get($embedConfig, 'partner_code', ''));
        if ($configured !== '') {
            return $configured;
        }

        $partners = array_keys((array) data_get($providerConfig, 'wallet.partners', []));
        return (string) ($partners[0] ?? 'demo_cms');
    }

    private function resolveExternalUserId(CmsUser $user): string
    {
        foreach ([$user->player_id, $user->external_id, $user->id, $user->username] as $candidate) {
            if ($candidate !== null && $candidate !== '') {
                return (string) $candidate;
            }
        }

        return (string) $user->id;
    }

    private function debugContext(
        CmsUser $user,
        Request $request,
        array $embedConfig,
        string $partnerCode,
        array $bootstrapPayload,
        array $localeResolution
    ): array
    {
        return [
            'request_id' => $bootstrapPayload['requestId'] ?? null,
            'cms_user_id' => $user->id,
            'cms_user_label' => $user->username ?: ($user->email ?: ('user:' . $user->id)),
            'cms_language' => $localeResolution['effective_locale'],
            'effective_locale' => $localeResolution['effective_locale'],
            'provider_locale' => data_get($bootstrapPayload, 'themeHints.locale'),
            'locale_fallback_used' => $localeResolution['locale_fallback_used'],
            'partner_code' => $partnerCode,
            'external_user_id' => $bootstrapPayload['externalUserId'] ?? null,
            'external_session_ref' => $bootstrapPayload['externalSessionRef'] ?? null,
            'wallet_context_ref' => $bootstrapPayload['walletContextRef'] ?? null,
            'requested_hostname' => data_get($embedConfig, 'requested_hostname'),
            'request_url' => $request->fullUrl(),
        ];
    }

    private function buildConsumeUrl(array $embedConfig, ?string $sessionToken, ?string $parentOrigin, ?string $requestedRedirect = null): ?string
    {
        $baseUrl = trim((string) data_get($embedConfig, 'public_base_url', ''));
        if (
            $baseUrl === ''
            || $sessionToken === null
            || $sessionToken === ''
            || !LocalEgress::assertUrlAllowed($baseUrl, 'chess_embed.iframe', [
            'feature' => 'chess_embed_iframe',
        ]))
        {
            return null;
        }

        $entryPath = '/' . ltrim((string) data_get($embedConfig, 'consume_path', '/embed/consume'), '/');
        $normalizedBase = rtrim($baseUrl, '/');
        $url = $normalizedBase . ($entryPath === '/' ? '/' : $entryPath);
        $query = Arr::where([
            (string) data_get($embedConfig, 'bridge_query_key', 'token') => $sessionToken,
            'origin' => $parentOrigin,
            'redirect' => $this->resolveSafeRedirectPath($requestedRedirect),
        ], static fn ($value) => $value !== null && $value !== '');

        if ($query === []) {
            return $url;
        }

        return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
    }

    private function resolveParentOrigin(Request $request, array $embedConfig): ?string
    {
        $configuredOrigin = $this->normalizeOrigin(
            (string) data_get($embedConfig, 'parent_origin_override', data_get($embedConfig, 'parent_origin', ''))
        );

        if ($configuredOrigin !== null) {
            return $configuredOrigin;
        }

        return $this->resolveRequestOrigin($request);
    }

    public function resolveSafeRedirectPath(mixed $requestedRedirect = null): ?string
    {
        if (!is_string($requestedRedirect)) {
            return null;
        }

        $requestedRedirect = trim((string) $requestedRedirect);

        if (
            $requestedRedirect === ''
            || preg_match('/[\x00-\x1F\x7F]/', $requestedRedirect)
            || str_starts_with($requestedRedirect, '//')
            || str_contains($requestedRedirect, '\\')
            || preg_match('/^[a-z][a-z0-9+.-]*:/i', $requestedRedirect)
        ) {
            return null;
        }

        return '/' . ltrim($requestedRedirect, '/');
    }

    private function resolvePostMessageOrigin(array $embedConfig, ?string $iframeSrc): ?string
    {
        $configuredOrigin = $this->normalizeOrigin((string) data_get($embedConfig, 'post_message_origin', ''));
        if ($configuredOrigin !== null) {
            return $configuredOrigin;
        }

        if (!$iframeSrc) {
            return null;
        }

        $scheme = parse_url($iframeSrc, PHP_URL_SCHEME);
        $host = parse_url($iframeSrc, PHP_URL_HOST);
        $port = parse_url($iframeSrc, PHP_URL_PORT);

        if (!is_string($scheme) || !is_string($host) || $scheme === '' || $host === '') {
            return null;
        }

        return sprintf('%s://%s%s', $scheme, $host, $port ? ':' . $port : '');
    }

    private function resolveRequestOrigin(Request $request): ?string
    {
        return $this->normalizeOrigin($request->getSchemeAndHttpHost());
    }

    private function normalizeOrigin(?string $origin): ?string
    {
        if (!is_string($origin)) {
            return null;
        }

        $origin = trim($origin);
        if ($origin === '') {
            return null;
        }

        $scheme = parse_url($origin, PHP_URL_SCHEME);
        $host = parse_url($origin, PHP_URL_HOST);
        $port = parse_url($origin, PHP_URL_PORT);

        if (!is_string($scheme) || !is_string($host) || $scheme === '' || $host === '') {
            return null;
        }

        return sprintf('%s://%s%s', $scheme, $host, $port ? ':' . $port : '');
    }

    private function resolveProductLocale(array $embedConfig): array
    {
        return $this->productLocales->resolve(
            'chessbet',
            'browser_embed',
            (array) data_get($embedConfig, 'locale', [])
        );
    }

    private function encodeBootstrapToken(array $payload, string $secret): string
    {
        $canonicalPayload = json_encode($this->sortValue($payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $encodedPayload = rtrim(strtr(base64_encode((string) $canonicalPayload), '+/', '-_'), '=');
        $signature = hash_hmac('sha256', (string) $canonicalPayload, $secret);

        return $encodedPayload . '.' . $signature;
    }

    private function sortValue($value)
    {
        if (!is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn ($item) => $this->sortValue($item), $value);
        }

        ksort($value);

        foreach ($value as $key => $nestedValue) {
            $value[$key] = $this->sortValue($nestedValue);
        }

        return $value;
    }
}
