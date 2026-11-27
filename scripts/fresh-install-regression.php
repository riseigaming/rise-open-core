<?php
// Explicit empty disposable MySQL only. No database creation/drop, source data or worker.
use App\Support\LocalEgress;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php'; $app->make(Kernel::class)->bootstrap();
$fail = static function (string $message): never { fwrite(STDERR, 'FAIL '.$message."\n"); exit(1); };
$database = (string) getenv('FRESH_INSTALL_DISPOSABLE_DB'); $origin = (string) getenv('FRESH_INSTALL_SMOKE_ORIGIN'); $url = parse_url($origin);
if ($database !== 'rise_b1_test' || !in_array(app()->environment(), ['local','testing','candidate'], true) || !LocalEgress::shouldBlock()
    || config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== $database
    || config('database.connections.mysql.host') !== '127.0.0.1' || !is_array($url) || ($url['scheme'] ?? '') !== 'http'
    || ($url['host'] ?? '') !== '127.0.0.1' || empty($url['port']) || !empty($url['path']) || isset($url['user']) || isset($url['query'])) $fail('explicit isolated database, loopback origin and blocked egress required');
$actual = DB::selectOne('SELECT DATABASE() AS name')->name;
$tables = DB::selectOne('SELECT COUNT(*) AS total FROM information_schema.tables WHERE table_schema=DATABASE()')->total;
if ($actual !== $database || (int) $tables !== 0) $fail('confirmed empty disposable target required; no data will be overwritten');
$socket = @fsockopen('127.0.0.1', $url['port'], $errno, $message, .2); if ($socket) { fclose($socket); $fail('smoke port must be free'); }
foreach (['COUNTRY_CODE','COUNTRY_NAME','CURRENCY_CODE'] as $field) if (!getenv('FRESH_INSTALL_'.$field)) $fail('explicit installation reference metadata required');
$snapshotPath = (string) getenv('FRESH_INSTALL_NATIVE_SNAPSHOT');
$snapshot = app(\App\Support\DatabaseSafety::class)->latestSnapshot();
if ($snapshotPath === '' || is_link($snapshotPath) || realpath($snapshotPath) !== ($snapshot['path'] ?? null)
    || !is_file($snapshotPath) || !is_readable($snapshotPath) || (fileperms($snapshotPath) & 0777) !== 0600) {
    $fail('a private native MariaDB dump of the verified empty target is required (FRESH_INSTALL_NATIVE_SNAPSHOT; see Run34)');
}
$stream = fopen($snapshotPath, 'rb');
$header = $stream ? fread($stream, 4096) : false;
if ($stream) fclose($stream);
if (!is_string($header) || !preg_match('/^-- MariaDB dump\b/m', $header)) {
    $fail('expected a native MariaDB dump header');
}
$run = static function (array $arguments, ?string $input = null): string {
    $process = new Process(array_merge([PHP_BINARY], $arguments), dirname(__DIR__)); $process->setInput($input); $process->setTimeout(60); $process->run();
    if (!$process->isSuccessful()) throw new RuntimeException('Step failed: '.implode(' ', $arguments)."\n".$process->getOutput().$process->getErrorOutput());
    return $process->getOutput();
};
$server = null; $failed = false;
try {
    echo $run(['artisan','migrate','--no-interaction']);
    $credentialFile = getenv('FRESH_INSTALL_ADMIN_CREDENTIALS_FILE');
    $credential = $credentialFile ? json_decode(file_get_contents($credentialFile), true) : [];
    $password = $credential['password'] ?? bin2hex(random_bytes(24)); $username = $credential['username'] ?? 'b1_fresh_install';
    $bootstrapArgs = ['artisan','app:admin-bootstrap','--name=B1 Fresh Install','--username='.$username,'--email=b1-fresh@example.invalid','--country-code='.getenv('FRESH_INSTALL_COUNTRY_CODE'),'--country-name='.getenv('FRESH_INSTALL_COUNTRY_NAME'),'--currency-code='.getenv('FRESH_INSTALL_CURRENCY_CODE'),'--password-stdin','--no-interaction'];
    $reject = static function (array $args, string $input, array $env = []) use ($database): void {
        $process = new Process(array_merge([PHP_BINARY], $args), dirname(__DIR__), $env); $process->setInput($input); $process->setTimeout(20); $process->run();
        if ($process->getExitCode() !== 1 || DB::table('cms_users')->exists()) throw new RuntimeException('Bootstrap negative case did not reject atomically.');
    };
    $badCountry = array_map(fn ($arg) => str_starts_with($arg, '--country-code=') ? '--country-code=ZZ' : $arg, $bootstrapArgs);
    $reject($badCountry, $password."\n"); echo "PASS invalid country bootstrap denial\n";
    $badCurrency = array_map(fn ($arg) => str_starts_with($arg, '--currency-code=') ? '--currency-code=BTC' : $arg, $bootstrapArgs);
    $reject($badCurrency, $password."\n"); echo "PASS invalid fiat metadata bootstrap denial\n";
    $reject($bootstrapArgs, "short\n"); echo "PASS short bootstrap password denial\n";
    $reject($bootstrapArgs, $password."\n", ['APP_KEY'=>'']); echo "PASS missing APP_KEY bootstrap denial\n";
    echo $run($bootstrapArgs, $password."\n");
    $repeat = new Process(array_merge([PHP_BINARY], $bootstrapArgs), dirname(__DIR__)); $repeat->setInput($password."\n"); $repeat->run();
    if ($repeat->getExitCode() !== 1 || DB::table('cms_users')->count() !== 1) throw new RuntimeException('Repeated/nonempty-target bootstrap accepted.');
    echo "PASS repeated/nonempty-target bootstrap denial\n";
    echo $run(['artisan','app:install:check']);
    $server = new Process([PHP_BINARY,'-S','127.0.0.1:'.$url['port'],'-t','public'], dirname(__DIR__), ['APP_URL' => $origin]); $server->start();
    for ($attempt=0;$attempt<30;$attempt++) { $ready=@fsockopen('127.0.0.1',$url['port'],$errno,$message,.1); if($ready){fclose($ready);break;}usleep(100000); }
    echo $run(['scripts/fresh-install-route-smoke.php'], json_encode(['origin'=>$origin,'username'=>$username,'password'=>$password]));
    unset($password);
    echo "PASS empty-schema install, first CMS administrator, install contract and real HTTP smoke\n";
} catch (Throwable $error) { fwrite(STDERR, 'FAIL '.$error->getMessage()."\n"); $failed = true; }
finally { if ($server) $server->stop(3); }

exit($failed ? 1 : 0);
