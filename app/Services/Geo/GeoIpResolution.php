<?php

namespace App\Services\Geo;

class GeoIpResolution
{
    public function __construct(
        public readonly ?string $requestIp,
        public readonly ?string $observedCountryCode,
        public readonly bool $vpnProxyDetected,
        public readonly bool $countryLookupFailed,
        public readonly bool $vpnProxyLookupFailed,
        public readonly string $source,
        public readonly string $version,
        public readonly string $vpnSignalState = VpnSignalResolution::UNKNOWN,
        public readonly ?string $vpnSignalSource = null,
        public readonly ?string $vpnRegistryMatchType = null,
        public readonly ?string $vpnRegistryLabel = null
    ) {
    }

    public static function unavailable(?string $requestIp, string $source = 'unavailable'): self
    {
        return new self($requestIp, null, false, true, false, $source, 'v1', VpnSignalResolution::UNKNOWN, $source);
    }

    public function restrictionContext(): array
    {
        return [
            'observed_country' => $this->observedCountryCode,
            'vpn_proxy_detected' => $this->vpnProxyDetected,
            'country_lookup_failed' => $this->countryLookupFailed,
            'vpn_proxy_lookup_failed' => $this->vpnProxyLookupFailed,
            'vpn_signal_state' => $this->vpnSignalState,
            'vpn_signal_source' => $this->vpnSignalSource,
            'vpn_registry_match_type' => $this->vpnRegistryMatchType,
            'vpn_registry_label' => $this->vpnRegistryLabel,
            'geo_lookup_source' => $this->source,
            'geo_lookup_version' => $this->version,
            'online' => true,
        ];
    }
}
