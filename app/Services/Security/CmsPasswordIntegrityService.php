<?php

namespace App\Services\Security;

use App\Models\CmsSensitiveMutationEvent;
use App\Models\CmsUser;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CmsPasswordIntegrityService
{
    private const PASSWORD_FIELDS = ['password', 'password_raw'];
    private static array $passwordMutationSourceStack = [];

    public function fillableWithoutPasswordFields(CmsUser $user): array
    {
        return array_values(array_diff($user->getFillable(), self::PASSWORD_FIELDS));
    }

    public function hashAndValidatePlainPassword(string $plain, string $source): string
    {
        $hash = Hash::make($plain);
        $this->assertAcceptablePasswordHash($hash);

        return $hash;
    }

    public function runWithPasswordMutationSource(string $source, callable $callback): mixed
    {
        $resolvedSource = trim($source) !== '' ? trim($source) : 'cms_unknown_password_mutation';
        self::$passwordMutationSourceStack[] = $resolvedSource;

        try {
            return $callback();
        } finally {
            array_pop(self::$passwordMutationSourceStack);
        }
    }

    public function describeHash(?string $hash): array
    {
        $hash = (string) $hash;

        if ($hash === '') {
            return [
                'length' => 0,
                'prefix' => null,
                'algorithm_guess' => 'empty',
                'malformed_bcrypt_like' => false,
                'needs_rehash' => null,
            ];
        }

        return [
            'length' => strlen($hash),
            'prefix' => substr($hash, 0, 7),
            'algorithm_guess' => $this->guessAlgorithm($hash),
            'malformed_bcrypt_like' => $this->isMalformedBcryptLike($hash),
            'needs_rehash' => $this->needsRehashSafely($hash),
        ];
    }

    public function assertAcceptablePasswordHash(?string $hash): void
    {
        $hash = (string) $hash;

        if ($hash === '') {
            return;
        }

        if ($this->isMalformedBcryptLike($hash)) {
            throw ValidationException::withMessages([
                'password' => 'Malformed password hash rejected.',
            ]);
        }
    }

    public function auditPasswordMutation(CmsUser $user, ?string $oldHash, ?string $newHash, string $source): void
    {
        $metadata = [
            'old' => $this->describeHash($oldHash),
            'new' => $this->describeHash($newHash),
        ];
        $context = $this->context();
        $payload = [
            'event_type' => 'cms_user_password_changed',
            'subject_type' => CmsUser::class,
            'subject_id' => $user->getKey(),
            'actor_type' => $context['actor_type'],
            'actor_id' => $context['actor_id'],
            'source' => $this->resolveAuditSource($source),
            'route_name' => $context['route_name'],
            'request_path' => $context['request_path'],
            'command_name' => $context['command_name'],
            'ip_address' => $context['ip_address'],
            'user_agent' => $context['user_agent'],
            'metadata' => array_merge($metadata, [
                'user_id' => $user->getKey(),
                'username' => $user->username,
            ]),
        ];

        if ($metadata['new']['malformed_bcrypt_like']) {
            Log::critical('cms_users.password changed to malformed bcrypt-like value', $payload);
        } else {
            Log::warning('cms_users.password changed', $payload);
        }

        $this->writeSensitiveMutationEvent($payload);
    }

    public function isMalformedBcryptLike(?string $hash): bool
    {
        $hash = (string) $hash;

        if (!$this->isBcryptLike($hash)) {
            return false;
        }

        return strlen($hash) !== 60 || !preg_match('/^\$2[aby]\$\d{2}\$/', $hash);
    }

    private function guessAlgorithm(string $hash): string
    {
        if ($this->isBcryptLike($hash)) {
            return 'bcrypt';
        }

        if (Str::startsWith($hash, ['$argon2i$', '$argon2id$'])) {
            return 'argon';
        }

        return 'unknown';
    }

    private function isBcryptLike(string $hash): bool
    {
        return Str::startsWith($hash, ['$2y$', '$2a$', '$2b$']);
    }

    private function needsRehashSafely(string $hash): ?bool
    {
        try {
            return Hash::needsRehash($hash);
        } catch (\Throwable) {
            return null;
        }
    }

    public function currentPasswordMutationSource(): ?string
    {
        $current = end(self::$passwordMutationSourceStack);

        return is_string($current) && $current !== '' ? $current : null;
    }

    private function context(): array
    {
        $request = request();
        $operator = $this->guardUser('cms') ?: $this->guardUser(null);

        return [
            'actor_type' => $operator ? CmsUser::class : null,
            'actor_id' => $operator?->getAuthIdentifier(),
            'route_name' => optional($request->route())->getName(),
            'request_path' => app()->runningInConsole() ? null : $request->path(),
            'command_name' => app()->runningInConsole() ? ($_SERVER['argv'][1] ?? null) : null,
            'ip_address' => app()->runningInConsole() ? null : $request->ip(),
            'user_agent' => app()->runningInConsole() ? null : substr((string) $request->userAgent(), 0, 500),
        ];
    }

    private function guardUser(?string $guard): mixed
    {
        try {
            return $guard ? auth()->guard($guard)->user() : auth()->user();
        } catch (\Throwable) {
            return null;
        }
    }

    private function writeSensitiveMutationEvent(array $payload): void
    {
        try {
            if (!Schema::hasTable('cms_sensitive_mutation_events')) {
                throw new \RuntimeException('Sensitive mutation audit storage unavailable.');
            }

            CmsSensitiveMutationEvent::query()->create($payload);
        } catch (\Throwable $exception) {
            throw new \RuntimeException('Sensitive mutation audit could not be persisted.', 0, $exception);
        }
    }

    private function resolveAuditSource(?string $fallbackSource = null): string
    {
        $scopedSource = $this->currentPasswordMutationSource();
        if (is_string($scopedSource) && $scopedSource !== '') {
            return $scopedSource;
        }

        if (app()->runningInConsole()) {
            return 'cms_console_or_unknown';
        }

        $normalizedFallback = trim((string) $fallbackSource);
        if ($normalizedFallback !== '') {
            return $normalizedFallback;
        }

        return 'cms_unknown_password_mutation';
    }
}
