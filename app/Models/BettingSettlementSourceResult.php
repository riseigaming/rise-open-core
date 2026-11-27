<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BettingSettlementSourceResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'betting_settlement_id',
        'replaces_source_result_id',
        'source_adapter_key',
        'ingest_origin',
        'source_result_key',
        'source_event_ref',
        'source_market_ref',
        'source_outcome_ref',
        'candidate_result_code',
        'candidate_status',
        'normalization_status',
        'normalization_reason',
        'precedence_outcome',
        'is_correction',
        'received_at',
        'processed_at',
        'superseded_at',
        'source_checksum',
        'source_payload',
        'meta',
    ];

    protected $casts = [
        'is_correction' => 'boolean',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
        'superseded_at' => 'datetime',
        'source_payload' => 'array',
        'meta' => 'array',
    ];

    public function settlement()
    {
        return $this->belongsTo(BettingSettlement::class, 'betting_settlement_id');
    }

    public function replacedSourceResult()
    {
        return $this->belongsTo(self::class, 'replaces_source_result_id');
    }

    public function walletEffects()
    {
        return $this->hasMany(BettingSettlementWalletEffect::class, 'betting_settlement_source_result_id');
    }
}
