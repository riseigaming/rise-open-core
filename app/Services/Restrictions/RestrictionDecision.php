<?php

namespace App\Services\Restrictions;

class RestrictionDecision
{
    public function __construct(
        public readonly bool $allowed,
        public readonly string $productKey,
        public readonly ?int $countryId,
        public readonly string $reasonCode,
        public readonly string $source,
        public readonly int $version,
        public readonly bool $geoRestricted,
        public readonly bool $vpnProxyDetected,
        public readonly bool $countryRestrictionBypassed,
        public readonly array $context = []
    ) {
    }

    /**
     * Stable, redacted fields intended for a later Ops Radar event adapter.
     */
    public function toEventFields(): array
    {
        return [
            'restriction_allowed' => $this->allowed,
            'restriction_reason_code' => $this->reasonCode,
            'restriction_source' => $this->source,
            'restriction_version' => $this->version,
            'restriction_scope' => 'country_only',
            'country_id' => $this->countryId,
            'observed_country' => $this->nullableString($this->context['observed_country'] ?? null),
            'vpn_proxy_detected' => $this->vpnProxyDetected,
            'country_lookup_failed' => (bool) ($this->context['country_lookup_failed'] ?? false),
            'vpn_proxy_lookup_failed' => (bool) ($this->context['vpn_proxy_lookup_failed'] ?? false),
            'vpn_signal_state' => $this->nullableString($this->context['vpn_signal_state'] ?? null),
            'vpn_signal_source' => $this->nullableString($this->context['vpn_signal_source'] ?? null),
            'vpn_registry_match_type' => $this->nullableString($this->context['vpn_registry_match_type'] ?? null),
            'vpn_registry_label' => $this->nullableString($this->context['vpn_registry_label'] ?? null),
            'geo_lookup_source' => $this->nullableString($this->context['geo_lookup_source'] ?? null),
            'geo_lookup_version' => $this->nullableString($this->context['geo_lookup_version'] ?? null),
            'country_changed' => (bool) ($this->context['country_changed'] ?? false),
            'multiple_countries' => (bool) ($this->context['multiple_countries'] ?? false),
            'geo_restricted' => $this->geoRestricted,
            'country_restriction_bypassed' => $this->countryRestrictionBypassed,
            'product' => $this->productKey,
            'payment_method' => $this->nullableString($this->context['payment_method'] ?? null),
            'game' => $this->nullableString($this->context['game'] ?? null),
            'provider' => $this->nullableString($this->context['provider'] ?? null),
            'online' => array_key_exists('online', $this->context)
                ? (bool) $this->context['online']
                : null,
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
