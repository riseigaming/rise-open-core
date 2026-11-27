<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExternalGameRealMoneyEligibilityOverride extends Model
{
    protected $fillable = [
        'cms_user_id',
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
