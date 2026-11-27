<?php

namespace App\Services\Payments;

use App\Support\LocalEgress;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\RequestInterface;

final class NovaGatewayClient
{
    public const EGRESS_OPTION = 'rise_nova_connector';

    public function configured(): bool
    {
        $url = parse_url((string) config('nova_payments.url'));
        if (config('nova_payments.enabled') !== true || !is_array($url)
            || !in_array($url['path'] ?? '', ['', '/'], true)
            || array_intersect(['user', 'pass', 'query', 'fragment'], array_keys($url))
            || !filled(config('nova_payments.username')) || !filled(config('nova_payments.password'))
            || !filled(config('nova_payments.site_secret'))) {
            return false;
        }

        if ($this->remote()) {
            $host = strtolower($url['host'] ?? '');
            return ($url['scheme'] ?? null) === 'https'
                && filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false
                && str_contains($host, '.') && !filter_var($host, FILTER_VALIDATE_IP)
                && !str_ends_with($host, '.localhost')
                && LocalEgress::extractUrlAuthority((string) config('nova_payments.url')) === $this->remoteAuthority()
                && config('nova_payments.rail') === 'btc'
                && config('nova_payments.provider_mode') === 'LIVE_HTTP'
                && config('nova_payments.network_mode') === 'mainnet';
        }

        return app()->environment(['local', 'testing']) && LocalEgress::shouldBlock()
            && in_array($url['scheme'] ?? null, ['http', 'https'], true)
            && ($url['host'] ?? null) === '127.0.0.1' && isset($url['port'])
            && config('nova_payments.provider_mode') === 'test'
            && in_array(config('nova_payments.network_mode'), ['testnet', 'regtest'], true);
    }

    public function remote(): bool
    {
        return $this->remoteAuthority() !== '';
    }

    public function sessionsAllowed(): bool
    {
        return $this->configured() && (!$this->remote() || config('nova_payments.remote_sessions_enabled') === true);
    }

    public function creditAllowed(): bool
    {
        return $this->sessionsAllowed() && (!$this->remote() || config('nova_payments.remote_credit_enabled') === true);
    }

    public function tenantBinding(): string
    {
        return hash_hmac('sha256', json_encode([
            rtrim((string) config('nova_payments.url'), '/'), config('nova_payments.site_secret'),
        ], JSON_THROW_ON_ERROR), (string) config('app.key'));
    }

    public function checkRemoteConnection(): void
    {
        abort_unless($this->remote() && $this->configured(), 503, 'Remote Nova connection is not configured.');
        $response = $this->request('GET', '/api/external/crypto/rails/btc/readiness');
        $fingerprint = $response->json('data.tenantFingerprint');
        abort_unless($response->ok() && $response->json('data.contract_version') === 1
            && $response->json('data.rail.asset') === 'BTC' && $response->json('data.rail.network') === 'BTC'
            && $response->json('data.rail.railKey') === 'btc' && $response->json('data.rail.precision') === 8
            && is_string($fingerprint) && hash_equals(hash_hmac('sha256', 'crypto-runtime-readiness:tenant:v1',
                (string) config('nova_payments.site_secret')), $fingerprint), 503, 'Nova tenant or BTC rail could not be verified.');
    }

    public function createSession(array $intent): array
    {
        return $this->paymentRequest('POST', '/api/external/crypto/session', $intent);
    }

    public function transactionStatus(string $reference): array
    {
        return $this->paymentRequest('GET', '/api/external/crypto/transaction/' . rawurlencode($reference));
    }

    private function paymentRequest(string $method, string $path, array $intent = []): array
    {
        abort_unless($this->sessionsAllowed(), 503, 'Nova payment sessions are unavailable.');
        $response = $this->request($method, $path, $intent);
        // Nova may still be provisioning an invoice after a timeout or a 503.
        abort_unless(in_array($response->status(), $method === 'POST' ? [200, 201] : [200], true)
            && $response->json('status') === 'Success' && is_array($response->json('data')),
            503, 'Nova payment confirmation is temporarily unavailable.');

        return $response->json('data');
    }

    public function permitsRemoteRequest(RequestInterface $request): bool
    {
        $uri = $request->getUri();
        // PSR-7 normalizes away an explicit default HTTPS port.
        $authority = $uri->getPort() === null ? strtolower($uri->getHost()) : strtolower($uri->getHost()) . ':' . $uri->getPort();
        $approved = $this->remoteAuthority();
        if (!$this->remote() || !$this->configured() || $uri->getScheme() !== 'https'
            || $uri->getUserInfo() !== '' || $uri->getQuery() !== '' || $uri->getFragment() !== ''
            || ($authority !== $approved && $authority . ':443' !== $approved)) {
            return false;
        }

        if ($request->getMethod() === 'GET' && $uri->getPath() === '/api/external/crypto/rails/btc/readiness') {
            return true;
        }

        return $this->sessionsAllowed() && (
            ($request->getMethod() === 'POST' && $uri->getPath() === '/api/external/crypto/session')
            || ($request->getMethod() === 'GET'
                && preg_match('~\A/api/external/crypto/transaction/nova_[a-f0-9]{64}\z~', $uri->getPath()))
        );
    }

    private function request(string $method, string $path, array $intent = []): Response
    {
        $base = rtrim((string) config('nova_payments.url'), '/');
        $url = $base . $path;
        $authority = $this->remote() ? $this->remoteAuthority() : LocalEgress::extractUrlAuthority($base);
        abort_unless(LocalEgress::assertUrlAllowedAgainstAuthorities($url, [$authority], 'payment.nova'), 503);

        $secret = (string) config('nova_payments.site_secret');
        try {
            $request = Http::acceptJson()->withoutRedirecting()->connectTimeout(2)->timeout(6)
                ->withBasicAuth((string) config('nova_payments.username'), (string) config('nova_payments.password'));
            if ($this->remote()) {
                $request->withOptions([self::EGRESS_OPTION => $this]);
            }

            return $method === 'POST'
                ? $request->post($url, array_merge($intent, ['siteSecret' => $secret]))
                : $request->withHeaders(['X-Site-Secret' => $secret])->get($url);
        } catch (ConnectionException) {
            abort(503, 'Nova payment confirmation is temporarily unavailable.');
        }
    }

    private function remoteAuthority(): string
    {
        return LocalEgress::normalizeAuthority((string) config('nova_payments.remote_authority'));
    }
}
