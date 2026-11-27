<?php

namespace App\Models;

use App\Services\VirtualShowcase\VirtualShowcaseMoneyFlowGuard;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WalletAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'cms_user_id',
        'wallet_type',
        'wallet_key',
        'asset',
        'display_name',
        'available_balance',
        'pending_balance',
        'status',
        'last_synced_at',
        'meta',
    ];

    protected $casts = [
        'available_balance' => 'decimal:8',
        'pending_balance' => 'decimal:8',
        'last_synced_at' => 'datetime',
        'meta' => 'array',
    ];

    public function owner()
    {
        return $this->belongsTo(CmsUser::class, 'cms_user_id');
    }

    public function ledgerEntries()
    {
        return $this->hasMany(WalletLedgerEntry::class, 'wallet_account_id');
    }

    protected static function booted(): void
    {
        static::creating(function (self $wallet): void {
            app(VirtualShowcaseMoneyFlowGuard::class)->assertUserIdAllowed(
                (int) $wallet->cms_user_id,
                'wallet_account_create'
            );
        });
    }
}
