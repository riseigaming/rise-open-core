(function () {
    'use strict';

    const selector = '[data-registration-turnstile]';
    const widgets = new WeakMap();

    function renderVisible() {
        if (!window.turnstile) return;
        document.querySelectorAll(selector).forEach(container => {
            if (widgets.has(container) || !container.getClientRects().length) return;
            try {
                const widgetId = window.turnstile.render(container, {
                    sitekey: container.dataset.sitekey,
                    action: 'register',
                    'response-field': true,
                    'expired-callback': () => window.turnstile.reset(widgetId),
                });
                widgets.set(container, widgetId);
            } catch (_) {
                // A hidden or transitioning modal can be rendered on the next focus/click.
            }
        });
    }

    function boot() {
        if (!document.querySelector(selector)) return;

        document.addEventListener('click', () => setTimeout(renderVisible, 0), true);
        document.addEventListener('focusin', renderVisible, true);
        document.addEventListener('submit', event => {
            const container = event.target.querySelector(selector);
            const widgetId = container && widgets.get(container);
            if (widgetId === undefined) return;
            // AJAX forms serialize synchronously; a consumed token must not be reused.
            setTimeout(() => window.turnstile && window.turnstile.reset(widgetId), 500);
        }, true);

        if (window.turnstile) {
            renderVisible();
            return;
        }
        const script = document.createElement('script');
        script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
        script.async = true;
        script.onload = renderVisible;
        document.head.appendChild(script);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, { once: true });
    else boot();
}());
