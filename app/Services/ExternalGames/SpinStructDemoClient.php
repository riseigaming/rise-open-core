<?php

namespace App\Services\ExternalGames;

use App\Support\LocalEgress;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/** Auth + catalogue/fun_launch only. Never calls game_launch or player/wallet APIs. */
class SpinStructDemoClient
{
    public function __construct(private ExternalGameGatewayHmacSigner $signer) {}

    public function capabilities(): array
    {
        $response = $this->request('GET', '/api/v1/games/all', []);
        $rows = $response->json('games');
        if (!in_array($response->json('status'), [1, '1', 'success'], true) || !is_array($rows) || $rows === []) {
            throw $this->failure($response, 'Demo catalogue is unavailable.');
        }
        $map = [];
        $known = 0;
        foreach ($rows as $row) {
            if (!is_array($row) || !is_scalar($row['game_id'] ?? null)) continue;
            $id = trim((string) $row['game_id']);
            if ($id === '' || strlen($id) > 191) continue;
            $value = self::flag($row['fun_mode'] ?? null);
            if (array_key_exists($id, $map) && $map[$id] !== $value) {
                throw $this->failure($response, 'Conflicting demo catalogue IDs.');
            }
            $map[$id] = $value;
            if ($value !== null) $known++;
        }
        if ($known === 0) throw $this->failure($response, 'Demo catalogue has no explicit fun_mode fields.');
        return $map;
    }

    public static function flag(mixed $value): ?int
    {
        if (in_array($value, [1, '1', true], true)) return 1;
        if (in_array($value, [0, '0', false], true)) return 0;
        return null;
    }

    public function launch(string $gameId, string $language, string $device, ?string $requestId = null): string
    {
        // Deliberately no user, IP, session, balance or wallet arguments.
        $payload = ['game_id' => $gameId, 'currency' => 'TRY',
            'lang' => in_array($language, ['tr', 'en', 'es'], true) ? $language : 'en',
            'device' => $device === 'mobile' ? 'mobile' : 'desktop'];
        if ($requestId !== null && preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{7,63}$/', $requestId) !== 1) {
            throw new \InvalidArgumentException('Invalid gateway request ID.');
        }
        $response = $this->request('POST', '/api/v1/games/fun_launch', $payload, $requestId);
        $url = $response->json('url');
        if ($response->json('status') !== 'success' || $response->json('mode') !== 'fun'
            || !is_string($url) || self::frameOrigin($url) === null) {
            throw $this->failure($response, 'Provider did not return a verified fun launch.', ['game_id' => $gameId]);
        }
        return $url;
    }

    public static function frameOrigin(string $url): ?string
    {
        if (strlen($url) > 8192 || preg_match('/[\x00-\x20\x7f]/', $url)) return null;
        if (ExternalGameLocalContract::allows($url)) return 'http://127.0.0.1:18971';
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass'])
            || (isset($parts['port']) && $parts['port'] !== 443)
            || !preg_match('/^(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/', $host)
            || preg_match('/\.(?:local|localhost|internal|test|invalid)$/', $host)) return null;
        return 'https://' . $host;
    }

    private function request(string $method, string $path, array $payload, ?string $requestId = null): Response
    {
        $config = (array) config('external_games.providers.primary_external_provider', []);
        $base = rtrim((string) ($config['api_base_url'] ?? ''), '/');
        if (LocalEgress::blocks('block_provider_apis') && !ExternalGameLocalContract::allows((string) config('external_games.providers.primary_external_provider.api_base_url'))) {
            throw new ExternalGameLaunchBlockedException('demo_local_blocked', 'Demo provider requests are disabled locally.');
        }
        if ((parse_url($base, PHP_URL_SCHEME) !== 'https' && !ExternalGameLocalContract::allows($base))
            || empty($config['agent_token']) || empty($config['agent_secret']) || empty($config['agent_code'])) {
            throw new ExternalGameLaunchBlockedException('demo_not_configured', 'Demo provider is not configured.');
        }
        $url = $base . $path;
        $isAuth = $path === '/api/v1/auth/authentication';
        $authorization = base64_encode($config['agent_token'] . ':' . $config['agent_secret']);
        if (!$isAuth) {
            $auth = $this->request('POST', '/api/v1/auth/authentication', []);
            $accessToken = $auth->json('access_token');
            if (!is_string($accessToken) || $accessToken === '' || preg_match('/[\r\n]/', $accessToken)) {
                throw $this->failure($auth, 'Demo authentication failed.');
            }
            $authorization = $accessToken;
        }
        $body = $method === 'POST' && !$isAuth ? json_encode([
            'agent_token' => $config['agent_token'], 'agent_code' => $config['agent_code'],
        ] + $payload, JSON_UNESCAPED_SLASHES) : '';
        try {
            $headers = array_merge([
                    'X-Authorization' => 'Bearer ' . $authorization,
                    'Accept' => 'application/json',
                ], $this->signer->headers($method, $url, $body, $config));
            if ($requestId !== null) {
                $headers['X-Gateway-Request-Id'] = $requestId;
            }
            $request = Http::withoutRedirecting()->timeout($method === 'GET' ? 60 : 20)
                ->withHeaders($headers);
            $response = $method === 'POST' ? $request->withBody($body, 'application/json')->post($url) : $request->get($url);
        } catch (ConnectionException) {
            // Do not retain the transport exception: it can include request secrets.
            throw new ExternalGameProviderLaunchException('Demo provider connection failed.',
                ['classification' => ExternalGameProviderLaunchException::INTEGRATION_ERROR]);
        }
        if (!$response->successful()) throw $this->failure($response, 'Demo provider request failed.', $payload);
        return $response;
    }

    private function failure(Response $response, string $message, array $context = []): ExternalGameProviderLaunchException
    {
        return new ExternalGameProviderLaunchException($message, ExternalGameProviderFailureSanitizer::fromResponse(
            $response, ['provider_alias' => 'primary_external_provider'] + $context));
    }
}
