<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class GameCurationSurface extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['auto_fill_enabled'=>'boolean'];
}
