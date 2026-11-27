<section class="dashboard-metric-detail" data-metric-detail-content data-metric="{{ $selectedMetric }}" data-clear-url="{{ $selectedMetricClearLink }}">
                    <div class="dashboard-panel-header">
                        <div>
                            <h2 class="dashboard-panel-title" id="dashboard-detail-title">{{ __('admin_extra.2d9faadc2657', ['value1' => $metricDetail['title']]) }}</h2>
                            <p class="dashboard-panel-text">{{ __('admin_extra.2d9faadc2657', ['value1' => $metricDetail['description']]) }}</p>
                        </div>
                        <div class="dashboard-panel-actions">
                            <span class="dashboard-chip">{{ __('admin_extra.2d9faadc2657', ['value1' => $metricDetail['summary']]) }}</span>
                            @if(!empty($metricDetail['full_link']))
                                <a href="{{ $metricDetail['full_link'] }}" class="dashboard-detail-link">{{ __('admin_extra.2d9faadc2657', ['value1' => $metricDetail['full_link_label']]) }}</a>
                            @endif
                        </div>
                    </div>

                    @if(!empty($metricDetail['rows']))
                        <div class="dashboard-table-scroll table-responsive">
                        <table class="dashboard-table" role="table">
                            <thead>
                            <tr>
                                @foreach($metricDetail['columns'] as $column)
                                    <th scope="col">{{ __('admin_extra.2d9faadc2657', ['value1' => $column]) }}</th>
                                @endforeach
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($metricDetail['rows'] as $row)
                                <tr role="row">
                                    @foreach($row['cells'] as $cell)
                                        <td role="cell" data-label="{{ $metricDetail['columns'][$loop->index] }}">
                                            <strong>{{ __('admin_extra.2d9faadc2657', ['value1' => $cell['primary']]) }}</strong>
                                            @if(!empty($cell['secondary']))
                                                <span class="dashboard-table-muted">{{ __('admin_extra.2d9faadc2657', ['value1' => $cell['secondary']]) }}</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                        </div>

                    @else
                        <div class="dashboard-empty">{{ __('admin_extra.2d9faadc2657', ['value1' => $metricDetail['empty']]) }}</div>
                    @endif
                    <nav class="dashboard-pagination" aria-label="{{ __('admin.dashboard.pagination') }}">
                        <span>{{ __('admin.dashboard.page_records',['page'=>$metricDetail['pagination']['page'],'total'=>number_format($metricDetail['pagination']['total'])]) }}</span>
                        @if($metricDetail['pagination']['previous'])<a class="dashboard-button" href="{{ $metricDetail['pagination']['previous'] }}">{{ __('admin.dashboard.previous') }}</a>@endif
                        @if($metricDetail['pagination']['next'])<a class="dashboard-button" href="{{ $metricDetail['pagination']['next'] }}">{{ __('admin.dashboard.next') }}</a>@endif
                    </nav>
                </section>
