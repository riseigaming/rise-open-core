<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $payload['gameName'] ?? 'European Roulette' }}</title>
    <style>
        :root {
            --bg: #071310;
            --shell: rgba(11, 23, 18, 0.92);
            --panel: rgba(18, 34, 28, 0.94);
            --panel-soft: rgba(21, 38, 32, 0.9);
            --felt: #0f5a39;
            --felt-deep: #0a432a;
            --wood: #5e2f19;
            --wood-deep: #2c140a;
            --gold: #dec27a;
            --gold-strong: #f7d786;
            --line: rgba(241, 231, 198, 0.18);
            --text: #f8f2e3;
            --muted: #c8bea9;
            --red: #c73f2f;
            --black: #182018;
            --green: #165f3f;
            --warning: #f2b75e;
            --success: #7fe0ae;
            --danger: #ff8f81;
            --shadow: 0 28px 90px rgba(0, 0, 0, 0.36);
        }

        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; }
        body {
            background:
                radial-gradient(circle at top, rgba(233, 196, 108, 0.12), transparent 24%),
                radial-gradient(circle at 20% 18%, rgba(106, 188, 146, 0.1), transparent 18%),
                linear-gradient(180deg, #102019 0%, #050a08 100%);
            color: var(--text);
            font-family: "Trebuchet MS", "Gill Sans", sans-serif;
            padding: 14px;
        }

        button, input { font: inherit; }

        .roulette-shell {
            width: min(1400px, 100%);
            margin: 0 auto;
            border-radius: 28px;
            border: 1px solid rgba(255,255,255,0.06);
            background:
                linear-gradient(180deg, rgba(255,255,255,0.03), rgba(255,255,255,0.01)),
                var(--shell);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .roulette-layout {
            display: grid;
            grid-template-columns: minmax(0, 1.75fr) minmax(340px, 0.9fr);
            gap: 14px;
            padding: 14px;
            align-items: start;
        }

        .panel {
            border-radius: 24px;
            background:
                linear-gradient(180deg, rgba(255,255,255,0.03), rgba(255,255,255,0.01)),
                var(--panel);
            border: 1px solid rgba(255,255,255,0.06);
        }

        .table-panel {
            padding: 16px;
            background:
                radial-gradient(circle at top, rgba(224, 193, 121, 0.12), transparent 22%),
                linear-gradient(180deg, rgba(17, 31, 24, 0.96), rgba(10, 20, 15, 0.98));
            display: grid;
            gap: 14px;
        }

        .topbar {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 10px 14px;
            align-items: end;
            padding: 14px 16px;
            border-radius: 20px;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.06);
        }

        .headline h1 {
            margin: 0;
            font-size: clamp(26px, 3vw, 38px);
            line-height: 1;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            font-family: "Palatino Linotype", "Book Antiqua", serif;
        }

        .headline p {
            margin: 6px 0 0;
            color: var(--muted);
            font-size: 13px;
        }

        .meta-strip {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-content: flex-start;
            justify-content: flex-end;
        }

        .meta-pill {
            border-radius: 999px;
            padding: 9px 12px;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.07);
            font-size: 12px;
            color: var(--muted);
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .meta-pill strong {
            color: var(--text);
            margin-left: 6px;
            font-weight: 700;
        }

        .history-strip {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            padding-bottom: 4px;
        }

        .history-pill {
            flex: 0 0 auto;
            min-width: 56px;
            text-align: center;
            border-radius: 14px;
            padding: 10px 12px;
            border: 1px solid rgba(255,255,255,0.08);
            background: rgba(255,255,255,0.04);
            font-weight: 800;
            font-size: 16px;
        }

        .history-pill.is-red { background: rgba(199,63,47,0.18); }
        .history-pill.is-black { background: rgba(24,32,24,0.78); }
        .history-pill.is-green { background: rgba(22,95,63,0.9); }
        .history-pill.is-fresh {
            animation: roulette-pill-pop .5s ease;
            box-shadow: 0 0 0 2px rgba(247, 215, 134, 0.28);
        }

        .table-wrap {
            overflow-x: auto;
            max-width: 100%;
            padding-bottom: 6px;
            -webkit-overflow-scrolling: touch;
            overscroll-behavior-x: contain;
            scroll-snap-type: x proximity;
        }

        .table-wrap::-webkit-scrollbar {
            height: 10px;
        }

        .table-wrap::-webkit-scrollbar-thumb {
            background: rgba(247, 215, 134, 0.28);
            border-radius: 999px;
        }

        .table-stage {
            position: relative;
            padding: 14px;
            border-radius: 22px;
            background: rgba(0,0,0,0.15);
            border: 1px solid rgba(255,255,255,0.05);
        }

        .roulette-table {
            min-width: 980px;
            display: grid;
            grid-template-columns: 88px minmax(0, 1fr) 76px;
            grid-template-rows: 246px 68px 68px;
            gap: 10px;
            align-items: stretch;
        }

        .zero-zone,
        .number-grid,
        .column-grid,
        .dozen-grid,
        .outside-grid {
            position: relative;
            border-radius: 20px;
            overflow: hidden;
        }

        .zero-zone,
        .number-grid,
        .column-grid,
        .dozen-grid,
        .outside-grid {
            background:
                linear-gradient(180deg, rgba(255,255,255,0.03), rgba(255,255,255,0.01)),
                var(--felt);
            border: 1px solid rgba(242, 232, 200, 0.18);
            box-shadow: inset 0 0 0 1px rgba(255,255,255,0.03);
        }

        .zero-zone {
            grid-row: 1 / span 1;
            padding: 8px;
            background:
                linear-gradient(180deg, rgba(255,255,255,0.04), rgba(255,255,255,0.01)),
                var(--felt-deep);
        }

        .zero-button {
            width: 100%;
            height: 100%;
            border: 0;
            border-radius: 16px;
            cursor: pointer;
            background: linear-gradient(180deg, #18724a, #0f4f33);
            color: #fff;
            font-size: 34px;
            font-weight: 800;
            letter-spacing: 0.04em;
            position: relative;
            box-shadow: inset 0 0 0 1px rgba(255,255,255,0.06);
        }

        .number-grid {
            grid-column: 2;
            grid-row: 1;
            display: grid;
            grid-template-columns: repeat(12, minmax(0, 1fr));
            grid-template-rows: repeat(3, minmax(0, 1fr));
            gap: 2px;
            padding: 8px;
            background:
                linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0.01)),
                #0c442c;
        }

        .number-cell,
        .outside-button,
        .column-button,
        .dozen-button,
        .inside-hotspot {
            position: relative;
            border: 0;
            cursor: pointer;
            color: #fff;
            overflow: hidden;
        }

        .number-cell {
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            font-weight: 800;
            letter-spacing: 0.04em;
            box-shadow: inset 0 0 0 1px rgba(255,255,255,0.06);
            transition: transform .14s ease, box-shadow .14s ease, filter .14s ease;
        }

        .zero-button,
        .number-cell,
        .outside-button,
        .column-button,
        .dozen-button,
        .inside-hotspot,
        .chip-button,
        .mode-button,
        .slip-btn,
        .action-btn {
            transition: transform .14s ease, box-shadow .14s ease, filter .14s ease, opacity .14s ease, border-color .14s ease;
            touch-action: manipulation;
        }

        .number-cell.is-red { background: linear-gradient(180deg, #cf4b39, #93261b); }
        .number-cell.is-black { background: linear-gradient(180deg, #27332a, #111713); }
        .number-cell.is-green { background: linear-gradient(180deg, #177048, #0f4f33); }

        .number-cell.is-winning,
        .outside-button.is-winning,
        .column-button.is-winning,
        .dozen-button.is-winning {
            box-shadow: 0 0 0 2px rgba(247, 215, 134, 0.55), 0 0 22px rgba(247, 215, 134, 0.3);
            filter: brightness(1.1);
        }

        .number-cell:hover,
        .outside-button:hover,
        .column-button:hover,
        .dozen-button:hover,
        .inside-hotspot:hover {
            transform: translateY(-1px);
        }

        .number-grid.is-inside-mode .number-cell,
        .number-grid.is-inside-mode + .column-grid .column-button {
            filter: saturate(.8) brightness(.85);
        }

        .column-grid {
            grid-column: 3;
            grid-row: 1;
            display: grid;
            grid-template-rows: repeat(3, minmax(0, 1fr));
            gap: 2px;
            padding: 8px;
        }

        .column-button,
        .dozen-button,
        .outside-button {
            border-radius: 14px;
            background: linear-gradient(180deg, #134b31, #0d3925);
            color: var(--text);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-weight: 800;
            box-shadow: inset 0 0 0 1px rgba(255,255,255,0.05);
        }

        .column-button { font-size: 16px; }

        .dozen-grid {
            grid-column: 2 / span 2;
            grid-row: 2;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 2px;
            padding: 8px;
        }

        .outside-grid {
            grid-column: 1 / span 3;
            grid-row: 3;
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 2px;
            padding: 8px;
        }

        .outside-button[data-tone="red"] { background: linear-gradient(180deg, #cf4b39, #93261b); }
        .outside-button[data-tone="black"] { background: linear-gradient(180deg, #27332a, #111713); }

        .number-grid.is-locked,
        .zero-zone.is-locked,
        .column-grid.is-locked,
        .dozen-grid.is-locked,
        .outside-grid.is-locked {
            opacity: .62;
            pointer-events: none;
        }

        .inside-overlay {
            position: absolute;
            inset: 8px;
            pointer-events: none;
            z-index: 3;
        }

        .inside-overlay.is-active {
            pointer-events: auto;
        }

        .inside-hotspot {
            position: absolute;
            border-radius: 14px;
            border: 1px solid transparent;
            background: transparent;
            color: transparent;
            font-size: 11px;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 4px;
            box-shadow: none;
            z-index: 4;
        }

        .number-cell.is-selected,
        .zero-button.is-selected,
        .outside-button.is-selected,
        .column-button.is-selected,
        .dozen-button.is-selected {
            box-shadow: 0 0 0 2px rgba(247, 215, 134, 0.52), 0 0 16px rgba(247, 215, 134, 0.18);
        }

        .inside-hotspot.is-selected {
            box-shadow: none;
        }

        .bet-marker {
            position: absolute;
            right: 6px;
            bottom: 6px;
            min-width: 28px;
            padding: 4px 6px;
            border-radius: 999px;
            background: rgba(10, 10, 10, 0.85);
            color: var(--gold-strong);
            font-size: 11px;
            line-height: 1;
            font-weight: 800;
            letter-spacing: 0.03em;
            box-shadow: 0 8px 18px rgba(0,0,0,0.22);
            display: none;
            justify-content: center;
            align-items: center;
            max-width: 52px;
            text-overflow: ellipsis;
            overflow: hidden;
        }

        .bet-marker.is-visible { display: inline-flex; }

        .inside-hotspot .bet-marker {
            right: 50%;
            bottom: 50%;
            transform: translate(50%, 50%);
            min-width: 26px;
            padding: 3px 5px;
            font-size: 10px;
            background: rgba(8, 10, 8, 0.9);
        }

        .outside-button .bet-marker,
        .column-button .bet-marker,
        .dozen-button .bet-marker {
            left: 8px;
            right: auto;
            bottom: 8px;
        }

        .side-stack {
            display: grid;
            gap: 12px;
            align-content: start;
            position: sticky;
            top: 14px;
        }

        .wheel-card,
        .control-card,
        .slip-card,
        .result-card {
            padding: 14px;
        }

        .card-title {
            margin: 0 0 12px;
            font-size: 12px;
            color: var(--muted);
            letter-spacing: 0.14em;
            text-transform: uppercase;
        }

        .card-title-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 12px;
        }

        .card-title-row .card-title {
            margin: 0;
        }

        .card-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 34px;
            padding: 6px 10px;
            border-radius: 999px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.08);
            color: var(--text);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .wheel-box {
            display: grid;
            justify-items: center;
            gap: 12px;
        }

        .wheel {
            --rotation: 0deg;
            width: min(220px, 100%);
            aspect-ratio: 1;
            border-radius: 50%;
            padding: 0;
            background: transparent;
            border: 0;
            box-shadow: 0 18px 36px rgba(0,0,0,0.22);
            position: relative;
        }

        .wheel-disc {
            --rotation: 0deg;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            position: relative;
            transition: transform 4.2s cubic-bezier(.12,.82,.18,1);
            transform: rotate(var(--rotation));
            transform-origin: 50% 50%;
            background:
                radial-gradient(circle at center, #5a5a5a 0 19%, #2f2f2f 19% 27%, #101010 27% 39%, #6d5820 39% 41%, #1e1e1e 41% 48%, #0a0a0a 48% 100%);
            overflow: hidden;
            will-change: transform;
            display: block;
            box-shadow:
                inset 0 0 0 2px rgba(255,255,255,0.08),
                inset 0 0 0 10px rgba(0,0,0,0.25),
                0 16px 28px rgba(0,0,0,0.28);
        }

        .wheel-disc.is-spinning {
            animation: roulette-pending-wheel 1.15s linear infinite;
            filter: saturate(1.08) brightness(1.02);
        }

        .wheel-disc::before {
            content: "";
            position: absolute;
            inset: 6px;
            border-radius: 50%;
            border: 4px solid rgba(244, 214, 132, 0.9);
            box-shadow: inset 0 0 0 1px rgba(86, 58, 8, 0.7);
            pointer-events: none;
        }

        .wheel-disc::after {
            content: "";
            position: absolute;
            inset: 24%;
            border-radius: 50%;
            background:
                radial-gradient(circle at 35% 35%, rgba(255,255,255,0.18), rgba(255,255,255,0) 42%),
                radial-gradient(circle at center, #6a6a6a 0 30%, #3f3f3f 30% 58%, #171717 58% 100%);
            border: 3px solid rgba(120,120,120,0.72);
            box-shadow: inset 0 0 0 2px rgba(20,20,20,0.46);
            z-index: 2;
            pointer-events: none;
        }

        .wheel-track {
            list-style: none;
            margin: 0;
            padding: 0;
            position: absolute;
            inset: 0;
            border-radius: 50%;
            z-index: 1;
        }

        .wheel-pocket {
            position: absolute;
            top: 0;
            left: calc(50% - 10px);
            width: 20px;
            height: 50%;
            transform-origin: 50% 100%;
            display: block;
        }

        .wheel-pocket::before {
            content: "";
            position: absolute;
            top: 9px;
            left: 0;
            width: 0;
            height: 0;
            border-left: 10px solid transparent;
            border-right: 10px solid transparent;
            border-top: 100px solid var(--pocket-color, #b02f21);
            filter: brightness(1.02);
        }

        .wheel-pocket span {
            position: absolute;
            top: 9px;
            left: 50%;
            width: 18px;
            margin-left: -9px;
            text-align: center;
            color: #f6f0df;
            font-size: 8px;
            font-weight: 800;
            line-height: 1;
            letter-spacing: 0.02em;
            transform: scale(.9, 1.25);
            text-shadow: 0 1px 2px rgba(0,0,0,0.6);
        }

        .wheel-pocket.is-red { --pocket-color: #b33a2b; }
        .wheel-pocket.is-black { --pocket-color: #121614; }
        .wheel-pocket.is-green { --pocket-color: #156641; }

        .wheel-pocket.is-red span,
        .wheel-pocket.is-green span {
            color: #fff6eb;
        }

        .wheel-ball {
            --ball-angle: 0deg;
            --ball-radius: 88;
            --ball-scale: 1;
            position: absolute;
            top: 50%;
            left: 50%;
            width: 12px;
            height: 12px;
            margin-left: -6px;
            margin-top: -6px;
            border-radius: 50%;
            background:
                radial-gradient(circle at 28% 28%, rgba(255,255,255,0.98) 0 18%, rgba(255,255,255,0.88) 18%, rgba(223,226,229,0.96) 42%, rgba(162,165,170,0.98) 72%, rgba(103,106,112,1) 100%);
            box-shadow:
                0 5px 11px rgba(0,0,0,0.32),
                inset -1px -2px 4px rgba(0,0,0,0.24),
                inset 1px 1px 3px rgba(255,255,255,0.52);
            z-index: 2;
            transform: rotate(var(--ball-angle)) translateY(calc(var(--ball-radius) * -1px)) scale(var(--ball-scale));
            transform-origin: center center;
            transition: transform .28s linear;
        }

        .wheel-ball::before {
            content: "";
            position: absolute;
            inset: 1px;
            border-radius: 50%;
            background: radial-gradient(circle at 35% 34%, rgba(255,255,255,0.95), rgba(255,255,255,0.25) 36%, rgba(255,255,255,0) 62%);
            mix-blend-mode: screen;
        }

        .wheel-ball::after {
            content: "";
            position: absolute;
            inset: auto;
            left: 22%;
            right: 22%;
            bottom: -4px;
            height: 4px;
            border-radius: 50%;
            background: radial-gradient(circle at center, rgba(0,0,0,0.32), rgba(0,0,0,0));
            filter: blur(1.5px);
            opacity: .42;
        }

        .wheel-ball.is-orbiting {
            filter: brightness(1.04) saturate(0.96);
        }

        .wheel-pointer {
            position: absolute;
            top: 4px;
            left: 50%;
            margin-left: -12px;
            width: 0;
            height: 0;
            border-left: 12px solid transparent;
            border-right: 12px solid transparent;
            border-top: 26px solid var(--gold-strong);
            filter: drop-shadow(0 8px 12px rgba(0,0,0,0.35));
            z-index: 3;
        }

        .wheel-readout {
            width: 100%;
            border-radius: 18px;
            background: rgba(0,0,0,0.18);
            border: 1px solid rgba(255,255,255,0.06);
            padding: 12px 14px;
            display: grid;
            gap: 6px;
            text-align: center;
        }

        .wheel-readout .label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.14em;
            color: var(--muted);
        }

        .wheel-readout .value {
            font-size: 30px;
            font-weight: 800;
            line-height: 1;
            font-variant-numeric: tabular-nums;
        }

        .wheel-readout .value.is-red { color: #ff8f81; }
        .wheel-readout .value.is-black { color: #f1eee8; }
        .wheel-readout .value.is-green { color: #7fe0ae; }
        .wheel-readout .value.is-neutral { color: var(--muted); }

        .chip-grid,
        .mode-grid {
            display: grid;
            gap: 10px;
        }

        .chip-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
            margin-bottom: 14px;
        }

        .chip-button {
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 18px;
            padding: 12px 10px;
            background: rgba(255,255,255,0.04);
            color: var(--text);
            cursor: pointer;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .chip-disc {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: radial-gradient(circle at center, #fff 0 28%, transparent 30%), var(--chip-color, #dcb650);
            box-shadow: inset 0 0 0 3px rgba(255,255,255,0.22);
        }

        .chip-button.is-active,
        .mode-button.is-active {
            box-shadow: 0 0 0 2px rgba(247, 215, 134, 0.48);
            border-color: rgba(247, 215, 134, 0.38);
        }

        .chip-button:hover,
        .mode-button:hover,
        .slip-btn:hover,
        .action-btn:not(:disabled):hover,
        .zero-button:hover {
            transform: translateY(-1px);
        }

        .mode-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .mode-button {
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 16px;
            padding: 12px 10px;
            background: rgba(255,255,255,0.04);
            color: var(--text);
            cursor: pointer;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .control-meta {
            margin-top: 14px;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
        }

        .readout {
            border-radius: 16px;
            padding: 12px 14px;
            background: rgba(0,0,0,0.18);
            border: 1px solid rgba(255,255,255,0.07);
        }

        .readout span {
            display: block;
            font-size: 11px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.14em;
            margin-bottom: 5px;
        }

        .readout strong {
            display: block;
            font-size: 18px;
            font-weight: 800;
            font-variant-numeric: tabular-nums;
        }

        .slip-list {
            display: grid;
            gap: 8px;
            max-height: 240px;
            overflow-y: auto;
        }

        .slip-empty {
            min-height: 88px;
            display: grid;
            place-items: center;
            text-align: center;
            color: var(--muted);
            border: 1px dashed rgba(255,255,255,0.1);
        }

        .slip-item {
            border-radius: 16px;
            padding: 12px;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            display: grid;
            gap: 6px;
        }

        .slip-head {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            align-items: center;
        }

        .slip-title {
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .slip-sub {
            font-size: 12px;
            color: var(--muted);
        }

        .slip-amount {
            font-weight: 800;
            color: var(--gold-strong);
        }

        .slip-actions {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
        }

        .slip-btn,
        .action-btn {
            border: 0;
            cursor: pointer;
            border-radius: 14px;
        }

        .slip-btn {
            padding: 8px 10px;
            background: rgba(255,255,255,0.06);
            color: var(--text);
            font-size: 12px;
            font-weight: 700;
        }

        .action-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 12px;
        }

        .action-btn {
            padding: 14px 16px;
            font-size: 14px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text);
        }

        .action-btn.is-clear { background: rgba(255,255,255,0.06); }
        .action-btn.is-spin { background: linear-gradient(135deg, #d29e35, #f7d786); color: #1b1308; }
        .action-btn.is-busy {
            filter: saturate(0.92) brightness(0.96);
        }
        .action-btn:disabled { opacity: .48; cursor: not-allowed; }

        .result-grid {
            display: grid;
            gap: 10px;
        }

        .result-card.is-pending {
            border-color: rgba(247, 215, 134, 0.2);
            box-shadow: inset 0 0 0 1px rgba(247, 215, 134, 0.08);
        }

        .result-card.is-win {
            border-color: rgba(127,224,174,0.24);
            box-shadow: inset 0 0 0 1px rgba(127,224,174,0.08);
        }

        .result-card.is-loss {
            border-color: rgba(255, 143, 129, 0.2);
            box-shadow: inset 0 0 0 1px rgba(255, 143, 129, 0.08);
        }

        .result-stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
        }

        .result-stat {
            border-radius: 14px;
            padding: 10px 12px;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.07);
        }

        .result-stat span {
            display: block;
            font-size: 11px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.12em;
            margin-bottom: 5px;
        }

        .result-stat strong {
            display: block;
            font-weight: 800;
            font-variant-numeric: tabular-nums;
        }

        .result-banner {
            border-radius: 18px;
            padding: 12px 14px;
            background: rgba(0,0,0,0.2);
            border: 1px solid rgba(255,255,255,0.07);
        }

        .result-banner strong {
            display: block;
            font-size: 14px;
            margin-bottom: 6px;
        }

        .result-banner span {
            display: block;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.45;
        }

        .line-results {
            display: grid;
            gap: 8px;
            max-height: 180px;
            overflow-y: auto;
        }

        .line-result {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 10px;
            border-radius: 14px;
            padding: 10px 12px;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.07);
        }

        .line-result .meta {
            color: var(--muted);
            font-size: 12px;
            margin-top: 4px;
        }

        .line-result .payout {
            text-align: right;
            font-weight: 800;
        }

        .line-result.is-won {
            border-color: rgba(127,224,174,0.2);
            background: rgba(127,224,174,0.08);
        }

        .line-result.is-lost {
            border-color: rgba(255,255,255,0.07);
        }

        .status-message {
            margin-top: 14px;
            min-height: 42px;
            border-radius: 16px;
            padding: 12px 14px;
            background: rgba(0,0,0,0.18);
            border: 1px solid rgba(255,255,255,0.07);
            color: var(--muted);
            font-size: 13px;
            line-height: 1.45;
        }

        .status-message.is-error {
            border-color: rgba(255, 143, 129, 0.22);
            color: #ffd3cc;
        }

        .status-message.is-busy {
            border-color: rgba(247, 215, 134, 0.22);
            color: var(--gold-strong);
        }

        .status-message.is-success {
            border-color: rgba(127,224,174,0.22);
            color: var(--success);
        }

        .table-footer-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.3fr) minmax(280px, 0.9fr);
            gap: 12px;
        }

        .table-footer-card {
            border-radius: 18px;
            padding: 12px 14px;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.07);
            min-width: 0;
        }

        .table-footer-label {
            margin-bottom: 10px;
            font-size: 11px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.14em;
        }

        .table-info-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
        }

        .table-info-item {
            border-radius: 14px;
            padding: 10px 12px;
            background: rgba(0,0,0,0.16);
            border: 1px solid rgba(255,255,255,0.06);
        }

        .table-info-item span {
            display: block;
            font-size: 11px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.12em;
            margin-bottom: 4px;
        }

        .table-info-item strong {
            display: block;
            font-weight: 800;
            font-variant-numeric: tabular-nums;
        }

        .roulette-main {
            display: grid;
            gap: 14px;
        }

        .roulette-layout {
            grid-template-columns: 1fr;
        }

        .stage-top {
            display: grid;
            grid-template-columns: 260px minmax(0, 1fr);
            gap: 14px;
            align-items: flex-start;
        }

        .stage-table {
            min-width: 0;
            display: grid;
            gap: 12px;
            position: relative;
            z-index: 1;
            order: 2;
        }

        .stage-support {
            width: 260px;
            display: grid;
            gap: 10px;
            position: relative;
            z-index: 2;
            order: 1;
        }

        .support-card {
            border-radius: 20px;
            padding: 14px;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.06);
        }

        .roulette-table {
            min-width: 1080px;
        }

        .inside-hotspot {
            border-color: transparent;
            background: transparent;
        }

        .wheel-card {
            padding: 12px;
        }

        .action-panel {
            display: grid;
            gap: 10px;
        }

        .action-panel .chip-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
            margin-bottom: 0;
        }

        .table-control-band {
            display: grid;
            grid-template-columns: minmax(320px, 1.15fr) minmax(240px, 300px) minmax(320px, 1fr);
            gap: 12px;
            align-items: start;
        }

        .table-control-card {
            border-radius: 18px;
            padding: 12px 14px;
            background: rgba(0,0,0,0.16);
            border: 1px solid rgba(255,255,255,0.06);
        }

        .table-control-card .card-title {
            margin-bottom: 10px;
        }

        .table-control-card .mode-grid {
            grid-template-columns: repeat(5, minmax(0, 1fr));
        }

        .table-control-card .table-info-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .control-actions {
            display: grid;
            gap: 10px;
            align-content: center;
        }

        .control-actions .action-btn.is-spin {
            min-height: 62px;
            font-size: 20px;
            letter-spacing: 0.1em;
        }

        .control-actions__row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }

        .control-live {
            display: grid;
            gap: 10px;
        }

        .control-live .table-info-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .secondary-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            align-items: start;
        }

        .secondary-panel {
            min-width: 0;
        }

        .history-panel__head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 12px;
        }

        .history-nav {
            display: inline-flex;
            gap: 8px;
        }

        .history-nav button {
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            padding: 8px 10px;
            background: rgba(255,255,255,0.04);
            color: var(--text);
            cursor: pointer;
        }

        .history-viewport {
            overflow: hidden;
        }

        .wheel-pocket.is-winning-pocket::before {
            filter: brightness(1.18);
            box-shadow: 0 0 0 2px rgba(247, 215, 134, 0.82), 0 0 16px rgba(247, 215, 134, 0.38);
        }

        .wheel-pocket.is-winning-pocket span {
            color: var(--gold-strong);
        }

        .table-lock-overlay {
            position: absolute;
            inset: 54px 0 0;
            border-radius: 20px;
            display: none;
            place-items: center;
            text-align: center;
            padding: 16px;
            background: rgba(6, 12, 9, 0.58);
            backdrop-filter: blur(4px);
            z-index: 6;
            pointer-events: none;
        }

        .table-lock-overlay.is-visible {
            display: grid;
        }

        .table-lock-overlay strong {
            display: block;
            font-size: 18px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 6px;
        }

        .table-lock-overlay span {
            color: var(--muted);
            font-size: 13px;
        }

        @media (max-width: 1180px) {
            .stage-top,
            .table-control-band,
            .secondary-grid {
                display: grid;
                grid-template-columns: 1fr;
            }

            .stage-support {
                width: auto;
                flex-basis: auto;
            }
        }

        @media (max-width: 768px) {
            body { padding: 8px; }
            .roulette-layout { padding: 10px; gap: 12px; }
            .table-panel,
            .wheel-card,
            .slip-card,
            .result-card,
            .support-card { padding: 14px; }
            .chip-grid,
            .mode-grid,
            .action-panel .chip-grid,
            .action-panel .mode-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .table-control-card .mode-grid,
            .table-control-card .table-info-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .control-live .table-info-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .result-stats { grid-template-columns: 1fr; }
            .table-info-grid { grid-template-columns: 1fr 1fr; }
            .inside-hotspot {
                border-width: 2px;
                background: transparent;
            }
            .bet-marker {
                min-width: 24px;
                padding: 3px 5px;
                font-size: 10px;
            }
        }

        @keyframes roulette-pending-wheel {
            from { transform: rotate(var(--rotation)); }
            to { transform: rotate(calc(var(--rotation) + 360deg)); }
        }

        @keyframes roulette-pill-pop {
            0% { transform: scale(.88); opacity: .45; }
            100% { transform: scale(1); opacity: 1; }
        }

        .rt-shell {
            --rt-bg-1: #07120f;
            --rt-bg-2: #10241a;
            --rt-panel: rgba(12, 24, 19, 0.76);
            --rt-panel-strong: rgba(9, 17, 14, 0.92);
            --rt-felt-1: #11633f;
            --rt-felt-2: #083a27;
            --rt-accent: #e6c06e;
            --rt-accent-strong: #ffde86;
            --rt-glow: rgba(230, 192, 110, 0.3);
            --rt-line: rgba(255, 255, 255, 0.09);
            --rt-safe-bottom: max(14px, env(safe-area-inset-bottom));
        }

        .rt-theme-low {
            --rt-bg-1: #08201b;
            --rt-bg-2: #123b32;
            --rt-felt-1: #178054;
            --rt-felt-2: #0d5138;
            --rt-accent: #91e6bd;
            --rt-accent-strong: #c9ffdf;
            --rt-glow: rgba(145, 230, 189, 0.28);
        }

        .rt-theme-high {
            --rt-bg-1: #100b18;
            --rt-bg-2: #25172d;
            --rt-felt-1: #54317c;
            --rt-felt-2: #241231;
            --rt-accent: #f4cb73;
            --rt-accent-strong: #fff0a8;
            --rt-glow: rgba(244, 203, 115, 0.34);
        }

        body {
            min-height: 100dvh;
            padding: 0;
            overflow-x: hidden;
            background:
                radial-gradient(circle at 50% 0%, var(--rt-glow, rgba(230,192,110,.3)), transparent 28%),
                linear-gradient(180deg, var(--rt-bg-2, #10241a), var(--rt-bg-1, #07120f) 58%, #030504);
        }

        .roulette-shell.rt-shell {
            width: 100%;
            min-height: 100dvh;
            border: 0;
            border-radius: 0;
            box-shadow: none;
            background:
                radial-gradient(circle at top, rgba(255,255,255,0.08), transparent 23%),
                linear-gradient(180deg, rgba(255,255,255,0.03), rgba(255,255,255,0)),
                var(--rt-bg-1);
        }

        .rt-layout {
            display: block;
            padding: 0;
        }

        .rt-surface {
            min-height: 100dvh;
            padding: 10px 10px calc(176px + var(--rt-safe-bottom));
            border: 0;
            border-radius: 0;
            background:
                radial-gradient(circle at 50% 12%, rgba(255,255,255,0.08), transparent 24%),
                linear-gradient(180deg, rgba(255,255,255,0.03), transparent 32%),
                transparent;
            gap: 10px;
        }

        .rt-hud {
            position: sticky;
            top: 0;
            z-index: 30;
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 9px;
            padding: calc(8px + env(safe-area-inset-top)) 10px 10px;
            border-radius: 0 0 22px 22px;
            background: linear-gradient(180deg, rgba(5, 10, 8, 0.96), rgba(8, 16, 12, 0.78));
            border: 1px solid var(--rt-line);
            border-top: 0;
            backdrop-filter: blur(12px);
            box-shadow: 0 16px 34px rgba(0,0,0,0.28);
        }

        .rt-hud .headline h1 {
            font-size: 18px;
            letter-spacing: 0;
            font-family: inherit;
        }

        .rt-hud .headline p {
            margin-top: 3px;
            font-size: 11px;
        }

        .rt-hud .meta-strip {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 6px;
        }

        .rt-hud .meta-pill {
            min-width: 0;
            padding: 8px 9px;
            border-radius: 14px;
            font-size: 9px;
            letter-spacing: 0;
            background: rgba(255,255,255,0.055);
        }

        .rt-hud .meta-pill strong {
            display: block;
            margin: 2px 0 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .rt-recent {
            display: grid;
            grid-template-columns: 28px minmax(0, 1fr) 28px;
            gap: 6px;
            align-items: center;
        }

        .rt-recent button,
        .rt-drawer-toggle {
            min-height: 34px;
            border: 1px solid var(--rt-line);
            border-radius: 12px;
            background: rgba(255,255,255,0.06);
            color: var(--text);
            cursor: pointer;
        }

        .rt-recent .history-viewport {
            overflow: hidden;
        }

        .rt-recent .history-strip {
            padding: 0;
        }

        .rt-recent .history-pill {
            min-width: 36px;
            padding: 7px 9px;
            border-radius: 12px;
            font-size: 13px;
        }

        .rt-stage-grid {
            display: grid;
            gap: 10px;
        }

        .rt-wheel-stage {
            position: relative;
            overflow: hidden;
            display: grid;
            grid-template-columns: 152px minmax(0, 1fr);
            gap: 10px;
            align-items: center;
            padding: 12px;
            border-radius: 22px;
            background:
                radial-gradient(circle at 22% 18%, var(--rt-glow), transparent 36%),
                linear-gradient(135deg, rgba(255,255,255,0.08), rgba(255,255,255,0.02)),
                var(--rt-panel);
            border: 1px solid var(--rt-line);
            box-shadow: inset 0 0 0 1px rgba(255,255,255,0.03), 0 18px 40px rgba(0,0,0,0.22);
        }

        .rt-wheel-stage .wheel {
            width: 148px;
        }

        .rt-wheel-stage .wheel-readout {
            padding: 9px;
            border-color: rgba(255,255,255,0.08);
            background: rgba(0,0,0,0.22);
        }

        .rt-result-banner {
            min-width: 0;
            border-radius: 20px;
            padding: 12px;
            background: rgba(0,0,0,0.24);
            border: 1px solid rgba(255,255,255,0.08);
        }

        .rt-result-banner .result-banner {
            padding: 0;
            border: 0;
            background: transparent;
        }

        .rt-result-banner strong {
            font-size: 15px;
        }

        .rt-result-stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 6px;
            margin-top: 10px;
        }

        .rt-result-stats .table-info-item {
            padding: 8px;
            border-radius: 12px;
        }

        .rt-result-stats .table-info-item span {
            letter-spacing: 0;
            font-size: 9px;
        }

        .rt-betting-area {
            min-width: 0;
            order: 2;
        }

        .rt-betting-area .table-stage {
            padding: 8px;
            border-radius: 22px;
            background:
                radial-gradient(circle at 50% 0, rgba(255,255,255,0.08), transparent 26%),
                linear-gradient(180deg, var(--rt-felt-1), var(--rt-felt-2));
            border-color: rgba(255,255,255,0.12);
        }

        .rt-betting-area .table-wrap {
            overflow: hidden;
            padding: 0;
        }

        .rt-betting-area .roulette-table {
            min-width: 0 !important;
            width: 100%;
            grid-template-columns: 38px minmax(0, 1fr) 28px;
            grid-template-rows: 156px 42px 42px;
            gap: 4px;
        }

        .rt-betting-area .zero-zone,
        .rt-betting-area .number-grid,
        .rt-betting-area .column-grid,
        .rt-betting-area .dozen-grid,
        .rt-betting-area .outside-grid {
            border-radius: 14px;
            padding: 4px;
        }

        .rt-betting-area .number-grid {
            gap: 1px;
        }

        .rt-betting-area .number-cell {
            min-width: 0;
            border-radius: 6px;
            font-size: 11px;
            letter-spacing: 0;
        }

        .rt-betting-area .zero-button {
            border-radius: 10px;
            font-size: 22px;
        }

        .rt-betting-area .column-button,
        .rt-betting-area .dozen-button,
        .rt-betting-area .outside-button {
            border-radius: 9px;
            font-size: 8px;
            letter-spacing: 0;
            line-height: 1.05;
            padding: 2px;
        }

        .rt-betting-area .inside-hotspot {
            border-radius: 8px;
            border-width: 2px;
            background: transparent;
        }

        .rt-betting-area .bet-marker {
            right: 2px;
            bottom: 2px;
            min-width: 18px;
            max-width: 30px;
            padding: 2px 3px;
            font-size: 8px;
        }

        .rt-action-dock {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 40;
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 8px;
            padding: 10px 10px var(--rt-safe-bottom);
            border-radius: 24px 24px 0 0;
            background: linear-gradient(180deg, rgba(10, 20, 16, 0.92), rgba(4, 8, 7, 0.98));
            border: 1px solid var(--rt-line);
            border-bottom: 0;
            box-shadow: 0 -18px 42px rgba(0,0,0,0.34);
            backdrop-filter: blur(14px);
        }

        .rt-action-dock .table-control-card {
            padding: 0;
            border: 0;
            background: transparent;
        }

        .rt-action-dock .card-title {
            display: none;
        }

        .rt-action-dock .chip-grid {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            padding-bottom: 2px;
            margin: 0;
        }

        .rt-action-dock .chip-button {
            flex: 0 0 auto;
            min-width: 72px;
            min-height: 44px;
            padding: 8px 10px;
            border-radius: 999px;
            background: rgba(255,255,255,0.075);
        }

        .rt-action-dock .mode-grid {
            display: flex;
            gap: 7px;
            overflow-x: auto;
        }

        .rt-action-dock .mode-button {
            flex: 0 0 auto;
            min-height: 34px;
            padding: 7px 10px;
            border-radius: 999px;
            font-size: 10px;
            letter-spacing: 0;
        }

        .rt-action-dock .control-actions {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 1.6fr;
            gap: 8px;
        }

        .rt-action-dock .control-actions__row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }

        .rt-action-dock .action-btn {
            min-height: 48px;
            padding: 10px 12px;
            border-radius: 16px;
        }

        .rt-action-dock .action-btn.is-spin {
            min-height: 48px;
            font-size: 17px;
            background: linear-gradient(135deg, var(--rt-accent), var(--rt-accent-strong));
        }

        .rt-action-dock .control-live {
            display: none;
        }

        .rt-action-dock .table-info-item {
            display: none;
        }

        .rt-drawer-toggle {
            position: fixed;
            right: 12px;
            bottom: calc(164px + var(--rt-safe-bottom));
            z-index: 60;
            padding: 8px 12px;
            color: var(--text);
            background: rgba(10, 18, 15, 0.9);
            backdrop-filter: blur(10px);
        }

        .rt-drawer {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 50;
            display: none;
            max-height: min(72dvh, 620px);
            overflow-y: auto;
            grid-template-columns: 1fr;
            gap: 10px;
            padding: 14px 12px calc(18px + var(--rt-safe-bottom));
            border-radius: 26px 26px 0 0;
            background: linear-gradient(180deg, rgba(17, 31, 25, 0.98), rgba(5, 9, 8, 0.99));
            border: 1px solid var(--rt-line);
            border-bottom: 0;
            box-shadow: 0 -24px 60px rgba(0,0,0,0.46);
        }

        .rt-drawer.is-open {
            display: grid;
            animation: rt-drawer-in .18s ease-out;
        }

        .rt-drawer .support-card {
            padding: 12px;
            border-radius: 18px;
            background: rgba(255,255,255,0.045);
        }

        .rt-drawer .slip-list,
        .rt-drawer .line-results {
            max-height: 220px;
        }

        .rt-status {
            margin-top: 0;
            border-color: rgba(255,255,255,0.08);
            background: rgba(0,0,0,0.2);
        }

        @media (min-width: 720px) {
            .rt-surface {
                max-width: 1180px;
                margin: 0 auto;
                padding-left: 16px;
                padding-right: 16px;
            }

            .rt-hud {
                grid-template-columns: 1fr 1.2fr;
                align-items: center;
                border-radius: 0 0 28px 28px;
            }

            .rt-recent {
                grid-column: 1 / -1;
            }

            .rt-wheel-stage {
                grid-template-columns: 230px minmax(0, 1fr);
            }

            .rt-wheel-stage .wheel {
                width: 220px;
            }

            .rt-betting-area .roulette-table {
                grid-template-columns: 72px minmax(0, 1fr) 52px;
                grid-template-rows: 226px 58px 58px;
                gap: 6px;
            }

            .rt-betting-area .number-cell {
                font-size: 18px;
                border-radius: 10px;
            }

            .rt-betting-area .zero-button {
                font-size: 30px;
            }

            .rt-betting-area .column-button,
            .rt-betting-area .dozen-button,
            .rt-betting-area .outside-button {
                font-size: 12px;
            }

            .rt-action-dock {
                left: 50%;
                right: auto;
                width: min(980px, calc(100% - 28px));
                transform: translateX(-50%);
                border-radius: 26px 26px 0 0;
            }
        }

        @media (max-width: 719px) {
            .rt-surface {
                padding-bottom: calc(148px + var(--rt-safe-bottom));
            }

            .rt-hud {
                gap: 7px;
                padding-bottom: 8px;
            }

            .rt-hud .meta-strip {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }

            .rt-hud .meta-pill {
                padding: 7px 6px;
                font-size: 8px;
            }

            .rt-wheel-stage {
                grid-template-columns: 118px minmax(0, 1fr);
                gap: 8px;
                padding: 10px;
            }

            .rt-wheel-stage .card-title-row {
                grid-column: 1 / -1;
                margin-bottom: 0;
            }

            .rt-wheel-stage .wheel {
                width: 112px;
            }

            .rt-wheel-stage .wheel-readout {
                display: none;
            }

            .rt-result-banner {
                padding: 10px;
            }

            .rt-result-banner .card-title-row {
                display: flex;
            }

            .rt-result-stats {
                grid-template-columns: repeat(3, minmax(0, 1fr));
                margin-top: 8px;
            }

            .rt-result-stats .table-info-item:nth-child(n+4) {
                display: none;
            }

            .rt-action-dock {
                gap: 7px;
                padding-top: 8px;
            }

            .rt-action-dock .chip-button {
                min-width: 66px;
                min-height: 40px;
            }

            .rt-action-dock .mode-button {
                min-height: 32px;
                padding: 6px 10px;
            }

            .rt-action-dock .action-btn,
            .rt-action-dock .action-btn.is-spin {
                min-height: 44px;
            }

            .rt-drawer-toggle {
                bottom: calc(136px + var(--rt-safe-bottom));
            }
        }

        @media (min-width: 1040px) {
            .rt-stage-grid {
                grid-template-columns: minmax(280px, .62fr) minmax(0, 1fr);
                align-items: start;
            }

            .rt-wheel-stage {
                grid-template-columns: 1fr;
                position: sticky;
                top: 112px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                animation-duration: 0.001ms !important;
                animation-iteration-count: 1 !important;
                scroll-behavior: auto !important;
                transition-duration: 0.001ms !important;
            }
        }

        @keyframes rt-drawer-in {
            from { opacity: 0; transform: translateY(18px); }
            to { opacity: 1; transform: translateY(0); }
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
<nav style="padding:12px"><a style="color:inherit" href="{{ route('cms.games.index') }}">← Global catalog</a> · <a style="color:inherit" href="{{ route('cms.wallet.index') }}">Wallet / transactions</a><p data-finance-mode>{{ $guestPreview ? __('catalog.guest_preview') : ($payload['isDemo'] ? 'Demo · virtual credits' : 'Real wallet · local synthetic funds') }}</p></nav>
@php
    $chipColors = [
        1 => '#d78640',
        5 => '#2a9b5a',
        10 => '#3f7de8',
        25 => '#222529',
        50 => '#8953d8',
        100 => '#d5ac34',
        250 => '#b14662',
        500 => '#6438c9',
        1000 => '#f0d06c',
    ];
    $redWheelNumbers = [1,3,5,7,9,12,14,16,18,19,21,23,25,27,30,32,34,36];
    $rtGameCode = (string) ($payload['gameCode'] ?? 'roulette-european');
    $rtTheme = str_contains($rtGameCode, 'low-limit')
        ? 'low'
        : (str_contains($rtGameCode, 'high-limit') ? 'high' : 'standard');
    $rtTierLabel = $payload['variantLabel']
        ?? ($rtTheme === 'high' ? 'High Limit' : ($rtTheme === 'low' ? 'Low Limit' : 'Standard'));
@endphp
<div
    class="roulette-shell rt-shell rt-theme-{{ $rtTheme }}"
    data-roulette-surface
    data-rt-theme="{{ $rtTheme }}"
    data-session-token="{{ $payload['sessionToken'] }}"
>
    <main class="roulette-layout rt-layout">
        <section class="panel table-panel roulette-main rt-surface">
            <header class="topbar rt-hud" data-rt-hud>
                <div class="headline">
                    <h1>{{ $payload['gameName'] ?? 'European Roulette' }}</h1>
                    <p>{{ trans('cms_public.games.roulette.table_label', ['tier' => $rtTierLabel, 'currency' => $payload['currency']]) }}</p>
                </div>
                <div class="meta-strip">
                    <div class="meta-pill">{{ trans('cms_public.games.common.balance') }} <strong data-roulette-balance>{{ $guestPreview ? '—' : number_format((float) $payload['balance'], 2) }} {{ $payload['currency'] }}</strong></div>
                    <div class="meta-pill">{{ trans('cms_public.games.common.stake') }} <strong data-roulette-total-stake>0.00 {{ $payload['currency'] }}</strong></div>
                    <div class="meta-pill">{{ trans('cms_public.games.roulette.tier') }} <strong>{{ $rtTierLabel }}</strong></div>
                    <div class="meta-pill">{{ trans('cms_public.games.roulette.limits') }} <strong>{{ number_format((float) data_get($payload, 'tableLimits.minBet', 1), 2) }} - {{ number_format((float) data_get($payload, 'tableLimits.maxBet', 1000), 2) }} {{ $payload['currency'] }}</strong></div>
                </div>
                <div class="rt-recent" data-rt-recent-strip>
                    <button type="button" data-history-prev aria-label="{{ trans('cms_public.games.roulette.previous_recent_number') }}">&larr;</button>
                    <div class="history-viewport" data-roulette-history-viewport>
                        <div class="history-strip" data-roulette-history>
                            @forelse(($payload['recentResults'] ?? []) as $entry)
                                <div class="history-pill is-{{ $entry['color'] }}" data-number="{{ $entry['number'] }}">{{ $entry['number'] }}</div>
                            @empty
                                <div class="history-pill">-</div>
                            @endforelse
                        </div>
                    </div>
                    <button type="button" data-history-next aria-label="{{ trans('cms_public.games.roulette.next_recent_number') }}">&rarr;</button>
                </div>
            </header>

            <div class="stage-top rt-stage-grid">
                <aside class="stage-support">
                    <section class="wheel-card support-card rt-wheel-stage" data-rt-wheel-stage>
                        <div class="card-title-row">
                            <h2 class="card-title">{{ trans('cms_public.games.roulette.wheel') }}</h2>
                            <span class="card-pill" data-roulette-lock-badge>{{ trans('cms_public.games.roulette.table_open') }}</span>
                        </div>
                        <div class="wheel-box">
                            <div class="wheel" data-roulette-wheel>
                                <div class="wheel-pointer"></div>
                                <div class="wheel-ball"></div>
                                <div class="wheel-disc" data-roulette-wheel-disc>
                                    <ul class="wheel-track" aria-hidden="true">
                                        @foreach(($payload['wheelOrder'] ?? []) as $index => $wheelNumber)
                                            @php
                                                $wheelTone = ((int) $wheelNumber) === 0
                                                    ? 'green'
                                                    : (in_array((int) $wheelNumber, $redWheelNumbers, true) ? 'red' : 'black');
                                                $wheelRotation = round((360 / max(count($payload['wheelOrder'] ?? []), 1)) * $index, 3);
                                            @endphp
                                            <li
                                                class="wheel-pocket is-{{ $wheelTone }}"
                                                style="transform: rotate({{ $wheelRotation }}deg);"
                                                data-wheel-pocket="{{ $wheelNumber }}"
                                            >
                                                <span>{{ $wheelNumber }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                            <div class="wheel-readout">
                                <div class="label">{{ trans('cms_public.games.roulette.winning_pocket') }}</div>
                                <div class="value" data-roulette-winning-number>-</div>
                            </div>
                        </div>
                        <div class="result-card rt-result-banner" data-roulette-result-card data-rt-result-banner>
                            <div class="card-title-row">
                                <h2 class="card-title">{{ trans('cms_public.games.roulette.result') }}</h2>
                                <span class="card-pill" data-roulette-result-tone>{{ trans('cms_public.games.roulette.idle') }}</span>
                            </div>
                            <div class="result-banner">
                                <strong data-roulette-result-summary></strong>
                                <span data-roulette-result-meta></span>
                            </div>
                            <div class="rt-result-stats">
                                <div class="table-info-item">
                                    <span>{{ trans('cms_public.games.roulette.pocket') }}</span>
                                    <strong data-roulette-result-pocket>-</strong>
                                </div>
                                <div class="table-info-item">
                                    <span>{{ trans('cms_public.games.common.status') }}</span>
                                    <strong data-roulette-status-label>{{ trans('cms_public.games.roulette.awaiting_spin') }}</strong>
                                </div>
                                <div class="table-info-item">
                                    <span>{{ trans('cms_public.games.common.live') }}</span>
                                    <strong data-roulette-live-result>-</strong>
                                </div>
                                <div class="table-info-item">
                                    <span>{{ trans('cms_public.games.common.stake') }}</span>
                                    <strong data-roulette-result-stake>0.00 {{ $payload['currency'] }}</strong>
                                </div>
                                <div class="table-info-item">
                                    <span>{{ trans('cms_public.games.roulette.return') }}</span>
                                    <strong data-roulette-result-return>0.00 {{ $payload['currency'] }}</strong>
                                </div>
                                <div class="table-info-item">
                                    <span>{{ trans('cms_public.games.roulette.net') }}</span>
                                    <strong data-roulette-result-net>0.00 {{ $payload['currency'] }}</strong>
                                </div>
                            </div>
                        </div>
                    </section>
                </aside>

                <div class="stage-table rt-betting-area" data-rt-betting-area>
                    <div class="table-stage">
                        <div class="table-wrap">
                            <div class="roulette-table">
                                <div class="zero-zone" data-roulette-interactive-group>
                                    <button
                                        type="button"
                                        class="zero-button"
                                        data-roulette-action="place-bet"
                                        data-bet-type="straight"
                                        data-bet-key="0"
                                        data-bet-label="Straight 0"
                                        data-number="0"
                                        data-testid="roulette-zero"
                                    >
                                        0
                                        <span class="bet-marker" data-bet-marker></span>
                                    </button>
                                </div>

                                <div class="number-grid" data-roulette-table data-roulette-interactive-group>
                                    @foreach(($payload['tableLayout']['numbers'] ?? []) as $cell)
                                        <button
                                            type="button"
                                            class="number-cell is-{{ $cell['color'] }}"
                                            style="grid-column: {{ $cell['column'] + 1 }}; grid-row: {{ $cell['row'] + 1 }};"
                                            data-roulette-action="place-bet"
                                            data-bet-type="straight"
                                            data-bet-key="{{ $cell['number'] }}"
                                            data-bet-label="Straight {{ $cell['number'] }}"
                                            data-number="{{ $cell['number'] }}"
                                            data-testid="roulette-number-{{ $cell['number'] }}"
                                        >
                                            {{ $cell['number'] }}
                                            <span class="bet-marker" data-bet-marker></span>
                                        </button>
                                    @endforeach

                                    @foreach((data_get($payload, 'tableLayout.insideHotspots', [])) as $mode => $hotspots)
                                        <div class="inside-overlay{{ $mode === 'split' ? ' is-active' : '' }}" data-roulette-inside-mode="{{ $mode }}">
                                            @foreach($hotspots as $hotspot)
                                                <button
                                                    type="button"
                                                    class="inside-hotspot"
                                                    style="left: {{ $hotspot['style']['left'] }}; top: {{ $hotspot['style']['top'] }}; width: {{ $hotspot['style']['width'] }}; height: {{ $hotspot['style']['height'] }};"
                                                    data-roulette-action="place-bet"
                                                    data-bet-type="{{ $hotspot['betType'] }}"
                                                    data-bet-key="{{ $hotspot['betKey'] }}"
                                                    data-bet-label="{{ strtoupper(str_replace('_', ' ', $hotspot['betType'])) }} {{ $hotspot['betKey'] }}"
                                                    data-testid="roulette-hotspot-{{ $hotspot['betType'] }}-{{ $hotspot['betKey'] }}"
                                                >
                                                    <span class="bet-marker" data-bet-marker></span>
                                                </button>
                                            @endforeach
                                        </div>
                                    @endforeach
                                </div>

                                <div class="column-grid" data-roulette-interactive-group>
                                    @foreach(($payload['tableLayout']['columns'] ?? []) as $column)
                                        <button
                                            type="button"
                                            class="column-button"
                                            data-roulette-action="place-bet"
                                            data-bet-type="{{ $column['betType'] }}"
                                            data-bet-key="{{ $column['betKey'] }}"
                                            data-bet-label="Column {{ $column['betKey'] }}"
                                            data-testid="roulette-column-{{ $column['betKey'] }}"
                                        >
                                            {{ $column['label'] }}
                                            <span class="bet-marker" data-bet-marker></span>
                                        </button>
                                    @endforeach
                                </div>

                                <div class="dozen-grid" data-roulette-interactive-group>
                                    @foreach(($payload['tableLayout']['dozens'] ?? []) as $dozen)
                                        <button
                                            type="button"
                                            class="dozen-button"
                                            data-roulette-action="place-bet"
                                            data-bet-type="{{ $dozen['betType'] }}"
                                            data-bet-key="{{ $dozen['betKey'] }}"
                                            data-bet-label="{{ $dozen['label'] }}"
                                            data-testid="roulette-dozen-{{ $dozen['betKey'] }}"
                                        >
                                            {{ $dozen['label'] }}
                                            <span class="bet-marker" data-bet-marker></span>
                                        </button>
                                    @endforeach
                                </div>

                                <div class="outside-grid" data-roulette-interactive-group>
                                    @foreach(($payload['tableLayout']['outside'] ?? []) as $outside)
                                        <button
                                            type="button"
                                            class="outside-button"
                                            data-tone="{{ $outside['tone'] }}"
                                            data-roulette-action="place-bet"
                                            data-bet-type="{{ $outside['betType'] }}"
                                            data-bet-key="{{ $outside['betKey'] }}"
                                            data-bet-label="{{ $outside['label'] }}"
                                            data-testid="roulette-outside-{{ $outside['betType'] }}-{{ $outside['betKey'] }}"
                                        >
                                            {{ $outside['label'] }}
                                            <span class="bet-marker" data-bet-marker></span>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        <div class="table-lock-overlay" data-roulette-lock-overlay>
                            <div>
                                <strong>{{ trans('cms_public.games.roulette.spinning') }}</strong>
                                <span>{{ trans('cms_public.games.roulette.spinning_body') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <section class="table-control-band rt-action-dock" data-rt-action-dock>
                <section class="table-control-card action-panel">
                    <h2 class="card-title">{{ trans('cms_public.games.roulette.chips') }}</h2>
                    <div class="chip-grid">
                        @foreach(($payload['chipValues'] ?? []) as $chipValue)
                            <button
                                type="button"
                                class="chip-button"
                                style="--chip-color: {{ $chipColors[$chipValue] ?? '#d5ac34' }}"
                                data-roulette-chip
                                data-chip-value="{{ $chipValue }}"
                                data-testid="roulette-chip-{{ $chipValue }}"
                            >
                                <span class="chip-disc"></span>
                                <span>{{ number_format((float) $chipValue, 0) }}</span>
                            </button>
                        @endforeach
                    </div>
                </section>

                <section class="table-control-card">
                    <h2 class="card-title">{{ trans('cms_public.games.roulette.inside_bet_mode') }}</h2>
                    <div class="mode-grid">
                        @foreach(($payload['betModes'] ?? []) as $mode)
                            <button
                                type="button"
                                class="mode-button"
                                data-roulette-mode
                                data-mode="{{ $mode['key'] }}"
                                data-testid="roulette-mode-{{ $mode['key'] }}"
                            >
                                {{ $mode['label'] }}
                            </button>
                        @endforeach
                    </div>
                    <div class="table-info-item" style="margin-top:8px;">
                        <span>{{ trans('cms_public.games.roulette.mode_hint') }}</span>
                        <strong data-roulette-mode-hint>-</strong>
                    </div>
                </section>

                <section class="table-control-card control-actions">
                    <button type="button" class="action-btn is-spin" data-roulette-spin-button data-testid="roulette-spin">{{ trans('cms_public.games.roulette.spin') }}</button>
                    <div class="control-actions__row">
                        <button type="button" class="action-btn is-clear" data-roulette-clear data-testid="roulette-clear">{{ trans('cms_public.games.roulette.clear') }}</button>
                        <button type="button" class="action-btn is-clear" data-roulette-repeat data-testid="roulette-repeat">{{ trans('cms_public.games.roulette.repeat') }}</button>
                    </div>
                </section>

                <section class="table-control-card control-live">
                    <h2 class="card-title">{{ trans('cms_public.games.roulette.live_state') }}</h2>
                        <div class="table-info-grid">
                        <div class="table-info-item">
                            <span>{{ trans('cms_public.games.common.balance') }}</span>
                            <strong data-roulette-live-balance>{{ $guestPreview ? '—' : number_format((float) $payload['balance'], 2) }} {{ $payload['currency'] }}</strong>
                        </div>
                        <div class="table-info-item">
                            <span>{{ trans('cms_public.games.roulette.open_bets') }}</span>
                            <strong data-roulette-footer-count>0</strong>
                        </div>
                        <div class="table-info-item">
                            <span>{{ trans('cms_public.games.common.total_stake') }}</span>
                            <strong>0.00 {{ $payload['currency'] }}</strong>
                            <strong data-roulette-footer-stake style="display:none">0.00 {{ $payload['currency'] }}</strong>
                        </div>
                        <div class="table-info-item">
                            <span>{{ trans('cms_public.games.roulette.selected_chip') }}</span>
                            <strong data-roulette-selected-chip>-</strong>
                            <strong data-roulette-footer-chip style="display:none">-</strong>
                        </div>
                        <div class="table-info-item">
                            <span>{{ trans('cms_public.games.roulette.active_mode') }}</span>
                            <strong data-roulette-active-mode>-</strong>
                            <strong data-roulette-footer-mode style="display:none">-</strong>
                        </div>
                    </div>
                </section>
            </section>

            <div class="status-message rt-status" data-roulette-status>
                {{ trans('cms_public.games.roulette.initial_status') }}
            </div>

            <button type="button" class="rt-drawer-toggle" data-rt-drawer-toggle aria-expanded="false">{{ trans('cms_public.games.common.details') }}</button>
            <section class="secondary-grid rt-drawer" data-rt-drawer>
                <section class="support-card secondary-panel slip-card">
                    <div class="card-title-row">
                        <h2 class="card-title">{{ trans('cms_public.games.roulette.bet_slip') }}</h2>
                        <span class="card-pill" data-roulette-bet-count>{{ trans('cms_public.games.roulette.bet_count', ['count' => 0]) }}</span>
                    </div>
                    <div class="slip-list" data-roulette-slip></div>
                </section>

                <section class="support-card secondary-panel line-panel">
                    <div class="card-title-row">
                        <h2 class="card-title">{{ trans('cms_public.games.roulette.line_results') }}</h2>
                    </div>
                    <div class="line-results" data-roulette-line-results></div>
                </section>

                <section class="support-card secondary-panel">
                    <div class="card-title-row">
                        <h2 class="card-title">{{ trans('cms_public.games.roulette.table_rules') }}</h2>
                        <span class="card-pill">{{ $rtTierLabel }}</span>
                    </div>
                    <div class="table-info-grid">
                        <div class="table-info-item">
                            <span>{{ trans('cms_public.games.roulette.min') }}</span>
                            <strong>{{ number_format((float) data_get($payload, 'tableLimits.minBet', 1), 2) }} {{ $payload['currency'] }}</strong>
                        </div>
                        <div class="table-info-item">
                            <span>{{ trans('cms_public.games.roulette.max') }}</span>
                            <strong>{{ number_format((float) data_get($payload, 'tableLimits.maxBet', 1000), 2) }} {{ $payload['currency'] }}</strong>
                        </div>
                        <div class="table-info-item">
                            <span>{{ trans('cms_public.games.roulette.straight') }}</span>
                            <strong>35:1</strong>
                        </div>
                        <div class="table-info-item">
                            <span>{{ trans('cms_public.games.roulette.outside') }}</span>
                            <strong>1:1</strong>
                        </div>
                    </div>
                </section>
            </section>
        </section>
    </main>
</div>

@if($guestPreview)
    </div>
@else
<script>
    (() => {
        const payload = @json($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        const rouletteI18n = @json(trans('cms_public.games.roulette'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        const commonI18n = @json(trans('cms_public.games.common'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const shell = document.querySelector('[data-roulette-surface]');
        if (!shell) {
            return;
        }

        const state = {
            selectedChip: Number(payload.chipValues?.[0] || 1),
            activeMode: 'straight',
            bets: new Map(),
            spinning: false,
            wheelRotation: 0,
            repeatableBets: [],
            ballAngle: 0,
            ballRadius: 88,
            ballScale: 1,
            orbitVelocity: 0,
            orbitFrame: null,
            orbitLastTs: null,
            spinSequence: 0,
            latestAppliedSpinSequence: 0,
            walletMessageSequence: 0,
            pendingCanonicalBalance: null,
        };

        const refs = {
            balance: shell.querySelector('[data-roulette-balance]'),
            winningNumber: shell.querySelector('[data-roulette-winning-number]'),
            status: shell.querySelector('[data-roulette-status]'),
            selectedChip: shell.querySelector('[data-roulette-selected-chip]'),
            activeMode: shell.querySelector('[data-roulette-active-mode]'),
            totalStake: shell.querySelector('[data-roulette-total-stake]'),
            spinButton: shell.querySelector('[data-roulette-spin-button]'),
            clearButton: shell.querySelector('[data-roulette-clear]'),
            repeatButton: shell.querySelector('[data-roulette-repeat]'),
            slip: shell.querySelector('[data-roulette-slip]'),
            history: shell.querySelector('[data-roulette-history]'),
            historyViewport: shell.querySelector('[data-roulette-history-viewport]'),
            historyPrev: shell.querySelector('[data-history-prev]'),
            historyNext: shell.querySelector('[data-history-next]'),
            resultSummary: shell.querySelector('[data-roulette-result-summary]'),
            resultMeta: shell.querySelector('[data-roulette-result-meta]'),
            resultPocket: shell.querySelector('[data-roulette-result-pocket]'),
            statusLabel: shell.querySelector('[data-roulette-status-label]'),
            resultStake: shell.querySelector('[data-roulette-result-stake]'),
            resultReturn: shell.querySelector('[data-roulette-result-return]'),
            resultNet: shell.querySelector('[data-roulette-result-net]'),
            resultCard: shell.querySelector('[data-roulette-result-card]'),
            resultTone: shell.querySelector('[data-roulette-result-tone]'),
            betCount: shell.querySelector('[data-roulette-bet-count]'),
            footerChip: shell.querySelector('[data-roulette-footer-chip]'),
            footerMode: shell.querySelector('[data-roulette-footer-mode]'),
            footerCount: shell.querySelector('[data-roulette-footer-count]'),
            footerStake: shell.querySelector('[data-roulette-footer-stake]'),
            liveBalance: shell.querySelector('[data-roulette-live-balance]'),
            liveResult: shell.querySelector('[data-roulette-live-result]'),
            lineResults: shell.querySelector('[data-roulette-line-results]'),
            wheelBall: shell.querySelector('.wheel-ball'),
            wheelDisc: shell.querySelector('[data-roulette-wheel-disc]'),
            modeHint: shell.querySelector('[data-roulette-mode-hint]'),
            lockBadge: shell.querySelector('[data-roulette-lock-badge]'),
            lockOverlay: shell.querySelector('[data-roulette-lock-overlay]'),
            numberGrid: shell.querySelector('[data-roulette-table]'),
            chipButtons: Array.from(shell.querySelectorAll('[data-roulette-chip]')),
            modeButtons: Array.from(shell.querySelectorAll('[data-roulette-mode]')),
            wheelPockets: Array.from(shell.querySelectorAll('[data-wheel-pocket]')),
            interactiveGroups: Array.from(shell.querySelectorAll('[data-roulette-interactive-group]')),
            insideOverlays: Array.from(shell.querySelectorAll('[data-roulette-inside-mode]')),
            betButtons: Array.from(shell.querySelectorAll('[data-roulette-action="place-bet"]')),
        };

        const defaultSpinLabel = refs.spinButton?.textContent || 'Spin';
        const WHEEL_CALIBRATION_OFFSET = 0;
        const textFrom = (map, key, fallback, replacements = {}) => {
            let value = Object.prototype.hasOwnProperty.call(map || {}, key) ? map[key] : fallback;
            if (typeof value !== 'string') value = fallback;
            Object.entries(replacements).forEach(([name, replacement]) => {
                value = value.replaceAll(`:${name}`, String(replacement));
            });
            return value;
        };
        const rtText = (key, fallback, replacements = {}) => textFrom(rouletteI18n, key, fallback, replacements);
        const commonText = (key, fallback, replacements = {}) => textFrom(commonI18n, key, fallback, replacements);

        const formatAmount = (amount) => `${Number(amount || 0).toFixed(2)} ${payload.currency}`;
        const setRouletteBalance = (amount) => {
            const nextBalance = Number(amount);
            if (!Number.isFinite(nextBalance)) {
                return false;
            }

            payload.balance = nextBalance;
            refs.balance.textContent = formatAmount(nextBalance);
            if (refs.liveBalance) {
                refs.liveBalance.textContent = formatAmount(nextBalance);
            }

            return true;
        };
        const postParentWalletUpdate = (result, requestRef = null) => {
            if (payload.isDemo !== false || !window.parent || window.parent === window) {
                return;
            }

            const balance = Number(result && result.balance);
            if (!Number.isFinite(balance)) {
                return;
            }

            state.walletMessageSequence += 1;
            window.parent.postMessage({
                type: 'internal-game:wallet-updated',
                source: 'roulette',
                roundId: result.roundId || null,
                requestRef: result.requestKey || requestRef || null,
                balance,
                currency: payload.currency || '',
                timestamp: Date.now(),
                sequence: state.walletMessageSequence,
            }, window.location.origin);
        };
        const lockParentWalletSync = (durationMs = 7000) => {
            if (payload.isDemo !== false || !window.parent || window.parent === window) {
                return;
            }

            const now = Date.now();
            window.parent.postMessage({
                type: 'internal-game:wallet-sync-lock',
                source: 'roulette',
                until: now + durationMs,
                timestamp: now,
            }, window.location.origin);
        };
        const applyCanonicalBalance = (result, requestRef = null, sequence = 0, options = {}) => {
            if (!result || result.balance == null || sequence < state.latestAppliedSpinSequence) {
                return false;
            }

            const deferred = options && options.defer === true;
            if (deferred) {
                state.pendingCanonicalBalance = {
                    balance: Number(result.balance),
                    roundId: result.roundId || null,
                    requestRef: result.requestKey || requestRef || null,
                    sequence,
                };

                return Number.isFinite(state.pendingCanonicalBalance.balance);
            }

            const applied = setRouletteBalance(result.balance);
            if (applied) {
                state.latestAppliedSpinSequence = sequence;
                postParentWalletUpdate(result, requestRef);
                state.pendingCanonicalBalance = null;
            }

            return applied;
        };
        const flushPendingCanonicalBalance = (fallbackResult = null) => {
            const pending = state.pendingCanonicalBalance;
            if (pending && pending.sequence >= state.latestAppliedSpinSequence) {
                const applied = setRouletteBalance(pending.balance);
                if (applied) {
                    state.latestAppliedSpinSequence = pending.sequence;
                    postParentWalletUpdate({
                        balance: pending.balance,
                        roundId: pending.roundId,
                        requestKey: pending.requestRef,
                    }, pending.requestRef);
                    state.pendingCanonicalBalance = null;
                }

                return applied;
            }

            if (fallbackResult && fallbackResult.balance != null) {
                return setRouletteBalance(fallbackResult.balance);
            }

            return false;
        };
        const compactAmount = (amount) => {
            const value = Number(amount || 0);
            if (value >= 1000) {
                return `${(value / 1000).toFixed(1).replace(/\.0$/, '')}k`;
            }

            return value % 1 === 0 ? `${value}` : value.toFixed(2);
        };
        const requestKey = () => {
            if (window.crypto?.randomUUID) {
                return window.crypto.randomUUID();
            }

            if (window.crypto?.getRandomValues) {
                const values = new Uint32Array(4);
                window.crypto.getRandomValues(values);

                return `${Date.now()}-${Array.from(values).map((value) => value.toString(16).padStart(8, '0')).join('')}`;
            }

            return `${Date.now()}-${Math.random().toString(16).slice(2)}`;
        };
        const betId = (betType, betKey) => `${betType}:${betKey}`;
        const normalizeDegrees = (value) => ((value % 360) + 360) % 360;
        const parseRotation = (transformValue) => {
            if (!transformValue || transformValue === 'none') {
                return state.wheelRotation || 0;
            }

            const matrixMatch = transformValue.match(/matrix\(([^)]+)\)/);
            if (!matrixMatch) {
                return state.wheelRotation || 0;
            }

            const [a, b] = matrixMatch[1].split(',').map((value) => Number(value.trim()));
            if (!Number.isFinite(a) || !Number.isFinite(b)) {
                return state.wheelRotation || 0;
            }

            return Math.atan2(b, a) * (180 / Math.PI);
        };
        const wait = (ms) => new Promise((resolve) => window.setTimeout(resolve, ms));
        const nextFrame = () => new Promise((resolve) => window.requestAnimationFrame(() => resolve()));
        const labelForMode = (mode) => (payload.betModes || []).find((entry) => entry.key === mode)?.label || mode;
        const modeHintFor = (mode) => ({
            straight: rtText('mode_hint_straight', 'Tap a number or 0 area in Straight mode.'),
            split: rtText('mode_hint_split', 'Use thin line hotspots in Split mode.'),
            street: rtText('mode_hint_street', 'Use vertical column areas in Street mode.'),
            corner: rtText('mode_hint_corner', 'Use four-number intersection hotspots in Corner mode.'),
            six_line: rtText('mode_hint_six_line', 'Select the wide two-column band in Six Line mode.'),
        }[mode] || rtText('mode_hint_default', 'Use the active mode to place inside bets.'));

        const collectBets = () => Array.from(state.bets.values()).map((entry) => ({
            betType: entry.betType,
            betKey: entry.betKey,
            stake: Number(entry.stake.toFixed(2)),
        }));

        const totalStake = () => Array.from(state.bets.values()).reduce((carry, entry) => carry + entry.stake, 0);

        const setStatus = (message, tone = 'neutral') => {
            refs.status.textContent = message;
            refs.status.classList.toggle('is-error', tone === 'error');
            refs.status.classList.toggle('is-busy', tone === 'busy');
            refs.status.classList.toggle('is-success', tone === 'success');
        };

        const setResultTone = (tone, label) => {
            refs.resultCard?.classList.remove('is-pending', 'is-win', 'is-loss');

            if (tone === 'pending') {
                refs.resultCard?.classList.add('is-pending');
            } else if (tone === 'win') {
                refs.resultCard?.classList.add('is-win');
            } else if (tone === 'loss') {
                refs.resultCard?.classList.add('is-loss');
            }

            if (refs.resultTone) {
                refs.resultTone.textContent = label;
            }
        };

        const applyBallPose = (angle, radius, transition = 'transform .28s linear', scale = 1) => {
            if (!refs.wheelBall) {
                return;
            }

            state.ballAngle = angle;
            state.ballRadius = radius;
            state.ballScale = scale;
            refs.wheelBall.style.transition = transition;
            refs.wheelBall.style.setProperty('--ball-angle', `${angle}deg`);
            refs.wheelBall.style.setProperty('--ball-radius', `${radius}`);
            refs.wheelBall.style.setProperty('--ball-scale', `${scale}`);
        };

        const setWheelRotation = (angle, transition = 'transform 4.2s cubic-bezier(.12,.82,.18,1)') => {
            if (!refs.wheelDisc) {
                return;
            }

            state.wheelRotation = angle;
            refs.wheelDisc.style.transition = transition;
            refs.wheelDisc.style.setProperty('--rotation', `${angle}deg`);
        };

        const freezeWheelMotion = () => {
            if (!refs.wheelDisc) {
                return state.wheelRotation || 0;
            }

            const currentRotation = parseRotation(window.getComputedStyle(refs.wheelDisc).transform);
            refs.wheelDisc.classList.remove('is-spinning');
            setWheelRotation(currentRotation, 'none');

            return currentRotation;
        };

        const stopBallOrbit = () => {
            if (state.orbitFrame) {
                window.cancelAnimationFrame(state.orbitFrame);
                state.orbitFrame = null;
            }

            state.orbitLastTs = null;
            state.orbitVelocity = 0;
            refs.wheelBall?.classList.remove('is-orbiting');
        };

        const startBallOrbit = () => {
            if (!refs.wheelBall) {
                return;
            }

            stopBallOrbit();
            refs.wheelBall.classList.add('is-orbiting');
            state.orbitVelocity = 620;

            const orbit = (timestamp) => {
                if (!state.spinning) {
                    stopBallOrbit();
                    return;
                }

                if (state.orbitLastTs === null) {
                    state.orbitLastTs = timestamp;
                }

                const deltaSeconds = (timestamp - state.orbitLastTs) / 1000;
                state.orbitLastTs = timestamp;

                state.orbitVelocity = Math.max(300, state.orbitVelocity - (deltaSeconds * 110));
                state.ballAngle -= state.orbitVelocity * deltaSeconds;
                const rimPulse = 86 + (Math.sin(timestamp / 140) * 0.8);
                applyBallPose(state.ballAngle, rimPulse, 'none', 1);
                state.orbitFrame = window.requestAnimationFrame(orbit);
            };

            state.orbitFrame = window.requestAnimationFrame(orbit);
        };

        const renderMarkers = () => {
            refs.betButtons.forEach((button) => {
                const marker = button.querySelector('[data-bet-marker]');
                if (!marker) {
                    return;
                }

                const id = betId(button.dataset.betType, button.dataset.betKey);
                const entry = state.bets.get(id);
                const visible = Boolean(entry);
                marker.classList.toggle('is-visible', visible);
                marker.textContent = visible ? compactAmount(entry.stake) : '';
                button.classList.toggle('is-selected', visible);
            });
        };

        const renderSlip = () => {
            const entries = Array.from(state.bets.values());
            refs.slip.innerHTML = '';

            if (entries.length === 0) {
                refs.slip.innerHTML = `<div class="slip-item slip-empty"><div>${rtText('no_bets', 'No bets yet.<br>Place a bet on the table.')}</div></div>`;
            } else {
                entries.forEach((entry) => {
                    const item = document.createElement('div');
                    item.className = 'slip-item';
                    item.innerHTML = `
                        <div class="slip-head">
                            <div>
                                <div class="slip-title">${entry.label}</div>
                                <div class="slip-sub">${entry.betType} / ${entry.betKey}</div>
                            </div>
                            <div class="slip-amount">${formatAmount(entry.stake)}</div>
                        </div>
                        <div class="slip-actions">
                            <button type="button" class="slip-btn" data-slip-remove="${entry.id}">${rtText('remove', 'Remove')}</button>
                        </div>
                    `;
                    refs.slip.appendChild(item);
                });
            }

            refs.totalStake.textContent = formatAmount(totalStake());
            if (refs.betCount) {
                refs.betCount.textContent = rtText('bet_count', `${entries.length} Bet`, { count: entries.length });
            }
            if (refs.footerCount) {
                refs.footerCount.textContent = `${entries.length}`;
            }
            if (refs.footerStake) {
                refs.footerStake.textContent = formatAmount(totalStake());
            }
            refs.spinButton.disabled = state.spinning || state.bets.size === 0;
            refs.clearButton.disabled = state.spinning || state.bets.size === 0;
            if (refs.repeatButton) {
                refs.repeatButton.disabled = state.spinning || state.repeatableBets.length === 0;
            }
        };

        const renderModes = () => {
            refs.modeButtons.forEach((button) => {
                button.classList.toggle('is-active', button.dataset.mode === state.activeMode);
            });

            refs.insideOverlays.forEach((overlay) => {
                overlay.classList.toggle('is-active', overlay.dataset.rouletteInsideMode === state.activeMode);
            });

            refs.activeMode.textContent = labelForMode(state.activeMode);
            if (refs.modeHint) {
                refs.modeHint.textContent = modeHintFor(state.activeMode);
            }
            refs.numberGrid.classList.toggle('is-inside-mode', state.activeMode !== 'straight');
            if (refs.footerMode) {
                refs.footerMode.textContent = labelForMode(state.activeMode);
            }
        };

        const renderChips = () => {
            refs.chipButtons.forEach((button) => {
                button.classList.toggle('is-active', Number(button.dataset.chipValue) === state.selectedChip);
            });

            refs.selectedChip.textContent = formatAmount(state.selectedChip);
            if (refs.footerChip) {
                refs.footerChip.textContent = formatAmount(state.selectedChip);
            }
        };

        const highlightWinningNumber = (winningNumber, winningColor) => {
            shell.querySelectorAll('.is-winning').forEach((element) => element.classList.remove('is-winning'));
            const numericWinningNumber = Number(winningNumber);
            const winningCell = Number.isFinite(numericWinningNumber)
                ? shell.querySelector(`[data-number="${numericWinningNumber}"]`)
                : null;
            if (winningCell) {
                winningCell.classList.add('is-winning');
            }

            refs.winningNumber.textContent = winningNumber;
            refs.winningNumber.className = `value is-${winningColor || 'neutral'}`;
            if (refs.liveResult) {
                refs.liveResult.textContent = winningNumber === '-' ? '-' : `${winningNumber} ${winningColor || ''}`.trim();
            }
            if (refs.resultPocket) {
                refs.resultPocket.textContent = winningNumber === '-' ? '-' : `${winningNumber} ${winningColor || ''}`.trim();
            }
        };

        const highlightWinningPocket = (winningNumber) => {
            refs.wheelPockets.forEach((pocket) => pocket.classList.remove('is-winning-pocket'));
            const winningPocket = refs.wheelPockets.find((pocket) => pocket.dataset.wheelPocket === String(winningNumber));
            if (winningPocket) {
                winningPocket.classList.add('is-winning-pocket');
            }
        };

        const renderHistory = (result) => {
            if (!refs.history) {
                return;
            }

            refs.history.querySelectorAll('.history-pill:not([data-number])').forEach(entry => entry.remove());
            const pill = document.createElement('div');
            pill.dataset.number = String(result.winningNumber);
            pill.className = `history-pill is-${result.winningColor} is-fresh`;
            pill.textContent = result.winningNumber;
            refs.history.prepend(pill);
            window.setTimeout(() => pill.classList.remove('is-fresh'), 600);

            while (refs.history.children.length > 12) {
                refs.history.removeChild(refs.history.lastElementChild);
            }
        };

        const renderLineResults = (lineResults) => {
            refs.lineResults.innerHTML = '';

            if (!Array.isArray(lineResults) || lineResults.length === 0) {
                refs.lineResults.innerHTML = `<div class="line-result"><div>${rtText('no_line_results', 'No line results.')}</div><div class="payout">-</div></div>`;
                return;
            }

            lineResults.forEach((line) => {
                const item = document.createElement('div');
                item.className = `line-result is-${line.status === 'won' ? 'won' : 'lost'}`;
                item.innerHTML = `
                    <div>
                        <strong>#${line.lineNo} ${line.betType} ${line.betKey}</strong>
                        <div class="meta">covered: ${line.coveredNumbers.join(', ')} | stake: ${formatAmount(line.stakeAmount)}</div>
                    </div>
                    <div class="payout">${line.status} / ${formatAmount(line.payoutAmount)}</div>
                `;
                refs.lineResults.appendChild(item);
            });
        };

        const lockTable = (locked) => {
            state.spinning = locked;
            refs.interactiveGroups.forEach((group) => group.classList.toggle('is-locked', locked));
            refs.lockOverlay.classList.toggle('is-visible', locked);
            if (refs.lockBadge) {
                refs.lockBadge.textContent = locked ? rtText('table_locked', 'Table locked') : rtText('table_open', 'Table open');
                refs.lockBadge.classList.toggle('is-locked', locked);
            }
            refs.spinButton.disabled = locked || state.bets.size === 0;
            refs.clearButton.disabled = locked || state.bets.size === 0;
            if (refs.repeatButton) {
                refs.repeatButton.disabled = locked || state.repeatableBets.length === 0;
            }
            refs.spinButton.classList.toggle('is-busy', locked);
            refs.spinButton.textContent = locked ? `${rtText('spinning', 'Spinning')}...` : defaultSpinLabel;
            refs.modeButtons.forEach((button) => button.disabled = locked);
            refs.chipButtons.forEach((button) => button.disabled = locked);

            if (locked) {
                setResultTone('pending', commonText('pending', 'Pending'));
                if (refs.statusLabel) {
                    refs.statusLabel.textContent = rtText('spinning', 'Spinning');
                }
            }
        };

        const applySettledResult = (result) => {
            flushPendingCanonicalBalance(result);
            refs.resultSummary.textContent = result.resultSummary || rtText('spin_settled', 'Spin settled.');
            refs.resultMeta.textContent = rtText('result_meta', 'Stake :stake | Return :return | Net :net', {
                stake: formatAmount(result.totalStake),
                return: formatAmount(result.totalReturn),
                net: formatAmount(result.net),
            });
            refs.resultStake.textContent = formatAmount(result.totalStake);
            refs.resultReturn.textContent = formatAmount(result.totalReturn);
            refs.resultNet.textContent = formatAmount(result.net);
            setResultTone(Number(result.net) > 0 ? 'win' : 'loss', Number(result.net) > 0 ? commonText('win', 'Win') : commonText('loss', 'Loss'));
            highlightWinningNumber(result.winningNumber, result.winningColor);
            highlightWinningPocket(result.winningNumber);
            renderLineResults(result.lineResults);
            renderHistory(result);
            if (refs.statusLabel) {
                refs.statusLabel.textContent = Number(result.net) > 0 ? rtText('settled_win', 'Settled win') : rtText('settled_loss', 'Settled loss');
            }
        };

        const replaceBets = (bets) => {
            state.bets.clear();
            bets.forEach((entry) => {
                state.bets.set(betId(entry.betType, entry.betKey), {
                    id: betId(entry.betType, entry.betKey),
                    betType: entry.betType,
                    betKey: entry.betKey,
                    label: entry.label || `${entry.betType} ${entry.betKey}`,
                    stake: Number(entry.stake),
                });
            });
            renderMarkers();
            renderSlip();
        };

        const beginWheelMotion = () => {
            if (!refs.wheelDisc) {
                return;
            }

            freezeWheelMotion();
            refs.wheelDisc.classList.add('is-spinning');
            startBallOrbit();
        };

        const animateWheelToPocket = async (winningNumber) => {
            if (!Array.isArray(payload.wheelOrder) || payload.wheelOrder.length === 0) {
                return;
            }

            stopBallOrbit();
            const currentWheelRotation = freezeWheelMotion();

            const index = payload.wheelOrder.indexOf(Number(winningNumber));
            if (index < 0) {
                return;
            }

            const segment = 360 / payload.wheelOrder.length;
            const wheelTargetNormalized = normalizeDegrees((-(index * segment)) + WHEEL_CALIBRATION_OFFSET);
            const currentWheelNormalized = normalizeDegrees(currentWheelRotation);
            let wheelDelta = wheelTargetNormalized - currentWheelNormalized;
            if (wheelDelta < 0) {
                wheelDelta += 360;
            }

            const finalWheelRotation = currentWheelRotation + (360 * 3) + wheelDelta;
            const currentBallNormalized = normalizeDegrees(state.ballAngle);
            const finalBallAngle = state.ballAngle - (currentBallNormalized + 360);

            await nextFrame();
            setWheelRotation(finalWheelRotation, 'transform 4.35s cubic-bezier(.11,.79,.16,1)');
            applyBallPose(finalBallAngle, 78, 'transform 3.35s cubic-bezier(.13,.7,.18,1)', 0.98);
            await wait(2850);
            applyBallPose(finalBallAngle, 71, 'transform 1.3s cubic-bezier(.17,.82,.2,1)', 0.92);
            await wait(1450);
        };

        const addBet = (betType, betKey, label) => {
            if (state.spinning) {
                return;
            }

            if (betType === 'straight' && state.activeMode !== 'straight') {
                setStatus(rtText('number_placement_straight', 'Straight mode must be active to place a number bet.'), 'neutral');
                return;
            }

            if (betType !== 'straight' && betType !== state.activeMode && ['split', 'street', 'corner', 'six_line'].includes(betType)) {
                setStatus(rtText('active_mode_now', 'Active mode is now :mode.', { mode: labelForMode(state.activeMode) }), 'neutral');
                return;
            }

            const id = betId(betType, betKey);
            const current = state.bets.get(id);
            const nextStake = Number(((current?.stake || 0) + state.selectedChip).toFixed(2));
            if (nextStake > Number(payload.tableLimits?.maxBet || 1000)) {
                setStatus(rtText('max_bet_exceeded', 'Table max bet limit exceeded.'), 'error');
                return;
            }

            state.bets.set(id, {
                id,
                betType,
                betKey,
                label,
                stake: nextStake,
            });

            renderMarkers();
            renderSlip();
            setStatus(rtText('bet_added', ':amount added for :label.', {
                label,
                amount: formatAmount(state.selectedChip),
            }), 'success');
        };

        const clearTable = () => {
            state.bets.clear();
            renderMarkers();
            renderSlip();
            setStatus(rtText('table_cleared', 'Table cleared.'));
        };

        const repeatTable = () => {
            if (state.spinning || state.repeatableBets.length === 0) {
                return;
            }

            replaceBets(state.repeatableBets);
            setStatus(rtText('repeated', 'The last valid bet set was loaded again.'), 'success');
        };

        const createSpinError = (envelope, fallbackMessage = commonText('request_rejected', 'Request rejected.')) => {
            const error = new Error(
                envelope && typeof envelope.message === 'string' && envelope.message.trim() !== ''
                    ? envelope.message
                    : fallbackMessage
            );

            error.code = envelope && typeof envelope.code === 'string' ? envelope.code : '';
            error.status = envelope && typeof envelope.status !== 'undefined'
                ? Number(envelope.status)
                : 0;

            return error;
        };

        const openDepositModalForInsufficientFunds = () => {
            const hostWindow = window.parent && window.parent !== window ? window.parent : window;

            try {
                if (typeof hostWindow.openCmsDepositModal === 'function') {
                    hostWindow.openCmsDepositModal();
                    return true;
                }
            } catch (error) {}

            return false;
        };

        const isInsufficientFundsSpinError = (error) => {
            if (!error) {
                return false;
            }

            const code = String(error.code || '').trim().toUpperCase();
            if (code === 'ROULETTE_INSUFFICIENT_FUNDS') {
                return true;
            }

            const message = String(error.message || '').trim().toLowerCase();
            return message.includes('insufficient funds');
        };

        const spin = async () => {
            if (state.spinning || state.bets.size === 0) {
                return;
            }

            state.spinSequence += 1;
            const currentSpinSequence = state.spinSequence;
            const currentRequestKey = requestKey();
            const spinStartedAt = Date.now();
            lockParentWalletSync();
            const submittedBets = collectBets();
            state.repeatableBets = submittedBets.map((entry) => ({
                ...entry,
                label: Array.from(state.bets.values()).find((bet) => bet.betType === entry.betType && bet.betKey === entry.betKey)?.label || `${entry.betType} ${entry.betKey}`,
            }));
            lockTable(true);
            beginWheelMotion();
            setStatus(rtText('spin_sending', 'Sending spin, table is locked.'), 'busy');
            highlightWinningPocket(-1);
            highlightWinningNumber('-', 'neutral');
            refs.resultSummary.textContent = `${rtText('spinning', 'Spinning')}...`;
            refs.resultMeta.textContent = rtText('spin_waiting', 'Once the backend result arrives, the animation will finish before reveal.');
            if (refs.statusLabel) {
                refs.statusLabel.textContent = rtText('spinning', 'Spinning');
            }

            try {
                const response = await fetch(payload.spinRoute, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({
                        sessionToken: payload.sessionToken,
                        requestKey: currentRequestKey,
                        bets: submittedBets,
                    }),
                });

                const envelope = await response.json();
                if (!response.ok || Number(envelope.status) !== 200) {
                    throw createSpinError(envelope);
                }

                const result = envelope.data;
                applyCanonicalBalance(result, currentRequestKey, currentSpinSequence, { defer: true });

                const minPendingMs = 900;
                const elapsedMs = Date.now() - spinStartedAt;
                if (elapsedMs < minPendingMs) {
                    await new Promise((resolve) => window.setTimeout(resolve, minPendingMs - elapsedMs));
                }

                refs.resultSummary.textContent = rtText('settling_spin', 'Settling spin...');
                refs.resultMeta.textContent = rtText('settling_body', 'Wheel and ball are landing on the backend result pocket.');
                if (refs.statusLabel) {
                    refs.statusLabel.textContent = commonText('pending', 'Pending');
                }
                await animateWheelToPocket(result.winningNumber);
                applySettledResult(result);

                clearTable();
                setStatus(rtText('winning_number', 'Winning number :number (:color).', {
                    number: result.winningNumber,
                    color: result.winningColor,
                }), 'success');
            } catch (error) {
                state.pendingCanonicalBalance = null;
                stopBallOrbit();
                freezeWheelMotion();
                setWheelRotation(0, 'transform .7s cubic-bezier(.2,.78,.28,1)');
                applyBallPose(state.ballAngle - normalizeDegrees(state.ballAngle), 88, 'transform .35s ease-out', 1);
                setResultTone('loss', commonText('error', 'Error'));
                if (refs.statusLabel) {
                    refs.statusLabel.textContent = commonText('error', 'Error');
                }

                if (isInsufficientFundsSpinError(error)) {
                    setStatus(commonText('deposit_opening', 'Insufficient balance. Opening the deposit window.'), 'error');
                    window.setTimeout(() => {
                        openDepositModalForInsufficientFunds();
                    }, 120);
                } else {
                    setStatus(error.message || rtText('spin_failed', 'Spin failed.'), 'error');
                }
            } finally {
                lockTable(false);
            }
        };

        refs.chipButtons.forEach((button) => {
            button.addEventListener('click', () => {
                state.selectedChip = Number(button.dataset.chipValue);
                renderChips();
            });
        });

        refs.modeButtons.forEach((button) => {
            button.addEventListener('click', () => {
                state.activeMode = button.dataset.mode;
                renderModes();
                setStatus(`${labelForMode(state.activeMode)} modu aktif.`);
            });
        });

        refs.betButtons.forEach((button) => {
            button.addEventListener('click', () => addBet(
                button.dataset.betType,
                button.dataset.betKey,
                button.dataset.betLabel || `${button.dataset.betType} ${button.dataset.betKey}`
            ));
        });

        refs.slip.addEventListener('click', (event) => {
            const target = event.target.closest('[data-slip-remove]');
            if (!target || state.spinning) {
                return;
            }

            state.bets.delete(target.dataset.slipRemove);
            renderMarkers();
            renderSlip();
        });

        refs.clearButton.addEventListener('click', clearTable);
        refs.repeatButton?.addEventListener('click', repeatTable);
        refs.spinButton.addEventListener('click', spin);
        refs.historyPrev?.addEventListener('click', () => refs.historyViewport?.scrollBy({ left: -220, behavior: 'smooth' }));
        refs.historyNext?.addEventListener('click', () => refs.historyViewport?.scrollBy({ left: 220, behavior: 'smooth' }));

        const drawer = shell.querySelector('[data-rt-drawer]');
        const drawerToggle = shell.querySelector('[data-rt-drawer-toggle]');
        drawerToggle?.addEventListener('click', () => {
            if (!drawer) {
                return;
            }

            const isOpen = drawer.classList.toggle('is-open');
            drawerToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            drawerToggle.textContent = isOpen ? commonText('close', 'Close') : commonText('details', 'Details');
        });

        renderChips();
        renderModes();
        renderMarkers();
        renderSlip();
        if (refs.liveBalance) {
            refs.liveBalance.textContent = formatAmount(payload.balance ?? 0);
        }
        setResultTone(null, 'Idle');
        setWheelRotation(0, 'none');
        applyBallPose(0, 88, 'none', 1);
        highlightWinningNumber('-', 'neutral');
        highlightWinningPocket(-1);
    })();
</script>
@endif
</body>
</html>

