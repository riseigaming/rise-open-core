<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExternalGameUserRiskLimitOverrideAudit extends Model
{
    protected $fillable = [
        'override_id',
        'cms_user_id',
        'action',
        'old_limits',
        'new_limits',
        'reason',
        'expires_at',
        'actor_id',
    ];

    protected $casts = [
        'old_limits' => 'array',
        'new_limits' => 'array',
        'expires_at' => 'datetime',
    ];
}
