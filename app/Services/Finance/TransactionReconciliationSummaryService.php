<?php

namespace App\Services\Finance;

use App\Models\CmsUserTransaction;
use App\Support\Money\CurrencyPrecision;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Bounded read-only port of WinX S0 1c151f21af58c1cc738f67f9e7a10953e6a6e6a2,
 * app/Services/Finance/TransactionReconciliationSummaryService.php.
 * Source SHA256: b89e949a01bcd4f6bb63c0f1e0e6e71c52924f7f70f17ab18d208180254f738c.
 *
 * Only transaction snapshot/category aggregation is admitted. Caller supplies an
 * authorized owner/provider-scoped CmsUserTransaction query; scope labels are
 * descriptive, never authorization. No lazy relations, repairs or ledger queries.
 * Exact coverage measures snapshot presence, NOT ledger validity or applied funds.
 * Legacy totals and action-derived cashflow are unavailable: status, canonical
 * owner, payout/refund and provider ledger evidence cannot be inferred here.
 *
 * Future cutover requires the scoped read-only precision inspector and DB tests
 * on an enrolled disposable target; conversions additionally need their own
 * authorized owner/provider query and executor/evidence contract. No activation
 * or routes. Basic read models retain Base direction; advanced reconciliation
 * remains Full direction with final entitlements pending (PRODUCT_PORTFOLIO.md).
 */
class TransactionReconciliationSummaryService
{
    public function summarize(Builder $baseQuery, array $filters = []): array
    {
        if (!$baseQuery->getModel() instanceof CmsUserTransaction) {
            throw new \InvalidArgumentException('A CmsUserTransaction query is required.');
        }
        // Joined rows can multiply monetary totals; only the canonical table is admitted.
        $sql = $baseQuery->getQuery();
        if ($sql->from !== 'cms_user_transactions' || $sql->joins || $sql->groups || $sql->unions) {
            throw new \InvalidArgumentException('A non-joined canonical transaction query is required.');
        }

        foreach (['period_start', 'period_end'] as $boundary) {
            if (isset($filters[$boundary]) && !$filters[$boundary] instanceof CarbonInterface) {
                throw new \InvalidArgumentException('Period boundaries must be Carbon dates.');
            }
        }
        if (isset($filters['period_start'], $filters['period_end'])
            && $filters['period_start']->greaterThan($filters['period_end'])) {
            throw new \InvalidArgumentException('Period start must not exceed period end.');
        }

        $state = [
            'scanned_count' => 0,
            'exact_available_count' => 0,
            'recoverable_count' => null,
            'legacy_only_count' => null,
            'unclassified_count' => 0,
            'repaired_count' => 0,
            'ambiguous_count' => null,
            'failed_count' => null,
            'blocks' => [],
        ];

        $query = (clone $baseQuery)
            ->select('cms_user_transactions.*')
            ->reorder('cms_user_transactions.id');

        if (($filters['period_start'] ?? null) instanceof CarbonInterface) {
            $query->where('cms_user_transactions.created_at', '>=', $filters['period_start']);
        }

        if (($filters['period_end'] ?? null) instanceof CarbonInterface) {
            $query->where('cms_user_transactions.created_at', '<=', $filters['period_end']);
        }

        $query->chunkById(200, function ($transactions) use (&$state) {
            foreach ($transactions as $transaction) {
                if (!$transaction instanceof CmsUserTransaction) {
                    throw new \UnexpectedValueException('Expected a canonical transaction row.');
                }

                $this->ingestTransaction($state, $transaction);
            }
        }, 'cms_user_transactions.id', 'id');

        $blocks = collect($state['blocks'])
            ->map(fn (array $block) => $this->finalizeBlock($block))
            ->sortBy('asset_or_currency')
            ->values()
            ->all();

        $trackedTotal = $state['scanned_count'];
        $coverage = $trackedTotal > 0
            ? round(($state['exact_available_count'] / $trackedTotal) * 100, 2)
            : null;

        return [
            'status' => 'partial',
            'basis' => 'transaction_snapshots_not_ledger_reconciliation',
            'unsupported' => ['precision_repair_inspection', 'legacy_asset_totals', 'ledger_cashflow', 'conversions'],
            'period_start' => $this->formatPeriodBoundary($filters['period_start'] ?? null),
            'period_end' => $this->formatPeriodBoundary($filters['period_end'] ?? null),
            'product_scope' => (string) ($filters['product_scope'] ?? 'transactions'),
            'transaction_scope' => (string) ($filters['transaction_scope'] ?? 'all'),
            'overview' => [
                'scanned_count' => $state['scanned_count'],
                'exact_available_count' => $state['exact_available_count'],
                'recoverable_count' => $state['recoverable_count'],
                'legacy_only_count' => $state['legacy_only_count'],
                'unclassified_count' => $state['unclassified_count'],
                'repaired_count' => $state['repaired_count'],
                'ambiguous_count' => $state['ambiguous_count'],
                'failed_count' => $state['failed_count'],
                'exact_coverage_rate' => $coverage,
            ],
            'transaction_blocks' => $blocks,
            'conversion_blocks' => null,
        ];
    }

