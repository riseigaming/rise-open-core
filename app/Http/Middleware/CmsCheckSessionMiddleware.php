<?php

namespace App\Http\Middleware;

use App\Enum\UserStatusEnum;
use App\Models\CmsUser;
use App\Services\Security\CmsAuthenticatedSessionService;
use Closure;
use Illuminate\Support\Facades\Auth;

class CmsCheckSessionMiddleware
{
    public function __construct(private CmsAuthenticatedSessionService $sessions)
    {
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $currentUser = Auth::guard("cms")->user();

        if ($currentUser instanceof CmsUser && $currentUser->status != UserStatusEnum::REJECTED){
            if (!$this->sessions->valid($request, $currentUser)) {
                $this->sessions->terminate($request, $currentUser, 'invalid_or_expired');
            }
        }

        return $next($request);
    }
}
