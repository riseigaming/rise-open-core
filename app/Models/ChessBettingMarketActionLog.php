<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChessBettingMarketActionLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'chess_betting_market_id',
        'chess_live_event_id',
        'chess_live_board_id',
        'actor_id',
        'action_type',
        'from_state',
        'to_state',
        'reason_type',
        'note',
        'source_ref',
        'source_snapshot',
        'meta',
        'created_at',
    ];

    protected $casts = [
        'source_snapshot' => 'array',
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    public function market()
    {
        return $this->belongsTo(ChessBettingMarket::class, 'chess_betting_market_id');
    }

    public function event()
    {
        return $this->belongsTo(ChessLiveEvent::class, 'chess_live_event_id');
    }

    public function board()
    {
        return $this->belongsTo(ChessLiveBoard::class, 'chess_live_board_id');
    }

    public function actor()
    {
        return $this->belongsTo(CmsUser::class, 'actor_id');
    }
}
