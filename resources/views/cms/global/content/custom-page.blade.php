@extends('cms.global.index', ['contentPageTitle' => $copy['title']])

@section('pageContent')
<section class="globalSurfacePage">
    <article class="container py-4" data-global-content-page>
        <h1>{{ $copy['title'] }}</h1>
        {!! \App\Support\CmsContentHtml::render($copy['body']) !!}
    </article>
</section>
@endsection
