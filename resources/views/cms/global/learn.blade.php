@extends('cms.global.index')
@section('pageContent')
<section class="globalSurfacePage journeyPage" data-global-learn>
@include('cms.global.partials.journey.breadcrumb',['crumbs'=>$slug ? [__('journey.learn')=>route('cms.front.learnCenter'),$guides[$slug]['title']=>null] : [__('journey.learn')=>null]])
@if($slug)
<article data-guide="{{ $slug }}"><header class="globalSurfaceIntro"><h1>{{ $guides[$slug]['title'] }}</h1><p>{{ $guides[$slug]['summary'] }}</p></header>
<nav class="journeyToc" aria-label="{{ __('journey.contents') }}">@foreach($guides[$slug]['sections'] as $section)<a href="#section-{{ $loop->index }}">{{ $section['title'] }}</a>@endforeach</nav>
@foreach($guides[$slug]['sections'] as $section)<section class="journeySection" id="section-{{ $loop->index }}"><h2>{{ $section['title'] }}</h2><p>{{ $section['body'] }}</p></section>@endforeach
<nav class="journeyRelated" aria-label="{{ __('journey.related') }}"><h2>{{ __('journey.related') }}</h2>@foreach(\App\Support\GlobalGuides::links($slug) as $label=>$href)<a class="globalSecondaryAction" href="{{ $href }}">{{ $label }}</a>@endforeach</nav></article>
@else<header class="globalSurfaceIntro"><h1>{{ __('journey.learn') }}</h1><p>{{ __('journey.learn_intro') }}</p></header>@endif
<div class="journeyCards">@foreach($guides as $key=>$guide)@if($slug!==$key)<article class="globalFinancePanel"><h2><a href="{{ route('cms.front.learnGuide',$key) }}">{{ $guide['title'] }}</a></h2><p>{{ $guide['summary'] }}</p><a href="{{ route('cms.front.learnGuide',$key) }}">{{ __('journey.read') }} →</a></article>@endif
@endforeach</div>
@include('cms.global.partials.journey.information-links')
</section>
@endsection
