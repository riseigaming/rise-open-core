<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BettingRawFeed extends Model
{
    use HasFactory;

    protected $fillable = [
        'betting_feed_id',
        'external_batch_ref',
        'ingest_status',
        'pulled_at',
        'processed_at',
        'http_status',
        'checksum',
        'record_count',
        'mapped_count',
        'failed_count',
        'payload',
        'meta',
        'error_message',
    ];

    protected $casts = [
        'pulled_at' => 'datetime',
        'processed_at' => 'datetime',
        'payload' => 'array',
        'meta' => 'array',
    ];

    public function feed()
    {
        return $this->belongsTo(BettingFeed::class, 'betting_feed_id');
    }

    public function quoteVersions()
    {
        return $this->hasMany(BettingQuoteVersion::class, 'origin_raw_feed_id');
    }

    public function canonicalResults()
    {
        return $this->hasMany(BettingCanonicalResult::class, 'betting_raw_feed_id');
    }
}
