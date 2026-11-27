<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#131416">
    <title>Dice · Rise</title>
    <link rel="stylesheet" href="{{ asset('internal-games/dice/dice.css') }}?v={{ filemtime(public_path('internal-games/dice/dice.css')) }}">
</head>
<body>
@php
    $guestPreview = $payload['preview'] ?? false;
@endphp
@if($guestPreview)
    @include('game-provider-front.partials.guest-preview')
@endif
<nav class="dice-back"><a href="{{ route('cms.games.detail','dice') }}">← {{ __('catalog.back') }}</a> · <a href="{{ route('cms.wallet.index') }}">Wallet / transactions</a></nav>
<main class="dice" data-dice>
    <header class="dice-header">
        <div class="dice-balance" aria-label="{{ $payload['isDemo'] ? trans('dice.balance') : trans('dice.real_balance') }}">
            <svg class="dice-wallet" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 8V6a3 3 0 0 0-3-3H6a4 4 0 0 0-4 4v10a4 4 0 0 0 4 4h11a3 3 0 0 0 3-3v-1h-5a4 4 0 0 1 0-8h5Zm-5 2a3 3 0 0 0 0 6h7v-6h-7ZM6 6h10v2H6V6Zm9 6h2v2h-2v-2Z"/></svg>
            <strong data-balance>{{ $guestPreview ? '—' : number_format($payload['balance'], 2, '.', '') }}</strong><span class="dice-unit">{{ $payload['currency'] }}</span>
        </div>
        <div class="dice-brand"><span class="dice-rise">Rise</span><span class="dice-brand-cross">×</span><h1>DICE</h1><span class="dice-demo">{{ $guestPreview ? __('catalog.guest_preview') : ($payload['isDemo'] ? trans('dice.demo') : trans('dice.real')) }}</span></div>
        <div class="dice-tools">
            <button class="dice-icon-button dice-sound" type="button" data-sound @disabled($guestPreview) aria-pressed="false" aria-label="{{ trans('dice.sound') }}" title="{{ trans('dice.sound') }}">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m11 4-5 4H3v8h3l5 4V4Z"/><path class="dice-sound-wave" d="M15 8a6 6 0 0 1 0 8m3-11a10 10 0 0 1 0 14" fill="none" stroke="currentColor" stroke-width="2"/><path class="dice-sound-off" d="m15 9 6 6m0-6-6 6" fill="none" stroke="currentColor" stroke-width="2"/></svg>
            </button>
            <details class="dice-settings">
                <summary class="dice-icon-button" aria-label="{{ trans('dice.settings') }}" title="{{ trans('dice.settings') }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m10 2-.6 3-2 .9-2.6-1.5-2 3.5L5 10v3l-2.2 2.1 2 3.5L7.4 17l2 .9.6 3h4l.6-3 2-.9 2.6 1.5 2-3.5L19 13v-3l2.2-2.1-2-3.5L16.6 6l-2-.9L14 2h-4Zm2 6a4 4 0 1 1 0 8 4 4 0 0 1 0-8Z"/></svg></summary>
                <div class="dice-settings-content">
                    <h2>{{ trans('dice.settings') }}</h2>
                    @unless($guestPreview)<label><span>{{ trans('dice.animation') }}</span><input type="checkbox" data-animation checked></label>@endunless
                    <p>{{ trans('dice.rules') }}</p>
                    <p>{{ trans('dice.rng') }}</p>
                    <dl><div><dt>{{ trans('dice.rtp') }}</dt><dd>{{ number_format($payload['rtpUnits'] / 100, 2) }}%</dd></div><div><dt>{{ trans('dice.limits') }}</dt><dd>{{ $payload['limits']['minBet'] }}–{{ $payload['limits']['maxBet'] }} {{ $payload['currency'] }}</dd></div>@if(!$payload['isDemo'])<div><dt>{{ trans('dice.max_payout') }}</dt><dd>{{ number_format($payload['maxPayoutMinor'] / 100, 2) }} {{ $payload['currency'] }}</dd></div>@endif</dl>
                </div>
            </details>
        </div>
    </header>
    <div class="dice-layout">
        <form class="dice-controls" data-controls novalidate @if($guestPreview) inert data-preview-surface @endif>
            <section class="dice-panel dice-stake-panel">
                <label class="dice-visually-hidden" for="dice-stake">{{ trans('dice.bet_amount') }}</label>
                <div class="dice-stake">
                    <input id="dice-stake" type="text" inputmode="decimal" autocomplete="off" spellcheck="false" value="0.20" data-stake aria-describedby="dice-credit-label">
                    <span class="dice-unit">{{ $payload['currency'] }}</span>
                    <button type="button" data-adjust="minus" aria-label="{{ trans('dice.decrease') }}">−</button>
                    <button type="button" data-adjust="plus" aria-label="{{ trans('dice.increase') }}">+</button>
                </div>
                <div class="dice-stake-shortcuts"><button type="button" data-adjust="half" aria-label="{{ trans('dice.half') }}">/2</button><button type="button" data-adjust="double" aria-label="{{ trans('dice.double') }}">×2</button></div>
                <button type="submit" class="dice-roll" data-roll><span data-roll-label>{{ trans('dice.bet') }}</span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 2 10 5.5v9L12 22 2 16.5v-9L12 2Zm0 2.3L5 8l7 3.7L19 8l-7-3.7Zm-8 5.4v5.6l7 3.9v-5.8L4 9.7Zm9 3.7v5.8l7-3.9V9.7l-7 3.7Z"/></svg></button>
            </section>
            <section class="dice-panel dice-target-panel">
                <div class="dice-range-row"><span class="dice-target-caption" aria-hidden="true">◆ <span data-target-small>50</span></span><label class="dice-visually-hidden" for="dice-target">{{ trans('dice.target') }}</label><input type="range" id="dice-target" min="3" max="99" step="0.01" value="50" data-target></div>
                <div class="dice-mode" role="group" aria-label="{{ trans('dice.target') }}"><button type="button" data-mode="under" aria-pressed="false"><span aria-hidden="true">⌄</span>{{ trans('dice.under') }}</button><button type="button" data-mode="over" aria-pressed="true"><span aria-hidden="true">⌃</span>{{ trans('dice.over') }}</button></div>
            </section>
            <aside class="dice-panel dice-demo-note"><span class="dice-demo-note-tag" id="dice-credit-label">{{ $payload['isDemo'] ? trans('dice.credits') : trans('dice.real_balance') }}</span><p>{{ $guestPreview ? __('catalog.guest_preview_note') : ($payload['isDemo'] ? trans('dice.demo_note') : trans('dice.real_note')) }}</p><span class="dice-session-label">Rise · Rise Internal Games</span></aside>
        </form>
        <section class="dice-stage" aria-label="Dice">
            <div class="dice-stage-pattern" aria-hidden="true"></div>
            <div class="dice-readouts">
                <div class="dice-readout"><strong data-target-large>50<span>.00</span></strong><span>{{ trans('dice.target') }}</span></div>
                <div class="dice-cube" aria-hidden="true"><svg viewBox="0 0 64 64"><path d="m32 3 25 14v29L32 61 7 46V17L32 3Zm0 7L14 20l18 10 18-10-18-10ZM13 26v16l16 9V35l-16-9Zm22 9v16l16-9V26l-16 9Z"/></svg></div>
                <div class="dice-readout dice-result-readout"><strong data-result>—</strong><span>{{ trans('dice.rolled') }}</span></div>
            </div>
            <div class="dice-stats"><div><strong data-multiplier>×1.96</strong><span>{{ trans('dice.multiplier') }}</span></div><div><strong data-payout>{{ $guestPreview ? '—' : '0.39 '.$payload['currency'] }}</strong><span>{{ trans('dice.payout') }}</span></div><div><strong data-chance>50%</strong><span>{{ trans('dice.chance') }}</span></div></div>
            <p class="dice-status" data-status role="status" aria-live="polite">{{ trans('dice.ready') }}</p>
        </section>
        <section class="dice-history">
            <header><h2>{{ trans('dice.history') }}</h2><button type="button" class="dice-icon-button" data-refresh aria-label="{{ trans('dice.refresh') }}" title="{{ trans('dice.refresh') }}">↻</button></header>
            <div class="dice-history-table"><table><thead><tr><th>{{ trans('dice.result') }}</th><th>{{ trans('dice.bet_amount') }}</th><th>{{ trans('dice.multiplier') }}</th><th>{{ trans('dice.return') }}</th></tr></thead><tbody data-history></tbody></table><p class="dice-history-empty" data-empty>{{ trans('dice.history_empty') }}</p></div>
        </section>
    </div>
</main>
@unless($guestPreview)
<script type="application/json" data-dice-payload>{!! json_encode($payload + ['copy' => trans('dice')], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
<script src="{{ asset('internal-games/dice/dice.js') }}?v={{ filemtime(public_path('internal-games/dice/dice.js')) }}" defer></script>
@endif
</body>
</html>
