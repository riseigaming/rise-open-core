<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternalGameRoundEvent extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $casts = ['payload'=>'array','created_at'=>'datetime'];
}
