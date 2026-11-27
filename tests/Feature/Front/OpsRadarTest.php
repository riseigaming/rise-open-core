<?php

namespace Tests\Feature\Front;

use App\Betting\Acceptance\AcceptanceService;
use App\Models\CmsGame;
use App\Models\CmsStaffPermission;
use App\Models\CmsStaffProfile;
use App\Models\ExternalGameSession;
use App\Services\CmsStaff\CmsStaffPermissionService as Permissions;
use App\Services\ExternalGames\ExternalGameCallbackHealthService;
use App\Services\ExternalGames\ExternalGameProviderConfig;
use App\Services\Security\CmsAuthenticatedSessionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Support\SportsbookReviewFixture;
use Tests\TestCase;

final class OpsRadarTest extends TestCase
{
    use DatabaseTransactions, SportsbookReviewFixture;

    private const PATH = '/cms/admin/ops-radar';
    private const A = ExternalGameProviderConfig::PRIMARY;
    private const B = 'radar_fixture_b';

    public function test_provider_status_scope_filtering_redaction_and_read_only_reload(): void
    {
        $this->setupBlackjack();
        $this->establish($this->operator);
        config(['external_games.providers.' . self::A . '.enabled' => true,
            'external_games.providers.' . self::B => ['enabled' => true]]);
        $health = app(ExternalGameCallbackHealthService::class);
        $health->forProvider(self::A)->recordFailure('redact-fixture-secret');
        $health->forProvider(self::B)->recordReplay();
        $a = $this->providerSession(self::A, 'failed', null);
        $b = $this->providerSession(self::B, 'launched', 'idempotent_replay');
        $this->get(self::PATH)->assertOk()->assertSee('Ops Radar')->assertDontSee('redact-fixture-secret');

        $before = $this->recordHash();
        $aResponse = $this->getJson(self::PATH . '?provider=' . self::A)->assertOk();
        $aResponse->assertJsonPath('providers.0.state', 'failure_observed')->assertJsonCount(1, 'rows')
            ->assertJsonPath('rows.0.reference', 'session:' . $a->id)->assertJsonPath('rows.0.state', 'failed');
        $this->getJson(self::PATH . '?provider=' . self::B . '&state=protected')->assertOk()
            ->assertJsonPath('providers.0.replays_24h', 1)->assertJsonCount(1, 'rows')
            ->assertJsonPath('rows.0.reference', 'session:' . $b->id)
            ->assertJsonPath('rows.0.reason', 'callback_replay_recorded')->assertDontSee('redact-fixture-secret');
        $this->getJson(self::PATH . '?provider=' . self::A . '&reference=session:' . $b->id)
            ->assertOk()->assertJsonCount(0, 'rows');
        $this->get($aResponse->json('providers.0.url'))->assertOk();
        $this->assertSame($before, $this->recordHash());
        $this->post(self::PATH)->assertStatus(405);
    }

    public function test_sportsbook_funded_pending_and_authoritative_refund_and_bad_ledger(): void
    {
        $this->setupSportsbookReview();
        $pending = $this->requestReviewCoupon('radar-pending');
        $rejected = $this->requestReviewCoupon('radar-refunded');
        $this->establish($this->operator);
        $acceptance = app(AcceptanceService::class);
        $acceptance->review($pending, 'accept', 'Run33 synthetic acceptance', $this->operator);
        $acceptance->review($rejected, 'reject', 'Run33 synthetic rejection', $this->operator);
        $before = $this->recordHash();
        $row = $this->getJson(self::PATH . '?reference=sportsbook:' . $pending->id)->assertOk()
            ->assertJsonPath('rows.0.state', 'pending')->assertJsonPath('rows.0.reason', 'sportsbook_funded_pending')
            ->assertJsonPath('rows.0.effects.0.direction', 'debit');
        $this->get($row->json('rows.0.url'))->assertOk();
        $this->getJson(self::PATH . '?reference=sportsbook:' . $rejected->id)->assertOk()
            ->assertJsonPath('rows.0.state', 'applied')->assertJsonPath('rows.0.reason', 'sportsbook_rejected_refund_matched')
            ->assertJsonCount(2, 'rows.0.effects');
        $this->assertSame($before, $this->recordHash());

        DB::table('wallet_ledger_entries')->where('entry_type', 'sportsbook_acceptance_refunded')->update(['amount' => '9.00']);
        $this->getJson(self::PATH . '?reference=sportsbook:' . $rejected->id)->assertOk()
            ->assertJsonPath('rows.0.state', 'unknown')->assertJsonPath('rows.0.reason', 'sportsbook_rejected_refund_unverified');
    }

