<?php
namespace App\Http\Controllers\Cms\Admin;
use App\Http\Controllers\Controller;
use App\Models\{CmsCountry,CmsSiteSetting};
use App\SiteSettings;
use App\Services\Games\CatalogAudit;
use App\Services\Restrictions\CentralRestrictionPolicyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RestrictionController extends Controller
{
    public function index(CentralRestrictionPolicyService $policy) { return view('cms.admin.catalog.restrictions',['countries'=>CmsCountry::orderBy('name')->get(),'policy'=>$policy->settings()]); }
    public function storeCountry(Request $r)
    {
        $r->merge(['iso2'=>strtoupper(trim((string)$r->input('iso2')))]);
        $v=$r->validate(['name'=>'required|string|max:120','iso2'=>['required','regex:/\A[A-Z]{2}\z/D',Rule::unique('cms_countries','iso2')]]);
        DB::transaction(function () use ($v) { $country=new CmsCountry; $country->forceFill($v)->save();CatalogAudit::record('catalog.country.created',$country); });
        return redirect()->route('cms.admin.restrictions.index')->with('catalog_status',__('admin_catalog.country_added'));
    }
    public function updateCountry(Request $r,CmsCountry $country,CentralRestrictionPolicyService $policy)
    {
        $v=$r->validate(['casino_blocked'=>'required|boolean','vpn_policy'=>'required|in:allow,block','reason'=>'required|string|min:3|max:255']);
        DB::transaction(function () use ($country,$policy,$v) {
            // Serialize edits to the existing shared JSON setting, including first creation.
            CmsSiteSetting::query()->lockForUpdate()->firstOrFail();
            $settings=$policy->settings();$entry=$settings['countries'][(string)$country->id]??[];
            $products=array_values(array_diff($entry['products']??[],['casino']));if ($v['casino_blocked']) $products[]='casino';
            $settings['countries'][(string)$country->id]=array_merge($entry,['products'=>$products,'reason_code'=>'geo_restricted','reason'=>$v['reason'],'updated_by'=>auth('cms')->id(),'updated_at'=>now()->toIso8601String()]);
            $settings['vpn_policy']=$v['vpn_policy'];
            SiteSettings::updateOrCreate(['key'=>CentralRestrictionPolicyService::SETTINGS_KEY],['value'=>json_encode($settings,JSON_THROW_ON_ERROR)]);
            CatalogAudit::record('catalog.country_policy.saved',$country,['casino_blocked'=>(bool)$v['casino_blocked'],'vpn_policy'=>$v['vpn_policy']]);
        });
        return redirect()->route('cms.admin.restrictions.index')->with('catalog_status',__('admin_catalog.policy_saved'));
    }
}
