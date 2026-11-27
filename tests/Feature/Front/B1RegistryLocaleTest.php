<?php
namespace Tests\Feature\Front;
use App\Models\{CmsLanguage,CmsUser};
use App\Http\Middleware\{CmsLanguageMiddleware,TrustProxies,FrameAncestorsPolicy};
use App\Services\Localization\{CountryDefaultLanguageService,CmsLanguageMutationService};
use App\Services\Geo\GeoIpResolution;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Artisan,Event};
use Tests\TestCase;

final class B1RegistryLocaleTest extends TestCase
{
    use DatabaseTransactions;
    private function login(): void
    {
        $c=json_decode(file_get_contents(getenv('TEST_ADMIN_CREDENTIALS_FILE')),true);
        $this->postJson('/cms/login',['email'=>$c['username'],'password'=>$c['password']],['X-Requested-With'=>'XMLHttpRequest'])->assertJsonPath('status',200);
        $this->withCookie(config('session.cookie'),session()->getId());
    }
    private function locale(array $query=[],array $cookies=[],array $server=[],?CmsUser $user=null): string
    {
        session()->flush();
        $r=Request::create('http://'.($server['HTTP_HOST']??'127.0.0.1').'/','GET',$query,$cookies,[],array_merge(['HTTP_ACCEPT_LANGUAGE'=>''],$server));
        $r->setLaravelSession(session()->driver());$r->setUserResolver(fn()=>$user);
        app(CmsLanguageMiddleware::class)->resolveLocale($r,new GeoIpResolution('192.0.2.8',(string)DB::table('cms_countries')->value('iso2'),false,false,false,'explicit_local_fixture','v1'));
        return app()->getLocale();
    }
    public function test_locale_precedence_country_mapping_and_invalid_inactive_fallback(): void
    {
        $mapping=app(CountryDefaultLanguageService::class);$id=(int)DB::table('cms_countries')->value('id');
        $countryCode=(string)DB::table('cms_countries')->where('id',$id)->value('iso2');
        $mapping->setCountryDefault($id,'es');
        $this->assertSame('es',$mapping->languageForObservedCountry($countryCode)->code);
        $this->assertSame('es',$this->locale());
        $this->assertSame('en',$this->locale([],[],['HTTP_ACCEPT_LANGUAGE'=>'en-US,en;q=0.9']));
        $this->assertSame('tr',$this->locale(['lang'=>'tr'],[],['HTTP_ACCEPT_LANGUAGE'=>'en']));
        $this->assertSame('en',$this->locale(['lang'=>'tr'],['cms_locale'=>'en']));
        $this->assertSame('en',$this->locale([],[],['HTTP_HOST'=>'en.example.invalid']));
        $user=CmsUser::where('role','admin')->firstOrFail();$user->language='en';
        $this->assertSame('en',$this->locale([],[],[],$user));
        CmsLanguage::where('code','es')->update(['status'=>0]);
        $this->assertNull($mapping->languageForObservedCountry($countryCode));
        $this->assertSame('en',$this->locale(['lang'=>'../private'],['cms_locale'=>'es']));
        $mapping->removeCountryDefault($id);$this->assertFalse($mapping->hasMappings());
    }
    public function test_language_crud_and_bulk_preserve_single_default(): void
    {
        $service=app(CmsLanguageMutationService::class);
        $row=$service->save(['name'=>'Deutsch','code'=>'de','status'=>1]);
        $service->save(['name'=>'German'], $row->id);$this->assertSame('German',$row->fresh()->name);
        $service->bulk([$row->id],'passive');$this->assertSame(0,(int)$row->fresh()->status);
        $service->bulk([$row->id],'active');$this->assertSame(1,(int)$row->fresh()->status);
        $service->bulk([$row->id],'delete');$this->assertNull($row->fresh());
        $this->assertSame(1,CmsLanguage::where('status',1)->where('is_default',1)->count());
    }
    public function test_primary_locale_selected_views_have_no_unresolved_translation_keys(): void
    {
        $this->login();$missing=[];
        app('translator')->handleMissingKeysUsing(function($key)use(&$missing){$missing[]=$key;return $key;});
        foreach(['en','tr','es'] as $locale){
            CmsUser::where('role','admin')->update(['language'=>$locale]);auth('cms')->forgetUser();
            session()->put(['currentLocale'=>$locale,'currentLocaleManuallySelected'=>true]);
            foreach(['/','/cms/information','/cms/promotions','/cms/sessions','/cms/edit-password','/cms/admin','/cms/admin/languages','/cms/admin/sliders','/cms/admin/promotions','/cms/admin/promotions/order','/cms/admin/sliders/create','/cms/admin/sliders/archive','/cms/admin/menus/edit','/cms/admin/settings/site-settings','/cms/admin/promotions/create'] as $path) $this->assertSame(200,$this->followingRedirects()->get($path)->status(),$locale.' '.$path);
        }
        $this->assertSame([],array_values(array_unique($missing)));
    }
    public function test_proxy_frame_and_app_registries_do_not_expand_authority(): void
    {
        config(['trustedproxy.proxies'=>[]]);Request::setTrustedProxies([],Request::HEADER_X_FORWARDED_FOR);
        $r=Request::create('http://'.($server['HTTP_HOST']??'127.0.0.1').'/','GET',[],[],[],['REMOTE_ADDR'=>'192.0.2.1','HTTP_X_FORWARDED_FOR'=>'203.0.113.9']);
        (new TrustProxies)->handle($r,fn()=>response('ok'));$this->assertSame('192.0.2.1',$r->ip());
        config(['frame_ancestors.enabled'=>true,'frame_ancestors.ancestors'=>['*','javascript:bad','https://trusted.example.invalid'],'frame_ancestors.include_self'=>true]);
        $response=(new FrameAncestorsPolicy)->handle($r,fn()=>response('ok')->header('Content-Security-Policy',"default-src 'self'; frame-ancestors *"));
        $this->assertStringContainsString("frame-ancestors 'self' https://trusted.example.invalid",$response->headers->get('Content-Security-Policy'));
        $commands=array_keys(Artisan::all());$appCommands=array_values(array_filter($commands,fn($c)=>str_starts_with($c,'app:')));sort($appCommands);
        $this->assertSame(['app:admin-bootstrap','app:install:check'],$appCommands);
        $this->assertCount(0,app(\Illuminate\Console\Scheduling\Schedule::class)->events());
        $this->assertInstanceOf(\App\Database\MySqlConnection::class,DB::connection());
        $this->assertNotEmpty(Event::getRawListeners()[\Illuminate\Console\Events\CommandStarting::class]??[]);
        foreach(['App\\Services\\PlayerService','App\\Services\\CanonicalWalletService','App\\Services\\OpsRadar\\OpsRadarService','App\\Models\\SupportRequest'] as $class)$this->assertFalse(class_exists($class));
        foreach(['/cms/support','/cms/affiliate','/cms/social','/chessbet','/cms/admin/financial'] as $path)$this->get($path)->assertNotFound();
    }
    public function test_metric_fragment_uses_real_roots_and_rejects_unselected_metric(): void
    {
        $this->login();$expected=app(\App\Services\GrowthAnalytics\GrowthCustomerIdentity::class)->roots()->whereDate('created_at',today())->count();
        $this->get('/cms/admin')->assertViewHas('registerCount',$expected);
        $this->get('/cms/admin?metric=registers&detail_page=2',['X-Dashboard-Fragment'=>'metric'])->assertOk()->assertHeader('Cache-Control','no-store, private');
        $this->get('/cms/admin?metric=logins',['X-Dashboard-Fragment'=>'metric'])->assertOk();
        $this->get('/cms/admin?metric=deposits',['X-Dashboard-Fragment'=>'metric','Accept'=>'application/json'])->assertStatus(422);
    }
    public function test_dashboard_excludes_smoke_virtual_and_child_registration_fixtures(): void
    {
        $this->login();$baseline=$this->get('/cms/admin')->assertOk()->viewData('registerCount');
        $baselineLogins=$this->get('/cms/admin')->viewData('loginCount');
        $admin=CmsUser::where('role','admin')->firstOrFail();
        $make=function(string $name,array $extra=[]) use ($admin): CmsUser {
            $row=$admin->replicate();$row->forceFill(array_merge(['username'=>$name,'email'=>$name.'@example.invalid','role'=>'customer','registration_source'=>'b1_acceptance_fixture','is_smoke_account'=>0,'parent_id'=>null],$extra));
            app(\App\Services\Security\CmsPasswordIntegrityService::class)->runWithPasswordMutationSource('b1_acceptance_fixture',fn()=>$row->save());return $row;
        };
        $root=$make('metric_real_root');$make('metric_child',['parent_id'=>$root->id]);
        $smoke=$make('metric_smoke',['is_smoke_account'=>1]);$virtual=$make('metric_virtual',['registration_source'=>CmsUser::WEEKLY_WINNER_VIRTUAL_SOURCE]);
        foreach([$root,$smoke,$virtual] as $user)\App\Helper\CmsUserLogService::save($user,$user,\App\Enum\UserLogEnum::LOGGED_IN);
        $this->get('/cms/admin')->assertViewHas('registerCount',$baseline+1)->assertViewHas('loginCount',$baselineLogins+1);
        $this->get('/cms/admin?metric=registers',['X-Dashboard-Fragment'=>'metric'])->assertSee('metric_real_root')->assertDontSee('metric_child')->assertDontSee('metric_smoke')->assertDontSee('metric_virtual');
    }
    public function test_saved_menu_auth_visibility_and_manual_locale_are_real(): void
    {
        $this->login();
        $menu=[['id'=>'private-account-menu','text_tr'=>'Signed-in content','href_tr'=>'/cms/sessions','text_en'=>'English account content','href_en'=>'/cms/sessions','visibility'=>'authenticated','children'=>[]]];
        $this->postJson('/cms/admin/menus/update',['slot'=>'sidebar','menu'=>json_encode($menu)])->assertOk();
        $this->get('/')->assertSee('English account content')->assertDontSee('Signed-in content');
        $this->get('/cms/setLanguage/tr');
        $this->withCookie(config('session.cookie'),session()->getId());
        $this->get('/?lang=en')->assertSee('Signed-in content')->assertDontSee('English account content');
        $this->get('/cms/logout');$this->withCookie(config('session.cookie'),session()->getId());
        $this->get('/')->assertDontSee('English account content')->assertDontSee('Signed-in content');
    }
}
