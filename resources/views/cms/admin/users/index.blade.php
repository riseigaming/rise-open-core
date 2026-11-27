@extends('cms.admin.index')
@section('title', __('admin_catalog.users'))
@section('content')
<div class="container-fluid py-3"><h1 class="h2 mb-3">{{ __('admin_catalog.users') }}</h1>@include('cms.admin.catalog.notice')

<div class="card"><div class="card-body"><div class="d-flex flex-wrap gap-2 justify-content-between mb-3"><form method="get" class="d-flex gap-2"><input name="q" value="{{ request('q') }}" aria-label="{{ __('admin_catalog.search_users') }}" placeholder="{{ __('admin_catalog.user_or_email') }}" class="form-control"><button class="btn btn-outline-primary">Filtrele</button></form><a class="btn btn-primary" href="{{ route('cms.admin.users.create') }}">{{ __('admin_catalog.add_user') }}</a></div><div class="table-responsive"><table class="table"><thead><tr><th>{{ __('admin_catalog.user') }}</th><th>{{ __('admin_catalog.email') }}</th><th>{{ __('admin_catalog.role') }}</th><th>{{ __('admin_catalog.status') }}</th><th>{{ __('admin_catalog.type_short') }}</th><th></th></tr></thead><tbody>@forelse($users as $u)<tr data-user-id="{{ $u->id }}"><td>{{ $u->username }}</td><td>{{ $u->email }}</td><td>{{ $u->role }}</td><td>{{ $u->status }}</td><td>{{ $u->userType?->name }}</td><td><a href="{{ route('cms.admin.users.edit',$u) }}">{{ __('admin_catalog.edit') }}</a></td></tr>@empty<tr><td colspan="6">{{ __('admin_catalog.no_records') }}</td></tr>@endforelse</tbody></table></div>{{ $users->links() }}</div></div>

</div>
@endsection
