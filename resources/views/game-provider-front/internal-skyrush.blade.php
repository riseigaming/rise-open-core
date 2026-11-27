<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $payload['gameName'] ?? 'SkyRush' }}</title>
    <style>
        :root {
            --bg: #08111f;
            --panel: #101b2d;
            --panel-2: #15253d;
            --line: rgba(255,255,255,.09);
            --text: #eef5ff;
            --muted: #8fa1bd;
            --accent: {{ data_get($payload, 'display.accent', '#6de0ff') }};
            --warn: {{ data_get($payload, 'display.warning', '#ffb65c') }};
            --danger: {{ data_get($payload, 'display.danger', '#ff5f7a') }};
            --success: #71f0a1;
        }

        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; background: radial-gradient(circle at top, #143056 0, #08111f 55%, #050b14 100%); color: var(--text); font-family: Inter, system-ui, sans-serif; }
        body { padding: 16px; }
        .shell { max-width: 1180px; margin: 0 auto; display: grid; gap: 16px; }
        .topbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; }
        .brand { display: flex; gap: 10px; align-items: center; font-weight: 700; letter-spacing: .04em; }
        .brand img { width: 32px; height: 32px; }
        .chip-row { display: flex; gap: 8px; flex-wrap: wrap; }
        .chip { border: 1px solid var(--line); background: rgba(255,255,255,.04); border-radius: 999px; padding: 8px 12px; color: var(--muted); font-size: 12px; }
        .chip strong { color: var(--text); }
        .chip.is-live { border-color: rgba(113,240,161,.24); background: rgba(113,240,161,.08); color: #cff7da; }
        .chip.is-delayed { border-color: rgba(255,182,92,.24); background: rgba(255,182,92,.08); color: #ffd697; }
        .chip.is-reconnecting { border-color: rgba(255,95,122,.28); background: rgba(255,95,122,.08); color: #ffc5cf; }
        .chip-button {
            border: 1px solid var(--line);
            background: rgba(255,255,255,.04);
            border-radius: 999px;
            padding: 8px 12px;
            color: var(--text);
            font-size: 12px;
            cursor: pointer;
            transition: background .18s ease, transform .18s ease, border-color .18s ease;
        }
        .chip-button:hover { background: rgba(255,255,255,.08); transform: translateY(-1px); }
        .chip-button.is-muted { color: var(--muted); }
        .surface { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 16px; align-items: start; }
        .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
        .stage { background: linear-gradient(180deg, rgba(255,255,255,.03), rgba(255,255,255,.01)); border: 1px solid var(--line); border-radius: 28px; padding: 18px; min-height: 620px; position: relative; overflow: hidden; }
        .stage::before { content: ""; position: absolute; inset: 0; background: radial-gradient(circle at 20% 20%, rgba(109,224,255,.14), transparent 30%), radial-gradient(circle at 80% 10%, rgba(255,95,122,.12), transparent 26%); pointer-events: none; }
        .history-strip { position: relative; z-index: 1; display: flex; gap: 8px; overflow: auto; padding-bottom: 4px; }
        .history-pill { flex: 0 0 auto; min-width: 72px; text-align: center; padding: 10px 12px; border-radius: 14px; background: rgba(7,14,25,.66); border: 1px solid rgba(255,255,255,.08); font-weight: 700; }
        .history-pill.is-hot { color: var(--success); }
        .history-pill.is-cold { color: var(--warn); }
        .arena { position: relative; z-index: 1; margin-top: 18px; border-radius: 24px; border: 1px solid rgba(255,255,255,.08); background: linear-gradient(180deg, rgba(14,26,43,.84), rgba(10,16,29,.98)); min-height: 500px; padding: 28px 24px; display: flex; flex-direction: column; justify-content: space-between; }
        .sky { position: relative; min-height: 320px; border-radius: 20px; overflow: hidden; background:
            radial-gradient(circle at 30% 20%, rgba(109,224,255,.12), transparent 18%),
            radial-gradient(circle at 70% 30%, rgba(255,255,255,.08), transparent 12%),
            linear-gradient(180deg, #122440 0%, #0a1526 100%);
            border: 1px solid rgba(255,255,255,.06);
        }
        .sky.is-crashing { animation: skyrushCrashShake .42s ease-out; }
        .sky .crash-flash {
            position: absolute;
            inset: 0;
            pointer-events: none;
            opacity: 0;
            background:
                radial-gradient(circle at 78% 72%, rgba(255,255,255,.95) 0%, rgba(255,95,122,.65) 16%, rgba(255,95,122,.18) 34%, transparent 54%);
            mix-blend-mode: screen;
        }
        .sky.is-crashing .crash-flash { animation: skyrushCrashFlash .4s ease-out; }
        .sky .shockwave {
            position: absolute;
            right: 10%;
            bottom: 14%;
            width: 36px;
            height: 36px;
            border-radius: 999px;
            border: 2px solid rgba(255,255,255,.8);
            opacity: 0;
            transform: translate(50%, 50%) scale(.2);
            pointer-events: none;
        }
        .sky.is-crashing .shockwave { animation: skyrushShockwave .46s ease-out; }
        .sky::after { content: ""; position: absolute; inset: 0; background-image:
            radial-gradient(circle at 12% 18%, rgba(255,255,255,.7) 0 1px, transparent 1px),
            radial-gradient(circle at 76% 12%, rgba(255,255,255,.55) 0 1px, transparent 1px),
            radial-gradient(circle at 42% 34%, rgba(255,255,255,.45) 0 1px, transparent 1px),
            radial-gradient(circle at 88% 45%, rgba(255,255,255,.45) 0 1px, transparent 1px),
            radial-gradient(circle at 18% 58%, rgba(255,255,255,.4) 0 1px, transparent 1px);
            opacity: .35;
            pointer-events: none;
        }
        .flight-canvas {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            display: block;
        }
        .multiplier-card { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; pointer-events: none; }
        .multiplier-card .phase { font-size: 12px; letter-spacing: .16em; text-transform: uppercase; color: var(--muted); margin-bottom: 12px; }
        .multiplier-card .multiplier {
            font-size: clamp(54px, 10vw, 108px);
            line-height: .95;
            font-weight: 800;
            letter-spacing: -.04em;
            text-shadow: 0 0 28px rgba(109,224,255,.18);
            font-variant-numeric: tabular-nums lining-nums;
            font-feature-settings: "tnum" 1, "lnum" 1;
            min-width: 5.6ch;
            text-align: center;
            display: inline-block;
        }
        .multiplier-card .multiplier.is-label {
            min-width: auto;
            font-size: clamp(24px, 4.8vw, 42px);
            letter-spacing: .08em;
            text-transform: uppercase;
            text-shadow: 0 0 18px rgba(109,224,255,.12);
        }
        .multiplier-card .sub { margin-top: 12px; color: var(--muted); font-size: 14px; }
        .status-banner { margin-top: 18px; border-radius: 16px; border: 1px solid rgba(255,255,255,.08); background: rgba(255,255,255,.03); padding: 14px 16px; min-height: 52px; color: var(--muted); }
        .status-banner strong { color: var(--text); }
        .launch-strip { margin-top: 12px; display: grid; gap: 8px; }
        .launch-pill {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            width: fit-content;
            max-width: 100%;
            padding: 8px 12px;
            border-radius: 999px;
            border: 1px solid rgba(255,255,255,.08);
            background: rgba(255,255,255,.04);
            color: var(--text);
            font-size: 12px;
            letter-spacing: .1em;
            text-transform: uppercase;
        }
        .launch-pill::before {
            content: "";
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: var(--accent);
            box-shadow: 0 0 0 0 rgba(109,224,255,.45);
        }
        .launch-pill.is-live::before {
            background: var(--success);
            animation: skyrushPulseDot 1.2s infinite;
        }
        .launch-pill.is-waiting::before { background: var(--warn); }
        .launch-pill.is-crashed::before { background: var(--danger); }
        .launch-progress {
            position: relative;
            height: 8px;
            border-radius: 999px;
            overflow: hidden;
            background: rgba(255,255,255,.06);
            border: 1px solid rgba(255,255,255,.06);
        }
        .launch-progress span {
            display: block;
            height: 100%;
            width: 100%;
            border-radius: 999px;
            background: linear-gradient(90deg, rgba(109,224,255,.86), rgba(255,182,92,.92));
            transform-origin: left center;
            will-change: transform, opacity, background;
            transition: transform .08s linear, opacity .18s ease, background .22s ease;
        }
        .side { display: grid; gap: 12px; }
        .panel { background: linear-gradient(180deg, var(--panel), var(--panel-2)); border: 1px solid var(--line); border-radius: 24px; padding: 16px; }
        .panel-title { margin: 0 0 12px; font-size: 12px; text-transform: uppercase; letter-spacing: .14em; color: var(--muted); }
        .panel-head { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 12px; }
        .panel-link {
            border: 1px solid rgba(255,255,255,.08);
            background: rgba(255,255,255,.04);
            color: var(--text);
            border-radius: 999px;
            padding: 8px 12px;
            font-size: 11px;
            letter-spacing: .1em;
            text-transform: uppercase;
            cursor: pointer;
        }
        .lane-stack { display: grid; gap: 12px; }
        .lane-panel { padding: 14px; border-radius: 18px; border: 1px solid rgba(255,255,255,.07); background: rgba(255,255,255,.03); }
        .lane-panel.is-hidden { display: none; }
        .lane-head { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 10px; }
        .lane-head strong { font-size: 13px; letter-spacing: .08em; text-transform: uppercase; }
        .lane-state { color: var(--muted); font-size: 12px; }
        .lane-selected { margin: 10px 0 12px; color: var(--muted); font-size: 13px; }
        .lane-selected strong { color: var(--text); margin-left: 6px; font-variant-numeric: tabular-nums lining-nums; font-feature-settings: "tnum" 1, "lnum" 1; }
        .readout { display: grid; gap: 10px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
        .readout-item { padding: 12px 14px; border-radius: 14px; background: linear-gradient(180deg, rgba(10,18,10,.96), rgba(6,14,9,.96)); border: 1px solid rgba(148,255,187,.15); }
        .readout-item span { display: block; font-size: 11px; letter-spacing: .12em; text-transform: uppercase; color: #79bf8d; opacity: .85; }
        .readout-item strong {
            display: block;
            margin-top: 6px;
            color: #dfffe8;
            font-size: 22px;
            letter-spacing: .02em;
            font-variant-numeric: tabular-nums lining-nums;
            font-feature-settings: "tnum" 1, "lnum" 1;
        }
        .bet-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; }
        .bet-btn, .action-btn { border: 0; border-radius: 16px; cursor: pointer; transition: transform .18s ease, opacity .18s ease, box-shadow .18s ease; }
        .bet-btn { background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.08); color: var(--text); padding: 12px 10px; font-weight: 700; }
        .bet-btn.is-active { background: linear-gradient(135deg, rgba(109,224,255,.18), rgba(109,224,255,.08)); border-color: rgba(109,224,255,.5); box-shadow: 0 0 0 1px rgba(109,224,255,.15) inset; }
        .action-btn { width: 100%; padding: 16px 18px; font-size: 18px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
        .action-btn.bet { background: linear-gradient(135deg, #1f78ff, #29b6ff); color: white; }
        .action-btn.cashout { background: linear-gradient(135deg, #19b34f, #80e766); color: #05100a; }
        .action-btn:disabled { opacity: .45; cursor: not-allowed; transform: none; box-shadow: none; }
        .action-btn:not(:disabled):hover, .bet-btn:hover { transform: translateY(-1px); }
        .auto-card {
            margin-top: 12px;
            padding: 12px;
            border-radius: 16px;
            border: 1px solid rgba(255,255,255,.07);
            background: rgba(255,255,255,.03);
            display: grid;
            gap: 10px;
        }
        .auto-toggle-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }
        .auto-toggle-row span {
            color: var(--muted);
            font-size: 12px;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .auto-switch {
            appearance: none;
            width: 44px;
            height: 26px;
            border-radius: 999px;
            border: 1px solid rgba(255,255,255,.12);
            background: rgba(255,255,255,.08);
            position: relative;
            cursor: pointer;
            transition: background .18s ease, border-color .18s ease;
        }
        .auto-switch::after {
            content: "";
            position: absolute;
            top: 3px;
            left: 3px;
            width: 18px;
            height: 18px;
            border-radius: 999px;
            background: #fff;
            transition: transform .18s ease;
        }
        .auto-switch:checked {
            background: rgba(109,224,255,.24);
            border-color: rgba(109,224,255,.45);
        }
        .auto-switch:checked::after {
            transform: translateX(18px);
            background: var(--accent);
        }
        .auto-input-row {
            display: grid;
            grid-template-columns: 1fr 100px;
            gap: 8px;
            align-items: center;
        }
        .auto-input-row span {
            color: var(--muted);
            font-size: 12px;
        }
        .auto-input {
            width: 100%;
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,.08);
            background: rgba(8,18,32,.9);
            color: var(--text);
            padding: 10px 12px;
            font: inherit;
            font-variant-numeric: tabular-nums lining-nums;
            font-feature-settings: "tnum" 1, "lnum" 1;
        }
        .auto-note {
            color: var(--muted);
            font-size: 12px;
            line-height: 1.45;
        }
        .meta-card { display: grid; gap: 10px; }
        .meta-row { display: flex; justify-content: space-between; align-items: baseline; gap: 12px; font-size: 14px; color: var(--muted); }
        .meta-row span { flex: 0 0 auto; }
        .meta-row strong {
            color: var(--text);
            flex: 1 1 auto;
            min-width: 0;
            text-align: right;
            white-space: nowrap;
            font-variant-numeric: tabular-nums lining-nums;
            font-feature-settings: "tnum" 1, "lnum" 1;
        }
        .mode-badge { display: inline-flex; align-items: center; gap: 8px; border-radius: 999px; padding: 8px 12px; background: rgba(255,182,92,.14); border: 1px solid rgba(255,182,92,.22); color: #ffd697; font-size: 12px; letter-spacing: .08em; text-transform: uppercase; }
        .mode-badge.is-hidden { display: none; }
        .outcome { padding: 14px 16px; border-radius: 16px; background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.08); }
        .outcome strong { display: block; font-size: 16px; margin-bottom: 6px; }
        .outcome.is-win { border-color: rgba(113,240,161,.32); background: rgba(113,240,161,.08); }
        .outcome.is-loss { border-color: rgba(255,95,122,.28); background: rgba(255,95,122,.08); }
        .outcome.is-demo { border-color: rgba(255,182,92,.28); background: rgba(255,182,92,.08); }
        .outcome.is-waiting { border-color: rgba(109,224,255,.24); background: rgba(109,224,255,.08); }
        .feed-list, .bets-list { display: grid; gap: 10px; }
        .feed-item, .bets-item {
            border-radius: 16px;
            padding: 12px 14px;
            background: rgba(255,255,255,.04);
            border: 1px solid rgba(255,255,255,.07);
        }
        .feed-item { display: flex; justify-content: space-between; gap: 10px; align-items: center; }
        .feed-item strong, .bets-item strong { display: block; color: var(--text); }
        .feed-item small, .bets-item small { color: var(--muted); }
        .feed-item.is-cashout { border-color: rgba(113,240,161,.22); background: rgba(113,240,161,.08); }
        .bets-item.is-win { border-color: rgba(113,240,161,.22); }
        .bets-item.is-loss { border-color: rgba(255,95,122,.22); }
        .drawer {
            position: fixed;
            inset: 0;
            background: rgba(3, 8, 16, .72);
            backdrop-filter: blur(10px);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 16px;
            z-index: 9999;
        }
        .drawer.is-open { display: flex; }
        .drawer-panel {
            width: min(720px, 100%);
            max-height: min(88vh, 920px);
            overflow: auto;
            border-radius: 24px;
            border: 1px solid rgba(255,255,255,.1);
            background: linear-gradient(180deg, rgba(12,20,34,.98), rgba(8,14,24,.98));
            box-shadow: 0 30px 80px rgba(0,0,0,.45);
            padding: 18px;
        }
        .drawer-grid { display: grid; gap: 10px; grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .drawer-card {
            border-radius: 16px;
            border: 1px solid rgba(255,255,255,.07);
            background: rgba(255,255,255,.04);
            padding: 12px 14px;
        }
        .drawer-card span { display: block; color: var(--muted); font-size: 11px; letter-spacing: .1em; text-transform: uppercase; }
        .drawer-card strong, .drawer-card code { display: block; margin-top: 6px; color: var(--text); word-break: break-word; }
        .verify-status {
            margin-top: 14px;
            border-radius: 16px;
            border: 1px solid rgba(255,255,255,.08);
            background: rgba(255,255,255,.04);
            padding: 14px 16px;
            display: grid;
            gap: 10px;
        }
        .verify-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            width: fit-content;
            padding: 8px 12px;
            border-radius: 999px;
            border: 1px solid rgba(255,255,255,.08);
            background: rgba(255,255,255,.04);
            font-size: 12px;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .verify-pill.is-ok {
            border-color: rgba(113,240,161,.24);
            background: rgba(113,240,161,.08);
            color: #cff7da;
        }
        .verify-pill.is-pending {
            border-color: rgba(255,182,92,.24);
            background: rgba(255,182,92,.08);
            color: #ffd697;
        }
        .verify-pill.is-fail {
            border-color: rgba(255,95,122,.28);
            background: rgba(255,95,122,.08);
            color: #ffc5cf;
        }
        .verify-steps {
            display: grid;
            gap: 8px;
            padding-left: 18px;
            margin: 0;
            color: var(--muted);
            font-size: 14px;
        }
        .verify-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .verify-button {
            border: 1px solid rgba(255,255,255,.08);
            background: rgba(255,255,255,.04);
            color: var(--text);
            border-radius: 12px;
            padding: 10px 12px;
            font-size: 12px;
            cursor: pointer;
        }
        .verify-grid {
            display: grid;
            gap: 10px;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        @keyframes skyrushCrashShake {
            0% { transform: translate3d(0, 0, 0); }
            18% { transform: translate3d(-4px, 2px, 0); }
            36% { transform: translate3d(5px, -3px, 0); }
            54% { transform: translate3d(-3px, 3px, 0); }
            72% { transform: translate3d(2px, -2px, 0); }
            100% { transform: translate3d(0, 0, 0); }
        }
        @keyframes skyrushCrashFlash {
            0% { opacity: 0; }
            18% { opacity: .95; }
            100% { opacity: 0; }
        }
        @keyframes skyrushShockwave {
            0% { opacity: .95; transform: translate(50%, 50%) scale(.18); }
            100% { opacity: 0; transform: translate(50%, 50%) scale(6); }
        }
        @keyframes skyrushPulseDot {
            0% { box-shadow: 0 0 0 0 rgba(113,240,161,.45); }
            70% { box-shadow: 0 0 0 10px rgba(113,240,161,0); }
            100% { box-shadow: 0 0 0 0 rgba(113,240,161,0); }
        }
        @media (max-width: 980px) {
            .surface { grid-template-columns: 1fr; }
            .stage { min-height: auto; }
            .arena { min-height: 440px; }
            .drawer-grid { grid-template-columns: 1fr; }
            .verify-grid { grid-template-columns: 1fr; }
        }
        .shell, .surface, .panel, .stage, .lane-panel { min-width: 0; max-width: 100%; }
        .meta-row { gap: 10px; }
        .meta-row strong { min-width: 0; overflow-wrap: anywhere; white-space: normal; text-align: right; }
        @media (max-width: 760px) { .surface { grid-template-columns: minmax(0,1fr); } .chip-row { max-width: 100%; } }
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
@php
    $bootPayload = $guestPreview ? '' : json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
@endphp
<div class="shell" id="skyRushApp" data-payload='{{ $bootPayload }}'>
    <div class="topbar"><a class="chip" href="{{ route('cms.games.detail', ['slug' => $session->game->slug]) }}">← {{ $payload['gameName'] }}</a>
        <div class="brand">
            <img src="{{ $session->game->imageUrl() }}" alt="SkyRush">
            <span>SkyRush</span>
        </div>
        <div class="chip-row">
            <span class="chip">{{ trans('cms_public.games.common.session') }} <strong id="sessionId">{{ $payload['sessionId'] }}</strong></span>
            <span class="chip">{{ trans('cms_public.games.common.currency') }} <strong id="currencyValue">{{ $payload['currency'] }}</strong></span>
            <span class="chip">{{ trans('cms_public.games.common.balance') }} <strong id="balanceValue">{{ $guestPreview ? '—' : number_format((float) $payload['balance'], 2) }} {{ $payload['currency'] }}</strong></span>
            <span class="chip is-live" id="connectionChip">{{ trans('cms_public.games.common.live') }}</span>
            <span class="chip" id="modeChip" @if(!$payload['isDemo']) style="display:none" @endif>{{ trans('cms_public.games.common.demo_session') }}</span>
            <button type="button" class="chip-button is-muted" id="soundToggle" aria-keyshortcuts="M" title="{{ trans('cms_public.games.skyrush.sound_toggle_title') }}">{{ trans('cms_public.games.skyrush.sound_off') }}</button>
        </div>
    </div>

    <div class="surface">
        <section class="stage">
            <div class="history-strip" id="historyStrip"></div>
                <div class="arena">
                    <div class="sky" id="skyStage">
                        <div class="crash-flash"></div>
                        <div class="shockwave"></div>
                        <canvas class="flight-canvas" id="flightCanvas" aria-hidden="true"></canvas>
                        <div class="multiplier-card" aria-live="polite" aria-atomic="true">
                            <div class="phase" id="roundPhaseLabel">{{ trans('cms_public.games.skyrush.taking_bets') }}</div>
                            <div class="multiplier" id="multiplierValue">1.00x</div>
                            <div class="sub" id="roundSubtext">{{ trans('cms_public.games.skyrush.place_before_takeoff') }}</div>
                    </div>
                </div>
                <div class="status-banner" id="statusBanner" role="status" aria-live="polite" aria-atomic="true">{{ trans('cms_public.games.skyrush.waiting_snapshot') }}</div>
                <div class="launch-strip">
                    <div class="launch-pill is-waiting" id="launchStateLabel" aria-live="polite" aria-atomic="true">{{ trans('cms_public.games.skyrush.takeoff_in') }}</div>
                    <div class="launch-progress"><span id="launchProgressBar"></span></div>
                </div>
            </div>
        </section>

        <aside class="side">
            <section class="panel">
                <h2 class="panel-title">{{ trans('cms_public.games.skyrush.bet_controls') }}</h2>
                <div class="lane-stack">
                    <div class="lane-panel">
                        <div class="lane-head">
                            <strong>{{ trans('cms_public.games.skyrush.lane', ['number' => 1]) }}</strong>
                            <span class="lane-state" id="laneState1">{{ trans('cms_public.games.common.ready') }}</span>
                        </div>
                        <div class="bet-grid" id="betGrid1"></div>
                        <div class="lane-selected">{{ trans('cms_public.games.common.stake') }} <strong id="selectedBetValue1">{{ number_format((float) ($payload['betValues'][0] ?? 0), 2) }} {{ $payload['currency'] }}</strong></div>
                        <button type="button" class="action-btn bet" id="betButton1" aria-keyshortcuts="B" title="{{ trans('cms_public.games.skyrush.place_bet_title') }}">{{ trans('cms_public.games.skyrush.place_bet') }}</button>
                        <div style="margin-top:8px">
                            <button type="button" class="action-btn cashout" id="cashoutButton1" style="display:none" aria-keyshortcuts="C" title="{{ trans('cms_public.games.skyrush.cash_out_title') }}">{{ trans('cms_public.games.skyrush.cash_out') }}</button>
                        </div>
                        <div class="auto-card">
                            <div class="auto-toggle-row">
                                <span>{{ trans('cms_public.games.skyrush.auto_bet') }}</span>
                                <input type="checkbox" class="auto-switch" id="autoBetToggle">
                            </div>
                            <div class="auto-toggle-row">
                                <span>{{ trans('cms_public.games.skyrush.repeat_last_stake') }}</span>
                                <input type="checkbox" class="auto-switch" id="repeatStakeToggle">
                            </div>
                            <div class="auto-toggle-row">
                                <span>{{ trans('cms_public.games.skyrush.auto_cashout') }}</span>
                                <input type="checkbox" class="auto-switch" id="autoCashoutToggle">
                            </div>
                            <div class="auto-toggle-row">
                                <span>{{ trans('cms_public.games.skyrush.stop_on_win') }}</span>
                                <input type="checkbox" class="auto-switch" id="stopOnWinToggle">
                            </div>
                            <div class="auto-toggle-row">
                                <span>{{ trans('cms_public.games.skyrush.stop_on_loss') }}</span>
                                <input type="checkbox" class="auto-switch" id="stopOnLossToggle">
                            </div>
                            <div class="auto-toggle-row">
                                <span>{{ trans('cms_public.games.skyrush.stop_low_balance') }}</span>
                                <input type="checkbox" class="auto-switch" id="stopLowBalanceToggle">
                            </div>
                            <div class="auto-input-row">
                                <span>{{ trans('cms_public.games.skyrush.auto_cashout_target') }}</span>
                                <input type="number" min="1.01" step="0.01" value="1.50" class="auto-input" id="autoCashoutTarget">
                            </div>
                            <div class="auto-input-row">
                                <span>{{ trans('cms_public.games.skyrush.minimum_balance_floor') }}</span>
                                <input type="number" min="0" step="0.01" value="25.00" class="auto-input" id="stopLowBalanceValue">
                            </div>
                            <div class="auto-note" id="autoStatusText">{{ trans('cms_public.games.skyrush.automation_idle') }}</div>
                        </div>
                    </div>
                    <div class="lane-panel">
                        <div class="lane-head">
                            <strong>{{ trans('cms_public.games.skyrush.lane', ['number' => 2]) }}</strong>
                            <span class="lane-state" id="laneState2">{{ trans('cms_public.games.common.ready') }}</span>
                        </div>
                        <div class="bet-grid" id="betGrid2"></div>
                        <div class="lane-selected">{{ trans('cms_public.games.common.stake') }} <strong id="selectedBetValue2">{{ number_format((float) ($payload['betValues'][0] ?? 0), 2) }} {{ $payload['currency'] }}</strong></div>
                        <button type="button" class="action-btn bet" id="betButton2">{{ trans('cms_public.games.skyrush.place_bet') }}</button>
                        <div style="margin-top:8px">
                            <button type="button" class="action-btn cashout" id="cashoutButton2" style="display:none">{{ trans('cms_public.games.skyrush.cash_out') }}</button>
                        </div>
                    </div>
                </div>
            </section>

            <section class="panel">
                <div class="panel-head">
                    <h2 class="panel-title" style="margin:0">{{ trans('cms_public.games.skyrush.round_monitor') }}</h2>
                    <button type="button" class="panel-link" id="roundDetailsButton" aria-keyshortcuts="V" title="{{ trans('cms_public.games.skyrush.open_pf_title') }}">{{ trans('cms_public.games.skyrush.provably_fair') }}</button>
                </div>
                <div class="meta-card">
                    <div class="meta-row"><span>{{ trans('cms_public.games.common.round') }}</span><strong id="crashRoundId">-</strong></div>
                    <div class="meta-row"><span>{{ trans('cms_public.games.common.selected_bets') }}</span><strong id="selectedBetValue">L1 {{ number_format((float) ($payload['betValues'][0] ?? 0), 2) }} • L2 {{ number_format((float) ($payload['betValues'][0] ?? 0), 2) }} {{ $payload['currency'] }}</strong></div>
                    <div class="meta-row"><span>{{ trans('cms_public.games.common.last_result') }}</span><strong id="lastOutcomeValue">{{ trans('cms_public.games.skyrush.no_settled_bet') }}</strong></div>
                    <div class="meta-row"><span>{{ trans('cms_public.games.common.status') }}</span><strong id="roundStatusValue">-</strong></div>
                    <div class="meta-row"><span>{{ trans('cms_public.games.skyrush.next_bet') }}</span><strong id="nextBetCountdownValue">-</strong></div>
                    <div class="meta-row"><span>{{ trans('cms_public.games.skyrush.crash_target') }}</span><strong id="crashTargetValue">{{ trans('cms_public.games.common.hidden') }}</strong></div>
                    <div class="meta-row"><span>{{ trans('cms_public.games.skyrush.my_bets') }}</span><strong id="myBetValue">{{ trans('cms_public.games.skyrush.lane_none', ['lane' => 1]) }} • {{ trans('cms_public.games.skyrush.lane_none', ['lane' => 2]) }}</strong></div>
                    <div class="meta-row"><span>{{ trans('cms_public.games.skyrush.cashouts') }}</span><strong id="myCashoutValue">L1: - • L2: -</strong></div>
                </div>
            </section>

            <section class="outcome" id="outcomeCard">
                <strong>{{ trans('cms_public.games.skyrush.waiting_for_bet') }}</strong>
                <div id="outcomeText" aria-live="polite" aria-atomic="true">{{ trans('cms_public.games.skyrush.betting_phase_help') }}</div>
            </section>

            <section class="panel">
                <h2 class="panel-title">{{ trans('cms_public.games.skyrush.live_bets_cashouts') }}</h2>
                <div class="feed-list" id="publicFeedList">
                    <div class="feed-item"><div><strong>{{ trans('cms_public.games.skyrush.no_live_feed') }}</strong><small>{{ trans('cms_public.games.skyrush.waiting_players') }}</small></div></div>
                </div>
            </section>

            <section class="panel">
                <h2 class="panel-title">{{ trans('cms_public.games.skyrush.my_bets') }}</h2>
                <div class="bets-list" id="myBetHistoryList">
                    <div class="bets-item"><strong>{{ trans('cms_public.games.skyrush.no_bets_yet') }}</strong><small>{{ trans('cms_public.games.skyrush.recent_crash_rounds') }}</small></div>
                </div>
            </section>
        </aside>
    </div>
</div>

<div class="sr-only" id="liveAnnouncer" aria-live="polite" aria-atomic="true"></div>

<div class="drawer" id="roundDrawer" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="roundDrawerTitle">
    <div class="drawer-panel">
        <div class="panel-head">
            <h2 class="panel-title" style="margin:0" id="roundDrawerTitle">{{ trans('cms_public.games.skyrush.drawer_title') }}</h2>
            <button type="button" class="panel-link" id="closeRoundDrawer" aria-keyshortcuts="Escape" title="{{ trans('cms_public.games.skyrush.close_drawer_title') }}">{{ trans('cms_public.games.common.close') }}</button>
        </div>
        <div class="drawer-grid">
            <div class="drawer-card"><span>{{ trans('cms_public.games.common.round_id') }}</span><strong id="drawerRoundId">-</strong></div>
            <div class="drawer-card"><span>{{ trans('cms_public.games.common.status') }}</span><strong id="drawerRoundStatus">-</strong></div>
            <div class="drawer-card"><span>{{ trans('cms_public.games.skyrush.current_crash') }}</span><strong id="drawerCrashValue">-</strong></div>
            <div class="drawer-card"><span>{{ trans('cms_public.games.skyrush.my_active_bets') }}</span><strong id="drawerActiveBets">-</strong></div>
            <div class="drawer-card"><span>{{ trans('cms_public.games.skyrush.server_seed_hash') }}</span><code id="drawerServerSeedHash">-</code></div>
            <div class="drawer-card"><span>{{ trans('cms_public.games.skyrush.fair_hash') }}</span><code id="drawerFairHash">-</code></div>
        </div>
        <div class="verify-status" id="verifyStatusCard">
            <div class="verify-pill is-pending" id="verifyStatusPill">{{ trans('cms_public.games.skyrush.verification_pending') }}</div>
            <div id="verifyStatusText">{{ trans('cms_public.games.skyrush.verify_text') }}</div>
            <ol class="verify-steps" id="verifySteps">
                <li>{{ trans('cms_public.games.skyrush.verify_step_1') }}</li>
                <li>{{ trans('cms_public.games.skyrush.verify_step_2') }}</li>
                <li>{!! trans('cms_public.games.skyrush.verify_step_3') !!}</li>
                <li>{{ trans('cms_public.games.skyrush.verify_step_4') }}</li>
            </ol>
            <div class="verify-grid">
                <div class="drawer-card"><span>{{ trans('cms_public.games.skyrush.revealed_server_seed') }}</span><code id="drawerRevealedServerSeed">{{ trans('cms_public.games.skyrush.pending_until_crash') }}</code></div>
                <div class="drawer-card"><span>{{ trans('cms_public.games.skyrush.computed_result') }}</span><strong id="drawerComputedResult">{{ trans('cms_public.games.common.pending') }}</strong></div>
            </div>
            <div class="verify-actions">
                <button type="button" class="verify-button" id="verifyNowButton">{{ trans('cms_public.games.skyrush.verify_now') }}</button>
                <button type="button" class="verify-button" id="copyRoundIdButton">{{ trans('cms_public.games.skyrush.copy_round_id') }}</button>
                <button type="button" class="verify-button" id="copyRevealButton">{{ trans('cms_public.games.skyrush.copy_reveal') }}</button>
            </div>
        </div>
        <div class="outcome" style="margin-top:14px">
            <strong>{{ trans('cms_public.games.skyrush.how_to_verify') }}</strong>
            <div id="drawerProvablyFairText">{{ trans('cms_public.games.skyrush.how_verify_text') }}</div>
        </div>
    </div>
</div>

@if($guestPreview)
    </div>
@else
<script>
(() => {
    const app = document.getElementById('skyRushApp');
    const payload = JSON.parse(app.dataset.payload || '{}');
    const skyI18n = @json(trans('cms_public.games.skyrush'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    const commonI18n = @json(trans('cms_public.games.common'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const BET_LANES = [1, 2];

    function textFrom(map, key, fallback, replacements = {}) {
        let value = Object.prototype.hasOwnProperty.call(map || {}, key) ? map[key] : fallback;
        if (typeof value !== 'string') value = fallback;
        Object.entries(replacements).forEach(([name, replacement]) => {
            value = value.replaceAll(`:${name}`, String(replacement));
        });
        return value;
    }

    const skyText = (key, fallback, replacements = {}) => textFrom(skyI18n, key, fallback, replacements);
    const commonText = (key, fallback, replacements = {}) => textFrom(commonI18n, key, fallback, replacements);

    function normalizeBetPayload(bet, fallbackLane = 1) {
        if (!bet || typeof bet !== 'object') {
            return null;
        }

        const lane = Number(bet.lane || fallbackLane || 1);
        return {
            ...bet,
            lane: lane === 2 ? 2 : 1,
        };
    }

    function normalizeMyBets(myBets, legacyMyBet = null) {
        const normalized = { '1': null, '2': null };

        if (myBets && typeof myBets === 'object') {
            BET_LANES.forEach((lane) => {
                const value = myBets[String(lane)] ?? myBets[lane] ?? null;
                normalized[String(lane)] = normalizeBetPayload(value, lane);
            });
        }

        if (legacyMyBet && !normalized[String(Number(legacyMyBet.lane || 1) === 2 ? 2 : 1)]) {
            const lane = Number(legacyMyBet.lane || 1) === 2 ? 2 : 1;
            normalized[String(lane)] = normalizeBetPayload(legacyMyBet, lane);
        }

        return normalized;
    }

    const state = {
        payload,
        selectedBets: {
            '1': Number((payload.betValues || [])[0] || 0),
            '2': Number((payload.betValues || [])[0] || 0),
        },
        snapshot: {
            balance: payload.balance,
            currency: payload.currency,
            currentRound: payload.currentRound,
            history: payload.history || [],
            myBet: normalizeBetPayload(payload.myBet || null),
            myBets: normalizeMyBets(payload.myBets, payload.myBet || null),
            publicFeed: payload.publicFeed || [],
            myBetHistory: payload.myBetHistory || [],
        },
        submittingByLane: { '1': false, '2': false },
        pollInFlight: false,
        lastSettledBetIds: {
            '1': payload.myBets?.['1']?.status?.startsWith?.('settled_') ? payload.myBets['1'].roundId : null,
            '2': payload.myBets?.['2']?.status?.startsWith?.('settled_') ? payload.myBets['2'].roundId : null,
        },
        render: {
            serverNowMs: payload.serverTime ? Date.parse(payload.serverTime) : Date.now(),
            clientSyncPerfMs: performance.now(),
            historySignature: '',
            lastStaticKey: '',
            lastPhaseKey: '',
            crashAnimationStartedAt: null,
            rafHandle: null,
            visualRoundId: payload.currentRound?.id || null,
            visualMultiplier: Number(payload.currentRound?.currentMultiplier || 1),
            lastCountdownText: '',
            lastFeedSignature: '',
            lastMyHistorySignature: '',
            soundEnabled: true,
            audioContext: null,
            audioUnlocked: false,
            masterGainNode: null,
            compressorNode: null,
            lastPhaseSoundKey: '',
            audioPrimed: false,
            engineNodes: null,
            ambienceNodes: null,
            lastCountdownWholeSecond: null,
            timingPhaseKey: '',
            monotonicRemainingMs: null,
            monotonicProgress: 0,
            lastLaunchText: '',
            canvasCtx: null,
            canvasWidth: 0,
            canvasHeight: 0,
            flightPath: null,
            lastMultiplierPaintMs: 0,
            lastMultiplierText: '',
            stageVisual: {
                roundId: null,
                mode: 'idle',
                enteredAt: 0,
                progress: 0,
                chargeProgress: 0,
                trailStrength: 0,
                takeoffBoost: 0,
                quickCrash: false,
            },
            motionVisual: {
                roundId: null,
                planeProgress: 0,
                trailProgress: 0,
                planeVelocity: 0,
                trailVelocity: 0,
                lastFrameMs: 0,
            },
            connectionState: 'live',
            lastSuccessfulSnapshotAt: Date.now(),
            failedSnapshotCount: 0,
            recoveryNoticeShown: false,
            recoveryNoticeUntil: 0,
            latestUiSyncStamp: 0,
            walletMessageSequence: 0,
        },
        automation: {
            autoBetEnabled: false,
            autoCashoutEnabled: false,
            autoCashoutTarget: 1.5,
            repeatLastStakeEnabled: true,
            stopOnWinEnabled: false,
            stopOnLossEnabled: false,
            stopLowBalanceEnabled: false,
            stopLowBalanceValue: 25,
            lastBetAmount: Number((payload.betValues || [])[0] || 0),
            autoBetRoundId: null,
            autoCashoutBetRoundId: null,
            statusText: skyText('automation_idle', 'Automation is idle. Auto bet places the current Lane 1 stake at the start of each betting window.'),
        },
    };

    const nodes = {
        balanceValue: document.getElementById('balanceValue'),
        currencyValue: document.getElementById('currencyValue'),
        connectionChip: document.getElementById('connectionChip'),
        modeChip: document.getElementById('modeChip'),
        historyStrip: document.getElementById('historyStrip'),
        roundPhaseLabel: document.getElementById('roundPhaseLabel'),
        multiplierValue: document.getElementById('multiplierValue'),
        roundSubtext: document.getElementById('roundSubtext'),
        statusBanner: document.getElementById('statusBanner'),
        crashRoundId: document.getElementById('crashRoundId'),
        selectedBetValue: document.getElementById('selectedBetValue'),
        selectedBetValues: {
            '1': document.getElementById('selectedBetValue1'),
            '2': document.getElementById('selectedBetValue2'),
        },
        betGrids: {
            '1': document.getElementById('betGrid1'),
            '2': document.getElementById('betGrid2'),
        },
        betButtons: {
            '1': document.getElementById('betButton1'),
            '2': document.getElementById('betButton2'),
        },
        cashoutButtons: {
            '1': document.getElementById('cashoutButton1'),
            '2': document.getElementById('cashoutButton2'),
        },
        laneStates: {
            '1': document.getElementById('laneState1'),
            '2': document.getElementById('laneState2'),
        },
        autoBetToggle: document.getElementById('autoBetToggle'),
        repeatStakeToggle: document.getElementById('repeatStakeToggle'),
        autoCashoutToggle: document.getElementById('autoCashoutToggle'),
        stopOnWinToggle: document.getElementById('stopOnWinToggle'),
        stopOnLossToggle: document.getElementById('stopOnLossToggle'),
        stopLowBalanceToggle: document.getElementById('stopLowBalanceToggle'),
        stopLowBalanceValue: document.getElementById('stopLowBalanceValue'),
        autoCashoutTarget: document.getElementById('autoCashoutTarget'),
        autoStatusText: document.getElementById('autoStatusText'),
        roundStatusValue: document.getElementById('roundStatusValue'),
        nextBetCountdownValue: document.getElementById('nextBetCountdownValue'),
        crashTargetValue: document.getElementById('crashTargetValue'),
        myBetValue: document.getElementById('myBetValue'),
        myCashoutValue: document.getElementById('myCashoutValue'),
        lastOutcomeValue: document.getElementById('lastOutcomeValue'),
        outcomeCard: document.getElementById('outcomeCard'),
        outcomeText: document.getElementById('outcomeText'),
        publicFeedList: document.getElementById('publicFeedList'),
        myBetHistoryList: document.getElementById('myBetHistoryList'),
        flightCanvas: document.getElementById('flightCanvas'),
        skyStage: document.getElementById('skyStage'),
        launchStateLabel: document.getElementById('launchStateLabel'),
        launchProgressBar: document.getElementById('launchProgressBar'),
        soundToggle: document.getElementById('soundToggle'),
        roundDetailsButton: document.getElementById('roundDetailsButton'),
        roundDrawer: document.getElementById('roundDrawer'),
        closeRoundDrawer: document.getElementById('closeRoundDrawer'),
        drawerRoundId: document.getElementById('drawerRoundId'),
        drawerRoundStatus: document.getElementById('drawerRoundStatus'),
        drawerCrashValue: document.getElementById('drawerCrashValue'),
        drawerActiveBets: document.getElementById('drawerActiveBets'),
        drawerServerSeedHash: document.getElementById('drawerServerSeedHash'),
        drawerFairHash: document.getElementById('drawerFairHash'),
        drawerProvablyFairText: document.getElementById('drawerProvablyFairText'),
        drawerRevealedServerSeed: document.getElementById('drawerRevealedServerSeed'),
        drawerComputedResult: document.getElementById('drawerComputedResult'),
        verifyStatusPill: document.getElementById('verifyStatusPill'),
        verifyStatusText: document.getElementById('verifyStatusText'),
        verifySteps: document.getElementById('verifySteps'),
        verifyNowButton: document.getElementById('verifyNowButton'),
        copyRoundIdButton: document.getElementById('copyRoundIdButton'),
        copyRevealButton: document.getElementById('copyRevealButton'),
        liveAnnouncer: document.getElementById('liveAnnouncer'),
    };

    function money(amount) {
        return `${Number(amount || 0).toFixed(2)} ${state.snapshot.currency || payload.currency}`;
    }

    function requestKey() {
        return (window.crypto && window.crypto.randomUUID)
            ? window.crypto.randomUUID()
            : `${Date.now()}-${Math.random().toString(16).slice(2)}`;
    }

    function postParentWalletUpdate(data, requestRef = null, stamp = Date.now()) {
        if (payload.isDemo !== false || !window.parent || window.parent === window) {
            return;
        }

        const balance = Number(data && data.balance);
        if (!Number.isFinite(balance)) {
            return;
        }

        state.render.walletMessageSequence += 1;
        window.parent.postMessage({
            type: 'internal-game:wallet-updated',
            source: 'skyrush',
            roundId: data && data.roundId ? data.roundId : null,
            requestRef: data && data.requestKey ? data.requestKey : requestRef,
            balance,
            currency: data && data.currency ? data.currency : (state.snapshot.currency || payload.currency || ''),
            timestamp: stamp,
            sequence: state.render.walletMessageSequence,
        }, window.location.origin);
    }

    function applyCanonicalBalance(data, { requestRef = null, stamp = Date.now(), emitParent = true } = {}) {
        if (!data || data.balance == null || stamp < state.render.latestUiSyncStamp) {
            return false;
        }

        const balance = Number(data.balance);
        if (!Number.isFinite(balance)) {
            return false;
        }

        state.snapshot.balance = balance;
        state.snapshot.balanceExact = data.balanceExact;
        payload.balance = balance;

        if (typeof data.currency === 'string' && data.currency !== '') {
            state.snapshot.currency = data.currency;
            payload.currency = data.currency;
        }

        state.render.latestUiSyncStamp = stamp;

        if (emitParent) {
            postParentWalletUpdate(data, requestRef, stamp);
        }

        return true;
    }

    function roundStillAcceptsBets(round) {
        if ((round?.status || '') !== 'betting') {
            return false;
        }

        const bettingClosesAtMs = round?.bettingClosesAt ? Date.parse(round.bettingClosesAt) : null;
        if (!Number.isFinite(bettingClosesAtMs)) {
            return false;
        }

        return estimatedServerNowMs() < bettingClosesAtMs;
    }

    function announceLive(message) {
        if (!nodes.liveAnnouncer || !message) {
            return;
        }

        if (nodes.liveAnnouncer.textContent === message) {
            return;
        }

        nodes.liveAnnouncer.textContent = '';
        window.setTimeout(() => {
            nodes.liveAnnouncer.textContent = message;
        }, 20);
    }

    function phaseLabel(status) {
        return status === 'betting' ? skyText('taking_bets', 'Taking Bets')
            : status === 'flying' ? skyText('live_flight', 'Live Flight')
            : status === 'crashed' ? skyText('crash', 'Crash')
            : status;
    }

    function laneBet(lane) {
        return state.snapshot.myBets?.[String(lane)] || null;
    }

    function hasAnySubmittingLane() {
        return BET_LANES.some((lane) => Boolean(state.submittingByLane[String(lane)]));
    }

    function activeBets() {
        return BET_LANES
            .map((lane) => laneBet(lane))
            .filter((bet) => bet && ['bet_accepted', 'processing'].includes(bet.status));
    }

    function settledBets() {
        return BET_LANES
            .map((lane) => laneBet(lane))
            .filter((bet) => bet && ['settled_win', 'settled_loss'].includes(bet.status));
    }

    function laneStatusLabel(bet) {
        if (!bet) return commonText('ready', 'Ready');
        if (bet.status === 'bet_accepted') return skyText('bet_placed', 'Bet placed');
        if (bet.status === 'settled_win') return skyText('cashed_out', 'Cashed out');
        if (bet.status === 'settled_loss') return skyText('crashed', 'Crashed');
        if (bet.status === 'refunded') return skyText('refunded', 'Refunded');

        return bet.status;
    }

    function visibleLanes() {
        return [1, 2];
    }

    function syncAutoUi() {
        nodes.autoBetToggle.checked = state.automation.autoBetEnabled;
        nodes.repeatStakeToggle.checked = state.automation.repeatLastStakeEnabled;
        nodes.autoCashoutToggle.checked = state.automation.autoCashoutEnabled;
        nodes.stopOnWinToggle.checked = state.automation.stopOnWinEnabled;
        nodes.stopOnLossToggle.checked = state.automation.stopOnLossEnabled;
        nodes.stopLowBalanceToggle.checked = state.automation.stopLowBalanceEnabled;
        const normalizedTarget = Number(state.automation.autoCashoutTarget || 1.5);
        if (Number(nodes.autoCashoutTarget.value || 0) !== normalizedTarget) {
            nodes.autoCashoutTarget.value = normalizedTarget.toFixed(2);
        }
        const normalizedFloor = Number(state.automation.stopLowBalanceValue || 0);
        if (Number(nodes.stopLowBalanceValue.value || 0) !== normalizedFloor) {
            nodes.stopLowBalanceValue.value = normalizedFloor.toFixed(2);
        }
        nodes.autoStatusText.textContent = state.automation.statusText;
    }

    function setAutoStatus(text) {
        state.automation.statusText = text;
        if (nodes.autoStatusText) {
            nodes.autoStatusText.textContent = text;
        }
    }

    function disableAutomation(reason) {
        state.automation.autoBetEnabled = false;
        state.automation.autoCashoutEnabled = false;
        state.automation.autoBetRoundId = null;
        state.automation.autoCashoutBetRoundId = null;
        setAutoStatus(reason);
        syncAutoUi();
    }

    function relativeTimeLabel(value) {
        if (!value) {
            return skyText('just_now', 'just now');
        }

        const diffMs = Math.max(0, Date.now() - Date.parse(value));
        const seconds = Math.floor(diffMs / 1000);
        if (seconds < 5) return skyText('just_now', 'just now');
        if (seconds < 60) return `${seconds}s ago`;
        const minutes = Math.floor(seconds / 60);
        return `${minutes}m ago`;
    }

    function humanizeRequestError(message) {
        const text = String(message || '').toLowerCase();
        if (text.includes('cash out') && (text.includes('closed') || text.includes('crash'))) {
            return skyText('cashout_window_closed', 'Cash out window closed; the request reached the server after the crash.');
        }
        if (text.includes('cashout') && text.includes('409')) {
            return skyText('cashout_window_closed', 'Cash out window closed; the request reached the server after the crash.');
        }
        if (text.includes('insufficient') || text.includes('409')) {
            return skyText('insufficient_balance', 'Insufficient balance for this bet amount.');
        }
        if (text.includes('stale') || text.includes('expired')) {
            return skyText('session_expired', 'This session expired. Reload the game to rejoin safely.');
        }
        if (text.includes('unauthorized') || text.includes('403')) {
            return skyText('unauthorized', 'This action is not authorized for the current wallet context.');
        }
        if (text.includes('lane') && text.includes('active bet')) {
            return skyText('active_bet_exists', 'This lane already has an active bet in the current round.');
        }
        return message || commonText('request_rejected', 'Request rejected.');
    }

    function feedMarkup(item) {
        const isCashout = item.type === 'cashout';
        const main = isCashout
            ? `${Number(item.cashoutAmount || 0).toFixed(2)} @ ${Number(item.cashoutMultiplier || 0).toFixed(2)}x`
            : `${Number(item.betAmount || 0).toFixed(2)} bet`;

        return `
            <div class="feed-item${isCashout ? ' is-cashout' : ''}">
                <div>
                    <strong>${item.player} • ${isCashout ? skyText('cashout_label', 'Cashout') : skyText('bet_label', 'Bet')}</strong>
                    <small>${relativeTimeLabel(item.createdAt)} • ${skyText('lane', 'Lane :number', { number: item.lane })}</small>
                </div>
                <strong>${main}</strong>
            </div>
        `;
    }

    function myBetHistoryMarkup(item) {
        const itemClass = item.status === 'settled_win'
            ? 'bets-item is-win'
            : item.status === 'settled_loss'
                ? 'bets-item is-loss'
                : 'bets-item';
        const summary = item.resultSummary || `${item.status} • ${Number(item.betAmount || 0).toFixed(2)}`;
        const detail = item.status === 'settled_win'
            ? `${commonText('win', 'Win')} ${Number(item.winAmount || 0).toFixed(2)} @ ${Number(item.cashoutMultiplier || 0).toFixed(2)}x`
            : `${commonText('stake', 'Stake')} ${Number(item.betAmount || 0).toFixed(2)} • ${skyText('lane', 'Lane :number', { number: item.lane })}`;

        return `
            <div class="${itemClass}">
                <strong>${summary}</strong>
                <small>${detail}</small>
            </div>
        `;
    }

    function syncPublicFeed() {
        const signature = JSON.stringify((state.snapshot.publicFeed || []).map((item) => [item.roundId, item.type, item.createdAt]));
        if (signature === state.render.lastFeedSignature) {
            return;
        }

        state.render.lastFeedSignature = signature;
        if (!state.snapshot.publicFeed || state.snapshot.publicFeed.length === 0) {
            nodes.publicFeedList.innerHTML = `<div class="feed-item"><div><strong>${skyText('no_live_feed', 'No live feed yet')}</strong><small>${skyText('waiting_players', 'Waiting for players.')}</small></div></div>`;
            return;
        }

        nodes.publicFeedList.innerHTML = state.snapshot.publicFeed.map(feedMarkup).join('');
    }

    function syncMyBetHistory() {
        const signature = JSON.stringify((state.snapshot.myBetHistory || []).map((item) => [item.roundId, item.status, item.settledAt, item.startedAt]));
        if (signature === state.render.lastMyHistorySignature) {
            return;
        }

        state.render.lastMyHistorySignature = signature;
        if (!state.snapshot.myBetHistory || state.snapshot.myBetHistory.length === 0) {
            nodes.myBetHistoryList.innerHTML = `<div class="bets-item"><strong>${skyText('no_bets_yet', 'No bets yet')}</strong><small>${skyText('recent_crash_rounds', 'Your recent crash rounds will appear here.')}</small></div>`;
            return;
        }

        nodes.myBetHistoryList.innerHTML = state.snapshot.myBetHistory.map(myBetHistoryMarkup).join('');
    }

    function updateConnectionState(nextState) {
        state.render.connectionState = nextState;
        nodes.connectionChip.className = 'chip';
        if (nextState === 'live') {
            nodes.connectionChip.classList.add('is-live');
            nodes.connectionChip.textContent = commonText('live', 'Live');
        } else if (nextState === 'delayed') {
            nodes.connectionChip.classList.add('is-delayed');
            nodes.connectionChip.textContent = commonText('delayed', 'Delayed');
        } else {
            nodes.connectionChip.classList.add('is-reconnecting');
            nodes.connectionChip.textContent = commonText('reconnecting', 'Reconnecting');
        }
    }

    function syncRoundDrawer() {
        const round = state.snapshot.currentRound || {};
        const active = activeBets();
        nodes.drawerRoundId.textContent = round.id || '-';
        nodes.drawerRoundStatus.textContent = phaseLabel(round.status || '-');
        nodes.drawerCrashValue.textContent = round.status === 'crashed' && round.crashMultiplier
            ? `${Number(round.crashMultiplier).toFixed(2)}x`
            : `${Number(round.currentMultiplier || 1).toFixed(2)}x live`;
        nodes.drawerActiveBets.textContent = active.length > 0
            ? active.map((bet) => `L${bet.lane} ${Number(bet.betAmount || 0).toFixed(2)}`).join(' • ')
            : skyText('no_active_bets', 'No active bets');
        nodes.drawerServerSeedHash.textContent = round.serverSeedHash || '-';
        nodes.drawerFairHash.textContent = round.fairHash || '-';
        nodes.drawerRevealedServerSeed.textContent = round.revealedServerSeed || skyText('pending_until_crash', 'Pending until crash');
        nodes.drawerComputedResult.textContent = round.status === 'crashed'
            ? `Crash result ${Number(round.crashMultiplier || 0).toFixed(2)}x`
            : commonText('pending', 'Pending');
        nodes.drawerProvablyFairText.textContent = round.status === 'crashed' && round.crashMultiplier
            ? `Round ${round.id} was precommitted before takeoff. The current crash point resolved at ${Number(round.crashMultiplier).toFixed(2)}x and must remain consistent with the published seed and fair hashes above.`
            : `Round ${round.id || '-' } is already precommitted before takeoff. The seed hash and fair hash above are the commitments for the currently open round.`;
        updateVerificationUi({
            state: 'pending',
            text: round.status === 'crashed' ? skyText('verification_ready', 'Verification ready. Run the local check below.') : skyText('verify_text', 'The round is precommitted before takeoff. Once it crashes, the revealed server seed can be verified against the published hashes.'),
        });
    }

    function updateVerificationUi({ state: verifyState, text, computedResult = null }) {
        nodes.verifyStatusPill.className = 'verify-pill';
        if (verifyState === 'ok') {
            nodes.verifyStatusPill.classList.add('is-ok');
            nodes.verifyStatusPill.textContent = skyText('verification_passed', 'Verification passed');
        } else if (verifyState === 'fail') {
            nodes.verifyStatusPill.classList.add('is-fail');
            nodes.verifyStatusPill.textContent = skyText('verification_failed', 'Verification failed');
        } else {
            nodes.verifyStatusPill.classList.add('is-pending');
            nodes.verifyStatusPill.textContent = skyText('verification_pending', 'Verification pending');
        }
        nodes.verifyStatusText.textContent = text;
        announceLive(text);
        if (computedResult) {
            nodes.drawerComputedResult.textContent = computedResult;
        }
    }

    async function sha256Hex(value) {
        const input = new TextEncoder().encode(String(value || ''));
        const hashBuffer = await crypto.subtle.digest('SHA-256', input);
        return Array.from(new Uint8Array(hashBuffer)).map((byte) => byte.toString(16).padStart(2, '0')).join('');
    }

    function deriveCrashMultiplierFromFairHash(fairHash, verification) {
        const slice = String(fairHash || '').slice(0, 13);
        const max = parseInt('fffffffffffff', 16);
        const ratio = max > 0 ? (parseInt(slice || '0', 16) / max) : 0;
        const houseEdge = Number(verification?.houseEdge || 0.965);
        const minMultiplier = Number(verification?.minCrashMultiplier || 1.08);
        const maxMultiplier = Number(verification?.maxCrashMultiplier || 25);
        const raw = houseEdge * (1 / Math.max(0.04, 1 - ratio));
        return Number(Math.min(maxMultiplier, Math.max(minMultiplier, raw)).toFixed(2));
    }

    async function verifyCurrentRound() {
        const round = state.snapshot.currentRound || {};
        if (round.status !== 'crashed' || !round.revealedServerSeed) {
            updateVerificationUi({
                state: 'pending',
                text: skyText('pending_until_crash', 'Pending until crash'),
                computedResult: commonText('pending', 'Pending'),
            });
            return;
        }

        try {
            const computedSeedHash = await sha256Hex(round.revealedServerSeed);
            const computedFairHash = await sha256Hex(`${round.revealedServerSeed}|${round.id}`);
            const computedCrash = deriveCrashMultiplierFromFairHash(computedFairHash, round.verification || {});
            const expectedCrash = Number(round.crashMultiplier || 0).toFixed(2);
            const passed = computedSeedHash === String(round.serverSeedHash || '')
                && computedFairHash === String(round.fairHash || '')
                && Number(computedCrash).toFixed(2) === expectedCrash;

            updateVerificationUi({
                state: passed ? 'ok' : 'fail',
                text: passed
                    ? skyText('verification_passed', 'Verification passed')
                    : skyText('verification_failed', 'Verification failed'),
                computedResult: `${computedCrash.toFixed(2)}x computed`,
            });
        } catch (_error) {
            updateVerificationUi({
                state: 'fail',
                text: skyText('verification_failed', 'Verification failed'),
                computedResult: commonText('error', 'Error'),
            });
        }
    }

    async function copyVerificationValue(value, successText) {
        try {
            await navigator.clipboard.writeText(String(value || ''));
            nodes.statusBanner.innerHTML = `<strong>${skyText('copied', 'Copied')}</strong> • ${successText}`;
            announceLive(successText);
        } catch (_error) {
            nodes.statusBanner.innerHTML = `<strong>${skyText('copy_failed', 'Copy failed')}</strong> • ${skyText('copy_failed_detail', 'Clipboard permission was not available.')}`;
            announceLive(`${skyText('copy_failed', 'Copy failed')}. ${skyText('copy_failed_detail', 'Clipboard permission was not available.')}`);
        }
    }

    function updateSoundToggle() {
        nodes.soundToggle.textContent = state.render.soundEnabled ? skyText('sound_on', 'Sound On') : skyText('sound_off', 'Sound Off');
        nodes.soundToggle.classList.toggle('is-muted', !state.render.soundEnabled);
    }

    function ensureAudioContext() {
        if (state.render.audioContext) {
            return state.render.audioContext;
        }

        const Context = window.AudioContext || window.webkitAudioContext;
        if (!Context) {
            return null;
        }

        state.render.audioContext = new Context();
        const masterGainNode = state.render.audioContext.createGain();
        const compressorNode = state.render.audioContext.createDynamicsCompressor();
        masterGainNode.gain.value = 0.9;
        compressorNode.threshold.value = -18;
        compressorNode.knee.value = 18;
        compressorNode.ratio.value = 3;
        compressorNode.attack.value = 0.003;
        compressorNode.release.value = 0.18;
        masterGainNode.connect(compressorNode);
        compressorNode.connect(state.render.audioContext.destination);
        state.render.masterGainNode = masterGainNode;
        state.render.compressorNode = compressorNode;
        return state.render.audioContext;
    }

    async function unlockAudio() {
        const ctx = ensureAudioContext();
        if (!ctx) {
            return null;
        }

        if (ctx.state === 'suspended') {
            try {
                await ctx.resume();
            } catch (_error) {
                return null;
            }
        }

        if (!state.render.audioPrimed) {
            try {
                const oscillator = ctx.createOscillator();
                const gainNode = ctx.createGain();
                gainNode.gain.value = 0.00001;
                oscillator.frequency.value = 440;
                oscillator.connect(gainNode);
                gainNode.connect(state.render.masterGainNode || ctx.destination);
                oscillator.start();
                oscillator.stop(ctx.currentTime + 0.01);
                state.render.audioPrimed = true;
            } catch (_error) {
                return null;
            }
        }

        state.render.audioUnlocked = true;
        return ctx;
    }

    function armAudioUnlock() {
        const prime = async () => {
            await activateAudioLandscape({ ping: true });
            window.removeEventListener('pointerdown', prime);
            window.removeEventListener('keydown', prime);
        };

        window.addEventListener('pointerdown', prime, { once: true });
        window.addEventListener('keydown', prime, { once: true });
    }

    async function activateAudioLandscape({ ping = false } = {}) {
        const ctx = await unlockAudio();
        if (!ctx) {
            return null;
        }

        startBackgroundAmbience();
        const round = state.snapshot?.currentRound || {};
        const multiplier = Number(state.render.visualMultiplier || round?.currentMultiplier || 1);
        updateBackgroundAmbience(multiplier, round?.status || 'betting');
        if ((round?.status || '') === 'flying') {
            startEngineSound();
            updateEngineSound(multiplier, round.status);
        }

        if (ping) {
            playTone({ frequency: 920, rampTo: 1040, type: 'triangle', duration: 0.08, gain: 0.08 });
        }

        return ctx;
    }

    function primeAudioLandscape(options = {}) {
        activateAudioLandscape(options).catch(() => null);
    }

    function playTone({ frequency, type = 'sine', duration = 0.12, gain = 0.04, rampTo = null, delay = 0 }) {
        if (!state.render.soundEnabled) {
            return;
        }

        const ctx = ensureAudioContext();
        if (!ctx || ctx.state !== 'running') {
            return;
        }

        const startAt = ctx.currentTime + delay;
        const endAt = startAt + duration;
        const oscillator = ctx.createOscillator();
        const gainNode = ctx.createGain();

        oscillator.type = type;
        oscillator.frequency.setValueAtTime(frequency, startAt);
        if (rampTo) {
            oscillator.frequency.exponentialRampToValueAtTime(Math.max(1, rampTo), endAt);
        }

        gainNode.gain.setValueAtTime(0.0001, startAt);
        gainNode.gain.exponentialRampToValueAtTime(gain, startAt + Math.min(0.03, duration / 2));
        gainNode.gain.exponentialRampToValueAtTime(0.0001, endAt);

        oscillator.connect(gainNode);
        gainNode.connect(state.render.masterGainNode || ctx.destination);
        oscillator.start(startAt);
        oscillator.stop(endAt + 0.01);
    }

    function playTakeoffSound() {
        playTone({ frequency: 320, rampTo: 720, type: 'sawtooth', duration: 0.34, gain: 0.2 });
        playTone({ frequency: 720, rampTo: 1020, type: 'triangle', duration: 0.18, gain: 0.14, delay: 0.14 });
    }

    function playCrashSound() {
        playTone({ frequency: 420, rampTo: 72, type: 'sawtooth', duration: 0.46, gain: 0.22 });
    }

    function playCashoutSound() {
        playTone({ frequency: 920, rampTo: 1240, type: 'triangle', duration: 0.16, gain: 0.18 });
        playTone({ frequency: 1240, rampTo: 1640, type: 'triangle', duration: 0.22, gain: 0.15, delay: 0.08 });
    }

    function playLossSound() {
        playTone({ frequency: 320, rampTo: 150, type: 'square', duration: 0.24, gain: 0.13 });
    }

    function startBackgroundAmbience() {
        if (!state.render.soundEnabled) {
            return;
        }

        const ctx = ensureAudioContext();
        if (!ctx || ctx.state !== 'running' || state.render.ambienceNodes || !state.render.audioUnlocked) {
            return;
        }

        const rumble = ctx.createOscillator();
        const base = ctx.createOscillator();
        const shimmer = ctx.createOscillator();
        const pad = ctx.createOscillator();
        const lfo = ctx.createOscillator();
        const filter = ctx.createBiquadFilter();
        const gainNode = ctx.createGain();
        const lfoGain = ctx.createGain();

        rumble.type = 'triangle';
        base.type = 'triangle';
        shimmer.type = 'sine';
        pad.type = 'triangle';
        lfo.type = 'sine';
        rumble.frequency.value = 74;
        base.frequency.value = 148;
        shimmer.frequency.value = 296;
        pad.frequency.value = 222;
        lfo.frequency.value = 0.12;
        filter.type = 'lowpass';
        filter.frequency.value = 1640;
        gainNode.gain.value = 0.0001;
        lfoGain.gain.value = 0.0035;

        rumble.connect(filter);
        base.connect(filter);
        shimmer.connect(filter);
        pad.connect(filter);
        filter.connect(gainNode);
        gainNode.connect(state.render.masterGainNode || ctx.destination);
        lfo.connect(lfoGain);
        lfoGain.connect(gainNode.gain);

        rumble.start();
        base.start();
        shimmer.start();
        pad.start();
        lfo.start();
        gainNode.gain.exponentialRampToValueAtTime(0.032, ctx.currentTime + 0.24);

        state.render.ambienceNodes = { rumble, base, shimmer, pad, lfo, filter, gainNode, lfoGain };
    }

    function updateBackgroundAmbience(multiplier, status) {
        const ctx = ensureAudioContext();
        const nodeset = state.render.ambienceNodes;
        if (!ctx || !nodeset) {
            return;
        }

        if (!state.render.soundEnabled || ctx.state !== 'running') {
            stopBackgroundAmbience();
            return;
        }

        const energy = status === 'flying'
            ? Math.min(1, Math.max(0, (multiplier - 1) / 2.2))
            : status === 'betting'
                ? 0.18
                : 0.08;

        nodeset.rumble.frequency.setTargetAtTime(74 + (energy * 8), ctx.currentTime, 0.22);
        nodeset.base.frequency.setTargetAtTime(148 + (energy * 20), ctx.currentTime, 0.2);
        nodeset.shimmer.frequency.setTargetAtTime(296 + (energy * 72), ctx.currentTime, 0.2);
        nodeset.pad.frequency.setTargetAtTime(222 + (energy * 30), ctx.currentTime, 0.22);
        nodeset.filter.frequency.setTargetAtTime(1480 + (energy * 760), ctx.currentTime, 0.22);
        nodeset.lfo.frequency.setTargetAtTime(0.1 + (energy * 0.05), ctx.currentTime, 0.3);
        nodeset.lfoGain.gain.setTargetAtTime(0.0025 + (energy * 0.003), ctx.currentTime, 0.3);
        nodeset.gainNode.gain.setTargetAtTime(0.024 + (energy * 0.02), ctx.currentTime, 0.24);
    }

    function stopBackgroundAmbience() {
        const ctx = ensureAudioContext();
        const nodeset = state.render.ambienceNodes;
        if (!ctx || !nodeset) {
            state.render.ambienceNodes = null;
            return;
        }

        try {
            nodeset.gainNode.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.18);
            nodeset.rumble.stop(ctx.currentTime + 0.22);
            nodeset.base.stop(ctx.currentTime + 0.22);
            nodeset.shimmer.stop(ctx.currentTime + 0.22);
            nodeset.pad.stop(ctx.currentTime + 0.22);
            nodeset.lfo.stop(ctx.currentTime + 0.22);
        } catch (_error) {
        }

        state.render.ambienceNodes = null;
    }

    function startEngineSound() {
        if (!state.render.soundEnabled) {
            return;
        }

        const ctx = ensureAudioContext();
        if (!ctx || ctx.state !== 'running' || state.render.engineNodes || !state.render.audioUnlocked) {
            return;
        }

        const carrier = ctx.createOscillator();
        const layer = ctx.createOscillator();
        const filter = ctx.createBiquadFilter();
        const gainNode = ctx.createGain();

        carrier.type = 'sawtooth';
        layer.type = 'triangle';
        carrier.frequency.value = 140;
        layer.frequency.value = 210;
        filter.type = 'lowpass';
        filter.frequency.value = 1280;
        gainNode.gain.value = 0.0001;

        carrier.connect(filter);
        layer.connect(filter);
        filter.connect(gainNode);
        gainNode.connect(state.render.masterGainNode || ctx.destination);

        carrier.start();
        layer.start();
        gainNode.gain.exponentialRampToValueAtTime(0.075, ctx.currentTime + 0.16);

        state.render.engineNodes = { carrier, layer, filter, gainNode };
    }

    function updateEngineSound(multiplier, status) {
        const ctx = ensureAudioContext();
        const nodeset = state.render.engineNodes;
        if (!ctx || !nodeset) {
            return;
        }

        if (!state.render.soundEnabled || status !== 'flying' || ctx.state !== 'running') {
            stopEngineSound();
            return;
        }

        const rpm = Math.min(1, Math.max(0, (multiplier - 1) / 3));
        nodeset.carrier.frequency.setTargetAtTime(140 + (rpm * 120), ctx.currentTime, 0.08);
        nodeset.layer.frequency.setTargetAtTime(210 + (rpm * 180), ctx.currentTime, 0.08);
        nodeset.filter.frequency.setTargetAtTime(1280 + (rpm * 920), ctx.currentTime, 0.08);
        nodeset.gainNode.gain.setTargetAtTime(0.055 + (rpm * 0.05), ctx.currentTime, 0.12);
    }

    function stopEngineSound() {
        const ctx = ensureAudioContext();
        const nodeset = state.render.engineNodes;
        if (!ctx || !nodeset) {
            state.render.engineNodes = null;
            return;
        }

        try {
            nodeset.gainNode.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.14);
            nodeset.carrier.stop(ctx.currentTime + 0.16);
            nodeset.layer.stop(ctx.currentTime + 0.16);
        } catch (_error) {
        }

        state.render.engineNodes = null;
    }

    function playCountdownTick(secondsLeft) {
        const freq = secondsLeft <= 1 ? 1080 : 820;
        playTone({ frequency: freq, rampTo: freq * 1.04, type: 'square', duration: 0.05, gain: secondsLeft <= 1 ? 0.08 : 0.05 });
    }

    function triggerCrashVisual() {
        if (!nodes.skyStage) {
            return;
        }

        nodes.skyStage.classList.remove('is-crashing');
        void nodes.skyStage.offsetWidth;
        nodes.skyStage.classList.add('is-crashing');
    }

    function syncTimingState(phaseKey, remainingMs, progress) {
        if (state.render.timingPhaseKey !== phaseKey) {
            state.render.timingPhaseKey = phaseKey;
            state.render.monotonicRemainingMs = remainingMs;
            state.render.monotonicProgress = progress;

            return {
                remainingMs,
                progress,
            };
        }

        if (typeof remainingMs === 'number') {
            state.render.monotonicRemainingMs = state.render.monotonicRemainingMs === null
                ? remainingMs
                : Math.min(state.render.monotonicRemainingMs, remainingMs);
        }

        state.render.monotonicProgress = Math.max(state.render.monotonicProgress || 0, progress || 0);

        return {
            remainingMs: state.render.monotonicRemainingMs,
            progress: state.render.monotonicProgress,
        };
    }

    function computeTimeline(round, visualStatus) {
        const nowMs = estimatedServerNowMs();
        const bettingClosesAtMs = round?.bettingClosesAt ? Date.parse(round.bettingClosesAt) : null;
        const startsAtMs = round?.startsAt ? Date.parse(round.startsAt) : null;
        const crashedAtMs = round?.crashedAt ? Date.parse(round.crashedAt) : null;
        const timing = timingConfig();
        const roundId = round?.id || 'none';

        if (visualStatus === 'betting' && bettingClosesAtMs && startsAtMs) {
            const rawRemaining = Math.max(0, bettingClosesAtMs - nowMs);
            const timingState = syncTimingState(
                `${roundId}:betting`,
                rawRemaining,
                Math.max(0, Math.min(1, 1 - (rawRemaining / (timing.bettingWindowSeconds * 1000))))
            );
            const remaining = timingState.remainingMs ?? rawRemaining;

            return {
                statusClass: 'launch-pill is-waiting',
                launchLabel: remaining <= 1800 ? skyText('final_call', 'Final call before takeoff') : skyText('betting_window_open', 'Betting window is open.'),
                countdownLabel: skyText('closes_in', 'Closes in :time', { time: formatCountdownMs(remaining) }),
                progress: timingState.progress,
                remainingMs: remaining,
            };
        }

        if (visualStatus === 'flying' && startsAtMs && crashedAtMs) {
            const total = Math.max(1, crashedAtMs - startsAtMs);
            const rawProgress = Math.max(0, Math.min(1, Math.max(0, nowMs - startsAtMs) / total));
            const timingState = syncTimingState(`${roundId}:flying`, null, rawProgress);
            const liveMultiplier = Number(state.render.visualMultiplier || round?.currentMultiplier || 1).toFixed(2);

            return {
                statusClass: 'launch-pill is-live',
                launchLabel: skyText('flight_live_with_multiplier', 'Flight live • :multiplierx', { multiplier: liveMultiplier }),
                countdownLabel: skyText('flight_live', 'Flight live'),
                progress: timingState.progress,
                remainingMs: null,
            };
        }

        if (crashedAtMs) {
            const rawRemaining = Math.max(0, (crashedAtMs + (timing.postCrashCooldownSeconds * 1000)) - nowMs);
            const timingState = syncTimingState(
                `${roundId}:crashed`,
                rawRemaining,
                Math.max(0, Math.min(1, 1 - (rawRemaining / (timing.postCrashCooldownSeconds * 1000))))
            );
            const remaining = timingState.remainingMs ?? rawRemaining;

            return {
                statusClass: 'launch-pill is-crashed',
                launchLabel: skyText('preparing_next_round', 'Preparing next round'),
                countdownLabel: skyText('opens_in', 'Opens in :time', { time: formatCountdownMs(remaining) }),
                progress: timingState.progress,
                remainingMs: remaining,
            };
        }

        syncTimingState(`${roundId}:${visualStatus || 'idle'}`, null, 0);

        return {
            statusClass: 'launch-pill',
            launchLabel: skyText('synchronizing', 'Synchronizing'),
            countdownLabel: '-',
            progress: 0,
            remainingMs: null,
        };
    }

    function syncLaunchUi(timeline) {
        const remaining = timeline.remainingMs;
        if (typeof remaining === 'number') {
            const wholeSecond = Math.ceil(remaining / 1000);
            if (remaining > 0 && remaining <= 3000 && state.render.lastCountdownWholeSecond !== wholeSecond) {
                state.render.lastCountdownWholeSecond = wholeSecond;
                playCountdownTick(wholeSecond);
            } else if (remaining > 3000) {
                state.render.lastCountdownWholeSecond = null;
            }
        } else {
            state.render.lastCountdownWholeSecond = null;
        }

        nodes.launchStateLabel.className = timeline.statusClass;
        if (timeline.launchLabel !== state.render.lastLaunchText) {
            state.render.lastLaunchText = timeline.launchLabel;
            nodes.launchStateLabel.textContent = timeline.launchLabel;
        }
        nodes.launchProgressBar.style.transform = `scaleX(${clamp(timeline.progress, 0, 1).toFixed(4)})`;
        nodes.launchProgressBar.style.opacity = timeline.progress > 0 ? '1' : '.35';
        if (timeline.progress >= 0.82) {
            nodes.launchProgressBar.style.background = 'linear-gradient(90deg, rgba(255,182,92,.94), rgba(255,95,122,.96))';
        } else if (timeline.progress >= 0.58) {
            nodes.launchProgressBar.style.background = 'linear-gradient(90deg, rgba(109,224,255,.82), rgba(255,182,92,.92))';
        } else {
            nodes.launchProgressBar.style.background = 'linear-gradient(90deg, rgba(109,224,255,.86), rgba(121,255,214,.88))';
        }
    }

    function estimatedServerNowMs() {
        return state.render.serverNowMs + (performance.now() - state.render.clientSyncPerfMs);
    }

    function flightMath() {
        return {
            growthRate: Number(payload.flightMath?.growthRate || 0.55),
            growthScale: Number(payload.flightMath?.growthScale || 0.35),
        };
    }

    function clamp(value, min, max) {
        return Math.min(max, Math.max(min, value));
    }

    function easeOutPower(value, power = 1.8) {
        return 1 - Math.pow(1 - clamp(value, 0, 1), power);
    }

    function displayProgressFromMultiplier(multiplier) {
        return easeOutPower(clamp((Number(multiplier || 1) - 1) / 0.95, 0, 1), 1.8);
    }

    function timingConfig() {
        return {
            bettingWindowSeconds: Number(payload.timing?.bettingWindowSeconds || 4),
            postCrashCooldownSeconds: Number(payload.timing?.postCrashCooldownSeconds || 2),
        };
    }

    function formatCountdownMs(milliseconds) {
        const clamped = Math.max(0, milliseconds);
        const totalSeconds = clamped / 1000;
        if (totalSeconds < 10) {
            return `${totalSeconds.toFixed(1)}s`;
        }

        const minutes = Math.floor(totalSeconds / 60);
        const seconds = Math.floor(totalSeconds % 60);

        return `${minutes}:${String(seconds).padStart(2, '0')}`;
    }

    function visualRoundState(round) {
        const status = round?.status || 'betting';
        const startsAtMs = round?.startsAt ? Date.parse(round.startsAt) : null;
        const crashedAtMs = round?.crashedAt ? Date.parse(round.crashedAt) : null;
        const roundId = round?.id || null;

        if (state.render.visualRoundId !== roundId) {
            state.render.visualRoundId = roundId;
            state.render.visualMultiplier = Number(round?.currentMultiplier || 1);
            state.render.crashAnimationStartedAt = null;
        }

        if (status !== 'flying') {
            const fixedMultiplier = Number(status === 'crashed' ? (round?.crashMultiplier || round?.currentMultiplier || 1) : (round?.currentMultiplier || 1));
            state.render.visualMultiplier = fixedMultiplier;
            return {
                status,
                multiplier: fixedMultiplier,
                crashedAtMs,
                displayProgress: status === 'crashed'
                    ? displayProgressFromMultiplier(fixedMultiplier)
                    : 0,
            };
        }

        if (!startsAtMs) {
            return {
                status,
                multiplier: Number(round?.currentMultiplier || 1),
                crashedAtMs,
                displayProgress: 0,
            };
        }

        const nowMs = estimatedServerNowMs();

        if (crashedAtMs && nowMs >= crashedAtMs) {
            return {
                status: 'crashed',
                multiplier: Number(round?.crashMultiplier || round?.currentMultiplier || 1),
                crashedAtMs,
                displayProgress: displayProgressFromMultiplier(Number(round?.crashMultiplier || round?.currentMultiplier || 1)),
            };
        }

        const authoritativeFloor = Number(round?.currentMultiplier || 1);
        const monotonicMultiplier = authoritativeFloor;
        state.render.visualMultiplier = monotonicMultiplier;

        return {
            status,
            multiplier: monotonicMultiplier,
            crashedAtMs,
            displayProgress: displayProgressFromMultiplier(monotonicMultiplier),
        };
    }

    function computeStageState(visual, timeline) {
        const timelineProgress = clamp(Number(timeline?.progress || 0), 0, 1);
        const multiplierProgress = clamp(Number(visual?.displayProgress || 0), 0, 1);
        const multiplierValue = Number(visual?.multiplier || 1);
        const isQuickCrashBand = multiplierValue <= 1.18;

        if (visual.status === 'betting') {
            const armingStart = 0.72;
            const armingProgress = timelineProgress >= armingStart
                ? easeOutPower((timelineProgress - armingStart) / (1 - armingStart), 1.35)
                : 0;
            return {
                mode: armingProgress > 0 ? 'arming' : 'charging',
                pathProgress: armingProgress * 0.06,
                chargeProgress: timelineProgress,
                trailStrength: 0,
                takeoffBoost: armingProgress * 0.35,
            };
        }

        if (visual.status === 'flying') {
            if (isQuickCrashBand) {
                const quickFlightProgress = easeOutPower(clamp(timelineProgress / 0.34, 0, 1), 1.02);
                return {
                    mode: timelineProgress < 0.1 ? 'takeoff' : 'quick-flight',
                    pathProgress: 0.1 + (quickFlightProgress * 0.38),
                    chargeProgress: 1,
                    trailStrength: 0.78,
                    takeoffBoost: clamp(1 - (timelineProgress / 0.14), 0, 1),
                    quickCrash: true,
                };
            }

            const takeoffWindow = 0.18;
            if (timelineProgress < takeoffWindow) {
                const launchProgress = easeOutPower(timelineProgress / takeoffWindow, 1.08);
                return {
                    mode: 'takeoff',
                    pathProgress: 0.08 + (launchProgress * 0.2),
                    chargeProgress: 1,
                    trailStrength: 0.72,
                    takeoffBoost: clamp(1 - launchProgress, 0, 1),
                    quickCrash: false,
                };
            }

            const flightTimeline = easeOutPower((timelineProgress - takeoffWindow) / (1 - takeoffWindow), 0.96);
            const pathProgress = clamp(
                Math.max(0.26 + (flightTimeline * 0.66), multiplierProgress * 0.78),
                0.16,
                1
            );

            return {
                mode: 'flying',
                pathProgress,
                chargeProgress: 1,
                trailStrength: 0.5 + (pathProgress * 0.68),
                takeoffBoost: 0,
                quickCrash: false,
            };
        }

        if (visual.status === 'crashed') {
            return {
                mode: 'burst',
                pathProgress: isQuickCrashBand
                    ? Math.max(0.42, multiplierProgress)
                    : Math.max(0.12, multiplierProgress),
                chargeProgress: 1,
                trailStrength: isQuickCrashBand ? 0.42 : 0.2,
                takeoffBoost: 0,
                quickCrash: isQuickCrashBand,
            };
        }

        return {
            mode: 'idle',
            pathProgress: 0,
            chargeProgress: 0,
            trailStrength: 0,
            takeoffBoost: 0,
            quickCrash: false,
        };
    }

    function smoothStageState(roundId, targetState) {
        const visual = state.render.stageVisual;
        if (visual.roundId !== roundId || visual.mode !== targetState.mode) {
            visual.roundId = roundId;
            visual.mode = targetState.mode;
            visual.enteredAt = performance.now();
            visual.progress = targetState.mode === 'charging' ? 0 : Math.max(0.02, targetState.pathProgress);
            visual.chargeProgress = targetState.chargeProgress;
            visual.trailStrength = targetState.trailStrength;
            visual.takeoffBoost = targetState.takeoffBoost;
            visual.quickCrash = Boolean(targetState.quickCrash);
            return { ...visual, modeElapsedMs: 0 };
        }

        visual.progress = Math.max(
            visual.progress,
            visual.progress + ((targetState.pathProgress - visual.progress) * 0.18)
        );
        visual.chargeProgress = Math.max(
            visual.chargeProgress,
            visual.chargeProgress + ((targetState.chargeProgress - visual.chargeProgress) * 0.22)
        );
        visual.trailStrength += (targetState.trailStrength - visual.trailStrength) * 0.18;
        visual.takeoffBoost += (targetState.takeoffBoost - visual.takeoffBoost) * 0.22;
        visual.quickCrash = Boolean(targetState.quickCrash);

        return { ...visual, modeElapsedMs: Math.max(0, performance.now() - visual.enteredAt) };
    }

    function smoothFlightMotion(roundId, targetProgress, stageMode) {
        const motion = state.render.motionVisual;
        const normalizedTarget = clamp(targetProgress, 0, 1);
        const nowMs = performance.now();
        if (motion.roundId !== roundId) {
            motion.roundId = roundId;
            motion.planeProgress = stageMode === 'charging' ? 0 : normalizedTarget;
            motion.trailProgress = stageMode === 'charging' ? 0 : Math.max(0, normalizedTarget - 0.03);
            motion.planeVelocity = 0;
            motion.trailVelocity = 0;
            motion.lastFrameMs = nowMs;
            return { ...motion };
        }

        const dt = clamp((nowMs - (motion.lastFrameMs || nowMs)) / 1000, 0.008, 0.05);
        motion.lastFrameMs = nowMs;

        const profile = stageMode === 'charging'
            ? { maxSpeed: 0.18, accel: 0.8 }
            : stageMode === 'arming'
                ? { maxSpeed: 0.32, accel: 1.2 }
                : stageMode === 'takeoff'
                    ? { maxSpeed: 0.74, accel: 2.4 }
                    : stageMode === 'quick-flight'
                        ? { maxSpeed: 0.9, accel: 2.8 }
                        : stageMode === 'flying'
                            ? { maxSpeed: 0.62, accel: 1.7 }
                            : stageMode === 'burst'
                                ? { maxSpeed: 0.48, accel: 1.4 }
                                : { maxSpeed: 0.3, accel: 1 };

        const nextPlaneVelocity = clamp(
            motion.planeVelocity + ((normalizedTarget - motion.planeProgress) * profile.accel * dt),
            -profile.maxSpeed,
            profile.maxSpeed
        );
        motion.planeVelocity = nextPlaneVelocity;
        motion.planeProgress = clamp(motion.planeProgress + (motion.planeVelocity * dt), 0, 1);
        if (motion.planeProgress < normalizedTarget) {
            motion.planeProgress = Math.min(normalizedTarget, motion.planeProgress + (profile.maxSpeed * 0.2 * dt));
        }

        const trailTarget = Math.max(0, motion.planeProgress - 0.03);
        const nextTrailVelocity = clamp(
            motion.trailVelocity + ((trailTarget - motion.trailProgress) * (profile.accel * 0.86) * dt),
            -(profile.maxSpeed * 0.72),
            profile.maxSpeed * 0.72
        );
        motion.trailVelocity = nextTrailVelocity;
        motion.trailProgress = clamp(motion.trailProgress + (motion.trailVelocity * dt), 0, 1);

        motion.planeProgress = clamp(motion.planeProgress, 0, 1);
        motion.trailProgress = clamp(Math.min(motion.trailProgress, motion.planeProgress), 0, 1);

        return { ...motion };
    }

    function planePresentation(stageState) {
        if (stageState.mode === 'burst') {
            return {
                visibility: 1,
                spawnLift: 0,
            };
        }

        if (stageState.mode === 'crashed') {
            const fade = clamp(1 - (stageState.modeElapsedMs / 260), 0, 1);
            return {
                visibility: fade,
                spawnLift: 0,
            };
        }

        if (stageState.mode === 'charging') {
            return {
                visibility: 0,
                spawnLift: 18,
            };
        }

        if (stageState.mode === 'arming') {
            const intro = easeOutPower(clamp(stageState.modeElapsedMs / 260, 0, 1), 1.35);
            return {
                visibility: intro,
                spawnLift: (1 - intro) * 18,
            };
        }

        return {
            visibility: 1,
            spawnLift: 0,
        };
    }

    function historyMarkup(item) {
        const hot = Number(item.crashMultiplier || 0) >= 2 ? 'is-hot' : 'is-cold';
        return `<div class="history-pill ${hot}">${Number(item.crashMultiplier || 0).toFixed(2)}x</div>`;
    }

    function renderBetButtons(lane) {
        const laneKey = String(lane);
        const betGrid = nodes.betGrids[laneKey];
        if (!betGrid) {
            return;
        }

        betGrid.innerHTML = '';
        (payload.betValues || []).forEach((value) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = `bet-btn${Number(value) === Number(state.selectedBets[laneKey]) ? ' is-active' : ''}`;
            button.textContent = `${Number(value).toFixed(0)}`;
            button.addEventListener('click', () => {
                if (state.submittingByLane[laneKey]) return;
                state.selectedBets[laneKey] = Number(value);
                syncUi();
                renderBetButtons(lane);
            });
            betGrid.appendChild(button);
        });
    }

    function renderAllBetButtons() {
        visibleLanes().forEach((lane) => renderBetButtons(lane));
    }

    function ensureFlightCanvas() {
        if (!nodes.flightCanvas || !nodes.skyStage) {
            return null;
        }

        return {
            ctx: state.render.canvasCtx,
            dpr: window.devicePixelRatio || 1,
            width: state.render.canvasWidth,
            height: state.render.canvasHeight,
        };
    }

    function resizeFlightCanvas() {
        if (!nodes.flightCanvas || !nodes.skyStage) {
            return;
        }

        const rect = nodes.skyStage.getBoundingClientRect();
        const dpr = window.devicePixelRatio || 1;
        const width = Math.max(1, Math.round(rect.width * dpr));
        const height = Math.max(1, Math.round(rect.height * dpr));

        if (nodes.flightCanvas.width === width && nodes.flightCanvas.height === height && state.render.canvasCtx) {
            return;
        }

        nodes.flightCanvas.width = width;
        nodes.flightCanvas.height = height;
        state.render.canvasCtx = nodes.flightCanvas.getContext('2d');
        state.render.canvasWidth = rect.width;
        state.render.canvasHeight = rect.height;
        state.render.flightPath = buildFlightPath(rect.width, rect.height);
    }

    function cubicBezierPoint(t, p0, p1, p2, p3) {
        const inv = 1 - t;
        const x = (inv ** 3 * p0.x)
            + (3 * inv * inv * t * p1.x)
            + (3 * inv * t * t * p2.x)
            + (t ** 3 * p3.x);
        const y = (inv ** 3 * p0.y)
            + (3 * inv * inv * t * p1.y)
            + (3 * inv * t * t * p2.y)
            + (t ** 3 * p3.y);

        return { x, y };
    }

    function cubicBezierTangent(t, p0, p1, p2, p3) {
        const inv = 1 - t;
        const x = 3 * inv * inv * (p1.x - p0.x)
            + 6 * inv * t * (p2.x - p1.x)
            + 3 * t * t * (p3.x - p2.x);
        const y = 3 * inv * inv * (p1.y - p0.y)
            + 6 * inv * t * (p2.y - p1.y)
            + 3 * t * t * (p3.y - p2.y);

        return { x, y };
    }

    function buildFlightPath(width, height) {
        const path = {
            p0: { x: width * 0.08, y: height * 0.82 },
            p1: { x: width * 0.24, y: height * 0.82 },
            p2: { x: width * 0.56, y: height * 0.58 },
            p3: { x: width * 0.9, y: height * 0.16 },
        };

        const samples = [];
        let totalLength = 0;
        let previousPoint = cubicBezierPoint(0, path.p0, path.p1, path.p2, path.p3);
        samples.push({ t: 0, point: previousPoint, length: 0 });

        for (let index = 1; index <= 220; index += 1) {
            const t = index / 220;
            const point = cubicBezierPoint(t, path.p0, path.p1, path.p2, path.p3);
            totalLength += Math.hypot(point.x - previousPoint.x, point.y - previousPoint.y);
            samples.push({ t, point, length: totalLength });
            previousPoint = point;
        }

        path.samples = samples;
        path.totalLength = totalLength;

        return path;
    }

    function pointAtPathProgress(path, progress) {
        const clamped = clamp(progress, 0, 1);
        const targetLength = clamped * path.totalLength;
        const samples = path.samples || [];

        for (let index = 1; index < samples.length; index += 1) {
            const current = samples[index];
            const previous = samples[index - 1];
            if (current.length >= targetLength) {
                const span = Math.max(0.0001, current.length - previous.length);
                const mix = (targetLength - previous.length) / span;
                return {
                    x: previous.point.x + ((current.point.x - previous.point.x) * mix),
                    y: previous.point.y + ((current.point.y - previous.point.y) * mix),
                    t: previous.t + ((current.t - previous.t) * mix),
                };
            }
        }

        const last = samples[samples.length - 1];
        return { x: last.point.x, y: last.point.y, t: last.t };
    }

    function tangentAtPathProgress(path, progress) {
        const point = pointAtPathProgress(path, progress);
        return cubicBezierTangent(point.t, path.p0, path.p1, path.p2, path.p3);
    }

    function overflowRiseFromMultiplier(multiplier, stageMode) {
        if (!['flying', 'quick-flight', 'burst', 'crashed'].includes(stageMode)) {
            return 0;
        }

        const normalized = Math.max(0, Math.log(Math.max(1, Number(multiplier || 1))) - Math.log(2.6));
        return clamp(normalized / Math.log(12), 0, 1);
    }

    function overflowDirection(tangent, overflow) {
        const length = Math.max(1, Math.hypot(tangent.x, tangent.y));
        const tx = tangent.x / length;
        const ty = tangent.y / length;
        const upwardBias = clamp(overflow, 0, 1) * 0.72;
        const dx = tx * (1 - upwardBias);
        const dy = (ty * (1 - upwardBias)) + (-1.18 * upwardBias);
        const mixedLength = Math.max(1, Math.hypot(dx, dy));

        return {
            x: dx / mixedLength,
            y: dy / mixedLength,
        };
    }

    function drawPlane(ctx, x, y, angle, scale, glow, alpha = 1) {
        ctx.save();
        ctx.translate(x, y);
        ctx.rotate(angle);
        ctx.scale(scale, scale);
        ctx.globalAlpha = alpha;

        ctx.shadowColor = `rgba(61, 215, 255, ${0.22 * glow})`;
        ctx.shadowBlur = 18 * glow;

        const bodyGradient = ctx.createLinearGradient(-22, -16, 24, 18);
        bodyGradient.addColorStop(0, '#fbfdff');
        bodyGradient.addColorStop(0.58, '#cfe3ff');
        bodyGradient.addColorStop(1, '#73c9ff');

        const wingGradient = ctx.createLinearGradient(-18, -12, 18, 18);
        wingGradient.addColorStop(0, '#72f1ff');
        wingGradient.addColorStop(1, '#286cf5');

        ctx.fillStyle = bodyGradient;
        ctx.beginPath();
        ctx.moveTo(-28, 0);
        ctx.lineTo(-5, -4);
        ctx.lineTo(18, -15);
        ctx.quadraticCurveTo(29, -20, 34, -10);
        ctx.quadraticCurveTo(36, -4, 27, 0);
        ctx.lineTo(8, 6);
        ctx.lineTo(28, 9);
        ctx.quadraticCurveTo(33, 10, 31, 15);
        ctx.quadraticCurveTo(28, 19, 22, 17);
        ctx.lineTo(3, 11);
        ctx.lineTo(-6, 23);
        ctx.lineTo(-14, 24);
        ctx.lineTo(-10, 11);
        ctx.lineTo(-25, 8);
        ctx.quadraticCurveTo(-33, 6, -34, 1);
        ctx.quadraticCurveTo(-34, -3, -28, 0);
        ctx.closePath();
        ctx.fill();

        ctx.shadowBlur = 0;
        ctx.fillStyle = wingGradient;
        ctx.beginPath();
        ctx.moveTo(-16, -2);
        ctx.lineTo(-1, -13);
        ctx.lineTo(10, -12);
        ctx.lineTo(-4, 2);
        ctx.closePath();
        ctx.fill();

        ctx.beginPath();
        ctx.moveTo(-10, 8);
        ctx.lineTo(4, 11);
        ctx.lineTo(10, 16);
        ctx.lineTo(-3, 13);
        ctx.closePath();
        ctx.fill();

        ctx.fillStyle = 'rgba(11, 23, 48, 0.65)';
        ctx.beginPath();
        ctx.arc(12, -9, 2.2, 0, Math.PI * 2);
        ctx.fill();

        ctx.restore();
    }

    function drawAtmosphere(ctx, width, height, stageState, progress, now) {
        const centerX = width * 0.5;
        const centerY = height * 0.48;
        const zoomOut = 1 - (Math.min(0.28, progress * 0.28));
        const breathing = 0.92 + (Math.sin(now / 1900) * 0.02);

        ctx.save();
        ctx.translate(centerX, centerY);
        ctx.scale(zoomOut * breathing, zoomOut * breathing);
        ctx.translate(-centerX, -centerY);

        const aurora = ctx.createRadialGradient(centerX, centerY + (progress * 18), 10, centerX, centerY, width * 0.58);
        aurora.addColorStop(0, 'rgba(77,198,255,0.09)');
        aurora.addColorStop(0.48, 'rgba(118,108,255,0.08)');
        aurora.addColorStop(1, 'rgba(10,18,35,0)');
        ctx.fillStyle = aurora;
        ctx.fillRect(0, 0, width, height);

        const ringAlpha = stageState.mode === 'charging' ? 0.05 : 0.11;
        ctx.strokeStyle = `rgba(109,224,255,${ringAlpha})`;
        ctx.lineWidth = 1;
        for (let index = 0; index < 5; index += 1) {
            const radiusX = (width * (0.18 + (index * 0.1))) + (progress * (36 + (index * 10)));
            const radiusY = (height * (0.09 + (index * 0.052))) + (progress * (18 + (index * 5)));
            ctx.beginPath();
            ctx.ellipse(centerX, centerY + (index * 5), radiusX, radiusY, 0, 0, Math.PI * 2);
            ctx.stroke();
        }

        for (let index = 0; index < 18; index += 1) {
            const starX = (width * ((index * 53 % 100) / 100));
            const starY = (height * ((index * 37 % 60) / 100)) + (progress * 8);
            const radius = 1 + ((index % 3) * 0.35);
            ctx.fillStyle = `rgba(255,255,255,${0.1 + ((index % 4) * 0.035)})`;
            ctx.beginPath();
            ctx.arc(starX, starY, radius, 0, Math.PI * 2);
            ctx.fill();
        }

        if (['takeoff', 'quick-flight', 'flying'].includes(stageState.mode)) {
            const glow = ctx.createRadialGradient(centerX, centerY, 12, centerX, centerY, width * 0.34);
            glow.addColorStop(0, 'rgba(109,224,255,0.12)');
            glow.addColorStop(1, 'rgba(109,224,255,0)');
            ctx.fillStyle = glow;
            ctx.fillRect(0, 0, width, height);
        }

        ctx.restore();
    }

    function drawTrailParticles(ctx, point, tangent, stageState, now) {
        if (stageState.trailStrength <= 0) {
            return;
        }

        const length = Math.max(1, Math.hypot(tangent.x, tangent.y));
        const nx = -tangent.x / length;
        const ny = -tangent.y / length;

        ctx.save();
        for (let index = 0; index < 12; index += 1) {
            const trailOffset = (index + 1) * (8 + (stageState.trailStrength * 2));
            const sway = Math.sin((now / 140) + index) * (1.5 + stageState.trailStrength);
            const px = point.x - ((tangent.x / length) * trailOffset) + (nx * sway);
            const py = point.y - ((tangent.y / length) * trailOffset) + (ny * sway);
            const radius = Math.max(1.2, 4.8 - (index * 0.28));
            const alpha = Math.max(0.04, 0.26 - (index * 0.018)) * stageState.trailStrength;

            ctx.fillStyle = index % 3 === 0
                ? `rgba(255,182,92,${alpha})`
                : `rgba(109,224,255,${alpha})`;
            ctx.beginPath();
            ctx.arc(px, py, radius, 0, Math.PI * 2);
            ctx.fill();
        }
        ctx.restore();
    }

    function drawFlightStage(stageState, multiplier) {
        const canvas = ensureFlightCanvas();
        if (!canvas || !canvas.ctx || !canvas.width || !canvas.height) {
            return;
        }

        const { ctx, dpr, width, height } = canvas;
        ctx.save();
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        ctx.clearRect(0, 0, width, height);

        const path = state.render.flightPath || buildFlightPath(width, height);
        const now = performance.now();
        const idleBob = Math.sin(now / 320) * 2.4;
        const stageMode = stageState.mode === 'burst' && stageState.modeElapsedMs >= (stageState.quickCrash ? 420 : 320)
            ? 'crashed'
            : stageState.mode;
        const motionState = smoothFlightMotion(stageState.roundId || 'none', stageState.progress, stageMode);
        const planeFx = planePresentation({ ...stageState, mode: stageMode });
        const visibleProgress = clamp(motionState.planeProgress, 0, 1);
        const visibleTrailProgress = clamp(motionState.trailProgress, 0, 1);
        const overflowRise = overflowRiseFromMultiplier(multiplier, stageMode);

        const horizonY = height * 0.83;
        drawAtmosphere(ctx, width, height, stageState, Math.min(1, visibleProgress + (overflowRise * 0.35)), now);

        ctx.strokeStyle = 'rgba(255,255,255,0.08)';
        ctx.lineWidth = 1;
        ctx.setLineDash([8, 8]);
        ctx.beginPath();
        ctx.moveTo(width * 0.06, horizonY);
        ctx.lineTo(width * 0.94, horizonY);
        ctx.stroke();
        ctx.setLineDash([]);

        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.strokeStyle = 'rgba(255,255,255,0.12)';
        ctx.lineWidth = 3;
        ctx.beginPath();
        ctx.moveTo(path.p0.x, path.p0.y);
        ctx.bezierCurveTo(path.p1.x, path.p1.y, path.p2.x, path.p2.y, path.p3.x, path.p3.y);
        if (overflowRise > 0) {
            const endTangent = tangentAtPathProgress(path, 0.999);
            const direction = overflowDirection(endTangent, overflowRise);
            const extensionLength = (width * 0.12) + (overflowRise * width * 0.18);
            const extensionX = Math.min(width * 0.92, path.p3.x + (direction.x * extensionLength));
            const extensionY = Math.max(height * 0.06, path.p3.y + (direction.y * extensionLength));
            ctx.lineTo(extensionX, extensionY);
        }
        ctx.stroke();

        const steps = 140;
        const trailGradient = ctx.createLinearGradient(path.p0.x, path.p0.y, path.p3.x, path.p3.y);
        trailGradient.addColorStop(0, 'rgba(109,224,255,0.85)');
        trailGradient.addColorStop(1, 'rgba(255,95,122,0.92)');
        ctx.strokeStyle = trailGradient;
        ctx.lineWidth = 5;
        ctx.shadowColor = 'rgba(109,224,255,0.45)';
        ctx.shadowBlur = 16;
        ctx.beginPath();
        let firstPoint = pointAtPathProgress(path, 0);
        ctx.moveTo(firstPoint.x, firstPoint.y);
        for (let index = 1; index <= Math.max(1, Math.round(steps * visibleTrailProgress)); index += 1) {
            const point = pointAtPathProgress(path, Math.min(index / steps, visibleTrailProgress));
            ctx.lineTo(point.x, point.y);
            if ((index / steps) >= visibleTrailProgress) {
                break;
            }
        }
        if (overflowRise > 0) {
            const endPoint = pointAtPathProgress(path, 1);
            const endTangent = tangentAtPathProgress(path, 0.999);
            const direction = overflowDirection(endTangent, overflowRise);
            const extensionLength = overflowRise * width * 0.18;
            const extensionX = Math.min(width * 0.92, endPoint.x + (direction.x * extensionLength));
            const extensionY = Math.max(height * 0.06, endPoint.y + (direction.y * extensionLength));
            ctx.lineTo(extensionX, extensionY);
        }
        ctx.stroke();
        ctx.shadowBlur = 0;

        const livePoint = pointAtPathProgress(path, Math.max(0.001, visibleProgress));
        const liveTangent = tangentAtPathProgress(path, Math.max(0.001, visibleProgress));
        let planeX = visibleProgress > 0 ? livePoint.x : path.p0.x;
        let planeY = visibleProgress > 0 ? livePoint.y : (path.p0.y + idleBob);
        let planeTangent = liveTangent;
        let planeAngle = Math.atan2(planeTangent.y, planeTangent.x);
        let planeScale = 0.82 + (visibleProgress * 0.28);
        let planeAlpha = planeFx.visibility;

        if (overflowRise > 0) {
            const endPoint = pointAtPathProgress(path, 1);
            const endTangent = tangentAtPathProgress(path, 0.999);
            const direction = overflowDirection(endTangent, overflowRise);
            const extensionLength = overflowRise * width * 0.18;
            planeX = Math.min(width * 0.9, endPoint.x + (direction.x * extensionLength));
            planeY = Math.max(height * 0.06, endPoint.y + (direction.y * extensionLength));
            planeTangent = direction;
            planeAngle = Math.atan2(planeTangent.y, planeTangent.x);
        }

        if (stageMode === 'charging' || stageMode === 'arming') {
            planeAngle = -0.18;
            planeY = path.p0.y + idleBob + planeFx.spawnLift;
        }

        if (stageMode === 'takeoff' || stageMode === 'quick-flight') {
            planeX -= (18 * stageState.takeoffBoost);
            planeY += (8 * stageState.takeoffBoost) + planeFx.spawnLift;
            planeAngle -= (0.1 * stageState.takeoffBoost);
            planeScale *= 1 + ((stageMode === 'quick-flight' ? 0.12 : 0.08) * stageState.takeoffBoost);
        }

        drawTrailParticles(ctx, { x: planeX, y: planeY }, planeTangent, stageState, now);

        if (stageMode === 'burst') {
            if (!state.render.crashAnimationStartedAt) {
                state.render.crashAnimationStartedAt = performance.now();
            }
            const fallDuration = stageState.quickCrash ? 420 : 320;
            const fallProgress = Math.min(1, (performance.now() - state.render.crashAnimationStartedAt) / fallDuration);
            planeX += (width * (stageState.quickCrash ? 0.12 : 0.08)) * fallProgress;
            planeY += (height * (stageState.quickCrash ? 0.26 : 0.22)) * fallProgress;
            planeAngle += ((stageState.quickCrash ? 1.35 : 1.1) * fallProgress);
            planeScale *= 1 + ((stageState.quickCrash ? 0.14 : 0.08) * fallProgress);
            planeAlpha = 1 - (fallProgress * (stageState.quickCrash ? 0.55 : 0.42));
        } else {
            state.render.crashAnimationStartedAt = null;
        }

        drawPlane(ctx, planeX, planeY, planeAngle, planeScale, (stageMode === 'flying' || stageMode === 'takeoff' || stageMode === 'quick-flight' || stageMode === 'burst') ? 1.1 + (multiplier > 1 ? Math.min(0.4, (multiplier - 1) * 0.12) : 0) : 1, planeAlpha);

        if (stageMode === 'flying' || stageMode === 'takeoff' || stageMode === 'quick-flight' || stageMode === 'burst') {
            ctx.fillStyle = 'rgba(109,224,255,0.16)';
            ctx.beginPath();
            ctx.arc(planeX, planeY, 22 + (visibleProgress * 18), 0, Math.PI * 2);
            ctx.fill();
        }

        ctx.restore();
    }

    function syncHistory() {
        const signature = (state.snapshot.history || [])
            .map((item) => `${item.id}:${Number(item.crashMultiplier || 0).toFixed(2)}`)
            .join('|');

        if (signature === state.render.historySignature) {
            return;
        }

        state.render.historySignature = signature;
        nodes.historyStrip.innerHTML = (state.snapshot.history || []).map(historyMarkup).join('');
    }

    function syncStaticUi() {
        const round = state.snapshot.currentRound || {};
        const myBets = state.snapshot.myBets || normalizeMyBets(null, state.snapshot.myBet);
        const isBettingOpen = roundStillAcceptsBets(round);
        const canBet = {
            '1': isBettingOpen && !laneBet(1) && Number(state.snapshot.balance || 0) >= Number(state.selectedBets['1']) && !state.submittingByLane['1'],
            '2': isBettingOpen && !laneBet(2) && Number(state.snapshot.balance || 0) >= Number(state.selectedBets['2']) && !state.submittingByLane['2'],
        };
        const canCashout = {
            '1': round.status === 'flying' && laneBet(1) && laneBet(1).status === 'bet_accepted' && !state.submittingByLane['1'],
            '2': round.status === 'flying' && laneBet(2) && laneBet(2).status === 'bet_accepted' && !state.submittingByLane['2'],
        };
        const isDemo = Boolean(payload.isDemo);
        const selectedBetSummary = visibleLanes()
            .map((lane) => `L${lane} ${Number(state.selectedBets[String(lane)] || 0).toFixed(2)}`)
            .join(' • ');
        const myBetSummary = visibleLanes()
            .map((lane) => {
                const bet = myBets[String(lane)];
                return bet ? `L${lane} ${money(bet.betAmount)} • ${laneStatusLabel(bet)}` : `L${lane} ${commonText('waiting', 'Waiting')}`;
            })
            .join(' • ');
        const cashoutSummary = visibleLanes()
            .map((lane) => {
                const bet = myBets[String(lane)];
                if (bet && Number(bet.cashoutAmount || 0) > 0) {
                    return `L${lane} ${money(bet.cashoutAmount)} @ ${Number(bet.cashoutMultiplier || 0).toFixed(2)}x`;
                }

                return `L${lane} -`;
            })
            .join(' • ');
        const staticKey = JSON.stringify({
            balance: Number(state.snapshot.balance || 0).toFixed(2),
            currency: state.snapshot.currency || payload.currency,
            selectedBets: BET_LANES.map((lane) => Number(state.selectedBets[String(lane)] || 0).toFixed(2)),
            roundId: round.id || '-',
            roundStatus: round.status || '-',
            crashTarget: round.status === 'crashed' && round.crashMultiplier ? Number(round.crashMultiplier).toFixed(2) : 'Hidden',
            myBets: BET_LANES.map((lane) => {
                const bet = myBets[String(lane)];
                return bet ? [
                    bet.status || null,
                    Number(bet.betAmount || 0).toFixed(2),
                    Number(bet.cashoutAmount || 0).toFixed(2),
                    Number(bet.cashoutMultiplier || 0).toFixed(2),
                ] : null;
            }),
            submitting: [Boolean(state.submittingByLane['1']), Boolean(state.submittingByLane['2'])],
        });

        if (staticKey === state.render.lastStaticKey) {
            return;
        }

        state.render.lastStaticKey = staticKey;

        nodes.balanceValue.textContent = `${state.snapshot.balanceExact || Number(state.snapshot.balance || 0).toFixed(2)} ${state.snapshot.currency || payload.currency}`;
        nodes.currencyValue.textContent = state.snapshot.currency || payload.currency;
        nodes.selectedBetValue.textContent = `${selectedBetSummary} ${state.snapshot.currency || payload.currency}`;
        visibleLanes().forEach((lane) => {
            const laneKey = String(lane);
            const bet = myBets[laneKey];
            nodes.selectedBetValues[laneKey].textContent = `${Number(state.selectedBets[laneKey] || 0).toFixed(2)} ${state.snapshot.currency || payload.currency}`;
            nodes.laneStates[laneKey].textContent = laneStatusLabel(bet);
            nodes.betButtons[laneKey].disabled = !canBet[laneKey];
            nodes.cashoutButtons[laneKey].disabled = !canCashout[laneKey];
            nodes.cashoutButtons[laneKey].style.display = canCashout[laneKey] || (bet && bet.status === 'bet_accepted') ? 'block' : 'none';
            nodes.betButtons[laneKey].textContent = state.submittingByLane[laneKey]
                ? commonText('submitting', 'Submitting...')
                : skyText('place_bet_lane', 'Place bet :lane', { lane });
            nodes.cashoutButtons[laneKey].textContent = state.submittingByLane[laneKey]
                ? commonText('submitting', 'Submitting...')
                : skyText('cash_out_lane', 'Cash out :lane', { lane });
        });
        nodes.crashRoundId.textContent = round.id || '-';
        nodes.roundStatusValue.textContent = phaseLabel(round.status || '-');
        nodes.crashTargetValue.textContent = round.status === 'crashed' && round.crashMultiplier
            ? `${Number(round.crashMultiplier).toFixed(2)}x`
            : commonText('hidden', 'Hidden');
        nodes.myBetValue.textContent = myBetSummary;
        nodes.myCashoutValue.textContent = cashoutSummary;

        if (isBettingOpen) {
        } else if (round.status === 'flying') {
        } else if (round.status === 'crashed') {
        }

        nodes.outcomeCard.className = 'outcome';
        const settled = settledBets();
        const active = activeBets();
        const settledWin = settled.filter((bet) => bet.status === 'settled_win');
        const settledLoss = settled.filter((bet) => bet.status === 'settled_loss');
        let playedWinSound = false;
        let playedLossSound = false;

        settledWin.forEach((bet) => {
            const laneKey = String(Number(bet.lane || 1));
            if (state.lastSettledBetIds[laneKey] !== bet.roundId) {
                state.lastSettledBetIds[laneKey] = bet.roundId;
                if (!playedWinSound) {
                    playCashoutSound();
                    playedWinSound = true;
                }
            }
        });

        settledLoss.forEach((bet) => {
            const laneKey = String(Number(bet.lane || 1));
            if (state.lastSettledBetIds[laneKey] !== bet.roundId) {
                state.lastSettledBetIds[laneKey] = bet.roundId;
                if (!playedLossSound && !playedWinSound) {
                    playLossSound();
                    playedLossSound = true;
                }
            }
        });

        if (settledWin.length > 0) {
            nodes.outcomeCard.classList.add('is-win');
            nodes.outcomeText.textContent = settledWin.map((bet) => bet.resultSummary || skyText('lane_cashed_out', 'Lane :lane cashed out :amount', {
                lane: bet.lane,
                amount: money(bet.cashoutAmount || bet.winAmount),
            })).join(' • ');
            nodes.lastOutcomeValue.textContent = settledWin.map((bet) => skyText('last_win_summary', 'L:lane Win :amount', {
                lane: bet.lane,
                amount: money(bet.winAmount),
            })).join(' • ');
        } else if (settledLoss.length > 0 && active.length === 0) {
            nodes.outcomeCard.classList.add('is-loss');
            nodes.outcomeText.textContent = settledLoss.map((bet) => bet.resultSummary || skyText('lane_crashed_before_cashout', 'Lane :lane crashed before cashout.', { lane: bet.lane })).join(' • ');
            nodes.lastOutcomeValue.textContent = settledLoss.map((bet) => skyText('last_loss_summary', 'L:lane Loss :amount', {
                lane: bet.lane,
                amount: money(bet.betAmount),
            })).join(' • ');
        } else if (isDemo) {
            nodes.outcomeCard.classList.add('is-demo');
            nodes.outcomeText.textContent = active.length > 0 ? skyText('demo_round_progress', 'Demo round in progress.') : skyText('demo_wallet_active', 'Demo wallet active');
            if (active.length === 0) {
                nodes.lastOutcomeValue.textContent = skyText('demo_wallet_active', 'Demo wallet active');
            }
        } else {
            if (active.length === 0) {
                nodes.outcomeCard.classList.add('is-waiting');
                nodes.outcomeText.textContent = round.status === 'betting'
                    ? `${skyText('waiting_for_bet', 'Waiting for bet')}. ${skyText('place_before_takeoff', 'Place your bet before takeoff.')}`
                    : `${skyText('waiting_for_bet', 'Waiting for bet')}. ${skyText('waiting_next_betting', 'Waiting for the next betting window.')}`;
                nodes.lastOutcomeValue.textContent = skyText('waiting_for_bet', 'Waiting for bet');
            } else {
                nodes.outcomeText.textContent = active.map((bet) => bet.resultSummary || `${skyText('lane', 'Lane :number', { number: bet.lane })} ${skyText('bet_placed', 'Bet placed')}.`).join(' • ');
            }
        }
    }

    function syncPhaseUi(status, multiplier, round) {
        const hasBet = activeBets().length > 0;
        const recoveryBannerActive = state.render.recoveryNoticeUntil > Date.now();
        const phaseKey = JSON.stringify({
            status,
            hasBet,
            crashMultiplier: Number(round?.crashMultiplier || 0).toFixed(2),
            recoveryBannerActive,
        });

        if (phaseKey === state.render.lastPhaseKey) {
            return;
        }

        state.render.lastPhaseKey = phaseKey;
        nodes.roundPhaseLabel.textContent = phaseLabel(status || 'betting');
        nodes.roundStatusValue.textContent = phaseLabel(status || '-');

        if (recoveryBannerActive) {
            nodes.statusBanner.innerHTML = `<strong>${skyText('recovered', 'Recovered')}</strong> • ${skyText('recovered_body', 'Rejoined the current round from authoritative server state.')}`;
        }

        if (status === 'betting') {
            nodes.roundSubtext.textContent = hasBet ? skyText('round_live_announce', 'Round is live. Watch your target multiplier to cash out.') : skyText('waiting_next_climb', 'Waiting for the next live climb.');
            if (!recoveryBannerActive) {
                nodes.statusBanner.innerHTML = `<strong>${phaseLabel(status)}</strong> • ${skyText('betting_window_open', 'Betting window is open.')}`;
            }
        } else if (status === 'flying') {
            nodes.roundSubtext.textContent = skyText('round_live_announce', 'Round is live. Watch your target multiplier to cash out.');
            if (!recoveryBannerActive) {
                nodes.statusBanner.innerHTML = `<strong>${skyText('round_live_status', 'Round live')}</strong> • ${skyText('authoritative_stream', 'Authoritative state is streaming from the server.')}`;
            }
        } else if (status === 'crashed') {
            nodes.roundSubtext.textContent = skyText('waiting_next_betting', 'Waiting for the next betting window.');
            if (!recoveryBannerActive) {
                nodes.statusBanner.innerHTML = `<strong>${skyText('crashed', 'Crashed')}</strong> • ${skyText('waiting_next_betting', 'Waiting for the next betting window.')}`;
            }
        } else {
            nodes.roundSubtext.textContent = skyText('waiting_snapshot', 'Waiting for authoritative snapshot...');
            if (!recoveryBannerActive) {
                nodes.statusBanner.textContent = skyText('waiting_snapshot', 'Waiting for authoritative snapshot...');
            }
        }

        const soundKey = `${round?.id || 'none'}:${status}`;
        if (soundKey !== state.render.lastPhaseSoundKey) {
            state.render.lastPhaseSoundKey = soundKey;
            announceLive(status === 'flying'
                ? skyText('round_live_announce', 'Round is live. Watch your target multiplier to cash out.')
                : status === 'crashed'
                    ? `Round crashed at ${Number(round?.crashMultiplier || multiplier || 1).toFixed(2)}x.`
                    : status === 'betting'
                        ? skyText('betting_window_open', 'Betting window is open.')
                        : `Round state updated to ${phaseLabel(status)}.`);
            if (status === 'flying') {
                playTakeoffSound();
            } else if (status === 'crashed') {
                triggerCrashVisual();
                playCrashSound();
            }
        }
    }

    function syncCountdownUi(timeline) {
        if (timeline.countdownLabel !== state.render.lastCountdownText) {
            state.render.lastCountdownText = timeline.countdownLabel;
            nodes.nextBetCountdownValue.textContent = timeline.countdownLabel;
        }
    }

    function syncAnimatedUi() {
        const round = state.snapshot.currentRound || {};
        const visual = visualRoundState(round);
        const multiplier = Number(visual.multiplier || 1);
        const timeline = computeTimeline(round, visual.status);
        const stageState = smoothStageState(
            round?.id || 'none',
            computeStageState(visual, timeline)
        );

        const nowPerfMs = performance.now();
        const waitingText = visual.status === 'flying' ? null : skyText('waiting_next_betting', 'Waiting for the next betting window.');
        const multiplierText = waitingText || `${multiplier.toFixed(2)}x`;
        if (
            multiplierText !== state.render.lastMultiplierText
            && (nowPerfMs - state.render.lastMultiplierPaintMs) >= 33
        ) {
            state.render.lastMultiplierPaintMs = nowPerfMs;
            state.render.lastMultiplierText = multiplierText;
            nodes.multiplierValue.textContent = multiplierText;
            nodes.multiplierValue.classList.toggle('is-label', Boolean(waitingText));
        }
        syncPhaseUi(visual.status, multiplier, round);
        syncCountdownUi(timeline);
        syncLaunchUi(timeline);
        drawFlightStage(stageState, multiplier);
        startBackgroundAmbience();
        updateBackgroundAmbience(multiplier, visual.status);
        if (visual.status === 'flying') {
            startEngineSound();
        }
        updateEngineSound(multiplier, visual.status);
        if (visual.status !== 'flying') {
            stopEngineSound();
        }
    }

    function syncUi() {
        syncHistory();
        syncPublicFeed();
        syncMyBetHistory();
        syncStaticUi();
        syncRoundDrawer();
        syncAutoUi();
        syncAnimatedUi();
    }

    async function maybeRunAutomation() {
        const round = state.snapshot.currentRound || {};
        const roundId = round.id || null;
        const lane = 1;
        const laneKey = '1';
        const bet = laneBet(lane);
        const visualMultiplier = Number(state.render.visualMultiplier || round.currentMultiplier || 1);
        const automationBetAmount = state.automation.repeatLastStakeEnabled
            ? Number(state.automation.lastBetAmount || state.selectedBets[laneKey] || 0)
            : Number(state.selectedBets[laneKey] || 0);
        const currentBalance = Number(state.snapshot.balance || 0);
        const lowBalanceFloor = Number(state.automation.stopLowBalanceValue || 0);

        if (!roundId) {
            return;
        }

        if (
            state.automation.stopLowBalanceEnabled
            && currentBalance <= lowBalanceFloor
            && (state.automation.autoBetEnabled || state.automation.autoCashoutEnabled)
        ) {
            disableAutomation(skyText('automation_stopped_low_balance', 'Automation stopped because balance dropped to :balance, below the :floor floor.', {
                balance: money(currentBalance),
                floor: money(lowBalanceFloor),
            }));
            return;
        }

        if (round.status === 'betting') {
            if (state.automation.autoCashoutBetRoundId && (!bet || bet.roundId !== state.automation.autoCashoutBetRoundId)) {
                state.automation.autoCashoutBetRoundId = null;
            }

            if (
                state.automation.autoBetEnabled
                && state.automation.autoBetRoundId !== roundId
                && !state.submittingByLane[laneKey]
                && !bet
                && currentBalance >= automationBetAmount
            ) {
                state.selectedBets[laneKey] = automationBetAmount;
                renderBetButtons(lane);
                state.automation.autoBetRoundId = roundId;
                setAutoStatus(skyText('auto_bet_armed', 'Auto bet armed for round :round. Placing :amount on Lane 1.', {
                    round: roundId,
                    amount: money(automationBetAmount),
                }));
                primeAudioLandscape();
                await placeBet(lane);
                return;
            }
        }

        if (
            round.status === 'flying'
            && state.automation.autoCashoutEnabled
            && bet
            && bet.status === 'bet_accepted'
            && state.automation.autoCashoutBetRoundId !== bet.roundId
            && !state.submittingByLane[laneKey]
            && visualMultiplier >= Number(state.automation.autoCashoutTarget || 1.5)
        ) {
            state.automation.autoCashoutBetRoundId = bet.roundId;
            setAutoStatus(skyText('auto_cashout_fired', 'Auto cashout fired for Lane 1 at :targetx target.', {
                target: Number(state.automation.autoCashoutTarget || 1.5).toFixed(2),
            }));
            primeAudioLandscape();
            await cashout(lane);
            return;
        }

        if (round.status === 'crashed' && bet && bet.status === 'settled_loss') {
            if (state.automation.stopOnLossEnabled) {
                disableAutomation(skyText('automation_stopped_loss', 'Automation stopped after a loss on Lane 1 at :multiplierx.', {
                    multiplier: Number(round.crashMultiplier || round.currentMultiplier || 1).toFixed(2),
                }));
                return;
            }
            setAutoStatus(skyText('auto_crashed_before_cashout', 'Lane 1 crashed before auto cashout at :multiplierx.', {
                multiplier: Number(round.crashMultiplier || round.currentMultiplier || 1).toFixed(2),
            }));
        } else if (bet && bet.status === 'settled_win') {
            if (state.automation.stopOnWinEnabled) {
                disableAutomation(skyText('automation_stopped_win', 'Automation stopped after a win on Lane 1 at :multiplierx.', {
                    multiplier: Number(bet.cashoutMultiplier || 0).toFixed(2),
                }));
                return;
            }
            setAutoStatus(skyText('auto_cashed_out', 'Lane 1 cashed out automatically at :multiplierx.', {
                multiplier: Number(bet.cashoutMultiplier || 0).toFixed(2),
            }));
        } else if (round.status === 'betting' && !bet && state.automation.autoBetEnabled) {
            setAutoStatus(skyText('auto_waiting', 'Auto bet is waiting for the next clean betting window on Lane 1:amount.', {
                amount: state.automation.repeatLastStakeEnabled ? ` ${money(automationBetAmount)}` : '',
            }));
        } else if (round.status === 'flying' && bet && bet.status === 'bet_accepted' && state.automation.autoCashoutEnabled) {
            setAutoStatus(skyText('auto_cashout_armed', 'Auto cashout is armed for :targetx on Lane 1.', {
                target: Number(state.automation.autoCashoutTarget || 1.5).toFixed(2),
            }));
        } else if (!state.automation.autoBetEnabled && !state.automation.autoCashoutEnabled) {
            setAutoStatus(skyText('automation_idle', 'Automation is idle. Auto bet places the current Lane 1 stake at the start of each betting window.'));
        }
    }

    function startAnimationLoop() {
        if (state.render.rafHandle) {
            return;
        }

        const tick = () => {
            state.render.rafHandle = window.requestAnimationFrame(tick);
            if (!document.hidden) {
                syncAnimatedUi();
            }
        };

        state.render.rafHandle = window.requestAnimationFrame(tick);
    }

    function stopAnimationLoop() {
        if (!state.render.rafHandle) {
            return;
        }

        window.cancelAnimationFrame(state.render.rafHandle);
        state.render.rafHandle = null;
    }

    async function postJson(url, body) {
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify(body),
        });

        return response.json();
    }

    async function refreshSnapshot() {
        if (state.pollInFlight) return;
        state.pollInFlight = true;
        const requestStartedAt = Date.now();

        try {
            const url = new URL(payload.snapshotRoute, window.location.origin);
            url.searchParams.set('sessionToken', payload.sessionToken);
            const response = await fetch(url.toString(), {
                method: 'GET',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
            });
            const json = await response.json();
            if (json.status === 200 && json.data) {
                const recoveredFromConnectionDelay = state.render.connectionState !== 'live';
                if (requestStartedAt < state.render.latestUiSyncStamp) {
                    state.render.lastSuccessfulSnapshotAt = Date.now();
                    state.render.failedSnapshotCount = 0;
                    updateConnectionState('live');
                    if (recoveredFromConnectionDelay) {
                        state.render.lastPhaseKey = null;
                        syncUi();
                    }
                    return;
                }

                state.snapshot = {
                    ...json.data,
                    myBet: normalizeBetPayload(json.data.myBet || null),
                    myBets: normalizeMyBets(json.data.myBets, json.data.myBet || null),
                };
                state.render.latestUiSyncStamp = requestStartedAt;
                state.render.serverNowMs = json.data.serverTime ? Date.parse(json.data.serverTime) : Date.now();
                state.render.clientSyncPerfMs = performance.now();
                state.render.lastSuccessfulSnapshotAt = Date.now();
                state.render.failedSnapshotCount = 0;
                updateConnectionState('live');
                if (recoveredFromConnectionDelay) {
                    state.render.lastPhaseKey = null;
                }
                if (!state.render.recoveryNoticeShown && (activeBets().length > 0 || ['flying', 'crashed'].includes(state.snapshot.currentRound?.status || ''))) {
                    state.render.recoveryNoticeShown = true;
                    state.render.recoveryNoticeUntil = Date.now() + 4200;
                }
                syncUi();
                await maybeRunAutomation();
            } else if (json.message) {
                updateConnectionState('delayed');
                nodes.statusBanner.textContent = humanizeRequestError(json.message);
            }
        } catch (_error) {
            state.render.failedSnapshotCount += 1;
            updateConnectionState(state.render.failedSnapshotCount > 1 ? 'reconnecting' : 'delayed');
            nodes.statusBanner.textContent = skyText('connection_delayed', 'Connection delayed. Re-syncing authoritative round state...');
        } finally {
            state.pollInFlight = false;
        }
    }

    async function placeBet(lane) {
        const laneKey = String(lane);
        if (state.submittingByLane[laneKey]) {
            return;
        }
        if (!roundStillAcceptsBets(state.snapshot.currentRound || {})) {
            nodes.statusBanner.textContent = skyText('betting_window_closed', 'Betting window already closed for this round.');
            syncUi();
            return;
        }
        state.submittingByLane[laneKey] = true;
        syncUi();
        try {
            const actionRequestKey = requestKey();
            const requestStartedAt = Date.now();
            const json = await postJson(payload.betRoute, {
                sessionToken: payload.sessionToken,
                lane,
                betAmount: state.selectedBets[laneKey],
                crashRoundId: state.snapshot.currentRound.id,
                requestKey: actionRequestKey,
            });
            if (json.status === 200 && json.data) {
                applyCanonicalBalance(json.data, {
                    requestRef: actionRequestKey,
                    stamp: requestStartedAt,
                });
                state.automation.lastBetAmount = Number(json.data.betAmount || state.selectedBets[laneKey] || 0);
                state.snapshot.myBets[laneKey] = normalizeBetPayload({
                    roundId: json.data.roundId,
                    lane: json.data.lane || lane,
                    status: json.data.status,
                    betAmount: json.data.betAmount,
                    cashoutMultiplier: json.data.cashoutMultiplier || 0,
                    cashoutAmount: json.data.cashoutAmount || 0,
                    winAmount: json.data.winAmount || 0,
                    refundAmount: json.data.refundAmount || 0,
                    resultSummary: json.data.summary,
                }, lane);
                state.snapshot.myBet = state.snapshot.myBets['1'] || state.snapshot.myBets['2'] || null;
                syncUi();
                await refreshSnapshot();
            } else {
                nodes.statusBanner.textContent = humanizeRequestError(json.message);
            }
        } catch (_error) {
            nodes.statusBanner.textContent = skyText('connection_delayed','Connection delayed. Re-syncing authoritative round state...');
            await refreshSnapshot();
        } finally {
            state.submittingByLane[laneKey] = false;
            syncUi();
        }
    }

    async function cashout(lane) {
        const laneKey = String(lane);
        const bet = state.snapshot.myBets?.[laneKey];
        if (state.submittingByLane[laneKey] || !bet?.roundId) return;
        state.submittingByLane[laneKey] = true;
        syncUi();
        try {
            const actionRequestKey = requestKey();
            const requestStartedAt = Date.now();
            const json = await postJson(payload.cashoutRoute, {
                sessionToken: payload.sessionToken,
                betRoundId: bet.roundId,
                requestKey: actionRequestKey,
            });
            if (json.status === 200 && json.data) {
                applyCanonicalBalance(json.data, {
                    requestRef: actionRequestKey,
                    stamp: requestStartedAt,
                });
                state.snapshot.myBets[laneKey] = normalizeBetPayload({
                    roundId: json.data.roundId,
                    lane: json.data.lane || lane,
                    status: json.data.status,
                    betAmount: json.data.betAmount,
                    cashoutMultiplier: json.data.cashoutMultiplier || 0,
                    cashoutAmount: json.data.cashoutAmount || 0,
                    winAmount: json.data.winAmount || 0,
                    refundAmount: json.data.refundAmount || 0,
                    resultSummary: json.data.summary,
                }, lane);
                state.snapshot.myBet = state.snapshot.myBets['1'] || state.snapshot.myBets['2'] || null;
                syncUi();
                await refreshSnapshot();
            } else {
                nodes.statusBanner.textContent = humanizeRequestError(json.message);
                await refreshSnapshot();
            }
        } catch (_error) {
            nodes.statusBanner.textContent = skyText('connection_delayed','Connection delayed. Re-syncing authoritative round state...');
            await refreshSnapshot();
        } finally {
            state.submittingByLane[laneKey] = false;
            syncUi();
        }
    }

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            resizeFlightCanvas();
            startAnimationLoop();
            refreshSnapshot();
        } else {
            stopBackgroundAmbience();
            stopEngineSound();
            stopAnimationLoop();
        }
    });
    window.addEventListener('resize', resizeFlightCanvas);
    window.addEventListener('focus', refreshSnapshot);
    visibleLanes().forEach((lane) => {
        const laneKey = String(lane);
        nodes.betButtons[laneKey].addEventListener('click', async () => {
            primeAudioLandscape();
            await placeBet(lane);
        });
        nodes.cashoutButtons[laneKey].addEventListener('click', async () => {
            primeAudioLandscape();
            await cashout(lane);
        });
    });
    nodes.soundToggle.addEventListener('click', async () => {
        const ctx = await unlockAudio();
        if (!ctx) {
            return;
        }

        state.render.soundEnabled = !state.render.soundEnabled;
        updateSoundToggle();

        if (state.render.soundEnabled) {
            await activateAudioLandscape({ ping: true });
        } else {
            stopBackgroundAmbience();
            stopEngineSound();
        }
    });
    nodes.autoBetToggle.addEventListener('change', () => {
        state.automation.autoBetEnabled = nodes.autoBetToggle.checked;
        if (!state.automation.autoBetEnabled) {
            state.automation.autoBetRoundId = null;
        }
        setAutoStatus(state.automation.autoBetEnabled
            ? skyText('auto_bet_enabled', 'Auto bet enabled. Lane 1 will place the selected stake when betting opens.')
            : (state.automation.autoCashoutEnabled
                ? skyText('auto_cashout_remains', 'Auto cashout remains armed for :targetx on Lane 1.', { target: Number(state.automation.autoCashoutTarget || 1.5).toFixed(2) })
                : skyText('automation_idle', 'Automation is idle. Auto bet places the current Lane 1 stake at the start of each betting window.')));
        syncAutoUi();
    });
    nodes.repeatStakeToggle.addEventListener('change', () => {
        state.automation.repeatLastStakeEnabled = nodes.repeatStakeToggle.checked;
        setAutoStatus(state.automation.repeatLastStakeEnabled
            ? skyText('repeat_enabled', 'Repeat last stake enabled. Auto bet will reuse :amount on Lane 1.', { amount: money(state.automation.lastBetAmount || state.selectedBets['1'] || 0) })
            : skyText('repeat_disabled', 'Repeat last stake disabled. Auto bet will use the current selected Lane 1 stake.'));
        syncAutoUi();
    });
    nodes.autoCashoutToggle.addEventListener('change', () => {
        state.automation.autoCashoutEnabled = nodes.autoCashoutToggle.checked;
        if (!state.automation.autoCashoutEnabled) {
            state.automation.autoCashoutBetRoundId = null;
        }
        setAutoStatus(state.automation.autoCashoutEnabled
            ? skyText('auto_cashout_enabled', 'Auto cashout enabled for :targetx on Lane 1.', { target: Number(state.automation.autoCashoutTarget || 1.5).toFixed(2) })
            : (state.automation.autoBetEnabled
                ? skyText('auto_bet_remains', 'Auto bet remains armed for Lane 1.')
                : skyText('automation_idle', 'Automation is idle. Auto bet places the current Lane 1 stake at the start of each betting window.')));
        syncAutoUi();
    });
    nodes.stopOnWinToggle.addEventListener('change', () => {
        state.automation.stopOnWinEnabled = nodes.stopOnWinToggle.checked;
        setAutoStatus(state.automation.stopOnWinEnabled
            ? skyText('stop_win_enabled', 'Stop on win enabled. Automation will pause after the next Lane 1 win.')
            : skyText('stop_win_disabled', 'Stop on win disabled.'));
        syncAutoUi();
    });
    nodes.stopOnLossToggle.addEventListener('change', () => {
        state.automation.stopOnLossEnabled = nodes.stopOnLossToggle.checked;
        setAutoStatus(state.automation.stopOnLossEnabled
            ? skyText('stop_loss_enabled', 'Stop on loss enabled. Automation will pause after the next Lane 1 loss.')
            : skyText('stop_loss_disabled', 'Stop on loss disabled.'));
        syncAutoUi();
    });
    nodes.stopLowBalanceToggle.addEventListener('change', () => {
        state.automation.stopLowBalanceEnabled = nodes.stopLowBalanceToggle.checked;
        setAutoStatus(state.automation.stopLowBalanceEnabled
            ? skyText('stop_low_enabled', 'Stop low balance enabled. Automation will pause below :amount.', { amount: money(state.automation.stopLowBalanceValue) })
            : skyText('stop_low_disabled', 'Stop low balance disabled.'));
        syncAutoUi();
    });
    nodes.stopLowBalanceValue.addEventListener('change', () => {
        const normalized = clamp(Number(nodes.stopLowBalanceValue.value || 0), 0, 1000000);
        state.automation.stopLowBalanceValue = Number(normalized.toFixed(2));
        nodes.stopLowBalanceValue.value = state.automation.stopLowBalanceValue.toFixed(2);
        setAutoStatus(skyText('minimum_balance_set', 'Minimum balance floor set to :amount.', { amount: money(state.automation.stopLowBalanceValue) }));
        syncAutoUi();
    });
    nodes.autoCashoutTarget.addEventListener('change', () => {
        const normalized = clamp(Number(nodes.autoCashoutTarget.value || 1.5), 1.01, 100);
        state.automation.autoCashoutTarget = Number(normalized.toFixed(2));
        nodes.autoCashoutTarget.value = state.automation.autoCashoutTarget.toFixed(2);
        if (state.automation.autoCashoutEnabled) {
            setAutoStatus(skyText('auto_cashout_target_updated', 'Auto cashout target updated to :targetx for Lane 1.', { target: state.automation.autoCashoutTarget.toFixed(2) }));
        } else {
            setAutoStatus(skyText('auto_cashout_target_set', 'Auto cashout target set to :targetx.', { target: state.automation.autoCashoutTarget.toFixed(2) }));
        }
        syncAutoUi();
    });
    nodes.roundDetailsButton.addEventListener('click', () => {
        syncRoundDrawer();
        nodes.roundDrawer.classList.add('is-open');
        nodes.roundDrawer.setAttribute('aria-hidden', 'false');
        nodes.closeRoundDrawer.focus();
        announceLive(skyText('drawer_opened', 'Provably fair drawer opened.'));
        verifyCurrentRound();
    });
    nodes.closeRoundDrawer.addEventListener('click', () => {
        nodes.roundDrawer.classList.remove('is-open');
        nodes.roundDrawer.setAttribute('aria-hidden', 'true');
        nodes.roundDetailsButton.focus();
        announceLive(skyText('drawer_closed', 'Provably fair drawer closed.'));
    });
    nodes.roundDrawer.addEventListener('click', (event) => {
        if (event.target === nodes.roundDrawer) {
            nodes.roundDrawer.classList.remove('is-open');
            nodes.roundDrawer.setAttribute('aria-hidden', 'true');
        }
    });
    nodes.verifyNowButton.addEventListener('click', verifyCurrentRound);
    nodes.copyRoundIdButton.addEventListener('click', () => copyVerificationValue(state.snapshot.currentRound?.id || '', skyText('copy_round_id', 'Copy round ID')));
    nodes.copyRevealButton.addEventListener('click', () => copyVerificationValue(state.snapshot.currentRound?.revealedServerSeed || '', skyText('copy_reveal', 'Copy reveal')));
    window.addEventListener('keydown', async (event) => {
        if (event.defaultPrevented || event.metaKey || event.ctrlKey || event.altKey) {
            return;
        }

        const target = event.target;
        const tagName = target instanceof HTMLElement ? target.tagName : '';
        const isEditable = target instanceof HTMLElement && (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(tagName));
        if (isEditable) {
            return;
        }

        if (event.key === 'Escape' && nodes.roundDrawer.classList.contains('is-open')) {
            event.preventDefault();
            nodes.closeRoundDrawer.click();
            return;
        }

        const key = event.key.toLowerCase();
        if (key === 'm') {
            event.preventDefault();
            nodes.soundToggle.click();
            return;
        }

        if (key === 'v') {
            event.preventDefault();
            if (nodes.roundDrawer.classList.contains('is-open')) {
                nodes.closeRoundDrawer.click();
            } else {
                nodes.roundDetailsButton.click();
            }
            return;
        }

        if (key === 'b') {
            event.preventDefault();
            if (!nodes.betButtons['1'].disabled) {
                primeAudioLandscape();
                await placeBet(1);
            }
            return;
        }

        if (key === 'c') {
            event.preventDefault();
            if (nodes.cashoutButtons['1'].style.display !== 'none' && !nodes.cashoutButtons['1'].disabled) {
                primeAudioLandscape();
                await cashout(1);
            }
            return;
        }

        if (['1', '2', '3'].includes(key)) {
            const index = Number(key) - 1;
            const betValues = payload.betValues || [];
            if (typeof betValues[index] !== 'undefined') {
                event.preventDefault();
                state.selectedBets['1'] = Number(betValues[index]);
                renderBetButtons(1);
                syncUi();
                announceLive(skyText('stake_selected', 'Lane 1 stake selected: :amount.', { amount: money(state.selectedBets['1']) }));
            }
        }
    });

    renderAllBetButtons();
    updateSoundToggle();
    updateConnectionState('live');
    armAudioUnlock();
    resizeFlightCanvas();
    syncUi();
    startAnimationLoop();
    refreshSnapshot();
    window.setInterval(() => {
        if (!document.hidden) {
            refreshSnapshot();
        }
    }, Math.max(100, Number(payload.snapshotIntervalMs || 150)));
})();
</script>
@endif
</body>
</html>
