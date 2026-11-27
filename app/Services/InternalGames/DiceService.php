<?php

namespace App\Services\InternalGames;

use App\Enum\TransactionActionEnum;
use App\Enum\TransactionStatusEnum;
use App\Models\CmsUser;
use App\Models\CmsUserTransaction;
use App\Models\InternalGameRound;
use App\Models\InternalGameSession;
use App\Services\ProductAccess\ProductAccessPolicyService;
use App\Services\Wallet\SpendableWalletContext;
use App\Services\Wallet\SpendableWalletService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Brick\Math\BigDecimal;

/** Canonical Rise Internal Games demo and wallet-backed Dice rounds. */
class DiceService
{
    public function __construct(
        private InternalGameCatalog $catalog,
        private InternalGameSessionService $sessions,
        private InternalGameAuditService $audit,
        private DiceEngine $engine,
        private ProductAccessPolicyService $access,
        private SpendableWalletService $wallets,
        private InternalGameLedgerAuditService $ledgerAudit,
    ) {
    }

    private function assertDiceSession(InternalGameSession $session, ?CmsUser $user): void
    {
        abort_unless($session->game_code === 'dice',404);
        $this->sessions->assertPlayableSession($session,$user);
    }

    public function snapshot(string $token, ?CmsUser $user): array
    {
        return $this->playPayload($this->sessions->resolvePlayableSession($token, $user), $user);
    }

    public function playPayload(InternalGameSession $session, ?CmsUser $user = null): array
    {
        $preview = !$session->exists;
        if (!$preview) {
            $this->assertDiceSession($session, $user);
        }

        return [
            'preview' => $preview,
            'sessionToken' => $session->session_token,
            'gameName' => 'Dice', 'isDemo' => $session->is_demo, 'currency' => $session->is_demo ? 'VC' : $session->currency,
            'balance' => $preview ? null : $this->realBalance($session),
            'rollRoute' => route('cms.internalGames.dice.roll'),
            'snapshotRoute' => route('cms.internalGames.dice.snapshot'),
            'limits' => ['minBet' => DiceEngine::MIN_BET_MINOR / 100, 'maxBet' => DiceEngine::MAX_BET_MINOR / 100,
                'minChance' => DiceEngine::MIN_CHANCE_UNITS / 100, 'maxChance' => DiceEngine::MAX_CHANCE_UNITS / 100],
            'rtpUnits' => DiceEngine::RTP_UNITS,
            'maxPayoutMinor' => $session->is_demo ? null : (int) config('internal_games.games.dice.max_gross_payout_minor', 100000),
            'history' => $preview ? [] : InternalGameRound::query()->where('internal_game_session_id', $session->id)
                ->where('game_code', 'dice')->where('is_demo', $session->is_demo)->whereIn('status', ['settled_win', 'settled_loss'])
                ->latest('id')->limit(20)->get()->map(fn ($round) => $this->responseForRound($round))->all(),
        ];
    }

