<?php

namespace App\Http\Middleware;

use App\Services\Security\CmsAuthenticatedSessionService;
use App\Models\CmsUser;
use Closure;
use Illuminate\Http\Request;

class RequireRecentCmsAuthentication
{
    public function __construct(private CmsAuthenticatedSessionService $sessions)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        if ($this->sessions->recentlyAuthenticated($request)) {
            return $next($request);
        }

        $user = auth()->guard('cms')->user();
        $this->sessions->terminate($request, $user instanceof CmsUser ? $user : null, 'recent_authentication_required');

        if (!$request->expectsJson() && !$request->ajax()) {
            return redirect()->route('cms.front.login')
                ->with('auth_error', __('cms_sessions.reauthentication_required'));
        }

        return response()->json([
            'status' => 423,
            'code' => 'recent_authentication_required',
            'message' => __('cms_sessions.reauthentication_required'),
            'redirectUrl' => route('cms.front.login'),
        ], 423);
    }
}
