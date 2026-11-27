(function (window, document) {
    'use strict';

    var MAX_VISIBLE_PER_STACK = 4;
    var DEFAULT_TIMEOUT = 2600;
    var stacks = {};
    var iconPaths = {
        success: '<path d="m5 12 4.2 4.2L19 6.5"/><circle cx="12" cy="12" r="9"/>',
        info: '<circle cx="12" cy="12" r="9"/><path d="M12 10v6"/><path d="M12 7h.01"/>',
        warning: '<path d="M10.1 4.2 3.9 15a2 2 0 0 0 1.73 3h12.74A2 2 0 0 0 20.1 15L13.9 4.2a2 2 0 0 0-3.8 0Z"/><path d="M12 9v4"/><path d="M12 16h.01"/>',
        error: '<circle cx="12" cy="12" r="9"/><path d="m9 9 6 6"/><path d="m15 9-6 6"/>',
        wallet: '<path d="M4 7.5h14a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-10a2 2 0 0 1 2-2h11"/><path d="M15 13h.01"/>',
        deposit: '<path d="M4 7.5h14a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-10a2 2 0 0 1 2-2h11"/><path d="M12 4v7"/><path d="m9 8 3 3 3-3"/>',
        withdraw: '<path d="M4 7.5h14a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-10a2 2 0 0 1 2-2h11"/><path d="M12 11V4"/><path d="m9 7 3-3 3 3"/>',
        support: '<path d="M4 12a8 8 0 0 1 16 0v4a3 3 0 0 1-3 3h-2v-6h5"/><path d="M4 13h5v6H7a3 3 0 0 1-3-3Z"/><path d="M12 20h2"/>',
        message: '<path d="M20 11.5a7.5 7.5 0 0 1-7.5 7.5H7l-3 2v-5.2A7.5 7.5 0 1 1 20 11.5Z"/>',
        bonus: '<rect x="4" y="9" width="16" height="11" rx="2"/><path d="M12 9v11"/><path d="M4 13h16"/><path d="M12 9H8.5A2.5 2.5 0 1 1 11 6.5V9Z"/><path d="M12 9h3.5A2.5 2.5 0 1 0 13 6.5V9Z"/>',
        backup: '<ellipse cx="12" cy="5" rx="7" ry="3"/><path d="M5 5v7c0 1.65 3.13 3 7 3s7-1.35 7-3V5"/><path d="M5 12v7c0 1.65 3.13 3 7 3s7-1.35 7-3v-7"/>',
        network: '<path d="M4 5h16v11H4z"/><path d="M2 19h20"/><path d="m9 9 6 6"/><path d="m15 9-6 6"/>',
        navigation: '<path d="M5 19 19 5"/><path d="M9 5h10v10"/>'
    };
    var contextIcons = {
        deposit: 'deposit',
        withdraw: 'withdraw',
        payout: 'withdraw',
        wallet: 'wallet',
        support: 'support',
        message: 'message',
        bonus: 'bonus',
        backup: 'backup',
        network: 'network',
        navigation: 'navigation'
    };
    var labels = {
        success: 'Başarılı',
        info: 'Bilgilendirme',
        warning: 'Uyarı',
        error: 'İşlem yapılamadı'
    };

    function normalizeType(type) {
        return ['success', 'info', 'warning', 'error'].indexOf(type) !== -1 ? type : 'info';
    }

    function normalizePosition(position) {
        var allowed = ['top-center', 'bottom-right', 'center-center'];
        return allowed.indexOf(position) !== -1 ? position : 'top-center';
    }

    function getStack(position) {
        position = normalizePosition(position);
        if (stacks[position]) {
            return stacks[position];
        }

        var stack = document.createElement('div');
        stack.className = 'cms-admin-notice-stack is-' + position;
        stack.setAttribute('aria-label', 'Uygulama bildirimleri');
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

    function closeButton() {
        var button = document.createElement('button');
        button.className = 'cms-admin-notice__dismiss';
        button.type = 'button';
        button.setAttribute('aria-label', 'Bildirimi kapat');
        button.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 7 10 10M17 7 7 17"/></svg>';
        return button;
    }

    function isMetric(value) {
        return typeof value === 'number' && Number.isFinite(value) && value >= 0 && value <= 100;
    }

    function removeNotice(notice) {
        if (!notice || !notice.parentNode) {
            return;
        }
        if (notice._cmsAdminNoticeTimer) {
            window.clearTimeout(notice._cmsAdminNoticeTimer);
        }
        notice.parentNode.removeChild(notice);
    }

    function show(options) {
        options = options || {};
        var type = normalizeType(options.type);
        var position = normalizePosition(options.position);
        var stack = getStack(position);
        var persistent = options.persistent === true;
        var context = String(options.context || '').toLowerCase();
        var iconName = contextIcons[context] || type;
        var hasMetric = isMetric(options.metric);
        var notice = document.createElement('article');
        var title = String(options.title || labels[type]);
        var message = String(options.message || '');

        notice.className = 'cms-admin-notice is-' + type + (hasMetric ? ' has-gauge' : '') + (persistent ? ' cms-admin-notice--persistent' : '');
        notice.setAttribute('role', type === 'warning' || type === 'error' ? 'alert' : 'status');
        notice.setAttribute('aria-live', type === 'warning' || type === 'error' ? 'assertive' : 'polite');

        var icon = document.createElement('span');
        icon.className = 'cms-admin-notice__icon';
        icon.appendChild(makeIcon(iconName));
        notice.appendChild(icon);

        var copy = document.createElement('div');
        copy.className = 'cms-admin-notice__copy';
        var titleNode = document.createElement('strong');
        titleNode.className = 'cms-admin-notice__title';
        titleNode.textContent = title;
        copy.appendChild(titleNode);
        if (message) {
            var messageNode = document.createElement('span');
            messageNode.className = 'cms-admin-notice__message';
            messageNode.textContent = message;
            copy.appendChild(messageNode);
        }
        notice.appendChild(copy);

        if (hasMetric) {
            var gauge = document.createElement('span');
            gauge.className = 'cms-admin-notice__gauge';
            gauge.style.setProperty('--cms-admin-notice-metric', String(options.metric));
            gauge.setAttribute('aria-label', String(Math.round(options.metric)) + '%');
            var metric = document.createElement('span');
            metric.className = 'cms-admin-notice__metric';
            metric.textContent = options.metricLabel || String(Math.round(options.metric));
            gauge.appendChild(metric);
            notice.appendChild(gauge);
        }

        if (options.action && options.action.label && typeof options.action.onClick === 'function') {
            var action = document.createElement('button');
            action.className = 'cms-admin-notice__action';
            action.type = 'button';
            action.textContent = String(options.action.label);
            action.addEventListener('click', options.action.onClick);
            notice.appendChild(action);
        }

        if (!persistent && options.dismissible !== false) {
            var dismiss = closeButton();
            dismiss.addEventListener('click', function () {
                removeNotice(notice);
            });
            notice.appendChild(dismiss);
        }

        if (!persistent) {
            while (stack.children.length >= MAX_VISIBLE_PER_STACK) {
                removeNotice(stack.firstElementChild);
            }
        }
        stack.appendChild(notice);

        if (!persistent && options.timeout !== 0) {
            var timeout = Number.isFinite(options.timeout) ? Math.max(0, options.timeout) : DEFAULT_TIMEOUT;
            if (timeout > 0) {
                notice._cmsAdminNoticeTimer = window.setTimeout(function () {
                    removeNotice(notice);
                }, timeout);
            }
        }

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

    window.CMSAdminNotice = {
        show: show,
        dismiss: removeNotice,
        maxVisible: MAX_VISIBLE_PER_STACK
    };

    // These names intentionally mirror common.js only inside admin layouts.
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
    window.toastMessage = function (statusCode, message) {
        var status = Number(statusCode);
        var type = status >= 200 && status < 300 ? 'success' : (status >= 100 && status < 200 ? 'info' : (status >= 400 ? 'warning' : 'warning'));
        return adapt(type, message, labels[type], DEFAULT_TIMEOUT, 'top-center');
    };
})(window, document);
