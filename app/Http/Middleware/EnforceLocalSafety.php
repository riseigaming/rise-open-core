<?php

namespace App\Http\Middleware;

use App\Support\LocalEgress;
use Closure;
use Illuminate\Http\RedirectResponse;

class EnforceLocalSafety
{
    public function handle($request, Closure $next)
    {
        if (LocalEgress::shouldBlockRequest($request)) {
            LocalEgress::skip('request.route', [
                'method' => $request->method(),
                'path' => $request->path(),
            ]);

            abort((int) config('local_safety.blocked_request_status', 503), 'Blocked by local safety policy.');
        }

        $response = $next($request);

        if (!LocalEgress::isEnabled()) {
            return $response;
        }

        if ($response instanceof RedirectResponse && !LocalEgress::isUrlAllowed($response->getTargetUrl())) {
            LocalEgress::skip('response.redirect', [
                'path' => $request->path(),
                'url' => $response->getTargetUrl(),
            ]);

            return response('External redirect blocked in local mode.', (int) config('local_safety.local_redirect_status', 409));
        }

        $location = $response->headers->get('Location');
        if ($location && !LocalEgress::isUrlAllowed($location)) {
            LocalEgress::skip('response.location', [
                'path' => $request->path(),
                'url' => $location,
            ]);

            return response('External redirect blocked in local mode.', (int) config('local_safety.local_redirect_status', 409));
        }

        $policy = LocalEgress::contentSecurityPolicy();
        $response->headers->set('Content-Security-Policy', $policy);
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        return $response;
    }
}
