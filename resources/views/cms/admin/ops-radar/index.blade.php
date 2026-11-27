@extends('cms.admin.index')
@section('title', 'Ops Radar')
@section('content')
<div class="container-fluid py-3">
    <h1 class="h2">Ops Radar</h1>
    <p>Read-only operational evidence · {{ $cockpit['generated_at'] }}</p>
    <section class="mb-4" aria-labelledby="ops-system">
        <h2 id="ops-system" class="h4">System</h2>
        <p>Database {{ $cockpit['system']['database'] }} · Public maintenance {{ $cockpit['system']['public_maintenance'] ? 'on' : 'off' }} · Finance control {{ $cockpit['system']['finance_control'] }}</p>
        @if($global = $cockpit['system']['global'])
            <p><a href="{{ $global['url'] }}">Global content</a> · {{ $global['published'] }} published · {{ $global['draft'] }} drafts · Accent {{ $global['accent'] }}</p>
        @endif
    </section>
    <form method="get" action="{{ route('cms.admin.opsRadar.index') }}" class="d-flex flex-wrap gap-3 align-items-end mb-4">
        <label>Provider <select name="provider" class="form-select"><option value="">All permitted</option>
            @foreach($aliases as $alias)<option @selected(($filters['provider'] ?? null) === $alias)>{{ $alias }}</option>@endforeach
        </select></label>
        <label>State <select name="state" class="form-select"><option value="">All</option>
            @foreach($states as $state)<option @selected(($filters['state'] ?? null) === $state)>{{ $state }}</option>@endforeach
        </select></label>
        <label>Reference <input class="form-control" name="reference" placeholder="txn:123" value="{{ $filters['reference'] ?? '' }}"></label>
        <button class="btn btn-primary" type="submit">Filter</button>
    </form>
    @if($cockpit['providers'])
        <section class="table-responsive mb-4" aria-labelledby="ops-providers">
            <h2 id="ops-providers" class="h4">Providers</h2>
            <table class="table">
                <thead><tr><th>Provider</th><th>Recorded status</th><th>Last sync</th><th>Games</th><th>Launches / 24h</th><th>Callback failures / 24h</th><th>Replays / 24h</th><th>Source</th></tr></thead>
                <tbody>@foreach($cockpit['providers'] as $provider)
                    <tr><td>{{ $provider['alias'] }}</td><td>{{ $provider['state'] }}</td><td>{{ $provider['last_sync'] ?? '—' }}</td>
                        <td>{{ $provider['games'] }}</td><td>{{ $provider['launches_24h'] }}</td>
                        <td>{{ $provider['callback_failures_24h'] }}</td><td>{{ $provider['replays_24h'] }}</td>
                        <td>@if($provider['url'])<a href="{{ $provider['url'] }}">Provider health and reconciliation</a>@else — @endif</td></tr>
                @endforeach</tbody>
            </table>
        </section>
    @endif
    <section class="table-responsive" aria-labelledby="ops-records">
        <h2 id="ops-records" class="h4">Operational records</h2>
        <p>Up to {{ $cockpit['sample_limit'] }} recent records per source. Use an exact reference for an older record.</p>
        <table class="table">
            <thead><tr><th>Reference</th><th>Provider</th><th>State</th><th>Evidence</th><th>Linked effects</th><th>Source</th><th>Time</th></tr></thead>
            <tbody>@forelse($cockpit['rows'] as $row)
                <tr data-ops-reference="{{ $row['reference'] }}"><td>{{ $row['reference'] }}</td><td>{{ $row['provider'] ?? '—' }}</td>
                    <td>{{ $row['state'] }}</td><td>{{ $row['reason'] }}
                        @if(!empty($row['duplicate_observation']))<br>Receipt replay evidence: {{ $row['duplicate_observation'] }}@endif</td>
                    <td>@foreach($row['effects'] as $effect)
                        <div><a href="{{ route('cms.admin.opsRadar.index', ['reference' => $effect['reference']]) }}">{{ $effect['reference'] }}</a>
                            {{ $effect['ledger_reference'] ?? 'unverified' }}
                            @if($effect['matched']){{ $effect['direction'] }} {{ $effect['amount'] }} {{ $effect['asset'] }}@endif</div>
                    @endforeach</td>
                    <td>@if($row['url'])<a href="{{ $row['url'] }}">Open source</a>@else — @endif</td><td>{{ $row['time'] }}</td></tr>
            @empty
                <tr><td colspan="7">No matching evidence.</td></tr>
            @endforelse</tbody>
        </table>
    </section>
</div>
@endsection
