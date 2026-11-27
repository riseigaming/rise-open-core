<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CmsState extends Model
{
    use HasFactory;

    public function country(){
        return $this->belongsTo(CmsCountry::class, "country_id");
    }

    public function cities(){
        return $this->hasMany(CmsCity::class, "state_id");
    }
}
