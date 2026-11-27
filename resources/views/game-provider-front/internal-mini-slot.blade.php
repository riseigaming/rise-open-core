<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $payload['gameName'] }}</title>
    <style>
        :root {
            --bg: #071017;
            --shell: rgba(8, 13, 19, 0.9);
            --panel: rgba(15, 23, 31, 0.86);
            --panel-strong: rgba(10, 16, 23, 0.96);
            --surface: rgba(255, 255, 255, 0.045);
            --surface-strong: rgba(255, 255, 255, 0.08);
            --line: rgba(255, 211, 108, 0.16);
            --line-strong: rgba(255, 211, 108, 0.28);
            --gold: #ffd46a;
            --gold-strong: #ffaf29;
            --gold-soft: rgba(255, 212, 106, 0.18);
            --green: #7cffb0;
            --green-soft: rgba(124, 255, 176, 0.14);
            --red: #ff8d84;
            --muted: #9aabb9;
            --text: #f5f7fa;
            --shadow: 0 28px 80px rgba(0, 0, 0, 0.42);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            color: var(--text);
            font-family: "Trebuchet MS", "Segoe UI", sans-serif;
            background:
                radial-gradient(circle at top, rgba(255, 174, 43, 0.12), transparent 24%),
                radial-gradient(circle at 20% 20%, rgba(62, 150, 255, 0.08), transparent 20%),
                linear-gradient(180deg, #0d1720 0%, #04080d 100%);
            min-height: 100vh;
            padding: 14px;
        }

        .shell {
            width: min(1320px, 100%);
            margin: 0 auto;
            border-radius: 30px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.06);
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.03), rgba(255, 255, 255, 0)),
                var(--shell);
            box-shadow: var(--shadow);
        }

        .layout {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
            padding: 24px;
        }

        .stage,
        .card {
            border-radius: 26px;
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.03), rgba(255, 255, 255, 0.015)),
                var(--panel);
            border: 1px solid rgba(255, 255, 255, 0.06);
        }

        .stage {
            padding: 20px;
        }

        .machine {
            position: relative;
            border-radius: 24px;
            padding: 18px 18px 20px;
            background:
                radial-gradient(circle at top, rgba(255, 213, 79, 0.16), transparent 30%),
                linear-gradient(180deg, #24190c 0%, #100b06 100%);
            border: 1px solid rgba(255, 213, 79, 0.26);
            box-shadow:
                inset 0 0 0 1px rgba(255, 255, 255, 0.03),
                inset 0 18px 40px rgba(255, 209, 107, 0.08),
                0 22px 50px rgba(0, 0, 0, 0.26);
        }

        .machine::before {
            content: "";
            position: absolute;
            inset: 12px;
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.04);
            pointer-events: none;
        }

        .machine::after {
            content: "";
            position: absolute;
            inset: auto 22px -10px;
            height: 18px;
            border-radius: 999px;
            background: radial-gradient(circle, rgba(255, 189, 77, 0.28), transparent 70%);
            pointer-events: none;
        }

        .machine-body {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 320px;
            gap: 16px;
            align-items: start;
        }

        .machine-main {
            min-width: 0;
        }

        .cabinet-side {
            display: grid;
            gap: 12px;
        }

        .display-panel {
            border-radius: 20px;
            padding: 14px;
            background:
                linear-gradient(180deg, rgba(18, 35, 27, 0.98), rgba(7, 18, 14, 0.98)),
                #0d1e16;
            border: 1px solid rgba(132, 255, 191, 0.14);
            box-shadow:
                inset 0 0 0 1px rgba(171, 255, 210, 0.04),
                inset 0 0 22px rgba(0, 0, 0, 0.18),
                0 12px 24px rgba(0, 0, 0, 0.2);
        }

        .display-grid {
            display: grid;
            gap: 10px;
        }

        .display-row {
            display: grid;
            grid-template-columns: 1fr;
            gap: 5px;
            padding: 8px 10px;
            border-radius: 12px;
            background: rgba(0, 0, 0, 0.16);
            border: 1px solid rgba(132, 255, 191, 0.08);
        }

        .display-label {
            color: rgba(151, 255, 203, 0.72);
            font-size: 10px;
            letter-spacing: 0.18em;
            text-transform: uppercase;
        }

        .display-value {
            color: #b7ffd9;
            font-size: 22px;
            line-height: 1;
            letter-spacing: 0.08em;
            font-family: "Courier New", monospace;
            text-shadow: 0 0 14px rgba(104, 255, 170, 0.18);
            word-break: break-word;
        }

        .display-value.is-small {
            font-size: 16px;
        }

        .display-value.is-gold {
            color: #ffe07f;
            text-shadow: 0 0 14px rgba(255, 204, 87, 0.18);
        }

        .machine-screen {
            position: relative;
            overflow: hidden;
            border-radius: 20px;
            border: 1px solid rgba(255, 213, 79, 0.16);
            background:
                radial-gradient(circle at center, rgba(255, 255, 255, 0.08), transparent 36%),
                linear-gradient(180deg, #fff7dd 0%, #efd287 100%);
            box-shadow:
                inset 0 0 0 14px rgba(60, 36, 6, 0.42),
                inset 0 0 38px rgba(0, 0, 0, 0.18),
                0 14px 30px rgba(0, 0, 0, 0.18);
            transform: translateZ(0);
            transition: transform 260ms ease, box-shadow 260ms ease;
        }

        .machine-screen-wrap {
            padding: 10px;
            border-radius: 24px;
            background:
                radial-gradient(circle at top, rgba(255, 234, 180, 0.12), transparent 48%),
                linear-gradient(180deg, rgba(255, 243, 207, 0.08), rgba(0, 0, 0, 0));
        }

        .machine.is-spinning .machine-screen {
            transform: perspective(1200px) rotateX(1.5deg) scale(1.008);
            box-shadow:
                inset 0 0 0 14px rgba(60, 36, 6, 0.42),
                inset 0 0 38px rgba(0, 0, 0, 0.18),
                0 18px 42px rgba(0, 0, 0, 0.24);
        }

        .machine-screen::before,
        .machine-screen::after {
            content: "";
            position: absolute;
            inset: 0;
            pointer-events: none;
            z-index: 3;
        }

        .machine-screen::before {
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.44), transparent 18%, transparent 80%, rgba(96, 60, 15, 0.14));
        }

        .machine-screen::after {
            background:
                linear-gradient(180deg, rgba(0, 0, 0, 0.08) 0, rgba(0, 0, 0, 0) 8px, rgba(255, 255, 255, 0.02) 8px, rgba(255, 255, 255, 0.02) 10px);
            background-size: 100% 10px;
            opacity: 0.28;
        }

        #slotCanvas {
            display: block;
            width: 100%;
            height: auto;
            aspect-ratio: 9 / 7;
        }

        .win-banner {
            position: absolute;
            top: 16px;
            left: 50%;
            transform: translate(-50%, -14px);
            min-width: 210px;
            padding: 12px 16px;
            border-radius: 16px;
            text-align: center;
            background: linear-gradient(135deg, rgba(27, 50, 35, 0.94), rgba(18, 28, 22, 0.94));
            border: 1px solid rgba(124, 255, 176, 0.22);
            box-shadow: 0 18px 32px rgba(0, 0, 0, 0.28);
            opacity: 0;
            pointer-events: none;
            transition: opacity 220ms ease, transform 220ms ease;
            z-index: 5;
        }

        .win-banner.is-visible {
            opacity: 1;
            transform: translate(-50%, 0);
        }

        .win-banner small {
            display: block;
            font-size: 11px;
            color: rgba(124, 255, 176, 0.82);
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }

        .win-banner strong {
            display: block;
            margin-top: 6px;
            font-size: 24px;
            color: var(--green);
        }

        .card,
        .console-panel {
            padding: 18px;
        }

        .card h2 {
            margin: 0 0 14px;
            color: var(--muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }

        .pill-list,
        .round-meta {
            display: flex;
            flex-direction: column;
            gap: 10px;
            font-size: 14px;
        }

        .pill {
            display: inline-flex;
            align-items: center;
            width: fit-content;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.05);
            color: var(--muted);
        }

        .control-stack {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .console-panel {
            border-radius: 20px;
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.04), rgba(255, 255, 255, 0.015)),
                rgba(8, 13, 19, 0.42);
            border: 1px solid rgba(255, 255, 255, 0.06);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.03);
        }

        .spin-bar {
            padding: 14px;
            border-radius: 20px;
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.04), rgba(255, 255, 255, 0.015)),
                rgba(8, 13, 19, 0.42);
            border: 1px solid rgba(255, 255, 255, 0.06);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.03);
        }

        .bet-toolbar {
            display: grid;
            grid-template-columns: 54px minmax(0, 1fr) 54px;
            gap: 10px;
            align-items: center;
        }

        .bet-adjust {
            border: 0;
            border-radius: 16px;
            min-height: 50px;
            cursor: pointer;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.06);
            color: var(--text);
            font-size: 24px;
            line-height: 1;
        }

        .bet-display {
            border-radius: 18px;
            min-height: 56px;
            padding: 10px 14px;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.05), rgba(255, 255, 255, 0.02));
            border: 1px solid rgba(255, 255, 255, 0.06);
            text-align: center;
        }

        .bet-display small {
            display: block;
            color: var(--muted);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }

        .bet-display strong {
            display: block;
            margin-top: 6px;
            font-size: 24px;
            color: #fff8e8;
        }

        .bet-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
        }

        .bet-button,
        .spin-button,
        .bet-adjust {
            transition: transform 180ms ease, opacity 180ms ease, box-shadow 180ms ease, background 180ms ease;
        }

        .bet-button,
        .spin-button {
            border: 0;
            border-radius: 16px;
            cursor: pointer;
        }

        .bet-button {
            min-height: 46px;
            background: rgba(255, 255, 255, 0.045);
            border: 1px solid rgba(255, 255, 255, 0.06);
            color: var(--text);
            font-weight: 800;
            font-size: 15px;
        }

        .bet-button.is-active {
            background: linear-gradient(135deg, var(--gold), var(--gold-strong));
            color: #2b1900;
            box-shadow: 0 10px 24px rgba(255, 175, 41, 0.22);
        }

        .bet-button:not(:disabled):hover,
        .bet-adjust:not(:disabled):hover {
            transform: translateY(-1px);
        }

        .spin-button {
            position: relative;
            overflow: hidden;
            width: 100%;
            min-height: 68px;
            background: linear-gradient(135deg, #ffe183, #ffba31 56%, #ff9d1f 100%);
            color: #2b1900;
            font-weight: 900;
            font-size: 22px;
            letter-spacing: 0.08em;
            box-shadow: 0 16px 32px rgba(255, 179, 37, 0.24);
        }

        .spin-button::after {
            content: "";
            position: absolute;
            inset: 1px;
            border-radius: 15px;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.26), transparent 40%);
            pointer-events: none;
        }

        .spin-button.is-loading {
            animation: pulseSpin 900ms ease infinite;
        }

        @keyframes pulseSpin {
            0%, 100% { transform: scale(1); box-shadow: 0 16px 32px rgba(255, 179, 37, 0.24); }
            50% { transform: scale(1.015); box-shadow: 0 20px 40px rgba(255, 179, 37, 0.32); }
        }

        .spin-help {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            margin-top: 10px;
            color: var(--muted);
            font-size: 12px;
        }

        .chip-row {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.05);
            color: var(--muted);
            font-size: 12px;
        }

        .chip strong {
            color: #fff5d1;
            font-size: 12px;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .message {
            min-height: 20px;
            margin-top: 8px;
            color: var(--muted);
            font-size: 14px;
        }

        .message.is-success { color: var(--green); }
        .message.is-error { color: var(--red); }
        .message.is-loss { color: var(--red); }
        .message.is-warning { color: var(--gold); }

        .round-meta-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        }

        .round-meta-row:last-child {
            border-bottom: 0;
            padding-bottom: 0;
        }

        .round-meta-row span {
            color: var(--muted);
        }

        .round-meta-row strong {
            text-align: right;
            color: #fff8eb;
            word-break: break-word;
        }

        .winning-line-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .winning-line {
            padding: 11px 13px;
            border-radius: 14px;
            background: linear-gradient(135deg, rgba(124, 255, 176, 0.12), rgba(124, 255, 176, 0.04));
            border: 1px solid rgba(124, 255, 176, 0.16);
            color: #c7ffd9;
            font-size: 13px;
        }

        .paytable-shell {
            overflow: auto;
            max-height: 250px;
            border-radius: 18px;
            border: 1px solid rgba(255, 255, 255, 0.04);
            background: rgba(255, 255, 255, 0.02);
        }

        .paytable {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        .paytable th,
        .paytable td {
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            padding: 10px 8px;
            text-align: center;
        }

        .paytable th {
            position: sticky;
            top: 0;
            z-index: 1;
            background: rgba(12, 18, 24, 0.96);
            color: #ffedbd;
        }

        .paytable th:first-child,
        .paytable td:first-child {
            text-align: left;
            min-width: 180px;
        }

        .pay-symbols {
            display: inline-flex;
            gap: 4px;
            align-items: center;
            flex-wrap: wrap;
        }

        .pay-symbol {
            width: 30px;
            height: 30px;
            border-radius: 8px;
            display: inline-block;
            background-image: url('/internal-games/mini-slot/ville/symbols-preview.png');
            background-repeat: no-repeat;
            background-size: 120px 120px;
            background-color: rgba(255, 247, 224, 0.92);
            border: 1px solid rgba(93, 54, 6, 0.12);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.28);
        }

        .pay-symbol.is-bar {
            background-position: 0 -90px;
        }

        .pay-symbol.is-2xbar {
            background-position: 0 -60px;
        }

        .pay-symbol.is-3xbar {
            background-position: 0 0;
        }

        .pay-symbol.is-seven {
            background-position: -90px -30px;
        }

        .pay-symbol.is-cherry {
            background-position: -60px -60px;
        }

        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        @media (max-width: 720px) {
            body {
                padding: 8px;
            }

            .shell {
                border-radius: 22px;
            }

            .layout {
                padding-left: 16px;
                padding-right: 16px;
            }

            .stage,
            .card {
                border-radius: 22px;
            }

            .machine {
                padding: 14px;
            }

            .machine-body {
                grid-template-columns: 1fr;
            }

            .bet-toolbar {
                grid-template-columns: 48px minmax(0, 1fr) 48px;
            }

            .spin-button {
                min-height: 62px;
                font-size: 20px;
            }

            .paytable th:first-child,
            .paytable td:first-child {
                min-width: 140px;
            }

        }
    </style>
