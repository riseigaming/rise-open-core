<?php

namespace App\Console\Commands;

use App\Services\Payments\NovaGatewayClient;
use Illuminate\Console\Command;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class CheckNovaConnectionCommand extends Command
{
    protected $signature = 'nova:connection-check';
    protected $description = 'Verify the configured remote Nova tenant and BTC rail over authenticated HTTPS';

    public function handle(NovaGatewayClient $gateway): int
    {
        try {
            $gateway->checkRemoteConnection();
        } catch (HttpExceptionInterface) {
            $this->error('Nova tenant and BTC rail verification failed.');
            return self::FAILURE;
        }
        $this->info('Nova HTTPS, tenant and BTC rail verified.');
        return self::SUCCESS;
    }
}
