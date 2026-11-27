<?php

namespace App\Http\Middleware;

use App\Models\CmsUser;
use App\Services\Security\CmsAuthenticatedSessionService;
use Closure;
use Illuminate\Http\Request;

class CmsUserActivity
{
    public function __construct(private CmsAuthenticatedSessionService $sessions)
    {
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $currentUser = auth()->guard("cms")->user();

        if ($currentUser instanceof CmsUser) {
            $seenAt = now();

            // Lazy public fragments deliberately omit StartSession. A remember
            // cookie can still resolve a user, but there is no request session.
            if ($request->hasSession()) {
                if (!$this->sessions->valid($request, $currentUser)) {
                    return $next($request);
                }
                $this->sessions->observeContext($request, $currentUser);
            }
            CmsUser::whereKey($currentUser->id)->update(['last_seen' => $seenAt]);

        }


        return $next($request);
    }
}
