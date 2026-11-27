<?php

namespace Tests\Feature\Front;

use App\Models\CmsLanguage;
use App\Models\CmsUser;
use App\Services\Geo\GeoIpResolution;
use App\Services\Geo\LocalVpnNetworkRegistry;
use App\Services\Geo\RequestGeoIpResolver;
use App\Services\Localization\CmsLanguageMutationService;
use App\Services\Restrictions\CentralRestrictionPolicyService as Policy;
use App\Services\Restrictions\ProductCountryRestrictionGuard;
use App\SiteSettings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

final class B1PolicyAndMailTest extends TestCase
{
    use DatabaseTransactions;

    private function restriction(string $vpn = 'block'): int
    {
        $country = (int) DB::table('cms_countries')->value('id');
        SiteSettings::updateOrCreate(['key' => Policy::SETTINGS_KEY], ['value' => json_encode(['version' => 1, 'vpn_policy' => $vpn,
            'countries' => [(string) $country => ['products' => ['site']]]])]);
        return $country;
    }

    private function geo(?string $country, bool $vpn = false): void
    {
        $fake = \Mockery::mock(RequestGeoIpResolver::class);
        $fake->shouldReceive('resolve')->andReturn(new GeoIpResolution('192.0.2.4', $country, $vpn, $country === null, false, 'explicit_local_fixture', 'v1'));
        $this->app->instance(RequestGeoIpResolver::class, $fake);
    }

    public function test_geo_451_real_html_json_language_and_vpn_policies(): void
    {
        $this->restriction(); $this->geo('TR');
        $this->get('/')->assertStatus(451)->assertHeader('Content-Language')->assertHeader('Cache-Control', 'no-store, private')
            ->assertDontSee('winx-logo')->assertDontSee('supportWidgetFrame')->assertDontSee('gelirsetheme/');
        $this->getJson('/')->assertStatus(451)->assertJsonPath('reason_code', 'geo_restricted');
        $this->restriction('allow'); $this->geo('TR', true);
        $this->get('/')->assertOk();
        $this->restriction('block'); $this->geo('TR', true);
        $this->getJson('/')->assertStatus(451)->assertJsonPath('reason_code', 'vpn_proxy_blocked');
    }

    public function test_unknown_country_and_policy_outage_preserve_source_allow_semantics(): void
    {
        $this->restriction(); $this->geo(null);
        $this->get('/')->assertOk();
        $fake = \Mockery::mock(Policy::class); $fake->shouldReceive('decideCountryRestriction')->andThrow(new \RuntimeException('Explicit local policy outage'));
        $this->app->instance(Policy::class, $fake);
        $decision = app(ProductCountryRestrictionGuard::class)->decide(Request::create('/'), 'site');
        $this->assertTrue($decision->allowed);
        $this->assertSame('restriction_service_unavailable', $decision->reasonCode);
    }

    public function test_local_registry_ip_cidr_disabled_invalid_and_asn_nonmatch(): void
    {
        config(['cache.default' => 'file', 'cache.stores.file.path'=>storage_path('framework/testing/geo-cache')]);
        $entries = [
            ['id' => '00000000-0000-4000-8000-000000000001', 'type' => 'ip', 'value' => '192.0.2.11', 'label' => 'Local exact fixture', 'active' => true],
            ['id' => '00000000-0000-4000-8000-000000000002', 'type' => 'cidr', 'value' => '198.51.100.0/24', 'label' => 'Local CIDR fixture', 'active' => true],
            ['id' => '00000000-0000-4000-8000-000000000003', 'type' => 'ip', 'value' => '203.0.113.12', 'label' => 'Disabled fixture', 'active' => false],
            ['id' => '00000000-0000-4000-8000-000000000004', 'type' => 'asn', 'value' => 'AS64500', 'label' => 'Unmatched ASN fixture', 'active' => true],
            ['id' => '00000000-0000-4000-8000-000000000005', 'type' => 'cidr', 'value' => 'garbage', 'label' => 'Invalid fixture', 'active' => true],
        ];
        SiteSettings::updateOrCreate(['key' => LocalVpnNetworkRegistry::SETTINGS_KEY], ['value' => json_encode(['version' => 1, 'entries' => $entries])]);
        $registry = app(LocalVpnNetworkRegistry::class);
        $this->assertSame('ip', $registry->match('192.0.2.11')['type']);
        $this->assertSame('cidr', $registry->match('198.51.100.99')['type']);
        $this->assertNull($registry->match('203.0.113.12'));
        $this->assertNull($registry->match('203.0.113.13'));
        $this->assertNull($registry->match('not-an-ip'));
        $files=\Illuminate\Support\Facades\File::allFiles(storage_path('framework/testing/geo-cache'));
        $this->assertNotEmpty($files);
    }

