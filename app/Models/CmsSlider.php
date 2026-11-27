<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CmsSlider extends Model
{
    use HasFactory, SoftDeletes;

    public const PLACEMENT_HOME = "home";

    protected $guarded = ["id"];
    protected $fillable = [
        "placement",
        "rank",
        "status",
        "theme_key",
        "image_path",
        "image_name",
        "use_same_image",
        "created_at",
        "updated_at",
        "deleted_at",
    ];

    public static function placements(): array
    {
        return [
            self::PLACEMENT_HOME,
        ];
    }

    public function translations(){
        return $this->hasMany(CmsSliderTranslation::class, "cms_slider_id");
    }

    public function translation(){
        return $this->hasOne(CmsSliderTranslation::class, "cms_slider_id")->where("cms_language_id", session("currentLanguageId"));
    }
}
