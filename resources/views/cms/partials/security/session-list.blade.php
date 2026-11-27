@php($history = $history ?? null)
<section class="cmsSessionPage">
    <div class="cmsSessionHeader">
        <div><h1>{{ __('cms_sessions.title') }}</h1><p>{{ __('cms_sessions.intro') }}</p></div>
        <a href="{{ route('cms.front.editPassword') }}">{{ __('cms_sessions.password') }}</a>
    </div>
    @if(session('session_status'))<p role="status">{{ session('session_status') }}</p>@endif
    @if(isset($errors) && $errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
    <p class="cmsSessionHint">{{ __('cms_sessions.availability') }} {{ __('cms_sessions.timezone', ['zone' => config('app.timezone')]) }}</p>
    <p class="cmsSessionFeedback" role="status" aria-live="polite"></p>
    <div class="cmsSessionActions">
        <form method="get">
            <label>{{ __('cms_sessions.show') }}
                <select name="sessions">
                    <option value="open" @if(($sessionFilter ?? 'open') === 'open') selected @endif>{{ __('cms_sessions.open_only') }}</option>
                    <option value="all" @if(($sessionFilter ?? 'open') === 'all') selected @endif>{{ __('cms_sessions.all_records') }}</option>
                </select>
            </label>
            <button type="submit" title="{{ __('cms_sessions.refresh') }}" aria-label="{{ __('cms_sessions.refresh') }}"><i class="fas fa-sync" aria-hidden="true"></i></button>
        </form>

            <form action="{{ route('cms.front.logoutOtherSessions') }}" method="post" data-session-action>@csrf
                <button type="submit">{{ __('cms_sessions.others') }}</button>
            </form>
            <form action="{{ route('cms.front.logoutAllSessions') }}" method="post" data-session-action>@csrf
                <button type="submit">{{ __('cms_sessions.all') }}</button>
            </form>
    </div>
    <div class="cmsSessionList">
        @forelse($sessions as $ses)
            <article class="cmsSessionRow" data-session-record="{{ $ses->id }}">
                <div>
                    <h2><i class="fas fa-desktop" aria-hidden="true"></i> {{ $ses->browser_name ?: __('cms_sessions.unknown') }} · {{ $ses->os_name ?: __('cms_sessions.unknown') }}
                        <span class="cmsSessionState{{ $ses->is_expired ? ' is-expired' : '' }}">{{ __($ses->is_current ? 'cms_sessions.current' : ($ses->is_expired ? 'cms_sessions.expired' : 'cms_sessions.active')) }}</span>
                    </h2>
                    <dl>
                        <div><dt>{{ __('cms_sessions.ip') }}</dt><dd>{{ $ses->display_ip ?: $ses->masked_ip_address ?: '-' }}</dd></div>
                        <div><dt>{{ __('cms_sessions.started') }}</dt><dd>{{ $ses->created_at?->format('d.m.Y H:i:s') }}</dd></div>
                        <div><dt>{{ __('cms_sessions.seen') }}</dt><dd>{{ $ses->updated_at?->format('d.m.Y H:i:s') }}</dd></div>
                    </dl>
                </div>

                    <form method="post" action="{{ route('cms.front.makeLogoutSession') }}" data-session-action>@csrf
                        <input type="hidden" name="userSessionId" value="{{ $ses->id }}">
                        <button type="submit">{{ __('cms_sessions.end') }}</button>
                    </form>

            </article>
        @empty
            <p data-sessions-empty>{{ __('cms_sessions.empty') }}</p>
        @endforelse
    </div>
    @if($sessions instanceof \Illuminate\Contracts\Pagination\Paginator){{ $sessions->withQueryString()->links() }}@endif
    <p class="cmsSessionHint">{{ __('cms_sessions.next_request') }}</p>
    <h2 class="cmsSessionHistoryTitle">{{ __('cms_sessions.history') }}</h2>
    @forelse($history ?? [] as $entry)
        <article class="cmsSessionRow">
            <div>
                <h2>{{ $entry->browser_name ?: __('cms_sessions.unknown') }} · {{ $entry->os_name ?: __('cms_sessions.unknown') }}</h2>
                <dl>
                    <div><dt>{{ __('cms_sessions.ip') }}</dt><dd>{{ $entry->display_ip ?: '-' }}</dd></div>
                    <div><dt>{{ __('cms_sessions.started') }}</dt><dd>{{ $entry->started_at }}</dd></div>
                    <div><dt>{{ __('cms_sessions.seen') }}</dt><dd>{{ $entry->last_activity_at }}</dd></div>
                    <div><dt>{{ __('cms_sessions.ended') }}</dt><dd>{{ $entry->ended_at }}</dd></div>
                </dl>

            </div>
        </article>
    @empty
        <p class="cmsSessionHint">{{ __('cms_sessions.history_empty') }}</p>
    @endforelse
    @if($history){{ $history->withQueryString()->links() }}@endif
</section>
<style>
    .cmsSessionPage{max-width:1120px;margin:24px auto;padding:0 20px 32px;color:inherit}
    .cmsSessionHeader{display:flex;align-items:start;justify-content:space-between;gap:20px;flex-wrap:wrap}
    .cmsSessionHeader>a{color:inherit;text-underline-offset:4px}
    .cmsSessionPage h1{font-size:24px;line-height:1.4;margin:0 0 8px}
    .cmsSessionPage h2{font-size:16px;line-height:1.5;margin:0 0 12px}
    .cmsSessionPage p,.cmsSessionPage dl{font-size:14px;line-height:1.6}
    .cmsSessionHint{opacity:.75;margin:16px 0}
    .cmsSessionActions{display:flex;gap:12px;flex-wrap:wrap;margin:20px 0}
    .cmsSessionRow{display:flex;justify-content:space-between;align-items:start;gap:20px;padding:20px 0;border-bottom:1px solid rgba(128,140,165,.3)}
    .cmsSessionRow>div{min-width:0;flex:1}
    .cmsSessionRow dl{display:flex;flex-wrap:wrap;gap:12px 28px;margin:0}
    .cmsSessionRow dt{font-size:12px;opacity:.75;font-weight:400}
    .cmsSessionRow dd{margin:0;overflow-wrap:anywhere}
    .cmsSessionState{display:inline-block;font-size:12px;margin-left:8px;color:inherit;border-bottom:2px solid #559f83}
    .cmsSessionState.is-expired{border-color:#8d97aa}
    .cmsSessionPage button{padding:9px 14px;border:1px solid rgba(128,140,165,.5);border-radius:6px;background:transparent;color:inherit;font-size:13px;cursor:pointer}
    .cmsSessionPage button:hover{background:rgba(128,140,165,.14)}
    .cmsSessionPage button:disabled{opacity:.5;cursor:wait}
    .cmsSessionPage summary{cursor:pointer;font-size:14px;padding:8px 0}
    .cmsSessionPage select{padding:8px;border:1px solid #8d97aa;border-radius:4px;background:transparent;color:inherit}
    .cmsSessionPage option{color:#172030;background:#fff}
    .cmsSessionPage input:not([type=hidden]){display:block;width:100%;min-width:180px;max-width:360px;padding:8px;margin:8px 0;border:1px solid #8d97aa;border-radius:4px;background:transparent;color:inherit}
    .cmsSessionAgent{max-width:640px;overflow-wrap:anywhere}
    .cmsSessionHistoryTitle{margin-top:32px!important}
    @media(max-width:600px){.cmsSessionRow{flex-direction:column;gap:12px}.cmsSessionPage{padding:0 16px 24px}.cmsSessionPage h1{font-size:21px}.cmsSessionRow dl{gap:12px 20px}}
</style>
<script>
(() => {
    const feedback = document.querySelector('.cmsSessionFeedback');
    document.querySelectorAll('[data-session-action]').forEach(form => form.addEventListener('submit', async event => {
        event.preventDefault();
        if (form.dataset.pending === 'true' || !window.confirm(@json(__('cms_sessions.confirm')))) return;
        form.dataset.pending = 'true';
        const button = form.querySelector('button[type="submit"]');
        button.disabled = true;
        feedback.textContent = @json(__('cms_sessions.processing'));
        try {
            const response = await fetch(form.action, {method:'POST', credentials:'same-origin',
                headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}, body:new FormData(form)});
            const result = await response.json();
            if (!response.ok || Number(result.status) !== 200) throw new Error(result.message || 'Request failed');
            window.location.assign(result.redirectUrl || @json(route('cms.front.sessions')));
        } catch (error) {
            feedback.textContent = @json(__('cms_sessions.failed'));
            button.disabled = false;
            form.dataset.pending = 'false';
        }
    }));
    document.querySelectorAll('[data-session-admin-action]').forEach(form => form.addEventListener('submit', event => {
        if (!window.confirm(@json(__('cms_sessions.confirm')))) event.preventDefault();
    }));
})();
</script>
