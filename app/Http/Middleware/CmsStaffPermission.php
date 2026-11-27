<?php

namespace App\Http\Middleware;

use App\Services\CmsStaff\CmsStaffPermissionService;
use Closure;

class CmsStaffPermission
{
    public function __construct(private CmsStaffPermissionService $permissions)
    {
    }

    public function handle($request, Closure $next, string $permissionKey = '')
    {
        $user = auth()->guard('cms')->user();

        if ($this->permissions->has($user, $permissionKey)) {
            return $next($request);
        }

        abort(403);
    }
}
