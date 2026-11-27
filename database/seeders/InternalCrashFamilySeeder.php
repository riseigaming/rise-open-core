<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\{Provider,CmsGame};
class InternalCrashFamilySeeder extends Seeder {public function run():void{DB::transaction(function(){
 $p=Provider::firstOrCreate(['slug'=>'internal-games'],['name'=>'Internal Games','kind'=>'internal','status'=>'active','show_left_panel'=>true,'is_maintenance'=>false]);if($p->kind!=='internal')throw new \RuntimeException('Provider identity collision');
 foreach(['sky-rush','orbit-x']as $code){$d=config('internal_games.games.'.$code);$g=CmsGame::where('slug',$d['slug'])->first();if($g&&($g->reference_id!==$d['reference_id']||(int)$g->provider_id!==(int)$p->id))throw new \RuntimeException('Crash game identity collision');if(!$g)CmsGame::create(['provider_id'=>$p->id,'name'=>$d['name'],'slug'=>$d['slug'],'reference_id'=>$d['reference_id'],'status'=>'active','game_type'=>'table','rank'=>$d['homepage_special_rank'],'description'=>'Existing Rise '.$d['name'].' · demo and canonical real-wallet modes']);}
});}}
