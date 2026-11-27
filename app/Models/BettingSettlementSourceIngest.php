<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BettingSettlementSourceIngest extends Model
{
    use HasFactory;

    protected $fillable = [
        'ingest_ref',
        'adapter_key',
        'ingest_origin',
        'payload_checksum',
        'ingest_status',
        'last_action',
        'matching_confidence',
        'resolution_reason',
        'resolution_source',
        'source_result_key',
        'source_event_ref',
        'source_market_ref',
        'source_outcome_ref',
        'input_settlement_ref',
        'input_acceptance_ref',
        'bound_settlement_ref',
        'bound_acceptance_ref',
        'betting_settlement_id',
        'betting_acceptance_id',
        'betting_settlement_source_result_id',
        'normalization_status',
        'normalization_reason',
        'retry_count',
        'replay_count',
        'first_received_at',
        'last_received_at',
        'last_retried_at',
        'resolved_at',
        'ignored_at',
        'raw_payload',
        'meta',
    ];

    protected $casts = [
        'retry_count' => 'integer',
        'replay_count' => 'integer',
        'first_received_at' => 'datetime',
        'last_received_at' => 'datetime',
        'last_retried_at' => 'datetime',
        'resolved_at' => 'datetime',
        'ignored_at' => 'datetime',
        'raw_payload' => 'array',
        'meta' => 'array',
    ];

    public function settlement()
    {
        return $this->belongsTo(BettingSettlement::class, 'betting_settlement_id');
    }

    public function acceptance()
    {
        return $this->belongsTo(BettingAcceptance::class, 'betting_acceptance_id');
    }

    public function sourceResult()
    {
        return $this->belongsTo(BettingSettlementSourceResult::class, 'betting_settlement_source_result_id');
    }

    public function activityLogs()
    {
        return $this->hasMany(BettingActivityLog::class, 'entity_id')->where('entity_type', 'settlement_source_ingest');
    }
}
