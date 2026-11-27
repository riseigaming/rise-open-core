<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use RuntimeException;

trait CreatesApplication
{
    public function createApplication()
    {
        $root = dirname(__DIR__);
        $testPort = (string) (getenv('TEST_DB_PORT') ?: '13336');
        if (!ctype_digit($testPort) || (int)$testPort < 1024 || (int)$testPort > 65535) throw new RuntimeException('Explicit valid disposable test port required.');
        if (!is_file($root.'/.env.testing') || getenv('TEST_DB_DATABASE') !== 'rise_b1_test') {
            throw new RuntimeException('Explicit disposable MySQL TEST_DB_DATABASE=rise_b1_test and local .env.testing are required.');
        }
        foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'rise_b1_test', 'QUEUE_CONNECTION' => 'database', 'CACHE_DRIVER' => 'array'] as $key => $value) {
            putenv($key.'='.$value); $_ENV[$key] = $_SERVER[$key] = $value;
        }
        $app = require $root.'/bootstrap/app.php';
        // Select the in-memory test cache before providers resolve RateLimiter.
        // A preceding framework unit boot may already have loaded immutable env values.
        $app->afterBootstrapping(\Illuminate\Foundation\Bootstrap\LoadConfiguration::class,
            static fn ($app) => $app['config']->set('cache.default', 'array'));
        $app->make(Kernel::class)->bootstrap();
        if (config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'rise_b1_test'
            || config('database.connections.mysql.host') !== '127.0.0.1' || (string) config('database.connections.mysql.port') !== (string) (getenv('TEST_DB_PORT') ?: '13336')
            || !\App\Support\LocalEgress::shouldBlock() || config('queue.default') !== 'database') {
            throw new RuntimeException('Unexpected acceptance database identity, queue or outbound policy.');
        }
        return $app;
    }
}
