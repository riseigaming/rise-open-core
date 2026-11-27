@extends('cms.admin.index')

@section('title', __('content_pages.title'))

@section('content')
<div class="container-fluid py-3">
    <h1>{{ __('content_pages.title') }}</h1>
    <ul>
        @foreach ($pages as $item)
            <li>
                <a href="{{ route('cms.admin.content-pages.edit', $item) }}">{{ $item->slug }}</a>
                <span>{{ __('content_pages.'.($item->published ? 'published' : 'draft')) }}</span>
            </li>
        @endforeach
    </ul>
    {{ $pages->links() }}
    <h2>{{ __('content_pages.create') }}</h2>
    @include('cms.admin.content-pages.form', ['page' => new \App\Models\CmsContentPage()])
</div>
@endsection
