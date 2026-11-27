<?php

namespace App\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;
use RuntimeException;
use Throwable;

class DatabaseSafety
{
    /**
     * Bounded identity for read-only health consumers. This deliberately omits
     * URLs, hosts, sockets, usernames, passwords, env-file values, and probes.
     *
     * @return array<string, scalar>
     */
    public function healthSummary(): array
    {
        $environment = app()->environment();
        $connection = (string) config('database.default');
        $active = (array) config("database.connections.{$connection}", []);
        $database = (string) ($active['database'] ?? '');

        return [
            'environment' => $environment,
            'config_cached' => app()->configurationIsCached(),
            'connection' => $connection,
            'driver' => (string) ($active['driver'] ?? ''),
            'database' => $database,
            'connection_allowed' => $this->isAllowedConnection($connection),
            'database_allowed' => $this->isAllowedDatabase($environment, $database),
        ];
    }

    public function summary(?string $command = null): array
    {
        $defaultConnection = (string) config('database.default');
        $connections = (array) config('database.connections', []);
        $activeConnection = (array) Arr::get($connections, $defaultConnection, []);
        $testingDefaults = $this->testingDefaults();
        $latestSnapshot = $this->latestSnapshot();
        $envFileDatabaseConfig = $this->envFileDatabaseConfig();
        $envDrift = $this->envFileDatabaseDrift($defaultConnection, $activeConnection, $envFileDatabaseConfig);

        $summary = [
            'environment' => app()->environment(),
            'command' => $command,
            'config_cached' => app()->configurationIsCached(),
            'default_connection' => $defaultConnection,
            'connections' => collect($connections)->map(function (array $config, string $name): array {
                return $this->mapConnection($name, $config);
            })->values()->all(),
            'active_connection' => $this->mapConnection($defaultConnection, $activeConnection),
            'queue' => [
                'default' => (string) config('queue.default'),
                'failed_driver' => (string) config('queue.failed.driver'),
                'failed_database' => (string) config('queue.failed.database'),
            ],
            'cache' => [
                'default' => (string) config('cache.default'),
            ],
            'testing_defaults' => $testingDefaults,
            'env_file_database' => $envFileDatabaseConfig,
            'env_drift' => $envDrift,
            'latest_snapshot' => $latestSnapshot,
            // Identity/TCP diagnostics are for explicit operator commands, not
            // the per-minute runner. Safety warnings use config/env/snapshot
            // checks above; destructive-command guards retain their full probe.
            'runtime_connection' => $command === 'schedule:run' || !$this->canProbeRuntimeConnection()
                ? []
                : $this->runtimeConnectionIdentity($defaultConnection, $activeConnection),
        ];

        $summary['warnings'] = $this->warnings($summary);

        return $summary;
    }

    /** Config-only probe gate; destructive-command authorization remains below. */
    public function canProbeRuntimeConnection(): bool
    {
        $connection = (string) config('database.default');
        $active = (array) config("database.connections.{$connection}", []);

        return $this->isAllowedConnection($connection)
            && $this->isSafeHost((string) ($active['driver'] ?? ''), (string) ($active['host'] ?? ''), (string) ($active['unix_socket'] ?? ''))
            && $this->isAllowedDatabase(app()->environment(), (string) ($active['database'] ?? ''))
            && (!app()->configurationIsCached()
                || $this->envFileDatabaseDrift($connection, $active, $this->envFileDatabaseConfig()) === []);
    }