    public function test_language_default_invariant_and_safe_redirects(): void
    {
        $service = app(CmsLanguageMutationService::class);
        $language = $service->save(['code' => 'de', 'name' => 'Deutsch', 'status' => 1, 'is_default' => 0]);
        $this->assertNotNull($language->id);
        $service->save(['code' => 'de', 'name' => 'Deutsch', 'status' => 1, 'is_default' => 1], $language->id);
        $this->assertSame(1, CmsLanguage::where('status', 1)->where('is_default', 1)->count());
        $this->assertSame('de', CmsLanguage::where('is_default', 1)->value('code'));
        $response = $this->get('/cms/setLanguage/en?redirect=https://evil.invalid/');
        $this->assertFalse(str_contains((string) $response->headers->get('Location'), 'evil.invalid'));
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->bulk(CmsLanguage::pluck('id')->all(), 'passive');
    }

    public function test_six_information_pages_render_primary_locales_without_provider_art_or_routes(): void
    {
        foreach (['tr','en','es'] as $locale) {
            foreach (['terms-conditions','privacy-policy','betting-rules','responsible-gaming','cookie','provably-fair'] as $page) {
                $response = $this->get('/cms/page/'.$page.'?lang='.$locale);
                $this->assertSame(200, $response->status(), $page.' '.$locale);
                $this->assertFalse(str_contains($response->getContent(), 'gelirseimages/fair-play.jpg'));
                $this->assertFalse(str_contains($response->getContent(), '/cms/support'));
                $this->assertFalse(str_contains($response->getContent(), 'Fly Away'));
            }
        }
        $this->get('/cms/page/not-an-information-page')->assertNotFound();
    }

    public function test_verification_and_reset_use_real_local_log_transport_and_single_use_tokens(): void
    {
        $user = CmsUser::where('role', 'admin')->firstOrFail();
        $messages = [];
        Event::listen(MessageSent::class, function ($event) use (&$messages) { $messages[] = $event->message->getHtmlBody(); });
        $this->assertTrue(app(\App\Services\Cms\CmsActivationMailService::class)->send($user));
        $this->assertNotEmpty($messages);
        $this->assertFalse(str_contains(end($messages), 'winx-logo'));
        $token = $user->fresh()->email_verify_hash;
        $this->assertTrue(is_string($token) && strlen($token) > 20);
        $this->get('/verify-email?verifyToken='.urlencode($token))->assertRedirect();
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertNull($user->fresh()->email_verify_hash);
        $this->get('/verify-email?verifyToken='.urlencode($token))->assertOk();
        $known = $this->post('/cms/password/forgot', ['email' => $user->email]);
        $unknown = $this->post('/cms/password/forgot', ['email' => 'unregistered@example.invalid']);
        $this->assertSame($known->status(), $unknown->status());
        $this->assertGreaterThanOrEqual(2, count($messages));
        $this->assertTrue(str_contains(end($messages), '/cms/password/reset/'));
        $resetToken = Password::broker('cms_users')->createToken($user->fresh());
        $data = ['email' => $user->email, 'token' => $resetToken, 'password' => 'Reset-Acceptance-73!', 'password_confirmation' => 'Reset-Acceptance-73!'];
        $this->post('/cms/password/reset', $data)->assertRedirect('/cms/login');
        $this->assertTrue(Hash::check($data['password'], $user->fresh()->password));
        $this->assertFalse(Password::broker('cms_users')->tokenExists($user->fresh(), $resetToken));
        $hash = $user->fresh()->password;
        $this->post('/cms/password/reset', array_merge($data, ['password' => 'Replay-Acceptance-74!', 'password_confirmation' => 'Replay-Acceptance-74!']))->assertSessionHasErrors();
        $this->assertTrue(hash_equals($hash, $user->fresh()->password));
        $this->assertSame('log', config('mail.default'));
        $this->assertSame('account_mail', config('mail.mailers.log.channel'));
    }
    public function test_missing_editorial_copy_is_503_without_dummy_content(): void
    {
        $resolver=\Mockery::mock(\App\Services\Localization\LocalizedCopyResolver::class);
        $resolver->shouldReceive('resolve')->andReturn(['found'=>false,'copy'=>null]);
        $this->app->instance(\App\Services\Localization\LocalizedCopyResolver::class,$resolver);
        $this->get('/cms/page/terms-conditions')->assertStatus(503);
        $this->get('/cms/information')->assertStatus(503);
    }
}
