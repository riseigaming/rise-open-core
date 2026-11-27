<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChessBettingMarketSelection extends Model
{
    use HasFactory;

    protected $fillable = [
        'chess_betting_market_id',
        'selection_code',
        'label',
        'sort_order',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function market()
    {
        return $this->belongsTo(ChessBettingMarket::class, 'chess_betting_market_id');
    }
}
