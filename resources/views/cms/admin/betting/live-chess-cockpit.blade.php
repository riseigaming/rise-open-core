@extends('cms.admin.index')

@section('style')
    <style>
        .cockpit-shell {
            padding-bottom: 2rem;
        }
        .cockpit-pane {
            border: 1px solid #dbe4ef;
            border-radius: 16px;
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            box-shadow: 0 14px 30px rgba(31, 58, 83, 0.06);
        }
        .cockpit-pane .card-header {
            background: transparent;
            border-bottom: 1px solid #e6eef8;
        }
        .board-list-shell,
        .queue-shell,
        .log-shell {
            max-height: 78vh;
            overflow: auto;
        }
        .board-row {
            display: block;
            padding: 0.95rem 1rem;
            border-bottom: 1px solid #eef3f9;
            color: inherit;
        }
        .board-row:hover {
            background: #f5f9ff;
        }
        .board-row.is-selected {
            background: #0f172a;
            color: #fff;
        }
        .board-row.is-selected .text-muted,
        .board-row.is-selected .small {
            color: rgba(255,255,255,0.72) !important;
        }
        .market-card,
        .template-card,
        .queue-card,
        .log-card {
            border: 1px solid #dfe9f4;
            border-radius: 14px;
            background: #fff;
        }
        .market-card.is-selected,
        .template-card.is-selected {
            border-color: #0f172a;
            box-shadow: 0 0 0 2px rgba(15, 23, 42, 0.08);
        }
        .status-pill {
            border-radius: 999px;
            padding: 0.35rem 0.65rem;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.02em;
        }
        .status-draft { background: #eef2f7; color: #4b5563; }
        .status-open { background: #dff8ec; color: #0f766e; }
        .status-suspended { background: #fff4d4; color: #a16207; }
        .status-closed { background: #e2e8f0; color: #334155; }
        .status-awaiting_result { background: #ffe7d1; color: #c2410c; }
        .status-settled { background: #d9f0ff; color: #0369a1; }
        .status-void { background: #fee2e2; color: #b91c1c; }
        .status-success { background: #dff8ec; color: #0f766e; }
        .status-warning { background: #fff4d4; color: #a16207; }
        .status-danger { background: #fee2e2; color: #b91c1c; }
        .status-secondary { background: #e2e8f0; color: #334155; }
        .status-info { background: #d9f0ff; color: #0369a1; }
        .status-live-feed-fresh { background: #dff8ec; color: #0f766e; }
        .status-live-feed-stale { background: #ffe7d1; color: #c2410c; }
        .status-live-feed-unknown { background: #eef2f7; color: #475569; }
        .source-pill,
        .editorial-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            border-radius: 999px;
            padding: 0.3rem 0.65rem;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            border: 1px solid transparent;
        }
        .source-pill + .source-pill,
        .editorial-pill + .editorial-pill {
            margin-left: 0.35rem;
        }
        .source-upstream { background: #e0ecff; color: #1d4ed8; border-color: #bfdbfe; }
        .source-manual { background: #fef3c7; color: #b45309; border-color: #fcd34d; }
        .integrity-source_confirmed { background: #dcfce7; color: #15803d; border-color: #86efac; }
        .integrity-source_pending { background: #fef3c7; color: #b45309; border-color: #fcd34d; }
        .integrity-incomplete { background: #fee2e2; color: #b91c1c; border-color: #fca5a5; }
        .editorial-live { background: #dcfce7; color: #166534; border-color: #86efac; }
        .editorial-hidden { background: #e2e8f0; color: #334155; border-color: #cbd5e1; }
        .readonly-shell {
            border: 1px dashed #cbd5e1;
            border-radius: 14px;
            background: #f8fafc;
            padding: 0.9rem 1rem;
        }
        .readonly-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.75rem;
        }
        .readonly-item {
            min-width: 0;
        }
        .disabled-action-reason {
            font-size: 0.78rem;
            color: #b45309;
            margin-top: 0.45rem;
        }
        .guard-warning-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .guard-warning-list li + li {
            margin-top: 0.35rem;
        }
        .board-clocks {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.5rem;
        }
        .clock-box {
            border: 1px solid #e5edf7;
            border-radius: 12px;
            background: #f8fbff;
            padding: 0.6rem 0.75rem;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 0.75rem;
        }
        .summary-box {
            border-radius: 14px;
            background: #f7fafc;
            padding: 0.85rem;
            border: 1px solid #e4edf5;
        }
        .section-label {
            text-transform: uppercase;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            color: #64748b;
        }
        .kbd-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 1.75rem;
            padding: 0.2rem 0.45rem;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            background: #fff;
            font-size: 0.78rem;
            font-weight: 700;
        }
        .queue-card.danger { border-left: 4px solid #dc2626; }
        .queue-card.warning { border-left: 4px solid #d97706; }
        .queue-card.info { border-left: 4px solid #0284c7; }
        .queue-card.secondary { border-left: 4px solid #64748b; }
        .bet-monitor-summary {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.65rem;
        }
        .bet-monitor-summary-box {
            border: 1px solid #e4edf5;
            border-radius: 12px;
            background: #f8fbff;
            padding: 0.75rem;
        }
        .bet-monitor-list {
            display: grid;
            gap: 0.75rem;
        }
        .bet-monitor-card {
            border: 1px solid #dfe9f4;
            border-radius: 14px;
            background: #fff;
            padding: 0.85rem 0.95rem;
        }
        .bet-monitor-card-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 0.75rem;
        }
        .bet-monitor-card-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.65rem 1rem;
            margin-top: 0.75rem;
        }
        .bet-monitor-label {
            text-transform: uppercase;
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            color: #64748b;
        }
        .bet-monitor-value {
            font-weight: 700;
            color: #0f172a;
            word-break: break-word;
        }
        .bet-monitor-copy {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            font-size: 0.8rem;
        }
        .market-actions .btn {
            margin-bottom: 0.35rem;
        }
        .board-editorial-actions form {
            display: inline-block;
            margin-right: 0.45rem;
            margin-bottom: 0.45rem;
        }
        .log-shell .log-card + .log-card {
            margin-top: 0.75rem;
        }
        @media (max-width: 1199.98px) {
            .board-list-shell,
            .queue-shell,
            .log-shell {
                max-height: none;
            }
            .summary-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
            .readonly-grid {
                grid-template-columns: minmax(0, 1fr);
            }
            .bet-monitor-summary,
            .bet-monitor-card-grid {
                grid-template-columns: minmax(0, 1fr);
            }
        }
    </style>
@endsection

@section('content')
    @include('cms.admin.betting.chess-canonical-read-model')
    @php
        $chessWalletSupportSettings = (array) ($chessWalletSupportSettings ?? []);
        $chessWalletOptions = collect((array) ($chessWalletSupportSettings['options'] ?? []));
        $chessSelectedAssets = collect((array) ($chessWalletSupportSettings['selectedAssets'] ?? []))
            ->map(fn ($value) => strtoupper((string) $value))
            ->all();
        $chessCodeLabel = static function ($value) {
            $normalized = strtolower(trim((string) $value));
            $key = 'admin.chess_cockpit.codes.'.str_replace([' ', '-'], '_', $normalized);

            return \Illuminate\Support\Facades\Lang::has($key) ? __($key) : $value;
        };
        $chessFieldLabel = static function ($value) {
            $key = 'admin.chess_cockpit.fields.'.strtolower((string) $value);

            return \Illuminate\Support\Facades\Lang::has($key) ? __($key) : str_replace('_', ' ', (string) $value);
        };
        $chessMessageLabel = static function ($value) {
            $messages = [
                'Chess core live snapshot endpoint is not configured.' => __('admin.chess_cockpit.endpoint_not_configured'),
                'Live source freshness is not currently confirmed.' => __('admin.chess_cockpit.freshness_unconfirmed'),
            ];

            return $messages[(string) $value] ?? $value;
        };
    @endphp
    <div class="container-fluid cockpit-shell" data-poll-interval-seconds="{{ $pollIntervalSeconds ?? 15 }}">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
            <div>
                <h2 class="mb-1">{{ __('admin_extra.adcfcad0b705') }}</h2>
                <div class="text-muted">
                    @if($isMonitoringOnly ?? false)
                        {{ __('admin.chess_cockpit.monitoring_intro') }}
                    @else
                        {{ __('admin.chess_cockpit.write_intro') }}
                    @endif
                </div>
            </div>
            @if(!empty($event))
                <div class="text-end">
                    <div class="section-label">{{ __('admin_extra.5d701fa6b3da') }}</div>
                    <div class="fw-bold">
                        {{ $event->title }}
                        @php
                            $freshnessStatus = strtolower((string) ($syncState['freshness_status'] ?? 'unknown'));
                        @endphp
                        <span class="status-pill status-live-feed-{{ in_array($freshnessStatus, ['fresh', 'stale'], true) ? $freshnessStatus : 'unknown' }}">{{ $chessCodeLabel($syncState['freshness_status'] ?? 'UNKNOWN') }}</span>
                        @if(!empty($eventMeta))
                            <span class="source-pill source-{{ $eventMeta['source_type'] }}">{{ $chessCodeLabel($eventMeta['source_type']) }}</span>
                            <span class="source-pill integrity-{{ $eventMeta['source_integrity'] }}">{{ $chessCodeLabel($eventMeta['source_integrity']) }}</span>
                        @endif
                    </div>
                    <div class="text-muted small">{{ $chessCodeLabel($event->stage_name) }} / {{ $chessCodeLabel($event->round_name) }}</div>
                </div>
            @endif
        </div>

        @if(session('betting_status'))
            <div class="alert alert-success">{{ __('admin_extra.2d9faadc2657', ['value1' => session('betting_status')]) }}</div>
        @endif
        @if($isMonitoringOnly ?? false)
            <div class="alert alert-info border border-primary-subtle">
                <strong>{{ __('admin_extra.6f4955fe97ba') }}</strong>
                {{ __('admin_extra.2e16c589ce32') }}
            </div>
        @endif
        @if(($shadowMode['enabled'] ?? false))
            <div class="alert alert-warning border border-dark" data-chess-shadow-active="1">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                    <div>
                        <strong>{{ __('admin_extra.d30356d442e4') }}</strong>
                        <div class="small mt-1">{{ __('admin_extra.10dca6933bcf') }}</div>
                    </div>
                    <div class="text-end small">
                        <div><strong>{{ __('admin_extra.5afeda7f4609') }}</strong> {{ __('admin_extra.2d9faadc2657', ['value1' => $shadowMode['source_type']]) }}</div>
                        <div><strong>{{ __('admin_extra.4ba384346527') }}</strong> {{ __('admin_extra.2d9faadc2657', ['value1' => $shadowMode['guard_summary']]) }}</div>
                    </div>
                </div>
            </div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">
                @foreach($errors->all() as $error)
                    <div>{{ __('admin_extra.2d9faadc2657', ['value1' => $error]) }}</div>
                @endforeach
            </div>
        @endif
        @if(($syncState['status'] ?? 'ok') !== 'ok')
            <div class="alert alert-warning">
                <strong>{{ __('admin_extra.0e9eccee27b6') }}</strong>
                {{ $chessMessageLabel($syncState['message'] ?? __('admin.chess_cockpit.endpoint_unreachable')) }}
                @if(!empty($syncState['failed_at']))
                    <div class="small mt-1">{{ __('admin_extra.6badfb655cb1', ['value1' => $syncState['failed_at']]) }}</div>
                @endif
            </div>
        @elseif(($syncState['freshness_status'] ?? 'UNKNOWN') !== 'FRESH')
            <div class="alert alert-warning">
                <strong>{{ __('admin_extra.22e4b9e6edbf') }}</strong>
                {{ $chessMessageLabel($syncState['freshness_reason'] ?? __('admin.chess_cockpit.freshness_unconfirmed')) }}
            </div>
        @endif

        <div class="card cockpit-pane mb-3">
            <div class="card-header">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                    <div>
                        <h5 class="mb-1">{{ __('admin_extra.2d9faadc2657', ['value1' => __('cms_public.game_detail.supported_wallets')]) }}</h5>
                        <div class="text-muted small">
                            {{ __('admin.copy.chess_wallet_help') }}
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-light text-dark border">{{ __('admin.copy.residual.source_prefix') }} {{ $chessWalletSupportSettings['sourceLabel'] ?? __('admin.copy.product_default') }}</span>
                        @if(!empty($chessWalletSupportSettings['settingsKey']))
                            <span class="badge bg-light text-dark border">{{ __('admin_extra.2d9faadc2657', ['value1' => $chessWalletSupportSettings['settingsKey']]) }}</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('cms.admin.betting.liveChessCockpit.walletSupport.update') }}">
                    @csrf
                    <input type="hidden" name="board" value="{{ (int) ($selectedBoard['id'] ?? request('board', 0)) }}">
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($chessWalletOptions as $option)
                            <label class="source-pill source-upstream" style="text-transform:none; cursor:pointer;">
                                <input
                                    type="checkbox"
                                    name="supported_wallet_assets[]"
                                    value="{{ $option['value'] }}"
                                    {{ in_array($option['value'], old('supported_wallet_assets', $chessSelectedAssets), true) ? 'checked' : '' }}
                                >
                                <span>{{ __('admin_extra.2d9faadc2657', ['value1' => $option['label']]) }}</span>
                            </label>
                        @endforeach
                    </div>
                    <div class="text-muted small mt-2">
                        {{ __('admin.copy.product_wallet_help') }}
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-outline-dark btn-sm">{{ __('admin.ui.save_wallet_settings') }}</button>
                    </div>
                </form>
            </div>
        </div>

        @if(!$isReady)
            <div class="alert alert-warning mb-0">
                {{ __('admin_extra.696d00f14db7') }}
                <code>{{ __('admin_extra.2bbfbdb40272') }}</code>
                {{ __('admin_extra.49ec08386604') }}
            </div>
        @else
            <div class="row g-3">
                <div class="col-xl-3">
                    <div class="card cockpit-pane h-100">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="section-label">{{ __('admin_extra.3d3d9d394f28') }}</div>
                                    <strong>{{ trans_choice('admin.chess_cockpit.board_count', $boards->count(), ['count' => $boards->count()]) }}</strong>
                                </div>
                                <div class="small text-muted"><span class="kbd-pill">{{ __('admin_extra.5c2dd944dde9') }}</span> / <span class="kbd-pill">k</span></div>
                            </div>
                        </div>
                        <div class="board-list-shell">
                            @foreach($boards as $board)
                                <a
                                    href="{{ route('cms.admin.betting.liveChessCockpit', ['board' => $board['id']]) }}"
                                    class="board-row {{ optional($selectedBoard)['id'] === $board['id'] ? 'is-selected' : '' }}"
                                    data-board-link
                                >
                                    <div class="d-flex justify-content-between align-items-start gap-2">
                                        <div>
                                            <div class="fw-bold">{{ __('admin_extra.432673362769', ['value1' => $board['board_no']]) }}</div>
                                            <div>{{ $chessCodeLabel($board['white_player_name']) }}</div>
                                            <div>{{ $chessCodeLabel($board['black_player_name']) }}</div>
                                            <div class="mt-2">
                                                <span class="source-pill source-{{ $board['source_type'] }}">{{ $chessCodeLabel($board['source_type']) }}</span>
                                                <span class="source-pill integrity-{{ $board['source_integrity'] }}">{{ $chessCodeLabel($board['source_integrity']) }}</span>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            @if($board['featured_flag'])
                                                <span class="badge bg-primary mb-1">{{ __('admin.ui.featured') }}</span>
                                            @endif
                                            @if($board['awaiting_settle'])
                                                <span class="badge bg-danger">{{ __('admin_extra.73f06275d653') }}</span>
                                            @endif
                                            @if($board['is_replaced'])
                                                <span class="badge bg-warning text-dark">{{ __('admin_extra.05033aaac5d0') }}</span>
                                            @endif
                                            @if($board['is_cancelled'])
                                                <span class="badge bg-secondary">{{ __('admin.dashboard.dynamic.cancelled') }}</span>
                                            @endif
                                            @if(!$board['editorial']['is_published'])
                                                <div class="mt-1"><span class="editorial-pill editorial-hidden">{{ __('admin_extra.fc6556a7ab95') }}</span></div>
                                            @endif
                                            @if($board['editorial']['is_hidden_from_betting_surface'])
                                                <div class="mt-1"><span class="editorial-pill editorial-hidden">{{ __('admin_extra.2d9faadc2657', ['value1' => __('cms_public.games.common.hidden')]) }}</span></div>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="small mt-2">{{ __('admin_extra.11dc9e195292') }} <strong>{{ $chessCodeLabel($board['official_status']) }}</strong></div>
                                    <div class="small">{{ __('admin_extra.1deaef346b2f', ['value1' => $board['white_clock'], 'value2' => $board['black_clock']]) }}</div>
                                    <div class="small">{{ __('admin_extra.9f0dfe272430', ['value1' => $board['open_market_count']]) }}</div>
                                    <div class="small">{{ __('admin_extra.2de3e4c42560', ['value1' => $board['last_action'] ?: '-']) }}</div>
                                    <div class="small">{{ __('admin_extra.6fa5ab8cafbe', ['value1' => $board['source_updated_at'] ?: '-']) }} <span class="badge bg-light text-dark border">{{ $chessCodeLabel($board['source_freshness_status']) }}</span></div>
                                    @if($board['open_block_reason'])
                                        <div class="disabled-action-reason">{{ __('admin_extra.2d9faadc2657', ['value1' => $board['open_block_reason']]) }}</div>
                                    @endif
                                    @if($board['official_result'])
                                        <div class="small mt-1">{{ __('admin_extra.e912ddcb4eb5') }} <strong>{{ __('admin_extra.2d9faadc2657', ['value1' => $board['official_result']]) }}</strong></div>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="col-xl-5">
                    <div class="card cockpit-pane mb-3">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="section-label">{{ __('admin_extra.7c514772ab33') }}</div>
                                    <strong>
                                        @if($selectedBoard)
                                            {{ __('admin.chess_cockpit.board_matchup', ['board' => $selectedBoard['board_no'], 'white' => $chessCodeLabel($selectedBoard['white_player_name']), 'black' => $chessCodeLabel($selectedBoard['black_player_name'])]) }}
                                        @else
                                            {{ __('admin.chess_cockpit.no_board_selected') }}
                                        @endif
                                    </strong>
                                </div>
                                <div class="text-end">
                                    @if(!empty($selectedBoard['featured_flag']))
                                        <span class="badge bg-primary">{{ __('admin_extra.7dbdf5cd35f8') }}</span>
                                    @endif
                                    @if($selectedBoard)
                                        <div class="mt-2">
                                            <span class="source-pill source-{{ $selectedBoard['source_type'] }}">{{ $chessCodeLabel($selectedBoard['source_type']) }}</span>
                                            <span class="source-pill integrity-{{ $selectedBoard['source_integrity'] }}">{{ $chessCodeLabel($selectedBoard['source_integrity']) }}</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            @if($selectedBoard)
                                <div class="row g-3 mb-3">
                                    <div class="col-lg-8">
                                        <div class="mb-2"><strong>{{ __('admin_extra.2d9faadc2657', ['value1' => $selectedBoard['event_name']]) }}</strong></div>
                                        <div class="text-muted mb-1">{{ __('admin_extra.fbb0094cc172', ['value1' => $chessCodeLabel($selectedBoard['stage_name']), 'value2' => $chessCodeLabel($selectedBoard['round_name'])]) }}</div>
                                        <div class="mb-1">{{ $chessCodeLabel($selectedBoard['white_player_name']) }} <span class="text-muted">{{ __('admin_extra.b58217ac9c20', ['value1' => $selectedBoard['white_rating'] ?: '-', 'value2' => $selectedBoard['white_points'] ?: '-']) }}</span></div>
                                        <div>{{ $chessCodeLabel($selectedBoard['black_player_name']) }} <span class="text-muted">{{ __('admin_extra.b58217ac9c20', ['value1' => $selectedBoard['black_rating'] ?: '-', 'value2' => $selectedBoard['black_points'] ?: '-']) }}</span></div>
                                        <div class="mt-2">{{ __('admin_extra.cd785b8b21f1') }} <span class="fw-bold">{{ $chessCodeLabel($selectedBoard['official_status']) }}</span></div>
                                        <div>{{ __('admin_extra.e912ddcb4eb5') }} <span class="fw-bold">{{ __('admin_extra.2d9faadc2657', ['value1' => $selectedBoard['official_result'] ?: '-']) }}</span></div>
                                        <div>{{ __('admin_extra.ac8a612571d4') }} <span class="fw-bold">{{ $chessCodeLabel($selectedBoard['source_freshness_status']) }}</span></div>
                                        @if($selectedBoard['is_replaced'] || $selectedBoard['is_cancelled'])
                                            <div class="mt-2">
                                                @if($selectedBoard['is_replaced'])
                                                    <span class="badge bg-warning text-dark">{{ __('admin_extra.7ede87ea6631') }}</span>
                                                @endif
                                                @if($selectedBoard['is_cancelled'])
                                                    <span class="badge bg-secondary">{{ __('admin_extra.0da06001854f') }}</span>
                                                @endif
                                            </div>
                                        @endif
                                        @if($selectedBoard['replacement_target'])
                                            <div class="small text-muted mt-1">{{ __('admin_extra.dd40991af658', ['value1' => $selectedBoard['replacement_target']]) }}</div>
                                        @endif
                                        @if($selectedBoard['replacement_source'])
                                            <div class="small text-muted mt-1">{{ __('admin_extra.7c68c3e42287', ['value1' => $selectedBoard['replacement_source']]) }}</div>
                                        @endif
                                        @if($selectedBoard['source_freshness_reason'])
                                            <div class="small text-muted mt-1">{{ $chessMessageLabel($selectedBoard['source_freshness_reason']) }}</div>
                                        @endif
                                        @if(!empty($selectedBoard['warnings']))
                                            <ul class="guard-warning-list mt-2">
                                                @foreach($selectedBoard['warnings'] as $warning)
                                                    <li class="small text-warning">{{ __('admin_extra.2d9faadc2657', ['value1' => $warning]) }}</li>
                                                @endforeach
                                            </ul>
                                        @endif
                                        @if($selectedBoard['official_source_url'])
                                            <div class="mt-2"><a href="{{ $selectedBoard['official_source_url'] }}" target="_blank" rel="noopener noreferrer">{{ __('admin_extra.47aa309c6463') }}</a></div>
                                        @endif
                                        <div class="small text-muted mt-1">{{ __('admin_extra.580a37ec2fd0', ['value1' => $selectedBoard['source_updated_at'] ?: '-']) }}</div>
                                    </div>
                                    <div class="col-lg-4">
                                        <div class="board-clocks">
                                            <div class="clock-box">
                                                <div class="section-label">{{ __('admin_extra.11ba93331700') }}</div>
                                                <strong>{{ __('admin_extra.2d9faadc2657', ['value1' => $selectedBoard['white_clock']]) }}</strong>
                                            </div>
                                            <div class="clock-box">
                                                <div class="section-label">{{ __('admin_extra.91c95065c4ee') }}</div>
                                                <strong>{{ __('admin_extra.2d9faadc2657', ['value1' => $selectedBoard['black_clock']]) }}</strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="readonly-shell mb-4">
                                    <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                                        <div>
                                            <div class="section-label">{{ __('admin_extra.6a9bbee90531') }}</div>
                                            <strong>{{ __('admin_extra.4d916af98544') }}</strong>
                                        </div>
                                        <div class="small text-muted">{{ __('admin_extra.97ff31cb5f01') }}</div>
                                    </div>
                                    <div class="readonly-grid">
                                        @foreach($selectedBoard['source_truth'] as $label => $value)
                                            <div class="readonly-item">
                                                <div class="section-label">{{ $chessFieldLabel($label) }}</div>
                                                <div class="fw-bold text-break">{{ $chessCodeLabel($value) }}</div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="summary-grid mb-4">
                                    <div class="summary-box">
                                        <div class="section-label">{{ __('admin.ui.open') }}</div>
                                        <div class="h4 mb-0">{{ __('admin_extra.2d9faadc2657', ['value1' => $selectedBoard['status_summary']['open']]) }}</div>
                                    </div>
                                    <div class="summary-box">
                                        <div class="section-label">{{ __('admin_extra.2d9faadc2657', ['value1' => __('affiliate.portal.status.account.suspended')]) }}</div>
                                        <div class="h4 mb-0">{{ __('admin_extra.2d9faadc2657', ['value1' => $selectedBoard['status_summary']['suspended']]) }}</div>
                                    </div>
                                    <div class="summary-box">
                                        <div class="section-label">{{ __('admin_extra.437a0d05f7ee') }}</div>
                                        <div class="h4 mb-0">{{ __('admin_extra.2d9faadc2657', ['value1' => $selectedBoard['status_summary']['review']]) }}</div>
                                    </div>
                                    <div class="summary-box">
                                        <div class="section-label">{{ __('admin_extra.2d9faadc2657', ['value1' => __('cms_public.sportsbook.history.resulted')]) }}</div>
                                        <div class="h4 mb-0">{{ __('admin_extra.2d9faadc2657', ['value1' => $selectedBoard['status_summary']['settled']]) }}</div>
                                    </div>
                                </div>

                                <div class="readonly-shell mb-4">
                                    <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                                        <div>
                                            <div class="section-label">{{ __('admin_extra.b7f25521f420') }}</div>
                                            <strong>{{ ($isMonitoringOnly ?? false) ? __('admin.chess_cockpit.read_only_editorial') : __('admin.chess_cockpit.surface_visibility') }}</strong>
                                        </div>
                                        <div>
                                            <span class="editorial-pill {{ $selectedBoard['editorial']['is_published'] ? 'editorial-live' : 'editorial-hidden' }}">
                                                {{ $selectedBoard['editorial']['is_published'] ? __('admin.chess_cockpit.published') : __('admin.chess_cockpit.unpublished') }}
                                            </span>
                                            <span class="editorial-pill {{ $selectedBoard['editorial']['is_hidden_from_betting_surface'] ? 'editorial-hidden' : 'editorial-live' }}">
                                                {{ $selectedBoard['editorial']['is_hidden_from_betting_surface'] ? __('admin.chess_cockpit.hidden_on_surface') : __('admin.chess_cockpit.visible_on_surface') }}
                                            </span>
                                        </div>
                                    </div>
                                    @if($isMonitoringOnly ?? false)
                                        <div class="small text-muted">{{ __('admin_extra.59fb77a0ecbf') }}</div>
                                    @else
                                        <div class="board-editorial-actions">
                                            <form method="POST" action="{{ route('cms.admin.betting.liveChessCockpit.boards.' . ($selectedBoard['featured_flag'] ? 'unfeature' : 'feature'), ['board' => $selectedBoard['id']]) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('admin_extra.2d9faadc2657', ['value1' => $selectedBoard['featured_flag'] ? 'Remove Featured' : 'Make Featured']) }}</button>
                                            </form>
                                            <form method="POST" action="{{ route('cms.admin.betting.liveChessCockpit.boards.' . ($selectedBoard['editorial']['is_published'] ? 'unpublish' : 'publish'), ['board' => $selectedBoard['id']]) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-dark btn-sm">{{ __('admin_extra.2d9faadc2657', ['value1' => $selectedBoard['editorial']['is_published'] ? 'Unpublish' : 'Publish']) }}</button>
                                            </form>
                                            <form method="POST" action="{{ route('cms.admin.betting.liveChessCockpit.boards.' . ($selectedBoard['editorial']['is_hidden_from_betting_surface'] ? 'show' : 'hide'), ['board' => $selectedBoard['id']]) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-secondary btn-sm">{{ __('admin_extra.2d9faadc2657', ['value1' => $selectedBoard['editorial']['is_hidden_from_betting_surface'] ? 'Show on Betting Surface' : 'Hide from Betting Surface']) }}</button>
                                            </form>
                                        </div>
                                    @endif
                                </div>

                                @if($isMonitoringOnly ?? false)
                                    <div class="readonly-shell mb-4">
                                        <div class="section-label mb-2">{{ __('admin_extra.341573ee1259') }}</div>
                                        <strong class="d-block mb-2">{{ __('admin_extra.9871772d1173') }}</strong>
                                        <div class="small text-muted">{{ __('admin_extra.5bb9a9ea7af8') }}</div>
                                    </div>
                                @else
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div>
                                            <div class="section-label">{{ __('admin_extra.3f117d443062') }}</div>
                                            <strong>{{ __('admin_extra.11a491d891c3') }}</strong>
                                        </div>
                                        <div class="small text-muted"><span class="kbd-pill">{{ __('admin_extra.7a81af3e591a') }}</span> {{ __('admin_extra.67b3cd4a9011') }}</div>
                                    </div>
                                    <div class="row g-2 mb-4" id="templateCards">
                                        @foreach($templates as $index => $template)
                                            <div class="col-md-6">
                                                <div
                                                    class="template-card p-3 {{ $index === 0 ? 'is-selected' : '' }}"
                                                    data-template-card
                                                    data-template-key="{{ $template['key'] }}"
                                                >
                                                    <div class="section-label">{{ __('admin_extra.2d9faadc2657', ['value1' => $template['group_label']]) }}</div>
                                                    <div class="fw-bold mb-1">{{ __('admin_extra.2d9faadc2657', ['value1' => $template['title']]) }}</div>
                                                    <div class="small text-muted mb-2">{{ __('admin_extra.2d9faadc2657', ['value1' => $template['description']]) }}</div>
                                                    <div class="small mb-2">
                                                        @foreach($template['selections'] as $selection)
                                                            <span class="badge bg-light text-dark border me-1">{{ __('admin_extra.2d9faadc2657', ['value1' => $selection['label']]) }}</span>
                                                        @endforeach
                                                    </div>
                                                    <div class="d-flex gap-2">
                                                        <button
                                                            type="button"
                                                            class="btn btn-dark btn-sm js-template-action"
                                                            data-template-key="{{ $template['key'] }}"
                                                            data-target-status="open"
                                                            @if(!$template['can_open']) disabled title="{{ $template['open_block_reason'] }}" @endif
                                                        >
                                                            {{ __('admin.ui.open') }}
                                                        </button>
                                                        <button
                                                            type="button"
                                                            class="btn btn-outline-secondary btn-sm js-template-action"
                                                            data-template-key="{{ $template['key'] }}"
                                                            data-target-status="draft"
                                                            @if(!$template['can_open']) disabled title="{{ $template['open_block_reason'] }}" @endif
                                                        >
                                                            {{ __('admin_extra.23d33e22acfc') }}
                                                        </button>
                                                    </div>
                                                    @if(!$template['can_open'])
                                                        <div class="disabled-action-reason">{{ __('admin_extra.2d9faadc2657', ['value1' => $template['open_block_reason']]) }}</div>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div>
                                            <div class="section-label">{{ __('admin_extra.9f6c1d010fda') }}</div>
                                            <strong>{{ __('admin_extra.9f9ce372a31a') }}</strong>
                                        </div>
                                        <div class="small text-muted">
                                            <span class="kbd-pill">s</span> {{ __('admin_extra.b6f0c43f69fa') }}
                                            <span class="kbd-pill">{{ __('admin_extra.84a516841ba7') }}</span> {{ __('admin_extra.da38860cb875') }}
                                            <span class="kbd-pill">{{ __('admin_extra.8efd86fb78a5') }}</span> {{ __('admin_extra.d424af29a8c5') }}
                                            <span class="kbd-pill">v</span> {{ __('admin_extra.e9cede9b80ea') }}
                                            <span class="kbd-pill">{{ __('admin_extra.d1854cae891e') }}</span> {{ __('admin_extra.c51048b7325d') }}
                                        </div>
                                    </div>
                                @endif

                                @forelse($selectedBoard['markets'] as $index => $market)
                                    @php
                                        $statusClass = 'status-' . $market['status'];
                                        $selectionPrompt = collect($market['selections'])->map(function ($selection) {
                                            return $selection['selection_code'] . ':' . $selection['label'];
                                        })->implode(', ');
                                    @endphp
                                    <div class="market-card p-3 mb-3 {{ $index === 0 ? 'is-selected' : '' }}" data-market-card data-market-id="{{ $market['id'] }}">
                                        <div class="d-flex justify-content-between align-items-start gap-3">
                                            <div>
                                                <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                                                    <span class="status-pill {{ $statusClass }}">{{ $chessCodeLabel($market['status']) }}</span>
                                                    @if(data_get($market, 'meta.shadow_mode'))
                                                        <span class="badge bg-warning text-dark">{{ __('admin_extra.ed9d3d832af8') }}</span>
                                                    @endif
                                                    <span class="badge bg-light text-dark border">{{ $chessCodeLabel($market['template_type']) }}</span>
                                                    @if($market['template_variant'] !== 'default')
                                                        <span class="badge bg-light text-dark border">{{ $chessCodeLabel($market['template_variant']) }}</span>
                                                    @endif
                                                </div>
                                                <div class="fw-bold">{{ __('admin_extra.2d9faadc2657', ['value1' => $market['title']]) }}</div>
                                                <div class="small text-muted mt-1">
                                                    {{ __('admin_extra.83ac969a8360', ['value1' => $market['opened_at'] ?: '-', 'value2' => $market['closed_at'] ?: '-', 'value3' => $market['settled_at'] ?: '-']) }}
                                                </div>
                                                <div class="small mt-2">
                                                    @foreach($market['selections'] as $selection)
                                                            <span class="badge bg-light text-dark border me-1">{{ $chessCodeLabel($selection['label']) }}</span>
                                                    @endforeach
                                                </div>
                                                @if($market['operator_note'])
                                                    <div class="small mt-2"><strong>{{ __('admin_extra.83423c198b60') }}</strong> {{ __('admin_extra.2d9faadc2657', ['value1' => $market['operator_note']]) }}</div>
                                                @endif
                                                @if($market['settle_reason'])
                                                    <div class="small mt-1"><strong>{{ __('admin_extra.18386d70f7fb') }}</strong> {{ __('admin_extra.2d9faadc2657', ['value1' => $market['settle_reason']]) }}</div>
                                                @endif
                                                @if($market['result_selection_code'])
                                                    <div class="small mt-1"><strong>{{ __('admin_extra.bd4afc0073ca') }}</strong> {{ __('admin_extra.2d9faadc2657', ['value1' => $market['result_selection_code']]) }}</div>
                                                @endif
                                                @if($market['latest_action'])
                                                    <div class="small mt-1 text-muted">{{ __('admin_extra.2de3e4c42560', ['value1' => $market['latest_action']]) }}</div>
                                                @endif
                                            </div>
                                            <div class="text-end">
                                                @if($market['suggested_selection_code'])
                                                    <div class="small text-muted">{{ __('admin_extra.c7efda72b9f5') }} <strong>{{ __('admin_extra.2d9faadc2657', ['value1' => $market['suggested_selection_code']]) }}</strong></div>
                                                @endif
                                            </div>
                                        </div>
                                        @if($isMonitoringOnly ?? false)
                                            <div class="small text-muted mt-3">{{ __('admin_extra.ec648fcde974') }}</div>
                                        @else
                                            <div class="market-actions mt-3">
                                                @if($market['status'] === 'draft')
                                                    <button type="button" class="btn btn-dark btn-sm js-market-action" data-action="open" data-route="{{ route('cms.admin.betting.liveChessCockpit.markets.open', ['market' => $market['id']]) }}">{{ __('admin.ui.open') }}</button>
                                                @endif
                                                @if($market['status'] === 'open')
                                                    <button type="button" class="btn btn-outline-warning btn-sm js-market-action" data-hotkey-action="suspend" data-action="suspend" data-route="{{ route('cms.admin.betting.liveChessCockpit.markets.suspend', ['market' => $market['id']]) }}">{{ __('admin_extra.b24247fb5a9d') }}</button>
                                                    <button type="button" class="btn btn-outline-dark btn-sm js-market-action" data-hotkey-action="close" data-action="close" data-route="{{ route('cms.admin.betting.liveChessCockpit.markets.close', ['market' => $market['id']]) }}" data-confirm="Close this market and stop taking bets?">{{ __('admin.ui.close') }}</button>
                                                @endif
                                                @if($market['status'] === 'suspended')
                                                    <button type="button" class="btn btn-outline-success btn-sm js-market-action" data-action="reopen" data-route="{{ route('cms.admin.betting.liveChessCockpit.markets.reopen', ['market' => $market['id']]) }}">{{ __('admin_extra.61e05da2a472') }}</button>
                                                    <button type="button" class="btn btn-outline-dark btn-sm js-market-action" data-hotkey-action="close" data-action="close" data-route="{{ route('cms.admin.betting.liveChessCockpit.markets.close', ['market' => $market['id']]) }}" data-confirm="Close this suspended market and stop taking bets?">{{ __('admin.ui.close') }}</button>
                                                @endif
                                                @if(in_array($market['status'], ['closed', 'awaiting_result']))
                                                    <button
                                                        type="button"
                                                        class="btn btn-outline-primary btn-sm js-market-action"
                                                        data-hotkey-action="settle"
                                                        data-action="settle"
                                                        data-route="{{ route('cms.admin.betting.liveChessCockpit.markets.settle', ['market' => $market['id']]) }}"
                                                        data-selection-options="{{ $selectionPrompt }}"
                                                        data-suggested-selection="{{ $market['suggested_selection_code'] }}"
                                                        @if($market['actions']['settle_block_reason']) disabled title="{{ $market['actions']['settle_block_reason'] }}" @endif
                                                    >
                                                        {{ __('admin_extra.3ebe66ce7cf6') }}
                                                    </button>
                                                @endif
                                                @if(in_array($market['status'], ['open', 'suspended', 'closed', 'awaiting_result']))
                                                    <button type="button" class="btn btn-outline-danger btn-sm js-market-action" data-hotkey-action="void" data-action="void" data-route="{{ route('cms.admin.betting.liveChessCockpit.markets.void', ['market' => $market['id']]) }}" data-confirm="Void this market? This cannot be reversed in V1.">{{ __('admin_extra.207c7c00630b') }}</button>
                                                @endif
                                                <button type="button" class="btn btn-outline-secondary btn-sm js-market-action" data-hotkey-action="note" data-action="note" data-route="{{ route('cms.admin.betting.liveChessCockpit.markets.note', ['market' => $market['id']]) }}" data-current-note="{{ e($market['operator_note'] ?: '') }}">{{ __('admin_extra.2c924e308820') }}</button>
                                                @if($market['actions']['settle_block_reason'])
                                                    <div class="disabled-action-reason">{{ __('admin_extra.2d9faadc2657', ['value1' => $market['actions']['settle_block_reason']]) }}</div>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                @empty
                                    <div class="alert alert-info mb-0">
                                        {{ __('admin_extra.2d9faadc2657', ['value1' => ($isMonitoringOnly ?? false) ? 'No market record exists on this board yet.' : 'No markets exist on this board yet. Open a template from the cards above.']) }}
                                    </div>
                                @endforelse
                            @else
                                <div class="alert alert-secondary mb-0">{{ __('admin_extra.64c08c7d3000') }}</div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-xl-4">
                    <div class="card cockpit-pane mb-3">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div>
                                <div class="section-label">{{ __('admin_extra.04f4c5fbe21f') }}</div>
                                <strong>{{ $dailyBetWindowLabel ?? now()->translatedFormat('d M Y') }}</strong>
                            </div>
                            <div class="small text-muted">{{ __('admin_extra.37ae06c3ee60') }}</div>
                        </div>
                        <div class="card-body">
                            <div class="bet-monitor-summary mb-3">
                                <div class="bet-monitor-summary-box">
                                    <div class="section-label">{{ __('admin.ui.total') }}</div>
                                    <div class="h4 mb-0">{{ __('admin_extra.2d9faadc2657', ['value1' => $dailyBetSummary['total'] ?? 0]) }}</div>
                                </div>
                                <div class="bet-monitor-summary-box">
                                    <div class="section-label">{{ __('admin.ui.pending') }}</div>
                                    <div class="h4 mb-0">{{ __('admin_extra.2d9faadc2657', ['value1' => $dailyBetSummary['pending'] ?? 0]) }}</div>
                                </div>
                                <div class="bet-monitor-summary-box">
                                    <div class="section-label">{{ __('admin_extra.2d9faadc2657', ['value1' => __('cms_public.sportsbook.history.resulted')]) }}</div>
                                    <div class="h4 mb-0">{{ __('admin_extra.2d9faadc2657', ['value1' => $dailyBetSummary['settled'] ?? 0]) }}</div>
                                </div>
                                <div class="bet-monitor-summary-box">
                                    <div class="section-label">{{ __('admin_extra.2d9faadc2657', ['value1' => __('cms_public.sportsbook.bet_feed.action_won')]) }}</div>
                                    <div class="h4 mb-0">{{ __('admin_extra.2d9faadc2657', ['value1' => $dailyBetSummary['won'] ?? 0]) }}</div>
                                </div>
                                <div class="bet-monitor-summary-box">
                                    <div class="section-label">{{ __('admin_extra.2d9faadc2657', ['value1' => __('cms_public.sportsbook.bet_feed.action_lost')]) }}</div>
                                    <div class="h4 mb-0">{{ __('admin_extra.2d9faadc2657', ['value1' => $dailyBetSummary['lost'] ?? 0]) }}</div>
                                </div>
                                <div class="bet-monitor-summary-box">
                                    <div class="section-label">{{ __('admin.dashboard.dynamic.failed') }}</div>
                                    <div class="h4 mb-0">{{ __('admin_extra.2d9faadc2657', ['value1' => $dailyBetSummary['failed'] ?? 0]) }}</div>
                                </div>
                            </div>

                            @if(collect($dailyBetReservations ?? [])->isEmpty())
                                <div class="alert alert-info mb-0">{{ __('admin_extra.6055cc332573') }}</div>
                            @else
                                <div class="bet-monitor-list queue-shell">
                                    @foreach($dailyBetReservations as $reservation)
                                        <article class="bet-monitor-card">
                                            <div class="bet-monitor-card-head">
                                                <div>
                                                <div class="fw-bold">{{ __('admin_extra.2d9faadc2657', ['value1' => $reservation['customer_label']]) }}</div>
                                                <div class="small text-muted">
                                                        {{ __('admin_extra.39d860921f85', ['value1' => $reservation['wallet_label'] ? 'Wallet: ' . $reservation['wallet_label'] . ' • ' : '', 'value2' => $reservation['provider_name'], 'value3' => $reservation['partner_code'] ?: '-']) }}
                                                </div>
                                                <div class="d-flex flex-wrap gap-2 mt-2">
                                                    <span class="badge bg-light text-dark border">{{ __('admin.copy.transaction_wallet') }}: {{ $reservation['wallet_context']['transaction_wallet'] ?? $reservation['currency'] }}</span>
                                                    <span class="badge bg-light text-dark border">{{ __('admin.copy.active_wallet') }}: {{ $reservation['wallet_context']['active_wallet'] ?? $reservation['currency'] }}</span>
                                                    <span class="badge bg-light text-dark border">{{ __('admin.copy.product') }}: {{ $reservation['wallet_context']['product_type'] ?? 'Chess' }}</span>
                                                    @if(!empty($reservation['wallet_context']['unsupported_reason']))
                                                        <span class="badge bg-warning text-dark">{{ __('admin_extra.ada265aa553a', ['value1' => $reservation['wallet_context']['unsupported_reason']]) }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                                <span class="status-pill status-{{ $reservation['status_tone'] }}">{{ __('admin_extra.2d9faadc2657', ['value1' => $reservation['status_label']]) }}</span>
                                            </div>
                                            <div class="bet-monitor-card-grid">
                                                <div>
                                                    <div class="bet-monitor-label">{{ __('admin.ui.stake') }}</div>
                                                    <div class="bet-monitor-value">{{ __('admin_extra.493b2a989f3f', ['value1' => number_format((float) $reservation['stake_amount'], 2), 'value2' => $reservation['currency']]) }}</div>
                                                </div>
                                                <div>
                                                    <div class="bet-monitor-label">{{ __('admin.ui.payout') }}</div>
                                                    <div class="bet-monitor-value">{{ __('admin_extra.493b2a989f3f', ['value1' => number_format((float) $reservation['payout_amount'], 2), 'value2' => $reservation['currency']]) }}</div>
                                                </div>
                                                <div>
                                                    <div class="bet-monitor-label">{{ __('admin_extra.39d1f19d01ed') }}</div>
                                                    <div class="bet-monitor-value">{{ __('admin_extra.2d9faadc2657', ['value1' => $reservation['reserved_at'] ?: '-']) }}</div>
                                                </div>
                                                <div>
                                                    <div class="bet-monitor-label">{{ __('admin.ui.settled_at') }}</div>
                                                    <div class="bet-monitor-value">{{ __('admin_extra.2d9faadc2657', ['value1' => $reservation['settled_at'] ?: '-']) }}</div>
                                                </div>
                                                <div>
                                                    <div class="bet-monitor-label">{{ __('admin_extra.f7e9fc22ef03') }}</div>
                                                    <div class="bet-monitor-value bet-monitor-copy">{{ __('admin_extra.2d9faadc2657', ['value1' => $reservation['support_id'] ?: '-']) }}</div>
                                                </div>
                                                <div>
                                                    <div class="bet-monitor-label">{{ __('admin_extra.1a9d76a93f7f') }}</div>
                                                    <div class="bet-monitor-value bet-monitor-copy">{{ __('admin_extra.2d9faadc2657', ['value1' => $reservation['technical_ref'] ?: '-']) }}</div>
                                                </div>
                                                <div>
                                                    <div class="bet-monitor-label">{{ __('admin_extra.ffcf132c06fa') }}</div>
                                                    <div class="bet-monitor-value bet-monitor-copy">{{ __('admin_extra.2d9faadc2657', ['value1' => $reservation['match_id'] ?: '-']) }}</div>
                                                </div>
                                                <div>
                                                    <div class="bet-monitor-label">{{ __('admin_extra.d8b16b56b8cb') }}</div>
                                                    <div class="bet-monitor-value bet-monitor-copy">{{ __('admin_extra.f52d942c22d3', ['value1' => $reservation['selection_id'] ?: '-', 'value2' => $reservation['odds_decimal'] ? ' • ' . $reservation['odds_decimal'] : '']) }}</div>
                                                </div>
                                            </div>
                                            @if($reservation['summary'])
                                                <div class="small text-muted mt-2">{{ __('admin_extra.2d9faadc2657', ['value1' => $reservation['summary']]) }}</div>
                                            @endif
                                        </article>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="card cockpit-pane mb-3">
                        <div class="card-header">
                            <div class="section-label">{{ __('admin_extra.5a5e7b337acf') }}</div>
                            <strong>{{ trans_choice('admin.chess_cockpit.active_item_count', $queueItems->count(), ['count' => $queueItems->count()]) }}</strong>
                        </div>
                        <div class="card-body queue-shell">
                            @forelse($queueItems as $item)
                                <a href="{{ route('cms.admin.betting.liveChessCockpit', ['board' => $item['board_id']]) }}" class="queue-card {{ $item['severity'] }} d-block p-3 mb-3">
                                    <div class="d-flex justify-content-between align-items-start gap-2">
                                        <div>
                                            <div class="fw-bold">{{ __('admin_extra.2d9faadc2657', ['value1' => $item['label']]) }}</div>
                                            <div class="small text-muted">{{ __('admin_extra.432673362769', ['value1' => $item['board_no']]) }}</div>
                                        </div>
                                        <span class="badge bg-light text-dark border">{{ __('admin_extra.2d9faadc2657', ['value1' => $item['severity']]) }}</span>
                                    </div>
                                    <div class="small mt-2">{{ __('admin_extra.2d9faadc2657', ['value1' => $item['summary']]) }}</div>
                                </a>
                            @empty
                                <div class="alert alert-success mb-0">
                                    {{ __('admin_extra.e7c0b5cd06ed') }}
                                </div>
                            @endforelse
                        </div>
                    </div>

                    <div class="card cockpit-pane">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div>
                                <div class="section-label">{{ __('admin_extra.28dd7536f7a6') }}</div>
                                <strong>{{ __('admin_extra.2e71156a2832') }}</strong>
                            </div>
                            <div class="small text-muted">{{ __('admin_extra.f5ec7772ca9b') }}</div>
                        </div>
                        <div class="card-body log-shell">
                            @forelse($logs as $log)
                                <div class="log-card p-3">
                                    <div class="d-flex justify-content-between align-items-start gap-2">
                                        <div>
                                            <div class="fw-bold">
                                                {{ $log->action_type }}
                                                @if(data_get($log->meta, 'shadow_mode'))
                                                    <span class="badge bg-warning text-dark">{{ __('admin_extra.ed9d3d832af8') }}</span>
                                                @endif
                                            </div>
                                            <div class="small text-muted">{{ optional($log->created_at)->format('d.m.Y H:i:s') }}</div>
                                        </div>
                                        <div class="text-end small">
                                            <div>{{ optional($log->actor)->full_name ?: optional($log->actor)->username ?: 'system' }}</div>
                                            <div class="text-muted">{{ __('admin_extra.4ed3655ea94b') }}</div>
                                        </div>
                                    </div>
                                    <div class="small mt-2">
                                        <strong>{{ __('admin_extra.96a41b946454') }}</strong> {{ data_get($log->meta, 'market_title') ?: optional($log->market)->title ?: '-' }}
                                    </div>
                                    <div class="small"><strong>{{ __('admin_extra.0232a5693962') }}</strong> {{ $log->chess_live_board_id ?: '-' }} | <strong>{{ __('admin_extra.e1d1873098d3') }}</strong> {{ $log->chess_live_event_id }}</div>
                                    <div class="small"><strong>{{ __('admin_extra.97d826d2d50c') }}</strong> {{ $log->from_state ?: '-' }} -> {{ $log->to_state ?: '-' }}</div>
                                    @if($log->reason_type)
                                        <div class="small"><strong>{{ __('admin_extra.2c25e64f53a5') }}</strong> {{ $log->reason_type }}</div>
                                    @endif
                                    @if($log->note)
                                        <div class="small"><strong>{{ __('admin_extra.83423c198b60') }}</strong> {{ $log->note }}</div>
                                    @endif
                                    @if(data_get($log->meta, 'shadow_mode'))
                                        <div class="small text-warning"><strong>{{ __('admin_extra.4ba384346527') }}</strong> {{ __('admin_extra.8755496d1e42') }}</div>
                                    @endif
                                    @if($log->source_ref)
                                        <div class="small text-muted"><strong>{{ __('admin_extra.0e55596f6e8d') }}</strong> {{ $log->source_ref }}</div>
                                    @endif
                                </div>
                            @empty
                                <div class="alert alert-secondary mb-0">
                                    {{ __('admin_extra.976cf77b6701') }}
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            @unless($isMonitoringOnly ?? false)
                <form id="templateActionForm" method="POST" action="{{ route('cms.admin.betting.liveChessCockpit.templates.open') }}" class="d-none">
                    @csrf
                    <input type="hidden" name="board_id" value="{{ $selectedBoard['id'] ?? '' }}">
                    <input type="hidden" name="template_key" id="templateActionKey">
                    <input type="hidden" name="target_status" id="templateActionStatus">
                </form>

                <form id="marketActionForm" method="POST" class="d-none">
                    @csrf
                    <input type="hidden" name="result_selection_code" id="marketResultSelectionCode">
                    <input type="hidden" name="settle_reason" id="marketSettleReason">
                    <input type="hidden" name="settle_note" id="marketSettleNote">
                    <input type="hidden" name="note" id="marketOperatorNote">
                </form>
            @endunless
        @endif
    </div>
@endsection

@section('script')
    @if($isReady)
        <script>
            (function () {
                const monitoringOnly = @json((bool) ($isMonitoringOnly ?? false));
                const templateCards = Array.from(document.querySelectorAll('[data-template-card]'));
                const marketCards = Array.from(document.querySelectorAll('[data-market-card]'));
                const boardLinks = Array.from(document.querySelectorAll('[data-board-link]'));
                const templateForm = document.getElementById('templateActionForm');
                const marketForm = document.getElementById('marketActionForm');
                const pollIntervalSeconds = Number(document.querySelector('[data-poll-interval-seconds]')?.getAttribute('data-poll-interval-seconds') || '15');

                function setSelected(cards, current) {
                    cards.forEach((card) => card.classList.remove('is-selected'));
                    if (current) {
                        current.classList.add('is-selected');
                    }
                }

                templateCards.forEach((card) => {
                    card.addEventListener('click', function (event) {
                        if (!event.target.closest('button')) {
                            setSelected(templateCards, card);
                        }
                    });
                });

                marketCards.forEach((card) => {
                    card.addEventListener('click', function (event) {
                        if (!event.target.closest('button')) {
                            setSelected(marketCards, card);
                        }
                    });
                });

                if (monitoringOnly) {
                    if (pollIntervalSeconds > 0) {
                        window.setInterval(function () {
                            const activeTag = String(document.activeElement && document.activeElement.tagName || '').toLowerCase();
                            if (document.hidden || activeTag === 'input' || activeTag === 'textarea' || activeTag === 'select') {
                                return;
                            }

                            window.location.reload();
                        }, pollIntervalSeconds * 1000);
                    }

                    return;
                }

                function submitTemplate(templateKey, targetStatus) {
                    document.getElementById('templateActionKey').value = templateKey;
                    document.getElementById('templateActionStatus').value = targetStatus;
                    templateForm.submit();
                }

                document.querySelectorAll('.js-template-action').forEach((button) => {
                    button.addEventListener('click', function (event) {
                        event.preventDefault();
                        setSelected(templateCards, button.closest('[data-template-card]'));
                        submitTemplate(button.dataset.templateKey, button.dataset.targetStatus);
                    });
                });

                function resetMarketForm() {
                    document.getElementById('marketResultSelectionCode').value = '';
                    document.getElementById('marketSettleReason').value = '';
                    document.getElementById('marketSettleNote').value = '';
                    document.getElementById('marketOperatorNote').value = '';
                }

                function executeMarketAction(button) {
                    const action = button.dataset.action;
                    const route = button.dataset.route;
                    const confirmation = button.dataset.confirm;

                    if (confirmation && !window.confirm(confirmation)) {
                        return;
                    }

                    resetMarketForm();

                    if (action === 'settle') {
                        const selectionOptions = button.dataset.selectionOptions || '';
                        const suggestedSelection = button.dataset.suggestedSelection || '';
                        const selectionCode = window.prompt(
                            'Settlement selection code (' + selectionOptions + ')' + (suggestedSelection ? ' Suggested: ' + suggestedSelection : ''),
                            suggestedSelection || ''
                        );

                        if (!selectionCode) {
                            return;
                        }

                        const settleReason = window.prompt(
                            'Settle reason: official_board_result, standings_confirmed, bracket_confirmed, arbiter_confirmation, manual_override',
                            'official_board_result'
                        );

                        if (!settleReason) {
                            return;
                        }

                        let settleNote = window.prompt('Settle note (optional unless manual_override)', '');
                        if (settleReason === 'manual_override' && !settleNote) {
                            settleNote = window.prompt('manual_override requires a note', '');
                            if (!settleNote) {
                                return;
                            }
                        }

                        document.getElementById('marketResultSelectionCode').value = selectionCode;
                        document.getElementById('marketSettleReason').value = settleReason;
                        document.getElementById('marketSettleNote').value = settleNote || '';
                    }

                    if (action === 'note') {
                        const currentNote = button.dataset.currentNote || '';
                        const nextNote = window.prompt('Operator note', currentNote);
                        if (nextNote === null) {
                            return;
                        }

                        document.getElementById('marketOperatorNote').value = nextNote;
                    }

                    if (action === 'void') {
                        const voidNote = window.prompt('Void note (optional)', '');
                        if (voidNote !== null) {
                            document.getElementById('marketOperatorNote').value = voidNote;
                        }
                    }

                    marketForm.setAttribute('action', route);
                    marketForm.submit();
                }

                document.querySelectorAll('.js-market-action').forEach((button) => {
                    button.addEventListener('click', function (event) {
                        event.preventDefault();
                        setSelected(marketCards, button.closest('[data-market-card]'));
                        executeMarketAction(button);
                    });
                });

                function selectedTemplateOpenButton() {
                    const selectedTemplate = document.querySelector('[data-template-card].is-selected') || templateCards[0];
                    return selectedTemplate ? selectedTemplate.querySelector('[data-target-status="open"]') : null;
                }

                function selectedMarketAction(actionName) {
                    const selectedMarket = document.querySelector('[data-market-card].is-selected') || marketCards[0];
                    return selectedMarket ? selectedMarket.querySelector('[data-hotkey-action="' + actionName + '"]') : null;
                }

                function currentBoardIndex() {
                    return boardLinks.findIndex((link) => link.classList.contains('is-selected'));
                }

                document.addEventListener('keydown', function (event) {
                    const tagName = String(document.activeElement && document.activeElement.tagName || '').toLowerCase();
                    if (tagName === 'input' || tagName === 'textarea' || tagName === 'select') {
                        return;
                    }

                    if (event.key === 'j' || event.key === 'k') {
                        const index = currentBoardIndex();
                        const fallbackIndex = index < 0 ? 0 : index;
                        const delta = event.key === 'j' ? 1 : -1;
                        const nextLink = boardLinks[fallbackIndex + delta];
                        if (nextLink) {
                            window.location.href = nextLink.getAttribute('href');
                            event.preventDefault();
                        }
                    }

                    if (event.key === 'o') {
                        const button = selectedTemplateOpenButton();
                        if (button) {
                            button.click();
                            event.preventDefault();
                        }
                    }

                    if (event.key === 's') {
                        const button = selectedMarketAction('suspend');
                        if (button) {
                            button.click();
                            event.preventDefault();
                        }
                    }

                    if (event.key === 'c') {
                        const button = selectedMarketAction('close');
                        if (button) {
                            button.click();
                            event.preventDefault();
                        }
                    }

                    if (event.key === 't') {
                        const button = selectedMarketAction('settle');
                        if (button) {
                            button.click();
                            event.preventDefault();
                        }
                    }

                    if (event.key === 'v') {
                        const button = selectedMarketAction('void');
                        if (button) {
                            button.click();
                            event.preventDefault();
                        }
                    }

                    if (event.key === 'n') {
                        const button = selectedMarketAction('note');
                        if (button) {
                            button.click();
                            event.preventDefault();
                        }
                    }
                });

                if (pollIntervalSeconds > 0) {
                    window.setInterval(function () {
                        const activeTag = String(document.activeElement && document.activeElement.tagName || '').toLowerCase();
                        if (document.hidden || activeTag === 'input' || activeTag === 'textarea' || activeTag === 'select') {
                            return;
                        }

                        window.location.reload();
                    }, pollIntervalSeconds * 1000);
                }
            })();
        </script>
    @endif
@endsection
