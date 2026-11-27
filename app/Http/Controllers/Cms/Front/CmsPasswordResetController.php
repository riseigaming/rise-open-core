<?php

namespace App\Http\Controllers\Cms\Front;

use App\Http\Controllers\Controller;
use App\Mail\CmsPasswordResetMail;
use App\Models\CmsUser;
use App\Services\Security\CmsAuthenticatedSessionService;
use App\Services\Security\CmsPasswordIntegrityService;
use App\Services\Cms\CmsMailDeliveryPolicy;
use App\Support\CmsThemeResolver;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;

class CmsPasswordResetController extends Controller
{
    public function requestForm()
    {
        return view(CmsThemeResolver::viewName('auth.password-forgot', CmsThemeResolver::activePhysicalNamespace()));
    }

    public function sendLink(Request $request, CmsMailDeliveryPolicy $mailPolicy)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);
        $email = trim((string) $validated['email']);
        $user = CmsUser::query()
            ->where('email', $email)
            ->whereNotNull('email_verified_at')
            ->first();

        if (
            $user instanceof CmsUser
            && Schema::hasTable('password_resets')
            && $mailPolicy->allows('password_reset')
        ) {
            try {
                Password::broker('cms_users')->sendResetLink(
                    ['email' => $email],
                    function (CmsUser $recipient, string $token): void {
                        Mail::to($recipient->email)->send(new CmsPasswordResetMail(
                            $recipient,
                            $token,
                            $recipient->language ?: session()->get('currentLocale')
                        ));
                    }
                );
            } catch (\Throwable $throwable) {
                Log::warning('cms_password_reset_mail_failed', [
                    'cms_user_id' => $user->id,
                    'exception' => $throwable::class,
                    'message' => $throwable->getMessage(),
                ]);
            }
        }

        $response = back()->with('status', trans('cms_password.request_received'));

        if ($request->input('auth_surface') === 'modal_password_recovery') {
            $response->with('open_password_forgot_modal', true);
        }

        return $response;
    }

    public function resetForm(Request $request, string $token)
    {
        return view(CmsThemeResolver::viewName('auth.password-reset', CmsThemeResolver::activePhysicalNamespace()), [
            'token' => $token,
            'email' => trim((string) $request->query('email')),
        ]);
    }

    public function update(
        Request $request,
        CmsPasswordIntegrityService $passwordIntegrity,
        CmsAuthenticatedSessionService $sessions
    ) {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'confirmed', function ($attribute, $value, $fail) {
                if (\App\Services\Security\RegistrationInputPolicy::passwordProblem($value)) $fail(trans('cms_public.auth.password_policy'));
            }],
        ]);

        if (! Schema::hasTable('password_resets')) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => trans('cms_password.reset_invalid')]);
        }

        $status = \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $request, $passwordIntegrity, $sessions) {
            CmsUser::query()->where('email', $validated['email'])->lockForUpdate()->first();
            return Password::broker('cms_users')->reset(
            $validated,
            function (CmsUser $user, string $password) use ($request, $passwordIntegrity, $sessions): void {
                $hash = $passwordIntegrity->hashAndValidatePlainPassword($password, 'cms_front_password_reset');

                $passwordIntegrity->runWithPasswordMutationSource('cms_front_password_reset', function () use ($user, $hash): void {
                    $user->forceFill([
                        'password' => $hash,
                        'password_raw' => null,
                    ])->save();
                });

                $sessions->afterPasswordChange($request, $user->fresh() ?: $user, false);
                event(new PasswordReset($user));
            }
        );
        });

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('cms.login')->with('success', trans('cms_password.reset_success'));
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => trans('cms_password.reset_invalid')]);
    }
}
