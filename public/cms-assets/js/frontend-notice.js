(function (window, document) {
    'use strict';

    var MAX_VISIBLE_PER_STACK = 3;
    var DEFAULT_TIMEOUT = 2600;
    var stacks = {};
    var queues = {};
    var MAX_PENDING_PER_STACK = 30;
    var keyedNotices = new Map();
    var order = 0;

    function visibleLimit() { return window.matchMedia('(max-width: 600px)').matches ? 2 : MAX_VISIBLE_PER_STACK; }

    function visibleNotices() {
        return Object.keys(stacks).flatMap(function (position) { return Array.from(stacks[position].children); });
    }

    function drain() {
        while (visibleNotices().length < visibleLimit()) {
            var pendingPositions = Object.keys(queues).filter(function (key) { return queues[key].length; })
                .sort(function (a, b) { return queues[a][0]._cmsOrder - queues[b][0]._cmsOrder; });
            if (!pendingPositions.length) return;
            var position = pendingPositions[0];
            var notice = queues[position].shift();
            stacks[position].appendChild(notice);
            notice._cmsQueued = false;
            notice._cmsRemaining = notice._cmsRemaining == null ? notice._cmsTimeout : notice._cmsRemaining;
            resume(notice);
        }
    }

    function pause(notice) {
        if (notice._cmsFrontendNoticeTimer) {
            window.clearTimeout(notice._cmsFrontendNoticeTimer);
            notice._cmsFrontendNoticeTimer = null;
            notice._cmsRemaining = Math.max(0, notice._cmsRemaining - (Date.now() - notice._cmsStarted));
        }
    }

    function resume(notice) {
        if (!notice.isConnected || !notice._cmsTimeout || document.hidden || notice.matches(':hover, :focus-within')) return;
        pause(notice);
        notice._cmsStarted = Date.now();
        notice._cmsFrontendNoticeTimer = window.setTimeout(function () { dismiss(notice); }, notice._cmsRemaining);
    }
    var labels = Object.assign({
        success: 'Action completed',
        info: 'Information',
        warning: 'Please check',
        error: 'Action could not be completed'
    }, window.CMSFrontNoticeLabels || {});
    var iconPaths = {
        success: '<circle cx="12" cy="12" r="9"/><path d="m7.8 12.2 2.8 2.8 5.8-6"/>',
        info: '<circle cx="12" cy="12" r="9"/><path d="M12 10v5"/><path d="M12 7.2h.01"/>',
        warning: '<path d="M10.1 4.3 3.9 15a2 2 0 0 0 1.73 3h12.74A2 2 0 0 0 20.1 15L13.9 4.3a2.2 2.2 0 0 0-3.8 0Z"/><path d="M12 9v4"/><path d="M12 16h.01"/>',
        error: '<circle cx="12" cy="12" r="9"/><path d="m9 9 6 6m0-6-6 6"/>',
        wallet: '<path d="M4 7.5h14a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-10a2 2 0 0 1 2-2h11"/><path d="M15 13h.01"/>',
        deposit: '<path d="M4 7.5h14a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-10a2 2 0 0 1 2-2h11"/><path d="M12 4v7m-3-3 3 3 3-3"/>',
        withdraw: '<path d="M4 7.5h14a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-10a2 2 0 0 1 2-2h11"/><path d="M12 11V4m-3 3 3-3 3 3"/>',
        support: '<path d="M4 12a8 8 0 0 1 16 0v4a3 3 0 0 1-3 3h-2v-6h5"/><path d="M4 13h5v6H7a3 3 0 0 1-3-3Z"/><path d="M12 20h2"/>',
        message: '<path d="M20 11.5a7.5 7.5 0 0 1-7.5 7.5H7l-3 2v-5.2A7.5 7.5 0 1 1 20 11.5Z"/>',
        bonus: '<rect x="4" y="9" width="16" height="11" rx="2"/><path d="M12 9v11M4 13h16M12 9H8.5A2.5 2.5 0 1 1 11 6.5V9ZM12 9h3.5A2.5 2.5 0 1 0 13 6.5V9Z"/>',
        copy: '<rect x="8" y="8" width="11" height="11" rx="2"/><path d="M5 15V6a2 2 0 0 1 2-2h9"/>',
        network: '<path d="M4 5h16v11H4zM2 19h20M9 9l6 6m0-6-6 6"/>'
    };
    var contextIcons = {
        wallet: 'wallet',
        payment: 'wallet',
        deposit: 'deposit',
        withdraw: 'withdraw',
        payout: 'withdraw',
        support: 'support',
        chat: 'message',
        message: 'message',
        bonus: 'bonus',
        copy: 'copy',
        network: 'network'
    };
    var legacyTypes = ['success', 'info', 'warning', 'error'];

    function normalizeType(type) {
        return legacyTypes.indexOf(type) !== -1 ? type : 'info';
    }

    function normalizePosition(position) {
        position = String(position || 'top-center').replace(/^toast-/, '');
        return ['top-center', 'top-right', 'bottom-right', 'center-center'].indexOf(position) !== -1 ? position : 'top-center';
    }

    function isMetric(value) {
        return typeof value === 'number' && Number.isFinite(value) && value >= 0 && value <= 100;
    }

    function normalizeMetric(metric, metricLabel) {
        var isObject = metric && typeof metric === 'object' && !Array.isArray(metric);
        var value = isObject ? metric.value : metric;

        if (!isMetric(value)) {
            return null;
        }

        var label = isObject ? metric.label : metricLabel;
        var rounded = String(Math.round(value));

        return {
            value: value,
            label: label === '%' ? rounded + '%' : (label || rounded),
            tone: isObject ? String(metric.tone || '') : ''
        };
    }

    function normalizeContextIcon(options, rawType, type) {
        var requested = String(options.icon || options.context || rawType || '').toLowerCase();
        return contextIcons[requested] || type;
    }

    function isLegacyType(value) {
        return typeof value === 'string' && legacyTypes.indexOf(value.toLowerCase()) !== -1;
    }

    function isStatusCode(value) {
        var status = typeof value === 'number' ? value : Number(value);
        return Number.isInteger(status) && status >= 100 && status <= 599;
    }

    function getStack(position) {
        position = normalizePosition(position);
        if (stacks[position]) {
            return stacks[position];
        }

        var stack = document.createElement('div');
        stack.className = 'cms-frontend-notice-stack is-' + position;
        stack.setAttribute('aria-label', 'Uygulama bildirimleri');
        queues[position] = [];
        document.body.appendChild(stack);
        stacks[position] = stack;
        return stack;
    }

    function makeIcon(name) {
        var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('viewBox', '0 0 24 24');
        svg.setAttribute('aria-hidden', 'true');
        svg.innerHTML = iconPaths[name] || iconPaths.info;
        return svg;
    }

    function dismiss(notice, immediate) {
        if (!notice) {
            return;
        }
        if (notice._cmsDismissing && !immediate) return;

        pause(notice);
        var position = notice._cmsPosition;
        if (notice._cmsKey && keyedNotices.get(notice._cmsKey) === notice) keyedNotices.delete(notice._cmsKey);
        if (queues[position]) queues[position] = queues[position].filter(function (item) { return item !== notice; });
        notice._cmsQueued = false;
        var remove = function () {
            if (notice.parentNode) notice.parentNode.removeChild(notice);
            if (position) drain(position);
        };
        if (notice.isConnected && !immediate && !document.hidden
            && !window.matchMedia('(prefers-reduced-motion: reduce)').matches && notice.animate) {
            notice._cmsDismissing = true;
            notice.animate([{ opacity: 1 }, { opacity: 0 }], { duration: 120, fill: 'forwards' }).finished.then(remove, remove);
        } else remove();
    }

    function makeDismissButton() {
        var button = document.createElement('button');
        button.className = 'cms-frontend-notice__dismiss';
        button.type = 'button';
        button.setAttribute('aria-label', 'Bildirimi kapat');
        button.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 7 10 10M17 7 7 17"/></svg>';
        return button;
    }

    function show(options) {
        options = options || {};
        var rawType = String(options.type || '').toLowerCase();
        var type = normalizeType(rawType);
        var position = normalizePosition(options.position);
        var stack = getStack(position);
        var persistent = options.persistent === true;
        var metricData = normalizeMetric(options.metric, options.metricLabel);
        var hasMetric = metricData !== null;
        var iconName = normalizeContextIcon(options, rawType, type);
        var notice = document.createElement('article');
        var title = String(options.title || labels[type]);
        var message = String(options.message || '');
        var key = String(options.id || [position, type, title, message].join('|'));
        if (keyedNotices.has(key)) return keyedNotices.get(key);
        notice._cmsKey = key;
        notice._cmsOrder = ++order;
        notice._cmsPosition = position;
        notice._cmsTimeout = persistent || options.timeout === 0 ? 0 :
            (Number.isFinite(options.timeout) ? Math.max(0, options.timeout) : DEFAULT_TIMEOUT);
        notice._cmsQueued = true;
        keyedNotices.set(key, notice);

        notice.className = 'cms-frontend-notice is-' + type + (hasMetric ? ' has-gauge' : '') + (persistent ? ' cms-frontend-notice--persistent' : '');
        notice.setAttribute('data-icon', iconName);
        notice.setAttribute('role', type === 'warning' || type === 'error' ? 'alert' : 'status');
        notice.setAttribute('aria-live', type === 'warning' || type === 'error' ? 'assertive' : 'polite');

        var icon = document.createElement('span');
        icon.className = 'cms-frontend-notice__icon';
        icon.appendChild(makeIcon(iconName));
        notice.appendChild(icon);

        var copy = document.createElement('div');
        copy.className = 'cms-frontend-notice__copy';
        var titleNode = document.createElement('strong');
        titleNode.className = 'cms-frontend-notice__title';
        titleNode.textContent = title;
        copy.appendChild(titleNode);
        if (message) {
            var messageNode = document.createElement('span');
            messageNode.className = 'cms-frontend-notice__message';
            messageNode.textContent = message;
            copy.appendChild(messageNode);
        }
        notice.appendChild(copy);

        if (hasMetric) {
            var gauge = document.createElement('span');
            gauge.className = 'cms-frontend-notice__gauge';
            gauge.style.setProperty('--cms-frontend-notice-metric', String(metricData.value));
            gauge.setAttribute('aria-label', String(Math.round(metricData.value)) + '%');
            if (metricData.tone) {
                gauge.setAttribute('data-tone', metricData.tone);
            }
            var metric = document.createElement('span');
            metric.className = 'cms-frontend-notice__metric';
            metric.textContent = metricData.label;
            gauge.appendChild(metric);
            notice.appendChild(gauge);
        }

        if (options.action && options.action.label && typeof options.action.onClick === 'function') {
            var action = document.createElement('button');
            action.className = 'cms-frontend-notice__action';
            action.type = 'button';
            action.textContent = String(options.action.label);
            action.addEventListener('click', options.action.onClick);
            notice.appendChild(action);
        }

        if (!persistent && options.dismissible !== false) {
            var dismissButton = makeDismissButton();
            dismissButton.addEventListener('click', function () {
                dismiss(notice);
            });
            notice.appendChild(dismissButton);
        }

        // Bursts wait FIFO; never evict a visible (especially persistent) notice.
        // Backlog is bounded. Durable customer history remains in the bell/history.
        if (queues[position].length >= MAX_PENDING_PER_STACK) {
            var dropped = queues[position].shift();
            keyedNotices.delete(dropped._cmsKey);
            dropped._cmsQueued = false;
        }
        queues[position].push(notice);
        notice.addEventListener('mouseenter', function () { pause(notice); });
        notice.addEventListener('mouseleave', function () { resume(notice); });
        notice.addEventListener('focusin', function () { pause(notice); });
        notice.addEventListener('focusout', function () { window.setTimeout(function () { resume(notice); }, 0); });
        drain(position);

        return notice;
    }

    function adapt(type, message, title, timeout, position, context) {
        return show({
            type: type,
            title: title || labels[type],
            message: message,
            timeout: typeof timeout === 'number' ? timeout : DEFAULT_TIMEOUT,
            position: position || 'top-center',
            context: context || ''
        });
    }

    function typeForStatus(statusCode) {
        if (isLegacyType(statusCode)) {
            return String(statusCode).toLowerCase();
        }

        var status = Number(statusCode);
        if (status >= 200 && status < 300) {
            return 'success';
        }
        if (status >= 100 && status < 200) {
            return 'info';
        }
        if (status >= 500) {
            return 'error';
        }
        return 'warning';
    }

    function normalizeLegacyArguments(first, second) {
        if (isLegacyType(first) || isStatusCode(first)) {
            return {
                type: typeForStatus(first),
                message: second
            };
        }

        if (isLegacyType(second) || isStatusCode(second)) {
            return {
                type: typeForStatus(second),
                message: first
            };
        }

        return {
            type: 'info',
            message: first
        };
    }

    window.CMSFrontendNotice = {
        show: show,
        dismiss: dismiss,
        clear: function () {
            keyedNotices.clear();
            Object.keys(queues).forEach(function (position) { queues[position] = []; });
            Object.keys(stacks).forEach(function (position) {
                while (stacks[position].firstElementChild) {
                    dismiss(stacks[position].firstElementChild, true);
                }
            });
        },
        maxVisible: MAX_VISIBLE_PER_STACK,
        maxPending: MAX_PENDING_PER_STACK
    };
    window.CMSFrontNotice = window.CMSFrontendNotice;
    window.WINXNotice = window.CMSFrontendNotice;

    document.addEventListener('visibilitychange', function () {
        Object.keys(stacks).forEach(function (position) {
            Array.from(stacks[position].children).forEach(function (notice) {
                if (document.hidden) pause(notice); else resume(notice);
            });
        });
    });
    window.addEventListener('resize', function () {
        while (visibleNotices().length > visibleLimit()) {
            var notice = visibleNotices().sort(function (a, b) { return b._cmsOrder - a._cmsOrder; })[0];
            pause(notice);
            notice.parentNode.removeChild(notice);
            notice._cmsQueued = true;
            queues[notice._cmsPosition].unshift(notice);
        }
        drain();
    });

    // These legacy names are intentionally overridden only by frontend layouts.
    window.successToast = function (message, title, timeout, position) {
        return adapt('success', message, title, timeout, position);
    };
    window.infoToast = function (message, title, timeout, position) {
        return adapt('info', message, title, timeout, position);
    };
    window.warningToast = function (message, title, timeout, position) {
        return adapt('warning', message, title, timeout, position);
    };
    window.errorToast = function (message, title, timeout, position) {
        return adapt('error', message, title, timeout, position);
    };
    window.toastMessage = function (first, second) {
        var normalized = normalizeLegacyArguments(first, second);
        return adapt(normalized.type, normalized.message, labels[normalized.type], DEFAULT_TIMEOUT, 'top-center');
    };
    window.showToastMessage = function (first, second) {
        var normalized = normalizeLegacyArguments(first, second);
        return adapt(normalized.type, normalized.message, labels[normalized.type], DEFAULT_TIMEOUT, 'top-right');
    };
})(window, document);
