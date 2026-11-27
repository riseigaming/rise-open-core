<?php

namespace App\Http\Middleware;

use App\Services\Restrictions\CentralRestrictionPolicyService;
use App\Services\Restrictions\ProductCountryRestrictionGuard;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceSiteCountryRestriction
{
    public function __construct(
        private readonly ProductCountryRestrictionGuard $guard
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldBypass($request)) {
            return $next($request);
        }

        $decision = $this->guard->decide($request, CentralRestrictionPolicyService::PRODUCT_SITE);
        $eventFields = $decision->toEventFields();

        $request->attributes->set('site_restriction_decision', $decision);
        $request->attributes->set('site_restriction_event_fields', $eventFields);

        if ($decision->allowed) {
            return $next($request);
        }

        $response = $this->guard->blockedResponse($request, $decision);

        return $response;
    }

    private function shouldBypass(Request $request): bool
    {
        $routeName = $request->route()?->getName();

        if ($routeName !== null && str_starts_with($routeName, 'cms.admin.')) {
            return true;
        }

        $path = trim($request->path(), '/');

        return in_array($path, ['health', 'status'], true)
            || $path === 'admin'
            || str_starts_with($path, 'admin/');
    }


}