    private function ingestTransaction(array &$state, CmsUserTransaction $transaction): void
    {
        $asset = $transaction->transactionAssetCode();
        $scale = $transaction->transactionPrecisionScale();
        $blockKey = $asset . ':' . $scale;
        $exactAvailable = $transaction->hasExactPrecisionSnapshot();
        $exactAmount = $transaction->exactAmountValue();
        $category = $this->categoryForTransaction($transaction);

        if (!isset($state['blocks'][$blockKey])) {
            $state['blocks'][$blockKey] = $this->makeEmptyBlock($asset, $scale);
        }

        $block = &$state['blocks'][$blockKey];
        $row = &$block['rows'][$category];

        $state['scanned_count']++;
        $block['record_count']++;
        $row['record_count']++;
        if ($exactAvailable && $exactAmount !== null) {
            $state['exact_available_count']++;
            $block['exact_record_count']++;
            $row['exact_record_count']++;
            $row['exact_minor'] = $this->addMinorStrings(
                $row['exact_minor'],
                $this->minorUnits($exactAmount, $scale)
            );

            if ($transaction->exact_precision_backfilled_at !== null) {
                $block['repaired_count']++;
                $row['repaired_count']++;
                $state['repaired_count']++;
            }

            return;
        }

        // No repair service exists in this Rise checkpoint. Missing exact data is
        // unknown, not proven legacy-only, recoverable, ambiguous or zero.
        $state['unclassified_count']++;
        $block['unclassified_count']++;
        $row['unclassified_count']++;
    }

    private function finalizeBlock(array $block): array
    {
        $rows = collect($block['rows'])
            ->filter(fn (array $row) => $row['record_count'] > 0)
            ->map(function (array $row) use ($block) {
                return [
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'record_count' => $row['record_count'],
                    'exact_record_count' => $row['exact_record_count'],
                    'legacy_only_count' => $row['legacy_only_count'],
                    'recoverable_count' => $row['recoverable_count'],
                    'repaired_count' => $row['repaired_count'],
                    'ambiguous_count' => $row['ambiguous_count'],
                    'unclassified_count' => $row['unclassified_count'],
                    'exact_total' => $row['exact_record_count'] > 0
                        ? $this->formatMinorUnits($row['exact_minor'], $block['precision_scale']) : null,
                    'summary_total' => null,
                    'display_total' => $row['exact_record_count'] === $row['record_count']
                        ? $this->formatMinorUnits($row['exact_minor'], $block['precision_scale']) : null,
                ];
            })
            ->values()
            ->all();

        return [
            'asset_or_currency' => $block['asset_or_currency'],
            'precision_scale' => $block['precision_scale'],
            'record_count' => $block['record_count'],
            'exact_record_count' => $block['exact_record_count'],
            'legacy_only_count' => $block['legacy_only_count'],
            'recoverable_count' => $block['recoverable_count'],
            'repaired_count' => $block['repaired_count'],
            'ambiguous_count' => $block['ambiguous_count'],
            'unclassified_count' => $block['unclassified_count'],
            'coverage_rate' => $block['record_count'] > 0
                ? round(($block['exact_record_count'] / $block['record_count']) * 100, 2) : null,
            'exact_inflow_total' => null,
            'exact_outflow_total' => null,
            'exact_net_total' => null,
            'summary_inflow_total' => null,
            'summary_outflow_total' => null,
            'summary_net_total' => null,
            'rows' => $rows,
        ];
    }

    private function makeEmptyBlock(string $asset, int $scale): array
    {
        return [
            'asset_or_currency' => $asset,
            'precision_scale' => $scale,
            'record_count' => 0,
            'exact_record_count' => 0,
            'legacy_only_count' => null,
            'recoverable_count' => null,
            'unclassified_count' => 0,
            'repaired_count' => 0,
            'ambiguous_count' => null,
            'rows' => collect($this->categoryDefinitions())
                ->mapWithKeys(fn (string $label, string $key) => [$key => [
                    'key' => $key,
                    'label' => $label,
                    'record_count' => 0,
                    'exact_record_count' => 0,
                    'legacy_only_count' => null,
                    'recoverable_count' => null,
                    'unclassified_count' => 0,
                    'repaired_count' => 0,
                    'ambiguous_count' => null,
                    'exact_minor' => '0',
                ]])
                ->all(),
        ];
    }

