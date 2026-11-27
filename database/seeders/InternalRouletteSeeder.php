<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use App\Models\{Provider,CmsGame};
use Illuminate\Support\Facades\DB;
class InternalRouletteSeeder extends Seeder {public function run(): void {DB::transaction(function(){
$p=Provider::firstOrCreate(['slug'=>'internal-games'],['name'=>'Internal Games','kind'=>'internal','status'=>'active','show_left_panel'=>true,'is_maintenance'=>false]);if($p->kind!=='internal')throw new \RuntimeException('Provider identity collision');
$d=config('internal_games.games.roulette-european');$g=CmsGame::where('slug',$d['slug'])->first();if($g&&($g->reference_id!=='internal:roulette-european'||(int)$g->provider_id!==(int)$p->id))throw new \RuntimeException('Roulette identity collision');
if(!$g)CmsGame::create(['provider_id'=>$p->id,'name'=>$d['name'],'slug'=>$d['slug'],'reference_id'=>'internal:roulette-european','status'=>'active','game_type'=>'table','rank'=>40,'description'=>'Existing Rise European Roulette · demo and canonical real-wallet modes']);
});}}
