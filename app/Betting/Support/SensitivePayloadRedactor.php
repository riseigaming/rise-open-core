<?php

namespace App\Betting\Support;

class SensitivePayloadRedactor
{
    private const REDACTED = '[redacted]';

    private const SENSITIVE_KEY_NEEDLES = [
        'quote_ref',
        'adapter_vps_quote_ref',
        'source_provider',
        'source_payload',
        'raw_payload',
        'provider_payload',
        'api_key',
        'apikey',
        'api_token',
        'authorization',
        'bearer',
        'token',
        'password',
        'secret',
    ];

    public function redact(mixed $value): mixed
    {
        return $this->redactValue($value);
    }

    private function redactValue(mixed $value): mixed
    {
        if (is_array($value)) {
            $redacted = [];

            foreach ($value as $key => $item) {
                if ($this->isSensitiveKey((string) $key)) {
                    $redacted[$this->redactedKey($redacted)] = self::REDACTED;
                    continue;
                }

                $redacted[$key] = $this->redactValue($item);
            }

            return $redacted;
        }

        if (is_string($value) && $this->containsSensitiveString($value)) {
            return self::REDACTED;
        }

        return $value;
    }

    private function redactedKey(array $payload): string
    {
        $base = 'redacted_field';
        if (!array_key_exists($base, $payload)) {
            return $base;
        }

        $index = 2;
        while (array_key_exists($base . '_' . $index, $payload)) {
            $index++;
        }

        return $base . '_' . $index;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower(str_replace(['-', '.'], '_', $key));

        foreach (self::SENSITIVE_KEY_NEEDLES as $needle) {
            if (str_contains($normalized, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function containsSensitiveString(string $value): bool
    {
        $normalized = strtolower($value);

        return str_contains($normalized, 'bearer ')
            || str_contains($normalized, 'apikey=')
            || str_contains($normalized, 'api_key=')
            || str_contains($normalized, 'api-token=')
            || str_contains($normalized, 'api_token=')
            || str_contains($normalized, 'authorization:');
    }
}
