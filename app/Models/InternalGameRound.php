<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Existing demo round fields; financial relations and computed appends excluded. */
class InternalGameRound extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['is_demo'=>'boolean','raw_result'=>'array','started_at'=>'datetime','settled_at'=>'datetime','cashed_out_at'=>'datetime','refunded_at'=>'datetime'];
    public function debitTransaction() { return $this->belongsTo(CmsUserTransaction::class,'debit_transaction_id'); }
    public function winTransaction() { return $this->belongsTo(CmsUserTransaction::class,'win_transaction_id'); }
    public function refundTransaction() { return $this->belongsTo(CmsUserTransaction::class,'refund_transaction_id'); }
    public function user() { return $this->belongsTo(CmsUser::class,'cms_user_id'); }
    public function rootUser() { return $this->belongsTo(CmsUser::class,'root_user_id'); }
    public function betLines() { return $this->hasMany(InternalGameRoundBet::class,'internal_game_round_id'); }
    public function crashRound() { return $this->belongsTo(InternalGameCrashRound::class,'internal_game_crash_round_id'); }
    public function session() { return $this->belongsTo(InternalGameSession::class, 'internal_game_session_id'); }
}
