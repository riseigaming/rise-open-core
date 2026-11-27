<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InternalGameRoundBet extends Model
{
    use HasFactory;

    protected $fillable = [
        'internal_game_round_id',
        'line_no',
        'bet_type',
        'bet_key',
        'covered_numbers_json',
        'stake_amount',
        'payout_multiplier',
        'payout_amount',
        'status',
        'meta_json',
    ];

    protected $casts = [
        'line_no' => 'integer',
        'covered_numbers_json' => 'array',
        'stake_amount' => 'float',
        'payout_multiplier' => 'float',
        'payout_amount' => 'float',
        'meta_json' => 'array',
    ];

    public function round()
    {
        return $this->belongsTo(InternalGameRound::class, 'internal_game_round_id');
    }
}
