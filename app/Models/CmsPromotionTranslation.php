<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CmsPromotionTranslation extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ["id"];
    protected $fillable = [
        "listing_title",
        "detail_title",
        "short_description",
        "description",
        "redirect_button_text",
        "cms_promotion_id",
        "cms_language_id",
    ];

    public function promotion(){
        return $this->belongsTo(CmsPromotion::class, "cms_promotion_id");
    }

    public function language(){
        return $this->belongsTo(CmsLanguage::class, "cms_language_id");
    }
}
