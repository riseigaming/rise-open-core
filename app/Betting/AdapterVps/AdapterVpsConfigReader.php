<?php

namespace App\Betting\AdapterVps;

class AdapterVpsConfigReader
{
    public const DEFAULT_PATH = 'app/private/adapter-vps.json';

    private $path;

    public function __construct(?string $path = null)
    {
        $this->path = $path ?: storage_path(self::DEFAULT_PATH);
    }

    public function path(): string
    {
        return $this->path;
    }

    public function read(): AdapterVpsRuntimeConfig
    {
        $localParityEnabled = app()->environment(['local', 'testing'])
            && (bool) config('betting.sportsbook_local_parity.enabled', false);
        $readSource = (string) config('betting.sportsbook_local_parity.read_source', 'adapter_live');
        $localWriteTarget = (string) config('betting.sportsbook_local_parity.write_target', 'disabled') === 'local'
            || (bool) config('betting.sportsbook_local_parity.finance_sandbox', false);
        $localFinanceEnabled = (bool) config('betting.sportsbook_local_parity.finance_enabled', false)
            || (bool) config('betting.sportsbook_local_parity.finance_sandbox', false);

        if ($localParityEnabled && $readSource !== 'adapter_live' && $localWriteTarget && $localFinanceEnabled) {
            return AdapterVpsRuntimeConfig::fromArray([
                'enabled' => true,
                'mode' => 'local_sandbox',
                'base_url' => 'http://127.0.0.1/local-sportsbook-sandbox',
                'auth_token' => 'local-sandbox-token',
                'connect_timeout_ms' => 50,
                'read_timeout_ms' => 50,
                'allow_public_prematch' => false,
                'allow_quote_verify' => true,
                'allow_acceptance' => true,
            ]);
        }

        if (!is_file($this->path)) {
            return AdapterVpsRuntimeConfig::disabled('missing_config', 'Adapter VPS private config file is missing.');
        }

        $raw = file_get_contents($this->path);
        if ($raw === false || trim($raw) === '') {
            return AdapterVpsRuntimeConfig::disabled('missing_config', 'Adapter VPS private config file is empty or unreadable.');
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            return AdapterVpsRuntimeConfig::disabled('missing_config', 'Adapter VPS private config JSON is invalid.');
        }

        if ($localParityEnabled && $readSource === 'adapter_live') {
            $decoded['read_timeout_ms'] = max(
                (int) ($decoded['read_timeout_ms'] ?? 0),
                max(1, (int) config('betting.sportsbook_local_parity.adapter_live_read_timeout_seconds', 90)) * 1000
            );
        }

        return AdapterVpsRuntimeConfig::fromArray($decoded);
    }
}
