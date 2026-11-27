@php
    $catalogPermissions=app(\App\Services\CmsStaff\CmsStaffPermissionService::class);
    $catalogActor=auth('cms')->user();
    $catalogAdmin=$catalogPermissions->isAdmin($catalogActor);
    $catalogGroups=[
      __('admin_catalog.game_management')=>[
        ['cms.admin.providers.index',__('admin_catalog.providers'),'provider',\App\Services\CmsStaff\CmsStaffPermissionService::CATALOG_PROVIDERS],
        ['cms.admin.games.index',__('admin_catalog.game_catalog'),'games',\App\Services\CmsStaff\CmsStaffPermissionService::CATALOG_GAMES],
        ['cms.admin.games.curation',__('admin_catalog.curation'),'queue',\App\Services\CmsStaff\CmsStaffPermissionService::CATALOG_CURATION]],
      __('admin_catalog.accounts_access')=>[['cms.admin.users.index',__('admin_catalog.users'),'registers',null],['cms.admin.userTypes.index',__('admin_catalog.user_types'),'users',null],['cms.admin.restrictions.index',__('admin_catalog.country_restrictions'),'shield',null]],
      __('admin_catalog.content')=>[['cms.admin.home',__('admin.dashboard.title'),'overview',null],['cms.admin.content-pages.index',__('content_pages.title'),'overview',null],['cms.admin.menus.edit',__('admin.b1.menus'),'menu',null],['cms.admin.settings.getSiteSettings',__('admin.b1.settings'),'filters',null],['cms.admin.languages.index',__('admin.b1.languages'),'language',null],['cms.admin.sliders.index',__('admin.b1.sliders'),'image',null],['cms.admin.promotions.index',__('admin.b1.promotions'),'approvedPromotions',null]],
    ];
    if ($catalogPermissions->has($catalogActor, 'catalog.providers.manage')
        || $catalogPermissions->has($catalogActor, 'finance.manual.override')) {
        $catalogGroups['Ops Radar'] = [['cms.admin.opsRadar.index', 'Ops Radar', 'overview',
            $catalogPermissions->has($catalogActor, 'catalog.providers.manage') ? 'catalog.providers.manage' : 'finance.manual.override']];
    }
    if (config('nova_payments.enabled')) {
        $catalogGroups['Nova'] = [['cms.admin.finance.nova', 'Nova', 'finance', 'finance.manual.override']];
    }
@endphp
@foreach($catalogGroups as $groupLabel=>$entries)
    @php($entries=array_values(array_filter($entries,fn($e)=>$e[3] ? $catalogPermissions->has($catalogActor,$e[3]) : $catalogAdmin)))
    @if(count($entries))<li class="nav-item catalog-nav-group" role="presentation"><span>{{ $groupLabel }}</span></li>@endif
    @foreach($entries as [$destination,$label,$icon,$permission])
    <li class="nav-item" @if($destination==='cms.admin.home') id="adminDashboardRow" @endif><a class="nav-link {{ (request()->routeIs($destination) || (str_ends_with($destination,'.index') && request()->routeIs(substr($destination,0,-6).'.*') && !request()->routeIs('cms.admin.games.curation*'))) ? 'activeSideItem' : '' }}" href="{{ route($destination) }}" title="{{ $label }}"><span class="nav-link-icon">@include('cms.admin.partials.dashboard-icon',['name'=>$icon])</span><span class="nav-link-title">{{ $label }}</span></a></li>
    @endforeach
@endforeach
