<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BettingMarket extends Model
{
    use HasFactory;

    protected $fillable = [
        'betting_event_id',
        'market_type',
        'specifier_key',
        'line',
        'line_unit',
        'status',
        'suspension_reason',
        'display_name',
        'sort_order',
        'meta',
    ];

    protected $casts = [
        'line' => 'decimal:3',
        'meta' => 'array',
    ];

    public function event()
    {
        return $this->belongsTo(BettingEvent::class, 'betting_event_id');
    }

    public function outcomes()
    {
        return $this->hasMany(BettingOutcome::class);
    }

    public function quoteSelections()
    {
        return $this->hasMany(BettingQuoteSelection::class);
    }
}
