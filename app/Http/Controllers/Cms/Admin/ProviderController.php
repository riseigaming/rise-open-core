<?php
namespace App\Http\Controllers\Cms\Admin;

use App\Http\Controllers\Controller;
use App\Models\Provider;
use App\Services\Games\CatalogAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProviderController extends Controller
{
    public function index(Request $request)
    {
        $filter=$request->validate(['q'=>'nullable|string|max:100','status'=>['nullable',Rule::in(['active','passive'])]]);
        $providers=Provider::withCount('games')->when($filter['q']??null,fn($q,$v)=>$q->where('name','like','%'.$v.'%'))
            ->when($filter['status']??null,fn($q,$v)=>$q->where('status',$v))->orderBy('rank')->orderBy('name')->paginate(20)->withQueryString();
        return view('cms.admin.providers.index',compact('providers'));
    }
    public function create() { return view('cms.admin.providers.form',['provider'=>new Provider(['kind'=>'external','status'=>'passive','show_left_panel'=>true])]); }
    public function edit(Provider $provider) { return view('cms.admin.providers.form',compact('provider')); }
    public function store(Request $request) { return $this->save($request,new Provider); }
    public function update(Request $request,Provider $provider) { return $this->save($request,$provider); }
    private function save(Request $r,Provider $provider)
    {
        $data=$r->validate([
            'name'=>'required|string|max:120','slug'=>['required','regex:/\A(?:[a-z0-9]+(?:-[a-z0-9]+)*|primary_external_provider)\z/D','max:120',Rule::unique('providers','slug')->ignore($provider->id)],
            'kind'=>['required',Rule::in(['internal','external'])],'description'=>'nullable|string|max:3000',
            'status'=>['required',Rule::in(['active','passive'])],'show_left_panel'=>'required|boolean','is_maintenance'=>'required|boolean','rank'=>'required|integer|min:0|max:100000',
            'config'=>'prohibited','credentials'=>'prohibited','endpoint'=>'prohibited',
        ]);
        // Provider aliases and kind scope user restrictions; changing them must not silently bypass a deny.
        abort_if($provider->exists && ($provider->slug!==$data['slug'] || $provider->kind!==$data['kind']),422,'Provider identity is immutable.');
        DB::transaction(function () use ($provider,$data) { $provider->fill($data)->save(); CatalogAudit::record('catalog.provider.saved',$provider); });
        return redirect()->route('cms.admin.providers.edit',$provider)->with('catalog_status',__('admin_catalog.provider_saved'));
    }
}
