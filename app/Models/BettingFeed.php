<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BettingFeed extends Model
{
    use HasFactory;

    protected $fillable = [
        'feed_key',
        'display_name',
        'adapter_key',
        'mode',
        'status',
        'priority',
        'supports_prematch',
        'settings',
        'last_pulled_at',
        'last_ingested_at',
    ];

    protected $casts = [
        'supports_prematch' => 'boolean',
        'settings' => 'array',
        'last_pulled_at' => 'datetime',
        'last_ingested_at' => 'datetime',
    ];

    public function rawFeeds()
    {
        return $this->hasMany(BettingRawFeed::class);
    }

    public function sourceMappings()
    {
        return $this->hasMany(BettingSourceMapping::class);
    }

    public function canonicalResults()
    {
        return $this->hasMany(BettingCanonicalResult::class);
    }

    public function quoteVersions()
    {
        return $this->hasMany(BettingQuoteVersion::class);
    }
}
