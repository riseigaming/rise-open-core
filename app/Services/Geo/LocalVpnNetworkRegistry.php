<?php

namespace App\Services\Geo;

use App\SiteSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;

class LocalVpnNetworkRegistry
{
    public const SETTINGS_KEY = 'local_vpn_network_registry_v1';
    public const VERSION = 1;
    public const TYPE_IP = 'ip';
    public const TYPE_CIDR = 'cidr';
    public const TYPE_ASN = 'asn';

    private const TYPES = [self::TYPE_IP, self::TYPE_CIDR, self::TYPE_ASN];

    public function storageAvailable(): bool
    {
        return Schema::hasTable('site_settings');
    }

    public function match(string $ip): ?array
    {
        $ip = $this->normalizeIp($ip);
        if ($ip === null || !$this->storageAvailable()) {
            return null;
        }

        $compiled = $this->compiled();
        if (isset($compiled['exact'][$ip])) {
            return $compiled['exact'][$ip];
        }

        $family = str_contains($ip, ':') ? 'v6' : 'v4';
        foreach ($compiled['cidr'][$family] as $entry) {
            if ($this->cidrContains($entry['value'], $ip)) {
                return $entry;
            }
        }

        return null;
    }

    private function settings(): array
    {
        if (!$this->storageAvailable()) {
            return ['version' => self::VERSION, 'entries' => []];
        }

        $raw = (string) SiteSettings::query()->where('key', self::SETTINGS_KEY)->value('value');
        $decoded = json_decode($raw, true);

        return $this->normalizeSettings(is_array($decoded) ? $decoded : []);
    }

    private function compiled(): array
    {
        $cached = Cache::get($this->cacheKey());
        if (is_array($cached) && isset($cached['exact'], $cached['cidr'])) {
            return $cached;
        }

        $compiled = ['exact' => [], 'cidr' => ['v4' => [], 'v6' => []]];
        foreach ($this->settings()['entries'] as $entry) {
            if (!$entry['active']) {
                continue;
            }

            if ($entry['type'] === self::TYPE_IP) {
                $compiled['exact'][$entry['value']] = $entry;
                continue;
            }

            if ($entry['type'] === self::TYPE_CIDR) {
                $compiled['cidr'][str_contains($entry['value'], ':') ? 'v6' : 'v4'][] = $entry;
            }
        }

        foreach ($compiled['cidr'] as &$entries) {
            usort($entries, fn (array $left, array $right): int => $this->cidrPrefix($right['value']) <=> $this->cidrPrefix($left['value']));
        }
        unset($entries);

        Cache::put($this->cacheKey(), $compiled, now()->addSeconds($this->cacheTtl()));

        return $compiled;
    }

    private function normalizeSettings(array $settings): array
    {
        $entries = [];
        foreach ((array) ($settings['entries'] ?? []) as $entryId => $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $id = is_string($entry['id'] ?? null) ? $entry['id'] : (string) $entryId;
            try {
                $id = $this->validateEntryId($id);
                // Retain a formerly configured ASN entry in storage even while
                // V1 has no authoritative ASN source. It remains deliberately
                // non-matching until an authoritative source is introduced.
                $type = $this->normalizeStoredType((string) ($entry['type'] ?? ''));
                $value = $this->normalizeValue($type, (string) ($entry['value'] ?? ''));
                $label = $this->normalizeRequired($entry['label'] ?? '', 100, 'A network label is required.');
            } catch (InvalidArgumentException) {
                continue;
            }

            $entries[$id] = [
                'id' => $id,
                'type' => $type,
                'value' => $value,
                'active' => (bool) ($entry['active'] ?? false),
                'label' => $label,
                'source' => $this->normalizeOptional($entry['source'] ?? null, 100) ?? 'operator_confirmed',
                'note' => $this->normalizeOptional($entry['note'] ?? null, 500),
                'created_by' => isset($entry['created_by']) ? (int) $entry['created_by'] : null,
                'created_at' => $entry['created_at'] ?? null,
                'updated_by' => isset($entry['updated_by']) ? (int) $entry['updated_by'] : null,
                'updated_at' => $entry['updated_at'] ?? null,
            ];
        }

        if (count($entries) > $this->maxEntries()) {
            $entries = array_slice($entries, 0, $this->maxEntries(), true);
        }

        return ['version' => self::VERSION, 'entries' => $entries];
    }

