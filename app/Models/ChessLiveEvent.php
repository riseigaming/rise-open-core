<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChessLiveEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'external_event_key',
        'title',
        'stage_name',
        'round_name',
        'official_status',
        'official_result',
        'official_source_url',
        'standings_snapshot',
        'source_updated_at',
        'meta',
    ];

    protected $casts = [
        'standings_snapshot' => 'array',
        'source_updated_at' => 'datetime',
        'meta' => 'array',
    ];

    public function boards()
    {
        return $this->hasMany(ChessLiveBoard::class, 'chess_live_event_id');
    }

    public function bettingMarkets()
    {
        return $this->hasMany(ChessBettingMarket::class, 'chess_live_event_id');
    }

    public function marketActionLogs()
    {
        return $this->hasMany(ChessBettingMarketActionLog::class, 'chess_live_event_id');
    }
}
