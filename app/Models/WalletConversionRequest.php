<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WalletConversionRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'cms_user_id',
        'source_wallet_account_id',
        'target_wallet_account_id',
        'source_asset',
        'target_asset',
        'requested_amount',
        'quoted_rate',
        'fee_percentage',
        'fee_amount',
        'receive_amount',
        'quote_expires_at',
        'status',
        'idempotency_key',
        'completed_at',
        'failed_at',
        'meta',
    ];

    protected $casts = [
        'requested_amount' => 'decimal:8',
        'quoted_rate' => 'decimal:8',
        'fee_percentage' => 'decimal:4',
        'fee_amount' => 'decimal:8',
        'receive_amount' => 'decimal:8',
        'quote_expires_at' => 'datetime',
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