    public function announceForCommand(?string $command, $output): void
    {
        if ($command === null) {
            return;
        }

        if (!$this->shouldObserveCommand($command)) {
            return;
        }

        $summary = $this->summary($command);
        // The per-minute scheduler only needs actionable safety warnings.
        // Its normal cached-configuration notice is expected in production
        // and otherwise repeats twice per minute in scheduler.log. Preserve
        // the full banner on explicit DB/betting commands and every material
        // connection, drift, host or snapshot warning.
        $warnings = (array) $summary['warnings'];
        if ($command === 'schedule:run') {
            $warnings = array_values(array_filter($warnings, static fn ($warning): bool =>
                $warning !== 'Laravel config cache is enabled; stale DB settings can survive .env edits until optimize:clear.'
            ));
            if ($warnings === []) {
                return;
            }
        }
        $active = $summary['active_connection'];
        $snapshot = $summary['latest_snapshot'];
        $snapshotLabel = $snapshot
            ? basename((string) $snapshot['path']) . ' (' . $snapshot['age_minutes'] . 'm old)'
            : 'none';

        $output->writeln(sprintf(
            '<comment>[db-safety]</comment> env=%s command=%s conn=%s driver=%s db=%s host=%s port=%s socket=%s queue=%s config_cache=%s snapshot=%s',
            $summary['environment'],
            $command,
            $active['name'] ?: 'n/a',
            $active['driver'] ?: 'n/a',
            $active['database'] ?: 'n/a',
            $active['host'] ?: 'n/a',
            $active['port'] ?: 'n/a',
            $active['socket'] ?: 'n/a',
            $summary['queue']['default'] ?: 'n/a',
            $summary['config_cached'] ? 'yes' : 'no',
            $snapshotLabel
        ));

        foreach ($warnings as $warning) {
            $output->writeln('<comment>[db-safety]</comment> warning=' . $warning);
        }
    }

    public function guardDestructiveCommand(string $command, ?\Symfony\Component\Console\Input\InputInterface $input = null): void
    {
        if (!(bool) config('db_safety.guard_destructive_commands', false)) {
            return;
        }

        if (!$this->isDestructiveCommand($command)) {
            return;
        }

        $summary = $this->summary($command);
        $active = $summary['active_connection'];
        $violations = [];

        if (!$this->isAllowedConnection($active['name'])) {
            $violations[] = 'Active connection is not in the DB_SAFETY_ALLOWED_CONNECTIONS allowlist.';
        }

        if (!$this->isSafeHost($active['driver'], $active['host'], $active['socket'])) {
            $violations[] = 'Active database host/socket does not look local-safe.';
        }

        if (!$this->isAllowedDatabase($summary['environment'], $active['database'])) {
            $violations[] = 'Active database is not in the DB_SAFETY_ALLOWED_DATABASES allowlist.';
        }

        if ($summary['config_cached'] && $summary['env_drift'] !== []) {
            $violations[] = 'Config is cached and DB settings differ from the current .env file.';
        }

        // Static target rejects retain the existing early-rejection behavior.
        if ($violations === []) {
            $identityViolation = app(LocalDatabaseInstanceIdentity::class)->violation($summary, base_path());
            if ($identityViolation !== null) $violations[] = $identityViolation;
        }

        // Only the snapshot condition can be replaced. Host/database/cache/identity checks
        // above still apply; all other commands retain the original contract.
        $additive = $violations === [] && $command === 'migrate' && $input !== null
            && app(AdditiveMigrationSafety::class)->approved($input);

        if (
            !$additive
            && !$this->destructiveCommandMaySkipSnapshot($summary)
            && (bool) config('db_safety.require_recent_snapshot', false)
            && !(bool) config('db_safety.allow_unguarded_destructive', false)
            && !$this->hasRecentSnapshot()
        ) {
            $violations[] = 'No recent DB snapshot was found in ' . config('db_safety.snapshot.directory') . '. Create a real native dump of the verified enrolled target; see docs/INSTALL_RUN34.md.';
        }

        if ($violations === []) {
            return;
        }

        throw new RuntimeException(
            '[db-safety] Blocked command "' . $command . '" for database "' . ($active['database'] ?: 'unknown') . "\".\n- "
            . implode("\n- ", $violations)
        );
    }

