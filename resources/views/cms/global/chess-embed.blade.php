@php($disableDepositAlert = true)
@php($disableSocialPanel = false)
@php($chessEmbedShellActive = true)
@php($frontLayoutMode = 'immersive')
@php($frontLayoutFlags = [
    'hide_preloader' => true,
    'hide_footer' => true,
    'hide_top_campaign_line' => true,
])

@extends("cms.global.index")

@section("pageContent")
    <style>
        .chessEmbedPageOuter {
            width: 100%;
            min-height: auto;
            float: none;
            clear: both;
            padding-top: clamp(6px, 1vw, 10px);
            padding-bottom: clamp(8px, 1.2vw, 12px);
            overflow-x: hidden;
        }

        .chessEmbedPageContainer {
            width: 100%;
            max-width: none !important;
            padding-left: clamp(8px, 1.1vw, 14px);
            padding-right: clamp(8px, 1.1vw, 14px);
            box-sizing: border-box;
        }

        .chess-embed-shell {
            display: flex;
            flex-direction: column;
            gap: 8px;
            width: 100%;
            max-width: none;
            padding: 0;
            margin: 0 auto;
        }

        .chess-embed-stage {
            background: var(--global-surface, #101b2b);
            border: 1px solid var(--global-border, rgba(255,255,255,.15));
            border-radius: 12px;
            box-shadow: 0 12px 32px rgba(8, 15, 34, 0.28);
        }

        .chess-embed-footer,
        .chess-embed-state p,
        .embedUnavailableState__message {
            margin: 0;
            color: #d8e1f0;
        }

        .chess-embed-stage {
            position: relative;
            min-height: min(720px, calc(100vh - 96px));
            overflow: hidden;
            width: 100%;
            isolation: isolate;
        }

        .chess-embed-stage iframe {
            width: 100%;
            max-width: none;
            height: min(720px, calc(100vh - 96px));
            min-height: 520px;
            border: 0;
            display: block;
            background: #081224;
            position: relative;
            z-index: 1;
        }

        .chess-embed-loading,
        .chess-embed-state {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 16px;
            text-align: center;
            background: linear-gradient(180deg, rgba(8, 18, 36, 0.94) 0%, rgba(10, 24, 48, 0.98) 100%);
            z-index: 2;
        }

        .chess-embed-loading.is-hidden {
            display: none;
        }

        .chess-embed-spinner {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            border: 4px solid rgba(255, 255, 255, 0.12);
            border-top-color: #ffcf0d;
            animation: chess-spin 0.9s linear infinite;
        }

        .chess-embed-state {
            position: relative;
            inset: auto;
            min-height: 0;
            padding: 14px 16px;
            border-left: 4px solid #ffcf0d;
            border-radius: 12px;
            align-items: flex-start;
            text-align: left;
            background: rgba(14, 27, 52, 0.92);
        }

        .chess-embed-state--error {
            border-left-color: #ff6a6a;
        }

        .embedUnavailableState {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: clamp(18px, 4vw, 42px);
            text-align: center;
            background: linear-gradient(180deg, rgba(8, 18, 36, 0.96) 0%, rgba(10, 24, 48, 0.99) 100%);
            z-index: 2;
        }

        .embedUnavailableState__card {
            width: min(100%, 460px);
            border: 1px solid rgba(255, 207, 13, 0.18);
            border-radius: 12px;
            background: rgba(14, 27, 52, 0.88);
            box-shadow: 0 18px 44px rgba(3, 8, 19, 0.28);
            padding: clamp(22px, 5vw, 34px);
        }

        .embedUnavailableState__message {
            font-size: clamp(15px, 2.6vw, 18px);
            line-height: 1.45;
            font-weight: 800;
        }

        .embedUnavailableState__title {
            margin: 0 0 8px;
            color: #ffcf0d;
            font-size: clamp(18px, 2.8vw, 22px);
            font-weight: 800;
        }

        .embedUnavailableState__detail {
            margin-top: 14px;
            text-align: left;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            padding-top: 12px;
        }

        .embedUnavailableState__detail summary {
            cursor: pointer;
            color: rgba(216, 225, 240, 0.84);
            font-size: 12px;
            font-weight: 700;
            list-style: none;
        }

        .embedUnavailableState__detail summary::-webkit-details-marker {
            display: none;
        }

        .embedUnavailableState__detail p {
            margin-top: 8px;
            color: rgba(216, 225, 240, 0.72);
            font-size: 12px;
            line-height: 1.5;
        }

        .chess-embed-footer {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            padding: 0 2px;
            font-size: 13px;
        }

        .chess-embed-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 6px;
        }

        .chess-embed-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 38px;
            padding: 0 16px;
            border: 1px solid rgba(255, 207, 13, 0.28);
            border-radius: 10px;
            background: #ffcf0d;
            color: #101827;
            font-weight: 800;
            text-decoration: none;
        }

        .chess-embed-action:hover,
        .chess-embed-action:focus {
            color: #101827;
            text-decoration: none;
        }

        .chess-embed-debug-note {
            width: 100%;
            margin-top: 4px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            padding-top: 10px;
        }

        .chess-embed-debug-note summary {
            cursor: pointer;
            color: rgba(216, 225, 240, 0.82);
            font-size: 12px;
            font-weight: 700;
            list-style: none;
        }

        .chess-embed-debug-note summary::-webkit-details-marker {
            display: none;
        }

        .chess-embed-debug-note p {
            margin-top: 8px;
            color: rgba(216, 225, 240, 0.72);
            font-size: 12px;
        }

        @keyframes chess-spin {
            to {
                transform: rotate(360deg);
            }
        }

        @media (max-width: 768px) {
            .chessEmbedPageOuter {
                padding-top: 6px;
                padding-bottom: 8px;
            }

            .chessEmbedPageContainer {
                padding-left: 6px;
                padding-right: 6px;
            }

            .chess-embed-shell {
                gap: 6px;
            }

            .chess-embed-stage,
            .chess-embed-stage iframe {
                min-height: 560px;
                height: calc(100svh - 74px);
            }

            .chess-embed-stage {
                border-radius: 10px;
            }

            .chess-embed-loading,
            .chess-embed-state {
                padding: 12px;
            }
        }

        @media (max-width: 430px) {
            .chess-embed-stage,
            .chess-embed-stage iframe {
                min-height: 540px;
                height: calc(100svh - 68px);
            }

            .chess-embed-state {
                padding: 12px 14px;
            }
        }

        @media (max-width: 390px) {
            .chess-embed-stage,
            .chess-embed-stage iframe {
                min-height: 520px;
                height: calc(100svh - 64px);
            }
        }

        @media (min-width: 1024px) and (max-width: 1439px) {
            body.globalThemeShell .chessEmbedPageOuter {
                width: 100%;
                margin-left: 0;
            }

            body.globalThemeShell .chessEmbedPageContainer {
                padding-left: 0 !important;
            }
        }

    </style>

    <div class="contentPageOuter chessEmbedPageOuter chessEmbedPageOuter--compact">
        <div class="container chessEmbedPageContainer">
            <div
                class="chess-embed-shell"
                data-effective-locale="{{ data_get($localeResolution ?? [], 'effective_locale', app()->getLocale()) }}"
                data-provider-locale="{{ data_get($localeResolution ?? [], 'provider_locale', app()->getLocale()) }}"
                data-locale-fallback-used="{{ data_get($localeResolution ?? [], 'locale_fallback_used', false) ? 'true' : 'false' }}"
            >
                @php($entryState = (array) ($entryState ?? []))
                @php($showEmbedDebug = app()->environment('local') && (bool) config('chess_wallet.default_provider_config.embed.debug_enabled', true))
                @php($walletSupportContext = (array) ($walletSupportContext ?? []))
                @php($supportedLabels = array_values(array_filter((array) data_get($walletSupportContext, 'supportedLabels', []), fn ($label) => trim((string) $label) !== '')))
                @php($hasWalletGuidance = !empty($walletSupportContext['reason_code']))
                @php($isTechnicalBootstrapFailure = !$hasWalletGuidance && filled($bootstrapError))
                @php($unavailableTitle = $hasWalletGuidance ? trans('wallet_playability.unsupported_wallet') : 'Satranc su an acilamiyor')
                @php($unavailableMessage = $hasWalletGuidance
                    ? (filled($bootstrapError) ? $bootstrapError : trans('wallet_playability.unsupported_wallet'))
                    : 'Baglanti kisa sureli kurulamadigi icin satranc ekrani hazirlanamadi.')

                <div class="chess-embed-stage">
                    @if($iframeSrc)
                        <div class="chess-embed-loading" id="chessEmbedLoading">
                            <div class="chess-embed-spinner"></div>
                            <strong style="color:#ffcf0d;">Chess surface yükleniyor</strong>
                            <p>Bridge token consume akışı tamamlanınca embedded surface hazır olacak.</p>
                        </div>

                        <iframe
                            id="chessEmbedFrame"
                            src="{{ $iframeSrc }}"
                            title="{{ $iframeTitle }}"
                            allow="clipboard-read; clipboard-write"
                            referrerpolicy="origin"
                        ></iframe>
                    @elseif($entryState !== [] && !$bootstrapError)
                        <div class="chess-embed-state">
                            <strong>{{ $entryState['title'] ?? 'Satranç alanı hazır' }}</strong>
                            <p>{{ $entryState['message'] ?? 'Devam etmek için giriş yapın.' }}</p>
                            <div class="chess-embed-actions">
                                <button type="button"
                                        class="chess-embed-action"
                                        data-chess-login-trigger
                                        data-login-redirect="{{ $entryState['loginRedirect'] ?? route('cms.front.providers.chess') }}">
                                    Giriş yap
                                </button>
                            </div>
                        </div>
                    @else
                        <div class="embedUnavailableState" role="status" aria-live="polite">
                            <div class="embedUnavailableState__card">
                                <h2 class="embedUnavailableState__title">{{ $unavailableTitle }}</h2>
                                <p class="embedUnavailableState__message">{{ $unavailableMessage }}</p>
                                @if($hasWalletGuidance)
                                    <div class="chess-embed-state chess-embed-state--error mt-3">
                                        <strong>{{ trans('wallet_playability.supported_wallets', ['wallets' => implode(', ', $supportedLabels)]) }}</strong>
                                        @if(!empty($walletSupportContext['switch_allowed']))
                                        <div class="chess-embed-actions">
                                            <a class="chess-embed-action"
                                               href="{{ route('cms.wallet.index') }}">
                                                {{ trans('wallet_playability.switch_wallet') }}
                                            </a>
                                        </div>
                                        @elseif(!empty($walletSupportContext['switch_reasons']))
                                            <p>{{ trans('wallet_playability.switch_blocked') }}</p>
                                        @endif
                                    </div>
                                @elseif($isTechnicalBootstrapFailure)
                                    <details class="embedUnavailableState__detail">
                                        <summary>Teknik detay</summary>
                                        <p>{{ $bootstrapError }}</p>
                                    </details>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>

                @if($showEmbedDebug && $iframeSrc)
                    <div class="chess-embed-footer">
                        <span>Partner: {{ $partnerCode }}</span>
                        <span>Bridge: {{ $bridgeExchangeStatus === 'ok' ? 'session token hazir' : $bridgeExchangeStatus }}</span>
                        @if($bridgeExpiresAt)
                            <span>Bridge expires: {{ $bridgeExpiresAt }}</span>
                        @endif
                        @if($embedRequestId)
                            <span>Request: {{ $embedRequestId }}</span>
                        @endif
                        @if($externalSessionRef)
                            <span>Session: {{ $externalSessionRef }}</span>
                        @endif
                        @if($debugSessionDetailUrl)
                            <span><a href="{{ $debugSessionDetailUrl }}" style="color:#ffcf0d;">Debug Detail</a></span>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@push("js")
    @php($bootstrapMetaJs = [
        'partnerCode' => $partnerCode,
        'bridgeToken' => $bridgeToken,
        'expiresAt' => data_get($bootstrapPayload, 'expiresAt'),
        'requestTimestamp' => data_get($bootstrapPayload, 'requestTimestamp'),
        'requestId' => $embedRequestId,
        'externalSessionRef' => $externalSessionRef,
        'walletContextRef' => $walletContextRef,
    ])
    <script>
        (function () {
            const iframe = document.getElementById("chessEmbedFrame");
            const loading = document.getElementById("chessEmbedLoading");
            const refreshButton = document.getElementById("refreshChessEmbed");
            const loginTrigger = document.querySelector("[data-chess-login-trigger]");
            const iframeSrc = @json($iframeSrc);
            const postMessageOrigin = @json($postMessageOrigin);
            const bootstrapMeta = @json($bootstrapMetaJs);
            const debugTelemetryUrl = @json($debugTelemetryUrl ?? null);
            const csrfToken = @json(csrf_token());
            const debugContext = {
                request_id: @json($embedRequestId),
                cms_user_id: @json(optional($selectedUser ?? auth('cms')->user())->id),
                cms_user_label: @json(optional($selectedUser ?? auth('cms')->user())->username ?? null),
                cms_language: @json(data_get($localeResolution ?? [], 'effective_locale', app()->getLocale())),
                effective_locale: @json(data_get($localeResolution ?? [], 'effective_locale', app()->getLocale())),
                provider_locale: @json(data_get($localeResolution ?? [], 'provider_locale', app()->getLocale())),
                locale_fallback_used: @json((bool) data_get($localeResolution ?? [], 'locale_fallback_used', false)),
                partner_code: @json($partnerCode),
                external_user_id: @json(data_get($bootstrapPayload, 'externalUserId')),
                external_session_ref: @json($externalSessionRef),
                wallet_context_ref: @json($walletContextRef),
                parent_origin: @json($parentOrigin),
                post_message_origin: @json($postMessageOrigin),
                iframe_src: @json($iframeSrc),
                consume_url: @json($iframeSrc),
            };
            const canonicalRefreshEvent = "prematch.embed.request_refresh";
            const parentRefreshRequestEvent = "prematch.embed.request_parent_refresh";
            const authRequiredEvent = "prematch.embed.auth_required";
            const canonicalEvents = new Set([
                "prematch.embed.ready",
                "prematch.embed.height_changed",
                authRequiredEvent,
                "prematch.embed.place_result",
                parentRefreshRequestEvent,
            ]);
            let readySeen = false;
            let consumeFailureTimer = null;
            let lastPlaceResult = null;

            const requestCmsLogin = function () {
                const redirectTarget = window.location.pathname + window.location.search + window.location.hash;
                const redirectInput = document.querySelector('[data-global-auth-panel="login"] input[name="redirectAfterLogin"]');

                if (redirectInput) {
                    redirectInput.value = redirectTarget;
                }

                if (typeof window.openCmsLoginModal === "function") {
                    window.openCmsLoginModal();
                    return;
                }

                const globalLogin = document.querySelector('[data-global-auth-open="login"]');
                if (globalLogin) {
                    globalLogin.click();
                } else {
                    window.location.assign(@json(route('cms.login')));
                }
            };

            if (loginTrigger) {
                loginTrigger.addEventListener("click", function () {
                    requestCmsLogin();
                });
            }

            if (!iframe || !iframeSrc) {
                return;
            }

            const recordEvent = function (eventName, detail) {
                if (!debugTelemetryUrl) {
                    return;
                }

                const payload = Object.assign({}, debugContext, detail || {}, {
                    event_name: eventName,
                });

                window.fetch(debugTelemetryUrl, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": csrfToken,
                    },
                    body: JSON.stringify(payload),
                    credentials: "same-origin",
                    keepalive: true,
                }).catch(function () {});
            };

            const hideLoading = function () {
                if (loading) {
                    loading.classList.add("is-hidden");
                }
            };

            const showLoading = function () {
                if (loading) {
                    loading.classList.remove("is-hidden");
                }
            };

            const requestChildRefresh = function () {
                if (!postMessageOrigin || !iframe.contentWindow) {
                    return;
                }

                iframe.contentWindow.postMessage({
                    type: canonicalRefreshEvent,
                    meta: bootstrapMeta,
                }, postMessageOrigin);

                recordEvent("embed.parent.message_sent", {
                    message_type: canonicalRefreshEvent,
                    detail: {
                        meta: bootstrapMeta,
                    },
                });
            };

            const isRejectedPlaceResult = function (payload) {
                return String(payload?.status || "").toLowerCase() === "rejected";
            };

            const armConsumeFailureTimer = function (detail) {
                window.clearTimeout(consumeFailureTimer);
                consumeFailureTimer = window.setTimeout(function () {
                    if (!readySeen) {
                        hideLoading();
                        recordEvent("embed.consume.failed", {
                            failure_reason: "no_ready_event_within_timeout",
                            detail: detail || null,
                        });
                    }
                }, 5000);
            };

            recordEvent("embed.consume.started");
            armConsumeFailureTimer();
            iframe.addEventListener("load", function () {
                hideLoading();
                recordEvent("embed.child.message_received", {
                    message_type: "iframe.load",
                });
            });

            refreshButton?.addEventListener("click", function () {
                showLoading();
                readySeen = false;
                recordEvent("embed.consume.started", {
                    detail: {
                        triggered_by: "parent_refresh_button",
                    },
                });
                armConsumeFailureTimer({
                    triggered_by: "parent_refresh_button",
                    blocking: true,
                });

                if (iframe.contentWindow) {
                    requestChildRefresh();
                    window.setTimeout(function () {
                        if (!loading || !loading.classList.contains("is-hidden")) {
                            iframe.src = iframeSrc;
                        }
                    }, 900);
                    return;
                }

                iframe.src = iframeSrc;
            });

            window.addEventListener("message", function (event) {
                if (postMessageOrigin && event.origin !== postMessageOrigin) {
                    return;
                }

                if (!iframe.contentWindow || event.source !== iframe.contentWindow) {
                    return;
                }

                const data = event.data || {};
                if (typeof data.type !== "string") {
                    return;
                }

                if (!canonicalEvents.has(data.type)) {
                    return;
                }

                 recordEvent("embed.child.message_received", {
                    message_type: data.type,
                    detail: data,
                    source: data.source || null,
                });

                if (data.type === "prematch.embed.ready") {
                    readySeen = true;
                    window.clearTimeout(consumeFailureTimer);
                    hideLoading();
                    recordEvent("embed.consume.succeeded", {
                        message_type: data.type,
                    });
                    recordEvent("embed.child.ready", {
                        message_type: data.type,
                    });
                    return;
                }

                if (data.type === "prematch.embed.height_changed" && typeof data.height === "number") {
                    iframe.style.height = Math.max(560, Math.min(1600, data.height)) + "px";
                    recordEvent("embed.child.height_changed", {
                        message_type: data.type,
                        height: data.height,
                    });
                    return;
                }

                if (data.type === authRequiredEvent) {
                    recordEvent("embed.child.auth_required", {
                        message_type: data.type,
                    });
                    requestCmsLogin();
                    return;
                }

                if (data.type === parentRefreshRequestEvent) {
                    recordEvent("embed.child.request_parent_refresh", {
                        message_type: data.type,
                    });

                    if (isRejectedPlaceResult(lastPlaceResult)) {
                        window.clearTimeout(consumeFailureTimer);
                        hideLoading();
                        recordEvent("embed.parent.refresh_skipped", {
                            message_type: data.type,
                            detail: {
                                reason: "rejected_place_result",
                                place_status: lastPlaceResult.status || null,
                                rejection_reason: lastPlaceResult.rejectionReason || null,
                                wallet_reason: lastPlaceResult.walletReason || null,
                            },
                        });
                        return;
                    }

                    readySeen = false;
                    recordEvent("embed.consume.started", {
                        detail: {
                            triggered_by: "child_request_parent_refresh",
                            blocking: false,
                            place_status: lastPlaceResult?.status || null,
                            rejection_reason: lastPlaceResult?.rejectionReason || null,
                            wallet_reason: lastPlaceResult?.walletReason || null,
                        },
                    });
                    armConsumeFailureTimer({
                        triggered_by: "child_request_parent_refresh",
                        blocking: false,
                        place_status: lastPlaceResult?.status || null,
                    });
                    requestChildRefresh();
                    return;
                }

                if (data.type === "prematch.embed.place_result") {
                    lastPlaceResult = data;
                    window.__prematchChessLastPlaceResult = data;
                    if (isRejectedPlaceResult(data)) {
                        window.clearTimeout(consumeFailureTimer);
                        hideLoading();
                    }
                    recordEvent("embed.child.place_result", {
                        message_type: data.type,
                        payload: data,
                    });
                }
            });
        })();
    </script>
@endpush
