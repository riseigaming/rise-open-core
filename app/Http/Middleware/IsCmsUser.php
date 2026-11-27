<?php

namespace App\Http\Middleware;

use App\Enum\UserStatusEnum;
use App\Models\CmsUser;
use App\Services\Security\CmsAuthenticatedSessionService;
use Closure;
use Illuminate\Support\Facades\Auth;

class IsCmsUser
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

                return $this->loginRedirect($request);
            }
            return $next($request);
        }

        if ($currentUser instanceof CmsUser) {
            $this->sessions->terminate($request, $currentUser, 'account_unavailable');
        }

        return $this->loginRedirect($request);
    }

    private function loginRedirect($request)
    {
        return redirect()->route('cms.front.login');
    }
}
