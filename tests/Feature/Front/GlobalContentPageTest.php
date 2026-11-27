<?php

namespace Tests\Feature\Front;

use App\Models\CmsContentPage;
use App\Models\CmsThemeSetting;
use App\Models\CmsUser;
use App\Services\Security\CmsAuthenticatedSessionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class GlobalContentPageTest extends TestCase
{
    use DatabaseTransactions;

    private const ADMIN = '/cms/admin/content-pages';
    private const PUBLIC_PAGE = '/cms/page/run29-guide';

    private function loginAdmin(): void
    {
        $credentials = json_decode(file_get_contents(getenv('TEST_ADMIN_CREDENTIALS_FILE')), true, flags: JSON_THROW_ON_ERROR);
        $this->postJson('/cms/login', ['email' => $credentials['username'], 'password' => $credentials['password']], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertJsonPath('status', 200);
        $this->withCookie(config('session.cookie'), session()->getId());
    }

    private function input(bool $published = false): array
    {
        return [
            'slug' => 'run29-guide',
            'published' => (int) $published,
            'translations' => [
                'tr' => ['title' => 'İlk adımlar', 'body' => '<p>Türkçe <strong>içerik</strong></p><script>alert(99)</script>'],
                'en' => ['title' => 'First steps', 'body' => '<p>English content <a href="javascript:alert(99)" onclick="alert(99)">Bad link</a></p>'],
                'es' => ['title' => 'Primeros pasos', 'body' => '<p>Contenido español</p>'],
            ],
        ];
    }

    private function menu(): array
    {
        return ['slot' => 'sidebar', 'menu' => json_encode([[
            'id' => 'run29-page', 'text' => 'Run29 menu label', 'href' => self::PUBLIC_PAGE, 'target' => '_self',
        ]], JSON_THROW_ON_ERROR)];
    }

    public function test_draft_publish_update_and_unpublish_share_the_public_and_menu_rule(): void
    {
        $this->loginAdmin();
        $this->get(self::ADMIN)->assertOk()->assertSee('data-content-page-form', false);
        $this->post(self::ADMIN, $this->input())->assertRedirect();
        $page = CmsContentPage::where('slug', 'run29-guide')->sole();
        $this->get(self::ADMIN.'/'.$page->id.'/edit')->assertOk()->assertSee('İlk adımlar');
        $this->get(self::PUBLIC_PAGE)->assertNotFound();
        $this->postJson('/cms/admin/menus/update', $this->menu())->assertUnprocessable();

        $this->put(self::ADMIN.'/'.$page->id, $this->input(true))->assertRedirect();
        $this->postJson('/cms/admin/menus/update', $this->menu())->assertOk();
        foreach (['tr' => 'İlk adımlar', 'en' => 'First steps', 'es' => 'Primeros pasos'] as $locale => $title) {
            $this->get(self::PUBLIC_PAGE.'?lang='.$locale)->assertOk()->assertSee($title)
                ->assertSee('href="'.self::PUBLIC_PAGE.'"', false)->assertDontSee('alert(99)', false);
        }
        $this->assertStringContainsString('<strong>', $page->fresh()->translations['tr']['body']);
        $this->assertStringNotContainsString('javascript:', $page->fresh()->translations['en']['body']);

        $edited = $this->input(true);
        $edited['translations']['es']['title'] = 'Guía actualizada';
        $this->put(self::ADMIN.'/'.$page->id, $edited)->assertRedirect();
        $this->get(self::PUBLIC_PAGE.'?lang=es')->assertOk()->assertSee('Guía actualizada');

        $this->put(self::ADMIN.'/'.$page->id, $this->input())->assertRedirect();
        $this->get(self::PUBLIC_PAGE)->assertNotFound();
        $this->get('/')->assertOk()->assertDontSee('href="'.self::PUBLIC_PAGE.'"', false);
        $items = json_decode($this->menu()['menu'], true, flags: JSON_THROW_ON_ERROR);
        $items[] = ['id' => 'run29-other', 'text' => 'Other link', 'href' => '/'];
        $this->postJson('/cms/admin/menus/update', ['slot' => 'sidebar', 'menu' => json_encode($items)])->assertOk();
        $this->get('/')->assertOk()->assertSee('Other link')->assertDontSee('href="'.self::PUBLIC_PAGE.'"', false);
        $items[1]['href'] = self::PUBLIC_PAGE;
        $this->postJson('/cms/admin/menus/update', ['slot' => 'sidebar', 'menu' => json_encode($items)])->assertUnprocessable();
        $this->assertDatabaseHas('cms_menus', ['name' => 'global_sidebar']);
        $this->get('/cms/page/terms-conditions')->assertOk();
    }

    public function test_publication_requires_readable_content_in_all_three_languages_and_unique_slug(): void
    {
        $this->loginAdmin();
        $data = $this->input(true);
        $data['translations']['es']['body'] = '<script>hello</script><p>&nbsp;</p>';
        $this->postJson(self::ADMIN, $data)->assertUnprocessable()->assertJsonValidationErrors('translations.es.body');
        $this->assertSame(0, CmsContentPage::count());

        $this->post(self::ADMIN, $this->input())->assertRedirect();
        $this->postJson(self::ADMIN, $this->input())->assertUnprocessable()->assertJsonValidationErrors('slug');
        $this->postJson(self::ADMIN, array_replace($this->input(), ['slug' => 'terms-conditions']))
            ->assertUnprocessable()->assertJsonValidationErrors('slug');
        $this->assertSame(1, CmsContentPage::count());
    }

    public function test_guest_customer_and_stale_admin_cannot_write_pages(): void
    {
        $this->postJson(self::ADMIN, $this->input(true))->assertRedirect();
        $this->loginAdmin();
        $this->withSession([CmsAuthenticatedSessionService::RECENT_AUTH_AT_KEY => now()->subHours(1)->timestamp]);
        $this->postJson(self::ADMIN, $this->input(true))->assertStatus(423);

        $admin = CmsUser::where('role', 'admin')->firstOrFail();
        $admin->update(['role' => 'customer']);
        auth('cms')->forgetUser();
        $this->loginAdmin();
        $this->postJson(self::ADMIN, $this->input(true))->assertForbidden();
        $this->assertSame(0, CmsContentPage::count());
    }

    public function test_accent_is_allowlisted_persisted_and_rendered_by_global(): void
    {
        $this->loginAdmin();
        $endpoint = '/cms/admin/settings/updateSiteSettings';
        $this->postJson($endpoint, ['accent_preset' => 'blue'])->assertOk();
        $this->assertSame('blue', CmsThemeSetting::where('theme_key', 'global')->value('accent_preset'));
        $this->get('/')->assertOk()->assertSee('data-global-accent="blue"', false);
        $this->postJson($endpoint, ['accent_preset' => 'red; color: red'])->assertUnprocessable();
        $this->assertSame('blue', CmsThemeSetting::where('theme_key', 'global')->value('accent_preset'));
        $this->postJson($endpoint, ['accent_preset' => 'default'])->assertOk();
        $this->get('/')->assertOk()->assertSee('data-global-accent="default"', false);
    }
}
