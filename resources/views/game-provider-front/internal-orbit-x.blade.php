<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $payload['gameName'] ?? 'Orbit X' }}</title>
    <link rel="stylesheet" href="/internal-games/orbit-x/style.css">
</head>

<body>
@php
    $guestPreview = $payload['preview'] ?? false;
@endphp
@if($guestPreview)
    @include('game-provider-front.partials.guest-preview')
    <div inert data-preview-surface>
@endif
<div id="wrapper">
    <div id="mid-wrapper">

        <a href="{{ route('cms.games.detail', ['slug' => $session->game->slug]) }}">← {{ $payload['gameName'] }}</a>
        <div id="header">
            <div id="last-counters"></div>
            <div>
                <div id="balance">BALANCE: </div>
                <div id="balance-amount">
                    <span id="balanceValue">{{ $guestPreview ? '—' : number_format((float) $payload['balance'], 2) }}</span>
                    <div style="position: absolute; top: 5px; right: 10px; font-size: 13px; color: #ffffff;" id="currencyValue">{{ $payload['currency'] }}</div>
                </div>
                <div id="mode-chip">{{ $guestPreview ? __('catalog.guest_preview') : ($payload['isDemo'] ? 'Demo · virtual credits' : 'Real wallet') }}</div>
            </div>

        </div>

        <div id="counterWrapper">
            <div id="bg-image"></div>
            <canvas id="canvas"></canvas>
            <div id="counter">1.00</div>
        </div>

        <div id="lanes" role="group" aria-label="Bet lane"><button type="button" data-orbit-lane="1">Lane 1</button><button type="button" data-orbit-lane="2">Lane 2</button></div>
        <div id="bottom-wrapper">
            <div id="input-wrapper">
                <input type="number" id="bet-input" min="0" max="1000" pattern="[0-9]{2}" />
                <div style="position: absolute; top: 10px; right: 10px; font-size: 19px; color: #ffffff;" id="inputCurrency">{{ $payload['currency'] }}</div>
            </div>
            <button id="bet-button">BET</button>
        </div>

        <div id="message-button">
            <p id="message"></p>
        </div>

        <div id="round-history" aria-label="Your rounds"></div>
        <div class="sr-only">snapshotRoute CASH OUT</div>
    </div>
</div>

@if($guestPreview)
    </div>
@else
<script>
    window.__ORBIT_X_BOOT__ = @json($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
</script>
<script src="/internal-games/orbit-x/script.js"></script>
@endif
</body>

</html>
