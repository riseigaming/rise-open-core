<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    // CMS verification uses the retained explicit CMS activation flow.
    protected $listen = [];
}