    private function normalizeStoredType(string $type): string
    {
        $type = strtolower(trim($type));
        if (!in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException('Unsupported VPN/proxy network type.');
        }

        return $type;
    }

    private function normalizeValue(string $type, string $value): string
    {
        return match ($type) {
            self::TYPE_IP => $this->normalizeIp($value) ?? throw new InvalidArgumentException('A valid IPv4 or IPv6 address is required.'),
            self::TYPE_CIDR => $this->normalizeCidr($value),
            self::TYPE_ASN => $this->normalizeAsn($value),
            default => throw new InvalidArgumentException('Unsupported VPN/proxy network type.'),
        };
    }

    private function normalizeIp(string $ip): ?string
    {
        $ip = trim($ip);
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        return inet_ntop((string) inet_pton($ip)) ?: null;
    }

    private function normalizeCidr(string $value): string
    {
        [$address, $prefix] = array_pad(explode('/', trim($value), 2), 2, null);
        $ip = $this->normalizeIp($address);
        if ($ip === null || $prefix === null || !ctype_digit($prefix)) {
            throw new InvalidArgumentException('A valid IPv4 or IPv6 CIDR network is required.');
        }

        $maximum = str_contains($ip, ':') ? 128 : 32;
        if ((int) $prefix > $maximum) {
            throw new InvalidArgumentException('CIDR prefix is outside the address family range.');
        }

        $packed = (string) inet_pton($ip);
        $network = $this->applyPrefixMask($packed, (int) $prefix);

        return (string) inet_ntop($network) . '/' . (int) $prefix;
    }

    private function normalizeAsn(string $value): string
    {
        $value = strtoupper(trim($value));
        $value = str_starts_with($value, 'AS') ? substr($value, 2) : $value;
        if (!ctype_digit($value) || (int) $value <= 0) {
            throw new InvalidArgumentException('A valid ASN is required.');
        }

        return 'AS' . (int) $value;
    }

    private function validateEntryId(string $entryId): string
    {
        $entryId = strtolower(trim($entryId));
        if (preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/', $entryId) !== 1) {
            throw new InvalidArgumentException('Invalid VPN/proxy network entry.');
        }

        return $entryId;
    }

    private function normalizeRequired(mixed $value, int $limit, string $message): string
    {
        $value = $this->normalizeOptional($value, $limit);
        if ($value === null || mb_strlen($value) < 2) {
            throw new InvalidArgumentException($message);
        }

        return $value;
    }

    private function normalizeOptional(mixed $value, int $limit): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $limit);
    }

    private function cidrContains(string $cidr, string $ip): bool
    {
        [$network, $prefix] = explode('/', $cidr, 2);
        $networkPacked = inet_pton($network);
        $ipPacked = inet_pton($ip);
        if ($networkPacked === false || $ipPacked === false || strlen($networkPacked) !== strlen($ipPacked)) {
            return false;
        }

        return $this->applyPrefixMask($ipPacked, (int) $prefix) === $networkPacked;
    }

    private function applyPrefixMask(string $packed, int $prefix): string
    {
        $result = '';
        $remaining = $prefix;
        foreach (str_split($packed) as $byte) {
            if ($remaining >= 8) {
                $result .= $byte;
                $remaining -= 8;
                continue;
            }
            if ($remaining <= 0) {
                $result .= "\0";
                continue;
            }

            $mask = (0xff << (8 - $remaining)) & 0xff;
            $result .= chr(ord($byte) & $mask);
            $remaining = 0;
        }

        return $result;
    }

    private function cidrPrefix(string $cidr): int
    {
        return (int) substr(strrchr($cidr, '/'), 1);
    }

    private function cacheKey(): string
    {
        return 'restrictions:vpn-network-registry:v1';
    }

    private function cacheTtl(): int
    {
        return max(30, min(3600, (int) config('restrictions.vpn_registry.cache_ttl_seconds', 300)));
    }

    private function maxEntries(): int
    {
        return max(1, min(500, (int) config('restrictions.vpn_registry.max_entries', 250)));
    }

}
