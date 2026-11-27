<?php
namespace Database\Seeders;

use App\Models\{CmsGame,Provider};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Explicit installation of the real built-in demo, never a fabricated playable fixture. */
class InternalDiceDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $provider = Provider::firstOrCreate(['slug'=>'internal-games'],['name'=>'Internal Games','kind'=>'internal',
                'status'=>'active','show_left_panel'=>true,'is_maintenance'=>false,'rank'=>0]);
            if ($provider->kind!=='internal') throw new \RuntimeException('Internal Games provider identity collision.');
            $game = CmsGame::where('slug','dice')->first();
            if ($game && ($game->reference_id!=='internal:dice' || (int)$game->provider_id!==(int)$provider->id)) {
                throw new \RuntimeException('Dice identity collision; existing catalog state preserved.');
            }
            // An operator-disabled row is never implicitly re-enabled by a repeat install.
            if (!$game) CmsGame::create(['provider_id'=>$provider->id,'name'=>'Dice','slug'=>'dice','reference_id'=>'internal:dice',
                'game_type'=>'instant','description'=>'Rise Internal Games — virtual-credit demo. No real money or withdrawals.',
                'status'=>'active','is_restricted'=>false,'is_maintenance'=>false,'rank'=>21]);
        });
    }
}
