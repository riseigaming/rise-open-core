<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CmsSession extends Model
{
    use HasFactory;
    protected $guarded = ["id"];
    protected $hidden = ['remember_token'];
    protected $casts = ['expires_at' => 'datetime', 'is_persistent' => 'boolean'];
    protected $fillable = [
        "os_name",
        "browser_name",
        "user_agent",
        "ip_address",
        "remember_token",
        "cms_user_id",
        "created_at",
        "updated_at",
    ];

    public function user(){
        return $this->belongsTo(CmsUser::class, "cms_user_id");
    }


}