    public function latestSnapshot(): ?array
    {
        $directory = (string) config('db_safety.snapshot.directory');
        if (!is_dir($directory)) {
            return null;
        }

        $extensions = (array) config('db_safety.snapshot.extensions', []);
        $candidates = [];

        foreach ($extensions as $extension) {
            foreach (glob($directory . '/*.' . $extension) ?: [] as $path) {
                if (is_file($path)) {
                    $candidates[] = $path;
                }
            }
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, static fn (string $left, string $right): int => filemtime($right) <=> filemtime($left));
        $latest = $candidates[0];
        $mtime = filemtime($latest) ?: time();

        return [
            'path' => $latest,
            'mtime' => $mtime,
            'age_minutes' => (int) floor(max(0, time() - $mtime) / 60),
        ];
    }

    public function hasRecentSnapshot(): bool
    {
        $snapshot = $this->latestSnapshot();
        if ($snapshot === null) {
            return false;
        }

        return (int) ($snapshot['age_minutes'] ?? PHP_INT_MAX) <= (int) config('db_safety.snapshot.recent_minutes', 1440);
    }

    public function warnings(array $summary): array
    {
        $warnings = [];
        $active = $summary['active_connection'];

        if ($summary['config_cached']) {
            $warnings[] = 'Laravel config cache is enabled; stale DB settings can survive .env edits until optimize:clear.';
        }

        if ($summary['env_drift'] !== []) {
            $warnings[] = 'Current config does not match .env for: ' . implode(', ', array_keys($summary['env_drift'])) . '.';
        }

        if (!$this->isSafeHost($active['driver'], $active['host'], $active['socket'])) {
            $warnings[] = 'Active DB target does not look local-safe.';
        }

        if (!$this->isAllowedDatabase($summary['environment'], $active['database'])) {
            $warnings[] = 'Active DB is outside the configured allowlist.';
        }

        if ($this->shouldRequireSnapshotWarning($summary)) {
            $warnings[] = 'No recent DB snapshot found for local destructive commands.';
        }

        return $warnings;
    }

    public function shouldObserveCommand(?string $command): bool
    {
        if (!$command) {
            return false;
        }

        return $this->matchesPatterns($command, (array) config('db_safety.observe_commands', []));
    }

    public function isDestructiveCommand(?string $command): bool
    {
        if (!$command) {
            return false;
        }

        return $this->matchesPatterns($command, (array) config('db_safety.destructive_commands', []));
    }

    public function testingDefaults(): array
    {
        $testConnection = getenv('TEST_DB_CONNECTION') ?: null;

        if ($testConnection) {
            return [
                'connection' => $testConnection,
                'database' => (string) (getenv('TEST_DB_DATABASE') ?: ''),
                'host' => (string) (getenv('TEST_DB_HOST') ?: '127.0.0.1'),
                'port' => (string) (getenv('TEST_DB_PORT') ?: '3306'),
            ];
        }

        return [
            'connection' => 'sqlite',
            'database' => ':memory:',
            'host' => '',
            'port' => '',
        ];
    }

    private function mapConnection(string $name, array $config): array
    {
        $driver = (string) ($config['driver'] ?? '');

        return [
            'name' => $name,
            'driver' => $driver,
            'database' => $this->normalizeDatabaseValue($driver, $config['database'] ?? null),
            'host' => (string) ($config['host'] ?? ''),
            'port' => (string) ($config['port'] ?? ''),
            'socket' => (string) ($config['unix_socket'] ?? ''),
            'url' => (string) ($config['url'] ?? ''),
        ];
    }

