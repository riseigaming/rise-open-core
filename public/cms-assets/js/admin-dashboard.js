(function (root) {
    'use strict';
    const defaultCopy = {loading_detail:'Ayrıntı yükleniyor…', auth_error:'Oturum veya erişim doğrulanamadı.', detail_error:'Ayrıntı yüklenemedi.'};
    function initializeFilters(doc, suppliedCopy) {
        const copy = Object.assign({}, defaultCopy, suppliedCopy || {});
        const range = doc.querySelector('[data-date-range]');
        const dates = doc.querySelector('[data-custom-dates]');
        if (range && dates) {
            const update = () => {
                const custom = range.value === 'custom';
                dates.hidden = !custom;
                dates.querySelectorAll('input').forEach(input => { input.disabled = !custom; input.required = custom; });
            };
            range.addEventListener('change', update);
            update();
        }
        const popover = doc.querySelector('.dashboard-filter-popover');
        doc.addEventListener('keydown', event => { if (event.key === 'Escape' && popover && popover.open) { popover.open = false; popover.querySelector('summary').focus(); } });
    }
    function initializeDetails(doc, suppliedCopy) {
        const copy = Object.assign({}, defaultCopy, suppliedCopy || {});
        const dialog = doc.querySelector('#dashboard-detail-dialog');
        if (!dialog || !dialog.showModal) return;
        const win = doc.defaultView;
        const body = dialog.querySelector('[data-detail-body]');
        const status = dialog.querySelector('[data-detail-status]');
        const error = dialog.querySelector('[data-detail-error]');
        const closeButton = dialog.querySelector('[data-detail-close]');
        let origin = null, position = null, controller = null, sequence = 0, requested = null;
        const clearUrl = () => {
            const url = new URL(win.location.href);
            url.searchParams.delete('metric'); url.searchParams.delete('detail_page'); url.hash = '';
            return url;
        };
        const mark = key => doc.querySelectorAll('a.dashboard-metric-card').forEach(card => {
            const selected = card.dataset.metric === key;
            card.classList.toggle('is-selected', selected);
            if (selected) card.setAttribute('aria-current', 'true'); else card.removeAttribute('aria-current');
        });
        function open(trigger) {
            if (dialog.open) return;
            origin = trigger || doc.querySelector('a.dashboard-metric-card.is-selected');
            position = { x: win.scrollX, y: win.scrollY };
            doc.body.classList.add('dashboard-detail-open');
            dialog.showModal();
            closeButton.focus({ preventScroll: true });
        }
        function close(updateUrl = true) {
            sequence++;
            if (controller) controller.abort();
            controller = null;
            if (dialog.open) dialog.close();
            doc.body.classList.remove('dashboard-detail-open');
            status.hidden = true; error.hidden = true; body.removeAttribute('aria-busy');
            if (updateUrl) win.history.replaceState(win.history.state, '', clearUrl());
            mark(null);
            if (origin && origin.isConnected) origin.focus({ preventScroll: true });
            if (position) win.scrollTo(position.x, position.y);
        }
        async function load(href, trigger, push = true) {
            const url = new URL(href, win.location.href);
            if (url.origin !== win.location.origin || url.pathname.replace(/\/$/, '') !== win.location.pathname.replace(/\/$/, '') || !url.searchParams.has('metric')) return;
            open(trigger);
            if (controller) controller.abort();
            controller = new AbortController();
            const localController = controller, current = ++sequence;
            requested = url.href;
            status.textContent = copy.loading_detail; status.hidden = false;
            error.hidden = true;
            body.setAttribute('aria-busy','true');
            const timeout = win.setTimeout(() => localController.abort(), 8000);
            try {
                const response = await win.fetch(url, { credentials:'same-origin', cache:'no-store', signal:localController.signal, headers: {
                    'Accept':'text/html', 'X-Requested-With':'XMLHttpRequest', 'X-Dashboard-Fragment':'metric'
                } });
                if (response.redirected || [401,403,419].includes(response.status)) throw new Error('auth');
                if (!response.ok) throw new Error('unavailable');
                const html = await response.text();
                if (current !== sequence) return;
                const parsed = new win.DOMParser().parseFromString(html,'text/html');
                const content = parsed.querySelector('[data-metric-detail-content]');
                if (!content || content.dataset.metric !== url.searchParams.get('metric')) throw new Error('invalid');
                body.replaceChildren(doc.importNode(content,true));
                status.hidden = true;
                if (push && win.location.href !== url.href) win.history.pushState(win.history.state,'',url);
                mark(content.dataset.metric);
                dialog.scrollTop = 0;
                closeButton.focus({ preventScroll: true });
            } catch (failure) {
                if (current !== sequence) return;
                status.hidden = true; error.hidden = false;
                error.querySelector('[data-detail-error-text]').textContent = failure.message === 'auth'
                    ? copy.auth_error
                    : copy.detail_error;
                error.querySelector('[data-detail-fallback]').href = url.href;
            } finally {
                win.clearTimeout(timeout);
                if (current === sequence) { controller = null; body.removeAttribute('aria-busy'); }
            }
        }
        doc.addEventListener('click', event => {
            const anchor = event.target.closest('a');
            if (!anchor || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
            if (!anchor.matches('a.dashboard-metric-card, .dashboard-drawer .dashboard-pagination a')) return;
            const url = new URL(anchor.href,win.location.href);
            if (url.origin !== win.location.origin || !url.searchParams.has('metric')) return;
            event.preventDefault(); load(anchor.href,anchor);
        });
        closeButton.addEventListener('click',()=>close());
        dialog.addEventListener('cancel',event=>{ event.preventDefault(); close(); });
        dialog.addEventListener('click',event=>{
            const rect=dialog.getBoundingClientRect();
            if (event.target===dialog && (event.clientX<rect.left || event.clientX>rect.right)) close();
        });
        error.querySelector('[data-detail-retry]').addEventListener('click',()=>{ if(requested) load(requested,origin); });
        win.addEventListener('popstate',()=>{
            if (new URL(win.location.href).searchParams.has('metric')) load(win.location.href,null,false);
            else close(false);
        });
        win.addEventListener('pagehide',()=>{ sequence++; if(controller) controller.abort(); });
        if (dialog.dataset.initialOpen === 'true') open(null);
    }
    const api = { initializeFilters, initializeDetails };
    if (typeof module !== 'undefined' && module.exports) module.exports = api;
    else root.AdminDashboardQueue = api;
})(typeof window !== 'undefined' ? window : globalThis);
