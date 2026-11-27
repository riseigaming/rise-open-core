<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExternalGameRealMoneyEligibilityOverrideAudit extends Model
{
    protected $fillable = [
        'override_id',
        'cms_user_id',
        'action',
        'old_values',
        'new_values',
        'reason',
        'expires_at',
        'actor_id',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'expires_at' => 'datetime',
    ];
}
