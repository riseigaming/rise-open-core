<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BettingQuoteSelection extends Model
{
    use HasFactory;

    protected $fillable = [
        'betting_quote_version_id',
        'betting_market_id',
        'betting_outcome_id',
        'offer_status',
        'trading_decision_source',
        'decimal_price',
        'base_decimal_price',
        'margin_factor',
        'implied_probability',
        'trading_notes',
        'meta',
        'override_meta',
    ];

    protected $casts = [
        'decimal_price' => 'decimal:6',
        'base_decimal_price' => 'decimal:6',
        'margin_factor' => 'decimal:6',
        'implied_probability' => 'decimal:6',
        'meta' => 'array',
        'override_meta' => 'array',
    ];

    public function quoteVersion()
    {
        return $this->belongsTo(BettingQuoteVersion::class, 'betting_quote_version_id');
    }

    public function market()
    {
        return $this->belongsTo(BettingMarket::class, 'betting_market_id');
    }

    public function outcome()
    {
        return $this->belongsTo(BettingOutcome::class, 'betting_outcome_id');
    }

    public function acceptances()
    {
        return $this->hasMany(BettingAcceptance::class, 'betting_quote_selection_id');
    }
}