    public function roll(string $token, ?CmsUser $user, int $stake, string $mode, int $target, string $requestKey): array
    {
        abort_unless(preg_match('/\A[A-Za-z0-9_-]{8,100}\z/', $requestKey), 422, trans('dice.invalid'));
        try {
            $this->engine->payoutMinor($stake, $mode, $target);
        } catch (\InvalidArgumentException) {
            abort(422, trans('dice.invalid'));
        }
        $session = $this->sessions->resolvePlayableSession($token, $user);
        $this->assertDiceSession($session, $user);
        $fingerprint = hash('sha256', json_encode([$stake, $mode, $target]));
        $lock = Cache::lock('internal-dice-roll:' . $session->id, 10);
        abort_unless($lock->get(), 409, trans('dice.busy'));

        try {
            return DB::transaction(function () use ($session, $user, $stake, $mode, $target, $requestKey, $fingerprint) {
                $session = InternalGameSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
                $this->assertDiceSession($session, $user);
                $duplicate = InternalGameRound::query()->where('internal_game_session_id', $session->id)
                    ->where('request_ref', $requestKey)->first();
                if ($duplicate) {
                    abort_unless(hash_equals((string) data_get($duplicate->raw_result, 'fingerprint', ''), $fingerprint), 409, trans('dice.conflict'));

                    return $this->responseForRound($duplicate);
                }
                if (!$session->is_demo) {
                    return $this->settleRealRound($session, $stake, $mode, $target, $requestKey, $fingerprint);
                }
                $balance = $this->balanceMinor($session);
                abort_unless($balance >= $stake, 422, trans('dice.insufficient'));
                $result = $this->engine->settle($stake, $mode, $target, $this->engine->roll());
                $result['balanceAfterMinor'] = $balance - $stake + $result['payoutMinor'];
                $result['fingerprint'] = $fingerprint;
                $round = InternalGameRound::query()->create([
                    'internal_game_session_id' => $session->id, 'provider_id' => $session->provider_id,
                    'cms_game_id' => $session->cms_game_id, 'game_code' => 'dice',
                    'cms_user_id' => null, 'root_user_id' => null, 'is_demo' => true,
                    'round_id' => 'igr_' . Str::upper(Str::random(18)), 'session_public_id' => $session->public_id,
                    'currency' => $session->currency, 'status' => $result['won'] ? 'settled_win' : 'settled_loss',
                    'bet_amount' => $stake / 100, 'win_amount' => $result['payoutMinor'] / 100, 'refund_amount' => 0,
                    'request_ref' => $requestKey, 'correlation_ref' => (string) Str::uuid(),
                    'raw_result' => $result, 'result_summary' => sprintf('%s %.2f / %.2f', $mode, $target / 100, $result['rolled']),
                    'started_at' => now(), 'settled_at' => now(),
                ]);
                $this->audit->logRound($session, $round, 'internal_game.dice.demo_settled', [
                    'request_ref' => $requestKey, 'correlation_ref' => $round->correlation_ref,
                    'finance_mode' => 'demo_no_wallet_mutation', 'bet_minor' => $stake,
                    'payout_minor' => $result['payoutMinor'], 'balance_after_minor' => $result['balanceAfterMinor'],
                ]);

                return $this->responseForRound($round);
            });
        } finally {
            $lock->release();
        }
    }

    private function realBalance(InternalGameSession $session): float
    {
        $wallet = $this->sessions->resolveWalletContextForSession($session);
        abort_unless($wallet instanceof SpendableWalletContext, 404);
        return $this->wallets->currentBalance($wallet);
    }

