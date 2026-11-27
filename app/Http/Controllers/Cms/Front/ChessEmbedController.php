<?php

namespace App\Http\Controllers\Cms\Front;

use App\Http\Controllers\Controller;
use App\Models\CmsUser;
use App\Services\Chess\ChessIntegrationReadiness;
use App\Services\ChessWallet\ChessEmbedService;
use App\Services\OpsRadar\OpsRadarEventEmitter;
use App\Services\Wallet\Exceptions\UnsupportedProductWalletException;
use Illuminate\Http\Request;

class ChessEmbedController extends Controller
{
    public function __construct(
        protected ChessEmbedService $chessEmbedService,
        protected ChessIntegrationReadiness $readiness
    )
    {
    }

    public function show(Request $request)
    {
        $countries=app(\App\Services\Restrictions\ProductCountryRestrictionGuard::class);
        $decision=$countries->decide($request,'chessbet'); if(!$decision->allowed)return $countries->blockedResponse($request,$decision);
        $game=\App\Models\CmsGame::with('provider')->where('slug','chess-bet')->firstOrFail();
        abort_unless(app(\App\Services\Games\CatalogReadService::class)->allowed($game,auth('cms')->user()),404);
        abort_if($game->is_maintenance || $game->provider->is_maintenance,503);
        /** @var CmsUser|null $user */
        $user = auth('cms')->user();
        $embedShellState = $user instanceof CmsUser
            ? $this->readiness->authenticatedEmbedState()
            : $this->readiness->guestEmbedState();

        if (!$embedShellState['ready']) {
            return $this->renderUnavailableShell($embedShellState['message'] ?: 'Chess betting is not ready in this environment.');
        }

        try {
            $viewData = $this->withDebugViewData(
                $user instanceof CmsUser
                    ? $this->chessEmbedService->buildShell($user, $request)
                    : $this->chessEmbedService->buildGuestShell($request)
            );
        } catch (UnsupportedProductWalletException $exception) {
            return $this->renderUnavailableShell($exception->getMessage(), $exception->details());
        }

        // Existing domain evidence remains recorded; optional Ops Radar is outside this transfer.

        return $this->renderChessEmbedView($viewData);
    }

    protected function renderUnavailableShell(string $message, array $walletSupport = [])
    {
        return $this->renderChessEmbedView($this->withDebugViewData([
            'pageTitle' => 'Chess',
            'provider' => null,
            'providerConfig' => [],
            'embedConfig' => [],
            'partnerCode' => null,
            'bootstrapPayload' => [
                'allowedSurfaceType' => 'PUBLIC',
                'sourceOfTruth' => 'cms_not_ready',
            ],
            'bootstrapToken' => null,
            'bootstrapError' => $message,
            'bridgeToken' => null,
            'bridgeExchangeStatus' => 'not_ready',
            'bridgeExchangeMessage' => 'Chess route stayed absence-safe and returned a controlled unavailable shell.',
            'bridgeExpiresAt' => null,
            'iframeSrc' => null,
            'openInNewTabUrl' => null,
            'postMessageOrigin' => null,
            'parentOrigin' => null,
            'embedRequestId' => null,
            'externalSessionRef' => request()->session()->getId() ?: null,
            'walletContextRef' => null,
            'iframeTitle' => 'Chess Betting Surface',
            'guestMode' => true,
            'walletSupportContext' => $walletSupport,
        ]));
    }

    protected function withDebugViewData(array $viewData): array
    {
        if (!(bool) config('chess_wallet.embed.debug_enabled', app()->environment('local'))) {
            $viewData['debugTelemetryUrl'] = null;
            $viewData['debugSessionDetailUrl'] = null;
            $viewData['debugSessionListUrl'] = null;
            $viewData['debugProbeUrl'] = null;
            return $viewData;
        }

        $requestId = $viewData['embedRequestId'] ?? null;
        $viewData['debugTelemetryUrl'] = route('cms.chessEmbed.debug.events');
        $viewData['debugSessionDetailUrl'] = $requestId ? route('cms.chessEmbed.debug.show', ['requestId' => $requestId]) : null;
        $viewData['debugSessionListUrl'] = route('cms.chessEmbed.debug.index');
        $viewData['debugProbeUrl'] = route('cms.chessEmbed.debug.probe');

        return $viewData;
    }

    protected function renderChessEmbedView(array $viewData)
    {
        return response()->view(\App\Support\CmsThemeResolver::viewName(
            'chess-embed',
            \App\Support\CmsThemeResolver::activePhysicalNamespace()
        ), $viewData)->header('Cache-Control','private, no-store')->header('Referrer-Policy','no-referrer');
    }
}
