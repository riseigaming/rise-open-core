<?php

namespace App\Services\Security;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class RegistrationTurnstileVerifier
{
    public function passes(Request $request): bool
    {
        if (!config('registration_security.turnstile.enabled', false)) {
            return true;
        }

        $token = $request->input('cf-turnstile-response');
        $secret = trim((string) config('registration_security.turnstile.secret_key', ''));
        if (!is_string($token) || $token === '' || strlen($token) > 2048 || $secret === '') {
            return false;
        }

        try {
            $response = Http::asForm()->timeout(3)->withOptions(['connect_timeout' => 2])
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                    'secret' => $secret,
                    'response' => $token,
                ]);
            if (!$response->ok() || $response->json('success') !== true) {
                return false;
            }

            $action = (string) config('registration_security.turnstile.expected_action', 'register');
            if ($action !== '' && $response->json('action') !== $action) {
                return false;
            }

            $hostname = strtolower(trim((string) config('registration_security.turnstile.expected_hostname', '')));

            return $hostname === '' || strtolower((string) $response->json('hostname')) === $hostname;
        } catch (\Throwable) {
            return false;
        }
    }
}
