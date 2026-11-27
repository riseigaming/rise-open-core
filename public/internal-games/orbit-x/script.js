let activeLane = 1;
const boot = window.__ORBIT_X_BOOT__ || {};

const canvas = document.getElementById('canvas');
const ctx = canvas.getContext('2d');

let speedX = 3;
let speedY = 1;

canvas.width = 800;
canvas.height = 250;

let x = 0;
let y = canvas.height;

let animationId = requestAnimationFrame(draw);

let dotPath = [];
let counter = 1.0;
let multiplier = 0;
let counterDepo = [];
let cashedOut = false;
let placedBet = false;
let isFlying = false;

const image = new Image();
image.src = '/internal-games/orbit-x/orbiter.png';
image.style.minWidth = '100%';
image.style.width = '100%';

const state = {
    selectedBet: Number((boot.betValues || [])[0] || 0),
    snapshot: {
        balance: boot.balance,
        currency: boot.currency,
        currentRound: boot.currentRound || {},
        history: boot.history || [],
        myBet: boot.myBets?.['1'] || boot.myBet || null,
        myBets: boot.myBets || {'1':boot.myBet || null,'2':null},
        myBetHistory: boot.myBetHistory || [],
        balanceExact: boot.balanceExact,
    },
    submitting: false,
    pollInFlight: false,
};

let balanceAmount = document.getElementById('balanceValue');
let currencyValue = document.getElementById('currencyValue');
let inputCurrency = document.getElementById('inputCurrency');
let betButton = document.getElementById('bet-button');
let lastCounters = document.getElementById('last-counters');
let classNameForCounter = '';
let inputBox = document.getElementById("bet-input");

let invalidChars = ["-", "+", "e", "E"];

inputBox.addEventListener("keydown", function (e) {
    if (invalidChars.includes(e.key)) {
        e.preventDefault();
    }
});

let messageField = document.getElementById('message');
messageField.textContent = 'Wait for the next round';

