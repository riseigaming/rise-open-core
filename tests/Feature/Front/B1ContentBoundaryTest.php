<?php
namespace Tests\Feature\Front;

use App\Models\{CmsUser,CmsSlider,CmsSliderTranslation,CmsSiteSetting,CmsLanguage};
use App\Services\Security\CmsAuthenticatedSessionService as Sessions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB,Storage,Hash};
use Tests\TestCase;

final class B1ContentBoundaryTest extends TestCase
{
    use DatabaseTransactions;

    private function login(): void
    {
        $c = json_decode(file_get_contents(getenv('TEST_ADMIN_CREDENTIALS_FILE')), true);
        $this->postJson('/cms/login', ['email'=>$c['username'],'password'=>$c['password']],['X-Requested-With'=>'XMLHttpRequest'])->assertJsonPath('status',200);
        $this->withCookie(config('session.cookie'), session()->getId());
    }
    private function slide(array $extra=[]): array
    {
        return array_replace(['placement'=>'home','status'=>1,'rank'=>1,'langContent'=>[
            ['cms_language_id'=>CmsLanguage::where('code','tr')->value('id'),'title'=>'Boundary slider','description'=>'Local description','url'=>'/cms/information']]], $extra);
    }
    public function test_slider_create_edit_rank_archive_restore_and_locale_scope(): void
    {
        $this->login();
        $r=$this->postJson('/cms/admin/sliders/store',$this->slide())->assertOk();
        $id=(int) basename(parse_url($r->json('redirect_url'),PHP_URL_PATH));
        $this->assertSame('global',CmsSlider::findOrFail($id)->theme_key);
        $this->assertSame('Boundary slider',CmsSliderTranslation::where('cms_slider_id',$id)->value('title'));
        $this->postJson('/cms/admin/sliders/store',$this->slide())->assertOk();
        $this->assertSame(2,(int) CmsSlider::findOrFail($id)->rank);
        $this->postJson('/cms/admin/sliders/update',$this->slide(['sliderId'=>$id,'rank'=>1]))->assertOk();
        $this->assertSame(1,(int) CmsSlider::findOrFail($id)->rank);
        $this->deleteJson('/cms/admin/sliders/delete/'.$id)->assertJsonPath('status',200);
        $this->assertNull(CmsSlider::find($id));
        $this->get('/cms/admin/sliders/archive')->assertOk()->assertSee('Boundary slider');
        $this->post('/cms/admin/sliders/restore/'.$id,['placement'=>'home'])->assertRedirect();
        $this->assertSame(0,(int) CmsSlider::findOrFail($id)->status);
        $this->postJson('/cms/admin/sliders/store',$this->slide(['theme'=>'winx']))->assertStatus(422);
        $this->postJson('/cms/admin/sliders/store',$this->slide(['placement'=>'casino']))->assertStatus(422);
    }
    public function test_image_svg_url_and_real_disk_failure_leave_no_partial_slider(): void
    {
        $this->login(); $before=CmsSlider::count();
        $svg=UploadedFile::fake()->createWithContent('fixture.svg','<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $this->postJson('/cms/admin/sliders/store',$this->slide(['shared_image'=>$svg]))->assertStatus(422);
        $unsafe=$this->slide(); $unsafe['langContent'][0]['url']='javascript:alert(1)';
        $this->postJson('/cms/admin/sliders/store',$unsafe)->assertStatus(422);
        $root=tempnam(sys_get_temp_dir(),'b1-disk-deny-');
        try {
            config(['filesystems.disks.public.root'=>$root]); Storage::forgetDisk('public');
            $this->postJson('/cms/admin/sliders/store',$this->slide(['shared_image'=>UploadedFile::fake()->image('fixture.png')]))->assertStatus(500);
            $this->assertSame($before,CmsSlider::count());
        } finally { unlink($root); Storage::forgetDisk('public'); }
    }
    public function test_recent_authentication_is_required_for_site_settings(): void
    {
        $this->login();
        $this->withSession([Sessions::RECENT_AUTH_AT_KEY=>now()->subHours(1)->timestamp]);
        $this->postJson('/cms/admin/settings/updateSiteSettings',['register_status'=>1])->assertStatus(423);
        $this->assertSame(0,(int) CmsSiteSetting::firstOrFail()->register_status);
    }
    public function test_guest_customer_and_inactive_identity_cannot_access_admin_forms(): void
    {
        foreach(['/cms/admin','/cms/admin/languages','/cms/admin/sliders','/cms/admin/promotions','/cms/admin/menus/edit','/cms/admin/settings/site-settings'] as $path) $this->get($path)->assertRedirect();
        $this->login(); $user=CmsUser::where('role','admin')->firstOrFail();
        $user->role='customer'; $user->save(); auth('cms')->forgetUser();
        // A role mutation invalidates the existing registry before authorization.
        $this->get('/cms/admin')->assertRedirect();
        auth('cms')->forgetUser();
        $c=json_decode(file_get_contents(getenv('TEST_ADMIN_CREDENTIALS_FILE')),true);
        $this->postJson('/cms/login',['email'=>$c['username'],'password'=>$c['password']],['X-Requested-With'=>'XMLHttpRequest'])->assertJsonPath('status',200);
        $this->withCookie(config('session.cookie'),session()->getId());
        foreach(['/cms/admin','/cms/admin/languages','/cms/admin/sliders','/cms/admin/promotions','/cms/admin/menus/edit','/cms/admin/settings/site-settings'] as $path) $this->get($path)->assertForbidden();
        $user->status='rejected';$user->save(); auth('cms')->forgetUser();
        $this->get('/cms/admin')->assertRedirect();
    }
    public function test_login_throttle_is_enforced_and_wrong_password_never_authenticates(): void
    {
        $c=json_decode(file_get_contents(getenv('TEST_ADMIN_CREDENTIALS_FILE')),true);
        for($i=0;$i<10;$i++) $this->postJson('/cms/login',['email'=>$c['username'],'password'=>'wrong-password'],['X-Requested-With'=>'XMLHttpRequest'])->assertJsonPath('status',400);
        $this->postJson('/cms/login',['email'=>$c['username'],'password'=>$c['password']],['X-Requested-With'=>'XMLHttpRequest'])->assertStatus(429);
        $this->assertGuest('cms');
    }
    public function test_manual_maintenance_is_503_escaped_and_without_private_support_assets(): void
    {
        config(['cms_public.maintenance.enabled'=>true,'cms_public.maintenance.message'=>'<script>maintenance fixture</script>']);
        $this->get('/')->assertStatus(503)->assertSee('&lt;script&gt;maintenance fixture&lt;/script&gt;',false)
            ->assertDontSee('<script>maintenance fixture</script>',false)->assertDontSee('/cms/support')->assertDontSee('winx-logo');
    }
    public function test_live_queue_and_failed_job_tables_accept_disposable_records_without_a_worker(): void
    {
        $id=DB::table('jobs_table')->insertGetId(['queue'=>'b1_quarantine','payload'=>'{"displayName":"B1 fixture; never execute"}','attempts'=>0,'reserved_at'=>null,'available_at'=>time()+86400,'created_at'=>time()]);
        $failure=DB::table('failed_jobs')->insertGetId(['connection'=>'database','queue'=>'b1_quarantine','payload'=>'{}','exception'=>'Synthetic local failure record','failed_at'=>now()]);
        $this->assertGreaterThan(0,$id); $this->assertGreaterThan(0,$failure);
        $this->assertSame(0,(int)DB::table('jobs_table')->where('id',$id)->value('attempts'));
    }
    public function test_admin_table_sorting_and_pagination_use_real_columns(): void
    {
        $this->login();
        $total=CmsLanguage::count();
        $this->getJson('/cms/admin/languages/get-ajax-list?sort=name&order=asc&limit=1&offset=1')
            ->assertOk()->assertJsonPath('total',$total)->assertJsonCount(1,'rows');
        $this->getJson('/cms/admin/languages/get-ajax-list?sort=not_a_column')->assertStatus(422);
        foreach(['listing_title','detail_title','campaignTypeText','topLineText','status'] as $sort)
            $this->getJson('/cms/admin/promotions/get-ajax-list?sort='.$sort.'&order=asc&limit=1')->assertOk()->assertJsonStructure(['total','rows']);
        $this->getJson('/cms/admin/promotions/get-ajax-list?sort=not_a_column')->assertStatus(422);
    }
    public function test_type_foreign_key_and_missing_phone_country_never_use_legacy_ids(): void
    {
        $this->assertNotSame(225,(int)DB::table('cms_countries')->value('id'));
        $this->assertNotSame(2,(int)\App\Models\CmsUserType::defaultTypeId());
        $user=CmsUser::where('role','admin')->firstOrFail();
        try {DB::table('cms_users')->where('id',$user->id)->update(['user_type_id'=>2147483647]);$this->fail('Invalid type FK accepted');}
        catch(\Illuminate\Database\QueryException){$this->assertNotSame(2147483647,(int)$user->fresh()->user_type_id);}
        foreach(['parent_id','country_id'] as $field) {
            try {DB::table('cms_users')->where('id',$user->id)->update([$field=>2147483647]);$this->fail('Invalid '.$field.' FK accepted');}
            catch(\Illuminate\Database\QueryException){$this->assertNotSame(2147483647,(int)$user->fresh()->getAttribute($field));}
        }
        try {DB::table('cms_sessions')->insert(['cms_user_id'=>2147483647]);$this->fail('Invalid session FK accepted');}
        catch(\Illuminate\Database\QueryException){$this->assertFalse(DB::table('cms_sessions')->where('cms_user_id',2147483647)->exists());}
        DB::table('cms_countries')->where('id',$user->country_id)->update(['iso2'=>'']);
        $this->expectException(\DomainException::class);
        app(\App\Services\Identity\PhoneIdentityService::class)->normalize('05329876543',(int)$user->country_id);
    }
    public function test_expired_password_reset_cannot_change_hash(): void
    {
        $user=CmsUser::where('role','admin')->firstOrFail();$before=$user->password;
        $token=\Illuminate\Support\Facades\Password::broker('cms_users')->createToken($user);
        DB::table('password_resets')->where('email',$user->email)->update(['created_at'=>now()->subDays(2)]);
        $this->post('/cms/password/reset',['token'=>$token,'email'=>$user->email,'password'=>'Expired-Local-927!','password_confirmation'=>'Expired-Local-927!'])->assertSessionHasErrors();
        $this->assertSame($before,$user->fresh()->password);
    }
    public function test_unsupported_remote_drivers_are_absent_and_rejected(): void
    {
        foreach([
            fn()=>Storage::disk('s3'),
            fn()=>\Illuminate\Support\Facades\Mail::mailer('smtp'),
            fn()=>\Illuminate\Support\Facades\Queue::connection('sync'),
            fn()=>\Illuminate\Support\Facades\Cache::store('redis'),
        ] as $resolve) {
            try {$resolve();$this->fail('Unsupported driver was accepted');}
            catch(\InvalidArgumentException){$this->addToAssertionCount(1);}
        }
        $this->assertFalse(config('restrictions.geo.enabled'));
        $this->assertSame([],config('local_safety.allowed_url_authorities'));
    }
}
