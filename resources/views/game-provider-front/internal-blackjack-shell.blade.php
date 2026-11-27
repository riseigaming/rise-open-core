{{--
    Shared mobile-first blackjack game surface.

    Wrappers pass:
      - $payload         array  payload from BlackjackService::playPayload()
      - $session         model  the InternalGameSession instance
      - $bjGameLabel     string short title shown on the felt crest
      - $bjGameTagline   string short tagline shown under the crest
      - $bjTheme         string 'classic' | 'salon' — drives data-bj-theme attribute
      - $bjThemeTokens   array  CSS custom-property overrides for theming

    Hard rules for this file:
      - Server is authoritative for round/card/dealer/result/payout/balance/settlement.
      - Animations and DOM toggles are presentation-only. They never decide truth.
      - Request/response contract is unchanged.
      - Existing test selectors are preserved verbatim.
--}}
@php
    $bjGameLabel = $bjGameLabel ?? ($payload['gameName'] ?? 'Blackjack');
    $bjGameTagline = $bjGameTagline ?? trans('cms_public.games.blackjack.tagline_default');
    $bjTheme = $bjTheme ?? 'classic';
    $bjThemeTokens = $bjThemeTokens ?? [];
    $bjCurrency = (string) ($payload['currency'] ?? 'TRY');
    $bjBalance = (float) ($payload['balance'] ?? 0);
    $bjFirstBet = (float) (($payload['betValues'][0] ?? 10));
    $bjPayoutMultiplier = (float) data_get($payload, 'rules.blackjackPayoutMultiplier', 2.5);
    $bjDealerHitsSoft17 = (bool) data_get($payload, 'rules.dealerHitsSoft17', false);
    $bjDoublePolicy = (string) data_get($payload, 'rules.doublePolicy', 'any_two_cards');
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#06311f">
    <title>{{ $payload['gameName'] ?? $bjGameLabel }}</title>
    <style>
        :root {
            /* Themable tokens (overridable by wrapper). Two-stop felt + accent set. */
            --bj-felt-1: #0e4d34;
            --bj-felt-2: #052e1d;
            --bj-felt-edge: #03190f;
            --bj-felt-pattern: rgba(255, 255, 255, 0.025);
            --bj-accent: #f0c869;
            --bj-accent-warm: #e89f4a;
            --bj-accent-soft: rgba(240, 200, 105, 0.18);
            --bj-accent-glow: rgba(240, 200, 105, 0.32);
            --bj-chip-rim: #f6dd96;
            --bj-crest-line: rgba(240, 200, 105, 0.36);
            /* Fixed structural tokens */
            --bj-ink: #fbf4dc;
            --bj-ink-soft: rgba(251, 244, 220, 0.74);
            --bj-ink-muted: rgba(251, 244, 220, 0.5);
            --bj-success: #8fe3b3;
            --bj-danger: #ff8a78;
            --bj-warning: #f1bf69;
            --bj-card-face: #fcf8ec;
            --bj-card-face-2: #e9dec0;
            --bj-card-red: #b5301f;
            --bj-card-black: #1a1815;
            --bj-shadow-strong: 0 16px 38px rgba(0, 0, 0, 0.42);
            --bj-shadow-soft: 0 8px 18px rgba(0, 0, 0, 0.30);
            /* Card sizing — bigger and felt-centered */
            --bj-card-w: clamp(72px, 22vw, 104px);
            --bj-card-overlap: clamp(20px, 7vw, 30px);
@foreach ($bjThemeTokens as $name => $value)
            {{ $name }}: {{ $value }};
@endforeach
        }

        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            padding: 0;
            height: 100%;
            overflow: hidden;
            overscroll-behavior: none;
            background: #000;
        }
        body {
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto,
                "Helvetica Neue", Arial, "Noto Sans", sans-serif;
            color: var(--bj-ink);
            font-feature-settings: "tnum" 1, "lnum" 1, "ss01" 1;
            -webkit-font-smoothing: antialiased;
            -webkit-text-size-adjust: 100%;
            -webkit-tap-highlight-color: transparent;
            touch-action: manipulation;
        }
        button {
            font: inherit;
            color: inherit;
            background: transparent;
            border: 0;
            cursor: pointer;
        }
        [hidden] { display: none !important; }

        /* === App shell: full viewport, no scrolling during play === */
        .bj-app {
            position: fixed;
            inset: 0;
            display: grid;
            grid-template-rows: auto 1fr auto;
            background:
                radial-gradient(ellipse 90% 60% at 50% 18%, var(--bj-felt-1) 0%, var(--bj-felt-2) 56%, var(--bj-felt-edge) 100%);
        }

        /* === HUD strip: floating glass overlay, never opaque slab === */
        .bj-hud {
            position: relative;
            z-index: 3;
            display: grid;
            grid-template-columns: 1fr auto 1fr auto;
            align-items: center;
            gap: 10px;
            padding:
                calc(env(safe-area-inset-top, 0px) + 8px)
                calc(env(safe-area-inset-right, 0px) + 14px)
                8px
                calc(env(safe-area-inset-left, 0px) + 14px);
            background: linear-gradient(180deg, rgba(0, 0, 0, 0.42), rgba(0, 0, 0, 0) 96%);
        }
        .bj-hud-balance,
        .bj-hud-mode {
            display: flex;
            flex-direction: column;
            min-width: 0;
            line-height: 1;
        }
        .bj-hud-mode { align-items: flex-end; }
        .bj-hud-label {
            font-size: 9px;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: var(--bj-ink-muted);
            margin-bottom: 4px;
        }
        .bj-hud-value {
            font-size: 17px;
            font-weight: 800;
            font-variant-numeric: tabular-nums;
            color: var(--bj-ink);
            display: inline-flex;
            align-items: baseline;
            gap: 6px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .bj-hud-value small {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.12em;
            color: var(--bj-ink-muted);
        }
        .bj-hud-bet {
            display: grid;
            place-items: center;
        }
        .bj-bet-chip {
            position: relative;
            width: 46px;
            height: 46px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background:
                radial-gradient(circle at 32% 28%, rgba(255, 255, 255, 0.55), transparent 36%),
                conic-gradient(var(--bj-chip-rim) 0deg, var(--bj-accent-warm) 28deg, var(--bj-chip-rim) 56deg, var(--bj-accent-warm) 84deg, var(--bj-chip-rim) 112deg, var(--bj-accent-warm) 140deg, var(--bj-chip-rim) 168deg, var(--bj-accent-warm) 196deg, var(--bj-chip-rim) 224deg, var(--bj-accent-warm) 252deg, var(--bj-chip-rim) 280deg, var(--bj-accent-warm) 308deg, var(--bj-chip-rim) 336deg, var(--bj-accent-warm) 360deg);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.36), inset 0 0 0 1px rgba(255, 255, 255, 0.18);
            color: #2b1606;
        }
        .bj-bet-chip::after {
            content: "";
            position: absolute;
            inset: 6px;
            border-radius: 50%;
            background: linear-gradient(180deg, #fbe7a8, #d59b3e);
            box-shadow: inset 0 0 0 1px rgba(110, 58, 8, 0.22);
        }
        .bj-bet-chip strong {
            position: relative;
            z-index: 1;
            font-size: 12px;
            font-weight: 800;
            font-variant-numeric: tabular-nums;
            letter-spacing: 0.02em;
        }
        .bj-bet-chip-label {
            position: absolute;
            top: -10px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 8px;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: var(--bj-ink-muted);
            white-space: nowrap;
        }
        .bj-info-btn {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: 1px solid var(--bj-crest-line);
            background: rgba(0, 0, 0, 0.34);
            color: var(--bj-accent);
            font-weight: 800;
            font-style: italic;
            font-size: 14px;
            display: grid;
            place-items: center;
            transition: transform .15s ease, background-color .15s ease;
        }
        .bj-info-btn:active { transform: scale(0.94); background: rgba(0, 0, 0, 0.52); }

        /* === Felt board: full play area, all gameplay lives here === */
        .bj-felt {
            position: relative;
            min-height: 0;
            overflow: hidden;
            isolation: isolate;
        }
        .bj-felt::before,
        .bj-felt::after {
            content: "";
            position: absolute;
            inset: 0;
            pointer-events: none;
        }
        /* Subtle weave / pattern texture; theme-overridable */
        .bj-felt::before {
            background:
                repeating-linear-gradient(45deg, var(--bj-felt-pattern) 0 2px, transparent 2px 9px),
                repeating-linear-gradient(-45deg, var(--bj-felt-pattern) 0 2px, transparent 2px 9px);
            opacity: 0.7;
            mix-blend-mode: screen;
            z-index: 0;
        }
        /* Vignette and inner ring glow */
        .bj-felt::after {
            background:
                radial-gradient(ellipse 70% 50% at 50% 50%, transparent 56%, rgba(0, 0, 0, 0.38) 100%);
            z-index: 0;
        }
        .bj-felt-ring {
            position: absolute;
            inset: 14px;
            border-radius: 50% / 28%;
            border: 1px solid var(--bj-crest-line);
            box-shadow: inset 0 0 60px rgba(0, 0, 0, 0.38), inset 0 0 0 4px rgba(0, 0, 0, 0.18);
            z-index: 0;
            pointer-events: none;
        }

        /* === Seat rows on the felt === */
        .bj-row {
            position: absolute;
            left: 0;
            right: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 0 16px;
            z-index: 1;
        }
        .bj-row--dealer { top: 8px; }
        .bj-row--player { bottom: 8px; }
        .bj-seat-meta {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 4px 10px;
            border-radius: 999px;
            background: rgba(0, 0, 0, 0.36);
            border: 1px solid var(--bj-crest-line);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
        }
        .bj-seat-name {
            font-size: 9px;
            letter-spacing: 0.22em;
            text-transform: uppercase;
            color: var(--bj-accent);
            font-weight: 800;
        }
        .bj-seat-total {
            font-size: 12px;
            font-weight: 800;
            color: var(--bj-ink);
            font-variant-numeric: tabular-nums;
            min-width: 24px;
            text-align: center;
        }
        .bj-cards {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
            min-height: var(--bj-card-w);
            padding: 4px 0;
        }

        /* === Card === */
        .bj-card {
            --bj-fan-rotate: 0deg;
            --bj-fan-shift: 0px;
            --bj-deal-delay: 0ms;
            --bj-flip-delay: 360ms;
            position: relative;
            width: var(--bj-card-w);
            aspect-ratio: 5 / 7;
            margin-left: calc(-1 * var(--bj-card-overlap));
            transform: translateX(var(--bj-fan-shift)) rotate(var(--bj-fan-rotate));
            transform-origin: 50% 100%;
            perspective: 1200px;
            filter: drop-shadow(0 8px 12px rgba(0, 0, 0, 0.36));
        }
        .bj-cards .bj-card:first-child { margin-left: 0; }
        .bj-card-deal {
            width: 100%;
            height: 100%;
            animation: bj-deal-flight .56s cubic-bezier(.22, .68, .22, 1) both;
            animation-delay: var(--bj-deal-delay);
        }
        .bj-card-flip {
            position: relative;
            width: 100%;
            height: 100%;
            transform-style: preserve-3d;
            animation: bj-flip-open .50s cubic-bezier(.22, .7, .22, 1) both;
            animation-delay: var(--bj-flip-delay);
        }
        .bj-card.is-static .bj-card-deal,
        .bj-card.is-static .bj-card-flip { animation: none; }
        .bj-card.is-static .bj-card-flip { transform: rotateY(180deg); }
        .bj-card.is-static.is-hidden .bj-card-flip { transform: rotateY(0deg); }
        .bj-card.is-hidden .bj-card-flip { animation: none; transform: rotateY(0deg); }

        .bj-card-face {
            position: absolute;
            inset: 0;
            border-radius: 11px;
            backface-visibility: hidden;
            -webkit-backface-visibility: hidden;
            overflow: hidden;
            box-shadow:
                inset 0 0 0 1px rgba(0, 0, 0, 0.08),
                inset 0 -3px 8px rgba(0, 0, 0, 0.08);
        }
        .bj-card-front {
            background:
                linear-gradient(180deg, var(--bj-card-face) 0%, var(--bj-card-face-2) 100%);
            color: var(--bj-card-black);
            padding: 7px 8px;
            display: grid;
            grid-template-rows: auto 1fr auto;
            transform: rotateY(180deg);
        }
        .bj-card-front.is-red { color: var(--bj-card-red); }
        .bj-card-back {
            background:
                radial-gradient(circle at 50% 50%, var(--bj-accent-soft), transparent 50%),
                linear-gradient(135deg, var(--bj-felt-1) 0%, var(--bj-felt-2) 100%);
            color: var(--bj-accent);
            border-color: var(--bj-accent-soft);
        }
        .bj-card-back::before {
            content: "";
            position: absolute;
            inset: 5px;
            border-radius: 8px;
            border: 1px solid var(--bj-crest-line);
            background:
                repeating-linear-gradient(45deg, rgba(255, 255, 255, 0.04) 0 4px, transparent 4px 10px),
                repeating-linear-gradient(-45deg, rgba(255, 255, 255, 0.04) 0 4px, transparent 4px 10px);
        }
        .bj-card-back-mark {
            position: absolute;
            inset: 0;
            display: grid;
            place-items: center;
            font-size: 13px;
            letter-spacing: 0.18em;
            font-weight: 800;
            color: var(--bj-accent);
            text-shadow: 0 1px 0 rgba(0, 0, 0, 0.32);
        }
        .bj-card-rank {
            font-size: clamp(13px, 4.4vw, 18px);
            font-weight: 800;
            line-height: 1;
            font-variant-numeric: tabular-nums;
        }
        .bj-card-rank--bottom { justify-self: end; transform: rotate(180deg); }
        .bj-card-suit {
            font-size: clamp(22px, 8vw, 32px);
            font-weight: 800;
            text-align: center;
            line-height: 1;
            align-self: center;
            justify-self: center;
        }

        @keyframes bj-deal-flight {
            from {
                transform: translate(110%, -28%) scale(0.62) rotate(-22deg);
                opacity: 0;
            }
            55% { opacity: 1; }
            to {
                transform: translate(0, 0) scale(1) rotate(0deg);
                opacity: 1;
            }
        }
        @keyframes bj-flip-open {
            from { transform: rotateY(0deg); }
            to { transform: rotateY(180deg); }
        }

        /* === Center stage: crest (idle) / outcome banner (resolved) === */
        .bj-center {
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            transform: translateY(-50%);
            display: grid;
            place-items: center;
            text-align: center;
            padding: 0 20px;
            z-index: 1;
            pointer-events: none;
        }
        .bj-crest {
            display: grid;
            gap: 8px;
            place-items: center;
            opacity: 0.85;
            transition: opacity .22s ease;
        }
        .bj-crest-rule-line {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            width: max-content;
        }
        .bj-crest-rule-line::before,
        .bj-crest-rule-line::after {
            content: "";
            width: 26px;
            height: 1px;
            background: var(--bj-crest-line);
        }
        .bj-crest-title {
            font-family: "Georgia", "Times New Roman", ui-serif, serif;
            font-style: italic;
            font-weight: 700;
            font-size: clamp(20px, 6.4vw, 28px);
            letter-spacing: 0.04em;
            color: var(--bj-accent);
            text-shadow: 0 2px 0 rgba(0, 0, 0, 0.30);
        }
        .bj-crest-tagline {
            font-size: 10px;
            letter-spacing: 0.32em;
            text-transform: uppercase;
            color: var(--bj-ink-muted);
            font-weight: 600;
        }
        .bj-app[data-bj-round-active="true"] .bj-crest { opacity: 0; }

        .bj-banner {
            position: absolute;
            inset: 50% 16px auto 16px;
            transform: translateY(-50%) scale(0.92);
            display: grid;
            justify-items: center;
            gap: 6px;
            padding: 18px 22px;
            border-radius: 18px;
            background:
                radial-gradient(circle at 50% 0%, rgba(255, 255, 255, 0.12), transparent 60%),
                linear-gradient(180deg, rgba(0, 0, 0, 0.62), rgba(0, 0, 0, 0.86));
            border: 1px solid var(--bj-crest-line);
            box-shadow: var(--bj-shadow-strong), 0 0 0 1px rgba(0, 0, 0, 0.4) inset;
            opacity: 0;
            pointer-events: none;
            transition: opacity .26s cubic-bezier(.22, .7, .22, 1),
                        transform .32s cubic-bezier(.22, .9, .26, 1);
            z-index: 2;
        }
        .bj-banner.is-visible {
            opacity: 1;
            transform: translateY(-50%) scale(1);
        }
        .bj-banner-title {
            font-family: "Georgia", "Times New Roman", ui-serif, serif;
            font-weight: 800;
            font-size: clamp(28px, 9vw, 40px);
            letter-spacing: 0.04em;
            line-height: 1;
            text-transform: uppercase;
        }
        .bj-banner-amount {
            font-size: 14px;
            font-weight: 700;
            color: var(--bj-ink-soft);
            font-variant-numeric: tabular-nums;
            letter-spacing: 0.04em;
        }
        .bj-banner[data-outcome="win"] .bj-banner-title,
        .bj-banner[data-outcome="blackjack"] .bj-banner-title { color: var(--bj-success); }
        .bj-banner[data-outcome="blackjack"] {
            border-color: rgba(143, 227, 179, 0.40);
            box-shadow: var(--bj-shadow-strong), 0 0 30px rgba(143, 227, 179, 0.28);
        }
        .bj-banner[data-outcome="loss"] .bj-banner-title { color: var(--bj-danger); }
        .bj-banner[data-outcome="push"] .bj-banner-title { color: var(--bj-warning); }

        /* === Action dock: mobile game controller === */
        .bj-dock {
            position: relative;
            z-index: 3;
            display: grid;
            gap: 8px;
            padding:
                10px
                calc(env(safe-area-inset-right, 0px) + 12px)
                calc(env(safe-area-inset-bottom, 0px) + 10px)
                calc(env(safe-area-inset-left, 0px) + 12px);
            background: linear-gradient(180deg, rgba(0, 0, 0, 0.0) 0%, rgba(0, 0, 0, 0.55) 38%, rgba(0, 0, 0, 0.78) 100%);
        }
        .bj-dock-row {
            display: flex;
            gap: 8px;
            align-items: stretch;
        }
        .bj-dock-row--chips {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
        }
        .bj-dock-row--chips::-webkit-scrollbar { display: none; }
        .bj-chip-rail {
            display: inline-flex;
            gap: 8px;
            margin: 0 auto;
            padding: 2px;
        }
        .bj-chip {
            flex: 0 0 auto;
            width: 54px;
            height: 54px;
            padding: 0;
            border-radius: 50%;
            position: relative;
            overflow: visible;
            display: inline-grid;
            place-items: center;
            background:
                radial-gradient(circle at 32% 28%, rgba(255, 255, 255, 0.5), transparent 38%),
                linear-gradient(180deg, var(--bj-chip-face-1, #f7e4a8) 0%, var(--bj-chip-face-2, #d9a94b) 100%);
            color: var(--bj-chip-text, #2a1906);
            box-shadow:
                inset 0 0 0 2px var(--bj-chip-rim, #f6dd96),
                inset 0 0 0 4px rgba(0, 0, 0, 0.12),
                inset 0 0 0 5px var(--bj-chip-rim, #f6dd96),
                0 6px 12px rgba(0, 0, 0, 0.32);
            font-weight: 800;
            transition: transform .14s ease, box-shadow .14s ease;
        }
        /* Casino-convention chip color tiers, applied via JS */
        .bj-chip[data-tier="1"] { --bj-chip-face-1: #fdfbf4; --bj-chip-face-2: #d6cfb8; --bj-chip-rim: #ece2c4; --bj-chip-text: #2c2820; }
        .bj-chip[data-tier="2"] { --bj-chip-face-1: #ec6d6d; --bj-chip-face-2: #962a2a; --bj-chip-rim: #ffd0d0; --bj-chip-text: #fff5f1; }
        .bj-chip[data-tier="3"] { --bj-chip-face-1: #5fa1e6; --bj-chip-face-2: #1f4c83; --bj-chip-rim: #cee2f7; --bj-chip-text: #f4f9ff; }
        .bj-chip[data-tier="4"] { --bj-chip-face-1: #4fb784; --bj-chip-face-2: #195d3a; --bj-chip-rim: #c8e9d6; --bj-chip-text: #f3fbf6; }
        .bj-chip[data-tier="5"] { --bj-chip-face-1: #3a3a3a; --bj-chip-face-2: #131313; --bj-chip-rim: #cfb87b; --bj-chip-text: #faf2dc; }
        .bj-chip[data-tier="6"] { --bj-chip-face-1: #c98be2; --bj-chip-face-2: #5b1f78; --bj-chip-rim: #efd7ff; --bj-chip-text: #fbf2ff; }
        .bj-chip strong {
            font-size: 12px;
            line-height: 1;
            font-variant-numeric: tabular-nums;
            letter-spacing: 0.02em;
        }
        .bj-chip.is-active {
            transform: translateY(-3px) scale(1.06);
            box-shadow:
                inset 0 0 0 2px var(--bj-chip-rim, #f6dd96),
                inset 0 0 0 4px rgba(0, 0, 0, 0.18),
                inset 0 0 0 5px var(--bj-chip-rim, #f6dd96),
                0 10px 18px rgba(0, 0, 0, 0.42),
                0 0 0 3px var(--bj-accent-glow);
        }
        .bj-chip:active { transform: translateY(0) scale(0.98); }
        .bj-chip:disabled { opacity: 0.48; cursor: not-allowed; transform: none; }

        .bj-cta,
        .bj-action {
            flex: 1 1 0;
            min-height: 58px;
            border-radius: 16px;
            font-size: 15px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            position: relative;
            isolation: isolate;
            transition: transform .12s ease, opacity .15s ease, box-shadow .15s ease, background-color .15s ease;
        }
        .bj-cta--primary {
            background:
                linear-gradient(180deg, var(--bj-accent) 0%, var(--bj-accent-warm) 100%);
            color: #2a1706;
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.5),
                0 10px 22px rgba(232, 159, 74, 0.32);
        }
        .bj-cta--primary::before {
            content: "";
            position: absolute;
            inset: 0;
            border-radius: 16px;
            box-shadow: inset 0 -2px 0 rgba(0, 0, 0, 0.20);
            pointer-events: none;
        }
        .bj-action {
            background: rgba(255, 255, 255, 0.08);
            color: var(--bj-ink);
            border: 1px solid var(--bj-crest-line);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
        }
        .bj-action--warning {
            background: rgba(241, 191, 105, 0.18);
            border-color: rgba(241, 191, 105, 0.40);
            color: var(--bj-accent);
        }
        .bj-cta:active,
        .bj-action:active { transform: translateY(1px) scale(0.99); }
        .bj-cta:disabled,
        .bj-action:disabled {
            opacity: 0.42;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        .bj-cta.is-loading,
        .bj-action.is-loading {
            pointer-events: none;
            color: transparent !important;
        }
        .bj-cta.is-loading::after,
        .bj-action.is-loading::after {
            content: "";
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            margin: -10px 0 0 -10px;
            border: 2px solid rgba(255, 255, 255, 0.34);
            border-top-color: rgba(255, 255, 255, 0.92);
            border-radius: 50%;
            animation: bj-spin .8s linear infinite;
        }
        .bj-cta--primary.is-loading::after {
            border-color: rgba(43, 23, 6, 0.36);
            border-top-color: rgba(43, 23, 6, 0.92);
        }
        @keyframes bj-spin { to { transform: rotate(360deg); } }

        /* Dock state machine: visible rows depend on round phase */
        .bj-dock[data-bj-dock-state="player-turn"] .bj-dock-row--chips,
        .bj-dock[data-bj-dock-state="player-turn"] .bj-dock-row--start { display: none; }
        .bj-dock[data-bj-dock-state="idle"] .bj-dock-row--actions,
        .bj-dock[data-bj-dock-state="resolved"] .bj-dock-row--actions { display: none; }

        /* === Bottom sheet (round details / rules / timeline) === */
        .bj-sheet-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.55);
            opacity: 0;
            transition: opacity .22s ease;
            z-index: 8;
        }
        .bj-app[data-bj-sheet-open="true"] .bj-sheet-backdrop {
            opacity: 1;
        }
        .bj-sheet {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 9;
            max-height: 78dvh;
            display: flex;
            flex-direction: column;
            transform: translateY(100%);
            transition: transform .28s cubic-bezier(.22, .7, .22, 1);
            background:
                linear-gradient(180deg, rgba(20, 36, 28, 0.98) 0%, rgba(8, 18, 13, 0.99) 100%);
            border-top: 1px solid var(--bj-crest-line);
            border-radius: 20px 20px 0 0;
            box-shadow: 0 -20px 40px rgba(0, 0, 0, 0.5);
            padding-bottom: env(safe-area-inset-bottom, 0px);
        }
        .bj-app[data-bj-sheet-open="true"] .bj-sheet {
            transform: translateY(0);
        }
        .bj-sheet-handle {
            width: 44px;
            height: 4px;
            background: var(--bj-ink-muted);
            border-radius: 999px;
            margin: 8px auto 4px;
            flex: 0 0 auto;
        }
        .bj-sheet-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 16px 10px;
            border-bottom: 1px solid var(--bj-crest-line);
            flex: 0 0 auto;
        }
        .bj-sheet-title {
            margin: 0;
            font-size: 11px;
            letter-spacing: 0.20em;
            text-transform: uppercase;
            color: var(--bj-accent);
            font-weight: 800;
        }
        .bj-sheet-close {
            font-size: 11px;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--bj-ink-soft);
            padding: 6px 10px;
            border-radius: 8px;
        }
        .bj-sheet-close:active { background: rgba(255, 255, 255, 0.08); }
        .bj-sheet-body {
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            padding: 12px 16px 18px;
            display: grid;
            gap: 12px;
        }
        .bj-sheet-section h3 {
            margin: 0 0 6px;
            font-size: 10px;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: var(--bj-ink-muted);
            font-weight: 700;
        }
        .bj-status {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px;
        }
        .bj-status > div {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 8px;
            padding: 8px 10px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid var(--bj-crest-line);
            font-size: 12px;
        }
        .bj-status strong {
            font-size: 9px;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--bj-ink-muted);
            font-weight: 700;
        }
        .bj-status span {
            font-weight: 700;
            color: var(--bj-ink);
            font-variant-numeric: tabular-nums;
            text-align: right;
        }
        .bj-message {
            border-radius: 10px;
            background: rgba(0, 0, 0, 0.28);
            border: 1px solid var(--bj-crest-line);
            padding: 10px 12px;
            font-size: 12px;
            line-height: 1.45;
            color: var(--bj-ink-soft);
            min-height: 38px;
        }
        .bj-message.is-error { color: var(--bj-danger); border-color: rgba(255, 138, 120, 0.34); }
        .bj-message.is-success { color: var(--bj-success); border-color: rgba(143, 227, 179, 0.34); }
        .bj-timeline {
            display: grid;
            gap: 6px;
            max-height: 32dvh;
            overflow-y: auto;
        }
        .bj-timeline-item {
            border-radius: 10px;
            padding: 8px 10px;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid var(--bj-crest-line);
            font-size: 11px;
            color: var(--bj-ink-soft);
            display: grid;
            gap: 2px;
        }
        .bj-timeline-item strong {
            color: var(--bj-ink);
            font-size: 11px;
            letter-spacing: 0.04em;
            font-weight: 800;
        }
        .bj-rules {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        .bj-rule-pill {
            border-radius: 999px;
            padding: 6px 10px;
            font-size: 9px;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--bj-crest-line);
            color: var(--bj-ink-soft);
            font-weight: 700;
        }
        .bj-outcome-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid var(--bj-crest-line);
            font-size: 10px;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--bj-ink-soft);
            font-weight: 700;
        }
        .bj-outcome-pill.is-win,
        .bj-outcome-pill.is-blackjack {
            color: var(--bj-success);
            background: rgba(143, 227, 179, 0.14);
            border-color: rgba(143, 227, 179, 0.32);
        }
        .bj-outcome-pill.is-loss {
            color: var(--bj-danger);
            background: rgba(255, 138, 120, 0.14);
            border-color: rgba(255, 138, 120, 0.32);
        }
        .bj-outcome-pill.is-push {
            color: var(--bj-warning);
            background: rgba(241, 191, 105, 0.14);
            border-color: rgba(241, 191, 105, 0.32);
        }

        /* === Theme: salon — visually distinct from classic, not just label-swapped === */
        [data-bj-theme="salon"] {
            --bj-felt-pattern: rgba(229, 184, 122, 0.05);
        }
        [data-bj-theme="salon"] .bj-felt::before {
            background:
                radial-gradient(circle at 50% 24%, rgba(229, 184, 122, 0.10), transparent 38%),
                repeating-linear-gradient(0deg, var(--bj-felt-pattern) 0 1px, transparent 1px 12px),
                repeating-linear-gradient(90deg, var(--bj-felt-pattern) 0 1px, transparent 1px 12px);
            opacity: 0.9;
        }
        [data-bj-theme="salon"] .bj-felt-ring {
            inset: 12px;
            box-shadow:
                inset 0 0 80px rgba(0, 0, 0, 0.46),
                inset 0 0 0 1px rgba(229, 184, 122, 0.18),
                inset 0 0 0 6px rgba(0, 0, 0, 0.22);
        }
        [data-bj-theme="salon"] .bj-crest-title {
            font-family: "Georgia", "Times New Roman", ui-serif, serif;
            font-style: normal;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            font-size: clamp(16px, 5.2vw, 22px);
        }
        [data-bj-theme="salon"] .bj-crest-tagline {
            letter-spacing: 0.42em;
        }

        /* === Wider screens (desktop adapts the mobile surface) === */
        @media (min-width: 720px) {
            .bj-app {
                max-width: 520px;
                margin: 12px auto;
                inset: 12px 0;
                border-radius: 28px;
                overflow: hidden;
                border: 1px solid var(--bj-crest-line);
                box-shadow: var(--bj-shadow-strong);
            }
            :root {
                --bj-card-w: clamp(84px, 9vw, 108px);
                --bj-card-overlap: 26px;
            }
            .bj-sheet {
                max-width: 520px;
                left: 50%;
                right: auto;
                transform: translate(-50%, 100%);
            }
            .bj-app[data-bj-sheet-open="true"] .bj-sheet {
                transform: translate(-50%, 0);
            }
            .bj-sheet-backdrop {
                background: rgba(0, 0, 0, 0.6);
            }
        }

        /* === Reduced motion: presentation-only animations short-circuit === */
        @media (prefers-reduced-motion: reduce) {
            .bj-card-deal,
            .bj-card-flip { animation: none !important; }
            .bj-card .bj-card-flip { transform: rotateY(180deg); }
            .bj-card.is-hidden .bj-card-flip { transform: rotateY(0deg); }
            .bj-banner {
                transition: opacity .01s linear;
                transform: translateY(-50%) scale(1);
            }
            .bj-banner.is-visible { transform: translateY(-50%) scale(1); }
            .bj-cta.is-loading::after,
            .bj-action.is-loading::after { animation: none; }
            .bj-cta,
            .bj-action,
            .bj-chip,
            .bj-info-btn,
            .bj-sheet,
            .bj-sheet-backdrop,
            .bj-crest { transition: none; }
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
<div class="bj-app"
     data-blackjack-surface
     data-testid="blackjack-surface"
     data-bj-theme="{{ $bjTheme }}"
     data-bj-round-active="false"
     data-bj-sheet-open="false">

    <header class="bj-hud" data-bj-balance-strip>
        <div class="bj-hud-balance">
            <span class="bj-hud-label">{{ trans('cms_public.games.common.balance') }}</span>
            <span class="bj-hud-value">
                <strong id="metaBalance">{{ $guestPreview ? '—' : \Brick\Math\BigDecimal::of((string)($payload['balanceExact'] ?? $bjBalance))->toScale(2, \Brick\Math\RoundingMode::UNNECESSARY) }}</strong>
                <small>{{ $bjCurrency }}</small>
            </span>
        </div>
        <div class="bj-hud-bet" title="{{ trans('cms_public.games.blackjack.selected_bet_title') }}">
            <span class="bj-bet-chip-label">{{ trans('cms_public.games.common.bet') }}</span>
            <span class="bj-bet-chip" id="selectedBetChip" data-tier="2">
                <strong id="selectedBetLabel">{{ (int) round($bjFirstBet) }}</strong>
            </span>
        </div>
        <div class="bj-hud-mode"><a style="color:inherit;font-size:11px" href="{{ route('cms.games.index') }}">← Global</a>
            <span class="bj-hud-label">{{ $guestPreview ? __('catalog.guest_preview') : (!empty($payload['isDemo']) ? 'Demo · virtual credits' : 'Real · local synthetic funds') }}</span>
            <span class="bj-hud-value"><strong>{{ $bjCurrency }}</strong></span>
        </div>
        <button type="button"
                class="bj-info-btn"
                data-bj-drawer-toggle
                aria-controls="bjDrawer"
                aria-label="{{ trans('cms_public.games.blackjack.round_details_aria') }}">i</button>
    </header>

    <main class="bj-felt" data-bj-stage>
        <div class="bj-felt-ring" aria-hidden="true"></div>

        <section class="bj-row bj-row--dealer">
            <div class="bj-seat-meta">
                <span class="bj-seat-name">{{ trans('cms_public.games.blackjack.dealer') }}</span>
                <span class="bj-seat-total" id="dealerTotal">—</span>
            </div>
            <div class="bj-cards" id="dealerCards" data-seat="dealer"></div>
        </section>

        <div class="bj-center">
            <div class="bj-crest" aria-hidden="false">
                <span class="bj-crest-rule-line">
                    <span class="bj-crest-title">{{ $bjGameLabel }}</span>
                </span>
                <span class="bj-crest-tagline">{{ $bjGameTagline }}</span>
            </div>
            <div class="bj-banner"
                 id="outcomeBanner"
                 data-bj-outcome-banner
                 aria-hidden="true"
                 aria-live="polite">
                <strong class="bj-banner-title" data-bj-banner-title></strong>
                <span class="bj-banner-amount" data-bj-banner-amount></span>
            </div>
        </div>

        <section class="bj-row bj-row--player">
            <div class="bj-cards" id="playerCards" data-seat="player"></div>
            <div class="bj-seat-meta">
                <span class="bj-seat-name">{{ trans('cms_public.games.blackjack.you') }}</span>
                <span class="bj-seat-total" id="playerTotal">—</span>
            </div>
        </section>
    </main>

    <nav class="bj-dock" data-bj-action-dock data-bj-dock-state="idle">
        <div class="bj-dock-row bj-dock-row--chips">
            <div class="bj-chip-rail" id="betChipRow"></div>
        </div>
        <div class="bj-dock-row bj-dock-row--start">
            <button type="button"
                    class="bj-cta bj-cta--primary"
                    id="startRoundBtn"
                    data-testid="blackjack-start">{{ trans('cms_public.games.blackjack.deal') }}</button>
        </div>
        <div class="bj-dock-row bj-dock-row--actions">
            <button type="button"
                    class="bj-action"
                    id="hitBtn"
                    data-action="hit"
                    data-testid="blackjack-hit">{{ trans('cms_public.games.blackjack.hit') }}</button>
            <button type="button"
                    class="bj-action"
                    id="standBtn"
                    data-action="stand"
                    data-testid="blackjack-stand">{{ trans('cms_public.games.blackjack.stand') }}</button>
            <button type="button"
                    class="bj-action bj-action--warning"
                    id="doubleBtn"
                    data-action="double"
                    data-testid="blackjack-double">{{ trans('cms_public.games.blackjack.double') }}</button>
        </div>
    </nav>

    <div class="bj-sheet-backdrop"
         data-bj-sheet-backdrop
         aria-hidden="true"
         hidden></div>

    <aside class="bj-sheet"
           id="bjDrawer"
           data-bj-drawer
           aria-hidden="true"
           hidden>
        <div class="bj-sheet-handle" aria-hidden="true"></div>
        <header class="bj-sheet-header">
            <h2 class="bj-sheet-title">{{ trans('cms_public.games.blackjack.round_details') }}</h2>
            <button type="button" class="bj-sheet-close" data-bj-sheet-close>{{ trans('cms_public.games.common.close') }}</button>
        </header>
        <div class="bj-sheet-body">
            <section class="bj-sheet-section"><h3>Round history</h3><div id="blackjackHistory" aria-live="polite"></div></section>
            <section class="bj-sheet-section">
                <h3>{{ trans('cms_public.games.common.status') }}</h3>
                <div class="bj-status">
                    <div><strong>{{ trans('cms_public.games.common.round') }}</strong><span id="roundLabel">—</span></div>
                    <div><strong>{{ trans('cms_public.games.blackjack.state') }}</strong><span id="phaseLabel">—</span></div>
                    <div><strong>{{ trans('cms_public.games.common.stake') }}</strong><span id="stakeLabel">—</span></div>
                    <div><strong>{{ trans('cms_public.games.common.payout') }}</strong><span id="payoutLabel">—</span></div>
                </div>
            </section>
            <section class="bj-sheet-section">
                <h3>{{ trans('cms_public.games.common.outcome') }}</h3>
                <span class="bj-outcome-pill" id="outcomePill">{{ trans('cms_public.games.blackjack.no_result_yet') }}</span>
            </section>
            <section class="bj-sheet-section">
                <h3>{{ trans('cms_public.games.blackjack.result_feed') }}</h3>
                <div class="bj-message" id="messageBox">{{ trans('cms_public.games.blackjack.choose_chip') }}</div>
            </section>
            <section class="bj-sheet-section">
                <h3>{{ trans('cms_public.games.blackjack.timeline') }}</h3>
                <div class="bj-timeline" id="timeline"></div>
            </section>
            <section class="bj-sheet-section">
                <h3>{{ trans('cms_public.games.common.rules') }}</h3>
                <div class="bj-rules">
                    <span class="bj-rule-pill">{{ trans('cms_public.games.blackjack.rule_payout', ['value' => number_format($bjPayoutMultiplier, 2)]) }}</span>
                    <span class="bj-rule-pill">{{ $bjDealerHitsSoft17 ? trans('cms_public.games.blackjack.dealer_hits_soft_17') : trans('cms_public.games.blackjack.dealer_stands_soft_17') }}</span>
                    <span class="bj-rule-pill">{{ trans('cms_public.games.blackjack.double_policy', ['policy' => str_replace('_', ' ', $bjDoublePolicy)]) }}</span>
                </div>
            </section>
        </div>
    </aside>
</div>

@if($guestPreview)
    </div>
@else
<script>
    // Server is authoritative. Every value rendered comes from the server response.
    // Animations and DOM toggles are presentation-only. Request payloads and response
    // contract are unchanged from the prior surface.
    const payload = @json($payload);
    const bjI18n = @json(trans('cms_public.games.blackjack'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    const commonI18n = @json(trans('cms_public.games.common'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const currency = String(payload.currency || '').trim();

    function textFrom(map, key, fallback, replacements = {}) {
        let value = Object.prototype.hasOwnProperty.call(map || {}, key) ? map[key] : fallback;
        if (typeof value !== 'string') value = fallback;
        Object.entries(replacements).forEach(([name, replacement]) => {
            value = value.replaceAll(`:${name}`, String(replacement));
        });
        return value;
    }

    const bjText = (key, fallback, replacements = {}) => textFrom(bjI18n, key, fallback, replacements);
    const commonText = (key, fallback, replacements = {}) => textFrom(commonI18n, key, fallback, replacements);
    const actionLabel = (action) => bjText(action, action);

    function numberOrFallback(value, fallback = 0) {
        if (value == null) {
            return fallback;
        }

        const numeric = Number(value);
        return Number.isFinite(numeric) ? numeric : fallback;
    }

    const state = {
        balance: numberOrFallback(payload.balance, 0),
        balanceExact: String(payload.balanceExact ?? payload.balance ?? '0'),
        history: payload.history || [],
        selectedBet: Number((payload.betValues || [10])[0] || 10),
        round: payload.currentRound || null,
        pending: false,
        pendingAction: null,
        animateNextDeal: false,
        walletMessageSequence: 0,
    };

    const $ = (id) => document.getElementById(id);
    const app = document.querySelector('.bj-app');
    const metaBalance = $('metaBalance');
    const selectedBetLabel = $('selectedBetLabel');
    const selectedBetChip = $('selectedBetChip');
    const roundLabel = $('roundLabel');
    const stakeLabel = $('stakeLabel');
    const payoutLabel = $('payoutLabel');
    const phaseLabel = $('phaseLabel');
    const outcomePill = $('outcomePill');
    const dealerCards = $('dealerCards');
    const playerCards = $('playerCards');
    const dealerTotal = $('dealerTotal');
    const playerTotal = $('playerTotal');
    const messageBox = $('messageBox');
    const timeline = $('timeline');
    const startRoundBtn = $('startRoundBtn');
    const betChipRow = $('betChipRow');
    const actionButtons = {
        hit: $('hitBtn'),
        stand: $('standBtn'),
        double: $('doubleBtn'),
    };
    const dock = document.querySelector('[data-bj-action-dock]');
    const sheet = document.querySelector('[data-bj-drawer]');
    const sheetBackdrop = document.querySelector('[data-bj-sheet-backdrop]');
    const sheetCloseBtn = document.querySelector('[data-bj-sheet-close]');
    const drawerToggle = document.querySelector('[data-bj-drawer-toggle]');
    const outcomeBanner = document.querySelector('[data-bj-outcome-banner]');
    const bannerTitle = outcomeBanner ? outcomeBanner.querySelector('[data-bj-banner-title]') : null;
    const bannerAmount = outcomeBanner ? outcomeBanner.querySelector('[data-bj-banner-amount]') : null;

    function requestKey() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return window.crypto.randomUUID();
        }
        return `${Date.now()}-${Math.random().toString(16).slice(2)}`;
    }

    function suitMark(suit) {
        return ({
            hearts: '\u2665',
            diamonds: '\u2666',
            clubs: '\u2663',
            spades: '\u2660',
            hidden: '',
        })[suit] || suit || '';
    }

    function fmt(n) {
        return Number(n || 0).toFixed(2);
    }

    function fmtCur(n) {
        return `${fmt(n)} ${currency}`.trim();
    }

    function setBalance(nextBalance, exact = null) {
        const numeric = Number(nextBalance);
        if (!Number.isFinite(numeric)) {
            return false;
        }

        state.balance = numeric;
        state.balanceExact = String(exact ?? nextBalance);
        payload.balance = numeric;
        return true;
    }

    function postParentWalletUpdate(data, requestKey = null) {
        if (payload.isDemo !== false || !window.parent || window.parent === window) {
            return;
        }

        const balance = numberOrFallback(data && data.balance, NaN);
        if (!Number.isFinite(balance)) {
            return;
        }

        state.walletMessageSequence += 1;
        window.parent.postMessage({
            type: 'internal-game:wallet-updated',
            source: 'blackjack',
            roundId: data && data.roundId ? data.roundId : (state.round && state.round.roundId ? state.round.roundId : null),
            requestRef: data && data.requestKey ? data.requestKey : requestKey,
            balance,
            currency: data && data.currency ? data.currency : (payload.currency || ''),
            timestamp: Date.now(),
            sequence: state.walletMessageSequence,
        }, window.location.origin);
    }

    function applyCanonicalBalance(data, requestKey = null) {
        if (!data || data.balance == null) {
            return false;
        }

        const applied = setBalance(data.balance, data.balanceExact);
        if (applied) {
            postParentWalletUpdate(data, requestKey);
        }

        return applied;
    }

    // Casino chip color tier from amount magnitude. Pure presentation.
    function chipTier(value) {
        const v = Number(value || 0);
        if (v >= 5000) return 6;
        if (v >= 1000) return 5;
        if (v >= 250) return 4;
        if (v >= 50) return 3;
        if (v >= 10) return 2;
        return 1;
    }

    function createCard(card, index, total, shouldAnimate) {
        const el = document.createElement('div');
        el.className = `bj-card ${card.hidden ? 'is-hidden' : ''} ${shouldAnimate ? '' : 'is-static'}`.trim();
        const rotate = total > 1 ? (index - ((total - 1) / 2)) * 4.5 : 0;
        const shift = total > 1 ? (index - ((total - 1) / 2)) * 2.4 : 0;
        el.style.setProperty('--bj-fan-rotate', `${rotate}deg`);
        el.style.setProperty('--bj-fan-shift', `${shift}px`);
        el.style.setProperty('--bj-deal-delay', `${index * 130}ms`);
        el.style.setProperty('--bj-flip-delay', `${360 + (index * 130)}ms`);
        const rank = String(card.rank || '?');
        const suitGlyph = card.hidden ? '' : suitMark(card.suit);
        const redClass = card.color === 'red' ? 'is-red' : '';
        el.innerHTML = `
            <div class="bj-card-deal">
                <div class="bj-card-flip">
                    <div class="bj-card-face bj-card-back">
                        <div class="bj-card-back-mark">BJ</div>
                    </div>
                    <div class="bj-card-face bj-card-front ${redClass}">
                        <div class="bj-card-rank">${rank}</div>
                        <div class="bj-card-suit">${suitGlyph}</div>
                        <div class="bj-card-rank bj-card-rank--bottom">${rank}</div>
                    </div>
                </div>
            </div>
        `;
        return el;
    }

    function cardSignature(cards) {
        return JSON.stringify((cards || []).map((c) => ({
            rank: c.rank || null,
            suit: c.suit || null,
            hidden: Boolean(c.hidden),
            color: c.color || null,
        })));
    }

    function setMessage(text, kind = '') {
        messageBox.className = `bj-message${kind ? ` is-${kind}` : ''}`;
        messageBox.textContent = text;
    }

    function renderChips() {
        betChipRow.innerHTML = '';
        const lockChips = state.pending || (state.round && state.round.phase === 'player_turn');
        (payload.betValues || []).forEach((value) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = `bj-chip${Number(value) === state.selectedBet ? ' is-active' : ''}`;
            btn.dataset.tier = String(chipTier(value));
            btn.disabled = lockChips;
            btn.innerHTML = `<strong>${Math.round(Number(value))}</strong>`;
            btn.setAttribute('aria-label', bjText('bet_aria', `Bet ${value} ${currency}`, { amount: value, currency }));
            btn.addEventListener('click', () => {
                if (btn.disabled) return;
                state.selectedBet = Number(value);
                render();
            });
            betChipRow.appendChild(btn);
        });
    }

    function renderCards(target, cards, shouldAnimate) {
        const sig = cardSignature(cards);
        if (target.dataset.signature === sig) return;
        target.dataset.signature = sig;
        target.innerHTML = '';
        (cards || []).forEach((c, i) => target.appendChild(createCard(c, i, (cards || []).length, shouldAnimate)));
    }

    function renderTimeline(items) {
        timeline.innerHTML = '';
        if (!Array.isArray(items) || items.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'bj-timeline-item';
            empty.innerHTML = `<strong>${bjText('no_actions_yet', 'No actions yet')}</strong><span>${bjText('no_actions_body', 'Round creation and player decisions will appear here.')}</span>`;
            timeline.appendChild(empty);
            return;
        }
        items.forEach((it) => {
            const node = document.createElement('div');
            node.className = 'bj-timeline-item';
            node.innerHTML = `
                <strong>#${it.sequence || '-'} ${String(it.type || '-').replaceAll('_', ' ')}</strong>
                <span>${bjText('card', 'Card')}: ${it.card || '-'} · ${commonText('outcome', 'Outcome')}: ${it.outcome || '-'}</span>
                <span>${bjText('player', 'Player')} ${it.player_total ?? '-'} / ${bjText('dealer', 'Dealer')} ${it.dealer_total ?? '-'}</span>
            `;
            timeline.appendChild(node);
        });
    }

    function dockStateFor(round) {
        if (round && round.phase === 'player_turn') return 'player-turn';
        if (round && round.phase === 'resolved') return 'resolved';
        return 'idle';
    }

    function bannerTitleFor(outcome) {
        switch (String(outcome || '').toLowerCase()) {
            case 'win': return commonText('win', 'Win');
            case 'blackjack': return 'Blackjack';
            case 'push': return bjText('push', 'Push');
            case 'loss': return bjText('lost', 'Lost');
            default: return String(outcome || '').toUpperCase() || '\u2014';
        }
    }

    function showBanner(round) {
        if (!outcomeBanner) return;
        if (!round || round.phase !== 'resolved') {
            outcomeBanner.classList.remove('is-visible');
            outcomeBanner.setAttribute('aria-hidden', 'true');
            outcomeBanner.removeAttribute('data-outcome');
            return;
        }
        const outcome = String(round.outcome || '').toLowerCase();
        outcomeBanner.dataset.outcome = outcome;
        if (bannerTitle) bannerTitle.textContent = bannerTitleFor(outcome);
        if (bannerAmount) {
            const stake = Number(round.totalStake || 0);
            const payoutTotal = Number(round.payoutTotal || 0);
            if (outcome === 'win' || outcome === 'blackjack') {
                bannerAmount.textContent = `+ ${fmtCur(payoutTotal - stake)}`;
            } else if (outcome === 'push') {
                bannerAmount.textContent = bjText('stake_returned', 'Stake returned');
            } else if (outcome === 'loss') {
                bannerAmount.textContent = `- ${fmtCur(stake)}`;
            } else {
                bannerAmount.textContent = '';
            }
        }
        outcomeBanner.setAttribute('aria-hidden', 'false');
        outcomeBanner.classList.add('is-visible');
    }

    function setLoading(target, loading) {
        if (!target) return;
        target.classList[loading ? 'add' : 'remove']('is-loading');
    }

    function updateSelectedBetVisual() {
        selectedBetLabel.textContent = String(Math.round(state.selectedBet));
        selectedBetChip.dataset.tier = String(chipTier(state.selectedBet));
        selectedBetChip.title = `${fmtCur(state.selectedBet)}`;
    }

    function setSheetOpen(open) {
        if (open) {
            sheet.removeAttribute('hidden');
            sheet.setAttribute('aria-hidden', 'false');
            sheetBackdrop.removeAttribute('hidden');
            sheetBackdrop.setAttribute('aria-hidden', 'false');
            app.setAttribute('data-bj-sheet-open', 'true');
            drawerToggle.setAttribute('aria-expanded', 'true');
        } else {
            app.setAttribute('data-bj-sheet-open', 'false');
            drawerToggle.setAttribute('aria-expanded', 'false');
            // Wait for slide-out before hiding from a11y tree
            window.setTimeout(() => {
                if (app.getAttribute('data-bj-sheet-open') === 'false') {
                    sheet.setAttribute('hidden', '');
                    sheet.setAttribute('aria-hidden', 'true');
                    sheetBackdrop.setAttribute('hidden', '');
                    sheetBackdrop.setAttribute('aria-hidden', 'true');
                }
            }, 300);
        }
    }

    function renderHistory() {
        const current = state.round;
        if(current && ['resolved','refunded'].includes(current.phase)) {
            state.history = [current,...state.history.filter(r=>r.roundId!==current.roundId)].slice(0,20);
        }
        const list = document.getElementById('blackjackHistory');list.replaceChildren();
        for(const round of state.history){const row=document.createElement('div');row.dataset.blackjackHistoryRound=round.roundId;row.style.cssText='padding:8px 0;overflow-wrap:anywhere;border-bottom:1px solid rgba(255,255,255,.15)';row.textContent=`${round.roundId} · ${round.status} · ${fmtCur(round.totalStake)} → ${fmtCur(round.payoutTotal)}${round.refundAmount ? ' · refund '+fmtCur(round.refundAmount) : ''}`;list.appendChild(row);}
    }

    function render() {
        renderChips();
        const exact = /^\d+(\.\d+)?$/.test(state.balanceExact) ? state.balanceExact.split('.') : null;
        metaBalance.textContent = exact ? exact[0]+'.'+((exact[1] || '')+'00').slice(0,2) : fmt(state.balance);
        renderHistory();
        updateSelectedBetVisual();

        const round = state.round;
        const hasActiveRound = !!round;
        const canStart = !state.pending && (!round || round.phase !== 'player_turn');
        const allowedActions = hasActiveRound ? (round.allowedActions || []) : [];

        startRoundBtn.disabled = !canStart;
        startRoundBtn.textContent = round && round.phase === 'resolved'
            ? bjText('play_again', 'Play again')
            : bjText('deal', 'Deal');
        Object.entries(actionButtons).forEach(([action, btn]) => {
            btn.disabled = state.pending || !allowedActions.includes(action);
        });

        if (dock) dock.dataset.bjDockState = dockStateFor(round);
        app.setAttribute('data-bj-round-active', round ? 'true' : 'false');

        if (!round) {
            roundLabel.textContent = '—';
            stakeLabel.textContent = '—';
            payoutLabel.textContent = '—';
            phaseLabel.textContent = commonText('waiting', 'Waiting');
            outcomePill.textContent = bjText('no_result_yet', 'No result yet');
            outcomePill.className = 'bj-outcome-pill';
            dealerTotal.textContent = '—';
            playerTotal.textContent = '—';
            renderCards(dealerCards, [], false);
            renderCards(playerCards, [], false);
            renderTimeline([]);
            showBanner(null);
            state.animateNextDeal = false;
            return;
        }

        roundLabel.textContent = `${round.roundId} · v${round.roundVersion}`;
        stakeLabel.textContent = fmtCur(round.totalStake || 0);
        payoutLabel.textContent = fmtCur(round.payoutTotal || 0);
        phaseLabel.textContent = String(round.phase || 'player_turn').replaceAll('_', ' ');
        outcomePill.textContent = round.outcome ? String(round.outcome).replaceAll('_', ' ') : bjText('in_progress', 'In progress');
        outcomePill.className = `bj-outcome-pill${round.outcome ? ` is-${String(round.outcome).toLowerCase()}` : ''}`;
        const dealerSummary = round.dealer && round.dealer.summary;
        dealerTotal.textContent = dealerSummary
            ? (dealerSummary.bestTotal && round.phase === 'resolved'
                ? `${dealerSummary.bestTotal}`
                : `${dealerSummary.visibleTotal || 0}`)
            : '—';
        const playerSummary = round.player && round.player.summary;
        playerTotal.textContent = playerSummary ? `${playerSummary.bestTotal || 0}` : '—';
        renderCards(dealerCards, (round.dealer && round.dealer.cards) || [], state.animateNextDeal);
        renderCards(playerCards, (round.player && round.player.cards) || [], state.animateNextDeal);
        renderTimeline(round.actionTimeline || []);
        showBanner(round);
        state.animateNextDeal = false;

        if (!state.pending) {
            setMessage(round.resultSummary || bjText('round_synced', 'Round synced from server.'), round.phase === 'resolved' ? 'success' : '');
        }
    }

    async function postJson(url, body) {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify(body),
            credentials: 'same-origin',
        });
        return await response.json();
    }

    function createBlackjackError(response, fallback) {
        const error = new Error(
            response && typeof response.message === 'string' && response.message.trim() !== ''
                ? response.message
                : fallback
        );
        error.code = response && typeof response.code === 'string' ? response.code : '';
        error.status = response && typeof response.status !== 'undefined' ? Number(response.status) : 0;
        return error;
    }

    function isBlackjackInsufficientFundsError(error) {
        if (!error) return false;
        const code = String(error.code || '').trim().toUpperCase();
        if (code === 'BLACKJACK_INSUFFICIENT_FUNDS') return true;
        return String(error.message || '').trim().toLowerCase().includes('insufficient funds');
    }

    function openBlackjackDepositModal() {
        const host = window.parent && window.parent !== window ? window.parent : window;
        try {
            if (typeof host.openCmsDepositModal === 'function') {
                host.openCmsDepositModal();
                return true;
            }
        } catch (e) { /* ignore */ }
        return false;
    }

    async function startRound() {
        if (state.pending) return;
        state.pending = true;
        state.pendingAction = 'start';
        setMessage(bjText('opening_round', 'Opening round and requesting initial deal...'));
        setLoading(startRoundBtn, true);
        render();

        try {
            const response = await postJson(payload.startRoute, {
                sessionToken: payload.sessionToken,
                betAmount: state.selectedBet,
                requestKey: requestKey(),
            });

            if (response.status !== 200) {
                throw createBlackjackError(response, bjText('round_start_failed', 'Round start failed.'));
            }

            state.round = response.data;
            applyCanonicalBalance(response.data, response.data && response.data.requestKey ? response.data.requestKey : null);
            state.animateNextDeal = true;
            setMessage(response.data.resultSummary || bjText('round_started', 'Round started.'), response.data.phase === 'resolved' ? 'success' : '');
        } catch (error) {
            if (isBlackjackInsufficientFundsError(error)) {
                setMessage(commonText('deposit_opening', 'Insufficient balance. Opening the deposit window.'), 'error');
                window.setTimeout(() => { openBlackjackDepositModal(); }, 120);
            } else {
                setMessage(error.message || bjText('round_start_failed', 'Round start failed.'), 'error');
            }
        } finally {
            state.pending = false;
            state.pendingAction = null;
            setLoading(startRoundBtn, false);
            render();
        }
    }

    async function runAction(action) {
        if (state.pending || !state.round) return;
        state.pending = true;
        state.pendingAction = action;
        setMessage(bjText('sending_action', `Sending ${action} to server...`, { action: actionLabel(action) }));
        setLoading(actionButtons[action], true);
        render();

        try {
            const response = await postJson(payload.actionRoute, {
                sessionToken: payload.sessionToken,
                roundId: state.round.roundId,
                action: action,
                requestKey: requestKey(),
                roundVersion: state.round.roundVersion,
            });

            if (response.status !== 200) {
                throw createBlackjackError(response, bjText('action_failed', 'Blackjack action failed.'));
            }

            state.round = response.data;
            applyCanonicalBalance(response.data, response.data && response.data.requestKey ? response.data.requestKey : null);
            state.animateNextDeal = true;
            setMessage(response.data.resultSummary || bjText('action_accepted', `${action} accepted.`, { action: actionLabel(action) }), response.data.phase === 'resolved' ? 'success' : '');
        } catch (error) {
            if (isBlackjackInsufficientFundsError(error)) {
                setMessage(commonText('deposit_opening', 'Insufficient balance. Opening the deposit window.'), 'error');
                window.setTimeout(() => { openBlackjackDepositModal(); }, 120);
            } else {
                setMessage(error.message || bjText('action_failed', 'Blackjack action failed.'), 'error');
            }
        } finally {
            state.pending = false;
            state.pendingAction = null;
            setLoading(actionButtons[action], false);
            render();
        }
    }

    startRoundBtn.addEventListener('click', startRound);
    Object.entries(actionButtons).forEach(([action, btn]) => {
        btn.addEventListener('click', () => runAction(action));
    });

    if (drawerToggle) drawerToggle.addEventListener('click', () => setSheetOpen(true));
    if (sheetCloseBtn) sheetCloseBtn.addEventListener('click', () => setSheetOpen(false));
    if (sheetBackdrop) sheetBackdrop.addEventListener('click', () => setSheetOpen(false));
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && app.getAttribute('data-bj-sheet-open') === 'true') {
            setSheetOpen(false);
        }
    });

    render();
</script>
@endif
</body>
</html>
