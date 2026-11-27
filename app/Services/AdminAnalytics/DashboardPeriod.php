<?php

namespace App\Services\AdminAnalytics;

use Carbon\Carbon;
use Illuminate\Http\Request;

/** One reporting clock and one validated context for totals, details and links. */
final class DashboardPeriod
{
    public static function fromRequest(Request $request): array
    {
        $input = $request->validate([
            'dateRange' => 'nullable|string|in:today,yesterday,last7,last30,thisWeek,thisMonth,previousMonth,custom,all',
            'start' => 'required_if:dateRange,custom|nullable|date_format:Y-m-d',
            'end' => 'required_if:dateRange,custom|nullable|date_format:Y-m-d|after_or_equal:start',
            'compare' => 'nullable|string|in:off,previous',
            'view' => 'nullable|string|in:overview',
            'metric' => 'nullable|string|in:registers,logins',
            'detail_page' => 'nullable|integer|min:1|max:10000',
        ]);
        $now = Carbon::now();
        $key = $input['dateRange'] ?? 'today';
        $end = $now->copy();
        $start = match ($key) {
            'yesterday' => $now->copy()->subDay()->startOfDay(),
            'last7' => $now->copy()->subDays(6)->startOfDay(),
            'last30' => $now->copy()->subDays(29)->startOfDay(),
            'thisWeek' => $now->copy()->startOfWeek(Carbon::MONDAY),
            'thisMonth' => $now->copy()->startOfMonth(),
            'previousMonth' => $now->copy()->startOfMonth()->subMonthNoOverflow(),
            'custom' => Carbon::createFromFormat('!Y-m-d', $input['start'], $now->timezone),
            'all' => null,
            default => $now->copy()->startOfDay(),
        };
        if ($key === 'yesterday') {
            $end = $start->copy()->endOfDay();
        } elseif ($key === 'previousMonth') {
            $end = $start->copy()->endOfMonth();
        } elseif ($key === 'custom') {
            $end = Carbon::createFromFormat('!Y-m-d', $input['end'], $now->timezone)->endOfDay();
            abort_if($start->gt($now) || $end->gt($now->copy()->endOfDay()), 422, __('admin.dashboard.dynamic.future_date'));
            abort_if((int) $start->diffInDays($end, true) > 366, 422, __('admin.dashboard.dynamic.range_too_long'));
            $end = $end->min($now);
        } elseif ($key === 'all') {
            $end = null;
        }

        // Calendar-day shift preserves local wall time across DST boundaries.
        $shift = $start && $end ? (int) $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay(), true) + 1 : 0;
        $shift = match ($key) {
            'thisWeek' => 7,
            'last7' => 7,
            'last30' => 30,
            default => $shift,
        };
        $previousStart = $start?->copy()->subDays($shift);
        $previousEnd = $end?->copy()->subDays($shift);
        if ($key === 'thisMonth') {
            $previousStart = $start->copy()->subMonthNoOverflow();
            $previousEnd = $end->copy()->subMonthNoOverflow();
        } elseif ($key === 'previousMonth') {
            $previousStart = $start->copy()->subMonthNoOverflow()->startOfMonth();
            $previousEnd = $previousStart->copy()->endOfMonth();
        }
        $labels = __('admin.dashboard.date_ranges');
        return [
            'key' => $key, 'label' => $labels[$key], 'start' => $start, 'end' => $end,
            'previous_start' => $previousStart, 'previous_end' => $previousEnd,
            'comparison_enabled' => ($input['compare'] ?? 'off') === 'previous' && $start !== null,
            'window_label' => $start ? $start->format('d.m.Y H:i').' – '.$end->format('d.m.Y H:i') : __('admin.dashboard.all_records_period'),
            'comparison_label' => $previousStart ? $previousStart->format('d.m.Y H:i').' – '.$previousEnd->format('d.m.Y H:i') : null,
            'description' => __('admin.dashboard.dynamic.period_description'),
            'as_of' => $now->toIso8601String(), 'timezone' => $now->timezoneName,
            'view' => $input['view'] ?? 'overview',
            'query' => array_filter($input, static fn ($value) => $value !== null && $value !== ''),
        ];
    }
}
