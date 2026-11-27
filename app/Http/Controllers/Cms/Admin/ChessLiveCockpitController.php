<?php

namespace App\Http\Controllers\Cms\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChessBettingMarket;
use App\Models\ChessLiveBoard;
use App\Services\Chess\ChessIntegrationReadiness;
use App\Services\ChessLiveCockpit\ChessBettingMarketActionService;
use App\Services\ChessLiveCockpit\ChessBettingMarketStateMachine;
use App\Services\ChessLiveCockpit\ChessLiveCockpitViewService;
use App\Services\Wallet\ProductWalletCapabilityService;
use App\Services\Wallet\ProductWalletOverrideSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class ChessLiveCockpitController extends Controller
{
    /** @var ChessLiveCockpitViewService */
    private $viewService;

    /** @var ChessBettingMarketActionService */
    private $actionService;

    public function __construct(
        ChessLiveCockpitViewService $viewService,
        ChessBettingMarketActionService $actionService
    ) {
        $this->viewService = $viewService;
        $this->actionService = $actionService;
    }

    public function index(
        Request $request,
        ProductWalletCapabilityService $productWallets,
        ProductWalletOverrideSettingsService $walletOverrideSettings
    )
    {
        if (!$this->adminState()['ready']) {
            return view('cms.admin.betting.live-chess-cockpit', [
                'pageTitle' => __('admin.chess_cockpit.title'),
                'isReady' => false,
                'isMonitoringOnly' => $this->isMonitoringOnly(),
                'chessWalletSupportSettings' => $this->walletSupportSettingsViewData($productWallets, $walletOverrideSettings),
            ]);
        }

        return view('cms.admin.betting.live-chess-cockpit', array_merge([
            'pageTitle' => __('admin.chess_cockpit.title'),
            'isReady' => true,
            'isMonitoringOnly' => $this->isMonitoringOnly(),
            'pendingChessReservations' => app(\App\Betting\Settlement\PendingChessSettlementReadService::class)->rows(app(\App\Betting\Settlement\PendingChessSettlementReadService::class)->filters($request->all())),
            'chessRecentReservations' => \Illuminate\Support\Facades\Schema::hasTable('chess_wallet_reservations') ? \App\Models\ChessWalletReservation::with('user')->latest('id')->limit(50)->get() : collect(),
            'chessWalletSupportSettings' => $this->walletSupportSettingsViewData($productWallets, $walletOverrideSettings),
        ], $this->viewService->build((int) $request->query('board', 0) ?: null)));
    }

    public function updateWalletSupport(
        Request $request,
        ProductWalletCapabilityService $productWallets,
        ProductWalletOverrideSettingsService $walletOverrideSettings
    ): RedirectResponse {
        $options = collect($productWallets->configurableAssetOptionsForProduct('chess'))
            ->pluck('value')
            ->all();

        $validated = $request->validate([
            'supported_wallet_assets' => ['nullable', 'array'],
            'supported_wallet_assets.*' => ['string', 'in:' . implode(',', $options)],
            'board' => ['nullable', 'integer'],
        ]);

        $walletOverrideSettings->persist(
            'chess',
            array_values((array) ($validated['supported_wallet_assets'] ?? [])),
            auth('cms')->user()
        );

        return redirect()->route('cms.admin.betting.liveChessCockpit', [
            'board' => (int) ($validated['board'] ?? 0) ?: null,
        ])->with('betting_status', __('admin.chess_cockpit.wallet_settings_updated'));
    }

    public function openFromTemplate(Request $request): RedirectResponse
    {
        if ($redirect = $this->abortWhenUnavailable()) {
            return $redirect;
        }

        $validated = $request->validate([
            'board_id' => ['required', 'integer'],
            'template_key' => ['required', 'string'],
            'target_status' => ['required', 'in:' . ChessBettingMarketStateMachine::STATUS_OPEN . ',' . ChessBettingMarketStateMachine::STATUS_DRAFT],
        ]);

        $board = ChessLiveBoard::query()->findOrFail($validated['board_id']);
        $this->actionService->createFromTemplate(
            $board,
            $validated['template_key'],
            $validated['target_status'],
            auth('cms')->user()
        );

        return redirect()->route('cms.admin.betting.liveChessCockpit', [
            'board' => $board->id,
        ])->with('betting_status', __('admin.chess_cockpit.template_created'));
    }

    public function featureBoard(ChessLiveBoard $board): RedirectResponse
    {
        if ($redirect = $this->abortWhenUnavailable()) {
            return $redirect;
        }

        $this->updateBoardEditorialState($board, [
            'featured_flag' => true,
        ]);

        return $this->backToEditorialBoard($board, __('admin.chess_cockpit.board_featured'));
    }

    public function unfeatureBoard(ChessLiveBoard $board): RedirectResponse
    {
        if ($redirect = $this->abortWhenUnavailable()) {
            return $redirect;
        }

        $this->updateBoardEditorialState($board, [
            'featured_flag' => false,
        ]);

        return $this->backToEditorialBoard($board, __('admin.chess_cockpit.board_unfeatured'));
    }

    public function publishBoard(ChessLiveBoard $board): RedirectResponse
    {
        if ($redirect = $this->abortWhenUnavailable()) {
            return $redirect;
        }

        $this->updateBoardEditorialState($board, [], [
            'is_published' => true,
        ]);

        return $this->backToEditorialBoard($board, __('admin.chess_cockpit.board_published'));
    }

    public function unpublishBoard(ChessLiveBoard $board): RedirectResponse
    {
        if ($redirect = $this->abortWhenUnavailable()) {
            return $redirect;
        }

        $this->updateBoardEditorialState($board, [], [
            'is_published' => false,
        ]);

        return $this->backToEditorialBoard($board, __('admin.chess_cockpit.board_unpublished'));
    }

    public function hideBoardFromBettingSurface(ChessLiveBoard $board): RedirectResponse
    {
        if ($redirect = $this->abortWhenUnavailable()) {
            return $redirect;
        }

        $this->updateBoardEditorialState($board, [], [
            'is_hidden_from_betting_surface' => true,
        ]);

        return $this->backToEditorialBoard($board, __('admin.chess_cockpit.board_hidden'));
    }

    public function showBoardOnBettingSurface(ChessLiveBoard $board): RedirectResponse
    {
        if ($redirect = $this->abortWhenUnavailable()) {
            return $redirect;
        }

        $this->updateBoardEditorialState($board, [], [
            'is_hidden_from_betting_surface' => false,
        ]);

        return $this->backToEditorialBoard($board, __('admin.chess_cockpit.board_visible'));
    }

    public function open(ChessBettingMarket $market): RedirectResponse
    {
        if ($redirect = $this->abortWhenUnavailable()) {
            return $redirect;
        }

        $market = $this->actionService->openDraft($market, auth('cms')->user());

        return $this->backToBoard($market, __('admin.chess_cockpit.market_opened'));
    }

    public function suspend(ChessBettingMarket $market): RedirectResponse
    {
        if ($redirect = $this->abortWhenUnavailable()) {
            return $redirect;
        }

        $market = $this->actionService->suspend($market, auth('cms')->user());

        return $this->backToBoard($market, __('admin.chess_cockpit.market_suspended'));
    }

    public function reopen(ChessBettingMarket $market): RedirectResponse
    {
        if ($redirect = $this->abortWhenUnavailable()) {
            return $redirect;
        }

        $market = $this->actionService->reopen($market, auth('cms')->user());

        return $this->backToBoard($market, __('admin.chess_cockpit.market_reopened'));
    }

    public function close(ChessBettingMarket $market): RedirectResponse
    {
        if ($redirect = $this->abortWhenUnavailable()) {
            return $redirect;
        }

        $market = $this->actionService->close($market, auth('cms')->user());

        return $this->backToBoard($market, __('admin.chess_cockpit.market_closed'));
    }

    public function settle(Request $request, ChessBettingMarket $market): RedirectResponse
    {
        if ($redirect = $this->abortWhenUnavailable()) {
            return $redirect;
        }

        $validated = $request->validate([
            'result_selection_code' => ['required', 'string'],
            'settle_reason' => ['required', 'string'],
            'settle_note' => ['nullable', 'string'],
        ]);

        $market = $this->actionService->settle(
            $market,
            $validated['result_selection_code'],
            $validated['settle_reason'],
            $validated['settle_note'] ?? null,
            auth('cms')->user()
        );

        return $this->backToBoard($market, __('admin.chess_cockpit.market_settled'));
    }

    public function void(Request $request, ChessBettingMarket $market): RedirectResponse
    {
        if ($redirect = $this->abortWhenUnavailable()) {
            return $redirect;
        }

        $validated = $request->validate([
            'note' => ['nullable', 'string'],
        ]);

        $market = $this->actionService->void($market, auth('cms')->user(), $validated['note'] ?? null);

        return $this->backToBoard($market, __('admin.chess_cockpit.market_voided'));
    }

    public function updateNote(Request $request, ChessBettingMarket $market): RedirectResponse
    {
        if ($redirect = $this->abortWhenUnavailable()) {
            return $redirect;
        }

        $validated = $request->validate([
            'note' => ['required', 'string'],
        ]);

        $market = $this->actionService->updateNote($market, $validated['note'], auth('cms')->user());

        return $this->backToBoard($market, __('admin.chess_cockpit.market_note_updated'));
    }

    private function backToBoard(ChessBettingMarket $market, string $message): RedirectResponse
    {
        return redirect()->route('cms.admin.betting.liveChessCockpit', [
            'board' => $market->chess_live_board_id,
        ])->with('betting_status', $message);
    }

    private function backToEditorialBoard(ChessLiveBoard $board, string $message): RedirectResponse
    {
        return redirect()->route('cms.admin.betting.liveChessCockpit', [
            'board' => $board->id,
        ])->with('betting_status', $message);
    }

    private function updateBoardEditorialState(ChessLiveBoard $board, array $attributes = [], array $editorialOverrides = []): void
    {
        abort_if(app(\App\Services\ChessLiveCockpit\ChessLiveCockpitShadowMode::class)->enabled(),409,'Shadow mode blocks public visibility writes.');
        $meta = (array) $board->meta;
        $editorial = array_merge([
            'is_published' => (bool) Arr::get($meta, 'editorial.is_published', true),
            'is_hidden_from_betting_surface' => (bool) Arr::get($meta, 'editorial.is_hidden_from_betting_surface', false),
        ], $editorialOverrides);

        $meta['editorial'] = $editorial;

        ChessLiveBoard::query()
            ->whereKey($board->id)
            ->update(array_merge($attributes, [
                'meta' => json_encode($meta),
            ]));
    }

    private function adminState(): array
    {
        return app(ChessIntegrationReadiness::class)->adminState();
    }

    private function isMonitoringOnly(): bool
    {
        return (bool)config('chess_live_cockpit.monitoring_only',true);
    }

    private function abortWhenUnavailable(): ?RedirectResponse
    {
        if ($this->isMonitoringOnly()) {
            return redirect()->route('cms.admin.betting.liveChessCockpit')
                ->with('betting_status', __('admin.chess_cockpit.monitoring_only'));
        }

        $state = $this->adminState();

        if ($state['ready']) {
            return null;
        }

        return redirect()->route('cms.admin.betting.liveChessCockpit')
            ->with('betting_status', $state['message'] ?: __('admin.chess_cockpit.not_ready'));
    }

    private function walletSupportSettingsViewData(
        ProductWalletCapabilityService $productWallets,
        ProductWalletOverrideSettingsService $walletOverrideSettings
    ): array {
        return [
            'options' => $productWallets->configurableAssetOptionsForProduct('chess'),
            'selectedAssets' => $walletOverrideSettings->selectedAssets('chess'),
            'sourceLabel' => $walletOverrideSettings->sourceLabel('chess'),
            'settingsKey' => ProductWalletOverrideSettingsService::SETTINGS_KEY,
        ];
    }
}
