<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('cms.global.partials.vendor-runtime')
    @include('cms.partials.customer-placeholder-style')
    @php
        $siteSettingTranslation = optional($siteSetting)->translation;
        $siteTitle = trim((string) (optional($siteSettingTranslation)->site_title ?: 'Global CMS'));
        $siteDescription = trim((string) (optional($siteSettingTranslation)->site_description ?: 'Content and account management'));
        $globalThemeCssPath = 'cms-assets/themes/global/global.css';
        $globalThemeCssVersion = file_exists(public_path($globalThemeCssPath)) ? filemtime(public_path($globalThemeCssPath)) : 1;
    @endphp
    @auth('cms')
        @include('cms.global.partials.frontend-notices')
    @endauth
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0b1017">
    <meta name="csrfToken" content="{{ csrf_token() }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $contentPageTitle ?? $learnSeoTitle ?? $siteTitle }}</title>
    <meta name="description" content="{{ $learnSeoDescription ?? $siteDescription }}">
    @stack('early_head')
    <link rel="stylesheet" href="{{ asset($globalThemeCssPath) }}?v={{ $globalThemeCssVersion }}">
    <link rel="stylesheet" href="{{ asset('cms-assets/themes/global/journeys.css') }}?v={{ filemtime(public_path('cms-assets/themes/global/journeys.css')) }}">
    <script defer src="{{ asset('cms-assets/themes/global/journeys.js') }}"></script>
    @stack('head')
</head>
<body class="globalThemeShell" data-global-accent="{{ \App\Models\CmsThemeSetting::normalizeAccentPreset(optional($siteSetting)->accent_preset) }}">
<div class="globalAppFrame" data-global-shell>
    @include('cms.global.partials.layout.menu')

    <main class="globalMain" id="global-main">
        @yield('pageContent')
    </main>

    @include('cms.global.partials.layout.footer')
</div>

