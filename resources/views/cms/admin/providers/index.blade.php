@extends('cms.admin.index')
@section('title', __('admin_catalog.providers'))
@section('content')
<div class="container-fluid py-3"><a href="{{ route('cms.admin.externalGames.edit') }}">External Games / import / controls</a><h1 class="h2 mb-3">{{ __('admin_catalog.providers') }}</h1>
@include('cms.admin.catalog.notice')

<div class="card"><div class="card-body">
<div class="d-flex flex-wrap gap-3 justify-content-between mb-3"><form method="get" class="d-flex gap-2 flex-wrap"><label class="visually-hidden" for="q">{{ __('admin_catalog.search_provider') }}</label><input class="form-control w-auto" id="q" name="q" value="{{ request('q') }}" placeholder="{{ __('admin_catalog.search_provider') }}"><select name="status" class="form-select w-auto" aria-label="Durum filtresi"><option value="">{{ __('admin_catalog.all_statuses') }}</option><option value="active" @selected(request('status')==='active')>{{ __('admin_catalog.active') }}</option><option value="passive" @selected(request('status')==='passive')>{{ __('admin_catalog.hidden') }}</option></select><button class="btn btn-outline-primary">Filtrele</button></form><a class="btn btn-primary" href="{{ route('cms.admin.providers.create') }}">{{ __('admin_catalog.add_provider') }}</a></div>
<div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>{{ __('admin_catalog.provider') }}</th><th>{{ __('admin_catalog.type') }}</th><th>{{ __('admin_catalog.status') }}</th><th>{{ __('admin_catalog.visibility') }}</th><th>{{ __('admin_catalog.game') }}</th><th>{{ __('admin_catalog.rank') }}</th><th></th></tr></thead><tbody>
@forelse($providers as $provider)<tr data-provider-id="{{ $provider->id }}"><td>{{ $provider->name }}</td><td>{{ $provider->kind }}</td><td>{{ $provider->is_maintenance ? __('admin_catalog.under_maintenance') : $provider->status }}</td><td>{{ $provider->show_left_panel ? __('admin_catalog.visible') : __('admin_catalog.hidden') }}</td><td>{{ $provider->games_count }}</td><td>{{ $provider->rank }}</td><td><a href="{{ route('cms.admin.providers.edit',$provider) }}">{{ __('admin_catalog.edit') }}</a></td></tr>@empty<tr><td colspan="7">{{ __('admin_catalog.no_records') }}</td></tr>@endforelse
</tbody></table></div>{{ $providers->links() }}</div></div>

</div>
@endsection
