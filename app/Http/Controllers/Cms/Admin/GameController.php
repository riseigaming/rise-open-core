<?php
namespace App\Http\Controllers\Cms\Admin;

use App\Helper\UploadHelper;
use App\Http\Controllers\Controller;
use App\Models\{CmsGame,Provider};
use App\Services\Games\CatalogAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class GameController extends Controller
{
    public function index(Request $r)
    {
        $f=$r->validate(['q'=>'nullable|string|max:100','provider_id'=>'nullable|integer|exists:providers,id','type'=>['nullable',Rule::in(array_keys(CmsGame::TYPES))],'status'=>['nullable',Rule::in(['active','passive'])], 'sort'=>['nullable',Rule::in(['name','rank','id'])]]);
        $games=CmsGame::with('provider')->when($f['q']??null,fn($q,$v)=>$q->where('name','like','%'.$v.'%'))
            ->when($f['provider_id']??null,fn($q,$v)=>$q->where('provider_id',$v))->when($f['type']??null,fn($q,$v)=>$q->where('game_type',$v))
            ->when($f['status']??null,fn($q,$v)=>$q->where('status',$v))->orderBy($f['sort']??'rank')->orderBy('id')->paginate(20)->withQueryString();
        return view('cms.admin.games.index',['games'=>$games,'providers'=>Provider::orderBy('name')->get()]);
    }
    public function create() { return $this->form(new CmsGame(['status'=>'passive','game_type'=>'slot'])); }
    public function edit(CmsGame $game) { return $this->form($game); }
    private function form(CmsGame $game) { return view('cms.admin.games.form',['game'=>$game,'providers'=>Provider::orderBy('name')->get()]); }
    public function store(Request $r) { return $this->save($r,new CmsGame); }
    public function update(Request $r,CmsGame $game) { return $this->save($r,$game); }
    private function save(Request $r,CmsGame $game)
    {
        $data=$r->validate([
            'provider_id'=>'required|integer|exists:providers,id','name'=>'required|string|max:160',
            'slug'=>['required','regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/D','max:160',Rule::unique('cms_games','slug')->ignore($game->id)],
            'game_type'=>['required',Rule::in(array_keys(CmsGame::TYPES))],'reference_id'=>'nullable|string|max:160','description'=>'nullable|string|max:5000',
            'status'=>['required',Rule::in(['active','passive'])],'is_restricted'=>'required|boolean','is_maintenance'=>'required|boolean','rank'=>'required|integer|min:0|max:100000',
            'image'=>'nullable|file|image|mimes:jpg,jpeg,png,webp|max:4096|dimensions:max_width=4096,max_height=4096',
            'demo'=>'prohibited','launch_mode'=>'prohibited','launch_url'=>'prohibited','wallet'=>'prohibited','balance'=>'prohibited','engine'=>'prohibited',
        ]);
        unset($data['image']);$uploaded=null;
        try {
            if ($r->hasFile('image')) $data['thumbnail_image']=$uploaded='catalog/games/'.UploadHelper::upload($r->file('image'),(string)Str::uuid(),'catalog/games');
            DB::transaction(function () use ($game,$data) { $game->fill($data)->save();CatalogAudit::record('catalog.game.saved',$game); });
        } catch (\Throwable $e) { if ($uploaded) Storage::disk('public')->delete($uploaded); throw $e; }
        return redirect()->route('cms.admin.games.edit',$game)->with('catalog_status','Oyun kaydedildi.');
    }
}
