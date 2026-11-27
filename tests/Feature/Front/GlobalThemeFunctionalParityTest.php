<?php

namespace Tests\Feature\Front;

use App\Models\CmsLanguage;
use App\Models\CmsPromotion;
use App\Models\CmsSession;
use App\Models\CmsSiteSetting;
use App\Models\CmsUser;
use App\Services\Security\CmsAuthenticatedSessionService;
use App\Services\Security\CmsPasswordIntegrityService;
use App\Services\Security\RegistrationTurnstileVerifier;
use App\Support\B1ContentUrl;
use App\Support\CmsThemeResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Real selected MySQL schema. CSRF/browser claims are covered by the separate HTTP harness. */
final class GlobalThemeFunctionalParityTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        // Test-only cache isolation; the real HTTP/browser harness retains file cache/session.
        config(['cache.default' => 'array']);
    }

    private function login(): CmsUser
    {
        $credential = json_decode(file_get_contents((getenv('TEST_ADMIN_CREDENTIALS_FILE') ?: throw new \RuntimeException('TEST_ADMIN_CREDENTIALS_FILE is required.'))), true);
        $this->postJson('/cms/login', ['email' => $credential['username'], 'password' => $credential['password']], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertJsonPath('status', 200);
        $this->withCookie(config('session.cookie'), session()->getId());
        return CmsUser::where('username', $credential['username'])->firstOrFail();
    }

    private function registration(array $overrides = []): array
    {
        return array_merge(['username' => 'b1_customer', 'password' => 'Accept-Local-927!', 'passwordConfirm' => 'Accept-Local-927!',
            'countryId' => DB::table('cms_countries')->value('id'), 'currencyId' => 'TRY', 'accept_terms' => '1'], $overrides);
    }

    public function test_selected_global_and_admin_entrypoints_resolve_without_private_products(): void
    {
        $this->get('/')->assertOk()->assertSee('/casino')->assertDontSee('/cms/financial')->assertDontSee('data-homepage-lazy-url');
        $this->get('/cms/promotions')->assertOk();
        $this->get('/cms/information')->assertOk();
        $this->login();
        foreach (['/cms/admin', '/cms/admin/menus/edit', '/cms/admin/settings/site-settings', '/cms/admin/languages',
            '/cms/admin/sliders', '/cms/admin/sliders/create', '/cms/admin/sliders/archive', '/cms/admin/promotions',
            '/cms/admin/promotions/create', '/cms/sessions', '/cms/edit-password'] as $path) $this->assertSame(200, $this->followingRedirects()->get($path)->status(), $path);
    }

    public function test_bootstrap_cannot_be_repeated(): void
    {
        $before = CmsUser::count();
        $this->artisan('app:admin-bootstrap', ['--no-interaction' => true])->assertExitCode(1);
        $this->assertSame($before, CmsUser::count());

    }

    public function test_real_schema_fk_queue_and_normal_type(): void
    {
        $this->assertSame('rise_b1_test', DB::selectOne('SELECT DATABASE() AS name')->name);
        $this->assertSame(72, DB::table('migrations')->count());
        $this->assertSame('database', config('queue.default'));
        $this->assertSame('jobs_table', config('queue.connections.database.table'));
        $this->assertSame('database', config('queue.failed.driver'));
        $this->assertFalse(Schema::hasTable('users'));
        $this->assertFalse(Schema::hasTable('promo_links'));
        $this->assertGreaterThan(0, \App\Models\CmsUserType::defaultTypeId());
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('cms_users')->where('role', 'admin')->update(['country_id' => 2147483647]);
    }

    public function test_unsupported_registration_fields_reject_without_mutation(): void
    {
        $before = CmsUser::count();
        foreach (['partnerPromoCode', 'promo_code', 'inviteToken', 'role', 'balance'] as $field) {
            $this->postJson('/cms/register', $this->registration([$field => 'unsupported']), ['X-Requested-With' => 'XMLHttpRequest'])->assertStatus(422);
        }
        $this->assertSame($before, CmsUser::count());
    }

    public function test_registration_gate_terms_country_fiat_and_duplicate_identity(): void
    {
        $this->postJson('/cms/register', $this->registration(), ['X-Requested-With' => 'XMLHttpRequest'])->assertJsonPath('code', 'registration_closed');
        CmsSiteSetting::query()->update(['register_status' => 1]);
        $before = CmsUser::count();
        $fixtureIp = 1;
        foreach ([['accept_terms' => 0], ['password' => 'short'], ['currencyId' => 'BTC'], ['username' => 'a b']] as $fields) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.($fixtureIp++)])->postJson('/cms/register', $this->registration($fields), ['X-Requested-With' => 'XMLHttpRequest'])->assertJsonPath('status', 400);
        }
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.20'])->postJson('/cms/register', $this->registration(['countryId' => 2147483647]), ['X-Requested-With' => 'XMLHttpRequest'])->assertStatus(422);
        $this->assertSame($before, CmsUser::count());
        $this->postJson('/cms/register', $this->registration(), ['X-Requested-With' => 'XMLHttpRequest'])->assertJsonPath('status', 200);
        $root = CmsUser::where('username', 'b1_customer')->firstOrFail();
        $this->assertNull($root->password_raw);
        $this->assertSame('customer', $root->role);
        $child = $root->subAccounts()->sole();
        $this->assertSame('TRY', $child->currency);
        $this->assertSame($root->country_id, $child->country_id);
        $this->assertTrue(Hash::check('Accept-Local-927!', $root->password));
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->get('/cms/admin')->assertForbidden();
        $this->get('/cms/logout');
        $this->postJson('/cms/register', $this->registration(['username' => 'B1_CUSTOMER']), ['X-Requested-With' => 'XMLHttpRequest'])->assertJsonPath('status', 400);
        $this->assertSame($before + 2, CmsUser::count());
    }

    public function test_email_identifier_registration_normalizes_and_rejects_duplicate_and_malformed_email(): void
    {
        CmsSiteSetting::query()->update(['register_status'=>1]);$before=CmsUser::count();
        $this->postJson('/cms/register',$this->registration(['username'=>'B1.Email.Customer@Example.Invalid']),['X-Requested-With'=>'XMLHttpRequest'])->assertJsonPath('status',200);
        $root=CmsUser::where('email','b1.email.customer@example.invalid')->whereNull('parent_id')->firstOrFail();
        $this->assertNull($root->password_raw);$this->assertSame(1,$root->subAccounts()->count());
        $this->get('/cms/logout');
        foreach(['b1.email.customer@example.invalid','bad@@example.invalid'] as $identifier)
            $this->postJson('/cms/register',$this->registration(['username'=>$identifier]),['X-Requested-With'=>'XMLHttpRequest'])->assertJsonPath('status',400);
        $this->assertSame($before+2,CmsUser::count());
    }

    public function test_phone_identity_and_tombstoned_collision(): void
    {
        CmsSiteSetting::query()->update(['register_status' => 1]);
        $data = $this->registration(['phone' => '+90 532 123 45 67']);
        $this->postJson('/cms/register', $data, ['X-Requested-With' => 'XMLHttpRequest'])->assertJsonPath('status', 200);
        $root = CmsUser::where('username', 'b1_customer')->firstOrFail();
        $this->assertSame('+905321234567', $root->phone_normalized);
        $this->get('/cms/logout');
        $root->delete();
        $before = CmsUser::withTrashed()->count();
        $this->postJson('/cms/register', $this->registration(['username' => 'other_customer', 'phone' => '05321234567']), ['X-Requested-With' => 'XMLHttpRequest'])->assertStatus(422);
        $this->postJson('/cms/register', $this->registration(), ['X-Requested-With' => 'XMLHttpRequest'])->assertJsonPath('status', 400);
        $this->assertSame($before, CmsUser::withTrashed()->count());
    }

    public function test_child_create_failure_rolls_back_root_and_mutation_audit(): void
    {
        CmsSiteSetting::query()->update(['register_status' => 1]);
        $before = CmsUser::count(); $audit = DB::table('cms_sensitive_mutation_events')->count();
        CmsUser::creating(function (CmsUser $user) { if ($user->parent_id) throw new \RuntimeException('Disposable injected child failure'); });
        $this->postJson('/cms/register', $this->registration(), ['X-Requested-With' => 'XMLHttpRequest'])->assertJsonPath('status', 500);
        $this->assertSame($before, CmsUser::count());
        $this->assertSame($audit, DB::table('cms_sensitive_mutation_events')->count());
    }

    public function test_menu_roundtrip_visibility_and_url_depth_negative_cases(): void
    {
        $this->login();
        $menu = [['id' => 'generic', 'text_tr' => 'B1 menu acceptance', 'href_tr' => '/cms/promotions', 'text_en' => 'B1 English menu', 'href_en' => '/cms/information', 'children' => []]];
        $this->postJson('/cms/admin/menus/update', ['slot' => 'sidebar', 'menu' => json_encode($menu)])->assertOk()->assertJsonPath('status', 200);
        $this->get('/')->assertSee('B1 English menu');
        $this->get('/?lang=tr')->assertSee('B1 menu acceptance');
        $this->assertSame('global_sidebar', DB::table('cms_menus')->where('name', 'global_sidebar')->value('name'));
        foreach (['javascript:alert(1)', '//example.invalid', '/sportsbook', '/cms/support', 'https://example.invalid/', '/cms/page/../../admin'] as $bad) {
            $menu[0]['href_tr'] = $bad;
            $this->postJson('/cms/admin/menus/update', ['slot' => 'sidebar', 'menu' => json_encode($menu)])->assertStatus(422);
        }
        $this->postJson('/cms/admin/menus/update', ['slot' => 'winx_sidebar', 'menu' => '[]'])->assertStatus(422);
        foreach (['{broken', '{}', json_encode([['text_tr'=>'Bad','href_tr'=>'/','visibility'=>'staff']])] as $payload)
            $this->postJson('/cms/admin/menus/update', ['slot'=>'sidebar','menu'=>$payload])->assertStatus(422);
        $leaf=['id'=>'child','text_tr'=>'Nested','href_tr'=>'/','children'=>[]];
        $parent=['id'=>'parent','text_tr'=>'Parent','href_tr'=>'/','children'=>[$leaf]];
        $this->postJson('/cms/admin/menus/update',['slot'=>'sidebar','menu'=>json_encode([array_merge($leaf,['id'=>'root','children'=>[$parent]])])])->assertStatus(422);
        $this->postJson('/cms/admin/menus/update',['slot'=>'sidebar','menu'=>json_encode([$leaf,$leaf])])->assertStatus(422);

    }

    public function test_settings_real_translation_and_reject_excluded_fields(): void
    {
        $this->login();
        $english = CmsLanguage::where('code', 'en')->value('id');
        $turkish = CmsLanguage::where('code', 'tr')->value('id');
        $this->postJson('/cms/admin/settings/updateSiteSettings', ['active_theme' => 'global', 'langContent' => [
            ['cms_language_id' => $english, 'site_title' => 'B1 English title', 'site_description' => 'English description.'],
            ['cms_language_id' => $turkish, 'site_title' => 'B1 Turkish title', 'site_description' => 'Turkish description.'],
        ]])->assertOk()->assertJsonPath('status', 200);
        $this->get('/')->assertSee('B1 English title')->assertSee('English description.')->assertDontSee('B1 Turkish title');
        $this->get('/?lang=tr')->assertSee('B1 Turkish title')->assertSee('Turkish description.')->assertDontSee('B1 English title');
        foreach (['chat_status', 'support_customer_ai_replies_status', 'splash_status', 'balance'] as $field) {
            $this->postJson('/cms/admin/settings/updateSiteSettings', [$field => 'active'])->assertStatus(422);
        }
        $this->postJson('/cms/admin/settings/updateSiteSettings', ['active_theme' => 'winx'])->assertStatus(422);
    }

    public function test_generic_timed_promotion_save_read_render_and_empty_cta(): void
    {
        $this->login(); $id = CmsLanguage::where('code', 'tr')->value('id');
        $this->postJson('/cms/admin/promotions/store', ['status' => 'active', 'is_permanent' => 0, 'show_on_homepage_top' => 1,
            'end_date' => now()->addDays(3)->format('Y-m-d H:i:s'), 'redirect_url' => '',
            'langContent' => [['cms_language_id' => $id, 'listing_title' => 'B1 Generic Promotion', 'detail_title' => 'B1 Details', 'description' => 'Saved generic text']]])
            ->assertOk()->assertJsonPath('status', 200);
        $promotion = CmsPromotion::latest('id')->firstOrFail();
        $this->assertNotNull($promotion->end_date);
        $this->get('/cms/promotions?lang=tr')->assertOk()->assertSee('B1 Generic Promotion')->assertSee('/casino');
        $this->get('/cms/promotions/detail/'.$promotion->id.'?lang=tr')->assertOk()->assertSee('Saved generic text')->assertDontSee('class="promotionDetailPrimaryButton"', false);
    }

    public function test_turnstile_all_branches_use_only_fake_transport(): void
    {
        config(['registration_security.turnstile.enabled' => true, 'registration_security.turnstile.secret_key' => 'fixture-only',
            'registration_security.turnstile.expected_action' => 'register', 'registration_security.turnstile.expected_hostname' => 'localhost']);
        $verifier = app(RegistrationTurnstileVerifier::class);
        $request = \Illuminate\Http\Request::create('/cms/register', 'POST', ['cf-turnstile-response' => 'fixture-token']);
        $this->assertFalse($verifier->passes(\Illuminate\Http\Request::create('/cms/register', 'POST')));
        config(['registration_security.turnstile.secret_key'=>'']);$this->assertFalse($verifier->passes($request));
        config(['registration_security.turnstile.secret_key'=>'fixture-only']);
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake(['*' => Http::response(['success' => true, 'action' => 'register', 'hostname' => 'localhost'])]);
        $this->assertTrue($verifier->passes($request));
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake(['*' => Http::response(['success' => true, 'action' => 'wrong', 'hostname' => 'localhost'])]);
        $this->assertFalse($verifier->passes($request));
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake(['*' => Http::response(['success' => true, 'action' => 'register', 'hostname' => 'evil.invalid'])]);
        $this->assertFalse($verifier->passes($request));
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('fixture timeout'));
        $this->assertFalse($verifier->passes($request));
        config(['registration_security.turnstile.enabled' => false]); $this->assertTrue($verifier->passes($request));
    }

    public function test_unknown_theme_missing_view_and_content_destination_fail_closed(): void
    {
        $this->assertSame(['global'], CmsThemeResolver::supportedThemeKeys());
        $this->assertSame([], CmsThemeResolver::missingRequiredViews('global'));
        foreach (['javascript:alert(1)', '//evil.invalid', '/sportsbook', 'http://evil.invalid', '/cms/page/not-a-policy'] as $url) $this->assertFalse(B1ContentUrl::allowed($url));
        $this->expectException(\InvalidArgumentException::class); CmsThemeResolver::physicalNamespace('winx');
    }
    public function test_editorial_html_preserves_formatting_and_rejects_executable_markup(): void
    {
        $safe = \App\Support\CmsContentHtml::render('<p onclick="bad()">Text <strong>bold</strong><script>alert(1)</script><a href="javascript:alert(1)">unsafe link</a></p>');
        $this->assertStringContainsString('<strong>bold</strong>', $safe);
        $this->assertStringNotContainsString('onclick', $safe);
        $this->assertStringNotContainsString('<script', $safe);
        $this->assertStringNotContainsString('javascript:', $safe);
        $this->assertStringContainsString('unsafe link', $safe);
    }
}
