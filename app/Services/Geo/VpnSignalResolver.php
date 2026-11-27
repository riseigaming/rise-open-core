<?php

namespace App\Services\Geo;

use Throwable;

class VpnSignalResolver
{
    public function __construct(
        private readonly LocalVpnNetworkRegistry $registry
    ) {
    }

    public function resolve(?string $ip): VpnSignalResolution
    {
        if (!is_string($ip) || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return VpnSignalResolution::unknown('local_registry_ip_unavailable');
        }

        try {
            if (!$this->registry->storageAvailable()) {
                return VpnSignalResolution::unknown('local_registry_unavailable');
            }

            $match = $this->registry->match($ip);
            if (!is_array($match)) {
                return VpnSignalResolution::notDetected('local_registry');
            }

            return VpnSignalResolution::confirmed(
                'local_registry',
                (string) $match['type'],
                (string) $match['value'],
                (string) $match['label']
            );
        } catch (Throwable) {
            return VpnSignalResolution::unknown('local_registry_error');
        }
    }
}
