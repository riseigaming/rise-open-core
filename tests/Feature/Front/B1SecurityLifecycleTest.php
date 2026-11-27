<?php

namespace Tests\Feature\Front;

use App\Http\Controllers\Cms\Front\ProfileController;
use App\Models\CmsSensitiveMutationEvent;
use App\Models\CmsUser;
use App\Models\CmsSession;
use App\Services\Security\CmsAuthenticatedSessionService as Sessions;
use App\Services\Security\CmsPasswordIntegrityService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Session\FileSessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class B1SecurityLifecycleTest extends TestCase
{
    use DatabaseTransactions;

    private function device(CmsUser $user, bool $remember = false, string $path = '/cms/sessions'): Request
    {
        $request = Request::create('http://127.0.0.1:18017'.$path, 'GET', ['remember' => $remember], [], [], ['REMOTE_ADDR' => '192.0.2.12', 'HTTP_USER_AGENT' => 'B1-Local-Fixture']);
        $path = storage_path('framework/testing/security-sessions');
        if (!is_dir($path)) mkdir($path, 0700, true);
        $store = new Store('b1-security', new FileSessionHandler(app('files'), $path, 120));
        $store->start(); $request->setLaravelSession($store);
        $this->app->instance('request', $request);
        auth('cms')->setUser($user);
        app(Sessions::class)->establish($request, $user, 'acceptance_fixture');
        $store->save();
        $request->query->remove('remember');
        return $request;
    }

    private function admin(): CmsUser { return CmsUser::where('role', 'admin')->firstOrFail(); }

    public function test_two_devices_rotate_ids_and_revoke_others_with_real_history(): void
    {
        $user = $this->admin(); $service = app(Sessions::class);
        $a = $this->device($user); $aId = $a->session()->getId();
        $b = $this->device($user->fresh(), true);
        $aRecord = $a->session()->get(Sessions::SESSION_RECORD_KEY);
        $bRecord = $b->session()->get(Sessions::SESSION_RECORD_KEY);
        $this->assertNotSame($aId, $b->session()->getId());
        $this->assertTrue($service->valid($a, $user->fresh()));
        $this->assertTrue($service->valid($b, $user->fresh()));
        $this->assertTrue((bool) CmsSession::findOrFail($bRecord)->is_persistent);
        $this->assertSame('192.0.2.x',$service->maskedIpForDisplay('192.0.2.12'));
        $this->assertNull($service->maskedIpForDisplay('invalid'));
        $service->revokeOthers($a, $user->fresh());
        $this->assertNotNull(CmsSession::find($aRecord));
        $this->assertNull(CmsSession::find($bRecord));
        $this->assertFalse($service->valid($b, $user->fresh()));
        $this->assertTrue(DB::table('cms_session_history')->where('session_record_id', $bRecord)->where('reason', 'self_service_others')->exists());
    }

    public function test_wrong_owner_record_cannot_revoke_another_users_session(): void
    {
        $user = $this->admin(); $request = $this->device($user); $record = $request->session()->get(Sessions::SESSION_RECORD_KEY);
        $other = new CmsUser(); $other->forceFill(['id' => $user->id + 99999]);
        $this->assertFalse(app(Sessions::class)->revokeRecord($request, $other, $record));
        $this->assertNotNull(CmsSession::find($record));
        $this->assertTrue(app(Sessions::class)->revokeRecord($request, $user, $record));
        $this->assertNull(CmsSession::find($record));
    }

    public function test_absolute_expiry_db_expiry_and_credential_change_reject_replay(): void
    {
        $user = $this->admin(); $request = $this->device($user, false, '/cms/admin'); $service = app(Sessions::class);
        $request->session()->put(Sessions::AUTHENTICATED_AT_KEY, now()->subMinutes(721)->timestamp);
        $this->assertFalse($service->valid($request, $user));
        $request->session()->put(Sessions::AUTHENTICATED_AT_KEY, now()->timestamp);
        CmsSession::whereKey($request->session()->get(Sessions::SESSION_RECORD_KEY))->update(['expires_at' => now()->subMinute()]);
        $this->assertFalse($service->valid($request, $user));
        CmsSession::whereKey($request->session()->get(Sessions::SESSION_RECORD_KEY))->update(['expires_at' => now()->addHour()]);
        $changed = clone $user; $changed->role = 'customer';
        $this->assertFalse($service->valid($request, $changed));
    }

    public function test_wrong_password_has_no_mutation_or_audit_side_effect(): void
    {
        $user = $this->admin(); $request = $this->device($user);
        $before = $user->password; $events = DB::table('cms_sensitive_mutation_events')->count();
        $request->merge(['currentPassword' => 'incorrect-current', 'new_password' => 'New-Acceptance-42!', 'password_confirm' => 'New-Acceptance-42!']);
        try { (new ProfileController())->update($request); $this->fail('Invalid current password accepted'); }
        catch (ValidationException) { $this->assertSame($before, $user->fresh()->password); }
        $this->assertSame($events, DB::table('cms_sensitive_mutation_events')->count());
    }

    public function test_password_change_hash_audit_rotation_and_other_device_revocation_are_real(): void
    {
        $credential = json_decode(file_get_contents(getenv('TEST_ADMIN_CREDENTIALS_FILE')), true);
        $user = $this->admin(); $a = $this->device($user); $b = $this->device($user->fresh());
        $oldSessionId = $a->session()->getId(); $other = $b->session()->get(Sessions::SESSION_RECORD_KEY);
        $this->app->instance('request', $a); auth('cms')->setUser($user->fresh());
        $a->merge(['currentPassword' => $credential['password'], 'new_password' => 'New-Acceptance-42!', 'password_confirm' => 'New-Acceptance-42!']);
        $response = (new ProfileController())->update($a);
        $this->assertSame(200, $response->status());
        $this->assertTrue(Hash::check('New-Acceptance-42!', $user->fresh()->password));
        $this->assertNull($user->fresh()->password_raw);
        $this->assertNotSame($oldSessionId, $a->session()->getId());
        $this->assertNull(CmsSession::find($other));
        $this->assertTrue(DB::table('cms_session_history')->where('session_record_id', $other)->where('reason', 'password_changed')->exists());
        $this->assertTrue(DB::table('cms_sensitive_mutation_events')->where('source', 'cms_profile_password_change')->exists());
        $this->assertTrue(app(Sessions::class)->valid($a, $user->fresh()));
        $this->assertFalse(app(Sessions::class)->valid($b, $user->fresh()));
    }

    public function test_failed_sensitive_audit_rolls_back_password_and_sessions(): void
    {
        $credential = json_decode(file_get_contents(getenv('TEST_ADMIN_CREDENTIALS_FILE')), true);
        $user = $this->admin(); $request = $this->device($user); $before = $user->password; $records = CmsSession::count();
        $request->merge(['currentPassword' => $credential['password'], 'new_password' => 'New-Acceptance-42!', 'password_confirm' => 'New-Acceptance-42!']);
        CmsSensitiveMutationEvent::creating(fn () => throw new \RuntimeException('Injected local audit-storage failure'));
        try { (new ProfileController())->update($request); $this->fail('Missing audit accepted'); }
        catch (\RuntimeException $error) { $this->assertSame($before, $user->fresh()->password); $this->assertStringContainsString('audit', strtolower($error->getMessage())); }
        $this->assertSame($records, CmsSession::count());
    }

    public function test_failed_history_insert_rolls_back_password_and_audit(): void
    {
        $credential = json_decode(file_get_contents(getenv('TEST_ADMIN_CREDENTIALS_FILE')), true);
        $user = $this->admin(); $request = $this->device($user); $this->device($user->fresh());
        $this->app->instance('request', $request); auth('cms')->setUser($user->fresh());
        $before = $user->password; $records = CmsSession::count(); $events = DB::table('cms_sensitive_mutation_events')->count();
        DB::connection()->beforeExecuting(function ($query) { if (str_starts_with(strtolower($query), 'insert into `cms_session_history`')) throw new \RuntimeException('Injected local history-storage failure'); });
        $request->merge(['currentPassword' => $credential['password'], 'new_password' => 'New-Acceptance-42!', 'password_confirm' => 'New-Acceptance-42!']);
        try { (new ProfileController())->update($request); $this->fail('Missing history accepted'); }
        catch (\RuntimeException $error) { $this->assertSame($before, $user->fresh()->password); $this->assertStringContainsString('history', strtolower($error->getMessage())); }
        $this->assertSame($records, CmsSession::count());
        $this->assertSame($events, DB::table('cms_sensitive_mutation_events')->count());
    }

    public function test_database_allowlist_and_compiled_view_guard_reject_wrong_targets(): void
    {
        config(['db_safety.allowed_test_databases' => ['unrelated_test']]);
        try { app(\App\Support\DatabaseSafety::class)->guardDestructiveCommand('migrate:fresh'); $this->fail('Wrong target accepted'); }
        catch (\RuntimeException) { $this->assertSame('rise_b1_test', DB::connection()->getDatabaseName()); }
        $this->expectException(\RuntimeException::class);
        app(\App\Support\ViewCachePathSafety::class)->assertSafe('view:clear', 'testing', sys_get_temp_dir(), storage_path());
    }
    public function test_external_http_is_denied_before_transport(): void
    {
        $this->assertTrue(\App\Support\LocalEgress::shouldBlock());
        try { \Illuminate\Support\Facades\Http::get('https://example.invalid/b1-no-egress'); $this->fail('Outbound transport was not blocked'); }
        catch (\RuntimeException $error) { $this->assertSame('External HTTP request blocked by local network safety policy.', $error->getMessage()); }
    }

    public function test_site_setting_downgrade_rejects_long_text_without_loss(): void
    {
        DB::table('site_settings')->insert(['key' => 'b1_long_text_fixture', 'value' => str_repeat('x', 256)]);
        $migration = require database_path('migrations/2026_04_02_170500_expand_site_settings_value_for_betting_sync_controls.php');
        if (!$migration instanceof \Illuminate\Database\Migrations\Migration) $migration = new \ExpandSiteSettingsValueForBettingSyncControls();
        try { $migration->down(); $this->fail('Unsafe text downgrade accepted'); }
        catch (\RuntimeException) { $this->assertSame(256, strlen(DB::table('site_settings')->where('key', 'b1_long_text_fixture')->value('value'))); }
    }
}
