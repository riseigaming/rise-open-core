<?php

namespace App\Services\Security;

/** New-registration rules only: never rewrite or restrict existing login credentials. */
final class RegistrationInputPolicy
{
    public static function identifierProblem($value): ?string
    {
        if (!is_string($value)) {
            return 'identifier_invalid';
        }
        $value = trim($value);
        if (str_contains($value, '@')) {
            return strlen($value) <= 100 && filter_var($value, FILTER_VALIDATE_EMAIL)
                ? null : 'identifier_invalid';
        }

        // Deliberately ASCII, with no transliteration: reject invisible/confusable
        // Unicode rather than silently turning it into somebody else's identifier.
        return preg_match('/\A[A-Za-z0-9][A-Za-z0-9_.-]{3,49}\z/D', $value)
            ? null : 'username_policy';
    }

    public static function passwordProblem($value): ?string
    {
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')
            || str_contains($value, "\0") || trim($value) === ''
            || mb_strlen($value, 'UTF-8') < 8) {
            return 'password_policy';
        }

        // bcrypt consumes bytes, not characters. Never truncate or normalize.
        return strlen($value) > 72 ? 'password_policy' : null;
    }

    /** Customer copy only; the acceptance rules above remain authoritative. */
    public static function passwordFeedbackKey($value): string
    {
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8') || str_contains($value, "\0")) {
            return 'password_unsupported';
        }
        if (trim($value) === '' || mb_strlen($value, 'UTF-8') < 8) {
            return 'password_short';
        }
        return 'password_too_long';
    }

    public static function identifierFeedbackKey($value): string
    {
        if (!is_string($value) || str_contains($value, '@')) return 'identifier_invalid';
        $value = trim($value);
        if (strlen($value) < 4 || strlen($value) > 50) return 'username_invalid';
        if (!preg_match('/\A[A-Za-z0-9_.-]+\z/D', $value)) return 'username_characters';
        return 'username_start';
    }
}