@include('cms.global.partials.layout.auth-sheet')
@include('cms.global.partials.layout.account-sheet')
@include('cms.global.partials.layout.utility-sheets')
<script>
    (() => {
        const shell = document.querySelector('[data-global-shell]');
        const toggle = document.querySelector('[data-global-rail-toggle]');

        if (shell && toggle) {
            const railMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
            let railTransitionTimer = null;
            const finishRailTransition = () => {
                window.clearTimeout(railTransitionTimer);
                railTransitionTimer = null;
                shell.classList.remove('is-rail-transitioning');
            };

            toggle.addEventListener('click', () => {
                const collapsed = shell.classList.toggle('is-rail-collapsed');
                shell.dataset.globalRailState = collapsed ? 'collapsed' : 'expanded';
                toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                toggle.setAttribute('aria-label', collapsed ? @json(trans('global.expand_navigation')) : @json(trans('global.collapse_navigation')));
                shell.classList.add('is-rail-transitioning');
                finishRailTransition();

                if (!railMotion.matches) {
                    shell.classList.add('is-rail-transitioning');
                    railTransitionTimer = window.setTimeout(finishRailTransition, 260);
                }
            });
        }

        const authSheet = document.querySelector('[data-global-auth-sheet]');
        const accountSheet = document.querySelector('[data-global-account-sheet]');
        const menuSheet = document.querySelector('[data-global-menu-sheet]');
        const confirmationSheet = document.querySelector('[data-global-confirmation-sheet]');
        const campaignSheet = document.querySelector('[data-global-campaign-sheet]');
        const languagePopover = document.querySelector('[data-global-language-popover]');
        const sheetLayers = [authSheet, accountSheet, menuSheet, confirmationSheet, campaignSheet].filter(Boolean);
        const popoverLayers = [languagePopover].filter(Boolean);
        const namedSheets = {
            menu: menuSheet,
            confirmation: confirmationSheet,
            campaign: campaignSheet,
        };
        const namedPopovers = {
            language: languagePopover,
        };
        let lastSheetTrigger = null;
        let lastPopoverTrigger = null;
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
        const closingTimers = new WeakMap();

        const focusableSelector = [
            'a[href]',
            'button:not([disabled])',
            'input:not([disabled]):not([type="hidden"])',
            'select:not([disabled])',
            'textarea:not([disabled])',
            '[tabindex]:not([tabindex="-1"])',
        ].join(',');
        const getFocusableElements = (layer) => [...layer.querySelectorAll(focusableSelector)]
            .filter((element) => !element.closest('[hidden]'));

        const focusLayerEntry = (layer) => {
            const target = layer.querySelector('[data-global-sheet-initial-focus]') || getFocusableElements(layer)[0];
            if (window.matchMedia('(max-width: 1024px)').matches && target?.matches('input, textarea, select, [contenteditable]')) {
                (layer.querySelector('[role="dialog"]') || layer.querySelector('[data-global-sheet-close]'))?.focus({ preventScroll: true });
                return;
            }
            target?.focus({ preventScroll: true });
        };

        const setControlledExpanded = (layer, expanded) => {
            if (!layer.id) {
                return;
            }

            document.querySelectorAll(`[aria-controls="${layer.id}"]`).forEach((trigger) => {
                trigger.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            });
        };

        const finishClosingLayer = (layer, layers, bodyClass, restoreTrigger = null) => {
            window.clearTimeout(closingTimers.get(layer));
            closingTimers.delete(layer);
            layer.setAttribute('hidden', '');
            layer.classList.remove('is-global-closing');
            layer.dataset.globalMotionState = 'closed';

            if (layers.every((entry) => entry.hidden)) {
                document.body.classList.remove(bodyClass);
            }

            if (restoreTrigger instanceof HTMLElement && restoreTrigger.isConnected) {
                restoreTrigger.focus();
            }
        };

        const closeLayer = (layer, layers, bodyClass, { restoreTrigger = null, immediate = false } = {}) => {
            if (!layer || layer.hidden) {
                return false;
            }

            setControlledExpanded(layer, false);

            if (immediate || reducedMotion.matches) {
                finishClosingLayer(layer, layers, bodyClass, restoreTrigger);
                return true;
            }

            layer.classList.add('is-global-closing');
            layer.dataset.globalMotionState = 'closing';

            const motionTarget = layer.querySelector('.globalSheetPanel, .globalPopoverPanel') || layer;
            let completed = false;
            const complete = () => {
                if (completed) {
                    return;
                }

                completed = true;
                motionTarget.removeEventListener('animationend', complete);
                motionTarget.removeEventListener('transitionend', complete);
                finishClosingLayer(layer, layers, bodyClass, restoreTrigger);
            };

            window.clearTimeout(closingTimers.get(layer));
            motionTarget.addEventListener('animationend', complete);
            motionTarget.addEventListener('transitionend', complete);
            closingTimers.set(layer, window.setTimeout(complete, 320));

            return true;
        };

        const prepareLayerForOpen = (layer, layers, bodyClass) => {
            window.clearTimeout(closingTimers.get(layer));
            closingTimers.delete(layer);
            layer.hidden = false;
            layer.classList.remove('is-global-closing');
            layer.dataset.globalMotionState = 'open';
            document.body.classList.add(bodyClass);
            setControlledExpanded(layer, true);
        };

        const setAuthMode = (mode) => {
            if (!authSheet) {
                return;
            }

            const safeMode = authSheet.querySelector(`[data-global-auth-panel="${mode}"]`) ? mode : 'login';
            authSheet.dataset.initialMode = safeMode;
            authSheet.querySelectorAll('[data-global-auth-panel]').forEach((panel) => {
                panel.hidden = panel.dataset.globalAuthPanel !== safeMode;
            });
            authSheet.querySelectorAll('[data-global-auth-mode]').forEach((button) => {
                const active = button.dataset.globalAuthMode === safeMode;
                button.classList.toggle('is-active', active);
                button.setAttribute('aria-selected', active ? 'true' : 'false');
                button.setAttribute('tabindex', active ? '0' : '-1');
            });
        };

        const closeSheets = ({ restoreFocus = true, immediate = false } = {}) => {
            const hadOpenSheet = sheetLayers.some((layer) => !layer.hidden);
            let restored = false;
            sheetLayers.forEach((layer) => {
                const restoreTrigger = !restored && restoreFocus && hadOpenSheet ? lastSheetTrigger : null;
                const closed = closeLayer(layer, sheetLayers, 'has-global-sheet', { restoreTrigger, immediate });
                if (closed && restoreTrigger) {
                    restored = true;
                }
            });
            if (hadOpenSheet) {
                lastSheetTrigger = null;
            }
        };

        const closePopovers = ({ restoreFocus = true, immediate = false } = {}) => {
            const hadOpenPopover = popoverLayers.some((layer) => !layer.hidden);
            let restored = false;
            popoverLayers.forEach((layer) => {
                const restoreTrigger = !restored && restoreFocus && hadOpenPopover ? lastPopoverTrigger : null;
                const closed = closeLayer(layer, popoverLayers, 'has-global-popover', { restoreTrigger, immediate });
                if (closed && restoreTrigger) {
                    restored = true;
                }
            });
            if (hadOpenPopover) {
                lastPopoverTrigger = null;
            }
        };

        const openSheet = (layer, trigger = document.activeElement) => {
            if (!layer) {
                return;
            }
            window.dispatchEvent(new Event('global:sheet-opening'));

            const restoreTarget = trigger instanceof HTMLElement && trigger.closest('[data-global-menu-sheet], [data-global-account-sheet], [data-global-auth-sheet], [data-global-confirmation-sheet], [data-global-campaign-sheet]')
                ? lastSheetTrigger
                : trigger;
            closeSheets({ restoreFocus: false, immediate: true });
            closePopovers({ restoreFocus: false, immediate: true });
            lastSheetTrigger = restoreTarget instanceof HTMLElement ? restoreTarget : null;
            prepareLayerForOpen(layer, sheetLayers, 'has-global-sheet');
            focusLayerEntry(layer);
            window.requestAnimationFrame(() => focusLayerEntry(layer));
        };

        const openPopover = (layer, trigger = document.activeElement) => {
            if (!layer) {
                return;
            }

            closeSheets({ restoreFocus: false, immediate: true });
            closePopovers({ restoreFocus: false, immediate: true });
            lastPopoverTrigger = trigger instanceof HTMLElement ? trigger : null;
            prepareLayerForOpen(layer, popoverLayers, 'has-global-popover');
            focusLayerEntry(layer);
            window.requestAnimationFrame(() => focusLayerEntry(layer));
        };

        document.querySelectorAll('[data-global-auth-open]').forEach((entry) => {
            entry.addEventListener('click', (event) => {
                if (!authSheet || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                    return;
                }
                event.preventDefault();
                setAuthMode(entry.dataset.globalAuthOpen || 'login');
                openSheet(authSheet, entry);
            });
        });
        // Profile cards arrive from the existing read endpoint after page boot.
        // Their auth action targets the Global sheet, never a missing WinX modal.
        document.addEventListener('click', event => {
            const entry = event.target.closest('[data-preview-auth-modal]');
            if (!entry) return;
            event.preventDefault();
            event.stopImmediatePropagation();
            setAuthMode(entry.dataset.previewAuthModal || 'login');
            openSheet(authSheet, entry);
        }, true);

        document.querySelectorAll('[data-global-account-open]').forEach((entry) => {
            entry.addEventListener('click', (event) => {
                if (!accountSheet || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                    return;
                }
                event.preventDefault();
                openSheet(accountSheet, entry);
            });
        });

        document.querySelectorAll('[data-global-sheet-open]').forEach((entry) => {
            entry.addEventListener('click', (event) => {
                const layer = namedSheets[entry.dataset.globalSheetOpen];
                if (!layer || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                    return;
                }
                event.preventDefault();
                openSheet(layer, entry);
                layer.dispatchEvent(new Event('global:sheet-open'));
            });
        });

        document.querySelectorAll('[data-global-popover-open]').forEach((entry) => {
            entry.addEventListener('click', (event) => {
                const layer = namedPopovers[entry.dataset.globalPopoverOpen];
                if (!layer || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                    return;
                }
                event.preventDefault();
                openPopover(layer, entry);
            });
        });

        document.addEventListener('click', event => {
            if (event.target.closest('[data-navbar-timed-promotion-trigger]')) {
                closeSheets({ restoreFocus: false, immediate: true });
                closePopovers({ restoreFocus: false, immediate: true });
            }
        }, true);

        document.querySelectorAll('[data-global-auth-mode]').forEach((button) => {
            button.addEventListener('click', () => setAuthMode(button.dataset.globalAuthMode || 'login'));
        });

        document.querySelectorAll('[data-global-sheet-close]').forEach((button) => {
            button.addEventListener('click', closeSheets);
        });

        document.querySelectorAll('[data-global-popover-close]').forEach((button) => {
            button.addEventListener('click', closePopovers);
        });

        document.querySelectorAll('[data-global-notice-dismiss]').forEach((button) => {
            button.addEventListener('click', () => {
                const notice = button.closest('[data-global-dismissible-notice]');
                if (notice) {
                    notice.hidden = true;
                }
            });
        });

        document.addEventListener('keydown', (event) => {
            const openPopoverLayer = popoverLayers.find((layer) => !layer.hidden);
            if (openPopoverLayer && event.key === 'Escape') {
                event.preventDefault();
                closePopovers();
                return;
            }

            const openSheetLayer = sheetLayers.find((layer) => !layer.hidden);
            if (!openSheetLayer) {
                return;
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                closeSheets();
                return;
            }

            if (event.key === 'Tab') {
                const focusable = getFocusableElements(openSheetLayer);
                if (!focusable.length) {
                    return;
                }
                const first = focusable[0];
                const last = focusable[focusable.length - 1];
                if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }
            }
        });

        document.querySelectorAll('[data-global-fullscreen-toggle]').forEach((fullscreenToggle) => {
            fullscreenToggle.addEventListener('click', async () => {
                try {
                    if (document.fullscreenElement) {
                        await document.exitFullscreen();
                    } else if (document.documentElement.requestFullscreen) {
                        await document.documentElement.requestFullscreen();
                    }
                } catch (error) {
                    fullscreenToggle.dataset.fullscreenUnavailable = 'true';
                }
            });
        });

        document.querySelectorAll('[data-global-hero-slider]').forEach((slider) => {
            const slides = [...slider.querySelectorAll('[data-global-slide]')];
            const status = slider.querySelector('[data-global-slider-status]');
            let activeIndex = Math.max(0, slides.findIndex((slide) => !slide.hidden));
            const showSlide = (nextIndex) => {
                if (slides.length < 2) {
                    return;
                }
                activeIndex = (nextIndex + slides.length) % slides.length;
                slides.forEach((slide, index) => {
                    const active = index === activeIndex;
                    slide.hidden = !active;
                    slide.classList.toggle('is-active', active);
                });
                if (status) {
                    status.textContent = `${activeIndex + 1} / ${slides.length}`;
                }
            };
            slider.querySelector('[data-global-slider-previous]')?.addEventListener('click', () => showSlide(activeIndex - 1));
            slider.querySelector('[data-global-slider-next]')?.addEventListener('click', () => showSlide(activeIndex + 1));
        });

        document.querySelectorAll('[data-global-register-form]').forEach((form) => {
            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                const feedback = form.querySelector('[data-global-register-feedback]');
                const submit = form.querySelector('button[type="submit"]');
                submit?.setAttribute('disabled', 'disabled');
                if (feedback) {
                    feedback.hidden = true;
                    feedback.textContent = '';
                }

                try {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        body: new FormData(form),
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });
                    const payload = await response.json();
                    if (response.ok && Number(payload.status) === 200 && payload.redirectUrl) {
                        window.location.assign(payload.redirectUrl);
                        return;
                    }
                    if (feedback) {
                        feedback.textContent = payload.message || 'Registration could not be completed.';
                        feedback.hidden = false;
                    }
                } catch (error) {
                    if (feedback) {
                        feedback.textContent = 'Registration could not be completed. Please try again.';
                        feedback.hidden = false;
                    }
                } finally {
                    submit?.removeAttribute('disabled');
                }
            });
        });

        if (authSheet && !authSheet.hidden) {
            setAuthMode(authSheet.dataset.initialMode || 'login');
            openSheet(authSheet, null);
        } else {
            setAuthMode(authSheet?.dataset.initialMode || 'login');
        }
    })();
</script>
@stack('js')
</body>
</html>
