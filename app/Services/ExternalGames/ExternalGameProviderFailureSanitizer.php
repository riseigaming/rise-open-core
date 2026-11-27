<?php

namespace App\Services\ExternalGames;

use Illuminate\Http\Client\Response;

class ExternalGameProviderFailureSanitizer
{
    private const SAFE_RESPONSE_KEYS = [
        'code',
        'error_code',
        'status',
        'message',
        'reason',
        'country',
        'currency',
    ];

    private const MAX_MESSAGE_LENGTH = 240;

    private const SAFE_ENVELOPE_KEYS = [
        'data',
        'error',
        'errors',
        'response',
    ];

    public static function fromResponse(
        Response $response,
        array $context,
        string $fallbackClassification = ExternalGameProviderLaunchException::PROVIDER_ERROR
    ): array {
        $safeResponse = self::allowedResponseFields($response->json());
        $details = array_merge(self::sanitizeContext($context), [
            'upstream_http_status' => self::httpStatus($response->status()),
            'provider_error_code' => self::shortText(
                $safeResponse['error_code'] ?? $safeResponse['code'] ?? null,
                96
            ),
            'provider_status' => self::shortText($safeResponse['status'] ?? null, 64),
            'provider_error_message' => self::message(
                $safeResponse['message'] ?? null
            ),
            'provider_error_reason' => self::message($safeResponse['reason'] ?? null),
            'provider_country' => self::country($safeResponse['country'] ?? null),
            'provider_currency' => self::currency($safeResponse['currency'] ?? null),
        ]);

        $details['classification'] = self::classification($details, $fallbackClassification);

        return array_filter(
            $details,
            static fn (mixed $value): bool => $value !== null && $value !== ''
        );
    }

    public static function withoutResponse(array $context, string $classification): array
    {
        return array_filter(
            array_merge(self::sanitizeContext($context), ['classification' => $classification]),
            static fn (mixed $value): bool => $value !== null && $value !== ''
        );
    }

    public static function sanitizeContext(array $context): array
    {
        return [
            'provider_alias' => self::shortText($context['provider_alias'] ?? null, 96),
            'game_id' => self::shortText($context['game_id'] ?? null, 191),
            'currency' => self::currency($context['currency'] ?? null),
            'payload_ip' => self::ip($context['payload_ip'] ?? $context['ip_address'] ?? null),
            'observed_country' => self::country($context['observed_country'] ?? null),
        ];
    }