    public function test_nova_intent_receipt_and_conflict_use_existing_rows_and_authorized_readback(): void
    {
        $this->setupBlackjack();
        $this->establish($this->operator);
        $wallet = app(\App\Services\Wallet\AssetWalletService::class)->ensureCryptoWallet($this->player, 'BTC');
        $context = json_encode(['tenant_binding' => 'redact-fixture-secret', 'network_mode' => 'mainnet']);
        $snapshot = json_encode(['status' => 'COMPLETED', 'provider_reference' => 'radar-invoice', 'pay_address' => 'redact-fixture-secret']);
        $base = [
            'cms_user_id' => $this->player->id, 'event_type' => 'nova_crypto_deposit', 'action' => 1,
            'amount' => '0', 'currency' => 'BTC', 'transaction_asset' => 'BTC', 'crypto_deposit_context' => $context,
            'source_wallet_account_id' => $wallet->id, 'created_at' => now(), 'updated_at' => now(),
        ];
        $pending = DB::table('cms_user_transactions')->insertGetId($base + ['transactionId' => 'radar-pending', 'status' => 2]);
        $paid = DB::table('cms_user_transactions')->insertGetId([
            'amount' => '0.001', 'before_balance' => '0', 'after_balance' => '0.001',
            'provider_transaction_id' => 'radar-session', 'execution_context_snapshot' => $snapshot,
        ] + $base + ['transactionId' => 'radar-paid', 'status' => 1]);
        $conflict = DB::table('cms_user_transactions')->insertGetId($base + [
            'transactionId' => 'radar-conflict', 'status' => 2, 'provider_transaction_id' => 'radar-session',
            'execution_context_snapshot' => $snapshot,
        ]);
        DB::table('wallet_ledger_entries')->insert([
            'wallet_account_id' => $wallet->id, 'cms_user_id' => $this->player->id,
            'entry_type' => 'crypto_deposit_credited', 'direction' => 'credit', 'status' => 'applied', 'amount' => '0.001',
            'balance_before' => '0', 'balance_after' => '0.001', 'asset' => 'BTC', 'source_type' => 'cms_user_transaction',
            'source_reference' => 'radar-paid', 'idempotency_key' => 'crypto_deposit_credited:radar-paid',
        ]);
        DB::table('crypto_settlement_claims')->insert([
            'output_fingerprint' => str_repeat('f', 64), 'cms_transaction_id' => $paid, 'provider' => 'bitcart',
            'provider_reference' => 'radar-invoice', 'session_id' => 'radar-session', 'rail_key' => 'btc',
            'asset' => 'BTC', 'network' => 'BTC', 'network_mode' => 'mainnet', 'tx_hash' => str_repeat('a', 64),
            'output_kind' => 'utxo_vout', 'address' => 'redact-fixture-secret', 'exact_amount' => '0.001',
        ]);
        $before = $this->recordHash();
        $this->getJson(self::PATH . '?provider=nova&reference=txn:' . $pending)->assertOk()
            ->assertJsonPath('rows.0.reason', 'nova_intent_unconfirmed');
        $result = $this->getJson(self::PATH . '?reference=txn:' . $paid)->assertOk()
            ->assertJsonPath('rows.0.reason', 'nova_credit_and_receipt_claim_matched')
            ->assertJsonPath('rows.0.duplicate_observation', 'attempt_not_recorded')->assertDontSee('redact-fixture-secret');
        $this->get($result->json('rows.0.url'))->assertOk()->assertSee('radar-paid')->assertDontSee('radar-conflict');
        $this->getJson(self::PATH . '?reference=txn:' . $conflict)->assertOk()
            ->assertJsonPath('rows.0.state', 'protected')->assertJsonPath('rows.0.duplicate_observation', 'ownership_conflict');
        $this->assertSame($before, $this->recordHash());

        DB::table('crypto_settlement_claims')->where('cms_transaction_id', $paid)->delete();
        $this->getJson(self::PATH . '?reference=txn:' . $paid)->assertOk()
            ->assertJsonPath('rows.0.state', 'unknown')->assertJsonPath('rows.0.reason', 'nova_credit_evidence_incomplete');
    }

