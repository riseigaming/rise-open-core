@extends('cms.global.index')
@push('head')<link rel="stylesheet" href="{{ asset('assets/cms/css/sportsbook-shell-v2.css') }}">@endpush
@section('pageContent')
<section class="globalSurfacePage" style="padding:24px 0"><div class="container"><h1>Sportsbook</h1><p data-sportsbook-mode>{{ $mode==='A'?'A · Synthetic snapshot · read-only':($mode==='B'?'B · Own adapter HTTP · read-only':'C · Local synthetic wallet') }}</p><nav><a href="?mode=A">A snapshot</a> · <a href="?mode=B">B adapter</a> · <a href="?mode=C">C local wallet</a> · <a href="{{ route('cms.front.sportsbook.history') }}">My bets / Kuponlarım</a></nav></div>
<div class="sportsbook-native-shell sb2-page sb2-page--framed" id="sportsbookSurface">@include('cms.shared.sportsbook.shell-v2-content')</div>
</section>
@endsection
