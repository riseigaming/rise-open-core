@once
    @push('js')
        <script>
            (() => {
                document.querySelectorAll('[data-global-account-form]').forEach(form => {
                    form.addEventListener('submit', async event => {
                        event.preventDefault();
                        if (form.dataset.submitting === 'true' || !form.reportValidity()) return;
                        if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) return;

                        const feedbackId = form.getAttribute('aria-describedby');
                        const feedback = (feedbackId ? document.getElementById(feedbackId) : null)
                            || form.querySelector('[data-global-account-feedback]')
                            || document.querySelector('[data-global-account-feedback]');
                        const button = form.querySelector('[type="submit"]');
                        const body = new FormData(form);
                        form.dataset.submitting = 'true';
                        button.disabled = true;
                        if (feedback) feedback.textContent = @json(trans('cms_actions.processing'));
                        try {
                            const response = await fetch(form.action, {
                                method: 'POST',
                                credentials: 'same-origin',
                                headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                                body,
                            });
                            if ([401, 419].includes(response.status)) {
                                window.location.assign(@json(route('cms.front.login')));
                                return;
                            }
                            const result = await response.json();
                            if (feedback) feedback.textContent = result.message || @json(trans('cms_alerts.general_error'));
                            if (response.ok && Number(result.status) === 200) {
                                const destination = new URL(result.redirectUrl || window.location.href, window.location.origin);
                                if (destination.origin === window.location.origin) window.location.assign(destination.href);
                            }
                        } catch (_) {
                            if (feedback) feedback.textContent = @json(trans('cms_alerts.general_error'));
                        } finally {
                            delete form.dataset.submitting;
                            button.disabled = false;
                        }
                    });
                });
            })();
        </script>
    @endpush
@endonce
