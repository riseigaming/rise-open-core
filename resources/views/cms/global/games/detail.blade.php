@extends('cms.global.index')
@push('head')<link rel="stylesheet" href="{{ asset('cms-assets/themes/global/catalog.css') }}">@endpush
@section('pageContent')
<section class="globalSurfacePage catalogPage"><div class="container py-4">

@include('cms.global.partials.journey.breadcrumb',['crumbs'=>[__('journey.catalog')=>route('cms.games.index',$filters),$game->name=>null]])
<a data-catalog-return href="{{ route('cms.games.index',$filters) }}">{{ __('journey.back') }}</a><article class="catalogDetail" data-game-id="{{ $game->id }}"><img src="{{ $game->imageUrl() }}" alt=""><div><small><a href="{{ route('cms.games.index',['provider'=>$game->provider_id]) }}">{{ $game->provider->name }}</a> · <a href="{{ route('cms.games.index',['type'=>$game->game_type]) }}">{{ __('journey.types.'.$game->game_type) }}</a></small><h1>{{ $game->name }}</h1><p>{{ $game->description }}</p><p role="status">{{ __($game->launchState() === 'demo' ? 'catalog.guest_preview' : 'catalog.'.$game->launchState()) }}</p>@if($game->launchState()==='chess')
<a class="globalPrimaryAction" data-launch-chess href="{{ route('cms.front.providers.chess') }}">{{ auth('cms')->check() ? 'ChessBet · canonical wallet' : 'ChessBet · guest' }}</a>
<a href="{{ route('cms.wallet.index') }}">Wallet / transactions</a>
@elseif($game->launchState()==='external')
@if(app(\App\Services\ExternalGames\ExternalGameDemoService::class)->available($game))<form method="post" action="{{ route('cms.games.launch',$game->slug) }}">@csrf<input type="hidden" name="playType" value="demo"><button data-launch-demo>Demo</button></form>@else<p>Demo unavailable for this game.</p>@endif
@php($externalCapability=app(\App\Services\ExternalGames\ExternalGameDiscoveryReadService::class)->realCapability($game,auth('cms')->user()))
@if($externalCapability['available'])<form method="post" action="{{ route('cms.games.launch',$game->slug) }}">@csrf<input type="hidden" name="playType" value="real"><button data-launch-real>Real wallet</button></form>@else<p role="status">{{ $externalCapability['reason'] }}</p>@endif
@elseif(in_array($game->launchState(),['demo','available']))
@if(app(\App\Services\InternalGames\InternalGameDemoModeService::class)->isInternalDemoSupported($game))
<form method="post" action="{{ route('cms.games.launch',$game->slug) }}">@csrf<input type="hidden" name="playType" value="demo"><button class="globalPrimaryAction" data-launch-demo>{{ __('catalog.' . (auth('cms')->check() ? 'demo_wallet' : 'guest_preview')) }}</button></form>
@endif
@if(auth('cms')->check())
<form method="post" action="{{ route('cms.games.launch',$game->slug) }}">@csrf<input type="hidden" name="playType" value="real"><button class="globalPrimaryAction" data-launch-real>Real wallet · local synthetic funds</button></form>
<a href="{{ route('cms.wallet.index') }}">Wallet / transactions</a>
@endif
@else<form method="post" action="{{ route('cms.games.launch',$game->slug) }}">@csrf<button class="globalPrimaryAction">{{ __('catalog.launch') }}</button></form>@endif</div></article><nav class="journeyRelated"><a href="{{ route('cms.front.learnGuide','catalog-discovery') }}">{{ __('journey.guides.catalog-discovery.title') }}</a></nav></div></section>
@endsection
