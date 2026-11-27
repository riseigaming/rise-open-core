<?php

namespace App\Models;

use App\Services\VirtualShowcase\VirtualShowcaseMoneyFlowGuard;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BettingSettlementWalletEffect extends Model
{
    use HasFactory;

    protected $fillable = [
        'effect_ref',
        'betting_settlement_id',
        'betting_settlement_source_result_id',
        'cms_user_id',
        'cms_user_transaction_id',
        'reversed_wallet_effect_id',
        'reversed_by_wallet_effect_id',
        'effect_phase',
        'effect_family',
        'result_code',
        'direction',
        'amount',
        'currency',
        'applied_at',
        'reversed_at',
        'meta',
    ];

    protected $casts = [
        'amount' => 'decimal:6',
        'applied_at' => 'datetime',
        'reversed_at' => 'datetime',
        'meta' => 'array',
    ];

    public function settlement()
    {
        return $this->belongsTo(BettingSettlement::class, 'betting_settlement_id');
    }

    public function sourceResult()
    {
        return $this->belongsTo(BettingSettlementSourceResult::class, 'betting_settlement_source_result_id');
    }

    public function transaction()
    {
        return $this->belongsTo(CmsUserTransaction::class, 'cms_user_transaction_id');
    }

    public function reversedWalletEffect()
    {
        return $this->belongsTo(self::class, 'reversed_wallet_effect_id');
    }

    public function reversedByWalletEffect()
    {
        return $this->belongsTo(self::class, 'reversed_by_wallet_effect_id');
    }

    protected static function booted(): void
    {
        static::creating(function (self $effect): void {
            $guard = app(VirtualShowcaseMoneyFlowGuard::class);
            $guard->assertUserIdAllowed((int) $effect->cms_user_id, 'betting_settlement_wallet_effect_create');
            $guard->assertBettingSettlementIdAllowed(
                (int) $effect->betting_settlement_id,
                'betting_settlement_wallet_effect_create'
            );
        });
    }
}
