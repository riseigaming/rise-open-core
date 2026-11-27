<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionCertificationParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'production_certification_run_id',
        'cms_user_id',
        'persona',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function run()
    {
        return $this->belongsTo(ProductionCertificationRun::class, 'production_certification_run_id');
    }

    public function user()
    {
        return $this->belongsTo(CmsUser::class, 'cms_user_id');
    }
}
