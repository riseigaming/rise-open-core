<?php

namespace App\Console;

use App\Console\Commands\BootstrapFirstAdminCommand;
use App\Console\Commands\CheckFreshInstallationCommand;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    // Installation and connector diagnostics use explicit commands.
    protected $commands = [
        BootstrapFirstAdminCommand::class,
        CheckFreshInstallationCommand::class,
        \App\Console\Commands\CheckNovaConnectionCommand::class,
    ];
}
