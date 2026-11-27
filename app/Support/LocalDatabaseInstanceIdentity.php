<?php

namespace App\Support;

use RuntimeException;
use Throwable;

/** Local accidental-misrouting protection; not hostile-server cryptographic attestation. */
final class LocalDatabaseInstanceIdentity
{
    public function applicationRoot(): string
    {
        $root = realpath(dirname(__DIR__, 2));
        if ($root === false) {
            throw new RuntimeException('Physical application root is unavailable.');
        }
        return $root;
    }

    public function manifestPath(): string
    {
        $directory = match (PHP_OS_FAMILY) {
            'Darwin' => '/Library/Application Support/RiseOpenCore/instance-manifests',
            'Linux' => '/etc/rise-open-core/instance-manifests',
            default => throw new RuntimeException('Trusted local instance enrollment requires macOS or Linux.'),
        };

        // Deliberately no env/config path override. Another installation's pin is not selectable.
        return $directory.'/'.hash('sha256', $this->applicationRoot()).'.json';
    }

    public function violation(array $summary, string $applicationRoot): ?string
    {
        try {
            if (realpath($applicationRoot) !== $this->applicationRoot()) {
                return 'Application root does not match the enrolled physical installation.';
            }
            if (!function_exists('posix_geteuid') || posix_geteuid() === 0) {
                return 'Instance-protected application commands require an unprivileged POSIX user.';
            }
            $expected = $this->readTrustedManifest($this->manifestPath());
            return $this->compare($expected, $summary);
        } catch (Throwable) {
            // No file paths, credentials or raw malformed manifest values enter CLI logs.
            return 'Trusted local instance enrollment is missing, invalid or not independently protected.';
        }
    }

    private function readTrustedManifest(string $path): array
    {
        clearstatcache(true);
        if (realpath($path) !== $path || !is_file($path) || !is_readable($path)) {
            throw new RuntimeException('Canonical readable regular manifest required.');
        }
        $file = null;
        for ($entry = $path; ; $entry = dirname($entry)) {
            clearstatcache(true, $entry);
            $stat = lstat($entry);
            $kind = $entry === $path ? 0100000 : 0040000;
            if ($stat === false || (int) $stat['uid'] !== 0
                || ($stat['mode'] & 0170000) !== $kind || ($stat['mode'] & 0022) !== 0
                || is_link($entry) || is_writable($entry)) {
                throw new RuntimeException('Root-owned nonreplaceable manifest and ancestors required.');
            }
            if ($entry === $path) {
                $file = $stat;
            }
            if ($entry === dirname($entry)) {
                break;
            }
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Manifest could not be opened.');
        }
        try {
            $opened = fstat($handle);
            if ($opened === false || $opened['dev'] !== $file['dev'] || $opened['ino'] !== $file['ino']
                || $opened['uid'] !== 0 || ($opened['mode'] & 0022) !== 0
                || $opened['size'] < 1 || $opened['size'] > 16384) {
                throw new RuntimeException('Manifest changed or exceeds bounded size.');
            }
            $bytes = stream_get_contents($handle, 16385);
        } finally {
            fclose($handle);
        }
        if (!is_string($bytes) || strlen($bytes) > 16384) {
            throw new RuntimeException('Bounded manifest read failed.');
        }
        $expected = json_decode($bytes, true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($expected)) {
            throw new RuntimeException('Manifest object required.');
        }
        return $expected;
    }

    private function compare(array $expected, array $summary): ?string
    {
        // v2 explicitly enrolls local + testing scopes on the SAME attested instance.
        // No wildcard, env-selected trust file, or automatic scope enrollment.
        $schema = $expected['schema'] ?? null;
        $scopes = $schema === 'rise-local-instance-v2' ? ($expected['scopes'] ?? null)
            : ($schema === 'rise-b1-local-instance-v1' ? [array_intersect_key($expected, array_flip(['environment', 'connection', 'database']))] : null);
        if (($expected['application_root'] ?? null) !== $this->applicationRoot()
            || !is_array($scopes) || !array_is_list($scopes) || count($scopes) < 1 || count($scopes) > 8
            || ($summary['active_connection']['driver'] ?? null) !== 'mysql'
            || !is_string($expected['runtime_datadir'] ?? null)
            || !str_starts_with($expected['runtime_datadir'], '/')
            || trim($expected['runtime_datadir'], '/') === ''
            || str_contains($expected['runtime_datadir'], "\0")
            || preg_match('~(?:^|/)\.{1,2}(?:/|$)~', $expected['runtime_datadir'])
            || !is_string($expected['runtime_server_id'] ?? null)
            || !ctype_digit($expected['runtime_server_id'])) {
            return 'Trusted instance enrollment scope is invalid.';
        }
        $matched = false;
        foreach ($scopes as $scope) {
            if (!is_array($scope) || count($scope) !== 3
                || !in_array($scope['environment'] ?? null, ['local','testing','candidate','preview'], true)
                || ($scope['connection'] ?? null) !== 'mysql'
                || !is_string($scope['database'] ?? null)
                || !preg_match('/\A[a-zA-Z0-9_]+\z/', $scope['database'])) {
                return 'Trusted instance enrollment scope is invalid.';
            }
            if ($scope['environment'] === ($summary['environment'] ?? null)
                && $scope['connection'] === ($summary['active_connection']['name'] ?? null)
                && $scope['database'] === ($summary['active_connection']['database'] ?? null)) $matched = true;
        }
        if (!$matched) return 'Trusted instance enrollment scope does not match this command.';
        $runtime = $summary['runtime_connection'] ?? [];
        foreach ([$runtime, $runtime['tcp_probe'] ?? null] as $actual) {
            if (!is_array($actual)
                || ($actual['database'] ?? null) !== ($summary['active_connection']['database'] ?? null)
                || rtrim((string) ($actual['runtime_datadir'] ?? ''), '/') !== rtrim($expected['runtime_datadir'], '/')
                || (string) ($actual['runtime_server_id'] ?? '') !== $expected['runtime_server_id']) {
                return 'Runtime database instance does not match the independently enrolled instance.';
            }
        }
        if (($runtime['socket_tcp_same_instance'] ?? null) !== 'yes') {
            return 'Current and TCP runtime identity could not be matched.';
        }
        return null;
    }
}
