<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BettingOutcomeObservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'betting_feed_id',
        'betting_raw_feed_id',
        'betting_event_observation_id',
        'betting_market_observation_id',
        'betting_event_id',
        'betting_market_id',
        'betting_outcome_id',
        'adapter_key',
        'source_name',
        'source_event_key',
        'source_market_key',
        'source_outcome_key',
        'canonical_market_key',
        'canonical_outcome_key',
        'label',
        'decimal_price',
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
        'decimal_price' => 'float',
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

    public function marketObservation()
    {
        return $this->belongsTo(BettingMarketObservation::class, 'betting_market_observation_id');
    }

    public function event()
    {
        return $this->belongsTo(BettingEvent::class, 'betting_event_id');
    }

    public function market()
    {
        return $this->belongsTo(BettingMarket::class, 'betting_market_id');
    }

    public function outcome()
    {
        return $this->belongsTo(BettingOutcome::class, 'betting_outcome_id');
    }
}
