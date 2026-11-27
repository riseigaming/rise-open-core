<?php

namespace App\Models;

use App\Services\VirtualShowcase\VirtualShowcaseMoneyFlowGuard;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BettingSettlement extends Model
{
    use HasFactory;

    protected $fillable = [
        'settlement_ref',
        'betting_acceptance_id',
        'reviewed_by_cms_user_id',
        'current_source_result_id',
        'status',
        'decision_source',
        'precedence_state',
        'manual_override_active',
        'result_code',
        'return_amount',
        'profit_amount',
        'settled_at',
        'reviewed_at',
        'source_reconciled_at',
        'reason',
        'review_notes',
        'source_conflict_reason',
        'context',
        'override_meta',
    ];

    protected $casts = [
        'manual_override_active' => 'boolean',
        'return_amount' => 'decimal:6',
        'profit_amount' => 'decimal:6',
        'settled_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'source_reconciled_at' => 'datetime',
        'context' => 'array',
        'override_meta' => 'array',
    ];

    public function acceptance()
    {
        return $this->belongsTo(BettingAcceptance::class, 'betting_acceptance_id');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(CmsUser::class, 'reviewed_by_cms_user_id');
    }

    public function currentSourceResult()
    {
        return $this->belongsTo(BettingSettlementSourceResult::class, 'current_source_result_id');
    }

    public function sourceResults()
    {
        return $this->hasMany(BettingSettlementSourceResult::class, 'betting_settlement_id');
    }

    public function sourceIngests()
    {
        return $this->hasMany(BettingSettlementSourceIngest::class, 'betting_settlement_id');
    }

    public function walletEffects()
    {
        return $this->hasMany(BettingSettlementWalletEffect::class, 'betting_settlement_id');
    }

    public function activityLogs()
    {
        return $this->hasMany(BettingActivityLog::class, 'betting_settlement_id');
    }

    protected static function booted(): void
    {
        static::creating(function (self $settlement): void {
            app(VirtualShowcaseMoneyFlowGuard::class)->assertBettingAcceptanceIdAllowed(
                (int) $settlement->betting_acceptance_id,
                'betting_settlement_create'
            );
        });
    }
}
