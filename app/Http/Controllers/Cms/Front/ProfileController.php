<?php

namespace App\Http\Controllers\Cms\Front;

use App\Enum\UserLogEnum;
use App\Helper\CmsUserLogService;
use App\Http\Controllers\Controller;
use App\Http\Middleware\CmsLanguageMiddleware;
use App\Models\CmsLanguage;
use App\Models\CmsUser;
use App\Models\CmsUserLog;
use App\Services\Cms\CmsActivationMailService;
use App\Services\Security\CmsPasswordIntegrityService;
use App\Services\Security\CmsAuthenticatedSessionService;
use App\Support\CmsThemeResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    public function editPassword(){
        $user = auth()->guard("cms")->user();
        $editLogCount = CmsUserLog::query()
            ->select("id")
            ->where("cms_user_id", $user->id)
            ->where("log_type", UserLogEnum::PROFILE_EDIT)
            ->count() > 0 ? "required" : "";
        return frontView('auth.edit-password',[
            'user' => $user,
            'formFillable' => $editLogCount <= 0,
        ]);
    }

    protected function activeThemeNamespace(): string
    {
        return CmsThemeResolver::activePhysicalNamespace();
    }

    public function resendVerifyEmail(Request $request){
        $currentUser = auth("cms")->user();

        if(!$currentUser instanceof CmsUser || trim((string) $currentUser->email) === ''){
            return response()->json([
                "message" => trans("cms_alerts.invalid_email"),
                "status" => 400
            ], 400);
        }

        $sent = app(CmsActivationMailService::class)->send(
            $currentUser,
            $currentUser->language ?: session()->get("currentLocale")
        );

        if(!$sent){
            return response()->json([
                "message" => trans("cms_alerts.verify_email_cant_send"),
                "status" => 400
            ], 400);
        }

        return response()->json([
            "message" => trans("cms_alerts.verify_email_resent"),
            "status" => 200,
        ]);
    }

    public function setLanguage(Request $request, $languageCode){
        $lang = $this->resolveLanguageByCode((string) $languageCode);

        if (!$lang instanceof CmsLanguage) {
            return redirect()->to($this->safeLanguageRedirectUrl($request));
        }

        session()->put("currentLocale", $lang->code);
        session()->put("currentLanguageId", $lang->id);
        session()->put("currentLanguageName", $lang->name);
        session()->put('currentLocaleManuallySelected', true);
        app()->setLocale($lang->code);

        $user = $request->user('cms');
        if ($user instanceof CmsUser && $user->language !== $lang->code) {
            $user->forceFill(['language' => $lang->code])->save();
        }

        return redirect()->to($this->safeLanguageRedirectUrl($request, $lang->code))
            ->withCookie(cookie(
                CmsLanguageMiddleware::MANUAL_LOCALE_COOKIE,
                $lang->code,
                60 * 24 * 365,
                '/',
                null,
                $request->isSecure(),
                true,
                false,
                'lax'
            ));
    }

    private function resolveLanguageByCode(string $languageCode): ?CmsLanguage
    {
        $requestedCode = Str::lower(trim($languageCode));

        if ($requestedCode === '') {
            return null;
        }

        $language = CmsLanguage::query()->where('status', 1)->where("code", $requestedCode)->first();

        if ($language instanceof CmsLanguage) {
            return $language;
        }

        $fallbackCode = match ($requestedCode) {
            'ing' => 'en',
            'esp' => 'es',
            default => null,
        };

        if (!$fallbackCode) {
            return null;
        }

        return CmsLanguage::query()->where('status', 1)->where("code", $fallbackCode)->first();
    }

    private function safeLanguageRedirectUrl(Request $request, ?string $selectedLocale = null): string
    {
        $fallback = route('cms.front.home');
        $requestedRedirect = $this->safeLanguageRedirectCandidate($request, $request->query('redirect'));

        if ($requestedRedirect !== null) {
            return $selectedLocale ? $this->redirectWithLocale($requestedRedirect, $selectedLocale) : $requestedRedirect;
        }

        $previous = url()->previous();

        $target = $this->safeLanguageRedirectCandidate($request, $previous) ?? $fallback;

        return $selectedLocale ? $this->redirectWithLocale($target, $selectedLocale) : $target;
    }

    private function redirectWithLocale(string $target, string $locale): string
    {
        $parts = parse_url($target);
        if (!is_array($parts)) {
            return $target;
        }

        parse_str((string) ($parts['query'] ?? ''), $query);
        $query['lang'] = $locale;

        $origin = ($parts['scheme'] ?? request()->getScheme()) . '://' . ($parts['host'] ?? request()->getHost());
        if (isset($parts['port'])) {
            $origin .= ':' . $parts['port'];
        }

        return $origin . ($parts['path'] ?? '/') . '?' . http_build_query($query)
            . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
    }

    private function safeLanguageRedirectCandidate(Request $request, mixed $candidate): ?string
    {
        if (!is_string($candidate) || trim($candidate) === '') {
            return null;
        }

        $candidate = trim($candidate);

        if (str_starts_with($candidate, '//')) {
            return null;
        }

        $redirectUrl = str_starts_with($candidate, '/')
            ? url($candidate)
            : $candidate;

        $scheme = parse_url($redirectUrl, PHP_URL_SCHEME);
        if ($scheme && ! in_array(strtolower($scheme), ['http', 'https'], true)) {
            return null;
        }

        $previousHost = parse_url($redirectUrl, PHP_URL_HOST);
        $currentHost = $request->getHost();

        if ($previousHost && $previousHost !== $currentHost) {
            return null;
        }

        $path = '/' . ltrim((string) (parse_url($redirectUrl, PHP_URL_PATH) ?: ''), '/');
        $unsafePrefixes = [
            '/cms/external-games/discovery',
            '/cms/external-games/activity',
            '/cms/api/',
            '/api/',
        ];

        foreach ($unsafePrefixes as $prefix) {
            if ($path === rtrim($prefix, '/') || str_starts_with($path, $prefix)) {
                return route('cms.front.home');
            }
        }

        return $redirectUrl;
    }

    public function sessions(Request $request){
        return response(frontView('sessions', app(CmsAuthenticatedSessionService::class)->overview($request, auth('cms')->user())))
            ->header('Cache-Control', 'private, no-store');
    }

    public function makeLogoutSession(Request  $request){
        $request->validate(['userSessionId' => 'required|integer|min:1']);
        try {
            $user = auth("cms")->user();
            $isCurrent = (int) $request->session()->get(CmsAuthenticatedSessionService::SESSION_RECORD_KEY)
                === (int) $request->input('userSessionId');
            $revoked = $user instanceof CmsUser
                && app(CmsAuthenticatedSessionService::class)->revokeRecord($request, $user, (int) $request->get("userSessionId"));

            if (!$revoked){
                return response()->json([
                    "message" => trans("cms_alerts.session_not_found"),
                    "status" => 400,
                ]);
            }

            return response()->json([
                "message" => trans("cms_alerts.session_closed"),
                "status" => 200,
                'redirectUrl' => $isCurrent ? route('cms.front.login') : route('cms.front.sessions'),
            ]);

        }catch (\Exception $exception){
            return response()->json([
                "message" => trans("cms_alerts.general_error"),
                "status" => 500,
            ]);
        }
    }

    public function logoutAllSessions(Request $request)
    {
        $user = auth('cms')->user();
        if (!$user instanceof CmsUser) {
            return response()->json(['status' => 401], 401);
        }

        app(CmsAuthenticatedSessionService::class)->revokeAll($request, $user);

        return response()->json([
            'message' => trans('cms_alerts.session_closed'),
            'status' => 200,
            'redirectUrl' => route('cms.front.login'),
        ]);
    }

    public function logoutOtherSessions(Request $request)
    {
        app(CmsAuthenticatedSessionService::class)->revokeOthers($request, auth('cms')->user());
        return response()->json(['status' => 200, 'message' => trans('cms_alerts.session_closed'),
            'redirectUrl' => route('cms.front.sessions')]);
    }

    public function update(Request $request)
    {
        abort_unless(auth('cms')->user() instanceof CmsUser, 401);
        $allowed = ['_token', 'currentPassword', 'new_password', 'password_confirm'];
        abort_if(array_diff(array_keys($request->all()), $allowed) !== [], 422);
        $request->validate([
            'currentPassword' => ['required', 'string'],
            'new_password' => ['required', 'string', 'same:password_confirm', function ($attribute, $value, $fail) {
                if (\App\Services\Security\RegistrationInputPolicy::passwordProblem($value)) $fail(trans('cms_public.auth.password_policy'));
            }],
            'password_confirm' => ['required', 'string'],
        ]);
        $integrity = app(CmsPasswordIntegrityService::class);
        $userId = (int) auth('cms')->id();
        $user = DB::transaction(function () use ($request, $integrity, $userId) {
            $user = CmsUser::query()->lockForUpdate()->findOrFail($userId);
            if (!Hash::check($request->input('currentPassword'), $user->password)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'currentPassword' => trans('cms_alerts.current_password_wrong'),
                ]);
            }
            $hash = $integrity->hashAndValidatePlainPassword(
                $request->input('new_password'), 'cms_front_profile_update'
            );
            $integrity->runWithPasswordMutationSource('cms_profile_password_change', function () use ($user, $hash) {
                $user->forceFill(['password' => $hash, 'password_raw' => null])->saveOrFail();
            });
            CmsUserLogService::save($user, $user, UserLogEnum::PASSWORD_EDIT);
            app(CmsAuthenticatedSessionService::class)->afterPasswordChange($request, $user, true);
            return $user;
        });
        return response()->json(['message' => trans('cms_alerts.profile_updated'), 'status' => 200]);
    }
}
