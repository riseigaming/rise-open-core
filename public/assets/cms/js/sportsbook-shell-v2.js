function readSportsbookShellJson(id, fallbackValue) {
    const node = document.getElementById(id);
    if (!node) {
        return fallbackValue;
    }

    try {
        return JSON.parse(node.textContent || "");
    } catch (error) {
        return fallbackValue;
    }
}

function sportsbookCouponUiState(hasSelection, actionState) {
    if (!hasSelection) return 'empty';
    if (['submitting', 'retrying'].includes(actionState)) return 'updating';
    return actionState === 'error' ? 'error' : 'has_selection';
}

function sportsbookDemoCoupon(previous, incoming) {
    if (!incoming || incoming.acceptance_mode !== 'offline_demo' || incoming.demo_selectable !== true) return previous;
    const items = previous && Array.isArray(previous.demo_selections) ? previous.demo_selections : [];
    const exists = items.some(item => item.selection_key === incoming.selection_key);
    const next = items.filter(item => String(item.event_id) !== String(incoming.event_id));
    if (!exists && next.length < 20) {
        next.push({event_id: incoming.event_id, selection_key: incoming.selection_key,
            event_name: incoming.event_name, outcome_name: incoming.outcome_name || incoming.label,
            market_name: incoming.market_name, raw_odd: Number(incoming.raw_odd)});
    }
    const odd = next.reduce((value, item) => value * item.raw_odd, 1);
    if (!next.length) return null;
    if (!Number.isFinite(odd) || next.some(item => !Number.isFinite(item.raw_odd) || item.raw_odd <= 1)) return previous;
    return {acceptance_mode: 'offline_demo', demo_selections: next, demo_selectable: true,
        selectable: false, acceptance_ready: false, selection_key: 'offline-demo-coupon',
        event_name: next.map(item => item.event_name).join(' + '),
        outcome_name: next.map(item => item.outcome_name).join(' + '),
        market_name: next.map(item => item.market_name).join(' + '), raw_odd: odd};
}

function sportsbookFeedItemAddable(item, now = Date.now()) {
    const reference = item && item.selection_reference;
    return Boolean(item && ['playable', 'partially_playable'].includes(item.status)
        && item.add_to_coupon_available === true && item.playable_selection_count > 0
        && Date.parse(item.expires_at) > now
        && reference && reference.event_id && reference.market_type && reference.outcome_code);
}

function sportsbookFeedHydratedContractUsable(selection) {
    if (!selection || selection.selectable !== true) return false;
    if (String(selection.acceptance_mode || '').startsWith('adapter_vps')) {
        return selection.acceptance_mode === 'adapter_vps_selection_token'
            && String(selection.selection_token || selection.client_selection_ref || '').trim() !== '';
    }
    return Number(selection.quote_selection_id) > 0 && Number(selection.quote_version_id) > 0;
}

function sportsbookFeedUiState(items, freshness) {
    if (freshness && freshness.status === 'degraded') return 'degraded_stale_source';
    return items.length ? 'ready_items' : 'ready_empty';
}

function sportsbookCountUiState(state, countKnown) {
    if (state === 'loading') return 'loading';
    if (['disabled', 'not_configured'].includes(state)) return 'passive';
    if (['stale', 'degraded', 'degraded_stale_source', 'error'].includes(state)) return 'unavailable';
    return countKnown ? 'count' : 'not_requested';
}

function sportsbookScopedCountPresentation(renderedCount, scopedTotal) {
    const rendered = Math.max(0, Number(renderedCount) || 0);
    if (scopedTotal === null || !Number.isInteger(scopedTotal) || scopedTotal < 0) {
        return { state: 'shown', count: rendered };
    }

    if (rendered > scopedTotal) {
        return { state: 'inconsistent', count: scopedTotal };
    }

    return { state: 'total', count: scopedTotal };
}

function sportsbookRowNoticeState(phase, tradingStatus, tradeable, hydrating) {
    if (phase === 'ended') return 'ended';
    if (['closed', 'settled', 'cancelled'].includes(tradingStatus)) return 'closed';
    if (['suspended', 'paused'].includes(tradingStatus)) return 'suspended';
    if (tradeable) return 'none';
    return hydrating ? 'resolving' : 'unavailable';
}

    document.querySelectorAll('[data-accordion-trigger]').forEach(function (button) {
        button.dataset.boundAccordion = '1';
        button.addEventListener('click', function () {
            const article = button.closest('.sb2-league');
            const isOpen = article.classList.toggle('is-open');
            button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    });

    (function () {
        const pageBootedAt = Date.now();
        const layout = document.querySelector('.sb2-layout');
        const sportsbookThemeRoot = document.getElementById('sportsbookSurface')
            || document.querySelector('.sportsbook-native-shell')
            || layout;
        const tabTriggers = Array.from(document.querySelectorAll('[data-tab-trigger]'));
        const tabPanels = Array.from(document.querySelectorAll('[data-tab-panel]'));
        const initialPayloadDeferred = layout ? layout.dataset.initialPayloadDeferred === '1' : false;
        const staticTabCounts = new Map(tabTriggers.map(function (button) {
            const key = button.dataset.tab || button.dataset.tabTrigger || 'pre-match';
            const countNode = button.querySelector('[data-tab-count]');

            return [key, Number(countNode ? (countNode.dataset.countRaw || 0) : 0)];
        }));
        const deferredTabCounts = new Set(tabTriggers
            .filter(function (button) {
                return button.dataset.countDeferred === '1';
            })
            .map(function (button) {
                return button.dataset.tab || button.dataset.tabTrigger || 'pre-match';
            }));
        const tabSwitchers = Array.from(document.querySelectorAll('[data-tab-switch]'));
        let triggers = Array.from(document.querySelectorAll('[data-selection-trigger]'));
        let matchRows = Array.from(document.querySelectorAll('[data-event-row]'));
        let leagueNodes = Array.from(document.querySelectorAll('.sb2-league'));
        let sportFilterButtons = Array.from(document.querySelectorAll('[data-sport-filter]'));
        const sportStrip = document.querySelector('[data-sport-strip]');
        const sportStripShell = sportStrip ? sportStrip.closest('.sb2-sports-strip-shell') : null;
        const surfaceSportSwitchers = Array.from(document.querySelectorAll('[data-surface-sport-switch]'));
        const sportScrollPrev = document.querySelector('[data-sport-scroll-prev]');
        const sportScrollNext = document.querySelector('[data-sport-scroll-next]');
        const searchToggleButton = document.querySelector('[data-search-toggle]');
        const themeToggleButton = document.querySelector('[data-theme-toggle]');
        const toolbarPanel = document.querySelector('[data-toolbar-panel]');
        const searchPanel = document.querySelector('[data-search-panel]');
        const leagueFilterPanel = document.querySelector('[data-league-filter-panel]');
        const leagueFilterList = document.querySelector('[data-league-filter-list]');
        const competitionRailToggleButton = document.querySelector('[data-competition-rail-toggle]');
        const competitionRailCloseButtons = Array.from(document.querySelectorAll('[data-competition-rail-close]'));
        const competitionRailBody = document.querySelector('[data-competition-rail-body]');
        const competitionRailCount = document.querySelector('[data-competition-rail-count]');
        const competitionRailActiveCount = document.querySelector('[data-competition-rail-active-count]');
        const hydrationBadge = document.querySelector('[data-hydration-badge]');
        const hydrationText = document.querySelector('[data-hydration-text]');
        const hydrationProgress = document.querySelector('[data-hydration-progress]');
        const hydrationProgressBar = document.querySelector('[data-hydration-progress-bar]');
        const boardStatus = document.querySelector('[data-board-status]');
        const boardStatusTitle = document.querySelector('[data-board-status-title]');
        const boardStatusText = document.querySelector('[data-board-status-text]');
        const boardStatusDebug = document.querySelector('[data-board-status-debug]');
        const boardStatusMeter = document.querySelector('[data-board-status-meter]');
        const boardSkeleton = document.querySelector('[data-board-skeleton]');
        const initialOffersEmptyState = document.querySelector('[data-initial-offers-empty-state]');
        const railSearchInput = document.querySelector('[data-rail-search-input]');
        const railSearchClear = document.querySelector('[data-rail-search-clear]');
        const eventSearchInput = document.querySelector('[data-event-search-input]');
        const eventSearchClear = document.querySelector('[data-event-search-clear]');
        const eventSearchCount = document.querySelector('[data-event-search-count]');
        const searchPanelCloseButtons = Array.from(document.querySelectorAll('[data-search-panel-close]'));
        const leagueFilterToggleButton = document.querySelector('[data-league-filter-toggle]');
        const featuredMatchesToggleButton = document.querySelector('[data-featured-matches-toggle]');
        const nextMatchesToggleButton = document.querySelector('[data-next-matches-toggle]');
        const allMatchesToggleButton = document.querySelector('[data-all-matches-toggle]');
        const activeFilterChip = document.querySelector('[data-active-filter-chip]');
        const activeFilterLabel = document.querySelector('[data-active-filter-label]');
        const activeFilterClearButton = document.querySelector('[data-active-filter-clear]');
        let leagueFilterGroups = Array.from(document.querySelectorAll('[data-league-filter-group]'));
        let leagueFilterButtons = Array.from(document.querySelectorAll('[data-league-filter]'));
        const filterEmptyState = document.querySelector('[data-filter-empty-state]');
        let moreToggleButtons = Array.from(document.querySelectorAll('[data-more-toggle]'));
        const matchPagination = document.querySelector('[data-match-pagination]');
        const matchPagePrev = document.querySelector('[data-match-page-prev]');
        const matchPageNext = document.querySelector('[data-match-page-next]');
        const matchPageLabel = document.querySelector('[data-match-page-label]');
        const matchPageSummary = document.querySelector('[data-match-page-summary]');
        const matchPageNumbers = document.querySelector('[data-match-page-numbers]');
        const slipModeButtons = Array.from(document.querySelectorAll('[data-slip-mode-tab]'));
        const filledState = document.querySelector('[data-slip-filled]');
        const slipIdleCard = document.querySelector('[data-slip-idle-card]');
        const slipModeSurface = document.querySelector('[data-slip-mode-surface]');
        const slipModeSurfaceTitle = document.querySelector('[data-slip-mode-surface-title]');
        const slipModeSurfaceText = document.querySelector('[data-slip-mode-surface-text]');
        const slipModeResetButton = document.querySelector('[data-slip-mode-reset]');
        const selectionCard = document.querySelector('[data-slip-selection-card]');
        const stakeCard = document.querySelector('[data-slip-stake-card]');
        const removeButtons = Array.from(document.querySelectorAll('[data-remove-selection]'));
        const fillBalanceButton = document.querySelector('[data-fill-balance]');
        const stakeChipButtons = Array.from(document.querySelectorAll('[data-stake-chip]'));
        const submitButton = document.querySelector('[data-submit-slip]');
        const communityShareButton = document.querySelector('[data-community-share-selection]');
        const communityShareFeedback = document.querySelector('[data-community-share-feedback]');
        const submitHelper = document.querySelector('[data-submit-helper]');
        const stakeInput = document.querySelector('[data-stake-input]');
        const slipEvent = document.querySelector('[data-slip-event]');
        const slipContext = document.querySelector('[data-slip-context]');
        const exactWarning = document.querySelector('[data-exact-refresh-warning]');
        const exactRetry = document.querySelector('[data-exact-refresh-retry]');
        const slipMarket = document.querySelector('[data-slip-market]');
        const slipOutcome = document.querySelector('[data-slip-outcome]');
        const slipOdd = document.querySelector('[data-slip-odd]');
        const slipStake = document.querySelector('[data-slip-stake]');
        const slipMultiplier = document.querySelector('[data-slip-multiplier]');
        const slipReturn = document.querySelector('[data-slip-return]');
        const resultCard = document.querySelector('[data-result-card]');
        const resultTitle = document.querySelector('[data-result-title]');
        const resultText = document.querySelector('[data-result-text]');
        const resultAction = document.querySelector('[data-result-action]');
        const resultClose = document.querySelector('[data-result-close]');
        const acceptanceRef = document.querySelector('[data-acceptance-ref]');
        const acceptanceId = document.querySelector('[data-acceptance-id]');
        const acceptedPrice = document.querySelector('[data-accepted-price]');
        const lastActionCard = document.querySelector('[data-last-action-card]');
        const lastActionStatus = document.querySelector('[data-last-action-status]');
        const lastStake = document.querySelector('[data-last-stake]');
        const lastAcceptanceRef = document.querySelector('[data-last-acceptance-ref]');
        const lastAcceptedPrice = document.querySelector('[data-last-accepted-price]');
        const lastReturn = document.querySelector('[data-last-return]');
        const lastActionSummary = document.querySelector('[data-last-action-summary]');
        const bahisbotPanel = document.querySelector('[data-bahisbot-panel]');
        const bahisbotIdle = document.querySelector('[data-bahisbot-idle]');
        const bahisbotSelection = document.querySelector('[data-bahisbot-selection]');
        const bahisbotMatch = document.querySelector('[data-bahisbot-match]');
        const bahisbotPick = document.querySelector('[data-bahisbot-pick]');
        const bahisbotMovement = document.querySelector('[data-bahisbot-movement]');
        const bahisbotMovementLabel = document.querySelector('[data-bahisbot-movement-label]');
        const bahisbotMovementText = document.querySelector('[data-bahisbot-movement-text]');
        const bahisbotMovementCaveat = document.querySelector('[data-bahisbot-movement-caveat]');
        const bahisbotStatus = document.querySelector('[data-bahisbot-status]');
        const bahisbotProgress = document.querySelector('[data-bahisbot-progress]');
        const bahisbotCard = document.querySelector('[data-bahisbot-card]');
        const bahisbotCardTitle = document.querySelector('[data-bahisbot-card-title]');
        const bahisbotCardSummary = document.querySelector('[data-bahisbot-card-summary]');
        const bahisbotCardSource = document.querySelector('[data-bahisbot-card-source]');
        const bahisbotIdentity = document.querySelector('[data-bahisbot-identity]');
        const bahisbotStart = document.querySelector('[data-bahisbot-start]');
        const bahisbotNewsAge = document.querySelector('[data-bahisbot-news-age]');
        const bahisbotCheckAge = document.querySelector('[data-bahisbot-check-age]');
        const bahisbotCacheWindow = document.querySelector('[data-bahisbot-cache-window]');
        const bahisbotToggle = document.querySelector('[data-bahisbot-toggle]');
        const bahisbotRetry = document.querySelector('[data-bahisbot-retry]');
        const bahisbotRetryRow = document.querySelector('[data-bahisbot-retry-row]');
        const bahisbotRetryLabel = document.querySelector('[data-bahisbot-retry-label]');
        const autoAcceptOddsChangeToggle = document.querySelector('[data-auto-accept-odds-change]');
        const autoAcceptOddsChangeHelper = document.querySelector('[data-auto-accept-odds-helper]');
        const slipShell = document.querySelector('[data-slip-shell]');
        const slipToggleButtons = Array.from(document.querySelectorAll('[data-slip-toggle]'));
        const collapsedSummary = document.querySelector('[data-slip-collapsed-summary]');
        const collapsedSummaryCount = document.querySelector('[data-slip-collapsed-count]');
        const collapsedSummaryTitle = document.querySelector('[data-slip-collapsed-title]');
        const collapsedSummaryMeta = document.querySelector('[data-slip-collapsed-meta]');
        const collapsedSummaryStake = document.querySelector('[data-slip-collapsed-stake]');
        const collapsedSummaryOdd = document.querySelector('[data-slip-collapsed-odd]');
        const historyTabs = Array.from(document.querySelectorAll('[data-history-filter]'));
        const historyCard = document.querySelector('[data-history-card]');
        const historyItems = document.querySelector('[data-history-items]');
        const historyEmpty = document.querySelector('[data-history-empty]');
        const historyPagination = document.querySelector('[data-history-pagination]');
        const historyPagePrev = document.querySelector('[data-history-page-prev]');
        const historyPageNext = document.querySelector('[data-history-page-next]');
        const historyPageLabel = document.querySelector('[data-history-page-label]');
        const myBetsPanel = document.querySelector('[data-my-bets-panel]');
        const myBetsOpenButton = document.querySelector('[data-my-bets-open]');
        const myBetsCloseButtons = Array.from(document.querySelectorAll('[data-my-bets-close]'));
        const acceptanceApiUrl = layout ? layout.dataset.acceptanceApiUrl : '';
        const adapterAcceptanceApiUrl = layout ? layout.dataset.adapterAcceptanceApiUrl : '';
        const bahisbotResearchUrl = layout ? layout.dataset.bahisbotResearchUrl : '';
        const communityShareUrl = layout ? layout.dataset.communityShareUrl : '';
        const offerEventApiTemplate = layout ? layout.dataset.offerEventApiTemplate : '';
        const offersIndexApiUrl = layout ? layout.dataset.offersIndexApiUrl : '';
        const catalogTreeApiUrl = layout ? layout.dataset.catalogTreeApiUrl : '';
        const livePollEnabled = layout ? layout.dataset.livePollEnabled === '1' : false;
        const livePollVisibleMs = Math.max(Number(layout ? layout.dataset.livePollVisibleSeconds || 12 : 12), 5) * 1000;
        const livePollBackgroundMs = Math.max(Number(layout ? layout.dataset.livePollBackgroundSeconds || 45 : 45), 15) * 1000;
        const displayTimezone = layout ? (layout.dataset.displayTimezone || 'UTC') : 'UTC';
        const displayTimezoneCookieName = layout ? (layout.dataset.displayTimezoneCookieName || 'cms_display_timezone') : 'cms_display_timezone';
        const livePollPrematchLimit = Math.max(Number(layout ? layout.dataset.livePollPrematchLimit || 150 : 150), 1);
        const livePollInGameLimit = Math.max(Number(layout ? layout.dataset.livePollIngameLimit || 150 : 150), 1);
        const livePollBootstrapLimit = Math.max(Number(layout ? layout.dataset.livePollBootstrapLimit || 150 : 150), 1);
        const livePollSearchLimit = Math.max(Number(layout ? layout.dataset.livePollSearchLimit || 150 : 150), 1);
        const livePollNextMatchesLimit = Math.max(Number(layout ? layout.dataset.livePollNextMatchesLimit || 24 : 24), 1);
        const livePollFeaturedMatchesLimit = Math.max(Number(layout ? layout.dataset.livePollFeaturedMatchesLimit || 24 : 24), 1);
        const upcomingSportWindowSeconds = Math.max(Number(layout ? layout.dataset.upcomingWindowHours || 24 : 24), 1) * 3600;
        const upcomingSportDisplayLimit = 50;
        const nextMatchesDisplayLimit = livePollNextMatchesLimit;
        const featuredMatchesDisplayLimit = livePollFeaturedMatchesLimit;
        const walletSnapshotUrl = layout ? layout.dataset.walletSnapshotUrl : '';
        const emptyStateTitle = filterEmptyState ? filterEmptyState.querySelector('h2') : null;
        const emptyStateText = filterEmptyState ? filterEmptyState.querySelector('p') : null;
        const viewer = {
            id: layout ? (layout.dataset.viewerId || '') : '',
            authenticated: layout ? layout.dataset.viewerAuthenticated === '1' : false,
            balance: layout ? Number(layout.dataset.viewerBalance || 0) : 0,
            currency: layout ? (layout.dataset.viewerCurrency || 'TRY') : 'TRY',
            maxStake: layout && layout.dataset.viewerMaxStake !== '' ? Number(layout.dataset.viewerMaxStake || 0) : null,
            maxStakeFormatted: layout ? (layout.dataset.viewerMaxStakeFormatted || '') : '',
            depositUrl: layout ? (layout.dataset.viewerDepositUrl || '') : '',
            financeReportUrl: layout ? (layout.dataset.viewerFinanceReportUrl || '') : '',
        };
        const balanceText = document.querySelector('.sb2-balance');
        const csrfTokenMeta = document.querySelector('meta[name="csrf-token"], meta[name="csrfToken"]');
        const authPromptModal = document.getElementById('sportsbookAuthPromptModal');
        const depositPromptModal = document.getElementById('sportsbookDepositPromptModal');
        const loginModalElement = document.getElementById('loginModal');
        const zeroBalanceModalElement = document.getElementById('zeroBalanceDepositModal');
        const catalogModal = document.querySelector('[data-catalog-modal]');
        const catalogModalOpen = document.querySelector('[data-catalog-modal-open]');
        const catalogModalStatus = document.querySelector('[data-catalog-modal-status]');
        const catalogModalBack = document.querySelector('[data-catalog-modal-back]');
        const catalogModalTitle = document.querySelector('[data-catalog-modal-title]');
        const catalogModalTitleIcon = document.querySelector('[data-catalog-modal-title-icon]');
        const catalogCrumbs = document.querySelector('[data-catalog-crumbs]');
        const catalogList = document.querySelector('[data-catalog-list]');
        const catalogEmpty = document.querySelector('[data-catalog-empty]');
        const catalogCloseButtons = Array.from(document.querySelectorAll('[data-catalog-modal-close]'));
        const browserTimezone = (() => {
            try {
                const resolved = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
                return typeof resolved === 'string' ? resolved.trim() : '';
            } catch (error) {
                return '';
            }
        })();

        if (browserTimezone) {
            try {
                document.cookie = `${displayTimezoneCookieName}=${encodeURIComponent(browserTimezone)}; path=/; max-age=31536000; samesite=lax`;
            } catch (error) {
                // Ignore cookie write failures in restricted browsers.
            }
        }

        function formatDisplayDateTime(isoValue) {
            if (!isoValue) {
                return null;
            }

            const parsed = new Date(isoValue);
            if (Number.isNaN(parsed.getTime())) {
                return null;
            }

            try {
                return {
                    time: new Intl.DateTimeFormat(i18nLocale, {
                        timeZone: displayTimezone,
                        hour: '2-digit',
                        minute: '2-digit',
                    }).format(parsed),
                    date: new Intl.DateTimeFormat(i18nLocale, {
                        timeZone: displayTimezone,
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric',
                    }).format(parsed),
                };
            } catch (error) {
                return {
                    time: parsed.toLocaleTimeString(i18nLocale, { hour: '2-digit', minute: '2-digit' }),
                    date: parsed.toLocaleDateString(i18nLocale),
                };
            }
        }

        function formatDisplayTime(isoValue) {
            const result = formatDisplayDateTime(isoValue);
            if (!result) {
                return null;
            }

            return result.time + ' • ' + result.date;
        }
        const openPrimaryLoginButton = document.querySelector('[data-open-primary-login]');
        const redirectAfterLoginInput = document.getElementById('redirectAfterLogin');
        const queryParams = new URLSearchParams(window.location.search);
        const summaryDebugEnabled = queryParams.get('debug_summary') === '1';
        const initialOfferSummary = readSportsbookShellJson('sportsbookShellInitialOfferSummary', null);
        const shellI18n = readSportsbookShellJson('sportsbookShellI18n', {});
        const betFeedPrewarmConfig = readSportsbookShellJson('sportsbookShellBetFeedPolling', null);
        const predictionPrewarmConfig = readSportsbookShellJson('sportsbookShellPredictionPolling', null);
        const socialMarketPrewarmConfig = readSportsbookShellJson('sportsbookShellSocialMarketPolling', null);
        const i18nLocale = typeof shellI18n.locale === 'string' && shellI18n.locale ? shellI18n.locale : 'tr-TR';

        function sanitizeCmsLoginRedirect(value) {
            if (typeof window.sanitizeCmsFrontRedirect === 'function') {
                return window.sanitizeCmsFrontRedirect(value);
            }

            const fallbackPath = '/cms/sportsbook';
            const dataPaths = [
                '/cms/sportsbook/bet-feed-items',
                '/cms/sportsbook/live-offers',
                '/cms/sportsbook/prediction-items',
                '/cms/sportsbook/social-market-items',
                '/cms/sportsbook/catalog-tree',
            ];
            const target = String(value || '').trim();

            if (!target) {
                return '';
            }

            if (target.startsWith('//')) {
                return '/';
            }

            try {
                const parsedUrl = new URL(target, window.location.origin);

                if (parsedUrl.origin !== window.location.origin) {
                    return '/';
                }

                const normalizedPath = parsedUrl.pathname.replace(/\/+$/, '') || '/';
                if (dataPaths.some(function (path) {
                    return normalizedPath === path || normalizedPath.indexOf(path + '/') === 0;
                })) {
                    return fallbackPath;
                }

                return parsedUrl.pathname + parsedUrl.search + parsedUrl.hash;
            } catch (error) {
                return '/';
            }
        }

        function t(key, fallback, replacements) {
            const raw = key.startsWith('history.')
                ? ((shellI18n.history || {})[key.slice('history.'.length)] ?? fallback)
                : key.startsWith('markets.')
                    ? ((shellI18n.markets || {})[key.slice('markets.'.length)] ?? fallback)
                : (Object.prototype.hasOwnProperty.call(shellI18n, key) ? shellI18n[key] : fallback);
            let value = typeof raw === 'string' ? raw : String(fallback || key);

            Object.entries(replacements || {}).forEach(function (entry) {
                value = value.split(':' + entry[0]).join(String(entry[1]));
            });

            return value;
        }

        const initialPrefill = {
            eventId: queryParams.get('prefill_event_id'),
            marketType: queryParams.get('prefill_market_type'),
            marketSpecifierKey: queryParams.get('prefill_market_specifier_key'),
            outcomeCode: queryParams.get('prefill_outcome_code'),
            selectionId: queryParams.get('prefill_selection_id'),
        };
        const productSportOrder = ['soccer', 'basketball', 'tennis', 'baseball', 'icehockey'];
        const productSportRankByKey = productSportOrder.reduce(function (carry, sportKey, index) {
            carry[sportKey] = index;

            return carry;
        }, {});
        let allowedSportFilters = new Set(
            sportFilterButtons.map(function (button) {
                return button.dataset.sportFilter || 'all';
            }).filter(isPrimarySportFilterKey)
        );
        let allowedLeagueFilters = new Set(
            leagueFilterButtons.map(function (button) {
                return button.dataset.leagueFilter || 'all';
            })
        );
        const datasetDefaultSportFilter = layout ? (layout.dataset.defaultSportFilter || 'soccer') : 'soccer';
        const firstPrimarySportFilter = sportFilterButtons.find(function (button) {
            return isPrimarySportFilterKey(button.dataset.sportFilter || '');
        })?.dataset.sportFilter || 'soccer';
        const fallbackSportFilter = allowedSportFilters.has(datasetDefaultSportFilter)
            ? datasetDefaultSportFilter
            : firstPrimarySportFilter;
        const initialSportFilter = queryParams.get('sport') || fallbackSportFilter;
        const initialLeagueFilter = queryParams.get('league') || 'all';
        const initialSearchQuery = String(queryParams.get('search') || '').trim().toLocaleLowerCase(i18nLocale);

        let selected = null;
        let submitState = 'idle';
        let submitTitle = '';
        let submitMessage = '';
        let acceptedPayload = null;
        let lastAcceptedPayload = null;
        let currentIdempotencyKey = null;
        let selectedQuoteRefreshKey = '';
        let selectedQuoteRefreshPromise = null;
        let acceptancePreparationInFlight = false;
        let exactWarningKey = '';
        let lastAdapterQuoteRefreshFailure = '';
        let bahisbotSelectionKey = '';
        let bahisbotPreviewRequestSequence = 0;
        let bahisbotResearchRequestSequence = 0;
        let bahisbotResearchAbortController = null;
        let bahisbotLatestPayload = null;
        let bahisbotContext = null;
        let bahisbotResearchResult = null;
        let bahisbotSessionExpired = false;
        let bahisbotResearchPending = false;
        let bahisbotPreviewPending = false;
        let bahisbotRetryAt = 0;
        let bahisbotRetryTimer = null;
        let bahisbotClosed = false;
        const bahisbotCachePollDelayMs = 2000;
        const bahisbotCachePollMaxAttempts = 10;
        let frozenAfterSuccess = false;
        let inlineGateMessage = '';
        let dismissedSuccessResult = false;
        let resultActionIntent = 'retry';
        let autoAcceptOddsChanges = false;
        let lastSubmitUsedAutoAcceptedOdds = false;
        let lastSubmittedStakeAmount = 0;
        let successResetTimer = null;
        let submitProgressTimer = null;
        let submitProgressStartedAt = 0;
        const successConfirmationHoldMs = 5200;
        let activeHistoryFilter = document.querySelector('[data-history-filter].is-active')?.dataset.historyFilter || 'all';
        let activeHistoryPage = 1;
        let activeSlipMode = 'single';
        let activeMatchPage = 1;
        let activeSportFilter = allowedSportFilters.has(initialSportFilter) ? initialSportFilter : fallbackSportFilter;
        let activeLeagueFilter = allowedLeagueFilters.has(initialLeagueFilter) ? initialLeagueFilter : 'all';
        let activeTimePriorityMode = queryParams.get('time_priority') === 'next';
        let activeInterestPriorityMode = !!featuredMatchesToggleButton && queryParams.get('interest_priority') === 'featured';
        let featuredEventOrder = new Map();
        const serverDefaultTab = layout ? (layout.dataset.defaultTab || 'pre-match') : 'pre-match';
        const historyPageSize = 4;
        let activeSearchQuery = initialSearchQuery;
        let activeToolbarPanel = null;
        let slipCollapsed = false;
        let slipViewportMode = 'static';
        const sportsbookThemeStorageKey = 'sportsbookTheme';
        const autoAcceptOddsStorageKey = 'sportsbookAutoAcceptOddsChange';
        const availableSportsbookThemes = new Set(['default', 'dark_blend']);
        const initialSportsbookTheme = resolveSportsbookThemePreference();
        const slipCollapsedByMode = {
            static: false,
            floating: false,
            mobile: true,
        };
        let activePrimaryTab = serverDefaultTab;
        if (activeInterestPriorityMode) {
            activePrimaryTab = 'pre-match';
            activeSportFilter = 'all';
            activeLeagueFilter = 'all';
            activeTimePriorityMode = false;
        } else if (activeTimePriorityMode) {
            activePrimaryTab = 'pre-match';
            activeSportFilter = 'all';
            activeLeagueFilter = 'all';
        }
        let livePollTimer = null;
        let livePollInFlight = false;
        let livePollAbortController = null;
        let livePollRequestToken = 0;
        let livePollCursorQueue = [null];
        let livePollCursorIndex = 0;
        const liveOfferPayloadCache = new Map();
        const liveOfferPaginationState = new Map();
        const liveOfferPrefetchInFlight = new Set();
        const liveOfferPrefetchPromises = new Map();
        const expandedEventDetailCache = new Map();
        const expandedEventDetailInFlight = new Map();
        const liveOfferCacheTtlMs = 90000;
        let liveOfferSnapshotVersion = '';
        const sportsbookRuntimeConfig = readSportsbookShellJson('sportsbookShellRuntimeConfig', {});
        let offlineDemo = sportsbookRuntimeConfig.offline_demo || {active: false};
        function applyOfflineDemoState(state) {
            if (!state) return;
            const changed = Boolean(state.active) !== Boolean(offlineDemo.active);
            offlineDemo = state;
            const notice = document.querySelector('[data-offline-demo-notice]');
            if (notice) {
                notice.hidden = !state.active;
                notice.querySelector('[data-offline-demo-copy]').textContent = t(state.available ? 'offline_demo_no_submit' : 'offline_demo_missing', '');
                notice.querySelector('[data-offline-demo-time]').textContent = state.generated_at || '';
            }
            if (changed) {
                liveOfferPayloadCache.clear();
                clearSelection();
            }
            updateSubmitButton();
        }
        const adapterPreSubmitQuoteRefreshEnabled = sportsbookRuntimeConfig.adapter_pre_submit_quote_refresh_enabled !== false;
        const adapterQuoteRefreshBeforeSubmitMs = 30000;
        const adapterQuoteRefreshRequestTimeoutMs = 8000;
        let priorityPrefetchStarted = false;
        let liveHydrationContextKey = '';
        let competitionRailCollapsed = window.innerWidth <= 1080;
        let hydrationBootstrapped = false;
        let hydrationInFlight = false;
        let hydrationRunSequence = 0;
        let hydrationReady = false;
        let initialPassivePaintRequested = false;
        let initialHydrationRecoveryNotified = false;
        let firstUsableHydrationPromise = null;
        let resolveFirstUsableHydration = null;
        let catalogPayload = null;
        let catalogPayloadKey = '';
        let providerDegradedState = null;
        let catalogLoading = false;
        let catalogLoadPromise = null;
        let catalogPath = [];
        let activeCatalogCompetition = null;
        let catalogSelectionLoadingTimer = null;
        let catalogSelectionLoadingContextKey = '';
        let boardRefreshHideTimer = null;
        let boardRefreshMinimumVisibleUntil = 0;
        let hydrationLoadingWatchdogTimer = null;
        let rowRecords = [];
        let leagueRecords = [];
        let leagueRecordByNode = new Map();
        let leagueFilterGroupRecords = [];
        let eventRowById = new Map();
        let rowRecordByRow = new Map();
        let sportFilterButtonByKey = new Map();
        let leagueFilterButtonByKey = new Map();
        let timePriorityLeagueNode = null;
        let phaseSearchIndexCache = new Map();
        let filteredViewCache = new Map();
        let boardRefreshToken = 0;
        let interactiveLiveRefreshTimer = null;
        const catalogSelectionLoadingTimeoutMs = 8000;
        let leagueWindowRefreshFrame = null;
        let offerSummaryState = initialOfferSummary && typeof initialOfferSummary === 'object'
            ? initialOfferSummary
            : null;
        let liveOfferCountsState = null;
        let sportRailSyncFrame = null;
        const rememberedSportCountsByTab = {
            'pre-match': {},
            'in-game': {},
        };
        renderSummaryDebugState(offerSummaryState);
        let rowVisibilityBatchFrame = null;
        let rowVisibilityBatchToken = 0;
        let rowVisibilityBatchActive = false;
        let livePatchFrame = null;
        let livePatchToken = 0;
        let averageMatchRowHeight = 186;
        let currentVisibleRowSet = new Set();
        let currentVisibleLeagueSet = new Set();
        let currentVisibleLeagueCountByNode = new Map();
        const leagueWindowBufferPx = 960;
        const leagueWindowMinimumPlaceholderPx = 220;
        const boardRefreshMinimumVisibleMs = 120;
        const hydrationLoadingWatchdogMs = 8000;
        const initialVisibleRowLimit = 24;
        const progressiveSearchInitialRowLimit = 24;
        const progressiveSearchDeferredBatchSize = 24;
        const visibleRowIncrement = 24;
        let visibleRowLimit = initialVisibleRowLimit;
        const rowVisibilityBatchSize = 72;

        sportFilterButtons.forEach(function (button) {
            const sportKey = button.dataset.sportFilter || '';
            const countNode = button.querySelector('.sb2-sport-count');
            const initialCount = Number(countNode ? (countNode.dataset.countRaw || 0) : 0);

            if (sportKey !== '' && initialCount > 0) {
                rememberedSportCountsByTab['pre-match'][sportKey] = initialCount;
            }
        });
        const livePatchBatchSize = 64;
        const sportLabelLookup = {
            upcoming: t('sport_upcoming', 'Yaklaşan'),
            soccer: t('sport_soccer', 'Futbol'),
            tennis: t('sport_tennis', 'Tenis'),
            basketball: t('sport_basketball', 'Basketbol'),
            snooker: t('sport_snooker', 'Snooker'),
            table_tennis: t('sport_table_tennis', 'Masa Tenisi'),
            dota2: t('sport_dota2', 'Dota 2'),
            cs2: t('sport_cs2', 'CS2'),
            volleyball: t('sport_volleyball', 'Voleybol'),
            baseball: t('sport_baseball', 'Beyzbol'),
            icehockey: t('sport_icehockey', 'Buz Hokeyi'),
            other: t('sport_other', 'Diğer'),
        };
        applySportsbookTheme(initialSportsbookTheme, false);
        applyAutoAcceptOddsPreference(resolveAutoAcceptOddsPreference(), false);

        function renderSummaryDebugState(summary) {
            if (!boardStatusDebug) {
                return;
            }

            const nextSummary = summary && typeof summary === 'object' ? summary : {};
            const summaryState = String(nextSummary.summary_state || 'healthy');
            const guardReason = String(nextSummary.summary_guard_reason || '');
            const lastHealthy = String(nextSummary.summary_last_healthy_generated_at || '');
            const rawGeneratedAt = String(nextSummary.summary_raw_generated_at || '');
            const rawTotal = Number(nextSummary.summary_raw_total || 0);

            layout.dataset.summaryState = summaryState;
            layout.dataset.summaryGuardReason = guardReason;
            layout.dataset.summaryLastHealthyGeneratedAt = lastHealthy;
            layout.dataset.summaryRawGeneratedAt = rawGeneratedAt;
            layout.dataset.summaryRawTotal = String(rawTotal);

            if (!summaryDebugEnabled) {
                if (boardStatus) {
                    boardStatus.hidden = true;
                }
                boardStatusDebug.hidden = true;
                boardStatusDebug.textContent = '';
                return;
            }

            if (boardStatus) {
                boardStatus.hidden = false;
            }
            boardStatusDebug.hidden = false;
            boardStatusDebug.classList.toggle('is-guarded', summaryState !== 'healthy');
            boardStatusDebug.classList.toggle('is-healthy', summaryState === 'healthy');
            boardStatusDebug.textContent = summaryState === 'healthy'
                ? `Özet: healthy • raw ${formatCountLabel(rawTotal)}`
                : `Özet: ${summaryState} • ${guardReason || 'guard'} • raw ${formatCountLabel(rawTotal)} • son sağlıklı ${lastHealthy || '-'}`;
        }

        function resolveSportsbookThemePreference() {
            const serverTheme = sportsbookThemeRoot ? String(sportsbookThemeRoot.dataset.sportsbookTheme || 'default') : 'default';
            let storedTheme = null;

            try {
                storedTheme = window.localStorage ? window.localStorage.getItem(sportsbookThemeStorageKey) : null;
            } catch (error) {
                storedTheme = null;
            }

            if (availableSportsbookThemes.has(storedTheme)) {
                return storedTheme;
            }

            return availableSportsbookThemes.has(serverTheme) ? serverTheme : 'default';
        }

        function getSportsbookThemeMeta(theme) {
            if (theme === 'dark_blend') {
                return {
                    theme: 'dark_blend',
                    label: t('switch_to_light_theme', 'Açık temaya geç'),
                    pressed: true,
                    icon: `<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3.5v2.25M12 18.25v2.25M4.75 12H2.5M21.5 12h-2.25M6.1 6.1 4.5 4.5M19.5 19.5l-1.6-1.6M17.9 6.1l1.6-1.6M4.5 19.5l1.6-1.6M12 7.25A4.75 4.75 0 1 1 7.25 12 4.76 4.76 0 0 1 12 7.25Z" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"/></svg>`,
                };
            }

            return {
                theme: 'default',
                label: t('switch_to_dark_theme', 'Koyu temaya geç'),
                pressed: false,
                icon: `<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 15.5A8.5 8.5 0 1 1 8.5 4a6.7 6.7 0 0 0 11.5 11.5Z" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"/></svg>`,
            };
        }

        function applySportsbookTheme(theme, persist = true) {
            if (!sportsbookThemeRoot) {
                return;
            }

            const normalizedTheme = availableSportsbookThemes.has(theme) ? theme : 'default';
            sportsbookThemeRoot.dataset.sportsbookTheme = normalizedTheme;
            if (layout && layout !== sportsbookThemeRoot) {
                layout.dataset.sportsbookTheme = normalizedTheme;
            }

            if (persist) {
                try {
                    if (window.localStorage) {
                        window.localStorage.setItem(sportsbookThemeStorageKey, normalizedTheme);
                    }
                } catch (error) {
                    // Ignore storage failures in private browsing or disabled storage contexts.
                }
            }

            if (themeToggleButton) {
                const meta = getSportsbookThemeMeta(normalizedTheme);
                themeToggleButton.setAttribute('aria-label', meta.label);
                themeToggleButton.setAttribute('aria-pressed', meta.pressed ? 'true' : 'false');
                themeToggleButton.title = meta.label;
                themeToggleButton.innerHTML = meta.icon;
            }
        }

        function toggleSportsbookTheme() {
            const currentTheme = sportsbookThemeRoot ? String(sportsbookThemeRoot.dataset.sportsbookTheme || 'default') : 'default';
            const nextTheme = currentTheme === 'dark_blend' ? 'default' : 'dark_blend';

            applySportsbookTheme(nextTheme, true);
        }

        function resolveAutoAcceptOddsPreference() {
            try {
                return window.localStorage
                    ? window.localStorage.getItem(autoAcceptOddsStorageKey) === '1'
                    : false;
            } catch (error) {
                return false;
            }
        }

        function applyAutoAcceptOddsPreference(enabled, persist = true) {
            autoAcceptOddsChanges = Boolean(enabled);

            if (autoAcceptOddsChangeToggle) {
                autoAcceptOddsChangeToggle.checked = autoAcceptOddsChanges;
            }

            if (autoAcceptOddsChangeHelper) {
                autoAcceptOddsChangeHelper.textContent = autoAcceptOddsChanges
                    ? t('auto_accept_on', 'Otomatik oran kabulü açık. Oran değişirse güncel oranla devam edilir.')
                    : t('auto_accept_off', 'Kapalı: Oran değişirse senden onay istenir.');
            }

            if (!persist) {
                return;
            }

            try {
                if (window.localStorage) {
                    window.localStorage.setItem(autoAcceptOddsStorageKey, autoAcceptOddsChanges ? '1' : '0');
                }
            } catch (error) {
                // Keep the coupon-local preference even when storage is unavailable.
            }
        }

        function formatCurrency(value) {
            return new Intl.NumberFormat('tr-TR', {
                style: 'currency',
                currency: 'TRY',
                minimumFractionDigits: 2,
            }).format(Number(value || 0));
        }

        function formatDecimal(value, digits) {
            return Number(value || 0).toLocaleString('tr-TR', {
                minimumFractionDigits: digits,
                maximumFractionDigits: digits,
            });
        }

        function formatChipLabel(value) {
            return '₺' + Number(value || 0).toLocaleString('tr-TR', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 0,
            });
        }

        function shortReferenceLabel(value) {
            const ref = String(value || '').trim();
            if (ref === '' || ref === '-') {
                return '-';
            }

            if (ref.length <= 12) {
                return ref;
            }

            return ref.slice(0, 8) + '…' + ref.slice(-4);
        }

        const sportsbookDataRequestCache = new Map();
        const sportsbookDataRequestInFlight = new Map();
        const sportsbookDataCacheTtlMs = 90000;
        const sportsbookDataCacheablePaths = new Set([
            '/cms/sportsbook/bet-feed-items',
            '/cms/sportsbook/prediction-items',
            '/cms/sportsbook/social-market-items',
            '/cms/sportsbook/catalog-tree',
        ]);

        function cloneJsonPayload(payload) {
            if (payload === null || typeof payload !== 'object') {
                return payload;
            }

            if (typeof window.structuredClone === 'function') {
                try {
                    return window.structuredClone(payload);
                } catch (error) {
                    // Fall through to JSON cloning for plain endpoint payloads.
                }
            }

            return JSON.parse(JSON.stringify(payload));
        }

        function jsonResponseFromRecord(record, fromCache) {
            return {
                ok: Boolean(record && record.ok),
                status: Number((record && record.status) || 0),
                fromCache: Boolean(fromCache),
                json: function () {
                    return Promise.resolve(cloneJsonPayload(record ? record.payload : null));
                },
            };
        }

        function normalizedSportsbookDataRequestKey(url, options) {
            const requestOptions = options || {};
            const method = String(requestOptions.method || 'GET').toUpperCase();

            if (method !== 'GET'
                || requestOptions.forceRefresh === true
                || requestOptions.cache === 'no-store') {
                return '';
            }

            try {
                const parsedUrl = new URL(String(url || ''), window.location.origin);
                if (!sportsbookDataCacheablePaths.has(parsedUrl.pathname)) {
                    return '';
                }

                parsedUrl.hash = '';

                return method + ' ' + parsedUrl.toString();
            } catch (error) {
                return '';
            }
        }

        function rawRequestJson(url, options) {
            const requestOptions = options || {};
            const method = String(requestOptions.method || 'GET').toUpperCase();
            const headers = requestOptions.headers || {};
            const timeoutMs = Number(requestOptions.timeoutMs || 0);

            if (typeof window.fetch === 'function') {
                const fetchOptions = Object.assign({}, requestOptions);
                delete fetchOptions.timeoutMs;

                if (timeoutMs > 0 && typeof window.AbortController === 'function' && !fetchOptions.signal) {
                    const controller = new AbortController();
                    const timeout = window.setTimeout(function () {
                        controller.abort();
                    }, timeoutMs);
                    fetchOptions.signal = controller.signal;

                    return window.fetch(url, fetchOptions).finally(function () {
                        window.clearTimeout(timeout);
                    });
                }

                return window.fetch(url, fetchOptions);
            }

            return new Promise(function (resolve, reject) {
                if (typeof XMLHttpRequest !== 'function') {
                    reject(new Error('json_request_unavailable'));
                    return;
                }

                const xhr = new XMLHttpRequest();
                xhr.open(method, url, true);
                if (timeoutMs > 0) {
                    xhr.timeout = timeoutMs;
                }
                Object.keys(headers).forEach(function (key) {
                    xhr.setRequestHeader(key, headers[key]);
                });
                xhr.withCredentials = requestOptions.credentials === 'same-origin' || requestOptions.credentials === 'include';
                xhr.onload = function () {
                    resolve({
                        ok: xhr.status >= 200 && xhr.status < 300,
                        status: xhr.status,
                        json: function () {
                            return Promise.resolve(JSON.parse(xhr.responseText || 'null'));
                        },
                    });
                };
                xhr.onerror = function () {
                    reject(new Error('json_request_failed'));
                };
                xhr.ontimeout = function () {
                    reject(new Error('json_request_timeout'));
                };
                xhr.send(requestOptions.body || null);
            });
        }

        function requestJson(url, options) {
            const requestOptions = options || {};
            const cacheKey = normalizedSportsbookDataRequestKey(url, requestOptions);
            const cacheTtlMs = Number(requestOptions.cacheTtlMs || sportsbookDataCacheTtlMs);
            const now = Date.now();

            if (cacheKey) {
                const cached = sportsbookDataRequestCache.get(cacheKey);
                if (cached && Number.isFinite(cacheTtlMs) && now - cached.cachedAt <= cacheTtlMs) {
                    return Promise.resolve(jsonResponseFromRecord(cached, true));
                }

                const inFlight = sportsbookDataRequestInFlight.get(cacheKey);
                if (inFlight) {
                    return inFlight.then(function (record) {
                        return jsonResponseFromRecord(record, true);
                    });
                }

                const promise = rawRequestJson(url, requestOptions)
                    .then(async function (response) {
                        let payload = null;
                        try {
                            payload = await response.json();
                        } catch (error) {
                            payload = null;
                        }

                        const record = {
                            ok: Boolean(response.ok),
                            status: Number(response.status || 0),
                            payload: payload,
                            cachedAt: Date.now(),
                        };

                        if (record.ok) {
                            sportsbookDataRequestCache.set(cacheKey, record);
                        }

                        return record;
                    })
                    .finally(function () {
                        sportsbookDataRequestInFlight.delete(cacheKey);
                    });

                sportsbookDataRequestInFlight.set(cacheKey, promise);

                return promise.then(function (record) {
                    return jsonResponseFromRecord(record, false);
                });
            }

            return rawRequestJson(url, requestOptions);
        }
        window.sportsbookShellRequestJson = requestJson;

        let tabPrewarmStarted = false;
        const tabPrewarmConcurrency = 1;
        window.sportsbookShellPrewarmState = {
            started: false,
            completed: false,
            tasks: [],
            completedTasks: [],
            failedTasks: [],
        };

        function sportsbookDataUrl(rawUrl, params) {
            if (!rawUrl) {
                return '';
            }

            try {
                const endpoint = new URL(String(rawUrl), window.location.origin);
                Object.entries(params || {}).forEach(function (entry) {
                    if (entry[1] !== null && entry[1] !== undefined && String(entry[1]) !== '') {
                        endpoint.searchParams.set(entry[0], String(entry[1]));
                    }
                });

                return endpoint.toString();
            } catch (error) {
                return '';
            }
        }

        async function prewarmSportsbookDataUrl(task) {
            if (!task || !task.url) {
                return;
            }

            try {
                const response = await requestJson(task.url, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                    },
                    credentials: 'same-origin',
                    cacheTtlMs: sportsbookDataCacheTtlMs,
                });

                if (response.ok) {
                    await response.json();
                    window.sportsbookShellPrewarmState.completedTasks.push(task.label);
                } else {
                    window.sportsbookShellPrewarmState.failedTasks.push(task.label);
                }
            } catch (error) {
                window.sportsbookShellPrewarmState.failedTasks.push(task.label);
            }
        }

        async function runSportsbookPrewarmQueue(tasks) {
            const queue = Array.isArray(tasks) ? tasks.slice() : [];
            const workers = Array.from({ length: Math.min(tabPrewarmConcurrency, queue.length) }, async function () {
                while (queue.length) {
                    const task = queue.shift();
                    await prewarmSportsbookDataUrl(task);
                }
            });

            await Promise.all(workers);
            window.sportsbookShellPrewarmState.completed = true;
            document.dispatchEvent(new CustomEvent('sportsbook-shell-prewarm-completed', {
                detail: window.sportsbookShellPrewarmState,
            }));
        }

        function scheduleSportsbookTabPrewarm() {
            if (tabPrewarmStarted) {
                return;
            }

            if (window.performance && typeof window.performance.mark === 'function') {
                window.performance.mark('sportsbook-prewarm-started');
            }

            const tasks = [];
            const seenUrls = new Set();
            const addTask = function (label, url) {
                if (!url) {
                    return;
                }

                tasks.push({
                    label: label,
                    url: url,
                    duplicateOfEarlierTask: seenUrls.has(url),
                });
                seenUrls.add(url);
            };

            addTask('bet-feed', sportsbookDataUrl(betFeedPrewarmConfig && betFeedPrewarmConfig.items_url, {
                limit: 18,
            }));
            addTask('social-market', sportsbookDataUrl(socialMarketPrewarmConfig && socialMarketPrewarmConfig.items_url));
            addTask('e-sports', sportsbookDataUrl(socialMarketPrewarmConfig && socialMarketPrewarmConfig.items_url));
            addTask('predictions', sportsbookDataUrl(predictionPrewarmConfig && predictionPrewarmConfig.items_url, {
                limit: predictionPrewarmConfig && predictionPrewarmConfig.limit ? predictionPrewarmConfig.limit : 4,
            }));

            const catalogEndpoint = catalogTreeEndpointForCurrentScope();
            addTask('catalog-tree', catalogEndpoint ? catalogEndpoint.toString() : '');

            if (!tasks.length) {
                return;
            }

            tabPrewarmStarted = true;
            window.sportsbookShellPrewarmState = {
                started: true,
                completed: false,
                tasks: tasks.map(function (task) {
                    return task.label;
                }),
                completedTasks: [],
                failedTasks: [],
                startedAt: Date.now(),
            };
            document.dispatchEvent(new CustomEvent('sportsbook-shell-prewarm-started', {
                detail: window.sportsbookShellPrewarmState,
            }));

            const run = function () {
                runSportsbookPrewarmQueue(tasks);
            };

            window.setTimeout(run, 40);
        }

        function scheduleSportsbookTabPrewarmWhenUsable(attempt) {
            if (tabPrewarmStarted) {
                return;
            }

            const nextAttempt = Number(attempt || 0);
            const settledEmptyState = (!initialPayloadDeferred || hydrationReady)
                ? document.querySelector('[data-initial-offers-empty-state]:not([hidden]), [data-filter-empty-state]:not([hidden])')
                : null;
            const preMatchUsable = hydrationReady
                || (!initialPayloadDeferred && visibleOfferRowCount() > 0)
                || Boolean(settledEmptyState);

            if (preMatchUsable || (!initialPayloadDeferred && nextAttempt >= 20)) {
                scheduleSportsbookTabPrewarm();
                return;
            }

            window.setTimeout(function () {
                scheduleSportsbookTabPrewarmWhenUsable(nextAttempt + 1);
            }, 350);
        }

        function formatDisplayOdd(value) {
            const normalized = String(value ?? '').trim().replace(',', '.');
            if (normalized === '' || normalized === '-') {
                return '-';
            }

            const sign = normalized.startsWith('-') ? '-' : '';
            const unsigned = sign ? normalized.slice(1) : normalized;
            const match = unsigned.match(/^(\d+)(?:\.(\d+))?$/);

            if (!match) {
                const numeric = Number(normalized);
                if (!Number.isFinite(numeric)) {
                    return normalized;
                }

                return numeric.toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                    useGrouping: false,
                });
            }

            const integerPart = match[1];
            const decimals = (match[2] || '');
            const centsBase = integerPart + (decimals + '00').slice(0, 2);
            let cents = BigInt(centsBase || '0');
            const roundDigit = Number((decimals + '000')[2] || '0');

            if (roundDigit >= 5) {
                cents += 1n;
            }

            const padded = cents.toString().padStart(3, '0');
            const whole = padded.slice(0, -2) || '0';
            const fraction = padded.slice(-2);

            return sign + whole + '.' + fraction;
        }

        function oddNumericValue(payload) {
            if (!payload) {
                return 0;
            }

            const rawValue = payload.raw_odd ?? payload.odd ?? payload.price ?? 0;
            const numeric = Number(String(rawValue).replace(',', '.'));
            return Number.isFinite(numeric) ? numeric : 0;
        }

        function escapeHtml(value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function safeAssetPath(path) {
            const value = String(path || '').trim();
            const allowedRoots = [
                '/assets/cms/img/sportsbook/',
                '/assets/cms/img/sportsbook-visual-assets/',
                '/assets/cms/img/sportsbook-catalog/',
                '/cms-assets/',
            ];

            if (!value || !value.startsWith('/') || value.startsWith('//') || value.includes('..') || value.includes('\\')) {
                return '';
            }

            return allowedRoots.some(function (root) { return value.startsWith(root); }) ? value : '';
        }

        function entityPlaceholderLabel(asset, label) {
            const explicit = String((asset && asset.placeholder_label) || '').trim();
            if (explicit) {
                return explicit.slice(0, 4).toLocaleUpperCase('tr-TR');
            }

            const compact = String(label || 'SB').replace(/[^A-Za-z0-9ÇĞİÖŞÜçğıöşü ]/g, '').trim();
            if (!compact) {
                return 'SB';
            }

            const parts = compact.split(/\s+/).filter(Boolean);
            if (parts.length === 1) {
                return compact.slice(0, 2).toLocaleUpperCase('tr-TR');
            }

            return parts.slice(0, 3).map(function (part) {
                return part.slice(0, 1).toLocaleUpperCase('tr-TR');
            }).join('');
        }

        function entityColor(asset) {
            const color = String((asset && asset.color) || '#425466').trim();

            return /^#[0-9a-fA-F]{6}$/.test(color) ? color : '#425466';
        }

        function entityMarkHtml(asset, label, type) {
            const descriptor = asset && typeof asset === 'object' ? asset : {};
            const path = safeAssetPath(descriptor.path || '');
            const fallback = escapeHtml(entityPlaceholderLabel(descriptor, label));
            const color = escapeHtml(entityColor(descriptor));
            const modifier = escapeHtml(String(type || descriptor.entity_type || 'entity').replace(/[^a-z0-9_-]+/gi, '-').toLowerCase());
            const iconKey = escapeHtml(String(descriptor.icon_key || 'shield'));
            const image = path
                ? `<img class="sb2-entity-mark__image" src="${escapeHtml(path)}" alt="" loading="lazy" data-entity-mark-image><span class="sb2-entity-mark__fallback" hidden>${fallback}</span>`
                : `<span class="sb2-entity-mark__fallback">${fallback}</span>`;

            return `<span class="sb2-entity-mark sb2-entity-mark--${modifier}" style="--sb2-entity-color: ${color};" data-icon-key="${iconKey}" aria-hidden="true">${image}</span>`;
        }

        const sportCardArtAssetByKey = {
            soccer: { path: '/assets/cms/img/sportsbook/sports/soccer.svg', color: '#0f9f6e' },
            basketball: { path: '/assets/cms/img/sportsbook/sports/basketball.svg', color: '#d97706' },
            tennis: { path: '/assets/cms/img/sportsbook/sports/tennis.svg', color: '#65a30d' },
            baseball: { path: '/assets/cms/img/sportsbook/sports/baseball.svg', color: '#dc2626' },
            icehockey: { path: '/assets/cms/img/sportsbook/sports/icehockey.svg', color: '#0891b2' },
            volleyball: { path: '/assets/cms/img/sportsbook/sports/volleyball.svg', color: '#7c3aed' },
            table_tennis: { path: '/assets/cms/img/sportsbook/sports/table_tennis.svg', color: '#16a34a' },
            snooker: { path: '/assets/cms/img/sportsbook/sports/snooker.svg', color: '#0f766e' },
            'e-sports': { path: '/assets/cms/img/sportsbook/sports/cs2.svg', color: '#7c3aed' },
        };

        const catalogSportTitleAssetByKey = {
            soccer: { path: '/cms-assets/themes/winx/header-logo/assets/symbols/football.webp', color: '#0f9f6e' },
            basketball: { path: '/cms-assets/themes/winx/preloader/assets/symbols/basketball.webp', color: '#d97706' },
            tennis: { path: '/cms-assets/themes/winx/preloader/assets/symbols/tennis.webp', color: '#65a30d' },
        };

        function sportCardArtHtml(asset, label, sportKey) {
            const normalizedSportKey = String(sportKey || '').trim().toLowerCase();
            const cleanAsset = {};
            Object.entries(asset && typeof asset === 'object' ? asset : {}).forEach(function (entry) {
                if (entry[1] !== null && entry[1] !== '') {
                    cleanAsset[entry[0]] = entry[1];
                }
            });
            const descriptor = Object.assign(
                {},
                sportCardArtAssetByKey[normalizedSportKey] || {},
                cleanAsset
            );

            return `<span class="sb2-sport-card-art" aria-hidden="true">${entityMarkHtml(descriptor, label, 'sport')}</span>`;
        }

        document.addEventListener('error', function (event) {
            const image = event.target;
            if (!image || !image.matches || !image.matches('[data-entity-mark-image]')) {
                return;
            }

            image.hidden = true;
            const fallback = image.parentElement ? image.parentElement.querySelector('.sb2-entity-mark__fallback') : null;
            if (fallback) {
                fallback.hidden = false;
            }
        }, true);

        function sportLabelFromKey(sportGroupKey) {
            return sportLabelLookup[sportGroupKey || 'other'] || t('sport_other', 'Diğer');
        }

        function sportIconFromKey(sportGroupKey) {
            const normalizedKey = String(sportGroupKey || '').trim().toLowerCase();
            const sportIconLookup = {
                soccer: '⚽',
                football: '⚽',
                basketball: '🏀',
                tennis: '🎾',
                volleyball: '🏐',
            };

            return sportIconLookup[normalizedKey] || '🏟️';
        }

        function escapeSelectorValue(value) {
            if (window.CSS && typeof window.CSS.escape === 'function') {
                return window.CSS.escape(String(value || ''));
            }

            return String(value || '').replace(/["\\]/g, '\\$&');
        }

        function formatCountLabel(count) {
            const numeric = Math.max(Number(count || 0), 0);
            return numeric > 1000 ? '1000+' : String(numeric);
        }

        function sportMonogram(label) {
            const normalized = String(label || 'SP').replace(/[^A-Za-z0-9ÇĞİÖŞÜçğıöşü ]/g, '').trim();
            if (!normalized) {
                return 'SP';
            }

            const parts = normalized.split(/\s+/).filter(Boolean);
            if (parts.length === 1) {
                return normalized.slice(0, 2).toUpperCase();
            }

            return parts.slice(0, 2).map(function (part) {
                return part.slice(0, 1).toUpperCase();
            }).join('');
        }

        function sportIconHtml(label) {
            const normalized = String(label || '').trim().toLowerCase();
            const iconClassByLabel = {
                'futbol': 'fas fa-futbol',
                'basketbol': 'fas fa-basketball-ball',
                'tenis': 'fas fa-tennis-ball',
                'beyzbol': 'fas fa-baseball-ball',
                'buz hokeyi': 'fas fa-hockey-puck',
                'buz': 'fas fa-hockey-puck',
                'diğer': 'fas fa-shapes',
                'diger': 'fas fa-shapes',
                'voleybol': 'fas fa-volleyball-ball',
                'masa tenisi': 'fas fa-table-tennis',
                'snooker': 'fas fa-bullseye',
            };
            const iconClass = iconClassByLabel[normalized];

            if (iconClass) {
                return `<i class="${escapeHtml(iconClass)}" aria-hidden="true"></i>`;
            }

            return `<span class="sb2-sport-monogram">${escapeHtml(sportMonogram(label))}</span>`;
        }

        function selectionPayloadForButton(item, shellEvent) {
            return Object.assign({}, item, {
                raw_odd: item.raw_odd ?? item.odd ?? '',
                odd: formatDisplayOdd(item.raw_odd ?? item.odd ?? ''),
                context_time: shellEvent.time || '',
                context_date: shellEvent.date || '',
                context_sport: shellEvent.sport_label || '',
                more_label: shellEvent.more || (Array.isArray(shellEvent.extra_markets) && shellEvent.extra_markets.length ? '+' + shellEvent.extra_markets.length : ''),
                quote_payload_fetched_at: pageBootedAt,
            });
        }

        function isAdapterQuoteRefSelection(selection) {
            return Boolean(selection)
                && selection.acceptance_mode === 'adapter_vps_selection_token';
        }

        function isSocialMarketSelection(selection) {
            return Boolean(selection)
                && selection.selection_source === 'social_market'
                && String(selection.selection_token || '').trim() !== '';
        }

        function adapterSelectionToken(selection) {
            return String((selection && (selection.selection_token || selection.client_selection_ref)) || '').trim();
        }

        function acceptanceEndpointForSelection(selection) {
            return isAdapterQuoteRefSelection(selection) ? adapterAcceptanceApiUrl : acceptanceApiUrl;
        }

        function acceptancePayloadForSelection(selection) {
            const stakeAmount = Number(currentStake().toFixed(2));

            if (isAdapterQuoteRefSelection(selection)) {
                return {
                    selection_token: adapterSelectionToken(selection),
                    stake_amount: stakeAmount,
                    currency: 'TRY',
                    accepted_price: oddNumericValue(selection),
                };
            }

            if (isSocialMarketSelection(selection)) {
                return {
                    selection_source: 'social_market',
                    selection_token: String(selection.selection_token || ''),
                    stake_amount: stakeAmount,
                    currency: 'TRY',
                    accepted_price: oddNumericValue(selection),
                };
            }

            return {
                ...(selection.selection_source === 'social_feed' ? {selection_source: 'social_feed'} : {}),
                quote_version_id: selection.quote_version_id,
                quote_selection_id: selection.quote_selection_id,
                stake_amount: stakeAmount,
                currency: 'TRY',
                accepted_price: oddNumericValue(selection),
            };
        }

        function isAdapterQuoteRefPayload(selection) {
            return Boolean(selection)
                && selection.acceptance_mode === 'adapter_vps_selection_token'
                && adapterSelectionToken(selection) !== '';
        }

        function stampSelectedPayload(selection, options) {
            if (!selection) {
                return selection;
            }

            const now = Date.now();
            const nextOptions = options || {};

            return Object.assign({}, selection, {
                selected_at: nextOptions.selectedAt || selection.selected_at || now,
                quote_payload_fetched_at: nextOptions.payloadFetchedAt || selection.quote_payload_fetched_at || pageBootedAt,
                quote_checked_at: nextOptions.refreshedAt || selection.quote_checked_at || '',
            });
        }

        function adapterQuoteAgeMs(selection) {
            if (!isAdapterQuoteRefSelection(selection)) {
                return 0;
            }

            const fetchedAt = Number(selection.quote_payload_fetched_at || selection.quote_checked_at || selection.selected_at || pageBootedAt);

            return Number.isFinite(fetchedAt) && fetchedAt > 0 ? Math.max(0, Date.now() - fetchedAt) : Number.POSITIVE_INFINITY;
        }

        function adapterQuoteNeedsFreshSubmitCheck(selection) {
            return isAdapterQuoteRefSelection(selection) && (adapterSelectionToken(selection) === ''
                || adapterQuoteAgeMs(selection) >= adapterQuoteRefreshBeforeSubmitMs);
        }

        function adapterSelectionRefreshKey(selection) {
            if (!isAdapterQuoteRefSelection(selection) && !(selection && selection.contract_required)) {
                return '';
            }

            return couponSelectionIdentity(selection);
        }

        function couponSelectionIdentity(selection) {
            if (!selection) return '';
            return [
                selection.event_id || '',
                selection.market_type || '',
                selection.market_specifier_key || 'default',
                selection.outcome_code || '',
            ].join('::');
        }

        function mergeAdapterRefreshedSelection(previousSelection, refreshedSelection) {
            if (!previousSelection || !refreshedSelection) {
                return null;
            }

            return stampSelectedPayload(Object.assign({}, previousSelection, refreshedSelection, {
                context_time: refreshedSelection.context_time || previousSelection.context_time || '',
                context_date: refreshedSelection.context_date || previousSelection.context_date || '',
                context_sport: refreshedSelection.context_sport || previousSelection.context_sport || '',
                event_name: refreshedSelection.event_name || previousSelection.event_name || '',
                market_name: refreshedSelection.market_name || previousSelection.market_name || '',
                outcome_name: refreshedSelection.outcome_name || previousSelection.outcome_name || '',
            }), {
                selectedAt: previousSelection.selected_at || Date.now(),
                payloadFetchedAt: refreshedSelection.quote_payload_fetched_at || Date.now(),
                refreshedAt: refreshedSelection.quote_checked_at || Date.now(),
            });
        }

        function startSelectedAdapterQuoteRefresh(selection) {
            if (!isAdapterQuoteRefSelection(selection)) {
                selectedQuoteRefreshKey = '';
                selectedQuoteRefreshPromise = null;
                exactWarningKey = '';
                setCouponQuoteUpdating(false);
                return null;
            }

            const refreshKey = adapterSelectionRefreshKey(selection);
            if (!refreshKey) {
                return null;
            }

            if (selectedQuoteRefreshPromise && selectedQuoteRefreshKey === refreshKey) {
                return selectedQuoteRefreshPromise;
            }

            selectedQuoteRefreshKey = refreshKey;
            setCouponQuoteUpdating(true);
            const pendingRefresh = refreshEventOffer(
                selection.event_id,
                selection.market_type,
                selection.outcome_code,
                selection.market_specifier_key,
                selection
            ).then(function (refreshedSelection) {
                if (selectedQuoteRefreshPromise !== pendingRefresh || !selected || adapterSelectionRefreshKey(selected) !== refreshKey) {
                    return null;
                }
                if (!refreshedSelection || refreshedSelection.selectable === false) {
                    exactWarningKey = refreshKey;
                    renderExactRefreshWarning();
                    return null;
                }

                if (selected && adapterSelectionRefreshKey(selected) === refreshKey) {
                    exactWarningKey = '';
                    selected = mergeAdapterRefreshedSelection(selected, refreshedSelection);
                    currentIdempotencyKey = null;
                    renderSelectionState();
                }

                return refreshedSelection;
            }).catch(function () {
                if (selectedQuoteRefreshPromise === pendingRefresh && selected && adapterSelectionRefreshKey(selected) === refreshKey) {
                    exactWarningKey = refreshKey;
                    renderExactRefreshWarning();
                }
                return null;
            });

            selectedQuoteRefreshPromise = pendingRefresh;
            pendingRefresh.finally(function () {
                if (selectedQuoteRefreshPromise === pendingRefresh) {
                    setCouponQuoteUpdating(false);
                }
            });

            return selectedQuoteRefreshPromise;
        }

        function setCouponQuoteUpdating(updating) {
            if (!slipShell) {
                return;
            }

            slipShell.classList.toggle('is-quote-updating', Boolean(updating));
            slipShell.setAttribute('aria-busy', updating ? 'true' : 'false');
            if (exactRetry) exactRetry.disabled = updating;
            renderExactRefreshWarning();
        }

        function renderExactRefreshWarning() {
            if (!selected || adapterSelectionRefreshKey(selected) !== exactWarningKey) {
                exactWarningKey = '';
            }
            if (exactWarning) exactWarning.hidden = !exactWarningKey;
        }

        function retrySelectedExactRefresh() {
            if (!selected || !exactWarningKey || (exactRetry && exactRetry.disabled) || submitState === 'submitting' || frozenAfterSuccess) {
                return;
            }
            // Only this explicit user action starts another request after a failed refresh.
            selectedQuoteRefreshPromise = null;
            return startSelectedAdapterQuoteRefresh(selected);
        }

        function pulseSelectionFeedback(button) {
            if (!button) {
                return;
            }

            button.classList.remove('is-adding-to-slip');
            void button.offsetWidth;
            button.classList.add('is-adding-to-slip');
            window.setTimeout(function () {
                button.classList.remove('is-adding-to-slip');
            }, 520);
        }

        function applyAdapterRefreshedSelectionToSelected(refreshKey, refreshedSelection, previousSelection) {
            if (!refreshedSelection || refreshedSelection.selectable === false || !selected) {
                return null;
            }

            if (adapterSelectionRefreshKey(selected) !== refreshKey || (previousSelection && selected !== previousSelection)) {
                return null;
            }

            selected = mergeAdapterRefreshedSelection(previousSelection || selected, refreshedSelection);
            currentIdempotencyKey = null;
            acceptedPayload = null;
            inlineGateMessage = '';
            renderSelectionState();

            return selected;
        }

        function shellSelectionKey(selection) {
            if (!selection) {
                return '';
            }

            const explicitKey = String(selection.selection_key || '').trim();
            if (explicitKey !== '') {
                return explicitKey;
            }

            return [
                selection.event_id || '',
                selection.market_type || '',
                selection.market_specifier_key || 'default',
                selection.outcome_code || '',
            ].join('::');
        }

        function notifyShellSelectionChange() {
            try {
                window.dispatchEvent(new CustomEvent('sportsbook-shell-selection-change', {
                    detail: {
                        has_selection: Boolean(selected),
                        selection_key: shellSelectionKey(selected),
                    },
                }));
            } catch (error) {
                // Feed button feedback is cosmetic; betslip state remains authoritative.
            }
        }

        function normalizeSearchQuery(value) {
            const raw = String(value || '').trim().toLocaleLowerCase('tr-TR');
            if (raw === '') {
                return '';
            }

            const asciiFolded = raw.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
            const aliases = [
                ['amerika birlesik devletleri', 'usa'],
                ['yeni zelanda', 'new zealand'],
                ['guney afrika', 'south africa'],
                ['guney kore', 'south korea'],
                ['kuzey kore', 'north korea'],
                ['cek cumhuriyeti', 'czech republic'],
                ['turkiye', 'turkey'],
                ['ingiltere', 'england'],
                ['ispanya', 'spain'],
                ['italya', 'italy'],
                ['almanya', 'germany'],
                ['fransa', 'france'],
                ['portekiz', 'portugal'],
                ['danimarka', 'denmark'],
                ['macaristan', 'hungary'],
                ['hirvatistan', 'croatia'],
                ['bulgaristan', 'bulgaria'],
                ['hollanda', 'netherlands'],
                ['iskocya', 'scotland'],
                ['norvec', 'norway'],
                ['finlandiya', 'finland'],
                ['irlanda', 'ireland'],
                ['belcika', 'belgium'],
                ['avusturya', 'austria'],
                ['polonya', 'poland'],
                ['isvicre', 'switzerland'],
                ['yunanistan', 'greece'],
                ['ukrayna', 'ukraine'],
                ['rusya', 'russia'],
                ['romanya', 'romania'],
                ['sirbistan', 'serbia'],
                ['slovakya', 'slovakia'],
                ['slovenya', 'slovenia'],
                ['litvanya', 'lithuania'],
                ['letonya', 'latvia'],
                ['estonya', 'estonia'],
                ['izlanda', 'iceland'],
                ['amerika', 'usa'],
                ['abd', 'usa'],
            ];

            const normalized = aliases
                .sort(function (left, right) {
                    return right[0].length - left[0].length;
                })
                .reduce(function (text, pair) {
                    const pattern = new RegExp(`\\b${pair[0].replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}\\b`, 'giu');
                    return text.replace(pattern, pair[1]);
                }, asciiFolded);

            return normalized
                .replace(/\s+/g, ' ')
                .trim();
        }

        function invalidateBoardIndex() {
            phaseSearchIndexCache = new Map();
            filteredViewCache = new Map();
        }

        function refreshAllowedFilterSets() {
            allowedSportFilters = new Set(
                sportFilterButtons.map(function (button) {
                    return button.dataset.sportFilter || 'all';
                }).filter(isPrimarySportFilterKey)
            );
            allowedLeagueFilters = new Set(
                leagueFilterButtons.map(function (button) {
                    return button.dataset.leagueFilter || 'all';
                })
            );
            sportFilterButtonByKey = new Map(
                sportFilterButtons.map(function (button) {
                    return [button.dataset.sportFilter || 'all', button];
                })
            );
            leagueFilterButtonByKey = new Map(
                leagueFilterButtons.map(function (button) {
                    return [button.dataset.leagueFilter || 'all', button];
                })
            );
        }

        function isPrimarySportFilterKey(sportKey) {
            return typeof sportKey === 'string'
                && sportKey !== ''
                && sportKey !== 'all'
                && sportKey !== 'upcoming';
        }

        function sportRailRank(sportKey) {
            return Object.prototype.hasOwnProperty.call(productSportRankByKey, sportKey)
                ? productSportRankByKey[sportKey]
                : productSportOrder.length + 1;
        }

        function sportFilterLabel(button) {
            const labelNode = button ? button.querySelector('.sb2-sport-label') : null;

            return normalizeSearchQuery(labelNode ? labelNode.textContent || '' : '');
        }

        function moveSurfaceSportSwitchersToRailEnd() {
            if (!sportStrip) {
                return;
            }

            surfaceSportSwitchers.forEach(function (button) {
                if (button && button.parentElement === sportStrip) {
                    sportStrip.appendChild(button);
                }
            });
        }

        function sortSportFilterButtonsByPriority() {
            if (!sportStrip) {
                return;
            }

            Array.from(sportStrip.querySelectorAll('[data-sport-filter]'))
                .filter(function (button) {
                    return isPrimarySportFilterKey(button.dataset.sportFilter || '');
                })
                .sort(function (left, right) {
                    const leftKey = left.dataset.sportFilter || '';
                    const rightKey = right.dataset.sportFilter || '';
                    const leftRank = sportRailRank(leftKey);
                    const rightRank = sportRailRank(rightKey);

                    if (leftRank !== rightRank) {
                        return leftRank - rightRank;
                    }

                    return sportFilterLabel(left).localeCompare(sportFilterLabel(right), 'tr');
                })
                .forEach(function (button) {
                    sportStrip.appendChild(button);
                });
            moveSurfaceSportSwitchersToRailEnd();
        }

        function setInitialOffersEmptyStateVisible(visible) {
            if (initialOffersEmptyState) {
                initialOffersEmptyState.hidden = !visible;
            }
        }

        function setProviderDegradedPanel(root, state) {
            if (!root || !state) {
                return;
            }

            const title = root.querySelector('h2');
            const body = root.querySelector('p');
            let code = root.querySelector('[data-provider-degraded-code]');
            if (!code) {
                code = document.createElement('p');
                code.dataset.providerDegradedCode = '1';
                root.appendChild(code);
            }

            if (title) {
                title.textContent = t(state.code === 'SPORTSBOOK_SNAPSHOT_STALE' ? 'snapshot_stale_title' : 'provider_unavailable_title', 'Maç öncesi veriler şu anda yenilenemiyor.');
            }
            if (body) {
                body.textContent = t(state.code === 'SPORTSBOOK_SNAPSHOT_STALE' ? 'snapshot_stale_body' : 'provider_unavailable_body', 'Birkaç dakika sonra tekrar deneyin.');
            }
            code.textContent = t('error_code_prefix', 'Kod') + ': ' + String(state.code || 'SPORTSBOOK_ADAPTER_UNAVAILABLE');
            code.hidden = false;
        }

        function clearProviderDegradedPanels() {
            document.querySelectorAll('[data-provider-degraded-code]').forEach(function (code) {
                code.hidden = true;
            });
        }

        function makeProviderRowsPassiveForRefresh() {
            matchRows.slice().forEach(function (row) {
                Array.from(row.querySelectorAll('[data-selection-trigger]')).forEach(function (button) {
                    const payload = JSON.parse(button.dataset.selection || '{}');
                    if (!isAdapterQuoteRefPayload(payload)) {
                        return;
                    }
                    payload.selection_token = '';
                    payload.selectable = false;
                    payload.acceptance_ready = false;
                    payload.selection_state_message = t('odds_refreshing', 'Oran yenileniyor.');
                    button.dataset.selection = JSON.stringify(payload);
                    button.disabled = true;
                    button.classList.add('is-disabled');
                    button.dataset.disabledReason = 'contract_refresh_required';
                    button.setAttribute('aria-disabled', 'true');
                });
            });

            if (selected && isAdapterQuoteRefPayload(selected)) {
                selected = Object.assign({}, selected, {
                    selection_token: '',
                    selectable: false,
                    acceptance_ready: false,
                    selection_state_reason: 'refreshing',
                    selection_state_message: t('odds_refreshing', 'Oran yenileniyor.'),
                });
                renderSelectionState();
            }
        }

        function showProviderDegradedState(state, options) {
            const preservePassiveRows = Boolean((options || {}).preservePassiveRows);
            providerDegradedState = state || {
                code: 'SPORTSBOOK_ADAPTER_UNAVAILABLE',
                title: 'Maç öncesi veriler şu anda yenilenemiyor.',
                message: 'Birkaç dakika sonra tekrar deneyin.',
            };
            if (preservePassiveRows && matchRows.length > 0) {
                makeProviderRowsPassiveForRefresh();
            } else {
                matchRows.slice().forEach(function (row) {
                    removeRowFromDom(row, { deferRefresh: true });
                });
            }
            refreshDynamicCollections();
            updateMatchPagination();
            setBoardSkeletonVisible(false);
            setProviderDegradedPanel(initialOffersEmptyState, providerDegradedState);
            setProviderDegradedPanel(filterEmptyState, providerDegradedState);
            setInitialOffersEmptyStateVisible(true);
            if (filterEmptyState) {
                filterEmptyState.hidden = Boolean(initialOffersEmptyState);
            }
            hideFilterRetryAction();
            if (layout && initialPayloadDeferred) {
                layout.dataset.initialHydrationState = 'degraded';
            }
            if (layout) layout.dataset.boardState = 'degraded';
            setSurfaceState(activePrimaryTab === 'in-game' ? 'in-game' : 'pre-match', 'degraded');
        }

        function clearProviderDegradedState() {
            if (!providerDegradedState) {
                return;
            }

            providerDegradedState = null;
            clearProviderDegradedPanels();
            if (initialOffersEmptyState) {
                const title = initialOffersEmptyState.querySelector('h2');
                const body = initialOffersEmptyState.querySelector('p');
                if (title) {
                    title.textContent = t('no_prematch_offers', 'Şu anda gösterilecek maç öncesi bahis seçeneği yok.');
                }
                if (body) {
                    body.textContent = t('prematch_offers_hint', 'Güncel maçlar geldiğinde burada görünecek.');
                }
            }
            if (filterEmptyState) {
                const title = filterEmptyState.querySelector('h2');
                const body = filterEmptyState.querySelector('p');
                if (title) {
                    title.textContent = t('no_active_match', 'Aktif maç yok');
                }
                if (body) {
                    body.textContent = t('empty_try_clear_filters', 'Filtreleri temizleyerek maç listesine dönebilirsin.');
                }
            }
        }

        function visibleOfferRowCount() {
            return Array.from(document.querySelectorAll('[data-event-row]')).filter(function (row) {
                return !row.hidden;
            }).length;
        }

        function removeNonPrimarySportFilterButtons() {
            if (!sportStrip) {
                return;
            }

            Array.from(sportStrip.querySelectorAll('[data-sport-filter]')).forEach(function (button) {
                if (!isPrimarySportFilterKey(button.dataset.sportFilter || '')) {
                    button.remove();
                }
            });
        }

        function refreshDynamicCollections() {
            triggers = Array.from(document.querySelectorAll('[data-selection-trigger]'));
            matchRows = Array.from(document.querySelectorAll('[data-event-row]'));
            leagueNodes = Array.from(document.querySelectorAll('.sb2-league'));
            removeNonPrimarySportFilterButtons();
            sortSportFilterButtonsByPriority();
            sportFilterButtons = Array.from(document.querySelectorAll('[data-sport-filter]'));
            if (sportFilterButtons.length) {
                clearSportRailLoadingPlaceholder();
            }
            leagueFilterGroups = Array.from(document.querySelectorAll('[data-league-filter-group]'));
            leagueFilterButtons = Array.from(document.querySelectorAll('[data-league-filter]'));
            moreToggleButtons = Array.from(document.querySelectorAll('[data-more-toggle]'));
            refreshAllowedFilterSets();

            eventRowById = new Map();
            rowRecordByRow = new Map();
            const leagueRows = new Map();

            rowRecords = matchRows.map(function (row) {
                const leagueNode = row.closest('.sb2-league');
                const record = {
                    row: row,
                    eventId: String(row.dataset.eventId || ''),
                    phase: row.dataset.eventPhase || 'pre-match',
                    sportKey: row.dataset.sportKey || 'all',
                    leagueKey: row.dataset.leagueFilterKey || 'all',
                    searchText: normalizeSearchQuery(row.dataset.searchText || ''),
                    startsAt: Number(row.dataset.startsAt || Number.MAX_SAFE_INTEGER),
                    leagueNode: leagueNode,
                };

                if (record.eventId !== '') {
                    eventRowById.set(record.eventId, row);
                }

                rowRecordByRow.set(row, record);

                if (leagueNode) {
                    const bucket = leagueRows.get(leagueNode) || [];
                    bucket.push(record);
                    leagueRows.set(leagueNode, bucket);
                }

                return record;
            });

            leagueRecords = leagueNodes.map(function (league, index) {
                return {
                    league: league,
                    index: index,
                    leagueKey: league.dataset.leagueFilterKey || '',
                    sportKey: league.dataset.sportGroupKey || 'all',
                    rows: leagueRows.get(league) || [],
                };
            });
            leagueRecordByNode = new Map(leagueRecords.map(function (record) {
                return [record.league, record];
            }));

            leagueFilterGroupRecords = leagueFilterGroups.map(function (group, index) {
                return {
                    group: group,
                    index: index,
                    key: group.dataset.leagueFilterGroup || '',
                    sportKey: group.dataset.sportGroupKey || 'all',
                    toggle: group.querySelector('[data-league-filter-group-toggle]'),
                    body: group.querySelector('[data-league-filter-group-body]'),
                    itemCount: group.querySelector('[data-league-filter-group-item-count]'),
                    eventCount: group.querySelector('[data-league-filter-group-event-count]'),
                    buttons: Array.from(group.querySelectorAll('[data-league-filter]')),
                };
            });

            invalidateBoardIndex();
            currentVisibleRowSet = new Set(matchRows.filter(function (row) {
                return !row.hidden;
            }));
            currentVisibleLeagueCountByNode = new Map();
            currentVisibleLeagueSet = new Set();
            leagueRecords.forEach(function (record) {
                const visibleCount = record.rows.reduce(function (total, rowRecord) {
                    return total + (rowRecord.row.hidden ? 0 : 1);
                }, 0);

                currentVisibleLeagueCountByNode.set(record.league, visibleCount);
                if (visibleCount > 0) {
                    currentVisibleLeagueSet.add(record.league);
                }
            });
        }

        function selectionIsActionable() {
            if (selected && selected.acceptance_mode === 'offline_demo') return true;
            return Boolean(selected) && (selected.selectable !== false || selected.contract_required === true);
        }

        function rowNoticeCopy(state) {
            if (state === 'ended') return t('event_ended', 'Karşılaşma sonuçlandı.');
            if (state === 'closed') return t('betting_closed', 'Şu an bahis kapalı');
            if (state === 'suspended') return t('event_suspended', 'Bahis geçici olarak askıda.');
            if (state === 'resolving') return t('odds_resolving', 'Oranlar doğrulanıyor…');
            if (state === 'unavailable') return t('event_unavailable', 'Oranlar şu anda kullanılamıyor.');
            return '';
        }

        function activePhaseForTab() {
            return activePrimaryTab === 'in-game' ? 'in-game' : 'pre-match';
        }

        function timePriorityActiveFor(phaseKey) {
            return activeTimePriorityMode && (phaseKey || activePhaseForTab()) === 'pre-match';
        }

        function interestPriorityActiveFor(phaseKey) {
            return activeInterestPriorityMode && (phaseKey || activePhaseForTab()) === 'pre-match';
        }

        function phaseLabelForTab(tabKey) {
            return tabKey === 'in-game' ? 'In Game' : 'Pre-match';
        }

        function rowMatchesPhase(row, phaseKey) {
            return (row.dataset.eventPhase || 'pre-match') === (phaseKey || activePhaseForTab());
        }

        function rowMatchesSearch(row, searchQuery) {
            const normalizedQuery = normalizeSearchQuery(typeof searchQuery === 'string' ? searchQuery : activeSearchQuery);
            if (normalizedQuery === '') {
                return true;
            }

            const searchText = normalizeSearchQuery(row.dataset.searchText || '');

            return searchText.includes(normalizedQuery);
        }

        function rowMatchesSport(row, sportKey) {
            const normalizedSportKey = typeof sportKey === 'string' ? sportKey : activeSportFilter;

            if (normalizedSportKey === 'upcoming') {
                const startsAt = Number(row.dataset.startsAt || Number.MAX_SAFE_INTEGER);
                const phaseKey = row.dataset.eventPhase || 'pre-match';
                const cutoff = Math.floor(Date.now() / 1000) + upcomingSportWindowSeconds;

                return phaseKey === 'pre-match' && startsAt > 0 && startsAt <= cutoff;
            }

            return !normalizedSportKey || normalizedSportKey === 'all' || (row.dataset.sportKey || '') === normalizedSportKey;
        }

        function rowMatchesLeague(row, leagueKey) {
            const normalizedLeagueKey = typeof leagueKey === 'string' ? leagueKey : activeLeagueFilter;

            return !normalizedLeagueKey || normalizedLeagueKey === 'all' || (row.dataset.leagueFilterKey || '') === normalizedLeagueKey;
        }

        function recordMatchesSport(record, sportKey) {
            const normalizedSportKey = typeof sportKey === 'string' ? sportKey : activeSportFilter;

            if (normalizedSportKey === 'upcoming') {
                const cutoff = Math.floor(Date.now() / 1000) + upcomingSportWindowSeconds;
                return record.phase === 'pre-match' && Number(record.startsAt || 0) <= cutoff;
            }

            return !normalizedSportKey || normalizedSportKey === 'all' || record.sportKey === normalizedSportKey;
        }

        function recordMatchesLeague(record, leagueKey) {
            const normalizedLeagueKey = typeof leagueKey === 'string' ? leagueKey : activeLeagueFilter;

            return !normalizedLeagueKey || normalizedLeagueKey === 'all' || record.leagueKey === normalizedLeagueKey;
        }

        function phaseIndexForSearch(searchQuery) {
            const normalizedQuery = normalizeSearchQuery(typeof searchQuery === 'string' ? searchQuery : activeSearchQuery);

            if (phaseSearchIndexCache.has(normalizedQuery)) {
                return phaseSearchIndexCache.get(normalizedQuery);
            }

            const index = {
                'pre-match': { records: [], bySport: {}, byLeague: {} },
                'in-game': { records: [], bySport: {}, byLeague: {} },
                upcoming: { records: [], bySport: {}, byLeague: {} },
            };

            const upcomingCutoff = Math.floor(Date.now() / 1000) + upcomingSportWindowSeconds;

            rowRecords.forEach(function (record) {
                const phaseKey = record.phase === 'in-game' ? 'in-game' : 'pre-match';

                if (record.phase === 'ended') {
                    return;
                }

                if (normalizedQuery !== '' && !record.searchText.includes(normalizedQuery)) {
                    return;
                }

                const bucket = index[phaseKey];
                bucket.records.push(record);
                bucket.bySport[record.sportKey] = (bucket.bySport[record.sportKey] || 0) + 1;
                bucket.byLeague[record.leagueKey] = (bucket.byLeague[record.leagueKey] || 0) + 1;

                if (record.phase === 'pre-match' && Number(record.startsAt || 0) <= upcomingCutoff) {
                    const upcomingBucket = index.upcoming;
                    upcomingBucket.records.push(record);
                    upcomingBucket.bySport[record.sportKey] = (upcomingBucket.bySport[record.sportKey] || 0) + 1;
                    upcomingBucket.byLeague[record.leagueKey] = (upcomingBucket.byLeague[record.leagueKey] || 0) + 1;
                }
            });

            phaseSearchIndexCache.set(normalizedQuery, index);

            return index;
        }

        function phaseDataFor(tabKey, searchQuery) {
            const phaseKey = tabKey === 'in-game'
                ? 'in-game'
                : (tabKey === 'upcoming' ? 'upcoming' : 'pre-match');
            const phaseData = phaseIndexForSearch(searchQuery)[phaseKey];

            return phaseData || { records: [], bySport: {}, byLeague: {} };
        }

        function rememberFeaturedEventOrder(payload, filters) {
            if ((filters || {}).interest_priority !== 'featured' || (payload.meta || {}).interest_priority !== 'featured') {
                return;
            }
            featuredEventOrder = new Map(((payload.data || {}).shell_events || []).map(function (event, index) {
                return [String(event.event_id), index];
            }));
            filteredViewCache.clear();
        }

        function rankedFeaturedRecords(records) {
            return records.filter(function (record) {
                return record.phase === 'pre-match' && featuredEventOrder.has(String(record.eventId));
            }).sort(function (left, right) {
                return featuredEventOrder.get(String(left.eventId)) - featuredEventOrder.get(String(right.eventId));
            }).slice(0, featuredMatchesDisplayLimit);
        }

        function filteredRowRecords(phaseKey, sportKey, leagueKey, searchQuery) {
            const normalizedPhase = phaseKey === 'in-game' ? 'in-game' : 'pre-match';
            const normalizedSport = typeof sportKey === 'string' ? sportKey : activeSportFilter;
            const normalizedLeague = typeof leagueKey === 'string' ? leagueKey : activeLeagueFilter;
            const normalizedSearch = normalizeSearchQuery(typeof searchQuery === 'string' ? searchQuery : activeSearchQuery);
            const cacheKey = [
                normalizedPhase,
                activeInterestPriorityMode ? 'featured' : (activeTimePriorityMode ? 'time-next' : (normalizedSport || 'all')),
                (activeTimePriorityMode || activeInterestPriorityMode) ? 'all' : (normalizedLeague || 'all'),
                normalizedSearch,
            ].join('|');

            if (filteredViewCache.has(cacheKey)) {
                return filteredViewCache.get(cacheKey);
            }

            let records = phaseDataFor(normalizedPhase, normalizedSearch).records;

            if (interestPriorityActiveFor(normalizedPhase)) {
                records = rankedFeaturedRecords(records);
            } else if (timePriorityActiveFor(normalizedPhase)) {
                const nowTimestamp = Math.floor(Date.now() / 1000);
                records = records
                    .filter(function (record) {
                        return record.phase === 'pre-match'
                            && (offlineDemo.active || Number(record.startsAt || 0) >= nowTimestamp);
                    })
                    .slice()
                    .sort(function (left, right) {
                        const byTime = Number(left.startsAt || Number.MAX_SAFE_INTEGER) - Number(right.startsAt || Number.MAX_SAFE_INTEGER);
                        if (byTime !== 0) {
                            return byTime;
                        }

                        return Number(left.eventId || 0) - Number(right.eventId || 0);
                    })
                    .slice(0, nextMatchesDisplayLimit);
            } else {
                records = records.filter(function (record) {
                    return recordMatchesSport(record, normalizedSport)
                        && recordMatchesLeague(record, normalizedLeague);
                });
            }

            if (normalizedSport === 'upcoming') {
                records = records
                    .slice()
                    .sort(function (left, right) {
                        const byTime = Number(left.startsAt || Number.MAX_SAFE_INTEGER) - Number(right.startsAt || Number.MAX_SAFE_INTEGER);
                        if (byTime !== 0) {
                            return byTime;
                        }

                        return Number(left.eventId || 0) - Number(right.eventId || 0);
                    })
                    .slice(0, upcomingSportDisplayLimit);
            }

            filteredViewCache.set(cacheKey, records);

            return records;
        }

        function contextualRows(phaseKey, sportKey, leagueKey, searchQuery) {
            return filteredRowRecords(phaseKey, sportKey, leagueKey, searchQuery)
                .map(function (record) {
                    return record.row;
                });
        }

        function countsForTab(tabKey) {
            const phaseIndex = phaseIndexForSearch(activeSearchQuery);

            if (activeSportFilter === 'upcoming') {
                const upcomingPhaseData = phaseIndex.upcoming || { records: [], bySport: {}, byLeague: {} };
                const upcomingCount = Math.min(upcomingPhaseData.records.length, upcomingSportDisplayLimit);

                return {
                    total: upcomingCount,
                    bySport: Object.assign({ upcoming: upcomingCount }, upcomingPhaseData.bySport),
                    byLeague: Object.assign({}, upcomingPhaseData.byLeague),
                };
            }

            const phaseData = phaseDataFor(tabKey, activeSearchQuery);

            return {
                total: phaseData.records.length,
                bySport: Object.assign({}, phaseData.bySport),
                byLeague: Object.assign({}, phaseData.byLeague),
            };
        }

        function summaryCountsForTab(tabKey) {
            if (!offerSummaryState || typeof offerSummaryState !== 'object') {
                return null;
            }

            const phaseKey = tabKey === 'in-game' ? 'in-game' : 'pre-match';
            const phaseCountKey = phaseKey === 'in-game' ? 'in_game_count' : 'pre_match_count';
            const sports = Object.entries(offerSummaryState.sports || {}).reduce(function (carry, entry) {
                carry[String(entry[0] || 'other')] = Number((entry[1] || {})[phaseCountKey] || 0);

                return carry;
            }, {});
            const leagues = Object.entries(offerSummaryState.leagues || {}).reduce(function (carry, entry) {
                carry[String(entry[0] || 'unknown_league')] = Number((entry[1] || {})[phaseCountKey] || 0);

                return carry;
            }, {});

            return {
                total: Number(((offerSummaryState.tab_counts || {})[phaseKey]) || 0),
                bySport: sports,
                byLeague: leagues,
            };
        }

        function payloadCountsForTab(tabKey) {
            if (!liveOfferCountsState || typeof liveOfferCountsState !== 'object') {
                return null;
            }

            const phaseKey = tabKey === 'in-game' ? 'in-game' : 'pre-match';
            const sportCounts = Object.entries(liveOfferCountsState.sport_group_counts || {}).reduce(function (carry, entry) {
                const sportKey = String(entry[0] || '');
                const payload = entry[1] || {};

                if (sportKey === '') {
                    return carry;
                }

                carry[sportKey] = Number(
                    phaseKey === 'in-game'
                        ? (payload.in_game_count ?? payload.visible_count ?? 0)
                        : (payload.pre_match_count ?? payload.visible_count ?? 0)
                ) || 0;

                return carry;
            }, {});
            const leagueCounts = Object.entries(liveOfferCountsState.league_counts || {}).reduce(function (carry, entry) {
                const leagueKey = String(entry[0] || '');
                const payload = entry[1] || {};

                if (leagueKey === '') {
                    return carry;
                }

                carry[leagueKey] = Number(
                    phaseKey === 'in-game'
                        ? (payload.in_game_count ?? payload.visible_count ?? 0)
                        : (payload.pre_match_count ?? payload.visible_count ?? 0)
                ) || 0;

                return carry;
            }, {});

            return {
                total: Number(((liveOfferCountsState.phase_counts || {})[phaseKey]) || 0),
                bySport: sportCounts,
                byLeague: leagueCounts,
            };
        }

        function mergeCountMaps(primary, fallback) {
            const merged = Object.assign({}, fallback || {});
            Object.entries(primary || {}).forEach(function (entry) {
                const key = String(entry[0] || '');
                if (key === '') {
                    return;
                }

                merged[key] = Math.max(Number(entry[1] || 0), Number(merged[key] || 0));
            });

            return merged;
        }

        function mergedCountsForTab(tabKey) {
            const rowCounts = countsForTab(tabKey);
            const summaryCounts = summaryCountsForTab(tabKey);
            const payloadCounts = payloadCountsForTab(tabKey);

            if (!summaryCounts && !payloadCounts) {
                return rowCounts;
            }

            const mergedSummaryCounts = summaryCounts || {
                total: 0,
                bySport: {},
                byLeague: {},
            };
            const mergedPayloadCounts = payloadCounts || {
                total: 0,
                bySport: {},
                byLeague: {},
            };

            return {
                total: Math.max(
                    Number(mergedPayloadCounts.total || 0),
                    Number(mergedSummaryCounts.total || 0),
                    Number(rowCounts.total || 0)
                ),
                bySport: mergeCountMaps(mergedPayloadCounts.bySport, mergeCountMaps(mergedSummaryCounts.bySport, rowCounts.bySport)),
                byLeague: mergeCountMaps(mergedPayloadCounts.byLeague, mergeCountMaps(mergedSummaryCounts.byLeague, rowCounts.byLeague)),
            };
        }

        function inventoryCountsForTab(tabKey) {
            const rowCounts = countsForTab(tabKey);
            const summaryCounts = summaryCountsForTab(tabKey);
            const payloadCounts = payloadCountsForTab(tabKey);

            if (!summaryCounts && !payloadCounts) {
                return rowCounts;
            }

            const inventorySummaryCounts = summaryCounts || {
                total: 0,
                bySport: {},
                byLeague: {},
            };
            const inventoryPayloadCounts = payloadCounts || {
                total: 0,
                bySport: {},
                byLeague: {},
            };

            return {
                total: Math.max(
                    Number(inventoryPayloadCounts.total || 0),
                    Number(inventorySummaryCounts.total || 0)
                ),
                bySport: mergeCountMaps(inventoryPayloadCounts.bySport, inventorySummaryCounts.bySport),
                byLeague: mergeCountMaps(inventoryPayloadCounts.byLeague, inventorySummaryCounts.byLeague),
            };
        }

        function countStateIsTransient() {
            return hydrationInFlight || livePollInFlight || !hydrationReady;
        }

        function rememberSportCounts(tabKey, bySport) {
            const phaseKey = tabKey === 'in-game' ? 'in-game' : 'pre-match';
            const store = rememberedSportCountsByTab[phaseKey] || {};

            Object.entries(bySport || {}).forEach(function (entry) {
                const sportKey = String(entry[0] || '');
                const count = Number(entry[1] || 0);

                if (sportKey !== '' && count > 0) {
                    store[sportKey] = count;
                }
            });

            rememberedSportCountsByTab[phaseKey] = store;
        }

        function rememberedSportCount(tabKey, sportKey) {
            const phaseKey = tabKey === 'in-game' ? 'in-game' : 'pre-match';
            const store = rememberedSportCountsByTab[phaseKey] || {};

            return Number(store[sportKey] || 0);
        }

        function visibleSportCount(tabKey, sportKey) {
            if (!sportKey) {
                return 0;
            }

            if (sportKey === 'upcoming') {
                const phaseIndex = phaseIndexForSearch(activeSearchQuery);
                const upcomingPhaseData = phaseIndex.upcoming || { records: [] };

                return Math.min(upcomingPhaseData.records.length, upcomingSportDisplayLimit);
            }

            const phaseData = phaseDataFor(tabKey, activeSearchQuery);
            return Number(phaseData.bySport[sportKey] || 0);
        }

        function ensureActiveSportButtonVisible(options) {
            if (!sportStrip) {
                return;
            }

            const nextOptions = options || {};
            const activeButton = sportFilterButtons.find(function (button) {
                return (button.dataset.sportFilter || 'all') === activeSportFilter && !button.hidden;
            });

            if (!activeButton) {
                syncSportScrollButtons();
                return;
            }

            const stripRect = sportStrip.getBoundingClientRect();
            const buttonRect = activeButton.getBoundingClientRect();
            const padding = window.innerWidth <= 767 ? 14 : 18;
            const isFullyVisible = buttonRect.left >= stripRect.left + padding
                && buttonRect.right <= stripRect.right - padding;

            if (!nextOptions.force && isFullyVisible) {
                syncSportScrollButtons();
                return;
            }

            const behavior = nextOptions.immediate ? 'auto' : 'smooth';

            if (window.innerWidth <= 767) {
                const targetLeft = Math.max(activeButton.offsetLeft - padding, 0);
                sportStrip.scrollTo({
                    left: targetLeft,
                    behavior,
                });
            } else {
                const currentScrollLeft = sportStrip.scrollLeft;
                let targetLeft = currentScrollLeft;

                if (buttonRect.left < stripRect.left + padding) {
                    targetLeft = currentScrollLeft - ((stripRect.left + padding) - buttonRect.left);
                } else if (buttonRect.right > stripRect.right - padding) {
                    targetLeft = currentScrollLeft + (buttonRect.right - (stripRect.right - padding));
                }

                sportStrip.scrollTo({
                    left: Math.max(targetLeft, 0),
                    behavior,
                });
            }

            window.setTimeout(syncSportScrollButtons, behavior === 'smooth' ? 160 : 0);
        }

        function queueActiveSportButtonVisibility(options) {
            if (sportRailSyncFrame !== null) {
                window.cancelAnimationFrame(sportRailSyncFrame);
            }

            sportRailSyncFrame = window.requestAnimationFrame(function () {
                sportRailSyncFrame = null;
                ensureActiveSportButtonVisible(options);
            });
        }

        function tabCountNode(tabKey) {
            const trigger = tabTriggers.find(function (button) {
                return (button.dataset.tab || button.dataset.tabTrigger || 'pre-match') === tabKey;
            });

            return trigger ? trigger.querySelector('[data-tab-count]') : null;
        }

        function setTabBadgeCount(tabKey, count) {
            const safeCount = Math.max(0, Number(count || 0));
            const trigger = tabTriggers.find(function (button) {
                return (button.dataset.tab || button.dataset.tabTrigger || 'pre-match') === tabKey;
            });
            const countNode = tabCountNode(tabKey);
            staticTabCounts.set(tabKey, safeCount);
            deferredTabCounts.delete(tabKey);
            if (trigger) {
                trigger.removeAttribute('data-count-deferred');
            }

            if (countNode) {
                countNode.classList.remove('is-loading');
                countNode.removeAttribute('data-count-loading');
                countNode.removeAttribute('aria-label');
                countNode.dataset.countRaw = String(safeCount);
                countNode.textContent = formatCountLabel(safeCount);
            }
        }
        window.sportsbookShellSetTabBadgeCount = setTabBadgeCount;

        function setSurfaceState(tabKey, state) {
            const panel = tabPanels.find(function (item) { return item.dataset.tabPanel === tabKey; });
            const trigger = tabTriggers.find(function (item) { return (item.dataset.tab || item.dataset.tabTrigger) === tabKey; });
            if (panel) {
                panel.dataset.surfaceState = state;
                panel.setAttribute('aria-busy', state === 'loading' ? 'true' : 'false');
                if (tabKey === 'bet-feed') panel.dataset.feedState = state;
                if (tabKey === 'predictions') panel.dataset.predictionsState = state;
            }
            if (trigger) trigger.dataset.surfaceState = state;
            updateTabCounts();
        }
        window.sportsbookShellSetSurfaceState = setSurfaceState;
        window.sportsbookShellSurfaceText = function (key, fallback) { return t(key, fallback); };

        function clearSportRailLoadingPlaceholder() {
            if (!sportStrip) {
                return;
            }

            const placeholder = sportStrip.querySelector('[data-sport-strip-loading]');
            if (placeholder) {
                placeholder.remove();
            }
        }

        function updateTabCounts() {
            const preMatchCounts = inventoryCountsForTab('pre-match');
            const inGameCounts = inventoryCountsForTab('in-game');

            tabTriggers.forEach(function (button) {
                const tabKey = button.dataset.tab || button.dataset.tabTrigger || 'pre-match';
                const countNode = button.querySelector('[data-tab-count]');
                const count = tabKey === 'in-game'
                    ? inGameCounts.total
                    : (tabKey === 'pre-match' ? preMatchCounts.total : (staticTabCounts.get(tabKey) || 0));

                if (countNode) {
                    if (['pre-match', 'in-game'].includes(tabKey) && (hydrationReady || count > 0)) {
                        deferredTabCounts.delete(tabKey);
                        button.removeAttribute('data-count-deferred');
                    }

                    const countState = sportsbookCountUiState(button.dataset.surfaceState || 'not_requested', !deferredTabCounts.has(tabKey));
                    countNode.setAttribute('aria-busy', countState === 'loading' ? 'true' : 'false');
                    if (countState === 'loading') {
                        countNode.classList.add('is-loading');
                        countNode.dataset.countLoading = '1';
                        countNode.setAttribute('aria-label', t('loading', 'Yükleniyor'));
                        countNode.textContent = '';
                        return;
                    }

                    countNode.classList.remove('is-loading');
                    countNode.removeAttribute('data-count-loading');
                    countNode.removeAttribute('aria-label');
                    countNode.dataset.countRaw = String(count);
                    countNode.textContent = countState === 'count' ? formatCountLabel(count)
                        : (countState === 'passive' ? t('surface_soon', 'Yakında') : (countState === 'unavailable' ? '!' : '—'));
                }
            });
        }

        function queryIncludingRoot(root, selector) {
            const scope = root || document;
            const nodes = [];

            if (scope.matches && scope.matches(selector)) {
                nodes.push(scope);
            }

            if (scope.querySelectorAll) {
                nodes.push(...Array.from(scope.querySelectorAll(selector)));
            }

            return nodes;
        }

        function bindAccordionTriggers(root) {
            queryIncludingRoot(root, '[data-accordion-trigger]').forEach(function (button) {
                if (button.dataset.boundAccordion === '1') {
                    return;
                }

                button.dataset.boundAccordion = '1';
                button.addEventListener('click', function () {
                    const article = button.closest('.sb2-league');
                    if (!article) {
                        return;
                    }

                    const nextExpanded = !article.classList.contains('is-open');
                    setLeagueExpanded(article, nextExpanded);
                    queueLeagueWindowRefresh();
                });
            });
        }

        function bindLeagueFilterGroupToggles(root) {
            queryIncludingRoot(root, '[data-league-filter-group-toggle]').forEach(function (button) {
                if (button.dataset.boundGroupToggle === '1') {
                    return;
                }

                button.dataset.boundGroupToggle = '1';
                button.addEventListener('click', function () {
                    const group = button.closest('[data-league-filter-group]');
                    const nextExpanded = button.getAttribute('aria-expanded') !== 'true';
                    if (!group) {
                        return;
                    }

                    group.dataset.userExpanded = nextExpanded ? '1' : '0';
                    setLeagueFilterGroupExpanded(group, nextExpanded);
                });
            });
        }

        function setLeagueExpanded(league, expanded) {
            if (!league) {
                return;
            }

            const header = league.querySelector('[data-accordion-trigger]');
            const body = league.querySelector('.sb2-league-body');
            const placeholder = league.querySelector('[data-league-placeholder]');
            const nextExpanded = Boolean(expanded);

            if (!nextExpanded && body && !body.hidden) {
                cacheLeagueBodyHeight(league);
            }

            league.classList.toggle('is-open', nextExpanded);

            if (header) {
                header.setAttribute('aria-expanded', nextExpanded ? 'true' : 'false');
            }

            if (body) {
                body.hidden = !nextExpanded || league.dataset.windowed === '1';
            }

            if (placeholder) {
                placeholder.hidden = !nextExpanded || league.dataset.windowed !== '1';
            }
        }

        function setLeagueFilterGroupExpanded(group, expanded) {
            if (!group) {
                return;
            }

            const body = group.querySelector('[data-league-filter-group-body]');
            const toggle = group.querySelector('[data-league-filter-group-toggle]');
            group.classList.toggle('is-open', Boolean(expanded));

            if (body) {
                body.hidden = !expanded;
            }

            if (toggle) {
                toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            }
        }

        function setCompetitionRailCollapsed(collapsed) {
            competitionRailCollapsed = Boolean(collapsed);

            if (layout) {
                layout.classList.toggle('is-competition-rail-collapsed', competitionRailCollapsed);
            }

            if (competitionRailBody) {
                competitionRailBody.hidden = competitionRailCollapsed;
            }

            if (competitionRailToggleButton) {
                competitionRailToggleButton.setAttribute('aria-expanded', competitionRailCollapsed ? 'false' : 'true');
            }
        }

        function updateCompetitionRailMeta() {
            const visibleLeagueButtons = leagueFilterButtons.filter(function (button) {
                return !button.hidden;
            });
            const visibleRows = filteredRowRecords(activePhaseForTab(), activeSportFilter, activeLeagueFilter, activeSearchQuery);
            const pagination = currentLiveOfferPaginationState();
            const sameGeneration = pagination && pagination.snapshotVersion !== ''
                && pagination.snapshotVersion === liveOfferSnapshotVersion;
            const scopedTotal = sameGeneration && pagination.visibleTotalAuthoritative && activeSportFilter !== 'upcoming'
                ? pagination.visibleTotal : null;
            const countPresentation = sportsbookScopedCountPresentation(visibleRows.length, scopedTotal);

            if (competitionRailCount) {
                const count = visibleLeagueButtons.length || leagueFilterButtons.length || 0;
                competitionRailCount.dataset.countRaw = String(count);
                competitionRailCount.textContent = formatCountLabel(count) + ' ' + t('tournaments', 'turnuva');
            }

            if (competitionRailActiveCount) {
                competitionRailActiveCount.dataset.countState = countPresentation.state;
                competitionRailActiveCount.textContent = layout && layout.classList.contains('is-scope-updating')
                    ? t('events_updating', 'Etkinlikler güncelleniyor')
                    : countPresentation.state === 'inconsistent'
                        ? t('event_count_inconsistent', 'Etkinlik sayısı eşleşmiyor')
                        : t(countPresentation.state === 'total' ? 'event_count' : 'events_shown',
                            countPresentation.state === 'total' ? ':count etkinlik' : ':count gösteriliyor', {
                                count: formatCountLabel(countPresentation.count),
                            });
            }

            if (boardStatusMeter) {
                boardStatusMeter.textContent = formatCountLabel(visibleLeagueButtons.length || 0) + ' ' + t('tournaments', 'turnuva');
            }
        }

        function updateLeagueFilterGroups() {
            leagueFilterGroupRecords.forEach(function (record) {
                const visibleButtons = record.buttons.filter(function (button) {
                    return !button.hidden;
                });
                const visibleEventCount = visibleButtons.reduce(function (total, button) {
                    const countNode = button.querySelector('strong');

                    return total + Number(countNode ? countNode.dataset.countRaw || 0 : 0);
                }, 0);
                const hasActiveLeague = visibleButtons.some(function (button) {
                    return (button.dataset.leagueFilter || '') === activeLeagueFilter;
                });
                const hasSavedPreference = record.group.dataset.userExpanded === '1' || record.group.dataset.userExpanded === '0';
                const nextExpanded = hasSavedPreference
                    ? record.group.dataset.userExpanded === '1'
                    : (
                        hasActiveLeague
                        || (activeSearchQuery !== '' && visibleButtons.length > 0)
                        || (activeLeagueFilter === 'all' && activeSearchQuery === '' && record.index === 0)
                    );

                record.group.hidden = visibleButtons.length === 0;

                if (record.itemCount) {
                    record.itemCount.dataset.countRaw = String(visibleEventCount);
                    record.itemCount.textContent = formatCountLabel(visibleEventCount);
                }

                if (record.eventCount) {
                    record.eventCount.dataset.countRaw = String(visibleEventCount);
                    record.eventCount.textContent = t('events', 'etkinlik');
                }

                const copyMeta = record.group.querySelector('.sb2-tournament-group-copy span');
                if (copyMeta) {
                    copyMeta.dataset.countRaw = String(visibleButtons.length);
                    copyMeta.textContent = formatCountLabel(visibleButtons.length) + ' ' + t('tournaments', 'turnuva');
                }

                if (!record.group.hidden) {
                    setLeagueFilterGroupExpanded(record.group, nextExpanded);
                }
            });
        }

        function rebalanceLeagueOpenState() {
            const visibleLeagues = leagueRecords.filter(function (record) {
                return !record.league.hidden;
            });

            if (!visibleLeagues.length) {
                return;
            }

            let openBudget = activeLeagueFilter !== 'all' ? 1 : 6;

            visibleLeagues.forEach(function (record, index) {
                const league = record.league;
                const matchingFilter = activeLeagueFilter !== 'all'
                    ? (league.dataset.leagueFilterKey || '') === activeLeagueFilter
                    : false;
                const hasVisibleRow = record.rows.some(function (rowRecord) {
                    return currentVisibleRowSet.has(rowRecord.row);
                });
                const shouldOpen = matchingFilter || hasVisibleRow || (activeLeagueFilter === 'all' && index < openBudget);

                setLeagueExpanded(league, shouldOpen);
            });

            queueLeagueWindowRefresh();
        }

        function updateAverageMatchRowHeight() {
            const sample = matchRows.find(function (row) {
                return !row.hidden && row.offsetHeight > 0;
            }) || matchRows.find(function (row) {
                return row.offsetHeight > 0;
            });

            if (!sample) {
                return;
            }

            const nextHeight = Math.round(sample.getBoundingClientRect().height || sample.offsetHeight || 0);
            if (nextHeight > 80) {
                averageMatchRowHeight = nextHeight;
            }
        }

        function visibleRowCountForLeague(league) {
            const record = leagueRecordByNode.get(league);
            if (!record) {
                return 0;
            }

            return record.rows.reduce(function (total, rowRecord) {
                return total + (rowRecord.row.hidden ? 0 : 1);
            }, 0);
        }

        function estimatedLeagueBodyHeight(league) {
            const cachedHeight = Number(league.dataset.windowBodyHeight || 0);
            const visibleRowCount = visibleRowCountForLeague(league);
            const estimatedHeight = visibleRowCount > 0
                ? Math.round(visibleRowCount * averageMatchRowHeight)
                : 0;

            return Math.max(cachedHeight, estimatedHeight, visibleRowCount > 0 ? leagueWindowMinimumPlaceholderPx : 0);
        }

        function cacheLeagueBodyHeight(league) {
            if (!league) {
                return 0;
            }

            const body = league.querySelector('.sb2-league-body');
            if (!body) {
                return estimatedLeagueBodyHeight(league);
            }

            const measuredHeight = Math.max(
                Math.round(body.scrollHeight || 0),
                Math.round(body.getBoundingClientRect().height || 0),
                estimatedLeagueBodyHeight(league)
            );

            if (measuredHeight > 0) {
                league.dataset.windowBodyHeight = String(measuredHeight);
            }

            return measuredHeight;
        }

        function setLeagueWindowed(league, windowed) {
            if (!league) {
                return;
            }

            const body = league.querySelector('.sb2-league-body');
            const placeholder = league.querySelector('[data-league-placeholder]');
            if (!body || !placeholder) {
                return;
            }

            const nextWindowed = Boolean(windowed) && league.classList.contains('is-open') && !league.hidden;
            const currentlyWindowed = league.dataset.windowed === '1';

            if (nextWindowed) {
                const bodyHeight = cacheLeagueBodyHeight(league) || estimatedLeagueBodyHeight(league);
                const placeholderHeight = Math.max(bodyHeight, leagueWindowMinimumPlaceholderPx);
                placeholder.style.height = placeholderHeight > 0 ? placeholderHeight + 'px' : '';
                placeholder.hidden = false;
                body.hidden = true;
                league.dataset.windowed = '1';
                league.classList.add('is-windowed');
                return;
            }

            if (!currentlyWindowed) {
                body.hidden = !league.classList.contains('is-open') || league.hidden;
                placeholder.hidden = true;
                placeholder.style.height = '';
                league.classList.remove('is-windowed');
                delete league.dataset.windowed;
                return;
            }

            placeholder.hidden = true;
            placeholder.style.height = '';
            body.hidden = !league.classList.contains('is-open') || league.hidden;
            league.classList.remove('is-windowed');
            delete league.dataset.windowed;
        }

        function shouldWindowLeague(league) {
            if (!league || league.hidden || !league.classList.contains('is-open')) {
                return false;
            }

            if (activeLeagueFilter !== 'all' || activeSearchQuery !== '') {
                return false;
            }

            const rect = league.getBoundingClientRect();
            return rect.bottom < -leagueWindowBufferPx || rect.top > (window.innerHeight + leagueWindowBufferPx);
        }

        function applyLeagueWindowing() {
            leagueWindowRefreshFrame = null;
            updateAverageMatchRowHeight();

            leagueRecords.forEach(function (record) {
                const league = record.league;
                if (!league || league.hidden) {
                    setLeagueWindowed(league, false);
                    return;
                }

                setLeagueWindowed(league, shouldWindowLeague(league));
            });
        }

        function queueLeagueWindowRefresh() {
            if (leagueWindowRefreshFrame !== null) {
                return;
            }

            leagueWindowRefreshFrame = window.requestAnimationFrame(function () {
                applyLeagueWindowing();
            });
        }

        function finishBoardRefreshingIfIdle() {
            if (rowVisibilityBatchActive
                || hydrationInFlight
                || (livePollInFlight && !hydrationReady)
                || (interactiveLiveRefreshTimer !== null && !hydrationReady)) {
                return;
            }

            setBoardRefreshing(false);
        }

        function rowBatchingEnabledFor(records) {
            return activeLeagueFilter === 'all'
                && activeSearchQuery === ''
                && records.length > visibleRowLimit;
        }

        function rowBatchPriority(record, index) {
            const league = record.leagueNode;
            const isSelected = selected && String(selected.event_id || '') === record.eventId;
            const isOpen = league ? league.classList.contains('is-open') : false;
            const isWindowed = league ? league.dataset.windowed === '1' : false;
            const rect = league && !isWindowed ? league.getBoundingClientRect() : null;
            const nearViewport = rect ? (rect.bottom >= -180 && rect.top <= window.innerHeight + 320) : false;

            return index
                - (isSelected ? 10000 : 0)
                - (nearViewport ? 1200 : 0)
                - (isOpen ? 260 : 0)
                + (isWindowed ? 2400 : 0);
        }

        function rowBatchPlan(rowRecords) {
            if (!rowBatchingEnabledFor(rowRecords)) {
                return {
                    immediateRows: new Set(rowRecords.map(function (record) {
                        return record.row;
                    })),
                    deferredRows: [],
                };
            }

            const prioritized = rowRecords
                .map(function (record, index) {
                    return {
                        record: record,
                        score: rowBatchPriority(record, index),
                    };
                })
                .sort(function (left, right) {
                    return left.score - right.score;
                });
            const immediateRows = new Set();
            const deferredRows = [];
            const representedLeagues = new Set();

            prioritized.forEach(function (entry) {
                const record = entry.record;
                const league = record.leagueNode;
                const shouldSeedLeague = league && !representedLeagues.has(league);

                if (shouldSeedLeague || immediateRows.size < visibleRowLimit) {
                    immediateRows.add(record.row);
                    if (league) {
                        representedLeagues.add(league);
                    }
                    return;
                }

                deferredRows.push(record.row);
            });

            return {
                immediateRows: immediateRows,
                deferredRows: deferredRows,
            };
        }

        function visibleLeagueCountsForRows(rows) {
            const counts = new Map();

            rows.forEach(function (row) {
                const record = rowRecordByRow.get(row);
                if (!record || !record.leagueNode) {
                    return;
                }

                counts.set(record.leagueNode, (counts.get(record.leagueNode) || 0) + 1);
            });

            return counts;
        }

        function applyVisibleLeagueState(visibleLeagueCountByNode) {
            leagueRecords.forEach(function (record) {
                const nextCount = visibleLeagueCountByNode.get(record.league) || 0;
                const shouldHide = nextCount <= 0;

                if (record.league.hidden !== shouldHide) {
                    record.league.hidden = shouldHide;
                }
            });

            currentVisibleLeagueCountByNode = new Map(visibleLeagueCountByNode);
            currentVisibleLeagueSet = new Set(Array.from(visibleLeagueCountByNode.keys()).filter(function (league) {
                return (visibleLeagueCountByNode.get(league) || 0) > 0;
            }));
            queueLeagueWindowRefresh();
        }

        function applyVisibleRowState(nextVisibleRows) {
            currentVisibleRowSet.forEach(function (row) {
                if (!nextVisibleRows.has(row) && !row.hidden) {
                    row.hidden = true;
                }
            });

            nextVisibleRows.forEach(function (row) {
                if (row.hidden) {
                    row.hidden = false;
                }
            });

            currentVisibleRowSet = new Set(nextVisibleRows);
        }

        function queueDeferredRowReveal(rows) {
            rowVisibilityBatchToken += 1;
            const batchToken = rowVisibilityBatchToken;

            if (rowVisibilityBatchFrame !== null) {
                window.cancelAnimationFrame(rowVisibilityBatchFrame);
                rowVisibilityBatchFrame = null;
            }

            if (!rows.length) {
                rowVisibilityBatchActive = false;
                finishBoardRefreshingIfIdle();
                return;
            }

            const queue = rows.slice();
            rowVisibilityBatchActive = true;

            if (boardStatusText && layout && layout.dataset.boardRefreshing === '1') {
                boardStatusText.textContent = t('events_appearing', 'Etkinlikler kademeli olarak yerleşiyor.');
            }

            const drain = function () {
                if (batchToken !== rowVisibilityBatchToken) {
                    return;
                }

                const batch = queue.splice(0, rowVisibilityBatchSize);
                batch.forEach(function (row) {
                    if (row.hidden) {
                        row.hidden = false;
                    }

                    currentVisibleRowSet.add(row);
                    const record = rowRecordByRow.get(row);
                    if (!record || !record.leagueNode) {
                        return;
                    }

                    const nextCount = (currentVisibleLeagueCountByNode.get(record.leagueNode) || 0) + 1;
                    currentVisibleLeagueCountByNode.set(record.leagueNode, nextCount);
                    currentVisibleLeagueSet.add(record.leagueNode);
                    if (record.leagueNode.hidden) {
                        record.leagueNode.hidden = false;
                    }
                });

                queueLeagueWindowRefresh();

                if (queue.length) {
                    rowVisibilityBatchFrame = window.requestAnimationFrame(drain);
                    return;
                }

                rowVisibilityBatchFrame = null;
                rowVisibilityBatchActive = false;
                updateLeagueVisibility();
                rebalanceLeagueOpenState();
                queueLeagueWindowRefresh();
                finishBoardRefreshingIfIdle();
            };

            rowVisibilityBatchFrame = window.requestAnimationFrame(drain);
        }

        function cancelDeferredRowReveal() {
            rowVisibilityBatchToken += 1;
            rowVisibilityBatchActive = false;

            if (rowVisibilityBatchFrame !== null) {
                window.cancelAnimationFrame(rowVisibilityBatchFrame);
                rowVisibilityBatchFrame = null;
            }
        }

        function livePatchPriorityForRow(row, index) {
            const eventId = String(row.dataset.eventId || '');
            const league = row.closest('.sb2-league');
            const isSelected = selected && String(selected.event_id || '') === eventId;
            const isWindowed = league ? league.dataset.windowed === '1' : false;
            const isOpen = league ? league.classList.contains('is-open') : false;
            const rect = league && !isWindowed ? league.getBoundingClientRect() : null;
            const nearViewport = rect ? (rect.bottom >= -220 && rect.top <= window.innerHeight + 380) : false;

            return index
                - (isSelected ? 10000 : 0)
                - (nearViewport ? 1200 : 0)
                - (isOpen ? 240 : 0)
                + (isWindowed ? 2000 : 0);
        }

        function drainLivePatchJobs(jobs) {
            livePatchToken += 1;
            const patchToken = livePatchToken;

            if (livePatchFrame !== null) {
                window.cancelAnimationFrame(livePatchFrame);
                livePatchFrame = null;
            }

            if (!jobs.length) {
                return Promise.resolve();
            }

            const queue = jobs
                .slice()
                .sort(function (left, right) {
                    return left.priority - right.priority;
                });

            return new Promise(function (resolve) {
                const drain = function () {
                    if (patchToken !== livePatchToken) {
                        resolve();
                        return;
                    }

                    const batch = queue.splice(0, livePatchBatchSize);
                    batch.forEach(function (job) {
                        job.run();
                    });

                    if (queue.length) {
                        livePatchFrame = window.requestAnimationFrame(drain);
                        return;
                    }

                    livePatchFrame = null;
                    resolve();
                };

                livePatchFrame = window.requestAnimationFrame(drain);
            });
        }

        function setHydrationUi(state, detail, meta) {
            const nextState = state || 'idle';
            const progressCurrent = Number(meta && meta.current ? meta.current : 0);
            const progressTotal = Math.max(Number(meta && meta.total ? meta.total : 0), 0);
            const progressPercent = progressTotal > 0
                ? Math.max(6, Math.min(100, Math.round((progressCurrent / progressTotal) * 100)))
                : 0;

            if (layout) {
                layout.dataset.hydrationState = nextState;
                layout.dataset.boardState = providerDegradedState ? 'degraded'
                    : (nextState === 'idle' ? (hydrationReady ? 'ready' : 'not_requested') : nextState);
                layout.dataset.boardPlaceholder = String(!hydrationReady && currentVisibleRowSet.size === 0 && visibleOfferRowCount() === 0);
                if (['pre-match', 'in-game'].includes(activePrimaryTab)) {
                    setSurfaceState(activePrimaryTab, layout.dataset.boardState);
                }
            }

            if (hydrationBadge) {
                hydrationBadge.textContent = ({
                    idle: t('live_list', 'Canlı liste'),
                    loading: t('loading', 'Yükleniyor'),
                    ready: t('live_list', 'Canlı liste'),
                    stale: 'Stale',
                })[nextState] || t('live_list', 'Canlı liste');
            }

            if (hydrationText) {
                hydrationText.textContent = detail || '';
            }

            if (boardStatusTitle) {
                boardStatusTitle.textContent = '';
            }

            if (boardStatusText) {
                boardStatusText.textContent = '';
            }

            if (hydrationProgress) {
                hydrationProgress.hidden = nextState !== 'loading';
            }

            if (hydrationProgressBar) {
                hydrationProgressBar.style.width = progressPercent + '%';
            }

            if (hydrationLoadingWatchdogTimer !== null) {
                window.clearTimeout(hydrationLoadingWatchdogTimer);
                hydrationLoadingWatchdogTimer = null;
            }

            if (nextState === 'loading') {
                const loadingContextKey = liveHydrationContextKey;
                hydrationLoadingWatchdogTimer = window.setTimeout(function () {
                    hydrationLoadingWatchdogTimer = null;
                    if (layout
                        && layout.dataset.hydrationState === 'loading'
                        && loadingContextKey === liveHydrationContextKey) {
                        settleHydrationUiForCurrentContext(currentLiveOfferFilters(), '');
                        finishBoardRefreshingIfIdle();
                    }
                }, hydrationLoadingWatchdogMs);
            }

            if (nextState === 'loading' && !hydrationReady && currentVisibleRowSet.size === 0 && visibleOfferRowCount() === 0) {
                setBoardSkeletonVisible(true);
            } else if (nextState !== 'loading') {
                setBoardSkeletonVisible(false);
            }
        }

        function setInitialHydrationState(state) {
            if (!layout || !initialPayloadDeferred) {
                return;
            }

            const nextState = ['pending', 'ready', 'failed', 'aborted'].includes(state) ? state : 'pending';
            layout.dataset.initialHydrationState = nextState;

            if (nextState === 'aborted') {
                setBoardSkeletonVisible(false);
                setInitialOffersEmptyStateVisible(false);
                return;
            }

            if (nextState === 'ready') {
                setBoardSkeletonVisible(false);
                setInitialOffersEmptyStateVisible(false);
                if (window.performance && typeof window.performance.mark === 'function') {
                    window.performance.mark('sportsbook-initial-hydration-ready');
                }
                markFirstUsableHydration();
                notifyInitialHydrationRecovery();
                return;
            }

            if (nextState === 'failed') {
                setBoardSkeletonVisible(false);
                if (initialOffersEmptyState) {
                    const title = initialOffersEmptyState.querySelector('h2');
                    const body = initialOffersEmptyState.querySelector('p');
                    if (title) {
                        title.textContent = t('live_refresh_failed_title', 'Liste yenilenemedi');
                    }
                    if (body) {
                        body.textContent = t('live_refresh_failed_body', 'Güncel oranlar alınamadı. Tekrar deneyebilirsin.');
                    }
                    setInitialOffersEmptyStateVisible(true);
                }
                return;
            }

            setBoardSkeletonVisible(true);
            setInitialOffersEmptyStateVisible(false);
            hideActiveFilterChip();
        }

        function firstUsableHydrationReady() {
            if (!initialPayloadDeferred || hydrationReady) {
                return Promise.resolve();
            }

            if (!firstUsableHydrationPromise) {
                firstUsableHydrationPromise = new Promise(function (resolve) {
                    resolveFirstUsableHydration = resolve;
                });
            }

            return firstUsableHydrationPromise;
        }

        function markFirstUsableHydration() {
            if (resolveFirstUsableHydration) {
                resolveFirstUsableHydration();
                resolveFirstUsableHydration = null;
            }
        }

        function notifyInitialHydrationRecovery() {
            if (initialHydrationRecoveryNotified) {
                return;
            }

            initialHydrationRecoveryNotified = true;
            window.__sportsbookInitialHydrationReady = true;
            document.dispatchEvent(new CustomEvent('sportsbook-shell-initial-hydration-ready'));
        }

        window.sportsbookShellFirstUsableHydrationReady = firstUsableHydrationReady;
        window.sportsbookShellAfterFirstUsableHydration = function (callback) {
            if (typeof callback !== 'function') {
                return firstUsableHydrationReady();
            }

            return firstUsableHydrationReady().then(callback);
        };

        window.sportsbookShellAfterTabPrewarm = function (callback) {
            const waitForPrewarm = window.sportsbookShellPrewarmState && window.sportsbookShellPrewarmState.completed
                ? Promise.resolve()
                : new Promise(function (resolve) {
                    document.addEventListener('sportsbook-shell-prewarm-completed', function () {
                        resolve();
                    }, { once: true });
                });

            if (typeof callback !== 'function') {
                return waitForPrewarm;
            }

            return waitForPrewarm.then(callback);
        };

        function currentLiveOfferFilters() {
            const filters = {
                phase_key: timePriorityActiveFor('pre-match') ? 'pre-match' : activePhaseForTab(),
            };

            if (interestPriorityActiveFor(filters.phase_key)) {
                filters.interest_priority = 'featured';
            } else if (timePriorityActiveFor(filters.phase_key)) {
                filters.time_priority = 'next';
            } else if (activeSportFilter !== 'all' && activeSportFilter !== 'upcoming') {
                filters.sport_group_key = activeSportFilter;
            }

            if (!timePriorityActiveFor(filters.phase_key) && !interestPriorityActiveFor(filters.phase_key) && activeLeagueFilter !== 'all') {
                filters.league_filter_key = activeLeagueFilter;
            }

            if (activeSearchQuery !== '') {
                filters.search = activeSearchQuery;
            }

            return filters;
        }

        function liveOfferCacheKey(filters) {
            const nextFilters = filters || currentLiveOfferFilters();
            // Passive first paint must never satisfy a hydrated cache lookup.
            if (nextFilters.defer_contracts) {
                return '';
            }
            const phaseKey = nextFilters.phase_key || activePhaseForTab();
            const sportKey = nextFilters.sport_group_key || '';
            const leagueKey = nextFilters.league_filter_key || '';
            const surfaceKey = nextFilters.surface || '';

            if (nextFilters.time_priority === 'next') {
                return phaseKey + '|time-next';
            }

            if (nextFilters.interest_priority === 'featured') {
                return phaseKey + '|featured|' + (surfaceKey === 'featured-slice' ? 'passive' : 'hydrated');
            }

            if (!sportKey || normalizeSearchQuery(nextFilters.search || '') !== '') {
                return '';
            }

            if (leagueKey) {
                return phaseKey + '|' + sportKey + '|league:' + leagueKey + (surfaceKey ? '|' + surfaceKey : '');
            }

            return phaseKey + '|' + sportKey + (surfaceKey ? '|' + surfaceKey : '');
        }

        function cachedLiveOfferPayload(filters, options) {
            if (offlineDemo.active) return null;
            const key = liveOfferCacheKey(filters);
            const entry = key ? liveOfferPayloadCache.get(key) : null;
            const allowStalePreparedSlice = !options || options.allowStalePreparedSlice !== false;

            if (!entry) {
                return null;
            }

            if ((Date.now() - entry.storedAt) > liveOfferCacheTtlMs) {
                liveOfferPayloadCache.delete(key);
                return null;
            }

            if (liveOfferSnapshotVersion && entry.snapshotVersion && entry.snapshotVersion !== liveOfferSnapshotVersion) {
                if ((!entry.sportSliceVersion && !entry.leagueSliceVersion) || !allowStalePreparedSlice) {
                    if (!entry.sportSliceVersion && !entry.leagueSliceVersion) {
                        liveOfferPayloadCache.delete(key);
                    }
                    return null;
                }
            }

            return entry.payload || null;
        }

        function rememberLiveOfferPayload(filters, payload) {
            if (payload && payload.meta && payload.meta.offline_demo && payload.meta.offline_demo.active) return;
            const key = liveOfferCacheKey(filters);
            const events = Array.isArray((((payload || {}).data || {}).events))
                ? payload.data.events
                : [];
            const shellEvents = Array.isArray((((payload || {}).data || {}).shell_events))
                ? payload.data.shell_events
                : [];

            const meta = ((payload || {}).meta && typeof payload.meta === 'object') ? payload.meta : {};
            const nextSnapshotVersion = String(meta.snapshot_version || ((meta.read_meta || {}).inventory_generation) || '');
            const nextSportSliceVersion = String(meta.sport_slice_version || '');
            const nextLeagueSliceVersion = String(meta.league_slice_version || '');
            if (nextSnapshotVersion && liveOfferSnapshotVersion && nextSnapshotVersion !== liveOfferSnapshotVersion) {
                liveOfferPayloadCache.forEach(function (entry, cacheKey) {
                    if (!entry || (!entry.sportSliceVersion && !entry.leagueSliceVersion)) {
                        liveOfferPayloadCache.delete(cacheKey);
                    }
                });
                liveOfferPaginationState.clear();
                expandedEventDetailCache.clear();
            }
            if (nextSnapshotVersion) {
                liveOfferSnapshotVersion = nextSnapshotVersion;
            }

            if (!filters?.event_id && !filters?.event_ids && !filters?.eventId) {
                rememberLiveOfferPagination(filters, payload, shellEvents.length);
            }

            if (!key || !payload) {
                return;
            }

            liveOfferPayloadCache.set(key, {
                payload: payload,
                storedAt: Date.now(),
                snapshotVersion: nextSnapshotVersion,
                sportSliceVersion: nextSportSliceVersion,
                leagueSliceVersion: nextLeagueSliceVersion,
            });
        }

        function rememberLiveOfferPagination(filters, payload, fallbackReturnedCount) {
            const key = buildLiveHydrationContextKey(filters || currentLiveOfferFilters());
            const meta = ((payload || {}).meta && typeof payload.meta === 'object') ? payload.meta : {};
            const pagination = (meta.pagination && typeof meta.pagination === 'object') ? meta.pagination : {};
            const rawVisibleTotal = pagination.visible_total ?? meta.visible_total;
            const visibleTotalAuthoritative = pagination.visible_total_authoritative === true
                || meta.visible_total_authoritative === true;
            const visibleTotal = rawVisibleTotal === undefined || rawVisibleTotal === null
                ? null : Number(rawVisibleTotal);
            const returnedCount = Number(pagination.returned_count ?? meta.returned_count ?? meta.offer_count ?? fallbackReturnedCount ?? 0);
            const nextCursor = String(pagination.next_cursor || meta.next_cursor || '');
            const hasMore = Boolean(pagination.has_more ?? meta.has_more) && nextCursor !== '';

            if (!key) {
                return;
            }

            liveOfferPaginationState.set(key, {
                hasMore: hasMore,
                nextCursor: nextCursor,
                returnedCount: Number.isFinite(returnedCount) ? Math.max(returnedCount, 0) : 0,
                visibleTotal: Number.isInteger(visibleTotal) && visibleTotal >= 0 ? visibleTotal : null,
                visibleTotalAuthoritative: visibleTotalAuthoritative,
                snapshotVersion: String(meta.snapshot_version || ((meta.read_meta || {}).inventory_generation) || ''),
                storedAt: Date.now(),
            });
        }

        function currentLiveOfferPaginationState() {
            const key = buildLiveHydrationContextKey(currentLiveOfferFilters());
            const state = key ? liveOfferPaginationState.get(key) : null;

            if (!state) {
                return null;
            }

            if ((Date.now() - state.storedAt) > liveOfferCacheTtlMs) {
                liveOfferPaginationState.delete(key);
                return null;
            }

            return state;
        }

        function eventPayloadFromShellEvent(shellEvent) {
            if (!shellEvent) {
                return null;
            }

            return {
                event_id: shellEvent.event_id,
                canonical_name: shellEvent.event_name || '',
                participant_home: shellEvent.home || '',
                participant_away: shellEvent.away || '',
                starts_at: shellEvent.starts_at_iso || '',
                starts_at_timestamp: shellEvent.starts_at_timestamp || 0,
                lifecycle_status: shellEvent.lifecycle_status || '',
                trading_status: shellEvent.trading_status || '',
                front_betting_enabled: shellEvent.front_betting_enabled !== false,
                quote: {
                    is_tradeable: shellEvent.is_tradeable !== false,
                    markets: [],
                },
            };
        }

        function shellSelectionState(selection, payloadFetchedAt) {
            if (!selection) {
                return null;
            }

            const selectable = selection.selectable !== false;
            const offerStatus = selection.offer_status || 'open';

            return {
                odd: selection.raw_odd ?? selection.odd,
                quoteSelectionId: selection.quote_selection_id ?? null,
                selectionToken: adapterSelectionToken(selection),
                acceptanceMode: selection.acceptance_mode || 'legacy_quote_selection',
                quoteVersionId: selection.quote_version_id || null,
                outcomeName: selection.outcome_name || selection.label || '',
                marketLine: selection.market_line ?? null,
                winxEdgePct: normalizedWinxEdgePct(selection),
                payloadFetchedAt: payloadFetchedAt,
                marketStatus: 'open',
                offerStatus: offerStatus,
                bettable: selectable,
                bettableReason: selection.bettable_reason || '',
                selectionStateMessage: selection.selection_state_message || '',
            };
        }

        function addShellSelectionGroup(selectionIndex, selections, payloadFetchedAt) {
            if (!Array.isArray(selections)) {
                return;
            }

            selections.forEach(function (selection) {
                const marketType = selection.market_type || '';
                const outcomeCode = selection.outcome_code || '';
                if (!marketType || !outcomeCode) {
                    return;
                }

                selectionIndex.set([
                    marketType,
                    selection.market_specifier_key || 'default',
                    outcomeCode,
                ].join('::'), shellSelectionState(selection, payloadFetchedAt));
            });
        }

        function rememberExpandedShellEvent(shellEvent) {
            const eventId = String((shellEvent || {}).event_id || '');
            const extraMarkets = Array.isArray((shellEvent || {}).extra_markets)
                ? shellEvent.extra_markets
                : [];

            if (!eventId || !extraMarkets.length) {
                return;
            }

            expandedEventDetailCache.set(eventId, {
                shellEvent: shellEvent,
                storedAt: Date.now(),
            });
        }

        function rememberExpandedDetailsFromPayload(payload) {
            const shellEvents = Array.isArray((((payload || {}).data || {}).shell_events))
                ? payload.data.shell_events
                : [];

            shellEvents.forEach(rememberExpandedShellEvent);
        }

        function cachedExpandedShellEvent(eventId) {
            const key = String(eventId || '');
            const entry = key ? expandedEventDetailCache.get(key) : null;

            if (!entry) {
                return null;
            }

            if ((Date.now() - entry.storedAt) > liveOfferCacheTtlMs) {
                expandedEventDetailCache.delete(key);
                return null;
            }

            return entry.shellEvent || null;
        }

        function shellEventFromPayload(payload, eventId) {
            const key = String(eventId || '');
            const shellEvents = Array.isArray((((payload || {}).data || {}).shell_events))
                ? payload.data.shell_events
                : [];

            return shellEvents.find(function (shellEvent) {
                return String(shellEvent.event_id || '') === key;
            }) || null;
        }

        function liveOfferFiltersForEventRow(row) {
            const filters = {
                phase_key: (row && row.dataset.eventPhase) || activePhaseForTab(),
            };
            const sportKey = row && row.dataset.sportKey ? row.dataset.sportKey : activeSportFilter;

            if (sportKey && sportKey !== 'all' && sportKey !== 'upcoming') {
                filters.sport_group_key = sportKey;
            }

            return filters;
        }

        async function resolveExpandedShellEvent(row) {
            const eventId = String((row || {}).dataset ? (row.dataset.eventId || '') : '');
            if (!eventId) {
                return null;
            }

            const cachedEvent = cachedExpandedShellEvent(eventId);
            if (cachedEvent) {
                return cachedEvent;
            }

            const filters = liveOfferFiltersForEventRow(row);
            const cachedPayload = cachedLiveOfferPayload(filters);
            const cachedShellEvent = shellEventFromPayload(cachedPayload, eventId);
            if (cachedShellEvent) {
                const hasLazyMarkets = Number(cachedShellEvent.extra_market_count || 0) > 0
                    || cachedShellEvent.markets_lazy === true;
                const hasResolvedExtraMarkets = Array.isArray(cachedShellEvent.extra_markets)
                    && cachedShellEvent.extra_markets.length > 0;
                if (hasLazyMarkets && !hasResolvedExtraMarkets) {
                    // Initial payloads intentionally omit extra market selections.
                } else {
                    rememberExpandedShellEvent(cachedShellEvent);
                    return cachedShellEvent;
                }
            }

            const detailFilters = {
                phase_key: (row && row.dataset.eventPhase) || activePhaseForTab(),
                event_ids: eventId,
            };
            const detailInflightKey = 'event|' + eventId;
            if (expandedEventDetailInFlight.has(detailInflightKey)) {
                return expandedEventDetailInFlight.get(detailInflightKey).then(function (payload) {
                    const shellEvent = shellEventFromPayload(payload, eventId);
                    if (shellEvent) {
                        rememberExpandedShellEvent(shellEvent);
                    }

                    return shellEvent;
                });
            }

            const detailRequest = fetchLiveOffersPayload(1, detailFilters)
                .then(function (payload) {
                    rememberExpandedDetailsFromPayload(payload);
                    return payload;
                })
                .finally(function () {
                    expandedEventDetailInFlight.delete(detailInflightKey);
                });

            expandedEventDetailInFlight.set(detailInflightKey, detailRequest);

            const detailPayload = await detailRequest;
            const detailShellEvent = shellEventFromPayload(detailPayload, eventId);
            if (detailShellEvent) {
                rememberExpandedShellEvent(detailShellEvent);
                return detailShellEvent;
            }

            if (cachedShellEvent) {
                rememberExpandedShellEvent(cachedShellEvent);
                return cachedShellEvent;
            }

            const inflightKey = liveOfferCacheKey(filters) || ('event|' + eventId);
            if (expandedEventDetailInFlight.has(inflightKey)) {
                return expandedEventDetailInFlight.get(inflightKey).then(function (payload) {
                    const shellEvent = shellEventFromPayload(payload, eventId);
                    if (shellEvent) {
                        rememberExpandedShellEvent(shellEvent);
                    }

                    return shellEvent;
                });
            }

            const request = fetchLiveOffersPayload(livePollLimitForFilters(filters, false), filters)
                .then(function (payload) {
                    rememberLiveOfferPayload(filters, payload);
                    rememberExpandedDetailsFromPayload(payload);
                    return payload;
                })
                .finally(function () {
                    expandedEventDetailInFlight.delete(inflightKey);
                });

            expandedEventDetailInFlight.set(inflightKey, request);

            const payload = await request;
            return shellEventFromPayload(payload, eventId);
        }

        function buildLiveHydrationContextKey(filters) {
            const nextFilters = filters || currentLiveOfferFilters();

            return [
                nextFilters.phase_key || '',
                nextFilters.interest_priority || '',
                nextFilters.time_priority || '',
                nextFilters.sport_group_key || '',
                nextFilters.league_filter_key || '',
                normalizeSearchQuery(nextFilters.search || ''),
            ].join('|');
        }

        function filtersMatchCurrentHydrationContext(filters) {
            return buildLiveHydrationContextKey(filters || currentLiveOfferFilters()) === liveHydrationContextKey;
        }

        function settleHydrationUiForCurrentContext(filters, detail) {
            if (!filtersMatchCurrentHydrationContext(filters)) {
                return;
            }

            const visibleCount = currentVisibleRowSet.size;
            const hasVisibleRows = visibleCount > 0;
            hydrationReady = hasVisibleRows || hydrationReady;
            if (hasVisibleRows || hydrationReady) {
                setInitialHydrationState('ready');
            } else {
                setInitialHydrationState('failed');
            }
            setHydrationUi(hasVisibleRows || hydrationReady ? 'ready' : 'stale', detail || (hasVisibleRows
                ? t('live_ready', 'Canlı liste hazır.')
                : t('live_preserved', 'Liste şu an yenilenemedi. Son görünüm korunuyor.')), {
                current: visibleCount,
                total: Math.max(visibleCount, 1),
            });
        }

        function expectedScopedEventCount(filters) {
            const nextFilters = filters || currentLiveOfferFilters();
            const normalizedSearch = normalizeSearchQuery(nextFilters.search || '');

            if (normalizedSearch !== '') {
                return 0;
            }

            if ((nextFilters.sport_group_key || '') === 'upcoming') {
                return Number((countsForTab(activePrimaryTab) || {}).total || 0);
            }

            if ((nextFilters.time_priority || '') === 'next') {
                return Math.min(Number((mergedCountsForTab('pre-match') || {}).total || 0), nextMatchesDisplayLimit);
            }

            if ((nextFilters.interest_priority || '') === 'featured') {
                return Math.min(Number((mergedCountsForTab('pre-match') || {}).total || 0), featuredMatchesDisplayLimit);
            }

            const stableCounts = summaryCountsForTab((nextFilters.phase_key || activePhaseForTab()) === 'in-game' ? 'in-game' : 'pre-match');
            if (!stableCounts) {
                return 0;
            }

            if (nextFilters.league_filter_key) {
                return Number(stableCounts.byLeague[nextFilters.league_filter_key] || 0);
            }

            if (nextFilters.sport_group_key) {
                return Number(stableCounts.bySport[nextFilters.sport_group_key] || 0);
            }

            return Number(stableCounts.total || 0);
        }

        function localScopedEventCount(filters) {
            const nextFilters = filters || currentLiveOfferFilters();

            return filteredRowRecords(
                nextFilters.phase_key || activePhaseForTab(),
                nextFilters.sport_group_key || activeSportFilter,
                nextFilters.league_filter_key || activeLeagueFilter,
                nextFilters.search || activeSearchQuery
            ).length;
        }

        function scopeNeedsHydration(filters) {
            const expectedCount = expectedScopedEventCount(filters);
            if (expectedCount <= 0) {
                return false;
            }

            return localScopedEventCount(filters) < expectedCount;
        }

        function usesPagedSportScopedLiveOffers(filters) {
            const nextFilters = filters || currentLiveOfferFilters();

            if (normalizeSearchQuery(nextFilters.search || '') !== '') {
                return false;
            }

            if ((nextFilters.phase_key || activePhaseForTab()) !== 'pre-match') {
                return false;
            }

            if ((nextFilters.time_priority || '') === 'next' || (nextFilters.interest_priority || '') === 'featured') {
                return false;
            }

            return Boolean(nextFilters.sport_group_key || nextFilters.league_filter_key);
        }

        function resetLiveHydrationWindow() {
            livePollCursorQueue = [null];
            livePollCursorIndex = 0;
        }

        function invalidateLiveHydrationState(previousTabKey) {
            // Retire the request before its asynchronous catch/finally can affect a new scope.
            livePollRequestToken += 1;
            hydrationRunSequence += 1;
            hydrationInFlight = false;
            livePollInFlight = false;
            if (livePollAbortController) {
                livePollAbortController.abort();
                livePollAbortController = null;
            }
            if (layout && layout.dataset.hydrationState === 'loading') {
                setHydrationUi('aborted', '');
            }
            if (layout && layout.dataset.initialHydrationState === 'pending') {
                setInitialHydrationState('aborted');
            }
            const retiredTab = previousTabKey || activePrimaryTab;
            if (['pre-match', 'in-game'].includes(retiredTab)) {
                const trigger = tabTriggers.find(function (item) { return (item.dataset.tab || item.dataset.tabTrigger) === retiredTab; });
                if (trigger && trigger.dataset.surfaceState === 'loading') {
                    setSurfaceState(retiredTab, providerDegradedState ? 'degraded' : 'aborted');
                }
            }
            visibleRowLimit = initialVisibleRowLimit;
            liveHydrationContextKey = buildLiveHydrationContextKey(currentLiveOfferFilters());
            hydrationBootstrapped = false;
            hydrationReady = false;
            resetLiveHydrationWindow();
            clearLivePollTimer();

            if (interactiveLiveRefreshTimer) {
                window.clearTimeout(interactiveLiveRefreshTimer);
                interactiveLiveRefreshTimer = null;
            }
        }

        function livePollLimitForFilters(filters, useBootstrapLimit) {
            const nextFilters = filters || currentLiveOfferFilters();

            if (usesPagedSportScopedLiveOffers(nextFilters)) {
                return initialVisibleRowLimit;
            }

            if (useBootstrapLimit) {
                return livePollBootstrapLimit;
            }

            if (normalizeSearchQuery(nextFilters.search || '') !== '') {
                return livePollSearchLimit;
            }

            if ((nextFilters.phase_key || activePhaseForTab()) === 'in-game') {
                return livePollInGameLimit;
            }

            if ((nextFilters.time_priority || '') === 'next') {
                return nextMatchesDisplayLimit;
            }

            if ((nextFilters.interest_priority || '') === 'featured') {
                return featuredMatchesDisplayLimit;
            }

            return livePollPrematchLimit;
        }

        function livePollCursorForNextCycle() {
            if (!Array.isArray(livePollCursorQueue) || livePollCursorQueue.length === 0) {
                return null;
            }

            const cursor = livePollCursorQueue[livePollCursorIndex % livePollCursorQueue.length] || null;
            livePollCursorIndex = (livePollCursorIndex + 1) % livePollCursorQueue.length;

            return cursor;
        }

        function prefetchLiveOfferPayload(filters, limit) {
            if (document.hidden || (filters || {}).interest_priority === 'featured') {
                return Promise.resolve(null);
            }
            if (!livePollEnabled || !offersIndexApiUrl) {
                return null;
            }

            const requestFilters = Object.assign({}, filters || {});
            if (!requestFilters.surface && !requestFilters.event_ids) {
                requestFilters.surface = 'initial';
            }

            const cacheKey = liveOfferCacheKey(requestFilters);
            if (!cacheKey || cachedLiveOfferPayload(requestFilters, { allowStalePreparedSlice: false })) {
                return null;
            }

            if (liveOfferPrefetchPromises.has(cacheKey)) {
                return liveOfferPrefetchPromises.get(cacheKey);
            }

            liveOfferPrefetchInFlight.add(cacheKey);
            const request = fetchLiveOffersPayload(livePollLimitForFilters(requestFilters, false) || limit, requestFilters, null, null)
                .then(function (payload) {
                    rememberLiveOfferPayload(requestFilters, payload);
                    rememberExpandedDetailsFromPayload(payload);

                    return payload;
                })
                .catch(function () {
                    return null;
                })
                .finally(function () {
                    liveOfferPrefetchInFlight.delete(cacheKey);
                    liveOfferPrefetchPromises.delete(cacheKey);
                });

            liveOfferPrefetchPromises.set(cacheKey, request);

            return request;
        }

        function scheduleNextMatchesPrefetch() {
            if (!livePollEnabled || activePrimaryTab !== 'pre-match') {
                return;
            }

            if (initialPayloadDeferred && !hydrationReady) {
                firstUsableHydrationReady().then(function () {
                    scheduleNextMatchesPrefetch();
                });
                return;
            }

            window.setTimeout(function () {
                prefetchLiveOfferPayload({
                    phase_key: 'pre-match',
                    time_priority: 'next',
                }, nextMatchesDisplayLimit);
            }, 300);
        }

        function setBoardSkeletonVisible(visible) {
            if (boardSkeleton) {
                boardSkeleton.hidden = !visible;
            }
        }

        function liveScopeMatchesRow(row, filters) {
            if (!row) {
                return false;
            }

            const nextFilters = filters || {};

            if (nextFilters.phase_key && (row.dataset.eventPhase || 'pre-match') !== nextFilters.phase_key) {
                return false;
            }

            if (nextFilters.sport_group_key && (row.dataset.sportKey || 'all') !== nextFilters.sport_group_key) {
                return false;
            }

            if (nextFilters.time_priority === 'next') {
                const startsAt = Number(row.dataset.startsAt || 0);
                return startsAt > 0 && (offlineDemo.active || startsAt >= Math.floor(Date.now() / 1000));
            }

            if (nextFilters.interest_priority === 'featured') {
                return (row.dataset.eventPhase || 'pre-match') === 'pre-match';
            }

            if (nextFilters.league_filter_key && (row.dataset.leagueFilterKey || 'all') !== nextFilters.league_filter_key) {
                return false;
            }

            return true;
        }

        function setBoardRefreshing(refreshing, detail, options) {
            const nextOptions = options || {};
            if (boardRefreshHideTimer !== null) {
                window.clearTimeout(boardRefreshHideTimer);
                boardRefreshHideTimer = null;
            }

            if (layout) {
                layout.classList.toggle('is-board-refreshing', Boolean(refreshing));
            }

            if (!refreshing) {
                if (nextOptions.immediate) {
                    boardRefreshMinimumVisibleUntil = 0;
                }
                const remainingVisibleMs = boardRefreshMinimumVisibleUntil - Date.now();
                if (remainingVisibleMs > 0) {
                    boardRefreshHideTimer = window.setTimeout(function () {
                        boardRefreshHideTimer = null;
                        setBoardRefreshing(false);
                    }, remainingVisibleMs);
                    return;
                }

                if (layout) {
                    delete layout.dataset.boardRefreshing;
                }
                if (hydrationReady && boardStatus && !summaryDebugEnabled) {
                    boardStatus.hidden = true;
                }
                return;
            }

            boardRefreshMinimumVisibleUntil = Date.now() + boardRefreshMinimumVisibleMs;

            if (layout) {
                layout.dataset.boardRefreshing = '1';
            }

            if (boardStatus && summaryDebugEnabled) {
                boardStatus.hidden = false;
            }

            if (boardStatusTitle) {
                boardStatusTitle.textContent = detail || '';
            }

            if (boardStatusText) {
                boardStatusText.textContent = detail || '';
            }

            if (boardStatusMeter) {
                boardStatusMeter.textContent = t('preparing', 'Hazırlanıyor');
            }
        }

        function applySearchQuery(nextQuery, options) {
            const nextOptions = options || {};
            const offersTabActive = activePrimaryTab === 'pre-match' || activePrimaryTab === 'in-game';
            activeSearchQuery = normalizeSearchQuery(nextQuery);

            if (!offersTabActive) {
                if (activePrimaryTab === 'bet-feed') {
                    applyBetFeedSearchState(activeSearchQuery);
                }
                updateSearchPanelState();
                return;
            }

            activeMatchPage = 1;
            invalidateLiveHydrationState();
            updateSearchPanelState();
            const localRowsReady = filteredRowRecords(
                activePhaseForTab(), activeSportFilter, activeLeagueFilter, activeSearchQuery
            ).length > 0;
            scheduleBoardRefresh({
                detail: '',
                updateTabCounts: true,
                indicateBusy: !localRowsReady,
            });

            if (nextOptions.queueLiveRefresh !== false) {
                queueInteractiveLiveRefresh(40);
            }
        }

        function nextAnimationFrame() {
            return new Promise(function (resolve) {
                window.requestAnimationFrame(function () {
                    resolve();
                });
            });
        }

        async function allowUsefulRowsToPaint() {
            await nextAnimationFrame();
            await new Promise(function (resolve) {
                window.setTimeout(resolve, 0);
            });
        }

        async function insertMissingShellEventsInBatches(shellEvents, byId, batchSize, options) {
            const nextOptions = options || {};
            const initialBatchSize = Math.max(Number(nextOptions.initialBatchSize || batchSize), 1);
            const deferredBatchSize = Math.max(Number(nextOptions.deferredBatchSize || batchSize), 1);
            let inserted = 0;
            let index = 0;

            while (index < shellEvents.length) {
                if (typeof nextOptions.shouldContinue === 'function' && !nextOptions.shouldContinue()) {
                    return false;
                }

                const currentBatchSize = index === 0 ? initialBatchSize : deferredBatchSize;
                const batch = shellEvents.slice(index, index + currentBatchSize);

                batch.forEach(function (shellEvent) {
                    const nextEvent = byId.get(String(shellEvent.event_id || '')) || eventPayloadFromShellEvent(shellEvent);
                    if (!nextEvent) {
                        return;
                    }

                    const nextRow = buildMatchRowElement(shellEvent);
                    insertOrMoveRowForEvent(nextRow, shellEvent, { deferRefresh: true });
                    patchMatchRowFromEvent(nextRow, nextEvent, shellEvent);
                });

                inserted += batch.length;
                index += batch.length;
                setHydrationUi('loading', '', {
                    current: inserted,
                    total: shellEvents.length,
                });

                if (inserted === batch.length && index < shellEvents.length) {
                    updateSearchPanelState();
                    setBoardRefreshing(false, '', { immediate: true });
                    await allowUsefulRowsToPaint();
                    continue;
                }

                if (index < shellEvents.length) {
                    await nextAnimationFrame();
                }
            }

            return true;
        }

        async function applyLivePayload(payload, options) {
            const demoState = (payload.meta || {}).offline_demo;
            if (demoState) applyOfflineDemoState(demoState);
            else if (offlineDemo.active && payload.meta && payload.meta.read_source === 'snapshot'
                && (payload.meta.read_meta || {}).snapshot_status === 'healthy') applyOfflineDemoState({active: false});
            const nextOptions = options || {};
            const appliedFilters = nextOptions.filters || currentLiveOfferFilters();
            const nextProviderState = (((payload || {}).meta || {}).degraded_state) && typeof payload.meta.degraded_state === 'object'
                ? payload.meta.degraded_state
                : null;

            if (nextProviderState && String(appliedFilters.phase_key || 'pre-match') === 'pre-match') {
                showProviderDegradedState(nextProviderState, {
                    preservePassiveRows: Boolean(nextOptions.keepStaleRows),
                });
                return;
            }

            clearProviderDegradedState();
            if (nextOptions.rowsReady) {
                hydrationReady = true;
            }
            const nextSummary = (((payload || {}).meta || {}).summary) && typeof payload.meta.summary === 'object'
                ? payload.meta.summary
                : null;
            const nextCounts = (((payload || {}).meta || {}).counts) && typeof payload.meta.counts === 'object'
                ? payload.meta.counts
                : null;
            const events = Array.isArray((((payload || {}).data || {}).events)) ? payload.data.events : [];
            const shellEvents = Array.isArray((((payload || {}).data || {}).shell_events)) ? payload.data.shell_events : [];
            rememberFeaturedEventOrder(payload, appliedFilters);
            const byId = new Map(events.map(function (event) {
                return [String(event.event_id || ''), event];
            }));
            const shellById = new Map(shellEvents.map(function (event) {
                return [String(event.event_id || ''), event];
            }));
            const patchJobs = [];

            rememberExpandedDetailsFromPayload(payload);

            if (nextSummary) {
                offerSummaryState = nextSummary;
                renderSummaryDebugState(offerSummaryState);
            }

            if (nextCounts) {
                liveOfferCountsState = nextCounts;
            }

            if (nextSummary || nextCounts) {
                ensureSportFilterButtonsFromSummary(nextSummary || offerSummaryState, nextCounts || liveOfferCountsState);
            }

            matchRows.slice().forEach(function (row, index) {
                const nextEvent = byId.get(String(row.dataset.eventId || ''));
                const nextShell = shellById.get(String(row.dataset.eventId || ''));
                const patchEvent = nextEvent || eventPayloadFromShellEvent(nextShell);
                if (patchEvent) {
                    patchJobs.push({
                        priority: livePatchPriorityForRow(row, index),
                        run: function () {
                            patchMatchRowFromEvent(row, patchEvent, nextShell || null);
                            if (nextShell) {
                                insertOrMoveRowForEvent(row, nextShell, { deferRefresh: true });
                            }
                        },
                    });
                } else if (!nextOptions.keepStaleRows && liveScopeMatchesRow(row, appliedFilters)) {
                    patchJobs.push({
                        priority: livePatchPriorityForRow(row, index) + 320,
                        run: function () {
                            markRowUnavailable(row, 'ended');
                            removeRowFromDom(row, { deferRefresh: true });
                        },
                    });
                }
            });

            await drainLivePatchJobs(patchJobs);

            const missingShellEvents = shellEvents.filter(function (shellEvent) {
                const eventId = String(shellEvent.event_id || '');
                return eventId !== ''
                    && !eventRowById.has(eventId);
            });

            if (nextOptions.chunkedInsert && missingShellEvents.length) {
                const insertedCurrentPayload = await insertMissingShellEventsInBatches(
                    missingShellEvents,
                    byId,
                    nextOptions.batchSize || 24,
                    {
                        initialBatchSize: nextOptions.initialBatchSize,
                        deferredBatchSize: nextOptions.deferredBatchSize,
                        shouldContinue: nextOptions.shouldContinue,
                    }
                );
                if (!insertedCurrentPayload) {
                    refreshDynamicCollections();
                    return;
                }
            } else {
                missingShellEvents.forEach(function (shellEvent) {
                    const eventId = String(shellEvent.event_id || '');
                    const nextEvent = byId.get(eventId) || eventPayloadFromShellEvent(shellEvent);
                    if (!eventId || !nextEvent) {
                        return;
                    }

                    const nextRow = buildMatchRowElement(shellEvent);
                    insertOrMoveRowForEvent(nextRow, shellEvent, { deferRefresh: true });
                    patchMatchRowFromEvent(nextRow, nextEvent, shellEvent);
                });
            }

            refreshDynamicCollections();
            sortLeagueNodes();
            updateTabCounts();
            updateSportFilterButtons();
            updateLeagueFilterButtons();
            updateMatchPagination();
            updateSearchPanelState();
            syncSportScrollButtons();
            if (shellEvents.length || visibleOfferRowCount() > 0) {
                setInitialOffersEmptyStateVisible(false);
            }

            if (nextOptions.markHydrated) {
                hydrationReady = true;
                setInitialHydrationState('ready');
                updateTabCounts();
                updateSportFilterButtons();
                updateLeagueFilterButtons();
                updateMatchPagination();
                setHydrationUi('ready', t('events_and_tournaments_ready', ':count etkinlik ve güncel turnuva listesi hazır.', { count: formatCountLabel(shellEvents.length) }), {
                    current: shellEvents.length,
                    total: shellEvents.length,
                });
            }
        }

        function handleSelectionTrigger(button) {
            if (submitState === 'submitting' || frozenAfterSuccess || button.disabled || button.classList.contains('is-disabled')) {
                return;
            }

            const payload = JSON.parse(button.dataset.selection || '{}');
            if (payload.acceptance_mode === 'offline_demo') {
                if (!offlineDemo.active) return;
                selected = sportsbookDemoCoupon(selected, payload);
                activeSlipMode = 'single';
                resetSubmissionState(false);
                if (slipViewportMode !== 'static') setSlipCollapsed(false);
                renderSelectionState();
                revealSlipShellAfterSelection();
                return;
            }
            if (!selectionPresentationReady(payload)) {
                return;
            }

            clearSuccessResetTimer();
            if (communityShareFeedback) {
                communityShareFeedback.textContent = '';
            }
            const isSelecting = !(selected && payload.selection_key === selected.selection_key);
            activeSlipMode = 'single';
            const stampedPayload = isSelecting ? stampSelectedPayload(payload, {
                selectedAt: Date.now(),
            }) : null;
            if (stampedPayload) {
                button.dataset.selection = JSON.stringify(stampedPayload);
            }
            selected = stampedPayload;
            exactWarningKey = '';

            if (isSelecting && slipViewportMode !== 'static') {
                setSlipCollapsed(false);
            }

            if (isSelecting) {
                pulseSelectionFeedback(button);
            } else {
                setCouponQuoteUpdating(false);
            }

            resetSubmissionState(false);
            renderSelectionState();

            if (stampedPayload) {
                revealSlipShellAfterSelection();
                // Snapshot first. Exact preparation belongs to submit or explicit retry.
            } else {
                selectedQuoteRefreshKey = '';
                selectedQuoteRefreshPromise = null;
            }
        }

        function bindSelectionTriggers(root) {
            queryIncludingRoot(root, '[data-selection-trigger]').forEach(function (button) {
                if (button.dataset.boundSelectionTrigger === '1') {
                    return;
                }

                if (!selectionPresentationReady(JSON.parse(button.dataset.selection || '{}'))) {
                    button.disabled = true;
                    button.classList.add('is-disabled');
                    button.setAttribute('aria-disabled', 'true');
                }
                button.dataset.boundSelectionTrigger = '1';
                button.addEventListener('click', function () {
                    handleSelectionTrigger(button);
                });
            });
        }

        async function handleMoreToggle(button) {
            const eventRow = button.closest('[data-event-row]');
            let extraMarkets = eventRow ? eventRow.querySelector('[data-extra-markets]') : null;

            if (!eventRow) {
                return;
            }

            if (button.dataset.extraMarketsLoading === '1') {
                return;
            }

            if (extraMarkets && extraMarkets.classList.contains('is-error')) {
                extraMarkets.remove();
                extraMarkets = null;
            }

            const shouldOpen = !extraMarkets || extraMarkets.hidden;
            if (!shouldOpen) {
                extraMarkets.hidden = true;
                button.classList.remove('is-active');
                button.setAttribute('aria-expanded', 'false');
                button.textContent = button.dataset.moreLabel || button.textContent;
                return;
            }

            if (!extraMarkets || extraMarkets.dataset.extraMarketsLoading === '1') {
                button.dataset.extraMarketsLoading = '1';
                button.classList.add('is-loading');
                button.classList.add('is-active');
                button.setAttribute('aria-expanded', 'true');
                button.textContent = t('loading', 'Yükleniyor');
                renderLazyExtraMarketsLoadingPanel(eventRow);

                try {
                    const shellEvent = await resolveExpandedShellEvent(eventRow);
                    if (!shellEvent || !detailMarketsForExtraPanel(shellEvent).length) {
                        throw new Error('extra_markets_unavailable');
                    }
                    extraMarkets = renderLazyExtraMarketsPanel(eventRow, shellEvent);
                } catch (error) {
                    extraMarkets = renderLazyExtraMarketsLoadingPanel(eventRow, t('extra_markets_unavailable', 'Ek marketler şu an yüklenemedi.'));
                    extraMarkets?.classList.add('is-error');
                    button.textContent = t('retry', 'Tekrar dene');
                    button.setAttribute('aria-expanded', 'true');
                    return;
                } finally {
                    delete button.dataset.extraMarketsLoading;
                    button.classList.remove('is-loading');
                }

                if (!extraMarkets || extraMarkets.dataset.extraMarketsLoading === '1') {
                    button.classList.remove('is-active');
                    button.setAttribute('aria-expanded', 'false');
                    button.textContent = button.dataset.moreLabel || button.textContent;
                    return;
                }
            }

            extraMarkets.hidden = !shouldOpen;
            button.classList.toggle('is-active', shouldOpen);
            button.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
            button.textContent = shouldOpen ? t('hide', 'Gizle') : (button.dataset.moreLabel || button.textContent);
        }

        function bindMoreToggleButtons(root) {
            queryIncludingRoot(root, '[data-more-toggle]').forEach(function (button) {
                if (button.dataset.boundMoreToggle === '1') {
                    return;
                }

                button.dataset.boundMoreToggle = '1';
                button.addEventListener('click', function () {
                    handleMoreToggle(button);
                });
            });
        }

        function handleMarketFamilyChip(button) {
            const panel = button.closest('[data-extra-markets]');
            if (!panel) {
                return;
            }

            const familyKey = button.dataset.marketFamilyChip || 'all';
            Array.from(panel.querySelectorAll('[data-market-family-chip]')).forEach(function (chip) {
                const isActive = chip === button;
                chip.classList.toggle('is-active', isActive);
                chip.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            });

            Array.from(panel.querySelectorAll('[data-market-family-section]')).forEach(function (section) {
                const shouldShow = familyKey === 'all' || section.dataset.marketFamilySection === familyKey;
                section.hidden = !shouldShow;
            });
        }

        function bindMarketFamilyChips(root) {
            queryIncludingRoot(root, '[data-market-family-chip]').forEach(function (button) {
                if (button.dataset.boundMarketFamilyChip === '1') {
                    return;
                }

                button.dataset.boundMarketFamilyChip = '1';
                button.addEventListener('click', function () {
                    handleMarketFamilyChip(button);
                });
            });
        }

        async function handleSportFilter(button) {
            const nextFilter = button.dataset.sportFilter || 'all';
            const returningFromSurfaceTab = activePrimaryTab === 'e-sports';
            if (!returningFromSurfaceTab && !activeTimePriorityMode && !activeInterestPriorityMode && nextFilter === activeSportFilter) {
                return;
            }

            activeTimePriorityMode = false;
            activeInterestPriorityMode = false;
            activeSportFilter = nextFilter;
            if (returningFromSurfaceTab) {
                setActiveTab('pre-match');
            }
            if (activeLeagueFilter !== 'all') {
                const activeLeagueButton = leagueFilterButtonByKey.get(activeLeagueFilter)
                    || leagueFilterButtons.find(function (node) {
                        return (node.dataset.leagueFilter || 'all') === activeLeagueFilter;
                    });

                if (activeLeagueButton && activeSportFilter !== 'all' && (activeLeagueButton.dataset.sportGroupKey || 'all') !== activeSportFilter) {
                    activeLeagueFilter = 'all';
                }
            }
            activeMatchPage = 1;
            invalidateLiveHydrationState();
            const nextFilters = currentLiveOfferFilters();
            const hydratedFilters = Object.assign({}, nextFilters, { surface: 'initial' });
            const preparedFilters = Object.assign({}, nextFilters, { surface: 'sport-slice' });
            const cachedPayload = cachedLiveOfferPayload(hydratedFilters);
            const cachedPreparedPayload = cachedLiveOfferPayload(preparedFilters);
            const localRowsReady = localScopedEventCount(nextFilters) > 0;

            hydrationReady = localRowsReady;
            updateSportFilterButtons();
            scheduleBoardRefresh({
                detail: (cachedPayload || cachedPreparedPayload || localRowsReady) ? t('cached_list_visible', 'Önceden yüklenen liste gösteriliyor.') : '',
                updateTabCounts: false,
                indicateBusy: !(cachedPayload || cachedPreparedPayload || localRowsReady),
            });
            queueActiveSportButtonVisibility({
                force: false,
            });

            if (localRowsReady) {
                hydrationBootstrapped = Boolean(cachedPayload);
                if (!cachedPayload) {
                    makeProviderRowsPassiveForRefresh();
                    queueInteractiveLiveRefresh(80, {
                        filters: nextFilters,
                    });
                }
                return;
            }

            if (cachedPayload) {
                await applyLivePayload(cachedPayload, {
                    filters: hydratedFilters,
                    keepStaleRows: true,
                    chunkedInsert: true,
                    batchSize: 4,
                    rowsReady: true,
                    markHydrated: true,
                });
                hydrationReady = true;
                hydrationBootstrapped = true;
                return;
            }

            let preparedPayload = cachedPreparedPayload;
            if (!preparedPayload) {
                try {
                    preparedPayload = await fetchLiveOffersPayload(initialVisibleRowLimit, preparedFilters, null, null);
                    if (!filtersMatchCurrentHydrationContext(nextFilters)) {
                        return;
                    }
                    rememberLiveOfferPayload(preparedFilters, preparedPayload);
                } catch (error) {
                    preparedPayload = null;
                }
            }

            if (preparedPayload) {
                await applyLivePayload(preparedPayload, {
                    filters: nextFilters,
                    keepStaleRows: true,
                    chunkedInsert: true,
                    batchSize: 4,
                    rowsReady: true,
                    markHydrated: false,
                });
            }

            if (scopeNeedsHydration(nextFilters) || preparedPayload) {
                setHydrationUi('loading', t('tournaments_loading', 'Turnuvalar kademeli yükleniyor.'), {
                    current: preparedPayload ? Math.min(initialVisibleRowLimit, localScopedEventCount(nextFilters)) : 0,
                    total: 1,
                });
            }
            hydrationBootstrapped = false;
            queueInteractiveLiveRefresh(preparedPayload || localRowsReady ? 80 : 0, {
                filters: nextFilters,
            });
        }

        async function handleNextMatchesToggle() {
            const nextActive = !activeTimePriorityMode;
            activeTimePriorityMode = nextActive;
            activeInterestPriorityMode = false;

            if (nextActive) {
                activeSportFilter = 'all';
                activeLeagueFilter = 'all';
                setActiveTab('pre-match');
            } else if (activeSportFilter === 'all') {
                activeSportFilter = firstAvailableSportFilter(mergedCountsForTab('pre-match'));
            }

            activeMatchPage = 1;
            invalidateLiveHydrationState();
            const nextFilters = currentLiveOfferFilters();
            const cachedPayload = nextActive ? cachedLiveOfferPayload(nextFilters) : null;
            const prefetchPromise = nextActive
                ? liveOfferPrefetchPromises.get(liveOfferCacheKey(nextFilters))
                : null;
            scheduleBoardRefresh({
                detail: nextActive ? (cachedPayload ? t('next_matches_visible', 'Sıradaki maçlar gösteriliyor.') : t('next_matches_loading', 'Sıradaki maçlar hazırlanıyor.')) : '',
                updateTabCounts: false,
            });

            if (nextActive && cachedPayload) {
                await applyLivePayload(cachedPayload, {
                    filters: nextFilters,
                    keepStaleRows: true,
                    markHydrated: true,
                });
                hydrationReady = true;
                hydrationBootstrapped = true;
                return;
            }

            if (nextActive && prefetchPromise) {
                const payload = await prefetchPromise;
                if (payload) {
                    rememberLiveOfferPayload(nextFilters, payload);
                    await applyLivePayload(payload, {
                        filters: nextFilters,
                        keepStaleRows: false,
                        markHydrated: true,
                    });
                    hydrationReady = true;
                    hydrationBootstrapped = true;
                    return;
                }
            }

            if (nextActive) {
                await pollLiveOffers({
                    interrupt: true,
                    filters: nextFilters,
                    limit: nextMatchesDisplayLimit,
                    markHydrated: true,
                    keepStaleRows: false,
                    chunkedInsert: true,
                    batchSize: nextMatchesDisplayLimit,
                });
                return;
            }

            queueInteractiveLiveRefresh(0, {
                filters: nextFilters,
            });
        }

        async function handleFeaturedMatchesToggle() {
            if (!featuredMatchesToggleButton || featuredMatchesToggleButton.hidden) {
                return;
            }
            const nextActive = !activeInterestPriorityMode;
            activeInterestPriorityMode = nextActive;
            activeTimePriorityMode = false;

            if (nextActive) {
                activeSportFilter = 'all';
                activeLeagueFilter = 'all';
                setActiveTab('pre-match');
            } else if (activeSportFilter === 'all') {
                activeSportFilter = firstAvailableSportFilter(mergedCountsForTab('pre-match'));
            }

            activeMatchPage = 1;
            invalidateLiveHydrationState();
            const nextFilters = currentLiveOfferFilters();
            const cachedPayload = nextActive ? cachedLiveOfferPayload(nextFilters) : null;
            scheduleBoardRefresh({
                detail: nextActive ? (cachedPayload ? t('featured_visible', 'Öne çıkanlar gösteriliyor.') : t('featured_loading', 'Öne çıkanlar hazırlanıyor.')) : '',
                updateTabCounts: false,
            });

            if (nextActive) {
                if (!cachedPayload) {
                    const passive = await pollLiveOffers({
                        interrupt: true, filters: nextFilters, surface: 'featured-slice',
                        limit: featuredMatchesDisplayLimit, markHydrated: true, keepStaleRows: false,
                    });
                    if (!filtersMatchCurrentHydrationContext(nextFilters)) {
                        return;
                    }
                    if (passive && ((passive.meta || {}).featured || {}).featured_hidden) {
                        featuredMatchesToggleButton.hidden = true;
                        activeInterestPriorityMode = false;
                        handleAllMatchesToggle();
                        return;
                    }
                    if (!passive || !(passive.data.shell_events || []).length) {
                        return;
                    }
                    await nextAnimationFrame();
                }
                await pollLiveOffers({
                    interrupt: true,
                    filters: nextFilters,
                    limit: featuredMatchesDisplayLimit,
                    markHydrated: true,
                    keepStaleRows: false,
                    chunkedInsert: true,
                    batchSize: featuredMatchesDisplayLimit,
                });
                return;
            }

            queueInteractiveLiveRefresh(0, {
                filters: nextFilters,
            });
        }

        function handleAllMatchesToggle() {
            activeInterestPriorityMode = false;
            activeTimePriorityMode = false;
            activeLeagueFilter = 'all';
            if (activeSportFilter === 'all') {
                activeSportFilter = firstAvailableSportFilter(mergedCountsForTab('pre-match'));
            }
            activeMatchPage = 1;
            invalidateLiveHydrationState();
            scheduleBoardRefresh({
                detail: '',
                updateTabCounts: false,
            });
            queueInteractiveLiveRefresh(0, {
                filters: currentLiveOfferFilters(),
            });
        }

        function bindSportFilterButtons(root) {
            queryIncludingRoot(root, '[data-sport-filter]').forEach(function (button) {
                if (button.dataset.boundSportFilter === '1') {
                    return;
                }

                button.dataset.boundSportFilter = '1';
                button.addEventListener('click', function () {
                    handleSportFilter(button);
                });
            });
        }

        function handleLeagueFilter(button) {
            activeTimePriorityMode = false;
            activeInterestPriorityMode = false;
            const nextLeagueFilter = button.dataset.leagueFilter || 'all';
            activeLeagueFilter = nextLeagueFilter === activeLeagueFilter ? 'all' : nextLeagueFilter;
            activeMatchPage = 1;
            invalidateLiveHydrationState();
            const nextFilters = currentLiveOfferFilters();
            const needsHydration = scopeNeedsHydration(nextFilters);
            const localRowsReady = localScopedEventCount(nextFilters) > 0;
            scheduleBoardRefresh({
                detail: '',
                updateTabCounts: false,
                indicateBusy: !localRowsReady,
            });
            if (needsHydration) {
                setHydrationUi('loading', t('tournaments_loading', 'Turnuvalar kademeli yükleniyor.'), {
                    current: 0,
                    total: 1,
                });
            }
            queueInteractiveLiveRefresh(needsHydration ? 0 : 240, {
                filters: nextFilters,
            });
        }

        function bindLeagueFilterButtons(root) {
            queryIncludingRoot(root, '[data-league-filter]').forEach(function (button) {
                if (button.dataset.boundLeagueFilter === '1') {
                    return;
                }

                button.dataset.boundLeagueFilter = '1';
                button.addEventListener('click', function () {
                    handleLeagueFilter(button);
                });
            });
        }

        function setCatalogModalOpen(open) {
            if (!catalogModal) {
                return;
            }

            if (open && myBetsPanel && !myBetsPanel.hidden) {
                setMyBetsPanelOpen(false);
            }

            catalogModal.hidden = !open;
            if (catalogModalOpen) {
                catalogModalOpen.setAttribute('aria-expanded', open ? 'true' : 'false');
                catalogModalOpen.classList.toggle('is-active', open);
            }

            if (open) {
                catalogPath = [];
                renderCatalogHeader();
                loadCatalogTree(false);
            }
        }

        function setMyBetsPanelOpen(open) {
            if (!myBetsPanel) {
                return;
            }

            if (open && catalogModal && !catalogModal.hidden) {
                setCatalogModalOpen(false);
            }

            myBetsPanel.hidden = !open;
            myBetsPanel.setAttribute('aria-hidden', open ? 'false' : 'true');
            document.documentElement.classList.toggle('sb2-my-bets-open', open);

            if (myBetsOpenButton) {
                myBetsOpenButton.setAttribute('aria-expanded', open ? 'true' : 'false');
                myBetsOpenButton.classList.toggle('is-active', open);
            }

            if (open) {
                updateHistoryVisibility();
                myBetsPanel.querySelector('.sb2-my-bets-modal__close')?.focus({ preventScroll: true });
            } else {
                myBetsOpenButton?.focus({ preventScroll: true });
            }
        }

        function catalogTreeEndpointForCurrentScope() {
            if (!catalogTreeApiUrl) {
                return null;
            }

            const endpoint = new URL(catalogTreeApiUrl, window.location.origin);
            if (activeSportFilter && activeSportFilter !== 'all' && activeSportFilter !== 'upcoming') {
                endpoint.searchParams.set('sport_group_key', activeSportFilter);
            }

            return endpoint;
        }

        function renderCatalogLoadingState() {
            if (!catalogList) {
                return;
            }

            catalogList.innerHTML = '';
            for (let index = 0; index < 4; index += 1) {
                const row = document.createElement('div');
                row.className = 'sb2-catalog-card sb2-catalog-card--skeleton';
                row.setAttribute('aria-hidden', 'true');
                row.innerHTML = '<span class="sb2-catalog-skeleton-mark"></span>'
                    + '<span class="sb2-catalog-skeleton-line"></span>'
                    + '<span class="sb2-catalog-skeleton-count"></span>';
                catalogList.appendChild(row);
            }
        }

        async function loadCatalogTree(force) {
            const endpoint = catalogTreeEndpointForCurrentScope();
            const nextCatalogPayloadKey = endpoint ? endpoint.toString() : '';

            if (!endpoint) {
                renderCatalogModal();
                return Promise.resolve();
            }

            if (catalogLoading) {
                return catalogLoadPromise || Promise.resolve();
            }

            if (catalogPayload && catalogPayloadKey === nextCatalogPayloadKey && !force) {
                renderCatalogModal();
                return Promise.resolve();
            }

            catalogLoading = true;
            if (catalogModalStatus) {
                catalogModalStatus.textContent = t('catalog_loading', 'Katalog yükleniyor...');
            }
            renderCatalogLoadingState();
            if (catalogEmpty) {
                catalogEmpty.hidden = true;
            }

            catalogLoadPromise = (async function () {
                try {
                    const response = await requestJson(endpoint.toString(), {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                    },
                    credentials: 'same-origin',
                    forceRefresh: Boolean(force),
                    });

                    if (!response.ok) {
                        throw new Error('catalog_unavailable');
                    }

                    const payload = await response.json();
                    catalogPayload = payload && payload.success ? payload.data : null;
                    catalogPayloadKey = catalogPayload ? nextCatalogPayloadKey : '';
                    catalogPath = [];
                    renderCatalogModal();
                } catch (error) {
                    catalogPayloadKey = '';
                    if (catalogModalStatus) {
                        catalogModalStatus.textContent = t('catalog_unavailable', 'Katalog şu anda yüklenemedi');
                    }
                    if (catalogList) {
                        catalogList.innerHTML = '';
                    }
                    if (catalogEmpty) {
                        catalogEmpty.hidden = false;
                        catalogEmpty.textContent = t('catalog_unavailable', 'Katalog şu anda yüklenemedi');
                    }
                } finally {
                    catalogLoading = false;
                    catalogLoadPromise = null;
                }
            })();

            return catalogLoadPromise;
        }

        function renderCatalogModal() {
            if (!catalogList || !catalogPayload) {
                return;
            }

            const availability = catalogPayload.availability && typeof catalogPayload.availability === 'object'
                ? catalogPayload.availability
                : null;
            if (availability && availability.state === 'degraded') {
                catalogList.innerHTML = '<div class="sb2-feed-empty"><strong>'
                    + escapeHtml(t('catalog_provider_unavailable_title', 'Katalog şu anda yenilenemiyor.'))
                    + '</strong><span>' + escapeHtml(t('provider_unavailable_body', 'Birkaç dakika sonra tekrar deneyin.'))
                    + '</span><span>' + escapeHtml(t('error_code_prefix', 'Kod')) + ': ' + escapeHtml(availability.code || 'SPORTSBOOK_CATALOG_PROVIDER_UNAVAILABLE')
                    + '</span></div>';
                renderCatalogHeader();
                renderCatalogCrumbs();
                if (catalogModalStatus) {
                    catalogModalStatus.textContent = t('catalog_provider_unavailable_title', 'Katalog şu anda yenilenemiyor.');
                }
                if (catalogEmpty) {
                    catalogEmpty.hidden = true;
                }
                return;
            }

            const records = catalogChildrenForPath();
            catalogList.innerHTML = '';
            renderCatalogHeader();
            renderCatalogCrumbs();

            if (catalogModalStatus) {
                catalogModalStatus.textContent = catalogStatusLabel();
            }

            if (catalogEmpty) {
                catalogEmpty.hidden = records.length > 0;
                catalogEmpty.textContent = t('no_active_match', 'Aktif maç yok');
            }

            records.forEach(function (record) {
                catalogList.appendChild(buildCatalogCard(record));
            });
        }

        function catalogChildrenForPath() {
            const regions = Array.isArray(catalogPayload.regions) ? catalogPayload.regions : [];
            const regionKey = catalogPath[0] || '';
            const countryKey = catalogPath[1] || '';

            if (!regionKey) {
                return regions.map(function (region) {
                    return Object.assign({ node_type: 'region' }, region);
                });
            }

            const region = regions.find(function (item) {
                return String(item.region_key || '') === regionKey;
            });
            if (!region) {
                return [];
            }

            if (!countryKey) {
                return (Array.isArray(region.countries) ? region.countries : []).map(function (country) {
                    return Object.assign({ node_type: 'country' }, country);
                });
            }

            const country = (Array.isArray(region.countries) ? region.countries : []).find(function (item) {
                return String(item.country_key || '') === countryKey;
            });

            return country && Array.isArray(country.competitions)
                ? country.competitions.map(function (competition) {
                    return Object.assign({ node_type: 'competition' }, competition);
                })
                : [];
        }

        function renderCatalogCrumbs() {
            if (!catalogCrumbs || !catalogPayload) {
                return;
            }

            catalogCrumbs.hidden = true;
            const crumbs = [{ label: t('catalog_fallback', 'Katalog'), path: [] }];
            const regions = Array.isArray(catalogPayload.regions) ? catalogPayload.regions : [];
            const region = regions.find(function (item) {
                return String(item.region_key || '') === String(catalogPath[0] || '');
            });
            if (region) {
                crumbs.push({ label: region.label || region.region_key, path: [region.region_key] });
            }

            const country = region && Array.isArray(region.countries)
                ? region.countries.find(function (item) {
                    return String(item.country_key || '') === String(catalogPath[1] || '');
                })
                : null;
            if (country) {
                crumbs.push({ label: country.label || country.country_key, path: [region.region_key, country.country_key] });
            }

            catalogCrumbs.innerHTML = '';
            crumbs.forEach(function (crumb) {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'sb2-catalog-crumb';
                button.textContent = crumb.label;
                button.addEventListener('click', function () {
                    catalogPath = crumb.path.slice();
                    renderCatalogModal();
                });
                catalogCrumbs.appendChild(button);
            });
        }

        function renderCatalogHeader() {
            const context = catalogCurrentContext();

            if (catalogModalTitle) {
                catalogModalTitle.textContent = context.title;
            }

            if (catalogModalTitleIcon) {
                catalogModalTitleIcon.setAttribute('aria-hidden', 'true');
                if (context.type === 'country') {
                    catalogModalTitleIcon.innerHTML = catalogCountryMarkHtml(context.record);
                } else if (context.type === 'region') {
                    catalogModalTitleIcon.innerHTML = catalogRegionMarkHtml(context.record);
                } else if (context.sport_key === 'other') {
                    catalogModalTitleIcon.innerHTML = catalogRegionMarkHtml({ region_key: 'international' });
                } else {
                    const asset = catalogSportTitleAssetByKey[context.sport_key]
                        || sportCardArtAssetByKey[context.sport_key]
                        || context.asset_meta;
                    catalogModalTitleIcon.innerHTML = entityMarkHtml(asset, context.title, 'sport');
                }
            }

            if (catalogModalBack) {
                const canNavigateBack = catalogPath.length > 0;
                catalogModalBack.hidden = !canNavigateBack;
                catalogModalBack.disabled = !canNavigateBack;
                catalogModalBack.setAttribute('aria-hidden', canNavigateBack ? 'false' : 'true');
                catalogModalBack.setAttribute('tabindex', canNavigateBack ? '0' : '-1');
                catalogModalBack.setAttribute('aria-label', t('back', 'Geri'));
            }
        }

        function catalogCurrentContext() {
            const region = catalogRegionForPath();
            const country = catalogCountryForPath(region);
            const sportGroupKey = catalogSportGroupKey();

            if (country) {
                return {
                    title: catalogDisplayLabel(country),
                    type: 'country',
                    record: country,
                    asset_meta: country.asset_meta || {},
                };
            }

            if (region) {
                return {
                    title: catalogDisplayLabel(region),
                    type: 'region',
                    record: region,
                    asset_meta: region.asset_meta || {},
                };
            }

            return {
                title: sportGroupKey === 'other' ? t('catalog_world', 'Dünya') : sportLabelFromKey(sportGroupKey),
                type: 'sport',
                sport_key: sportGroupKey,
                asset_meta: catalogPayload && catalogPayload.sport_asset ? catalogPayload.sport_asset : {},
            };
        }

        function catalogSportGroupKey() {
            const sportGroupKey = normalizeCatalogSportGroupKey(activeSportFilter);
            if (sportGroupKey) {
                return sportGroupKey;
            }

            const activeSportButton = document.querySelector('[data-sport-filter].is-active, [data-sport-filter][aria-selected="true"]');
            const visibleSportGroupKey = normalizeCatalogSportGroupKey(activeSportButton ? activeSportButton.dataset.sportFilter : '');
            if (visibleSportGroupKey) {
                return visibleSportGroupKey;
            }

            const urlSportGroupKey = normalizeCatalogSportGroupKey(new URLSearchParams(window.location.search).get('sport'));

            return urlSportGroupKey || 'other';
        }

        function normalizeCatalogSportGroupKey(sportGroupKey) {
            const normalizedKey = String(sportGroupKey || '').trim().toLowerCase();

            return normalizedKey && normalizedKey !== 'all' && normalizedKey !== 'upcoming'
                ? normalizedKey
                : '';
        }

        function catalogRegionForPath() {
            const regions = catalogPayload && Array.isArray(catalogPayload.regions) ? catalogPayload.regions : [];
            const regionKey = String(catalogPath[0] || '');

            return regions.find(function (item) {
                return String(item.region_key || '') === regionKey;
            }) || null;
        }

        function catalogCountryForPath(region) {
            if (!region || !Array.isArray(region.countries)) {
                return null;
            }

            const countryKey = String(catalogPath[1] || '');

            return region.countries.find(function (item) {
                return String(item.country_key || '') === countryKey;
            }) || null;
        }

        function buildCatalogCard(record) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'sb2-catalog-card';
            if ((record.event_count || 0) <= 0) {
                button.classList.add('is-empty');
            }
            button.dataset.catalogNodeType = record.node_type || '';
            button.dataset.catalogType = record.node_type || '';
            button.dataset.catalogKey = catalogRecordKey(record);

            const badge = document.createElement('span');
            badge.className = 'sb2-catalog-card__badge';
            if ((record.node_type || '') === 'country') {
                badge.classList.add('sb2-catalog-card__badge--country');
                badge.innerHTML = catalogCountryMarkHtml(record);
            } else if ((record.node_type || '') === 'region') {
                badge.innerHTML = catalogRegionMarkHtml(record);
            } else {
                badge.innerHTML = entityMarkHtml(record.asset_meta || {}, catalogDisplayLabel(record), record.node_type || 'catalog');
            }

            const title = document.createElement('strong');
            title.className = 'sb2-catalog-card__title';
            title.textContent = catalogDisplayLabel(record);

            const count = document.createElement('span');
            count.className = 'sb2-catalog-card__count';
            count.textContent = record.count_label || formatCountLabel(record.event_count || 0);

            const availabilityLabel = String(record.availability_label || '').trim();
            if (availabilityLabel) {
                button.title = availabilityLabel;
            }

            const chevron = document.createElement('span');
            chevron.className = 'sb2-catalog-card__chevron';
            chevron.setAttribute('aria-hidden', 'true');
            chevron.innerHTML = '<i class="fas fa-chevron-right"></i>';

            button.appendChild(badge);
            button.appendChild(title);
            button.appendChild(count);
            button.appendChild(chevron);

            button.addEventListener('click', function () {
                handleCatalogCard(record);
            });

            return button;
        }

        function catalogRecordKey(record) {
            if ((record.node_type || '') === 'region') {
                return String(record.region_key || '');
            }
            if ((record.node_type || '') === 'country') {
                return String(record.country_key || '');
            }
            if ((record.node_type || '') === 'competition') {
                return String(record.canonical_competition_key || '');
            }

            return '';
        }

        async function handleCatalogCard(record) {
            if ((record.node_type || '') === 'region') {
                catalogPath = [String(record.region_key || '')];
                renderCatalogModal();
                return;
            }

            if ((record.node_type || '') === 'country') {
                catalogPath = [String(record.region_key || catalogPath[0] || ''), String(record.country_key || '')];
                renderCatalogModal();
                return;
            }

            if ((record.node_type || '') !== 'competition') {
                return;
            }

            if ((record.event_count || 0) <= 0 || !record.league_filter_key) {
                const emptyLabel = record.availability_label || record.empty_label || t('no_active_match', 'Aktif maç yok');
                if (catalogModalStatus) {
                    catalogModalStatus.textContent = emptyLabel;
                }
                if (catalogEmpty) {
                    catalogEmpty.hidden = false;
                    catalogEmpty.textContent = emptyLabel;
                }
                return;
            }

            setActiveTab('pre-match');
            activeTimePriorityMode = false;
            activeInterestPriorityMode = false;
            activeSearchQuery = '';
            activeSportFilter = record.sport_group_key || activeSportFilter;
            activeLeagueFilter = record.league_filter_key;
            activeCatalogCompetition = {
                leagueFilterKey: activeLeagueFilter,
                sportGroupKey: activeSportFilter,
                label: catalogDisplayLabel(record),
            };
            activeMatchPage = 1;
            invalidateLiveHydrationState();
            const filters = currentLiveOfferFilters();
            startCatalogSelectionLoading();
            updateUtilityButtons();
            updateSearchPanelState();
            updateLeagueFilterButtons();
            updateMatchPagination();
            setCatalogModalOpen(false);
            setHydrationUi('loading', t('tournament_matches_loading', 'Turnuva maçları yükleniyor.'), {
                current: 0,
                total: 1,
            });
            const preparedFilters = Object.assign({}, filters, {
                surface: 'league-slice',
            });
            const preparedPayload = await pollLiveOffers({
                bootstrap: true,
                surface: 'league-slice',
                limit: initialVisibleRowLimit,
                filters: preparedFilters,
                chunkedInsert: true,
                batchSize: 24,
                markHydrated: false,
                keepStaleRows: false,
                interrupt: true,
            });

            if (!filtersMatchCurrentHydrationContext(filters)) {
                return;
            }

            if (preparedPayload) {
                const preparedMeta = ((preparedPayload || {}).meta || {});
                setHydrationUi('loading', t('odds_refreshing', 'Oranlar yenileniyor.'), {
                    current: Number(preparedMeta.returned_count || 0),
                    total: Math.max(Number(preparedMeta.visible_total || 0), 1),
                });
                await nextAnimationFrame();
            }

            queueInteractiveLiveRefresh(preparedPayload ? 40 : 0, {
                filters: filters,
            });
        }

        function catalogCompetitionMatchesActiveFilter() {
            return activeCatalogCompetition !== null
                && activePrimaryTab === 'pre-match'
                && activeCatalogCompetition.leagueFilterKey === activeLeagueFilter
                && activeCatalogCompetition.sportGroupKey === activeSportFilter;
        }

        function clearCatalogSelectionLoadingGuard() {
            if (catalogSelectionLoadingTimer !== null) {
                window.clearTimeout(catalogSelectionLoadingTimer);
                catalogSelectionLoadingTimer = null;
            }
        }

        function catalogSelectionStillCurrent(filters) {
            return catalogSelectionLoadingContextKey !== ''
                && catalogSelectionLoadingContextKey === buildLiveHydrationContextKey(filters || currentLiveOfferFilters())
                && activePrimaryTab === 'pre-match';
        }

        function showCatalogSelectionDelayedState() {
            catalogSelectionLoadingTimer = null;
            if (!catalogSelectionStillCurrent()) {
                return;
            }

            if (layout) {
                layout.classList.remove('is-scope-updating');
            }
            setBoardSkeletonVisible(false);
            setBoardRefreshing(false);
            setHydrationUi('stale', t('catalog_delayed_title', 'Liste biraz gecikti'));

            if (filterEmptyState) {
                if (emptyStateTitle) {
                    emptyStateTitle.textContent = t('catalog_delayed_title', 'Liste biraz gecikti');
                }
                if (emptyStateText) {
                    emptyStateText.textContent = t('catalog_delayed_body', 'Turnuva filtresi için maç listesi alınamadı. Tekrar deneyebilirsin.');
                }
                filterEmptyState.hidden = false;
                showFilterRetryAction();
            }

            if (matchPageSummary) {
                matchPageSummary.textContent = t('catalog_delayed_title', 'Liste biraz gecikti');
            }
        }

        function finishCatalogSelectionLoadingForFilters(filters, succeeded) {
            if (!catalogSelectionStillCurrent(filters)) {
                return;
            }

            clearCatalogSelectionLoadingGuard();
            catalogSelectionLoadingContextKey = '';
            setBoardSkeletonVisible(false);
            setBoardRefreshing(false);

            if (succeeded) {
                hideFilterRetryAction();
                return;
            }

            if (filterEmptyState && currentVisibleRowSet.size === 0) {
                if (emptyStateTitle) {
                    emptyStateTitle.textContent = t('catalog_delayed_title', 'Liste biraz gecikti');
                }
                if (emptyStateText) {
                    emptyStateText.textContent = t('catalog_delayed_body', 'Turnuva filtresi için maç listesi alınamadı. Tekrar deneyebilirsin.');
                }
                filterEmptyState.hidden = false;
                showFilterRetryAction();
            }
        }

        function startCatalogSelectionLoading() {
            clearCatalogSelectionLoadingGuard();
            catalogSelectionLoadingContextKey = buildLiveHydrationContextKey(currentLiveOfferFilters());
            setBoardRefreshing(true, t('tournament_matches_loading', 'Turnuva maçları yükleniyor.'));
            cancelDeferredRowReveal();

            const hasVisibleRows = currentVisibleRowSet.size > 0 || visibleOfferRowCount() > 0;
            if (!hasVisibleRows) {
                applyVisibleRowState(new Set());
                applyVisibleLeagueState(new Map());
                rebalanceLeagueOpenState();
            }
            setBoardSkeletonVisible(!hasVisibleRows);

            if (filterEmptyState) {
                filterEmptyState.hidden = true;
            }
            hideFilterRetryAction();

            if (matchPageNumbers) {
                matchPageNumbers.innerHTML = '';
            }

            if (matchPageSummary) {
                matchPageSummary.textContent = hasVisibleRows ? t('list_updating', 'Liste güncelleniyor') : t('events_loading', 'Etkinlikler yükleniyor');
            }

            catalogSelectionLoadingTimer = window.setTimeout(showCatalogSelectionDelayedState, catalogSelectionLoadingTimeoutMs);
        }

        function catalogDisplayLabel(record) {
            const label = record.label || record.short_label || '';
            if (String(record.node_type || '') === 'competition') {
                return label || t('catalog_fallback', 'Katalog');
            }

            const key = String(record.country_key || record.region_key || '').toLowerCase();
            if (key === 'international') {
                return t('catalog_world', 'Dünya');
            }
            const regionKeys = {
                europe: 'europe', south_america: 'south_america', north_america: 'north_america',
                north_central_america: 'north_central_america', africa: 'africa', asia: 'asia',
                oceania: 'oceania', australia_oceania: 'oceania', domestic_europe: 'domestic_europe',
                domestic_americas: 'domestic_americas', other: 'other',
            };
            if ((record.node_type === 'region' || (record.region_key && !record.country_key)) && regionKeys[key]) {
                return t('catalog_region_' + regionKeys[key], label);
            }

            const subdivisionLabels = {
                england: 'catalog_country_england', scotland: 'catalog_country_scotland',
                wales: 'catalog_country_wales', northern_ireland: 'catalog_country_northern_ireland',
            };
            if (subdivisionLabels[key]) {
                return t(subdivisionLabels[key], label);
            }

            const iso2 = String(record.iso2 || record.country_code || '').trim().toUpperCase();
            if (/^[A-Z]{2}$/.test(iso2) && typeof Intl.DisplayNames === 'function') {
                return new Intl.DisplayNames([i18nLocale], { type: 'region' }).of(iso2) || label;
            }

            return label || t('catalog_fallback', 'Katalog');
        }

        function catalogRegionMarkHtml(record) {
            const key = String(record.region_key || '').toLowerCase();
            const marks = {
                africa: ['fa-earth-africa', 'africa'],
                asia: ['fa-earth-asia', 'asia'],
                australia_oceania: ['fa-earth-oceania', 'oceania'],
                domestic_americas: ['fa-trophy', 'leagues'],
                domestic_europe: ['fa-trophy', 'leagues'],
                europe: ['fa-earth-europe', 'europe'],
                international: ['fa-globe', 'international'],
                north_america: ['fa-earth-americas', 'americas'],
                other: ['fa-compass', 'other'],
                south_america: ['fa-earth-americas', 'americas'],
            };
            const mark = marks[key] || marks.international;

            return `<span class="sb2-catalog-region-mark sb2-catalog-region-mark--${mark[1]}" aria-hidden="true"><i class="fas ${mark[0]}"></i></span>`;
        }

        function catalogCountryMarkHtml(record) {
            const iso2 = String(record.iso2 || record.country_code || '').trim().toUpperCase();
            if (catalogFlagEmoji(record) === '🌐') {
                return catalogRegionMarkHtml({ region_key: 'international' });
            }

            return `<img class="sb2-catalog-country-flag" src="/dist/img/flags/${iso2.toLowerCase()}.svg" alt="" loading="lazy" decoding="async">`;
        }

        function catalogFlagEmoji(record) {
            const iso2 = String(record.iso2 || record.country_code || '').trim().toUpperCase();
            // ISO 3166-1 countries/territories only; subdivisions and unknown codes stay neutral.
            const countryCodes = 'AD AE AF AG AI AL AM AO AQ AR AS AT AU AW AX AZ BA BB BD BE BF BG BH BI BJ BL BM BN BO BQ BR BS BT BV BW BY BZ CA CC CD CF CG CH CI CK CL CM CN CO CR CU CV CW CX CY CZ DE DJ DK DM DO DZ EC EE EG EH ER ES ET FI FJ FK FM FO FR GA GB GD GE GF GG GH GI GL GM GN GP GQ GR GS GT GU GW GY HK HM HN HR HT HU ID IE IL IM IN IO IQ IR IS IT JE JM JO JP KE KG KH KI KM KN KP KR KW KY KZ LA LB LC LI LK LR LS LT LU LV LY MA MC MD ME MF MG MH MK ML MM MN MO MP MQ MR MS MT MU MV MW MX MY MZ NA NC NE NF NG NI NL NO NP NR NU NZ OM PA PE PF PG PH PK PL PM PN PR PS PT PW PY QA RE RO RS RU RW SA SB SC SD SE SG SH SI SJ SK SL SM SN SO SR SS ST SV SX SY SZ TC TD TF TG TH TJ TK TL TM TN TO TR TT TV TW TZ UA UG UM US UY UZ VA VC VE VG VI VN VU WF WS YE YT ZA ZM ZW'.split(' ');

            if (!countryCodes.includes(iso2)) {
                return '🌐';
            }

            return String.fromCodePoint(
                iso2.charCodeAt(0) - 65 + 0x1F1E6,
                iso2.charCodeAt(1) - 65 + 0x1F1E6
            );
        }

        function catalogMeta(record) {
            if ((record.node_type || '') === 'region') {
                return formatCountLabel(record.country_count || 0) + ' ' + t('countries', 'ülke') + ' • ' + formatCountLabel(record.competition_count || 0) + ' ' + t('tournaments', 'turnuva');
            }

            if ((record.node_type || '') === 'country') {
                return formatCountLabel(record.competition_count || 0) + ' ' + t('tournaments', 'turnuva');
            }

            return [record.tier || '', record.public_surface || ''].filter(Boolean).join(' • ');
        }

        function catalogStatusLabel() {
            if (!catalogPayload) {
                return t('catalog_loading', 'Katalog yükleniyor...');
            }

            if (catalogPath.length === 0) {
                return t('select_region', 'Bölge seç');
            }

            if (catalogPath.length === 1) {
                return t('select_country', 'Ülke seç');
            }

            return t('select_tournament', 'Turnuva seç');
        }

        function navigateCatalogBack() {
            if (!catalogPath.length) {
                return;
            }

            catalogPath = catalogPath.slice(0, -1);
            renderCatalogModal();
        }

        function updateEmptyStateCopy() {
            if (!emptyStateTitle || !emptyStateText) {
                return;
            }

            hideFilterRetryAction();

            if (activePrimaryTab === 'in-game') {
                emptyStateTitle.textContent = t('no_live_events', 'Şu anda gösterilecek canlı etkinlik yok.');
                emptyStateText.textContent = t('live_rows_hint', 'Canlıya düşen maçlar burada sayfa yenilenmeden görünür.');
                return;
            }

            if (activeInterestPriorityMode) {
                emptyStateTitle.textContent = t('featured_empty_title', 'Öne çıkan maç bulunamadı.');
                emptyStateText.textContent = t('featured_empty_body', 'Uygun maçlar geldiğinde burada önem sırasıyla görünür.');
                return;
            }

            if (activeTimePriorityMode) {
                emptyStateTitle.textContent = t('next_empty_title', 'Sıradaki maç bulunamadı.');
                emptyStateText.textContent = t('next_empty_body', 'Yaklaşan maçlar geldiğinde burada zaman sırasıyla görünür.');
                return;
            }

            emptyStateTitle.textContent = t('no_matching_events', 'Eşleşen etkinlik bulunamadı.');
            emptyStateText.textContent = t('no_matching_events_hint', 'Arama kelimesini veya turnuva filtresini temizleyip yeniden deneyin.');
        }

        function filterRetryActionButton() {
            if (!filterEmptyState) {
                return null;
            }

            let button = filterEmptyState.querySelector('[data-filter-retry]');
            if (!button) {
                button = document.createElement('button');
                button.type = 'button';
                button.className = 'sb2-mini-action';
                button.dataset.filterRetry = '1';
                button.textContent = t('retry', 'Tekrar dene');
                button.addEventListener('click', function () {
                    hideFilterRetryAction();
                    startCatalogSelectionLoading();
                    queueInteractiveLiveRefresh(0, {
                        filters: currentLiveOfferFilters(),
                        interrupt: true,
                    });
                });
                filterEmptyState.appendChild(button);
            }

            return button;
        }

        function showFilterRetryAction() {
            const button = filterRetryActionButton();
            if (button) {
                button.hidden = false;
            }
        }

        function hideFilterRetryAction() {
            if (!filterEmptyState) {
                return;
            }

            const button = filterEmptyState.querySelector('[data-filter-retry]');
            if (button) {
                button.hidden = true;
            }
        }

        function rowSearchText(shellEvent) {
            return [
                shellEvent.league_title || '',
                shellEvent.sport_label || '',
                shellEvent.event_name || '',
                shellEvent.home || '',
                shellEvent.away || '',
            ].filter(Boolean).join(' ');
        }

        function textMatchesAny(value, needles) {
            const haystack = String(value || '').toLocaleLowerCase('tr-TR');

            return needles.some(function (needle) {
                return needle !== '' && haystack.indexOf(needle) !== -1;
            });
        }

        function extraMarketsHaveKind(extraMarkets, needles) {
            return extraMarkets.some(function (market) {
                const selections = Array.isArray(market.selections) ? market.selections : [];

                if (textMatchesAny(market.title || market.market_type || '', needles)) {
                    return true;
                }

                return selections.some(function (selection) {
                    return textMatchesAny(selection.market_type || '', needles)
                        || textMatchesAny(selection.market_name || '', needles)
                        || textMatchesAny(selection.label || '', needles);
                });
            });
        }

        function marketFamilyMeta(market, sportKey) {
            if (market && market.market_family_key) {
                const label = market.market_family_label || market.title || market.market_family_key;

                return {
                    key: market.market_family_key,
                    chip: market.market_family_chip || label,
                    label: label,
                    priority: Number(market.market_family_order || 999),
                    shell: market.is_catalog_shell === true,
                };
            }

            const selections = Array.isArray(market.selections) ? market.selections : [];
            const textParts = [market.title || '', market.market_type || ''];
            selections.forEach(function (selection) {
                textParts.push(
                    selection.market_type || '',
                    selection.market_name || '',
                    selection.label || '',
                    selection.outcome_code || ''
                );
            });
            const hasAny = function (needles) {
                return textMatchesAny(textParts.join(' '), needles);
            };

            if (hasAny(['player', 'oyuncu'])) {
                return { key: 'player_markets', chip: t('markets.player', 'Oyuncu'), label: t('markets.player_markets', 'Oyuncu marketleri'), priority: 90, shell: true };
            }

            if (sportKey === 'basketball') {
                if (hasAny(['quarter', 'ceyrek'])) return { key: 'quarters', chip: t('markets.quarters', 'Çeyrekler'), label: t('markets.quarters', 'Çeyrekler'), priority: 50 };
                if (hasAny(['half', 'ilk yarı', 'ilk yari', '1st half', 'first half'])) return { key: 'first_half', chip: t('markets.first_half', 'İlk Yarı'), label: t('markets.first_half', 'İlk Yarı'), priority: 40 };
                if (hasAny(['margin', 'winning margin', 'kazanma marjı', 'kazanma marji'])) return { key: 'margin', chip: t('markets.margin', 'Marj'), label: t('markets.winning_margin', 'Kazanma Marjı'), priority: 60 };
                if (hasAny(['team total', 'team_total', 'takım toplam', 'takim toplam'])) return { key: 'team_total', chip: t('markets.team', 'Takım'), label: t('markets.team_total', 'Takım Toplamı'), priority: 35 };
                if (hasAny(['spread', 'handicap', 'handikap'])) return { key: 'handicap', chip: t('markets.handicap', 'Handikap'), label: t('markets.handicap', 'Handikap'), priority: 30 };
                if (hasAny(['total', 'toplam', 'points'])) return { key: 'totals', chip: t('markets.total', 'Toplam'), label: t('markets.total_points', 'Toplam Sayı'), priority: 20 };
                if (hasAny(['match_result', 'h2h', 'winner', 'moneyline'])) return { key: 'main', chip: t('market_main_chip', 'Ana'), label: t('market_match_result', 'Maç Sonucu'), priority: 10 };
                return { key: 'other', chip: t('sport_other', 'Diğer'), label: t('other_markets', 'Diğer Marketler'), priority: 80 };
            }

            if (sportKey === 'tennis') {
                if (hasAny(['tie break', 'tie-break', 'tiebreak'])) return { key: 'tiebreak', chip: 'Tie-break', label: 'Tie-break', priority: 70 };
                if (hasAny(['set score', 'set_score'])) return { key: 'set_score', chip: t('markets.set_score', 'Set Skoru'), label: t('markets.set_score', 'Set Skoru'), priority: 60 };
                if (hasAny(['first set', '1st set', 'ilk set'])) return { key: 'first_set', chip: t('markets.first_set', 'İlk Set'), label: t('markets.first_set', 'İlk Set'), priority: 50 };
                if (hasAny(['set'])) return { key: 'sets', chip: t('markets.sets', 'Setler'), label: t('markets.sets', 'Setler'), priority: 45 };
                if (hasAny(['spread', 'handicap', 'handikap'])) return { key: 'handicap', chip: t('markets.handicap', 'Handikap'), label: t('game_handicap', 'Game handikap'), priority: 30 };
                if (hasAny(['total', 'toplam', 'games'])) return { key: 'totals', chip: t('markets.total', 'Toplam'), label: t('markets.total_games', 'Toplam Oyun'), priority: 20 };
                if (hasAny(['match_winner', 'match winner', 'h2h', 'winner'])) return { key: 'main', chip: t('market_main_chip', 'Ana'), label: t('market_match_winner', 'Maç Kazananı'), priority: 10 };
                return { key: 'other', chip: t('sport_other', 'Diğer'), label: t('other_markets', 'Diğer Marketler'), priority: 80 };
            }

            if (hasAny(['corner', 'korner'])) return { key: 'corners', chip: t('markets.corners', 'Kornerler'), label: t('markets.corners', 'Kornerler'), priority: 70 };
            if (hasAny(['card', 'kart'])) return { key: 'cards', chip: t('markets.cards', 'Kartlar'), label: t('markets.cards', 'Kartlar'), priority: 75 };
            if (hasAny(['correct score', 'correct_score', 'doğru skor', 'dogru skor'])) return { key: 'correct_score', chip: t('markets.score', 'Skor'), label: t('markets.correct_score', 'Doğru Skor'), priority: 60 };
            if (hasAny(['half', 'ilk yarı', 'ilk yari', '1st half', 'first half'])) return { key: 'first_half', chip: t('markets.first_half', 'İlk Yarı'), label: t('markets.first_half', 'İlk Yarı'), priority: 50 };
            if (hasAny(['both teams', 'btts', 'karşılıklı', 'karsilikli'])) return { key: 'btts', chip: t('markets.btts_short', 'KG Var'), label: t('markets.btts', 'Karşılıklı Gol'), priority: 45 };
            if (hasAny(['double chance', 'double_chance', 'çifte şans', 'cifte sans'])) return { key: 'double_chance', chip: t('markets.double_chance', 'Çifte Şans'), label: t('markets.double_chance', 'Çifte Şans'), priority: 40 };
            if (hasAny(['spread', 'handicap', 'handikap'])) return { key: 'handicap', chip: t('markets.handicap', 'Handikap'), label: t('markets.handicap', 'Handikap'), priority: 30 };
            if (hasAny(['total', 'toplam', 'goals'])) return { key: 'totals', chip: t('markets.total', 'Toplam'), label: t('markets.total', 'Toplam'), priority: 20 };
            if (hasAny(['match_result', 'h2h', 'winner', '1x2'])) return { key: 'main', chip: t('market_main_chip', 'Ana'), label: t('market_match_result', 'Maç Sonucu'), priority: 10 };

            return { key: 'other', chip: t('sport_other', 'Diğer'), label: t('other_markets', 'Diğer Marketler'), priority: 80 };
        }

        function groupExtraMarketsByFamily(extraMarkets, shellEvent) {
            const groupsByKey = new Map();
            const sportKey = shellEvent.sport_group_key || '';

            extraMarkets.forEach(function (market) {
                const meta = marketFamilyMeta(market, sportKey);
                const selections = Array.isArray(market.selections) ? market.selections : [];
                const allCatalogShells = selections.length > 0 && selections.every(function (selection) {
                    return selection.is_catalog_shell === true || selection.acceptance_mode === 'catalog_shell';
                });
                const hasActionableOffer = selections.some(function (selection) {
                    return selection.selectable === true && selectionPresentationReady(selection);
                });

                if (!groupsByKey.has(meta.key)) {
                    groupsByKey.set(meta.key, {
                        key: meta.key,
                        chip: meta.chip,
                        label: meta.label,
                        priority: meta.priority,
                        isShellFamily: allCatalogShells,
                        hasActionableOffer: false,
                        markets: [],
                    });
                }

                const group = groupsByKey.get(meta.key);
                group.markets.push(market);
                group.hasActionableOffer = group.hasActionableOffer || hasActionableOffer;
                group.isShellFamily = group.isShellFamily && allCatalogShells;
            });

            return Array.from(groupsByKey.values()).sort(function (left, right) {
                if (left.hasActionableOffer !== right.hasActionableOffer) {
                    return left.hasActionableOffer ? -1 : 1;
                }
                const byPriority = Number(left.priority || 0) - Number(right.priority || 0);
                if (byPriority !== 0) {
                    return byPriority;
                }

                return String(left.label || '').localeCompare(String(right.label || ''), 'tr');
            });
        }

        function marketHeadingItems(shellEvent) {
            const sportKey = shellEvent.sport_group_key || 'other';
            const matchResult = Array.isArray(shellEvent.match_result) ? shellEvent.match_result : [];
            const totals = Array.isArray(shellEvent.totals) ? shellEvent.totals : [];
            const extraMarkets = Array.isArray(shellEvent.extra_markets) ? shellEvent.extra_markets : [];
            const headings = [];

            if (matchResult.length) {
                headings.push({
                    label: sportKey === 'tennis' ? t('market_tennis_winner', 'Maç kazananı') : t('market_result_lower', 'Maç sonucu'),
                    span: Math.min(3, Math.max(1, matchResult.length)),
                    secondary: false,
                });
            }

            if (totals.length) {
                headings.push({
                    label: sportKey === 'basketball'
                        ? t('markets.total_points', 'Toplam sayı')
                        : (sportKey === 'tennis' ? t('markets.total_games', 'Toplam oyun') : t('markets.total', 'Toplam')),
                    span: Math.min(2, Math.max(1, totals.length)),
                    secondary: false,
                });
            }

            if (extraMarkets.length) {
                const hasHandicap = extraMarketsHaveKind(extraMarkets, ['spread', 'spreads', 'handicap', 'handikap']);
                headings.push({
                    label: sportKey === 'basketball' && hasHandicap
                        ? t('markets.handicap', 'Handikap')
                        : (sportKey === 'tennis' && hasHandicap ? t('game_handicap', 'Game handikap') : t('extra_markets', 'Ek marketler')),
                    span: 1,
                    secondary: true,
                });
            }

            return headings;
        }

        function marketHeadingsHtml(shellEvent) {
            const headings = marketHeadingItems(shellEvent);

            return `
                <div class="sb2-market-headings" data-market-headings>
                    ${headings.map(function (heading) {
                        return `<div class="sb2-market-heading${heading.secondary ? ' is-secondary' : ''}" data-heading-span="${heading.span}">${escapeHtml(heading.label)}</div>`;
                    }).join('')}
                </div>
            `;
        }

        function totalSelectionLabel(selection) {
            if (!['total_goals', 'total_points', 'total_games'].includes(String(selection.market_type || ''))) {
                return '';
            }

            const side = String(selection.outcome_code || '');
            const rawLine = selection.market_line;
            if (!['over', 'under'].includes(side) || rawLine === null || rawLine === undefined || String(rawLine).trim() === '') {
                return '';
            }

            const line = Number(String(rawLine).replace(',', '.'));
            return Number.isFinite(line) ? t('markets.' + side, side) + ' ' + String(line) : '';
        }

        function normalizedWinxEdgePct(selection) {
            const value = Number(selection && selection.winx_edge_pct);
            return Number.isFinite(value) && value >= 2 && value <= 100
                ? Math.round(value * 10) / 10
                : null;
        }

        function syncWinxEdgeBadge(button, rawEdgePct) {
            if (!button) return;
            const edgePct = normalizedWinxEdgePct({ winx_edge_pct: rawEdgePct });
            const value = button.querySelector('.sb2-odd-box-value');
            let wrap = button.querySelector('.sb2-odd-box-value-wrap');
            let edge = button.querySelector('.sb2-winx-edge');

            if (edgePct === null) {
                if (edge) edge.remove();
                if (wrap && value && !wrap.querySelector('.sb2-winx-edge')) {
                    wrap.replaceWith(value);
                }
                return;
            }

            if (!value) return;
            if (!wrap) {
                wrap = document.createElement('span');
                wrap.className = 'sb2-odd-box-value-wrap';
                value.replaceWith(wrap);
                wrap.appendChild(value);
            }
            if (!edge) {
                edge = document.createElement('span');
                edge.className = 'sb2-winx-edge';
                wrap.insertBefore(edge, value);
            }
            edge.textContent = `EDGE +${edgePct.toFixed(1)}%`;
            edge.title = t('markets.winx_edge_tooltip', 'WinX Edge — Bu markette daha düşük marj uyguluyoruz.');
            edge.setAttribute('aria-label', `WinX Edge +${edgePct.toFixed(1)}%`);
        }

        function selectionPresentationReady(selection) {
            const visibleLabel = String(selection.label || '').trim();
            if (visibleLabel === '' || visibleLabel === '-') {
                if (!totalSelectionLabel(selection)) return false;
            }

            if (!['total_goals', 'total_points', 'total_games'].includes(String(selection.market_type || ''))) {
                return true;
            }

            const price = Number(String(selection.raw_odd ?? selection.odd ?? '').replace(',', '.'));
            const hasIdentity = Boolean(selection.event_id && selection.market_type && selection.outcome_code
                && (selection.selection_key || selection.quote_selection_id || selection.selection_token || selection.client_selection_ref));
            return totalSelectionLabel(selection) !== '' && hasIdentity && Number.isFinite(price) && price > 1;
        }

        function selectionButtonHtml(item, shellEvent, compact) {
            const payload = selectionPayloadForButton(item, shellEvent);
            const presentationReady = selectionPresentationReady(payload);
            const demoSelectable = offlineDemo.active && item.acceptance_mode === 'offline_demo' && item.demo_selectable === true;
            const disabled = (!demoSelectable && item.selectable === false) || !presentationReady;
            const totalLabel = totalSelectionLabel(payload);
            if (totalLabel) {
                payload.label = totalLabel;
                payload.outcome_name = totalLabel;
            }
            payload.selectable = demoSelectable ? false : !disabled;
            const isCatalogShell = item.is_catalog_shell === true || item.acceptance_mode === 'catalog_shell';
            const classes = [
                'sb2-odd-box',
                compact ? 'sb2-odd-box--compact' : '',
                ['total_goals', 'total_points', 'total_games'].includes(item.market_type || '') ? 'sb2-odd-box--total' : '',
                isCatalogShell ? 'sb2-odd-box--catalog-shell' : '',
            ].filter(Boolean).join(' ');
            const disabledTitle = !presentationReady
                ? t('odds_resolving', 'Oranlar doğrulanıyor…')
                : (item.selection_state_message || t('event_unavailable', 'Oranlar şu anda kullanılamıyor.'));
            const rawEdgePct = Number(item.winx_edge_pct);
            const edgePct = Number.isFinite(rawEdgePct) && rawEdgePct >= 2 && rawEdgePct <= 100
                ? Math.round(rawEdgePct * 10) / 10
                : null;
            const oddHtml = `<span class="sb2-odd-box-value">${escapeHtml(item.odd || '-')}</span>`;
            const valueHtml = edgePct === null
                ? oddHtml
                : `<span class="sb2-odd-box-value-wrap"><span class="sb2-winx-edge" title="${escapeHtml(t('markets.winx_edge_tooltip', 'WinX Edge — Bu markette daha düşük marj uyguluyoruz.'))}" aria-label="${escapeHtml(`WinX Edge +${edgePct.toFixed(1)}%`)}">${escapeHtml(`EDGE +${edgePct.toFixed(1)}%`)}</span>${oddHtml}</span>`;

            return `
                <button
                    type="button"
                    class="${classes}${disabled ? ' is-disabled' : ''}"
                    data-selection-trigger
                    data-selection="${escapeHtml(JSON.stringify(payload))}"
                    data-event-id="${escapeHtml(shellEvent.event_id)}"
                    data-market-type="${escapeHtml(item.market_type || '')}"
                    data-market-specifier="${escapeHtml(item.market_specifier_key || 'default')}"
                    data-outcome-code="${escapeHtml(item.outcome_code || '')}"
                    ${disabled ? `title="${escapeHtml(disabledTitle)}" disabled aria-disabled="true"` : ''}
                >
                    <span class="sb2-odd-box-label">${escapeHtml(totalLabel || item.label || '-')}</span>
                    ${valueHtml}
                </button>
            `;
        }

        function extraMarketHtml(market, shellEvent) {
            const selections = Array.isArray(market.selections) ? market.selections : [];
            if (!selections.length) {
                return '';
            }

            return `
                <div class="sb2-extra-market">
                    <div class="sb2-extra-market-title">${escapeHtml(market.title || t('market_fallback', 'Diğer market'))}</div>
                    <div class="sb2-extra-market-grid">
                        ${selections.map(function (item) {
                            return selectionButtonHtml(item, shellEvent, true);
                        }).join('')}
                    </div>
                </div>
            `;
        }

        function marketFamilyHtml(group, shellEvent) {
            const shellClass = group.isShellFamily ? ' is-shell-family' : '';

            return `
                <section class="sb2-extra-market-family${shellClass}" data-market-family-section="${escapeHtml(group.key)}">
                    <div class="sb2-extra-market-family-header">
                        <div>
                            <span>${escapeHtml(group.label || t('other_markets', 'Diğer Marketler'))}</span>
                            ${group.isShellFamily ? '<em>' + escapeHtml(t('catalog_markets_passive', 'Henüz bahis kabul etmeyen katalog marketleri')) + '</em>' : ''}
                        </div>
                        <strong>${escapeHtml(t('markets.count', ':count market', { count: group.markets.length }))}</strong>
                    </div>
                    <div class="sb2-extra-market-family-grid">
                        ${group.markets.map(function (market) {
                            return extraMarketHtml(market, shellEvent);
                        }).join('')}
                    </div>
                </section>
            `;
        }

        function extraMarketsPanelHtml(extraMarkets, shellEvent) {
            if (!extraMarkets.length) {
                return '';
            }

            const groups = groupExtraMarketsByFamily(extraMarkets, shellEvent);

            return `
                <div class="sb2-extra-markets" data-extra-markets hidden>
                    <div class="sb2-extra-markets-header">
                        <div>
                            <span>${escapeHtml(t('extra_markets', 'Ek marketler'))}</span>
                            <small>${escapeHtml(shellEvent.home || '')} vs ${escapeHtml(shellEvent.away || '')}</small>
                        </div>
                        <strong>${escapeHtml(t('markets.count', ':count market', { count: extraMarkets.length }))}</strong>
                    </div>
                    <div class="sb2-market-family-tabs" data-market-family-tabs aria-label="${escapeHtml(t('markets.families', 'Market aileleri'))}">
                        <button type="button" class="sb2-market-family-chip is-active" data-market-family-chip="all" aria-pressed="true">${escapeHtml(t('markets.all', 'Tümü'))}</button>
                        ${groups.map(function (group) {
                            return `
                                <button
                                    type="button"
                                    class="sb2-market-family-chip${group.isShellFamily ? ' is-shell-family' : ''}"
                                    data-market-family-chip="${escapeHtml(group.key)}"
                                    aria-pressed="false"
                                >
                                    ${escapeHtml(group.chip || group.label || t('sport_other', 'Diğer'))}
                                    <span>${escapeHtml(String(group.markets.length))}</span>
                                </button>
                            `;
                        }).join('')}
                    </div>
                    <div class="sb2-extra-market-list">
                        ${groups.map(function (group) {
                            return marketFamilyHtml(group, shellEvent);
                        }).join('')}
                    </div>
                </div>
            `;
        }

        function detailMarketsForExtraPanel(shellEvent) {
            const detailMarkets = [];
            const totals = Array.isArray((shellEvent || {}).totals) ? shellEvent.totals : [];
            const extraMarkets = Array.isArray((shellEvent || {}).extra_markets) ? shellEvent.extra_markets : [];

            if (totals.length) {
                detailMarkets.push({
                    title: t('markets.total', 'Toplam'),
                    market_type: totals[0].market_type || 'total_goals',
                    market_family_key: 'totals',
                    market_family_chip: t('markets.total', 'Toplam'),
                    market_family_label: t('markets.total', 'Toplam'),
                    market_family_order: 20,
                    selections: totals,
                });
            }

            return detailMarkets.concat(extraMarkets);
        }

        function extraMarketsLoadingPanelHtml(row, message) {
            const home = row ? (row.querySelector('.sb2-team-line:first-child span:last-child')?.textContent || row.querySelector('.sb2-team-lines div:first-child')?.textContent || '') : '';
            const away = row ? (row.querySelector('.sb2-team-line:nth-child(2) span:last-child')?.textContent || row.querySelector('.sb2-team-lines div:nth-child(2)')?.textContent || '') : '';

            return `
                <div class="sb2-extra-markets sb2-extra-markets--loading" data-extra-markets data-extra-markets-loading>
                    <div class="sb2-extra-markets-header">
                        <div>
                            <span>${escapeHtml(t('extra_markets', 'Ek marketler'))}</span>
                            <small>${escapeHtml(home)} vs ${escapeHtml(away)}</small>
                        </div>
                        <strong>${escapeHtml(t('preparing', 'Hazırlanıyor'))}</strong>
                    </div>
                    <div class="sb2-extra-market-loader" role="status" aria-live="polite">
                        <span aria-hidden="true"></span>
                        ${escapeHtml(message || t('market_details_loading', 'Market detayları yükleniyor.'))}
                    </div>
                </div>
            `;
        }

        function renderLazyExtraMarketsPanel(row, shellEvent) {
            const oddsArea = row ? row.querySelector('.sb2-odds-area') : null;
            const extraMarkets = detailMarketsForExtraPanel(shellEvent || {});

            if (!oddsArea || !extraMarkets.length) {
                return null;
            }

            const existing = oddsArea.querySelector('[data-extra-markets]');
            if (existing) {
                existing.remove();
            }

            oddsArea.insertAdjacentHTML('beforeend', extraMarketsPanelHtml(extraMarkets, shellEvent));

            const panel = oddsArea.querySelector('[data-extra-markets]');
            if (!panel) {
                return null;
            }

            bindSelectionTriggers(panel);
            refreshSelectionTriggers();
            bindMarketFamilyChips(panel);
            return panel;
        }

        function renderLazyExtraMarketsLoadingPanel(row, message) {
            const oddsArea = row ? row.querySelector('.sb2-odds-area') : null;

            if (!oddsArea) {
                return null;
            }

            const existing = oddsArea.querySelector('[data-extra-markets]');
            if (existing) {
                existing.remove();
            }

            oddsArea.insertAdjacentHTML('beforeend', extraMarketsLoadingPanelHtml(row, message));

            return oddsArea.querySelector('[data-extra-markets]');
        }

        function buildMatchRowElement(shellEvent) {
            const row = document.createElement('div');
            const totals = Array.isArray(shellEvent.totals) ? shellEvent.totals : [];
            const matchResult = Array.isArray(shellEvent.match_result) ? shellEvent.match_result : [];
            const extraMarkets = Array.isArray(shellEvent.extra_markets) ? shellEvent.extra_markets : [];
            const extraMarketCount = Math.max(Number(shellEvent.extra_market_count || extraMarkets.length || 0), 0);
            const hasExtraMarketDetails = extraMarkets.length > 0 || extraMarketCount > 0 || shellEvent.markets_lazy === true;
            const moreLabel = shellEvent.more || ('+' + extraMarketCount);
            const phaseKey = shellEvent.phase_key || 'pre-match';
            const phaseLabel = shellEvent.phase_label || (phaseKey === 'in-game' ? t('phase_live', 'Canlı') : 'Pre-match');
            const rowNoticeState = shellEvent.row_notice_state || sportsbookRowNoticeState(
                phaseKey, String(shellEvent.trading_status || '').toLowerCase(), shellEvent.is_tradeable === true, false
            );

            rememberExpandedShellEvent(shellEvent);

            row.className = 'sb2-match-row';
            row.dataset.eventRow = '';
            row.dataset.eventId = String(shellEvent.event_id || '');
            row.dataset.offlineDemo = shellEvent.offline_demo ? '1' : '0';
            row.dataset.eventName = shellEvent.event_name || '';
            row.dataset.noticeState = rowNoticeState;
            row.dataset.eventPhase = phaseKey;
            row.dataset.eventLifecycleStatus = shellEvent.lifecycle_status || '';
            row.dataset.eventTradingStatus = shellEvent.trading_status || '';
            row.dataset.startsAt = String(shellEvent.starts_at_timestamp || '');
            row.dataset.sportKey = shellEvent.sport_group_key || 'other';
            row.dataset.leagueFilterKey = shellEvent.league_filter_key || 'turnuva';
            row.dataset.searchText = rowSearchText(shellEvent);
            row.hidden = phaseKey !== activePhaseForTab();

            row.innerHTML = `
                <div class="sb2-match-meta">
                    <div class="sb2-time-pill">
                        <span class="sb2-time-fire" aria-hidden="true"></span>
                        <span data-time-pill-label>${escapeHtml(phaseKey === 'in-game' ? t('phase_live', 'Canlı') : (shellEvent.time || '--:--') + ' • ' + (shellEvent.date || '--.--.----'))}</span>
                    </div>

                    <div class="sb2-match-tags">
                        <span class="sb2-match-tag">${escapeHtml(shellEvent.league_title || t('league_fallback', 'Lig'))}</span>
                        <span class="sb2-match-tag sb2-match-tag--phase${phaseKey === 'in-game' ? ' is-live' : ''}" data-event-phase-badge>${escapeHtml(phaseLabel)}</span>
                    </div>

                    <div class="sb2-team-lines">
                        <div class="sb2-team-line">
                            ${entityMarkHtml(shellEvent.home_team_asset || {}, shellEvent.home || '', 'team')}
                            <span>${escapeHtml(shellEvent.home || '')}</span>
                        </div>
                        <div class="sb2-team-line">
                            ${entityMarkHtml(shellEvent.away_team_asset || {}, shellEvent.away || '', 'team')}
                            <span>${escapeHtml(shellEvent.away || '')}</span>
                        </div>
                    </div>
                </div>

                <div class="sb2-odds-area">
                    ${marketHeadingsHtml(shellEvent)}

                    <div class="sb2-betting-closed-note" data-betting-closed-note role="status" aria-live="polite"${rowNoticeState === 'none' ? ' hidden' : ''}>
                        ${escapeHtml(rowNoticeCopy(rowNoticeState))}
                    </div>

                    <div class="sb2-odds-grid">
                        ${matchResult.map(function (item) { return selectionButtonHtml(item, shellEvent, false); }).join('')}
                        ${totals.map(function (item) { return selectionButtonHtml(item, shellEvent, false); }).join('')}
                        ${hasExtraMarketDetails ? `<button type="button" class="sb2-more-box" data-more-toggle data-more-label="${escapeHtml(moreLabel)}" aria-expanded="false" aria-label="${escapeHtml(t('open_extra_markets', 'Ek marketleri aç: :label', { label: moreLabel }))}">${escapeHtml(moreLabel)}</button>` : ''}
                    </div>
                </div>
            `;

            bindSelectionTriggers(row);
            bindMoreToggleButtons(row);
            bindMarketFamilyChips(row);

            return row;
        }

        function buildLeagueNodeElement(shellEvent) {
            const league = document.createElement('div');
            league.className = 'sb2-league is-open';
            league.dataset.sportGroupKey = shellEvent.sport_group_key || 'other';
            league.dataset.leagueFilterKey = shellEvent.league_filter_key || 'turnuva';
            league.dataset.leagueTitle = shellEvent.league_title || 'Lig';
            league.innerHTML = `
                <button type="button" class="sb2-league-header" data-accordion-trigger aria-expanded="true">
                    <div class="sb2-league-head-left">
                        <div class="sb2-league-badge">${entityMarkHtml(shellEvent.competition_asset || {}, shellEvent.league_title || 'Lig', 'competition')}</div>
                        <div class="sb2-league-meta">
                            <div class="sb2-league-title">${escapeHtml(shellEvent.league_title || 'Lig')}</div>
                            <div class="sb2-league-sport">${escapeHtml(shellEvent.sport_label || sportLabelFromKey(shellEvent.sport_group_key || 'other'))}</div>
                        </div>
                    </div>

                    <span class="sb2-league-chevron" aria-hidden="true">
                        <svg viewBox="0 0 24 24">
                            <path d="M6 14L12 8L18 14" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"></path>
                        </svg>
                    </span>
                </button>

                <div class="sb2-league-body"></div>
                <div class="sb2-league-placeholder" data-league-placeholder hidden aria-hidden="true"></div>
            `;

            bindAccordionTriggers(league);
            return league;
        }

        function ensureTimePriorityLeagueNode() {
            const container = document.querySelector('.sb2-leagues');
            if (!container) {
                return null;
            }

            const activeMeta = interestPriorityActiveFor(activePhaseForTab())
                ? {
                    league_filter_key: '__interest_priority_featured',
                    league_title: t('featured_title', 'Öne çıkanlar'),
                    league_badge: t('featured_badge', 'ÖNE'),
                }
                : {
                    league_filter_key: '__time_priority_next',
                    league_title: t('next_matches_title', 'Sıradaki maçlar'),
                    league_badge: t('next_badge', 'SIR'),
                };

            if (timePriorityLeagueNode && timePriorityLeagueNode.isConnected) {
                const title = timePriorityLeagueNode.querySelector('.sb2-league-title');
                const badge = timePriorityLeagueNode.querySelector('.sb2-league-badge');
                timePriorityLeagueNode.dataset.leagueFilterKey = activeMeta.league_filter_key;
                if (title) {
                    title.textContent = activeMeta.league_title;
                }
                if (badge) {
                    badge.innerHTML = entityMarkHtml(activeMeta.competition_asset || {}, activeMeta.league_title || activeMeta.league_badge, 'competition');
                }
                return timePriorityLeagueNode;
            }

            timePriorityLeagueNode = buildLeagueNodeElement({
                sport_group_key: 'all',
                league_filter_key: activeMeta.league_filter_key,
                league_title: activeMeta.league_title,
                league_badge: activeMeta.league_badge,
                sport_label: t('all_sports', 'Tüm sporlar'),
            });
            timePriorityLeagueNode.dataset.timePriorityLeague = '1';
            container.prepend(timePriorityLeagueNode);

            return timePriorityLeagueNode;
        }

        function restoreRowsFromTimePriorityLeague() {
            if (!timePriorityLeagueNode || !timePriorityLeagueNode.isConnected) {
                timePriorityLeagueNode = null;
                return false;
            }

            const rows = Array.from(timePriorityLeagueNode.querySelectorAll('[data-event-row]'));
            let moved = false;

            rows.forEach(function (row) {
                const leagueKey = row.dataset.leagueFilterKey || '';
                const targetLeague = leagueKey
                    ? document.querySelector('.sb2-league[data-league-filter-key="' + escapeSelectorValue(leagueKey) + '"]:not([data-time-priority-league])')
                    : null;
                const targetBody = targetLeague ? targetLeague.querySelector('.sb2-league-body') : null;

                if (!targetBody) {
                    return;
                }

                insertRowInLeague(targetLeague, row);
                moved = true;
            });

            timePriorityLeagueNode.remove();
            timePriorityLeagueNode = null;

            return moved || rows.length > 0;
        }

        function syncTimePriorityRowLayout(records) {
            if (!timePriorityActiveFor(activePhaseForTab()) && !interestPriorityActiveFor(activePhaseForTab())) {
                if (restoreRowsFromTimePriorityLeague()) {
                    refreshDynamicCollections();
                    return filteredRowRecords(activePhaseForTab(), activeSportFilter, activeLeagueFilter, activeSearchQuery);
                }

                return records;
            }

            const league = ensureTimePriorityLeagueNode();
            const body = league ? league.querySelector('.sb2-league-body') : null;
            if (!league || !body) {
                return records;
            }

            records.forEach(function (record) {
                if (record && record.row) {
                    body.appendChild(record.row);
                }
            });
            league.hidden = records.length === 0;

            refreshDynamicCollections();

            return filteredRowRecords(activePhaseForTab(), activeSportFilter, activeLeagueFilter, activeSearchQuery);
        }

        function ensureSportFilterButton(shellEvent, options) {
            const nextOptions = options || {};
            if (!sportStrip
                || !shellEvent
                || !isPrimarySportFilterKey(shellEvent.sport_group_key || '')) {
                return;
            }

            if (sportFilterButtonByKey.has(shellEvent.sport_group_key)) {
                return;
            }

            const label = shellEvent.sport_label || sportLabelFromKey(shellEvent.sport_group_key);
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'sb2-sport-card';
            button.dataset.sportFilter = shellEvent.sport_group_key;
            button.setAttribute('role', 'tab');
            button.setAttribute('aria-selected', 'false');
            button.innerHTML = `
                ${sportCardArtHtml(shellEvent.sport_asset || {}, label, shellEvent.sport_group_key || '')}
                <span class="sb2-sport-label">${escapeHtml(label)}</span>
                <span class="sb2-sport-count" data-count-raw="0">0</span>
                <span class="sb2-sport-icon-shell" aria-hidden="true">
                    ${entityMarkHtml(shellEvent.sport_asset || {}, label, 'sport')}
                </span>
            `;
            clearSportRailLoadingPlaceholder();
            sportStrip.appendChild(button);
            sortSportFilterButtonsByPriority();
            allowedSportFilters.add(shellEvent.sport_group_key);
            sportFilterButtonByKey.set(shellEvent.sport_group_key, button);
            bindSportFilterButtons(button);

            if (!nextOptions.deferRefresh) {
                refreshDynamicCollections();
                syncSportScrollButtons();
            }
        }

        function ensureSportFilterButtonFromSummary(sportKey, sportPayload, options) {
            const nextOptions = options || {};
            const normalizedSportKey = String(sportKey || '');
            if (!sportStrip
                || !isPrimarySportFilterKey(normalizedSportKey)
                || sportFilterButtonByKey.has(normalizedSportKey)) {
                return;
            }

            const payload = sportPayload && typeof sportPayload === 'object' ? sportPayload : {};
            const label = payload.label || sportLabelFromKey(normalizedSportKey);
            const preMatchCount = Number(payload.pre_match_count || 0) || 0;
            const inGameCount = Number(payload.in_game_count || 0) || 0;
            const totalCount = preMatchCount + inGameCount;
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'sb2-sport-card';
            button.dataset.sportFilter = normalizedSportKey;
            button.setAttribute('role', 'tab');
            button.setAttribute('aria-selected', 'false');
            button.innerHTML = `
                ${sportCardArtHtml(payload.asset_meta || {}, label, normalizedSportKey)}
                <span class="sb2-sport-label">${escapeHtml(label)}</span>
                <span class="sb2-sport-count" data-count-raw="${totalCount}">${formatCountLabel(totalCount)}</span>
                <span class="sb2-sport-icon-shell" aria-hidden="true">
                    ${entityMarkHtml(payload.asset_meta || {}, label, 'sport')}
                </span>
            `;
            clearSportRailLoadingPlaceholder();
            sportStrip.appendChild(button);
            sortSportFilterButtonsByPriority();
            allowedSportFilters.add(normalizedSportKey);
            sportFilterButtonByKey.set(normalizedSportKey, button);
            bindSportFilterButtons(button);

            if (!nextOptions.deferRefresh) {
                refreshDynamicCollections();
                syncSportScrollButtons();
            }
        }

        function ensureSportFilterButtonsFromSummary(summary, counts) {
            const summarySports = summary && typeof summary === 'object' ? (summary.sports || {}) : {};
            const countedSports = counts && typeof counts === 'object' ? (counts.sport_group_counts || {}) : {};
            const mergedSports = Object.assign({}, summarySports, countedSports);
            let added = false;

            Object.entries(mergedSports).forEach(function (entry) {
                const sportKey = String(entry[0] || '');
                const payload = entry[1] || {};
                const preMatchCount = Number(payload.pre_match_count || 0) || 0;
                const inGameCount = Number(payload.in_game_count || 0) || 0;

                if (preMatchCount + inGameCount > 0 && !sportFilterButtonByKey.has(sportKey)) {
                    ensureSportFilterButtonFromSummary(sportKey, payload, { deferRefresh: true });
                    added = true;
                }
            });

            if (added) {
                refreshDynamicCollections();
                syncSportScrollButtons();
            }
        }

        function refreshSelectionTriggers() {
            triggers = Array.from(document.querySelectorAll('[data-selection-trigger]'));
            bindSelectionTriggers(document);

            return triggers;
        }

        function ensureLeagueFilterGroup(shellEvent, options) {
            const nextOptions = options || {};
            if (!leagueFilterList || !shellEvent || !shellEvent.league_group_key) {
                return null;
            }

            const existingGroup = leagueFilterList.querySelector(
                '[data-league-filter-group="' + escapeSelectorValue(shellEvent.league_group_key) + '"][data-sport-group-key="' + escapeSelectorValue(shellEvent.sport_group_key || 'other') + '"]'
            );
            if (existingGroup) {
                return existingGroup.querySelector('[data-league-filter-group-body]');
            }

            const group = document.createElement('section');
            group.className = 'sb2-tournament-group';
            group.dataset.leagueFilterGroup = shellEvent.league_group_key;
            group.dataset.sportGroupKey = shellEvent.sport_group_key || 'other';
            group.innerHTML = `
                <button type="button" class="sb2-tournament-group-toggle" data-league-filter-group-toggle aria-expanded="false">
                    <span class="sb2-tournament-group-copy">
                        <strong>${escapeHtml(shellEvent.league_group_label || t('tournaments', 'Turnuvalar'))}</strong>
                        <span data-count-raw="0">0 turnuva</span>
                    </span>
                    <span class="sb2-tournament-group-meta">
                        <strong data-league-filter-group-item-count data-count-raw="0">0</strong>
                        <em data-league-filter-group-event-count data-count-raw="0">${escapeHtml(t('events', 'etkinlik'))}</em>
                        <span class="sb2-tournament-group-chevron" aria-hidden="true">
                            <svg viewBox="0 0 24 24">
                                <path d="M6 9L12 15L18 9" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"></path>
                            </svg>
                        </span>
                    </span>
                </button>
                <div class="sb2-tournament-group-body" data-league-filter-group-body hidden></div>
            `;

            leagueFilterList.appendChild(group);
            bindLeagueFilterGroupToggles(group);

            if (!nextOptions.deferRefresh) {
                refreshDynamicCollections();
            }

            return group.querySelector('[data-league-filter-group-body]');
        }

        function ensureLeagueFilterButton(shellEvent, options) {
            const nextOptions = options || {};
            if (!shellEvent || !shellEvent.league_filter_key) {
                return;
            }

            if (leagueFilterButtonByKey.has(shellEvent.league_filter_key)) {
                return;
            }

            const groupBody = ensureLeagueFilterGroup(shellEvent, { deferRefresh: true });
            if (!groupBody) {
                return;
            }

            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'sb2-tournament-chip';
            button.dataset.leagueFilter = shellEvent.league_filter_key;
            button.dataset.leagueFilterLabel = shellEvent.league_title || t('tournament', 'Turnuva');
            button.dataset.sportGroupKey = shellEvent.sport_group_key || 'other';
            button.dataset.leagueFilterGroupKey = shellEvent.league_group_key || 'other';
            button.setAttribute('aria-pressed', 'false');
            button.innerHTML = `
                <span class="sb2-tournament-chip-title">
                    ${entityMarkHtml(shellEvent.competition_asset || {}, shellEvent.league_title || t('tournament', 'Turnuva'), 'competition')}
                    <span>${escapeHtml(shellEvent.league_title || t('tournament', 'Turnuva'))}</span>
                </span>
                <strong>0</strong>
            `;
            groupBody.appendChild(button);
            allowedLeagueFilters.add(shellEvent.league_filter_key);
            leagueFilterButtonByKey.set(shellEvent.league_filter_key, button);
            bindLeagueFilterButtons(button);

            if (!nextOptions.deferRefresh) {
                refreshDynamicCollections();
            }
        }

        function leagueNearestStartsAt(leagueNode) {
            const record = leagueRecordByNode.get(leagueNode);
            const startsAtValues = record
                ? record.rows.map(function (rowRecord) {
                    return Number(rowRecord.startsAt || Number.MAX_SAFE_INTEGER);
                }).filter(Number.isFinite)
                : [];

            return startsAtValues.length ? Math.min.apply(Math, startsAtValues) : Number.MAX_SAFE_INTEGER;
        }

        function sortLeagueNodes() {
            const container = document.querySelector('.sb2-leagues');
            if (!container) {
                return;
            }

            const sortedLeagueNodes = leagueNodes
                .slice()
                .sort(function (left, right) {
                    const byTime = leagueNearestStartsAt(left) - leagueNearestStartsAt(right);
                    if (byTime !== 0) {
                        return byTime;
                    }

                    return String(left.dataset.leagueTitle || '').localeCompare(String(right.dataset.leagueTitle || ''), 'tr');
                });
            const currentLeagueNodes = Array.from(container.children).filter(function (node) {
                return node.classList && node.classList.contains('sb2-league');
            });
            const alreadySorted = sortedLeagueNodes.length === currentLeagueNodes.length
                && sortedLeagueNodes.every(function (league, index) {
                    return currentLeagueNodes[index] === league;
                });

            if (alreadySorted) {
                return;
            }

            sortedLeagueNodes.forEach(function (league) {
                container.appendChild(league);
            });
        }

        function ensureLeagueNode(shellEvent, options) {
            const nextOptions = options || {};
            ensureSportFilterButton(shellEvent, { deferRefresh: true });
            ensureLeagueFilterButton(shellEvent, { deferRefresh: true });

            let league = document.querySelector('.sb2-league[data-league-filter-key="' + escapeSelectorValue(shellEvent.league_filter_key || 'turnuva') + '"]');
            if (!league) {
                const container = document.querySelector('.sb2-leagues');
                if (!container) {
                    return null;
                }

                league = buildLeagueNodeElement(shellEvent);
                container.appendChild(league);
                if (!nextOptions.deferRefresh) {
                    refreshDynamicCollections();
                    sortLeagueNodes();
                }
            }

            return league;
        }

        function insertRowInLeague(league, row) {
            const body = league ? league.querySelector('.sb2-league-body') : null;
            if (!body || !row) {
                return;
            }

            const targetStartsAt = Number(row.dataset.startsAt || Number.MAX_SAFE_INTEGER);
            const leagueRecord = leagueRecordByNode.get(league);
            const siblings = leagueRecord
                ? leagueRecord.rows.map(function (rowRecord) {
                    return rowRecord.row;
                }).filter(function (sibling) {
                    return sibling !== row;
                })
                : Array.from(body.querySelectorAll('[data-event-row]')).filter(function (sibling) {
                    return sibling !== row;
                });

            const nextSibling = siblings.find(function (sibling) {
                const siblingStartsAt = Number(sibling.dataset.startsAt || Number.MAX_SAFE_INTEGER);
                if (targetStartsAt !== siblingStartsAt) {
                    return targetStartsAt < siblingStartsAt;
                }

                return Number(row.dataset.eventId || 0) < Number(sibling.dataset.eventId || 0);
            });

            if (nextSibling) {
                body.insertBefore(row, nextSibling);
                return;
            }

            body.appendChild(row);
        }

        function insertOrMoveRowForEvent(row, shellEvent, options) {
            const nextOptions = options || {};
            if (!row || !shellEvent) {
                return;
            }

            row.dataset.eventPhase = shellEvent.phase_key || 'pre-match';
            row.dataset.startsAt = String(shellEvent.starts_at_timestamp || row.dataset.startsAt || '');
            row.dataset.sportKey = shellEvent.sport_group_key || row.dataset.sportKey || 'other';
            row.dataset.leagueFilterKey = shellEvent.league_filter_key || row.dataset.leagueFilterKey || 'turnuva';
            row.dataset.searchText = rowSearchText(shellEvent);

            const league = ensureLeagueNode(shellEvent, { deferRefresh: true });
            if (!league) {
                return;
            }

            insertRowInLeague(league, row);

            if (!nextOptions.deferRefresh) {
                refreshDynamicCollections();
                sortLeagueNodes();
            }
        }

        function removeLeagueIfEmpty(league, options) {
            const nextOptions = options || {};
            if (!league) {
                return;
            }

            if (league.querySelector('[data-event-row]')) {
                return;
            }

            const leagueKey = league.dataset.leagueFilterKey || '';
            league.remove();

            if (leagueKey) {
                const chip = leagueFilterButtonByKey.get(leagueKey)
                    || document.querySelector('[data-league-filter="' + escapeSelectorValue(leagueKey) + '"]');
                if (chip && chip.dataset.leagueFilter !== 'all') {
                    chip.remove();
                }
            }

            if (!nextOptions.deferRefresh) {
                refreshDynamicCollections();
            }
        }

        function removeRowFromDom(row, options) {
            const nextOptions = options || {};
            if (!row) {
                return;
            }

            const league = row.closest('.sb2-league');
            row.remove();
            removeLeagueIfEmpty(league, { deferRefresh: true });

            if (!nextOptions.deferRefresh) {
                refreshDynamicCollections();
            }
        }

        function syncStakeChipValues() {
            const defaultValues = [50, 100, 250, 500];
            let chipValues = defaultValues.slice();

            if (viewer.authenticated && viewer.balance > 0) {
                chipValues = defaultValues.filter(function (value) {
                    return value <= viewer.balance;
                });

                const roundedBalance = Math.floor(viewer.balance);
                if (chipValues.length === 0 && roundedBalance > 0) {
                    chipValues = [roundedBalance];
                } else if (roundedBalance > 0 && !chipValues.includes(roundedBalance) && chipValues.length < stakeChipButtons.length) {
                    chipValues.push(roundedBalance);
                }
            }

            stakeChipButtons.forEach(function (button, index) {
                const value = chipValues[index];
                if (!value || value <= 0) {
                    button.hidden = true;
                    button.disabled = true;
                    return;
                }

                button.hidden = false;
                button.disabled = submitState === 'submitting' || frozenAfterSuccess;
                button.dataset.stakeChip = String(value);
                button.textContent = formatChipLabel(value);
            });

            if (fillBalanceButton) {
                fillBalanceButton.hidden = false;
                fillBalanceButton.disabled = submitState === 'submitting'
                    || frozenAfterSuccess
                    || !viewer.authenticated
                    || viewer.balance <= 0;
            }

            updateStakeChipSelection();
        }

        function normalizeStakeInputValue(value) {
            const digits = String(value || '').replace(/\D/g, '').replace(/^0+/, '');

            return digits;
        }

        function updateStakeChipSelection() {
            const amount = Number(currentStake().toFixed(2));
            stakeChipButtons.forEach(function (button) {
                if (button.hidden) {
                    button.classList.remove('is-active');
                    return;
                }
                const chipValue = Number(button.dataset.stakeChip || 0);
                button.classList.toggle('is-active', chipValue === amount && amount > 0);
            });
        }

        function renderSlipModeState() {
            const hasHistoryModeTab = slipModeButtons.some(function (button) {
                return (button.dataset.slipModeTab || 'single') === 'history';
            });
            const isHistoryMode = hasHistoryModeTab && activeSlipMode === 'history' && Boolean(historyCard);
            const isAltMode = activeSlipMode === 'combo' || (!hasHistoryModeTab && activeSlipMode === 'system');
            const socialSingleOnly = isSocialMarketSelection(selected);

            slipModeButtons.forEach(function (button) {
                const buttonMode = button.dataset.slipModeTab || 'single';
                const isBlockedSocialMode = socialSingleOnly && (buttonMode === 'combo' || buttonMode === 'system');
                const isActive = buttonMode === activeSlipMode;
                button.classList.toggle('is-active', isActive);
                button.setAttribute('aria-selected', isActive ? 'true' : 'false');
                button.disabled = isBlockedSocialMode;
                if (isBlockedSocialMode) {
                    button.setAttribute('title', t('social_single_only', 'Sosyal Market seçimleri tekli oynanır.'));
                } else {
                    button.removeAttribute('title');
                }
            });

            if (slipModeSurface) {
                slipModeSurface.hidden = !isAltMode;
            }

            if (collapsedSummary) {
                collapsedSummary.hidden = !slipCollapsed || isHistoryMode || isAltMode;
            }

            if (isAltMode) {
                if (slipModeSurfaceTitle) {
                    slipModeSurfaceTitle.textContent = activeSlipMode === 'combo' ? t('combo', 'Kombine') : t('system', 'Seri');
                }

                if (slipModeSurfaceText) {
                    slipModeSurfaceText.textContent = t('mode_closed_body', 'Bu görünüm bu turda kapalı. Tekli akış aynı yüzeyde açık kalır.');
                }
            }
        }

        function applySlipModeVisibility() {
            const hasSelection = Boolean(selected);
            const showSuccessOnlyState = !hasSelection && submitState === 'success' && Boolean(acceptedPayload);
            const showLastActionOnlyState = !hasSelection && Boolean(lastAcceptedPayload);
            const hasHistoryModeTab = slipModeButtons.some(function (button) {
                return (button.dataset.slipModeTab || 'single') === 'history';
            });
            const showSingleMode = activeSlipMode === 'single';
            const showHistoryMode = hasHistoryModeTab && activeSlipMode === 'history';
            const showAltMode = activeSlipMode === 'combo' || (!hasHistoryModeTab && activeSlipMode === 'system');

            if (filledState) {
                filledState.hidden = !showSingleMode || (!hasSelection && !showSuccessOnlyState && !showLastActionOnlyState);
            }

            if (slipIdleCard) {
                slipIdleCard.hidden = !showSingleMode || hasSelection || submitState !== 'idle';
            }

            if (selectionCard) {
                selectionCard.hidden = !showSingleMode || !hasSelection;
            }

            if (stakeCard) {
                stakeCard.hidden = !showSingleMode || !hasSelection;
            }

             if (resultCard) {
                resultCard.hidden = !showSingleMode || submitState === 'idle' || (submitState === 'success' && dismissedSuccessResult);
            }

            if (lastActionCard) {
                lastActionCard.hidden = !showSingleMode || !lastAcceptedPayload;
            }

            if (slipModeSurface) {
                slipModeSurface.hidden = !showAltMode;
            }

        }

        function updateCollapsedSummary() {
            if (!collapsedSummaryTitle || !collapsedSummaryMeta || !collapsedSummaryOdd || !collapsedSummaryStake) {
                return;
            }

            const selectionCount = selected ? 1 : 0;

            if (collapsedSummaryCount) {
                collapsedSummaryCount.textContent = String(selectionCount);
                collapsedSummaryCount.hidden = selectionCount < 1;
            }

            if (!selected) {
                collapsedSummaryTitle.textContent = t('slip_empty', 'Kupon boş');
                collapsedSummaryMeta.textContent = t('select_odds', 'Bir oran seç');
                collapsedSummaryStake.textContent = formatCurrency(0);
                collapsedSummaryOdd.textContent = '-';
                if (collapsedSummary) {
                    collapsedSummary.hidden = !slipCollapsed;
                }
                return;
            }

            if (collapsedSummary) {
                collapsedSummary.hidden = !slipCollapsed;
            }

            collapsedSummaryTitle.textContent = selected.event_name || t('selection_fallback', 'Seçim');
            collapsedSummaryMeta.textContent = [selected.market_name, selected.outcome_name].filter(Boolean).join(', ') || '-';
            collapsedSummaryStake.textContent = formatCurrency(currentStake());
            collapsedSummaryOdd.textContent = acceptedPayload && acceptedPayload.accepted_price
                ? formatDisplayOdd(acceptedPayload.accepted_price)
                : (selected.odd || '-');
        }

        function resolveSlipViewportMode() {
            if (window.innerWidth <= 767) {
                return 'mobile';
            }

            if (window.innerWidth <= 1399) {
                return 'floating';
            }

            return 'static';
        }

        function syncSlipLayoutMode() {
            if (!layout) {
                return;
            }

            layout.classList.toggle('is-slip-static', slipViewportMode === 'static');
            layout.classList.toggle('is-slip-floating', slipViewportMode === 'floating');
            layout.classList.toggle('is-slip-mobile', slipViewportMode === 'mobile');
            layout.classList.toggle('is-slip-collapsed', slipCollapsed);
            // Floating/mobile slips have one bounded scroll container. A sibling
            // research panel otherwise escapes the collapsed sheet over the board.
            if (bahisbotPanel && slipShell) {
                if (slipViewportMode !== 'static') {
                    if (bahisbotPanel.parentElement !== slipShell) {
                        slipShell.appendChild(bahisbotPanel);
                    }
                } else if (bahisbotPanel.parentElement === slipShell) {
                    slipShell.insertAdjacentElement('afterend', bahisbotPanel);
                }
            }
        }

        function setSlipCollapsed(collapsed, options) {
            const nextOptions = options || {};
            slipCollapsed = Boolean(collapsed);

            if (!nextOptions.skipRemember && slipCollapsedByMode[slipViewportMode] !== undefined) {
                slipCollapsedByMode[slipViewportMode] = slipCollapsed;
            }

            if (slipShell) {
                slipShell.classList.toggle('is-collapsed', slipCollapsed);
            }

            if (collapsedSummary) {
                collapsedSummary.hidden = !slipCollapsed;
            }

            slipToggleButtons.forEach(function (button) {
                button.setAttribute('aria-label', slipCollapsed ? t('slip_expand', 'Kuponu büyüt') : t('slip_collapse', 'Kuponu küçült'));
                button.setAttribute('aria-expanded', slipCollapsed ? 'false' : 'true');
            });

            syncSlipLayoutMode();
            renderSlipModeState();
            updateCollapsedSummary();
        }

        function syncSlipViewportMode(force) {
            const nextMode = resolveSlipViewportMode();
            const modeChanged = nextMode !== slipViewportMode;

            slipViewportMode = nextMode;
            syncSlipLayoutMode();

            if (modeChanged || force) {
                setSlipCollapsed(Boolean(slipCollapsedByMode[nextMode]), {
                    skipRemember: true,
                });
            }
        }

        function currentStake() {
            if (!stakeInput) {
                return 0;
            }

            return Math.max(Number(normalizeStakeInputValue(stakeInput.value) || 0), 0);
        }

        function hasInsufficientBalance() {
            if (offlineDemo.active || (selected && selected.acceptance_mode === 'offline_demo')) return false;
            return viewer.authenticated && viewer.balance > 0 && currentStake() > viewer.balance;
        }

        function openModal(element) {
            if (!element || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
                return false;
            }

            bootstrap.Modal.getOrCreateInstance(element).show();

            return true;
        }

        function closeModal(element) {
            if (!element || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
                return;
            }

            bootstrap.Modal.getOrCreateInstance(element).hide();
        }

        function showLoginModalDirectly() {
            const globalLogin = document.querySelector('[data-global-auth-open="login"]');
            if (globalLogin) { globalLogin.click(); return true; }
            if (!loginModalElement || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
                return false;
            }

            if (redirectAfterLoginInput) {
                redirectAfterLoginInput.value = sanitizeCmsLoginRedirect(window.location.href);
            }

            bootstrap.Modal.getOrCreateInstance(loginModalElement).show();

            return true;
        }

        function openLoginFlow() {
            if (authPromptModal && authPromptModal.classList.contains('show')) {
                authPromptModal.addEventListener('hidden.bs.modal', function handleHidden() {
                    authPromptModal.removeEventListener('hidden.bs.modal', handleHidden);
                    showLoginModalDirectly();
                }, { once: true });

                closeModal(authPromptModal);
                return true;
            }

            if (showLoginModalDirectly()) {
                return true;
            }

            return openModal(authPromptModal);
        }

        function openDepositFlow() {
            if (typeof window.openCmsDepositModal === 'function') {
                return window.openCmsDepositModal();
            }

            if (openModal(zeroBalanceModalElement)) {
                return true;
            }

            if (viewer.depositUrl) {
                return openModal(depositPromptModal);
            }

            return false;
        }

        function openWalletSwitchFlow() {
            if (typeof window.openCmsWalletCenterModal === 'function') {
                return window.openCmsWalletCenterModal();
            }

            if (viewer.financeReportUrl) {
                window.location.assign(viewer.financeReportUrl);
                return true;
            }

            return false;
        }

        function keepSelectionWithQuoteUnavailable(message, traceSuffix) {
            currentIdempotencyKey = null;
            acceptedPayload = null;
            frozenAfterSuccess = false;
            submitState = 'error';
            submitTitle = t('quote_not_found_title', 'Oran bulunamadı');
            submitMessage = String(message || adapterRefreshCopyForMissingSelection()) + String(traceSuffix || '');
            inlineGateMessage = '';
            resultActionIntent = 'retry';
            renderSelectionState();
        }

        async function handleRefreshQuoteAction() {
            if (!selected) {
                return;
            }

            const previousSelection = selected;
            const previousPrice = oddNumericValue(previousSelection);
            submitState = 'submitting';
            submitTitle = t('quote_checking_title', 'Oran doğrulanıyor');
            submitMessage = t('quote_checking_long', 'Oran sağlayıcıdan doğrulanıyor... Bu işlem birkaç saniye sürebilir.');
            startSubmitProgressTimer();

            const refreshedSelection = await refreshEventOffer(
                previousSelection.event_id,
                previousSelection.market_type,
                previousSelection.outcome_code,
                previousSelection.market_specifier_key,
                previousSelection
            );

            if (refreshedSelection) {
                clearSubmitProgressTimer();
                exactWarningKey = '';
                selected = stampSelectedPayload(refreshedSelection, {
                    selectedAt: previousSelection.selected_at || Date.now(),
                    payloadFetchedAt: refreshedSelection.quote_payload_fetched_at || Date.now(),
                    refreshedAt: refreshedSelection.quote_checked_at || Date.now(),
                });
                currentIdempotencyKey = null;
                acceptedPayload = null;
                inlineGateMessage = '';
                const nextPrice = oddNumericValue(selected);
                if (Math.abs(previousPrice - nextPrice) > 0.000001) {
                    submitState = 'refreshed';
                    submitTitle = t('quote_updated_title', 'Oran güncellendi');
                    submitMessage = t('quote_updated_body', 'Eski oran :old, güncel oran :new. Devam etmek için yeniden onayla.', {
                        old: formatDisplayOdd(previousPrice),
                        new: formatDisplayOdd(nextPrice),
                    });
                } else {
                    submitState = 'verified';
                    submitTitle = t('quote_verified_title', 'Oran doğrulandı');
                    submitMessage = t('quote_verified_body', 'Seçili oran değişmedi. Kuponu güvenle onaylayabilirsin.');
                    showInlineSubmitMessage(t('quote_verified_inline', 'Oran doğrulandı.'));
                }
                renderSelectionState();
                return;
            }

            clearSubmitProgressTimer();
            keepSelectionWithQuoteUnavailable(t('unavailable_market', 'Seçili market şu an bahis için uygun değil.'));
        }

        async function refreshSelectedAdapterQuoteBeforeSubmit() {
            if (!adapterPreSubmitQuoteRefreshEnabled && !(selected && selected.contract_required)) {
                return true;
            }

            if (!isAdapterQuoteRefSelection(selected) && !(selected && selected.contract_required)) {
                return true;
            }

            const previousSelection = selected;
            const previousPrice = oddNumericValue(previousSelection);
            const staleByAge = adapterQuoteNeedsFreshSubmitCheck(previousSelection);
            const refreshKey = adapterSelectionRefreshKey(previousSelection);

            if (!previousSelection.contract_required && !staleByAge && sportsbookFeedHydratedContractUsable(previousSelection)) {
                lastSubmitUsedAutoAcceptedOdds = false;
                return true;
            }

            submitState = 'submitting';
            submitTitle = t('quote_checking_title', 'Oran doğrulanıyor');
            submitMessage = staleByAge
                ? t('quote_checking_long', 'Oran sağlayıcıdan doğrulanıyor... Bu işlem birkaç saniye sürebilir.')
                : t('quote_checking_short', 'Oran sağlayıcıdan doğrulanıyor...');
            startSubmitProgressTimer();

            if (selectedQuoteRefreshPromise && selectedQuoteRefreshKey === refreshKey) {
                const prefetchedSelection = await selectedQuoteRefreshPromise;
                const appliedSelection = applyAdapterRefreshedSelectionToSelected(refreshKey, prefetchedSelection, previousSelection);
                if (appliedSelection && sportsbookFeedHydratedContractUsable(appliedSelection) && !adapterQuoteNeedsFreshSubmitCheck(appliedSelection)) {
                    selected.contract_required = false;
                    clearSubmitProgressTimer();
                    lastSubmitUsedAutoAcceptedOdds = Math.abs(previousPrice - oddNumericValue(selected)) > 0.000001;
                    submitState = lastSubmitUsedAutoAcceptedOdds ? 'refreshed' : 'verified';
                    submitTitle = lastSubmitUsedAutoAcceptedOdds
                        ? t('quote_updated_title', 'Oran güncellendi')
                        : t('quote_verified_title', 'Oran doğrulandı');
                    submitMessage = lastSubmitUsedAutoAcceptedOdds
                        ? t('quote_updated_continue', 'Güncel oran doğrulandı; kupon bu oranla gönderiliyor.')
                        : t('quote_verified_body', 'Seçili oran değişmedi. Kuponu güvenle onaylayabilirsin.');
                    renderSelectionState();
                    if (lastSubmitUsedAutoAcceptedOdds && !autoAcceptOddsChanges) {
                        submitMessage = t('quote_updated_body', 'Eski oran :old, güncel oran :new. Devam etmek için yeniden onayla.', {old: formatDisplayOdd(previousPrice), new: formatDisplayOdd(oddNumericValue(selected))});
                        renderSelectionState();
                        return false;
                    }
                    return true;
                }

                clearSubmitProgressTimer();
                keepSelectionWithQuoteUnavailable(adapterRefreshUnavailableCopy());
                return false;
            }

            const refreshedSelection = await refreshEventOffer(
                previousSelection.event_id,
                previousSelection.market_type,
                previousSelection.outcome_code,
                previousSelection.market_specifier_key,
                previousSelection
            );

            if (!refreshedSelection) {
                clearSubmitProgressTimer();
                keepSelectionWithQuoteUnavailable(adapterRefreshUnavailableCopy());
                return false;
            }

            if (!sportsbookFeedHydratedContractUsable(refreshedSelection)) {
                clearSubmitProgressTimer();
                keepSelectionWithQuoteUnavailable(adapterRefreshUnavailableCopy());
                return false;
            }

            const nextSelection = applyAdapterRefreshedSelectionToSelected(refreshKey, refreshedSelection, previousSelection);
            if (!nextSelection) {
                clearSubmitProgressTimer();
                keepSelectionWithQuoteUnavailable(adapterRefreshUnavailableCopy());
                return false;
            }
            const nextPrice = oddNumericValue(nextSelection);
            selected.contract_required = false;

            if (Math.abs(previousPrice - nextPrice) > 0.000001) {
                clearSubmitProgressTimer();
                if (autoAcceptOddsChanges) {
                    lastSubmitUsedAutoAcceptedOdds = true;
                    submitState = 'submitting';
                    submitTitle = t('quote_updated_title', 'Oran güncellendi');
                    submitMessage = t('quote_updated_continue', 'Güncel oran doğrulandı; kupon bu oranla gönderiliyor.');
                    showInlineSubmitMessage(t('quote_verified_continue', 'Oran doğrulandı ve güncel oranla devam ediliyor.'));
                    renderSelectionState();
                    return true;
                }

                submitState = 'refreshed';
                submitTitle = t('quote_updated_title', 'Oran güncellendi');
                submitMessage = t('quote_updated_body', 'Eski oran :old, güncel oran :new. Devam etmek için yeniden onayla.', { old: formatDisplayOdd(previousPrice), new: formatDisplayOdd(nextPrice) });
                renderSelectionState();
                return false;
            }

            lastSubmitUsedAutoAcceptedOdds = false;
            clearSubmitProgressTimer();
            submitState = 'verified';
            submitTitle = t('quote_verified_title', 'Oran doğrulandı');
            submitMessage = t('quote_verified_body', 'Seçili oran değişmedi. Kuponu güvenle onaylayabilirsin.');
            renderSelectionState();
            return true;
        }

        function configureErrorResultAction(presentation) {
            const suggestedAction = String(presentation && presentation.suggestedAction ? presentation.suggestedAction : 'retry');
            resultActionIntent = suggestedAction;

            if (!resultAction) {
                return;
            }

            if ((!selected && suggestedAction !== 'switch_wallet' && suggestedAction !== 'deposit_or_lower_stake')
                || (selected && !selectionIsActionable() && !['pick_another_selection', 'refresh_selection'].includes(suggestedAction))) {
                resultAction.hidden = true;
                return;
            }

            resultAction.hidden = false;

            if (suggestedAction === 'deposit_or_lower_stake') {
                resultAction.textContent = viewer.balance > 0 ? t('reduce_stake', 'Tutarı düşür') : t('deposit', 'Para yatır');
                return;
            }

            if (suggestedAction === 'switch_wallet') {
                resultAction.textContent = t('switch_wallet', 'Cüzdanı değiştir');
                return;
            }

            if (suggestedAction === 'refresh_quote') {
                resultAction.textContent = t('refresh_odds', 'Oranı yenile');
                return;
            }

            if (suggestedAction === 'pick_another_selection' || suggestedAction === 'refresh_selection') {
                resultAction.textContent = t('change_selection', 'Yeni seçim yap');
                return;
            }

            resultAction.textContent = t('retry', 'Tekrar dene');
        }

        function parseBalanceAmount() {
            if (!balanceText) {
                return 0;
            }

            const rawValue = balanceText.textContent || '';
            const normalized = rawValue.replace(/[^\d,.-]/g, '').replace(/\./g, '').replace(',', '.');
            const parsed = Number(normalized);

            return Number.isFinite(parsed) ? Math.max(parsed, 0) : 0;
        }

        function buildIdempotencyKey() {
            if (!selected) {
                return '';
            }

            if (currentIdempotencyKey) {
                return currentIdempotencyKey;
            }

            // Keep one bounded request identity across timeout/retry; encrypted selection tokens exceed the source SQL key width.
            currentIdempotencyKey = ['shell', viewer.id || 'guest', window.crypto.randomUUID()].join(':');

            return currentIdempotencyKey;
        }

        function applyWalletSnapshot(snapshot) {
            if (!snapshot || typeof snapshot !== 'object') {
                return;
            }

            if (typeof snapshot.balance === 'number') {
                viewer.balance = Number(snapshot.balance || 0);
            }

            if (typeof snapshot.currency === 'string' && snapshot.currency !== '') {
                viewer.currency = snapshot.currency;
            }

            if (balanceText) {
                balanceText.textContent = typeof snapshot.formattedBalance === 'string' && snapshot.formattedBalance !== ''
                    ? [snapshot.formattedBalance, snapshot.currency].filter(Boolean).join(' ')
                    : formatCurrency(viewer.balance);
            }

            if (window.CmsBalanceSync && typeof window.CmsBalanceSync.applySnapshot === 'function') {
                window.CmsBalanceSync.applySnapshot(snapshot);
            }

            syncStakeChipValues();
        }

        async function refreshWalletSnapshot() {
            if (!viewer.authenticated) {
                return null;
            }

            try {
                if (window.CmsBalanceSync && typeof window.CmsBalanceSync.refresh === 'function') {
                    const synced = await window.CmsBalanceSync.refresh(true);
                    if (synced) {
                        applyWalletSnapshot(synced);
                        return synced;
                    }
                }

                if (!walletSnapshotUrl) {
                    return null;
                }

                const response = await requestJson(walletSnapshotUrl, {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    cache: 'no-store',
                });

                if (!response.ok) {
                    return null;
                }

                const snapshot = await response.json();
                applyWalletSnapshot(snapshot);

                return snapshot;
            } catch (error) {
                return null;
            }
        }

        function applyAcceptedPayload(payload) {
            acceptedPayload = payload;
            lastAcceptedPayload = payload;
            const shortAcceptanceRef = shortReferenceLabel(payload.acceptance_ref || '-');
            const stakeAmount = Number(payload.stake_amount || lastSubmittedStakeAmount || 0);
            const acceptedOdd = Number(payload.accepted_price || 0);
            const potentialReturn = stakeAmount > 0 && acceptedOdd > 0 ? stakeAmount * acceptedOdd : 0;
            const summaryLines = [];

            if (stakeAmount > 0 || acceptedOdd > 0) {
                summaryLines.push([
                    stakeAmount > 0 ? formatCurrency(stakeAmount) : '',
                    acceptedOdd > 0 ? formatDisplayOdd(acceptedOdd) : '',
                ].filter(Boolean).join(' · '));
            }

            if (potentialReturn > 0) {
                summaryLines.push(t('potential_return', 'Olası getiri') + ' ' + formatCurrency(potentialReturn));
            }

            if (shortAcceptanceRef && shortAcceptanceRef !== '-') {
                summaryLines.push('Ref: ' + shortAcceptanceRef);
            }

            if (acceptanceRef) {
                acceptanceRef.textContent = shortAcceptanceRef;
                acceptanceRef.removeAttribute('title');
                acceptanceRef.dataset.acceptanceRef = payload.acceptance_ref || '';
            }
            if (acceptanceId) {
                acceptanceId.textContent = payload.acceptance_id || '-';
            }
            if (acceptedPrice) {
                acceptedPrice.textContent = formatDisplayOdd(payload.accepted_price || '-');
            }
            if (lastActionStatus) {
                lastActionStatus.textContent = t('accepted', 'Bahis alındı');
            }
            if (lastActionSummary) {
                lastActionSummary.textContent = summaryLines.length ? summaryLines.join('\n') : t('accepted', 'Bahis alındı');
            }
            if (lastStake) {
                lastStake.textContent = stakeAmount > 0 ? formatCurrency(stakeAmount) : '-';
            }
            if (lastAcceptanceRef) {
                lastAcceptanceRef.textContent = shortAcceptanceRef;
                lastAcceptanceRef.removeAttribute('title');
                lastAcceptanceRef.dataset.acceptanceRef = payload.acceptance_ref || '';
            }
            if (lastAcceptedPrice) {
                lastAcceptedPrice.textContent = formatDisplayOdd(payload.accepted_price || '-');
            }
            if (lastReturn) {
                lastReturn.textContent = potentialReturn > 0 ? formatCurrency(potentialReturn) : '-';
            }
        }

        function clearSuccessResetTimer() {
            if (successResetTimer) {
                window.clearTimeout(successResetTimer);
                successResetTimer = null;
            }
        }

        function resetAcceptedCouponToIdle() {
            if (submitState !== 'success') {
                return;
            }

            selected = null;
            frozenAfterSuccess = false;
            currentIdempotencyKey = null;
            inlineGateMessage = '';
            dismissedSuccessResult = true;
            submitState = 'idle';
            submitTitle = '';
            submitMessage = '';

            if (stakeInput) {
                stakeInput.value = '';
            }

            renderStakeSummary();
            renderSelectionState();
        }

        function scheduleAcceptedCouponReset() {
            clearSuccessResetTimer();
            successResetTimer = window.setTimeout(function () {
                successResetTimer = null;
                resetAcceptedCouponToIdle();
            }, successConfirmationHoldMs);
        }

        function updateActiveButtons() {
            refreshSelectionTriggers().forEach(function (button) {
                const payload = JSON.parse(button.dataset.selection || '{}');
                const isSelected = selected && (payload.selection_key === selected.selection_key
                    || (selected.demo_selections || []).some(item => item.selection_key === payload.selection_key));
                button.classList.toggle('is-selected', Boolean(isSelected));
                button.classList.toggle('is-locked', Boolean(isSelected && frozenAfterSuccess));
            });
        }

        function updateLeagueVisibility() {
            const visibleLeagueCountByNode = new Map();

            currentVisibleRowSet.forEach(function (row) {
                const record = rowRecordByRow.get(row);
                if (!record || !record.leagueNode) {
                    return;
                }

                visibleLeagueCountByNode.set(record.leagueNode, (visibleLeagueCountByNode.get(record.leagueNode) || 0) + 1);
            });

            applyVisibleLeagueState(visibleLeagueCountByNode);
        }

        function updateBrowserQueryState() {
            if (typeof window === 'undefined' || !window.history || !window.history.replaceState) {
                return;
            }

            const nextParams = new URLSearchParams(window.location.search);

            if (activeSportFilter !== 'all') {
                nextParams.set('sport', activeSportFilter);
            } else {
                nextParams.delete('sport');
            }

            if (activeInterestPriorityMode) {
                nextParams.set('interest_priority', 'featured');
                nextParams.delete('time_priority');
                nextParams.delete('sport');
                nextParams.delete('league');
            } else if (activeTimePriorityMode) {
                nextParams.set('time_priority', 'next');
                nextParams.delete('interest_priority');
                nextParams.delete('sport');
                nextParams.delete('league');
            } else {
                nextParams.delete('time_priority');
                nextParams.delete('interest_priority');
            }

            if (!activeTimePriorityMode && !activeInterestPriorityMode && activeLeagueFilter !== 'all') {
                nextParams.set('league', activeLeagueFilter);
            } else {
                nextParams.delete('league');
            }

            if (activeSearchQuery !== '') {
                nextParams.set('search', activeSearchQuery);
            } else {
                nextParams.delete('search');
            }

            const nextQuery = nextParams.toString();
            const nextUrl = window.location.pathname + (nextQuery ? '?' + nextQuery : '') + window.location.hash;
            window.history.replaceState({}, '', nextUrl);
        }

        function filteredMatchRows() {
            return contextualRows(activePhaseForTab(), activeSportFilter, activeLeagueFilter, activeSearchQuery);
        }

        function queueInteractiveLiveRefresh(delayMs, options) {
            if (!livePollEnabled) {
                return;
            }

            const nextOptions = options || {};

            if (interactiveLiveRefreshTimer) {
                window.clearTimeout(interactiveLiveRefreshTimer);
            }

            interactiveLiveRefreshTimer = window.setTimeout(async function () {
                interactiveLiveRefreshTimer = null;
                clearLivePollTimer();
                if (nextOptions.filters && !filtersMatchCurrentHydrationContext(nextOptions.filters)) {
                    finishBoardRefreshingIfIdle();
                    return;
                }
                if (hydrationInFlight || livePollInFlight) {
                    queueInteractiveLiveRefresh(120, nextOptions);
                    return;
                }
                await bootstrapFullCoverageHydration({
                    interrupt: nextOptions.interrupt !== false,
                    filters: nextOptions.filters,
                });
                queueLivePoll(document.hidden ? livePollBackgroundMs : livePollVisibleMs);
            }, typeof delayMs === 'number' ? delayMs : 900);
        }

        function loadMoreAvailableForCurrentScope() {
            return activePrimaryTab === 'pre-match'
                && !activeTimePriorityMode
                && !activeInterestPriorityMode
                && activeSearchQuery === '';
        }

        function updateMatchLoadMoreControl(totalRows, visibleRows) {
            if (!matchPagination) {
                return;
            }

            const remotePagination = currentLiveOfferPaginationState();
            const remoteTotalRows = remotePagination ? Number(remotePagination.visibleTotal || 0) : 0;
            const effectiveTotalRows = Math.max(totalRows, remoteTotalRows);
            const canLoadMore = loadMoreAvailableForCurrentScope()
                && (totalRows > visibleRows || (remotePagination && remotePagination.hasMore && effectiveTotalRows > visibleRows));
            matchPagination.hidden = !canLoadMore;

            if (matchPageLabel) {
                matchPageLabel.textContent = t('match_list', 'Maç listesi');
            }

            if (matchPageSummary) {
                matchPageSummary.textContent = canLoadMore
                    ? t('match_count_page', ':visible / :total maç', { visible: formatCountLabel(visibleRows), total: formatCountLabel(effectiveTotalRows) })
                    : t('match_count', ':count maç', { count: formatCountLabel(effectiveTotalRows || totalRows) });
            }

            if (matchPagePrev) {
                matchPagePrev.hidden = true;
            }

            if (matchPageNext) {
                matchPageNext.hidden = !canLoadMore;
                matchPageNext.disabled = !canLoadMore;
                matchPageNext.textContent = t('show_more', 'Daha fazla göster');
            }

            if (matchPageNumbers) {
                matchPageNumbers.innerHTML = '';
            }
        }

        function scheduleBoardRefresh(options) {
            const nextOptions = options || {};
            const token = ++boardRefreshToken;

            if (nextOptions.indicateBusy === false) {
                setBoardRefreshing(false, '', { immediate: true });
            } else {
                setBoardRefreshing(true, nextOptions.detail);
            }

            window.requestAnimationFrame(function () {
                if (token !== boardRefreshToken) {
                    return;
                }

                if (nextOptions.updateTabCounts) {
                    updateTabCounts();
                }

                updateSportFilterButtons();
                updateLeagueFilterButtons();
                updateEmptyStateCopy();
                updateMatchPagination();
                queueLeagueWindowRefresh();
                finishBoardRefreshingIfIdle();
            });
        }

        function sportAvailabilityScore(counts, sportKey) {
            return Math.max(
                Number(((counts || {}).bySport || {})[sportKey] || 0),
                visibleSportCount(activePrimaryTab, sportKey)
            );
        }

        function firstAvailableSportFilter(counts) {
            const candidates = sportFilterButtons
                .map(function (button) {
                    return button.dataset.sportFilter || '';
                })
                .filter(function (sportKey, index, list) {
                    return isPrimarySportFilterKey(sportKey)
                        && list.indexOf(sportKey) === index
                        && sportAvailabilityScore(counts, sportKey) > 0;
                });

            for (let index = 0; index < 3; index += 1) {
                if (candidates.indexOf(productSportOrder[index]) !== -1) {
                    return productSportOrder[index];
                }
            }

            return candidates.sort(function (left, right) {
                const countDelta = sportAvailabilityScore(counts, right) - sportAvailabilityScore(counts, left);

                if (countDelta !== 0) {
                    return countDelta;
                }

                if (sportRailRank(left) !== sportRailRank(right)) {
                    return sportRailRank(left) - sportRailRank(right);
                }

                return left.localeCompare(right, 'tr');
            })[0] || fallbackSportFilter;
        }

        function updateSportFilterButtons() {
            const counts = activeSportFilter === 'upcoming'
                ? countsForTab(activePrimaryTab)
                : inventoryCountsForTab(activePrimaryTab);
            const transientCounts = countStateIsTransient();
            const hideEmptyOfferNavigation = shouldHideOfferNavigationForEmptyState(counts);
            rememberSportCounts(activePrimaryTab, counts.bySport);
            const activeSportVisibleCount = visibleSportCount(activePrimaryTab, activeSportFilter);

            if (sportStripShell) {
                sportStripShell.hidden = hideEmptyOfferNavigation;
            }

            if (!transientCounts
                && activeSportFilter !== 'all'
                && activeSportFilter !== 'upcoming'
                && !counts.bySport[activeSportFilter]
                && activeSportVisibleCount <= 0) {
                activeSportFilter = firstAvailableSportFilter(counts);
            }

            sportFilterButtons.forEach(function (button) {
                const isActive = (button.dataset.sportFilter || 'all') === activeSportFilter;
                const sportKey = button.dataset.sportFilter || 'all';
                const count = counts.bySport[sportKey] || 0;
                const visibleCount = visibleSportCount(activePrimaryTab, sportKey);
                const rememberedCount = rememberedSportCount(activePrimaryTab, sportKey);
                const countNode = button.querySelector('.sb2-sport-count');
                button.classList.toggle('is-active', isActive);
                button.setAttribute('aria-selected', isActive ? 'true' : 'false');
                if (countNode) {
                    countNode.dataset.countRaw = String(count);
                    countNode.textContent = formatCountLabel(count);
                }
                button.hidden = hideEmptyOfferNavigation || !(
                    isActive
                    || count > 0
                    || visibleCount > 0
                    || (transientCounts && rememberedCount > 0)
                    || transientCounts
                );
            });

            updateSurfaceSportSwitchers();
            queueActiveSportButtonVisibility({
                immediate: transientCounts || !hydrationReady,
            });
            updateUtilityButtons();
        }

        function shouldHideOfferNavigationForEmptyState(counts) {
            if (activePrimaryTab !== 'in-game') {
                return false;
            }

            if (deferredTabCounts.has('in-game') && countStateIsTransient()) {
                return false;
            }

            const nextCounts = counts || inventoryCountsForTab(activePrimaryTab);

            return Number((nextCounts || {}).total || 0) <= 0
                && phaseDataFor('in-game', '').records.length <= 0;
        }

        function updateSurfaceSportSwitchers() {
            surfaceSportSwitchers.forEach(function (button) {
                const targetTab = button.dataset.tabSwitch || '';
                const isActive = targetTab === activePrimaryTab;
                button.classList.toggle('is-active', isActive);
                button.setAttribute('aria-selected', isActive ? 'true' : 'false');
            });
        }

        function updateUtilityButtons() {
            if (featuredMatchesToggleButton) {
                const featuredActive = interestPriorityActiveFor('pre-match');
                featuredMatchesToggleButton.classList.toggle('is-active', featuredActive);
                featuredMatchesToggleButton.setAttribute('aria-pressed', featuredActive ? 'true' : 'false');
            }

            if (nextMatchesToggleButton) {
                const nextActive = timePriorityActiveFor('pre-match');
                nextMatchesToggleButton.classList.toggle('is-active', nextActive);
                nextMatchesToggleButton.setAttribute('aria-pressed', nextActive ? 'true' : 'false');
            }

            if (allMatchesToggleButton) {
                const allActive = !interestPriorityActiveFor('pre-match') && !timePriorityActiveFor('pre-match');
                allMatchesToggleButton.classList.toggle('is-active', allActive);
                allMatchesToggleButton.setAttribute('aria-pressed', allActive ? 'true' : 'false');
            }
        }

        function leagueFilterLabelForKey(leagueKey) {
            if (!leagueKey || leagueKey === 'all') {
                return '';
            }

            if (leagueKey === activeLeagueFilter && catalogCompetitionMatchesActiveFilter()) {
                return activeCatalogCompetition.label;
            }

            const button = leagueFilterButtonByKey.get(leagueKey)
                || leagueFilterButtons.find(function (node) {
                    return (node.dataset.leagueFilter || '') === leagueKey;
                });

            if (!button) {
                return '';
            }

            return String(button.dataset.leagueFilterLabel || button.querySelector('span')?.textContent || '').trim();
        }

        function updateActiveFilterChip() {
            if (!activeFilterChip || !activeFilterLabel) {
                return;
            }

            const leagueLabel = leagueFilterLabelForKey(activeLeagueFilter);
            const hasLeagueFilter = activePrimaryTab === 'pre-match'
                && activeLeagueFilter !== 'all'
                && leagueLabel !== ''
                && !activeTimePriorityMode
                && !activeInterestPriorityMode;

            activeFilterChip.hidden = !hasLeagueFilter;
            activeFilterChip.classList.toggle('is-active', hasLeagueFilter);
            activeFilterChip.setAttribute('aria-pressed', hasLeagueFilter ? 'true' : 'false');
            activeFilterChip.setAttribute(
                'aria-label',
                hasLeagueFilter
                    ? t('clear_active_filter', 'Aktif turnuva filtresini temizle') + ': ' + leagueLabel
                    : t('clear_active_filter', 'Aktif turnuva filtresini temizle')
            );

            if (hasLeagueFilter) {
                activeFilterLabel.textContent = t('active_filter', 'Filtre') + ': ' + leagueLabel;
            } else {
                activeFilterLabel.textContent = t('active_filter', 'Filtre');
            }
        }

        function hideActiveFilterChip() {
            if (!activeFilterChip) {
                return;
            }

            activeFilterChip.hidden = true;
            activeFilterChip.classList.remove('is-active');
            activeFilterChip.setAttribute('aria-pressed', 'false');
            activeFilterChip.setAttribute('aria-label', t('clear_active_filter', 'Aktif turnuva filtresini temizle'));

            if (activeFilterLabel) {
                activeFilterLabel.textContent = t('active_filter', 'Filtre');
            }
        }

        function clearActiveLeagueFilter() {
            hideActiveFilterChip();

            if (activeLeagueFilter === 'all') {
                updateActiveFilterChip();
                return;
            }

            activeLeagueFilter = 'all';
            activeTimePriorityMode = false;
            activeInterestPriorityMode = false;
            activeMatchPage = 1;
            invalidateLiveHydrationState();
            updateUtilityButtons();
            updateLeagueFilterButtons();
            updateMatchPagination();
            scheduleBoardRefresh({
                detail: '',
                updateTabCounts: false,
            });
            queueInteractiveLiveRefresh(120, {
                filters: currentLiveOfferFilters(),
            });
        }

        function updateLeagueFilterButtons() {
            const countsByLeague = Object.assign({}, mergedCountsForTab(activePrimaryTab).byLeague);
            const transientCounts = countStateIsTransient();

            if (activeLeagueFilter !== 'all') {
                const activeLeagueButton = leagueFilterButtonByKey.get(activeLeagueFilter)
                    || leagueFilterButtons.find(function (button) {
                        return (button.dataset.leagueFilter || 'all') === activeLeagueFilter;
                    });
                const activeLeagueCount = countsByLeague[activeLeagueFilter] || 0;

                // The slim initial rail may not contain a valid Catalog competition yet.
                if (!catalogCompetitionMatchesActiveFilter() && (!activeLeagueButton
                    || (!transientCounts && activeLeagueCount <= 0)
                    || (activeSportFilter !== 'all'
                        && activeSportFilter !== 'upcoming'
                        && (activeLeagueButton.dataset.sportGroupKey || 'all') !== activeSportFilter))) {
                    activeLeagueFilter = 'all';
                }
            }

            let visibleButtonCount = 0;

            leagueFilterButtons.forEach(function (button) {
                const leagueKey = button.dataset.leagueFilter || 'all';
                const sportGroupKey = button.dataset.sportGroupKey || 'all';
                const rawCount = countsByLeague[leagueKey] || 0;
                const countNode = button.querySelector('strong');
                const matchesSport = activeSportFilter === 'all'
                    || activeSportFilter === 'upcoming'
                    || sportGroupKey === activeSportFilter;
                const isVisible = matchesSport && (rawCount > 0 || transientCounts);
                const isActive = isVisible && leagueKey === activeLeagueFilter;

                button.hidden = !isVisible;
                button.disabled = !isVisible;
                button.classList.toggle('is-active', isActive);
                button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                if (countNode) {
                    countNode.dataset.countRaw = String(rawCount);
                    countNode.textContent = formatCountLabel(rawCount);
                }

                if (isVisible) {
                    visibleButtonCount += 1;
                }
            });

            updateLeagueFilterGroups();

            if (leagueFilterPanel) {
                leagueFilterPanel.hidden = shouldHideOfferNavigationForEmptyState();
            }

            if (leagueFilterToggleButton) {
                leagueFilterToggleButton.setAttribute('aria-expanded', 'true');
                leagueFilterToggleButton.classList.toggle('is-active', activeLeagueFilter !== 'all');
            }

            updateCompetitionRailMeta();
            updateActiveFilterChip();
        }

        function updateToolbarPanelMode() {
            if (!toolbarPanel) {
                return;
            }

            if (activeToolbarPanel) {
                toolbarPanel.dataset.toolbarPanelMode = activeToolbarPanel;
            } else {
                delete toolbarPanel.dataset.toolbarPanelMode;
            }
        }

        function updateSearchPanelState() {
            const hasSearchValue = activeSearchQuery !== '';
            const searchPanelOpen = activeToolbarPanel === 'search';
            const offersTabActive = activePrimaryTab === 'pre-match' || activePrimaryTab === 'in-game';

            updateToolbarPanelMode();

            if (toolbarPanel) {
                toolbarPanel.hidden = !searchPanelOpen;
                toolbarPanel.setAttribute('aria-hidden', searchPanelOpen ? 'false' : 'true');
            }

            if (searchPanel) {
                searchPanel.hidden = !searchPanelOpen;
            }

            if (searchToggleButton) {
                searchToggleButton.setAttribute('aria-expanded', searchPanelOpen ? 'true' : 'false');
                searchToggleButton.classList.toggle('is-active', searchPanelOpen || hasSearchValue);
            }

            if (railSearchInput && railSearchInput.value !== activeSearchQuery) {
                railSearchInput.value = activeSearchQuery;
            }

            if (railSearchClear) {
                railSearchClear.hidden = !hasSearchValue;
            }

            if (eventSearchInput && eventSearchInput.value !== activeSearchQuery) {
                eventSearchInput.value = activeSearchQuery;
            }

            if (eventSearchClear) {
                eventSearchClear.hidden = !hasSearchValue;
            }

            if (eventSearchCount) {
                if (activePrimaryTab === 'bet-feed') {
                    if (!hasSearchValue) {
                        eventSearchCount.textContent = t('search_bet_feed_hint', 'Akıştaki maç, seçim veya kullanıcı metnine göre ara.');
                    } else {
                        const resultCount = applyBetFeedSearchState(activeSearchQuery);
                        eventSearchCount.textContent = t('results_found', ':count sonuç bulundu.', { count: formatCountLabel(resultCount) });
                    }
                } else if (!offersTabActive) {
                    eventSearchCount.textContent = '';
                } else if (!hasSearchValue) {
                    eventSearchCount.textContent = t('search_refine_hint', 'Takım, maç veya turnuva adıyla sonuçları daralt.');
                } else {
                    const mountedResultCount = filteredRowRecords(
                        activePhaseForTab(), activeSportFilter, activeLeagueFilter, activeSearchQuery
                    ).length;
                    const pagination = currentLiveOfferPaginationState();
                    const completeReturnedCount = pagination && !pagination.hasMore
                        ? pagination.returnedCount
                        : 0;
                    const authoritativeVisibleTotal = pagination && pagination.visibleTotalAuthoritative
                        ? pagination.visibleTotal
                        : 0;
                    const resultCount = Math.max(
                        mountedResultCount,
                        completeReturnedCount || 0,
                        authoritativeVisibleTotal || 0
                    );
                    eventSearchCount.textContent = t('results_found', ':count sonuç bulundu.', { count: formatCountLabel(resultCount) });
                }
            }

            document.documentElement.classList.toggle('sb2-search-modal-open', searchPanelOpen);
        }

        function betFeedSearchHaystack(row) {
            return String(row ? row.textContent || '' : '').toLocaleLowerCase('tr-TR');
        }

        function applyBetFeedSearchState(query) {
            const panel = document.querySelector('[data-tab-panel="bet-feed"]');
            const stream = panel ? panel.querySelector('[data-bet-feed-stream]') : null;
            if (!stream) {
                return 0;
            }

            const normalizedQuery = normalizeSearchQuery(query);
            const needles = normalizedQuery.split(/\s+/).filter(Boolean);
            const rows = Array.from(stream.querySelectorAll('.sb2-feed-item[data-item-id]:not([data-item-id=""])'));
            const existingEmpty = stream.querySelector('[data-bet-feed-search-empty]');
            let visibleCount = 0;

            if (existingEmpty) {
                existingEmpty.remove();
            }

            rows.forEach(function (row) {
                const isMatch = !needles.length || needles.every(function (needle) {
                    return betFeedSearchHaystack(row).indexOf(needle) !== -1;
                });

                row.hidden = !isMatch;
                if (isMatch) {
                    visibleCount += 1;
                }
            });

            if (needles.length && rows.length && visibleCount === 0) {
                const empty = document.createElement('div');
                empty.className = 'sb2-feed-empty';
                empty.dataset.betFeedSearchEmpty = '1';
                empty.textContent = t('no_matching_events', 'Eşleşen etkinlik bulunamadı.');
                stream.prepend(empty);
            }

            return visibleCount;
        }

        window.sportsbookShellApplyBetFeedSearch = function () {
            return applyBetFeedSearchState(activeSearchQuery);
        };

        function setToolbarPanel(panelName) {
            activeToolbarPanel = activeToolbarPanel === panelName ? null : panelName;
            updateSearchPanelState();
            updateLeagueFilterButtons();

            if (activeToolbarPanel === 'search' && (eventSearchInput || railSearchInput)) {
                window.setTimeout(function () {
                    if (eventSearchInput) {
                        eventSearchInput.focus();
                        eventSearchInput.select();
                        return;
                    }

                    railSearchInput.focus();
                    railSearchInput.select();
                }, 20);
            }
        }

        function updateMatchPagination() {
            cancelDeferredRowReveal();

            const rowRecords = syncTimePriorityRowLayout(
                filteredRowRecords(activePhaseForTab(), activeSportFilter, activeLeagueFilter, activeSearchQuery)
            );
            const rows = rowRecords.map(function (record) {
                return record.row;
            });
            const pendingHydration = !hydrationReady && scopeNeedsHydration(currentLiveOfferFilters());

            if (pendingHydration) {
                if (currentVisibleRowSet.size > 0 || visibleOfferRowCount() > 0) {
                    if (layout) {
                        layout.classList.add('is-scope-updating');
                    }
                    setInitialOffersEmptyStateVisible(false);
                    setBoardSkeletonVisible(false);
                    if (filterEmptyState) {
                        filterEmptyState.hidden = true;
                    }
                    updateMatchLoadMoreControl(currentVisibleRowSet.size, currentVisibleRowSet.size);
                    if (matchPageNumbers) {
                        matchPageNumbers.innerHTML = '';
                    }
                    if (matchPageSummary) {
                        matchPageSummary.textContent = t('events_updating', 'Etkinlikler güncelleniyor');
                    }
                    updateCompetitionRailMeta();
                    updateBrowserQueryState();
                    queueLeagueWindowRefresh();
                    rowVisibilityBatchActive = false;
                    return;
                }

                applyVisibleRowState(new Set());
                applyVisibleLeagueState(new Map());
                rebalanceLeagueOpenState();
                setInitialOffersEmptyStateVisible(false);
                setBoardSkeletonVisible(true);
                if (filterEmptyState) {
                    filterEmptyState.hidden = true;
                }
                updateMatchLoadMoreControl(0, 0);
                if (matchPageNumbers) {
                    matchPageNumbers.innerHTML = '';
                }
                if (matchPageSummary) {
                    matchPageSummary.textContent = t('events_loading', 'Etkinlikler yükleniyor');
                }
                updateCompetitionRailMeta();
                updateBrowserQueryState();
                queueLeagueWindowRefresh();
                rowVisibilityBatchActive = false;
                return;
            }

            if (layout) {
                layout.classList.remove('is-scope-updating');
            }

            if (!rows.length) {
                applyVisibleRowState(new Set());
                applyVisibleLeagueState(new Map());
                rebalanceLeagueOpenState();
                const showLoadingState = !hydrationReady;
                setInitialOffersEmptyStateVisible(false);
                setBoardSkeletonVisible(showLoadingState);
                if (filterEmptyState) {
                    filterEmptyState.hidden = showLoadingState;
                }
                updateMatchLoadMoreControl(0, 0);
                if (matchPageNumbers) {
                    matchPageNumbers.innerHTML = '';
                }
                if (matchPageSummary) {
                    matchPageSummary.textContent = '0 ' + t('events', 'etkinlik');
                }
                updateCompetitionRailMeta();
                updateBrowserQueryState();
                queueLeagueWindowRefresh();
                rowVisibilityBatchActive = false;
                return;
            }

            setBoardSkeletonVisible(false);
            setInitialOffersEmptyStateVisible(false);
            if (filterEmptyState) {
                filterEmptyState.hidden = true;
            }

            const visibleRows = new Set(rows);
            const batchPlan = rowBatchPlan(rowRecords);
            const immediateRows = batchPlan.immediateRows;
            const deferredRows = batchPlan.deferredRows;
            const visibleLeagueCountByNode = visibleLeagueCountsForRows(Array.from(immediateRows));

            applyVisibleRowState(immediateRows);
            applyVisibleLeagueState(visibleLeagueCountByNode);
            rebalanceLeagueOpenState();

            updateMatchLoadMoreControl(rows.length, immediateRows.size);

            if (matchPagePrev) {
                matchPagePrev.disabled = true;
            }

            if (matchPageNumbers) {
                matchPageNumbers.innerHTML = '';
            }
            updateCompetitionRailMeta();
            updateBrowserQueryState();
            queueLeagueWindowRefresh();

            if (deferredRows.length) {
                if (loadMoreAvailableForCurrentScope()) {
                    rowVisibilityBatchActive = false;
                    finishBoardRefreshingIfIdle();
                    return;
                }

                queueDeferredRowReveal(deferredRows);
                return;
            }

            rowVisibilityBatchActive = false;
            finishBoardRefreshingIfIdle();
        }

        function revealMatchPageForEvent(eventId) {
            if (!eventId) {
                return;
            }

            const rows = filteredMatchRows();
            const rowIndex = rows.findIndex(function (row) {
                return String(row.dataset.eventId || '') === String(eventId);
            });

            if (rowIndex === -1) {
                return;
            }

            updateMatchPagination();
        }

        function syncSportScrollButtons() {
            if (!sportStrip) {
                return;
            }

            const hasOverflow = sportStrip.scrollWidth > sportStrip.clientWidth + 4;
            const maxScrollLeft = Math.max(sportStrip.scrollWidth - sportStrip.clientWidth, 0);
            const atStart = sportStrip.scrollLeft <= 4;
            const atEnd = sportStrip.scrollLeft >= maxScrollLeft - 4;

            if (sportScrollPrev) {
                sportScrollPrev.hidden = !hasOverflow;
                sportScrollPrev.disabled = !hasOverflow || atStart;
            }

            if (sportScrollNext) {
                sportScrollNext.hidden = !hasOverflow;
                sportScrollNext.disabled = !hasOverflow || atEnd;
            }
        }

        function updateHistoryVisibility() {
            if (!historyItems) {
                return;
            }

            const items = Array.from(historyItems.querySelectorAll('[data-history-item]'));
            const filteredItems = items.filter(function (item) {
                return activeHistoryFilter === 'all' || item.dataset.historyCategory === activeHistoryFilter;
            });
            const totalPages = Math.max(Math.ceil(filteredItems.length / historyPageSize), 1);
            activeHistoryPage = Math.min(Math.max(activeHistoryPage, 1), totalPages);
            const start = (activeHistoryPage - 1) * historyPageSize;
            const end = start + historyPageSize;
            let visibleCount = 0;

            items.forEach(function (item) {
                const itemIndex = filteredItems.indexOf(item);
                const visible = itemIndex !== -1 && itemIndex >= start && itemIndex < end;
                item.hidden = !visible;
                if (visible) {
                    visibleCount += 1;
                }
            });

            historyItems.hidden = visibleCount === 0;

            if (historyEmpty) {
                historyEmpty.hidden = visibleCount !== 0;
            }

            if (historyPagination) {
                historyPagination.hidden = filteredItems.length <= historyPageSize;
            }

            if (historyPageLabel) {
                historyPageLabel.textContent = activeHistoryPage + ' / ' + totalPages;
            }

            if (historyPagePrev) {
                historyPagePrev.disabled = activeHistoryPage <= 1;
            }

            if (historyPageNext) {
                historyPageNext.disabled = activeHistoryPage >= totalPages;
            }

            document.querySelectorAll('[data-history-count="all"]').forEach(function (node) {
                node.textContent = String(items.length);
                if (node.classList.contains('sb2-my-bets-btn-count')) {
                    node.hidden = items.length === 0;
                }
            });
            document.querySelectorAll('[data-history-count="open"]').forEach(function (node) {
                node.textContent = String(items.filter(function (item) {
                    return item.dataset.historyCategory === 'open';
                }).length);
            });
        }

        function createHistoryItemElement(item) {
            if (!historyItems) {
                return null;
            }

            const element = document.createElement('div');
            element.className = 'sb2-history-item';
            element.dataset.historyItem = '1';
            element.dataset.historyCategory = item.category;
            element.innerHTML = `
                <div class="sb2-history-item-top">
                    <div>
                        <span class="sb2-history-item-kicker">${escapeHtml(t('history.bet_coupon', 'Bahis kuponu'))}</span>
                        <strong></strong>
                    </div>
                    <time></time>
                </div>
                <div class="sb2-history-item-mid">
                    <span></span>
                    <strong></strong>
                </div>
                <div class="sb2-history-item-metrics">
                    <span><small>${escapeHtml(t('history.stake_amount', 'Bahis tutarı'))}</small><strong></strong></span>
                    <span><small>${escapeHtml(t('history.odds', 'Oran'))}</small><strong></strong></span>
                </div>
                <footer class="sb2-history-item-footer">
                    <span class="sb2-history-status"></span>
                    <button type="button" class="sb2-history-detail-toggle" data-history-detail-toggle aria-expanded="false" aria-label="${escapeHtml(t('history.show_detail', 'Kupon ayrıntılarını göster'))}">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.9"/></svg>
                    </button>
                </footer>
                <div class="sb2-history-item-detail" data-history-item-detail hidden>
                    <span>${escapeHtml(t('history.short_reference', 'Kısa referans'))}</span>
                    <div class="sb2-history-ref">
                        <code></code>
                        <button type="button" class="sb2-history-copy" aria-label="${escapeHtml(t('history.copy_ref', 'Referansı kopyala'))}" title="${escapeHtml(t('history.copy_ref', 'Referansı kopyala'))}">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M9 9.5A2.5 2.5 0 0 1 11.5 7h5A2.5 2.5 0 0 1 19 9.5v7a2.5 2.5 0 0 1-2.5 2.5h-5A2.5 2.5 0 0 1 9 16.5v-7Zm-4 2A2.5 2.5 0 0 1 7.5 9" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"/>
                            </svg>
                        </button>
                    </div>
                </div>
            `;

            element.querySelector('.sb2-history-item-top strong').textContent = item.event_name;
            element.querySelector('.sb2-history-item-top time').textContent = item.requested_at_label;
            element.querySelector('.sb2-history-item-mid span').textContent = item.market_name;
            element.querySelector('.sb2-history-item-mid strong').textContent = item.outcome_name;
            element.querySelector('.sb2-history-item-metrics > span:first-child strong').textContent = item.stake_amount + ' ' + item.currency;
            element.querySelector('.sb2-history-item-metrics > span:last-child strong').textContent = item.accepted_price;
            element.querySelector('.sb2-history-status').textContent = item.status_label;
            element.querySelector('.sb2-history-item-detail code').textContent = item.short_acceptance_ref;
            element.querySelector('.sb2-history-copy').dataset.copyText = item.acceptance_ref;

            return element;
        }

        function upsertHistoryItemFromPayload(payload) {
            if (!historyItems || !payload) {
                return;
            }

            const item = {
                acceptance_ref: payload.acceptance_ref || '-',
                short_acceptance_ref: shortReferenceLabel(payload.acceptance_ref || '-'),
                event_name: (payload.event && payload.event.canonical_name) || (selected && selected.event_name) || '-',
                market_name: (payload.market && payload.market.display_name) || (selected && formatSlipMarketName(selected)) || 'Market',
                outcome_name: (payload.selection && payload.selection.display_name) || (selected && selected.outcome_name) || '-',
                stake_amount: formatDecimal(currentStake(), 2),
                currency: payload.currency || viewer.currency || 'TRY',
                accepted_price: formatDisplayOdd(payload.accepted_price || 0),
                requested_at_label: t('just_now', 'Az önce'),
                status_label: t('history.status_pending', 'Sonuç bekleniyor'),
                category: 'open',
            };

            const existing = Array.from(historyItems.querySelectorAll('[data-history-item]')).find(function (node) {
                const refNode = node.querySelector('code');
                return refNode && refNode.textContent === item.acceptance_ref;
            });
            const element = createHistoryItemElement(item);

            if (!element) {
                return;
            }

            if (existing) {
                existing.replaceWith(element);
            } else {
                historyItems.prepend(element);
            }

            activeHistoryPage = 1;
            updateHistoryVisibility();
        }

        function applySubmitButtonVisualState(state) {
            if (!submitButton) {
                return;
            }

            const palette = ({
                primary: {
                    background: '#4cab53',
                    borderColor: '#43984a',
                    boxShadow: '0 10px 20px rgba(34, 91, 39, 0.18)',
                    color: '#ffffff',
                },
                hover: {
                    background: '#43984a',
                    borderColor: '#3b8741',
                    boxShadow: '0 12px 24px rgba(34, 91, 39, 0.22)',
                    color: '#ffffff',
                },
                active: {
                    background: '#3b8741',
                    borderColor: '#34753a',
                    boxShadow: '0 7px 16px rgba(34, 91, 39, 0.18)',
                    color: '#ffffff',
                },
                locked: {
                    background: '#3f9546',
                    borderColor: '#34753a',
                    boxShadow: '0 8px 18px rgba(34, 91, 39, 0.16)',
                    color: '#ffffff',
                },
                disabled: {
                    background: '#dff1e3',
                    borderColor: '#b8dfbf',
                    boxShadow: 'inset 0 1px 0 rgba(255, 255, 255, 0.72)',
                    color: '#3f7f46',
                },
            })[state] || {
                background: '#dff1e3',
                borderColor: '#b8dfbf',
                boxShadow: 'inset 0 1px 0 rgba(255, 255, 255, 0.72)',
                color: '#3f7f46',
            };

            submitButton.style.setProperty('background-color', palette.background, 'important');
            submitButton.style.setProperty('background-image', 'none', 'important');
            submitButton.style.setProperty('border-color', palette.borderColor, 'important');
            submitButton.style.setProperty('box-shadow', palette.boxShadow, 'important');
            submitButton.style.setProperty('color', palette.color, 'important');
        }

        function clearSubmitProgressTimer() {
            if (submitProgressTimer) {
                window.clearTimeout(submitProgressTimer);
                submitProgressTimer = null;
            }
        }

        function submitProgressStageForElapsed(elapsedMs) {
            if (elapsedMs >= 5000) {
                return {
                    title: t('submit_wait_long_title', 'Biraz uzadı'),
                    message: t('submit_wait_long_body', 'Sağlayıcı yanıtı bekleniyor. Lütfen tekrar göndermeyin.'),
                };
            }

            if (elapsedMs >= 2000) {
                return {
                    title: t('accepting_bet_title', 'Bahis alınıyor'),
                    message: t('accepting_bet_body', 'Kupon son güvenlik kontrolünden geçiriliyor.'),
                };
            }

            return {
                title: t('quote_checking_title', 'Oran doğrulanıyor'),
                message: t('quote_checking_short', 'Oran sağlayıcıdan doğrulanıyor...'),
            };
        }

        function renderSubmitProgressStage() {
            if (submitState !== 'submitting') {
                clearSubmitProgressTimer();
                return;
            }

            const elapsedMs = Math.max(Date.now() - submitProgressStartedAt, 0);
            const stage = submitProgressStageForElapsed(elapsedMs);
            submitTitle = stage.title;
            submitMessage = stage.message;
            showInlineSubmitMessage(stage.message);
            renderResultState();
            updateSubmitButton();

            clearSubmitProgressTimer();
            const nextDelay = elapsedMs < 2000
                ? 2000 - elapsedMs
                : (elapsedMs < 5000 ? 5000 - elapsedMs : 1000);
            submitProgressTimer = window.setTimeout(renderSubmitProgressStage, Math.max(nextDelay, 250));
        }

        function startSubmitProgressTimer() {
            clearSubmitProgressTimer();
            submitProgressStartedAt = Date.now();
            renderSubmitProgressStage();
        }

        function updateSubmitButton() {
            if (!submitButton) {
                return;
            }
            if (offlineDemo.active || (selected && selected.acceptance_mode === 'offline_demo')) {
                submitButton.disabled = true;
                submitButton.textContent = t('offline_demo_title', 'DEMO');
                applySubmitButtonVisualState('disabled');
                if (submitHelper) {
                    submitHelper.hidden = false;
                    submitHelper.textContent = t('offline_demo_no_submit', '');
                }
                return;
            }

            const canAct = Boolean(selected) && currentStake() > 0;
            const selectionActionable = selectionIsActionable();
            let helperText = '';

            if (submitState === 'submitting' || submitState === 'retrying') {
                submitButton.textContent = submitTitle || t('quote_checking_title', 'Oran doğrulanıyor');
                submitButton.disabled = true;
                applySubmitButtonVisualState('locked');
                if (submitHelper) {
                    submitHelper.hidden = false;
                    submitHelper.textContent = submitMessage || t('request_once', 'İstek tek kez ve kontrollü biçimde gönderiliyor.');
                }
                return;
            }

            if (submitState === 'success' && frozenAfterSuccess) {
                submitButton.textContent = t('approved', 'Onaylandı');
                submitButton.disabled = true;
                applySubmitButtonVisualState('locked');
                if (submitHelper) {
                    submitHelper.hidden = false;
                    submitHelper.textContent = t('selection_locked', 'Bu seçim sabitlendi. Değiştirmek istersen önce seçimi yenile.');
                }
                return;
            }

            if (selected && !selectionActionable) {
                submitButton.textContent = selected.selection_state_reason === 'refreshing'
                    ? t('odds_refreshing', 'Oran yenileniyor.') : t('closed', 'Kapalı');
                submitButton.disabled = true;
                applySubmitButtonVisualState('disabled');
                helperText = selected.selection_state_message
                    ? selected.selection_state_message
                    : t('unavailable_market', 'Seçili market şu an bahis için uygun değil.');
            } else if (!canAct) {
                submitButton.textContent = t('submit', 'Bahis yap');
                submitButton.disabled = true;
                applySubmitButtonVisualState('disabled');
                helperText = '';
            } else if (hasInsufficientBalance()) {
                submitButton.textContent = t('insufficient_balance', 'Yetersiz bakiye');
                submitButton.disabled = true;
                applySubmitButtonVisualState('disabled');
                helperText = t('insufficient_balance_body', 'Bahis tutarı mevcut bakiyeni aşıyor.');
            } else if (!viewer.authenticated) {
                submitButton.textContent = t('login', 'Giriş yap');
                submitButton.disabled = false;
                applySubmitButtonVisualState('primary');
                helperText = '';
            } else if (viewer.balance <= 0) {
                submitButton.textContent = t('deposit', 'Para yatır');
                submitButton.disabled = false;
                applySubmitButtonVisualState('primary');
                helperText = '';
            } else {
                submitButton.textContent = t('submit', 'Bahis yap');
                submitButton.disabled = false;
                applySubmitButtonVisualState('primary');
                helperText = '';
            }

            if (submitHelper) {
                if (!helperText && inlineGateMessage && submitState === 'idle') {
                    helperText = inlineGateMessage;
                }
                submitHelper.hidden = helperText === '';
                submitHelper.textContent = helperText;
            }
        }

        function showInlineSubmitMessage(message) {
            inlineGateMessage = message || '';
            if (!submitHelper) {
                return;
            }

            submitHelper.hidden = !message;
            submitHelper.textContent = message || '';
        }

        function updateInteractiveState() {
            const shouldDisable = submitState === 'submitting' || submitState === 'retrying' || frozenAfterSuccess;

            if (slipShell) {
                slipShell.dataset.couponState = sportsbookCouponUiState(Boolean(selected), submitState);
            }

            refreshSelectionTriggers().forEach(function (button) {
                const disabledByState = button.classList.contains('is-disabled');
                button.disabled = shouldDisable || disabledByState;
            });

            removeButtons.forEach(function (button) {
                button.disabled = shouldDisable || !selected;
            });

            if (stakeInput) {
                stakeInput.disabled = shouldDisable || (Boolean(selected) && !selectionIsActionable());
            }

            if (fillBalanceButton) {
                fillBalanceButton.disabled = shouldDisable || (Boolean(selected) && !selectionIsActionable()) || !viewer.authenticated || viewer.balance <= 0;
            }

            if (autoAcceptOddsChangeToggle) {
                autoAcceptOddsChangeToggle.disabled = shouldDisable || (Boolean(selected) && !selectionIsActionable());
            }

            stakeChipButtons.forEach(function (button) {
                button.disabled = shouldDisable || (Boolean(selected) && !selectionIsActionable());
            });
        }

        function renderStakeSummary() {
            const stake = currentStake();
            const odd = selected ? oddNumericValue(selected) : 0;
            const possibleReturn = stake * odd;

            if (slipStake) {
                slipStake.textContent = formatCurrency(stake);
            }

            if (slipMultiplier) {
                slipMultiplier.textContent = odd > 0 ? t('odds_prefix', 'Oran') + ' ' + formatDisplayOdd(odd) + 'x' : t('odds_empty', 'Oran -');
            }

            if (slipReturn) {
                slipReturn.textContent = formatCurrency(possibleReturn);
            }

            updateStakeChipSelection();
            updateCollapsedSummary();
        }

        function clearActiveSelectionPresentation() {
            if (slipEvent) {
                slipEvent.textContent = '';
            }

            if (slipContext) {
                slipContext.textContent = '';
            }

            if (slipMarket) {
                slipMarket.textContent = '';
            }

            if (slipOutcome) {
                slipOutcome.textContent = '';
            }

            if (slipOdd) {
                slipOdd.textContent = '';
            }

            if (selectionCard) {
                selectionCard.hidden = true;
            }

            if (stakeCard) {
                stakeCard.hidden = true;
            }
        }

        function bahisbotTokenForSelection(selection) {
            return isAdapterQuoteRefSelection(selection) ? adapterSelectionToken(selection) : '';
        }

        function bahisbotKeyForSelection(selection) {
            const token = bahisbotTokenForSelection(selection);
            if (!token) {
                return '';
            }

            return [
                token,
                selection.event_id || '',
                selection.market_type || '',
                selection.market_specifier_key || 'default',
                selection.outcome_code || '',
            ].join('::');
        }

        function bahisbotTimeLabel(value) {
            const time = Date.parse(value || '');
            if (!Number.isFinite(time) || time > Date.now()) return '';
            const minutes = Math.floor((Date.now() - time) / 60000);
            try {
                const formatter = new Intl.RelativeTimeFormat(i18nLocale, {numeric: 'auto'});
                if (minutes < 1) return formatter.format(0, 'minute');
                if (minutes < 60) return formatter.format(-minutes, 'minute');
                const hours = Math.floor(minutes / 60);
                return hours < 24 ? formatter.format(-hours, 'hour') : formatter.format(-Math.floor(hours / 24), 'day');
            } catch (_) {
                return minutes < 60
                    ? t('minutes_ago', ':count minutes ago', {count: Math.max(1, minutes)})
                    : t('hours_ago', ':count hours ago', {count: Math.floor(minutes / 60)});
            }
        }

        function renderBahisbotIdentity(event) {
            if (bahisbotIdentity) {
                const categories = Array.isArray(event && event.categories) ? event.categories : [];
                const labels = {
                    women:t('bahisbot_category_women', 'Kadınlar'), female:t('bahisbot_category_women', 'Kadınlar'),
                    youth:t('bahisbot_category_youth', 'Gençler'), reserves:t('bahisbot_category_reserves', 'Rezervler'),
                    football:t('sport_soccer', 'Futbol'), soccer:t('sport_soccer', 'Futbol'), basketball:t('sport_basketball', 'Basketbol'),
                    tennis:t('sport_tennis', 'Tenis'), baseball:t('sport_baseball', 'Beyzbol'),
                    ice_hockey:t('sport_icehockey', 'Buz Hokeyi'), icehockey:t('sport_icehockey', 'Buz Hokeyi'),
                    table_tennis:t('sport_table_tennis', 'Masa Tenisi'), volleyball:t('sport_volleyball', 'Voleybol'), snooker:t('sport_snooker', 'Snooker')
                };
                const sport = String(event && event.sport || '').toLowerCase();
                bahisbotIdentity.textContent = [labels[sport] || labels[sport.split('_')[0]] || '', event && event.competition,
                    ...categories.filter(value => typeof value === 'string').map(value => labels[value.toLowerCase()] || value)].filter(Boolean).join(' · ');
                bahisbotIdentity.hidden = !bahisbotIdentity.textContent;
            }
            if (bahisbotMatch && event) bahisbotMatch.textContent = event.name || [event.home_team,event.away_team].filter(Boolean).join(' - ');
            if (bahisbotStart) {
                bahisbotStart.hidden = true;
                bahisbotStart.textContent = '';
                bahisbotStart.removeAttribute('datetime');
                if (event && Number.isFinite(Date.parse(event.starts_at))) {
                    try {
                        bahisbotStart.textContent = new Intl.DateTimeFormat(i18nLocale, {timeZone:event.timezone || 'UTC',dateStyle:'medium',timeStyle:'short'}).format(new Date(event.starts_at)) + ' (' + (event.timezone || 'UTC') + ')';
                        bahisbotStart.setAttribute('datetime',event.starts_at);
                        bahisbotStart.hidden = false;
                    } catch (_) { /* Invalid timezone must not invent a kickoff. */ }
                }
            }
        }

        function updateBahisbotRetry() {
            if (bahisbotRetryTimer) window.clearTimeout(bahisbotRetryTimer);
            bahisbotRetryTimer = null;
            if (bahisbotSessionExpired) {
                if (bahisbotRetryRow) bahisbotRetryRow.hidden = bahisbotClosed;
                if (bahisbotRetry) bahisbotRetry.disabled = true;
                if (bahisbotRetryLabel) bahisbotRetryLabel.textContent = t('bahisbot_session_expired', 'Oturum doğrulanamadı. Sayfayı yenileyip tekrar deneyin. Seçimin kuponda korunuyor.');
                return;
            }
            const status = bahisbotResearchResult && bahisbotResearchResult.status;
            if (!bahisbotClosed && status === 'card' && Number.isFinite(Date.parse(bahisbotResearchResult.valid_until))) {
                const remainingMs = Date.parse(bahisbotResearchResult.valid_until) - Date.now();
                if (remainingMs <= 0) {
                    bahisbotResearchResult = {status:'unavailable',card:null,meta:{error_reason:'expired'}};
                    bahisbotRetryAt = 0;
                    renderBahisbotResearch(bahisbotResearchResult);
                    updateBahisbotRetry();
                    return;
                }
                bahisbotRetryTimer = window.setTimeout(updateBahisbotRetry, Math.min(remainingMs, 3600000));
            }
            const visible = !bahisbotClosed && (bahisbotResearchPending || ['unavailable','no_result'].includes(status));
            if (bahisbotRetryRow) bahisbotRetryRow.hidden = !visible;
            const remaining = Math.max(0, Math.ceil((bahisbotRetryAt - Date.now()) / 1000));
            if (bahisbotRetry) bahisbotRetry.disabled = bahisbotResearchPending || bahisbotPreviewPending || remaining > 0 || !selected || !bahisbotTokenForSelection(selected);
            if (bahisbotRetryLabel) bahisbotRetryLabel.textContent = bahisbotResearchPending
                ? t('bahisbot_researching', 'Araştırılıyor…')
                : (remaining > 0
                    ? (remaining < 60
                        ? t('bahisbot_retry_in_seconds', 'Yeniden deneme :count sn sonra kullanılabilir.', {count: remaining})
                        : t('bahisbot_retry_in_minutes', 'Yeniden deneme :count dk sonra kullanılabilir.', {count: Math.ceil(remaining / 60)}))
                    : t('bahisbot_retry', 'Yeniden dene'));
            if (visible && !bahisbotResearchPending && remaining > 0) {
                bahisbotRetryTimer = window.setTimeout(updateBahisbotRetry, Math.min(remaining * 1000, 60000));
            }
        }

        function rememberBahisbotResearch(research) {
            bahisbotResearchResult = research;
            bahisbotResearchPending = research.status === 'pending';
            if (!bahisbotResearchPending) {
                const delay = Math.max(0, Math.min(3600, Number(research.retry_after_seconds) || 30));
                bahisbotRetryAt = Date.now() + delay * 1000;
            }
            updateBahisbotRetry();
        }

        function reuseBahisbotResearch() {
            const result = bahisbotResearchResult;
            if (!result || !bahisbotContext) return false;
            if (result.status === 'card') {
                const card = result.card || {};
                if (!card.source_url || !Number.isFinite(Date.parse(result.checked_at))
                    || Date.parse(result.checked_at) > Date.now() || Date.parse(result.valid_until) <= Date.now()
                    || !Number.isFinite(Date.parse(result.valid_until)) || Date.parse(bahisbotContext.event.starts_at) <= Date.now()) return false;
            } else if (!bahisbotResearchPending && bahisbotRetryAt <= Date.now()) return false;
            renderBahisbotResearch(result);
            return true;
        }

        function acceptBahisbotPreview(payload, token) {
            const event = payload.event;
            if (!event || !event.research_context || !selected || String(event.id) !== String(selected.event_id)) {
                bahisbotContext = null;
                if (!bahisbotClosed) renderBahisbotResearch({status:'unavailable',card:null});
                return;
            }
            if (!bahisbotContext || bahisbotContext.key !== event.research_context) {
                abortBahisbotResearch();
                bahisbotResearchRequestSequence++;
                bahisbotResearchResult = null;
                bahisbotResearchPending = false;
                bahisbotRetryAt = 0;
            }
            bahisbotContext = {key:event.research_context,event,token};
            renderBahisbotIdentity(event);
            if (bahisbotClosed) return;
            if (!reuseBahisbotResearch() && !bahisbotResearchPending) fetchBahisbotPreview(selected, true);
        }

        function retryBahisbotResearch() {
            if (bahisbotSessionExpired) return;
            if (bahisbotClosed || !selected || !bahisbotTokenForSelection(selected) || bahisbotResearchPending || bahisbotPreviewPending || Date.now() < bahisbotRetryAt) return;
            // Revalidate the current opaque selection first; never force cache eviction.
            bahisbotResearchResult = null;
            fetchBahisbotPreview(selected, false);
        }

        if (bahisbotRetry) bahisbotRetry.addEventListener('click', retryBahisbotResearch);
        if (bahisbotToggle) bahisbotToggle.addEventListener('click', function () {
            bahisbotClosed = !bahisbotClosed;
            bahisbotToggle.setAttribute('aria-expanded', String(!bahisbotClosed));
            bahisbotToggle.setAttribute('aria-label', bahisbotClosed ? t('bahisbot_open', "Bahisbot'u aç") : t('bahisbot_close', "Bahisbot'u kapat"));
            bahisbotToggle.title = bahisbotToggle.getAttribute('aria-label');
            if (bahisbotSelection) bahisbotSelection.hidden = bahisbotClosed || !selected;
            if (bahisbotClosed) {
                abortBahisbotResearch();
                bahisbotResearchRequestSequence++;
                if (bahisbotResearchPending) bahisbotResearchResult = null;
                bahisbotResearchPending = false;
                bahisbotPanel.setAttribute('aria-busy','false');
                setBahisbotProgress('');
            } else if (selected) fetchBahisbotPreview(selected, false);
            updateBahisbotRetry();
        });

        function setBahisbotProgress(stage) {
            if (!bahisbotProgress) {
                return;
            }

            const stages = ['match', 'sources', 'result'];
            const activeIndex = stages.indexOf(stage);
            bahisbotProgress.hidden = activeIndex < 0;
            bahisbotProgress.dataset.stage = activeIndex < 0 ? '' : stage;
            Array.from(bahisbotProgress.querySelectorAll('[data-bahisbot-progress-step]')).forEach(function (step, index) {
                step.classList.toggle('is-active', index === activeIndex);
                step.classList.toggle('is-complete', activeIndex >= 0 && index < activeIndex);
            });
        }

        function resetBahisbotResultSurfaces() {
            if (bahisbotPanel) {
                bahisbotPanel.setAttribute('aria-busy', 'false');
                if (bahisbotPanel.dataset) bahisbotPanel.dataset.researchStatus = '';
            }
            setBahisbotProgress('');
            if (bahisbotMovement) {
                bahisbotMovement.hidden = true;
            }

            if (bahisbotMovementText) {
                bahisbotMovementText.textContent = '';
            }
            if (bahisbotMovementLabel) {
                bahisbotMovementLabel.textContent = t('bahisbot_odds_movement', 'Oran hareketi');
            }
            if (bahisbotMovementCaveat) {
                bahisbotMovementCaveat.textContent = t('bahisbot_movement_unverified', 'Hareketin nedeni doğrulanmadı.');
            }

            if (bahisbotStatus) {
                bahisbotStatus.hidden = true;
                bahisbotStatus.textContent = '';
            }

            if (bahisbotCard) {
                bahisbotCard.hidden = true;
                bahisbotCard.removeAttribute('href');
            }
        }

        function renderBahisbotIdle() {
            bahisbotSelectionKey = '';
            bahisbotLatestPayload = null;
            bahisbotContext = null;
            bahisbotResearchResult = null;
            bahisbotResearchPending = false;
            bahisbotRetryAt = 0;
            renderBahisbotIdentity(null);
            updateBahisbotRetry();
            bahisbotPreviewRequestSequence++;
            bahisbotResearchRequestSequence++;
            abortBahisbotResearch();

            if (!bahisbotPanel) {
                return;
            }

            if (bahisbotIdle) {
                bahisbotIdle.hidden = false;
            }

            if (bahisbotSelection) {
                bahisbotSelection.hidden = true;
            }

            resetBahisbotResultSurfaces();
        }

        function abortBahisbotResearch() {
            if (bahisbotResearchAbortController) {
                bahisbotResearchAbortController.abort();
                bahisbotResearchAbortController = null;
            }
        }

        function bahisbotMarketStatusMessage(signal) {
            const state = signal && signal.state ? String(signal.state) : '';
            const minutes = Number(signal && signal.minutes ? signal.minutes : 0);
            const minuteCopy = minutes > 0 ? String(minutes) : '1';
            const from = signal && signal.price_from ? formatDisplayOdd(signal.price_from) : '';
            const to = signal && signal.price_to ? formatDisplayOdd(signal.price_to) : '';

            if (state === 'currently_unavailable') {
                return t('bahisbot_selection_unavailable', 'Bu seçim şu anda geçici olarak kullanılamıyor.');
            }

            if (state === 'reopened') {
                let message = t('bahisbot_selection_reopened', 'Bu seçim :minutes dakika önce yeniden açıldı.', {minutes: minuteCopy});
                if (from && to && from !== to) {
                    message += t('bahisbot_selection_reopened_odds', ' Oran :from’dan :to’ye güncellendi.', {from, to});
                }
                return message;
            }

            if (state === 'suspended') {
                return t('bahisbot_selection_suspended', 'Bu seçim :minutes dakika önce askıya alındı.', {minutes: minuteCopy});
            }

            return '';
        }

        function renderBahisbotMovement(signal) {
            if (!bahisbotMovement || !bahisbotMovementText || !signal) {
                if (bahisbotMovement) {
                    bahisbotMovement.hidden = true;
                }
                return;
            }

            if (signal.type === 'market_status_change') {
                const statusMessage = bahisbotMarketStatusMessage(signal);
                if (!statusMessage) {
                    bahisbotMovement.hidden = true;
                    return;
                }
                if (bahisbotMovementLabel) {
                    bahisbotMovementLabel.textContent = t('bahisbot_market_status', 'Market durumu');
                }
                if (bahisbotMovementCaveat) {
                    bahisbotMovementCaveat.textContent = t('bahisbot_reason_unverified', 'Nedeni doğrulanmadı.');
                }
                bahisbotMovementText.textContent = statusMessage;
                bahisbotMovement.hidden = false;
                return;
            }

            if (bahisbotMovementLabel) {
                bahisbotMovementLabel.textContent = t('bahisbot_odds_movement', 'Oran hareketi');
            }
            if (bahisbotMovementCaveat) {
                bahisbotMovementCaveat.textContent = t('bahisbot_movement_unverified', 'Hareketin nedeni doğrulanmadı.');
            }
            const from = formatDisplayOdd(signal.from);
            const to = formatDisplayOdd(signal.to);
            const minutes = Number(signal.minutes || 0);
            const directionCopy = signal.direction === 'drifted'
                ? t('bahisbot_odds_drifted', 'yükseldi')
                : t('bahisbot_odds_shortened', 'geriledi');

            bahisbotMovementText.textContent = t('bahisbot_odds_changed', 'Bu seçimin oranı son :minutes dakikada :from’den :to’ye :direction.', {
                minutes: minutes > 0 ? minutes : 1, from, to, direction: directionCopy
            });
            bahisbotMovement.hidden = false;
        }

        function renderBahisbotResearch(research) {
            const payload = research || {};
            if (payload !== bahisbotResearchResult) {
                rememberBahisbotResearch(payload);
                if (payload !== bahisbotResearchResult) return;
            }
            const card = payload.card || null;
            const status = payload.status || '';
            const timedOut = payload.meta && payload.meta.error_reason === 'timeout';
            const incomplete = payload.meta && payload.meta.error_reason === 'incomplete_response';
            const message = payload.meta && payload.meta.error_reason === 'session_expired'
                ? t('bahisbot_session_expired', 'Oturum doğrulanamadı. Sayfayı yenileyip tekrar deneyin. Seçimin kuponda korunuyor.')
                : status === 'no_result'
                ? t('bahisbot_no_result', 'Doğrulanmış güncel bir gelişme bulunamadı.')
                : (status === 'pending' ? t('bahisbot_pending', 'Araştırılıyor… Güncel kaynaklar kontrol ediliyor.') : (status === 'unavailable'
                    ? (payload.meta && payload.meta.error_reason === 'expired' ? t('bahisbot_expired', 'Araştırma sonucunun kontrol süresi doldu. Yeniden kontrol edebilirsin.') : (timedOut ? t('bahisbot_timeout', 'Araştırma zamanında tamamlanamadı. Seçimin kuponda korunuyor.') : (incomplete ? t('bahisbot_incomplete_coupon', 'Araştırma şu anda tamamlanamadı. Kuponun korunuyor.') : t('bahisbot_unavailable_coupon', 'Bahisbot araştırmasına şu anda ulaşılamıyor. Seçimin kuponda korunuyor.'))))
                    : (payload.message || '')));

            if (bahisbotPanel) {
                bahisbotPanel.setAttribute('aria-busy', status === 'pending' ? 'true' : 'false');
                if (bahisbotPanel.dataset) bahisbotPanel.dataset.researchStatus = status;
            }

            if (status !== 'pending') {
                setBahisbotProgress('');
            } else if (!bahisbotProgress || bahisbotProgress.hidden) {
                setBahisbotProgress('sources');
            }

            if (bahisbotStatus) {
                bahisbotStatus.hidden = !message;
                bahisbotStatus.textContent = message;
            }

            const cacheWindowSeconds = Number(payload.retry_after_seconds || 0);
            const showCacheWindow = ['card', 'no_result'].includes(status) && cacheWindowSeconds > 0;
            if (bahisbotCacheWindow) {
                bahisbotCacheWindow.hidden = !showCacheWindow;
                bahisbotCacheWindow.textContent = showCacheWindow
                    ? t('bahisbot_cache_window', 'Bu araştırma yaklaşık :count dk cache’den gösterilir.', {
                        count: Math.max(1, Math.ceil(cacheWindowSeconds / 60)),
                    })
                    : '';
            }

            if (!bahisbotCard) {
                return;
            }

            if (!card || payload.status !== 'card') {
                bahisbotCard.hidden = true;
                bahisbotCard.removeAttribute('href');
                return;
            }

            if (bahisbotCardTitle) {
                bahisbotCardTitle.textContent = card.title || t('bahisbot_title_current', 'Güncel gelişme');
            }
            if (bahisbotCardSummary) {
                bahisbotCardSummary.textContent = card.summary || '';
            }
            let source = null;
            try {
                const url = new URL(card.source_url);
                if (url.protocol === 'https:' && !url.username && !url.password) source = url;
            } catch (_) { /* No fabricated source link. */ }
            if (bahisbotCardSource) bahisbotCardSource.textContent = source
                ? [card.source_label || t('bahisbot_source', 'Kaynak'), source.hostname].join(' · ') : t('bahisbot_source_missing', 'Kaynak bilgisi yok');
            if (bahisbotNewsAge) bahisbotNewsAge.textContent = source
                ? t('bahisbot_news', 'Haber: :time', {time: bahisbotTimeLabel(card.published_at) || t('bahisbot_published_unknown', 'yayın zamanı belirtilmemiş')}) : '';
            if (bahisbotCheckAge) bahisbotCheckAge.textContent = source
                ? t('bahisbot_checked', 'Kontrol: :time', {time: bahisbotTimeLabel(payload.checked_at) || t('bahisbot_time_unknown', 'zaman bilgisi yok')}) : '';

            if (source) {
                bahisbotCard.setAttribute('href', source.href);
            } else {
                bahisbotCard.removeAttribute('href');
            }
            bahisbotCard.hidden = false;
        }

        function renderBahisbotPayload(payload, includeResearch) {
            bahisbotLatestPayload = payload || null;
            renderBahisbotMovement(payload && payload.status_signal ? payload.status_signal : (payload && payload.movement ? payload.movement : null));

            if (includeResearch) {
                const research = payload && payload.research ? payload.research : {
                    status: 'unavailable',
                    message: '',
                    card: null,
                };
                rememberBahisbotResearch(research);
                if (bahisbotContext && selected && bahisbotContext.token === bahisbotTokenForSelection(selected)) renderBahisbotResearch(research);
            }
        }

        function isCurrentBahisbotRequest(includeResearch, sequence, token, requestKey) {
            if (includeResearch) {
                return !bahisbotClosed && sequence === bahisbotResearchRequestSequence && !!selected && !!bahisbotContext
                    && !!bahisbotTokenForSelection(selected)
                    && bahisbotContext.key === requestKey && String(selected.event_id) === String(bahisbotContext.event.id);
            }
            return !(
                (includeResearch && sequence !== bahisbotResearchRequestSequence)
                || (!includeResearch && sequence !== bahisbotPreviewRequestSequence)
                || !selected
                || bahisbotTokenForSelection(selected) !== token
                || bahisbotKeyForSelection(selected) !== requestKey
            );
        }

        function bahisbotRequestPayload(token, includeResearch, cacheOnly) {
            return {
                selection_token: token,
                research: Boolean(includeResearch),
                cache_only: Boolean(cacheOnly),
            };
        }

        function bahisbotRequestHeaders() {
            const headers = {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'};
            // Laravel's current session cookie takes precedence over a cached HTML meta element.
            const cookie = document.cookie.split(';').map(function (part) { return part.trim(); })
                .find(function (part) { return part.startsWith('XSRF-TOKEN='); });
            if (cookie) {
                try {
                    const value = decodeURIComponent(cookie.slice('XSRF-TOKEN='.length));
                    if (value) { headers['X-XSRF-TOKEN'] = value; return headers; }
                } catch (_) { /* Fall back to the current document, never log token content. */ }
            }
            const meta = document.querySelector('meta[name="csrf-token"], meta[name="csrfToken"]');
            const token = meta ? meta.getAttribute('content') : '';
            if (token) headers['X-CSRF-TOKEN'] = token;
            return headers;
        }

        function handleBahisbotSessionFailure(response) {
            if (response.status !== 419) return false;
            bahisbotSessionExpired = true;
            abortBahisbotResearch();
            bahisbotPreviewRequestSequence += 1;
            bahisbotResearchRequestSequence += 1;
            bahisbotPreviewPending = false;
            renderBahisbotResearch({status: 'unavailable', card: null, meta: {error_reason: 'session_expired'}});
            return true;
        }

        function clearBahisbotResearchRequest(abortController, timeoutId) {
            if (timeoutId) {
                window.clearTimeout(timeoutId);
            }

            if (bahisbotResearchAbortController === abortController) {
                bahisbotResearchAbortController = null;
            }
        }

        function waitForBahisbotPoll(signal) {
            return new Promise(function (resolve, reject) {
                const onAbort = function () {
                    window.clearTimeout(timer);
                    reject(new DOMException('Aborted', 'AbortError'));
                };
                const timer = window.setTimeout(function () {
                    if (signal) {
                        signal.removeEventListener('abort', onAbort);
                    }
                    resolve();
                }, bahisbotCachePollDelayMs);
                if (!signal) {
                    return;
                }
                if (signal.aborted) {
                    window.clearTimeout(timer);
                    reject(new DOMException('Aborted', 'AbortError'));
                    return;
                }
                signal.addEventListener('abort', onAbort, {once: true});
            });
        }

        async function pollBahisbotCachedResearch(sequence, token, requestKey, abortController) {
            for (let attempt = 0; attempt < bahisbotCachePollMaxAttempts; attempt += 1) {
                await waitForBahisbotPoll(abortController ? abortController.signal : null);

                if (!isCurrentBahisbotRequest(true, sequence, token, requestKey)) {
                    return null;
                }

                const response = await fetch(bahisbotResearchUrl, {
                    method: 'POST',
                    headers: bahisbotRequestHeaders(),
                    credentials: 'same-origin',
                    signal: abortController ? abortController.signal : undefined,
                    body: JSON.stringify(bahisbotRequestPayload(token, true, true)),
                });
                if (handleBahisbotSessionFailure(response)) return null;
                const result = await response.json();

                if (!isCurrentBahisbotRequest(true, sequence, token, requestKey)) {
                    return null;
                }

                if (!response.ok || !result || !result.success || !result.data) {
                    renderBahisbotResearch({
                        status: 'unavailable',
                        message: '',
                        retry_after_seconds: Math.max(30, Number(response.headers.get('Retry-After')) || 0),
                        card: null,
                    });
                    return null;
                }

                renderBahisbotPayload(result.data, true);
                if (!result.data.research || result.data.research.status !== 'pending') {
                    return result.data;
                }
            }

            if (isCurrentBahisbotRequest(true, sequence, token, requestKey)) {
                renderBahisbotResearch({
                    status: 'unavailable',
                    message: t('bahisbot_poll_timeout', 'Araştırma henüz tamamlanamadı. Biraz sonra tekrar deneyin.'),
                    meta: {error_reason: 'timeout'},
                    card: null,
                });
            }

            return null;
        }

        async function fetchBahisbotPreview(selection, includeResearch) {
            if (offlineDemo.active || (selection && selection.acceptance_mode === 'offline_demo')) return null;
            if (bahisbotSessionExpired) {
                renderBahisbotResearch({status: 'unavailable', card: null, meta: {error_reason: 'session_expired'}});
                return null;
            }
            const token = bahisbotTokenForSelection(selection);
            if (!bahisbotResearchUrl || !token) {
                return null;
            }

            if (includeResearch && (!bahisbotContext || bahisbotClosed)) return null;
            const requestKey = includeResearch ? bahisbotContext.key : bahisbotKeyForSelection(selection);
            const sequence = includeResearch
                ? ++bahisbotResearchRequestSequence
                : ++bahisbotPreviewRequestSequence;

            if (includeResearch) {
                abortBahisbotResearch();
            } else {
                bahisbotPreviewPending = true;
                updateBahisbotRetry();
                if (!bahisbotClosed) {
                    setBahisbotProgress('match');
                    if (bahisbotStatus) { bahisbotStatus.textContent = t('bahisbot_match_checking', 'Maç bilgisi kontrol ediliyor…'); bahisbotStatus.hidden = false; }
                    if (bahisbotPanel) bahisbotPanel.setAttribute('aria-busy','true');
                    if (bahisbotCard) bahisbotCard.hidden = true;
                }
            }

            const abortController = includeResearch && typeof AbortController !== 'undefined'
                ? new AbortController()
                : null;
            let researchTimedOut = false;
            const researchTimeout = abortController
                ? window.setTimeout(function () {
                    researchTimedOut = true;
                    abortController.abort();
                }, 35000)
                : null;

            if (abortController) {
                bahisbotResearchAbortController = abortController;
            }

            if (includeResearch) {
                setBahisbotProgress('sources');
                renderBahisbotResearch({status: 'pending', card: null});
            }

            try {
                const response = await fetch(bahisbotResearchUrl, {
                    method: 'POST',
                    headers: bahisbotRequestHeaders(),
                    credentials: 'same-origin',
                    signal: abortController ? abortController.signal : undefined,
                    body: JSON.stringify(bahisbotRequestPayload(token, includeResearch, false)),
                });
                if (handleBahisbotSessionFailure(response)) return null;
                const result = await response.json();

                if (!isCurrentBahisbotRequest(includeResearch, sequence, token, requestKey)) {
                    return null;
                }

                if (!response.ok || !result || !result.success || !result.data) {
                    if (includeResearch || !bahisbotClosed) {
                        const retryAfter = Number(response.headers.get('Retry-After'));
                        renderBahisbotResearch({
                            status: 'unavailable',
                            message: '',
                            retry_after_seconds: Number.isFinite(retryAfter) && retryAfter > 0 ? retryAfter : 30,
                            card: null,
                        });
                    }
                    if (!includeResearch) { abortBahisbotResearch(); bahisbotResearchRequestSequence++; bahisbotContext = null; }
                    return null;
                }

                renderBahisbotPayload(result.data, includeResearch);
                if (!includeResearch) acceptBahisbotPreview(result.data, token);
                if (includeResearch && result.data.research && result.data.research.status === 'pending') {
                    setBahisbotProgress('result');
                    return await pollBahisbotCachedResearch(sequence, token, requestKey, abortController);
                }

                clearBahisbotResearchRequest(abortController, researchTimeout);
                return result.data;
            } catch (error) {
                clearBahisbotResearchRequest(abortController, researchTimeout);

                if (includeResearch && isCurrentBahisbotRequest(includeResearch, sequence, token, requestKey)) {
                    renderBahisbotResearch({
                        status: 'unavailable',
                        message: '',
                        meta: {error_reason: researchTimedOut ? 'timeout' : 'transport_failure'},
                        card: null,
                    });
                }
                if (!includeResearch && isCurrentBahisbotRequest(false, sequence, token, requestKey) && !bahisbotClosed) {
                    abortBahisbotResearch(); bahisbotResearchRequestSequence++; bahisbotContext = null;
                    renderBahisbotResearch({status:'unavailable',card:null});
                }

                return null;
            } finally {
                if (!includeResearch && sequence === bahisbotPreviewRequestSequence) { bahisbotPreviewPending = false; updateBahisbotRetry(); }
                if (includeResearch && bahisbotResearchAbortController === abortController) {
                    clearBahisbotResearchRequest(abortController, researchTimeout);
                }
            }
        }

        function renderBahisbotSelectionState() {
            if (!bahisbotPanel) {
                return;
            }

            if (!selected) {
                renderBahisbotIdle();
                return;
            }

            if (bahisbotIdle) {
                bahisbotIdle.hidden = true;
            }

            if (bahisbotSelection) {
                bahisbotSelection.hidden = bahisbotClosed;
            }

            if (bahisbotMatch) {
                bahisbotMatch.textContent = selected.event_name || '';
            }

            if (bahisbotPick) {
                bahisbotPick.textContent = [formatSlipMarketLine(selected), formatDisplayOdd(selected.raw_odd ?? selected.odd ?? '')].filter(Boolean).join(' · ');
            }

            const nextKey = bahisbotKeyForSelection(selected);
            if (!nextKey) {
                abortBahisbotResearch();
                bahisbotResearchRequestSequence++;
                bahisbotContext = null;
                bahisbotResearchResult = null;
                bahisbotResearchPending = false;
                renderBahisbotIdentity(null);
                updateBahisbotRetry();
                resetBahisbotResultSurfaces();
                if (bahisbotStatus) {
                    bahisbotStatus.hidden = false;
                    bahisbotStatus.textContent = t('bahisbot_safe_link_missing', 'Bu seçim için güvenli araştırma bağlantısı bulunamadı.');
                }
                return;
            }

            if (nextKey !== bahisbotSelectionKey) {
                bahisbotSelectionKey = nextKey;
                bahisbotLatestPayload = null;
                resetBahisbotResultSurfaces();
                renderBahisbotIdentity(null);
                if (!bahisbotClosed) fetchBahisbotPreview(selected, false);
            } else if (bahisbotLatestPayload) {
                renderBahisbotMovement(bahisbotLatestPayload.movement || null);
            }
        }

        function renderResultState() {
            if (!resultCard || !resultTitle || !resultText || !resultAction || !lastActionCard) {
                return;
            }

            if (activeSlipMode !== 'single') {
                resultCard.hidden = true;
                lastActionCard.hidden = true;
                return;
            }

            if (submitState === 'idle') {
                dismissedSuccessResult = false;
                resultCard.hidden = true;
                resultAction.hidden = true;
                if (resultClose) {
                    resultClose.hidden = true;
                }
                resultCard.dataset.state = '';
                submitTitle = '';
                resultActionIntent = 'retry';
                if (!lastAcceptedPayload) {
                    lastActionCard.hidden = true;
                } else {
                    lastActionCard.hidden = false;
                }
                return;
            }

            resultCard.hidden = false;
            resultAction.hidden = true;
            if (resultClose) {
                resultClose.hidden = submitState !== 'success';
            }
            resultCard.dataset.state = submitState;

            if (selected && !selectionIsActionable() && submitState !== 'submitting' && submitState !== 'success') {
                const reason = selected.selection_state_reason || 'unavailable';
                resultActionIntent = 'pick_another_selection';
                resultTitle.textContent = reason === 'refreshing'
                    ? t('odds_refreshing', 'Oran yenileniyor.')
                    : (reason === 'suspended'
                        ? t('market_suspended', 'Seçili market geçici olarak askıya alındı.')
                        : (['closed', 'ended', 'settled'].includes(reason)
                            ? t('closed_selection', 'Seçim kapandı')
                            : t('unavailable_market', 'Seçili market şu an bahis için uygun değil.')));
                resultText.textContent = selected.selection_state_message || t('unavailable_market', 'Seçili market şu an bahis için uygun değil.');
                return;
            }

            if (submitState === 'submitting') {
                dismissedSuccessResult = false;
                resultTitle.textContent = submitTitle || t('sending', 'Gönderiliyor');
                resultText.textContent = submitMessage || t('controlled_processing', 'Seçimin kontrollü biçimde işleniyor.');
                resultAction.hidden = true;
            } else if (submitState === 'success') {
                if (dismissedSuccessResult) {
                    resultCard.hidden = true;
                    return;
                }

                resultTitle.textContent = submitTitle || t('accepted', 'Bahis alındı');
                resultText.textContent = submitMessage || t('accepted_body', 'Kupon alındı. Son işlem özeti aşağıda.');
                resultAction.hidden = true;
                lastActionCard.hidden = false;
            } else if (submitState === 'verified') {
                dismissedSuccessResult = false;
                resultTitle.textContent = submitTitle || t('quote_verified_title', 'Oran doğrulandı');
                resultText.textContent = submitMessage || t('quote_verified_body', 'Seçili oran değişmedi. Kuponu güvenle onaylayabilirsin.');
                resultAction.hidden = true;
            } else if (submitState === 'refreshed') {
                dismissedSuccessResult = false;
                resultActionIntent = 'continue';
                resultTitle.textContent = submitTitle || t('quote_updated_title', 'Oran güncellendi');
                resultText.textContent = submitMessage || t('quote_updated_body', 'Seçim yenilendi. Devam etmek için tekrar onayla.', { old: '-', new: '-' });
                resultAction.hidden = false;
                resultAction.textContent = t('continue_current_odds', 'Güncel oranla devam et');
            } else if (submitState === 'error') {
                dismissedSuccessResult = false;
                resultTitle.textContent = submitTitle || t('action_failed', 'İşlem tamamlanamadı');
                resultText.textContent = submitMessage || t('selection_action_failed', 'Bu seçim şu anda tamamlanamadı.');
                configureErrorResultAction({
                    suggestedAction: resultActionIntent,
                });
            }
        }

        function renderSelectionState() {
            renderExactRefreshWarning();
            const hasSelection = Boolean(selected);
            const hasHistoryModeTab = slipModeButtons.some(function (button) {
                return (button.dataset.slipModeTab || 'single') === 'history';
            });
            const isHistoryMode = hasHistoryModeTab && activeSlipMode === 'history' && Boolean(historyCard);

            if (slipShell) {
                slipShell.classList.toggle('has-selection', hasSelection);
            }

            renderSlipModeState();
            applySlipModeVisibility();

            if (!hasSelection) {
                acceptedPayload = submitState === 'success' ? acceptedPayload : null;
                frozenAfterSuccess = false;
                clearActiveSelectionPresentation();
                renderResultState();
                updateActiveButtons();
                updateInteractiveState();
                updateSubmitButton();
                notifyShellSelectionChange();
                renderBahisbotSelectionState();
                updateCommunityShareState();
                return;
            }

            if (slipEvent) {
                slipEvent.textContent = selected.event_name || '';
            }

            if (slipContext) {
                const contextParts = [selected.context_sport, selected.context_time, selected.context_date].filter(Boolean);
                slipContext.textContent = contextParts.join(' • ') || '';
            }

            if (slipMarket) {
                slipMarket.textContent = formatSlipMarketLine(selected);
            }

            if (slipOutcome) {
                slipOutcome.textContent = selected.outcome_name || '';
            }

            if (slipOdd) {
                slipOdd.textContent = formatDisplayOdd(selected.raw_odd ?? selected.odd ?? '');
            }

            renderStakeSummary();
            renderResultState();
            updateActiveButtons();
            updateInteractiveState();
            updateSubmitButton();
            updateCollapsedSummary();
            notifyShellSelectionChange();
            renderBahisbotSelectionState();
            updateCommunityShareState();

            if (stakeInput && submitState === 'idle' && document.activeElement !== stakeInput) {
                window.setTimeout(function () {
                    stakeInput.focus();
                    stakeInput.select();
                }, 20);
            }
        }

        function updateCommunityShareState() {
            if (!communityShareButton) {
                return;
            }

            const canShare = Boolean(!offlineDemo.active && viewer.authenticated && communityShareUrl && selected
                && selected.event_id && selected.market_type && selected.outcome_code);
            communityShareButton.disabled = !canShare;
        }

        function splitEventTeams(eventName) {
            const rawName = String(eventName || '').trim();
            if (!rawName) {
                return [];
            }

            const separators = [' vs ', ' VS ', ' v ', ' V ', ' - ', ' – '];
            for (const separator of separators) {
                if (rawName.includes(separator)) {
                    return rawName.split(separator).map(function (part) {
                        return part.trim();
                    }).filter(Boolean).slice(0, 2);
                }
            }

            return [];
        }

        function formatSlipOutcomeName(selection) {
            const rawOutcome = String(selection && selection.outcome_name ? selection.outcome_name : '').trim();
            const normalized = rawOutcome.toLowerCase();
            const teams = splitEventTeams(selection && selection.event_name);

            if ((normalized === '1' || normalized === 'home') && teams[0]) {
                return teams[0];
            }

            if ((normalized === '2' || normalized === 'away') && teams[1]) {
                return teams[1];
            }

            if (normalized === 'x' || normalized === 'draw') {
                return 'Beraberlik';
            }

            return rawOutcome;
        }

        function formatSlipMarketName(selection) {
            const rawType = String(selection && selection.market_type ? selection.market_type : '').toLowerCase();
            const rawName = String(selection && selection.market_name ? selection.market_name : '').toLowerCase();
            const sportName = String(selection && selection.context_sport ? selection.context_sport : '').toLowerCase();
            const combined = `${rawType} ${rawName}`;
            const compactMoreLabel = String(selection && selection.more_label ? selection.more_label : '').trim();

            if (compactMoreLabel && (rawName.includes('ek market') || rawType.includes('extra_market'))) {
                return compactMoreLabel;
            }

            if (combined.includes('team_total')) {
                return t('team_total', 'Takım toplamı');
            }

            if (combined.includes('spread') || combined.includes('handicap') || combined.includes('handikap')) {
                return sportName.includes('tenis') || sportName.includes('tennis') ? t('game_handicap', 'Game handikap') : t('handicap', 'Handikap');
            }

            if (combined.includes('total')) {
                if (sportName.includes('basket') || sportName.includes('basketbol')) {
                    return t('total_points', 'Toplam sayı');
                }

                if (sportName.includes('tenis') || sportName.includes('tennis')) {
                    return t('total_games', 'Toplam oyun');
                }

                return t('total', 'Toplam');
            }

            if (combined.includes('btts')) {
                return t('btts', 'Karşılıklı gol');
            }

            if (combined.includes('double_chance')) {
                return t('double_chance', 'Çifte şans');
            }

            if (combined.includes('h2h') || combined.includes('match_result') || combined.includes('match result') || combined.includes('winner')) {
                return sportName.includes('tenis') || sportName.includes('tennis') ? t('market_tennis_winner', 'Maç kazananı') : t('market_result_lower', 'Maç sonucu');
            }

            return selection && selection.market_name ? selection.market_name : t('market_fallback', 'Market');
        }

        function formatSlipMarketLine(selection) {
            const marketName = formatSlipMarketName(selection);
            const outcomeName = formatSlipOutcomeName(selection);

            if (/^\+\d+/.test(marketName)) {
                return marketName;
            }

            return outcomeName ? `${marketName}, ${outcomeName}` : marketName;
        }

        function setActiveTab(tabKey) {
            const normalizedTabKey = tabKey === 'esports' ? 'e-sports' : tabKey;
            const nextTabKey = ['in-game', 'bet-feed', 'predictions', 'e-sports', 'social-market'].includes(normalizedTabKey) ? normalizedTabKey : 'pre-match';
            const previousTabKey = activePrimaryTab;
            const tabChanged = nextTabKey !== previousTabKey;

            if (tabChanged && activeSearchQuery !== '') {
                activeSearchQuery = '';
                activeToolbarPanel = null;
                updateSearchPanelState();
            }

            activePrimaryTab = nextTabKey;
            if ((activeTimePriorityMode || activeInterestPriorityMode) && activePrimaryTab !== 'pre-match') {
                activeTimePriorityMode = false;
                activeInterestPriorityMode = false;
                if (activeSportFilter === 'all') {
                    activeSportFilter = firstAvailableSportFilter(mergedCountsForTab(activePrimaryTab));
                }
            }
            const offersTabActive = activePrimaryTab === 'pre-match' || activePrimaryTab === 'in-game';

            tabTriggers.forEach(function (button) {
                const buttonKey = button.dataset.tab || button.dataset.tabTrigger || 'pre-match';
                const isActive = buttonKey === activePrimaryTab;
                button.classList.toggle('is-active', isActive);
                button.setAttribute('aria-selected', isActive ? 'true' : 'false');
            });

            tabSwitchers.forEach(function (button) {
                const buttonKey = button.dataset.tabSwitch || 'pre-match';
                const isActive = buttonKey === activePrimaryTab;
                button.classList.toggle('is-active', isActive);
                button.setAttribute('aria-selected', isActive ? 'true' : 'false');
            });
            updateSurfaceSportSwitchers();

            let matchedPanel = false;
            tabPanels.forEach(function (panel) {
                const panelKey = panel.dataset.tabPanel || '';
                const railOnlySurfaceMode = activePrimaryTab === 'e-sports' && panelKey === 'pre-match';
                const isActive = panelKey === activePrimaryTab
                    || (offersTabActive && panelKey === 'pre-match')
                    || railOnlySurfaceMode
                    || (tabPanels.length === 1 && !['pre-match', 'in-game', 'bet-feed', 'predictions', 'e-sports', 'social-market'].includes(panelKey));
                panel.hidden = !isActive;
                panel.classList.toggle('is-active', isActive);
                panel.classList.toggle('is-surface-rail-only', railOnlySurfaceMode);
                panel.setAttribute('aria-hidden', isActive ? 'false' : 'true');
                matchedPanel = matchedPanel || isActive;
            });

            if (!matchedPanel && tabPanels[0]) {
                tabPanels[0].hidden = false;
                tabPanels[0].classList.add('is-active');
                tabPanels[0].setAttribute('aria-hidden', 'false');
            }

            document.dispatchEvent(new CustomEvent('sportsbook-shell-tab-activated', {
                detail: {
                    tab: activePrimaryTab,
                    previousTab: previousTabKey,
                    changed: tabChanged,
                },
            }));
            window.setTimeout(function () {
                if (activePrimaryTab === 'bet-feed' && typeof window.sportsbookShellEnsureBetFeedLoaded === 'function') {
                    window.sportsbookShellEnsureBetFeedLoaded();
                }
            }, 0);

            if (['bet-feed', 'predictions', 'e-sports', 'social-market'].includes(activePrimaryTab)) {
                activeTimePriorityMode = false;
                activeInterestPriorityMode = false;
                activeLeagueFilter = 'all';
                hideActiveFilterChip();
                if (tabChanged) {
                    invalidateLiveHydrationState(previousTabKey);
                }
                updateUtilityButtons();
                return;
            }

            if (tabChanged) {
                invalidateLiveHydrationState(previousTabKey);
            }

            if (tabChanged) {
                activeMatchPage = 1;
                scheduleBoardRefresh({
                    detail: '',
                    updateTabCounts: activeSearchQuery !== '',
                });
                queueInteractiveLiveRefresh(60, {
                    interrupt: true,
                });
            }
        }

        function resetSubmissionState(preserveSelection) {
            clearSuccessResetTimer();
            clearSubmitProgressTimer();
            if (!preserveSelection) {
                acceptedPayload = null;
                currentIdempotencyKey = null;
                frozenAfterSuccess = false;
                dismissedSuccessResult = false;
            }
            inlineGateMessage = '';
            submitState = 'idle';
            submitTitle = '';
            submitMessage = '';
            renderResultState();
            updateSubmitButton();
        }

        function clearSelection() {
            clearSuccessResetTimer();
            clearSubmitProgressTimer();
            selected = null;
            acceptedPayload = null;
            frozenAfterSuccess = false;
            currentIdempotencyKey = null;
            submitState = 'idle';
            submitTitle = '';
            submitMessage = '';
            inlineGateMessage = '';
            dismissedSuccessResult = false;

            if (stakeInput) {
                stakeInput.value = '';
            }

            clearActiveSelectionPresentation();

            if (resultCard) {
                resultCard.hidden = true;
                resultCard.dataset.state = '';
            }

            if (lastActionCard) {
                lastActionCard.hidden = !lastAcceptedPayload;
            }

            if (collapsedSummary) {
                collapsedSummary.hidden = !slipCollapsed;
            }

            renderStakeSummary();
            renderSelectionState();

            if (slipViewportMode === 'mobile') {
                setSlipCollapsed(true);
            }
        }

        function handleCopyAction(button) {
            const value = button ? (button.dataset.copyText || '') : '';
            if (!value) {
                return;
            }

            const markCopied = function () {
                button.classList.add('is-copied');
                window.setTimeout(function () {
                    button.classList.remove('is-copied');
                }, 1200);
            };

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(value).then(markCopied).catch(markCopied);
                return;
            }

            markCopied();
        }

        function setClosedNote(row, message, hidden) {
            const note = row ? row.querySelector('[data-betting-closed-note]') : null;
            if (!note) {
                return;
            }

            note.hidden = Boolean(hidden);
            if (!hidden && message) {
                note.textContent = message;
            }
        }

        function pulseOddChange(button, direction) {
            if (!button || !direction) {
                return;
            }

            const className = direction === 'up' ? 'is-odd-up' : 'is-odd-down';
            button.classList.remove('is-odd-up', 'is-odd-down');
            button.classList.add(className);

            window.setTimeout(function () {
                button.classList.remove(className);
            }, 920);
        }

        function selectionStateMessageFromReason(reason) {
            if (reason === 'closed') {
                return t('closed_selection', 'Seçim kapandı');
            }

            if (reason === 'presentation_incomplete') {
                return t('odds_resolving', 'Oranlar doğrulanıyor…');
            }

            if (reason === 'ended') {
                return t('market_resulted', 'Seçili market sonuçlandı ve artık kapalı.');
            }

            if (reason === 'suspended') {
                return t('market_suspended', 'Seçili market geçici olarak askıya alındı.');
            }

            if (reason === 'settled') {
                return t('market_resulted', 'Seçili market sonuçlandı ve artık kapalı.');
            }

            if (reason === 'market_acceptance_rollout_disabled' || reason === 'market_settlement_rollout_disabled' || reason === 'market_not_bettable') {
                return t('unavailable_market', 'Seçili market şu an bahis için uygun değil.');
            }

            if (reason === 'market_outside_canary_scope') {
                return t('unavailable_market', 'Seçili market şu an bahis için uygun değil.');
            }

            if (reason === 'odds_not_available') {
                return t('market_unavailable', 'Bu market için henüz oran yok');
            }

            return t('unavailable_market', 'Seçili market şu an bahis için uygun değil.');
        }

        function setSelectionButtonState(button, options) {
            if (!button) {
                return;
            }

            const reason = options.reason || '';
            const nextRawOdd = options.rawOdd ?? options.odd ?? '-';
            const nextOdd = formatDisplayOdd(nextRawOdd);
            const oldPayload = JSON.parse(button.dataset.selection || '{}');
            const previousSelectionToken = adapterSelectionToken(oldPayload);
            const incomingSelectionToken = String(options.selectionToken || '').trim();
            const candidate = Object.assign({}, oldPayload, {
                raw_odd: nextRawOdd,
                market_line: options.marketLine !== undefined ? options.marketLine : (oldPayload.market_line ?? null),
                selection_token: incomingSelectionToken || previousSelectionToken,
            });
            const isDisabled = Boolean(options.disabled) || !selectionPresentationReady(candidate);
            const presentationLabel = totalSelectionLabel(candidate);
            const preservesExistingAdapterContract = previousSelectionToken !== ''
                && (incomingSelectionToken === '' || incomingSelectionToken === previousSelectionToken);
            const oldRawOdd = String(oldPayload.raw_odd ?? oldPayload.odd ?? '');
            const disabledMessage = isDisabled
                ? (options.selectionStateMessage || oldPayload.selection_state_message || selectionStateMessageFromReason(reason))
                : '';
            const nextPayload = Object.assign({}, oldPayload, {
                raw_odd: nextRawOdd,
                odd: nextOdd,
                market_line: candidate.market_line,
                winx_edge_pct: options.winxEdgePct !== undefined ? options.winxEdgePct : oldPayload.winx_edge_pct,
                quote_selection_id: options.quoteSelectionId ?? oldPayload.quote_selection_id,
                selection_token: incomingSelectionToken || previousSelectionToken,
                acceptance_mode: preservesExistingAdapterContract
                    ? oldPayload.acceptance_mode
                    : (options.acceptanceMode || oldPayload.acceptance_mode || 'legacy_quote_selection'),
                quote_version_id: options.quoteVersionId || oldPayload.quote_version_id,
                outcome_name: presentationLabel || options.outcomeName || oldPayload.outcome_name,
                label: presentationLabel || oldPayload.label,
                quote_payload_fetched_at: preservesExistingAdapterContract
                    ? (oldPayload.quote_payload_fetched_at || pageBootedAt)
                    : (options.payloadFetchedAt || oldPayload.quote_payload_fetched_at || pageBootedAt),
                quote_checked_at: preservesExistingAdapterContract
                    ? (oldPayload.quote_checked_at || '')
                    : (options.refreshedAt || oldPayload.quote_checked_at || ''),
                selection_key: options.quoteSelectionId
                    ? 'selection-' + options.quoteSelectionId
                    : oldPayload.selection_key,
                selectable: !isDisabled,
                selection_state_message: disabledMessage,
            });

            button.dataset.selection = JSON.stringify(nextPayload);
            button.classList.toggle('is-disabled', isDisabled);
            button.dataset.disabledReason = reason;
            button.disabled = isDisabled || submitState === 'submitting' || frozenAfterSuccess;
            button.setAttribute('aria-disabled', isDisabled ? 'true' : 'false');
            button.title = disabledMessage;

            const value = button.querySelector('.sb2-odd-box-value');
            const label = button.querySelector('.sb2-odd-box-label');
            if (label && presentationLabel) {
                label.textContent = presentationLabel;
            }
            if (value) {
                value.textContent = nextOdd;
            }
            syncWinxEdgeBadge(button, nextPayload.winx_edge_pct);

            if (!isDisabled && oldRawOdd && oldRawOdd !== String(nextRawOdd)) {
                const oldValue = Number(String(oldRawOdd).replace(',', '.'));
                const newValue = Number(String(nextRawOdd).replace(',', '.'));
                if (Number.isFinite(oldValue) && Number.isFinite(newValue) && oldValue !== newValue) {
                    pulseOddChange(button, newValue > oldValue ? 'up' : 'down');
                }
            }
        }

        function deriveEventPhase(event) {
            if (!event) {
                return 'pre-match';
            }

            const lifecycleStatus = String(event.lifecycle_status || '');
            if (['settled', 'cancelled', 'finished', 'completed', 'closed'].includes(lifecycleStatus)) {
                return 'ended';
            }

            if (event.is_live === true || ['started', 'live', 'inplay'].includes(lifecycleStatus)) {
                return 'in-game';
            }

            return 'pre-match';
        }

        function resolvedEventPhase(shellEvent, eventPayload) {
            const shellPhase = shellEvent && shellEvent.phase_key ? shellEvent.phase_key : '';
            const derivedPhase = deriveEventPhase(eventPayload);

            if (shellPhase === 'ended' || derivedPhase === 'ended') {
                return 'ended';
            }

            if (shellPhase === 'in-game' || derivedPhase === 'in-game') {
                return 'in-game';
            }

            if (shellPhase === 'pre-match' || derivedPhase === 'pre-match') {
                return 'pre-match';
            }

            return shellPhase || derivedPhase || 'pre-match';
        }

        function updateSelectedFromLiveState(row, eventPayload, selectedButtonState) {
            if (selected && selected.acceptance_mode === 'offline_demo') return;
            if (!selected || !row) {
                return;
            }

            const selectedEventId = String(selected.event_id || '');
            if (selectedEventId !== String(row.dataset.eventId || '')) {
                return;
            }

            if (!selectedButtonState || selectedButtonState.disabled) {
                selected = Object.assign({}, selected, {
                    selectable: false,
                    selection_state_reason: selectedButtonState ? selectedButtonState.reason : 'ended',
                    selection_state_message: selectionStateMessageFromReason(selectedButtonState ? selectedButtonState.reason : 'ended'),
                });
                submitState = 'refreshed';
                submitTitle = t('closed_selection', 'Seçim kapandı');
                submitMessage = selected.selection_state_message;
                renderSelectionState();
                return;
            }

            const oldOdd = String(selected.odd || '');
            const previousSelectionToken = adapterSelectionToken(selected);
            const incomingSelectionToken = String(selectedButtonState.selectionToken || '').trim();
            const preservesExistingAdapterContract = previousSelectionToken !== ''
                && (incomingSelectionToken === '' || incomingSelectionToken === previousSelectionToken);
            selected = Object.assign({}, selected, {
                raw_odd: selectedButtonState.rawOdd ?? selected.raw_odd ?? selected.odd,
                odd: formatDisplayOdd(selectedButtonState.rawOdd ?? selectedButtonState.odd),
                quote_selection_id: selectedButtonState.quoteSelectionId ?? selected.quote_selection_id,
                selection_token: incomingSelectionToken || previousSelectionToken,
                acceptance_mode: preservesExistingAdapterContract
                    ? selected.acceptance_mode
                    : (selectedButtonState.acceptanceMode || selected.acceptance_mode || 'legacy_quote_selection'),
                quote_version_id: selectedButtonState.quoteVersionId || selected.quote_version_id,
                market_line: selectedButtonState.marketLine,
                outcome_name: selectedButtonState.outcomeName || selected.outcome_name,
                quote_payload_fetched_at: preservesExistingAdapterContract
                    ? (selected.quote_payload_fetched_at || pageBootedAt)
                    : (selectedButtonState.payloadFetchedAt || selected.quote_payload_fetched_at || pageBootedAt),
                quote_checked_at: preservesExistingAdapterContract
                    ? (selected.quote_checked_at || '')
                    : (selectedButtonState.refreshedAt || selected.quote_checked_at || ''),
                selection_key: selectedButtonState.quoteSelectionId
                    ? 'selection-' + selectedButtonState.quoteSelectionId
                    : selected.selection_key,
                selectable: true,
                selection_state_reason: '',
                selection_state_message: '',
            });

            if (oldOdd !== String(selected.odd || '')) {
                submitState = 'refreshed';
                submitTitle = t('quote_updated_title', 'Oran güncellendi');
                submitMessage = t('quote_refreshed_continue', 'Seçili oranın fiyatı güncellendi. Kupon güncel oranla devam ediyor.');
            }

            renderSelectionState();
        }

        function patchMatchRowFromEvent(row, eventPayload, shellEvent) {
            if (!row || !eventPayload) {
                return;
            }
            if (shellEvent && shellEvent.offline_demo && offlineDemo.active) {
                if (row.dataset.offlineDemo !== '1') {
                    const demoRow = buildMatchRowElement(shellEvent);
                    row.innerHTML = demoRow.innerHTML;
                    Object.assign(row.dataset, demoRow.dataset);
                }
                return;
            }
            row.dataset.offlineDemo = '0';

            const phase = resolvedEventPhase(shellEvent, eventPayload);
            const shellTradeable = shellEvent ? shellEvent.is_tradeable !== false : true;
            const isTradeable = Boolean(eventPayload.front_betting_enabled) && Boolean((((eventPayload || {}).quote || {}).is_tradeable)) && shellTradeable;
            const phaseBadge = row.querySelector('[data-event-phase-badge]');
            const timeLabel = row.querySelector('[data-time-pill-label]');
            const allButtons = Array.from(row.querySelectorAll('[data-selection-trigger]'));
            const markets = Array.isArray((((eventPayload || {}).quote || {}).markets)) ? eventPayload.quote.markets : [];
            const selectionIndex = new Map();
            const payloadFetchedAt = Date.now();

            markets.forEach(function (market) {
                if (!Array.isArray(market.selections)) {
                    return;
                }

                market.selections.forEach(function (selection) {
                    selectionIndex.set([market.market_type, market.specifier_key || 'default', selection.outcome_code].join('::'), {
                        odd: selection.decimal_price,
                        quoteSelectionId: selection.quote_selection_id,
                        selectionToken: adapterSelectionToken(selection),
                        acceptanceMode: selection.acceptance_mode || 'legacy_quote_selection',
                        quoteVersionId: (((eventPayload || {}).quote || {}).quote_version_id) || null,
                        outcomeName: selection.display_name,
                        marketLine: market.line ?? null,
                        winxEdgePct: normalizedWinxEdgePct(selection),
                        payloadFetchedAt: payloadFetchedAt,
                        marketStatus: market.status || 'open',
                        offerStatus: selection.offer_status || 'open',
                        bettable: selection.bettable !== false && market.bettable !== false,
                        bettableReason: selection.bettable_reason || market.bettable_reason || '',
                        selectionStateMessage: selection.selection_state_message || market.selection_state_message || '',
                    });
                });
            });
            if (shellEvent) {
                addShellSelectionGroup(selectionIndex, shellEvent.match_result, payloadFetchedAt);
                addShellSelectionGroup(selectionIndex, shellEvent.totals, payloadFetchedAt);
                (Array.isArray(shellEvent.extra_markets) ? shellEvent.extra_markets : []).forEach(function (market) {
                    addShellSelectionGroup(selectionIndex, market.selections, payloadFetchedAt);
                });
            }

            row.dataset.eventPhase = phase;
            row.dataset.eventLifecycleStatus = String(eventPayload.lifecycle_status || '');
            row.dataset.eventTradingStatus = String(eventPayload.trading_status || '');
            if (phase !== activePhaseForTab()) {
                row.hidden = true;
            }
            row.dataset.startsAt = String(
                (shellEvent && shellEvent.starts_at_timestamp)
                || (eventPayload.starts_at ? Math.floor(Date.parse(eventPayload.starts_at) / 1000) : row.dataset.startsAt || '')
            );
            row.dataset.eventName = String(eventPayload.canonical_name || row.dataset.eventName || '');

            if (shellEvent) {
                row.dataset.sportKey = shellEvent.sport_group_key || row.dataset.sportKey || 'other';
                row.dataset.leagueFilterKey = shellEvent.league_filter_key || row.dataset.leagueFilterKey || 'turnuva';
                row.dataset.searchText = rowSearchText(shellEvent);

                const leagueTag = row.querySelector('.sb2-match-tag');
                if (leagueTag) {
                    leagueTag.textContent = shellEvent.league_title || leagueTag.textContent;
                }

                const teamLines = row.querySelector('.sb2-team-lines');
                if (teamLines) {
                    const teamLineNodes = teamLines.querySelectorAll('div');
                    if (teamLineNodes[0]) {
                        teamLineNodes[0].textContent = shellEvent.home || teamLineNodes[0].textContent;
                    }
                    if (teamLineNodes[1]) {
                        teamLineNodes[1].textContent = shellEvent.away || teamLineNodes[1].textContent;
                    }
                }
            }

            if (phaseBadge) {
                const isLive = phase === 'in-game';
                phaseBadge.textContent = phase === 'ended' ? t('phase_ended', 'Bitti') : (isLive ? t('phase_live', 'Canlı') : 'Pre-match');
                phaseBadge.classList.toggle('is-live', isLive);
                phaseBadge.classList.toggle('is-ended', phase === 'ended');
            }

            if (timeLabel) {
                if (phase === 'in-game') {
                    timeLabel.textContent = t('phase_live', 'Canlı');
                } else if (eventPayload.starts_at) {
                    const localized = formatDisplayTime(eventPayload.starts_at);
                    if (localized) {
                        timeLabel.textContent = localized;
                    }
                }
            }

            let selectedButtonState = null;

            allButtons.forEach(function (button) {
                const marketType = button.dataset.marketType || '';
                const marketSpecifier = button.dataset.marketSpecifier || 'default';
                const outcomeCode = button.dataset.outcomeCode || '';
                const nextState = selectionIndex.get([marketType, marketSpecifier, outcomeCode].join('::'));
                const currentPayload = JSON.parse(button.dataset.selection || '{}');
                const presentationReady = selectionPresentationReady(Object.assign({}, currentPayload, {
                    raw_odd: nextState ? nextState.odd : currentPayload.raw_odd,
                    market_line: nextState ? nextState.marketLine : currentPayload.market_line,
                    selection_token: nextState ? (nextState.selectionToken || currentPayload.selection_token) : currentPayload.selection_token,
                }));
                const shouldDisable = !nextState || !isTradeable || nextState.marketStatus !== 'open' || nextState.offerStatus !== 'open' || nextState.bettable === false
                    || !presentationReady;
                let reason = '';
                if (phase === 'ended') {
                    reason = 'ended';
                } else if (['closed', 'settled', 'cancelled'].includes(String(eventPayload.trading_status || '').toLowerCase())) {
                    reason = 'closed';
                } else if (!isTradeable) {
                    reason = 'suspended';
                } else if (!nextState) {
                    reason = 'settled';
                } else if (nextState.bettable === false) {
                    reason = nextState.bettableReason || 'market_not_bettable';
                } else if (['closed', 'settled', 'cancelled'].includes(nextState.marketStatus)
                    || ['closed', 'settled', 'cancelled'].includes(nextState.offerStatus)) {
                    reason = 'closed';
                } else if (nextState.marketStatus !== 'open' || nextState.offerStatus !== 'open') {
                    reason = 'suspended';
                } else if (!presentationReady) {
                    reason = 'presentation_incomplete';
                }

                setSelectionButtonState(button, {
                    disabled: shouldDisable,
                    reason: reason,
                    rawOdd: nextState ? nextState.odd : (JSON.parse(button.dataset.selection || '{}').raw_odd || JSON.parse(button.dataset.selection || '{}').odd || '-'),
                    odd: nextState ? nextState.odd : (JSON.parse(button.dataset.selection || '{}').odd || '-'),
                    quoteSelectionId: nextState ? nextState.quoteSelectionId : null,
                    selectionToken: nextState ? nextState.selectionToken : null,
                    acceptanceMode: nextState ? nextState.acceptanceMode : null,
                    quoteVersionId: nextState ? nextState.quoteVersionId : null,
                    outcomeName: nextState ? nextState.outcomeName : null,
                    marketLine: nextState ? nextState.marketLine : null,
                    winxEdgePct: nextState ? nextState.winxEdgePct : null,
                    selectionStateMessage: nextState ? nextState.selectionStateMessage : null,
                    payloadFetchedAt: nextState ? nextState.payloadFetchedAt : null,
                });

                if (selected && String(selected.event_id || '') === String(row.dataset.eventId || '')
                    && String(selected.market_type || '') === marketType
                    && String(selected.market_specifier_key || 'default') === marketSpecifier
                    && String(selected.outcome_code || '') === outcomeCode) {
                    selectedButtonState = {
                        disabled: shouldDisable,
                        reason: reason,
                        rawOdd: nextState ? nextState.odd : null,
                        odd: nextState ? formatDisplayOdd(nextState.odd) : null,
                        quoteSelectionId: nextState ? nextState.quoteSelectionId : null,
                        selectionToken: nextState ? nextState.selectionToken : null,
                        acceptanceMode: nextState ? nextState.acceptanceMode : null,
                        quoteVersionId: nextState ? nextState.quoteVersionId : null,
                        outcomeName: nextState ? nextState.outcomeName : null,
                        marketLine: nextState ? nextState.marketLine : null,
                        payloadFetchedAt: nextState ? nextState.payloadFetchedAt : null,
                    };
                }
            });

            const rowNoticeState = sportsbookRowNoticeState(
                phase, String(eventPayload.trading_status || '').toLowerCase(), isTradeable,
                shellEvent && shellEvent.row_notice_state === 'resolving'
            );
            row.dataset.noticeState = rowNoticeState;
            setClosedNote(row, rowNoticeCopy(rowNoticeState), rowNoticeState === 'none');

            updateSelectedFromLiveState(row, eventPayload, selectedButtonState);
        }

        function markRowUnavailable(row, reason) {
            if (!row) {
                return;
            }

            row.dataset.eventPhase = 'ended';
            row.dataset.noticeState = reason === 'ended' ? 'ended' : 'unavailable';
            const phaseBadge = row.querySelector('[data-event-phase-badge]');
            if (phaseBadge) {
                phaseBadge.textContent = t('phase_ended', 'Bitti');
                phaseBadge.classList.remove('is-live');
                phaseBadge.classList.add('is-ended');
            }

            Array.from(row.querySelectorAll('[data-selection-trigger]')).forEach(function (button) {
                setSelectionButtonState(button, {
                    disabled: true,
                    reason: reason || 'ended',
                    rawOdd: JSON.parse(button.dataset.selection || '{}').raw_odd || JSON.parse(button.dataset.selection || '{}').odd || '-',
                    odd: (JSON.parse(button.dataset.selection || '{}').odd || '-'),
                    winxEdgePct: null,
                });
            });

            setClosedNote(row, rowNoticeCopy(row.dataset.noticeState), false);
            updateSelectedFromLiveState(row, null, { disabled: true, reason: reason || 'ended' });
        }

        async function fetchLiveOffersPayload(limit, filters, cursor, signal) {
            const url = new URL(offersIndexApiUrl, window.location.origin);
            const numericLimit = Number(limit);
            if (Number.isFinite(numericLimit) && numericLimit > 0) {
                url.searchParams.set('limit', String(Math.floor(numericLimit)));
            }
            const nextFilters = filters || currentLiveOfferFilters();
            if (nextFilters.include_non_open_selections === true || queryParams.get('debug_non_open') === '1') {
                url.searchParams.set('include_non_open_selections', '1');
            }

            if (nextFilters.phase_key) {
                url.searchParams.set('phase_key', String(nextFilters.phase_key));
            }

            if (nextFilters.time_priority) {
                url.searchParams.set('time_priority', String(nextFilters.time_priority));
            }

            if (nextFilters.interest_priority) {
                url.searchParams.set('interest_priority', String(nextFilters.interest_priority));
            }

            if (nextFilters.sport_group_key) {
                url.searchParams.set('sport_group_key', String(nextFilters.sport_group_key));
            }

            if (nextFilters.league_filter_key) {
                url.searchParams.set('league_filter_key', String(nextFilters.league_filter_key));
            }

            if (nextFilters.search) {
                url.searchParams.set('search', String(nextFilters.search));
            }

            if (nextFilters.event_ids) {
                url.searchParams.set('event_ids', Array.isArray(nextFilters.event_ids)
                    ? nextFilters.event_ids.join(',')
                    : String(nextFilters.event_ids));
            }

            if (nextFilters.surface) {
                url.searchParams.set('surface', String(nextFilters.surface));
            }

            if (nextFilters.defer_contracts) {
                url.searchParams.set('defer_contracts', '1');
            }

            if (cursor) {
                url.searchParams.set('cursor', String(cursor));
            }

            const earlyInitialOffers = window.__sportsbookInitialOffers || null;
            if (!cursor
                && earlyInitialOffers
                && earlyInitialOffers.url === url.toString()
                && earlyInitialOffers.promise
                && typeof earlyInitialOffers.promise.then === 'function') {
                const earlyRecord = await earlyInitialOffers.promise.catch(function () {
                    return null;
                });
                if (earlyRecord && earlyRecord.ok && earlyRecord.payload) {
                    return cloneJsonPayload(earlyRecord.payload);
                }
            }

            const response = await requestJson(url.toString(), {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Display-Timezone': displayTimezone,
                },
                cache: 'no-store',
                signal: signal || undefined,
            });

            if (!response.ok) {
                throw new Error('live_offers_unavailable');
            }

            return response.json();
        }

        async function pollLiveOffers(options) {
            if (!livePollEnabled || !offersIndexApiUrl) {
                return;
            }

            const nextOptions = options || {};
            const intendedFilters = nextOptions.filters || currentLiveOfferFilters();
            if (intendedFilters.interest_priority === 'featured'
                && (!featuredMatchesToggleButton || featuredMatchesToggleButton.hidden || document.hidden)) {
                return null;
            }

            if (livePollInFlight) {
                if (!nextOptions.interrupt || !livePollAbortController) {
                    return;
                }

                livePollAbortController.abort();
                livePollAbortController = null;
                livePollInFlight = false;
            }

            const requestToken = ++livePollRequestToken;
            const requestFilters = Object.assign({}, nextOptions.filters || currentLiveOfferFilters());
            if (nextOptions.surface) {
                requestFilters.surface = String(nextOptions.surface);
            } else if (!requestFilters.surface && !requestFilters.event_ids) {
                requestFilters.surface = 'initial';
            }
            const requestContextKey = buildLiveHydrationContextKey(requestFilters);
            const numericRequestedLimit = Number(nextOptions.limit);
            const requestLimit = Number.isFinite(numericRequestedLimit) && numericRequestedLimit > 0
                ? Math.max(Math.floor(numericRequestedLimit), 1)
                : livePollLimitForFilters(requestFilters, Boolean(nextOptions.bootstrap));
            const requestCursor = nextOptions.cursor || null;
            const cachedPayload = !requestCursor && !nextOptions.forceRefresh
                ? cachedLiveOfferPayload(requestFilters)
                : null;

            if (cachedPayload) {
                await applyLivePayload(cachedPayload, {
                    filters: requestFilters,
                    chunkedInsert: Boolean(nextOptions.chunkedInsert),
                    batchSize: nextOptions.batchSize || 24,
                    initialBatchSize: nextOptions.initialBatchSize,
                    deferredBatchSize: nextOptions.deferredBatchSize,
                    shouldContinue: function () {
                        return requestToken === livePollRequestToken && requestContextKey === liveHydrationContextKey;
                    },
                    markHydrated: Boolean(nextOptions.markHydrated),
                    keepStaleRows: true,
                });
                finishCatalogSelectionLoadingForFilters(requestFilters, true);

                return cachedPayload;
            }

            const abortController = typeof AbortController !== 'undefined' ? new AbortController() : null;

            livePollAbortController = abortController;
            livePollInFlight = true;

            try {
                if (nextOptions.markHydrated) {
                    setHydrationUi('loading', '', {
                        current: 0,
                        total: 1,
                    });
                }

                const payload = await fetchLiveOffersPayload(
                    requestLimit,
                    requestFilters,
                    requestCursor,
                    abortController ? abortController.signal : null
                );

                if (requestToken !== livePollRequestToken || requestContextKey !== liveHydrationContextKey) {
                    return null;
                }

                rememberLiveOfferPayload(requestFilters, payload);

                await applyLivePayload(payload, {
                    filters: requestFilters,
                    chunkedInsert: Boolean(nextOptions.chunkedInsert),
                    batchSize: nextOptions.batchSize || 24,
                    initialBatchSize: nextOptions.initialBatchSize,
                    deferredBatchSize: nextOptions.deferredBatchSize,
                    shouldContinue: function () {
                        return requestToken === livePollRequestToken && requestContextKey === liveHydrationContextKey;
                    },
                    rowsReady: Boolean(nextOptions.rowsReady),
                    markHydrated: Boolean(nextOptions.markHydrated),
                    keepStaleRows: Boolean(nextOptions.keepStaleRows),
                });
                finishCatalogSelectionLoadingForFilters(requestFilters, true);

                return payload;
            } catch (error) {
                if (error && error.name === 'AbortError') {
                    if (requestToken === livePollRequestToken && requestContextKey === liveHydrationContextKey && nextOptions.markHydrated) {
                        settleHydrationUiForCurrentContext(requestFilters, '');
                    }

                    return null;
                }

                if (requestToken === livePollRequestToken && requestContextKey === liveHydrationContextKey && nextOptions.markHydrated) {
                    setHydrationUi('stale', t('live_preserved', 'Canlı görünüm korunuyor. Tam liste bir sonraki denemede yenilenecek.'));
                }
                finishCatalogSelectionLoadingForFilters(requestFilters, false);

                return null;
            } finally {
                if (requestToken === livePollRequestToken) {
                    livePollInFlight = false;
                    livePollAbortController = null;
                }

                finishBoardRefreshingIfIdle();
            }
        }

        async function bootstrapFullCoverageHydration(options) {
            if (!livePollEnabled || ['bet-feed', 'predictions', 'e-sports', 'social-market'].includes(activePrimaryTab)) {
                return null;
            }

            const nextOptions = options || {};
            const bootstrapFilters = nextOptions.filters || currentLiveOfferFilters();
            const contextKey = buildLiveHydrationContextKey(bootstrapFilters);

            if (contextKey !== liveHydrationContextKey) {
                liveHydrationContextKey = contextKey;
                hydrationBootstrapped = false;
                hydrationReady = false;
                resetLiveHydrationWindow();
                if (livePollAbortController) {
                    livePollAbortController.abort();
                }
            }

            if (hydrationBootstrapped || hydrationInFlight) {
                return null;
            }

            hydrationInFlight = true;
            const hydrationRun = ++hydrationRunSequence;
            setHydrationUi('loading', t('tournaments_loading', 'Turnuvalar kademeli yükleniyor.'), {
                current: 0,
                total: 1,
            });

            const pageCursors = [];
            let nextCursor = null;
            let loadedCount = 0;
            let lastPayload = null;
            let completed = false;
            const pagedSportScope = usesPagedSportScopedLiveOffers(bootstrapFilters);
            const progressiveSearch = String(bootstrapFilters.search || '').trim() !== '';
            const hydrationRequestLimit = progressiveSearch
                ? livePollLimitForFilters(bootstrapFilters, false)
                : initialVisibleRowLimit;

            try {
                if (initialPayloadDeferred && !initialPassivePaintRequested && !hydrationReady && bootstrapFilters.phase_key !== 'in-game') {
                    initialPassivePaintRequested = true;
                    await pollLiveOffers({
                        surface: 'initial',
                        filters: Object.assign({}, bootstrapFilters, { defer_contracts: true }),
                        limit: initialVisibleRowLimit,
                        chunkedInsert: true,
                        batchSize: 4,
                        rowsReady: true,
                        markHydrated: false,
                        keepStaleRows: true,
                        interrupt: nextOptions.interrupt !== false,
                    });
                    if (contextKey !== liveHydrationContextKey || hydrationRun !== hydrationRunSequence) {
                        return null;
                    }
                }
                while (true) {
                    const payload = await pollLiveOffers({
                        bootstrap: true,
                        surface: 'initial',
                        cursor: nextCursor,
                        limit: hydrationRequestLimit,
                        filters: bootstrapFilters,
                        chunkedInsert: true,
                        batchSize: progressiveSearch ? progressiveSearchInitialRowLimit : 20,
                        initialBatchSize: progressiveSearch ? progressiveSearchInitialRowLimit : null,
                        deferredBatchSize: progressiveSearch ? progressiveSearchDeferredBatchSize : null,
                        markHydrated: false,
                        keepStaleRows: true,
                        interrupt: nextOptions.interrupt !== false,
                    });

                    if (!payload) {
                        return null;
                    }

                    if (contextKey !== liveHydrationContextKey || hydrationRun !== hydrationRunSequence) {
                        return null;
                    }

                    lastPayload = payload;
                    pageCursors.push(nextCursor);

                    const meta = (payload && payload.meta) || {};
                    loadedCount += Number(meta.offer_count || 0) || 0;
                    const hasMore = Boolean(meta.has_more) && String(meta.next_cursor || '') !== '';
                    setHydrationUi('loading', t('tournaments_loading', 'Turnuvalar kademeli yükleniyor.'), {
                        current: loadedCount,
                        total: Math.max(loadedCount + (hasMore ? Math.max(Number(meta.offer_count || 0), 1) : 0), 1),
                    });

                    if (!hasMore) {
                        break;
                    }

                    if (pagedSportScope) {
                        break;
                    }

                    nextCursor = String(meta.next_cursor || '');
                    if (nextCursor === '') {
                        break;
                    }
                }

                livePollCursorQueue = pagedSportScope ? [null] : (pageCursors.length ? pageCursors : [null]);
                livePollCursorIndex = 0;
                hydrationReady = true;
                hydrationBootstrapped = true;
                completed = true;
                setInitialHydrationState('ready');
                setHydrationUi('ready', loadedCount > 0
                    ? t('events_and_tournaments_ready', ':count etkinlik ve güncel turnuva listesi hazır.', { count: formatCountLabel(loadedCount) })
                    : t('live_ready', 'Canlı liste hazır.'), {
                    current: loadedCount,
                    total: Math.max(loadedCount, 1),
                });
                updateTabCounts();
                updateSportFilterButtons();
                updateLeagueFilterButtons();
                updateMatchPagination();

                return lastPayload;
            } finally {
                if (hydrationRun !== hydrationRunSequence) return;
                if (!completed) {
                    hydrationBootstrapped = false;
                }
                hydrationInFlight = false;
                if (!completed && contextKey === liveHydrationContextKey) {
                    settleHydrationUiForCurrentContext(bootstrapFilters, '');
                }
                finishBoardRefreshingIfIdle();
            }
        }

        function clearLivePollTimer() {
            if (livePollTimer) {
                window.clearTimeout(livePollTimer);
                livePollTimer = null;
            }
        }

        function queueLivePoll(delayMs) {
            clearLivePollTimer();
            if (document.hidden) {
                return;
            }
            if (!livePollEnabled || hydrationInFlight || ['bet-feed', 'predictions', 'e-sports', 'social-market'].includes(activePrimaryTab)) {
                return;
            }

            clearLivePollTimer();
            livePollTimer = window.setTimeout(async function () {
                const currentFilters = currentLiveOfferFilters();
                const cursor = livePollCursorForNextCycle();
                await pollLiveOffers({
                    interrupt: false,
                    limit: livePollLimitForFilters(currentFilters, false),
                    filters: currentFilters,
                    cursor: cursor,
                    keepStaleRows: true,
                });
                queueLivePoll(document.hidden ? livePollBackgroundMs : livePollVisibleMs);
            }, Math.max(delayMs || 0, 0));
        }

        function schedulePrioritySportPrefetch() {
            if (!livePollEnabled || priorityPrefetchStarted || ['bet-feed', 'predictions', 'e-sports', 'social-market'].includes(activePrimaryTab)) {
                return;
            }

            if (initialPayloadDeferred && !hydrationReady) {
                firstUsableHydrationReady().then(function () {
                    schedulePrioritySportPrefetch();
                });
                return;
            }

            priorityPrefetchStarted = true;

            const counts = mergedCountsForTab(activePrimaryTab);
            const sportsToPrefetch = productSportOrder
                .filter(function (sportKey) {
                    return sportKey !== activeSportFilter
                        && allowedSportFilters.has(sportKey)
                        && sportAvailabilityScore(counts, sportKey) > 0;
                })
                .slice(0, 4);

            let nextSportIndex = 0;
            const prefetchWorker = function () {
                const sportKey = sportsToPrefetch[nextSportIndex++];
                if (!sportKey) {
                    return Promise.resolve();
                }

                const filters = {
                    phase_key: activePhaseForTab(),
                    sport_group_key: sportKey,
                    surface: 'sport-slice',
                };

                return Promise.resolve(prefetchLiveOfferPayload(filters, initialVisibleRowLimit))
                    .catch(function () {
                        // Prefetch is opportunistic; normal live refresh remains the source of truth.
                    })
                    .then(prefetchWorker);
            };

            window.setTimeout(function () {
                Promise.all([prefetchWorker(), prefetchWorker()]);
            }, 450);
        }

        const eventOfferRefreshes = new Map();
        function refreshEventOffer(eventId, marketType, outcomeCode, marketSpecifierKey, fallbackSelectionPayload) {
            const key = [eventId, marketType, marketSpecifierKey || 'default', outcomeCode,
                Boolean(fallbackSelectionPayload && fallbackSelectionPayload.feed_item_id)].join('::');
            if (eventOfferRefreshes.has(key)) return eventOfferRefreshes.get(key);
            const pending = fetchExactEventOffer(eventId, marketType, outcomeCode, marketSpecifierKey, fallbackSelectionPayload);
            eventOfferRefreshes.set(key, pending);
            pending.finally(function () {
                if (eventOfferRefreshes.get(key) === pending) eventOfferRefreshes.delete(key);
            });
            return pending;
        }

        async function fetchExactEventOffer(eventId, marketType, outcomeCode, marketSpecifierKey, fallbackSelectionPayload) {
            if (offlineDemo.active || (fallbackSelectionPayload && fallbackSelectionPayload.acceptance_mode === 'offline_demo')) return null;
            if (!offersIndexApiUrl || !eventId) {
                return null;
            }

            lastAdapterQuoteRefreshFailure = '';

            try {
                const endpoint = new URL(offersIndexApiUrl, window.location.origin);
                endpoint.searchParams.set('event_ids', String(eventId));
                endpoint.searchParams.set('limit', '1');
                endpoint.searchParams.set('include_non_open_selections', '1');
                endpoint.searchParams.set('include_events', '1');
                endpoint.searchParams.set('fresh', '1');
                endpoint.searchParams.set('market_type', String(marketType));
                endpoint.searchParams.set('market_specifier_key', String(marketSpecifierKey || 'default'));
                endpoint.searchParams.set('outcome_code', String(outcomeCode));
                const response = await requestJson(endpoint.toString(), {
                    headers: {
                        'Accept': 'application/json',
                        'X-Display-Timezone': displayTimezone,
                        ...(csrfTokenMeta ? {'X-CSRF-TOKEN': csrfTokenMeta.getAttribute('content') || ''} : {}),
                    },
                    credentials: 'same-origin',
                    cache: 'no-store',
                    timeoutMs: adapterQuoteRefreshRequestTimeoutMs,
                });

                if (!response.ok) {
                    let errorPayload = null;
                    try {
                        errorPayload = await response.json();
                    } catch (error) {
                        errorPayload = null;
                    }
                    lastAdapterQuoteRefreshFailure = String(
                        (errorPayload && errorPayload.error && errorPayload.error.details && errorPayload.error.details.sportsbook_code)
                        || (errorPayload && errorPayload.error && errorPayload.error.code)
                        || (response.status === 503 ? 'QUOTE_REFRESH_UNAVAILABLE' : '')
                    );
                    return null;
                }

                const payload = await response.json();
                const events = Array.isArray((((payload || {}).data || {}).events)) ? payload.data.events : [];
                const eventPayload = events.find(function (item) {
                    return String(item.event_id || '') === String(eventId);
                }) || null;
                const button = triggers.find(function (trigger) {
                    return Number(trigger.dataset.eventId || 0) === Number(eventId)
                        && trigger.dataset.marketType === marketType
                        && String(trigger.dataset.marketSpecifier || 'default') === String(marketSpecifierKey || 'default')
                        && trigger.dataset.outcomeCode === outcomeCode;
                });

                const marketSpecifier = String(marketSpecifierKey || (button ? button.dataset.marketSpecifier : '') || 'default');
                const quote = ((eventPayload || {}).quote) || {};
                const marketList = Array.isArray(quote.markets) ? quote.markets : [];
                const market = marketList.find(function (item) {
                    return item.market_type === marketType && String(item.specifier_key || 'default') === marketSpecifier;
                });

                if (!market || !Array.isArray(market.selections)) {
                    return null;
                }

                const selection = market.selections.find(function (item) {
                    return item.outcome_code === outcomeCode && item.offer_status === 'open';
                });

                if (!selection) {
                    return null;
                }

                if (fallbackSelectionPayload && fallbackSelectionPayload.feed_item_id
                    && (market.status !== 'open' || eventPayload.is_live === true
                        || !(Date.parse(eventPayload.starts_at || '') > Date.now()))) {
                    return null;
                }

                const isFeedRefresh = Boolean(fallbackSelectionPayload && fallbackSelectionPayload.feed_item_id);
                const oldPayload = isFeedRefresh ? {
                    event_id: eventId,
                    market_type: marketType,
                    market_specifier_key: marketSpecifier,
                    outcome_code: outcomeCode,
                    feed_item_id: fallbackSelectionPayload.feed_item_id,
                    selection_key: 'feed-' + eventId + '-' + marketType + '-' + marketSpecifier + '-' + outcomeCode,
                    event_name: eventPayload.canonical_name || eventPayload.event_name || '',
                    market_name: market.display_name || '',
                    outcome_name: selection.display_name || '',
                    context_sport: eventPayload.sport_label || eventPayload.sport || '',
                } : button
                    ? JSON.parse(button.dataset.selection || '{}')
                    : Object.assign({}, fallbackSelectionPayload || {}, {
                        event_id: eventId,
                        market_type: marketType,
                        market_specifier_key: marketSpecifier,
                        outcome_code: outcomeCode,
                    });
                const oldWasAdapterQuoteRef = isAdapterQuoteRefPayload(oldPayload);
                const nextSelectionToken = adapterSelectionToken(selection);
                const refreshedAt = Date.now();
                const marketLine = market.line ?? null;
                const totalLabel = totalSelectionLabel({ market_type: marketType, outcome_code: outcomeCode, market_line: marketLine });

                if (oldWasAdapterQuoteRef && !nextSelectionToken) {
                    return null;
                }

                const refreshedPayload = Object.assign({}, oldPayload, {
                    label: totalLabel || oldPayload.label || selection.display_name || oldPayload.outcome_name || '-',
                    raw_odd: selection.decimal_price,
                    odd: formatDisplayOdd(selection.decimal_price),
                    market_line: marketLine,
                    quote_selection_id: oldWasAdapterQuoteRef ? null : (selection.quote_selection_id ?? oldPayload.quote_selection_id),
                    selection_token: oldWasAdapterQuoteRef ? nextSelectionToken : (nextSelectionToken || adapterSelectionToken(oldPayload)),
                    acceptance_mode: nextSelectionToken ? 'adapter_vps_selection_token' : (selection.acceptance_mode || 'legacy_quote_selection'),
                    quote_version_id: quote.quote_version_id,
                    outcome_name: totalLabel || selection.display_name,
                    quote_payload_fetched_at: refreshedAt,
                    quote_checked_at: refreshedAt,
                    selection_key: !oldWasAdapterQuoteRef && selection.quote_selection_id
                        ? 'selection-' + selection.quote_selection_id
                        : oldPayload.selection_key,
                    selectable: quote.is_tradeable === true && selection.bettable !== false && market.bettable !== false && (!oldWasAdapterQuoteRef || nextSelectionToken !== ''),
                    bettable_reason: selection.bettable_reason || market.bettable_reason || '',
                    selection_state_message: (selection.bettable === false || market.bettable === false)
                        ? selectionStateMessageFromReason(selection.bettable_reason || market.bettable_reason || 'market_not_bettable')
                        : '',
                });
                refreshedPayload.selectable = refreshedPayload.selectable && selectionPresentationReady(refreshedPayload);

                if (button && !isFeedRefresh) {
                    button.dataset.selection = JSON.stringify(refreshedPayload);
                    button.disabled = !refreshedPayload.selectable;
                    button.classList.toggle('is-disabled', !refreshedPayload.selectable);
                    button.setAttribute('aria-disabled', refreshedPayload.selectable ? 'false' : 'true');
                    const label = button.querySelector('.sb2-odd-box-label');
                    const value = button.querySelector('.sb2-odd-box-value');
                    if (label) {
                        label.textContent = refreshedPayload.label || '-';
                    }
                    if (value) {
                        value.textContent = refreshedPayload.odd || '-';
                    }
                }

                return refreshedPayload;
            } catch (error) {
                lastAdapterQuoteRefreshFailure = error && (error.name === 'AbortError' || error.message === 'json_request_timeout')
                    ? 'timeout'
                    : 'refresh_unavailable';
                return null;
            }
        }

        function buildAcceptanceTraceSuffix(result) {
            const supportCode = result && result.error && result.error.details && result.error.details.support_code
                ? String(result.error.details.support_code)
                : (result && result.meta && result.meta.support_code ? String(result.meta.support_code) : '');
            const requestId = supportCode || (result && result.meta && result.meta.request_id
                ? String(result.meta.request_id)
                : '');

            if (!requestId) {
                return '';
            }

            return ' ' + t('support_code', 'Destek kodu') + ': ' + requestId;
        }

        function isRefreshableAcceptanceError(code, suggestedAction) {
            if (String(suggestedAction || '') === 'refresh_quote') {
                return true;
            }

            return [
                'STALE_QUOTE_VERSION',
                'STALE_PRICE',
                'ADAPTER_VPS_QUOTE_REJECTED',
                'ADAPTER_VPS_VERIFY_INVALID',
            ].includes(String(code || ''));
        }

        function isClosedAcceptanceError(code, suggestedAction) {
            return String(code || '') === 'ADAPTER_VPS_QUOTE_REJECTED'
                && String(suggestedAction || '') === 'pick_another_selection';
        }

        function adapterRefreshCopyForMissingSelection() {
            return t('exact_selection_unavailable', 'Bu seçim için güncel oran bulunamadı.');
        }

        function adapterRefreshCopyForDelayedQuote() {
            return t('exact_refresh_delayed', 'Oran yenileme gecikti. Lütfen tekrar deneyin.');
        }

        function adapterRefreshUnavailableCopy() {
            return ['timeout', 'QUOTE_REFRESH_UNAVAILABLE', 'SPORTSBOOK_EXACT_REFRESH_UNAVAILABLE', 'refresh_unavailable'].includes(String(lastAdapterQuoteRefreshFailure || ''))
                ? `${adapterRefreshCopyForDelayedQuote()} ${t('error_code_prefix', 'Kod')}: SPORTSBOOK_EXACT_REFRESH_UNAVAILABLE`
                : adapterRefreshCopyForMissingSelection();
        }

        function resolveAcceptanceErrorPresentation(result) {
            const fallback = {
                code: '',
                title: t('action_failed', 'İşlem tamamlanamadı'),
                message: t('selection_action_failed', 'Seçim şu anda işlenemedi. Birkaç saniye sonra yeniden deneyebilirsin.'),
                suggestedAction: 'retry',
            };

            if (!result || !result.error) {
                return fallback;
            }

            const ui = result.error.details && result.error.details.ui ? result.error.details.ui : {};
            const action = String(ui.suggested_action || 'retry');
            const code = String(result.error.code || '');
            const message = isClosedAcceptanceError(code, action)
                ? t('market_unavailable', 'Bu market için henüz oran yok')
                : isRefreshableAcceptanceError(code, action)
                    ? t('exact_refresh_preserved', 'Oran şu anda güncellenemedi. Kuponun korunuyor; birazdan tekrar deneyebilirsin.')
                    : action === 'deposit_or_lower_stake'
                        ? t('insufficient_balance_body', 'Bahis tutarı mevcut bakiyeni aşıyor.')
                        : action === 'switch_wallet'
                            ? t('wallet_action_needed', 'Bu işlem için başka bir cüzdan seç.')
                            : fallback.message;

            return {
                code: code,
                title: fallback.title,
                message: message,
                suggestedAction: action,
            };
        }

        function findPrefillSelection() {
            if (!initialPrefill.selectionId) {
                return null;
            }

            return triggers.find(function (button) {
                const payload = JSON.parse(button.dataset.selection || '{}');
                return String(payload.quote_selection_id) === String(initialPrefill.selectionId);
            }) || null;
        }

        function findEventFirstSelection() {
            if (!initialPrefill.eventId) {
                return null;
            }

            return triggers.find(function (button) {
                if (button.disabled) {
                    return false;
                }

                return String(button.dataset.eventId || '') === String(initialPrefill.eventId);
            }) || null;
        }

        function applySportFilterForButton(button) {
            if (!button) {
                return;
            }

            const row = button.closest('[data-event-row]');
            const sportKey = row ? (row.dataset.sportKey || '') : '';

            if (!sportKey || !allowedSportFilters.has(sportKey)) {
                return;
            }

            activeSportFilter = sportKey;
            activeLeagueFilter = 'all';
            activeMatchPage = 1;
            updateSportFilterButtons();
            updateLeagueFilterButtons();
            updateMatchPagination();
            queueActiveSportButtonVisibility({
                force: true,
            });
        }

        function focusSlipShell() {
            if (!slipShell) {
                return;
            }

            slipShell.scrollIntoView({ behavior: 'smooth', block: 'start' });
            slipShell.classList.add('is-feed-focused');
            window.setTimeout(function () {
                slipShell.classList.remove('is-feed-focused');
            }, 1200);
        }

        function revealSlipShellAfterSelection() {
            if (!slipShell) {
                return;
            }

            const rect = slipShell.getBoundingClientRect();
            const viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0;
            const viewportWidth = window.innerWidth || document.documentElement.clientWidth || 0;
            const hasUsableRect = rect.width > 0 && rect.height > 0;
            const visibleVertically = hasUsableRect && rect.top >= 0 && rect.bottom <= viewportHeight;
            const visibleHorizontally = hasUsableRect && rect.left >= 0 && rect.right <= viewportWidth;

            if (visibleVertically && visibleHorizontally) {
                slipShell.classList.add('is-feed-focused');
                window.setTimeout(function () {
                    slipShell.classList.remove('is-feed-focused');
                }, 900);
                return;
            }

            focusSlipShell();
        }

        async function prefillExternalSelection(selectionPayload) {
            if (!selectionPayload || !selectionPayload.event_id || !selectionPayload.market_type || !selectionPayload.outcome_code) {
                return false;
            }

            const localButton = triggers.find(function (button) {
                return String(button.dataset.eventId || '') === String(selectionPayload.event_id || '')
                    && String(button.dataset.marketType || '') === String(selectionPayload.market_type || '')
                    && String(button.dataset.marketSpecifier || 'default') === String(selectionPayload.market_specifier_key || 'default')
                    && String(button.dataset.outcomeCode || '') === String(selectionPayload.outcome_code || '')
                    && !button.disabled
                    && !button.classList.contains('is-disabled');
            }) || null;

            if (submitState === 'submitting' || submitState === 'retrying' || frozenAfterSuccess) return false;
            const presentation = selectionPayload.presentation;
            if (selectionPayload.feed_item_id && presentation && !(Date.parse(presentation.expires_at) > Date.now())) return false;
            const readyPayload = localButton ? JSON.parse(localButton.dataset.selection || '{}') : selectionPayload;
            if (sportsbookFeedHydratedContractUsable(readyPayload) && !adapterQuoteNeedsFreshSubmitCheck(readyPayload)) {
                if (localButton) {
                    applySportFilterForButton(localButton);
                    revealMatchPageForEvent(readyPayload.event_id);
                }
                activeSlipMode = 'single';
                selected = stampSelectedPayload(readyPayload, {selectedAt: Date.now()});
                resetSubmissionState(false);
                if (slipViewportMode !== 'static') setSlipCollapsed(false);
                renderSelectionState();
                focusSlipShell();
                return true;
            }

            // Only the server's bounded public presentation may form a provisional coupon.
            // It carries no token and is never sent to acceptance until Exact prepares one.
            if (selectionPayload.feed_item_id && presentation
                && Date.parse(presentation.expires_at) > Date.now()
                && Number(presentation.raw_odd) > 1 && Number.isFinite(Number(presentation.raw_odd))
                && presentation.event_name && presentation.market_name && presentation.outcome_name) {
                const provisional = {
                    event_id: selectionPayload.event_id, market_type: selectionPayload.market_type,
                    market_specifier_key: selectionPayload.market_specifier_key || 'default', outcome_code: selectionPayload.outcome_code,
                    feed_item_id: selectionPayload.feed_item_id, acceptance_mode: 'adapter_vps_selection_token',
                    selection_token: '', contract_required: true, selectable: false,
                    selection_key: 'feed-' + [selectionPayload.event_id, selectionPayload.market_type, selectionPayload.market_specifier_key || 'default', selectionPayload.outcome_code].join('-'),
                    event_name: presentation.event_name, market_name: presentation.market_name, outcome_name: presentation.outcome_name,
                    label: presentation.outcome_name, market_line: presentation.market_line,
                    raw_odd: Number(presentation.raw_odd), odd: formatDisplayOdd(presentation.raw_odd),
                    presentation_expires_at: presentation.expires_at,
                };
                if (!selectionPresentationReady(provisional)) return false;
                activeSlipMode = 'single';
                selected = stampSelectedPayload(provisional, {selectedAt: Date.now()});
                resetSubmissionState(false);
                if (slipViewportMode !== 'static') setSlipCollapsed(false);
                submitState = 'refreshed';
                submitTitle = t('indicative_odds_title', 'Doğrulanmamış oran');
                submitMessage = t('indicative_odds_body', 'Bu oran yalnızca gösterge niteliğindedir. Seçim ve güncel oran gönderimde doğrulanacak.');
                renderSelectionState();
                focusSlipShell();
                return true;
            }

            submitState = 'refreshed';
            submitTitle = 'Market kapali';
            submitMessage = 'Bu akistaki secim su an kupona eklenemiyor. Market kapanmis ya da oran guncellenmis olabilir.';
            if (selectionPayload.feed_item_id) {
                const feedCopy = readSportsbookShellJson('sportsbookShellBetFeedI18n', {});
                submitTitle = feedCopy.not_addable || submitTitle;
                submitMessage = feedCopy.add_race_warning || submitMessage;
            }
            renderSelectionState();
            focusSlipShell();

            return false;
        }

        window.sportsbookShellPrefillSelection = prefillExternalSelection;

        async function applyInitialPrefill() {
            const prefilledButton = findPrefillSelection();

            if (prefilledButton) {
                const payload = JSON.parse(prefilledButton.dataset.selection || '{}');
                if (payload && payload.selectable) {
                    applySportFilterForButton(prefilledButton);
                    revealMatchPageForEvent(payload.event_id);
                    activeSlipMode = 'single';
                    selected = stampSelectedPayload(payload, {
                        selectedAt: Date.now(),
                    });
                    resetSubmissionState(false);
                    renderSelectionState();
                    return;
                }
            }

            const eventPrefillButton = !initialPrefill.marketType && !initialPrefill.outcomeCode
                ? findEventFirstSelection()
                : triggers.find(function (button) {
                    return !button.disabled && String(button.dataset.eventId || '') === String(initialPrefill.eventId || '')
                        && button.dataset.marketType === initialPrefill.marketType
                        && String(button.dataset.marketSpecifier || 'default') === String(initialPrefill.marketSpecifierKey || 'default')
                        && button.dataset.outcomeCode === initialPrefill.outcomeCode;
                });

            if (eventPrefillButton) {
                const payload = JSON.parse(eventPrefillButton.dataset.selection || '{}');
                if (payload && payload.selectable) {
                    applySportFilterForButton(eventPrefillButton);
                    revealMatchPageForEvent(payload.event_id);
                    activeSlipMode = 'single';
                    selected = stampSelectedPayload(payload, {
                        selectedAt: Date.now(),
                    });
                    resetSubmissionState(false);
                    renderSelectionState();
                    return;
                }
            }

            if (!initialPrefill.eventId || !initialPrefill.marketType || !initialPrefill.outcomeCode) {
                return;
            }

            // Direct link from a human-initiated Sports room coupon click:
            // resolve current Exact once, never trust the shared historical odds.
            if (queryParams.get('community_prefill') === '1'
                && /^[1-9][0-9]{0,11}$/.test(String(initialPrefill.eventId))
                && /^[a-z0-9_]{1,80}$/.test(String(initialPrefill.marketType))
                && /^[a-z0-9_:.+-]{1,80}$/.test(String(initialPrefill.marketSpecifierKey || 'default'))
                && /^[a-z0-9_:.+-]{1,80}$/.test(String(initialPrefill.outcomeCode))) {
                const fresh = await refreshEventOffer(
                    Number(initialPrefill.eventId), initialPrefill.marketType,
                    initialPrefill.outcomeCode, initialPrefill.marketSpecifierKey || 'default'
                );
                if (fresh && sportsbookFeedHydratedContractUsable(fresh)) {
                    await prefillExternalSelection(fresh);
                    return;
                }
            }
            // A URL identity without a valid current Exact contract cannot
            // form a coupon. No old odds or silent acceptance.
            submitState = 'refreshed';
            submitTitle = t('quote_updated_title', 'Oran güncellendi');
            submitMessage = t('prefill_quote_closed', 'Ana sayfadaki oran artık geçerli değil. Bu seçim kapanmış olabilir; lütfen yeni bir oran seç.');
            renderSelectionState();
        }

        async function submitAcceptance(options) {
            if (JSON.parse(document.getElementById("sportsbookShellRuntimeConfig")?.textContent || "{}").finance_read_only) { showInlineSubmitMessage("Read-only sportsbook data. Select C local wallet to wager synthetic funds."); return; }
            if (offlineDemo.active || (selected && selected.acceptance_mode === 'offline_demo')) return;
            const allowScheduledRetry = Boolean(options && options.autoRetry === true);
            if (!selected
                || currentStake() <= 0
                || acceptancePreparationInFlight
                || submitState === 'submitting'
                || (submitState === 'retrying' && !allowScheduledRetry)
                || frozenAfterSuccess) {
                return;
            }

            lastSubmitUsedAutoAcceptedOdds = false;

            if (!viewer.authenticated) {
                showInlineSubmitMessage(t('login_required_inline', 'Devam etmek için giriş yapman gerekiyor.'));
                if (!openLoginFlow()) {
                    submitState = 'error';
                    submitMessage = t('login_required_inline', 'Devam etmek için giriş yapman gerekiyor.');
                    renderResultState();
                }
                return;
            }

            if (viewer.balance <= 0) {
                showInlineSubmitMessage(t('deposit_required_inline', 'Devam etmek için bakiye yüklemen gerekiyor.'));
                if (viewer.depositUrl || zeroBalanceModalElement || depositPromptModal) {
                    if (!openDepositFlow() && viewer.depositUrl) {
                        window.location.assign(viewer.depositUrl);
                    }
                    return;
                }
                return;
            }

            if (hasInsufficientBalance()) {
                showInlineSubmitMessage(t('insufficient_balance_body', 'Bahis tutarı mevcut bakiyeni aşıyor.'));
                return;
            }

            const submittingKey = couponSelectionIdentity(selected);
            acceptancePreparationInFlight = true;
            let prepared = false;
            try {
                prepared = await refreshSelectedAdapterQuoteBeforeSubmit();
            } finally {
                acceptancePreparationInFlight = false;
            }
            if (!prepared) {
                return;
            }
            if (!selected || couponSelectionIdentity(selected) !== submittingKey
                || ((isAdapterQuoteRefSelection(selected) || selected.contract_required) && !sportsbookFeedHydratedContractUsable(selected))) return;

            const endpoint = acceptanceEndpointForSelection(selected);
            if (!endpoint) {
                submitState = 'error';
                frozenAfterSuccess = false;
                acceptedPayload = null;
                submitTitle = t('action_failed', 'İşlem tamamlanamadı');
                submitMessage = t('unavailable_market', 'Seçili market şu an bahis için uygun değil.');
                inlineGateMessage = '';
                resultActionIntent = 'retry';
                renderSelectionState();
                return;
            }

            submitState = 'submitting';
            submitTitle = t('quote_checking_title', 'Oran doğrulanıyor');
            submitMessage = t('quote_checking_short', 'Oran sağlayıcıdan doğrulanıyor...');
            acceptedPayload = null;
            inlineGateMessage = '';
            startSubmitProgressTimer();
            renderResultState();
            updateInteractiveState();
            updateSubmitButton();

            lastSubmittedStakeAmount = Number(currentStake().toFixed(2));
            const payload = acceptancePayloadForSelection(selected);

            try {
                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'Idempotency-Key': buildIdempotencyKey(),
                        ...(csrfTokenMeta ? {'X-CSRF-TOKEN': csrfTokenMeta.getAttribute('content') || ''} : {}),
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(payload),
                });

                const result = await response.json();

                if (response.ok && result.success && result.data && result.data.acceptance) {
                    clearSubmitProgressTimer();
                    submitState = 'success';
                    submitTitle = t('accepted', 'Bahis alındı');
                    submitMessage = lastSubmitUsedAutoAcceptedOdds
                        ? t('accepted_auto_body', 'Oran doğrulandı ve güncel oranla kabul edildi.')
                        : t('accepted_body', 'Kupon alındı. Son işlem özeti aşağıda.');
                    frozenAfterSuccess = false;
                    dismissedSuccessResult = false;
                    applyAcceptedPayload(result.data.acceptance);
                    upsertHistoryItemFromPayload(result.data.acceptance);
                    selected = null;
                    currentIdempotencyKey = null;
                    if (stakeInput) {
                        stakeInput.value = '';
                    }
                    renderStakeSummary();
                    renderSelectionState();
                    scheduleAcceptedCouponReset();
                    void refreshWalletSnapshot();
                    return;
                }

                const presentation = resolveAcceptanceErrorPresentation(result);
                const traceSuffix = buildAcceptanceTraceSuffix(result);
                const previousSelection = selected;
                const previousPrice = oddNumericValue(previousSelection);

                clearSubmitProgressTimer();
                submitState = 'error';
                frozenAfterSuccess = false;
                submitTitle = presentation.title;

                if (isClosedAcceptanceError(presentation.code, presentation.suggestedAction)) {
                    acceptedPayload = null;
                    submitMessage = presentation.message + traceSuffix;
                    inlineGateMessage = '';
                    resultActionIntent = presentation.suggestedAction || 'pick_another_selection';
                    renderResultState();
                } else if (isRefreshableAcceptanceError(presentation.code, presentation.suggestedAction)) {
                    const refreshedSelection = await refreshEventOffer(selected.event_id, selected.market_type, selected.outcome_code, selected.market_specifier_key, selected);
                    if (refreshedSelection) {
                        selected = stampSelectedPayload(Object.assign({}, refreshedSelection, {
                            auto_refresh_retry_used: Boolean(previousSelection && previousSelection.auto_refresh_retry_used),
                        }), {
                            selectedAt: previousSelection.selected_at || Date.now(),
                            payloadFetchedAt: refreshedSelection.quote_payload_fetched_at || Date.now(),
                            refreshedAt: refreshedSelection.quote_checked_at || Date.now(),
                        });
                        currentIdempotencyKey = null;
                        acceptedPayload = null;
                        const nextPrice = oddNumericValue(selected);

                        if (autoAcceptOddsChanges && !Boolean(previousSelection && previousSelection.auto_refresh_retry_used)) {
                            selected.auto_refresh_retry_used = true;
                            lastSubmitUsedAutoAcceptedOdds = Math.abs(previousPrice - nextPrice) > 0.000001;
                            submitState = 'retrying';
                            submitTitle = lastSubmitUsedAutoAcceptedOdds
                                ? t('quote_updated_title', 'Oran güncellendi')
                                : t('quote_verified_title', 'Oran doğrulandı');
                            submitMessage = lastSubmitUsedAutoAcceptedOdds
                                ? t('quote_updated_continue', 'Güncel oran doğrulandı; kupon bu oranla gönderiliyor.')
                                : t('quote_verified_body', 'Seçili oran değişmedi. Kuponu güvenle onaylayabilirsin.');
                            inlineGateMessage = '';
                            showInlineSubmitMessage(lastSubmitUsedAutoAcceptedOdds
                                ? t('quote_verified_continue', 'Oran doğrulandı ve güncel oranla devam ediliyor.')
                                : t('quote_verified_inline', 'Oran doğrulandı.'));
                            renderSelectionState();
                            window.setTimeout(function () {
                                submitAcceptance({autoRetry: true});
                            }, 0);
                            return;
                        }

                        submitState = 'refreshed';
                        submitTitle = t('quote_updated_title', 'Oran güncellendi');
                        submitMessage = t('old_odds_expired', 'Eski oran artık geçerli değil. En güncel oran yüklendi; devam etmek için seçimi yeniden onayla.') + traceSuffix;
                        inlineGateMessage = '';
                        renderSelectionState();
                    } else {
                        keepSelectionWithQuoteUnavailable(adapterRefreshCopyForMissingSelection(), traceSuffix);
                    }
                } else {
                    acceptedPayload = null;
                    submitMessage = presentation.message + traceSuffix;
                    inlineGateMessage = '';
                    resultActionIntent = presentation.suggestedAction || 'retry';
                    renderResultState();
                }
            } catch (error) {
                clearSubmitProgressTimer();
                submitState = 'error';
                frozenAfterSuccess = false;
                acceptedPayload = null;
                submitTitle = t('connection_lost_title', 'Bağlantı kesildi');
                submitMessage = t('request_interrupted', 'İstek gönderilirken bağlantı beklenmedik biçimde kesildi. Tekrar deneyebilirsin.');
                inlineGateMessage = '';
                resultActionIntent = 'retry';
                renderResultState();
            }

            if (submitState === 'submitting') {
                clearSubmitProgressTimer();
                submitState = 'error';
                frozenAfterSuccess = false;
                acceptedPayload = null;
                submitTitle = t('action_failed', 'İşlem tamamlanamadı');
                submitMessage = t('selection_action_failed', 'Seçim şu anda işlenemedi. Birkaç saniye sonra yeniden deneyebilirsin.');
                inlineGateMessage = '';
                resultActionIntent = 'retry';
                renderResultState();
            }

            updateInteractiveState();
            updateSubmitButton();
        }

        bindSelectionTriggers(document);

        removeButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                if (submitState === 'submitting') {
                    return;
                }

                clearSelection();
            });
        });

        if (fillBalanceButton && stakeInput) {
            fillBalanceButton.addEventListener('click', function () {
                if (submitState === 'submitting' || frozenAfterSuccess) {
                    return;
                }

                const balanceAmount = Math.floor(parseBalanceAmount());
                stakeInput.value = balanceAmount > 0 ? String(balanceAmount) : '';
                currentIdempotencyKey = null;
                acceptedPayload = null;
                inlineGateMessage = '';
                clearSuccessResetTimer();
                renderStakeSummary();
                updateSubmitButton();
            });
        }

        bindMoreToggleButtons(document);
        bindMarketFamilyChips(document);

        stakeChipButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                if (!stakeInput || submitState === 'submitting' || frozenAfterSuccess) {
                    return;
                }

                stakeInput.value = normalizeStakeInputValue(button.dataset.stakeChip || '');
                currentIdempotencyKey = null;
                acceptedPayload = null;
                inlineGateMessage = '';
                clearSuccessResetTimer();
                if (submitState !== 'submitting' && submitState !== 'refreshed') {
                    submitState = 'idle';
                    submitMessage = '';
                }
                renderResultState();
                renderStakeSummary();
                updateSubmitButton();
            });
        });

        if (stakeInput) {
            stakeInput.addEventListener('input', function () {
                const normalizedValue = normalizeStakeInputValue(stakeInput.value);
                if (stakeInput.value !== normalizedValue) {
                    stakeInput.value = normalizedValue;
                }
                currentIdempotencyKey = null;
                acceptedPayload = null;
                clearSuccessResetTimer();
                if (submitState !== 'submitting' && submitState !== 'refreshed') {
                    submitState = 'idle';
                    submitMessage = '';
                }
                inlineGateMessage = '';
                renderResultState();
                renderStakeSummary();
                updateSubmitButton();
            });

            stakeInput.addEventListener('keydown', function (event) {
                if (event.key !== 'Enter') {
                    return;
                }

                event.preventDefault();
                submitAcceptance();
            });
        }

        if (exactRetry) {
            exactRetry.addEventListener('click', retrySelectedExactRefresh);
        }

        if (resultAction) {
            resultAction.addEventListener('click', async function () {
                if (submitState === 'refreshed' || submitState === 'error') {
                    if (submitState === 'error') {
                        if (resultActionIntent === 'deposit_or_lower_stake') {
                            if (viewer.balance > 0 && stakeInput) {
                                stakeInput.value = String(Math.floor(Number(viewer.balance) || 0));
                                submitState = 'idle';
                                submitMessage = '';
                                inlineGateMessage = t('stake_adjusted', 'Bahis tutarı bakiyene göre güncellendi.');
                                renderStakeSummary();
                                renderSelectionState();
                                stakeInput.focus();
                                stakeInput.select();
                                return;
                            }

                            if (openDepositFlow()) {
                                return;
                            }
                        }

                        if (resultActionIntent === 'switch_wallet') {
                            if (openWalletSwitchFlow()) {
                                return;
                            }
                        }

                        if (resultActionIntent === 'refresh_quote') {
                            await handleRefreshQuoteAction();
                            return;
                        }

                        if (resultActionIntent === 'pick_another_selection' || resultActionIntent === 'refresh_selection') {
                            clearSelection();
                            return;
                        }
                    }

                    submitState = 'idle';
                    submitMessage = '';
                    inlineGateMessage = '';
                    renderSelectionState();
                    if (stakeInput) {
                        stakeInput.focus();
                    }
                    return;
                }

                if (submitState !== 'success') {
                    return;
                }

                frozenAfterSuccess = false;
                submitState = 'idle';
                submitMessage = '';
                acceptedPayload = null;
                currentIdempotencyKey = null;
                inlineGateMessage = '';
                clearSuccessResetTimer();
                renderSelectionState();
            });
        }

        if (resultClose) {
            resultClose.addEventListener('click', function () {
                if (submitState !== 'success') {
                    return;
                }

                dismissedSuccessResult = true;
                clearSuccessResetTimer();
                renderResultState();
            });
        }

        if (openPrimaryLoginButton) {
            openPrimaryLoginButton.addEventListener('click', function () {
                if (authPromptModal && authPromptModal.classList.contains('show')) {
                    authPromptModal.addEventListener('hidden.bs.modal', function handleHidden() {
                        authPromptModal.removeEventListener('hidden.bs.modal', handleHidden);
                        showLoginModalDirectly();
                    }, { once: true });

                    closeModal(authPromptModal);
                    return;
                }

                if (showLoginModalDirectly()) {
                    return;
                }

                openModal(loginModalElement);
            });
        }

        if (submitButton) {
            submitButton.addEventListener('mouseenter', function () {
                if (!submitButton.disabled && submitState !== 'success') {
                    applySubmitButtonVisualState('hover');
                }
            });
            submitButton.addEventListener('mouseleave', function () {
                updateSubmitButton();
            });
            submitButton.addEventListener('mousedown', function () {
                if (!submitButton.disabled && submitState !== 'success') {
                    applySubmitButtonVisualState('active');
                }
            });
            submitButton.addEventListener('mouseup', function () {
                if (!submitButton.disabled && submitState !== 'success') {
                    applySubmitButtonVisualState('hover');
                }
            });
            submitButton.addEventListener('click', submitAcceptance);
        }

        if (communityShareButton) {
            communityShareButton.addEventListener('click', async function () {
                if (communityShareButton.disabled || !selected || !communityShareUrl) {
                    return;
                }

                const originalLabel = communityShareButton.textContent;
                communityShareButton.disabled = true;
                communityShareButton.textContent = t('sharing', 'Paylaşılıyor...');
                if (communityShareFeedback) {
                    communityShareFeedback.textContent = '';
                }

                try {
                    const response = await fetch(communityShareUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            ...(csrfTokenMeta ? {'X-CSRF-TOKEN': csrfTokenMeta.getAttribute('content') || ''} : {}),
                        },
                        body: JSON.stringify({
                            event_id: selected.event_id,
                            market_type: selected.market_type,
                            market_specifier_key: selected.market_specifier_key || 'default',
                            outcome_code: selected.outcome_code,
                        }),
                    });
                    const payload = await response.json().catch(function () { return {}; });
                    if (!response.ok) {
                        throw new Error('share_failed');
                    }
                    if (communityShareFeedback) {
                        communityShareFeedback.textContent = t('share_success', 'Spor odasında paylaşıldı.');
                    }
                } catch (error) {
                    if (communityShareFeedback) {
                        communityShareFeedback.textContent = t('share_failed', 'Seçim paylaşılamadı.');
                    }
                } finally {
                    communityShareButton.textContent = originalLabel;
                    updateCommunityShareState();
                }
            });
        }

        tabTriggers.forEach(function (button) {
            button.addEventListener('click', function () {
                setActiveTab(button.dataset.tab || button.dataset.tabTrigger || serverDefaultTab);
            });
        });

        tabSwitchers.forEach(function (button) {
            button.addEventListener('click', function () {
                setActiveTab(button.dataset.tabSwitch || 'pre-match');
            });
        });

        bindSportFilterButtons(document);

        if (searchToggleButton) {
            searchToggleButton.addEventListener('click', function () {
                setToolbarPanel('search');
            });
        }

        if (themeToggleButton) {
            themeToggleButton.addEventListener('click', function () {
                toggleSportsbookTheme();
            });
        }

        if (autoAcceptOddsChangeToggle) {
            autoAcceptOddsChangeToggle.addEventListener('change', function () {
                applyAutoAcceptOddsPreference(autoAcceptOddsChangeToggle.checked, true);
            });
        }

        if (leagueFilterToggleButton) {
            leagueFilterToggleButton.addEventListener('click', function () {
                setToolbarPanel('league');
            });
        }

        if (competitionRailToggleButton) {
            competitionRailToggleButton.addEventListener('click', function () {
                setCompetitionRailCollapsed(!competitionRailCollapsed);
            });
        }

        competitionRailCloseButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                setCompetitionRailCollapsed(true);
            });
        });

        if (railSearchInput) {
            railSearchInput.addEventListener('input', function () {
                applySearchQuery(railSearchInput.value || '', {
                    detail: '',
                });
            });
        }

        if (railSearchClear) {
            railSearchClear.addEventListener('click', function () {
                applySearchQuery('', {
                    detail: '',
                });
                if (railSearchInput) {
                    railSearchInput.focus();
                }
            });
        }

        if (eventSearchInput) {
            eventSearchInput.addEventListener('input', function () {
                applySearchQuery(eventSearchInput.value || '', {
                    detail: '',
                });
            });
        }

        if (eventSearchClear) {
            eventSearchClear.addEventListener('click', function () {
                applySearchQuery('', {
                    detail: '',
                });
                if (eventSearchInput) {
                    eventSearchInput.focus();
                }
            });
        }

        searchPanelCloseButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                activeToolbarPanel = null;
                updateSearchPanelState();
                updateLeagueFilterButtons();
                if (searchToggleButton) {
                    searchToggleButton.focus();
                }
            });
        });

        bindLeagueFilterButtons(document);

        if (sportStrip) {
            sportStrip.addEventListener('scroll', syncSportScrollButtons, { passive: true });
        }

        if (sportScrollPrev) {
            sportScrollPrev.addEventListener('click', function () {
                if (!sportStrip) {
                    return;
                }

                sportStrip.scrollBy({
                    left: -Math.max(220, Math.floor(sportStrip.clientWidth * 0.6)),
                    behavior: 'smooth',
                });
            });
        }

        if (sportScrollNext) {
            sportScrollNext.addEventListener('click', function () {
                if (!sportStrip) {
                    return;
                }

                sportStrip.scrollBy({
                    left: Math.max(220, Math.floor(sportStrip.clientWidth * 0.6)),
                    behavior: 'smooth',
                });
            });
        }

        slipModeButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                const nextMode = button.dataset.slipModeTab || 'single';
                if (isSocialMarketSelection(selected) && (nextMode === 'combo' || nextMode === 'system')) {
                    activeSlipMode = 'single';
                    showInlineSubmitMessage(t('social_single_only', 'Sosyal Market seçimleri tekli oynanır.'));
                } else {
                    activeSlipMode = nextMode;
                }
                renderSlipModeState();
                applySlipModeVisibility();
                renderSelectionState();
            });
        });

        if (slipModeResetButton) {
            slipModeResetButton.addEventListener('click', function () {
                activeSlipMode = 'single';
                renderSlipModeState();
                applySlipModeVisibility();
                renderSelectionState();
            });
        }

        slipToggleButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                setSlipCollapsed(!slipCollapsed);
            });
        });

        historyTabs.forEach(function (button) {
            button.addEventListener('click', function () {
                if (button.disabled || button.dataset.historySupported !== '1') {
                    return;
                }

                activeHistoryFilter = button.dataset.historyFilter || 'all';
                activeHistoryPage = 1;
                historyTabs.forEach(function (tab) {
                    const isActive = tab === button;
                    tab.classList.toggle('is-active', isActive);
                    tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
                });
                updateHistoryVisibility();
            });
        });

        if (myBetsOpenButton) {
            myBetsOpenButton.addEventListener('click', function () {
                setMyBetsPanelOpen(Boolean(myBetsPanel && myBetsPanel.hidden));
            });
        }

        myBetsCloseButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                setMyBetsPanelOpen(false);
            });
        });

        if (historyItems) {
            historyItems.addEventListener('click', function (event) {
                const toggle = event.target.closest('[data-history-detail-toggle]');
                if (!toggle) {
                    return;
                }

                const item = toggle.closest('[data-history-item]');
                const detail = item ? item.querySelector('[data-history-item-detail]') : null;
                if (!detail) {
                    return;
                }

                const expanded = toggle.getAttribute('aria-expanded') === 'true';
                toggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
                toggle.setAttribute('aria-label', expanded ? t('history.show_detail', 'Kupon ayrıntılarını göster') : t('history.hide_detail', 'Kupon ayrıntılarını gizle'));
                detail.hidden = expanded;
                item.classList.toggle('is-expanded', !expanded);
            });
        }

        if (matchPagePrev) {
            matchPagePrev.addEventListener('click', function () {
                activeMatchPage -= 1;
                updateMatchPagination();
            });
        }

        if (matchPageNext) {
            matchPageNext.addEventListener('click', async function () {
                if (matchPageNext.disabled || matchPagination?.hidden || !loadMoreAvailableForCurrentScope()) {
                    return;
                }

                const rowRecordsForScope = filteredRowRecords(activePhaseForTab(), activeSportFilter, activeLeagueFilter, activeSearchQuery);
                const remotePagination = currentLiveOfferPaginationState();
                const needsRemotePage = rowRecordsForScope.length <= currentVisibleRowSet.size
                    && remotePagination
                    && remotePagination.hasMore
                    && remotePagination.nextCursor;

                matchPageNext.disabled = true;

                if (needsRemotePage) {
                    matchPageNext.textContent = t('loading', 'Yükleniyor');
                    scheduleBoardRefresh({
                        detail: '',
                        updateTabCounts: false,
                    });

                    await pollLiveOffers({
                        interrupt: true,
                        filters: currentLiveOfferFilters(),
                        cursor: remotePagination.nextCursor,
                        limit: visibleRowIncrement,
                        keepStaleRows: true,
                        chunkedInsert: true,
                        batchSize: 20,
                    });
                }

                visibleRowLimit += visibleRowIncrement;
                updateMatchPagination();
            });
        }

        if (matchPageNumbers) {
            matchPageNumbers.addEventListener('click', function (event) {
                const pageButton = event.target.closest('[data-match-page]');
                if (!pageButton) {
                    return;
                }

                const nextPage = Number.parseInt(pageButton.dataset.matchPage || '1', 10);
                if (!Number.isFinite(nextPage) || nextPage < 1 || nextPage === activeMatchPage) {
                    return;
                }

                activeMatchPage = nextPage;
                updateMatchPagination();
            });
        }

        if (historyPagePrev) {
            historyPagePrev.addEventListener('click', function () {
                activeHistoryPage -= 1;
                updateHistoryVisibility();
            });
        }

        if (historyPageNext) {
            historyPageNext.addEventListener('click', function () {
                activeHistoryPage += 1;
                updateHistoryVisibility();
            });
        }

        document.addEventListener('click', function (event) {
            const copyButton = event.target.closest('[data-copy-text]');
            if (!copyButton) {
                const clickedInsideToolbar = event.target.closest('.sb2-toolbar, .sb2-toolbar-panel, .sb2-topbar-actions');
                if (!clickedInsideToolbar && activeToolbarPanel !== null) {
                    activeToolbarPanel = null;
                    updateSearchPanelState();
                    updateLeagueFilterButtons();
                }
                return;
            }

            event.preventDefault();
            handleCopyAction(copyButton);
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && myBetsPanel && !myBetsPanel.hidden) {
                setMyBetsPanelOpen(false);
                return;
            }

            if (event.key === 'Escape' && catalogModal && !catalogModal.hidden) {
                setCatalogModalOpen(false);
                return;
            }

            if (event.key !== 'Escape' || activeToolbarPanel === null) {
                return;
            }

            activeToolbarPanel = null;
            updateSearchPanelState();
            updateLeagueFilterButtons();
        });

        document.addEventListener('visibilitychange', function () {
            if (!livePollEnabled) {
                return;
            }

            queueLivePoll(document.hidden ? livePollBackgroundMs : 250);
        });

        window.addEventListener('scroll', function () {
            queueLeagueWindowRefresh();
        }, { passive: true });

        window.addEventListener('resize', function () {
            if (window.innerWidth <= 1080 && !competitionRailCollapsed) {
                setCompetitionRailCollapsed(true);
                syncSlipViewportMode();
                queueLeagueWindowRefresh();
                queueActiveSportButtonVisibility({
                    immediate: true,
                    force: true,
                });
                return;
            }

            if (window.innerWidth > 1080 && competitionRailCollapsed) {
                setCompetitionRailCollapsed(false);
            }

            syncSlipViewportMode();
            queueLeagueWindowRefresh();
            queueActiveSportButtonVisibility({
                immediate: true,
                force: true,
            });
        });

        const activePanel = document.querySelector('[data-tab-panel].is-active:not([hidden])');
        const activeTrigger = document.querySelector('[data-tab].is-active, [data-tab-trigger].is-active');
        bindAccordionTriggers(document);
        bindLeagueFilterGroupToggles(document);
        bindSelectionTriggers(document);
        bindMoreToggleButtons(document);
        bindMarketFamilyChips(document);
        bindSportFilterButtons(document);
        bindLeagueFilterButtons(document);
        window.sportsbookShellBindSelectionTriggers = function (root) {
            bindSelectionTriggers(root || document);
            refreshSelectionTriggers();
        };
        if (featuredMatchesToggleButton) {
            featuredMatchesToggleButton.addEventListener('click', function () {
                handleFeaturedMatchesToggle();
            });
        }
        if (nextMatchesToggleButton) {
            nextMatchesToggleButton.addEventListener('click', function () {
                handleNextMatchesToggle();
            });
        }
        if (allMatchesToggleButton) {
            allMatchesToggleButton.addEventListener('click', function () {
                handleAllMatchesToggle();
            });
        }
        if (activeFilterClearButton) {
            activeFilterClearButton.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                clearActiveLeagueFilter();
            });
        }
        if (catalogModalOpen) {
            catalogModalOpen.addEventListener('click', function () {
                setCatalogModalOpen(!(catalogModal && !catalogModal.hidden));
            });
        }
        if (catalogModalBack) {
            catalogModalBack.addEventListener('click', function () {
                navigateCatalogBack();
            });
        }
        catalogCloseButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                setCatalogModalOpen(false);
            });
        });
        refreshDynamicCollections();
        updateSearchPanelState();
        updateUtilityButtons();
        setCompetitionRailCollapsed(competitionRailCollapsed);
        syncSlipViewportMode(true);
        setHydrationUi('idle', t('background_loading', 'Tam turnuva görünümü arka planda tamamlanacak.'));
        setActiveTab(
            (activePanel && activePanel.dataset.tabPanel)
            || (activeTrigger && (activeTrigger.dataset.tab || activeTrigger.dataset.tabTrigger))
            || serverDefaultTab
        );
        if (new URLSearchParams(window.location.search).get('catalog') === '1') {
            setCatalogModalOpen(true);
            const catalogUrl = new URL(window.location.href);
            catalogUrl.searchParams.delete('catalog');
            window.history.replaceState(window.history.state, '', catalogUrl.pathname + catalogUrl.search + catalogUrl.hash);
        }
        syncStakeChipValues();
        syncSportScrollButtons();
        queueActiveSportButtonVisibility({
            immediate: true,
            force: true,
        });
        updateHistoryVisibility();
        renderSlipModeState();
        renderSelectionState();
        applyInitialPrefill();
        // Inactive modules load on explicit activation, never on initial-board completion.
        bootstrapFullCoverageHydration().finally(function () {
            queueLivePoll(livePollVisibleMs);
        });
    })();

    (function initSocialMarketFilters() {
        const roots = Array.from(document.querySelectorAll('[data-social-market-root]'));
        if (!roots.length) {
            return;
        }

        roots.forEach(function (root) {
        const rail = root.querySelector('[data-social-market-chip-rail]');
        const grid = root.querySelector('[data-social-market-grid]');
        const pollConfig = readSportsbookShellJson('sportsbookShellSocialMarketPolling', null);
        const i18n = readSportsbookShellJson('sportsbookShellI18n', {});
        const staticSocialMarketPayload = root.dataset.socialMarketStatic === 'true';
        const socialMarketCategoryScope = String(root.dataset.socialMarketCategoryScope || '');
        let chips = Array.from(root.querySelectorAll('[data-social-market-category-filter]'));
        let cards = Array.from(root.querySelectorAll('[data-social-market-card]'));
        const socialSearchInput = document.querySelector('[data-event-search-input]');
        const socialSearchClear = document.querySelector('[data-event-search-clear]');
        const socialSearchCount = document.querySelector('[data-event-search-count]');
        let activeSocialCategory = '';
        let socialMarketLoadInFlight = false;
        let socialMarketLoadPromise = null;
        let socialMarketLoaded = cards.length > 0;
        const socialPanelKey = String(root.closest('[data-tab-panel]')?.dataset.tabPanel || 'social-market');
        window.sportsbookShellSetSurfaceState(socialPanelKey, socialMarketLoaded ? 'ready' : 'not_requested');

        function escapeHtml(value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function t(key, fallback, replacements) {
            const raw = key.startsWith('social_market.')
                ? (i18n.social_market || {})[key.slice('social_market.'.length)]
                : i18n[key];
            let value = typeof raw === 'string' ? raw : fallback;
            Object.entries(replacements || {}).forEach(function (entry) {
                value = value.split(':' + entry[0]).join(String(entry[1]));
            });
            return value;
        }

        function normalizeSocialSearch(value) {
            return String(value || '')
                .toLocaleLowerCase('tr-TR')
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .trim();
        }

        function socialCardSearchText(card) {
            return normalizeSocialSearch(card.dataset.socialMarketSearch || card.textContent || '');
        }

        function currentSocialSearchQuery() {
            return normalizeSocialSearch(socialSearchInput ? socialSearchInput.value : '');
        }

        function isSocialMarketActive() {
            const panel = root.closest('[data-tab-panel]');
            return !!(panel && !panel.hidden && panel.classList.contains('is-active'));
        }

        function refreshSocialMarketCollections() {
            chips = Array.from(root.querySelectorAll('[data-social-market-category-filter]'));
            cards = Array.from(root.querySelectorAll('[data-social-market-card]'));
        }

        function applyCategory(categoryKey) {
            refreshSocialMarketCollections();
            if (!chips.length || !cards.length) {
                return;
            }

            activeSocialCategory = categoryKey || activeSocialCategory || String(chips[0].dataset.socialMarketCategoryFilter || '');
            const normalizedSearch = currentSocialSearchQuery();
            let visibleCount = 0;

            chips.forEach(function (chip) {
                const active = String(chip.dataset.socialMarketCategoryFilter || '') === activeSocialCategory;
                chip.classList.toggle('is-active', active);
                chip.setAttribute('aria-selected', active ? 'true' : 'false');
                chip.setAttribute('tabindex', active ? '0' : '-1');

                if (active && typeof chip.scrollIntoView === 'function') {
                    chip.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' });
                }
            });

            cards.forEach(function (card) {
                const cardCategory = String(card.dataset.socialMarketCategory || '');
                const categoryMatches = normalizedSearch !== ''
                    || activeSocialCategory === ''
                    || activeSocialCategory === 'all'
                    || cardCategory === activeSocialCategory;
                const searchMatches = normalizedSearch === '' || socialCardSearchText(card).indexOf(normalizedSearch) !== -1;
                const visible = categoryMatches && searchMatches;

                card.hidden = !visible;
                if (visible) {
                    visibleCount += 1;
                }
            });

            if (socialSearchCount && normalizedSearch !== '' && isSocialMarketActive()) {
                socialSearchCount.textContent = visibleCount + ' sosyal market sonucu.';
            }
        }

        function chipFromTarget(target) {
            if (!target || typeof target.closest !== 'function') {
                return null;
            }

            const chip = target.closest('[data-social-market-category-filter]');
            return chip && root.contains(chip) ? chip : null;
        }

        function socialPriceLabel(value) {
            const numberValue = Number(value || 0);
            if (!Number.isFinite(numberValue) || numberValue <= 0) {
                return 'x-';
            }

            return 'x' + numberValue.toFixed(2).replace(/0+$/, '').replace(/\.$/, '');
        }

        function createSocialChip(category, index) {
            const categoryKey = String((category && category.key) || '');
            const categoryTitle = String((category && category.title) || categoryKey || 'Kategori');
            const categoryIcon = String((category && category.icon) || '').trim();
            const count = Number((category && category.count) || 0);
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'sb2-social-market-chip' + (index === 0 ? ' is-active' : '');
            button.dataset.socialMarketCategoryFilter = categoryKey;
            button.setAttribute('aria-selected', index === 0 ? 'true' : 'false');

            const icon = document.createElement('span');
            icon.className = 'sb2-social-market-chip-icon';
            if (categoryIcon.indexOf('fa') === 0) {
                const node = document.createElement('i');
                node.className = categoryIcon;
                node.setAttribute('aria-hidden', 'true');
                icon.appendChild(node);
            } else {
                icon.textContent = String(categoryIcon || categoryTitle).slice(0, 2).toUpperCase();
            }

            const title = document.createElement('span');
            title.textContent = categoryTitle;

            const counter = document.createElement('strong');
            counter.dataset.countRaw = String(count);
            counter.textContent = count > 1000 ? '1000+' : String(Math.max(0, count));

            button.append(icon, title, counter);
            return button;
        }

        function createSocialCard(market, firstCategoryKey) {
            const marketPayload = market && typeof market === 'object' ? market : {};
            const render = marketPayload.render && typeof marketPayload.render === 'object' ? marketPayload.render : {};
            const categoryKey = String(marketPayload.category_key || '');
            const titleText = String(marketPayload.title || t('social_market_title', 'Sosyal Market'));
            const topicText = String(render.topic || marketPayload.subtitle || marketPayload.category_title || '');
            const badgeText = String(render.badge || 'SM').trim();
            const iconClass = String(render.thumbnail_icon || 'fas fa-chart-line').trim();
            const variant = String(render.card_variant || 'standard').trim();
            const searchParts = [
                marketPayload.title,
                marketPayload.subtitle,
                marketPayload.category_title,
                topicText,
                badgeText,
                ...(Array.isArray(marketPayload.outcomes) ? marketPayload.outcomes.map(function (outcome) {
                    return outcome && outcome.label;
                }) : []),
            ].filter(Boolean);

            const card = document.createElement('article');
            card.className = 'sb2-social-market-card';
            card.dataset.socialMarketCard = '1';
            card.dataset.socialMarketCategory = categoryKey;
            card.dataset.socialMarketSearch = normalizeSocialSearch(searchParts.join(' '));
            if (firstCategoryKey && categoryKey !== firstCategoryKey) {
                card.hidden = true;
            }

            const head = document.createElement('button');
            head.type = 'button';
            head.className = 'sb2-social-market-card-head';
            head.dataset.socialMarketDetailTrigger = '1';
            head.setAttribute('aria-label', t('open_detail_for', ':title ayrıntısını aç', { title: titleText }));

            const thumb = document.createElement('span');
            thumb.className = 'sb2-social-market-thumb';
            thumb.dataset.variant = variant;
            if (iconClass.indexOf('fa') === 0) {
                const icon = document.createElement('i');
                icon.className = iconClass;
                icon.setAttribute('aria-hidden', 'true');
                thumb.appendChild(icon);
            }
            const badge = document.createElement('strong');
            badge.textContent = badgeText || String(marketPayload.category_title || 'SM').slice(0, 2).toUpperCase();
            thumb.appendChild(badge);

            const titleBlock = document.createElement('div');
            titleBlock.className = 'sb2-social-market-title-block';
            const title = document.createElement('h3');
            title.textContent = titleText;
            const topic = document.createElement('span');
            topic.textContent = topicText || String(marketPayload.category_title || '');
            titleBlock.append(title, topic);

            const chevron = document.createElement('span');
            chevron.className = 'sb2-social-market-chevron';
            chevron.setAttribute('aria-hidden', 'true');
            const chevronIcon = document.createElement('i');
            chevronIcon.className = 'fas fa-chevron-right';
            chevron.appendChild(chevronIcon);
            head.append(thumb, titleBlock, chevron);
            card.appendChild(head);

            if (marketPayload.subtitle) {
                const subtitle = document.createElement('p');
                subtitle.className = 'sb2-social-market-subtitle';
                subtitle.textContent = String(marketPayload.subtitle);
                card.appendChild(subtitle);
            }

            const outcomes = document.createElement('div');
            outcomes.className = 'sb2-social-market-outcomes';
            (Array.isArray(marketPayload.outcomes) ? marketPayload.outcomes : []).forEach(function (outcome) {
                const tone = String((outcome && outcome.render && outcome.render.tone) || 'neutral');
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'sb2-social-market-outcome sb2-odd-box is-' + (['yes', 'no', 'neutral'].includes(tone) ? tone : 'neutral');
                button.dataset.selectionTrigger = '1';
                button.dataset.selection = JSON.stringify(outcome || {});

                const label = document.createElement('span');
                label.className = 'sb2-odd-box-label';
                label.textContent = String((outcome && outcome.label) || '-');
                const price = document.createElement('span');
                price.className = 'sb2-odd-box-value';
                price.textContent = socialPriceLabel(outcome && outcome.price);
                button.append(label, price);
                outcomes.appendChild(button);
            });
            card.appendChild(outcomes);

            const metrics = document.createElement('div');
            metrics.className = 'sb2-social-market-metrics';
            metrics.setAttribute('aria-label', t('social_market.stats_aria', 'Sosyal market istatistikleri'));
            const volume = document.createElement('span');
            const volumeIcon = document.createElement('i');
            volumeIcon.className = 'fas fa-chart-bar';
            volumeIcon.setAttribute('aria-hidden', 'true');
            volume.append(volumeIcon, ' ' + t('social_market.interest_score', 'İlgi skoru') + ': ' + String(render.volume_label || '-'));
            const bettors = document.createElement('span');
            const bettorsIcon = document.createElement('i');
            bettorsIcon.className = 'fas fa-users';
            bettorsIcon.setAttribute('aria-hidden', 'true');
            bettors.append(bettorsIcon, ' ' + t('social_market.community_interest', 'Topluluk ilgisi') + ': ' + String(render.bettors_label || '-'));
            metrics.append(volume, bettors);
            card.appendChild(metrics);

            return card;
        }

        function renderSocialMarketPayload(payload) {
            const data = payload && typeof payload === 'object' ? payload : {};
            const categories = (Array.isArray(data.categories) ? data.categories : []).filter(function (category) {
                return socialMarketCategoryScope === '' || String((category && category.key) || '') === socialMarketCategoryScope;
            });
            const markets = (Array.isArray(data.markets) ? data.markets : []).filter(function (market) {
                return socialMarketCategoryScope === '' || String((market && market.category_key) || '') === socialMarketCategoryScope;
            });
            const firstCategoryKey = String((categories[0] && categories[0].key) || '');
            const panel = root.closest('[data-tab-panel]');
            const panelKey = panel ? String(panel.dataset.tabPanel || '') : 'social-market';
            const statsMarketCount = socialMarketCategoryScope === ''
                ? Number(((data.stats || {}).market_count) || markets.length || 0)
                : Number(markets.length || 0);
            window.sportsbookShellSetTabBadgeCount(panelKey, statsMarketCount);
            window.sportsbookShellSetSurfaceState(panelKey, 'ready');

            if (rail) {
                rail.replaceChildren();
                categories.forEach(function (category, index) {
                    rail.appendChild(createSocialChip(category, index));
                });
            }

            if (grid) {
                grid.replaceChildren();
                if (!markets.length) {
                    const empty = document.createElement('div');
                    empty.className = 'sb2-feed-empty';
                    empty.textContent = t('social_empty', 'Gösterilecek sosyal market bulunamadı.');
                    grid.appendChild(empty);
                } else {
                    markets.forEach(function (market) {
                        grid.appendChild(createSocialCard(market, firstCategoryKey));
                    });
                }
            }

            socialMarketLoaded = true;
            activeSocialCategory = firstCategoryKey;
            refreshSocialMarketCollections();
            if (typeof window.sportsbookShellBindSelectionTriggers === 'function') {
                window.sportsbookShellBindSelectionTriggers(root);
            }
            if (chips.length && cards.length) {
                applyCategory(activeSocialCategory);
            }
        }

        async function ensureSocialMarketLoaded() {
            if (staticSocialMarketPayload) {
                return Promise.resolve();
            }

            if (socialMarketLoaded) {
                return Promise.resolve();
            }

            if (socialMarketLoadInFlight) {
                return socialMarketLoadPromise || Promise.resolve();
            }

            if (!pollConfig || !pollConfig.enabled || !pollConfig.items_url) {
                return Promise.resolve();
            }

            socialMarketLoadInFlight = true;
            window.sportsbookShellSetSurfaceState(socialPanelKey, 'loading');
            if (grid) {
                grid.innerHTML = '<div class="sb2-feed-empty">' + escapeHtml(t('social_loading', 'Sosyal Market yükleniyor.')) + '</div>';
            }

            socialMarketLoadPromise = (async function () {
                try {
                const response = await window.sportsbookShellRequestJson(String(pollConfig.items_url), {
                    headers: {
                        'Accept': 'application/json',
                    },
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    throw new Error('Social market request failed');
                }

                const payload = await response.json();
                renderSocialMarketPayload((payload || {}).data || {});
                } catch (error) {
                    window.sportsbookShellSetSurfaceState(socialPanelKey, 'error');
                    if (grid) {
                        grid.innerHTML = '<div class="sb2-feed-empty">' + escapeHtml(t('social_error', 'Sosyal Market şu anda yüklenemedi.')) + '</div>';
                    }
                } finally {
                    socialMarketLoadInFlight = false;
                    socialMarketLoadPromise = null;
                }
            })();

            return socialMarketLoadPromise;
        }

        root.addEventListener('click', function (event) {
            const chip = chipFromTarget(event.target);
            if (!chip) {
                return;
            }

            event.preventDefault();
            applyCategory(String(chip.dataset.socialMarketCategoryFilter || ''));
        });

        if (socialSearchInput) {
            socialSearchInput.addEventListener('input', function () {
                applyCategory(activeSocialCategory);
            });
        }

        if (socialSearchClear) {
            socialSearchClear.addEventListener('click', function () {
                window.setTimeout(function () {
                    applyCategory(activeSocialCategory);
                }, 0);
            });
        }

        document.querySelectorAll('[data-tab-trigger]').forEach(function (button) {
            button.addEventListener('click', function () {
                const buttonKey = button.dataset.tab || button.dataset.tabTrigger || '';
                const panel = root.closest('[data-tab-panel]');
                const panelKey = panel ? String(panel.dataset.tabPanel || '') : 'social-market';
                if (buttonKey !== panelKey) {
                    return;
                }

                window.setTimeout(function () {
                    ensureSocialMarketLoaded().then(function () {
                        applyCategory(activeSocialCategory);
                    });
                }, 0);
            });
        });

        chips.forEach(function (chip) {
            chip.addEventListener('keydown', function (event) {
                if (!['ArrowRight', 'ArrowLeft', 'Home', 'End'].includes(event.key)) {
                    return;
                }

                event.preventDefault();
                const currentIndex = chips.indexOf(chip);
                let nextIndex = currentIndex;
                if (event.key === 'ArrowRight') {
                    nextIndex = Math.min(chips.length - 1, currentIndex + 1);
                } else if (event.key === 'ArrowLeft') {
                    nextIndex = Math.max(0, currentIndex - 1);
                } else if (event.key === 'Home') {
                    nextIndex = 0;
                } else if (event.key === 'End') {
                    nextIndex = chips.length - 1;
                }

                const nextChip = chips[nextIndex] || chip;
                applyCategory(String(nextChip.dataset.socialMarketCategoryFilter || ''));
                nextChip.focus({ preventScroll: true });
            });
        });

        root.addEventListener('keydown', function (event) {
            const chip = chipFromTarget(event.target);
            if (!chip || !['ArrowRight', 'ArrowLeft', 'Home', 'End'].includes(event.key)) {
                return;
            }

            refreshSocialMarketCollections();
            event.preventDefault();
            const currentIndex = chips.indexOf(chip);
            let nextIndex = currentIndex;
            if (event.key === 'ArrowRight') {
                nextIndex = Math.min(chips.length - 1, currentIndex + 1);
            } else if (event.key === 'ArrowLeft') {
                nextIndex = Math.max(0, currentIndex - 1);
            } else if (event.key === 'Home') {
                nextIndex = 0;
            } else if (event.key === 'End') {
                nextIndex = chips.length - 1;
            }

            const nextChip = chips[nextIndex] || chip;
            applyCategory(String(nextChip.dataset.socialMarketCategoryFilter || ''));
            nextChip.focus({ preventScroll: true });
        });

        if (rail) {
            rail.addEventListener('wheel', function (event) {
                if (rail.scrollWidth <= rail.clientWidth || Math.abs(event.deltaY) <= Math.abs(event.deltaX)) {
                    return;
                }

                event.preventDefault();
                rail.scrollLeft += event.deltaY;
            }, { passive: false });
        }

        const detailModal = root.querySelector('[data-social-market-detail-modal]');
        const modalPanel = detailModal ? detailModal.querySelector('.sb2-social-market-modal-panel') : null;
        const modalThumb = detailModal ? detailModal.querySelector('[data-social-market-modal-thumb]') : null;
        const modalTopic = detailModal ? detailModal.querySelector('[data-social-market-modal-topic]') : null;
        const modalTitle = detailModal ? detailModal.querySelector('[data-social-market-modal-title]') : null;
        const modalSubtitle = detailModal ? detailModal.querySelector('[data-social-market-modal-subtitle]') : null;
        const modalOutcomes = detailModal ? detailModal.querySelector('[data-social-market-modal-outcomes]') : null;
        const modalMetrics = detailModal ? detailModal.querySelector('[data-social-market-modal-metrics]') : null;
        let lastSocialMarketDetailTrigger = null;

        function closeSocialMarketDetail() {
            if (!detailModal || detailModal.hidden) {
                return;
            }

            detailModal.hidden = true;
            detailModal.setAttribute('aria-hidden', 'true');

            if (lastSocialMarketDetailTrigger && typeof lastSocialMarketDetailTrigger.focus === 'function') {
                lastSocialMarketDetailTrigger.focus({ preventScroll: true });
            }
        }

        function openSocialMarketDetail(card, trigger) {
            if (!detailModal || !modalPanel || !card) {
                return;
            }

            const title = card.querySelector('.sb2-social-market-title-block h3');
            const topic = card.querySelector('.sb2-social-market-title-block span');
            const subtitle = card.querySelector('.sb2-social-market-subtitle');
            const thumb = card.querySelector('.sb2-social-market-thumb');
            const outcomes = Array.from(card.querySelectorAll('.sb2-social-market-outcome[data-selection-trigger]'));
            const metrics = card.querySelector('.sb2-social-market-metrics');

            lastSocialMarketDetailTrigger = trigger || null;

            if (modalTitle) {
                modalTitle.textContent = title ? title.textContent.trim() : '';
            }
            if (modalTopic) {
                modalTopic.textContent = topic ? topic.textContent.trim() : '';
            }
            if (modalSubtitle) {
                modalSubtitle.textContent = subtitle ? subtitle.textContent.trim() : '';
                modalSubtitle.hidden = !modalSubtitle.textContent.trim();
            }
            if (modalThumb) {
                modalThumb.replaceChildren();
                if (thumb) {
                    modalThumb.appendChild(thumb.cloneNode(true));
                }
            }
            if (modalOutcomes) {
                modalOutcomes.replaceChildren();
                outcomes.forEach(function (button) {
                    const clone = button.cloneNode(true);
                    delete clone.dataset.boundSelectionTrigger;
                    modalOutcomes.appendChild(clone);
                });
            }
            if (modalMetrics) {
                modalMetrics.replaceChildren();
                if (metrics) {
                    Array.from(metrics.children).forEach(function (item) {
                        modalMetrics.appendChild(item.cloneNode(true));
                    });
                }
            }

            detailModal.hidden = false;
            detailModal.setAttribute('aria-hidden', 'false');

            const closeButton = detailModal.querySelector('.sb2-social-market-modal-close');
            if (closeButton && typeof closeButton.focus === 'function') {
                closeButton.focus({ preventScroll: true });
            }
        }

        if (detailModal) {
            root.addEventListener('click', function (event) {
                const trigger = event.target && typeof event.target.closest === 'function'
                    ? event.target.closest('[data-social-market-detail-trigger]')
                    : null;
                if (!trigger || !root.contains(trigger)) {
                    return;
                }

                const card = trigger.closest('[data-social-market-card]');
                if (!card) {
                    return;
                }

                event.preventDefault();
                openSocialMarketDetail(card, trigger);
            });

            detailModal.addEventListener('click', function (event) {
                const closeTrigger = event.target && typeof event.target.closest === 'function'
                    ? event.target.closest('[data-social-market-detail-close]')
                    : null;
                if (closeTrigger && detailModal.contains(closeTrigger)) {
                    event.preventDefault();
                    closeSocialMarketDetail();
                    return;
                }

                const selectionTrigger = event.target && typeof event.target.closest === 'function'
                    ? event.target.closest('[data-selection-trigger]')
                    : null;
                if (selectionTrigger && detailModal.contains(selectionTrigger)) {
                    event.preventDefault();
                    const modalPayload = JSON.parse(selectionTrigger.dataset.selection || '{}');
                    const modalSelectionKey = String(modalPayload.selection_key || '');
                    const originalTrigger = Array.from(root.querySelectorAll('[data-social-market-card] .sb2-social-market-outcome[data-selection-trigger]')).find(function (button) {
                        const payload = JSON.parse(button.dataset.selection || '{}');
                        return modalSelectionKey !== '' && String(payload.selection_key || '') === modalSelectionKey;
                    });

                    if (originalTrigger && typeof originalTrigger.click === 'function') {
                        originalTrigger.click();
                    }

                    window.setTimeout(closeSocialMarketDetail, 0);
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    closeSocialMarketDetail();
                }
            });
        }

        const initialPayload = readSportsbookShellJson('sportsbookShellInitialSocialMarkets', null);
        if (!socialMarketLoaded && initialPayload && Array.isArray(initialPayload.markets) && initialPayload.markets.length) {
            renderSocialMarketPayload(initialPayload);
        }

        document.addEventListener('sportsbook-shell-tab-activated', function (event) {
            const panel = root.closest('[data-tab-panel]');
            const panelKey = panel ? String(panel.dataset.tabPanel || '') : '';
            if (((event || {}).detail || {}).tab !== panelKey) {
                return;
            }

            ensureSocialMarketLoaded().then(function () {
                applyCategory(activeSocialCategory);
            });
        });

        if (isSocialMarketActive()) {
            ensureSocialMarketLoaded();
        }

        refreshSocialMarketCollections();
        const activeChip = chips.find(function (chip) {
            return chip.classList.contains('is-active');
        }) || chips[0];

        if (activeChip) {
            applyCategory(String(activeChip.dataset.socialMarketCategoryFilter || ''));
        }
        });
    })();

    (function () {
        const root = document.querySelector('[data-bet-feed-root]');
        const stream = root ? root.querySelector('[data-bet-feed-stream]') : null;
        const template = document.getElementById('sb2BetFeedRowTemplate');
        const pollConfig = readSportsbookShellJson('sportsbookShellBetFeedPolling', null);
        const i18n = readSportsbookShellJson('sportsbookShellBetFeedI18n', {});

        if (!root || !stream || !template) {
            return;
        }

        const seenIds = new Set(
            Array.from(stream.querySelectorAll('.sb2-feed-item'))
                .map(function (row) {
                    return String(row.dataset.itemId || '');
                })
                .filter(Boolean)
        );
        let pollTimer = null;
        let addFeedbackTimer = null;
        let selectedFeedSelectionKey = '';
        let initialBetFeedLoaded = seenIds.size > 0;
        let betFeedLoadInFlight = false;
        let betFeedLoadPromise = null;
        window.sportsbookShellSetSurfaceState('bet-feed', initialBetFeedLoaded ? 'ready_items' : 'not_requested');

        const t = function (key, fallback) {
            const value = i18n && Object.prototype.hasOwnProperty.call(i18n, key) ? i18n[key] : '';

            return typeof value === 'string' && value !== '' ? value : fallback;
        };

        const money = function (value) {
            return Number(value || 0).toLocaleString('tr-TR', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 0,
            }) + ' TL';
        };

        const relativeLabel = function (dateIso) {
            const createdAt = new Date(dateIso);
            const diffMs = Math.max(0, Date.now() - createdAt.getTime());
            const minutes = Math.floor(diffMs / 60000);

            if (minutes < 1) {
                return t('just_now', 'Now');
            }

            if (minutes < 60) {
                return minutes + ' ' + t('minutes_ago_suffix', 'min ago');
            }

            const hours = Math.floor(minutes / 60);
            if (hours < 24) {
                return hours + ' ' + t('hours_ago_suffix', 'h ago');
            }

            return createdAt.toLocaleString(t('datetime_locale', 'en-US'), {
                day: '2-digit',
                month: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
            });
        };

        const renderDetailLines = function (container, lines) {
            container.innerHTML = '';

            (Array.isArray(lines) ? lines : []).forEach(function (line, index) {
                const row = document.createElement('div');
                row.className = 'sb2-feed-detail-line';

                const left = document.createElement('span');
                left.textContent = line;

                row.appendChild(left);
                container.appendChild(row);
            });
        };

        const hydrateRow = function (row, item) {
            row.dataset.itemId = item.feed_item_id || '';
            row.dataset.feedStatus = item.status || 'unavailable';
            row.dataset.expiresAt = item.expires_at || '';
            row.classList.remove('is-expanded');
            const text = function (role, value) {
                row.querySelector('[data-role="' + role + '"]').textContent = value || '—';
            };
            text('event-label', item.event_label);
            text('summary-button', item.short_coupon_label);
            text('league-label', item.league_name);
            text('outcome-label', item.outcome_label);
            text('source-label', item.source_label);
            text('display-name', item.creator_display);
            text('status-label', item.status_label);
            text('odds-value', item.odds_total == null ? '—' : Number(item.odds_total).toFixed(2));
            ['created', 'checked'].forEach(function (kind) {
                const node = row.querySelector('[data-role="' + kind + '-time"]');
                node.dateTime = item[kind + '_at'] || '';
                node.textContent = feedTimeLabel(item[kind + '_at']);
            });
            text('generation-time', feedTimeLabel(item.read_model_generated_at));
            const enabled = sportsbookFeedItemAddable(item);
            const button = row.querySelector('[data-role="betslip-button"]');
            button.dataset.defaultLabel = t('add_to_betslip', 'Add to betslip');
            button.dataset.betFeedAddSelection = JSON.stringify(enabled ? item.selection_reference : null);
            button.disabled = !enabled;
            button.hidden = !enabled;
            button.classList.toggle('is-disabled', !enabled);
            const reason = row.querySelector('[data-role="disabled-reason"]');
            reason.textContent = enabled ? '' : (item.disabled_reason_label || t('not_addable', 'This coupon can no longer be added.'));
            reason.hidden = enabled;
            const nativeShare = row.querySelector('[data-role="native-share"]');
            if (nativeShare) {
                nativeShare.dataset.feedShareUrl = item.share_url || '';
                nativeShare.disabled = !item.share_url;
            }
            const roomShare = row.querySelector('[data-role="sports-room-share"]');
            if (roomShare) {
                roomShare.dataset.feedChatShareRoute = item.chat_share_route || '';
                roomShare.disabled = !item.chat_share_route;
            }
        };

        const feedTimeLabel = function (value) {
            const date = new Date(value || '');
            return Number.isFinite(date.getTime())
                ? date.toLocaleString(t('datetime_locale', 'en-US'), {day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit'})
                : '—';
        };

        const setAddButtonLabel = function (button, label) {
            if (!button) {
                return;
            }

            const labelNode = button.querySelector('.sb2-feed-add-label');
            if (labelNode) {
                labelNode.textContent = label;
                return;
            }

            button.textContent = label;
        };

        const feedSelectionKey = function (selection) {
            if (!selection) {
                return '';
            }

            const explicitKey = String(selection.selection_key || '').trim();
            if (explicitKey !== '') {
                return explicitKey;
            }

            return [
                selection.event_id || '',
                selection.market_type || '',
                selection.market_specifier_key || 'default',
                selection.outcome_code || '',
            ].join('::');
        };

        const expireFeedRow = function (row) {
            if (!row) return;
            row.dataset.feedStatus = 'stale';
            const button = row.querySelector('[data-bet-feed-add-selection]');
            button.disabled = true;
            button.hidden = true;
            button.dataset.betFeedAddSelection = 'null';
            row.querySelector('[data-role="odds-value"]').textContent = '—';
            row.querySelector('[data-role="status-label"]').textContent = t('stale', 'Needs refresh');
            const reason = row.querySelector('[data-role="disabled-reason"]');
            reason.hidden = false;
            reason.textContent = t('not_addable', 'This coupon can no longer be added.');
        };

        const expireVisibleFeedRows = function () {
            if (document.hidden || !isBetFeedActive()) return;
            stream.querySelectorAll('.sb2-feed-contract').forEach(function (row) {
                if (!(Date.parse(row.dataset.expiresAt) > Date.now())) expireFeedRow(row);
            });
        };

        const selectionForAddButton = function (button) {
            if (!button) {
                return null;
            }

            try {
                return JSON.parse(button.dataset.betFeedAddSelection || '{}');
            } catch (error) {
                return null;
            }
        };

        const resetFeedAddButton = function (button) {
            if (!button) {
                return;
            }

            button.classList.remove('is-pending', 'is-added', 'is-failed');
            const selection = selectionForAddButton(button);
            const isSelected = !button.disabled
                && !button.classList.contains('is-disabled')
                && selectedFeedSelectionKey !== ''
                && feedSelectionKey(selection) === selectedFeedSelectionKey;

            if (isSelected) {
                button.classList.add('is-added');
                setAddButtonLabel(button, t('added', 'Added'));
                button.closest('.sb2-feed-item')?.classList.add('is-feed-selected');
                return;
            }

            button.closest('.sb2-feed-item')?.classList.remove('is-feed-selected');
            setAddButtonLabel(button, button.dataset.defaultLabel || t('add_to_betslip', 'Add to betslip'));
        };

        const clearSelectedFeedButton = function (exceptButton) {
            Array.from(root.querySelectorAll('[data-bet-feed-add-selection]')).forEach(function (button) {
                if (button === exceptButton) {
                    return;
                }

                button.classList.remove('is-added', 'is-pending', 'is-failed');
                button.closest('.sb2-feed-item')?.classList.remove('is-feed-selected');
                if (!button.disabled && !button.classList.contains('is-disabled')) {
                    setAddButtonLabel(button, button.dataset.defaultLabel || t('add_to_betslip', 'Add to betslip'));
                }
            });
        };

        const markFeedAddButtonSelected = function (button, selection) {
            selectedFeedSelectionKey = feedSelectionKey(selection);
            clearSelectedFeedButton(button);
            button.classList.remove('is-pending', 'is-failed');
            button.classList.add('is-added');
            button.closest('.sb2-feed-item')?.classList.add('is-feed-selected');
            setAddButtonLabel(button, t('added', 'Added'));
        };

        const restoreAddButton = function (button) {
            resetFeedAddButton(button);
        };

        const renderFeedItems = function (items, options) {
            const records = Array.isArray(items) ? items : [];
            const previousIds = new Set(seenIds);
            const hasCopyable = records.some(function (item) {
                return sportsbookFeedItemAddable(item);
            });

            root.classList.remove('is-feed-updating');
            delete root.dataset.loadingVisual;
            stream.innerHTML = '';
            seenIds.clear();

            if (!records.length) {
                const empty = document.createElement('div');
                empty.className = 'sb2-feed-empty';
                empty.dataset.betFeedEmpty = '1';
                const state = options && options.state;
                empty.textContent = state === 'degraded_stale_source'
                    ? window.sportsbookShellSurfaceText('feed_stale_source', 'Bahis akışı için güncel maç verisi şu anda kullanılamıyor. Eski içerik gösterilmiyor.')
                    : (state === 'error'
                        ? window.sportsbookShellSurfaceText('feed_load_error', 'Bahis akışı yüklenemedi. Tekrar deneyin.')
                        : t('empty', 'No recent coupons with sharing permission from the last 24 hours.'));
                stream.appendChild(empty);
                return;
            }

            if (!hasCopyable) {
                const empty = document.createElement('div');
                empty.className = 'sb2-feed-empty sb2-feed-copyable-empty';
                empty.dataset.betFeedCopyableEmpty = '1';
                const title = document.createElement('strong');
                title.textContent = t('copyable_empty', 'No currently addable selections.');
                const detail = document.createElement('span');
                detail.textContent = t('passive_description', 'These coupons are shown without current odds.');
                empty.append(title, detail);
                stream.appendChild(empty);
            }

            records.forEach(function (item) {
                const itemId = String(item && item.id || '');
                if (!itemId) {
                    return;
                }

                const fragment = template.content.cloneNode(true);
                const row = fragment.querySelector('.sb2-feed-item');
                hydrateRow(row, item);
                if (options && options.markNew && !previousIds.has(itemId)) {
                    row.classList.add('is-new');
                    window.setTimeout(function () {
                        row.classList.remove('is-new');
                    }, 700);
                }
                resetFeedAddButton(row.querySelector('[data-bet-feed-add-selection]'));
                stream.appendChild(row);
                seenIds.add(itemId);
            });

            if (typeof window.sportsbookShellApplyBetFeedSearch === 'function') {
                window.sportsbookShellApplyBetFeedSearch();
            }
        };

        const renderFeedLoadingState = function () {
            const hasRows = stream.querySelectorAll('.sb2-feed-item[data-item-id]:not([data-item-id=""])').length > 0;
            root.dataset.loadingVisual = hasRows ? 'overlay' : 'skeleton';
            root.classList.toggle('is-feed-updating', hasRows);

            if (hasRows) {
                return;
            }

            stream.innerHTML = '';
            for (let index = 0; index < 3; index += 1) {
                const card = document.createElement('div');
                card.className = 'sb2-feed-loading-card';
                card.setAttribute('aria-hidden', 'true');
                card.innerHTML = '<span class="sb2-feed-loading-line is-wide"></span>'
                    + '<span class="sb2-feed-loading-line"></span>'
                    + '<span class="sb2-feed-loading-chip"></span>';
                stream.appendChild(card);
            }
        };

        window.addEventListener('sportsbook-shell-selection-change', function (event) {
            selectedFeedSelectionKey = String((event && event.detail && event.detail.selection_key) || '');
            Array.from(root.querySelectorAll('[data-bet-feed-add-selection]')).forEach(function (button) {
                resetFeedAddButton(button);
            });
        });

        const scheduleNextPoll = function () {
            if (!pollConfig || !pollConfig.enabled || !pollConfig.items_url) {
                return;
            }

            if (pollTimer) {
                window.clearTimeout(pollTimer);
            }

            pollTimer = window.setTimeout(async function () {
                await pollLatestItems();
                scheduleNextPoll();
            }, Math.max(15000, Number(pollConfig.interval_seconds || 60) * 1000));
        };

        const pollLatestItems = async function () {
            if (document.hidden || !isBetFeedActive() || !pollConfig || !pollConfig.enabled || !pollConfig.items_url) {
                return;
            }

            window.sportsbookShellSetSurfaceState('bet-feed', 'loading');
            renderFeedLoadingState();
            try {
                const endpoint = new URL(String(pollConfig.items_url), window.location.origin);
                endpoint.searchParams.set('limit', '18');
                const response = await window.sportsbookShellRequestJson(endpoint.toString(), {
                    headers: {
                        'Accept': 'application/json',
                    },
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    throw new Error('feed_unavailable');
                }

                const payload = await response.json();
                const items = Array.isArray((((payload || {}).data || {}).items)) ? payload.data.items : [];
                const stats = (((payload || {}).data || {}).stats) || {};
                const state = sportsbookFeedUiState(items, ((payload || {}).meta || {}).freshness);
                window.sportsbookShellSetTabBadgeCount('bet-feed', Number(stats.feedCount || items.length || 0));
                window.sportsbookShellSetSurfaceState('bet-feed', state);
                renderFeedItems(state === 'degraded_stale_source' ? [] : items, { markNew: true, state: state });
                initialBetFeedLoaded = true;
            } catch (error) {
                window.sportsbookShellSetSurfaceState('bet-feed', 'error');
                renderFeedItems([], { state: 'error' });
            }
        };

        const isBetFeedActive = function () {
            const panel = root.closest('[data-tab-panel="bet-feed"]');
            return !!(panel && !panel.hidden && panel.classList.contains('is-active'));
        };

        const hasRenderedBetFeedRows = function () {
            return root.querySelectorAll('[data-bet-feed-stream] .sb2-feed-item[data-item-id]:not([data-item-id=""])').length > 0;
        };

        const ensureBetFeedLoaded = async function () {
            if (initialBetFeedLoaded && hasRenderedBetFeedRows()) {
                return Promise.resolve();
            }

            if (betFeedLoadInFlight) {
                return betFeedLoadPromise || Promise.resolve();
            }

            betFeedLoadInFlight = true;
            betFeedLoadPromise = (async function () {
                try {
                await pollLatestItems();
                } finally {
                    betFeedLoadInFlight = false;
                    betFeedLoadPromise = null;
                }
            })();

            return betFeedLoadPromise;
        };
        window.sportsbookShellEnsureBetFeedLoaded = ensureBetFeedLoaded;
        // Local display expiry only; never sends a request or extends freshness.
        window.setInterval(expireVisibleFeedRows, 1000);

        root.addEventListener('click', async function (event) {
            const share = event.target.closest('[data-role="native-share"]');
            if (share && !share.disabled) {
                event.preventDefault();
                const raw = share.dataset.feedShareUrl || '';
                let url;
                try { url = new URL(raw, window.location.origin); } catch (_) { return; }
                if (!raw || url.origin !== window.location.origin || url.protocol !== window.location.protocol) return;
                const title = share.closest('.sb2-feed-item')?.querySelector('[data-role="event-label"]')?.textContent || 'WinX';
                try {
                    if (typeof navigator.share === 'function') {
                        await navigator.share({title, url: url.href});
                        return;
                    }
                } catch (error) {
                    if (error && error.name === 'AbortError') return;
                }
                try {
                    if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                        await navigator.clipboard.writeText(url.href);
                        share.dataset.shareCopied = '1';
                        return;
                    }
                } catch (_) {}
                window.prompt('Bağlantıyı kopyala / Copy link', url.href);
                return;
            }

            const roomShare = event.target.closest('[data-role="sports-room-share"]');
            if (roomShare && !roomShare.disabled) {
                event.preventDefault();
                const route = roomShare.dataset.feedChatShareRoute || '';
                if (!route || roomShare.dataset.pending === '1') return;
                let url;
                try { url = new URL(route, window.location.origin); } catch (_) { return; }
                if (url.origin !== window.location.origin || url.protocol !== window.location.protocol) return;
                roomShare.dataset.pending = '1';
                roomShare.disabled = true;
                try {
                    const csrf = document.querySelector('meta[name="csrfToken"],meta[name="csrf-token"]')?.content || '';
                    const response = await fetch(url.href, {
                        method: 'POST',
                        headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrf},
                    });
                    const result = await response.json();
                    if (response.ok && ['published', 'already_shared'].includes(result.status)) {
                        roomShare.dataset.shared = '1';
                        roomShare.textContent = t('shared_to_sports', 'Spor odasında paylaşıldı');
                    } else if (response.status === 401) {
                        openLoginFlow();
                    } else {
                        roomShare.dataset.shareError = result.status || 'unavailable';
                    }
                } catch (_) { roomShare.dataset.shareError = 'unavailable'; }
                finally {
                    roomShare.dataset.pending = '0';
                    roomShare.disabled = roomShare.dataset.shared === '1';
                }
                return;
            }

            const summaryButton = event.target.closest('.sb2-feed-summary');
            if (summaryButton) {
                const row = summaryButton.closest('.sb2-feed-item');
                if (!row) {
                    return;
                }

                const expanded = row.classList.toggle('is-expanded');
                summaryButton.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                return;
            }

            const addButton = event.target.closest('[data-bet-feed-add-selection]');
            if (!addButton) {
                return;
            }

            event.preventDefault();
            if (addButton.disabled || addButton.classList.contains('is-disabled')) {
                return;
            }

            const selection = selectionForAddButton(addButton);
            const feedRow = addButton.closest('.sb2-feed-item');
            if (!feedRow || !(Date.parse(feedRow.dataset.expiresAt) > Date.now())) {
                expireFeedRow(feedRow);
                return;
            }
            if (!selection || !selection.event_id || typeof window.sportsbookShellPrefillSelection !== 'function') {
                if (feedSelectionKey(selection) === selectedFeedSelectionKey) {
                    selectedFeedSelectionKey = '';
                }
                addButton.classList.remove('is-pending', 'is-added');
                addButton.classList.add('is-failed');
                setAddButtonLabel(addButton, t('failed', 'Could not add'));
                addFeedbackTimer = window.setTimeout(function () {
                    restoreAddButton(addButton);
                }, 1800);
                return;
            }

            if (addFeedbackTimer) {
                window.clearTimeout(addFeedbackTimer);
            }

            Array.from(root.querySelectorAll('[data-bet-feed-add-selection]')).forEach(function (button) {
                if (button === addButton || button.classList.contains('is-added')) {
                    return;
                }

                button.classList.remove('is-pending', 'is-failed');
                if (!button.disabled && !button.classList.contains('is-disabled')) {
                    setAddButtonLabel(button, button.dataset.defaultLabel || t('add_to_betslip', 'Add to betslip'));
                }
            });

            addButton.classList.remove('is-added', 'is-failed');
            addButton.classList.add('is-pending');
            setAddButtonLabel(addButton, t('adding', 'Adding...'));

            let added = false;

            try {
                added = await window.sportsbookShellPrefillSelection(selection);
            } catch (error) {
                added = false;
            }

            addButton.classList.remove('is-pending');
            if (added) {
                markFeedAddButtonSelected(addButton, selection);
                return;
            }

            if (feedSelectionKey(selection) === selectedFeedSelectionKey) {
                selectedFeedSelectionKey = '';
            }
            addButton.classList.remove('is-added');
            addButton.classList.add('is-failed');
            setAddButtonLabel(addButton, t('failed', 'Could not add'));
            const warning = feedRow.querySelector('[data-role="disabled-reason"]');
            warning.hidden = false;
            warning.textContent = t('add_race_warning', 'Odds changed or the market closed. The betslip could not be updated.');

            addFeedbackTimer = window.setTimeout(function () {
                restoreAddButton(addButton);
            }, 1800);
        });

        if (isBetFeedActive()) {
            ensureBetFeedLoaded();
        }

        document.addEventListener('sportsbook-shell-tab-activated', function (event) {
            if (((event || {}).detail || {}).tab === 'bet-feed') {
                ensureBetFeedLoaded();
            }
        });
        document.querySelectorAll('[data-tab-trigger]').forEach(function (button) {
            button.addEventListener('click', function () {
                const buttonKey = button.dataset.tab || button.dataset.tabTrigger || '';
                if (buttonKey === 'bet-feed') {
                    window.setTimeout(ensureBetFeedLoaded, 0);
                }
            });
        });

        scheduleNextPoll();
    })();

    (function () {
        const shell = document.querySelector('[data-predictions-shell]');
        const content = shell ? shell.querySelector('[data-predictions-content]') : null;
        const grid = shell ? shell.querySelector('[data-predictions-grid]') : null;
        const template = document.getElementById('sb2PredictionCardTemplate');
        const pollConfig = readSportsbookShellJson('sportsbookShellPredictionPolling', null);
        const initialPredictionItems = readSportsbookShellJson('sportsbookShellInitialPredictionItems', []);
        const i18n = readSportsbookShellJson('sportsbookShellI18n', {});
        const seenIds = new Set(
            Array.from(document.querySelectorAll('[data-prediction-card]'))
                .map(function (card) {
                    return String(card.dataset.predictionId || '');
                })
                .filter(Boolean)
        );
        let pollTimer = null;
        let pollInFlight = false;
        let pollPromise = null;
        let pollToken = 0;
        let latestPredictionItems = Array.isArray(initialPredictionItems) ? initialPredictionItems : [];
        let expandedPredictionIds = new Set();
        let predictionsPollScheduled = false;

        if (!shell || !content || !grid || !template) {
            return;
        }

        function t(key, fallback, replacements) {
            let value = Object.prototype.hasOwnProperty.call(i18n, key) ? i18n[key] : fallback;
            Object.entries(replacements || {}).forEach(function ([name, replacement]) {
                value = String(value).split(':' + name).join(String(replacement));
            });
            return value;
        }

        function formatCountLabel(count) {
            const safeCount = Math.max(0, Number(count || 0));
            return safeCount > 1000 ? '1000+' : String(safeCount);
        }

        function normalizeProviderLabel(value) {
            return String(value || '-').replace(/API-Football/g, 'Football');
        }

        function setPredictionCardExpanded(card, expanded) {
            if (!card) {
                return;
            }

            const detail = card.querySelector('[data-role="prediction-detail"]');
            const toggle = card.querySelector('[data-role="prediction-detail-toggle"]');
            const toggleLabel = card.querySelector('[data-role="prediction-detail-toggle-label"]');
            const cardId = String(card.dataset.predictionId || '');
            const nextExpanded = Boolean(expanded);

            card.classList.toggle('is-expanded', nextExpanded);

            if (detail) {
                detail.hidden = !nextExpanded;
            }

            if (toggle) {
                toggle.setAttribute('aria-expanded', nextExpanded ? 'true' : 'false');
            }

            if (toggleLabel) {
                toggleLabel.textContent = nextExpanded ? t('close_detail', 'Detayı kapat') : t('open_detail', 'Detayı aç');
            }

            if (cardId) {
                if (nextExpanded) {
                    expandedPredictionIds.add(cardId);
                } else {
                    expandedPredictionIds.delete(cardId);
                }
            }
        }

        function buildPredictionCard(item) {
            const fragment = template.content.cloneNode(true);
            const card = fragment.querySelector('[data-prediction-card]');

            if (!card) {
                return null;
            }

            card.dataset.predictionId = String(item.id || '');
            card.querySelector('[data-role="prediction-league"]').textContent = item.league_name || t('league_fallback', 'League');
            card.querySelector('[data-role="prediction-fixture"]').textContent = (item.home_team || '-') + ' vs ' + (item.away_team || '-');

            const copy = card.querySelector('[data-role="prediction-copy"]');
            const shortSummary = card.querySelector('[data-role="prediction-short-summary"]');
            const editorNote = card.querySelector('[data-role="prediction-editor-note"]');
            const provider = card.querySelector('[data-role="prediction-provider"]');
            const kickoff = card.querySelector('[data-role="prediction-kickoff"]');
            const predictable = card.querySelector('[data-role="prediction-predictable"]');
            const observed = card.querySelector('[data-role="prediction-observed"]');
            const updated = card.querySelector('[data-role="prediction-updated"]');
            const winnerPick = card.querySelector('[data-role="prediction-winner-pick"]');
            const winnerBreakdown = card.querySelector('[data-role="prediction-winner-breakdown"]');
            const providerSmall = card.querySelector('[data-role="prediction-provider-small"]');
            const drawProbability = card.querySelector('[data-role="prediction-draw-probability"]');
            const totalsPick = card.querySelector('[data-role="prediction-totals-pick"]');
            const totalsBreakdown = card.querySelector('[data-role="prediction-totals-breakdown"]');
            const confidence = card.querySelector('[data-role="prediction-confidence"]');
            const predictableSmall = card.querySelector('[data-role="prediction-predictable-small"]');

            if (provider) {
                provider.textContent = normalizeProviderLabel(item.provider_name);
            }

            if (kickoff) {
                kickoff.textContent = item.kickoff_label || '-';
            }

            if (predictable) {
                predictable.textContent = item.predictable_label || '-';
            }

            if (observed) {
                observed.textContent = t('prediction_observed', 'Observed :value', { value: item.observed_label || '-' });
            }

            if (updated) {
                updated.textContent = t('prediction_updated', 'Updated :value', { value: item.updated_label || '-' });
            }

            if (winnerPick) {
                winnerPick.textContent = item.winner_pick_label || '-';
            }

            if (winnerBreakdown) {
                winnerBreakdown.textContent = item.winner_breakdown_label || '-';
            }

            if (providerSmall) {
                providerSmall.textContent = t('prediction_provider', 'Provider: :value', { value: normalizeProviderLabel(item.provider_name) });
            }

            if (drawProbability) {
                drawProbability.textContent = item.draw_probability_label || '-';
            }

            if (totalsPick) {
                totalsPick.textContent = item.totals_pick_label || '-';
            }

            if (totalsBreakdown) {
                totalsBreakdown.textContent = item.totals_breakdown_label || '-';
            }

            if (confidence) {
                confidence.textContent = item.confidence_label || '-';
            }

            if (predictableSmall) {
                predictableSmall.textContent = item.predictable_label || '-';
            }

            if (shortSummary) {
                shortSummary.textContent = item.short_summary || '';
                shortSummary.hidden = !item.short_summary;
            }

            if (editorNote) {
                editorNote.textContent = item.editor_note || '';
                editorNote.hidden = !item.editor_note;
            }

            if (copy) {
                copy.hidden = !(item.short_summary || item.editor_note);
            }

            setPredictionCardExpanded(card, expandedPredictionIds.has(String(item.id || '')));

            return card;
        }

        function renderItems(items) {
            const records = Array.isArray(items) ? items : [];
            grid.innerHTML = '';

            if (records.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'sb2-feed-empty';
                const state = shell.closest('[data-tab-panel]')?.dataset.predictionsState;
                empty.textContent = state === 'disabled' ? t('predictions_disabled', 'Yakında · Tahminler henüz aktif değil.')
                    : state === 'not_configured' ? t('predictions_not_configured', 'Yakında · Tahmin kaynağı henüz yapılandırılmadı.')
                    : state === 'error' ? t('predictions_load_error', 'Tahminler yüklenemedi. Tekrar deneyin.')
                    : t('prediction_empty', 'Gösterilecek tahmin kaydı bulunamadı.');
                grid.appendChild(empty);
                return;
            }

            records.forEach(function (item) {
                const card = buildPredictionCard(item);
                if (card) {
                    grid.appendChild(card);
                }
            });
        }

        grid.addEventListener('click', function (event) {
            const toggle = event.target.closest('[data-role="prediction-detail-toggle"]');
            if (!toggle) {
                return;
            }

            const card = toggle.closest('[data-prediction-card]');
            if (!card) {
                return;
            }

            setPredictionCardExpanded(card, !card.classList.contains('is-expanded'));
        });

        async function refreshPredictions() {
            if (document.hidden || !isPredictionsActive() || !pollConfig || !pollConfig.enabled || !pollConfig.items_url) {
                return Promise.resolve();
            }

            if (pollInFlight) {
                return pollPromise || Promise.resolve();
            }

            const token = ++pollToken;
            pollInFlight = true;
            window.sportsbookShellSetSurfaceState('predictions', 'loading');

            pollPromise = (async function () {
                try {
                const endpoint = new URL(String(pollConfig.items_url), window.location.origin);
                endpoint.searchParams.set('limit', String(pollConfig.limit || 4));

                const response = await window.sportsbookShellRequestJson(endpoint.toString(), {
                    headers: {
                        'Accept': 'application/json',
                    },
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    throw new Error('predictions_unavailable');
                }

                const payload = await response.json();
                if (token !== pollToken) {
                    return;
                }

                const items = Array.isArray((((payload || {}).data || {}).items)) ? payload.data.items : [];
                latestPredictionItems = items;
                window.sportsbookShellSetTabBadgeCount('predictions', items.length);
                const availability = ((payload || {}).meta || {}).surface_state;
                window.sportsbookShellSetSurfaceState('predictions', ['disabled', 'not_configured'].includes(availability)
                    ? availability : (items.length ? 'ready_items' : 'ready_empty'));

                renderItems(items);
                } catch (error) {
                    window.sportsbookShellSetSurfaceState('predictions', 'error');
                    renderItems([]);
                } finally {
                    pollInFlight = false;
                    pollPromise = null;
                }
            })();

            return pollPromise;
        }

        function schedulePoll() {
            if (!pollConfig || !pollConfig.enabled || !pollConfig.items_url) {
                return;
            }

            predictionsPollScheduled = true;

            if (pollTimer) {
                window.clearTimeout(pollTimer);
            }

            const configuredIntervalMs = Math.max(3600000, Number(pollConfig.interval_seconds || 3600) * 1000);
            const now = new Date();
            const nextHour = new Date(now.getTime());
            nextHour.setHours(now.getHours() + 1, 0, 4, 0);
            const nextDelayMs = configuredIntervalMs >= 3600000
                ? Math.max(30000, nextHour.getTime() - now.getTime())
                : configuredIntervalMs;

            pollTimer = window.setTimeout(async function () {
                await refreshPredictions();
                schedulePoll();
            }, nextDelayMs);
        }

        function isPredictionsActive() {
            const panel = shell.closest('[data-tab-panel="predictions"]');
            return !!(panel && !panel.hidden && panel.classList.contains('is-active'));
        }

        async function ensurePredictionsLoaded() {
            await refreshPredictions();
            if (!predictionsPollScheduled) {
                schedulePoll();
            }
        }

        window.sportsbookShellSetSurfaceState('predictions', latestPredictionItems.length ? 'ready_items'
            : ((pollConfig && pollConfig.surface_state) || (pollConfig && pollConfig.enabled ? 'not_requested' : 'disabled')));
        renderItems(latestPredictionItems);
        if (isPredictionsActive()) {
            ensurePredictionsLoaded();
        }
        document.addEventListener('sportsbook-shell-tab-activated', function (event) {
            if (((event || {}).detail || {}).tab === 'predictions') {
                ensurePredictionsLoaded();
            }
        });
    })();
