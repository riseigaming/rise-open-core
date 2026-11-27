<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BettingEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_key',
        'sport_code',
        'lifecycle_status',
        'trading_status',
        'visibility_status',
        'front_betting_enabled',
        'canonical_name',
        'tournament_name',
        'participant_home',
        'participant_away',
        'starts_at',
        'discovered_at',
        'last_source_seen_at',
        'current_quote_version_id',
        'current_publish_snapshot_id',
        'meta',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'discovered_at' => 'datetime',
        'last_source_seen_at' => 'datetime',
        'front_betting_enabled' => 'boolean',
        'meta' => 'array',
    ];

    public function markets()
    {
        return $this->hasMany(BettingMarket::class);
    }

    public function quoteVersions()
    {
        return $this->hasMany(BettingQuoteVersion::class);
    }

    public function currentQuoteVersion()
    {
        return $this->belongsTo(BettingQuoteVersion::class, 'current_quote_version_id');
    }

    public function currentPublishSnapshot()
    {
        return $this->belongsTo(BettingCanonicalPublishSnapshot::class, 'current_publish_snapshot_id');
    }

    public function latestQuoteVersion()
    {
        return $this->hasOne(BettingQuoteVersion::class)->latestOfMany('version_no');
    }

    public function acceptances()
    {
        return $this->hasMany(BettingAcceptance::class);
    }

    public function canonicalResults()
    {
        return $this->hasMany(BettingCanonicalResult::class);
    }

    public function publishSnapshots()
    {
        return $this->hasMany(BettingCanonicalPublishSnapshot::class, 'betting_event_id');
    }

    public function activityLogs()
    {
        return $this->hasMany(BettingActivityLog::class, 'betting_event_id');
    }
}