    private function categoryDefinitions(): array
    {
        return [
            'deposits' => 'admin.finance_reconciliation.categories.deposits',
            'payouts' => 'admin.finance_reconciliation.categories.payouts',
            'gameplay_debits' => 'admin.finance_reconciliation.categories.gameplay_debits',
            'gameplay_credits' => 'admin.finance_reconciliation.categories.gameplay_credits',
            'gameplay_refunds' => 'admin.finance_reconciliation.categories.gameplay_refunds',
            'transfers' => 'admin.finance_reconciliation.categories.transfers',
            'adjustments' => 'admin.finance_reconciliation.categories.adjustments',
            'other' => 'admin.finance_reconciliation.categories.other',
        ];
    }

    private function categoryForTransaction(CmsUserTransaction $transaction): string
    {
        return match ((int) $transaction->action) {
            1 => 'deposits',
            2 => 'payouts',
            4 => 'gameplay_debits',
            5 => 'gameplay_credits',
            6 => 'gameplay_refunds',
            8 => 'transfers',
            7, 9, 10 => 'adjustments',
            default => 'other',
        };
    }

    private function formatPeriodBoundary(mixed $value): ?string
    {
        return $value instanceof CarbonInterface ? $value->toDateTimeString() : null;
    }

    private function minorUnits(string $value, int $scale): string
    {
        $normalized = CurrencyPrecision::format($value, $scale);
        $negative = str_starts_with($normalized, '-');
        $digits = str_replace('.', '', ltrim($normalized, '-'));
        $digits = ltrim($digits, '0');
        if ($digits === '') {
            return '0';
        }

        return $negative ? '-' . $digits : $digits;
    }

    private function formatMinorUnits(string $minor, int $scale): string
    {
        $negative = str_starts_with($minor, '-');
        $digits = ltrim($minor, '-');
        $digits = ltrim($digits, '0');

        if ($digits === '') {
            $digits = '0';
        }

        if ($scale === 0) {
            return ($negative ? '-' : '') . $digits;
        }

        $digits = str_pad($digits, $scale + 1, '0', STR_PAD_LEFT);
        $whole = substr($digits, 0, -$scale);
        $fraction = substr($digits, -$scale);

        return ($negative ? '-' : '') . $whole . '.' . $fraction;
    }

    private function addMinorStrings(string $left, string $right): string
    {
        $leftNegative = $this->isNegativeMinor($left);
        $rightNegative = $this->isNegativeMinor($right);
        $leftDigits = ltrim($this->absoluteMinor($left), '0') ?: '0';
        $rightDigits = ltrim($this->absoluteMinor($right), '0') ?: '0';

        if ($leftNegative === $rightNegative) {
            $sum = $this->addUnsignedStrings($leftDigits, $rightDigits);

            return $leftNegative && $sum !== '0' ? '-' . $sum : $sum;
        }

        $comparison = $this->compareUnsignedStrings($leftDigits, $rightDigits);
        if ($comparison === 0) {
            return '0';
        }

        if ($comparison > 0) {
            $difference = $this->subtractUnsignedStrings($leftDigits, $rightDigits);

            return $leftNegative ? '-' . $difference : $difference;
        }

        $difference = $this->subtractUnsignedStrings($rightDigits, $leftDigits);

        return $rightNegative ? '-' . $difference : $difference;
    }

    private function addUnsignedStrings(string $left, string $right): string
    {
        $carry = 0;
        $result = '';
        $left = strrev($left);
        $right = strrev($right);
        $length = max(strlen($left), strlen($right));

        for ($index = 0; $index < $length; $index++) {
            $digit = (int) ($left[$index] ?? 0) + (int) ($right[$index] ?? 0) + $carry;
            $result .= (string) ($digit % 10);
            $carry = intdiv($digit, 10);
        }

        if ($carry > 0) {
            $result .= (string) $carry;
        }

        return strrev($result);
    }

    private function subtractUnsignedStrings(string $left, string $right): string
    {
        $borrow = 0;
        $result = '';
        $left = strrev($left);
        $right = strrev($right);
        $length = strlen($left);

        for ($index = 0; $index < $length; $index++) {
            $digit = (int) $left[$index] - (int) ($right[$index] ?? 0) - $borrow;
            if ($digit < 0) {
                $digit += 10;
                $borrow = 1;
            } else {
                $borrow = 0;
            }

            $result .= (string) $digit;
        }

        return ltrim(strrev($result), '0') ?: '0';
    }

    private function compareUnsignedStrings(string $left, string $right): int
    {
        $left = ltrim($left, '0') ?: '0';
        $right = ltrim($right, '0') ?: '0';

        if (strlen($left) !== strlen($right)) {
            return strlen($left) <=> strlen($right);
        }

        return strcmp($left, $right) <=> 0;
    }

    private function absoluteMinor(string $minor): string
    {
        return ltrim($minor, '-');
    }

    private function isNegativeMinor(string $minor): bool
    {
        return str_starts_with($minor, '-') && $this->absoluteMinor($minor) !== '0';
    }
}
