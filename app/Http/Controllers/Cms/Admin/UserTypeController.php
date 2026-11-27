<?php
namespace App\Http\Controllers\Cms\Admin;
use App\Http\Controllers\Controller;
use App\Models\CmsUserType;
use App\Services\Games\CatalogAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
class UserTypeController extends Controller
{
    public function index() { return view('cms.admin.users.types',['types'=>CmsUserType::orderBy('name')->get()]); }
    public function store(Request $r) { return $this->save($r,new CmsUserType); }
    public function update(Request $r,CmsUserType $type) { return $this->save($r,$type); }
    private function save(Request $r,CmsUserType $type)
    {
        $v=$r->validate(['name'=>['required','string','max:100',Rule::unique('cms_user_types','name')->ignore($type->id)],'description'=>'nullable|string|max:1000']);
        abort_if($type->exists && array_key_exists($type->name,CmsUserType::coreDefinitions()),422,'Core profile labels are fixed. Add a custom type instead.');
        DB::transaction(function () use ($type,$v) { $type->fill($v)->save(); CatalogAudit::record('catalog.user_type.saved',$type); });
        return redirect()->route('cms.admin.userTypes.index')->with('catalog_status',__('admin_catalog.user_type_saved'));
    }
}
