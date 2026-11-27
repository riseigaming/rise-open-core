<?php
namespace App\Services\Games;

use App\Models\{CmsGame,GameCurationItem,GameCurationSurface};
use Illuminate\Support\Facades\DB;

/** Existing surface-key + explicit ranked-item workflow, without provider import/finance consumers. */
class GameCurationService
{
    public const SURFACES = ['casino_all_games','home_special_games'];

    public function saveOrder(string $key, array $ids, bool $active, bool $autoFill): void
    {
        abort_unless(in_array($key,self::SURFACES,true),404);
        abort_unless(count($ids)===count(array_unique($ids)) && count($ids)<=100,422);
        abort_unless(CmsGame::whereIn('id',$ids)->count()===count($ids),422);
        DB::transaction(function () use ($key,$ids,$active,$autoFill) {
            $surface=GameCurationSurface::where('surface_key',$key)->lockForUpdate()->firstOrFail();
            $surface->update(['status'=>$active?'active':'passive','auto_fill_enabled'=>$autoFill]);
            GameCurationItem::where('surface_key',$key)->delete();
            foreach ($ids as $rank=>$id) GameCurationItem::create(['surface_key'=>$key,'game_id'=>$id,'rank'=>$rank+1]);
            CatalogAudit::record('catalog.curation.saved',$surface,['game_ids'=>$ids,'active'=>$active,'auto_fill'=>$autoFill]);
        });
    }
}
