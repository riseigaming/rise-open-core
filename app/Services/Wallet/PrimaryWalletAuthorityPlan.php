<?php

namespace App\Services\Wallet;

use App\Models\WalletAccount;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Read-only reconciliation evidence. No model callbacks, money writes or inferred FX. */
final class PrimaryWalletAuthorityPlan
{
    public function forWallet(WalletAccount $wallet): array
    {
        $owner = DB::table('cms_users')->where('id', $wallet->cms_user_id)
            ->first(['id', 'parent_id', 'currency', 'balance', 'deleted_at']);
        $children = DB::table('cms_users')->where('parent_id', $wallet->cms_user_id)
            ->orderBy('id')->get(['id', 'parent_id', 'currency', 'balance', 'deleted_at']);
        $ledger = DB::table('wallet_ledger_entries')->where('wallet_account_id', $wallet->id)
            ->orderBy('id')->get(['id', 'cms_user_id', 'asset', 'status', 'direction', 'amount',
                'balance_before', 'balance_after', 'idempotency_key']);
        $familyIds = $children->pluck('id')->push((int) $wallet->cms_user_id)->all();
        // Include legacy unbound transactions: their presence forbids guessing a zero.
        $transactionColumns = array_values(array_intersect([
            'id', 'cms_user_id', 'source_wallet_account_id', 'currency', 'source_currency',
            'action', 'status', 'amount', 'before_balance', 'after_balance',
        ], Schema::getColumnListing('cms_user_transactions')));
        $transactions = DB::table('cms_user_transactions')->where(function ($query) use ($wallet, $familyIds, $transactionColumns) {
            if (in_array('source_wallet_account_id', $transactionColumns, true)) {
                $query->where('source_wallet_account_id', $wallet->id)->orWhere(function ($legacy) use ($wallet, $familyIds) {
                    $legacy->whereIn('cms_user_id', $familyIds)->where('currency', $wallet->asset);
                });
            } else {
                $query->whereIn('cms_user_id', $familyIds)->where('currency', $wallet->asset);
            }
        })->orderBy('id')->get($transactionColumns);
        $requests = [];
        foreach (['wallet_conversion_requests', 'wallet_transfer_requests'] as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            $columns = array_values(array_intersect([
                'id', 'cms_user_id', 'source_wallet_account_id', 'target_wallet_account_id',
                'status', 'requested_amount', 'source_amount', 'target_amount', 'created_at', 'updated_at',
            ], Schema::getColumnListing($table)));
            $requests[$table] = DB::table($table)->where('source_wallet_account_id', $wallet->id)
                ->orWhere('target_wallet_account_id', $wallet->id)->orderBy('id')->get($columns)->all();
        }
        $version = (int) data_get($wallet->meta, 'authoritative_balance_version', 0);
        $plan = [
            'wallet_id' => (int) $wallet->id,
            'owner_id' => (int) $wallet->cms_user_id,
            'asset' => (string) $wallet->asset,
            'category' => 'D',
            'reason' => 'unclassified',
            'safe' => false,
            'source' => null,
            'before_version' => $version,
            'after_version' => $version,
            'before_balance' => (string) $wallet->available_balance,
            'after_balance' => null,
            'mirror_effect' => 'none',
            'ledger_count' => $ledger->count(),
            'transaction_count' => $transactions->count(),
            'transfer_conversion_count' => array_sum(array_map('count', $requests)),
            'evidence_sha256' => hash('sha256', json_encode([
                $wallet->getRawOriginal(), $owner, $children->all(), $ledger->all(), $transactions->all(), $requests,
            ], JSON_THROW_ON_ERROR)),
        ];
        $blocked = static fn (string $reason, string $category = 'D'): array => array_replace($plan, [
            'reason' => $reason, 'category' => $category,
        ]);
        if (!$owner) {
            return $blocked('missing_owner', 'C');
        }
        if ($owner->deleted_at !== null) {
            return $blocked('soft_deleted_owner', 'C');
        }
        if ($owner->parent_id !== null) {
            return $blocked('wallet_owned_by_child');
        }
        if ($wallet->wallet_type !== 'primary_balance' || $wallet->wallet_key !== 'default'
            || !in_array($wallet->asset, ['TRY', 'USD', 'EUR'], true)) {
            return $blocked('unsupported_wallet_identity');
        }

        try {
            $row = $this->decimal($wallet->available_balance);
            $pending = $this->decimal($wallet->pending_balance);
            if (!$pending->isZero()) {
                return $blocked('pending_balance_requires_review');
            }
            if ($row->isNegative()) {
                return $blocked('negative_wallet_balance');
            }
            $sameCurrency = $children->filter(fn ($child) => $child->currency === $wallet->asset);
            if ($sameCurrency->contains(fn ($child) => $child->deleted_at !== null)) {
                return $blocked('deleted_legacy_currency_owner');
            }
            $mirrors = $sameCurrency->map(fn ($child) => (string) $this->decimal($child->balance));
            if ($owner->currency === $wallet->asset) {
                $mirrors->push((string) $this->decimal($owner->balance));
            }
            if ($mirrors->contains(fn ($balance) => BigDecimal::of($balance)->isNegative())) {
                return $blocked('negative_legacy_balance');
            }
            if ($mirrors->unique()->count() > 1) {
                return $blocked('legacy_mirror_divergence');
            }

            $last = null;
            foreach ($ledger as $entry) {
                if ($entry->asset !== $wallet->asset || !in_array((int) $entry->cms_user_id, $familyIds, true)) {
                    return $blocked('ledger_identity_mismatch');
                }
                if ($entry->status !== 'applied') {
                    return $blocked('non_applied_ledger_requires_review');
                }
                $before = $this->decimal($entry->balance_before);
                $amount = $this->decimal($entry->amount);
                $after = $this->decimal($entry->balance_after);
                if ($amount->isNegative() || $before->isNegative() || $after->isNegative()) {
                    return $blocked('negative_ledger_value');
                }
                if ($last === null && !$before->isZero() && $mirrors->isEmpty()) {
                    return $blocked('unproven_ledger_opening');
                }
                if ($last !== null && !$last->isEqualTo($before)) {
                    return $blocked('ledger_chain_discontinuity');
                }
                $expected = match ($entry->direction) {
                    'credit' => $before->plus($amount),
                    'debit' => $before->minus($amount),
                    default => null,
                };
                if ($expected === null || !$expected->isEqualTo($after)) {
                    return $blocked('ledger_arithmetic_mismatch');
                }
                $last = $after;
            }

            $mirror = $mirrors->first();
            if ($last !== null && $mirror !== null && !$last->isEqualTo($mirror)) {
                return $blocked('ledger_mirror_disagreement');
            }
            if ($version >= 2) {
                if (($last !== null && !$last->isEqualTo($row)) || ($mirror !== null && !$row->isEqualTo($mirror))) {
                    return $blocked('authoritative_balance_disagreement');
                }
                $source = 'already_authoritative';
                $target = (string) $row;
            } elseif ($mirror !== null) {
                $source = $owner->currency === $wallet->asset ? 'root_currency_compatibility' : 'legacy_currency_owner';
                $target = $mirror;
            } elseif ($last !== null) {
                $source = 'ledger_evidence';
                $target = (string) $last;
            } else {
                if ($transactions->isNotEmpty() || $plan['transfer_conversion_count'] > 0) {
                    return $blocked('transaction_evidence_requires_review');
                }
                if (!$row->isZero() && data_get($wallet->meta, 'reference') !== 'canonical_wallet_projection') {
                    return $blocked('nonzero_without_alias_provenance');
                }
                $source = 'zero_unproven';
                $target = '0.00';
            }
            return array_replace($plan, [
                'category' => $source === 'zero_unproven' ? 'B' : 'A',
                'reason' => $source, 'source' => $source, 'safe' => true,
                'after_version' => max(2, $version), 'after_balance' => $target,
            ]);
        } catch (\Brick\Math\Exception\MathException | \InvalidArgumentException $exception) {
            return $blocked('invalid_decimal_precision');
        }
    }

    private function decimal(string $value): BigDecimal
    {
        return BigDecimal::of($value)->toScale(2, RoundingMode::UNNECESSARY);
    }
}
