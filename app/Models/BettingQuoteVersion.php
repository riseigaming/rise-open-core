<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BettingQuoteVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'betting_event_id',
        'betting_feed_id',
        'betting_canonical_result_id',
        'replaces_quote_version_id',
        'origin_raw_feed_id',
        'version_uuid',
        'version_no',
        'quote_status',
        'generation_mode',
        'trading_state',
        'manual_action',
        'manual_reason',
        'published_by_cms_user_id',
        'published_at',
        'selection_count',
        'compiled_at',
        'effective_from',
        'effective_until',
        'trader_note',
        'payload_digest',
        'meta',
        'override_meta',
    ];

    protected $casts = [
        'compiled_at' => 'datetime',
        'effective_from' => 'datetime',
        'effective_until' => 'datetime',
        'published_at' => 'datetime',
        'meta' => 'array',
        'override_meta' => 'array',
    ];

    public function event()
    {
        return $this->belongsTo(BettingEvent::class, 'betting_event_id');
    }

    public function feed()
    {
        return $this->belongsTo(BettingFeed::class, 'betting_feed_id');
    }

    public function canonicalResult()
    {
        return $this->belongsTo(BettingCanonicalResult::class, 'betting_canonical_result_id');
    }

    public function rawFeed()
    {
        return $this->belongsTo(BettingRawFeed::class, 'origin_raw_feed_id');
    }

    public function replacedVersion()
    {
        return $this->belongsTo(self::class, 'replaces_quote_version_id');
    }

    public function publishedBy()
    {
        return $this->belongsTo(CmsUser::class, 'published_by_cms_user_id');
    }

    public function selections()
    {
        return $this->hasMany(BettingQuoteSelection::class);
    }

    public function acceptances()
    {
        return $this->hasMany(BettingAcceptance::class);
    }
}