    public static function sanitizeStoredDetails(array $details): array
    {
        $classification = (string) ($details['classification'] ?? '');
        if (!in_array($classification, [
            ExternalGameProviderLaunchException::BLOCKED_GEO,
            ExternalGameProviderLaunchException::BLOCKED_CURRENCY,
            ExternalGameProviderLaunchException::PROVIDER_ERROR,
            ExternalGameProviderLaunchException::INTEGRATION_ERROR,
        ], true)) {
            $classification = ExternalGameProviderLaunchException::PROVIDER_ERROR;
        }

        return array_filter([
            'classification' => $classification,
            'upstream_http_status' => self::httpStatus((int) ($details['upstream_http_status'] ?? 0)),
            'provider_error_code' => self::shortText($details['provider_error_code'] ?? null, 96),
            'provider_status' => self::shortText($details['provider_status'] ?? null, 64),
            'provider_error_message' => self::message($details['provider_error_message'] ?? null),
            'provider_error_reason' => self::message($details['provider_error_reason'] ?? null),
            'provider_alias' => self::shortText($details['provider_alias'] ?? null, 96),
            'game_id' => self::shortText($details['game_id'] ?? null, 191),
            'currency' => self::currency($details['currency'] ?? null),
            'payload_ip' => self::ip($details['payload_ip'] ?? null),
            'observed_country' => self::country($details['observed_country'] ?? null),
            'provider_country' => self::country($details['provider_country'] ?? null),
            'provider_currency' => self::currency($details['provider_currency'] ?? null),
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    private static function allowedResponseFields(mixed $payload): array
    {
        if (!is_array($payload)) {
            return [];
        }

        $found = [];
        self::collectAllowedFields($payload, $found, 0);

        return $found;
    }

    private static function collectAllowedFields(array $payload, array &$found, int $depth): void
    {
        if ($depth > 3) {
            return;
        }

        foreach ($payload as $key => $value) {
            $normalizedKey = strtolower(trim((string) $key));

            if (
                in_array($normalizedKey, self::SAFE_RESPONSE_KEYS, true)
                && !array_key_exists($normalizedKey, $found)
                && (is_scalar($value) || $value === null)
            ) {
                $found[$normalizedKey] = $value;
            }

            if (is_array($value) && in_array($normalizedKey, self::SAFE_ENVELOPE_KEYS, true)) {
                self::collectAllowedFields($value, $found, $depth + 1);
            }
        }
    }

    private static function classification(array $details, string $fallback): string
    {
        $code = strtoupper(trim((string) ($details['provider_error_code'] ?? '')));
        $status = strtoupper(trim((string) ($details['provider_status'] ?? '')));
        $message = trim((string) ($details['provider_error_message'] ?? ''));
        $reason = trim((string) ($details['provider_error_reason'] ?? ''));
        $signal = trim($code . ' ' . $status . ' ' . $message . ' ' . $reason);

        if (self::isExplicitGeoSignal($signal)) {
            return ExternalGameProviderLaunchException::BLOCKED_GEO;
        }

        if (self::isExplicitCurrencySignal($signal)) {
            return ExternalGameProviderLaunchException::BLOCKED_CURRENCY;
        }

        if (self::isExplicitTransportSignal($signal)) {
            return ExternalGameProviderLaunchException::INTEGRATION_ERROR;
        }

        return in_array($fallback, [
            ExternalGameProviderLaunchException::BLOCKED_GEO,
            ExternalGameProviderLaunchException::BLOCKED_CURRENCY,
            ExternalGameProviderLaunchException::PROVIDER_ERROR,
            ExternalGameProviderLaunchException::INTEGRATION_ERROR,
        ], true) ? $fallback : ExternalGameProviderLaunchException::PROVIDER_ERROR;
    }

    private static function isExplicitGeoSignal(string $signal): bool
    {
        if ($signal === '') {
            return false;
        }

        return preg_match(
            '/(?:GEO|COUNTR(?:Y|IES)|JURISDICTION|TERRITOR(?:Y|IES)).{0,80}(?:BLOCK|RESTRICT|DENI|FORBID|NOT[ _-]?(?:ALLOWED|AVAILABLE|SUPPORTED)|UNAVAILABLE)|(?:BLOCK|RESTRICT|DENI|FORBID|NOT[ _-]?(?:ALLOWED|AVAILABLE|SUPPORTED)|UNAVAILABLE).{0,80}(?:GEO|COUNTR(?:Y|IES)|JURISDICTION|TERRITOR(?:Y|IES))/i',
            $signal
        ) === 1;
    }

    private static function isExplicitCurrencySignal(string $signal): bool
    {
        if ($signal === '') {
            return false;
        }

        return preg_match(
            '/(?:CURRENCY|CURRENCIES).{0,80}(?:BLOCK|RESTRICT|DENI|FORBID|INVALID|NOT[ _-]?(?:ALLOWED|AVAILABLE|SUPPORTED)|UNSUPPORTED)|(?:BLOCK|RESTRICT|DENI|FORBID|INVALID|NOT[ _-]?(?:ALLOWED|AVAILABLE|SUPPORTED)|UNSUPPORTED).{0,80}(?:CURRENCY|CURRENCIES)/i',
            $signal
        ) === 1;
    }

    private static function isExplicitTransportSignal(string $signal): bool
    {
        if ($signal === '') {
            return false;
        }

        return preg_match(
            '/(?:BAD[ _-]?GATEWAY|GATEWAY[ _-]?(?:TIMEOUT|UNAVAILABLE|ERROR)|UPSTREAM[ _-]?(?:TIMEOUT|UNAVAILABLE|CONNECTION[ _-]?(?:FAILED|ERROR))|TRANSPORT[ _-]?(?:FAILED|ERROR))/i',
            $signal
        ) === 1;
    }

    private static function message(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $message = html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $message = preg_replace('/(?:https?|wss?):\/\/[^\s<>"\']+/iu', '[redacted-url]', $message) ?? '';
        $message = preg_replace('/\bBearer\s+[A-Za-z0-9._~+\/=-]+/iu', 'Bearer [redacted]', $message) ?? '';
        $message = preg_replace(
            '/\b(authorization|access[_ -]?token|refresh[_ -]?token|launch[_ -]?token|session[_ -]?token|token|secret|password|launch[_ -]?url)\b\s*[:=]\s*[^\s,;]+/iu',
            '$1=[redacted]',
            $message
        ) ?? '';
        $message = preg_replace('/\beyJ[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}(?:\.[A-Za-z0-9_-]{10,})?\b/u', '[redacted-token]', $message) ?? '';
        $message = preg_replace('/\b[A-Za-z0-9+\/_=-]{40,}\b/u', '[redacted]', $message) ?? '';
        $message = preg_replace('/(?:\r?\n|\r)\s*(?:#\d+|at\s+\S+).*/isu', '', $message) ?? '';
        $message = preg_replace('/\s+/u', ' ', $message) ?? '';
        $message = trim($message);

        return $message !== '' ? mb_substr($message, 0, self::MAX_MESSAGE_LENGTH) : null;
    }

    private static function shortText(mixed $value, int $limit): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $value = trim(strip_tags((string) $value));
        if ($value === '') {
            return null;
        }

        $value = preg_replace('/[^A-Za-z0-9._:\/-]+/u', '_', $value) ?? '';
        $value = trim($value, '_');

        return $value !== '' ? mb_substr($value, 0, $limit) : null;
    }

    private static function currency(mixed $value): ?string
    {
        $value = strtoupper(trim((string) $value));

        return preg_match('/^[A-Z0-9]{2,12}$/', $value) === 1 ? $value : null;
    }

    private static function country(mixed $value): ?string
    {
        $value = strtoupper(trim((string) $value));

        return preg_match('/^[A-Z]{2}$/', $value) === 1 ? $value : null;
    }

    private static function ip(mixed $value): ?string
    {
        $value = trim((string) $value);

        return filter_var($value, FILTER_VALIDATE_IP) !== false ? $value : null;
    }

    private static function httpStatus(int $status): ?int
    {
        return $status >= 100 && $status <= 599 ? $status : null;
    }
}
