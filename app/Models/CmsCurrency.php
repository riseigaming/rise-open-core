<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CmsCurrency extends Model
{
    use HasFactory, SoftDeletes;
    protected  $guarded = ["id"];
    protected $fillable =  [
        "name",
        "code",
        "symbol",
        "rate",
        "is_default",
        "status",
        "created_at",
        "updated_at",
        "deleted_at",
    ];
}