function requestKey() {
    return (window.crypto && window.crypto.randomUUID)
        ? window.crypto.randomUUID()
        : `${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

function currentAllowedBet() {
    return (boot.betValues || []).map((value) => Number(value)).includes(Number(state.selectedBet));
}

function currentMultiplier() {
    const round = state.snapshot.currentRound || {};

    if (round.status === 'crashed' && round.crashMultiplier) {
        return Number(round.crashMultiplier);
    }

    return Number(round.currentMultiplier || 1);
}

function updateCounterDepo() {
    counterDepo = (state.snapshot.history || []).map((item) => Number(item.crashMultiplier || 0).toFixed(2));

    lastCounters.innerHTML = counterDepo.map(function (i) {
        const numeric = Number(i);

        if ((numeric < 2.00)) {
            classNameForCounter = 'blueBorder';

        } else if ((numeric >= 2) && (numeric < 10)) {

            classNameForCounter = 'purpleBorder';
        } else {
            classNameForCounter = 'burgundyBorder';
        }

        return '<p' + ' class=' + classNameForCounter + '>' + i + '</p>';
    }).join('');
}

inputBox.addEventListener('change', function () {
    const value = Number(inputBox.value || 0);
    if (Number.isFinite(value) && value > 0) {
        state.selectedBet = value;
        syncUi();
    }
});

function syncStateFlags() {
    const round = state.snapshot.currentRound || {};
    const myBet = state.snapshot.myBets?.[String(activeLane)] || null;
    isFlying = round.status === 'flying';
    placedBet = !!(myBet && myBet.status === 'bet_accepted');
    cashedOut = !!(myBet && myBet.status === 'settled_win');
    counter = currentMultiplier();
}

document.querySelectorAll('[data-orbit-lane]').forEach(button=>button.addEventListener('click',()=>{if(state.submitting)return;activeLane=Number(button.dataset.orbitLane);syncUi();}));
function syncUi() {
    state.snapshot.myBet=state.snapshot.myBets?.[String(activeLane)] || null;
    syncStateFlags();
    document.querySelectorAll('[data-orbit-lane]').forEach(button=>{const lane=Number(button.dataset.orbitLane),bet=state.snapshot.myBets?.[String(lane)];button.disabled=state.submitting;button.setAttribute('aria-pressed',String(lane===activeLane));button.textContent=`Lane ${lane} · ${bet?.status || 'ready'}`;});
    const history=document.getElementById('round-history');history.replaceChildren();
    for(const item of (state.snapshot.myBetHistory || [])){const row=document.createElement('p');row.dataset.orbitRound=item.roundId;row.textContent=`Lane ${item.lane} · ${item.status} · bet ${item.betAmount} · return ${item.cashoutAmount || item.refundAmount || 0}`;history.append(row);}

    const round = state.snapshot.currentRound || {};
    const myBet = state.snapshot.myBet;
    const canBet = round.status === 'betting'
        && !myBet
        && Number(state.snapshot.balance || 0) >= Number(state.selectedBet)
        && !state.submitting
        && currentAllowedBet();
    const canCashout = round.status === 'flying'
        && myBet
        && myBet.status === 'bet_accepted'
        && !state.submitting;

    balanceAmount.textContent = state.snapshot.balanceExact || Number(state.snapshot.balance || 0).toFixed(2);
    currencyValue.textContent = state.snapshot.currency || boot.currency;
    inputCurrency.textContent = state.snapshot.currency || boot.currency;
    inputBox.value = String(state.selectedBet);

    if (canCashout || placedBet) {
        betButton.textContent = state.submitting ? 'SUBMITTING...' : 'CASH OUT';
        betButton.classList.add('cashout');
        betButton.disabled = !canCashout;
    } else {
        betButton.textContent = state.submitting ? 'SUBMITTING...' : 'BET';
        betButton.classList.remove('cashout');
        betButton.disabled = !canBet;
    }

    if (!currentAllowedBet()) {
        messageField.textContent = `Allowed bets: ${(boot.betValues || []).join(', ')}`;
    } else if (myBet && myBet.status === 'settled_win') {
        messageField.textContent = myBet.resultSummary || `Bet cashed out: ${(Number(myBet.winAmount || 0)).toFixed(2)}`;
    } else if (myBet && myBet.status === 'settled_loss') {
        messageField.textContent = myBet.resultSummary || 'Crash happened before cashout';
    } else if (round.status === 'betting') {
        messageField.textContent = placedBet ? 'Placed Bet' : 'Place your bet';
    } else if (round.status === 'flying') {
        messageField.textContent = placedBet ? 'Cash out now' : 'Wait for the next round';
    } else if (round.status === 'crashed') {
        messageField.textContent = round.crashMultiplier
            ? `Crashed at ${Number(round.crashMultiplier).toFixed(2)}x`
            : 'Place your bet';
    }

    syncStateFlags();
}

function pathPoint(progress) {
    return {
        x: progress * canvas.width,
        y: canvas.height / 2 + 50 * Math.cos((progress * canvas.width) / 100)
    };
}

function draw() {
    const round = state.snapshot.currentRound || {};
    const target = currentMultiplier();
    const counterNode = document.getElementById('counter');
    const displayed = Number(String(counterNode.textContent || '1').replace('x', '')) || 1;
    const nextDisplay = Math.abs(displayed - target) > 0.01
        ? displayed + ((target - displayed) * 0.12)
        : target;

    counterNode.textContent = nextDisplay.toFixed(2) + 'x';

    ctx.clearRect(0, 0, canvas.width, canvas.height);
    updateCounterDepo();

    const progress = round.status === 'betting'
        ? 0
        : Math.min(1, Math.max(0, (nextDisplay - 1) / 4));
    const point = pathPoint(progress);
    x = point.x;
    y = point.y;

    dotPath = [];
    for (let i = 0; i <= 60; i++) {
        const p = progress * (i / 60);
        dotPath.push(pathPoint(p));
    }

    const canvasOffsetX = canvas.width / 2 - x;
    const canvasOffsetY = canvas.height / 2 - y;

    ctx.save();
    ctx.translate(canvasOffsetX, canvasOffsetY);

    for (let i = 1; i < dotPath.length; i++) {
        ctx.beginPath();
        ctx.strokeStyle = '#dc3545';
        ctx.moveTo(dotPath[i - 1].x, dotPath[i - 1].y);
        ctx.lineTo(dotPath[i].x, dotPath[i].y);
        ctx.stroke();
    }

    ctx.beginPath();
    ctx.fillStyle = '#dc3545';
    ctx.lineWidth = 5;
    ctx.arc(x, y, 1, 0, 2 * Math.PI);
    ctx.fill();

    if (image.complete) {
        ctx.drawImage(image, x - 28, y - 78, 185, 85);
    }

    ctx.restore();
    animationId = requestAnimationFrame(draw);
}

async function postJson(url, body) {
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            'Accept': 'application/json',
        },
        body: JSON.stringify(body),
    });

    const result=await response.json();
    if(!response.ok && !result.message) result.message='Request rejected ('+response.status+')';
    return result;
}

async function refreshSnapshot() {
    if (state.pollInFlight) return;
    state.pollInFlight = true;

    try {
        const url = new URL(boot.snapshotRoute, window.location.origin);
        url.searchParams.set('sessionToken', boot.sessionToken);
        const response = await fetch(url.toString(), {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
        });
        const json = await response.json();

        if (json.status === 200 && json.data) {
            state.snapshot = json.data;
            syncUi();
        } else if (json.message) {
            messageField.textContent = json.message;
        }
    } catch (_error) {
        messageField.textContent = 'Snapshot refresh failed';
    } finally {
        state.pollInFlight = false;
    }
}

betButton.addEventListener('click', () => {
    if (placedBet && isFlying) {
        cashOut();
    } else {
        placeBet();
    }

    if (!placedBet && !isFlying) {
        messageField.textContent = 'Place your bet';
    }
});

async function placeBet() {
    if (placedBet || Number(inputBox.value) === 0 || isNaN(inputBox.value) || isFlying || Number(inputBox.value) > Number(state.snapshot.balance || 0)) {
        messageField.textContent = currentAllowedBet() ? 'Wait for the next round' : `Allowed bets: ${(boot.betValues || []).join(', ')}`;
        return;
    }

    if (!currentAllowedBet()) {
        messageField.textContent = `Allowed bets: ${(boot.betValues || []).join(', ')}`;
        return;
    }

    state.submitting = true;
    syncUi();

    try {
        const json = await postJson(boot.betRoute, {
            sessionToken: boot.sessionToken,
            betAmount: state.selectedBet,
            lane: activeLane,
            crashRoundId: state.snapshot.currentRound.id,
            requestKey: requestKey(),
        });

        if (json.status === 200 && json.data) {
            state.snapshot.balance = json.data.balance;
            state.snapshot.balanceExact = json.data.balanceExact;
            state.snapshot.myBets[String(activeLane)] = {
                roundId: json.data.roundId,
                status: json.data.status,
                betAmount: json.data.betAmount,
                cashoutMultiplier: json.data.cashoutMultiplier || 0,
                cashoutAmount: json.data.cashoutAmount || 0,
                winAmount: json.data.winAmount || 0,
                refundAmount: json.data.refundAmount || 0,
                resultSummary: json.data.summary,
            };
            messageField.textContent = 'Placed Bet';
            await refreshSnapshot();
        } else {
            messageField.textContent = json.message || 'Wait for the next round';
        }
    } catch (_error) {
        messageField.textContent='Connection uncertain. Checking authoritative round...';
        await refreshSnapshot();
    } finally {
        state.submitting = false;
        syncUi();
    }
}

async function cashOut() {
    if (cashedOut || Number(inputBox.value) === 0) {
        messageField.textContent = 'Wait for the next round';
        return;
    }

    state.submitting = true;
    syncUi();

    try {
        const json = await postJson(boot.cashoutRoute, {
            sessionToken: boot.sessionToken,
            betRoundId: state.snapshot.myBet?.roundId,
            requestKey: requestKey(),
        });

        if (json.status === 200 && json.data) {
            state.snapshot.balance = json.data.balance;
            state.snapshot.balanceExact = json.data.balanceExact;
            state.snapshot.myBets[String(activeLane)] = {
                roundId: json.data.roundId,
                status: json.data.status,
                betAmount: json.data.betAmount,
                cashoutMultiplier: json.data.cashoutMultiplier || 0,
                cashoutAmount: json.data.cashoutAmount || 0,
                winAmount: json.data.winAmount || 0,
                refundAmount: json.data.refundAmount || 0,
                resultSummary: json.data.summary,
            };

            cashedOut = true;
            placedBet = false;
            messageField.textContent = `Bet cashed out: ${(Number(json.data.winAmount || 0)).toFixed(2)}`;
            await refreshSnapshot();
        } else {
            messageField.textContent = json.message || "Can't cash out now";
            await refreshSnapshot();
        }
    } catch (_error) {
        messageField.textContent='Connection uncertain. Checking authoritative round...';
        await refreshSnapshot();
    } finally {
        state.submitting = false;
        syncUi();
    }
}

syncUi();
refreshSnapshot();
window.setInterval(() => {
    if (!document.hidden) {
        refreshSnapshot();
    }
}, Math.max(500, Number(boot.snapshotIntervalMs || 750)));
