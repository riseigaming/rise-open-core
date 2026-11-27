<?php

namespace Tests\Unit;

use App\Support\LocalDatabaseInstanceIdentity;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;

final class LocalDatabaseInstanceIdentityTest extends TestCase
{
    private function data(): array
    {
        $gate = new LocalDatabaseInstanceIdentity();
        $expected = ['schema' => 'rise-b1-local-instance-v1', 'application_root' => $gate->applicationRoot(),
            'environment' => 'local', 'connection' => 'mysql', 'database' => 'rise_b1_candidate',
            'runtime_datadir' => '/independent-fixture/A/', 'runtime_server_id' => '7101'];
        $actual = ['database' => 'rise_b1_candidate', 'runtime_datadir' => '/independent-fixture/A/', 'runtime_server_id' => '7101'];
        return [$expected, ['environment' => 'local', 'active_connection' => ['name' => 'mysql', 'driver' => 'mysql', 'database' => 'rise_b1_candidate'],
            'runtime_connection' => $actual + ['tcp_probe' => $actual, 'socket_tcp_same_instance' => 'yes']]];
    }

    public function testPureComparisonSeparatesActualInstanceFromHostPort(): void
    {
        // Pure array logic only. This is NOT trusted enrollment or real A/B acceptance.
        [$expected, $summary] = $this->data();
        $method = new ReflectionMethod(LocalDatabaseInstanceIdentity::class, 'compare');
        $gate = new LocalDatabaseInstanceIdentity();
        self::assertNull($method->invoke($gate, $expected, $summary));
        $summary['active_connection']['host'] = '127.0.0.1';
        $summary['active_connection']['port'] = '9999';
        self::assertNull($method->invoke($gate, $expected, $summary));
        $summary['runtime_connection']['runtime_datadir'] = '/independent-fixture/B/';
        $summary['runtime_connection']['runtime_server_id'] = '7102';
        self::assertNotNull($method->invoke($gate, $expected, $summary));
    }

    public function testPureComparisonRejectsScopeAndMissingOrDivergentProbes(): void
    {
        [$expected, $summary] = $this->data();$method = new ReflectionMethod(LocalDatabaseInstanceIdentity::class, 'compare');$gate = new LocalDatabaseInstanceIdentity();
        foreach (['schema', 'application_root', 'environment', 'connection', 'database', 'runtime_datadir', 'runtime_server_id'] as $key) {
            $invalid = $expected;unset($invalid[$key]);self::assertNotNull($method->invoke($gate, $invalid, $summary));
        }
        $invalid = $summary;$invalid['runtime_connection']['tcp_probe'] = null;self::assertNotNull($method->invoke($gate, $expected, $invalid));
        $invalid = $summary;$invalid['runtime_connection']['tcp_probe']['runtime_server_id'] = '7102';self::assertNotNull($method->invoke($gate, $expected, $invalid));
        $invalid = $summary;$invalid['runtime_connection']['socket_tcp_same_instance'] = 'unknown';self::assertNotNull($method->invoke($gate, $expected, $invalid));
    }

    public function testExplicitScopesCannotBeCrossCombinedOrExpanded(): void
    {
        [$expected, $summary] = $this->data();
        $expected['schema'] = 'rise-local-instance-v2';
        unset($expected['environment'], $expected['connection'], $expected['database']);
        $expected['scopes'] = [
            ['environment'=>'local','connection'=>'mysql','database'=>'rise_b1_candidate'],
            ['environment'=>'testing','connection'=>'mysql','database'=>'rise_b1_test'],
        ];
        $method = new ReflectionMethod(LocalDatabaseInstanceIdentity::class, 'compare');
        $gate = new LocalDatabaseInstanceIdentity();
        self::assertNull($method->invoke($gate, $expected, $summary));
        $summary['environment'] = 'testing';
        self::assertNotNull($method->invoke($gate, $expected, $summary));
        $summary['active_connection']['database'] = 'rise_b1_test';
        $summary['runtime_connection']['database'] = 'rise_b1_test';
        $summary['runtime_connection']['tcp_probe']['database'] = 'rise_b1_test';
        self::assertNull($method->invoke($gate, $expected, $summary));
        $summary['runtime_connection']['tcp_probe']['runtime_datadir'] = '/independent-fixture/B/';
        self::assertNotNull($method->invoke($gate, $expected, $summary));
        $expected['scopes'][1]['database'] = '*';
        self::assertNotNull($method->invoke($gate, $expected, $summary));
    }

    public function testEnvironmentCannotRetargetManifestToAnotherPath(): void
    {
        $gate = new LocalDatabaseInstanceIdentity();$path = $gate->manifestPath();$old = getenv('DB_SAFETY_EXPECTED_INSTANCE_MANIFEST');
        try {putenv('DB_SAFETY_EXPECTED_INSTANCE_MANIFEST=/untrusted/another-pin.json');self::assertSame($path, $gate->manifestPath());}
        finally {putenv($old === false ? 'DB_SAFETY_EXPECTED_INSTANCE_MANIFEST' : 'DB_SAFETY_EXPECTED_INSTANCE_MANIFEST='.$old);}
        self::assertStringContainsString(hash('sha256', $gate->applicationRoot()), $path);
        [, $summary] = $this->data();self::assertNotNull($gate->violation($summary, '/untrusted/other-installation'));
    }

    public function testReadOnlyAppOwnedFileIsNeverATrustedFixture(): void
    {
        self::assertNotSame(0, posix_geteuid(), 'Run tests as unprivileged user; never claim root fixture acceptance here.');
        $file = tempnam(sys_get_temp_dir(), 'rise-untrusted-');file_put_contents($file, '{}');chmod($file, 0444);
        try {
            $method = new ReflectionMethod(LocalDatabaseInstanceIdentity::class, 'readTrustedManifest');
            $this->expectException(RuntimeException::class);$method->invoke(new LocalDatabaseInstanceIdentity(), realpath($file));
        } finally {chmod($file, 0600);unlink($file);}
    }

    public function testMissingSecureEnrollmentFailsClosed(): void
    {
        $gate = new LocalDatabaseInstanceIdentity();
        if (is_file($gate->manifestPath())) {
            // Test a genuinely absent trust file without deleting the installed pin.
            $missing = $gate->manifestPath().'.missing-'.bin2hex(random_bytes(6));
            self::assertFileDoesNotExist($missing);
            $this->expectException(RuntimeException::class);
            (new ReflectionMethod(LocalDatabaseInstanceIdentity::class, 'readTrustedManifest'))->invoke($gate, $missing);
            return;
        }
        self::assertFileDoesNotExist($gate->manifestPath());
        [, $summary] = $this->data();self::assertNotNull($gate->violation($summary, $gate->applicationRoot()));
    }
}
