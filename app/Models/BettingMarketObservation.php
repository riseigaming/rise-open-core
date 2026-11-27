<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BettingMarketObservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'betting_feed_id',
        'betting_raw_feed_id',
        'betting_event_observation_id',
        'betting_event_id',
        'adapter_key',
        'source_name',
        'source_event_key',
        'source_market_key',
        'canonical_market_family',
        'canonical_market_key',
        'specifier_key',
        'display_name',
        'line',
        'line_unit',
        'market_status',
        'market_suspended',
        'source_observed_at',
        'source_freshness',
        'source_confidence',
        'source_priority',
        'raw_payload_ref',
        'payload_digest',
        'raw_payload',
        'meta',
    ];

    protected $casts = [
        'line' => 'float',
        'market_suspended' => 'boolean',
        'source_observed_at' => 'datetime',
        'source_freshness' => 'integer',
        'source_confidence' => 'float',
        'source_priority' => 'integer',
        'raw_payload' => 'array',
        'meta' => 'array',
    ];

    public function feed()
    {
        return $this->belongsTo(BettingFeed::class, 'betting_feed_id');
    }

    public function rawFeed()
    {
        return $this->belongsTo(BettingRawFeed::class, 'betting_raw_feed_id');
    }

    public function eventObservation()
    {
        return $this->belongsTo(BettingEventObservation::class, 'betting_event_observation_id');
    }

    public function event()
    {
        return $this->belongsTo(BettingEvent::class, 'betting_event_id');
    }

    public function outcomeObservations()
    {
        return $this->hasMany(BettingOutcomeObservation::class, 'betting_market_observation_id');
    }
}
