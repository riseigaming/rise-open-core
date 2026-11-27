<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class GameCurationItem extends Model
{
    protected $guarded = ['id'];
    public function game() { return $this->belongsTo(CmsGame::class); }
}
