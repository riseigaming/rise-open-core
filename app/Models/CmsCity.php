<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CmsCity extends Model
{
    use HasFactory;

    protected $guarded = ["id"];

    public function country(){
        return $this->belongsTo(CmsCountry::class, "country_id");
    }

    public function stage(){
        return $this->belongsTo(CmsState::class, "state_id");
    }
}
