<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CmsStaffProfile extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(CmsUser::class, 'cms_user_id');
    }
}
