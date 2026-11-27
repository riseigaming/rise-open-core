<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * @var array|string
     */
    protected $proxies;

    protected function setTrustedProxyIpAddresses(Request $request)
    {
        // Use the validated IP/CIDR list; never inherit automatic cloud proxy trust.
        $this->proxies = config('trustedproxy.proxies', []);
        $this->setTrustedProxyIpAddressesToSpecificIps($request, $this->proxies);
    }

    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers = Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO;
}
