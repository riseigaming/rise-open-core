@extends('cms.global.index')
@push('head')<link rel="stylesheet" href="{{ asset('cms-assets/themes/global/catalog.css') }}">@endpush
@section('pageContent')
<section class="globalSurfacePage catalogPage"><div class="container py-4">

<article data-launch-state="{{ $state }}"><h1>{{ $game->name }}</h1><p role="alert">{{ $message }}</p><a class="globalSecondaryAction" href="{{ route('cms.games.index') }}">{{ __('catalog.back') }}</a></article></div></section>
@endsection