    public function test_customer_denied_and_provider_operator_has_no_finance_or_cms_data(): void
    {
        $this->setupBlackjack();
        $this->establish($this->player);
        $this->getJson(self::PATH)->assertForbidden();
        CmsStaffProfile::create(['cms_user_id' => $this->player->id, 'active' => true]);
        foreach ([Permissions::ACCESS, Permissions::CATALOG_PROVIDERS] as $permission) {
            CmsStaffPermission::create(['cms_user_id' => $this->player->id, 'permission_key' => $permission, 'enabled' => true]);
        }
        $id = DB::table('cms_user_transactions')->insertGetId([
            'cms_user_id' => $this->player->id, 'transactionId' => 'radar-restricted',
            'event_type' => 'nova_crypto_deposit', 'status' => 2, 'action' => 1, 'amount' => 0,
        ]);
        $this->getJson(self::PATH . '?reference=txn:' . $id)->assertOk()->assertJsonCount(0, 'rows')
            ->assertJsonPath('system.global', null)->assertJsonPath('system.finance_control', 'unknown');
        $this->getJson(self::PATH . '?provider=nova')->assertUnprocessable();
        $this->getJson(self::PATH . '?reference=txn:1%27')->assertUnprocessable();
    }

    public function test_internal_game_chess_and_global_content_use_source_read_models(): void
    {
        $this->setupBlackjack();
        $this->vector();
        $session = $this->openBlackjack();
        $result = app(\App\Services\InternalGames\BlackjackService::class)
            ->startRound($session->session_token, $this->player, 10, 'radar-blackjack');
        $round = \App\Models\InternalGameRound::where('round_id', $result['roundId'])->sole();
        $chess = \App\Models\ChessWalletReservation::create([
            'cms_reservation_id' => 'radar-chess', 'cms_user_id' => $this->player->id,
            'partner_code' => 'local-fixture', 'external_user_id' => 'redact-fixture-secret',
            'idempotency_key' => 'radar-chess', 'placement_intent_id' => 'radar-chess',
            'stake_amount' => '10.00', 'currency' => 'TRY', 'status' => 'reserved',
        ]);
        DB::table('cms_content_pages')->insert(['slug' => 'radar-fixture', 'published' => true, 'translations' => '{}']);
        $this->establish($this->operator);
        $this->getJson(self::PATH . '?reference=round:' . $round->id)->assertOk()
            ->assertJsonPath('rows.0.state', 'pending')->assertJsonPath('rows.0.effects.0.matched', true)
            ->assertJsonPath('system.global.published', 1);
        $chessResult = $this->getJson(self::PATH . '?reference=chess:' . $chess->id)->assertOk()
            ->assertJsonPath('rows.0.state', 'pending')->assertJsonCount(0, 'rows.0.effects')->assertDontSee('redact-fixture-secret');
        $this->get($chessResult->json('rows.0.url'))->assertOk();
    }

    private function providerSession(string $alias, string $status, ?string $callback): ExternalGameSession
    {
        return ExternalGameSession::create([
            'provider_alias' => $alias, 'cms_game_id' => CmsGame::firstOrFail()->id, 'provider_game_id' => 'same-id',
            'cms_user_id' => $this->player->id, 'wallet_currency' => 'TRY', 'session_token' => 'redact-fixture-secret-' . $alias,
            'provider_launch_url' => 'https://example.invalid/redact-fixture-secret', 'status' => $status,
            'last_callback_status' => $callback,
        ]);
    }

    private function establish(\App\Models\CmsUser $user): void
    {
        $this->actingAs($user, 'cms');
        $session = app('session')->driver();
        $session->start();
        $request = Request::create('/');
        $request->setLaravelSession($session);
        app(CmsAuthenticatedSessionService::class)->establish($request, $user, 'run33_test');
        $session->save();
        $this->withCookie(config('session.cookie'), $session->getId());
    }

    private function recordHash(): string
    {
        $tables = ['betting_acceptances', 'betting_settlements', 'external_game_sessions', 'crypto_settlement_claims',
            'wallet_ledger_entries', 'wallet_accounts', 'cms_user_transactions', 'site_settings', 'cms_sensitive_mutation_events'];
        return hash('sha256', json_encode(array_map(fn ($table) => DB::table($table)->orderBy('id')->get()->all(), $tables)));
    }
}
