<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CmsSiteSettingTranslation extends Model
{
    use HasFactory;

    protected $guarded = ["id"];
    protected $fillable = [
        "site_title",
        "site_description",
        "cms_language_id",
        "cms_setting_id",
        "first_modal_text",
    ];

    public function partner(){
        return $this->belongsTo(CmsPartner::class, "cms_partner_id");
    }

    public function language(){
        return $this->belongsTo(CmsLanguage::class, "cms_language_id");
    }
}
