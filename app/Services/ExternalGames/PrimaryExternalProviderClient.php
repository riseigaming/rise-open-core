<?php

namespace App\Services\ExternalGames;

use App\Support\LocalEgress;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PrimaryExternalProviderClient
{
    public function __construct(private readonly ExternalGameGatewayHmacSigner $gatewaySigner)
    {
    }

    public function authenticate(string $providerAlias = 'primary_external_provider'): string
    {
        if ((LocalEgress::blocks('block_provider_apis') && !ExternalGameLocalContract::allows((string) config("external_games.providers.{$providerAlias}.api_base_url"), $providerAlias))) {
            throw new ExternalGameProviderLaunchException(
                'External Games provider API is blocked by local safety policy.',
                ExternalGameProviderFailureSanitizer::withoutResponse(
                    ['provider_alias' => $providerAlias],
                    ExternalGameProviderLaunchException::INTEGRATION_ERROR
                )
            );
        }

        $config = $this->providerConfig($providerAlias);
        try {
            $baseUrl = $this->baseUrl($config);
        } catch (RuntimeException $exception) {
            throw new ExternalGameProviderLaunchException(
                $exception->getMessage(),
                ExternalGameProviderFailureSanitizer::withoutResponse(
                    ['provider_alias' => $providerAlias],
                    ExternalGameProviderLaunchException::INTEGRATION_ERROR
                ),
                $exception
            );
        }
        $agentToken = trim((string) ($config['agent_token'] ?? ''));
        $agentSecret = trim((string) ($config['agent_secret'] ?? ''));

        if ($agentToken === '' || $agentSecret === '') {
            throw new ExternalGameProviderLaunchException(
                'External Games provider credentials are not configured.',
                ExternalGameProviderFailureSanitizer::withoutResponse(
                    ['provider_alias' => $providerAlias],
                    ExternalGameProviderLaunchException::INTEGRATION_ERROR
                )
            );
        }

        $url = $baseUrl . '/api/v1/auth/authentication';
        $body = '';

        try {
            $response = Http::withoutRedirecting()->timeout($this->timeout($config))
                ->withHeaders(array_merge([
                    'X-Authorization' => 'Bearer ' . base64_encode($agentToken . ':' . $agentSecret),
                    'Accept' => 'application/json',
                ], $this->gatewaySigner->headers('POST', $url, $body, $config)))
                ->withBody($body, 'application/json')
                ->post($url);
        } catch (ConnectionException $exception) {
            throw new ExternalGameProviderLaunchException(
                'External Games provider authentication request timed out or could not connect.',
                ExternalGameProviderFailureSanitizer::withoutResponse(
                    ['provider_alias' => $providerAlias],
                    ExternalGameProviderLaunchException::INTEGRATION_ERROR
                ),
                $exception
            );
        }

        if (!$response->successful()) {
            throw new ExternalGameProviderLaunchException(
                'External Games provider authentication failed.',
                ExternalGameProviderFailureSanitizer::fromResponse(
                    $response,
                    ['provider_alias' => $providerAlias]
                )
            );
        }

        $accessToken = $this->firstString((array) $response->json(), [
            'access_token',
            'token',
            'data.access_token',
            'data.token',
        ]);

        if ($accessToken === '') {
            throw new ExternalGameProviderLaunchException(
                'External Games provider authentication did not return an access token.',
                ExternalGameProviderFailureSanitizer::fromResponse(
                    $response,
                    ['provider_alias' => $providerAlias]
                )
            );
        }

        return $accessToken;
    }

    public function gamesAll(string $providerAlias = 'primary_external_provider'): array
    {
        $config = $this->providerConfig($providerAlias);
        $baseUrl = $this->baseUrl($config);
        $accessToken = $this->authenticate($providerAlias);

        $url = $baseUrl . '/api/v1/games/all';
        $body = '';

        $response = Http::withoutRedirecting()->timeout($this->catalogTimeout($config))
            ->withHeaders(array_merge([
                'X-Authorization' => 'Bearer ' . $accessToken,
                'Accept' => 'application/json',
            ], $this->gatewaySigner->headers('GET', $url, $body, $config)))
            ->get($url);

        if (!$response->successful()) {
            throw new RuntimeException('External Games catalog fetch failed.');
        }

        $payload = $response->json();

        return is_array($payload) ? $payload : [];
    }

    public function gameLaunch(array $payload, string $providerAlias = 'primary_external_provider'): array
    {
        $diagnosticContext = [
            'provider_alias' => $providerAlias,
            'game_id' => $payload['game_id'] ?? null,
            'currency' => $payload['currency'] ?? null,
            'payload_ip' => $payload['ip_address'] ?? null,
        ];

        if ((LocalEgress::blocks('block_provider_apis') && !ExternalGameLocalContract::allows((string) config("external_games.providers.{$providerAlias}.api_base_url"), $providerAlias))) {
            throw new ExternalGameProviderLaunchException(
                'External Games provider API is blocked by local safety policy.',
                ExternalGameProviderFailureSanitizer::withoutResponse(
                    $diagnosticContext,
                    ExternalGameProviderLaunchException::INTEGRATION_ERROR
                )
            );
        }

        $config = $this->providerConfig($providerAlias);
        try {
            $baseUrl = $this->baseUrl($config);
        } catch (RuntimeException $exception) {
            throw new ExternalGameProviderLaunchException(
                $exception->getMessage(),
                ExternalGameProviderFailureSanitizer::withoutResponse(
                    $diagnosticContext,
                    ExternalGameProviderLaunchException::INTEGRATION_ERROR
                ),
                $exception
            );
        }
        $agentToken = trim((string) ($config['agent_token'] ?? ''));
        $agentSecret = trim((string) ($config['agent_secret'] ?? ''));
        $agentCode = trim((string) ($config['agent_code'] ?? ''));
        $launchProfile = (string) ($payload['_launch_payload_profile'] ?? '');

        if ($agentToken !== '') {
            $payload = ['agent_token' => $agentToken] + $payload;
        }

        if ($agentCode !== '') {
            $payload = ['agent_code' => $agentCode] + $payload;
        }

        $payload = $this->outboundLaunchPayload($payload);

        $url = $baseUrl . '/api/v1/games/game_launch';
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $body = is_string($body) ? $body : '{}';

        $headers = [
            'Accept' => 'application/json',
        ];

        if ($this->launchAuthMode($launchProfile) === 'agent_basic_bearer') {
            if ($agentToken === '' || $agentSecret === '') {
                throw new ExternalGameProviderLaunchException(
                    'External Games provider credentials are not configured.',
                    ExternalGameProviderFailureSanitizer::withoutResponse(
                        $diagnosticContext,
                        ExternalGameProviderLaunchException::INTEGRATION_ERROR
                    )
                );
            }

            $basicAuthToken = base64_encode($agentToken . ':' . $agentSecret);
            $headers['Authorization'] = 'Bearer ' . $basicAuthToken;
            $headers['X-Authorization'] = 'Bearer ' . $basicAuthToken;
        } else {
            try {
                $accessToken = $this->authenticate($providerAlias);
            } catch (ExternalGameProviderLaunchException $exception) {
                throw $exception->withContext($diagnosticContext);
            }
            $headers['X-Authorization'] = 'Bearer ' . $accessToken;
        }

        try {
            $response = Http::withoutRedirecting()->timeout($this->timeout($config))
                ->withHeaders(array_merge($headers, $this->gatewaySigner->headers('POST', $url, $body, $config)))
                ->withBody($body, 'application/json')
                ->post($url);
        } catch (ConnectionException $exception) {
            throw new ExternalGameProviderLaunchException(
                'External Games launch request timed out or could not connect.',
                ExternalGameProviderFailureSanitizer::withoutResponse(
                    $diagnosticContext,
                    ExternalGameProviderLaunchException::INTEGRATION_ERROR
                ),
                $exception
            );
        }

        if (!$response->successful()) {
            throw new ExternalGameProviderLaunchException(
                'External Games launch request failed.',
                ExternalGameProviderFailureSanitizer::fromResponse($response, $diagnosticContext)
            );
        }

        $raw = $response->json();
        $raw = is_array($raw) ? $raw : [];
        $launchUrl = $this->firstString($raw, [
            'launch_url',
            'lobby_url',
            'game_url',
            'url',
            'data.launch_url',
            'data.lobby_url',
            'data.game_url',
            'data.url',
        ]);

        if ($launchUrl === '') {
            throw new ExternalGameProviderLaunchException(
                'External Games launch response did not include a launch URL.',
                ExternalGameProviderFailureSanitizer::fromResponse($response, $diagnosticContext)
            );
        }

        return [
            'launch_url' => $launchUrl,
            'provider_reference' => $this->firstString($raw, [
                'reference',
                'launch_reference',
                'data.reference',
                'data.launch_reference',
            ]) ?: null,
            'raw' => $raw,
        ];
    }

    private function providerConfig(string $providerAlias): array
    {
        if (!ExternalGameProviderConfig::enabled($providerAlias) || !ExternalGameProviderConfig::distinctWalletCredentials($providerAlias)) {
            throw new RuntimeException('External Games provider connection is unavailable.');
        }
        return ExternalGameProviderConfig::providers()[$providerAlias];
    }

    private function outboundLaunchPayload(array $payload): array
    {
        $profile = (string) ($payload['_launch_payload_profile'] ?? '');
        unset(
            $payload['_launch_payload_profile'],
            $payload['effective_locale'],
            $payload['provider_locale'],
            $payload['locale_fallback_used']
        );

        if ($profile === '') {
            return $payload;
        }

        $allowedKeys = (array) config("external_games.launch_payload_profiles.{$profile}.allowed_keys", []);
        if ($allowedKeys === []) {
            return $payload;
        }

        if (
            (string) config("external_games.launch_payload_profiles.{$profile}.balance_format", '') === 'string_2dp'
            && array_key_exists('balance', $payload)
        ) {
            $payload['balance'] = (string) \Brick\Math\BigDecimal::of((string)$payload['balance'])->toScale(2, \Brick\Math\RoundingMode::HALF_UP);
        }

        $allowed = array_flip($allowedKeys);
        $filtered = array_intersect_key($payload, $allowed);

        return collect($allowedKeys)
            ->filter(fn (string $key): bool => array_key_exists($key, $filtered))
            ->mapWithKeys(fn (string $key): array => [$key => $filtered[$key]])
            ->all();
    }

    private function launchAuthMode(string $profile): string
    {
        if ($profile === '') {
            return '';
        }

        return (string) config("external_games.launch_payload_profiles.{$profile}.auth_mode", '');
    }

    private function baseUrl(array $config): string
    {
        $baseUrl = rtrim(trim((string) ($config['api_base_url'] ?? '')), '/');

        if ($baseUrl === '') {
            throw new RuntimeException('External Games API base URL is not configured.');
        }

        return $baseUrl;
    }

    private function timeout(array $config): int
    {
        return max(1, (int) ($config['timeout_seconds'] ?? 15));
    }

    private function catalogTimeout(array $config): int
    {
        return max($this->timeout($config), (int) ($config['catalog_timeout_seconds'] ?? 60));
    }

    private function firstString(array $payload, array $keys): string
    {
        foreach ($keys as $key) {
            $value = trim((string) data_get($payload, $key, ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}
