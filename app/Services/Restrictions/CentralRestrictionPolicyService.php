<?php

namespace App\Services\Restrictions;

use App\SiteSettings;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class CentralRestrictionPolicyService
{
    public const SETTINGS_KEY = 'central_restrictions_v1';
    public const SETTINGS_VERSION = 1;

    public const PRODUCT_SITE = 'site';
    public const PRODUCT_CASINO = 'casino';
    public const PRODUCT_SPORTSBOOK = 'sportsbook';
    public const PRODUCT_CHESSBET = 'chessbet';
    public const PRODUCT_PAYMENTS = 'payments';

    public const PRODUCTS = [
        self::PRODUCT_SITE,
        self::PRODUCT_CASINO,
        self::PRODUCT_SPORTSBOOK,
        self::PRODUCT_CHESSBET,
        self::PRODUCT_PAYMENTS,
    ];

    public const REASON_GEO_RESTRICTED = 'geo_restricted';
    public const REASON_COUNTRY_BYPASSED_VPN_PROXY = 'country_restriction_bypassed_vpn_proxy';
    public const REASON_VPN_PROXY_ALLOWED = 'vpn_proxy_allowed';
    public const REASON_VPN_PROXY_BLOCKED = 'vpn_proxy_blocked';

    public const VPN_POLICY_ALLOW = 'allow';
    public const VPN_POLICY_BLOCK = 'block';
    public const VPN_POLICIES = [
        self::VPN_POLICY_ALLOW,
        self::VPN_POLICY_BLOCK,
    ];

    public function storageAvailable(): bool
    {
        return Schema::hasTable('site_settings');
    }

    public function settings(): array
    {
        if (!$this->storageAvailable()) {
            return $this->normalize([]);
        }

        $raw = (string) SiteSettings::query()
            ->where('key', self::SETTINGS_KEY)
            ->value('value');
        $decoded = json_decode($raw, true);

        return $this->normalize(is_array($decoded) ? $decoded : []);
    }

    /**
     * This service decides only the country restriction. Its result must still
     * be combined with existing product, provider, maintenance and wallet gates.
     */
    public function decideCountryRestriction(?int $countryId, string $productKey, array $context = []): RestrictionDecision
    {
        $productKey = $this->normalizeProduct($productKey);
        $settings = $this->settings();
        $entry = $countryId !== null
            ? ($settings['countries'][(string) $countryId] ?? null)
            : null;
        $geoRestricted = is_array($entry)
            && in_array($productKey, (array) ($entry['products'] ?? []), true);
        $vpnProxyDetected = (bool) ($context['vpn_proxy_detected'] ?? false);
        $vpnPolicy = (string) ($settings['vpn_policy'] ?? self::VPN_POLICY_ALLOW);

        // A failed VPN/proxy lookup is an unknown signal, never evidence that
        // the visitor is using a VPN. The explicit policy applies only to a
        // positively confirmed VPN/proxy signal.
        if ($vpnProxyDetected && $vpnPolicy === self::VPN_POLICY_BLOCK) {
            return new RestrictionDecision(
                false,
                $productKey,
                $countryId,
                self::REASON_VPN_PROXY_BLOCKED,
                self::SETTINGS_KEY,
                self::SETTINGS_VERSION,
                $geoRestricted,
                true,
                false,
                $context
            );
        }

        if ($vpnProxyDetected) {
            return new RestrictionDecision(
                true,
                $productKey,
                $countryId,
                $geoRestricted
                    ? self::REASON_COUNTRY_BYPASSED_VPN_PROXY
                    : self::REASON_VPN_PROXY_ALLOWED,
                self::SETTINGS_KEY,
                self::SETTINGS_VERSION,
                $geoRestricted,
                true,
                $geoRestricted,
                $context
            );
        }

        if ($geoRestricted) {
            return new RestrictionDecision(
                false,
                $productKey,
                $countryId,
                (string) ($entry['reason_code'] ?? self::REASON_GEO_RESTRICTED),
                self::SETTINGS_KEY,
                self::SETTINGS_VERSION,
                true,
                false,
                false,
                $context
            );
        }

        return new RestrictionDecision(
            true,
            $productKey,
            $countryId,
            $countryId === null ? 'country_unavailable' : 'country_not_restricted',
            self::SETTINGS_KEY,
            self::SETTINGS_VERSION,
            false,
            $vpnProxyDetected,
            false,
            $context
        );
    }

    private function normalize(array $settings): array
    {
        $countries = [];

        foreach ((array) ($settings['countries'] ?? []) as $countryId => $entry) {
            $countryId = (int) $countryId;
            if ($countryId <= 0 || !is_array($entry)) {
                continue;
            }

            $products = $this->normalizeProducts((array) ($entry['products'] ?? []));
            $countries[(string) $countryId] = [
                'products' => $products,
                'reason_code' => $this->normalizeReasonCode((string) ($entry['reason_code'] ?? self::REASON_GEO_RESTRICTED)),
                'reason' => $this->normalizeReason($entry['reason'] ?? null),
                'note' => $this->normalizeNote($entry['note'] ?? null),
                'updated_at' => $entry['updated_at'] ?? null,
                'updated_by' => isset($entry['updated_by']) ? (int) $entry['updated_by'] : null,
            ];
        }

        ksort($countries, SORT_NUMERIC);

        $vpnPolicy = strtolower(trim((string) ($settings['vpn_policy'] ?? self::VPN_POLICY_ALLOW)));
        if (!in_array($vpnPolicy, self::VPN_POLICIES, true)) {
            $vpnPolicy = self::VPN_POLICY_ALLOW;
        }
        $vpnPolicyMeta = is_array($settings['vpn_policy_meta'] ?? null)
            ? $settings['vpn_policy_meta']
            : [];

        return [
            'version' => self::SETTINGS_VERSION,
            'vpn_policy' => $vpnPolicy,
            'vpn_policy_meta' => [
                'reason' => $this->normalizeReason($vpnPolicyMeta['reason'] ?? null),
                'note' => $this->normalizeNote($vpnPolicyMeta['note'] ?? null),
                'updated_at' => $vpnPolicyMeta['updated_at'] ?? null,
                'updated_by' => isset($vpnPolicyMeta['updated_by']) ? (int) $vpnPolicyMeta['updated_by'] : null,
            ],
            'countries' => $countries,
        ];
    }

    private function normalizeProducts(array $products): array
    {
        $normalized = array_values(array_unique(array_filter(array_map(
            fn ($product): string => strtolower(trim((string) $product)),
            $products
        ), fn (string $product): bool => in_array($product, self::PRODUCTS, true))));

        usort($normalized, fn (string $left, string $right): int => array_search($left, self::PRODUCTS, true) <=> array_search($right, self::PRODUCTS, true));

        return $normalized;
    }

    private function normalizeProduct(string $productKey): string
    {
        $productKey = strtolower(trim($productKey));
        if (!in_array($productKey, self::PRODUCTS, true)) {
            throw new InvalidArgumentException('Unsupported restriction product.');
        }

        return $productKey;
    }

    private function normalizeReasonCode(string $reasonCode): string
    {
        return $reasonCode === self::REASON_GEO_RESTRICTED
            ? $reasonCode
            : self::REASON_GEO_RESTRICTED;
    }

    private function normalizeNote(mixed $note): ?string
    {
        $note = trim((string) $note);

        return $note !== '' ? mb_substr($note, 0, 500) : null;
    }

    private function normalizeReason(mixed $reason): ?string
    {
        $reason = trim((string) $reason);

        return $reason !== '' ? mb_substr($reason, 0, 160) : null;
    }

}
