<?php

namespace Tests\Feature\InternalGames;

use App\Models\{CmsGame, CmsUserTransaction, InternalGameRound, InternalGameSession, WalletLedgerEntry};
use Brick\Math\BigDecimal;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BlackjackFixture;
use Tests\TestCase;

final class CanonicalInternalGameEntryTest extends TestCase
{
    use DatabaseTransactions, BlackjackFixture;

    public static function entryModes(): iterable
    {
        $variants = [
            'dice' => ['dice', 'dice/roll', ['betAmount' => '0.20', 'mode' => 'over', 'target' => '50.00']],
            'european-roulette' => ['roulette-european', 'roulette/spin', ['bets' => [['betType' => 'straight', 'betKey' => '1', 'stake' => '1.00']]]],
            'blackjack-classic' => ['blackjack-classic', 'blackjack/start', ['betAmount' => '10.00']],
            'blackjack-salon' => ['blackjack-salon', 'blackjack/start', ['betAmount' => '25.00']],
            'sky-rush' => ['sky-rush', 'skyrush/bet', ['betAmount' => '10.00', 'lane' => 1]],
            'orbit-x' => ['orbit-x', 'skyrush/bet', ['betAmount' => '10.00', 'lane' => 2]],
            'fusion-reels' => ['mini-slot-classic', 'slot/spin', ['betAmount' => '10.00']],
            'big-win' => ['big-win', 'slot/spin', ['betAmount' => '10.00']],
        ];
        foreach ($variants as $slug => [$code, $endpoint, $input]) {
            foreach (['demo', 'real'] as $mode) {
                yield "$slug-$mode" => [$slug, $code, $endpoint, $input, $mode];
            }
        }
    }

    #[DataProvider('entryModes')]
    public function test_guest_preview_and_member_bet_share_the_entry_contract(
        string $slug, string $code, string $endpoint, array $input, string $mode
    ): void {
        $this->withCredentials();
        $this->setupBlackjack('500.00', false);
        foreach ([\Database\Seeders\InternalDiceDemoSeeder::class, \Database\Seeders\InternalRouletteSeeder::class,
            \Database\Seeders\InternalSlotFamilySeeder::class, \Database\Seeders\InternalCrashFamilySeeder::class] as $seeder) {
            (new $seeder())->run();
        }

        $launch = '/cms/games/'.$slug.'/launch';
        $bet = '/cms/internal-games/'.$endpoint;
        $before = $this->state();
        $this->get('/cms/games/'.$slug)->assertOk()->assertSee('data-launch-demo', false)->assertDontSee('data-launch-real', false);
        $this->post($launch, ['playType' => 'demo'])->assertOk()
            ->assertSee('data-guest-preview="'.$code.'"', false)->assertSee('inert data-preview-surface', false)
            ->assertDontSee('"/cms/internal-games/', false)->assertDontSee('virtual credits', false);
        $this->postJson($launch, ['playType' => 'real'])->assertForbidden();
        $this->postJson($bet, $input + ['sessionToken' => Str::uuid()->toString(), 'requestKey' => 'run30-guest-action'])->assertNotFound();
        self::assertSame($before, $this->state());

        $game = CmsGame::where('slug', $slug)->firstOrFail();
        $old = InternalGameSession::create([
            'public_id' => 'igs_legacy_'.$code, 'session_token' => Str::uuid()->toString(),
            'provider_id' => $game->provider_id, 'cms_game_id' => $game->id, 'game_code' => $code,
            'currency' => 'VC', 'status' => 'active', 'is_demo' => true, 'launched_at' => now(),
            'last_activity_at' => now(), 'expires_at' => now()->addMinutes(15),
            'launch_payload' => ['finance_mode' => 'demo_no_wallet_mutation', 'dice_browser_hash' => hash('sha256', session()->getId())],
        ]);
        $before = $this->state();
        $this->get('/cms/internal-games/play/'.$old->session_token)->assertForbidden();
        $this->postJson($bet, $input + ['sessionToken' => $old->session_token, 'requestKey' => 'run30-legacy-action'])->assertForbidden();
        self::assertSame($before, $this->state());

        $this->postJson('/cms/login', ['email' => $this->player->username, 'password' => 'Blackjack-Local-927!'],
            ['X-Requested-With' => 'XMLHttpRequest'])->assertJsonPath('status', 200);
        $this->withCookie(config('session.cookie'), session()->getId());
        $fundedBefore = $this->financeHash();
        $response = $this->post($launch, ['playType' => $mode])->assertRedirect();
        $this->withCookie(config('session.cookie'), session()->getId());
        $token = basename($response->headers->get('Location'));
        $session = InternalGameSession::where('session_token', $token)->sole();
        self::assertFalse($session->is_demo);
        self::assertSame($code, $session->game_code);
        self::assertSame('TRY', $session->currency);
        self::assertSame($this->wallet->id, (int) $session->launch_payload['wallet_account_id']);
        self::assertSame($this->player->id, (int) $session->cms_user_id);
        self::assertSame('real_wallet', $session->launch_payload['finance_mode']);
        self::assertArrayNotHasKey('dice_initial_balance_minor', $session->launch_payload);
        $this->get('/cms/internal-games/play/'.$token)->assertOk()->assertDontSee('data-guest-preview', false);
        self::assertSame($fundedBefore, $this->financeHash(), 'Opening the game alone cannot debit the wallet.');

        $action = $input + ['sessionToken' => $token, 'requestKey' => 'run30-member-action'];
        $accepted = $this->postJson($bet, $action)->assertOk()->json('data');
        $round = InternalGameRound::where('round_id', $accepted['roundId'])->sole();
        $debit = CmsUserTransaction::findOrFail($round->debit_transaction_id);
        $ledger = WalletLedgerEntry::where('wallet_account_id', $this->wallet->id)
            ->where('meta->cms_user_transaction_id', $debit->id)->sole();
        self::assertFalse($round->is_demo);
        self::assertSame($session->id, (int) $round->internal_game_session_id);
        self::assertSame($this->wallet->id, (int) $debit->source_wallet_account_id);
        self::assertSame($this->player->id, (int) $ledger->cms_user_id);
        self::assertSame('TRY', $ledger->asset);
        self::assertSame('debit', $ledger->direction);
        self::assertTrue(BigDecimal::of($round->bet_amount)->isEqualTo($ledger->amount));
        self::assertTrue(BigDecimal::of($ledger->balance_before)->minus($ledger->amount)->isEqualTo($ledger->balance_after));
        self::assertNotSame($fundedBefore, $this->financeHash());
        $acceptedState = $this->state();
        $this->postJson($bet, $action)->assertOk()->assertJsonPath('data.roundId', $round->round_id);
        self::assertSame($acceptedState, $this->state());
        $this->reconcileBlackjack();
    }

    private function state(): string
    {
        return hash('sha256', $this->financeHash().json_encode(array_map(
            fn (string $table) => DB::table($table)->orderBy('id')->get()->all(),
            ['internal_game_sessions', 'internal_game_rounds', 'internal_game_round_events', 'internal_game_crash_rounds']
        )));
    }
}
