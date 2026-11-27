<?php

namespace App\Services\Cms;

use App\Mail\CmsEmailVerification;
use App\Models\CmsUser;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class CmsActivationMailService
{
    private const TOKEN_PREFIX_REGISTRATION = 'registration_';

    private const TOKEN_PREFIX_EMAIL_CHANGE = 'email-change_';

    public function __construct(
        private readonly CmsMailDeliveryPolicy $mailPolicy
    ) {
    }

    public function send(CmsUser $user, ?string $locale = null, ?string $purpose = null): bool
    {
        if (! $this->mailPolicy->allows('verification')) {
            return false;
        }

        $email = trim((string) $user->email);

        if ($email === '') {
            return false;
        }

        try {
            $token = trim((string) $user->email_verify_hash);
            $purpose = $this->resolvePurpose($purpose, $token);

            if ($token === '') {
                $token = $this->tokenPrefix($purpose) . Str::uuid();
                $user->forceFill([
                    'email_verify_hash' => $token,
                ])->save();
            }

            Mail::to($email)->send(new CmsEmailVerification($user->fresh() ?: $user, $token, $locale, $purpose));

            return true;
        } catch (\Throwable $throwable) {
            Log::warning('cms_activation_mail_failed', [
                'cms_user_id' => $user->id,
                'exception' => $throwable::class,
                'message' => $throwable->getMessage(),
            ]);

            return false;
        }
    }

    private function resolvePurpose(?string $purpose, string $token): string
    {
        if ($purpose === CmsEmailVerification::PURPOSE_EMAIL_CHANGE) {
            return CmsEmailVerification::PURPOSE_EMAIL_CHANGE;
        }

        if ($purpose === CmsEmailVerification::PURPOSE_REGISTRATION) {
            return CmsEmailVerification::PURPOSE_REGISTRATION;
        }

        return Str::startsWith($token, self::TOKEN_PREFIX_EMAIL_CHANGE)
            ? CmsEmailVerification::PURPOSE_EMAIL_CHANGE
            : CmsEmailVerification::PURPOSE_REGISTRATION;
    }

    private function tokenPrefix(string $purpose): string
    {
        return $purpose === CmsEmailVerification::PURPOSE_EMAIL_CHANGE
            ? self::TOKEN_PREFIX_EMAIL_CHANGE
            : self::TOKEN_PREFIX_REGISTRATION;
    }
}
