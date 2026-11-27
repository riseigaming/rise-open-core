<?php

namespace App\Betting\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SportsbookLocalParityService
{
    public const QUOTE_CACHE_PREFIX = 'sportsbook:local-parity:quote:';

    private const ALLOWED_PATHS = [
        '/cms/sportsbook/live-offers',
        '/cms/sportsbook/catalog-tree',
        '/cms/sportsbook/bet-feed-items',
        '/cms/sportsbook/social-market-items',
        '/cms/sportsbook/prediction-items',
    ];

    public function __construct(private readonly PublicSelectionTokenService $selectionTokenService)
    {
    }

    public function enabled(): bool
    {
        return app()->environment(['local', 'testing'])
            && (bool) config('betting.sportsbook_local_parity.enabled', false);
    }

    public function financeSandboxEnabled(): bool
    {
        $writeTarget = (string) config('betting.sportsbook_local_parity.write_target', 'disabled');

        return $this->enabled()
            && ($writeTarget === 'local' || (bool) config('betting.sportsbook_local_parity.finance_sandbox', false))
            && (
                (bool) config('betting.sportsbook_local_parity.finance_enabled', false)
                || (bool) config('betting.sportsbook_local_parity.finance_sandbox', false)
            );
    }

    public function readSource(): string
    {
        return (string) config('betting.sportsbook_local_parity.read_source', 'adapter_live');
    }

    public function usesAdapterLiveReads(): bool
    {
        return $this->enabled() && $this->readSource() === 'adapter_live';
    }

    public function shouldProxyReads(): bool
    {
        return $this->enabled()
            && $this->readSource() === 'forge_read_proxy';
    }

