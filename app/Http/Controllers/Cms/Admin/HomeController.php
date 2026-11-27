<?php

namespace App\Http\Controllers\Cms\Admin;

use App\Enum\UserLogEnum;
use App\Http\Controllers\Controller;
use App\Models\CmsPromotion;
use App\Models\CmsUser;
use App\Models\CmsUserLog;
use App\Services\AdminAnalytics\DashboardPeriod;
use App\Services\GrowthAnalytics\GrowthCustomerIdentity;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class HomeController extends Controller
{
    private ?array $period = null;


    public function index(Request $request)
    {
        $this->period = DashboardPeriod::fromRequest($request);
        $range = $this->period;
        $dateRange = $range['key'];
        $selectedMetric = $this->resolveDashboardMetric($request->get('metric'));
        $registerCount = $this->buildQueryByDate(app(GrowthCustomerIdentity::class)->roots(), $dateRange)->count();
        $loginCount = $this->buildQueryByDate($this->nonVirtualLoginLogQuery(), $dateRange)
            ->where('log_type', UserLogEnum::LOGGED_IN)->count();
        $uniqueLoginUserCount = $this->buildQueryByDate($this->nonVirtualLoginLogQuery(), $dateRange)
            ->where('log_type', UserLogEnum::LOGGED_IN)->whereNotNull('cms_user_id')->distinct()->count('cms_user_id');
        $comparison = [
            'registers' => $this->metricDeltaData($registerCount, $range['comparison_enabled']
                ? $this->applyWindow(app(GrowthCustomerIdentity::class)->roots(), $range['previous_start'], $range['previous_end'])->count() : null),
            'logins' => $this->metricDeltaData($loginCount, $range['comparison_enabled']
                ? $this->applyWindow($this->nonVirtualLoginLogQuery(), $range['previous_start'], $range['previous_end'])
                    ->where('log_type', UserLogEnum::LOGGED_IN)->count() : null),
        ];
        $data = compact('dateRange', 'selectedMetric', 'registerCount', 'loginCount', 'uniqueLoginUserCount', 'comparison') + [
            'period' => $range, 'dateFilters' => $this->dateFilters(),
            'dateFilterLinks' => collect($this->dateFilters())->mapWithKeys(fn ($filter) => [$filter['key'] =>
                $this->contextLink(['dateRange' => $filter['key'], 'start' => null, 'end' => null, 'detail_page' => null])])->all(),
            'metricLinks' => ['registers' => $this->buildDashboardMetricLink($dateRange, 'registers'),
                'logins' => $this->buildDashboardMetricLink($dateRange, 'logins')],
            'selectedMetricClearLink' => $this->buildDashboardMetricLink($dateRange),
            'recentDailyOverview' => $this->buildRecentDailyOverview(),
            'activePromotionCount' => CmsPromotion::query()->where('status', 'active')->count(),
            'endingSoonPromotionCount' => CmsPromotion::query()->where('status', 'active')
                ->whereBetween('end_date', [now(), now()->addDays(7)])->count(),
            'totalUserCount' => app(GrowthCustomerIdentity::class)->roots()->count(),
        ];
        $data['metricDetail'] = $this->buildMetricDetail($selectedMetric, $range, $data);
        if ($request->header('X-Dashboard-Fragment') === 'metric') {
            abort_unless($data['metricDetail'], 422, __('admin.dashboard.dynamic.select_metric'));
            return response()->view('cms.admin.partials.dashboard-metric-detail', $data)->header('Cache-Control', 'private, no-store');
        }
        return view('cms.admin.home', $data);
    }

    public function buildQueryByDate(Builder $builder, $time = null, string $column = 'created_at'): Builder
    {
        $resolvedRange = $this->resolveDateRange($time);

        return $this->applyWindow($builder, $resolvedRange['start'], $resolvedRange['end'], $column);
    }

    private function applyWindow(Builder $builder, ?Carbon $start, ?Carbon $end, string $column = 'created_at'): Builder
    {
        if (!$start || !$end) {
            return $builder;
        }

        return $builder->whereBetween($column, [$start, $end]);
    }

    private function resolveDateRange(?string $time): array
    {
        return $this->period ?? DashboardPeriod::fromRequest(new Request(['dateRange' => $time ?: 'today']));
    }

    private function dateFilters(): array
    {
        return [
            ['key' => 'today', 'label' => __('admin.copy.today')],
            ['key' => 'yesterday', 'label' => __('admin.copy.yesterday')],
            ['key' => 'last7', 'label' => __('admin.dashboard.last_seven_days')],
            ['key' => 'thisMonth', 'label' => __('admin.users.this_month')],
        ];
    }

    private function contextLink(array $overrides = []): string
    {
        return route('cms.admin.home', array_filter(
            array_replace($this->period['query'] ?? [], $overrides),
            static fn ($value) => $value !== null && $value !== ''
        ));
    }

    private function metricDeltaData($currentValue, $previousValue): array
    {
        if ($currentValue === null || $previousValue === null) {
            return [
                'direction' => 'neutral',
                'text' => __('admin.dashboard.dynamic.no_comparison'),
            ];
        }

        $delta = round($currentValue - $previousValue, 2);

        if ((float) $delta === 0.0) {
            return [
                'direction' => 'neutral',
                'text' => __('admin.dashboard.dynamic.same_as_previous'),
            ];
        }

        if ((float) $previousValue === 0.0) {
            return [
                'direction' => $delta > 0 ? 'up' : 'down',
                'text' => $delta > 0 ? __('admin.dashboard.dynamic.no_previous_activity') : __('admin.dashboard.dynamic.lower_than_previous'),
            ];
        }

        $percentage = round(abs($delta) / abs((float) $previousValue) * 100);

        return [
            'direction' => $delta > 0 ? 'up' : 'down',
            'text' => __('admin.dashboard.dynamic.percent_vs_previous', [
                'direction' => $delta > 0 ? '+' : '-',
                'percent' => $percentage,
            ]),
        ];
    }

    private function nonVirtualLoginLogQuery(): Builder
    {
        return CmsUserLog::query()->whereDoesntHave('user',
            fn (Builder $query) => $query->withTrashed()->where(function (Builder $query): void {
                $query->where('registration_source', CmsUser::WEEKLY_WINNER_VIRTUAL_SOURCE);
                if (Schema::hasColumn('cms_users', 'is_smoke_account')) {
                    $query->orWhere('is_smoke_account', true);
                }
            }));
    }

    private function buildRecentDailyOverview(): array
    {
        $now = Carbon::parse($this->period['as_of'] ?? now());
        $start = $now->copy()->subDays(6)->startOfDay();
        $users = app(GrowthCustomerIdentity::class)->roots()->whereBetween('created_at', [$start, $now])
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')->groupByRaw('DATE(created_at)')->pluck('total', 'day');
        $logins = $this->nonVirtualLoginLogQuery()->where('log_type', UserLogEnum::LOGGED_IN)
            ->whereBetween('created_at', [$start, $now])
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')->groupByRaw('DATE(created_at)')->pluck('total', 'day');
        $rows = [];
        for ($offset = 0; $offset < 7; $offset++) {
            $day = $start->copy()->addDays($offset);
            $key = $day->toDateString();
            $rows[] = [
                'label' => $day->format('d.m'), 'full_date' => $day->format('d.m.Y'),
                'registers' => (int) ($users[$key] ?? 0), 'logins' => (int) ($logins[$key] ?? 0),
            ];
        }
        return $rows;
    }

    private function resolveDashboardMetric(?string $metric): ?string
    {
        abort_if($metric !== null && !in_array($metric, ['registers', 'logins'], true), 422);
        return $metric;
    }

    private function buildDashboardMetricLink(string $dateRange, ?string $metric = null): string
    {
        return $this->contextLink(['dateRange' => $dateRange, 'metric' => $metric, 'detail_page' => null]);
    }

    private function buildMetricDetail(?string $metric, array $resolvedRange, array $context): ?array
    {
        if (!$metric) {
            return null;
        }

        $dateLabel = $resolvedRange['window_label'];

        switch ($metric) {
            case 'registers':
                $users = $this->applyWindow(app(GrowthCustomerIdentity::class)->roots(), $resolvedRange['start'], $resolvedRange['end'])
                    ->orderByDesc('created_at')->orderByDesc('id')
                    ->offset($this->detailOffset())->limit(50)
                    ->get();

                return [
                    'title' => __('admin.dashboard.dynamic.new_registrations_detail'),
                    'description' => __('admin.dashboard.dynamic.registrations_description', ['period' => $dateLabel]),
                    'summary' => __('admin.dashboard.dynamic.registration_count', ['count' => number_format((int) $context['registerCount'])]),
                    'columns' => [__('admin.dashboard.user'), __('admin.dashboard.dynamic.contact'), __('admin.dashboard.dynamic.account'), __('admin.dashboard.dynamic.registration')],
                    'rows' => $users->map(function (CmsUser $user) {
                        return [
                            'cells' => [
                                $this->detailCell($user->full_name ?: __('admin.dashboard.dynamic.unnamed_user'), $this->userReference($user)),
                                $this->detailCell($user->email ?: __('admin.dashboard.no_email'), $user->phone ?: __('admin.dashboard.dynamic.no_phone')),
                                $this->detailCell($user->currency ?: '-', $user->role ?: 'user'),
                                $this->detailCell($this->formatDashboardDateTime($user->created_at), 'ID: ' . $user->id),
                            ],
                        ];
                    })->all(),
                    'empty' => __('admin.dashboard.dynamic.no_registrations'),
                    'pagination' => $this->detailPagination((int) $context['registerCount']),
                ];

            case 'logins':
                $logs = $this->applyWindow($this->nonVirtualLoginLogQuery()->with(['user', 'operator']), $resolvedRange['start'], $resolvedRange['end'])
                    ->where('log_type', UserLogEnum::LOGGED_IN)
                    ->orderByDesc('created_at')->orderByDesc('id')
                    ->offset($this->detailOffset())->limit(50)
                    ->get();

                return [
                    'title' => __('admin.dashboard.dynamic.login_detail'),
                    'description' => __('admin.dashboard.dynamic.login_description', ['period' => $dateLabel]),
                    'summary' => $this->formatLoginSummary((int) $context['loginCount'], (int) $context['uniqueLoginUserCount']),
                    'columns' => [__('admin.dashboard.user'), __('admin.dashboard.dynamic.record'), __('admin.dashboard.dynamic.operator'), __('admin.dashboard.dynamic.time')],
                    'rows' => $logs->map(function (CmsUserLog $log) {
                        $user = $log->user;
                        $operator = $log->operator;

                        return [
                            'cells' => [
                                $this->detailCell(optional($user)->full_name ?: optional($user)->username ?: __('admin.dashboard.dynamic.no_user'), $this->userReference($user)),
                                $this->detailCell($log->title ?: __('admin.dashboard.dynamic.successful_login'), $log->description ?: 'logged_in'),
                                $this->detailCell(optional($operator)->full_name ?: optional($operator)->username ?: __('admin.dashboard.dynamic.system'), $operator ? $this->userReference($operator) : __('admin.dashboard.dynamic.operator_unassigned')),
                                $this->detailCell($this->formatDashboardDateTime($log->created_at), 'Log ID: ' . $log->id),
                            ],
                        ];
                    })->all(),
                    'empty' => __('admin.dashboard.dynamic.no_logins'),
                    'pagination' => $this->detailPagination((int) $context['loginCount']),
                ];

        }
        return null;
    }

    private function detailOffset(): int
    {
        return (max(1, (int) ($this->period['query']['detail_page'] ?? 1)) - 1) * 50;
    }

    private function detailPagination(int $total): array
    {
        $page = max(1, (int) ($this->period['query']['detail_page'] ?? 1));
        return [
            'page' => $page, 'total' => $total,
            'previous' => $page > 1 ? $this->contextLink(['detail_page' => $page - 1]).'#metric-detail' : null,
            'next' => $page * 50 < $total ? $this->contextLink(['detail_page' => $page + 1]).'#metric-detail' : null,
        ];
    }

    private function detailCell(?string $primary, ?string $secondary = null): array
    {
        return [
            'primary' => $primary ?: '-',
            'secondary' => $secondary,
        ];
    }

    private function userReference(?CmsUser $user): string
    {
        if (!$user) {
            return __('admin.dashboard.dynamic.no_user_reference');
        }

        if ($user->username) {
            return '@' . $user->username;
        }

        return 'ID: ' . $user->id;
    }

    private function formatDashboardDateTime($value): string
    {
        if (!$value) {
            return '-';
        }

        return Carbon::parse($value)->format('d.m.Y H:i');
    }

    private function formatLoginSummary(int $loginCount, int $uniqueLoginUserCount): string
    {
        $loginCountLabel = __('admin.dashboard.dynamic.login_records', ['count' => number_format($loginCount)]);

        if ($loginCount <= 1) {
            return $loginCountLabel;
        }

        if ($uniqueLoginUserCount === 1) {
            return __('admin.dashboard.dynamic.one_user_login_records', ['records' => $loginCountLabel]);
        }

        if ($uniqueLoginUserCount === 0) {
            return $loginCountLabel;
        }

        if ($uniqueLoginUserCount < $loginCount) {
            return __('admin.dashboard.dynamic.user_login_records', [
                'users' => number_format($uniqueLoginUserCount),
                'records' => $loginCountLabel,
            ]);
        }

        return $loginCountLabel;
    }

    private function formatLoginMetricSubvalue(int $loginCount, int $uniqueLoginUserCount): string
    {
        $userCount = $uniqueLoginUserCount > 0 ? $uniqueLoginUserCount : $loginCount;

        return __('admin.dashboard.metrics.logins_note', [
            'users' => number_format($userCount),
            'records' => number_format($loginCount),
        ]);
    }

    /** @return array{count: ?int, guests: Collection} */

}
