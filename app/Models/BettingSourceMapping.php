<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BettingSourceMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'betting_feed_id',
        'entity_type',
        'entity_id',
        'source_context',
        'source_key',
        'source_label',
        'source_group',
        'mapped_at',
        'extra',
    ];

    protected $casts = [
        'mapped_at' => 'datetime',
        'extra' => 'array',
    ];

    public function feed()
    {
        return $this->belongsTo(BettingFeed::class, 'betting_feed_id');
    }
}