    public function proxyJson(Request $request, string $path)
    {
        if (!$this->shouldProxyReads() || !in_array($path, self::ALLOWED_PATHS, true)) {
            return null;
        }

        $baseUrl = rtrim((string) config('betting.sportsbook_local_parity.base_url', ''), '/');
        if ($baseUrl === '' || !filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            return response()->json($this->errorPayload('LOCAL_PARITY_BASE_URL_INVALID'), 503);
        }

        $url = $baseUrl . $path;
        $query = $this->safeQuery($request);
        $startedAt = microtime(true);

        try {
            $pending = Http::acceptJson()
                ->withHeaders([
                    'X-Requested-With' => 'XMLHttpRequest',
                    'User-Agent' => 'CMS-Local-Sportsbook-Parity/1.0',
                ])
                ->timeout(max(1, (int) config('betting.sportsbook_local_parity.timeout_seconds', 12)))
                ->withOptions(['connect_timeout' => 3]);

            [$username, $password] = $this->basicAuth();
            if ($username !== '' && $password !== '') {
                $pending = $pending->withBasicAuth($username, $password);
            }

            $response = $pending->get($url, $query);
        } catch (\Throwable) {
            return response()->json($this->errorPayload('LOCAL_PARITY_READ_FAILED'), 502);
        }

        $payload = $response->json();
        if (!$response->successful() || !is_array($payload)) {
            return response()->json($this->errorPayload('LOCAL_PARITY_READ_UNAVAILABLE'), $response->status() ?: 502);
        }

        $payload = $this->rewritePublicPayload($payload, $path);
        Arr::set($payload, 'meta.local_parity', [
            'enabled' => true,
            'read_source' => 'forge_read_proxy',
            'write_mode' => $this->financeSandboxEnabled() ? 'local_sandbox' : 'disabled',
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        return response()->json($payload, $response->status());
    }

    public function verifiedQuoteFor(string $quoteRef): ?array
    {
        if (!$this->financeSandboxEnabled() || !str_starts_with($quoteRef, 'sandbox:')) {
            return null;
        }

        $quote = Cache::get($this->quoteCacheKey($quoteRef));

        return is_array($quote) ? $quote : null;
    }

    public function quoteCacheKey(string $quoteRef): string
    {
        return self::QUOTE_CACHE_PREFIX . hash('sha256', $quoteRef);
    }

    public function rewriteAdapterLivePayload(array $payload, string $path): array
    {
        if (!$this->usesAdapterLiveReads()) {
            return $payload;
        }

        $payload = $this->rewritePublicPayload($payload, $path);
        if ($path === '/cms/sportsbook/live-offers') {
            Arr::set($payload, 'meta.read_source', 'local_parity_adapter_live');
        }
        Arr::set($payload, 'meta.local_parity', [
            'enabled' => true,
            'read_source' => 'adapter_live',
            'write_mode' => $this->financeSandboxEnabled() ? 'local_sandbox' : 'disabled',
        ]);

        return $payload;
    }

    private function rewritePublicPayload(array $payload, string $path): array
    {
        $this->rewriteNode($payload, [], []);

        if ($path === '/cms/sportsbook/live-offers') {
            Arr::set($payload, 'meta.read_source', 'local_parity_forge_read_proxy');
        }

        return $payload;
    }

    private function rewriteNode(array &$node, array $eventContext, array $marketContext): void
    {
        if ($this->looksLikeEvent($node)) {
            $eventContext = $this->eventContext($node, $eventContext);
        }

        if ($this->looksLikeMarket($node)) {
            $marketContext = $this->marketContext($node, $marketContext);
        }

        if ($this->looksLikeSelection($node)) {
            $this->rewriteSelection($node, $eventContext, $marketContext);

            return;
        }

        foreach ($node as &$value) {
            if (is_array($value)) {
                $this->rewriteNode($value, $eventContext, $marketContext);
            }
        }
    }

    private function rewriteSelection(array &$selection, array $event, array $market): void
    {
        unset(
            $selection['quote_ref'],
            $selection['source_provider'],
            $selection['provider_key'],
            $selection['provider'],
            $selection['provider_payload'],
            $selection['raw_payload']
        );

        $price = $this->price($selection);
        $disabled = (bool) ($selection['disabled'] ?? false)
            || (array_key_exists('selectable', $selection) && !(bool) $selection['selectable'])
            || (array_key_exists('bettable', $selection) && !(bool) $selection['bettable']);

        if ($price === null || $disabled) {
            unset($selection['selection_token'], $selection['client_selection_ref']);
            if (($selection['acceptance_mode'] ?? null) === 'adapter_vps_selection_token') {
                $selection['acceptance_mode'] = 'adapter_vps_selection';
            }

            return;
        }

        if (!$this->financeSandboxEnabled()) {
            unset($selection['selection_token'], $selection['client_selection_ref']);
            $selection['acceptance_mode'] = 'adapter_vps_selection';
            $selection['selectable'] = false;
            $selection['disabled'] = true;
            $selection['bettable'] = false;
            $selection['bettable_reason'] = 'local_finance_disabled';
            $selection['selection_state_message'] = 'Local sportsbook finance sandbox is disabled.';

            return;
        }

        $quoteRef = $this->sandboxQuoteRef($event, $market, $selection, $price);
        $marketForToken = [
            'market_type' => (string) ($market['market_type'] ?? $selection['market_type'] ?? 'match_result'),
            'specifier_key' => (string) ($market['specifier_key'] ?? $selection['market_specifier_key'] ?? 'default'),
            'status' => 'open',
        ];
        $eventForToken = [
            'event_id' => (int) ($event['event_id'] ?? $selection['event_id'] ?? 0),
            'event_key' => 'local_parity:' . (string) ($event['source_event_key'] ?? $event['event_id'] ?? $selection['event_id'] ?? ''),
            'source_event_key' => (string) ($event['source_event_key'] ?? $event['event_id'] ?? $selection['event_id'] ?? ''),
            'phase_key' => 'pre-match',
            'quote' => ['quote_version_id' => $event['quote_version_id'] ?? $selection['quote_version_id'] ?? null],
        ];
        $selectionForToken = [
            'quote_ref' => $quoteRef,
            'outcome_code' => (string) ($selection['outcome_code'] ?? $selection['selection_key'] ?? Str::slug((string) ($selection['outcome_name'] ?? $selection['label'] ?? 'selection'), '_')),
            'offer_status' => 'open',
            'decimal_price' => $price,
            'acceptance_mode' => 'adapter_vps_quote_ref',
        ];

        $token = $this->selectionTokenService->issueForAdapterSelection($eventForToken, $marketForToken, $selectionForToken);
        if ($token === null) {
            unset($selection['selection_token'], $selection['client_selection_ref']);
            $selection['selectable'] = false;
            $selection['disabled'] = true;
            $selection['bettable'] = false;
            $selection['bettable_reason'] = 'local_sandbox_token_unavailable';

            return;
        }

        $this->storeVerifiedQuote($quoteRef, $event, $marketForToken, $selection, $price);

        $selection['selection_token'] = $token;
        $selection['acceptance_mode'] = 'adapter_vps_selection_token';
        $selection['selectable'] = true;
        $selection['disabled'] = false;
        $selection['bettable'] = true;
        $selection['bettable_reason'] = null;
        unset($selection['client_selection_ref'], $selection['selection_state_message']);
    }

    private function storeVerifiedQuote(string $quoteRef, array $event, array $market, array $selection, string $price): void
    {
        $quote = [
            'quote_ref' => $quoteRef,
            'quote_version_ref' => 'sandbox:quote-version:' . substr(hash('sha256', $quoteRef), 0, 24),
            'quote_payload_digest' => hash('sha256', json_encode([$event, $market, $selection, $price], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: $quoteRef),
            'verification_id' => 'sandbox:' . substr(hash('sha256', $quoteRef . ':verify'), 0, 24),
            'status' => 'open',
            'trading_state' => 'open',
            'offer_status' => 'open',
            'verified_at' => now()->toIso8601String(),
            'event' => [
                'event_id' => $event['event_id'] ?? null,
                'source_event_key' => (string) ($event['source_event_key'] ?? $event['event_id'] ?? ''),
                'canonical_name' => (string) ($event['event_name'] ?? $event['canonical_name'] ?? ''),
                'starts_at' => $event['starts_at'] ?? null,
            ],
            'market' => [
                'market_type' => (string) ($market['market_type'] ?? 'match_result'),
                'title' => (string) ($selection['market_name'] ?? $market['display_name'] ?? $market['market_type'] ?? 'Market'),
                'specifier_key' => (string) ($market['specifier_key'] ?? 'default'),
                'line' => $selection['line'] ?? $market['line'] ?? null,
            ],
            'selection' => [
                'outcome_code' => (string) ($selection['outcome_code'] ?? ''),
                'display_name' => (string) ($selection['outcome_name'] ?? $selection['display_name'] ?? $selection['label'] ?? ''),
                'decimal_price' => $price,
                'offer_status' => 'open',
            ],
            'price' => ['decimal_price' => $price],
            'source' => [
                'provider_key' => 'local_sandbox',
                'connector_key' => 'local_parity',
            ],
            'settlement_identity' => [
                'source_event_key' => (string) ($event['source_event_key'] ?? $event['event_id'] ?? ''),
                'market_type' => (string) ($market['market_type'] ?? 'match_result'),
                'selection_key' => (string) ($selection['selection_key'] ?? $selection['outcome_code'] ?? ''),
            ],
        ];

        Cache::put(
            $this->quoteCacheKey($quoteRef),
            $quote,
            now()->addSeconds(max(60, (int) config('betting.sportsbook_local_parity.quote_ttl_seconds', 900)))
        );
    }

    private function looksLikeEvent(array $node): bool
    {
        return isset($node['event_id']) || isset($node['event_key']) || isset($node['source_event_key']) || isset($node['match_result']);
    }

    private function looksLikeMarket(array $node): bool
    {
        return isset($node['market_type']) || isset($node['specifier_key']) || isset($node['selections']);
    }

    private function looksLikeSelection(array $node): bool
    {
        return (isset($node['selection_token']) || isset($node['quote_selection_id']) || isset($node['outcome_code']) || isset($node['raw_odd']) || isset($node['decimal_price']))
            && ($this->price($node) !== null);
    }

    private function eventContext(array $node, array $fallback): array
    {
        return array_filter(array_merge($fallback, [
            'event_id' => $node['event_id'] ?? $fallback['event_id'] ?? null,
            'event_key' => $node['event_key'] ?? $fallback['event_key'] ?? null,
            'source_event_key' => $node['source_event_key'] ?? $node['event_key'] ?? $fallback['source_event_key'] ?? null,
            'event_name' => $node['event_name'] ?? $node['canonical_name'] ?? $node['name'] ?? $fallback['event_name'] ?? null,
            'canonical_name' => $node['canonical_name'] ?? $node['event_name'] ?? $fallback['canonical_name'] ?? null,
            'starts_at' => $node['starts_at'] ?? $fallback['starts_at'] ?? null,
            'quote_version_id' => data_get($node, 'quote.quote_version_id', $node['quote_version_id'] ?? $fallback['quote_version_id'] ?? null),
        ]), static fn ($value): bool => $value !== null && $value !== '');
    }

    private function marketContext(array $node, array $fallback): array
    {
        return array_filter(array_merge($fallback, [
            'market_type' => $node['market_type'] ?? $fallback['market_type'] ?? null,
            'specifier_key' => $node['specifier_key'] ?? $node['market_specifier_key'] ?? $fallback['specifier_key'] ?? 'default',
            'display_name' => $node['display_name'] ?? $node['market_name'] ?? $fallback['display_name'] ?? null,
            'line' => $node['line'] ?? $fallback['line'] ?? null,
        ]), static fn ($value): bool => $value !== null && $value !== '');
    }

    private function sandboxQuoteRef(array $event, array $market, array $selection, string $price): string
    {
        return 'sandbox:' . hash('sha256', implode('|', [
            (string) ($event['event_id'] ?? $selection['event_id'] ?? ''),
            (string) ($market['market_type'] ?? $selection['market_type'] ?? 'match_result'),
            (string) ($market['specifier_key'] ?? $selection['market_specifier_key'] ?? 'default'),
            (string) ($selection['outcome_code'] ?? $selection['selection_key'] ?? $selection['label'] ?? ''),
            $price,
        ]));
    }

    private function price(array $selection): ?string
    {
        $value = $selection['decimal_price'] ?? $selection['raw_odd'] ?? $selection['odd'] ?? null;

        return is_numeric($value) && (float) $value > 0
            ? number_format((float) $value, 6, '.', '')
            : null;
    }

    private function safeQuery(Request $request): array
    {
        return collect($request->query())
            ->only([
                'limit',
                'surface',
                'phase_key',
                'sport_group_key',
                'league_filter_key',
                'search',
                'cursor',
                'time_priority',
                'interest_priority',
                'event_id',
                'event_ids',
                'fresh',
            ])
            ->all();
    }

    private function basicAuth(): array
    {
        $username = (string) config('betting.sportsbook_local_parity.basic_auth.username', '');
        $password = (string) config('betting.sportsbook_local_parity.basic_auth.password', '');
        if ($username !== '' && $password !== '') {
            return [$username, $password];
        }

        $path = (string) config('betting.sportsbook_local_parity.basic_auth.agents_path', '');
        if ($path === '' || !is_file($path)) {
            return ['', ''];
        }

        $values = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if ($line === '' || str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $values[trim($key)] = trim($value, "\"' ");
        }

        return [
            (string) ($values['BASIC_AUTH_USERNAME'] ?? $values['BASIC_AUTH_USER'] ?? ''),
            (string) ($values['BASIC_AUTH_PASSWORD'] ?? $values['BASIC_AUTH_PASS'] ?? ''),
        ];
    }

    private function errorPayload(string $code): array
    {
        return [
            'success' => false,
            'data' => null,
            'error' => [
                'code' => $code,
                'message' => 'Local sportsbook parity read source is unavailable.',
            ],
            'meta' => [
                'local_parity' => [
                    'enabled' => $this->enabled(),
                    'read_source' => $this->readSource(),
                ],
            ],
        ];
    }
}
