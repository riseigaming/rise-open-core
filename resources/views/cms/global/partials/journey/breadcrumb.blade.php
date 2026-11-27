<nav class="globalBreadcrumbs journeyBreadcrumb" aria-label="{{ __('journey.breadcrumb') }}"><a href="{{ route('cms.front.home') }}">{{ __('cms_public.shell.home') }}</a>@foreach($crumbs as $label=>$href)<span aria-hidden="true">/</span>@if($href)<a href="{{ $href }}">{{ $label }}</a>@else<span aria-current="page">{{ $label }}</span>@endif
@endforeach</nav>
