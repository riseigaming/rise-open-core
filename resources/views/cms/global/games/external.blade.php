@extends('cms.global.index')
@section('pageContent')
<section class="container py-4" data-external-session="{{ $session?->id }}" data-external-mode="{{ $mode }}">
<a href="{{ route('cms.games.detail',$game->slug) }}">← {{ $game->name }}</a>
<h1>{{ $game->name }}</h1><p>{{ $mode==='demo'?'Demo · no financial effect':'Real wallet · synthetic local funds' }}</p>
<iframe title="{{ $game->name }}" data-external-frame src="{{ $url }}" sandbox="{{ implode(' ',config('external_games.iframe.default_sandbox_capabilities',[])) }}" referrerpolicy="no-referrer" style="width:100%;height:70vh;border:0"></iframe>
<p role="status">Provider connection: if the game does not load, return to the game detail and retry. A launch does not debit your wallet.</p>
<a href="{{ route('cms.wallet.index') }}">Wallet / transactions / ledger</a>
</section>
@endsection
