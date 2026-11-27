<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Browser-bound Internal Games session with canonical wallet context. */
class InternalGameSession extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['session_token', 'launch_payload'];
    protected $casts = ['is_demo'=>'boolean','launch_payload'=>'array','launched_at'=>'datetime',
        'last_activity_at'=>'datetime','expires_at'=>'datetime','ended_at'=>'datetime'];
    public function game() { return $this->belongsTo(CmsGame::class, 'cms_game_id'); }
    public function user() { return $this->belongsTo(CmsUser::class,'cms_user_id'); }
    public function rootUser() { return $this->belongsTo(CmsUser::class,'root_user_id'); }
    public function rounds() { return $this->hasMany(InternalGameRound::class); }
}
