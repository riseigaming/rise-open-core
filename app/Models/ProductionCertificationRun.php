<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionCertificationRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'run_key',
        'status',
        'created_by',
        'expires_at',
        'revoked_at',
        'closed_at',
        'notes',
        'meta',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'closed_at' => 'datetime',
        'meta' => 'array',
    ];

    public function participants()
    {
        return $this->hasMany(ProductionCertificationParticipant::class, 'production_certification_run_id');
    }
}
