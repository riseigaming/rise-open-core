<?php

namespace App\Services\Geo;

class VpnSignalResolution
{
    public const CONFIRMED = 'confirmed';
    public const NOT_DETECTED = 'not_detected';
    public const UNKNOWN = 'unknown';

    public function __construct(
        public readonly string $state,
        public readonly string $source,
        public readonly ?string $matchedType = null,
        public readonly ?string $matchedValue = null,
        public readonly ?string $label = null
    ) {
    }

    public static function confirmed(string $source, ?string $matchedType = null, ?string $matchedValue = null, ?string $label = null): self
    {
        return new self(self::CONFIRMED, $source, $matchedType, $matchedValue, $label);
    }

    public static function notDetected(string $source): self
    {
        return new self(self::NOT_DETECTED, $source);
    }

    public static function unknown(string $source): self
    {
        return new self(self::UNKNOWN, $source);
    }

    public function confirmedVpn(): bool
    {
        return $this->state === self::CONFIRMED;
    }
}
