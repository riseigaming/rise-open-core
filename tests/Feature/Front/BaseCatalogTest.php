<?php
namespace Tests\Feature\Front;

use App\Models\{CmsGame,Provider,CmsUser,CmsUserType,CmsStaffProfile,CmsStaffPermission,CmsUserProductAccessOverride,CmsCountry};
use App\Services\CmsStaff\CmsStaffPermissionService as P;
use App\Services\Security\CmsAuthenticatedSessionService as Sessions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB,Hash,Storage,Schema};
use Tests\TestCase;

final class BaseCatalogTest extends TestCase
{
    use DatabaseTransactions;
    private function login(?CmsUser $user=null): void
    {
        if (auth('cms')->check()) { $this->get('/cms/logout'); $this->withCookie(config('session.cookie'),session()->getId()); }
        $c=json_decode(file_get_contents(getenv('TEST_ADMIN_CREDENTIALS_FILE')),true);
        $this->postJson('/cms/login',['email'=>$user?->username??$c['username'],'password'=>$user?'Catalog-Fixture-927!':$c['password']],['X-Requested-With'=>'XMLHttpRequest'])->assertJsonPath('status',200);
        $this->withCookie(config('session.cookie'),session()->getId());
    }
    private function user(string $name='catalog_customer'): CmsUser
    {
        $u=new CmsUser;$u->forceFill(['username'=>$name,'email'=>$name.'@example.invalid','role'=>'customer','status'=>'normal','country_id'=>CmsCountry::value('id'),'user_type_id'=>CmsUserType::value('id'),'password'=>Hash::make('Catalog-Fixture-927!')])->save();return $u;
    }
    private function provider(string $slug='local-provider',string $kind='external'): Provider
    {
        return Provider::create(['name'=>$slug,'slug'=>$slug,'kind'=>$kind,'status'=>'active','show_left_panel'=>true]);
    }
    private function game(Provider $p,string $name='Local Game'): CmsGame
    {
        return CmsGame::create(['provider_id'=>$p->id,'name'=>$name,'slug'=>strtolower(str_replace(' ','-',$name)),'status'=>'active','game_type'=>'slot']);
    }
    private function gameData(Provider $p,array $extra=[]): array
    {
        return array_replace(['provider_id'=>$p->id,'name'=>'Saved catalog game','slug'=>'saved-catalog-game','game_type'=>'slot','status'=>'active','is_restricted'=>0,'is_maintenance'=>0,'rank'=>2],$extra);
    }
    private function providerData(array $extra=[]): array
    {
        return array_replace(['name'=>'Owned catalog provider','slug'=>'owned-catalog-provider','kind'=>'external','status'=>'active','show_left_panel'=>1,'is_maintenance'=>0,'rank'=>1],$extra);
    }
    public function test_real_admin_provider_game_upload_filter_and_global_consumer(): void
    {
        $this->login();$this->post('/cms/admin/providers',$this->providerData())->assertRedirect();$p=Provider::where('slug','owned-catalog-provider')->firstOrFail();
        Storage::fake('public');$this->post('/cms/admin/games',$this->gameData($p,['image'=>UploadedFile::fake()->image('catalog.png')]))->assertRedirect();
        $g=CmsGame::where('slug','saved-catalog-game')->firstOrFail();Storage::disk('public')->assertExists($g->thumbnail_image);
        $this->get('/cms/admin/games?q=Saved&provider_id='.$p->id.'&type=slot')->assertOk()->assertSee('Saved catalog game');
        $this->get('/cms/admin/games?q=NoSuchCatalogRecord')->assertOk()->assertDontSee('Saved catalog game');
        $this->get('/casino')->assertOk()->assertSee('Saved catalog game')->assertHeader('Cache-Control','no-store, private');
        $this->get('/cms/games/'.$g->slug)->assertOk()->assertSee('Saved catalog game');
        $this->assertTrue(DB::table('cms_sensitive_mutation_events')->where('event_type','catalog.game.saved')->exists());
    }
    public function test_customer_and_task_limited_staff_cannot_cross_management_permissions(): void
    {
        $p=$this->provider();$u=$this->user();$this->login($u);$count=Provider::count();
        $this->get('/cms/admin/games')->assertForbidden();$this->post('/cms/admin/providers',$this->providerData())->assertForbidden();
        $this->assertSame($count,Provider::count());
        CmsStaffProfile::create(['cms_user_id'=>$u->id,'active'=>true]);
        foreach([P::ACCESS,P::CATALOG_GAMES]as $key)CmsStaffPermission::create(['cms_user_id'=>$u->id,'permission_key'=>$key,'enabled'=>true]);
        $this->get('/cms/admin/games')->assertOk()->assertDontSee('href="'.route('cms.admin.providers.index').'"',false);
        $this->get('/cms/admin/providers')->assertForbidden();$this->post('/cms/admin/providers',$this->providerData())->assertForbidden();
        $this->post('/cms/admin/games',$this->gameData($p))->assertRedirect();
        $this->post('/cms/admin/games/curation',['surface'=>'casino_all_games','active'=>1,'auto_fill_enabled'=>1])->assertForbidden();
        $this->post('/cms/admin/users/'.$u->id,['role'=>'admin'])->assertForbidden();
        $this->assertSame('customer',$u->fresh()->role);$this->assertSame($count,Provider::count());
    }
    public function test_explicit_product_deny_beats_provider_allow_and_cannot_be_changed_by_customer(): void
    {
        $external=$this->provider();$denied=$this->game($external,'Denied Catalog Game');$internal=$this->provider('internal-local','internal');$allowed=$this->game($internal,'Allowed Catalog Game');$u=$this->user();
        foreach([['provider_alias'=>'','access_mode'=>'deny'],['provider_alias'=>$external->slug,'access_mode'=>'allow']]as $v)CmsUserProductAccessOverride::create($v+['cms_user_id'=>$u->id,'product_key'=>'external_games','reason'=>'Explicit isolated fixture']);
        $this->login($u);$this->get('/casino')->assertOk()->assertDontSee($denied->name)->assertSee($allowed->name);
        $this->get('/cms/games/'.$denied->slug)->assertNotFound();$this->post('/cms/games/'.$denied->slug.'/launch')->assertNotFound();
        $this->post('/cms/admin/users/'.$u->id.'/access',['product'=>'external_games','provider'=>'','mode'=>'allow','reason'=>'Attempt'])->assertForbidden();
        $this->assertSame('deny',CmsUserProductAccessOverride::where('cms_user_id',$u->id)->where('provider_alias','')->value('access_mode'));
    }
    public function test_provider_false_flags_are_explicitly_selected_on_edit_and_remain_hidden(): void
    {
        $this->login();$p=$this->provider('owned-catalog-provider');$p->update(['show_left_panel'=>false,'is_maintenance'=>false]);
        $html=$this->get('/cms/admin/providers/'.$p->id.'/edit')->assertOk()->getContent();
        foreach (['show_left_panel','is_maintenance'] as $field) {
            preg_match('/<select[^>]*id="'.$field.'"[^>]*>(.*?)<\/select>/s',$html,$select);
            $this->assertMatchesRegularExpression('/<option value="0"[^>]*selected/',$select[1]);
        }
        $this->post('/cms/admin/providers/'.$p->id,$this->providerData(['show_left_panel'=>0,'is_maintenance'=>0]))->assertRedirect();
        $this->assertFalse($p->fresh()->show_left_panel);$this->assertFalse($p->fresh()->is_maintenance);
        $g=$this->game($p);$this->get('/casino')->assertOk()->assertDontSee($g->name);
    }
    public function test_native_admin_access_contract_rejects_wrong_provider_scope_and_persists_deny_precedence(): void
    {
        $this->login();$p=$this->provider();$u=$this->user();$g=$this->game($p);
        $base=['product'=>'external_games','provider'=>'','mode'=>'deny','reason'=>'Admin catalog access acceptance'];
        $url='/cms/admin/users/'.$u->id.'/access';
        $this->post($url,$base)->assertRedirect();
        $this->postJson($url,array_replace($base,['product'=>'internal_games','provider'=>$p->slug,'mode'=>'allow']))->assertUnprocessable()->assertJsonValidationErrors('provider');
        $this->post($url,array_replace($base,['provider'=>$p->slug,'mode'=>'allow']))->assertRedirect();
        $this->assertSame(2,CmsUserProductAccessOverride::where('cms_user_id',$u->id)->count());
        $this->login($u);$this->get('/casino')->assertOk()->assertDontSee($g->name);
    }
    public function test_hidden_maintenance_and_unsupported_states_never_create_game_or_money_execution(): void
    {
        $p=$this->provider();$g=$this->game($p);$this->get('/casino')->assertOk();
        $before = CmsGame::count();
        $launch = '/cms/games/'.$g->slug.'/launch';
        $this->post($launch)->assertStatus(409)->assertSee('Play is currently unavailable.');
        $this->post($launch.'?lang=tr')->assertStatus(409)->assertSee('başlatılamıyor');
        $this->assertSame($before,CmsGame::count());$this->assertSame(0,DB::table('internal_game_sessions')->count());$this->assertSame(0,DB::table('internal_game_rounds')->count());$this->assertSame(0,DB::table('cms_user_transactions')->count());
        $p->update(['is_maintenance'=>true]);$this->post('/cms/games/'.$g->slug.'/launch')->assertStatus(503);
        $p->update(['is_maintenance'=>false]);$g->update(['is_restricted'=>true]);$this->get('/cms/games/'.$g->slug)->assertNotFound();$this->post('/cms/games/'.$g->slug.'/launch')->assertNotFound();
        $g->update(['is_restricted'=>false]);$p->update(['show_left_panel'=>false]);$this->get('/casino')->assertDontSee($g->name);$this->get('/cms/games/'.$g->slug)->assertNotFound();
    }
    public function test_curation_order_and_home_consumer_keep_visibility_policy(): void
    {
        $this->login();$p=$this->provider();$a=$this->game($p,'Alpha Catalog');$b=$this->game($p,'Beta Catalog');$hidden=$this->game($p,'Hidden Catalog');$hidden->update(['status'=>'passive']);
        $data=['surface'=>'casino_all_games','active'=>1,'auto_fill_enabled'=>0,'game_ids'=>[$b->id,$hidden->id,$a->id]];
        $this->post('/cms/admin/games/curation',$data)->assertRedirect();$this->get('/casino')->assertOk()->assertSeeInOrder(['Beta Catalog','Alpha Catalog'])->assertDontSee('Hidden Catalog');
        $this->postJson('/cms/admin/games/curation',array_replace($data,['game_ids'=>[$a->id,$a->id]]))->assertUnprocessable();
        $this->post('/cms/admin/games/curation',array_replace($data,['surface'=>'home_special_games']))->assertRedirect();$this->get('/')->assertOk()->assertSeeInOrder(['Beta Catalog','Alpha Catalog'])->assertDontSee('Hidden Catalog');
    }
    public function test_country_restriction_uses_existing_policy_and_is_immediately_consumed(): void
    {
        $this->login();$g=$this->game($this->provider());$country=CmsCountry::value('id');
        $this->post('/cms/admin/restrictions/'.$country,['casino_blocked'=>1,'vpn_policy'=>'allow','reason'=>'Local catalog country policy'])->assertRedirect();
        $this->get('/casino')->assertOk()->assertDontSee($g->name);$this->get('/cms/games/'.$g->slug)->assertNotFound();
        $this->post('/cms/admin/restrictions/'.$country,['casino_blocked'=>0,'vpn_policy'=>'allow','reason'=>'Local catalog policy restored'])->assertRedirect();
        $this->get('/casino')->assertOk()->assertSee($g->name);
    }
    public function test_provider_identity_and_real_launch_configuration_are_not_mutable_through_metadata_forms(): void
    {
        $this->login();$p=$this->provider('owned-catalog-provider');
        $this->postJson('/cms/admin/providers/'.$p->id,$this->providerData(['slug'=>'changed-identity']))->assertUnprocessable();$this->assertSame('owned-catalog-provider',$p->fresh()->slug);
        $this->postJson('/cms/admin/providers',$this->providerData(['config'=>['secret'=>'must-not-be-stored']]))->assertUnprocessable();
        $this->postJson('/cms/admin/games',$this->gameData($p,['launch_url'=>'https://example.invalid/launch']))->assertUnprocessable();
        $svg=UploadedFile::fake()->createWithContent('unsafe.svg','<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $this->postJson('/cms/admin/games',$this->gameData($p,['image'=>$svg]))->assertUnprocessable();$this->assertSame(0,CmsGame::count());
    }
    public function test_user_staff_role_edits_are_audited_and_last_admin_cannot_be_disabled(): void
    {
        $this->login();$admin=CmsUser::where('role','admin')->firstOrFail();
        $v=['username'=>'new_catalog_staff','email'=>'new-catalog-staff@example.invalid','first_name'=>'Catalog','last_name'=>'Staff','role'=>'customer','status'=>'normal','country_id'=>CmsCountry::value('id'),'user_type_id'=>CmsUserType::value('id'),'password'=>'Catalog-Fixture-927!','staff_active'=>1,'permissions'=>[P::ACCESS,P::CATALOG_GAMES]];
        $this->post('/cms/admin/users',$v)->assertRedirect();$u=CmsUser::where('username',$v['username'])->firstOrFail();
        $this->assertTrue(Hash::check($v['password'],$u->password));$this->assertNull($u->password_raw);$this->assertTrue(app(P::class)->has($u,P::CATALOG_GAMES));$this->assertFalse(app(P::class)->has($u,P::CATALOG_PROVIDERS));
        $this->postJson('/cms/admin/users',array_replace($v,['username'=>'another_catalog_staff','email'=>'another-staff@example.invalid','balance'=>100]))->assertUnprocessable();
        $this->postJson('/cms/admin/users/'.$admin->id,array_replace($v,['username'=>$admin->username,'email'=>$admin->email,'role'=>'customer','password'=>'']))->assertUnprocessable();
        $this->assertSame('admin',$admin->fresh()->role);$this->assertTrue(DB::table('cms_sensitive_mutation_events')->where('event_type','catalog.user.saved')->where('subject_id',$u->id)->exists());
    }
}
