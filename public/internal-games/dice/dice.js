/* Rise Dice client. The server owns rolls and balances in both modes. */
(() => {
    'use strict';
    const root = document.querySelector('[data-dice]');
    const boot = document.querySelector('[data-dice-payload]');
    if (!root || !boot) return;
    const payload = JSON.parse(boot.textContent);
    const copy = payload.copy;
    const currency = payload.currency || 'VC';
    const find = (name) => root.querySelector(`[data-${name}]`);
    const form = find('controls');
    const stake = find('stake');
    const target = find('target');
    const button = find('roll');
    const status = find('status');
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
    const pendingKey = `rise-dice-pending:${payload.sessionToken}`;
    const state = { mode: 'over', history: payload.history || [], pending: null, busy: false, ended: false, sound: false };
    let audio;
    const store = (key, value) => { try { sessionStorage.setItem(key, JSON.stringify(value)); } catch (_) {} };
    try { state.pending = JSON.parse(sessionStorage.getItem(pendingKey)) || null; } catch (_) {}
    const amount = () => {
        const value = stake.value.trim().replace(',', '.');
        return /^\d{1,6}(\.\d{1,2})?$/.test(value) ? Math.round(Number(value) * 100) : NaN;
    };
    const money = (value) => Number(value).toFixed(2);
    const number = (value) => Number(value).toFixed(2).replace(/\.00$/, '');
    const text = (node, value) => { node.textContent = value; };
    const message = (value, error = false) => { text(status, value); status.classList.toggle('is-error', error); };
    const setPending = (value) => { state.pending = value; store(pendingKey, value); };
    const lock = () => {
        form.querySelectorAll('input,button').forEach((node) => { node.disabled = state.busy || state.ended || !!state.pending; });
        button.disabled = state.busy || state.ended;
        find('refresh').disabled = state.busy;
        form.setAttribute('aria-busy', String(state.busy));
        root.classList.toggle('is-rolling', state.busy);
        text(find('roll-label'), state.busy ? copy.rolling : state.pending ? copy.retry : copy.bet);
    };
    function updateOdds() {
        const units = Math.round(Number(target.value) * 100);
        const chance = state.mode === 'under' ? units : 10000 - units;
        const multiplier = payload.rtpUnits / chance;
        target.style.setProperty('--target', `${((Number(target.value) - Number(target.min)) / (Number(target.max) - Number(target.min))) * 100}%`);
        target.style.setProperty('--low', state.mode === 'under' ? '#1685ff' : '#fc4f42');
        target.style.setProperty('--high', state.mode === 'under' ? '#fc4f42' : '#1685ff');
        target.setAttribute('aria-valuetext', `${copy[state.mode]} ${number(target.value)} · ${number(chance / 100)}%`);
        text(find('target-small'), number(target.value));
        const pieces = Number(target.value).toFixed(2).split('.');
        const decimals = document.createElement('span');
        decimals.textContent = `.${pieces[1]}`;
        find('target-large').replaceChildren(document.createTextNode(pieces[0]), decimals);
        text(find('chance'), `${number(chance / 100)}%`);
        text(find('multiplier'), `×${multiplier.toFixed(4).replace(/0+$/, '').replace(/\.$/, '')}`);
        const minor = amount();
        text(find('payout'), Number.isFinite(minor) && minor >= 0 ? `${money(Math.floor(minor * payload.rtpUnits / chance) / 100)} ${currency}` : '—');
    }
    function renderHistory() {
        const fragment = document.createDocumentFragment();
        state.history.slice(0, 20).forEach((entry) => {
            const row = document.createElement('tr');
            row.className = entry.result.won ? 'is-win' : 'is-loss';
            [
                `${number(entry.result.rolled)} · ${copy[entry.result.mode]} ${number(entry.result.target)}`,
                `${money(entry.betAmount)} ${currency}`, `×${number(entry.result.multiplier)}`, `${money(entry.winAmount)} ${currency}`,
            ].forEach((value) => { const cell = document.createElement('td'); cell.textContent = value; row.append(cell); });
            fragment.append(row);
        });
        find('history').replaceChildren(fragment);
        find('empty').hidden = state.history.length > 0;
    }
    function showResult(entry, announce = true) {
        const result = find('result');
        const pieces = Number(entry.result.rolled).toFixed(2).split('.');
        const decimals = document.createElement('span');
        decimals.textContent = `.${pieces[1]}`;
        result.replaceChildren(document.createTextNode(pieces[0]), decimals);
        result.parentElement.classList.toggle('is-win', entry.result.won);
        result.parentElement.classList.toggle('is-loss', !entry.result.won);
        text(find('balance'), money(entry.balance));
        if (announce) message(`${entry.result.won ? copy.won : copy.lost} · ${number(entry.result.rolled)}${entry.result.won ? ` · ${money(entry.winAmount)} ${currency}` : ''}`);
    }
    function tone(won) {
        if (!state.sound) return;
        try {
            audio ||= new (window.AudioContext || window.webkitAudioContext)();
            audio.resume().catch(() => {});
            const oscillator = audio.createOscillator();
            const gain = audio.createGain();
            oscillator.type = 'sine';
            oscillator.frequency.setValueAtTime(won ? 660 : 220, audio.currentTime);
            gain.gain.setValueAtTime(0.035, audio.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, audio.currentTime + 0.16);
            oscillator.connect(gain); gain.connect(audio.destination);
            oscillator.start(); oscillator.stop(audio.currentTime + 0.17);
        } catch (_) {}
    }
    async function request(url, options) {
        const controller = new AbortController();
        const timeout = window.setTimeout(() => controller.abort(), 10000);
        try {
            const response = await fetch(url, { credentials: 'same-origin', signal: controller.signal, ...options });
            const body = await response.json();
            return { response, body };
        } finally { window.clearTimeout(timeout); }
    }
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (state.busy || state.ended) return;
        if (!state.pending) {
            const minor = amount();
            const chance = state.mode === 'under' ? Math.round(Number(target.value) * 100) : 10000 - Math.round(Number(target.value) * 100);
            const valid = Number.isFinite(minor) && minor >= payload.limits.minBet * 100 && minor <= payload.limits.maxBet * 100;
            const withinPayout = !payload.maxPayoutMinor || Math.floor(minor * payload.rtpUnits / chance) <= payload.maxPayoutMinor;
            stake.setAttribute('aria-invalid', String(!valid || !withinPayout));
            if (!valid || !withinPayout) { message(valid ? copy.payout_limit : copy.invalid, true); return; }
            setPending({ sessionToken: payload.sessionToken, requestKey: crypto.randomUUID(),
                betAmount: money(minor / 100), target: Number(target.value).toFixed(2), mode: state.mode });
        }
        state.busy = true; lock(); message(copy.rolling);
        try {
            const { response, body } = await request(payload.rollRoute, { method: 'POST', headers: {
                'Content-Type': 'application/json', Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            }, body: JSON.stringify(state.pending) });
            if (!response.ok || !body.data) {
                const terminal = [401, 403, 404, 410, 419, 451].includes(response.status);
                state.ended = terminal;
                // Busy, throttled or uncertain results retry the SAME request key.
                if (response.status === 422 || terminal) setPending(null);
                message(terminal ? copy.expired : body.message || copy.error, true);
                return;
            }
            const entry = body.data;
            setPending(null);
            state.history = [entry, ...state.history.filter((item) => item.roundId !== entry.roundId)].slice(0, 20);
            showResult(entry); renderHistory(); tone(entry.result.won);
        } catch (_) {
            message(copy.network, true);
        } finally {
            state.busy = false; lock();
        }
    });
    root.querySelectorAll('[data-mode]').forEach((node) => node.addEventListener('click', () => {
        if (state.busy || state.pending || state.ended || node.dataset.mode === state.mode) return;
        state.mode = node.dataset.mode;
        const mirrored = 100 - Number(target.value);
        target.min = String(state.mode === 'under' ? payload.limits.minChance : 100 - payload.limits.maxChance);
        target.max = String(state.mode === 'under' ? payload.limits.maxChance : 100 - payload.limits.minChance);
        target.value = String(mirrored); // Set bounds first, preserving probability at the endpoints too.
        root.querySelectorAll('[data-mode]').forEach((item) => item.setAttribute('aria-pressed', String(item === node)));
        updateOdds();
    }));
    root.querySelectorAll('[data-adjust]').forEach((node) => node.addEventListener('click', () => {
        const current = Number.isFinite(amount()) ? amount() : payload.limits.minBet * 100;
        const next = { minus: current - 10, plus: current + 10, half: Math.floor(current / 2), double: current * 2 }[node.dataset.adjust];
        stake.value = money(Math.max(payload.limits.minBet * 100, Math.min(payload.limits.maxBet * 100, next)) / 100);
        stake.removeAttribute('aria-invalid'); updateOdds();
    }));
    stake.addEventListener('input', () => { stake.removeAttribute('aria-invalid'); updateOdds(); });
    target.addEventListener('input', updateOdds);
    find('sound').addEventListener('click', () => { state.sound = !state.sound; find('sound').setAttribute('aria-pressed', String(state.sound)); tone(true); });
    find('animation').checked = !reduced.matches;
    find('animation').addEventListener('change', () => root.classList.toggle('dice-no-motion', !find('animation').checked));
    root.classList.toggle('dice-no-motion', reduced.matches);
    const settings = root.querySelector('.dice-settings');
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && settings.open) { settings.open = false; settings.querySelector('summary').focus(); } });
    document.addEventListener('pointerdown', (event) => { if (!settings.contains(event.target)) settings.open = false; });
    find('refresh').addEventListener('click', async () => {
        if (state.busy) return;
        find('refresh').disabled = true;
        try {
            const url = new URL(payload.snapshotRoute, location.href);
            url.searchParams.set('sessionToken', payload.sessionToken);
            const { response, body } = await request(url, { headers: { Accept: 'application/json' } });
            if (!response.ok || !body.data) { message(copy.refresh_error, true); return; }
            state.history = body.data.history; renderHistory();
            const recovered = state.pending && state.history.find((entry) => entry.requestKey === state.pending.requestKey);
            if (recovered) { setPending(null); showResult(recovered); }
            text(find('balance'), money(body.data.balance));
        } catch (_) { message(copy.refresh_error, true); }
        finally { lock(); }
    });
    // A reload must retain an uncertain request rather than silently placing a new bet.
    if (state.pending && state.pending.sessionToken === payload.sessionToken) {
        const recovered = state.history.find((entry) => entry.requestKey === state.pending.requestKey);
        if (recovered) { setPending(null); showResult(recovered); }
        else {
            state.mode = state.pending.mode;
            target.min = String(state.mode === 'under' ? payload.limits.minChance : 100 - payload.limits.maxChance);
            target.max = String(state.mode === 'under' ? payload.limits.maxChance : 100 - payload.limits.minChance);
            target.value = state.pending.target; stake.value = state.pending.betAmount;
            root.querySelectorAll('[data-mode]').forEach((item) => item.setAttribute('aria-pressed', String(item.dataset.mode === state.mode)));
            message(copy.network, true);
        }
    } else { setPending(null); }
    if (state.history[0] && !state.pending) showResult(state.history[0], false);
    renderHistory(); updateOdds(); lock();
})();