    /** Entire economic round is atomic on Rise's canonical DB and wallet projection. */
    private function settleRealRound(
        InternalGameSession $session, int $stake, string $mode, int $target,
        string $requestKey, string $fingerprint
    ): array {
        $this->sessions->assertCasinoCountryAllowedForNewGameplay();
        $context = $this->sessions->resolveWalletContextForSession($session, true);
        abort_unless($context instanceof SpendableWalletContext, 404);
        $this->sessions->assertNewGameplayAllowed($session);
        $this->wallets->assertNoDrift($context, 'internal_dice_before_debit', [
            'session_id' => $session->id, 'game_code' => 'dice',
        ]);
        $before = $this->wallets->currentDecimalBalance($context);
        $amount = (string) BigDecimal::of($stake)->dividedBy(100, 2);
        abort_unless(BigDecimal::of($before)->isGreaterThanOrEqualTo($amount), 422, trans('dice.insufficient'));
        // Bound the gross liability, not just the stake.
        abort_unless($this->engine->payoutMinor($stake, $mode, $target) <= (int) config('internal_games.games.dice.max_gross_payout_minor', 100000), 422, trans('dice.payout_limit'));
        $result = $this->engine->settle($stake, $mode, $target, $this->engine->roll());
        $payout = (string) BigDecimal::of($result['payoutMinor'])->dividedBy(100, 2);
        $round = InternalGameRound::query()->create([
            'internal_game_session_id' => $session->id, 'provider_id' => $session->provider_id,
            'cms_game_id' => $session->cms_game_id, 'game_code' => 'dice',
            'cms_user_id' => $session->cms_user_id, 'root_user_id' => $session->root_user_id,
            'is_demo' => false, 'round_id' => 'igr_' . Str::upper(Str::random(18)),
            'session_public_id' => $session->public_id, 'currency' => $session->currency,
            'status' => 'processing', 'bet_amount' => $amount, 'win_amount' => 0,
            'refund_amount' => 0, 'request_ref' => $requestKey, 'correlation_ref' => (string) Str::uuid(),
            'raw_result' => ['fingerprint' => $fingerprint], 'started_at' => now(),
        ]);
        $afterDebit = (string) BigDecimal::of($before)->minus($amount);
        $bet = CmsUserTransaction::query()->create([
            'cms_user_id' => $context->ownerWalletId(),
            'transactionId' => 'bet_' . $round->round_id, 'round_id' => $round->round_id,
            'provider_transaction_id' => $requestKey, 'game_provider_id' => $session->provider_id,
            'game_id' => $session->cms_game_id, 'action' => TransactionActionEnum::Bet,
            'event_type' => 'internal_dice_bet', 'status' => TransactionStatusEnum::Success,
            'amount' => $amount, 'currency' => $session->currency,
            'source_wallet_account_id' => $context->walletAccountId(),
            'source_wallet_type' => $context->walletType(), 'source_currency' => $context->currency(),
            'before_balance' => $before, 'after_balance' => $afterDebit, 'acceptor' => 'api',
        ]);
        $this->wallets->projectDecimalBalance($context, $afterDebit);
        $this->ledgerAudit->recordDecimal($context, $session, $round, $bet,
            'internal_game_bet_debited', 'debit', $amount, $before, $afterDebit, self::class,
            ['request_ref' => $requestKey]);
        $round->debit_transaction_id = $bet->id;
        $after = $afterDebit;
        $win = null;
        if ($payout > 0) {
            $after = (string) BigDecimal::of($afterDebit)->plus($payout);
            $win = CmsUserTransaction::query()->create([
                'cms_user_id' => $context->ownerWalletId(), 'transactionId' => 'win_' . $round->round_id,
                'ref_transaction_id' => $bet->transactionId, 'round_id' => $round->round_id,
                'provider_transaction_id' => $requestKey, 'game_provider_id' => $session->provider_id,
                'game_id' => $session->cms_game_id, 'action' => TransactionActionEnum::Win,
                'event_type' => 'internal_dice_win', 'status' => TransactionStatusEnum::Success,
                'amount' => $payout, 'currency' => $session->currency,
                'source_wallet_account_id' => $context->walletAccountId(),
                'source_wallet_type' => $context->walletType(), 'source_currency' => $context->currency(),
                'before_balance' => $afterDebit, 'after_balance' => $after, 'acceptor' => 'api',
            ]);
            $this->wallets->projectDecimalBalance($context, $after);
            $this->ledgerAudit->recordDecimal($context, $session, $round, $win,
                'internal_game_win_paid', 'credit', $payout, $afterDebit, $after, self::class,
                ['request_ref' => $requestKey]);
            $round->win_transaction_id = $win->id;
        }
        $result['balanceAfterMinor'] = BigDecimal::of($after)->multipliedBy(100)->toBigInteger()->toInt();
        $result['fingerprint'] = $fingerprint;
        $result['linkedTransactions'] = ['bet' => $bet->transactionId, 'win' => $win?->transactionId];
        $round->fill([
            'status' => $result['won'] ? 'settled_win' : 'settled_loss',
            'win_amount' => $payout, 'raw_result' => $result,
            'result_summary' => sprintf('%s %.2f / %.2f', $mode, $target / 100, $result['rolled']),
            'settled_at' => now(),
        ])->save();
        $session->forceFill(['last_activity_at' => now()])->save();
        $this->audit->logRound($session, $round, 'internal_game.dice.real_settled', [
            'request_ref' => $requestKey, 'correlation_ref' => $round->correlation_ref,
            'bet_transaction_id' => $bet->transactionId, 'win_transaction_id' => $win?->transactionId,
            'bet_minor' => $stake, 'payout_minor' => $result['payoutMinor'],
        ] + $context->auditPayload());
        return $this->responseForRound($round);
    }

    private function balanceMinor(InternalGameSession $session): int
    {
        $delta = InternalGameRound::query()->where('internal_game_session_id', $session->id)
            ->where('is_demo', true)->selectRaw('COALESCE(SUM(win_amount + refund_amount - bet_amount), 0) as delta')->value('delta');

        return (int) data_get($session->launch_payload, 'dice_initial_balance_minor', 0) + (int) round((float) $delta * 100);
    }

    private function responseForRound(InternalGameRound $round): array
    {
        return ['roundId' => $round->round_id, 'requestKey' => $round->request_ref, 'betAmount' => (float) $round->bet_amount,
            'winAmount' => (float) $round->win_amount, 'balance' => data_get($round->raw_result, 'balanceAfterMinor', 0) / 100,
            'result' => collect($round->raw_result)->only(['mode', 'target', 'rolled', 'won', 'multiplier', 'winChance'])->all()];
    }
}

