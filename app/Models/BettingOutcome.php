<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BettingOutcome extends Model
{
    use HasFactory;

    protected $fillable = [
        'betting_market_id',
        'outcome_code',
        'display_name',
        'status',
        'sort_order',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function market()
    {
        return $this->belongsTo(BettingMarket::class, 'betting_market_id');
    }

    public function quoteSelections()
    {
        return $this->hasMany(BettingQuoteSelection::class, 'betting_outcome_id');
    }
}