    private function runtimeConnectionIdentity(string $connectionName, array $activeConnection): array
    {
        $identity = [
            'pdo_transport' => 'unknown',
            'database' => '',
            'runtime_socket' => '',
            'runtime_port' => '',
            'runtime_hostname' => '',
            'runtime_datadir' => '',
            'runtime_server_id' => '',
            'runtime_version' => '',
            'socket_tcp_same_instance' => 'unknown',
            'tcp_probe' => null,
        ];

        if ((string) ($activeConnection['driver'] ?? '') !== 'mysql') {
            return $identity;
        }

        try {
            $pdo = DB::connection($connectionName)->getPdo();
            $connectionStatus = (string) $pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS);
            $identity['pdo_transport'] = Str::contains(Str::lower($connectionStatus), 'unix socket')
                ? 'unix_socket'
                : 'tcp';

            $runtimeRow = (array) DB::selectOne(
                'select database() as db, @@socket as socket, @@port as port, @@hostname as hostname, @@datadir as datadir, @@server_id as server_id, @@version as version'
            );

            $identity['database'] = (string) ($runtimeRow['db'] ?? '');
            $identity['runtime_socket'] = (string) ($runtimeRow['socket'] ?? '');
            $identity['runtime_port'] = (string) ($runtimeRow['port'] ?? '');
            $identity['runtime_hostname'] = (string) ($runtimeRow['hostname'] ?? '');
            $identity['runtime_datadir'] = (string) ($runtimeRow['datadir'] ?? '');
            $identity['runtime_server_id'] = (string) ($runtimeRow['server_id'] ?? '');
            $identity['runtime_version'] = (string) ($runtimeRow['version'] ?? '');

            $tcpProbe = $this->tcpProbeIdentity($activeConnection);
            $identity['tcp_probe'] = $tcpProbe;

            if ($tcpProbe !== null) {
                $sameDatadir = (string) ($tcpProbe['runtime_datadir'] ?? '') !== ''
                    && (string) ($tcpProbe['runtime_datadir'] ?? '') === $identity['runtime_datadir'];
                $sameServerId = (string) ($tcpProbe['runtime_server_id'] ?? '') !== ''
                    && (string) ($tcpProbe['runtime_server_id'] ?? '') === $identity['runtime_server_id'];

                $identity['socket_tcp_same_instance'] = ($sameDatadir && $sameServerId) ? 'yes' : 'no';
            }
        } catch (Throwable) {
            return $identity;
        }

