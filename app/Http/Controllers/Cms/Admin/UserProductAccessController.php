<?php
namespace App\Http\Controllers\Cms\Admin;
use App\Http\Controllers\Controller;
use App\Models\{CmsUser,Provider};
use App\Services\ProductAccess\ProductAccessPolicyService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserProductAccessController extends Controller
{
    public function update(Request $r,CmsUser $user,ProductAccessPolicyService $policy)
    {
        $v=$r->validate(['product'=>['required',Rule::in(ProductAccessPolicyService::PRODUCTS)],'provider'=>['nullable','string','max:120',Rule::exists('providers','slug')->where('kind',$r->input('product')==='internal_games'?'internal':'external')],'mode'=>'required|in:default,allow,deny','reason'=>'required|string|min:3|max:255']);
        $policy->upsertOverride($user,$v['product'],$v['provider']??'',$v['mode'],$v['reason']);
        return redirect()->route('cms.admin.users.edit',$user)->with('catalog_status',__('admin_catalog.product_access_saved'));
    }
}
