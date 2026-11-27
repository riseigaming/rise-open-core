<?php
namespace App\Http\Controllers\Cms\Admin;
use App\Http\Controllers\Controller;
use App\Models\{CmsGame,GameCurationItem,GameCurationSurface};
use App\Services\Games\GameCurationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GameCurationController extends Controller
{
    public function index(Request $r)
    {
        $key=$r->validate(['surface'=>['nullable',Rule::in(GameCurationService::SURFACES)]])['surface']??'casino_all_games';
        return view('cms.admin.games.curation',['surface'=>GameCurationSurface::where('surface_key',$key)->firstOrFail(),
            'surfaces'=>GameCurationSurface::all(),'items'=>GameCurationItem::with('game.provider')->where('surface_key',$key)->orderBy('rank')->get(),
            'games'=>CmsGame::with('provider')->orderBy('name')->get()]);
    }
    public function saveOrder(Request $r,GameCurationService $service)
    {
        $v=$r->validate(['surface'=>['required',Rule::in(GameCurationService::SURFACES)],'game_ids'=>'nullable|array|max:100','game_ids.*'=>'required|integer|distinct|exists:cms_games,id','active'=>'required|boolean','auto_fill_enabled'=>'required|boolean']);
        $service->saveOrder($v['surface'],array_map('intval',$v['game_ids']??[]),(bool)$v['active'],(bool)$v['auto_fill_enabled']);
        return redirect()->route('cms.admin.games.curation',['surface'=>$v['surface']])->with('catalog_status',__('admin_catalog.curation_saved'));
    }
}
