<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WalletTransferRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'cms_user_id',
        'source_wallet_account_id',
        'target_wallet_account_id',
        'asset',
        'requested_amount',
        'status',
        'idempotency_key',
        'completed_at',
        'failed_at',
        'meta',
    ];

    protected $casts = [
        'requested_amount' => 'decimal:8',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
        'meta' => 'array',
    ];

    public function owner()
    {
        return $this->belongsTo(CmsUser::class, 'cms_user_id');
    }

    public function sourceWallet()
    {
        return $this->belongsTo(WalletAccount::class, 'source_wallet_account_id');
    }

    public function targetWallet()
    {
        return $this->belongsTo(WalletAccount::class, 'target_wallet_account_id');
    }
}
