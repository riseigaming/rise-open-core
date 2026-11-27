<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CmsStaffPermission extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(CmsUser::class, 'cms_user_id');
    }
}
