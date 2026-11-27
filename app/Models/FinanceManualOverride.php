<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinanceManualOverride extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'operation_id',
        'actor_admin_id',
        'cms_user_id',
        'cms_user_transaction_id',
        'transaction_reference',
        'before_status',
        'after_status',
        'external_resolution',
        'economic_action',
        'wallet_account_id',
        'wallet_type',
        'asset',
        'amount',
        'balance_before',
        'balance_after',
        'reason',
        'evidence_reference',
        'manual_override',
        'request_fingerprint',
        'meta',
    ];

    protected $casts = [
        'amount' => 'decimal:8',
        'balance_before' => 'decimal:8',
        'balance_after' => 'decimal:8',
        'manual_override' => 'boolean',
        'meta' => 'array',
    ];

    public function actor()
    {
        return $this->belongsTo(CmsUser::class, 'actor_admin_id');
    }

    public function customer()
    {
        return $this->belongsTo(CmsUser::class, 'cms_user_id');
    }

    public function transaction()
    {
        return $this->belongsTo(CmsUserTransaction::class, 'cms_user_transaction_id');
    }

    public function walletAccount()
    {
        return $this->belongsTo(WalletAccount::class, 'wallet_account_id');
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Finance manual override audit records are immutable.'));
        static::deleting(fn () => throw new \LogicException('Finance manual override audit records are immutable.'));
    }
}
