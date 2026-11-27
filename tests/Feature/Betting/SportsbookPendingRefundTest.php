<?php

namespace Tests\Feature\Betting;

use App\Betting\Acceptance\AcceptanceService;
use App\Betting\Exceptions\AcceptanceReviewConflictException;
use App\Models\{BettingAcceptance, CmsUserTransaction, WalletLedgerEntry, WalletUserPreference};
use App\Services\Wallet\{ActiveWalletService, AssetWalletService};
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\SportsbookReviewFixture;
use Tests\TestCase;

final class SportsbookPendingRefundTest extends TestCase
{
    use DatabaseTransactions, SportsbookReviewFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupSportsbookReview();
    }

    private function loginOperator(): void
    {
        $credentials = json_decode(file_get_contents(getenv('TEST_ADMIN_CREDENTIALS_FILE')), true, flags: JSON_THROW_ON_ERROR);
        $this->login($credentials['username'], $credentials['password']);
    }

    private function login(string $username, string $password): void
    {
        $this->postJson('/cms/login', ['email' => $username, 'password' => $password], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertJsonPath('status', 200);
        $this->withCookie(config('session.cookie'), session()->getId());
    }

    public function test_authorized_reject_refunds_the_persisted_debit_and_http_replays_do_not_credit(): void
    {
        $coupon = $this->requestReviewCoupon('run28-reject');
        $this->assertSame('490.00000000', $this->wallet->fresh()->available_balance);
        $debit = CmsUserTransaction::where('ref_transaction_id', $coupon->acceptance_ref)->sole();
        $this->loginOperator();
        $url = route('cms.admin.sportsbook.review', $coupon->id);
        $this->postJson($url, ['decision' => 'reject', 'reason' => 'Synthetic authorized rejection'])->assertOk();
        $this->assertSame('rejected', $coupon->fresh()->status);
        $this->assertSame('500.00000000', $this->wallet->fresh()->available_balance);
        $refund = CmsUserTransaction::where('event_type', 'sportsbook_acceptance_refund')->sole();
        $entry = WalletLedgerEntry::where('entry_type', 'sportsbook_acceptance_refunded')->sole();
        $this->assertSame($debit->transactionId, $refund->ref_transaction_id);
        $this->assertSame($refund->transactionId, $entry->source_reference);
        $this->assertEquals($this->player->id, $entry->cms_user_id);
        $this->assertEquals($this->wallet->id, $entry->wallet_account_id);
        $this->assertSame('10.00000000', $entry->amount);
        $this->assertSame('TRY', $entry->asset);
        $this->assertSame('credit', $entry->direction);

        $hash = $this->financeHash();
        $this->postJson($url, ['decision' => 'reject', 'reason' => 'Synthetic authorized rejection'])->assertConflict();
        foreach (['reject', 'cancel', 'accept'] as $decision) {
            $this->postJson($url, ['decision' => $decision, 'reason' => 'Different retry payload'])->assertConflict();
        }
        $this->assertSame($hash, $this->financeHash());
        $this->reconcileBlackjack();
    }

    public function test_authorized_cancellation_refunds_original_wallet_despite_changed_preference(): void
    {
        $coupon = $this->requestReviewCoupon('run28-cancel');
        $other = app(AssetWalletService::class)->ensureCryptoWallet($this->player, 'BTC', true);
        $this->assertNotNull($other);
        // Model a changed persisted preference even on profiles that block switching with open bets.
        WalletUserPreference::updateOrCreate(['cms_user_id' => $this->player->id], [
            'active_wallet_account_id' => $other->id, 'changed_at' => now(),
        ]);
        $this->assertEquals($other->id, app(ActiveWalletService::class)->resolveForUser($this->player)->id);
        $this->loginOperator();
        $this->postJson(route('cms.admin.sportsbook.review', $coupon->id), [
            'decision' => 'cancel', 'reason' => 'Synthetic authorized cancellation',
        ])->assertOk();
        $this->assertSame('cancelled', $coupon->fresh()->status);
        $this->assertSame('500.00000000', $this->wallet->fresh()->available_balance);
        $this->assertSame('0.00000000', $other->fresh()->available_balance);
        $this->assertEquals($this->wallet->id, WalletLedgerEntry::where('entry_type', 'sportsbook_acceptance_refunded')->sole()->wallet_account_id);
    }

    public static function corruptions(): array
    {
        return array_map(static fn (string $case): array => [$case], [
            'missing debit', 'missing ledger', 'foreign debit owner', 'foreign pinned wallet', 'missing pinned wallet',
            'wrong debit amount', 'wrong exact amount', 'wrong ledger source', 'wrong ledger asset', 'wrong ledger amount',
        ]);
    }

    #[DataProvider('corruptions')]
    public function test_corrupt_provenance_keeps_the_coupon_open_and_does_not_write(string $case): void
    {
        $coupon = $this->requestReviewCoupon('run28-corrupt');
        $debit = DB::table('cms_user_transactions')->where('ref_transaction_id', $coupon->acceptance_ref);
        $entry = DB::table('wallet_ledger_entries')->where('idempotency_key', 'sportsbook_acceptance:' . $coupon->acceptance_ref);
        match ($case) {
            'missing debit' => $debit->delete(),
            'missing ledger' => $entry->delete(),
            'foreign debit owner' => $debit->update(['cms_user_id' => $this->operator->id]),
            'foreign pinned wallet' => $this->changePinnedWallet($coupon, app(AssetWalletService::class)->ensurePrimaryBalanceWallet($this->operator, 'TRY', true)->id),
            'missing pinned wallet' => $this->changePinnedWallet($coupon, null),
            'wrong debit amount' => $debit->update(['amount' => '9.99']),
            'wrong exact amount' => $debit->update(['exact_amount' => '9.99']),
            'wrong ledger source' => $entry->update(['source_reference' => 'foreign-debit']),
            'wrong ledger asset' => $entry->update(['asset' => 'USD']),
            'wrong ledger amount' => $entry->update(['amount' => '9.99']),
        };
        $hash = $this->financeHash();
        try {
            app(AcceptanceService::class)->review($coupon, 'reject', 'Corrupt fixture', $this->operator);
            $this->fail('A damaged provenance chain must fail closed.');
        } catch (AcceptanceReviewConflictException) {
            $this->assertSame('pending', $coupon->fresh()->status);
            $this->assertSame($hash, $this->financeHash());
        }
    }

    private function changePinnedWallet(BettingAcceptance $coupon, ?int $id): void
    {
        $coupon->update(['context' => array_replace($coupon->context, ['wallet_account_id' => $id])]);
    }

    public function test_database_ledger_insert_failure_rolls_back_wallet_transaction_and_status(): void
    {
        $coupon = $this->requestReviewCoupon('run28-ledger-failure');
        $before = $this->financeHash();
        $events = WalletLedgerEntry::getEventDispatcher();
        WalletLedgerEntry::setEventDispatcher(clone $events);
        WalletLedgerEntry::creating(function (WalletLedgerEntry $entry): void {
            if ($entry->entry_type === 'sportsbook_acceptance_refunded') {
                $entry->amount = null; // Exercise the actual MariaDB NOT NULL constraint.
            }
        });

        try {
            app(AcceptanceService::class)->review($coupon, 'reject', 'Force failed ledger insert', $this->operator);
            $this->fail('Expected ledger insert constraint failure.');
        } catch (QueryException $e) {
            $this->assertSame(1048, $e->errorInfo[1]);
            $this->assertSame('pending', $coupon->fresh()->status);
            $this->assertNull($coupon->fresh()->reviewed_at);
            $this->assertSame($before, $this->financeHash());
        } finally {
            WalletLedgerEntry::setEventDispatcher($events);
        }
    }

    public function test_accepted_coupon_stays_pending_and_base_post_is_denied_after_event(): void
    {
        $coupon = $this->requestReviewCoupon('run28-pending');
        $coupon = app(AcceptanceService::class)->review($coupon, 'accept', 'Synthetic acceptance', $this->operator);
        $this->assertSame('accepted', $coupon->status);
        $this->assertSame('pending', $coupon->settlement->result_code);
        $before = $this->financeHash();

        try {
            Carbon::setTestNow(now()->addDays(2));
            $this->loginOperator();
            $post = route('cms.admin.sportsbook.settle', $coupon->settlement->id);
            $this->postJson($post, ['result' => 'won', 'reason' => 'After the synthetic event'])->assertForbidden();
            $this->get(route('cms.admin.betting.acceptances.show', $coupon->id))
                ->assertOk()->assertSee($coupon->acceptance_ref)->assertSee('10.000000 TRY')
                ->assertSee('datetime="' . $coupon->requested_at->toIso8601String() . '"', false)
                ->assertDontSee($post);
            $this->get('/cms/logout')->assertRedirect();
            auth('cms')->forgetUser();
            $this->login($this->player->username, 'Blackjack-Local-927!');
            $this->get(route('cms.front.sportsbook.history'))
                ->assertOk()->assertSee($coupon->acceptance_ref)->assertSee('10.000000 TRY')
                ->assertSee('accepted')->assertSee('pending')
                ->assertSee('datetime="' . $coupon->requested_at->toIso8601String() . '"', false);
            $this->assertSame('pending', $coupon->settlement->fresh()->status);
            $this->assertSame($before, $this->financeHash());
        } finally {
            Carbon::setTestNow();
        }
    }
}
