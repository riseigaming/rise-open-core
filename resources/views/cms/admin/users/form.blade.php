@extends('cms.admin.index')
@section('title', __('admin_catalog.user_record'))
@section('content')
@if($user->exists && $user->role==='customer' && app(\App\Services\CmsStaff\CmsStaffPermissionService::class)->has(auth('cms')->user(),'finance.manual.override'))<div class="container-fluid pt-3"><a href="{{ route('cms.admin.transactions.manualOverride.create',['customer_id'=>$user->id]) }}">Canonical wallet / manual finance / ledger</a></div>@endif
<div class="container-fluid py-3"><h1 class="h2 mb-3">{{ __('admin_catalog.user_record') }}</h1>@include('cms.admin.catalog.notice')

<div class="card mb-3"><div class="card-body"><form id="user-form" method="post" action="{{ $user->exists ? route('cms.admin.users.update',$user) : route('cms.admin.users.store') }}">@csrf<div class="row">
@foreach(['username'=>__('admin_catalog.username'),'email'=>__('admin_catalog.email'),'first_name'=>__('admin_catalog.first_name'),'last_name'=>__('admin_catalog.last_name')] as $field=>$label)<div class="col-md-6 mb-3"><label class="form-label" for="{{ $field }}">{{ $label }}</label><input class="form-control" id="{{ $field }}" name="{{ $field }}" value="{{ old($field,$user->$field) }}" @if(in_array($field,['username','email'])) required @endif></div>@endforeach
<div class="col-md-6 mb-3"><label class="form-label" for="password">{{ $user->exists ? __('admin_catalog.new_password') : __('admin_catalog.password') }}</label><input class="form-control" type="password" autocomplete="new-password" id="password" name="password" @unless($user->exists) required @endunless></div>
@foreach(['role'=>['admin'=>__('admin_catalog.administrator'),'customer'=>__('admin_catalog.customer_staff')],'status'=>['normal'=>'Normal','accepted'=>__('admin_catalog.approved'),'fully_accepted'=>__('admin_catalog.fully_approved'),'rejected'=>__('admin_catalog.disabled')]] as $field=>$options)<div class="col-md-3 mb-3"><label class="form-label" for="{{ $field }}">{{ $field==='role'?__('admin_catalog.role'):__('admin_catalog.status') }}</label><select class="form-select" id="{{ $field }}" name="{{ $field }}">@foreach($options as $value=>$text)<option value="{{ $value }}" @selected(old($field,$user->$field)===$value)>{{ $text }}</option>@endforeach</select></div>@endforeach
<div class="col-md-6 mb-3"><label class="form-label" for="country_id">{{ __('admin_catalog.country') }}</label><select class="form-select" id="country_id" name="country_id" required>@foreach($countries as $c)<option value="{{ $c->id }}" @selected((string)old('country_id',$user->country_id)===(string)$c->id)>{{ $c->name }} · {{ $c->iso2 }}</option>@endforeach</select></div><div class="col-md-6 mb-3"><label class="form-label" for="user_type_id">{{ __('admin_catalog.user_type') }}</label><select class="form-select" id="user_type_id" name="user_type_id" required>@foreach($types as $type)<option value="{{ $type->id }}" @selected((string)old('user_type_id',$user->user_type_id)===(string)$type->id)>{{ $type->name }}</option>@endforeach</select></div></div>
<h2 class="h3">{{ __('admin_catalog.staff_permissions') }}</h2><p class="text-muted">{{ __('admin_catalog.staff_help') }}</p><label class="form-label" for="staff_active">Staff profili aktif</label><select class="form-select mb-3" id="staff_active" name="staff_active"><option value="0" @selected(!old('staff_active',$staff?->active))>{{ __('admin_catalog.no') }}</option><option value="1" @selected(old('staff_active',$staff?->active))>{{ __('admin_catalog.yes') }}</option></select>
@foreach($permissionKeys as $key)<label class="form-check mb-2"><input type="checkbox" class="form-check-input" name="permissions[]" value="{{ $key }}" @checked(in_array($key,old('permissions',$enabledPermissions)))><span class="form-check-label">{{ ['cms.staff.access'=>__('admin_catalog.panel_access'),'catalog.providers.manage'=>__('admin_catalog.provider_management'),'catalog.games.manage'=>__('admin_catalog.game_management'),'catalog.curation.manage'=>__('admin_catalog.curation')][$key] }}</span></label>@endforeach
<button class="btn btn-primary mt-3" type="submit">{{ __('admin_catalog.save_user') }}</button> <a class="btn btn-outline-secondary mt-3" href="{{ route('cms.admin.users.index') }}">{{ __('admin_catalog.back_to_list') }}</a></form></div></div>
@if($user->exists)
<div class="card mb-3"><div class="card-body"><h2 class="h3">{{ __('admin_catalog.catalog_access') }}</h2><p>{{ __('admin_catalog.access_help') }}</p>
<form id="access-form" method="post" action="{{ route('cms.admin.users.access',$user) }}">@csrf<div class="row"><div class="col-md-3 mb-3"><label for="product" class="form-label">{{ __('admin_catalog.product') }}</label><select class="form-select" id="product" name="product"><option value="internal_games">Internal Games</option><option value="external_games">External Games</option><option value="sportsbook">Sportsbook</option></select></div><div class="col-md-3 mb-3"><label for="provider" class="form-label">{{ __('admin_catalog.provider_scope') }}</label><select class="form-select" id="provider" name="provider"><option value="">{{ __('admin_catalog.all_product') }}</option>@foreach($providers as $provider)<option value="{{ $provider->slug }}" data-product="{{ $provider->productKey() }}">{{ $provider->name }} ({{ $provider->kind }})</option>@endforeach</select></div><div class="col-md-3 mb-3"><label for="mode" class="form-label">{{ __('admin_catalog.access') }}</label><select class="form-select" id="mode" name="mode"><option value="deny">{{ __('admin_catalog.deny') }}</option><option value="allow">{{ __('admin_catalog.allow') }}</option><option value="default">{{ __('admin_catalog.remove_override') }}</option></select></div><div class="col-md-3 mb-3"><label for="reason" class="form-label">{{ __('admin_catalog.reason') }}</label><input class="form-control" id="reason" name="reason" required minlength="3"></div></div><button class="btn btn-outline-primary">{{ __('admin_catalog.save_access') }}</button></form>
<div class="table-responsive mt-3"><table class="table"><thead><tr><th>{{ __('admin_catalog.product') }}</th><th>{{ __('admin_catalog.provider') }}</th><th>{{ __('admin_catalog.decision') }}</th><th>{{ __('admin_catalog.reason') }}</th></tr></thead><tbody>@forelse($access as $a)<tr><td>{{ $a->product_key }}</td><td>{{ $a->provider_alias ?: __('admin_catalog.all') }}</td><td>{{ $a->access_mode }}</td><td>{{ $a->reason }}</td></tr>@empty<tr><td colspan="4">{{ __('admin_catalog.no_overrides') }}</td></tr>@endforelse</tbody></table></div></div></div>
<div class="card"><div class="card-body"><h2 class="h3">{{ __('admin_catalog.sessions') }}</h2><div class="table-responsive"><table class="table"><thead><tr><th>{{ __('admin_catalog.browser') }}</th><th>{{ __('admin_catalog.last_seen') }}</th><th>{{ __('admin_catalog.action') }}</th></tr></thead><tbody>@forelse($sessions as $s)<tr><td>{{ $s->browser_name }} · {{ $s->os_name }}</td><td>{{ $s->updated_at }}</td><td><form method="post" action="{{ route('cms.admin.users.sessions.revoke',$user) }}">@csrf<input type="hidden" name="session_id" value="{{ $s->id }}"><input type="hidden" name="reason" value="Catalog admin revocation"><button class="btn btn-sm btn-outline-danger">{{ __('admin_catalog.revoke_session') }}</button></form></td></tr>@empty<tr><td colspan="3">{{ __('admin_catalog.no_sessions') }}</td></tr>@endforelse</tbody></table></div></div></div>
@endif

</div>
@endsection

@section('script')
<script>
(function () {
    const product = document.getElementById('product');
    const provider = document.getElementById('provider');
    if (!product || !provider) return;
    function syncProviders() {
        for (const option of provider.options) {
            option.hidden = option.disabled = !!option.value && option.dataset.product !== product.value;
        }
        if (provider.selectedOptions[0]?.disabled) provider.value = '';
    }
    product.addEventListener('change', syncProviders);
    syncProviders();
})();
</script>
@endsection
