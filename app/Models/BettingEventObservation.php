<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BettingEventObservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'betting_feed_id',
        'betting_raw_feed_id',
        'betting_event_id',
        'adapter_key',
        'source_name',
        'source_event_key',
        'canonical_event_key',
        'raw_payload_ref',
        'sport_code',
        'region_code',
        'region_name',
        'competition_key',
        'competition_name',
        'participant_home',
        'participant_away',
        'normalized_participant_home',
        'normalized_participant_away',
        'scheduled_start_at',
        'source_observed_at',
        'source_freshness',
        'source_confidence',
        'source_priority',
        'is_live',
        'payload_digest',
        'raw_payload',
        'meta',
    ];

    protected $casts = [
        'scheduled_start_at' => 'datetime',
        'source_observed_at' => 'datetime',
        'source_freshness' => 'integer',
        'source_confidence' => 'float',
        'source_priority' => 'integer',
        'is_live' => 'boolean',
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

    public function event()
    {
        return $this->belongsTo(BettingEvent::class, 'betting_event_id');
    }

    public function marketObservations()
    {
        return $this->hasMany(BettingMarketObservation::class, 'betting_event_observation_id');
    }
}
