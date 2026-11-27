<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChessBettingMarket extends Model
{
    use HasFactory;

    protected $fillable = [
        'chess_live_event_id',
        'chess_live_board_id',
        'scope_type',
        'template_type',
        'template_variant',
        'title',
        'status',
        'opened_at',
        'closed_at',
        'settled_at',
        'voided_at',
        'settle_reason',
        'settle_note',
        'operator_note',
        'result_selection_code',
        'result_payload',
        'created_by',
        'updated_by',
        'source_ref',
        'meta',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'settled_at' => 'datetime',
        'voided_at' => 'datetime',
        'result_payload' => 'array',
        'meta' => 'array',
    ];

    public function event()
    {
        return $this->belongsTo(ChessLiveEvent::class, 'chess_live_event_id');
    }

    public function board()
    {
        return $this->belongsTo(ChessLiveBoard::class, 'chess_live_board_id');
    }

    public function selections()
    {
        return $this->hasMany(ChessBettingMarketSelection::class, 'chess_betting_market_id')->orderBy('sort_order');
    }

    public function actionLogs()
    {
        return $this->hasMany(ChessBettingMarketActionLog::class, 'chess_betting_market_id')->latest('id');
    }

    public function creator()
    {
        return $this->belongsTo(CmsUser::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(CmsUser::class, 'updated_by');
    }
}