</head>
<body>
@php
    $guestPreview = $payload['preview'] ?? false;
@endphp
@if($guestPreview)
    @include('game-provider-front.partials.guest-preview')
    <div inert data-preview-surface>
@endif
<div class="shell">
    <div class="layout">
        <section class="stage">
            <div class="machine" id="slotMachineShell">
                <span class="sr-only">{{ trans('cms_public.games.mini_slot.client_reels_only') }}</span>
                @if($payload['isDemo'])
                    <span class="sr-only">{{ trans('cms_public.games.common.demo_session') }}</span>
                @endif
                <div class="machine-body">
                    <div class="machine-main">
                        <div class="machine-screen-wrap">
                            <div class="machine-screen" data-slot-surface="animated">
                                <div class="win-banner" id="winBanner">
                                    <small>{{ trans('cms_public.games.mini_slot.round_win') }}</small>
                                    <strong id="winBannerValue">0.00 {{ $payload['currency'] }}</strong>
                                </div>
                                @if($guestPreview)
                                    <div style="display:flex;justify-content:center;align-items:center;height:100%;gap:10px">
                                        @foreach(array_slice($payload['symbolAssets'], 0, 3) as $symbol => $assetPath)
                                            <img src="{{ $assetPath }}" alt="{{ $symbol }}" style="width:24%;max-width:120px">
                                        @endforeach
                                    </div>
                                @else
                                    <canvas id="slotCanvas" width="480" height="360" aria-label="{{ trans('cms_public.games.mini_slot.animated_slot_machine') }}"></canvas>
                                @endif
                            </div>
                        </div>
                    </div>
                    <aside class="cabinet-side">
                        <div class="console-panel">
                            <h2>{{ trans('cms_public.games.mini_slot.bet_controls') }}</h2>
                            <div class="control-stack">
                                <div class="bet-toolbar">
                                    <button type="button" class="bet-adjust" id="betDownButton" aria-label="{{ trans('cms_public.games.mini_slot.decrease_bet') }}">−</button>
                                    <div class="bet-display">
                                        <small>{{ trans('cms_public.games.mini_slot.bet_amount') }}</small>
                                        <strong id="betDisplayValue">{{ number_format((float) ($payload['betValues'][0] ?? 0), 2) }}</strong>
                                    </div>
                                    <button type="button" class="bet-adjust" id="betUpButton" aria-label="{{ trans('cms_public.games.mini_slot.increase_bet') }}">+</button>
                                </div>
                                <div class="bet-grid" id="betGrid">
                                    @foreach($payload['betValues'] as $index => $betValue)
                                        <button class="bet-button{{ $index === 0 ? ' is-active' : '' }}" data-bet="{{ $betValue }}">
                                            {{ number_format((float) $betValue, 2) }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        <div class="spin-bar">
                            <button type="button" class="spin-button" id="spinButton">{{ trans('cms_public.games.mini_slot.spin') }}</button>
                            <div class="spin-help">
                                <span>{{ trans('cms_public.games.mini_slot.tap_friendly') }}</span>
                            </div>
                            <div class="message" id="slotMessage"></div>
                        </div>
                        <div class="display-panel">
                            <div class="display-grid">
                                <div class="display-row">
                                    <span class="display-label">{{ trans('cms_public.games.common.currency') }}</span>
                                    <strong class="display-value is-small">{{ $payload['currency'] }}</strong>
                                </div>
                                <div class="display-row">
                                    <span class="display-label">{{ $payload['isDemo'] ? trans('cms_public.games.common.demo_session') : trans('cms_public.games.common.balance') }}</span>
                                    <strong class="display-value is-gold" id="balanceValue">{{ $guestPreview ? '—' : \Brick\Math\BigDecimal::of((string)$payload['balanceExact'])->toScale(2) . ' ' . $payload['currency'] }}</strong>
                                </div>
                                <div class="display-row">
                                    <span class="display-label">{{ trans('cms_public.games.common.bet') }}</span>
                                    <strong class="display-value is-small" id="currentBetValue">{{ number_format((float) ($payload['betValues'][0] ?? 0), 2) . ' ' . $payload['currency'] }}</strong>
                                </div>
                                <div class="display-row">
                                    <span class="display-label">{{ trans('cms_public.games.common.last_win') }}</span>
                                    <strong class="display-value is-small" id="lastWinValue">0.00 {{ $payload['currency'] }}</strong>
                                </div>
                                @if($payload['isDemo'])
                                    <div class="display-row">
                                        <span class="display-label">{{ trans('cms_public.games.common.mode') }}</span>
                                        <strong class="display-value is-small" id="modeValue">{{ trans('cms_public.games.common.demo') }}</strong>
                                    </div>
                                @endif
                            </div>
                        </div>

                    </aside>
                </div>
            </div>
        </section>
    </div>
</div>
<div class="sr-only" aria-hidden="true">
    <span id="modeChip">{{ $payload['isDemo'] ? trans('cms_public.games.common.demo_session') : trans('cms_public.games.common.real_wallet_session') }}</span>
    <span id="spinHint">{{ $guestPreview ? __('catalog.guest_preview_note') : ($payload['isDemo'] ? trans('cms_public.games.mini_slot.demo_balance_visible') : trans('cms_public.games.mini_slot.using_real_wallet')) }}</span>
    @if(!$payload['isDemo'])
        <span id="modeValue">{{ trans('cms_public.games.common.real_wallet') }}</span>
    @endif
    <span id="lastWinMeta">{{ trans('cms_public.games.mini_slot.no_winning_round') }}</span>
    <span id="roundIdValue">-</span>
    <span id="roundStatusValue">-</span>
    <span id="betAmountValue">-</span>
    <span id="winAmountValue">-</span>
    <span id="transactionValue">-</span>
    <div id="winningLines"><div class="message">{{ trans('cms_public.games.mini_slot.no_rounds_yet') }}</div></div>
</div>

@if($guestPreview)
    </div>
@else
@include('game-provider-front.partials.slot-session')
<script>
    const payload = @json($payload);
    const slotI18n = @json(trans('cms_public.games.mini_slot'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    const commonI18n = @json(trans('cms_public.games.common'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const spinButton = document.getElementById('spinButton');
    const betButtons = Array.from(document.querySelectorAll('.bet-button'));
    const betDownButton = document.getElementById('betDownButton');
    const betUpButton = document.getElementById('betUpButton');
    const slotMessage = document.getElementById('slotMessage');
    const balanceValue = document.getElementById('balanceValue');
    const currentBetValue = document.getElementById('currentBetValue');
    const betDisplayValue = document.getElementById('betDisplayValue');
    const lastWinValue = document.getElementById('lastWinValue');
    const lastWinMeta = document.getElementById('lastWinMeta');
    const roundIdValue = document.getElementById('roundIdValue');
    const roundStatusValue = document.getElementById('roundStatusValue');
    const betAmountValue = document.getElementById('betAmountValue');
    const winAmountValue = document.getElementById('winAmountValue');
    const transactionValue = document.getElementById('transactionValue');
    const winningLines = document.getElementById('winningLines');
    const canvas = document.getElementById('slotCanvas');
    const slotMachineShell = document.getElementById('slotMachineShell');
    const winBanner = document.getElementById('winBanner');
    const winBannerValue = document.getElementById('winBannerValue');
    const spinHint = document.getElementById('spinHint');
    const modeChip = document.getElementById('modeChip');
    const spinAudio = payload.audioAssets?.spin ? new Audio(payload.audioAssets.spin) : null;
    const winAudio = payload.audioAssets?.win ? new Audio(payload.audioAssets.win) : null;
    const FACELIFT_SPRITE_MAP = {
        BAR: { x: 0, y: 384, size: 128 },
        '2xBAR': { x: 0, y: 256, size: 128 },
        '3xBAR': { x: 0, y: 0, size: 128 },
        '7': { x: 384, y: 128, size: 128 },
        Cherry: { x: 256, y: 256, size: 128 },
    };
    const FACELIFT_SHEET = '/internal-games/mini-slot/ville/symbols-preview.png';
    const CORE_SYMBOL_ORDER = ['BAR', '2xBAR', '3xBAR', '7', 'Cherry'];
    const SYMBOL_ORDER = [
        ...CORE_SYMBOL_ORDER.filter((symbol) => payload.symbolAssets?.[symbol]),
        ...Object.keys(payload.symbolAssets || {}).filter((symbol) => !CORE_SYMBOL_ORDER.includes(symbol)),
    ];
    const SYMBOL_POOL = SYMBOL_ORDER.length ? SYMBOL_ORDER : Object.keys(payload.symbolAssets || {});
    const DEFAULT_SYMBOL = SYMBOL_POOL[0] || 'BAR';
    const VISIBLE_ROWS = 3;
    const VISIBLE_REELS = 3;

    function textFrom(map, key, fallback, replacements = {}) {
        let value = Object.prototype.hasOwnProperty.call(map || {}, key) ? map[key] : fallback;
        if (typeof value !== 'string') value = fallback;
        Object.entries(replacements).forEach(([name, replacement]) => {
            value = value.replaceAll(`:${name}`, String(replacement));
        });
        return value;
    }

    const slotText = (key, fallback, replacements = {}) => textFrom(slotI18n, key, fallback, replacements);
    const commonText = (key, fallback, replacements = {}) => textFrom(commonI18n, key, fallback, replacements);

    let selectedBet = Number(payload.betValues[0] || 1);
    let machineReady = false;
    let machineBusy = false;
    let winBannerTimer = null;
    let lastResolvedGrid = Array.from({ length: VISIBLE_REELS }, () => Array.from({ length: VISIBLE_ROWS }, () => DEFAULT_SYMBOL));
    let slotMachine = null;
    let spinSequence = 0;
    let latestAppliedSpinSequence = 0;
    let walletMessageSequence = 0;

    function symbolAsset(symbol) {
        return payload.symbolAssets?.[symbol] || payload.symbolAssets?.[DEFAULT_SYMBOL] || '';
    }

    function formatMoney(amount) {return window.riseSlotSession.format(amount)+' '+payload.currency;}

    function setMessage(message, type = '') {
        slotMessage.textContent = message || '';
        slotMessage.className = 'message' + (type ? ` is-${type}` : '');
    }

    function currentBalance() {
        return typeof payload.balance === 'number' ? Number(payload.balance) : null;
    }

    function setBalance(amount, exact = null) {
        const nextBalance = Number(amount);
        if (!Number.isFinite(nextBalance)) {
            return false;
        }

        payload.balance = nextBalance;
        payload.balanceExact = exact ?? String(amount);
        balanceValue.textContent = formatMoney(payload.balanceExact);
        return true;
    }

    function postParentWalletUpdate(data, requestKey = null) {
        if (payload.isDemo !== false || !window.parent || window.parent === window) {
            return;
        }

        const balance = Number(data && data.balance);
        if (!Number.isFinite(balance)) {
            return;
        }

        walletMessageSequence += 1;
        window.parent.postMessage({
            type: 'internal-game:wallet-updated',
            source: 'mini-slot',
            roundId: data.roundId || null,
            requestRef: data.requestKey || requestKey || null,
            balance,
            balanceExact: data.balanceExact,
            currency: data.currency || payload.currency || '',
            timestamp: Date.now(),
            sequence: walletMessageSequence,
        }, window.location.origin);
    }

    function applyCanonicalBalance(data, requestKey = null, sequence = 0) {
        if (!data || data.balance == null || sequence < latestAppliedSpinSequence) {
            return false;
        }

        const applied = setBalance(data.balance,data.balanceExact);
        if (applied) {
            latestAppliedSpinSequence = sequence;
            postParentWalletUpdate(data, requestKey);
        }

        return applied;
    }

    function roundNet(data) {
        const payout = Number(data?.winAmount || 0);
        const bet = Number(data?.betAmount != null ? data.betAmount : selectedBet);
        return {
            bet: Number.isFinite(bet) ? bet : 0,
            payout: Number.isFinite(payout) ? payout : 0,
            net: payout - (Number.isFinite(bet) ? bet : 0),
        };
    }

    function semanticRoundStatus(data) {
        const result = roundNet(data);

        if (result.net > 0) {
            return {
                tone: 'success',
                label: 'Net win',
                message: `Net win ${formatMoney(result.net)} (payout ${formatMoney(result.payout)}).`,
            };
        }

        if (result.payout > 0 && Math.abs(result.net) < 0.005) {
            return {
                tone: '',
                label: 'Break even',
                message: 'Break even: stake returned.',
            };
        }

        if (result.payout > 0) {
            return {
                tone: 'loss',
                label: 'Partial payout',
                message: `Partial payout ${formatMoney(result.payout)} - net loss ${formatMoney(Math.abs(result.net))}.`,
            };
        }

        return {
            tone: '',
            label: 'Loss',
            message: 'No win this round.',
        };
    }

    function updateBetDisplay() {
        currentBetValue.textContent = formatMoney(selectedBet);
        betDisplayValue.textContent = Number(selectedBet).toFixed(2);
        spinHint.textContent = payload.isDemo
            ? `${slotText('demo_balance_visible', 'Demo balance visible')} · ${commonText('bet', 'Bet')} ${Number(selectedBet).toFixed(2)}`
            : `${slotText('using_real_wallet', 'Using real wallet')} · ${commonText('bet', 'Bet')} ${Number(selectedBet).toFixed(2)}`;
    }

    function hideWinBanner() {
        winBanner.classList.remove('is-visible');
    }

    function showWinBanner(amount) {
        winBannerValue.textContent = formatMoney(amount);
        winBanner.classList.add('is-visible');
        clearTimeout(winBannerTimer);
        winBannerTimer = window.setTimeout(hideWinBanner, 2200);
    }

    function canSpin() {
        if (!machineReady || machineBusy) {
            return false;
        }

        if (payload.isDemo) {
            return true;
        }

        const balance = currentBalance();
        return balance !== null && balance >= selectedBet;
    }

    function syncSpinAvailability() {
        const disabled = !canSpin();
        spinButton.disabled = disabled;
        betDownButton.disabled = machineBusy;
        betUpButton.disabled = machineBusy;

        if (!machineReady) {
            setMessage(slotText('assets_loading', 'Assets are loading...'), 'warning');
            return;
        }

        if (machineBusy) {
            setMessage(slotText('spin_processing', 'Spin is processing...'));
            return;
        }

        if (disabled) {
            setMessage(commonText('insufficient_balance_min', 'Insufficient balance. Minimum required: :amount :currency.', {
                amount: selectedBet.toFixed(2),
                currency: payload.currency,
            }), 'warning');
        } else if (slotMessage.classList.contains('is-warning')) {
            setMessage('');
        }
    }

    function normalizeGrid(grid) {
        const normalized = [];

        for (let reel = 0; reel < VISIBLE_REELS; reel++) {
            const column = [];
            for (let row = 0; row < VISIBLE_ROWS; row++) {
                column.push((grid?.[reel] || [])[row] || DEFAULT_SYMBOL);
            }
            normalized.push(column);
        }

        return normalized;
    }

    function normalizeWinningLines(lines) {
        return (lines || [])
            .map((line) => {
                const raw = `${line?.line || ''} ${line?.lineLabel || ''}`.toLowerCase();
                let rowIndex = 0;

                if (raw.includes('middle')) {
                    rowIndex = 1;
                } else if (raw.includes('bottom')) {
                    rowIndex = 2;
                } else if (typeof line?.lineIndex === 'number') {
                    rowIndex = line.lineIndex;
                }

                return {
                    rowIndex,
                    label: line?.lineLabel || line?.line || 'line',
                    symbol: line?.symbol || DEFAULT_SYMBOL,
                    multiplier: Number(line?.multiplier || 0),
                };
            })
            .filter((line) => line.rowIndex >= 0 && line.rowIndex < VISIBLE_ROWS);
    }

    function randomSymbols(length) {
        return Array.from({ length }, () => SYMBOL_POOL[Math.floor(Math.random() * SYMBOL_POOL.length)] || DEFAULT_SYMBOL);
    }

    function setSelectedBet(nextBet) {
        selectedBet = Number(nextBet);
        betButtons.forEach((button) => {
            button.classList.toggle('is-active', Number(button.dataset.bet) === selectedBet);
        });
        updateBetDisplay();
        syncSpinAvailability();
    }

    function shiftBet(direction) {
        const betValues = payload.betValues.map(Number);
        const currentIndex = Math.max(0, betValues.indexOf(selectedBet));
        const nextIndex = Math.min(betValues.length - 1, Math.max(0, currentIndex + direction));
        setSelectedBet(betValues[nextIndex]);
    }

    class AnimatedReel {
        constructor(index, symbolSize) {
            this.index = index;
            this.symbolSize = symbolSize;
            this.sequence = [];
            this.offset = 0;
            this.speed = 0;
            this.blur = 0;
            this.state = 'idle';
            this.stopAt = 0;
            this.stopOffset = 0;
            this.onStop = null;
            this.bounceDistance = 14;
            this.setIdleColumn(Array.from({ length: VISIBLE_ROWS }, () => DEFAULT_SYMBOL));
        }

        setIdleColumn(column) {
            this.sequence = [...randomSymbols(2), ...column, ...randomSymbols(2)];
            this.offset = this.symbolSize * 2;
            this.speed = 0;
            this.blur = 0;
            this.state = 'idle';
            this.onStop = null;
        }

        startSpin() {
            this.sequence = randomSymbols(84);
            this.offset = 0;
            this.speed = 1540 + (this.index * 170);
            this.blur = 0;
            this.state = 'spinning';
            this.onStop = null;
        }

        planStop(column, stopAt, onStop) {
            const leadInCount = 20 + (this.index * 3);

            this.sequence = [...randomSymbols(leadInCount), ...column, ...randomSymbols(4)];
            this.offset = 0;
            this.speed = 1820 + (this.index * 180);
            this.blur = 0;
            this.state = 'waiting-stop';
            this.stopAt = stopAt;
            this.stopOffset = leadInCount * this.symbolSize;
            this.onStop = onStop;
        }

        update(deltaMs, now) {
            const deltaSeconds = deltaMs / 1000;

            if (this.state === 'idle') {
                return;
            }

            if (this.state === 'spinning') {
                const cycle = Math.max(this.symbolSize, (this.sequence.length - VISIBLE_ROWS) * this.symbolSize);
                this.offset = (this.offset + (this.speed * deltaSeconds)) % cycle;
                return;
            }

            if (this.state === 'waiting-stop') {
                this.offset += this.speed * deltaSeconds;
                if (now >= this.stopAt) {
                    this.state = 'settling';
                }
                return;
            }

            if (this.state === 'settling') {
                const target = this.stopOffset;
                const remaining = target - this.offset;

                if (remaining <= 0.8) {
                    this.offset = target;
                    this.blur = 0;
                    this.state = 'bounce-back';
                    return;
                }

                const easing = 1 - Math.pow(0.0014, deltaSeconds);
                const step = Math.min(remaining, Math.max(240 * deltaSeconds, remaining * easing));
                this.offset += step;
                this.blur = 0;
                return;
            }

            if (this.state === 'bounce-back') {
                this.offset = this.stopOffset + this.bounceDistance;
                this.state = 'bounce-settle';
                return;
            }

            if (this.state === 'bounce-settle') {
                const remaining = this.offset - this.stopOffset;
                if (remaining <= 0.7) {
                    this.offset = this.stopOffset;
                    this.blur = 0;
                    this.state = 'idle';
                    const callback = this.onStop;
                    this.onStop = null;
                    if (typeof callback === 'function') {
                        callback();
                    }
                    return;
                }

                this.offset -= Math.max(90 * deltaSeconds, remaining * 0.42);
            }
        }
    }

    class AnimatedSlotMachine {
        constructor(canvasNode, images, spriteSheet) {
            this.canvas = canvasNode;
            this.ctx = canvasNode.getContext('2d');
            this.images = images;
            this.spriteSheet = spriteSheet;
            this.width = canvasNode.width;
            this.height = canvasNode.height;
            this.symbolSize = this.height / VISIBLE_ROWS;
            this.reelWidth = this.width / VISIBLE_REELS;
            this.reelXOffsets = Array.from({ length: VISIBLE_REELS }, (_, index) => index * this.reelWidth);
            this.reels = [0, 1, 2].map((index) => new AnimatedReel(index, this.symbolSize));
            this.winningLines = [];
            this.flashStartedAt = 0;
            this.lastFrameAt = performance.now();
            this.renderLoop = this.renderLoop.bind(this);
            window.requestAnimationFrame(this.renderLoop);
        }

        setIdleGrid(grid) {
            const normalizedGrid = normalizeGrid(grid);
            this.winningLines = [];
            normalizedGrid.forEach((column, index) => this.reels[index].setIdleColumn(column));
        }

        startSpin() {
            this.winningLines = [];
            this.reels.forEach((reel) => reel.startSpin());
        }

        stopWithGrid(grid, lines) {
            const normalizedGrid = normalizeGrid(grid);
            const normalizedLines = normalizeWinningLines(lines);
            const now = performance.now();

            return Promise.all(this.reels.map((reel, index) => new Promise((resolve) => {
                reel.planStop(normalizedGrid[index], now + 280 + (index * 260), resolve);
            }))).then(() => {
                this.winningLines = normalizedLines;
                this.flashStartedAt = performance.now();
            });
        }

        renderLoop(now) {
            const deltaMs = Math.min(now - this.lastFrameAt, 40);
            this.lastFrameAt = now;
            this.reels.forEach((reel) => reel.update(deltaMs, now));
            this.draw(now);
            window.requestAnimationFrame(this.renderLoop);
        }

        draw(now) {
            const ctx = this.ctx;
            ctx.clearRect(0, 0, this.width, this.height);

            const background = ctx.createLinearGradient(0, 0, 0, this.height);
            background.addColorStop(0, '#fff8e0');
            background.addColorStop(1, '#eec768');
            ctx.fillStyle = background;
            ctx.fillRect(0, 0, this.width, this.height);

            this.drawWinningRowsBackdrop(now);
            this.reels.forEach((reel, index) => this.drawReel(reel, this.reelXOffsets[index]));
            this.drawFrame();
            this.drawWinningLines(now);
        }

        drawWinningRowsBackdrop(now) {
            if (!this.winningLines.length) {
                return;
            }

            const ctx = this.ctx;
            const flashAge = now - this.flashStartedAt;
            const pulse = 0.18 + ((Math.sin(flashAge / 160) + 1) * 0.1);

            this.winningLines.forEach((line) => {
                const y = line.rowIndex * this.symbolSize;
                const fill = ctx.createLinearGradient(0, y, this.width, y + this.symbolSize);
                fill.addColorStop(0, `rgba(124, 255, 176, ${pulse})`);
                fill.addColorStop(0.5, `rgba(255, 240, 165, ${pulse + 0.05})`);
                fill.addColorStop(1, `rgba(124, 255, 176, ${pulse})`);
                ctx.fillStyle = fill;
                ctx.fillRect(0, y, this.width, this.symbolSize);
            });
        }

        drawReel(reel, xOffset) {
            const ctx = this.ctx;
            const topIndex = Math.floor(reel.offset / this.symbolSize);
            const remainder = reel.offset % this.symbolSize;

            ctx.save();
            ctx.beginPath();
            ctx.rect(xOffset, 0, this.reelWidth, this.height);
            ctx.clip();

            const reelBackground = ctx.createLinearGradient(xOffset, 0, xOffset + this.reelWidth, 0);
            reelBackground.addColorStop(0, 'rgba(59, 37, 10, 0.28)');
            reelBackground.addColorStop(0.5, 'rgba(214, 182, 108, 0.18)');
            reelBackground.addColorStop(1, 'rgba(59, 37, 10, 0.28)');
            ctx.fillStyle = reelBackground;
            ctx.fillRect(xOffset, 0, this.reelWidth, this.height);

            for (let slotIndex = -1; slotIndex < VISIBLE_ROWS + 2; slotIndex++) {
                const symbolIndex = Math.max(0, Math.min(reel.sequence.length - 1, topIndex + slotIndex));
                const symbol = reel.sequence[symbolIndex] || DEFAULT_SYMBOL;
                const y = (slotIndex * this.symbolSize) - remainder;
                this.drawSymbol(symbol, xOffset + 10, y + 10, this.reelWidth - 20, this.symbolSize - 20, reel.blur);
            }

            ctx.restore();
        }

        drawSymbol(symbol, x, y, width, height, blur) {
            const ctx = this.ctx;
            const image = this.images[symbol];
            const sprite = FACELIFT_SPRITE_MAP[symbol];
            const isBlank = symbol === 'Blank';

            ctx.save();

            const panelGradient = ctx.createLinearGradient(x, y, x, y + height);
            if (isBlank) {
                panelGradient.addColorStop(0, '#6e5424');
                panelGradient.addColorStop(1, '#3f2e10');
            } else {
                panelGradient.addColorStop(0, '#f3dfb0');
                panelGradient.addColorStop(1, '#cda04c');
            }
            ctx.fillStyle = panelGradient;
            ctx.fillRect(x, y, width, height);

            ctx.strokeStyle = isBlank ? 'rgba(255, 240, 197, 0.08)' : 'rgba(105, 71, 16, 0.22)';
            ctx.lineWidth = 2;
            ctx.strokeRect(x, y, width, height);

            if (isBlank) {
                const insetX = x + 10;
                const insetY = y + 10;
                const insetWidth = width - 20;
                const insetHeight = height - 20;
                const blankGlow = ctx.createLinearGradient(insetX, insetY, insetX, insetY + insetHeight);
                blankGlow.addColorStop(0, 'rgba(255, 244, 210, 0.07)');
                blankGlow.addColorStop(1, 'rgba(18, 12, 4, 0.16)');
                ctx.fillStyle = blankGlow;
                ctx.fillRect(insetX, insetY, insetWidth, insetHeight);

                ctx.strokeStyle = 'rgba(255, 226, 157, 0.08)';
                ctx.lineWidth = 1.5;
                ctx.strokeRect(insetX, insetY, insetWidth, insetHeight);

                ctx.beginPath();
                ctx.strokeStyle = 'rgba(255, 225, 154, 0.16)';
                ctx.lineWidth = 2;
                ctx.moveTo(insetX + 12, insetY + (insetHeight / 2));
                ctx.lineTo(insetX + insetWidth - 12, insetY + (insetHeight / 2));
                ctx.stroke();

                ctx.beginPath();
                ctx.moveTo(insetX + (insetWidth / 2), insetY + 12);
                ctx.lineTo(insetX + (insetWidth / 2), insetY + insetHeight - 12);
                ctx.stroke();

                ctx.fillStyle = 'rgba(255, 231, 176, 0.18)';
                ctx.beginPath();
                ctx.arc(x + (width / 2), y + (height / 2), Math.max(10, Math.min(width, height) * 0.12), 0, Math.PI * 2);
                ctx.fill();
            } else if (sprite && this.spriteSheet) {
                ctx.drawImage(
                    this.spriteSheet,
                    sprite.x,
                    sprite.y,
                    sprite.size,
                    sprite.size,
                    x + 8,
                    y + 8,
                    width - 16,
                    height - 16
                );
            } else if (image) {
                ctx.drawImage(image, x + 8, y + 8, width - 16, height - 16);
            } else {
                const fallbackPanel = ctx.createLinearGradient(x + 6, y + 6, x + 6, y + height - 6);
                fallbackPanel.addColorStop(0, 'rgba(91, 62, 18, 0.92)');
                fallbackPanel.addColorStop(1, 'rgba(56, 37, 10, 0.94)');
                ctx.fillStyle = fallbackPanel;
                ctx.fillRect(x + 6, y + 6, width - 12, height - 12);

                ctx.fillStyle = 'rgba(255, 233, 187, 0.82)';
                ctx.font = 'bold 28px Trebuchet MS, sans-serif';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(symbol, x + (width / 2), y + (height / 2));
            }

            ctx.restore();
        }

        drawFrame() {
            const ctx = this.ctx;
            ctx.save();
            ctx.strokeStyle = 'rgba(93, 54, 6, 0.42)';
            ctx.lineWidth = 5;

            this.reelXOffsets.forEach((xOffset) => {
                ctx.strokeRect(xOffset + 2, 2, this.reelWidth - 4, this.height - 4);
            });

            [1, 2].forEach((rowIndex) => {
                const y = rowIndex * this.symbolSize;
                ctx.beginPath();
                ctx.moveTo(0, y);
                ctx.lineTo(this.width, y);
                ctx.strokeStyle = 'rgba(82, 49, 5, 0.14)';
                ctx.lineWidth = 2;
                ctx.stroke();
            });

            ctx.restore();
        }

        drawWinningLines(now) {
            if (!this.winningLines.length) {
                return;
            }

            const ctx = this.ctx;
            const flashAge = now - this.flashStartedAt;
            const pulse = 0.66 + (Math.sin(flashAge / 140) * 0.24);

            ctx.save();
            this.winningLines.forEach((line) => {
                const y = (line.rowIndex * this.symbolSize) + (this.symbolSize / 2);
                ctx.beginPath();
                ctx.moveTo(14, y);
                ctx.lineTo(this.width - 14, y);
                ctx.strokeStyle = `rgba(255, 86, 46, ${pulse})`;
                ctx.lineWidth = 4;
                ctx.stroke();
            });
            ctx.restore();
        }
    }

    function preloadImages() {
        const symbolPromise = Promise.all(SYMBOL_POOL.map((symbol) => new Promise((resolve) => {
            const source = symbolAsset(symbol);
            if (!source) {
                resolve([symbol, null]);
                return;
            }

            const image = new Image();
            image.onload = () => resolve([symbol, image]);
            image.onerror = () => resolve([symbol, null]);
            image.src = source;
        }))).then((entries) => Object.fromEntries(entries));

        const spritePromise = new Promise((resolve) => {
            const image = new Image();
            image.onload = () => resolve(image);
            image.onerror = () => resolve(null);
            image.src = FACELIFT_SHEET;
        });

        return Promise.all([symbolPromise, spritePromise]);
    }

    function updateRound(data) {
        const semantic = semanticRoundStatus(data);

        roundIdValue.textContent = data.roundId || '-';
        roundStatusValue.textContent = semantic.label;
        betAmountValue.textContent = formatMoney(data.betAmount || 0);
        winAmountValue.textContent = formatMoney(data.winAmount || 0);

        const tx = data.transactions || {};
        transactionValue.textContent = [tx.bet, tx.win, tx.refund].filter(Boolean).join(' / ') || '-';

        winningLines.innerHTML = '';
        const lines = data.result?.winningLines || [];
        if (!lines.length) {
            winningLines.innerHTML = `<div class="message">${slotText('no_winning_lines', 'No winning lines.')}</div>`;
            lastWinMeta.textContent = slotText('no_winning_line_last_round', 'No winning line in last round');
            hideWinBanner();
        } else {
            lines.forEach((line) => {
                const item = document.createElement('div');
                item.className = 'winning-line';
                item.textContent = `${line.lineLabel || line.line}: ${line.symbol} (${line.multiplier}x)`;
                winningLines.appendChild(item);
            });
            lastWinMeta.textContent = slotText('winning_line_count', `${lines.length} winning line${lines.length > 1 ? 's' : ''}`, { count: lines.length });
        }

        if (data.balance != null) {
            setBalance(data.balance,data.balanceExact);
        }

        lastWinValue.textContent = formatMoney(data.winAmount || 0);

        if (roundNet(data).net > 0) {
            showWinBanner(data.winAmount);
            slotMachineShell.classList.add('is-win');
        } else {
            hideWinBanner();
            slotMachineShell.classList.remove('is-win');
        }

        syncSpinAvailability();
        return semantic;
    }

    betButtons.forEach((button) => {
        button.addEventListener('click', () => {
            if (machineBusy) {
                return;
            }

            setSelectedBet(button.dataset.bet);
        });
    });

    betDownButton.addEventListener('click', () => {
        if (machineBusy) {
            return;
        }

        shiftBet(-1);
    });

    betUpButton.addEventListener('click', () => {
        if (machineBusy) {
            return;
        }

        shiftBet(1);
    });

    spinButton.addEventListener('click', async () => {
        if (!canSpin()) {
            syncSpinAvailability();
            return;
        }

        const currentSpinSequence = spinSequence + 1;
        spinSequence = currentSpinSequence;
        machineBusy = true;
        slotMachineShell.classList.add('is-spinning');
        slotMachineShell.classList.remove('is-win');
        spinButton.disabled = true;
        spinButton.classList.add('is-loading');
        betButtons.forEach((button) => {
            button.disabled = true;
        });
        setMessage(slotText('spin_processing', 'Spin is processing...'));
        hideWinBanner();

        slotMachine.startSpin();

        if (spinAudio) {
            spinAudio.currentTime = 0;
            spinAudio.play().catch(() => {});
        }

        try {
            const json = await window.riseSlotSession.spin(selectedBet);
            const requestKey = json.data.requestRef;

            applyCanonicalBalance(json.data, requestKey, currentSpinSequence);

            const authoritativeGrid = normalizeGrid(json.data.result?.grid || []);
            await slotMachine.stopWithGrid(authoritativeGrid, json.data.result?.winningLines || []);
            lastResolvedGrid = authoritativeGrid;
            const semantic = updateRound(json.data);

            if (roundNet(json.data).net > 0 && winAudio) {
                winAudio.currentTime = 0;
                winAudio.play().catch(() => {});
            }

            setMessage(semantic.message || slotText('round_settled', 'Round settled.'), semantic.tone);
        } catch (error) {
            await slotMachine.stopWithGrid(lastResolvedGrid, []);
            setMessage(error.message || slotText('spin_failed', 'Spin failed.'), 'error');
        } finally {
            machineBusy = false;
            slotMachineShell.classList.remove('is-spinning');
            spinButton.classList.remove('is-loading');
            betButtons.forEach((button) => {
                button.disabled = false;
            });
            syncSpinAvailability();
        }
    });

    preloadImages().then(async ([images, spriteSheet]) => {
        slotMachine = new AnimatedSlotMachine(canvas, images, spriteSheet);
        slotMachine.setIdleGrid(lastResolvedGrid);
        machineReady = true;
        try{await window.riseSlotSession.restore(async data=>{lastResolvedGrid=normalizeGrid(data.result.grid);slotMachine.setIdleGrid(lastResolvedGrid);updateRound(data);});}catch(error){setMessage(error.message,'error');}
        updateBetDisplay();
        syncSpinAvailability();
    });

    updateBetDisplay();
    modeChip.textContent = payload.isDemo
        ? commonText('demo_session', 'Demo Session')
        : commonText('real_wallet_session', 'Real Wallet Session');
</script>
@endif
</body>
</html>
