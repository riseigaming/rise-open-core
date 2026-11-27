@extends('cms.admin.index')
@section('title', __('admin_catalog.provider_record'))
@section('content')
<div class="container-fluid py-3"><h1 class="h2 mb-3">{{ __('admin_catalog.provider_record') }}</h1>
@include('cms.admin.catalog.notice')

<div class="card"><div class="card-body"><form id="provider-form" method="post" action="{{ $provider->exists ? route('cms.admin.providers.update',$provider) : route('cms.admin.providers.store') }}">@csrf
<div class="row">@foreach(['name'=>__('admin_catalog.provider_name'),'slug'=>__('admin_catalog.immutable_key'),'description'=>__('admin_catalog.description'),'rank'=>__('admin_catalog.rank')] as $field=>$label)<div class="col-md-6 mb-3"><label class="form-label" for="{{ $field }}">{{ $label }}</label><input id="{{ $field }}" name="{{ $field }}" class="form-control" value="{{ old($field,$provider->$field ?? ($field==='rank'?0:'')) }}" @if($field==='rank') type="number" min="0" @else type="text" @endif @if(in_array($field,['name','slug'])) required @endif @if($provider->exists && $field==='slug') readonly @endif></div>@endforeach
<div class="col-md-6 mb-3"><label class="form-label" for="kind">{{ __('admin_catalog.catalog_type') }}</label><select class="form-select" id="kind" name="kind" @if($provider->exists) disabled @endif><option value="external" @selected(old('kind',$provider->kind)==='external')>External</option><option value="internal" @selected(old('kind',$provider->kind)==='internal')>Internal</option></select>@if($provider->exists)<input type="hidden" name="kind" value="{{ $provider->kind }}">@endif</div>
@foreach(['status'=>__('admin_catalog.status'),'show_left_panel'=>__('admin_catalog.show_in_catalog'),'is_maintenance'=>__('admin_catalog.maintenance')] as $field=>$label)<div class="col-md-4 mb-3"><label class="form-label" for="{{ $field }}">{{ $label }}</label><select class="form-select" id="{{ $field }}" name="{{ $field }}">@foreach($field==='status'?['active'=>__('admin_catalog.active'),'passive'=>__('admin_catalog.hidden')]:['1'=>__('admin_catalog.yes'),'0'=>__('admin_catalog.no')] as $value=>$text)<option value="{{ $value }}" @selected((string)old($field,$field==='status'?$provider->$field:(int)(bool)$provider->$field)===(string)$value)>{{ $text }}</option>@endforeach</select></div>@endforeach</div>
<p class="text-muted">{{ __('admin_catalog.provider_setup_help') }}</p><button class="btn btn-primary" type="submit">{{ __('admin_catalog.save') }}</button> <a class="btn btn-outline-secondary" href="{{ route('cms.admin.providers.index') }}">{{ __('admin_catalog.back_to_list') }}</a></form></div></div>

</div>
@endsection
