<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BettingCanonicalPublishSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'betting_feed_id',
        'betting_event_id',
        'canonical_event_key',
        'publish_version_no',
        'publish_status',
        'publish_digest',
        'source_count',
        'market_family_count',
        'selection_count',
        'compiled_at',
        'published_at',
        'canonical_payload',
        'source_provenance',
        'market_provenance',
        'arbitration_summary',
        'coverage_summary',
        'meta',
    ];

    protected $casts = [
        'compiled_at' => 'datetime',
        'published_at' => 'datetime',
        'canonical_payload' => 'array',
        'source_provenance' => 'array',
        'market_provenance' => 'array',
        'arbitration_summary' => 'array',
        'coverage_summary' => 'array',
        'meta' => 'array',
    ];

    public function feed()
    {
        return $this->belongsTo(BettingFeed::class, 'betting_feed_id');
    }

    public function event()
    {
        return $this->belongsTo(BettingEvent::class, 'betting_event_id');
    }
}
