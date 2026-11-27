@foreach($items as $item)
@php($current=app(\App\Services\Cms\CmsNavigationService::class)->isCurrent($item['href']))
<div class="journeyNavItem">
<a href="{{ $item['href'] }}" target="{{ $item['target'] }}" rel="noopener noreferrer" @if($current) aria-current="page" @endif title="{{ $item['title'] ?? '' }}">{{ $item['text'] }}</a>
@if(!empty($item['children']))<div class="journeyNavChildren">@include('cms.global.partials.journey.nav-links',['items'=>$item['children']])</div>@endif
</div>
@endforeach
