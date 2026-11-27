<?php
namespace App\Http\Controllers\Cms\Admin;

use App\Http\Controllers\Controller;
use App\Models\{CmsUser,CmsUserType,CmsCountry,CmsCurrency,CmsSession,CmsStaffProfile,CmsStaffPermission,CmsUserProductAccessOverride,Provider};
use App\Services\CmsStaff\CmsStaffPermissionService as Permissions;
use App\Services\Games\CatalogAudit;
use App\Services\Security\{CmsAuthenticatedSessionService,CmsPasswordIntegrityService,RegistrationInputPolicy};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\{Rule,ValidationException};

class UserController extends Controller
{
    public function index(Request $r)
    {
        $f=$r->validate(['q'=>'nullable|string|max:100']);
        $users=CmsUser::with('userType')->when($f['q']??null,fn($q,$v)=>$q->where(fn($q)=>$q->where('username','like','%'.$v.'%')->orWhere('email','like','%'.$v.'%')))->orderByDesc('id')->paginate(20)->withQueryString();
        return view('cms.admin.users.index',compact('users'));
    }
    public function create() { return $this->form(new CmsUser(['role'=>'customer','status'=>'normal'])); }
    public function edit(CmsUser $user) { return $this->form($user); }
    private function form(CmsUser $user)
    {
        return view('cms.admin.users.form',[
            'user'=>$user,'countries'=>CmsCountry::orderBy('name')->get(),'types'=>CmsUserType::orderBy('name')->get(),
            'staff'=>$user->exists?CmsStaffProfile::where('cms_user_id',$user->id)->first():null,
            'permissionKeys'=>Permissions::CATALOG_PERMISSIONS,
            'enabledPermissions'=>$user->exists?CmsStaffPermission::where('cms_user_id',$user->id)->where('enabled',true)->pluck('permission_key')->all():[],
            'sessions'=>$user->exists?CmsSession::where('cms_user_id',$user->id)->orderByDesc('updated_at')->get():collect(),
            'access'=>$user->exists?CmsUserProductAccessOverride::where('cms_user_id',$user->id)->get():collect(),
            'providers'=>Provider::orderBy('name')->get(),
        ]);
    }
    public function store(Request $r,CmsPasswordIntegrityService $passwords,CmsAuthenticatedSessionService $sessions) { return $this->save($r,new CmsUser,$passwords,$sessions); }
    public function update(Request $r,CmsUser $user,CmsPasswordIntegrityService $passwords,CmsAuthenticatedSessionService $sessions) { return $this->save($r,$user,$passwords,$sessions); }
    private function save(Request $r,CmsUser $user,CmsPasswordIntegrityService $passwords,CmsAuthenticatedSessionService $sessions)
    {
        $r->merge(['username'=>trim((string)$r->input('username')),'email'=>strtolower(trim((string)$r->input('email')))]);
        $v=$r->validate([
            'username'=>['required','string','max:50',Rule::unique('cms_users','username')->ignore($user->id)],
            'email'=>['required','email','max:100',Rule::unique('cms_users','email')->ignore($user->id)],
            'first_name'=>'nullable|string|max:50','last_name'=>'nullable|string|max:50',
            'role'=>['required',Rule::in(['admin','customer'])],'status'=>['required',Rule::in(['normal','accepted','fully_accepted','rejected'])],
            'country_id'=>'required|integer|exists:cms_countries,id','user_type_id'=>'required|integer|exists:cms_user_types,id',
            'password'=>$user->exists?'nullable|string':'required|string','staff_active'=>'required|boolean',
            'permissions'=>'nullable|array','permissions.*'=>['required',Rule::in(Permissions::CATALOG_PERMISSIONS)],
            'balance'=>'prohibited','password_raw'=>'prohibited','remember_token'=>'prohibited','external_id'=>'prohibited','player_id'=>'prohibited',
        ]);
        if (RegistrationInputPolicy::identifierProblem($v['username']) || str_contains($v['username'],'@')) throw ValidationException::withMessages(['username'=>__('admin_catalog.invalid_username')]);
        if (($v['password']??'')!=='' && RegistrationInputPolicy::passwordProblem($v['password'])) throw ValidationException::withMessages(['password'=>__('admin_catalog.invalid_password')]);
        $exists=$user->exists;$plain=$v['password']??null;$staffActive=(bool)$v['staff_active'];$selected=$v['permissions']??[];
        unset($v['password'],$v['staff_active'],$v['permissions']);
        DB::transaction(function () use ($user,$v,$staffActive,$selected,$plain,$passwords,$exists) {
            $admins=CmsUser::where('role','admin')->where('status','<>','rejected')->lockForUpdate()->get();
            if ($exists && $user->role==='admin' && $user->status!=='rejected' && ($v['role']!=='admin' || $v['status']==='rejected') && $admins->count()<=1) throw ValidationException::withMessages(['role'=>__('admin_catalog.last_administrator')]);
            $user->fill($v);
            if (!$exists) {
                $currency = CmsCurrency::query()->where('status', 'active')
                    ->whereIn('code', config('product_wallets.fiat_assets', []))
                    ->orderByDesc('is_default')->orderBy('id')->value('code');
                if ($currency === null) {
                    throw ValidationException::withMessages(['currency' => __('admin_catalog.no_active_currency')]);
                }
                // Profile metadata only. No wallet provisioning.
                $user->currency = $currency;
            }
            $passwords->runWithPasswordMutationSource('base_catalog_admin',function () use ($user,$plain,$passwords) {
                if ($plain!==null && $plain!=='') $user->password=$passwords->hashAndValidatePlainPassword($plain,'base_catalog_admin');
                $user->save();
            });
            CmsStaffProfile::updateOrCreate(['cms_user_id'=>$user->id],['active'=>$staffActive,'title'=>'Catalog staff','department'=>'Catalog']);
            foreach (Permissions::CATALOG_PERMISSIONS as $key) CmsStaffPermission::updateOrCreate(['cms_user_id'=>$user->id,'permission_key'=>$key],['enabled'=>in_array($key,$selected,true)]);
            CatalogAudit::record('catalog.user.saved',$user,['role'=>$user->role,'status'=>$user->status,'staff_active'=>$staffActive]);
        });
        if ($exists) $sessions->afterAuthorizationChange($r,$user,auth('cms')->id()===$user->id && $user->status!=='rejected');
        return redirect()->route('cms.admin.users.edit',$user)->with('catalog_status',__('admin_catalog.user_saved'));
    }
    public function revokeSession(Request $r,CmsUser $user,CmsAuthenticatedSessionService $sessions)
    {
        $v=$r->validate(['session_id'=>'required|integer|min:1','reason'=>'required|string|min:3|max:120']);
        $sessions->revokeAsAdmin($r,$user,(int)$v['session_id'],$v['reason']);
        return redirect()->route('cms.admin.users.edit',$user)->with('catalog_status',__('admin_catalog.session_revoked'));
    }
}
