<?php

namespace Tests\Feature\Front;

use App\Models\ChessWalletReservation;
use App\Models\CmsUser;
use App\Services\ChessWallet\ChessWalletProviderResolver;
use App\Services\ChessWallet\ChessWalletReservationService;
use App\Services\Security\CmsAuthenticatedSessionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BlackjackFixture;
use Tests\TestCase;

final class OpsRadarChessLedgerTest extends TestCase
{
    use DatabaseTransactions, BlackjackFixture;

    private array $partner;
    private array $payload;
    private ChessWalletReservation $reservation;
    private array $reserved;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupBlackjack();
        (new \Database\Seeders\HostedChessCatalogSeeder)->run();
        config(['chess_integration.wallet_enabled' => true, 'chess_wallet.partner_overrides' => [
            'local-owned' => ['secret' => 'test-only-local-chess-secret', 'allow_settlement_handoff' => true],
        ]]);
        $this->partner = app(ChessWalletProviderResolver::class)->resolvePartnerContext('local-owned');
        $this->payload = ['externalUserId' => (string) $this->player->id, 'placementIntentId' => 'radar-revision',
            'amount' => '10.01', 'currency' => 'TRY', 'marketInstanceId' => 'hosted-contract-market',
            'selectionId' => 'source-selection', 'quoteExpiresAt' => now()->addMinutes(2)->toAtomString(),
            'walletAccountId' => $this->wallet->id];
        $this->reserved = app(ChessWalletReservationService::class)->reserve($this->payload, $this->partner, 'request', 'radar-revision');
        $this->assertSame(200, $this->reserved['httpStatus']);
        $this->reservation = ChessWalletReservation::where('cms_reservation_id', $this->reserved['body']['cmsReservationId'])->sole();
        $this->actingAs($this->operator, 'cms');
        $session = app('session')->driver();
        $session->start();
        $request = Request::create('/');
        $request->setLaravelSession($session);
        app(CmsAuthenticatedSessionService::class)->establish($request, $this->operator, 'run34_revision_test');
        $session->save();
        $this->withCookie(config('session.cookie'), $session->getId());
    }

    public function test_reserve_win_and_replay_match_distinct_ledgers_without_read_mutation(): void
    {
        $this->radar()->assertJsonPath('rows.0.state', 'pending')->assertJsonCount(1, 'rows.0.effects')
            ->assertJsonPath('rows.0.effects.0.matched', true)->assertJsonPath('rows.0.effects.0.direction', 'debit');
        $before = $this->financeHash();
        $this->assertSame($this->reserved, app(ChessWalletReservationService::class)
            ->reserve($this->payload, $this->partner, 'replay', 'radar-revision'));
        $this->assertSame($before, $this->financeHash());
        $handoff = ['cmsReservationId' => $this->reservation->cms_reservation_id, 'placedBetId' => 'placed-radar',
            'resultStatus' => 'WIN', 'payoutAmount' => '22.53', 'settlementSummary' => ['source' => 'controlled_contract_vector'],
            'settledAt' => now()->toAtomString()];
        $service = app(ChessWalletReservationService::class);
        $this->assertSame(200, $service->handoffSettlement($handoff, 'win', $this->partner)['httpStatus']);
        $before = $this->financeHash();
        $this->assertSame(200, $service->handoffSettlement($handoff, 'replay', $this->partner)['httpStatus']);
        $this->assertSame($before, $this->financeHash());
        $this->assertSame(2, DB::table('wallet_ledger_entries')->where('source_type', 'chess_reservation')
            ->where('source_reference', $this->reservation->cms_reservation_id)->count());
        $response = $this->radar()->assertJsonCount(2, 'rows.0.effects')
            ->assertJsonPath('rows.0.effects.0.matched', true)->assertJsonPath('rows.0.effects.1.matched', true)
            ->assertJsonPath('rows.0.effects.1.direction', 'credit')->assertJsonPath('rows.0.effects.1.amount', '22.53');
        $effects = $response->json('rows.0.effects');
        $this->assertNotSame($effects[0]['ledger_reference'], $effects[1]['ledger_reference']);
        $this->getJson('/cms/admin/ops-radar?reference=txn:' . $this->reservation->reserve_transaction_id)
            ->assertOk()->assertJsonPath('rows.0.state', 'applied');
        $this->assertSame($before, $this->financeHash());
        $this->reconcileBlackjack();
    }

    #[DataProvider('refundKinds')]
    public function test_release_and_void_use_their_own_transaction_entry_types(string $kind): void
    {
        $service = app(ChessWalletReservationService::class);
        $payload = ['cmsReservationId' => $this->reservation->cms_reservation_id];
        $result = $kind === 'release' ? $service->release($payload, $this->partner, 'release')
            : $service->handoffSettlement($payload + ['resultStatus' => 'VOID', 'payoutAmount' => '0.00',
                'placedBetId' => 'placed-void', 'settlementSummary' => ['source' => 'controlled_contract_vector'], 'settledAt' => now()->toAtomString()], 'void', $this->partner);
        $this->assertSame(200, $result['httpStatus'], json_encode($result));
        $this->radar()->assertJsonCount(2, 'rows.0.effects')->assertJsonPath('rows.0.effects.0.matched', true)
            ->assertJsonPath('rows.0.effects.1.matched', true)->assertJsonPath('rows.0.effects.1.direction', 'credit')
            ->assertJsonPath('rows.0.effects.1.amount', '10.01');
        $this->reconcileBlackjack();
    }

    public static function refundKinds(): array
    {
        return [['release'], ['void']];
    }

    #[DataProvider('invalidEvidence')]
    public function test_missing_or_inconsistent_chess_evidence_never_matches(string $kind): void
    {
        $ledger = DB::table('wallet_ledger_entries')->where('source_type', 'chess_reservation');
        $transaction = DB::table('cms_user_transactions')->where('id', $this->reservation->reserve_transaction_id);
        switch ($kind) {
            case 'missing': $ledger->delete(); break;
            case 'owner': $ledger->update(['cms_user_id' => $this->operator->id]); break;
            case 'wallet':
                $other = DB::transaction(fn () => app(\App\Services\Wallet\AssetWalletService::class)
                    ->ensurePrimaryBalanceWallet($this->operator, 'TRY', true));
                $ledger->update(['wallet_account_id' => $other->id]); break;
            case 'type': $ledger->update(['entry_type' => 'chess_settlement_win']); break;
            case 'direction': $ledger->update(['direction' => 'credit']); break;
            case 'asset': $ledger->update(['asset' => 'USD']); break;
            case 'amount': $ledger->update(['amount' => '10.02']); break;
            case 'before': $ledger->update(['balance_before' => '499.99']); break;
            case 'idempotency': $ledger->update(['idempotency_key' => 'other-transaction']); break;
            case 'source': $ledger->update(['source_reference' => 'other-reservation']); break;
            case 'transaction-owner': $transaction->update(['cms_user_id' => $this->operator->id]); break;
            case 'transaction-ref': $transaction->update(['ref_transaction_id' => 'other-intent']); break;
            case 'transaction-event': $transaction->update(['event_type' => 'chess_release']); break;
            case 'reservation-wallet': $this->reservation->update(['request_payload' => ['wallet_account_id' => 999999]]); break;
            case 'reservation-owner': $this->reservation->update(['cms_user_id' => $this->operator->id]); break;
            case 'unbound': $this->reservation->update(['reserve_transaction_id' => null]); break;
        }
        $before = $this->financeHash();
        $this->getJson('/cms/admin/ops-radar?reference=txn:' . $transaction->value('id'))->assertOk()
            ->assertJsonPath('rows.0.effects.0.matched', false)->assertJsonPath('rows.0.effects.0.ledger_reference', null);
        $this->assertSame($before, $this->financeHash());
    }

    public static function invalidEvidence(): array
    {
        return array_map(fn ($kind) => [$kind], ['missing', 'owner', 'wallet', 'type', 'direction', 'asset',
            'amount', 'before', 'idempotency', 'source', 'transaction-owner', 'transaction-ref',
            'transaction-event', 'reservation-wallet', 'reservation-owner', 'unbound']);
    }

    private function radar(): \Illuminate\Testing\TestResponse
    {
        $before = $this->financeHash();
        $response = $this->getJson('/cms/admin/ops-radar?reference=chess:' . $this->reservation->id)->assertOk();
        $this->assertSame($before, $this->financeHash());
        return $response;
    }
}
