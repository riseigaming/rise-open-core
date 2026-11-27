<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#0a0306">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $payload['gameName'] }}</title>
    <style>
        /* ============================================================
           Big Win — HTML5 Slot Front Client v2
           Mobile-first target: 390 × 844 portrait
           Manifest v2 §8 asset paths only
           Asset URLs are injected at boot via CSS vars (--bw-asset-*),
           so the file works both via file:// preview and CMS absolute.
           ============================================================ */

        :root {
            /* Palette */
            --bw-ink:        #fff6e0;
            --bw-ink-dim:    rgba(255, 246, 224, 0.70);
            --bw-ink-faint:  rgba(255, 246, 224, 0.42);

            --bw-gold-1:  #ffe48a;
            --bw-gold-2:  #ffc24a;
            --bw-gold-3:  #f6a609;
            --bw-gold-4:  #b6700a;
            --bw-gold-hi: #fff6d2;

            --bw-red-1:    #ff5a5e;
            --bw-red-2:    #d11e2a;
            --bw-red-3:    #7a0f15;
            --bw-red-deep: #2a080c;
            --bw-wood-1:   #4a0a10;
            --bw-wood-2:   #2a060a;
            --bw-wood-3:   #14030a;

            --bw-cyan:  #5ee0ff;
            --bw-green: #36e3a1;

            /* Asset URLs — set by JS at boot via the asset-base resolver. */
            --bw-asset-bg: none;
            --bw-asset-cabinet: none;
            --bw-asset-glow: none;
            --bw-asset-coin: none;
            --bw-asset-spark: none;
            --bw-asset-mascot: none;

            --bw-r-sm: 10px;
            --bw-r-md: 14px;
            --bw-r-lg: 20px;
            --bw-r-xl: 26px;

            --bw-ease:      cubic-bezier(0.2, 0.8, 0.2, 1);
            --bw-ease-snap: cubic-bezier(0.34, 1.56, 0.64, 1);

            --bw-safe-top: max(env(safe-area-inset-top), 0px);
            --bw-safe-bot: max(env(safe-area-inset-bottom), 0px);
        }

        *, *::before, *::after { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }

        html, body {
            margin: 0; padding: 0;
            overflow-x: hidden;
            background: #07030a;
            color: var(--bw-ink);
        }
        body {
            min-height: 100vh;
            min-height: 100dvh;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            font-size: 14px;
            line-height: 1.4;
            -webkit-font-smoothing: antialiased;
            text-rendering: optimizeLegibility;
            position: relative;
        }
        button { font-family: inherit; }
        .bw-sr-only {
            position: absolute !important;
            width: 1px; height: 1px;
            padding: 0; margin: -1px;
            overflow: hidden; clip: rect(0,0,0,0);
            white-space: nowrap; border: 0;
        }

        /* ---------- Cinematic backdrop ---------- */
        .bw-scene {
            position: fixed;
            inset: 0;
            z-index: -3;
            background-color: #1a040a;
            background-image:
                radial-gradient(ellipse 60% 40% at 50% 0%, rgba(255, 100, 60, 0.30), transparent 65%),
                radial-gradient(ellipse 70% 50% at 50% 100%, rgba(246, 166, 9, 0.15), transparent 70%),
                var(--bw-asset-bg);
            background-size: auto, auto, cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
        }
        .bw-vignette {
            position: fixed; inset: 0; z-index: -2; pointer-events: none;
            background:
                radial-gradient(ellipse 75% 65% at 50% 30%, transparent 0%, transparent 50%, rgba(0, 0, 0, 0.65) 100%),
                linear-gradient(180deg, rgba(0, 0, 0, 0.30), transparent 18%, transparent 80%, rgba(0, 0, 0, 0.5));
        }
        .bw-ambient-orb {
            position: fixed; z-index: -1; pointer-events: none;
            border-radius: 50%;
            filter: blur(70px);
            opacity: 0.45;
            will-change: transform;
        }
        .bw-ambient-orb--a {
            width: 420px; height: 420px;
            top: -120px; left: -120px;
            background: radial-gradient(circle, rgba(255, 90, 94, 0.75), transparent 70%);
            animation: bwDriftA 24s ease-in-out infinite;
        }
        .bw-ambient-orb--b {
            width: 460px; height: 460px;
            bottom: -120px; right: -130px;
            background: radial-gradient(circle, rgba(246, 166, 9, 0.55), transparent 70%);
            animation: bwDriftB 28s ease-in-out infinite;
        }
        @keyframes bwDriftA {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50%      { transform: translate(36px, 28px) scale(1.08); }
        }
        @keyframes bwDriftB {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50%      { transform: translate(-32px, -36px) scale(1.10); }
        }
        /* dotted bokeh that picks up the red background */
        .bw-bokeh {
            position: fixed; inset: 0; z-index: -1; pointer-events: none;
            background-image:
                radial-gradient(rgba(255, 200, 120, 0.25) 1px, transparent 1.6px),
                radial-gradient(rgba(255, 120,  90, 0.18) 1px, transparent 1.6px);
            background-size: 26px 26px, 38px 38px;
            background-position: 0 0, 13px 19px;
            mask-image: radial-gradient(ellipse 80% 60% at 50% 30%, #000 0%, transparent 78%);
            -webkit-mask-image: radial-gradient(ellipse 80% 60% at 50% 30%, #000 0%, transparent 78%);
            opacity: 0.55;
        }

        /* ---------- App shell ---------- */
        .bw-app {
            position: relative;
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
            padding: var(--bw-safe-top) 0 var(--bw-safe-bot);
            max-width: 1280px;
            margin: 0 auto;
            overflow-x: clip;
        }

        /* ---------- Top HUD (compact glass strip) ---------- */
        .bw-hud {
            position: sticky;
            top: var(--bw-safe-top);
            z-index: 30;
            display: grid;
            grid-template-columns: auto 1fr auto;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            margin: 6px 8px 0;
            border-radius: var(--bw-r-md);
            background: linear-gradient(180deg, rgba(40, 8, 12, 0.78), rgba(14, 4, 8, 0.62));
            -webkit-backdrop-filter: blur(18px) saturate(140%);
                    backdrop-filter: blur(18px) saturate(140%);
            border: 1px solid rgba(255, 216, 107, 0.22);
            box-shadow: 0 10px 24px -12px rgba(0, 0, 0, 0.7);
        }
        .bw-identity { display: flex; align-items: center; gap: 8px; min-width: 0; }
        .bw-logo {
            position: relative;
            width: 34px; height: 34px;
            border-radius: 10px;
            background:
                radial-gradient(circle at 30% 28%, var(--bw-gold-hi) 0%, var(--bw-gold-1) 35%, var(--bw-gold-3) 75%, var(--bw-gold-4) 100%);
            box-shadow:
                0 6px 14px -4px rgba(246, 166, 9, 0.55),
                inset 0 0 0 1.5px rgba(255, 255, 255, 0.55),
                inset 0 -3px 6px rgba(110, 60, 0, 0.4);
            display: flex; align-items: center; justify-content: center;
            font-weight: 900;
            color: #3a1c00;
            font-size: 12px;
            letter-spacing: -0.04em;
            flex-shrink: 0;
        }
        .bw-mode-chip {
            justify-self: center;
            display: inline-flex; align-items: center; gap: 6px;
            padding: 4px 10px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            border-radius: 999px;
            color: var(--bw-cyan);
            background: rgba(94, 224, 255, 0.10);
            border: 1px solid rgba(94, 224, 255, 0.32);
        }
        .bw-mode-chip[data-mode="real"] {
            color: var(--bw-green);
            background: rgba(54, 227, 161, 0.10);
            border-color: rgba(54, 227, 161, 0.32);
        }
        .bw-mode-dot {
            width: 6px; height: 6px; border-radius: 50%;
            background: currentColor;
            box-shadow: 0 0 8px currentColor;
        }
        .bw-balance {
            justify-self: end;
            display: flex; flex-direction: column; align-items: flex-end;
            padding: 4px 10px;
            border-radius: 10px;
            background: linear-gradient(180deg, rgba(255, 216, 107, 0.14), rgba(255, 216, 107, 0.02));
            border: 1px solid rgba(255, 216, 107, 0.26);
            min-width: 0;
            max-width: 44vw;
        }
        .bw-balance-label {
            font-size: 8.5px; letter-spacing: 0.20em;
            text-transform: uppercase; color: var(--bw-ink-faint);
        }
        .bw-balance-value {
            margin-top: 1px;
            font-weight: 800; font-size: 14px; line-height: 1.15;
            color: var(--bw-gold-1);
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
            overflow: hidden; text-overflow: ellipsis;
            max-width: 100%;
        }

        /* ---------- Main layout ---------- */
        .bw-layout {
            flex: 1;
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
            padding: 10px 8px 16px;
        }

        /* ============================================================
           The Game Machine — the dominant visual element.
           Built as one cabinet, controls embedded inside it.
           ============================================================ */
        .bw-machine {
            position: relative;
            width: 100%;
            max-width: 480px;
            margin: 0 auto;
            align-self: start;
            border-radius: 26px;
            padding: 6px;
            background:
                /* outer chrome rim — gold gradient */
                linear-gradient(180deg, var(--bw-gold-1) 0%, var(--bw-gold-3) 28%, var(--bw-gold-4) 60%, var(--bw-gold-3) 88%, var(--bw-gold-1) 100%);
            box-shadow:
                /* big depth shadow */
                0 32px 60px -16px rgba(0, 0, 0, 0.85),
                /* outer red glow */
                0 0 80px -16px rgba(209, 30, 42, 0.55),
                /* inner specular */
                inset 0 1px 0 rgba(255, 255, 255, 0.75),
                inset 0 -2px 4px rgba(0, 0, 0, 0.35);
            isolation: isolate;
        }
        /* Inner cabinet face (dark wood) */
        .bw-machine-face {
            position: relative;
            border-radius: 18px;
            background:
                radial-gradient(ellipse 110% 60% at 50% 0%, rgba(255, 180, 100, 0.18), transparent 60%),
                radial-gradient(ellipse 80% 50% at 50% 100%, rgba(122, 15, 21, 0.45), transparent 70%),
                linear-gradient(180deg, var(--bw-wood-1) 0%, var(--bw-wood-2) 50%, var(--bw-wood-3) 100%);
            padding: 10px 10px 8px;
            box-shadow:
                inset 0 2px 6px rgba(0, 0, 0, 0.6),
                inset 0 0 0 1px rgba(255, 216, 107, 0.28);
        }

        /* Top marquee — "BIG WIN" wordmark + multiplier ticker */
        .bw-marquee-bar {
            position: relative;
            display: grid;
            grid-template-columns: auto 1fr auto;
            align-items: center;
            gap: 8px;
            padding: 6px 10px;
            margin-bottom: 8px;
            border-radius: 12px;
            background:
                linear-gradient(180deg, rgba(255, 216, 107, 0.22), rgba(255, 216, 107, 0.04) 50%, rgba(122, 15, 21, 0.35));
            border: 1px solid rgba(255, 216, 107, 0.42);
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.18),
                inset 0 -1px 2px rgba(0, 0, 0, 0.45);
        }
        .bw-marquee-light {
            width: 8px; height: 8px;
            border-radius: 50%;
            background: radial-gradient(circle at 30% 30%, #fff6d2, var(--bw-gold-1) 45%, var(--bw-gold-3) 100%);
            box-shadow: 0 0 8px var(--bw-gold-2), 0 0 16px rgba(255, 216, 107, 0.5);
            animation: bwBlink 1.6s ease-in-out infinite;
        }
        .bw-marquee-light--r {
            background: radial-gradient(circle at 30% 30%, #ffe5e5, var(--bw-red-1) 45%, var(--bw-red-2) 100%);
            box-shadow: 0 0 8px var(--bw-red-1), 0 0 16px rgba(255, 90, 94, 0.5);
            animation-delay: 0.8s;
        }
        @keyframes bwBlink {
            0%, 100% { opacity: 1; }
            50%      { opacity: 0.35; }
        }
        .bw-wordmark {
            position: relative;
            text-align: center;
            font-weight: 900;
            font-size: clamp(20px, 5.6vw, 26px);
            letter-spacing: 0.08em;
            line-height: 1;
            background: linear-gradient(180deg, #fff6d2 0%, var(--bw-gold-1) 35%, var(--bw-gold-3) 75%, var(--bw-gold-4) 100%);
            -webkit-background-clip: text;
                    background-clip: text;
            color: transparent;
            text-shadow: 0 0 1px rgba(255, 216, 107, 0.2);
            filter: drop-shadow(0 2px 0 rgba(0, 0, 0, 0.4));
            white-space: nowrap;
            user-select: none;
        }
        .bw-wordmark::after {
            content: 'BIG WIN';
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.7), transparent 50%);
            -webkit-background-clip: text;
                    background-clip: text;
            color: transparent;
            pointer-events: none;
        }
        .bw-marquee-multipliers {
            position: absolute;
            left: 0; right: 0;
            top: 100%;
            margin-top: 4px;
            display: flex;
            justify-content: center;
            gap: 8px;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.16em;
            color: var(--bw-gold-1);
            text-transform: uppercase;
            text-shadow: 0 0 8px rgba(255, 216, 107, 0.45);
            pointer-events: none;
        }
        .bw-marquee-multipliers span { opacity: 0.55; }
        .bw-marquee-multipliers span.is-hot {
            color: var(--bw-gold-hi);
            opacity: 1;
            text-shadow: 0 0 10px rgba(255, 216, 107, 0.85);
        }

        /* Reel window — the canvas + chrome rails + payline */
        .bw-reel-frame {
            position: relative;
            margin-top: 18px;        /* room for the multiplier ticker overlapping marquee bar */
            border-radius: 14px;
            padding: 4px;
            background: linear-gradient(180deg, var(--bw-gold-3), var(--bw-gold-4) 50%, var(--bw-gold-3));
            box-shadow:
                0 0 0 1px rgba(255, 216, 107, 0.4) inset,
                0 4px 14px -2px rgba(0, 0, 0, 0.7);
        }
        .bw-reel-frame::before,
        .bw-reel-frame::after {
            /* side coin-slot accents */
            content: '';
            position: absolute;
            top: 14%; bottom: 14%;
            width: 6px;
            background: linear-gradient(180deg, var(--bw-gold-4), var(--bw-gold-3) 50%, var(--bw-gold-4));
            border-radius: 4px;
            box-shadow: inset 0 1px 2px rgba(255, 255, 255, 0.5), inset 0 -1px 2px rgba(0, 0, 0, 0.4);
        }
        .bw-reel-frame::before { left: -10px; }
        .bw-reel-frame::after  { right: -10px; }

        .bw-canvas-wrap {
            position: relative;
            border-radius: 10px;
            overflow: hidden;
            background:
                radial-gradient(ellipse at 50% 25%, rgba(122, 15, 21, 0.55), transparent 65%),
                linear-gradient(180deg, #16040a 0%, #2a080c 100%);
            box-shadow:
                inset 0 2px 12px rgba(0, 0, 0, 0.85),
                inset 0 -2px 12px rgba(0, 0, 0, 0.6);
            aspect-ratio: 4 / 3;
            isolation: isolate;
        }
        #slotCanvas {
            display: block;
            width: 100%; height: 100%;
            position: relative;
            z-index: 1;
        }
        .bw-canvas-glare {
            position: absolute; inset: 0;
            pointer-events: none; z-index: 2;
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.10), transparent 22%),
                linear-gradient(0deg,   rgba(0, 0, 0, 0.32),   transparent 26%);
            border-radius: inherit;
        }
        /* Horizontal payline indicator */
        .bw-payline {
            position: absolute;
            left: 0; right: 0;
            top: 50%;
            height: 2px;
            transform: translateY(-50%);
            background: linear-gradient(90deg, transparent, rgba(255, 216, 107, 0.7), transparent);
            box-shadow: 0 0 8px rgba(255, 216, 107, 0.6);
            pointer-events: none;
            z-index: 3;
            opacity: 0.55;
        }

        /* Cabinet bezel overlay — gold frame asset */
        .bw-cabinet-overlay {
            position: absolute; inset: -2px;
            z-index: 6; pointer-events: none;
            background-image: var(--bw-asset-cabinet);
            background-size: 100% 100%;
            background-position: center;
            background-repeat: no-repeat;
            opacity: 0.85;
            mix-blend-mode: screen;
        }

        /* Win-time glow halo */
        .bw-glow-halo {
            position: absolute; inset: -10%;
            z-index: 2; pointer-events: none;
            background-image: var(--bw-asset-glow);
            background-size: 90% 90%;
            background-position: center;
            background-repeat: no-repeat;
            opacity: 0;
            transition: opacity 0.45s var(--bw-ease);
            mix-blend-mode: screen;
        }
        .bw-glow-halo.is-on { opacity: 0.95; }

        /* Winning cells */
        .bw-winning-lines {
            position: absolute; inset: 0;
            z-index: 4; pointer-events: none;
        }
        .bw-winning-lines .bw-wl-cell {
            position: absolute;
            border-radius: 8px;
            box-shadow:
                0 0 0 2px rgba(255, 216, 107, 0.90),
                0 0 18px 4px rgba(255, 216, 107, 0.55);
            animation: bwWinGlow 1.1s var(--bw-ease) infinite alternate;
        }
        @keyframes bwWinGlow {
            from { box-shadow: 0 0 0 2px rgba(255, 216, 107, 0.55), 0 0 12px 2px rgba(255, 216, 107, 0.35); }
            to   { box-shadow: 0 0 0 2px rgba(255, 216, 107, 0.98), 0 0 24px 7px rgba(255, 216, 107, 0.75); }
        }

        /* Coin/spark layer */
        .bw-confetti {
            position: absolute; inset: 0;
            z-index: 5; pointer-events: none;
            overflow: hidden;
            border-radius: inherit;
        }
        .bw-coin {
            position: absolute;
            top: -16%;
            width: 40px; height: 40px;
            background-image: var(--bw-asset-coin);
            background-size: contain;
            background-position: center;
            background-repeat: no-repeat;
            opacity: 0;
            will-change: transform, opacity;
            animation: bwCoinFall var(--d, 2.4s) cubic-bezier(0.45, 0, 0.6, 1) forwards;
            filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.55));
        }
        @keyframes bwCoinFall {
            0%   { transform: translate3d(0, -20px, 0) rotate(0deg);   opacity: 0; }
            8%   { opacity: 1; }
            100% { transform: translate3d(var(--dx, 0), 130%, 0) rotate(var(--rot, 720deg)); opacity: 0.4; }
        }
        .bw-spark {
            position: absolute;
            width: 36px; height: 36px;
            margin: -18px 0 0 -18px;
            background-image: var(--bw-asset-spark);
            background-size: contain;
            background-position: center;
            background-repeat: no-repeat;
            opacity: 0;
            will-change: transform, opacity;
            animation: bwSparkPop var(--d, 1.3s) ease-out forwards;
            filter: drop-shadow(0 0 8px rgba(255, 216, 107, 0.7));
        }
        @keyframes bwSparkPop {
            0%   { transform: scale(0) rotate(0deg);    opacity: 0; }
            30%  { transform: scale(1.25) rotate(60deg);  opacity: 1; }
            100% { transform: scale(0.5) rotate(120deg);  opacity: 0; }
        }

        /* Win banner */
        .bw-win-banner {
            position: absolute; inset: 0;
            z-index: 7;
            display: flex; align-items: center; justify-content: center;
            opacity: 0;
            transform: scale(0.9);
            transition: opacity .35s var(--bw-ease), transform .55s var(--bw-ease-snap);
            pointer-events: none;
        }
        .bw-win-banner.is-visible { opacity: 1; transform: scale(1); }
        .bw-win-banner-inner {
            position: relative;
            padding: 14px 28px;
            border-radius: 18px;
            background: radial-gradient(ellipse at center, rgba(54, 10, 14, 0.94), rgba(14, 4, 8, 0.97));
            border: 1.5px solid rgba(255, 216, 107, 0.65);
            box-shadow:
                0 0 60px rgba(255, 216, 107, 0.50),
                0 0 24px rgba(209, 30, 42, 0.45),
                inset 0 0 24px rgba(255, 216, 107, 0.12);
            text-align: center;
            min-width: 60%;
        }
        .bw-win-banner-label {
            font-size: 11px; font-weight: 800;
            letter-spacing: 0.34em; text-transform: uppercase;
            color: var(--bw-gold-1);
            text-shadow: 0 0 12px rgba(255, 216, 107, 0.7);
        }
        .bw-win-banner-value {
            margin-top: 4px;
            font-size: clamp(28px, 8vw, 48px);
            font-weight: 900;
            letter-spacing: -0.02em;
            line-height: 1;
            font-variant-numeric: tabular-nums;
            background: linear-gradient(180deg, var(--bw-gold-hi) 0%, var(--bw-gold-1) 50%, var(--bw-gold-3) 100%);
            -webkit-background-clip: text;
                    background-clip: text;
            color: transparent;
            text-shadow: 0 0 16px rgba(255, 216, 107, 0.4);
        }

        .bw-pulse-ring {
            position: absolute; inset: 0;
            z-index: 4; border-radius: inherit;
            pointer-events: none;
            box-shadow: 0 0 0 0 rgba(255, 216, 107, 0);
        }
        .bw-pulse-ring.is-pulsing { animation: bwPulseRing 1.4s var(--bw-ease) 2; }
        @keyframes bwPulseRing {
            0%   { box-shadow: 0 0 0 0 rgba(255, 216, 107, 0.6), inset 0 0 0 0 rgba(255, 216, 107, 0.3); }
            100% { box-shadow: 0 0 0 28px rgba(255, 216, 107, 0), inset 0 0 0 8px rgba(255, 216, 107, 0); }
        }

        /* ---------- Embedded info strip inside the cabinet ---------- */
        .bw-info-strip {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: center;
            gap: 6px;
            margin-top: 8px;
            padding: 6px 8px;
            border-radius: 10px;
            background: linear-gradient(180deg, rgba(0, 0, 0, 0.45), rgba(0, 0, 0, 0.25));
            border: 1px solid rgba(255, 216, 107, 0.18);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.06);
        }
        .bw-info-cell {
            min-width: 0;
            text-align: center;
        }
        .bw-info-label {
            font-size: 8.5px; letter-spacing: 0.18em;
            text-transform: uppercase;
            color: var(--bw-ink-faint);
            line-height: 1;
        }
        .bw-info-value {
            margin-top: 3px;
            font-weight: 800; font-size: 13px; line-height: 1.1;
            font-variant-numeric: tabular-nums;
            color: var(--bw-ink);
            white-space: nowrap;
            overflow: hidden; text-overflow: ellipsis;
        }
        .bw-info-value--gold {
            color: var(--bw-gold-1);
            text-shadow: 0 0 8px rgba(255, 216, 107, 0.3);
        }
        .bw-info-value[data-status="win"]      { color: var(--bw-gold-1); }
        .bw-info-value[data-status="loss"]     { color: var(--bw-ink-dim); }
        .bw-info-value[data-status="spinning"] { color: var(--bw-cyan); }
        .bw-info-value[data-status="error"]    { color: #ffb7c2; }
        .bw-info-meta {
            margin-top: 2px;
            font-size: 9px;
            color: var(--bw-ink-faint);
            white-space: nowrap;
            overflow: hidden; text-overflow: ellipsis;
            font-variant-numeric: tabular-nums;
        }
        .bw-info-divider {
            width: 1px; height: 26px;
            background: linear-gradient(180deg, transparent, rgba(255, 216, 107, 0.4), transparent);
        }

        /* ---------- Message line ---------- */
        .bw-message {
            margin-top: 6px;
            padding: 6px 10px;
            border-radius: 8px;
            background: rgba(0, 0, 0, 0.30);
            border: 1px solid rgba(255, 216, 107, 0.14);
            color: var(--bw-ink-dim);
            font-size: 12px;
            text-align: center;
            min-height: 28px;
            display: flex; align-items: center; justify-content: center;
            transition: background .25s var(--bw-ease), color .25s var(--bw-ease), border-color .25s var(--bw-ease);
        }
        .bw-message[data-tone="win"] {
            background: rgba(255, 216, 107, 0.10);
            border-color: rgba(255, 216, 107, 0.40);
            color: var(--bw-gold-1);
        }
        .bw-message[data-tone="loss"] {
            background: rgba(209, 30, 42, 0.08);
            border-color: rgba(209, 30, 42, 0.22);
            color: var(--bw-ink-dim);
        }
        .bw-message[data-tone="error"] {
            background: rgba(255, 74, 120, 0.12);
            border-color: rgba(255, 74, 120, 0.38);
            color: #ffb7c2;
        }
        .bw-message[data-tone="info"] {
            background: rgba(94, 224, 255, 0.08);
            border-color: rgba(94, 224, 255, 0.30);
            color: var(--bw-cyan);
        }

        /* ---------- Embedded control bar (the bottom of the machine) ---------- */
        .bw-controls {
            display: grid;
            grid-template-columns: auto 1fr;
            grid-template-rows: auto auto auto;
            gap: 8px;
            align-items: center;
            margin-top: 8px;
            padding: 8px;
            border-radius: 14px;
            background:
                linear-gradient(180deg, rgba(0, 0, 0, 0.55), rgba(0, 0, 0, 0.40)),
                linear-gradient(180deg, var(--bw-wood-1), var(--bw-wood-2));
            border: 1px solid rgba(255, 216, 107, 0.30);
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.05),
                inset 0 -1px 2px rgba(0, 0, 0, 0.7);
        }
        .bw-bet-cluster {
            display: grid;
            grid-template-columns: 36px auto 36px;
            align-items: center;
            gap: 4px;
        }
        .bw-stepper {
            width: 36px; height: 44px;
            border-radius: 10px;
            border: 1px solid rgba(255, 216, 107, 0.40);
            background:
                radial-gradient(circle at 50% 0%, rgba(255, 216, 107, 0.35), transparent 60%),
                linear-gradient(180deg, rgba(255, 216, 107, 0.18), rgba(122, 15, 21, 0.25));
            color: var(--bw-gold-1);
            font-size: 22px; font-weight: 900;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: transform .12s var(--bw-ease), filter .2s var(--bw-ease);
            -webkit-appearance: none;
                    appearance: none;
            line-height: 1; padding: 0;
            text-shadow: 0 1px 0 rgba(0, 0, 0, 0.5);
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.25),
                inset 0 -1px 2px rgba(0, 0, 0, 0.4);
        }
        .bw-stepper:hover:not(:disabled) { filter: brightness(1.15); }
        .bw-stepper:active:not(:disabled) { transform: scale(0.94); }
        .bw-stepper:disabled { opacity: 0.4; cursor: not-allowed; }

        .bw-bet-display {
            display: flex; flex-direction: column; align-items: center;
            min-width: 64px;
            padding: 2px 6px;
            border-left: 1px solid rgba(255, 216, 107, 0.18);
            border-right: 1px solid rgba(255, 216, 107, 0.18);
        }
        .bw-bet-display-label {
            font-size: 8.5px; letter-spacing: 0.20em;
            text-transform: uppercase; color: var(--bw-ink-faint);
        }
        .bw-bet-display-value {
            margin-top: 1px;
            font-weight: 800; font-size: 15px;
            color: var(--bw-gold-1);
            font-variant-numeric: tabular-nums;
            line-height: 1.1; white-space: nowrap;
            text-shadow: 0 0 6px rgba(255, 216, 107, 0.4);
        }

        /* SPIN button — large gold push button */
        .bw-spin {
            position: relative;
            display: flex; align-items: center; justify-content: center;
            width: 100%;
            height: 60px;
            padding: 0 16px;
            border-radius: 14px;
            border: none;
            cursor: pointer;
            font-weight: 900;
            font-size: 17px;
            letter-spacing: 0.20em;
            text-transform: uppercase;
            color: #2d1500;
            background:
                radial-gradient(ellipse at 50% 0%, var(--bw-gold-hi) 0%, transparent 55%),
                linear-gradient(180deg, var(--bw-gold-1) 0%, var(--bw-gold-2) 45%, var(--bw-gold-3) 100%);
            box-shadow:
                0 14px 28px -10px rgba(246, 166, 9, 0.8),
                0 0 0 2px rgba(110, 60, 0, 0.6),
                0 0 0 3.5px rgba(255, 216, 107, 0.85),
                0 -3px 8px rgba(110, 60, 0, 0.5) inset,
                0 2px 0 rgba(255, 255, 255, 0.6) inset;
            transition: transform .12s var(--bw-ease), box-shadow .2s var(--bw-ease), filter .2s var(--bw-ease);
            -webkit-appearance: none;
                    appearance: none;
            overflow: hidden;
        }
        .bw-spin::after {
            content: ''; position: absolute;
            top: -20%; left: -30%;
            width: 50%; height: 140%;
            background: linear-gradient(110deg, transparent, rgba(255, 255, 255, 0.55), transparent);
            transform: skewX(-18deg);
            pointer-events: none;
            animation: bwSpinSheen 5s ease-in-out infinite;
        }
        @keyframes bwSpinSheen {
            0%, 60% { transform: translateX(-40%) skewX(-18deg); opacity: 0; }
            70%     { opacity: 0.9; }
            100%    { transform: translateX(260%) skewX(-18deg); opacity: 0; }
        }
        .bw-spin:hover:not(:disabled) { filter: brightness(1.06); }
        .bw-spin:active:not(:disabled) {
            transform: translateY(1px) scale(0.985);
        }
        .bw-spin:disabled,
        .bw-spin.is-busy {
            cursor: not-allowed;
            color: rgba(45, 21, 0, 0.55);
            filter: saturate(0.7) brightness(0.85);
        }
        .bw-spin.is-busy::after { animation: none; opacity: 0; }
        .bw-spin-label {
            position: relative; z-index: 1;
            display: inline-flex; align-items: center; gap: 8px;
        }
        .bw-spin-spinner {
            display: none;
            width: 16px; height: 16px;
            border-radius: 50%;
            border: 2px solid rgba(45, 21, 0, 0.35);
            border-top-color: rgba(45, 21, 0, 0.85);
            animation: bwSpinnerRot 0.85s linear infinite;
        }
        .bw-spin.is-busy .bw-spin-spinner { display: inline-block; }
        @keyframes bwSpinnerRot { to { transform: rotate(360deg); } }

        /* Bet preset rail — kept inside the controls block, scrolls horizontally */
        .bw-bet-presets {
            grid-column: 1 / -1;
            display: flex; gap: 6px;
            overflow-x: auto; overflow-y: hidden;
            padding: 2px;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            min-height: 26px;
        }
        .bw-bet-presets::-webkit-scrollbar { display: none; }
        .bw-bet-presets.is-empty { display: none; }
        .bw-bet-presets .bet-button {
            flex: 0 0 auto;
            min-width: 50px;
            height: 26px;
            padding: 0 10px;
            border-radius: 8px;
            border: 1px solid rgba(255, 216, 107, 0.22);
            background: rgba(0, 0, 0, 0.40);
            color: var(--bw-ink-dim);
            font-weight: 700;
            font-size: 11px;
            font-variant-numeric: tabular-nums;
            cursor: pointer;
            transition: transform .12s var(--bw-ease), background .2s var(--bw-ease), color .2s var(--bw-ease), border-color .2s var(--bw-ease);
            -webkit-appearance: none;
                    appearance: none;
            white-space: nowrap;
        }
        .bw-bet-presets .bet-button:hover:not(:disabled) {
            background: rgba(0, 0, 0, 0.6);
            color: var(--bw-ink);
        }
        .bw-bet-presets .bet-button:active:not(:disabled) { transform: scale(0.95); }
        .bw-bet-presets .bet-button.is-active {
            background: linear-gradient(180deg, rgba(255, 216, 107, 0.25), rgba(255, 216, 107, 0.08));
            border-color: rgba(255, 216, 107, 0.65);
            color: var(--bw-gold-1);
            box-shadow: 0 0 0 1px rgba(255, 216, 107, 0.30) inset;
        }
        .bw-bet-presets .bet-button:disabled { opacity: 0.45; cursor: not-allowed; }

        .bw-spin-hint {
            grid-column: 1 / -1;
            text-align: center;
            font-size: 9.5px;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: var(--bw-ink-faint);
            min-height: 12px;
            padding: 0 6px;
        }

        /* Mascot — tucked into the cabinet bottom-right, decorative only */
        .bw-mascot {
            position: absolute;
            bottom: -18px;
            right: -10px;
            width: 64px;
            height: auto;
            z-index: 8;
            pointer-events: none;
            opacity: 0.92;
            filter: drop-shadow(0 6px 10px rgba(0, 0, 0, 0.65));
            transition: transform .4s var(--bw-ease), opacity .4s var(--bw-ease);
        }
        .bw-mascot.is-celebrating {
            transform: translateY(-4px) rotate(-3deg) scale(1.06);
            opacity: 1;
        }
        @media (max-width: 380px) { .bw-mascot { width: 54px; bottom: -14px; right: -8px; } }
        @media (min-width: 540px) { .bw-mascot { width: 78px; } }

        /* ---------- Desktop side panel ---------- */
        .bw-side { display: none; }
        .bw-panel {
            border-radius: var(--bw-r-md);
            padding: 14px 16px;
            background: linear-gradient(180deg, rgba(40, 8, 12, 0.78), rgba(14, 4, 8, 0.62));
            border: 1px solid rgba(255, 216, 107, 0.20);
            -webkit-backdrop-filter: blur(18px) saturate(140%);
                    backdrop-filter: blur(18px) saturate(140%);
            box-shadow: 0 10px 24px -12px rgba(0, 0, 0, 0.7);
        }
        .bw-panel h3 {
            margin: 0 0 10px;
            font-size: 10px; font-weight: 700;
            letter-spacing: 0.22em; text-transform: uppercase;
            color: var(--bw-ink-faint);
        }
        .bw-kv {
            display: grid; grid-template-columns: auto 1fr;
            gap: 8px 14px; margin: 0; font-size: 13px;
        }
        .bw-kv dt { color: var(--bw-ink-faint); }
        .bw-kv dd {
            margin: 0; color: var(--bw-ink);
            text-align: right;
            font-variant-numeric: tabular-nums;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        .bw-tx {
            font-size: 12px; color: var(--bw-ink-dim);
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            display: grid; gap: 6px;
        }
        .bw-tx-row {
            display: flex; justify-content: space-between; gap: 8px;
            padding: 6px 8px;
            background: rgba(0, 0, 0, 0.30);
            border: 1px solid rgba(255, 216, 107, 0.12);
            border-radius: 8px;
        }
        .bw-tx-row .l { color: var(--bw-ink-faint); font-family: -apple-system, sans-serif; }
        .bw-tx-row .v { color: var(--bw-ink); }
        .bw-tx-row.is-credit .v { color: var(--bw-green); }
        .bw-tx-row.is-debit  .v { color: #ffb7c2; }

        /* ---------- Hidden mirror for `currentBetValue` (SR-only) ---------- */
        /* (Already covered by .bw-sr-only) */

        /* ---------- Responsive ---------- */
        @media (min-width: 540px) {
            .bw-machine { max-width: 520px; }
        }
        @media (min-width: 980px) {
            .bw-app {
                max-width: 1440px;
            }
            .bw-layout {
                grid-template-columns: minmax(0, 1fr) 340px;
                gap: 24px;
                padding: 18px 28px 24px;
                align-items: start;
            }
            .bw-side { display: flex; flex-direction: column; gap: 12px; }
            .bw-machine {
                max-width: 760px;
                box-shadow:
                    0 42px 90px -24px rgba(0, 0, 0, 0.88),
                    0 0 120px -24px rgba(209, 30, 42, 0.62),
                    inset 0 1px 0 rgba(255, 255, 255, 0.75),
                    inset 0 -2px 4px rgba(0, 0, 0, 0.35);
            }
            .bw-machine-face {
                padding: 14px 14px 12px;
            }
            .bw-reel-frame {
                padding: 6px;
            }
        }
        @media (min-width: 1280px) {
            .bw-layout {
                grid-template-columns: minmax(0, 1fr) 360px;
                gap: 28px;
            }
            .bw-machine {
                max-width: 780px;
            }
        }

        /* ---------- Reduced motion ---------- */
        @media (prefers-reduced-motion: reduce) {
            .bw-ambient-orb { animation: none !important; }
            .bw-spin::after { animation: none !important; opacity: 0; }
            .bw-winning-lines .bw-wl-cell { animation: none !important; }
            .bw-coin, .bw-spark { display: none !important; }
            .bw-pulse-ring.is-pulsing { animation: none !important; }
            .bw-win-banner { transition: opacity .15s linear; }
            .bw-win-banner.is-visible { transform: none; }
            .bw-glow-halo { transition: opacity .2s linear; }
            .bw-marquee-light { animation: none !important; opacity: 1; }
            .bw-mascot { transition: none !important; }
        }
        @supports not ((backdrop-filter: blur(1px)) or (-webkit-backdrop-filter: blur(1px))) {
            .bw-hud, .bw-panel { background: rgba(14, 4, 8, 0.94); }
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
    <div class="bw-scene" aria-hidden="true"></div>
    <div class="bw-vignette" aria-hidden="true"></div>
    <div class="bw-bokeh" aria-hidden="true"></div>
    <div class="bw-ambient-orb bw-ambient-orb--a" aria-hidden="true"></div>
    <div class="bw-ambient-orb bw-ambient-orb--b" aria-hidden="true"></div>

    <main class="bw-app">
        <header class="bw-hud" role="banner">
            <div class="bw-identity">
                <div class="bw-logo" aria-hidden="true">BW</div>
            </div>
            <div class="bw-mode-chip" id="modeChip" data-mode="demo" aria-label="Game mode">
                <span class="bw-mode-dot" aria-hidden="true"></span>
                <span id="modeValue">{{ $guestPreview ? __('catalog.guest_preview') : 'DEMO' }}</span>
            </div>
            <div class="bw-balance" aria-label="Balance">
                <div class="bw-balance-label">Balance</div>
                <div class="bw-balance-value" id="balanceValue">{{ $guestPreview ? '—' : '0.00' }}</div>
            </div>
        </header>

        <section class="bw-layout">
            <div class="bw-machine" id="slotMachineShell">
                <div class="bw-machine-face">
                    <!-- Top marquee with wordmark + blinking lights + multiplier ticker -->
                    <div class="bw-marquee-bar">
                        <span class="bw-marquee-light bw-marquee-light--r" aria-hidden="true"></span>
                        <div class="bw-wordmark" aria-hidden="true">BIG WIN</div>
                        <span class="bw-marquee-light" aria-hidden="true"></span>
                        <div class="bw-marquee-multipliers" id="bwMultipliers" aria-hidden="true">
                            <span>×2</span><span>×3</span><span>×5</span><span>×10</span><span>×25</span>
                        </div>
                    </div>

                    <!-- Reel window -->
                    <div class="bw-reel-frame">
                        <div class="bw-canvas-wrap">
                            <div class="bw-glow-halo" id="bwGlowHalo" aria-hidden="true"></div>
                            @if($guestPreview)
                                    <div style="display:flex;justify-content:center;align-items:center;height:100%;gap:10px">
                                        @foreach(array_slice($payload['symbolAssets'], 0, 3) as $symbol => $assetPath)
                                            <img src="{{ $assetPath }}" alt="{{ $symbol }}" style="width:24%;max-width:120px">
                                        @endforeach
                                    </div>
                                @else
                                    <canvas id="slotCanvas" width="600" height="450" aria-label="Slot reels"></canvas>
                                @endif
                            <div class="bw-canvas-glare" aria-hidden="true"></div>
                            <div class="bw-payline" aria-hidden="true"></div>
                            <div class="bw-winning-lines" id="winningLines" aria-hidden="true"></div>
                            <div class="bw-pulse-ring" id="bwPulseRing" aria-hidden="true"></div>
                            <div class="bw-confetti" id="bwConfetti" aria-hidden="true"></div>
                            <div class="bw-cabinet-overlay" aria-hidden="true"></div>

                            <div class="bw-win-banner" id="winBanner" role="status" aria-live="polite">
                                <div class="bw-win-banner-inner">
                                    <div class="bw-win-banner-label">Big Win</div>
                                    <div class="bw-win-banner-value" id="winBannerValue">0.00</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Info strip (last win / round) -->
                    <div class="bw-info-strip">
                        <div class="bw-info-cell">
                            <div class="bw-info-label">Last Win</div>
                            <div class="bw-info-value bw-info-value--gold" id="lastWinValue">0.00</div>
                            <div class="bw-info-meta" id="lastWinMeta">No spin yet</div>
                        </div>
                        <div class="bw-info-divider" aria-hidden="true"></div>
                        <div class="bw-info-cell">
                            <div class="bw-info-label">Round</div>
                            <div class="bw-info-value" id="roundStatusValue" data-status="idle">Idle</div>
                            <div class="bw-info-meta" id="roundIdValue">—</div>
                        </div>
                    </div>

                    <!-- Message line -->
                    <div class="bw-message" id="slotMessage" data-tone="info" role="status" aria-live="polite">
                        Set your bet and tap SPIN
                    </div>

                    <!-- Embedded controls -->
                    <div class="bw-controls">
                        <div class="bw-bet-cluster">
                            <button class="bw-stepper" id="betDownButton" type="button" aria-label="Decrease bet">−</button>
                            <div class="bw-bet-display">
                                <div class="bw-bet-display-label">Bet</div>
                                <div class="bw-bet-display-value">
                                    <span id="betDisplayValue">0.00</span>
                                    <span id="currentBetValue" class="bw-sr-only">0</span>
                                </div>
                            </div>
                            <button class="bw-stepper" id="betUpButton" type="button" aria-label="Increase bet">+</button>
                        </div>

                        <button class="bw-spin" id="spinButton" type="button" aria-label="Spin">
                            <span class="bw-spin-label">
                                <span class="bw-spin-spinner" aria-hidden="true"></span>
                                <span>Spin</span>
                            </span>
                        </button>

                        <div id="betGrid" class="bw-bet-presets is-empty" aria-label="Bet presets" role="group"></div>

                        <div class="bw-spin-hint" id="spinHint">Tap to spin</div>
                    </div>
                </div>

                <!-- Decorative mascot tucked into the cabinet skirt -->
                <img class="bw-mascot" id="bwMascot" src="" alt="" aria-hidden="true">
            </div>

            <aside class="bw-side" aria-label="Round details">
                <section class="bw-panel">
                    <h3>Round</h3>
                    <dl class="bw-kv">
                        <dt>Bet</dt><dd id="betAmountValue">0.00</dd>
                        <dt>Win</dt><dd id="winAmountValue">0.00</dd>
                    </dl>
                </section>
                <section class="bw-panel">
                    <h3>Transactions</h3>
                    <div class="bw-tx" id="transactionValue">
                        <div class="bw-tx-row"><span class="l">No transactions yet</span><span class="v"></span></div>
                    </div>
                </section>
            </aside>
        </section>
    </main>

    @if($guestPreview)
    </div>
@else
@include('game-provider-front.partials.slot-session')
    <script>
        window.bigWinPayload = @json($payload);
    </script>
    <script>
        (function () {
            'use strict';

            // =================================================================
            // INTEGRATION CONTRACT (unchanged from v1)
            // Input payload (window.bigWinPayload):
            //   { sessionToken, gameName: "Big Win", currency, isDemo,
            //     balance, betValues[], spinRoute?,
            //     symbolAssets?, audioAssets?, assetBase? }
            // Request body to spinRoute: { sessionToken, betAmount, requestKey }
            // Response (CMS-authoritative, sole source of truth):
            //   { status: 200, data: { balance, roundId, status, betAmount,
            //                          winAmount, transactions:{bet?,win?},
            //                          result: { grid, winningLines? } } }
            // CMS authority: session, balance, win amount, transaction IDs,
            //                result grid, round status.
            // =================================================================

            // ---------- Asset base resolver ----------
            // Production (Blade view): assets live at Laravel public-root absolute paths.
            // Preview (file:// open):  assets live next to the HTML inside
            //   the package-local public tree — use that path so the file is
            //   reviewable without a web server.
            // The resolver can also be overridden via payload.assetBase.
            function resolveAssetBase() {
                try {
                    const p = (window.bigWinPayload || {});
                    if (typeof p.assetBase === 'string' && p.assetBase.length) {
                        return p.assetBase.replace(/\/+$/, '');
                    }
                    const proto = String(window.location && window.location.protocol || '');
                    if (proto === 'file:' || proto === '') {
                        // file:// → use a path relative to the HTML.
                        return './public';
                    }
                    return ''; // empty → asset paths start with '/'
                } catch (e) {
                    return '';
                }
            }
            const ASSET_BASE = resolveAssetBase();
            function asset(absolutePath) {
                // absolutePath is one of the manifest-approved public paths.
                return ASSET_BASE + absolutePath;
            }

            // Inject asset URLs into CSS variables so backgrounds work too.
            (function injectAssetVars() {
                const map = {
                    '--bw-asset-bg':      'none',
                    '--bw-asset-cabinet': "url('" + asset('/internal-games/big-win/generated/cabinet/frame.png') + "')",
                    '--bw-asset-glow':    "url('" + asset('/internal-games/big-win/generated/effects/glow.png') + "')",
                    '--bw-asset-coin':    "url('" + asset('/internal-games/big-win/generated/effects/coin.png') + "')",
                    '--bw-asset-spark':   "url('" + asset('/internal-games/big-win/generated/effects/spark.png') + "')",
                    '--bw-asset-mascot':  "url('" + asset('/internal-games/big-win/generated/character/mascot.png') + "')",
                };
                Object.entries(map).forEach(([k, v]) => {
                    document.documentElement.style.setProperty(k, v);
                });
                // <img> src — set once at boot
                const m = document.getElementById('bwMascot');
                if (m) m.src = asset('/internal-games/big-win/generated/character/mascot.png');
            })();

            // ---------- Payload ----------
            const payload = window.bigWinPayload;
            if (!payload?.sessionToken || !payload?.spinRoute) throw new Error('A valid server slot session is required.');

            // ---------- Manifest-restricted asset URLs (resolved via asset()) ----------
            const ENGINE_SYMBOL_URLS = {
                'Blank':  asset('/internal-games/big-win/generated/symbols/blank.png'),
                'BAR':    asset('/internal-games/big-win/generated/symbols/bar.png'),
                '2xBAR':  asset('/internal-games/big-win/generated/symbols/2xbar.png'),
                '3xBAR':  asset('/internal-games/big-win/generated/symbols/3xbar.png'),
                '7':      asset('/internal-games/big-win/generated/symbols/seven.png'),
                'Cherry': asset('/internal-games/mini-slot/images/Cherry.png'),
            };
            const ENGINE_KEYS = Object.freeze(['Blank', 'BAR', '2xBAR', '3xBAR', '7', 'Cherry']);

            const TAPE_SYMBOL_URLS = {
                'star':       asset('/internal-games/big-win/generated/symbols/star.png'),
                'bell':       asset('/internal-games/big-win/generated/symbols/bell.png'),
                'watermelon': asset('/internal-games/big-win/generated/symbols/watermelon.png'),
                'grapes':     asset('/internal-games/big-win/generated/symbols/grapes.png'),
                'lemon':      asset('/internal-games/big-win/generated/symbols/lemon.png'),
                'orange':     asset('/internal-games/big-win/generated/symbols/orange.png'),
                'plum':       asset('/internal-games/big-win/generated/symbols/plum.png'),
                'lime':       asset('/internal-games/big-win/generated/symbols/lime.png'),
            };
            // Spin tape weighted toward engine symbols so the player still
            // sees BAR/7/Cherry rolling past during anticipation.
            const SPIN_TAPE_KEYS = Object.freeze([
                'Cherry', 'BAR', '2xBAR', '3xBAR', '7',
                'Cherry', 'BAR', '7',
                'star', 'bell', 'watermelon', 'grapes',
                'lemon', 'orange', 'plum', 'lime',
            ]);

            const SOUND_SPIN_URL = (payload.audioAssets && payload.audioAssets.spin) || asset('/internal-games/mini-slot/sound/spin.mp3');
            const SOUND_WIN_URL  = (payload.audioAssets && payload.audioAssets.win)  || asset('/internal-games/mini-slot/sound/win.mp3');

            // CMS still supplies symbolAssets as part of the shared payload,
            // but this surface intentionally renders only manifest-v2 Big Win
            // assets for symbol art. The server result keys remain unchanged.

            // ---------- State ----------
            const reduceMotionMQ = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)');
            const state = {
                balance: Number(payload.balance) || 0,
                bet: 0,
                busy: false,
                lastRoundId: null,
                walletMessageSeq: 0,
                grid: makeIdleSampleGrid(3, 3),
                rows: 3,
                cols: 3,
                spinningOffsets: null,
                idleDrift: 0,
                reduceMotion: !!(reduceMotionMQ && reduceMotionMQ.matches),
            };

            // ---------- DOM refs ----------
            const $ = (id) => document.getElementById(id);
            const els = {
                canvas:         $('slotCanvas'),
                shell:          $('slotMachineShell'),
                winBanner:      $('winBanner'),
                winBannerValue: $('winBannerValue'),
                betDown:        $('betDownButton'),
                betUp:          $('betUpButton'),
                betDisplay:     $('betDisplayValue'),
                currentBet:     $('currentBetValue'),
                betGrid:        $('betGrid'),
                spinButton:     $('spinButton'),
                message:        $('slotMessage'),
                balance:        $('balanceValue'),
                lastWin:        $('lastWinValue'),
                lastWinMeta:    $('lastWinMeta'),
                modeChip:       $('modeChip'),
                modeValue:      $('modeValue'),
                spinHint:       $('spinHint'),
                roundId:        $('roundIdValue'),
                roundStatus:    $('roundStatusValue'),
                betAmount:      $('betAmountValue'),
                winAmount:      $('winAmountValue'),
                transactions:   $('transactionValue'),
                winningLines:   $('winningLines'),
                confetti:       $('bwConfetti'),
                pulseRing:      $('bwPulseRing'),
                glowHalo:       $('bwGlowHalo'),
                mascot:         $('bwMascot'),
                multipliers:    $('bwMultipliers'),
            };

            // ---------- Bet handling ----------
            const betValues = (Array.isArray(payload.betValues) ? payload.betValues : [])
                .map(Number).filter(n => isFinite(n) && n > 0).sort((a, b) => a - b);
            const minBet = betValues.length ? betValues[0] : 1;
            const maxBet = betValues.length ? betValues[betValues.length - 1] : 100;
            state.bet = betValues.length ? betValues[0] : 1;

            function clampBet(v) {
                let n = Number(v);
                if (!isFinite(n)) return state.bet;
                if (betValues.length) {
                    n = betValues.reduce((best, s) => Math.abs(s - n) < Math.abs(best - n) ? s : best, betValues[0]);
                }
                return Math.min(Math.max(n, minBet), maxBet);
            }

            // ---------- Formatting ----------
            const currencySym = payload.currencySymbol || '';
            const currencyCode = payload.currency || '';
            function formatMoney(value) {const out=window.riseSlotSession.format(value);return currencyCode ? out+' '+currencyCode : out;}
            function shortenId(id) {
                if (id == null) return '—';
                const s = String(id);
                if (s.length <= 18) return s;
                return s.slice(0, 8) + '…' + s.slice(-6);
            }

            // ---------- Grid helpers ----------
            function makeBlankGrid(rows, cols) {
                const g = [];
                for (let r = 0; r < rows; r++) {
                    const row = [];
                    for (let c = 0; c < cols; c++) row.push('Blank');
                    g.push(row);
                }
                return g;
            }
            // Decorative idle pre-fill so the cabinet doesn't sit empty at boot.
            // Uses ONLY engine keys (manifest §7 invariant). Never represents
            // a server result.
            function makeIdleSampleGrid(rows, cols) {
                const idleSet = ['Cherry', 'BAR', '7', '2xBAR', 'Cherry', '3xBAR', 'BAR', '7', 'Cherry'];
                const g = [];
                let k = 0;
                for (let r = 0; r < rows; r++) {
                    const row = [];
                    for (let c = 0; c < cols; c++) row.push(idleSet[(k++) % idleSet.length]);
                    g.push(row);
                }
                return g;
            }
            function detectGridDims(grid) {
                if (!Array.isArray(grid) || grid.length === 0) return { rows: 3, cols: 3 };
                if (isCmsColumnMajorGrid(grid)) return { rows: grid[0].length, cols: grid.length };
                if (Array.isArray(grid[0])) return { rows: grid.length, cols: grid[0].length };
                const n = grid.length;
                if (n === 9)  return { rows: 3, cols: 3 };
                if (n === 15) return { rows: 3, cols: 5 };
                if (n === 25) return { rows: 5, cols: 5 };
                return { rows: 3, cols: 3 };
            }
            function isCmsColumnMajorGrid(grid) {
                return !!payload.spinRoute
                    && Array.isArray(grid)
                    && grid.length > 0
                    && grid.every((column) => Array.isArray(column))
                    && grid.every((column) => column.length === grid[0].length);
            }
            // Enforce engine-key-only invariant: anything else collapses to 'Blank'.
            function normaliseGrid(grid) {
                const { rows, cols } = detectGridDims(grid);
                const out = [];
                const safe = (v) => ENGINE_KEYS.indexOf(String(v)) >= 0 ? String(v) : 'Blank';
                if (isCmsColumnMajorGrid(grid)) {
                    for (let r = 0; r < rows; r++) {
                        const row = [];
                        for (let c = 0; c < cols; c++) row.push(safe(grid[c] && grid[c][r]));
                        out.push(row);
                    }
                } else if (Array.isArray(grid) && Array.isArray(grid[0])) {
                    for (let r = 0; r < rows; r++) {
                        const row = [];
                        for (let c = 0; c < cols; c++) row.push(safe(grid[r] && grid[r][c]));
                        out.push(row);
                    }
                } else if (Array.isArray(grid)) {
                    for (let r = 0; r < rows; r++) {
                        const row = [];
                        for (let c = 0; c < cols; c++) row.push(safe(grid[r * cols + c]));
                        out.push(row);
                    }
                } else {
                    return { grid: makeBlankGrid(rows, cols), rows, cols };
                }
                return { grid: out, rows, cols };
            }

            // ---------- Image preload ----------
            const symbolImages = {};
            let symbolsReady = 0;
            const totalSymbols = Object.keys(ENGINE_SYMBOL_URLS).length + Object.keys(TAPE_SYMBOL_URLS).length;
            function preloadImages() {
                const all = Object.assign({}, ENGINE_SYMBOL_URLS, TAPE_SYMBOL_URLS);
                Object.entries(all).forEach(([name, url]) => {
                    const img = new Image();
                    img.decoding = 'async';
                    img.onload = () => { symbolsReady++; renderCanvas(); };
                    img.onerror = () => { symbolsReady++; renderCanvas(); };
                    img.src = url;
                    symbolImages[name] = img;
                });
            }

            // ---------- Audio ----------
            let audioSpin = null, audioWin = null;
            function initAudio() {
                try {
                    audioSpin = new Audio(SOUND_SPIN_URL);
                    audioSpin.preload = 'auto';
                    audioSpin.volume = 0.45;
                    audioWin = new Audio(SOUND_WIN_URL);
                    audioWin.preload = 'auto';
                    audioWin.volume = 0.6;
                } catch (e) { /* audio optional */ }
            }
            function playSound(a) {
                if (!a) return;
                try { a.currentTime = 0; const p = a.play(); if (p && p.catch) p.catch(() => {}); } catch (e) {}
            }
            function stopSound(a) {
                if (!a) return;
                try { a.pause(); a.currentTime = 0; } catch (e) {}
            }

            // ---------- Canvas render ----------
            function renderCanvas() {
                const canvas = els.canvas;
                if (!canvas) return;
                const ctx = canvas.getContext('2d');
                const W = canvas.width, H = canvas.height;
                const { cols, rows, grid, spinningOffsets } = state;

                // Background well (dark crimson with vertical brown gradient)
                const bg = ctx.createLinearGradient(0, 0, 0, H);
                bg.addColorStop(0, '#1a040a');
                bg.addColorStop(0.5, '#2a080c');
                bg.addColorStop(1, '#0e0408');
                ctx.fillStyle = bg;
                ctx.fillRect(0, 0, W, H);

                const reelW = W / cols;
                const cellH = H / rows;

                // Reel wells with vertical gold rails between each reel
                for (let c = 0; c < cols; c++) {
                    const x = c * reelW;
                    // dark well per column
                    const cg = ctx.createLinearGradient(x, 0, x, H);
                    cg.addColorStop(0,   'rgba(255, 220, 160, 0.10)');
                    cg.addColorStop(0.5, 'rgba(255, 220, 160, 0.02)');
                    cg.addColorStop(1,   'rgba(0, 0, 0, 0.30)');
                    ctx.fillStyle = cg;
                    ctx.fillRect(x + 3, 3, reelW - 6, H - 6);
                }
                // gold rail dividers
                for (let c = 0; c <= cols; c++) {
                    const x = c * reelW;
                    const railGrad = ctx.createLinearGradient(x, 0, x, H);
                    railGrad.addColorStop(0,   'rgba(255, 216, 107, 0.05)');
                    railGrad.addColorStop(0.5, 'rgba(255, 216, 107, 0.55)');
                    railGrad.addColorStop(1,   'rgba(255, 216, 107, 0.05)');
                    ctx.fillStyle = railGrad;
                    ctx.fillRect(x - 1, 6, 2, H - 12);
                }

                // payline emphasis behind symbols
                const plY = H / 2;
                const plGrad = ctx.createLinearGradient(0, plY - 4, 0, plY + 4);
                plGrad.addColorStop(0, 'rgba(255, 216, 107, 0)');
                plGrad.addColorStop(0.5, 'rgba(255, 216, 107, 0.08)');
                plGrad.addColorStop(1, 'rgba(255, 216, 107, 0)');
                ctx.fillStyle = plGrad;
                ctx.fillRect(0, plY - 4, W, 8);

                const pad = Math.min(reelW, cellH) * 0.06;
                const symMax = Math.min(reelW, cellH) - pad * 2;

                for (let c = 0; c < cols; c++) {
                    ctx.save();
                    ctx.beginPath();
                    ctx.rect(c * reelW + 4, 4, reelW - 8, H - 8);
                    ctx.clip();

                    if (spinningOffsets) {
                        const offset = spinningOffsets[c] || 0;
                        const tapeLen = rows + 6;
                        const tapeH = tapeLen * cellH;
                        const baseY = ((offset % tapeH) + tapeH) % tapeH;
                        for (let i = -2; i < tapeLen + 2; i++) {
                            const sym = pickTapeSymbol(c, i);
                            const y = (i * cellH + baseY) % tapeH;
                            const cy = y + cellH / 2 - cellH;
                            drawSymbol(ctx, sym, c * reelW + reelW / 2, cy, symMax, true);
                        }
                    } else {
                        const drift = 0;
                        for (let r = 0; r < rows; r++) {
                            const sym = grid[r] && grid[r][c] ? grid[r][c] : 'Blank';
                            drawSymbol(ctx, sym, c * reelW + reelW / 2, r * cellH + cellH / 2 + drift, symMax, false);
                        }
                    }
                    ctx.restore();
                }

                // top/bottom inner shadow inside the canvas
                const topS = ctx.createLinearGradient(0, 0, 0, 40);
                topS.addColorStop(0, 'rgba(0, 0, 0, 0.7)');
                topS.addColorStop(1, 'rgba(0, 0, 0, 0)');
                ctx.fillStyle = topS; ctx.fillRect(0, 0, W, 40);
                const botS = ctx.createLinearGradient(0, H - 40, 0, H);
                botS.addColorStop(0, 'rgba(0, 0, 0, 0)');
                botS.addColorStop(1, 'rgba(0, 0, 0, 0.7)');
                ctx.fillStyle = botS; ctx.fillRect(0, H - 40, W, 40);
            }

            function pickTapeSymbol(col, i) {
                const seed = ((col + 1) * 1103515245 + (i + 1000) * 12345) & 0x7fffffff;
                return SPIN_TAPE_KEYS[seed % SPIN_TAPE_KEYS.length];
            }

            function drawSymbol(ctx, name, cx, cy, size, isSpinning) {
                const img = symbolImages[name];
                if (!img || !img.complete || img.naturalWidth === 0) {
                    // Fallback only used when image truly failed.
                    // Kept extremely subtle so a missing single asset is not
                    // visually catastrophic, but won't be visible when assets
                    // load correctly.
                    ctx.fillStyle = 'rgba(255, 216, 107, 0.10)';
                    ctx.beginPath();
                    ctx.arc(cx, cy, size * 0.10, 0, Math.PI * 2);
                    ctx.fill();
                    return;
                }
                const ratio = img.naturalWidth / img.naturalHeight;
                let w = size, h = size;
                if (ratio > 1) { h = size / ratio; } else { w = size * ratio; }
                // Slight motion blur effect during spin via alpha
                if (isSpinning) ctx.globalAlpha = 0.92;
                ctx.drawImage(img, cx - w / 2, cy - h / 2, w, h);
                ctx.globalAlpha = 1;
            }

            // ---------- Idle loop (lightweight) ----------
            let idleAnim = null;
            function startIdleLoop() {
                if (idleAnim) {
                    cancelAnimationFrame(idleAnim);
                    idleAnim = null;
                }
            }

            // ---------- Spin animation ----------
            let spinAnim = null;
            function startSpinAnimation() {
                cancelSpinAnimation();
                if (idleAnim) { cancelAnimationFrame(idleAnim); idleAnim = null; }
                state.spinningOffsets = new Array(state.cols).fill(0);
                const speeds = state.spinningOffsets.map((_, i) => 32 + i * 4);
                let last = performance.now();
                const step = (now) => {
                    const dt = Math.min(48, now - last);
                    last = now;
                    for (let c = 0; c < state.cols; c++) {
                        state.spinningOffsets[c] = state.spinningOffsets[c] + speeds[c] * (dt / 16.6);
                    }
                    renderCanvas();
                    spinAnim = requestAnimationFrame(step);
                };
                spinAnim = requestAnimationFrame(step);
            }
            function cancelSpinAnimation() {
                if (spinAnim) cancelAnimationFrame(spinAnim);
                spinAnim = null;
            }
            function stopSpinAnimation(finalGrid) {
                cancelSpinAnimation();
                state.spinningOffsets = null;
                const norm = normaliseGrid(finalGrid);
                state.grid = norm.grid;
                state.rows = norm.rows;
                state.cols = norm.cols;
                renderCanvas();
                startIdleLoop();

            }

            // ---------- Winning lines ----------
            function clearWinningLines() {
                if (els.winningLines) els.winningLines.innerHTML = '';
            }
            function paintWinningLines(wlines) {
                clearWinningLines();
                if (!wlines || !els.winningLines) return;
                const cells = extractWinningCells(wlines);
                if (!cells.length) return;
                const cw = 100 / state.cols, ch = 100 / state.rows;
                const frag = document.createDocumentFragment();
                cells.forEach(([r, c]) => {
                    if (r < 0 || r >= state.rows || c < 0 || c >= state.cols) return;
                    const div = document.createElement('div');
                    div.className = 'bw-wl-cell';
                    div.style.left   = (c * cw + 1) + '%';
                    div.style.top    = (r * ch + 1) + '%';
                    div.style.width  = (cw - 2) + '%';
                    div.style.height = (ch - 2) + '%';
                    frag.appendChild(div);
                });
                els.winningLines.appendChild(frag);
            }
            function extractWinningCells(node) {
                const out = [];
                const push = (a, b) => {
                    const r = Number(a), c = Number(b);
                    if (isFinite(r) && isFinite(c)) out.push([r, c]);
                };
                const walk = (n) => {
                    if (n == null) return;
                    if (Array.isArray(n)) {
                        if (n.length === 2 && typeof n[0] === 'number' && typeof n[1] === 'number') {
                            push(n[0], n[1]); return;
                        }
                        n.forEach(walk); return;
                    }
                    if (typeof n === 'object') {
                        if (Array.isArray(n.positions)) { walk(n.positions); return; }
                        if (Array.isArray(n.cells))     { walk(n.cells); return; }
                        if (typeof n.row === 'number' && typeof n.col === 'number') { push(n.row, n.col); return; }
                        const line = String(n.line || n.lineLabel || '').toLowerCase();
                        const rowIndex = line.includes('middle') ? 1
                            : line.includes('bottom') ? 2
                                : (line.includes('top') ? 0 : (typeof n.lineIndex === 'number' ? n.lineIndex : null));
                        if (rowIndex !== null) {
                            for (let c = 0; c < state.cols; c++) push(rowIndex, c);
                            return;
                        }
                    }
                };
                walk(node);
                const seen = new Set();
                return out.filter(([r, c]) => {
                    const k = r + ',' + c;
                    if (seen.has(k)) return false;
                    seen.add(k);
                    return true;
                });
            }

            // ---------- Win effects ----------
            function fireCoinShower(intensity) {
                if (state.reduceMotion || !els.confetti) return;
                const rect = els.canvas.getBoundingClientRect();
                const W = rect.width || 360;
                const n = Math.min(34, Math.max(14, Math.round(intensity)));
                const frag = document.createDocumentFragment();
                for (let i = 0; i < n; i++) {
                    const piece = document.createElement('span');
                    piece.className = 'bw-coin';
                    const sz = 26 + Math.random() * 22;
                    piece.style.left   = (Math.random() * W) + 'px';
                    piece.style.width  = sz + 'px';
                    piece.style.height = sz + 'px';
                    piece.style.setProperty('--dx',  ((Math.random() - 0.5) * 240) + 'px');
                    piece.style.setProperty('--d',   (1.8 + Math.random() * 1.6) + 's');
                    piece.style.setProperty('--rot', ((Math.random() < 0.5 ? -1 : 1) * (360 + Math.random() * 540)) + 'deg');
                    piece.addEventListener('animationend', () => piece.remove(), { once: true });
                    frag.appendChild(piece);
                }
                els.confetti.appendChild(frag);
            }
            function fireSparks(cells) {
                if (state.reduceMotion || !cells || !cells.length || !els.confetti) return;
                const cw = 100 / state.cols, ch = 100 / state.rows;
                const n = Math.min(10, cells.length * 2);
                const frag = document.createDocumentFragment();
                for (let i = 0; i < n; i++) {
                    const [r, c] = cells[i % cells.length];
                    if (r == null || c == null) continue;
                    const piece = document.createElement('span');
                    piece.className = 'bw-spark';
                    const jx = (Math.random() - 0.5) * cw * 0.6;
                    const jy = (Math.random() - 0.5) * ch * 0.6;
                    piece.style.left = (c * cw + cw / 2 + jx) + '%';
                    piece.style.top  = (r * ch + ch / 2 + jy) + '%';
                    piece.style.setProperty('--d', (1.0 + Math.random() * 0.6) + 's');
                    piece.style.animationDelay = (Math.random() * 0.25) + 's';
                    piece.addEventListener('animationend', () => piece.remove(), { once: true });
                    frag.appendChild(piece);
                }
                els.confetti.appendChild(frag);
            }
            function setGlowHalo(on) {
                if (els.glowHalo) els.glowHalo.classList.toggle('is-on', !!on);
            }
            function setMascotCelebrating(on) {
                if (els.mascot) els.mascot.classList.toggle('is-celebrating', !!on);
            }
            function pulseCabinet() {
                if (state.reduceMotion || !els.pulseRing) return;
                els.pulseRing.classList.remove('is-pulsing');
                void els.pulseRing.offsetWidth;
                els.pulseRing.classList.add('is-pulsing');
            }
            function flashMultiplier(winSymbolMultiplier) {
                if (!els.multipliers) return;
                const spans = els.multipliers.querySelectorAll('span');
                spans.forEach(s => s.classList.remove('is-hot'));
                if (!winSymbolMultiplier) return;
                // Find the closest visible multiplier and light it.
                const targets = ['×2', '×3', '×5', '×10', '×25'];
                let bestIdx = 0;
                let bestDelta = Infinity;
                targets.forEach((label, i) => {
                    const v = Number(label.slice(1));
                    const d = Math.abs(v - winSymbolMultiplier);
                    if (d < bestDelta) { bestDelta = d; bestIdx = i; }
                });
                if (spans[bestIdx]) spans[bestIdx].classList.add('is-hot');
            }

            function animateWinBannerValue(target) {
                if (!els.winBannerValue) return;
                els.winBanner && els.winBanner.classList.add('is-visible');
                if (state.reduceMotion) {
                    els.winBannerValue.textContent = formatMoney(target);
                    return;
                }
                const tStart = performance.now();
                const dur = 900;
                const startVal = 0;
                const step = (now) => {
                    const t = Math.min(1, (now - tStart) / dur);
                    // ease-out cubic
                    const eased = 1 - Math.pow(1 - t, 3);
                    const v = startVal + (target - startVal) * eased;
                    els.winBannerValue.textContent = formatMoney(v);
                    if (t < 1) requestAnimationFrame(step);
                    else els.winBannerValue.textContent = formatMoney(target);
                };
                requestAnimationFrame(step);
            }
            function hideWinBanner() {
                if (els.winBanner) els.winBanner.classList.remove('is-visible');
            }

            // ---------- HUD writers ----------
            function applyMode() {
                if (!els.modeChip) return;
                const m = payload.isDemo === false ? 'real' : 'demo';
                els.modeChip.dataset.mode = m;
                if (els.modeValue) els.modeValue.textContent = m.toUpperCase();
            }
            function setBalance(v, exact = null) {
                const n = Number(v);
                if (!isFinite(n)) return;
                state.balance = n;
                state.balanceExact = exact ?? String(v);
                if (els.balance) els.balance.textContent = formatMoney(state.balanceExact);
            }
            function postParentWalletUpdate(data) {
                if (payload.isDemo !== false || !window.parent || window.parent === window) {
                    return;
                }

                const balance = Number(data && data.balance);
                if (!isFinite(balance)) {
                    return;
                }

                state.walletMessageSeq += 1;
                window.parent.postMessage({
                    type: 'internal-game:wallet-updated',
                    source: 'big-win',
                    roundId: data.roundId || null,
                    balance: balance,
                    balanceExact: data.balanceExact,
                    currency: data.currency || currencyCode || '',
                    timestamp: Date.now(),
                    sequence: state.walletMessageSeq,
                }, window.location.origin);
            }
            function applyImmediateWalletUpdate(envelope) {
                const data = (envelope && envelope.data) || {};
                if (data.balance == null) {
                    return;
                }

                setBalance(data.balance,data.balanceExact);
                postParentWalletUpdate(data);
            }
            function setBet(v) {
                const next = clampBet(v);
                state.bet = next;
                if (els.betDisplay) els.betDisplay.textContent = formatMoney(next);
                if (els.currentBet) els.currentBet.textContent = String(next);
                if (els.betAmount)  els.betAmount.textContent  = formatMoney(next);
                updateSteppersDisabled();
                highlightActivePreset();
            }
            function updateSteppersDisabled() {
                if (els.betDown) els.betDown.disabled = state.busy || state.bet <= minBet;
                if (els.betUp)   els.betUp.disabled   = state.busy || state.bet >= maxBet;
                if (els.betGrid) els.betGrid.querySelectorAll('.bet-button').forEach(b => { b.disabled = state.busy; });
            }
            function nextBet(dir) {
                if (!betValues.length) return setBet(state.bet);
                let idx = betValues.indexOf(state.bet);
                if (idx === -1) {
                    idx = betValues.reduce((best, s, i) =>
                        Math.abs(s - state.bet) < Math.abs(betValues[best] - state.bet) ? i : best, 0);
                }
                return setBet(betValues[Math.min(Math.max(idx + dir, 0), betValues.length - 1)]);
            }
            function setBusy(busy) {
                state.busy = !!busy;
                if (els.spinButton) {
                    els.spinButton.disabled = state.busy;
                    els.spinButton.classList.toggle('is-busy', state.busy);
                }
                updateSteppersDisabled();
            }
            function setMessage(text, tone) {
                if (!els.message) return;
                els.message.textContent = text;
                els.message.dataset.tone = tone || 'info';
            }
            function setHint(text) {
                if (els.spinHint) els.spinHint.textContent = text || '';
            }
            function setRound(id, status) {
                if (els.roundId) els.roundId.textContent = shortenId(id);
                if (els.roundStatus) {
                    const s = String(status || 'idle').toLowerCase();
                    const labels = {
                        win: 'Win',
                        loss: 'Loss',
                        partial_loss: 'Partial payout',
                        break_even: 'Break even',
                    };
                    els.roundStatus.dataset.status = s;
                    els.roundStatus.textContent = labels[s] || (s.charAt(0).toUpperCase() + s.slice(1));
                }
            }
            function setLastWin(amount, meta) {
                if (els.lastWin)   els.lastWin.textContent   = formatMoney(amount);
                if (els.winAmount) els.winAmount.textContent = formatMoney(amount);
                if (els.lastWinMeta) els.lastWinMeta.textContent = meta || '';
            }
            function renderTransactions(tx) {
                if (!els.transactions) return;
                if (!tx || (tx.bet == null && tx.win == null)) {
                    els.transactions.innerHTML =
                        '<div class="bw-tx-row"><span class="l">No transactions yet</span><span class="v"></span></div>';
                    return;
                }
                const escape = (s) => String(s)
                    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
                const rows = [];
                if (tx.bet != null) {
                    rows.push('<div class="bw-tx-row is-debit"><span class="l">Bet</span><span class="v" title="' +
                        escape(tx.bet) + '">' + escape(shortenId(tx.bet)) + '</span></div>');
                }
                if (tx.win != null) {
                    rows.push('<div class="bw-tx-row is-credit"><span class="l">Win</span><span class="v" title="' +
                        escape(tx.win) + '">' + escape(shortenId(tx.win)) + '</span></div>');
                }
                els.transactions.innerHTML = rows.join('');
            }
            function renderBetPresets() {
                if (!els.betGrid) return;
                els.betGrid.innerHTML = '';
                if (!betValues.length) {
                    els.betGrid.classList.add('is-empty');
                    return;
                }
                els.betGrid.classList.remove('is-empty');
                const frag = document.createDocumentFragment();
                betValues.forEach((v) => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'bet-button';
                    btn.dataset.value = String(v);
                    btn.textContent = formatMoney(v);
                    btn.addEventListener('click', () => { if (!state.busy) setBet(v); });
                    frag.appendChild(btn);
                });
                els.betGrid.appendChild(frag);
            }
            function highlightActivePreset() {
                if (!els.betGrid) return;
                els.betGrid.querySelectorAll('.bet-button').forEach((b) => {
                    const v = Number(b.dataset.value);
                    b.classList.toggle('is-active', isFinite(v) && Math.abs(v - state.bet) < 1e-9);
                });
            }

            // =================================================================
            // ADAPTER BOUNDARY
            // =================================================================
            function makeRequestKey() {
                if (window.crypto && typeof crypto.randomUUID === 'function') return crypto.randomUUID();
                return 'bw-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 10);
            }

            function createSpinRequest(currentState) {
                return {
                    sessionToken: payload.sessionToken || '',
                    betAmount: Number(currentState.bet) || 0,
                    requestKey: makeRequestKey(),
                };
            }

            async function callSpinAdapter(request) {return window.riseSlotSession.spin(request.betAmount);}

            function applySpinResponse(envelope) {
                const data = (envelope && envelope.data) || {};
                const balance   = data.balance;
                const roundId   = data.roundId;
                const betAmount = data.betAmount;
                const winAmount = Number(data.winAmount || 0);
                const effectiveBetAmount = Number(betAmount != null ? betAmount : state.bet);
                const netAmount = winAmount - (isFinite(effectiveBetAmount) ? effectiveBetAmount : 0);
                const tx        = data.transactions || {};
                const result    = data.result || {};
                const grid      = result.grid;
                const wlines    = result.winningLines;

                stopSpinAnimation(grid);

                if (balance != null) setBalance(balance,data.balanceExact);
                if (betAmount != null && els.betAmount) els.betAmount.textContent = formatMoney(betAmount);

                const displayStatus = netAmount > 0
                    ? 'win'
                    : (winAmount > 0 && Math.abs(netAmount) < 0.005 ? 'break_even' : (winAmount > 0 ? 'partial_loss' : 'loss'));

                state.lastRoundId = roundId || state.lastRoundId;
                setRound(roundId, displayStatus);
                renderTransactions(tx);

                const winCells = extractWinningCells(wlines);
                if (winCells.length) paintWinningLines(wlines);

                if (netAmount > 0) {
                    setLastWin(winAmount, roundId ? ('Round ' + shortenId(roundId)) : '');
                    setMessage('Net win ' + formatMoney(netAmount) + ' (payout ' + formatMoney(winAmount) + ').', 'win');
                    setHint('Spin again');

                    const ratio = state.bet > 0 ? winAmount / state.bet : 1;
                    const intensity = Math.min(34, 14 + Math.round(ratio * 4));

                    setGlowHalo(true);
                    animateWinBannerValue(winAmount);
                    pulseCabinet();
                    fireCoinShower(intensity);
                    fireSparks(winCells);
                    flashMultiplier(ratio);
                    setMascotCelebrating(true);
                    playSound(audioWin);

                    const holdMs = state.reduceMotion ? 1400 : 2800;
                    setTimeout(() => {
                        hideWinBanner();
                        setGlowHalo(false);
                        setMascotCelebrating(false);
                        flashMultiplier(0);
                    }, holdMs);
                } else if (winAmount > 0) {
                    setLastWin(winAmount, roundId ? ('Round ' + shortenId(roundId)) : '');
                    if (Math.abs(netAmount) < 0.005) {
                        setMessage('Break even: stake returned.', 'info');
                        setHint('Tap to spin');
                    } else {
                        setMessage('Partial payout ' + formatMoney(winAmount) + ' - net loss ' + formatMoney(Math.abs(netAmount)) + '.', 'loss');
                        setHint('Tap to spin');
                    }
                } else {
                    setLastWin(0, roundId ? ('Round ' + shortenId(roundId)) : '');
                    setMessage('No win this round.', 'loss');
                    setHint('Tap to spin');
                }
            }

            // ---------- Spin orchestration ----------
            async function spin() {
                if (state.busy) return;

                setBusy(true);
                hideWinBanner();
                clearWinningLines();
                setGlowHalo(false);
                setMascotCelebrating(false);
                flashMultiplier(0);
                setMessage('Spinning…', 'info');
                setHint('Good luck');
                setRound(state.lastRoundId, 'spinning');
                playSound(audioSpin);
                startSpinAnimation();

                const request = createSpinRequest(state);
                const minSpinMs = state.reduceMotion ? 220 : 1000;
                const tStart = performance.now();

                let envelope = null, errMessage = '';
                try {
                    envelope = await callSpinAdapter(request);
                    applyImmediateWalletUpdate(envelope);
                } catch (e) {
                    errMessage = (e && e.message) || 'Network error';
                }

                const elapsed = performance.now() - tStart;
                if (elapsed < minSpinMs) await new Promise(r => setTimeout(r, minSpinMs - elapsed));
                stopSound(audioSpin);

                if (!envelope || !envelope.data) {
                    stopSpinAnimation(state.grid);
                    setBusy(false);
                    setMessage(errMessage || 'Spin failed. Try again.', 'error');
                    setRound(state.lastRoundId, 'error');
                    setHint('Tap to retry');
                    return;
                }

                applySpinResponse(envelope);
                setBusy(false);
            }

            // ---------- Wiring ----------
            function wire() {
                applyMode();
                setBalance(state.balance,payload.balanceExact);
                renderBetPresets();
                setBet(state.bet);
                setRound(null, 'idle');
                renderTransactions(null);
                setMessage('Set your bet and tap SPIN', 'info');
                setHint('Tap to spin');

                if (els.betDown)    els.betDown.addEventListener('click',    () => { if (!state.busy) nextBet(-1); });
                if (els.betUp)      els.betUp.addEventListener('click',      () => { if (!state.busy) nextBet(+1); });
                if (els.spinButton) els.spinButton.addEventListener('click', spin);

                document.addEventListener('keydown', (e) => {
                    if (e.target && /^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName)) return;
                    if (e.code === 'Space' || e.code === 'Enter') {
                        e.preventDefault(); spin();
                    } else if (e.code === 'ArrowUp' || e.code === 'ArrowRight') {
                        e.preventDefault(); if (!state.busy) nextBet(+1);
                    } else if (e.code === 'ArrowDown' || e.code === 'ArrowLeft') {
                        e.preventDefault(); if (!state.busy) nextBet(-1);
                    }
                });

                window.addEventListener('resize', () => {
                    if (!state.spinningOffsets) renderCanvas();
                }, { passive: true });

                if (reduceMotionMQ) {
                    const onChange = (e) => { state.reduceMotion = e.matches; };
                    if (reduceMotionMQ.addEventListener) reduceMotionMQ.addEventListener('change', onChange);
                    else if (reduceMotionMQ.addListener) reduceMotionMQ.addListener(onChange);
                }
            }

            // ---------- Boot ----------
            initAudio();
            preloadImages();
            wire();
            renderCanvas();
            startIdleLoop();
            setBusy(true);
            window.riseSlotSession.restore(data=>applySpinResponse({status:200,data})).catch(error=>setMessage(error.message,'error')).finally(()=>setBusy(false));

            window.BigWin = {
                createSpinRequest: createSpinRequest,
                callSpinAdapter:   callSpinAdapter,
                applySpinResponse: applySpinResponse,
                _assetBase:        ASSET_BASE,
            };
        })();
    </script>
@endif
</body>
</html>
