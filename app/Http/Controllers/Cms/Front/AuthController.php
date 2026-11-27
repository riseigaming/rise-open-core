<?php

namespace App\Http\Controllers\Cms\Front;

use App\Enum\UserLogEnum;
use App\Enum\UserStatusEnum;
use App\Helper\CmsUserLogService;
use App\Http\Controllers\Controller;
use App\Models\CmsLanguage;
use App\Models\CmsSiteSetting;
use App\Models\CmsUser;
use App\Models\CmsUserType;
use App\Services\Cms\CmsActivationMailService;
use App\Mail\CmsEmailVerification;
use App\Services\Security\CmsPasswordIntegrityService;
use App\Services\Security\RegistrationInputPolicy;
use App\Services\Security\RegistrationTurnstileVerifier;
use App\Services\Security\CmsAuthenticatedSessionService;
use App\Support\CmsThemeResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    private const REMEMBER_ME_DURATION_MINUTES = 60 * 24 * 30;
    private function registerIdentifierExists(string $type, string $value): bool
    {
        $column = $type === 'email' ? 'email' : 'username';

        return CmsUser::withTrashed()
            ->whereRaw('LOWER(' . $column . ') = ?', [Str::lower($value)])
            ->exists();
    }

    private function resolveRegisterIdentifierStatus($identifier): array
    {
        $value = is_string($identifier) ? trim($identifier) : '';
        $type = Str::contains($value, '@') ? 'email' : 'username';

        if ($problem = RegistrationInputPolicy::identifierProblem($identifier)) {
            return [
                'status' => 'invalid',
                'type' => $type,
                'value' => $value,
                'message' => trans('cms_public.auth.' . RegistrationInputPolicy::identifierFeedbackKey($identifier)),
            ];
        }

        if ($type === 'email') {
            $normalizedEmail = Str::lower($value);

            if (strlen($normalizedEmail) > 100 || filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL) === false) {
                return [
                    'status' => 'invalid',
                    'type' => 'email',
                    'value' => $normalizedEmail,
                    'message' => trans('cms_public.auth.identifier_invalid'),
                ];
            }

            if ($this->registerIdentifierExists('email', $normalizedEmail)) {
                return [
                    'status' => 'unavailable',
                    'type' => 'email',
                    'value' => $normalizedEmail,
                    'message' => trans('cms_public.auth.registration_failed'),
                ];
            }

            return [
                'status' => 'available',
                'type' => 'email',
                'value' => $normalizedEmail,
                'message' => trans('cms_public.auth.identifier_available'),
            ];
        }

        if (strlen($value) < 4 || strlen($value) > 50) {
            return [
                'status' => 'invalid',
                'type' => 'username',
                'value' => $value,
                'message' => trans('cms_public.auth.identifier_invalid'),
            ];
        }

        if ($this->registerIdentifierExists('username', $value)) {
            return [
                'status' => 'unavailable',
                'type' => 'username',
                'value' => $value,
                'message' => trans('cms_public.auth.username_unavailable'),
            ];
        }

        return [
            'status' => 'available',
            'type' => 'username',
            'value' => $value,
            'message' => trans('cms_public.auth.identifier_available'),
        ];
    }

    private function generateAvailableUsernameFromEmail(string $email): string
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $candidate = 'user' . Str::lower(Str::random(12));

            if (!$this->registerIdentifierExists('username', $candidate)) {
                return $candidate;
            }
        }

        do {
            $candidate = 'user' . Str::lower(Str::random(12));
        } while ($this->registerIdentifierExists('username', $candidate));

        return $candidate;
    }

    public function registerAvailability(Request $request)
    {
        if ($this->shouldRedirectBrowserNavigation($request)) {
            return redirect()->route('cms.register');
        }

        $rawIdentifier = $request->input('identifier');
        $identifier = is_string($rawIdentifier) ? trim($rawIdentifier) : '';

        if (Str::contains($identifier, '@')) {
            $normalizedEmail = Str::lower($identifier);

            if ($normalizedEmail === '' || strlen($normalizedEmail) > 100 || filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL) === false) {
                return response()->json([
                    'status' => 'invalid',
                    'type' => 'email',
                    'message' => trans('cms_public.auth.identifier_invalid'),
                ]);
            }

            return response()->json([
                'status' => 'neutral',
                'type' => 'email',
                'message' => '',
            ])->header('Cache-Control', 'private, no-store');
        }

        $result = $this->resolveRegisterIdentifierStatus($rawIdentifier);

        return response()->json([
            'status' => $result['status'],
            'type' => $result['type'],
            'message' => $result['message'],
        ])->header('Cache-Control', 'private, no-store');
    }

    private function shouldRedirectBrowserNavigation(Request $request): bool
    {
        if ($request->ajax() || $request->expectsJson() || $request->wantsJson()) {
            return false;
        }

        $acceptable = $request->getAcceptableContentTypes();
        if ($acceptable === [] || $acceptable === ['*/*']) {
            return false;
        }

        $acceptHeader = (string) $request->headers->get('Accept', '');

        return str_contains($acceptHeader, 'text/html')
            && ! str_contains($acceptHeader, 'application/json')
            && ! str_contains($acceptHeader, '+json');
    }

    private function resolveRegisterCountryId(Request $request): int
    {
        // Bootstrap's country is an explicitly validated installation reference, never legacy ID 225.
        $defaultId = CmsUser::query()->where('registration_source', 'app:admin-bootstrap')
            ->where('role', 'admin')->whereNull('parent_id')->orderBy('id')->value('country_id');
        $request->validate(['countryId' => ['sometimes', 'integer', 'min:1', 'exists:cms_countries,id']]);
        $id = (int) $request->input('countryId', $defaultId);
        if ($id <= 0 || !DB::table('cms_countries')->where('id', $id)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['countryId' => 'A valid installation country is required.']);
        }
        return $id;
    }

    private function resolveRegisterCurrencyCode(Request $request): ?string
    {
        $requestedCurrency = Str::upper(trim((string) $request->input('currencyId', '')));

        if ($requestedCurrency === '' || !in_array(
            $requestedCurrency,
            app(\App\Services\Wallet\EnabledWalletAssetCatalog::class)->fiat(),
            true
        )) {
            return null;
        }

        if (!Schema::hasTable('cms_currencies') || !Schema::hasColumn('cms_currencies', 'code')) {
            return $requestedCurrency;
        }

        $query = DB::table('cms_currencies')
            ->whereRaw('UPPER(code) = ?', [$requestedCurrency]);

        if (Schema::hasColumn('cms_currencies', 'status')) {
            $query->where('status', 'active');
        }

        $currencyCode = $query->value('code');

        return is_string($currencyCode) && trim($currencyCode) !== ''
            ? Str::upper(trim($currencyCode))
            : null;
    }

    private function resolveClientTraceCode(Request $request): string
    {
        $incomingTraceCode = strtoupper(trim((string) ($request->input('client_trace_id', '') ?: $request->header('X-Auth-Trace-Code', ''))));

        if ($incomingTraceCode !== '' && preg_match('/^[A-Z0-9-]{6,64}$/', $incomingTraceCode)) {
            return $incomingTraceCode;
        }

        return 'LGN-' . strtoupper(Str::random(10));
    }

    private function resolveSafeFrontRedirect(?string $redirectTarget, string $fallbackRoute): string
    {
        $fallback = route($fallbackRoute);
        $target = trim((string) $redirectTarget);
        if ($target === '' || str_starts_with($target, '//') || preg_match('/[\x00-\x20\\\\]/', $target)) {
            return $fallback;
        }
        $parts = parse_url($target);
        if ($parts === false || isset($parts['user']) || isset($parts['pass'])
            || (isset($parts['scheme']) && !in_array(strtolower($parts['scheme']), ['http', 'https'], true))
            || (isset($parts['host']) && (strtolower($parts['host']) !== strtolower(request()->getHost())
                || ($parts['port'] ?? (strtolower($parts['scheme'] ?? '') === 'https' ? 443 : 80)) !== request()->getPort()))) {
            return $fallback;
        }
        $path = '/' . ltrim($parts['path'] ?? '', '/');
        // The selected GET registry is the destination allowlist; never redirect into excluded products.
        $probe = Request::create(url($path), 'GET');
        try {
            $route = app('router')->getRoutes()->match($probe);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
            return $fallback;
        }
        if (!in_array($route->getName(), ['cms.front.home', 'cms.promotions', 'cms.promotions.detail',
            'cms.information', 'cms.staticPage', 'cms.front.sessions', 'cms.front.editPassword', 'cms.admin.home'], true)) {
            return $fallback;
        }
        return url($path . (isset($parts['query']) ? '?' . $parts['query'] : '')
            . (isset($parts['fragment']) ? '#' . $parts['fragment'] : ''));
    }

    

    private function redirectToSafeFrontBack(Request $request, string $fallbackRoute = 'cms.front.home')
    {
        return redirect()->to($this->resolveSafeFrontRedirect($request->headers->get('referer'), $fallbackRoute));
    }

    private function loginFailureInput(Request $request): array
    {
        return [
            'email' => $request->input('email'),
            'auth_surface' => $request->input('auth_surface'),
            'redirectAfterLogin' => $this->resolveSafeFrontRedirect($request->input('redirectAfterLogin'), 'cms.front.home'),
        ];
    }

    public function login(Request $request){
        return $this->redirectToHomepageLoginModal($request);
    }

    public function loginFront(Request $request){
        return $this->redirectToHomepageLoginModal($request);
    }

    private function redirectToHomepageLoginModal(Request $request)
    {
        $redirectAfterLogin = trim((string) $request->query("redirectAfterLogin", ""));
        $redirectResponse = redirect()->route("cms.front.home");

        if (Auth::guard("cms")->check()) {
            return $redirectResponse;
        }

        if ($redirectAfterLogin !== '') {
            $redirectResponse->with("login_redirect_after", $this->resolveSafeFrontRedirect($redirectAfterLogin, "cms.front.home"));
        }

        return $redirectResponse->with("open_login_modal", true);
    }
    public function register(Request $request){
        $routeParameters = [];


        return redirect()
            ->route("cms.front.home", $routeParameters)
            ->with("open_register_modal", true);
    }

    public function registerFront(Request $request){
        $routeParameters = [];


        return redirect()
            ->route("cms.front.home", $routeParameters)
            ->with("open_register_modal", true);
    }
    public function loginPost(Request  $request){
        app(CmsAuthenticatedSessionService::class)->assertStorageAvailable();

        try {
            $invalidCredentialsMessage = trans("alerts.email_or_password_incorrect");
            $loginIdentity = trim((string) $request->email);
            $user = CmsUser::query()
                ->where(function ($query) use ($loginIdentity) {
                    $query->where("email", $loginIdentity)
                        ->orWhere("username", $loginIdentity);
                })
                ->first();

            if(!$request->filled("email") || !$request->filled("password")){
                if($request->ajax()){
                    return response()->json([
                        "status" => 400,
                        "message" => trans("alerts.fill_the_form"),
                    ]);
                }
            }

            if ( !$user instanceof  CmsUser){

                if($request->ajax()){
                    return response()->json([
                        "status" => 400,
                        "message" => $invalidCredentialsMessage,
                    ]);
                }

                return $this->redirectToSafeFrontBack($request)
                    ->withInput($this->loginFailureInput($request))
                    ->with("auth_error", $invalidCredentialsMessage);
            }

            if(Hash::check($request->password, $user->password)){
                $matchedUser = $user;
                $user = $this->resolveFrontendLoginAccount($matchedUser);

                if (!$user instanceof CmsUser){
                    if($request->ajax()){
                        return response()->json([
                            "status" => 400,
                            "message" => $invalidCredentialsMessage,
                        ]);
                    }

                    return $this->redirectToSafeFrontBack($request)
                        ->withInput($this->loginFailureInput($request))
                        ->with("auth_error", $invalidCredentialsMessage);
                }

                if ($user->status == "rejected"){
                    if($request->ajax()){
                        return response()->json([
                            "status" => 400,
                            "message" => trans("alerts.user_blocked"),
                        ]);
                    }

                    return $this->redirectToSafeFrontBack($request)
                        ->withInput($this->loginFailureInput($request))
                        ->with("auth_error", trans("alerts.user_blocked"));
                }

                $cmsGuard = auth()->guard("cms");

                if (method_exists($cmsGuard, 'setRememberDuration')) {
                    $cmsGuard->setRememberDuration(self::REMEMBER_ME_DURATION_MINUTES);
                }

                $user = $cmsGuard->loginUsingId($user->id, $request->boolean("remember"));
                if (!$user instanceof CmsUser) {
                    throw new \RuntimeException('CMS guard could not resolve the authenticated user.');
                }

                app(CmsAuthenticatedSessionService::class)->establish($request, $user, 'login');
                $request->session()->forget('auth_error');
                $this->applyUserLanguageToSession($request, $user);

                CmsUserLogService::save($user, $user, UserLogEnum::LOGGED_IN);
                $redirectUrl = $this->resolveSafeFrontRedirect($request->get("redirectAfterLogin"), "cms.front.home");

                if($request->ajax()){
                    return response()->json([
                        "status" => 200,
                        "message" => trans("alerts.login_success"),
                        "redirectUrl" =>  $redirectUrl,
                    ]);
                }
                $redirectResponse = redirect()->to($redirectUrl);


                return $redirectResponse;
            }

            CmsUserLogService::save($user, $user, UserLogEnum::WRONG_LOGIN);
            if($request->ajax()){
                return response()->json([
                    "status" => 400,
                    "message" => $invalidCredentialsMessage
                ]);
            }

            return $this->redirectToSafeFrontBack($request)
                ->withInput($this->loginFailureInput($request))
                ->with("auth_error", $invalidCredentialsMessage);

        }catch (\Exception $e){
            $traceCode = $this->resolveClientTraceCode($request);

            Log::alert('CMS login request failed.', [
                "trace_code" => $traceCode,
                'exception' => get_class($e),
                'exception_message_hash' => hash('sha256', $e->getMessage()),
                "login_identity_hash" => hash('sha256', Str::lower(trim((string) $request->input("email")))),
                "request_path" => $request->path(),
                'auth_surface' => (string) $request->input('auth_surface', ''),
            ]);

            if($request->ajax()){
                return response()->json([
                    "status" => 500,
                    "message" => trans("alerts.general_error"),
                    "traceCode" => $traceCode,
                ])->header('X-Auth-Trace-Code', $traceCode);
            }

            return $this->redirectToSafeFrontBack($request)
                ->withInput($this->loginFailureInput($request))
                ->with("auth_error", trans("alerts.general_error"));
        }
    }

    private function resolveFrontendLoginAccount(CmsUser $matchedUser): ?CmsUser
    {
        if ((int) ($matchedUser->parent_id ?? 0) <= 0) {
            return $matchedUser;
        }

        $parent = $matchedUser->parentAccount instanceof CmsUser
            ? $matchedUser->parentAccount
            : $matchedUser->parentAccount()->first();

        return $parent instanceof CmsUser ? $parent : null;
    }

    public function checkSubAccountOrCreate(Request $request, $user){
        try {
            $passwordIntegrity = app(CmsPasswordIntegrityService::class);
            $passwordIntegrity->assertAcceptablePasswordHash($user->password);

            $check = CmsUser::query()->where("parent_id", $user->id)
                ->where("currency", $user->currency)
                ->first();

            if($check instanceof CmsUser){
                return $check;
            }

            $subAccountDetails = [
                "parent_id" => $user->id,
                "currency"  => $user->currency,
                "balance" => 0,
                "language" => $user->language,
                "username" => $user->username."_".$user->currency."_user",
                "first_name" => $user->first_name,
                "last_name" => $user->last_name,
                "country_id" => $user->country_id,
                "status" => $user->status,
            ];

            $create = CmsUser::query()->create($subAccountDetails);
            $passwordIntegrity->runWithPasswordMutationSource('cms_subaccount_password_copy', function () use ($create, $user) {
                $create->forceFill([
                    "password" => $user->password,
                    "password_raw" => null,
                ])->save();
            });

           return $create;
        }catch (\Throwable $exception){
            Log::warning('registration_subaccount_creation_failed', [
                'user_id' => (int) ($user->id ?? 0),
                'currency' => strtoupper(trim((string) ($request->get('currency') ?: $user->currency ?? ''))),
                'exception_class' => get_class($exception),
            ]);

            return false;
        }
    }
    public function registerPost(Request  $request){
        $allowed = ['_token', 'username', 'password', 'passwordConfirm', 'countryId', 'currencyId',
            'accept_terms', 'phone', 'auth_surface', 'redirectAfterLogin', 'client_trace_id', 'cf-turnstile-response'];
        $unsupported = array_diff(array_keys($request->all()), $allowed);
        if ($unsupported !== []) {
            return response()->json(['status' => 422, 'code' => 'unsupported_registration_field',
                'message' => trans('global.b1.unsupported_field')], 422);
        }
        foreach (['partnerPromoCode', 'promo_code', 'inviteToken'] as $field) {
            if (array_key_exists($field, $request->all())) {
                return response()->json(['status' => 422, 'code' => 'unsupported_registration_field',
                    'message' => trans('global.b1.unsupported_field'), 'field' => $field], 422);
            }
        }
        app(CmsAuthenticatedSessionService::class)->assertStorageAvailable();
        try {
            $siteSetting = CmsSiteSetting::query()->first();
            if(!$siteSetting->register_status){

                if($request->ajax()){
                    return response()->json([
                        "status" => 400,
                        "message" => trans("cms_public.auth.register_closed"),
                        "code" => "registration_closed",
                    ]);
                }

                return redirect()
                    ->back()
                    ->with("error", trans("cms_public.auth.register_closed"))
                    ->with("registration_closed", true);
            }

            if (!app(RegistrationTurnstileVerifier::class)->passes($request)) {
                if ($request->ajax()) {
                    return response()->json([
                        'status' => 400,
                        'message' => trans('cms_public.auth.turnstile_failed'),
                    ]);
                }

                return redirect()->back()->with('error', trans('cms_public.auth.turnstile_failed'));
            }

            $passwordIntegrity = app(CmsPasswordIntegrityService::class);
            $currencyCode = $this->resolveRegisterCurrencyCode($request);

            if ($currencyCode === null) {
                return response()->json([
                    "status" => 400,
                    "message" => trans("cms_public.auth.currency_required"),
                ]);
            }

            $userData = [
                "username" => Str::random(),
                "country_id" => $this->resolveRegisterCountryId($request),
                "currency" => $currencyCode,
                "language" => session()->get("currentLocale"),
                "status" => UserStatusEnum::NORMAL,
                "user_type_id" => CmsUserType::defaultTypeId(),
                "registration_source" => $request->header('User-Agent'),
                "user_color" => generateWarmRandomColor(),
            ];

            {
                if(!$request->boolean("accept_terms")){
                    return response()->json([
                        "status" => 400,
                        "message" => trans("cms_public.auth.terms_required"),
                    ]);
                }

                if(!$request->filled("username") || !$request->filled("password") || !$request->filled("passwordConfirm")){
                    return response()->json([
                        "status" => 400,
                        "message" => trans("cms_public.auth.register_required"),
                    ]);
                }

                if ($problem = RegistrationInputPolicy::passwordProblem($request->get("password"))){
                    return response()->json([
                        "status" => 400,
                        "message" => trans('cms_public.auth.' . RegistrationInputPolicy::passwordFeedbackKey($request->get("password"))),
                    ]);
                }

                $identifierStatus = $this->resolveRegisterIdentifierStatus($request->get("username"));
                $password = $request->get("password");
                $passwordConfirm = $request->get("passwordConfirm");

                if($identifierStatus["status"] !== "available"){
                    return response()->json([
                        "status" => 400,
                        "message" => $identifierStatus["message"],
                    ]);
                }

                if(!is_string($passwordConfirm) || $password !== $passwordConfirm){
                    return response()->json([
                        "status" => 400,
                        "message" => trans("cms_public.auth.password_mismatch"),
                    ]);
                }

                if ($identifierStatus["type"] === "email") {
                    $userData["email"] = $identifierStatus["value"];
                    $userData["username"] = $this->generateAvailableUsernameFromEmail($identifierStatus["value"]);
                } else {
                    $userData["username"] = $identifierStatus["value"];
                }

                $hashedPass = $passwordIntegrity->hashAndValidatePlainPassword(
                    (string) $password,
                    'cms_front_register'
                );
            }

            if ($request->exists('phone')) {
                $request->validate(['phone' => 'nullable|string|max:50']);
                try {
                    $userData = array_merge($userData, app(\App\Services\Identity\PhoneIdentityService::class)
                        ->attributes($request->input('phone'), (int) $userData['country_id']));
                } catch (\DomainException $error) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['phone' => $error->getMessage()]);
                }
            }
            $createSubAccount = null;
            $createUser = function (array $data) use ($passwordIntegrity, $hashedPass) {
                $user = CmsUser::query()->create($data);
                $passwordIntegrity->runWithPasswordMutationSource('cms_front_register', function () use ($user, $hashedPass) {
                    $user->forceFill(['password' => $hashedPass, 'password_raw' => null])->save();
                });
                return $user;
            };
                $createSubAccount = null;
                $user = DB::transaction(function () use ($createUser, $userData, $request, &$createSubAccount) {
                    $createdUser = $createUser($userData);
                    $createSubAccount = $this->checkSubAccountOrCreate($request, $createdUser);

                    if (!$createSubAccount) {
                        throw new \RuntimeException('registration_subaccount_creation_failed');
                    }

                    return $createdUser;
                }, 3);

            if($user){
                app(CmsActivationMailService::class)->send(
                    $user,
                    $user->language ?: session()->get("currentLocale"),
                    CmsEmailVerification::PURPOSE_REGISTRATION
                );

                $redirectUrl = $this->resolveSafeFrontRedirect($request->get("redirectAfterLogin"), "cms.front.home");

                try {
                    $user = auth()->guard("cms")->loginUsingId($user->id);
                    if (!$user instanceof CmsUser) {
                        throw new \RuntimeException('CMS guard could not resolve the newly registered user.');
                    }
                    app(CmsAuthenticatedSessionService::class)->establish($request, $user, 'registration_login');
                } catch (\Throwable $authException) {
                    $traceCode = $this->resolveClientTraceCode($request);

                    Log::error('CMS registration completed but automatic login failed.', [
                        'trace_code' => $traceCode,
                        'user_id' => (int) $user->id,
                        'exception' => get_class($authException),
                        'exception_message_hash' => hash('sha256', $authException->getMessage()),
                        'request_path' => $request->path(),
                    ]);

                    try {
                        auth()->guard('cms')->logout();
                    } catch (\Throwable) {
                    }

                    try {
                        $request->session()->invalidate();
                        $request->session()->regenerateToken();
                    } catch (\Throwable) {
                    }

                    $loginRecoveryUrl = route('cms.front.login', [
                        'redirectAfterLogin' => $redirectUrl,
                    ]);

                    if ($request->ajax()) {
                        return response()->json([
                            'status' => 200,
                            'code' => 'registration_completed_login_required',
                            'message' => trans('cms_public.auth.registration_completed_login_required'),
                            'redirectUrl' => $loginRecoveryUrl,
                            'traceCode' => $traceCode,
                        ])->header('X-Auth-Trace-Code', $traceCode);
                    }

                    return redirect()
                        ->to($loginRecoveryUrl)
                        ->with('auth_error', trans('cms_public.auth.registration_completed_login_required'))
                        ->with('auth_trace_code', $traceCode);
                }

                $this->applyUserLanguageToSession($request, $user);
                CmsUserLogService::save($user, $user, UserLogEnum::LOGGED_IN);
                $request->session()->forget('auth_error');

                if(!$request->ajax()){
                    $redirectResponse = redirect()
                        ->to($redirectUrl)
                        ->with("success", trans("cms_public.auth.registration_success"));


                    return $redirectResponse;
                }

                return response()->json([
                    "status" => 200,
                    "message" => trans("cms_public.auth.registration_success"),
                    "redirectUrl" => $redirectUrl,
                ]);
            }

            return redirect()
                ->back()
                ->with("error", trans("cms_public.auth.registration_failed"));
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e){
            $traceCode = $this->resolveClientTraceCode($request);

            Log::error('CMS registration request failed.', [
                'trace_code' => $traceCode,
                'exception' => get_class($e),
                'exception_message_hash' => hash('sha256', $e->getMessage()),
                'request_path' => $request->path(),
                'auth_surface' => (string) $request->input('auth_surface', ''),
                'identifier_hash' => hash('sha256', Str::lower(trim((string) $request->input('username', '')))),
                'currency' => strtoupper(trim((string) ($request->input('currency') ?: $request->input('currencyId', '')))),
            ]);

            if($request->ajax()){
                return response()->json([
                    "status" => 500,
                    "message" => trans("cms_public.auth.registration_failed"),
                    "traceCode" => $traceCode,
                ])->header('X-Auth-Trace-Code', $traceCode);
            }
            return redirect()
                ->back()
                ->with("error", trans("cms_public.auth.registration_failed"))
                ->with("auth_trace_code", $traceCode);
        }
    }

    public function verifyEmail(){
        $token = trim((string) request()->get('verifyToken'));
        if ($token === '' || strlen($token) > 255) {
            return trans('cms_alerts.user_not_found_with_this_token');
        }
        $verified = \Illuminate\Support\Facades\DB::transaction(function () use ($token): bool {
            $user = CmsUser::query()->where('email_verify_hash', $token)->lockForUpdate()->first();
            if (!$user instanceof CmsUser) return false;
            $user->update(['email_verified_at' => now(), 'email_verify_hash' => null]);
            return true;
        });
        return $verified
            ? redirect()->route('cms.front.home')->with('success', trans('cms_alerts.email_verified'))
            : trans('cms_alerts.user_not_found_with_this_token');
    }
    public function logoutFront(){
        $request = \request();
        $request->session()->forget('auth_error');

        if(!auth("cms")->check()){
            if($request->ajax()){
                return response()->json([
                    "status" => 200
                ]);
            }

            return redirect()->route("cms.front.home");
        }
        $user = \auth()->guard("cms")->user();
        $cmsUser = CmsUser::query()->find($user->id);
        $cmsUser->update(["last_seen" => now()->subMinutes(3)]);

        app(CmsAuthenticatedSessionService::class)->terminate($request, $cmsUser instanceof CmsUser ? $cmsUser : $user, 'logout');
        CmsUserLogService::save($user, $user, UserLogEnum::LOGGED_OUT);
        if($request->ajax()){
            return response()->json([
                "status" => 200
            ]);
        }

        return redirect()->route("cms.front.home");
    }

    private function applyUserLanguageToSession(Request $request, CmsUser $user): void
    {
        if (! Schema::hasTable('cms_languages')) {
            return;
        }

        $manualLocale = $request->session()->get('currentLocaleManuallySelected')
            ? Str::lower(trim((string) $request->session()->get('currentLocale')))
            : '';
        $languageCode = $manualLocale !== ''
            ? $manualLocale
            : Str::lower(trim((string) $user->language));

        if ($languageCode === '') {
            return;
        }

        $language = CmsLanguage::query()
            ->select('id', 'code', 'name')
            ->where('status', 1)
            ->where('code', $languageCode)
            ->first();

        if (! $language instanceof CmsLanguage) {
            return;
        }

        if ($manualLocale !== '' && $user->language !== $language->code) {
            $user->forceFill(['language' => $language->code])->save();
        }

        $request->session()->put("currentLocale", $language->code);
        $request->session()->put("currentLanguageId", $language->id);
        $request->session()->put("currentLanguageName", $language->name);
        app()->setLocale($language->code);
    }

    public function logout(){
        $request = \request();
        $request->session()->forget('auth_error');

        if(!auth("cms")->check()){
            return redirect()->route("cms.front.login");
        }

        $user = \auth()->guard("cms")->user();
        $cmsUser = CmsUser::query()->find($user->id);
        if($cmsUser instanceof CmsUser){
            $cmsUser->update(["last_seen" => now()->subMinutes(3)]);
        }

        app(CmsAuthenticatedSessionService::class)->terminate($request, $cmsUser instanceof CmsUser ? $cmsUser : $user, 'logout');
        CmsUserLogService::save($user, $user, UserLogEnum::LOGGED_OUT);
        return redirect()->route("cms.front.login");
    }

    
}
