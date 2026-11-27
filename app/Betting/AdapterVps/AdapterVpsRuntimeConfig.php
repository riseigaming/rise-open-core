<?php

namespace App\Betting\AdapterVps;

class AdapterVpsRuntimeConfig
{
    private $enabled;
    private $usable;
    private $code;
    private $reason;
    private $mode;
    private $baseUrl;
    private $authToken;
    private $connectTimeoutMs;
    private $readTimeoutMs;
    private $allowPublicPrematch;
    private $allowQuoteVerify;
    private $allowAcceptance;

    private function __construct(array $attributes)
    {
        $this->enabled = (bool) ($attributes['enabled'] ?? false);
        $this->usable = (bool) ($attributes['usable'] ?? false);
        $this->code = (string) ($attributes['code'] ?? 'disabled');
        $this->reason = (string) ($attributes['reason'] ?? '');
        $this->mode = (string) ($attributes['mode'] ?? 'disabled');
        $this->baseUrl = $attributes['base_url'] ?? null;
        $this->authToken = $attributes['auth_token'] ?? null;
        $this->connectTimeoutMs = max(1, (int) ($attributes['connect_timeout_ms'] ?? 800));
        $this->readTimeoutMs = max(1, (int) ($attributes['read_timeout_ms'] ?? 2500));
        $this->allowPublicPrematch = (bool) ($attributes['allow_public_prematch'] ?? false);
        $this->allowQuoteVerify = (bool) ($attributes['allow_quote_verify'] ?? false);
        $this->allowAcceptance = (bool) ($attributes['allow_acceptance'] ?? false);
    }

    public static function disabled(string $code, string $reason): self
    {
        return new self([
            'enabled' => false,
            'usable' => false,
            'code' => $code,
            'reason' => $reason,
            'mode' => 'disabled',
        ]);
    }

    public static function fromArray(array $data): self
    {
        if (!(bool) ($data['enabled'] ?? false)) {
            return self::disabled('disabled', 'Adapter VPS private config is disabled.');
        }

        $baseUrl = rtrim(trim((string) ($data['base_url'] ?? '')), '/');
        $authToken = trim((string) ($data['auth_token'] ?? ''));

        if ($baseUrl === '' || $authToken === '') {
            return self::disabled('missing_config', 'Adapter VPS private config requires base_url and auth_token.');
        }

        if (!filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            return self::disabled('missing_config', 'Adapter VPS private config base_url is invalid.');
        }

        return new self([
            'enabled' => true,
            'usable' => true,
            'code' => 'ok',
            'reason' => '',
            'mode' => (string) ($data['mode'] ?? 'shadow'),
            'base_url' => $baseUrl,
            'auth_token' => $authToken,
            'connect_timeout_ms' => $data['connect_timeout_ms'] ?? 800,
            'read_timeout_ms' => $data['read_timeout_ms'] ?? 2500,
            'allow_public_prematch' => $data['allow_public_prematch'] ?? false,
            'allow_quote_verify' => $data['allow_quote_verify'] ?? false,
            'allow_acceptance' => $data['allow_acceptance'] ?? false,
        ]);
    }

    public function isUsable(): bool
    {
        return $this->usable;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function baseUrl(): string
    {
        return (string) $this->baseUrl;
    }

    public function mode(): string
    {
        return $this->mode;
    }

    public function authToken(): string
    {
        return (string) $this->authToken;
    }

    public function connectTimeoutSeconds(): float
    {
        return $this->connectTimeoutMs / 1000;
    }

    public function readTimeoutSeconds(): float
    {
        return $this->readTimeoutMs / 1000;
    }

    public function allowPublicPrematch(): bool
    {
        return $this->allowPublicPrematch;
    }

    public function allowQuoteVerify(): bool
    {
        return $this->allowQuoteVerify;
    }

    public function allowAcceptance(): bool
    {
        return $this->allowAcceptance;
    }

    public function safeSummary(): array
    {
        return [
            'enabled' => $this->enabled,
            'usable' => $this->usable,
            'code' => $this->code,
            'reason' => $this->reason,
            'mode' => $this->mode,
            'base_url' => $this->baseUrl,
            'has_auth_token' => $this->authToken !== null && $this->authToken !== '',
            'connect_timeout_ms' => $this->connectTimeoutMs,
            'read_timeout_ms' => $this->readTimeoutMs,
            'allow_public_prematch' => $this->allowPublicPrematch,
            'allow_quote_verify' => $this->allowQuoteVerify,
            'allow_acceptance' => $this->allowAcceptance,
        ];
    }
}
