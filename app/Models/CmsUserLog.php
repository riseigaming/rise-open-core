<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CmsUserLog extends Model
{
    use HasFactory;
    protected $guarded = ["id"];
    protected $fillable = [
        "log_type",
        "title",
        "description",
        "cms_user_id",
        "cms_operator_id"
    ];

    public function operator(){
        return $this->belongsTo(CmsUser::class, "cms_operator_id");
    }

    public function user(){
        return $this->belongsTo(CmsUser::class, "cms_user_id");
    }
}
