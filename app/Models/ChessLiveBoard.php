<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChessLiveBoard extends Model
{
    use HasFactory;

    protected $fillable = [
        'chess_live_event_id',
        'external_board_key',
        'board_no',
        'white_player_name',
        'black_player_name',
        'white_rating',
        'black_rating',
        'white_points',
        'black_points',
        'white_clock_seconds',
        'black_clock_seconds',
        'official_status',
        'official_result',
        'official_source_url',
        'featured_flag',
        'source_updated_at',
        'source_ref',
        'meta',
    ];

    protected $casts = [
        'featured_flag' => 'boolean',
        'source_updated_at' => 'datetime',
        'meta' => 'array',
    ];

    public function event()
    {
        return $this->belongsTo(ChessLiveEvent::class, 'chess_live_event_id');
    }

    public function bettingMarkets()
    {
        return $this->hasMany(ChessBettingMarket::class, 'chess_live_board_id');
    }

    public function marketActionLogs()
    {
        return $this->hasMany(ChessBettingMarketActionLog::class, 'chess_live_board_id');
    }
}
