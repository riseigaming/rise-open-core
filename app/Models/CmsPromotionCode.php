<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CmsPromotionCode extends Model
{
    use HasFactory, SoftDeletes;
    protected $guarded = ["id"];
    protected $fillable = [
        "promo_code",
        "currency",
        "promo_amount",
        "start_date",
        "end_date",
        "promo_count",
        "promo_left",
        "status",
        "created_at",
        "updated_at",
        "deleted_at",
    ];


}
