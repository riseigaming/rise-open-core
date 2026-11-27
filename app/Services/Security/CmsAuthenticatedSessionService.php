<?php

namespace App\Services\Security;

use App\Models\CmsSession;
use App\Models\CmsUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class CmsAuthenticatedSessionService
{
    public const SESSION_RECORD_KEY = 'cms_authenticated_session_id';
    public const AUTHENTICATED_AT_KEY = 'cms_authenticated_at';
    public const RECENT_AUTH_AT_KEY = 'cms_recent_auth_at';
    public const DEVICE_COOKIE = 'cms_device_session';
    private const CREDENTIAL_STATE_KEY = 'cms_credential_state';

    public function assertStorageAvailable(): void
    {
        if (!$this->sessionTableAvailable() || !$this->lifecycleColumnsAvailable() || !$this->historyAvailable()) {
            abort(503, 'Session registry unavailable. Please retry.');
        }
    }

    public function establish(Request $request, CmsUser $user, string $reason, bool $rotate = true): void
    {
        $this->assertStorageAvailable();
        $this->deleteLinkedRecord($request, 'session_rotated');

        if (!$user->getRememberToken()) {
            CmsUser::query()->whereKey($user->id)->where(function ($query): void {
                $query->whereNull('remember_token')->orWhere('remember_token', '');
            })->update(['remember_token' => Str::random(60)]);
            $user->refresh();
        }

        if ($rotate) {
            $request->session()->regenerate(true);
        }

        $now = now()->timestamp;
        $request->session()->put([
            self::AUTHENTICATED_AT_KEY => $now,
            self::RECENT_AUTH_AT_KEY => $now,
            self::CREDENTIAL_STATE_KEY => $this->credentialState($user),
        ]);

        $record = $this->createRecord($request, $user);
        if ($record instanceof CmsSession) {
            $request->session()->put(self::SESSION_RECORD_KEY, (int) $record->id);
            Cookie::queue(Cookie::make(self::DEVICE_COOKIE, (string) $record->id,
                $record->is_persistent ? $this->rememberMinutes() : 0,
                config('session.path', '/'), config('session.domain'),
                (bool) config('session.secure'), true, false, config('session.same_site', 'lax')));
        } else {
            $request->session()->forget(self::SESSION_RECORD_KEY);
        }
    }

    public function valid(Request $request, CmsUser $user): bool
    {
        if (!$request->hasSession()) {
            return false;
        }
        if (!$this->credentialStateMatches($request, $user) || $this->absoluteLifetimeExpired($request)) {
            return false;
        }

        if (!$this->sessionTableAvailable()) {
            abort(503, 'Session registry unavailable. Please retry.');
        }

        try {
            $recordId = (int) $request->session()->get(self::SESSION_RECORD_KEY, 0);
            $restoring = $recordId === 0 && auth('cms')->viaRemember();
            if ($restoring) {
                // This cookie is encrypted by the web middleware; never match devices by user agent.
                $recordId = (int) $request->cookie(self::DEVICE_COOKIE, 0);
            }
            $record = $recordId > 0 ? CmsSession::query()->whereKey($recordId)
                ->where('cms_user_id', $user->id)->first() : null;
            if (!$record || (string) $record->remember_token !== (string) $user->getRememberToken()
                || $this->expiresAt($record)->isPast()
                || ($restoring && !$record->is_persistent)) {
                return false;
            }
            if ($restoring) {
                $request->session()->regenerate(true);
                $request->session()->put([
                    self::SESSION_RECORD_KEY => (int) $record->id,
                    self::AUTHENTICATED_AT_KEY => $record->created_at->timestamp,
                    self::RECENT_AUTH_AT_KEY => 0,
                    self::CREDENTIAL_STATE_KEY => $this->credentialState($user),
                ]);
            }

            return !$this->absoluteLifetimeExpired($request);
        } catch (Throwable) {
            // Do not log users out or bypass revocation during a registry outage.
            abort(503, 'Session registry unavailable. Please retry.');
        }
    }

    public function observeContext(Request $request, CmsUser $user): void
    {
        if (!$this->sessionTableAvailable()) {
            return;
        }

        try {
            $recordId = (int) $request->session()->get(self::SESSION_RECORD_KEY, 0);
            $record = $recordId > 0
                ? CmsSession::query()->whereKey($recordId)->where('cms_user_id', $user->id)->first()
                : null;

            if (!$record instanceof CmsSession) {
                return;
            }

            $agent = getUserAgentData();
            $ip = trim((string) $request->ip());
            $userAgent = Str::limit((string) $agent['userAgent'], 255, '');
            $ipChanged = (string) $record->ip_address !== $ip;
            $userAgentChanged = (string) $record->user_agent !== $userAgent;

            $context = [
                'ip_address' => $ip !== '' ? $ip : null,
                'user_agent' => $userAgent,
                'browser_name' => Str::limit((string) $agent['browser'], 255, ''),
                'os_name' => Str::limit((string) $agent['os'], 255, ''),
                'updated_at' => now(),
            ];
            if ($this->lifecycleColumnsAvailable() && !$record->is_persistent) {
                $context['expires_at'] = now()->addMinutes((int) config('session.lifetime', 120));
            }
            // A concurrent revocation must never reinsert or renew a deleted record.
            CmsSession::query()->whereKey($record->id)->update($context);

        } catch (Throwable) {
            // Context is observational only and must never interrupt authentication.
        }
    }

    public function terminate(Request $request, ?CmsUser $user, string $reason): void
    {
        if ($user instanceof CmsUser) {
            $this->deleteLinkedRecord($request, $reason);
        }

        $this->logoutCurrentDevice($request);
    }

    public function revokeRecord(Request $request, CmsUser $user, int $recordId): bool
    {
        if (!$this->sessionTableAvailable()) {
            return false;
        }

        $record = CmsSession::query()->whereKey($recordId)->where('cms_user_id', $user->id)->first();
        if (!$record instanceof CmsSession) {
            return false;
        }

        $isCurrent = (int) $request->session()->get(self::SESSION_RECORD_KEY, 0) === (int) $record->id;
        $this->archiveAndDelete($record, 'self_service', (int) $user->id);

        if ($isCurrent) {
            $this->logoutCurrentDevice($request);
        }

        return true;
    }

    public function revokeAll(Request $request, CmsUser $user): void
    {
        if ($this->sessionTableAvailable()) {
            $this->revokeUserRecords($user, 'self_service_all', (int) $user->id);
        }

        $this->logoutCurrentDevice($request);
    }

    public function afterPasswordChange(Request $request, CmsUser $user, bool $keepCurrent): void
    {
        abort_unless($this->sessionTableAvailable() && $this->lifecycleColumnsAvailable() && $this->historyAvailable(), 503, 'Session registry unavailable. Please retry.');
        $currentRecordId = $keepCurrent ? (int) $request->session()->get(self::SESSION_RECORD_KEY, 0) : 0;

        if ($this->sessionTableAvailable()) {
            $query = CmsSession::query()->where('cms_user_id', $user->id);
            if ($currentRecordId > 0) {
                $query->whereKeyNot($currentRecordId);
            }
            $query->eachById(fn (CmsSession $record) => $this->archiveAndDelete($record, 'password_changed', (int) $user->id));
        }

        $user->setRememberToken(Str::random(60));
        $user->save();

        if ($keepCurrent && auth()->guard('cms')->id() === $user->id) {
            $request->session()->regenerate(true);
            $request->session()->put([
                self::RECENT_AUTH_AT_KEY => now()->timestamp,
                self::CREDENTIAL_STATE_KEY => $this->credentialState($user->fresh()),
            ]);

            if ($currentRecordId > 0) {
                CmsSession::query()->whereKey($currentRecordId)->update([
                    'remember_token' => $user->getRememberToken(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function afterAuthorizationChange(Request $request, CmsUser $user, bool $keepCurrent): void
    {
        if ($keepCurrent && auth()->guard('cms')->id() === $user->id) {
            $this->revokeUserRecords($user, 'authorization_changed', (int) $user->id,
                (int) $request->session()->get(self::SESSION_RECORD_KEY, 0));
            $this->establish($request, $user->fresh(), 'authorization_changed');

            return;
        }

        if ($this->sessionTableAvailable()) {
            $this->revokeUserRecords($user, 'authorization_changed', (int) auth('cms')->id());
        }

        $user->setRememberToken(Str::random(60));
        $user->save();
    }

    public function recentlyAuthenticated(Request $request): bool
    {
        $confirmedAt = (int) $request->session()->get(self::RECENT_AUTH_AT_KEY, 0);
        $timeout = max(60, (int) config('auth.cms_recent_auth_timeout', 1800));

        return $confirmedAt > 0 && $confirmedAt >= now()->subSeconds($timeout)->timestamp;
    }

    private function credentialStateMatches(Request $request, CmsUser $user): bool
    {
        $stored = (string) $request->session()->get(self::CREDENTIAL_STATE_KEY, '');
        if ($stored === '') {
            return true;
        }

        return hash_equals($stored, $this->credentialState($user));
    }

    private function absoluteLifetimeExpired(Request $request): bool
    {
        if (!Str::startsWith('/' . trim($request->path(), '/'), '/cms/admin')) {
            return false;
        }

        $minutes = max(0, (int) config('auth.cms_staff_absolute_lifetime', 720));
        if ($minutes === 0) {
            return false;
        }

        $authenticatedAt = (int) $request->session()->get(self::AUTHENTICATED_AT_KEY, 0);

        return $authenticatedAt > 0 && $authenticatedAt < now()->subMinutes($minutes)->timestamp;
    }

    private function credentialState(CmsUser $user): string
    {
        return hash_hmac('sha256', implode('|', [
            (int) $user->id,
            (string) $user->getAuthPassword(),
            (string) $user->role,
            (string) $user->status,
            (string) $user->getRememberToken(),
        ]), (string) config('app.key'));
    }

    private function createRecord(Request $request, CmsUser $user): CmsSession
    {
        if (!$this->sessionTableAvailable()) {
            abort(503, 'Session registry unavailable. Please retry.');
        }

        try {
            $agent = getUserAgentData();

            $record = new CmsSession([
                'cms_user_id' => $user->id,
                'remember_token' => $user->getRememberToken(),
                'browser_name' => Str::limit((string) $agent['browser'], 255, ''),
                'os_name' => Str::limit((string) $agent['os'], 255, ''),
                'user_agent' => Str::limit((string) $agent['userAgent'], 255, ''),
                'ip_address' => trim((string) $request->ip()) ?: null,
            ]);
            if ($this->lifecycleColumnsAvailable()) {
                $persistent = $request->boolean('remember');
                $record->forceFill([
                    'is_persistent' => $persistent,
                    'expires_at' => now()->addMinutes($persistent ? $this->rememberMinutes() : (int) config('session.lifetime', 120)),
                ]);
            }
            $record->save();
            return $record;
        } catch (Throwable) {
            abort(503, 'Session registry unavailable. Please retry.');
        }
    }

    private function deleteLinkedRecord(Request $request, string $reason): void
    {
        if (!$this->sessionTableAvailable()) {
            return;
        }

        try {
            $recordId = (int) $request->session()->get(self::SESSION_RECORD_KEY, 0);
            if ($recordId > 0) {
                $record = CmsSession::find($recordId);
                if ($record) {
                    $this->archiveAndDelete($record, $reason, (int) auth('cms')->id());
                }
            }
            $request->session()->forget(self::SESSION_RECORD_KEY);
        } catch (Throwable) {
            abort(503, 'Session registry unavailable. Please retry.');
        }
    }

    private function sessionTableAvailable(): bool
    {
        try {
            return Schema::hasTable('cms_sessions');
        } catch (Throwable) {
            return false;
        }
    }

    public function lifecycleColumnsAvailable(): bool
    {
        return Schema::hasColumns('cms_sessions', ['expires_at', 'is_persistent']);
    }

    public function historyAvailable(): bool
    {
        return Schema::hasTable('cms_session_history');
    }

    private function rememberMinutes(): int
    {
        return max(1, (int) config('auth.cms_remember_session_days', 30)) * 1440;
    }

    public function expiresAt(CmsSession $record): \Illuminate\Support\Carbon
    {
        return $record->expires_at ?: ($record->updated_at ?: $record->created_at ?: now())->copy()
            ->addMinutes((int) config('session.lifetime', 120));
    }

    private function logoutCurrentDevice(Request $request): void
    {
        // logout() rotates the shared remember token and invalidates other devices.
        auth('cms')->logoutCurrentDevice();
        Cookie::queue(Cookie::forget(self::DEVICE_COOKIE, config('session.path', '/'), config('session.domain')));
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    private function archiveAndDelete(CmsSession $record, string $reason, ?int $actorId): bool
    {
        return DB::transaction(function () use ($record, $reason, $actorId): bool {
            $record = CmsSession::query()->whereKey($record->id)->lockForUpdate()->first();
            if (!$record) {
                return false;
            }
            abort_unless($this->historyAvailable(), 503, 'Session history unavailable. Please retry.');
            {
                DB::table('cms_session_history')->insert([
                    'session_record_id' => $record->id,
                    'cms_user_id' => $record->cms_user_id,
                    'ended_by' => $actorId ?: null,
                    'reason' => Str::limit($reason, 255, ''),
                    'browser_name' => $record->browser_name,
                    'os_name' => $record->os_name,
                    'ip_address' => $record->ip_address,
                    'user_agent' => $record->user_agent,
                    'started_at' => $record->created_at,
                    'last_activity_at' => $record->updated_at,
                    'ended_at' => now(),
                ]);
            }
            return (bool) $record->delete();
        });
    }

    private function revokeUserRecords(CmsUser $user, string $reason, ?int $actorId, int $except = 0): int
    {
        $count = 0;
        CmsSession::query()->where('cms_user_id', $user->id)->when($except > 0, fn ($q) => $q->whereKeyNot($except))
            ->eachById(function (CmsSession $record) use ($reason, $actorId, &$count): void {
                $count += (int) $this->archiveAndDelete($record, $reason, $actorId);
            });
        return $count;
    }

    public function revokeOthers(Request $request, CmsUser $user): int
    {
        $current = (int) $request->session()->get(self::SESSION_RECORD_KEY, 0);
        abort_unless($current > 0, 409);
        $count = $this->revokeUserRecords($user, 'self_service_others', (int) $user->id, $current);
        return $count;
    }

    public function revokeAsAdmin(Request $request, CmsUser $user, ?int $recordId, string $reason): int
    {
        abort_unless(auth('cms')->user()?->role === 'admin', 403);
        $actorId = (int) auth('cms')->id();
        if ($recordId !== null) {
            $record = CmsSession::query()->where('cms_user_id', $user->id)->whereKey($recordId)->firstOrFail();
            $count = (int) $this->archiveAndDelete($record, 'admin: ' . $reason, $actorId);
        } else {
            $count = $this->revokeUserRecords($user, 'admin: ' . $reason, $actorId);
        }
        if ($actorId === (int) $user->id && ($recordId === null
            || $recordId === (int) $request->session()->get(self::SESSION_RECORD_KEY))) {
            $this->logoutCurrentDevice($request);
        }
        return $count;
    }

    public function overview(Request $request, CmsUser $user, bool $admin = false): array
    {
        $current = (int) $request->session()->get(self::SESSION_RECORD_KEY, 0);
        $sessionFilter = $request->query('sessions') === 'all' ? 'all' : 'open';
        $query = CmsSession::query()->where('cms_user_id', $user->id);
        if ($sessionFilter === 'open') {
            $query->where('remember_token', $user->getRememberToken());
            $cutoff = now()->subMinutes((int) config('session.lifetime', 120));
            if ($this->lifecycleColumnsAvailable()) {
                $query->where(fn ($q) => $q->where('expires_at', '>', now())
                    ->orWhere(fn ($legacy) => $legacy->whereNull('expires_at')->where('updated_at', '>', $cutoff)));
            } else {
                $query->where('updated_at', '>', $cutoff);
            }
        }
        $sessions = $query->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$current])
            ->latest('updated_at')->paginate(20, ['*'], 'sessions_page');
        $sessions->getCollection()->each(function (CmsSession $record) use ($current, $admin, $user): void {
            $record->setAttribute('display_ip', $admin ? $record->ip_address : $this->maskedIpForDisplay((string) $record->ip_address));
            $record->setAttribute('is_current', (int) $record->id === $current);
            $record->setAttribute('is_expired', $this->expiresAt($record)->isPast()
                || (string) $record->remember_token !== (string) $user->getRememberToken());
        });
        $history = $this->historyAvailable() ? DB::table('cms_session_history')->where('cms_user_id', $user->id)
            ->orderByDesc('ended_at')->orderByDesc('id')->paginate(20, ['*'], 'history_page') : null;
        if ($history) {
            $history->getCollection()->each(function ($record) use ($admin): void {
                $record->display_ip = $admin ? $record->ip_address : $this->maskedIpForDisplay((string) $record->ip_address);
            });
        }
        return compact('sessions', 'history', 'sessionFilter') + ['sessionManagementReady' => $this->lifecycleColumnsAvailable() && $this->historyAvailable()];
    }

    

    public function maskedIpForDisplay(string $ip): ?string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            $parts[3] = 'x';

            return implode('.', $parts);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return implode(':', array_slice(explode(':', $ip), 0, 3)) . '::';
        }

        return null;
    }

    private function surface(Request $request): string
    {
        return Str::startsWith('/' . trim($request->path(), '/'), '/cms/admin') ? 'cms_admin' : 'cms_front';
    }
}
