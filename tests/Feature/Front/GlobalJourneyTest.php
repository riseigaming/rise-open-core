<?php
namespace Tests\Feature\Front;

use App\Models\{CmsGame,Provider,CmsMenu};
use App\Services\Cms\CmsNavigationService;
use App\Support\{B1ContentUrl,GlobalGuides};
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class GlobalJourneyTest extends TestCase
{
    use DatabaseTransactions;

    public function test_guides_have_real_local_consumers_and_unknown_guides_are_not_success(): void
    {
        foreach (['tr','en','es'] as $locale) {
            $this->withSession(['currentLocale'=>$locale]);
            $this->get('/cms/learn')->assertOk()->assertSee('data-global-learn',false);
            foreach (GlobalGuides::SLUGS as $slug) {
                $this->get('/cms/learn/'.$slug)->assertOk()->assertSee('data-guide="'.$slug.'"',false)->assertDontSee('journey.guides.');
            }
        }
        $this->get('/cms/learn/islem-gecmisi')->assertNotFound();
        $this->get('/cms/learn/unknown')->assertNotFound();
        $this->assertFalse(B1ContentUrl::allowed('/cms/learn/unknown'));
        $this->assertTrue(B1ContentUrl::allowed('/cms/learn/catalog-discovery'));
        $this->assertFalse(B1ContentUrl::allowed('/cms/front/support'));
    }

    public function test_saved_nested_localized_menus_remain_authoritative_on_desktop_and_mobile(): void
    {
        $item=['text_tr'=>'Birinci','text_en'=>'First','href_tr'=>'/cms/learn','href_en'=>'/cms/learn','target'=>'_self','children'=>[
            ['text_tr'=>'İkinci','text_en'=>'Second','href_tr'=>'/cms/information','href_en'=>'/cms/information','children'=>[
                ['text_tr'=>'Üçüncü','text_en'=>'Third','href_tr'=>'/cms/page/cookie','href_en'=>'/cms/page/cookie']
            ]]
        ]];
        CmsMenu::updateOrCreate(['name'=>'global_sidebar'],['json'=>json_encode([$item])]);
        $this->withSession(['currentLocale'=>'en']);
        $r=$this->get('/cms/learn')->assertOk()->assertSee('First')->assertSee('Second')->assertSee('Third');
        $this->assertGreaterThanOrEqual(2,substr_count($r->getContent(),'Third'));
        $this->assertSame('/cms/learn',app(CmsNavigationService::class)->sidebar([],'en')[0]['href']);
    }

    public function test_real_catalog_filter_detail_and_return_preserve_only_catalog_context(): void
    {
        $p=Provider::create(['name'=>'Journey provider','slug'=>'journey-provider','kind'=>'external','status'=>'active','show_left_panel'=>true]);
        $g=CmsGame::create(['provider_id'=>$p->id,'name'=>'Journey Slot','slug'=>'journey-slot','status'=>'active','game_type'=>'slot']);
        CmsGame::create(['provider_id'=>$p->id,'name'=>'Journey Table','slug'=>'journey-table','status'=>'active','game_type'=>'table']);
        $query=['q'=>'Journey','type'=>'slot','provider'=>$p->id,'page'=>1];
        $this->get('/casino?'.http_build_query($query))->assertOk()->assertSee('Journey Slot')->assertDontSee('Journey Table')->assertSee('catalog-providers');
        $html=$this->get('/cms/games/journey-slot?'.http_build_query($query+['return'=>'https://example.invalid/steal']))->assertOk()->getContent();
        preg_match('/data-catalog-return href="([^"]+)"/',$html,$match);
        $target=html_entity_decode($match[1]); parse_str(parse_url($target,PHP_URL_QUERY),$actual);
        $this->assertSame('/casino',parse_url($target,PHP_URL_PATH));
        $this->assertEquals($query,$actual);
        $this->assertStringNotContainsString('https://example.invalid/steal',$html);
        $g->update(['status'=>'passive']);$this->get('/cms/games/journey-slot')->assertNotFound();
    }

    public function test_guest_navigation_does_not_claim_protected_account_or_unimplemented_features(): void
    {
        $items=app(CmsNavigationService::class)->sidebar([],'en');
        $this->assertNotContains('authenticated',array_column($items,'visibility'));
        $serialized=json_encode($items,JSON_UNESCAPED_SLASHES);
        $this->assertStringContainsString('/cms/sportsbook',$serialized);
        $this->assertTrue(\App\Support\B1ContentUrl::allowed('/cms/sportsbook'));
        $this->assertFalse(\App\Support\B1ContentUrl::allowed('/cms/api/betting/acceptances'));
        $this->assertFalse(\App\Support\B1ContentUrl::allowed('/cms/admin/sportsbook'));
        foreach(['eg_flag','eg_favorites','eg_recent','chess','support','finance'] as $missing) $this->assertStringNotContainsString($missing,$serialized);
        $this->get('/cms/sessions')->assertRedirect();
    }

    public function test_information_pages_reveal_draft_status_and_link_each_other(): void
    {
        foreach(array_keys(\App\Support\WinxInformationPages::PAGES) as $page) {
            $this->get('/cms/page/'.$page)->assertOk()->assertSee('journeyNotice',false)
                ->assertSee('/cms/page/cookie',false)->assertSee('/cms/learn/promotions-and-information',false);
        }
    }
}
