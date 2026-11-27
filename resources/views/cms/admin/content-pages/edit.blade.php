@extends('cms.admin.index')

@section('title', __('content_pages.title'))

@section('content')
<div class="container-fluid py-3">
    <h1>{{ $page->slug }}</h1>
    <p><a href="{{ route('cms.admin.content-pages.index') }}">{{ __('content_pages.title') }}</a></p>
    @if (session('page_saved'))
        <div class="alert alert-success" role="status">{{ __('content_pages.saved') }}</div>
    @endif
    @if ($page->published)
        <p><a data-public-page href="{{ route('cms.staticPage', $page->slug) }}">{{ __('content_pages.view') }}</a></p>
    @endif
    @include('cms.admin.content-pages.form')
</div>
@endsection
