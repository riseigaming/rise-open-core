<?php

namespace App\Models;

use App\Services\VirtualShowcase\VirtualShowcaseMoneyFlowGuard;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WalletLedgerEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'wallet_account_id',
        'cms_user_id',
        'entry_type',
        'direction',
        'status',
        'amount',
        'balance_before',
        'balance_after',
        'asset',
        'source_type',
        'source_reference',
        'idempotency_key',
        'applied_at',
        'meta',
    ];

    protected $casts = [
        'amount' => 'decimal:8',
        'balance_before' => 'decimal:8',
        'balance_after' => 'decimal:8',
        'applied_at' => 'datetime',
        'meta' => 'array',
    ];

    public function walletAccount()
    {
        return $this->belongsTo(WalletAccount::class, 'wallet_account_id');
    }

    public function owner()
    {
        return $this->belongsTo(CmsUser::class, 'cms_user_id');
    }

    protected static function booted(): void
    {
        static::creating(function (self $entry): void {
            $guard = app(VirtualShowcaseMoneyFlowGuard::class);
            $guard->assertUserIdAllowed((int) $entry->cms_user_id, 'wallet_ledger_entry_create');
            $guard->assertWalletAccountIdAllowed((int) $entry->wallet_account_id, 'wallet_ledger_entry_create');
        });
    }
}
