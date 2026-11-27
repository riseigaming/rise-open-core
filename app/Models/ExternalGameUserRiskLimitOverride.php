<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExternalGameUserRiskLimitOverride extends Model
{
    protected $fillable = [
        'cms_user_id',
        'max_single_bet',
        'rolling_24h_stake_limit',
        'rolling_24h_net_loss_limit',
        'max_open_external_sessions',
        'starts_at',
        'expires_at',
        'reason',
        'is_active',
        'created_by',
        'updated_by',
        'revoked_at',
        'revoked_by',
        'revoke_reason',
    ];

    protected $casts = [
        'max_single_bet' => 'float',
        'rolling_24h_stake_limit' => 'float',
        'rolling_24h_net_loss_limit' => 'float',
        'max_open_external_sessions' => 'integer',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'revoked_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(CmsUser::class, 'cms_user_id');
    }

    public function creator()
    {
        return $this->belongsTo(CmsUser::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(CmsUser::class, 'updated_by');
    }
}
