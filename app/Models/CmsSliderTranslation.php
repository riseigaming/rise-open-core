<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CmsSliderTranslation extends Model
{
    use HasFactory, SoftDeletes;
    protected $guarded = ["id"];
    protected $fillable = [
        "title",
        "description",
        "image_path",
        "image_name",
        "url",
        "cms_language_id",
        "cms_slider_id",
        "created_at",
        "updated_at",
        "deleted_at",
    ];

    public function slider(){
        return $this->belongsTo(CmsSlider::class, "cms_slider_id");
    }
    public function language(){
        return $this->belongsTo(CmsLanguage::class, "cms_language_id");
    }
}
