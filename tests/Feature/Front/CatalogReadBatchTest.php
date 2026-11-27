<?php
namespace Tests\Feature\Front;

use App\Models\{CmsCountry,CmsGame,CmsUser,CmsUserProductAccessOverride,GameCurationItem,GameCurationSurface,Provider};
use App\Services\Games\CatalogReadService;
use App\Services\ProductAccess\ProductAccessPolicyService;
use App\Services\Restrictions\CentralRestrictionPolicyService;
use App\SiteSettings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class CatalogReadBatchTest extends TestCase
{
    use DatabaseTransactions;

    private function provider(string $slug, string $kind='external'): Provider
    {
        return Provider::create(['slug'=>$slug,'name'=>$slug,'kind'=>$kind,'status'=>'active','show_left_panel'=>true]);
    }

    private function game(Provider $provider, string $slug, array $extra=[]): CmsGame
    {
        return CmsGame::create(array_replace(['provider_id'=>$provider->id,'slug'=>$slug,'name'=>$slug,'game_type'=>'slot','status'=>'active','rank'=>10],$extra));
    }

    private function deny(CmsUser $user, string $product, string $alias='', string $mode='deny'): void
    {
        CmsUserProductAccessOverride::create(['cms_user_id'=>$user->id,'product_key'=>$product,'provider_alias'=>$alias,'access_mode'=>$mode,'reason'=>'Disposable batch regression']);
    }

    public function test_batch_matches_single_provider_policy_including_sql_collation_and_deny_precedence(): void
    {
        $user=CmsUser::where('role','admin')->firstOrFail();
        $external=$this->provider('batch-external');$other=$this->provider('batch-other');$internal=$this->provider('batch-internal','internal');
        // Product keys, mode and aliases have always been compared by MySQL collation.
        $this->deny($user,'EXTERNAL_GAMES','BATCH-EXTERNAL','DENY');
        $this->deny($user,'external_games','','allow');
        $this->deny($user,'internal_games');$this->deny($user,'internal_games',$internal->slug,'allow');
        $access=app(ProductAccessPolicyService::class);$filter=$access->catalogFilter($user);
        foreach ([$external,$other,$internal] as $provider) $this->assertSame($access->catalogAllowed($user,$provider),$filter($provider));
        $this->assertFalse($filter($external));$this->assertTrue($filter($other));$this->assertFalse($filter($internal));
        $a=$this->game($external,'batch-denied');$b=$this->game($other,'batch-visible',['is_maintenance'=>true]);$c=$this->game($internal,'batch-internal-denied');
        $this->game($other,'batch-hidden',['status'=>'passive']);$this->game($other,'batch-restricted',['is_restricted'=>true]);
        $hidden=$this->provider('batch-provider-hidden');$hidden->update(['show_left_panel'=>false]);$this->game($hidden,'batch-provider-hidden-game');
        $catalog=app(CatalogReadService::class);
        $this->assertSame([$b->id],$catalog->items('casino_all_games',$user)->modelKeys());
        $this->assertSame([$a->id,$b->id,$c->id],$catalog->items('casino_all_games',null)->modelKeys());
        $this->assertSame('maintenance',$b->launchState());$this->assertSame('unsupported',$a->launchState());
        $user->status='rejected';$this->assertCount(0,$catalog->items('casino_all_games',$user));
    }

    public function test_each_evaluation_reads_policy_changes_and_preserves_filters_and_curated_order(): void
    {
        $user=CmsUser::where('role','admin')->firstOrFail();$user->country_id=CmsCountry::value('id');
        $provider=$this->provider('batch-current');$other=$this->provider('batch-unrelated','internal');
        $a=$this->game($provider,'batch-alpha',['rank'=>1]);$b=$this->game($provider,'batch-beta',['rank'=>20,'game_type'=>'table']);$c=$this->game($other,'batch-gamma');
        GameCurationItem::create(['surface_key'=>'casino_all_games','game_id'=>$b->id,'rank'=>1]);
        $catalog=app(CatalogReadService::class);
        $this->assertSame([$b->id,$a->id,$c->id],$catalog->items('casino_all_games',$user)->modelKeys());
        $this->assertSame([$b->id],$catalog->items('casino_all_games',$user,['provider'=>$provider->id,'type'=>'table','q'=>'beta'])->modelKeys());
        $this->deny($user,'external_games');$this->assertSame([$c->id],$catalog->items('casino_all_games',$user)->modelKeys());
        $another=clone $user;$another->id=-1;$this->assertCount(3,$catalog->items('casino_all_games',$another));
        CmsUserProductAccessOverride::where('cms_user_id',$user->id)->delete();
        SiteSettings::updateOrCreate(['key'=>CentralRestrictionPolicyService::SETTINGS_KEY],['value'=>json_encode(['countries'=>[$user->country_id=>['products'=>['casino']]]])]);
        $this->assertCount(0,$catalog->items('casino_all_games',$user));
        SiteSettings::where('key',CentralRestrictionPolicyService::SETTINGS_KEY)->update(['value'=>'{}']);
        $this->assertCount(3,$catalog->items('casino_all_games',$user));
        GameCurationSurface::where('surface_key','casino_all_games')->update(['auto_fill_enabled'=>false]);
        $this->assertSame([$b->id],$catalog->items('casino_all_games',$user)->modelKeys());
        GameCurationSurface::where('surface_key','casino_all_games')->update(['status'=>'passive']);
        $this->assertCount(0,$catalog->items('casino_all_games',$user));
    }

    public function test_two_index_reads_have_bounded_queries_as_game_count_grows(): void
    {
        $provider=$this->provider('batch-cost','internal');$user=CmsUser::where('role','admin')->firstOrFail();
        $catalog=app(CatalogReadService::class);$counts=[];
        foreach ([100,1000] as $size) {
            $start=CmsGame::count();$rows=[];
            for ($i=$start;$i<$size;$i++) $rows[]=['provider_id'=>$provider->id,'slug'=>'batch-cost-'.$i,'name'=>'batch-cost-'.$i,'game_type'=>'slot','status'=>'active','rank'=>$i];
            foreach (array_chunk($rows,250) as $chunk) DB::table('cms_games')->insert($chunk);
            DB::enableQueryLog();DB::flushQueryLog();
            $this->assertCount($size,$catalog->items('casino_all_games',$user));
            $this->assertCount($size,$catalog->items('casino_all_games',$user));
            $counts[]=count(DB::getQueryLog());DB::disableQueryLog();DB::flushQueryLog();
        }
        $this->assertSame($counts[0],$counts[1]);$this->assertLessThanOrEqual(14,$counts[1]);
    }
}
