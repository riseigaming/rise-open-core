@extends("cms.admin.index")
@section('title', __('admin.dashboard.title'))
@section("style")
<link rel="stylesheet" href="{{ asset('cms-assets/css/admin-dashboard.css') }}?v={{ filemtime(public_path('cms-assets/css/admin-dashboard.css')) }}">
@endsection
@section("content")
<div class="container-xl">
@php
    $dashboardDateRangeLabel = __('admin.dashboard.date_ranges.'.$dateRange);
    $dashboardDateWindowLabel = $period['start']
        ? $period['start']->format('d.m.Y H:i').' – '.$period['end']->format('d.m.Y H:i')
        : __('admin.dashboard.all_records_period');
    $dashboardComparisonLabel = $period['previous_start']
        ? $period['previous_start']->format('d.m.Y H:i').' – '.$period['previous_end']->format('d.m.Y H:i')
        : null;
    $dashboardJsCopy = array_merge(trans('admin.dashboard.js'), ['health' => trans('admin.dashboard.health')]);
@endphp
<main class="dashboard-shell" aria-label="{{ __('admin.dashboard.workspace') }}" data-dashboard-view="{{ $period['view'] }}">
    <div class="dashboard-controls">
        <nav class="dashboard-filter-list" aria-label="{{ __('admin.dashboard.report_period') }}">
            @foreach($dateFilters as $filter)
                <a href="{{ $dateFilterLinks[$filter['key']] }}" class="dashboard-filter-link {{ $dateRange === $filter['key'] ? 'is-active' : '' }}" @if($dateRange === $filter['key']) aria-current="page" @endif>{{ __('admin.dashboard.date_ranges.'.$filter['key']) }}</a>
            @endforeach
        </nav>
        <details class="dashboard-filter-popover">
            <summary>@include('cms.admin.partials.dashboard-icon',['name'=>'filters'])<span>{{ __('admin.dashboard.date_and_filters') }}</span></summary>
            <form method="get" action="{{ route('cms.admin.home') }}" class="dashboard-filter-form">
                <input type="hidden" name="view" value="{{ $period['view'] }}">
                @if($selectedMetric)<input type="hidden" name="metric" value="{{ $selectedMetric }}">@endif
                <label>{{ __('admin.dashboard.period') }}<select name="dateRange" data-date-range>
                    @foreach(__('admin.dashboard.date_ranges') as $key=>$label)
                        <option value="{{ $key }}" @if($dateRange === $key) selected @endif>{{ __('admin_extra.2d9faadc2657', ['value1' => $label]) }}</option>
                    @endforeach
                </select></label>
                <div class="dashboard-date-inputs" data-custom-dates @if($dateRange !== 'custom') hidden @endif>
                    <label>{{ __('admin.dashboard.start') }}<input type="date" name="start" max="{{ now()->toDateString() }}" value="{{ $period['start']?->toDateString() }}" @if($dateRange !== 'custom') disabled @endif></label>
                    <label>{{ __('admin.dashboard.end') }}<input type="date" name="end" max="{{ now()->toDateString() }}" value="{{ $period['end']?->toDateString() }}" @if($dateRange !== 'custom') disabled @endif></label>
                </div>
                <label>{{ __('admin.dashboard.comparison') }}<select name="compare"><option value="off" @if(!$period['comparison_enabled']) selected @endif>{{ __('admin.dashboard.comparison_off') }}</option><option value="previous" @if($period['comparison_enabled']) selected @endif>{{ __('admin.dashboard.comparison_previous') }}</option></select></label>
                <small>{{ __('admin.dashboard.range_help') }}</small>
                <button class="dashboard-button is-primary" type="submit">{{ __('admin.dashboard.apply') }}</button>
            </form>
        </details>
        <a class="dashboard-refresh-report" href="{{ request()->fullUrl() }}">@include('cms.admin.partials.dashboard-icon',['name'=>'refresh'])<span>{{ __('admin.dashboard.refresh_report') }}</span></a>
    </div>
    @if($errors->any())<div class="dashboard-notice" role="alert">{{ $errors->first() }}</div>@endif
    <div class="dashboard-context">
        <strong>{{ __('admin_extra.2d9faadc2657', ['value1' => $dashboardDateRangeLabel]) }}</strong><span>{{ __('admin_extra.2d9faadc2657', ['value1' => $dashboardDateWindowLabel]) }}</span><span>{{ __('admin_extra.2d9faadc2657', ['value1' => $period['timezone']]) }}</span>
        <span>{{ __('admin.dashboard.report') }}: <time datetime="{{ $period['as_of'] }}">{{ \Carbon\Carbon::parse($period['as_of'])->format('H:i:s') }}</time></span>
        @if($period['comparison_enabled'])<span>{{ __('admin.dashboard.compared_with') }}: {{ $dashboardComparisonLabel }}</span>@endif
    </div>
    @php
        $metrics = [
            ['key'=>'registers','label'=>__('admin.dashboard.metrics.registers'),'value'=>number_format($registerCount),'note'=>__('admin.dashboard.metrics.registers_note'),'comparison'=>'registers'],
            ['key'=>'logins','label'=>__('admin.dashboard.metrics.logins'),'value'=>number_format($loginCount),'note'=>__('admin.dashboard.metrics.logins_note',['users'=>number_format($uniqueLoginUserCount ?: $loginCount),'records'=>number_format($loginCount)]),'comparison'=>'logins'],
        ];
    @endphp
    <section class="dashboard-metric-grid" aria-label="{{ __('admin.dashboard.selected_period_metrics') }}">
        @foreach($metrics as $metric)
            @if($metric['key'])
                <a href="{{ $metricLinks[$metric['key']] }}#metric-detail" data-metric="{{ $metric['key'] }}" class="dashboard-metric-card {{ $selectedMetric === $metric['key'] ? 'is-selected' : '' }}" @if($selectedMetric === $metric['key']) aria-current="true" @endif aria-label="{{ __('admin.dashboard.metrics.open_details',['metric'=>$metric['label']]) }}">
            @else
                <div class="dashboard-metric-card" data-metric="net">
            @endif
                <div class="dashboard-metric-heading"><span class="dashboard-metric-label">{{ __('admin_extra.2d9faadc2657', ['value1' => $metric['label']]) }}</span>@if($metric['key'])<span class="dashboard-metric-arrow" aria-hidden="true">↗</span>@endif</div><p class="dashboard-metric-value">{{ __('admin_extra.2d9faadc2657', ['value1' => $metric['value']]) }}</p>
                <div class="dashboard-metric-subvalue">{{ __('admin_extra.2d9faadc2657', ['value1' => $metric['note']]) }}</div>
                @if($period['comparison_enabled'] && $metric['comparison'])<div class="dashboard-comparison">{{ __('admin_extra.2d9faadc2657', ['value1' => $comparison[$metric['comparison']]['text']]) }}</div>@endif
            @if($metric['key'])</a>@else</div>@endif
        @endforeach
    </section>
    <dialog id="dashboard-detail-dialog" class="dashboard-drawer" aria-label="{{ __('admin.dashboard.metric_detail') }}" data-initial-open="{{ $metricDetail ? 'true' : 'false' }}">
        <div class="dashboard-drawer-top"><span>{{ __('admin.dashboard.transaction_detail') }}</span><button type="button" class="dashboard-button" data-detail-close autofocus>{{ __('admin.dashboard.close_detail') }} <span aria-hidden="true">×</span></button></div>
        <div class="dashboard-drawer-status" data-detail-status role="status" hidden></div>
        <div data-detail-body>@if($metricDetail)@include('cms.admin.partials.dashboard-metric-detail')@endif</div>
        <div class="dashboard-drawer-error" data-detail-error hidden><p data-detail-error-text></p><button class="dashboard-button" type="button" data-detail-retry>{{ __('admin.dashboard.retry') }}</button><a data-detail-fallback class="dashboard-detail-link">{{ __('admin.dashboard.open_full_page') }}</a></div>
    </dialog>
    @if($metricDetail)<noscript>@include('cms.admin.partials.dashboard-metric-detail')<a href="{{ $selectedMetricClearLink }}">{{ __('admin.dashboard.close_detail') }}</a></noscript>@endif

        <section class="dashboard-panel">
            <div class="dashboard-panel-header"><div><h2 class="dashboard-panel-title">{{ __('admin.dashboard.last_seven_days') }}</h2><p class="dashboard-panel-text">{{ __('admin.dashboard.fixed_window_note') }}</p></div></div>
            <div class="dashboard-table-scroll"><table class="dashboard-table">
                <thead><tr><th scope="col">{{ __('admin.dashboard.day') }}</th><th scope="col" class="numeric">{{ __('admin.dashboard.registrations') }}</th><th scope="col" class="numeric">{{ __('admin.dashboard.logins') }}</th></tr></thead>
                <tbody>@foreach($recentDailyOverview as $day)<tr><td title="{{ $day['full_date'] }}">{{ __('admin_extra.2d9faadc2657', ['value1' => $day['label']]) }}</td><td class="numeric">{{ __('admin_extra.2d9faadc2657', ['value1' => number_format($day['registers'])]) }}</td><td class="numeric">{{ __('admin_extra.2d9faadc2657', ['value1' => number_format($day['logins'])]) }}</td></tr>@endforeach</tbody>
            </table></div>
        </section>
    <details class="dashboard-secondary dashboard-panel"><summary>{{ __('admin.dashboard.other_indicators') }}</summary>
        <div class="dashboard-summary"><span>{{ __('admin.dashboard.active_promotions_now', ['count' => $activePromotionCount]) }}</span><span>{{ __('admin.dashboard.promotions_ending', ['count' => $endingSoonPromotionCount]) }}</span><span>{{ __('admin.dashboard.total_users', ['count' => $totalUserCount]) }}</span></div>
    </details>
</main>
</div>
@endsection
@section('script')
    <script src="{{ asset('cms-assets/js/admin-dashboard.js') }}?v={{ filemtime(public_path('cms-assets/js/admin-dashboard.js')) }}"></script>
    <script>window.AdminDashboardQueue.initializeFilters(document, @json($dashboardJsCopy)); window.AdminDashboardQueue.initializeDetails(document, @json($dashboardJsCopy));</script>
@endsection
