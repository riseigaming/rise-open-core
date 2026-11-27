<?php

namespace App\Http\Middleware;

use App\Services\CmsStaff\CmsStaffPermissionService;
use Closure;

class CmsStaffAccess
{
    public function __construct(private CmsStaffPermissionService $permissions)
    {
    }

    public function handle($request, Closure $next)
    {
        $user = auth()->guard('cms')->user();

        if ($this->permissions->has($user, CmsStaffPermissionService::ACCESS)) {
            return $next($request);
        }

        abort(403);
    }
}
