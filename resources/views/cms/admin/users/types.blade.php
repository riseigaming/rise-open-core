@extends('cms.admin.index')
@section('title', __('admin_catalog.user_types'))
@section('content')
<div class="container-fluid py-3"><h1 class="h2 mb-3">{{ __('admin_catalog.user_types') }}</h1>@include('cms.admin.catalog.notice')

<div class="card"><div class="card-body"><form class="row align-items-end mb-4" method="post" action="{{ route('cms.admin.userTypes.store') }}">@csrf<div class="col-md-4"><label class="form-label" for="name">{{ __('admin_catalog.new_type_name') }}</label><input class="form-control" id="name" name="name" required></div><div class="col-md-6"><label class="form-label" for="description">{{ __('admin_catalog.description') }}</label><input class="form-control" id="description" name="description"></div><div class="col-md-2"><button class="btn btn-primary">{{ __('admin_catalog.add_type') }}</button></div></form>
@foreach($types as $type)<form class="row align-items-end mb-3" method="post" action="{{ route('cms.admin.userTypes.update',$type) }}">@csrf<div class="col-md-4"><label class="form-label">{{ __('admin_catalog.type_short') }}<input class="form-control" name="name" value="{{ $type->name }}" @if(array_key_exists($type->name,\App\Models\CmsUserType::coreDefinitions())) readonly @endif></label></div><div class="col-md-6"><label class="form-label">{{ __('admin_catalog.description') }}<input class="form-control" name="description" value="{{ $type->description }}" @if(array_key_exists($type->name,\App\Models\CmsUserType::coreDefinitions())) readonly @endif></label></div>@unless(array_key_exists($type->name,\App\Models\CmsUserType::coreDefinitions()))<div class="col-md-2"><button class="btn btn-outline-primary">{{ __('admin_catalog.save') }}</button></div>@endunless</form>@endforeach</div></div>

</div>
@endsection
