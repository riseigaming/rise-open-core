<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CmsLanguage extends Model
{
    use HasFactory;
    protected $guarded = ["id"];
    protected $fillable = [
        "name",
        "code",
        "status",
        "is_default",
    ];

}