        return $identity;
    }

    private function tcpProbeIdentity(array $activeConnection): ?array
    {
        $host = (string) ($activeConnection['host'] ?? '');
        $port = (string) ($activeConnection['port'] ?? '');
        $database = (string) ($activeConnection['database'] ?? '');
        $username = (string) ($activeConnection['username'] ?? '');
        $password = (string) ($activeConnection['password'] ?? '');

        if ($host === '' || $port === '' || $database === '' || $username === '') {
            return null;
        }

        try {
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database);
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 2,
            ]);

            $statement = $pdo->query('select database() as db, @@socket as socket, @@port as port, @@hostname as hostname, @@datadir as datadir, @@server_id as server_id, @@version as version');
            $row = $statement ? $statement->fetch(PDO::FETCH_ASSOC) : false;

            if (!is_array($row)) {
                return null;
            }

            return [
                'database' => (string) ($row['db'] ?? ''),
                'runtime_socket' => (string) ($row['socket'] ?? ''),
                'runtime_port' => (string) ($row['port'] ?? ''),
                'runtime_hostname' => (string) ($row['hostname'] ?? ''),
                'runtime_datadir' => (string) ($row['datadir'] ?? ''),
                'runtime_server_id' => (string) ($row['server_id'] ?? ''),
                'runtime_version' => (string) ($row['version'] ?? ''),
            ];
        } catch (Throwable) {
            return null;
        }
    }

    private function normalizeDatabaseValue(string $driver, $database): string
    {
        if ($database === null) {
            return '';
        }

        $database = (string) $database;

        if ($driver === 'sqlite' && $database !== ':memory:' && $database !== '') {
            return str_replace(base_path() . DIRECTORY_SEPARATOR, '', $database);
        }

        return $database;
    }

    private function envFileDatabaseConfig(): array
    {
        $path = base_path('.env');
        if (!is_file($path)) {
            return [];
        }

        $keys = ['DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_SOCKET'];
        $values = [];

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if (!preg_match('/^\s*([A-Z0-9_]+)\s*=\s*(.*)\s*$/', $line, $matches)) {
                continue;
            }

            $key = $matches[1];
            if (!in_array($key, $keys, true)) {
                continue;
            }

            $raw = trim($matches[2]);
            $values[$key] = $this->stripWrappingQuotes($raw);
        }

        return $values;
    }

    private function envFileDatabaseDrift(string $defaultConnection, array $activeConnection, array $envFileConfig): array
    {
        if ($envFileConfig === []) {
            return [];
        }

        $expected = [
            'DB_CONNECTION' => $defaultConnection,
            'DB_HOST' => (string) ($activeConnection['host'] ?? ''),
            'DB_PORT' => (string) ($activeConnection['port'] ?? ''),
            'DB_DATABASE' => $this->normalizeDatabaseValue((string) ($activeConnection['driver'] ?? ''), $activeConnection['database'] ?? null),
            'DB_SOCKET' => (string) ($activeConnection['unix_socket'] ?? ''),
        ];

        $drift = [];

        foreach ($expected as $key => $value) {
            $envValue = $envFileConfig[$key] ?? null;
            if ($envValue === null) {
                continue;
            }

            if ((string) $envValue !== (string) $value) {
                $drift[$key] = [
                    'env' => $envValue,
                    'config' => (string) $value,
                ];
            }
        }

        return $drift;
    }

    private function stripWrappingQuotes(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if ((Str::startsWith($value, '"') && Str::endsWith($value, '"')) || (Str::startsWith($value, "'") && Str::endsWith($value, "'"))) {
            return substr($value, 1, -1);
        }

        return $value;
    }

    private function isAllowedConnection(string $connection): bool
    {
        return in_array($connection, (array) config('db_safety.allowed_connections', []), true);
    }

    private function isAllowedDatabase(string $environment, string $database): bool
    {
        $databases = (array) config('db_safety.allowed_databases', []);
        if ($environment === 'testing') {
            $databases = array_values(array_unique(array_merge($databases, (array) config('db_safety.allowed_test_databases', []))));
        }

        if (in_array($database, $databases, true)) {
            return true;
        }

        if ($environment === 'testing' && Str::contains(Str::lower($database), 'test')) {
            return true;
        }

        return false;
    }

    private function isSafeHost(string $driver, string $host, string $socket): bool
    {
        if ($driver === 'sqlite') {
            return true;
        }

        if ($socket !== '') {
            return Str::startsWith($socket, ['/tmp/', storage_path()]);
        }

        return in_array(Str::lower($host), array_map(static fn (string $value): string => Str::lower($value), (array) config('db_safety.allowed_local_hosts', [])), true);
    }

    private function destructiveCommandMaySkipSnapshot(array $summary): bool
    {
        $active = $summary['active_connection'];
        $environment = (string) ($summary['environment'] ?? '');
        $database = (string) ($active['database'] ?? '');
        $driver = (string) ($active['driver'] ?? '');

        if ($driver === 'sqlite') {
            return true;
        }

        if ($environment === 'testing' && ($database === '' || Str::contains(Str::lower($database), 'test'))) {
            return true;
        }

        return false;
    }

    private function shouldRequireSnapshotWarning(array $summary): bool
    {
        if (!(bool) config('db_safety.require_recent_snapshot', false)) {
            return false;
        }

        if ($this->destructiveCommandMaySkipSnapshot($summary)) {
            return false;
        }

        return !$this->hasRecentSnapshot();
    }

    private function matchesPatterns(string $command, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if ($command === $pattern || Str::is($pattern, $command)) {
                return true;
            }
        }

        return false;
    }
}
