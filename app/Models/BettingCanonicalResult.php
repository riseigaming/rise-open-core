<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BettingCanonicalResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'betting_feed_id',
        'betting_raw_feed_id',
        'betting_event_id',
        'source_event_key',
        'source_sport_key',
        'mapping_status',
        'supported_market_count',
        'unsupported_market_count',
        'supported_outcome_count',
        'unsupported_outcome_count',
        'canonical_payload',
        'raw_snapshot',
        'warnings',
        'errors',
        'error_message',
        'mapped_at',
    ];

    protected $casts = [
        'canonical_payload' => 'array',
        'raw_snapshot' => 'array',
        'warnings' => 'array',
        'errors' => 'array',
        'mapped_at' => 'datetime',
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
}
